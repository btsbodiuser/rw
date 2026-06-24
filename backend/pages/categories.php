<?php
$pageTitle = 'Ангилал';
$db = getDB();

$categories = $db->query("SELECT c.*, 
    (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count,
    (SELECT COUNT(*) FROM shop_categories WHERE category_id = c.id) as shop_count
    FROM categories c ORDER BY c.sort_order")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500"><?= count($categories) ?> ангилал</p>
    <a href="index.php?page=category-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Ангилал нэмэх
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Ангилал</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Slug</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Бүтээгдэхүүн</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дэлгүүр</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дараалал</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Төлөв</th>
                <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($categories as $c): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl"><?= e($c['icon']) ?></span>
                        <div>
                            <p class="font-medium text-gray-900"><?= e($c['name']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($c['name_mn']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3 text-sm text-gray-500 font-mono"><?= e($c['slug']) ?></td>
                <td class="px-5 py-3 text-sm text-center text-gray-600"><?= $c['product_count'] ?></td>
                <td class="px-5 py-3 text-sm text-center text-gray-600"><?= $c['shop_count'] ?></td>
                <td class="px-5 py-3 text-sm text-center text-gray-600"><?= $c['sort_order'] ?></td>
                <td class="px-5 py-3 text-center">
                    <span class="w-2.5 h-2.5 rounded-full inline-block <?= $c['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="index.php?page=category-form&id=<?= $c['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Засах">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <?php if (!hasRole('pos_cashier')): ?>
                        <a href="index.php?page=category-delete&id=<?= $c['id'] ?>&token=<?= generateCSRFToken() ?>" class="p-1.5 text-gray-400 hover:text-red-600"
                           onclick="return confirm('Энэ ангилалыг устгах уу?')" title="Устгах">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
