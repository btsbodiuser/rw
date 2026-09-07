<?php
/**
 * Backfill facet pivots (shoe_type / run_type / cushioning / gait / technical
 * features) AND generate colour+size variants for products that were imported
 * from runnersneed.com. Heuristics parse the slug/name — see maps below.
 *
 * Only touches rows tagged with the seeder's description marker so it can't
 * corrupt hand-curated products.
 *
 * Idempotent: uses INSERT IGNORE on unique pivot rows and skips products
 * that already have variants.
 */

require __DIR__ . '/includes/config.php';
$db = getDB();

// Lookup maps
$byId = fn(array $rows, string $key) => array_column($rows, 'id', $key);
$idBySlug = [
    'shoe_type'  => $byId($db->query("SELECT id, slug FROM shoe_types")->fetchAll(PDO::FETCH_ASSOC),         'slug'),
    'run_type'   => $byId($db->query("SELECT id, slug FROM run_types")->fetchAll(PDO::FETCH_ASSOC),          'slug'),
    'cushioning' => $byId($db->query("SELECT id, slug FROM cushionings")->fetchAll(PDO::FETCH_ASSOC),        'slug'),
    'gait'       => $byId($db->query("SELECT id, slug FROM gait_types")->fetchAll(PDO::FETCH_ASSOC),         'slug'),
    'feature'    => $byId($db->query("SELECT id, slug FROM technical_features")->fetchAll(PDO::FETCH_ASSOC), 'slug'),
];
$colorIdByName = $byId($db->query("SELECT id, LOWER(name) AS name FROM product_colors")->fetchAll(PDO::FETCH_ASSOC), 'name');
$sizeIdByGroupName = [];
foreach ($db->query("SELECT id, name, size_group FROM product_sizes")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $sizeIdByGroupName[$r['size_group']][strtolower($r['name'])] = (int)$r['id'];
}

