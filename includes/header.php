<?php
// Expected: $page_title (string), $page_description (string, optional)
$page_title       = $page_title ?? s('site_name', 'Runners World');
$page_description = $page_description ?? s('site_description', 'Солонгосын шилдэг дэлгүүрүүдээс шууд авчирсан бүтээгдэхүүн');
$_categories      = getCategories();
$_activityTypes   = getActivityTypes();
$_shops           = getShops();
$_genderShowcase  = getGenderShowcaseImages();
$_cartCount       = cartCount();
$_user            = getSessionUser();
$_base            = getBaseUrl();
$_asset           = fn(string $p) => assetUrl($p);
$_url             = fn(string $p = '') => url($p);
?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <!-- Ochaka fonts & icons -->
    <link rel="stylesheet" href="<?= assetUrl('fonts/fonts.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('icon/icomoon/style.css') ?>">
    <!-- Ochaka CSS -->
    <link rel="stylesheet" href="<?= assetUrl('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('css/swiper-bundle.min.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('css/animate.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('css/styles.css') ?>">
    <!-- Custom overrides -->
    <link rel="stylesheet" href="<?= assetUrl('css/custom.css') ?>">
    <?php $_favicon = s('site_favicon', ''); ?>
    <link rel="shortcut icon" href="<?= $_favicon ? htmlspecialchars(fixImageUrl($_favicon)) : assetUrl('images/logo/favicon.svg') ?>">
    <?= $extra_head ?? '' ?>
