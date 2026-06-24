<?php
/**
 * API: Verify OTP code
 * POST /api/auth/verify-otp.php
 * Body: { "phone": "99112233", "code": "1234" }
 * Returns: { "verified": true, "otp_token": "..." }
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
$code = trim($input['code'] ?? '');

if (strlen($phone) !== 8 || strlen($code) !== 4) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid phone or code']);
    exit;
}

$db = getDB();

// Rate limit: max 10 verify attempts per phone per 15 minutes
$stmt = $db->prepare("SELECT COALESCE(SUM(verify_attempts), 0) FROM otp_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$stmt->execute([$phone]);
if ((int)$stmt->fetchColumn() >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Хэт олон оролдлого хийсэн байна. 15 минутын дараа дахин оролдоно уу.']);
    exit;
}

// Find the latest unused OTP for this phone
$stmt = $db->prepare("SELECT id, code, verify_attempts FROM otp_codes WHERE phone = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->execute([$phone]);
$otp = $stmt->fetch();

if (!$otp) {
    http_response_code(400);
    echo json_encode(['error' => 'Код буруу эсвэл хугацаа дууссан. Шинэ код илгээнэ үү.']);
    exit;
}

// Check per-OTP attempt limit (max 5 tries per code)
if ((int)$otp['verify_attempts'] >= 5) {
    // Invalidate this OTP
    $db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otp['id']]);
    http_response_code(429);
    echo json_encode(['error' => 'Хэт олон удаа буруу оруулсан байна. Шинэ код илгээнэ үү.']);
    exit;
}

// Increment attempt counter
$db->prepare("UPDATE otp_codes SET verify_attempts = verify_attempts + 1 WHERE id = ?")->execute([$otp['id']]);

// Verify code
if ($otp['code'] !== $code) {
    $remaining = max(5 - (int)$otp['verify_attempts'], 0);
    http_response_code(400);
    $msg = $remaining > 0
        ? "Код буруу байна. Үлдсэн оролдлого: $remaining"
        : 'Код буруу байна. Шинэ код илгээнэ үү.';
    echo json_encode(['error' => $msg, 'attempts_remaining' => $remaining]);
    exit;
}

// Mark OTP as used
$db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otp['id']]);

// Generate a temporary OTP token (used for register/reset-password step)
$otpToken = bin2hex(random_bytes(32));

// Store in separate column with 10min expiry
$db->prepare("UPDATE otp_codes SET otp_token = ?, otp_token_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")->execute([$otpToken, $otp['id']]);

echo json_encode(['verified' => true, 'otp_token' => $otpToken]);
