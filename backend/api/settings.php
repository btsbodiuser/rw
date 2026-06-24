<?php
/**
 * API: Get Public Settings
 * GET /api/settings.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();

$rows = $db->query("SELECT setting_key, setting_value, type FROM settings WHERE is_public = 1")->fetchAll();

$settings = [];
foreach ($rows as $row) {
    $value = $row['setting_value'];
    if ($row['type'] === 'number') {
        $value = is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : $value;
    } elseif ($row['type'] === 'boolean') {
        $value = ($value === '1' || $value === 'true');
    }
    $settings[$row['setting_key']] = $value;
}

echo json_encode(['settings' => $settings]);
