<?php
/**
 * API: Public blog posts list
 * GET /backend/api/blog-posts.php
 * GET /backend/api/blog-posts.php?limit=N
 * Returns: { "posts": [{ id, title_mn, title, slug, excerpt_mn, image, published_at }, ...] }
 *
 * Only published rows, ordered by sort_order ASC, published_at DESC.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$db = getDB();

$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;

$stmt = $db->prepare("
    SELECT id, title_mn, title, slug, excerpt_mn, image, published_at
    FROM blog_posts
    WHERE is_published = 1
    ORDER BY sort_order ASC, published_at DESC
    LIMIT ?
");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$posts = array_map(function ($r) {
    return [
        'id'           => (int)$r['id'],
        'title_mn'     => $r['title_mn'],
        'title'        => $r['title'],
        'slug'         => $r['slug'],
        'excerpt_mn'   => $r['excerpt_mn'],
        'image'        => $r['image'],
        'published_at' => $r['published_at'],
    ];
}, $rows);

echo json_encode(['posts' => $posts], JSON_UNESCAPED_UNICODE);
