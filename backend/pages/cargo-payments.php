<?php
$pageTitle = 'Ачааны төлбөр';
$db = getDB();

$search        = trim($_GET['search'] ?? '');
$statusFilter  = $_GET['status'] ?? '';
$methodFilter  = $_GET['method'] ?? '';
$page          = max(1, (int)($_GET['pg'] ?? 1));
$perPage       = 30;
$offset        = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter !== '') {
    $where[]  = "cp.status = ?";
    $params[] = $statusFilter;
}
if ($methodFilter !== '') {
    $where[]  = "cp.payment_method = ?";
    $params[] = $methodFilter;
}

$whereStr = implode(' AND ', $where);

$total = $db->prepare("
    SELECT COUNT(*) FROM cargo_payments cp
    JOIN orders o ON o.id = cp.order_id
    WHERE $whereStr
");
$total->execute($params);
$totalRows  = (int)$total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $db->prepare("
    SELECT cp.id, cp.payment_method, cp.invoice_id, cp.amount, cp.status, cp.paid_at, cp.created_at,
           o.id AS order_id, o.order_number, o.customer_name, o.customer_phone, o.status AS order_status
    FROM cargo_payments cp
    JOIN orders o ON o.id = cp.order_id
    WHERE $whereStr
    ORDER BY cp.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Totals for summary
$summaryStmt = $db->prepare("
    SELECT
        SUM(CASE WHEN cp.status = 'paid'    THEN cp.amount ELSE 0 END) AS paid_total,
        SUM(CASE WHEN cp.status = 'pending' THEN cp.amount ELSE 0 END) AS pending_total,
        COUNT(CASE WHEN cp.status = 'paid'    THEN 1 END) AS paid_count,
        COUNT(CASE WHEN cp.status = 'pending' THEN 1 END) AS pending_count
    FROM cargo_payments cp
    JOIN orders o ON o.id = cp.order_id
    WHERE $whereStr
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$methodLabels = ['qpay' => 'QPay', 'bonum' => 'Bonum', 'storepay' => 'StorePay'];
$orderStatusLabels = [
    'pending' => 'Хүлээгдэж байна', 'confirmed' => 'Баталгаажсан',
    'cargo_shipping' => 'Тээвэрт гарсан', 'cargo_arrived' => 'Монголд ирсэн',
    'ready_pickup' => 'Авахад бэлэн', 'delivering' => 'Хүргэлтэнд',
    'delivered' => 'Хүргэгдсэн', 'picked_up' => 'Авсан',
    'completed' => 'Дууссан', 'cancelled' => 'Цуцлагдсан',
];

function buildUrl(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    return 'index.php?' . http_build_query($params);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-5">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">💳 Ачааны төлбөр</h1>
        <span class="text-sm text-gray-500">Нийт <?= number_format($totalRows) ?> бүртгэл</span>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-xs text-green-600 font-medium mb-1">Төлсөн</p>
            <p class="text-2xl font-bold text-green-800"><?= formatPrice((float)($summary['paid_total'] ?? 0)) ?></p>
            <p class="text-xs text-green-600 mt-0.5"><?= (int)($summary['paid_count'] ?? 0) ?> гүйлгээ</p>
        </div>
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <p class="text-xs text-yellow-600 font-medium mb-1">Хүлээгдэж байна</p>
            <p class="text-2xl font-bold text-yellow-800"><?= formatPrice((float)($summary['pending_total'] ?? 0)) ?></p>
            <p class="text-xs text-yellow-600 mt-0.5"><?= (int)($summary['pending_count'] ?? 0) ?> гүйлгээ</p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="index.php" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <input type="hidden" name="page" value="cargo-payments">
        <div class="flex flex-wrap gap-3">
            <input type="text" name="search" value="<?= e($search) ?>"
                placeholder="Захиалгын дугаар, нэр, утас..."
                class="flex-1 min-w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх статус</option>
                <option value="paid"    <?= $statusFilter === 'paid'    ? 'selected' : '' ?>>Төлсөн</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Хүлээгдэж байна</option>
                <option value="failed"  <?= $statusFilter === 'failed'  ? 'selected' : '' ?>>Амжилтгүй</option>
            </select>
            <select name="method" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх арга</option>
                <option value="qpay"     <?= $methodFilter === 'qpay'     ? 'selected' : '' ?>>QPay</option>
                <option value="bonum"    <?= $methodFilter === 'bonum'    ? 'selected' : '' ?>>Bonum</option>
                <option value="storepay" <?= $methodFilter === 'storepay' ? 'selected' : '' ?>>StorePay</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Хайх
            </button>
            <?php if ($search || $statusFilter || $methodFilter): ?>
            <a href="index.php?page=cargo-payments" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200">
                Цэвэрлэх
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <?php if (empty($rows)): ?>
        <div class="py-16 text-center text-gray-400">
            <p class="text-4xl mb-3">📭</p>
            <p class="font-medium">Бүртгэл олдсонгүй</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Захиалга</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Хэрэглэгч</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Арга</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600">Дүн</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Статус</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Огноо</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Төлсөн огноо</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($rows as $row):
                        [$badgeCls, $badgeLabel] = match($row['status']) {
                            'paid'    => ['bg-green-100 text-green-700',  'Төлсөн'],
                            'pending' => ['bg-yellow-100 text-yellow-700', 'Хүлээгдэж байна'],
                            'failed'  => ['bg-red-100 text-red-700',      'Амжилтгүй'],
                            default   => ['bg-gray-100 text-gray-600',    $row['status']],
                        };
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <a href="index.php?page=order-detail&id=<?= $row['order_id'] ?>"
                               class="font-mono text-blue-600 hover:text-blue-800 font-medium text-xs">
                                <?= e($row['order_number']) ?>
                            </a>
                            <p class="text-xs text-gray-400 mt-0.5"><?= e($orderStatusLabels[$row['order_status']] ?? $row['order_status']) ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900"><?= e($row['customer_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($row['customer_phone']) ?></p>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-700">
                            <?= $methodLabels[$row['payment_method']] ?? e($row['payment_method']) ?>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900">
                            <?= formatPrice((float)$row['amount']) ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeCls ?>">
                                <?= $badgeLabel ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                            <?= date('Y/m/d H:i', strtotime($row['created_at'])) ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                            <?= $row['paid_at'] ? date('Y/m/d H:i', strtotime($row['paid_at'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
            <p class="text-xs text-gray-500">
                <?= ($offset + 1) ?>–<?= min($offset + $perPage, $totalRows) ?> / <?= number_format($totalRows) ?>
            </p>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= buildUrl(['pg' => $page - 1]) ?>" class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">← Өмнөх</a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?= buildUrl(['pg' => $i]) ?>"
                       class="px-3 py-1.5 text-xs border rounded-lg <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= buildUrl(['pg' => $page + 1]) ?>" class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">Дараах →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
