<?php
$pageTitle = 'Аудит лог';
$db = getDB();

// Only super_admin can view audit logs
requireRole('super_admin');

// Filters
$actionFilter = $_GET['action'] ?? '';
$entityFilter = $_GET['entity'] ?? '';
$actorFilter = $_GET['actor'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['pg'] ?? 1));

$where = ["1=1"];
$params = [];

if ($actionFilter) {
    $where[] = "al.action = ?";
    $params[] = $actionFilter;
}
if ($entityFilter) {
    $where[] = "al.entity_type = ?";
    $params[] = $entityFilter;
}
if ($actorFilter) {
    $where[] = "al.actor_type = ?";
    $params[] = $actorFilter;
}
if ($search) {
    $where[] = "(al.action LIKE ? OR al.details LIKE ? OR al.ip_address LIKE ? OR CAST(al.entity_id AS CHAR) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dateFrom) {
    $where[] = "al.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "al.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereStr = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log al WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 30, $page);

// Fetch logs with actor names
$stmt = $db->prepare("
    SELECT al.*,
        CASE 
            WHEN al.actor_type = 'admin' THEN (SELECT name FROM admins WHERE id = al.actor_id)
            WHEN al.actor_type = 'customer' THEN (SELECT COALESCE(name, phone) FROM customers WHERE id = al.actor_id)
            ELSE NULL
        END as actor_name
    FROM audit_log al
    WHERE $whereStr
    ORDER BY al.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get distinct values for filter dropdowns
$actions = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entityTypes = $db->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

// Build filter URL for pagination
$filterUrl = 'index.php?page=audit-log';
if ($actionFilter) $filterUrl .= '&action=' . urlencode($actionFilter);
if ($entityFilter) $filterUrl .= '&entity=' . urlencode($entityFilter);
if ($actorFilter) $filterUrl .= '&actor=' . urlencode($actorFilter);
if ($search) $filterUrl .= '&search=' . urlencode($search);
if ($dateFrom) $filterUrl .= '&date_from=' . urlencode($dateFrom);
if ($dateTo) $filterUrl .= '&date_to=' . urlencode($dateTo);

// Action labels for human-readable display
$actionLabels = [
    'order_created' => ['label' => 'Захиалга үүсгэсэн', 'color' => 'bg-blue-100 text-blue-700', 'icon' => '🛒'],
    'create' => ['label' => 'Үүсгэсэн', 'color' => 'bg-blue-100 text-blue-700', 'icon' => '➕'],
    'update' => ['label' => 'Засварласан', 'color' => 'bg-sky-100 text-sky-700', 'icon' => '✏️'],
    'edit' => ['label' => 'Засварласан', 'color' => 'bg-sky-100 text-sky-700', 'icon' => '✏️'],
    'cancel' => ['label' => 'Цуцалсан', 'color' => 'bg-red-100 text-red-700', 'icon' => '❌'],
    'order_auto_cancelled' => ['label' => 'Автомат цуцлагдсан', 'color' => 'bg-red-100 text-red-700', 'icon' => '⏰'],
    'order_auto_confirmed' => ['label' => 'Автомат баталгаажсан', 'color' => 'bg-green-100 text-green-700', 'icon' => '✅'],
    'order_status_changed' => ['label' => 'Төлөв өөрчилсөн', 'color' => 'bg-orange-100 text-orange-700', 'icon' => '🔄'],
    'order_payment_changed' => ['label' => 'Төлбөр өөрчилсөн', 'color' => 'bg-emerald-100 text-emerald-700', 'icon' => '💰'],
    'order_cargo_fee_updated' => ['label' => 'Ачааны төлбөр', 'color' => 'bg-amber-100 text-amber-700', 'icon' => '📋'],
    'order_fulfillment_changed' => ['label' => 'Хүргэлт өөрчилсөн', 'color' => 'bg-teal-100 text-teal-700', 'icon' => '📍'],
    'payment_callback' => ['label' => 'Төлбөр хариу', 'color' => 'bg-yellow-100 text-yellow-700', 'icon' => '💳'],
    'delivery_assigned' => ['label' => 'Хүргэлт оноосон', 'color' => 'bg-purple-100 text-purple-700', 'icon' => '🚚'],
    'delivery_batch_created' => ['label' => 'Хүргэлт багцалсан', 'color' => 'bg-purple-100 text-purple-700', 'icon' => '📦'],
    'delivery_unassigned' => ['label' => 'Хүргэлт цуцалсан', 'color' => 'bg-rose-100 text-rose-700', 'icon' => '🚫'],
    'product_import' => ['label' => 'Бараа импортолсон', 'color' => 'bg-indigo-100 text-indigo-700', 'icon' => '📥'],
    'product_created' => ['label' => 'Бараа үүсгэсэн', 'color' => 'bg-indigo-100 text-indigo-700', 'icon' => '🆕'],
    'product_updated' => ['label' => 'Бараа засварласан', 'color' => 'bg-indigo-100 text-indigo-700', 'icon' => '✏️'],
    'customer_created' => ['label' => 'Хэрэглэгч үүсгэсэн', 'color' => 'bg-cyan-100 text-cyan-700', 'icon' => '👤'],
    'customer_updated' => ['label' => 'Хэрэглэгч засварласан', 'color' => 'bg-cyan-100 text-cyan-700', 'icon' => '✏️'],
    'permission_granted' => ['label' => 'Эрх олгосон', 'color' => 'bg-green-100 text-green-700', 'icon' => '🔓'],
    'permission_revoked' => ['label' => 'Эрх хассан', 'color' => 'bg-red-100 text-red-700', 'icon' => '🔒'],
    'sms_send' => ['label' => 'SMS илгээсэн', 'color' => 'bg-pink-100 text-pink-700', 'icon' => '📱'],
    'sync' => ['label' => 'Синк хийсэн', 'color' => 'bg-sky-100 text-sky-700', 'icon' => '🔁'],
];

$actorTypeLabels = [
    'system' => ['label' => 'Систем', 'color' => 'bg-gray-100 text-gray-600'],
    'admin' => ['label' => 'Админ', 'color' => 'bg-violet-100 text-violet-700'],
    'customer' => ['label' => 'Хэрэглэгч', 'color' => 'bg-cyan-100 text-cyan-700'],
];

$entityTypeLabels = [
    'order' => 'Захиалга',
    'product' => 'Бүтээгдэхүүн',
    'products' => 'Бүтээгдэхүүн',
    'customer' => 'Хэрэглэгч',
    'category' => 'Ангилал',
    'shop' => 'Брэнд',
    'delivery' => 'Хүргэлт',
    'delivery_batch' => 'Хүргэлтийн багц',
    'cargo_batch' => 'Ачааны багц',
    'cargo_fee' => 'Ачааны төлбөр',
    'product_names' => 'Барааны нэр',
    'product_entry_permission' => 'Бараа оруулах эрх',
    'cancel_expired' => 'Хугацаа дууссан цуцлалт',
    'setting' => 'Тохиргоо',
    'settings' => 'Тохиргоо',
    'admin' => 'Админ',
    'media' => 'Медиа',
];

// Detail key translations (JSON keys → Mongolian)
$detailKeyLabels = [
    'order_number' => 'Захиалгын дугаар',
    'old_status' => 'Хуучин төлөв',
    'new_status' => 'Шинэ төлөв',
    'old_payment_status' => 'Хуучин төлбөр',
    'new_payment_status' => 'Шинэ төлбөр',
    'old_fulfillment' => 'Хуучин хүргэлт',
    'new_fulfillment' => 'Шинэ хүргэлт',
    'item_id' => 'Барааны ID',
    'cargo_fee_paid' => 'Ачааны төлбөр төлсөн',
    'district_id' => 'Дүүрэг ID',
    'address' => 'Хаяг',
    'reason' => 'Шалтгаан',
    'driver_id' => 'Жолоочийн ID',
    'driver_name' => 'Жолоочийн нэр',
    'order_count' => 'Захиалгын тоо',
    'order_id' => 'Захиалгын ID',
    'batch_id' => 'Багцын ID',
    'phone' => 'Утас',
    'name' => 'Нэр',
    'customer_name' => 'Хэрэглэгчийн нэр',
    'customer_phone' => 'Хэрэглэгчийн утас',
    'total' => 'Нийт дүн',
    'payment_method' => 'Төлбөрийн арга',
    'count' => 'Тоо',
    'updated' => 'Шинэчилсэн',
    'message' => 'Мессеж',
    'recipients' => 'Хүлээн авагч',
    'cancelled' => 'Цуцлагдсан',
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <p class="text-sm text-gray-500">Нийт <?= $total ?> бүртгэл</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="space-y-3">
        <input type="hidden" name="page" value="audit-log">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Хайх (IP, үйлдэл, дэлгэрэнгүй)..."
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <select name="action" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх үйлдэл</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>>
                        <?= isset($actionLabels[$a]) ? $actionLabels[$a]['label'] : e($a) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="entity" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх обьект</option>
                <?php foreach ($entityTypes as $et): ?>
                    <option value="<?= e($et) ?>" <?= $entityFilter === $et ? 'selected' : '' ?>>
                        <?= $entityTypeLabels[$et] ?? e($et) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="actor" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх гүйцэтгэгч</option>
                <option value="system" <?= $actorFilter === 'system' ? 'selected' : '' ?>>Систем</option>
                <option value="admin" <?= $actorFilter === 'admin' ? 'selected' : '' ?>>Админ</option>
                <option value="customer" <?= $actorFilter === 'customer' ? 'selected' : '' ?>>Хэрэглэгч</option>
            </select>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500 whitespace-nowrap">Эхлэх:</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500 whitespace-nowrap">Дуусах:</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="col-span-2 flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Хайх</button>
                <a href="index.php?page=audit-log" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 flex items-center">Цэвэрлэх</a>
            </div>
        </div>
    </form>
</div>

<!-- Audit Log Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Огноо</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Обьект</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Гүйцэтгэгч</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">IP хаяг</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дэлгэрэнгүй</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">Аудит лог олдсонгүй</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): 
                    $actionInfo = $actionLabels[$log['action']] ?? ['label' => $log['action'], 'color' => 'bg-gray-100 text-gray-600', 'icon' => '📝'];
                    $actorInfo = $actorTypeLabels[$log['actor_type']] ?? ['label' => $log['actor_type'], 'color' => 'bg-gray-100 text-gray-600'];
                    $details = $log['details'] ? json_decode($log['details'], true) : null;
                    if (!is_array($details)) $details = null;
                ?>
                <tr class="hover:bg-gray-50 group">
                    <!-- Date -->
                    <td class="px-5 py-3 whitespace-nowrap">
                        <p class="text-sm text-gray-900"><?= date('Y-m-d', strtotime($log['created_at'])) ?></p>
                        <p class="text-xs text-gray-400"><?= date('H:i:s', strtotime($log['created_at'])) ?></p>
                    </td>
                    <!-- Action -->
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-base"><?= $actionInfo['icon'] ?></span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $actionInfo['color'] ?>">
                                <?= e($actionInfo['label']) ?>
                            </span>
                        </div>
                    </td>
                    <!-- Entity -->
                    <td class="px-5 py-3">
                        <p class="text-sm text-gray-900"><?= e($entityTypeLabels[$log['entity_type']] ?? $log['entity_type']) ?></p>
                        <?php if ($log['entity_id']): ?>
                            <p class="text-xs text-gray-400">ID: <?= e($log['entity_id']) ?></p>
                        <?php endif; ?>
                    </td>
                    <!-- Actor -->
                    <td class="px-5 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $actorInfo['color'] ?>">
                            <?= e($actorInfo['label']) ?>
                        </span>
                        <?php if ($log['actor_name']): ?>
                            <p class="text-xs text-gray-600 mt-1"><?= e($log['actor_name']) ?></p>
                        <?php elseif ($log['actor_id']): ?>
                            <p class="text-xs text-gray-400 mt-1">ID: <?= e($log['actor_id']) ?></p>
                        <?php endif; ?>
                    </td>
                    <!-- IP Address -->
                    <td class="px-5 py-3">
                        <code class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded"><?= e($log['ip_address'] ?? '-') ?></code>
                    </td>
                    <!-- Details -->
                    <td class="px-5 py-3">
                        <?php if ($details): ?>
                            <div x-data="{ open: false }">
                                <button @click="open = !open" class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    Дэлгэрэнгүй харах
                                </button>
                                <div x-show="open" x-cloak x-transition class="mt-2 max-w-xs">
                                    <div class="bg-gray-50 rounded-lg p-3 text-xs space-y-1">
                                        <?php foreach ($details as $key => $value): ?>
                                            <div class="flex gap-2">
                                                <span class="text-gray-400 font-medium whitespace-nowrap"><?= e($detailKeyLabels[$key] ?? $key) ?>:</span>
                                                <span class="text-gray-700 break-all"><?= e(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPagination($pagination, $filterUrl); ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
