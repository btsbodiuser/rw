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

// ── BRANDS ────────────────────────────────────────────────────
$page_title = 'Брэндүүд — ' . $siteName;

try {
    // "outlet" is a special filter tag stored in this table, not a real brand — exclude it here.
    $allShopsRaw = $db->query("SELECT id, slug, name, name_mn, logo, sort_order FROM shops WHERE is_active = 1 AND slug != 'outlet' ORDER BY name")->fetchAll();
} catch (Throwable) {
    $allShopsRaw = [];
}

// Which letters actually have a brand (only these get a tab, matching runnersneed's behaviour)
$brandsByLetter = [];
foreach ($allShopsRaw as $s) {
    $letter = mb_strtoupper(mb_substr($s['name'], 0, 1, 'UTF-8'), 'UTF-8');
    if (!ctype_alpha($letter)) $letter = '#';
    $brandsByLetter[$letter][] = $s;
}
ksort($brandsByLetter);
$availableLetters = array_keys($brandsByLetter);

$popularShops = array_values(array_filter($allShopsRaw, fn($s) => (int)$s['sort_order'] < 100));
usort($popularShops, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

$brandsFilter = strtoupper(trim((string)($_GET['filter'] ?? 'all')));
if ($brandsFilter !== 'ALL' && $brandsFilter !== 'POPULAR' && !in_array($brandsFilter, $availableLetters, true)) {
    $brandsFilter = 'ALL';
}

// Groups to render: ['LETTER' => [shops...]] — a single-letter or popular
// filter renders one group; "all" renders every letter in order.
if ($brandsFilter === 'POPULAR') {
    $brandGroups = ['' => $popularShops];
} elseif ($brandsFilter === 'ALL') {
    $brandGroups = $brandsByLetter;
} else {
    $brandGroups = [$brandsFilter => $brandsByLetter[$brandsFilter] ?? []];
}

function brandShopUrl(array $s): string {
    return url('shop?shop=' . urlencode($s['slug']));
}

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

        /* Brand logo tiles: 1:1, logo kept whole (contain) so nothing crops */
        .rbt-brand .brand-image {
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .rbt-brand .brand-image img {
            max-width: 80%;
            max-height: 80%;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .rw-brand-fallback {
            font-size: 28px;
            font-weight: 700;
            color: var(--color-body, #737373);
        }
        .rw-brand-name {
            margin-top: 10px;
            margin-bottom: 0;
            font-size: 14px;
            color: var(--color-body, #737373);
        }
        .rw-brand-letter-heading {
            font-size: 22px;
            font-weight: 700;
            margin: 32px 0 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--color-gray-200, #e5e5e5);
        }

        /* A-Z filter bar */
        .rw-brand-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 16px 0;
            border-bottom: 1px solid var(--color-gray-200, #e5e5e5);
        }
        .rw-brand-filter-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            color: var(--color-body, #737373);
            background: var(--color-gray-light, #f5f5f5);
        }
        .rw-brand-filter-item:hover {
            background: var(--color-gray-200, #e5e5e5);
            color: var(--color-heading, #1a1a1a);
        }
        .rw-brand-filter-item.active {
            background: var(--color-primary, #215ADA);
            color: #fff;
        }
    </style>
EXTRA_CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- SHOP BREADCRUMB -->
    <!-- BRANDS BREADCRUMB -->
    <div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active">Брэндүүд</li>
                </ul>
                <h1 class="title h3 mt--10">Брэндүүд</h1>
            </div>
        </div>
    </div>

    <!-- BRANDS FILTER -->
    <div class="rbt-bg-color-white pb--20">
        <div class="container">
            <div class="rw-brand-filter-bar">
                <a href="<?= h(url('brands?filter=popular')) ?>" class="rw-brand-filter-item <?= $brandsFilter === 'POPULAR' ? 'active' : '' ?>">Түгээмэл</a>
                <a href="<?= h(url('brands?filter=all')) ?>" class="rw-brand-filter-item <?= $brandsFilter === 'ALL' ? 'active' : '' ?>">Бүгд</a>
                <?php foreach ($availableLetters as $letter): ?>
                <a href="<?= h(url('brands?filter=' . urlencode($letter))) ?>" class="rw-brand-filter-item <?= $brandsFilter === $letter ? 'active' : '' ?>"><?= h($letter) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- BRANDS MAIN -->
    <div class="rbt-component-area rbt-brands-area rbt-section-gapBottom rbt-bg-color-white">
        <div class="container">
            <?php if (!array_filter($brandGroups)): ?>
            <div class="text-center py-5">
                <p class="text-muted mb-0">Брэнд олдсонгүй.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($brandGroups as $letter => $groupShops): ?>
                <?php if (!$groupShops) continue; ?>
                <?php if ($letter !== ''): ?>
                <div class="row">
                    <div class="col-12">
                        <h2 class="rw-brand-letter-heading"><?= h($letter) ?></h2>
                    </div>
                </div>
                <?php endif; ?>
                <div class="row row--12 mt_dec--24 mb--40">
                    <?php foreach ($groupShops as $s): ?>
                    <div class="col-lg-2 col-md-4 col-sm-4 col-4 mt--24">
                        <div class="rbt-brand text-center style-four">
                            <a href="<?= h(brandShopUrl($s)) ?>">
                                <div class="rbt-brand-inner">
                                    <div class="brand-image">
                                        <?php if ($s['logo']): ?>
                                        <img src="<?= h(fixImageUrl($s['logo'])) ?>" alt="<?= h($s['name_mn'] ?: $s['name']) ?>">
                                        <?php else: ?>
                                        <span class="rw-brand-fallback"><?= h(mb_substr($s['name_mn'] ?: $s['name'], 0, 1, 'UTF-8')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="rw-brand-name"><?= h($s['name_mn'] ?: $s['name']) ?></p>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>


    <!-- ALL CATEGORIES -->


<?php require __DIR__ . '/includes/footer.php'; ?>
