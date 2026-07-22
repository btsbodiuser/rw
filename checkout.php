<?php
require_once __DIR__ . '/includes/config.php';

// Require login before checkout
if (!isLoggedIn()) {
    header('Location: ' . url('login.php?redirect=' . urlencode(url('checkout.php'))));
    exit;
}

// Redirect to cart if empty
if (empty($_SESSION['cart'])) {
    header('Location: ' . url('cart.php'));
    exit;
}

$page_title = 'Захиалга хийх';
require_once __DIR__ . '/includes/header.php';

$cartItems   = $_SESSION['cart'] ?? [];
$subtotal    = cartTotal();
$cartCount   = cartCount();
$sessionUser = getSessionUser() ?? [];
$userName    = htmlspecialchars($sessionUser['name']  ?? '');
$userPhone   = htmlspecialchars($sessionUser['phone'] ?? '');

// Active payment methods (from backend settings).
$payQpay     = sBool('payment_qpay_enabled',     true);
$payBonum    = sBool('payment_bonum_enabled',    false);
$payStorepay = sBool('payment_storepay_enabled', false);
$payTransfer = sBool('payment_transfer_enabled', true);

// Pick default: first enabled method in display order
$defaultPay = $payQpay ? 'qpay'
            : ($payBonum ? 'bonum'
            : ($payStorepay ? 'storepay'
            : ($payTransfer ? 'transfer' : '')));

// Bank transfer account details (shown after order creation)
$bankName          = s('bank_name');
$bankAccountNumber = s('bank_account_number');
$bankAccountName   = s('bank_account_name');

