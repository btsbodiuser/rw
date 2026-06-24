<?php
$pageTitle = 'Бүтээгдэхүүн';
$db = getDB();

// Filters
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$shopFilter = $_GET['shop'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$stockFilter = $_GET['stock'] ?? '';
$activeFilter = $_GET['active'] ?? '';
$webFilter = $_GET['web'] ?? '';
$imgFilter = $_GET['img'] ?? '';
$sortCol = $_GET['sort'] ?? '';
$sortDir = strtolower($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['pg'] ?? 1));

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(p.name LIKE ? OR p.name_mn LIKE ? OR p.slug LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryFilter) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}
if ($shopFilter) {
    $where[] = "p.shop_id = ?";
    $params[] = $shopFilter;
}
if ($typeFilter) {
    $where[] = "p.type = ?";
    $params[] = $typeFilter;
}
if ($stockFilter === 'out') {
    $where[] = "p.type = 'ready' AND p.stock = 0";
} elseif ($stockFilter === 'lt5') {
    $where[] = "p.type = 'ready' AND p.stock < 5";
} elseif ($stockFilter === 'lt10') {
    $where[] = "p.type = 'ready' AND p.stock < 10";
} elseif ($stockFilter === 'lt50') {
    $where[] = "p.type = 'ready' AND p.stock < 50";
} elseif ($stockFilter === 'lt100') {
    $where[] = "p.type = 'ready' AND p.stock < 100";
} elseif ($stockFilter === 'gt100') {
    $where[] = "p.type = 'ready' AND p.stock > 100";
} elseif ($stockFilter === 'gt150') {
    $where[] = "p.type = 'ready' AND p.stock > 150";
} elseif ($stockFilter === 'gt500') {
    $where[] = "p.type = 'ready' AND p.stock > 500";
}
if ($activeFilter === '1') {
    $where[] = "p.is_active = 1";
} elseif ($activeFilter === '0') {
    $where[] = "p.is_active = 0";
}
if ($webFilter === '1') {
    $where[] = "p.show_in_store = 1";
} elseif ($webFilter === '0') {
    $where[] = "p.show_in_store = 0";
}
if ($imgFilter === '1') {
    $where[] = "p.image IS NOT NULL AND p.image != ''";
} elseif ($imgFilter === '0') {
    $where[] = "(p.image IS NULL OR p.image = '')";
}

$whereStr = implode(' AND ', $where);

