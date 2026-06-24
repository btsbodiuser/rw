<?php
$pageTitle = 'Хэрэглэгч';
$db = getDB();

// Filters
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['pg'] ?? 1));
$perPage = in_array((int)($_GET['per_page'] ?? 20), [20, 50, 100]) ? (int)($_GET['per_page'] ?? 20) : 20;
$showDuplicates = !empty($_GET['duplicates']);

// Sorting
$allowedSorts = [
    'name' => 'c.name',
    'phone' => 'c.phone',
    'order_count' => 'order_count',
    'total_spent' => 'total_spent',
    'address_count' => 'address_count',
    'created_at' => 'c.created_at',
];
$sortBy = $_GET['sort'] ?? 'created_at';
$sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
if (!isset($allowedSorts[$sortBy])) $sortBy = 'created_at';
$orderClause = $allowedSorts[$sortBy] . ' ' . $sortDir;

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(c.phone LIKE ? OR c.name LIKE ? OR c.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($showDuplicates) {
    $where[] = "c.phone IN (SELECT phone FROM customers GROUP BY phone HAVING COUNT(*) > 1)";
}

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM customers c WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$stmt = $db->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) as order_count,
        (SELECT COALESCE(SUM(total), 0) FROM orders WHERE customer_id = c.id AND status != 'cancelled') as total_spent,
        (SELECT COUNT(*) FROM customer_addresses WHERE customer_id = c.id) as address_count
    FROM customers c
    WHERE $whereStr
    ORDER BY $orderClause
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Detect duplicate phones in current result set for highlighting
$phoneCounts = [];
foreach ($customers as $c) {
    $phoneCounts[$c['phone']] = ($phoneCounts[$c['phone']] ?? 0) + 1;
}
// Also check DB-wide duplicates
$allPhones = array_unique(array_column($customers, 'phone'));
if (!empty($allPhones)) {
    $phPlaceholders = implode(',', array_fill(0, count($allPhones), '?'));
    $dupStmt = $db->prepare("SELECT phone, COUNT(*) as cnt FROM customers WHERE phone IN ($phPlaceholders) GROUP BY phone HAVING COUNT(*) > 1");
    $dupStmt->execute(array_values($allPhones));
    foreach ($dupStmt->fetchAll() as $d) {
        $phoneCounts[$d['phone']] = (int)$d['cnt'];
    }
}

// Total duplicate count for badge
$dupCountStmt = $db->query("SELECT COUNT(*) FROM (SELECT phone FROM customers GROUP BY phone HAVING COUNT(*) > 1) t");
$duplicatePhoneCount = (int)$dupCountStmt->fetchColumn();

$filterUrl = 'index.php?page=customers';
if ($search) $filterUrl .= '&search=' . urlencode($search);
if ($showDuplicates) $filterUrl .= '&duplicates=1';
if ($perPage !== 20) $filterUrl .= '&per_page=' . $perPage;
if ($sortBy !== 'created_at' || $sortDir !== 'desc') {
    $filterUrl .= '&sort=' . urlencode($sortBy) . '&dir=' . urlencode($sortDir);
}

// Helper to build sort URL
function sortUrl($col) {
    global $sortBy, $sortDir, $search, $showDuplicates, $perPage;
    $dir = ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc';
    $url = 'index.php?page=customers&sort=' . $col . '&dir=' . $dir;
    if ($search) $url .= '&search=' . urlencode($search);
    if ($showDuplicates) $url .= '&duplicates=1';
    if ($perPage !== 20) $url .= '&per_page=' . $perPage;
    return $url;
}
function sortIcon($col) {
    global $sortBy, $sortDir;
    if ($sortBy !== $col) return '<svg class="w-3 h-3 text-gray-300 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>';
    $icon = $sortDir === 'asc'
        ? '<svg class="w-3 h-3 text-blue-600 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>'
        : '<svg class="w-3 h-3 text-blue-600 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
    return $icon;
}

