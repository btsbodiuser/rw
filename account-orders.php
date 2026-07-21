<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . url('login.php?redirect=' . urlencode(url('account-orders.php'))));
    exit;
}

$user = getSessionUser();
$activeAccountPage = 'orders';
$page_title = 'Миний захиалгууд';
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Миний захиалгууд</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('account.php') ?>" class="h6 link">Бүртгэл</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Захиалгууд</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- Account -->
        <section class="flat-spacing">
            <div class="container">
                <div class="row">
                    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>

                    <!-- Main Content -->
                    <div class="col-xl-9">
                        <div class="my-account-content">
                            <h2 class="account-title type-semibold">Миний захиалгууд</h2>
                            <div id="orders-wrap">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-secondary" role="status"></div>
                                    <p class="h6 text-main mt-2">Захиалгуудыг ачаалж байна...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /Account -->

        <!-- Cargo Payment Modal -->
        <div class="modal fade modalCentered" id="cargo-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content" style="padding:32px;text-align:center;">
                    <div class="header d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-semibold mb-0">Ачааны төлбөр</h4>
                        <span class="icon-close icon-close-popup" data-bs-dismiss="modal" style="cursor:pointer;"></span>
                    </div>
                    <div id="cargo-modal-body"></div>
                </div>
            </div>
        </div>
        <!-- /Cargo Payment Modal -->

