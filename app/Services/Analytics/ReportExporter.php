<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use ZipArchive;

class ReportExporter
{
    public function csv(array $data): string
    {
        $p = $this->prepareReportData($data);
        $lines = [];

        $csvRow = static fn (array $cols): string => implode(',', array_map(
            static fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
            $cols
        ));

        $lines[] = "\xEF\xBB\xBF".$csvRow([$p['coop_name'], '']);
        $lines[] = $csvRow(['Judul Laporan', $p['title']]);
        $lines[] = $csvRow(['Periode Aktif', $p['period_text']]);
        $lines[] = $csvRow(['Waktu Cetak', $p['printed_at']]);
        $lines[] = '';

        $lines[] = $csvRow(['RINGKASAN LABA RUGI', 'Nilai (Rp)', 'Nilai Terformat']);
        foreach ($p['profit_loss_rows'] as [$label, $numVal, $formattedVal]) {
            $lines[] = $csvRow([$label, $numVal, $formattedVal]);
        }
        $lines[] = '';

        $lines[] = $csvRow(['RINCIAN METODE PEMBAYARAN', 'Jumlah Transaksi', 'Subtotal (Rp)', 'Subtotal Terformat']);
        foreach ($p['payment_rows'] as $pm) {
            $lines[] = $csvRow([$pm['label'], $pm['orders_count'], $pm['amount'], $this->fmtRp((float) $pm['amount'])]);
        }
        $lines[] = '';

        $lines[] = $csvRow(['DAFTAR PRODUK TERLARIS', 'SKU', 'Qty Terjual', 'Satuan', 'Total Omzet (Rp)', 'Total Omzet Terformat']);
        if (empty($p['top_products'])) {
            $lines[] = $csvRow(['Tidak ada transaksi produk pada periode ini.', '-', 0, '-', '0.00', 'Rp 0']);
        } else {
            foreach ($p['top_products'] as $idx => $prod) {
                $amt = (float) ($prod['sales_revenue'] ?? $prod['omzet'] ?? 0);
                $lines[] = $csvRow([
                    ($idx + 1).'. '.($prod['name'] ?? '-'),
                    $prod['sku'] ?? '-',
                    (int) ($prod['units'] ?? $prod['sold_qty'] ?? 0),
                    $prod['unit'] ?? 'unit',
                    number_format($amt, 2, '.', ''),
                    $this->fmtRp($amt),
                ]);
            }
        }
        $lines[] = '';

        $lines[] = $csvRow(['RINCIAN BIAYA OPERASIONAL', 'Kategori', 'Keterangan', 'Nominal (Rp)', 'Nominal Terformat']);
        if (empty($p['expenses'])) {
            $lines[] = $csvRow(['Tidak ada biaya operasional pada periode ini.', '-', '-', '0.00', 'Rp 0']);
        } else {
            foreach ($p['expenses'] as $exp) {
                $amt = (float) ($exp['amount'] ?? 0);
                $lines[] = $csvRow([
                    $exp['expense_date'] ?? '-',
                    $exp['category'] ?? '-',
                    $exp['description'] ?? '-',
                    number_format($amt, 2, '.', ''),
                    $this->fmtRp($amt),
                ]);
            }
        }

        if (! empty($p['trend_rows'])) {
            $lines[] = '';
            $lines[] = $csvRow(['TREN PERIODE', 'Omzet (Rp)', 'HPP (Rp)', 'Laba Kotor (Rp)', 'Kas Masuk (Rp)', 'Biaya Operasional (Rp)', 'Laba Bersih (Rp)']);
            foreach ($p['trend_rows'] as $r) {
                $lines[] = $csvRow([
                    $r['period'] ?? $r['date'] ?? $r['label'] ?? '-',
                    $r['sales_revenue'] ?? '0.00',
                    $r['cogs'] ?? '0.00',
                    $r['gross_profit'] ?? '0.00',
                    $r['cash_receipts'] ?? '0.00',
                    $r['operating_expenses'] ?? '0.00',
                    $r['estimated_net_profit'] ?? '0.00',
                ]);
            }
        }

        return implode("\r\n", $lines)."\r\n";
    }

