<?php
require_once __DIR__ . '/includes/config.php';

// ── Resolve product ────────────────────────────────────────────────────────
$db = getDB();

// Fallback: extract slug from /product/{slug} URL if rewrite didn't set it
if (empty($_GET['slug']) && empty($_GET['id'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('~/product/([^/?]+)~', $uri, $m)) {
        $_GET['slug'] = urldecode($m[1]);
    }
}

$product = null;
if (!empty($_GET['slug'])) {
    $stmt = $db->prepare("
        SELECT p.*, c.name_mn AS category_name, c.name AS category_name_en, c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.slug = ? AND p.show_in_store = 1
        LIMIT 1
    ");
    $stmt->execute([trim($_GET['slug'])]);
    $product = $stmt->fetch();
} elseif (!empty($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT p.*, c.name_mn AS category_name, c.name AS category_name_en, c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.id = ? AND p.show_in_store = 1
        LIMIT 1
    ");
    $stmt->execute([(int)$_GET['id']]);
    $product = $stmt->fetch();
}

if (!$product) {
    http_response_code(404);
    $page_title = '404 — Бүтээгдэхүүн олдсонгүй';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container py-5 text-center"><h2>Бүтээгдэхүүн олдсонгүй</h2><a href="' . url('shop.php') . '" class="tf-btn animate-btn mt-3">Дэлгүүр рүү буцах</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ── Product images ─────────────────────────────────────────────────────────
// Build gallery from product_color_images + media, fall back to product.image
$galleryImages = [];
try {
    $imgRows = $db->prepare("
        SELECT m.filename FROM product_color_images pci
        JOIN media m ON m.id = pci.media_id
        WHERE pci.product_id = ?
        ORDER BY pci.sort_order ASC
    ");
    $imgRows->execute([$product['id']]);
    foreach ($imgRows->fetchAll() as $pi) {
        $galleryImages[] = fixImageUrl($pi['filename']);
    }
} catch (Throwable $e) {}
if (empty($galleryImages)) {
    $galleryImages[] = fixImageUrl($product['image']);
}

// ── Variants ───────────────────────────────────────────────────────────────
$variants = [];
$colors   = [];
$sizes    = [];
if (!empty($product['has_variants'])) {
    try {
        $vStmt = $db->prepare("
            SELECT pv.id AS variant_id, pv.sku, pv.price_override, pv.stock,
                   pv.color_id, pv.size_id,
                   pc.name AS color_name, pc.hex_code AS color_hex,
                   ps.name AS size_name
            FROM product_variants pv
            LEFT JOIN product_colors pc ON pc.id = pv.color_id
            LEFT JOIN product_sizes  ps ON ps.id  = pv.size_id
            WHERE pv.product_id = ? AND pv.is_active = 1
            ORDER BY pv.id ASC
        ");
        $vStmt->execute([$product['id']]);
        $variants = $vStmt->fetchAll();

        foreach ($variants as $v) {
            if (!empty($v['color_id']) && !isset($colors[$v['color_id']])) {
                $colors[$v['color_id']] = ['name' => $v['color_name'], 'hex' => $v['color_hex']];
            }
            if (!empty($v['size_id']) && !isset($sizes[$v['size_id']])) {
                $sizes[$v['size_id']] = $v['size_name'];
            }
        }
    } catch (Throwable $e) {}
}

// ── Related products ───────────────────────────────────────────────────────
$related = [];
if (!empty($product['category_id'])) {
    $rStmt = $db->prepare("
        SELECT p.id, p.slug, p.name, p.name_mn, p.type, p.price, p.original_price, p.image, p.stock,
               c.name_mn AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.category_id = ? AND p.id != ? AND p.show_in_store = 1
        ORDER BY p.reviews DESC, p.rating DESC
        LIMIT 8
    ");
    $rStmt->execute([$product['category_id'], $product['id']]);
    $related = $rStmt->fetchAll();
}

// ── Computed values ────────────────────────────────────────────────────────
$productName  = $product['name_mn'] ?: $product['name'];
$price        = (float)$product['price'];
$origPrice    = !empty($product['original_price']) ? (float)$product['original_price'] : null;
$discount     = ($origPrice && $origPrice > $price) ? round((1 - $price / $origPrice) * 100) : 0;
$rating       = (float)($product['rating'] ?? 0);
$reviewCount  = (int)($product['reviews'] ?? 0);
$stock        = (int)($product['stock'] ?? 0);
$isPreorder   = ($product['type'] === 'preorder');
$isSoldOut    = ($stock === 0 && !$isPreorder);
$preorderDate = !empty($product['preorder_date']) ? $product['preorder_date'] : null;
$description  = $product['description_mn'] ?: ($product['description'] ?? '');
$catName      = $product['category_name'] ?: $product['category_name_en'] ?? '';
$catSlug      = $product['category_slug'] ?? '';

// Star rendering helper — inline for this file
function renderStars(float $rating): string {
    $stars = '';
    $starPath = 'M14 5.4091L8.913 5.07466L6.99721 0.261719L5.08143 5.07466L0 5.4091L3.89741 8.7184L2.61849 13.7384L6.99721 10.9707L11.376 13.7384L10.097 8.7184L14 5.4091Z';
    for ($i = 1; $i <= 5; $i++) {
        $fill = ($i <= round($rating)) ? '#EF9122' : '#D1D5DB';
        $stars .= '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">'
            . '<path d="' . $starPath . '" fill="' . $fill . '"/></svg>';
    }
    return $stars;
}

// ── Page meta ──────────────────────────────────────────────────────────────
$page_title       = htmlspecialchars($productName) . ' — ' . s('site_name', 'Runners World');
$page_description = mb_strimwidth(strip_tags($description), 0, 160, '...');

// Build variant JSON for JS
$variantsJson = json_encode(array_map(fn($v) => [
    'id'       => (int)$v['variant_id'],
    'color_id' => (int)$v['color_id'],
    'size_id'  => (int)$v['size_id'],
    'price'    => !empty($v['price_override']) ? (float)$v['price_override'] : $price,
    'stock'    => (int)$v['stock'],
    'sku'      => $v['sku'] ?? '',
], $variants));

ob_start();
?>
<script>
const PRODUCT_ID = <?= (int)$product['id'] ?>;
const PRODUCT_VARIANTS = <?= $variantsJson ?>;
let selectedColorId = 0;
let selectedSizeId  = 0;

function getMatchingVariant() {
    if (!PRODUCT_VARIANTS.length) return null;
    return PRODUCT_VARIANTS.find(v =>
        (selectedColorId === 0 || v.color_id === selectedColorId) &&
        (selectedSizeId  === 0 || v.size_id  === selectedSizeId)
    ) || null;
}

// Color swatch click
document.querySelectorAll('.color-btn[data-color-id]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.color-btn[data-color-id]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedColorId = parseInt(this.dataset.colorId);
        document.querySelector('.variant-selected')?.setAttribute('data-variant-id', getMatchingVariant()?.id || 0);
        updateVariantStock();
    });
});

// Size btn click
document.querySelectorAll('.size-btn[data-size-id]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.size-btn[data-size-id]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedSizeId = parseInt(this.dataset.sizeId);
        document.querySelector('.variant-selected')?.setAttribute('data-variant-id', getMatchingVariant()?.id || 0);
        updateVariantStock();
    });
});

function updateVariantStock() {
    const v = getMatchingVariant();
    const atcBtn = document.getElementById('btn-product-atc');
    const buyBtn = document.getElementById('btn-product-buy');
    if (v && v.stock === 0) {
        atcBtn && (atcBtn.disabled = true);
        buyBtn && (buyBtn.style.pointerEvents = 'none');
    } else {
        atcBtn && (atcBtn.disabled = false);
        buyBtn && (buyBtn.style.pointerEvents = '');
    }
}

// Quantity buttons
document.querySelector('.btn-decrease')?.addEventListener('click', function() {
    const inp = document.querySelector('.quantity-product');
    if (inp) { const v = Math.max(1, parseInt(inp.value || 1) - 1); inp.value = v; }
});
document.querySelector('.btn-increase')?.addEventListener('click', function() {
    const inp = document.querySelector('.quantity-product');
    if (inp) { inp.value = parseInt(inp.value || 1) + 1; }
});

// Add to cart — product page (main + sticky buttons)
function doAddToCart() {
    const qty       = parseInt(document.querySelector('.quantity-product')?.value || 1);
    const variantId = getMatchingVariant()?.id || 0;
    fetch(BASE_URL + 'cart-action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'add', product_id: PRODUCT_ID, variant_id: variantId, qty: qty})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            updateCartBadge(d.count);
            refreshMiniCart().then(() => {
                const el = document.getElementById('shoppingCart');
                if (el) new bootstrap.Offcanvas(el).show();
            });
        }
    });
}
document.getElementById('btn-product-atc')?.addEventListener('click', doAddToCart);
document.getElementById('btn-sticky-atc')?.addEventListener('click', doAddToCart);
</script>
<?php
$extra_scripts = ob_get_clean();

