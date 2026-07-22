<?php
require_once __DIR__ . '/includes/config.php';

// ── Filter params (multi-select) ───────────────────────────────────────────────
// Accepts both new CSV format (?gender=women,men) and legacy PHP-array format
// (?gender[]=women&gender[]=men) so old bookmarks continue to work.
function csvParam(string $key): array {
    $raw = $_GET[$key] ?? '';
    if (is_array($raw)) $raw = implode(',', $raw);
    return array_values(array_filter(array_map('trim', explode(',', (string)$raw))));
}

$_allowedGenders    = ['men', 'women', 'unisex', 'kids'];
$_allowedTypes      = ['ready', 'preorder'];

$filterGenders      = array_values(array_intersect(csvParam('gender'), $_allowedGenders));
$filterCategories   = csvParam('category');
// Activities filter carries slugs in the URL (?activity=trail-running,hiking).
// Numeric IDs are still accepted for backward compatibility with old bookmarks.
$filterActivities   = csvParam('activity');
$filterTypes        = array_values(array_intersect(csvParam('type'), $_allowedTypes));
$filterShops        = csvParam('shop');
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
$catRows = $db->query(
    "SELECT id, slug, name, name_mn, icon FROM categories ORDER BY sort_order, name_mn"
)->fetchAll();

// Shops
$shopRows = $db->query(
    "SELECT id, slug, name, name_mn, description_mn, color, logo FROM shops WHERE is_active = 1 ORDER BY sort_order, name_mn, name"
)->fetchAll();

// ── Detect single-brand focus (show shop hero banner when exactly one is picked)
$shopInfo = null;
if (count($filterShops) === 1) {
    foreach ($shopRows as $sh) {
        if ($sh['slug'] === $filterShops[0]) { $shopInfo = $sh; break; }
    }
}

// Activity types
$activityRows = $db->query(
    "SELECT * FROM activity_types WHERE is_active = 1 ORDER BY sort_order"
)->fetchAll();

