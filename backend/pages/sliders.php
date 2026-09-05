<?php
$pageTitle = 'Баннер';
$db = getDB();

// Load locations for filter dropdown + grouped listing
$locations = $db->query("SELECT id, slug, label_mn, label_en FROM banner_locations ORDER BY sort_order, label_mn")->fetchAll();

$locFilter = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

$where = '';
$params = [];
if ($locFilter > 0) { $where = 'WHERE s.location_id = ?'; $params[] = $locFilter; }

$sliders = $db->prepare("
    SELECT s.*, bl.label_mn AS location_label, bl.slug AS location_slug
    FROM sliders s
    LEFT JOIN banner_locations bl ON bl.id = s.location_id
    $where
    ORDER BY bl.sort_order, s.sort_order ASC, s.id ASC
");
$sliders->execute($params);
$sliders = $sliders->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div class="flex items-center gap-3">
        <p class="text-sm text-gray-500"><?= count($sliders) ?> баннер</p>
        <?php if (!empty($locations)): ?>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="page" value="sliders">
            <select name="location_id" onchange="this.form.submit()"
                    class="text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="0">Бүх байршил</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>" <?= $locFilter === (int)$loc['id'] ? 'selected' : '' ?>>
                        <?= e($loc['label_mn']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
        <a href="index.php?page=banner-locations" class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium text-gray-700">
            Байршил удирдах
        </a>
        <a href="index.php?page=slider-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Баннер нэмэх
        </a>
    </div>
</div>

<?php if (empty($sliders)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <p>Баннер алга.</p>
        <a href="index.php?page=slider-form" class="mt-3 inline-block text-blue-600 hover:underline text-sm">Эхний баннер үүсгэх</a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Зураг</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Байршил</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Гарчиг</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Товч</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Дараалал</th>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Идэвхтэй</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($sliders as $sl): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <?php $imgSrc = getBasePath() . 'backend/uploads/media/' . $sl['image']; ?>
                        <img src="<?= e($imgSrc) ?>" alt="" class="w-20 h-12 object-cover rounded-lg border border-gray-100">
                    </td>
                    <td class="px-5 py-3">
                        <?php if ($sl['location_label']): ?>
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 rounded-md">
                                <?= e($sl['location_label']) ?>
                            </span>
                            <p class="text-[10px] text-gray-400 font-mono mt-0.5"><?= e($sl['location_slug']) ?></p>
                        <?php else: ?>
                            <span class="text-xs text-red-500">Байршилгүй</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-sm font-medium text-gray-900"><?= e($sl['title_mn'] ?? '—') ?></p>
                        <?php if ($sl['subtitle_mn']): ?>
                        <p class="text-xs text-gray-400 mt-0.5"><?= e(mb_strimwidth($sl['subtitle_mn'], 0, 60, '…')) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600"><?= e($sl['btn_text'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-sm text-center text-gray-600"><?= (int)$sl['sort_order'] ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="w-2.5 h-2.5 rounded-full inline-block <?= $sl['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="index.php?page=slider-form&id=<?= $sl['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Засах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <?php if (!hasRole('pos_cashier')): ?>
                            <a href="index.php?page=slider-delete&id=<?= $sl['id'] ?>&token=<?= generateCSRFToken() ?>"
                               class="p-1.5 text-gray-400 hover:text-red-600"
                               onclick="return confirm('Энэ баннерыг устгах уу?')" title="Устгах">
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
