<?php
/**
 * API: Newsletter subscribe
 * POST /backend/api/subscribe.php
 * Body: { "email": "user@example.com" }
 * Returns: { "success": true, "message": "..." } or { "success": false, "error": "..." }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(trim($input['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Хүчинтэй имэйл хаяг оруулна уу.']);
    exit;
}

$db = getDB();
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// Rate limit: max 5 subscribes per IP in 10 minutes
if ($ip) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE ip_address = ? AND subscribed_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $stmt->execute([$ip]);
    if ((int)$stmt->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Хэт олон удаа оролдлоо. Түр хүлээгээд дахин оролдоно уу.']);
        exit;
    }
}

// Upsert: if email already exists, just reactivate (idempotent)
$stmt = $db->prepare("SELECT id, is_active FROM newsletter_subscribers WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    if ((int)$existing['is_active'] === 1) {
        echo json_encode(['success' => true, 'message' => 'Та энэ имэйлээр аль хэдийн бүртгүүлсэн байна.']);
        exit;
    }
    // Reactivate
    $db->prepare("UPDATE newsletter_subscribers SET is_active = 1, unsubscribed_at = NULL WHERE id = ?")
        ->execute([$existing['id']]);
    echo json_encode(['success' => true, 'message' => 'Дахин бүртгэгдлээ. Баярлалаа!']);
    exit;
}

$db->prepare("INSERT INTO newsletter_subscribers (email, ip_address, user_agent) VALUES (?, ?, ?)")
    ->execute([$email, $ip, $ua]);

echo json_encode(['success' => true, 'message' => 'Бүртгүүлсэнд баярлалаа!']);
