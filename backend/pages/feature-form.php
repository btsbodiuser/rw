<?php
$pageTitle = 'Шинж чанарын хайрцаг';
$db = getDB();

$id   = (int)($_GET['id'] ?? 0);
$item = null;
if ($id) {
    $s = $db->prepare("SELECT * FROM features WHERE id = ?");
    $s->execute([$id]);
    $item = $s->fetch();
    if (!$item) { setFlash('error', 'Хайрцаг олдсонгүй.'); header('Location: index.php?page=features'); exit; }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=features');
        exit;
    }

    $icon        = trim($_POST['icon']            ?? '') ?: 'icon-star';
    $title_mn    = trim($_POST['title_mn']        ?? '');
    $description = trim($_POST['description_mn']  ?? '');
    $sort        = (int)($_POST['sort_order']     ?? 0);
    $active      = isset($_POST['is_active']) ? 1 : 0;

    if (!$title_mn) $errors[] = 'Гарчиг оруулна уу.';

    if (empty($errors)) {
        if ($id) {
            $db->prepare("UPDATE features SET icon=?, title_mn=?, description_mn=?, sort_order=?, is_active=? WHERE id=?")
               ->execute([$icon, $title_mn, $description ?: null, $sort, $active, $id]);
        } else {
            $db->prepare("INSERT INTO features (icon, title_mn, description_mn, sort_order, is_active) VALUES (?,?,?,?,?)")
               ->execute([$icon, $title_mn, $description ?: null, $sort, $active]);
        }
        setFlash('success', 'Хадгалагдлаа.');
        header('Location: index.php?page=features');
        exit;
    }

    $item = [
        'icon'           => $icon,
        'title_mn'       => $title_mn,
        'description_mn' => $description,
        'sort_order'     => $sort,
        'is_active'      => $active,
    ];
}

$v = [
    'icon'           => (string)($item['icon']            ?? 'icon-boat'),
    'title_mn'       => (string)($item['title_mn']        ?? ''),
    'description_mn' => (string)($item['description_mn']  ?? ''),
    'sort_order'     => (int)   ($item['sort_order']      ?? 0),
    'is_active'      => (int)   ($item['is_active']       ?? 1),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg">
    <div class="flex items-center gap-3 mb-6">
        <a href="index.php?page=features" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-lg font-semibold text-gray-900"><?= $id ? 'Засах' : 'Нэмэх' ?></h2>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Гарчиг *</label>
            <input type="text" name="title_mn" value="<?= e($v['title_mn']) ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required
                   placeholder="Жишээ: Үнэгүй хүргэлт">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Тайлбар</label>
            <input type="text" name="description_mn" value="<?= e($v['description_mn']) ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                   placeholder="Жишээ: 50,000₮-с дээш захиалгад">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Icon <span class="text-gray-400 font-normal">(icomoon icon class)</span>
            </label>
            <input type="text" name="icon" value="<?= e($v['icon']) ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none"
                   placeholder="icon-boat">
            <p class="text-xs text-gray-500 mt-1">
                Түгээмэл: <code class="bg-gray-100 px-1 rounded">icon-boat</code>,
                <code class="bg-gray-100 px-1 rounded">icon-package</code>,
                <code class="bg-gray-100 px-1 rounded">icon-truck</code>,
                <code class="bg-gray-100 px-1 rounded">icon-calender</code>,
                <code class="bg-gray-100 px-1 rounded">icon-headset</code>,
                <code class="bg-gray-100 px-1 rounded">icon-shield-check</code>,
                <code class="bg-gray-100 px-1 rounded">icon-star</code>
            </p>
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
            <a href="index.php?page=features" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                Буцах
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
