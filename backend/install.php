<?php
/**
 * Runners World — Суулгах / Installation Wizard
 * Run once: https://runnersworld.mn/backend/install.php
 */
session_start();

$lockFile = __DIR__ . '/.installed';

// ── CSRF Token ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['error' => 'CSRF token буруу байна. Хуудсаа дахин ачааллана уу.']);
        exit;
    }

    if (file_exists($lockFile)) {
        echo json_encode(['error' => 'Аль хэдийн суулгасан байна.']);
        exit;
    }

    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';
    $dbName = trim($_POST['db_name'] ?? 'rw');

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        echo json_encode(['error' => 'DB нэр зөвхөн үсэг, тоо, доогуур зураас агуулах ёстой']);
        exit;
    }
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? '';
    $adminName = trim($_POST['admin_name'] ?? 'Admin');
    $siteName = trim($_POST['site_name'] ?? 'Runners World');
    $siteNameMn = trim($_POST['site_name_mn'] ?? $siteName);
    $siteSlogan = trim($_POST['site_slogan'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($adminUser) || strlen($adminPass) < 6) {
        echo json_encode(['error' => 'Админ нэр, нууц үг (6+ тэмдэгт) шаардлагатай']);
        exit;
    }

    $log = [];
    try {
        $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");
        $pdo->exec("ALTER DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $log[] = '✓ Мэдээллийн сан үүсгэсэн';

        // ==================== TABLES ====================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `role` ENUM('super_admin','admin','pos_cashier') NOT NULL DEFAULT 'admin',
            `remember_token` VARCHAR(64) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ admins';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `shops` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(50) UNIQUE NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `name_mn` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `description_mn` TEXT,
            `color` VARCHAR(20) DEFAULT '#3B82F6',
            `logo` VARCHAR(255),
            `is_active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ shops';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(50) UNIQUE NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `name_mn` VARCHAR(100) NOT NULL,
            `icon` VARCHAR(10) DEFAULT '',
            `image` VARCHAR(255) NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ categories';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `shop_categories` (
            `shop_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            PRIMARY KEY (`shop_id`, `category_id`),
            FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ shop_categories';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `name_mn` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) UNIQUE NOT NULL,
            `category_id` INT NOT NULL,
            `shop_id` INT NOT NULL,
            `type` ENUM('ready','preorder') DEFAULT 'ready',
            `price` DECIMAL(12,2) NOT NULL,
            `original_price` DECIMAL(12,2) NULL,
            `weight_kg` DECIMAL(8,3) NULL,
            `barcode` VARCHAR(50) NULL,
            `image` VARCHAR(255),
            `main_image_id` INT NULL,
            `image_ids` JSON NULL,
            `description` TEXT,
            `description_mn` TEXT,
            `stock` INT DEFAULT 0,
            `preorder_date` DATE NULL,
            `order_status` ENUM('open','closed') DEFAULT 'open',
            `rating` DECIMAL(2,1) DEFAULT 0,
            `reviews` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `show_in_store` TINYINT(1) NOT NULL DEFAULT 1,
            `hide_cargo_fee` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`),
            FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`),
            INDEX `idx_products_barcode` (`barcode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ products';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `media` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(50) NOT NULL,
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `alt_text` VARCHAR(255) DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ media';

        // Add main_image_id FK now that media table exists
        try {
            $pdo->exec("ALTER TABLE `products` ADD CONSTRAINT `fk_products_main_image` FOREIGN KEY (`main_image_id`) REFERENCES `media`(`id`) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // FK may already exist on re-run
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cargo_batches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `due_date` DATETIME NOT NULL,
            `status` ENUM('open','closed','shipping','arrived') DEFAULT 'open',
            `cargo_rate_per_kg` DECIMAL(10,2) NOT NULL,
            `notes` TEXT,
            `sms_sent_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ cargo_batches';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `districts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `name_mn` VARCHAR(100) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ districts';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `khoroos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `district_id` INT NOT NULL,
            `number` INT NOT NULL,
            `name` VARCHAR(100),
            UNIQUE KEY `uq_district_number` (`district_id`, `number`),
            FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ khoroos';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `customer_id` INT NULL,
            `order_number` VARCHAR(20) UNIQUE NOT NULL,
            `order_type` ENUM('pos','online') NOT NULL DEFAULT 'online',
            `fulfillment` ENUM('pickup','delivery') DEFAULT 'delivery',
            `status` ENUM('pending','confirmed','cargo_shipping','cargo_arrived','ready_pickup','delivering','partially_delivered','delivered','picked_up','completed','cancelled') DEFAULT 'pending',
            `customer_name` VARCHAR(100),
            `customer_phone` VARCHAR(20),
            `district_id` INT NULL,
            `khoroo_id` INT NULL,
            `address` TEXT NULL,
            `detail_address` TEXT NULL,
            `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `delivery_fee` DECIMAL(10,2) DEFAULT 0,
            `cargo_fee` DECIMAL(10,2) DEFAULT 0,
            `cargo_fee_paid` TINYINT(1) NOT NULL DEFAULT 0,
            `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `cargo_batch_id` INT NULL,
            `payment_method` VARCHAR(50) DEFAULT 'cash',
            `payment_cash` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `payment_card` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `payment_transfer` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `payment_transfer_nomin` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `payment_status` ENUM('pending','paid','refunded') DEFAULT 'pending',
            `qpay_invoice_id` VARCHAR(100) NULL,
            `bonum_invoice_id` VARCHAR(100) NULL,
            `storepay_invoice_id` VARCHAR(100) NULL,
            `notes` TEXT,
            `order_note` TEXT NULL,
            `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`khoroo_id`) REFERENCES `khoroos`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`cargo_batch_id`) REFERENCES `cargo_batches`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ orders';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `stock_movements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `variant_id` INT NULL,
            `delta` INT NOT NULL,
            `balance_after` INT NULL,
            `reason` VARCHAR(40) NOT NULL,
            `order_id` INT NULL,
            `actor_type` ENUM('customer','admin','system') NOT NULL DEFAULT 'system',
            `actor_id` INT NULL,
            `note` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_sm_product_time` (`product_id`, `created_at`),
            INDEX `idx_sm_variant_time` (`variant_id`, `created_at`),
            INDEX `idx_sm_order` (`order_id`),
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ stock_movements';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `product_price` DECIMAL(12,2) NOT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `line_total` DECIMAL(12,2) NULL,
            `weight_kg` DECIMAL(8,3) NULL,
            `cargo_fee` DECIMAL(10,2) DEFAULT 0,
            `cargo_fee_paid` TINYINT(1) NOT NULL DEFAULT 0,
            `hide_cargo_fee` TINYINT(1) NOT NULL DEFAULT 0,
            `delivery_status` ENUM('pending','delivered') NOT NULL DEFAULT 'pending',
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ order_items';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(50) PRIMARY KEY,
            `setting_value` TEXT NOT NULL,
            `label` VARCHAR(100) NOT NULL,
            `type` ENUM('text','number','boolean') DEFAULT 'text',
            `is_public` TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ settings';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `phone` VARCHAR(20) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ customers';

        // Add orders.customer_id FK now that customers table exists
        try {
            $pdo->exec("ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // FK may already exist on re-run
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS `otp_codes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `phone` VARCHAR(20) NOT NULL,
            `code` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `is_used` TINYINT(1) DEFAULT 0,
            `verify_attempts` INT NOT NULL DEFAULT 0,
            `otp_token` VARCHAR(64) NULL,
            `otp_token_expires` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_phone_code` (`phone`, `code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ otp_codes';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `customer_id` INT NOT NULL,
            `token` VARCHAR(64) UNIQUE NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
            INDEX `idx_token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ customer_sessions';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_addresses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `customer_id` INT NOT NULL,
            `label` VARCHAR(50) DEFAULT '',
            `district_id` INT NULL,
            `khoroo_id` INT NULL,
            `address` TEXT NOT NULL,
            `detail_address` TEXT NULL,
            `is_default` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ customer_addresses';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `action` VARCHAR(50) NOT NULL,
            `entity_type` VARCHAR(50) NOT NULL,
            `entity_id` INT NULL,
            `actor_type` ENUM('customer','admin','system') DEFAULT 'system',
            `actor_id` INT NULL,
            `ip_address` VARCHAR(45) NULL,
            `details` JSON NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_entity` (`entity_type`, `entity_id`),
            INDEX `idx_actor` (`actor_type`, `actor_id`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ audit_log';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `delivery_drivers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ delivery_drivers';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `deliveries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `driver_id` INT NOT NULL,
            `status` ENUM('assigned','picked_up','delivered','failed') DEFAULT 'assigned',
            `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `picked_up_at` DATETIME NULL,
            `delivered_at` DATETIME NULL,
            `notes` TEXT NULL,
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`driver_id`) REFERENCES `delivery_drivers`(`id`) ON DELETE CASCADE,
            INDEX `idx_driver` (`driver_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = '✓ deliveries';

        // ==================== SEED: Admin ============================
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO `admins` (`username`, `password`, `name`, `role`) VALUES (?, ?, ?, 'super_admin')");
        $stmt->execute([$adminUser, $hash, $adminName]);
        $log[] = '✓ Админ хэрэглэгч үүсгэсэн';

        // ==================== SEED: Settings =========================
        $settings = [
            ['delivery_fee_enabled', '1', 'Хүргэлтийн төлбөрийг тооцох', 'boolean'],
            ['delivery_fee', '5000', 'Delivery Fee', 'number'],
            ['free_delivery_threshold', '50000', 'Free Delivery Threshold', 'number'],
            ['cargo_rate_per_kg', '8000', 'Cargo Rate per KG', 'number'],
            ['site_name', $siteName, 'Site Name', 'text'],
            ['site_name_mn', $siteNameMn, 'Site Name (Mongolian)', 'text'],
            ['site_slogan', $siteSlogan, 'Site Slogan', 'text'],
            ['site_logo', '', 'Site Logo', 'text'],
            ['site_favicon', '', 'Site Favicon', 'text'],
            ['site_primary_color', '#2563eb', 'Primary Color', 'text'],
            ['currency', '₮', 'Currency Symbol', 'text'],
            ['order_number_prefix', 'NO', 'Захиалгын дугаарын угтвар', 'text'],
            ['reconciliation_time_window_minutes', '30', 'Тохирол: цагийн цонх (минут)', 'number'],
            ['phone', $phone, 'Contact Phone', 'text'],
            ['email', $email, 'Contact Email', 'text'],
            ['address', '', 'Store Address', 'text'],
            ['hero_title', 'Чанартай бүтээгдэхүүн', 'Hero Title', 'text'],
            ['hero_subtitle', 'Танай гэрт хүргэнэ', 'Hero Subtitle', 'text'],
            ['hero_description', '', 'Hero Description', 'text'],
            ['hero_btn1_text', 'Бүх бараа үзэх', 'Hero Button 1 Text', 'text'],
            ['hero_btn2_text', 'Урьдчилсан захиалга', 'Hero Button 2 Text', 'text'],
            ['shops_title', '', 'Shops Section Title', 'text'],
            ['shops_description', '', 'Shops Section Description', 'text'],
            ['feature1_title', '', 'Feature 1 Title', 'text'],
            ['feature1_desc', '', 'Feature 1 Description', 'text'],
            ['feature2_title', '', 'Feature 2 Title', 'text'],
            ['feature2_desc', '', 'Feature 2 Description', 'text'],
            ['feature3_title', '', 'Feature 3 Title', 'text'],
            ['feature3_desc', '', 'Feature 3 Description', 'text'],
            ['top_bar_text', '', 'Top Bar Text', 'text'],
            ['top_bar_text_short', '', 'Top Bar Text (Short)', 'text'],
            ['home_categories_enabled', '1', 'Ангиллын хэсгийг харуулах', 'boolean'],
            ['home_category_ready_enabled', '1', 'Бэлэн бараа картыг харуулах', 'boolean'],
            ['home_category_preorder_enabled', '1', 'Урьдчилсан захиалга картыг харуулах', 'boolean'],
            ['home_category_discount_enabled', '1', 'Хямдрал картыг харуулах', 'boolean'],
            ['home_category_new_enabled', '1', 'Шинэ бараа картыг харуулах', 'boolean'],
            ['home_category_ready_image', '', 'Бэлэн бараа картны зураг', 'text'],
            ['home_category_preorder_image', '', 'Урьдчилсан захиалга картны зураг', 'text'],
            ['home_category_discount_image', '', 'Хямдрал картны зураг', 'text'],
            ['home_category_new_image', '', 'Шинэ бараа картны зураг', 'text'],
            ['bank_name', '', 'Банкны нэр', 'text'],
            ['bank_account_number', '', 'Дансны дугаар', 'text'],
            ['bank_account_name', '', 'Дансны нэр', 'text'],
            ['payment_qpay_enabled', '1', 'QPay төлбөр идэвхтэй', 'boolean'],
            ['payment_transfer_enabled', '1', 'Шилжүүлэг төлбөр идэвхтэй', 'boolean'],
            ['payment_bonum_enabled', '0', 'Bonum төлбөр идэвхтэй', 'boolean'],
            ['payment_storepay_enabled', '0', 'StorePay төлбөр идэвхтэй', 'boolean'],
            ['login_phone_password_enabled', '1', 'Утас + нууц үгээр нэвтрэх', 'boolean'],
            ['login_phone_otp_enabled', '1', 'Утас + OTP кодоор нэвтрэх', 'boolean'],
            ['login_register_without_otp_enabled', '0', 'SMS OTP-г хассан шууд бүртгүүлэх', 'boolean'],
            ['login_email_enabled', '0', 'Имэйлээр нэвтрэх', 'boolean'],
            ['register_email_enabled', '0', 'Имэйлээр бүртгүүлэх', 'boolean'],
            ['login_google_enabled', '0', 'Google нэвтрэх идэвхгүй', 'boolean'],
            ['login_facebook_enabled', '0', 'Facebook нэвтрэх идэвхгүй', 'boolean'],
            ['google_client_id', '', 'Google Client ID', 'text'],
            ['facebook_app_id', '', 'Facebook App ID', 'text'],
        ];
        $sStmt = $pdo->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`) VALUES (?, ?, ?, ?)");
        foreach ($settings as $s) { $sStmt->execute($s); }

        $privateSettings = [
            ['messagepro_api_key', '', 'MessagePro API Key', 'text'],
            ['messagepro_from_number', '', 'MessagePro From Number', 'text'],
            ['qpay_username', '', 'QPay Username', 'text'],
            ['qpay_password', '', 'QPay Password', 'text'],
            ['qpay_invoice_code', '', 'QPay Invoice Code', 'text'],
            ['bonum_merchant_id', '', 'Bonum Merchant ID', 'text'],
            ['bonum_terminal_id', '', 'Bonum Terminal ID', 'text'],
            ['bonum_secret_key', '', 'Bonum Secret Key', 'text'],
            ['bonum_checksum_key', '', 'Bonum Checksum Key', 'text'],
            ['storepay_store_id', '', 'StorePay Store ID', 'text'],
            ['storepay_username', '', 'StorePay System Username', 'text'],
            ['storepay_password', '', 'StorePay System Password', 'text'],
            ['storepay_app_username', '', 'StorePay App Username', 'text'],
            ['storepay_app_password', '', 'StorePay App Password', 'text'],
            ['smtp_host', '', 'SMTP Host', 'text'],
            ['smtp_port', '587', 'SMTP Port', 'number'],
            ['smtp_username', '', 'SMTP Username', 'text'],
            ['smtp_password', '', 'SMTP Password', 'text'],
            ['smtp_from_email', '', 'SMTP From Email', 'text'],
            ['smtp_from_name', '', 'SMTP From Name', 'text'],
            ['smtp_encryption', 'tls', 'SMTP Encryption (tls/ssl)', 'text'],
            ['email_template_subject_register', 'Runners World имэйл баталгаажуулах код', 'Имэйл баталгаажуулах сэдэв', 'text'],
            ['email_template_body_register', 'Таны баталгаажуулах код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Имэйл баталгаажуулах бие', 'text'],
            ['email_template_subject_reset', 'Runners World нууц үг сэргээх код', 'Нууц үг сэргээх сэдэв', 'text'],
            ['email_template_body_reset', 'Таны нууц үг сэргээх код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Нууц үг сэргээх бие', 'text'],
        ];
        $sStmt2 = $pdo->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, 0)");
        foreach ($privateSettings as $ps) { $sStmt2->execute($ps); }
        $log[] = '✓ Тохиргоо үүсгэсэн';

        // ==================== SEED: Districts ========================
        $districts = [
            ['Баянгол', 'Bayangol', range(1, 23)],
            ['Баянзүрх', 'Bayanzurkh', range(1, 28)],
            ['Чингэлтэй', 'Chingeltei', range(1, 19)],
            ['Сүхбаатар', 'Sukhbaatar', range(1, 20)],
            ['Хан-Уул', 'Khan-Uul', range(1, 22)],
            ['Сонгинохайрхан', 'Songinokhairkhan', range(1, 32)],
            ['Налайх', 'Nalaikh', range(1, 7)],
            ['Багануур', 'Baganuur', range(1, 5)],
            ['Багахангай', 'Bagakhangai', range(1, 2)],
        ];

        // ── Aimags (no khoroos) ──
        $aimags = [
            ['Архангай аймаг', 'Arkhangai'],
            ['Баян-Өлгий аймаг', 'Bayan-Olgii'],
            ['Баянхонгор аймаг', 'Bayankhongor'],
            ['Булган аймаг', 'Bulgan'],
            ['Говь-Алтай аймаг', 'Govi-Altai'],
            ['Говьсүмбэр аймаг', 'Govisumber'],
            ['Дархан-Уул аймаг', 'Darkhan-Uul'],
            ['Дорноговь аймаг', 'Dornogobi'],
            ['Дорнод аймаг', 'Dornod'],
            ['Дундговь аймаг', 'Dundgobi'],
            ['Завхан аймаг', 'Zavkhan'],
            ['Орхон аймаг', 'Orkhon'],
            ['Өвөрхангай аймаг', 'Ovorkhangai'],
            ['Өмнөговь аймаг', 'Omnogobi'],
            ['Сүхбаатар аймаг', 'Sukhbaatar-aimag'],
            ['Сэлэнгэ аймаг', 'Selenge'],
            ['Төв аймаг', 'Tuv'],
            ['Увс аймаг', 'Uvs'],
            ['Ховд аймаг', 'Khovd'],
            ['Хөвсгөл аймаг', 'Khovsgol'],
            ['Хэнтий аймаг', 'Khentii'],
        ];
        $dStmt = $pdo->prepare("INSERT IGNORE INTO `districts` (`name`, `name_mn`, `sort_order`) VALUES (?, ?, ?)");
        $kStmt = $pdo->prepare("INSERT IGNORE INTO `khoroos` (`district_id`, `number`) VALUES (?, ?)");
        $kNameStmt = $pdo->prepare("INSERT IGNORE INTO `khoroos` (`district_id`, `number`, `name`) VALUES (?, ?, ?)");
        foreach ($districts as $i => $d) {
            $dStmt->execute([$d[1], $d[0], $i + 1]);
            $districtId = $pdo->lastInsertId();
            if (!$districtId) {
                // District already exists, look up its ID
                $lookup = $pdo->prepare("SELECT `id` FROM `districts` WHERE `name` = ?");
                $lookup->execute([$d[1]]);
                $districtId = $lookup->fetchColumn();
            }
            if ($districtId) {
                foreach ($d[2] as $khoroo) {
                    $kStmt->execute([$districtId, $khoroo]);
                }
            }
        }
        // Insert aimags with soums
        $aimagSoums = [
            'Arkhangai' => ['Эрдэнэбулган','Батцэнгэл','Булган','Жаргалант','Ихтамир','Өгийнуур','Өлзийт','Өндөр-Улаан','Тариат','Төвшрүүлэх','Хайрхан','Хангай','Хашаат','Хотонт','Цахир','Цэнхэр','Цэцэрлэг','Чулуут','Эрдэнэмандал'],
            'Bayan-Olgii' => ['Өлгий','Алтай','Алтанцөгц','Баяннуур','Бугат','Булган','Буянт','Дэлүүн','Ногооннуур','Сагсай','Толбо','Улаанхус','Цэнгэл'],
            'Bayankhongor' => ['Баянхонгор','Баацагаан','Баян-Өндөр','Баянбулаг','Баянговь','Баянлиг','Баянцагаан','Баян-Овоо','Богд','Бөмбөгөр','Бууцагаан','Галуут','Гурванбулаг','Жаргалант','Жинст','Заг','Өлзийт','Хүрээмарал','Шинэжинст','Эрдэнэцогт'],
            'Bulgan' => ['Булган','Баян-Агт','Баяннуур','Бугат','Бүрэгхангай','Гурванбулаг','Дашинчилэн','Могод','Орхон','Рашаант','Сайхан','Сэлэнгэ','Тэшиг','Хангал','Хишиг-Өндөр','Хутаг-Өндөр'],
            'Govi-Altai' => ['Есөнбулаг','Алтай','Баян-Уул','Бигэр','Бугат','Дарив','Дэлгэр','Жаргалан','Тайшир','Тонхил','Төгрөг','Халиун','Хөхморьт','Цогт','Цээл','Чандмань','Шарга','Эрдэнэ'],
            'Govisumber' => ['Сүмбэр','Баянтал','Шивээговь'],
            'Darkhan-Uul' => ['Дархан','Хонгор','Орхон','Шарынгол'],
            'Dornogobi' => ['Сайншанд','Айраг','Алтанширээ','Даланжаргалан','Дэлгэрэх','Замын-Үүд','Иххэт','Мандах','Өргөн','Сайхандулаан','Улаанбадрах','Хатанбулаг','Хөвсгөл','Эрдэнэ'],
            'Dornod' => ['Хэрлэн','Баян-Уул','Баяндун','Баянтүмэн','Булган','Гурванзагал','Дашбалбар','Матад','Сэргэлэн','Халхгол','Хөлөнбуйр','Цагаан-Овоо','Чойбалсан','Чулуунхороот'],
            'Dundgobi' => ['Мандалговь','Адаацаг','Баянжаргалан','Говь-Угтаал','Гурвансайхан','Дэлгэрхангай','Дэлгэрцогт','Дэрэн','Луус','Өлзийт','Өндөршил','Сайнцагаан','Сайхан-Овоо','Хулд','Цагаандэлгэр'],
            'Zavkhan' => ['Улиастай','Алдархаан','Асгат','Баянтэс','Баянхайрхан','Дөрвөлжин','Завханмандал','Идэр','Их-Уул','Нөмрөг','Отгон','Сантмаргац','Сонгино','Тосонцэнгэл','Түдэвтэй','Тэлмэн','Тэс','Ургамал','Цагаанхайрхан','Цагаанчулуут','Цэцэн-Уул','Шилүүстэй','Эрдэнэхайрхан','Яруу'],
            'Orkhon' => ['Баян-Өндөр','Жаргалант'],
            'Ovorkhangai' => ['Арвайхээр','Баруунбаян-Улаан','Бат-Өлзий','Баян-Өндөр','Баянгол','Богд','Бүрд','Гучин-Ус','Есөнзүйл','Зүүнбаян-Улаан','Нарийнтээл','Өлзийт','Сант','Тарагт','Төгрөг','Уянга','Хайрхандулаан','Хархорин','Хужирт'],
            'Omnogobi' => ['Даланзадгад','Баян-Овоо','Баяндалай','Булган','Гурвантэс','Мандал-Овоо','Манлай','Номгон','Ноён','Сэврэй','Ханбогд','Ханхонгор','Хүрмэн','Цогт-Овоо','Цогтцэций'],
            'Sukhbaatar-aimag' => ['Баруун-Урт','Асгат','Баяндэлгэр','Дарьганга','Мөнххаан','Наран','Онгон','Сүхбаатар','Түвшинширээ','Түмэнцогт','Уулбаян','Халзан','Эрдэнэцагаан'],
            'Selenge' => ['Сүхбаатар','Алтанбулаг','Баруунбүрэн','Баянгол','Ерөө','Жавхлант','Зүүнбүрэн','Мандал','Орхон','Орхонтуул','Сайхан','Сант','Түшиг','Хүдэр','Хушаат','Цагааннуур','Шаамар'],
            'Tuv' => ['Зуунмод','Алтанбулаг','Аргалант','Архуст','Батсүмбэр','Баян','Баян-Өнжүүл','Баяндэлгэр','Баянжаргалан','Баянхангай','Баянцагаан','Баянцогт','Баянчандмань','Борнуур','Бүрэн','Дэлгэрхаан','Жаргалант','Заамар','Лүн','Мөнгөнморьт','Өндөрширээт','Сэргэлэн','Угтаалцайдам','Цээл','Эрдэнэ','Эрдэнэсант','Баянбулаг'],
            'Uvs' => ['Улаангом','Баруунтуруун','Бөхмөрөн','Давст','Завхан','Зүүнговь','Зүүнхангай','Малчин','Наранбулаг','Өлгий','Өмнөговь','Өндөрхангай','Сагил','Тариалан','Тэс','Түргэн','Ховд','Хяргас','Цагаанхайрхан'],
            'Khovd' => ['Жаргалант','Алтай','Булган','Буянт','Дарви','Дөргөн','Дуут','Зэрэг','Манхан','Мөнххайрхан','Мөст','Мянгад','Үенч','Ховд','Цэцэг','Чандмань','Эрдэнэбүрэн'],
            'Khovsgol' => ['Мөрөн','Алаг-Эрдэнэ','Арбулаг','Баянзүрх','Бүрэнтогтох','Галт','Жаргалант','Их-Уул','Рашаант','Рэнчинлхүмбэ','Тариалан','Тосонцэнгэл','Төмөрбулаг','Түнэл','Улаан-Уул','Ханх','Цагааннуур','Цагаан-Уул','Цагаан-Үүр','Цэцэрлэг','Чандмань-Өндөр','Шинэ-Идэр','Эрдэнэбулган'],
            'Khentii' => ['Хэрлэн','Батноров','Батширээт','Биндэр','Бор-Өндөр','Галшар','Дадал','Дарханы','Дэлгэрхаан','Жаргалтхаан','Мөрөн','Норовлин','Өмнөдэлгэр','Цэнхэрмандал','Баян-Адарга','Баянмөнх','Баян-Овоо'],
        ];
        foreach ($aimags as $i => $a) {
            $dStmt->execute([$a[1], $a[0], count($districts) + $i + 1]);
            $districtId = $pdo->lastInsertId();
            if (!$districtId) {
                $lookup = $pdo->prepare("SELECT `id` FROM `districts` WHERE `name` = ?");
                $lookup->execute([$a[1]]);
                $districtId = $lookup->fetchColumn();
            }
            if ($districtId && isset($aimagSoums[$a[1]])) {
                foreach ($aimagSoums[$a[1]] as $j => $soumName) {
                    $kNameStmt->execute([$districtId, $j + 1, $soumName]);
                }
            }
        }
        $log[] = '✓ Дүүрэг, хороод, аймгууд үүсгэсэн';

        // ==================== Update database.php ====================
        $dbConfigPath = __DIR__ . '/config/database.php';
        $expHost = var_export($dbHost, true);
        $expName = var_export($dbName, true);
        $expUser = var_export($dbUser, true);
        $expPass = var_export($dbPass, true);
        $dbConfig = <<<PHP
