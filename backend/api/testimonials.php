<?php
/**
 * API: Public testimonials list
 * GET /backend/api/testimonials.php
 * Returns: { "testimonials": [{ id, customer_name, customer_avatar, rating, title, body }, ...] }
 *
 * Only active rows, ordered by sort_order ASC, id ASC.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$db = getDB();

$rows = $db->query("
    SELECT id, customer_name, customer_avatar, rating, title, body
    FROM testimonials
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$testimonials = array_map(function ($r) {
    return [
        'id'              => (int)$r['id'],
        'customer_name'   => $r['customer_name'],
        'customer_avatar' => $r['customer_avatar'],
        'rating'          => (int)$r['rating'],
        'title'           => $r['title'],
        'body'            => $r['body'],
    ];
}, $rows);

echo json_encode(['testimonials' => $testimonials], JSON_UNESCAPED_UNICODE);
