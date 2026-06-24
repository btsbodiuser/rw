<?php
$pageTitle = 'Жолооч';
$db = getDB();
$id = $_GET['id'] ?? null;
$driver = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM delivery_drivers WHERE id = ?");
    $stmt->execute([$id]);
    $driver = $stmt->fetch();
    if (!$driver) {
        setFlash('error', 'Жолооч олдсонгүй.');
        header('Location: index.php?page=drivers');
        exit;
    }
    $pageTitle = 'Жолооч засах';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $accessToken = trim($_POST['access_token'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name) || empty($phone)) {
        setFlash('error', 'Нэр болон утас заавал бөглөнө.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Check token uniqueness
    if ($accessToken) {
        $tokenCheck = $db->prepare("SELECT id FROM delivery_drivers WHERE access_token = ? AND id != ?");
        $tokenCheck->execute([$accessToken, $id ?? 0]);
        if ($tokenCheck->fetch()) {
            setFlash('error', 'Энэ токен аль хэдийн ашиглагдаж байна.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    if ($id) {
        $stmt = $db->prepare("UPDATE delivery_drivers SET name = ?, phone = ?, is_active = ?, access_token = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $isActive, $accessToken ?: null, $id]);
        setFlash('success', 'Жолооч шинэчлэгдлээ.');
    } else {
        $stmt = $db->prepare("INSERT INTO delivery_drivers (name, phone, is_active, access_token) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $isActive, $accessToken ?: null]);
        setFlash('success', 'Жолооч нэмэгдлээ.');
    }
    header('Location: index.php?page=drivers');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="index.php?page=drivers" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Жолоочид руу буцах
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-5"><?= $id ? 'Жолооч засах' : 'Шинэ жолооч' ?></h2>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Нэр *</label>
                <input type="text" name="name" value="<?= e($driver['name'] ?? '') ?>" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Утас *</label>
                <input type="text" name="phone" value="<?= e($driver['phone'] ?? '') ?>" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Токен (линк)</label>
                <input type="text" name="access_token" value="<?= e($driver['access_token'] ?? '') ?>" placeholder="жишээ: bat, bold123"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <p class="text-xs text-gray-400 mt-1">Жолоочийн линкэд ашиглагдана. Хоосон бол линк үүсэхгүй.</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" <?= ($driver['is_active'] ?? 1) ? 'checked' : '' ?>
                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="is_active" class="text-sm text-gray-700">Идэвхтэй</label>
            </div>
            <div class="pt-4 flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <?= $id ? 'Хадгалах' : 'Нэмэх' ?>
                </button>
                <a href="index.php?page=drivers" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Болих</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
