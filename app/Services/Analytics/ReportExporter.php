<?php

namespace App\Services\Analytics;

use ZipArchive;

class ReportExporter
{
    public function csv(array $data): string
    {
        $m = $data['metrics'] ?? [];
        $period = $data['period'] ?? ['start' => '-', 'end' => '-'];
        $rows = $data['rows'] ?? $data['trend'] ?? [];

        $lines = [];
        $lines[] = "\xEF\xBB\xBFKomponen,Nilai (Rp)";
        $lines[] = '"Periode Mulai","'.($period['start'] ?? '-').'"';
        $lines[] = '"Periode Akhir","'.($period['end'] ?? '-').'"';
        $lines[] = '"Omzet Penjualan Selesai","'.($m['sales_revenue'] ?? '0.00').'"';
        $lines[] = '"Penjualan Tunai & Transfer","'.($m['cash_sales_revenue'] ?? '0.00').'"';
        $lines[] = '"Penjualan Piutang Baru","'.($m['new_credit_sales'] ?? '0.00').'"';
        $lines[] = '"HPP Barang Terjual","'.($m['cogs'] ?? '0.00').'"';
        $lines[] = '"Laba Kotor","'.($m['gross_profit'] ?? '0.00').'"';
        $lines[] = '"Biaya Operasional","'.($m['operating_expenses'] ?? '0.00').'"';
        $lines[] = '"Estimasi Laba Bersih","'.($m['estimated_net_profit'] ?? '0.00').'"';
        $lines[] = '"Kas Masuk Terverifikasi","'.($m['cash_receipts'] ?? '0.00').'"';
        $lines[] = '"Piutang Terbuka (Akhir Periode)","'.($m['receivables_outstanding_as_of_end'] ?? '0.00').'"';
        $lines[] = '"Piutang Jatuh Tempo (Akhir Periode)","'.($m['overdue_outstanding_as_of_end'] ?? '0.00').'"';

        if (! empty($rows)) {
            $lines[] = '';
            $lines[] = 'Periode,Omzet Penjualan,HPP,Laba Kotor,Kas Masuk,Biaya Operasional,Estimasi Laba Bersih';
            foreach ($rows as $r) {
                $lines[] = sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s"',
                    $r['period'] ?? $r['date'] ?? $r['label'] ?? '-',
                    $r['sales_revenue'] ?? '0.00',
                    $r['cogs'] ?? '0.00',
                    $r['gross_profit'] ?? '0.00',
                    $r['cash_receipts'] ?? '0.00',
                    $r['operating_expenses'] ?? '0.00',
                    $r['estimated_net_profit'] ?? '0.00'
                );
            }
        }

        return implode("\r\n", $lines)."\r\n";
    }

    public function xlsx(array $data): string
    {
        $m = $data['metrics'] ?? [];
        $period = $data['period'] ?? ['start' => '-', 'end' => '-'];

        $sheetRows = [
            ['Laporan Keuangan KoperasiKu', ''],
            ['Periode', ($period['start'] ?? '-').' s/d '.($period['end'] ?? '-')],
            ['Komponen', 'Nilai (Rp)'],
            ['Omzet Penjualan Selesai', (string) ($m['sales_revenue'] ?? '0.00')],
            ['Penjualan Tunai & Transfer', (string) ($m['cash_sales_revenue'] ?? '0.00')],
            ['Penjualan Piutang Baru', (string) ($m['new_credit_sales'] ?? '0.00')],
            ['HPP Barang Terjual', (string) ($m['cogs'] ?? '0.00')],
            ['Laba Kotor', (string) ($m['gross_profit'] ?? '0.00')],
            ['Biaya Operasional', (string) ($m['operating_expenses'] ?? '0.00')],
            ['Estimasi Laba Bersih', (string) ($m['estimated_net_profit'] ?? '0.00')],
            ['Kas Masuk Terverifikasi', (string) ($m['cash_receipts'] ?? '0.00')],
            ['Piutang Terbuka (Akhir Periode)', (string) ($m['receivables_outstanding_as_of_end'] ?? '0.00')],
            ['Piutang Jatuh Tempo (Akhir Periode)', (string) ($m['overdue_outstanding_as_of_end'] ?? '0.00')],
        ];

        if (! class_exists(ZipArchive::class)) {
            return $this->csv($data);
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
            .'</Relationships>');

        $xmlRows = '';
        foreach ($sheetRows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $xmlRows .= '<row r="'.$rowNum.'">';
            foreach ($row as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                $escaped = htmlspecialchars((string) $val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xmlRows .= '<c r="'.$colLetter.$rowNum.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }
            $xmlRows .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'</worksheet>');

        $zip->close();
        $bytes = (string) file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $bytes;
    }

    public function pdf(array $data): string
    {
        $m = $data['metrics'] ?? [];
        $period = $data['period'] ?? ['start' => '-', 'end' => '-'];

        $lines = [
            'LAPORAN KEUANGAN KOPERASIKU - KOPEG POLMAN BANDUNG',
            'Periode: '.($period['start'] ?? '-').' s/d '.($period['end'] ?? '-'),
            '------------------------------------------------------------',
            'Omzet Penjualan Selesai         : Rp '.number_format((float) ($m['sales_revenue'] ?? 0), 2, ',', '.'),
            'Penjualan Tunai & Transfer      : Rp '.number_format((float) ($m['cash_sales_revenue'] ?? 0), 2, ',', '.'),
            'Penjualan Piutang Baru          : Rp '.number_format((float) ($m['new_credit_sales'] ?? 0), 2, ',', '.'),
            'HPP Barang Terjual              : Rp '.number_format((float) ($m['cogs'] ?? 0), 2, ',', '.'),
            'Laba Kotor                      : Rp '.number_format((float) ($m['gross_profit'] ?? 0), 2, ',', '.'),
            'Biaya Operasional               : Rp '.number_format((float) ($m['operating_expenses'] ?? 0), 2, ',', '.'),
            'Estimasi Laba Bersih            : Rp '.number_format((float) ($m['estimated_net_profit'] ?? 0), 2, ',', '.'),
            'Kas Masuk Terverifikasi         : Rp '.number_format((float) ($m['cash_receipts'] ?? 0), 2, ',', '.'),
            'Piutang Terbuka (Akhir Periode) : Rp '.number_format((float) ($m['receivables_outstanding_as_of_end'] ?? 0), 2, ',', '.'),
            'Piutang Jatuh Tempo (Overdue)   : Rp '.number_format((float) ($m['overdue_outstanding_as_of_end'] ?? 0), 2, ',', '.'),
        ];

        $stream = "BT\n/F1 11 Tf\n50 760 Td\n16 TL\n";
        foreach ($lines as $line) {
            $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $stream .= "({$safe}) Tj\nT*\n";
        }
        $stream .= "ET\n";

        $objs = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
        $objs[5] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream";

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
}
