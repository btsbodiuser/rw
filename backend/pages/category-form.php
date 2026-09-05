<?php
$pageTitle = isset($_GET['id']) ? 'Ангилал засах' : 'Ангилал нэмэх';
$db = getDB();
$id = $_GET['id'] ?? null;
$category = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) {
        setFlash('error', 'Ангилал олдсонгүй.');
        header('Location: index.php?page=categories');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $nameMn = trim($_POST['name_mn'] ?? '');
    $slug = slugify($name);
    $icon = trim($_POST['icon'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $parentIdRaw = $_POST['parent_id'] ?? '';
    $parentId = ($parentIdRaw !== '' && (int)$parentIdRaw > 0) ? (int)$parentIdRaw : null;
    // Prevent self-parent
    if ($parentId && $id && $parentId === (int)$id) $parentId = null;

    $errors = [];
    if (!$name) $errors[] = 'Нэр шаардлагатай.';
    if (!$nameMn) $errors[] = 'Монгол нэр шаардлагатай.';

    // Handle image upload
    $image = $category['image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'categories');
        if (isset($upload['error'])) {
            $errors[] = $upload['error'];
        } else {
            if ($category && !empty($category['image'])) deleteImage($category['image']);
            $image = $upload['path'];
        }
    }

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE categories SET name=?, name_mn=?, slug=?, parent_id=?, icon=?, image=?, is_active=?, sort_order=? WHERE id=?");
            $stmt->execute([$name, $nameMn, $slug, $parentId, $icon, $image, $isActive, $sortOrder, $id]);
            setFlash('success', 'Ангилал шинэчлэгдлээ.');
        } else {
            $stmt = $db->prepare("INSERT INTO categories (name, name_mn, slug, parent_id, icon, image, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $nameMn, $slug, $parentId, $icon, $image, $isActive, $sortOrder]);
            setFlash('success', 'Ангилал үүсгэгдлээ.');
        }
        header('Location: index.php?page=categories');
        exit;
    }
    $category = $_POST;
}

// Load top-level categories for the parent picker (exclude self and this row's descendants)
$excludeIds = $id ? [(int)$id] : [];
$parentOptions = $db->query("SELECT id, name, name_mn FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=categories" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Ангилал руу буцах
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <?= csrfField() ?>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Нэр (Англи) *</label>
                <input type="text" name="name" value="<?= e($category['name'] ?? '') ?>" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Нэр (Монгол) *</label>
                <input type="text" name="name_mn" value="<?= e($category['name_mn'] ?? '') ?>" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Эх ангилал</label>
                <select name="parent_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">— Дээд түвшний ангилал —</option>
                    <?php foreach ($parentOptions as $po): ?>
                        <?php if (in_array((int)$po['id'], $excludeIds, true)) continue; ?>
                        <option value="<?= (int)$po['id'] ?>" <?= (int)($category['parent_id'] ?? 0) === (int)$po['id'] ? 'selected' : '' ?>>
                            <?= e($po['name_mn'] ?: $po['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">Дэд ангилал үүсгэх бол эх ангилалыг сонго (жиш: Хувцас → Пүүлэн, Гутал).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дүрс тэмдэгт (emoji)</label>
                <input type="text" name="icon" value="<?= e($category['icon'] ?? '') ?>" maxlength="10"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                       placeholder="e.g. 🏠">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дараалал</label>
                <input type="number" name="sort_order" value="<?= e($category['sort_order'] ?? 0) ?>" min="0"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Зураг</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 file:text-sm">
                <?php if (!empty($category['image'])): ?>
                <p class="text-xs text-gray-500 mt-1">Одоогийн: <?= e(basename($category['image'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= ($category['is_active'] ?? 1) ? 'checked' : '' ?>
                   class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="text-sm font-medium text-gray-700">Идэвхтэй</span>
        </label>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="index.php?page=categories" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Болих</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <?= $id ? 'Шинэчлэх' : 'Үүсгэх' ?> Ангилал
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
