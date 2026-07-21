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

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name  = trim($input['name'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
    $email = trim($input['email'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Нэрээ зөв оруулна уу']);
        exit;
    }
    if ($phone !== '' && strlen($phone) !== 8) {
        http_response_code(400);
        echo json_encode(['error' => '8 оронтой утасны дугаар оруулна уу']);
        exit;
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'И-мэйл хаяг буруу байна']);
        exit;
    }

    if ($phone !== '') {
        $stmt = $db->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $customer['id']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Энэ утасны дугаар өөр хэрэглэгчид бүртгэлтэй байна']);
            exit;
        }
    }
    if ($email !== '') {
        $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
        $stmt->execute([$email, $customer['id']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Энэ и-мэйл хаяг өөр хэрэглэгчид бүртгэлтэй байна']);
            exit;
        }
    }

    $db->prepare("UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?")
        ->execute([$name, $phone !== '' ? $phone : null, $email !== '' ? $email : null, $customer['id']]);

    $customer['name']  = $name;
    $customer['phone'] = $phone !== '' ? $phone : null;
    $customer['email'] = $email !== '' ? $email : null;
}

echo json_encode([
    'success' => true,
    'user' => [
        'id' => (int)$customer['id'],
        'phone' => $customer['phone'],
        'name' => $customer['name'],
        'email' => $customer['email'] ?: null,
        'google_connected' => !empty($customer['google_id']),
        'facebook_connected' => !empty($customer['facebook_id']),
    ],
]);
