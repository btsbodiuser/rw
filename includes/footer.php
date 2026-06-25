<?php
$_cartItems = $_SESSION['cart'] ?? [];
$_cartTotal = cartTotal();
$_cartCount = cartCount();

// Recommendations: 4 random in-store products
$_recoItems = [];
try {
    $s = getDB()->query("SELECT id, slug, name_mn, name, image, price, compare_price FROM products WHERE show_in_store = 1 ORDER BY RAND() LIMIT 4");
    $_recoItems = $s->fetchAll();
} catch (Throwable $e) {}
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
                                        <?php $addr = s('address', ''); if ($addr): ?>
                                        <li>
                                            <i class="icon icon-map-pin"></i>
                                            <span class="br-line"></span>
                                            <span class="h6"><?= htmlspecialchars($addr) ?></span>
                                        </li>
                                        <?php endif; ?>
                                        <?php $phone = s('phone', ''); if ($phone): ?>
                                        <li>
                                            <i class="icon icon-phone"></i>
                                            <span class="br-line"></span>
                                            <a href="tel:<?= htmlspecialchars($phone) ?>" class="h6 link"><?= htmlspecialchars($phone) ?></a>
                                        </li>
                                        <?php endif; ?>
                                        <?php $email = s('email', ''); if ($email): ?>
                                        <li>
                                            <i class="icon icon-envelope-simple"></i>
                                            <span class="br-line"></span>
                                            <a href="mailto:<?= htmlspecialchars($email) ?>" class="h6 link"><?= htmlspecialchars($email) ?></a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                    <?php
                                    $fb = s('facebook_url', '');
                                    $ig = s('instagram_url', '');
                                    $tt = s('tiktok_url', '');
                                    if ($fb || $ig || $tt): ?>
                                    <div class="social-wrap">
                                        <ul class="tf-social-icon">
                                            <?php if ($fb): ?><li><a href="<?= htmlspecialchars($fb) ?>" target="_blank" class="social-facebook"><span class="icon"><i class="icon-fb"></i></span></a></li><?php endif; ?>
                                            <?php if ($ig): ?><li><a href="<?= htmlspecialchars($ig) ?>" target="_blank" class="social-instagram"><span class="icon"><i class="icon-instagram-logo"></i></span></a></li><?php endif; ?>
                                            <?php if ($tt): ?><li><a href="<?= htmlspecialchars($tt) ?>" target="_blank" class="social-tiktok"><span class="icon"><i class="icon-tiktok"></i></span></a></li><?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
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
                                            <?= htmlspecialchars(s('site_slogan', '') ?: s('site_name', 'Runners World')) ?>
                                        </p>
                                        <?php $phone2 = s('phone', ''); if ($phone2): ?>
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
                        <a href="<?= url('blog') ?>" class="nav-mb-link h5 fw-medium">Блог</a>
                    </li>
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
    <?php
    $freeThreshold   = (float)s('free_delivery_threshold', 0);
    $deliveryEnabled = s('delivery_fee_enabled', '1') === '1';
    $progressPct     = ($freeThreshold > 0 && $_cartTotal < $freeThreshold)
                        ? min(100, round($_cartTotal / $freeThreshold * 100)) : 100;
    $remaining       = max(0, $freeThreshold - $_cartTotal);
    ?>
    <div class="offcanvas offcanvas-end popup-shopping-cart" id="shoppingCart">
        <?php if (!empty($_recoItems)): ?>
        <div class="tf-minicart-recommendations">
            <h4 class="title">Танд таалагдаж болох</h4>
            <div class="wrap-recommendations">
                <div class="list-cart">
                    <?php foreach ($_recoItems as $rp): ?>
                    <div class="list-cart-item">
                        <div class="image">
                            <img src="<?= htmlspecialchars(fixImageUrl($rp['image'] ?? null)) ?>"
                                 alt="<?= htmlspecialchars($rp['name_mn'] ?: $rp['name']) ?>">
                        </div>
                        <div class="content">
                            <h6 class="name">
                                <a class="link text-line-clamp-1" href="<?= url('product/' . htmlspecialchars($rp['slug'])) ?>">
                                    <?= htmlspecialchars($rp['name_mn'] ?: $rp['name']) ?>
                                </a>
                            </h6>
                            <div class="cart-item-bot">
                                <div class="price-wrap price">
                                    <?php if (!empty($rp['compare_price']) && (float)$rp['compare_price'] > (float)$rp['price']): ?>
                                    <span class="price-old h6 fw-normal"><?= formatPrice((float)$rp['compare_price']) ?></span>
                                    <?php endif; ?>
                                    <span class="price-new h6"><?= formatPrice((float)$rp['price']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="canvas-wrapper">
            <div class="popup-header">
                <span class="title fw-semibold h4">Таны сагс</span>
                <span class="icon-close icon-close-popup" data-bs-dismiss="offcanvas"></span>
            </div>
            <div class="wrap">
                <div class="tf-mini-cart-wrap list-file-delete wrap-empty_text">
                    <div class="tf-mini-cart-main">
                        <div class="tf-mini-cart-sroll">
                            <div class="tf-mini-cart-items<?= empty($_cartItems) ? ' list-empty' : '' ?>" id="mini-cart-items">
                                <?php if (empty($_cartItems)): ?>
                                <div class="box-text_empty type-shop_cart">
                                    <div class="shop-empty_top">
                                        <span class="icon"><i class="icon-shopping-cart-simple"></i></span>
                                        <h3 class="text-emp fw-normal">Сагс хоосон байна</h3>
                                        <p class="h6 text-main">Та одоогоор ямар ч бараа нэмээгүй байна</p>
                                    </div>
                                    <div class="shop-empty_bot">
                                        <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn" data-bs-dismiss="offcanvas">Дэлгүүр үзэх</a>
                                        <a href="<?= url() ?>" class="tf-btn style-line" data-bs-dismiss="offcanvas">Нүүр хуудас</a>
                                    </div>
                                </div>
                                <?php else: ?>
                                <?php foreach ($_cartItems as $item): ?>
                                <div class="tf-mini-cart-item file-delete">
                                    <div class="tf-mini-cart-image">
                                        <a href="<?= url('product/' . htmlspecialchars($item['slug'] ?? '')) ?>">
                                            <img src="<?= htmlspecialchars(fixImageUrl($item['image'] ?? null)) ?>"
                                                 alt="<?= htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '') ?>">
                                        </a>
                                    </div>
                                    <div class="tf-mini-cart-info">
                                        <?php if (!empty($item['category_name'])): ?>
                                        <div class="text-small text-main-2 sub"><?= htmlspecialchars($item['category_name']) ?></div>
                                        <?php endif; ?>
                                        <h6 class="title">
                                            <a href="<?= url('product/' . htmlspecialchars($item['slug'] ?? '')) ?>" class="link text-line-clamp-1">
                                                <?= htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '') ?>
                                            </a>
                                        </h6>
                                        <?php if (!empty($item['variant_label'])): ?>
                                        <div class="size">
                                            <div class="text-small text-main-2 sub"><?= htmlspecialchars($item['variant_label']) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="h6 fw-semibold">
                                                <span class="number"><?= (int)$item['qty'] ?>x</span>
                                                <span class="price text-primary tf-mini-card-price"><?= formatPrice((float)$item['price']) ?></span>
                                            </div>
                                            <i class="icon link icon-close remove btn-remove-cart" style="cursor:pointer;"
                                               data-product-id="<?= (int)$item['product_id'] ?>"
                                               data-variant-id="<?= (int)($item['variant_id'] ?? 0) ?>"></i>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tf-mini-cart-bottom box-empty_clear"<?= empty($_cartItems) ? ' style="display:none"' : '' ?>>
                        <div class="tf-mini-cart-tool">
                            <div class="tf-mini-cart-tool-btn btn-add-note">
                                <div class="h6">Тэмдэглэл</div>
                                <i class="icon icon-note-pencil"></i>
                            </div>
                            <div class="tf-mini-cart-tool-btn btn-estimate-shipping">
                                <div class="h6">Хүргэлт</div>
                                <i class="icon icon-truck"></i>
                            </div>
                            <div class="tf-mini-cart-tool-btn btn-add-gift">
                                <div class="h6">Бэлэг</div>
                                <i class="icon icon-gift"></i>
                            </div>
                        </div>
                        <div class="tf-mini-cart-threshold">
                            <div class="text">
                                <h6 class="subtotal">Нийт дүн (<span class="prd-count" id="mini-cart-count"><?= $_cartCount ?></span> бараа)</h6>
                                <h4 class="text-primary total-price tf-totals-total-value" id="mini-cart-total"><?= formatPrice($_cartTotal) ?></h4>
                            </div>
                            <?php if ($deliveryEnabled && $freeThreshold > 0): ?>
                            <div class="tf-progress-bar tf-progress-ship" id="mini-ship-bar">
                                <div class="value" style="width:<?= $progressPct ?>%" data-progress="<?= $progressPct ?>"></div>
                            </div>
                            <div class="desc text-main" id="mini-ship-desc">
                                <?php if ($remaining > 0): ?>
                                Нэмж <span class="text-primary fw-bold"><?= formatPrice($remaining) ?></span> авбал үнэгүй хүргэлт!
                                <?php else: ?>
                                <i class="icon icon-truck me-1"></i><span class="text-primary fw-bold">Үнэгүй хүргэлт</span> авах болно!
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="tf-mini-cart-bottom-wrap">
                            <div class="tf-mini-cart-view-checkout">
                                <a href="<?= url('cart.php') ?>" class="tf-btn btn-white animate-btn animate-dark line">Сагс харах</a>
                                <a href="<?= url('checkout.php') ?>" class="tf-btn animate-btn d-inline-flex bg-dark-2 w-100 justify-content-center">
                                    <span>Төлбөр төлөх</span>
                                </a>
                            </div>
                            <?php if ($deliveryEnabled && $freeThreshold > 0): ?>
                            <div class="free-shipping">
                                <i class="icon icon-truck"></i>
                                <?= formatPrice($freeThreshold) ?>-аас дээш захиалгад үнэгүй хүргэлт
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tf-mini-cart-tool-openable add-note">
                        <div class="overlay tf-mini-cart-tool-close"></div>
                        <form class="tf-mini-cart-tool-content">
                            <label class="tf-mini-cart-tool-text h5 fw-semibold">
                                <i class="icon icon-note-pencil"></i>
                                Тэмдэглэл
                            </label>
                            <textarea name="note" placeholder="Захиалгын талаар тэмдэглэл..."></textarea>
                            <div class="tf-cart-tool-btns">
                                <button class="subscribe-button tf-btn animate-btn d-inline-flex bg-dark-2 w-100" type="submit">Хадгалах</button>
                                <div class="tf-btn btn-white animate-btn animate-dark line tf-mini-cart-tool-close">Болих</div>
                            </div>
                        </form>
                    </div>
                    <div class="tf-mini-cart-tool-openable estimate-shipping">
                        <div class="overlay tf-mini-cart-tool-close"></div>
                        <div class="tf-mini-cart-tool-content">
                            <div class="tf-mini-cart-tool-text h5 fw-semibold">
                                <i class="icon icon-truck"></i>
                                Хүргэлтийн мэдээлэл
                            </div>
                            <p class="h6 text-main mt-2">
                                <?php if ($deliveryEnabled && $freeThreshold > 0): ?>
                                <?= formatPrice($freeThreshold) ?>-аас дээш захиалгад үнэгүй хүргэлт үйлчилнэ.
                                <?php else: ?>
                                Хүргэлтийн нөхцөлийн талаар дэлгэрэнгүй мэдээллийг дэлгүүртэй холбогдон авна уу.
                                <?php endif; ?>
                            </p>
                            <div class="tf-cart-tool-btns mt-3">
                                <div class="tf-btn btn-white animate-btn animate-dark line tf-mini-cart-tool-close">Хаах</div>
                            </div>
                        </div>
                    </div>
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
    <script src="<?= assetUrl('js/parallaxie.js') ?>"></script>
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
                refreshMiniCart().then(() => {
                    const el = document.getElementById('shoppingCart');
                    if (el) new bootstrap.Offcanvas(el).show();
                });
            } else {
                console.error('Add to cart failed:', data.error);
            }
        })
        .catch(err => console.error('Cart request error:', err));
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
        return fetch(BASE_URL + 'cart-action.php?action=mini')
            .then(r => r.json())
            .then(data => {
                const wrap = document.getElementById('mini-cart-items');
                if (wrap) {
                    wrap.innerHTML = data.items_html;
                    wrap.classList.toggle('list-empty', data.empty);
                }
                // Ochaka's checkListEmpty() hides this on empty cart — restore it when items exist
                const bottom = document.querySelector('.tf-mini-cart-bottom.box-empty_clear');
                if (bottom) bottom.style.display = data.empty ? 'none' : '';
                const countEl = document.getElementById('mini-cart-count');
                if (countEl) countEl.textContent = data.count;
                const totalEl = document.getElementById('mini-cart-total');
                if (totalEl) totalEl.textContent = data.total;
                const bar = document.getElementById('mini-ship-bar');
                if (bar) {
                    const val = bar.querySelector('.value');
                    if (val) { val.style.width = data.progress + '%'; val.dataset.progress = data.progress; }
                }
                const desc = document.getElementById('mini-ship-desc');
                if (desc && data.progress < 100) {
                    desc.innerHTML = 'Нэмж <span class="text-primary fw-bold">' + data.remaining + '</span> авбал үнэгүй хүргэлт!';
                } else if (desc) {
                    desc.innerHTML = '<i class="icon icon-truck me-1"></i><span class="text-primary fw-bold">Үнэгүй хүргэлт</span> авах болно!';
                }
            });
    }

    // Search — live results in Ochaka modal-search style
    let searchTimer;
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    const searchResultsList = document.getElementById('search-results-list');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            searchTimer = setTimeout(() => {
                fetch(BASE_URL + 'backend/api/products.php?search=' + encodeURIComponent(q) + '&limit=6')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.products || data.products.length === 0) {
                            searchResults.style.display = 'none';
                            return;
                        }
                        searchResults.style.display = 'block';
                        searchResultsList.innerHTML = data.products.map(p => `
                            <a class="item text-main link h6" href="${BASE_URL}product/${p.slug}">
                                <span>${p.name_mn || p.name}</span>
                                <i class="icon icon-arrow-top-right"></i>
                            </a>`).join('');
                    });
            }, 300);
        });

        // Hide results when modal closes
        document.getElementById('search')?.addEventListener('hidden.bs.modal', function() {
            searchInput.value = '';
            searchResults.style.display = 'none';
        });
    }
    </script>
    <?= $extra_scripts ?? '' ?>
</body>
</html>
