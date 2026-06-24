<?php
$pageTitle = 'Хүргэлт';
$db = getDB();

$isPosReadonly = false;
$tab = $_GET['tab'] ?? 'prepare';
$searchQuery = trim($_GET['q'] ?? '');
$activeDrivers = $db->query("SELECT id, name, phone FROM delivery_drivers WHERE is_active = 1 ORDER BY name")->fetchAll();

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ── Mark orders as ready for delivery ──
    if ($action === 'mark_ready') {
        $orderIds = array_map('intval', $_POST['order_ids'] ?? []);
        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $db->prepare("UPDATE orders SET ready_for_delivery = 1 WHERE id IN ($placeholders)")->execute($orderIds);
            setFlash('success', count($orderIds) . ' захиалга бэлэн болгосон.');
        }
        header('Location: index.php?page=deliveries&tab=prepare');
        exit;
    }

    // ── Unmark ready ──
    if ($action === 'unmark_ready') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            $db->prepare("UPDATE orders SET ready_for_delivery = 0 WHERE id = ?")->execute([$orderId]);
        }
        header('Location: index.php?page=deliveries&tab=prepare');
        exit;
    }

    // ── Batch assign: create batch + assign multiple orders to driver ──
    if ($action === 'batch_assign') {
        $driverId = (int)($_POST['driver_id'] ?? 0);
        $orderIds = array_map('intval', $_POST['order_ids'] ?? []);
        $notes = trim($_POST['notes'] ?? '');

        if (!$driverId || empty($orderIds)) {
            setFlash('error', 'Жолооч болон захиалга сонгоно уу.');
        } else {
            $db->beginTransaction();
            try {
                // Create batch
                $batchStmt = $db->prepare("INSERT INTO delivery_batches (driver_id, notes) VALUES (?, ?)");
                $batchStmt->execute([$driverId, $notes ?: null]);
                $batchId = $db->lastInsertId();

                $insertDel = $db->prepare("INSERT INTO deliveries (batch_id, order_id, driver_id, notes) VALUES (?, ?, ?, ?)");
                $updateOrder = $db->prepare("UPDATE orders SET status = 'delivering' WHERE id = ?");
                // Mark old active deliveries as failed
                $failOld = $db->prepare("UPDATE deliveries SET status = 'failed', notes = CONCAT(COALESCE(notes,''), ' [Шинэ багцад оруулсан]') WHERE order_id = ? AND status IN ('assigned','picked_up')");

                $count = 0;
                foreach ($orderIds as $oid) {
                    $failOld->execute([$oid]);
                    $insertDel->execute([$batchId, $oid, $driverId, null]);
                    $updateOrder->execute([$oid]);
                    $count++;
                }

                $db->commit();
                auditLog('delivery_batch_created', 'delivery_batch', $batchId, 'admin', $GLOBALS['currentAdmin']['id'] ?? null,
                    json_encode(['driver_id' => $driverId, 'order_count' => $count]));
                setFlash('success', $count . ' захиалга багцалж жолоочид хуваарилагдлаа.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', 'Алдаа: ' . $e->getMessage());
            }
        }
        header('Location: index.php?page=deliveries&tab=active');
        exit;
    }

    // ── Unassign delivery from driver ──
    if ($action === 'unassign_delivery') {
        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        if ($deliveryId) {
            $db->beginTransaction();
            try {
                $delStmt = $db->prepare("SELECT order_id, batch_id FROM deliveries WHERE id = ?");
                $delStmt->execute([$deliveryId]);
                $delRow = $delStmt->fetch();

                if ($delRow) {
                    $orderId = $delRow['order_id'];
                    $bId = $delRow['batch_id'];

                    $db->prepare("UPDATE deliveries SET status = 'failed', notes = CONCAT(COALESCE(notes,''), ' [Хуваарилалт цуцлагдсан]') WHERE id = ?")->execute([$deliveryId]);
                    $db->prepare("UPDATE orders SET status = 'confirmed', ready_for_delivery = 1 WHERE id = ?")->execute([$orderId]);

                    if ($bId) {
                        $remaining = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE batch_id = ? AND status IN ('assigned','picked_up')");
                        $remaining->execute([$bId]);
                        if ($remaining->fetchColumn() == 0) {
                            $db->prepare("UPDATE delivery_batches SET status = 'completed', completed_at = NOW() WHERE id = ? AND status NOT IN ('completed','cancelled')")->execute([$bId]);
                        }
                    }

                    $db->commit();
                    auditLog('delivery_unassigned', 'delivery', $deliveryId, 'admin', $GLOBALS['currentAdmin']['id'] ?? null,
                        json_encode(['order_id' => $orderId, 'batch_id' => $bId]));
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
        $returnTab = $_POST['return_tab'] ?? 'active';
        header('Location: index.php?page=deliveries&tab=' . $returnTab);
        exit;
    }

    // ── Update single delivery status ──
    if ($action === 'update_status') {
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

            // Auto-complete batch if all deliveries done
            $batchCheck = $db->prepare("SELECT batch_id FROM deliveries WHERE id = ?");
            $batchCheck->execute([$deliveryId]);
            $bId = $batchCheck->fetchColumn();
            if ($bId) {
                $remaining = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE batch_id = ? AND status IN ('assigned','picked_up')");
                $remaining->execute([$bId]);
                if ($remaining->fetchColumn() == 0) {
                    $db->prepare("UPDATE delivery_batches SET status = 'completed', completed_at = NOW() WHERE id = ? AND status != 'completed'")->execute([$bId]);
                }
            }

            setFlash('success', 'Хүргэлтийн төлөв шинэчлэгдлээ.');
        }
        $returnTab = $_POST['return_tab'] ?? 'active';
        header('Location: index.php?page=deliveries&tab=' . $returnTab);
        exit;
    }

    // ── Update batch status ──
    if ($action === 'update_batch_status') {
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $allowed = ['in_progress', 'completed', 'cancelled'];
        if ($batchId && in_array($newStatus, $allowed)) {
            $upd = ['status = ?'];
            $prm = [$newStatus];
            if ($newStatus === 'completed') {
                $upd[] = 'completed_at = NOW()';
            }
            $prm[] = $batchId;
            $db->prepare("UPDATE delivery_batches SET " . implode(', ', $upd) . " WHERE id = ?")->execute($prm);

            if ($newStatus === 'completed') {
                // Mark all remaining assigned/picked_up deliveries in this batch as delivered
                $delIds = $db->prepare("SELECT id, order_id FROM deliveries WHERE batch_id = ? AND status IN ('assigned','picked_up')");
                $delIds->execute([$batchId]);
                $pending = $delIds->fetchAll();
                foreach ($pending as $p) {
                    $db->prepare("UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ?")->execute([$p['id']]);
                    $db->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$p['order_id']]);
                }
            }
            setFlash('success', 'Багцын төлөв шинэчлэгдлээ.');
        }
        header('Location: index.php?page=deliveries&tab=active');
        exit;
    }
}

