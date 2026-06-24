<?php
/**
 * API: Media Upload for permitted customers
 * 
 * POST /api/media-upload.php — Upload an image file
 * 
 * Header: Authorization: Bearer <token>
 * Body:   multipart/form-data with "file" field
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDB();

// ── Auth: require valid customer token ──
$bearerToken = getBearerToken();
if (!$bearerToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$stmt = $db->prepare("
    SELECT c.id FROM customer_sessions s
    JOIN customers c ON c.id = s.customer_id
    WHERE s.token = ? AND s.expires_at > NOW()
");
$stmt->execute([$bearerToken]);
$customer = $stmt->fetch();

if (!$customer) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

$customerId = (int) $customer['id'];

// ── Check permission ──
$permStmt = $db->prepare("SELECT 1 FROM product_entry_permissions WHERE customer_id = ?");
$permStmt->execute([$customerId]);
if (!$permStmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to upload media']);
    exit;
}

// ── Upload ──
if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

// Reuse the existing uploadMedia function from functions.php
// It returns an array — we need to echo it as JSON
$result = uploadMedia($_FILES['file']);

if (isset($result['error'])) {
    http_response_code(400);
}

echo json_encode($result);
