<?php
require_once __DIR__ . '/includes/config.php';

// ── Category slug (from URL or GET param) ─────────────────────────────────────
// Supports both ?slug=foo (direct GET) and routing via .htaccess /category/foo
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    // Try to extract from REQUEST_URI: /rw/category/foo
    $uri   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $base  = rtrim(str_replace('\\', '/', realpath(__DIR__)), '/');
    $docR  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $base  = str_replace($docR, '', $base);
    if (preg_match('#' . preg_quote($base, '#') . '/category/([^/?]+)#', $uri, $m)) {
        $slug = $m[1];
    }
}

$db = getDB();

// ── Load current category ──────────────────────────────────────────────────────
$catStmt = $db->prepare(
    "SELECT id, slug, name, name_mn, icon, image FROM categories WHERE slug = ? LIMIT 1"
);
$catStmt->execute([$slug]);
$category = $catStmt->fetch();

// ── Filter params ──────────────────────────────────────────────────────────────
$filterType    = trim($_GET['type'] ?? '');
$filterShop    = trim($_GET['shop'] ?? '');
$filterSearch  = trim($_GET['search'] ?? '');
$filterDiscount = !empty($_GET['discount']);
$filterNewOnly  = !empty($_GET['new']);
$filterGender  = trim($_GET['gender'] ?? '');
$filterActivity = (int)($_GET['activity'] ?? 0);
$_allowedSorts = ['popular', 'newest', 'price_asc', 'price_desc'];
$filterSort    = in_array($_GET['sort'] ?? '', $_allowedSorts, true) ? $_GET['sort'] : 'newest';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 12;

// Whitelist type values
if (!in_array($filterType, ['ready', 'preorder'], true)) {
    $filterType = '';
}
if (!in_array($filterGender, ['men','women','unisex','kids'], true)) {
    $filterGender = '';
}

// ── Sidebar: categories with product counts ────────────────────────────────────
$catRows = $db->query(
    "SELECT c.id, c.slug, c.name, c.name_mn, c.icon,
            COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.show_in_store = 1
     GROUP BY c.id
     ORDER BY c.sort_order, c.name_mn"
)->fetchAll();

// ── Sidebar: shops ─────────────────────────────────────────────────────────────
$shopRows = $db->query(
    "SELECT id, slug, name, name_mn, color FROM shops ORDER BY name_mn, name"
)->fetchAll();

// ── Sidebar: activity types ────────────────────────────────────────────────────
$activityRows = $db->query(
    "SELECT * FROM activity_types WHERE is_active = 1 ORDER BY sort_order"
)->fetchAll();

// ── Page title (set before header) ────────────────────────────────────────────
if ($category) {
    $catLabel  = $category['name_mn'] ?: $category['name'];
    $page_title = $catLabel . ' | ' . s('site_name', 'Runners World');
} else {
    $page_title = 'Ангилал олдсонгүй | ' . s('site_name', 'Runners World');
}

// ── Build product query (only when category found) ────────────────────────────
$products      = [];
$totalProducts = 0;
$totalPages    = 1;
$offset        = ($page - 1) * $perPage;

