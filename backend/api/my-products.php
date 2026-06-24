<?php
/**
 * API: My Products for permitted customers
 * 
 * GET /api/my-products.php — List products created by the current customer
 * 
 * Header: Authorization: Bearer <token>
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDB();

// ── Auth ──
$bearerToken = getBearerToken();
if (!$bearerToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$stmt = $db->prepare("
    SELECT c.id FROM customer_sessions s
    JOIN customers c ON c.id = s.customer_id
    WHERE s.token = ? AND s.expires_at > NOW()
");
$stmt->execute([$bearerToken]);
$customer = $stmt->fetch();

if (!$customer) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

$customerId = (int) $customer['id'];

// ── Check permission ──
$permStmt = $db->prepare("SELECT 1 FROM product_entry_permissions WHERE customer_id = ?");
$permStmt->execute([$customerId]);
if (!$permStmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'No permission']);
    exit;
}

// ── Fetch products ──
$stmt = $db->prepare("
    SELECT p.*, c.slug AS category_slug, c.name AS category_name, c.name_mn AS category_name_mn,
           s.slug AS shop_slug, s.name AS shop_name, s.name_mn AS shop_name_mn,
           m.filename AS main_image_filename
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN shops s ON s.id = p.shop_id
    LEFT JOIN media m ON m.id = p.main_image_id
    WHERE p.created_by_customer_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$customerId]);
$products = $stmt->fetchAll();

// Build response
$result = [];
foreach ($products as $p) {
    $imageUrl = null;
    if ($p['main_image_filename']) {
        $imageUrl = '/backend/uploads/media/' . $p['main_image_filename'];
    } elseif ($p['image']) {
        $imageUrl = $p['image'];
    }

    // Gallery images
    $galleryImages = [];
    $imageIdsRaw = $p['image_ids'] ?? null;
    if ($imageIdsRaw) {
        $ids = json_decode($imageIdsRaw, true);
        if (is_array($ids) && count($ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $gStmt = $db->prepare("SELECT filename FROM media WHERE id IN ($placeholders)");
            $gStmt->execute($ids);
            foreach ($gStmt->fetchAll() as $gImg) {
                $galleryImages[] = '/backend/uploads/media/' . $gImg['filename'];
            }
        }
    }

    $result[] = [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'name_mn' => $p['name_mn'],
        'slug' => $p['slug'],
        'type' => $p['type'],
        'price' => (float) $p['price'],
        'original_price' => $p['original_price'] ? (float) $p['original_price'] : null,
        'stock' => (int) $p['stock'],
        'is_active' => (int) $p['is_active'],
        'image' => $imageUrl,
        'images' => $galleryImages,
        'category' => $p['category_slug'],
        'category_name' => $p['category_name_mn'] ?: $p['category_name'],
        'shop' => $p['shop_slug'],
        'shop_name' => $p['shop_name_mn'] ?: $p['shop_name'],
        'created_at' => $p['created_at'],
    ];
}

echo json_encode(['products' => $result]);
