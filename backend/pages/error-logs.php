<?php
$pageTitle = 'Алдааны лог';
$db = getDB();

requireRole('super_admin');

require_once __DIR__ . '/../includes/error-logger.php';

// Manual cleanup action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'cleanup') {
    $deleted = cleanupErrorLogs(30);
    header('Location: index.php?page=error-logs&cleaned=' . $deleted);
    exit;
}

// Filters
$levelFilter    = $_GET['level'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$search         = $_GET['search'] ?? '';
$dateFrom       = $_GET['date_from'] ?? '';
$dateTo         = $_GET['date_to'] ?? '';
$page           = max(1, (int)($_GET['pg'] ?? 1));

$where = ["1=1"];
$params = [];

if ($levelFilter) {
    $where[] = "el.level = ?";
    $params[] = $levelFilter;
}
if ($categoryFilter) {
    $where[] = "el.category = ?";
    $params[] = $categoryFilter;
}
if ($search) {
    $where[] = "(el.message LIKE ? OR el.context_data LIKE ? OR el.source_file LIKE ? OR el.ip_address LIKE ?)";
    for ($i = 0; $i < 4; $i++) $params[] = "%$search%";
}
if ($dateFrom) {
    $where[] = "el.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "el.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereStr = implode(' AND ', $where);

// 24h stats cards
$stats = $db->query("
    SELECT level, COUNT(*) AS cnt
    FROM error_logs
    WHERE created_at > NOW() - INTERVAL 24 HOUR
    GROUP BY level
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Count + fetch
$countStmt = $db->prepare("SELECT COUNT(*) FROM error_logs el WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 30, $page);

$stmt = $db->prepare("
    SELECT el.* FROM error_logs el
    WHERE $whereStr
    ORDER BY el.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$categories = $db->query("SELECT DISTINCT category FROM error_logs ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$filterUrl = 'index.php?page=error-logs';
if ($levelFilter)    $filterUrl .= '&level=' . urlencode($levelFilter);
if ($categoryFilter) $filterUrl .= '&category=' . urlencode($categoryFilter);
if ($search)         $filterUrl .= '&search=' . urlencode($search);
if ($dateFrom)       $filterUrl .= '&date_from=' . urlencode($dateFrom);
if ($dateTo)         $filterUrl .= '&date_to=' . urlencode($dateTo);

$levelLabels = [
    'critical' => ['label' => 'Ноцтой',      'color' => 'bg-red-100 text-red-700',       'icon' => '🔴'],
    'error'    => ['label' => 'Алдаа',       'color' => 'bg-orange-100 text-orange-700', 'icon' => '🟠'],
    'warning'  => ['label' => 'Анхааруулга', 'color' => 'bg-yellow-100 text-yellow-700', 'icon' => '🟡'],
    'info'     => ['label' => 'Мэдээлэл',    'color' => 'bg-blue-100 text-blue-700',     'icon' => '🔵'],
];

$categoryLabels = [
    'payment' => 'Төлбөр',
    'webhook' => 'Вебхүүк',
    'order'   => 'Захиалга',
    'auth'    => 'Нэвтрэлт',
    'system'  => 'Систем',
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <p class="text-sm text-gray-500">Нийт <?= $total ?> бүртгэл</p>
    <form method="POST" onsubmit="return confirm('30 хоногоос хуучин логуудыг устгах уу?')">
        <input type="hidden" name="do" value="cleanup">
        <button type="submit" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">🧹 Хуучин лог цэвэрлэх (30+ хоног)</button>
    </form>
</div>

<?php if (isset($_GET['cleaned'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm mb-4">
        <?= (int)$_GET['cleaned'] ?> хуучин лог устгагдлаа.
    </div>
<?php endif; ?>

<!-- 24h stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php foreach (['critical', 'error', 'warning', 'info'] as $lvl): $info = $levelLabels[$lvl]; ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase"><?= $info['icon'] ?> <?= e($info['label']) ?> (24ц)</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int)($stats[$lvl] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="space-y-3">
        <input type="hidden" name="page" value="error-logs">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Хайх (мессеж, файл, IP)..."
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <select name="level" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх түвшин</option>
                <?php foreach ($levelLabels as $lvl => $info): ?>
                    <option value="<?= $lvl ?>" <?= $levelFilter === $lvl ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх ангилал</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c) ?>" <?= $categoryFilter === $c ? 'selected' : '' ?>><?= e($categoryLabels[$c] ?? $c) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Хайх</button>
                <a href="index.php?page=error-logs" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 flex items-center">Цэвэрлэх</a>
            </div>
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
        </div>
    </form>
</div>

<!-- Error Log Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Огноо</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Түвшин</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Ангилал</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Мессеж</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Эх файл</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дэлгэрэнгүй</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">Алдааны лог олдсонгүй 🎉</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log):
                    $lvlInfo = $levelLabels[$log['level']] ?? ['label' => $log['level'], 'color' => 'bg-gray-100 text-gray-600', 'icon' => '⚪'];
                    $context = $log['context_data'] ? json_decode($log['context_data'], true) : null;
                    if (!is_array($context) || !$context) $context = null;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 whitespace-nowrap">
                        <p class="text-sm text-gray-900"><?= date('Y-m-d', strtotime($log['created_at'])) ?></p>
                        <p class="text-xs text-gray-400"><?= date('H:i:s', strtotime($log['created_at'])) ?></p>
                    </td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $lvlInfo['color'] ?>">
                            <?= $lvlInfo['icon'] ?> <?= e($lvlInfo['label']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-sm text-gray-700"><?= e($categoryLabels[$log['category']] ?? $log['category']) ?></p>
                    </td>
                    <td class="px-5 py-3 max-w-md">
                        <p class="text-sm text-gray-900 break-words"><?= e($log['message']) ?></p>
                        <?php if ($log['ip_address']): ?>
                            <code class="text-xs text-gray-400"><?= e($log['ip_address']) ?></code>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <code class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded"><?= e($log['source_file'] ? basename(str_replace('\\', '/', $log['source_file'])) : '-') ?></code>
                    </td>
                    <td class="px-5 py-3">
                        <?php if ($context): ?>
                            <div x-data="{ open: false }">
                                <button @click="open = !open" class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    Дэлгэрэнгүй харах
                                </button>
                                <div x-show="open" x-cloak x-transition class="mt-2 max-w-xs">
                                    <div class="bg-gray-50 rounded-lg p-3 text-xs space-y-1">
                                        <?php foreach ($context as $key => $value): ?>
                                            <div class="flex gap-2">
                                                <span class="text-gray-400 font-medium whitespace-nowrap"><?= e($key) ?>:</span>
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
