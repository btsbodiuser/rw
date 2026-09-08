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

// ── PRODUCT: load by slug ────────────────────────────────────
$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . $urlShop);
    exit;
}

$pstmt = $db->prepare("
    SELECT p.*,
           c.slug AS category_slug, c.name_mn AS category_name_mn, c.name AS category_name,
           s.slug AS shop_slug, s.name_mn AS shop_name_mn, s.name AS shop_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN shops s ON s.id = p.shop_id
    WHERE p.slug = ? AND p.is_active = 1 AND p.show_in_store = 1
    LIMIT 1
");
$pstmt->execute([$slug]);
$product = $pstmt->fetch();

if (!$product) {
    http_response_code(404);
}

if ($product) {
    $prodName    = $product['name_mn'] ?: $product['name'];
    $page_title  = $prodName . ' — ' . $siteName;

    // Gallery: resolve image_ids (JSON array of media ids) to URLs, fallback to main image
    $galleryImages = [];
    if (!empty($product['image_ids'])) {
        $ids = array_values(array_filter(array_map('intval', json_decode($product['image_ids'], true) ?: [])));
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $mstmt = $db->prepare("SELECT id, filename FROM media WHERE id IN ($ph)");
            $mstmt->execute($ids);
            $mediaById = [];
            foreach ($mstmt->fetchAll() as $m) { $mediaById[$m['id']] = $m['filename']; }
            foreach ($ids as $mid) {
                if (!empty($mediaById[$mid])) $galleryImages[] = fixImageUrl('uploads/media/' . $mediaById[$mid]);
            }
        }
    }
    if (!$galleryImages && !empty($product['image'])) $galleryImages[] = fixImageUrl($product['image']);
    if (!$galleryImages) $galleryImages[] = fixImageUrl(null);

    // Variants (color x size), if this product has them
    $hasVariants = !empty($product['has_variants']);
    $variants = [];
    $variantColors = [];
    $variantSizes = [];
    if ($hasVariants) {
        try {
            $vstmt = $db->prepare("
                SELECT v.id, v.color_id, v.size_id, v.price_override, v.stock,
                       co.name_mn AS color_name_mn, co.name AS color_name, co.hex_code,
                       sz.name AS size_name
                FROM product_variants v
                LEFT JOIN product_colors co ON co.id = v.color_id
                LEFT JOIN product_sizes sz ON sz.id = v.size_id
                WHERE v.product_id = ? AND v.is_active = 1
                ORDER BY co.sort_order, sz.sort_order
            ");
            $vstmt->execute([$product['id']]);
            $variants = $vstmt->fetchAll();
        } catch (Throwable) { $variants = []; }

        foreach ($variants as $v) {
            if ($v['color_id'] && !isset($variantColors[$v['color_id']])) {
                $variantColors[$v['color_id']] = [
                    'id'   => (int)$v['color_id'],
                    'name' => $v['color_name_mn'] ?: $v['color_name'],
                    'hex'  => $v['hex_code'],
                ];
            }
            if ($v['size_id'] && !isset($variantSizes[$v['size_id']])) {
                $variantSizes[$v['size_id']] = [
                    'id'   => (int)$v['size_id'],
                    'name' => $v['size_name'],
                ];
            }
        }
    }

    // Running-attribute tags (real data — grouped by category so each row can carry its own label)
    function productAttrTags(PDO $db, int $pid, string $pivot, string $table, string $fk): array {
        try {
            $stmt = $db->prepare("SELECT t.name_mn, t.name FROM `$pivot` pv JOIN `$table` t ON t.id = pv.`$fk` WHERE pv.product_id = ?");
            $stmt->execute([$pid]);
            return array_map(fn($r) => $r['name_mn'] ?: $r['name'], $stmt->fetchAll());
        } catch (Throwable) { return []; }
    }
    $specGroups = array_values(array_filter([
        ['label' => 'Гутлын төрөл',    'key' => 'shoe_type',         'items' => productAttrTags($db, $product['id'], 'product_shoe_types',         'shoe_types',         'shoe_type_id')],
        ['label' => 'Гүйлтийн төрөл',  'key' => 'run_type',          'items' => productAttrTags($db, $product['id'], 'product_run_types',          'run_types',          'run_type_id')],
        ['label' => 'Зөөлөвч',         'key' => 'cushioning',        'items' => productAttrTags($db, $product['id'], 'product_cushionings',        'cushionings',        'cushioning_id')],
        ['label' => 'Алхалт',          'key' => 'gait_type',         'items' => productAttrTags($db, $product['id'], 'product_gait_types',         'gait_types',         'gait_type_id')],
        ['label' => 'Техникийн онцлог','key' => 'technical_feature', 'items' => productAttrTags($db, $product['id'], 'product_technical_features', 'technical_features', 'technical_feature_id')],
    ], fn($g) => !empty($g['items'])));

    // Related products — same category
    try {
        $rstmt = $db->prepare("
            SELECT p.id, p.slug, p.name, p.name_mn, p.price, p.original_price,
                   p.image, p.stock, p.rating, p.reviews, p.created_at, p.type,
                   c.slug AS category_slug, c.name_mn AS category_name_mn, c.name AS category_name,
                   s.slug AS shop_slug, s.name_mn AS shop_name_mn, s.name AS shop_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN shops s ON s.id = p.shop_id
            WHERE p.is_active = 1 AND p.show_in_store = 1 AND p.category_id = ? AND p.id != ?
            ORDER BY p.created_at DESC
            LIMIT 8
        ");
        $rstmt->execute([$product['category_id'], $product['id']]);
        $relatedProducts = $rstmt->fetchAll();
    } catch (Throwable) { $relatedProducts = []; }

    // Presentation values
    $prodBrand    = $product['shop_name_mn'] ?: ($product['shop_name'] ?: '');
    $prodBrandUrl = !empty($product['shop_slug']) ? url('shop?shop=' . urlencode($product['shop_slug'])) : $urlShop;
    $prodCatName  = $product['category_name_mn'] ?: ($product['category_name'] ?: '');
    $prodCatUrl   = !empty($product['category_slug']) ? url('shop?category=' . urlencode($product['category_slug'])) : $urlShop;
    $prodDesc     = $product['description_mn'] ?: ($product['description'] ?: '');
    $prodPrice    = (float)$product['price'];
    $prodOld      = $product['original_price'] !== null ? (float)$product['original_price'] : null;
    $hasSale      = $prodOld && $prodOld > $prodPrice;
    $discountPct  = $hasSale ? (int)round(100 - ($prodPrice / $prodOld * 100)) : 0;
    $rating       = (float)($product['rating'] ?? 0);
    $reviewsCount = (int)($product['reviews'] ?? 0);
    $stockNum     = $hasVariants ? (int)array_sum(array_column($variants, 'stock')) : (int)$product['stock'];
    $isSoldOut    = $stockNum <= 0;
    $isPreorder   = ($product['type'] === 'preorder');
    $isNew        = strtotime($product['created_at']) > strtotime('-30 days');
    $prodUrl      = url('product?slug=' . urlencode($product['slug']));
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

        /* Gallery main image + thumbs: force a consistent 1:1 frame and show the
           whole photo (contain), not a crop — our real product photos are portrait
           (shoes, apparel), and the theme's own default (object-fit:cover, height
           inherited from whatever the swiper computes) would both crop the product
           itself and vary in height from product to product. !important beats the
           theme's more specific `.rbt-product-single-img img{object-fit:cover}`. */
        .rbt-single-product-media-area .rbt-product-single-img,
        .rbt-single-product-media-area .rbt-thumb-img-sm {
            aspect-ratio: 1 / 1;
            height: auto !important;
            background: var(--color-gray-light, #f2f2f2);
        }
        .rbt-single-product-media-area .rbt-product-single-img img,
        .rbt-single-product-media-area .rbt-thumb-img-sm img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            object-position: center;
        }

        /* Variant pickers */
        .rw-variant-swatch,
        .rw-variant-size-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid var(--color-gray-300, #ddd);
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .rw-variant-swatch.active,
        .rw-variant-size-btn.active {
            border-color: var(--color-primary, #111);
            background: var(--color-gray-light, #f7f7f7);
        }
        .rw-variant-swatch-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
            border: 1px solid rgba(0,0,0,.15);
        }

    </style>
EXTRA_CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- SHOP BREADCRUMB -->
    <!-- PRODUCT MAIN -->
    <?php if (!$product): ?>
    <div class="rbt-shop-area rbt-section-gapBottom rbt-bg-color-white pt--60">
        <div class="container text-center py-5">
            <i class="fa-regular fa-bag-shopping" style="font-size:3rem;color:#ddd;"></i>
            <h4 class="mt--16">Бараа олдсонгүй</h4>
            <p class="text-muted">Энэ бараа устсан эсвэл байхгүй байна.</p>
            <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border mt--12">Бүх бараа харах</a>
        </div>
    </div>
    <?php else: ?>

    <div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlShop) ?>">Дэлгүүр</a></li>
                    <?php if ($prodCatName): ?>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item"><a href="<?= h($prodCatUrl) ?>"><?= h($prodCatName) ?></a></li>
                    <?php endif; ?>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active"><?= h($prodName) ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="rbt-component-area rbt-single-product-area rbt-bg-color-white rbt-section-gapBottom">
        <div class="container">
            <div class="row row--30">

                <!-- Gallery -->
                <div class="col-xl-6 col-lg-6 col-12 mt--30">
                    <div class="rbt-single-product-media-area position-sticky-top d-flex row row--12 rbt-gap--0">
                        <?php if (count($galleryImages) > 1): ?>
                        <div class="col-lg-1-5 col-lg-2 order-2 order-lg-1">
                            <div class="swiper product-single-slider-two-thumb-activation rbt-arrow-show-dfl rbt-thumb-has-bg-shape-overlay rbt-swiper-right-bottom-one rbt-arrow-between rbt-swiper-arrow-transparent">
                                <div class="swiper-wrapper rbt-store-thumb-variation-1">
                                    <?php foreach ($galleryImages as $gi => $img): ?>
                                    <div class="swiper-slide">
                                        <button class="thumbnail d-block position-relative">
                                            <span class="rbt-thumb-img-sm"><img class="w-100" src="<?= h($img) ?>" alt="<?= h($prodName) ?>"></span>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="rbt-swiper-arrow rbt-arrow-right"><i class="fa-regular fa-chevron-down"></i></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="<?= count($galleryImages) > 1 ? 'col-lg-4-5 col-lg-10 order-1 order-lg-2' : 'col-12' ?>">
                            <?php if (count($galleryImages) > 1): ?>
                            <div class="swiper rbt-medea-lg-img-area-md-wider product-single-slider-two-activation rbt-arrow-between rbt-arrow-show-dfl">
                                <?php if ($isNew): ?>
                                <div class="rbt-product-badge rbt-product-badge-bg-green rbt-badge-top-left--position">Шинэ</div>
                                <?php endif; ?>
                                <?php if ($hasSale && !$isSoldOut): ?>
                                <div class="rbt-product-badge rbt-bg-color-secondary rbt-badge-top-left--position">-<?= $discountPct ?>%</div>
                                <?php endif; ?>

                                <button class="rbt-enlarge-btn position-bottom-right" data-fancybox="product-single-gallary" data-src="<?= h($galleryImages[0]) ?>">
                                    <span class="rbt-icon"><i class="fa-regular fa-arrows-maximize"></i></span>
                                    <span class="rbt-enlarge-text">Томруулах</span>
                                </button>

                                <div class="swiper-wrapper rbt-store-thumb-main-1">
                                    <?php foreach ($galleryImages as $gi => $img): ?>
                                    <div class="swiper-slide">
                                        <div class="thumbnail">
                                            <div class="rbt-product-single-img">
                                                <img class="w-100" data-fancybox="product-single-gallary" data-src="<?= h($img) ?>" src="<?= h($img) ?>" alt="<?= h($prodName) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="rbt-swiper-arrow rbt-arrow-left">
                                    <div class="custom-overflow"><i class="rbt-icon fa-regular fa-arrow-left"></i><i class="rbt-icon-top fa-regular fa-arrow-left"></i></div>
                                </div>
                                <div class="rbt-swiper-arrow rbt-arrow-right">
                                    <div class="custom-overflow"><i class="rbt-icon fa-regular fa-arrow-right"></i><i class="rbt-icon-top fa-regular fa-arrow-right"></i></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="rbt-medea-lg-img-area-md-wider position-relative">
                                <?php if ($isNew): ?>
                                <div class="rbt-product-badge rbt-product-badge-bg-green rbt-badge-top-left--position">Шинэ</div>
                                <?php endif; ?>
                                <?php if ($hasSale && !$isSoldOut): ?>
                                <div class="rbt-product-badge rbt-bg-color-secondary rbt-badge-top-left--position">-<?= $discountPct ?>%</div>
                                <?php endif; ?>

                                <button class="rbt-enlarge-btn position-bottom-right" data-fancybox="product-single-gallary" data-src="<?= h($galleryImages[0]) ?>">
                                    <span class="rbt-icon"><i class="fa-regular fa-arrows-maximize"></i></span>
                                    <span class="rbt-enlarge-text">Томруулах</span>
                                </button>

                                <div class="thumbnail">
                                    <div class="rbt-product-single-img">
                                        <img class="w-100" data-fancybox="product-single-gallary" data-src="<?= h($galleryImages[0]) ?>" src="<?= h($galleryImages[0]) ?>" alt="<?= h($prodName) ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="col-xl-6 col-lg-6 col-12 mt--30">
                    <div class="rbt-single-product-content ptb--0">
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($prodCatName): ?>
                            <a href="<?= h($prodCatUrl) ?>" class="rbt-card-subtitle rbt-card-catagories-text"><?= h($prodCatName) ?></a>
                            <?php endif; ?>
                            <?php if ($prodBrand): ?>
                            <a href="<?= h($prodBrandUrl) ?>" class="rbt-card-subtitle rbt-card-catagories-text"><?= h($prodBrand) ?></a>
                            <?php endif; ?>
                        </div>
                        <h2 class="rbt-card-title mt--12"><?= h($prodName) ?></h2>

                        <?php $skuText = $product['sku'] ?? '' ?: ($product['barcode'] ?? ''); ?>
                        <?php if ($skuText !== ''): ?>
                        <p class="rbt-text-color-gray-600 mt--8 mb--0 b3"><span class="rbt-text-semi-bold">SKU:</span> <span id="rwSku"><?= h($skuText) ?></span></p>
                        <?php endif; ?>

                        <?php if ($rating > 0 || $reviewsCount > 0): ?>
                        <div class="rbt-card-rating mt--12">
                            <ul class="rbt-rating-icon-list">
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <li><i class="fa-solid fa-star <?= $r <= round($rating) ? 'rbt-rated-icon' : '' ?>"></i></li>
                                <?php endfor; ?>
                            </ul>
                            <p class="rating-digit">(<?= $reviewsCount ?>)</p>
                        </div>
                        <?php endif; ?>

                        <div class="rbt-info-wrapper mt--16">
                            <div class="pricing-part mt--0" id="rwPriceBox">
                                <?php if ($hasSale): ?>
                                <del class="price-text" id="rwPriceOld"><?= h(formatPrice($prodOld)) ?></del>
                                <?php endif; ?>
                                <span class="price-text" id="rwPriceNow"><?= h(formatPrice($prodPrice)) ?></span>
                                <?php if ($hasSale): ?>
                                <span class="rbt-offer-badge rbt-offer-badge-md">-<?= $discountPct ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($prodDesc): ?>
                        <p class="description-text b2 mt--16"><?= nl2br(h($prodDesc)) ?></p>
                        <?php endif; ?>

                        <?php
                        $allSpecTags = [];
                        foreach ($specGroups as $g) { foreach ($g['items'] as $t) $allSpecTags[] = $t; }
                        ?>
                        <?php if ($allSpecTags): ?>
                        <div class="rbt-info-wrapper d-flex mt--24">
                            <div class="prd-info-section">
                                <div class="prd-id-text">
                                    <div data-type="list" data-variation_key="rbt_product_attribute_technical" class="rbt-product-switch-area">
                                        <ul class="rbt-switcher-root rbt-switcher-product-list product-switcher-activation">
                                            <?php foreach ($allSpecTags as $tag): ?>
                                            <li><a data-value="<?= h($tag) ?>" class="rbt-btn rbt-btn-border rbt-btn-xs rbt-store-button" href="#0">
                                                    <span class="rbt-store-radio-button"><?= h($tag) ?></span>
                                                </a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= h(url('cart-action')) ?>" id="rwAddToCartForm">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                            <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI'] ?? $prodUrl) ?>">
                            <input type="hidden" name="variant_id" id="rwVariantIdInput" value="">

                            <?php if ($hasVariants && $variantColors): ?>
                            <div class="rbt-single-widget mt--24">
                                <h4 class="rbt-widget-title mb--12">Өнгө</h4>
                                <div class="d-flex gap-2 flex-wrap" id="rwColorGroup">
                                    <?php foreach ($variantColors as $c): ?>
                                    <label class="rw-variant-swatch" title="<?= h($c['name']) ?>">
                                        <input type="radio" name="color_id" value="<?= (int)$c['id'] ?>" class="visually-hidden">
                                        <span class="rw-variant-swatch-dot" style="background:<?= h($c['hex'] ?: '#ccc') ?>"></span>
                                        <span class="rw-variant-swatch-label"><?= h($c['name']) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($hasVariants && $variantSizes): ?>
                            <div class="rbt-single-widget mt--16">
                                <h4 class="rbt-widget-title mb--12">Хэмжээ</h4>
                                <div class="d-flex gap-2 flex-wrap" id="rwSizeGroup">
                                    <?php foreach ($variantSizes as $s): ?>
                                    <label class="rw-variant-size-btn">
                                        <input type="radio" name="size_id" value="<?= (int)$s['id'] ?>" class="visually-hidden">
                                        <span><?= h($s['name']) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($hasVariants): ?>
                            <p class="text-muted small mt--8 mb-0" id="rwVariantMsg">Өнгө, хэмжээгээ сонгоно уу.</p>
                            <?php endif; ?>

                            <div class="product-btn-grp mt--24">
                                <div class="rbt-qty-area">
                                    <button type="button" class="qty-item-btn qty-item-btn-decr"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" name="qty" class="items-qty-input" value="1" min="1">
                                    <button type="button" class="qty-item-btn qty-item-btn-incr"><i class="fa-solid fa-plus"></i></button>
                                </div>
                                <?php if ($isSoldOut): ?>
                                <button type="button" class="rbt-btn rbt-btn-border d-block text-center" disabled>Дууссан</button>
                                <?php else: ?>
                                <button type="submit" id="rwAddToCartBtn" class="rbt-btn rbt-btn-border has-left-icon d-block text-center"
                                        <?= $hasVariants ? 'disabled' : '' ?>>
                                    <i class="fa-regular fa-cart-shopping"></i> Сагслах
                                </button>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isSoldOut): ?>
                            <div class="prd-btn-grp">
                                <a id="rwBuyNowBtn" class="rbt-btn d-block text-center w-100<?= $hasVariants ? ' disabled' : '' ?>" href="#">Шууд худалдаж авах</a>
                            </div>
                            <?php endif; ?>
                        </form>

                        <div class="rbt-quick-link-grp mt--16">
                            <button type="button" class="rbt-quick-link" data-bs-toggle="modal" data-bs-target="#socialShareModal"><i class="fa-sharp fa-regular fa-share-nodes"></i><span>Хуваалцах</span></button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($relatedProducts): ?>
            <div class="row mt--60">
                <div class="col-12">
                    <h3 class="title mb--24">Төстэй бараа</h3>
                </div>
            </div>
            <div class="row row--12 mt_dec--24">
                <?php foreach ($relatedProducts as $i => $rp): renderProductCard($rp, $i, 'col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 col-6'); endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($hasVariants): ?>
    <script>
    (function () {
        var variants = <?= json_encode(array_map(function ($v) {
            return [
                'id' => (int)$v['id'],
                'color_id' => $v['color_id'] ? (int)$v['color_id'] : null,
                'size_id' => $v['size_id'] ? (int)$v['size_id'] : null,
                'stock' => (int)$v['stock'],
                'price' => $v['price_override'] !== null ? (float)$v['price_override'] : null,
                'sku' => $v['sku'] ?? null,
            ];
        }, $variants), JSON_UNESCAPED_UNICODE) ?>;
        var basePrice = <?= json_encode($prodPrice) ?>;
        var baseSku   = <?= json_encode($product['sku'] ?? '' ?: ($product['barcode'] ?? '')) ?>;

        function selectedValue(name) {
            var el = document.querySelector('input[name="' + name + '"]:checked');
            return el ? parseInt(el.value, 10) : null;
        }
        function findVariant() {
            var colorId = selectedValue('color_id');
            var sizeId = selectedValue('size_id');
            var hasColors = document.getElementById('rwColorGroup');
            var hasSizes = document.getElementById('rwSizeGroup');
            if (hasColors && colorId === null) return null;
            if (hasSizes && sizeId === null) return null;
            for (var i = 0; i < variants.length; i++) {
                var v = variants[i];
                if ((v.color_id === colorId) && (v.size_id === sizeId)) return v;
            }
            return null;
        }
        function formatPrice(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '₮';
        }
        function update() {
            var v = findVariant();
            var input = document.getElementById('rwVariantIdInput');
            var btn = document.getElementById('rwAddToCartBtn');
            var buyBtn = document.getElementById('rwBuyNowBtn');
            var msg = document.getElementById('rwVariantMsg');
            var priceNow = document.getElementById('rwPriceNow');
            var skuEl = document.getElementById('rwSku');
            if (!v) {
                input.value = '';
                if (btn) btn.disabled = true;
                if (buyBtn) buyBtn.classList.add('disabled');
                if (msg) msg.textContent = 'Өнгө, хэмжээгээ сонгоно уу.';
                if (skuEl) skuEl.textContent = baseSku;
                return;
            }
            input.value = v.id;
            if (priceNow) priceNow.textContent = formatPrice(v.price !== null ? v.price : basePrice);
            if (skuEl) skuEl.textContent = v.sku || baseSku;
            if (v.stock > 0) {
                if (btn) btn.disabled = false;
                if (buyBtn) buyBtn.classList.remove('disabled');
                if (msg) msg.textContent = v.stock + ' ширхэг үлдсэн';
            } else {
                if (btn) btn.disabled = true;
                if (buyBtn) buyBtn.classList.add('disabled');
                if (msg) msg.textContent = 'Энэ сонголт дууссан байна';
            }
        }
        document.querySelectorAll('input[name="color_id"], input[name="size_id"]').forEach(function (el) {
            el.addEventListener('change', update);
        });
        document.querySelectorAll('.rw-variant-swatch, .rw-variant-size-btn').forEach(function (label) {
            label.addEventListener('click', function () {
                document.querySelectorAll('.' + label.className.split(' ')[0]).forEach(function (l) { l.classList.remove('active'); });
                label.classList.add('active');
            });
        });
        update();
    })();
    </script>
    <?php endif; ?>

    <script>
    (function () {
        // "Buy Now": submits the Add-to-Cart form but redirects to the cart page.
        var buyBtn = document.getElementById('rwBuyNowBtn');
        var form = document.getElementById('rwAddToCartForm');
        var redirectInput = form ? form.querySelector('input[name="redirect"]') : null;
        if (buyBtn && form && redirectInput) {
            buyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (buyBtn.classList.contains('disabled')) return;
                redirectInput.value = <?= json_encode(url('cart')) ?>;
                form.submit();
            });
        }

        // Share: point the already-loaded #socialShareModal at THIS product's real URL.
        var shareModal = document.getElementById('socialShareModal');
        if (shareModal) {
            var shareUrl = <?= json_encode($prodUrl) ?>;
            var shareTitle = <?= json_encode($prodName) ?>;
            shareModal.addEventListener('show.bs.modal', function () {
                var copyField = shareModal.querySelector('.rbt-copy-value-field');
                if (copyField) copyField.value = shareUrl;
                var encUrl = encodeURIComponent(shareUrl), encTitle = encodeURIComponent(shareTitle);
                var links = {
                    'facebook-btn': 'https://www.facebook.com/sharer/sharer.php?u=' + encUrl,
                    'telegram-btn': 'https://t.me/share/url?url=' + encUrl + '&text=' + encTitle,
                    'whatsapp-btn': 'https://wa.me/?text=' + encTitle + '%20' + encUrl,
                    'email-btn':    'mailto:?subject=' + encTitle + '&body=' + encUrl
                };
                Object.keys(links).forEach(function (cls) {
                    var a = shareModal.querySelector('.' + cls);
                    if (a) a.href = links[cls];
                });
            });
        }
    })();
    </script>

    <?php endif; // $product ?>


    <!-- ALL CATEGORIES -->


<?php require __DIR__ . '/includes/footer.php'; ?>
