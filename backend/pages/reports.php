<?php
$pageTitle = 'Тайлан';
$db = getDB();

// ── CSV Export (must run before any HTML output) ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportTab = $_GET['tab'] ?? 'sales';
    $exportFrom = $_GET['from'] ?? date('Y-m-01');
    $exportTo = $_GET['to'] ?? date('Y-m-d');
    $exportRange = $_GET['range'] ?? 'month';
    
    // Recalculate dates for export
    switch ($exportRange) {
        case 'today': $exportFrom = $exportTo = date('Y-m-d'); break;
        case 'week': $exportFrom = date('Y-m-d', strtotime('monday this week')); $exportTo = date('Y-m-d'); break;
        case 'month': $exportFrom = date('Y-m-01'); $exportTo = date('Y-m-d'); break;
        case 'year': $exportFrom = date('Y-01-01'); $exportTo = date('Y-m-d'); break;
        case 'all': $exportFrom = '2020-01-01'; $exportTo = date('Y-m-d'); break;
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $exportTab . '_' . $exportFrom . '_' . $exportTo . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    if ($exportTab === 'sales') {
        $stmt = $db->prepare("SELECT DATE(created_at) as day, SUM(CASE WHEN payment_status='paid' THEN total ELSE 0 END) as revenue, COUNT(*) as orders FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled' GROUP BY DATE(created_at) ORDER BY day");
        $stmt->execute([$exportFrom, $exportTo]);
        fputcsv($out, ['Огноо', 'Орлого', 'Захиалга']);
        while ($r = $stmt->fetch()) { fputcsv($out, [$r['day'], $r['revenue'], $r['orders']]); }
    } elseif ($exportTab === 'products') {
        $stmt = $db->prepare("SELECT oi.product_name, s.name as shop_name, c.name as category_name, SUM(oi.quantity) as total_qty, COUNT(DISTINCT oi.order_id) as order_count, SUM(COALESCE(oi.line_total, oi.product_price * oi.quantity)) as total_revenue FROM order_items oi JOIN orders o ON oi.order_id = o.id LEFT JOIN products p ON oi.product_id = p.id LEFT JOIN shops s ON p.shop_id = s.id LEFT JOIN categories c ON p.category_id = c.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled' GROUP BY oi.product_id, oi.product_name ORDER BY total_revenue DESC");
        $stmt->execute([$exportFrom, $exportTo]);
        fputcsv($out, ['Бүтээгдэхүүн', 'Дэлгүүр', 'Ангилал', 'Тоо', 'Захиалга', 'Орлого']);
        while ($r = $stmt->fetch()) { fputcsv($out, [$r['product_name'], $r['shop_name'] ?? '', $r['category_name'] ?? '', $r['total_qty'], $r['order_count'], $r['total_revenue']]); }
    } elseif ($exportTab === 'customers') {
        $stmt = $db->prepare("SELECT o.customer_name, o.customer_phone, COUNT(*) as order_count, SUM(CASE WHEN payment_status='paid' THEN total ELSE 0 END) as total_spent, MAX(o.created_at) as last_order FROM orders o WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled' AND o.customer_name IS NOT NULL AND o.customer_name != '' GROUP BY o.customer_phone ORDER BY total_spent DESC");
        $stmt->execute([$exportFrom, $exportTo]);
        fputcsv($out, ['Нэр', 'Утас', 'Захиалга', 'Нийт зарцуулсан', 'Сүүлийн захиалга']);
        while ($r = $stmt->fetch()) { fputcsv($out, [$r['customer_name'], $r['customer_phone'], $r['order_count'], $r['total_spent'], $r['last_order']]); }
    } elseif ($exportTab === 'cargo') {
        $stmt = $db->prepare("SELECT cb.name, cb.status, COUNT(o.id) as order_count, SUM(CASE WHEN o.payment_status='paid' THEN 1 ELSE 0 END) as paid_count, SUM(CASE WHEN o.payment_status='paid' THEN o.total ELSE 0 END) as total_revenue, SUM(CASE WHEN o.payment_status='paid' THEN o.cargo_fee ELSE 0 END) as total_cargo_fee FROM cargo_batches cb LEFT JOIN orders o ON o.cargo_batch_id = cb.id AND o.status != 'cancelled' GROUP BY cb.id ORDER BY cb.created_at DESC");
        $stmt->execute([]);
        fputcsv($out, ['Багц', 'Төлөв', 'Захиалга', 'Төлсөн', 'Орлого', 'Ачааны төлбөр']);
        while ($r = $stmt->fetch()) { fputcsv($out, [$r['name'], $r['status'], $r['order_count'], $r['paid_count'], $r['total_revenue'], $r['total_cargo_fee']]); }
    } elseif ($exportTab === 'stock') {
        $expShop = (int)($_GET['stock_shop'] ?? 0);
        $expWhere = "p.type = 'ready' AND p.is_active = 1";
        $expParams = [];
        if ($expShop) { $expWhere .= " AND p.shop_id = ?"; $expParams[] = $expShop; }
        $stmt = $db->prepare("SELECT p.name_mn, p.name, p.stock, p.has_variants, c.name as category_name, s.name as shop_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN shops s ON p.shop_id = s.id WHERE $expWhere ORDER BY s.name, p.stock ASC, p.name_mn");
        $stmt->execute($expParams);
        $rows = $stmt->fetchAll();
        // Fetch variants
        $expVarIds = array_column(array_filter($rows, fn($r) => $r['has_variants']), 'id');
        // (variant detail not easily available here; export flat stock only)
        header('Content-Disposition: attachment; filename="stock_report_' . date('Y-m-d') . '.csv"');
        fputcsv($out, ['Бүтээгдэхүүн (МН)', 'Бүтээгдэхүүн', 'Дэлгүүр', 'Ангилал', 'Нөөц', 'Төлөв']);
        foreach ($rows as $r) {
            $status = $r['stock'] == 0 ? 'Дууссан' : ($r['stock'] <= 5 ? 'Бага' : 'Хэвийн');
            fputcsv($out, [$r['name_mn'], $r['name'], $r['shop_name'] ?? '', $r['category_name'] ?? '', $r['stock'], $status]);
        }
    }
    fclose($out);
    exit;
}

