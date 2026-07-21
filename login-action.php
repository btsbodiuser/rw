<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!empty($body['token']) && !empty($body['user'])) {
    // Regenerate the session ID on login (keep the data) so a session ID
    // known/fixed before authentication can't be reused to hijack the
    // now-authenticated session (session fixation).
    session_regenerate_id(true);
    $_SESSION['token']   = $body['token'];
    $_SESSION['user_id'] = $body['user']['id'];
    $_SESSION['user']    = $body['user'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
