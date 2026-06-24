<?php
// Periodically re-sync cargo_fee_paid on order_items and orders.
// Cron: */5 * * * * php /path/to/backend/cron/sync-cargo-fee-paid.php
// Web:  https://runnersworld.mn/backend/cron/sync-cargo-fee-paid.php?key=YOUR_SECRET
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

$msg = date('Y-m-d H:i:s') . " — Synced cargo_fee_paid: {$stmt1} item(s), {$stmt2} order(s) updated.";

// Log to file
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents($logDir . '/sync-cargo-fee.log', $msg . "\n", FILE_APPEND | LOCK_EX);

if ($isCli) {
    echo $msg . "\n";
} else {
    echo json_encode(['success' => true, 'message' => $msg]);
}
