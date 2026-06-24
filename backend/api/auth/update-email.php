<?php
/**
 * API: Update customer email with OTP verification
 * POST /api/auth/update-email.php
 * Header: Authorization: Bearer <token>
 * Body: { "new_email": "user@example.com", "otp_code": "1234" }
 * Returns: { "success": true, "email": "user@example.com" }
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = getBearerToken();
if (!$token) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT c.id FROM customer_sessions s JOIN customers c ON c.id = s.customer_id WHERE s.token = ? AND s.expires_at > NOW()");
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired session']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newEmail = trim($input['new_email'] ?? '');
$otpCode = trim($input['otp_code'] ?? '');

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

if (strlen($otpCode) !== 4 || !ctype_digit($otpCode)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid OTP format']);
    exit;
}

// Check email not already taken by another customer
$stmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
$stmt->execute([$newEmail, $customer['id']]);
if ($stmt->fetch()) {
    ob_end_clean();
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered to another account']);
    exit;
}

// Find valid OTP for the new email
$stmt = $db->prepare("SELECT id, code FROM otp_codes WHERE identifier = ? AND type = 'email' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$newEmail]);
$otp = $stmt->fetch();

if (!$otp || !password_verify($otpCode, $otp['code'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired OTP. Please request a new code.']);
    exit;
}

$db->prepare("DELETE FROM otp_codes WHERE id = ?")->execute([$otp['id']]);
$db->prepare("UPDATE customers SET email = ? WHERE id = ?")->execute([$newEmail, $customer['id']]);

ob_end_clean();
echo json_encode(['success' => true, 'email' => $newEmail]);
