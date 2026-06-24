<?php
$pageTitle = 'Захиалгын дэлгэрэнгүй';
$db = getDB();
$id = $_GET['id'] ?? null;

if (!$id) { header('Location: index.php?page=orders'); exit; }

// Build redirect URL preserving return param
$returnParam = $_GET['return'] ?? '';
$selfUrl = 'index.php?page=order-detail&id=' . urlencode($id);
if ($returnParam) $selfUrl .= '&return=' . urlencode($returnParam);

$stmt = $db->prepare("SELECT o.*, d.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name, cb.name as batch_name 
    FROM orders o 
    LEFT JOIN districts d ON o.district_id = d.id 
    LEFT JOIN khoroos k ON o.khoroo_id = k.id 
    LEFT JOIN cargo_batches cb ON o.cargo_batch_id = cb.id
    WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Захиалга олдсонгүй.');
    header('Location: index.php?page=orders');
    exit;
}

// Order items
$items = $db->prepare("SELECT oi.*, p.image, p.type as product_type FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$items->execute([$id]);
$orderItems = $items->fetchAll();

// Cargo payments
$cpStmt = $db->prepare("SELECT * FROM cargo_payments WHERE order_id = ? ORDER BY created_at DESC");
$cpStmt->execute([$id]);
$cargoPayments = $cpStmt->fetchAll();

// Compute partially_delivered flag
$totalItems = count($orderItems);
$deliveredItems = count(array_filter($orderItems, fn($i) => ($i['delivery_status'] ?? 'pending') === 'delivered'));
$isPartiallyDelivered = $deliveredItems > 0 && $deliveredItems < $totalItems;

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $selfUrl);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = $_POST['new_status'] ?? '';
        $allowed = ['pending','confirmed','cargo_shipping','cargo_arrived','ready_pickup','delivering','partially_delivered','delivered','picked_up','completed','cancelled'];
        if (in_array($newStatus, $allowed)) {
            try {
                $db->beginTransaction();

                $db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $id]);

                // Stamp confirmed_at on first transition past `pending`
                // (set-once via COALESCE so later reverts/edits don't bump it).
                if ($newStatus !== 'pending' && $newStatus !== 'cancelled') {
                    $db->prepare("UPDATE orders SET confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?")->execute([$id]);
                }

                $oldStatus = $order['status'];

                $adminId = $GLOBALS['currentAdmin']['id'] ?? null;
                // Restore stock: non-cancelled → cancelled
                if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                    foreach ($orderItems as $item) {
                        adjustStock(
                            $db,
                            (int)$item['product_id'],
                            !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                            +(int)$item['quantity'],
                            'order_cancel',
                            (int)$id,
                            'admin',
                            $adminId,
                            'Order ' . $order['order_number'] . ' cancelled'
                        );
                    }
                }

                // Deduct stock: cancelled → non-cancelled
                if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
                    foreach ($orderItems as $item) {
                        $r = adjustStock(
                            $db,
                            (int)$item['product_id'],
                            !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                            -(int)$item['quantity'],
                            'order_uncancel',
                            (int)$id,
                            'admin',
                            $adminId,
                            'Order ' . $order['order_number'] . ' un-cancelled'
                        );
                        if ($r['status'] === 'insufficient') {
                            throw new Exception("Cannot un-cancel: stock depleted for '{$item['product_name']}'");
                        }
                    }
                }

                $db->commit();
                auditLog('order_status_changed', 'order', $id, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, [
                    'order_number' => $order['order_number'],
                    'old_status' => $order['status'],
                    'new_status' => $newStatus,
                ]);

                // Notify StorePay so the customer's loan is cancelled too
                if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled' && !empty($order['storepay_invoice_id'])) {
                    cancelStorepayOrder($db, (int)$id, 'Захиалга цуцлагдсан #' . $order['order_number']);
                }

                setFlash('success', 'Захиалгын төлөв шинэчлэгдлээ.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', 'Алдаа гарлаа: ' . $e->getMessage());
            }
            header('Location: ' . $selfUrl);
            exit;
        }
    }

    if ($action === 'update_payment') {
        $paymentStatus = $_POST['payment_status'] ?? '';
        if (in_array($paymentStatus, ['pending', 'paid', 'refunded'])) {
            $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?")->execute([$paymentStatus, $id]);

            auditLog('order_payment_changed', 'order', $id, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, [
                'order_number' => $order['order_number'],
                'old_payment_status' => $order['payment_status'],
                'new_payment_status' => $paymentStatus,
            ]);

            // When payment confirmed, auto-mark visible cargo fees as paid
            if ($paymentStatus === 'paid') {
                $db->prepare("
                    UPDATE order_items oi
                    JOIN products p ON oi.product_id = p.id
                    SET oi.cargo_fee_paid = 1
                    WHERE oi.order_id = ? AND oi.cargo_fee > 0 AND p.hide_cargo_fee = 0
                ")->execute([$id]);
                $unpaid = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
                $unpaid->execute([$id]);
                $allPaid = (int)$unpaid->fetchColumn() === 0 ? 1 : 0;
                $db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $id]);
            }

            setFlash('success', 'Төлбөрийн төлөв шинэчлэгдлээ.');
            header('Location: ' . $selfUrl);
            exit;
        }
    }

    if ($action === 'update_cargo_fee_paid') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $cargoFeePaid = (int)($_POST['cargo_fee_paid'] ?? 0);
        $db->prepare("UPDATE order_items SET cargo_fee_paid = ? WHERE id = ? AND order_id = ?")->execute([$cargoFeePaid ? 1 : 0, $itemId, $id]);
        // Auto-update order-level: 1 if ALL cargo items paid, 0 otherwise
        $unpaid = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
        $unpaid->execute([$id]);
        $allPaid = (int)$unpaid->fetchColumn() === 0 ? 1 : 0;
        $db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $id]);
        auditLog('order_cargo_fee_updated', 'order', $id, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, [
            'order_number' => $order['order_number'],
            'item_id' => $itemId,
            'cargo_fee_paid' => $cargoFeePaid ? 1 : 0,
        ]);
        setFlash('success', 'Ачааны төлбөрийн төлөв шинэчлэгдлээ.');
        header('Location: ' . $selfUrl);
        exit;
    }

    if ($action === 'update_item_delivery_status') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $newDeliveryStatus = $_POST['delivery_status'] ?? '';
        if (in_array($newDeliveryStatus, ['pending', 'delivered']) && $itemId > 0) {
            $db->prepare("UPDATE order_items SET delivery_status = ? WHERE id = ? AND order_id = ?")->execute([$newDeliveryStatus, $itemId, $id]);

            // Re-check: auto-update order status to partially_delivered / delivered
            $itemsCheck = $db->prepare("SELECT delivery_status FROM order_items WHERE order_id = ?");
            $itemsCheck->execute([$id]);
            $allItemStatuses = $itemsCheck->fetchAll(PDO::FETCH_COLUMN);
            $totalCount = count($allItemStatuses);
            $deliveredCount = count(array_filter($allItemStatuses, fn($s) => $s === 'delivered'));

            if ($deliveredCount === $totalCount && $totalCount > 0) {
                $db->prepare("UPDATE orders SET status = 'delivered' WHERE id = ? AND status IN ('delivering','partially_delivered')")->execute([$id]);
            } elseif ($deliveredCount > 0 && $deliveredCount < $totalCount) {
                $db->prepare("UPDATE orders SET status = 'partially_delivered' WHERE id = ? AND status IN ('delivering','delivered')")->execute([$id]);
            }

            auditLog('order_item_delivery_status', 'order', $id, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, [
                'order_number' => $order['order_number'],
                'item_id' => $itemId,
                'delivery_status' => $newDeliveryStatus,
            ]);
            setFlash('success', 'Барааны хүргэлтийн төлөв шинэчлэгдлээ.');
        }
        header('Location: ' . $selfUrl);
        exit;
    }

    if ($action === 'update_order_note') {
        $orderNote = trim($_POST['order_note'] ?? '');
        $db->prepare("UPDATE orders SET order_note = ? WHERE id = ?")->execute([$orderNote, $id]);
        auditLog('order_note_updated', 'order', $id, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, [
            'order_number' => $order['order_number'],
        ]);
        setFlash('success', 'Тэмдэглэл хадгалагдлаа.');
        header('Location: ' . $selfUrl);
        exit;
    }

    if ($action === 'update_fulfillment') {
        $newFulfillment = $_POST['fulfillment'] ?? '';
        if (in_array($newFulfillment, ['delivery', 'pickup'])) {
            if ($newFulfillment === 'delivery') {
                $districtId = (int)($_POST['district_id'] ?? 0);
                $khorooId = (int)($_POST['khoroo_id'] ?? 0);
                $address = trim($_POST['address'] ?? '');
                $detailAddress = trim($_POST['detail_address'] ?? '');
                if ($districtId && $address) {
                    $db->prepare("UPDATE orders SET fulfillment = 'delivery', district_id = ?, khoroo_id = ?, address = ?, detail_address = ? WHERE id = ?")
                        ->execute([$districtId, $khorooId ?: null, $address, $detailAddress, $id]);
                    auditLog('order_fulfillment_changed', 'order', $id, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, [
                        'order_number' => $order['order_number'],
                        'old_fulfillment' => $order['fulfillment'],
                        'new_fulfillment' => 'delivery',
                        'district_id' => $districtId,
                        'address' => $address,
                    ]);
                    setFlash('success', 'Хүргэлтийн мэдээлэл шинэчлэгдлээ.');
                } else {
                    setFlash('error', 'Дүүрэг, хаяг оруулна уу.');
                }
            } else {
                $db->prepare("UPDATE orders SET fulfillment = 'pickup', district_id = NULL, khoroo_id = NULL, address = NULL, detail_address = NULL WHERE id = ?")
                    ->execute([$id]);
                auditLog('order_fulfillment_changed', 'order', $id, 'admin', $GLOBALS['currentAdmin']['id'] ?? null, [
                    'order_number' => $order['order_number'],
                    'old_fulfillment' => $order['fulfillment'],
                    'new_fulfillment' => 'pickup',
                ]);
                setFlash('success', 'Очиж авах болгосон.');
            }
            header('Location: ' . $selfUrl);
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <?php
            $returnUrl = $_GET['return'] ?? 'index.php?page=orders';
            $returnLabel = 'Захиалга руу буцах';
            if (strpos($returnUrl, 'page=deliveries') !== false) {
                $returnLabel = 'Хүргэлт руу буцах';
            } elseif (strpos($returnUrl, 'page=delivery-batch-detail') !== false) {
                $returnLabel = 'Багц руу буцах';
            } elseif (strpos($returnUrl, 'page=reconciliation') !== false) {
                $returnLabel = 'Тулгалт руу буцах';
            } elseif (strpos($returnUrl, 'page=order-create') !== false) {
                $returnLabel = 'Захиалга үүсгэх руу буцах';
            }
        ?>
        <a href="<?= e($returnUrl) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <?= $returnLabel ?>
        </a>
        <div class="flex items-center gap-2">
            <?= orderStatusLabel($order['status']) ?>
            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $order['order_type'] === 'pos' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?>">
                <?= strtoupper($order['order_type']) ?>
            </span>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Order Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Items -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Order #<?= e($order['order_number']) ?></h3>
                    <p class="text-xs text-gray-400 mt-1"><?= formatDate($order['created_at']) ?></p>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php foreach ($orderItems as $item): ?>
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="flex items-center gap-3">
                            <?php if ($item['image']): ?>
                                <img src="<?= e($item['image']) ?>" alt="" class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?= e($item['product_name']) ?>
                                    <?php if (!empty($item['variant_label'])): ?>
                                        <span class="text-purple-600 font-normal text-xs ml-1">(<?= e($item['variant_label']) ?>)</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?= formatPrice($item['product_price']) ?> × <?= $item['quantity'] ?>
                                    <?php if ($item['weight_kg']): ?> · <?= $item['weight_kg'] ?>kg<?php endif; ?>
                                    <?php if ($item['cargo_fee'] > 0): ?> · Ачаа: <?= formatPrice($item['cargo_fee']) ?>
                                        <?php if ($item['cargo_fee_paid']): ?>
                                            <span class="px-1 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Төлсөн</span>
                                        <?php else: ?>
                                            <span class="px-1 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Төлөөгүй</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <?php $ds = $item['delivery_status'] ?? 'pending'; ?>
                                    <span class="px-1.5 py-0.5 rounded text-xs font-medium <?= $ds === 'delivered' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= $ds === 'delivered' ? 'Хүргэсэн' : 'Хүлээгдэж буй' ?>
                                    </span>
                                    <form method="POST" class="inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_item_delivery_status">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="delivery_status" value="<?= $ds === 'delivered' ? 'pending' : 'delivered' ?>">
                                        <button type="submit" class="text-xs px-1.5 py-0.5 rounded transition-colors <?= $ds === 'delivered' ? 'bg-yellow-500 hover:bg-yellow-600 text-white' : 'bg-green-600 hover:bg-green-700 text-white' ?>">
                                            <?= $ds === 'delivered' ? 'Буцаах' : 'Хүргэсэн' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-medium"><?= formatPrice($item['product_price'] * $item['quantity']) ?></span>
                            <?php if ($item['line_total'] !== null && (float)$item['line_total'] !== (float)($item['product_price'] * $item['quantity'])): ?>
                                <p class="text-xs font-medium text-orange-600">Төлсөн: <?= formatPrice($item['line_total']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 space-y-2">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Дүн</span>
                        <span><?= formatPrice($order['subtotal']) ?></span>
                    </div>
                    <?php if ($order['delivery_fee'] > 0): ?>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Хүргэлтийн төлбөр</span>
                        <span class="flex items-center gap-2">
                            <?= formatPrice($order['delivery_fee']) ?>
                            <?php if ($order['payment_status'] === 'paid'): ?>
                                <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Төлсөн</span>
                            <?php else: ?>
                                <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Төлөөгүй</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php elseif ($order['fulfillment'] === 'delivery' && getSetting('delivery_fee_enabled', '1') !== '1'): ?>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Хүргэлтийн төлбөр</span>
                        <span class="text-amber-600">Хүргэлтийн үед бодогдоно</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['cargo_fee'] > 0): ?>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Ачааны төлбөр</span>
                        <span class="flex items-center gap-2">
                            <?= formatPrice($order['cargo_fee']) ?>
                            <?php if ($order['cargo_fee_paid']): ?>
                                <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Төлсөн</span>
                            <?php else: ?>
                                <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Төлөөгүй</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['vat_included']): ?>
                    <div class="flex justify-between text-sm text-orange-600">
                        <span>НӨАТ (10%)</span>
                        <span><?= formatPrice($order['vat_amount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-base font-bold text-gray-900 pt-2 border-t border-gray-200">
                        <span>Нийт</span>
                        <span><?= formatPrice($order['total']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Customer & Delivery -->
            <?php if ($order['order_type'] === 'online'): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Хэрэглэгч & Хүргэлт</h3>
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Хэрэглэгч</p>
                        <p class="font-medium"><?= e($order['customer_name']) ?></p>
                        <p class="text-gray-600"><?= e($order['customer_phone']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500">Хүргэлт</p>
                        <p class="font-medium"><?= $order['fulfillment'] === 'pickup' ? '🏪 Очиж авах' : '🚚 Хүргэлт' ?></p>
                        <?php if ($order['fulfillment'] === 'delivery' && $order['district_name']): ?>
                            <p class="text-gray-600"><?= e($order['district_name']) ?>, <?= $order['khoroo_name'] ?: $order['khoroo_number'] . '-р хороо' ?></p>
                            <p class="text-gray-600"><?= e($order['address']) ?></p>
                            <?php if ($order['detail_address']): ?>
                                <p class="text-gray-600"><?= e($order['detail_address']) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($order['batch_name']): ?>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-sm text-gray-500">Ачааны багц: <span class="font-medium text-gray-900"><?= e($order['batch_name']) ?></span></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Actions -->
        <div class="space-y-6">
            <!-- Delivery Info -->
            <?php
            $deliveryInfo = $db->prepare("SELECT d.*, dd.name as driver_name, dd.phone as driver_phone 
                FROM deliveries d JOIN delivery_drivers dd ON d.driver_id = dd.id 
                WHERE d.order_id = ? ORDER BY d.id DESC LIMIT 1");
            $deliveryInfo->execute([$id]);
            $delivery = $deliveryInfo->fetch();
            ?>
            <?php if ($delivery): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">🚚 Хүргэлт</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Жолооч</span>
                        <span class="font-medium"><?= e($delivery['driver_name']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Утас</span>
                        <span><?= e($delivery['driver_phone']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Төлөв</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            <?= match($delivery['status']) { 'assigned' => 'bg-yellow-100 text-yellow-700', 'picked_up' => 'bg-blue-100 text-blue-700', 'delivered' => 'bg-green-100 text-green-700', 'failed' => 'bg-red-100 text-red-700', default => 'bg-gray-100 text-gray-700' } ?>">
                            <?= match($delivery['status']) { 'assigned' => 'Хуваарилсан', 'picked_up' => 'Авсан', 'delivered' => 'Хүргэсэн', 'failed' => 'Амжилтгүй', default => $delivery['status'] } ?>
                        </span>
                    </div>
                    <?php if ($delivery['delivered_at']): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Хүргэсэн</span>
                        <span class="text-xs"><?= formatDate($delivery['delivered_at']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($order['fulfillment'] === 'delivery' && !in_array($order['status'], ['cancelled', 'completed', 'delivered'])): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">🚚 Хүргэлт</h3>
                <a href="index.php?page=delivery-assign&order_id=<?= $id ?>" class="w-full py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 block text-center">
                    Жолооч хуваарилах
                </a>
            </div>
            <?php endif; ?>
            <!-- Change Fulfillment -->
            <?php
            // Load customer's saved addresses (if order has a customer_id)
            $customerAddresses = [];
            if (!empty($order['customer_id'])) {
                $addrStmt = $db->prepare("
                    SELECT a.*, d.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name
                    FROM customer_addresses a
                    LEFT JOIN districts d ON a.district_id = d.id
                    LEFT JOIN khoroos k ON a.khoroo_id = k.id
                    WHERE a.customer_id = ?
                    ORDER BY a.is_default DESC, a.id DESC
                ");
                $addrStmt->execute([$order['customer_id']]);
                $customerAddresses = $addrStmt->fetchAll();
            }
            // Build x-data as proper JSON to avoid escaping issues
            $fulfillmentXData = [
                'fulfillment' => $order['fulfillment'],
                'districtId'  => (string)((int)($order['district_id'] ?? 0)),
                'khorooId'    => (string)((int)($order['khoroo_id'] ?? 0)),
                'address'     => $order['address'] ?? '',
                'detailAddress' => $order['detail_address'] ?? '',
                'savedAddr'   => 'manual',
                'addrs'       => array_map(function($a) {
                    return [
                        'id'     => (string)$a['id'],
                        'did'    => (string)((int)$a['district_id']),
                        'kid'    => (string)((int)$a['khoroo_id']),
                        'addr'   => $a['address'],
                        'detail' => $a['detail_address'] ?? '',
                        'label'  => $a['district_name'] . ' ' . ($a['khoroo_name'] ?: ($a['khoroo_number'] ? $a['khoroo_number'] . '-р хороо' : '')) . ' — ' . $a['address'] . ($a['detail_address'] ? ', ' . $a['detail_address'] : ''),
                        'isDefault' => (bool)$a['is_default'],
                    ];
                }, $customerAddresses),
            ];
            $allDistricts = $db->query("SELECT id, name_mn FROM districts WHERE is_active = 1 ORDER BY name_mn")->fetchAll();
            $allKhoroos = $db->query("SELECT id, district_id, number, name FROM khoroos ORDER BY number")->fetchAll();
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5"
                x-data="<?= htmlspecialchars(json_encode($fulfillmentXData), ENT_QUOTES, 'UTF-8') ?>">
                <h3 class="font-semibold text-gray-900 mb-3">📦 Хүргэлтийн төрөл</h3>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_fulfillment">
                    <!-- Always submit via hidden inputs bound to Alpine state -->
                    <input type="hidden" name="district_id" :value="districtId">
                    <input type="hidden" name="khoroo_id" :value="khorooId">
                    <input type="hidden" name="address" :value="address">
                    <input type="hidden" name="detail_address" :value="detailAddress">

                    <select name="fulfillment" x-model="fulfillment" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3 focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="pickup">🏪 Очиж авах</option>
                        <option value="delivery">🚚 Хүргэлт</option>
                    </select>
                    <template x-if="fulfillment === 'delivery'">
                        <div class="space-y-3 mb-3">
                            <template x-if="addrs.length > 0">
                                <div class="space-y-2">
                                    <label class="text-xs font-medium text-gray-500 uppercase">Хадгалсан хаяг</label>
                                    <template x-for="a in addrs" :key="a.id">
                                        <label class="flex items-start gap-2 p-2.5 border rounded-lg cursor-pointer hover:border-blue-400 transition-colors text-sm"
                                            :class="savedAddr === a.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                            <input type="radio" :value="a.id" x-model="savedAddr"
                                                @change="districtId = a.did; khorooId = a.kid; address = a.addr; detailAddress = a.detail"
                                                class="mt-0.5">
                                            <div>
                                                <span class="font-medium text-gray-900" x-text="a.label"></span>
                                                <template x-if="a.isDefault">
                                                    <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full ml-1">Үндсэн</span>
                                                </template>
                                            </div>
                                        </label>
                                    </template>
                                    <label class="flex items-center gap-2 p-2.5 border rounded-lg cursor-pointer hover:border-blue-400 transition-colors text-sm"
                                        :class="savedAddr === 'manual' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                        <input type="radio" value="manual" x-model="savedAddr" class="mt-0.5">
                                        <span class="font-medium text-gray-700">+ Шинэ хаяг оруулах</span>
                                    </label>
                                </div>
                            </template>

                            <div x-show="savedAddr === 'manual' || addrs.length === 0" x-transition>
                                <select x-model="districtId" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none mb-3">
                                    <option value="">Дүүрэг сонгох</option>
                                    <?php foreach ($allDistricts as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= e($d['name_mn']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select x-model="khorooId" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none mb-3">
                                    <option value="">Хороо сонгох</option>
                                    <?php foreach ($allKhoroos as $k): ?>
                                        <option value="<?= $k['id'] ?>" x-show="districtId == '<?= $k['district_id'] ?>'">
                                            <?= e($k['name'] ?: $k['number'] . '-р хороо') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" x-model="address" placeholder="Гудамж, байр, тоот" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none mb-3">
                                <input type="text" x-model="detailAddress" placeholder="Нэмэлт хаяг (орц, давхар)" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                    </template>
                    <button type="submit" class="w-full py-2.5 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700">
                        Хүргэлт шинэчлэх
                    </button>
                </form>
            </div>
            <!-- Update Status -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Төлөв шинэчлэх</h3>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <select name="new_status" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3 focus:ring-2 focus:ring-blue-500 outline-none">
                        <?php
                        $statuses = [
                            'pending' => 'Хүлээгдэж буй',
                            'confirmed' => 'Баталгаажсан',
                            'cargo_shipping' => 'Ачаа тээвэрлэж буй',
                            'cargo_arrived' => 'Ачаа ирсэн',
                            'ready_pickup' => 'Очиж авахад бэлэн',
                            'delivering' => 'Хүргэж буй',
                            'partially_delivered' => 'Хэсэгчлэн хүргэсэн',
                            'delivered' => 'Хүргэгдсэн',
                            'picked_up' => 'Очиж авсан',
                            'completed' => 'Дууссан',
                            'cancelled' => 'Цуцлагдсан',
                        ];
                        foreach ($statuses as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $order['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        Төлөв шинэчлэх
                    </button>
                </form>
            </div>

            <!-- Payment -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Төлбөр</h3>
                <?php 
                $pmLabels = ['cash' => 'Бэлэн', 'card' => 'Карт', 'transfer' => 'Шилжүүлэг', 'transfer_nomin' => 'Номин шилжүүлэг', 'split' => 'Хуваасан', 'qpay' => 'QPay', 'bonum' => 'Bonum'];
                $pmLabel = $pmLabels[$order['payment_method']] ?? ucfirst($order['payment_method']);
                ?>
                <p class="text-sm text-gray-600 mb-1">Төлбөрийн арга: <strong><?= e($pmLabel) ?></strong></p>
                <?php if ($order['payment_method'] === 'split'): ?>
                <div class="text-sm text-gray-500 mb-2 space-y-0.5">
                    <?php if (!empty($order['payment_cash']) && $order['payment_cash'] > 0): ?>
                    <p>💵 Бэлэн: <strong><?= formatPrice($order['payment_cash']) ?></strong></p>
                    <?php endif; ?>
                    <?php if (!empty($order['payment_card']) && $order['payment_card'] > 0): ?>
                    <p>💳 Карт: <strong><?= formatPrice($order['payment_card']) ?></strong></p>
                    <?php endif; ?>
                    <?php if (!empty($order['payment_transfer']) && $order['payment_transfer'] > 0): ?>
                    <p>📲 Шилжүүлэг: <strong><?= formatPrice($order['payment_transfer']) ?></strong></p>
                    <?php endif; ?>
                    <?php if (!empty($order['payment_transfer_nomin']) && $order['payment_transfer_nomin'] > 0): ?>
                    <p>🏪 Номин шилжүүлэг: <strong><?= formatPrice($order['payment_transfer_nomin']) ?></strong></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_payment">
                    <select name="payment_status" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3 focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Хүлээгдэж буй</option>
                        <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Төлсөн</option>
                        <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Буцаагдсан</option>
                    </select>
                    <button type="submit" class="w-full py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">
                        Төлбөр шинэчлэх
                    </button>
                </form>
            </div>

            <!-- Customer Notes -->
            <?php if ($order['notes']): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-2">Хэрэглэгчийн тэмдэглэл</h3>
                <p class="text-sm text-gray-600"><?= nl2br(e($order['notes'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Admin Order Note -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">📝 Админ тэмдэглэл</h3>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_order_note">
                    <textarea name="order_note" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3 focus:ring-2 focus:ring-blue-500 outline-none resize-y" placeholder="Захиалгын тэмдэглэл..."><?= e($order['order_note'] ?? '') ?></textarea>
                    <button type="submit" class="w-full py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">
                        Тэмдэглэл хадгалах
                    </button>
                </form>
            </div>

            <!-- Cargo Fee Payment (per item) -->
            <?php
            $cargoItems = array_filter($orderItems, fn($i) => $i['cargo_fee'] > 0);
            if ($cargoItems): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">📦 Ачааны төлбөр</h3>
                <p class="text-sm text-gray-600 mb-1">Нийт: <strong><?= formatPrice($order['cargo_fee']) ?></strong></p>
                <p class="text-sm mb-3">
                    <?php if ($order['cargo_fee_paid']): ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Бүгд төлсөн</span>
                    <?php else: ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Төлөөгүй</span>
                    <?php endif; ?>
                </p>
                <div class="space-y-3">
                    <?php foreach ($cargoItems as $ci): ?>
                    <div class="border border-gray-100 rounded-lg p-3">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm font-medium text-gray-900 flex-1">
                                <?= e($ci['product_name']) ?>
                                <?php if (!empty($ci['variant_label'])): ?>
                                    <span class="text-purple-600 font-normal text-xs ml-1">(<?= e($ci['variant_label']) ?>)</span>
                                <?php endif; ?>
                            </p>
                            <span class="text-sm font-medium text-gray-700 ml-2"><?= formatPrice($ci['cargo_fee']) ?></span>
                        </div>
                        <form method="POST" class="mt-1">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_cargo_fee_paid">
                            <input type="hidden" name="item_id" value="<?= $ci['id'] ?>">
                            <input type="hidden" name="cargo_fee_paid" value="<?= $ci['cargo_fee_paid'] ? '0' : '1' ?>">
                            <button type="submit" class="w-full py-1.5 text-xs font-medium rounded-lg transition-colors <?= $ci['cargo_fee_paid'] ? 'bg-yellow-500 hover:bg-yellow-600 text-white' : 'bg-green-600 hover:bg-green-700 text-white' ?>">
                                <?= $ci['cargo_fee_paid'] ? 'Төлөөгүй болгох' : 'Төлсөн болгох' ?>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cargo Payments History -->
            <?php if ($cargoPayments): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">💳 Ачааны төлбөрийн бүртгэл</h3>
                <div class="space-y-2">
                    <?php foreach ($cargoPayments as $cp):
                        $cpStatus = $cp['status'];
                        $statusBadge = match($cpStatus) {
                            'paid'    => ['bg-green-100 text-green-700',  'Төлсөн'],
                            'pending' => ['bg-yellow-100 text-yellow-700', 'Хүлээгдэж байна'],
                            'failed'  => ['bg-red-100 text-red-700',      'Амжилтгүй'],
                            default   => ['bg-gray-100 text-gray-600',    $cpStatus],
                        };
                        $methodLabel = match($cp['payment_method']) {
                            'qpay'     => 'QPay',
                            'bonum'    => 'Bonum',
                            'storepay' => 'StorePay',
                            default    => $cp['payment_method'],
                        };
                    ?>
                    <div class="flex items-center justify-between text-sm border border-gray-100 rounded-lg px-3 py-2 gap-2">
                        <div class="min-w-0 flex flex-col gap-0.5">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-800"><?= $methodLabel ?></span>
                                <span class="text-gray-500"><?= formatPrice($cp['amount']) ?></span>
                            </div>
                            <?php if ($cp['invoice_id']): ?>
                                <span class="text-xs text-gray-400 font-mono truncate"><?= e($cp['invoice_id']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-col items-end gap-0.5 flex-shrink-0">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $statusBadge[0] ?>"><?= $statusBadge[1] ?></span>
                            <span class="text-xs text-gray-400 whitespace-nowrap"><?= date('m/d H:i', strtotime($cp['created_at'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
