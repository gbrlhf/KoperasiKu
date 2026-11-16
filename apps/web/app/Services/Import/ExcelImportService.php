<?php

namespace App\Services\Import;

use App\Contracts\InventoryServiceInterface;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\ImportRow;
use App\Models\Member;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Pricing\WeightedAverageCostService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportService
{
    public function __construct(
        protected InventoryServiceInterface $inventoryService,
        protected WeightedAverageCostService $wacService
    ) {}

    public function stageUpload(
        UploadedFile $file,
        string $importType,
        string $actorUserId,
        ?string $cutoffAt = null
    ): ImportBatch {
        $importType = strtoupper($importType);
        $storedPath = $file->store('imports');
        $fullPath = Storage::path($storedPath);

        $checksum = hash_file('sha256', $fullPath);
        $alreadyCommitted = ImportBatch::where('checksum', $checksum)
            ->where('import_type', $importType)
            ->where('status', 'COMMITTED')
            ->exists();

        $spreadsheet = IOFactory::load($fullPath);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (count($sheetData) < 2) {
            throw new \InvalidArgumentException('File Excel/CSV kosong atau tidak memiliki baris data setelah header.');
        }

        $rawHeaders = array_shift($sheetData);
        $headers = array_map(function ($h, $idx) {
            $noBom = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            $clean = Str::snake(trim($noBom));
            return $clean !== '' ? $clean : "col_{$idx}";
        }, $rawHeaders, array_keys($rawHeaders));

        return DB::transaction(function () use (
            $file, $storedPath, $checksum, $importType, $actorUserId, $cutoffAt, $sheetData, $headers, $alreadyCommitted
        ) {
            $batch = ImportBatch::create([
                'uploaded_by' => $actorUserId,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'checksum' => $checksum,
                'import_type' => $importType,
                'status' => 'UPLOADED',
                'cutoff_at' => $cutoffAt,
                'column_mapping' => array_combine($headers, $headers),
                'quality_metadata' => [
                    'duplicate_file_warning' => $alreadyCommitted,
                ],
            ]);

            $totalRows = 0;
            foreach ($sheetData as $index => $rowValues) {
                if (count(array_filter($rowValues, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $totalRows++;
                $assoc = [];
                foreach ($headers as $colIdx => $headerName) {
                    $assoc[$headerName] = isset($rowValues[$colIdx]) ? trim((string) $rowValues[$colIdx]) : null;
                }

                ImportRow::create([
                    'batch_id' => $batch->id,
                    'row_number' => $index + 2,
                    'row_status' => 'PENDING',
                    'raw_data' => $assoc,
                ]);
            }

            $batch->update(['total_rows' => $totalRows]);

            return $this->validateBatch($batch);
        });
    }

    public function validateBatch(ImportBatch $batch, ?array $customMapping = null): ImportBatch
    {
        if ($batch->status === 'COMMITTED') {
            return $batch->load('rows.errors');
        }

        if ($customMapping) {
            $batch->update(['column_mapping' => $customMapping]);
        }

        $mapping = $batch->column_mapping ?? [];
        $rows = $batch->rows()->orderBy('row_number')->get();

        $validCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        $costMissingCount = 0;
        $seenKeys = [];

        foreach ($rows as $row) {
            $row->errors()->delete();

            $mapped = [];
            foreach ($row->raw_data as $rawKey => $val) {
                $targetKey = $mapping[$rawKey] ?? $rawKey;
                $mapped[$targetKey] = $val;
            }

            [$status, $sourceKey, $normalized, $errors, $flags] = match ($batch->import_type) {
                'MEMBERS' => $this->validateMemberRow($mapped, $seenKeys),
                'SUPPLIERS' => $this->validateSupplierRow($mapped, $seenKeys),
                'PRODUCTS' => $this->validateProductRow($mapped, $seenKeys),
                'STOCK_OPENING' => $this->validateStockOpeningRow($mapped, $seenKeys),
                default => ['INVALID', null, null, [['field' => 'import_type', 'code' => 'UNSUPPORTED_TYPE', 'msg' => "Tipe import {$batch->import_type} tidak didukung."]], []],
            };

            if ($sourceKey !== null) {
                $seenKeys[$sourceKey] = true;
            }

            if (!empty($flags['cost_missing'])) {
                $costMissingCount++;
            }

            $row->update([
                'row_status' => $status,
                'source_key' => $sourceKey,
                'normalized_data' => $normalized,
            ]);

            foreach ($errors as $err) {
                ImportError::create([
                    'import_row_id' => $row->id,
                    'field_name' => $err['field'],
                    'error_code' => $err['code'],
                    'error_message' => $err['msg'],
                ]);
            }

            if ($status === 'VALID') {
                $validCount++;
            } elseif ($status === 'DUPLICATE') {
                $duplicateCount++;
            } else {
                $errorCount++;
            }
        }

        $meta = $batch->quality_metadata ?? [];
        $meta['cost_missing_rows'] = $costMissingCount;

        $batch->update([
            'status' => 'PREVIEW_READY',
            'valid_rows' => $validCount,
            'error_rows' => $errorCount,
            'duplicate_rows' => $duplicateCount,
            'quality_metadata' => $meta,
        ]);

        return $batch->fresh(['rows.errors']);
    }

    public function commitBatch(ImportBatch $batch, string $actorUserId): ImportBatch
    {
        if ($batch->status === 'COMMITTED') {
            return $batch;
        }

        if ($batch->status !== 'PREVIEW_READY') {
            throw new \RuntimeException('Batch belum siap di-commit. Jalankan validasi preview terlebih dahulu.');
        }

        DB::transaction(function () use ($batch, $actorUserId) {
            $lockedBatch = ImportBatch::where('id', $batch->id)->lockForUpdate()->firstOrFail();
            if ($lockedBatch->status === 'COMMITTED') {
                return;
            }

            $validRows = $lockedBatch->rows()->where('row_status', 'VALID')->orderBy('row_number')->get();

            foreach ($validRows as $row) {
                $data = $row->normalized_data;

                match ($lockedBatch->import_type) {
                    'MEMBERS' => Member::updateOrCreate(
                        ['employee_number' => $data['employee_number']],
                        [
                            'member_number' => $data['member_number'],
                            'full_name' => $data['full_name'],
                            'department' => $data['department'] ?? null,
                            'status' => $data['status'] ?? 'ACTIVE',
                        ]
                    ),
                    'SUPPLIERS' => Supplier::updateOrCreate(
                        ['code' => $data['code']],
                        [
                            'name' => $data['name'],
                            'phone' => $data['phone'] ?? null,
                            'address' => $data['address'] ?? null,
                            'lead_time_days' => $data['lead_time_days'] ?? 1,
                            'is_active' => true,
                        ]
                    ),
                    'PRODUCTS' => $this->commitProductRow($data),
                    'STOCK_OPENING' => $this->commitStockOpeningRow($data, $lockedBatch->id, $actorUserId),
                };
            }

            $lockedBatch->update([
                'status' => 'COMMITTED',
                'committed_at' => now(),
            ]);
        });

        return $batch->fresh();
    }

    private function commitProductRow(array $data): void
    {
        $category = Category::firstOrCreate(['name' => $data['category']]);
        $supplierId = null;

        if (!empty($data['supplier_code'])) {
            $supplierId = Supplier::where('code', $data['supplier_code'])->value('id');
        }

        Product::updateOrCreate(
            ['sku' => $data['sku']],
            [
                'category_id' => $category->id,
                'supplier_id' => $supplierId,
                'barcode' => $data['barcode'] ?: null,
                'name' => $data['name'],
                'unit' => $data['unit'] ?: 'pcs',
                'selling_price' => $data['selling_price'],
                'current_cost' => $data['current_cost'],
                'reorder_point' => $data['reorder_point'],
                'is_active' => true,
            ]
        );
    }

    private function commitStockOpeningRow(array $data, string $batchId, string $actorUserId): void
    {
        $product = Product::where('sku', $data['sku'])->firstOrFail();
        $ref = "IMPORT-OPENING-{$batchId}";

        if ($data['unit_cost'] > 0) {
            $this->wacService->receiveWithCostUpdate(
                productId: $product->id,
                incomingQty: $data['quantity'],
                incomingUnitCost: $data['unit_cost'],
                receiptReference: $ref,
                actorUserId: $actorUserId
            );
        } else {
            $this->inventoryService->receive(
                productId: $product->id,
                quantity: $data['quantity'],
                reason: 'STOCK_OPENING_IMPORT',
                actorUserId: $actorUserId,
                sourceReference: $ref
            );
        }
    }

    private function validateMemberRow(array $data, array $seenKeys): array
    {
        $errors = [];
        $empNo = trim((string) ($data['employee_number'] ?? $data['nip'] ?? ''));
        $memNo = trim((string) ($data['member_number'] ?? $data['no_anggota'] ?? ''));
        $name = trim((string) ($data['full_name'] ?? $data['nama'] ?? ''));

        if ($empNo === '') {
            $errors[] = ['field' => 'employee_number', 'code' => 'REQUIRED', 'msg' => 'NIP / employee_number wajib diisi.'];
        }
        if ($memNo === '') {
            $errors[] = ['field' => 'member_number', 'code' => 'REQUIRED', 'msg' => 'Nomor anggota wajib diisi.'];
        }
        if ($name === '') {
            $errors[] = ['field' => 'full_name', 'code' => 'REQUIRED', 'msg' => 'Nama lengkap wajib diisi.'];
        }
        if ($empNo !== '' && isset($seenKeys[$empNo])) {
            return ['DUPLICATE', $empNo, null, [['field' => 'employee_number', 'code' => 'DUPLICATE_IN_FILE', 'msg' => "Duplikat employee_number {$empNo} di dalam file."]], []];
        }
        if (!empty($errors)) {
            return ['INVALID', $empNo ?: null, null, $errors, []];
        }

        return ['VALID', $empNo, [
            'employee_number' => $empNo,
            'member_number' => $memNo,
            'full_name' => $name,
            'department' => $data['department'] ?? $data['unit_kerja'] ?? null,
            'status' => strtoupper($data['status'] ?? 'ACTIVE') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ], [], []];
    }

    private function validateSupplierRow(array $data, array $seenKeys): array
    {
        $errors = [];
        $code = trim((string) ($data['code'] ?? $data['kode_supplier'] ?? ''));
        $name = trim((string) ($data['name'] ?? $data['nama_supplier'] ?? ''));

        if ($code === '') {
            $errors[] = ['field' => 'code', 'code' => 'REQUIRED', 'msg' => 'Kode supplier wajib diisi.'];
        }
        if ($name === '') {
            $errors[] = ['field' => 'name', 'code' => 'REQUIRED', 'msg' => 'Nama supplier wajib diisi.'];
        }
        if ($code !== '' && isset($seenKeys[$code])) {
            return ['DUPLICATE', $code, null, [['field' => 'code', 'code' => 'DUPLICATE_IN_FILE', 'msg' => "Duplikat kode supplier {$code} di dalam file."]], []];
        }
        if (!empty($errors)) {
            return ['INVALID', $code ?: null, null, $errors, []];
        }

        return ['VALID', $code, [
            'code' => $code,
            'name' => $name,
            'phone' => $data['phone'] ?? $data['telepon'] ?? null,
            'address' => $data['address'] ?? $data['alamat'] ?? null,
            'lead_time_days' => max(1, (int) ($data['lead_time_days'] ?? 1)),
        ], [], []];
    }

    private function validateProductRow(array $data, array $seenKeys): array
    {
        $errors = [];
        $sku = trim((string) ($data['sku'] ?? ''));
        $name = trim((string) ($data['name'] ?? $data['nama_produk'] ?? ''));
        $category = trim((string) ($data['category'] ?? $data['kategori'] ?? 'Umum'));
        $sellPrice = $this->parseNumeric($data['selling_price'] ?? $data['harga_jual'] ?? null);
        $rawCost = $data['current_cost'] ?? $data['hpp'] ?? null;
        $cost = $this->parseNumeric($rawCost) ?? 0.0;
        $costMissing = ($rawCost === null || trim((string) $rawCost) === '' || $cost <= 0);

        if ($sku === '') {
            $errors[] = ['field' => 'sku', 'code' => 'REQUIRED', 'msg' => 'SKU produk wajib diisi.'];
        }
        if ($name === '') {
            $errors[] = ['field' => 'name', 'code' => 'REQUIRED', 'msg' => 'Nama produk wajib diisi.'];
        }
        if ($sellPrice === null || $sellPrice < 0) {
            $errors[] = ['field' => 'selling_price', 'code' => 'INVALID_PRICE', 'msg' => 'Harga jual harus angka >= 0.'];
        }
        if ($cost < 0) {
            $errors[] = ['field' => 'current_cost', 'code' => 'NEGATIVE_COST', 'msg' => 'HPP tidak boleh negatif.'];
        }
        if ($sku !== '' && isset($seenKeys[$sku])) {
            return ['DUPLICATE', $sku, null, [['field' => 'sku', 'code' => 'DUPLICATE_IN_FILE', 'msg' => "Duplikat SKU {$sku} di dalam file."]], []];
        }
        if (!empty($errors)) {
            return ['INVALID', $sku ?: null, null, $errors, ['cost_missing' => $costMissing]];
        }

        return ['VALID', $sku, [
            'sku' => $sku,
            'barcode' => trim((string) ($data['barcode'] ?? '')) ?: null,
            'name' => $name,
            'category' => $category !== '' ? $category : 'Umum',
            'supplier_code' => trim((string) ($data['supplier_code'] ?? '')) ?: null,
            'unit' => trim((string) ($data['unit'] ?? $data['satuan'] ?? 'pcs')) ?: 'pcs',
            'selling_price' => $sellPrice,
            'current_cost' => $cost,
            'reorder_point' => max(0, (int) ($data['reorder_point'] ?? 0)),
        ], [], ['cost_missing' => $costMissing]];
    }

    private function validateStockOpeningRow(array $data, array $seenKeys): array
    {
        $errors = [];
        $sku = trim((string) ($data['sku'] ?? ''));
        $qty = (int) ($data['quantity'] ?? $data['stok_awal'] ?? 0);
        $unitCost = $this->parseNumeric($data['unit_cost'] ?? $data['hpp'] ?? 0) ?? 0.0;

        if ($sku === '') {
            $errors[] = ['field' => 'sku', 'code' => 'REQUIRED', 'msg' => 'SKU wajib diisi.'];
        } elseif (!Product::where('sku', $sku)->exists()) {
            $errors[] = ['field' => 'sku', 'code' => 'SKU_NOT_FOUND', 'msg' => "SKU {$sku} belum terdaftar di Master Produk."];
        }
        if ($qty <= 0) {
            $errors[] = ['field' => 'quantity', 'code' => 'INVALID_QTY', 'msg' => 'Jumlah stok awal harus > 0.'];
        }
        if ($sku !== '' && isset($seenKeys[$sku])) {
            return ['DUPLICATE', $sku, null, [['field' => 'sku', 'code' => 'DUPLICATE_IN_FILE', 'msg' => "Duplikat SKU {$sku} pada file stok awal."]], []];
        }
        if (!empty($errors)) {
            return ['INVALID', $sku ?: null, null, $errors, []];
        }

        return ['VALID', $sku, [
            'sku' => $sku,
            'quantity' => $qty,
            'unit_cost' => max(0, $unitCost),
        ], [], []];
    }

    private function parseNumeric(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }
}