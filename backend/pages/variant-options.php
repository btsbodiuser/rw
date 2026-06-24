<?php
$pageTitle = 'Өнгө & Хэмжээ';
$db = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=variant-options');
        exit;
    }

    $action = $_POST['action'];

    // ---- COLORS ----
    if ($action === 'add_color') {
        $name    = trim($_POST['name'] ?? '');
        $nameMn  = trim($_POST['name_mn'] ?? '');
        $hex     = trim($_POST['hex_code'] ?? '#000000');
        if ($name && $nameMn) {
            $sort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM product_colors")->fetchColumn();
            $db->prepare("INSERT INTO product_colors (name, name_mn, hex_code, sort_order) VALUES (?, ?, ?, ?)")->execute([$name, $nameMn, $hex, $sort]);
            setFlash('success', "\"$nameMn\" өнгө нэмэгдлээ.");
        } else {
            setFlash('error', 'Нэр (англи) болон нэр (монгол) шаардлагатай.');
        }
    }

    if ($action === 'update_color') {
        $id      = (int)($_POST['color_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $nameMn  = trim($_POST['name_mn'] ?? '');
        $hex     = trim($_POST['hex_code'] ?? '#000000');
        if ($id && $name && $nameMn) {
            $db->prepare("UPDATE product_colors SET name = ?, name_mn = ?, hex_code = ? WHERE id = ?")->execute([$name, $nameMn, $hex, $id]);
            setFlash('success', 'Өнгө шинэчлэгдлээ.');
        } else {
            setFlash('error', 'Нэр (англи) болон нэр (монгол) шаардлагатай.');
        }
    }

    if ($action === 'delete_color') {
        $id = (int)($_POST['color_id'] ?? 0);
        // Check if used in variants
        $used = $db->prepare("SELECT COUNT(*) FROM product_variants WHERE color_id = ?");
        $used->execute([$id]);
        if ((int)$used->fetchColumn() > 0) {
            setFlash('error', 'Энэ өнгийг хувилбарт ашигласан тул устгах боломжгүй.');
        } else {
            $db->prepare("DELETE FROM product_colors WHERE id = ?")->execute([$id]);
            setFlash('success', 'Өнгө устгагдлаа.');
        }
    }

    // ---- SIZES ----
    if ($action === 'add_size') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $sort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM product_sizes")->fetchColumn();
            $db->prepare("INSERT INTO product_sizes (name, sort_order) VALUES (?, ?)")->execute([$name, $sort]);
            setFlash('success', "\"$name\" хэмжээ нэмэгдлээ.");
        }
    }

    if ($action === 'update_size') {
        $id   = (int)($_POST['size_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $db->prepare("UPDATE product_sizes SET name = ? WHERE id = ?")->execute([$name, $id]);
            setFlash('success', 'Хэмжээ шинэчлэгдлээ.');
        }
    }

    if ($action === 'delete_size') {
        $id = (int)($_POST['size_id'] ?? 0);
        $used = $db->prepare("SELECT COUNT(*) FROM product_variants WHERE size_id = ?");
        $used->execute([$id]);
        if ((int)$used->fetchColumn() > 0) {
            setFlash('error', 'Энэ хэмжээг хувилбарт ашигласан тул устгах боломжгүй.');
        } else {
            $db->prepare("DELETE FROM product_sizes WHERE id = ?")->execute([$id]);
            setFlash('success', 'Хэмжээ устгагдлаа.');
        }
    }

    header('Location: index.php?page=variant-options');
    exit;
}

