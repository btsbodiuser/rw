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

// ── CART: build line items from session ──────────────────────
$page_title = 'Сагс — ' . $siteName;

$cartLines = [];
$cartSubtotal = 0.0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $line) {
        $qty = (int)($line['qty'] ?? 0);
        if ($qty <= 0) continue;
        $lineTotal = (float)$line['price'] * $qty;
        $cartSubtotal += $lineTotal;
        $cartLines[] = [
            'key'         => $key,
            'product_id'  => (int)$line['product_id'],
            'variant_id'  => $line['variant_id'] ?? null,
            'slug'        => $line['slug'],
            'name'        => $line['name'],
            'image'       => fixImageUrl($line['image'] ?? null),
            'price'       => (float)$line['price'],
            'qty'         => $qty,
            'color'       => $line['color'] ?? '',
            'size'        => $line['size'] ?? '',
            'line_total'  => $lineTotal,
            'url'         => url('product?slug=' . urlencode($line['slug'])),
        ];
    }
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

        .rbt-list-view-variation .rbt-card-img {
            width: 110px;
            flex: 0 0 110px;
        }
    </style>
EXTRA_CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- SHOP BREADCRUMB -->
    <!-- CART BREADCRUMB -->
    <div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active">Сагс</li>
                </ul>
                <h1 class="title h3 mt--10">Миний сагс</h1>
            </div>
        </div>
    </div>

    <!-- CART MAIN -->
    <div class="rbt-shop-area rbt-section-gapBottom rbt-bg-color-white">
        <div class="container">
            <?php if (!$cartLines): ?>
            <div class="text-center py-5">
                <i class="fa-regular fa-cart-shopping" style="font-size:3rem;color:#ddd;"></i>
                <h4 class="mt--16">Сагс хоосон байна</h4>
                <p class="text-muted">Худалдан авалт хийхийн тулд бараа сонгоно уу.</p>
                <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border mt--12">Дэлгүүр рүү очих</a>
            </div>
            <?php else: ?>
            <div class="row row--30">
                <div class="col-lg-8 mt--30">
                    <?php foreach ($cartLines as $line): ?>
                    <div class="rbt-card rbt-product-card rbt-list-view-variation rbt-list-view-sm mb--16">
                        <div class="inner">
                            <div class="rbt-card-img rbt-bg-color-default">
                                <a href="<?= h($line['url']) ?>"><img src="<?= h($line['image']) ?>" alt="<?= h($line['name']) ?>"></a>
                            </div>
                            <div class="rbt-card-body d-flex flex-wrap justify-content-between align-items-center rbt-gap--12">
                                <div class="left-part">
                                    <h3 class="rbt-card-title h6"><a href="<?= h($line['url']) ?>"><?= h($line['name']) ?></a></h3>
                                    <?php if ($line['color'] || $line['size']): ?>
                                    <p class="text-muted small mb-0">
                                        <?= $line['color'] ? h($line['color']) : '' ?><?= ($line['color'] && $line['size']) ? ' / ' : '' ?><?= $line['size'] ? h($line['size']) : '' ?>
                                    </p>
                                    <?php endif; ?>
                                    <div class="pricing-part mt--4">
                                        <span class="price-text"><?= h(formatPrice($line['price'])) ?></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center rbt-gap--16 flex-wrap">
                                    <form method="POST" action="<?= h(url('cart-action')) ?>" class="d-flex align-items-center">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="key" value="<?= h($line['key']) ?>">
                                        <div class="rbt-qty-area rbt-qty-sm">
                                            <button type="button" class="qty-item-btn qty-item-btn-decr"><i class="fa-solid fa-minus"></i></button>
                                            <input type="number" name="qty" class="items-qty-input" value="<?= (int)$line['qty'] ?>" min="1" onchange="this.form.submit()">
                                            <button type="button" class="qty-item-btn qty-item-btn-incr"><i class="fa-solid fa-plus"></i></button>
                                        </div>
                                    </form>
                                    <div class="pricing-part mb-0">
                                        <span class="price-text"><?= h(formatPrice($line['line_total'])) ?></span>
                                    </div>
                                    <form method="POST" action="<?= h(url('cart-action')) ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="key" value="<?= h($line['key']) ?>">
                                        <button type="submit" class="rbt-round-btn" aria-label="Устгах"><i class="fa-regular fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border rbt-btn-sm has-left-icon">
                        <i class="fa-regular fa-arrow-left"></i> Дэлгүүр рүү буцах
                    </a>
                </div>

                <div class="col-lg-4 mt--30">
                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one p--24 rbt-rounded--12">
                        <h4 class="rbt-widget-title mb--16">Захиалгын дүн</h4>
                        <div class="d-flex justify-content-between mb--12">
                            <span>Дэд дүн</span>
                            <strong><?= h(formatPrice($cartSubtotal)) ?></strong>
                        </div>
                        <hr class="rbt-separator rbt-separator-gray200">
                        <div class="d-flex justify-content-between mb--16">
                            <span class="h6 mb-0">Нийт дүн</span>
                            <span class="h6 mb-0"><?= h(formatPrice($cartSubtotal)) ?></span>
                        </div>
                        <a href="<?= h(url('checkout')) ?>" class="rbt-btn rbt-btn-border w-100 text-center">Захиалга үргэлжлүүлэх</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ALL CATEGORIES -->


<?php require __DIR__ . '/includes/footer.php'; ?>
