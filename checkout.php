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
                            <form id="checkout-form" novalidate>

                                <!-- Customer Info -->
                                <div class="box-ip-checkout estimate-shipping">
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
                                <div class="box-ip-checkout" style="margin-top:24px;">
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
                                <div id="delivery-fields" class="box-ip-checkout" style="margin-top:20px;">
                                    <h2 class="title type-semibold">Хүргэлтийн хаяг</h2>
                                    <div class="form_content">
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

                                <!-- Payment Method -->
                                <div class="box-ip-payment" style="margin-top:24px;">
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
                                <div class="box-ip-checkout" style="margin-top:24px;">
                                    <h2 class="title type-semibold">Нэмэлт тэмдэглэл</h2>
                                    <div class="form_content">
                                        <textarea id="notes" name="notes" placeholder="Захиалгын талаарх тэмдэглэл..." style="height:120px;"></textarea>
                                    </div>
                                </div>

                                <!-- Error Message -->
                                <div id="checkout-error" class="alert" style="display:none;margin-top:16px;padding:12px 16px;border-radius:6px;background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;"></div>

                                <!-- Submit -->
                                <div class="button_submit" style="margin-top:24px;">
                                    <button type="submit" id="btn-submit" class="tf-btn animate-btn w-100">
                                        <span id="btn-submit-text">Захиалга өгөх</span>
                                        <i class="icon icon-arrow-right"></i>
                                    </button>
                                </div>

                            </form>
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
                                        <span id="summary-delivery">Тооцоолох</span>
                                    </li>
                                </ul>

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

        <!-- QPay Modal -->
        <div class="modal fade modalCentered" id="qpay-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content" style="padding:32px;text-align:center;">
                    <div class="header d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-semibold mb-0">QPay — QR Код</h4>
                        <span class="icon-close icon-close-popup" data-bs-dismiss="modal" style="cursor:pointer;"></span>
                    </div>
                    <div id="qpay-content">
                        <div class="spinner-border" role="status" style="width:2.5rem;height:2.5rem;"></div>
                        <p class="h6 text-main mt-3">QR код ачааллаж байна...</p>
                    </div>
                    <div id="qpay-qr" style="display:none;">
                        <img id="qpay-qr-img" src="" alt="QPay QR" style="max-width:220px;width:100%;margin:0 auto 12px;">
                        <p class="h6 text-main mb-3">Ухаалаг утасны банкны апп-аар скан хийж төлнэ үү.</p>
                        <div id="qpay-check-status" style="margin-top:12px;">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <span class="h6 text-main">Төлбөр хүлээж байна...</span>
                        </div>
                        <div id="qpay-urls" class="mt-3"></div>
                    </div>
                    <div id="qpay-paid" style="display:none;">
                        <i class="icon icon-check-circle" style="font-size:3rem;color:#16a34a;"></i>
                        <h4 class="mt-2 text-success">Төлбөр амжилттай!</h4>
                        <p class="h6 text-main">Захиалга бүртгэгдлээ. Шилжүүлж байна...</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- /QPay Modal -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
    'use strict';

    const BASE = <?= json_encode(getBaseUrl()) ?>;

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

    function updateDeliveryDisplay() {
        const isDelivery = document.querySelector('input[name="fulfillment"]:checked')?.value === 'delivery';
        const delSpan = document.getElementById('summary-delivery');
        if (delSpan) {
            delSpan.textContent = isDelivery ? 'Тооцоолох' : 'Өөрөө авна';
        }
    }

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

        if (fulfillment === 'delivery') {
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
            payload.district_id    = parseInt(document.getElementById('district_id').value) || null;
            payload.khoroo_id      = parseInt(document.getElementById('khoroo_id').value) || null;
            payload.address        = document.getElementById('address').value.trim();
            payload.detail_address = document.getElementById('detail_address').value.trim();
        }

        return payload;
    }

    // ── QPay flow ────────────────────────────────────────────────────────────
    let qpayCheckInterval = null;

    function startQpayFlow(orderNumber, total) {
        const modal = new bootstrap.Modal(document.getElementById('qpay-modal'));
        modal.show();

        document.getElementById('qpay-content').style.display = '';
        document.getElementById('qpay-qr').style.display = 'none';
        document.getElementById('qpay-paid').style.display = 'none';

        fetch(BASE + 'backend/api/qpay.php?action=create-invoice', {
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
            document.getElementById('qpay-content').style.display = 'none';

            if (data.error || !data.qr_image) {
                document.getElementById('qpay-content').innerHTML =
                    '<p class="text-danger h6">' + (data.error || 'QR код үүсгэхэд алдаа гарлаа') + '</p>';
                document.getElementById('qpay-content').style.display = '';
                return;
            }

            document.getElementById('qpay-qr-img').src = 'data:image/png;base64,' + data.qr_image;
            document.getElementById('qpay-qr').style.display = '';

            // Show deep-link buttons if provided
            if (data.urls && data.urls.length > 0) {
                const urlWrap = document.getElementById('qpay-urls');
                urlWrap.innerHTML = data.urls.slice(0, 4).map(u =>
                    `<a href="${u.link}" class="tf-btn type-small style-2 m-1" target="_blank">${u.name}</a>`
                ).join('');
            }

            // Poll for payment
            const invoiceId = data.invoice_id;
            let attempts = 0;
            qpayCheckInterval = setInterval(() => {
                attempts++;
                if (attempts > 60) {
                    clearInterval(qpayCheckInterval);
                    return;
                }
                fetch(BASE + 'backend/api/qpay.php?action=check-payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ invoice_id: invoiceId })
                })
                .then(r => r.json())
                .then(check => {
                    if (check.paid) {
                        clearInterval(qpayCheckInterval);
                        document.getElementById('qpay-qr').style.display = 'none';
                        document.getElementById('qpay-paid').style.display = '';
                        setTimeout(() => {
                            window.location.href = BASE + 'track-order.php?order=' + encodeURIComponent(orderNumber);
                        }, 1800);
                    }
                })
                .catch(() => {});
            }, 3000);
        })
        .catch(() => {
            document.getElementById('qpay-content').innerHTML =
                '<p class="text-danger h6">Сервертэй холбогдоход алдаа гарлаа. Дахин оролдоно уу.</p>';
        });
    }

    // Clear QPay poll when modal closes
    document.getElementById('qpay-modal').addEventListener('hidden.bs.modal', function () {
        if (qpayCheckInterval) { clearInterval(qpayCheckInterval); qpayCheckInterval = null; }
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
                window.location.href = BASE + 'track-order.php?order=' + encodeURIComponent(orderNumber);
            }
        })
        .catch(() => {
            window.location.href = BASE + 'track-order.php?order=' + encodeURIComponent(orderNumber);
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
            window.location.href = BASE + 'track-order.php?order=' + encodeURIComponent(orderNumber);
        })
        .catch(() => {
            window.location.href = BASE + 'track-order.php?order=' + encodeURIComponent(orderNumber);
        });
    }

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

        fetch(BASE + 'backend/api/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
                window.location.href = BASE + 'track-order.php?order=' + encodeURIComponent(orderNumber);
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
