<?php

namespace App\Services;

use App\Services\Import\SmartImportMapper;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SimpleXlsxService
{
    public function parseFile(string $filePath, string $extension, string $type = ''): array
    {
        $ext = strtolower(trim($extension));
        if ($ext === 'xlsx') {
            return $this->parseXlsx($filePath, $type);
        }
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->parseCsv($filePath, $type);
        }

        throw new RuntimeException('Format file tidak didukung. Gunakan file .xlsx atau .csv.');
    }

    private function parseCsv(string $filePath, string $type = ''): array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new RuntimeException('Gagal membaca file CSV.');
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);

        $rawGrid = [];
        foreach ($lines as $idx => $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $rawGrid[] = ['row_num' => $idx + 1, 'line' => (string) $line];
        }

        if (count($rawGrid) < 2) {
            throw new RuntimeException('File kosong atau hanya berisi baris header tanpa data.');
        }

        // Deteksi delimiter di beberapa baris awal agar judul tanpa pemisah tidak mengecoh
        $sampleText = implode("\n", array_map(fn ($r) => $r['line'], array_slice($rawGrid, 0, 5)));
        $delimiter = substr_count($sampleText, ';') > substr_count($sampleText, ',') ? ';' : ',';

        $grid = [];
        foreach ($rawGrid as $entry) {
            $vals = array_map(fn ($v) => trim((string) $v), str_getcsv($entry['line'], $delimiter));
            $hasContent = false;
            foreach ($vals as $v) {
                if ($v !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) {
                $grid[] = ['row_num' => $entry['row_num'], 'cells' => $vals];
            }
        }

        return $this->buildStructuredResult($grid, $type);
    }

    private function parseXlsx(string $filePath, string $type = ''): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive tidak aktif di container.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('File .xlsx rusak atau tidak dapat dibuka.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = @simplexml_load_string($sharedXml);
            if ($sx instanceof SimpleXMLElement) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $run) {
                            $text .= (string) ($run->t ?? '');
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
            throw new RuntimeException('Worksheet pertama tidak ditemukan di dalam file .xlsx.');
        }

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml instanceof SimpleXMLElement || !isset($xml->sheetData->row)) {
            throw new RuntimeException('Isi worksheet .xlsx tidak valid.');
        }

        $grid = [];
        $seqRow = 0;
        foreach ($xml->sheetData->row as $row) {
            $seqRow++;
            $excelRowNum = (int) ($row['r'] ?? $seqRow);
            $rowCells = [];
            $nextCol = 0;
            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colLetters = preg_replace('/[^A-Za-z]/', '', $ref);
                if ($colLetters !== '') {
                    $colIdx = $this->columnLettersToIndex($colLetters);
                    $nextCol = $colIdx + 1;
                } else {
                    $colIdx = $nextCol;
                    $nextCol++;
                }

                $val = $this->extractCellValue($cell, $sharedStrings);
                $rowCells[$colIdx] = trim($val);
            }

            $hasContent = false;
            foreach ($rowCells as $v) {
                if ($v !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if ($hasContent) {
                ksort($rowCells);
                $grid[] = ['row_num' => $excelRowNum > 0 ? $excelRowNum : $seqRow, 'cells' => $rowCells];
            }
        }

        if (count($grid) < 2) {
            throw new RuntimeException('File Excel kosong atau hanya berisi header tanpa baris data.');
        }

        return $this->buildStructuredResult($grid, $type);
    }

    /**
     * Auto-detect header row within rows 0..4 and map subsequent data rows cleanly by column index.
     *
     * @param  array<int, array{row_num: int, cells: array<int, string>}>  $grid
     */
    private function buildStructuredResult(array $grid, string $type = ''): array
    {
        if (count($grid) < 2) {
            throw new RuntimeException('File kosong atau hanya berisi baris header tanpa data.');
        }

        $denseForDetection = array_map(function (array $row): array {
            $cells = $row['cells'];
            if (empty($cells)) {
                return [];
            }
            $maxC = max(array_keys($cells));
            $out = [];
            for ($c = 0; $c <= $maxC; $c++) {
                $out[$c] = (string) ($cells[$c] ?? '');
            }

            return $out;
        }, array_slice($grid, 0, 5));

        $headerIdx = SmartImportMapper::detectHeaderRowIndex($denseForDetection, $type);
        if ($headerIdx >= count($grid) - 1) {
            $headerIdx = 0;
        }

        $headerCells = $grid[$headerIdx]['cells'];
        $maxCol = max(array_keys($headerCells));
        foreach (array_slice($grid, $headerIdx + 1) as $row) {
            if (! empty($row['cells'])) {
                $maxCol = max($maxCol, max(array_keys($row['cells'])));
            }
        }

        $colToKey = [];
        $fileHeaders = [];
        $seenKeys = [];
        for ($c = 0; $c <= $maxCol; $c++) {
            $rawLabel = trim((string) ($headerCells[$c] ?? ''));
            $normKey = $this->normalizeHeader($rawLabel);
            if ($normKey === '') {
                continue;
            }
            if (isset($seenKeys[$normKey])) {
                $seenKeys[$normKey]++;
                $normKey .= '_' . $seenKeys[$normKey];
            } else {
                $seenKeys[$normKey] = 1;
            }
            $colToKey[$c] = $normKey;
            $fileHeaders[] = [
                'key' => $normKey,
                'label' => $rawLabel !== '' ? $rawLabel : $normKey,
            ];
        }

        $rows = [];
        for ($r = $headerIdx + 1; $r < count($grid); $r++) {
            $cells = $grid[$r]['cells'];
            $assoc = [];
            $hasValue = false;
            foreach ($colToKey as $c => $key) {
                $val = isset($cells[$c]) ? trim((string) $cells[$c]) : '';
                if ($val !== '') {
                    $hasValue = true;
                }
                $assoc[$key] = $val;
            }
            if ($hasValue) {
                $rows[] = ['row_num' => $grid[$r]['row_num'], 'data' => $assoc];
            }
        }

        if (empty($rows)) {
            throw new RuntimeException('Tidak ada baris data setelah baris header (' . $grid[$headerIdx]['row_num'] . ').');
        }

        return [
            'headers' => array_values(array_map(fn ($h) => $h['key'], $fileHeaders)),
            'file_headers' => $fileHeaders,
            'header_row_num' => $grid[$headerIdx]['row_num'],
            'rows' => $rows,
        ];
    }

    /**
     * Extract text or numeric value from a worksheet cell element, supporting rich inline strings.
     */
    private function extractCellValue(SimpleXMLElement $cell, array $sharedStrings): string
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

    public function createXlsxTempFile(array $headers, array $rows, string $sheetName = 'KoperasiKu'): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kop_xlsx_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file Excel sementara.');
        }

        $safeSheet = $this->escapeXml(mb_substr($sheetName, 0, 30));

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $safeSheet . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>';

        $sheetData = '<sheetData>' . $this->buildXmlRow(1, $headers, true);
        $rowNum = 2;
        foreach ($rows as $row) {
            $sheetData .= $this->buildXmlRow($rowNum++, array_values($row), false);
        }
        $sheetData .= '</sheetData>';

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $sheetData
            . '</worksheet>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
        $zip->close();

        return $tmpPath;
    }

    private function buildXmlRow(int $rowNum, array $values, bool $isHeader): string
    {
        $xml = '<row r="' . $rowNum . '">';
        foreach ($values as $colIdx => $val) {
            $ref = $this->indexToColumnLetters($colIdx) . $rowNum;
            $styleAttr = $isHeader ? ' s="1"' : '';
            if (!$isHeader && (is_int($val) || is_float($val))) {
                $xml .= '<c r="' . $ref . '"' . $styleAttr . '><v>' . $val . '</v></c>';
            } else {
                $escaped = $this->escapeXml((string) ($val ?? ''));
                $xml .= '<c r="' . $ref . '" t="inlineStr"' . $styleAttr . '><is><t>' . $escaped . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        return $xml;
    }

    private function normalizeHeader(string $header): string
    {
        return SmartImportMapper::normalizeHeader($header);
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    private function indexToColumnLetters(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $rem = ($index - 1) % 26;
            $letters = chr(65 + $rem) . $letters;
            $index = intdiv($index - 1, 26);
        }
        return $letters;
    }

    private function escapeXml(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
        return htmlspecialchars((string) $clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}