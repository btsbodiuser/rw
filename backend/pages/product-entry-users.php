<?php
/**
 * Admin Page: Manage Product Entry Permissions
 * Grant/revoke product creation rights to customers
 */
$pageTitle = 'Бараа оруулагч';
$db = getDB();

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'grant') {
        $phone = trim($_POST['phone'] ?? '');
        if ($phone) {
            $custStmt = $db->prepare("SELECT id, name, phone FROM customers WHERE phone = ?");
            $custStmt->execute([$phone]);
            $cust = $custStmt->fetch();
            
            if (!$cust) {
                setFlash('error', 'Хэрэглэгч олдсонгүй: ' . htmlspecialchars($phone));
            } else {
                // Check if already granted
                $existsStmt = $db->prepare("SELECT 1 FROM product_entry_permissions WHERE customer_id = ?");
                $existsStmt->execute([$cust['id']]);
                if ($existsStmt->fetchColumn()) {
                    setFlash('warning', 'Энэ хэрэглэгч аль хэдийн эрхтэй байна');
                } else {
                    $insertStmt = $db->prepare("INSERT INTO product_entry_permissions (customer_id, granted_by) VALUES (?, ?)");
                    $insertStmt->execute([$cust['id'], $currentAdmin['id']]);
                    auditLog('permission_granted', 'product_entry_permission', $cust['id'], 'admin', $currentAdmin['id'], [
                        'customer_phone' => $cust['phone'],
                        'customer_name' => $cust['name'],
                    ]);
                    setFlash('success', $cust['phone'] . ' хэрэглэгчид бараа оруулах эрх өгсөн');
                }
            }
        }
    }
    
    if ($action === 'revoke') {
        $permId = (int) ($_POST['permission_id'] ?? 0);
        if ($permId) {
            // Get customer info for audit log before deleting
            $infoStmt = $db->prepare("
                SELECT p.customer_id, c.phone, c.name 
                FROM product_entry_permissions p 
                JOIN customers c ON c.id = p.customer_id 
                WHERE p.id = ?
            ");
            $infoStmt->execute([$permId]);
            $info = $infoStmt->fetch();
            
            $delStmt = $db->prepare("DELETE FROM product_entry_permissions WHERE id = ?");
            $delStmt->execute([$permId]);
            
            if ($delStmt->rowCount() > 0 && $info) {
                auditLog('permission_revoked', 'product_entry_permission', $info['customer_id'], 'admin', $currentAdmin['id'], [
                    'customer_phone' => $info['phone'],
                    'customer_name' => $info['name'],
                ]);
                setFlash('success', 'Эрх хассан');
            }
        }
    }
    
    header('Location: index.php?page=product-entry-users');
    exit;
}

// ── Fetch permitted users ──
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['pg'] ?? 1));

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(c.phone LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM product_entry_permissions p JOIN customers c ON c.id = p.customer_id WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmt = $db->prepare("
    SELECT p.id as perm_id, p.created_at as granted_at, p.granted_by,
           c.id as customer_id, c.phone, c.name,
           a.name as granted_by_name,
           (SELECT COUNT(*) FROM products WHERE created_by_customer_id = c.id) as product_count
    FROM product_entry_permissions p
    JOIN customers c ON c.id = p.customer_id
    LEFT JOIN admins a ON a.id = p.granted_by
    WHERE $whereStr
    ORDER BY p.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$entries = $stmt->fetchAll();

$filterUrl = 'index.php?page=product-entry-users';
if ($search) $filterUrl .= '&search=' . urlencode($search);

require_once __DIR__ . '/../includes/header.php';
?>

<?= renderFlash() ?>

<div class="flex flex-col sm:flex-row justify-between gap-4 mb-6">
    <p class="text-sm text-gray-500"><?= $total ?> эрхтэй хэрэглэгч</p>
</div>

<!-- Grant Permission Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Бараа оруулах эрх өгөх</h3>
    <form method="POST" class="flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="grant">
        <input type="text" name="phone" placeholder="Утасны дугаар (8 оронтой)" 
               pattern="[0-9]{8}" maxlength="8" required
               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
            Эрх өгөх
        </button>
    </form>
</div>

<!-- Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex gap-3">
        <input type="hidden" name="page" value="product-entry-users">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Утас, нэрээр хайх..."
               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Хайх</button>
        <a href="index.php?page=product-entry-users" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Цэвэрлэх</a>
    </form>
</div>

<!-- Permissions Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Хэрэглэгч</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Утас</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Оруулсан бараа</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Эрх өгсөн</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Огноо</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">Эрхтэй хэрэглэгч олдсонгүй</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-green-100 rounded-full flex items-center justify-center">
                                        <span class="text-green-700 font-bold text-sm"><?= strtoupper(mb_substr($entry['name'] ?: $entry['phone'], 0, 1)) ?></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 text-sm"><?= e($entry['name'] ?: '—') ?></p>
                                        <p class="text-xs text-gray-500">ID: <?= $entry['customer_id'] ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700"><?= e($entry['phone']) ?></td>
                            <td class="px-5 py-3 text-center">
                                <?php if ($entry['product_count'] > 0): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><?= $entry['product_count'] ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500"><?= e($entry['granted_by_name'] ?: '—') ?></td>
                            <td class="px-5 py-3 text-right text-sm text-gray-500"><?= date('Y-m-d', strtotime($entry['granted_at'])) ?></td>
                            <td class="px-5 py-3 text-center">
                                <form method="POST" class="inline" onsubmit="return confirm('Энэ хэрэглэгчийн эрхийг хасах уу?')">
                                    <input type="hidden" name="_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="permission_id" value="<?= $entry['perm_id'] ?>">
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">
                                        Эрх хасах
                                    </button>
                                </form>
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
