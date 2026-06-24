<?php
/**
 * API: Reset password with email OTP
 * POST /api/auth/reset-password-email.php
 * Body: { "email": "user@example.com", "otp": "1234", "new_password": "..." }
 * Returns: { "success": true }
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

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$otp = trim($input['otp'] ?? '');
$newPassword = $input['password'] ?? $input['new_password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

if (strlen($otp) !== 4 || !ctype_digit($otp)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid OTP format']);
    exit;
}

if (strlen($newPassword) < 6) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    exit;
}

// Find the latest valid OTP for this email
$stmt = $db->prepare("
    SELECT id, code FROM otp_codes
    WHERE identifier = ? AND type = 'email' AND expires_at > NOW()
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$email]);
$row = $stmt->fetch();

if (!$row || !password_verify($otp, $row['code'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired OTP']);
    exit;
}

// Find customer by email
$stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
$stmt->execute([$email]);
$customer = $stmt->fetch();

if (!$customer) {
    ob_end_clean();
    http_response_code(404);
    echo json_encode(['error' => 'Email not found']);
    exit;
}

try {
    $db->beginTransaction();

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->prepare("UPDATE customers SET password = ? WHERE id = ?")->execute([$hashedPassword, $customer['id']]);

    // Delete used OTP and invalidate all sessions
    $db->prepare("DELETE FROM otp_codes WHERE id = ?")->execute([$row['id']]);
    $db->prepare("DELETE FROM customer_sessions WHERE customer_id = ?")->execute([$customer['id']]);

    $db->commit();

    auditLog('password_reset', 'customer', $customer['id'], 'customer', $customer['id'], ['method' => 'email']);

    ob_end_clean();
    echo json_encode(['message' => 'Password reset successful']);
} catch (Exception $e) {
    $db->rollBack();
    error_log('Password reset failed: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Password reset failed. Please try again.']);
}
