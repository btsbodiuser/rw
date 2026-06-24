<?php
$pageTitle = 'Хэрэглэгчийн дэлгэрэнгүй';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: index.php?page=customers'); exit; }

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    setFlash('error', 'Хэрэглэгч олдсонгүй.');
    header('Location: index.php?page=customers');
    exit;
}

// Addresses
$addrStmt = $db->prepare("
    SELECT a.*, d.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name
    FROM customer_addresses a
    LEFT JOIN districts d ON d.id = a.district_id
    LEFT JOIN khoroos k ON k.id = a.khoroo_id
    WHERE a.customer_id = ?
    ORDER BY a.is_default DESC, a.created_at DESC
");
$addrStmt->execute([$id]);
$addresses = $addrStmt->fetchAll();

// Orders
$orderStmt = $db->prepare("
    SELECT o.*, d.name_mn as district_name,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    LEFT JOIN districts d ON o.district_id = d.id
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
    LIMIT 50
");
$orderStmt->execute([$id]);
$orders = $orderStmt->fetchAll();

// Stats
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0) as total_spent,
        COALESCE(AVG(CASE WHEN status != 'cancelled' THEN total ELSE NULL END), 0) as avg_order
    FROM orders WHERE customer_id = ?
");
$statsStmt->execute([$id]);
$stats = $statsStmt->fetch();

$statusColors = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'cargo_shipping' => 'bg-indigo-100 text-indigo-800',
    'cargo_arrived' => 'bg-purple-100 text-purple-800',
    'ready_pickup' => 'bg-cyan-100 text-cyan-800',
    'delivering' => 'bg-orange-100 text-orange-800',
    'delivered' => 'bg-green-100 text-green-800',
    'picked_up' => 'bg-green-100 text-green-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'cancelled' => 'bg-red-100 text-red-800',
];

$paymentColors = [
    'pending' => 'bg-yellow-100 text-yellow-700',
    'paid' => 'bg-green-100 text-green-700',
    'refunded' => 'bg-red-100 text-red-700',
];

// Function to determine registration method
function getRegistrationMethod($customer) {
    if (!empty($customer['google_id'])) {
        return ['Google', 'bg-red-100 text-red-800', '🔵'];
    }
    if (!empty($customer['facebook_id'])) {
        return ['Facebook', 'bg-blue-100 text-blue-800', '📘'];
    }
    if (!empty($customer['email'])) {
        return ['Имэйл', 'bg-green-100 text-green-800', '📧'];
    }
    return ['Утас', 'bg-gray-100 text-gray-800', '📱'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Back Button -->
<div class="mb-6">
    <a href="index.php?page=customers" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 text-sm font-medium transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Хэрэглэгч рүү буцах
    </a>
</div>

<!-- Customer Info + Stats -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
    <!-- Customer Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                <span class="text-blue-700 font-bold text-xl"><?= strtoupper(mb_substr($customer['name'] ?: $customer['phone'], 0, 1)) ?></span>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-900"><?= e($customer['name'] ?: '—') ?></h2>
                <p class="text-sm text-gray-500">ID: <?= $customer['id'] ?></p>
            </div>
        </div>

        <div class="space-y-3">
            <!-- Phone -->
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Утас</p>
                <p class="text-sm text-gray-900"><?= e($customer['phone']) ?></p>
            </div>

            <!-- Email -->
            <?php if (!empty($customer['email'])): ?>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Имэйл</p>
                <p class="text-sm text-gray-900"><?= e($customer['email']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Registration Method -->
            <?php $regMethod = getRegistrationMethod($customer); ?>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Бүртгүүлсэн арга</p>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium <?= $regMethod[1] ?>">
                    <span><?= $regMethod[2] ?></span>
                    <?= e($regMethod[0]) ?>
                </span>
            </div>

            <!-- Social IDs -->
            <?php if (!empty($customer['google_id'])): ?>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Google ID</p>
                <p class="text-sm text-gray-900 font-mono"><?= e($customer['google_id']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($customer['facebook_id'])): ?>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Facebook ID</p>
                <p class="text-sm text-gray-900 font-mono"><?= e($customer['facebook_id']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Avatar -->
            <?php if (!empty($customer['avatar'])): ?>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Зураг</p>
                <img src="<?= e($customer['avatar']) ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover">
            </div>
            <?php endif; ?>

            <!-- Registration Date -->
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Бүртгүүлсэн огноо</p>
                <p class="text-sm text-gray-900"><?= date('Y-m-d H:i:s', strtotime($customer['created_at'])) ?></p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">Нийт захиалга</p>
        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_orders'] ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">Нийт зарцуулсан</p>
        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_spent']) ?>₮</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">Дунд. захиалга</p>
        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['avg_order']) ?>₮</p>
    </div>
</div>

<!-- Addresses -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Хадгалсан хаяг (<?= count($addresses) ?>)</h3>
    <?php if (empty($addresses)): ?>
        <p class="text-gray-400 text-sm py-4 text-center">Хадгалсан хаяг байхгүй</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($addresses as $addr): ?>
                <div class="border border-gray-200 rounded-lg p-4 <?= $addr['is_default'] ? 'ring-2 ring-blue-200 bg-blue-50/30' : '' ?>">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="text-sm font-medium text-gray-900">
                                <?= e($addr['district_name'] ?? '') ?>
                                <?php if ($addr['khoroo_name']): ?>
                                    <?= e($addr['khoroo_name']) ?>
                                <?php elseif ($addr['khoroo_number']): ?>
                                    <?= e($addr['khoroo_number']) ?>-р хороо
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($addr['is_default']): ?>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Үндсэн</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-600"><?= e($addr['address']) ?></p>
                    <?php if ($addr['detail_address']): ?>
                        <p class="text-sm text-gray-500"><?= e($addr['detail_address']) ?></p>
                    <?php endif; ?>
                    <?php if ($addr['label']): ?>
                        <p class="text-xs text-gray-400 mt-2"><?= e($addr['label']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Orders History -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Захиалгын түүх</h3>
    </div>
    <?php if (empty($orders)): ?>
        <p class="text-gray-400 text-sm py-12 text-center">Захиалга байхгүй</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Захиалга №</th>
                        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Тоо</th>
                        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Нийт</th>
                        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Төлөв</th>
                        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Төлбөр</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Хүргэлт</th>
                        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Огноо</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($orders as $o): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3">
                                <a href="index.php?page=order-detail&id=<?= $o['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                    #<?= e($o['order_number']) ?>
                                </a>
                            </td>
                            <td class="px-5 py-3 text-center text-sm text-gray-700"><?= $o['item_count'] ?></td>
                            <td class="px-5 py-3 text-right text-sm font-medium text-gray-900"><?= number_format($o['total']) ?>₮</td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$o['status']] ?? 'bg-gray-100 text-gray-700' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $o['status'])) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $paymentColors[$o['payment_status']] ?? 'bg-gray-100 text-gray-700' ?>">
                                    <?= ucfirst($o['payment_status']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600">
                                <?php if ($o['district_name']): ?>
                                    <?= e($o['district_name']) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-right text-sm text-gray-500"><?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
