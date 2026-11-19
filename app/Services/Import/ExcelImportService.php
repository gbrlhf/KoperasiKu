<?php

namespace App\Services\Import;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\ImportRow;
use App\Models\InventoryBalance;
use App\Models\Member;
use App\Models\MemberCreditAccount;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class ExcelImportService
{
    public const TYPE_MAP = [
        'PRODUK' => 'PRODUCTS',
        'PRODUCTS' => 'PRODUCTS',
        'ANGGOTA' => 'MEMBERS',
        'MEMBERS' => 'MEMBERS',
        'STOK' => 'STOCK_OPENING',
        'STOCK_OPENING' => 'STOCK_OPENING',
        'PEMASOK' => 'SUPPLIERS',
        'SUPPLIERS' => 'SUPPLIERS',
    ];

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditService $auditService
    ) {}

    public static function normalizeType(string $type): string
    {
        $upper = strtoupper(trim($type));

        return self::TYPE_MAP[$upper] ?? $upper;
    }

    /**
     * Upload & parse file Excel/CSV ke staging import_rows, lalu jalankan validasi otomatis.
     */
    public function upload(UploadedFile $file, string $type, ?string $cutoffAt, ?string $actorUserId): ImportBatch
    {
        $normalizedType = self::normalizeType($type);
        $rawRows = $this->readFileRows($file);

        $batch = DB::transaction(function () use ($file, $normalizedType, $cutoffAt, $actorUserId, $rawRows): ImportBatch {
            $checksum = @hash_file('sha256', $file->getRealPath()) ?: hash('sha256', $file->getClientOriginalName().now()->timestamp);
            $batch = ImportBatch::create([
                'import_type' => $normalizedType,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => 'imports/'.$file->getClientOriginalName(),
                'checksum' => $checksum,
                'status' => 'UPLOADED',
                'total_rows' => count($rawRows),
                'valid_rows' => 0,
                'error_rows' => 0,
                'duplicate_rows' => 0,
                'cutoff_at' => $cutoffAt,
                'uploaded_by' => $actorUserId,
            ]);

            foreach ($rawRows as $index => $payload) {
                ImportRow::create([
                    'batch_id' => $batch->id,
                    'row_number' => $index + 2,
                    'raw_data' => $payload,
                    'normalized_data' => null,
                    'row_status' => 'PENDING',
                ]);
            }

            return $batch;
        });

        return $this->validateBatch($batch);
    }

    /**
     * Validasi baris staging untuk mendeteksi duplikat & error field.
     */
    public function validateBatch(ImportBatch $batch): ImportBatch
    {
        if ($batch->status === 'COMMITTED') {
            throw new BusinessRuleException('Batch impor sudah di-commit dan tidak dapat divalidasi ulang.');
        }

        return DB::transaction(function () use ($batch): ImportBatch {
            $batch->load('rows');

            $validCount = 0;
            $errorCount = 0;
            $duplicateCount = 0;
            $seenKeys = [];

            foreach ($batch->rows as $row) {
                $row->errors()->delete();
                $raw = $this->normalizeRowKeys($row->raw_data ?? []);

                [$normalized, $errors, $uniqueKey] = match ($batch->import_type) {
                    'PRODUCTS' => $this->validateProductRow($raw),
                    'MEMBERS' => $this->validateMemberRow($raw),
                    'STOCK_OPENING' => $this->validateStockOpeningRow($raw),
                    'SUPPLIERS' => $this->validateSupplierRow($raw),
                    default => [[], [['field' => 'type', 'code' => 'INVALID_TYPE', 'message' => 'Tipe impor tidak dikenali']], null],
                };

                if ($uniqueKey !== null) {
                    if (isset($seenKeys[$uniqueKey])) {
                        $errors[] = [
                            'field' => 'unique_key',
                            'code' => 'DUPLICATE_IN_FILE',
                            'message' => "Duplikat kunci '{$uniqueKey}' ditemukan di dalam file yang sama (baris {$seenKeys[$uniqueKey]}).",
                        ];
                    } else {
                        $seenKeys[$uniqueKey] = $row->row_number;
                    }
                }

                $hasDuplicateError = collect($errors)->contains(fn ($e) => str_starts_with($e['code'], 'DUPLICATE'));

                if (empty($errors)) {
                    $row->update([
                        'source_key' => $uniqueKey,
                        'normalized_data' => $normalized,
                        'row_status' => 'VALID',
                    ]);
                    $validCount++;
                } else {
                    foreach ($errors as $err) {
                        ImportError::create([
                            'import_row_id' => $row->id,
                            'field_name' => $err['field'],
                            'error_code' => $err['code'],
                            'error_message' => $err['message'],
                        ]);
                    }

                    $status = $hasDuplicateError ? 'DUPLICATE' : 'INVALID';
                    $row->update([
                        'source_key' => $uniqueKey,
                        'normalized_data' => $normalized,
                        'row_status' => $status,
                    ]);

                    if ($hasDuplicateError) {
                        $duplicateCount++;
                    }
                    $errorCount++;
                }
            }

            $batch->update([
                'status' => $errorCount === 0 && $batch->total_rows > 0 ? 'VALIDATED' : 'FAILED',
                'valid_rows' => $validCount,
                'error_rows' => $errorCount,
                'duplicate_rows' => $duplicateCount,
            ]);

            return $batch->fresh(['rows.errors']);
        });
    }

    /**
     * Commit batch impor ke tabel master secara transaksional.
     */
    public function commitBatch(ImportBatch $batch, ?string $actorUserId): ImportBatch
    {
        if ($batch->status === 'COMMITTED') {
            throw new BusinessRuleException('Batch impor ini sudah pernah di-commit sebelumnya.');
        }

        if ($batch->valid_rows <= 0) {
            throw new BusinessRuleException('Tidak ada baris valid yang dapat disimpan pada batch impor ini.');
        }

        return DB::transaction(function () use ($batch, $actorUserId): ImportBatch {
            $lockedBatch = ImportBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($lockedBatch->status === 'COMMITTED') {
                throw new BusinessRuleException('Batch impor ini sudah pernah di-commit.');
            }

            $rows = ImportRow::query()
                ->where('batch_id', $lockedBatch->id)
                ->where('row_status', 'VALID')
                ->orderBy('row_number')
                ->get();

            foreach ($rows as $row) {
                $data = $row->normalized_data;

                match ($lockedBatch->import_type) {
                    'PRODUCTS' => $this->commitProductRow($data, $lockedBatch->id, $actorUserId),
                    'MEMBERS' => $this->commitMemberRow($data),
                    'STOCK_OPENING' => $this->commitStockOpeningRow($data, $lockedBatch->id, $actorUserId),
                    'SUPPLIERS' => $this->commitSupplierRow($data),
                };

                $row->update(['row_status' => 'COMMITTED']);
            }

            $lockedBatch->update([
                'status' => 'COMMITTED',
                'committed_at' => now(),
            ]);

            $this->auditService->record(
                action: 'EXCEL_IMPORT_COMMITTED',
                entityType: 'import_batch',
                entityId: (string) $lockedBatch->id,
                oldValues: null,
                newValues: [
                    'type' => $lockedBatch->import_type,
                    'valid_rows' => $lockedBatch->valid_rows,
                    'cutoff_at' => $lockedBatch->cutoff_at?->toIso8601String(),
                ],
                actorId: $actorUserId
            );

            return $lockedBatch->fresh();
        });
    }

    private function normalizeRowKeys(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $key = Str::slug((string) $k, '_');
            $out[$key] = is_string($v) ? trim($v) : $v;
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readFileRows(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $path = $file->getRealPath();

        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->readCsvRows($path);
        }

        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            return $this->rowsArrayToAssociative($rows);
        }

        if ($ext === 'xlsx' && class_exists(ZipArchive::class)) {
            return $this->readXlsxRowsNative($path);
        }

        return $this->readCsvRows($path);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [];
        }
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $this->rowsArrayToAssociative($rows);
    }

    /**
     * Native XLSX parser using ZipArchive & SimpleXML (no external vendor dependency required).
     *
     * @return array<int, array<string, mixed>>
     */
    private function readXlsxRowsNative(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sx = @simplexml_load_string($sharedStringsXml);
            if ($sx !== false && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) ($r->t ?? '');
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false || ! isset($sheet->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colLetters = preg_replace('/[0-9]+/', '', $ref);
                $colIndex = $this->columnLettersToIndex($colLetters);

                $type = (string) ($cell['t'] ?? '');
                $val = (string) ($cell->v ?? '');
                if ($type === 's') {
                    $idx = (int) $val;
                    $val = $sharedStrings[$idx] ?? $val;
                } elseif ($type === 'inlineStr') {
                    $val = (string) ($cell->is->t ?? '');
                }

                while (count($rowData) < $colIndex) {
                    $rowData[] = '';
                }
                $rowData[$colIndex] = $val;
            }
            $rows[] = $rowData;
        }

        return $this->rowsArrayToAssociative($rows);
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rowsArrayToAssociative(array $rows): array
    {
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map(function ($h) {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $h));

            return Str::slug($clean, '_');
        }, $rows[0]);

        $result = [];
        foreach (array_slice($rows, 1) as $row) {
            $isEmpty = collect($row)->every(fn ($v) => trim((string) $v) === '');
            if ($isEmpty) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $idx => $key) {
                if ($key !== '') {
                    $assoc[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : null;
                }
            }
            $result[] = $assoc;
        }

        return $result;
    }

    private function validateProductRow(array $raw): array
    {
        $errors = [];
        $sku = trim((string) ($raw['sku'] ?? $raw['kode_sku'] ?? $raw['kode'] ?? ''));
        $barcode = trim((string) ($raw['barcode'] ?? '')) ?: null;
        $name = trim((string) ($raw['name'] ?? $raw['nama_produk'] ?? $raw['nama'] ?? ''));
        $categoryName = trim((string) ($raw['category_name'] ?? $raw['category'] ?? $raw['kategori'] ?? 'Umum'));
        $unit = trim((string) ($raw['unit'] ?? $raw['satuan'] ?? 'pcs')) ?: 'pcs';
        $sellingPrice = $raw['selling_price'] ?? $raw['harga_jual'] ?? $raw['price'] ?? null;
        $currentCost = $raw['current_cost'] ?? $raw['harga_pokok'] ?? $raw['hpp'] ?? $raw['cost'] ?? 0;
        $reorderPoint = (int) ($raw['reorder_point'] ?? $raw['min_stock'] ?? $raw['stok_minimum'] ?? 5);
        $initialStock = (int) ($raw['initial_stock'] ?? $raw['stok_awal'] ?? $raw['stok'] ?? 0);

        if ($sku === '') {
            $errors[] = ['field' => 'sku', 'code' => 'REQUIRED', 'message' => 'SKU wajib diisi.'];
        } elseif (Product::where('sku', $sku)->exists()) {
            $errors[] = ['field' => 'sku', 'code' => 'DUPLICATE_DB', 'message' => "SKU '{$sku}' sudah terdaftar di database."];
        }

        if ($barcode !== null && Product::where('barcode', $barcode)->exists()) {
            $errors[] = ['field' => 'barcode', 'code' => 'DUPLICATE_BARCODE', 'message' => "Barcode '{$barcode}' sudah digunakan produk lain."];
        }

        if ($name === '') {
            $errors[] = ['field' => 'name', 'code' => 'REQUIRED', 'message' => 'Nama produk wajib diisi.'];
        }

        if (! is_numeric($sellingPrice) || (float) $sellingPrice < 0) {
            $errors[] = ['field' => 'selling_price', 'code' => 'INVALID_PRICE', 'message' => 'Harga jual harus berupa angka >= 0.'];
        }

        if (! is_numeric($currentCost) || (float) $currentCost < 0) {
            $errors[] = ['field' => 'current_cost', 'code' => 'INVALID_COST', 'message' => 'Harga pokok (HPP) harus berupa angka >= 0.'];
        }

        $normalized = [
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $name,
            'category_name' => $categoryName ?: 'Umum',
            'unit' => $unit,
            'selling_price' => is_numeric($sellingPrice) ? round((float) $sellingPrice, 2) : 0,
            'current_cost' => is_numeric($currentCost) ? round((float) $currentCost, 2) : 0,
            'reorder_point' => max(0, $reorderPoint),
            'initial_stock' => max(0, $initialStock),
        ];

        return [$normalized, $errors, $sku !== '' ? $sku : null];
    }

    private function validateMemberRow(array $raw): array
    {
        $errors = [];
        $employeeNumber = trim((string) ($raw['employee_number'] ?? $raw['nip'] ?? $raw['nim'] ?? $raw['member_number'] ?? $raw['no_anggota'] ?? ''));
        $memberNumber = trim((string) ($raw['member_number'] ?? $raw['no_anggota'] ?? $employeeNumber));
        $fullName = trim((string) ($raw['full_name'] ?? $raw['nama_lengkap'] ?? $raw['name'] ?? $raw['nama'] ?? ''));
        $department = trim((string) ($raw['department'] ?? $raw['unit_kerja'] ?? $raw['divisi'] ?? 'Umum')) ?: 'Umum';

        if ($employeeNumber === '') {
            $errors[] = ['field' => 'employee_number', 'code' => 'REQUIRED', 'message' => 'NIP / Nomor Pegawai wajib diisi.'];
        } elseif (Member::where('employee_number', $employeeNumber)->exists()) {
            $errors[] = ['field' => 'employee_number', 'code' => 'DUPLICATE_DB', 'message' => "NIP '{$employeeNumber}' sudah terdaftar."];
        }

        if ($memberNumber === '') {
            $errors[] = ['field' => 'member_number', 'code' => 'REQUIRED', 'message' => 'Nomor anggota wajib diisi.'];
        } elseif (Member::where('member_number', $memberNumber)->exists()) {
            $errors[] = ['field' => 'member_number', 'code' => 'DUPLICATE_DB', 'message' => "Nomor anggota '{$memberNumber}' sudah terdaftar."];
        }

        if ($fullName === '') {
            $errors[] = ['field' => 'full_name', 'code' => 'REQUIRED', 'message' => 'Nama lengkap anggota wajib diisi.'];
        }

        $normalized = [
            'employee_number' => $employeeNumber,
            'member_number' => $memberNumber,
            'full_name' => $fullName,
            'department' => $department,
        ];

        return [$normalized, $errors, $employeeNumber !== '' ? $employeeNumber : null];
    }

    private function validateStockOpeningRow(array $raw): array
    {
        $errors = [];
        $sku = trim((string) ($raw['sku'] ?? $raw['kode_sku'] ?? ''));
        $qty = $raw['quantity'] ?? $raw['on_hand'] ?? $raw['qty'] ?? $raw['stok'] ?? null;
        $unitCost = $raw['unit_cost'] ?? $raw['current_cost'] ?? $raw['hpp'] ?? null;

        $product = $sku !== '' ? Product::where('sku', $sku)->first() : null;
        if (! $product) {
            $errors[] = ['field' => 'sku', 'code' => 'PRODUCT_NOT_FOUND', 'message' => "Produk dengan SKU '{$sku}' tidak ditemukan."];
        }

        if (! is_numeric($qty) || (int) $qty <= 0) {
            $errors[] = ['field' => 'quantity', 'code' => 'INVALID_QTY', 'message' => 'Kuantitas saldo awal harus bilangan bulat > 0.'];
        }

        $normalized = [
            'product_id' => $product?->id,
            'sku' => $sku,
            'quantity' => is_numeric($qty) ? (int) $qty : 0,
            'unit_cost' => is_numeric($unitCost) ? round((float) $unitCost, 2) : (float) ($product?->current_cost ?? 0),
        ];

        return [$normalized, $errors, $sku !== '' ? $sku : null];
    }

    private function validateSupplierRow(array $raw): array
    {
        $errors = [];
        $code = trim((string) ($raw['code'] ?? $raw['kode'] ?? $raw['kode_pemasok'] ?? ''));
        $name = trim((string) ($raw['name'] ?? $raw['nama'] ?? $raw['nama_pemasok'] ?? ''));
        $phone = trim((string) ($raw['phone'] ?? $raw['telepon'] ?? $raw['no_hp'] ?? '')) ?: null;
        $address = trim((string) ($raw['address'] ?? $raw['alamat'] ?? '')) ?: null;

        if ($code === '' && $name !== '') {
            $code = 'SUP-'.strtoupper(Str::random(5));
        }

        if ($code === '') {
            $errors[] = ['field' => 'code', 'code' => 'REQUIRED', 'message' => 'Kode pemasok wajib diisi.'];
        } elseif (Supplier::where('code', $code)->exists()) {
            $errors[] = ['field' => 'code', 'code' => 'DUPLICATE_DB', 'message' => "Kode pemasok '{$code}' sudah terdaftar."];
        }

        if ($name === '') {
            $errors[] = ['field' => 'name', 'code' => 'REQUIRED', 'message' => 'Nama pemasok wajib diisi.'];
        }

        $normalized = [
            'code' => $code,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
        ];

        return [$normalized, $errors, $code !== '' ? $code : null];
    }

    private function commitProductRow(array $data, string $batchId, ?string $actorUserId): void
    {
        $category = Category::firstOrCreate(['name' => $data['category_name']]);

        $product = Product::create([
            'category_id' => $category->id,
            'category' => $category->name,
            'sku' => $data['sku'],
            'barcode' => $data['barcode'],
            'name' => $data['name'],
            'unit' => $data['unit'],
            'selling_price' => $data['selling_price'],
            'current_cost' => $data['current_cost'],
            'reorder_point' => $data['reorder_point'],
            'is_active' => true,
        ]);

        InventoryBalance::create([
            'product_id' => $product->id,
            'on_hand' => 0,
            'reserved' => 0,
        ]);

        if (! empty($data['initial_stock']) && (int) $data['initial_stock'] > 0) {
            $this->inventoryService->receive(
                productId: $product->id,
                quantity: (int) $data['initial_stock'],
                reason: 'PURCHASE_RECEIPT',
                actorUserId: (string) ($actorUserId ?? 'SYSTEM'),
                sourceReference: $batchId
            );
        }
    }

    private function commitMemberRow(array $data): void
    {
        $member = Member::create([
            'employee_number' => $data['employee_number'],
            'member_number' => $data['member_number'],
            'full_name' => $data['full_name'],
            'department' => $data['department'],
            'status' => 'ACTIVE',
        ]);

        MemberCreditAccount::create([
            'member_id' => $member->id,
            'status' => 'ACTIVE',
        ]);
    }

    private function commitStockOpeningRow(array $data, string $batchId, ?string $actorUserId): void
    {
        $this->inventoryService->receive(
            productId: $data['product_id'],
            quantity: (int) $data['quantity'],
            reason: 'PURCHASE_RECEIPT',
            actorUserId: (string) ($actorUserId ?? 'SYSTEM'),
            sourceReference: $batchId
        );

        if (isset($data['unit_cost']) && $data['unit_cost'] > 0) {
            Product::whereKey($data['product_id'])->update([
                'current_cost' => $data['unit_cost'],
            ]);
        }
    }

    private function commitSupplierRow(array $data): void
    {
        Supplier::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => true,
        ]);
    }
}
