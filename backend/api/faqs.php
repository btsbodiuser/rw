<?php
/**
 * API: Public FAQ list
 * GET /api/faqs.php
 * Returns: { "faqs": [{ id, category, question, answer }, ...] }
 *
 * Only active rows, ordered by category (alphabetical) then sort_order asc.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$db = getDB();

$rows = $db->query("
    SELECT id, category, question, answer
    FROM faqs
    WHERE is_active = 1
    ORDER BY category ASC, sort_order ASC, id ASC
")->fetchAll();

$faqs = array_map(function ($r) {
    return [
        'id' => (int)$r['id'],
        'category' => $r['category'],
        'question' => $r['question'],
        'answer' => $r['answer'],
    ];
}, $rows);

echo json_encode(['faqs' => $faqs], JSON_UNESCAPED_UNICODE);
