<?php
/**
 * Lightweight XLSX reader using built-in ZipArchive + SimpleXML.
 * No Composer / PhpSpreadsheet required.
 * 
 * Returns rows as arrays of strings (same format as str_getcsv output).
 */

function readXlsxFile(string $filePath): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive PHP extension is required to read Excel files.');
    }

    $zip = new ZipArchive();
    $res = $zip->open($filePath);
    if ($res !== true) {
        throw new RuntimeException('Файлыг нээхэд алдаа гарлаа. Зөв .xlsx файл эсэхийг шалгана уу.');
    }

    // ── Read shared strings ──
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = new SimpleXMLElement($ssXml);
        foreach ($ss->si as $si) {
            // Handle both simple <t> and rich text <r><t>
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $run) {
                    $text .= (string)$run->t;
                }
            }
            $sharedStrings[] = $text;
        }
    }

    // ── Read first worksheet ──
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        throw new RuntimeException('Worksheet олдсонгүй.');
    }

    $sheet = new SimpleXMLElement($sheetXml);
    $rows = [];

    if (!isset($sheet->sheetData->row)) {
        $zip->close();
        return $rows;
    }

    // ── Read styles to detect date formats ──
    $dateFormatCells = [];
    $stylesXml = $zip->getFromName('xl/styles.xml');
    if ($stylesXml !== false) {
        $styles = new SimpleXMLElement($stylesXml);
        // Collect number format IDs that are date formats
        $dateFormatIds = [14, 15, 16, 17, 18, 19, 20, 21, 22];
        // Collect custom date formats
        if (isset($styles->numFmts->numFmt)) {
            foreach ($styles->numFmts->numFmt as $fmt) {
                $code = (string)$fmt['formatCode'];
                if (preg_match('/[ymdh]/i', $code) && !preg_match('/#|0\.0/', $code)) {
                    $dateFormatIds[] = (int)$fmt['numFmtId'];
                }
            }
        }
        // Map cell style index -> isDate
        if (isset($styles->cellXfs->xf)) {
            foreach ($styles->cellXfs->xf as $idx => $xf) {
                $numFmtId = (int)($xf['numFmtId'] ?? 0);
                if (in_array($numFmtId, $dateFormatIds)) {
                    $dateFormatCells[$idx] = true;
                }
            }
        }
    }

    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $maxCol = 0;

        foreach ($row->c as $cell) {
            $ref = (string)$cell['r']; // e.g. "A1", "B1"
            $colIndex = columnToIndex($ref);
            $maxCol = max($maxCol, $colIndex);

            $type = (string)($cell['t'] ?? '');
            $styleIdx = (int)($cell['s'] ?? 0);
            $value = '';

            if ($type === 's') {
                // Shared string
                $idx = (int)(string)$cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)($cell->is->t ?? '');
            } elseif ($type === 'b') {
                $value = ((string)$cell->v === '1') ? 'TRUE' : 'FALSE';
            } else {
                $rawVal = (string)($cell->v ?? '');
                // Check if this is a date-formatted cell
                if ($rawVal !== '' && isset($dateFormatCells[$styleIdx])) {
                    $value = excelDateToString((float)$rawVal);
                } else {
                    $value = $rawVal;
                }
            }

            // Fill gaps with empty strings
            while (count($rowData) < $colIndex) {
                $rowData[] = '';
            }
            $rowData[$colIndex] = $value;
        }

        $rows[] = $rowData;
    }

    $zip->close();
    return $rows;
}

/**
 * Convert Excel column reference (e.g. "A1", "AB12") to 0-based column index.
 */
function columnToIndex(string $cellRef): int {
    preg_match('/^([A-Z]+)/', strtoupper($cellRef), $m);
    $letters = $m[1] ?? 'A';
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

/**
 * Convert Excel serial date number to Y-m-d H:i:s string.
 */
function excelDateToString(float $serial): string {
    if ($serial < 1) return '';
    // Excel epoch: 1900-01-01, but Excel treats 1900 as leap year (bug compatibility)
    $unixBase = mktime(0, 0, 0, 1, 1, 1900);
    $days = (int)$serial - 2; // -1 for epoch, -1 for Excel's 1900 leap year bug
    $fraction = $serial - (int)$serial;
    $timestamp = $unixBase + ($days * 86400) + (int)round($fraction * 86400);
    
    if ($fraction > 0.0001) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    return date('Y-m-d', $timestamp);
}