// Delivery fee settings (same logic as CheckoutPage.tsx's shippingFee calc)
$deliveryFeeEnabled    = sBool('delivery_fee_enabled', true);
$deliveryFeeAmount     = (float) s('delivery_fee', '5000');
$freeDeliveryThreshold = (float) s('free_delivery_threshold', '50000');
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Захиалга хийх</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('cart.php') ?>" class="h6 link">Сагс</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Захиалга хийх</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- Check Out -->
        <section class="flat-spacing">
            <div class="container">
                <div class="row">

                    <!-- Left: Order Form -->
                    <div class="col-lg-8">
                        <div class="tf-page-checkout mb-lg-0">
                            <form id="checkout-form" class="tf-checkout-cart-main" novalidate>

                                <!-- Customer Info -->
                                <div class="box-ip-checkout">
                                    <h2 class="title type-semibold">Хувийн мэдээлэл</h2>
                                    <div class="form_content">
                                        <div class="cols tf-grid-layout sm-col-2">
                                            <fieldset>
                                                <input type="text"
                                                    id="customer_name"
                                                    name="customer_name"
                                                    placeholder="Нэр (заавал) *"
                                                    required
                                                    autocomplete="name"
                                                    value="<?= $userName ?>">
                                                <div class="invalid-feedback text-danger text-small" id="err-name"></div>
                                            </fieldset>
                                            <fieldset>
                                                <input type="tel"
                                                    id="customer_phone"
                                                    name="customer_phone"
                                                    placeholder="Утасны дугаар (заавал) *"
                                                    required
                                                    maxlength="8"
                                                    pattern="[0-9]{8}"
                                                    autocomplete="tel"
                                                    value="<?= $userPhone ?>">
                                                <div class="invalid-feedback text-danger text-small" id="err-phone"></div>
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fulfillment -->
                                <div class="box-ip-checkout">
                                    <h2 class="title type-semibold">Хүлээн авах арга</h2>
                                    <div class="form_content">
                                        <div class="d-flex gap-4 flex-wrap">
                                            <label class="check-ship mb-0" for="fulfillment-delivery">
                                                <input type="radio"
                                                    id="fulfillment-delivery"
                                                    name="fulfillment"
                                                    value="delivery"
                                                    class="tf-check-rounded style-2 line-black"
                                                    checked>
                                                <span class="text h6">
                                                    <i class="icon icon-truck me-1"></i> Хүргэлт
                                                </span>
                                            </label>
                                            <label class="check-ship mb-0" for="fulfillment-pickup">
                                                <input type="radio"
                                                    id="fulfillment-pickup"
                                                    name="fulfillment"
                                                    value="pickup"
                                                    class="tf-check-rounded style-2 line-black">
                                                <span class="text h6">
                                                    <i class="icon icon-map-pin me-1"></i> Өөрөө авах
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery Fields (shown when delivery is selected) -->
                                <div id="delivery-fields" class="box-ip-checkout">
                                    <h2 class="title type-semibold">Хүргэлтийн хаяг</h2>
                                    <div class="form_content">

                                        <!-- Saved addresses (populated by JS if the customer has any) -->
                                        <div id="saved-addresses-wrap" style="display:none;margin-bottom:8px;">
                                            <div id="saved-addresses-list" class="d-flex flex-column gap-2 mb-2"></div>
                                            <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                                <input type="radio" name="saved_address" id="saved-address-new" value="new" class="tf-check-rounded style-2">
                                                <span class="h6">+ Шинэ хаяг оруулах</span>
                                            </label>
                                        </div>

                                        <div id="new-address-fields" class="d-flex flex-column gap-3">
                                            <div class="cols tf-grid-layout sm-col-2">
                                                <fieldset>
                                                    <div class="tf-select">
                                                        <select id="district_id" name="district_id" class="w-100">
                                                            <option value="" disabled selected>Дүүрэг сонгох...</option>
                                                        </select>
                                                    </div>
                                                    <div class="invalid-feedback text-danger text-small" id="err-district"></div>
                                                </fieldset>
                                                <fieldset>
                                                    <div class="tf-select">
                                                        <select id="khoroo_id" name="khoroo_id" class="w-100" disabled>
                                                            <option value="" disabled selected>Хороо сонгох...</option>
                                                        </select>
                                                    </div>
                                                    <div class="invalid-feedback text-danger text-small" id="err-khoroo"></div>
                                                </fieldset>
                                            </div>
                                            <fieldset>
                                                <input type="text"
                                                    id="address"
                                                    name="address"
                                                    placeholder="Гудамж, байр, орц, давхар *"
                                                    autocomplete="street-address">
                                                <div class="invalid-feedback text-danger text-small" id="err-address"></div>
                                            </fieldset>
                                            <fieldset>
                                                <input type="text"
                                                    id="detail_address"
                                                    name="detail_address"
                                                    placeholder="Нэмэлт мэдээлэл (тоот, орцны код гэх мэт)"
                                                    autocomplete="address-line2">
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div class="box-ip-payment">
                                    <h2 class="title type-semibold">Төлбөрийн хэлбэр</h2>
                                    <div class="payment-method-box" id="payment-method-box">

                                        <?php if ($payQpay): ?>
                                        <!-- QPay -->
                                        <div class="payment_accordion">
                                            <label for="pay-qpay" class="payment_check checkbox-wrap">
                                                <input type="radio" name="payment_method" class="tf-check-rounded style-2" id="pay-qpay" value="qpay" <?= $defaultPay === 'qpay' ? 'checked' : '' ?>>
                                                <span class="pay-title d-flex align-items-center gap-2">
                                                    <span class="badge bg-primary text-white px-2 py-1" style="font-size:0.8rem;border-radius:4px;">QPay</span>
                                                    QPay — QR код
                                                </span>
                                            </label>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($payBonum): ?>
                                        <!-- Bonum -->
                                        <div class="payment_accordion">
                                            <label for="pay-bonum" class="payment_check checkbox-wrap">
                                                <input type="radio" name="payment_method" class="tf-check-rounded style-2" id="pay-bonum" value="bonum" <?= $defaultPay === 'bonum' ? 'checked' : '' ?>>
                                                <span class="pay-title d-flex align-items-center gap-2">
                                                    <span class="badge text-white px-2 py-1" style="font-size:0.8rem;border-radius:4px;background:#7c3aed;">Bonum</span>
                                                    Bonum — Хэтэвч
                                                </span>
                                            </label>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($payStorepay): ?>
                                        <!-- StorePay -->
                                        <div class="payment_accordion">
                                            <label for="pay-storepay" class="payment_check checkbox-wrap">
                                                <input type="radio" name="payment_method" class="tf-check-rounded style-2" id="pay-storepay" value="storepay" <?= $defaultPay === 'storepay' ? 'checked' : '' ?>>
                                                <span class="pay-title d-flex align-items-center gap-2">
                                                    <span class="badge text-white px-2 py-1" style="font-size:0.8rem;border-radius:4px;background:#f59e0b;">SP</span>
                                                    StorePay — Зээлээр авах
                                                </span>
                                            </label>
                                            <div id="storepay-phone-wrap" style="display:none;padding:12px 0 4px 28px;">
                                                <fieldset>
                                                    <input type="tel"
                                                        id="storepay_phone"
                                                        name="storepay_phone"
                                                        placeholder="StorePay утасны дугаар *"
                                                        maxlength="8"
                                                        pattern="[0-9]{8}">
                                                    <div class="invalid-feedback text-danger text-small" id="err-storepay-phone"></div>
                                                </fieldset>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($payTransfer): ?>
                                        <!-- Transfer -->
                                        <div class="payment_accordion">
                                            <label for="pay-transfer" class="payment_check checkbox-wrap">
                                                <input type="radio" name="payment_method" class="tf-check-rounded style-2" id="pay-transfer" value="transfer" <?= $defaultPay === 'transfer' ? 'checked' : '' ?>>
                                                <span class="pay-title d-flex align-items-center gap-2">
                                                    <i class="icon icon-arrows-left-right" style="font-size:1.1rem;"></i>
                                                    Шилжүүлэг
                                                </span>
                                            </label>
                                        </div>
                                        <?php endif; ?>

                                    </div><!-- /#payment-method-box -->
                                </div>

                                <!-- Notes -->
                                <div class="box-ip-checkout">
                                    <h2 class="title type-semibold">Нэмэлт тэмдэглэл</h2>
                                    <div class="form_content">
                                        <textarea id="notes" name="notes" placeholder="Захиалгын талаарх тэмдэглэл..." style="height:120px;"></textarea>
                                    </div>
                                </div>

                                <!-- Error Message -->
                                <div id="checkout-error" class="alert" style="display:none;padding:12px 16px;border-radius:6px;background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;"></div>

                                <!-- Submit -->
                                <div class="button_submit">
                                    <button type="submit" id="btn-submit" class="tf-btn animate-btn w-100">
                                        <span id="btn-submit-text">Захиалга өгөх</span>
                                        <i class="icon icon-arrow-right"></i>
                                    </button>
                                </div>

                            </form>

                            <!-- Bank Transfer Payment Instructions (shown after order created via transfer) -->
                            <div id="transfer-payment-section" style="display:none;">
                                <button type="button" id="btn-transfer-back" class="link h6 text-main" style="border:none;background:none;padding:0;margin-bottom:20px;display:flex;align-items:center;gap:6px;">
                                    <i class="icon icon-caret-left"></i> Буцах
                                </button>

                                <h2 class="title type-semibold text-center mb-4">Банкны шилжүүлгээр төлөх</h2>

                                <div style="max-width:420px;margin:0 auto;">
                                    <div class="text-center mb-4">
                                        <p class="h6 text-main mb-1">Төлөх дүн</p>
                                        <p class="fw-bold" style="font-size:2rem;" id="transfer-amount">0₮</p>
                                        <p class="h6 text-main mt-1">Захиалга #<span id="transfer-order-number"></span></p>
                                    </div>

                                    <div style="background:#f9fafb;border-radius:10px;padding:20px;margin-bottom:20px;">
                                        <?php if ($bankName): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="h6 text-main">Банк</span>
                                            <span class="h6 fw-semibold"><?= htmlspecialchars($bankName) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($bankAccountNumber): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="h6 text-main">Дансны дугаар</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="h6 fw-semibold" style="font-family:monospace;"><?= htmlspecialchars($bankAccountNumber) ?></span>
                                                <button type="button" class="btn-copy-field" data-copy="<?= htmlspecialchars($bankAccountNumber) ?>" style="border:none;background:none;color:#2563eb;cursor:pointer;padding:2px;font-size:.8rem;text-decoration:underline;">
                                                    Хуулах
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($bankAccountName): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="h6 text-main">Дансны нэр</span>
                                            <span class="h6 fw-semibold"><?= htmlspecialchars($bankAccountName) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-center pt-3" style="border-top:1px solid #e5e7eb;">
                                            <span class="h6 text-main">Гүйлгээний утга</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="h6 fw-semibold text-primary" style="font-family:monospace;" id="transfer-ref"></span>
                                                <button type="button" class="btn-copy-field" id="btn-copy-ref" style="border:none;background:none;color:#2563eb;cursor:pointer;padding:2px;font-size:.8rem;text-decoration:underline;">
                                                    Хуулах
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px;margin-bottom:20px;">
                                        <p class="h6 fw-medium mb-2" style="color:#92400e;">Анхааруулга</p>
                                        <ul class="text-small" style="color:#b45309;margin:0;padding-left:18px;">
                                            <li>Гүйлгээний утга дээр захиалгын дугаараа <strong>заавал</strong> бичнэ үү</li>
                                            <li>Шилжүүлсний дараа 1 цагийн дотор баталгаажна</li>
                                            <li>Асуудал гарвал админтай холбогдоно уу</li>
                                        </ul>
                                    </div>

                                    <button type="button" id="btn-transfer-done" class="tf-btn animate-btn w-100">
                                        Шилжүүлэг хийсэн
                                    </button>
                                    <p class="text-small text-main text-center mt-2">Шилжүүлэг хийсний дараа дарна уу</p>
                                </div>
                            </div>

                            <!-- QPay Payment (shown after order created via QPay) -->
                            <div id="qpay-payment-section" style="display:none;">
                                <button type="button" id="btn-qpay-back" class="link h6 text-main" style="border:none;background:none;padding:0;margin-bottom:20px;display:flex;align-items:center;gap:6px;">
                                    <i class="icon icon-caret-left"></i> Буцах
                                </button>

                                <h2 class="title type-semibold text-center mb-4">QPay-ээр төлөх</h2>

                                <div id="qpay-error-banner" style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px;margin-bottom:16px;max-width:420px;margin-left:auto;margin-right:auto;">
                                    <p class="h6 mb-1" style="color:#92400e;" id="qpay-error-text"></p>
                                    <p class="text-small" style="color:#b45309;">Захиалга үүссэн. Админтай холбогдоно уу.</p>
                                </div>

                                <div style="max-width:420px;margin:0 auto;">
                                    <div id="qpay-qr-box" style="background:#f9fafb;border-radius:10px;padding:24px;margin-bottom:16px;position:relative;display:none;">
                                        <div id="qpay-expired-overlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.92);border-radius:10px;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:16px;text-align:center;">
                                            <p class="h6 fw-medium text-danger mb-0">QR кодны хугацаа дууслаа</p>
                                            <button type="button" id="btn-qpay-refresh" class="tf-btn type-small">Шинэ QR авах</button>
                                        </div>
                                        <img id="qpay-qr-img" src="" alt="QPay QR" style="max-width:220px;width:100%;margin:0 auto;display:block;">
                                        <p class="text-small text-center mt-2" id="qpay-countdown" style="color:#f97316;display:none;"></p>
                                    </div>
                                    <div id="qpay-loading" class="text-center" style="padding:24px 0;">
                                        <div class="spinner-border" role="status"></div>
                                        <p class="h6 text-main mt-3">QR код үүсгэж байна...</p>
                                    </div>

                                    <div class="text-center mb-4">
                                        <p class="h6 text-main mb-1">Төлөх дүн</p>
                                        <p class="fw-bold" style="font-size:2rem;" id="qpay-amount">0₮</p>
                                        <p class="h6 text-main mt-1">Захиалга #<span id="qpay-order-number"></span></p>
                                    </div>

                                    <div id="qpay-bank-apps" style="display:none;margin-bottom:16px;">
                                        <p class="text-small fw-medium text-center mb-3">Банкны апп-аар төлөх</p>
                                        <div id="qpay-bank-apps-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;"></div>
                                        <p class="text-small text-center mt-2" style="color:#c2410c;">⚠️ Банкны апп-д төлсний дараа энэ хуудас руу буцаж ирнэ үү</p>
                                    </div>

                                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:16px;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span style="width:8px;height:8px;border-radius:50%;background:#3b82f6;display:inline-block;"></span>
                                            <span class="h6 fw-medium mb-0" style="color:#1e40af;">Төлбөр хүлээж байна...</span>
                                        </div>
                                        <p class="text-small mb-0" style="color:#1d4ed8;">
                                            QPay апп-аас QR кодыг уншуулж эсвэл дээрх банкны апп дээр дарж төлбөрөө төлнө үү. Төлбөр төлөгдсөний дараа автоматаар баталгаажна.
                                        </p>
                                    </div>

                                    <button type="button" id="btn-qpay-done" class="tf-btn animate-btn w-100">
                                        <span class="btn-text">Төлбөр төлсөн</span>
                                        <span class="btn-loading" style="display:none;">Шалгаж байна...</span>
                                    </button>
                                    <p class="text-small text-main text-center mt-2">Автоматаар шалгагдана. Хэрэв шалгагдахгүй бол дарна уу.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Order Summary -->
                    <div class="col-lg-4">
                        <div class="fl-sidebar-cart sticky-top">
                            <div class="box-your-order">
                                <h2 class="title type-semibold">Таны захиалга</h2>

                                <ul class="list-order-product">
                                    <?php foreach ($cartItems as $item):
                                        $name     = htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '');
                                        $slug     = htmlspecialchars($item['slug'] ?? '');
                                        $imgSrc   = htmlspecialchars(fixImageUrl($item['image'] ?? null));
                                        $price    = (float)$item['price'];
                                        $qty      = (int)$item['qty'];
                                        $label    = htmlspecialchars($item['variant_label'] ?? '');
                                    ?>
                                    <li class="order-item">
                                        <a href="<?= url('product/' . $slug) ?>" class="img-prd">
                                            <img src="<?= $imgSrc ?>" alt="<?= $name ?>">
                                        </a>
                                        <div class="infor-prd">
                                            <h6 class="prd_name">
                                                <a href="<?= url('product/' . $slug) ?>" class="link"><?= $name ?></a>
                                            </h6>
                                            <div class="prd_select text-small">
                                                <?php if ($label): ?><?= $label ?> — <?php endif; ?>
                                                <?= $qty ?> ширхэг
                                            </div>
                                        </div>
                                        <p class="price-prd h6"><?= formatPrice($price * $qty) ?></p>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>

                                <ul class="list-total">
                                    <li class="total-item h6">
                                        <span class="fw-bold text-black">Дэд нийлбэр</span>
                                        <span id="summary-subtotal"><?= formatPrice($subtotal) ?></span>
                                    </li>
                                    <li class="total-item h6">
                                        <span class="fw-bold text-black">Хүргэлт</span>
                                        <span id="summary-delivery">Тооцоолж байна...</span>
                                    </li>
                                </ul>
                                <p class="text-small mb-0" id="free-shipping-msg" style="display:none;"></p>

                                <div class="last-total h5 fw-medium text-black">
                                    <span>Нийт</span>
                                    <span id="summary-total"><?= formatPrice($subtotal) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <!-- /Check Out -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
    'use strict';

    const BASE  = <?= json_encode(getBaseUrl()) ?>;
    const TOKEN = <?= json_encode($_SESSION['token'] ?? '') ?>;
    const SUBTOTAL = <?= json_encode($subtotal) ?>;
    const SETTINGS = {
        delivery_fee_enabled: <?= json_encode($deliveryFeeEnabled) ?>,
        delivery_fee: <?= json_encode($deliveryFeeAmount) ?>,
        free_delivery_threshold: <?= json_encode($freeDeliveryThreshold) ?>
    };

    // ── Districts / Khoroos ──────────────────────────────────────────────────
    let districtsData = [];

    function loadDistricts() {
        fetch(BASE + 'backend/api/districts.php')
            .then(r => r.json())
            .then(data => {
                districtsData = data.districts || [];
                const sel = document.getElementById('district_id');
                districtsData.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name_mn || d.name;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    loadDistricts();

    document.getElementById('district_id').addEventListener('change', function () {
        const did = parseInt(this.value);
        const kSel = document.getElementById('khoroo_id');
        kSel.innerHTML = '<option value="" disabled selected>Хороо сонгох...</option>';
        kSel.disabled = true;

        const district = districtsData.find(d => d.id === did);
        if (!district || !district.khoroos || district.khoroos.length === 0) return;

        district.khoroos.forEach(k => {
            const opt = document.createElement('option');
            opt.value = k.id;
            opt.textContent = (k.number ? k.number + '-р хороо' : '') + (k.name ? ' ' + k.name : '');
            kSel.appendChild(opt);
        });
        kSel.disabled = false;
    });

    // ── Saved addresses ──────────────────────────────────────────────────────
    let savedAddresses = [];
    let selectedSavedAddressId = null; // null => use the new-address fields

    function applyAddressModeUI() {
        document.getElementById('new-address-fields').style.display = selectedSavedAddressId !== null ? 'none' : '';
    }

    function loadSavedAddresses() {
        if (!TOKEN) return;
        fetch(BASE + 'backend/api/addresses.php', {
            headers: { 'Authorization': 'Bearer ' + TOKEN }
        })
        .then(r => r.json())
        .then(data => {
            savedAddresses = data.addresses || [];
            if (savedAddresses.length === 0) return;

            const wrap = document.getElementById('saved-addresses-wrap');
            const list = document.getElementById('saved-addresses-list');
            wrap.style.display = '';

            list.innerHTML = savedAddresses.map(a => {
                const khoroo = a.khoroo_number ? (a.khoroo_number + '-р хороо' + (a.khoroo_name ? ' ' + a.khoroo_name : '')) : '';
                return `<label class="d-flex align-items-start gap-2" style="cursor:pointer;">
                    <input type="radio" name="saved_address" class="tf-check-rounded style-2 mt-1" value="${a.id}">
                    <span class="h6" style="font-weight:400;">
                        <strong>${a.label || 'Хаяг'}</strong>${a.is_default == 1 ? ' <span class="text-primary" style="font-size:.75rem;">(Үндсэн)</span>' : ''}<br>
                        <span class="text-main">${a.district_name || ''}${khoroo ? ', ' + khoroo : ''} — ${a.address || ''}${a.detail_address ? ', ' + a.detail_address : ''}</span>
                    </span>
                </label>`;
            }).join('');

            // Default selection: the customer's default address, else the first
            const defaultAddr = savedAddresses.find(a => a.is_default == 1) || savedAddresses[0];
            selectedSavedAddressId = String(defaultAddr.id);
            const radio = list.querySelector('input[value="' + defaultAddr.id + '"]');
            if (radio) radio.checked = true;
            applyAddressModeUI();

            wrap.querySelectorAll('input[name="saved_address"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    selectedSavedAddressId = this.value === 'new' ? null : this.value;
                    applyAddressModeUI();
                });
            });
        })
        .catch(() => {});
    }
    loadSavedAddresses();

    // ── Fulfillment toggle ───────────────────────────────────────────────────
    document.querySelectorAll('input[name="fulfillment"]').forEach(radio => {
        radio.addEventListener('change', function () {
            const deliveryFields = document.getElementById('delivery-fields');
            if (this.value === 'delivery') {
                deliveryFields.style.display = '';
            } else {
                deliveryFields.style.display = 'none';
            }
            updateDeliveryDisplay();
        });
    });

    // Same logic as CheckoutPage.tsx's shippingFee calculation:
    // pickup or delivery-fee disabled => 0; over free-delivery threshold => 0; else the flat fee.
    function computeShippingFee() {
        const isDelivery = document.querySelector('input[name="fulfillment"]:checked')?.value === 'delivery';
        if (!isDelivery || !SETTINGS.delivery_fee_enabled) return 0;
        return SUBTOTAL >= SETTINGS.free_delivery_threshold ? 0 : SETTINGS.delivery_fee;
    }

    function updateDeliveryDisplay() {
        const isDelivery = document.querySelector('input[name="fulfillment"]:checked')?.value === 'delivery';
        const delSpan  = document.getElementById('summary-delivery');
        const totalSpan = document.getElementById('summary-total');
        const freeMsg  = document.getElementById('free-shipping-msg');
        if (!delSpan || !totalSpan) return;

        let fee = 0;
        if (!isDelivery) {
            delSpan.textContent = 'Үнэгүй';
        } else if (!SETTINGS.delivery_fee_enabled) {
            delSpan.textContent = 'Хүргэлтийн үед бодогдоно';
        } else {
            fee = computeShippingFee();
            delSpan.textContent = fee === 0 ? 'Үнэгүй' : fee.toLocaleString() + '₮';
        }

        totalSpan.textContent = (SUBTOTAL + fee).toLocaleString() + '₮';

        if (freeMsg) {
            if (isDelivery && SETTINGS.delivery_fee_enabled && SETTINGS.free_delivery_threshold > 0) {
                freeMsg.style.display = '';
                if (fee === 0) {
                    freeMsg.style.color = '#16a34a';
                    freeMsg.textContent = SETTINGS.free_delivery_threshold.toLocaleString() + '₮-аас дээш захиалга үнэгүй хүргэлттэй';
                } else {
                    freeMsg.style.color = '#6b7280';
                    freeMsg.textContent = (SETTINGS.free_delivery_threshold - SUBTOTAL).toLocaleString() + '₮ нэмж авбал үнэгүй хүргэлттэй';
                }
            } else {
                freeMsg.style.display = 'none';
            }
        }
    }
    updateDeliveryDisplay();

    // ── StorePay phone toggle ────────────────────────────────────────────────
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function () {
            const wrap = document.getElementById('storepay-phone-wrap');
            if (wrap) {
                wrap.style.display = this.value === 'storepay' ? '' : 'none';
            }
        });
    });

    // ── Validation helpers ───────────────────────────────────────────────────
    function showErr(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.textContent = msg; el.style.display = msg ? 'block' : 'none'; }
    }

    function clearErrors() {
        ['err-name', 'err-phone', 'err-district', 'err-khoroo', 'err-address', 'err-storepay-phone'].forEach(id => showErr(id, ''));
        const box = document.getElementById('checkout-error');
        if (box) { box.style.display = 'none'; box.textContent = ''; }
    }

    function showGlobalError(msg) {
        const box = document.getElementById('checkout-error');
        if (box) { box.textContent = msg; box.style.display = 'block'; }
    }

    function validate() {
        let valid = true;

        const name  = document.getElementById('customer_name').value.trim();
        const phone = document.getElementById('customer_phone').value.trim().replace(/\D/g, '');
        const fulfillment = document.querySelector('input[name="fulfillment"]:checked')?.value;
        const payment = document.querySelector('input[name="payment_method"]:checked')?.value;

        if (!name) {
            showErr('err-name', 'Нэр оруулна уу');
            valid = false;
        }
        if (!phone || phone.length !== 8) {
            showErr('err-phone', '8 оронтой утасны дугаар оруулна уу');
            valid = false;
        }

        if (fulfillment === 'delivery' && selectedSavedAddressId === null) {
            const did = document.getElementById('district_id').value;
            const kid = document.getElementById('khoroo_id').value;
            const addr = document.getElementById('address').value.trim();

            if (!did) { showErr('err-district', 'Дүүрэг сонгоно уу'); valid = false; }
            if (!kid) { showErr('err-khoroo', 'Хороо сонгоно уу'); valid = false; }
            if (!addr) { showErr('err-address', 'Хаяг оруулна уу'); valid = false; }
        }

        if (payment === 'storepay') {
            const spPhone = document.getElementById('storepay_phone').value.trim().replace(/\D/g, '');
            if (!spPhone || spPhone.length !== 8) {
                showErr('err-storepay-phone', 'StorePay утасны дугаар (8 оронтой) оруулна уу');
                valid = false;
            }
        }

        return valid;
    }

    // ── Build order payload ──────────────────────────────────────────────────
    function buildPayload() {
        const fulfillment = document.querySelector('input[name="fulfillment"]:checked')?.value || 'delivery';
        const payment     = document.querySelector('input[name="payment_method"]:checked')?.value || 'qpay';

        const payload = {
            customer_name:  document.getElementById('customer_name').value.trim(),
            customer_phone: document.getElementById('customer_phone').value.trim().replace(/\D/g, ''),
            fulfillment:    fulfillment,
            payment_method: payment,
            notes:          document.getElementById('notes').value.trim(),
            items: <?= json_encode(array_map(fn($i) => [
                'product_id' => (int)$i['product_id'],
                'variant_id' => (int)($i['variant_id'] ?? 0) ?: null,
                'quantity'   => (int)$i['qty'],
            ], $cartItems)) ?>
        };

        if (fulfillment === 'delivery') {
            if (selectedSavedAddressId !== null) {
                const saved = savedAddresses.find(a => String(a.id) === selectedSavedAddressId);
                if (saved) {
                    payload.district_id    = saved.district_id;
                    payload.khoroo_id      = saved.khoroo_id;
                    payload.address        = saved.address;
                    payload.detail_address = saved.detail_address || '';
                }
            } else {
                payload.district_id    = parseInt(document.getElementById('district_id').value) || null;
                payload.khoroo_id      = parseInt(document.getElementById('khoroo_id').value) || null;
                payload.address        = document.getElementById('address').value.trim();
                payload.detail_address = document.getElementById('detail_address').value.trim();
            }
        }

        return payload;
    }

    // ── QPay flow (inline, not a popup — see note above on why) ────────────────
    let qpayCheckInterval  = null;
    let qpayCountdownTimer = null;
    let qpaySecondsLeft    = null;
    let currentQpayOrder   = '';
    let currentQpayTotal   = 0;

    function formatQpayCountdown(secs) {
        const m = Math.floor(secs / 60);
        const s = String(secs % 60).padStart(2, '0');
        return 'QR дуусахад: ' + m + ':' + s;
    }

    function stopQpayCountdown() {
        if (qpayCountdownTimer) { clearInterval(qpayCountdownTimer); qpayCountdownTimer = null; }
    }

    function startQpayCountdown() {
        stopQpayCountdown();
        qpaySecondsLeft = 30 * 60;
        const countdownEl = document.getElementById('qpay-countdown');
        const overlayEl   = document.getElementById('qpay-expired-overlay');
        overlayEl.style.display = 'none';

        qpayCountdownTimer = setInterval(() => {
            qpaySecondsLeft = Math.max(0, qpaySecondsLeft - 1);
            if (qpaySecondsLeft <= 300) {
                countdownEl.style.display = '';
                countdownEl.textContent = formatQpayCountdown(qpaySecondsLeft);
            }
            if (qpaySecondsLeft <= 0) {
                overlayEl.style.display = 'flex';
                stopQpayCountdown();
            }
        }, 1000);
    }

    function stopQpayPoll() {
        if (qpayCheckInterval) { clearInterval(qpayCheckInterval); qpayCheckInterval = null; }
    }

    function renderQpayBankApps(urls) {
        const wrap = document.getElementById('qpay-bank-apps');
        const grid = document.getElementById('qpay-bank-apps-grid');
        if (!urls || urls.length === 0) { wrap.style.display = 'none'; return; }
        grid.innerHTML = urls.map(u => `
            <a href="${u.link}" target="_blank" rel="noopener noreferrer"
               style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;">
                <img src="${u.logo}" alt="${u.description}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">
                <span class="text-small text-center" style="font-size:10px;line-height:1.2;color:#4b5563;">${u.description}</span>
            </a>`).join('');
        wrap.style.display = '';
    }

    function createQpayInvoice(orderNumber, total) {
        document.getElementById('qpay-loading').style.display = '';
        document.getElementById('qpay-qr-box').style.display = 'none';
        document.getElementById('qpay-error-banner').style.display = 'none';

        return fetch(BASE + 'backend/api/qpay.php?action=create-invoice', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_number: orderNumber,
                amount: total,
                description: 'Захиалга #' + orderNumber
            })
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('qpay-loading').style.display = 'none';

            if (data.error || !data.qr_image) {
                document.getElementById('qpay-error-text').textContent = data.error || 'QR код үүсгэхэд алдаа гарлаа';
                document.getElementById('qpay-error-banner').style.display = '';
                return null;
            }

            document.getElementById('qpay-qr-img').src = 'data:image/png;base64,' + data.qr_image;
            document.getElementById('qpay-qr-box').style.display = '';
            renderQpayBankApps(data.urls);
            startQpayCountdown();

            stopQpayPoll();
            const invoiceId = data.invoice_id;
            qpayCheckInterval = setInterval(() => checkQpayPayment(invoiceId), 3000);
            return invoiceId;
        })
        .catch(() => {
            document.getElementById('qpay-loading').style.display = 'none';
            document.getElementById('qpay-error-text').textContent = 'Сервертэй холбогдоход алдаа гарлаа. Дахин оролдоно уу.';
            document.getElementById('qpay-error-banner').style.display = '';
            return null;
        });
    }

    function checkQpayPayment(invoiceId) {
        return fetch(BASE + 'backend/api/qpay.php?action=check-payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: invoiceId })
        })
        .then(r => r.json())
        .then(check => {
            if (check.paid) {
                stopQpayPoll();
                stopQpayCountdown();
                window.location.href = BASE + 'track-order?order=' + encodeURIComponent(currentQpayOrder);
            }
            return check.paid;
        })
        .catch(() => false);
    }

    // Immediately re-check when the customer returns from their banking app
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && currentQpayInvoiceId) {
            checkQpayPayment(currentQpayInvoiceId);
        }
    });
    let currentQpayInvoiceId = null;

    function startQpayFlow(orderNumber, total) {
        currentQpayOrder = orderNumber;
        currentQpayTotal = total;
        document.getElementById('qpay-amount').textContent = total.toLocaleString('mn-MN') + '₮';
        document.getElementById('qpay-order-number').textContent = orderNumber;

        document.getElementById('checkout-form').style.display = 'none';
        document.getElementById('qpay-payment-section').style.display = '';
        document.getElementById('qpay-payment-section').scrollIntoView({ behavior: 'smooth', block: 'start' });

        createQpayInvoice(orderNumber, total).then(invoiceId => { currentQpayInvoiceId = invoiceId; });
    }

    document.getElementById('btn-qpay-refresh').addEventListener('click', function () {
        createQpayInvoice(currentQpayOrder, currentQpayTotal).then(invoiceId => { currentQpayInvoiceId = invoiceId; });
    });

    document.getElementById('btn-qpay-done').addEventListener('click', function () {
        if (!currentQpayInvoiceId) return;
        const btn = this;
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-loading').style.display = '';
        btn.disabled = true;
        checkQpayPayment(currentQpayInvoiceId).finally(() => {
            btn.querySelector('.btn-text').style.display = '';
            btn.querySelector('.btn-loading').style.display = 'none';
            btn.disabled = false;
        });
    });

    document.getElementById('btn-qpay-back').addEventListener('click', function () {
        stopQpayPoll();
        stopQpayCountdown();
        currentQpayInvoiceId = null;
        if (currentQpayOrder) {
            fetch(BASE + 'backend/api/orders.php?action=cancel', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_number: currentQpayOrder })
            }).catch(() => {});
        }
        currentQpayOrder = '';
        document.getElementById('qpay-payment-section').style.display = 'none';
        document.getElementById('checkout-form').style.display = '';
    });

    // ── Bonum flow ───────────────────────────────────────────────────────────
    function startBonumFlow(orderNumber, total) {
        fetch(BASE + 'backend/api/bonum.php?action=create-invoice', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_number: orderNumber,
                amount: total,
                description: 'Захиалга #' + orderNumber
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.follow_up_link) {
                window.location.href = data.follow_up_link;
            } else {
                window.location.href = BASE + 'track-order?order=' + encodeURIComponent(orderNumber);
            }
        })
        .catch(() => {
            window.location.href = BASE + 'track-order?order=' + encodeURIComponent(orderNumber);
        });
    }

    // ── StorePay flow ────────────────────────────────────────────────────────
    function startStorePayFlow(orderNumber, total) {
        const spPhone = document.getElementById('storepay_phone').value.trim().replace(/\D/g, '');
        fetch(BASE + 'backend/api/storepay.php?action=create-invoice', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_number:  orderNumber,
                amount:        total,
                mobile_number: spPhone,
                description:   'Захиалга #' + orderNumber
            })
        })
        .then(r => r.json())
        .then(() => {
            window.location.href = BASE + 'track-order?order=' + encodeURIComponent(orderNumber);
        })
        .catch(() => {
            window.location.href = BASE + 'track-order?order=' + encodeURIComponent(orderNumber);
        });
    }

    // ── Bank transfer flow ───────────────────────────────────────────────────
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const original = btn.textContent;
            btn.textContent = 'Хуулагдлаа';
            setTimeout(() => { btn.textContent = original; }, 1500);
        }).catch(() => {});
    }

    document.querySelectorAll('.btn-copy-field[data-copy]').forEach(btn => {
        btn.addEventListener('click', function () { copyToClipboard(this.dataset.copy, this); });
    });
    document.getElementById('btn-copy-ref')?.addEventListener('click', function () {
        copyToClipboard(document.getElementById('transfer-ref').textContent, this);
    });

    let currentTransferOrder = '';

    function showTransferPaymentSection(orderNumber, total) {
        currentTransferOrder = orderNumber;
        document.getElementById('transfer-amount').textContent = total.toLocaleString('mn-MN') + '₮';
        document.getElementById('transfer-order-number').textContent = orderNumber;
        document.getElementById('transfer-ref').textContent = orderNumber;

        document.getElementById('checkout-form').style.display = 'none';
        document.getElementById('transfer-payment-section').style.display = '';
        document.getElementById('transfer-payment-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('btn-transfer-back')?.addEventListener('click', function () {
        if (currentTransferOrder) {
            fetch(BASE + 'backend/api/orders.php?action=cancel', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_number: currentTransferOrder })
            }).catch(() => {});
        }
        currentTransferOrder = '';
        document.getElementById('transfer-payment-section').style.display = 'none';
        document.getElementById('checkout-form').style.display = '';
    });

    document.getElementById('btn-transfer-done')?.addEventListener('click', function () {
        window.location.href = BASE + 'track-order?order=' + encodeURIComponent(currentTransferOrder);
    });

    // ── Form submit ──────────────────────────────────────────────────────────
    document.getElementById('checkout-form').addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();

        if (!validate()) return;

        const btn = document.getElementById('btn-submit');
        const btnText = document.getElementById('btn-submit-text');
        btn.disabled = true;
        btnText.textContent = 'Захиалга боловсруулж байна...';

        const payload = buildPayload();

        const orderHeaders = { 'Content-Type': 'application/json' };
        if (TOKEN) orderHeaders['Authorization'] = 'Bearer ' + TOKEN;

        fetch(BASE + 'backend/api/orders.php', {
            method: 'POST',
            headers: orderHeaders,
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btnText.textContent = 'Захиалга өгөх';

            if (data.error || !data.success) {
                const msg = (data.errors && data.errors.length)
                    ? data.errors.join('; ')
                    : (data.error || 'Захиалга хийхэд алдаа гарлаа');
                showGlobalError(msg);
                return;
            }

            const orderNumber = data.order_number;
            const total       = data.total || 0;
            const payment     = payload.payment_method;

            // Clear session cart via AJAX (best-effort)
            fetch(BASE + 'cart-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear' })
            }).catch(() => {});

            if (payment === 'qpay') {
                startQpayFlow(orderNumber, total);
            } else if (payment === 'bonum') {
                startBonumFlow(orderNumber, total);
            } else if (payment === 'storepay') {
                startStorePayFlow(orderNumber, total);
            } else {
                // transfer
                showTransferPaymentSection(orderNumber, total);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btnText.textContent = 'Захиалга өгөх';
            showGlobalError('Сервертэй холбогдоход алдаа гарлаа. Дахин оролдоно уу.');
        });
    });

})();
</script>
