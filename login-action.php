<?php
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('login'));
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
// Phone identifiers may still carry spaces/dashes from user input; email identifiers must pass through untouched.
if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    $identifier = preg_replace('/[^0-9]/', '', $identifier);
}
$password = (string)($_POST['password'] ?? '');
$redirect = (string)($_POST['redirect'] ?? url('account'));
$base     = getBaseUrl();
if ($redirect === '' || (!str_starts_with($redirect, $base) && !str_starts_with($redirect, '/'))) {
    $redirect = url('account');
}

if ($identifier === '' || $password === '') {
    header('Location: ' . url('login') . '?' . http_build_query(['error' => 'Утасны дугаар эсвэл и-мэйл, нууц үгээ оруулна уу', 'redirect' => $redirect]));
    exit;
}

$res = apiCall('POST', 'auth/login.php', ['identifier' => $identifier, 'password' => $password]);

if ($res['code'] === 200 && !empty($res['data']['success'])) {
    loginCustomerSession($res['data']['user'], $res['data']['token']);
    if (!empty($_POST['remember'])) {
        rememberCustomer($res['data']['token']);
    }
    header('Location: ' . $redirect);
    exit;
}

$msg = $res['data']['error'] ?? 'Нэвтрэхэд алдаа гарлаа. Дахин оролдоно уу.';
header('Location: ' . url('login') . '?' . http_build_query(['error' => $msg, 'redirect' => $redirect]));
exit;
