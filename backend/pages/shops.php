<?php
$pageTitle = 'Брэнд';
$db = getDB();

$shops = $db->query("SELECT s.*, 
    (SELECT COUNT(*) FROM products WHERE shop_id = s.id) as product_count,
    (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND is_active = 1) as active_products,
    GROUP_CONCAT(c.name SEPARATOR ', ') as categories
    FROM shops s
    LEFT JOIN shop_categories sc ON s.id = sc.shop_id
    LEFT JOIN categories c ON sc.category_id = c.id
    GROUP BY s.id
    ORDER BY s.sort_order")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500"><?= count($shops) ?> брэнд</p>
    <a href="index.php?page=shop-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Брэнд нэмэх
    </a>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($shops as $shop): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg" style="background-color: <?= e($shop['color']) ?>">
                    <?= strtoupper(substr($shop['name'], 0, 2)) ?>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900"><?= e($shop['name']) ?></h3>
                    <p class="text-sm text-gray-500"><?= e($shop['name_mn']) ?></p>
                </div>
            </div>
            <span class="w-2.5 h-2.5 rounded-full mt-2 <?= $shop['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
        </div>
        
        <p class="text-xs text-gray-400 mb-3"><?= e($shop['categories'] ?: 'Ангилал байхгүй') ?></p>
        
        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
            <span class="text-sm text-gray-600"><?= $shop['active_products'] ?>/<?= $shop['product_count'] ?> бүтээгдэхүүн</span>
            <div class="flex gap-2">
                <a href="index.php?page=shop-form&id=<?= $shop['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600 transition-colors" title="Засах">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </a>
                <?php if (!hasRole('pos_cashier')): ?>
                <a href="index.php?page=shop-delete&id=<?= $shop['id'] ?>&token=<?= generateCSRFToken() ?>" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Устгах"
                   onclick="return confirm('Энэ брэндийг устгах уу? Бүтээгдэхүүнийг дахин хуваарилах шаардлагатай.')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