// Fetch data
$colors = $db->query("SELECT c.*, (SELECT COUNT(*) FROM product_variants WHERE color_id = c.id) as variant_count FROM product_colors c ORDER BY c.sort_order, c.id")->fetchAll();
$sizes  = $db->query("SELECT s.*, (SELECT COUNT(*) FROM product_variants WHERE size_id = s.id) as variant_count FROM product_sizes s ORDER BY s.sort_order, s.id")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- COLORS -->
    <div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <h3 class="font-semibold text-gray-900 mb-3">Өнгө нэмэх</h3>
            <form method="POST" class="space-y-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_color">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input type="text" name="name" required placeholder="Name (e.g. Red)"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <input type="text" name="name_mn" required placeholder="Нэр (жиш: Улаан)"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="flex gap-3 items-center">
                    <input type="color" name="hex_code" value="#e53e3e"
                           class="w-12 h-10 rounded-lg border border-gray-300 cursor-pointer p-1">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Нэмэх</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">Өнгөнүүд (<?= count($colors) ?>)</h3>
            </div>
            <?php if (empty($colors)): ?>
                <p class="p-5 text-gray-500 text-sm">Өнгө бүртгэгдээгүй байна.</p>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($colors as $color): ?>
                    <div x-data="{ editing: false }" class="px-5 py-3 flex items-center gap-3">
                        <!-- View mode -->
                        <template x-if="!editing">
                            <div class="flex items-center gap-3 w-full">
                                <span class="w-8 h-8 rounded-full border border-gray-200 flex-shrink-0" style="background-color: <?= e($color['hex_code']) ?>"></span>
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-gray-900"><?= e($color['name_mn']) ?></span>
                                    <span class="text-xs text-gray-500 ml-2"><?= e($color['name']) ?></span>
                                    <span class="text-xs text-gray-400 ml-2"><?= e($color['hex_code']) ?></span>
                                </div>
                                <span class="text-xs text-gray-400"><?= (int)$color['variant_count'] ?> хувилбар</span>
                                <button @click="editing = true" class="text-gray-400 hover:text-blue-600 p-1" title="Засах">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <?php if ((int)$color['variant_count'] === 0): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Устгах уу?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_color">
                                    <input type="hidden" name="color_id" value="<?= $color['id'] ?>">
                                    <button type="submit" class="text-gray-400 hover:text-red-600 p-1" title="Устгах">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </template>
                        <!-- Edit mode -->
                        <template x-if="editing">
                            <form method="POST" class="flex items-center gap-2 w-full flex-wrap">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_color">
                                <input type="hidden" name="color_id" value="<?= $color['id'] ?>">
                                <input type="color" name="hex_code" value="<?= e($color['hex_code']) ?>"
                                       class="w-8 h-8 rounded-full border border-gray-200 cursor-pointer p-0.5 flex-shrink-0">
                                <input type="text" name="name" value="<?= e($color['name']) ?>" required placeholder="Name (en)"
                                       class="flex-1 min-w-[120px] px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <input type="text" name="name_mn" value="<?= e($color['name_mn']) ?>" required placeholder="Нэр (мн)"
                                       class="flex-1 min-w-[120px] px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <button type="submit" class="text-green-600 hover:text-green-700 p-1" title="Хадгалах">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button type="button" @click="editing = false" class="text-gray-400 hover:text-gray-600 p-1" title="Болих">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        </template>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SIZES -->
    <div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <h3 class="font-semibold text-gray-900 mb-3">Хэмжээ нэмэх</h3>
            <form method="POST" class="flex flex-col sm:flex-row gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_size">
                <input type="text" name="name" required placeholder="Нэр (жиш: XL, 42, 200мл)"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Нэмэх</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">Хэмжээнүүд (<?= count($sizes) ?>)</h3>
            </div>
            <?php if (empty($sizes)): ?>
                <p class="p-5 text-gray-500 text-sm">Хэмжээ бүртгэгдээгүй байна.</p>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($sizes as $size): ?>
                    <div x-data="{ editing: false }" class="px-5 py-3 flex items-center gap-3">
                        <!-- View mode -->
                        <template x-if="!editing">
                            <div class="flex items-center gap-3 w-full">
                                <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-sm font-semibold text-gray-700 flex-shrink-0"><?= e($size['name']) ?></span>
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-gray-900"><?= e($size['name']) ?></span>
                                </div>
                                <span class="text-xs text-gray-400"><?= (int)$size['variant_count'] ?> хувилбар</span>
                                <button @click="editing = true" class="text-gray-400 hover:text-blue-600 p-1" title="Засах">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <?php if ((int)$size['variant_count'] === 0): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Устгах уу?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_size">
                                    <input type="hidden" name="size_id" value="<?= $size['id'] ?>">
                                    <button type="submit" class="text-gray-400 hover:text-red-600 p-1" title="Устгах">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </template>
                        <!-- Edit mode -->
                        <template x-if="editing">
                            <form method="POST" class="flex items-center gap-3 w-full">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_size">
                                <input type="hidden" name="size_id" value="<?= $size['id'] ?>">
                                <input type="text" name="name" value="<?= e($size['name']) ?>" required
                                       class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <button type="submit" class="text-green-600 hover:text-green-700 p-1" title="Хадгалах">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button type="button" @click="editing = false" class="text-gray-400 hover:text-gray-600 p-1" title="Болих">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        </template>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
