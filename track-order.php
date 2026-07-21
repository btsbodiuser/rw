<?php
require_once __DIR__ . '/includes/config.php';

$page_title       = 'Захиалга хянах';
$order_number_get = trim($_GET['order'] ?? '');
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Захиалга хянах</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Захиалга хянах</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- Track Order -->
        <section class="flat-spacing">
            <div class="container">
                <div class="s-log<?= isLoggedIn() ? ' s-log-solo' : '' ?>">
                    <!-- Left: Track Form -->
                    <div class="col-left">
                        <div class="heading">
                            <h1 class="mb-8">Захиалгаа хянах</h1>
                            <p class="text-subhead">
                                Захиалгын дугаараа доорх хайлтын хэсэгт оруулаад "Хянах" товчийг дарна уу.
                                Захиалгын дугаар нь таны захиалгын баталгааны и-мэйл болон баримт дээр байна.
                            </p>
                        </div>
                        <form class="form-login" id="trackForm" novalidate>
                            <div class="list-ver">
                                <fieldset>
                                    <input type="text" id="order_number" name="order_number"
                                           placeholder="Захиалгын дугаар *"
                                           value="<?= htmlspecialchars($order_number_get) ?>"
                                           required>
                                </fieldset>
                            </div>
                            <button type="submit" class="tf-btn animate-btn w-100">
                                <span class="btn-text">Хянах</span>
                                <span class="btn-loading" style="display:none;">Хайж байна...</span>
                            </button>
                        </form>

                        <!-- Results area -->
                        <div id="track-result" class="mt-5" style="display:none;"></div>
                    </div>

                    <?php if (!isLoggedIn()): ?>
                    <!-- Right: Login prompt -->
                    <div class="col-right">
                        <h1 class="heading">Бүртгэлтэй хэрэглэгч үү?</h1>
                        <p class="h6 text-sub">
                            Та бүртгэлтэй бол нэвтэрч захиалгуудаа бүгдийг нэг дор харах боломжтой. Хурдан, хялбар, ямар ч үед.
                        </p>
                        <a href="<?= url('login.php') ?>" class="btn_log tf-btn animate-btn">
                            Нэвтрэх
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <!-- /Track Order -->

