<?php
$pageTitle = 'Багц дэлгэрэнгүй';
$db = getDB();

$batchId = (int)($_GET['id'] ?? 0);
if (!$batchId) {
    header('Location: index.php?page=deliveries&tab=active');
    exit;
}

// Get batch
$batchStmt = $db->prepare("SELECT b.*, dd.name as driver_name, dd.phone as driver_phone
    FROM delivery_batches b
    JOIN delivery_drivers dd ON b.driver_id = dd.id
    WHERE b.id = ?");
$batchStmt->execute([$batchId]);
$batch = $batchStmt->fetch();

if (!$batch) {
    setFlash('error', 'Багц олдсонгүй.');
    header('Location: index.php?page=deliveries&tab=active');
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_delivery') {
        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $allowed = ['assigned', 'picked_up', 'delivered', 'failed'];
        if ($deliveryId && in_array($newStatus, $allowed)) {
            $updates = ['status = ?'];
            $params = [$newStatus];

            if ($newStatus === 'picked_up') {
                $updates[] = 'picked_up_at = NOW()';
            } elseif ($newStatus === 'delivered') {
                $updates[] = 'delivered_at = NOW()';
                $orderIdStmt = $db->prepare("SELECT order_id FROM deliveries WHERE id = ?");
                $orderIdStmt->execute([$deliveryId]);
                $orderId = $orderIdStmt->fetchColumn();
                if ($orderId) {
                    $db->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$orderId]);
                }
            }

            $params[] = $deliveryId;
            $db->prepare("UPDATE deliveries SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

            // Auto-complete batch if all done
            $remaining = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE batch_id = ? AND status IN ('assigned','picked_up')");
            $remaining->execute([$batchId]);
            if ($remaining->fetchColumn() == 0) {
                $db->prepare("UPDATE delivery_batches SET status = 'completed', completed_at = NOW() WHERE id = ? AND status != 'completed'")->execute([$batchId]);
            }

            setFlash('success', 'Хүргэлтийн төлөв шинэчлэгдлээ.');
        }
        header('Location: index.php?page=delivery-batch-detail&id=' . $batchId);
        exit;
    }

    if ($action === 'unassign_delivery') {
        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        if ($deliveryId) {
            $db->beginTransaction();
            try {
                // Get the order_id
                $orderStmt = $db->prepare("SELECT order_id FROM deliveries WHERE id = ? AND batch_id = ?");
                $orderStmt->execute([$deliveryId, $batchId]);
                $orderId = $orderStmt->fetchColumn();

                if ($orderId) {
                    // Mark delivery as failed with unassign note
                    $db->prepare("UPDATE deliveries SET status = 'failed', notes = CONCAT(COALESCE(notes,''), ' [Хуваарилалт цуцлагдсан]') WHERE id = ?")->execute([$deliveryId]);

                    // Revert order status back so it reappears in delivery pool
                    $db->prepare("UPDATE orders SET status = 'confirmed', ready_for_delivery = 1 WHERE id = ?")->execute([$orderId]);

                    // If no active deliveries remain in batch, mark batch completed
                    $remaining = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE batch_id = ? AND status IN ('assigned','picked_up')");
                    $remaining->execute([$batchId]);
                    if ($remaining->fetchColumn() == 0) {
                        $db->prepare("UPDATE delivery_batches SET status = 'completed', completed_at = NOW() WHERE id = ? AND status NOT IN ('completed','cancelled')")->execute([$batchId]);
                    }

                    $db->commit();
                    auditLog('delivery_unassigned', 'delivery', $deliveryId, 'admin', $GLOBALS['currentAdmin']['id'] ?? null,
                        json_encode(['order_id' => $orderId, 'batch_id' => $batchId]));
                    setFlash('success', 'Хүргэлтийн хуваарилалт цуцлагдлаа.');
                } else {
                    $db->rollBack();
                    setFlash('error', 'Хүргэлт олдсонгүй.');
                }
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', 'Алдаа: ' . $e->getMessage());
            }
        }
        header('Location: index.php?page=delivery-batch-detail&id=' . $batchId);
        exit;
    }

    if ($action === 'update_batch') {
        $newStatus = $_POST['new_status'] ?? '';
        $allowed = ['in_progress', 'completed', 'cancelled'];
        if (in_array($newStatus, $allowed)) {
            $upd = ['status = ?'];
            $prm = [$newStatus];
            if ($newStatus === 'completed') {
                $upd[] = 'completed_at = NOW()';
                // Mark all remaining as delivered
                $pending = $db->prepare("SELECT id, order_id FROM deliveries WHERE batch_id = ? AND status IN ('assigned','picked_up')");
                $pending->execute([$batchId]);
                foreach ($pending->fetchAll() as $p) {
                    $db->prepare("UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ?")->execute([$p['id']]);
                    $db->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$p['order_id']]);
                }
            }
            $prm[] = $batchId;
            $db->prepare("UPDATE delivery_batches SET " . implode(', ', $upd) . " WHERE id = ?")->execute($prm);
            setFlash('success', 'Багцын төлөв шинэчлэгдлээ.');
        }
        header('Location: index.php?page=delivery-batch-detail&id=' . $batchId);
        exit;
    }
}

