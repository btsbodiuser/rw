<?php
/**
 * API: Change password for the logged-in customer
 * POST /api/auth/change-password.php
 * Header: Authorization: Bearer <token>
 * Body: { "current_password": "...", "new_password": "..." }
 *   current_password is required unless the account has no password yet
 *   (e.g. social-login accounts, where customers.password is '').
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT c.id, c.password FROM customer_sessions s JOIN customers c ON c.id = s.customer_id WHERE s.token = ? AND s.expires_at > NOW()");
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired session']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$currentPassword = (string)($input['current_password'] ?? '');
$newPassword     = (string)($input['new_password'] ?? '');

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Шинэ нууц үг доод тал нь 6 тэмдэгт байх ёстой']);
    exit;
}

$hasPassword = !empty($customer['password']);
if ($hasPassword && !password_verify($currentPassword, $customer['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Одоогийн нууц үг буруу байна']);
    exit;
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT);
$db->prepare("UPDATE customers SET password = ? WHERE id = ?")->execute([$hash, $customer['id']]);

echo json_encode(['success' => true]);
