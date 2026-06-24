<?php
/**
 * API: Get Products
 * GET /api/products.php
 * Query params: category, shop, type, search, sort, page, limit
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();

$category = $_GET['category'] ?? '';
$shop = $_GET['shop'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';
$discount = $_GET['discount'] ?? '';
$newItems = $_GET['new'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(200, max(1, (int)($_GET['limit'] ?? 12)));

$where = ["p.is_active = 1", "p.show_in_store = 1"];
$params = [];

if ($category) {
    $where[] = "c.slug = ?";
    $params[] = $category;
}
if ($shop) {
    $where[] = "s.slug = ?";
    $params[] = $shop;
}
if ($type) {
    $where[] = "p.type = ?";
    $params[] = $type;
}
if ($discount) {
    $where[] = "p.original_price IS NOT NULL AND p.original_price > p.price";
}
if ($newItems) {
    $where[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}
if ($search) {
    $where[] = "(p.name LIKE ? OR p.name_mn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

$orderBy = match($sort) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular' => 'p.rating DESC, p.reviews DESC',
    'name' => 'p.name ASC',
    default => 'p.created_at DESC',
};

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN shops s ON p.shop_id = s.id 
    WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$offset = ($page - 1) * $limit;

// Fetch
$stmt = $db->prepare("SELECT p.id, p.name, p.name_mn, p.slug, p.type, p.price, p.original_price, 
    p.weight_kg, p.image, p.main_image_id, p.image_ids, p.description, p.description_mn, p.stock, p.has_variants, p.preorder_date, p.order_status, p.rating, p.reviews, p.hide_cargo_fee,
    c.slug as category, c.name as category_name,
    s.slug as shop, s.name as shop_name,
    m.filename as main_image_filename
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN shops s ON p.shop_id = s.id
    LEFT JOIN media m ON p.main_image_id = m.id
    WHERE $whereStr
    ORDER BY (p.order_status = 'closed') ASC, $orderBy
    LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Format products
foreach ($products as &$p) {
    $p['id'] = (int)$p['id'];
    $p['price'] = (float)$p['price'];
    $p['original_price'] = $p['original_price'] ? (float)$p['original_price'] : null;
    $p['weight_kg'] = $p['weight_kg'] ? (float)$p['weight_kg'] : null;
    $p['stock'] = $p['stock'] !== null ? (int)$p['stock'] : null;
    $p['has_variants'] = (int)($p['has_variants'] ?? 0);
    $p['rating'] = (float)$p['rating'];
    $p['reviews'] = (int)$p['reviews'];
    $p['hide_cargo_fee'] = (int)($p['hide_cargo_fee'] ?? 0);

    // Use media image if available, fallback to legacy image
    $imgUrl = null;
    if ($p['main_image_filename']) {
        $imgUrl = getBasePath() . 'backend/uploads/media/' . $p['main_image_filename'];
    } elseif ($p['image'] && !str_starts_with($p['image'], 'http')) {
        $imgUrl = getBasePath() . 'backend/' . $p['image'];
    } else {
        $imgUrl = $p['image'];
    }
    $p['image'] = $imgUrl;
    $p['images'] = [];

    unset($p['main_image_id'], $p['main_image_filename']);
}
unset($p);

// Bulk fetch gallery images (fix N+1)
$allImageIds = [];
$productImageMap = []; // product index => image_ids array
foreach ($products as $idx => &$p) {
    if ($p['image_ids']) {
        $gIds = json_decode($p['image_ids'], true);
        if ($gIds && is_array($gIds)) {
            $productImageMap[$idx] = $gIds;
            foreach ($gIds as $gid) {
                $allImageIds[$gid] = true;
            }
        }
    }
    unset($p['image_ids']);
}
unset($p);

$mediaMap = [];
if ($allImageIds) {
    $uniqueIds = array_keys($allImageIds);
    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
    $mStmt = $db->prepare("SELECT id, filename FROM media WHERE id IN ($placeholders)");
    $mStmt->execute($uniqueIds);
    foreach ($mStmt->fetchAll() as $m) {
        $mediaMap[(int)$m['id']] = getBasePath() . 'backend/uploads/media/' . $m['filename'];
    }
}

foreach ($productImageMap as $idx => $gIds) {
    foreach ($gIds as $gid) {
        if (isset($mediaMap[$gid])) {
            $products[$idx]['images'][] = $mediaMap[$gid];
        }
    }
}

// Bulk fetch variant stock for has_variants products
$variantProductIds = [];
foreach ($products as $idx => $p) {
    if ($p['has_variants']) {
        $variantProductIds[(int)$p['id']] = $idx;
    }
}
if ($variantProductIds) {
    $vpIds = array_keys($variantProductIds);
    $vpPlaceholders = implode(',', array_fill(0, count($vpIds), '?'));
    // SUM returns NULL when every variant has NULL stock — preserve to mean "unlimited".
    $vsStmt = $db->prepare("SELECT product_id, SUM(stock) as total_stock FROM product_variants WHERE product_id IN ($vpPlaceholders) AND is_active = 1 GROUP BY product_id");
    $vsStmt->execute($vpIds);
    foreach ($vsStmt->fetchAll() as $vs) {
        $idx = $variantProductIds[(int)$vs['product_id']];
        $products[$idx]['stock'] = $vs['total_stock'] !== null ? (int)$vs['total_stock'] : null;
    }
}

// Bulk fetch price tiers
$productIdToIdx = [];
foreach ($products as $idx => $p) {
    $productIdToIdx[(int)$p['id']] = $idx;
    $products[$idx]['price_tiers'] = [];
}
if ($productIdToIdx) {
    $pIds = array_keys($productIdToIdx);
    $tPlaceholders = implode(',', array_fill(0, count($pIds), '?'));
    $tStmt = $db->prepare("SELECT product_id, min_qty, unit_price FROM product_price_tiers WHERE product_id IN ($tPlaceholders) ORDER BY product_id, min_qty ASC");
    $tStmt->execute($pIds);
    foreach ($tStmt->fetchAll() as $t) {
        $idx = $productIdToIdx[(int)$t['product_id']];
        $products[$idx]['price_tiers'][] = [
            'min_qty'    => (int)$t['min_qty'],
            'unit_price' => (float)$t['unit_price'],
        ];
    }
}

echo json_encode([
    'products' => $products,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => max(1, ceil($total / $limit)),
]);
