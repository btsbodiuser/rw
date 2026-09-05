<?php
/**
 * Runners World — Database Migration Runner
 * 
 * Safe upgrade for live databases. Run via browser or CLI:
 *   Browser:  https://yourdomain.com/backend/migrate.php
 *   CLI:      php backend/migrate.php
 * 
 * Each migration runs only once and is recorded in the `migrations` table.
 * All migrations use IF NOT EXISTS / column-existence checks → safe to re-run.
 */
require_once __DIR__ . '/config/database.php';

$db = getDB();
$isCli = php_sapi_name() === 'cli';

// ── Ensure migrations tracking table exists ──
$db->exec("CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── Helper: check if a migration was already applied ──
function migrationApplied(PDO $db, string $name): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM `migrations` WHERE `name` = ?");
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn() > 0;
}

// ── Helper: record a migration as applied ──
function recordMigration(PDO $db, string $name): void {
    $stmt = $db->prepare("INSERT INTO `migrations` (`name`) VALUES (?)");
    $stmt->execute([$name]);
}

// ── Helper: check if a column exists ──
function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

// ── Helper: check if a table exists ──
function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

// ── Helper: check if an index exists ──
function indexExists(PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

// ══════════════════════════════════════════════════════════════
//  MIGRATIONS — Add new migrations at the bottom of this array
// ══════════════════════════════════════════════════════════════
$migrations = [];

// ──────────────────────────────────────────────────────────────
// Migration 001: Bring old DB up to current schema
// This covers ALL tables/columns that install.php defines.
// Safe on fresh installs too (uses IF NOT EXISTS everywhere).
// ──────────────────────────────────────────────────────────────
$migrations['001_full_schema_sync'] = function (PDO $db) {

    // ── Table: media ──
    $db->exec("CREATE TABLE IF NOT EXISTS `media` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `filename` VARCHAR(255) NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `mime_type` VARCHAR(50) NOT NULL,
        `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
        `alt_text` VARCHAR(255) DEFAULT '',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Table: cargo_batches ──
    $db->exec("CREATE TABLE IF NOT EXISTS `cargo_batches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `due_date` DATETIME NOT NULL,
        `status` ENUM('open','closed','shipping','arrived') DEFAULT 'open',
        `cargo_rate_per_kg` DECIMAL(10,2) NOT NULL,
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Table: khoroos ──
    $db->exec("CREATE TABLE IF NOT EXISTS `khoroos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `district_id` INT NOT NULL,
        `number` INT NOT NULL,
        `name` VARCHAR(100),
        FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Table: customers ──
    $db->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `phone` VARCHAR(20) UNIQUE NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `name` VARCHAR(100) DEFAULT '',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Table: otp_codes ──
    $db->exec("CREATE TABLE IF NOT EXISTS `otp_codes` (
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

    // ── Table: customer_sessions ──
    $db->exec("CREATE TABLE IF NOT EXISTS `customer_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT NOT NULL,
        `token` VARCHAR(64) UNIQUE NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
        INDEX `idx_token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Table: customer_addresses ──
    $db->exec("CREATE TABLE IF NOT EXISTS `customer_addresses` (
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

    // ── Table: audit_log ──
    $db->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
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

    // ── Table: shop_categories (if missing) ──
    $db->exec("CREATE TABLE IF NOT EXISTS `shop_categories` (
        `shop_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        PRIMARY KEY (`shop_id`, `category_id`),
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Products: new columns ──
    if (!columnExists($db, 'products', 'barcode')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `barcode` VARCHAR(50) NULL AFTER `weight_kg`");
    }
    if (!columnExists($db, 'products', 'main_image_id')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `main_image_id` INT NULL AFTER `image`");
    }
    if (!columnExists($db, 'products', 'image_ids')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `image_ids` JSON NULL AFTER `main_image_id`");
    }
    if (!columnExists($db, 'products', 'preorder_date')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `preorder_date` DATE NULL AFTER `stock`");
    }
    if (!columnExists($db, 'products', 'is_active')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `reviews`");
    }
    if (!columnExists($db, 'products', 'updated_at')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    if (!indexExists($db, 'products', 'idx_products_barcode')) {
        $db->exec("ALTER TABLE `products` ADD INDEX `idx_products_barcode` (`barcode`)");
    }

    // ── Orders: new columns ──
    if (!columnExists($db, 'orders', 'customer_id')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `customer_id` INT NULL AFTER `id`");
    }
    if (!columnExists($db, 'orders', 'order_type')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `order_type` ENUM('pos','online') NOT NULL DEFAULT 'online' AFTER `order_number`");
    }
    if (!columnExists($db, 'orders', 'fulfillment')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `fulfillment` ENUM('pickup','delivery') DEFAULT 'delivery' AFTER `order_type`");
    }
    if (!columnExists($db, 'orders', 'khoroo_id')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `khoroo_id` INT NULL AFTER `district_id`");
    }
    if (!columnExists($db, 'orders', 'detail_address')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `detail_address` TEXT NULL AFTER `address`");
    }
    if (!columnExists($db, 'orders', 'cargo_fee')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `cargo_fee` DECIMAL(10,2) DEFAULT 0 AFTER `delivery_fee`");
    }
    if (!columnExists($db, 'orders', 'cargo_batch_id')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `cargo_batch_id` INT NULL AFTER `total`");
    }
    if (!columnExists($db, 'orders', 'payment_method')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'cash' AFTER `cargo_batch_id`");
    }
    if (!columnExists($db, 'orders', 'payment_status')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_status` ENUM('pending','paid','refunded') DEFAULT 'pending' AFTER `payment_method`");
    }
    if (!columnExists($db, 'orders', 'qpay_invoice_id')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `qpay_invoice_id` VARCHAR(100) NULL AFTER `payment_status`");
    }

    // ── Order Items: new columns ──
    if (!columnExists($db, 'order_items', 'weight_kg')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `weight_kg` DECIMAL(8,3) NULL AFTER `quantity`");
    }
    if (!columnExists($db, 'order_items', 'cargo_fee')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `cargo_fee` DECIMAL(10,2) DEFAULT 0 AFTER `weight_kg`");
    }
    if (!columnExists($db, 'order_items', 'line_total')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `line_total` DECIMAL(12,2) NULL AFTER `quantity`");
    }

    // ── Categories: new columns ──
    if (!columnExists($db, 'categories', 'image')) {
        $db->exec("ALTER TABLE `categories` ADD COLUMN `image` VARCHAR(255) NULL AFTER `icon`");
    }

    // ── Admins: role column (if old schema had simpler role) ──
    // Ensure role enum includes pos_cashier
    if (columnExists($db, 'admins', 'role')) {
        try {
            $db->exec("ALTER TABLE `admins` MODIFY COLUMN `role` ENUM('super_admin','admin','pos_cashier') NOT NULL DEFAULT 'admin'");
        } catch (Exception $e) {
            // Already correct enum, ignore
        }
    }

    // ── Orders: expand status enum if needed ──
    try {
        $db->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','confirmed','cargo_shipping','cargo_arrived','ready_pickup','delivering','delivered','picked_up','completed','cancelled') DEFAULT 'pending'");
    } catch (Exception $e) {
        // Already correct
    }

    // ── Settings: is_public column ──
    if (!columnExists($db, 'settings', 'is_public')) {
        $db->exec("ALTER TABLE `settings` ADD COLUMN `is_public` TINYINT(1) DEFAULT 1");
    }

    // ── Seed: new settings (INSERT IGNORE = won't overwrite existing) ──
    $newSettings = [
        ['cargo_rate_per_kg', '8000', 'Cargo Rate per KG', 'number', 1],
        ['hero_title', 'Чанартай бүтээгдэхүүн', 'Hero Title', 'text', 1],
        ['hero_subtitle', 'Танай гэрт хүргэнэ', 'Hero Subtitle', 'text', 1],
        ['hero_description', '', 'Hero Description', 'text', 1],
        ['hero_btn1_text', 'Бүх бараа үзэх', 'Hero Button 1 Text', 'text', 1],
        ['hero_btn2_text', 'Урьдчилсан захиалга', 'Hero Button 2 Text', 'text', 1],
        ['shops_title', '', 'Shops Section Title', 'text', 1],
        ['shops_description', '', 'Shops Section Description', 'text', 1],
        ['feature1_title', '', 'Feature 1 Title', 'text', 1],
        ['feature1_desc', '', 'Feature 1 Description', 'text', 1],
        ['feature2_title', '', 'Feature 2 Title', 'text', 1],
        ['feature2_desc', '', 'Feature 2 Description', 'text', 1],
        ['feature3_title', '', 'Feature 3 Title', 'text', 1],
        ['feature3_desc', '', 'Feature 3 Description', 'text', 1],
        ['top_bar_text', '', 'Top Bar Text', 'text', 1],
        ['top_bar_text_short', '', 'Top Bar Text (Short)', 'text', 1],
        ['home_categories_enabled', '1', 'Ангиллын хэсгийг харуулах', 'boolean', 1],
        ['home_category_ready_enabled', '1', 'Бэлэн бараа картыг харуулах', 'boolean', 1],
        ['home_category_preorder_enabled', '1', 'Урьдчилсан захиалга картыг харуулах', 'boolean', 1],
        ['home_category_discount_enabled', '1', 'Хямдрал картыг харуулах', 'boolean', 1],
        ['home_category_new_enabled', '1', 'Шинэ бараа картыг харуулах', 'boolean', 1],
        ['home_category_ready_image', '', 'Бэлэн бараа картны зураг', 'text', 1],
        ['home_category_preorder_image', '', 'Урьдчилсан захиалга картны зураг', 'text', 1],
        ['home_category_discount_image', '', 'Хямдрал картны зураг', 'text', 1],
        ['home_category_new_image', '', 'Шинэ бараа картны зураг', 'text', 1],
    ];
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    foreach ($newSettings as $s) { $sStmt->execute($s); }

    $privateSettings = [
        ['messagepro_api_key', '', 'MessagePro API Key', 'text', 0],
        ['messagepro_from_number', '', 'MessagePro From Number', 'text', 0],
        ['qpay_username', '', 'QPay Username', 'text', 0],
        ['qpay_password', '', 'QPay Password', 'text', 0],
        ['qpay_invoice_code', '', 'QPay Invoice Code', 'text', 0],
        ['smtp_host', '', 'SMTP Host', 'text', 0],
        ['smtp_port', '587', 'SMTP Port', 'number', 0],
        ['smtp_username', '', 'SMTP Username', 'text', 0],
        ['smtp_password', '', 'SMTP Password', 'text', 0],
        ['smtp_from_email', '', 'SMTP From Email', 'text', 0],
        ['smtp_from_name', '', 'SMTP From Name', 'text', 0],
        ['smtp_encryption', 'tls', 'SMTP Encryption (tls/ssl)', 'text', 0],
    ];
    foreach ($privateSettings as $ps) { $sStmt->execute($ps); }

    // ── Seed: khoroos for existing districts (if khoroos table was just created) ──
    $existingKhoroos = $db->query("SELECT COUNT(*) FROM `khoroos`")->fetchColumn();
    if ((int) $existingKhoroos === 0) {
        $districtKhoroos = [
            'Bayangol' => range(1, 23),
            'Bayanzurkh' => range(1, 28),
            'Chingeltei' => range(1, 19),
            'Sukhbaatar' => range(1, 20),
            'Khan-Uul' => range(1, 22),
            'Songinokhairkhan' => range(1, 32),
        ];
        $kStmt = $db->prepare("INSERT IGNORE INTO `khoroos` (`district_id`, `number`) VALUES (?, ?)");
        foreach ($districtKhoroos as $districtName => $khorooNumbers) {
            $dStmt = $db->prepare("SELECT `id` FROM `districts` WHERE `name` = ?");
            $dStmt->execute([$districtName]);
            $districtId = $dStmt->fetchColumn();
            if ($districtId) {
                foreach ($khorooNumbers as $num) {
                    $kStmt->execute([$districtId, $num]);
                }
            }
        }
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 002: Add OTP fields (if otp_codes existed before without them)
// ──────────────────────────────────────────────────────────────
$migrations['002_otp_extra_fields'] = function (PDO $db) {
    if (!tableExists($db, 'otp_codes')) return;

    if (!columnExists($db, 'otp_codes', 'verify_attempts')) {
        $db->exec("ALTER TABLE `otp_codes` ADD COLUMN `verify_attempts` INT NOT NULL DEFAULT 0 AFTER `is_used`");
    }
    if (!columnExists($db, 'otp_codes', 'otp_token')) {
        $db->exec("ALTER TABLE `otp_codes` ADD COLUMN `otp_token` VARCHAR(64) NULL AFTER `verify_attempts`");
    }
    if (!columnExists($db, 'otp_codes', 'otp_token_expires')) {
        $db->exec("ALTER TABLE `otp_codes` ADD COLUMN `otp_token_expires` DATETIME NULL AFTER `otp_token`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 003: Delivery module tables
// ──────────────────────────────────────────────────────────────
$migrations['003_delivery_module'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `delivery_drivers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `deliveries` (
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
};

// ──────────────────────────────────────────────────────────────
// Migration 004: Remember me token for admin login
// ──────────────────────────────────────────────────────────────
$migrations['004_admin_remember_token'] = function (PDO $db) {
    if (!columnExists($db, 'admins', 'remember_token')) {
        $db->exec("ALTER TABLE `admins` ADD COLUMN `remember_token` VARCHAR(64) NULL AFTER `role`");
    }
};

// Migration 005: Security hardening — ip_address on OTP, site_url setting
// ──────────────────────────────────────────────────────────────
$migrations['005_security_hardening'] = function (PDO $db) {
    // Add ip_address column to otp_codes for per-IP rate limiting
    if (!columnExists($db, 'otp_codes', 'ip_address')) {
        $db->exec("ALTER TABLE `otp_codes` ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER `phone`");
    }

    // Add site_url setting for CORS and QPay callback
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'site_url'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("INSERT INTO `settings` (`setting_key`, `setting_value`, `type`, `label`, `is_public`) 
            VALUES ('site_url', 'https://runnersworld.mn', 'text', 'Сайтын URL', 0)");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 006: VAT support for POS sales
// ──────────────────────────────────────────────────────────────
$migrations['006_vat_support'] = function (PDO $db) {
    if (!columnExists($db, 'orders', 'vat_included')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `vat_included` TINYINT(1) NOT NULL DEFAULT 0 AFTER `total`");
    }
    if (!columnExists($db, 'orders', 'vat_amount')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `vat_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `total`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 007: Seed "Store Only" category, shop, and 10 products
// ──────────────────────────────────────────────────────────────
$migrations['007_store_only_seed'] = function (PDO $db) {
    // Insert category
    $db->prepare("INSERT IGNORE INTO `categories` (`slug`, `name`, `name_mn`, `icon`, `is_active`, `sort_order`) VALUES (?, ?, ?, ?, 1, 0)")
        ->execute(['store-only', 'Store Only', 'Зөвхөн дэлгүүр', '🏪']);

    $catId = $db->query("SELECT `id` FROM `categories` WHERE `slug` = 'store-only'")->fetchColumn();

    // Insert shop
    $db->prepare("INSERT IGNORE INTO `shops` (`slug`, `name`, `name_mn`, `description`, `description_mn`, `color`, `is_active`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, 1, 0)")
        ->execute(['store-only', 'Store Only', 'Зөвхөн дэлгүүр', 'In-store products sold via POS', 'POS-оор зарагдах дэлгүүрийн бараа', '#10B981']);

    $shopId = $db->query("SELECT `id` FROM `shops` WHERE `slug` = 'store-only'")->fetchColumn();

    // Link shop to category
    $db->prepare("INSERT IGNORE INTO `shop_categories` (`shop_id`, `category_id`) VALUES (?, ?)")
        ->execute([$shopId, $catId]);

    // Seed 10 ready products
    $products = [
        ['Цай 100гр',          'store-only-tsai',       2500,  null,   50, '1000000001'],
        ['Кофе 200гр',         'store-only-kofe',       8500,  9500,   30, '1000000002'],
        ['Сүү 1л',             'store-only-suu',        3500,  null,   100, '1000000003'],
        ['Талх',               'store-only-talkh',      2000,  null,   80, '1000000004'],
        ['Чихэр 250гр',       'store-only-chikher',    4500,  5000,   60, '1000000005'],
        ['Жимс (Алим) 1кг',   'store-only-alim',       6000,  null,   40, '1000000006'],
        ['Өндөг 10ш',         'store-only-undug',      5500,  null,   45, '1000000007'],
        ['Гурил 1кг',         'store-only-guril',      3000,  null,   70, '1000000008'],
        ['Сахар 1кг',         'store-only-sakhar',     3200,  null,   65, '1000000009'],
        ['Ус 1.5л',           'store-only-us',         1500,  null,   120, '1000000010'],
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO `products` (`name`, `name_mn`, `slug`, `category_id`, `shop_id`, `type`, `price`, `original_price`, `stock`, `barcode`, `is_active`) 
        VALUES (?, ?, ?, ?, ?, 'ready', ?, ?, ?, ?, 1)");

    foreach ($products as $p) {
        $stmt->execute([
            $p[0],      // name (same as name_mn for store products)
            $p[0],      // name_mn
            $p[1],      // slug
            $catId,     // category_id
            $shopId,    // shop_id
            $p[2],      // price
            $p[3],      // original_price
            $p[4],      // stock
            $p[5],      // barcode
        ]);
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 008: Add show_in_store flag to products
// ──────────────────────────────────────────────────────────────
$migrations['008_product_show_in_store'] = function (PDO $db) {
    if (!columnExists($db, 'products', 'show_in_store')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `show_in_store` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 009: Payment reconciliation tables
// ──────────────────────────────────────────────────────────────
$migrations['009_payment_reconciliation'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `bank_statement_imports` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `filename` VARCHAR(255) NOT NULL,
        `account_number` VARCHAR(50) DEFAULT NULL,
        `period_from` DATE DEFAULT NULL,
        `period_to` DATE DEFAULT NULL,
        `total_transactions` INT NOT NULL DEFAULT 0,
        `qpay_transactions` INT NOT NULL DEFAULT 0,
        `matched_transactions` INT NOT NULL DEFAULT 0,
        `unmatched_transactions` INT NOT NULL DEFAULT 0,
        `imported_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `bank_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `import_id` INT NOT NULL,
        `transaction_date` DATETIME NOT NULL,
        `credit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
        `debit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
        `description` TEXT,
        `counter_account` VARCHAR(50) DEFAULT NULL,
        `is_qpay` TINYINT(1) NOT NULL DEFAULT 0,
        `qpay_ref` VARCHAR(50) DEFAULT NULL,
        `order_number` VARCHAR(20) DEFAULT NULL,
        `qpay_charge` DECIMAL(10,2) DEFAULT NULL,
        `order_id` INT DEFAULT NULL,
        `match_status` ENUM('matched','unmatched','amount_mismatch','no_order') NOT NULL DEFAULT 'unmatched',
        `order_total` DECIMAL(12,2) DEFAULT NULL,
        `expected_amount` DECIMAL(12,2) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `import_id` (`import_id`),
        KEY `order_number` (`order_number`),
        KEY `order_id` (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

// ──────────────────────────────────────────────────────────────
// Migration 010: Product entry permissions for customers
// ──────────────────────────────────────────────────────────────
$migrations['010_product_entry_permissions'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `product_entry_permissions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT NOT NULL,
        `granted_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_customer` (`customer_id`),
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`granted_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!columnExists($db, 'products', 'created_by_customer_id')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `created_by_customer_id` INT NULL AFTER `show_in_store`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 011: Add line_total to order_items (POS custom pricing)
// ──────────────────────────────────────────────────────────────
$migrations['011_order_items_line_total'] = function (PDO $db) {
    if (!columnExists($db, 'order_items', 'line_total')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `line_total` DECIMAL(12,2) NULL AFTER `quantity`");
    }
};

$migrations['012_products_order_status'] = function (PDO $db) {
    if (!columnExists($db, 'products', 'order_status')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `order_status` ENUM('open','closed') DEFAULT 'open' AFTER `preorder_date`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 013: Product→Batch link & item-level cargo tracking
//   - products.cargo_batch_id  (which batch this preorder product belongs to)
//   - order_items.cargo_batch_id  (snapshot at order time)
//   - order_items.cargo_status  (per-item cargo state)
// ──────────────────────────────────────────────────────────────
$migrations['013_product_batch_link'] = function (PDO $db) {
    // Product → batch
    if (!columnExists($db, 'products', 'cargo_batch_id')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `cargo_batch_id` INT NULL AFTER `order_status`");
        $db->exec("ALTER TABLE `products` ADD INDEX `idx_products_cargo_batch` (`cargo_batch_id`)");
    }

    // Order item → batch + per-item cargo status
    if (!columnExists($db, 'order_items', 'cargo_batch_id')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `cargo_batch_id` INT NULL AFTER `cargo_fee`");
        $db->exec("ALTER TABLE `order_items` ADD INDEX `idx_oi_cargo_batch` (`cargo_batch_id`)");
    }
    if (!columnExists($db, 'order_items', 'cargo_status')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `cargo_status` ENUM('pending','shipping','arrived') NULL AFTER `cargo_batch_id`");
    }

    // Back-fill existing order_items: copy cargo_batch_id from the parent order
    $db->exec("
        UPDATE order_items oi
        JOIN orders o ON oi.order_id = o.id
        SET oi.cargo_batch_id = o.cargo_batch_id,
            oi.cargo_status = CASE
                WHEN o.status IN ('cargo_arrived','ready_pickup','delivering','delivered','picked_up','completed') THEN 'arrived'
                WHEN o.status = 'cargo_shipping' THEN 'shipping'
                WHEN o.cargo_batch_id IS NOT NULL THEN 'pending'
                ELSE NULL
            END
        WHERE o.cargo_batch_id IS NOT NULL AND oi.cargo_batch_id IS NULL
    ");
};

// ── 014: Assign preorder products to latest open batch ──
$migrations['014_preorder_assign_latest_batch'] = function (PDO $db) {
    // Find the latest open batch
    $stmt = $db->query("SELECT id FROM cargo_batches WHERE status = 'open' ORDER BY id DESC LIMIT 1");
    $latestBatchId = $stmt->fetchColumn();

    if ($latestBatchId) {
        // Assign preorder products that have no batch yet
        $upd = $db->prepare("
            UPDATE products
            SET cargo_batch_id = ?
            WHERE type = 'preorder'
              AND cargo_batch_id IS NULL
              AND is_active = 1
        ");
        $upd->execute([$latestBatchId]);
    }
};

$migrations['015_split_payment'] = function (PDO $db) {
    if (!columnExists($db, 'orders', 'payment_cash')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_cash` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `payment_method`");
    }
    if (!columnExists($db, 'orders', 'payment_card')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_card` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `payment_cash`");
    }
    if (!columnExists($db, 'orders', 'payment_transfer')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_transfer` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `payment_card`");
    }
};

$migrations['016_backfill_payment_columns'] = function (PDO $db) {
    $db->exec("UPDATE orders SET payment_cash = total WHERE payment_method = 'cash' AND payment_cash = 0 AND payment_card = 0 AND payment_transfer = 0 AND total > 0");
    $db->exec("UPDATE orders SET payment_card = total WHERE payment_method = 'card' AND payment_cash = 0 AND payment_card = 0 AND payment_transfer = 0 AND total > 0");
    $db->exec("UPDATE orders SET payment_transfer = total WHERE payment_method = 'transfer' AND payment_cash = 0 AND payment_card = 0 AND payment_transfer = 0 AND total > 0");
};

// ──────────────────────────────────────────────────────────────
// Migration 017: Add hide_cargo_fee flag to products
// Allows admin to control per-product whether cargo fee is shown on frontend
// ──────────────────────────────────────────────────────────────
$migrations['017_product_hide_cargo_fee'] = function (PDO $db) {
    if (!columnExists($db, 'products', 'hide_cargo_fee')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `hide_cargo_fee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `show_in_store`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 018: Add cargo_fee_paid to orders
// Tracks whether cargo fee has been collected (paid on arrival)
// ──────────────────────────────────────────────────────────────
$migrations['018_order_cargo_fee_paid'] = function (PDO $db) {
    if (!columnExists($db, 'orders', 'cargo_fee_paid')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `cargo_fee_paid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cargo_fee`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 019: Add cargo_fee_paid to order_items
// Per-item tracking of cargo fee payment (some items paid, some not)
// ──────────────────────────────────────────────────────────────
$migrations['019_order_items_cargo_fee_paid'] = function (PDO $db) {
    if (!columnExists($db, 'order_items', 'cargo_fee_paid')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `cargo_fee_paid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cargo_status`");
    }
    // Back-fill from order-level cargo_fee_paid
    $db->exec("
        UPDATE order_items oi
        JOIN orders o ON oi.order_id = o.id
        SET oi.cargo_fee_paid = o.cargo_fee_paid
        WHERE oi.cargo_fee > 0
    ");
};

// ──────────────────────────────────────────────────────────────
// Migration 020: Add hide_cargo_fee snapshot to order_items
// Tracks which items had visible cargo fees included in QPay total
// ──────────────────────────────────────────────────────────────
$migrations['020_order_items_hide_cargo_fee'] = function (PDO $db) {
    if (!columnExists($db, 'order_items', 'hide_cargo_fee')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `hide_cargo_fee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cargo_fee_paid`");
    }
    // Back-fill: mark paid items with visible cargo fee that were already paid via QPay
    $db->exec("
        UPDATE order_items oi
        JOIN orders o ON oi.order_id = o.id
        SET oi.cargo_fee_paid = 1
        WHERE oi.cargo_fee > 0 AND oi.hide_cargo_fee = 0
          AND o.payment_status = 'paid' AND o.payment_method = 'qpay'
    ");
    // Re-sync order-level cargo_fee_paid
    $db->exec("
        UPDATE orders o
        SET o.cargo_fee_paid = (
            SELECT CASE WHEN COUNT(*) = 0 THEN 0
                        WHEN SUM(CASE WHEN oi2.cargo_fee_paid = 0 THEN 1 ELSE 0 END) = 0 THEN 1
                        ELSE 0 END
            FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.cargo_fee > 0
        )
        WHERE o.cargo_fee > 0
    ");
};

// ──────────────────────────────────────────────────────────────
// Migration 021: Mark cargo_fee_paid=1 for preorder products with visible cargo fee
// Products with hide_cargo_fee=0 and type=preorder → their order_items get cargo_fee_paid=1
// Then re-sync order-level cargo_fee_paid accordingly
// ──────────────────────────────────────────────────────────────
$migrations['021_preorder_visible_cargo_fee_paid'] = function (PDO $db) {
    // Update order_items: set cargo_fee_paid=1 where the product is preorder with visible cargo fee
    $db->exec("
        UPDATE order_items oi
        JOIN products p ON oi.product_id = p.id
        SET oi.cargo_fee_paid = 1
        WHERE p.type = 'preorder'
          AND p.hide_cargo_fee = 0
          AND oi.cargo_fee > 0
          AND oi.cargo_fee_paid = 0
    ");

    // Re-sync order-level cargo_fee_paid: 1 if ALL cargo items paid, 0 otherwise
    $db->exec("
        UPDATE orders o
        SET o.cargo_fee_paid = (
            SELECT CASE WHEN COUNT(*) = 0 THEN o.cargo_fee_paid
                        WHEN SUM(CASE WHEN oi2.cargo_fee_paid = 0 THEN 1 ELSE 0 END) = 0 THEN 1
                        ELSE 0 END
            FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.cargo_fee > 0
        )
        WHERE o.cargo_fee > 0
    ");
};

// ──────────────────────────────────────────────────────────────
// Migration 022: Re-run cargo_fee_paid sync using products.hide_cargo_fee
// ──────────────────────────────────────────────────────────────
$migrations['022_resync_cargo_fee_paid'] = function (PDO $db) {
    // Mark cargo_fee_paid=1 for preorder items with visible cargo fee
    $db->exec("
        UPDATE order_items oi
        JOIN products p ON oi.product_id = p.id
        SET oi.cargo_fee_paid = 1
        WHERE p.type = 'preorder'
          AND p.hide_cargo_fee = 0
          AND oi.cargo_fee > 0
          AND oi.cargo_fee_paid = 0
    ");

    // Re-sync order-level cargo_fee_paid
    $db->exec("
        UPDATE orders o
        SET o.cargo_fee_paid = (
            SELECT CASE WHEN COUNT(*) = 0 THEN o.cargo_fee_paid
                        WHEN SUM(CASE WHEN oi2.cargo_fee_paid = 0 THEN 1 ELSE 0 END) = 0 THEN 1
                        ELSE 0 END
            FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.cargo_fee > 0
        )
        WHERE o.cargo_fee > 0
    ");
};

// ──────────────────────────────────────────────────────────────
// Migration 023: Add payment_transfer_nomin column to orders
// New payment method: Nomin transfer (separate from bank transfer)
// ──────────────────────────────────────────────────────────────
$migrations['023_payment_transfer_nomin'] = function (PDO $db) {
    if (!columnExists($db, 'orders', 'payment_transfer_nomin')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_transfer_nomin` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `payment_transfer`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 024: Add sms_sent_at to cargo_batches
// ──────────────────────────────────────────────────────────────
$migrations['024_cargo_batch_sms_sent_at'] = function (PDO $db) {
    if (!columnExists($db, 'cargo_batches', 'sms_sent_at')) {
        $db->exec("ALTER TABLE `cargo_batches` ADD COLUMN `sms_sent_at` DATETIME NULL AFTER `notes`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 025: Add 3 remaining UB districts + 21 aimags
// ──────────────────────────────────────────────────────────────
$migrations['025_add_districts_aimags'] = function (PDO $db) {
    $dStmt = $db->prepare("INSERT IGNORE INTO `districts` (`name`, `name_mn`, `sort_order`) VALUES (?, ?, ?)");
    $kStmt = $db->prepare("INSERT IGNORE INTO `khoroos` (`district_id`, `number`) VALUES (?, ?)");

    // 3 remaining Ulaanbaatar districts with khoroos
    $newDistricts = [
        ['Nalaikh',    'Налайх',    range(1, 7), 7],
        ['Baganuur',   'Багануур',  range(1, 5), 8],
        ['Bagakhangai','Багахангай', range(1, 2), 9],
    ];

    foreach ($newDistricts as $d) {
        $dStmt->execute([$d[0], $d[1], $d[3]]);
        $lookup = $db->prepare("SELECT `id` FROM `districts` WHERE `name` = ?");
        $lookup->execute([$d[0]]);
        $districtId = $lookup->fetchColumn();
        if ($districtId) {
            foreach ($d[2] as $khoroo) {
                $kStmt->execute([$districtId, $khoroo]);
            }
        }
    }

    // 21 aimags (no khoroos)
    $aimags = [
        ['Arkhangai',    'Архангай аймаг',    10],
        ['Bayan-Olgii',  'Баян-Өлгий аймаг',  11],
        ['Bayankhongor', 'Баянхонгор аймаг',  12],
        ['Bulgan',       'Булган аймаг',       13],
        ['Govi-Altai',   'Говь-Алтай аймаг',  14],
        ['Govisumber',   'Говьсүмбэр аймаг',  15],
        ['Darkhan-Uul',  'Дархан-Уул аймаг',  16],
        ['Dornogobi',    'Дорноговь аймаг',   17],
        ['Dornod',       'Дорнод аймаг',      18],
        ['Dundgobi',     'Дундговь аймаг',    19],
        ['Zavkhan',      'Завхан аймаг',      20],
        ['Orkhon',       'Орхон аймаг',       21],
        ['Ovorkhangai',  'Өвөрхангай аймаг',  22],
        ['Omnogobi',     'Өмнөговь аймаг',    23],
        ['Sukhbaatar-aimag', 'Сүхбаатар аймаг', 24],
        ['Selenge',      'Сэлэнгэ аймаг',    25],
        ['Tuv',          'Төв аймаг',         26],
        ['Uvs',          'Увс аймаг',         27],
        ['Khovd',        'Ховд аймаг',        28],
        ['Khovsgol',     'Хөвсгөл аймаг',    29],
        ['Khentii',      'Хэнтий аймаг',     30],
    ];

    foreach ($aimags as $a) {
        $dStmt->execute([$a[0], $a[1], $a[2]]);
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 026: Add soums for all 21 aimags into khoroos table
// ──────────────────────────────────────────────────────────────
$migrations['026_aimag_soums'] = function (PDO $db) {
    $kStmt = $db->prepare("INSERT IGNORE INTO `khoroos` (`district_id`, `number`, `name`) VALUES (?, ?, ?)");

    // Aimag name => list of soum names
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

    foreach ($aimagSoums as $aimagName => $soums) {
        $lookup = $db->prepare("SELECT `id` FROM `districts` WHERE `name` = ?");
        $lookup->execute([$aimagName]);
        $districtId = $lookup->fetchColumn();
        if ($districtId) {
            foreach ($soums as $i => $soumName) {
                $kStmt->execute([$districtId, $i + 1, $soumName]);
            }
        }
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 027: Delivery batches
// ──────────────────────────────────────────────────────────────
$migrations['027_delivery_batches'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `delivery_batches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `driver_id` INT NOT NULL,
        `status` ENUM('assigned','in_progress','completed','cancelled') DEFAULT 'assigned',
        `notes` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `completed_at` DATETIME NULL,
        FOREIGN KEY (`driver_id`) REFERENCES `delivery_drivers`(`id`) ON DELETE CASCADE,
        INDEX `idx_driver` (`driver_id`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!columnExists($db, 'deliveries', 'batch_id')) {
        $db->exec("ALTER TABLE `deliveries` ADD COLUMN `batch_id` INT NULL AFTER `id`");
        $db->exec("ALTER TABLE `deliveries` ADD INDEX `idx_batch` (`batch_id`)");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 028: Driver access tokens
// ──────────────────────────────────────────────────────────────
$migrations['028_driver_access_token'] = function (PDO $db) {
    if (!columnExists($db, 'delivery_drivers', 'access_token')) {
        $db->exec("ALTER TABLE `delivery_drivers` ADD COLUMN `access_token` VARCHAR(64) NULL AFTER `is_active`");
        $db->exec("ALTER TABLE `delivery_drivers` ADD UNIQUE INDEX `idx_access_token` (`access_token`)");
    }
    // Generate tokens for existing drivers
    $drivers = $db->query("SELECT id FROM delivery_drivers WHERE access_token IS NULL")->fetchAll();
    $stmt = $db->prepare("UPDATE delivery_drivers SET access_token = ? WHERE id = ?");
    foreach ($drivers as $d) {
        $stmt->execute([bin2hex(random_bytes(16)), $d['id']]);
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 029: Ready for delivery flag
// ──────────────────────────────────────────────────────────────
$migrations['029_ready_for_delivery'] = function (PDO $db) {
    if (!columnExists($db, 'orders', 'ready_for_delivery')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `ready_for_delivery` TINYINT(1) NOT NULL DEFAULT 0 AFTER `fulfillment`");
        $db->exec("ALTER TABLE `orders` ADD INDEX `idx_ready_delivery` (`ready_for_delivery`)");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 030: Bank transfer & payment method toggle settings
// ──────────────────────────────────────────────────────────────
$migrations['030_bank_payment_settings'] = function (PDO $db) {
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $rows = [
        ['bank_name', '', 'Банкны нэр', 'text', 1],
        ['bank_account_number', '', 'Дансны дугаар', 'text', 1],
        ['bank_account_name', '', 'Дансны нэр', 'text', 1],
        ['payment_qpay_enabled', '1', 'QPay төлбөр идэвхтэй', 'boolean', 1],
        ['payment_transfer_enabled', '1', 'Шилжүүлэг төлбөр идэвхтэй', 'boolean', 1],
    ];
    foreach ($rows as $r) { $sStmt->execute($r); }
};

$migrations['031_bank_transfer_reconciliation'] = function (PDO $db) {
    if (!columnExists($db, 'bank_transactions', 'is_transfer')) {
        $db->exec("ALTER TABLE `bank_transactions` ADD COLUMN `is_transfer` TINYINT(1) DEFAULT 0 AFTER `is_qpay`");
    }
    if (!columnExists($db, 'bank_transactions', 'match_method')) {
        $db->exec("ALTER TABLE `bank_transactions` ADD COLUMN `match_method` VARCHAR(30) DEFAULT NULL AFTER `match_status`");
    }
    // Add 'ignored' to match_status ENUM
    $db->exec("ALTER TABLE `bank_transactions` MODIFY COLUMN `match_status` ENUM('matched','unmatched','amount_mismatch','no_order','ignored') NOT NULL DEFAULT 'unmatched'");
    if (!columnExists($db, 'bank_statement_imports', 'transfer_transactions')) {
        $db->exec("ALTER TABLE `bank_statement_imports` ADD COLUMN `transfer_transactions` INT DEFAULT 0 AFTER `unmatched_transactions`");
        $db->exec("ALTER TABLE `bank_statement_imports` ADD COLUMN `transfer_matched` INT DEFAULT 0 AFTER `transfer_transactions`");
        $db->exec("ALTER TABLE `bank_statement_imports` ADD COLUMN `transfer_unmatched` INT DEFAULT 0 AFTER `transfer_matched`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 032: Product variants (sizes, colors, color images)
// ──────────────────────────────────────────────────────────────
$migrations['032_product_variants'] = function (PDO $db) {
    // ── product_colors: predefined color options ──
    $db->exec("CREATE TABLE IF NOT EXISTS `product_colors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(50) NOT NULL,
        `name_mn` VARCHAR(50) NOT NULL,
        `hex_code` VARCHAR(7) DEFAULT NULL,
        `sort_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── product_sizes: predefined size options ──
    $db->exec("CREATE TABLE IF NOT EXISTS `product_sizes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(20) NOT NULL,
        `sort_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── product_variants: each purchasable combination ──
    $db->exec("CREATE TABLE IF NOT EXISTS `product_variants` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL,
        `color_id` INT DEFAULT NULL,
        `size_id` INT DEFAULT NULL,
        `sku` VARCHAR(50) DEFAULT NULL,
        `price_override` DECIMAL(12,2) DEFAULT NULL,
        `stock` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_product_color_size` (`product_id`, `color_id`, `size_id`),
        INDEX `idx_variant_product` (`product_id`),
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`color_id`) REFERENCES `product_colors`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`size_id`) REFERENCES `product_sizes`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── product_color_images: per-color photo gallery ──
    $db->exec("CREATE TABLE IF NOT EXISTS `product_color_images` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL,
        `color_id` INT NOT NULL,
        `media_id` INT NOT NULL,
        `sort_order` INT DEFAULT 0,
        INDEX `idx_pci_product_color` (`product_id`, `color_id`),
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`color_id`) REFERENCES `product_colors`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── products: has_variants flag ──
    if (!columnExists($db, 'products', 'has_variants')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `has_variants` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stock`");
    }

    // ── order_items: variant tracking ──
    if (!columnExists($db, 'order_items', 'variant_id')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `variant_id` INT DEFAULT NULL AFTER `product_id`");
    }
    if (!columnExists($db, 'order_items', 'variant_label')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `variant_label` VARCHAR(100) DEFAULT NULL AFTER `variant_id`");
    }

    // ── Seed common colors ──
    $colors = [
        ['Black',   'Хар',      '#000000', 1],
        ['White',   'Цагаан',   '#FFFFFF', 2],
        ['Red',     'Улаан',    '#EF4444', 3],
        ['Blue',    'Цэнхэр',   '#3B82F6', 4],
        ['Green',   'Ногоон',   '#22C55E', 5],
        ['Yellow',  'Шар',      '#EAB308', 6],
        ['Pink',    'Ягаан',    '#EC4899', 7],
        ['Purple',  'Нил ягаан', '#8B5CF6', 8],
        ['Orange',  'Улбар шар', '#F97316', 9],
        ['Brown',   'Бор',      '#92400E', 10],
        ['Gray',    'Саарал',   '#6B7280', 11],
        ['Beige',   'Цайвар шар','#D2B48C', 12],
        ['Navy',    'Хар хөх',  '#1E3A5F', 13],
    ];
    $cStmt = $db->prepare("INSERT IGNORE INTO `product_colors` (`name`, `name_mn`, `hex_code`, `sort_order`) VALUES (?, ?, ?, ?)");
    foreach ($colors as $c) { $cStmt->execute($c); }

    // ── Seed common sizes ──
    $sizes = [
        ['XS', 1], ['S', 2], ['M', 3], ['L', 4], ['XL', 5], ['2XL', 6], ['3XL', 7],
        ['34', 10], ['35', 11], ['36', 12], ['37', 13], ['38', 14], ['39', 15],
        ['40', 16], ['41', 17], ['42', 18], ['43', 19], ['44', 20], ['45', 21],
    ];
    $sStmt = $db->prepare("INSERT IGNORE INTO `product_sizes` (`name`, `sort_order`) VALUES (?, ?)");
    foreach ($sizes as $s) { $sStmt->execute($s); }
};

// ──────────────────────────────────────────────────────────────
// Migration 033: Social login (Google & Facebook)
// ──────────────────────────────────────────────────────────────
$migrations['033_social_login'] = function (PDO $db) {
    // Add social columns to customers
    if (!columnExists($db, 'customers', 'email')) {
        $db->exec("ALTER TABLE `customers` ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `name`");
    }
    if (!columnExists($db, 'customers', 'google_id')) {
        $db->exec("ALTER TABLE `customers` ADD COLUMN `google_id` VARCHAR(255) DEFAULT NULL AFTER `email`");
    }
    if (!columnExists($db, 'customers', 'facebook_id')) {
        $db->exec("ALTER TABLE `customers` ADD COLUMN `facebook_id` VARCHAR(255) DEFAULT NULL AFTER `google_id`");
    }
    if (!columnExists($db, 'customers', 'avatar')) {
        $db->exec("ALTER TABLE `customers` ADD COLUMN `avatar` VARCHAR(500) DEFAULT NULL AFTER `facebook_id`");
    }

    // Add indexes for social IDs
    if (!indexExists($db, 'customers', 'idx_google_id')) {
        $db->exec("ALTER TABLE `customers` ADD INDEX `idx_google_id` (`google_id`)");
    }
    if (!indexExists($db, 'customers', 'idx_facebook_id')) {
        $db->exec("ALTER TABLE `customers` ADD INDEX `idx_facebook_id` (`facebook_id`)");
    }

    // Login method settings
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $sStmt->execute(['login_phone_password_enabled', '1', 'Утас + Нууц үг нэвтрэх', 'boolean', 1]);
    $sStmt->execute(['login_phone_otp_enabled',      '1', 'Утас + OTP нэвтрэх',      'boolean', 1]);
    $sStmt->execute(['login_google_enabled',         '0', 'Google нэвтрэх',          'boolean', 1]);
    $sStmt->execute(['login_facebook_enabled',       '0', 'Facebook нэвтрэх',        'boolean', 1]);
    $sStmt->execute(['google_client_id',             '',  'Google Client ID',         'text',    1]);
    $sStmt->execute(['facebook_app_id',              '',  'Facebook App ID',          'text',    1]);
};

// ──────────────────────────────────────────────────────────────
// Migration 034: Item-level delivery_status, order partially_delivered & order_note
//   - order_items.delivery_status  ENUM('pending','delivered') DEFAULT 'pending'
//   - orders.status ENUM expanded with 'partially_delivered'
//   - orders.order_note  TEXT NULL  (admin-side notes, separate from customer notes)
// ──────────────────────────────────────────────────────────────
$migrations['034_item_delivery_status_and_order_note'] = function (PDO $db) {
    // 1. Add delivery_status to order_items
    if (!columnExists($db, 'order_items', 'delivery_status')) {
        $db->exec("ALTER TABLE `order_items` ADD COLUMN `delivery_status` ENUM('pending','delivered') NOT NULL DEFAULT 'pending' AFTER `hide_cargo_fee`");
    }

    // 2. Expand orders.status enum to include 'partially_delivered'
    try {
        $db->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','confirmed','cargo_shipping','cargo_arrived','ready_pickup','delivering','partially_delivered','delivered','picked_up','completed','cancelled') DEFAULT 'pending'");
    } catch (Exception $e) {
        // Already correct
    }

    // 3. Add order_note column to orders (admin notes)
    if (!columnExists($db, 'orders', 'order_note')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `order_note` TEXT NULL AFTER `notes`");
    }

    // 4. Back-fill: mark items as 'delivered' for orders already in delivered/completed/picked_up status
    $db->exec("
        UPDATE order_items oi
        JOIN orders o ON oi.order_id = o.id
        SET oi.delivery_status = 'delivered'
        WHERE o.status IN ('delivered','picked_up','completed')
          AND oi.delivery_status = 'pending'
    ");
};

// ──────────────────────────────────────────────────────────────
// Migration 035: Allow registration without OTP (for customers without SMS service)
// ──────────────────────────────────────────────────────────────
$migrations['035_register_without_otp_setting'] = function (PDO $db) {
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $sStmt->execute(['login_register_without_otp_enabled', '0', 'SMS-гүй бүртгэл (Утас + Нууц үг)', 'boolean', 1]);
};

// ──────────────────────────────────────────────────────────────
// Migration 036: FAQ table (admin-managed FAQ for the storefront)
// ──────────────────────────────────────────────────────────────
$migrations['036_faqs_table'] = function (PDO $db) {
    if (!tableExists($db, 'faqs')) {
        $db->exec("CREATE TABLE `faqs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category` VARCHAR(100) NOT NULL,
            `question` VARCHAR(500) NOT NULL,
            `answer` TEXT NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_faq_active_sort` (`is_active`, `sort_order`),
            INDEX `idx_faq_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed with the previously-hardcoded entries so the public page keeps
        // its content. Admins can edit/remove these from the Тохиргоо → ТАХ page.
        $seed = [
            ['Захиалга', 'Хэрхэн захиалга өгөх вэ?', 'Та хүссэн бараагаа сонгоод сагсанд нэмнэ. Дараа нь "Сагс" руу орж, хаяг болон холбоо барих мэдээллээ оруулаад захиалгаа баталгаажуулна. Манай ажилтан утсаар холбогдож захиалгыг дахин баталгаажуулна.'],
            ['Захиалга', 'Захиалгаа цуцлах боломжтой юу?', 'Тийм, бараа хүргэгдээгүй бол захиалгаа цуцлах боломжтой. 7711-1234 утсаар холбогдож захиалгын дугаараа хэлээд цуцлах хүсэлтээ илгээнэ үү. Хэрэв төлбөр төлсөн бол 3-5 хоногийн дотор буцааж олгоно.'],
            ['Захиалга', 'Урьдчилсан захиалга гэж юу вэ?', 'Урьдчилсан захиалга гэдэг нь Солонгосын том дэлгүүрүүдээс (E-mart, Costco, Lotte Mart, Olive Young) шууд захиалах бараа юм. Эдгээр бараа манай агуулахад байхгүй бөгөөд таны захиалгын дагуу Солонгосоос авчирдаг. Хүргэлтийн хугацаа 14-21 өдөр.'],
            ['Төлбөр',   'Ямар төлбөрийн хэлбэрүүдтэй вэ?', 'Бид QPay, бэлэн мөнгө болон картаар төлбөр хүлээн авдаг. Онлайнаар захиалахдаа QPay-ээр урьдчилж төлөх эсвэл бараа хүлээн авахдаа төлбөрөө төлөх боломжтой.'],
            ['Төлбөр',   'QPay-ээр хэрхэн төлөх вэ?', 'Захиалгаа баталгаажуулсны дараа QR код гарч ирнэ. Та аль ч банкны апп-аараа QR код уншуулж төлбөрөө төлнө. Төлбөр амжилттай төлөгдсний дараа автоматаар баталгаажина.'],
            ['Хүргэлт',  'Хүргэлт хэдэн өдөр үргэлжилдэг вэ?', 'Бэлэн бараа 1-2 өдөрт, урьдчилсан захиалга 14-21 өдөрт хүргэгддэг. Хүргэлтийн хугацаа танай хаягаас хамаарна.'],
            ['Хүргэлт',  'Хүргэлтийн төлбөр хэд вэ?', 'Бүх хүргэлт 8,000₮.'],
            ['Хүргэлт',  'Хүргэлтийн хүрээ хаана байдаг вэ?', 'Бид зөвхөн Улаанбаатар хотын төвийн 6 дүүрэгт хүргэлт үйлчилгээ үзүүлдэг.'],
            ['Бараа бүтээгдэхүүн', 'Бүх бараа Солонгосоос ирдэг үү?', 'Тийм, бидний бүх бараа Солонгосын алдартай дэлгүүрүүд болох E-mart, Costco, Lotte Mart, Olive Young-аас шууд авчирдаг. Энэ нь чанар, үнэний баталгаа юм.'],
            ['Бараа бүтээгдэхүүн', 'Бараа дуусах үед хэрхэн мэдэх вэ?', 'Агуулахад байгаа бараанууд дуусах шахсан үед "Бага үлдсэн" гэсэн тэмдэг гарна. Урьдчилсан захиалгын бараанд ийм асуудал гардаггүй.'],
            ['Данс',     'Данс үүсгэх шаардлагатай юу?', 'Үгүй, данс үүсгэхгүйгээр захиалга өгч болно. Гэхдээ данс үүсгэвэл захиалгынхаа түүхийг харах, хүргэлтийн мэдээлэл хадгалах зэрэг олон давуу талтай.'],
            ['Данс',     'Хэрхэн данс үүсгэх вэ?', 'Баруун дээд булангаас "Нэвтрэх" товч дарж, "Бүртгүүлэх" сонголтыг сонгоно. Утасны дугаар болон нууц үг оруулаад бүртгэлээ үүсгэнэ.'],
        ];

        $ins = $db->prepare("INSERT INTO `faqs` (`category`, `question`, `answer`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, 1)");
        foreach ($seed as $i => $row) {
            $ins->execute([$row[0], $row[1], $row[2], ($i + 1) * 10]);
        }
    }
};

// Migration 037: Add homepage category grid enable toggles and card image settings
$migrations['037_home_category_settings'] = function (PDO $db) {
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $newSettings = [
        ['home_categories_enabled', '1', 'Ангиллын хэсгийг харуулах', 'boolean', 1],
        ['home_category_ready_enabled', '1', 'Бэлэн бараа картыг харуулах', 'boolean', 1],
        ['home_category_preorder_enabled', '1', 'Урьдчилсан захиалга картыг харуулах', 'boolean', 1],
        ['home_category_discount_enabled', '1', 'Хямдрал картыг харуулах', 'boolean', 1],
        ['home_category_new_enabled', '1', 'Шинэ бараа картыг харуулах', 'boolean', 1],
        ['home_category_ready_image', '', 'Бэлэн бараа картны зураг', 'text', 1],
        ['home_category_preorder_image', '', 'Урьдчилсан захиалга картны зураг', 'text', 1],
        ['home_category_discount_image', '', 'Хямдрал картны зураг', 'text', 1],
        ['home_category_new_image', '', 'Шинэ бараа картны зураг', 'text', 1],
    ];
    foreach ($newSettings as $s) {
        $sStmt->execute($s);
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 038: Email authentication support
// ──────────────────────────────────────────────────────────────
$migrations['038_email_authentication'] = function (PDO $db) {
    // Add identifier and type columns to otp_codes for email/phone support
    if (!columnExists($db, 'otp_codes', 'identifier')) {
        $db->exec("ALTER TABLE `otp_codes` ADD COLUMN `identifier` VARCHAR(255) NULL AFTER `phone`");
    }
    if (!columnExists($db, 'otp_codes', 'type')) {
        $db->exec("ALTER TABLE `otp_codes` ADD COLUMN `type` ENUM('phone','email') NOT NULL DEFAULT 'phone' AFTER `identifier`");
    }

    // Add index for identifier
    if (!indexExists($db, 'otp_codes', 'idx_identifier_type')) {
        $db->exec("ALTER TABLE `otp_codes` ADD INDEX `idx_identifier_type` (`identifier`, `type`)");
    }

    // Back-fill existing phone records
    $db->exec("UPDATE otp_codes SET identifier = phone, type = 'phone' WHERE identifier IS NULL AND phone IS NOT NULL");

    // Email settings
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $emailSettings = [
        ['smtp_host', '', 'SMTP сервер', 'text', 0],
        ['smtp_port', '587', 'SMTP порт', 'number', 0],
        ['smtp_username', '', 'SMTP хэрэглэгчийн нэр', 'text', 0],
        ['smtp_password', '', 'SMTP нууц үг', 'password', 0],
        ['smtp_from_email', '', 'Илгээгчийн имэйл', 'text', 0],
        ['smtp_from_name', '', 'Илгээгчийн нэр', 'text', 0],
        ['smtp_encryption', 'tls', 'Шифрлэлт', 'select', 0],
        ['login_email_enabled', '0', 'Имэйлээр нэвтрэх', 'boolean', 1],
        ['register_email_enabled', '0', 'Имэйлээр бүртгүүлэх', 'boolean', 1],
        ['email_template_subject_register', 'Runners World имэйл баталгаажуулах код', 'Имэйл баталгаажуулах сэдэв', 'text', 0],
        ['email_template_body_register', 'Таны баталгаажуулах код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Имэйл баталгаажуулах бие', 'text', 0],
        ['email_template_subject_reset', 'Runners World нууц үг сэргээх код', 'Нууц үг сэргээх сэдэв', 'text', 0],
        ['email_template_body_reset', 'Таны нууц үг сэргээх код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Нууц үг сэргээх бие', 'text', 0],
    ];
    foreach ($emailSettings as $s) {
        $sStmt->execute($s);
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 039: Configurable order number prefix
// ──────────────────────────────────────────────────────────────
$migrations['039_order_number_prefix_setting'] = function (PDO $db) {
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['order_number_prefix', 'NO', 'Захиалгын дугаарын угтвар', 'text', 1]);
};

// ──────────────────────────────────────────────────────────────
// Migration 040: Reconciliation time-window matching threshold
// ──────────────────────────────────────────────────────────────
$migrations['040_reconciliation_time_window'] = function (PDO $db) {
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['reconciliation_time_window_minutes', '30', 'Тохирол: цагийн цонх (минут)', 'number', 0]);
};

// ──────────────────────────────────────────────────────────────
// Migration 041: Make otp_codes.phone nullable for email-only OTPs
// ──────────────────────────────────────────────────────────────
$migrations['041_otp_phone_nullable'] = function (PDO $db) {
    if (!tableExists($db, 'otp_codes')) return;
    if (!columnExists($db, 'otp_codes', 'phone')) return;

    $col = $db->query("SHOW COLUMNS FROM `otp_codes` LIKE 'phone'")->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper($col['Null']) === 'NO') {
        $db->exec("ALTER TABLE `otp_codes` MODIFY `phone` VARCHAR(20) NULL");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 042: Bonum payment gateway settings
// ──────────────────────────────────────────────────────────────
$migrations['042_bonum_payment_settings'] = function (PDO $db) {
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $sStmt->execute(['payment_bonum_enabled', '0', 'Bonum төлбөр идэвхтэй', 'boolean', 1]);
    $sStmt->execute(['bonum_merchant_id',    '', 'Bonum Merchant ID',    'text', 0]);
    $sStmt->execute(['bonum_terminal_id',    '', 'Bonum Terminal ID',    'text', 0]);
    $sStmt->execute(['bonum_secret_key',     '', 'Bonum Secret Key',     'text', 0]);
};

// ──────────────────────────────────────────────────────────────
// Migration 043: Bonum checksum key + bonum_invoice_id on orders
// ──────────────────────────────────────────────────────────────
$migrations['043_bonum_checksum_and_invoice_col'] = function (PDO $db) {
    // Add checksum key setting for webhook verification
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $sStmt->execute(['bonum_checksum_key', '', 'Bonum Checksum Key', 'text', 0]);

    // Add invoice ID column to orders for tracking
    if (!columnExists($db, 'orders', 'bonum_invoice_id')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `bonum_invoice_id` VARCHAR(100) NULL AFTER `qpay_invoice_id`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 044: Delivery fee toggle setting
// ──────────────────────────────────────────────────────────────
$migrations['044_delivery_fee_enabled_setting'] = function (PDO $db) {
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $sStmt->execute(['delivery_fee_enabled', '1', 'Хүргэлтийн төлбөрийг тооцох', 'boolean', 1]);
};

// ──────────────────────────────────────────────────────────────
// Migration 045: StorePay (BNPL) payment gateway
// ──────────────────────────────────────────────────────────────
$migrations['045_storepay_payment_gateway'] = function (PDO $db) {
    $sStmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
    $sStmt->execute(['payment_storepay_enabled', '0', 'StorePay төлбөр идэвхтэй', 'boolean', 1]);
    $sStmt->execute(['storepay_store_id',        '', 'StorePay Store ID',        'text',    0]);
    $sStmt->execute(['storepay_username',        '', 'StorePay System Username', 'text',    0]);
    $sStmt->execute(['storepay_password',        '', 'StorePay System Password', 'text',    0]);
    $sStmt->execute(['storepay_app_username',    '', 'StorePay App Username',    'text',    0]);
    $sStmt->execute(['storepay_app_password',    '', 'StorePay App Password',    'text',    0]);

    if (!columnExists($db, 'orders', 'storepay_invoice_id')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `storepay_invoice_id` VARCHAR(100) NULL AFTER `bonum_invoice_id`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 046: Nullable stock — NULL means unlimited preorder
// ──────────────────────────────────────────────────────────────
$migrations['046_nullable_preorder_stock'] = function (PDO $db) {
    $db->exec("ALTER TABLE `product_variants` MODIFY COLUMN `stock` INT DEFAULT NULL");
    $db->exec("ALTER TABLE `products` MODIFY COLUMN `stock` INT DEFAULT NULL");
    // Existing preorder products had stock=0 (default) but were effectively unlimited — restore that
    $db->exec("UPDATE `products` SET `stock` = NULL WHERE `type` = 'preorder' AND `stock` = 0 AND `has_variants` = 0");
    $db->exec("UPDATE `product_variants` pv JOIN `products` p ON pv.product_id = p.id SET pv.stock = NULL WHERE p.type = 'preorder' AND pv.stock = 0");
};

// ──────────────────────────────────────────────────────────────
// Migration 047: Quantity-based tier pricing
// ──────────────────────────────────────────────────────────────
$migrations['047_product_price_tiers'] = function (PDO $db) {
    if (!tableExists($db, 'product_price_tiers')) {
        $db->exec("
            CREATE TABLE `product_price_tiers` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `product_id` INT NOT NULL,
                `min_qty`    INT NOT NULL,
                `unit_price` DECIMAL(12,2) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_product_min_qty` (`product_id`, `min_qty`),
                CONSTRAINT `fk_price_tiers_product`
                    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 048: Bonum statement reconciliation support
//   - bank_statement_imports.source       VARCHAR(20) 'khan_bank'|'bonum'
//   - bank_statement_imports.bonum_transactions  INT
//   - bank_transactions.is_bonum          TINYINT(1)
// ──────────────────────────────────────────────────────────────
$migrations['048_bonum_reconciliation'] = function (PDO $db) {
    if (!columnExists($db, 'bank_statement_imports', 'source')) {
        $db->exec("ALTER TABLE `bank_statement_imports` ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'khan_bank' AFTER `filename`");
    }
    if (!columnExists($db, 'bank_statement_imports', 'bonum_transactions')) {
        $db->exec("ALTER TABLE `bank_statement_imports` ADD COLUMN `bonum_transactions` INT NOT NULL DEFAULT 0 AFTER `transfer_unmatched`");
    }
    if (!columnExists($db, 'bank_transactions', 'is_bonum')) {
        $db->exec("ALTER TABLE `bank_transactions` ADD COLUMN `is_bonum` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_transfer`");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 049: orders.confirmed_at — first time order entered
//   `confirmed` status. Used to allocate scarce stock FIFO by
//   verification time (sort orders by confirmed_at ASC).
// ──────────────────────────────────────────────────────────────
$migrations['049_orders_confirmed_at'] = function (PDO $db) {
    if (!columnExists($db, 'orders', 'confirmed_at')) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `confirmed_at` TIMESTAMP NULL DEFAULT NULL AFTER `order_note`");
    }

    // Backfill from audit log: earliest confirmation event per order
    $db->exec("
        UPDATE `orders` o
        JOIN (
            SELECT entity_id, MIN(created_at) AS first_confirmed_at
            FROM audit_log
            WHERE entity_type = 'order'
              AND (
                    action = 'order_auto_confirmed'
                 OR (action = 'order_status_changed'
                     AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.new_status')) = 'confirmed')
              )
            GROUP BY entity_id
        ) a ON a.entity_id = o.id
        SET o.confirmed_at = a.first_confirmed_at
        WHERE o.confirmed_at IS NULL
    ");

    // Fallback: orders past `pending` with no audit trail (e.g. POS sales
    // created already at `completed`, or pre-audit-logging history) —
    // use created_at so they aren't NULL.
    $db->exec("
        UPDATE `orders`
        SET confirmed_at = created_at
        WHERE confirmed_at IS NULL
          AND status NOT IN ('pending', 'cancelled')
    ");

    if (!indexExists($db, 'orders', 'idx_orders_confirmed_at')) {
        $db->exec("ALTER TABLE `orders` ADD INDEX `idx_orders_confirmed_at` (`confirmed_at`)");
    }
};

// ──────────────────────────────────────────────────────────────
// Migration 050: stock_movements ledger — every stock change is
// logged here going forward. Backfills best-effort from existing
// order_items + audit_log so old movements appear in history.
// ──────────────────────────────────────────────────────────────
$migrations['050_stock_movements_ledger'] = function (PDO $db) {
    if (!tableExists($db, 'stock_movements')) {
        $db->exec("
            CREATE TABLE `stock_movements` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `product_id`    INT NOT NULL,
                `variant_id`    INT NULL,
                `delta`         INT NOT NULL,
                `balance_after` INT NULL,
                `reason`        VARCHAR(40) NOT NULL,
                `order_id`      INT NULL,
                `actor_type`    ENUM('customer','admin','system') NOT NULL DEFAULT 'system',
                `actor_id`      INT NULL,
                `note`          VARCHAR(255) NULL,
                `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_sm_product_time` (`product_id`, `created_at`),
                INDEX `idx_sm_variant_time` (`variant_id`, `created_at`),
                INDEX `idx_sm_order`        (`order_id`),
                CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_sm_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_sm_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)        ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Backfill: every order_items row → a `backfill_sale` movement at the order
    // created_at. Cancelled orders also get a `backfill_cancel` movement at
    // orders.updated_at. balance_after is left NULL (cannot reconstruct accurately).
    // We only backfill for products/variants whose stock is currently non-NULL
    // (i.e. tracked), because uncapped preorders never actually moved stock.
    $db->exec("
        INSERT INTO stock_movements
            (product_id, variant_id, delta, balance_after, reason, order_id,
             actor_type, actor_id, note, created_at)
        SELECT
            oi.product_id,
            oi.variant_id,
            -oi.quantity,
            NULL,
            'backfill_sale',
            o.id,
            CASE WHEN o.order_type = 'pos' THEN 'admin' ELSE 'customer' END,
            NULL,
            CONCAT('Order ', o.order_number),
            o.created_at
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        LEFT JOIN product_variants pv ON pv.id = oi.variant_id
        WHERE NOT EXISTS (SELECT 1 FROM stock_movements WHERE order_id = o.id AND product_id = oi.product_id AND COALESCE(variant_id,0) = COALESCE(oi.variant_id,0) AND reason = 'backfill_sale')
          AND (
              (oi.variant_id IS NOT NULL AND pv.stock IS NOT NULL)
              OR (oi.variant_id IS NULL AND p.stock IS NOT NULL)
          )
    ");

    $db->exec("
        INSERT INTO stock_movements
            (product_id, variant_id, delta, balance_after, reason, order_id,
             actor_type, actor_id, note, created_at)
        SELECT
            oi.product_id,
            oi.variant_id,
            oi.quantity,
            NULL,
            'backfill_cancel',
            o.id,
            'system',
            NULL,
            CONCAT('Order ', o.order_number, ' cancelled'),
            COALESCE(o.updated_at, o.created_at)
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        LEFT JOIN product_variants pv ON pv.id = oi.variant_id
        WHERE o.status = 'cancelled'
          AND NOT EXISTS (SELECT 1 FROM stock_movements WHERE order_id = o.id AND product_id = oi.product_id AND COALESCE(variant_id,0) = COALESCE(oi.variant_id,0) AND reason = 'backfill_cancel')
          AND (
              (oi.variant_id IS NOT NULL AND pv.stock IS NOT NULL)
              OR (oi.variant_id IS NULL AND p.stock IS NOT NULL)
          )
    ");
};

// ──────────────────────────────────────────────────────────────
// Migration 051: Inventory Arrivals (FIFO fulfillment system)
// ──────────────────────────────────────────────────────────────
$migrations['051_inventory_arrivals'] = function (PDO $db) {
    // Add 'receiving' status to cargo_batches (between shipping and arrived)
    $db->exec("ALTER TABLE cargo_batches MODIFY COLUMN status ENUM('open','closed','shipping','receiving','arrived') NOT NULL DEFAULT 'open'");

    // inventory_arrivals: records a physical shipment that landed in Mongolia
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_arrivals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cargo_batch_id INT NULL,
        arrival_date DATE NOT NULL,
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_batch (cargo_batch_id),
        INDEX idx_date (arrival_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // inventory_arrival_items: each product/variant line within an arrival
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_arrival_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        arrival_id INT NOT NULL,
        product_id INT NOT NULL,
        variant_id INT NULL,
        quantity_received INT NOT NULL DEFAULT 1,
        quantity_matched INT NOT NULL DEFAULT 0,
        INDEX idx_arrival (arrival_id),
        INDEX idx_product_variant (product_id, variant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Link order_items to the arrival_item that fulfilled them
    if (!columnExists($db, 'order_items', 'arrival_item_id')) {
        $db->exec("ALTER TABLE order_items ADD COLUMN arrival_item_id INT NULL AFTER cargo_status, ADD INDEX idx_arrival_item (arrival_item_id)");
    }
};

$migrations['052_sms_queue'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS sms_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) NULL COMMENT 'cargo_arrived, batch_notify, etc.',
        order_id INT NULL,
        status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        error_text TEXT NULL,
        INDEX idx_status_created (status, created_at),
        INDEX idx_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

$migrations['053_sms_templates'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS sms_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        body TEXT NOT NULL,
        variables VARCHAR(255) NULL COMMENT 'comma-separated available placeholders',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $templates = [
        ['cargo_arrived',      'Ачаа ирсэн мэдэгдэл',         'Tanii zahialsan baraa Solongosoos irlee. Hayag: BGD 26-r horoo Narnii horoolol 9-r bair, 72228880. Tue-Fri 10:00-19:00, Sat 11:00-19:00.',  null],
        ['batch_notify',       'Батч SMS мэдэгдэл',            'Tanii zahialsan baraa Solongosoos irlee. Hayag: BGD 26-r horoo Narnii horoolol 9-r bair, 72228880. Tue-Fri 10:00-19:00, Sat 11:00-19:00.',  null],
        ['otp',                'Баталгаажуулах код (OTP)',      'Runners World баталгаажуулах код: {code}',                                                                                                      '{code}'],
        ['order_ready_pickup', 'Очиж авахад бэлэн',            'Сайн байна уу! Таны {order_number} захиалга очиж авахад бэлэн боллоо. - Runners World',                                                        '{order_number}'],
        ['order_delivering',   'Хүргэлтэнд гарсан',            'Сайн байна уу! Таны {order_number} захиалга хүргэгдэж байна. - Runners World',                                                                  '{order_number}'],
        ['order_general',      'Ерөнхий захиалга мэдэгдэл',    'Сайн байна уу! Таны {order_number} захиалгатай холбоотой мэдэгдэл. - Runners World',                                                           '{order_number}'],
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO sms_templates (`key`, name, body, variables) VALUES (?,?,?,?)");
    foreach ($templates as $t) $stmt->execute($t);
};

$migrations['054_cargo_payments'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS cargo_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        payment_method ENUM('qpay','bonum','storepay') NOT NULL,
        invoice_id VARCHAR(255) NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
        paid_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order (order_id),
        INDEX idx_invoice (invoice_id),
        CONSTRAINT fk_cp_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

$migrations['055_bank_tx_cargo_payment'] = function (PDO $db) {
    if (!columnExists($db, 'bank_transactions', 'cargo_payment_id')) {
        $db->exec("ALTER TABLE `bank_transactions`
            ADD COLUMN `cargo_payment_id` INT NULL AFTER `order_id`,
            ADD KEY `idx_cargo_payment_id` (`cargo_payment_id`)");
    }
};

$migrations['056_testimonials'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `testimonials` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `customer_name`   VARCHAR(100) NOT NULL,
        `customer_avatar` VARCHAR(255) DEFAULT NULL,
        `rating`          TINYINT NOT NULL DEFAULT 5,
        `title`           VARCHAR(200) DEFAULT NULL,
        `body`            TEXT NOT NULL,
        `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order`      INT NOT NULL DEFAULT 0,
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

$migrations['057_blog_posts'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `blog_posts` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `title`        VARCHAR(200) NOT NULL,
        `title_mn`     VARCHAR(200) DEFAULT NULL,
        `slug`         VARCHAR(200) NOT NULL,
        `excerpt`      TEXT DEFAULT NULL,
        `excerpt_mn`   TEXT DEFAULT NULL,
        `image`        VARCHAR(255) DEFAULT NULL,
        `is_published` TINYINT(1) NOT NULL DEFAULT 0,
        `published_at` TIMESTAMP NULL DEFAULT NULL,
        `sort_order`   INT NOT NULL DEFAULT 0,
        `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

$migrations['058_blog_posts_body'] = function (PDO $db) {
    $rows = $db->query("SHOW COLUMNS FROM `blog_posts` LIKE 'body_mn'")->fetchAll();
    if (empty($rows)) {
        $db->exec("ALTER TABLE `blog_posts` ADD COLUMN `body_mn` LONGTEXT DEFAULT NULL AFTER `excerpt_mn`");
    }
    $rows = $db->query("SHOW COLUMNS FROM `blog_posts` LIKE 'body'")->fetchAll();
    if (empty($rows)) {
        $db->exec("ALTER TABLE `blog_posts` ADD COLUMN `body` LONGTEXT DEFAULT NULL AFTER `body_mn`");
    }
};

$migrations['059_sliders'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `sliders` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `title_mn`    VARCHAR(200) DEFAULT NULL,
        `subtitle_mn` VARCHAR(400) DEFAULT NULL,
        `btn_text`    VARCHAR(100) DEFAULT NULL,
        `btn_url`     VARCHAR(500) DEFAULT NULL,
        `image`       VARCHAR(255) NOT NULL,
        `text_dark`   TINYINT(1)  NOT NULL DEFAULT 1,
        `is_active`   TINYINT(1)  NOT NULL DEFAULT 1,
        `sort_order`  INT         NOT NULL DEFAULT 0,
        `created_at`  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

$migrations['060_gender_activity'] = function (PDO $db) {
    // Gender on products
    if (!columnExists($db, 'products', 'gender')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `gender` ENUM('men','women','unisex','kids') NOT NULL DEFAULT 'unisex' AFTER `category_id`");
        $db->exec("ALTER TABLE `products` ADD INDEX `idx_products_gender` (`gender`)");
    }

    // Activity types master list
    $db->exec("CREATE TABLE IF NOT EXISTS `activity_types` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(80) NOT NULL,
        `name_mn`    VARCHAR(80) NOT NULL,
        `slug`       VARCHAR(80) NOT NULL UNIQUE,
        `icon`       VARCHAR(10) DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Product ↔ activity many-to-many
    $db->exec("CREATE TABLE IF NOT EXISTS `product_activity_types` (
        `product_id`      INT NOT NULL,
        `activity_type_id` INT NOT NULL,
        PRIMARY KEY (`product_id`, `activity_type_id`),
        FOREIGN KEY (`product_id`)       REFERENCES `products`(`id`)       ON DELETE CASCADE,
        FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed common activity types
    $db->exec("INSERT IGNORE INTO `activity_types` (`name`, `name_mn`, `slug`, `icon`, `sort_order`) VALUES
        ('Trail Running',  'Трейл гүйлт',   'trail-running',  '🏔️', 1),
        ('Road Running',   'Замын гүйлт',   'road-running',   '🏃', 2),
        ('Hiking',         'Уулын аялал',   'hiking',         '⛰️', 3),
        ('Cross Training', 'Дасгал хийх',   'cross-training', '💪', 4),
        ('Walking',        'Алхалт',         'walking',        '🚶', 5),
        ('Gym',            'Фитнесс',        'gym',            '🏋️', 6)
    ");
};

$migrations['061_contact_messages'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `contact_messages` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(150) NOT NULL,
        `email`      VARCHAR(190) NOT NULL,
        `phone`      VARCHAR(30)  DEFAULT NULL,
        `message`    TEXT NOT NULL,
        `ip_address` VARCHAR(45)  DEFAULT NULL,
        `user_agent` VARCHAR(255) DEFAULT NULL,
        `status`     ENUM('new','read','spam') NOT NULL DEFAULT 'new',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_contact_messages_status` (`status`),
        INDEX `idx_contact_messages_ip_created` (`ip_address`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

$migrations['062_newsletter_subscribers'] = function (PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `email`          VARCHAR(190) NOT NULL UNIQUE,
        `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
        `ip_address`     VARCHAR(45)  DEFAULT NULL,
        `user_agent`     VARCHAR(255) DEFAULT NULL,
        `subscribed_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `unsubscribed_at` TIMESTAMP NULL DEFAULT NULL,
        INDEX `idx_newsletter_active` (`is_active`),
        INDEX `idx_newsletter_ip_subscribed` (`ip_address`, `subscribed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

$migrations['063_feature_boxes'] = function (PDO $db) {
    // Feature-boxes row rendered on /shop (delivery, genuine, returns, support).
    // Add feature4_* + per-box icon override so admins can rewrite the whole row.
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, 1)");
    $rows = [
        ['feature1_icon',  'icon-boat',     'Feature 1 Icon (icon class)', 'text'],
        ['feature1_title', 'Үнэгүй хүргэлт', 'Feature 1 Title',            'text'],
        ['feature1_desc',  '50,000₮-с дээш захиалгад', 'Feature 1 Description', 'text'],
        ['feature2_icon',  'icon-package',  'Feature 2 Icon (icon class)', 'text'],
        ['feature2_title', 'Жинхэнэ бараа', 'Feature 2 Title',             'text'],
        ['feature2_desc',  'Солонгосоос шууд', 'Feature 2 Description',    'text'],
        ['feature3_icon',  'icon-calender', 'Feature 3 Icon (icon class)', 'text'],
        ['feature3_title', '30 хоногийн буцаалт', 'Feature 3 Title',       'text'],
        ['feature3_desc',  'Мөнгө буцаах баталгаа', 'Feature 3 Description', 'text'],
        ['feature4_icon',  'icon-headset',  'Feature 4 Icon (icon class)', 'text'],
        ['feature4_title', 'Онлайн дэмжлэг', 'Feature 4 Title',            'text'],
        ['feature4_desc',  '7 хоногт 24 цаг', 'Feature 4 Description',     'text'],
    ];
    foreach ($rows as $r) {
        $stmt->execute($r);
    }
    // Also patch any pre-existing empty title/desc from earlier install
    $patch = $db->prepare("UPDATE `settings` SET `setting_value` = ? WHERE `setting_key` = ? AND (`setting_value` IS NULL OR `setting_value` = '')");
    foreach ($rows as $r) {
        $patch->execute([$r[1], $r[0]]);
    }
};

$migrations['064_features_table'] = function (PDO $db) {
    // Feature-boxes row (delivery, genuine, returns, support) shown on /shop.
    // Moving from settings-row-per-field to a proper table so admins can add,
    // edit, reorder, delete features without new migrations.
    $db->exec("CREATE TABLE IF NOT EXISTS `features` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `icon`          VARCHAR(80)  NOT NULL DEFAULT 'icon-star',
        `title_mn`      VARCHAR(150) NOT NULL,
        `description_mn` VARCHAR(255) DEFAULT NULL,
        `sort_order`    INT NOT NULL DEFAULT 0,
        `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
        `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_features_active_sort` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed from previously-stored settings if the table is empty.
    $count = (int)$db->query("SELECT COUNT(*) FROM features")->fetchColumn();
    if ($count === 0) {
        $keys = [];
        for ($i = 1; $i <= 4; $i++) {
            $keys[] = "feature{$i}_icon";
            $keys[] = "feature{$i}_title";
            $keys[] = "feature{$i}_desc";
        }
        $ph = implode(',', array_fill(0, count($keys), '?'));
        $rows = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($ph)");
        $rows->execute($keys);
        $s = [];
        foreach ($rows->fetchAll() as $r) $s[$r['setting_key']] = $r['setting_value'];

        $insert = $db->prepare("INSERT INTO features (icon, title_mn, description_mn, sort_order) VALUES (?, ?, ?, ?)");
        $seeded = false;
        for ($i = 1; $i <= 4; $i++) {
            $title = trim($s["feature{$i}_title"] ?? '');
            if ($title === '') continue;
            $insert->execute([
                trim($s["feature{$i}_icon"] ?? '') ?: 'icon-star',
                $title,
                trim($s["feature{$i}_desc"] ?? '') ?: null,
                $i * 10,
            ]);
            $seeded = true;
        }
        // Fallback defaults if nothing was in settings
        if (!$seeded) {
            $insert->execute(['icon-boat',     'Үнэгүй хүргэлт',        '50,000₮-с дээш захиалгад', 10]);
            $insert->execute(['icon-package',  'Жинхэнэ бараа',        'Солонгосоос шууд',           20]);
            $insert->execute(['icon-calender', '30 хоногийн буцаалт', 'Мөнгө буцаах баталгаа',      30]);
            $insert->execute(['icon-headset',  'Онлайн дэмжлэг',       '7 хоногт 24 цаг',            40]);
        }
    }
};

$migrations['065_drop_legacy_feature_settings'] = function (PDO $db) {
    // The feature boxes now live in the dedicated `features` table (mig 064).
    // Delete the old settings rows so they stop cluttering the Homepage tab.
    $keys = [];
    for ($i = 1; $i <= 6; $i++) {
        $keys[] = "feature{$i}_icon";
        $keys[] = "feature{$i}_title";
        $keys[] = "feature{$i}_desc";
    }
    $ph = implode(',', array_fill(0, count($keys), '?'));
    $db->prepare("DELETE FROM settings WHERE setting_key IN ($ph)")->execute($keys);
};

// ──────────────────────────────────────────────────────────────
// Migration 066: Running-store product attributes
//   - categories.parent_id (subcategories: Clothing Type / Accessories Type)
//   - product_sizes.size_group (clothing / shoes / accessories)
//   - shoe_types, run_types, cushionings, gait_types + product pivots
//   - seed the vocabularies with values from the reference sheet
// ──────────────────────────────────────────────────────────────
$migrations['066_running_attributes'] = function (PDO $db) {

    // ── Categories: parent_id for hierarchy ──
    if (!columnExists($db, 'categories', 'parent_id')) {
        $db->exec("ALTER TABLE `categories` ADD COLUMN `parent_id` INT NULL AFTER `slug`");
        $db->exec("ALTER TABLE `categories` ADD INDEX `idx_categories_parent` (`parent_id`)");
    }

    // ── Product sizes: group tag so sizes can be filtered by domain ──
    if (!columnExists($db, 'product_sizes', 'size_group')) {
        $db->exec("ALTER TABLE `product_sizes` ADD COLUMN `size_group` ENUM('clothing','shoes','accessories') NOT NULL DEFAULT 'clothing' AFTER `name`");
        $db->exec("ALTER TABLE `product_sizes` ADD INDEX `idx_sizes_group` (`size_group`)");

        // Back-fill: numeric-looking sizes (e.g. 40, 42, 235) → shoes; letter sizes → clothing
        $db->exec("UPDATE `product_sizes` SET `size_group` = 'shoes' WHERE `name` REGEXP '^[0-9]+([.][0-9]+)?$'");
    }

    // ── Shoe types (Road / Trail / Race / Lightweight) ──
    $db->exec("CREATE TABLE IF NOT EXISTS `shoe_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(80) NOT NULL,
        `name_mn` VARCHAR(80) NOT NULL,
        `slug` VARCHAR(80) NOT NULL UNIQUE,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `product_shoe_types` (
        `product_id` INT NOT NULL,
        `shoe_type_id` INT NOT NULL,
        PRIMARY KEY (`product_id`, `shoe_type_id`),
        KEY `idx_pst_shoe` (`shoe_type_id`),
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`shoe_type_id`) REFERENCES `shoe_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Run types (Race / Tempo / Daily / Long) ──
    $db->exec("CREATE TABLE IF NOT EXISTS `run_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(80) NOT NULL,
        `name_mn` VARCHAR(80) NOT NULL,
        `slug` VARCHAR(80) NOT NULL UNIQUE,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `product_run_types` (
        `product_id` INT NOT NULL,
        `run_type_id` INT NOT NULL,
        PRIMARY KEY (`product_id`, `run_type_id`),
        KEY `idx_prt_run` (`run_type_id`),
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`run_type_id`) REFERENCES `run_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Cushioning (Max / Balanced / Responsive) ──
    $db->exec("CREATE TABLE IF NOT EXISTS `cushionings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(80) NOT NULL,
        `name_mn` VARCHAR(80) NOT NULL,
        `slug` VARCHAR(80) NOT NULL UNIQUE,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `product_cushionings` (
        `product_id` INT NOT NULL,
        `cushioning_id` INT NOT NULL,
        PRIMARY KEY (`product_id`, `cushioning_id`),
        KEY `idx_pcu_cushion` (`cushioning_id`),
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`cushioning_id`) REFERENCES `cushionings`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Gait types (Neutral / Stability) ──
    $db->exec("CREATE TABLE IF NOT EXISTS `gait_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(80) NOT NULL,
        `name_mn` VARCHAR(80) NOT NULL,
        `slug` VARCHAR(80) NOT NULL UNIQUE,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `product_gait_types` (
        `product_id` INT NOT NULL,
        `gait_type_id` INT NOT NULL,
        PRIMARY KEY (`product_id`, `gait_type_id`),
        KEY `idx_pgt_gait` (`gait_type_id`),
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`gait_type_id`) REFERENCES `gait_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Seed vocabularies ──
    $db->exec("INSERT IGNORE INTO `shoe_types` (`name`, `name_mn`, `slug`, `sort_order`) VALUES
        ('Road',        'Замын',       'road',        1),
        ('Trail',       'Трейл',        'trail',       2),
        ('Race',        'Уралдааны',   'race',        3),
        ('Lightweight', 'Хөнгөн',       'lightweight', 4)
    ");

    $db->exec("INSERT IGNORE INTO `run_types` (`name`, `name_mn`, `slug`, `sort_order`) VALUES
        ('Race Run',   'Уралдааны гүйлт', 'race-run',   1),
        ('Tempo Run',  'Темпо гүйлт',     'tempo-run',  2),
        ('Daily Run',  'Өдөр тутмын гүйлт', 'daily-run', 3),
        ('Long Run',   'Урт гүйлт',       'long-run',   4)
    ");

    $db->exec("INSERT IGNORE INTO `cushionings` (`name`, `name_mn`, `slug`, `sort_order`) VALUES
        ('Max',        'Макс',        'max',        1),
        ('Balanced',   'Тэнцвэртэй',   'balanced',   2),
        ('Responsive', 'Хариу үйлдэлт', 'responsive', 3)
    ");

    $db->exec("INSERT IGNORE INTO `gait_types` (`name`, `name_mn`, `slug`, `sort_order`) VALUES
        ('Neutral',   'Нейтрал',   'neutral',   1),
        ('Stability', 'Стабилити', 'stability', 2)
    ");
};

// ──────────────────────────────────────────────────────────────
// Migration 067: Banner system with managed locations
//   - banner_locations table (admin-managed vocabulary)
//   - sliders.location_id FK → banner_locations
//   - seed 4 default locations
//   - backfill existing sliders → hero_home
// ──────────────────────────────────────────────────────────────
$migrations['067_banner_locations'] = function (PDO $db) {

    // Locations vocabulary
    $db->exec("CREATE TABLE IF NOT EXISTS `banner_locations` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `slug`       VARCHAR(60) NOT NULL UNIQUE,
        `label_mn`   VARCHAR(120) NOT NULL,
        `label_en`   VARCHAR(120) NOT NULL,
        `description` VARCHAR(255) NULL,
        `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed defaults
    $db->exec("INSERT IGNORE INTO `banner_locations` (`slug`, `label_mn`, `label_en`, `description`, `sort_order`) VALUES
        ('hero_home',   'Нүүр — Дээд том баннер',   'Home Hero',        'Nav-ын доор эхний том баннер (slider хэлбэрээр эргэлддэг)', 10),
        ('mid_home',    'Нүүр — Дунд хэсэг',        'Home Middle',      'Deals болон Brands хэсгийн хооронд',                          20),
        ('shop_top',    'Дэлгүүр — Дээр',           'Shop Page Top',    'Дэлгүүрийн жагсаалтын дээд талд',                             30),
        ('above_footer','Нүүр — Footer дээр',       'Above Footer',     'Newsletter/footer-ийн дээд талд',                             40)
    ");

    // Link sliders to locations
    if (!columnExists($db, 'sliders', 'location_id')) {
        $db->exec("ALTER TABLE `sliders` ADD COLUMN `location_id` INT NULL AFTER `id`");
        $db->exec("ALTER TABLE `sliders` ADD INDEX `idx_sliders_location` (`location_id`)");

        // Backfill: existing rows all go to hero_home
        $db->exec("UPDATE `sliders`
                   SET `location_id` = (SELECT id FROM `banner_locations` WHERE slug = 'hero_home')
                   WHERE `location_id` IS NULL");
    }
};

// ══════════════════════════════════════════════════════════════
//  ADD FUTURE MIGRATIONS ABOVE THIS LINE
// ══════════════════════════════════════════════════════════════


// ── Run migrations ──
$results = [];
$hasErrors = false;

foreach ($migrations as $name => $fn) {
    if (migrationApplied($db, $name)) {
        $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Already applied'];
        continue;
    }

    try {
        $fn($db);
        recordMigration($db, $name);
        $results[] = ['name' => $name, 'status' => 'applied', 'message' => 'Successfully applied'];
    } catch (Exception $e) {
        $results[] = ['name' => $name, 'status' => 'error', 'message' => $e->getMessage()];
        $hasErrors = true;
        break; // Stop on first error
    }
}

// ── Output ──
if ($isCli) {
    echo "\n=== Runners World Database Migration ===\n\n";
    foreach ($results as $r) {
        $icon = match ($r['status']) {
            'applied' => '✅',
            'skipped' => '⏭️ ',
            'error' => '❌',
        };
        echo "$icon {$r['name']} — {$r['message']}\n";
    }
    echo "\nDone.\n";
    exit($hasErrors ? 1 : 0);
}

// Browser output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-lg w-full">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">🔄 Database Migration</h1>

        <?php if (empty($results)): ?>
            <p class="text-gray-500">No migrations defined.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($results as $r): ?>
                    <div class="flex items-start gap-3 p-3 rounded-lg <?= $r['status'] === 'error' ? 'bg-red-50' : ($r['status'] === 'applied' ? 'bg-green-50' : 'bg-gray-50') ?>">
                        <span class="text-lg mt-0.5">
                            <?= match ($r['status']) {
                                'applied' => '✅',
                                'skipped' => '⏭️',
                                'error' => '❌',
                            } ?>
                        </span>
                        <div>
                            <p class="font-mono text-sm font-semibold text-gray-800"><?= htmlspecialchars($r['name']) ?></p>
                            <p class="text-sm <?= $r['status'] === 'error' ? 'text-red-600' : 'text-gray-500' ?>"><?= htmlspecialchars($r['message']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-6 pt-4 border-t">
            <a href="/backend/" class="text-blue-600 hover:underline text-sm">← Back to Admin</a>
        </div>
    </div>
</body>
</html>
