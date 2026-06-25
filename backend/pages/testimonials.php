<?php
$pageTitle = 'Сэтгэгдэл';
$db = getDB();

$testimonials = $db->query("SELECT id, customer_name, customer_avatar, rating, title, sort_order, is_active, created_at
                             FROM testimonials ORDER BY sort_order ASC, id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500"><?= count($testimonials) ?> сэтгэгдэл</p>
    <a href="index.php?page=testimonial-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Сэтгэгдэл нэмэх
    </a>
</div>

<?php if (empty($testimonials)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <p>Одоогоор сэтгэгдэл байхгүй байна.</p>
        <a href="index.php?page=testimonial-form" class="mt-3 inline-block text-blue-600 hover:underline text-sm">Эхний сэтгэгдэл нэмэх</a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Аватар</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Нэр</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Гарчиг</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үнэлгээ</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дараалал</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Идэвхтэй</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($testimonials as $t): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <?php if ($t['customer_avatar']): ?>
                            <img src="<?= e(str_starts_with($t['customer_avatar'], 'http') ? $t['customer_avatar'] : getBasePath() . 'backend/uploads/media/' . $t['customer_avatar']) ?>"
                                 alt="<?= e($t['customer_name']) ?>"
                                 class="w-9 h-9 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-sm font-bold">
                                <?= e(mb_substr($t['customer_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-sm font-medium text-gray-900"><?= e($t['customer_name']) ?></p>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-sm text-gray-600"><?= e($t['title'] ?? '') ?></p>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex gap-0.5">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <svg class="w-3.5 h-3.5 <?= $s <= (int)$t['rating'] ? 'text-yellow-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php endfor; ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-sm text-center text-gray-600"><?= (int)$t['sort_order'] ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="w-2.5 h-2.5 rounded-full inline-block <?= $t['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="index.php?page=testimonial-form&id=<?= $t['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Засах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <?php if (!hasRole('pos_cashier')): ?>
                            <a href="index.php?page=testimonial-delete&id=<?= $t['id'] ?>&token=<?= generateCSRFToken() ?>"
                               class="p-1.5 text-gray-400 hover:text-red-600"
                               onclick="return confirm('Энэ сэтгэгдлийг устгах уу?')" title="Устгах">
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