// ── Stats ──
$statsStmt = $db->query("SELECT
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
    SUM(CASE WHEN status = 'picked_up' THEN 1 ELSE 0 END) as picked_up,
    SUM(CASE WHEN status = 'delivered' AND DATE(delivered_at) = CURDATE() THEN 1 ELSE 0 END) as delivered_today
    FROM deliveries");
$stats = $statsStmt->fetch();

// ── Waiting for cargo (orders with preorder items whose cargo batch hasn't arrived yet) ──
$waitingCargoOrders = $db->query("SELECT o.*, dist.name_mn as district_name,
    k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
    (SELECT COUNT(*) FROM order_items oi2
        LEFT JOIN cargo_batches cb2 ON oi2.cargo_batch_id = cb2.id
        WHERE oi2.order_id = o.id AND oi2.cargo_batch_id IS NOT NULL
        AND (cb2.status IS NULL OR cb2.status != 'arrived')
    ) as pending_cargo_count,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id AND cargo_batch_id IS NOT NULL) as cargo_item_count
    FROM orders o
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE o.fulfillment = 'delivery'
    AND o.status IN ('confirmed', 'cargo_shipping')
    AND o.id NOT IN (SELECT order_id FROM deliveries WHERE status IN ('assigned', 'picked_up'))
    AND EXISTS (
        SELECT 1 FROM order_items oi
        LEFT JOIN cargo_batches cb ON oi.cargo_batch_id = cb.id
        WHERE oi.order_id = o.id
        AND oi.cargo_batch_id IS NOT NULL
        AND (cb.status IS NULL OR cb.status != 'arrived')
    )
    ORDER BY o.created_at ASC")->fetchAll();

// ── Tab 1: Prepare — orders where ALL cargo batches arrived AND all cargo fees paid ──
$pendingOrders = $db->query("SELECT o.*, dist.name_mn as district_name, dist.id as dist_id,
    k.number as khoroo_number, k.id as khoroo_id_val, COALESCE(k.name, '') as khoroo_name,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE o.fulfillment = 'delivery'
    AND o.ready_for_delivery = 0
    AND o.id NOT IN (SELECT order_id FROM deliveries WHERE status IN ('assigned', 'picked_up'))
    AND (
        o.status IN ('cargo_arrived', 'ready_pickup')
        OR (
            o.status = 'confirmed'
            AND NOT EXISTS (
                SELECT 1 FROM order_items oi
                LEFT JOIN cargo_batches cb ON oi.cargo_batch_id = cb.id
                WHERE oi.order_id = o.id
                AND oi.cargo_batch_id IS NOT NULL
                AND (cb.status IS NULL OR cb.status != 'arrived')
            )
        )
    )
    AND NOT EXISTS (
        SELECT 1 FROM order_items oi
        WHERE oi.order_id = o.id
        AND oi.cargo_fee > 0
        AND oi.cargo_fee_paid = 0
    )
    ORDER BY o.created_at ASC")->fetchAll();

// ── Orders awaiting cargo fee payment (all items arrived, but cargo fee unpaid) ──
$waitingPaymentOrders = $db->query("SELECT o.*, dist.name_mn as district_name,
    k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
    (SELECT COALESCE(SUM(cargo_fee), 0) FROM order_items
        WHERE order_id = o.id AND cargo_fee > 0 AND cargo_fee_paid = 0) as unpaid_cargo_amount,
    (SELECT COUNT(*) FROM order_items
        WHERE order_id = o.id AND cargo_fee > 0 AND cargo_fee_paid = 0) as unpaid_cargo_count
    FROM orders o
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE o.fulfillment = 'delivery'
    AND o.ready_for_delivery = 0
    AND o.id NOT IN (SELECT order_id FROM deliveries WHERE status IN ('assigned', 'picked_up'))
    AND (
        o.status IN ('cargo_arrived', 'ready_pickup')
        OR (
            o.status = 'confirmed'
            AND NOT EXISTS (
                SELECT 1 FROM order_items oi
                LEFT JOIN cargo_batches cb ON oi.cargo_batch_id = cb.id
                WHERE oi.order_id = o.id
                AND oi.cargo_batch_id IS NOT NULL
                AND (cb.status IS NULL OR cb.status != 'arrived')
            )
        )
    )
    AND EXISTS (
        SELECT 1 FROM order_items oi
        WHERE oi.order_id = o.id
        AND oi.cargo_fee > 0
        AND oi.cargo_fee_paid = 0
    )
    ORDER BY o.created_at ASC")->fetchAll();

// ── Tab 2: Assign — marked ready, grouped by district ──
$readyOrders = $db->query("SELECT o.*, dist.name_mn as district_name, dist.id as dist_id,
    k.number as khoroo_number, k.id as khoroo_id_val, COALESCE(k.name, '') as khoroo_name,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE o.fulfillment = 'delivery'
    AND o.ready_for_delivery = 1
    AND o.id NOT IN (SELECT order_id FROM deliveries WHERE status IN ('assigned', 'picked_up'))
    AND (
        o.status IN ('cargo_arrived', 'ready_pickup')
        OR (
            o.status = 'confirmed'
            AND NOT EXISTS (
                SELECT 1 FROM order_items oi
                LEFT JOIN cargo_batches cb ON oi.cargo_batch_id = cb.id
                WHERE oi.order_id = o.id
                AND oi.cargo_batch_id IS NOT NULL
                AND (cb.status IS NULL OR cb.status != 'arrived')
            )
        )
    )
    ORDER BY dist.name_mn, k.number, o.customer_phone, o.created_at ASC")->fetchAll();

// Load order items for all pending + ready orders
$allPendingIds = array_merge(array_column($pendingOrders, 'id'), array_column($readyOrders, 'id'));
$orderItems = [];
if (!empty($allPendingIds)) {
    $placeholders = implode(',', array_fill(0, count($allPendingIds), '?'));
    $itemsStmt = $db->prepare("SELECT oi.order_id, oi.product_name, oi.quantity, oi.product_price, oi.line_total,
        p.image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.id");
    $itemsStmt->execute($allPendingIds);
    foreach ($itemsStmt->fetchAll() as $item) {
        $orderItems[$item['order_id']][] = $item;
    }
}

// Group ready orders by district+khoroo, then by customer
$grouped = [];
foreach ($readyOrders as $o) {
    $distName = $o['district_name'] ?? 'Дүүрэг тодорхойгүй';
    $khorooLabel = $o['khoroo_name'] ?: ($o['khoroo_number'] ? $o['khoroo_number'] . '-р хороо' : 'Хороо тодорхойгүй');
    $key = $distName . '||' . $khorooLabel;
    $custKey = $o['customer_phone'] ?: $o['customer_name'];
    $grouped[$key][$custKey][] = $o;
}

$allAssignableCount = count($readyOrders);

// ── Tab 2: Active batches ──
$activeBatches = $db->query("SELECT b.*,
    dd.name as driver_name, dd.phone as driver_phone,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id) as total_orders,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id AND status = 'delivered') as delivered_count,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id AND status = 'failed') as failed_count,
    (SELECT SUM(o.total) FROM deliveries d2 JOIN orders o ON d2.order_id = o.id WHERE d2.batch_id = b.id) as batch_total
    FROM delivery_batches b
    JOIN delivery_drivers dd ON b.driver_id = dd.id
    WHERE b.status IN ('assigned', 'in_progress')
    ORDER BY b.created_at DESC")->fetchAll();

// Also load unbatched active deliveries (legacy or single-assigned)
$unbatchedDeliveries = $db->query("SELECT d.*,
    dd.name as driver_name, dd.phone as driver_phone,
    o.order_number, o.customer_name, o.customer_phone, o.total, o.payment_status,
    dist.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name, o.address
    FROM deliveries d
    JOIN delivery_drivers dd ON d.driver_id = dd.id
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN districts dist ON o.district_id = dist.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    WHERE d.batch_id IS NULL AND d.status IN ('assigned', 'picked_up')
    ORDER BY d.assigned_at DESC")->fetchAll();

// ── Tab 3: Completed (last 7 days) ──
$completedBatches = $db->query("SELECT b.*,
    dd.name as driver_name,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id) as total_orders,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id AND status = 'delivered') as delivered_count,
    (SELECT COUNT(*) FROM deliveries WHERE batch_id = b.id AND status = 'failed') as failed_count,
    (SELECT SUM(o.total) FROM deliveries d2 JOIN orders o ON d2.order_id = o.id WHERE d2.batch_id = b.id) as batch_total
    FROM delivery_batches b
    JOIN delivery_drivers dd ON b.driver_id = dd.id
    WHERE b.status IN ('completed', 'cancelled')
    AND DATE(b.completed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY b.completed_at DESC")->fetchAll();

// ── Search results ──
$searchResults = [];
if ($searchQuery !== '') {
    $like = '%' . $searchQuery . '%';
    $searchResults = $db->prepare("SELECT o.*, dist.name_mn as district_name,
        k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name,
        d.id as delivery_id, d.status as delivery_status, d.assigned_at, d.delivered_at,
        dd.name as driver_name, dd.phone as driver_phone,
        db.id as batch_id, db.status as batch_status
        FROM orders o
        LEFT JOIN districts dist ON o.district_id = dist.id
        LEFT JOIN khoroos k ON o.khoroo_id = k.id
        LEFT JOIN deliveries d ON d.order_id = o.id AND d.id = (
            SELECT MAX(d2.id) FROM deliveries d2 WHERE d2.order_id = o.id
        )
        LEFT JOIN delivery_drivers dd ON d.driver_id = dd.id
        LEFT JOIN delivery_batches db ON d.batch_id = db.id
        WHERE o.fulfillment = 'delivery'
        AND o.status NOT IN ('cancelled', 'pending')
        AND (o.customer_phone LIKE ? OR o.order_number LIKE ? OR o.customer_name LIKE ?)
        ORDER BY o.created_at DESC
        LIMIT 50");
    $searchResults->execute([$like, $like, $like]);
    $searchResults = $searchResults->fetchAll();
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

<!-- Search Bar -->
<div class="mb-6">
    <form method="GET" class="flex gap-2">
        <input type="hidden" name="page" value="deliveries">
        <div class="relative flex-1 max-w-md">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Утас, захиалгын дугаар, нэрээр хайх..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
        </div>
        <button type="submit" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">Хайх</button>
        <?php if ($searchQuery !== ''): ?>
            <a href="index.php?page=deliveries" class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-200">Цэвэрлэх</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($searchQuery !== ''): ?>
<!-- ═══════════════════ SEARCH RESULTS ═══════════════════ -->
<div class="mb-4">
    <h3 class="text-base font-bold text-gray-900 mb-3">"<?= e($searchQuery) ?>" хайлтын үр дүн <span class="text-gray-400">(<?= count($searchResults) ?>)</span></h3>
</div>

<?php if (empty($searchResults)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400 text-lg">Илэрц олдсонгүй</p>
        <p class="text-gray-300 text-sm mt-1">Утас, захиалгын дугаар, эсвэл нэрээр хайна уу</p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($searchResults as $r):
            $delStatus = $r['delivery_status'] ?? null;
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <a href="index.php?page=order-detail&id=<?= $r['id'] ?>&return=<?= urlencode('index.php?page=deliveries&q=' . urlencode($searchQuery)) ?>" class="font-semibold text-blue-600 hover:underline">#<?= e($r['order_number']) ?></a>
                        <span class="text-sm text-gray-600"><?= e($r['customer_name']) ?></span>
                        <span class="text-sm text-gray-400"><?= e($r['customer_phone']) ?></span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap text-xs">
                        <?= orderStatusLabel($r['status']) ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $r['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= $r['payment_status'] === 'paid' ? 'Төлсөн' : 'Төлөөгүй' ?>
                        </span>
                        <span class="text-gray-500"><?= formatPrice($r['total']) ?></span>
                        <?php if ($r['district_name']): ?>
                            <span class="text-gray-400"><?= e($r['district_name']) ?><?= $r['khoroo_number'] ? ', ' . $r['khoroo_number'] . '-р хороо' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($delStatus): ?>
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span class="px-2 py-0.5 rounded-full font-medium <?= $deliveryStatuses[$delStatus]['class'] ?? 'bg-gray-100 text-gray-600' ?>"><?= $deliveryStatuses[$delStatus]['label'] ?? $delStatus ?></span>
                        <?php if ($r['driver_name']): ?>
                            <span class="text-gray-500">🚗 <?= e($r['driver_name']) ?> (<?= e($r['driver_phone']) ?>)</span>
                        <?php endif; ?>
                        <?php if ($r['batch_id']): ?>
                            <a href="index.php?page=delivery-batch-detail&id=<?= $r['batch_id'] ?>" class="text-blue-500 hover:underline">Багц #<?= $r['batch_id'] ?></a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <p class="mt-1 text-xs text-gray-400">Жолооч хуваарилаагүй</p>
                    <?php endif; ?>
                </div>
                <div class="flex gap-1">
                    <?php if (!$delStatus || in_array($delStatus, ['failed'])): ?>
                        <a href="index.php?page=delivery-assign&order_id=<?= $r['id'] ?>" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100">Хуваарилах</a>
                    <?php endif; ?>
                    <a href="index.php?page=order-detail&id=<?= $r['id'] ?>&return=<?= urlencode('index.php?page=deliveries&q=' . urlencode($searchQuery)) ?>" class="px-3 py-1.5 bg-gray-50 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-100">Дэлгэрэнгүй</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php else: ?>
<!-- ═══════════════════ MAIN DELIVERY DASHBOARD ═══════════════════ -->

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-7 gap-3 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Ачаа хүлээж буй</p>
        <p class="text-2xl font-bold <?= count($waitingCargoOrders) > 0 ? 'text-purple-600' : 'text-gray-900' ?>"><?= count($waitingCargoOrders) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Төлбөр хүлээж буй</p>
        <p class="text-2xl font-bold <?= count($waitingPaymentOrders) > 0 ? 'text-amber-600' : 'text-gray-900' ?>"><?= count($waitingPaymentOrders) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Бэлтгэх</p>
        <p class="text-2xl font-bold <?= count($pendingOrders) > 0 ? 'text-red-600' : 'text-gray-900' ?>"><?= count($pendingOrders) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Хуваарилахад бэлэн</p>
        <p class="text-2xl font-bold <?= $allAssignableCount > 0 ? 'text-orange-600' : 'text-gray-900' ?>"><?= $allAssignableCount ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Хуваарилсан</p>
        <p class="text-2xl font-bold <?= ($stats['assigned'] ?? 0) > 0 ? 'text-yellow-600' : 'text-gray-900' ?>"><?= $stats['assigned'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Авч явсан</p>
        <p class="text-2xl font-bold <?= ($stats['picked_up'] ?? 0) > 0 ? 'text-blue-600' : 'text-gray-900' ?>"><?= $stats['picked_up'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Өнөөдөр хүргэсэн</p>
        <p class="text-2xl font-bold text-green-600"><?= $stats['delivered_today'] ?? 0 ?></p>
    </div>
</div>

<!-- Tabs -->
<div class="mb-6 flex gap-1 bg-gray-100 rounded-xl p-1 w-fit flex-wrap">
    <?php if (!$isPosReadonly): ?>
    <a href="index.php?page=deliveries&tab=prepare"
       class="px-4 py-2.5 rounded-lg text-sm font-medium transition <?= $tab === 'prepare' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
        📦 Бэлтгэх
        <?php if (count($pendingOrders) > 0): ?>
            <span class="ml-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs"><?= count($pendingOrders) ?></span>
        <?php endif; ?>
    </a>
    <a href="index.php?page=deliveries&tab=assign"
       class="px-4 py-2.5 rounded-lg text-sm font-medium transition <?= $tab === 'assign' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
        🚚 Хуваарилах
        <?php if ($allAssignableCount > 0): ?>
            <span class="ml-1 px-1.5 py-0.5 bg-orange-500 text-white rounded-full text-xs"><?= $allAssignableCount ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
    <a href="index.php?page=deliveries&tab=active"
       class="px-4 py-2.5 rounded-lg text-sm font-medium transition <?= $tab === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
        🏃 Хүргэлтэнд
        <?php if (count($activeBatches) + count($unbatchedDeliveries) > 0): ?>
            <span class="ml-1 px-1.5 py-0.5 bg-blue-500 text-white rounded-full text-xs"><?= count($activeBatches) + count($unbatchedDeliveries) ?></span>
        <?php endif; ?>
    </a>
    <a href="index.php?page=deliveries&tab=completed"
       class="px-4 py-2.5 rounded-lg text-sm font-medium transition <?= $tab === 'completed' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
        ✅ Дууссан
    </a>
</div>

<?php // ═══════════════════ TAB: PREPARE ═══════════════════ ?>
<?php if ($tab === 'prepare'): ?>

<?php if (empty($pendingOrders)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400 text-lg">Бэлтгэх захиалга байхгүй</p>
        <p class="text-gray-300 text-sm mt-1">Бүх бараа нь ирсэн захиалга энд харагдана</p>
    </div>
<?php else: ?>
    <div x-data="{ pendingSel: [] }" class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-base font-bold text-gray-900">📦 Бэлтгэх <span class="text-red-500">(<?= count($pendingOrders) ?>)</span></h3>
                <p class="text-xs text-gray-400 mt-1">Бараа бэлдээд "Бэлэн болгох" дарна уу → Хуваарилах таб руу шилжинэ.</p>
            </div>
            <?php if (!$isPosReadonly): ?>
            <div class="flex items-center gap-2">
                <button @click="pendingSel = pendingSel.length === <?= count($pendingOrders) ?> ? [] : [<?= implode(',', array_column($pendingOrders, 'id')) ?>]"
                    class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium">
                    <span x-text="pendingSel.length === <?= count($pendingOrders) ?> ? 'Бүгдийг болих' : 'Бүгдийг сонгох'"></span>
                </button>
                <form method="POST" x-show="pendingSel.length > 0" x-cloak>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_ready">
                    <template x-for="id in pendingSel" :key="id">
                        <input type="hidden" name="order_ids[]" :value="id">
                    </template>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                        ✓ Бэлэн болгох (<span x-text="pendingSel.length"></span>)
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <div class="space-y-2">
            <?php
            $custCounts = [];
            foreach ($pendingOrders as $po) {
                $ck = $po['customer_phone'] ?: $po['customer_name'];
                $custCounts[$ck] = ($custCounts[$ck] ?? 0) + 1;
            }
            ?>
            <?php foreach ($pendingOrders as $order):
                $ck = $order['customer_phone'] ?: $order['customer_name'];
                $isDuplicate = $custCounts[$ck] > 1;
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:border-blue-200 transition <?= !$isPosReadonly ? 'cursor-pointer' : '' ?> <?= $isDuplicate ? 'ring-1 ring-amber-200' : '' ?>"
                 <?= !$isPosReadonly ? "@click=\"pendingSel.includes({$order['id']}) ? pendingSel = pendingSel.filter(i => i !== {$order['id']}) : pendingSel.push({$order['id']})\"" : '' ?>>
                <div class="flex items-start gap-3">
                    <?php if (!$isPosReadonly): ?>
                    <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-green-600 mt-0.5 flex-shrink-0" value="<?= $order['id'] ?>"
                        :checked="pendingSel.includes(<?= $order['id'] ?>)"
                        @click.stop="pendingSel.includes(<?= $order['id'] ?>) ? pendingSel = pendingSel.filter(i => i !== <?= $order['id'] ?>) : pendingSel.push(<?= $order['id'] ?>)">
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <a href="index.php?page=order-detail&id=<?= $order['id'] ?>&return=<?= urlencode('index.php?page=deliveries&tab=prepare') ?>" @click.stop class="font-semibold text-blue-600 hover:underline">#<?= e($order['order_number']) ?></a>
                            <span class="text-sm font-medium text-gray-900"><?= e($order['customer_name']) ?></span>
                            <span class="text-sm text-gray-400"><?= e($order['customer_phone']) ?></span>
                            <?php if ($isDuplicate): ?>
                                <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium">🔗 <?= $custCounts[$ck] ?> захиалга</span>
                            <?php endif; ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $order['payment_status'] === 'paid' ? '✓ Төлсөн' : 'Төлөөгүй' ?>
                            </span>
                            <span class="text-sm font-medium text-gray-700"><?= formatPrice($order['total']) ?></span>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-gray-400">
                            <span><?= e($order['district_name'] ?? 'Дүүрэг тодорхойгүй') ?><?= $order['khoroo_name'] ? ', ' . e($order['khoroo_name']) : ($order['khoroo_number'] ? ', ' . $order['khoroo_number'] . '-р хороо' : '') ?></span>
                            <?php if ($order['address']): ?>
                                <span><?= e($order['address']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($orderItems[$order['id']])): ?>
                        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
                            <?php foreach ($orderItems[$order['id']] as $item): ?>
                            <span class="text-xs text-gray-500"><?= e($item['product_name']) ?> <span class="text-gray-400">×<?= $item['quantity'] ?></span></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

    <!-- Waiting for cargo payment banner -->
    <?php if (!empty($waitingPaymentOrders)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mt-6">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-amber-600 text-lg">💳</span>
            <h4 class="font-semibold text-amber-800">Ачааны төлбөр хүлээж буй захиалга (<?= count($waitingPaymentOrders) ?>)</h4>
        </div>
        <p class="text-xs text-amber-600 mb-3">Бараа нь бүрэн ирсэн боловч ачааны төлбөр төлөгдөөгүй тул бэлтгэх боломжгүй.</p>
        <div class="space-y-2">
            <?php foreach ($waitingPaymentOrders as $wpo): ?>
            <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-amber-100">
                <div class="flex items-center gap-3">
                    <a href="index.php?page=order-detail&id=<?= $wpo['id'] ?>&return=<?= urlencode('index.php?page=deliveries&tab=prepare') ?>" class="font-medium text-blue-600 hover:underline text-sm">#<?= e($wpo['order_number']) ?></a>
                    <span class="text-sm text-gray-600"><?= e($wpo['customer_name']) ?></span>
                    <span class="text-xs text-gray-400"><?= e($wpo['customer_phone']) ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-amber-700 font-medium">Төлөгдөөгүй: <?= formatPrice((float)$wpo['unpaid_cargo_amount']) ?></span>
                    <span class="text-xs text-gray-400"><?= (int)$wpo['unpaid_cargo_count'] ?> бараа</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Waiting for cargo info banner -->
    <?php if (!empty($waitingCargoOrders)): ?>
    <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 mt-6">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-purple-600 text-lg">⏳</span>
            <h4 class="font-semibold text-purple-800">Ачаа хүлээж буй захиалга (<?= count($waitingCargoOrders) ?>)</h4>
        </div>
        <p class="text-xs text-purple-600 mb-3">Эдгээр захиалгын зарим бараа ачаагаар ирээгүй байгаа тул бэлтгэх боломжгүй.</p>
        <div class="space-y-2">
            <?php foreach ($waitingCargoOrders as $wco): ?>
            <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-purple-100">
                <div class="flex items-center gap-3">
                    <a href="index.php?page=order-detail&id=<?= $wco['id'] ?>&return=<?= urlencode('index.php?page=deliveries&tab=prepare') ?>" class="font-medium text-blue-600 hover:underline text-sm">#<?= e($wco['order_number']) ?></a>
                    <span class="text-sm text-gray-600"><?= e($wco['customer_name']) ?></span>
                    <span class="text-xs text-gray-400"><?= e($wco['customer_phone']) ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-purple-600 font-medium"><?= $wco['pending_cargo_count'] ?>/<?= $wco['cargo_item_count'] ?> бараа хүлээгдэж буй</span>
                    <span class="text-xs text-gray-400"><?= formatPrice($wco['total']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php // ═══════════════════ TAB: ASSIGN ═══════════════════ ?>
<?php elseif ($tab === 'assign'): ?>

<?php if (empty($readyOrders)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400 text-lg">Хуваарилахад бэлэн захиалга байхгүй</p>
        <p class="text-gray-300 text-sm mt-1">Эхлээд "Бэлтгэх" табаас захиалга бэлдэж "Бэлэн болгох" товч дарна уу</p>
    </div>
<?php else: ?>
    <div x-data="{
        selected: [],
        driverId: '',
        notes: '',
        selectAll(groupKey) {
            const checkboxes = document.querySelectorAll('.order-cb[data-group=\'' + groupKey + '\']');
            const allChecked = Array.from(checkboxes).every(cb => this.selected.includes(parseInt(cb.value)));
            checkboxes.forEach(cb => {
                const val = parseInt(cb.value);
                if (allChecked) {
                    this.selected = this.selected.filter(id => id !== val);
                } else if (!this.selected.includes(val)) {
                    this.selected.push(val);
                }
            });
        },
        selectCustomer(custKey) {
            const checkboxes = document.querySelectorAll('.order-cb[data-customer=\'' + custKey + '\']');
            const allChecked = Array.from(checkboxes).every(cb => this.selected.includes(parseInt(cb.value)));
            checkboxes.forEach(cb => {
                const val = parseInt(cb.value);
                if (allChecked) {
                    this.selected = this.selected.filter(id => id !== val);
                } else if (!this.selected.includes(val)) {
                    this.selected.push(val);
                }
            });
        },
        selectAllOrders() {
            const all = document.querySelectorAll('.order-cb');
            const allChecked = this.selected.length === all.length;
            if (allChecked) {
                this.selected = [];
            } else {
                this.selected = Array.from(all).map(cb => parseInt(cb.value));
            }
        },
        toggleOrder(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(i => i !== id);
            } else {
                this.selected.push(id);
            }
        }
    }">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-bold text-gray-900">🚚 Хуваарилахад бэлэн <span class="text-orange-500">(<?= $allAssignableCount ?>)</span></h3>
        </div>

        <!-- Assign toolbar -->
        <?php if (!$isPosReadonly): ?>
        <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-4 mb-4 sticky top-0 z-10" x-show="selected.length > 0" x-cloak>
            <form method="POST" class="flex flex-wrap items-center gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="batch_assign">
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="order_ids[]" :value="id">
                </template>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-700">Сонгосон:</span>
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold" x-text="selected.length"></span>
                </div>
                <select name="driver_id" x-model="driverId" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Жолооч сонгох...</option>
                    <?php foreach ($activeDrivers as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['phone']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="notes" x-model="notes" placeholder="Тэмдэглэл..." class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none w-48">
                <button type="submit" :disabled="!driverId" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    🚚 Багцалж хуваарилах
                </button>
            </form>
        </div>

        <!-- Select all button -->
        <div class="mb-3 flex items-center gap-3">
            <button @click="selectAllOrders()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium">
                <span x-text="selected.length === <?= $allAssignableCount ?> ? 'Бүгдийг болих' : 'Бүгдийг сонгох'"></span>
            </button>
            <span class="text-xs text-gray-400">Нийт <?= $allAssignableCount ?> захиалга · <?= count($grouped) ?> бүс</span>
        </div>
        <?php endif; ?>

        <!-- Grouped by district + khoroo, then by customer -->
        <?php foreach ($grouped as $groupKey => $customerGroups):
            $parts = explode('||', $groupKey);
            $safeKey = md5($groupKey);
            $groupOrderCount = 0;
            foreach ($customerGroups as $co) $groupOrderCount += count($co);
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <?php if (!$isPosReadonly): ?>
                    <button @click="selectAll('<?= $safeKey ?>')" class="w-5 h-5 rounded border border-gray-300 bg-white flex items-center justify-center hover:border-blue-500 transition text-xs cursor-pointer">
                        <span x-show="(() => { const cbs = document.querySelectorAll('.order-cb[data-group=\'<?= $safeKey ?>\']'); return cbs.length > 0 && Array.from(cbs).every(cb => selected.includes(parseInt(cb.value))); })()" class="text-blue-600">✓</span>
                    </button>
                    <?php endif; ?>
                    <div>
                        <span class="font-semibold text-gray-900"><?= e($parts[0]) ?></span>
                        <span class="text-gray-400 mx-1">·</span>
                        <span class="text-gray-600"><?= e($parts[1]) ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if (count($customerGroups) < $groupOrderCount): ?>
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium"><?= count($customerGroups) ?> хэрэглэгч</span>
                    <?php endif; ?>
                    <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium"><?= $groupOrderCount ?> захиалга</span>
                </div>
            </div>

            <?php foreach ($customerGroups as $custKey => $orders):
                $hasMultipleOrders = count($orders) > 1;
                $safeCustKey = md5($custKey);
                $custTotal = array_sum(array_column($orders, 'total'));
            ?>
                <?php if ($hasMultipleOrders): ?>
                <!-- Customer with multiple orders - merged header -->
                <div class="px-5 py-2 bg-amber-50 border-b border-amber-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <?php if (!$isPosReadonly): ?>
                        <button @click="selectCustomer('<?= $safeCustKey ?>')" class="text-amber-600 hover:text-amber-800 text-xs font-medium cursor-pointer">🔗 Нэгтгэх</button>
                        <?php endif; ?>
                        <span class="font-medium text-sm text-gray-900"><?= e($orders[0]['customer_name']) ?></span>
                        <span class="text-xs text-gray-400"><?= e($orders[0]['customer_phone']) ?></span>
                        <span class="px-1.5 py-0.5 bg-amber-200 text-amber-800 rounded text-xs font-bold"><?= count($orders) ?> захиалга · <?= formatPrice($custTotal) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php foreach ($orders as $order): ?>
                <div class="px-5 py-3 flex items-start gap-3 hover:bg-blue-50/30 transition <?= !$isPosReadonly ? 'cursor-pointer' : '' ?> border-b border-gray-50 <?= $hasMultipleOrders ? 'pl-10' : '' ?>" <?= !$isPosReadonly ? "@click=\"toggleOrder({$order['id']})\"" : '' ?>>
                    <?php if (!$isPosReadonly): ?>
                    <input type="checkbox" class="order-cb w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-1"
                           data-group="<?= $safeKey ?>" data-customer="<?= $safeCustKey ?>" value="<?= $order['id'] ?>"
                           :checked="selected.includes(<?= $order['id'] ?>)"
                           @click.stop="toggleOrder(<?= $order['id'] ?>)">
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <a href="index.php?page=order-detail&id=<?= $order['id'] ?>&return=<?= urlencode('index.php?page=deliveries&tab=assign') ?>" @click.stop class="font-medium text-blue-600 hover:underline text-sm">#<?= e($order['order_number']) ?></a>
                            <?php if (!$hasMultipleOrders): ?>
                            <span class="text-sm text-gray-600"><?= e($order['customer_name']) ?></span>
                            <span class="text-xs text-gray-400"><?= e($order['customer_phone']) ?></span>
                            <?php endif; ?>
                            <span class="px-1.5 py-0.5 rounded-full text-xs font-medium <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $order['payment_status'] === 'paid' ? '✓ Төлсөн' : 'Төлөөгүй' ?>
                            </span>
                            <span class="text-xs font-medium text-gray-700"><?= formatPrice($order['total']) ?></span>
                        </div>
                        <?php if ($order['address']): ?>
                        <p class="text-xs text-gray-400 mb-1"><?= e($order['address']) ?><?= $order['detail_address'] ? ' · ' . e($order['detail_address']) : '' ?></p>
                        <?php endif; ?>
                        <!-- Products -->
                        <?php if (!empty($orderItems[$order['id']])): ?>
                        <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                            <?php foreach ($orderItems[$order['id']] as $item): ?>
                            <span class="text-xs text-gray-500"><?= e($item['product_name']) ?> <span class="text-gray-400">×<?= $item['quantity'] ?></span></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isPosReadonly): ?>
                    <div class="flex-shrink-0">
                        <form method="POST" @click.stop>
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="unmark_ready">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="p-1 text-gray-300 hover:text-red-500 rounded" title="Бэлэн биш болгох">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php // ═══════════════════ TAB: ACTIVE ═══════════════════ ?>
<?php elseif ($tab === 'active'): ?>

<?php if (empty($activeBatches) && empty($unbatchedDeliveries)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400 text-lg">Идэвхитэй хүргэлт байхгүй</p>
    </div>
<?php else: ?>

    <!-- Active Batches -->
    <?php foreach ($activeBatches as $batch): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <a href="index.php?page=delivery-batch-detail&id=<?= $batch['id'] ?>" class="font-semibold text-gray-900 hover:text-blue-600">
                        Багц #<?= $batch['id'] ?>
                    </a>
                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium <?= $batchStatuses[$batch['status']]['class'] ?>">
                        <?= $batchStatuses[$batch['status']]['label'] ?>
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right text-sm">
                    <p class="font-medium text-gray-900"><?= e($batch['driver_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= e($batch['driver_phone']) ?></p>
                </div>
                <?php if (!$isPosReadonly): ?>
                <?php if ($batch['status'] === 'assigned'): ?>
                <form method="POST" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_batch_status">
                    <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
                    <input type="hidden" name="new_status" value="in_progress">
                    <button type="submit" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100">Хүргэлт эхлүүлэх</button>
                </form>
                <?php elseif ($batch['status'] === 'in_progress'): ?>
                <form method="POST" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_batch_status">
                    <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
                    <input type="hidden" name="new_status" value="completed">
                    <button type="submit" class="px-3 py-1.5 bg-green-50 text-green-600 rounded-lg text-xs font-medium hover:bg-green-100">Бүгд хүргэсэн</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- Progress bar -->
        <div class="px-5 py-3 bg-gray-50 flex items-center gap-4 text-xs">
            <?php
                $total = $batch['total_orders'];
                $delivered = $batch['delivered_count'];
                $failed = $batch['failed_count'];
                $pct = $total > 0 ? round(($delivered + $failed) / $total * 100) : 0;
            ?>
            <div class="flex-1">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all <?= $pct >= 100 ? 'bg-green-500' : 'bg-blue-500' ?>" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <span class="text-gray-500 whitespace-nowrap">
                <?= $delivered ?>/<?= $total ?> хүргэсэн
                <?php if ($failed): ?><span class="text-red-500 ml-1">(<?= $failed ?> амжилтгүй)</span><?php endif; ?>
            </span>
            <span class="font-medium text-gray-700"><?= formatPrice($batch['batch_total'] ?? 0) ?></span>
            <a href="index.php?page=delivery-batch-detail&id=<?= $batch['id'] ?>" class="text-blue-600 hover:underline">Дэлгэрэнгүй →</a>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Unbatched deliveries -->
    <?php if (!empty($unbatchedDeliveries)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
            <h3 class="font-semibold text-gray-700 text-sm">Багцгүй хүргэлтүүд</h3>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($unbatchedDeliveries as $del): ?>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-0.5">
                        <a href="index.php?page=order-detail&id=<?= $del['order_id'] ?>&return=<?= urlencode('index.php?page=deliveries&tab=active') ?>" class="font-medium text-blue-600 hover:underline text-sm">#<?= e($del['order_number']) ?></a>
                        <span class="text-sm text-gray-600"><?= e($del['customer_name']) ?></span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $deliveryStatuses[$del['status']]['class'] ?>">
                            <?= $deliveryStatuses[$del['status']]['label'] ?>
                        </span>
                    </div>
                    <p class="text-xs text-gray-400">
                        <?= e($del['district_name'] ?? '') ?><?= $del['khoroo_name'] ? ', ' . e($del['khoroo_name']) : ($del['khoroo_number'] ? ', ' . $del['khoroo_number'] . '-р хороо' : '') ?>
                        · <?= e($del['driver_name']) ?>
                    </p>
                </div>
                <div class="flex gap-1">
                    <?php if (!$isPosReadonly): ?>
                    <?php if ($del['status'] === 'assigned'): ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                        <input type="hidden" name="new_status" value="picked_up">
                        <input type="hidden" name="return_tab" value="active">
                        <button type="submit" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100">Авсан</button>
                    </form>
                    <?php elseif ($del['status'] === 'picked_up'): ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                        <input type="hidden" name="new_status" value="delivered">
                        <input type="hidden" name="return_tab" value="active">
                        <button type="submit" class="px-3 py-1.5 bg-green-50 text-green-600 rounded-lg text-xs font-medium hover:bg-green-100">Хүргэсэн</button>
                    </form>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                        <input type="hidden" name="new_status" value="failed">
                        <input type="hidden" name="return_tab" value="active">
                        <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">Амжилтгүй</button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php // ═══════════════════ TAB: COMPLETED ═══════════════════ ?>
<?php elseif ($tab === 'completed'): ?>

<?php if (empty($completedBatches)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400 text-lg">Сүүлийн 7 хоногт дууссан багц байхгүй</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Багц</th>
                        <th class="px-5 py-3 text-left">Жолооч</th>
                        <th class="px-5 py-3 text-center">Захиалга</th>
                        <th class="px-5 py-3 text-center">Хүргэсэн</th>
                        <th class="px-5 py-3 text-center">Амжилтгүй</th>
                        <th class="px-5 py-3 text-right">Нийт дүн</th>
                        <th class="px-5 py-3 text-left">Дууссан</th>
                        <th class="px-5 py-3 text-center">Төлөв</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($completedBatches as $batch): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="index.php?page=delivery-batch-detail&id=<?= $batch['id'] ?>" class="font-medium text-blue-600 hover:underline">#<?= $batch['id'] ?></a>
                        </td>
                        <td class="px-5 py-3 text-gray-900"><?= e($batch['driver_name']) ?></td>
                        <td class="px-5 py-3 text-center"><?= $batch['total_orders'] ?></td>
                        <td class="px-5 py-3 text-center text-green-600 font-medium"><?= $batch['delivered_count'] ?></td>
                        <td class="px-5 py-3 text-center <?= $batch['failed_count'] > 0 ? 'text-red-600 font-medium' : 'text-gray-400' ?>"><?= $batch['failed_count'] ?></td>
                        <td class="px-5 py-3 text-right font-medium"><?= formatPrice($batch['batch_total'] ?? 0) ?></td>
                        <td class="px-5 py-3 text-xs text-gray-500"><?= $batch['completed_at'] ? formatDateShort($batch['completed_at']) : '-' ?></td>
                        <td class="px-5 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $batchStatuses[$batch['status']]['class'] ?>">
                                <?= $batchStatuses[$batch['status']]['label'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>

<?php endif; ?>
<?php // end of search/main dashboard conditional ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
