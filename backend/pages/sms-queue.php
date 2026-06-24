<?php
$pageTitle = 'SMS жагсаалт';
$db = getDB();

// ── Check MessagePro config ──
$cfgRows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('messagepro_api_key','messagepro_from_number')")->fetchAll();
$apiKey = ''; $fromNumber = '';
foreach ($cfgRows as $r) {
    if ($r['setting_key'] === 'messagepro_api_key')     $apiKey     = $r['setting_value'];
    if ($r['setting_key'] === 'messagepro_from_number') $fromNumber = $r['setting_value'];
}
$smsConfigured = $apiKey && $fromNumber;

// ── AJAX: send one queued message ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'send_one') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'CSRF']); exit;
    }
    if (!$smsConfigured) {
        echo json_encode(['error' => 'MessagePro тохируулагдаагүй байна']); exit;
    }
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID олдсонгүй']); exit; }
    echo json_encode(sendQueuedSMS($id));
    exit;
}

// ── AJAX: send multiple queued messages ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'send_bulk') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'CSRF']); exit;
    }
    if (!$smsConfigured) {
        echo json_encode(['error' => 'MessagePro тохируулагдаагүй байна']); exit;
    }
    $ids = array_map('intval', $data['ids'] ?? []);
    if (empty($ids)) { echo json_encode(['error' => 'Сонгогдоогүй байна']); exit; }

    $sent = 0; $failed = 0; $errors = [];
    foreach ($ids as $id) {
        $result = sendQueuedSMS($id);
        if ($result['success']) { $sent++; } else { $failed++; $errors[] = $result['error']; }
    }
    echo json_encode(['success' => true, 'sent' => $sent, 'failed' => $failed, 'errors' => $errors]);
    exit;
}

// ── AJAX: delete pending messages ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'CSRF']); exit;
    }
    $ids = array_map('intval', $data['ids'] ?? []);
    if (empty($ids)) { echo json_encode(['error' => 'Сонгогдоогүй байна']); exit; }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("DELETE FROM sms_queue WHERE id IN ($ph) AND status = 'pending'")->execute($ids);
    echo json_encode(['success' => true]);
    exit;
}

// ── Stats ──
$todaySent = (int)$db->query("SELECT COUNT(*) FROM sms_queue WHERE status='sent' AND DATE(sent_at)=CURDATE()")->fetchColumn();
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM sms_queue WHERE status='pending'")->fetchColumn();
$failedCount  = (int)$db->query("SELECT COUNT(*) FROM sms_queue WHERE status='failed'")->fetchColumn();

// ── Load messages ──
$tab = $_GET['tab'] ?? 'pending';
$allowedTabs = ['pending', 'sent', 'failed'];
if (!in_array($tab, $allowedTabs)) $tab = 'pending';

