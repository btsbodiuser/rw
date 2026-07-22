<?php
$pageTitle = 'Үйл ажиллагааны төрөл';
$db = getDB();

$items = $db->query("SELECT * FROM activity_types ORDER BY sort_order, name_mn")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500"><?= count($items) ?> төрөл</p>
    <a href="index.php?page=activity-type-form"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Нэмэх
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дүрс</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Монгол нэр</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">English</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Slug</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дараалал</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Идэвхтэй</th>
                <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php if (empty($items)): ?>
                <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">Төрөл байхгүй байна</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-xl"><?= $item['icon'] ?? '' ?></td>
                <td class="px-5 py-3 font-medium text-gray-900"><?= e($item['name_mn']) ?></td>
                <td class="px-5 py-3 text-sm text-gray-500"><?= e($item['name']) ?></td>
                <td class="px-5 py-3 text-xs text-gray-400 font-mono"><?= e($item['slug']) ?></td>
                <td class="px-5 py-3 text-center text-sm text-gray-600"><?= $item['sort_order'] ?></td>
                <td class="px-5 py-3 text-center">
                    <span class="w-2.5 h-2.5 rounded-full inline-block <?= $item['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="index.php?page=activity-type-form&id=<?= $item['id'] ?>"
                           class="p-1.5 text-gray-400 hover:text-blue-600 transition-colors" title="Засах">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <form method="POST" action="index.php?page=activity-type-delete" class="inline"
                              onsubmit="return confirm('«<?= e(addslashes($item['name_mn'])) ?>» төрлийг устгах уу?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Устгах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
