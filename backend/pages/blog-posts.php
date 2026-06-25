<?php
$pageTitle = 'Блог / Мэдээ';
$db = getDB();

$posts = $db->query("SELECT id, title_mn, title, slug, image, is_published, published_at, sort_order, created_at
                     FROM blog_posts ORDER BY sort_order ASC, created_at DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500"><?= count($posts) ?> нийтлэл</p>
    <a href="index.php?page=blog-post-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Нийтлэл нэмэх
    </a>
</div>

<?php if (empty($posts)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <p>Одоогоор нийтлэл байхгүй байна.</p>
        <a href="index.php?page=blog-post-form" class="mt-3 inline-block text-blue-600 hover:underline text-sm">Эхний нийтлэл нэмэх</a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Зураг</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Гарчиг</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Slug</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Нийтлэгдсэн</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Огноо</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($posts as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <?php if ($p['image']): ?>
                            <img src="<?= e(str_starts_with($p['image'], 'http') ? $p['image'] : getBasePath() . 'backend/uploads/media/' . $p['image']) ?>"
                                 alt="<?= e($p['title_mn'] ?: $p['title']) ?>"
                                 class="w-10 h-10 rounded object-cover">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-sm font-medium text-gray-900"><?= e($p['title_mn'] ?: $p['title']) ?></p>
                        <?php if ($p['title_mn'] && $p['title']): ?>
                            <p class="text-xs text-gray-400 mt-0.5"><?= e($p['title']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <code class="text-xs text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded"><?= e($p['slug']) ?></code>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="w-2.5 h-2.5 rounded-full inline-block <?= $p['is_published'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">
                        <?= $p['published_at'] ? e(date('Y-m-d', strtotime($p['published_at']))) : '<span class="text-gray-300">—</span>' ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="index.php?page=blog-post-form&id=<?= $p['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Засах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <?php if (!hasRole('pos_cashier')): ?>
                            <a href="index.php?page=blog-post-delete&id=<?= $p['id'] ?>&token=<?= generateCSRFToken() ?>"
                               class="p-1.5 text-gray-400 hover:text-red-600"
                               onclick="return confirm('Энэ нийтлэлийг устгах уу?')" title="Устгах">
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
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
