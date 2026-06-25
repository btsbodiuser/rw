<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!empty($body['token']) && !empty($body['user'])) {
    $_SESSION['token']   = $body['token'];
    $_SESSION['user_id'] = $body['user']['id'];
    $_SESSION['user']    = $body['user'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
