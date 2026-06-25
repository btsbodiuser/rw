<?php
$_cartItems = $_SESSION['cart'] ?? [];
$_cartTotal = cartTotal();
$_cartCount = cartCount();
?>

        <!-- Footer -->
        <footer class="tf-footer style-4">
            <div class="container d-flex">
                <span class="br-line"></span>
            </div>
            <div class="footer-body">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-3 col-sm-6 mb_30 mb-xl-0">
                            <div class="footer-col-block">
                                <p class="footer-heading footer-heading-mobile">Холбоо барих</p>
                                <div class="tf-collapse-content">
                                    <ul class="footer-contact">
                                        <li>
                                            <i class="icon icon-map-pin"></i>
                                            <span class="br-line"></span>
                                            <span class="h6"><?= htmlspecialchars(s('contact_address', 'Улаанбаатар, Монгол')) ?></span>
                                        </li>
                                        <?php $phone = s('contact_phone', ''); if ($phone): ?>
                                        <li>
                                            <i class="icon icon-phone"></i>
                                            <span class="br-line"></span>
                                            <a href="tel:<?= htmlspecialchars($phone) ?>" class="h6 link"><?= htmlspecialchars($phone) ?></a>
                                        </li>
                                        <?php endif; ?>
                                        <?php $email = s('contact_email', ''); if ($email): ?>
                                        <li>
                                            <i class="icon icon-envelope-simple"></i>
                                            <span class="br-line"></span>
                                            <a href="mailto:<?= htmlspecialchars($email) ?>" class="h6 link"><?= htmlspecialchars($email) ?></a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                    <div class="social-wrap">
                                        <ul class="tf-social-icon">
                                            <?php $fb = s('social_facebook', ''); if ($fb): ?>
                                            <li><a href="<?= htmlspecialchars($fb) ?>" target="_blank" class="social-facebook"><span class="icon"><i class="icon-fb"></i></span></a></li>
                                            <?php endif; ?>
                                            <?php $ig = s('social_instagram', ''); if ($ig): ?>
                                            <li><a href="<?= htmlspecialchars($ig) ?>" target="_blank" class="social-instagram"><span class="icon"><i class="icon-instagram-logo"></i></span></a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-sm-6 mb_30 mb-xl-0">
                            <div class="footer-col-block footer-wrap-1 ms-xl-auto">
                                <p class="footer-heading footer-heading-mobile">Худалдаа</p>
                                <div class="tf-collapse-content">
                                    <ul class="footer-menu-list">
                                        <li><a href="<?= url('shop.php') ?>" class="link h6">Бүх бараа</a></li>
                                        <li><a href="<?= url('shop.php?type=ready') ?>" class="link h6">Бэлэн бараа</a></li>
                                        <li><a href="<?= url('shop.php?type=preorder') ?>" class="link h6">Урьдчилсан захиалга</a></li>
                                        <li><a href="<?= url('track-order.php') ?>" class="link h6">Захиалга хянах</a></li>
                                        <li><a href="<?= url('cart.php') ?>" class="link h6">Миний сагс</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb_30 mb-sm-0">
                            <div class="footer-col-block footer-wrap-2 mx-xl-auto">
                                <p class="footer-heading footer-heading-mobile">Мэдээлэл</p>
                                <div class="tf-collapse-content">
                                    <ul class="footer-menu-list">
                                        <li><a href="<?= url('contact.php') ?>" class="link h6">Холбоо барих</a></li>
                                        <li><a href="<?= url('faq.php') ?>" class="link h6">Асуулт & Хариулт</a></li>
                                        <li><a href="<?= url('faq.php') ?>" class="link h6">Хүргэлтийн нөхцөл</a></li>
                                        <li><a href="<?= url('faq.php') ?>" class="link h6">Буцаалт & Нөхөн олговор</a></li>
                                        <?php if (isLoggedIn()): ?>
                                        <li><a href="<?= url('account.php') ?>" class="link h6">Миний бүртгэл</a></li>
                                        <?php else: ?>
                                        <li><a href="<?= url('login.php') ?>" class="link h6">Нэвтрэх / Бүртгүүлэх</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-sm-6">
                            <div class="footer-col-block">
                                <p class="footer-heading footer-heading-mobile">Холбоотой байх</p>
                                <div class="tf-collapse-content">
                                    <div class="footer-newsletter">
                                        <p class="h6 caption">
                                            <?= htmlspecialchars(s('site_name', 'Runners World')) ?> — Солонгосын шилдэг дэлгүүрүүдээс жинхэнэ бүтээгдэхүүн, хурдан хүргэлттэй.
                                        </p>
                                        <?php $phone2 = s('contact_phone', ''); if ($phone2): ?>
                                        <a href="tel:<?= htmlspecialchars($phone2) ?>" class="tf-btn animate-btn type-small-2 mt-2">
                                            <i class="icon icon-phone"></i> <?= htmlspecialchars($phone2) ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="container">
                    <div class="inner-bottom">
                        <ul class="list-hor">
                            <li><a href="<?= url('faq.php') ?>" class="h6 link">Тусламж & FAQ</a></li>
                            <li class="br-line type-vertical"></li>
                            <li><a href="<?= url('contact.php') ?>" class="h6 link">Холбоо барих</a></li>
                        </ul>
                        <div class="list-hor flex-wrap">
                            <span class="h6">Төлбөр:</span>
                            <ul class="payment-method-list">
                                <li><img src="<?= assetUrl('images/payment/visa.png') ?>" alt="Visa"></li>
                                <li><img src="<?= assetUrl('images/payment/master-card.png') ?>" alt="Mastercard"></li>
                            </ul>
                        </div>
                        <div class="h6 text-main">&copy; <?= date('Y') ?> <?= htmlspecialchars(s('site_name', 'Runners World')) ?>. Бүх эрх хуулиар хамгаалагдсан.</div>
                    </div>
                </div>
            </div>
        </footer>
        <!-- /Footer -->
    </main><!-- /#wrapper -->

    <!-- Mobile Menu Offcanvas -->
    <div class="offcanvas offcanvas-start canvas-mb" id="mobileMenu">
        <span class="icon-close-popup" data-bs-dismiss="offcanvas"><i class="icon-close"></i></span>
        <div class="canvas-header">
            <p class="text-logo-mb fw-bold"><?= htmlspecialchars(s('site_name', 'Runners World')) ?></p>
            <?php if (isLoggedIn()): ?>
            <a href="<?= url('account.php') ?>" class="tf-btn type-small style-2">
                Миний бүртгэл <i class="icon icon-user"></i>
            </a>
            <?php else: ?>
            <a href="<?= url('login.php') ?>" class="tf-btn type-small style-2">
                Нэвтрэх <i class="icon icon-user"></i>
            </a>
            <?php endif; ?>
            <span class="br-line"></span>
        </div>
        <div class="canvas-body">
            <div class="mb-content-top">
                <ul class="nav-ul-mb">
                    <li class="nav-mb-item">
                        <a href="<?= url() ?>" class="nav-mb-link h5 fw-medium">Нүүр хуудас</a>
                    </li>
                    <li class="nav-mb-item">
                        <a href="<?= url('shop.php') ?>" class="nav-mb-link h5 fw-medium">Дэлгүүр</a>
                    </li>
                    <?php if (!empty($_categories)): ?>
                    <li class="nav-mb-item">
                        <span class="nav-mb-link h5 fw-medium" data-bs-toggle="collapse" data-bs-target="#mob-categories">
                            Ангилал <i class="icon icon-caret-down"></i>
                        </span>
                        <div class="collapse" id="mob-categories">
                            <ul class="sub-nav-mobile">
                                <?php foreach ($_categories as $cat): ?>
                                <li>
                                    <a href="<?= url('category/' . htmlspecialchars($cat['slug'])) ?>" class="sub-nav-link h6">
                                        <?= htmlspecialchars($cat['name_mn'] ?: $cat['name']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                    <li class="nav-mb-item">
                        <a href="<?= url('track-order.php') ?>" class="nav-mb-link h5 fw-medium">Захиалга хянах</a>
                    </li>
                    <li class="nav-mb-item">
                        <a href="<?= url('faq.php') ?>" class="nav-mb-link h5 fw-medium">Тусламж & FAQ</a>
                    </li>
                    <li class="nav-mb-item">
                        <a href="<?= url('contact.php') ?>" class="nav-mb-link h5 fw-medium">Холбоо барих</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- /Mobile Menu -->

    <!-- Bottom Toolbar (mobile) -->
    <div class="tf-toolbar-bottom">
        <div class="toolbar-item">
            <a href="<?= url('shop.php') ?>">
                <span class="toolbar-icon"><i class="icon icon-storefront"></i></span>
                <span class="toolbar-label">Дэлгүүр</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="#search" data-bs-toggle="modal">
                <span class="toolbar-icon"><i class="icon icon-magnifying-glass"></i></span>
                <span class="toolbar-label">Хайх</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="<?= isLoggedIn() ? url('account.php') : url('login.php') ?>">
                <span class="toolbar-icon"><i class="icon icon-user"></i></span>
                <span class="toolbar-label">Бүртгэл</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="#shoppingCart" data-bs-toggle="offcanvas">
                <span class="toolbar-icon">
                    <i class="icon icon-shopping-cart-simple"></i>
                    <span class="toolbar-count" id="cart-count-toolbar"><?= $_cartCount ?></span>
                </span>
                <span class="toolbar-label">Сагс</span>
            </a>
        </div>
    </div>
    <!-- /Toolbar -->

    <!-- Shopping Cart Offcanvas -->
    <div class="offcanvas offcanvas-end" id="shoppingCart">
        <div class="canvas-wrapper">
            <div class="popup-header">
                <span class="title fw-semibold h4">Таны сагс</span>
                <span class="icon-close icon-close-popup" data-bs-dismiss="offcanvas"></span>
            </div>
            <div class="wrap">
                <div class="tf-mini-cart-wrap">
                    <div class="tf-mini-cart-main">
                        <div class="tf-mini-cart-sroll">
                            <div class="tf-mini-cart-items" id="mini-cart-items">
                                <?php if (empty($_cartItems)): ?>
                                <div class="box-text_empty type-shop_cart">
                                    <div class="shop-empty_top">
                                        <span class="icon"><i class="icon-shopping-cart-simple"></i></span>
                                        <h3 class="text-emp fw-normal">Сагс хоосон байна</h3>
                                        <p class="h6 text-main">Та одоогоор ямар ч бараа нэмээгүй байна</p>
                                    </div>
                                    <div class="shop-empty_bot">
                                        <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn" data-bs-dismiss="offcanvas">Дэлгүүр үзэх</a>
                                    </div>
                                </div>
                                <?php else: ?>
                                <?php foreach ($_cartItems as $item): ?>
                                <div class="tf-mini-cart-item" data-product-id="<?= (int)$item['product_id'] ?>" data-variant-id="<?= (int)($item['variant_id'] ?? 0) ?>">
                                    <div class="tf-mini-cart-image">
                                        <a href="<?= url('product/' . htmlspecialchars($item['slug'] ?? '')) ?>">
                                            <img src="<?= htmlspecialchars(fixImageUrl($item['image'] ?? null)) ?>" alt="<?= htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '') ?>">
                                        </a>
                                    </div>
                                    <div class="tf-mini-cart-info">
                                        <h6 class="title">
                                            <a href="<?= url('product/' . htmlspecialchars($item['slug'] ?? '')) ?>" class="link text-line-clamp-1">
                                                <?= htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '') ?>
                                            </a>
                                        </h6>
                                        <?php if (!empty($item['variant_label'])): ?>
                                        <div class="text-small text-main-2"><?= htmlspecialchars($item['variant_label']) ?></div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="h6 fw-semibold">
                                                <span class="number"><?= (int)$item['qty'] ?>x</span>
                                                <span class="price text-primary"><?= formatPrice((float)$item['price']) ?></span>
                                            </div>
                                            <button class="icon link icon-close btn-remove-cart" style="border:none;background:none;cursor:pointer;"
                                                data-product-id="<?= (int)$item['product_id'] ?>"
                                                data-variant-id="<?= (int)($item['variant_id'] ?? 0) ?>">
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($_cartItems)): ?>
                    <div class="tf-mini-cart-bottom">
                        <div class="tf-mini-cart-threshold">
                            <div class="text">
                                <h6 class="subtotal">Нийт дүн (<span id="mini-cart-count"><?= $_cartCount ?></span> бараа)</h6>
                                <h4 class="text-primary" id="mini-cart-total"><?= formatPrice($_cartTotal) ?></h4>
                            </div>
                        </div>
                        <div class="tf-mini-cart-bottom-wrap">
                            <div class="tf-mini-cart-view-checkout">
                                <a href="<?= url('cart.php') ?>" class="tf-btn btn-white animate-btn animate-dark line">Сагс харах</a>
                                <a href="<?= url('checkout.php') ?>" class="tf-btn animate-btn d-inline-flex bg-dark-2 w-100 justify-content-center">
                                    <span>Төлбөр төлөх</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- /Shopping Cart Offcanvas -->

    <!-- Ochaka Scripts -->
    <script src="<?= assetUrl('js/bootstrap.min.js') ?>"></script>
    <script src="<?= assetUrl('js/jquery.min.js') ?>"></script>
    <script src="<?= assetUrl('js/swiper-bundle.min.js') ?>"></script>
    <script src="<?= assetUrl('js/carousel.js') ?>"></script>
    <script src="<?= assetUrl('js/bootstrap-select.min.js') ?>"></script>
    <script src="<?= assetUrl('js/lazysize.min.js') ?>"></script>
    <script src="<?= assetUrl('js/wow.min.js') ?>"></script>
    <script src="<?= assetUrl('js/count-down.js') ?>"></script>
    <script src="<?= assetUrl('js/main.js') ?>"></script>

    <!-- Cart & Search JS -->
    <script>
    const BASE_URL = <?= json_encode(getBaseUrl()) ?>;

    // Add to cart
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-add-to-cart');
        if (!btn) return;
        e.preventDefault();
        const productId  = btn.dataset.productId;
        const variantId  = btn.dataset.variantId || 0;
        const qty        = parseInt(btn.closest('[data-qty]')?.dataset?.qty || 1);
        fetch(BASE_URL + 'cart-action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'add', product_id: productId, variant_id: variantId, qty: qty})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCartBadge(data.count);
                refreshMiniCart();
                // Open cart offcanvas
                const el = document.getElementById('shoppingCart');
                if (el) new bootstrap.Offcanvas(el).show();
            }
        });
    });

    // Remove from cart
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remove-cart');
        if (!btn) return;
        fetch(BASE_URL + 'cart-action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'remove', product_id: btn.dataset.productId, variant_id: btn.dataset.variantId || 0})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCartBadge(data.count);
                refreshMiniCart();
            }
        });
    });

    function updateCartBadge(count) {
        document.querySelectorAll('#cart-count-badge, #cart-count-toolbar').forEach(el => {
            el.textContent = count;
        });
    }

    function refreshMiniCart() {
        fetch(BASE_URL + 'cart-action.php?action=mini')
            .then(r => r.text())
            .then(html => {
                const wrap = document.getElementById('mini-cart-items');
                if (wrap) wrap.innerHTML = html;
            });
    }

    // Search
    let searchTimer;
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 2) {
                document.getElementById('search-results').innerHTML = '<p class="text-small text-main text-center py-4">Хайх бараагаа бичнэ үү...</p>';
                return;
            }
            searchTimer = setTimeout(() => {
                fetch(BASE_URL + 'backend/api/products.php?search=' + encodeURIComponent(q) + '&limit=6')
                    .then(r => r.json())
                    .then(data => {
                        const results = document.getElementById('search-results');
                        if (!data.products || data.products.length === 0) {
                            results.innerHTML = '<p class="text-small text-main text-center py-4">Олдсонгүй</p>';
                            return;
                        }
                        results.innerHTML = '<ul class="tf-search-list">' + data.products.map(p => `
                            <li class="tf-search-item">
                                <a href="${BASE_URL}product/${p.slug}" class="d-flex align-items-center gap-3 link py-2 px-3">
                                    <img src="${p.image || ''}" alt="${p.name_mn || p.name}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                                    <div>
                                        <div class="h6 fw-medium">${p.name_mn || p.name}</div>
                                        <div class="text-primary fw-semibold">${Number(p.price).toLocaleString()}₮</div>
                                    </div>
                                </a>
                            </li>`).join('') + '</ul>';
                    });
            }, 350);
        });
    }
    </script>
    <?= $extra_scripts ?? '' ?>
</body>
</html>