// Get deliveries in this batch
$deliveries = $db->prepare("SELECT d.*,
    o.order_number, o.customer_name, o.customer_phone, o.total, o.payment_status, o.delivery_fee,
    dist.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name,
    o.address, o.detail_address,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE d.batch_id = ?
    ORDER BY FIELD(d.status, 'assigned', 'picked_up', 'delivered', 'failed'), d.id ASC");
$deliveries->execute([$batchId]);
$deliveryList = $deliveries->fetchAll();

$totalOrders = 0;
$deliveredCount = 0;
$failedCount = 0;
$batchTotal = 0;
foreach ($deliveryList as $d) {
    $isUnassigned = ($d['status'] === 'failed' && strpos($d['notes'] ?? '', 'Хуваарилалт цуцлагдсан') !== false);
    if ($isUnassigned) continue;
    $totalOrders++;
    if ($d['status'] === 'delivered') $deliveredCount++;
    if ($d['status'] === 'failed') $failedCount++;
    $batchTotal += $d['total'];
}

$deliveryStatuses = [
    'assigned' => ['label' => 'Хуваарилсан', 'class' => 'bg-yellow-100 text-yellow-700'],
    'picked_up' => ['label' => 'Авсан', 'class' => 'bg-blue-100 text-blue-700'],
    'delivered' => ['label' => 'Хүргэсэн', 'class' => 'bg-green-100 text-green-700'],
    'failed'    => ['label' => 'Амжилтгүй', 'class' => 'bg-red-100 text-red-700'],
];

$batchStatuses = [
    'assigned'    => ['label' => 'Хуваарилсан', 'class' => 'bg-yellow-100 text-yellow-700'],
    'in_progress' => ['label' => 'Хүргэж байна', 'class' => 'bg-blue-100 text-blue-700'],
    'completed'   => ['label' => 'Дууссан', 'class' => 'bg-green-100 text-green-700'],
    'cancelled'   => ['label' => 'Цуцалсан', 'class' => 'bg-red-100 text-red-700'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Back + Header -->
<div class="mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <a href="index.php?page=deliveries&tab=<?= in_array($batch['status'], ['assigned','in_progress']) ? 'active' : 'completed' ?>"
           class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Буцах
        </a>
        <div>
            <h2 class="text-lg font-bold text-gray-900">Багц #<?= $batch['id'] ?></h2>
            <p class="text-sm text-gray-500">
                <?= e($batch['driver_name']) ?> · <?= e($batch['driver_phone']) ?>
                · <?= formatDateShort($batch['created_at']) ?>
            </p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-medium <?= $batchStatuses[$batch['status']]['class'] ?>">
            <?= $batchStatuses[$batch['status']]['label'] ?>
        </span>
        <?php if ($batch['status'] === 'assigned'): ?>
        <form method="POST" class="inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_batch">
            <input type="hidden" name="new_status" value="in_progress">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Хүргэлт эхлүүлэх</button>
        </form>
        <?php elseif ($batch['status'] === 'in_progress'): ?>
        <form method="POST" class="inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_batch">
            <input type="hidden" name="new_status" value="completed">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Бүгд хүргэсэн</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Summary -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Нийт захиалга</p>
        <p class="text-xl font-bold text-gray-900"><?= $totalOrders ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Хүргэсэн</p>
        <p class="text-xl font-bold text-green-600"><?= $deliveredCount ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Амжилтгүй</p>
        <p class="text-xl font-bold <?= $failedCount > 0 ? 'text-red-600' : 'text-gray-900' ?>"><?= $failedCount ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Үлдсэн</p>
        <p class="text-xl font-bold <?= ($totalOrders - $deliveredCount - $failedCount) > 0 ? 'text-orange-600' : 'text-gray-900' ?>"><?= $totalOrders - $deliveredCount - $failedCount ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Нийт дүн</p>
        <p class="text-xl font-bold text-gray-900"><?= formatPrice($batchTotal) ?></p>
    </div>
</div>

<!-- Progress bar -->
<?php $pct = $totalOrders > 0 ? min(round(($deliveredCount + $failedCount) / $totalOrders * 100), 100) : 0; ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex items-center gap-4">
        <div class="flex-1">
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="h-3 rounded-full transition-all <?= $pct >= 100 ? 'bg-green-500' : 'bg-blue-500' ?>" style="width: <?= $pct ?>%"></div>
            </div>
        </div>
        <span class="text-sm font-medium text-gray-600"><?= $pct ?>%</span>
    </div>
</div>

<?php if ($batch['notes']): ?>
<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
    <p class="text-sm text-yellow-800"><strong>Тэмдэглэл:</strong> <?= e($batch['notes']) ?></p>
</div>
<?php endif; ?>

<!-- Deliveries list -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Захиалга</th>
                    <th class="px-5 py-3 text-left">Хэрэглэгч</th>
                    <th class="px-5 py-3 text-left">Хаяг</th>
                    <th class="px-5 py-3 text-right">Дүн</th>
                    <th class="px-5 py-3 text-center">Төлбөр</th>
                    <th class="px-5 py-3 text-center">Төлөв</th>
                    <th class="px-5 py-3 text-center">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($deliveryList as $i => $del): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-5 py-3">
                        <a href="index.php?page=order-detail&id=<?= $del['order_id'] ?>&return=<?= urlencode('index.php?page=delivery-batch-detail&id=' . $batchId) ?>" class="font-medium text-blue-600 hover:underline">#<?= e($del['order_number']) ?></a>
                        <p class="text-xs text-gray-400"><?= $del['item_count'] ?> бараа</p>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-gray-900"><?= e($del['customer_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= e($del['customer_phone']) ?></p>
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-600 max-w-[200px]">
                        <?= e($del['district_name'] ?? '') ?><?= $del['khoroo_name'] ? ', ' . e($del['khoroo_name']) : ($del['khoroo_number'] ? ', ' . $del['khoroo_number'] . '-р хороо' : '') ?>
                        <?php if ($del['address']): ?><br><?= e($del['address']) ?><?php endif; ?>
                        <?php if ($del['detail_address']): ?><br><span class="text-gray-400"><?= e($del['detail_address']) ?></span><?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right font-medium"><?= formatPrice($del['total']) ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $del['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= $del['payment_status'] === 'paid' ? 'Төлсөн' : 'Төлөөгүй' ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $deliveryStatuses[$del['status']]['class'] ?>">
                            <?= $deliveryStatuses[$del['status']]['label'] ?>
                        </span>
                        <?php if ($del['delivered_at']): ?>
                            <p class="text-xs text-gray-400 mt-1"><?= formatDateShort($del['delivered_at']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?php if ($del['status'] === 'assigned'): ?>
                        <div class="flex gap-1 justify-center">
                            <form method="POST" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_delivery">
                                <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                                <input type="hidden" name="new_status" value="picked_up">
                                <button type="submit" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100">Авсан</button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Хуваарилалт цуцлах уу?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="unassign_delivery">
                                <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                                <button type="submit" class="px-3 py-1.5 bg-gray-50 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-100">Цуцлах</button>
                            </form>
                        </div>
                        <?php elseif ($del['status'] === 'picked_up'): ?>
                        <div class="flex gap-1 justify-center">
                            <form method="POST" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_delivery">
                                <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                                <input type="hidden" name="new_status" value="delivered">
                                <button type="submit" class="px-3 py-1.5 bg-green-50 text-green-600 rounded-lg text-xs font-medium hover:bg-green-100">Хүргэсэн</button>
                            </form>
                            <form method="POST" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_delivery">
                                <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                                <input type="hidden" name="new_status" value="failed">
                                <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">Амжилтгүй</button>
                            </form>
                        </div>
                        <?php elseif ($del['status'] === 'delivered'): ?>
                            <span class="text-green-500">✓</span>
                        <?php elseif ($del['status'] === 'failed'): ?>
                            <span class="text-red-500">✗</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