// Resolve activity filter values (slugs, or legacy numeric IDs) to real IDs for SQL
$_activityBySlug = [];
$_activityById   = [];
foreach ($activityRows as $ar) {
    $_activityBySlug[$ar['slug']] = (int)$ar['id'];
    $_activityById[(int)$ar['id']] = $ar['slug'];
}
$filterActivityIds = [];
foreach ($filterActivities as $v) {
    if (isset($_activityBySlug[$v])) {
        $filterActivityIds[] = $_activityBySlug[$v];
    } elseif (ctype_digit((string)$v) && isset($_activityById[(int)$v])) {
        $filterActivityIds[] = (int)$v;
    }
}
$filterActivityIds = array_values(array_unique($filterActivityIds));

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
if (!empty($filterActivityIds)) {
    $ph = inClause($filterActivityIds);
    $whereClauses[] = "EXISTS (SELECT 1 FROM product_activity_types pat WHERE pat.product_id = p.id AND pat.activity_type_id IN $ph)";
    $params = array_merge($params, $filterActivityIds);
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

// Base URL for filter/pagination links — always the generic /shop grid.
function shopBaseUrl(): string {
    return url('shop');
}

// Build a clean query string from current filters, with optional overrides.
// Multi-value filters are joined with commas (no PHP-array [] syntax).
function shopUrl(array $overrides = []): string {
    global $filterGenders, $filterCategories, $filterActivities,
           $filterTypes, $filterShops, $filterDiscount, $filterNewOnly,
           $filterSearch, $filterSort;

    $vals = array_merge([
        'gender'   => $filterGenders,
        'category' => $filterCategories,
        'activity' => $filterActivities,
        'type'     => $filterTypes,
        'shop'     => $filterShops,
        'search'   => $filterSearch,
        'discount' => $filterDiscount ? '1' : '',
        'new'      => $filterNewOnly ? '1' : '',
        'sort'     => $filterSort !== 'newest' ? $filterSort : '',
        'page'     => '',
    ], $overrides);

    $pairs = [];
    foreach ($vals as $k => $v) {
        if (is_array($v)) {
            if (!empty($v)) $pairs[$k] = implode(',', $v);
        } elseif ($v !== '' && $v !== null) {
            $pairs[$k] = (string)$v;
        }
    }
    $qs = http_build_query($pairs);
    return shopBaseUrl() . ($qs ? '?' . $qs : '');
}

function shopCategoryUrl(string $slug): string {
    return shopUrl(['category' => $slug !== '' ? [$slug] : []]);
}

function shopPageUrl(int $p): string {
    return shopUrl(['page' => $p]);
}

function shopSortUrl(string $sortValue): string {
    return shopUrl(['sort' => $sortValue === 'newest' ? '' : $sortValue]);
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
                                                        <input type="checkbox" name="gender" value="<?= $val ?>"
                                                               <?= in_array($val, $filterGenders) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
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
                                                        <input type="checkbox" name="category" value="<?= htmlspecialchars($cat['slug']) ?>"
                                                               <?= in_array($cat['slug'], $filterCategories) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
                                                        <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
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
                                                        <input type="checkbox" name="activity" value="<?= htmlspecialchars($ar['slug']) ?>"
                                                               <?= in_array($ar['slug'], $filterActivities, true) || in_array((int)$ar['id'], $filterActivityIds, true) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
                                                        <?= $ar['icon'] ? htmlspecialchars($ar['icon']) . ' ' : '' ?><?= htmlspecialchars($ar['name_mn']) ?>
                                                    </label>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- 4. Брэнд -->
                                    <?php if (!empty($shopRows)): ?>
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
                                                        <input type="checkbox" name="shop" value="<?= htmlspecialchars($sh['slug']) ?>"
                                                               <?= in_array($sh['slug'], $filterShops) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
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
                                                        <input type="checkbox" name="type" value="ready"
                                                               <?= in_array('ready', $filterTypes) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
                                                        Бэлэн бараа
                                                    </label>
                                                </li>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="type" value="preorder"
                                                               <?= in_array('preorder', $filterTypes) ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
                                                        Урьдчилсан захиалга
                                                    </label>
                                                </li>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="discount" value="1"
                                                               <?= $filterDiscount ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
                                                        Хямдралтай
                                                    </label>
                                                </li>
                                                <li class="list-item">
                                                    <label class="filter-check-label h6">
                                                        <input type="checkbox" name="new" value="1"
                                                               <?= $filterNewOnly ? 'checked' : '' ?>
                                                               onchange="document.getElementById('shop-filter-form').requestSubmit()">
                                                        Шинээр ирсэн
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
                                'popular'    => 'Эрэлттэй',
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
                                <?php
                                $_searchHidden = [
                                    'category' => $filterCategories,
                                    'type'     => $filterTypes,
                                    'gender'   => $filterGenders,
                                    'activity' => $filterActivities,
                                    'shop'     => $filterShops,
                                ];
                                foreach ($_searchHidden as $pk => $vals):
                                    if (empty($vals)) continue; ?>
                                <input type="hidden" name="<?= htmlspecialchars($pk) ?>" value="<?= htmlspecialchars(implode(',', $vals)) ?>">
                                <?php endforeach; ?>
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
        <?php
        $_features = [];
        try {
            $_features = $db->query("SELECT icon, title_mn, description_mn FROM features WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();
        } catch (Throwable $e) {}
        if (!empty($_features)): ?>
        <div class="flat-spacing">
            <div class="container">
                <div dir="ltr" class="swiper tf-swiper"
                     data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1"
                     data-space-lg="97" data-space-md="33" data-space="13"
                     data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                    <div class="swiper-wrapper">
                        <?php foreach ($_features as $f): ?>
                        <div class="swiper-slide">
                            <div class="box-icon_V01">
                                <span class="icon"><i class="<?= htmlspecialchars($f['icon']) ?>"></i></span>
                                <div class="content">
                                    <h4 class="title fw-normal"><?= htmlspecialchars($f['title_mn']) ?></h4>
                                    <?php if (!empty($f['description_mn'])): ?>
                                    <p class="text"><?= htmlspecialchars($f['description_mn']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- /Box Icon -->

<script>
// Intercept shop filter/search forms: build clean CSV URLs (?gender=women,men)
// instead of PHP-array URLs (?gender[]=women&gender[]=men).
(function () {
    const multi = ['gender', 'category', 'activity', 'shop', 'type'];

    function buildCleanUrl(form) {
        const params = new URLSearchParams();

        // Multi-select checkbox groups → comma-joined single param
        multi.forEach(name => {
            const vals = Array.from(form.querySelectorAll(
                'input[type=checkbox][name="' + name + '"]:checked'
            )).map(i => i.value);
            if (vals.length) params.set(name, vals.join(','));
        });

        // Non-multi inputs (hidden + text + single checkboxes for discount/new)
        Array.from(form.elements).forEach(el => {
            if (!el.name || multi.includes(el.name)) return;
            if (el.type === 'checkbox' && !el.checked) return;
            const v = el.value.trim();
            if (v !== '') params.set(el.name, v);
        });

        // Any filter change resets to page 1
        params.delete('page');

        const qs = params.toString();
        return form.action + (qs ? '?' + qs : '');
    }

    document.querySelectorAll('form[action*="shop"]').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            window.location.href = buildCleanUrl(form);
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
