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
use App\Models\User;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ZipArchive;

class ExcelImportService
{
    public const TYPE_MAP = [
        'PRODUK' => 'PRODUCTS',
        'PRODUCTS' => 'PRODUCTS',
        'PRODUCT' => 'PRODUCTS',
        'ANGGOTA' => 'MEMBERS',
        'MEMBERS' => 'MEMBERS',
        'MEMBER' => 'MEMBERS',
        'STOK' => 'STOCK_OPENING',
        'STOCK' => 'STOCK_OPENING',
        'STOCK_OPENING' => 'STOCK_OPENING',
        'PEMASOK' => 'SUPPLIERS',
        'SUPPLIERS' => 'SUPPLIERS',
        'SUPPLIER' => 'SUPPLIERS',
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

    private function normalizeHeaderName(string $header): string
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $clean = strtolower(trim((string) $clean));
        $clean = preg_replace('/[^a-z0-9_]+/', '_', $clean);

        return trim((string) $clean, '_');
    }

    private function pickValue(array $raw, array $aliases, mixed $default = ''): mixed
    {
        foreach ($aliases as $key) {
            if (array_key_exists($key, $raw) && $raw[$key] !== null && trim((string) $raw[$key]) !== '') {
                return $raw[$key];
            }
        }

        return $default;
    }

    private function normalizeRowKeys(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $key = $this->normalizeHeaderName((string) $k);
            if ($key !== '') {
                $out[$key] = is_string($v) ? trim($v) : $v;
            }
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
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (! empty($data)) {
                $rows[] = array_values($data);
            }
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

        $sparseRows = [];
        $maxCol = 0;
        foreach ($sheet->sheetData->row as $row) {
            $rowCells = [];
            $nextCol = 0;
            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colLetters = preg_replace('/[^A-Za-z]/', '', $ref);
                if ($colLetters !== '') {
                    $colIndex = $this->columnLettersToIndex($colLetters);
                    $nextCol = $colIndex + 1;
                } else {
                    $colIndex = $nextCol;
                    $nextCol++;
                }

                $maxCol = max($maxCol, $colIndex);
                $rowCells[$colIndex] = trim($this->extractXlsxCellValue($cell, $sharedStrings));
            }

            $hasContent = false;
            foreach ($rowCells as $v) {
                if ($v !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if ($hasContent) {
                $sparseRows[] = $rowCells;
            }
        }

        $rows = [];
        foreach ($sparseRows as $rowCells) {
            $dense = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $dense[$c] = $rowCells[$c] ?? '';
            }
            $rows[] = $dense;
        }

        return $this->rowsArrayToAssociative($rows);
    }

