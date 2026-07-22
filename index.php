<?php
require_once __DIR__ . '/includes/config.php';

$db = getDB();
$a  = assetUrl(); // base asset URL

// ── DB Queries ─────────────────────────────────────────────────────────────
// Tab 1: Newest (trending)
$trending = $db->query("
    SELECT p.id, p.slug, p.name, p.name_mn, p.type, p.price, p.original_price, p.image, p.stock,
           c.name_mn AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.show_in_store = 1
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

// Tab 2: Best sellers (most reviews)
$bestsellers = $db->query("
    SELECT p.id, p.slug, p.name, p.name_mn, p.type, p.price, p.original_price, p.image, p.stock,
           c.name_mn AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.show_in_store = 1
    ORDER BY p.reviews DESC, p.rating DESC LIMIT 8
")->fetchAll();

// Tab 3: On sale (discounted)
$onsale = $db->query("
    SELECT p.id, p.slug, p.name, p.name_mn, p.type, p.price, p.original_price, p.image, p.stock,
           c.name_mn AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.show_in_store = 1 AND p.original_price IS NOT NULL AND p.original_price > p.price
    ORDER BY (p.original_price - p.price) DESC LIMIT 8
")->fetchAll();

// Shops grid ("trust section")
$homeShops = [];
try {
    $homeShops = $db->query("SELECT slug, name, name_mn, description_mn, color FROM shops WHERE is_active = 1 ORDER BY sort_order, name_mn, name")->fetchAll();
} catch (Throwable $e) {}

$page_title       = s('site_name', 'Runners World');
$page_description = s('site_description', 'Солонгосын шилдэг дэлгүүрүүдээс шууд авчирсан бүтээгдэхүүн');

// Sliders from DB; fall back to static slides if table empty or missing
$sliders = [];
try {
    $sliders = $db->query("SELECT * FROM sliders WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Throwable $e) {}

require_once __DIR__ . '/includes/header.php';
?>

        <!-- Banner Slider -->
        <div class="tf-slideshow type-abs tf-btn-swiper-main hover-sw-nav">
            <div dir="ltr" class="swiper tf-swiper sw-slide-show slider_effect_fade" data-auto="true" data-loop="true" data-effect="fade" data-delay="3000">
                <div class="swiper-wrapper">
                    <?php if (!empty($sliders)): ?>
                    <?php foreach ($sliders as $sl):
                        $slImg = fixImageUrl($sl['image']);
                        $textClass = $sl['text_dark'] ? 'text-black' : 'text-white';
                    ?>
                    <div class="swiper-slide">
                        <div class="slider-wrap">
                            <div class="sld_image">
                                <img src="<?= htmlspecialchars($slImg) ?>" data-src="<?= htmlspecialchars($slImg) ?>" alt="<?= htmlspecialchars($sl['title_mn'] ?? 'Slider') ?>" class="lazyload">
                            </div>
                            <?php if ($sl['title_mn'] || $sl['subtitle_mn'] || $sl['btn_text']): ?>
                            <div class="sld_content">
                                <div class="container">
                                    <div class="content-sld_wrap">
                                        <?php if ($sl['title_mn']): ?>
                                        <h1 class="title_sld text-display fade-item fade-item-1 <?= $textClass ?>"><?= htmlspecialchars($sl['title_mn']) ?></h1>
                                        <?php endif; ?>
                                        <?php if ($sl['subtitle_mn']): ?>
                                        <p class="sub-text_sld h5 <?= $textClass ?> fade-item fade-item-2"><?= htmlspecialchars($sl['subtitle_mn']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($sl['btn_text']): ?>
                                        <div class="fade-item fade-item-3">
                                            <a href="<?= htmlspecialchars($sl['btn_url'] ?: url('shop.php')) ?>" class="tf-btn animate-btn fw-semibold">
                                                <?= htmlspecialchars($sl['btn_text']) ?> <i class="icon icon-arrow-right"></i>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <!-- Fallback static slides when no DB slides exist -->
                    <div class="swiper-slide">
                        <div class="slider-wrap">
                            <div class="sld_image">
                                <img src="<?= assetUrl('images/slider/slider-1.jpg') ?>" data-src="<?= assetUrl('images/slider/slider-1.jpg') ?>" alt="Slider" class="lazyload">
                            </div>
                            <div class="sld_content">
                                <div class="container">
                                    <div class="content-sld_wrap">
                                        <h1 class="title_sld text-display fade-item fade-item-1"><?= htmlspecialchars(s('hero_title', 'Шинэ цуглуулга')) ?></h1>
                                        <p class="sub-text_sld h5 text-black fade-item fade-item-2"><?= htmlspecialchars(s('hero_subtitle', 'Солонгосоос шууд авчирсан жинхэнэ бүтээгдэхүүн')) ?></p>
                                        <div class="fade-item fade-item-3">
                                            <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn fw-semibold">
                                                <?= htmlspecialchars(s('hero_btn1_text', 'Дэлгүүр үзэх')) ?> <i class="icon icon-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="sw-dot-default tf-sw-pagination"></div>
            </div>
            <div class="tf-sw-nav nav-prev-swiper"><i class="icon icon-caret-left"></i></div>
            <div class="tf-sw-nav nav-next-swiper"><i class="icon icon-caret-right"></i></div>
        </div>
        <!-- /Banner Slider -->

        <!-- Collection -->
        <?php $cats = getCategories(); ?>
        <div class="s-collection">
            <div dir="ltr" class="swiper tf-swiper" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1" data-pagination="1"
                data-space-lg="24" data-space-md="15" data-space="10" data-pagination-sm="1" data-pagination-md="2" data-pagination-lg="4">
                <div class="swiper-wrapper">
                    <?php foreach ($cats as $cat):
                        $label  = htmlspecialchars($cat['name_mn'] ?: $cat['name']);
                        $href   = url('shop?category=' . urlencode($cat['slug']));
                        $imgSrc = $cat['image'] ? fixImageUrl($cat['image']) : assetUrl('images/collections/cls-1.jpg');
                    ?>
                    <div class="swiper-slide">
                        <div class="wg-cls-2 d-flex hover-img">
                            <a href="<?= $href ?>" class="image img-style">
                                <img class="lazyload" src="<?= $imgSrc ?>" data-src="<?= $imgSrc ?>" alt="<?= $label ?>">
                            </a>
                            <div class="cls-content_wrap b-16">
                                <div class="cls-content">
                                    <a href="<?= $href ?>" class="tag_cls h3 link"><?= $label ?></a>
                                    <span class="br-line type-vertical"></span>
                                    <a href="<?= $href ?>" class="tf-btn-line text-nowrap"> Үзэх </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="sw-dot-default tf-sw-pagination"></div>
            </div>
        </div>
        <!-- /Collection -->

        <?php if (!empty($homeShops)): ?>
        <!-- Shops Grid -->
        <div class="flat-spacing pb-0">
            <div class="container">
                <div class="text-center mb-24">
                    <h2 class="h1 title mb-12"><?= htmlspecialchars(s('shops_title', 'Солонгосын шилдэг дэлгүүрүүдээс шууд авчирна')) ?></h2>
                    <p class="h6 text-main" style="max-width:640px;margin:0 auto;">
                        <?= htmlspecialchars(s('shops_description', 'Бид Солонгосын хамгийн том, итгэлтэй дэлгүүрүүдээс жинхэнэ бүтээгдэхүүнийг шууд авчирч, Улаанбаатар хотод танд хүргэнэ')) ?>
                    </p>
                </div>
                <div class="row row-cols-3 row-cols-sm-4 row-cols-md-6 row-cols-lg-8 g-3 home-brand-grid">
                    <?php foreach ($homeShops as $sh):
                        $shLabel = $sh['name_mn'] ?: $sh['name'];
                    ?>
                    <div class="col">
                        <a href="<?= url('shop?shop=' . urlencode($sh['slug'])) ?>" class="home-brand-tile">
                            <span class="home-brand-tile-label"><?= htmlspecialchars($shLabel) ?></span>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <!-- /Shops Grid -->
        <?php endif; ?>

        <!-- New Arrivals -->
        <div class="flat-spacing flat-animate-tab">
            <div class="container">
                <div class="sect-title wow fadeInUp">
                    <div class="h1 title text-center mb-24">Манай бараанууд</div>
                    <ul class="tab-product_list" role="tablist">
                        <li class="nav-tab-item" role="presentation">
                            <a href="#tab-new" data-bs-toggle="tab" class="tf-btn-line tf-btn-tab active">Шинэ бараа</a>
                        </li>
                        <li class="nav-tab-item" role="presentation">
                            <a href="#tab-best" data-bs-toggle="tab" class="tf-btn-line tf-btn-tab">Бестселлер</a>
                        </li>
                        <li class="nav-tab-item" role="presentation">
                            <a href="#tab-sale" data-bs-toggle="tab" class="tf-btn-line tf-btn-tab">Хямдралтай</a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <!-- Tab: New -->
                    <div class="tab-pane active show" id="tab-new" role="tabpanel">
                        <div dir="ltr" class="swiper tf-swiper wrap-sw-over wow fadeInUp"
                            data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="2"
                            data-space-lg="48" data-space-md="30" data-space="12"
                            data-pagination="2" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4" data-grid="2">
                            <div class="swiper-wrapper">
                                <?php if (empty($trending)): ?>
                                <div class="swiper-slide"><p class="text-main text-center py-4">Бараа байхгүй байна</p></div>
                                <?php else: foreach ($trending as $p): ?>
                                <div class="swiper-slide"><?php include __DIR__ . '/includes/product-card.php'; ?></div>
                                <?php endforeach; endif; ?>
                            </div>
                            <div class="sw-dot-default tf-sw-pagination"></div>
                        </div>
                    </div>
                    <!-- Tab: Best Sellers -->
                    <div class="tab-pane" id="tab-best" role="tabpanel">
                        <div dir="ltr" class="swiper tf-swiper wrap-sw-over wow fadeInUp"
                            data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="2"
                            data-space-lg="48" data-space-md="30" data-space="12"
                            data-pagination="2" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4" data-grid="2">
                            <div class="swiper-wrapper">
                                <?php if (empty($bestsellers)): ?>
                                <div class="swiper-slide"><p class="text-main text-center py-4">Бараа байхгүй байна</p></div>
                                <?php else: foreach ($bestsellers as $p): ?>
                                <div class="swiper-slide"><?php include __DIR__ . '/includes/product-card.php'; ?></div>
                                <?php endforeach; endif; ?>
                            </div>
                            <div class="sw-dot-default tf-sw-pagination"></div>
                        </div>
                    </div>
                    <!-- Tab: On Sale -->
                    <div class="tab-pane" id="tab-sale" role="tabpanel">
                        <div dir="ltr" class="swiper tf-swiper wrap-sw-over wow fadeInUp"
                            data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="2"
                            data-space-lg="48" data-space-md="30" data-space="12"
                            data-pagination="2" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4" data-grid="2">
                            <div class="swiper-wrapper">
                                <?php if (empty($onsale)): ?>
                                <div class="swiper-slide"><p class="text-main text-center py-4">Хямдралтай бараа байхгүй байна</p></div>
                                <?php else: foreach ($onsale as $p): ?>
                                <div class="swiper-slide"><?php include __DIR__ . '/includes/product-card.php'; ?></div>
                                <?php endforeach; endif; ?>
                            </div>
                            <div class="sw-dot-default tf-sw-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /New Arrivals -->


<?php
$testimonials = [];
try {
    $testimonials = getDB()->query("SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 6")->fetchAll();
} catch(Throwable $e) {}
if (!empty($testimonials)):
?>
<section class="flat-spacing pb-0">
    <div class="container">
        <div class="h1 sect-title text-black fw-medium text-center wow fadeInUp">Үйлчлүүлэгчдийн сэтгэгдэл</div>
        <div dir="ltr" class="swiper tf-swiper" data-preview="3" data-tablet="2" data-mobile-sm="1" data-mobile="1"
            data-space-lg="48" data-space-md="24" data-space="12"
            data-pagination="1" data-pagination-sm="1" data-pagination-md="2" data-pagination-lg="3">
            <div class="swiper-wrapper">
                <?php foreach ($testimonials as $t): ?>
                <div class="swiper-slide">
                    <div class="testimonial-V01">
                        <div>
                            <h4 class="tes_title"><?= htmlspecialchars($t['title'] ?? '') ?></h4>
                            <p class="tes_text h4">"<?= htmlspecialchars($t['body']) ?>"</p>
                            <div class="tes_author">
                                <p class="author-name h5"><?= htmlspecialchars($t['customer_name']) ?></p>
                                <i class="author-verified icon-check-circle"></i>
                            </div>
                            <div class="rate_wrap">
                                <?php for($s=1;$s<=5;$s++): ?>
                                <i class="icon-star <?= $s<=(int)$t['rating'] ? 'text-star' : 'text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sw-dot-default tf-sw-pagination"></div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
$blogPosts = [];
try {
    $blogPosts = getDB()->query("SELECT * FROM blog_posts WHERE is_published=1 ORDER BY sort_order ASC, published_at DESC LIMIT 4")->fetchAll();
} catch(Throwable $e) {}
if (!empty($blogPosts)):
?>
<div class="flat-spacing">
    <div class="container">
        <div class="h1 sect-title text-black fw-medium text-center wow fadeInUp">Мэдээ мэдээлэл</div>
        <div dir="ltr" class="swiper tf-swiper" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1"
            data-space-lg="48" data-space-md="24" data-space="12"
            data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
            <div class="swiper-wrapper">
                <?php foreach ($blogPosts as $i => $b):
                    $bImg = $b['image'] ? fixImageUrl($b['image']) : assetUrl('images/blog/blog-1.jpg');
                    $bTitle = htmlspecialchars($b['title_mn'] ?: $b['title']);
                    $bDate = $b['published_at'] ? date('Y.m.d', strtotime($b['published_at'])) : '';
                ?>
                <div class="swiper-slide">
                    <div class="article-blog type-space-2 hover-img4 wow fadeInLeft" data-wow-delay="<?= $i * 0.1 ?>s">
                        <a href="<?= url('blog/' . htmlspecialchars($b['slug'])) ?>" class="entry_image img-style4">
                            <img src="<?= $bImg ?>" data-src="<?= $bImg ?>" alt="<?= $bTitle ?>" class="lazyload aspect-ratio-0">
                        </a>
                        <div class="entry_tag">
                            <span class="name-tag h6"><?= $bDate ?></span>
                        </div>
                        <div class="blog-content">
                            <a href="<?= url('blog/' . htmlspecialchars($b['slug'])) ?>" class="entry_name h4 link"><?= $bTitle ?></a>
                            <?php if ($b['excerpt_mn']): ?>
                            <p class="h6 text-main mt-1"><?= htmlspecialchars(mb_strimwidth($b['excerpt_mn'], 0, 80, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sw-dot-default tf-sw-pagination"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
