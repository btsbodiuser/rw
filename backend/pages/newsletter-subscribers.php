<?php
$pageTitle = 'Мэдээллийн захидлын бүртгэл';
$db = getDB();

// ── Inline actions: deactivate, reactivate, delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=newsletter-subscribers');
        exit;
    }
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && in_array($action, ['deactivate', 'reactivate', 'delete'], true)) {
        if ($action === 'delete') {
            $db->prepare("DELETE FROM newsletter_subscribers WHERE id = ?")->execute([$id]);
            setFlash('success', 'Бүртгэл устгагдлаа.');
        } elseif ($action === 'deactivate') {
            $db->prepare("UPDATE newsletter_subscribers SET is_active = 0, unsubscribed_at = NOW() WHERE id = ?")->execute([$id]);
            setFlash('success', 'Идэвхгүй болголоо.');
        } elseif ($action === 'reactivate') {
            $db->prepare("UPDATE newsletter_subscribers SET is_active = 1, unsubscribed_at = NULL WHERE id = ?")->execute([$id]);
            setFlash('success', 'Дахин идэвхжүүллээ.');
        }
    }
    header('Location: index.php?page=newsletter-subscribers' . (!empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''));
    exit;
}

// ── Export CSV ──
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query("SELECT email, is_active, subscribed_at, unsubscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
    fputcsv($out, ['Email', 'Status', 'Subscribed', 'Unsubscribed']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['email'],
            (int)$r['is_active'] === 1 ? 'Active' : 'Inactive',
            $r['subscribed_at'],
            $r['unsubscribed_at'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── List ──
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['pg'] ?? 1));

$where  = ["1=1"];
$params = [];
if ($statusFilter === 'active') {
    $where[] = "is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $where[] = "is_active = 0";
}
if ($search !== '') {
    $where[] = "email LIKE ?";
    $params[] = '%' . $search . '%';
}
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 30, $page);

$stmt = $db->prepare("SELECT * FROM newsletter_subscribers WHERE $whereStr ORDER BY subscribed_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$subscribers = $stmt->fetchAll();

$counts = [
    'active'   => (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1")->fetchColumn(),
    'inactive' => (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 0")->fetchColumn(),
];

$filterUrl = 'index.php?page=newsletter-subscribers';
if ($statusFilter) $filterUrl .= '&status=' . urlencode($statusFilter);
if ($search !== '') $filterUrl .= '&q=' . urlencode($search);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <p class="text-sm text-gray-500"><?= (int)$total ?> бүртгэл</p>
    <div class="flex items-center gap-2">
        <form method="get" class="flex items-center gap-2">
            <input type="hidden" name="page" value="newsletter-subscribers">
            <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Имэйл хайх..." class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:border-gray-400">
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-gray-900 text-white text-sm font-medium">Хайх</button>
        </form>
        <a href="index.php?page=newsletter-subscribers&export=csv" class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">CSV татах</a>
    </div>
</div>

<div class="flex gap-2 mb-4">
    <a href="index.php?page=newsletter-subscribers" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $statusFilter === '' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        Бүгд
    </a>
    <a href="index.php?page=newsletter-subscribers&status=active" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $statusFilter === 'active' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        Идэвхтэй (<?= $counts['active'] ?>)
    </a>
    <a href="index.php?page=newsletter-subscribers&status=inactive" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $statusFilter === 'inactive' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        Идэвхгүй (<?= $counts['inactive'] ?>)
    </a>
</div>

<?php if (empty($subscribers)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <p>Одоогоор бүртгэл байхгүй байна.</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Имэйл</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Төлөв</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Бүртгүүлсэн</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">IP</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($subscribers as $s): ?>
                <tr class="hover:bg-gray-50 align-middle">
                    <td class="px-5 py-3">
                        <p class="text-sm font-medium text-gray-900"><?= e($s['email']) ?></p>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?php if ((int)$s['is_active'] === 1): ?>
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Идэвхтэй</span>
                        <?php else: ?>
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Идэвхгүй</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-500 whitespace-nowrap">
                        <?= date('Y-m-d H:i', strtotime($s['subscribed_at'])) ?>
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                        <?= e($s['ip_address'] ?? '') ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-1 flex-wrap">
                            <?php if ((int)$s['is_active'] === 1): ?>
                            <form method="post" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 rounded">Идэвхгүй</button>
                            </form>
                            <?php else: ?>
                            <form method="post" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="action" value="reactivate">
                                <button type="submit" class="px-2 py-1 text-xs text-green-600 hover:bg-green-50 rounded">Сэргээх</button>
                            </form>
                            <?php endif; ?>
                            <?php if (hasRole('super_admin', 'admin')): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Энэ бүртгэлийг устгах уу?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="px-2 py-1 text-xs text-gray-400 hover:text-red-600 hover:bg-red-50 rounded">Устгах</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($pagination, $filterUrl); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
