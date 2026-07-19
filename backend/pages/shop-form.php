<?php
$pageTitle = isset($_GET['id']) ? 'Брэнд засах' : 'Брэнд нэмэх';
$db = getDB();
$id = $_GET['id'] ?? null;
$shop = null;
$shopCategories = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM shops WHERE id = ?");
    $stmt->execute([$id]);
    $shop = $stmt->fetch();
    if (!$shop) {
        setFlash('error', 'Брэнд олдсонгүй.');
        header('Location: index.php?page=shops');
        exit;
    }
    $catStmt = $db->prepare("SELECT category_id FROM shop_categories WHERE shop_id = ?");
    $catStmt->execute([$id]);
    $shopCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
}

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $nameMn = trim($_POST['name_mn'] ?? '');
    $slug = slugify($name);
    $description = trim($_POST['description'] ?? '');
    $descriptionMn = trim($_POST['description_mn'] ?? '');
    $color = $_POST['color'] ?? '#3B82F6';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $selectedCategories = $_POST['categories'] ?? [];

    $errors = [];
    if (!$name) $errors[] = 'Брэндийн нэр шаардлагатай.';
    if (!$nameMn) $errors[] = 'Монгол нэр шаардлагатай.';

    // Handle logo upload
    $logo = $shop['logo'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $upload = uploadImage($_FILES['logo'], 'shops');
        if (isset($upload['error'])) {
            $errors[] = $upload['error'];
        } else {
            if ($shop && $shop['logo']) deleteImage($shop['logo']);
            $logo = $upload['path'];
        }
    }

    if (empty($errors)) {
        if ($id) {
            $slugCheck = $db->prepare("SELECT id FROM shops WHERE slug = ? AND id != ?");
            $slugCheck->execute([$slug, $id]);
            if ($slugCheck->fetch()) $slug .= '-' . time();

            $stmt = $db->prepare("UPDATE shops SET name=?, name_mn=?, slug=?, description=?, description_mn=?, color=?, logo=?, is_active=?, sort_order=? WHERE id=?");
            $stmt->execute([$name, $nameMn, $slug, $description, $descriptionMn, $color, $logo, $isActive, $sortOrder, $id]);

            // Update categories
            $db->prepare("DELETE FROM shop_categories WHERE shop_id = ?")->execute([$id]);
            $catStmt = $db->prepare("INSERT INTO shop_categories (shop_id, category_id) VALUES (?, ?)");
            foreach ($selectedCategories as $catId) {
                $catStmt->execute([$id, (int)$catId]);
            }
            setFlash('success', 'Брэнд шинэчлэгдлээ.');
        } else {
            $slugCheck = $db->prepare("SELECT id FROM shops WHERE slug = ?");
            $slugCheck->execute([$slug]);
            if ($slugCheck->fetch()) $slug .= '-' . time();

            $stmt = $db->prepare("INSERT INTO shops (name, name_mn, slug, description, description_mn, color, logo, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $nameMn, $slug, $description, $descriptionMn, $color, $logo, $isActive, $sortOrder]);
            $newId = $db->lastInsertId();

            $catStmt = $db->prepare("INSERT INTO shop_categories (shop_id, category_id) VALUES (?, ?)");
            foreach ($selectedCategories as $catId) {
                $catStmt->execute([$newId, (int)$catId]);
            }
            setFlash('success', 'Брэнд үүсгэгдлээ.');
        }
        header('Location: index.php?page=shops');
        exit;
    } else {
        $shop = $_POST;
        $shop['logo'] = $logo;
        $shopCategories = $selectedCategories;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=shops" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Брэнд руу буцах
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <?= csrfField() ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Брэндийн мэдээлэл</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нэр (Англи) *</label>
                    <input type="text" name="name" value="<?= e($shop['name'] ?? '') ?>" required
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нэр (Монгол) *</label>
                    <input type="text" name="name_mn" value="<?= e($shop['name_mn'] ?? '') ?>" required
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тайлбар (Англи)</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-vertical"><?= e($shop['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тайлбар (Монгол)</label>
                    <textarea name="description_mn" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-vertical"><?= e($shop['description_mn'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Брэнд өнгө</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" value="<?= e($shop['color'] ?? '#3B82F6') ?>" class="w-12 h-10 rounded border border-gray-300 cursor-pointer">
                        <span class="text-sm text-gray-500"><?= e($shop['color'] ?? '#3B82F6') ?></span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дараалал</label>
                    <input type="number" name="sort_order" value="<?= e($shop['sort_order'] ?? 0) ?>" min="0"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Лого</label>
                    <input type="file" name="logo" accept="image/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 file:text-sm">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" <?= ($shop['is_active'] ?? 1) ? 'checked' : '' ?>
                               class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">Идэвхтэй</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ангилал</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <?php foreach ($categories as $c): ?>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="categories[]" value="<?= $c['id'] ?>"
                           <?= in_array($c['id'], $shopCategories) ? 'checked' : '' ?>
                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm"><?= e($c['icon'] . ' ' . $c['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="index.php?page=shops" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Болих</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <?= $id ? 'Брэнд шинэчлэх' : 'Брэнд үүсгэх' ?>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
