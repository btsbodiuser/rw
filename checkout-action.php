<?php
require_once __DIR__ . '/includes/config.php';

// POST-only endpoint. Any GET (e.g. refresh) → back to checkout.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('checkout'));
    exit;
}

if (empty($_SESSION['cart'])) {
    setFlash('error', 'Сагс хоосон байна.');
    header('Location: ' . url('cart'));
    exit;
}

// CSRF: session-token gate blocks forged POSTs from other origins.
if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Хүсэлт хүчингүй боллоо. Хуудсаа шинэчилж дахин оролдоно уу.');
    header('Location: ' . url('checkout'));
    exit;
}

// ── Collect + validate form ─────────────────────────────────
$name          = trim((string)($_POST['customer_name'] ?? ''));
$phone         = preg_replace('/[^0-9]/', '', (string)($_POST['customer_phone'] ?? ''));
$districtId    = (int)($_POST['district_id'] ?? 0);
$khorooId      = (int)($_POST['khoroo_id'] ?? 0);
$address       = trim((string)($_POST['address'] ?? ''));
$detailAddress = trim((string)($_POST['detail_address'] ?? ''));
$paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
$notes         = trim((string)($_POST['notes'] ?? ''));
$saveAddress   = !empty($_POST['save_address']);

$errors = [];
if ($name === '')                        $errors[] = 'Нэрээ оруулна уу.';
if (strlen($phone) !== 8)                $errors[] = 'Утасны дугаар 8 оронтой байх ёстой.';
if ($districtId <= 0)                    $errors[] = 'Дүүрэг сонгоно уу.';
if ($khorooId <= 0)                      $errors[] = 'Хороо сонгоно уу.';
if ($address === '')                     $errors[] = 'Хаяг оруулна уу.';

$allowedPayments = ['qpay', 'transfer', 'bonum', 'storepay'];
if (!in_array($paymentMethod, $allowedPayments, true)) $errors[] = 'Төлбөрийн хэрэгслээ сонгоно уу.';

// Make sure the chosen method is actually enabled in settings — protects
// against form-tampering when an admin has disabled a gateway mid-session.
$enabledMap = [
    'qpay'     => sBool('payment_qpay_enabled', false),
    'transfer' => sBool('payment_transfer_enabled', false),
    'bonum'    => sBool('payment_bonum_enabled', false),
    'storepay' => sBool('payment_storepay_enabled', false),
];
if (!($enabledMap[$paymentMethod] ?? false)) {
    $errors[] = 'Сонгосон төлбөрийн хэрэгсэл идэвхгүй байна.';
}

if ($errors) {
    setFlash('error', implode(' ', $errors));
    header('Location: ' . url('checkout'));
    exit;
}

// ── Build items payload from session cart ───────────────────
$items = [];
foreach ($_SESSION['cart'] as $line) {
    $qty = (int)($line['qty'] ?? 0);
    if ($qty <= 0) continue;
    $items[] = [
        'product_id' => (int)$line['product_id'],
        'variant_id' => !empty($line['variant_id']) ? (int)$line['variant_id'] : null,
        'quantity'   => $qty,
    ];
}
if (!$items) {
    setFlash('error', 'Сагс хоосон байна.');
    header('Location: ' . url('cart'));
    exit;
}

// ── Delegate to the existing customer-facing JSON API ───────
// backend/api/orders.php handles: duplicate detection, per-row stock locks,
// price tiers, cargo fees, preorder validation, address save. We just pass
// the customer's bearer token so the order is attributed to their account.
$payload = [
    'customer_name'  => $name,
    'customer_phone' => $phone,
    'fulfillment'    => 'delivery',
    'district_id'    => $districtId,
    'khoroo_id'      => $khorooId,
    'address'        => $address,
    'detail_address' => $detailAddress,
    'payment_method' => $paymentMethod,
    'notes'          => $notes,
    'save_address'   => $saveAddress,
    'items'          => $items,
];

$res = apiCall('POST', 'orders.php', $payload, customerToken());

if ($res['code'] !== 200 || empty($res['data']['success'])) {
    $msg = $res['data']['error']
        ?? (is_array($res['data']['errors'] ?? null) ? implode(' ', $res['data']['errors']) : 'Захиалга үүсгэхэд алдаа гарлаа.');
    setFlash('error', (string)$msg);
    header('Location: ' . url('checkout'));
    exit;
}

$orderNumber = (string)($res['data']['order_number'] ?? '');
if ($orderNumber === '') {
    setFlash('error', 'Захиалга үүссэн ч дугаар авч чадсангүй. Админд хандана уу.');
    header('Location: ' . url('cart'));
    exit;
}

// Order created — empty session cart and land on the thanks page.
$_SESSION['cart']              = [];
$_SESSION['last_order_number'] = $orderNumber;

header('Location: ' . url('order-thanks?order=' . urlencode($orderNumber)));
exit;
