<?php

namespace App\Services\Import;

/**
 * Smart header auto-detection, synonym/fuzzy column mapping, and currency/numeric parsing
 * shared across Excel/CSV import services and controllers.
 */
class SmartImportMapper
{
    /**
     * Field definitions & ordered synonym dictionaries per normalized import type.
     *
     * @return array<string, array{label: string, required: bool, type: string, synonyms: array<int, string>}>
     */
    public static function fieldDefinitions(string $type): array
    {
        $canonical = self::canonicalType($type);

        return match ($canonical) {
            'PRODUK' => [
                'sku' => [
                    'label' => 'SKU / Kode Barang',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['sku', 'kode_sku', 'kode_produk', 'kode_barang', 'id_produk', 'plu', 'kode', 'barcode'],
                ],
                'name' => [
                    'label' => 'Nama Produk',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['nama_produk', 'nama_barang', 'name', 'nama', 'produk', 'barang', 'item', 'deskripsi', 'uraian'],
                ],
                'selling_price' => [
                    'label' => 'Harga Jual',
                    'required' => true,
                    'type' => 'money',
                    'synonyms' => ['selling_price', 'harga_jual', 'harga_satuan', 'price', 'harga', 'jual', 'tarif'],
                ],
                'current_cost' => [
                    'label' => 'HPP / Harga Modal',
                    'required' => false,
                    'type' => 'money',
                    'synonyms' => ['current_cost', 'harga_pokok', 'harga_modal', 'harga_beli', 'hpp', 'modal', 'beli', 'cost'],
                ],
                'initial_stock' => [
                    'label' => 'Stok Awal',
                    'required' => false,
                    'type' => 'int',
                    'synonyms' => ['initial_stock', 'stok_awal', 'stok_tersedia', 'stok_fisik', 'stok', 'stock', 'qty', 'jumlah', 'kuantitas', 'saldo'],
                ],
                'category' => [
                    'label' => 'Kategori',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['category_name', 'category', 'kategori', 'jenis', 'kelompok'],
                ],
                'unit' => [
                    'label' => 'Satuan',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['unit', 'satuan', 'uom', 'kemasan'],
                ],
                'barcode' => [
                    'label' => 'Barcode',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['barcode', 'ean', 'upc'],
                ],
                'reorder_point' => [
                    'label' => 'Minimum Stok',
                    'required' => false,
                    'type' => 'int',
                    'synonyms' => ['reorder_point', 'min_stock', 'min_stok', 'stok_minimum', 'batas_stok'],
                ],
                'supplier_code' => [
                    'label' => 'Kode Pemasok',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['kode_pemasok', 'supplier_code', 'pemasok', 'supplier'],
                ],
                'status' => [
                    'label' => 'Status',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['status', 'aktif'],
                ],
            ],
            'STOK' => [
                'sku' => [
                    'label' => 'SKU / Kode Barang',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['sku', 'kode_sku', 'kode_produk', 'kode_barang', 'id_produk', 'kode', 'barcode'],
                ],
                'quantity' => [
                    'label' => 'Stok / Qty',
                    'required' => true,
                    'type' => 'int',
                    'synonyms' => ['stok_fisik', 'stok_awal', 'stok_tersedia', 'on_hand', 'quantity', 'qty', 'stok', 'stock', 'jumlah', 'kuantitas', 'saldo'],
                ],
                'unit_cost' => [
                    'label' => 'HPP / Harga Modal Baru',
                    'required' => false,
                    'type' => 'money',
                    'synonyms' => ['harga_modal_baru', 'unit_cost', 'current_cost', 'harga_modal', 'harga_beli', 'hpp', 'modal', 'beli', 'cost'],
                ],
            ],
            'ANGGOTA' => [
                'employee_number' => [
                    'label' => 'NIM / NIP / ID Anggota',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['nim_nip', 'nim', 'nip', 'employee_number', 'id_karyawan', 'id_anggota', 'member_number', 'no_anggota', 'nomor_anggota', 'kode_anggota', 'nomor', 'member'],
                ],
                'full_name' => [
                    'label' => 'Nama Lengkap',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['full_name', 'nama_lengkap', 'name', 'nama', 'anggota', 'karyawan', 'mahasiswa', 'pegawai'],
                ],
                'department' => [
                    'label' => 'Jurusan / Unit',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['jurusan', 'prodi', 'program_studi', 'department', 'divisi', 'unit_kerja', 'unit', 'departemen', 'bagian'],
                ],
                'email' => [
                    'label' => 'Email',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['email', 'surel', 'mail'],
                ],
                'member_number' => [
                    'label' => 'Nomor Anggota',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['member_number', 'no_anggota', 'nomor_anggota', 'kode_anggota'],
                ],
                'status' => [
                    'label' => 'Status',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['status', 'aktif'],
                ],
            ],
            'PEMASOK' => [
                'code' => [
                    'label' => 'Kode Pemasok',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['kode_pemasok', 'code', 'kode', 'id_pemasok'],
                ],
                'name' => [
                    'label' => 'Nama Pemasok',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['nama_pemasok', 'name', 'nama', 'pemasok', 'supplier', 'perusahaan'],
                ],
                'phone' => [
                    'label' => 'Kontak / Telepon',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['phone', 'kontak', 'telepon', 'telp', 'no_hp', 'hp', 'whatsapp', 'wa'],
                ],
                'address' => [
                    'label' => 'Alamat',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['address', 'alamat', 'lokasi'],
                ],
                'lead_time_days' => [
                    'label' => 'Lead Time (Hari)',
                    'required' => false,
                    'type' => 'int',
                    'synonyms' => ['lead_time_hari', 'lead_time_days', 'lead_time'],
                ],
                'status' => [
                    'label' => 'Status',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['status', 'aktif'],
                ],
            ],
            default => [
                'ref' => [
                    'label' => 'No. Referensi',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['no_referensi', 'nomor_transaksi', 'referensi', 'order_no', 'no_order', 'invoice', 'faktur'],
                ],
                'sku' => [
                    'label' => 'SKU / Kode Barang',
                    'required' => true,
                    'type' => 'string',
                    'synonyms' => ['sku', 'kode_sku', 'kode_produk', 'kode_barang', 'id_produk', 'kode'],
                ],
                'qty' => [
                    'label' => 'Qty',
                    'required' => true,
                    'type' => 'int',
                    'synonyms' => ['qty', 'quantity', 'jumlah', 'kuantitas'],
                ],
                'price' => [
                    'label' => 'Harga Satuan',
                    'required' => true,
                    'type' => 'money',
                    'synonyms' => ['harga_satuan', 'harga_jual', 'harga', 'price', 'tarif'],
                ],
                'tanggal' => [
                    'label' => 'Tanggal',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['tanggal', 'tgl', 'date'],
                ],
                'metode_bayar' => [
                    'label' => 'Metode Bayar',
                    'required' => false,
                    'type' => 'string',
                    'synonyms' => ['metode_bayar', 'pembayaran', 'payment', 'metode'],
                ],
            ],
        };
    }

