<?php
/**
 * API: Register new customer WITHOUT OTP verification
 * POST /api/auth/register-direct.php
 * Body: { "phone": "99112233", "password": "...", "name": "..." }
 * Returns: { "success": true, "token": "...", "user": {...} }
 *
 * Only available when admin enables `login_register_without_otp_enabled`.
 * Intended for customers who cannot receive SMS.
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

// Server-side gate: only allow if admin has enabled this method
if (getSetting('login_register_without_otp_enabled', '0') !== '1') {
    http_response_code(403);
    echo json_encode(['error' => 'SMS-гүй бүртгэл идэвхгүй байна']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
$password = $input['password'] ?? '';
$name = trim($input['name'] ?? '');

if (strlen($phone) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => '8 оронтой утасны дугаар оруулна уу']);
    exit;
}
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Нууц үг хамгийн багадаа 6 тэмдэгт байх ёстой']);
    exit;
}
if ($name === '' || mb_strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Нэрээ оруулна уу (хамгийн ихдээ 100 тэмдэгт)']);
    exit;
}

$db = getDB();

// Per-IP rate limit: max 5 direct registrations per hour
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$rlKey = 'reg_direct_' . md5($clientIp);
$now = time();
$bucket = $_SESSION[$rlKey] ?? ['count' => 0, 'reset' => $now + 3600];
if ($now > $bucket['reset']) {
    $bucket = ['count' => 0, 'reset' => $now + 3600];
}
if ($bucket['count'] >= 5) {
    $_SESSION[$rlKey] = $bucket;
    session_write_close();
    http_response_code(429);
    echo json_encode(['error' => 'Хэт олон бүртгэл хийсэн байна. Дараа дахин оролдоно уу.']);
    exit;
}
$bucket['count']++;
$_SESSION[$rlKey] = $bucket;
session_write_close();

// Reject if phone already registered (no OTP means we cannot verify ownership;
// existing owners should use login or OTP-based reset)
$stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
$stmt->execute([$phone]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Энэ дугаар бүртгэлтэй байна. Нэвтэрнэ үү.']);
    exit;
}

// Create customer
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare("INSERT INTO customers (phone, password, name, created_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([$phone, $hash, $name]);
$customerId = (int)$db->lastInsertId();

// Create session token (7 days) — same scheme as login.php / register.php
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
$db->prepare("INSERT INTO customer_sessions (customer_id, token, expires_at) VALUES (?, ?, ?)")
    ->execute([$customerId, $token, $expiresAt]);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => $customerId,
        'phone' => $phone,
        'name' => $name,
    ],
]);