// Load imported products with category so we know which are shoes vs clothing
$rows = $db->query("
    SELECT p.id, p.name, p.slug, p.gender, c.slug AS cat_slug, pc.slug AS parent_slug
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN categories pc ON pc.id = c.parent_id
    WHERE p.description = 'Imported from runnersneed.com for local development.'
")->fetchAll(PDO::FETCH_ASSOC);

$insertPivot = function (string $table, string $col, int $pid, ?int $tid) use ($db): bool {
    if (!$tid) return false;
    static $prepared = [];
    $key = "$table|$col";
    if (!isset($prepared[$key])) {
        $prepared[$key] = $db->prepare("INSERT IGNORE INTO $table (product_id, $col) VALUES (?, ?)");
    }
    return $prepared[$key]->execute([$pid, $tid]);
};

$statsPivot = ['shoe_type' => 0, 'run_type' => 0, 'cushioning' => 0, 'gait' => 0, 'feature' => 0];
$statsVariants = 0;

// Variant defaults per category
$shoeSizesUK   = ['38', '39', '40', '41', '42', '43', '44', '45'];
$clothingSizes = ['S', 'M', 'L', 'XL'];

// Product-name-based colour guesses (very rough — used only when name mentions one)
$colourKeywords = [
    'black'  => 'black', 'white'  => 'white', 'red' => 'red', 'blue' => 'blue',
    'navy'   => 'navy',  'green'  => 'green', 'yellow' => 'yellow', 'pink' => 'pink',
    'purple' => 'purple', 'orange' => 'orange', 'brown' => 'brown', 'grey' => 'gray',
    'gray'   => 'gray',  'beige'  => 'beige', 'stellar' => 'gray',
];

$vIns = $db->prepare("INSERT INTO product_variants (product_id, color_id, size_id, sku, price_override, stock, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, ?, 1, NOW(), NOW())");
$flagVariants = $db->prepare("UPDATE products SET has_variants = 1 WHERE id = ?");
$existingVariantPids = array_flip(array_map('intval', $db->query("SELECT DISTINCT product_id FROM product_variants")->fetchAll(PDO::FETCH_COLUMN)));

$db->beginTransaction();

foreach ($rows as $p) {
    $slug = $p['slug'];
    $name = strtolower($p['name']);
    $catSlug    = $p['cat_slug'];
    $parentSlug = $p['parent_slug'];
    $isShoe     = $parentSlug === 'shoes' || $catSlug === 'shoes';
    $isCloth    = $parentSlug === 'clothes' || $catSlug === 'clothes';

    // ── shoe_type ────────────────────────────────────────────────
    if ($isShoe) {
        $st = 'road';
        if (str_contains($slug, 'trail') || str_contains($name, 'trail')
            || str_contains($slug, 'speedgoat') || str_contains($slug, 'kjerag')
            || str_contains($slug, 'tecton') || str_contains($slug, 'ultra-glide')
            || str_contains($slug, 'trabuco') || str_contains($slug, 'sense')) $st = 'trail';
        elseif (str_contains($slug, 'race') || str_contains($slug, 'metaspeed')
            || str_contains($slug, 'endorphin-elite') || str_contains($slug, 'vaporfly')
            || str_contains($slug, 'alphafly')) $st = 'race';
        elseif (str_contains($slug, 'lightweight') || str_contains($slug, 'aero-')
            || str_contains($slug, 'novablast')) $st = 'lightweight';
        if ($insertPivot('product_shoe_types', 'shoe_type_id', $p['id'], $idBySlug['shoe_type'][$st] ?? null)) $statsPivot['shoe_type']++;

        // ── run_type ───────────────────────────────────────────────
        $rt = 'daily-run';
        if (str_contains($slug, 'race') || str_contains($slug, 'metaspeed') || str_contains($slug, 'vaporfly')) $rt = 'race-run';
        elseif (str_contains($slug, 'tempo') || str_contains($slug, 'endorphin-speed')) $rt = 'tempo-run';
        elseif (str_contains($slug, 'long') || str_contains($slug, 'ultra')
            || str_contains($slug, 'glycerin') || str_contains($slug, 'nimbus')
            || str_contains($slug, 'cumulus') || str_contains($slug, 'clifton')
            || str_contains($slug, '1080')) $rt = 'long-run';
        if ($insertPivot('product_run_types', 'run_type_id', $p['id'], $idBySlug['run_type'][$rt] ?? null)) $statsPivot['run_type']++;

        // ── cushioning ────────────────────────────────────────────
        $cu = 'balanced';
        if (str_contains($slug, 'max') || str_contains($slug, 'nimbus')
            || str_contains($slug, 'glycerin') || str_contains($slug, 'clifton')
            || str_contains($slug, 'bondi') || str_contains($slug, '1080')) $cu = 'max';
        elseif (str_contains($slug, 'novablast') || str_contains($slug, 'endorphin')
            || str_contains($slug, 'aero') || str_contains($slug, 'race')
            || str_contains($slug, 'metaspeed')) $cu = 'responsive';
        if ($insertPivot('product_cushionings', 'cushioning_id', $p['id'], $idBySlug['cushioning'][$cu] ?? null)) $statsPivot['cushioning']++;

        // ── gait ──────────────────────────────────────────────────
        $ga = 'neutral';
        if (str_contains($slug, 'gts') || str_contains($slug, 'kayano')
            || str_contains($slug, 'adrenaline') || str_contains($slug, 'gt-2000')
            || str_contains($slug, 'gt-')) $ga = 'stability';
        if ($insertPivot('product_gait_types', 'gait_type_id', $p['id'], $idBySlug['gait'][$ga] ?? null)) $statsPivot['gait']++;
    }

    // ── features (works for shoes AND clothing/accessories) ───────
    $features = [];
    if (str_contains($slug, 'gtx') || str_contains($slug, 'gore-tex') || str_contains($slug, 'waterproof')) $features[] = 'waterproof';
    if (str_contains($slug, 'wind') || str_contains($slug, 'stormshell'))                                   $features[] = 'windproof';
    if (str_contains($slug, 'insulated') || str_contains($slug, 'fleece') || str_contains($slug, 'jumper')) $features[] = 'insulated';
    if (str_contains($slug, 'lightweight') || str_contains($slug, 'aero-')
        || str_contains($slug, 'ultra-light'))                                                              $features[] = 'lightweight';
    if (str_contains($slug, 'compression') || str_contains($slug, 'tights'))                                $features[] = 'stretch';
    if (str_contains($slug, 't-shirt') || str_contains($slug, 'base-layer')
        || str_contains($slug, 'jersey') || str_contains($slug, 'top'))                                     $features[] = 'moisture-wicking';
    if ($isShoe && !in_array('waterproof', $features))                                                      $features[] = 'breathable';
    foreach (array_unique($features) as $ft) {
        if ($insertPivot('product_technical_features', 'technical_feature_id', $p['id'], $idBySlug['feature'][$ft] ?? null)) $statsPivot['feature']++;
    }

    // ── variants (colour + size) ─────────────────────────────────
    if (isset($existingVariantPids[$p['id']])) continue;
    // Detect colour from name; default Black
    $col = null;
    foreach ($colourKeywords as $kw => $slugCol) {
        if (str_contains($name, $kw)) { $col = $slugCol; break; }
    }
    $col ??= 'black';
    $colorId = $colorIdByName[$col] ?? $colorIdByName['black'] ?? null;
    if (!$colorId) continue;

    $sizeGroup = $isShoe ? 'shoes' : ($isCloth ? 'clothing' : null);
    if ($sizeGroup === null) {
        // Accessories: single-size / one-size row (use size_id NULL)
        $sku = 'RN-' . $p['id'] . '-OS';
        $vIns->execute([$p['id'], $colorId, null, $sku, 25]);
        $statsVariants++;
    } else {
        $names = $sizeGroup === 'shoes' ? $shoeSizesUK : $clothingSizes;
        foreach ($names as $sn) {
            $sizeId = $sizeIdByGroupName[$sizeGroup][strtolower($sn)] ?? null;
            if (!$sizeId) continue;
            $sku = 'RN-' . $p['id'] . '-' . strtoupper($sn);
            $vIns->execute([$p['id'], $colorId, $sizeId, $sku, 5]);
            $statsVariants++;
        }
    }
    $flagVariants->execute([$p['id']]);
}

$db->commit();

echo "Backfilled pivots: " . json_encode($statsPivot) . PHP_EOL;
echo "New variants inserted: $statsVariants" . PHP_EOL;
echo "Products touched: " . count($rows) . PHP_EOL;
