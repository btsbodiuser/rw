<?php
$pageTitle = 'Хянах самбар';
$db = getDB();

// Auto-cancel unpaid orders older than 1 hour
cancelExpiredOrders();
// Auto-confirm paid pending orders
confirmPaidOrders();

// Stats
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$activeProducts = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
$totalOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$todayOrders = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn();
$todayRevenue = $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid' AND status != 'cancelled'")->fetchColumn();
$todayPayments = $db->query("SELECT 
    COALESCE(SUM(payment_cash), 0) as cash_total,
    COALESCE(SUM(payment_card), 0) as card_total,
    COALESCE(SUM(payment_transfer), 0) as transfer_total,
    COALESCE(SUM(payment_transfer_nomin), 0) as transfer_nomin_total
    FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid' AND status != 'cancelled'")->fetch();
$monthRevenue = $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND payment_status = 'paid' AND status != 'cancelled'")->fetchColumn();
$pendingOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$posToday = $db->query("SELECT COUNT(*) FROM orders WHERE order_type = 'pos' AND DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn();
$lowStockProducts = $db->query("SELECT COUNT(*) FROM products WHERE type = 'ready' AND stock <= 5 AND is_active = 1")->fetchColumn();

// Open cargo batch
$openBatch = getOpenBatch();

// Recent orders
$recentOrders = $db->query("SELECT o.*, 
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count 
    FROM orders o ORDER BY o.created_at DESC LIMIT 10")->fetchAll();

// Low stock products
$lowStockItems = $db->query("SELECT p.*, s.name as shop_name, c.name as category_name 
    FROM products p 
    LEFT JOIN shops s ON p.shop_id = s.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.type = 'ready' AND p.stock <= 5 AND p.is_active = 1 
    ORDER BY p.stock ASC LIMIT 10")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats Grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Өнөөдрийн захиалга</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= $todayOrders ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">POS: <?= $posToday ?> | Нийт: <?= $totalOrders ?></p>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Өнөөдрийн орлого</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= formatPrice($todayRevenue) ?></p>
            </div>
            <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Энэ сар: <?= formatPrice($monthRevenue) ?></p>
        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
            <?php if ($todayPayments['cash_total'] > 0): ?><span class="text-[10px] text-gray-400">💵<?= formatPrice($todayPayments['cash_total']) ?></span><?php endif; ?>
            <?php if ($todayPayments['card_total'] > 0): ?><span class="text-[10px] text-gray-400">💳<?= formatPrice($todayPayments['card_total']) ?></span><?php endif; ?>
            <?php if ($todayPayments['transfer_total'] > 0): ?><span class="text-[10px] text-gray-400">📲<?= formatPrice($todayPayments['transfer_total']) ?></span><?php endif; ?>
            <?php if ($todayPayments['transfer_nomin_total'] > 0): ?><span class="text-[10px] text-gray-400">🏪<?= formatPrice($todayPayments['transfer_nomin_total']) ?></span><?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Хүлээгдэж буй захиалга</p>
                <p class="text-2xl font-bold <?= $pendingOrders > 0 ? 'text-orange-600' : 'text-gray-900' ?> mt-1"><?= $pendingOrders ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <a href="index.php?page=orders&status=pending" class="text-xs text-blue-600 hover:underline mt-2 inline-block">Хүлээгдэж буй →</a>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Бүтээгдэхүүн</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= $activeProducts ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <p class="text-xs <?= $lowStockProducts > 0 ? 'text-red-500 font-medium' : 'text-gray-400' ?> mt-2">
            <?= $lowStockProducts > 0 ? "⚠ $lowStockProducts нөөц бага" : "Нөөц хангалттай" ?>
        </p>
    </div>
</div>

<!-- Cargo Batch Alert -->
<?php if ($openBatch): ?>
<div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        </div>
        <div>
            <p class="font-medium text-blue-900"><?= e($openBatch['name']) ?></p>
            <p class="text-sm text-blue-600">Due: <?= formatDate($openBatch['due_date']) ?> | Rate: <?= formatPrice($openBatch['cargo_rate_per_kg']) ?>/kg</p>
        </div>
    </div>
    <a href="index.php?page=cargo-batches" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Удирдах</a>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-2 gap-6">
    <!-- Recent Orders -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Сүүлийн захиалгауд</h3>
            <a href="index.php?page=orders" class="text-sm text-blue-600 hover:underline">Бүгдийг харах</a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php if (empty($recentOrders)): ?>
                <div class="p-8 text-center text-gray-400">Захиалга байхгүй</div>
            <?php else: ?>
                <?php foreach ($recentOrders as $order): ?>
                <a href="index.php?page=order-detail&id=<?= $order['id'] ?>" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium <?= $order['order_type'] === 'pos' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?>">
                            <?= $order['order_type'] === 'pos' ? 'POS' : 'ON' ?>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?= e($order['order_number']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($order['customer_name'] ?: 'Зочин') ?> · <?= $order['item_count'] ?> бараа</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?= formatPrice($order['total']) ?></p>
                        <div class="mt-1"><?= orderStatusLabel($order['status']) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Нөөц дууссан анхааруулга</h3>
            <a href="index.php?page=products" class="text-sm text-blue-600 hover:underline">Бүгдийг харах</a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php if (empty($lowStockItems)): ?>
                <div class="p-8 text-center text-gray-400 text-sm">Бүх бүтээгдэхүүний нөөц хангалттай байна 👍</div>
            <?php else: ?>
                <?php foreach ($lowStockItems as $p): ?>
                <a href="index.php?page=product-form&id=<?= $p['id'] ?>" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div>
                        <p class="text-sm font-medium text-gray-900"><?= e($p['name']) ?></p>
                        <p class="text-xs text-gray-400"><?= e($p['shop_name']) ?> · <?= e($p['category_name']) ?></p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $p['stock'] == 0 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' ?>">
                            <?= $p['stock'] == 0 ? 'Нөөц дууссан' : $p['stock'] . ' үлдсэн' ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
