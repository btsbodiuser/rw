<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');
$db       = getDB();

// ── Resolve the order ──────────────────────────────────────
// Prefer the URL param; fall back to the session's "last order" so refreshes
// after a successful checkout still land on the correct thanks page.
$orderNumber = trim((string)($_GET['order'] ?? $_SESSION['last_order_number'] ?? ''));
if ($orderNumber === '') {
    header('Location: ' . $urlHome);
    exit;
}

$stmt = $db->prepare("
    SELECT o.id, o.order_number, o.customer_name, o.customer_phone,
           o.address, o.detail_address, o.subtotal, o.delivery_fee, o.total,
           o.payment_method, o.payment_status, o.qpay_invoice_id, o.status,
           o.notes, o.created_at,
           d.name_mn AS district_name, k.number AS khoroo_number
    FROM orders o
    LEFT JOIN districts d ON d.id = o.district_id
    LEFT JOIN khoroos   k ON k.id = o.khoroo_id
    WHERE o.order_number = ?
    LIMIT 1
");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . $urlHome);
    exit;
}

// ── Order lines for the summary ────────────────────────────
$itemsStmt = $db->prepare("
    SELECT oi.product_id, oi.variant_id, oi.variant_label, oi.product_name,
           oi.product_price, oi.quantity, oi.line_total,
           p.slug, p.image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$order['id']]);
$orderItems = $itemsStmt->fetchAll();

// ── Payment method labels + follow-up data ─────────────────
$pmLabel = match ($order['payment_method']) {
    'qpay'     => 'QPay',
    'transfer' => 'Дансаар шилжүүлэх',
    'bonum'    => 'Bonum',
    'storepay' => 'StorePay',
    'card'     => 'Карт',
    'cash'     => 'Бэлэн мөнгө',
    default    => 'Тодорхойгүй',
};

// Bank details for transfer method — sourced from settings.
$bankName    = s('bank_name', '');
$bankAccNo   = s('bank_account_number', '');
$bankAccName = s('bank_account_name', '');

