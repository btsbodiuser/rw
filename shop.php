<?php
require_once __DIR__ . '/includes/config.php';

// ── Per-shop landing page (/shop/{slug}) ────────────────────────────────────────
// Normally set via the ^shop/([^/]+)/?$ rewrite rule; PATH_INFO is a defensive
// fallback matching product.php/category.php's pattern.
$shopSlug = trim($_GET['shop_slug'] ?? '');
if ($shopSlug === '' && !empty($_SERVER['PATH_INFO'])) {
    $shopSlug = trim($_SERVER['PATH_INFO'], '/');
}

// ── Filter params (multi-select checkboxes) ────────────────────────────────────
$_allowedGenders    = ['men', 'women', 'unisex', 'kids'];
$_allowedTypes      = ['ready', 'preorder'];

$filterGenders      = array_values(array_intersect((array)($_GET['gender']   ?? []), $_allowedGenders));
$filterCategories   = array_values(array_filter(array_map('trim', (array)($_GET['category'] ?? []))));
$filterActivities   = array_values(array_filter(array_map('intval', (array)($_GET['activity'] ?? []))));
$filterTypes        = array_values(array_intersect((array)($_GET['type']     ?? []), $_allowedTypes));
$filterShops        = array_values(array_filter(array_map('trim', (array)($_GET['shop']    ?? []))));
$filterDiscount     = !empty($_GET['discount']);
$filterNewOnly      = !empty($_GET['new']);
$filterSearch       = trim($_GET['search'] ?? '');
$_allowedSorts      = ['popular', 'newest', 'price_asc', 'price_desc'];
$filterSort         = in_array($_GET['sort'] ?? '', $_allowedSorts, true) ? $_GET['sort'] : 'newest';
$page               = max(1, (int)($_GET['page'] ?? 1));
$perPage            = 12;
$offset             = ($page - 1) * $perPage;

$db = getDB();

// ── Load sidebar data ──────────────────────────────────────────────────────────
// Categories with product counts
$catRows = $db->query(
    "SELECT c.id, c.slug, c.name, c.name_mn, c.icon,
            COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.show_in_store = 1
     GROUP BY c.id
     ORDER BY c.sort_order, c.name_mn"
)->fetchAll();

// Shops
$shopRows = $db->query(
    "SELECT id, slug, name, name_mn, description_mn, color, logo FROM shops WHERE is_active = 1 ORDER BY sort_order, name_mn, name"
)->fetchAll();