// Function to determine registration method
function getRegistrationMethod($customer) {
    if (!empty($customer['google_id'])) {
        return ['Google', 'bg-red-100 text-red-800', '🔵'];
    }
    if (!empty($customer['facebook_id'])) {
        return ['Facebook', 'bg-blue-100 text-blue-800', '📘'];
    }
    if (!empty($customer['email'])) {
        return ['Имэйл', 'bg-green-100 text-green-800', '📧'];
    }
    return ['Утас', 'bg-gray-100 text-gray-800', '📱'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
    <div class="flex items-center gap-3">
        <p class="text-sm text-gray-500"><?= $total ?> хэрэглэгч</p>
        <div class="flex items-center gap-1 text-xs text-gray-400">
            |
            <?php foreach ([20, 50, 100] as $pp): ?>
                <?php
                    $ppUrl = 'index.php?page=customers';
                    if ($search) $ppUrl .= '&search=' . urlencode($search);
                    if ($showDuplicates) $ppUrl .= '&duplicates=1';
                    if ($pp !== 20) $ppUrl .= '&per_page=' . $pp;
                    if ($sortBy !== 'created_at' || $sortDir !== 'desc') $ppUrl .= '&sort=' . urlencode($sortBy) . '&dir=' . urlencode($sortDir);
                ?>
                <?php if ($perPage === $pp): ?>
                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-medium"><?= $pp ?></span>
                <?php else: ?>
                    <a href="<?= $ppUrl ?>" class="px-2 py-0.5 hover:bg-gray-100 rounded"><?= $pp ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <a href="index.php?page=customer-form" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Хэрэглэгч нэмэх
    </a>
</div>

<!-- Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="hidden" name="page" value="customers">
        <?php if ($sortBy !== 'created_at' || $sortDir !== 'desc'): ?>
            <input type="hidden" name="sort" value="<?= e($sortBy) ?>">
            <input type="hidden" name="dir" value="<?= e($sortDir) ?>">
        <?php endif; ?>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Нэр, утас, имэйлээр хайх..."
               class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Хайх</button>
        <?php if ($duplicatePhoneCount > 0): ?>
            <a href="index.php?page=customers<?= $showDuplicates ? '' : '&duplicates=1' ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $showDuplicates ? 'bg-red-100 text-red-700 ring-2 ring-red-300' : 'border border-red-200 text-red-600 hover:bg-red-50' ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Давхардсан утас
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold <?= $showDuplicates ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700' ?>"><?= $duplicatePhoneCount ?></span>
            </a>
        <?php endif; ?>
        <a href="index.php?page=customers" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Цэвэрлэх</a>
    </form>
</div>

<!-- Customers Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">
                        <a href="<?= sortUrl('name') ?>" class="hover:text-gray-900">Хэрэглэгч <?= sortIcon('name') ?></a>
                    </th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Имэйл</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">
                        <a href="<?= sortUrl('phone') ?>" class="hover:text-gray-900">Утас <?= sortIcon('phone') ?></a>
                    </th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Бүртгүүлсэн арга</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">
                        <a href="<?= sortUrl('order_count') ?>" class="hover:text-gray-900">Захиалга <?= sortIcon('order_count') ?></a>
                    </th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">
                        <a href="<?= sortUrl('total_spent') ?>" class="hover:text-gray-900">Нийт зарцуулсан <?= sortIcon('total_spent') ?></a>
                    </th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">
                        <a href="<?= sortUrl('address_count') ?>" class="hover:text-gray-900">Хаяг <?= sortIcon('address_count') ?></a>
                    </th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">
                        <a href="<?= sortUrl('created_at') ?>" class="hover:text-gray-900">Бүртгүүлсэн огноо <?= sortIcon('created_at') ?></a>
                    </th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="9" class="px-5 py-12 text-center text-gray-400">Хэрэглэгч олдсонгүй</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <?php $regMethod = getRegistrationMethod($c); ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span class="text-blue-700 font-bold text-sm"><?= strtoupper(mb_substr($c['name'] ?: $c['phone'], 0, 1)) ?></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 text-sm"><?= e($c['name'] ?: '—') ?></p>
                                        <p class="text-xs text-gray-500">ID: <?= $c['id'] ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm">
                                <?php if (!empty($c['email'])): ?>
                                    <span class="text-gray-700"><?= e($c['email']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-sm">
                                <?php $isDup = ($phoneCounts[$c['phone']] ?? 1) > 1; ?>
                                <span class="<?= $isDup ? 'text-red-700 font-medium' : 'text-gray-700' ?>"><?= e($c['phone']) ?></span>
                                <?php if ($isDup): ?>
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-600" title="Давхардсан утас">×<?= $phoneCounts[$c['phone']] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium <?= $regMethod[1] ?>">
                                    <span><?= $regMethod[2] ?></span>
                                    <?= e($regMethod[0]) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if ($c['order_count'] > 0): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?= $c['order_count'] ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-right text-sm font-medium text-gray-900"><?= number_format($c['total_spent']) ?>₮</td>
                            <td class="px-5 py-3 text-center text-sm text-gray-700"><?= $c['address_count'] ?></td>
                            <td class="px-5 py-3 text-right text-sm text-gray-500"><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="index.php?page=customer-form&id=<?= $c['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                                        Засах
                                    </a>
                                    <a href="index.php?page=customer-detail&id=<?= $c['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors">
                                        Дэлгэрэнгүй
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPagination($pagination, $filterUrl); ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
