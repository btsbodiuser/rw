<?php
/**
 * API: Get current user from token
 * GET /api/auth/me.php
 * Header: Authorization: Bearer <token>
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$token = getBearerToken();

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'No token provided']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT c.id, c.phone, c.name, c.email, c.google_id, c.facebook_id
    FROM customer_sessions s
    JOIN customers c ON c.id = s.customer_id
    WHERE s.token = ? AND s.expires_at > NOW()
");
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

echo json_encode([
    'user' => [
        'id' => (int)$customer['id'],
        'phone' => $customer['phone'],
        'name' => $customer['name'],
        'email' => $customer['email'] ?: null,
        'google_connected' => !empty($customer['google_id']),
        'facebook_connected' => !empty($customer['facebook_id']),
    ],
]);
