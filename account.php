<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . url('login.php?redirect=' . urlencode(url('account.php'))));
    exit;
}

$user = getSessionUser();
$page_title = 'Миний бүртгэл';
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Миний бүртгэл</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Бүртгэл</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- Account -->
        <section class="flat-spacing">
            <div class="container">
                <div class="row">
                    <!-- Sidebar -->
                    <div class="col-xl-3 d-none d-xl-block">
                        <div class="sidebar-account sidebar-content-wrap sticky-top">
                            <div class="account-author">
                                <div class="author_avatar">
                                    <div class="image">
                                        <img src="<?= assetUrl('images/avatar/avatar-4.jpg') ?>" alt="Avatar">
                                    </div>
                                </div>
                                <h4 class="author_name"><?= htmlspecialchars($user['name'] ?? '') ?></h4>
                                <p class="author_email h6"><?= htmlspecialchars($user['phone'] ?? $user['email'] ?? '') ?></p>
                            </div>
                            <ul class="my-account-nav">
                                <li>
                                    <a href="#tab-profile" data-bs-toggle="tab" class="my-account-nav_item h5 active" id="nav-profile-tab">
                                        <i class="icon icon-circle-four"></i>
                                        Профайл
                                    </a>
                                </li>
                                <li>
                                    <a href="#tab-orders" data-bs-toggle="tab" class="my-account-nav_item h5" id="nav-orders-tab">
                                        <i class="icon icon-box-arrow-down"></i>
                                        Захиалгууд
                                    </a>
                                </li>
                                <li>
                                    <a href="<?= url('logout-action.php') ?>" class="my-account-nav_item h5"
                                       onclick="return confirm('Гарахдаа итгэлтэй байна уу?')">
                                        <i class="icon icon-sign-out"></i>
                                        Гарах
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="col-xl-9">
                        <!-- Mobile tab buttons -->
                        <ul class="nav d-xl-none mb-4 gap-2 flex-wrap" id="accountTabsMobile">
                            <li class="nav-item">
                                <a class="tf-btn type-small style-2 active" href="#tab-profile" data-bs-toggle="tab">Профайл</a>
                            </li>
                            <li class="nav-item">
                                <a class="tf-btn type-small style-2" href="#tab-orders" data-bs-toggle="tab">Захиалгууд</a>
                            </li>
                            <li class="nav-item">
                                <a class="tf-btn type-small style-2" href="<?= url('logout-action.php') ?>"
                                   onclick="return confirm('Гарахдаа итгэлтэй байна уу?')">Гарах</a>
                            </li>
                        </ul>

                        <div class="tab-content my-account-content">

                            <!-- Tab 1: Profile -->
                            <div class="tab-pane fade show active" id="tab-profile">
                                <h2 class="account-title type-semibold mb-4">Профайл мэдээлэл</h2>
                                <div id="profile-alert" class="alert" style="display:none;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:.9rem;"></div>
                                <form id="profileForm" class="form-login">
                                    <div class="list-ver">
                                        <fieldset>
                                            <label class="h6 fw-medium mb-8 d-block">Нэр</label>
                                            <input type="text" id="prof_name" name="name"
                                                   value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                                                   placeholder="Нэр" required>
                                        </fieldset>
                                        <fieldset>
                                            <label class="h6 fw-medium mb-8 d-block">Утасны дугаар</label>
                                            <input type="text" id="prof_phone" name="phone"
                                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                                   placeholder="Утасны дугаар">
                                        </fieldset>
                                        <fieldset>
                                            <label class="h6 fw-medium mb-8 d-block">И-мэйл</label>
                                            <input type="email" id="prof_email" name="email"
                                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                                   placeholder="И-мэйл хаяг">
                                        </fieldset>
                                    </div>
                                    <button type="submit" class="tf-btn animate-btn" id="btnSaveProfile">
                                        <span class="btn-text">Хадгалах</span>
                                        <span class="btn-loading" style="display:none;">Хадгалж байна...</span>
                                    </button>
                                </form>
                            </div>

                            <!-- Tab 2: Orders -->
                            <div class="tab-pane fade" id="tab-orders">
                                <h2 class="account-title type-semibold mb-4">Миний захиалгууд</h2>
                                <div id="orders-wrap">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-secondary" role="status"></div>
                                        <p class="h6 text-main mt-2">Захиалгуудыг ачаалж байна...</p>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /.tab-content -->
                    </div>
                </div>
            </div>
        </section>
        <!-- /Account -->

