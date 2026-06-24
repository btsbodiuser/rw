<?php
/**
 * API: Logout - invalidate session token
 * POST /api/auth/logout.php
 * Header: Authorization: Bearer <token>
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$token = getBearerToken();

if ($token) {
    $db = getDB();
    $db->prepare("DELETE FROM customer_sessions WHERE token = ?")->execute([$token]);
}

echo json_encode(['success' => true]);
