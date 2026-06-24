<?php
$pageTitle = 'Жолооч хуваарилах';
$db = getDB();

$orderId = (int)($_GET['order_id'] ?? 0);

if (!$orderId) {
    header('Location: index.php?page=deliveries');
    exit;
}

// Get order
$stmt = $db->prepare("SELECT o.*, dist.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name
    FROM orders o
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Захиалга олдсонгүй.');
    header('Location: index.php?page=deliveries');
    exit;
}

// Check if already assigned with active delivery
$existingDelivery = $db->prepare("SELECT d.*, dd.name as driver_name FROM deliveries d JOIN delivery_drivers dd ON d.driver_id = dd.id WHERE d.order_id = ? AND d.status IN ('assigned','picked_up')");
$existingDelivery->execute([$orderId]);
$existing = $existingDelivery->fetch();

// Order items
$items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$orderId]);
$orderItems = $items->fetchAll();

// Active drivers
$drivers = $db->query("SELECT dd.*, 
    (SELECT COUNT(*) FROM deliveries d WHERE d.driver_id = dd.id AND d.status IN ('assigned','picked_up')) as active_count
    FROM delivery_drivers dd WHERE dd.is_active = 1 ORDER BY dd.name")->fetchAll();

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $driverId = (int)($_POST['driver_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$driverId) {
        setFlash('error', 'Жолооч сонгоно уу.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // If there's an existing active delivery, mark it as failed
    if ($existing) {
        $db->prepare("UPDATE deliveries SET status = 'failed', notes = CONCAT(COALESCE(notes,''), ' [Дахин хуваарилсан]') WHERE id = ?")->execute([$existing['id']]);
    }

    $stmt = $db->prepare("INSERT INTO deliveries (order_id, driver_id, notes) VALUES (?, ?, ?)");
    $stmt->execute([$orderId, $driverId, $notes ?: null]);

    // Update order status to delivering
    $db->prepare("UPDATE orders SET status = 'delivering' WHERE id = ?")->execute([$orderId]);

    auditLog('delivery_assigned', 'order', $orderId, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, 
        json_encode(['driver_id' => $driverId]));

    setFlash('success', 'Жолооч хуваарилагдлаа.');
    header('Location: index.php?page=deliveries&tab=active');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=deliveries" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Хүргэлт руу буцах
        </a>
    </div>

    <!-- Order Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Захиалга #<?= e($order['order_number']) ?></h3>
            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= $order['payment_status'] === 'paid' ? 'Төлсөн' : 'Төлөөгүй' ?>
            </span>
        </div>
        <div class="grid md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Хэрэглэгч</p>
                <p class="font-medium"><?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?></p>
            </div>
            <div>
                <p class="text-gray-500">Хаяг</p>
                <p class="font-medium"><?= e($order['district_name'] ?? '') ?><?= $order['khoroo_name'] ? ', ' . e($order['khoroo_name']) : ($order['khoroo_number'] ? ', ' . $order['khoroo_number'] . '-р хороо' : '') ?></p>
                <?php if ($order['address']): ?><p class="text-gray-600"><?= e($order['address']) ?></p><?php endif; ?>
                <?php if ($order['detail_address']): ?><p class="text-gray-600"><?= e($order['detail_address']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between text-sm">
            <span class="text-gray-500"><?= count($orderItems) ?> бараа</span>
            <span class="font-bold"><?= formatPrice($order['total']) ?></span>
        </div>
    </div>

    <?php if ($existing): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
        <p class="text-sm text-yellow-800">⚠ Энэ захиалга одоогоор <strong><?= e($existing['driver_name']) ?></strong> жолоочид хуваарилагдсан байна. Шинэ жолооч сонговол хуучныг солино.</p>
    </div>
    <?php endif; ?>

    <!-- Assign Driver -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Жолооч сонгох</h3>
        <?php if (empty($drivers)): ?>
            <p class="text-gray-400 text-sm">Идэвхтэй жолооч бүртгэгдээгүй байна. <a href="index.php?page=driver-form" class="text-blue-600 hover:underline">Жолооч нэмэх →</a></p>
        <?php else: ?>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div class="space-y-2">
                <?php foreach ($drivers as $d): ?>
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 cursor-pointer transition">
                    <input type="radio" name="driver_id" value="<?= $d['id'] ?>" class="text-blue-600 focus:ring-blue-500" required>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900"><?= e($d['name']) ?></p>
                        <p class="text-xs text-gray-500"><?= e($d['phone']) ?></p>
                    </div>
                    <?php if ($d['active_count'] > 0): ?>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs"><?= $d['active_count'] ?> идэвхтэй</span>
                    <?php else: ?>
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Чөлөөтэй</span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Тэмдэглэл (заавал биш)</label>
                <textarea name="notes" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Нэмэлт мэдээлэл..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Хуваарилах</button>
                <a href="index.php?page=deliveries" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Болих</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
