<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');
$db       = getDB();

// ── Guards ──────────────────────────────────────────────────
// Empty cart → back to cart page.
if (empty($_SESSION['cart'])) {
    header('Location: ' . url('cart'));
    exit;
}

// Signed-in customer prefill (guest checkout is allowed).
$sessionUser = getSessionUser();
$customerId  = $sessionUser['id'] ?? null;

// ── Cart lines + summary ───────────────────────────────────
$cartLines    = [];
$cartSubtotal = 0.0;
foreach ($_SESSION['cart'] as $key => $line) {
    $qty = (int)($line['qty'] ?? 0);
    if ($qty <= 0) continue;
    $lineTotal     = (float)$line['price'] * $qty;
    $cartSubtotal += $lineTotal;
    $cartLines[]   = [
        'key'        => $key,
        'name'       => $line['name'],
        'image'      => fixImageUrl($line['image'] ?? null),
        'price'      => (float)$line['price'],
        'qty'        => $qty,
        'color'      => $line['color'] ?? '',
        'size'       => $line['size'] ?? '',
        'line_total' => $lineTotal,
    ];
}

// ── Delivery fee (mirrors backend/api/orders.php logic) ─────
$deliveryFeeEnabled  = sBool('delivery_fee_enabled', true);
$deliveryFeeAmount   = (float)s('delivery_fee', 7000);
$freeDelivThreshold  = (float)s('free_delivery_threshold', 500000);
$deliveryFee         = ($deliveryFeeEnabled && $cartSubtotal < $freeDelivThreshold) ? $deliveryFeeAmount : 0.0;
$cartTotal           = $cartSubtotal + $deliveryFee;

// ── Payment methods (only enabled ones) ─────────────────────
$paymentMethods = [];
if (sBool('payment_qpay_enabled', false)) {
    $paymentMethods['qpay'] = ['label' => 'QPay', 'desc' => 'QR кодоор аль ч банкны аппаас төлөх', 'icon' => 'fa-qrcode'];
}
if (sBool('payment_transfer_enabled', false)) {
    $paymentMethods['transfer'] = ['label' => 'Шилжүүлэг', 'desc' => 'Дансанд шууд шилжүүлэх', 'icon' => 'fa-building-columns'];
}
if (sBool('payment_bonum_enabled', false)) {
    $paymentMethods['bonum'] = ['label' => 'Bonum', 'desc' => 'Bonum хэрэглэгч бол илгээмжээр төлөх', 'icon' => 'fa-credit-card'];
}
if (sBool('payment_storepay_enabled', false)) {
    $paymentMethods['storepay'] = ['label' => 'StorePay', 'desc' => 'Хэсэгчилсэн төлбөр (BNPL)', 'icon' => 'fa-hand-holding-dollar'];
}
$defaultPaymentMethod = array_key_first($paymentMethods);

