<?php
$pageTitle = 'Бүтээгдэхүүн импорт';
$db = getDB();

$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$shops = $db->query("SELECT * FROM shops WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$importResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $shopId = (int)($_POST['shop_id'] ?? 0);

    if (!$categoryId || !$shopId) {
        setFlash('error', 'Ангилал болон Брэнд сонгоно уу.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Файл сонгоно уу.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $file = $_FILES['import_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        setFlash('error', 'Зөвхөн .xlsx, .xls, .csv файл зөвшөөрнө.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        setFlash('error', 'Файлын хэмжээ хэт их байна (макс 5MB).');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $rows = $ext === 'csv' ? parseCSV($file['tmp_name']) : parseXLSX($file['tmp_name']);

    if (empty($rows)) {
        setFlash('error', 'Файл хоосон эсвэл уншиж чадсангүй.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $header = array_map(fn($h) => mb_strtolower(trim($h)), $rows[0]);
    $colMap = detectColumns($header);

    if ($colMap['name'] === null) {
        setFlash('error', 'Нэр (name) багана олдсонгүй. Баганы нэрийг шалгана уу.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $dataRows = array_slice($rows, 1);
    $updateExisting = isset($_POST['update_existing']);

    // ── Pre-load color/size lookups ──
    $colorLookup = [];
    foreach ($db->query("SELECT id, LOWER(name) AS name, LOWER(name_mn) AS name_mn FROM product_colors") as $c) {
        $colorLookup[$c['name']] = (int)$c['id'];
        $colorLookup[$c['name_mn']] = (int)$c['id'];
    }
    $sizeLookup = [];
    foreach ($db->query("SELECT id, LOWER(name) AS name FROM product_sizes") as $s) {
        $sizeLookup[$s['name']] = (int)$s['id'];
    }

    // ── Attribute lookups (slug/name → id), one map per attribute ──
    $attrTables = [
        'activity'   => ['activity_types', 'product_activity_types', 'activity_type_id'],
        'shoe_type'  => ['shoe_types',     'product_shoe_types',     'shoe_type_id'],
        'run_type'   => ['run_types',      'product_run_types',      'run_type_id'],
        'cushioning' => ['cushionings',    'product_cushionings',    'cushioning_id'],
        'gait'       => ['gait_types',     'product_gait_types',     'gait_type_id'],
    ];
    $attrLookups = [];
    foreach ($attrTables as $key => [$tbl, $_p, $_fk]) {
        $attrLookups[$key] = [];
        foreach ($db->query("SELECT id, LOWER(slug) AS slug, LOWER(name) AS name, LOWER(name_mn) AS name_mn FROM `$tbl`") as $r) {
            if ($r['slug'])    $attrLookups[$key][$r['slug']]    = (int)$r['id'];
            if ($r['name'])    $attrLookups[$key][$r['name']]    = (int)$r['id'];
            if ($r['name_mn']) $attrLookups[$key][$r['name_mn']] = (int)$r['id'];
        }
    }
    $unknownAttrs = []; // ['activity' => ['xxx' => true], ...]
    $_allowedGenders = ['men', 'women', 'unisex', 'kids'];

    // ── Group rows by product_key (fall back to barcode, then name) ──
    $groups = []; // [groupKey => ['product' => [...], 'variants' => [[...],...], 'firstLine' => N]]
    $skippedItems = [];
    $unknownColors = [];
    $unknownSizes = [];

    foreach ($dataRows as $rowIdx => $row) {
        $lineNum = $rowIdx + 2;

        $name = getColValue($row, $colMap['name'], '');
        $productKey = getColValue($row, $colMap['product_key'], '');
        $barcode = getColValue($row, $colMap['barcode'], '');

        $groupKey = $productKey !== ''
            ? 'k:' . mb_strtolower($productKey)
            : ($barcode !== '' ? 'b:' . $barcode : ($name !== '' ? 'n:' . mb_strtolower($name) : ''));

        if ($groupKey === '') {
            $skippedItems[] = ['line' => $lineNum, 'name' => '(хоосон)', 'reason' => 'product_key, barcode, name бүгд хоосон'];
            continue;
        }

        $variantColor = getColValue($row, $colMap['variant_color'], '');
        $variantSize  = getColValue($row, $colMap['variant_size'], '');
        $variantSku   = getColValue($row, $colMap['variant_sku'], '');
        $variantPrice = getColValue($row, $colMap['variant_price'], '');
        $variantStock = getColValue($row, $colMap['variant_stock'], '');
        $hasVariantData = ($variantColor !== '' || $variantSize !== '' || $variantSku !== '' || $variantPrice !== '' || $variantStock !== '');

        if (!isset($groups[$groupKey])) {
            // First time we see this product — capture product fields
            if ($name === '') {
                $skippedItems[] = ['line' => $lineNum, 'name' => '(хоосон нэр)', 'reason' => 'Бүлгийн эхний мөрөнд name заавал байх ёстой'];
                continue;
            }
            // Resolve gender + attribute id lists from first row
            $genderRaw = mb_strtolower(getColValue($row, $colMap['gender'], 'unisex'));
            $gender = in_array($genderRaw, $_allowedGenders, true) ? $genderRaw : 'unisex';

            $attrIds = [];
            foreach ($attrTables as $akey => $_cfg) {
                $attrIds[$akey] = [];
                $raw = getColValue($row, $colMap[$akey], '');
                if ($raw === '') continue;
                foreach (preg_split('/[,;|]+/u', $raw) as $tok) {
                    $tok = mb_strtolower(trim($tok));
                    if ($tok === '') continue;
                    if (isset($attrLookups[$akey][$tok])) {
                        $attrIds[$akey][] = $attrLookups[$akey][$tok];
                    } else {
                        $unknownAttrs[$akey][$tok] = true;
                    }
                }
                $attrIds[$akey] = array_values(array_unique($attrIds[$akey]));
            }

            $groups[$groupKey] = [
                'firstLine' => $lineNum,
                'product' => [
                    'name'           => $name,
                    'name_mn'        => getColValue($row, $colMap['name_mn'], $name),
                    'description'    => getColValue($row, $colMap['description'], ''),
                    'description_mn' => getColValue($row, $colMap['description_mn'], ''),
                    'type'           => normaliseType(getColValue($row, $colMap['type'], 'ready')),
                    'price'          => parsePrice(getColValue($row, $colMap['price'], '0')) ?? 0.0,
                    'original_price' => parsePrice(getColValue($row, $colMap['original_price'], '')),
                    'weight_kg'      => parseFloat2(getColValue($row, $colMap['weight'], '')),
                    'barcode'        => $barcode !== '' ? $barcode : null,
                    'stock'          => max(0, (int)getColValue($row, $colMap['stock'], 0)),
                    'show_in_store'  => parseBool(getColValue($row, $colMap['show_in_store'], '0')),
                    'hide_cargo_fee' => parseBool(getColValue($row, $colMap['hide_cargo_fee'], '0')),
                    'order_status'   => normaliseOrderStatus(getColValue($row, $colMap['order_status'], 'open')),
                    'preorder_date'  => normaliseDate(getColValue($row, $colMap['preorder_date'], '')),
                    'gender'         => $gender,
                ],
                'attrIds'  => $attrIds,
                'variants' => [],
            ];
        }

        if ($hasVariantData) {
            // Resolve color/size ids (case-insensitive lookup)
            $colorId = null;
            if ($variantColor !== '') {
                $key = mb_strtolower($variantColor);
                if (isset($colorLookup[$key])) {
                    $colorId = $colorLookup[$key];
                } else {
                    $unknownColors[$variantColor] = true;
                }
            }
            $sizeId = null;
            if ($variantSize !== '') {
                $key = mb_strtolower($variantSize);
                if (isset($sizeLookup[$key])) {
                    $sizeId = $sizeLookup[$key];
                } else {
                    $unknownSizes[$variantSize] = true;
                }
            }

            // Skip variant rows that name an unknown color or size
            if (($variantColor !== '' && $colorId === null) || ($variantSize !== '' && $sizeId === null)) {
                $skippedItems[] = [
                    'line' => $lineNum,
                    'name' => $name ?: ($groups[$groupKey]['product']['name'] ?? ''),
                    'reason' => 'Танигдаагүй өнгө/хэмжээ: ' . trim($variantColor . ' ' . $variantSize),
                ];
                continue;
            }

            $groups[$groupKey]['variants'][] = [
                'color_id'       => $colorId,
                'size_id'        => $sizeId,
                'sku'            => $variantSku !== '' ? $variantSku : null,
                'price_override' => parsePrice($variantPrice),
                'stock'          => max(0, (int)$variantStock),
                'is_active'      => 1,
                'line'           => $lineNum,
            ];
        }
    }

    // ── Apply to DB ──
    $productsImported = 0;
    $productsUpdated = 0;
    $variantsImported = 0;
    $variantsUpdated = 0;

    $db->beginTransaction();
    try {
        foreach ($groups as $groupKey => $group) {
            $p = $group['product'];
            $hasVariants = !empty($group['variants']);

            // Find existing product (by barcode if updateExisting AND barcode set)
            $existing = null;
            if ($updateExisting && $p['barcode']) {
                $stmt = $db->prepare("SELECT id, stock FROM products WHERE barcode = ? LIMIT 1");
                $stmt->execute([$p['barcode']]);
                $existing = $stmt->fetch();
            }

            if ($existing) {
                $productId = (int)$existing['id'];
                $oldStock = $existing['stock'] !== null ? (int)$existing['stock'] : null;
                $newStock = $hasVariants ? 0 : (int)$p['stock'];
                $stmt = $db->prepare("UPDATE products SET
                    name = ?, name_mn = ?, description = ?, description_mn = ?,
                    type = ?, price = ?, original_price = ?, weight_kg = ?,
                    barcode = ?, stock = ?, has_variants = ?,
                    show_in_store = ?, hide_cargo_fee = ?, order_status = ?, preorder_date = ?,
                    gender = ?,
                    updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([
                    $p['name'], $p['name_mn'], $p['description'], $p['description_mn'],
                    $p['type'], $p['price'], $p['original_price'], $p['weight_kg'],
                    $p['barcode'], $newStock, $hasVariants ? 1 : 0,
                    $p['show_in_store'], $p['hide_cargo_fee'], $p['order_status'], $p['preorder_date'],
                    $p['gender'],
                    $productId,
                ]);
                if (!$hasVariants && $oldStock !== null && $oldStock !== $newStock) {
                    $db->prepare("INSERT INTO stock_movements (product_id, variant_id, delta, balance_after, reason, order_id, actor_type, actor_id, note) VALUES (?, NULL, ?, ?, 'import', NULL, 'admin', ?, ?)")
                       ->execute([$productId, $newStock - $oldStock, $newStock, $currentAdmin['id'] ?? null, 'Product import (update)']);
                }
                $productsUpdated++;
            } else {
                $slug = uniqueSlug($db, slugify($p['name']));
                $newStock = $hasVariants ? 0 : (int)$p['stock'];
                $stmt = $db->prepare("INSERT INTO products
                    (name, name_mn, slug, category_id, shop_id, type,
                     price, original_price, weight_kg, barcode,
                     description, description_mn, stock, has_variants,
                     is_active, show_in_store, hide_cargo_fee, order_status, preorder_date, gender)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $p['name'], $p['name_mn'], $slug, $categoryId, $shopId, $p['type'],
                    $p['price'], $p['original_price'], $p['weight_kg'], $p['barcode'],
                    $p['description'], $p['description_mn'], $newStock, $hasVariants ? 1 : 0,
                    $p['show_in_store'], $p['hide_cargo_fee'], $p['order_status'], $p['preorder_date'], $p['gender'],
                ]);
                $productId = (int)$db->lastInsertId();
                if (!$hasVariants && $newStock > 0) {
                    $db->prepare("INSERT INTO stock_movements (product_id, variant_id, delta, balance_after, reason, order_id, actor_type, actor_id, note) VALUES (?, NULL, ?, ?, 'import', NULL, 'admin', ?, ?)")
                       ->execute([$productId, $newStock, $newStock, $currentAdmin['id'] ?? null, 'Product import (new)']);
                }
                $productsImported++;
            }

            // Upsert variants — keyed on (product_id, color_id, size_id) per the UNIQUE index
            if ($hasVariants) {
                $findVar = $db->prepare("SELECT id FROM product_variants
                    WHERE product_id = ?
                      AND ((color_id IS NULL AND ? IS NULL) OR color_id = ?)
                      AND ((size_id IS NULL AND ? IS NULL) OR size_id = ?)
                    LIMIT 1");
                $insVar = $db->prepare("INSERT INTO product_variants
                    (product_id, color_id, size_id, sku, price_override, stock, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $updVar = $db->prepare("UPDATE product_variants
                    SET sku = ?, price_override = ?, stock = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?");

                $smIns = $db->prepare("INSERT INTO stock_movements (product_id, variant_id, delta, balance_after, reason, order_id, actor_type, actor_id, note) VALUES (?, ?, ?, ?, 'import', NULL, 'admin', ?, ?)");
                $varStockLookup = $db->prepare("SELECT stock FROM product_variants WHERE id = ?");
                foreach ($group['variants'] as $v) {
                    $findVar->execute([$productId, $v['color_id'], $v['color_id'], $v['size_id'], $v['size_id']]);
                    $row = $findVar->fetch();
                    if ($row) {
                        $varStockLookup->execute([$row['id']]);
                        $oldVStock = $varStockLookup->fetchColumn();
                        $oldVStock = ($oldVStock === false || $oldVStock === null) ? null : (int)$oldVStock;
                        $updVar->execute([$v['sku'], $v['price_override'], $v['stock'], $v['is_active'], $row['id']]);
                        $newVStock = (int)$v['stock'];
                        if ($oldVStock !== null && $oldVStock !== $newVStock) {
                            $smIns->execute([$productId, $row['id'], $newVStock - $oldVStock, $newVStock, $currentAdmin['id'] ?? null, 'Variant import (update)']);
                        }
                        $variantsUpdated++;
                    } else {
                        $insVar->execute([$productId, $v['color_id'], $v['size_id'], $v['sku'], $v['price_override'], $v['stock'], $v['is_active']]);
                        $newVariantId = (int)$db->lastInsertId();
                        $newVStock = (int)$v['stock'];
                        if ($newVStock > 0) {
                            $smIns->execute([$productId, $newVariantId, $newVStock, $newVStock, $currentAdmin['id'] ?? null, 'Variant import (new)']);
                        }
                        $variantsImported++;
                    }
                }
            }

            // Sync attribute pivots (activity + shoe/run/cushioning/gait).
            // Only touch when the sheet actually named the column, so blank cells
            // don't wipe attributes on existing products.
            foreach ($attrTables as $akey => [$_tbl, $pivot, $fk]) {
                if ($colMap[$akey] === null) continue;
                $ids = $group['attrIds'][$akey] ?? [];
                $db->prepare("DELETE FROM `$pivot` WHERE product_id = ?")->execute([$productId]);
                if ($ids) {
                    $ins = $db->prepare("INSERT IGNORE INTO `$pivot` (product_id, `$fk`) VALUES (?, ?)");
                    foreach ($ids as $vid) $ins->execute([$productId, (int)$vid]);
                }
            }
        }

        $db->commit();

        $importResults = [
            'totalRows'        => count($dataRows),
            'productsImported' => $productsImported,
            'productsUpdated'  => $productsUpdated,
            'variantsImported' => $variantsImported,
            'variantsUpdated'  => $variantsUpdated,
            'skipped'          => count($skippedItems),
            'skippedItems'     => $skippedItems,
            'unknownColors'    => array_keys($unknownColors),
            'unknownSizes'     => array_keys($unknownSizes),
            'unknownAttrs'     => array_map('array_keys', $unknownAttrs),
        ];

        auditLog('product_import', 'products', null, 'admin', $currentAdmin['id'] ?? null, json_encode($importResults));
    } catch (\Exception $e) {
        $db->rollBack();
        setFlash('error', 'Импорт алдаа: ' . $e->getMessage());
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// ════════════════════════════════════════════════════════════════════
//  File parsing
// ════════════════════════════════════════════════════════════════════

function parseCSV(string $filepath): array {
    $rows = [];
    $handle = fopen($filepath, 'r');
    if (!$handle) return [];

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $filtered = array_filter($data, fn($v) => trim((string)$v) !== '');
        if (!empty($filtered) || !empty($rows)) { // keep blank rows after header — caller filters
            $rows[] = $data;
        }
    }
    fclose($handle);
    return $rows;
}

function parseXLSX(string $filepath): array {
    if (!class_exists('ZipArchive')) return [];

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) return [];

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssDoc = new DOMDocument();
        $ssDoc->loadXML($ssXml);
        foreach ($ssDoc->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) $text .= $t->textContent;
            $sharedStrings[] = $text;
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) { $zip->close(); return []; }

    $doc = new DOMDocument();
    $doc->loadXML($sheetXml);
    $rows = [];
    foreach ($doc->getElementsByTagName('row') as $rowNode) {
        $rowData = [];
        foreach ($rowNode->getElementsByTagName('c') as $cell) {
            $ref = $cell->getAttribute('r');
            $colIndex = xlsxColToIndex($ref);
            $type = $cell->getAttribute('t');
            $valueNode = $cell->getElementsByTagName('v')->item(0);
            $value = $valueNode ? $valueNode->textContent : '';

            if ($type === 's' && isset($sharedStrings[(int)$value])) {
                $value = $sharedStrings[(int)$value];
            } elseif ($type === 'inlineStr') {
                $isNode = $cell->getElementsByTagName('is')->item(0);
                if ($isNode) {
                    $tNode = $isNode->getElementsByTagName('t')->item(0);
                    $value = $tNode ? $tNode->textContent : '';
                }
            }

            while (count($rowData) < $colIndex) $rowData[] = '';
            $rowData[$colIndex] = $value;
        }
        $rows[] = $rowData;
    }
    $zip->close();

    // Trim trailing fully-empty rows but preserve interior blanks
    while (!empty($rows) && empty(array_filter(end($rows), fn($v) => trim((string)$v) !== ''))) {
        array_pop($rows);
    }
    return $rows;
}

function xlsxColToIndex(string $ref): int {
    preg_match('/^([A-Z]+)/', $ref, $m);
    $letters = $m[1] ?? 'A';
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

// ════════════════════════════════════════════════════════════════════
//  Column detection
// ════════════════════════════════════════════════════════════════════

function detectColumns(array $header): array {
    $map = [
        'product_key' => null, 'name' => null, 'name_mn' => null,
        'description' => null, 'description_mn' => null,
        'type' => null, 'price' => null, 'original_price' => null,
        'weight' => null, 'barcode' => null, 'stock' => null,
        'show_in_store' => null, 'hide_cargo_fee' => null,
        'order_status' => null, 'preorder_date' => null,
        'gender' => null,
        'activity' => null, 'shoe_type' => null, 'run_type' => null,
        'cushioning' => null, 'gait' => null,
        'variant_color' => null, 'variant_size' => null,
        'variant_sku' => null, 'variant_price' => null, 'variant_stock' => null,
    ];

    $patterns = [
        'product_key'    => ['product_key', 'key', 'group', 'group_key'],
        'name'           => ['name', 'нэр', 'product name', 'product', 'бүтээгдэхүүн', 'item'],
        'name_mn'        => ['name_mn', 'mongolian name'],
        'description'    => ['description', 'desc'],
        'description_mn' => ['description_mn', 'тайлбар', 'тодорхойлолт'],
        'type'           => ['type', 'төрөл'],
        'price'          => ['price', 'үнэ', 'selling price'],
        'original_price' => ['original_price', 'original price', 'хуучин үнэ', 'compare price'],
        'weight'         => ['weight_kg', 'weight', 'жин', 'кг', 'kg'],
        'barcode'        => ['barcode', 'баркод', 'бар код', 'штрих'],
        'stock'          => ['stock', 'тоо', 'үлдэгдэл', 'нөөц', 'qty'],
        'show_in_store'  => ['show_in_store', 'show', 'visible', 'дэлгэц'],
        'hide_cargo_fee' => ['hide_cargo_fee', 'hide_cargo'],
        'order_status'   => ['order_status', 'order status'],
        'preorder_date'  => ['preorder_date', 'preorder date', 'захиалгын огноо'],
        'gender'         => ['gender', 'хүйс'],
        'activity'       => ['activity', 'activity_type', 'activities', 'үйл ажиллагаа'],
        'shoe_type'      => ['shoe_type', 'shoe_types', 'shoe', 'гутлын төрөл'],
        'run_type'       => ['run_type', 'run_types', 'гүйлтийн төрөл'],
        'cushioning'     => ['cushioning', 'cushion', 'зөөлөвч'],
        'gait'           => ['gait', 'gait_type', 'алхаа'],
        'variant_color'  => ['variant_color', 'color', 'өнгө'],
        'variant_size'   => ['variant_size', 'size', 'хэмжээ'],
        'variant_sku'    => ['variant_sku', 'sku'],
        'variant_price'  => ['variant_price', 'price_override'],
        'variant_stock'  => ['variant_stock'],
    ];

    foreach ($header as $colIdx => $colName) {
        foreach ($patterns as $field => $keywords) {
            if ($map[$field] !== null) continue;
            foreach ($keywords as $keyword) {
                if ($colName === $keyword) { $map[$field] = $colIdx; break 2; }
            }
        }
    }
    // Second pass — substring match for anything still unmatched
    foreach ($header as $colIdx => $colName) {
        foreach ($patterns as $field => $keywords) {
            if ($map[$field] !== null) continue;
            foreach ($keywords as $keyword) {
                if (mb_strpos($colName, $keyword) !== false) { $map[$field] = $colIdx; break 2; }
            }
        }
    }

    return $map;
}

// ════════════════════════════════════════════════════════════════════
//  Value parsing helpers
// ════════════════════════════════════════════════════════════════════

function getColValue(array $row, ?int $colIndex, string $default): string {
    if ($colIndex === null || !isset($row[$colIndex])) return $default;
    $val = trim((string)$row[$colIndex]);
    return $val !== '' ? $val : $default;
}

function parsePrice(string $val): ?float {
    $val = trim($val);
    if ($val === '') return null;
    $val = preg_replace('/[₮$€¥\s,\x{20BD}]+/u', '', $val);
    if (!is_numeric($val)) return null;
    $f = (float)$val;
    return $f >= 0 ? $f : null;
}

function parseFloat2(string $val): ?float {
    if ($val === '') return null;
    $val = preg_replace('/[^\d.\-]/', '', $val);
    if (!is_numeric($val)) return null;
    return (float)$val;
}

function parseBool(string $val): int {
    $v = mb_strtolower(trim($val));
    return in_array($v, ['1', 'true', 'yes', 'y', 'тийм'], true) ? 1 : 0;
}

function normaliseType(string $val): string {
    $v = mb_strtolower(trim($val));
    return $v === 'preorder' ? 'preorder' : 'ready';
}

function normaliseOrderStatus(string $val): string {
    $v = mb_strtolower(trim($val));
    return $v === 'closed' ? 'closed' : 'open';
}

function normaliseDate(string $val): ?string {
    $val = trim($val);
    if ($val === '') return null;
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : null;
}

function uniqueSlug(PDO $db, string $base): string {
    $slug = $base;
    $stmt = $db->prepare("SELECT 1 FROM products WHERE slug = ? LIMIT 1");
    $i = 1;
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) return $slug;
        $i++;
        $slug = $base . '-' . $i;
        if ($i > 1000) return $base . '-' . time();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-1 sm:px-0">
    <div class="mb-6">
        <a href="index.php?page=products" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Бүтээгдэхүүн рүү буцах
        </a>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mb-6">📥 Бүтээгдэхүүн импорт (Excel / CSV)</h1>

    <?php if ($importResults): ?>
    <div class="mb-6 p-5 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl">
        <h3 class="text-lg font-bold text-green-800 mb-3">✅ Импорт амжилттай!</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-white rounded-lg p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-gray-800"><?= $importResults['totalRows'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Нийт мөр</div>
            </div>
            <div class="bg-white rounded-lg p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-green-600"><?= $importResults['productsImported'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Шинэ бараа</div>
            </div>
            <div class="bg-white rounded-lg p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-blue-600"><?= $importResults['productsUpdated'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Шинэчилсэн бараа</div>
            </div>
            <div class="bg-white rounded-lg p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-green-600"><?= $importResults['variantsImported'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Шинэ вариант</div>
            </div>
            <div class="bg-white rounded-lg p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-blue-600"><?= $importResults['variantsUpdated'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Шинэчилсэн вариант</div>
            </div>
            <div class="bg-white rounded-lg p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-gray-400"><?= $importResults['skipped'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Алгассан</div>
            </div>
        </div>

        <?php
        $unknownAttrsShow = array_filter($importResults['unknownAttrs'] ?? []);
        $attrLabelsMn = [
            'activity' => 'Үйл ажиллагаа', 'shoe_type' => 'Гутлын төрөл',
            'run_type' => 'Гүйлтийн төрөл', 'cushioning' => 'Зөөлөвч', 'gait' => 'Алхаа',
        ];
        ?>
        <?php if (!empty($importResults['unknownColors']) || !empty($importResults['unknownSizes']) || $unknownAttrsShow): ?>
        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm">
            <p class="font-semibold text-yellow-800 mb-1">⚠️ Танигдаагүй утгууд:</p>
            <?php if (!empty($importResults['unknownColors'])): ?>
                <p class="text-yellow-700">Өнгө: <?= e(implode(', ', $importResults['unknownColors'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($importResults['unknownSizes'])): ?>
                <p class="text-yellow-700">Хэмжээ: <?= e(implode(', ', $importResults['unknownSizes'])) ?></p>
            <?php endif; ?>
            <?php foreach ($unknownAttrsShow as $ak => $vals): ?>
                <p class="text-yellow-700"><?= e($attrLabelsMn[$ak] ?? $ak) ?>: <?= e(implode(', ', $vals)) ?></p>
            <?php endforeach; ?>
            <p class="text-xs text-yellow-600 mt-1">Тохирох хүснэгтэд эдгээрийг урьдчилан нэмнэ үү (slug эсвэл нэрээр).</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($importResults['skippedItems'])): ?>
        <div class="mt-4">
            <button onclick="document.getElementById('skippedList').classList.toggle('hidden')" class="text-sm font-medium text-gray-600 hover:text-gray-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                Алгассан <?= count($importResults['skippedItems']) ?> мөр харах
            </button>
            <div id="skippedList" class="hidden mt-2 bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Мөр</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Нэр</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Шалтгаан</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($importResults['skippedItems'] as $si): ?>
                        <tr>
                            <td class="px-3 py-2 text-gray-400"><?= (int)$si['line'] ?></td>
                            <td class="px-3 py-2 text-gray-900"><?= e($si['name']) ?></td>
                            <td class="px-3 py-2 text-yellow-600"><?= e($si['reason']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Instructions -->
    <div class="mb-6 p-5 bg-blue-50 border border-blue-200 rounded-xl">
        <h3 class="font-semibold text-blue-800 mb-2">📋 Заавар</h3>
        <ul class="text-sm text-blue-700 space-y-1.5">
            <li>• Excel (.xlsx) эсвэл CSV (.csv) файл байршуулна уу</li>
            <li>• Эхний мөр нь <strong>баганы нэр</strong> байх ёстой</li>
            <li>• <strong>name</strong> багана заавал, мөн <strong>product_key</strong> эсвэл <strong>barcode</strong>-оор бараа бүлэглэнэ</li>
            <li>• Нэг бараанд олон вариант байвал ижил <strong>product_key</strong>-тэй мөрүүдийг ар араас нь оруулна</li>
            <li>• Эхний мөрөнд бүтээгдэхүүний мэдээллийг бүтнээр нь, дараагийн мөрүүдэд зөвхөн вариант мэдээллийг бөглөнө</li>
            <li>• Вариант байгаа бол барааны үндсэн <strong>stock</strong> хэрэглэгдэхгүй — вариант бүрийн stock автоматаар нийлбэр болно</li>
            <li>• Өнгө: монгол ("Хар", "Цагаан") эсвэл англи ("Black", "White") нэр зөвшөөрнө</li>
            <li>• Хэмжээ: product_sizes хүснэгтэд бүртгэлтэй утгууд (XS–3XL, 34–45)</li>
            <li>• <strong>gender</strong>: men / women / unisex / kids</li>
            <li>• <strong>activity, shoe_type, run_type, cushioning, gait</strong>: багана бүрт таслалаар тусгаарласан slug эсвэл нэр (жиш: <code>road,trail</code>). Зөвхөн бүлгийн эхний мөрөнд бөглөнө.</li>
            <li>• Хоосон үлдээвэл тухайн шинж чанарыг хуучин утгаас нь <em>устгана</em> — багана өөрөө байхгүй бол хөнддөггүй.</li>
        </ul>
        <div class="mt-3 pt-3 border-t border-blue-200">
            <a href="generate-import-template.php?download=1" class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
                ⬇️ XLSX жишээ файл татах
            </a>
        </div>
    </div>

    <!-- Import Form -->
    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        <?= csrfField() ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ангилал <span class="text-red-500">*</span></label>
                <select name="category_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Сонгох --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == ($_POST['category_id'] ?? '')) ? 'selected' : '' ?>>
                            <?= e($cat['name_mn'] ?: $cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Брэнд <span class="text-red-500">*</span></label>
                <select name="shop_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Сонгох --</option>
                    <?php foreach ($shops as $shop): ?>
                        <option value="<?= $shop['id'] ?>" <?= ($shop['id'] == ($_POST['shop_id'] ?? '')) ? 'selected' : '' ?>>
                            <?= e($shop['name_mn'] ?: $shop['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Excel / CSV файл <span class="text-red-500">*</span></label>
            <div class="relative" x-data="{ fileName: '', dragOver: false }"
                 @dragover.prevent="dragOver = true"
                 @dragleave="dragOver = false"
                 @drop.prevent="dragOver = false; $refs.fileInput.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0]?.name || ''">
                <input type="file" name="import_file" accept=".xlsx,.xls,.csv" required x-ref="fileInput"
                       @change="fileName = $el.files[0]?.name || ''"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                <div class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
                     :class="dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400'">
                    <svg class="mx-auto w-10 h-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <template x-if="!fileName">
                        <div>
                            <p class="text-sm text-gray-600">Файл чирж оруулах эсвэл <span class="text-blue-600 font-medium">сонгох</span></p>
                            <p class="text-xs text-gray-400 mt-1">.xlsx, .csv (макс 5MB)</p>
                        </div>
                    </template>
                    <template x-if="fileName">
                        <div>
                            <p class="text-sm font-medium text-green-700" x-text="'📄 ' + fileName"></p>
                            <p class="text-xs text-gray-400 mt-1">Өөр файл сонгохын тулд дахин дарна уу</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="update_existing" id="update_existing" value="1"
                   class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                   <?= isset($_POST['update_existing']) ? 'checked' : '' ?>>
            <label for="update_existing" class="text-sm text-gray-700">
                Давхцсан бүтээгдэхүүний мэдээллийг шинэчлэх (баркодоор таних)
            </label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-200 transition-all">
                📥 Импорт хийх
            </button>
            <a href="index.php?page=products" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800">Болих</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
