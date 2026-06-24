<?php
/**
 * API: Check if phone number exists
 * POST /api/auth/check-phone.php
 * Body: { "phone": "99112233" }
 * Returns: { "exists": true/false }
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

// Rate limit: max 10 checks per IP per minute
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$cacheKey = 'check_phone_' . md5($clientIp);
if (!isset($_SESSION)) { session_start(); session_write_close(); }
$now = time();
$attempts = $_SESSION[$cacheKey] ?? ['count' => 0, 'reset' => $now + 60];
if ($now > $attempts['reset']) {
    $attempts = ['count' => 0, 'reset' => $now + 60];
}
$attempts['count']++;
$_SESSION[$cacheKey] = $attempts;
if ($attempts['count'] > 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Try again later.']);
    exit;
}

$stmt = $db->prepare("SELECT id, password FROM customers WHERE phone = ?");
$stmt->execute([$phone]);
$customer = $stmt->fetch();

echo json_encode([
    'exists' => (bool)$customer,
    'has_password' => (bool)($customer['password'] ?? null),
]);
