<?php
/**
 * Webhook / Payment E2E Test Utility (admin-only)
 *
 * Auth: send header  X-Admin-Token: <MASTER_PASSWORD>
 *
 * POST /backend/api/webhook-test.php?action=verify-config
 *   → configuration status for QPay / Bonum / StorePay / site_url
 *
 * POST /backend/api/webhook-test.php?action=test-order
 *   Body: { "amount": 1000, "payment_method": "qpay|bonum|storepay" }
 *   → creates a pending TEST- order using a real product + district.
 *     Open the returned thanks_url in a browser to run the REAL invoice
 *     creation flow (order-thanks.php JS calls qpay/bonum/storepay.php).
 *
 * POST /backend/api/webhook-test.php?action=ping-callback
 *   Body: { "order_number": "TEST-...", "provider": "qpay|bonum|storepay" }
 *   → calls the real callback endpoint server-side, returns its response
 *     (proves the webhook URL is reachable and wired to the order).
 *
 * POST /backend/api/webhook-test.php?action=simulate-paid
 *   Body: { "order_number": "TEST-..." }
 *   → marks a TEST- order paid with the same side effects as a real
 *     webhook (cargo fees, auto-confirm). Refuses non-TEST orders.
 *
 * POST /backend/api/webhook-test.php?action=cleanup
 *   → deletes all TEST- orders and their items.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/error-logger.php';

$adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
if (!hash_equals(MASTER_PASSWORD, $adminToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$db     = getDB();

$siteUrl = rtrim(getSetting('site_url', ''), '/');
if (!$siteUrl) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(getBasePath(), '/');
}

switch ($action) {

// ── verify-config ────────────────────────────────────────────
case 'verify-config':
    $set = fn(string $k) => getSetting($k) !== '' && getSetting($k) !== null;
    echo json_encode([
        'status' => 'ok',
        'config' => [
            'qpay' => [
                'enabled'      => getSetting('payment_qpay_enabled') === '1',
                'username'     => $set('qpay_username'),
                'password'     => $set('qpay_password'),
                'invoice_code' => $set('qpay_invoice_code'),
                'callback_url' => $siteUrl . '/backend/api/qpay-callback.php?order={orderNumber}',
            ],
            'bonum' => [
                'enabled'      => getSetting('payment_bonum_enabled') === '1',
                'terminal_id'  => $set('bonum_terminal_id'),
                'secret_key'   => $set('bonum_secret_key'),
                'checksum_key' => $set('bonum_checksum_key'),
                'callback_url' => $siteUrl . '/backend/api/bonum-callback.php?order={orderNumber}',
            ],
            'storepay' => [
                'enabled'      => getSetting('payment_storepay_enabled') === '1',
                'store_id'     => $set('storepay_store_id'),
                'username'     => $set('storepay_username'),
                'password'     => $set('storepay_password'),
                'app_username' => $set('storepay_app_username'),
                'app_password' => $set('storepay_app_password'),
                'callback_url' => $siteUrl . '/backend/api/storepay-callback.php?order={orderNumber}&id={loanId}',
            ],
            'general' => [
                'site_url'        => getSetting('site_url') ?: '(not set — using host header)',
                'cron_secret_key' => $set('cron_secret_key'),
            ],
        ],
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

// ── test-order ───────────────────────────────────────────────
case 'test-order':
    $amount        = (float)($input['amount'] ?? 1000);
    $paymentMethod = trim($input['payment_method'] ?? 'qpay');

    if ($amount <= 0 || !in_array($paymentMethod, ['qpay', 'bonum', 'storepay'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'amount > 0 and payment_method (qpay|bonum|storepay) required']);
        exit;
    }

    // Use real FK targets so inserts don't violate constraints
    $product  = $db->query("SELECT id, name FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
    $district = $db->query("SELECT id FROM districts WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
    $khoroo   = $district ? $db->query("SELECT id FROM khoroos WHERE district_id = " . (int)$district['id'] . " ORDER BY number LIMIT 1")->fetch() : null;

    if (!$product || !$district) {
        http_response_code(500);
        echo json_encode(['error' => 'No active product or district found to build a test order']);
        exit;
    }

    // orders.order_number is VARCHAR(20) — keep within limit
    $orderNumber = 'TEST-' . date('mdHis') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

    $db->prepare("
        INSERT INTO orders
            (order_number, customer_name, customer_phone, fulfillment, district_id, khoroo_id,
             address, payment_method, subtotal, delivery_fee, total, payment_status, status)
        VALUES (?, ?, ?, 'delivery', ?, ?, ?, ?, ?, 0, ?, 'pending', 'pending')
    ")->execute([
        $orderNumber, 'Webhook Test', '99000000',
        (int)$district['id'], $khoroo ? (int)$khoroo['id'] : null,
        'Webhook test — do not ship', $paymentMethod, $amount, $amount,
    ]);
    $orderId = (int)$db->lastInsertId();

    $db->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, line_total)
        VALUES (?, ?, ?, ?, 1, ?)
    ")->execute([$orderId, (int)$product['id'], '[TEST] ' . $product['name'], $amount, $amount]);

    auditLog('test_order_created', 'order', $orderId, 'admin', null, [
        'order_number' => $orderNumber, 'amount' => $amount, 'payment_method' => $paymentMethod,
    ]);

    echo json_encode([
        'success'      => true,
        'order_number' => $orderNumber,
        'order_id'     => $orderId,
        'thanks_url'   => $siteUrl . '/order-thanks?order=' . rawurlencode($orderNumber),
        'note'         => 'Open thanks_url in a browser — it runs the real invoice-creation flow for ' . $paymentMethod,
    ], JSON_UNESCAPED_SLASHES);
    exit;

// ── ping-callback ────────────────────────────────────────────
case 'ping-callback':
    $orderNumber = trim($input['order_number'] ?? '');
    $provider    = trim($input['provider'] ?? '');

    if (!$orderNumber || !in_array($provider, ['qpay', 'bonum', 'storepay'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'order_number and provider (qpay|bonum|storepay) required']);
        exit;
    }

    $url = match ($provider) {
        'qpay'     => $siteUrl . '/backend/api/qpay-callback.php?order=' . rawurlencode($orderNumber),
        'bonum'    => $siteUrl . '/backend/api/bonum-callback.php?order=' . rawurlencode($orderNumber),
        'storepay' => $siteUrl . '/backend/api/storepay-callback.php?order=' . rawurlencode($orderNumber),
    };

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($provider === 'bonum') {
        // Bonum posts JSON; an unknown-status body exercises parse + skip path
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        $opts[CURLOPT_POSTFIELDS] = json_encode(['type' => 'PAYMENT', 'status' => 'PING', 'body' => []]);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo json_encode([
        'success'       => $err === '',
        'callback_url'  => $url,
        'http_code'     => $code,
        'response_body' => mb_substr((string)$body, 0, 500),
        'curl_error'    => $err ?: null,
    ], JSON_UNESCAPED_SLASHES);
    exit;

// ── simulate-paid ────────────────────────────────────────────
case 'simulate-paid':
    $orderNumber = trim($input['order_number'] ?? '');
    if (!str_starts_with($orderNumber, 'TEST-')) {
        http_response_code(400);
        echo json_encode(['error' => 'Only TEST- orders can be simulated']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, order_number, payment_status, status FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    if ($order['payment_status'] === 'paid') {
        echo json_encode(['success' => true, 'already_paid' => true]);
        exit;
    }

    // Mirror the real callback side effects
    $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'")
       ->execute([$order['id']]);
    $db->prepare("
        UPDATE order_items oi
        JOIN products p ON oi.product_id = p.id
        SET oi.cargo_fee_paid = 1
        WHERE oi.order_id = ? AND oi.cargo_fee > 0 AND p.hide_cargo_fee = 0
    ")->execute([$order['id']]);
    if ($order['status'] === 'pending') {
        $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")
           ->execute([$order['id']]);
    }

    auditLog('test_webhook_simulated', 'order', $order['id'], 'admin', null, ['order_number' => $orderNumber]);

    echo json_encode(['success' => true, 'order_number' => $orderNumber, 'payment_status' => 'paid', 'status' => 'confirmed']);
    exit;

// ── cleanup ──────────────────────────────────────────────────
case 'cleanup':
    $ids = $db->query("SELECT id FROM orders WHERE order_number LIKE 'TEST-%'")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $db->exec("DELETE FROM order_items WHERE order_id IN ($in)");
        $db->exec("DELETE FROM orders WHERE id IN ($in)");
    }
    auditLog('test_orders_cleanup', 'order', 0, 'admin', null, ['deleted' => count($ids)]);
    echo json_encode(['success' => true, 'deleted' => count($ids)]);
    exit;

default:
    http_response_code(400);
    echo json_encode([
        'error' => 'Unknown action',
        'available_actions' => ['verify-config', 'test-order', 'ping-callback', 'simulate-paid', 'cleanup'],
    ]);
}
