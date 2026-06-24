<?php
/**
 * API: Track Order by Order Number
 * GET /api/order-status.php?order_number=NO12345678
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$orderNumber = trim($_GET['order_number'] ?? '');

if (empty($orderNumber)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order number is required']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT o.order_number, o.order_type, o.fulfillment, o.status,
           o.customer_name, o.customer_phone,
           o.subtotal, o.delivery_fee, o.cargo_fee, o.cargo_fee_paid, o.total,
           o.payment_method, o.payment_status,
           o.created_at, o.updated_at,
           d.name AS district_name, d.name_mn AS district_name_mn,
           cb.name AS batch_name, cb.status AS batch_status
    FROM orders o
    LEFT JOIN districts d ON o.district_id = d.id
    LEFT JOIN cargo_batches cb ON o.cargo_batch_id = cb.id
    WHERE o.order_number = ?
");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$stmt = $db->prepare("
    SELECT oi.product_name, oi.variant_label, oi.product_price, oi.quantity, oi.line_total, oi.weight_kg, oi.cargo_fee,
           oi.cargo_status, oi.cargo_fee_paid, oi.delivery_status, cb.name as batch_name,
           p.image, p.slug, m.filename as main_image_filename
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN media m ON p.main_image_id = m.id
    LEFT JOIN cargo_batches cb ON oi.cargo_batch_id = cb.id
    WHERE oi.order_id = (SELECT id FROM orders WHERE order_number = ?)
");
$stmt->execute([$orderNumber]);
$items = $stmt->fetchAll();

foreach ($items as &$item) {
    $item['product_price'] = (float)$item['product_price'];
    $item['quantity'] = (int)$item['quantity'];
    $item['weight_kg'] = $item['weight_kg'] ? (float)$item['weight_kg'] : null;
    $item['cargo_fee'] = (float)$item['cargo_fee'];
    $item['cargo_fee_paid'] = (int)($item['cargo_fee_paid'] ?? 0);
    $item['delivery_status'] = $item['delivery_status'] ?? 'pending';
    // Build image URL same as products API
    if (!empty($item['main_image_filename'])) {
        $item['image'] = getBasePath() . 'backend/uploads/media/' . $item['main_image_filename'];
    } elseif (!empty($item['image']) && !str_starts_with($item['image'], 'http')) {
        $item['image'] = getBasePath() . 'backend/' . $item['image'];
    }
    unset($item['main_image_filename']);
}

$statusFlow = [];
if ($order['order_type'] === 'online') {
    $hasPreorder = (float)$order['cargo_fee'] > 0;
    if ($hasPreorder) {
        if ($order['fulfillment'] === 'delivery') {
            $statusFlow = ['pending', 'confirmed', 'cargo_shipping', 'cargo_arrived', 'delivering', 'delivered'];
        } else {
            $statusFlow = ['pending', 'confirmed', 'cargo_shipping', 'cargo_arrived', 'ready_pickup', 'picked_up'];
        }
    } else {
        if ($order['fulfillment'] === 'delivery') {
            $statusFlow = ['pending', 'confirmed', 'delivering', 'delivered'];
        } else {
            $statusFlow = ['pending', 'confirmed', 'ready_pickup', 'picked_up'];
        }
    }
}

$statusLabels = [
    'pending' => 'Хүлээгдэж байна',
    'confirmed' => 'Баталгаажсан',
    'cargo_shipping' => 'Солонгосоос тээвэрлэж байна',
    'cargo_arrived' => 'Монголд ирсэн',
    'ready_pickup' => 'Авахад бэлэн',
    'delivering' => 'Хүргэлтэнд гарсан',
    'partially_delivered' => 'Хэсэгчлэн хүргэсэн',
    'delivered' => 'Хүргэгдсэн',
    'picked_up' => 'Авсан',
    'completed' => 'Дууссан',
    'cancelled' => 'Цуцлагдсан',
];

echo json_encode([
    'order' => [
        'order_number' => $order['order_number'],
        'type' => $order['order_type'],
        'fulfillment' => $order['fulfillment'],
        'status' => $order['status'],
        'status_label' => $statusLabels[$order['status']] ?? $order['status'],
        'customer_name' => mb_substr($order['customer_name'], 0, 1) . '***',
        'customer_phone' => '****' . substr($order['customer_phone'], -4),
        'district' => $order['district_name'],
        'district_mn' => $order['district_name_mn'],
        'subtotal' => (float)$order['subtotal'],
        'delivery_fee' => (float)$order['delivery_fee'],
        'cargo_fee' => (float)$order['cargo_fee'],
        'cargo_fee_paid' => (int)$order['cargo_fee_paid'],
        'total' => (float)$order['total'],
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'],
        'batch_name' => $order['batch_name'],
        'batch_status' => $order['batch_status'],
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at'],
        'items' => $items,
        'status_flow' => $statusFlow,
    ],
]);
