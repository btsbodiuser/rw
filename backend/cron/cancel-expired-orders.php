<?php
// Auto-cancel unpaid orders based on payment method:
// QPay: 30 minutes, Bank transfer: 24 hours, Others: 12 hours
// Cron: */5 * * * * php /home/fitzonem/runnersworld.mn/backend/cron/cancel-expired-orders.php
// Web:  https://runnersworld.mn/backend/cron/cancel-expired-orders.php?key=YOUR_SECRET
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$isCli = php_sapi_name() === 'cli';

// If accessed via browser, require a secret key
if (!$isCli) {
    $cronKey = getSetting('cron_secret_key', '');
    if (!$cronKey || ($_GET['key'] ?? '') !== $cronKey) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$db = getDB();

// Find unpaid pending orders past their timeout
$stmt = $db->prepare("
    SELECT id, order_number, payment_method
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
    $db->beginTransaction();
    try {
        // Cancel the order
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
                $item['variant_id'] ? (int)$item['variant_id'] : null,
                +(int)$item['quantity'],
                'order_cancel',
                (int)$order['id'],
                'system',
                null,
                'Auto-cancel: ' . $order['order_number']
            );
        }

        $db->commit();
        $cancelledCount++;

        auditLog('order_auto_cancelled', 'order', $order['id'], 'system', null, [
            'order_number' => $order['order_number'],
            'reason' => 'Unpaid ' . $order['payment_method'] . ' order expired',
        ]);

        // Cancel the StorePay loan if this was a StorePay order
        if ($order['payment_method'] === 'storepay') {
            cancelStorepayOrder($db, (int)$order['id'], 'Expired unpaid order #' . $order['order_number']);
        }

    } catch (Exception $e) {
        $db->rollBack();
        if ($isCli) {
            echo "Error cancelling {$order['order_number']}: {$e->getMessage()}\n";
        }
    }
}

if ($isCli) {
    echo date('Y-m-d H:i:s') . " — Cancelled {$cancelledCount} expired orders.\n";
}

// ── Auto-confirm paid pending orders ──
$paidPending = $db->query("
    SELECT id, order_number FROM orders
    WHERE status = 'pending' AND payment_status = 'paid'
")->fetchAll();

$confirmedCount = 0;
foreach ($paidPending as $order) {
    try {
        $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")
           ->execute([$order['id']]);
        $confirmedCount++;

        auditLog('order_auto_confirmed', 'order', $order['id'], 'system', null, [
            'order_number' => $order['order_number'],
            'reason' => 'Payment received',
        ]);
    } catch (Exception $e) {
        if ($isCli) {
            echo "Error confirming {$order['order_number']}: {$e->getMessage()}\n";
        }
    }
}

if ($isCli) {
    echo date('Y-m-d H:i:s') . " — Auto-confirmed {$confirmedCount} paid orders.\n";
}

// ── Cleanup expired OTP codes ──
$otpCleanup = $db->exec("DELETE FROM otp_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
if ($isCli && $otpCleanup > 0) {
    echo date('Y-m-d H:i:s') . " — Cleaned up {$otpCleanup} expired OTP codes.\n";
}

// ── Cleanup expired customer sessions ──
$sessionCleanup = $db->exec("DELETE FROM customer_sessions WHERE expires_at < NOW()");
if ($isCli && $sessionCleanup > 0) {
    echo date('Y-m-d H:i:s') . " — Cleaned up {$sessionCleanup} expired sessions.\n";
}

// Log to file
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logMsg = date('Y-m-d H:i:s') . " — Cancelled: {$cancelledCount}, Confirmed: {$confirmedCount}, OTP cleaned: {$otpCleanup}, Sessions cleaned: {$sessionCleanup}";
file_put_contents($logDir . '/cancel-expired.log', $logMsg . "\n", FILE_APPEND | LOCK_EX);

if (!$isCli) {
    header('Content-Type: application/json');
    echo json_encode(['cancelled' => $cancelledCount, 'confirmed' => $confirmedCount, 'otp_cleaned' => $otpCleanup, 'sessions_cleaned' => $sessionCleanup]);
}