    public static function canonicalType(string $type): string
    {
        return match (strtoupper(trim($type))) {
            'PRODUK', 'PRODUCTS', 'PRODUCT' => 'PRODUK',
            'STOK', 'STOCK', 'STOCK_OPENING' => 'STOK',
            'ANGGOTA', 'MEMBERS', 'MEMBER' => 'ANGGOTA',
            'PEMASOK', 'SUPPLIERS', 'SUPPLIER' => 'PEMASOK',
            'TRANSAKSI', 'TRANSACTIONS', 'TRANSACTION' => 'TRANSAKSI',
            default => 'PRODUK',
        };
    }

    public static function normalizeHeader(string $header): string
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $clean = strtolower(trim((string) $clean));
        $clean = preg_replace('/[^a-z0-9]+/', '_', $clean);

        return trim((string) $clean, '_');
    }

    /**
     * Scan rows 0..min(4, count-1) and select the row that best looks like a column header row,
     * skipping title banners, subtitles, or blank rows above the actual table.
     *
     * @param  array<int, array<int, string>>  $denseRows
     */
    public static function detectHeaderRowIndex(array $denseRows, string $type = ''): int
    {
        $maxScan = min(5, count($denseRows));
        if ($maxScan <= 1) {
            return 0;
        }

        $defs = self::fieldDefinitions($type);
        $allSynonyms = [];
        foreach ($defs as $def) {
            foreach ($def['synonyms'] as $syn) {
                $allSynonyms[] = $syn;
            }
        }
        $allSynonyms = array_values(array_unique($allSynonyms));

        $bestIdx = 0;
        $bestScore = -9999;

        for ($r = 0; $r < $maxScan; $r++) {
            $cells = $denseRows[$r] ?? [];
            $nonEmpty = array_values(array_filter($cells, fn ($v) => trim((string) $v) !== ''));
            if (empty($nonEmpty)) {
                continue;
            }

            $score = 0;
            // Baris judul tunggal seperti "DAFTAR PRODUK KOPERASI" sangat mungkin bukan baris header tabel
            if (count($nonEmpty) === 1 && count($denseRows) > $r + 1) {
                $nextNonEmpty = array_filter($denseRows[$r + 1] ?? [], fn ($v) => trim((string) $v) !== '');
                if (count($nextNonEmpty) > 1) {
                    $score -= 5;
                }
            }

            $matchedCols = 0;
            foreach ($nonEmpty as $cellVal) {
                $rawStr = trim((string) $cellVal);
                $norm = self::normalizeHeader($rawStr);
                if ($norm === '') {
                    continue;
                }

                // Nilai uang atau angka murni di dalam sel menandakan baris data, bukan header
                if (self::parseNumber($rawStr) !== null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawStr)) {
                    $score -= 3;
                    continue;
                }

                $colMatched = false;
                if (in_array($norm, $allSynonyms, true)) {
                    $score += 6;
                    $colMatched = true;
                } else {
                    foreach ($allSynonyms as $syn) {
                        if (strlen($syn) >= 3 && (str_contains($norm, $syn) || str_contains($syn, $norm))) {
                            $score += 3;
                            $colMatched = true;
                            break;
                        }
                    }
                }

                if ($colMatched) {
                    $matchedCols++;
                } elseif (strlen($rawStr) <= 35 && preg_match('/^[a-zA-Z]/', $rawStr)) {
                    $score += 1;
                }
            }

            if ($matchedCols >= 2) {
                $score += 5;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $r;
            }
        }

        return $bestIdx;
    }

    /**
     * Build automatic mapping [systemField => normalizedFileHeaderKey] using exact match then fuzzy substring match.
     *
     * @param  array<int, array{key: string, label: string}>  $fileHeaders
     * @return array<string, string|null>
     */
    public static function autoMapHeaders(array $fileHeaders, string $type): array
    {
        $defs = self::fieldDefinitions($type);
        $mapping = [];
        $usedHeaderKeys = [];

        foreach ($defs as $field => $_def) {
            $mapping[$field] = null;
        }

        $headerKeys = array_values(array_unique(array_filter(array_map(fn ($h) => $h['key'] ?? '', $fileHeaders))));

        // Pass 1: Exact synonym match (prioritized by synonym list order)
        foreach ($defs as $field => $def) {
            foreach ($def['synonyms'] as $syn) {
                if (in_array($syn, $headerKeys, true) && ! isset($usedHeaderKeys[$syn])) {
                    $mapping[$field] = $syn;
                    $usedHeaderKeys[$syn] = true;
                    break;
                }
            }
        }

        // Pass 2: Fuzzy substring/token match on unmapped headers, preferring longest synonym match
        $candidates = [];
        foreach ($defs as $field => $def) {
            if ($mapping[$field] !== null) {
                continue;
            }
            foreach ($headerKeys as $hKey) {
                if (isset($usedHeaderKeys[$hKey])) {
                    continue;
                }
                foreach ($def['synonyms'] as $synIdx => $syn) {
                    if (strlen($syn) < 3) {
                        continue;
                    }
                    $hTokens = explode('_', $hKey);
                    $score = 0;
                    if (in_array($syn, $hTokens, true)) {
                        $score = 100 + strlen($syn) * 2 - $synIdx;
                    } elseif (str_contains($hKey, $syn)) {
                        $score = 60 + strlen($syn) * 2 - $synIdx;
                    } elseif (strlen($hKey) >= 4 && str_contains($syn, $hKey)) {
                        $score = 40 + strlen($hKey) - $synIdx;
                    }
                    if ($score > 0) {
                        $candidates[] = [
                            'field' => $field,
                            'header' => $hKey,
                            'score' => $score,
                        ];
                    }
                }
            }
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        foreach ($candidates as $cand) {
            $f = $cand['field'];
            $h = $cand['header'];
            if ($mapping[$f] !== null || isset($usedHeaderKeys[$h])) {
                continue;
            }
            $mapping[$f] = $h;
            $usedHeaderKeys[$h] = true;
        }

        return $mapping;
    }

    /**
     * Apply system->file mapping onto one raw row keyed by normalized file headers.
     * Preserves unmapped original keys as fallback so legacy lookups also keep working.
     *
     * @param  array<string, mixed>  $rawRow
     * @param  array<string, string|null>  $mapping
     * @return array<string, mixed>
     */
    public static function applyMappingToRow(array $rawRow, array $mapping): array
    {
        $resolved = $rawRow;
        foreach ($mapping as $sysField => $fileHeaderKey) {
            if ($fileHeaderKey !== null && $fileHeaderKey !== '' && array_key_exists($fileHeaderKey, $rawRow)) {
                $resolved[$sysField] = $rawRow[$fileHeaderKey];
            }
        }

        return $resolved;
    }

    /**
     * Metadata list for frontend mapping UI (auto-detected & missing required fields).
     *
     * @param  array<int, array{key: string, label: string}>  $fileHeaders
     * @param  array<string, string|null>  $mapping
     * @return array{fields: array<int, array<string, mixed>>, missing_required: array<int, string>}
     */
    public static function mappingSummary(array $fileHeaders, array $mapping, string $type): array
    {
        $defs = self::fieldDefinitions($type);
        $labelByKey = [];
        foreach ($fileHeaders as $h) {
            $labelByKey[$h['key']] = $h['label'];
        }

        $fields = [];
        $missing = [];
        foreach ($defs as $field => $def) {
            $mappedKey = $mapping[$field] ?? null;
            $isMapped = $mappedKey !== null && $mappedKey !== '' && isset($labelByKey[$mappedKey]);
            if ($def['required'] && ! $isMapped) {
                $missing[] = $def['label'];
            }
            $fields[] = [
                'field' => $field,
                'label' => $def['label'],
                'required' => $def['required'],
                'mapped_header' => $isMapped ? $mappedKey : null,
                'mapped_label' => $isMapped ? $labelByKey[$mappedKey] : null,
            ];
        }

        return [
            'fields' => $fields,
            'missing_required' => $missing,
        ];
    }

    /**
     * Parse Indonesian/international currency & numeric formats:
     * "Rp 15.000", "Rp15.000,-", "15,000", "15.000,50", "15000", etc.
     */
    public static function parseNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // Remove currency symbols & trailing ",-"
        $clean = preg_replace('/^(rp\.?|idr)\s*/i', '', $str);
        $clean = preg_replace('/\s*(rp\.?|idr)$/i', '', (string) $clean);
        $clean = preg_replace('/,\s*-$/', '', (string) $clean);
        $clean = str_replace(["\u{00A0}", ' '], '', (string) $clean);

        if ($clean === '' || ! preg_match('/^[+-]?[\d.,]+$/', $clean)) {
            return null;
        }

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($clean, '.');
            $lastComma = strrpos($clean, ',');
            if ($lastComma > $lastDot) {
                // 15.000,50 -> 15000.50
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                // 15,000.50 -> 15000.50
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasDot) {
            // Multiple dots (1.250.000) or single dot with exact 3 trailing digits (15.000) -> thousands separator
            if (substr_count($clean, '.') > 1 || preg_match('/^[+-]?\d{1,3}(\.\d{3})+$/', $clean)) {
                $clean = str_replace('.', '', $clean);
            }
        } elseif ($hasComma) {
            if (substr_count($clean, ',') > 1 || preg_match('/^[+-]?\d{1,3}(,\d{3})+$/', $clean)) {
                // 15,000 -> 15000
                $clean = str_replace(',', '', $clean);
            } else {
                // 15,5 -> 15.5
                $clean = str_replace(',', '.', $clean);
            }
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
