<?php
/**
 * Runners World — Generate product-import .xlsx template
 *
 * Run once to produce a fresh `product-import-template.xlsx` next to this file.
 *   CLI:     php backend/generate-import-template.php
 *   Browser: https://yourdomain.com/backend/generate-import-template.php?download=1
 *
 * Schema (matches install.php + migration 032 product_variants):
 *   - products             (one row per product)
 *   - product_variants     (color/size combos; FK product_id)
 *   - product_colors       (lookup by name OR name_mn — both accepted)
 *   - product_sizes        (lookup by name)
 *
 * Single-sheet layout. One row per SKU:
 *   - Product without variants → one row, leave variant_* columns blank,
 *     use the row's `stock` for the product.
 *   - Product with variants → one row per variant. Repeat the same
 *     `product_key` so the importer groups them. The product-level fields
 *     (name, price, weight, etc.) come from the FIRST row of each group;
 *     subsequent rows can leave them blank or repeat them. The product's
 *     `stock` column is ignored when variants exist (stock lives on the
 *     variant rows instead).
 */

$headers = [
    'product_key',          // your join key — repeat across rows of same product
    'name',                 // required (used on the FIRST row of each group)
    'name_mn',              // defaults to name if blank
    'description',
    'description_mn',
    'type',                 // 'ready' or 'preorder'
    'price',                // base selling price (required on first row)
    'original_price',       // strikethrough price (optional)
    'weight_kg',
    'barcode',              // product-level barcode (optional)
    'stock',                // used only when variant_* columns are blank
    'show_in_store',        // 0 hidden in storefront, 1 visible (default 0)
    'hide_cargo_fee',       // 0 or 1
    'order_status',         // 'open' or 'closed'
    'preorder_date',        // YYYY-MM-DD, only for type=preorder
    'variant_color',        // matches product_colors.name OR name_mn
    'variant_size',         // matches product_sizes.name
    'variant_sku',          // optional
    'variant_price',        // optional override of product price
    'variant_stock',        // required if any variant_* on this row is filled
];

// Sample rows demonstrate the three patterns:
//   1. Product without variants     → single row, variant_* blank
//   2. Product with size variants   → multiple rows, variant_size set
//   3. Product with color+size      → multiple rows, both set
$rows = [
    $headers,

    // No variants — Cookie Box
    ['WH-001','Cookie Box','Күүки хайрцаг','12 pcs','12шт','ready',12000,15000,0.300,'WH-0001',50,1,0,'open','',  '','','','',''],

    // No variants — Notebook
    ['WH-004','Notebook A5','А5 дэвтэр','80 pages','80хууд','ready',4500,'',0.250,'WH-0004',200,1,0,'open','',   '','','','',''],

    // Size-only variants — T-Shirt Basic
    // First row carries the product fields; subsequent rows repeat product_key only
    ['WH-002','T-Shirt Basic','Энгийн майк','Cotton','Хөвөн','ready',35000,'',0.250,'WH-0002','',1,0,'open','',  '','S', 'WH-0002-S', '',     20],
    ['WH-002','','','','','','','','','','','','','','',                                                          '','M', 'WH-0002-M', '',     45],
    ['WH-002','','','','','','','','','','','','','','',                                                          '','L', 'WH-0002-L', '',     30],
    ['WH-002','','','','','','','','','','','','','','',                                                          '','XL','WH-0002-XL',37000, 12],

    // Color + Size variants — Sneakers Air
    ['WH-003','Sneakers Air','Гутал Эйр','Sport','Спорт','ready',180000,220000,0.900,'WH-0003','',1,0,'open','', 'Хар','40','WH-0003-BK40','',8],
    ['WH-003','','','','','','','','','','','','','','',                                                          'Хар','41','WH-0003-BK41','',10],
    ['WH-003','','','','','','','','','','','','','','',                                                          'Хар','42','WH-0003-BK42','',7],
    ['WH-003','','','','','','','','','','','','','','',                                                          'Цагаан','40','WH-0003-WH40','',5],
    ['WH-003','','','','','','','','','','','','','','',                                                          'Цагаан','41','WH-0003-WH41','',6],
    ['WH-003','','','','','','','','','','','','','','',                                                          'Улаан','42','WH-0003-RD42',195000,3],

    // Color-only preorder — Winter Coat
    ['WH-005','Winter Coat','Өвлийн пальто','Preorder','Захиал','preorder',420000,'',1.500,'WH-0005','',1,0,'open','2026-09-15','Хар','','WH-0005-BK','',0],
    ['WH-005','','','','','','','','','','','','','','',                                                                          'Хар хөх','','WH-0005-NV','',0],
    ['WH-005','','','','','','','','','','','','','','',                                                                          'Бор','','WH-0005-BR','',0],
];