</head>
<body>

    <!-- Scroll Top -->
    <button id="goTop">
        <span class="border-progress"></span>
        <span class="icon icon-caret-up"></span>
    </button>

    <!-- Preload -->
    <div class="preload preload-container" id="preload">
        <div class="preload-logo"><div class="spinner"></div></div>
    </div>

    <main id="wrapper">
        <!-- Top Bar -->
        <div class="tf-topbar bg-black">
            <div class="container-full">
                <div class="row">
                    <?php $_topBarText = trim(s('top_bar_text', '')); ?>
                    <?php if ($_topBarText): ?>
                    <div class="col-lg-8">
                        <div class="topbar-left">
                            <h6 class="text-up text-white fw-normal text-line-clamp-1">
                                <?= htmlspecialchars($_topBarText) ?>
                            </h6>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="<?= $_topBarText ? 'col-lg-4' : 'col-lg-12' ?> d-none d-lg-block">
                        <ul class="topbar-right topbar-option-list">
                            <li class="h6">
                                <a href="<?= url('contact.php') ?>" class="text-white link">Холбоо барих</a>
                            </li>
                            <li class="br-line"></li>
                            <li class="h6">
                                <a href="<?= url('faq.php') ?>" class="text-white link">Тусламж & FAQ</a>
                            </li>
                            <li class="br-line"></li>
                            <li class="h6">
                                <a href="<?= url('track-order.php') ?>" class="text-white link">Захиалга хянах</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Top Bar -->

        <!-- Header -->
        <header class="tf-header header-fix">
            <div class="container-full">
                <div class="row align-items-center">
                    <!-- Mobile menu toggle -->
                    <div class="col-md-4 col-3 d-xl-none">
                        <a href="#mobileMenu" data-bs-toggle="offcanvas" class="btn-mobile-menu">
                            <span></span>
                        </a>
                    </div>
                    <!-- Logo -->
                    <div class="col-xl-3 col-md-4 col-6 d-flex justify-content-center justify-content-xl-start">
                        <a href="<?= url() ?>" class="logo-site" style="display:flex;align-items:center;gap:0;">
                            <?php $logo = s('site_logo', ''); $siteName = htmlspecialchars(s('site_name', 'Runners World')); ?>
                            <?php if ($logo): ?>
                                <img src="<?= htmlspecialchars(fixImageUrl($logo)) ?>" alt="<?= $siteName ?>" style="height:36px;width:auto;object-fit:contain;display:block;">
                                <span class="fw-bold item-link" style="line-height:36px;padding-left:0;"><?= $siteName ?></span>
                            <?php else: ?>
                                <span class="fw-bold" style="font-size:1.4rem;letter-spacing:-0.5px;"><?= $siteName ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <!-- Desktop Nav -->
                    <div class="col-xl-6 d-none d-xl-block">
                        <nav class="box-navigation">
                            <ul class="box-nav-menu">
                                <li class="menu-item">
                                    <a href="<?= url('shop.php') ?>" class="item-link">ДЭЛГҮҮР<i class="icon icon-caret-down"></i></a>
                                    <div class="sub-menu mega-menu">
                                        <div class="container">
                                            <div class="row">
                                                <div class="col-3">
                                                    <div class="mega-menu-item">
                                                        <h4 class="menu-heading">Хүйс</h4>
                                                        <ul class="sub-menu_list">
                                                            <li><a href="<?= url('shop.php') ?>?gender%5B%5D=men" class="sub-menu_link">Эрэгтэй</a></li>
                                                            <li><a href="<?= url('shop.php') ?>?gender%5B%5D=women" class="sub-menu_link">Эмэгтэй</a></li>
                                                            <li><a href="<?= url('shop.php') ?>?gender%5B%5D=kids" class="sub-menu_link">Хүүхэд</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-3">
                                                    <div class="mega-menu-item">
                                                        <h4 class="menu-heading">Ангилал</h4>
                                                        <ul class="sub-menu_list">
                                                            <?php foreach ($_categories as $cat): ?>
                                                            <li>
                                                                <a href="<?= url('shop?category=' . urlencode($cat['slug'])) ?>" class="sub-menu_link">
                                                                    <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
                                                                </a>
                                                            </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <?php if (!empty($_activityTypes)): ?>
                                                <div class="col-3">
                                                    <div class="mega-menu-item">
                                                        <h4 class="menu-heading">Спортын төрөл</h4>
                                                        <ul class="sub-menu_list">
                                                            <?php foreach ($_activityTypes as $act): ?>
                                                            <li>
                                                                <a href="<?= url('shop.php') ?>?activity%5B%5D=<?= (int)$act['id'] ?>" class="sub-menu_link">
                                                                    <?= htmlspecialchars($act['name_mn'] ?: $act['name']) ?>
                                                                </a>
                                                            </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($_genderShowcase)): ?>
                                                <div class="col-3">
                                                    <ul class="list-ver">
                                                        <?php if (!empty($_genderShowcase['men'])): ?>
                                                        <li class="wg-cls hover-img" style="height:150px;">
                                                            <a href="<?= url('shop.php') ?>?gender%5B%5D=men" class="image img-style">
                                                                <img src="<?= htmlspecialchars(fixImageUrl($_genderShowcase['men'])) ?>" alt="Эрэгтэй" style="height:100%;width:100%;object-fit:cover;">
                                                            </a>
                                                            <div class="cls-content">
                                                                <h4 class="tag_cls">Эрэгтэй</h4>
                                                                <span class="br-line type-vertical"></span>
                                                                <a href="<?= url('shop.php') ?>?gender%5B%5D=men" class="tf-btn-line">Shop now</a>
                                                            </div>
                                                        </li>
                                                        <?php endif; ?>
                                                        <?php if (!empty($_genderShowcase['women'])): ?>
                                                        <li class="wg-cls hover-img" style="height:150px;">
                                                            <a href="<?= url('shop.php') ?>?gender%5B%5D=women" class="image img-style">
                                                                <img src="<?= htmlspecialchars(fixImageUrl($_genderShowcase['women'])) ?>" alt="Эмэгтэй" style="height:100%;width:100%;object-fit:cover;">
                                                            </a>
                                                            <div class="cls-content">
                                                                <h4 class="tag_cls">Эмэгтэй</h4>
                                                                <span class="br-line type-vertical"></span>
                                                                <a href="<?= url('shop.php') ?>?gender%5B%5D=women" class="tf-btn-line">Shop now</a>
                                                            </div>
                                                        </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($_shops)): ?>
                                            <div class="row mt-3 pt-3" style="border-top:1px solid #eee;">
                                                <div class="col-12">
                                                    <h4 class="menu-heading" style="margin-bottom: 24px;">Брэнд</h4>
                                                    <ul class="sub-menu_list" style="display:flex;flex-wrap:wrap;gap:8px;list-style:none;padding:0;margin:0;">
                                                        <?php foreach ($_shops as $sh): ?>
                                                        <li>
                                                            <a href="<?= url('shop?shop=' . urlencode($sh['slug'])) ?>" class="sub-menu_link"
                                                               style="display:inline-block;padding:5px 14px;border-radius:999px;border:1px solid #e5e7eb;font-size:.85rem;">
                                                                <?= htmlspecialchars($sh['name_mn'] ?: $sh['name']) ?>
                                                            </a>
                                                        </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                                <li class="menu-item">
                                    <a href="<?= url('blog') ?>" class="item-link">БЛОГ</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <!-- Icons -->
                    <div class="col-xl-3 col-md-4 col-3">
                        <ul class="nav-icon-list">
                            <li class="d-none d-lg-flex">
                                <?php if ($_user): ?>
                                <a class="nav-icon-item link" href="<?= url('account.php') ?>" title="<?= htmlspecialchars($_user['name']) ?>">
                                    <i class="icon icon-user"></i>
                                </a>
                                <?php else: ?>
                                <a class="nav-icon-item link" href="<?= url('login.php') ?>">
                                    <i class="icon icon-user"></i>
                                </a>
                                <?php endif; ?>
                            </li>
                            <li class="d-none d-md-flex">
                                <a class="nav-icon-item link" href="#search" data-bs-toggle="modal">
                                    <i class="icon icon-magnifying-glass"></i>
                                </a>
                            </li>
                            <li class="shop-cart" data-bs-toggle="offcanvas" data-bs-target="#shoppingCart">
                                <a class="nav-icon-item link" href="#shoppingCart" data-bs-toggle="offcanvas">
                                    <i class="icon icon-shopping-cart-simple"></i>
                                </a>
                                <span class="count" id="cart-count-badge"><?= $_cartCount ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>
        <!-- /Header -->

        <!-- Search Modal -->
        <div class="modal modalCentered fade modal-search" id="search">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                    <div>
                        <form class="form-search style-2" id="search-form" action="<?= url('shop.php') ?>" method="get">
                            <fieldset>
                                <input type="text" id="search-input" name="search" placeholder="Бараа хайх..." class="style-stroke" tabindex="0" autocomplete="off">
                            </fieldset>
                            <button type="submit" class="link"><i class="icon icon-magnifying-glass"></i></button>
                        </form>
                        <ul class="quick-link-list">
                            <?php foreach (array_slice($_categories, 0, 6) as $cat): ?>
                            <li>
                                <a href="<?= url('shop?category=' . urlencode($cat['slug'])) ?>" class="link-item text-main h6 link">
                                    <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div id="search-results" class="view-history-wrap" style="display:none;">
                        <h4 class="title">Хайлтын үр дүн</h4>
                        <div class="view-history-list" id="search-results-list"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Search Modal -->

        <!-- Page content starts here -->