// ── Resolve the landing-page shop, if any ───────────────────────────────────────
$shopInfo = null;
if ($shopSlug !== '') {
    foreach ($shopRows as $sh) {
        if ($sh['slug'] === $shopSlug) { $shopInfo = $sh; break; }
    }
    if (!$shopInfo) {
        http_response_code(404);
        $page_title = '404 — Дэлгүүр олдсонгүй';
        require_once __DIR__ . '/includes/header.php';
        echo '<div class="container py-5 text-center"><h2>Дэлгүүр олдсонгүй</h2><a href="' . url('shop.php') . '" class="tf-btn animate-btn mt-3">Дэлгүүр рүү буцах</a></div>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
    // Landing page is scoped to this one shop — the URL path is the filter.
    $filterShops = [$shopSlug];
}

// Activity types
$activityRows = $db->query(
    "SELECT * FROM activity_types WHERE is_active = 1 ORDER BY sort_order"
)->fetchAll();

// ── Build product query ────────────────────────────────────────────────────────
$whereClauses = ['p.show_in_store = 1'];
$params       = [];

// Helper: build IN clause
function inClause(array $vals): string {
    return '(' . implode(',', array_fill(0, count($vals), '?')) . ')';
}

if (!empty($filterCategories)) {
    $whereClauses[] = 'c.slug IN ' . inClause($filterCategories);
    $params = array_merge($params, $filterCategories);
}
if (!empty($filterTypes)) {
    $whereClauses[] = 'p.type IN ' . inClause($filterTypes);
    $params = array_merge($params, $filterTypes);
}
if (!empty($filterShops)) {
    $whereClauses[] = 's.slug IN ' . inClause($filterShops);
    $params = array_merge($params, $filterShops);
}
if (!empty($filterGenders)) {
    $whereClauses[] = 'p.gender IN ' . inClause($filterGenders);
    $params = array_merge($params, $filterGenders);
}
if (!empty($filterActivities)) {
    $ph = inClause($filterActivities);
    $whereClauses[] = "EXISTS (SELECT 1 FROM product_activity_types pat WHERE pat.product_id = p.id AND pat.activity_type_id IN $ph)";
    $params = array_merge($params, $filterActivities);
}
if ($filterSearch !== '') {
    $whereClauses[] = '(p.name LIKE ? OR p.name_mn LIKE ?)';
    $like = '%' . $filterSearch . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($filterDiscount) {
    $whereClauses[] = 'p.original_price > p.price';
}
if ($filterNewOnly) {
    $whereClauses[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

$whereSQL = implode(' AND ', $whereClauses);

$orderBy = match($filterSort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular'    => 'p.rating DESC, p.reviews DESC',
    default      => 'p.created_at DESC', // newest
};

// Total count for pagination
$countStmt = $db->prepare(
    "SELECT COUNT(p.id)
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN shops s ON p.shop_id = s.id
     WHERE {$whereSQL}"
);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($totalProducts / $perPage));
$page          = min($page, $totalPages);

// Product rows
$productStmt = $db->prepare(
    "SELECT p.id, p.slug, p.name, p.name_mn, p.type, p.price, p.original_price,
            p.image, p.stock, p.rating, p.reviews, p.category_id, p.shop_id,
            c.name AS category_name, c.name_mn AS category_name_mn,
            s.name AS shop_name, s.name_mn AS shop_name_mn
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN shops s ON p.shop_id = s.id
     WHERE {$whereSQL}
     ORDER BY {$orderBy}
     LIMIT {$perPage} OFFSET {$offset}"
);
$productStmt->execute($params);
$products = $productStmt->fetchAll();

// ── Active category label (for breadcrumb when exactly one is selected) ────────
$activeCategoryName = '';
if (count($filterCategories) === 1) {
    foreach ($catRows as $cr) {
        if ($cr['slug'] === $filterCategories[0]) {
            $activeCategoryName = $cr['name_mn'] ?: $cr['name'];
            break;
        }
    }
}

// Base URL for filter/pagination links: /shop/{slug} on a shop landing page
// (the shop is implicit in the path there, so it's dropped from the query
// string), otherwise the generic /shop.php grid.
function shopBaseUrl(): string {
    global $shopSlug;
    return $shopSlug !== '' ? url('shop/' . rawurlencode($shopSlug)) : url('shop.php');
}

// ── Pagination URL: preserves all current checkbox filter arrays ───────────────
function shopCategoryUrl(string $slug): string {
    global $shopSlug;
    $params = array_filter([
        'category' => $slug !== '' ? [$slug] : [],
        'type'     => $_GET['type']     ?? [],
        'shop'     => $shopSlug !== '' ? [] : ($_GET['shop'] ?? []),
        'gender'   => $_GET['gender']   ?? [],
        'activity' => $_GET['activity'] ?? [],
        'search'   => $_GET['search']   ?? '',
        'discount' => $_GET['discount'] ?? '',
        'new'      => $_GET['new']      ?? '',
        'sort'     => $_GET['sort']     ?? '',
    ], fn($v) => $v !== '' && $v !== [] && $v !== null);
    $qs = http_build_query($params);
    return shopBaseUrl() . ($qs ? '?' . $qs : '');
}

function shopPageUrl(int $p): string {
    global $shopSlug;
    $qs = http_build_query(array_filter([
        'category' => $_GET['category'] ?? [],
        'type'     => $_GET['type']     ?? [],
        'shop'     => $shopSlug !== '' ? [] : ($_GET['shop'] ?? []),
        'gender'   => $_GET['gender']   ?? [],
        'activity' => $_GET['activity'] ?? [],
        'search'   => $_GET['search']   ?? '',
        'discount' => $_GET['discount'] ?? '',
        'new'      => $_GET['new']      ?? '',
        'sort'     => $_GET['sort']     ?? '',
        'page'     => $p,
    ], fn($v) => $v !== '' && $v !== [] && $v !== null));
    return shopBaseUrl() . ($qs ? '?' . $qs : '');
}

// Sort dropdown link — preserves all current filters, swaps only `sort`.
function shopSortUrl(string $sortValue): string {
    global $shopSlug;
    $qs = http_build_query(array_filter([
        'category' => $_GET['category'] ?? [],
        'type'     => $_GET['type']     ?? [],
        'shop'     => $shopSlug !== '' ? [] : ($_GET['shop'] ?? []),
        'gender'   => $_GET['gender']   ?? [],
        'activity' => $_GET['activity'] ?? [],
        'search'   => $_GET['search']   ?? '',
        'discount' => $_GET['discount'] ?? '',
        'new'      => $_GET['new']      ?? '',
        'sort'     => $sortValue,
    ], fn($v) => $v !== '' && $v !== [] && $v !== null));
    return shopBaseUrl() . ($qs ? '?' . $qs : '');
}

// ── Page title ─────────────────────────────────────────────────────────────────
$shopLabel   = $shopInfo ? ($shopInfo['name_mn'] ?: $shopInfo['name']) : '';
$titlePrefix = $shopLabel ?: $activeCategoryName;
$page_title  = ($titlePrefix ? $titlePrefix . ' — ' : '') . 'Дэлгүүр' . ' | ' . s('site_name', 'Runners World');

require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page"><?= $shopLabel ? htmlspecialchars($shopLabel) : ($activeCategoryName ? htmlspecialchars($activeCategoryName) : 'Дэлгүүр') ?></h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <?php if ($shopLabel): ?>
                        <li><a href="<?= url('shop.php') ?>" class="h6 link">Дэлгүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal"><?= htmlspecialchars($shopLabel) ?></h6></li>
                        <?php elseif ($activeCategoryName): ?>
                        <li><a href="<?= url('shop.php') ?>" class="h6 link">Дэлгүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal"><?= htmlspecialchars($activeCategoryName) ?></h6></li>
                        <?php else: ?>
                        <li><h6 class="current-page fw-normal">Дэлгүүр</h6></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <?php if ($shopInfo): ?>
        <!-- Shop Hero -->
        <div class="flat-spacing pb-0">
            <div class="container">
                <div class="rounded-4 p-4 p-md-5 d-flex align-items-center gap-4 flex-wrap"
                     style="background:linear-gradient(to bottom right, <?= hexToLight($shopInfo['color'] ?: '#999999', 0.08) ?>, <?= hexToLight($shopInfo['color'] ?: '#999999', 0.18) ?>);">
                    <?php if (!empty($shopInfo['logo'])): ?>
                    <img src="<?= htmlspecialchars(fixImageUrl($shopInfo['logo'])) ?>" alt="<?= htmlspecialchars($shopLabel) ?>"
                         style="width:88px;height:88px;object-fit:contain;background:#fff;border-radius:16px;padding:10px;flex-shrink:0;">
                    <?php endif; ?>
                    <div>
                        <h2 class="fw-bold mb-2" style="color:<?= htmlspecialchars($shopInfo['color'] ?: '#111') ?>;"><?= htmlspecialchars($shopLabel) ?></h2>
                        <?php if (!empty($shopInfo['description_mn'])): ?>
                        <p class="h6 text-main mb-2" style="max-width:640px;white-space:pre-line;"><?= htmlspecialchars($shopInfo['description_mn']) ?></p>
                        <?php endif; ?>
                        <p class="h6 text-main mb-0"><?= $totalProducts ?> бүтээгдэхүүн</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Shop Hero -->
        <?php endif; ?>

        <!-- Category Swiper -->
        <div class="flat-spacing pb-0">
            <div class="container">
                <div dir="ltr" class="swiper tf-swiper"
                     data-preview="6" data-tablet="5" data-mobile-sm="4" data-mobile="3"
                     data-space-lg="16" data-space-md="12" data-space="8">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <a href="<?= shopCategoryUrl('') ?>"
                               class="tf-btn animate-btn type-small<?= empty($filterCategories) ? ' btn-primary' : ' btn-white animate-dark' ?>">
                                Бүгд
                            </a>
                        </div>
                        <?php foreach ($catRows as $cat): ?>
                        <div class="swiper-slide">
                            <a href="<?= shopCategoryUrl($cat['slug']) ?>"
                               class="tf-btn animate-btn type-small<?= (count($filterCategories) === 1 && in_array($cat['slug'], $filterCategories)) ? ' btn-primary' : ' btn-white animate-dark' ?>">
                                <?php if ($cat['icon']): ?><?= htmlspecialchars($cat['icon']) ?> <?php endif; ?>
                                <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </div>
        <!-- /Category Swiper -->

        <!-- Section Product -->
        <div class="flat-spacing-3 pb-0">
            <div class="container">
                <div class="row">
                    <!-- Sidebar -->
                    <div class="col-xl-3">
                        <div class="canvas-sidebar sidebar-filter canvas-filter left">
                            <div class="canvas-wrapper">
                                <div class="canvas-header d-xl-none">
                                    <span class="title h3 fw-medium">Шүүлтүүр</span>
                                    <span class="icon-close link icon-close-popup fs-24 close-filter"></span>
                                </div>

                                <form method="GET" action="<?= shopBaseUrl() ?>" id="shop-filter-form">
                                    <?php if ($filterSearch): ?>
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($filterSearch) ?>">
                                    <?php endif; ?>
                                    <input type="hidden" name="page" value="1">

                                <div class="canvas-body">

                                    <!-- 1. Хүйс -->
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-gender"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-gender">
                                            <span class="h4 fw-semibold">Хүйс</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-gender" class="collapse show">
                                            <ul class="collapse-body filter-group-check">
                                                <?php foreach (['men' => 'Эрэгтэй', 'women' => 'Эмэгтэй', 'unisex' => 'Унисекс', 'kids' => 'Хүүхэд'] as $val => $lbl): ?>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="gender[]" value="<?= $val ?>"
                                                               <?= in_array($val, $filterGenders) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        <?= $lbl ?>
                                                    </label>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- 2. Ангилал -->
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-category"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-category">
                                            <span class="h4 fw-semibold">Ангилал</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-category" class="collapse show">
                                            <ul class="collapse-body filter-group-check group-category">
                                                <?php foreach ($catRows as $cat): ?>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="category[]" value="<?= htmlspecialchars($cat['slug']) ?>"
                                                               <?= in_array($cat['slug'], $filterCategories) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
                                                        <span class="count"><?= (int)$cat['product_count'] ?></span>
                                                    </label>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- 3. Үйл ажиллагаа -->
                                    <?php if (!empty($activityRows)): ?>
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-activity"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-activity">
                                            <span class="h4 fw-semibold">Үйл ажиллагаа</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-activity" class="collapse show">
                                            <ul class="collapse-body filter-group-check">
                                                <?php foreach ($activityRows as $ar): ?>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="activity[]" value="<?= (int)$ar['id'] ?>"
                                                               <?= in_array((int)$ar['id'], $filterActivities) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        <?= $ar['icon'] ? htmlspecialchars($ar['icon']) . ' ' : '' ?><?= htmlspecialchars($ar['name_mn']) ?>
                                                    </label>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- 4. Брэнд (already scoped by the URL on a shop landing page) -->
                                    <?php if (!$shopInfo && !empty($shopRows)): ?>
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-shops"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-shops">
                                            <span class="h4 fw-semibold">Брэнд</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-shops" class="collapse show">
                                            <ul class="collapse-body filter-group-check current-scrollbar">
                                                <?php foreach ($shopRows as $sh): ?>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="shop[]" value="<?= htmlspecialchars($sh['slug']) ?>"
                                                               <?= in_array($sh['slug'], $filterShops) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        <?= htmlspecialchars($sh['name_mn'] ?: $sh['name']) ?>
                                                    </label>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- 5. Төрөл -->
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-type"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-type">
                                            <span class="h4 fw-semibold">Төрөл</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-type" class="collapse show">
                                            <ul class="collapse-body filter-group-check">
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="type[]" value="ready"
                                                               <?= in_array('ready', $filterTypes) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        Бэлэн бараа
                                                    </label>
                                                </li>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="type[]" value="preorder"
                                                               <?= in_array('preorder', $filterTypes) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        Урьдчилсан захиалга
                                                    </label>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- 6. Хямдрал -->
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-discount"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-discount">
                                            <span class="h4 fw-semibold">Хямдрал</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-discount" class="collapse show">
                                            <ul class="collapse-body filter-group-check">
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="discount" value="1"
                                                               <?= $filterDiscount ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        Зөвхөн хямдарсан
                                                    </label>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- 7. Шинэ -->
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-new"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-new">
                                            <span class="h4 fw-semibold">Шинэ</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-new" class="collapse show">
                                            <ul class="collapse-body filter-group-check">
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="new" value="1"
                                                               <?= $filterNewOnly ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').submit()">
                                                        ✨ Зөвхөн шинэ бараа
                                                    </label>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                </div><!-- /canvas-body -->

                                <div class="canvas-bottom d-xl-none">
                                    <button type="button" onclick="window.location='<?= shopBaseUrl() ?>'" class="tf-btn btn-reset">
                                        Шүүлтүүр арилгах
                                    </button>
                                </div>

                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- /Sidebar -->

                    <!-- Product Grid -->
                    <div class="col-xl-9">
                        <!-- Shop control bar -->
                        <div class="tf-shop-control">
                            <div class="tf-control-filter d-xl-none">
                                <button type="button" id="filterShop" class="tf-btn-filter">
                                    <span class="icon icon-filter"></span>
                                    <span class="text">Шүүлтүүр</span>
                                </button>
                            </div>
                            <div class="meta-filter-shop d-none d-xl-flex align-items-center gap-2">
                                <span class="h6 text-main">
                                    <?= $totalProducts ?> бараа
                                    <?php if ($filterSearch): ?>
                                    &mdash; <em>"<?= htmlspecialchars($filterSearch) ?>"</em>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <!-- Sort by -->
                            <?php
                            $_sortLabels = [
                                'newest'     => 'Шинэ',
                                'popular'    => 'Алдартай',
                                'price_asc'  => 'Үнэ: Бага → Их',
                                'price_desc' => 'Үнэ: Их → Бага',
                            ];
                            ?>
                            <div class="tf-control-sorting">
                                <p class="h6 d-none d-lg-block">Эрэмбэлэх:</p>
                                <div class="tf-dropdown-sort">
                                    <div class="btn-select" data-bs-toggle="dropdown">
                                        <span class="text-sort-value"><?= htmlspecialchars($_sortLabels[$filterSort]) ?></span>
                                        <span class="icon icon-caret-down"></span>
                                    </div>
                                    <div class="dropdown-menu">
                                        <?php foreach ($_sortLabels as $val => $lbl): ?>
                                        <a href="<?= shopSortUrl($val) ?>" class="select-item<?= $filterSort === $val ? ' active' : '' ?>" data-sort-value="<?= $val ?>">
                                            <span class="text-value-item"><?= htmlspecialchars($lbl) ?></span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Search within shop -->
                            <form method="get" action="<?= shopBaseUrl() ?>" class="d-flex align-items-center gap-2">
                                <?php foreach (['category', 'type', 'gender', 'activity'] as $pk): ?>
                                <?php foreach ((array)($_GET[$pk] ?? []) as $pv): ?>
                                <input type="hidden" name="<?= htmlspecialchars($pk) ?>[]" value="<?= htmlspecialchars($pv) ?>">
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                                <?php if (!$shopInfo): foreach ((array)($_GET['shop'] ?? []) as $pv): ?>
                                <input type="hidden" name="shop[]" value="<?= htmlspecialchars($pv) ?>">
                                <?php endforeach; endif; ?>
                                <?php if ($filterDiscount): ?>
                                <input type="hidden" name="discount" value="1">
                                <?php endif; ?>
                                <?php if ($filterNewOnly): ?>
                                <input type="hidden" name="new" value="1">
                                <?php endif; ?>
                                <?php if ($filterSort !== 'newest'): ?>
                                <input type="hidden" name="sort" value="<?= htmlspecialchars($filterSort) ?>">
                                <?php endif; ?>
                                <div class="d-flex" style="border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
                                    <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>"
                                           placeholder="Бараа хайх..." class="h6"
                                           style="border:none;padding:6px 12px;outline:none;min-width:180px;">
                                    <button type="submit" class="tf-btn type-small" style="border-radius:0;border:none;">
                                        <i class="icon icon-magnifying-glass"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Grid wrapper -->
                        <div class="wrapper-control-shop">
                            <?php if (empty($products)): ?>
                            <div class="text-center py-5">
                                <i class="icon icon-bag fs-48 text-main" style="font-size:3rem;"></i>
                                <h4 class="mt-3">Бараа олдсонгүй</h4>
                                <p class="h6 text-main mt-2">Шүүлтүүрийн нөхцөлийг өөрчилж үзнэ үү.</p>
                                <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn mt-4">Бүх бараа харах</a>
                            </div>
                            <?php else: ?>
                            <div class="wrapper-shop tf-grid-layout tf-col-4" id="gridLayout">
                                <?php foreach ($products as $p): ?>
                                <?php
                                // Ensure category_name is present for product-card.php
                                $p['category_name'] = $p['category_name_mn'] ?: ($p['category_name'] ?? '');
                                ?>
                                <?php require __DIR__ . '/includes/product-card.php'; ?>
                                <?php endforeach; ?>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                <div class="wd-full wg-pagination m-0 justify-content-center">
                                    <?php if ($page > 1): ?>
                                    <a href="<?= shopPageUrl($page - 1) ?>" class="pagination-item h6 direct">
                                        <i class="icon icon-caret-left"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="pagination-item h6 direct disabled" style="opacity:.4;">
                                        <i class="icon icon-caret-left"></i>
                                    </span>
                                    <?php endif; ?>

                                    <?php
                                    $start = max(1, $page - 2);
                                    $end   = min($totalPages, $page + 2);
                                    if ($start > 1): ?>
                                    <a href="<?= shopPageUrl(1) ?>" class="pagination-item h6">1</a>
                                    <?php if ($start > 2): ?><span class="pagination-item h6 disabled">…</span><?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <?php if ($i === $page): ?>
                                    <span class="pagination-item h6 active"><?= $i ?></span>
                                    <?php else: ?>
                                    <a href="<?= shopPageUrl($i) ?>" class="pagination-item h6"><?= $i ?></a>
                                    <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?><span class="pagination-item h6 disabled">…</span><?php endif; ?>
                                    <a href="<?= shopPageUrl($totalPages) ?>" class="pagination-item h6"><?= $totalPages ?></a>
                                    <?php endif; ?>

                                    <?php if ($page < $totalPages): ?>
                                    <a href="<?= shopPageUrl($page + 1) ?>" class="pagination-item h6 direct">
                                        <i class="icon icon-caret-right"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="pagination-item h6 direct disabled" style="opacity:.4;">
                                        <i class="icon icon-caret-right"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- /Grid wrapper -->
                    </div>
                    <!-- /Product Grid -->
                </div>
            </div>
        </div>
        <!-- /Section Product -->

        <!-- Box Icon -->
        <div class="flat-spacing">
            <div class="container">
                <div dir="ltr" class="swiper tf-swiper"
                     data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1"
                     data-space-lg="97" data-space-md="33" data-space="13"
                     data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <div class="box-icon_V01">
                                <span class="icon"><i class="icon-boat"></i></span>
                                <div class="content">
                                    <h4 class="title fw-normal">Үнэгүй хүргэлт</h4>
                                    <p class="text">50,000₮-с дээш захиалгад</p>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="box-icon_V01">
                                <span class="icon"><i class="icon-package"></i></span>
                                <div class="content">
                                    <h4 class="title fw-normal">Жинхэнэ бараа</h4>
                                    <p class="text">Солонгосоос шууд</p>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="box-icon_V01">
                                <span class="icon"><i class="icon-calender"></i></span>
                                <div class="content">
                                    <h4 class="title fw-normal">30 хоногийн буцаалт</h4>
                                    <p class="text">Мөнгө буцаах баталгаа</p>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="box-icon_V01">
                                <span class="icon"><i class="icon-headset"></i></span>
                                <div class="content">
                                    <h4 class="title fw-normal">Онлайн дэмжлэг</h4>
                                    <p class="text">7 хоногт 24 цаг</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </div>
        <!-- /Box Icon -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