// ── CSV export: respects current filters, ignores pagination ──
if (($_GET['export'] ?? '') === 'csv') {
    $exportStmt = $db->prepare("SELECT p.*, s.name as shop_name, c.name as category_name
        FROM products p
        LEFT JOIN shops s ON p.shop_id = s.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereStr
        ORDER BY p.created_at DESC");
    $exportStmt->execute($params);

    $variantStmt = $db->prepare("SELECT
            pv.id, pv.product_id, pv.sku, pv.price_override, pv.stock, pv.is_active,
            pc.name_mn AS color_name, ps.name AS size_name
        FROM product_variants pv
        LEFT JOIN product_colors pc ON pc.id = pv.color_id
        LEFT JOIN product_sizes  ps ON ps.id = pv.size_id
        WHERE pv.product_id = ?
        ORDER BY pc.name_mn, ps.name");

    $filename = 'products_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens Mongolian correctly
    fputcsv($out, [
        'ID', 'Нэр (EN)', 'Нэр (MN)', 'Slug', 'Баркод', 'Ангилал', 'Дэлгүүр',
        'Төрөл', 'Үнэ', 'Хямдрахын өмнө', 'Жин (кг)',
        'Нөөц', 'Хувилбартай', 'Хувилбар',
        'Урьдчилсан огноо', 'Захиалга авах', 'Идэвхтэй', 'Сайтад харагдах',
        'Үүсгэсэн огноо',
    ]);

    foreach ($exportStmt->fetchAll() as $p) {
        $stockLabel = $p['stock'] === null ? '∞' : (string)$p['stock'];
        $baseRow = [
            $p['id'],
            $p['name'],
            $p['name_mn'],
            $p['slug'],
            $p['barcode'],
            $p['category_name'],
            $p['shop_name'],
            $p['type'] === 'ready' ? 'Бэлэн' : 'Урьдчилсан',
            $p['price'],
            $p['original_price'],
            $p['weight_kg'],
            $stockLabel,
            $p['has_variants'] ? 'Тийм' : 'Үгүй',
            '', // variant placeholder (filled below for variant products)
            $p['preorder_date'],
            $p['order_status'] === 'open' ? 'Нээлттэй' : 'Хаалттай',
            $p['is_active'] ? 'Тийм' : 'Үгүй',
            $p['show_in_store'] ? 'Тийм' : 'Үгүй',
            $p['created_at'],
        ];

        if ($p['has_variants']) {
            $variantStmt->execute([$p['id']]);
            $variants = $variantStmt->fetchAll();
            if (empty($variants)) {
                fputcsv($out, $baseRow);
            } else {
                $first = true;
                foreach ($variants as $v) {
                    $label = trim(($v['color_name'] ?? '') . (($v['color_name'] && $v['size_name']) ? ' / ' : '') . ($v['size_name'] ?? ''));
                    $variantStock = $v['stock'] === null ? '∞' : (string)$v['stock'];
                    $variantLabel = $label !== '' ? $label : ('#' . $v['id']);

                    if ($first) {
                        // First variant row: full product info + this variant's stock + label
                        $row = $baseRow;
                        $row[11] = $variantStock;
                        $row[13] = $variantLabel;
                        $first = false;
                    } else {
                        // Subsequent variant rows: leave product columns blank for a
                        // visually-merged effect in Excel; only stock + variant label fill.
                        $row = array_fill(0, count($baseRow), '');
                        $row[11] = $variantStock;
                        $row[13] = $variantLabel;
                    }
                    fputcsv($out, $row);
                }
            }
        } else {
            fputcsv($out, $baseRow);
        }
    }

    fclose($out);
    exit;
}

// Stock summary stats (always over all ready products, ignoring current filters)
$stockStats = $db->query("
    SELECT
        COUNT(*) as total_ready,
        SUM(stock > 0) as in_stock,
        SUM(stock = 0) as out_of_stock,
        SUM(stock > 0 AND stock <= 5) as low_stock,
        SUM(stock) as total_units
    FROM products
    WHERE type = 'ready' AND is_active = 1
")->fetch();

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 15, $page);

// Sort
$allowedSorts = [
    'name' => 'p.name',
    'category' => 'c.name',
    'shop' => 's.name',
    'type' => 'p.type',
    'price' => 'p.price',
    'stock' => 'p.stock',
    'weight' => 'p.weight_kg',
    'active' => 'p.is_active',
    'created' => 'p.created_at',
];
$orderBy = isset($allowedSorts[$sortCol])
    ? "{$allowedSorts[$sortCol]} {$sortDir}"
    : "(p.order_status = 'closed') ASC, p.created_at DESC";

// Fetch
$stmt = $db->prepare("SELECT p.*, s.name as shop_name, c.name as category_name 
    FROM products p 
    LEFT JOIN shops s ON p.shop_id = s.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE $whereStr 
    ORDER BY {$orderBy} 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$products = $stmt->fetchAll();

// For filters
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$shops = $db->query("SELECT * FROM shops WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$filterUrl = 'index.php?page=products';
if ($search) $filterUrl .= '&search=' . urlencode($search);
if ($categoryFilter) $filterUrl .= '&category=' . urlencode($categoryFilter);
if ($shopFilter) $filterUrl .= '&shop=' . urlencode($shopFilter);
if ($typeFilter) $filterUrl .= '&type=' . urlencode($typeFilter);
if ($stockFilter) $filterUrl .= '&stock=' . urlencode($stockFilter);
if ($activeFilter !== '') $filterUrl .= '&active=' . urlencode($activeFilter);
if ($webFilter !== '') $filterUrl .= '&web=' . urlencode($webFilter);
if ($imgFilter !== '') $filterUrl .= '&img=' . urlencode($imgFilter);
if ($sortCol) $filterUrl .= '&sort=' . urlencode($sortCol) . '&dir=' . urlencode(strtolower($sortDir));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Top Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <p class="text-sm text-gray-500"><?= $total ?> бүтээгдэхүүн олдсон</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= e($filterUrl . (strpos($filterUrl, '?') !== false ? '&' : '?') . 'export=csv') ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm font-medium transition-colors"
           title="Одоогийн шүүлтийг Excel-д татах">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
            Excel
        </a>
        <a href="index.php?page=product-import" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
            Импорт
        </a>
        <a href="index.php?page=product-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Бүтээгдэхүүн нэмэх
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-9 gap-3">
        <input type="hidden" name="page" value="products">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Нэр, баркод, slug-ээр хайх..."
               class="col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
        <select name="category" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх ангилал</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $categoryFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="shop" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх дэлгүүр</option>
            <?php foreach ($shops as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $shopFilter == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх төрөл</option>
            <option value="ready" <?= $typeFilter === 'ready' ? 'selected' : '' ?>>Бэлэн</option>
            <option value="preorder" <?= $typeFilter === 'preorder' ? 'selected' : '' ?>>Урьдчилсан</option>
        </select>
        <select name="stock" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх нөөц</option>
            <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Дууссан (0)</option>
            <option value="lt5" <?= $stockFilter === 'lt5' ? 'selected' : '' ?>>< 5</option>
            <option value="lt10" <?= $stockFilter === 'lt10' ? 'selected' : '' ?>>< 10</option>
            <option value="lt50" <?= $stockFilter === 'lt50' ? 'selected' : '' ?>>< 50</option>
            <option value="lt100" <?= $stockFilter === 'lt100' ? 'selected' : '' ?>>< 100</option>
            <option value="gt100" <?= $stockFilter === 'gt100' ? 'selected' : '' ?>>> 100</option>
            <option value="gt150" <?= $stockFilter === 'gt150' ? 'selected' : '' ?>>> 150</option>
            <option value="gt500" <?= $stockFilter === 'gt500' ? 'selected' : '' ?>>> 500</option>
        </select>
        <select name="active" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх төлөв</option>
            <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Идэвхтэй</option>
            <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Идэвхгүй</option>
        </select>
        <select name="web" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх (Вэб)</option>
            <option value="1" <?= $webFilter === '1' ? 'selected' : '' ?>>Вэбсайтад харагдана</option>
            <option value="0" <?= $webFilter === '0' ? 'selected' : '' ?>>Зөвхөн POS</option>
        </select>
        <select name="img" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх (Зураг)</option>
            <option value="1" <?= $imgFilter === '1' ? 'selected' : '' ?>>Зурагтай</option>
            <option value="0" <?= $imgFilter === '0' ? 'selected' : '' ?>>Зураггүй</option>
        </select>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Хайх</button>
            <a href="index.php?page=products" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Цэвэрлэх</a>
        </div>
    </form>
</div>

<!-- Stock Summary -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <a href="index.php?page=products&type=ready" class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-blue-200 transition-colors">
        <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
        </div>
        <div>
            <p class="text-xs text-gray-400">Нийт бэлэн бараа</p>
            <p class="text-xl font-bold text-gray-900"><?= number_format($stockStats['total_ready'] ?? 0) ?></p>
        </div>
    </a>
    <a href="index.php?page=products&type=ready&stock=gt100" class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-green-200 transition-colors">
        <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center text-green-600 flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div>
            <p class="text-xs text-gray-400">Нөөцтэй</p>
            <p class="text-xl font-bold text-green-600"><?= number_format($stockStats['in_stock'] ?? 0) ?></p>
        </div>
    </a>
    <a href="index.php?page=products&type=ready&stock=lt5" class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-yellow-200 transition-colors">
        <div class="w-9 h-9 rounded-lg bg-yellow-50 flex items-center justify-center text-yellow-600 flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <div>
            <p class="text-xs text-gray-400">Бага нөөц (≤5)</p>
            <p class="text-xl font-bold text-yellow-600"><?= number_format($stockStats['low_stock'] ?? 0) ?></p>
        </div>
    </a>
    <a href="index.php?page=products&type=ready&stock=out" class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-red-200 transition-colors">
        <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center text-red-600 flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <div>
            <p class="text-xs text-gray-400">Дууссан (0)</p>
            <p class="text-xl font-bold text-red-600"><?= number_format($stockStats['out_of_stock'] ?? 0) ?></p>
        </div>
    </a>
</div>

<!-- Products Table -->
<?php
// Build sort URL helper
$baseSortUrl = 'index.php?page=products';
if ($search) $baseSortUrl .= '&search=' . urlencode($search);
if ($categoryFilter) $baseSortUrl .= '&category=' . urlencode($categoryFilter);
if ($shopFilter) $baseSortUrl .= '&shop=' . urlencode($shopFilter);
if ($typeFilter) $baseSortUrl .= '&type=' . urlencode($typeFilter);
if ($stockFilter) $baseSortUrl .= '&stock=' . urlencode($stockFilter);
if ($activeFilter !== '') $baseSortUrl .= '&active=' . urlencode($activeFilter);
if ($webFilter !== '') $baseSortUrl .= '&web=' . urlencode($webFilter);

function sortHeader($label, $col, $currentSort, $currentDir, $baseUrl, $align = 'left') {
    $isActive = $currentSort === $col;
    $nextDir = ($isActive && $currentDir === 'ASC') ? 'desc' : 'asc';
    $arrow = '';
    if ($isActive) {
        $arrow = $currentDir === 'ASC'
            ? '<svg class="w-3 h-3 inline ml-0.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>'
            : '<svg class="w-3 h-3 inline ml-0.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
    }
    $cls = $isActive ? 'text-blue-600' : 'text-gray-500 hover:text-gray-700';
    $textAlign = "text-{$align}";
    return "<th class=\"{$textAlign} px-5 py-3 text-xs font-medium uppercase\"><a href=\"{$baseUrl}&sort={$col}&dir={$nextDir}\" class=\"{$cls} inline-flex items-center gap-0.5 group\">{$label}{$arrow}</a></th>";
}
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <?= sortHeader('Бүтээгдэхүүн', 'name', $sortCol, $sortDir, $baseSortUrl) ?>
                    <?= sortHeader('Ангилал', 'category', $sortCol, $sortDir, $baseSortUrl) ?>
                    <?= sortHeader('Дэлгүүр', 'shop', $sortCol, $sortDir, $baseSortUrl) ?>
                    <?= sortHeader('Төрөл', 'type', $sortCol, $sortDir, $baseSortUrl) ?>
                    <?= sortHeader('Үнэ', 'price', $sortCol, $sortDir, $baseSortUrl, 'right') ?>
                    <?= sortHeader('Нөөц', 'stock', $sortCol, $sortDir, $baseSortUrl, 'center') ?>
                    <?= sortHeader('Жин', 'weight', $sortCol, $sortDir, $baseSortUrl, 'center') ?>
                    <?= sortHeader('Төлөв', 'active', $sortCol, $sortDir, $baseSortUrl, 'center') ?>
                    <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase">Вэб</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($products)): ?>
                    <tr><td colspan="10" class="px-5 py-12 text-center text-gray-400">Бүтээгдэхүүн олдсонгүй</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <?php if ($p['image']): ?>
                                <img src="<?= e($p['image']) ?>" alt="" class="w-10 h-10 rounded-lg object-cover bg-gray-100">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?= e($p['name_mn']) ?></p>
                                <p class="text-xs text-gray-400"><?= e($p['name']) ?></p>
                                <?php if ($p['barcode']): ?>
                                    <p class="text-xs text-gray-400 mt-0.5 cursor-pointer hover:text-blue-600 copy-barcode" data-barcode="<?= e($p['barcode']) ?>" title="Хуулах">📋 <?= e($p['barcode']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600"><?= e($p['category_name']) ?></td>
                    <td class="px-5 py-3 text-sm text-gray-600"><?= e($p['shop_name']) ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $p['type'] === 'ready' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700' ?>">
                            <?= $p['type'] === 'ready' ? 'Бэлэн' : 'Урьдчилсан' ?>
                        </span>
                        <?php if (($p['order_status'] ?? 'open') === 'closed'): ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Хаалттай</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-right">
                        <span class="font-medium"><?= formatPrice($p['price']) ?></span>
                        <?php if ($p['original_price']): ?>
                            <br><span class="text-xs text-gray-400 line-through"><?= formatPrice($p['original_price']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?php if ($p['type'] === 'ready'): ?>
                            <span class="text-sm font-medium <?= $p['stock'] == 0 ? 'text-red-600' : ($p['stock'] <= 5 ? 'text-yellow-600' : 'text-gray-900') ?>">
                                <?= $p['stock'] ?>
                            </span>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center text-sm text-gray-600">
                        <?= $p['weight_kg'] ? $p['weight_kg'] . 'kg' : '-' ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="w-2.5 h-2.5 rounded-full inline-block <?= $p['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"></span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="w-2.5 h-2.5 rounded-full inline-block <?= ($p['show_in_store'] ?? 1) ? 'bg-blue-500' : 'bg-gray-300' ?>" title="<?= ($p['show_in_store'] ?? 1) ? 'Вэбсайтад харагдана' : 'Вэбсайтад харагдахгүй' ?>"></span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="index.php?page=stock-history&product_id=<?= $p['id'] ?>" class="p-1.5 text-gray-400 hover:text-green-600 transition-colors" title="Нөөцийн түүх">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </a>
                            <a href="index.php?page=product-form&id=<?= $p['id'] ?>&return=<?= urlencode($filterUrl) ?>" class="p-1.5 text-gray-400 hover:text-blue-600 transition-colors" title="Засах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <?php if (!hasRole('pos_cashier')): ?>
                            <a href="index.php?page=product-delete&id=<?= $p['id'] ?>&token=<?= generateCSRFToken() ?>&return=<?= urlencode($filterUrl) ?>" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Устгах"
                               onclick="return confirm('Энэ бүтээгдэхүүнийг устгах уу?')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPagination($pagination, $filterUrl); ?>

<script>
document.querySelectorAll('.copy-barcode').forEach(el => {
    el.addEventListener('click', e => {
        e.stopPropagation();
        const bc = el.dataset.barcode;
        navigator.clipboard.writeText(bc).then(() => {
            const orig = el.textContent;
            el.textContent = '✅ Хуулсан!';
            setTimeout(() => el.textContent = orig, 1000);
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
