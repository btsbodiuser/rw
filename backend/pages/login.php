<?php
/**
 * Login Page
 */

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear remember token
    if (isset($_SESSION['admin_id'])) {
        $db = getDB();
        $db->prepare("UPDATE admins SET remember_token = NULL WHERE id = ?")->execute([$_SESSION['admin_id']]);
    }
    setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
    session_destroy();
    session_start();
    setFlash('info', 'Та системээс гарлаа.');
    header('Location: index.php?page=login');
    exit;
}

// Already logged in
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['admin_role'] ?? 'admin';
    header('Location: index.php?page=' . ($role === 'pos_cashier' ? 'pos' : 'dashboard'));
    exit;
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Буруу хүсэлт. Дахин оролдоно уу.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];

                // Remember me
                if (!empty($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    $db->prepare("UPDATE admins SET remember_token = ? WHERE id = ?")->execute([$token, $admin['id']]);
                    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                    setcookie('remember_token', $token, [
                        'expires' => time() + 86400 * 30,
                        'path' => '/',
                        'httponly' => true,
                        'secure' => $secure,
                        'samesite' => 'Lax',
                    ]);
                }

                $redir = $admin['role'] === 'pos_cashier' ? 'pos' : 'dashboard';
                header('Location: index.php?page=' . $redir);
                exit;
            } else {
                $error = 'Нэвтрэх нэр буюу нууц үг буруу байна.';
            }
        } else {
            $error = 'Бүх талбарыг бөглөнө үү.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Нэвтрэх - <?= e(getSetting('site_name_mn', 'Runners World')) ?> Удирдлага</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900"><?= e(getSetting('site_name_mn', 'Runners World')) ?> Удирдлага</h1>
            <p class="text-gray-500 mt-1">Дэлгүүрээ удирдахын тулд нэвтрэнэ үү</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-8">
            <?php renderFlash(); ?>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg text-sm">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php?page=login" class="space-y-5">
                <?= csrfField() ?>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Нэвтрэх нэр</label>
                    <input type="text" id="username" name="username" required autofocus
                           value="<?= e($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                           placeholder="Нэвтрэх нэрээ оруулна уу">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                           placeholder="Нууц үгээ оруулна уу">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" value="1"
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Намайг сана</label>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:ring-4 focus:ring-blue-200 transition">
                    Нэвтрэх
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6"><?= e(getSetting('site_name_mn', 'Runners World')) ?> Удирдлага v1.0</p>
    </div>
</body>
</html>
