<?php
require_once __DIR__ . '/includes/config.php';

$db = getDB();
$a  = assetUrl(); // base ochaka asset URL

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

$page_title       = s('site_name', 'Runners World');
$page_description = s('site_description', 'Солонгосын шилдэг дэлгүүрүүдээс шууд авчирсан бүтээгдэхүүн');
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Banner Slider -->
        <div class="tf-slideshow type-abs tf-btn-swiper-main hover-sw-nav">
            <div dir="ltr" class="swiper tf-swiper sw-slide-show slider_effect_fade" data-auto="true" data-loop="true" data-effect="fade" data-delay="3000">
                <div class="swiper-wrapper">
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
                                                <?= htmlspecialchars(s('hero_btn1_text', 'Дэлгүүр үзэх')) ?>
                                                <i class="icon icon-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="slider-wrap">
                            <div class="sld_image">
                                <img src="<?= assetUrl('images/slider/slider-2.jpg') ?>" data-src="<?= assetUrl('images/slider/slider-2.jpg') ?>" alt="Slider" class="lazyload">
                            </div>
                            <div class="sld_content">
                                <div class="container">
                                    <div class="content-sld_wrap">
                                        <h1 class="title_sld text-display fade-item fade-item-1"><?= htmlspecialchars(s('hero_btn2_text', 'Урьдчилсан захиалга')) ?></h1>
                                        <p class="sub-text_sld h5 text-black fade-item fade-item-2"><?= htmlspecialchars(s('hero_description', 'Хамгийн сүүлийн үеийн бараа захиалаарай')) ?></p>
                                        <div class="fade-item fade-item-3">
                                            <a href="<?= url('shop.php?type=preorder') ?>" class="tf-btn animate-btn fw-semibold">
                                                Захиалах <i class="icon icon-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="slider-wrap">
                            <div class="sld_image">
                                <img src="<?= assetUrl('images/slider/slider-3.jpg') ?>" data-src="<?= assetUrl('images/slider/slider-3.jpg') ?>" alt="Slider" class="lazyload">
                            </div>
                            <div class="sld_content">
                                <div class="container">
                                    <div class="content-sld_wrap">
                                        <h1 class="title_sld text-display fade-item fade-item-1">Бэлэн бараа</h1>
                                        <p class="sub-text_sld h5 text-black fade-item fade-item-2">Шууд хүлээн авах боломжтой бараанууд</p>
                                        <div class="fade-item fade-item-3">
                                            <a href="<?= url('shop.php?type=ready') ?>" class="tf-btn animate-btn fw-semibold">
                                                <?= htmlspecialchars(s('hero_btn3_text', 'Харах')) ?> <i class="icon icon-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
            <div dir="ltr" class="swiper tf-swiper" data-preview="3" data-tablet="2" data-mobile-sm="2" data-mobile="1" data-pagination="1"
                data-space-lg="24" data-space-md="15" data-space="10" data-pagination-sm="1" data-pagination-md="2" data-pagination-lg="3">
                <div class="swiper-wrapper">
                    <?php foreach ($cats as $cat):
                        $label  = htmlspecialchars($cat['name_mn'] ?: $cat['name']);
                        $href   = url('category/' . htmlspecialchars($cat['slug']));
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

        <!-- Shop This Look (static) -->
        <div class="themesFlat">
            <div class="container-full">
                <div class="h1 sect-title text-black fw-medium text-center wow fadeInUp">Shop This Look</div>
                <div class="row">
                    <div class="col-xl-4">
                        <div class="box-image_V01 hover-img mb-xl-0 wow fadeInUp">
                            <a href="<?= url('shop.php') ?>" class="box-image_image img-style">
                                <img src="<?= assetUrl('images/section/box-image-1.jpg') ?>" data-src="<?= assetUrl('images/section/box-image-1.jpg') ?>" alt="Image" class="lazyload">
                            </a>
                            <div class="box-image_content">
                                <a href="<?= url('shop.php') ?>" class="title text-display fw-semibold text-white link">Lookbook</a>
                                <span class="sub-title h5 text-white">Манай бүтээгдэхүүнүүд</span>
                                <a href="<?= url('shop.php') ?>" class="tf-btn-line style-white"> ХАРАХ </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div dir="ltr" class="swiper tf-swiper wrap-sw-over wow fadeInUp" data-preview="3" data-tablet="3" data-mobile-sm="2"
                            data-mobile="2" data-space-lg="48" data-space-md="30" data-space="12" data-pagination="2" data-pagination-sm="2"
                            data-pagination-md="3" data-pagination-lg="3">
                            <div class="swiper-wrapper">
                                <?php
                                // Use on-sale or trending products for "Shop This Look"
                                $lookProducts = !empty($onsale) ? array_slice($onsale, 0, 4) : array_slice($trending, 0, 4);
                                foreach ($lookProducts as $p):
                                ?>
                                <div class="swiper-slide"><?php include __DIR__ . '/includes/product-card.php'; ?></div>
                                <?php endforeach; ?>
                                <?php if (empty($lookProducts)): ?>
                                <!-- fallback static items -->
                                <?php for ($i = 23; $i <= 26; $i++): ?>
                                <div class="swiper-slide">
                                    <div class="card-product">
                                        <div class="card-product_wrapper">
                                            <a href="<?= url('shop.php') ?>" class="product-img">
                                                <img class="lazyload img-product" src="<?= assetUrl("images/products/product-{$i}.jpg") ?>" data-src="<?= assetUrl("images/products/product-{$i}.jpg") ?>" alt="Product">
                                            </a>
                                        </div>
                                        <div class="card-product_info">
                                            <a href="<?= url('shop.php') ?>" class="name-product h4 link">Бүтээгдэхүүн</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; endif; ?>
                            </div>
                            <div class="sw-dot-default tf-sw-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Shop This Look -->

        <!-- Testimonial (static) -->
        <section class="flat-spacing pb-0">
            <div class="container">
                <div class="h1 sect-title text-black fw-medium text-center wow fadeInUp">Үйлчлүүлэгчдийн сэтгэгдэл</div>
                <div dir="ltr" class="swiper tf-swiper" data-preview="3" data-tablet="2" data-mobile-sm="1" data-mobile="1" data-space-lg="48"
                    data-space-md="24" data-space="12" data-pagination="1" data-pagination-sm="1" data-pagination-md="2" data-pagination-lg="3">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <div class="testimonial-V01 wow fadeInLeft">
                                <div>
                                    <h4 class="tes_title">Маш сайн чанар</h4>
                                    <p class="tes_text h4">"Бараа маш хурдан ирсэн, чанар нь гайхалтай. Солонгосоос авчирсан гэдэг нь мэдрэгдэж байна!"</p>
                                    <div class="tes_author"><p class="author-name h5">Б. Болормаа</p><i class="author-verified icon-check-circle"></i></div>
                                    <div class="rate_wrap"><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i></div>
                                </div>
                                <span class="br-line"></span>
                                <div class="tes_product">
                                    <div class="product-image"><img class="lazyload" src="<?= assetUrl('images/products/product-35.jpg') ?>" data-src="<?= assetUrl('images/products/product-35.jpg') ?>" alt="Product"></div>
                                    <div class="product-infor"><h5 class="prd_name"><a href="<?= url('shop.php') ?>" class="link">Манай бараа</a></h5></div>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="testimonial-V01 wow fadeInLeft" data-wow-delay="0.1s">
                                <div>
                                    <h4 class="tes_title">Хурдан хүргэлт</h4>
                                    <p class="tes_text h4">"Захиалсан өдрөөсөө 2 хоногт гэртээ хүлээн авлаа. Дахин захиалах нь дамжиггүй!"</p>
                                    <div class="tes_author"><p class="author-name h5">Д. Номин</p><i class="author-verified icon-check-circle"></i></div>
                                    <div class="rate_wrap"><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i></div>
                                </div>
                                <span class="br-line"></span>
                                <div class="tes_product">
                                    <div class="product-image"><img class="lazyload" src="<?= assetUrl('images/products/product-40.jpg') ?>" data-src="<?= assetUrl('images/products/product-40.jpg') ?>" alt="Product"></div>
                                    <div class="product-infor"><h5 class="prd_name"><a href="<?= url('shop.php') ?>" class="link">Манай бараа</a></h5></div>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="testimonial-V01 wow fadeInLeft" data-wow-delay="0.2s">
                                <div>
                                    <h4 class="tes_title">Жинхэнэ бараа</h4>
                                    <p class="tes_text h4">"Солонгосоос шууд авчирсан гэдэгт итгэлтэй. Үнэ, чанарын хувьд хаанаас ч илүү."</p>
                                    <div class="tes_author"><p class="author-name h5">Г. Анхбаяр</p><i class="author-verified icon-check-circle"></i></div>
                                    <div class="rate_wrap"><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i><i class="icon-star text-star"></i></div>
                                </div>
                                <span class="br-line"></span>
                                <div class="tes_product">
                                    <div class="product-image"><img class="lazyload" src="<?= assetUrl('images/products/product-13.jpg') ?>" data-src="<?= assetUrl('images/products/product-13.jpg') ?>" alt="Product"></div>
                                    <div class="product-infor"><h5 class="prd_name"><a href="<?= url('shop.php') ?>" class="link">Манай бараа</a></h5></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </section>
        <!-- /Testimonial -->

        <!-- Blog (static) -->
        <div class="flat-spacing">
            <div class="container">
                <div class="h1 sect-title text-black fw-medium text-center wow fadeInUp">Мэдээ мэдээлэл</div>
                <div dir="ltr" class="swiper tf-swiper" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1" data-space-lg="48"
                    data-space-md="24" data-space="12" data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                    <div class="swiper-wrapper">
                        <?php
                        $blogItems = [
                            ['img' => 'blog-1.jpg', 'title' => 'Шинэ бараа ирлээ — Солонгосоос шинэ цуглуулга'],
                            ['img' => 'blog-2.jpg', 'title' => 'Урьдчилсан захиалгын давуу тал юу вэ?'],
                            ['img' => 'blog-3.jpg', 'title' => 'Хэрхэн зөв хэмжээ сонгох вэ?'],
                            ['img' => 'blog-4.jpg', 'title' => 'Хамгийн их захиалагддаг 5 бараа'],
                        ];
                        foreach ($blogItems as $i => $b):
                        ?>
                        <div class="swiper-slide">
                            <div class="article-blog type-space-2 hover-img4 wow fadeInLeft" data-wow-delay="<?= $i * 0.1 ?>s">
                                <a href="<?= url('faq.php') ?>" class="entry_image img-style4">
                                    <img src="<?= assetUrl('images/blog/' . $b['img']) ?>" data-src="<?= assetUrl('images/blog/' . $b['img']) ?>" alt="Blog" class="lazyload aspect-ratio-0">
                                </a>
                                <div class="entry_tag">
                                    <a href="<?= url('faq.php') ?>" class="name-tag h6 link"><?= date('Y') ?></a>
                                </div>
                                <div class="blog-content">
                                    <a href="<?= url('faq.php') ?>" class="entry_name link h4"><?= htmlspecialchars($b['title']) ?></a>
                                    <a href="<?= url('faq.php') ?>" class="tf-btn-line"> Дэлгэрэнгүй </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </div>
        <!-- /Blog -->

        <!-- Gallery (static) -->
        <section class="flat-spacing pt-0 pb-xl-0">
            <div class="container">
                <div class="sect-title text-center wow fadeInUp">
                    <div class="h1 title mb-16"><?= htmlspecialchars(s('site_name', 'Runners World')) ?></div>
                    <?php $ig = s('social_instagram', ''); ?>
                    <h6><?= $ig ? '@' . htmlspecialchars(ltrim($ig, 'https://www.instagram.com/')) : 'Бидэнтэй холбоотой байгаарай' ?></h6>
                </div>
            </div>
            <div dir="ltr" class="swiper tf-swiper wow fadeInUp" data-preview="6" data-tablet="4" data-mobile-sm="3" data-mobile="2" data-space="0"
                data-pagination="2" data-pagination-sm="3" data-pagination-md="4" data-pagination-lg="6">
                <div class="swiper-wrapper">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="swiper-slide">
                        <div class="gallery-item hover-img hover-overlay">
                            <div class="image img-style">
                                <img class="lazyload" src="<?= assetUrl("images/gallery/gallery-{$i}.jpg") ?>" data-src="<?= assetUrl("images/gallery/gallery-{$i}.jpg") ?>" alt="Gallery">
                            </div>
                            <a href="<?= url('shop.php') ?>" class="box-icon hover-tooltip">
                                <span class="icon icon-instagram-logo"></span>
                                <span class="tooltip">Дэлгүүр үзэх</span>
                            </a>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="sw-dot-default tf-sw-pagination"></div>
            </div>
        </section>
        <!-- /Gallery -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
