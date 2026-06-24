<?php
/**
 * API: Register new user with email verification
 * POST /api/auth/register-email.php
 * Body: { "email": "user@example.com", "name": "User Name", "phone": "99112233", "password": "..." }
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

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$name = trim($input['name'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

if (empty($name) || strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Name must be at least 2 characters']);
    exit;
}

if (empty($phone) || strlen($phone) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone must be at least 8 characters']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    exit;
}

// Check if email already exists
$stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered']);
    exit;
}

try {
    $db->beginTransaction();

    // Create customer
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO customers (email, name, phone, password, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$email, $name, $phone, $hashedPassword]);
    $customerId = $db->lastInsertId();

    // Create session token
    $token = createSessionToken($db, $customerId);

    $db->commit();

    auditLog('register', 'customer', $customerId, 'customer', $customerId, ['method' => 'email']);

    echo json_encode([
        'message' => 'Registration successful',
        'token' => $token,
        'user' => [
            'id' => $customerId,
            'email' => $email,
            'name' => $name,
            'phone' => $phone
        ]
    ]);
} catch (Exception $e) {
    $db->rollBack();
    error_log('Registration failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again.']);
}
?>