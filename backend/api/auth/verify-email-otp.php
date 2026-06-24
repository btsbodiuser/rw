<?php
/**
 * API: Verify email OTP
 * POST /api/auth/verify-email-otp.php
 * Body: { "email": "user@example.com", "otp": "123456" }
 * Returns: { "success": true, "token": "..." }
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

// OTP is valid, delete it and return success
$db->prepare("DELETE FROM otp_codes WHERE id = ?")->execute([$row['id']]);

ob_end_clean();
echo json_encode(['message' => 'OTP verified successfully']);
?>