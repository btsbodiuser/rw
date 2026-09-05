<?php
$pageTitle = 'Гүйлтийн шинж чанар';
$db = getDB();

// Table config: slug → [tableName, pivotTable, pivotFkColumn, labelMn, labelEn]
$attrs = [
    'shoe_type'  => ['shoe_types',  'product_shoe_types',  'shoe_type_id',  'Гутлын төрөл',    'Shoe types'],
    'run_type'   => ['run_types',   'product_run_types',   'run_type_id',   'Гүйлтийн төрөл',   'Run types'],
    'cushioning' => ['cushionings', 'product_cushionings', 'cushioning_id', 'Зөөлөвч',          'Cushioning'],
    'gait'       => ['gait_types',  'product_gait_types',  'gait_type_id',  'Алхааны төрөл',   'Gait types'],
];

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=running-attributes');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $attrKey = $_POST['attr'] ?? '';
    if (!isset($attrs[$attrKey])) {
        setFlash('error', 'Үл мэдэгдэх шинж чанар.');
        header('Location: index.php?page=running-attributes');
        exit;
    }
    [$table, $pivot, $fk] = $attrs[$attrKey];

    if ($action === 'add') {
        $name    = trim($_POST['name']    ?? '');
        $nameMn  = trim($_POST['name_mn'] ?? '');
        $slug    = trim($_POST['slug']    ?? '');
        if (!$name || !$nameMn) {
            setFlash('error', 'Нэр (англи + монгол) шаардлагатай.');
        } else {
            if (!$slug) $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
            $slug = trim($slug, '-');
            $sort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM `$table`")->fetchColumn();
            try {
                $db->prepare("INSERT INTO `$table` (name, name_mn, slug, sort_order, is_active) VALUES (?,?,?,?,1)")
                   ->execute([$name, $nameMn, $slug, $sort]);
                setFlash('success', "«$nameMn» нэмэгдлээ.");
            } catch (PDOException $e) {
                setFlash('error', 'Slug давхардсан байж болзошгүй. Өөр slug оруулна уу.');
            }
        }
    }

    if ($action === 'update') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name']    ?? '');
        $nameMn = trim($_POST['name_mn'] ?? '');
        $slug   = trim($_POST['slug']    ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;
        $sort   = (int)($_POST['sort_order'] ?? 0);
        if ($id && $name && $nameMn) {
            if (!$slug) $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
            $slug = trim($slug, '-');
            try {
                $db->prepare("UPDATE `$table` SET name=?, name_mn=?, slug=?, sort_order=?, is_active=? WHERE id=?")
                   ->execute([$name, $nameMn, $slug, $sort, $active, $id]);
                setFlash('success', 'Шинэчлэгдлээ.');
            } catch (PDOException $e) {
                setFlash('error', 'Slug давхардсан байна.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
            setFlash('success', 'Устгагдлаа.');
        }
    }

    header('Location: index.php?page=running-attributes#' . $attrKey);
    exit;
}

// ── Load all attribute rows with product usage counts ──
$data = [];
foreach ($attrs as $key => [$table, $pivot, $fk, $labelMn, $labelEn]) {
    $rows = $db->query("
        SELECT a.*, (SELECT COUNT(*) FROM `$pivot` p WHERE p.`$fk` = a.id) AS product_count
        FROM `$table` a
        ORDER BY a.sort_order, a.name_mn
    ")->fetchAll();
    $data[$key] = $rows;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-900">Гүйлтийн шинж чанарууд</h2>
    <p class="text-sm text-gray-500 mt-1">Гутлын төрөл, гүйлтийн төрөл, зөөлөвч, алхааны төрөл — эдгээрийг шүүлтүүрт хэрэглэнэ.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

<?php foreach ($attrs as $key => [$table, $pivot, $fk, $labelMn, $labelEn]): ?>
<?php $items = $data[$key]; ?>
<div id="<?= e($key) ?>" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-gray-900"><?= e($labelMn) ?></h3>
            <p class="text-xs text-gray-400"><?= e($labelEn) ?> · <?= count($items) ?></p>
        </div>
    </div>

    <!-- Add form -->
    <form method="POST" class="p-4 bg-gray-50 border-b border-gray-100 space-y-2">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="attr" value="<?= e($key) ?>">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <input type="text" name="name_mn" required placeholder="Монгол нэр"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <input type="text" name="name" required placeholder="English"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <input type="text" name="slug" placeholder="slug (auto)"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">+ Нэмэх</button>
        </div>
    </form>

    <!-- List -->
    <?php if (empty($items)): ?>
        <p class="p-5 text-sm text-gray-400">Хоосон байна.</p>
    <?php else: ?>
    <div class="divide-y divide-gray-50">
        <?php foreach ($items as $item): ?>
        <div x-data="{ editing: false }" class="px-4 py-2">
            <!-- View -->
            <template x-if="!editing">
                <div class="flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <span class="font-medium text-gray-900"><?= e($item['name_mn']) ?></span>
                        <span class="text-xs text-gray-500 ml-2"><?= e($item['name']) ?></span>
                        <span class="text-xs text-gray-400 ml-2 font-mono"><?= e($item['slug']) ?></span>
                    </div>
                    <span class="text-xs text-gray-400"><?= (int)$item['product_count'] ?> бараа</span>
                    <span class="w-2 h-2 rounded-full <?= $item['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                    <button @click="editing = true" class="text-gray-400 hover:text-blue-600 p-1" title="Засах">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <?php if ((int)$item['product_count'] === 0): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('«<?= e(addslashes($item['name_mn'])) ?>» устгах уу?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="attr" value="<?= e($key) ?>">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
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
                    <input type="hidden" name="attr" value="<?= e($key) ?>">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <input type="text" name="name_mn" value="<?= e($item['name_mn']) ?>" required placeholder="Монгол"
                           class="sm:col-span-2 px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <input type="text" name="name" value="<?= e($item['name']) ?>" required placeholder="English"
                           class="sm:col-span-2 px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <input type="text" name="slug" value="<?= e($item['slug']) ?>" placeholder="slug"
                           class="px-2 py-1.5 border border-gray-300 rounded text-sm font-mono">
                    <input type="number" name="sort_order" value="<?= (int)$item['sort_order'] ?>" min="0"
                           class="px-2 py-1.5 border border-gray-300 rounded text-sm w-16" title="Дараалал">
                    <label class="flex items-center gap-1 col-span-full sm:col-auto">
                        <input type="checkbox" name="is_active" value="1" <?= $item['is_active'] ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-blue-600">
                        <span class="text-xs text-gray-600">Идэвхтэй</span>
                    </label>
                    <div class="flex gap-1 col-span-full sm:col-auto justify-end">
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
<?php endforeach; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
