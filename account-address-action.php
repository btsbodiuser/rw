<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || !customerToken()) {
    header('Location: ' . url('login') . '?redirect=' . urlencode(url('account') . '?tab=addresses'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('account') . '?tab=addresses');
    exit;
}

$token  = customerToken();
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    apiCall('POST', 'addresses.php', [
        'label'          => trim($_POST['label'] ?? ''),
        'district_id'    => (int)($_POST['district_id'] ?? 0),
        'khoroo_id'      => (int)($_POST['khoroo_id'] ?? 0),
        'address'        => trim($_POST['address'] ?? ''),
        'detail_address' => trim($_POST['detail_address'] ?? ''),
        'is_default'     => !empty($_POST['is_default']),
    ], $token);
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        apiCall('DELETE', 'addresses.php?id=' . $id, null, $token);
    }
}

header('Location: ' . url('account') . '?tab=addresses');
exit;
