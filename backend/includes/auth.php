<?php
/**
 * Auth middleware - include this to require admin login
 */

if (!isset($_SESSION['admin_id'])) {
    // Check remember me cookie
    if (!empty($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $rStmt = getDB()->prepare("SELECT id, role FROM admins WHERE remember_token = ? AND remember_token IS NOT NULL");
        $rStmt->execute([$token]);
        $remembered = $rStmt->fetch();
        if ($remembered) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $remembered['id'];
            $_SESSION['admin_role'] = $remembered['role'];
            // Rotate token
            $newToken = bin2hex(random_bytes(32));
            getDB()->prepare("UPDATE admins SET remember_token = ? WHERE id = ?")->execute([$newToken, $remembered['id']]);
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie('remember_token', $newToken, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
            header('Location: index.php?page=login');
            exit;
        }
    } else {
        header('Location: index.php?page=login');
        exit;
    }
}

// Refresh admin data
$adminStmt = getDB()->prepare("SELECT id, username, name, role FROM admins WHERE id = ?");
$adminStmt->execute([$_SESSION['admin_id']]);
$currentAdmin = $adminStmt->fetch();

if (!$currentAdmin) {
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

$_SESSION['admin_role'] = $currentAdmin['role'];

// Role-based access control
function hasRole(string ...$roles): bool {
    global $currentAdmin;
    return in_array($currentAdmin['role'], $roles);
}

function requireRole(string ...$roles): void {
    if (!hasRole(...$roles)) {
        http_response_code(403);
        $GLOBALS['pageTitle'] = 'Хандах эрхгүй';
        require_once __DIR__ . '/header.php';
        echo '<div class="text-center py-20"><h2 class="text-2xl font-bold text-red-400">403 - Хандах эрхгүй</h2><p class="text-gray-500 mt-2">Энэ хуудсанд хандах эрх байхгүй байна.</p><a href="index.php" class="mt-4 inline-block text-blue-600 hover:underline">Буцах</a></div>';
        require_once __DIR__ . '/footer.php';
        exit;
    }
}

$roleLabels = [
    'super_admin' => 'Супер админ',
    'admin' => 'Админ',
    'pos_cashier' => 'Кассчин',
];

// Define page access per role
$rolePages = [
    'super_admin' => null, // null = all pages
    'admin' => ['dashboard', 'pos', 'products', 'product-form', 'product-delete', 'categories', 'category-form', 'category-delete', 'shops', 'shop-form', 'shop-delete', 'media', 'media-upload', 'media-delete', 'orders', 'order-detail', 'order-create', 'customers', 'customer-detail', 'customer-form', 'pos-history', 'cargo-batches', 'cargo-batch-form', 'batch-handout', 'inventory-arrivals', 'inventory-arrival-form', 'sms-queue', 'sms-templates', 'districts', 'district-form', 'reports', 'reconciliation', 'drivers', 'driver-form', 'driver-delete', 'deliveries', 'delivery-assign', 'product-entry-users', 'sync-products', 'stock-history', 'faqs', 'faq-form', 'faq-delete', 'running-attributes', 'sliders', 'slider-form', 'slider-delete', 'banner-locations', 'contact-messages', 'newsletter-subscribers', 'features', 'feature-form', 'feature-delete'],
    'pos_cashier' => ['pos', 'pos-history', 'products', 'product-form', 'categories', 'category-form', 'shops', 'shop-form', 'media', 'media-upload', 'orders', 'order-detail', 'deliveries', 'delivery-assign', 'delivery-batch-detail', 'drivers', 'driver-form', 'driver-delete', 'stock-history'],
];

$allowedPages = array_key_exists($currentAdmin['role'], $rolePages) ? $rolePages[$currentAdmin['role']] : [];

function canAccessPage(string $page): bool {
    global $allowedPages;
    return $allowedPages === null || in_array($page, $allowedPages);
}
