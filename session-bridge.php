<?php
/**
 * Bridges a bearer token from the customer-facing JSON API (returned by
 * register.php / login.php / verify flows called via fetch()) into this
 * server-rendered app's PHP session, so isLoggedIn()/getSessionUser() work.
 */
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim((string)($input['token'] ?? ''));

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token required']);
    exit;
}

$res = apiCall('GET', 'auth/me.php', null, $token);

if ($res['code'] === 200 && !empty($res['data']['success'])) {
    loginCustomerSession($res['data']['user'], $token);
    if (!empty($input['remember'])) {
        rememberCustomer($token);
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(401);
echo json_encode(['success' => false, 'error' => 'Invalid session']);
