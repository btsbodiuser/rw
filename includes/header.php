<?php
/**
 * Shared storefront header partial.
 *
 * Emits <!DOCTYPE> through the end of the two side-nav offcanvas panels
 * (categories + cart). Every root-level page requires this after
 * `includes/config.php`.
 *
 * Callers may set (before requiring):
 *   $page_title   — <title> text (required)
 *   $extraStyles  — page-specific CSS injected inside the shared <style> block
 *   $bodyClass    — override the default body class (default: rbt-header-sticky)
 */

$db          = getDB();
$siteName    = s('site_name', 'Runners World');
$siteLogoRaw = s('site_logo', '');
$logoUrl     = $siteLogoRaw ? fixImageUrl($siteLogoRaw) : assetUrl('images/logo/logo.webp');
$faviconRaw  = s('site_favicon', '');
$faviconUrl  = $faviconRaw ? fixImageUrl($faviconRaw) : assetUrl('images/favicon.png');
$sitePhone   = s('phone', s('site_phone', ''));

$cartCount    = cartCount();
$cartTotalFmt = formatPrice(cartTotal());
$loggedIn     = isLoggedIn();
$sessionUser  = getSessionUser();

$urlHome    = url();
$urlShop    = url('shop');
$urlCart    = url('cart');
$urlAccount = url('account');
$urlLogin   = url('login');
$urlLogout  = url('logout-action');

