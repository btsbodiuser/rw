<?php
/**
 * API: Customer Orders
 * GET /api/customer-orders.php — list orders for authenticated customer
 * GET /api/customer-orders.php?order_number=X — get specific order detail
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Auth: require customer token ──
$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT c.id FROM customer_sessions s
    JOIN customers c ON c.id = s.customer_id
    WHERE s.token = ? AND s.expires_at > NOW()
");
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

$customerId = (int)$customer['id'];

// ── PUT: Update fulfillment (pickup → delivery) ──
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderNumber = trim($input['order_number'] ?? '');
    $fulfillment = trim($input['fulfillment'] ?? '');
    
    if (!$orderNumber || !in_array($fulfillment, ['delivery', 'pickup'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    // Only allow changes on early-stage orders
    $stmt = $db->prepare("SELECT id, status, fulfillment FROM orders WHERE order_number = ? AND customer_id = ?");
    $stmt->execute([$orderNumber, $customerId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    if (!in_array($order['status'], ['pending', 'confirmed'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Захиалгын төлөв өөрчлөх боломжгүй']);
        exit;
    }
    
    if ($fulfillment === 'delivery') {
        $districtId = (int)($input['district_id'] ?? 0);
        $khorooId = (int)($input['khoroo_id'] ?? 0);
        $address = trim($input['address'] ?? '');
        
        if (!$districtId || !$khorooId || !$address) {
            http_response_code(400);
            echo json_encode(['error' => 'Хаяг мэдээлэл бүрэн оруулна уу']);
            exit;
        }
        
        $detailAddress = trim($input['detail_address'] ?? '');
        $db->prepare("UPDATE orders SET fulfillment = 'delivery', district_id = ?, khoroo_id = ?, address = ?, detail_address = ? WHERE id = ?")
            ->execute([$districtId, $khorooId, $address, $detailAddress, $order['id']]);
    } else {
        $db->prepare("UPDATE orders SET fulfillment = 'pickup', district_id = NULL, khoroo_id = NULL, address = NULL, detail_address = NULL WHERE id = ?")
            ->execute([$order['id']]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

$orderNumber = trim($_GET['order_number'] ?? '');

// ── Single order detail ──
if ($orderNumber) {
    $stmt = $db->prepare("
        SELECT o.*, d.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name
        FROM orders o
        LEFT JOIN districts d ON o.district_id = d.id
        LEFT JOIN khoroos k ON o.khoroo_id = k.id
        WHERE o.order_number = ? AND o.customer_id = ?
    ");
    $stmt->execute([$orderNumber, $customerId]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    $itemStmt = $db->prepare("
        SELECT oi.product_id, oi.product_name, oi.product_price, oi.quantity, oi.line_total, oi.cargo_fee, oi.cargo_fee_paid, oi.variant_label, oi.delivery_status,
               p.image, p.name_mn, p.slug, m.filename as main_image_filename
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN media m ON p.main_image_id = m.id
        WHERE oi.order_id = ?
    ");
    $itemStmt->execute([$order['id']]);
    $items = $itemStmt->fetchAll();

    echo json_encode([
        'order' => formatOrder($order, $items),
    ]);
    exit;
}

// ── List all customer orders ──
$stmt = $db->prepare("
    SELECT o.*, d.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name
    FROM orders o
    LEFT JOIN districts d ON o.district_id = d.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$customerId]);
$orders = $stmt->fetchAll();

$result = [];
if ($orders) {
    // Bulk fetch all order items (fix N+1)
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $db->prepare("
        SELECT oi.order_id, oi.product_id, oi.product_name, oi.product_price, oi.quantity, oi.line_total, oi.cargo_fee, oi.cargo_fee_paid, oi.variant_label, oi.delivery_status,
               p.image, p.name_mn, p.slug, m.filename as main_image_filename
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN media m ON p.main_image_id = m.id
        WHERE oi.order_id IN ($placeholders)
    ");
    $itemStmt->execute($orderIds);
    $allItems = $itemStmt->fetchAll();

    // Group items by order_id
    $itemsByOrder = [];
    foreach ($allItems as $item) {
        $itemsByOrder[$item['order_id']][] = $item;
    }

    foreach ($orders as $order) {
        $items = $itemsByOrder[$order['id']] ?? [];
        $result[] = formatOrder($order, $items);
    }
}

echo json_encode(['orders' => $result]);

function formatOrder($order, $items) {
    $formattedItems = [];
    foreach ($items as $item) {
        // Build image URL same as products API
        $imgUrl = null;
        if (!empty($item['main_image_filename'])) {
            $imgUrl = getBasePath() . 'backend/uploads/media/' . $item['main_image_filename'];
        } elseif (!empty($item['image']) && !str_starts_with($item['image'], 'http')) {
            $imgUrl = getBasePath() . 'backend/' . $item['image'];
        } else {
            $imgUrl = $item['image'];
        }

        $formattedItems[] = [
            'product_id' => (int)$item['product_id'],
            'product_name' => $item['product_name'],
            'product_name_mn' => $item['name_mn'] ?? $item['product_name'],
            'product_price' => (float)$item['product_price'],
            'quantity' => (int)$item['quantity'],
            'line_total' => $item['line_total'] !== null ? (float)$item['line_total'] : null,
            'cargo_fee' => (float)$item['cargo_fee'],
            'cargo_fee_paid' => (int)($item['cargo_fee_paid'] ?? 0),
            'delivery_status' => $item['delivery_status'] ?? 'pending',
            'variant_label' => $item['variant_label'] ?? null,
            'image' => $imgUrl,
        ];
    }

    return [
        'id' => (int)$order['id'],
        'order_number' => $order['order_number'],
        'status' => $order['status'],
        'fulfillment' => $order['fulfillment'],
        'customer_name' => $order['customer_name'],
        'customer_phone' => $order['customer_phone'],
        'district_name' => $order['district_name'] ?? '',
        'khoroo_number' => $order['khoroo_number'] ?? '',
        'khoroo_name' => $order['khoroo_name'] ?? '',
        'address' => $order['address'] ?? '',
        'detail_address' => $order['detail_address'] ?? '',
        'subtotal' => (float)$order['subtotal'],
        'delivery_fee' => (float)$order['delivery_fee'],
        'cargo_fee' => (float)$order['cargo_fee'],
        'cargo_fee_paid' => (int)($order['cargo_fee_paid'] ?? 0),
        'total' => (float)$order['total'],
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'],
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at'],
        'items' => $formattedItems,
    ];
}
