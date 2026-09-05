<?php
$currentPage = $_GET['page'] ?? 'dashboard';
$role = $currentAdmin['role'] ?? 'admin';

$menuGroups = [
    [
        'label' => null,
        'items' => array_filter([
            canAccessPage('dashboard') ? ['page' => 'dashboard', 'label' => 'Хянах самбар'] : null,
            canAccessPage('pos') ? ['page' => 'pos', 'label' => 'POS Терминал'] : null,
        ])
    ],
    [
        'label' => 'Каталог',
        'items' => array_filter([
            canAccessPage('products') ? ['page' => 'products', 'label' => 'Бүтээгдэхүүн'] : null,
            canAccessPage('categories') ? ['page' => 'categories', 'label' => 'Ангилал'] : null,
            canAccessPage('activity-types') ? ['page' => 'activity-types', 'label' => 'Үйл ажиллагаа'] : null,
            canAccessPage('running-attributes') ? ['page' => 'running-attributes', 'label' => 'Гүйлтийн шинж чанар'] : null,
            canAccessPage('variant-options') ? ['page' => 'variant-options', 'label' => 'Өнгө & Хэмжээ'] : null,
            canAccessPage('shops') ? ['page' => 'shops', 'label' => 'Брэнд'] : null,
            canAccessPage('product-import') ? ['page' => 'product-import', 'label' => 'Импорт (Excel)'] : null,
            canAccessPage('media') ? ['page' => 'media', 'label' => 'Медиа сан'] : null,
            canAccessPage('product-entry-users') ? ['page' => 'product-entry-users', 'label' => 'Бараа оруулагч'] : null,
        ])
    ],
    [
        'label' => 'Борлуулалт',
        'items' => array_filter([
            canAccessPage('orders') ? ['page' => 'orders', 'label' => 'Захиалга'] : null,
            canAccessPage('order-create') ? ['page' => 'order-create', 'label' => 'Захиалга үүсгэх'] : null,
            canAccessPage('bulk-order-import') ? ['page' => 'bulk-order-import', 'label' => 'Хуулгаас импорт'] : null,
            canAccessPage('customers') ? ['page' => 'customers', 'label' => 'Хэрэглэгч'] : null,
            canAccessPage('pos-history') ? ['page' => 'pos-history', 'label' => 'POS Түүх'] : null,
            canAccessPage('cargo-batches') ? ['page' => 'cargo-batches', 'label' => 'Ачааны багц'] : null,
            canAccessPage('cargo-payments') ? ['page' => 'cargo-payments', 'label' => 'Ачааны төлбөр'] : null,
            canAccessPage('inventory-arrivals') ? ['page' => 'inventory-arrivals', 'label' => 'Ирэлт бүртгэл'] : null,
            canAccessPage('sms-queue') ? ['page' => 'sms-queue', 'label' => 'SMS жагсаалт'] : null,
            canAccessPage('sms-templates') ? ['page' => 'sms-templates', 'label' => 'SMS загвар'] : null,
        ])
    ],
    [
        'label' => 'Хүргэлт',
        'items' => array_filter([
            canAccessPage('deliveries') ? ['page' => 'deliveries', 'label' => 'Хүргэлт'] : null,
            canAccessPage('drivers') ? ['page' => 'drivers', 'label' => 'Жолоочид'] : null,
        ])
    ],
    [
        'label' => 'Контент',
        'items' => array_filter([
            canAccessPage('sliders') ? ['page' => 'sliders', 'label' => 'Баннер'] : null,
            canAccessPage('banner-locations') ? ['page' => 'banner-locations', 'label' => 'Баннер байршил'] : null,
            canAccessPage('features') ? ['page' => 'features', 'label' => 'Шинж чанарын хайрцаг'] : null,
            canAccessPage('testimonials') ? ['page' => 'testimonials', 'label' => 'Сэтгэгдэл'] : null,
            canAccessPage('blog-posts') ? ['page' => 'blog-posts', 'label' => 'Блог / Мэдээ'] : null,
            canAccessPage('faqs') ? ['page' => 'faqs', 'label' => 'ТАХ (FAQ)'] : null,
            canAccessPage('contact-messages') ? ['page' => 'contact-messages', 'label' => 'Холбоо барих мессеж'] : null,
            canAccessPage('newsletter-subscribers') ? ['page' => 'newsletter-subscribers', 'label' => 'Мэдээллийн захидал'] : null,
        ])
    ],
    [
        'label' => 'Систем',
        'items' => array_filter([
            canAccessPage('reports') ? ['page' => 'reports', 'label' => 'Тайлан'] : null,
            canAccessPage('reconciliation') ? ['page' => 'reconciliation', 'label' => 'Төлбөр тулгалт'] : null,
            canAccessPage('districts') ? ['page' => 'districts', 'label' => 'Дүүрэг'] : null,
            canAccessPage('settings') ? ['page' => 'settings', 'label' => 'Тохиргоо'] : null,
            canAccessPage('users') ? ['page' => 'users', 'label' => 'Хэрэглэгчид'] : null,
            canAccessPage('audit-log') ? ['page' => 'audit-log', 'label' => 'Аудит лог'] : null,
            canAccessPage('sync-products') ? ['page' => 'sync-products', 'label' => 'Синхрончлол'] : null,
        ])
    ],
];