$page_title  = 'Захиалга #' . $orderNumber . ' — ' . $siteName;
$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-thanks-card { background: #fff; border: 1px solid #eef1f5; border-radius: 10px; padding: 32px; }
.rw-thanks-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: #dcfce7; color: #16a34a;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; font-size: 32px;
}
.rw-thanks-order { font-family: monospace; font-size: 20px; color: var(--color-primary, #00B7FF); font-weight: bold; }
.rw-thanks-bankcard { background: #f7f9fc; border: 1px dashed #d1d5db; border-radius: 8px; padding: 20px; margin-top: 16px; }
.rw-bank-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e9ef; }
.rw-bank-row:last-child { border-bottom: none; }
.rw-bank-row strong { color: #0a0a0a; font-family: monospace; }
.rw-item-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; background: #f7f9fc; }
#rwQpayQr img { max-width: 240px; }
#rwQpayApps {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 10px;
}
.rw-qpay-bank {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 12px 6px; border: 1px solid #e5e9ef; border-radius: 10px;
    background: #fff; text-align: center;
    transition: border-color .15s, box-shadow .15s, transform .15s;
}
.rw-qpay-bank:hover {
    border-color: var(--color-primary, #00B7FF);
    box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
    transform: translateY(-2px);
}
.rw-qpay-bank img {
    width: 44px; height: 44px; border-radius: 10px; object-fit: contain;
    background: #f7f9fc;
}
.rw-qpay-bank span {
    font-size: 12px; line-height: 1.25; color: #334155;
    overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
</style>
CSS;

require __DIR__ . '/includes/header.php';
?>

<div class="rbt-component-area rbt-section-gap rbt-bg-color-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Success card -->
                <div class="rw-thanks-card text-center">
                    <div class="rw-thanks-icon"><i class="fa-solid fa-check"></i></div>
                    <h2 class="title h4">Захиалга амжилттай үүслээ!</h2>
                    <p class="mb--12">Захиалгын дугаар: <span class="rw-thanks-order">#<?= h($orderNumber) ?></span></p>
                    <p class="text-muted b3 mb--0">Захиалгын мэдээллийг доор үзнэ үү. Дэлгэрэнгүйг <a href="<?= h(url('account')) ?>">Хувийн бүртгэл</a> хэсгээс шалгах боломжтой.</p>
                </div>

                <!-- Payment instructions per method -->
                <?php if ($order['payment_method'] === 'transfer'): ?>
                <div class="rw-thanks-card mt--24">
                    <h3 class="title h5">Төлбөр төлөх заавар — Дансаар шилжүүлэх</h3>
                    <p class="b3">Дараах дансанд захиалгын дүнг шилжүүлж, шилжүүлгийн утга дээр <strong>захиалгын дугаараа</strong> заавал бичнэ үү.</p>
                    <div class="rw-thanks-bankcard">
                        <?php if ($bankName): ?>
                        <div class="rw-bank-row"><span>Банк</span><strong><?= h($bankName) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($bankAccNo): ?>
                        <div class="rw-bank-row"><span>Дансны дугаар</span><strong><?= h($bankAccNo) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($bankAccName): ?>
                        <div class="rw-bank-row"><span>Хүлээн авагч</span><strong><?= h($bankAccName) ?></strong></div>
                        <?php endif; ?>
                        <div class="rw-bank-row"><span>Дүн</span><strong><?= h(formatPrice((float)$order['total'])) ?></strong></div>
                        <div class="rw-bank-row"><span>Гүйлгээний утга</span><strong><?= h($orderNumber) ?></strong></div>
                    </div>
                    <p class="b4 text-muted mt--12 mb-0">Төлбөр хүлээн авмагц захиалгыг баталгаажуулж, хүргэлтэд хүргэнэ.</p>
                </div>

                <?php elseif ($order['payment_method'] === 'qpay'): ?>
                <div class="rw-thanks-card mt--24" id="rwQpayBox">
                    <h3 class="title h5">Төлбөр төлөх — QPay</h3>
                    <p class="b3">Аль ч банкны аппаас доорх QR кодыг уншуулан төлбөрөө хийнэ үү.</p>
                    <div id="rwQpayQr" class="text-center py-4">
                        <i class="fa-regular fa-spinner-third fa-spin"></i> QR код бэлдэж байна...
                    </div>
                    <div id="rwQpayApps" class="mt--16" style="display:none;"></div>
                </div>

                <?php elseif ($order['payment_method'] === 'bonum'): ?>
                <div class="rw-thanks-card mt--24" id="rwBonumBox">
                    <h3 class="title h5">Төлбөр төлөх — Bonum</h3>
                    <p class="b3">Bonum аппаар төлөх линкийг бэлдэж байна...</p>
                    <div id="rwBonumStatus" class="text-center py-3"><i class="fa-regular fa-spinner-third fa-spin"></i></div>
                </div>

                <?php elseif ($order['payment_method'] === 'storepay'): ?>
                <div class="rw-thanks-card mt--24" id="rwStorepayBox">
                    <h3 class="title h5">Төлбөр төлөх — StorePay</h3>
                    <p class="b3">StorePay-ээр хэсэгчилсэн төлбөрийн хүсэлт илгээж байна...</p>
                    <div id="rwStorepayStatus" class="text-center py-3"><i class="fa-regular fa-spinner-third fa-spin"></i></div>
                </div>
                <?php endif; ?>

                <!-- Order summary -->
                <div class="rw-thanks-card mt--24">
                    <h3 class="title h5">Захиалгын дэлгэрэнгүй</h3>

                    <div class="row row--12 mb--16">
                        <div class="col-md-6">
                            <p class="b4 text-muted mb--4">Хүлээн авагч</p>
                            <p class="b3 mb-0"><strong><?= h($order['customer_name']) ?></strong> — <?= h($order['customer_phone']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="b4 text-muted mb--4">Төлбөрийн хэрэгсэл</p>
                            <p class="b3 mb-0"><strong><?= h($pmLabel) ?></strong> <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= $order['payment_status'] === 'paid' ? 'Төлөгдсөн' : 'Хүлээгдэж буй' ?></span></p>
                        </div>
                        <div class="col-12 mt--12">
                            <p class="b4 text-muted mb--4">Хүргэлтийн хаяг</p>
                            <p class="b3 mb-0"><?= h($order['district_name'] ?: '—') ?>, <?= (int)$order['khoroo_number'] ?>-р хороо, <?= h($order['address']) ?><?= $order['detail_address'] ? ' — ' . h($order['detail_address']) : '' ?></p>
                        </div>
                        <?php if ($order['notes']): ?>
                        <div class="col-12 mt--12">
                            <p class="b4 text-muted mb--4">Тэмдэглэл</p>
                            <p class="b3 mb-0"><?= nl2br(h($order['notes'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr class="rbt-separator-gray200">

                    <?php foreach ($orderItems as $it):
                        $img = $it['image'] ? fixImageUrl($it['image']) : fixImageUrl(null);
                        $url = $it['slug'] ? url('product?slug=' . urlencode($it['slug'])) : '#';
                    ?>
                    <div class="d-flex align-items-center rbt-gap--12 mb--12">
                        <img src="<?= h($img) ?>" class="rw-item-thumb" alt="<?= h($it['product_name']) ?>">
                        <div class="flex-fill">
                            <p class="b3 mb-0"><a href="<?= h($url) ?>" class="rbt-text-color-heading"><?= h($it['product_name']) ?></a></p>
                            <?php if ($it['variant_label']): ?>
                            <p class="b4 text-muted mb-0"><?= h($it['variant_label']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="b4 text-muted"><?= (int)$it['quantity'] ?> ×</span>
                        <span class="rbt-text-bold b3"><?= h(formatPrice((float)$it['product_price'] * (int)$it['quantity'])) ?></span>
                    </div>
                    <?php endforeach; ?>

                    <hr class="rbt-separator-gray200">

                    <div class="d-flex justify-content-between mb--8"><span>Дэд дүн</span><strong><?= h(formatPrice((float)$order['subtotal'])) ?></strong></div>
                    <div class="d-flex justify-content-between mb--8"><span>Хүргэлт</span><strong><?= (float)$order['delivery_fee'] > 0 ? h(formatPrice((float)$order['delivery_fee'])) : 'Үнэгүй' ?></strong></div>
                    <div class="d-flex justify-content-between h5 mb-0"><span>Нийт</span><span class="rbt-text-color-primary"><?= h(formatPrice((float)$order['total'])) ?></span></div>
                </div>

                <div class="text-center mt--24">
                    <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border me-2"><i class="fa-regular fa-arrow-left mr--4"></i> Үргэлжлүүлэн хайх</a>
                    <a href="<?= h(url('account')) ?>" class="rbt-btn">Миний захиалгууд <i class="fa-regular fa-arrow-right ml--4"></i></a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
// ── Kick off gateway invoice creation on the client ───────────────────
$needsGateway = in_array($order['payment_method'], ['qpay', 'bonum', 'storepay'], true);
if ($needsGateway):
?>
<script>
(function () {
    var orderNumber = <?= json_encode($orderNumber) ?>;
    var amount      = <?= json_encode((float)$order['total']) ?>;
    var phone       = <?= json_encode((string)$order['customer_phone']) ?>;
    var method      = <?= json_encode($order['payment_method']) ?>;
    var apiBase     = <?= json_encode(getBaseUrl() . 'backend/api/') ?>;

    function post(url, body) {
        return fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body || {})
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, code: r.status, data: d }; }); });
    }

    if (method === 'qpay') {
        post(apiBase + 'qpay.php?action=create-invoice', {
            order_number: orderNumber, amount: amount, description: 'Runners World #' + orderNumber
        }).then(function (res) {
            var box = document.getElementById('rwQpayQr');
            if (!res.ok || !res.data.success) {
                box.innerHTML = '<p class="text-danger mb-0">QPay нэхэмжлэл үүсгэхэд алдаа гарлаа. Дахин оролдоно уу.</p>';
                return;
            }
            var img = res.data.qr_image ? '<img src="data:image/png;base64,' + res.data.qr_image + '" alt="QPay QR">' : '';
            box.innerHTML = img || '<p class="text-muted">QR код байхгүй.</p>';
            var apps = document.getElementById('rwQpayApps');
            if (Array.isArray(res.data.urls) && res.data.urls.length) {
                apps.style.display = 'grid';
                res.data.urls.forEach(function (u) {
                    if (!u || !u.link) return;
                    var a = document.createElement('a');
                    a.href = u.link; a.className = 'rw-qpay-bank';
                    var label = u.description || u.name || 'Төлөх';
                    if (u.logo) {
                        var img = document.createElement('img');
                        img.src = u.logo; img.alt = label; img.loading = 'lazy';
                        img.onerror = function () { this.remove(); };
                        a.appendChild(img);
                    }
                    var s = document.createElement('span');
                    s.textContent = label;
                    a.appendChild(s);
                    apps.appendChild(a);
                });
            }
        }).catch(function () {
            document.getElementById('rwQpayQr').innerHTML = '<p class="text-danger mb-0">Сүлжээний алдаа.</p>';
        });
    }

    if (method === 'bonum') {
        post(apiBase + 'bonum.php?action=create-invoice', {
            order_number: orderNumber, amount: amount, description: 'Runners World #' + orderNumber
        }).then(function (res) {
            var box = document.getElementById('rwBonumStatus');
            if (!res.ok || !res.data.success) {
                box.innerHTML = '<p class="text-danger mb-0">Bonum нэхэмжлэл үүсгэхэд алдаа гарлаа.</p>';
                return;
            }
            if (res.data.follow_up_link) {
                box.innerHTML = '<a class="rbt-btn" href="' + res.data.follow_up_link + '" target="_blank"><i class="fa-regular fa-arrow-up-right-from-square mr--4"></i> Bonum руу очих</a>';
            } else {
                box.innerHTML = '<p class="mb-0 text-success">Bonum нэхэмжлэл үүсгэгдлээ. Bonum апп-даа шалгаарай.</p>';
            }
        });
    }

    if (method === 'storepay') {
        // StorePay needs the mobile number to push the request.
        post(apiBase + 'storepay.php?action=create-invoice', {
            order_number: orderNumber, amount: amount, mobile_number: phone, description: 'Runners World #' + orderNumber
        }).then(function (res) {
            var box = document.getElementById('rwStorepayStatus');
            if (!res.ok || !res.data.success) {
                box.innerHTML = '<p class="text-danger mb-0">StorePay хүсэлт илгээхэд алдаа гарлаа.</p>';
                return;
            }
            box.innerHTML = '<p class="mb-0 text-success">StorePay хүсэлт таны утас руу илгээгдлээ. StorePay аппаа нээж зөвшөөрнө үү.</p>';
        });
    }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
