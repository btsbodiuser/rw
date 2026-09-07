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

// Nav data (mega menu)
try {
    $navAllCategories = $db->query("SELECT id, parent_id, slug, name, name_mn FROM categories WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll();
} catch (Throwable) { $navAllCategories = []; }

$navCatBySlug = [];
foreach ($navAllCategories as $_c) { $navCatBySlug[$_c['slug']] = $_c; }

$_shoeSlugs      = ['road', 'trail', 'race', 'lightweight', 'shoes', 'gutal', 'sneakers'];
$_clothingSlugs  = ['shorts', 'tops', 'jackets', 'clothes', 'clothing', 'apparel', 'huvtsas', 'tights', 'socks'];
$_accessorySlugs = ['accessories', 'dagaldakh', 'hats', 'gloves', 'belts', 'sunglasses'];

$navShoeCats = $navClothingCats = $navAccessoryCats = [];
foreach ($navAllCategories as $c) {
    $sl = strtolower($c['slug']);
    if (in_array($sl, $_shoeSlugs, true))          $navShoeCats[]      = $c;
    elseif (in_array($sl, $_clothingSlugs, true))  $navClothingCats[]  = $c;
    elseif (in_array($sl, $_accessorySlugs, true)) $navAccessoryCats[] = $c;
}

try { $navShoeTypes = $db->query("SELECT slug, name_mn, name FROM shoe_types         WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navShoeTypes = []; }
try { $navRunTypes  = $db->query("SELECT slug, name_mn, name FROM run_types          WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navRunTypes  = []; }
try { $navGaitTypes = $db->query("SELECT slug, name_mn, name FROM gait_types         WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navGaitTypes = []; }
try { $navFeatures  = $db->query("SELECT slug, name_mn, name FROM technical_features WHERE is_active = 1 ORDER BY sort_order")->fetchAll(); } catch (Throwable) { $navFeatures  = []; }

$navBrands = getPopularShops();

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
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.png">

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
        <?= $extraStyles ?>
    </style>
</head>

<body class="<?= h($bodyClass) ?>">
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

                                <div class="header-info p-0 d-none d-xl-block ml--28">
                                    <a class="rbt-offcanvas-trigger-btn rbt-offcanvas-trigger-transparent-btn rbt-cat-offcanvas-activation rbt-burger-menu-bar"
                                        href="#!">
                                        <div class="rbt-burger-menu-bar-wrapper">
                                            <i class="rbt-line-btn">
                                                <span class="rbt-lines"></span>
                                            </i>
                                            <i class="rbt-line-btn rbt-hover-effect">
                                                <span class="rbt-lines"></span>
                                            </i>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>


                        <div class="rbt-header-content d-none d-xl-block">
                            <div class="header-info">
                                <div class="rbt-search-with-category uni-header-swc-one">
                                    <?php $_cats = getCategories(); ?>
                                    <form method="get" action="<?= h($urlShop) ?>">
                                        <div class="rbt-inner-search-field border-0">
                                            <div
                                                class="rbt-search-input-section has-left-catagory-section rbt-inner-search-label-animate-activation">
                                                <div class="filter-select rbt-modern-select search-by-category">
                                                    <select name="category" class="rbt-select-activation" data-live-search="true"
                                                        data-live-search-placeholder="Ангилал хайх">
                                                        <option value="">Бүх ангилал</option>
                                                        <?php foreach ($_cats as $_c): if (!empty($_c['parent_id'] ?? null)) continue; ?>
                                                            <option value="<?= h($_c['slug']) ?>"><?= h($_c['name_mn'] ?: $_c['name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

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
                            <div class="header-info p-0 d-none d-xxl-flex mr--24">
                                <a class="rbt-offcanvas-trigger-btn rbt-cat-offcanvas-activation rbt-burger-menu-bar"
                                    href="#!">
                                    <div class="rbt-burger-menu-bar-wrapper">
                                        <i class="rbt-line-btn">
                                            <span class="rbt-lines"></span>
                                        </i>
                                        <i class="rbt-line-btn rbt-hover-effect">
                                            <span class="rbt-lines"></span>
                                        </i>
                                    </div>
                                </a>
                            </div>
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
                                data-tooltip="Sign In" data-tooltip-position="bottom">
                                <a class="rbt-round-btn has-rbt-md-fsize" href="#!" data-bs-toggle="modal"
                                    data-bs-target="#signinModal">
                                    <i class="fa-regular fa-user"></i>
                                </a>
                            </li>

                            <li class="rbt-access-box rbt-scroll-trigger fade_in animation-order-4 tooltips tooltip-distance-lg  d-none d-lg-flex"
                                data-tooltip="Compare" data-tooltip-position="bottom">
                                <a class="rbt-round-btn has-rbt-md-fsize" href="#" data-bs-toggle="modal"
                                    data-bs-target="#compareviewModal">
                                    <i class="fa-regular fa-code-compare"></i>
                                    <div class="access-box-count">6</div>
                                </a>
                            </li>


                            <li class="rbt-access-box rbt-scroll-trigger fade_in animation-order-5 rbt-wishlist d-none d-lg-flex tooltips tooltip-distance-lg"
                                data-tooltip="Wishlist" data-tooltip-position="bottom">
                                <a class="rbt-round-btn has-rbt-md-fsize" href="#!" data-bs-toggle="modal"
                                    data-bs-target="#wishlistModal">
                                    <i class="fa-regular fa-heart"></i>
                                    <div class="access-box-count">7</div>
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
                                <h2 class="rbt-title text-start text-md-center"><span class="rbt-bold--text">Search For
                                        Products</span></h2>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <form class="rbt-search-form">
                                <div class="input-sectition position-relative w-100 mr--12 mr_sm--4">
                                    <input class="search-input" type="text" placeholder="What Are You Looking For?">
                                    <i class="fa-sharp fa-regular inner-search-icon fa-magnifying-glass"></i>
                                    <button class="media-search-btn media-search-popupactivation">
                                        <i class="fa-sharp fa-regular fa-camera"></i>
                                    </button>
                                </div>
                                <div class="submit-btn">
                                    <a class="rbt-btn btn-md" href="#">Search</a>
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
                                        <h2 class="title"><span class="rbt-bold--text">Popular searches</span></h2>
                                    </div>
                                </div>

                                <div class="rbt-search-list-wrapper rbt-tag-list rbt-tag-list-rounded-lg">
                                    <a href="#">Fashion</a>
                                    <a href="#">Interior</a>
                                    <a href="#">Nature</a>
                                    <a href="#">Jewellery</a>
                                    <a href="#">Art</a>
                                    <a href="#">Aliexpress</a>
                                    <a href="#">Technology</a>
                                    <a href="#">Texture</a>
                                    <a href="#">Architecture</a>
                                    <a href="#">Business</a>
                                    <a href="#">Jewellery</a>
                                    <a href="#">Aliexpress</a>
                                </div>
                            </div>

                            <div class="rbt-separator-mid ptb--24">
                                <hr class="rbt-separator m-0">
                            </div>

                            <!-- Start Card Area -->
                            <div class="row row--0">
                                <div class="col-lg-12">
                                    <div class="border-0 p-0 text-left title-sm-fsize">
                                        <h2 class="title"><span class="rbt-bold--text">Trending Products</span></h2>
                                    </div>
                                </div>
                            </div>

                            <div class="row row--12 m--0 mt_dec--24">

                                <!-- Start Single Card  -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-6 mt--24 mt_sm--16">
                                    <div class="rbt-card rbt-product-card">
                                        <div class="inner rbt-scroll-trigger fade_in animation-order-1">
                                            <div class="rbt-card-img rbt-has-hover-img rbt-bg-color-default">
                                                <a href="product-single-default.html">
                                                    <img class="rbt-prd-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-01-a-1.webp"
                                                        alt="Card Image">
                                                    <img class="rbt-hover-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-01-a-1-hover.webp"
                                                        alt="Card Image">
                                                </a>
                                                <div
                                                    class="rbt-product-badge rbt-product-badge-bg-danger border-rounded rbt-content-top-left">
                                                    Hot</div>
                                                <div
                                                    class="rbt-product-badge rbt-product-badge-bg-secondary-gradient border-rounded rbt-content-top-left">
                                                    Best Seller</div>
                                                <div
                                                    class="rbt-quick-btn-grp has-mixup-midlayer bottom-right--position">
                                                    <button class="rbt-search-btn rbt-quick-btn tooltips" type="button"
                                                        data-bs-toggle="modal" data-bs-target="#quickviewModal"
                                                        data-tooltip="Quick View" data-tooltip-position="left"><i
                                                            class="fa-regular fa-magnifying-glass-plus"></i></button>
                                                    <button class="rbt-wishlisted-btn rbt-quick-btn tooltips"
                                                        type="button" data-bs-toggle="modal"
                                                        data-bs-target="#wishlistModal" data-tooltip="Add to wishlist"
                                                        data-tooltip-position="left"><i
                                                            class="fa-regular fa-heart"></i></button>
                                                </div>
                                            </div>
                                            <div class="rbt-card-body">
                                                <div class="rbt-color-select-area">
                                                    <ul class="rbt-switcher-color-list product-switcher-activation">
                                                        <li class="active"><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#2B2B2B"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-01-a-1.webp"
                                                                data-tooltip="Black" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips "
                                                                data-switcher-color="#a09fa4"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-01-a-2.webp"
                                                                data-tooltip="Red" data-tooltip-position="top" href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#cc999d"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-01-a-3.webp"
                                                                data-tooltip="Pink" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                    </ul>
                                                    <a class="prd-link-text" href="product-single-default.html">+12 More
                                                        Items</a>
                                                </div>
                                                <a href="shop-by-categories.html"
                                                    class="rbt-card-subtitle rbt-card-catagories-text">Headphones &
                                                    Music</a>
                                                <h2 class="rbt-card-title h6"><a
                                                        href="product-single-default.html">Samsung
                                                        Quiet
                                                        Comfort Noise Cancelling
                                                        Earbuds - Black</a></h2>
                                                <div class="rbt-card-rating">
                                                    <div class="rbt-text-swiper-container rbt-arrow-vertical">
                                                        <div class="swiper-wrapper">
                                                            <div class="swiper-slide">
                                                                <div class="rbt-text-group"> <span class="icon mr--4"><i
                                                                            class="fa-solid fa-bag-shopping"></i></span>
                                                                    90+ Sold Recently
                                                                </div>
                                                            </div>
                                                            <div class="swiper-slide">
                                                                <div class="rbt-text-group"> <span class="icon mr--4"><i
                                                                            class="fa-solid fa-truck"></i></span>
                                                                    Free shipping
                                                                </div>
                                                            </div>
                                                            <div class="swiper-slide">
                                                                <div class="rbt-text-group"> <span class="icon mr--4"><i
                                                                            class="fa-solid fa-rotate-left"></i></span>
                                                                    7 Days Return Plicy
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="rbt-verticle-arrow rbt-arrow-prev">
                                                            <i class="fa-regular fa-chevron-up"></i>
                                                        </div>
                                                        <div class="rbt-verticle-arrow rbt-arrow-next">
                                                            <i class="fa-regular fa-chevron-down"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="pricing-part">
                                                    <del class="price-text">$295.00</del>
                                                    <span class="price-text">$179.98</span>
                                                    <span class="rbt-offer-badge">-30%</span>
                                                </div>
                                                <div class="prd-btn-grp">
                                                    <a class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block has-left-icon rbt-cart-sidenav-activation"
                                                        href="#"><i class="fa-regular fa-cart-shopping"></i> Add To
                                                        Cart</a>
                                                    <a class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block rbt-btn-transparent has-left-icon rbt-compare-btn-activation rbt-compare-bottom-sidenav-activation"
                                                        href="#"><i class="fa-regular fa-file-plus-minus"></i>Add To
                                                        Compare</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Single Card  -->

                                <!-- Start Single Card  -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-6 mt--24 mt_sm--16">
                                    <div class="rbt-card rbt-product-card">
                                        <div class="inner rbt-scroll-trigger fade_in animation-order-2">
                                            <div class="rbt-card-img rbt-has-hover-img rbt-bg-color-default">
                                                <a href="product-single-default.html">
                                                    <img class="rbt-prd-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-04-a-1.webp"
                                                        alt="Card Image">
                                                    <img class="rbt-hover-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-04-a-1-hover.webp"
                                                        alt="Card Image">
                                                </a>
                                                <div
                                                    class="rbt-product-badge rbt-product-badge-bg-secondary-gradient border-rounded rbt-content-top-left">
                                                    Best Seller</div>
                                                <div
                                                    class="rbt-quick-btn-grp has-mixup-midlayer bottom-right--position">
                                                    <button class="rbt-search-btn rbt-quick-btn tooltips" type="button"
                                                        data-bs-toggle="modal" data-bs-target="#quickviewModal"
                                                        data-tooltip="Quick View" data-tooltip-position="left"><i
                                                            class="fa-regular fa-magnifying-glass-plus"></i></button>
                                                    <button class="rbt-wishlisted-btn rbt-quick-btn tooltips"
                                                        type="button" data-bs-toggle="modal"
                                                        data-bs-target="#wishlistModal" data-tooltip="Add to wishlist"
                                                        data-tooltip-position="left"><i
                                                            class="fa-regular fa-heart"></i></button>
                                                </div>
                                            </div>
                                            <div class="rbt-card-body">
                                                <div class="rbt-color-select-area">
                                                    <ul class="rbt-switcher-color-list product-switcher-activation">
                                                        <li class="active"><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#bdb6d6"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-04-a-1.webp"
                                                                data-tooltip="Purple" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips "
                                                                data-switcher-color="#486788"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-04-a-2.webp"
                                                                data-tooltip="Blue" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#1a1a1a"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-04-a-3.webp"
                                                                data-tooltip="Black" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                    </ul>
                                                    <a class="prd-link-text" href="product-single-default.html">+12 More
                                                        Items</a>
                                                </div>
                                                <a href="shop-by-categories.html"
                                                    class="rbt-card-subtitle rbt-card-catagories-text">Headphones &
                                                    Music</a>
                                                <h2 class="rbt-card-title h6"><a
                                                        href="product-single-default.html">Keurig K-Duo
                                                        Bose Noise Cancelling
                                                        Headphones 700 </a></h2>
                                                <div class="rbt-card-rating">
                                                    <ul class="rbt-rating-icon-list">
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                    </ul>
                                                    <p class="rating-digit">(10)</p>
                                                </div>
                                                <div class="pricing-part">
                                                    <del class="price-text">$295.00</del>
                                                    <span class="price-text">$179.98</span>
                                                </div>
                                                <div class="prd-btn-grp">
                                                    <button
                                                        class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block has-left-icon rbt-cart-sidenav-activation"><i
                                                            class="fa-regular fa-cart-shopping"></i> Add To
                                                        Cart</button>
                                                    <button
                                                        class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block rbt-btn-transparent has-left-icon rbt-compare-btn-activation"
                                                        type="button" data-bs-toggle="modal"
                                                        data-bs-target="#addedcomparisonModal"><i
                                                            class="fa-regular fa-file-plus-minus"></i>Add To
                                                        Compare</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Single Card  -->

                                <!-- Start Single Card  -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-6 mt--24 mt_sm--16">
                                    <div class="rbt-card rbt-product-card">
                                        <div class="inner rbt-scroll-trigger fade_in animation-order-4">
                                            <div class="rbt-card-img rbt-has-hover-img rbt-bg-color-default">
                                                <a href="product-single-default.html">
                                                    <img class="rbt-prd-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-08-a-1.webp"
                                                        alt="Card Image">
                                                    <img class="rbt-hover-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-08-a-1-hover.webp"
                                                        alt="Card Image">
                                                </a>
                                                <div
                                                    class="rbt-product-badge rbt-product-badge-bg-green border-rounded rbt-content-top-left">
                                                    New</div>
                                                <div
                                                    class="rbt-quick-btn-grp has-mixup-midlayer bottom-right--position">
                                                    <button class="rbt-search-btn rbt-quick-btn tooltips" type="button"
                                                        data-bs-toggle="modal" data-bs-target="#quickviewModal"
                                                        data-tooltip="Quick View" data-tooltip-position="left"><i
                                                            class="fa-regular fa-magnifying-glass-plus"></i></button>
                                                    <button class="rbt-wishlisted-btn rbt-quick-btn tooltips"
                                                        type="button" data-bs-toggle="modal"
                                                        data-bs-target="#wishlistModal" data-tooltip="Add to wishlist"
                                                        data-tooltip-position="left"><i
                                                            class="fa-regular fa-heart"></i></button>
                                                </div>
                                            </div>
                                            <div class="rbt-card-body">
                                                <div class="rbt-color-select-area">
                                                    <ul class="rbt-switcher-color-list product-switcher-activation">
                                                        <li class="active"><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#202020"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-08-a-1.webp"
                                                                data-tooltip="Black" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips "
                                                                data-switcher-color="#9e9e9e"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-08-a-2.webp"
                                                                data-tooltip="Gray" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#171717"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-08-a-3.webp"
                                                                data-tooltip="Light Black" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                    </ul>
                                                    <a class="prd-link-text" href="product-single-default.html">+12 More
                                                        Items</a>
                                                </div>
                                                <a href="shop-by-categories.html"
                                                    class="rbt-card-subtitle rbt-card-catagories-text">Electronics &
                                                    Camera</a>
                                                <h2 class="rbt-card-title h6"><a
                                                        href="product-single-default.html">GoPro HERO
                                                        11
                                                        4K Action Camera with SD
                                                        Card</a></h2>
                                                <div class="rbt-card-rating">
                                                    <div class="rbt-text-swiper-container rbt-arrow-vertical">
                                                        <div class="swiper-wrapper">
                                                            <div class="swiper-slide">
                                                                <div class="rbt-text-group"> <span class="icon mr--4"><i
                                                                            class="fa-solid fa-bag-shopping"></i></span>
                                                                    90+ Sold Recently
                                                                </div>
                                                            </div>
                                                            <div class="swiper-slide">
                                                                <div class="rbt-text-group"> <span class="icon mr--4"><i
                                                                            class="fa-solid fa-truck"></i></span>
                                                                    Free shipping
                                                                </div>
                                                            </div>
                                                            <div class="swiper-slide">
                                                                <div class="rbt-text-group"> <span class="icon mr--4"><i
                                                                            class="fa-solid fa-rotate-left"></i></span>
                                                                    7 Days Return Plicy
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="rbt-verticle-arrow rbt-arrow-prev">
                                                            <i class="fa-regular fa-chevron-up"></i>
                                                        </div>
                                                        <div class="rbt-verticle-arrow rbt-arrow-next">
                                                            <i class="fa-regular fa-chevron-down"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="pricing-part">
                                                    <del class="price-text">$295.00</del>
                                                    <span class="price-text">$179.98</span>
                                                    <div
                                                        class="rbt-badge rbt-badge-bg-green rbt-badge-border rbt-badge-small rbt-badge-rounded">
                                                        12 in
                                                        Stock</div>
                                                </div>
                                                <div class="prd-btn-grp">
                                                    <a class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block has-left-icon rbt-cart-sidenav-activation"
                                                        href="#"><i class="fa-regular fa-cart-shopping"></i> Add To
                                                        Cart</a>
                                                    <a class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block rbt-btn-transparent has-left-icon rbt-compare-btn-activation"
                                                        href="#"><i class="fa-regular fa-file-plus-minus"></i>Add To
                                                        Compare</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Single Card  -->

                                <!-- Start Single Card  -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-6 mt--24 mt_sm--16">
                                    <div class="rbt-card rbt-product-card">
                                        <div class="inner rbt-scroll-trigger fade_in animation-order-4">
                                            <div class="rbt-card-img rbt-has-hover-img rbt-bg-color-default">
                                                <a href="product-single-default.html">
                                                    <img class="rbt-prd-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-07-a-1.webp"
                                                        alt="Card Image">
                                                    <img class="rbt-hover-img"
                                                        src="assets/images/product-img/electronics/electronics-bg-trans-07-a-1-hover.webp"
                                                        alt="Card Image">
                                                </a>
                                                <div
                                                    class="rbt-product-badge rbt-product-badge-bg-yellow border-rounded rbt-content-top-left">
                                                    Trending
                                                </div>
                                                <div
                                                    class="rbt-quick-btn-grp has-mixup-midlayer bottom-right--position">
                                                    <button class="rbt-search-btn rbt-quick-btn tooltips" type="button"
                                                        data-bs-toggle="modal" data-bs-target="#quickviewModal"
                                                        data-tooltip="Quick View" data-tooltip-position="left"><i
                                                            class="fa-regular fa-magnifying-glass-plus"></i></button>
                                                    <button class="rbt-wishlisted-btn rbt-quick-btn tooltips"
                                                        type="button" data-bs-toggle="modal"
                                                        data-bs-target="#wishlistModal" data-tooltip="Add to wishlist"
                                                        data-tooltip-position="left"><i
                                                            class="fa-regular fa-heart"></i></button>
                                                </div>
                                            </div>
                                            <div class="rbt-card-body">
                                                <div class="rbt-color-select-area">
                                                    <ul class="rbt-switcher-color-list product-switcher-activation">
                                                        <li class="active"><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#afb1b3"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-07-a-1.webp"
                                                                data-tooltip="Gray" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips "
                                                                data-switcher-color="#7796b9"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-07-a-2.webp"
                                                                data-tooltip="Sky Blue" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                        <li><a class="rbt-switcher--color tooltips"
                                                                data-switcher-color="#b84a5f"
                                                                data-src="assets/images/product-img/electronics/electronics-bg-trans-07-a-3.webp"
                                                                data-tooltip="Pink Red" data-tooltip-position="top"
                                                                href="#">
                                                                <div class="rbt-color-circle"></div>
                                                            </a></li>
                                                    </ul>
                                                    <a class="prd-link-text" href="product-single-default.html">+12 More
                                                        Items</a>
                                                </div>
                                                <a href="shop-by-categories.html"
                                                    class="rbt-card-subtitle rbt-card-catagories-text">Tablets &
                                                    Accessories</a>
                                                <h2 class="rbt-card-title h6"><a
                                                        href="product-single-default.html">Samsung
                                                        Galaxy
                                                        N-569 Tab S7 with
                                                        Stylish – 8GB/128GB</a></h2>
                                                <div class="rbt-card-rating">
                                                    <ul class="rbt-rating-icon-list">
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                        <li><i class="fa-solid fa-star rbt-rated-icon"></i></li>
                                                        <li><i class="fa-solid fa-star"></i></li>
                                                        <li><i class="fa-solid fa-star"></i></li>
                                                    </ul>
                                                    <p class="rating-digit">(25)</p>
                                                </div>
                                                <div class="pricing-part">
                                                    <del class="price-text">$295.00</del>
                                                    <span class="price-text">$179.98</span>
                                                </div>
                                                <div class="prd-btn-grp">
                                                    <a class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block has-left-icon rbt-cart-sidenav-activation"
                                                        href="#"><i class="fa-regular fa-cart-shopping"></i> Add To
                                                        Cart</a>
                                                    <a class="rbt-btn rbt-btn-border rbt-btn-sm rbt-square-btn d-block rbt-btn-transparent has-left-icon rbt-compare-btn-activation"
                                                        href="#"><i class="fa-regular fa-file-plus-minus"></i>Add To
                                                        Compare</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Single Card  -->

                            </div>
                            <!-- End Card Area -->
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
            <svg class="rbt-preloader-cart" role="img" aria-label="Shopping cart line animation" viewBox="0 0 128 128"
                width="128px" height="128px" xmlns="http://www.w3.org/2000/svg">
                <g fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="8">
                    <g class="rbt-preloader-cart-track" stroke="hsla(0,10%,10%,0.1)">
                        <polyline points="4,4 21,4 26,22 124,22 112,64 35,64 39,80 106,80" />
                        <circle cx="43" cy="111" r="13" />
                        <circle cx="102" cy="111" r="13" />
                    </g>
                    <g class="rbt-preloader-cart-lines" stroke="currentColor">
                        <polyline class="rbt-preloader-cart-top"
                            points="4,4 21,4 26,22 124,22 112,64 35,64 39,80 106,80" stroke-dasharray="338 338"
                            stroke-dashoffset="-338" />
                        <g class="rbt-preloader-cart-wheel1" transform="rotate(-90,43,111)">
                            <circle class="rbt-preloader-cart-wheel-stroke" cx="43" cy="111" r="13"
                                stroke-dasharray="81.68 81.68" stroke-dashoffset="81.68" />
                        </g>
                        <g class="rbt-preloader-cart-wheel2" transform="rotate(90,102,111)">
                            <circle class="rbt-preloader-cart-wheel-stroke" cx="102" cy="111" r="13"
                                stroke-dasharray="81.68 81.68" stroke-dashoffset="81.68" />
                        </g>
                    </g>
                </g>
            </svg>
            <div class="preloader-text">
                <p class="preloader-msg">Gearing up something amazing for you…</p>
                <p class="preloader-msg preloader-msg--last">Still waiting? Magic takes a moment! ✨</p>
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
                                <img src="<?= h($logoUrl) ?>" alt="Unimart Logo Images">
                            </a>
                        </div>
                        <div class="rbt-btn-close">
                            <button class="close-button rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>
                    <p class="description">Unimart is a E-commerce Template. Worldwide electronics store since 1978.</p>
                    <div class="rbt-inner-search-field style-one rbt-search-field-rounded rbt-search-field-sm-width">
                        <input type="text" placeholder="Search for products">
                        <button class="rbt-round-btn search-btn rbt-text-color-gray-500" type="submit"><i
                                class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </div>
                <div class="rbt-tab rbt-round-shape-tab">
                    <ul class="nav nav-tabs mb--0" id="mobile-menuTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="rbt-tab-mobilemenu-1" data-bs-toggle="tab"
                                data-bs-target="#rbt-tab-pane-mobilemenu-1" type="button" role="tab"
                                aria-controls="rbt-tab-pane-mobilemenu-1" aria-selected="true">
                                <i class="fa-solid fa-bars-sort"></i>
                                Menu
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="rbt-tab-mobilemenu-2" data-bs-toggle="tab"
                                data-bs-target="#rbt-tab-pane-mobilemenu-2" type="button" role="tab"
                                aria-controls="rbt-tab-pane-mobilemenu-2" aria-selected="false">
                                <i class="fa-sharp fa-regular fa-layer-group"></i>
                                Catagories
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="mobile-menuTabContent">
                        <div class="tab-pane fade show active" id="rbt-tab-pane-mobilemenu-1" role="tabpanel"
                            aria-labelledby="rbt-tab-mobilemenu-1" tabindex="0">
                            <nav class="rbt-mainmenu-nav">
                            <?php require __DIR__ . "/nav-menu.php"; ?>
                            </nav>
                        </div>
                        <div class="tab-pane fade" id="rbt-tab-pane-mobilemenu-2" role="tabpanel"
                            aria-labelledby="rbt-tab-mobilemenu-2" tabindex="0">
                            <?php require __DIR__ . "/nav-menu.php"; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mobile-menu-bottom">
                <div class="social-share-wrapper">
                    <span class="rbt-short-title d-block">Find With Us</span>
                    <ul class="rbt-social-icon-list mt--12">
                        <li><a href="#"><i class="fa-brands fa-x-twitter"></i></a></li>
                        <li><a href="#"><i class="fa-brands fa-youtube"></i></a></li>
                        <li><a href="#"><i class="fa-brands fa-facebook"></i></a></li>
                        <li><a href="#"><i class="fa-brands fa-whatsapp"></i></a></li>
                        <li><a href="#"><i class="fa-brands fa-instagram"></i></a></li>
                        <li><a href="#"><i class="fa-brands fa-telegram"></i></a></li>
                    </ul>
                </div>
                <ul class="navbar-top-left rbt-information-list justify-content-center">
                    <li>
                        <a href="mailto:hello@example.com"><i class="fa-light fa-envelope"></i>example@gmail.com</a>
                    </li>
                    <li>
                        <a href="tel:+302555-0107"><i class="fa-regular fa-phone"></i>(302) 555-0107</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- LEFT CATEGORIES -->
    <!-- Start Side Nav -->
    <div class="rbt-offcanvas-cat-side-menu rbt-category-sidemenu ">
        <div class="inner-wrapper">
            <div class="rbt-categories-sidebar d-flex">
                <div class="rbt-sidebar-left-content">
                    <div class="rbt-sidebar-left-inner">
                        <!-- Start sidebar left header -->
                        <div class="rbt-sidebar-left-content-head">
                            <div class="rbt-categories-sidebar-top-content mb--24">
                                <div class="logo">
                                    <a href="<?= h($urlHome) ?>">
                                        <img src="<?= h($logoUrl) ?>" alt="Unimart Logo">
                                    </a>
                                </div>
                                <button class="rbt-sidebar-close-btn">
                                    <i class="fa-sharp fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <div
                                class="rbt-access-box rbt-scroll-trigger fade_in animation-order-1 rbt-access-box-has-bg-hover rbt-access-box-has-bg-hover-white d-inline-block">
                                <a href="#!" class="rbt-access-box-wrapper" data-bs-toggle="modal"
                                    data-bs-target="#signinModal">
                                    <div
                                        class="rbt-round-btn rbt-bg-color-brand-300 rbt-text-color-primary has-rbt-sm-fsize">
                                        <i class="fa-regular fa-user"></i>
                                    </div>
                                    <div class="content">
                                        <p>Log in/Sign Up</p>
                                        <span>Access Account</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <!-- End sidebar left header -->

                        <div class="rbt-sidebar-tabs-wrapper">
                            <div class="rbt-sidebar-tabs-inner">
                                <!-- Start tabs -->
                                <ul class="rbt-sidebar-sub-categories nav flex-column nav-pills" id="v-pills-tab"
                                    role="tablist" aria-orientation="vertical">
                                    <li>
                                        <button class="rbt-nav-link nav-link" id="rbt-tab-cat-sidebar-1"
                                            data-bs-toggle="pill" data-bs-target="#rbt-nav-pill-1" type="button"
                                            role="tab" aria-controls="rbt-nav-pill-1" aria-selected="true">
                                            <span class="rbt-round-btn">
                                                <i class="fa-regular fa-camera"></i>
                                            </span>
                                            <span class="rbt-content">
                                                <span class="rbt-sub-category-title">
                                                    <span>Camera & Photo</span>
                                                </span>
                                                <span class="description">Popular Camera & Photo accessories</span>
                                            </span>
                                            <span class="icon">
                                                <i class="fa-regular fa-chevron-right"></i>
                                            </span>
                                        </button>
                                    </li>
                                    <li>
                                        <button class="rbt-nav-link nav-link" id="rbt-tab-cat-sidebar-2"
                                            data-bs-toggle="pill" data-bs-target="#rbt-nav-pill-2" type="button"
                                            role="tab" aria-controls="rbt-nav-pill-2" aria-selected="false">
                                            <span class="rbt-round-btn">
                                                <i class="fa-regular fa-watch-apple"></i>
                                            </span>
                                            <span class="rbt-content">
                                                <span class="rbt-sub-category-title">
                                                    <span>All Watches</span>
                                                    <span
                                                        class="rbt-product-badge rbt-product-badge-bg-primary">EXCLUSIVE</span>
                                                </span>
                                                <span class="description">Pages with a demonstration
                                                    of Smartwatches</span>
                                            </span>
                                            <span class="icon">
                                                <i class="fa-regular fa-chevron-right"></i>
                                            </span>
                                        </button>
                                    </li>
                                    <li>
                                        <button class="rbt-nav-link nav-link" id="rbt-tab-cat-sidebar-3"
                                            data-bs-toggle="pill" data-bs-target="#rbt-nav-pill-3" type="button"
                                            role="tab" aria-controls="rbt-nav-pill-3" aria-selected="false">
                                            <span class="rbt-round-btn">
                                                <i class="fa-sharp fa-regular fa-camcorder"></i>
                                            </span>
                                            <span class="rbt-content">
                                                <span class="rbt-sub-category-title">
                                                    <span>TVs, Audio-Video</span>
                                                </span>
                                                <span class="description">Top TVs, Audio-Videothe most famous
                                                    brands</span>
                                            </span>
                                            <span class="icon">
                                                <i class="fa-regular fa-chevron-right"></i>
                                            </span>
                                        </button>
                                    </li>
                                    <li>
                                        <button class="rbt-nav-link nav-link" id="rbt-tab-cat-sidebar-4"
                                            data-bs-toggle="pill" data-bs-target="#rbt-nav-pill-4" type="button"
                                            role="tab" aria-controls="rbt-nav-pill-4" aria-selected="false">
                                            <span class="rbt-round-btn">
                                                <i class="fa-light fa-game-console-handheld"></i>
                                            </span>
                                            <span class="rbt-content">
                                                <span class="rbt-sub-category-title">
                                                    <span>Gaming</span>
                                                    <span class="rbt-product-badge rbt-bg-color-green">TRENDING</span>
                                                </span>
                                                <span class="description">Accessories for Games from
                                                    the best brands</span>
                                            </span>
                                            <span class="icon">
                                                <i class="fa-regular fa-chevron-right"></i>
                                            </span>
                                        </button>
                                    </li>
                                    <li>
                                        <button class="rbt-nav-link nav-link" id="rbt-tab-cat-sidebar-5"
                                            data-bs-toggle="pill" data-bs-target="#rbt-nav-pill-5" type="button"
                                            role="tab" aria-controls="rbt-nav-pill-5" aria-selected="false">
                                            <span class="rbt-round-btn">
                                                <i class="fa-sharp fa-regular fa-headphones"></i>
                                            </span>
                                            <span class="rbt-content">
                                                <span class="rbt-sub-category-title">
                                                    <span>Headphones & Music</span>
                                                </span>
                                                <span class="description">Catalog best Headphones
                                                    & Music here now</span>
                                            </span>
                                            <span class="icon">
                                                <i class="fa-regular fa-chevron-right"></i>
                                            </span>
                                        </button>
                                    </li>
                                    <li>
                                        <button class="rbt-nav-link nav-link" id="rbt-tab-cat-sidebar-6"
                                            data-bs-toggle="pill" data-bs-target="#rbt-nav-pill-6" type="button"
                                            role="tab" aria-controls="rbt-nav-pill-6" aria-selected="false">
                                            <span class="rbt-round-btn">
                                                <i class="fa-sharp fa-regular fa-blender-phone"></i>
                                            </span>
                                            <span class="rbt-content">
                                                <span class="rbt-sub-category-title">
                                                    <span>Appliances</span>
                                                    <span class="rbt-product-badge rbt-bg-color-danger">HOT</span>
                                                </span>
                                                <span class="description">Full list links of all
                                                    House Appliances active</span>
                                            </span>
                                            <span class="icon">
                                                <i class="fa-regular fa-chevron-right"></i>
                                            </span>
                                        </button>
                                    </li>
                                </ul>
                                <!-- End tabs -->

                                <!-- Start quick links -->
                                <div class="rbt-sidebar-quick-links-part">
                                    <div class="rbt-sidebar-bottom-inner">
                                        <hr class="rbt-separator rbt-separator-gray200 mb--24">
                                        <nav class="rbt-sidebar-nav">
                                            <h2 class="rbt-sub-category-title h4">
                                                <a data-bs-toggle="collapse" href="#collapseExample" role="button"
                                                    aria-expanded="false" aria-controls="collapseExample">
                                                    Quick Links
                                                    <span class="icon"><i class="fa-regular fa-chevron-down"></i></span>
                                                </a>
                                            </h2>
                                            <div class="collapse" id="collapseExample">
                                                <ul class="rbt-sidebar-quick-links">
                                                    <li><a href="about.html">About us</a></li>
                                                    <li><a href="#">Reviews</a></li>
                                                    <li><a href="#">Delivery & payment</a></li>
                                                    <li><a href="blogs.html">Blog Articles</a></li>
                                                </ul>
                                            </div>
                                        </nav>
                                        <hr class="rbt-separator rbt-separator-gray200 mb--24 mt--24">
                                        <nav class="rbt-sidebar-nav">
                                            <h2 class="rbt-sub-category-title h4">
                                                <a data-bs-toggle="collapse" href="#collapseExample2" role="button"
                                                    aria-expanded="false" aria-controls="collapseExample2">
                                                    More Links
                                                    <span class="icon"><i class="fa-regular fa-chevron-down"></i></span>
                                                </a>
                                            </h2>
                                            <div class="collapse" id="collapseExample2">
                                                <ul class="rbt-sidebar-quick-links">
                                                    <li><a href="contact.html">Contacts</a></li>
                                                    <li><a href="#">Information</a></li>
                                                    <li><a href="terms-policy.html">Terms & Conditions</a></li>
                                                </ul>
                                            </div>
                                        </nav>
                                    </div>
                                </div>
                                <!-- End quick links -->
                            </div>
                        </div>

                        <!-- Start sidebar footer -->
                        <div class="rbt-sidebar-left-content-footer">
                            <div class="rbt-sidebar-contact-area">
                                <div class="rbt-sidebar-contact-inner rbt-link-hover">
                                    <p class="rbt-contact-text">Boston, 44 Main street</p>
                                    <a class="rbt-contact-links" href="tel:+1(917)722-7425">+1(917)722-7425 (the call is
                                        free)</a>
                                    <p class="rbt-contact-text mt--12">Mon-Sun 9.00 - 18.00</p>
                                    <a class="rbt-contact-links" href="mailto:demo@example.com">demo@example.com</a>
                                    <a class="rbt-contact-links d-block" href="<?= h(url('contact')) ?>">View on map</a>
                                </div>
                            </div>
                        </div>
                        <!-- End sidebar footer -->

                    </div>
                </div>

                <div class="rbt-sidebar-right-content">
                    <div class="rbt-sidebar-right-inner">

                        <!-- Start tab content -->
                        <div class="tab-content" id="v-pills-tabContent">

                            <!-- Start single Category Tab content -->
                            <div class="rbt-tab-content tab-pane fade show active" id="rbt-nav-pill-1" role="tabpanel"
                                aria-labelledby="rbt-tab-cat-sidebar-1" tabindex="0">
                                <div class="rbt-sub-category-products">
                                    <div class="rbt-category-products-inner">

                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-7.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Action Camera</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Sports Cameras</a></li>
                                                <li><a href="shop-by-category.html">Underwater Cameras</a></li>
                                                <li><a href="shop-by-category.html">360 Cameras</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-8.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Camera lenses</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">VR Cameras</a></li>
                                                <li><a href="shop-by-category.html">Panoramic Cameras </a></li>
                                                <li><a href="shop-by-category.html">3D Cameras</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-9.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Digital Camera</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Drone Cameras</a></li>
                                                <li><a href="shop-by-category.html">Helmet Cameras</a></li>
                                                <li><a href="shop-by-category.html">Dual-Lens Cameras</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-10.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">DSLR</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Compact 360 Cameras</a></li>
                                                <li><a href="shop-by-category.html">DSLR Cameras</a></li>
                                                <li><a href="shop-by-category.html">Mirrorless Cameras</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-11.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Handycam</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Point-and-Shoot Cameras</a></li>
                                                <li><a href="shop-by-category.html">Bridge Cameras</a></li>
                                                <li><a href="shop-by-category.html">Compact Cameras</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-12.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Mirrorless Camera</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Full-Frame Mirrorless</a></li>
                                                <li><a href="shop-by-category.html">APS-C Mirrorless</a></li>
                                                <li><a href="shop-by-category.html">Micro Four Thirds Mirrorless</a>
                                                </li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-13.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Dash Cam</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Compact Mirrorless</a></li>
                                                <li><a href="shop-by-category.html">Medium Format Mirrorless</a></li>
                                                <li><a href="shop-by-category.html">Panoramic</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-14.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Video Camera</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Digital Camcorders</a></li>
                                                <li><a href="shop-by-category.html">Professional Camcorders</a></li>
                                                <li><a href="shop-by-category.html">4K Camcorders</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-15.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Instant Camera</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Compact Camcorders</a></li>
                                                <li><a href="shop-by-category.html">High Definition (HD) Camcorders</a>
                                                </li>
                                                <li><a href="shop-by-category.html">Panoramic</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-16.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Camera Accessories</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">SD Cards (High-Speed)</a></li>
                                                <li><a href="shop-by-category.html">MicroSD Cards</a></li>
                                                <li><a href="shop-by-category.html">External Hard Drives</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-17.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Camera Tripod</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Travel Tripods</a></li>
                                                <li><a href="shop-by-category.html">Tabletop Tripods</a></li>
                                                <li><a href="shop-by-category.html">Monopods</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->

                                    </div>
                                    <!-- Start banner -->
                                    <div class="rbt-sidebar-banner">
                                        <div class="rbt-banner-img">
                                            <img src="assets/images/product-img/sidebar-category/product-banner.webp"
                                                alt="Banner Image">
                                        </div>
                                        <div class="rbt-sidebar-banner-content">
                                            <p class="rbt-sidebar-banner-text">Camera Accessories
                                                <span class="rbt-text-color-primary rbt-text-semi-bold ml--4">11th
                                                    December</span>
                                            </p>
                                            <h2 class="rbt-sidebar-banner-titile h4">Up to 40% Off <span
                                                    class="rbt-text-regular">On All Brands</span>
                                            </h2>
                                            <a href="#" class="rbt-btn rbt-btn-sm">Know More</a>
                                        </div>
                                    </div>
                                    <!-- End banner -->

                                </div>
                            </div>
                            <!-- End single Category Tab content -->

                            <!-- Start single Category Tab content -->
                            <div class="rbt-tab-content tab-pane fade" id="rbt-nav-pill-2" role="tabpanel"
                                aria-labelledby="rbt-tab-cat-sidebar-2" tabindex="0">
                                <div class="rbt-sub-category-products">
                                    <div class="rbt-category-products-inner">

                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-1.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Fitness Tracker</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Smart Bands</a></li>
                                                <li><a href="shop-by-category.html">Heart Rate Monitors</a></li>
                                                <li><a href="shop-by-category.html">Sleep Trackers</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-2.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Bluetooth</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Luxury Bluetooth Watches</a></li>
                                                <li><a href="shop-by-category.html">Hybrid Smartwatches</a></li>
                                                <li><a href="shop-by-category.html">Kids' Smartwatches</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-3.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Hybrid</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Fitness Hybrid Watches</a></li>
                                                <li><a href="shop-by-category.html">Smart Hybrid Watches</a></li>
                                                <li><a href="shop-by-category.html">Classic Hybrid Watches</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-4.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Regular</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Analog Watches</a></li>
                                                <li><a href="shop-by-category.html">Digital Watches</a></li>
                                                <li><a href="shop-by-category.html">Dress Watches</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-5.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Touchscreen</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Smartwatches</a></li>
                                                <li><a href="shop-by-category.html">Fitness Trackers</a></li>
                                                <li><a href="shop-by-category.html">Hybrid Smartwatches</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->

                                    </div>
                                    <!-- Start banner -->
                                    <div class="rbt-sidebar-banner">
                                        <div class="rbt-banner-img">
                                            <img src="assets/images/product-img/sidebar-category/product-banner.webp"
                                                alt="Banner Image">
                                        </div>
                                        <div class="rbt-sidebar-banner-content">
                                            <p class="rbt-sidebar-banner-text">Starting From <span
                                                    class="rbt-text-color-primary rbt-text-semi-bold ml--4">11th
                                                    December</span></p>
                                            <h2 class="rbt-sidebar-banner-titile h4">Up to 40% Off <span
                                                    class="rbt-text-regular">On All Brands</span>
                                            </h2>
                                            <a href="#" class="rbt-btn rbt-btn-sm">Know More</a>
                                        </div>
                                    </div>
                                    <!-- End banner -->

                                </div>
                            </div>
                            <!-- End single Category Tab content -->

                            <!-- Start single Category Tab content -->
                            <div class="rbt-tab-content tab-pane fade" id="rbt-nav-pill-3" role="tabpanel"
                                aria-labelledby="rbt-tab-cat-sidebar-3" tabindex="0">
                                <div class="rbt-sub-category-products">
                                    <div class="rbt-category-products-inner">

                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-18.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">QLED TV</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li>
                                                    <a href="shop-by-categories.html"
                                                        class="rbt-underline-btn btn-white">
                                                        View All
                                                        <i class="fa-regular fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-19.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Smart TV</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li>
                                                    <a href="shop-by-categories.html"
                                                        class="rbt-underline-btn btn-white">
                                                        View All
                                                        <i class="fa-regular fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-20.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">UHD TV</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li>
                                                    <a href="shop-by-categories.html"
                                                        class="rbt-underline-btn btn-white">
                                                        View All
                                                        <i class="fa-regular fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-21.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">HD TV</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li>
                                                    <a href="shop-by-categories.html"
                                                        class="rbt-underline-btn btn-white">
                                                        View All
                                                        <i class="fa-regular fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-22.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">LED TV</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li>
                                                    <a href="shop-by-categories.html"
                                                        class="rbt-underline-btn btn-white">
                                                        View All
                                                        <i class="fa-regular fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-23.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">4K TV</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li>
                                                    <a href="shop-by-categories.html"
                                                        class="rbt-underline-btn btn-white">
                                                        View All
                                                        <i class="fa-regular fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->

                                    </div>
                                    <!-- Start banner -->
                                    <div class="rbt-sidebar-banner">
                                        <div class="rbt-banner-img">
                                            <img src="assets/images/product-img/sidebar-category/product-banner.webp"
                                                alt="Banner Image">
                                        </div>
                                        <div class="rbt-sidebar-banner-content">
                                            <p class="rbt-sidebar-banner-text">Starting From <span
                                                    class="rbt-text-color-primary rbt-text-semi-bold ml--4">11th
                                                    December</span></p>
                                            <h2 class="rbt-sidebar-banner-titile h4">Up to 40% Off <span
                                                    class="rbt-text-regular">On All Brands</span>
                                            </h2>
                                            <a href="#" class="rbt-btn rbt-btn-sm">Know More</a>
                                        </div>
                                    </div>
                                    <!-- End banner -->

                                </div>
                            </div>
                            <!-- End single Category Tab content -->

                            <!-- Start single Category Tab content -->
                            <div class="rbt-tab-content tab-pane fade" id="rbt-nav-pill-4" role="tabpanel"
                                aria-labelledby="rbt-tab-cat-sidebar-4" tabindex="0">
                                <div class="rbt-sub-category-products">
                                    <div class="rbt-category-products-inner">

                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-24.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Gaming Keyboard</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Apex Gamer Pro</a></li>
                                                <li><a href="shop-by-category.html">Stealth Strike Keyboard</a></li>
                                                <li><a href="shop-by-category.html">Rapid Fire RGB</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-25.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Gaming Headset</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">SoundStorm Pro</a></li>
                                                <li><a href="shop-by-category.html">EchoMaster Elite</a></li>
                                                <li><a href="shop-by-category.html">BattleTune 360</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-26.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Gaming Chair</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Elite Gamer Throne</a></li>
                                                <li><a href="shop-by-category.html">Turbo Comfort Seat</a></li>
                                                <li><a href="shop-by-category.html">Pro Series Gaming Chair</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-27.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Mouse Pads</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">GlidePro Mouse Pad</a></li>
                                                <li><a href="shop-by-category.html">PixelPerfect Pad</a></li>
                                                <li><a href="shop-by-category.html">EagleEye Mouse Mat</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-28.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Joystick</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">ProGamer Joystick</a></li>
                                                <li><a href="shop-by-category.html">Precision Play Controller</a></li>
                                                <li><a href="shop-by-category.html">TurboGrip Joystick</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-29.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">VR headset</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">VisionSphere VR Headset</a></li>
                                                <li><a href="shop-by-category.html">ImmersiveEye VR Goggles</a></li>
                                                <li><a href="shop-by-category.html">RealityFusion Headset</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-30.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">PlayStation Acce...</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Crystal Clear Faceplate</a></li>
                                                <li><a href="shop-by-category.html">ComfortFit Chair</a></li>
                                                <li><a href="shop-by-category.html">Dynamic RGB LED</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-31.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Gaming Desk</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">ProGamer Desk</a></li>
                                                <li><a href="shop-by-category.html">Titan Gaming Station</a></li>
                                                <li><a href="shop-by-category.html">Arcade Pro Desk</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-32.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Gaming Sofa</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Victory Lounge</a></li>
                                                <li><a href="shop-by-category.html">Pixel Perch</a></li>
                                                <li><a href="shop-by-category.html">Gamer's Retreat</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->

                                    </div>
                                    <!-- Start banner -->
                                    <div class="rbt-sidebar-banner">
                                        <div class="rbt-banner-img">
                                            <img src="assets/images/product-img/sidebar-category/product-banner.webp"
                                                alt="Banner Image">
                                        </div>
                                        <div class="rbt-sidebar-banner-content">
                                            <p class="rbt-sidebar-banner-text">Starting From <span
                                                    class="rbt-text-color-primary rbt-text-semi-bold ml--4">11th
                                                    December</span></p>
                                            <h2 class="rbt-sidebar-banner-titile h4">Up to 40% Off <span
                                                    class="rbt-text-regular">On All Brands</span>
                                            </h2>
                                            <a href="#" class="rbt-btn rbt-btn-sm">Know More</a>
                                        </div>
                                    </div>
                                    <!-- End banner -->

                                </div>
                            </div>
                            <!-- End single Category Tab content -->

                            <!-- Start single Category Tab content -->
                            <div class="rbt-tab-content tab-pane fade" id="rbt-nav-pill-5" role="tabpanel"
                                aria-labelledby="rbt-tab-cat-sidebar-5" tabindex="0">
                                <div class="rbt-sub-category-products">

                                    <div class="rbt-category-products-inner">
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-33.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Bluetooth Headphone</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">SoundWave Pro</a></li>
                                                <li><a href="shop-by-category.html">AeroSound Bluetooth</a></li>
                                                <li><a href="shop-by-category.html">PulseBeats Wireless</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-34.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Headphone Stand</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Audio Aegis</a></li>
                                                <li><a href="shop-by-category.html">Harmonic Holder</a></li>
                                                <li><a href="shop-by-category.html">Headset Haven</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-35.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Home Theater</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Cinematic Sound Bar</a></li>
                                                <li><a href="shop-by-category.html">Ultra HD Projector</a></li>
                                                <li><a href="shop-by-category.html">4K Smart TV</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-36.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Bluetooth Speaker</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">SoundWave Pro</a></li>
                                                <li><a href="shop-by-category.html">BassBlaster 360</a></li>
                                                <li><a href="shop-by-category.html">AeroSound Compact</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-37.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Soundbar</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Versatile Soundbar</a></li>
                                                <li><a href="shop-by-category.html">Signature Series Soundbar</a></li>
                                                <li><a href="shop-by-category.html">ProSound Soundbar</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-38.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Microphone</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">SoundWave Pro</a></li>
                                                <li><a href="shop-by-category.html">EchoSphere Mic</a></li>
                                                <li><a href="shop-by-category.html">ClearCast 3000</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-39.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Voice Recorder</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">EchoNote Pro</a></li>
                                                <li><a href="shop-by-category.html">VoxCapture 3000</a></li>
                                                <li><a href="shop-by-category.html">SoundScribe</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-40.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Sound Card</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">AeroSound Pro</a></li>
                                                <li><a href="shop-by-category.html">EchoMaster FX</a></li>
                                                <li><a href="shop-by-category.html">Vortex SoundBlaster</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->

                                    </div>
                                    <!-- Start banner -->
                                    <div class="rbt-sidebar-banner">
                                        <div class="rbt-banner-img">
                                            <img src="assets/images/product-img/sidebar-category/product-banner.webp"
                                                alt="Banner Image">
                                        </div>
                                        <div class="rbt-sidebar-banner-content">
                                            <p class="rbt-sidebar-banner-text">Starting From <span
                                                    class="rbt-text-color-primary rbt-text-semi-bold ml--4">11th
                                                    December</span></p>
                                            <h2 class="rbt-sidebar-banner-titile h4">Up to 40% Off <span
                                                    class="rbt-text-regular">On All Brands</span>
                                            </h2>
                                            <a href="#" class="rbt-btn rbt-btn-sm">Know More</a>
                                        </div>
                                    </div>
                                    <!-- End banner -->
                                </div>
                            </div>
                            <!-- End single Category Tab content -->

                            <!-- Start single Category Tab content -->
                            <div class="rbt-tab-content tab-pane fade" id="rbt-nav-pill-6" role="tabpanel"
                                aria-labelledby="rbt-tab-cat-sidebar-6" tabindex="0">
                                <div class="rbt-sub-category-products">
                                    <div class="rbt-category-products-inner">
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-41.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Air Conditioner</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">CoolBreeze Pro</a></li>
                                                <li><a href="shop-by-category.html">ChillMaster Elite</a></li>
                                                <li><a href="shop-by-category.html">AirFlow Genius</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-42.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Geyser</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">AquaFlow Geysers</a></li>
                                                <li><a href="shop-by-category.html">TurboHeat Geysers</a></li>
                                                <li><a href="shop-by-category.html">EcoHeat Geysers</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-43.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Oven</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">CrispBake Oven</a></li>
                                                <li><a href="shop-by-category.html">QuickHeat Convection Oven</a></li>
                                                <li><a href="shop-by-category.html">PerfectBake Electric Oven</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-44.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Air Fryer</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">CrispMaster Air Fryer</a></li>
                                                <li><a href="shop-by-category.html">Healthy Fry Pro</a></li>
                                                <li><a href="shop-by-category.html">QuickCrisp Air Fryer</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-45.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Washing Machine</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">EcoClean Pro</a></li>
                                                <li><a href="shop-by-category.html">UltraWash 360</a></li>
                                                <li><a href="shop-by-category.html">QuickSpin Deluxe</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-46.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Sewing Machine</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">StitchPro 300</a></li>
                                                <li><a href="shop-by-category.html">SewMaster Deluxe</a></li>
                                                <li><a href="shop-by-category.html">QuiltCraft Elite</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-47.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Air Purifier</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">PureAir Breeze</a></li>
                                                <li><a href="shop-by-category.html">FreshFlow Purifier</a></li>
                                                <li><a href="shop-by-category.html">BreatheEasy Pro</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-48.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Vacuum Cleaner</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">PowerSweep Pro</a></li>
                                                <li><a href="shop-by-category.html">UltraClean Cyclone</a></li>
                                                <li><a href="shop-by-category.html">DustBuster Max</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-49.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Blender</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Smoothie Master Pro</a></li>
                                                <li><a href="shop-by-category.html">NutriBlend Ultra</a></li>
                                                <li><a href="shop-by-category.html">EcoBlend Portable Blender</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-50.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Cooker</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">PowerMix 3000</a></li>
                                                <li><a href="shop-by-category.html">Frozen Fusion Blender</a></li>
                                                <li><a href="shop-by-category.html">UltraSmooth Blender</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-51.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Iron</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">Blender & Chop Duo</a></li>
                                                <li><a href="shop-by-category.html">TurboMix Professional</a></li>
                                                <li><a href="shop-by-category.html">BlendSmart 2-in-1</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->
                                        <!-- Start product singel -->
                                        <div class="rbt-sub-category-product">
                                            <a href="#" class="rbt-sidebar-category-img">
                                                <img src="assets/images/product-img/sidebar-category/category-product-52.webp"
                                                    alt="Product Image">
                                            </a>
                                            <h2 class="rbt-category-offcanvas-header h5"><a
                                                    href="shop-by-categories.html">Mini Heater</a></h2>
                                            <ul class="rbt-product-features has-link-underline-effect">
                                                <li><a href="shop-by-category.html">HeatWave Blanket</a></li>
                                                <li><a href="shop-by-category.html">ThermoCushion </a></li>
                                                <li><a href="shop-by-category.html">SootheHeat Massager</a></li>
                                            </ul>
                                        </div>
                                        <!-- End product singel -->

                                    </div>
                                    <!-- Start banner -->
                                    <div class="rbt-sidebar-banner">
                                        <div class="rbt-banner-img">
                                            <img src="assets/images/product-img/sidebar-category/product-banner.webp"
                                                alt="Banner Image">
                                        </div>
                                        <div class="rbt-sidebar-banner-content">
                                            <p class="rbt-sidebar-banner-text">Starting From <span
                                                    class="rbt-text-color-primary rbt-text-semi-bold ml--4">11th
                                                    December</span></p>
                                            <h2 class="rbt-sidebar-banner-titile h4">Up to 40% Off <span
                                                    class="rbt-text-regular">On All Brands</span>
                                            </h2>
                                            <a href="#" class="rbt-btn rbt-btn-sm">Know More</a>
                                        </div>
                                    </div>
                                    <!-- End banner -->

                                </div>
                            </div>
                            <!-- End single Category Tab content -->
                        </div>

                        <!-- End tab content -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Side Nav -->

    <!-- YOUR CART -->
    <!-- Start Side Nav -->
    <div class="rbt-cart-side-menu rbt-sidebar-cart">
        <div class="inner-wrapper">
            <div class="inner-top">
                <div class="rbt-cart-header">
                    <div class="title-section">
                        <h2 class="title mb--0 h6"><i class="fa-sharp fa-regular fa-cart-shopping mr--12"></i> Your cart
                        </h2>
                    </div>
                    <div class="rbt-quick-info-tag d-flex mt--16 rbt-flash-animation">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M18.9706 14.9359C18.8148 18.8649 15.7493 22 11.9891 22C8.12909 22 5 18.5858 5 14.6221C5 14.0924 4.99101 13.0336 5.74352 11.2472C6.19387 10.1781 6.47633 9.50646 6.63574 8.89253C6.72333 8.55511 6.89367 8.01904 7.37926 8.89253C7.66559 9.40757 7.67666 10.1483 7.67666 10.1483C7.67666 10.1483 8.74197 9.28536 9.4611 7.63673C10.5153 5.21985 9.67419 3.77512 9.38675 2.77048C9.28727 2.42294 9.22481 1.79833 9.90721 2.06409C10.6025 2.33495 12.4408 3.69334 13.4017 5.12512C14.7732 7.16855 15.2605 9.128 15.2605 9.128C15.2605 9.128 15.6997 8.55268 15.8553 7.95068C16.0312 7.27089 16.0338 6.59763 16.5988 7.32285C17.1361 8.01253 17.9341 9.3086 18.3833 10.5408C19.1989 12.7784 18.9706 14.9359 18.9706 14.9359Z"
                                fill="url(#paint0_linear_47_2365484)" />
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M11.9999 22C9.23852 22 7 19.7944 7 17.0735C7 15.4318 7.67145 14.435 9.0689 13.0833C9.96366 12.2179 10.8011 11.1549 11.157 10.4311C11.2271 10.2886 11.3866 9.54605 12.0014 10.4155C12.3239 10.8714 12.8296 11.6823 13.1538 12.3744C13.7127 13.5676 13.8461 14.7239 13.8461 14.7239C13.8461 14.7239 14.3938 14.4059 14.7692 13.5871C14.8902 13.3232 15.1348 12.3241 15.8186 13.323C16.3204 14.0561 17.0097 15.3741 16.9999 17.0735C16.9999 19.7944 14.7613 22 11.9999 22Z"
                                fill="#FC9502" />
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M12.1019 16C12.8497 16 12.8497 17.4475 13.7996 19.3803C14.4321 20.6672 13.486 22 12.1019 22C10.7178 22 10 20.8271 10 19.3803C10 17.9335 11.3541 16 12.1019 16Z"
                                fill="#FCE202" />
                            <defs>
                                <linearGradient id="paint0_linear_47_2365484" x1="11.9995" y1="22.0148" x2="11.9995"
                                    y2="2.01511" gradientUnits="userSpaceOnUse">
                                    <stop offset="1" stop-color="#FF4C0D" />
                                    <stop offset="1" stop-color="#FC9502" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <p>Limited Item, <strong>checkout within <span class="rbt-countdown-cart">10m
                                    00s</span></strong>
                        </p>
                    </div>
                    <div class="rbt-btn-close" id="btn_sideNavClose">
                        <button class="minicart-close-button rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>
                <nav class="side-nav w-100">
                    <ul class="rbt-minicart-wrapper">
                        <li class="minicart-item">
                            <div class="thumbnail">
                                <a href="product-single-default.html">
                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-10-a-1-hover.webp"
                                        alt="Product Image">
                                </a>
                            </div>
                            <div class="product-content">
                                <h3 class="title h6"><a href="product-single-default.html">JBL PartyBox 100W Speaker</a>
                                </h3>
                                <span class="quantity">1x <span class="price">$359.00</span></span>
                                <div class="bottom-part">
                                    <div class="rbt-qty-area">
                                        <button class="qty-item-btn qty-item-btn-decr"><i
                                                class="fa-solid fa-minus"></i></button>
                                        <input type="number" class="items-qty-input" value="01" min="1">
                                        <button class="qty-item-btn qty-item-btn-incr"><i
                                                class="fa-solid fa-plus"></i></button>
                                    </div>
                                    <button class="edit-btn" type="button" data-bs-toggle="modal"
                                        data-bs-target="#quickviewEditCartModal"><i class="fa-regular fa-pen"></i>
                                        Edit</button>
                                </div>
                            </div>
                            <div class="close-btn">
                                <button class="rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </li>

                        <li class="minicart-item">
                            <div class="thumbnail">
                                <a href="product-single-default.html">
                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-04-a-1-hover.webp"
                                        alt="Product Image">
                                </a>
                            </div>
                            <div class="product-content">
                                <h3 class="title h6"><a href="product-single-default.html">Apple Watch Ultra 2</a></h3>
                                <span class="quantity">1x <span class="price">$359.00</span></span>
                                <div class="bottom-part">
                                    <div class="rbt-qty-area">
                                        <button class="qty-item-btn qty-item-btn-decr"><i
                                                class="fa-solid fa-minus"></i></button>
                                        <input type="number" class="items-qty-input" value="01" min="1">
                                        <button class="qty-item-btn qty-item-btn-incr"><i
                                                class="fa-solid fa-plus"></i></button>
                                    </div>
                                    <button class="edit-btn" type="button" data-bs-toggle="modal"
                                        data-bs-target="#quickviewEditCartModal"><i class="fa-regular fa-pen"></i>
                                        Edit</button>
                                </div>
                            </div>
                            <div class="close-btn">
                                <button class="rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </li>

                        <li class="minicart-item">
                            <div class="thumbnail">
                                <a href="product-single-default.html">
                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-01-a-1-hover.webp"
                                        alt="Product Image">
                                </a>
                            </div>
                            <div class="product-content">
                                <h3 class="title h6"><a href="product-single-default.html">PlayStation Wireless
                                        Headphone</a>
                                </h3>
                                <span class="quantity">1x <span class="price">$759.00</span></span>
                                <div class="bottom-part">
                                    <div class="rbt-qty-area">
                                        <button class="qty-item-btn qty-item-btn-decr"><i
                                                class="fa-solid fa-minus"></i></button>
                                        <input type="number" class="items-qty-input" value="01" min="1">
                                        <button class="qty-item-btn qty-item-btn-incr"><i
                                                class="fa-solid fa-plus"></i></button>
                                    </div>
                                    <button class="edit-btn" type="button" data-bs-toggle="modal"
                                        data-bs-target="#quickviewEditCartModal"><i class="fa-regular fa-pen"></i>
                                        Edit</button>
                                </div>
                            </div>
                            <div class="close-btn">
                                <button class="rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </li>

                        <li class="minicart-item">
                            <div class="thumbnail">
                                <a href="product-single-default.html">
                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-02-a-1-hover.webp"
                                        alt="Product Image">
                                </a>
                            </div>
                            <div class="product-content">
                                <h3 class="title h6"><a href="product-single-default.html">Awei CL-115M USB 2.4A Cable
                                    </a>
                                </h3>
                                <span class="quantity">1x <span class="price">$459.00</span></span>
                                <div class="bottom-part">
                                    <div class="rbt-qty-area">
                                        <button class="qty-item-btn qty-item-btn-decr"><i
                                                class="fa-solid fa-minus"></i></button>
                                        <input type="number" class="items-qty-input" value="01" min="1">
                                        <button class="qty-item-btn qty-item-btn-incr"><i
                                                class="fa-solid fa-plus"></i></button>
                                    </div>
                                    <button class="edit-btn" type="button" data-bs-toggle="modal"
                                        data-bs-target="#quickviewEditCartModal"><i class="fa-regular fa-pen"></i>
                                        Edit</button>
                                </div>
                            </div>
                            <div class="close-btn">
                                <button class="rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </li>

                        <li class="minicart-item">
                            <div class="thumbnail">
                                <a href="product-single-default.html">
                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-03-a-1-hover.webp"
                                        alt="Product Image">
                                </a>
                            </div>
                            <div class="product-content">
                                <h3 class="title h6"><a href="product-single-default.html">MaxGreen 45W Power
                                        Adapter</a>
                                </h3>
                                <span class="quantity">1x <span class="price">$999.00</span></span>
                                <div class="bottom-part">
                                    <div class="rbt-qty-area">
                                        <button class="qty-item-btn qty-item-btn-decr"><i
                                                class="fa-solid fa-minus"></i></button>
                                        <input type="number" class="items-qty-input" value="01" min="1">
                                        <button class="qty-item-btn qty-item-btn-incr"><i
                                                class="fa-solid fa-plus"></i></button>
                                    </div>
                                    <button class="edit-btn" type="button" data-bs-toggle="modal"
                                        data-bs-target="#quickviewEditCartModal"><i class="fa-regular fa-pen"></i>
                                        Edit</button>
                                </div>
                            </div>
                            <div class="close-btn">
                                <button class="rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </li>

                        <li class="minicart-item">
                            <div class="thumbnail">
                                <a href="product-single-default.html">
                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-05-a-1-hover.webp"
                                        alt="Product Image">
                                </a>
                            </div>
                            <div class="product-content">
                                <h3 class="title h6"><a href="product-single-default.html">Havit PB90 Power Bank </a>
                                </h3>
                                <span class="quantity">1x <span class="price">$288.00</span></span>
                                <div class="bottom-part">
                                    <div class="rbt-qty-area">
                                        <button class="qty-item-btn qty-item-btn-decr"><i
                                                class="fa-solid fa-minus"></i></button>
                                        <input type="number" class="items-qty-input" value="01" min="1">
                                        <button class="qty-item-btn qty-item-btn-incr"><i
                                                class="fa-solid fa-plus"></i></button>
                                    </div>
                                    <button class="edit-btn" type="button" data-bs-toggle="modal"
                                        data-bs-target="#quickviewEditCartModal"><i class="fa-regular fa-pen"></i>
                                        Edit</button>
                                </div>
                            </div>
                            <div class="close-btn">
                                <button class="rbt-round-btn"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </li>
                    </ul>
                    <div class="minicart-quick-access-area mt--24">
                        <a href="#" class="single-quick-access rbt-note-btn">
                            <span class="icon"><i class="fa-regular fa-pen"></i></span>
                            <span class="text">Note</span>
                        </a>
                        <span class="hr-sepator"></span>
                        <a href="#" class="single-quick-access rbt-shipping-btn">
                            <span class="icon"><i class="fa-regular fa-truck-fast"></i></span>
                            <span class="text">Shipping</span>
                        </a>
                        <span class="hr-sepator"></span>
                        <a href="#" class="single-quick-access rbt-coupon-btn">
                            <span class="icon"><i class="fa-regular fa-ticket"></i></span>
                            <span class="text">Coupon</span>
                        </a>
                    </div>
                    <div class="minicart-inc-items-area mt--12">
                        <h3 class="title h6 positin-top">You May Also Like</h3>
                        <div class="bottom-area">
                            <div
                                class="swiper rbt-dot-top-right inc-item-swiper-activation rbt-minicart-wrapper overflow-hidden">
                                <div class="swiper-wrapper">
                                    <!-- single slide -->
                                    <div class="swiper-slide">
                                        <div class="minicart-item">
                                            <div class="thumbnail">
                                                <a href="product-single-default.html">
                                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-08-a-1-hover.webp"
                                                        alt="Product Image">
                                                </a>
                                            </div>
                                            <div class="product-content">
                                                <h3 class="title h6"><a href="product-single-default.html">Keurig K-Duo
                                                        4K
                                                        Waterproof Action
                                                        Video Camera </a></h3>
                                                <span class="quantity"><span class="price">$345.00</span></span>
                                            </div>
                                            <a href="#!" class="add-itembtn tooltips" data-bs-toggle="modal"
                                                data-bs-target="#addedcartModal" data-tooltip="Add to Cart"><i
                                                    class="fa-regular fa-cart-plus"></i></a>
                                        </div>
                                    </div>
                                    <!-- single slide -->
                                    <div class="swiper-slide">
                                        <div class="minicart-item">
                                            <div class="thumbnail">
                                                <a href="product-single-default.html">
                                                    <img src="assets/images/product-img/electronics/electronics-bg-trans-06-a-1-hover.webp"
                                                        alt="Product Image">
                                                </a>
                                            </div>
                                            <div class="product-content">
                                                <h3 class="title h6"><a href="product-single-default.html">Full Amoled
                                                        HD
                                                        Streaming Webcam</a>
                                                </h3>
                                                <span class="quantity"><span class="price">$189.00</span></span>
                                            </div>
                                            <a href="#!" class="add-itembtn tooltips" data-bs-toggle="modal"
                                                data-bs-target="#addedcartModal" data-tooltip="Add to Cart"><i
                                                    class="fa-regular fa-cart-plus"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="rbt-swiper-pagination"></div>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
            <div class="rbt-minicart-footer">
                <hr class="mb--0 mt--16">
                <div class="rbt-cart-subttotal">
                    <p>Subtotal (2 items)</p>
                    <p class="price">$758.00</p>
                </div>
                <div class="rbt-cart-subttotal">
                    <p>Shipping</p>
                    <p class="price">$10.00</p>
                </div>
                <hr class="mb--0">
                <div class="rbt-cart-subttotal">
                    <p class="subtotal"><strong>Total</strong></p>
                    <p class="price">$768.00</p>
                </div>
                <div class="offer-progress-area">
                    <p class="offer-text">Add <strong>$248.00</strong> More To Get <strong>Free Shipping</strong></p>
                    <div class="progress" role="progressbar" aria-label="Shipping-progress" aria-valuenow="75"
                        aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar w-75"></div>
                    </div>
                </div>
                <div class="rbt-minicart-bottom mt--24">
                    <div class="checkout-btn mt--20">
                        <a class="rbt-btn w-100 text-center" href="#">
                            <span class="btn-text">Checkout</span>
                        </a>
                    </div>
                    <div class="share-btn-grp rbt-link-hover">
                        <a href="cart.html" class="share-btn"><i class="fa-regular fa-pen mr--4"></i> View Cart</a>
                        <button data-bs-toggle="modal" data-bs-target="#socialShareModal" type="button"
                            class="share-btn"><i class="fa-sharp fa-solid fa-link mr--4"></i> Share Cart</button>
                    </div>
                </div>
            </div>
        </div>
        <a href="#!" class="rbt-close-inner-popup rbt-popup-close-btn"></a>
        <div class="rbt-offcanvas-inner-popup">
            <div class="rbt-offcanvas-inner-popup-card note-popup">
                <div class="rbt-offcanvas-card-inner">
                    <h3 class="rbt-title rbt-text-bold h6">
                        <span class="mr--4"><i class="fa-regular fa-pen"></i></span>
                        Add note for seller
                    </h3>
                    <form>
                        <div class="rbt-input-field-grp mb--12">
                            <textarea class="rbt-text-field" name="message"
                                placeholder="Notes about your order, e.g. special notes for delivery."></textarea>
                        </div>
                        <div class="rbt-btn-group mt--16">
                            <button class="rbt-btn rbt-btn-md rbt-btn-primary d-block w-100">Apply</button>
                            <button
                                class="rbt-btn rbt-btn-md rbt-btn-naked d-block w-100 mt--8 mb--8 rbt-popup-close-btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="rbt-offcanvas-inner-popup">
            <div class="rbt-offcanvas-inner-popup-card shipping-popup">
                <div class="rbt-offcanvas-card-inner">
                    <h3 class="rbt-title rbt-text-bold h6">
                        <span class="mr--4"><i class="fa-light fa-truck-fast"></i></span>
                        Estimate shipping rates
                    </h3>
                    <form>
                        <div class="rbt-input-field-grp mb--12">
                            <div class="rbt-dropdown-select filter-select rbt-modern-select search-by-category">
                                <select class="w-100 rbt-select-activation" data-live-search="true"
                                    data-live-search-placeholder="Search City">
                                    <option>Select your City</option>
                                    <option>New York</option>
                                    <option>London</option>
                                    <option>Paris</option>
                                    <option>Tokyo</option>
                                    <option>Dubai</option>
                                    <option>Singapore</option>
                                    <option>Sydney</option>
                                    <option>Berlin</option>
                                    <option>Toronto</option>
                                    <option>Los Angeles</option>
                                </select>
                            </div>
                        </div>
                        <div class="rbt-input-field-grp mb--12">
                            <input type="text" placeholder="State / County">
                        </div>
                        <div class="rbt-input-field-grp mb--12">
                            <input type="text" placeholder="City">
                        </div>
                        <div class="rbt-input-field-grp">
                            <input type="text" placeholder="Postcode / ZIP">
                        </div>
                        <div class="rbt-btn-group mt--16">
                            <button class="rbt-btn rbt-btn-md rbt-btn-primary d-block w-100">Calculate shipping
                                rates</button>
                            <button
                                class="rbt-btn rbt-btn-md rbt-btn-naked d-block w-100 mt--8 mb--8 rbt-popup-close-btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="rbt-offcanvas-inner-popup">
            <div class="rbt-offcanvas-inner-popup-card coupon-popup">
                <div class="rbt-offcanvas-card-inner">
                    <h3 class="rbt-title rbt-text-bold h6">
                        <span class="mr--4"><i class="fa-regular fa-ticket"></i></span>
                        Select or input Coupon
                    </h3>
                    <div class="rbt-coupon-wrapper rbt-bg-color-white">
                        <div class="rbt-coupon">
                            <div class="inner rbt-text-copy-activation">
                                <div class="left-part">
                                    <input type="text" value="WELCOME100" readonly
                                        class="rbt-coupon-code-text rbt-has-right-shepe-border rbt-copy-value-field">
                                </div>
                                <div class="coupon-details">
                                    <h2 class="rbt-coupon-info-title b1">UP TO 30% OFF</h2>
                                    <p class="rbt-coupon-info-sub-title b3 mt--4">For orders over $9.90</p>
                                    <ul class="rbt-coupon-info-list mt--12">
                                        <li><span>12/18/2023 14:00 ~ 12/25/2023 14:00</span></li>
                                        <li><span>The minimum spend for this coupon <strong>$200.00</strong></span></li>
                                    </ul>
                                </div>
                                <button class="copy-icon rbt-round-btn rbt-bg-primary rbt-copy-btn" data-tooltip="Copy">
                                    <i class="fa-sharp fa-regular fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="rbt-coupon">
                            <div class="inner rbt-text-copy-activation">
                                <div class="left-part">
                                    <input type="text" value="WELCOME100" readonly
                                        class="rbt-coupon-code-text rbt-has-right-shepe-border rbt-copy-value-field">
                                </div>
                                <div class="coupon-details">
                                    <h2 class="rbt-coupon-info-title b1">UP TO 30% OFF</h2>
                                    <p class="rbt-coupon-info-sub-title b3 mt--4">For orders over $9.90</p>
                                    <ul class="rbt-coupon-info-list mt--12">
                                        <li><span>12/18/2023 14:00 ~ 12/25/2023 14:00</span></li>
                                        <li><span>The minimum spend for this coupon <strong>$200.00</strong></span></li>
                                    </ul>
                                </div>
                                <button class="copy-icon rbt-round-btn rbt-bg-primary rbt-copy-btn" data-tooltip="Copy">
                                    <i class="fa-sharp fa-regular fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <form>
                        <div class="rbt-input-field-grp mt--24">
                            <p class="b1 mb--12 rbt-text-color-gray-600">If you have coupon code, please apply it below.
                            </p>
                            <input type="text" placeholder="Coupon code">
                        </div>
                        <div class="rbt-btn-group mt--16">
                            <button class="rbt-btn rbt-btn-md rbt-btn-primary d-block w-100">Apply</button>
                            <button
                                class="rbt-btn rbt-btn-md rbt-btn-naked d-block w-100 mt--8 mb--8 rbt-popup-close-btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End Side Nav -->
