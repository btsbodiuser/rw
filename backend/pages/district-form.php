<?php
$pageTitle = 'Дүүрэг засах';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=districts');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $nameMn = trim($_POST['name_mn'] ?? '');

    if (!$name || !$nameMn) {
        setFlash('error', 'Нэр оруулна уу.');
        header('Location: index.php?page=district-form' . ($id ? '&id=' . $id : ''));
        exit;
    }

    if ($id) {
        $db->prepare("UPDATE districts SET name = ?, name_mn = ? WHERE id = ?")->execute([$name, $nameMn, $id]);
        setFlash('success', 'Дүүрэг шинэчлэгдлээ.');
    } else {
        $sortOrder = $db->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM districts")->fetchColumn();
        $db->prepare("INSERT INTO districts (name, name_mn, sort_order) VALUES (?, ?, ?)")->execute([$name, $nameMn, $sortOrder]);
        setFlash('success', 'Дүүрэг нэмэгдлээ.');
    }

    header('Location: index.php?page=districts');
    exit;
}

$district = null;
if ($id) {
    $stmt = $db->prepare("SELECT * FROM districts WHERE id = ?");
    $stmt->execute([$id]);
    $district = $stmt->fetch();
    if (!$district) {
        setFlash('error', 'Дүүрэг олдсонгүй.');
        header('Location: index.php?page=districts');
        exit;
    }
    $pageTitle = 'Дүүрэг засах: ' . $district['name_mn'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4"><?= $id ? 'Дүүрэг засах' : 'Дүүрэг нэмэх' ?></h3>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Англи нэр</label>
                <input type="text" name="name" required value="<?= e($district['name'] ?? '') ?>" placeholder="Bayangol"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Монгол нэр</label>
                <input type="text" name="name_mn" required value="<?= e($district['name_mn'] ?? '') ?>" placeholder="Баянгол"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <?= $id ? 'Хадгалах' : 'Нэмэх' ?>
                </button>
                <a href="index.php?page=districts" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Буцах</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
