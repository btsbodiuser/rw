<?php
/**
 * API: Submit contact form message
 * POST /api/contact.php
 * Body: { name, email, phone?, message, website (honeypot, must stay empty), rendered_at (ms timestamp) }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$name    = trim((string)($input['name'] ?? ''));
$email   = trim((string)($input['email'] ?? ''));
$phone   = trim((string)($input['phone'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$website = trim((string)($input['website'] ?? ''));       // honeypot — real users never see/fill this
$renderedAt = (float)($input['rendered_at'] ?? 0);          // client-side ms timestamp from when the form loaded

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// ── Honeypot: bots fill every field, real users never see this one ──
// Fake a normal success response so the bot gets no signal it was caught.
if ($website !== '') {
    echo json_encode(['success' => true]);
    exit;
}

// ── Field validation ──
if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
    http_response_code(400);
    echo json_encode(['error' => 'Нэрээ зөв оруулна уу.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    http_response_code(400);
    echo json_encode(['error' => 'И-мэйл хаягаа зөв оруулна уу.']);
    exit;
}
if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Мессеж хамгийн багадаа 10 тэмдэгт байх ёстой.']);
    exit;
}
if ($phone !== '' && mb_strlen($phone) > 30) {
    $phone = mb_substr($phone, 0, 30);
}

$db = getDB();

// ── Rate limit (session, matches check-phone.php's pattern): max 5/hour ──
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$cacheKey = 'contact_form_' . md5($clientIp);
$now = time();
$attempts = $_SESSION[$cacheKey] ?? ['count' => 0, 'reset' => $now + 3600];
if ($now > $attempts['reset']) {
    $attempts = ['count' => 0, 'reset' => $now + 3600];
}
$attempts['count']++;
$_SESSION[$cacheKey] = $attempts;
if ($attempts['count'] > 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Хэт олон хүсэлт илгээлээ. Түр хүлээгээд дахин оролдоно уу.']);
    exit;
}

// ── Rate limit (DB, IP-based — survives a cleared session cookie): max 5/hour ──
$rlStmt = $db->prepare("SELECT COUNT(*) FROM contact_messages WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$rlStmt->execute([$clientIp]);
if ((int)$rlStmt->fetchColumn() >= 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Хэт олон хүсэлт илгээлээ. Түр хүлээгээд дахин оролдоно уу.']);
    exit;
}

// ── Spam heuristics (flagged, not blocked — stored for admin review either way) ──
$isSpam = false;
// Submitted implausibly fast for a human to have read the form and typed a message.
// A missing/zero timestamp is itself suspicious — real browser JS always sends one;
// only a raw bot POST would omit it, so treat that the same as "too fast".
if ($renderedAt <= 0 || (microtime(true) * 1000 - $renderedAt) < 2000) {
    $isSpam = true;
}
// Link-stuffing is the single most common contact-form spam pattern.
if (preg_match_all('~https?://~i', $message) >= 3) {
    $isSpam = true;
}
// Markup in a plain-text field is never legitimate here.
if ($name !== strip_tags($name) || $message !== strip_tags($message)) {
    $isSpam = true;
}

$stmt = $db->prepare("INSERT INTO contact_messages (name, email, phone, message, ip_address, user_agent, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $name,
    $email,
    $phone !== '' ? $phone : null,
    $message,
    $clientIp,
    mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    $isSpam ? 'spam' : 'new',
]);

// No synchronous email notification here on purpose: an SMTP handshake can
// stall for several seconds (slow host, network filtering, etc.) and this
// visitor is waiting on the response. The message is already durably saved
// and reviewable in the admin panel (backend/pages/contact-messages.php),
// which is the actual notification path for staff.
echo json_encode(['success' => true]);
