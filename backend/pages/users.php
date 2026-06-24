<?php
/**
 * Users Management — Super Admin only
 */
requireRole('super_admin');

$pageTitle = 'Хэрэглэгчид';
$db = getDB();

$users = $db->query("SELECT id, username, name, role, created_at FROM admins ORDER BY id")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Хэрэглэгчид</h2>
        <p class="text-sm text-gray-500 mt-1">Систем хэрэглэгч удирдах</p>
    </div>
    <a href="index.php?page=user-form" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
        + Шинэ хэрэглэгч
    </a>
</div>

<!-- Role Legend -->
<div class="flex gap-3 mb-4">
    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-purple-100 text-purple-700">Супер админ</span>
    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-blue-100 text-blue-700">Админ</span>
    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-green-100 text-green-700">Кассчин</span>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <th class="px-4 py-3">Нэр</th>
                <th class="px-4 py-3">Нэвтрэх нэр</th>
                <th class="px-4 py-3">Эрх</th>
                <th class="px-4 py-3">Үүсгэсэн</th>
                <th class="px-4 py-3 text-right">Үйлдэл</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($users as $u): ?>
            <?php
                $roleBadges = [
                    'super_admin' => 'bg-purple-100 text-purple-700',
                    'admin' => 'bg-blue-100 text-blue-700',
                    'pos_cashier' => 'bg-green-100 text-green-700',
                ];
                $badge = $roleBadges[$u['role']] ?? 'bg-gray-100 text-gray-700';
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900"><?= e($u['name']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= e($u['username']) ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $badge ?>">
                        <?= e($roleLabels[$u['role']] ?? $u['role']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-500"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                <td class="px-4 py-3 text-right">
                    <a href="index.php?page=user-form&id=<?= $u['id'] ?>" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Засах</a>
                    <?php if ($u['id'] !== $currentAdmin['id']): ?>
                    <a href="index.php?page=user-delete&id=<?= $u['id'] ?>&token=<?= e($_SESSION['csrf_token'] ?? '') ?>" 
                       onclick="return confirm('Энэ хэрэглэгчийг устгах уу?')"
                       class="text-red-500 hover:text-red-700 text-xs font-medium ml-3">Устгах</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
