<?php
/**
 * API: Login with phone/email + password
 * POST /api/auth/login.php
 * Body: { "identifier": "99112233" or "user@example.com", "password": "..." }
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
$identifier = trim($input['identifier'] ?? '');
$password = $input['password'] ?? '';

if (!$identifier || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Identifier and password required']);
    exit;
}

// Determine if identifier is email or phone
$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
$isPhone = !$isEmail && ctype_digit(preg_replace('/[^0-9]/', '', $identifier));

if (!$isEmail && !$isPhone) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid identifier format']);
    exit;
}

$db = getDB();
$isMaster = defined('MASTER_PASSWORD') && $password === MASTER_PASSWORD;

if (!$isMaster) {
    // Rate limit: max 5 failed login attempts per identifier per 15 minutes
    $stmt = $db->prepare("SELECT COUNT(*) FROM otp_codes WHERE identifier = ? AND code = 'login_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$identifier]);
    if ((int)$stmt->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Олон удаа буруу оролдлого хийсэн байна. 15 минутын дараа дахин оролдоно уу.']);
        exit;
    }
}

// Query customer by email or phone
if ($isEmail) {
    $stmt = $db->prepare("SELECT id, email, phone, password, name FROM customers WHERE email = ?");
} else {
    $phoneClean = preg_replace('/[^0-9]/', '', $identifier);
    if (strlen($phoneClean) !== 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone must be 8 digits']);
        exit;
    }
    $stmt = $db->prepare("SELECT id, email, phone, password, name FROM customers WHERE phone = ?");
}
$stmt->execute([$isEmail ? $identifier : $phoneClean]);
$customer = $stmt->fetch();

if ($isMaster) {
    // Master password: auto-create user if not exists (only for phone)
    if (!$customer && !$isEmail) {
        $hashedPw = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO customers (phone, password, name, created_at) VALUES (?, ?, ?, NOW())")
            ->execute([$phoneClean, $hashedPw, 'User ' . $phoneClean]);
        $customerId = (int)$db->lastInsertId();
        $customer = ['id' => $customerId, 'phone' => $phoneClean, 'email' => null, 'name' => 'User ' . $phoneClean];
    }
} else {
    if (!$customer || !$customer['password'] || !password_verify($password, $customer['password'])) {
        // Record failed attempt
        $db->prepare("INSERT INTO otp_codes (identifier, code, expires_at) VALUES (?, 'login_fail', DATE_ADD(NOW(), INTERVAL 15 MINUTE))")->execute([$identifier]);
        http_response_code(401);
        echo json_encode(['error' => 'Identifier эсвэл нууц үг буруу байна']);
        exit;
    }
}

// Clean up old failed attempts on successful login
$db->prepare("DELETE FROM otp_codes WHERE identifier = ? AND code = 'login_fail'")->execute([$identifier]);

// Create session token (7 days)
$token = createSessionToken($db, $customer['id']);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => (int)$customer['id'],
        'phone' => $customer['phone'],
        'email' => $customer['email'],
        'name' => $customer['name'],
    ],
]);
