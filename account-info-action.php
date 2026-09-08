<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || !customerToken()) {
    header('Location: ' . url('login') . '?redirect=' . urlencode(url('account') . '?tab=info'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('account') . '?tab=info');
    exit;
}

// CSRF gate — same-session token required.
if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Хүсэлт хүчингүй боллоо. Дахин оролдоно уу.');
    header('Location: ' . url('account') . '?tab=info');
    exit;
}

$token  = customerToken();
$action = $_POST['action'] ?? '';
$error  = '';

if ($action === 'update_basic') {
    $meRes = apiCall('GET', 'auth/me.php', null, $token);
    $current = $meRes['data']['user'] ?? [];
    $res = apiCall('PUT', 'auth/me.php', [
        'name'  => trim($_POST['name'] ?? ''),
        'phone' => $current['phone'] ?? '',
        'email' => $current['email'] ?? '',
    ], $token);
    if ($res['code'] !== 200) $error = $res['data']['error'] ?? 'Алдаа гарлаа';
} elseif ($action === 'update_contact') {
    $meRes = apiCall('GET', 'auth/me.php', null, $token);
    $current = $meRes['data']['user'] ?? [];
    $res = apiCall('PUT', 'auth/me.php', [
        'name'  => $current['name'] ?? '',
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ], $token);
    if ($res['code'] !== 200) $error = $res['data']['error'] ?? 'Алдаа гарлаа';
} elseif ($action === 'change_password') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    if ($newPassword !== $confirm) {
        $error = 'Нууц үг таарахгүй байна';
    } else {
        $res = apiCall('POST', 'auth/change-password.php', [
            'current_password' => $_POST['current_password'] ?? '',
            'new_password'     => $newPassword,
        ], $token);
        if ($res['code'] !== 200) $error = $res['data']['error'] ?? 'Алдаа гарлаа';
    }
}

$redirect = url('account') . '?tab=info';
if ($error) $redirect .= '&error=' . urlencode($error);
header('Location: ' . $redirect);
exit;
