<?php
/**
 * QPay Payment Callback
 * Called by QPay when payment is completed.
 * GET /api/qpay-callback.php?order=NO12345678
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    http_response_code(400);
    echo 'Missing order number';
    exit;
}

$db = getDB();

// Find the order
$stmt = $db->prepare("SELECT id, order_number, qpay_invoice_id, payment_status, status FROM orders WHERE order_number = ?");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

if ($order['payment_status'] === 'paid') {
    echo 'Already paid';
    exit;
}

// Verify payment with QPay
$qpayUsername = getSetting('qpay_username');
$qpayPassword = getSetting('qpay_password');

if (empty($qpayUsername) || empty($qpayPassword) || empty($order['qpay_invoice_id'])) {
    http_response_code(500);
    echo 'QPay not configured or no invoice';
    exit;
}

// Authenticate with QPay
$ch = curl_init('https://merchant.qpay.mn/v2/auth/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($qpayUsername . ':' . $qpayPassword),
    ],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$authResponse = curl_exec($ch);
$authCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($authCode !== 200) {
    http_response_code(502);
    echo 'QPay auth failed';
    exit;
}

$authData = json_decode($authResponse, true);
$accessToken = $authData['access_token'] ?? '';

if (!$accessToken) {
    http_response_code(502);
    echo 'No access token';
    exit;
}

// Check payment status
$ch = curl_init('https://merchant.qpay.mn/v2/payment/check');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'object_type' => 'INVOICE',
        'object_id' => $order['qpay_invoice_id'],
    ]),
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$checkResponse = curl_exec($ch);
$checkCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($checkCode !== 200) {
    http_response_code(502);
    echo 'Payment check failed';
    exit;
}

$checkData = json_decode($checkResponse, true);

if (!empty($checkData['rows']) && count($checkData['rows']) > 0) {
    // Payment confirmed — update order
    $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'")
       ->execute([$order['id']]);

    // Auto-mark visible cargo fees as paid (they were included in QPay total)
    $db->prepare("
        UPDATE order_items oi
        JOIN products p ON oi.product_id = p.id
        SET oi.cargo_fee_paid = 1
        WHERE oi.order_id = ? AND oi.cargo_fee > 0 AND p.hide_cargo_fee = 0
    ")->execute([$order['id']]);
    // Update order-level cargo_fee_paid (1 if all cargo items paid, 0 otherwise)
    $unpaidCount = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
    $unpaidCount->execute([$order['id']]);
    $allPaid = (int)$unpaidCount->fetchColumn() === 0 ? 1 : 0;
    $db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $order['id']]);

    // Auto-confirm if still pending
    if ($order['status'] === 'pending') {
        $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")
           ->execute([$order['id']]);
    }

    auditLog('payment_callback', 'order', $order['id'], 'system', null, [
        'order_number' => $order['order_number'],
        'invoice_id' => $order['qpay_invoice_id'],
    ]);

    echo 'SUCCESS';
} else {
    echo 'NOT PAID';
}