// ── Saved addresses (signed-in customers) ───────────────────
$savedAddresses = [];
if ($customerId) {
    $stmt = $db->prepare("
        SELECT a.id, a.label, a.district_id, a.khoroo_id, a.address, a.detail_address, a.is_default,
               d.name_mn AS district_name, k.number AS khoroo_number
        FROM customer_addresses a
        LEFT JOIN districts d ON d.id = a.district_id
        LEFT JOIN khoroos   k ON k.id = a.khoroo_id
        WHERE a.customer_id = ?
        ORDER BY a.is_default DESC, a.id DESC
    ");
    $stmt->execute([$customerId]);
    $savedAddresses = $stmt->fetchAll();
}

// ── Districts + khoroos for dropdowns ───────────────────────
$districts = $db->query("SELECT id, name_mn, name FROM districts WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll();
$khoroos   = $db->query("SELECT id, district_id, number, name FROM khoroos ORDER BY district_id, number")->fetchAll();

// Map for client-side district → khoroos filtering.
$khoroosByDistrict = [];
foreach ($khoroos as $k) {
    $khoroosByDistrict[(int)$k['district_id']][] = [
        'id'     => (int)$k['id'],
        'label'  => $k['number'] . '-р хороо' . (!empty($k['name']) ? ' — ' . $k['name'] : ''),
    ];
}

// Prefill from session user + first saved address (if any).
$defaultAddr = $savedAddresses[0] ?? null;

$page_title  = 'Захиалга өгөх — ' . $siteName;
$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-payment-radio {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border: 1px solid #e5e9ef; border-radius: 8px;
    cursor: pointer; transition: border-color .15s;
}
.rw-payment-radio:hover { border-color: var(--color-primary, #00B7FF); }
.rw-payment-radio input[type="radio"] { margin: 0; }
.rw-payment-radio input[type="radio"]:checked + .rw-payment-body { color: var(--color-primary, #00B7FF); }
.rw-payment-radio.active { border-color: var(--color-primary, #00B7FF); background: #f2fbff; }
.rw-payment-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #f2f4f7; border-radius: 8px; font-size: 18px; }
.rw-payment-body { flex: 1; }
.rw-payment-body strong { display: block; }
.rw-payment-body span { color: #6b7280; font-size: 13px; }
.rw-saved-address { padding: 12px 14px; border: 1px solid #e5e9ef; border-radius: 8px; cursor: pointer; }
.rw-saved-address.active { border-color: var(--color-primary, #00B7FF); background: #f2fbff; }
.rw-checkout-card { background: #fff; border: 1px solid #eef1f5; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.rw-checkout-card h3 { margin-bottom: 16px; }
.rw-summary-line { display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center; }
.rw-summary-thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 6px; background: #f7f9fc; }
</style>
CSS;

require __DIR__ . '/includes/header.php';
?>

<div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
    <div class="container">
        <div class="rbt-breadcrumb-inner text-left">
            <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                <li class="rbt-breadcrumb-item"><a href="<?= h(url('cart')) ?>">Сагс</a></li>
                <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                <li class="rbt-breadcrumb-item active">Захиалга өгөх</li>
            </ul>
            <h1 class="title h3 mt--10">Захиалга өгөх</h1>
        </div>
    </div>
</div>

<div class="rbt-component-area rbt-section-gapBottom rbt-bg-color-white">
    <div class="container">
        <?php if (empty($paymentMethods)): ?>
        <div class="alert alert-warning">Одоогоор идэвхтэй төлбөрийн хэрэгсэл алга байна. Админд хандана уу.</div>
        <?php else: ?>
        <form method="post" action="<?= h(url('checkout-action')) ?>" id="rwCheckoutForm" novalidate>
            <?= csrfField() ?>
            <div class="row row--24">
                <div class="col-lg-8 mt--24">

                    <!-- Delivery details -->
                    <div class="rw-checkout-card">
                        <h3 class="title h5">Хүлээн авагчийн мэдээлэл</h3>

                        <?php if ($savedAddresses): ?>
                        <div class="mb--20">
                            <p class="text-muted small mb--8">Хадгалсан хаяг сонгох:</p>
                            <div class="row row--12">
                                <?php foreach ($savedAddresses as $i => $addr): ?>
                                <div class="col-md-6 mb--12">
                                    <label class="rw-saved-address d-block <?= $i === 0 ? 'active' : '' ?>">
                                        <input type="radio" name="saved_address_id" value="<?= (int)$addr['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> class="d-none rw-saved-address-radio"
                                               data-district="<?= (int)$addr['district_id'] ?>"
                                               data-khoroo="<?= (int)$addr['khoroo_id'] ?>"
                                               data-address="<?= h($addr['address']) ?>"
                                               data-detail="<?= h($addr['detail_address'] ?? '') ?>">
                                        <strong class="d-block"><?= h($addr['district_name'] ?: '—') ?>, <?= (int)$addr['khoroo_number'] ?>-р хороо</strong>
                                        <span class="text-muted small"><?= h($addr['address']) ?><?= $addr['detail_address'] ? ' — ' . h($addr['detail_address']) : '' ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <div class="col-md-6 mb--12">
                                    <label class="rw-saved-address d-block">
                                        <input type="radio" name="saved_address_id" value="new" class="d-none rw-saved-address-radio">
                                        <strong class="d-block"><i class="fa-regular fa-plus mr--4"></i> Шинэ хаяг</strong>
                                        <span class="text-muted small">Шинэ хаягаар хүргүүлэх</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row row--12">
                            <div class="col-md-6 mb--16">
                                <label class="form-label">Овог, нэр <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" value="<?= h($sessionUser['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb--16">
                                <label class="form-label">Утасны дугаар <span class="text-danger">*</span></label>
                                <input type="tel" name="customer_phone" class="form-control" value="<?= h($sessionUser['phone'] ?? '') ?>" pattern="[0-9]{8}" maxlength="8" required>
                            </div>
                            <div class="col-md-6 mb--16">
                                <label class="form-label">Дүүрэг <span class="text-danger">*</span></label>
                                <select name="district_id" id="rwDistrict" class="form-control" required>
                                    <option value="">— Сонгох —</option>
                                    <?php foreach ($districts as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>" <?= $defaultAddr && (int)$defaultAddr['district_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['name_mn'] ?: $d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb--16">
                                <label class="form-label">Хороо <span class="text-danger">*</span></label>
                                <select name="khoroo_id" id="rwKhoroo" class="form-control" required>
                                    <option value="">Эхлээд дүүргээ сонгоно уу</option>
                                </select>
                            </div>
                            <div class="col-12 mb--16">
                                <label class="form-label">Байшин, гудамж <span class="text-danger">*</span></label>
                                <input type="text" name="address" id="rwAddress" class="form-control" value="<?= h($defaultAddr['address'] ?? '') ?>" required>
                            </div>
                            <div class="col-12 mb--16">
                                <label class="form-label">Нэмэлт тайлбар (орц, давхар, хаалганы код)</label>
                                <input type="text" name="detail_address" id="rwDetail" class="form-control" value="<?= h($defaultAddr['detail_address'] ?? '') ?>">
                            </div>
                            <?php if ($customerId && !$savedAddresses): ?>
                            <div class="col-12">
                                <label class="d-flex align-items-center gap-2"><input type="checkbox" name="save_address" value="1" checked> <span>Энэ хаягийг миний хаягуудад хадгалах</span></label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment method -->
                    <div class="rw-checkout-card">
                        <h3 class="title h5">Төлбөрийн хэрэгсэл</h3>
                        <div class="d-flex flex-column rbt-gap--12">
                            <?php foreach ($paymentMethods as $key => $pm): ?>
                            <label class="rw-payment-radio <?= $key === $defaultPaymentMethod ? 'active' : '' ?>">
                                <input type="radio" name="payment_method" value="<?= h($key) ?>" <?= $key === $defaultPaymentMethod ? 'checked' : '' ?>>
                                <span class="rw-payment-icon"><i class="fa-regular <?= h($pm['icon']) ?>"></i></span>
                                <span class="rw-payment-body">
                                    <strong><?= h($pm['label']) ?></strong>
                                    <span><?= h($pm['desc']) ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="rw-checkout-card">
                        <h3 class="title h5">Захиалгын тэмдэглэл</h3>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Хүргэлтийн хугацаа, тусгай хүсэлт г.м."></textarea>
                    </div>
                </div>

                <!-- Order summary -->
                <div class="col-lg-4 mt--24">
                    <div class="rw-checkout-card position-sticky" style="top: 100px;">
                        <div class="d-flex justify-content-between align-items-center mb--16">
                            <h3 class="title h5 mb-0">Захиалгын дүн</h3>
                            <a href="<?= h(url('cart')) ?>" class="b3 rbt-text-color-primary">Өөрчлөх</a>
                        </div>

                        <?php foreach ($cartLines as $line): ?>
                        <div class="d-flex align-items-center rbt-gap--12 mb--12">
                            <img src="<?= h($line['image']) ?>" class="rw-summary-thumb" alt="<?= h($line['name']) ?>">
                            <div class="flex-fill">
                                <p class="b3 mb--4 rbt-text-bold"><?= h($line['name']) ?></p>
                                <?php $meta = trim(($line['color'] ?? '') . (($line['color'] && $line['size']) ? ' / ' : '') . ($line['size'] ?? '')); ?>
                                <?php if ($meta): ?><p class="b4 mb--4 text-muted"><?= h($meta) ?></p><?php endif; ?>
                                <p class="b4 mb-0"><?= (int)$line['qty'] ?> × <?= h(formatPrice($line['price'])) ?></p>
                            </div>
                            <span class="rbt-text-bold b3"><?= h(formatPrice($line['line_total'])) ?></span>
                        </div>
                        <?php endforeach; ?>

                        <hr class="rbt-separator-gray200 mt--16 mb--16">

                        <div class="rw-summary-line">
                            <span>Дэд дүн</span>
                            <strong><?= h(formatPrice($cartSubtotal)) ?></strong>
                        </div>
                        <div class="rw-summary-line">
                            <span>Хүргэлт</span>
                            <strong><?= $deliveryFee > 0 ? h(formatPrice($deliveryFee)) : 'Үнэгүй' ?></strong>
                        </div>
                        <?php if ($deliveryFeeEnabled && $deliveryFee > 0): ?>
                        <p class="b4 text-muted mb--12">
                            <?= h(formatPrice($freeDelivThreshold - $cartSubtotal)) ?> нэмэгдвэл үнэгүй хүргэлт.
                        </p>
                        <?php endif; ?>
                        <hr class="rbt-separator-gray200 mt--0 mb--12">
                        <div class="rw-summary-line">
                            <span class="h6 mb-0">Нийт</span>
                            <span class="h6 mb-0 rbt-text-color-primary"><?= h(formatPrice($cartTotal)) ?></span>
                        </div>

                        <button type="submit" class="rbt-btn w-100 text-center mt--16" id="rwPlaceOrderBtn">
                            <i class="fa-regular fa-check mr--4"></i> Захиалга баталгаажуулах
                        </button>
                        <p class="b4 text-muted text-center mt--12 mb-0">Баталгаажуулснаар <a href="#">үйлчилгээний нөхцөл</a>-тэй танилцаж, хүлээн зөвшөөрсөнд тооцно.</p>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    // Client-side district → khoroo filter (no round-trip on every district change).
    var khoroosByDistrict = <?= json_encode($khoroosByDistrict, JSON_UNESCAPED_UNICODE) ?>;
    var distSel   = document.getElementById('rwDistrict');
    var khorSel   = document.getElementById('rwKhoroo');
    var initialK  = <?= json_encode((int)($defaultAddr['khoroo_id'] ?? 0)) ?>;

    function populateKhoroos(districtId, selectedKhoroo) {
        khorSel.innerHTML = '';
        var list = khoroosByDistrict[districtId] || [];
        if (!list.length) {
            khorSel.innerHTML = '<option value="">Хороо олдсонгүй</option>';
            return;
        }
        khorSel.appendChild(new Option('— Сонгох —', ''));
        list.forEach(function (k) {
            var opt = new Option(k.label, k.id);
            if (k.id === selectedKhoroo) opt.selected = true;
            khorSel.appendChild(opt);
        });
    }

    if (distSel.value) populateKhoroos(parseInt(distSel.value, 10), initialK);
    distSel.addEventListener('change', function () {
        populateKhoroos(parseInt(distSel.value, 10) || 0, 0);
    });

    // Payment radio card active state.
    document.querySelectorAll('.rw-payment-radio input[type="radio"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.querySelectorAll('.rw-payment-radio').forEach(function (el) { el.classList.remove('active'); });
            r.closest('.rw-payment-radio').classList.add('active');
        });
    });

    // Saved-address card active state + prefill form fields.
    document.querySelectorAll('.rw-saved-address-radio').forEach(function (r) {
        r.addEventListener('change', function () {
            document.querySelectorAll('.rw-saved-address').forEach(function (el) { el.classList.remove('active'); });
            r.closest('.rw-saved-address').classList.add('active');
            if (r.value === 'new') {
                distSel.value = ''; populateKhoroos(0, 0);
                document.getElementById('rwAddress').value = '';
                document.getElementById('rwDetail').value  = '';
                return;
            }
            var d = parseInt(r.getAttribute('data-district'), 10) || 0;
            var k = parseInt(r.getAttribute('data-khoroo'), 10) || 0;
            distSel.value = d;
            populateKhoroos(d, k);
            document.getElementById('rwAddress').value = r.getAttribute('data-address') || '';
            document.getElementById('rwDetail').value  = r.getAttribute('data-detail')  || '';
        });
    });

    // Prevent double-submit.
    var form = document.getElementById('rwCheckoutForm');
    var btn  = document.getElementById('rwPlaceOrderBtn');
    form && form.addEventListener('submit', function () {
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-regular fa-spinner-third fa-spin mr--4"></i> Илгээж байна...'; }
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
