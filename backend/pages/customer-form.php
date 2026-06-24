<?php
/**
 * Customer Form — Create / Edit customer (frontend user)
 */
$db = getDB();
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;
$errors = [];

if ($editId) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$editId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        setFlash('error', 'Хэрэглэгч олдсонгүй.');
        header('Location: index.php?page=customers');
        exit;
    }
}

$pageTitle = $customer ? 'Хэрэглэгч засах' : 'Шинэ хэрэглэгч';

// Function to determine registration method
function getRegistrationMethod($customer) {
    if (!empty($customer['google_id'])) {
        return ['Google', 'bg-red-100 text-red-800', '🔵'];
    }
    if (!empty($customer['facebook_id'])) {
        return ['Facebook', 'bg-blue-100 text-blue-800', '📘'];
    }
    if (!empty($customer['email'])) {
        return ['Имэйл', 'bg-green-100 text-green-800', '📧'];
    }
    return ['Утас', 'bg-gray-100 text-gray-800', '📱'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Буруу хүсэлт.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $googleId = trim($_POST['google_id'] ?? '');
        $facebookId = trim($_POST['facebook_id'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate
        if (empty($name)) $errors[] = 'Нэр оруулна уу.';
        if (strlen($phone) !== 8) $errors[] = 'Утасны дугаар 8 оронтой байх ёстой.';
        if (!$customer && strlen($password) < 6) $errors[] = 'Нууц үг 6+ тэмдэгт байх ёстой.';
        if ($customer && $password && strlen($password) < 6) $errors[] = 'Нууц үг 6+ тэмдэгт байх ёстой.';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Имэйл хаяг буруу байна.';

        // Check duplicate phone
        $checkStmt = $db->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
        $checkStmt->execute([$phone, $editId]);
        if ($checkStmt->fetch()) $errors[] = 'Энэ утасны дугаар бүртгэлтэй байна.';

        // Check duplicate email if provided
        if ($email) {
            $checkStmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
            $checkStmt->execute([$email, $editId]);
            if ($checkStmt->fetch()) $errors[] = 'Энэ имэйл хаяг бүртгэлтэй байна.';
        }

        if (empty($errors)) {
            if ($customer) {
                // Update
                if ($password) {
                    $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, google_id = ?, facebook_id = ?, avatar = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $email, $googleId, $facebookId, $avatar, password_hash($password, PASSWORD_BCRYPT), $editId]);
                } else {
                    $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, google_id = ?, facebook_id = ?, avatar = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $email, $googleId, $facebookId, $avatar, $editId]);
                }
                auditLog('customer_updated', 'customer', $editId, 'admin', $currentAdmin['id'], [
                    'name' => $name, 'phone' => $phone, 'email' => $email,
                ]);
                setFlash('success', 'Хэрэглэгч шинэчлэгдлээ.');
            } else {
                // Create
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO customers (phone, password, name, email, google_id, facebook_id, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$phone, $hash, $name, $email, $googleId, $facebookId, $avatar]);
                $newId = $db->lastInsertId();
                auditLog('customer_created', 'customer', $newId, 'admin', $currentAdmin['id'], [
                    'name' => $name, 'phone' => $phone, 'email' => $email,
                ]);
                setFlash('success', 'Хэрэглэгч үүсгэгдлээ.');
            }
            header('Location: index.php?page=customers');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="index.php?page=customers" class="p-2 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h2 class="text-xl font-bold text-gray-900"><?= e($pageTitle) ?></h2>
            <?php if ($customer): ?>
                <?php $regMethod = getRegistrationMethod($customer); ?>
                <p class="text-sm text-gray-500 mt-1">
                    Бүртгүүлсэн арга:
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium <?= $regMethod[1] ?> ml-1">
                        <span><?= $regMethod[2] ?></span>
                        <?= e($regMethod[0]) ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>
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
            <input type="text" name="name" value="<?= e($customer['name'] ?? ($_POST['name'] ?? '')) ?>" required
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Утасны дугаар</label>
            <input type="text" name="phone" value="<?= e($customer['phone'] ?? ($_POST['phone'] ?? '')) ?>" required
                   pattern="[0-9]{8}" maxlength="8" placeholder="8 оронтой дугаар"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Имэйл хаяг</label>
            <input type="email" name="email" value="<?= e($customer['email'] ?? ($_POST['email'] ?? '')) ?>"
                   placeholder="user@example.com"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Google ID</label>
            <input type="text" name="google_id" value="<?= e($customer['google_id'] ?? ($_POST['google_id'] ?? '')) ?>"
                   placeholder="Google account ID"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Facebook ID</label>
            <input type="text" name="facebook_id" value="<?= e($customer['facebook_id'] ?? ($_POST['facebook_id'] ?? '')) ?>"
                   placeholder="Facebook account ID"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Зураг URL</label>
            <input type="url" name="avatar" value="<?= e($customer['avatar'] ?? ($_POST['avatar'] ?? '')) ?>"
                   placeholder="https://example.com/avatar.jpg"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Нууц үг <?= $customer ? '<span class="text-gray-400 font-normal">(хоосон бол хэвээр)</span>' : '' ?>
            </label>
            <input type="password" name="password" minlength="6" <?= $customer ? '' : 'required' ?>
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <?php if ($customer): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Бүртгүүлсэн огноо</label>
            <input type="text" value="<?= date('Y-m-d H:i:s', strtotime($customer['created_at'])) ?>" readonly
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-600 cursor-not-allowed">
        </div>
        <?php endif; ?>

        <div class="flex gap-3 pt-2">
            <a href="index.php?page=customers" class="flex-1 py-2.5 text-center bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Болих</a>
            <button type="submit" class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"><?= $customer ? 'Хадгалах' : 'Үүсгэх' ?></button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
