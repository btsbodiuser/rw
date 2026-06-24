<?php
$batchId = (int)($_GET['id'] ?? 0);
if (!$batchId) { header('Location: index.php?page=cargo-batches'); exit; }

$db = getDB();

$batch = $db->prepare("SELECT * FROM cargo_batches WHERE id = ?");
$batch->execute([$batchId]);
$batch = $batch->fetch();
if (!$batch) { setFlash('error', 'Багц олдсонгүй.'); header('Location: index.php?page=cargo-batches'); exit; }

$pageTitle = 'Түгээлт — ' . $batch['name'];

// Use per-order cargo_arrived check for 'receiving' batches and 'arrived' batches that
// were processed through the FIFO arrival system (have inventory_arrivals records).
// Old 'arrived' batches (pre-FIFO, no arrival records) use the legacy all-ready logic.
$hasArrivalRecords = false;
if ($batch['status'] === 'arrived') {
    $arrCheck = $db->prepare("SELECT COUNT(*) FROM inventory_arrivals WHERE cargo_batch_id = ?");
    $arrCheck->execute([$batchId]);
    $hasArrivalRecords = (int)$arrCheck->fetchColumn() > 0;
}
$isReceivingBatch = ($batch['status'] === 'receiving') || ($batch['status'] === 'arrived' && $hasArrivalRecords);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF']);
        exit;
    }

    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($_POST['action'] === 'handout') {
        // For receiving batches, only allow handout when all items have arrived
        if ($isReceivingBatch) {
            $statusCheck = $db->prepare("SELECT status FROM orders WHERE id = ?");
            $statusCheck->execute([$orderId]);
            $currentStatus = $statusCheck->fetchColumn();
            if ($currentStatus !== 'cargo_arrived') {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Захиалгын бүх бараа ирээгүй байна. Олгох боломжгүй.']);
                    exit;
                }
                setFlash('error', 'Захиалгын бүх бараа ирээгүй байна.');
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
        $db->prepare("UPDATE orders SET status = 'picked_up' WHERE id = ? AND status != 'cancelled'")->execute([$orderId]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        setFlash('success', 'Захиалга олгогдлоо.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($_POST['action'] === 'undo_handout') {
        $db->prepare("UPDATE orders SET status = 'cargo_arrived' WHERE id = ? AND status = 'picked_up'")->execute([$orderId]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($_POST['action'] === 'mark_paid') {
        $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Fetch all orders in this batch
$orders = $db->prepare("
    SELECT DISTINCT o.*,
        d.name_mn as district_name,
        (SELECT GROUP_CONCAT(CONCAT(p.name_mn, IF(oi2.variant_label IS NOT NULL AND oi2.variant_label != '', CONCAT(' [', oi2.variant_label, ']'), ''), ' ×', oi2.quantity) ORDER BY p.name_mn SEPARATOR ', ')
         FROM order_items oi2 JOIN products p ON oi2.product_id = p.id
         WHERE oi2.order_id = o.id AND oi2.cargo_batch_id = ?) as item_summary,
        (SELECT SUM(oi3.quantity) FROM order_items oi3 WHERE oi3.order_id = o.id AND oi3.cargo_batch_id = ?) as batch_item_count,
        (SELECT GROUP_CONCAT(CONCAT(p2.name_mn, IF(oi4.variant_label IS NOT NULL AND oi4.variant_label != '', CONCAT(' [', oi4.variant_label, ']'), ''), ' ×', oi4.quantity) ORDER BY p2.name_mn SEPARATOR ', ')
         FROM order_items oi4 JOIN products p2 ON oi4.product_id = p2.id
         WHERE oi4.order_id = o.id AND oi4.cargo_batch_id = ? AND oi4.cargo_status != 'arrived') as waiting_items
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id AND oi.cargo_batch_id = ?
    LEFT JOIN districts d ON o.district_id = d.id
    WHERE o.status != 'cancelled'
    ORDER BY
        CASE WHEN o.status = 'picked_up' THEN 2
             WHEN o.status = 'cargo_arrived' THEN 0
             ELSE 1 END,
        o.customer_name ASC
");
$orders->execute([$batchId, $batchId, $batchId, $batchId]);
$orders = $orders->fetchAll();

// Stats
$totalOrders  = count($orders);
$handedOut    = count(array_filter($orders, fn($o) => $o['status'] === 'picked_up'));
$readyCount   = count(array_filter($orders, fn($o) => $o['status'] === 'cargo_arrived'));
$waitingCount = $totalOrders - $handedOut - $readyCount;
$paidCount    = count(array_filter($orders, fn($o) => $o['payment_status'] === 'paid'));
$unpaidCount  = $totalOrders - $paidCount;
$pct          = $totalOrders > 0 ? round(($handedOut / $totalOrders) * 100) : 0;
$readyPct     = $totalOrders > 0 ? round((($handedOut + $readyCount) / $totalOrders) * 100) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="index.php?page=cargo-batch-form&id=<?= $batchId ?>" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <?= e($batch['name']) ?> руу буцах
    </a>
</div>

<!-- Progress Bar -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-bold text-gray-900">📦 Түгээлт</h2>
            <span class="text-sm text-gray-500"><?= e($batch['name']) ?></span>
            <?php if ($isReceivingBatch): ?>
                <span class="px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 rounded-full">Хүлээн авч байна</span>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="text-green-600 font-medium">✅ <?= $handedOut ?> олгосон</span>
            <span class="text-blue-600 font-medium">📦 <?= $readyCount ?> бэлэн</span>
            <?php if ($waitingCount > 0): ?>
                <span class="text-yellow-600 font-medium">⏳ <?= $waitingCount ?> хүлээгдэж буй</span>
            <?php endif; ?>
            <?php if ($unpaidCount > 0): ?>
                <span class="text-red-500 font-medium">💰 <?= $unpaidCount ?> төлөөгүй</span>
            <?php endif; ?>
        </div>
    </div>
    <!-- Stacked progress bar: green (handed out) + blue (ready) + yellow (waiting) -->
    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden flex">
        <div class="bg-green-500 h-full transition-all duration-500" style="width: <?= $pct ?>%"></div>
        <div class="bg-blue-400 h-full transition-all duration-500" style="width: <?= max(0, $readyPct - $pct) ?>%"></div>
    </div>
    <div class="flex justify-between text-xs text-gray-400 mt-1">
        <span><?= $handedOut ?> олгосон / <?= $readyCount ?> бэлэн / <?= $waitingCount ?> хүлээгдэж буй</span>
        <span><?= $handedOut ?>/<?= $totalOrders ?> (<?= $pct ?>%)</span>
    </div>
</div>

<!-- Search + Tabs -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6"
     x-data="{ search: '', tab: '<?= $readyCount > 0 ? 'ready' : ($waitingCount > 0 ? 'waiting' : 'done') ?>' }">
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" placeholder="Утас, нэр, захиалгын дугаар..."
                   class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                   autofocus>
        </div>
        <div class="flex flex-wrap gap-1 bg-gray-100 rounded-lg p-1 self-start">
            <button @click="tab = 'ready'"
                    :class="tab === 'ready' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-2 rounded-md text-sm font-medium transition-all">
                📦 Бэлэн (<?= $readyCount ?>)
            </button>
            <?php if ($waitingCount > 0 || $isReceivingBatch): ?>
            <button @click="tab = 'waiting'"
                    :class="tab === 'waiting' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-2 rounded-md text-sm font-medium transition-all">
                ⏳ Хүлээгдэж буй (<?= $waitingCount ?>)
            </button>
            <?php endif; ?>
            <button @click="tab = 'done'"
                    :class="tab === 'done' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-2 rounded-md text-sm font-medium transition-all">
                ✅ Олгосон (<?= $handedOut ?>)
            </button>
            <button @click="tab = 'all'"
                    :class="tab === 'all' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-2 rounded-md text-sm font-medium transition-all">
                Бүгд (<?= $totalOrders ?>)
            </button>
        </div>
    </div>

    <!-- Orders List -->
    <div class="space-y-3">
        <?php if (empty($orders)): ?>
            <div class="text-center py-12 text-gray-400">Энэ багцад захиалга байхгүй.</div>
        <?php endif; ?>

        <?php foreach ($orders as $o):
            $isHandedOut = ($o['status'] === 'picked_up');
            $isReady     = $isReceivingBatch
                           ? ($o['status'] === 'cargo_arrived')
                           : !$isHandedOut;
            $isWaiting   = !$isHandedOut && !$isReady;
            $isPaid      = ($o['payment_status'] === 'paid');

            if ($isHandedOut)   $tabMatch = 'done';
            elseif ($isReady)   $tabMatch = 'ready';
            else                $tabMatch = 'waiting';

            $searchText = mb_strtolower(($o['customer_phone'] ?? '') . ' ' . ($o['customer_name'] ?? '') . ' ' . $o['order_number']);

            if ($isHandedOut)   $cardClass = 'bg-green-50/50 border-green-200';
            elseif ($isWaiting) $cardClass = 'bg-yellow-50/40 border-yellow-200';
            else                $cardClass = $isPaid ? 'border-gray-200 hover:border-blue-300' : 'border-orange-200 bg-orange-50/30';
        ?>
        <div class="border rounded-xl p-4 transition-all <?= $cardClass ?>"
             data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
             data-tab="<?= $tabMatch ?>"
             x-show="(tab === 'all' || tab === $el.dataset.tab) && (search === '' || $el.dataset.search.includes(search.toLowerCase()))"
             x-transition>

            <div class="flex items-start justify-between gap-4">
                <!-- Customer Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="font-bold text-gray-900"><?= e($o['customer_name'] ?: 'Нэргүй') ?></span>
                        <a href="tel:<?= e($o['customer_phone']) ?>" class="text-sm text-blue-600 hover:underline" onclick="event.stopPropagation()"><?= e($o['customer_phone']) ?></a>
                        <?php if (!$isPaid): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">Төлөөгүй</span>
                        <?php endif; ?>
                        <?php if ($isHandedOut): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">✅ Олгосон</span>
                        <?php elseif ($isWaiting): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700">⏳ Ачаа хүлээгдэж буй</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700">📦 Бэлэн</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-gray-500 mb-1">
                        <span class="font-medium text-gray-700"><?= e($o['order_number']) ?></span>
                        <span class="mx-1">·</span>
                        <span><?= (int)$o['batch_item_count'] ?> бараа</span>
                        <?php if ($o['district_name']): ?>
                            <span class="mx-1">·</span>
                            <span><?= e($o['district_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-400 leading-relaxed"><?= e($o['item_summary']) ?></p>

                    <?php if ($isWaiting && $o['waiting_items']): ?>
                        <div class="mt-2 flex items-start gap-1.5">
                            <svg class="w-3.5 h-3.5 text-yellow-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-xs text-yellow-700">Ирээгүй: <?= e($o['waiting_items']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Price + Actions -->
                <div class="text-right flex-shrink-0">
                    <p class="text-lg font-bold text-gray-900"><?= formatPrice($o['total']) ?></p>
                    <?php if ($o['cargo_fee'] > 0): ?>
                        <p class="text-xs text-orange-600">ачаа: <?= formatPrice($o['cargo_fee']) ?></p>
                    <?php endif; ?>

                    <div class="flex items-center gap-2 mt-2 justify-end">
                        <?php if (!$isPaid && !$isHandedOut): ?>
                            <form method="POST" class="inline ajax-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="mark_paid">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="px-3 py-1.5 bg-yellow-100 text-yellow-800 rounded-lg text-xs font-medium hover:bg-yellow-200 transition-colors">
                                    💰 Төлсөн
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isHandedOut): ?>
                            <form method="POST" class="inline ajax-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="undo_handout">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors">
                                    ↩ Буцаах
                                </button>
                            </form>
                        <?php elseif ($isWaiting): ?>
                            <button type="button" disabled
                                    title="Бүх бараа ирсний дараа олгох боломжтой"
                                    class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg text-sm font-bold cursor-not-allowed">
                                📦 Олгох
                            </button>
                        <?php else: ?>
                            <form method="POST" class="inline ajax-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="handout">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors active:scale-95">
                                    📦 Олгох
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="index.php?page=order-detail&id=<?= $o['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Дэлгэрэнгүй" onclick="event.stopPropagation()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div x-show="!document.querySelector('[data-tab][x-show]')" class="hidden text-center py-8 text-gray-400 text-sm">
            Хайлтад тохирох захиалга олдсонгүй.
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.ajax-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.style.opacity = '0.5';

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(this),
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Алдаа гарлаа');
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        } catch (err) {
            window.location.reload();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
