<?php
/**
 * Runners World Admin Panel - Main Router
 */
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$page = $_GET['page'] ?? 'dashboard';

// Public pages (no auth required)
$publicPages = ['login'];

if (in_array($page, $publicPages)) {
    require_once __DIR__ . '/pages/login.php';
    exit;
}

// Auth check
require_once __DIR__ . '/includes/auth.php';

// POS cashier redirect: if accessing a page they can't see, redirect to POS
if (!canAccessPage($page)) {
    $defaultPage = $currentAdmin['role'] === 'pos_cashier' ? 'pos' : 'dashboard';
    header('Location: index.php?page=' . $defaultPage);
    exit;
}

// Protected pages
$protectedPages = [
    'dashboard', 'products', 'product-form', 'product-delete', 'product-bulk-action', 'product-import',
    'media', 'media-upload', 'media-delete',
    'shops', 'shop-form', 'shop-delete',
    'categories', 'category-form', 'category-delete',
    'variant-options',
    'activity-types', 'activity-type-form', 'activity-type-delete',
    'orders', 'order-detail',
    'customers', 'customer-detail', 'customer-form',
    'cargo-batches', 'cargo-batch-form', 'cargo-batch-delete', 'batch-handout', 'cargo-payments',
    'inventory-arrivals', 'inventory-arrival-form', 'sms-queue', 'sms-templates',
    'pos', 'pos-history',
    'districts', 'district-form',
    'settings', 'users', 'user-form', 'user-delete',
    'reports', 'reconciliation',
    'drivers', 'driver-form', 'driver-delete',
    'deliveries', 'delivery-assign', 'delivery-batch-detail',
    'audit-log',
    'product-entry-users',
    'order-create',
    'bulk-order-import',
    'sync-products',
    'stock-history',
    'faqs', 'faq-form', 'faq-delete',
    'sliders', 'slider-form', 'slider-delete',
    'testimonials', 'testimonial-form', 'testimonial-delete',
    'blog-posts', 'blog-post-form', 'blog-post-delete',
];

if (in_array($page, $protectedPages)) {
    require_once __DIR__ . '/pages/' . $page . '.php';
} else {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-20"><h2 class="text-2xl font-bold text-gray-400">404 - Page Not Found</h2><a href="index.php" class="mt-4 inline-block text-blue-600 hover:underline">Go to Dashboard</a></div>';
    require_once __DIR__ . '/includes/footer.php';
}
