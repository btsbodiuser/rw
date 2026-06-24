<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Удирдлага') ?> - <?= e(getSetting('site_name', 'Runners World')) ?> Удирдлага</title>
    <?php $sFavicon = getSetting('site_favicon'); ?>
    <?php if ($sFavicon): ?>
    <link rel="icon" type="image/png" href="<?= e(getBasePath()) ?>backend/uploads/media/<?= e($sFavicon) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {"50":"#eff6ff","100":"#dbeafe","200":"#bfdbfe","300":"#93c5fd","400":"#60a5fa","500":"#3b82f6","600":"#2563eb","700":"#1d4ed8","800":"#1e40af","900":"#1e3a8a"}
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #94a3b8;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            letter-spacing: 0.01em;
        }
        .nav-item:hover {
            color: #e2e8f0;
            background: rgba(255,255,255,0.05);
            transform: translateX(4px);
        }
        .nav-item.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(139,92,246,0.2), rgba(217,70,239,0.1));
            box-shadow: 0 0 20px rgba(139,92,246,0.08);
        }
        .nav-indicator {
            width: 3px;
            height: 0;
            border-radius: 3px;
            background: linear-gradient(to bottom, #8b5cf6, #d946ef);
            transition: height 0.2s ease;
            flex-shrink: 0;
        }
        .nav-item.active .nav-indicator {
            height: 20px;
        }
        .nav-item:hover .nav-indicator {
            height: 12px;
        }
        .nav-label {
            white-space: nowrap;
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex min-h-screen" x-data="{ sidebarOpen: true, mobileSidebar: false }">
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-h-screen min-w-0" :class="sidebarOpen ? 'lg:ml-[250px]' : 'lg:ml-0'">
            <!-- Top Header -->
            <header class="bg-white/80 backdrop-blur-md border-b border-gray-200/60 sticky top-0 z-20">
                <div class="flex items-center justify-between px-5 py-3">
                    <div class="flex items-center gap-3">
                        <button @click="sidebarOpen = !sidebarOpen" class="hidden lg:block p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <button @click="mobileSidebar = true" class="lg:hidden p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div>
                            <h1 class="text-base font-semibold text-gray-900"><?= e($pageTitle ?? 'Хянах самбар') ?></h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="index.php?page=pos" class="hidden sm:flex items-center gap-2 px-3.5 py-2 bg-gradient-to-r from-emerald-500 to-green-600 text-white rounded-lg hover:from-emerald-600 hover:to-green-700 text-sm font-medium transition-all shadow-sm shadow-green-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            POS
                        </a>
                        <div class="h-6 w-px bg-gray-200 mx-1 hidden sm:block"></div>
                        <div class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-gray-50 cursor-default">
                            <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-semibold text-xs shadow-sm">
                                <?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?>
                            </div>
                            <span class="hidden sm:inline text-sm font-medium text-gray-700"><?= e($currentAdmin['name'] ?? 'Admin') ?></span>
                        </div>
                        <a href="index.php?page=login&action=logout" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors" title="Гарах">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 lg:p-6">
                <?php renderFlash(); ?>
