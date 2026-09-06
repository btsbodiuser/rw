<?php
/**
 * API: Send email OTP for registration or password reset
 * POST /api/auth/send-email-otp.php
 * Body: { "email": "user@example.com", "purpose": "register" or "reset" }
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
$purpose = $input['purpose'] ?? $input['action'] ?? 'register';
$purpose = in_array($purpose, ['reset', 'update'], true) ? $purpose : 'register';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

// Rate limiting: max 5 active (non-expired) OTPs per email
$stmt = $db->prepare("SELECT COUNT(*) FROM otp_codes WHERE identifier = ? AND type = 'email' AND expires_at > NOW()");
$stmt->execute([$email]);
if ($stmt->fetchColumn() >= 5) {
    ob_end_clean();
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

// Check if email already exists for registration
if ($purpose === 'register') {
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        ob_end_clean();
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }
} elseif ($purpose === 'reset') {
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['error' => 'Email not found']);
        exit;
    }
}
// 'update': no pre-check — update-email.php verifies ownership and uniqueness

// Invalidate any earlier unused codes for this email so the one just sent
// is always the one that verifies — otherwise a user who requests a second
// code (or opens a delayed first email) can enter a code that looks right
// but no longer matches the latest row and gets "Invalid or expired OTP".
$db->prepare("DELETE FROM otp_codes WHERE identifier = ? AND type = 'email' AND is_used = 0")->execute([$email]);

// Generate OTP
$otp = generateOTP();

// Store OTP — use MySQL NOW() + INTERVAL, not PHP's date(), so expiry can't
// drift if the web server's PHP timezone and MySQL's timezone ever differ.
$stmt = $db->prepare("INSERT INTO otp_codes (identifier, type, code, expires_at) VALUES (?, 'email', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
$stmt->execute([$email, password_hash($otp, PASSWORD_DEFAULT)]);

// Send email
try {
    $titles = [
        'register' => 'Имэйл баталгаажуулах',
        'reset'    => 'Нууц үг сэргээх',
        'update'   => 'Имэйл хаяг шинэчлэх',
    ];
    $defaultSubjects = [
        'register' => 'Баталгаажуулах код — ' . getSetting('site_name', 'Runners World'),
        'reset'    => 'Нууц үг сэргээх код — ' . getSetting('site_name', 'Runners World'),
        'update'   => 'Имэйл шинэчлэх код — ' . getSetting('site_name', 'Runners World'),
    ];
    $defaultBodies = [
        'register' => 'Та имэйл хаягаа баталгаажуулахын тулд доорх кодыг ашиглана уу. Код <strong>10 минутын</strong> дараа хүчингүй болно.',
        'reset'    => 'Та нууц үгээ сэргээхийн тулд доорх кодыг ашиглана уу. Код <strong>10 минутын</strong> дараа хүчингүй болно.',
        'update'   => 'Та имэйл хаягаа шинэчлэхийн тулд доорх кодыг ашиглана уу. Код <strong>10 минутын</strong> дараа хүчингүй болно.',
    ];

    $subjectKey = $purpose === 'register' ? 'email_template_subject_register' : 'email_template_subject_reset';
    $bodyKey    = $purpose === 'register' ? 'email_template_body_register'    : 'email_template_body_reset';

    $subject      = getSetting($subjectKey, $defaultSubjects[$purpose]);
    $bodyTemplate = getSetting($bodyKey, $defaultBodies[$purpose]);
    $innerHtml    = str_replace(['{otp}', '{email}', '{purpose}'], ['', $email, $purpose], $bodyTemplate);

    $body = buildEmailHtml($innerHtml, $titles[$purpose], $otp);

    sendEmail($email, $subject, $body, true);

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Verification code sent to your email']);
} catch (Exception $e) {
    error_log('Email sending failed: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email. Please try again.']);
}
