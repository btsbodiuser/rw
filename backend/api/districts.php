<?php
/**
 * API: Get Districts & Khoroos
 * GET /api/districts.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();

$districts = $db->query("SELECT id, name, name_mn FROM districts WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$khoroos = $db->query("SELECT id, district_id, number, name FROM khoroos ORDER BY district_id, number")->fetchAll();

$khorooMap = [];
foreach ($khoroos as $k) {
    $item = [
        'id' => (int)$k['id'],
        'number' => (int)$k['number'],
    ];
    if (!empty($k['name'])) {
        $item['name'] = $k['name'];
    }
    $khorooMap[(int)$k['district_id']][] = $item;
}

foreach ($districts as &$d) {
    $d['id'] = (int)$d['id'];
    $d['khoroos'] = $khorooMap[$d['id']] ?? [];
}

echo json_encode(['districts' => $districts]);
