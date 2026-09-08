<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');
$db       = getDB();

// ── Page-specific prep ───────────────────────────────────────

// Banner slider(s) — hero_home location
$sliders = getBannersForLocation('hero_home');

// Parent categories only (Shoes / Clothes / Accessories — the bento box's 3 small tiles)
try {
    $homeCategories = $db->query("
        SELECT id, slug, name, name_mn, image
        FROM categories
        WHERE is_active = 1 AND parent_id IS NULL
        ORDER BY sort_order, name_mn
    ")->fetchAll();
} catch (Throwable) { $homeCategories = []; }

// Top 3 popular brands — the bento box's other 3 small tiles
$homeBentoBrands = array_slice(getPopularShops(), 0, 3);

// Static campaign art for the 2 big "shop by gender" tiles
$homeBentoMenImage   = assetUrl('images/cat-mens.jpeg');
$homeBentoWomenImage = assetUrl('images/cat-womens.jpeg');

// Featured products for "Deals of The Day" — three sets, one per tab.
$_baseProductSelect = "
    SELECT p.id, p.slug, p.name, p.name_mn, p.price, p.original_price,
           p.image, p.stock, p.rating, p.reviews, p.created_at, p.type, p.has_variants,
           c.slug AS category_slug, c.name_mn AS category_name_mn, c.name AS category_name,
           s.slug AS shop_slug, s.name_mn AS shop_name_mn, s.name AS shop_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN shops s ON s.id = p.shop_id
    WHERE p.is_active = 1 AND p.show_in_store = 1
";
try {
    $newArrivals = $db->query($_baseProductSelect . " ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
} catch (Throwable) { $newArrivals = []; }
try {
    $bestSellers = $db->query($_baseProductSelect . " ORDER BY p.rating DESC, p.reviews DESC, p.created_at DESC LIMIT 8")->fetchAll();
} catch (Throwable) { $bestSellers = []; }
try {
    $onSaleProducts = $db->query($_baseProductSelect . " AND p.original_price > p.price ORDER BY (1 - p.price / p.original_price) DESC LIMIT 8")->fetchAll();
} catch (Throwable) { $onSaleProducts = []; }

// Backwards-compat alias so any legacy references keep working
$featuredProducts = $newArrivals;

/**
 * Render a single product card. Used by both the tab panels below and any
 * future product grids.
 */

$extraStyles = <<<'EXTRA_CSS'
    <!-- Site-specific overrides -->
    <style>
        /* Multi-word nav labels (e.g. "Гүйлтийн гутал") must not wrap: this
           theme's nav row has a fixed line-height, so a wrapped second line
           renders outside the clipped header bounds and becomes invisible. */
        .mainmenu > li > a {
            white-space: nowrap;
        }
        /* Home "Shop By Categories" bento tiles: force 1:1 aspect, and fit the
           whole source photo inside it (source photos are portrait product
           shots, not square — "cover" would crop the product itself). */
        .rw-cat-square {
            aspect-ratio: 1 / 1;
            background: var(--color-gray-light, #f2f2f2);
        }
        .rw-cat-square > a,
        .rw-cat-square img {
            display: block;
            width: 100%;
            height: 100%;
        }
        /* !important: the theme's own .rbt-cat-box-5 .inner .rbt-image-portion a img
           rule is more specific and otherwise forces object-fit:cover + min-height:305px. */
        .rw-cat-square img {
            object-fit: contain !important;
            object-position: center;
            min-height: 0 !important;
        }
        /* Category tiles (not brand logos) fill the frame like the gender tiles,
           for a consistent look across all category images. */
        .rw-cat-square.rw-cat-fill {
            overflow: hidden;
        }
        .rw-cat-square.rw-cat-fill img {
            object-fit: cover !important;
        }

        /* The 2 big gender tiles use the bento design's native wide-banner
           ratio (1026x490 ≈ 2.09:1), not the small tiles' 1:1 square. These are
           curated campaign photos (not arbitrary product shots), so — unlike
           the small tiles — fill the frame and crop rather than letterbox. */
        .rw-cat-wide {
            aspect-ratio: 1026 / 490;
            background: var(--color-gray-light, #f2f2f2);
            overflow: hidden;
        }
        .rw-cat-wide > a,
        .rw-cat-wide img {
            display: block;
            width: 100%;
            height: 100%;
        }
        .rw-cat-wide img {
            object-fit: cover !important;
            object-position: center;
            min-height: 0 !important;
        }

        /* Product card images: force 1:1 for a consistent grid.
           The .rbt-card-img container wraps every product photo. */
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

        /* Brand logos: 1:1 tiles, keep whole logo visible (contain) so nothing gets cropped */
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
    </style>
EXTRA_CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- BIG BANNER -->
    <!-- Start Component Area -->
    <div class="rbt-component-area rbt-products-banner-area rbt-bg-color-white">
        <div class="container-fluid p-0">
            <!-- Start Product Banner Area -->
            <?php
            // Hero banner: single image only. First active banner in the
            // hero_home location wins (by sort_order). Fallback = design demo.
            $heroBanner = $sliders[0] ?? null;
            $heroUrl    = $heroBanner && !empty($heroBanner['btn_url']) ? url($heroBanner['btn_url']) : $urlShop;
            $heroImg    = $heroBanner && !empty($heroBanner['image'])
                ? fixImageUrl($heroBanner['image'])
                : assetUrl('images/hero-slider-banner/slider-gym-01.webp');
            $heroTitle  = $heroBanner
                ? trim(($heroBanner['title_mn'] ?? '') . ($heroBanner['subtitle_mn'] ? ' — ' . $heroBanner['subtitle_mn'] : ''))
                : '';
            ?>
            <div class="row row--0">
                <div class="col-lg-12 col-md-12 col-sm-12 col-12 d-flex justify-content-center">
                    <a href="<?= h($heroUrl) ?>" class="rbt-hero-slider-banner">
                        <img src="<?= h($heroImg) ?>" alt="<?= h($heroTitle ?: 'Banner') ?>">
                    </a>
                </div>
            </div>
            <!-- End Product Banner Area -->
        </div>
    </div>
    <!-- End Component Area -->

    <!-- CATEGORIES: bento box (3 categories, 2 big gender tiles, 3 popular brands) -->
    <!-- Start Component Area -->
    <div class="rbt-component-area rbt-catagories-styles-area rbt-section-gap3 rbt-bg-color-white">
        <div class="container">
            <div class="row mb--32">
                <div class="col-lg-12 d-flex justify-content-between flex-row align-items-center flex-wrap rbt-gap--16">
                    <div class="rbt-component-section-title rbt-gap--4 p-0 mb--0 border-0">
                        <h2 class="rbt-title rbt-scroll-trigger fade_in animation-order-2"><span class="rbt-bold--text">Ангилал</span></h2>
                    </div>
                    <a class="rbt-btn rbt-btn-secondary rbt-btn-sm-2 rbt-scroll-trigger fade_in animation-order-3"
                        href="<?= h($urlShop) ?>">
                        <span class="btn-text">Бүх ангилал</span>
                        <span class="btn-icon ml--4"><i class="fa-sharp fa-solid fa-arrow-up-right-from-square"></i></span>
                    </a>
                </div>
            </div>

            <?php
            // Small square tile: category or brand. Category photos fill the frame
            // (cover) like the gender tiles — brand logos still use "contain" so
            // the mark isn't cropped.
            function homeBentoSmallTile(string $url, string $img, string $label, bool $isBrand = false): void { ?>
                <div class="col-lg-1-5 col-lg-4 col-md-4 col-sm-12 col-6 mt--24">
                    <div class="rbt-cat-box rbt-cat-box-5 rbt-card-has-animated text-center">
                        <div class="inner">
                            <div class="rbt-image-portion rw-cat-square <?= $isBrand ? '' : 'rw-cat-fill' ?>">
                                <a href="<?= h($url) ?>"><img src="<?= h($img) ?>" alt="<?= h($label) ?>" <?= $isBrand ? 'style="padding:16%;box-sizing:border-box;"' : '' ?>></a>
                            </div>
                            <a href="<?= h($url) ?>" class="rbt-btn rbt-btn-white rbt-btn-md"><?= h($label) ?></a>
                        </div>
                        <div class="rbt-right-corner-portion">
                            <div class="rbt-corner-portion-wrapper">
                                <a href="<?= h($url) ?>" class="rbt-card-link-btn"><i class="fa-solid fa-arrow-up-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php }
            // Wide tile: shop-by-gender — same "photo + name button" treatment as the small tiles.
            function homeBentoWideTile(string $url, ?string $img, string $label): void { ?>
                <div class="col-lg-2-5 col-lg-8 col-md-8 col-sm-12 col-6 mt--24">
                    <div class="rbt-cat-box rbt-cat-box-5 rbt-card-has-animated wider-coloumn text-center">
                        <div class="inner">
                            <div class="rbt-image-portion rw-cat-wide">
                                <a href="<?= h($url) ?>"><img src="<?= h($img ?: assetUrl('images/catagory-img/cat-bg-lg-01.webp')) ?>" alt="<?= h($label) ?>"></a>
                            </div>
                            <a href="<?= h($url) ?>" class="rbt-btn rbt-btn-white rbt-btn-md"><?= h($label) ?></a>
                        </div>
                        <div class="rbt-right-corner-portion">
                            <div class="rbt-corner-portion-wrapper">
                                <a href="<?= h($url) ?>" class="rbt-card-link-btn"><i class="fa-solid fa-arrow-up-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php }

            $catBySlug = array_column($homeCategories, null, 'slug');
            function homeBentoCatTile(array $catBySlug, string $slug, string $fallbackLabel): void {
                $c = $catBySlug[$slug] ?? null;
                $url   = url('shop?category=' . urlencode($slug));
                $img   = !empty($c['image']) ? fixImageUrl($c['image']) : assetUrl('images/catagory-img/cat-bg-06.webp');
                $label = $c ? ($c['name_mn'] ?: $c['name']) : $fallbackLabel;
                homeBentoSmallTile($url, $img, $label);
            }
            ?>

            <div class="row row--12 mt_dec--24 rbt-mobile-row">
                <?php homeBentoCatTile($catBySlug, 'shoes', 'Пүүз'); ?>
                <?php homeBentoCatTile($catBySlug, 'clothes', 'Хувцас'); ?>
                <?php homeBentoCatTile($catBySlug, 'accessories', 'Дагалдах'); ?>

                <?php homeBentoWideTile(url('shop?gender=men'), $homeBentoMenImage, 'Эрэгтэй'); ?>

                <?php if (!empty($homeBentoBrands[0])): $b = $homeBentoBrands[0];
                    homeBentoSmallTile(url('shop?shop=' . urlencode($b['slug'])), !empty($b['logo']) ? fixImageUrl($b['logo']) : assetUrl('images/brands/brand-d-01.webp'), $b['name_mn'] ?: $b['name'], true);
                endif; ?>

                <?php homeBentoWideTile(url('shop?gender=women'), $homeBentoWomenImage, 'Эмэгтэй'); ?>

                <?php if (!empty($homeBentoBrands[1])): $b = $homeBentoBrands[1];
                    homeBentoSmallTile(url('shop?shop=' . urlencode($b['slug'])), !empty($b['logo']) ? fixImageUrl($b['logo']) : assetUrl('images/brands/brand-d-01.webp'), $b['name_mn'] ?: $b['name'], true);
                endif; ?>
                <?php if (!empty($homeBentoBrands[2])): $b = $homeBentoBrands[2];
                    homeBentoSmallTile(url('shop?shop=' . urlencode($b['slug'])), !empty($b['logo']) ? fixImageUrl($b['logo']) : assetUrl('images/brands/brand-d-01.webp'), $b['name_mn'] ?: $b['name'], true);
                endif; ?>
            </div>
        </div>
    </div>
    <!-- End Component Area -->

    <!-- FEATURED -->
    <!-- Start Component Area -->
    <div id="rbt-product-block-01"
        class="rbt-component-area rbt-catagories-area rbt-section-gap2 rbt-bg-color-gray-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div
                        class="rbt-component-section-title d-flex flex-row justify-content-between align-items-center p-0 mb--32 mb_sm--16 border-0">
                        <h2 class="rbt-title rbt-scroll-trigger fade_in animation-order-1 h4"><span class="rbt-bold--text">Онцлох бараа</span></h2>
                        <div class="mobile-horizontal-scroll-section">
                            <div id="dealsTabs"
                                class="rbt-product-nav-section rbt-nav-effect-activation rbt-scroll-trigger fade_in animation-order-2">
                                <ul class="rbt-product-nav-grp">
                                    <li><a href="#" class="rbt-product-nav active" data-deals-tab="new">Шинэ ирсэн</a></li>
                                    <li><a href="#" class="rbt-product-nav" data-deals-tab="best">Эрэлттэй</a></li>
                                    <li><a href="#" class="rbt-product-nav" data-deals-tab="sale">Хямдралтай</a></li>
                                </ul>
                                <ul class="rbt-product-nav-grp">
                                    <li><a href="<?= h($urlShop) ?>" class="rbt-product-nav">Бүгд</a></li>
                                </ul>
                                <span class="rbt-bg-highlight"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $_dealsTabs = [
                'new'  => ['label' => 'Шинэ ирсэн',  'items' => $newArrivals],
                'best' => ['label' => 'Эрэлттэй',    'items' => $bestSellers],
                'sale' => ['label' => 'Хямдралтай',  'items' => $onSaleProducts],
            ];
            ?>
            <?php foreach ($_dealsTabs as $tabKey => $tab): ?>
            <!-- Start Card Area -->
            <div class="row row--12 mt_dec--24 deals-tab-panel" data-deals-panel="<?= h($tabKey) ?>"
                 <?= $tabKey !== 'new' ? 'style="display:none;"' : '' ?>>
                <?php if (empty($tab['items'])): ?>
                    <div class="col-12 text-center py-5"><p class="text-muted">Бараа алга.</p></div>
                <?php else: ?>
                    <?php foreach ($tab['items'] as $i => $prod): renderProductCard($prod, $i, 'col-xxl-3 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-6'); endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <!-- End Card Area -->
        </div>
    </div>
    <!-- End Component Area -->

    <!-- TOP BRANDS -->
    <!-- Start Component Area -->
    <div class="rbt-component-area rbt-brands-area rbt-section-gap rbt-bg-color-white">
        <div class="container">

            <div class="row">
                <div class="col-lg-12 d-flex justify-content-between flex-row align-items-center flex-wrap mb--32 rbt-gap--16 pb-2">
                    <div class="rbt-component-section-title rbt-gap--4 p-0 mb--0 border-0">
                        <h2 class="rbt-title rbt-scroll-trigger fade_in animation-order-2"><span class="rbt-bold--text">Брэндүүд</span></h2>
                    </div>
                    <a class="rbt-btn rbt-btn-secondary rbt-btn-sm-2 rbt-scroll-trigger fade_in animation-order-3"
                        href="<?= h(url('brands')) ?>">
                        <span class="btn-text">Бүх брэнд</span>
                        <span class="btn-icon ml--4"><i class="fa-sharp fa-solid fa-arrow-up-right-from-square"></i></span>
                    </a>
                </div>
            </div>

            <!-- Start Brands Area -->
            <div class="row row--12 mt_dec--24">
                <?php $_shops = getPopularShops(); if (empty($_shops)): ?>
                    <div class="col-12 text-center py-4"><p class="text-muted">Брэнд байхгүй.</p></div>
                <?php else: ?>
                    <?php foreach ($_shops as $i => $shop):
                        $shopUrl   = url('shop?shop=' . urlencode($shop['slug']));
                        $shopLogo  = !empty($shop['logo']) ? fixImageUrl($shop['logo']) : assetUrl('images/brands/brand-d-01.webp');
                        $shopLabel = $shop['name_mn'] ?: $shop['name'];
                        $order     = ($i % 12) + 1;
                    ?>
                <div class="col-lg-2 col-md-4 col-sm-4 col-4 mt--24">
                    <div class="rbt-brand text-center style-four rbt-scroll-trigger fade_in animation-order-<?= $order ?>">
                        <a href="<?= h($shopUrl) ?>" title="<?= h($shopLabel) ?>">
                            <div class="rbt-brand-inner">
                                <div class="brand-image rbt-scroll-trigger fade_in animation-order-<?= $order ?>">
                                    <img src="<?= h($shopLogo) ?>" alt="<?= h($shopLabel) ?>">
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- End Brands Area -->

        </div>
    </div>
    <!-- End Component Area -->

<?php require __DIR__ . '/includes/footer.php'; ?>