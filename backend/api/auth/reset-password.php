<?php
/**
 * API: Reset password (after OTP verification)
 * POST /api/auth/reset-password.php
 * Body: { "phone": "99112233", "otp_token": "...", "password": "..." }
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

if (strlen($phone) !== 8 || !$otpToken || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone, OTP token, and password (min 6 chars) required']);
    exit;
}

$db = getDB();

// Verify OTP token
$stmt = $db->prepare("SELECT id FROM otp_codes WHERE phone = ? AND otp_token = ? AND is_used = 1 AND otp_token_expires > NOW()");
$stmt->execute([$phone, $otpToken]);
if (!$stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid OTP token']);
    exit;
}

// Update password
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare("UPDATE customers SET password = ? WHERE phone = ?");
$stmt->execute([$hash, $phone]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Customer not found']);
    exit;
}

// Get customer for session
$stmt = $db->prepare("SELECT id, phone, name FROM customers WHERE phone = ?");
$stmt->execute([$phone]);
$customer = $stmt->fetch();

// Create session token (7 days)
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
$db->prepare("INSERT INTO customer_sessions (customer_id, token, expires_at) VALUES (?, ?, ?)")
    ->execute([$customer['id'], $token, $expiresAt]);

// Clean up OTP tokens
$db->prepare("DELETE FROM otp_codes WHERE phone = ?")->execute([$phone]);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => (int)$customer['id'],
        'phone' => $customer['phone'],
        'name' => $customer['name'],
    ],
]);
