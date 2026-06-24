<?php
$pageTitle = 'POS Түүх';
$db = getDB();

// ── Handle cancel order POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    $action = $data['action'] ?? '';
    $orderId = (int)($data['order_id'] ?? 0);

    if ($action === 'cancel' && $orderId > 0) {
        try {
            $order = $db->prepare("SELECT * FROM orders WHERE id = ? AND order_type = 'pos'");
            $order->execute([$orderId]);
            $order = $order->fetch();

            if (!$order) {
                echo json_encode(['error' => 'Захиалга олдсонгүй']);
                exit;
            }
            if ($order['status'] === 'cancelled') {
                echo json_encode(['error' => 'Аль хэдийн цуцлагдсан']);
                exit;
            }

            $db->beginTransaction();

            // Restore stock via the ledger
            global $currentAdmin;
            $adminId = $currentAdmin['id'] ?? null;
            $items = $db->prepare("SELECT oi.product_id, oi.variant_id, oi.quantity FROM order_items oi WHERE oi.order_id = ?");
            $items->execute([$orderId]);
            foreach ($items->fetchAll() as $item) {
                adjustStock(
                    $db,
                    (int)$item['product_id'],
                    $item['variant_id'] ? (int)$item['variant_id'] : null,
                    +(int)$item['quantity'],
                    'pos_cancel',
                    (int)$orderId,
                    'admin',
                    $adminId,
                    'POS cancel: ' . $order['order_number']
                );
            }

            // Cancel order
            $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);

            // Audit log
            global $currentAdmin;
            auditLog('cancel', 'order', $orderId, 'admin', $currentAdmin['id'] ?? null, [
                'order_number' => $order['order_number'],
                'total' => $order['total'],
                'payment_method' => $order['payment_method'],
                'reason' => $data['reason'] ?? '',
            ]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Захиалга цуцлагдлаа']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

// Filter inputs
$dateFrom = $_GET['from'] ?? date('Y-m-d');
$dateTo = $_GET['to'] ?? $dateFrom;
$searchOrder = trim($_GET['q'] ?? '');
$paymentFilter = $_GET['pay'] ?? '';
$exportType = $_GET['export'] ?? '';
$page = max(1, (int)($_GET['pg'] ?? 1));

// Build WHERE clause
$where = "o.order_type = 'pos'";
$params = [];
$summaryWhere = "order_type = 'pos' AND status != 'cancelled'";
$summaryParams = [];

// Date range
$where .= " AND DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?";
$params[] = $dateFrom;
$params[] = $dateTo;
$summaryWhere .= " AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
$summaryParams[] = $dateFrom;
$summaryParams[] = $dateTo;

// Order number search
if ($searchOrder !== '') {
    $where .= " AND o.order_number LIKE ?";
    $params[] = '%' . $searchOrder . '%';
    $summaryWhere .= " AND order_number LIKE ?";
    $summaryParams[] = '%' . $searchOrder . '%';
}

// Payment method
if ($paymentFilter && in_array($paymentFilter, ['cash', 'card', 'transfer', 'transfer_nomin', 'split'])) {
    $where .= " AND o.payment_method = ?";
    $params[] = $paymentFilter;
    $summaryWhere .= " AND payment_method = ?";
    $summaryParams[] = $paymentFilter;
}

// Summary for filtered results (needed for both export and display)
$summaryStmt = $db->prepare("SELECT 
    COUNT(*) as total_sales, 
    COALESCE(SUM(total), 0) as total_revenue,
    COALESCE(SUM(payment_cash), 0) as cash_total,
    COALESCE(SUM(payment_card), 0) as card_total,
    COALESCE(SUM(payment_transfer), 0) as transfer_total,
    COALESCE(SUM(payment_transfer_nomin), 0) as transfer_nomin_total,
    COALESCE(SUM(vat_amount), 0) as vat_total
    FROM orders WHERE $summaryWhere");
$summaryStmt->execute($summaryParams);
$summary = $summaryStmt->fetch();

// Count cancelled for info
$cancelledStmt = $db->prepare("SELECT COUNT(*) FROM orders o WHERE $where AND o.status = 'cancelled'");
$cancelledStmt->execute($params);
$cancelledCount = (int)$cancelledStmt->fetchColumn();

$paymentLabels = ['cash' => 'Бэлэн', 'card' => 'Карт', 'transfer' => 'Шилжүүлэг', 'transfer_nomin' => 'Номин шилжүүлэг', 'split' => 'Хуваасан'];
$isSingleDay = ($dateFrom === $dateTo);
$siteName = getSetting('site_name', 'Runners World');

// ── Export: CSV ──
if ($exportType === 'csv') {
    $allStmt = $db->prepare("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count FROM orders o WHERE $where ORDER BY o.created_at DESC");
    $allStmt->execute($params);
    $allOrders = $allStmt->fetchAll();

    $filename = 'POS_Report_' . $dateFrom . ($isSingleDay ? '' : '_' . $dateTo) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Захиалга №', 'Огноо', 'Бараа тоо', 'Дүн', 'НӨАТ', 'Нийт', 'Төлбөрийн арга', 'Бэлэн', 'Карт', 'Шилжүүлэг', 'Номин шилжүүлэг']);
    foreach ($allOrders as $o) {
        fputcsv($out, [
            $o['order_number'],
            date('Y-m-d H:i', strtotime($o['created_at'])),
            $o['item_count'],
            $o['subtotal'],
            $o['vat_amount'],
            $o['total'],
            $paymentLabels[$o['payment_method']] ?? $o['payment_method'],
            $o['payment_cash'],
            $o['payment_card'],
            $o['payment_transfer'],
            $o['payment_transfer_nomin'],
        ]);
    }
    // Summary row
    fputcsv($out, []);
    fputcsv($out, ['НИЙТ', '', $summary['total_sales'], '', $summary['vat_total'], $summary['total_revenue'], '', $summary['cash_total'], $summary['card_total'], $summary['transfer_total'], $summary['transfer_nomin_total']]);
    fclose($out);
    exit;
}

// ── Export: Print (beautiful HTML report) ──
if ($exportType === 'print') {
    $allStmt = $db->prepare("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count FROM orders o WHERE $where ORDER BY o.created_at ASC");
    $allStmt->execute($params);
    $allOrders = $allStmt->fetchAll();

    $dateLabel = $isSingleDay ? date('Y.m.d', strtotime($dateFrom)) : date('Y.m.d', strtotime($dateFrom)) . ' — ' . date('Y.m.d', strtotime($dateTo));
    ?>
    <!DOCTYPE html>
    <html lang="mn">
    <head>
    <meta charset="UTF-8">
    <title>POS Тайлан — <?= e($dateLabel) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 12px; color: #1a1a1a; padding: 24px; max-width: 900px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 16px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .header .subtitle { font-size: 13px; color: #555; margin-top: 4px; }
        .header .date-range { font-size: 15px; font-weight: 600; margin-top: 8px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .summary-box { border: 1px solid #ddd; border-radius: 8px; padding: 12px; text-align: center; }
        .summary-box .label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-box .value { font-size: 18px; font-weight: 700; margin-top: 4px; }
        .summary-box.total { background: #f0fdf4; border-color: #86efac; }
        .summary-box.total .value { color: #16a34a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f8f9fa; text-align: left; padding: 8px 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #555; border-bottom: 2px solid #ddd; }
        td { padding: 7px 10px; border-bottom: 1px solid #eee; font-size: 12px; }
        tr:nth-child(even) { background: #fafafa; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-medium { font-weight: 500; }
        .font-bold { font-weight: 700; }
        .footer-row td { font-weight: 700; background: #f8f9fa; border-top: 2px solid #333; font-size: 13px; }
        .report-footer { text-align: center; font-size: 11px; color: #999; margin-top: 24px; padding-top: 12px; border-top: 1px solid #eee; }
        .payment-breakdown { margin-bottom: 24px; }
        .payment-breakdown h3 { font-size: 13px; font-weight: 600; margin-bottom: 8px; }
        .bar-row { display: flex; align-items: center; margin-bottom: 6px; }
        .bar-label { width: 100px; font-size: 12px; color: #555; }
        .bar-track { flex: 1; height: 22px; background: #f3f4f6; border-radius: 4px; overflow: hidden; margin: 0 10px; }
        .bar-fill { height: 100%; border-radius: 4px; display: flex; align-items: center; padding-left: 8px; font-size: 11px; color: #fff; font-weight: 600; min-width: fit-content; }
        .bar-fill.cash { background: #16a34a; }
        .bar-fill.card { background: #2563eb; }
        .bar-fill.transfer { background: #9333ea; }
        .bar-fill.transfer-nomin { background: #0d9488; }
        .bar-amount { width: 100px; text-align: right; font-weight: 600; font-size: 12px; }
        @media print {
            body { padding: 12px; }
            .no-print { display: none !important; }
        }
    </style>
    </head>
    <body>
    <div class="no-print" style="text-align:center; margin-bottom:16px;">
        <button onclick="window.print()" style="padding:10px 32px; background:#111; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;">🖨️ Хэвлэх</button>
        <button onclick="window.close()" style="padding:10px 24px; background:#f3f4f6; color:#333; border:1px solid #ddd; border-radius:8px; font-size:14px; cursor:pointer; margin-left:8px;">Хаах</button>
    </div>

    <div class="header">
        <h1><?= e($siteName) ?></h1>
        <div class="subtitle">POS Борлуулалтын Тайлан</div>
        <div class="date-range">📅 <?= e($dateLabel) ?></div>
        <?php if ($paymentFilter): ?>
        <div class="subtitle" style="margin-top:4px;">Шүүлтүүр: <?= e($paymentLabels[$paymentFilter] ?? $paymentFilter) ?></div>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-box total">
            <div class="label">Нийт орлого</div>
            <div class="value"><?= formatPrice($summary['total_revenue']) ?></div>
        </div>
        <div class="summary-box">
            <div class="label">Борлуулалтын тоо</div>
            <div class="value"><?= $summary['total_sales'] ?></div>
        </div>
        <div class="summary-box">
            <div class="label">НӨАТ</div>
            <div class="value" style="color:#ea580c;"><?= formatPrice($summary['vat_total']) ?></div>
        </div>
    </div>

    <!-- Payment Breakdown Chart -->
    <div class="payment-breakdown">
        <h3>Төлбөрийн төрлөөр</h3>
        <?php
        $maxPayment = max((float)$summary['cash_total'], (float)$summary['card_total'], (float)$summary['transfer_total'], (float)$summary['transfer_nomin_total'], 1);
        $paymentBars = [
            ['label' => '💵 Бэлэн', 'amount' => $summary['cash_total'], 'class' => 'cash'],
            ['label' => '💳 Карт', 'amount' => $summary['card_total'], 'class' => 'card'],
            ['label' => '📲 Шилжүүлэг', 'amount' => $summary['transfer_total'], 'class' => 'transfer'],
            ['label' => '🏪 Номин', 'amount' => $summary['transfer_nomin_total'], 'class' => 'transfer-nomin'],
        ];
        foreach ($paymentBars as $bar):
            $pct = $maxPayment > 0 ? round(((float)$bar['amount'] / (float)$summary['total_revenue']) * 100) : 0;
            $barWidth = $maxPayment > 0 ? max(round(((float)$bar['amount'] / $maxPayment) * 100), ($bar['amount'] > 0 ? 8 : 0)) : 0;
        ?>
        <div class="bar-row">
            <div class="bar-label"><?= $bar['label'] ?></div>
            <div class="bar-track">
                <div class="bar-fill <?= $bar['class'] ?>" style="width:<?= $barWidth ?>%"><?= $pct ?>%</div>
            </div>
            <div class="bar-amount"><?= formatPrice($bar['amount']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Orders Table -->
    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>Захиалга</th>
                <th><?= $isSingleDay ? 'Цаг' : 'Огноо' ?></th>
                <th class="text-center">Бараа</th>
                <th class="text-right">Дүн</th>
                <th class="text-right">НӨАТ</th>
                <th class="text-right">Нийт</th>
                <th class="text-center">Төлбөр</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($allOrders as $i => $o): $isCancelled = ($o['status'] === 'cancelled'); ?>
            <tr<?= $isCancelled ? ' style="color:#999;text-decoration:line-through;"' : '' ?>>
                <td><?= $i + 1 ?></td>
                <td class="font-medium"><?= e($o['order_number']) ?><?= $isCancelled ? ' <span style="text-decoration:none;color:#dc2626;font-size:10px;font-weight:700;">Цуцлагдсан</span>' : '' ?></td>
                <td><?= $isSingleDay ? date('H:i', strtotime($o['created_at'])) : date('m/d H:i', strtotime($o['created_at'])) ?></td>
                <td class="text-center"><?= $o['item_count'] ?></td>
                <td class="text-right"><?= formatPrice($o['subtotal']) ?></td>
                <td class="text-right"><?= $o['vat_included'] ? formatPrice($o['vat_amount']) : '—' ?></td>
                <td class="text-right font-medium"><?= formatPrice($o['total']) ?></td>
                <td class="text-center" style="text-decoration:none;"><?= e($paymentLabels[$o['payment_method']] ?? ucfirst($o['payment_method'])) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="footer-row">
                <td colspan="3">НИЙТ (<?= $summary['total_sales'] ?> борлуулалт<?= $cancelledCount > 0 ? ', ' . $cancelledCount . ' цуцлагдсан' : '' ?>)</td>
                <td class="text-center"><?= count($allOrders) ?></td>
                <td class="text-right"><?= formatPrice($summary['total_revenue'] - $summary['vat_total']) ?></td>
                <td class="text-right"><?= formatPrice($summary['vat_total']) ?></td>
                <td class="text-right"><?= formatPrice($summary['total_revenue']) ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="report-footer">
        <?= e($siteName) ?> — Тайлан үүсгэсэн: <?= date('Y.m.d H:i') ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Normal page rendering ──
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o WHERE $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmt = $db->prepare("SELECT o.*, 
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count 
    FROM orders o WHERE $where 
    ORDER BY o.created_at DESC 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Load delivery + driver info for these orders
$deliveryMap = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $delStmt = $db->prepare("
        SELECT d.order_id, d.status as delivery_status, dd.name as driver_name, dd.phone as driver_phone
        FROM deliveries d
        LEFT JOIN delivery_drivers dd ON d.driver_id = dd.id
        WHERE d.order_id IN ($placeholders)
        ORDER BY d.id DESC
    ");
    $delStmt->execute($orderIds);
    foreach ($delStmt->fetchAll() as $del) {
        if (!isset($deliveryMap[$del['order_id']])) {
            $deliveryMap[$del['order_id']] = $del;
        }
    }
}

// Date range label
$isToday = ($dateFrom === date('Y-m-d') && $dateTo === date('Y-m-d'));

// Quick preset URLs
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');
$hasFilters = ($searchOrder !== '' || $paymentFilter !== '' || !$isToday);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="space-y-3">
        <input type="hidden" name="page" value="pos-history">
        <div class="flex flex-col lg:flex-row gap-3">
            <!-- Date range -->
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500 whitespace-nowrap">Эхлэх</label>
                <input type="date" name="from" value="<?= e($dateFrom) ?>"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <label class="text-xs text-gray-500 whitespace-nowrap">Дуусах</label>
                <input type="date" name="to" value="<?= e($dateTo) ?>"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <!-- Order number search -->
            <div class="relative flex-1 max-w-xs">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= e($searchOrder) ?>" placeholder="Захиалга №..."
                       class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <!-- Payment method -->
            <select name="pay" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх төлбөр</option>
                <option value="cash" <?= $paymentFilter === 'cash' ? 'selected' : '' ?>>💵 Бэлэн</option>
                <option value="card" <?= $paymentFilter === 'card' ? 'selected' : '' ?>>💳 Карт</option>
                <option value="transfer" <?= $paymentFilter === 'transfer' ? 'selected' : '' ?>>📲 Шилжүүлэг</option>
                <option value="transfer_nomin" <?= $paymentFilter === 'transfer_nomin' ? 'selected' : '' ?>>🏪 Номин шилжүүлэг</option>
                <option value="split" <?= $paymentFilter === 'split' ? 'selected' : '' ?>>✂️ Хуваасан</option>
            </select>

            <!-- Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Хайх</button>
                <?php if ($hasFilters): ?>
                <a href="index.php?page=pos-history" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 text-gray-600">✕ Цэвэрлэх</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick presets -->
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs text-gray-400">Түргэн:</span>
            <a href="index.php?page=pos-history&from=<?= $today ?>&to=<?= $today ?>"
               class="px-2.5 py-1 rounded-full text-xs font-medium transition-colors <?= $isToday && !$searchOrder && !$paymentFilter ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Өнөөдөр</a>
            <a href="index.php?page=pos-history&from=<?= date('Y-m-d', strtotime('-1 day')) ?>&to=<?= date('Y-m-d', strtotime('-1 day')) ?>"
               class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">Өчигдөр</a>
            <a href="index.php?page=pos-history&from=<?= $weekStart ?>&to=<?= $today ?>"
               class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">Энэ 7 хоног</a>
            <a href="index.php?page=pos-history&from=<?= $monthStart ?>&to=<?= $today ?>"
               class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">Энэ сар</a>
            <a href="index.php?page=pos-history&from=<?= date('Y-m-01', strtotime('-1 month')) ?>&to=<?= date('Y-m-t', strtotime('-1 month')) ?>"
               class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">Өмнөх сар</a>
            <span class="text-xs text-gray-400 ml-2">
                <?php if ($isSingleDay): ?>
                    📅 <?= date('Y.m.d', strtotime($dateFrom)) ?>
                <?php else: ?>
                    📅 <?= date('Y.m.d', strtotime($dateFrom)) ?> — <?= date('Y.m.d', strtotime($dateTo)) ?>
                <?php endif; ?>
            </span>
        </div>
    </form>
    <div class="flex items-center justify-between mt-2">
        <div class="flex items-center gap-2">
            <?php
            $exportParams = 'from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo);
            if ($searchOrder !== '') $exportParams .= '&q=' . urlencode($searchOrder);
            if ($paymentFilter !== '') $exportParams .= '&pay=' . urlencode($paymentFilter);
            ?>
            <a href="index.php?page=pos-history&<?= $exportParams ?>&export=print" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors">
                🖨️ Тайлан хэвлэх
            </a>
            <a href="index.php?page=pos-history&<?= $exportParams ?>&export=csv"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors">
                📥 CSV татах
            </a>
        </div>
    </div>
</div>

<!-- Summary -->
<div class="grid grid-cols-2 lg:grid-cols-7 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Борлуулалт</p>
        <p class="text-xl font-bold text-gray-900 mt-1"><?= $summary['total_sales'] ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Нийт орлого</p>
        <p class="text-xl font-bold text-green-600 mt-1"><?= formatPrice($summary['total_revenue']) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Бэлэн</p>
        <p class="text-xl font-bold text-gray-900 mt-1"><?= formatPrice($summary['cash_total']) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Карт</p>
        <p class="text-xl font-bold text-gray-900 mt-1"><?= formatPrice($summary['card_total']) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Шилжүүлэг</p>
        <p class="text-xl font-bold text-gray-900 mt-1"><?= formatPrice($summary['transfer_total']) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Номин шилжүүлэг</p>
        <p class="text-xl font-bold text-gray-900 mt-1"><?= formatPrice($summary['transfer_nomin_total']) ?></p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-orange-100">
        <p class="text-xs text-gray-500">НӨАТ</p>
        <p class="text-xl font-bold text-orange-600 mt-1"><?= formatPrice($summary['vat_total']) ?></p>
    </div>
</div>

<!-- Sales Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Захиалга №</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Тоо</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Нийт</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">НӨАТ</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Төлбөр</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Хүргэлт</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase"><?= $isSingleDay ? 'Цаг' : 'Огноо / Цаг' ?></th>
                    <th class="text-center px-3 py-3 text-xs font-medium text-gray-500 uppercase w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">Борлуулалт олдсонгүй</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o):
                    $isCancelled = ($o['status'] === 'cancelled');
                ?>
                <tr class="<?= $isCancelled ? 'bg-red-50/50 opacity-60' : 'hover:bg-gray-50 cursor-pointer' ?>" <?= $isCancelled ? '' : "onclick=\"window.location='index.php?page=order-detail&id={$o['id']}'\"" ?>>
                    <td class="px-5 py-3 text-sm font-medium <?= $isCancelled ? 'text-gray-400 line-through' : 'text-blue-600' ?>">
                        <?= e($o['order_number']) ?>
                        <?php if ($isCancelled): ?>
                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-600 no-underline inline-block" style="text-decoration:none">Цуцлагдсан</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-center text-gray-600"><?= $o['item_count'] ?></td>
                    <td class="px-5 py-3 text-sm text-right font-medium <?= $isCancelled ? 'line-through text-gray-400' : '' ?>"><?= formatPrice($o['total']) ?></td>
                    <td class="px-5 py-3 text-sm text-center">
                        <?php if ($o['vat_included']): ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700"><?= formatPrice($o['vat_amount']) ?></span>
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-center">
                        <?php if ($o['payment_method'] === 'split'): ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Хуваасан</span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><?= e($paymentLabels[$o['payment_method']] ?? ucfirst($o['payment_method'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-center">
                        <?php
                        $del = $deliveryMap[$o['id']] ?? null;
                        if ($del):
                            $delStatusColors = [
                                'assigned'  => 'bg-blue-100 text-blue-700',
                                'picked_up' => 'bg-yellow-100 text-yellow-700',
                                'delivered' => 'bg-green-100 text-green-700',
                                'failed'    => 'bg-red-100 text-red-700',
                            ];
                            $delStatusLabels = [
                                'assigned'  => 'Хуваарилсан',
                                'picked_up' => 'Авсан',
                                'delivered' => 'Хүргэсэн',
                                'failed'    => 'Амжилтгүй',
                            ];
                            $cls = $delStatusColors[$del['delivery_status']] ?? 'bg-gray-100 text-gray-700';
                            $lbl = $delStatusLabels[$del['delivery_status']] ?? $del['delivery_status'];
                        ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $cls ?>"><?= $lbl ?></span>
                            <div class="text-[11px] text-gray-500 mt-0.5">🚗 <?= e($del['driver_name']) ?></div>
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-right text-gray-500">
                        <?php if ($isSingleDay): ?>
                            <?= date('H:i', strtotime($o['created_at'])) ?>
                        <?php else: ?>
                            <?= date('m/d H:i', strtotime($o['created_at'])) ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <?php if (!$isCancelled): ?>
                        <button onclick="event.stopPropagation(); openCancelModal(<?= $o['id'] ?>, '<?= e($o['order_number']) ?>', '<?= formatPrice($o['total']) ?>')"
                                class="text-xs text-red-400 hover:text-red-600 hover:bg-red-50 px-2 py-1 rounded transition-colors" title="Цуцлах">
                            ✕
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$filterUrl = 'index.php?page=pos-history&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo);
if ($searchOrder !== '') $filterUrl .= '&q=' . urlencode($searchOrder);
if ($paymentFilter !== '') $filterUrl .= '&pay=' . urlencode($paymentFilter);
renderPagination($pagination, $filterUrl);
?>

<?php if ($cancelledCount > 0): ?>
<div class="mt-4 text-center">
    <span class="text-xs text-gray-400">Цуцлагдсан: <?= $cancelledCount ?> захиалга (нийт дүнд ороогүй)</span>
</div>
<?php endif; ?>

<!-- Cancel Confirmation Modal -->
<div id="cancelModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center">
        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">Захиалга цуцлах</h3>
        <p class="text-sm text-gray-500 mb-1">
            <span id="cancelOrderNumber" class="font-medium text-gray-700"></span> — <span id="cancelOrderTotal" class="font-medium"></span>
        </p>
        <p class="text-xs text-gray-400 mb-4">Нөөц буцаагдана. Энэ үйлдлийг буцаах боломжгүй.</p>

        <textarea id="cancelReason" placeholder="Шалтгаан (заавал биш)..." rows="2"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none mb-4 resize-none"></textarea>

        <div class="grid grid-cols-2 gap-3">
            <button onclick="closeCancelModal()"
                    class="py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors text-sm">
                Буцах
            </button>
            <button id="confirmCancelBtn" onclick="confirmCancel()"
                    class="py-2.5 rounded-xl font-bold text-white bg-red-600 hover:bg-red-700 transition-colors text-sm">
                Цуцлах
            </button>
        </div>
    </div>
</div>

<script>
let cancelOrderId = null;
const csrfToken = '<?= generateCSRFToken() ?>';

function openCancelModal(orderId, orderNumber, orderTotal) {
    cancelOrderId = orderId;
    document.getElementById('cancelOrderNumber').textContent = orderNumber;
    document.getElementById('cancelOrderTotal').textContent = orderTotal;
    document.getElementById('cancelReason').value = '';
    const modal = document.getElementById('cancelModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeCancelModal() {
    cancelOrderId = null;
    const modal = document.getElementById('cancelModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function confirmCancel() {
    if (!cancelOrderId) return;
    const btn = document.getElementById('confirmCancelBtn');
    btn.disabled = true;
    btn.textContent = '...';

    try {
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                action: 'cancel',
                order_id: cancelOrderId,
                reason: document.getElementById('cancelReason').value.trim(),
            }),
        });
        const data = await res.json();
        if (data.error) {
            alert(data.error);
        } else {
            window.location.reload();
        }
    } catch (e) {
        alert('Алдаа гарлаа');
    }
    btn.disabled = false;
    btn.textContent = 'Цуцлах';
}

// Close on Escape or outside click
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCancelModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
