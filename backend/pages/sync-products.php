<?php
$pageTitle = 'Синхрончлол';
$db = getDB();
requireRole('super_admin', 'admin');

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=sync-products');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ── Sync cargo_fee_paid ──
    if ($action === 'sync_cargo_fee') {
        try {
            $db->beginTransaction();

            // Mark cargo_fee_paid=1 for preorder items with visible cargo fee
            // Exclude orders created via admin order-create page
            $stmt1 = $db->exec("
                UPDATE order_items oi
                JOIN products p ON oi.product_id = p.id
                SET oi.cargo_fee_paid = 1
                WHERE p.type = 'preorder'
                  AND p.hide_cargo_fee = 0
                  AND oi.cargo_fee > 0
                  AND oi.cargo_fee_paid = 0
                  AND oi.order_id NOT IN (
                      SELECT al.entity_id FROM audit_log al
                      WHERE al.entity_type = 'order' AND al.action = 'create'
                        AND al.details LIKE '%admin_create%'
                  )
            ");

            // Re-sync order-level cargo_fee_paid (exclude admin-created orders)
            $stmt2 = $db->exec("
                UPDATE orders o
                SET o.cargo_fee_paid = (
                    SELECT CASE WHEN COUNT(*) = 0 THEN o.cargo_fee_paid
                                WHEN SUM(CASE WHEN oi2.cargo_fee_paid = 0 THEN 1 ELSE 0 END) = 0 THEN 1
                                ELSE 0 END
                    FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.cargo_fee > 0
                )
                WHERE o.cargo_fee > 0
                  AND o.id NOT IN (
                      SELECT al.entity_id FROM audit_log al
                      WHERE al.entity_type = 'order' AND al.action = 'create'
                        AND al.details LIKE '%admin_create%'
                  )
            ");

            $db->commit();

            global $currentAdmin;
            auditLog('sync', 'cargo_fee', null, 'admin', $currentAdmin['id'], [
                'items_updated' => $stmt1,
                'orders_updated' => $stmt2,
            ]);

            setFlash('success', "Ачааны хураамж синхрончлогдлоо. {$stmt1} бараа, {$stmt2} захиалга шинэчлэгдлээ.");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Алдаа: ' . $e->getMessage());
        }
        header('Location: index.php?page=sync-products');
        exit;
    }

    // ── Sync order_items product names ──
    if ($action === 'sync_product_names') {
        try {
            $updated = $db->exec("
                UPDATE order_items oi
                JOIN products p ON oi.product_id = p.id
                SET oi.product_name = p.name
                WHERE oi.product_name != p.name
            ");

            global $currentAdmin;
            auditLog('sync', 'product_names', null, 'admin', $currentAdmin['id'], [
                'items_updated' => $updated,
            ]);

            setFlash('success', "Барааны нэр синхрончлогдлоо. {$updated} бараа шинэчлэгдлээ.");
        } catch (Exception $e) {
            setFlash('error', 'Алдаа: ' . $e->getMessage());
        }
        header('Location: index.php?page=sync-products');
        exit;
    }

    // ── Cancel expired orders ──
    if ($action === 'cancel_expired') {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                SELECT id, order_number
                FROM orders
                WHERE status = 'pending'
                  AND payment_status = 'pending'
                  AND (
                    (payment_method IN ('qpay', 'bonum', 'storepay') AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR))
                    OR (payment_method = 'transfer' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
                    OR (payment_method NOT IN ('qpay', 'bonum', 'storepay', 'transfer') AND created_at < DATE_SUB(NOW(), INTERVAL 12 HOUR))
                  )
            ");
            $stmt->execute();
            $expiredOrders = $stmt->fetchAll();

            $cancelledCount = 0;
            foreach ($expiredOrders as $order) {
                $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'")
                   ->execute([$order['id']]);

                // Restore stock via the ledger
                $items = $db->prepare("
                    SELECT oi.product_id, oi.variant_id, oi.quantity
                    FROM order_items oi
                    WHERE oi.order_id = ?
                ");
                $items->execute([$order['id']]);
                foreach ($items->fetchAll() as $item) {
                    adjustStock(
                        $db,
                        (int)$item['product_id'],
                        !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                        +(int)$item['quantity'],
                        'order_cancel',
                        (int)$order['id'],
                        'admin',
                        $currentAdmin['id'] ?? null,
                        'Sync: cancel expired'
                    );
                }
                $cancelledCount++;
            }

            $db->commit();

            global $currentAdmin;
            auditLog('sync', 'cancel_expired', null, 'admin', $currentAdmin['id'], [
                'cancelled_count' => $cancelledCount,
            ]);

            setFlash('success', "Хугацаа дууссан {$cancelledCount} захиалга цуцлагдлаа.");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Алдаа: ' . $e->getMessage());
        }
        header('Location: index.php?page=sync-products');
        exit;
    }
}

// ── Stats for display ──
$pendingCargoItems = $db->query("
    SELECT COUNT(*) FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE p.type = 'preorder'
      AND p.hide_cargo_fee = 0
      AND oi.cargo_fee > 0
      AND oi.cargo_fee_paid = 0
      AND oi.order_id NOT IN (
          SELECT al.entity_id FROM audit_log al
          WHERE al.entity_type = 'order' AND al.action = 'create'
            AND al.details LIKE '%admin_create%'
      )
")->fetchColumn();

$expiredOrderCount = $db->query("
    SELECT COUNT(*) FROM orders
    WHERE status = 'pending'
      AND payment_status = 'pending'
      AND (
        (payment_method IN ('qpay', 'bonum', 'storepay') AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR))
        OR (payment_method = 'transfer' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
        OR (payment_method NOT IN ('qpay', 'bonum', 'storepay', 'transfer') AND created_at < DATE_SUB(NOW(), INTERVAL 12 HOUR))
      )
")->fetchColumn();

$mismatchedNames = $db->query("
    SELECT COUNT(*) FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.product_name != p.name
")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/20">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        </div>
        <div>
            <h2 class="text-xl font-bold text-gray-900">Синхрончлол</h2>
            <p class="text-sm text-gray-500">Системийн өгөгдлийг гараар синхрончлох</p>
        </div>
    </div>

    <!-- Sync Cargo Fee -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    Ачааны хураамж синхрончлох
                </h3>
                <p class="text-sm text-gray-500 mt-2 ml-10">
                    Урьдчилсан захиалгын барааны ачааны хураамж төлөгдсөн эсэхийг шинэчлэх.
                    Харагдах ачааны хураамжтай бүх барааг <strong>cargo_fee_paid = 1</strong> болгож,
                    захиалгын түвшинд нэгтгэнэ.
                </p>
                <?php if ($pendingCargoItems > 0): ?>
                <p class="text-sm mt-2 ml-10">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <?= (int)$pendingCargoItems ?> бараа хүлээгдэж байна
                    </span>
                </p>
                <?php else: ?>
                <p class="text-sm mt-2 ml-10">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Бүгд синхрончлогдсон
                    </span>
                </p>
                <?php endif; ?>
            </div>
            <form method="POST" class="shrink-0 ml-4">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="sync_cargo_fee">
                <button type="submit" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm"
                        onclick="return confirm('Ачааны хураамж синхрончлох уу?')">
                    <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Синхрончлох
                </button>
            </form>
        </div>
    </div>

    <!-- Sync Product Names -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <span class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </span>
                    Барааны нэр синхрончлох
                </h3>
                <p class="text-sm text-gray-500 mt-2 ml-10">
                    Захиалгын барааны нэрийг (<strong>order_items.product_name</strong>) одоогийн бүтээгдэхүүний нэрээр (<strong>products.name</strong>) шинэчлэх.
                    Бараа нэрээ өөрчилсөн тохиолдолд хуучин захиалгуудад тусгана.
                </p>
                <?php if ($mismatchedNames > 0): ?>
                <p class="text-sm mt-2 ml-10">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <?= (int)$mismatchedNames ?> бараа зөрүүтэй
                    </span>
                </p>
                <?php else: ?>
                <p class="text-sm mt-2 ml-10">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Бүгд таарч байна
                    </span>
                </p>
                <?php endif; ?>
            </div>
            <form method="POST" class="shrink-0 ml-4">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="sync_product_names">
                <button type="submit" class="px-4 py-2.5 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors shadow-sm"
                        <?= $mismatchedNames == 0 ? 'disabled' : '' ?>
                        onclick="return confirm('<?= (int)$mismatchedNames ?> барааны нэр шинэчлэх үү?')">
                    <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Синхрончлох
                </button>
            </form>
        </div>
    </div>

    <!-- Cancel Expired Orders -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <span class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    Хугацаа дууссан захиалга цуцлах
                </h3>
                <p class="text-sm text-gray-500 mt-2 ml-10">
                    1 цагаас дээш хугацаанд төлбөр төлөгдөөгүй, хүлээгдэж буй захиалгуудыг автоматаар цуцлах.
                    Бэлэн барааны нөөцийг буцаан нэмнэ.
                </p>
                <?php if ($expiredOrderCount > 0): ?>
                <p class="text-sm mt-2 ml-10">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        <?= (int)$expiredOrderCount ?> хугацаа дууссан захиалга
                    </span>
                </p>
                <?php else: ?>
                <p class="text-sm mt-2 ml-10">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Хугацаа дууссан захиалга байхгүй
                    </span>
                </p>
                <?php endif; ?>
            </div>
            <form method="POST" class="shrink-0 ml-4">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="cancel_expired">
                <button type="submit" class="px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors shadow-sm"
                        <?= $expiredOrderCount == 0 ? 'disabled' : '' ?>
                        onclick="return confirm('<?= (int)$expiredOrderCount ?> хугацаа дууссан захиалга цуцлах уу?')">
                    <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Цуцлах
                </button>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
