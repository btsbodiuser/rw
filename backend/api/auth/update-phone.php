<?php
/**
 * API: Update customer phone
 * POST /api/auth/update-phone.php
 * Header: Authorization: Bearer <token>
 * Body: { "new_phone": "99112233", "otp_code": "1234" }
 *   otp_code is required only for phone-registered accounts (no email).
 *   Email-registered accounts may omit it — email login already proves identity.
 * Returns: { "success": true, "phone": "99112233" }
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
$stmt = $db->prepare("SELECT c.id, c.email FROM customer_sessions s JOIN customers c ON c.id = s.customer_id WHERE s.token = ? AND s.expires_at > NOW()");
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired session']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newPhone = preg_replace('/[^0-9]/', '', $input['new_phone'] ?? '');
$otpCode = trim($input['otp_code'] ?? '');
$hasEmail = !empty($customer['email']);

if (strlen($newPhone) !== 8) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid phone number']);
    exit;
}

// Email-registered accounts skip OTP — identity already proven by session
if (!$hasEmail) {
    if (strlen($otpCode) !== 4 || !ctype_digit($otpCode)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid OTP format']);
        exit;
    }
}

// Check phone not already taken by another customer
$stmt = $db->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
$stmt->execute([$newPhone, $customer['id']]);
if ($stmt->fetch()) {
    ob_end_clean();
    http_response_code(409);
    echo json_encode(['error' => 'Phone number already registered to another account']);
    exit;
}

if (!$hasEmail) {
    // Phone-registered account: verify OTP for the new number
    $stmt = $db->prepare("SELECT id, code, verify_attempts FROM otp_codes WHERE phone = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$newPhone]);
    $otp = $stmt->fetch();

    if (!$otp) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired OTP. Please request a new code.']);
        exit;
    }

    if ((int)$otp['verify_attempts'] >= 5) {
        $db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otp['id']]);
        ob_end_clean();
        http_response_code(429);
        echo json_encode(['error' => 'Too many incorrect attempts. Please request a new code.']);
        exit;
    }

    $db->prepare("UPDATE otp_codes SET verify_attempts = verify_attempts + 1 WHERE id = ?")->execute([$otp['id']]);

    if ($otp['code'] !== $otpCode) {
        $remaining = max(4 - (int)$otp['verify_attempts'], 0);
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => "Incorrect code. $remaining attempts remaining."]);
        exit;
    }

    $db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otp['id']]);
}

$db->prepare("UPDATE customers SET phone = ? WHERE id = ?")->execute([$newPhone, $customer['id']]);

ob_end_clean();
echo json_encode(['success' => true, 'phone' => $newPhone]);
