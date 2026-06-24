<?php
/**
 * User Form — Create / Edit admin user (Super Admin only)
 */
requireRole('super_admin');

$db = getDB();
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = null;
$errors = [];

if ($editId) {
    $stmt = $db->prepare("SELECT id, username, name, role FROM admins WHERE id = ?");
    $stmt->execute([$editId]);
    $user = $stmt->fetch();
    if (!$user) {
        setFlash('error', 'Хэрэглэгч олдсонгүй.');
        header('Location: index.php?page=users');
        exit;
    }
}

$pageTitle = $user ? 'Хэрэглэгч засах' : 'Шинэ хэрэглэгч';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Буруу хүсэлт.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'pos_cashier';

        // Validate
        if (empty($name)) $errors[] = 'Нэр оруулна уу.';
        if (empty($username)) $errors[] = 'Нэвтрэх нэр оруулна уу.';
        if (!$user && strlen($password) < 6) $errors[] = 'Нууц үг 6+ тэмдэгт байх ёстой.';
        if ($user && $password && strlen($password) < 6) $errors[] = 'Нууц үг 6+ тэмдэгт байх ёстой.';
        if (!in_array($role, ['super_admin', 'admin', 'pos_cashier'])) $errors[] = 'Буруу эрх.';

        // Check duplicate username
        $checkStmt = $db->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $checkStmt->execute([$username, $editId]);
        if ($checkStmt->fetch()) $errors[] = 'Энэ нэвтрэх нэр бүртгэлтэй байна.';

        // Prevent demoting yourself
        if ($user && $user['id'] === $currentAdmin['id'] && $role !== 'super_admin') {
            $errors[] = 'Өөрийнхөө эрхийг бууруулах боломжгүй.';
        }

        if (empty($errors)) {
            if ($user) {
                // Update
                if ($password) {
                    $stmt = $db->prepare("UPDATE admins SET name = ?, username = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$name, $username, password_hash($password, PASSWORD_BCRYPT), $role, $editId]);
                } else {
                    $stmt = $db->prepare("UPDATE admins SET name = ?, username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$name, $username, $role, $editId]);
                }
                setFlash('success', 'Хэрэглэгч шинэчлэгдлээ.');
            } else {
                // Create
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO admins (username, password, name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $name, $role]);
                setFlash('success', 'Хэрэглэгч үүсгэгдлээ.');
            }
            header('Location: index.php?page=users');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="index.php?page=users" class="p-2 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-xl font-bold text-gray-900"><?= e($pageTitle) ?></h2>
    </div>

    <?php if ($errors): ?>
    <div class="mb-4 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg text-sm">
        <?php foreach ($errors as $err): ?>
        <p><?= e($err) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Нэр</label>
            <input type="text" name="name" value="<?= e($user['name'] ?? ($_POST['name'] ?? '')) ?>" required
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Нэвтрэх нэр</label>
            <input type="text" name="username" value="<?= e($user['username'] ?? ($_POST['username'] ?? '')) ?>" required
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Нууц үг <?= $user ? '<span class="text-gray-400 font-normal">(хоосон бол хэвээр)</span>' : '' ?>
            </label>
            <input type="password" name="password" minlength="6" <?= $user ? '' : 'required' ?>
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Эрх</label>
            <div class="grid grid-cols-3 gap-3">
                <?php
                $roles = [
                    'super_admin' => ['Супер админ', 'Бүх эрхтэй', 'bg-purple-50 border-purple-300 text-purple-700', 'peer-checked:bg-purple-100 peer-checked:border-purple-500'],
                    'admin' => ['Админ', 'Тохиргоо, хэрэглэгч удирдахаас бусад', 'bg-blue-50 border-blue-300 text-blue-700', 'peer-checked:bg-blue-100 peer-checked:border-blue-500'],
                    'pos_cashier' => ['Кассчин', 'POS + POS түүх', 'bg-green-50 border-green-300 text-green-700', 'peer-checked:bg-green-100 peer-checked:border-green-500'],
                ];
                $selectedRole = $user['role'] ?? ($_POST['role'] ?? 'pos_cashier');
                foreach ($roles as $roleKey => [$label, $desc, $colors, $checkedColors]):
                ?>
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="<?= $roleKey ?>" class="peer hidden"
                           <?= $selectedRole === $roleKey ? 'checked' : '' ?>>
                    <div class="border-2 rounded-xl p-3 text-center transition-all <?= $colors ?> <?= $checkedColors ?> peer-checked:ring-2 peer-checked:ring-offset-1">
                        <p class="text-sm font-bold"><?= $label ?></p>
                        <p class="text-[10px] mt-1 opacity-70"><?= $desc ?></p>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <a href="index.php?page=users" class="flex-1 py-2.5 text-center bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Болих</a>
            <button type="submit" class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"><?= $user ? 'Хадгалах' : 'Үүсгэх' ?></button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