// Nav data (mega menu) — built from the REAL category parent/child tree
// (not a guessed slug list), filtered to subcategories that actually have
// purchasable products so the menu never links to an empty result.
try {
    // Per-gender counts too, so the men/women megamenus can skip subcategories
    // that only have items for the other gender (e.g. "bras" under Хувцас
    // should never appear when browsing men).
    $navAllCategories = $db->query("
        SELECT c.id, c.parent_id, c.slug, c.name, c.name_mn, c.sort_order,
               COALESCE(SUM(CASE WHEN p.gender IN ('men','unisex')   THEN 1 ELSE 0 END), 0) AS mens_count,
               COALESCE(SUM(CASE WHEN p.gender IN ('women','unisex') THEN 1 ELSE 0 END), 0) AS womens_count,
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.show_in_store = 1
        WHERE c.is_active = 1
        GROUP BY c.id, c.parent_id, c.slug, c.name, c.name_mn, c.sort_order
        ORDER BY c.sort_order, c.name_mn
    ")->fetchAll();
} catch (Throwable) { $navAllCategories = []; }

$navTopCategories = []; // slug => row, top-level categories (parent_id IS NULL)
$navSubCategories = []; // parent_id => [rows...], only children with product_count > 0
foreach ($navAllCategories as $c) {
    if (empty($c['parent_id'])) {
        $navTopCategories[$c['slug']] = $c;
    } elseif ((int)$c['product_count'] > 0) {
        $navSubCategories[$c['parent_id']][] = $c;
    }
}

try { $navShoeTypes = $db->query("SELECT slug, name_mn, name FROM shoe_types         WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navShoeTypes = []; }
try { $navRunTypes  = $db->query("SELECT slug, name_mn, name FROM run_types          WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navRunTypes  = []; }
try { $navGaitTypes = $db->query("SELECT slug, name_mn, name FROM gait_types         WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navGaitTypes = []; }
try { $navFeatures  = $db->query("SELECT slug, name_mn, name FROM technical_features WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navFeatures  = []; }

$navBrands = getPopularShops();

// Popular searches (derived — no dedicated table yet): top parent categories +
// top brands. Cap the list at 12 chips and link each into shop.php with the
// right filter, so clicks land on real filtered results.
$popularSearchTags = [];
foreach (array_slice(array_values($navTopCategories), 0, 6) as $c) {
    $label = $c['name_mn'] ?: $c['name'];
    if ($label === '') continue;
    $popularSearchTags[] = ['label' => $label, 'url' => url('shop?category=' . urlencode($c['slug']))];
}
foreach (array_slice($navBrands, 0, 6) as $b) {
    $label = $b['name_mn'] ?? '' ?: ($b['name'] ?? '');
    if ($label === '') continue;
    $popularSearchTags[] = ['label' => $label, 'url' => url('shop?shop=' . urlencode($b['slug']))];
}
$popularSearchTags = array_slice($popularSearchTags, 0, 12);

$extraStyles = $extraStyles ?? '';
$bodyClass   = $bodyClass   ?? 'rbt-header-sticky';
$page_title  = $page_title  ?? $siteName;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <base href="<?= htmlspecialchars(getBaseUrl()) ?>">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="robots" content="index, follow">
    <meta name="description"
        content="<?= htmlspecialchars(s('site_description', $siteName)) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Fonts: Inter (body) + Oswald (display) + Caveat (script) + Rubik (decorative).
         All four have full Cyrillic + Latin coverage so Mongolian and English render
         with matching metrics — replaces Cabin/Bebas Neue/Caprasimo which had partial
         or no Cyrillic support. -->
    <link rel="preload"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@400;500;600;700&family=Caveat:wght@400;500;600;700&family=Rubik:wght@500;700&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext"
        as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@400;500;600;700&family=Caveat:wght@400;500;600;700&family=Rubik:wght@500;700&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
    </noscript>
    <link rel="preload" href="assets/fonts/fa-brands-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/fa-regular-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= h($faviconUrl) ?>">

    <!-- CSS
	============================================ -->
    <link rel="stylesheet" href="assets/css/vendor/bootstrap.min.css">
    <link rel="preload" href="assets/css/plugins/fontawesome-all.min.css" as="style"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="assets/css/plugins/fontawesome-all.min.css">
    </noscript>
    <link rel="stylesheet" href="assets/css/plugins/swiper.css">
    <link rel="stylesheet" href="assets/css/plugins/fancybox.css">
    <link rel="stylesheet" href="assets/css/plugins/mavo.css">
    <link rel="stylesheet" href="assets/css/plugins/odometer.css">
    <link rel="stylesheet" href="assets/css/plugins/animation.css">
    <link rel="stylesheet" href="assets/css/plugins/bootstrap-select.min.css">
    <link rel="stylesheet" href="assets/css/plugins/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="assets/css/style.min.css">

    <!-- Site-specific overrides -->
    <style>
        /* Font stack override — theme defaults (Cabin/Bebas Neue/Caprasimo)
           don't fully cover Cyrillic. Point the theme's CSS variables at
           Google Fonts that do, keeping the same visual roles (UI / display /
           script / decorative). :root is enough — the theme's stylesheet
           reads these vars everywhere. */
        :root {
            --font-primary:    "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            --font-secondary:  "Caveat", cursive;
            --font-tertiary:   "Oswald", "Impact", sans-serif;
            --font-quaternary: "Rubik", "Inter", sans-serif;

            /* Brand palette — derived from the Runners`World logo (electric
               cyan on carbon-black). Overrides the theme's default blue/orange
               so every CTA, price, active-state and gradient picks it up. */
            --color-primary:   #00B7FF;
            --color-secondary: #0284C7;
            --color-heading:   #0A0A0A;
        }
        body {
            font-family: var(--font-primary);
        }

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
        /* Mega-menu brand strip: some logos ship at full resolution and blow
           out the row. Cap each cell to a uniform box and letterbox the logo
           inside so tall/wide variants sit centered without distortion. */
        .rbt-nav-brand-list > li {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rbt-nav-brand-list > li > a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 96px;
            height: 48px;
        }
        .rbt-nav-brand-list > li > a > img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        /* Preloader: brand logo replaces the theme's stock cart animation.
           Soft pulse so it reads as "loading" without being distracting. */
        .rbt-preloader-logo {
            width: 140px;
            height: auto;
            display: block;
            margin: 0 auto 16px;
            animation: rwPreloaderPulse 1.4s ease-in-out infinite;
        }
        @keyframes rwPreloaderPulse {
            0%, 100% { opacity: 1;   transform: scale(1); }
            50%      { opacity: .55; transform: scale(.94); }
        }
        <?= $extraStyles ?>
    </style>
</head>

<body class="<?= h($bodyClass) ?>">
    <?php $rwFlash = getFlash(); if ($rwFlash): ?>
    <div class="container mt--16">
        <div class="alert alert-<?= h($rwFlash['type'] === 'error' ? 'danger' : $rwFlash['type']) ?> alert-dismissible fade show" role="alert">
            <?= h($rwFlash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
    <header class="rbt-header rbt-header-2">

        <div
            class="rbt-header-wrapper rbt-header-sticky-activation rbt-header-wrapper-one header-space-between rbt-bg-color-white header-not-transparent header-sticky plr--0 position-relative z-5">
            <div class="rbt-topbar-section rbt-topbar-one">
                <div class="container">
                    <?php $topbarAnnouncements = getBannersForLocation('topbar_announcement'); ?>
                    <div class="row align-items-center d-none d-md-flex mlr--0 row--0">
                        <?php if ($topbarAnnouncements): ?>
                        <div class="col-lg-6 col-md-6 col-12">
                            <div class="rbt-fancy-item fancy-menu-text fancy-menu-start">
                                <div class="rbt-fancy-text">
                                    <div class="rbt-text-swiper-container rbt-arrow-vertical">
                                        <div class="swiper-wrapper">
                                            <?php foreach ($topbarAnnouncements as $_ann): ?>
                                            <div class="swiper-slide">
                                                <?= h($_ann['title_mn'] ?? '') ?>
                                                <?php if (!empty($_ann['btn_text']) && !empty($_ann['btn_url'])): ?>
                                                <a class="rbt-fancy-link ml--4" href="<?= h($_ann['btn_url']) ?>"><?= h($_ann['btn_text']) ?></a>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($topbarAnnouncements) > 1): ?>
                                        <div class="rbt-verticle-arrow rbt-arrow-prev">
                                            <i class="fa-regular fa-chevron-up"></i>
                                        </div>
                                        <div class="rbt-verticle-arrow rbt-arrow-next">
                                            <i class="fa-regular fa-chevron-down"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="<?= $topbarAnnouncements ? 'col-lg-6 col-md-6 col-12' : 'col-12' ?>">
                            <div
                                class="rbt-header-sec-col rbt-header-right rbt-fancy-item fancy-menu-address fancy-menu-end">
                                <div class="rbt-header-content m--0">
                                    <ul class="rbt-quick-access d-none d-lg-flex">
                                        <li class="rbt-access-box">
                                            <div class="header-info">
                                                <a href="<?= h(url('contact')) ?>" class="rbt-access-link">Дэлгүүрийн байршил</a>
                                            </div>
                                            <div class="header-info">
                                                <a href="<?= h(url('track-order')) ?>" class="rbt-access-link">Захиалга шалгах</a>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rbt-separator-mid">
                <hr class="rbt-separator rbt-separator-gray100 m-0">
            </div>
            <div class="rbt-wrapper-middle rbt-header-middle-one">
                <div class="container">
                    <div class="mainbar-row @@navigationEnd align-items-center">
                        <div class="header-left">
                            <!-- Start Mobile-Menu-Bar -->
                            <div class="mobile-menu-bar d-block d-xl-none">
                                <div class="hamberger">
                                    <button class="hamberger-button rbt-round-btn">
                                        <i class="fa-solid fa-bars"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Start Mobile-Menu-Bar -->
                            <div class="rbt-header-content">
                                <div class="header-info">
                                    <div class="logo">
                                        <a href="<?= h($urlHome) ?>">
                                            <img src="<?= h($logoUrl) ?>" alt="Ecommerce Logo Images">
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>


                        <div class="rbt-header-content d-none d-xl-block">
                            <div class="header-info">
                                <div class="rbt-search-with-category uni-header-swc-one">
                                    <form method="get" action="<?= h($urlShop) ?>">
                                        <div class="rbt-inner-search-field border-0">
                                            <div class="rbt-search-input-section rbt-inner-search-label-animate-activation">
                                                <input type="text" name="search" placeholder="Юу хайх вэ?" value="<?= h($_GET['search'] ?? '') ?>">
                                            </div>
                                            <button class="rbt-round-btn search-btn" type="submit"
                                                aria-label="Search"><i
                                                    class="fa-sharp fa-solid fa-magnifying-glass"></i></button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="header-right">
                            <!-- Navbar Icons -->
                            <ul class="rbt-quick-access">
                                <li
                                    class="rbt-access-box rbt-scroll-trigger fade_in animation-order-1 rbt-access-box-has-bg-hover d-none d-lg-flex">
                                    <a href="<?= $sitePhone ? 'tel:' . h(preg_replace('/\s+/', '', $sitePhone)) : '#' ?>" class="rbt-access-box-wrapper">
                                        <div class="rbt-round-btn rbt-bg-static-gray">
                                            <i class="fa-regular fa-phone"></i>
                                        </div>
                                        <div class="content p-0">
                                            <p>Утас</p>
                                            <span><?= h($sitePhone ?: '—') ?></span>
                                        </div>
                                    </a>
                                </li>
                                <li
                                    class="rbt-access-box rbt-scroll-trigger fade_in animation-order-3 rbt-access-box-has-bg-hover d-none d-lg-flex">
                                    <?php if ($loggedIn): ?>
                                    <a href="<?= h($urlAccount) ?>" class="rbt-access-box-wrapper">
                                        <div class="rbt-round-btn rbt-bg-static-gray">
                                            <i class="fa-regular fa-user"></i>
                                        </div>
                                        <div class="content">
                                            <p><?= h($sessionUser['name'] ?? 'My account') ?></p>
                                            <span>Хувийн бүртгэл</span>
                                        </div>
                                    </a>
                                    <?php else: ?>
                                    <a href="<?= h($urlLogin) ?>" class="rbt-access-box-wrapper">
                                        <div class="rbt-round-btn rbt-bg-static-gray">
                                            <i class="fa-regular fa-user"></i>
                                        </div>
                                        <div class="content">
                                            <p>Нэвтрэх / Бүртгүүлэх</p>
                                            <span>Хувийн бүртгэл</span>
                                        </div>
                                    </a>
                                    <?php endif; ?>
                                </li>
                                <li
                                    class="rbt-access-box rbt-scroll-trigger fade_in animation-order-3 rbt-access-box-has-bg-hover d-flex d-lg-none">
                                    <a class="search-trigger-active rbt-round-btn rbt-bg-static-gray rbt-modern-close-btn"
                                        href="#">
                                        <i class="fa-regular fa-search search-icon"></i>
                                        <div class="modern-close-wrapper"></div>
                                    </a>
                                </li>
                                <li
                                    class="rbt-access-box rbt-scroll-trigger fade_in animation-order-3 rbt-access-box-has-bg-hover rbt-mini-cart">
                                    <a href="#" class="rbt-access-box-wrapper rbt-cart-sidenav-activation">
                                        <div class="rbt-round-btn rbt-bg-static-gray">
                                            <i class="fa-regular fa-bag-shopping"></i>
                                            <span class="access-box-count rbt-shiny" id="rbt-cart-count-1"><?= $cartCount ?></span>
                                        </div>
                                        <div class="content p-0">
                                            <p>Total Cart</p>
                                            <span id="rbt-cart-total-1">Total <?= h($cartTotalFmt) ?></span>
                                        </div>
                                    </a>
                                </li>
                            </ul>


                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- Start Header Mid -->
        <div class="rbt-header-middle position-relative rbt-header-mid-1 rbt-bg-color-primary d-none d-xl-block">
            <div class="container">
                <div class="rbt-header-sec align-items-center @@flexDirection">

                    <div class="rbt-main-navigation d-none d-xl-block">
                        <nav class="rbt-mainmenu-nav">
                            <?php require __DIR__ . "/nav-menu.php"; ?>
                        </nav>
                    </div>

                </div>
            </div>
        </div>
        <!-- End Header Top -->





        <div
            class="rbt-header-common-sticky-activation rbt-header-wrapper-common justify-content-between rbt-bg-color-white">
            <?php $topbarAnnouncements = getBannersForLocation('topbar_announcement'); ?>
            <?php if ($topbarAnnouncements): ?>
            <div
                class="rbt-header-campaign rbt-header-campaign-1 rbt-header-top-news rbt-topbar-bg-img rbt-topbar-bg-one w-100">
                <div class="rbt-corner-portion-wrapper">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-6">
                                <div class="inner justify-content-center">
                                    <div class="rbt-text-swiper-container rbt-arrow-vertical">
                                        <div class="swiper-wrapper">
                                            <?php foreach ($topbarAnnouncements as $_ann): ?>
                                            <div class="swiper-slide">
                                                <div class="rbt-fancy-item fancy-menu-text fancy-menu-center">
                                                    <p class="rbt-fancy-text rbt-text-color-white">
                                                        <?= h($_ann['title_mn'] ?? '') ?>
                                                        <?php if (!empty($_ann['btn_text']) && !empty($_ann['btn_url'])): ?>
                                                        <a class="rbt-text-color-white ml--4" href="<?= h($_ann['btn_url']) ?>"><?= h($_ann['btn_text']) ?></a>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <?php if (count($topbarAnnouncements) > 1): ?>
                                        <div class="rbt-verticle-arrow rbt-text-color-white rbt-arrow-prev">
                                            <i class="fa-regular fa-chevron-up"></i>
                                        </div>
                                        <div class="rbt-verticle-arrow rbt-text-color-white rbt-arrow-next">
                                            <i class="fa-regular fa-chevron-down"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="icon-close position-right">
                    <button class="rbt-round-btn btn-white-off bgsection-activation" aria-label="Close Button">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <div class="container">
                <div class="mainbar-row rbt-mainbar-row-md-height  align-items-center">
                    <div class="header-left">
                        <div class="rbt-header-content d-flex">
                            <div class="header-info d-xl-block d-none">
                                <div class="logo rbt-logo-height-sm">
                                    <a href="<?= h($urlHome) ?>">
                                        <img src="<?= h($logoUrl) ?>" alt="Ecommerce Logo Images">
                                    </a>
                                </div>
                            </div>
                        </div>
                        <!-- Start Mobile-Menu-Bar -->
                        <div class="mobile-menu-bar d-block d-xl-none">
                            <div class="hamberger">
                                <button class="hamberger-button rbt-round-btn">
                                    <i class="fa-solid fa-bars"></i>
                                </button>
                            </div>
                        </div>
                        <!-- Start Mobile-Menu-Bar -->
                    </div>

                    <div class="header-info d-xl-none d-block">
                        <div class="logo">
                            <a href="<?= h($urlHome) ?>">
                                <img src="<?= h($logoUrl) ?>" alt="Ecommerce Logo Images">
                            </a>
                        </div>
                    </div>

                    <div class="rbt-header-content d-none d-xl-block">
                        <div class="header-info">
                            <nav class="rbt-mainmenu-nav">
                            <?php require __DIR__ . "/nav-menu.php"; ?>
                            </nav>
                        </div>
                    </div>

                    <div class="header-right">
                        <!-- Navbar Icons -->
                        <ul class="rbt-quick-access rbt-gap--12">

                            <li class="rbt-access-box rbt-scroll-trigger fade_in animation-order-3 tooltips tooltip-distance-lg"
                                data-tooltip="Search" data-tooltip-position="bottom">
                                <a class="rbt-round-btn has-rbt-md-fsize rbt-common-search-trigger-active rbt-modern-close-btn"
                                    href="#">
                                    <i class="fa-regular fa-search search-icon"></i>
                                    <div class="modern-close-wrapper"></div>
                                </a>
                            </li>

                            <li class="rbt-access-box rbt-scroll-trigger fade_in animation-order-3 d-none d-lg-flex tooltips tooltip-distance-lg"
                                data-tooltip="<?= $loggedIn ? 'Хувийн бүртгэл' : 'Нэвтрэх' ?>" data-tooltip-position="bottom">
                                <a class="rbt-round-btn has-rbt-md-fsize" href="<?= h($loggedIn ? $urlAccount : $urlLogin) ?>">
                                    <i class="fa-regular fa-user"></i>
                                </a>
                            </li>


                            <li class="rbt-access-box rbt-scroll-trigger fade_in animation-order-5 rbt-access-box-has-bg-hover rbt-mini-cart tooltips tooltip-distance-lg"
                                data-tooltip="Cart" data-tooltip-position="bottom">
                                <a class="rbt-cart-sidenav-activation" href="#!">
                                    <span class="rbt-round-btn has-rbt-md-fsize">
                                        <i class="fa-regular fa-bag-shopping"></i>
                                        <span class="access-box-count rbt-shiny" id="rbt-cart-count-2"><?= $cartCount ?></span>
                                    </span>
                                    <div class="content ml--4">
                                        <span class="title-text" id="rbt-cart-total-2"><?= h($cartTotalFmt) ?></span>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>


            <!-- Start Search Dropdown  -->
            <div class="rbt-search-dropdown rbt-common-search-dropdown-activation">
                <div class="wrapper">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="rbt-component-section-title border-0 p-0 text-center">
                                <h2 class="rbt-title text-start text-md-center"><span class="rbt-bold--text">Бараа хайх</span></h2>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <form class="rbt-search-form" method="get" action="<?= h($urlShop) ?>">
                                <div class="input-sectition position-relative w-100 mr--12 mr_sm--4">
                                    <input class="search-input" type="text" name="search" placeholder="Бараа хайх...">
                                    <i class="fa-sharp fa-regular inner-search-icon fa-magnifying-glass"></i>
                                </div>
                                <div class="submit-btn">
                                    <button class="rbt-btn btn-md" type="submit">Хайх</button>
                                </div>
                                <div class="rbt-media-search-section">
                                    <div class="rbt-media-wrapper">
                                        <div class="section-title"><span class="title b1">Find product inspiration with
                                                Image
                                                Search</span></div>
                                        <div class="rbt-file-upload-container">
                                            <input type="file" class="fileInput" multiple hidden>
                                            <div class="file-upload-area fileUploadArea">
                                                <div class="file-upload-content">
                                                    <span class="rbt-icon"><i
                                                            class="fa-solid fa-cloud-arrow-up"></i></span>
                                                    <p class="rbt-title">Drag & Drop Files Here <span
                                                            class="rbt-text-color-gray-400">Or</span></p>
                                                    <button class="browseFilesButton rbt-btn rbt-btn-sm">Browse
                                                        Files</button>
                                                </div>
                                                <div class="fileList file-list"></div>
                                            </div>
                                            <p class="fileCount">0 of 10</p>
                                        </div>
                                        <div class="rbt-copy-link-part rbt-text-copy-activation">
                                            <input class="rbt-copy-value-field" type="text"
                                                value="https://unimart.template/wishlist" readonly>
                                            <button class="rbt-btn rbt-btn-xs has-left-icon rbt-copy-btn"
                                                data-tooltip="Copy">
                                                <i class="fa-regular fa-copy"></i>
                                                <span class="rbt-btn-text">Copy</span>
                                            </button>
                                        </div>
                                        <button type="button" class="rbt-round-btn rbt-ms-dismiss-btn">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                                <a href="javascript:void(0);" class="rbt-ms-dismiss-outsider"></a>
                            </form>
                        </div>
                    </div>
                    <div class="rbt-search-scroll-vertical-wrapper rbt-scroll-vertical">
                        <div class="inner">
                            <div class="row row--0">
                                <div class="col-lg-12">
                                    <div class="border-0 p-0 text-left title-sm-fsize">
                                        <h2 class="title"><span class="rbt-bold--text">Түгээмэл хайлт</span></h2>
                                    </div>
                                </div>

                                <?php if ($popularSearchTags): ?>
                                <div class="rbt-search-list-wrapper rbt-tag-list rbt-tag-list-rounded-lg">
                                    <?php foreach ($popularSearchTags as $t): ?>
                                    <a href="<?= h($t['url']) ?>"><?= h($t['label']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
            <!-- End Search Dropdown  -->
        </div>
    </header>

    <!-- Start Preloader Area  -->
    <div class="rbt-preloader">
        <div class="rbt-preloader-inner">
            <img class="rbt-preloader-logo" src="<?= h($logoUrl) ?>" alt="<?= h($siteName) ?>">
            <div class="preloader-text">
                <p class="preloader-msg">Ачааллаж байна…</p>
            </div>
        </div>
    </div>
    <!-- End Preloader Area -->

    <!-- Mobile Menu Section -->
    <div class="popup-mobile-menu">
        <div class="inner-wrapper">
            <div class="mobile-menu-top">
                <div class="inner-top">
                    <div class="content">
                        <div class="logo">
                            <a href="<?= h($urlHome) ?>">
                                <img src="<?= h($logoUrl) ?>" alt="<?= h($siteName) ?>">
                            </a>
                        </div>
                        <div class="rbt-btn-close">
                            <button class="close-button rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>
                    <?php $_mmSlogan = s('site_slogan', ''); ?>
                    <?php if ($_mmSlogan): ?>
                    <p class="description"><?= h($_mmSlogan) ?></p>
                    <?php endif; ?>
                    <form method="get" action="<?= h($urlShop) ?>" class="rbt-inner-search-field style-one rbt-search-field-rounded rbt-search-field-sm-width">
                        <input type="text" name="search" placeholder="Бараа хайх..." value="<?= h($_GET['search'] ?? '') ?>">
                        <button class="rbt-round-btn search-btn rbt-text-color-gray-500" type="submit" aria-label="Хайх"><i
                                class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                </div>
                <div class="rbt-tab rbt-round-shape-tab">
                    <nav class="rbt-mainmenu-nav">
                    <?php require __DIR__ . "/nav-menu.php"; ?>
                    </nav>
                </div>
            </div>
            <?php
            $_mmSocials = array_filter([
                'facebook'  => ['url' => s('facebook_url', ''),  'icon' => 'fa-facebook'],
                'instagram' => ['url' => s('instagram_url', ''), 'icon' => 'fa-instagram'],
                'tiktok'    => ['url' => s('tiktok_url', ''),    'icon' => 'fa-tiktok'],
            ], fn($soc) => $soc['url'] !== '');
            $_mmEmail = s('email', '');
            ?>
            <div class="mobile-menu-bottom">
                <?php if ($_mmSocials): ?>
                <div class="social-share-wrapper">
                    <span class="rbt-short-title d-block">Бидэнтэй нэгдээрэй</span>
                    <ul class="rbt-social-icon-list mt--12">
                        <?php foreach ($_mmSocials as $soc): ?>
                        <li><a href="<?= h($soc['url']) ?>" target="_blank" rel="noopener"><i class="fa-brands <?= h($soc['icon']) ?>"></i></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <ul class="navbar-top-left rbt-information-list justify-content-center">
                    <?php if ($_mmEmail): ?>
                    <li>
                        <a href="mailto:<?= h($_mmEmail) ?>"><i class="fa-light fa-envelope"></i><?= h($_mmEmail) ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if ($sitePhone): ?>
                    <li>
                        <a href="tel:<?= h(preg_replace('/\s+/', '', $sitePhone)) ?>"><i class="fa-regular fa-phone"></i><?= h($sitePhone) ?></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- YOUR CART -->
    <!-- Start Side Nav -->
    <?php
    // Header-scoped names: pages like cart.php build their own $cartLines before this include
    $mcLines = $_SESSION['cart'] ?? [];
    $mcCount = count($mcLines);
    $mcSubtotal = cartTotal();
    ?>
    <div class="rbt-cart-side-menu rbt-sidebar-cart">
        <div class="inner-wrapper">
            <div class="inner-top">
                <div class="rbt-cart-header">
                    <div class="title-section">
                        <h2 class="title mb--0 h6"><i class="fa-sharp fa-regular fa-cart-shopping mr--12"></i> Таны сагс</h2>
                    </div>
                    <div class="rbt-btn-close" id="btn_sideNavClose">
                        <button class="minicart-close-button rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>
                <nav class="side-nav w-100">
                    <?php if ($mcCount === 0): ?>
                    <div class="text-center py-4">
                        <i class="fa-regular fa-cart-shopping" style="font-size:2.5rem;color:#ddd;"></i>
                        <p class="mt--12 mb--0">Сагс хоосон байна.</p>
                        <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border rbt-btn-sm mt--16">Дэлгүүр рүү</a>
                    </div>
                    <?php else: ?>
                    <ul class="rbt-minicart-wrapper">
                        <?php foreach ($mcLines as $mcKey => $mcLine):
                            $lineImg  = !empty($mcLine['image']) ? fixImageUrl($mcLine['image']) : fixImageUrl(null);
                            $lineUrl  = url('product?slug=' . urlencode($mcLine['slug']));
                            $lineQty  = (int)$mcLine['qty'];
                            $lineTot  = (float)$mcLine['price'] * $lineQty;
                            $meta     = trim(($mcLine['color'] ?? '') . (($mcLine['color'] && $mcLine['size']) ? ' / ' : '') . ($mcLine['size'] ?? ''));
                        ?>
                        <li class="minicart-item">
                            <div class="thumbnail">
                                <a href="<?= h($lineUrl) ?>">
                                    <img src="<?= h($lineImg) ?>" alt="<?= h($mcLine['name']) ?>">
                                </a>
                            </div>
                            <div class="product-content">
                                <h3 class="title h6"><a href="<?= h($lineUrl) ?>"><?= h($mcLine['name']) ?></a></h3>
                                <?php if ($meta !== ''): ?>
                                <p class="rbt-text-color-gray-600 b4 mb--4"><?= h($meta) ?></p>
                                <?php endif; ?>
                                <span class="quantity"><?= $lineQty ?>x <span class="price"><?= h(formatPrice($mcLine['price'])) ?></span></span>
                                <div class="bottom-part">
                                    <div class="rbt-qty-area">
                                        <form method="post" action="<?= h(url('cart-action')) ?>" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="key" value="<?= h($mcKey) ?>">
                                            <input type="hidden" name="qty" value="<?= max(1, $lineQty - 1) ?>">
                                            <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI'] ?? $urlHome) ?>">
                                            <button type="submit" class="qty-item-btn qty-item-btn-decr" <?= $lineQty <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-minus"></i></button>
                                        </form>
                                        <span class="items-qty-input" style="min-width:2ch;text-align:center;"><?= $lineQty ?></span>
                                        <form method="post" action="<?= h(url('cart-action')) ?>" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="key" value="<?= h($mcKey) ?>">
                                            <input type="hidden" name="qty" value="<?= $lineQty + 1 ?>">
                                            <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI'] ?? $urlHome) ?>">
                                            <button type="submit" class="qty-item-btn qty-item-btn-incr"><i class="fa-solid fa-plus"></i></button>
                                        </form>
                                    </div>
                                    <span class="price rbt-text-bold ml--8"><?= h(formatPrice($lineTot)) ?></span>
                                </div>
                            </div>
                            <div class="close-btn">
                                <form method="post" action="<?= h(url('cart-action')) ?>">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="key" value="<?= h($mcKey) ?>">
                                    <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI'] ?? $urlHome) ?>">
                                    <button type="submit" class="rbt-round-btn" aria-label="Устгах"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </nav>
            </div>
            <?php if ($mcCount > 0): ?>
            <div class="rbt-minicart-footer">
                <hr class="mb--0 mt--16">
                <div class="rbt-cart-subttotal">
                    <p>Дүн (<?= $mcCount ?> бараа)</p>
                    <p class="price"><?= h(formatPrice($mcSubtotal)) ?></p>
                </div>
                <div class="rbt-minicart-bottom mt--24">
                    <div class="checkout-btn mt--20">
                        <a class="rbt-btn w-100 text-center" href="<?= h($urlCart) ?>">
                            <span class="btn-text">Захиалгаа үргэлжлүүлэх</span>
                        </a>
                    </div>
                    <div class="share-btn-grp rbt-link-hover">
                        <a href="<?= h($urlCart) ?>" class="share-btn"><i class="fa-regular fa-cart-shopping mr--4"></i> Сагс харах</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- End Side Nav -->

