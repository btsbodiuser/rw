<?php
/**
 * API: Product Entry for permitted customers
 * 
 * GET  /api/product-entry.php         — Check if current customer has permission
 * GET  /api/product-entry.php?id=X    — Get product data for editing (owner only)
 * POST /api/product-entry.php         — Create a new product
 * PUT  /api/product-entry.php?id=X    — Update own product
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

$db = getDB();

// ── Auth: require valid customer token ──
$bearerToken = getBearerToken();
if (!$bearerToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$stmt = $db->prepare("
    SELECT c.id, c.phone, c.name FROM customer_sessions s
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
$hasPermission = (bool) $permStmt->fetchColumn();

// ══════════════════════════════════════════════════════════════
//  GET — Check permission  OR  fetch single product for editing
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (!$editId) {
        // Also return open cargo batches for the form
        $batches = [];
        if ($hasPermission) {
            $bStmt = $db->query("SELECT id, name, cargo_rate_per_kg FROM cargo_batches WHERE status = 'open' ORDER BY created_at DESC");
            $batches = $bStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($batches as &$b) {
                $b['id'] = (int) $b['id'];
                $b['cargo_rate_per_kg'] = (float) $b['cargo_rate_per_kg'];
            }
            unset($b);
        }
        echo json_encode(['allowed' => $hasPermission, 'cargo_batches' => $batches]);
        exit;
    }

    // Fetch own product for editing
    if (!$hasPermission) {
        http_response_code(403);
        echo json_encode(['error' => 'No permission']);
        exit;
    }

    $pStmt = $db->prepare("
        SELECT p.id, p.name, p.name_mn, p.category_id, p.shop_id, p.type,
               p.price, p.original_price, p.stock, p.weight_kg,
               p.main_image_id, p.image_ids, p.description_mn, p.preorder_date,
               p.show_in_store, p.order_status, p.cargo_batch_id, p.hide_cargo_fee
        FROM products p
        WHERE p.id = ? AND p.created_by_customer_id = ?
    ");
    $pStmt->execute([$editId, $customerId]);
    $product = $pStmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found or not yours']);
        exit;
    }

    // Resolve main image
    $mainImageData = null;
    if ($product['main_image_id']) {
        $mStmt = $db->prepare("SELECT id, filename, original_name FROM media WHERE id = ?");
        $mStmt->execute([$product['main_image_id']]);
        $mi = $mStmt->fetch();
        if ($mi) {
            $mainImageData = [
                'id' => (int) $mi['id'],
                'filename' => $mi['filename'],
                'original_name' => $mi['original_name'],
            ];
        }
    }

    // Resolve gallery images
    $galleryData = [];
    if ($product['image_ids']) {
        $ids = json_decode($product['image_ids'], true);
        if (is_array($ids) && count($ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $gStmt = $db->prepare("SELECT id, filename, original_name FROM media WHERE id IN ($placeholders)");
            $gStmt->execute($ids);
            foreach ($gStmt->fetchAll() as $gi) {
                $galleryData[] = [
                    'id' => (int) $gi['id'],
                    'filename' => $gi['filename'],
                    'original_name' => $gi['original_name'],
                ];
            }
        }
    }

    echo json_encode([
        'product' => [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'name_mn' => $product['name_mn'],
            'category_id' => (int) $product['category_id'],
            'shop_id' => (int) $product['shop_id'],
            'type' => $product['type'],
            'price' => (float) $product['price'],
            'original_price' => $product['original_price'] ? (float) $product['original_price'] : null,
            'stock' => (int) $product['stock'],
            'weight_kg' => $product['weight_kg'] ? (float) $product['weight_kg'] : null,
            'description_mn' => $product['description_mn'] ?? '',
            'preorder_date' => $product['preorder_date'],
            'show_in_store' => (int) ($product['show_in_store'] ?? 1),
            'order_status' => $product['order_status'] ?? 'open',
            'cargo_batch_id' => $product['cargo_batch_id'] ? (int) $product['cargo_batch_id'] : null,
            'hide_cargo_fee' => (int) ($product['hide_cargo_fee'] ?? 0),
            'main_image' => $mainImageData,
            'gallery_images' => $galleryData,
        ],
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  PUT — Update own product
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!$hasPermission) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to edit products']);
        exit;
    }

    $editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($editId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Product id required']);
        exit;
    }

    // Verify ownership
    $ownerCheck = $db->prepare("SELECT id FROM products WHERE id = ? AND created_by_customer_id = ?");
    $ownerCheck->execute([$editId, $customerId]);
    if (!$ownerCheck->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found or not yours']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Validate (same as create)
    $errors = [];

    $name = trim($input['name'] ?? '');
    $nameMn = trim($input['name_mn'] ?? '');
    if ($nameMn === '') $errors[] = 'Барааны нэр (монгол) оруулна уу';
    if ($name === '') $name = $nameMn;

    $categoryId_in = (int) ($input['category_id'] ?? 0);
    if ($categoryId_in <= 0) $errors[] = 'Ангилал сонгоно уу';

    $shopId_in = (int) ($input['shop_id'] ?? 0);
    if ($shopId_in <= 0) $errors[] = 'Дэлгүүр сонгоно уу';

    $type = $input['type'] ?? 'ready';
    if (!in_array($type, ['ready', 'preorder'])) $errors[] = 'Төрөл буруу байна';

    $price = (float) ($input['price'] ?? 0);
    if ($price <= 0) $errors[] = 'Үнэ 0-с их байх ёстой';

    $originalPrice = !empty($input['original_price']) ? (float) $input['original_price'] : null;
    $stock = (int) ($input['stock'] ?? 0);
    $weightKg = !empty($input['weight_kg']) ? (float) $input['weight_kg'] : null;
    $preorderDate = !empty($input['preorder_date']) ? $input['preorder_date'] : null;
    $descriptionMn = trim($input['description_mn'] ?? '');
    $mainImageId = !empty($input['main_image_id']) ? (int) $input['main_image_id'] : null;
    $imageIds = !empty($input['image_ids']) && is_array($input['image_ids']) ? array_map('intval', $input['image_ids']) : null;
    $showInStore = isset($input['show_in_store']) ? ((int) $input['show_in_store'] ? 1 : 0) : 1;
    $orderStatus = ($input['order_status'] ?? 'open') === 'closed' ? 'closed' : 'open';
    $cargoBatchId = !empty($input['cargo_batch_id']) ? (int) $input['cargo_batch_id'] : null;
    $hideCargoFee = isset($input['hide_cargo_fee']) ? ((int) $input['hide_cargo_fee'] ? 1 : 0) : 0;

    if ($type === 'preorder' && !$weightKg) {
        $errors[] = 'Урьдчилсан захиалгын барааны жин оруулна уу';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    // Verify category and shop exist
    $catCheck = $db->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
    $catCheck->execute([$categoryId_in]);
    if (!$catCheck->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['error' => 'Ангилал олдсонгүй']);
        exit;
    }

    $shopCheck = $db->prepare("SELECT id FROM shops WHERE id = ? AND is_active = 1");
    $shopCheck->execute([$shopId_in]);
    if (!$shopCheck->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['error' => 'Дэлгүүр олдсонгүй']);
        exit;
    }

    // Verify media IDs
    if ($mainImageId) {
        $mediaCheck = $db->prepare("SELECT id FROM media WHERE id = ?");
        $mediaCheck->execute([$mainImageId]);
        if (!$mediaCheck->fetchColumn()) {
            $mainImageId = null;
        }
    }

    // Verify cargo batch (must be open)
    if ($cargoBatchId) {
        $batchCheck = $db->prepare("SELECT id FROM cargo_batches WHERE id = ? AND status = 'open'");
        $batchCheck->execute([$cargoBatchId]);
        if (!$batchCheck->fetchColumn()) {
            $cargoBatchId = null;
        }
    }

    // Update product
    $stmt = $db->prepare("
        UPDATE products SET
            name = ?, name_mn = ?, category_id = ?, shop_id = ?, type = ?,
            price = ?, original_price = ?, stock = ?, weight_kg = ?,
            main_image_id = ?, image_ids = ?,
            description = ?, description_mn = ?,
            preorder_date = ?, show_in_store = ?, order_status = ?, cargo_batch_id = ?, hide_cargo_fee = ?,
            updated_at = NOW()
        WHERE id = ? AND created_by_customer_id = ?
    ");

    $stmt->execute([
        $name,
        $nameMn,
        $categoryId_in,
        $shopId_in,
        $type,
        $price,
        $originalPrice,
        $stock,
        $weightKg,
        $mainImageId,
        $imageIds ? json_encode($imageIds) : null,
        $descriptionMn,
        $descriptionMn,
        $preorderDate,
        $showInStore,
        $orderStatus,
        $cargoBatchId,
        $hideCargoFee,
        $editId,
        $customerId,
    ]);

    auditLog('product_updated', 'product', $editId, 'customer', $customerId, [
        'name' => $name,
        'price' => $price,
        'type' => $type,
    ]);

    $slugStmt = $db->prepare("SELECT slug FROM products WHERE id = ?");
    $slugStmt->execute([$editId]);
    $updatedSlug = $slugStmt->fetchColumn();

    echo json_encode(['success' => true, 'id' => $editId, 'slug' => $updatedSlug]);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  POST — Create product
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!$hasPermission) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to add products']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// ── Validate required fields ──
$errors = [];

$name = trim($input['name'] ?? '');
$nameMn = trim($input['name_mn'] ?? '');
if ($nameMn === '') $errors[] = 'Барааны нэр (монгол) оруулна уу';
if ($name === '') $name = $nameMn;

$categoryId = (int) ($input['category_id'] ?? 0);
if ($categoryId <= 0) $errors[] = 'Ангилал сонгоно уу';

$shopId = (int) ($input['shop_id'] ?? 0);
if ($shopId <= 0) $errors[] = 'Дэлгүүр сонгоно уу';

$type = $input['type'] ?? 'ready';
if (!in_array($type, ['ready', 'preorder'])) $errors[] = 'Төрөл буруу байна';

$price = (float) ($input['price'] ?? 0);
if ($price <= 0) $errors[] = 'Үнэ 0-с их байх ёстой';

$originalPrice = !empty($input['original_price']) ? (float) $input['original_price'] : null;
$stock = (int) ($input['stock'] ?? 0);
$weightKg = !empty($input['weight_kg']) ? (float) $input['weight_kg'] : null;
$preorderDate = !empty($input['preorder_date']) ? $input['preorder_date'] : null;
$descriptionMn = trim($input['description_mn'] ?? '');
$mainImageId = !empty($input['main_image_id']) ? (int) $input['main_image_id'] : null;
$imageIds = !empty($input['image_ids']) && is_array($input['image_ids']) ? array_map('intval', $input['image_ids']) : null;
$showInStore = isset($input['show_in_store']) ? ((int) $input['show_in_store'] ? 1 : 0) : 1;
$orderStatus = ($input['order_status'] ?? 'open') === 'closed' ? 'closed' : 'open';
$cargoBatchId = !empty($input['cargo_batch_id']) ? (int) $input['cargo_batch_id'] : null;
$hideCargoFee = isset($input['hide_cargo_fee']) ? ((int) $input['hide_cargo_fee'] ? 1 : 0) : 0;

if ($type === 'preorder' && !$weightKg) {
    $errors[] = 'Урьдчилсан захиалгын барааны жин оруулна уу';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed', 'errors' => $errors]);
    exit;
}

// ── Verify category and shop exist ──
$catCheck = $db->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
$catCheck->execute([$categoryId]);
if (!$catCheck->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['error' => 'Ангилал олдсонгүй']);
    exit;
}

$shopCheck = $db->prepare("SELECT id FROM shops WHERE id = ? AND is_active = 1");
$shopCheck->execute([$shopId]);
if (!$shopCheck->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['error' => 'Дэлгүүр олдсонгүй']);
    exit;
}

// ── Verify media IDs belong to media table ──
if ($mainImageId) {
    $mediaCheck = $db->prepare("SELECT id FROM media WHERE id = ?");
    $mediaCheck->execute([$mainImageId]);
    if (!$mediaCheck->fetchColumn()) {
        $mainImageId = null;
    }
}

// ── Verify cargo batch (must be open) ──
if ($cargoBatchId) {
    $batchCheck = $db->prepare("SELECT id FROM cargo_batches WHERE id = ? AND status = 'open'");
    $batchCheck->execute([$cargoBatchId]);
    if (!$batchCheck->fetchColumn()) {
        $cargoBatchId = null;
    }
}

// ── Generate unique slug ──
$slug = slugify($name);
if (!$slug) $slug = 'product';

$slugCheck = $db->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
$slugCheck->execute([$slug]);
if ((int) $slugCheck->fetchColumn() > 0) {
    $slug .= '-' . time();
}

// ── Insert product ──
$stmt = $db->prepare("
    INSERT INTO products (
        name, name_mn, slug, category_id, shop_id, type,
        price, original_price, stock, weight_kg,
        main_image_id, image_ids,
        description, description_mn,
        preorder_date, is_active, show_in_store,
        order_status, cargo_batch_id, hide_cargo_fee,
        rating, reviews, created_by_customer_id
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?,
        ?, ?,
        ?, 1, ?,
        ?, ?, ?,
        0, 0, ?
    )
");

$stmt->execute([
    $name,
    $nameMn,
    $slug,
    $categoryId,
    $shopId,
    $type,
    $price,
    $originalPrice,
    $stock,
    $weightKg,
    $mainImageId,
    $imageIds ? json_encode($imageIds) : null,
    $descriptionMn,                             // description (copy of description_mn)
    $descriptionMn,
    $preorderDate,
    $showInStore,
    $orderStatus,
    $cargoBatchId,
    $hideCargoFee,
    $customerId,
]);

$productId = (int) $db->lastInsertId();

auditLog('product_created', 'product', $productId, 'customer', $customerId, [
    'name' => $name,
    'price' => $price,
    'type' => $type,
]);

echo json_encode([
    'success' => true,
    'id' => $productId,
    'slug' => $slug,
]);
