<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Member;
use App\Models\MemberCreditAccount;
use App\Models\Product;
use App\Models\User;
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
            $parsed = $this->xlsx->parseFile($file->getRealPath(), $ext);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $validRows = [];
        $errors = [];
        $duplicateCount = 0;

        foreach ($parsed['rows'] as $entry) {
            $rowNum = $entry['row_num'];
            $d = $entry['data'];

            if ($type === 'PRODUK') {
                $sku = trim((string) $this->pick($d, ['kode_barang', 'kode_produk', 'kode_sku', 'sku', 'kode']));
                $barcode = trim((string) $this->pick($d, ['barcode']));
                $name = trim((string) $this->pick($d, ['nama', 'nama_produk', 'nama_barang', 'produk', 'name']));
                $category = trim((string) $this->pick($d, ['kategori', 'category', 'category_name'], 'Sembako & Beras'));
                $supCode = trim((string) $this->pick($d, ['kode_pemasok', 'supplier_code', 'pemasok']));
                $unit = trim((string) $this->pick($d, ['satuan', 'unit'], 'Pcs'));
                $costRaw = $this->pick($d, ['harga_modal', 'harga_beli', 'modal', 'harga_pokok', 'hpp', 'current_cost', 'cost'], '0');
                $costRaw = $costRaw === '' ? '0' : $costRaw;
                $priceRaw = $this->pick($d, ['harga_jual', 'harga', 'selling_price', 'price']);
                $stockRaw = $this->pick($d, ['stok_awal', 'stok', 'stock', 'initial_stock']);
                $minStockRaw = $this->pick($d, ['min_stok', 'min_stock', 'stok_minimum', 'reorder_point'], '10');
                $minStockRaw = $minStockRaw === '' ? '10' : $minStockRaw;
                $statusRaw = strtoupper(trim((string) $this->pick($d, ['status'], 'ACTIVE')));

                if ($sku === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => '—', 'message' => 'Kode barang (SKU) kosong'];
                    continue;
                }
                if ($name === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Nama produk kosong'];
                    continue;
                }
                if (!is_numeric($costRaw) || (float) $costRaw < 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Harga modal bukan angka valid'];
                    continue;
                }
                if (!is_numeric($priceRaw) || (float) $priceRaw <= 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Harga jual harus angka > 0'];
                    continue;
                }
                if ($stockRaw !== '' && (!is_numeric($stockRaw) || (int) $stockRaw < 0)) {
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
                    'current_cost' => (float) $costRaw,
                    'selling_price' => (float) $priceRaw,
                    'stock' => $stockRaw !== '' ? (int) $stockRaw : null,
                    'reorder_point' => is_numeric($minStockRaw) ? max(0, (int) $minStockRaw) : 10,
                    'is_active' => !in_array($statusRaw, ['INACTIVE', 'NONAKTIF', '0', 'FALSE'], true),
                ];
            } elseif ($type === 'ANGGOTA') {
                $memberNo = trim((string) $this->pick($d, ['nim_nip', 'nim', 'nip', 'no_anggota', 'member_number', 'nomor_anggota', 'kode_anggota', 'id_karyawan', 'employee_number']));
                $empNo = trim((string) $this->pick($d, ['id_karyawan', 'employee_number', 'nip', 'nim', 'nim_nip', 'no_anggota', 'member_number'], $memberNo));
                $fullName = trim((string) $this->pick($d, ['nama_lengkap', 'nama', 'full_name', 'name']));
                $dept = trim((string) $this->pick($d, ['jurusan', 'department', 'divisi', 'unit_kerja', 'unit'], 'Umum'));
                $email = strtolower(trim((string) $this->pick($d, ['email'])));
                $status = strtoupper(trim((string) $this->pick($d, ['status'], 'ACTIVE')));

                if ($memberNo === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => '—', 'message' => 'NIM / NIP / No. anggota kosong'];
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
                $code = strtoupper(trim((string) $this->pick($d, ['kode_pemasok', 'kode', 'code'])));
                $name = trim((string) $this->pick($d, ['nama_pemasok', 'nama', 'name']));
                $phone = trim((string) $this->pick($d, ['kontak', 'telepon', 'no_hp', 'phone']));
                $address = trim((string) $this->pick($d, ['alamat', 'address']));
                $leadRaw = $this->pick($d, ['lead_time_hari', 'lead_time_days', 'lead_time'], '2');
                $leadRaw = $leadRaw === '' ? '2' : $leadRaw;
                $statusRaw = strtoupper(trim((string) $this->pick($d, ['status'], 'ACTIVE')));

                if ($code === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => '—', 'message' => 'Kode pemasok kosong'];
                    continue;
                }
                if ($name === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $code, 'message' => 'Nama pemasok kosong'];
                    continue;
                }
                if (!is_numeric($leadRaw) || (int) $leadRaw < 0) {
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
                    'lead_time_days' => (int) $leadRaw,
                    'is_active' => !in_array($statusRaw, ['INACTIVE', 'NONAKTIF', '0', 'FALSE'], true),
                ];
            } elseif ($type === 'STOK') {
                $sku = trim((string) $this->pick($d, ['kode_barang', 'kode_produk', 'kode_sku', 'sku', 'kode']));
                $stockRaw = $this->pick($d, ['stok_fisik', 'stok_awal', 'stok', 'qty', 'quantity', 'jumlah', 'on_hand']);
                $costRaw = $this->pick($d, ['harga_modal_baru', 'harga_modal', 'harga_beli', 'hpp', 'unit_cost', 'current_cost', 'cost']);

                if ($sku === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => '—', 'message' => 'Kode barang (SKU) kosong'];
                    continue;
                }
                if (!Product::where('sku', $sku)->exists()) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'SKU tidak ditemukan di master produk'];
                    continue;
                }
                if (!is_numeric($stockRaw) || (int) $stockRaw < 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Stok fisik harus angka >= 0'];
                    continue;
                }
                if ($costRaw !== '' && (!is_numeric($costRaw) || (float) $costRaw < 0)) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $sku, 'message' => 'Harga modal baru tidak valid'];
                    continue;
                }

                $duplicateCount++;
                $validRows[] = [
                    'sku' => $sku,
                    'stock' => (int) $stockRaw,
                    'new_cost' => $costRaw !== '' ? (float) $costRaw : null,
                ];
            } elseif ($type === 'TRANSAKSI') {
                $ref = trim((string) $this->pick($d, ['no_referensi', 'nomor_transaksi']));
                $sku = trim((string) $this->pick($d, ['kode_barang', 'kode_produk', 'kode_sku', 'sku']));
                $qty = $this->pick($d, ['qty', 'quantity', 'jumlah']);
                $price = $this->pick($d, ['harga_satuan', 'harga']);

                if ($ref === '' || $sku === '') {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $ref ?: '—', 'message' => 'No. referensi dan kode barang wajib diisi'];
                    continue;
                }
                if (!is_numeric($qty) || (int) $qty <= 0 || !is_numeric($price) || (float) $price < 0) {
                    $errors[] = ['row_number' => $rowNum, 'identifier' => $ref, 'message' => 'Qty atau harga satuan tidak valid'];
                    continue;
                }

                $validRows[] = [
                    'ref' => $ref,
                    'tanggal' => trim((string) ($d['tanggal'] ?? date('Y-m-d'))),
                    'sku' => $sku,
                    'qty' => (int) $qty,
                    'harga_satuan' => (float) $price,
                    'metode_bayar' => strtoupper(trim((string) ($d['metode_bayar'] ?? 'CASH'))),
                ];
            }
        }

        $batchId = (string) Str::uuid();
        $checksum = hash_file('sha256', $file->getRealPath()) ?: sha1($batchId);

        DB::table('import_batches')->insert([
            'id' => $batchId,
            'uploaded_by' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => 'imports/' . $batchId . '.' . $ext,
            'checksum' => $checksum,
            'import_type' => $type,
            'status' => 'PREVIEW',
            'cutoff_at' => $request->input('cut_off_date') ?: null,
            'total_rows' => count($validRows) + count($errors),
            'valid_rows' => count($validRows),
            'error_rows' => count($errors),
            'duplicate_rows' => $duplicateCount,
            'column_mapping' => json_encode($parsed['headers']),
            'quality_metadata' => json_encode([
                'valid_rows' => $validRows,
                'errors' => $errors,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preview = [
            'valid_count' => count($validRows),
            'duplicate_count' => $duplicateCount,
            'errors' => $errors,
        ];

        return response()->json([
            'id' => $batchId,
            'batch' => [
                'id' => $batchId,
                'import_type' => $type,
                'file_name' => $file->getClientOriginalName(),
                'valid_rows' => count($validRows),
                'error_rows' => count($errors),
                'status' => 'PREVIEW',
            ],
            'preview' => $preview,
            'valid_count' => count($validRows),
            'errors' => $errors,
        ]);
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