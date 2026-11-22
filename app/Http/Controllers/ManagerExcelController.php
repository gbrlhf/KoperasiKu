<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Member;
use App\Models\MemberCreditAccount;
use App\Models\Product;
use App\Models\User;
use App\Services\Import\SmartImportMapper;
use App\Services\SimpleXlsxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ManagerExcelController extends Controller
{
    public function __construct(private readonly SimpleXlsxService $xlsx)
    {
    }

    public function downloadTemplate(Request $request): BinaryFileResponse|StreamedResponse
    {
        $type = $this->normalizeType((string) $request->query('type', 'PRODUK'));
        $format = strtolower((string) $request->query('format', 'xlsx'));

        [$headers, $rows, $baseName] = match ($type) {
            'ANGGOTA' => [
                ['no_anggota', 'id_karyawan', 'nama_lengkap', 'divisi', 'email', 'status'],
                [
                    ['MEM-101', 'EMP-101', 'Budi Santoso', 'Teknik Otomasi', 'budi.santoso@polman-bandung.ac.id', 'ACTIVE'],
                    ['MEM-102', 'EMP-102', 'Sari Wulandari', 'Administrasi', 'sari.wulandari@polman-bandung.ac.id', 'ACTIVE'],
                ],
                'template_impor_anggota',
            ],
            'PEMASOK' => [
                ['kode_pemasok', 'nama_pemasok', 'kontak', 'alamat', 'lead_time_hari', 'status'],
                [
                    ['SUP-001', 'CV Tani Makmur', '0812-555-0101', 'Jl. Raya Padalarang No. 12', 3, 'ACTIVE'],
                    ['SUP-002', 'PT Sumber Pangan', '022-555-0188', 'Kawasan Industri Cimahi', 5, 'ACTIVE'],
                ],
                'template_impor_pemasok',
            ],
            'STOK' => [
                ['kode_barang', 'stok_fisik', 'harga_modal_baru'],
                [
                    ['SEM-001', 50, 65000],
                    ['MNY-001', 40, 31000],
                ],
                'template_stok_awal_cutoff',
            ],
            'TRANSAKSI' => [
                ['no_referensi', 'tanggal', 'kode_barang', 'qty', 'harga_satuan', 'metode_bayar'],
                [
                    ['HIST-001', '2026-10-25', 'SEM-001', 2, 74500, 'CASH'],
                    ['HIST-002', '2026-10-26', 'MNY-001', 1, 36000, 'TRANSFER'],
                ],
                'template_histori_transaksi',
            ],
            default => [
                ['kode_barang', 'barcode', 'nama', 'kategori', 'kode_pemasok', 'satuan', 'harga_modal', 'harga_jual', 'stok_awal', 'min_stok', 'status'],
                [
                    ['BRS-10K', '899100100099', 'Beras Pandan Wangi 10 Kg', 'Sembako & Beras', 'SUP-001', 'Sak', 128000, 145000, 20, 5, 'ACTIVE'],
                    ['GLP-002', '899100100100', 'Gula Aren Organik 500 gr', 'Sembako & Beras', 'SUP-001', 'Pouch', 18000, 23000, 35, 10, 'ACTIVE'],
                ],
                'template_impor_produk',
            ],
        };

        return $this->sendSpreadsheet($headers, $rows, $baseName, $format);
    }

    public function exportData(Request $request): BinaryFileResponse|StreamedResponse
    {
        $type = $this->normalizeType((string) $request->query('type', 'PRODUK'));
        $format = strtolower((string) $request->query('format', 'xlsx'));

        if ($type === 'PEMASOK') {
            $headers = ['kode_pemasok', 'nama_pemasok', 'kontak', 'alamat', 'lead_time_hari', 'status'];
            $suppliers = DB::table('suppliers')->orderBy('code')->get();
            $rows = $suppliers->map(fn ($s) => [
                $s->code,
                $s->name,
                $s->phone ?? '',
                $s->address ?? '',
                (int) ($s->lead_time_days ?? 1),
                $s->is_active ? 'ACTIVE' : 'INACTIVE',
            ])->all();

            return $this->sendSpreadsheet($headers, $rows, 'export_pemasok_koperasiku_' . date('Ymd_Hi'), $format);
        }

        if ($type === 'ANGGOTA') {
            $headers = ['no_anggota', 'id_karyawan', 'nama_lengkap', 'divisi', 'email', 'status_anggota', 'status_piutang'];
            $usersById = User::all()->keyBy('id');
            $members = Member::with('creditAccount')->orderBy('member_number')->get();
            $rows = $members->map(fn ($m) => [
                $m->member_number,
                $m->employee_number,
                $m->full_name,
                $m->department ?? 'Umum',
                $usersById->get($m->user_id)?->email ?? '',
                $m->status,
                $m->creditAccount?->status ?? 'NONAKTIF',
            ])->all();

            return $this->sendSpreadsheet($headers, $rows, 'export_anggota_koperasiku_' . date('Ymd_Hi'), $format);
        }

        $balances = InventoryBalance::all()->keyBy('product_id');

        if ($type === 'STOK') {
            $headers = ['kode_barang', 'nama_produk', 'kategori', 'stok_on_hand', 'stok_reserved', 'stok_tersedia', 'min_stok', 'hpp_saat_ini'];
            $products = Product::orderBy('sku')->get();
            $rows = $products->map(function ($p) use ($balances) {
                $bal = $balances->get($p->id);
                $onHand = (int) ($bal?->on_hand ?? 0);
                $reserved = (int) ($bal?->reserved ?? 0);
                return [
                    $p->sku,
                    $p->name,
                    $p->category ?? '',
                    $onHand,
                    $reserved,
                    max(0, $onHand - $reserved),
                    (int) ($p->reorder_point ?? 0),
                    (float) $p->current_cost,
                ];
            })->all();

            return $this->sendSpreadsheet($headers, $rows, 'export_stok_koperasiku_' . date('Ymd_Hi'), $format);
        }

        $suppliersById = DB::table('suppliers')->get()->keyBy('id');
        $headers = ['kode_barang', 'barcode', 'nama', 'kategori', 'kode_pemasok', 'satuan', 'harga_modal', 'harga_jual', 'margin_persen', 'stok_tersedia', 'min_stok', 'status'];
        $products = Product::orderBy('sku')->get();
        $rows = $products->map(function ($p) use ($balances, $suppliersById) {
            $bal = $balances->get($p->id);
            $cost = (float) $p->current_cost;
            $price = (float) $p->selling_price;
            $margin = $price > 0 ? round((($price - $cost) / $price) * 100, 1) : 0;
            $onHand = (int) ($bal?->on_hand ?? 0);
            $reserved = (int) ($bal?->reserved ?? 0);
            $supCode = $p->supplier_id ? ($suppliersById->get($p->supplier_id)?->code ?? '') : '';

            return [
                $p->sku,
                $p->barcode ?? '',
                $p->name,
                $p->category ?? 'Umum',
                $supCode,
                $p->unit ?? 'Pcs',
                $cost,
                $price,
                $margin,
                max(0, $onHand - $reserved),
                (int) ($p->reorder_point ?? 0),
                $p->is_active ? 'ACTIVE' : 'INACTIVE',
            ];
        })->all();

        return $this->sendSpreadsheet($headers, $rows, 'export_produk_koperasiku_' . date('Ymd_Hi'), $format);
    }

    public function uploadPreview(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string'],
            'file' => ['required', 'file', 'max:10240'],
            'cut_off_date' => ['nullable', 'date'],
        ]);

        try {
            $type = $this->normalizeType((string) $request->input('type'), strict: true);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        try {
            $parsed = $this->xlsx->parseFile($file->getRealPath(), $ext, $type);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $fileHeaders = $parsed['file_headers'] ?? array_map(
            fn ($h) => ['key' => (string) $h, 'label' => (string) $h],
            $parsed['headers'] ?? []
        );
        $mapping = SmartImportMapper::autoMapHeaders($fileHeaders, $type);
        $rawRows = $parsed['rows'] ?? [];
        $validation = $this->validateRowsWithMapping($type, $rawRows, $fileHeaders, $mapping);

        $batchId = (string) Str::uuid();
        $checksum = hash_file('sha256', $file->getRealPath()) ?: sha1($batchId);
        $headerRowNum = (int) ($parsed['header_row_num'] ?? 1);

        DB::table('import_batches')->insert([
            'id' => $batchId,
            'uploaded_by' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => 'imports/' . $batchId . '.' . $ext,
            'checksum' => $checksum,
            'import_type' => $type,
            'status' => 'PREVIEW',
            'cutoff_at' => $request->input('cut_off_date') ?: null,
            'total_rows' => count($rawRows),
            'valid_rows' => count($validation['valid_rows']),
            'error_rows' => count($validation['errors']),
            'duplicate_rows' => $validation['duplicate_count'],
            'column_mapping' => json_encode($mapping),
            'quality_metadata' => json_encode([
                'header_row_num' => $headerRowNum,
                'file_headers' => $fileHeaders,
                'raw_rows' => $rawRows,
                'mapping' => $mapping,
                'fields_meta' => $validation['fields_meta'],
                'missing_required' => $validation['missing_required'],
                'valid_rows' => $validation['valid_rows'],
                'errors' => $validation['errors'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preview = [
            'valid_count' => count($validation['valid_rows']),
            'duplicate_count' => $validation['duplicate_count'],
            'errors' => $validation['errors'],
            'header_row_num' => $headerRowNum,
            'file_headers' => $fileHeaders,
            'column_mapping' => $mapping,
            'fields_meta' => $validation['fields_meta'],
            'missing_required' => $validation['missing_required'],
        ];

        return response()->json([
            'id' => $batchId,
            'batch' => [
                'id' => $batchId,
                'import_type' => $type,
                'file_name' => $file->getClientOriginalName(),
                'valid_rows' => count($validation['valid_rows']),
                'error_rows' => count($validation['errors']),
                'status' => 'PREVIEW',
            ],
            'preview' => $preview,
            'valid_count' => count($validation['valid_rows']),
            'duplicate_count' => $validation['duplicate_count'],
            'errors' => $validation['errors'],
            'header_row_num' => $headerRowNum,
            'file_headers' => $fileHeaders,
            'column_mapping' => $mapping,
            'fields_meta' => $validation['fields_meta'],
            'missing_required' => $validation['missing_required'],
        ]);
    }

    public function revalidateMapping(Request $request, string $id): JsonResponse
    {
        $batch = DB::table('import_batches')->where('id', $id)->first();
        if (!$batch) {
            return response()->json(['message' => 'Batch impor tidak ditemukan.'], 404);
        }
        if ($batch->status === 'COMMITTED') {
            return response()->json(['message' => 'Batch sudah di-commit dan tidak dapat divalidasi ulang.'], 422);
        }

        $validated = $request->validate([
            'mapping' => ['required', 'array'],
        ]);

        $meta = json_decode((string) ($batch->quality_metadata ?? '{}'), true);
        $rawRows = is_array($meta['raw_rows'] ?? null) ? $meta['raw_rows'] : [];
        $fileHeaders = is_array($meta['file_headers'] ?? null) ? $meta['file_headers'] : [];
        $type = (string) $batch->import_type;

        $allowedKeys = array_flip(array_map(fn ($h) => (string) ($h['key'] ?? ''), $fileHeaders));
        $defs = SmartImportMapper::fieldDefinitions($type);
        $newMapping = [];
        foreach ($defs as $field => $_def) {
            $chosen = isset($validated['mapping'][$field]) ? trim((string) $validated['mapping'][$field]) : '';
            $newMapping[$field] = ($chosen !== '' && isset($allowedKeys[$chosen])) ? $chosen : null;
        }

        $result = $this->validateRowsWithMapping($type, $rawRows, $fileHeaders, $newMapping);

        $meta['mapping'] = $newMapping;
        $meta['fields_meta'] = $result['fields_meta'];
        $meta['missing_required'] = $result['missing_required'];
        $meta['valid_rows'] = $result['valid_rows'];
        $meta['errors'] = $result['errors'];

        DB::table('import_batches')->where('id', $id)->update([
            'valid_rows' => count($result['valid_rows']),
            'error_rows' => count($result['errors']),
            'duplicate_rows' => $result['duplicate_count'],
            'column_mapping' => json_encode($newMapping),
            'quality_metadata' => json_encode($meta),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $batch->id,
            'import_type' => $batch->import_type,
            'file_name' => $batch->file_name,
            'valid_count' => count($result['valid_rows']),
            'duplicate_count' => $result['duplicate_count'],
            'errors' => $result['errors'],
            'header_row_num' => (int) ($meta['header_row_num'] ?? 1),
            'file_headers' => $fileHeaders,
            'column_mapping' => $newMapping,
            'fields_meta' => $result['fields_meta'],
            'missing_required' => $result['missing_required'],
        ]);
    }

    /**
     * Validate parsed rows against an explicit column mapping and smart currency/number normalization.
     */
    private function validateRowsWithMapping(string $type, array $rawRows, array $fileHeaders, array $mapping): array
    {
        $validRows = [];
        $errors = [];
        $duplicateCount = 0;

        foreach ($rawRows as $entry) {
            $rowNum = $entry['row_num'] ?? '-';
            $d = SmartImportMapper::applyMappingToRow($entry['data'] ?? [], $mapping);

            if ($type === 'PRODUK') {
                $sku = trim((string) $this->pick($d, ['sku', 'kode_barang', 'kode_produk', 'kode_sku', 'id_produk', 'kode']));
                $barcode = trim((string) $this->pick($d, ['barcode']));
                $name = trim((string) $this->pick($d, ['name', 'nama', 'nama_produk', 'nama_barang', 'produk', 'barang', 'item', 'deskripsi']));
                $category = trim((string) $this->pick($d, ['category', 'kategori', 'category_name'], 'Sembako & Beras'));
                $supCode = trim((string) $this->pick($d, ['supplier_code', 'kode_pemasok', 'pemasok']));
                $unit = trim((string) $this->pick($d, ['unit', 'satuan'], 'Pcs'));

                $costVal = $this->pick($d, ['current_cost', 'harga_modal', 'harga_beli', 'modal', 'harga_pokok', 'hpp', 'beli', 'cost'], '');
                $costParsed = $costVal === '' ? 0.0 : SmartImportMapper::parseNumber($costVal);
                $priceVal = $this->pick($d, ['selling_price', 'harga_jual', 'harga', 'price', 'jual', 'tarif'], '');
                $priceParsed = SmartImportMapper::parseNumber($priceVal);
                $stockVal = $this->pick($d, ['initial_stock', 'stok_awal', 'stok', 'stock', 'qty', 'jumlah', 'kuantitas', 'saldo'], '');
                $stockParsed = $stockVal === '' ? null : SmartImportMapper::parseNumber($stockVal);
                $minStockVal = $this->pick($d, ['reorder_point', 'min_stok', 'min_stock', 'stok_minimum'], '10');
                $minStockParsed = SmartImportMapper::parseNumber($minStockVal);
                $statusRaw = strtoupper(trim((string) $this->pick($d, ['status'], 'ACTIVE')));

                if ($sku === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $name !== '' ? $name : '—', 'message' => 'Kode barang (SKU) kosong'];
                    continue;
                }
                if ($name === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Nama produk kosong'];
                    continue;
                }
                if ($costParsed === null || $costParsed < 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Harga modal / HPP bukan angka valid'];
                    continue;
                }
                if ($priceParsed === null || $priceParsed <= 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Harga jual harus angka > 0'];
                    continue;
                }
                if ($stockVal !== '' && ($stockParsed === null || (int) round($stockParsed) < 0)) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Stok awal harus angka >= 0'];
                    continue;
                }

                if (Product::where('sku', $sku)->exists()) {
                    $duplicateCount++;
                }

                $validRows[] = [
                    'sku' => $sku,
                    'barcode' => $barcode !== '' ? $barcode : null,
                    'name' => $name,
                    'category' => $category !== '' ? $category : 'Sembako & Beras',
                    'supplier_code' => $supCode !== '' ? $supCode : null,
                    'unit' => $unit !== '' ? $unit : 'Pcs',
                    'current_cost' => round($costParsed, 2),
                    'selling_price' => round($priceParsed, 2),
                    'stock' => $stockParsed !== null ? (int) round($stockParsed) : null,
                    'reorder_point' => $minStockParsed !== null ? max(0, (int) round($minStockParsed)) : 10,
                    'is_active' => !in_array($statusRaw, ['INACTIVE', 'NONAKTIF', '0', 'FALSE'], true),
                ];
            } elseif ($type === 'ANGGOTA') {
                $empNo = trim((string) $this->pick($d, ['employee_number', 'nim_nip', 'nim', 'nip', 'id_karyawan', 'id_anggota', 'no_anggota', 'member_number', 'nomor_anggota', 'kode_anggota', 'nomor', 'member']));
                $memberNo = trim((string) $this->pick($d, ['member_number', 'no_anggota', 'nomor_anggota', 'kode_anggota'], $empNo));
                if ($memberNo === '') {
                    $memberNo = $empNo;
                }
                $fullName = trim((string) $this->pick($d, ['full_name', 'nama_lengkap', 'nama', 'name']));
                $dept = trim((string) $this->pick($d, ['department', 'jurusan', 'prodi', 'divisi', 'unit_kerja', 'unit', 'departemen', 'bagian'], 'Umum'));
                $email = strtolower(trim((string) $this->pick($d, ['email'])));
                $status = strtoupper(trim((string) $this->pick($d, ['status'], 'ACTIVE')));

                if ($memberNo === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $fullName !== '' ? $fullName : '—', 'message' => 'NIM / NIP / No. anggota kosong'];
                    continue;
                }
                if ($fullName === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $memberNo, 'message' => 'Nama lengkap kosong'];
                    continue;
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $memberNo, 'message' => 'Format email tidak valid'];
                    continue;
                }

                $dupMember = Member::where('member_number', $memberNo)
                    ->orWhere('employee_number', $empNo !== '' ? $empNo : $memberNo)
                    ->exists();
                $dupEmail = $email !== '' && User::whereRaw('LOWER(email) = ?', [$email])->exists();
                if ($dupMember || $dupEmail) {
                    $duplicateCount++;
                }

                $validRows[] = [
                    'member_number' => $memberNo,
                    'employee_number' => $empNo !== '' ? $empNo : $memberNo,
                    'full_name' => $fullName,
                    'department' => $dept !== '' ? $dept : 'Umum',
                    'email' => $email !== '' ? $email : null,
                    'status' => in_array($status, ['ACTIVE', 'INACTIVE'], true) ? $status : 'ACTIVE',
                ];
            } elseif ($type === 'PEMASOK') {
                $name = trim((string) $this->pick($d, ['name', 'nama_pemasok', 'nama', 'pemasok', 'supplier', 'perusahaan']));
                $code = strtoupper(trim((string) $this->pick($d, ['code', 'kode_pemasok', 'kode', 'id_pemasok'])));
                if ($code === '' && $name !== '') {
                    $code = 'SUP-' . strtoupper(Str::random(5));
                }
                $phone = trim((string) $this->pick($d, ['phone', 'kontak', 'telepon', 'telp', 'no_hp', 'hp']));
                $address = trim((string) $this->pick($d, ['address', 'alamat', 'lokasi']));
                $leadVal = $this->pick($d, ['lead_time_days', 'lead_time_hari', 'lead_time'], '2');
                $leadParsed = $leadVal === '' ? 2.0 : SmartImportMapper::parseNumber($leadVal);
                $statusRaw = strtoupper(trim((string) $this->pick($d, ['status'], 'ACTIVE')));

                if ($code === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => '—', 'message' => 'Kode pemasok kosong'];
                    continue;
                }
                if ($name === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $code, 'message' => 'Nama pemasok kosong'];
                    continue;
                }
                if ($leadParsed === null || (int) round($leadParsed) < 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $code, 'message' => 'Lead time harus angka >= 0'];
                    continue;
                }

                if (DB::table('suppliers')->where('code', $code)->exists()) {
                    $duplicateCount++;
                }

                $validRows[] = [
                    'code' => $code,
                    'name' => $name,
                    'phone' => $phone !== '' ? $phone : null,
                    'address' => $address !== '' ? $address : null,
                    'lead_time_days' => (int) round($leadParsed),
                    'is_active' => !in_array($statusRaw, ['INACTIVE', 'NONAKTIF', '0', 'FALSE'], true),
                ];
            } elseif ($type === 'STOK') {
                $sku = trim((string) $this->pick($d, ['sku', 'kode_barang', 'kode_produk', 'kode_sku', 'id_produk', 'kode']));
                $stockVal = $this->pick($d, ['quantity', 'stok_fisik', 'stok_awal', 'stok', 'stock', 'qty', 'jumlah', 'kuantitas', 'saldo', 'on_hand'], '');
                $stockParsed = SmartImportMapper::parseNumber($stockVal);
                $costVal = $this->pick($d, ['unit_cost', 'harga_modal_baru', 'harga_modal', 'harga_beli', 'hpp', 'modal', 'current_cost', 'cost'], '');
                $costParsed = $costVal === '' ? null : SmartImportMapper::parseNumber($costVal);

                if ($sku === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => '—', 'message' => 'Kode barang (SKU) kosong'];
                    continue;
                }
                if (!Product::where('sku', $sku)->exists()) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'SKU tidak ditemukan di master produk'];
                    continue;
                }
                if ($stockParsed === null || (int) round($stockParsed) < 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Stok fisik harus angka >= 0'];
                    continue;
                }
                if ($costVal !== '' && ($costParsed === null || $costParsed < 0)) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Harga modal baru tidak valid'];
                    continue;
                }

                $duplicateCount++;
                $validRows[] = [
                    'sku' => $sku,
                    'stock' => (int) round($stockParsed),
                    'new_cost' => $costParsed !== null ? round($costParsed, 2) : null,
                ];
            } elseif ($type === 'TRANSAKSI') {
                $ref = trim((string) $this->pick($d, ['ref', 'no_referensi', 'nomor_transaksi']));
                $sku = trim((string) $this->pick($d, ['sku', 'kode_barang', 'kode_produk', 'kode_sku']));
                $qtyVal = $this->pick($d, ['qty', 'quantity', 'jumlah', 'kuantitas'], '');
                $qtyParsed = SmartImportMapper::parseNumber($qtyVal);
                $priceVal = $this->pick($d, ['price', 'harga_satuan', 'harga'], '');
                $priceParsed = SmartImportMapper::parseNumber($priceVal);

                if ($ref === '' || $sku === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $ref ?: '—', 'message' => 'No. referensi dan kode barang wajib diisi'];
                    continue;
                }
                if ($qtyParsed === null || (int) round($qtyParsed) <= 0 || $priceParsed === null || $priceParsed < 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $ref, 'message' => 'Qty atau harga satuan tidak valid'];
                    continue;
                }

                $validRows[] = [
                    'ref' => $ref,
                    'tanggal' => trim((string) $this->pick($d, ['tanggal'], date('Y-m-d'))),
                    'sku' => $sku,
                    'qty' => (int) round($qtyParsed),
                    'harga_satuan' => round($priceParsed, 2),
                    'metode_bayar' => strtoupper(trim((string) $this->pick($d, ['metode_bayar'], 'CASH'))),
                ];
            }
        }

        $summary = SmartImportMapper::mappingSummary($fileHeaders, $mapping, $type);

        return [
            'valid_rows' => $validRows,
            'errors' => $errors,
            'duplicate_count' => $duplicateCount,
            'fields_meta' => $summary['fields'],
            'missing_required' => $summary['missing_required'],
        ];
    }

    public function getPreview(string $id): JsonResponse
    {
        $batch = DB::table('import_batches')->where('id', $id)->first();
        if (!$batch) {
            return response()->json(['message' => 'Batch preview tidak ditemukan.'], 404);
        }

        $meta = json_decode((string) ($batch->quality_metadata ?? '{}'), true);
        $validRows = is_array($meta['valid_rows'] ?? null) ? $meta['valid_rows'] : [];
        $errors = is_array($meta['errors'] ?? null) ? $meta['errors'] : [];

        return response()->json([
            'id' => $batch->id,
            'import_type' => $batch->import_type,
            'file_name' => $batch->file_name,
            'valid_count' => count($validRows),
            'duplicate_count' => (int) ($batch->duplicate_rows ?? 0),
            'errors' => $errors,
            'header_row_num' => (int) ($meta['header_row_num'] ?? 1),
            'file_headers' => $meta['file_headers'] ?? [],
            'column_mapping' => $meta['mapping'] ?? json_decode((string) ($batch->column_mapping ?? '{}'), true),
            'fields_meta' => $meta['fields_meta'] ?? [],
            'missing_required' => $meta['missing_required'] ?? [],
        ]);
    }

    public function commitBatch(string $id): JsonResponse
    {
        $batch = DB::table('import_batches')->where('id', $id)->first();
        if (!$batch) {
            return response()->json(['message' => 'Batch impor tidak ditemukan.'], 404);
        }
        if ($batch->status === 'COMMITTED') {
            return response()->json(['message' => 'Batch impor sudah pernah disimpan sebelumnya (idempoten).', 'batch' => $batch]);
        }

        $meta = json_decode((string) ($batch->quality_metadata ?? '{}'), true);
        $rows = is_array($meta['valid_rows'] ?? null) ? $meta['valid_rows'] : [];
        if (count($rows) === 0) {
            return response()->json(['message' => 'Tidak ada baris valid untuk disimpan.'], 422);
        }

        $type = $batch->import_type;

        DB::transaction(function () use ($type, $rows, $id): void {
            if ($type === 'PRODUK') {
                $suppliersByCode = DB::table('suppliers')->get()->keyBy(fn ($s) => strtoupper((string) $s->code));
                foreach ($rows as $r) {
                    $catName = $r['category'] ?? 'Sembako & Beras';
                    $category = Category::firstOrCreate(
                        ['slug' => Str::slug($catName)],
                        ['name' => $catName, 'icon' => 'bi-box-seam']
                    );
                    $supplierId = !empty($r['supplier_code'])
                        ? ($suppliersByCode->get(strtoupper((string) $r['supplier_code']))?->id ?? null)
                        : null;

                    $product = Product::where('sku', $r['sku'])->first();
                    $incomingStock = $r['stock'] ?? null;
                    $incomingCost = (float) $r['current_cost'];

                    if ($product) {
                        $balance = InventoryBalance::firstOrCreate(
                            ['product_id' => $product->id],
                            ['on_hand' => 0, 'reserved' => 0]
                        );
                        $oldStock = (int) $balance->on_hand;
                        $oldCost = (float) $product->current_cost;

                        $finalCost = $incomingCost;
                        if ($incomingStock !== null && $incomingStock > 0 && ($oldStock + $incomingStock) > 0 && $oldCost > 0) {
                            $finalCost = round((($oldStock * $oldCost) + ($incomingStock * $incomingCost)) / ($oldStock + $incomingStock), 2);
                        }

                        $product->update([
                            'name' => $r['name'],
                            'barcode' => $r['barcode'] ?: $product->barcode,
                            'category' => $category->name,
                            'category_id' => $category->id,
                            'supplier_id' => $supplierId ?? $product->supplier_id,
                            'unit' => $r['unit'],
                            'current_cost' => $finalCost,
                            'selling_price' => (float) $r['selling_price'],
                            'reorder_point' => (int) ($r['reorder_point'] ?? $product->reorder_point ?? 10),
                            'is_active' => (bool) ($r['is_active'] ?? true),
                        ]);

                        if ($incomingStock !== null) {
                            $balance->update(['on_hand' => $oldStock + $incomingStock]);
                        }
                    } else {
                        $product = Product::create([
                            'sku' => $r['sku'],
                            'barcode' => $r['barcode'],
                            'name' => $r['name'],
                            'category' => $category->name,
                            'category_id' => $category->id,
                            'supplier_id' => $supplierId,
                            'unit' => $r['unit'],
                            'current_cost' => $incomingCost,
                            'selling_price' => (float) $r['selling_price'],
                            'original_price' => (float) $r['selling_price'],
                            'reorder_point' => (int) ($r['reorder_point'] ?? 10),
                            'is_active' => (bool) ($r['is_active'] ?? true),
                        ]);

                        InventoryBalance::create([
                            'product_id' => $product->id,
                            'on_hand' => (int) ($incomingStock ?? 0),
                            'reserved' => 0,
                        ]);
                    }
                }
            } elseif ($type === 'ANGGOTA') {
                foreach ($rows as $r) {
                    $email = isset($r['email']) && trim((string) $r['email']) !== ''
                        ? strtolower(trim((string) $r['email']))
                        : null;

                    $user = null;
                    if ($email !== null) {
                        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
                        if (!$user) {
                            $user = new User(['email' => $email]);
                            $user->password_hash = Hash::make('password123');
                        }
                        $user->status = $r['status'] === 'ACTIVE' ? 'active' : 'inactive';
                        $user->save();
                        $user->roles()->syncWithoutDetaching(['MEMBER']);
                    }

                    $member = Member::where('member_number', $r['member_number'])
                        ->orWhere('employee_number', $r['employee_number'])
                        ->when($user, fn ($q) => $q->orWhere('user_id', $user->id))
                        ->first();

                    if ($member) {
                        $member->update([
                            'user_id' => $user?->id ?? $member->user_id,
                            'employee_number' => $r['employee_number'],
                            'member_number' => $r['member_number'],
                            'full_name' => $r['full_name'],
                            'department' => $r['department'] ?? 'Umum',
                            'status' => $r['status'],
                        ]);
                    } else {
                        $member = Member::create([
                            'user_id' => $user?->id,
                            'employee_number' => $r['employee_number'],
                            'member_number' => $r['member_number'],
                            'full_name' => $r['full_name'],
                            'department' => $r['department'] ?? 'Umum',
                            'status' => $r['status'],
                        ]);
                    }

                    MemberCreditAccount::firstOrCreate(
                        ['member_id' => $member->id],
                        ['status' => 'ACTIVE', 'activated_at' => now()]
                    );
                }
            } elseif ($type === 'PEMASOK') {
                foreach ($rows as $r) {
                    $existing = DB::table('suppliers')->where('code', $r['code'])->first();
                    if ($existing) {
                        DB::table('suppliers')->where('id', $existing->id)->update([
                            'name' => $r['name'],
                            'phone' => $r['phone'],
                            'address' => $r['address'],
                            'lead_time_days' => (int) $r['lead_time_days'],
                            'is_active' => (bool) $r['is_active'],
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('suppliers')->insert([
                            'id' => (string) Str::uuid(),
                            'code' => $r['code'],
                            'name' => $r['name'],
                            'phone' => $r['phone'],
                            'address' => $r['address'],
                            'lead_time_days' => (int) $r['lead_time_days'],
                            'is_active' => (bool) $r['is_active'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } elseif ($type === 'STOK') {
                foreach ($rows as $r) {
                    $product = Product::where('sku', $r['sku'])->firstOrFail();
                    if ($r['new_cost'] !== null && $r['new_cost'] > 0) {
                        $product->update(['current_cost' => (float) $r['new_cost']]);
                    }
                    InventoryBalance::updateOrCreate(
                        ['product_id' => $product->id],
                        ['on_hand' => (int) $r['stock']]
                    );
                }
            }

            DB::table('import_batches')->where('id', $id)->update([
                'status' => 'COMMITTED',
                'committed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Berhasil menyimpan ' . count($rows) . ' baris data impor ke database.',
            'batch' => DB::table('import_batches')->where('id', $id)->first(),
        ]);
    }

    public function exportErrors(string $id): BinaryFileResponse|StreamedResponse
    {
        $batch = DB::table('import_batches')->where('id', $id)->first();
        $meta = json_decode((string) ($batch?->quality_metadata ?? '{}'), true);
        $errors = is_array($meta['errors'] ?? null) ? $meta['errors'] : [];

        $headers = ['baris_ke', 'kode_atau_identitas', 'keterangan_error'];
        $rows = array_map(fn ($e) => [
            $e['row_number'] ?? '',
            $e['identifier'] ?? '',
            $e['message'] ?? '',
        ], $errors);

        return $this->sendSpreadsheet($headers, $rows, 'laporan_baris_error_' . date('Ymd_Hi'), 'xlsx');
    }

    private function sendSpreadsheet(array $headers, array $rows, string $baseName, string $format): BinaryFileResponse|StreamedResponse
    {
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $baseName . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $tmpFile = $this->xlsx->createXlsxTempFile($headers, $rows, 'KoperasiKu');
        return response()->download($tmpFile, $baseName . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Resolve the first non-null column value from a list of normalized header aliases.
     */
    private function pick(array $row, array $aliases, mixed $default = ''): mixed
    {
        foreach ($aliases as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
                return $row[$key];
            }
        }
        return $default;
    }

    private function normalizeType(string $raw, bool $strict = false): string
    {
        $v = strtoupper(trim($raw));
        return match ($v) {
            'PRODUK', 'PRODUCTS', 'PRODUCT' => 'PRODUK',
            'ANGGOTA', 'MEMBERS', 'MEMBER' => 'ANGGOTA',
            'PEMASOK', 'SUPPLIERS', 'SUPPLIER' => 'PEMASOK',
            'STOK', 'STOCK', 'STOCK_OPENING' => 'STOK',
            'TRANSAKSI', 'TRANSACTIONS', 'TRANSACTION' => 'TRANSAKSI',
            default => $strict
                ? throw new \InvalidArgumentException("Tipe impor '{$raw}' tidak valid. Pilih PRODUK, ANGGOTA, PEMASOK, STOK, atau TRANSAKSI.")
                : 'PRODUK',
        };
    }
}