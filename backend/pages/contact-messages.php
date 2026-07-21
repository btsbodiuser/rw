<?php
$pageTitle = 'Холбоо барих мессеж';
$db = getDB();

// ── Inline actions: mark read, mark spam, restore, delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=contact-messages');
        exit;
    }
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && in_array($action, ['read', 'spam', 'new', 'delete'], true)) {
        if ($action === 'delete') {
            $db->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
            setFlash('success', 'Мессеж устгагдлаа.');
        } else {
            $db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")->execute([$action, $id]);
            setFlash('success', 'Төлөв шинэчлэгдлээ.');
        }
    }
    header('Location: index.php?page=contact-messages' . (!empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''));
    exit;
}

// ── List ──
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['pg'] ?? 1));

$where = ["1=1"];
$params = [];
if (in_array($statusFilter, ['new', 'read', 'spam'], true)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM contact_messages WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmt = $db->prepare("SELECT * FROM contact_messages WHERE $whereStr ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$messages = $stmt->fetchAll();

$counts = $db->query("SELECT status, COUNT(*) c FROM contact_messages GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$filterUrl = 'index.php?page=contact-messages';
if ($statusFilter) $filterUrl .= '&status=' . urlencode($statusFilter);

require_once __DIR__ . '/../includes/header.php';

$statusLabels = ['new' => 'Шинэ', 'read' => 'Уншсан', 'spam' => 'Спам'];
$statusColors = ['new' => 'bg-blue-100 text-blue-700', 'read' => 'bg-gray-100 text-gray-600', 'spam' => 'bg-red-100 text-red-700'];
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500"><?= (int)$total ?> мессеж</p>
</div>

<div class="flex gap-2 mb-4">
    <a href="index.php?page=contact-messages" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $statusFilter === '' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        Бүгд
    </a>
    <?php foreach (['new', 'read', 'spam'] as $st): ?>
    <a href="index.php?page=contact-messages&status=<?= $st ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $statusFilter === $st ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        <?= $statusLabels[$st] ?> (<?= (int)($counts[$st] ?? 0) ?>)
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($messages)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <p>Одоогоор мессеж байхгүй байна.</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Илгээгч</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Мессеж</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Төлөв</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Огноо</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($messages as $m): ?>
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-5 py-3">
                        <p class="text-sm font-medium text-gray-900"><?= e($m['name']) ?></p>
                        <p class="text-xs text-gray-500"><?= e($m['email']) ?></p>
                        <?php if ($m['phone']): ?>
                        <p class="text-xs text-gray-500"><?= e($m['phone']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 max-w-md">
                        <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= e($m['message']) ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?= e($m['ip_address'] ?? '') ?></p>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$m['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= $statusLabels[$m['status']] ?? $m['status'] ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-500 whitespace-nowrap">
                        <?= date('Y-m-d H:i', strtotime($m['created_at'])) ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-1 flex-wrap">
                            <?php if ($m['status'] !== 'read'): ?>
                            <form method="post" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="action" value="read">
                                <button type="submit" class="px-2 py-1 text-xs text-blue-600 hover:bg-blue-50 rounded">Уншсан</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($m['status'] !== 'spam'): ?>
                            <form method="post" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="action" value="spam">
                                <button type="submit" class="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded">Спам</button>
                            </form>
                            <?php else: ?>
                            <form method="post" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="action" value="new">
                                <button type="submit" class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 rounded">Сэргээх</button>
                            </form>
                            <?php endif; ?>
                            <?php if (hasRole('super_admin', 'admin')): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Энэ мессежийг устгах уу?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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
