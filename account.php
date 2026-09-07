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

// ── ACCOUNT ───────────────────────────────────────────────────
$page_title = 'Хувийн бүртгэл — ' . $siteName;

if (!$loggedIn || !customerToken()) {
    header('Location: ' . url('login') . '?redirect=' . urlencode(url('account')));
    exit;
}

$accountToken = customerToken();
$accountTab   = in_array($_GET['tab'] ?? '', ['info', 'addresses', 'orders'], true) ? $_GET['tab'] : 'info';

$meRes = apiCall('GET', 'auth/me.php', null, $accountToken);
if ($meRes['code'] !== 200) {
    // Token no longer valid server-side — force re-login
    logoutCustomerSession();
    header('Location: ' . url('login') . '?redirect=' . urlencode(url('account')));
    exit;
}
$accountUser = $meRes['data']['user'];

$accountAddresses = [];
$accountDistricts = [];
if ($accountTab === 'addresses') {
    $addrRes = apiCall('GET', 'addresses.php', null, $accountToken);
    $accountAddresses = $addrRes['data']['addresses'] ?? [];
    $distRes = apiCall('GET', 'districts.php');
    $accountDistricts = $distRes['data']['districts'] ?? [];
}

$accountOrders = [];
if ($accountTab === 'orders') {
    $ordRes = apiCall('GET', 'customer-orders.php', null, $accountToken);
    $accountOrders = $ordRes['data']['orders'] ?? [];
}

$orderStatusLabels = [
    'pending'        => 'Хүлээгдэж буй',
    'confirmed'      => 'Баталгаажсан',
    'cargo_shipping' => 'Карго тээвэрлэж буй',
    'cargo_arrived'  => 'Карго ирсэн',
    'ready_pickup'   => 'Авахад бэлэн',
    'delivering'     => 'Хүргэж буй',
    'delivered'      => 'Хүргэгдсэн',
    'picked_up'      => 'Авсан',
    'completed'      => 'Дууссан',
    'cancelled'      => 'Цуцлагдсан',
];

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

    </style>