    public function xlsx(array $data): string
    {
        if (! class_exists(ZipArchive::class)) {
            return $this->csv($data);
        }

        $p = $this->prepareReportData($data);

        // Tiap sel: [value, styleIndex (0=normal, 1=bold header, 2=section title, 3=currency Rp numeric, 4=integer numeric)]
        $sheetRows = [];
        $sheetRows[] = [[$p['coop_name'], 2]];
        $sheetRows[] = [[$p['title'], 2]];
        $sheetRows[] = [['Periode Aktif', 1], [$p['period_text'], 0]];
        $sheetRows[] = [['Waktu Cetak', 1], [$p['printed_at'], 0]];
        $sheetRows[] = [];

        $sheetRows[] = [['RINGKASAN LABA RUGI', 2], ['', 2], ['', 2]];
        $sheetRows[] = [['Pos Keuangan', 1], ['Nilai', 1], ['Keterangan', 1]];
        foreach ($p['profit_loss_rows'] as [$label, $rawNum, $formatted, $isCurrency]) {
            $sheetRows[] = [
                [$label, 0],
                [$isCurrency ? (float) $rawNum : $formatted, $isCurrency ? 3 : 0],
                [$formatted, 0],
            ];
        }
        $sheetRows[] = [];

        $sheetRows[] = [['RINCIAN METODE PEMBAYARAN', 2], ['', 2], ['', 2]];
        $sheetRows[] = [['Metode Pembayaran', 1], ['Jumlah Transaksi', 1], ['Subtotal Omzet', 1]];
        foreach ($p['payment_rows'] as $pm) {
            $sheetRows[] = [
                [$pm['label'], 0],
                [(int) $pm['orders_count'], 4],
                [(float) $pm['amount'], 3],
            ];
        }
        $sheetRows[] = [];

        $sheetRows[] = [['DAFTAR PRODUK TERLARIS', 2], ['', 2], ['', 2], ['', 2], ['', 2]];
        $sheetRows[] = [['SKU', 1], ['Nama Produk', 1], ['Qty Terjual', 1], ['Satuan', 1], ['Total Omzet', 1]];
        if (empty($p['top_products'])) {
            $sheetRows[] = [['-', 0], ['Tidak ada penjualan produk pada periode ini.', 0], [0, 4], ['-', 0], [0, 3]];
        } else {
            foreach ($p['top_products'] as $prod) {
                $sheetRows[] = [
                    [$prod['sku'] ?? '-', 0],
                    [$prod['name'] ?? '-', 0],
                    [(int) ($prod['units'] ?? $prod['sold_qty'] ?? 0), 4],
                    [$prod['unit'] ?? 'unit', 0],
                    [(float) ($prod['sales_revenue'] ?? $prod['omzet'] ?? 0), 3],
                ];
            }
        }
        $sheetRows[] = [];

        $sheetRows[] = [['RINCIAN BIAYA OPERASIONAL', 2], ['', 2], ['', 2], ['', 2]];
        $sheetRows[] = [['Tanggal', 1], ['Kategori', 1], ['Keterangan', 1], ['Nominal', 1]];
        if (empty($p['expenses'])) {
            $sheetRows[] = [['-', 0], ['-', 0], ['Tidak ada catatan biaya operasional pada periode ini.', 0], [0, 3]];
        } else {
            foreach ($p['expenses'] as $exp) {
                $sheetRows[] = [
                    [$exp['expense_date'] ?? '-', 0],
                    [$exp['category'] ?? '-', 0],
                    [$exp['description'] ?? '-', 0],
                    [(float) ($exp['amount'] ?? 0), 3],
                ];
            }
        }

        if (! empty($p['trend_rows'])) {
            $sheetRows[] = [];
            $sheetRows[] = [['TREN HARIAN / PERIODE', 2], ['', 2], ['', 2], ['', 2], ['', 2], ['', 2]];
            $sheetRows[] = [['Tanggal / Periode', 1], ['Omzet', 1], ['HPP', 1], ['Laba Kotor', 1], ['Kas Masuk', 1], ['Biaya Operasional', 1], ['Laba Bersih', 1]];
            foreach ($p['trend_rows'] as $r) {
                $sheetRows[] = [
                    [$r['period'] ?? $r['date'] ?? $r['label'] ?? '-', 0],
                    [(float) ($r['sales_revenue'] ?? 0), 3],
                    [(float) ($r['cogs'] ?? 0), 3],
                    [(float) ($r['gross_profit'] ?? 0), 3],
                    [(float) ($r['cash_receipts'] ?? 0), 3],
                    [(float) ($r['operating_expenses'] ?? 0), 3],
                    [(float) ($r['estimated_net_profit'] ?? 0), 3],
                ];
            }
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive;
        if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
            return $this->csv($data);
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Laporan Keuangan" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Rp &quot;#,##0;(&quot;Rp &quot;#,##0);&quot;Rp &quot;0"/></numFmts>'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF065F46"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF059669"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFECFDF5"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>');

        $colsXml = '<cols>'
            .'<col min="1" max="1" width="32" customWidth="1"/>'
            .'<col min="2" max="2" width="34" customWidth="1"/>'
            .'<col min="3" max="3" width="38" customWidth="1"/>'
            .'<col min="4" max="4" width="20" customWidth="1"/>'
            .'<col min="5" max="7" width="22" customWidth="1"/>'
            .'</cols>';

        $xmlRows = '';
        foreach ($sheetRows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $xmlRows .= '<row r="'.$rowNum.'">';
            foreach ($row as $cIdx => $cell) {
                $val = $cell[0];
                $style = (int) ($cell[1] ?? 0);
                $colLetter = chr(65 + $cIdx);
                if (is_int($val) || is_float($val)) {
                    $xmlRows .= '<c r="'.$colLetter.$rowNum.'" s="'.$style.'"><v>'.$val.'</v></c>';
                } else {
                    $escaped = htmlspecialchars((string) $val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xmlRows .= '<c r="'.$colLetter.$rowNum.'" t="inlineStr" s="'.$style.'"><is><t>'.$escaped.'</t></is></c>';
                }
            }
            $xmlRows .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$colsXml
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'</worksheet>');

        $zip->close();
        $bytes = (string) file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $bytes;
    }

    public function pdf(array $data): string
    {
        $p = $this->prepareReportData($data);
        $lines = [];

        $lines[] = ['bold', $p['coop_name']];
        $lines[] = ['bold', $p['title']];
        if ($p['coop_address'] !== '') {
            $lines[] = ['normal', $this->truncate($p['coop_address'], 84)];
        }
        $lines[] = ['normal', 'Periode Laporan : '.$p['period_text']];
        $lines[] = ['normal', 'Waktu Cetak     : '.$p['printed_at']];
        $lines[] = ['normal', str_repeat('=', 84)];

        $lines[] = ['bold', '1. RINGKASAN LABA RUGI & KAS'];
        $lines[] = ['normal', str_repeat('-', 84)];
        foreach ($p['profit_loss_rows'] as [$label, $_raw, $formatted]) {
            $lines[] = ['normal', sprintf('  %-44s %36s', $this->truncate($label, 44), $formatted)];
        }
        $lines[] = ['normal', ''];

        $lines[] = ['bold', '2. RINCIAN METODE PEMBAYARAN'];
        $lines[] = ['normal', str_repeat('-', 84)];
        $lines[] = ['bold', sprintf('  %-34s %14s %30s', 'Metode Pembayaran', 'Transaksi', 'Subtotal Omzet')];
        foreach ($p['payment_rows'] as $pm) {
            $lines[] = ['normal', sprintf(
                '  %-34s %14s %30s',
                $this->truncate($pm['label'], 34),
                number_format((int) $pm['orders_count'], 0, ',', '.').' trx',
                $this->fmtRp((float) $pm['amount'])
            )];
        }
        $lines[] = ['normal', ''];

        $lines[] = ['bold', '3. DAFTAR PRODUK TERLARIS (TOP 10)'];
        $lines[] = ['normal', str_repeat('-', 84)];
        if (empty($p['top_products'])) {
            $lines[] = ['normal', '  Belum ada transaksi produk pada periode ini.'];
        } else {
            $lines[] = ['bold', sprintf('  %-10s %-34s %12s %22s', 'SKU', 'Nama Produk', 'Qty', 'Total Omzet')];
            foreach ($p['top_products'] as $prod) {
                $qtyText = number_format((int) ($prod['units'] ?? $prod['sold_qty'] ?? 0), 0, ',', '.').' '.($prod['unit'] ?? '');
                $amt = (float) ($prod['sales_revenue'] ?? $prod['omzet'] ?? 0);
                $lines[] = ['normal', sprintf(
                    '  %-10s %-34s %12s %22s',
                    $this->truncate((string) ($prod['sku'] ?? '-'), 10),
                    $this->truncate((string) ($prod['name'] ?? '-'), 34),
                    $this->truncate(trim($qtyText), 12),
                    $this->fmtRp($amt)
                )];
            }
        }
        $lines[] = ['normal', ''];

        $lines[] = ['bold', '4. RINCIAN BIAYA OPERASIONAL'];
        $lines[] = ['normal', str_repeat('-', 84)];
        if (empty($p['expenses'])) {
            $lines[] = ['normal', '  Tidak ada biaya operasional tercatat pada periode ini.'];
        } else {
            $lines[] = ['bold', sprintf('  %-12s %-14s %-34s %18s', 'Tanggal', 'Kategori', 'Keterangan', 'Nominal')];
            foreach ($p['expenses'] as $exp) {
                $amt = (float) ($exp['amount'] ?? 0);
                $lines[] = ['normal', sprintf(
                    '  %-12s %-14s %-34s %18s',
                    substr((string) ($exp['expense_date'] ?? '-'), 0, 10),
                    $this->truncate((string) ($exp['category'] ?? '-'), 14),
                    $this->truncate((string) ($exp['description'] ?? '-'), 34),
                    $this->fmtRp($amt)
                )];
            }
        }

        // Paginate into A4 pages (46 lines per page)
        $pages = array_chunk($lines, 46);
        if (empty($pages)) {
            $pages = [[['normal', 'Tidak ada data laporan.']]];
        }
        $totalPages = count($pages);

        // Object IDs:
        // 1: Catalog, 2: Pages, 3: F1 Courier, 4: F2 Courier-Bold, then per page (pageObj, contentObj)
        $objs = [];
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>';

        $pageRefs = [];
        $nextId = 5;
        foreach ($pages as $pIdx => $pageLines) {
            $pageObjId = $nextId++;
            $contentObjId = $nextId++;
            $pageRefs[] = "{$pageObjId} 0 R";

            $stream = "BT\n42 790 Td\n14.5 TL\n";
            foreach ($pageLines as [$weight, $txt]) {
                $font = $weight === 'bold' ? '/F2' : '/F1';
                $size = ($pIdx === 0 && $weight === 'bold' && str_contains($txt, $p['coop_name'])) ? '10' : '9.2';
                $safe = $this->pdfSafeText($txt);
                $stream .= "{$font} {$size} Tf\n({$safe}) Tj\nT*\n";
            }
            $footerText = sprintf('Halaman %d dari %d  |  %s', $pIdx + 1, $totalPages, $p['printed_at']);
            $stream .= "ET\nBT\n/F1 8 Tf\n42 28 Td\n(".$this->pdfSafeText($footerText).") Tj\nET\n";

            $objs[$pageObjId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObjId} 0 R >>";
            $objs[$contentObjId] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}endstream";
        }

        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageRefs).'] /Count '.count($pageRefs).' >>';
        ksort($objs);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objs) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function prepareReportData(array $data): array
    {
        $m = $data['metrics'] ?? [];
        $period = $data['period'] ?? ['start' => '-', 'end' => '-'];
        $start = (string) ($period['start'] ?? '-');
        $end = (string) ($period['end'] ?? '-');

        $rev = (float) ($m['sales_revenue'] ?? 0);
        $cogs = (float) ($m['cogs'] ?? 0);
        $gp = (float) ($m['gross_profit'] ?? ($rev - $cogs));
        $opex = (float) ($m['operating_expenses'] ?? 0);
        $net = (float) ($m['estimated_net_profit'] ?? ($gp - $opex));
        $marginPct = $rev > 0 ? round(($gp / $rev) * 100, 1) : 0.0;
        $txCount = (int) ($m['completed_orders_count'] ?? 0);
        $cashIn = (float) ($m['cash_receipts'] ?? 0);
        $openRecv = (float) ($m['receivables_outstanding_as_of_end'] ?? 0);
        $overdueRecv = (float) ($m['overdue_outstanding_as_of_end'] ?? 0);

        $paymentRows = $data['payment_breakdown'] ?? null;
        if (! is_array($paymentRows) || empty($paymentRows)) {
            $cashSales = (float) ($m['cash_sales_revenue'] ?? 0);
            $creditSales = (float) ($m['new_credit_sales'] ?? 0);
            $paymentRows = [
                ['method' => 'CASH', 'label' => 'Tunai & Transfer Bank', 'orders_count' => 0, 'amount' => number_format($cashSales, 2, '.', '')],
                ['method' => 'CREDIT', 'label' => 'Piutang Anggota', 'orders_count' => 0, 'amount' => number_format($creditSales, 2, '.', '')],
            ];
        }

        $brand = function_exists('config') ? (string) config('koperasi.brand_name', 'KoperasiKu') : 'KoperasiKu';
        $full = function_exists('config') ? (string) config('koperasi.full_name', 'Koperasi Konsumen Pegawai (KOPEG)') : 'Koperasi Konsumen Pegawai (KOPEG)';
        $addr = function_exists('config') ? (string) config('koperasi.contact.address', '') : '';

        return [
            'coop_name' => "{$brand} - {$full}",
            'coop_address' => $addr,
            'title' => 'Laporan Keuangan & Analitik Operasional',
            'period_text' => ($start === $end) ? $start : "{$start} s/d {$end}",
            'printed_at' => CarbonImmutable::now('Asia/Jakarta')->format('d M Y, H:i').' WIB',
            'profit_loss_rows' => [
                ['Total Omzet Penjualan Selesai', number_format($rev, 2, '.', ''), $this->fmtRp($rev), true],
                ['Total HPP Barang Terjual', number_format($cogs, 2, '.', ''), $this->fmtRp($cogs), true],
                ['Laba Kotor', number_format($gp, 2, '.', ''), $this->fmtRp($gp), true],
                ['Margin Laba Kotor', number_format($marginPct, 1, '.', ''), number_format($marginPct, 1, ',', '.').'%', false],
                ['Total Biaya Operasional', number_format($opex, 2, '.', ''), $this->fmtRp($opex), true],
                ['Estimasi Laba Bersih', number_format($net, 2, '.', ''), $this->fmtRp($net), true],
                ['Transaksi Selesai', (string) $txCount, number_format($txCount, 0, ',', '.').' transaksi', false],
                ['Kas Masuk Terverifikasi', number_format($cashIn, 2, '.', ''), $this->fmtRp($cashIn), true],
                ['Piutang Terbuka (Akhir Periode)', number_format($openRecv, 2, '.', ''), $this->fmtRp($openRecv), true],
                ['Piutang Jatuh Tempo (Overdue)', number_format($overdueRecv, 2, '.', ''), $this->fmtRp($overdueRecv), true],
            ],
            'payment_rows' => $paymentRows,
            'top_products' => is_array($data['top_products'] ?? null) ? $data['top_products'] : [],
            'expenses' => is_array($data['operating_expenses_items'] ?? null) ? $data['operating_expenses_items'] : [],
            'trend_rows' => $data['rows'] ?? $data['trend'] ?? [],
        ];
    }

    private function fmtRp(float $val): string
    {
        $prefix = $val < 0 ? '-Rp ' : 'Rp ';

        return $prefix.number_format(abs($val), 0, ',', '.');
    }

    private function truncate(string $text, int $max): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (mb_strlen($clean) <= $max) {
            return $clean;
        }

        return mb_substr($clean, 0, max(1, $max - 3)).'...';
    }

    private function pdfSafeText(string $text): string
    {
        $ascii = str_replace(
            ['–', '—', '·', '×', '“', '”', '‘', '’'],
            ['-', '-', '|', 'x', '"', '"', "'", "'"],
            $text
        );
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ascii) ?: preg_replace('/[^\x20-\x7E]/', ' ', $ascii);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $ascii);
    }
}
