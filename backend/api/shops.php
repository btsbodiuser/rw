<?php
/**
 * API: Get Shops
 * GET /api/shops.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();

$shops = $db->query("SELECT s.id, s.slug, s.name, s.name_mn, s.description, s.description_mn, s.color, s.logo,
    GROUP_CONCAT(c.slug) as category_slugs
    FROM shops s
    LEFT JOIN shop_categories sc ON s.id = sc.shop_id
    LEFT JOIN categories c ON sc.category_id = c.id
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY s.sort_order")->fetchAll();

foreach ($shops as &$shop) {
    $shop['id'] = (int)$shop['id'];
    $shop['categories'] = $shop['category_slugs'] ? explode(',', $shop['category_slugs']) : [];
    unset($shop['category_slugs']);
    if ($shop['logo'] && !str_starts_with($shop['logo'], 'http')) {
        $shop['logo'] = getBasePath() . 'backend/' . $shop['logo'];
    }
}

echo json_encode(['shops' => $shops]);
