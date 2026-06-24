<?php
/**
 * API: Get Single Product
 * GET /api/product.php?id=1 or ?slug=smart-air-purifier
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();

$id = $_GET['id'] ?? null;
$slug = $_GET['slug'] ?? null;

if (!$id && !$slug) {
    http_response_code(400);
    echo json_encode(['error' => 'Product id or slug required']);
    exit;
}

if ($id) {
    $stmt = $db->prepare("SELECT p.*, c.slug as category, c.name as category_name, s.slug as shop, s.name as shop_name,
        m.filename as main_image_filename
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN shops s ON p.shop_id = s.id
        LEFT JOIN media m ON p.main_image_id = m.id
        WHERE p.id = ? AND p.is_active = 1");
    $stmt->execute([(int)$id]);
} else {
    $stmt = $db->prepare("SELECT p.*, c.slug as category, c.name as category_name, s.slug as shop, s.name as shop_name,
        m.filename as main_image_filename
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN shops s ON p.shop_id = s.id
        LEFT JOIN media m ON p.main_image_id = m.id
        WHERE p.slug = ? AND p.is_active = 1");
    $stmt->execute([$slug]);
}

$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product['id'] = (int)$product['id'];
$product['price'] = (float)$product['price'];
$product['original_price'] = $product['original_price'] ? (float)$product['original_price'] : null;
$product['weight_kg'] = $product['weight_kg'] ? (float)$product['weight_kg'] : null;
$product['stock'] = $product['stock'] !== null ? (int)$product['stock'] : null;
$product['rating'] = (float)$product['rating'];
$product['reviews'] = (int)$product['reviews'];
$product['hide_cargo_fee'] = (int)($product['hide_cargo_fee'] ?? 0);

// Main image
if ($product['main_image_filename']) {
    $product['image'] = getBasePath() . 'backend/uploads/media/' . $product['main_image_filename'];
} elseif ($product['image'] && !str_starts_with($product['image'], 'http')) {
    $product['image'] = getBasePath() . 'backend/' . $product['image'];
}

// Gallery images
$product['images'] = [];
if ($product['image_ids']) {
    $gIds = json_decode($product['image_ids'], true);
    if ($gIds && is_array($gIds)) {
        $gPlaceholders = implode(',', array_fill(0, count($gIds), '?'));
        $gStmt = $db->prepare("SELECT id, filename FROM media WHERE id IN ($gPlaceholders)");
        $gStmt->execute($gIds);
        $gMap = [];
        foreach ($gStmt->fetchAll() as $gm) {
            $gMap[(int)$gm['id']] = getBasePath() . 'backend/uploads/media/' . $gm['filename'];
        }
        foreach ($gIds as $gid) {
            if (isset($gMap[$gid])) $product['images'][] = $gMap[$gid];
        }
    }
}

// Remove internal fields
unset($product['category_id'], $product['shop_id'], $product['is_active'], $product['created_at'], $product['updated_at'],
    $product['main_image_id'], $product['image_ids'], $product['main_image_filename']);

// ── Variants ──
$product['has_variants'] = (int)($product['has_variants'] ?? 0);
$product['variants'] = [];
$product['color_images'] = [];

if ($product['has_variants']) {
    // Fetch active variants with color & size info
    $vStmt = $db->prepare("
        SELECT pv.id, pv.color_id, pv.size_id, pv.sku, pv.price_override, pv.stock,
               pc.name as color_name, pc.name_mn as color_name_mn, pc.hex_code,
               ps.name as size_name
        FROM product_variants pv
        LEFT JOIN product_colors pc ON pv.color_id = pc.id
        LEFT JOIN product_sizes ps ON pv.size_id = ps.id
        WHERE pv.product_id = ? AND pv.is_active = 1
        ORDER BY pc.sort_order, ps.sort_order
    ");
    $vStmt->execute([$product['id']]);
    $variants = $vStmt->fetchAll();

    // Build a color lookup for color_images later
    $colorLookup = [];
    foreach ($variants as &$v) {
        $v['id'] = (int)$v['id'];
        $v['color_id'] = $v['color_id'] ? (int)$v['color_id'] : null;
        $v['size_id'] = $v['size_id'] ? (int)$v['size_id'] : null;
        $v['stock'] = $v['stock'] !== null ? (int)$v['stock'] : null;
        $v['price_override'] = $v['price_override'] !== null ? (float)$v['price_override'] : null;
        // Remap to frontend-expected keys
        $v['color_name'] = $v['color_name_mn'] ?? $v['color_name'];
        $v['color_hex'] = $v['hex_code'] ?? null;
        if ($v['color_id'] && !isset($colorLookup[$v['color_id']])) {
            $colorLookup[$v['color_id']] = ['name' => $v['color_name'], 'hex' => $v['color_hex']];
        }
        unset($v['color_name_mn'], $v['hex_code']);
    }
    unset($v);
    $product['variants'] = $variants;

    // Compute total variant stock — if every variant is NULL (unlimited), product stock stays NULL.
    $variantStocks = array_column($variants, 'stock');
    $hasNumeric = false;
    $sum = 0;
    foreach ($variantStocks as $s) {
        if ($s !== null) { $hasNumeric = true; $sum += (int)$s; }
    }
    $product['stock'] = $hasNumeric ? $sum : null;

    // Fetch color images
    $ciStmt = $db->prepare("
        SELECT pci.color_id, m.filename
        FROM product_color_images pci
        JOIN media m ON pci.media_id = m.id
        WHERE pci.product_id = ?
        ORDER BY pci.color_id, pci.sort_order
    ");
    $ciStmt->execute([$product['id']]);
    $colorImages = [];
    foreach ($ciStmt->fetchAll() as $ci) {
        $cid = (int)$ci['color_id'];
        if (!isset($colorImages[$cid])) $colorImages[$cid] = [];
        $colorImages[$cid][] = getBasePath() . 'backend/uploads/media/' . $ci['filename'];
    }
    // Convert to array of {color_id, color_name, color_hex, images[]}
    $product['color_images'] = [];
    foreach ($colorImages as $colorId => $imgs) {
        $product['color_images'][] = [
            'color_id' => $colorId,
            'color_name' => $colorLookup[$colorId]['name'] ?? '',
            'color_hex' => $colorLookup[$colorId]['hex'] ?? '#ccc',
            'images' => $imgs,
        ];
    }
}

// ── Price tiers ──
$product['price_tiers'] = [];
$tStmt = $db->prepare("SELECT min_qty, unit_price FROM product_price_tiers WHERE product_id = ? ORDER BY min_qty ASC");
$tStmt->execute([$product['id']]);
foreach ($tStmt->fetchAll() as $t) {
    $product['price_tiers'][] = [
        'min_qty'    => (int)$t['min_qty'],
        'unit_price' => (float)$t['unit_price'],
    ];
}

echo json_encode($product);
