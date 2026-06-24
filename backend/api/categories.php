<?php
/**
 * API: Get Categories
 * GET /api/categories.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();

$categories = $db->query("SELECT id, slug, name, name_mn, icon, image 
    FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

foreach ($categories as &$c) {
    $c['id'] = (int)$c['id'];
    if ($c['image'] && !str_starts_with($c['image'], 'http')) {
        $c['image'] = getBasePath() . 'backend/' . $c['image'];
    }
}

echo json_encode(['categories' => $categories]);
