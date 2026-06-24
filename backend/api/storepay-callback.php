<?php
/**
 * StorePay Payment Callback (server-to-server webhook)
 *
 * StorePay calls this URL when a loan invoice is confirmed.
 * GET /api/storepay-callback.php?order={orderNumber}&id={loanId}
 *
 * Per the docs the `id` query param contains the loan invoice id; we must
 * re-call the check API to verify before marking the order as paid.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$orderNumber = $_GET['order'] ?? '';
$loanId      = $_GET['id']    ?? '';

$db = getDB();

// Find the order — prefer order number, fall back to loan ID lookup
$order = null;
if ($orderNumber !== '') {
    $stmt = $db->prepare("SELECT id, order_number, storepay_invoice_id, payment_status, status FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
}
if (!$order && $loanId !== '') {
    $stmt = $db->prepare("SELECT id, order_number, storepay_invoice_id, payment_status, status FROM orders WHERE storepay_invoice_id = ?");
    $stmt->execute([$loanId]);
    $order = $stmt->fetch();
}

if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

// If already paid, just acknowledge (or redirect browser)
if ($order['payment_status'] === 'paid') {
    if (empty($_SERVER['HTTP_USER_AGENT']) || strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false) {
        echo 'Already paid';
    } else {
        header('Location: ' . getBasePath() . 'order-tracking/' . rawurlencode($order['order_number']));
    }
    exit;
}

// Verify stored loan ID matches the one StorePay sent
$effectiveLoanId = $loanId !== '' ? $loanId : (string)$order['storepay_invoice_id'];
if ($loanId !== '' && $order['storepay_invoice_id'] && $order['storepay_invoice_id'] !== $loanId) {
    http_response_code(403);
    echo 'Invoice ID mismatch';
    exit;
}
if ($effectiveLoanId === '') {
    http_response_code(400);
    echo 'Missing loan id';
    exit;
}

// ── Re-verify with the StorePay check API ───────────────────────
$username    = getSetting('storepay_username');
$password    = getSetting('storepay_password');
$appUsername = getSetting('storepay_app_username');
$appPassword = getSetting('storepay_app_password');

if (!$username || !$password || !$appUsername || !$appPassword) {
    http_response_code(500);
    echo 'StorePay not configured';
    exit;
}

// Try cached token first
$tokenFile   = sys_get_temp_dir() . '/storepay_tok_' . md5($appUsername . '|' . $username) . '.json';
$accessToken = null;
if (file_exists($tokenFile)) {
    $td = json_decode(file_get_contents($tokenFile), true);
    if (!empty($td['token']) && !empty($td['expires_at']) && $td['expires_at'] > time() + 60) {
        $accessToken = $td['token'];
    }
}

// Authenticate if no cached token
if (!$accessToken) {
    $authUrl = 'https://service.storepay.mn/merchant-uaa/oauth/token'
             . '?grant_type=password'
             . '&username=' . urlencode($username)
             . '&password=' . urlencode($password);

    $ch = curl_init($authUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode($appUsername . ':' . $appPassword),
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $authResp = curl_exec($ch);
    $authCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($authCode !== 200) {
        http_response_code(502);
        echo 'StorePay auth failed';
        exit;
    }
    $authData    = json_decode($authResp, true);
    $accessToken = $authData['access_token'] ?? '';

    if (!$accessToken) {
        http_response_code(502);
        echo 'No access token';
        exit;
    }

    file_put_contents($tokenFile, json_encode([
        'token'      => $accessToken,
        'expires_at' => time() + (int)($authData['expires_in'] ?? 7200) - 60,
    ]));
}

// Check the loan status
$ch = curl_init('https://service.storepay.mn/lend-merchant/merchant/loan/check/' . urlencode($effectiveLoanId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$checkResp = curl_exec($ch);
$checkCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($checkCode !== 200) {
    http_response_code(502);
    echo 'Payment check failed';
    exit;
}

$checkData = json_decode($checkResp, true);
$status    = strtolower((string)($checkData['status'] ?? ''));
$value     = $checkData['value'] ?? null;
$paid      = ($status === 'success') && ($value === true || $value === 'true' || $value === 1 || $value === '1');

if (!$paid) {
    echo 'NOT PAID';
    exit;
}

// ── Mark order as paid ──────────────────────────────────────────
$db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'")
   ->execute([$order['id']]);

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

if ($order['status'] === 'pending') {
    $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")
       ->execute([$order['id']]);
}

auditLog('payment_storepay_callback', 'order', $order['id'], 'system', null, [
    'order_number' => $order['order_number'],
    'invoice_id'   => $effectiveLoanId,
]);

// Browser hitting the URL → redirect to tracking; server-to-server → plain text ack
if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') !== false) {
    header('Location: ' . getBasePath() . 'order-tracking/' . rawurlencode($order['order_number']));
    exit;
}

echo 'SUCCESS';
