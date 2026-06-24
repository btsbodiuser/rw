<?php
/**
 * API: Register new customer (after OTP verification)
 * POST /api/auth/register.php
 * Body: { "phone": "99112233", "otp_token": "...", "password": "...", "name": "..." }
 * Returns: { "success": true, "token": "...", "user": {...} }
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

$input = json_decode(file_get_contents('php://input'), true);
$phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
$otpToken = trim($input['otp_token'] ?? '');
$password = $input['password'] ?? '';
$name = trim($input['name'] ?? '');

if (strlen($phone) !== 8 || !$otpToken || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone, verified OTP token, and password (min 6 chars) required']);
    exit;
}

$db = getDB();

// Verify OTP token is valid and not expired
$stmt = $db->prepare("SELECT id FROM otp_codes WHERE phone = ? AND otp_token = ? AND is_used = 1 AND otp_token_expires > NOW()");
$stmt->execute([$phone, $otpToken]);
if (!$stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid OTP token']);
    exit;
}

// Check if customer already exists
$stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
$stmt->execute([$phone]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Phone already registered']);
    exit;
}

// Create customer
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare("INSERT INTO customers (phone, password, name) VALUES (?, ?, ?)");
$stmt->execute([$phone, $hash, $name]);
$customerId = $db->lastInsertId();

// Create session token (7 days)
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
$db->prepare("INSERT INTO customer_sessions (customer_id, token, expires_at) VALUES (?, ?, ?)")
    ->execute([$customerId, $token, $expiresAt]);

// Clean up OTP tokens for this phone
$db->prepare("DELETE FROM otp_codes WHERE phone = ?")->execute([$phone]);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => (int)$customerId,
        'phone' => $phone,
        'name' => $name,
    ],
]);
