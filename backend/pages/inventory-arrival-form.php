<?php
$pageTitle = 'Ирэлт бүртгэх';
$db = getDB();

$arrivalId = (int)($_GET['id'] ?? 0);
$batchId   = (int)($_GET['batch_id'] ?? 0);
$arrival   = null;
$existingItems = [];

if ($arrivalId) {
    $stmt = $db->prepare("SELECT * FROM inventory_arrivals WHERE id = ?");
    $stmt->execute([$arrivalId]);
    $arrival = $stmt->fetch();
    if (!$arrival) {
        setFlash('error', 'Ирэлт олдсонгүй.');
        header('Location: index.php?page=inventory-arrivals');
        exit;
    }
    $pageTitle = 'Ирэлт дэлгэрэнгүй';

    $iStmt = $db->prepare("
        SELECT iai.*, p.name_mn as product_name,
               pc.name_mn as color_name, ps.name as size_name
        FROM inventory_arrival_items iai
        JOIN products p ON p.id = iai.product_id
        LEFT JOIN product_variants pv ON pv.id = iai.variant_id
        LEFT JOIN product_colors pc ON pc.id = pv.color_id
        LEFT JOIN product_sizes ps ON ps.id = pv.size_id
        WHERE iai.arrival_id = ?
        ORDER BY iai.id ASC
    ");
    $iStmt->execute([$arrivalId]);
    $existingItems = $iStmt->fetchAll();
}

// ── AJAX: items still waiting in a cargo batch ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'batch_items') {
    header('Content-Type: application/json');
    $bid = (int)($_GET['batch_id'] ?? 0);
    if (!$bid) { echo json_encode(['items' => []]); exit; }

    $stmt = $db->prepare("
        SELECT
            oi.product_id,
            oi.variant_id,
            p.name_mn AS product_name,
            COALESCE(oi.variant_label, '') AS variant_label,
            pc.name_mn AS color_name,
            ps.name    AS size_name,
            SUM(oi.quantity) AS total_ordered,
            SUM(CASE WHEN oi.cargo_status = 'arrived' THEN oi.quantity ELSE 0 END) AS total_arrived,
            SUM(CASE WHEN oi.cargo_status != 'arrived' THEN oi.quantity ELSE 0 END) AS still_waiting
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        LEFT JOIN product_variants pv ON pv.id  = oi.variant_id
        LEFT JOIN product_colors   pc ON pc.id  = pv.color_id
        LEFT JOIN product_sizes    ps ON ps.id  = pv.size_id
        WHERE oi.cargo_batch_id = ?
          AND o.status NOT IN ('cancelled', 'picked_up', 'delivered', 'completed')
        GROUP BY oi.product_id, oi.variant_id, oi.variant_label,
                 p.name_mn, pc.name_mn, ps.name, pc.sort_order, ps.sort_order
        HAVING still_waiting > 0
        ORDER BY p.name_mn ASC, pc.sort_order ASC, ps.sort_order ASC
    ");
    $stmt->execute([$bid]);
    echo json_encode(['items' => $stmt->fetchAll()]);
    exit;
}

// ── AJAX: product search (for extra rows) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search_products') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { echo json_encode([]); exit; }

    $stmt = $db->prepare("
        SELECT p.id, p.name_mn, p.has_variants,
               (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) as variant_count
        FROM products p
        WHERE p.is_active = 1 AND (p.name_mn LIKE ? OR p.name LIKE ?)
        ORDER BY p.name_mn ASC LIMIT 20
    ");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── AJAX: variants for a product (for extra rows) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_variants') {
    header('Content-Type: application/json');
    $pid = (int)($_GET['product_id'] ?? 0);
    if (!$pid) { echo json_encode([]); exit; }

    $stmt = $db->prepare("
        SELECT pv.id,
               pc.name_mn as color, ps.name as size,
               CONCAT(COALESCE(pc.name_mn,''), IF(pc.name_mn IS NOT NULL AND ps.name IS NOT NULL, ' / ', ''), COALESCE(ps.name,'')) as label
        FROM product_variants pv
        LEFT JOIN product_colors pc ON pc.id = pv.color_id
        LEFT JOIN product_sizes  ps ON ps.id = pv.size_id
        WHERE pv.product_id = ? AND pv.is_active = 1
        ORDER BY pc.sort_order, ps.sort_order
    ");
    $stmt->execute([$pid]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── Handle form submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']); exit;
    }

    $arrivalDateRaw = $data['arrival_date'] ?? '';
    $parsedDate = DateTime::createFromFormat('Y-m-d', $arrivalDateRaw);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $arrivalDateRaw) {
        echo json_encode(['error' => 'Огноо буруу форматтай байна (YYYY-MM-DD)']); exit;
    }
    $arrivalDate = $parsedDate->format('Y-m-d');
    $bId   = (int)($data['cargo_batch_id'] ?? 0) ?: null;
    $notes = mb_substr(trim($data['notes'] ?? ''), 0, 500);
    $items = $data['items'] ?? [];

    // Deduplicate and skip zero quantities
    $merged = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $vid = (int)($item['variant_id'] ?? 0) ?: null;
        $qty = (int)($item['quantity'] ?? 0);
        if (!$pid || $qty <= 0) continue;
        $key = $pid . '_' . ($vid ?? 'null');
        if (isset($merged[$key])) {
            $merged[$key]['qty'] += $qty;
        } else {
            $merged[$key] = ['pid' => $pid, 'vid' => $vid, 'qty' => $qty];
        }
    }

    if (empty($merged)) {
        echo json_encode(['error' => 'Ирсэн тоо ширхэг оруулна уу (бүгд тэг байна)']); exit;
    }

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO inventory_arrivals (cargo_batch_id, arrival_date, notes, created_by) VALUES (?,?,?,?)")
           ->execute([$bId, $arrivalDate, $notes ?: null, $currentAdmin['id'] ?? null]);
        $newArrivalId = (int)$db->lastInsertId();

        foreach ($merged as $row) {
            $db->prepare("INSERT INTO inventory_arrival_items (arrival_id, product_id, variant_id, quantity_received) VALUES (?,?,?,?)")
               ->execute([$newArrivalId, $row['pid'], $row['vid'], $row['qty']]);
        }

        $result = processArrivalFIFO($db, $newArrivalId);
        $db->commit();

        // Auto-SMS customers whose orders are now fully ready (outside transaction)
        $smsSent = 0;
        if (!empty($result['orders_now_ready'])) {
            $smsSent = sendArrivalSMSForOrders($result['orders_now_ready']);
        }

        echo json_encode([
            'success'               => true,
            'arrival_id'            => $newArrivalId,
            'order_items_fulfilled' => $result['order_items_fulfilled'],
            'orders_now_ready'      => count($result['orders_now_ready']),
            'sms_sent'              => $smsSent,
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Void an arrival ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'void') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']); exit;
    }
    $vid = (int)($data['arrival_id'] ?? 0);
    if (!$vid) { echo json_encode(['error' => 'ID олдсонгүй']); exit; }

    // Confirm it exists
    $check = $db->prepare("SELECT id FROM inventory_arrivals WHERE id = ?");
    $check->execute([$vid]);
    if (!$check->fetch()) { echo json_encode(['error' => 'Ирэлт олдсонгүй']); exit; }

    try {
        $voided = voidArrival($db, $vid);
        echo json_encode(['success' => true, 'affected_orders' => count($voided['affected_orders'])]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Batches available for arrival recording
$batches = $db->query("SELECT id, name, status FROM cargo_batches ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($arrival): ?>
<!-- ══════════════════════════════════════════════
     VIEW MODE
══════════════════════════════════════════════ -->
<div class="mb-6 flex items-center justify-between">
    <a href="index.php?page=inventory-arrivals" class="text-sm text-gray-500 hover:text-gray-700">← Ирэлтийн жагсаалт</a>
    <button onclick="voidArrival(<?= $arrivalId ?>)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        Буцаах
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-900"><?= date('Y-m-d', strtotime($arrival['arrival_date'])) ?></h2>
            <?php if ($arrival['notes']): ?>
                <p class="text-sm text-gray-500 mt-1"><?= e($arrival['notes']) ?></p>
            <?php endif; ?>
        </div>
        <?php
        $bn = null;
        if ($arrival['cargo_batch_id']) {
            $batchRow = $db->prepare("SELECT name FROM cargo_batches WHERE id = ?");
            $batchRow->execute([$arrival['cargo_batch_id']]);
            $bn = $batchRow->fetchColumn() ?: null;
        }
        if ($bn):
        ?>
            <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full"><?= e($bn) ?></span>
        <?php endif; ?>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-gray-500 text-xs uppercase tracking-wide">
                <th class="text-left pb-2">Бүтээгдэхүүн</th>
                <th class="text-left pb-2">Хувилбар</th>
                <th class="text-right pb-2">Ирсэн</th>
                <th class="text-right pb-2">Тохирсон</th>
                <th class="text-right pb-2">Тохироогүй</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($existingItems as $ei):
                $leftover = (int)$ei['quantity_received'] - (int)$ei['quantity_matched'];
                $parts = array_filter([$ei['color_name'], $ei['size_name']]);
            ?>
            <tr>
                <td class="py-2 font-medium text-gray-900"><?= e($ei['product_name']) ?></td>
                <td class="py-2 text-gray-500"><?= $parts ? e(implode(' / ', $parts)) : '—' ?></td>
                <td class="py-2 text-right font-mono"><?= (int)$ei['quantity_received'] ?></td>
                <td class="py-2 text-right font-mono text-green-600"><?= (int)$ei['quantity_matched'] ?></td>
                <td class="py-2 text-right font-mono <?= $leftover > 0 ? 'text-amber-600 font-semibold' : 'text-gray-300' ?>">
                    <?= $leftover ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$fulfilledOrders = $db->prepare("
    SELECT DISTINCT o.id, o.order_number, o.customer_name, o.status
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN inventory_arrival_items iai ON iai.id = oi.arrival_item_id
    WHERE iai.arrival_id = ?
    ORDER BY o.order_number
");
$fulfilledOrders->execute([$arrivalId]);
$fOrders = $fulfilledOrders->fetchAll();
if ($fOrders):
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-3">Биелэгдсэн захиалгууд (<?= count($fOrders) ?>)</h3>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($fOrders as $fo): ?>
            <a href="index.php?page=order-detail&id=<?= (int)$fo['id'] ?>"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-50 border border-green-200 text-green-800 rounded-lg text-xs hover:bg-green-100">
                <?= e($fo['order_number']) ?>
                <?php if ($fo['status'] === 'cargo_arrived'): ?>
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══════════════════════════════════════════════
     CREATE MODE
══════════════════════════════════════════════ -->
<div class="mb-6">
    <a href="index.php?page=inventory-arrivals" class="text-sm text-gray-500 hover:text-gray-700">← Ирэлтийн жагсаалт</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

<!-- ── Left column ── -->
<div class="lg:col-span-2 space-y-5">

    <!-- Header fields -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="font-semibold text-gray-900 mb-4">Ирэлтийн мэдээлэл</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ирсэн огноо</label>
                <input type="date" id="arrivalDate" value="<?= date('Y-m-d') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ачааны багц</label>
                <select id="cargoBatchId" onchange="onBatchChange()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none">
                    <option value="">— Сонгох —</option>
                    <?php
                    $statusLabels = [
                        'open'      => 'Нээлттэй',
                        'closed'    => 'Хаагдсан',
                        'shipping'  => 'Тээвэрлэж буй',
                        'receiving' => 'Хүлээн авч байна',
                        'arrived'   => 'Ирсэн',
                    ];
                    foreach ($batches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['id'] == $batchId ? 'selected' : '' ?>>
                            <?= e($b['name']) ?> — <?= $statusLabels[$b['status']] ?? $b['status'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Тэмдэглэл</label>
                <input type="text" id="arrivalNotes" placeholder="Жишээ: 2-р дугаар тээвэр..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none">
            </div>
        </div>
    </div>

    <!-- Batch items table (shown after batch is selected) -->
    <div id="batchItemsCard" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-900">Багцын бараанууд</h2>
                <p class="text-xs text-gray-400 mt-0.5">Ирсэн ширхэгийг оруулна уу. Ирээгүй зүйлийг 0 орхино уу.</p>
            </div>
            <span id="batchLoadingSpinner" class="hidden">
                <svg class="w-5 h-5 animate-spin text-orange-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
            </span>
        </div>

        <div id="batchItemsEmpty" class="hidden px-5 py-8 text-center text-gray-400 text-sm">
            Энэ багцад хүлээгдэж буй захиалга байхгүй байна.
        </div>

        <div id="batchItemsTableWrap" class="overflow-x-auto hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-100">
                        <th class="text-left px-5 py-3">Бүтээгдэхүүн</th>
                        <th class="text-left px-3 py-3">Хувилбар</th>
                        <th class="text-right px-3 py-3">Захиалга</th>
                        <th class="text-right px-3 py-3">Ирсэн</th>
                        <th class="text-right px-3 py-3">Үлдсэн</th>
                        <th class="text-right px-5 py-3 text-orange-600">Одоо ирсэн</th>
                    </tr>
                </thead>
                <tbody id="batchItemsTbody" class="divide-y divide-gray-50"></tbody>
            </table>
        </div>
    </div>

    <!-- Extra / manual rows -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-semibold text-gray-900">Нэмэлт бараа</h2>
                <p class="text-xs text-gray-400 mt-0.5">Багцад байхгүй бараа нэмэх бол энд нэмнэ үү.</p>
            </div>
            <button type="button" onclick="addExtraRow()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-100 text-orange-700 rounded-lg text-sm font-medium hover:bg-orange-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Нэмэх
            </button>
        </div>

        <div id="extraRowsContainer" class="space-y-3"></div>
        <div id="extraEmptyMsg" class="py-4 text-center text-gray-300 text-xs">
            Нэмэлт бараа байхгүй
        </div>
    </div>

</div>

<!-- ── Right column: summary + save ── -->
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-4">
        <h2 class="font-semibold text-gray-900 mb-4">Хадгалах</h2>

        <div class="space-y-2 text-sm text-gray-600 mb-5">
            <div class="flex justify-between">
                <span>Нэр төрөл:</span>
                <span id="summaryItems" class="font-mono font-bold text-gray-900">0</span>
            </div>
            <div class="flex justify-between">
                <span>Нийт ширхэг:</span>
                <span id="summaryUnits" class="font-mono font-bold text-gray-900">0</span>
            </div>
        </div>

        <button type="button" id="saveBtn" onclick="saveArrival()"
                class="w-full py-2.5 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            Хадгалах & Тохируулах
        </button>
        <p class="text-xs text-gray-400 mt-2 text-center">
            Хадгалсны дараа FIFO дарааллаар захиалгуудад автоматаар тохируулагдана.
        </p>
    </div>
</div>

</div><!-- /grid -->

<!-- Result modal -->
<div id="resultModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 text-center">
        <div id="resultIcon" class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center text-2xl"></div>
        <h3 id="resultTitle" class="text-xl font-bold text-gray-900 mb-1"></h3>
        <p id="resultDetail" class="text-gray-500 text-sm mb-5"></p>
        <div class="flex gap-3">
            <a href="index.php?page=inventory-arrivals" class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">
                Жагсаалт руу
            </a>
            <a id="resultViewLink" href="#" class="flex-1 py-2.5 bg-orange-500 text-white rounded-lg text-sm font-medium hover:bg-orange-600">
                Дэлгэрэнгүй
            </a>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= generateCSRFToken() ?>';
let extraRowCount = 0;
let searchTimers = {};

// ── Batch selector ──────────────────────────────
async function onBatchChange() {
    const batchId = document.getElementById('cargoBatchId').value;
    const card    = document.getElementById('batchItemsCard');
    const spinner = document.getElementById('batchLoadingSpinner');
    const empty   = document.getElementById('batchItemsEmpty');
    const wrap    = document.getElementById('batchItemsTableWrap');
    const tbody   = document.getElementById('batchItemsTbody');

    if (!batchId) {
        card.classList.add('hidden');
        tbody.innerHTML = '';
        updateSummary();
        return;
    }

    card.classList.remove('hidden');
    spinner.classList.remove('hidden');
    empty.classList.add('hidden');
    wrap.classList.add('hidden');
    tbody.innerHTML = '';

    try {
        const res  = await fetch(`index.php?page=inventory-arrival-form&action=batch_items&batch_id=${batchId}`);
        const data = await res.json();
        spinner.classList.add('hidden');

        if (!data.items || data.items.length === 0) {
            empty.classList.remove('hidden');
        } else {
            data.items.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'batch-item-row hover:bg-orange-50/30';
                tr.dataset.productId = item.product_id;
                tr.dataset.variantId = item.variant_id ?? '';

                const variant = item.color_name || item.size_name
                    ? [item.color_name, item.size_name].filter(Boolean).join(' / ')
                    : (item.variant_label || '—');

                const waiting = parseInt(item.still_waiting);

                tr.innerHTML = `
                    <td class="px-5 py-3 font-medium text-gray-900">${escHtml(item.product_name)}</td>
                    <td class="px-3 py-3 text-gray-500 text-xs">${escHtml(variant)}</td>
                    <td class="px-3 py-3 text-right text-gray-400 font-mono text-xs">${item.total_ordered}</td>
                    <td class="px-3 py-3 text-right text-green-600 font-mono text-xs">${item.total_arrived}</td>
                    <td class="px-3 py-3 text-right text-orange-600 font-mono text-xs font-semibold">${waiting}</td>
                    <td class="px-5 py-2 text-right">
                        <input type="number"
                               min="0" max="${waiting}" value="${waiting}"
                               class="batch-qty w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-right font-mono focus:ring-2 focus:ring-orange-400 outline-none"
                               oninput="updateSummary()">
                    </td>
                `;
                tbody.appendChild(tr);
            });
            wrap.classList.remove('hidden');
        }
    } catch (e) {
        spinner.classList.add('hidden');
        empty.textContent = 'Ачааллахад алдаа гарлаа';
        empty.classList.remove('hidden');
    }

    updateSummary();
}

// ── Extra (manual) rows ────────────────────────
function addExtraRow() {
    extraRowCount++;
    const container = document.getElementById('extraRowsContainer');
    document.getElementById('extraEmptyMsg').classList.add('hidden');

    const row = document.createElement('div');
    row.className = 'extra-row border border-gray-200 rounded-xl p-4 space-y-3 bg-gray-50';
    row.dataset.rowId = extraRowCount;
    row.innerHTML = `
        <div class="flex items-start gap-2">
            <div class="flex-1 relative">
                <input type="text" placeholder="Бүтээгдэхүүн хайх..." autocomplete="off"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none product-search"
                       oninput="searchProduct(this, ${extraRowCount})" onblur="hideDropdown(this, 300)">
                <input type="hidden" class="product-id">
                <div class="search-dropdown absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-48 overflow-y-auto"></div>
            </div>
            <button type="button" onclick="removeExtraRow(${extraRowCount})" class="mt-1 p-1.5 text-gray-400 hover:text-red-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Хувилбар (өнгө/хэмжээ)</label>
                <select class="variant-select w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none" disabled>
                    <option value="">— Эхлээд бүтээгдэхүүн сонгоно уу —</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Ирсэн ширхэг</label>
                <input type="number" min="1" value="1" class="extra-qty w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none" oninput="updateSummary()">
            </div>
        </div>
    `;
    container.appendChild(row);
    row.querySelector('.product-search').focus();
    updateSummary();
}

function removeExtraRow(rowId) {
    const row = document.querySelector(`.extra-row[data-row-id="${rowId}"]`);
    if (row) row.remove();
    if (!document.querySelectorAll('.extra-row').length) {
        document.getElementById('extraEmptyMsg').classList.remove('hidden');
    }
    updateSummary();
}

// ── Product search for extra rows ──────────────
async function searchProduct(input, rowId) {
    clearTimeout(searchTimers[rowId]);
    const q = input.value.trim();
    const row = input.closest('.extra-row');
    const dropdown = row.querySelector('.search-dropdown');
    if (q.length < 1) { dropdown.classList.add('hidden'); return; }

    searchTimers[rowId] = setTimeout(async () => {
        const res = await fetch(`index.php?page=inventory-arrival-form&action=search_products&q=${encodeURIComponent(q)}`);
        const products = await res.json();
        dropdown.innerHTML = '';
        if (!products.length) {
            dropdown.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">Олдсонгүй</div>';
        } else {
            products.forEach(p => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 text-sm hover:bg-orange-50 cursor-pointer flex items-center justify-between';
                item.innerHTML = `<span>${escHtml(p.name_mn)}</span>${p.has_variants ? '<span class="text-xs text-gray-400">Хувилбартай</span>' : ''}`;
                item.onmousedown = () => selectProduct(row, p);
                dropdown.appendChild(item);
            });
        }
        dropdown.classList.remove('hidden');
    }, 200);
}

function hideDropdown(input, delay) {
    setTimeout(() => {
        input.closest('.extra-row')?.querySelector('.search-dropdown')?.classList.add('hidden');
    }, delay);
}

async function selectProduct(row, product) {
    row.querySelector('.product-search').value = product.name_mn;
    row.querySelector('.product-id').value = product.id;
    row.querySelector('.search-dropdown').classList.add('hidden');

    const sel = row.querySelector('.variant-select');
    sel.innerHTML = '<option value="">Ачааллаж байна...</option>';
    sel.disabled = true;

    if (product.has_variants && product.variant_count > 0) {
        const res = await fetch(`index.php?page=inventory-arrival-form&action=get_variants&product_id=${product.id}`);
        const variants = await res.json();
        sel.innerHTML = '<option value="">— Сонгох —</option>';
        variants.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.label || '—';
            sel.appendChild(opt);
        });
        sel.disabled = false;
    } else {
        sel.innerHTML = '<option value="">Хувилбаргүй</option>';
        sel.disabled = true;
    }
}

// ── Summary ────────────────────────────────────
function updateSummary() {
    let itemCount = 0, unitCount = 0;

    document.querySelectorAll('.batch-item-row').forEach(row => {
        const qty = parseInt(row.querySelector('.batch-qty')?.value || 0);
        if (qty > 0) { itemCount++; unitCount += qty; }
    });
    document.querySelectorAll('.extra-row').forEach(row => {
        const qty = parseInt(row.querySelector('.extra-qty')?.value || 0);
        if (qty > 0) { itemCount++; unitCount += qty; }
    });

    document.getElementById('summaryItems').textContent = itemCount;
    document.getElementById('summaryUnits').textContent = unitCount;
}

// ── Save ───────────────────────────────────────
async function saveArrival() {
    const items = [];
    let hasError = false;

    // Collect from batch table
    document.querySelectorAll('.batch-item-row').forEach(row => {
        const pid = parseInt(row.dataset.productId || 0);
        const vid = parseInt(row.dataset.variantId || 0) || null;
        const qty = parseInt(row.querySelector('.batch-qty')?.value || 0);
        if (qty > 0) items.push({ product_id: pid, variant_id: vid, quantity: qty });
    });

    // Collect from extra rows
    document.querySelectorAll('.extra-row').forEach(row => {
        const pid = parseInt(row.querySelector('.product-id').value || 0);
        const sel = row.querySelector('.variant-select');
        const vid = sel && !sel.disabled ? parseInt(sel.value || 0) || null : null;
        const qty = parseInt(row.querySelector('.extra-qty').value || 0);

        if (!pid) { hasError = true; row.querySelector('.product-search').classList.add('border-red-500'); return; }
        if (qty > 0) items.push({ product_id: pid, variant_id: vid, quantity: qty });
    });

    if (hasError) { alert('Нэмэлт бараануудад бүтээгдэхүүн сонгоно уу.'); return; }
    if (!items.length) { alert('Ирсэн ширхэг оруулна уу (бүгд тэг байна).'); return; }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = 'Хадгалж байна...';

    try {
        const res = await fetch('index.php?page=inventory-arrival-form&action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token:     csrfToken,
                arrival_date:   document.getElementById('arrivalDate').value,
                cargo_batch_id: document.getElementById('cargoBatchId').value || null,
                notes:          document.getElementById('arrivalNotes').value,
                items,
            }),
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('resultIcon').className = 'w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center text-2xl bg-green-100';
            document.getElementById('resultIcon').textContent = '✅';
            document.getElementById('resultTitle').textContent = 'Ирэлт бүртгэгдлээ!';
            const smsPart = data.sms_sent > 0 ? ` ${data.sms_sent} мэдэгдэл SMS жагсаалтад нэмэгдлээ.` : '';
            document.getElementById('resultDetail').textContent =
                `${data.order_items_fulfilled} мөр тохируулагдлаа. ${data.orders_now_ready} захиалга бүрэн бэлэн боллоо.${smsPart}`;
            document.getElementById('resultViewLink').href = `index.php?page=inventory-arrival-form&id=${data.arrival_id}`;
            btn.disabled = true;
            btn.textContent = 'Хадгалагдсан';
        } else {
            document.getElementById('resultIcon').className = 'w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center text-2xl bg-red-100';
            document.getElementById('resultIcon').textContent = '❌';
            document.getElementById('resultTitle').textContent = 'Алдаа гарлаа';
            document.getElementById('resultDetail').textContent = data.error || 'Дахин оролдоно уу.';
            btn.disabled = false;
            btn.textContent = 'Хадгалах & Тохируулах';
        }
        document.getElementById('resultModal').classList.remove('hidden');
    } catch (e) {
        alert('Серверийн алдаа: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Хадгалах & Тохируулах';
    }
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Auto-load batch items if pre-selected from URL
<?php if ($batchId): ?>
window.addEventListener('DOMContentLoaded', () => onBatchChange());
<?php endif; ?>
</script>

<?php endif; // end if ($arrival) / else create mode ?>

<?php if ($arrival): ?>
<script>
const csrfTokenView = '<?= generateCSRFToken() ?>';
async function voidArrival(arrivalId) {
    if (!confirm('Энэ ирэлтийг буцаах уу?\n\nТохируулагдсан захиалгуудын бараа статус "тээвэрлэж буй" руу буцна. Энэ үйлдлийг буцаах боломжгүй.')) return;

    const btn = event.currentTarget;
    btn.disabled = true;
    btn.textContent = 'Буцааж байна...';

    try {
        const res = await fetch('index.php?page=inventory-arrival-form&action=void', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfTokenView, arrival_id: arrivalId }),
        });
        const data = await res.json();
        if (data.success) {
            alert(`Ирэлт буцаагдлаа. ${data.affected_orders} захиалгын статус шинэчлэгдлээ.`);
            window.location.href = 'index.php?page=inventory-arrivals';
        } else {
            alert('Алдаа: ' + (data.error || 'Дахин оролдоно уу.'));
            btn.disabled = false;
            btn.textContent = 'Буцаах';
        }
    } catch (e) {
        alert('Серверийн алдаа: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Буцаах';
    }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