<?php
$statusLabels = [
    'pending'   => ['label' => 'Хүлээгдэж байна', 'css' => 'stt-pending'],
    'confirmed' => ['label' => 'Баталгаажсан',     'css' => 'stt-complete'],
    'shipped'   => ['label' => 'Хүргэлтэнд',       'css' => 'stt-delivery'],
    'delivered' => ['label' => 'Хүргэгдсэн',       'css' => 'stt-complete'],
    'cancelled' => ['label' => 'Цуцлагдсан',       'css' => 'stt-cancel'],
];
$extra_scripts = '<script>
(function () {
    const BASE  = ' . json_encode(getBaseUrl()) . ';
    const TOKEN = ' . json_encode($_SESSION['token'] ?? '') . ';
    const STATUS = ' . json_encode($statusLabels) . ';

    // Profile form
    document.getElementById("profileForm").addEventListener("submit", function (e) {
        e.preventDefault();
        const btn   = document.getElementById("btnSaveProfile");
        const alert = document.getElementById("profile-alert");

        btn.querySelector(".btn-text").style.display   = "none";
        btn.querySelector(".btn-loading").style.display = "";
        btn.disabled = true;
        alert.style.display = "none";

        const name  = document.getElementById("prof_name").value.trim();
        const phone = document.getElementById("prof_phone").value.trim();
        const email = document.getElementById("prof_email").value.trim();

        fetch(BASE + "backend/api/auth/me.php", {
            method: "PUT",
            headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
            body: JSON.stringify({name: name, phone: phone, email: email})
        })
        .then(r => r.json())
        .then(data => {
            alert.style.display  = "block";
            if (data.success || data.user) {
                alert.style.background = "#d1fae5";
                alert.style.color      = "#065f46";
                alert.style.border     = "1px solid #6ee7b7";
                alert.textContent      = "Мэдээлэл амжилттай шинэчлэгдлээ.";
            } else {
                alert.style.background = "#fee2e2";
                alert.style.color      = "#991b1b";
                alert.style.border     = "1px solid #fca5a5";
                alert.textContent      = data.message || "Алдаа гарлаа.";
            }
        })
        .catch(() => {
            alert.style.display  = "block";
            alert.style.background = "#fee2e2";
            alert.style.color      = "#991b1b";
            alert.style.border     = "1px solid #fca5a5";
            alert.textContent      = "Сүлжээний алдаа. Дахин оролдоно уу.";
        })
        .finally(() => {
            btn.querySelector(".btn-text").style.display   = "";
            btn.querySelector(".btn-loading").style.display = "none";
            btn.disabled = false;
        });
    });

    // Load orders when tab shown
    function loadOrders() {
        fetch(BASE + "backend/api/customer-orders.php", {
            headers: {"Authorization": "Bearer " + TOKEN}
        })
        .then(r => r.json())
        .then(data => {
            const wrap = document.getElementById("orders-wrap");
            const orders = data.orders || data;
            if (!Array.isArray(orders) || orders.length === 0) {
                wrap.innerHTML = \'<div class="box-text_empty type-shop_cart text-center py-5"><span class="icon"><i class="icon-box-arrow-down" style="font-size:3rem;color:#ccc;"></i></span><h5 class="text-main mt-3">Захиалга олдсонгүй</h5></div>\';
                return;
            }
            let rows = "";
            orders.forEach(o => {
                const st = STATUS[o.status] || {label: o.status, css: "stt-pending"};
                const date = o.created_at ? new Date(o.created_at).toLocaleDateString("mn-MN") : "-";
                const total = o.total_amount ? Number(o.total_amount).toLocaleString() + "₮" : "-";
                rows += `<tr class="tb-order-item">
                    <td class="tb-order_code">#${o.order_number || o.id}</td>
                    <td>${date}</td>
                    <td class="tb-order_price">${total}</td>
                    <td><div class="tb-order_status ${st.css}">${st.label}</div></td>
                    <td><a href="${BASE}track-order.php?order=${encodeURIComponent(o.order_number || o.id)}" class="tf-btn type-small style-2">Дэлгэрэнгүй</a></td>
                </tr>`;
            });
            wrap.innerHTML = `<div class="overflow-auto"><table class="table-my_order order_recent">
                <thead><tr>
                    <th>Захиалга #</th>
                    <th>Огноо</th>
                    <th>Нийт дүн</th>
                    <th>Статус</th>
                    <th></th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`;
        })
        .catch(() => {
            document.getElementById("orders-wrap").innerHTML = \'<p class="text-center py-4 text-main">Захиалгуудыг ачаалж чадсангүй.</p>\';
        });
    }

    // Tab switch listener
    document.querySelectorAll(\'[data-bs-toggle="tab"]\').forEach(function (el) {
        el.addEventListener("shown.bs.tab", function (e) {
            if (e.target.getAttribute("href") === "#tab-orders") {
                loadOrders();
            }
        });
    });

    // Sync sidebar tab link with main tab
    document.querySelectorAll(\'[data-bs-toggle="tab"]\').forEach(function(el) {
        el.addEventListener("click", function(e) {
            e.preventDefault();
            const target = this.getAttribute("href");
            document.querySelectorAll(\'[data-bs-toggle="tab"]\').forEach(function(t) {
                t.classList.remove("active");
            });
            this.classList.add("active");
            document.querySelectorAll(".tab-pane").forEach(function(p) {
                p.classList.remove("show", "active");
            });
            const pane = document.querySelector(target);
            if (pane) { pane.classList.add("show", "active"); }
            if (target === "#tab-orders") { loadOrders(); }
        });
    });
}());
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
