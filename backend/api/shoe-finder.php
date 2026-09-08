<?php
/**
 * AI Shoe Finder — POST /api/shoe-finder.php
 *
 * Takes 5 short answers (gender, distance, terrain, gait, budget), hard-filters
 * the shoe catalog in SQL, sends a compact candidate list to Claude, and returns
 * the top 3–5 recommendations with a Mongolian reasoning line each.
 *
 * The Anthropic API key is a private setting (is_public=0) — never touched by
 * the frontend getSettings() cache. Raw HTTP curl matches the codebase pattern
 * used by QPay/Bonum/StorePay integrations (no composer dep).
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/error-logger.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$apiKey = trim((string) getSetting('anthropic_api_key', ''));
$model  = trim((string) getSetting('anthropic_model',   'claude-haiku-4-5'));
if ($apiKey === '') {
    http_response_code(503);
    echo json_encode(['error' => 'AI service not configured']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$gender   = in_array($input['gender']   ?? '', ['men','women'],                                            true) ? $input['gender']   : null;
$distance = in_array($input['distance'] ?? '', ['under_5k','5_15k','half','full','trail'],                 true) ? $input['distance'] : null;
$terrain  = in_array($input['terrain']  ?? '', ['road','soft','trail','mixed'],                            true) ? $input['terrain']  : null;
$gait     = in_array($input['gait']     ?? '', ['neutral','overpronation','underpronation','unknown'],     true) ? $input['gait']     : 'unknown';
$budget   = in_array($input['budget']   ?? '', ['under_200k','200_400k','400_600k','over_600k','any'],     true) ? $input['budget']   : 'any';

if (!$gender || !$distance || !$terrain) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required answers']);
    exit;
}

$priceBands = [
    'under_200k' => [0,        200000],
    '200_400k'   => [200000,   400000],
    '400_600k'   => [400000,   600000],
    'over_600k'  => [600000,   99999999],
    'any'        => [0,        99999999],
];
[$minPrice, $maxPrice] = $priceBands[$budget];

$db = getDB();

// Shoe category tree: parent id 38 (Пүүз) + its children.
$catRows = $db->query("SELECT id FROM categories WHERE id = 38 OR parent_id = 38")->fetchAll(PDO::FETCH_COLUMN);
if (!$catRows) {
    echo json_encode(['recommendations' => [], 'error' => 'No shoe category configured']);
    exit;
}
$catPlaceholders = implode(',', array_fill(0, count($catRows), '?'));

// Hard filter: gender + price band + in-stock + shoe category. Include unisex
// so unisex products surface for both genders.
$sql = "
    SELECT p.id, p.name, p.name_mn, p.slug, p.price, p.gender, p.weight_kg,
           p.description_mn, p.image, p.rating, p.reviews,
           s.name AS shop_name
    FROM products p
    LEFT JOIN shops s ON s.id = p.shop_id
    WHERE p.show_in_store = 1
      AND p.is_active = 1
      AND (p.stock IS NULL OR p.stock > 0)
      AND p.category_id IN ($catPlaceholders)
      AND (p.gender = ? OR p.gender = 'unisex')
      AND p.price BETWEEN ? AND ?
    ORDER BY p.rating DESC, p.reviews DESC
    LIMIT 30
";
$params = array_merge($catRows, [$gender, $minPrice, $maxPrice]);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

if (!$candidates) {
    echo json_encode([
        'recommendations' => [],
        'message' => 'Таны шалгуурт тохирох гутал олдсонгүй. Төсөв эсвэл шалгуураа өөрчилж үзнэ үү.',
    ]);
    exit;
}

// Attach running-attribute tags per candidate for the model to reason over.
$ids = array_column($candidates, 'id');
$idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
$tagQueries = [
    'shoe_types'   => "SELECT pst.product_id, st.slug FROM product_shoe_types pst JOIN shoe_types st ON st.id = pst.shoe_type_id WHERE pst.product_id IN ($idPlaceholders)",
    'run_types'    => "SELECT prt.product_id, rt.slug FROM product_run_types prt JOIN run_types rt ON rt.id = prt.run_type_id WHERE prt.product_id IN ($idPlaceholders)",
    'cushionings'  => "SELECT pc.product_id, c.slug  FROM product_cushionings pc  JOIN cushionings c  ON c.id  = pc.cushioning_id  WHERE pc.product_id IN ($idPlaceholders)",
    'gaits'        => "SELECT pgt.product_id, gt.slug FROM product_gait_types pgt JOIN gait_types gt ON gt.id = pgt.gait_type_id WHERE pgt.product_id IN ($idPlaceholders)",
    'features'     => "SELECT ptf.product_id, tf.slug FROM product_technical_features ptf JOIN technical_features tf ON tf.id = ptf.technical_feature_id WHERE ptf.product_id IN ($idPlaceholders)",
];
$tags = [];
foreach ($ids as $id) {
    $tags[$id] = ['shoe_types' => [], 'run_types' => [], 'cushionings' => [], 'gaits' => [], 'features' => []];
}
foreach ($tagQueries as $key => $q) {
    $ts = $db->prepare($q);
    $ts->execute($ids);
    foreach ($ts->fetchAll() as $row) {
        $tags[$row['product_id']][$key][] = $row['slug'];
    }
}

// Compact catalog: only the fields the model needs to rank + cite.
$catalog = [];
foreach ($candidates as $c) {
    $catalog[] = [
        'id'          => (int) $c['id'],
        'name'        => $c['name_mn'] ?: $c['name'],
        'brand'       => $c['shop_name'] ?: '',
        'price'       => (int) $c['price'],
        'gender'      => $c['gender'],
        'weight_g'    => $c['weight_kg'] ? (int) round((float) $c['weight_kg'] * 1000) : null,
        'rating'      => (float) $c['rating'],
        'reviews'     => (int) $c['reviews'],
        'shoe_types'  => $tags[$c['id']]['shoe_types'],
        'run_types'   => $tags[$c['id']]['run_types'],
        'cushioning'  => $tags[$c['id']]['cushionings'],
        'gait'        => $tags[$c['id']]['gaits'],
        'features'    => $tags[$c['id']]['features'],
    ];
}

$userAnswers = [
    'gender'   => $gender,
    'distance' => $distance,
    'terrain'  => $terrain,
    'gait'     => $gait,
    'budget'   => $budget,
];

$systemPrompt = <<<'PROMPT'
Та Runner's World-ийн гүйлтийн гутлын мэргэжилтэн. Хэрэглэгчийн хариултад тохирсон 3-5 гутлыг өгөгдсөн каталогоос сонгож санал болгоно уу.

Дүрэм:
- Зөвхөн доорх JSON каталогт байгаа `id`-г ашиглана уу. Шинэ гутал зохиох ЁСГҮЙ.
- Хэрэглэгчийн distance, terrain, gait, cushioning-д тохирохыг эрэмбэлнэ.
- reason_mn нь 1-2 өгүүлбэрээр, ЯАГААД тохирохыг Монголоор тайлбарлана (жишээ: "Уулын жимд зориулагдсан Vibram улаар нь тав тухтай").
- Хамгийн тохирох 3-5 гутлыг л сонгоно. Хангалттай тохироогүй бол цөөн гутал сонгосон ч болно.

JSON форматаар л хариулна уу.
PROMPT;

$userMessage = "Хэрэглэгчийн хариултууд:\n" . json_encode($userAnswers, JSON_UNESCAPED_UNICODE)
    . "\n\nКаталог:\n" . json_encode($catalog, JSON_UNESCAPED_UNICODE);

$requestBody = [
    'model'      => $model,
    'max_tokens' => 1024,
    'system'     => $systemPrompt,
    'messages'   => [
        ['role' => 'user', 'content' => $userMessage],
    ],
    'output_config' => [
        'format' => [
            'type'   => 'json_schema',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'recommendations' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'product_id' => ['type' => 'integer'],
                                'reason_mn'  => ['type' => 'string'],
                            ],
                            'required' => ['product_id', 'reason_mn'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['recommendations'],
                'additionalProperties' => false,
            ],
        ],
    ],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'x-api-key: '        . $apiKey,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT    => 30,
]);
$raw  = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    logError('error', 'ai_shoe_finder', 'Anthropic curl failed', ['curl_error' => $err]);
    http_response_code(502);
    echo json_encode(['error' => 'AI service unreachable']);
    exit;
}

$response = json_decode($raw, true);
if ($code !== 200 || !is_array($response)) {
    logError('error', 'ai_shoe_finder', 'Anthropic non-200', ['http_code' => $code, 'body' => substr($raw, 0, 500)]);
    http_response_code(502);
    echo json_encode(['error' => 'AI service error']);
    exit;
}

// Extract the first text block — structured outputs still land in a text block.
$assistantText = '';
foreach ($response['content'] ?? [] as $block) {
    if (($block['type'] ?? '') === 'text') {
        $assistantText = $block['text'] ?? '';
        break;
    }
}

$parsed = json_decode($assistantText, true);
$recs = is_array($parsed['recommendations'] ?? null) ? $parsed['recommendations'] : [];

// Enrich each recommendation with the full product data so the frontend can
// render a card without a second round-trip.
$byId = [];
foreach ($candidates as $c) $byId[(int) $c['id']] = $c;

$out = [];
foreach ($recs as $rec) {
    $pid = (int) ($rec['product_id'] ?? 0);
    if (!isset($byId[$pid])) continue;
    $p = $byId[$pid];
    $imgUrl = null;
    if (!empty($p['image'])) {
        $imgUrl = str_starts_with($p['image'], 'http') ? $p['image']
                : (str_starts_with($p['image'], '/') ? $p['image']
                : getBasePath() . 'backend/' . ltrim($p['image'], '/'));
    }
    $out[] = [
        'id'        => $pid,
        'name_mn'   => $p['name_mn'] ?: $p['name'],
        'slug'      => $p['slug'],
        'price'     => (float) $p['price'],
        'brand'     => $p['shop_name'] ?: '',
        'image'     => $imgUrl,
        'rating'    => (float) $p['rating'],
        'reviews'   => (int)   $p['reviews'],
        'reason_mn' => (string) ($rec['reason_mn'] ?? ''),
    ];
}

echo json_encode(['recommendations' => $out], JSON_UNESCAPED_UNICODE);