if ($category) {
    $whereClauses = ['p.show_in_store = 1', 'p.category_id = ?'];
    $params       = [(int)$category['id']];

    if ($filterType !== '') {
        $whereClauses[] = 'p.type = ?';
        $params[]       = $filterType;
    }
    if ($filterShop !== '') {
        $whereClauses[] = 's.slug = ?';
        $params[]       = $filterShop;
    }
    if ($filterSearch !== '') {
        $whereClauses[] = '(p.name LIKE ? OR p.name_mn LIKE ?)';
        $like           = '%' . $filterSearch . '%';
        $params[]       = $like;
        $params[]       = $like;
    }
    if ($filterDiscount) {
        $whereClauses[] = 'p.original_price > p.price';
    }
    if ($filterGender !== '') {
        $whereClauses[] = 'p.gender = ?';
        $params[]       = $filterGender;
    }
    if ($filterActivity) {
        $whereClauses[] = 'EXISTS (SELECT 1 FROM product_activity_types pat WHERE pat.product_id = p.id AND pat.activity_type_id = ?)';
        $params[]       = $filterActivity;
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

    // Count
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
    $offset        = ($page - 1) * $perPage;

    // Products
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
}

// ── Helper: build URL preserving current params ────────────────────────────────
function categoryUrl(array $override = [], array $remove = []): string {
    global $slug;
    $allowed = ['type', 'shop', 'search', 'discount', 'new', 'sort', 'gender', 'activity', 'page'];
    $params  = [];
    foreach ($allowed as $key) {
        if (in_array($key, $remove, true)) continue;
        if (array_key_exists($key, $override)) {
            if ($override[$key] !== '' && $override[$key] !== null) {
                $params[$key] = $override[$key];
            }
        } elseif (!empty($_GET[$key])) {
            $params[$key] = $_GET[$key];
        }
    }
    $qs   = http_build_query($params);
    $base = url('category/' . rawurlencode($slug));
    return $base . ($qs ? '?' . $qs : '');
}

require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <?php if ($category): ?>
                    <h1 class="title-page">
                        <?php if ($category['icon']): ?>
                        <?= htmlspecialchars($category['icon']) ?>
                        <?php endif; ?>
                        <?= htmlspecialchars($catLabel) ?>
                    </h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('shop.php') ?>" class="h6 link">Дэлгүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal"><?= htmlspecialchars($catLabel) ?></h6></li>
                    </ul>
                    <?php else: ?>
                    <h1 class="title-page">Ангилал олдсонгүй</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('shop.php') ?>" class="h6 link">Дэлгүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Олдсонгүй</h6></li>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <?php if (!$category): ?>
        <!-- 404 Message -->
        <div class="flat-spacing">
            <div class="container">
                <div class="text-center py-5">
                    <i class="icon icon-tag" style="font-size:4rem;color:#ccc;"></i>
                    <h2 class="mt-4">Ангилал олдсонгүй</h2>
                    <p class="h6 text-main mt-2">Хайсан ангилал байхгүй эсвэл устгагдсан байна.</p>
                    <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn mt-4">
                        <i class="icon icon-arrow-left"></i> Бүх бараа харах
                    </a>
                </div>
            </div>
        </div>
        <!-- /404 Message -->
        <?php else: ?>

        <!-- Category Swiper -->
        <div class="flat-spacing pb-0">
            <div class="container">
                <div dir="ltr" class="swiper tf-swiper"
                     data-preview="6" data-tablet="5" data-mobile-sm="4" data-mobile="3"
                     data-space-lg="16" data-space-md="12" data-space="8">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn type-small btn-white animate-dark">
                                Бүгд
                            </a>
                        </div>
                        <?php foreach ($catRows as $cat): ?>
                        <div class="swiper-slide">
                            <a href="<?= url('category/' . htmlspecialchars($cat['slug'])) ?>"
                               class="tf-btn animate-btn type-small<?= $cat['slug'] === $slug ? ' btn-primary' : ' btn-white animate-dark' ?>">
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
                                <div class="canvas-body">

                                    <!-- Category links -->
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-category"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-category">
                                            <span class="h4 fw-semibold">Ангилал</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-category" class="collapse show">
                                            <ul class="collapse-body filter-group-check group-category">
                                                <li class="list-item">
                                                    <a href="<?= url('shop.php') ?>" class="link h6">
                                                        Бүгд
                                                    </a>
                                                </li>
                                                <?php foreach ($catRows as $cat): ?>
                                                <li class="list-item">
                                                    <a href="<?= url('category/' . htmlspecialchars($cat['slug'])) ?>"
                                                       class="link h6<?= $cat['slug'] === $slug ? ' active fw-semibold' : '' ?>">
                                                        <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
                                                        <span class="count"><?= (int)$cat['product_count'] ?></span>
                                                    </a>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Type filter -->
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
                                                    <a href="<?= categoryUrl([], ['type', 'page']) ?>"
                                                       class="link h6<?= $filterType === '' ? ' active fw-semibold' : '' ?>">
                                                        Бүгд
                                                    </a>
                                                </li>
                                                <li class="list-item">
                                                    <a href="<?= categoryUrl(['type' => 'ready'], ['page']) ?>"
                                                       class="link h6<?= $filterType === 'ready' ? ' active fw-semibold' : '' ?>">
                                                        Бэлэн бараа
                                                    </a>
                                                </li>
                                                <li class="list-item">
                                                    <a href="<?= categoryUrl(['type' => 'preorder'], ['page']) ?>"
                                                       class="link h6<?= $filterType === 'preorder' ? ' active fw-semibold' : '' ?>">
                                                        Урьдчилсан захиалга
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Discount filter -->
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
                                                    <?php if ($filterDiscount): ?>
                                                    <a href="<?= categoryUrl([], ['discount', 'page']) ?>" class="link h6 active fw-semibold">
                                                        Зөвхөн хямдарсан <i class="icon icon-close fs-12"></i>
                                                    </a>
                                                    <?php else: ?>
                                                    <a href="<?= categoryUrl(['discount' => '1'], ['page']) ?>" class="link h6">
                                                        Зөвхөн хямдарсан
                                                    </a>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- New arrivals filter -->
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
                                                    <?php if ($filterNewOnly): ?>
                                                    <a href="<?= categoryUrl([], ['new', 'page']) ?>" class="link h6 active fw-semibold">
                                                        ✨ Зөвхөн шинэ бараа <i class="icon icon-close fs-12"></i>
                                                    </a>
                                                    <?php else: ?>
                                                    <a href="<?= categoryUrl(['new' => '1'], ['page']) ?>" class="link h6">
                                                        ✨ Зөвхөн шинэ бараа
                                                    </a>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Shop filter -->
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
                                                <li class="list-item">
                                                    <a href="<?= categoryUrl([], ['shop', 'page']) ?>"
                                                       class="link h6<?= $filterShop === '' ? ' active fw-semibold' : '' ?>">
                                                        Бүгд
                                                    </a>
                                                </li>
                                                <?php foreach ($shopRows as $sh): ?>
                                                <li class="list-item">
                                                    <a href="<?= categoryUrl(['shop' => $sh['slug']], ['page']) ?>"
                                                       class="link h6<?= $filterShop === $sh['slug'] ? ' active fw-semibold' : '' ?>">
                                                        <?= htmlspecialchars($sh['name_mn'] ?: $sh['name']) ?>
                                                    </a>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Gender filter -->
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#sidebar-gender"
                                             role="button" data-bs-toggle="collapse"
                                             aria-expanded="true" aria-controls="sidebar-gender">
                                            <span class="h4 fw-semibold">Хүйс</span>
                                            <span class="icon icon-caret-down fs-20"></span>
                                        </div>
                                        <div id="sidebar-gender" class="collapse show">
                                            <ul class="collapse-body filter-group-check">
                                                <?php foreach ([''=>'Бүгд','men'=>'Эрэгтэй','women'=>'Эмэгтэй','unisex'=>'Унисекс','kids'=>'Хүүхэд'] as $val => $lbl): ?>
                                                <li class="list-item">
                                                    <a href="<?= categoryUrl(['gender' => $val], ['page']) ?>"
                                                       class="link h6<?= $filterGender === $val ? ' active fw-semibold' : '' ?>">
                                                        <?= $lbl ?>
                                                    </a>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Activity type filter -->
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
                                                <li class="list-item">
                                                    <a href="<?= categoryUrl(['activity' => ''], ['page']) ?>"
                                                       class="link h6<?= !$filterActivity ? ' active fw-semibold' : '' ?>">
                                                        Бүгд
                                                    </a>
                                                </li>
                                                <?php foreach ($activityRows as $ar): ?>
                                                <li class="list-item">
                                                    <a href="<?= categoryUrl(['activity' => $ar['id']], ['page']) ?>"
                                                       class="link h6<?= $filterActivity === (int)$ar['id'] ? ' active fw-semibold' : '' ?>">
                                                        <?= $ar['icon'] ? htmlspecialchars($ar['icon']) . ' ' : '' ?><?= htmlspecialchars($ar['name_mn']) ?>
                                                    </a>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div><!-- /canvas-body -->

                                <div class="canvas-bottom d-xl-none">
                                    <a href="<?= url('category/' . htmlspecialchars($slug)) ?>" class="tf-btn btn-reset">
                                        Шүүлтүүр арилгах
                                    </a>
                                </div>
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
                                        <a href="<?= categoryUrl(['sort' => $val], ['page']) ?>" class="select-item<?= $filterSort === $val ? ' active' : '' ?>" data-sort-value="<?= $val ?>">
                                            <span class="text-value-item"><?= htmlspecialchars($lbl) ?></span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Search within category -->
                            <form method="get" action="<?= url('category/' . htmlspecialchars($slug)) ?>"
                                  class="d-flex align-items-center gap-2">
                                <?php foreach (['type', 'shop', 'discount', 'new', 'sort', 'page'] as $pk): ?>
                                <?php if (!empty($_GET[$pk])): ?>
                                <input type="hidden" name="<?= htmlspecialchars($pk) ?>" value="<?= htmlspecialchars($_GET[$pk]) ?>">
                                <?php endif; ?>
                                <?php endforeach; ?>
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
                                <i class="icon icon-bag" style="font-size:3rem;color:#ccc;"></i>
                                <h4 class="mt-3">Бараа олдсонгүй</h4>
                                <p class="h6 text-main mt-2">Энэ ангилалд одоогоор бараа байхгүй байна.</p>
                                <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn mt-4">Бүх бараа харах</a>
                            </div>
                            <?php else: ?>
                            <div class="wrapper-shop tf-grid-layout tf-col-4" id="gridLayout">
                                <?php foreach ($products as $p): ?>
                                <?php
                                $p['category_name'] = $p['category_name_mn'] ?: ($p['category_name'] ?? '');
                                ?>
                                <?php require __DIR__ . '/includes/product-card.php'; ?>
                                <?php endforeach; ?>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                <div class="wd-full wg-pagination m-0 justify-content-center">
                                    <?php if ($page > 1): ?>
                                    <a href="<?= categoryUrl(['page' => $page - 1]) ?>" class="pagination-item h6 direct">
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
                                    <a href="<?= categoryUrl(['page' => 1]) ?>" class="pagination-item h6">1</a>
                                    <?php if ($start > 2): ?><span class="pagination-item h6 disabled">…</span><?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <?php if ($i === $page): ?>
                                    <span class="pagination-item h6 active"><?= $i ?></span>
                                    <?php else: ?>
                                    <a href="<?= categoryUrl(['page' => $i]) ?>" class="pagination-item h6"><?= $i ?></a>
                                    <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?><span class="pagination-item h6 disabled">…</span><?php endif; ?>
                                    <a href="<?= categoryUrl(['page' => $totalPages]) ?>" class="pagination-item h6"><?= $totalPages ?></a>
                                    <?php endif; ?>

                                    <?php if ($page < $totalPages): ?>
                                    <a href="<?= categoryUrl(['page' => $page + 1]) ?>" class="pagination-item h6 direct">
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
        <div class="flat-spacing-6 pt-0">
            <div class="container">
                <div class="row">
                    <div class="col-4">
                        <div class="tf-icon-box style-row">
                            <div class="icon"><i class="icon icon-truck"></i></div>
                            <div class="content">
                                <h5 class="fw-semibold">Үнэгүй хүргэлт</h5>
                                <p class="h6 text-main">50,000₮-с дээш захиалгад</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="tf-icon-box style-row">
                            <div class="icon"><i class="icon icon-shield-check"></i></div>
                            <div class="content">
                                <h5 class="fw-semibold">Жинхэнэ бараа</h5>
                                <p class="h6 text-main">Солонгосоос шууд</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="tf-icon-box style-row">
                            <div class="icon"><i class="icon icon-refresh"></i></div>
                            <div class="content">
                                <h5 class="fw-semibold">Буцаалт</h5>
                                <p class="h6 text-main">30 хоногийн дотор</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Box Icon -->

        <?php endif; // end if ($category) ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