<?php
$statusLabels = [
    'pending'             => ['label' => 'Хүлээгдэж байна',   'css' => 'stt-pending'],
    'confirmed'           => ['label' => 'Баталгаажсан',       'css' => 'stt-complete'],
    'cargo_shipping'      => ['label' => 'Ачаа явж байна',     'css' => 'stt-delivery'],
    'cargo_arrived'       => ['label' => 'Ачаа ирсэн',         'css' => 'stt-delivery'],
    'ready_pickup'        => ['label' => 'Авахад бэлэн',       'css' => 'stt-complete'],
    'delivering'          => ['label' => 'Хүргэлтэнд',         'css' => 'stt-delivery'],
    'partially_delivered' => ['label' => 'Хэсэгчлэн хүргэсэн', 'css' => 'stt-delivery'],
    'delivered'           => ['label' => 'Хүргэгдсэн',         'css' => 'stt-complete'],
    'picked_up'           => ['label' => 'Авсан',              'css' => 'stt-complete'],
    'completed'           => ['label' => 'Дууссан',            'css' => 'stt-complete'],
];
$extra_scripts = '<script>
(function () {
    const BASE  = ' . json_encode(getBaseUrl()) . ';
    const TOKEN = ' . json_encode($_SESSION['token'] ?? '') . ';
    const STATUS = ' . json_encode($statusLabels) . ';

    let districtsData = [];
    let addressesData = [];
    let settingsData = {};
    let ordersData = [];

    function loadDistricts() {
        return fetch(BASE + "backend/api/districts.php").then(r => r.json()).then(d => { districtsData = d.districts || []; });
    }
    function loadAddresses() {
        return fetch(BASE + "backend/api/addresses.php", {headers: {"Authorization": "Bearer " + TOKEN}})
            .then(r => r.json()).then(d => { addressesData = d.addresses || []; }).catch(() => {});
    }
    function loadSettings() {
        return fetch(BASE + "backend/api/settings.php").then(r => r.json()).then(d => { settingsData = d.settings || {}; }).catch(() => {});
    }

    function toBool(v) { return v === true || v === "1" || v === 1; }

    function fulfillmentBadge(o) {
        return o.fulfillment === "pickup"
            ? \'<span class="badge" style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:99px;font-size:.75rem;">Очиж авах</span>\'
            : \'<span class="badge" style="background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:99px;font-size:.75rem;">Хүргэлт</span>\';
    }
    function paymentBadge(o) {
        const map = {transfer: ["#ede9fe","#5b21b6","Шилжүүлэг"], bonum: ["#e0e7ff","#3730a3","Bonum"], qpay: ["#cffafe","#155e75","QPay"], storepay: ["#fef9c3","#854d0e","StorePay"]};
        const m = map[o.payment_method] || ["#f3f4f6","#374151", o.payment_method];
        return `<span class="badge" style="background:${m[0]};color:${m[1]};padding:3px 10px;border-radius:99px;font-size:.75rem;">${m[2]}</span>`;
    }
    function paidBadge(o) {
        return o.payment_status === "paid"
            ? \'<span class="badge" style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:99px;font-size:.75rem;">Төлсөн</span>\'
            : "";
    }

    function renderItems(order) {
        return order.items.map(item => `
            <div class="d-flex gap-3 py-2" style="border-bottom:1px solid #f3f4f6;">
                <img src="${item.image || ""}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:6px;background:#f3f4f6;">
                <div class="flex-1">
                    <p class="h6 fw-medium mb-0">${item.product_name_mn || item.product_name}</p>
                    ${item.variant_label ? `<p class="text-small text-primary mb-0">${item.variant_label}</p>` : ""}
                    <p class="text-small text-main mb-0">${item.quantity} ширхэг × ${Number(item.product_price).toLocaleString()}₮</p>
                    ${item.cargo_fee > 0 ? (item.cargo_fee_paid
                        ? `<p class="text-small mb-0"><span class="text-main">Ачаа: ${Number(item.cargo_fee).toLocaleString()}₮</span> <span style="background:#dcfce7;color:#15803d;padding:1px 6px;border-radius:4px;font-size:.7rem;">Төлсөн</span></p>`
                        : `<p class="text-small mb-0"><span style="background:#fef9c3;color:#854d0e;padding:1px 6px;border-radius:4px;font-size:.7rem;">Ачаа төлөөгүй</span></p>`) : ""}
                </div>
                <div class="text-end">
                    <p class="h6 fw-medium mb-0">${Number(item.product_price * item.quantity).toLocaleString()}₮</p>
                </div>
            </div>`).join("");
    }

    function renderAddressBlock(order) {
        if (order.fulfillment === "pickup") {
            return \'<p class="h6 fw-medium mb-0">Очиж авах</p><p class="text-small text-main mb-0">Захиалга бэлэн болмогц мэдэгдэнэ</p>\';
        }
        const khoroo = order.khoroo_name || (order.khoroo_number ? order.khoroo_number + "-р хороо" : "");
        return `<p class="h6 fw-medium mb-0">Хүргэх хаяг:</p><p class="text-small text-main mb-0">${order.district_name || ""}${khoroo ? ", " + khoroo : ""}</p><p class="text-small text-main mb-0">${order.address || ""}</p>`;
    }

    function renderOrderCard(order) {
        const st = STATUS[order.status] || {label: order.status, css: "stt-pending"};
        const created = new Date(order.created_at).toLocaleDateString("mn-MN", {year: "numeric", month: "long", day: "numeric"});
        const canSwitchToDelivery = order.fulfillment === "pickup" && ["pending", "confirmed"].includes(order.status);
        const cargoPayable = order.cargo_fee > 0 && !order.cargo_fee_paid && ["cargo_arrived","ready_pickup","delivering","partially_delivered"].includes(order.status);

        return `<div class="account-order-card" style="border:1px solid #eee;border-radius:10px;padding:20px;margin-bottom:20px;">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="fw-semibold mb-1">#${order.order_number}</h5>
                    <p class="text-small text-main mb-2">${created}</p>
                    <div class="d-flex flex-wrap gap-2">${fulfillmentBadge(order)}${paymentBadge(order)}${paidBadge(order)}</div>
                </div>
                <div class="tb-order_status ${st.css}">${st.label}</div>
            </div>

            <div style="border-top:1px solid #f3f4f6;border-bottom:1px solid #f3f4f6;">
                ${renderItems(order)}
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3">
                <div>${renderAddressBlock(order)}</div>
                <div class="text-end">
                    ${order.delivery_fee > 0 ? `<p class="text-small text-main mb-1">Хүргэлт: <strong>${Number(order.delivery_fee).toLocaleString()}₮</strong></p>` : ""}
                    <p class="text-small text-main mb-0">Нийт дүн</p>
                    <p class="h5 fw-bold mb-0">${Number(order.total).toLocaleString()}₮</p>
                    ${order.cargo_fee > 0 ? (order.cargo_fee_paid
                        ? `<p class="text-small mt-1 mb-0"><span class="text-main">Ачаа: ${Number(order.cargo_fee).toLocaleString()}₮</span> <span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:99px;font-size:.7rem;">Төлсөн</span></p>`
                        : cargoPayable
                            ? `<button type="button" class="tf-btn type-small style-2 btn-pay-cargo mt-1" data-order="${order.order_number}" data-fee="${order.cargo_fee}" style="background:#f59e0b;color:#fff;border:none;">Ачаа (${Number(order.cargo_fee).toLocaleString()}₮) төлөх</button>`
                            : `<p class="text-small mt-1 mb-0"><span style="background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:99px;font-size:.7rem;">Ачаа төлөөгүй</span></p>`) : ""}
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3 pt-3" style="border-top:1px solid #f3f4f6;">
                ${canSwitchToDelivery ? `<button type="button" class="tf-btn type-small style-2 btn-switch-delivery" data-order="${order.order_number}" style="background:#f97316;color:#fff;border:none;">Хүргэлтээр авах</button>` : ""}
                <a href="${BASE}track-order.php?order=${encodeURIComponent(order.order_number)}" class="tf-btn type-small style-2">Захиалга мөшгих</a>
            </div>

            <div class="fulfillment-form-wrap" id="fulfillment-form-${order.order_number}" style="display:none;margin-top:16px;padding:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;"></div>
        </div>`;
    }

    function loadOrders() {
        loadAddresses();
        return fetch(BASE + "backend/api/customer-orders.php", {headers: {"Authorization": "Bearer " + TOKEN}})
        .then(r => r.json())
        .then(data => {
            const wrap = document.getElementById("orders-wrap");
            ordersData = (data.orders || []).filter(o => o.status !== "cancelled");
            if (ordersData.length === 0) {
                wrap.innerHTML = \'<div class="box-text_empty type-shop_cart text-center py-5"><span class="icon"><i class="icon-box-arrow-down" style="font-size:3rem;color:#ccc;"></i></span><h5 class="text-main mt-3">Захиалга олдсонгүй</h5></div>\';
                return;
            }
            wrap.innerHTML = ordersData.map(renderOrderCard).join("");
            wireOrderActions();
        })
        .catch(() => {
            document.getElementById("orders-wrap").innerHTML = \'<p class="text-center py-4 text-main">Захиалгуудыг ачаалж чадсангүй.</p>\';
        });
    }

    function wireOrderActions() {
        document.querySelectorAll(".btn-switch-delivery").forEach(btn => {
            btn.addEventListener("click", function () { openFulfillmentForm(this.dataset.order); });
        });
        document.querySelectorAll(".btn-pay-cargo").forEach(btn => {
            btn.addEventListener("click", function () { openCargoModal(this.dataset.order, parseFloat(this.dataset.fee)); });
        });
    }

    // ── Fulfillment change (pickup -> delivery) ──
    function openFulfillmentForm(orderNumber) {
        const wrap = document.getElementById("fulfillment-form-" + orderNumber);
        if (wrap.style.display !== "none" && wrap.innerHTML) { wrap.style.display = "none"; wrap.innerHTML = ""; return; }

        let quickSelect = "";
        if (addressesData.length > 0) {
            quickSelect = \'<p class="text-small text-main mb-1">Хадгалсан хаягаас сонгох:</p><div class="d-flex flex-column gap-1 mb-3">\' +
                addressesData.map(a => `<button type="button" class="quick-addr-btn text-start" data-district="${a.district_id}" data-khoroo="${a.khoroo_id}" data-address="${(a.address || "").replace(/"/g, "&quot;")}" data-detail="${(a.detail_address || "").replace(/"/g, "&quot;")}" style="border:1px solid #e5e7eb;background:#fff;border-radius:6px;padding:8px 10px;font-size:.85rem;">${a.district_name || ""}${a.khoroo_name ? ", " + a.khoroo_name : ""} — ${a.address || ""}</button>`).join("") +
                "</div>";
        }

        wrap.innerHTML = `<p class="h6 fw-medium mb-2" style="color:#9a3412;">Хүргэлтийн хаяг оруулна уу</p>
            ${quickSelect}
            <div class="tf-select mb-2"><select class="w-100 ff-district"><option value="">Дүүрэг сонгох</option>${districtsData.map(d => `<option value="${d.id}">${d.name_mn || d.name}</option>`).join("")}</select></div>
            <div class="tf-select mb-2"><select class="w-100 ff-khoroo" disabled><option value="">Хороо сонгох</option></select></div>
            <fieldset class="mb-2"><input type="text" class="ff-address" placeholder="Гудамж, байр, тоот"></fieldset>
            <fieldset class="mb-2"><input type="text" class="ff-detail" placeholder="Орц, давхар, тоот (заавал биш)"></fieldset>
            <div class="d-flex gap-2">
                <button type="button" class="tf-btn style-2 ff-cancel">Болих</button>
                <button type="button" class="tf-btn animate-btn ff-save">Хүргэлтээр солих</button>
            </div>`;
        wrap.style.display = "";

        const dSel = wrap.querySelector(".ff-district");
        const kSel = wrap.querySelector(".ff-khoroo");
        dSel.addEventListener("change", function () {
            kSel.innerHTML = \'<option value="">Хороо сонгох</option>\';
            const d = districtsData.find(x => String(x.id) === this.value);
            if (!d) { kSel.disabled = true; return; }
            d.khoroos.forEach(k => {
                const opt = document.createElement("option");
                opt.value = k.id;
                opt.textContent = (k.number ? k.number + "-р хороо" : "") + (k.name ? " " + k.name : "");
                kSel.appendChild(opt);
            });
            kSel.disabled = false;
        });

        wrap.querySelectorAll(".quick-addr-btn").forEach(b => {
            b.addEventListener("click", function () {
                dSel.value = this.dataset.district;
                dSel.dispatchEvent(new Event("change"));
                kSel.value = this.dataset.khoroo;
                wrap.querySelector(".ff-address").value = this.dataset.address;
                wrap.querySelector(".ff-detail").value = this.dataset.detail;
            });
        });

        wrap.querySelector(".ff-cancel").addEventListener("click", function () { wrap.style.display = "none"; wrap.innerHTML = ""; });
        wrap.querySelector(".ff-save").addEventListener("click", function () {
            const districtId = parseInt(dSel.value) || null;
            const khorooId   = parseInt(kSel.value) || null;
            const address    = wrap.querySelector(".ff-address").value.trim();
            const detail     = wrap.querySelector(".ff-detail").value.trim();
            if (!districtId || !khorooId || !address) { alert("Дүүрэг, хороо, хаяг оруулна уу"); return; }

            this.disabled = true;
            fetch(BASE + "backend/api/customer-orders.php", {
                method: "PUT",
                headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
                body: JSON.stringify({order_number: orderNumber, fulfillment: "delivery", district_id: districtId, khoroo_id: khorooId, address: address, detail_address: detail})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { loadOrders(); }
                else { alert(data.error || "Алдаа гарлаа"); this.disabled = false; }
            })
            .catch(() => { alert("Сүлжээний алдаа"); this.disabled = false; });
        });
    }

    // ── Cargo payment modal ──
    let cargoModal = null;
    let cargoPollInterval = null;
    let currentCargoPaymentId = null;

    function stopCargoPoll() { if (cargoPollInterval) { clearInterval(cargoPollInterval); cargoPollInterval = null; } }

    function openCargoModal(orderNumber, fee) {
        cargoModal = cargoModal || new bootstrap.Modal(document.getElementById("cargo-modal"));
        const body = document.getElementById("cargo-modal-body");

        const qpayEnabled     = toBool(settingsData.payment_qpay_enabled);
        const bonumEnabled    = toBool(settingsData.payment_bonum_enabled);
        const storepayEnabled = toBool(settingsData.payment_storepay_enabled);

        let methodButtons = "";
        if (qpayEnabled)     methodButtons += \'<button type="button" class="tf-btn style-2 w-100 mb-2 cargo-method" data-method="qpay">QPay</button>\';
        if (bonumEnabled)    methodButtons += \'<button type="button" class="tf-btn style-2 w-100 mb-2 cargo-method" data-method="bonum">Bonum</button>\';
        if (storepayEnabled) methodButtons += \'<button type="button" class="tf-btn style-2 w-100 mb-2 cargo-method" data-method="storepay">StorePay</button>\';

        body.innerHTML = `<p class="h6 text-main mb-1">Захиалга #${orderNumber}</p>
            <p class="fw-bold mb-3" style="font-size:1.5rem;">${Number(fee).toLocaleString()}₮</p>
            <p class="text-small text-main mb-2">Төлбөрийн хэрэгсэл сонгоно уу:</p>
            ${methodButtons || \'<p class="text-small text-danger">Төлбөрийн хэрэгсэл идэвхгүй байна</p>\'}`;

        body.querySelectorAll(".cargo-method").forEach(btn => {
            btn.addEventListener("click", function () { selectCargoMethod(orderNumber, fee, this.dataset.method); });
        });

        cargoModal.show();
    }

    function selectCargoMethod(orderNumber, fee, method) {
        const body = document.getElementById("cargo-modal-body");

        if (method === "storepay") {
            body.innerHTML = `<p class="h6 text-main mb-3">StorePay-д бүртгэлтэй утасны дугаар</p>
                <fieldset class="mb-3"><input type="tel" class="text-center cargo-sp-phone" placeholder="99999999" maxlength="8"></fieldset>
                <div id="cargo-error" class="text-danger text-small mb-2"></div>
                <button type="button" class="tf-btn animate-btn w-100 cargo-sp-submit">Нэхэмжлэл үүсгэх</button>`;
            body.querySelector(".cargo-sp-submit").addEventListener("click", function () {
                const mobile = body.querySelector(".cargo-sp-phone").value.replace(/\\D/g, "");
                if (!/^\\d{8}$/.test(mobile)) { body.querySelector("#cargo-error").textContent = "8 оронтой дугаар оруулна уу"; return; }
                createCargoInvoice(orderNumber, method, mobile);
            });
            return;
        }

        createCargoInvoice(orderNumber, method, "");
    }

    function createCargoInvoice(orderNumber, method, mobile) {
        const body = document.getElementById("cargo-modal-body");
        body.innerHTML = \'<div class="spinner-border" role="status"></div><p class="h6 text-main mt-3">Үүсгэж байна...</p>\';

        fetch(BASE + "backend/api/cargo-payment.php?action=create", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
            body: JSON.stringify({order_number: orderNumber, payment_method: method, mobile_number: mobile})
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) { body.innerHTML = `<p class="text-danger h6">${data.error}</p>`; return; }
            currentCargoPaymentId = data.cargo_payment_id;

            if (method === "qpay" && data.qr_image) {
                let urlsHtml = "";
                if (data.urls && data.urls.length) {
                    urlsHtml = \'<div class="d-flex flex-wrap gap-1 justify-content-center mt-2">\' +
                        data.urls.slice(0, 6).map(u => `<a href="${u.link}" target="_blank" class="tf-btn type-small style-2">${u.name}</a>`).join("") + "</div>";
                }
                body.innerHTML = `<img src="data:image/png;base64,${data.qr_image}" alt="QPay QR" style="max-width:220px;width:100%;margin:0 auto 12px;">
                    <p class="h6 text-main mb-2">QPay апп-аар скан хийж төлнө үү.</p>
                    ${urlsHtml}
                    <div class="mt-3"><div class="spinner-border spinner-border-sm me-2"></div><span class="h6 text-main">Төлбөр хүлээж байна...</span></div>`;
            } else if (method === "bonum" && data.follow_up_link) {
                body.innerHTML = `<a href="${data.follow_up_link}" target="_blank" class="tf-btn animate-btn w-100 mb-3">Bonum-аар төлөх</a>
                    <div class="mt-2"><div class="spinner-border spinner-border-sm me-2"></div><span class="h6 text-main">Төлбөр хүлээж байна...</span></div>`;
            } else {
                body.innerHTML = \'<p class="h6 text-main">Нэхэмжлэх илгээгдлээ.</p><div class="mt-2"><div class="spinner-border spinner-border-sm me-2"></div><span class="h6 text-main">Төлбөр хүлээж байна...</span></div>\';
            }

            stopCargoPoll();
            cargoPollInterval = setInterval(() => checkCargoPayment(currentCargoPaymentId), 3000);
        })
        .catch(() => { body.innerHTML = \'<p class="text-danger h6">Сервертэй холбогдоход алдаа гарлаа.</p>\'; });
    }

    function checkCargoPayment(cpId) {
        fetch(BASE + "backend/api/cargo-payment.php?action=check", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
            body: JSON.stringify({cargo_payment_id: cpId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.paid) {
                stopCargoPoll();
                document.getElementById("cargo-modal-body").innerHTML = \'<i class="icon icon-check-circle" style="font-size:3rem;color:#16a34a;"></i><h4 class="mt-2 text-success">Төлбөр амжилттай!</h4>\';
                setTimeout(() => { cargoModal.hide(); loadOrders(); }, 1500);
            }
        })
        .catch(() => {});
    }

    document.getElementById("cargo-modal").addEventListener("hidden.bs.modal", stopCargoPoll);

    Promise.all([loadDistricts(), loadSettings()]).then(loadOrders);
}());
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