require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title / Breadcrumb -->
        <section class="s-page-title style-2">
            <div class="container">
                <div class="content">
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('shop.php') ?>" class="h6 link">Дэлгүүр</a></li>
                        <?php if ($catName): ?>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('shop.php?category=' . urlencode($catSlug)) ?>" class="h6 link"><?= htmlspecialchars($catName) ?></a></li>
                        <?php endif; ?>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li>
                            <h6 class="current-page fw-normal"><?= htmlspecialchars($productName) ?></h6>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- Product Main -->
        <section class="flat-single-product flat-spacing-3">
            <div class="tf-main-product section-image-zoom">
                <div class="container">
                    <div class="row">

                        <!-- Product Images -->
                        <div class="col-md-6">
                            <div class="tf-product-media-wrap sticky-top">
                                <?php if (count($galleryImages) > 1): ?>
                                <div class="product-thumbs-slider">
                                    <!-- Vertical thumbs swiper -->
                                    <div dir="ltr"
                                         class="swiper tf-product-media-thumbs other-image-zoom"
                                         data-direction="vertical"
                                         data-preview="4.7">
                                        <div class="swiper-wrapper stagger-wrap">
                                            <?php foreach ($galleryImages as $idx => $imgUrl): ?>
                                            <div class="swiper-slide stagger-item">
                                                <div class="item">
                                                    <img class="lazyload"
                                                         src="<?= htmlspecialchars($imgUrl) ?>"
                                                         data-src="<?= htmlspecialchars($imgUrl) ?>"
                                                         alt="<?= htmlspecialchars($productName) ?> <?= $idx + 1 ?>">
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <!-- Main swiper -->
                                    <div class="flat-wrap-media-product">
                                        <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started">
                                            <div class="swiper-wrapper">
                                                <?php foreach ($galleryImages as $idx => $imgUrl): ?>
                                                <div class="swiper-slide">
                                                    <a href="<?= htmlspecialchars($imgUrl) ?>"
                                                       target="_blank"
                                                       class="item"
                                                       data-pswp-width="860px"
                                                       data-pswp-height="1146px">
                                                        <img class="tf-image-zoom lazyload"
                                                             data-zoom="<?= htmlspecialchars($imgUrl) ?>"
                                                             data-src="<?= htmlspecialchars($imgUrl) ?>"
                                                             src="<?= htmlspecialchars($imgUrl) ?>"
                                                             alt="<?= htmlspecialchars($productName) ?> <?= $idx + 1 ?>">
                                                    </a>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <!-- Single image — no thumbs swiper -->
                                <div class="flat-wrap-media-product">
                                    <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started">
                                        <div class="swiper-wrapper">
                                            <div class="swiper-slide">
                                                <a href="<?= htmlspecialchars($galleryImages[0]) ?>"
                                                   target="_blank"
                                                   class="item"
                                                   data-pswp-width="860px"
                                                   data-pswp-height="1146px">
                                                    <img class="tf-image-zoom lazyload"
                                                         data-zoom="<?= htmlspecialchars($galleryImages[0]) ?>"
                                                         data-src="<?= htmlspecialchars($galleryImages[0]) ?>"
                                                         src="<?= htmlspecialchars($galleryImages[0]) ?>"
                                                         alt="<?= htmlspecialchars($productName) ?>">
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- /Product Images -->

                        <!-- Product Info -->
                        <div class="col-md-6">
                            <div class="tf-product-info-wrap position-relative">
                                <div class="tf-zoom-main sticky-top"></div>
                                <div class="tf-product-info-list other-image-zoom">

                                    <!-- Name -->
                                    <h2 class="product-info-name"><?= htmlspecialchars($productName) ?></h2>

                                    <!-- Rating -->
                                    <div class="product-info-meta">
                                        <div class="rating">
                                            <div class="d-flex gap-4">
                                                <?= renderStars($rating) ?>
                                            </div>
                                            <div class="reviews text-main">(<?= number_format($reviewCount) ?> үнэлгээ)</div>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div class="tf-product-heading">
                                        <div class="product-info-price price-wrap">
                                            <span class="price-new price-on-sale h2 fw-4"><?= formatPrice($price) ?></span>
                                            <?php if ($origPrice && $origPrice > $price): ?>
                                            <span class="price-old compare-at-price h6"><?= formatPrice($origPrice) ?></span>
                                            <p class="badges-on-sale h6 fw-semibold">
                                                <span class="number-sale" data-person-sale="<?= $discount ?>">-<?= $discount ?>%</span>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Preorder / sold out badges -->
                                    <?php if ($isPreorder): ?>
                                    <div class="tf-product-info-liveview mb-2">
                                        <span class="product-badge_item h6" style="background:#f97316;color:#fff;padding:4px 10px;border-radius:4px;">
                                            Урьдчилсан захиалга
                                        </span>
                                        <?php if ($preorderDate): ?>
                                        <p class="h6 text-main ms-2">Хүлээлгэж өгөх огноо: <strong><?= htmlspecialchars($preorderDate) ?></strong></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif ($isSoldOut): ?>
                                    <div class="tf-product-info-liveview mb-2">
                                        <span class="product-badge_item h6 sold-out" style="padding:4px 10px;border-radius:4px;">
                                            Дууссан
                                        </span>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Variants -->
                                    <?php if (!empty($variants)): ?>
                                    <div class="tf-product-variant variant-selected" data-variant-id="0">

                                        <?php if (!empty($colors)): ?>
                                        <!-- Color swatches -->
                                        <div class="variant-picker-item variant-color">
                                            <div class="variant-picker-label">
                                                <div class="h4 fw-semibold">
                                                    Өнгө
                                                    <span class="variant-picker-label-value value-currentColor"></span>
                                                </div>
                                            </div>
                                            <div class="variant-picker-values">
                                                <?php $firstColor = true; foreach ($colors as $colorId => $color): ?>
                                                <div class="hover-tooltip tooltip-bot color-btn<?= $firstColor ? ' active' : '' ?>"
                                                     data-color-id="<?= (int)$colorId ?>"
                                                     style="cursor:pointer;">
                                                    <span class="check-color"
                                                          style="background:<?= htmlspecialchars($color['hex'] ?: '#ccc') ?>;display:inline-block;width:24px;height:24px;border-radius:50%;border:2px solid #eee;"></span>
                                                    <span class="tooltip"><?= htmlspecialchars($color['name'] ?? '') ?></span>
                                                </div>
                                                <?php $firstColor = false; endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($sizes)): ?>
                                        <!-- Size buttons -->
                                        <div class="variant-picker-item variant-size mt-3">
                                            <div class="variant-picker-label">
                                                <div class="h4 fw-semibold">
                                                    Хэмжээ
                                                    <span class="variant-picker-label-value value-currentSize"></span>
                                                </div>
                                            </div>
                                            <div class="variant-picker-values">
                                                <?php $firstSize = true; foreach ($sizes as $sizeId => $sizeName): ?>
                                                <span class="size-btn<?= $firstSize ? ' active' : '' ?>"
                                                      data-size-id="<?= (int)$sizeId ?>"
                                                      style="cursor:pointer;"><?= htmlspecialchars($sizeName) ?></span>
                                                <?php $firstSize = false; endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                    </div><!-- /.variant-selected -->
                                    <?php endif; ?>

                                    <!-- Quantity + ATC -->
                                    <div class="tf-product-total-quantity">
                                        <div class="group-btn">
                                            <div class="wg-quantity">
                                                <button class="btn-quantity btn-decrease" type="button">
                                                    <i class="icon icon-minus"></i>
                                                </button>
                                                <input class="quantity-product" type="text" name="number" value="1" min="1">
                                                <button class="btn-quantity btn-increase" type="button">
                                                    <i class="icon icon-plus"></i>
                                                </button>
                                            </div>
                                            <button id="btn-product-atc"
                                                    type="button"
                                                    class="tf-btn animate-btn"
                                                    data-product-id="<?= (int)$product['id'] ?>"
                                                    <?= $isSoldOut ? 'disabled' : '' ?>>
                                                Сагсанд нэмэх
                                                <i class="icon icon-shopping-cart-simple"></i>
                                            </button>
                                        </div>
                                        <a href="<?= url('checkout.php') ?>"
                                           id="btn-product-buy"
                                           class="tf-btn btn-primary w-100 mt-2<?= $isSoldOut ? '' : '' ?>"
                                           <?= $isSoldOut ? 'style="pointer-events:none;opacity:.6;"' : '' ?>>
                                            Одоо худалдаж авах
                                        </a>
                                    </div>

                                    <!-- Delivery info -->
                                    <div class="tf-product-delivery-return mt-3">
                                        <div class="product-delivery">
                                            <div class="icon icon-clock-cd"></div>
                                            <p class="h6">
                                                Хүргэлтийн хугацааны тооцоолол:
                                                <span class="fw-7 text-black">7–14 хоног</span>
                                                <?php if (!$product['hide_cargo_fee']): ?>
                                                (карго нэмэгдэнэ)
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="product-delivery return mt-2">
                                            <div class="icon icon-compare"></div>
                                            <p class="h6">
                                                Буцаалт <span class="fw-7 text-black">7 хоног</span> дотор боломжтой.
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Trust seal -->
                                    <div class="tf-product-trust-seal mt-3">
                                        <p class="h6 text-seal">Аюулгүй төлбөр:</p>
                                        <ul class="list-card">
                                            <li class="card-item"><img src="<?= assetUrl('images/payment/visa.png') ?>" alt="Visa"></li>
                                            <li class="card-item"><img src="<?= assetUrl('images/payment/master-card.png') ?>" alt="Mastercard"></li>
                                            <li class="card-item"><img src="<?= assetUrl('images/payment/amex.png') ?>" alt="Amex"></li>
                                            <li class="card-item"><img src="<?= assetUrl('images/payment/paypal.png') ?>" alt="PayPal"></li>
                                        </ul>
                                    </div>

                                    <!-- Category / SKU -->
                                    <ul class="tf-product-cate-sku mt-3">
                                        <?php if ($catName): ?>
                                        <li class="item-cate-sku h6">
                                            <span class="label fw-6 text-black">Ангилал:</span>
                                            <a href="<?= url('shop.php?category=' . urlencode($catSlug)) ?>"
                                               class="value link text-main-2">
                                                <?= htmlspecialchars($catName) ?>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (!empty($product['type'])): ?>
                                        <li class="item-cate-sku h6">
                                            <span class="label fw-6 text-black">Төрөл:</span>
                                            <span class="value text-main-2">
                                                <?= $product['type'] === 'preorder' ? 'Урьдчилсан захиалга' : 'Бэлэн бараа' ?>
                                            </span>
                                        </li>
                                        <?php endif; ?>
                                    </ul>

                                </div>
                            </div>
                        </div>
                        <!-- /Product Info -->

                    </div><!-- /.row -->
                </div><!-- /.container -->
            </div>

            <!-- Sticky ATC bar -->
            <div class="tf-sticky-btn-atc">
                <div class="container">
                    <div class="tf-height-observer w-100 d-flex align-items-center">
                        <div class="tf-sticky-atc-product d-flex align-items-center">
                            <div class="tf-mini-cart-item align-items-start">
                                <div class="tf-mini-cart-image">
                                    <img class="lazyload"
                                         src="<?= htmlspecialchars($galleryImages[0]) ?>"
                                         data-src="<?= htmlspecialchars($galleryImages[0]) ?>"
                                         alt="<?= htmlspecialchars($productName) ?>">
                                </div>
                                <div class="tf-mini-cart-info">
                                    <h6 class="title">
                                        <span class="text-line-clamp-1"><?= htmlspecialchars($productName) ?></span>
                                    </h6>
                                    <div class="h6 fw-semibold"><?= formatPrice($price) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="tf-sticky-atc-infos">
                            <div class="tf-sticky-atc-btns">
                                <button type="button" id="btn-sticky-atc"
                                        class="tf-btn animate-btn"
                                        data-product-id="<?= (int)$product['id'] ?>"
                                        <?= $isSoldOut ? 'disabled' : '' ?>>
                                    Сагсанд нэмэх
                                    <i class="icon icon-shopping-cart-simple"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </section>
        <!-- /Product Main -->

        <!-- Description / Details / Reviews tabs -->
        <section class="flat-spacing">
            <div class="container">
                <div class="flat-animate-tab tab-style-1">
                    <ul class="menu-tab menu-tab-1" role="tablist">
                        <li class="nav-tab-item" role="presentation">
                            <a href="#tab-descriptions" class="tab-link active" data-bs-toggle="tab">Тайлбар</a>
                        </li>
                        <li class="nav-tab-item" role="presentation">
                            <a href="#tab-details" class="tab-link" data-bs-toggle="tab">Нэмэлт мэдээлэл</a>
                        </li>
                        <li class="nav-tab-item" role="presentation">
                            <a href="#tab-reviews" class="tab-link" data-bs-toggle="tab">Үнэлгээ</a>
                        </li>
                    </ul>
                    <div class="tab-content">

                        <!-- Description -->
                        <div class="tab-pane wd-product-descriptions active show" id="tab-descriptions" role="tabpanel">
                            <div class="tab-descriptions">
                                <?php if ($description): ?>
                                <div class="h6 desc"><?= nl2br(htmlspecialchars($description)) ?></div>
                                <?php else: ?>
                                <p class="h6 text-main">Тайлбар байхгүй байна.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="tab-pane wd-product-descriptions" id="tab-details" role="tabpanel">
                            <div class="tab-descriptions">
                                <div class="list-infor tf-grid-layout md-col-2 xl-col-3">
                                    <div class="infor-item">
                                        <div class="h4 heading">Бүтээгдэхүүний мэдээлэл</div>
                                        <ul>
                                            <?php if (!empty($product['weight_kg'])): ?>
                                            <li>
                                                <h6 class="fw-6 text-black title">Жин:</h6>
                                                <div class="h6"><?= htmlspecialchars((string)$product['weight_kg']) ?> кг</div>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (!empty($product['type'])): ?>
                                            <li>
                                                <h6 class="fw-6 text-black title">Төрөл:</h6>
                                                <div class="h6"><?= $product['type'] === 'preorder' ? 'Урьдчилсан захиалга' : 'Бэлэн бараа' ?></div>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <h6 class="fw-6 text-black title">Нөөц:</h6>
                                                <div class="h6">
                                                    <?php if ($isSoldOut): ?>
                                                        <span class="text-danger">Дууссан</span>
                                                    <?php elseif ($isPreorder): ?>
                                                        <span style="color:#f97316;">Урьдчилсан захиалга</span>
                                                    <?php else: ?>
                                                        <span class="text-success">Бэлэн (<?= $stock ?> ширхэг)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                            <?php if ($catName): ?>
                                            <li>
                                                <h6 class="fw-6 text-black title">Ангилал:</h6>
                                                <div class="h6"><?= htmlspecialchars($catName) ?></div>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php if (!empty($product['hide_cargo_fee'])): ?>
                                    <div class="infor-item">
                                        <div class="h4 heading">Хүргэлт</div>
                                        <ul>
                                            <li>
                                                <h6 class="fw-6 text-black title">Карго:</h6>
                                                <div class="h6">Үнэгүй</div>
                                            </li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Reviews -->
                        <div class="tab-pane wd-product-descriptions" id="tab-reviews" role="tabpanel">
                            <div class="tab-reviews write-cancel-review-wrap">
                                <div class="tab-reviews-heading">
                                    <div class="top">
                                        <div class="text-center">
                                            <div class="number fw-6"><?= number_format($rating, 1) ?> <span>/5</span></div>
                                            <div class="list-star d-flex justify-content-center gap-4">
                                                <?= renderStars($rating) ?>
                                            </div>
                                            <p class="quantity-reviews"><?= number_format($reviewCount) ?> үнэлгээнд тулгуурласан</p>
                                        </div>
                                        <div class="rating-score">
                                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                            <div class="item">
                                                <div class="number-1"><?= $star ?></div>
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M14 5.4091L8.913 5.07466L6.99721 0.261719L5.08143 5.07466L0 5.4091L3.89741 8.7184L2.61849 13.7384L6.99721 10.9707L11.376 13.7384L10.097 8.7184L14 5.4091Z" fill="#EF9122"/>
                                                </svg>
                                                <div class="line-bg">
                                                    <?php $barW = ($star <= round($rating)) ? min(100, ($star / 5) * 100 + 20) : max(0, ($star / 5) * 100 - 20); ?>
                                                    <div style="width:<?= $barW ?>%;"></div>
                                                </div>
                                                <div class="number-2"><?= $star ?></div>
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="reply-comment cancel-review-wrap mt-3">
                                    <p class="h6 text-main text-center py-4">
                                        Одоогоор үнэлгээ байхгүй байна.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.tab-content -->
                </div>
            </div>
        </section>
        <!-- /Description section -->

        <?php if (!empty($related)): ?>
        <!-- Related Products -->
        <section class="flat-spacing-3">
            <div class="container">
                <h2 class="sect-title text-center">Төстэй бүтээгдэхүүн</h2>
                <div dir="ltr"
                     class="swiper tf-swiper wrap-sw-over"
                     data-preview="4"
                     data-tablet="3"
                     data-mobile-sm="2"
                     data-mobile="2"
                     data-space-lg="48"
                     data-space-md="30"
                     data-space="12"
                     data-pagination="2"
                     data-pagination-sm="2"
                     data-pagination-md="3"
                     data-pagination-lg="4">
                    <div class="swiper-wrapper">
                        <?php foreach ($related as $p): ?>
                        <div class="swiper-slide">
                            <?php require __DIR__ . '/includes/product-card.php'; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sw-dots style-2 sw-pagination-product justify-content-center mt-4"></div>
                </div>
            </div>
        </section>
        <!-- /Related Products -->
        <?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