// ── Date range filter ──
$range = $_GET['range'] ?? 'month';
$customFrom = $_GET['from'] ?? '';
$customTo = $_GET['to'] ?? '';

switch ($range) {
    case 'today':
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
        break;
    case 'week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo = date('Y-m-d');
        break;
    case 'month':
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-d');
        break;
    case 'year':
        $dateFrom = date('Y-01-01');
        $dateTo = date('Y-m-d');
        break;
    case 'all':
        $dateFrom = '2020-01-01';
        $dateTo = date('Y-m-d');
        break;
    case 'custom':
        $dateFrom = $customFrom ?: date('Y-m-01');
        $dateTo = $customTo ?: date('Y-m-d');
        break;
    default:
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-d');
}

$tab = $_GET['tab'] ?? 'sales';

// ══════════════════════════════════════════════════
//  SALES DATA
// ══════════════════════════════════════════════════
$salesStmt = $db->prepare("SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
    (SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'cancelled') as cancelled_orders,
    SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue,
    SUM(CASE WHEN payment_status = 'paid' THEN cargo_fee ELSE 0 END) as total_cargo_fee,
    SUM(CASE WHEN payment_status = 'paid' THEN delivery_fee ELSE 0 END) as total_delivery_fee,
    AVG(CASE WHEN payment_status = 'paid' THEN total ELSE NULL END) as avg_order_value,
    SUM(CASE WHEN order_type = 'pos' AND payment_status = 'paid' THEN total ELSE 0 END) as pos_revenue,
    SUM(CASE WHEN order_type = 'online' AND payment_status = 'paid' THEN total ELSE 0 END) as online_revenue,
    SUM(CASE WHEN order_type = 'pos' THEN 1 ELSE 0 END) as pos_orders,
    SUM(CASE WHEN order_type = 'online' THEN 1 ELSE 0 END) as online_orders,
    SUM(CASE WHEN fulfillment = 'delivery' THEN 1 ELSE 0 END) as delivery_orders,
    SUM(CASE WHEN fulfillment = 'pickup' THEN 1 ELSE 0 END) as pickup_orders
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'");
$salesStmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$sales = $salesStmt->fetch();

// Daily revenue for chart
$dailyStmt = $db->prepare("SELECT DATE(created_at) as day, 
    SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue,
    COUNT(*) as orders
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY DATE(created_at) ORDER BY day");
$dailyStmt->execute([$dateFrom, $dateTo]);
$dailyData = $dailyStmt->fetchAll();

// Daily revenue by type (online vs POS)
$dailyTypeStmt = $db->prepare("SELECT DATE(created_at) as day, 
    SUM(CASE WHEN order_type = 'online' AND payment_status = 'paid' THEN total ELSE 0 END) as online_revenue,
    SUM(CASE WHEN order_type = 'pos' AND payment_status = 'paid' THEN total ELSE 0 END) as pos_revenue
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY DATE(created_at) ORDER BY day");
$dailyTypeStmt->execute([$dateFrom, $dateTo]);
$dailyTypeData = $dailyTypeStmt->fetchAll();

// Status breakdown
$statusStmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status ORDER BY cnt DESC");
$statusStmt->execute([$dateFrom, $dateTo]);
$statusBreakdown = $statusStmt->fetchAll();

// Hourly distribution
$hourlyStmt = $db->prepare("SELECT HOUR(created_at) as hour, COUNT(*) as cnt 
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY HOUR(created_at) ORDER BY hour");
$hourlyStmt->execute([$dateFrom, $dateTo]);
$hourlyData = $hourlyStmt->fetchAll();

// ══════════════════════════════════════════════════
//  PRODUCT DATA
// ══════════════════════════════════════════════════
// Best sellers
$bestSellersStmt = $db->prepare("SELECT oi.product_name, oi.product_id, p.image,
    SUM(oi.quantity) as total_qty,
    SUM(COALESCE(oi.line_total, oi.product_price * oi.quantity)) as total_revenue,
    COUNT(DISTINCT oi.order_id) as order_count,
    s.name as shop_name, c.name as category_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN shops s ON p.shop_id = s.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY oi.product_id, oi.product_name
    ORDER BY total_revenue DESC LIMIT 20");
$bestSellersStmt->execute([$dateFrom, $dateTo]);
$bestSellers = $bestSellersStmt->fetchAll();

// Category performance
$categoryStmt = $db->prepare("SELECT c.name as category_name, c.name_mn,
    SUM(oi.quantity) as total_qty,
    SUM(COALESCE(oi.line_total, oi.product_price * oi.quantity)) as total_revenue,
    COUNT(DISTINCT o.id) as order_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY c.id ORDER BY total_revenue DESC");
$categoryStmt->execute([$dateFrom, $dateTo]);
$categoryData = $categoryStmt->fetchAll();

// Shop performance
$shopStmt = $db->prepare("SELECT s.name as shop_name,
    SUM(oi.quantity) as total_qty,
    SUM(COALESCE(oi.line_total, oi.product_price * oi.quantity)) as total_revenue,
    COUNT(DISTINCT o.id) as order_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN shops s ON p.shop_id = s.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY s.id ORDER BY total_revenue DESC");
$shopStmt->execute([$dateFrom, $dateTo]);
$shopData = $shopStmt->fetchAll();

// Low stock
$lowStock = $db->query("SELECT p.*, s.name as shop_name, c.name as category_name 
    FROM products p 
    LEFT JOIN shops s ON p.shop_id = s.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.type = 'ready' AND p.stock <= 5 AND p.is_active = 1 
    ORDER BY p.stock ASC LIMIT 20")->fetchAll();

// ══════════════════════════════════════════════════
//  CUSTOMER DATA
// ══════════════════════════════════════════════════
$customerStatsStmt = $db->prepare("SELECT 
    COUNT(DISTINCT customer_id) as unique_customers,
    (SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN ? AND ?) as new_customers
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND customer_id IS NOT NULL AND status != 'cancelled'");
$customerStatsStmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$customerStats = $customerStatsStmt->fetch();

// Daily user registrations for chart
$dailyRegStmt = $db->prepare("SELECT DATE(created_at) as day, COUNT(*) as registrations
    FROM customers WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at) ORDER BY day");
$dailyRegStmt->execute([$dateFrom, $dateTo]);
$dailyRegData = $dailyRegStmt->fetchAll();

// Top customers
$topCustomersStmt = $db->prepare("SELECT o.customer_name, o.customer_phone,
    COUNT(*) as order_count,
    SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_spent,
    MIN(o.created_at) as first_order,
    MAX(o.created_at) as last_order
    FROM orders o
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled' AND o.customer_name IS NOT NULL AND o.customer_name != ''
    GROUP BY o.customer_phone ORDER BY total_spent DESC LIMIT 20");
$topCustomersStmt->execute([$dateFrom, $dateTo]);
$topCustomers = $topCustomersStmt->fetchAll();

// By district
$districtStmt = $db->prepare("SELECT d.name_mn as district_name,
    COUNT(*) as order_count,
    SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END) as total_revenue
    FROM orders o
    JOIN districts d ON o.district_id = d.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY o.district_id ORDER BY order_count DESC");
$districtStmt->execute([$dateFrom, $dateTo]);
$districtData = $districtStmt->fetchAll();

// ══════════════════════════════════════════════════
//  CARGO DATA
// ══════════════════════════════════════════════════
$cargoStmt = $db->prepare("SELECT cb.*,
    COUNT(o.id) as order_count,
    SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END) as total_revenue,
    SUM(CASE WHEN o.payment_status = 'paid' THEN o.cargo_fee ELSE 0 END) as total_cargo_fee,
    SUM(oi_w.total_weight) as total_weight
    FROM cargo_batches cb
    LEFT JOIN orders o ON o.cargo_batch_id = cb.id AND o.status != 'cancelled'
    LEFT JOIN (SELECT order_id, SUM(weight_kg * quantity) as total_weight FROM order_items GROUP BY order_id) oi_w ON oi_w.order_id = o.id
    WHERE cb.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
       OR cb.id IN (SELECT DISTINCT cargo_batch_id FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND cargo_batch_id IS NOT NULL)
    GROUP BY cb.id ORDER BY cb.created_at DESC");
$cargoStmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$cargoData = $cargoStmt->fetchAll();

// ══════════════════════════════════════════════════
//  STOCK / WAREHOUSE DATA  (current state, no date range)
// ══════════════════════════════════════════════════
$stockShopFilter = (int)($_GET['stock_shop'] ?? 0);

$allShops = $db->query("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();

$stockSummary = $db->query("SELECT
    COUNT(*) as total_products,
    COALESCE(SUM(stock), 0) as total_units,
    SUM(stock = 0) as out_of_stock,
    SUM(stock > 0 AND stock <= 5) as low_stock,
    SUM(stock > 5) as healthy
    FROM products WHERE type = 'ready' AND is_active = 1")->fetch();

$shopStockData = $db->query("SELECT s.id as shop_id, s.name as shop_name,
    COUNT(p.id) as total_products,
    COALESCE(SUM(p.stock), 0) as total_units,
    SUM(p.stock = 0) as out_of_stock,
    SUM(p.stock > 0 AND p.stock <= 5) as low_stock
    FROM products p JOIN shops s ON p.shop_id = s.id
    WHERE p.type = 'ready' AND p.is_active = 1
    GROUP BY s.id ORDER BY s.sort_order, s.name")->fetchAll();

$stockWhere = "p.type = 'ready' AND p.is_active = 1";
$stockParams = [];
if ($stockShopFilter) { $stockWhere .= " AND p.shop_id = ?"; $stockParams[] = $stockShopFilter; }
$stockProductsStmt = $db->prepare("SELECT p.id, p.name, p.name_mn, p.stock, p.has_variants, p.image,
    c.name as category_name, s.name as shop_name, s.id as shop_id
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN shops s ON p.shop_id = s.id
    WHERE $stockWhere ORDER BY p.stock ASC, s.name, p.name_mn");
$stockProductsStmt->execute($stockParams);
$stockProducts = $stockProductsStmt->fetchAll();

$variantMap = [];
$variantProductIds = array_column(array_filter($stockProducts, fn($p) => $p['has_variants']), 'id');
if (!empty($variantProductIds)) {
    $vPh = implode(',', array_fill(0, count($variantProductIds), '?'));
    $vStmt = $db->prepare("SELECT pv.product_id, pv.stock,
        TRIM(CONCAT(COALESCE(pc.name_mn,''), ' ', COALESCE(ps.name,''))) as label
        FROM product_variants pv
        LEFT JOIN product_colors pc ON pv.color_id = pc.id
        LEFT JOIN product_sizes ps ON pv.size_id = ps.id
        WHERE pv.product_id IN ($vPh) AND pv.is_active = 1
        ORDER BY pv.product_id, pv.id");
    $vStmt->execute($variantProductIds);
    foreach ($vStmt->fetchAll() as $v) { $variantMap[$v['product_id']][] = $v; }
}

require_once __DIR__ . '/../includes/header.php';

$statusLabels = [
    'pending' => 'Хүлээгдэж буй', 'confirmed' => 'Баталгаажсан',
    'cargo_shipping' => 'Тээвэрлэж буй', 'cargo_arrived' => 'Ачаа ирсэн',
    'ready_pickup' => 'Бэлэн', 'delivering' => 'Хүргэж буй',
    'delivered' => 'Хүргэгдсэн', 'picked_up' => 'Очиж авсан',
    'completed' => 'Дууссан', 'cancelled' => 'Цуцлагдсан',
];
$batch_statuses = ['open' => 'Нээлттэй', 'closed' => 'Хаагдсан', 'shipping' => 'Тээвэрлэж буй', 'arrived' => 'Ирсэн'];
?>

<!-- Date Range Filter -->
<div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-100 p-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="page" value="reports">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Хугацаа</label>
            <select name="range" onchange="toggleCustomDates(this)" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Өнөөдөр</option>
                <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>Энэ долоо хоног</option>
                <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>Энэ сар</option>
                <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>Энэ жил</option>
                <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>Бүх цаг</option>
                <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Сонгох</option>
            </select>
        </div>
        <div id="customDates" class="flex gap-2 <?= $range !== 'custom' ? 'hidden' : '' ?>">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Эхлэх</label>
                <input type="date" name="from" value="<?= e($dateFrom) ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Дуусах</label>
                <input type="date" name="to" value="<?= e($dateTo) ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>
        <?php if ($tab === 'stock'): ?>
        <div id="stockShopWrap">
            <label class="block text-xs text-gray-500 mb-1">Дэлгүүр</label>
            <select name="stock_shop" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Бүх дэлгүүр</option>
                <?php foreach ($allShops as $sh): ?>
                    <option value="<?= $sh['id'] ?>" <?= $stockShopFilter == $sh['id'] ? 'selected' : '' ?>><?= e($sh['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Харах</button>
        <a href="index.php?page=reports&tab=<?= e($tab) ?>&range=<?= e($range) ?>&from=<?= e($dateFrom) ?>&to=<?= e($dateTo) ?>&stock_shop=<?= $stockShopFilter ?>&export=csv" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">CSV Татах</a>
    </form>
    <p class="text-xs text-gray-400 mt-2"><?= date('Y.m.d', strtotime($dateFrom)) ?> — <?= date('Y.m.d', strtotime($dateTo)) ?></p>
</div>

<!-- Tabs -->
<div class="mb-6 flex gap-1 bg-gray-100 rounded-lg p-1 w-fit">
    <?php
    $tabs = [
        'sales' => 'Борлуулалт',
        'products' => 'Бүтээгдэхүүн',
        'customers' => 'Хэрэглэгч',
        'cargo' => 'Ачаа',
        'stock' => 'Агуулах',
    ];
    foreach ($tabs as $key => $label): ?>
        <a href="index.php?page=reports&tab=<?= $key ?>&range=<?= e($range) ?>&from=<?= e($dateFrom) ?>&to=<?= e($dateTo) ?>"
           class="px-4 py-2 rounded-md text-sm font-medium transition <?= $tab === $key ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'sales'): ?>
<!-- ══════════════════════════════════════════ SALES TAB ══════════════════════════════════════════ -->

<!-- Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Нийт орлого</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= formatPrice($sales['total_revenue'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-1">Дундаж: <?= formatPrice($sales['avg_order_value'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Нийт захиалга</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $sales['total_orders'] ?? 0 ?></p>
        <p class="text-xs text-gray-400 mt-1">Төлсөн: <?= $sales['paid_orders'] ?? 0 ?> | Цуцалсан: <?= $sales['cancelled_orders'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">POS / Онлайн</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $sales['pos_orders'] ?? 0 ?> / <?= $sales['online_orders'] ?? 0 ?></p>
        <p class="text-xs text-gray-400 mt-1">POS: <?= formatPrice($sales['pos_revenue'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Хүргэлт / Очиж авах</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $sales['delivery_orders'] ?? 0 ?> / <?= $sales['pickup_orders'] ?? 0 ?></p>
        <p class="text-xs text-gray-400 mt-1">Ачаа: <?= formatPrice($sales['total_cargo_fee'] ?? 0) ?></p>
    </div>
</div>

<!-- Revenue Chart -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="font-semibold text-gray-900 mb-4">Өдөр тутмын орлого</h3>
    <div class="h-64" id="revenueChart">
        <?php if (empty($dailyData)): ?>
            <div class="flex items-center justify-center h-full text-gray-400">Мэдээлэл байхгүй байна</div>
        <?php else: ?>
            <canvas id="dailyRevenueCanvas"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- Revenue by Type Chart -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="font-semibold text-gray-900 mb-4">Өдөр тутмын орлого (Онлайн / POS)</h3>
    <div class="h-64" id="revenueByTypeChart">
        <?php if (empty($dailyTypeData)): ?>
            <div class="flex items-center justify-center h-full text-gray-400">Мэдээлэл байхгүй байна</div>
        <?php else: ?>
            <canvas id="dailyRevenueByTypeCanvas"></canvas>
        <?php endif; ?>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    <!-- Status Breakdown -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Захиалгын төлөв</h3>
        <div class="space-y-3">
            <?php $totalForPercent = max(array_sum(array_column($statusBreakdown, 'cnt')), 1); ?>
            <?php foreach ($statusBreakdown as $s): ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 flex-1">
                    <span class="text-sm text-gray-700"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span>
                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full <?= $s['status'] === 'cancelled' ? 'bg-red-400' : ($s['status'] === 'completed' ? 'bg-green-400' : 'bg-blue-400') ?>"
                             style="width: <?= round($s['cnt'] / $totalForPercent * 100) ?>%"></div>
                    </div>
                </div>
                <span class="text-sm font-medium text-gray-900 ml-3"><?= $s['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Hourly Distribution -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Цагийн тархалт</h3>
        <?php if (empty($hourlyData)): ?>
            <div class="flex items-center justify-center h-32 text-gray-400 text-sm">Мэдээлэл байхгүй</div>
        <?php else: ?>
        <div class="space-y-2">
            <?php $maxHourly = max(array_column($hourlyData, 'cnt')); ?>
            <?php foreach ($hourlyData as $h): ?>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 w-10"><?= str_pad($h['hour'], 2, '0', STR_PAD_LEFT) ?>:00</span>
                <div class="flex-1 h-4 bg-gray-100 rounded overflow-hidden">
                    <div class="h-full bg-violet-400 rounded" style="width: <?= round($h['cnt'] / max($maxHourly, 1) * 100) ?>%"></div>
                </div>
                <span class="text-xs font-medium text-gray-700 w-8 text-right"><?= $h['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'products'): ?>
<!-- ══════════════════════════════════════════ PRODUCTS TAB ══════════════════════════════════════════ -->

<!-- Best Sellers -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Шилдэг бүтээгдэхүүн (орлогоор)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Бүтээгдэхүүн</th>
                    <th class="px-5 py-3 text-left">Дэлгүүр</th>
                    <th class="px-5 py-3 text-left">Ангилал</th>
                    <th class="px-5 py-3 text-right">Тоо ширхэг</th>
                    <th class="px-5 py-3 text-right">Захиалга</th>
                    <th class="px-5 py-3 text-right">Орлого</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($bestSellers as $i => $bs): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <?php if ($bs['image']): ?>
                                <img src="<?= e($bs['image']) ?>" alt="" class="w-10 h-10 rounded-lg object-cover bg-gray-100">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                            <?php endif; ?>
                            <span class="font-medium text-gray-900"><?= e($bs['product_name']) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-600"><?= e($bs['shop_name'] ?? '-') ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= e($bs['category_name'] ?? '-') ?></td>
                    <td class="px-5 py-3 text-right font-medium"><?= $bs['total_qty'] ?></td>
                    <td class="px-5 py-3 text-right"><?= $bs['order_count'] ?></td>
                    <td class="px-5 py-3 text-right font-medium text-green-600"><?= formatPrice($bs['total_revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bestSellers)): ?>
                <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">Энэ хугацаанд борлуулалт байхгүй</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    <!-- By Category -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Ангилалаар</h3>
        <?php if (empty($categoryData)): ?>
            <p class="text-sm text-gray-400 text-center py-4">Мэдээлэл байхгүй</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php $maxCat = max(array_column($categoryData, 'total_revenue') ?: [1]); ?>
            <?php foreach ($categoryData as $cat): ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-700"><?= e($cat['name_mn'] ?: $cat['category_name']) ?></span>
                    <span class="font-medium"><?= formatPrice($cat['total_revenue']) ?> <span class="text-gray-400 font-normal">(<?= $cat['total_qty'] ?>ш)</span></span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-400 rounded-full" style="width: <?= round($cat['total_revenue'] / max($maxCat, 1) * 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- By Shop -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Дэлгүүрээр</h3>
        <?php if (empty($shopData)): ?>
            <p class="text-sm text-gray-400 text-center py-4">Мэдээлэл байхгүй</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php $maxShop = max(array_column($shopData, 'total_revenue') ?: [1]); ?>
            <?php foreach ($shopData as $shop): ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-700"><?= e($shop['shop_name']) ?></span>
                    <span class="font-medium"><?= formatPrice($shop['total_revenue']) ?> <span class="text-gray-400 font-normal">(<?= $shop['total_qty'] ?>ш)</span></span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-purple-400 rounded-full" style="width: <?= round($shop['total_revenue'] / max($maxShop, 1) * 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Low Stock -->
<?php if (!empty($lowStock)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">⚠ Нөөц бага бүтээгдэхүүн</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Бүтээгдэхүүн</th>
                    <th class="px-5 py-3 text-left">Дэлгүүр</th>
                    <th class="px-5 py-3 text-left">Ангилал</th>
                    <th class="px-5 py-3 text-right">Нөөц</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($lowStock as $ls): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-900"><?= e($ls['name']) ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= e($ls['shop_name'] ?? '-') ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= e($ls['category_name'] ?? '-') ?></td>
                    <td class="px-5 py-3 text-right">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $ls['stock'] <= 0 ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' ?>">
                            <?= $ls['stock'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'customers'): ?>
<!-- ══════════════════════════════════════════ CUSTOMERS TAB ══════════════════════════════════════════ -->

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Идэвхтэй хэрэглэгч</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $customerStats['unique_customers'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Шинэ хэрэглэгч</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $customerStats['new_customers'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Нийт бүртгэлтэй</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $db->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?></p>
    </div>
</div>

<!-- Daily Registration Chart -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="font-semibold text-gray-900 mb-4">Өдөр тутмын бүртгэл</h3>
    <div class="h-64" id="registrationChart">
        <?php if (empty($dailyRegData)): ?>
            <div class="flex items-center justify-center h-full text-gray-400">Мэдээлэл байхгүй байна</div>
        <?php else: ?>
            <canvas id="dailyRegistrationCanvas"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- Top Customers -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Шилдэг хэрэглэгчид (зарцуулалтаар)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Нэр</th>
                    <th class="px-5 py-3 text-left">Утас</th>
                    <th class="px-5 py-3 text-right">Захиалга</th>
                    <th class="px-5 py-3 text-right">Нийт зарцуулсан</th>
                    <th class="px-5 py-3 text-left">Сүүлийн захиалга</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($topCustomers as $i => $tc): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-5 py-3 font-medium text-gray-900"><?= e($tc['customer_name']) ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= e($tc['customer_phone']) ?></td>
                    <td class="px-5 py-3 text-right"><?= $tc['order_count'] ?></td>
                    <td class="px-5 py-3 text-right font-medium text-green-600"><?= formatPrice($tc['total_spent']) ?></td>
                    <td class="px-5 py-3 text-gray-500"><?= formatDateShort($tc['last_order']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($topCustomers)): ?>
                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">Энэ хугацаанд хэрэглэгч байхгүй</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- By District -->
<?php if (!empty($districtData)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="font-semibold text-gray-900 mb-4">Дүүргээр</h3>
    <div class="space-y-3">
        <?php $maxDist = max(array_column($districtData, 'order_count') ?: [1]); ?>
        <?php foreach ($districtData as $dist): ?>
        <div>
            <div class="flex justify-between text-sm mb-1">
                <span class="text-gray-700"><?= e($dist['district_name']) ?></span>
                <span class="font-medium"><?= $dist['order_count'] ?> захиалга · <?= formatPrice($dist['total_revenue']) ?></span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-teal-400 rounded-full" style="width: <?= round($dist['order_count'] / max($maxDist, 1) * 100) ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'cargo'): ?>
<!-- ══════════════════════════════════════════ CARGO TAB ══════════════════════════════════════════ -->

<?php if (empty($cargoData)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
        <p class="text-gray-400">Энэ хугацаанд ачааны багц байхгүй</p>
    </div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($cargoData as $batch): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-gray-900"><?= e($batch['name']) ?></h3>
                <p class="text-xs text-gray-500 mt-1">Хугацаа: <?= formatDateShort($batch['due_date']) ?> · Тариф: <?= formatPrice($batch['cargo_rate_per_kg']) ?>/кг</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-medium 
                <?= $batch['status'] === 'arrived' ? 'bg-green-100 text-green-700' : 
                   ($batch['status'] === 'shipping' ? 'bg-blue-100 text-blue-700' : 
                   ($batch['status'] === 'closed' ? 'bg-gray-100 text-gray-700' : 'bg-orange-100 text-orange-700')) ?>">
                <?= $batch_statuses[$batch['status']] ?? $batch['status'] ?>
            </span>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-500">Захиалга</p>
                <p class="text-lg font-bold"><?= $batch['order_count'] ?> <span class="text-sm font-normal text-gray-400">(<?= $batch['paid_count'] ?> төлсөн)</span></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Нийт орлого</p>
                <p class="text-lg font-bold text-green-600"><?= formatPrice($batch['total_revenue']) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Ачааны төлбөр</p>
                <p class="text-lg font-bold"><?= formatPrice($batch['total_cargo_fee']) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Нийт жин</p>
                <p class="text-lg font-bold"><?= number_format($batch['total_weight'] ?? 0, 1) ?> кг</p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'stock'): ?>
<!-- ══════════════════════════════════════════ STOCK / WAREHOUSE TAB ══════════════════════════════════════════ -->

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Нийт бэлэн бараа</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stockSummary['total_products']) ?></p>
        <p class="text-xs text-gray-400 mt-1">Идэвхтэй SKU</p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500">Нийт үлдэгдэл</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= number_format($stockSummary['total_units']) ?></p>
        <p class="text-xs text-gray-400 mt-1">ширхэг</p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-yellow-100">
        <p class="text-sm text-gray-500">Бага нөөц (≤5)</p>
        <p class="text-2xl font-bold text-yellow-600 mt-1"><?= number_format($stockSummary['low_stock']) ?></p>
        <p class="text-xs text-gray-400 mt-1">бүтээгдэхүүн</p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-red-100">
        <p class="text-sm text-gray-500">Нөөц дууссан</p>
        <p class="text-2xl font-bold text-red-600 mt-1"><?= number_format($stockSummary['out_of_stock']) ?></p>
        <p class="text-xs text-gray-400 mt-1">бүтээгдэхүүн</p>
    </div>
</div>

<!-- Per-shop breakdown -->
<?php if (!empty($shopStockData) && !$stockShopFilter): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="font-semibold text-gray-900 mb-4">Дэлгүүрээр</h3>
    <div class="space-y-3">
        <?php $maxUnits = max(array_column($shopStockData, 'total_units') ?: [1]); ?>
        <?php foreach ($shopStockData as $ss): ?>
        <div>
            <div class="flex items-center justify-between text-sm mb-1 gap-2">
                <a href="index.php?page=reports&tab=stock&stock_shop=<?= $ss['shop_id'] ?>" class="text-gray-700 hover:text-blue-600 font-medium truncate"><?= e($ss['shop_name']) ?></a>
                <div class="flex items-center gap-3 flex-shrink-0 text-xs text-gray-500">
                    <span class="font-semibold text-gray-900"><?= number_format($ss['total_units']) ?> ш</span>
                    <?php if ($ss['out_of_stock'] > 0): ?>
                        <span class="px-1.5 py-0.5 rounded bg-red-100 text-red-600"><?= $ss['out_of_stock'] ?> дууссан</span>
                    <?php endif; ?>
                    <?php if ($ss['low_stock'] > 0): ?>
                        <span class="px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-600"><?= $ss['low_stock'] ?> бага</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-blue-400 rounded-full" style="width: <?= $maxUnits > 0 ? round($ss['total_units'] / $maxUnits * 100) : 0 ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Product list -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">
            Агуулахын үлдэгдэл
            <?php if ($stockShopFilter): $sn = array_column($allShops, 'name', 'id')[$stockShopFilter] ?? ''; ?>
                — <span class="text-blue-600"><?= e($sn) ?></span>
                <a href="index.php?page=reports&tab=stock" class="ml-2 text-xs text-gray-400 hover:text-gray-600">✕ Цэвэрлэх</a>
            <?php endif; ?>
        </h3>
        <span class="text-sm text-gray-400"><?= count($stockProducts) ?> бүтээгдэхүүн</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left">Бүтээгдэхүүн</th>
                    <?php if (!$stockShopFilter): ?><th class="px-5 py-3 text-left">Дэлгүүр</th><?php endif; ?>
                    <th class="px-5 py-3 text-left">Ангилал</th>
                    <th class="px-5 py-3 text-center">Нөөц</th>
                    <th class="px-5 py-3 text-left">Варианты дэлгэрэнгүй</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($stockProducts)): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">Бүтээгдэхүүн олдсонгүй</td></tr>
                <?php endif; ?>
                <?php foreach ($stockProducts as $sp):
                    $variants = $variantMap[$sp['id']] ?? [];
                    $stockClass = $sp['stock'] == 0
                        ? 'bg-red-100 text-red-700'
                        : ($sp['stock'] <= 5 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700');
                    $stockLabel = $sp['stock'] == 0 ? 'Дууссан' : ($sp['stock'] <= 5 ? 'Бага' : 'Хэвийн');
                ?>
                <tr class="hover:bg-gray-50 <?= $sp['stock'] == 0 ? 'opacity-60' : '' ?>">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <?php if ($sp['image']): ?>
                                <img src="<?= e($sp['image']) ?>" alt="" class="w-9 h-9 rounded-lg object-cover bg-gray-100 flex-shrink-0">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-300 flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-medium text-gray-900"><?= e($sp['name_mn']) ?></p>
                                <p class="text-xs text-gray-400"><?= e($sp['name']) ?></p>
                            </div>
                        </div>
                    </td>
                    <?php if (!$stockShopFilter): ?>
                    <td class="px-5 py-3 text-gray-600">
                        <a href="index.php?page=reports&tab=stock&stock_shop=<?= $sp['shop_id'] ?>" class="hover:text-blue-600"><?= e($sp['shop_name'] ?? '-') ?></a>
                    </td>
                    <?php endif; ?>
                    <td class="px-5 py-3 text-gray-500 text-xs"><?= e($sp['category_name'] ?? '-') ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $stockClass ?>">
                            <?= $sp['stock'] ?>
                            <span class="font-normal opacity-70"><?= $stockLabel ?></span>
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        <?php if (!empty($variants)): ?>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($variants as $v): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border <?= $v['stock'] == 0 ? 'border-red-200 bg-red-50 text-red-600' : ($v['stock'] <= 5 ? 'border-yellow-200 bg-yellow-50 text-yellow-700' : 'border-gray-200 bg-gray-50 text-gray-600') ?>">
                                        <?= e($v['label']) ?>
                                        <span class="font-semibold"><?= $v['stock'] ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-gray-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- Chart.js (lightweight, loads from CDN only when chart needed) -->
<?php if (($tab === 'sales' && (!empty($dailyData) || !empty($dailyTypeData))) || ($tab === 'customers' && !empty($dailyRegData))): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('dailyRevenueCanvas');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d['day'])), $dailyData)) ?>,
            datasets: [{
                label: 'Орлого',
                data: <?= json_encode(array_column($dailyData, 'revenue')) ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.5)',
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 1,
                borderRadius: 4,
            }, {
                label: 'Захиалга',
                data: <?= json_encode(array_column($dailyData, 'orders')) ?>,
                type: 'line',
                borderColor: 'rgb(234, 88, 12)',
                backgroundColor: 'rgba(234, 88, 12, 0.1)',
                yAxisID: 'y1',
                tension: 0.3,
                pointRadius: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => (v/1000) + 'k' } },
                y1: { position: 'right', beginAtZero: true, grid: { display: false } }
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

const typeCtx = document.getElementById('dailyRevenueByTypeCanvas');
if (typeCtx) {
    new Chart(typeCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d['day'])), $dailyTypeData)) ?>,
            datasets: [{
                label: 'Онлайн',
                data: <?= json_encode(array_column($dailyTypeData, 'online_revenue')) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1,
                borderRadius: 4,
            }, {
                label: 'POS',
                data: <?= json_encode(array_column($dailyTypeData, 'pos_revenue')) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.6)',
                borderColor: 'rgb(16, 185, 129)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { callback: v => (v/1000) + 'k' } }
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>
<?php endif; ?>

<?php if ($tab === 'customers' && !empty($dailyRegData)): ?>
<script>
const regCtx = document.getElementById('dailyRegistrationCanvas');
if (regCtx) {
    new Chart(regCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d['day'])), $dailyRegData)) ?>,
            datasets: [{
                label: 'Бүртгэл',
                data: <?= json_encode(array_column($dailyRegData, 'registrations')) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.5)',
                borderColor: 'rgb(16, 185, 129)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>
<?php endif; ?>

<script>
function toggleCustomDates(sel) {
    document.getElementById('customDates').classList.toggle('hidden', sel.value !== 'custom');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
