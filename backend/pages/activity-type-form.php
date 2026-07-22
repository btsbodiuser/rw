<?php
$pageTitle = 'Үйл ажиллагааны төрөл';
$db = getDB();

$id   = (int)($_GET['id'] ?? 0);
$item = null;
if ($id) {
    $s = $db->prepare("SELECT * FROM activity_types WHERE id = ?");
    $s->execute([$id]);
    $item = $s->fetch();
    if (!$item) { setFlash('error', 'Төрөл олдсонгүй.'); header('Location: index.php?page=activity-types'); exit; }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { setFlash('error', 'Буруу хүсэлт.'); header('Location: index.php?page=activity-types'); exit; }

    $name     = trim($_POST['name']     ?? '');
    $name_mn  = trim($_POST['name_mn']  ?? '');
    $slug     = trim($_POST['slug']     ?? '');
    $icon     = trim($_POST['icon']     ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if (!$name)    $errors[] = 'English нэр оруулна уу.';
    if (!$name_mn) $errors[] = 'Монгол нэр оруулна уу.';
    if (!$slug)    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));

    if (empty($errors)) {
        if ($id) {
            $db->prepare("UPDATE activity_types SET name=?, name_mn=?, slug=?, icon=?, sort_order=?, is_active=? WHERE id=?")
               ->execute([$name, $name_mn, $slug, $icon ?: null, $sort, $active, $id]);
        } else {
            $db->prepare("INSERT INTO activity_types (name, name_mn, slug, icon, sort_order, is_active) VALUES (?,?,?,?,?,?)")
               ->execute([$name, $name_mn, $slug, $icon ?: null, $sort, $active]);
        }
        setFlash('success', 'Хадгалагдлаа.');
        header('Location: index.php?page=activity-types');
        exit;
    }

    // Validation failed — keep POSTed values as the source of truth for the form
    $item = [
        'name'       => $name,
        'name_mn'    => $name_mn,
        'slug'       => $slug,
        'icon'       => $icon,
        'sort_order' => $sort,
        'is_active'  => $active,
    ];
}

// Resolve display values (edit → DB row, add → empty)
$v = [
    'name_mn'    => (string)($item['name_mn']    ?? ''),
    'name'       => (string)($item['name']       ?? ''),
    'slug'       => (string)($item['slug']       ?? ''),
    'icon'       => (string)($item['icon']       ?? ''),
    'sort_order' => (int)   ($item['sort_order'] ?? 0),
    'is_active'  => (int)   ($item['is_active']  ?? 1),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg">
    <div class="flex items-center gap-3 mb-6">
        <a href="index.php?page=activity-types" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-lg font-semibold text-gray-900"><?= $id ? 'Засах' : 'Нэмэх' ?></h2>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <?= csrfField() ?>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Монгол нэр *</label>
                <input type="text" name="name_mn" value="<?= e($v['name_mn']) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">English *</label>
                <input type="text" name="name" value="<?= e($v['name']) ?>"
                       id="nameEn"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                <input type="text" name="slug" value="<?= e($v['slug']) ?>"
                       id="slugField"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none"
                       placeholder="auto-generated">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дүрс (emoji)</label>
                <input type="text" name="icon" value="<?= e($v['icon']) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-xl text-center focus:ring-2 focus:ring-blue-500 outline-none"
                       placeholder="🏔️" maxlength="4">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дараалал</label>
                <input type="number" name="sort_order" value="<?= $v['sort_order'] ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" min="0">
            </div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" <?= $v['is_active'] ? 'checked' : '' ?>
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <span class="text-sm font-medium text-gray-700">Идэвхтэй</span>
                </label>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Хадгалах
            </button>
            <a href="index.php?page=activity-types" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                Буцах
            </a>
        </div>
    </form>
</div>

<script>
document.getElementById('nameEn')?.addEventListener('input', function () {
    const slugField = document.getElementById('slugField');
    if (!slugField.dataset.edited) {
        slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
});
document.getElementById('slugField')?.addEventListener('input', function () {
    this.dataset.edited = '1';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
