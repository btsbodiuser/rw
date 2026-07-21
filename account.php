<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . url('login.php?redirect=' . urlencode(url('account.php'))));
    exit;
}

$user = getSessionUser();
$activeAccountPage = 'dashboard';
$page_title = 'Миний бүртгэл';
require_once __DIR__ . '/includes/header.php';

// Order stat counts for this customer
$db = getDB();
$customerId = (int)($user['id'] ?? 0);

$pendingStatuses = ['pending', 'confirmed', 'cargo_shipping', 'cargo_arrived', 'ready_pickup', 'delivering', 'partially_delivered'];
$successStatuses = ['delivered', 'picked_up', 'completed'];

$countByStatuses = function (array $statuses) use ($db, $customerId): int {
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status IN ($placeholders)");
    $stmt->execute(array_merge([$customerId], $statuses));
    return (int)$stmt->fetchColumn();
};

$pendingCount = $countByStatuses($pendingStatuses);
$successCount = $countByStatuses($successStatuses);
$totalStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status != 'cancelled'");
$totalStmt->execute([$customerId]);
$totalCount = (int)$totalStmt->fetchColumn();
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
                    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>

                    <!-- Main Content -->
                    <div class="col-xl-9">
                        <div class="my-account-content">
                            <div class="acount-order_stats">
                                <div class="row g-3">
                                    <div class="col-md-4 col-6">
                                        <div class="order-box">
                                            <div class="order_icon">
                                                <i class="icon icon-truck"></i>
                                            </div>
                                            <div class="order_info">
                                                <p class="info_label h6">Хүлээгдэж буй</p>
                                                <h2 class="info_count type-semibold"><?= $pendingCount ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <div class="order-box">
                                            <div class="order_icon">
                                                <i class="icon icon-check-fat"></i>
                                            </div>
                                            <div class="order_info">
                                                <p class="info_label h6">Амжилттай захиалга</p>
                                                <h2 class="info_count type-semibold"><?= $successCount ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <div class="order-box">
                                            <div class="order_icon">
                                                <i class="icon icon-box-arrow-up"></i>
                                            </div>
                                            <div class="order_info">
                                                <p class="info_label h6">Нийт захиалга</p>
                                                <h2 class="info_count type-semibold"><?= $totalCount ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="account-my_order mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2 class="account-title type-semibold mb-0">Сүүлийн захиалгууд</h2>
                                    <a href="<?= url('account-orders.php') ?>" class="link h6 fw-semibold">Бүгдийг харах</a>
                                </div>
                                <div id="recent-orders-wrap">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-secondary" role="status"></div>
                                        <p class="h6 text-main mt-2">Ачааллаж байна...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /Account -->

<?php
$statusLabels = [
    'pending'             => ['label' => 'Хүлээгдэж байна',        'css' => 'stt-pending'],
    'confirmed'           => ['label' => 'Баталгаажсан',            'css' => 'stt-complete'],
    'cargo_shipping'      => ['label' => 'Ачаа явж байна',          'css' => 'stt-delivery'],
    'cargo_arrived'       => ['label' => 'Ачаа ирсэн',              'css' => 'stt-delivery'],
    'ready_pickup'        => ['label' => 'Авахад бэлэн',            'css' => 'stt-complete'],
    'delivering'          => ['label' => 'Хүргэлтэнд',              'css' => 'stt-delivery'],
    'partially_delivered' => ['label' => 'Хэсэгчлэн хүргэсэн',      'css' => 'stt-delivery'],
    'delivered'           => ['label' => 'Хүргэгдсэн',              'css' => 'stt-complete'],
    'picked_up'           => ['label' => 'Авсан',                   'css' => 'stt-complete'],
    'completed'           => ['label' => 'Дууссан',                 'css' => 'stt-complete'],
    'cancelled'           => ['label' => 'Цуцлагдсан',              'css' => 'stt-cancel'],
];
$extra_scripts = '<script>
(function () {
    const BASE  = ' . json_encode(getBaseUrl()) . ';
    const TOKEN = ' . json_encode($_SESSION['token'] ?? '') . ';
    const STATUS = ' . json_encode($statusLabels) . ';

    fetch(BASE + "backend/api/customer-orders.php", {
        headers: {"Authorization": "Bearer " + TOKEN}
    })
    .then(r => r.json())
    .then(data => {
        const wrap = document.getElementById("recent-orders-wrap");
        const orders = (data.orders || []).filter(o => o.status !== "cancelled").slice(0, 5);
        if (orders.length === 0) {
            wrap.innerHTML = \'<div class="box-text_empty type-shop_cart text-center py-5"><span class="icon"><i class="icon-box-arrow-down" style="font-size:3rem;color:#ccc;"></i></span><h5 class="text-main mt-3">Захиалга олдсонгүй</h5></div>\';
            return;
        }
        let rows = "";
        orders.forEach(o => {
            const st = STATUS[o.status] || {label: o.status, css: "stt-pending"};
            const first = o.items && o.items[0];
            const img = first ? first.image : "";
            const name = first ? (first.product_name_mn || first.product_name) : "";
            const variant = first && first.variant_label ? \'<span> \' + first.variant_label + "</span>" : "";
            const moreCount = o.items && o.items.length > 1 ? " +" + (o.items.length - 1) : "";
            rows += `<tr class="tb-order-item">
                <td class="tb-order_code">#${o.order_number}</td>
                <td>
                    <div class="tb-order_product">
                        ${img ? `<a href="${BASE}track-order.php?order=${encodeURIComponent(o.order_number)}" class="img-prd"><img src="${img}" alt=""></a>` : ""}
                        <div class="infor-prd">
                            <h6><a href="${BASE}track-order.php?order=${encodeURIComponent(o.order_number)}" class="prd_name link">${name}${moreCount}</a></h6>
                            <p class="prd_select text-small">${variant}</p>
                        </div>
                    </div>
                </td>
                <td class="tb-order_price">${Number(o.total).toLocaleString()}₮</td>
                <td><div class="tb-order_status ${st.css}">${st.label}</div></td>
            </tr>`;
        });
        wrap.innerHTML = `<div class="overflow-auto"><table class="table-my_order order_recent">
            <thead><tr>
                <th>Захиалга</th>
                <th>Бараа</th>
                <th>Дүн</th>
                <th>Төлөв</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table></div>`;
    })
    .catch(() => {
        document.getElementById("recent-orders-wrap").innerHTML = \'<p class="text-center py-4 text-main">Захиалгуудыг ачаалж чадсангүй.</p>\';
    });
}());
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