$messages = $db->prepare("
    SELECT q.*, o.order_number
    FROM sms_queue q
    LEFT JOIN orders o ON o.id = q.order_id
    WHERE q.status = ?
    ORDER BY q.created_at DESC
    LIMIT 500
");
$messages->execute([$tab]);
$messages = $messages->fetchAll();

$typeLabels = [
    'cargo_arrived' => 'Ачаа ирсэн',
    'batch_notify'  => 'Багц мэдэгдэл',
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <p class="text-sm text-gray-500 mt-0.5">
            Өнөөдөр илгээсэн: <strong class="<?= $todaySent >= 450 ? 'text-red-600' : ($todaySent >= 350 ? 'text-orange-600' : 'text-gray-900') ?>"><?= $todaySent ?> / 500</strong>
        </p>
    </div>
    <?php if (!$smsConfigured): ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-800 rounded-lg text-sm">
            ⚠️ MessagePro тохируулагдаагүй — зөвхөн log хадгалах горим
        </span>
    <?php else: ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-800 rounded-lg text-sm">
            ✅ MessagePro идэвхтэй
        </span>
    <?php endif; ?>
</div>

<!-- Daily limit bar -->
<?php $limitPct = min(100, round($todaySent / 500 * 100)); ?>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6">
    <div class="flex justify-between text-xs text-gray-500 mb-1.5">
        <span>Өдрийн хязгаар (500)</span>
        <span><?= $todaySent ?> ашигласан · <?= 500 - $todaySent ?> үлдсэн</span>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
        <div class="h-full rounded-full transition-all <?= $limitPct >= 90 ? 'bg-red-500' : ($limitPct >= 70 ? 'bg-orange-400' : 'bg-blue-500') ?>"
             style="width: <?= $limitPct ?>%"></div>
    </div>
</div>

<!-- Tabs -->
<div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit mb-5">
    <a href="?page=sms-queue&tab=pending"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $tab === 'pending' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' ?>">
        Хүлээгдэж буй
        <?php if ($pendingCount > 0): ?>
            <span class="ml-1 px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs"><?= $pendingCount ?></span>
        <?php endif; ?>
    </a>
    <a href="?page=sms-queue&tab=sent"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $tab === 'sent' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' ?>">
        Илгээгдсэн
    </a>
    <a href="?page=sms-queue&tab=failed"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $tab === 'failed' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' ?>">
        Алдаатай
        <?php if ($failedCount > 0): ?>
            <span class="ml-1 px-1.5 py-0.5 bg-red-100 text-red-700 rounded-full text-xs"><?= $failedCount ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if (empty($messages)): ?>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    <?= $tab === 'pending' ? 'Хүлээгдэж буй мэдэгдэл байхгүй байна.' : ($tab === 'failed' ? 'Алдаатай мэдэгдэл байхгүй.' : 'Илгээгдсэн мэдэгдэл байхгүй.') ?>
</div>
<?php else: ?>

<!-- Bulk action toolbar -->
<?php if ($tab === 'pending'): ?>
<div id="bulkBar" class="hidden mb-4 flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3">
    <span class="text-sm text-blue-800"><span id="selectedCount">0</span> сонгогдсон</span>
    <div class="flex-1"></div>
    <?php if ($smsConfigured): ?>
        <button onclick="sendSelected()" class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
            Сонгосонийг илгээх
        </button>
    <?php endif; ?>
    <button onclick="deleteSelected()" class="px-4 py-1.5 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200">
        Устгах
    </button>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500">
                <?php if ($tab === 'pending'): ?>
                <th class="px-4 py-3 w-8">
                    <input type="checkbox" id="selectAll" onchange="toggleAll(this)" class="rounded">
                </th>
                <?php endif; ?>
                <th class="text-left px-4 py-3">Утас</th>
                <th class="text-left px-4 py-3">Мэдэгдэл</th>
                <th class="text-left px-3 py-3">Төрөл</th>
                <th class="text-left px-3 py-3">Захиалга</th>
                <th class="text-left px-3 py-3">Огноо</th>
                <?php if ($tab === 'failed'): ?>
                <th class="text-left px-3 py-3">Алдаа</th>
                <?php endif; ?>
                <th class="px-4 py-3 w-24"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50" id="msgTable">
            <?php foreach ($messages as $msg): ?>
            <tr class="hover:bg-gray-50/50 msg-row" data-id="<?= $msg['id'] ?>">
                <?php if ($tab === 'pending'): ?>
                <td class="px-4 py-3">
                    <input type="checkbox" class="msg-checkbox rounded" value="<?= $msg['id'] ?>" onchange="updateBulkBar()">
                </td>
                <?php endif; ?>
                <td class="px-4 py-3 font-mono font-medium text-gray-900"><?= e($msg['phone']) ?></td>
                <td class="px-4 py-3 text-gray-600 max-w-xs">
                    <span class="line-clamp-2 text-xs"><?= e($msg['message']) ?></span>
                </td>
                <td class="px-3 py-3">
                    <?php if ($msg['type']): ?>
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">
                            <?= e($typeLabels[$msg['type']] ?? $msg['type']) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-3 py-3">
                    <?php if ($msg['order_number']): ?>
                        <a href="index.php?page=order-detail&id=<?= (int)$msg['order_id'] ?>"
                           class="text-blue-600 hover:underline text-xs"><?= e($msg['order_number']) ?></a>
                    <?php else: ?>
                        <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-3 py-3 text-gray-400 text-xs whitespace-nowrap">
                    <?php if ($tab === 'sent' && $msg['sent_at']): ?>
                        <?= date('m/d H:i', strtotime($msg['sent_at'])) ?>
                    <?php else: ?>
                        <?= date('m/d H:i', strtotime($msg['created_at'])) ?>
                    <?php endif; ?>
                </td>
                <?php if ($tab === 'failed'): ?>
                <td class="px-3 py-3 text-red-500 text-xs max-w-[140px]">
                    <span class="line-clamp-2"><?= e($msg['error_text'] ?? '') ?></span>
                </td>
                <?php endif; ?>
                <td class="px-4 py-3 text-right">
                    <?php if ($tab === 'pending' && $smsConfigured): ?>
                        <button onclick="sendOne(<?= $msg['id'] ?>, this)"
                                class="px-3 py-1 bg-blue-500 text-white rounded-lg text-xs hover:bg-blue-600">
                            Илгээх
                        </button>
                    <?php elseif ($tab === 'failed' && $smsConfigured): ?>
                        <button onclick="retryOne(<?= $msg['id'] ?>, this)"
                                class="px-3 py-1 bg-orange-500 text-white rounded-lg text-xs hover:bg-orange-600">
                            Дахин
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<script>
const csrfToken = '<?= generateCSRFToken() ?>';

function toggleAll(cb) {
    document.querySelectorAll('.msg-checkbox').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.msg-checkbox:checked').length;
    const bar = document.getElementById('bulkBar');
    if (checked > 0) {
        bar?.classList.remove('hidden');
        const cnt = document.getElementById('selectedCount');
        if (cnt) cnt.textContent = checked;
    } else {
        bar?.classList.add('hidden');
    }
    const all = document.getElementById('selectAll');
    const total = document.querySelectorAll('.msg-checkbox').length;
    if (all) all.indeterminate = checked > 0 && checked < total;
}

function getSelectedIds() {
    return [...document.querySelectorAll('.msg-checkbox:checked')].map(c => parseInt(c.value));
}

async function sendOne(id, btn) {
    btn.disabled = true; btn.textContent = '...';
    const res = await fetch('index.php?page=sms-queue&action=send_one', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: csrfToken, id }),
    });
    const data = await res.json();
    if (data.success) {
        btn.closest('tr').remove();
        updateCountBadge();
    } else {
        btn.disabled = false; btn.textContent = 'Илгээх';
        alert('Алдаа: ' + (data.error || 'Дахин оролдоно уу'));
    }
}