<?php
$extra_scripts = '<script>
(function () {
    const BASE = ' . json_encode(getBaseUrl()) . ';

    const STATUS_LABELS = {
        pending:   "Хүлээгдэж байна",
        confirmed: "Баталгаажсан",
        shipped:   "Хүргэлтэнд",
        delivered: "Хүргэгдсэн",
        cancelled: "Цуцлагдсан"
    };
    const STATUS_ICONS = {
        pending:   "icon-package-thin",
        confirmed: "icon-check-fat",
        shipped:   "icon-truck",
        delivered: "icon-check-circle",
        cancelled: "icon-x-circle"
    };
    const STATUS_ORDER = ["pending","confirmed","shipped","delivered"];

    function renderResult(data) {
        const wrap = document.getElementById("track-result");
        if (!data || (!data.order && !data.order_number)) {
            wrap.style.display = "block";
            wrap.innerHTML = \'<div style="padding:20px;background:#fee2e2;border-radius:8px;color:#991b1b;border:1px solid #fca5a5;">\' +
                \'<i class="icon icon-warning" style="margin-right:6px;"></i>Захиалга олдсонгүй. Дугаараа шалгана уу.\' + \'</div>\';
            return;
        }

        const o = data.order || data;
        const status = o.status || "pending";
        const isCancelled = status === "cancelled";
        const steps = STATUS_ORDER;

        // Timeline
        let timelineHtml = \'<div class="tf-order-status-list mb-4">\';
        steps.forEach(function(step) {
            const stepIdx = steps.indexOf(step);
            const curIdx  = steps.indexOf(status);
            const isDone  = !isCancelled && curIdx >= stepIdx;
            const isCur   = step === status && !isCancelled;
            const color   = isDone ? "#16a34a" : "#ccc";
            const iconClass = STATUS_ICONS[step] || "icon-circle";
            timelineHtml += `<div class="tf-order-status-item d-flex align-items-center gap-3 mb-3">
                <div style="width:40px;height:40px;border-radius:50%;background:${color};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="icon ${iconClass}" style="color:#fff;font-size:1.1rem;"></i>
                </div>
                <div>
                    <p class="h6 fw-${isCur ? "bold" : "normal"}" style="color:${isCur ? "#000" : isDone ? "#333" : "#aaa"};">
                        ${STATUS_LABELS[step] || step}
                    </p>
                </div>
            </div>`;
        });
        if (isCancelled) {
            timelineHtml += `<div class="tf-order-status-item d-flex align-items-center gap-3 mb-3">
                <div style="width:40px;height:40px;border-radius:50%;background:#ef4444;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="icon icon-x" style="color:#fff;font-size:1.1rem;"></i>
                </div>
                <div><p class="h6 fw-bold" style="color:#ef4444;">Цуцлагдсан</p></div>
            </div>`;
        }
        timelineHtml += "</div>";

        // Order items
        let itemsHtml = "";
        const items = o.items || o.order_items || [];
        if (items.length > 0) {
            itemsHtml = \'<h5 class="mb-3 fw-semibold">Захиалгын бараанууд</h5><div class="overflow-auto mb-4"><table class="table-my_order" style="width:100%;">\' +
                \'<thead><tr><th>Бараа</th><th>Тоо</th><th>Үнэ</th></tr></thead><tbody>\';
            items.forEach(function(item) {
                itemsHtml += `<tr>
                    <td class="h6">${item.name_mn || item.name || item.product_name || "-"}</td>
                    <td class="h6">${item.qty || item.quantity || 1}</td>
                    <td class="tb-order_price">${Number(item.price || item.unit_price || 0).toLocaleString()}₮</td>
                </tr>`;
            });
            itemsHtml += "</tbody></table></div>";
        }

        // Totals
        const total = o.total_amount || o.total || 0;
        const shipping = o.shipping_fee || o.delivery_fee || 0;
        const date = o.created_at ? new Date(o.created_at).toLocaleDateString("mn-MN") : "-";
        const currentLabel = isCancelled ? "Цуцлагдсан" : (STATUS_LABELS[status] || status);
        const currentColor = isCancelled ? "#ef4444" : "#16a34a";

        wrap.style.display = "block";
        wrap.innerHTML = `
            <div style="border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h4 class="fw-semibold">Захиалга #${o.order_number || o.id}</h4>
                        <p class="h6 text-main">Огноо: ${date}</p>
                    </div>
                    <div class="tb-order_status" style="background:${currentColor}10;color:${currentColor};border:1px solid ${currentColor}40;padding:6px 14px;border-radius:20px;font-weight:600;">
                        ${currentLabel}
                    </div>
                </div>
                ${timelineHtml}
                ${itemsHtml}
                <div class="d-flex flex-column gap-1" style="border-top:1px solid #e5e7eb;padding-top:16px;">
                    ${shipping > 0 ? `<div class="d-flex justify-content-between h6"><span>Хүргэлтийн төлбөр</span><span>${Number(shipping).toLocaleString()}₮</span></div>` : ""}
                    <div class="d-flex justify-content-between h5 fw-bold"><span>Нийт дүн</span><span style="color:#111;">${Number(total).toLocaleString()}₮</span></div>
                </div>
                ${o.shipping_address ? `<div class="mt-3 h6 text-main"><i class="icon icon-map-pin me-1"></i>Хүргэлтийн хаяг: ${o.shipping_address}</div>` : ""}
            </div>`;
    }

    function doTrack(orderNum) {
        const btn = document.getElementById("trackForm").querySelector("button");
        btn.querySelector(".btn-text").style.display  = "none";
        btn.querySelector(".btn-loading").style.display = "";
        btn.disabled = true;

        const wrap = document.getElementById("track-result");
        wrap.style.display = "none";

        fetch(BASE + "backend/api/order-status.php?order_number=" + encodeURIComponent(orderNum))
        .then(r => r.json())
        .then(data => {
            renderResult(data);
        })
        .catch(() => {
            wrap.style.display = "block";
            wrap.innerHTML = \'<div style="padding:20px;background:#fee2e2;border-radius:8px;color:#991b1b;border:1px solid #fca5a5;">Сүлжээний алдаа. Дахин оролдоно уу.</div>\';
        })
        .finally(() => {
            btn.querySelector(".btn-text").style.display  = "";
            btn.querySelector(".btn-loading").style.display = "none";
            btn.disabled = false;
        });
    }

    document.getElementById("trackForm").addEventListener("submit", function (e) {
        e.preventDefault();
        const num = document.getElementById("order_number").value.trim();
        if (!num) return;
        // Update URL
        const url = new URL(window.location.href);
        url.searchParams.set("order", num);
        window.history.replaceState({}, "", url.toString());
        doTrack(num);
    });

    // Auto-load if order number in URL
    const initOrder = ' . json_encode($order_number_get) . ';
    if (initOrder) {
        doTrack(initOrder);
    }
}());
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