$xlsx = buildXlsx(['Products' => $rows]);

$outPath = __DIR__ . '/product-import-template.xlsx';
file_put_contents($outPath, $xlsx);

if (php_sapi_name() === 'cli') {
    echo "Wrote " . strlen($xlsx) . " bytes → $outPath\n";
} else {
    if (isset($_GET['download'])) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="product-import-template.xlsx"');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
        exit;
    }
    echo "<pre>Wrote " . strlen($xlsx) . " bytes to product-import-template.xlsx\n";
    echo "Append ?download=1 to download.</pre>";
}

// ════════════════════════════════════════════════════════════════
//  Minimal XLSX writer — no external dependencies
// ════════════════════════════════════════════════════════════════
function buildXlsx(array $sheets): string {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension required');
    }

    $stringIndex = [];
    foreach ($sheets as $rows) {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (is_string($cell) && $cell !== '' && !isset($stringIndex[$cell])) {
                    $stringIndex[$cell] = count($stringIndex);
                }
            }
        }
    }

    $sheetXmls = [];
    $sheetNum = 0;
    foreach ($sheets as $name => $rows) {
        $sheetNum++;
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';
        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($row as $cIdx => $cell) {
                if ($cell === '' || $cell === null) continue;
                $ref = colLetter($cIdx) . $rowNum;
                $styleAttr = $rIdx === 0 ? ' s="1"' : '';
                if (is_int($cell) || is_float($cell)) {
                    $xml .= '<c r="' . $ref . '"' . $styleAttr . '><v>' . $cell . '</v></c>';
                } else {
                    $sIdx = $stringIndex[$cell];
                    $xml .= '<c r="' . $ref . '" t="s"' . $styleAttr . '><v>' . $sIdx . '</v></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';
        $sheetXmls[$sheetNum] = ['name' => $name, 'xml' => $xml];
    }

    $ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($stringIndex) . '" uniqueCount="' . count($stringIndex) . '">';
    foreach ($stringIndex as $str => $_) {
        $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
    }
    $ssXml .= '</sst>';

    $wbXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $wbXml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $wbXml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $wbXml .= '<sheets>';
    foreach ($sheetXmls as $i => $s) {
        $wbXml .= '<sheet name="' . htmlspecialchars($s['name'], ENT_XML1 | ENT_QUOTES) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
    }
    $wbXml .= '</sheets></workbook>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        . '<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs>'
        . '</styleSheet>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $wbRels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach ($sheetXmls as $i => $_) {
        $wbRels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }
    $ssRid = count($sheetXmls) + 1;
    $stRid = count($sheetXmls) + 2;
    $wbRels .= '<Relationship Id="rId' . $ssRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
    $wbRels .= '<Relationship Id="rId' . $stRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $wbRels .= '</Relationships>';

    $contentTypes  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
    $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    foreach ($sheetXmls as $i => $_) {
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $contentTypes .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
    $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $contentTypes .= '</Types>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',         $contentTypes);
    $zip->addFromString('_rels/.rels',                 $rootRels);
    $zip->addFromString('xl/workbook.xml',             $wbXml);
    $zip->addFromString('xl/styles.xml',               $stylesXml);
    $zip->addFromString('xl/sharedStrings.xml',        $ssXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels',  $wbRels);
    foreach ($sheetXmls as $i => $s) {
        $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $s['xml']);
    }
    $zip->close();

    $bytes = file_get_contents($tmp);
    @unlink($tmp);
    return $bytes;
}

function colLetter(int $idx): string {
    $letters = '';
    $idx++;
    while ($idx > 0) {
        $rem = ($idx - 1) % 26;
        $letters = chr(65 + $rem) . $letters;
        $idx = intdiv($idx - 1, 26);
    }
    return $letters;
}