async function retryOne(id, btn) {
    btn.disabled = true; btn.textContent = '...';
    const res = await fetch('index.php?page=sms-queue&action=send_one', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: csrfToken, id }),
    });
    const data = await res.json();
    if (data.success) {
        btn.closest('tr').remove();
    } else {
        btn.disabled = false; btn.textContent = 'Дахин';
        alert('Алдаа: ' + (data.error || ''));
    }
}

async function sendSelected() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    if (!confirm(`${ids.length} мэдэгдэл илгээх үү?`)) return;

    const btn = event.currentTarget;
    btn.disabled = true; btn.textContent = 'Илгээж байна...';

    const res = await fetch('index.php?page=sms-queue&action=send_bulk', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: csrfToken, ids }),
    });
    const data = await res.json();
    if (data.success) {
        alert(`${data.sent} амжилттай илгээгдлээ.${data.failed > 0 ? ` ${data.failed} алдаатай.` : ''}`);
        window.location.reload();
    } else {
        alert('Алдаа: ' + (data.error || ''));
        btn.disabled = false; btn.textContent = 'Сонгосонийг илгээх';
    }
}

async function deleteSelected() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    if (!confirm(`${ids.length} мэдэгдэл устгах уу?`)) return;

    const res = await fetch('index.php?page=sms-queue&action=delete', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: csrfToken, ids }),
    });
    const data = await res.json();
    if (data.success) {
        ids.forEach(id => document.querySelector(`.msg-row[data-id="${id}"]`)?.remove());
        updateBulkBar();
    }
}

function updateCountBadge() {
    const remaining = document.querySelectorAll('.msg-row').length;
    if (remaining === 0) document.getElementById('msgTable')?.closest('table')
        ?.closest('.bg-white')?.replaceWith(Object.assign(document.createElement('div'), {
            className: 'bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400',
            textContent: 'Хүлээгдэж буй мэдэгдэл байхгүй байна.'
        }));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
