<?php
/**
 * Driver Deliveries API - Token-based, no auth required
 * 
 * GET  ?token=xxx           → Get driver info + active deliveries
 * POST ?token=xxx&action=update_status  → Update delivery status
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = getDB();
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token || strlen($token) < 2 || strlen($token) > 64) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Find driver by token
$stmt = $db->prepare("SELECT id, name, phone FROM delivery_drivers WHERE access_token = ? AND is_active = 1");
$stmt->execute([$token]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    http_response_code(401);
    echo json_encode(['error' => 'Driver not found']);
    exit;
}

// ── POST: Update delivery status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'update_status') {
        $deliveryId = (int)($input['delivery_id'] ?? 0);
        $newStatus = $input['new_status'] ?? '';
        $allowed = ['picked_up', 'delivered', 'failed'];

        if (!$deliveryId || !in_array($newStatus, $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }

        // Verify this delivery belongs to this driver
        $check = $db->prepare("SELECT id, order_id, batch_id, status FROM deliveries WHERE id = ? AND driver_id = ?");
        $check->execute([$deliveryId, $driver['id']]);
        $delivery = $check->fetch(PDO::FETCH_ASSOC);

        if (!$delivery) {
            http_response_code(403);
            echo json_encode(['error' => 'Delivery not found']);
            exit;
        }

        // Validate transition
        $validTransitions = [
            'assigned' => ['picked_up'],
            'picked_up' => ['delivered', 'failed'],
        ];
        if (!isset($validTransitions[$delivery['status']]) || !in_array($newStatus, $validTransitions[$delivery['status']])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status transition']);
            exit;
        }

        $updates = ['status = ?'];
        $params = [$newStatus];

        if ($newStatus === 'picked_up') {
            $updates[] = 'picked_up_at = NOW()';
        } elseif ($newStatus === 'delivered') {
            $updates[] = 'delivered_at = NOW()';
            $db->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$delivery['order_id']]);
        }

        $params[] = $deliveryId;
        $db->prepare("UPDATE deliveries SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

        // Auto-complete batch if all done
        if ($delivery['batch_id']) {
            $remaining = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE batch_id = ? AND status IN ('assigned','picked_up')");
            $remaining->execute([$delivery['batch_id']]);
            if ($remaining->fetchColumn() == 0) {
                $db->prepare("UPDATE delivery_batches SET status = 'completed', completed_at = NOW() WHERE id = ? AND status != 'completed'")->execute([$delivery['batch_id']]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Status updated']);
        exit;
    }

    if ($action === 'update_batch_status') {
        $batchId = (int)($input['batch_id'] ?? 0);
        $newStatus = $input['new_status'] ?? '';

        if (!$batchId || !in_array($newStatus, ['in_progress'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }

        // Verify batch belongs to driver
        $check = $db->prepare("SELECT id, status FROM delivery_batches WHERE id = ? AND driver_id = ?");
        $check->execute([$batchId, $driver['id']]);
        $batch = $check->fetch(PDO::FETCH_ASSOC);

        if (!$batch || $batch['status'] !== 'assigned') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid batch']);
            exit;
        }

        $db->prepare("UPDATE delivery_batches SET status = 'in_progress' WHERE id = ?")->execute([$batchId]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ── GET: Driver info + active deliveries ──

// Active batches
$batches = $db->prepare("SELECT b.id, b.status, b.notes, b.created_at,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id AND (status != 'failed' OR notes NOT LIKE '%Хуваарилалт цуцлагдсан%')) as total_orders,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id AND status = 'delivered') as delivered_count,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id AND status = 'failed' AND notes NOT LIKE '%Хуваарилалт цуцлагдсан%') as failed_count
    FROM delivery_batches b
    WHERE b.driver_id = ? AND b.status IN ('assigned', 'in_progress')
    ORDER BY b.created_at DESC");
$batches->execute([$driver['id']]);
$activeBatches = $batches->fetchAll(PDO::FETCH_ASSOC);

// Active deliveries grouped by batch
$deliveries = $db->prepare("SELECT d.id, d.batch_id, d.status, d.assigned_at, d.picked_up_at, d.delivered_at, d.notes,
    o.order_number, o.customer_name, o.customer_phone, o.total, o.payment_status, o.delivery_fee,
    dist.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name,
    o.address, o.detail_address,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE d.driver_id = ? AND d.status IN ('assigned', 'picked_up')
    ORDER BY d.batch_id DESC, FIELD(d.status, 'picked_up', 'assigned'), d.id ASC");
$deliveries->execute([$driver['id']]);
$activeDeliveries = $deliveries->fetchAll(PDO::FETCH_ASSOC);

// Load products for active deliveries
$activeOrderIds = array_unique(array_column($activeDeliveries, 'order_id'));
$deliveryProducts = [];
if (!empty($activeOrderIds)) {
    // Map delivery to order_id
    $delOrderMap = [];
    foreach ($activeDeliveries as $ad) {
        $delOrderMap[$ad['id']] = $ad['order_id'] ?? null;
    }
    // We need order_id on deliveries - refetch with order_id
    $orderIdPlaceholders = implode(',', array_fill(0, count($activeOrderIds), '?'));
    $itemsStmt = $db->prepare("SELECT oi.order_id, oi.product_name, oi.quantity, oi.product_price
        FROM order_items oi WHERE oi.order_id IN ($orderIdPlaceholders) ORDER BY oi.id");
    $itemsStmt->execute($activeOrderIds);
    foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $deliveryProducts[$item['order_id']][] = $item;
    }
}

// Attach products to deliveries and get order_id
$deliveriesResult = [];
// Re-query to also get order_id for product mapping
$deliveries2 = $db->prepare("SELECT d.id, d.order_id, d.batch_id, d.status, d.assigned_at, d.picked_up_at, d.delivered_at, d.notes,
    o.order_number, o.customer_name, o.customer_phone, o.total, o.payment_status, o.delivery_fee,
    dist.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name,
    o.address, o.detail_address,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE d.driver_id = ? AND d.status IN ('assigned', 'picked_up')
    ORDER BY d.batch_id DESC, FIELD(d.status, 'picked_up', 'assigned'), d.id ASC");
$deliveries2->execute([$driver['id']]);
foreach ($deliveries2->fetchAll(PDO::FETCH_ASSOC) as $del) {
    $del['products'] = $deliveryProducts[$del['order_id']] ?? [];
    $deliveriesResult[] = $del;
}

// Today's completed
$completed = $db->prepare("SELECT d.id, d.batch_id, d.status, d.delivered_at,
    o.order_number, o.customer_name, o.total, o.payment_status,
    dist.name_mn as district_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN districts dist ON o.district_id = dist.id
    WHERE d.driver_id = ? AND d.status IN ('delivered', 'failed') AND DATE(d.delivered_at) = CURDATE()
    ORDER BY d.delivered_at DESC");
$completed->execute([$driver['id']]);
$completedToday = $completed->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'driver' => $driver,
    'batches' => $activeBatches,
    'deliveries' => $deliveriesResult,
    'completed_today' => $completedToday,
], JSON_UNESCAPED_UNICODE);
