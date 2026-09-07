<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');
$db       = getDB();

// ── Page-specific prep ───────────────────────────────────────

// Banner slider(s)
try {
    $sliders = $db->query("SELECT * FROM sliders WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();
} catch (Throwable) { $sliders = []; }

// Parent categories only (for the "Shop By Categories" swiper)
try {
    $homeCategories = $db->query("
        SELECT id, slug, name, name_mn, image
        FROM categories
        WHERE is_active = 1 AND parent_id IS NULL
        ORDER BY sort_order, name_mn
    ")->fetchAll();
} catch (Throwable) { $homeCategories = []; }


/**
 * Render a single product card. Used by both the tab panels below and any
 * future product grids.
 */

// ── SHOP: filter parsing + product query ────────────────────
function csvOrArrayParam(string $key): array {
    $raw = $_GET[$key] ?? '';
    if (is_array($raw)) return array_values(array_filter(array_map('trim', $raw)));
    return array_values(array_filter(array_map('trim', explode(',', (string)$raw))));
}

$_allowedGendersShop = ['men', 'women', 'unisex', 'kids'];
$_allowedSortsShop   = ['newest', 'oldest', 'price_asc', 'price_desc', 'popular'];

$f = [
    'search'     => trim($_GET['search'] ?? ''),
    'category'   => csvOrArrayParam('category'),
    'gender'     => array_values(array_intersect(csvOrArrayParam('gender'), $_allowedGendersShop)),
    'shop'       => csvOrArrayParam('shop'),
    'shoe_type'  => csvOrArrayParam('shoe_type'),
    'run_type'   => csvOrArrayParam('run_type'),
    'cushioning' => csvOrArrayParam('cushioning'),
    'gait'       => csvOrArrayParam('gait'),
    'feature'    => csvOrArrayParam('feature'),
    'discount'   => !empty($_GET['discount']),
    'new'        => !empty($_GET['new']),
    'price_min'  => trim((string)($_GET['price_min'] ?? '')),
    'price_max'  => trim((string)($_GET['price_max'] ?? '')),
    'sort'       => in_array($_GET['sort'] ?? '', $_allowedSortsShop, true) ? $_GET['sort'] : 'newest',
];

$shopPage    = max(1, (int)($_GET['page'] ?? 1));
$shopPerPage = 12;

// Load master lists for the sidebar
try { $allCategories = $db->query("SELECT id, slug, name, name_mn FROM categories WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll(); } catch (Throwable) { $allCategories = []; }
$allBrands       = getShops();
try { $allShoeTypes  = $db->query("SELECT slug, name_mn, name FROM shoe_types WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll(); } catch (Throwable) { $allShoeTypes = []; }
try { $allRunTypes   = $db->query("SELECT slug, name_mn, name FROM run_types WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll(); } catch (Throwable) { $allRunTypes = []; }
try { $allCushionings = $db->query("SELECT slug, name_mn, name FROM cushionings WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll(); } catch (Throwable) { $allCushionings = []; }
try { $allGaitTypes  = $db->query("SELECT slug, name_mn, name FROM gait_types WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll(); } catch (Throwable) { $allGaitTypes = []; }
try { $allFeatures   = $db->query("SELECT slug, name_mn, name FROM technical_features WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll(); } catch (Throwable) { $allFeatures = []; }

$genderLabels = ['men' => 'Эрэгтэй', 'women' => 'Эмэгтэй', 'unisex' => 'Унисекс', 'kids' => 'Хүүхэд'];

// Facet counts — how many active, in-store products carry each filter value.
// (Global counts, independent of the other filters currently applied.)
function shopFacetCounts(PDO $db, string $sql): array {
    try {
        $out = [];
        foreach ($db->query($sql)->fetchAll() as $r) { $out[$r['slug']] = (int)$r['cnt']; }
        return $out;
    } catch (Throwable) { return []; }
}
$catCounts        = shopFacetCounts($db, "SELECT c.slug, COUNT(*) cnt FROM products p JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 AND p.show_in_store = 1 GROUP BY c.slug");
$genderCounts     = shopFacetCounts($db, "SELECT p.gender AS slug, COUNT(*) cnt FROM products p WHERE p.is_active = 1 AND p.show_in_store = 1 AND p.gender IS NOT NULL GROUP BY p.gender");
$brandCounts      = shopFacetCounts($db, "SELECT s.slug, COUNT(*) cnt FROM products p JOIN shops s ON s.id = p.shop_id WHERE p.is_active = 1 AND p.show_in_store = 1 GROUP BY s.slug");
$shoeTypeCounts   = shopFacetCounts($db, "SELECT st.slug, COUNT(DISTINCT p.id) cnt FROM products p JOIN product_shoe_types pst ON pst.product_id = p.id JOIN shoe_types st ON st.id = pst.shoe_type_id WHERE p.is_active = 1 AND p.show_in_store = 1 GROUP BY st.slug");
$runTypeCounts    = shopFacetCounts($db, "SELECT rt.slug, COUNT(DISTINCT p.id) cnt FROM products p JOIN product_run_types prt ON prt.product_id = p.id JOIN run_types rt ON rt.id = prt.run_type_id WHERE p.is_active = 1 AND p.show_in_store = 1 GROUP BY rt.slug");
$cushioningCounts = shopFacetCounts($db, "SELECT cu.slug, COUNT(DISTINCT p.id) cnt FROM products p JOIN product_cushionings pc2 ON pc2.product_id = p.id JOIN cushionings cu ON cu.id = pc2.cushioning_id WHERE p.is_active = 1 AND p.show_in_store = 1 GROUP BY cu.slug");
$gaitCounts       = shopFacetCounts($db, "SELECT gt.slug, COUNT(DISTINCT p.id) cnt FROM products p JOIN product_gait_types pgt ON pgt.product_id = p.id JOIN gait_types gt ON gt.id = pgt.gait_type_id WHERE p.is_active = 1 AND p.show_in_store = 1 GROUP BY gt.slug");
$featureCounts    = shopFacetCounts($db, "SELECT tf.slug, COUNT(DISTINCT p.id) cnt FROM products p JOIN product_technical_features ptf ON ptf.product_id = p.id JOIN technical_features tf ON tf.id = ptf.technical_feature_id WHERE p.is_active = 1 AND p.show_in_store = 1 GROUP BY tf.slug");

// Promo tiles dropped into the product grid (reuses the "shop_top" banner location)
$shopTopBanners = getBannersForLocation('shop_top');

// Label lookups + a URL builder for the "active filters" tag list
$catLabelBySlug   = array_column($allCategories, null, 'slug');
$brandLabelBySlug = array_column($allBrands, null, 'slug');
$shoeTypeLabelBySlug   = array_column($allShoeTypes, null, 'slug');
$runTypeLabelBySlug    = array_column($allRunTypes, null, 'slug');
$cushioningLabelBySlug = array_column($allCushionings, null, 'slug');
$gaitLabelBySlug       = array_column($allGaitTypes, null, 'slug');
$featureLabelBySlug    = array_column($allFeatures, null, 'slug');

function shopChipUrl(array $overrides): string {
    global $f, $urlShop;
    $base = [
        'search'     => $f['search'],
        'category'   => implode(',', $f['category']),
        'gender'     => implode(',', $f['gender']),
        'shop'       => implode(',', $f['shop']),
        'shoe_type'  => implode(',', $f['shoe_type']),
        'run_type'   => implode(',', $f['run_type']),
        'cushioning' => implode(',', $f['cushioning']),
        'gait'       => implode(',', $f['gait']),
        'feature'    => implode(',', $f['feature']),
        'discount'   => $f['discount'] ? 1 : '',
        'new'        => $f['new'] ? 1 : '',
        'price_min'  => $f['price_min'],
        'price_max'  => $f['price_max'],
        'sort'       => $f['sort'] !== 'newest' ? $f['sort'] : '',
    ];
    $q = array_merge($base, $overrides);
    $qs = http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
    return $urlShop . ($qs ? '?' . $qs : '');
}
function shopRemoveChipUrl(string $key, string $value): string {
    global $f;
    return shopChipUrl([$key => implode(',', array_diff($f[$key], [$value]))]);
}

// Build the "active filters" tag list shown above the grid
$activeChips = [];
foreach ($f['category'] as $v) { $activeChips[] = ['label' => $catLabelBySlug[$v]['name_mn'] ?? ($catLabelBySlug[$v]['name'] ?? $v), 'url' => shopRemoveChipUrl('category', $v)]; }
foreach ($f['gender'] as $v)   { $activeChips[] = ['label' => $genderLabels[$v] ?? $v, 'url' => shopRemoveChipUrl('gender', $v)]; }
foreach ($f['shop'] as $v)     { $activeChips[] = ['label' => $brandLabelBySlug[$v]['name_mn'] ?? ($brandLabelBySlug[$v]['name'] ?? $v), 'url' => shopRemoveChipUrl('shop', $v)]; }
foreach ($f['shoe_type'] as $v)  { $activeChips[] = ['label' => $shoeTypeLabelBySlug[$v]['name_mn'] ?? ($shoeTypeLabelBySlug[$v]['name'] ?? $v), 'url' => shopRemoveChipUrl('shoe_type', $v)]; }
foreach ($f['run_type'] as $v)   { $activeChips[] = ['label' => $runTypeLabelBySlug[$v]['name_mn'] ?? ($runTypeLabelBySlug[$v]['name'] ?? $v), 'url' => shopRemoveChipUrl('run_type', $v)]; }
foreach ($f['cushioning'] as $v) { $activeChips[] = ['label' => $cushioningLabelBySlug[$v]['name_mn'] ?? ($cushioningLabelBySlug[$v]['name'] ?? $v), 'url' => shopRemoveChipUrl('cushioning', $v)]; }
foreach ($f['gait'] as $v)       { $activeChips[] = ['label' => $gaitLabelBySlug[$v]['name_mn'] ?? ($gaitLabelBySlug[$v]['name'] ?? $v), 'url' => shopRemoveChipUrl('gait', $v)]; }
foreach ($f['feature'] as $v)    { $activeChips[] = ['label' => $featureLabelBySlug[$v]['name_mn'] ?? ($featureLabelBySlug[$v]['name'] ?? $v), 'url' => shopRemoveChipUrl('feature', $v)]; }
if ($f['discount']) { $activeChips[] = ['label' => 'Хямдралтай', 'url' => shopChipUrl(['discount' => ''])]; }
if ($f['new'])      { $activeChips[] = ['label' => 'Шинэ ирсэн', 'url' => shopChipUrl(['new' => ''])]; }
if ($f['search'] !== '') { $activeChips[] = ['label' => '"' . $f['search'] . '"', 'url' => shopChipUrl(['search' => ''])]; }
if ($f['price_min'] !== '' || $f['price_max'] !== '') { $activeChips[] = ['label' => 'Үнэ: ' . ($f['price_min'] ?: '0') . ' - ' . ($f['price_max'] ?: '∞'), 'url' => shopChipUrl(['price_min' => '', 'price_max' => ''])]; }

// Build WHERE
$where  = ['p.is_active = 1', 'p.show_in_store = 1'];
$params = [];
$_in = fn(array $vals) => '(' . implode(',', array_fill(0, count($vals), '?')) . ')';

if ($f['search'] !== '') {
    $where[] = '(p.name LIKE ? OR p.name_mn LIKE ?)';
    $params[] = '%' . $f['search'] . '%';
    $params[] = '%' . $f['search'] . '%';
}
if ($f['category']) {
    $where[] = 'c.slug IN ' . $_in($f['category']);
    $params = array_merge($params, $f['category']);
}
if ($f['gender']) {
    $where[] = 'p.gender IN ' . $_in($f['gender']);
    $params = array_merge($params, $f['gender']);
}
if ($f['shop']) {
    $where[] = 's.slug IN ' . $_in($f['shop']);
    $params = array_merge($params, $f['shop']);
}
if ($f['shoe_type']) {
    $ph = $_in($f['shoe_type']);
    $where[] = "EXISTS (SELECT 1 FROM product_shoe_types pst JOIN shoe_types st ON st.id = pst.shoe_type_id WHERE pst.product_id = p.id AND st.slug IN $ph)";
    $params = array_merge($params, $f['shoe_type']);
}
if ($f['run_type']) {
    $ph = $_in($f['run_type']);
    $where[] = "EXISTS (SELECT 1 FROM product_run_types prt JOIN run_types rt ON rt.id = prt.run_type_id WHERE prt.product_id = p.id AND rt.slug IN $ph)";
    $params = array_merge($params, $f['run_type']);
}
if ($f['cushioning']) {
    $ph = $_in($f['cushioning']);
    $where[] = "EXISTS (SELECT 1 FROM product_cushionings pc2 JOIN cushionings cu ON cu.id = pc2.cushioning_id WHERE pc2.product_id = p.id AND cu.slug IN $ph)";
    $params = array_merge($params, $f['cushioning']);
}
if ($f['gait']) {
    $ph = $_in($f['gait']);
    $where[] = "EXISTS (SELECT 1 FROM product_gait_types pgt JOIN gait_types gt ON gt.id = pgt.gait_type_id WHERE pgt.product_id = p.id AND gt.slug IN $ph)";
    $params = array_merge($params, $f['gait']);
}
if ($f['feature']) {
    $ph = $_in($f['feature']);
    $where[] = "EXISTS (SELECT 1 FROM product_technical_features ptf JOIN technical_features tf ON tf.id = ptf.technical_feature_id WHERE ptf.product_id = p.id AND tf.slug IN $ph)";
    $params = array_merge($params, $f['feature']);
}
if ($f['discount']) {
    $where[] = 'p.original_price > p.price';
}
if ($f['new']) {
    $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}
if ($f['price_min'] !== '' && is_numeric($f['price_min'])) {
    $where[] = 'p.price >= ?';
    $params[] = (float)$f['price_min'];
}
if ($f['price_max'] !== '' && is_numeric($f['price_max'])) {
    $where[] = 'p.price <= ?';
    $params[] = (float)$f['price_max'];
}

$whereSql = implode(' AND ', $where);

$orderSql = match ($f['sort']) {
    'oldest'     => 'p.created_at ASC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular'    => 'p.rating DESC, p.reviews DESC',
    default      => 'p.created_at DESC',
};

// Count
try {
    $countSql = "SELECT COUNT(DISTINCT p.id)
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN shops s ON s.id = p.shop_id
        WHERE $whereSql";
    $cs = $db->prepare($countSql);
    $cs->execute($params);
    $shopTotalProducts = (int)$cs->fetchColumn();
} catch (Throwable) { $shopTotalProducts = 0; }

$shopTotalPages = max(1, (int)ceil($shopTotalProducts / $shopPerPage));
$shopPage = min($shopPage, $shopTotalPages);
$offset = ($shopPage - 1) * $shopPerPage;

// Rows
try {
    $sql = "SELECT p.id, p.slug, p.name, p.name_mn, p.price, p.original_price,
                   p.image, p.stock, p.rating, p.reviews, p.created_at, p.type,
                   c.slug AS category_slug, c.name_mn AS category_name_mn, c.name AS category_name,
                   s.slug AS shop_slug, s.name_mn AS shop_name_mn, s.name AS shop_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN shops s ON s.id = p.shop_id
            WHERE $whereSql
            ORDER BY $orderSql
            LIMIT $shopPerPage OFFSET $offset";
    $ps = $db->prepare($sql);
    $ps->execute($params);
    $shopProducts = $ps->fetchAll();
} catch (Throwable) { $shopProducts = []; }

$shopSortLabels = [
    'newest'     => 'Шинэ эхэнд',
    'oldest'     => 'Хуучин эхэнд',
    'price_asc'  => 'Үнэ: Бага → Их',
    'price_desc' => 'Үнэ: Их → Бага',
    'popular'    => 'Эрэлттэй',
];

// Params to preserve across sort/pagination (drop 'sort' and 'page')
$shopBaseQuery = array_filter([
    'search'     => $f['search'],
    'category'   => $f['category'] ? implode(',', $f['category']) : '',
    'gender'     => $f['gender'] ? implode(',', $f['gender']) : '',
    'shop'       => $f['shop'] ? implode(',', $f['shop']) : '',
    'shoe_type'  => $f['shoe_type'] ? implode(',', $f['shoe_type']) : '',
    'run_type'   => $f['run_type'] ? implode(',', $f['run_type']) : '',
    'cushioning' => $f['cushioning'] ? implode(',', $f['cushioning']) : '',
    'gait'       => $f['gait'] ? implode(',', $f['gait']) : '',
    'feature'    => $f['feature'] ? implode(',', $f['feature']) : '',
    'discount'   => $f['discount'] ? '1' : '',
    'new'        => $f['new'] ? '1' : '',
    'price_min'  => $f['price_min'],
    'price_max'  => $f['price_max'],
    'sort'       => $f['sort'] !== 'newest' ? $f['sort'] : '',
], fn($v) => $v !== '' && $v !== null);

// For sort form: keep everything except sort (which is the select itself)
$shopPreserveParams = $shopBaseQuery;
unset($shopPreserveParams['sort']);

// Page title logic
$shopPageTitle = 'Дэлгүүр';
$shopPageSubtitle = '';
if (count($f['category']) === 1) {
    $catSlug = $f['category'][0];
    foreach ($allCategories as $c) {
        if ($c['slug'] === $catSlug) { $shopPageSubtitle = $c['name_mn'] ?: $c['name']; break; }
    }
}
if (count($f['gender']) === 1) {
    $shopPageTitle = ucfirst($f['gender'][0]) === 'Men' ? 'Эрэгтэй' : (ucfirst($f['gender'][0]) === 'Women' ? 'Эмэгтэй' : ($f['gender'][0] === 'kids' ? 'Хүүхэд' : 'Дэлгүүр'));
}
$page_title = ($shopPageSubtitle ? $shopPageSubtitle . ' — ' : '') . $shopPageTitle . ' | ' . $siteName;

$extraStyles = <<<'EXTRA_CSS'
    <!-- Site-specific overrides -->
    <style>
        /* Multi-word nav labels (e.g. "Гүйлтийн гутал") must not wrap: this
           theme's nav row has a fixed line-height, so a wrapped second line
           renders outside the clipped header bounds and becomes invisible. */
        .mainmenu > li > a {
            white-space: nowrap;
        }
        /* Product card images: force 1:1 for a consistent grid. */
        .rbt-card-img {
            aspect-ratio: 1 / 1;
            position: relative;
            overflow: hidden;
        }
        .rbt-card-img > a,
        .rbt-card-img > img,
        .rbt-card-img .rbt-prd-img,
        .rbt-card-img .rbt-hover-img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        /* Promo tiles dropped into the product grid */
        .rw-shop-promo-tile {
            display: flex;
            align-items: flex-end;
            aspect-ratio: 1 / 1;
            border-radius: var(--radius, 6px);
            background-size: cover;
            background-position: center;
            background-color: var(--color-gray-light, #f2f2f2);
            overflow: hidden;
            position: relative;
        }
        .rw-shop-promo-tile-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
            padding: 20px;
            color: #fff;
            background: linear-gradient(0deg, rgba(0,0,0,.55) 0%, rgba(0,0,0,0) 60%);
        }
        .rw-shop-promo-tile-content.text-dark {
            color: #111;
            background: linear-gradient(0deg, rgba(255,255,255,.65) 0%, rgba(255,255,255,0) 60%);
        }
        .rw-shop-promo-tile-eyebrow {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .9;
        }
        .rw-shop-promo-tile-title {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.25;
        }

        /* Mobile filter drawer: the sidebar becomes a slide-in panel below lg */
        @media (max-width: 991.98px) {
            .rw-shop-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 300px;
                max-width: 85vw;
                z-index: 1050;
                overflow-y: auto;
                transform: translateX(-100%);
                transition: transform .3s ease;
                background: var(--color-white, #fff);
            }
            .rw-shop-sidebar.rw-open {
                transform: translateX(0);
            }
            .rw-shop-sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.5);
                z-index: 1040;
            }
            .rw-shop-sidebar-overlay.rw-open {
                display: block;
            }
        }
    </style>
EXTRA_CSS;

$extraScripts = <<<'EXTRA_JS'
    <!-- Mobile filter drawer -->
    <script>
    (function () {
        var sidebar = document.querySelector('.rw-shop-sidebar');
        var overlay = document.getElementById('rwSidebarOverlay');
        var openBtn = document.getElementById('rwSidebarOpen');
        var closeBtn = document.getElementById('rwSidebarClose');
        if (!sidebar || !overlay) return;
        function open() { sidebar.classList.add('rw-open'); overlay.classList.add('rw-open'); }
        function close() { sidebar.classList.remove('rw-open'); overlay.classList.remove('rw-open'); }
        if (openBtn) openBtn.addEventListener('click', function (e) { e.preventDefault(); open(); });
        if (closeBtn) closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', close);
    })();
    </script>
EXTRA_JS;

require __DIR__ . '/includes/header.php';
?>

    <!-- SHOP BREADCRUMB -->
    <div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active">Дэлгүүр</li>
                    <?php if (!empty($shopPageSubtitle)): ?>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active"><?= h($shopPageSubtitle) ?></li>
                    <?php endif; ?>
                </ul>
                <h1 class="title h3 mt--10"><?= h($shopPageTitle) ?></h1>
                <?php if ($shopTotalProducts > 0): ?>
                    <p class="text-muted mb-0 mt--4"><?= (int)$shopTotalProducts ?> бараа</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SHOP MAIN -->
    <div class="rbt-shop-area rbt-section-gapBottom rbt-bg-color-white">
        <div class="container">
            <div class="row row--30">

                <!-- SIDEBAR -->
                <div class="col-xl-3 col-lg-4 mt--30 rbt-shop-sidebar-col">
                <a href="#" class="rbt-filter-button d-lg-none mb--20 d-inline-flex align-items-center gap-2" id="rwSidebarOpen">
                    <i class="fa-sharp fa-regular fa-filter"></i> <span class="filter-text">Шүүлтүүр</span>
                    <?php if ($activeChips): ?><span class="rbt-badge rbt-badge-bg-green rbt-badge-small rbt-badge-rounded"><?= count($activeChips) ?></span><?php endif; ?>
                </a>
                <aside class="rbt-sidebar has-rbt-fshape rw-shop-sidebar">
                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one position-relative">
                        <button type="button" class="rbt-sidebar-close-btn d-lg-none" id="rwSidebarClose"><i class="fa-sharp fa-solid fa-xmark"></i></button>
                        <div class="rbt-sidebar-top">
                            <h2 class="rbt-sidebar-title h6"><i class="fa-sharp fa-regular fa-filter-list mr--4"></i>
                                Шүүлтүүр
                                <span class="rbt-fshape-right-portion">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="40" viewBox="0 0 52 50" fill="none">
                                        <path
                                            d="M51.5337 49.984C-64.8544 49.9977 116.427 49.9764 0.0390625 49.9901C0.0390625 31.262 0.0390625 20.7619 0.0390625 2.03378C11.2391 1.63419 16.5034 4.56468 19.5034 10.5602L30.0034 38.5311C34.0374 47.934 45.4209 49.4481 51.5337 49.984Z"
                                            fill="var(--color-white)" />
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M13.246 1.97519C16.582 3.50685 18.8114 5.90944 20.3979 9.07997L20.4213 9.12681L30.9315 37.1248C33.053 42.053 36.807 44.7979 40.7367 46.3047C44.6934 47.8219 48.798 48.068 51.4731 47.987C51.4731 47.987 51.51 49.2041 51.5337 49.984C48.7087 50.0695 44.3134 49.8162 40.02 48.17C35.7052 46.5155 31.4643 43.4388 29.0842 37.891L29.0751 37.8698C29.0751 37.8698 19.997 12.7279 18.5857 9.92689C17.1743 7.12591 15.2591 5.09828 12.4108 3.79055C8.49554 1.49902 0.0390625 2.03378 0.0390625 2.03378C0.0390625 20.7619 0.0390625 31.262 0.0390625 49.9901L0.0408325 0.0348727C5.70805 -0.16568 9.9493 0.461575 13.246 1.97519Z"
                                            fill="var(--color-gray-200)" />
                                    </svg>
                                </span>
                            </h2>
                        </div>
                        <div class="rbt-sidebar-bottom">
                    <form method="GET" action="<?= h($urlShop) ?>" id="shopFilterForm">

                        <!-- Search -->
                        <div class="rbt-single-widget rbt-widget-categories">
                            <div class="rbt-single-widget-inner">
                                <div class="rbt-inner-search-field style-one rbt-search-field-rounded mt--16">
                                    <input type="text" name="search" placeholder="Бараа хайх..." value="<?= h($f['search']) ?>">
                                    <button class="rbt-round-btn search-btn" type="submit" aria-label="Хайх"><i class="fa-solid fa-magnifying-glass"></i></button>
                                </div>
                            </div>
                        </div>

                        <?php
                        $shopWidgetN = 0;
                        function shopSidebarWidget(string $title, array $items, string $name, array $selected, array $counts = [], bool $open = true): void {
                            global $shopWidgetN;
                            $shopWidgetN++;
                            $cid = 'rbt-shop-collapse-' . $shopWidgetN;
                            ?>
                            <div class="rbt-single-widget rbt-widget-categories">
                                <div class="rbt-single-widget-inner">
                                    <h2 class="rbt-widget-title rbt-widget-title-without-border h4">
                                        <a data-bs-toggle="collapse" href="#<?= $cid ?>" role="button" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $cid ?>">
                                            <?= h($title) ?>
                                            <span class="icon"><i class="fa-regular fa-chevron-down"></i></span>
                                        </a>
                                    </h2>
                                    <div class="collapse <?= $open ? 'show' : '' ?>" id="<?= $cid ?>">
                                        <ul class="rbt-sidebar-list-wrapper rbt-categories-list-check">
                                            <?php foreach ($items as $slug => $label):
                                                $cnt = $counts[$slug] ?? null;
                                                if ($cnt !== null && $cnt < 1 && !in_array($slug, $selected, true)) continue;
                                                $cbId = $name . '-' . $slug;
                                            ?>
                                            <li class="rbt-check-group">
                                                <input id="<?= h($cbId) ?>" type="checkbox" name="<?= h($name) ?>[]" value="<?= h($slug) ?>"
                                                       <?= in_array($slug, $selected, true) ? 'checked' : '' ?>
                                                       onchange="document.getElementById('shopFilterForm').submit()">
                                                <label for="<?= h($cbId) ?>"><?= h($label) ?>
                                                    <?php if ($cnt !== null): ?><span class="rbt-lable count">(<?= $cnt ?>)</span><?php endif; ?>
                                                </label>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }

                        if (!empty($allCategories)) {
                            $items = [];
                            foreach ($allCategories as $c) { $items[$c['slug']] = $c['name_mn'] ?: $c['name']; }
                            shopSidebarWidget('Ангилал', $items, 'category', $f['category'], $catCounts);
                        }

                        shopSidebarWidget('Хүйс', $genderLabels, 'gender', $f['gender'], $genderCounts);

                        if (!empty($allBrands)) {
                            $items = [];
                            foreach ($allBrands as $b) { $items[$b['slug']] = $b['name_mn'] ?: $b['name']; }
                            shopSidebarWidget('Брэнд', $items, 'shop', $f['shop'], $brandCounts, false);
                        }

                        if (!empty($allShoeTypes)) {
                            $items = [];
                            foreach ($allShoeTypes as $st) { $items[$st['slug']] = $st['name_mn'] ?: $st['name']; }
                            shopSidebarWidget('Гутлын төрөл', $items, 'shoe_type', $f['shoe_type'], $shoeTypeCounts, false);
                        }

                        if (!empty($allRunTypes)) {
                            $items = [];
                            foreach ($allRunTypes as $rt) { $items[$rt['slug']] = $rt['name_mn'] ?: $rt['name']; }
                            shopSidebarWidget('Гүйлтийн төрөл', $items, 'run_type', $f['run_type'], $runTypeCounts, false);
                        }

                        if (!empty($allCushionings)) {
                            $items = [];
                            foreach ($allCushionings as $cu) { $items[$cu['slug']] = $cu['name_mn'] ?: $cu['name']; }
                            shopSidebarWidget('Зөөлөвч', $items, 'cushioning', $f['cushioning'], $cushioningCounts, false);
                        }

                        if (!empty($allGaitTypes)) {
                            $items = [];
                            foreach ($allGaitTypes as $g) { $items[$g['slug']] = $g['name_mn'] ?: $g['name']; }
                            shopSidebarWidget('Алхаа', $items, 'gait', $f['gait'], $gaitCounts, false);
                        }

                        if (!empty($allFeatures)) {
                            $items = [];
                            foreach ($allFeatures as $tf) { $items[$tf['slug']] = $tf['name_mn'] ?: $tf['name']; }
                            shopSidebarWidget('Техник шинж чанар', $items, 'feature', $f['feature'], $featureCounts, false);
                        }
                        ?>

                        <!-- Price -->
                        <div class="rbt-single-widget rbt-widget-categories">
                            <div class="rbt-single-widget-inner">
                                <h2 class="rbt-widget-title rbt-widget-title-without-border h4">
                                    <a data-bs-toggle="collapse" href="#rbt-shop-collapse-price" role="button" aria-expanded="true" aria-controls="rbt-shop-collapse-price">
                                        Үнэ (₮)
                                        <span class="icon"><i class="fa-regular fa-chevron-down"></i></span>
                                    </a>
                                </h2>
                                <div class="collapse show" id="rbt-shop-collapse-price">
                                    <div class="rbt-price-input-grp pt--16">
                                        <input type="number" name="price_min" min="0" step="1000" placeholder="Доод" value="<?= h($f['price_min']) ?>">
                                        <input type="number" name="price_max" min="0" step="1000" placeholder="Дээд" value="<?= h($f['price_max']) ?>">
                                        <button type="submit" class="rbt-btn rbt-btn-sm">→</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Extras -->
                        <div class="rbt-single-widget rbt-widget-categories">
                            <div class="rbt-single-widget-inner">
                                <ul class="rbt-sidebar-list-wrapper rbt-categories-list-check">
                                    <li class="rbt-check-group">
                                        <input id="rw-discount" type="checkbox" name="discount" value="1" <?= $f['discount'] ? 'checked' : '' ?>
                                               onchange="document.getElementById('shopFilterForm').submit()">
                                        <label for="rw-discount">Хямдралтай зөвхөн</label>
                                    </li>
                                    <li class="rbt-check-group">
                                        <input id="rw-new" type="checkbox" name="new" value="1" <?= $f['new'] ? 'checked' : '' ?>
                                               onchange="document.getElementById('shopFilterForm').submit()">
                                        <label for="rw-new">Шинэ ирсэн</label>
                                    </li>
                                </ul>
                            </div>
                        </div>

                    </form>
                        </div>
                    </div>
                </aside>
                </div>
                <div class="rw-shop-sidebar-overlay d-lg-none" id="rwSidebarOverlay"></div>

                <!-- PRODUCT GRID -->
                <div class="col-xl-9 col-lg-8 mt--30">

                    <!-- Active filter tags -->
                    <?php if ($activeChips): ?>
                    <div class="rbt-shop-tools-wrapper mb--16">
                        <div class="rbt-shop-tool-content rbt-shop-filter-tag-wrapper w-100">
                            <div class="rbt-shop-filter-tag-list rbt-tag-list rbt-tag-list-rounded rbt-tag-list-var-one">
                                <?php foreach ($activeChips as $chip): ?>
                                <a href="<?= h($chip['url']) ?>"><i class="fa-solid fa-xmark"></i> <?= h($chip['label']) ?></a>
                                <?php endforeach; ?>
                                <a href="<?= h($urlShop) ?>" class="text-decoration-underline">Бүгдийг цэвэрлэх</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sort + view -->
                    <div class="rbt-shop-tools-wrapper rbt-shop-tools-wrapper-var-one mb--24">
                        <div class="rbt-shop-tool-content rbt-shop-view-var-wrapper">
                            <p class="rbt-shop-tools-title h6 mb-0">
                                <?= $shopTotalProducts ?> бараа
                                <?php if ($f['search'] !== ''): ?>
                                    &mdash; <em>«<?= h($f['search']) ?>»</em>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="rbt-shop-tool-content rbt-shop-view-sort-wrapper">
                            <div class="rbt-tools-select-single">
                                <p class="rbt-shop-tools-title h6">Эрэмбэлэх:</p>
                                <form method="GET" id="shopSortForm">
                                    <?php foreach ($shopPreserveParams as $pk => $pv):
                                        if (is_array($pv)) {
                                            foreach ($pv as $vv) echo '<input type="hidden" name="' . h($pk) . '[]" value="' . h((string)$vv) . '">';
                                        } elseif ($pv !== '' && $pv !== null) {
                                            echo '<input type="hidden" name="' . h($pk) . '" value="' . h((string)$pv) . '">';
                                        }
                                    endforeach; ?>
                                    <div class="rbt-modern-select rbt-shop-view-sort-select-one">
                                        <select name="sort" id="sortSelect" class="rbt-select-activation" onchange="document.getElementById('shopSortForm').submit()">
                                            <?php foreach ($shopSortLabels as $sv => $sl): ?>
                                            <option value="<?= h($sv) ?>" <?= $f['sort'] === $sv ? 'selected' : '' ?>><?= h($sl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Grid -->
                    <?php if (empty($shopProducts)): ?>
                        <div class="text-center py-5">
                            <i class="fa-regular fa-bag-shopping" style="font-size:3rem;color:#ddd;"></i>
                            <h4 class="mt--16">Бараа олдсонгүй</h4>
                            <p class="text-muted">Шүүлтүүрээ өөрчилж дахин оролдоно уу.</p>
                            <a href="<?= h($urlShop) ?>" class="btn btn-dark mt--12">Бүх бараа харах</a>
                        </div>
                    <?php else: ?>
                    <div class="row row--12 mt_dec--24">
                        <?php
                        $promoEvery = 8;
                        $promoIdx = 0;
                        foreach ($shopProducts as $i => $prod):
                            renderProductCard($prod, $i);
                            if ($shopTopBanners && $i > 0 && ($i + 1) % $promoEvery === 0) {
                                $banner = $shopTopBanners[$promoIdx % count($shopTopBanners)];
                                $promoIdx++;
                                ?>
                                <div class="col-xxl-4 col-xl-6 col-lg-6 col-md-6 col-sm-6 col-6 mt--24">
                                    <a href="<?= h($banner['btn_url'] ?: $urlShop) ?>" class="rw-shop-promo-tile" style="background-image:url('<?= h(fixImageUrl($banner['image'])) ?>')">
                                        <span class="rw-shop-promo-tile-content <?= !empty($banner['text_dark']) ? 'text-dark' : '' ?>">
                                            <?php if (!empty($banner['subtitle_mn'])): ?><span class="rw-shop-promo-tile-eyebrow"><?= h($banner['subtitle_mn']) ?></span><?php endif; ?>
                                            <?php if (!empty($banner['title_mn'])): ?><span class="rw-shop-promo-tile-title"><?= h($banner['title_mn']) ?></span><?php endif; ?>
                                            <?php if (!empty($banner['btn_text'])): ?><span class="rbt-btn rbt-btn-sm rbt-btn-white mt--12"><?= h($banner['btn_text']) ?></span><?php endif; ?>
                                        </span>
                                    </a>
                                </div>
                                <?php
                            }
                        endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($shopTotalPages > 1): ?>
                    <nav class="mt--40 d-flex justify-content-center">
                        <ul class="pagination">
                            <?php
                            $mkPageUrl = function(int $p) use ($shopBaseQuery, $urlShop): string {
                                $q = $shopBaseQuery;
                                if ($p > 1) $q['page'] = $p; else unset($q['page']);
                                $qs = http_build_query($q);
                                return $urlShop . ($qs ? '?' . $qs : '');
                            };
                            $start = max(1, $shopPage - 2);
                            $end = min($shopTotalPages, $shopPage + 2);
                            ?>
                            <?php if ($shopPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= h($mkPageUrl($shopPage - 1)) ?>">&laquo;</a></li>
                            <?php endif; ?>
                            <?php if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= h($mkPageUrl(1)) ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $shopPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= h($mkPageUrl($i)) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            <?php if ($end < $shopTotalPages): ?>
                            <?php if ($end < $shopTotalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= h($mkPageUrl($shopTotalPages)) ?>"><?= $shopTotalPages ?></a></li>
                            <?php endif; ?>
                            <?php if ($shopPage < $shopTotalPages): ?>
                            <li class="page-item"><a class="page-link" href="<?= h($mkPageUrl($shopPage + 1)) ?>">&raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>


    <!-- ALL CATEGORIES -->


<?php require __DIR__ . '/includes/footer.php'; ?>
