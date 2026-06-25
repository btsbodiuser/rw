<?php
// Expected: $page_title (string), $page_description (string, optional)
$page_title       = $page_title ?? s('site_name', 'Runners World');
$page_description = $page_description ?? s('site_description', 'Солонгосын шилдэг дэлгүүрүүдээс шууд авчирсан бүтээгдэхүүн');
$_categories      = getCategories();
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
    <link rel="stylesheet" href="<?= url('public/css/custom.css') ?>">
    <link rel="shortcut icon" href="<?= assetUrl('images/logo/favicon.svg') ?>">
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
                    <div class="col-lg-8">
                        <div class="topbar-left">
                            <h6 class="text-up text-white fw-normal text-line-clamp-1">
                                <?= htmlspecialchars(s('topbar_text', 'Солонгосоос шууд авчирсан жинхэнэ бүтээгдэхүүн — хурдан хүргэлт, итгэмжлэгдсэн үнэ')) ?>
                            </h6>
                        </div>
                    </div>
                    <div class="col-lg-4 d-none d-lg-block">
                        <ul class="topbar-right topbar-option-list">
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
                                    <a href="<?= url() ?>" class="item-link">НҮҮР</a>
                                </li>
                                <li class="menu-item">
                                    <a href="<?= url('shop.php') ?>" class="item-link">ДЭЛГҮҮР</a>
                                </li>
                                <?php if (!empty($_categories)): ?>
                                <li class="menu-item position-relative">
                                    <a href="#" class="item-link">АНГИЛАЛ<i class="icon icon-caret-down"></i></a>
                                    <div class="sub-menu">
                                        <ul class="sub-menu_list">
                                            <?php foreach ($_categories as $cat): ?>
                                            <li>
                                                <a href="<?= url('category/' . htmlspecialchars($cat['slug'])) ?>" class="sub-menu_link">
                                                    <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
                                                </a>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </li>
                                <?php endif; ?>
                                <li class="menu-item">
                                    <a href="<?= url('track-order.php') ?>" class="item-link">ЗАХИАЛГА</a>
                                </li>
                                <li class="menu-item">
                                    <a href="<?= url('contact.php') ?>" class="item-link">ХОЛБОО БАРИХ</a>
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
        <div class="modal fade modalCentered" id="search" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="header">
                        <span class="icon"><i class="icon-magnifying-glass"></i></span>
                        <input id="search-input" class="flex-grow-1" type="text" placeholder="Бараа хайх..." autofocus>
                        <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                    </div>
                    <div class="wrap" id="search-results">
                        <p class="text-small text-main text-center py-4">Хайх бараагаа бичнэ үү...</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Search Modal -->

        <!-- Page content starts here -->
