<?php
/**
 * API: Customer Addresses
 * GET    /api/addresses.php       — list addresses (auth required)
 * POST   /api/addresses.php       — create address (auth required)
 * DELETE /api/addresses.php?id=X  — delete address (auth required)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Auth: require customer token ──
$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT c.id FROM customer_sessions s
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

$customerId = (int)$customer['id'];

// ── GET: list addresses ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT a.*, d.name_mn as district_name,
               COALESCE(k.number, '') as khoroo_number,
               COALESCE(k.name, '') as khoroo_name
        FROM customer_addresses a
        LEFT JOIN districts d ON d.id = a.district_id
        LEFT JOIN khoroos k ON k.id = a.khoroo_id
        WHERE a.customer_id = ?
        ORDER BY a.is_default DESC, a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$customerId]);
    $addresses = $stmt->fetchAll();

    echo json_encode(['addresses' => $addresses]);
    exit;
}

// ── POST: create address ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $errors = [];
    if (empty($input['district_id'])) $errors[] = 'District is required';
    if (empty($input['khoroo_id'])) $errors[] = 'Khoroo is required';
    if (empty(trim($input['address'] ?? ''))) $errors[] = 'Address is required';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    $isDefault = !empty($input['is_default']) ? 1 : 0;

    // If setting as default, unset other defaults
    if ($isDefault) {
        $db->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")->execute([$customerId]);
    }

    // If first address, make it default
    $countStmt = $db->prepare("SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?");
    $countStmt->execute([$customerId]);
    if ((int)$countStmt->fetchColumn() === 0) {
        $isDefault = 1;
    }

    $stmt = $db->prepare("
        INSERT INTO customer_addresses (customer_id, label, district_id, khoroo_id, address, detail_address, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $customerId,
        trim($input['label'] ?? ''),
        (int)$input['district_id'],
        (int)$input['khoroo_id'],
        trim($input['address']),
        trim($input['detail_address'] ?? ''),
        $isDefault,
    ]);

    $newId = $db->lastInsertId();

    // Return the created address
    $stmt = $db->prepare("
        SELECT a.*, d.name_mn as district_name,
               COALESCE(k.number, '') as khoroo_number,
               COALESCE(k.name, '') as khoroo_name
        FROM customer_addresses a
        LEFT JOIN districts d ON d.id = a.district_id
        LEFT JOIN khoroos k ON k.id = a.khoroo_id
        WHERE a.id = ?
    ");
    $stmt->execute([$newId]);

    echo json_encode(['success' => true, 'address' => $stmt->fetch()]);
    exit;
}

// ── PUT: update address ──
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Address ID is required']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $stmt->execute([$id, $customerId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Address not found']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $errors = [];
    if (empty($input['district_id'])) $errors[] = 'District is required';
    if (empty($input['khoroo_id'])) $errors[] = 'Khoroo is required';
    if (empty(trim($input['address'] ?? ''))) $errors[] = 'Address is required';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    $isDefault = !empty($input['is_default']) ? 1 : 0;

    // If setting as default, unset other defaults
    if ($isDefault) {
        $db->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")->execute([$customerId]);
    }

    $db->prepare("
        UPDATE customer_addresses
        SET label = ?, district_id = ?, khoroo_id = ?, address = ?, detail_address = ?, is_default = ?
        WHERE id = ? AND customer_id = ?
    ")->execute([
        trim($input['label'] ?? ''),
        (int)$input['district_id'],
        (int)$input['khoroo_id'],
        trim($input['address']),
        trim($input['detail_address'] ?? ''),
        $isDefault,
        $id,
        $customerId,
    ]);

    // If no default remains (edge case: unset the only default), keep at least one default
    $countStmt = $db->prepare("SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ? AND is_default = 1");
    $countStmt->execute([$customerId]);
    if ((int)$countStmt->fetchColumn() === 0) {
        $db->prepare("UPDATE customer_addresses SET is_default = 1 WHERE id = ?")->execute([$id]);
    }

    $stmt = $db->prepare("
        SELECT a.*, d.name_mn as district_name,
               COALESCE(k.number, '') as khoroo_number,
               COALESCE(k.name, '') as khoroo_name
        FROM customer_addresses a
        LEFT JOIN districts d ON d.id = a.district_id
        LEFT JOIN khoroos k ON k.id = a.khoroo_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'address' => $stmt->fetch()]);
    exit;
}

// ── DELETE: remove address ──
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Address ID is required']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $stmt->execute([$id, $customerId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Address not found']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
