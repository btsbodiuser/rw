<?php
$pageTitle = 'Дүүрэг & Хороо';
$db = getDB();

// Handle add district
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=districts');
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'add_district') {
        $name = trim($_POST['name'] ?? '');
        $nameMn = trim($_POST['name_mn'] ?? '');
        if ($name && $nameMn) {
            $sortOrder = $db->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM districts")->fetchColumn();
            $db->prepare("INSERT INTO districts (name, name_mn, sort_order) VALUES (?, ?, ?)")->execute([$name, $nameMn, $sortOrder]);
            setFlash('success', 'Дүүрэг нэмэгдлээ.');
        }
    }

    if ($action === 'toggle_district') {
        $districtId = (int)($_POST['district_id'] ?? 0);
        $db->prepare("UPDATE districts SET is_active = NOT is_active WHERE id = ?")->execute([$districtId]);
        setFlash('success', 'Дүүргийн төлөв солигдлоо.');
    }

    if ($action === 'add_khoroo') {
        $districtId = (int)($_POST['district_id'] ?? 0);
        $number = (int)($_POST['khoroo_number'] ?? 0);
        if ($districtId && $number) {
            $exists = $db->prepare("SELECT id FROM khoroos WHERE district_id = ? AND number = ?");
            $exists->execute([$districtId, $number]);
            if (!$exists->fetch()) {
                $db->prepare("INSERT INTO khoroos (district_id, number) VALUES (?, ?)")->execute([$districtId, $number]);
                setFlash('success', "$number-р хороо нэмэгдлээ.");
            } else {
                setFlash('warning', "$number-р хороо аль хэдийн байна.");
            }
        }
    }

    if ($action === 'delete_khoroo') {
        $khorooId = (int)($_POST['khoroo_id'] ?? 0);
        $db->prepare("DELETE FROM khoroos WHERE id = ?")->execute([$khorooId]);
        setFlash('success', 'Хороо устгагдлаа.');
    }

    header('Location: index.php?page=districts');
    exit;
}

$districts = $db->query("SELECT d.*, 
    (SELECT COUNT(*) FROM khoroos WHERE district_id = d.id) as khoroo_count
    FROM districts d ORDER BY d.sort_order")->fetchAll();

// Get khoroos for each district
$khoroosByDistrict = [];
$khoroos = $db->query("SELECT * FROM khoroos ORDER BY district_id, number")->fetchAll();
foreach ($khoroos as $k) {
    $khoroosByDistrict[$k['district_id']][] = $k;
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Add District Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="font-semibold text-gray-900 mb-3">Дүүрэг нэмэх</h3>
    <form method="POST" class="flex flex-col sm:flex-row gap-3">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_district">
        <input type="text" name="name" required placeholder="Англи нэр (жиш: Bayangol)"
               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        <input type="text" name="name_mn" required placeholder="Монгол нэр (жиш: Баянгол)"
               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Нэмэх</button>
    </form>
</div>

<!-- Districts List -->
<div class="space-y-4" x-data="{ openDistrict: null }">
    <?php foreach ($districts as $d): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50" @click="openDistrict = openDistrict === <?= $d['id'] ?> ? null : <?= $d['id'] ?>">
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full <?= $d['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                <div>
                    <h4 class="font-medium text-gray-900"><?= e($d['name_mn']) ?> <span class="text-gray-400 font-normal">(<?= e($d['name']) ?>)</span></h4>
                    <p class="text-xs text-gray-400"><?= $d['khoroo_count'] ?> хороо</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" class="inline" onclick="event.stopPropagation()">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_district">
                    <input type="hidden" name="district_id" value="<?= $d['id'] ?>">
                    <button type="submit" class="text-xs px-3 py-1 rounded-lg <?= $d['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?> hover:opacity-80">
                        <?= $d['is_active'] ? 'Идэвхтэй' : 'Идэвхгүй' ?>
                    </button>
                </form>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="openDistrict === <?= $d['id'] ?> ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>

        <!-- Khoroos -->
        <div x-show="openDistrict === <?= $d['id'] ?>" x-collapse class="border-t border-gray-100 px-5 py-4">
            <div class="flex flex-wrap gap-2 mb-4">
                <?php if (isset($khoroosByDistrict[$d['id']])): ?>
                    <?php foreach ($khoroosByDistrict[$d['id']] as $k): ?>
                    <div class="flex items-center gap-1 px-3 py-1.5 bg-gray-100 rounded-lg text-sm">
                        <span><?= $k['name'] ? e($k['name']) : $k['number'] . '-р хороо' ?></span>
                        <form method="POST" class="inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_khoroo">
                            <input type="hidden" name="khoroo_id" value="<?= $k['id'] ?>">
                            <button type="submit" class="text-gray-400 hover:text-red-500 ml-1" onclick="return confirm('Энэ хороог устгах уу?')">×</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="POST" class="flex gap-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_khoroo">
                <input type="hidden" name="district_id" value="<?= $d['id'] ?>">
                <input type="number" name="khoroo_number" min="1" required placeholder="Дугаар №"
                       class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <input type="text" name="khoroo_name" placeholder="Нэр (сумын нэр)"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Нэмэх</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