// Remove empty groups
$menuGroups = array_filter($menuGroups, fn($g) => !empty($g['items']));
?>

<!-- Mobile Sidebar Overlay -->
<div x-show="mobileSidebar" x-cloak @click="mobileSidebar = false" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 lg:hidden" x-transition:enter="transition-opacity ease-out duration-300" x-transition:leave="transition-opacity ease-in duration-200"></div>

<!-- Sidebar -->
<aside class="fixed top-0 left-0 z-40 h-full w-[250px] bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 transform transition-transform duration-300 ease-in-out flex flex-col shadow-2xl shadow-black/20"
       :class="{
           '-translate-x-full': !mobileSidebar,
           'translate-x-0': mobileSidebar,
           'lg:translate-x-0': sidebarOpen,
           'lg:-translate-x-full': !sidebarOpen
       }">

    <!-- Logo -->
    <div class="flex items-center justify-between px-5 py-5">
        <a href="index.php" class="flex items-center gap-3 group">
            <?php $sLogo = getSetting('site_logo'); ?>
            <?php if ($sLogo): ?>
            <img src="<?= e(getBasePath()) ?>backend/uploads/media/<?= e($sLogo) ?>" alt="<?= e(getSetting('site_name', 'Runners World')) ?>"
                 class="w-9 h-9 rounded-xl object-contain shadow-lg">
            <?php else: ?>
            <div class="w-9 h-9 bg-gradient-to-br from-violet-500 via-purple-500 to-fuchsia-500 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30 group-hover:shadow-purple-500/50 transition-shadow duration-300">
                <span class="text-white font-black text-sm"><?= e(mb_substr(getSetting('site_name', 'Runners World'), 0, 1)) ?></span>
            </div>
            <?php endif; ?>
            <div>
                <span class="text-white font-bold text-[15px] tracking-tight block leading-tight"><?= e(getSetting('site_name', 'Runners World')) ?></span>
                <span class="text-slate-500 text-[10px] font-medium tracking-wider uppercase">Удирдлага</span>
            </div>
        </a>
        <button @click="mobileSidebar = false" class="lg:hidden p-1.5 rounded-lg text-slate-500 hover:text-white hover:bg-white/10 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- Separator -->
    <div class="mx-5 h-px bg-gradient-to-r from-transparent via-slate-700/50 to-transparent"></div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 pt-4 pb-4">
        <?php foreach ($menuGroups as $group): ?>
            <?php if ($group['label']): ?>
                <div class="mt-6 mb-2 px-3">
                    <span class="text-[10px] font-bold text-slate-600 uppercase tracking-[0.2em]"><?= $group['label'] ?></span>
                </div>
            <?php endif; ?>
            <div class="space-y-0.5">
                <?php foreach ($group['items'] as $item): ?>
                    <a href="index.php?page=<?= $item['page'] ?>"
                       class="nav-item group <?= $currentPage === $item['page'] ? 'active' : '' ?>"
                       @click="mobileSidebar = false">
                        <span class="nav-indicator"></span>
                        <span class="nav-label"><?= $item['label'] ?></span>
                        <?php if ($currentPage === $item['page']): ?>
                            <span class="ml-auto w-1.5 h-1.5 rounded-full bg-violet-400 shadow-sm shadow-violet-400/50"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- Bottom -->
    <div class="px-5 py-4">
        <div class="h-px bg-gradient-to-r from-transparent via-slate-700/50 to-transparent mb-4"></div>
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-gradient-to-br from-violet-500 to-fuchsia-500 rounded-lg flex items-center justify-center text-white text-xs font-bold shadow-lg shadow-purple-500/20">
                <?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-300 truncate"><?= e($currentAdmin['name'] ?? 'Admin') ?></p>
                <p class="text-[10px] text-slate-600"><?= e($roleLabels[$currentAdmin['role']] ?? 'Admin') ?></p>
            </div>
        </div>
    </div>
</aside>