<?php
function getDB(): PDO {
    static \$db = null;
    if (\$db) return \$db;
    \$db = new PDO(
        'mysql:host=' . (\$_ENV['ESHOP_DB_HOST'] ?? {$expHost}) . ';dbname=' . (\$_ENV['ESHOP_DB_NAME'] ?? {$expName}) . ';charset=utf8mb4',
        \$_ENV['ESHOP_DB_USER'] ?? {$expUser},
        \$_ENV['ESHOP_DB_PASS'] ?? {$expPass},
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return \$db;
}

PHP;
        file_put_contents($dbConfigPath, $dbConfig);
        $log[] = '✓ database.php тохируулсан';

        // ==================== Lock (atomic) =========================
        $fp = fopen($lockFile, 'x');
        if ($fp === false) {
            echo json_encode(['error' => 'Аль хэдийн суулгасан байна (lock file).']);
            exit;
        }
        fwrite($fp, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'site_name' => $siteName,
        ]));
        fclose($fp);

        echo json_encode(['success' => true, 'log' => $log]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'MySQL алдаа: ' . $e->getMessage(), 'log' => $log]);
    }
    exit;
}

// ── Already installed? ──
if (file_exists($lockFile)) {
    ?><!DOCTYPE html>
    <html lang="mn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Суулгасан байна</title>
    <script src="https://cdn.tailwindcss.com"></script>
    </head><body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Аль хэдийн суулгасан</h1>
        <p class="text-gray-500 mb-6">Систем амжилттай суулгагдсан байна.</p>
        <div class="flex gap-3">
            <a href="index.php" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition">Админ панел</a>
            <a href="/" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">Дэлгүүр</a>
        </div>
        <p class="text-xs text-gray-400 mt-4">Дахин суулгах бол: <code>.installed</code> файлыг устгана уу</p>
    </div></body></html><?php
    exit;
}
?><!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Runners World — Суулгах</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .step { display: none; }
        .step.active { display: block; }
        .animate-spin { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">

<div class="bg-white rounded-2xl shadow-xl p-8 max-w-lg w-full">
    <!-- Header -->
    <div class="text-center mb-8">
        <div class="w-16 h-16 bg-blue-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl font-bold">N</div>
        <h1 class="text-2xl font-bold text-gray-900">Системийн суулгалт</h1>
        <p class="text-gray-500 text-sm mt-1">Шаардлагатай мэдээллийг бөглөнө үү</p>
    </div>

    <!-- Progress -->
    <div class="flex items-center justify-center gap-2 mb-8" id="progress">
        <div class="w-3 h-3 rounded-full bg-blue-600" data-step="1"></div>
        <div class="w-8 h-0.5 bg-gray-200" data-line="1"></div>
        <div class="w-3 h-3 rounded-full bg-gray-300" data-step="2"></div>
        <div class="w-8 h-0.5 bg-gray-200" data-line="2"></div>
        <div class="w-3 h-3 rounded-full bg-gray-300" data-step="3"></div>
    </div>

    <!-- Step 1: Database -->
    <div class="step active" id="step1">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <span class="text-blue-600">1.</span> Мэдээллийн сан
        </h2>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">MySQL Host</label>
                    <input type="text" id="db_host" value="localhost" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DB нэр</label>
                    <input type="text" id="db_name" value="rw" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Хэрэглэгч</label>
                    <input type="text" id="db_user" value="root" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                    <input type="password" id="db_pass" value="" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
        </div>
        <button onclick="goStep(2)" class="w-full mt-6 bg-blue-600 text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition">Дараах &rarr;</button>
    </div>

    <!-- Step 2: Admin -->
    <div class="step" id="step2">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <span class="text-blue-600">2.</span> Админ хэрэглэгч
        </h2>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нэвтрэх нэр *</label>
                    <input type="text" id="admin_user" value="admin" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нэр</label>
                    <input type="text" id="admin_name" value="" placeholder="Админ" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Нууц үг * <span class="text-gray-400 font-normal">(6+ тэмдэгт)</span></label>
                <input type="password" id="admin_pass" value="" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="goStep(1)" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">&larr; Өмнөх</button>
            <button onclick="goStep(3)" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition">Дараах &rarr;</button>
        </div>
    </div>

    <!-- Step 3: Site Info -->
    <div class="step" id="step3">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <span class="text-blue-600">3.</span> Дэлгүүрийн мэдээлэл
        </h2>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дэлгүүрийн нэр</label>
                    <input type="text" id="site_name" value="Runners World" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нэр (Монгол)</label>
                    <input type="text" id="site_name_mn" value="Runners World" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Слоган</label>
                <input type="text" id="site_slogan" value="Чанартай бараа, хямд үнэ" placeholder="Жиш: Чанартай бараа, хямд үнэ" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Утас</label>
                    <input type="text" id="phone" value="77117711" placeholder="99999999" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">И-мэйл</label>
                    <input type="email" id="email" value="info@runnersworld.mn" placeholder="info@runnersworld.mn" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="goStep(2)" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">&larr; Өмнөх</button>
            <button onclick="runInstall()" id="installBtn" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-semibold hover:bg-green-700 transition">
                Суулгах &#10003;
            </button>
        </div>
    </div>

    <!-- Step 4: Result -->
    <div class="step" id="step4">
        <div id="resultArea"></div>
    </div>

    <!-- CSRF Token -->
    <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <!-- Error -->
    <div id="errorMsg" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
</div>

<script>
let currentStep = 1;

function goStep(n) {
    if (n === 2 && currentStep === 1) {
        if (!document.getElementById('db_host').value.trim() || !document.getElementById('db_name').value.trim()) {
            showError('MySQL host болон DB нэр шаардлагатай');
            return;
        }
    }
    if (n === 3 && currentStep === 2) {
        if (!document.getElementById('admin_user').value.trim()) {
            showError('Админ нэвтрэх нэр шаардлагатай');
            return;
        }
        if (document.getElementById('admin_pass').value.length < 6) {
            showError('Нууц үг 6-с дээш тэмдэгт байх ёстой');
            return;
        }
    }
    hideError();
    document.querySelectorAll('.step').forEach(function(s) { s.classList.remove('active'); });
    document.getElementById('step' + n).classList.add('active');

    for (var i = 1; i <= 3; i++) {
        var dot = document.querySelector('[data-step="' + i + '"]');
        dot.className = 'w-3 h-3 rounded-full ' + (i <= n ? 'bg-blue-600' : 'bg-gray-300');
    }
    for (var j = 1; j <= 2; j++) {
        var line = document.querySelector('[data-line="' + j + '"]');
        line.className = 'w-8 h-0.5 ' + (j < n ? 'bg-blue-600' : 'bg-gray-200');
    }
    currentStep = n;
}

function showError(msg) {
    var el = document.getElementById('errorMsg');
    el.textContent = msg;
    el.classList.remove('hidden');
}
function hideError() {
    document.getElementById('errorMsg').classList.add('hidden');
}

function runInstall() {
    var btn = document.getElementById('installBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin inline w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Суулгаж байна...';
    hideError();

    var data = new FormData();
    data.append('action', 'install');
    data.append('csrf_token', document.getElementById('csrf_token').value);
    var ids = ['db_host','db_user','db_pass','db_name','admin_user','admin_pass','admin_name','site_name','site_name_mn','site_slogan','phone','email'];
    for (var i = 0; i < ids.length; i++) {
        data.append(ids[i], document.getElementById(ids[i]).value);
    }

    fetch('install.php', { method: 'POST', body: data })
    .then(function(res) { return res.json(); })
    .then(function(result) {
        if (result.error) {
            showError(result.error);
            btn.disabled = false;
            btn.textContent = 'Суулгах \u2713';
            return;
        }

        var logContainer = document.createElement('div');
        logContainer.className = 'bg-gray-50 rounded-lg p-4 mb-4 text-left space-y-1';
        if (result.log) {
            for (var i = 0; i < result.log.length; i++) {
                var logLine = document.createElement('div');
                logLine.className = 'text-sm text-gray-600';
                logLine.textContent = result.log[i];
                logContainer.appendChild(logLine);
            }
        }
        document.getElementById('resultArea').innerHTML =
            '<div class="text-center">' +
            '<div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"><svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>' +
            '<h2 class="text-xl font-bold text-gray-900 mb-2">Амжилттай суулгалаа!</h2>' +
            '<div id="logContainer"></div>' +
            '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left">' +
            '<p class="text-sm font-medium text-blue-900 mb-1">Дараагийн алхам:</p>' +
            '<ul class="text-sm text-blue-800 space-y-1">' +
            '<li>1. Админ &rarr; Дэлгүүр нэмэх</li>' +
            '<li>2. Админ &rarr; Ангилал нэмэх</li>' +
            '<li>3. Админ &rarr; Бүтээгдэхүүн нэмэх</li>' +
            '<li>4. Тохиргоо &rarr; Лого, QPay тохируулах</li>' +
            '</ul></div>' +
            '<div class="flex gap-3">' +
            '<a href="index.php" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition">Админ панел &rarr;</a>' +
            '<a href="/" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">Дэлгүүр харах</a>' +
            '</div></div>';
        document.getElementById('logContainer').replaceWith(logContainer);
        goStep(4);
    })
    .catch(function(e) {
        showError('Сервертэй холбогдож чадсангүй: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Суулгах \u2713';
    });
}
</script>

</body>
</html>
