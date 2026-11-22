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

    private array $lastParsedHeaders = [];
    private int $lastHeaderRowNum = 1;

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
        $this->lastParsedHeaders = [];
        $this->lastHeaderRowNum = 1;
        $rawRows = $this->readFileRows($file, $normalizedType);
        $autoMapping = SmartImportMapper::autoMapHeaders($this->lastParsedHeaders, $normalizedType);

        $batch = DB::transaction(function () use ($file, $normalizedType, $cutoffAt, $actorUserId, $rawRows, $autoMapping): ImportBatch {
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
                'column_mapping' => [
                    'header_row_num' => $this->lastHeaderRowNum,
                    'file_headers' => $this->lastParsedHeaders,
                    'mapping' => $autoMapping,
                ],
                'cutoff_at' => $cutoffAt,
                'uploaded_by' => $actorUserId,
            ]);

            foreach ($rawRows as $index => $entry) {
                $rowNum = is_array($entry) && array_key_exists('row_num', $entry) ? (int) $entry['row_num'] : ($index + 2);
                $payload = is_array($entry) && array_key_exists('data', $entry) ? $entry['data'] : $entry;
                ImportRow::create([
                    'batch_id' => $batch->id,
                    'row_number' => $rowNum,
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
     * Validasi baris staging untuk mendeteksi duplikat & error field (mendukung custom mapping manual).
     */
    public function validateBatch(ImportBatch $batch, ?array $customMapping = null): ImportBatch
    {
        if ($batch->status === 'COMMITTED') {
            throw new BusinessRuleException('Batch impor sudah di-commit dan tidak dapat divalidasi ulang.');
        }

        return DB::transaction(function () use ($batch, $customMapping): ImportBatch {
            $batch->load('rows');

            $colMeta = is_array($batch->column_mapping) ? $batch->column_mapping : [];
            $fileHeaders = is_array($colMeta['file_headers'] ?? null) ? $colMeta['file_headers'] : [];
            if (empty($fileHeaders) && $batch->rows->isNotEmpty()) {
                $firstKeys = array_keys($this->normalizeRowKeys($batch->rows->first()->raw_data ?? []));
                $fileHeaders = array_map(fn ($k) => ['key' => (string) $k, 'label' => (string) $k], $firstKeys);
            }

            $mapping = is_array($colMeta['mapping'] ?? null)
                ? $colMeta['mapping']
                : SmartImportMapper::autoMapHeaders($fileHeaders, $batch->import_type);

            if ($customMapping !== null) {
                $allowed = array_flip(array_map(fn ($h) => (string) ($h['key'] ?? ''), $fileHeaders));
                $defs = SmartImportMapper::fieldDefinitions($batch->import_type);
                foreach ($defs as $field => $_def) {
                    if (array_key_exists($field, $customMapping)) {
                        $chosen = trim((string) ($customMapping[$field] ?? ''));
                        $mapping[$field] = ($chosen !== '' && isset($allowed[$chosen])) ? $chosen : null;
                    }
                }
            }

            $validCount = 0;
            $errorCount = 0;
            $duplicateCount = 0;
            $seenKeys = [];

            foreach ($batch->rows as $row) {
                $row->errors()->delete();
                $rawBase = $this->normalizeRowKeys($row->raw_data ?? []);
                $raw = SmartImportMapper::applyMappingToRow($rawBase, $mapping);

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

            $summary = SmartImportMapper::mappingSummary($fileHeaders, $mapping, $batch->import_type);
            $batch->update([
                'status' => $errorCount === 0 && $batch->total_rows > 0 ? 'VALIDATED' : 'FAILED',
                'valid_rows' => $validCount,
                'error_rows' => $errorCount,
                'duplicate_rows' => $duplicateCount,
                'column_mapping' => [
                    'header_row_num' => (int) ($colMeta['header_row_num'] ?? 1),
                    'file_headers' => $fileHeaders,
                    'mapping' => $mapping,
                    'fields_meta' => $summary['fields'],
                    'missing_required' => $summary['missing_required'],
                ],
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
        return SmartImportMapper::normalizeHeader($header);
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
    private function readFileRows(UploadedFile $file, string $type = ''): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $path = $file->getRealPath();

        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->readCsvRows($path, $type);
        }

        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            return $this->rowsArrayToAssociative($rows, $type);
        }

        if ($ext === 'xlsx' && class_exists(ZipArchive::class)) {
            return $this->readXlsxRowsNative($path, $type);
        }

        return $this->readCsvRows($path, $type);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsvRows(string $path, string $type = ''): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $sample = '';
        for ($i = 0; $i < 5; $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $sample .= preg_replace('/^\xEF\xBB\xBF/', '', $line);
        }
        if ($sample === '') {
            fclose($handle);

            return [];
        }
        $delimiter = substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        $lineNo = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNo++;
            $nonEmpty = collect($data)->contains(fn ($v) => trim((string) $v) !== '');
            if ($nonEmpty) {
                if (isset($data[0]) && is_string($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                }
                $rows[] = ['row_num' => $lineNo, 'cells' => array_values($data)];
            }
        }
        fclose($handle);

        return $this->rowsArrayToAssociative($rows, $type);
    }

    /**
     * Native XLSX parser using ZipArchive & SimpleXML (no external vendor dependency required).
     *
     * @return array<int, array<string, mixed>>
     */
    private function readXlsxRowsNative(string $path, string $type = ''): array
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
        $seqRow = 0;
        foreach ($sheet->sheetData->row as $row) {
            $seqRow++;
            $excelRowNum = (int) ($row['r'] ?? $seqRow);
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
                $sparseRows[] = ['row_num' => $excelRowNum > 0 ? $excelRowNum : $seqRow, 'cells' => $rowCells];
            }
        }

        $rows = [];
        foreach ($sparseRows as $entry) {
            $dense = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $dense[$c] = $entry['cells'][$c] ?? '';
            }
            $rows[] = ['row_num' => $entry['row_num'], 'cells' => $dense];
        }

        return $this->rowsArrayToAssociative($rows, $type);
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
    private function rowsArrayToAssociative(array $rows, string $type = ''): array
    {
        if (count($rows) < 2) {
            return [];
        }

        // Normalize shape into [['row_num' => int, 'cells' => array]]
        $grid = [];
        foreach ($rows as $idx => $entry) {
            if (is_array($entry) && array_key_exists('cells', $entry)) {
                $grid[] = $entry;
            } else {
                $grid[] = ['row_num' => $idx + 1, 'cells' => array_values((array) $entry)];
            }
        }

        $denseForDetection = array_map(fn ($r) => $r['cells'], array_slice($grid, 0, 5));
        $headerIdx = SmartImportMapper::detectHeaderRowIndex($denseForDetection, $type);
        if ($headerIdx >= count($grid) - 1) {
            $headerIdx = 0;
        }
        $this->lastHeaderRowNum = (int) ($grid[$headerIdx]['row_num'] ?? ($headerIdx + 1));

        $headers = [];
        $fileHeaders = [];
        $seen = [];
        foreach ($grid[$headerIdx]['cells'] as $idx => $h) {
            $rawLabel = trim((string) $h);
            $norm = $this->normalizeHeaderName($rawLabel);
            if ($norm === '') {
                continue;
            }
            if (isset($seen[$norm])) {
                $seen[$norm]++;
                $norm .= '_' . $seen[$norm];
            } else {
                $seen[$norm] = 1;
            }
            $headers[$idx] = $norm;
            $fileHeaders[] = [
                'key' => $norm,
                'label' => $rawLabel !== '' ? $rawLabel : $norm,
            ];
        }
        $this->lastParsedHeaders = $fileHeaders;

        $result = [];
        foreach (array_slice($grid, $headerIdx + 1) as $entry) {
            $row = $entry['cells'];
            $isEmpty = collect($row)->every(fn ($v) => trim((string) $v) === '');
            if ($isEmpty) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $idx => $key) {
                $assoc[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : null;
            }
            $result[] = ['row_num' => $entry['row_num'], 'data' => $assoc];
        }

        return $result;
    }

    private function validateProductRow(array $raw): array
    {
        $errors = [];
        $sku = trim((string) $this->pickValue($raw, ['sku', 'kode_sku', 'kode_produk', 'kode_barang', 'id_produk', 'kode']));
        $barcode = trim((string) $this->pickValue($raw, ['barcode'])) ?: null;
        $name = trim((string) $this->pickValue($raw, ['name', 'nama_produk', 'nama_barang', 'nama', 'produk', 'barang', 'item', 'deskripsi']));
        $categoryName = trim((string) $this->pickValue($raw, ['category_name', 'category', 'kategori'], 'Umum'));
        $unit = trim((string) $this->pickValue($raw, ['unit', 'satuan'], 'pcs')) ?: 'pcs';

        $sellingRaw = $this->pickValue($raw, ['selling_price', 'harga_jual', 'harga', 'price', 'jual', 'tarif'], null);
        $sellingParsed = SmartImportMapper::parseNumber($sellingRaw);
        $costRaw = $this->pickValue($raw, ['current_cost', 'harga_pokok', 'harga_modal', 'harga_beli', 'modal', 'hpp', 'beli', 'cost'], '0');
        $costParsed = $costRaw === '' ? 0.0 : SmartImportMapper::parseNumber($costRaw);
        $reorderParsed = SmartImportMapper::parseNumber($this->pickValue($raw, ['reorder_point', 'min_stock', 'min_stok', 'stok_minimum'], '5'));
        $stockParsed = SmartImportMapper::parseNumber($this->pickValue($raw, ['initial_stock', 'stok_awal', 'stok', 'stock', 'qty', 'jumlah', 'kuantitas', 'saldo'], '0'));

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

        if ($sellingParsed === null || $sellingParsed < 0) {
            $errors[] = ['field' => 'selling_price', 'code' => 'INVALID_PRICE', 'message' => 'Harga jual harus berupa angka >= 0.'];
        }

        if ($costParsed === null || $costParsed < 0) {
            $errors[] = ['field' => 'current_cost', 'code' => 'INVALID_COST', 'message' => 'Harga pokok (HPP) harus berupa angka >= 0.'];
        }

        $normalized = [
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $name,
            'category_name' => $categoryName ?: 'Umum',
            'unit' => $unit,
            'selling_price' => $sellingParsed !== null ? round($sellingParsed, 2) : 0,
            'current_cost' => $costParsed !== null ? round($costParsed, 2) : 0,
            'reorder_point' => $reorderParsed !== null ? max(0, (int) round($reorderParsed)) : 5,
            'initial_stock' => $stockParsed !== null ? max(0, (int) round($stockParsed)) : 0,
        ];

        return [$normalized, $errors, $sku !== '' ? $sku : null];
    }

    private function validateMemberRow(array $raw): array
    {
        $errors = [];
        $employeeNumber = trim((string) $this->pickValue($raw, ['employee_number', 'nim_nip', 'nip', 'nim', 'id_karyawan', 'id_anggota', 'member_number', 'no_anggota', 'nomor_anggota', 'kode_anggota', 'nomor', 'member']));
        $memberNumber = trim((string) $this->pickValue($raw, ['member_number', 'no_anggota', 'nomor_anggota', 'kode_anggota', 'nim_nip', 'nim', 'nip', 'employee_number', 'id_karyawan', 'id_anggota'], $employeeNumber));
        $fullName = trim((string) $this->pickValue($raw, ['full_name', 'nama_lengkap', 'name', 'nama']));
        $department = trim((string) $this->pickValue($raw, ['department', 'jurusan', 'prodi', 'unit_kerja', 'divisi', 'unit', 'departemen', 'bagian'], 'Umum')) ?: 'Umum';
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
        $sku = trim((string) $this->pickValue($raw, ['sku', 'kode_sku', 'kode_produk', 'kode_barang', 'id_produk', 'kode']));
        $qtyParsed = SmartImportMapper::parseNumber($this->pickValue($raw, ['quantity', 'on_hand', 'qty', 'stok_fisik', 'stok_awal', 'stok', 'stock', 'jumlah', 'kuantitas', 'saldo'], null));
        $unitCostParsed = SmartImportMapper::parseNumber($this->pickValue($raw, ['unit_cost', 'current_cost', 'harga_modal_baru', 'harga_modal', 'harga_beli', 'modal', 'hpp', 'cost'], null));

        $product = $sku !== '' ? Product::where('sku', $sku)->first() : null;
        if (! $product) {
            $errors[] = ['field' => 'sku', 'code' => 'PRODUCT_NOT_FOUND', 'message' => "Produk dengan SKU '{$sku}' tidak ditemukan."];
        }

        if ($qtyParsed === null || (int) round($qtyParsed) <= 0) {
            $errors[] = ['field' => 'quantity', 'code' => 'INVALID_QTY', 'message' => 'Kuantitas saldo awal harus bilangan bulat > 0.'];
        }

        $normalized = [
            'product_id' => $product?->id,
            'sku' => $sku,
            'quantity' => $qtyParsed !== null ? (int) round($qtyParsed) : 0,
            'unit_cost' => $unitCostParsed !== null ? round($unitCostParsed, 2) : (float) ($product?->current_cost ?? 0),
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
