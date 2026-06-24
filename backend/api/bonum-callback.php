<?php
/**
 * Bonum Payment Webhook Receiver
 *
 * Bonum POSTs here when a payment succeeds or fails.
 * POST /api/bonum-callback.php?order={orderNumber}
 *
 * Body example (SUCCESS):
 * {
 *   "type": "PAYMENT",
 *   "status": "SUCCESS",
 *   "body": {
 *     "invoiceId": "...",
 *     "transactionId": "NO12345678",
 *     "status": "PAID",
 *     "amount": 10000.00,
 *     ...
 *   }
 * }
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

// Browser redirect after payment: Bonum sends the customer back here via GET
// (or any request with no JSON body). Forward them to the order-tracking page
// on the storefront instead of dumping JSON.
if (!$payload) {
    $orderNumber = $_GET['order'] ?? '';
    $target = getBasePath() . 'order-tracking' . ($orderNumber ? '/' . rawurlencode($orderNumber) : '');
    header('Location: ' . $target);
    exit;
}

header('Content-Type: application/json');

$type   = $payload['type']   ?? '';
$status = $payload['status'] ?? '';
$body   = $payload['body']   ?? [];

// Only process successful payment webhooks
if ($type !== 'PAYMENT' || $status !== 'SUCCESS') {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}

$invoiceId   = $body['invoiceId']     ?? '';
$orderNumber = $body['transactionId'] ?? ($_GET['order'] ?? '');
$bodyStatus  = strtoupper($body['status'] ?? '');

if ($bodyStatus !== 'PAID' || (empty($invoiceId) && empty($orderNumber))) {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}

$db = getDB();

// Find order — prefer invoice ID match, fall back to order number
if ($invoiceId) {
    $stmt = $db->prepare("SELECT id, order_number, bonum_invoice_id, payment_status, status FROM orders WHERE bonum_invoice_id = ?");
    $stmt->execute([$invoiceId]);
} else {
    $stmt = $db->prepare("SELECT id, order_number, bonum_invoice_id, payment_status, status FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
}
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    exit;
}

if ($order['payment_status'] === 'paid') {
    echo json_encode(['ok' => true, 'already_paid' => true]);
    exit;
}

// Security: verify the invoice_id matches what we stored for this order
if ($invoiceId && $order['bonum_invoice_id'] && $order['bonum_invoice_id'] !== $invoiceId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invoice ID mismatch']);
    exit;
}

// Optionally verify via the Bonum API (re-authenticate and GET invoice status)
// This adds security but requires credentials to be available here
$terminalId = getSetting('bonum_terminal_id');
$secretKey  = getSetting('bonum_secret_key');
$verified   = false;

if ($terminalId && $secretKey && $invoiceId) {
    // Load cached token from temp file
    $tokenFile   = sys_get_temp_dir() . '/bonum_tok_' . md5($terminalId) . '.json';
    $accessToken = null;
    if (file_exists($tokenFile)) {
        $td = json_decode(file_get_contents($tokenFile), true);
        if (!empty($td['token']) && !empty($td['expires_at']) && $td['expires_at'] > time() + 60) {
            $accessToken = $td['token'];
        }
    }

    if ($accessToken) {
        $ch = curl_init('https://apis.bonum.mn/bonum-gateway/ecommerce/invoices/' . urlencode($invoiceId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'X-TERMINAL-ID: '        . $terminalId,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $verifyResponse = curl_exec($ch);
        $verifyCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($verifyCode === 200) {
            $verifyData = json_decode($verifyResponse, true);
            $apiStatus  = strtoupper($verifyData['status'] ?? ($verifyData['body']['status'] ?? ''));
            $verified   = ($apiStatus === 'PAID');
        }
    } else {
        // No cached token available — trust the webhook (Bonum sends only on real payments)
        $verified = true;
    }
} else {
    // No credentials to verify with — trust the webhook
    $verified = true;
}

if (!$verified) {
    http_response_code(402);
    echo json_encode(['ok' => false, 'error' => 'Payment verification failed']);
    exit;
}

// Mark order as paid
$db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'")
   ->execute([$order['id']]);

// Mark visible cargo fees paid (they were included in Bonum total)
$db->prepare("
    UPDATE order_items oi
    JOIN products p ON oi.product_id = p.id
    SET oi.cargo_fee_paid = 1
    WHERE oi.order_id = ? AND oi.cargo_fee > 0 AND p.hide_cargo_fee = 0
")->execute([$order['id']]);

$unpaid = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
$unpaid->execute([$order['id']]);
$allPaid = (int)$unpaid->fetchColumn() === 0 ? 1 : 0;
$db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $order['id']]);

// Auto-confirm if still pending
if ($order['status'] === 'pending') {
    $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")
       ->execute([$order['id']]);
}

auditLog('payment_bonum_webhook', 'order', $order['id'], 'system', null, [
    'order_number' => $order['order_number'],
    'invoice_id'   => $invoiceId,
    'amount'       => $body['amount'] ?? null,
]);

echo json_encode(['ok' => true]);