EXTRA_CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- SHOP BREADCRUMB -->
    <!-- ACCOUNT BREADCRUMB -->
    <div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active">Хувийн бүртгэл</li>
                </ul>
                <h1 class="title h3 mt--10">Сайн байна уу, <?= h($accountUser['name'] ?: $accountUser['phone']) ?></h1>
            </div>
        </div>
    </div>

    <!-- ACCOUNT MAIN -->
    <div class="rbt-shop-area rbt-section-gapBottom rbt-bg-color-white">
        <div class="container">
            <div class="row row--30">
                <aside class="col-lg-3 mt--30">
                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one p--16 rbt-rounded--12">
                        <ul class="list-unstyled mb-0">
                            <li class="mb--4"><a href="<?= h(url('account?tab=info')) ?>" class="rbt-btn rbt-btn-sm w-100 text-start <?= $accountTab === 'info' ? 'rbt-btn-border' : 'rbt-btn-transparent' ?>"><i class="fa-regular fa-user mr--8"></i>Миний мэдээлэл</a></li>
                            <li class="mb--4"><a href="<?= h(url('account?tab=addresses')) ?>" class="rbt-btn rbt-btn-sm w-100 text-start <?= $accountTab === 'addresses' ? 'rbt-btn-border' : 'rbt-btn-transparent' ?>"><i class="fa-regular fa-location-dot mr--8"></i>Хаягууд</a></li>
                            <li class="mb--4"><a href="<?= h(url('account?tab=orders')) ?>" class="rbt-btn rbt-btn-sm w-100 text-start <?= $accountTab === 'orders' ? 'rbt-btn-border' : 'rbt-btn-transparent' ?>"><i class="fa-regular fa-bag-shopping mr--8"></i>Захиалгууд</a></li>
                            <li><a href="<?= h($urlLogout) ?>" class="rbt-btn rbt-btn-sm rbt-btn-transparent w-100 text-start"><i class="fa-regular fa-arrow-right-from-bracket mr--8"></i>Гарах</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-lg-9 mt--30">

                    <?php if ($accountTab === 'info'): ?>
                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one p--24 rbt-rounded--12">
                        <h4 class="rbt-widget-title mb--16">Миний мэдээлэл</h4>
                        <table class="table">
                            <tr><th style="width:160px;">Нэр</th><td><?= h($accountUser['name'] ?: '—') ?></td></tr>
                            <tr><th>Утасны дугаар</th><td><?= h($accountUser['phone'] ?: '—') ?></td></tr>
                            <tr><th>И-мэйл</th><td><?= h($accountUser['email'] ?: '—') ?></td></tr>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if ($accountTab === 'addresses'): ?>
                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one p--24 rbt-rounded--12 mb--24">
                        <h4 class="rbt-widget-title mb--16">Хадгалсан хаяг</h4>
                        <?php if (!$accountAddresses): ?>
                        <p class="text-muted mb-0">Одоогоор хаяг хадгалаагүй байна.</p>
                        <?php else: ?>
                        <?php foreach ($accountAddresses as $addr): ?>
                        <div class="d-flex justify-content-between align-items-start border-bottom pb--12 mb--12">
                            <div>
                                <p class="mb-0">
                                    <strong><?= h($addr['label'] ?: 'Хаяг') ?></strong>
                                    <?php if (!empty($addr['is_default'])): ?><span class="rbt-badge rbt-badge-bg-green rbt-badge-small rbt-badge-rounded ms-2">Үндсэн</span><?php endif; ?>
                                </p>
                                <p class="text-muted mb-0 small">
                                    <?= h($addr['district_name'] ?? '') ?><?= !empty($addr['khoroo_number']) ? ', ' . (int)$addr['khoroo_number'] . '-р хороо' : '' ?>
                                    — <?= h($addr['address']) ?><?= $addr['detail_address'] ? ', ' . h($addr['detail_address']) : '' ?>
                                </p>
                            </div>
                            <form method="POST" action="<?= h(url('account-address-action')) ?>" onsubmit="return confirm('Энэ хаягийг устгах уу?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                                <button type="submit" class="rbt-round-btn" aria-label="Устгах"><i class="fa-regular fa-trash"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one p--24 rbt-rounded--12">
                        <h4 class="rbt-widget-title mb--16">Шинэ хаяг нэмэх</h4>
                        <form method="POST" action="<?= h(url('account-address-action')) ?>" id="rwAddAddressForm">
                            <input type="hidden" name="action" value="add">
                            <div class="rbt-input-field-grp">
                                <label class="rbt-field-label">Нэршил (жишээ: Гэр, Ажил)</label>
                                <input class="rbt-input-field" type="text" name="label">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="rbt-input-field-grp">
                                        <label class="rbt-field-label">Дүүрэг<span class="rbt-text-color-danger">*</span></label>
                                        <select class="rbt-input-field" name="district_id" id="rwDistrictSelect" required>
                                            <option value="">Сонгох</option>
                                            <?php foreach ($accountDistricts as $d): ?>
                                            <option value="<?= (int)$d['id'] ?>"><?= h($d['name_mn'] ?: $d['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="rbt-input-field-grp">
                                        <label class="rbt-field-label">Хороо<span class="rbt-text-color-danger">*</span></label>
                                        <select class="rbt-input-field" name="khoroo_id" id="rwKhorooSelect" required>
                                            <option value="">Эхлээд дүүрэг сонгоно уу</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="rbt-input-field-grp">
                                <label class="rbt-field-label">Хаяг<span class="rbt-text-color-danger">*</span></label>
                                <input class="rbt-input-field" type="text" name="address" placeholder="Гудамж, байр" required>
                            </div>
                            <div class="rbt-input-field-grp">
                                <label class="rbt-field-label">Нэмэлт тайлбар</label>
                                <input class="rbt-input-field" type="text" name="detail_address" placeholder="Орц, давхар, тоот">
                            </div>
                            <div class="rbt-check-group mb--16">
                                <input id="rwIsDefault" type="checkbox" name="is_default" value="1">
                                <label for="rwIsDefault">Үндсэн хаяг болгох</label>
                            </div>
                            <button type="submit" class="rbt-btn">Хаяг нэмэх</button>
                        </form>
                    </div>

                    <script>
                    (function () {
                        var districts = <?= json_encode($accountDistricts, JSON_UNESCAPED_UNICODE) ?>;
                        var districtSelect = document.getElementById('rwDistrictSelect');
                        var khorooSelect = document.getElementById('rwKhorooSelect');
                        districtSelect.addEventListener('change', function () {
                            var d = districts.find(function (x) { return String(x.id) === districtSelect.value; });
                            khorooSelect.innerHTML = '';
                            if (!d || !d.khoroos.length) {
                                khorooSelect.innerHTML = '<option value="">Хороо алга</option>';
                                return;
                            }
                            khorooSelect.innerHTML = '<option value="">Сонгох</option>';
                            d.khoroos.forEach(function (k) {
                                var opt = document.createElement('option');
                                opt.value = k.id;
                                opt.textContent = k.number + '-р хороо' + (k.name ? ' (' + k.name + ')' : '');
                                khorooSelect.appendChild(opt);
                            });
                        });
                    })();
                    </script>
                    <?php endif; ?>

                    <?php if ($accountTab === 'orders'): ?>
                    <?php if (!$accountOrders): ?>
                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one p--24 rbt-rounded--12 text-center">
                        <p class="text-muted mb-0">Одоогоор захиалга хийгээгүй байна.</p>
                        <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border mt--12">Дэлгүүр рүү очих</a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($accountOrders as $order): ?>
                    <div class="rbt-sidebar-widget-wrapper rbt-sidebar-bg-one p--24 rbt-rounded--12 mb--16">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb--12">
                            <div>
                                <strong>#<?= h($order['order_number']) ?></strong>
                                <span class="text-muted small ms-2"><?= h(date('Y.m.d', strtotime($order['created_at']))) ?></span>
                            </div>
                            <span class="rbt-badge rbt-badge-border rbt-badge-rounded"><?= h($orderStatusLabels[$order['status']] ?? $order['status']) ?></span>
                        </div>
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="d-flex justify-content-between small mb--4">
                            <span><?= h($item['product_name_mn']) ?> × <?= (int)$item['quantity'] ?></span>
                            <span><?= h(formatPrice($item['line_total'] ?? ($item['product_price'] * $item['quantity']))) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <hr class="rbt-separator rbt-separator-gray200">
                        <div class="d-flex justify-content-between">
                            <strong>Нийт дүн</strong>
                            <strong><?= h(formatPrice($order['total'])) ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>


    <!-- ALL CATEGORIES -->


<?php require __DIR__ . '/includes/footer.php'; ?>
