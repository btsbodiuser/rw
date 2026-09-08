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

// SKUs aren't stored in the session cart — batch-fetch them (variant SKU wins)
if ($cartLines) {
    $pids = array_unique(array_column($cartLines, 'product_id'));
    $ph   = implode(',', array_fill(0, count($pids), '?'));
    $stmt = $db->prepare("SELECT id, sku FROM products WHERE id IN ($ph)");
    $stmt->execute(array_values($pids));
    $productSkus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $vids = array_filter(array_column($cartLines, 'variant_id'));
    $variantSkus = [];
    if ($vids) {
        $ph   = implode(',', array_fill(0, count($vids), '?'));
        $stmt = $db->prepare("SELECT id, sku FROM product_variants WHERE id IN ($ph)");
        $stmt->execute(array_values($vids));
        $variantSkus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    foreach ($cartLines as &$cl) {
        $cl['sku'] = ($cl['variant_id'] ? ($variantSkus[$cl['variant_id']] ?? '') : '')
                  ?: ($productSkus[$cl['product_id']] ?? '');
    }
    unset($cl);
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

        /* Cart table: compact type, wrapping names */
        .rbt-transparent-table-one .rbt-wish-product-name {
            font-size: 15px;
            line-height: 1.4;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word; /* Safari < 15.4 fallback for overflow-wrap:anywhere */
            max-width: 300px;
            margin-bottom: 4px;
        }
        .rbt-transparent-table-one .price-text.h6 {
            font-size: 15px;
        }
        .rbt-transparent-table-one .rbt-product-id {
            font-size: 12px;
            color: #6b7280;
        }
        .rbt-transparent-table-one thead th {
            font-size: 13px;
        }
        /* Safari: theme's 120px min-width per cell forces horizontal scroll */
        .rbt-transparent-table-one.table-variation-one tbody tr td,
        .rbt-transparent-table-one.table-variation-one thead tr th {
            min-width: 90px;
        }
        .rbt-transparent-table-one { width: 100%; }
        .rbt-scrollable-content { -webkit-overflow-scrolling: touch; }
        /* Consistent qty input across browsers (Safari shows native spinners) */
        .items-qty-input::-webkit-outer-spin-button,
        .items-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .items-qty-input { -moz-appearance: textfield; appearance: textfield; }
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
            <div class="row row--12 mt_dec--24">
                <div class="col-12 col-md-12 col-lg-8 mt--24">
                    <div class="rbt-transparent-table-one-wrapper rbt-has-bg-gray rbt-scrollable-content">
                        <table class="rbt-transparent-table-one table-variation-one mb--0">
                            <thead>
                                <tr>
                                    <th scope="col">Бараа</th>
                                    <th scope="col">Үнэ</th>
                                    <th scope="col">Тоо</th>
                                    <th scope="col">Нийт</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartLines as $line): ?>
                                <tr>
                                    <td>
                                        <div class="cart-product-card">
                                            <div class="product-thumbnail">
                                                <a href="<?= h($line['url']) ?>">
                                                    <img src="<?= h($line['image']) ?>" alt="<?= h($line['name']) ?>">
                                                </a>
                                                <form method="POST" action="<?= h(url('cart-action')) ?>" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="key" value="<?= h($line['key']) ?>">
                                                    <button type="submit" class="close-btn" aria-label="Устгах"><i class="fa-solid fa-xmark"></i></button>
                                                </form>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <h3 class="rbt-wish-product-name h6">
                                                    <a href="<?= h($line['url']) ?>"><?= h($line['name']) ?></a>
                                                </h3>
                                                <?php if (!empty($line['sku'])): ?>
                                                <span class="rbt-product-id"><span class="rbt-text-semi-bold">SKU:</span> <?= h($line['sku']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($line['color'] || $line['size']): ?>
                                                <span class="rbt-product-id">
                                                    <?= $line['color'] ? h($line['color']) : '' ?><?= ($line['color'] && $line['size']) ? ' / ' : '' ?><?= $line['size'] ? h($line['size']) : '' ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="price-text h6 d-block"><?= h(formatPrice($line['price'])) ?></span>
                                    </td>
                                    <td>
                                        <form method="POST" action="<?= h(url('cart-action')) ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="key" value="<?= h($line['key']) ?>">
                                            <div class="rbt-qty-area rbt-qty-sm">
                                                <button type="button" class="qty-item-btn qty-item-btn-decr"><i class="fa-solid fa-minus"></i></button>
                                                <input type="number" name="qty" class="items-qty-input" value="<?= (int)$line['qty'] ?>" min="1" onchange="this.form.submit()">
                                                <button type="button" class="qty-item-btn qty-item-btn-incr"><i class="fa-solid fa-plus"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="price-text h6 d-block"><span class="rbt-bold--text"><?= h(formatPrice($line['line_total'])) ?></span></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border rbt-btn-sm has-left-icon mt--24">
                        <i class="fa-regular fa-arrow-left"></i> Дэлгүүр рүү буцах
                    </a>
                </div>

                <div class="col-12 col-md-12 col-lg-4 mt--24">
                    <div class="rbt-sidebar-widget mt--0">
                        <div class="rbt-inner">
                            <div class="rbt-cart-subttotal">
                                <p>Дэд дүн (<?= count($cartLines) ?> бараа)</p>
                                <p class="price"><?= h(formatPrice($cartSubtotal)) ?></p>
                            </div>
                            <hr class="mb--8 mt--8 rbt-bg-color-gray-200">
                            <div class="rbt-cart-subttotal mb--12">
                                <p class="subtotal"><strong>Нийт дүн</strong></p>
                                <p class="price"><?= h(formatPrice($cartSubtotal)) ?></p>
                            </div>
                            <div class="rbt-minicart-bottom mt--24">
                                <div class="checkout-btn mt--20">
                                    <a class="rbt-btn w-100 text-center" href="<?= h(url('checkout')) ?>">
                                        <span class="btn-text">Захиалга үргэлжлүүлэх</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ALL CATEGORIES -->


<?php require __DIR__ . '/includes/footer.php'; ?>
