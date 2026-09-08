<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');
$db       = getDB();

// ── ACCOUNT ───────────────────────────────────────────────────
$page_title = 'Хувийн бүртгэл — ' . $siteName;

if (!$loggedIn || !customerToken()) {
    header('Location: ' . url('login') . '?redirect=' . urlencode(url('account')));
    exit;
}

$accountToken = customerToken();
$accountTab   = in_array($_GET['tab'] ?? '', ['info', 'addresses', 'orders'], true) ? $_GET['tab'] : 'info';
$accountError = trim($_GET['error'] ?? '');

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
// Order count for the sidebar badge — fetched on every tab, not just "orders".
if ($accountTab !== 'orders') {
    $ordCountRes = apiCall('GET', 'customer-orders.php', null, $accountToken);
    $accountOrderCount = count($ordCountRes['data']['orders'] ?? []);
} else {
    $accountOrderCount = count($accountOrders);
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
function orderStatusBadgeClass(string $status): string {
    return match ($status) {
        'delivered', 'picked_up', 'completed' => 'rbt-badge-bg-green',
        'cancelled' => 'rbt-badge-bg-danger',
        default => 'rbt-badge-bg-warning',
    };
}
$paymentMethodLabels = [
    'qpay'     => 'QPay',
    'bonum'    => 'Бонум',
    'storepay' => 'StorePay',
    'transfer' => 'Данс шилжүүлэг',
    'cash'     => 'Бэлэн мөнгө',
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

        /* Account order-history thumbnails: fixed small squares, whole photo visible. */
        .ordered-item {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            overflow: hidden;
            background: var(--color-gray-light, #f2f2f2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .ordered-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .ordered-item.more-icon {
            background: transparent;
            color: var(--color-body, #6b7280);
        }
        .rbt-order-row {
            cursor: pointer;
            transition: background-color .15s;
        }
        .rbt-order-row:hover {
            background-color: rgba(0, 183, 255, 0.04);
        }
        /* Order detail offcanvas: reuses the theme's rbt-sidebar-cart look. */
        .rw-order-offcanvas { width: 480px; max-width: 100%; }
        .rw-order-offcanvas .offcanvas-body { padding: 0; }
        .rw-order-offcanvas .inner-wrapper {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .rw-order-offcanvas .inner-top {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 24px;
        }
        .rw-order-offcanvas .rbt-minicart-footer {
            border-top: 1px solid #eaeaea;
            padding: 20px 24px;
            background: #fafafa;
        }
    </style>
EXTRA_CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- SHOP BREADCRUMB -->
    <!-- ACCOUNT BREADCRUMB -->
    <div class="rbt-breadcrumb-two rbt-bg-color-gray-100">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active">Хувийн бүртгэл</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ACCOUNT MAIN -->
    <div class="rbt-component-area rbt-section-gap rbt-bg-color-gray-light">
        <div class="container">
            <div class="row row--12 mt_dec--24">

                <!-- SIDEBAR -->
                <div class="col-12 col-md-12 col-lg-4 col-xl-3 mt--24">
                    <aside class="rbt-profile-sidebar sticky-top">
                        <div class="rbt-user-profile">
                            <figure class="rbt-user-profile-img">
                                <img src="<?= h(!empty($accountUser['avatar'] ?? '') ? $accountUser['avatar'] : assetUrl('images/dashboard/user-profile-01.webp')) ?>" alt="Profile Image">
                            </figure>
                            <div class="pl--12">
                                <h2 class="h6 mb-1"><?= h($accountUser['name'] ?: $accountUser['phone']) ?></h2>
                            </div>
                        </div>
                        <hr class="mb--8 mt--20">
                        <div class="rbt-sidebar-widgets">
                            <div class="rbt-sidebar-single-widget">
                                <nav class="rbt-sidebar-nav-list list-group">
                                    <a href="<?= h(url('account?tab=orders')) ?>" class="<?= $accountTab === 'orders' ? 'active' : '' ?>">
                                        <span><i class="fa-regular fa-bag-shopping mr--4"></i>Захиалгууд</span>
                                        <?php if ($accountOrderCount > 0): ?><span class="badge bg-primary rounded-pill ms-auto"><?= (int)$accountOrderCount ?></span><?php endif; ?>
                                    </a>
                                </nav>
                            </div>
                            <div class="rbt-sidebar-single-widget">
                                <h2 class="rbt-title h6">Бүртгэл</h2>
                                <nav class="rbt-sidebar-nav-list list-group">
                                    <a href="<?= h(url('account?tab=info')) ?>" class="<?= $accountTab === 'info' ? 'active' : '' ?>">
                                        <span><i class="fa-regular fa-user mr--4"></i>Хувийн мэдээлэл</span>
                                    </a>
                                    <a href="<?= h(url('account?tab=addresses')) ?>" class="<?= $accountTab === 'addresses' ? 'active' : '' ?>">
                                        <span><i class="fa-regular fa-location-dot mr--4"></i>Хаягууд</span>
                                    </a>
                                </nav>
                            </div>
                            <hr>
                            <nav class="rbt-sidebar-nav-list list-group">
                                <a href="<?= h($urlLogout) ?>">
                                    <span><i class="fa-regular fa-arrow-right-from-bracket mr--4"></i>Гарах</span>
                                </a>
                            </nav>
                        </div>
                    </aside>
                </div>

                <!-- CONTENT -->
                <div class="col-12 col-md-12 col-lg-8 col-xl-9 mt--24">

                    <?php if ($accountTab === 'info'): ?>
                    <div class="rbt-profile-content-area">
                        <div class="row row--12 mt_dec--24">
                            <div class="col-12 mt--24">
                                <div class="rbt-component-section-title rbt-gap--4 mb--0 p-0 border-0">
                                    <h2 class="rbt-title mb--0"><span class="rbt-text-bold">Хувийн мэдээлэл</span></h2>
                                </div>
                            </div>
                        </div>
                        <hr class="mt--20 mb--16">

                        <?php if ($accountError): ?>
                        <div class="alert alert-danger"><?= h($accountError) ?></div>
                        <?php endif; ?>

                        <div class="rbt-scrollable-content hide-scrollbar">
                            <div class="rbt-single-info mb--24">
                                <div class="rbt-single-info-header d-flex justify-content-between align-items-center mb--12 pt--4">
                                    <h2 class="h6 mb--0">Үндсэн мэдээлэл</h2>
                                    <button class="rbt-btn rbt-btn-sm rbt-btn-secondary" type="button" data-bs-toggle="modal" data-bs-target="#basicInfoEditModal"><i class="fa-light fa-pen-to-square mr--4"></i>Засах</button>
                                </div>
                                <p class="b1 mb--0"><?= h($accountUser['name'] ?: '—') ?></p>
                            </div>
                            <hr>
                            <div class="rbt-single-info mb--24">
                                <div class="rbt-single-info-header d-flex justify-content-between align-items-center mb--12 pt--4">
                                    <h2 class="h6 mb--0">Холбоо барих мэдээлэл</h2>
                                    <button class="rbt-btn rbt-btn-sm rbt-btn-secondary" type="button" data-bs-toggle="modal" data-bs-target="#contactInfoEditModal"><i class="fa-light fa-pen-to-square mr--4"></i>Засах</button>
                                </div>
                                <p class="b1 mb--8"><i class="fa-regular fa-phone mr--4 text-muted"></i><?= h($accountUser['phone'] ?: '—') ?></p>
                                <p class="b1 mb--0"><i class="fa-regular fa-envelope mr--4 text-muted"></i><?= h($accountUser['email'] ?: '—') ?></p>
                            </div>
                            <hr>
                            <div class="rbt-single-info mb--24">
                                <div class="rbt-single-info-header d-flex justify-content-between align-items-center mb--12 pt--4">
                                    <h2 class="h6 mb--0">Нууц үг</h2>
                                    <button class="rbt-btn rbt-btn-sm rbt-btn-secondary" type="button" data-bs-toggle="modal" data-bs-target="#passwordEditModal"><i class="fa-light fa-pen-to-square mr--4"></i>Засах</button>
                                </div>
                                <p class="b1 mb--0">**********</p>
                            </div>
                        </div>
                    </div>

                    <!-- Basic info modal -->
                    <div class="rbt-default-modal modal fade has-rbt-top-folder-shape" id="basicInfoEditModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="basicInfoEditModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered rbt-cart-edit-area">
                            <div class="modal-content">
                                <div class="rbt-folder-shape-right-portion">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="85" height="90" viewBox="0 0 85 90" fill="none">
                                        <path
                                            d="M0 0H11.1844C14.5695 0 17.7971 1.42971 20.0716 3.93671L82.1927 72.4059C83.9992 74.397 84.9999 76.9893 84.9999 79.6778C84.9999 85.6547 85.0001 90 85.0001 90H0V0Z"
                                            fill="white" />
                                    </svg>
                                </div>
                                <div class="modal-header">
                                    <button type="button" class="rbt-round-btn rbt-modal-dis-btn" data-bs-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                                <div class="rbt-top-folder-shape-wrapper">
                                    <div class="rbt-single-product-area rbt-bg-color-white rbt-content-trs-portion">
                                        <h2 class="rbt-title rbt-modal-title h5 mb--24" id="basicInfoEditModalLabel">Үндсэн мэдээлэл засах</h2>
                                        <form method="POST" action="<?= h(url('account-info-action')) ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update_basic">
                                            <div class="row row--12 mt_dec--24">
                                                <div class="col-12 mt--24">
                                                    <label for="edit_name" class="form-label">Нэр</label>
                                                    <input type="text" id="edit_name" name="name" value="<?= h($accountUser['name'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex rbt-gap--16">
                                                        <button type="button" class="rbt-btn rbt-btn-secondary rbt-btn-md rbt-square-btn mt--24" data-bs-dismiss="modal">Цуцлах</button>
                                                        <button type="submit" class="rbt-btn rbt-btn-md rbt-square-btn mt--24">Хадгалах</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact info modal -->
                    <div class="rbt-default-modal modal fade has-rbt-top-folder-shape" id="contactInfoEditModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="contactInfoEditModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered rbt-cart-edit-area">
                            <div class="modal-content">
                                <div class="rbt-folder-shape-right-portion">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="85" height="90" viewBox="0 0 85 90" fill="none">
                                        <path
                                            d="M0 0H11.1844C14.5695 0 17.7971 1.42971 20.0716 3.93671L82.1927 72.4059C83.9992 74.397 84.9999 76.9893 84.9999 79.6778C84.9999 85.6547 85.0001 90 85.0001 90H0V0Z"
                                            fill="white" />
                                    </svg>
                                </div>
                                <div class="modal-header">
                                    <button type="button" class="rbt-round-btn rbt-modal-dis-btn" data-bs-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                                <div class="rbt-top-folder-shape-wrapper">
                                    <div class="rbt-single-product-area rbt-bg-color-white rbt-content-trs-portion">
                                        <h2 class="rbt-title rbt-modal-title h5 mb--24" id="contactInfoEditModalLabel">Холбоо барих мэдээлэл засах</h2>
                                        <form method="POST" action="<?= h(url('account-info-action')) ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update_contact">
                                            <div class="row row--12 mt_dec--24">
                                                <div class="col-md-6 mt--24">
                                                    <label for="phone_number" class="form-label">Утасны дугаар</label>
                                                    <input type="text" id="phone_number" name="phone" value="<?= h($accountUser['phone'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6 mt--24">
                                                    <label for="edit_email" class="form-label">И-мэйл хаяг</label>
                                                    <input type="text" id="edit_email" name="email" value="<?= h($accountUser['email'] ?? '') ?>">
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex rbt-gap--16">
                                                        <button type="button" class="rbt-btn rbt-btn-secondary rbt-btn-md rbt-square-btn mt--24" data-bs-dismiss="modal">Цуцлах</button>
                                                        <button type="submit" class="rbt-btn rbt-btn-md rbt-square-btn mt--24">Хадгалах</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password modal -->
                    <div class="rbt-default-modal modal fade has-rbt-top-folder-shape" id="passwordEditModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="passwordEditModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered rbt-cart-edit-area">
                            <div class="modal-content">
                                <div class="rbt-folder-shape-right-portion">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="85" height="90" viewBox="0 0 85 90" fill="none">
                                        <path
                                            d="M0 0H11.1844C14.5695 0 17.7971 1.42971 20.0716 3.93671L82.1927 72.4059C83.9992 74.397 84.9999 76.9893 84.9999 79.6778C84.9999 85.6547 85.0001 90 85.0001 90H0V0Z"
                                            fill="white" />
                                    </svg>
                                </div>
                                <div class="modal-header">
                                    <button type="button" class="rbt-round-btn rbt-modal-dis-btn" data-bs-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                                <div class="rbt-top-folder-shape-wrapper">
                                    <div class="rbt-single-product-area rbt-bg-color-white rbt-content-trs-portion">
                                        <h2 class="rbt-title rbt-modal-title h5 mb--24" id="passwordEditModalLabel">Нууц үг солих</h2>
                                        <form method="POST" action="<?= h(url('account-info-action')) ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="change_password">
                                            <div class="row row--12 mt_dec--24">
                                                <div class="col-12 mt--24">
                                                    <label for="current_password" class="form-label">Одоогийн нууц үг</label>
                                                    <input type="password" id="current_password" name="current_password" placeholder="Одоогийн нууц үг">
                                                </div>
                                                <div class="col-md-6 mt--24">
                                                    <label for="new_password" class="form-label">Шинэ нууц үг</label>
                                                    <input type="password" id="new_password" name="new_password" placeholder="Доод тал нь 6 тэмдэгт" required minlength="6">
                                                </div>
                                                <div class="col-md-6 mt--24">
                                                    <label for="confirm_password" class="form-label">Шинэ нууц үг давтах</label>
                                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Дахин оруулах" required minlength="6">
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex rbt-gap--16">
                                                        <button type="button" class="rbt-btn rbt-btn-secondary rbt-btn-md rbt-square-btn mt--24" data-bs-dismiss="modal">Цуцлах</button>
                                                        <button type="submit" class="rbt-btn rbt-btn-md rbt-square-btn mt--24">Хадгалах</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($accountTab === 'addresses'): ?>
                    <div class="rbt-profile-content-area">
                        <div class="row row--12 mt_dec--24">
                            <div class="col-12 mt--24">
                                <div class="rbt-component-section-title rbt-gap--4 mb--0 p-0 border-0">
                                    <h2 class="rbt-title mb--0"><span class="rbt-text-bold">Хадгалсан хаяг</span></h2>
                                </div>
                            </div>
                        </div>
                        <hr class="mt--20 mb--16">
                        <?php if (!$accountAddresses): ?>
                        <p class="text-muted mb-0">Одоогоор хаяг хадгалаагүй байна.</p>
                        <?php else: ?>
                        <?php foreach ($accountAddresses as $addr): ?>
                        <div class="rbt-single-info mb--16 d-flex justify-content-between align-items-start">
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
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                                <button type="submit" class="rbt-round-btn" aria-label="Устгах"><i class="fa-regular fa-trash"></i></button>
                            </form>
                        </div>
                        <hr>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <h2 class="h6 mt--24 mb--16">Шинэ хаяг нэмэх</h2>
                        <form method="POST" action="<?= h(url('account-address-action')) ?>" id="rwAddAddressForm">
                            <?= csrfField() ?>
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
                    <div class="rbt-profile-content-area">
                        <div class="row row--12 mt_dec--24">
                            <div class="col-12 mt--24">
                                <div class="rbt-component-section-title rbt-gap--4 mb--0 p-0 border-0">
                                    <h2 class="rbt-title mb--0"><span class="rbt-text-bold">Захиалгын түүх</span></h2>
                                </div>
                            </div>
                        </div>
                        <hr class="mt--20 mb--16">

                        <?php if (!$accountOrders): ?>
                        <div class="text-center py-5">
                            <p class="text-muted mb-0">Одоогоор захиалга хийгээгүй байна.</p>
                            <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border mt--12">Дэлгүүр рүү очих</a>
                        </div>
                        <?php else: ?>
                        <div class="rbt-transparent-table-one-wrapper">
                            <table class="rbt-transparent-table-one table-variation-one m--0 mb--0">
                                <thead>
                                    <tr>
                                        <th class="pt--0" scope="col"><i class="fa-regular fa-hashtag mr--4"></i>Дугаар</th>
                                        <th class="pt--0" scope="col"><i class="fa-regular fa-calendars mr--4"></i>Огноо</th>
                                        <th class="pt--0" scope="col"><i class="fa-regular fa-truck-fast mr--4"></i>Төлөв</th>
                                        <th class="pt--0" scope="col"><i class="fa-regular fa-sack-dollar mr--4"></i>Нийт дүн</th>
                                        <th class="pt--0" scope="col"><i class="fa-regular fa-bag-shopping mr--4"></i>Бараа</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($accountOrders as $oi => $order): $ocId = 'rwOrderOc' . $oi; ?>
                                    <tr class="rbt-order-row" data-bs-toggle="offcanvas" data-bs-target="#<?= $ocId ?>" aria-controls="<?= $ocId ?>">
                                        <td>
                                            <div class="cart-product-card">
                                                <span class="rbt-product-id rbt-cursor-pointer">
                                                    <span class="rbt-text-semi-bold">#</span><?= h($order['order_number']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span><?= h(date('Y.m.d', strtotime($order['created_at']))) ?></span></td>
                                        <td>
                                            <div class="rbt-badge <?= orderStatusBadgeClass($order['status']) ?> rbt-badge-border rbt-badge-md rbt-badge-rounded">
                                                <?= h($orderStatusLabels[$order['status']] ?? $order['status']) ?>
                                            </div>
                                        </td>
                                        <td><p class="price-text h6 mb-0"><span class="rbt-bold--text"><?= h(formatPrice($order['total'])) ?></span></p></td>
                                        <td>
                                            <div class="rbt-order-sum-area rbt-order-sum-area-xm d-flex">
                                                <span class="ordered-items-wrapper d-flex rbt-gap--4 align-items-center ms-auto">
                                                    <?php foreach (array_slice($order['items'], 0, 3) as $item): ?>
                                                    <span class="ordered-item"><img src="<?= h(fixImageUrl($item['image'])) ?>" alt="<?= h($item['product_name_mn']) ?>"></span>
                                                    <?php endforeach; ?>
                                                    <span class="ordered-item more-icon ms-auto"><i class="fa-solid fa-chevron-right"></i></span>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php foreach ($accountOrders as $oi => $order): $ocId = 'rwOrderOc' . $oi; ?>
                        <div class="offcanvas offcanvas-end rw-order-offcanvas" tabindex="-1" id="<?= $ocId ?>" aria-labelledby="<?= $ocId ?>Label">
                            <div class="offcanvas-header border-bottom">
                                <div>
                                    <h5 class="offcanvas-title mb--4" id="<?= $ocId ?>Label">
                                        Захиалга <span class="rbt-text-semi-bold">#<?= h($order['order_number']) ?></span>
                                    </h5>
                                    <div class="rbt-badge <?= orderStatusBadgeClass($order['status']) ?> rbt-badge-border rbt-badge-md rbt-badge-rounded">
                                        <?= h($orderStatusLabels[$order['status']] ?? $order['status']) ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                            </div>
                            <div class="offcanvas-body">
                                <div class="inner-wrapper">
                                    <div class="inner-top">
                                        <nav class="side-nav w-100 mb--0">
                                            <ul class="rbt-minicart-wrapper">
                                                <?php foreach ($order['items'] as $item): ?>
                                                <li class="minicart-item">
                                                    <div class="thumbnail transparent-verticle-thumbnail">
                                                        <a href="#"><img src="<?= h(fixImageUrl($item['image'])) ?>" alt="<?= h($item['product_name_mn']) ?>"></a>
                                                    </div>
                                                    <div class="product-content">
                                                        <p class="rbt-title mb--8 h6 b2"><a href="#"><?= h($item['product_name_mn']) ?></a></p>
                                                        <?php if (!empty($item['variant_label'])): ?>
                                                        <span class="quantity"><?= h($item['variant_label']) ?></span><br>
                                                        <?php endif; ?>
                                                        <span class="quantity"><?= (int)$item['quantity'] ?>x <span class="price"><?= h(formatPrice($item['product_price'])) ?></span></span>
                                                    </div>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </nav>
                                    </div>
                                    <div class="rbt-minicart-footer">
                                        <div class="rbt-sidebar-widget p--0 bg-transparent mt--0">
                                            <div class="rbt-inner">
                                                <h2 class="b1 h6 mb--8"><?= $order['fulfillment'] === 'pickup' ? 'Хүлээж авах' : 'Хүргэлт' ?></h2>
                                                <?php if ($order['fulfillment'] === 'pickup'): ?>
                                                <div class="rbt-cart-subttotal"><p>Аргаа</p><p>Дэлгүүрээс авах</p></div>
                                                <?php else: ?>
                                                <?php if (!empty($order['district_name'])): ?>
                                                <div class="rbt-cart-subttotal"><p>Дүүрэг</p><p><?= h($order['district_name']) ?></p></div>
                                                <?php endif; ?>
                                                <?php if (!empty($order['khoroo_number'])): ?>
                                                <div class="rbt-cart-subttotal"><p>Хороо</p><p><?= (int)$order['khoroo_number'] ?>-р хороо</p></div>
                                                <?php endif; ?>
                                                <?php if (!empty($order['address'])): ?>
                                                <div class="rbt-cart-subttotal"><p>Хаяг</p><p class="text-end"><?= h($order['address']) ?></p></div>
                                                <?php endif; ?>
                                                <?php if (!empty($order['detail_address'])): ?>
                                                <div class="rbt-cart-subttotal"><p>Нэмэлт</p><p class="text-end"><?= h($order['detail_address']) ?></p></div>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                <div class="rbt-cart-subttotal"><p>Хүлээн авагч</p><p><?= h($order['customer_name']) ?></p></div>
                                                <div class="rbt-cart-subttotal"><p>Утас</p><p><?= h($order['customer_phone']) ?></p></div>
                                            </div>
                                        </div>
                                        <div class="rbt-sidebar-widget p--0 bg-transparent mt--0 mt_sm--8 mt_lg--8">
                                            <div class="rbt-inner">
                                                <h2 class="b1 h6 mb--8">Төлбөр</h2>
                                                <div class="rbt-cart-subttotal"><p>Төлбөрийн хэрэгсэл</p><p><?= h($paymentMethodLabels[$order['payment_method']] ?? $order['payment_method']) ?></p></div>
                                                <div class="rbt-cart-subttotal"><p>Төлбөрийн төлөв</p><p><?= $order['payment_status'] === 'paid' ? 'Төлөгдсөн' : ($order['payment_status'] === 'refunded' ? 'Буцаагдсан' : 'Хүлээгдэж буй') ?></p></div>
                                                <div class="rbt-cart-subttotal"><p>Барааны дүн</p><p><?= h(formatPrice($order['subtotal'])) ?></p></div>
                                                <?php if ((float)$order['delivery_fee'] > 0): ?>
                                                <div class="rbt-cart-subttotal"><p>Хүргэлтийн төлбөр</p><p><?= h(formatPrice($order['delivery_fee'])) ?></p></div>
                                                <?php endif; ?>
                                                <?php if ((float)$order['cargo_fee'] > 0): ?>
                                                <div class="rbt-cart-subttotal"><p>Карго төлбөр</p><p><?= h(formatPrice($order['cargo_fee'])) ?></p></div>
                                                <?php endif; ?>
                                                <hr class="mb--8 mt--8 rbt-bg-color-gray-200">
                                                <div class="rbt-cart-subttotal mb--12">
                                                    <p class="subtotal"><strong>Нийт дүн</strong></p>
                                                    <p class="price"><?= h(formatPrice($order['total'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($order['payment_status'] === 'pending' && $order['status'] === 'pending'): ?>
                                        <div class="checkout-btn mt--20">
                                            <a href="<?= h(url('order-thanks?order=' . urlencode($order['order_number']))) ?>" class="rbt-btn w-100 text-center">
                                                <span class="btn-text">Төлбөр төлөх</span>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
