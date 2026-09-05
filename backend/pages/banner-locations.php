<?php
$pageTitle = 'Баннер байршил';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=banner-locations');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $slug    = trim($_POST['slug']     ?? '');
        $labelMn = trim($_POST['label_mn'] ?? '');
        $labelEn = trim($_POST['label_en'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        if (!$slug)    $slug    = preg_replace('/[^a-z0-9]+/', '_', strtolower($labelEn ?: $labelMn));
        $slug = trim($slug, '_');

        if (!$slug || !$labelMn) {
            setFlash('error', 'Slug ба монгол нэр шаардлагатай.');
        } else {
            $sort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+10 FROM banner_locations")->fetchColumn();
            try {
                $db->prepare("INSERT INTO banner_locations (slug, label_mn, label_en, description, sort_order, is_active) VALUES (?,?,?,?,?,1)")
                   ->execute([$slug, $labelMn, $labelEn ?: $labelMn, $desc, $sort]);
                setFlash('success', "«$labelMn» нэмэгдлээ.");
            } catch (PDOException $e) {
                setFlash('error', 'Slug давхардсан байна.');
            }
        }
    }

    if ($action === 'update') {
        $id      = (int)($_POST['id'] ?? 0);
        $slug    = trim($_POST['slug']     ?? '');
        $labelMn = trim($_POST['label_mn'] ?? '');
        $labelEn = trim($_POST['label_en'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $sort    = (int)($_POST['sort_order'] ?? 0);
        $active  = isset($_POST['is_active']) ? 1 : 0;
        if (!$id || !$slug || !$labelMn) {
            setFlash('error', 'ID / slug / нэр шаардлагатай.');
        } else {
            try {
                $db->prepare("UPDATE banner_locations SET slug=?, label_mn=?, label_en=?, description=?, sort_order=?, is_active=? WHERE id=?")
                   ->execute([$slug, $labelMn, $labelEn ?: $labelMn, $desc, $sort, $active, $id]);
                setFlash('success', 'Шинэчлэгдлээ.');
            } catch (PDOException $e) {
                setFlash('error', 'Slug давхардсан байна.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $c = $db->prepare("SELECT COUNT(*) FROM sliders WHERE location_id = ?");
            $c->execute([$id]);
            if ((int)$c->fetchColumn() > 0) {
                setFlash('error', 'Баннер оноогдсон байршлыг устгах боломжгүй. Эхлээд баннеруудыг өөр байршил руу шилжүүлнэ үү.');
            } else {
                $db->prepare("DELETE FROM banner_locations WHERE id = ?")->execute([$id]);
                setFlash('success', 'Байршил устгагдлаа.');
            }
        }
    }

    header('Location: index.php?page=banner-locations');
    exit;
}

$locations = $db->query("
    SELECT bl.*, (SELECT COUNT(*) FROM sliders s WHERE s.location_id = bl.id) AS banner_count
    FROM banner_locations bl
    ORDER BY bl.sort_order, bl.label_mn
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-900">Баннер байршлууд</h2>
    <p class="text-sm text-gray-500 mt-1">Баннерууд харагдах газруудыг энд удирдана. Slug-ыг frontend код ашигладаг тул онцгойлон болгоомжтой солино уу.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Add form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-900 mb-3">Байршил нэмэх</h3>
            <form method="POST" class="space-y-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Монгол нэр *</label>
                    <input type="text" name="label_mn" required placeholder="жиш: Дэлгүүр — Дээр"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">English</label>
                    <input type="text" name="label_en" placeholder="Shop Page Top"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Slug (auto)</label>
                    <input type="text" name="slug" placeholder="shop_top"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Тайлбар (сонголтоор)</label>
                    <input type="text" name="description" placeholder="Хаана харагдах эсэх, ямар хэмжээтэй г.м."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">+ Нэмэх</button>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">Одоогийн байршлууд (<?= count($locations) ?>)</h3>
            </div>
            <?php if (empty($locations)): ?>
                <p class="p-5 text-sm text-gray-400">Байршил бүртгэлгүй.</p>
            <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php foreach ($locations as $loc): ?>
                <div x-data="{ editing: false }" class="px-5 py-3">
                    <!-- View -->
                    <template x-if="!editing">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900"><?= e($loc['label_mn']) ?></p>
                                <p class="text-xs text-gray-500">
                                    <span class="font-mono"><?= e($loc['slug']) ?></span>
                                    <?php if ($loc['label_en']): ?>· <?= e($loc['label_en']) ?><?php endif; ?>
                                    <?php if ($loc['description']): ?>
                                        <br><span class="text-gray-400"><?= e($loc['description']) ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="text-xs text-gray-400"><?= (int)$loc['banner_count'] ?> баннер</span>
                            <span class="w-2 h-2 rounded-full <?= $loc['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                            <button @click="editing = true" class="text-gray-400 hover:text-blue-600 p-1" title="Засах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <?php if ((int)$loc['banner_count'] === 0): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('«<?= e(addslashes($loc['label_mn'])) ?>» байршлыг устгах уу?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$loc['id'] ?>">
                                <button type="submit" class="text-gray-400 hover:text-red-600 p-1" title="Устгах">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </template>
                    <!-- Edit -->
                    <template x-if="editing">
                        <form method="POST" class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-center">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int)$loc['id'] ?>">
                            <input type="text" name="label_mn" value="<?= e($loc['label_mn']) ?>" required placeholder="Монгол"
                                   class="sm:col-span-2 px-2 py-1.5 border border-gray-300 rounded text-sm">
                            <input type="text" name="label_en" value="<?= e($loc['label_en']) ?>" placeholder="English"
                                   class="sm:col-span-2 px-2 py-1.5 border border-gray-300 rounded text-sm">
                            <input type="text" name="slug" value="<?= e($loc['slug']) ?>" required placeholder="slug"
                                   class="px-2 py-1.5 border border-gray-300 rounded text-sm font-mono">
                            <input type="number" name="sort_order" value="<?= (int)$loc['sort_order'] ?>" min="0"
                                   class="px-2 py-1.5 border border-gray-300 rounded text-sm w-16" title="Дараалал">
                            <input type="text" name="description" value="<?= e($loc['description']) ?>" placeholder="Тайлбар"
                                   class="sm:col-span-5 px-2 py-1.5 border border-gray-300 rounded text-sm">
                            <div class="flex items-center gap-2 col-span-full sm:col-auto justify-end">
                                <label class="flex items-center gap-1">
                                    <input type="checkbox" name="is_active" value="1" <?= $loc['is_active'] ? 'checked' : '' ?>
                                           class="rounded border-gray-300 text-blue-600">
                                    <span class="text-xs text-gray-600">Идэвх</span>
                                </label>
                                <button type="submit" class="text-green-600 hover:text-green-700 p-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button type="button" @click="editing = false" class="text-gray-400 hover:text-gray-600 p-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </form>
                    </template>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
