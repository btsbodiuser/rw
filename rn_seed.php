<?php
/**
 * One-off dev seeder: import products parsed from RunnersNeed mens page 1
 * (rn_products.json) into the local `products` table. Prices are converted
 * from GBP to MNT using GBP_MNT_RATE below.
 *
 * NOT for production. Data is copyrighted by Cotswold Outdoor Group.
 * Delete this file and the seeded rows before deploying.
 */

require __DIR__ . '/includes/config.php';

const GBP_MNT_RATE = 4500;   // rough; adjust as you like
const DEFAULT_STOCK = 50;

$db = getDB();

$src = $argv[1] ?? 'rn_products.json';
$json = file_get_contents(__DIR__ . '/' . $src);
$products = json_decode($json, true);
if (!is_array($products)) {
    fwrite(STDERR, "Failed to load rn_products.json\n");
    exit(1);
}

// Cache shops (brand slug => id)
$shops = [];
foreach ($db->query("SELECT id, slug, name FROM shops")->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $shops[$s['slug']] = (int)$s['id'];
    // also index by lower name for fallback
    $shops[strtolower(str_replace(' ', '-', $s['name']))] = (int)$s['id'];
}

// Cache categories (slug => id)
$cats = [];
foreach ($db->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cats[$c['slug']] = (int)$c['id'];
}

$catMap = [
    'Shoes'       => 'shoes',
    'Socks'       => 'socks',
    'Sunglasses'  => 'sunglasses',
    'Gloves'      => 'accessories',
    'Tops'        => 'tops',
    'Bras'        => 'bras',
    'Jackets'     => 'jackets',
    'Shorts'      => 'shorts',
    'Tights'      => 'tights',
    'Leggings'    => 'leggins',
    'Headwear'    => 'caps',       // headbands go to 'bands' below when detected
    'Packs'       => 'packs',
    'Accessories' => 'accessories',
    'Care'        => 'accessories',
    'Other'       => 'accessories',
];

$slugToBrandKey = function (string $brand): string {
    return strtolower(str_replace([' ', "'"], ['-', ''], $brand));
};

$genderOf = function (string $slug): string {
    if (str_contains($slug, '-mens-'))   return 'men';
    if (str_contains($slug, '-womens-')) return 'women';
    if (str_contains($slug, '-kids-'))   return 'kids';
    return 'unisex';
};

$categoryOf = function (string $slug, string $rough) use ($cats, $catMap): int {
    // Special-case subcategories inside "Shoes"
    if ($rough === 'Shoes') {
        if (str_contains($slug, 'trail'))  return $cats['trail']  ?? $cats['shoes'];
        if (str_contains($slug, 'race'))   return $cats['race']   ?? $cats['shoes'];
        return $cats['road'] ?? $cats['shoes'];
    }
    if ($rough === 'Headwear' && str_contains($slug, 'headband')) return $cats['bands'] ?? $cats['accessories'];
    $target = $catMap[$rough] ?? 'accessories';
    return $cats[$target] ?? $cats['accessories'];
};

$roundMnt = function (float $gbp): int {
    return (int)(round($gbp * GBP_MNT_RATE / 100) * 100);
};

$existing = $db->query("SELECT slug FROM products")->fetchAll(PDO::FETCH_COLUMN);
$existing = array_flip($existing);

$db->beginTransaction();
$ins = $db->prepare("
    INSERT INTO products
        (name, name_mn, slug, category_id, gender, shop_id, type,
         price, original_price, image, description, description_mn,
         stock, has_variants, is_active, show_in_store, created_at, updated_at)
    VALUES
        (:name, :name_mn, :slug, :category_id, :gender, :shop_id, 'ready',
         :price, :original_price, :image, :description, :description_mn,
         :stock, 0, 1, 1, NOW(), NOW())
");

$inserted = 0;
$skipped = 0;

foreach ($products as $p) {
    $slug = $p['slug'];
    if (isset($existing[$slug])) { $skipped++; continue; }

    $brandKey = $slugToBrandKey($p['brand']);
    $shopId = $shops[$brandKey] ?? null;
    if (!$shopId) {
        // Create shop on the fly
        $stmt = $db->prepare("INSERT INTO shops (slug, name, name_mn, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, 1, 0, NOW(), NOW())");
        $stmt->execute([$brandKey, $p['brand'], $p['brand']]);
        $shopId = (int)$db->lastInsertId();
        $shops[$brandKey] = $shopId;
        fwrite(STDOUT, "Created shop: {$p['brand']}\n");
    }

    $catId = $categoryOf($slug, $p['category']);
    $gender = $genderOf($slug);
    $img = $p['image'];
    if ($img && str_starts_with($img, '//')) $img = 'https:' . $img;

    $displayName = trim($p['brand'] . ' ' . $p['name']);
    $priceMnt = $roundMnt((float)$p['price_gbp']);
    $rrpMnt = $p['rrp_gbp'] !== null ? $roundMnt((float)$p['rrp_gbp']) : null;

    $ins->execute([
        ':name'           => $displayName,
        ':name_mn'        => $displayName,
        ':slug'           => $slug,
        ':category_id'    => $catId,
        ':gender'         => $gender,
        ':shop_id'        => $shopId,
        ':price'          => $priceMnt,
        ':original_price' => $rrpMnt,
        ':image'          => $img,
        ':description'    => 'Imported from runnersneed.com for local development.',
        ':description_mn' => 'Runnersneed.com-оос авсан туршилтын бараа.',
        ':stock'          => DEFAULT_STOCK,
    ]);
    $inserted++;
    echo "  + {$displayName} ({$priceMnt}₮)\n";
}

$db->commit();
echo "\nDone. Inserted: {$inserted}, skipped (already existed): {$skipped}\n";