    private function extractXlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        if ($type === 's') {
            $idx = (int) ($cell->v ?? -1);

            return (string) ($sharedStrings[$idx] ?? '');
        }
        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return (string) $cell->is->t;
            }
            if (isset($cell->is->r)) {
                $text = '';
                foreach ($cell->is->r as $run) {
                    $text .= (string) ($run->t ?? '');
                }

                return $text;
            }

            return '';
        }

        return (string) ($cell->v ?? '');
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

        $headers = [];
        foreach ($rows[0] as $idx => $h) {
            $headers[$idx] = $this->normalizeHeaderName((string) $h);
        }

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
        $sku = trim((string) $this->pickValue($raw, ['sku', 'kode_sku', 'kode_produk', 'kode_barang', 'kode']));
        $barcode = trim((string) $this->pickValue($raw, ['barcode'])) ?: null;
        $name = trim((string) $this->pickValue($raw, ['name', 'nama_produk', 'nama_barang', 'nama', 'produk']));
        $categoryName = trim((string) $this->pickValue($raw, ['category_name', 'category', 'kategori'], 'Umum'));
        $unit = trim((string) $this->pickValue($raw, ['unit', 'satuan'], 'pcs')) ?: 'pcs';
        $sellingPrice = $this->pickValue($raw, ['selling_price', 'harga_jual', 'harga', 'price'], null);
        $currentCost = $this->pickValue($raw, ['current_cost', 'harga_pokok', 'harga_modal', 'harga_beli', 'modal', 'hpp', 'cost'], 0);
        $reorderPoint = (int) $this->pickValue($raw, ['reorder_point', 'min_stock', 'min_stok', 'stok_minimum'], 5);
        $initialStock = (int) $this->pickValue($raw, ['initial_stock', 'stok_awal', 'stok', 'stock'], 0);

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
        $employeeNumber = trim((string) $this->pickValue($raw, ['employee_number', 'nim_nip', 'nip', 'nim', 'id_karyawan', 'member_number', 'no_anggota', 'nomor_anggota', 'kode_anggota']));
        $memberNumber = trim((string) $this->pickValue($raw, ['member_number', 'no_anggota', 'nomor_anggota', 'kode_anggota', 'nim_nip', 'nim', 'nip', 'employee_number', 'id_karyawan'], $employeeNumber));
        $fullName = trim((string) $this->pickValue($raw, ['full_name', 'nama_lengkap', 'name', 'nama']));
        $department = trim((string) $this->pickValue($raw, ['department', 'jurusan', 'unit_kerja', 'divisi', 'unit'], 'Umum')) ?: 'Umum';
        $email = strtolower(trim((string) $this->pickValue($raw, ['email'])));

        if ($employeeNumber === '') {
            $errors[] = ['field' => 'employee_number', 'code' => 'REQUIRED', 'message' => 'NIM / NIP / Nomor Pegawai wajib diisi.'];
        } elseif (Member::where('employee_number', $employeeNumber)->exists()) {
            $errors[] = ['field' => 'employee_number', 'code' => 'DUPLICATE_DB', 'message' => "NIM / NIP '{$employeeNumber}' sudah terdaftar."];
        }

        if ($memberNumber === '') {
            $errors[] = ['field' => 'member_number', 'code' => 'REQUIRED', 'message' => 'Nomor anggota wajib diisi.'];
        } elseif (Member::where('member_number', $memberNumber)->exists()) {
            $errors[] = ['field' => 'member_number', 'code' => 'DUPLICATE_DB', 'message' => "Nomor anggota '{$memberNumber}' sudah terdaftar."];
        }

        if ($fullName === '') {
            $errors[] = ['field' => 'full_name', 'code' => 'REQUIRED', 'message' => 'Nama lengkap anggota wajib diisi.'];
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'email', 'code' => 'INVALID_EMAIL', 'message' => 'Format email tidak valid.'];
        }

        $normalized = [
            'employee_number' => $employeeNumber,
            'member_number' => $memberNumber,
            'full_name' => $fullName,
            'department' => $department,
            'email' => $email !== '' ? $email : null,
        ];

        return [$normalized, $errors, $employeeNumber !== '' ? $employeeNumber : null];
    }

    private function validateStockOpeningRow(array $raw): array
    {
        $errors = [];
        $sku = trim((string) $this->pickValue($raw, ['sku', 'kode_sku', 'kode_produk', 'kode_barang', 'kode']));
        $qty = $this->pickValue($raw, ['quantity', 'on_hand', 'qty', 'stok_fisik', 'stok_awal', 'stok', 'jumlah'], null);
        $unitCost = $this->pickValue($raw, ['unit_cost', 'current_cost', 'harga_modal_baru', 'harga_modal', 'harga_beli', 'hpp', 'cost'], null);

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
        $code = trim((string) $this->pickValue($raw, ['code', 'kode', 'kode_pemasok']));
        $name = trim((string) $this->pickValue($raw, ['name', 'nama', 'nama_pemasok']));
        $phone = trim((string) $this->pickValue($raw, ['phone', 'kontak', 'telepon', 'no_hp'])) ?: null;
        $address = trim((string) $this->pickValue($raw, ['address', 'alamat'])) ?: null;

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
        $userId = null;
        if (! empty($data['email'])) {
            $email = strtolower(trim((string) $data['email']));
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $user) {
                $user = new User(['email' => $email]);
                $user->password_hash = Hash::make('password123');
                $user->status = 'active';
                $user->save();
            }
            $user->roles()->syncWithoutDetaching(['MEMBER']);
            $userId = $user->id;
        }

        $member = Member::create([
            'user_id' => $userId,
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
