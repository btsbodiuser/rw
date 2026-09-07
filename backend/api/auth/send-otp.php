<?php
/**
 * API: Send OTP via MessagePro
 * POST /api/auth/send-otp.php
 * Body: { "phone": "99112233" }
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

if (strlen($phone) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid phone number']);
    exit;
}

$db = getDB();

// Rate limit: max 5 OTP requests per phone per hour
// Exclude login_fail rows (login.php uses the otp_codes table for failed-login tracking)
$stmt = $db->prepare("SELECT COUNT(*) FROM otp_codes WHERE phone = ? AND code <> 'login_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([$phone]);
if ($stmt->fetchColumn() >= 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Энэ дугаар руу дэндүү олон код илгээсэн байна. 1 цагийн дараа дахин оролдоно уу.']);
    exit;
}

// Rate limit: max 20 OTP requests per IP per hour
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$stmt = $db->prepare("SELECT COUNT(*) FROM otp_codes WHERE ip_address = ? AND code <> 'login_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([$clientIp]);
$ipCount = (int)$stmt->fetchColumn();
if ($ipCount >= 20) {
    http_response_code(429);
    echo json_encode(['error' => 'Хэт олон хүсэлт ирүүлсэн байна. Хэсэг хугацааны дараа дахин оролдоно уу.']);
    exit;
}

// Generate 4-digit OTP
$code = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

// Save OTP — use MySQL NOW() + INTERVAL to avoid PHP/MySQL timezone mismatch
$stmt = $db->prepare("INSERT INTO otp_codes (phone, code, expires_at, ip_address) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE), ?)");
$stmt->execute([$phone, $code, $clientIp]);

// Send SMS via the shared backend helper — it already handles credentials
// lookup, the localhost CA-bundle problem (verifypeer=false), and consistent
// error shape. Was previously duplicated here with SSL verification on, which
// broke on WampServer where no CA bundle is configured.
$smsText = getSMSTemplate('otp', ['code' => $code], "Runners World баталгаажуулах код: $code");
$result  = sendSingleSMS($phone, $smsText);

if (!$result['success']) {
    error_log('[send-otp] MessagePro failed: ' . ($result['error'] ?? 'unknown'));
    http_response_code(502);
    echo json_encode([
        'error' => 'СМС илгээх үед алдаа гарлаа. Дахин оролдоно уу.',
        'debug' => ['reason' => $result['error']],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'OTP sent']);
