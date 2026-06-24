<?php
$pageTitle = 'Захиалга';
$db = getDB();

// Auto-cancel unpaid orders older than 1 hour
cancelExpiredOrders();
// Auto-confirm paid pending orders
confirmPaidOrders();

// Filters
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$fulfillmentFilter = $_GET['fulfillment'] ?? '';
$search = $_GET['search'] ?? '';
$productSearch = trim($_GET['product'] ?? '');
$cargoFeePaidFilter = $_GET['cargo_fee_paid'] ?? '';
$paymentMethodFilter = $_GET['payment_method'] ?? '';
$dateFilter = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['pg'] ?? 1));

// Sorting
$sortableColumns = [
    'order_number' => 'o.order_number',
    'customer'     => 'o.customer_name',
    'type'         => 'o.order_type',
    'fulfillment'  => 'o.fulfillment',
    'items'        => 'item_count',
    'total'        => 'o.total',
    'status'       => 'o.status',
    'payment'      => 'o.payment_status',
    'method'       => 'o.payment_method',
    'date'         => 'o.created_at',
    'confirmed'    => 'o.confirmed_at',
];
$sortCol = $_GET['sort'] ?? 'date';
$sortDir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$sortSql = isset($sortableColumns[$sortCol]) ? $sortableColumns[$sortCol] : 'o.created_at';

$where = ["1=1"];
$params = [];

if ($statusFilter) {
    $where[] = "o.status = ?";
    $params[] = $statusFilter;
}
if ($typeFilter) {
    $where[] = "o.order_type = ?";
    $params[] = $typeFilter;
}
if ($fulfillmentFilter) {
    $where[] = "o.fulfillment = ?";
    $params[] = $fulfillmentFilter;
}
if ($search) {
    $where[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($productSearch !== '') {
    $where[] = "EXISTS (SELECT 1 FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id AND (p.name LIKE ? OR p.name_mn LIKE ?))";
    $params[] = "%$productSearch%";
    $params[] = "%$productSearch%";
}
if ($cargoFeePaidFilter !== '') {
    $where[] = "o.cargo_fee > 0 AND o.cargo_fee_paid = ?";
    $params[] = (int)$cargoFeePaidFilter;
}
if ($paymentMethodFilter) {
    $where[] = "o.payment_method = ?";
    $params[] = $paymentMethodFilter;
}
if ($dateFilter) {
    $where[] = "DATE(o.created_at) = ?";
    $params[] = $dateFilter;
}

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmt = $db->prepare("SELECT o.*, 
    d.name_mn as district_name,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
    cb.name as batch_name
    FROM orders o 
    LEFT JOIN districts d ON o.district_id = d.id 
    LEFT JOIN cargo_batches cb ON o.cargo_batch_id = cb.id
    WHERE $whereStr 
    ORDER BY $sortSql $sortDir 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$filterUrl = 'index.php?page=orders';
if ($statusFilter) $filterUrl .= '&status=' . urlencode($statusFilter);
if ($typeFilter) $filterUrl .= '&type=' . urlencode($typeFilter);
if ($fulfillmentFilter) $filterUrl .= '&fulfillment=' . urlencode($fulfillmentFilter);
if ($search) $filterUrl .= '&search=' . urlencode($search);
if ($productSearch !== '') $filterUrl .= '&product=' . urlencode($productSearch);
if ($cargoFeePaidFilter !== '') $filterUrl .= '&cargo_fee_paid=' . urlencode($cargoFeePaidFilter);
if ($paymentMethodFilter) $filterUrl .= '&payment_method=' . urlencode($paymentMethodFilter);
if ($dateFilter) $filterUrl .= '&date=' . urlencode($dateFilter);
if ($sortCol !== 'date' || $sortDir !== 'DESC') {
    $filterUrl .= '&sort=' . urlencode($sortCol) . '&dir=' . urlencode(strtolower($sortDir));
}

// ── AJAX: Get SMS recipient list from current filters ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'sms_recipients') {
    header('Content-Type: application/json');

    $apiKey = ''; $fromNumber = '';
    $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('messagepro_api_key', 'messagepro_from_number')")->fetchAll();
    foreach ($rows as $row) {
        if ($row['setting_key'] === 'messagepro_api_key') $apiKey = $row['setting_value'];
        if ($row['setting_key'] === 'messagepro_from_number') $fromNumber = $row['setting_value'];
    }
    if (!$apiKey || !$fromNumber) {
        echo json_encode(['error' => 'SMS тохиргоо (MessagePro) хийгдээгүй байна.']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT MIN(o.customer_name) as customer_name, o.customer_phone,
               GROUP_CONCAT(DISTINCT o.order_number ORDER BY o.order_number SEPARATOR ', ') as order_numbers
        FROM orders o
        WHERE $whereStr
          AND o.customer_phone IS NOT NULL AND o.customer_phone != ''
        GROUP BY o.customer_phone
        ORDER BY MIN(o.created_at) DESC
        LIMIT 500
    ");
    $stmt->execute($params);

    $recipients = [];
    foreach ($stmt->fetchAll() as $r) {
        $phone = preg_replace('/[^0-9]/', '', $r['customer_phone']);
        if (strlen($phone) !== 8) continue;
        $recipients[] = [
            'phone'         => $phone,
            'name'          => $r['customer_name'] ?: 'Хэрэглэгч',
            'order_numbers' => $r['order_numbers'],
        ];
    }

    echo json_encode(['recipients' => $recipients, 'total' => count($recipients)]);
    exit;
}

// ── AJAX: Send single SMS via MessagePro ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'send_order_sms') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']);
        exit;
    }

    $phone   = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');
    $smsText = trim($data['text'] ?? '');
    if (strlen($phone) !== 8 || $smsText === '') {
        echo json_encode(['error' => 'Буруу утга']);
        exit;
    }

    $apiKey = ''; $fromNumber = '';
    $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('messagepro_api_key', 'messagepro_from_number')")->fetchAll();
    foreach ($rows as $row) {
        if ($row['setting_key'] === 'messagepro_api_key') $apiKey = $row['setting_value'];
        if ($row['setting_key'] === 'messagepro_from_number') $fromNumber = $row['setting_value'];
    }
    if (!$apiKey || !$fromNumber) {
        echo json_encode(['error' => 'SMS тохиргоо хийгдээгүй']);
        exit;
    }

    $url = 'https://api-text.callpro.mn/v1/sms/send?' . http_build_query([
        'from' => $fromNumber,
        'to'   => $phone,
        'text' => $smsText,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-api-key: $apiKey"],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['error' => 'cURL алдаа: ' . $curlError]);
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => "HTTP {$httpCode}", 'response' => mb_substr($response, 0, 200)]);
    }
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
    <p class="text-sm text-gray-500"><?= $total ?> захиалга</p>
    <div class="flex items-center gap-2">
        <button onclick="openSmsModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            SMS илгээх
        </button>
        <?php if (canAccessPage('order-create')): ?>
        <a href="index.php?page=order-create" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Захиалга үүсгэх
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <input type="hidden" name="page" value="orders">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Захиалгын №, нэр, утас..."
               class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        <input type="text" name="product" value="<?= e($productSearch) ?>" placeholder="Барааны нэрээр хайх..."
               class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх төлөв</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Хүлээгдэж буй</option>
            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Баталгаажсан</option>
            <option value="cargo_shipping" <?= $statusFilter === 'cargo_shipping' ? 'selected' : '' ?>>Ачаа тээвэрлэж буй</option>
            <option value="cargo_arrived" <?= $statusFilter === 'cargo_arrived' ? 'selected' : '' ?>>Ачаа ирсэн</option>
            <option value="ready_pickup" <?= $statusFilter === 'ready_pickup' ? 'selected' : '' ?>>Очиж авахад бэлэн</option>
            <option value="delivering" <?= $statusFilter === 'delivering' ? 'selected' : '' ?>>Хүргэж буй</option>
            <option value="partially_delivered" <?= $statusFilter === 'partially_delivered' ? 'selected' : '' ?>>Хэсэгчлэн хүргэсэн</option>
            <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Хүргэгдсэн</option>
            <option value="picked_up" <?= $statusFilter === 'picked_up' ? 'selected' : '' ?>>Очиж авсан</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Дууссан</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Цуцлагдсан</option>
        </select>
        <select name="type" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх төрөл</option>
            <option value="online" <?= $typeFilter === 'online' ? 'selected' : '' ?>>Онлайн</option>
            <option value="pos" <?= $typeFilter === 'pos' ? 'selected' : '' ?>>POS</option>
        </select>
        <select name="fulfillment" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүх хүргэлт</option>
            <option value="delivery" <?= $fulfillmentFilter === 'delivery' ? 'selected' : '' ?>>Хүргэлт</option>
            <option value="pickup" <?= $fulfillmentFilter === 'pickup' ? 'selected' : '' ?>>Очиж авах</option>
        </select>
        <select name="payment_method" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Төлбөрийн арга</option>
            <option value="qpay" <?= $paymentMethodFilter === 'qpay' ? 'selected' : '' ?>>QPay</option>
            <option value="transfer" <?= $paymentMethodFilter === 'transfer' ? 'selected' : '' ?>>Шилжүүлэг</option>
            <option value="bonum" <?= $paymentMethodFilter === 'bonum' ? 'selected' : '' ?>>Bonum</option>
            <option value="cash" <?= $paymentMethodFilter === 'cash' ? 'selected' : '' ?>>Бэлэн</option>
            <option value="card" <?= $paymentMethodFilter === 'card' ? 'selected' : '' ?>>Карт</option>
        </select>
        <select name="cargo_fee_paid" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Ачааны төлбөр</option>
            <option value="0" <?= $cargoFeePaidFilter === '0' ? 'selected' : '' ?>>Төлөөгүй</option>
            <option value="1" <?= $cargoFeePaidFilter === '1' ? 'selected' : '' ?>>Төлсөн</option>
        </select>
        <input type="date" name="date" value="<?= e($dateFilter) ?>"
               class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
               title="Огноогоор шүүх">
        <div class="flex gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Хайх</button>
            <a href="index.php?page=orders" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Цэвэрлэх</a>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <?php
                $baseSortUrl = preg_replace('/[&?](sort|dir)=[^&]*/','', $filterUrl);
                function sortTh($col, $label, $align, $currentCol, $currentDir, $baseUrl) {
                    $isActive = $col === $currentCol;
                    $nextDir = ($isActive && $currentDir === 'ASC') ? 'desc' : 'asc';
                    $arrow = '';
                    if ($isActive) {
                        $arrow = $currentDir === 'ASC'
                            ? '<svg class="w-3 h-3 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>'
                            : '<svg class="w-3 h-3 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
                    }
                    $url = $baseUrl . '&sort=' . urlencode($col) . '&dir=' . $nextDir;
                    $activeClass = $isActive ? 'text-gray-900' : 'text-gray-500';
                    echo '<th class="' . $align . ' px-5 py-3 text-xs font-medium uppercase whitespace-nowrap">';
                    echo '<a href="' . e($url) . '" class="' . $activeClass . ' hover:text-gray-900 transition-colors">' . $label . $arrow . '</a>';
                    echo '</th>';
                }
                ?>
                <tr>
                    <?php sortTh('order_number', 'Захиалга', 'text-left', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('customer', 'Хэрэглэгч', 'text-left', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('type', 'Төрөл', 'text-center', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('fulfillment', 'Хүргэлт', 'text-center', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('items', 'Тоо', 'text-center', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('total', 'Нийт', 'text-right', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('status', 'Төлөв', 'text-center', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('payment', 'Төлбөр', 'text-center', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('method', 'Арга', 'text-center', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('date', 'Огноо', 'text-right', $sortCol, $sortDir, $baseSortUrl); ?>
                    <?php sortTh('confirmed', 'Баталгаажсан', 'text-right', $sortCol, $sortDir, $baseSortUrl); ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="11" class="px-5 py-12 text-center text-gray-400">Захиалга олдсонгүй</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o): ?>
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='index.php?page=order-detail&id=<?= $o['id'] ?>&return=<?= urlencode($filterUrl) ?>'">
                    <td class="px-5 py-3">
                        <span class="text-sm font-medium text-blue-600"><?= e($o['order_number']) ?></span>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-sm font-medium text-gray-900"><?= e($o['customer_name'] ?: 'Жирийн') ?></p>
                        <p class="text-xs text-gray-400"><?= e($o['customer_phone'] ?: '-') ?></p>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $o['order_type'] === 'pos' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?>">
                            <?= strtoupper($o['order_type']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center text-sm text-gray-600">
                        <?= $o['fulfillment'] === 'pickup' ? '🏪 Очиж авах' : '🚚 Хүргэлт' ?>
                    </td>
                    <td class="px-5 py-3 text-center text-sm text-gray-600"><?= $o['item_count'] ?></td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-sm font-medium text-gray-900"><?= formatPrice($o['total']) ?></span>
                        <?php if ($o['cargo_fee'] > 0): ?>
                            <br><span class="text-xs <?= $o['cargo_fee_paid'] ? 'text-green-500' : 'text-red-500' ?>">ачаа: <?= formatPrice($o['cargo_fee']) ?> <?= $o['cargo_fee_paid'] ? '✓' : '✗' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center"><?= orderStatusLabel($o['status']) ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $o['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : ($o['payment_status'] === 'refunded' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') ?>">
                            <?= ucfirst($o['payment_status']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?php
                        $pmColors = ['qpay' => 'bg-cyan-100 text-cyan-700', 'transfer' => 'bg-purple-100 text-purple-700', 'cash' => 'bg-gray-100 text-gray-700', 'card' => 'bg-blue-100 text-blue-700', 'split' => 'bg-orange-100 text-orange-700', 'bonum' => 'bg-indigo-100 text-indigo-700'];
                        $pmLabels = ['qpay' => 'QPay', 'transfer' => 'Шилжүүлэг', 'cash' => 'Бэлэн', 'card' => 'Карт', 'split' => 'Хуваасан', 'bonum' => 'Bonum'];
                        $pmKey = $o['payment_method'];
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $pmColors[$pmKey] ?? 'bg-gray-100 text-gray-700' ?>">
                            <?= $pmLabels[$pmKey] ?? ucfirst($pmKey) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right text-xs text-gray-500"><?= formatDate($o['created_at']) ?></td>
                    <td class="px-5 py-3 text-right text-xs text-gray-500"><?= $o['confirmed_at'] ? formatDate($o['confirmed_at']) : '<span class="text-gray-300">—</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPagination($pagination, $filterUrl); ?>

<!-- ═══════════ SMS Modal ═══════════ -->
<div id="smsModal" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh]">

        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 flex-shrink-0">
            <div>
                <h2 class="text-base font-semibold text-gray-900">SMS Мэдэгдэл илгээх</h2>
                <p class="text-xs text-gray-400 mt-0.5">Одоогийн шүүлтийн дагуу захиалагчид</p>
            </div>
            <button onclick="closeSmsModal()" id="smsCloseBtn" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Loading -->
        <div id="smsLoading" class="flex items-center justify-center py-16">
            <div class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Error -->
        <div id="smsError" class="hidden mx-6 my-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg"></div>

        <!-- Content -->
        <div id="smsContent" class="hidden flex-1 overflow-y-auto px-6 py-4 space-y-4">

            <!-- Recipient count -->
            <div class="flex items-center gap-2 text-sm">
                <span class="px-2.5 py-1 bg-green-100 text-green-700 font-semibold rounded-full" id="smsCount">0</span>
                <span class="text-gray-500">хүлээн авагч (утасны дугаараар нэгтгэсэн)</span>
            </div>

            <!-- Template -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Мессежийн загвар
                    <span class="text-gray-400 font-normal ml-1">— <code class="bg-gray-100 px-1 rounded">{name}</code> <code class="bg-gray-100 px-1 rounded">{order_number}</code> ашиглах боломжтой</span>
                </label>
                <textarea id="smsTemplate" rows="3" oninput="updatePreview()"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none resize-none"
                    placeholder="Мессежийн текст..."></textarea>
                <p class="text-xs text-gray-400 mt-1 text-right" id="smsCharCount">0 тэмдэгт</p>
            </div>

            <!-- Recipients preview -->
            <div>
                <p class="text-xs font-medium text-gray-600 mb-1.5">Урьдчилан харах (эхний 5)</p>
                <div id="recipientsList" class="space-y-1.5 max-h-36 overflow-y-auto"></div>
            </div>

            <!-- Progress (hidden until send) -->
            <div id="smsProgress" class="hidden space-y-2">
                <div class="flex justify-between text-xs text-gray-500">
                    <span>Илгээж байна...</span>
                    <span id="smsProgressText">0 / 0</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div id="smsProgressBar" class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width:0%"></div>
                </div>
                <div id="smsResultsList" class="max-h-28 overflow-y-auto space-y-0.5 text-xs"></div>
            </div>
        </div>

        <!-- Footer buttons -->
        <div id="smsFooter" class="hidden flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
            <button onclick="closeSmsModal()" id="smsCancelBtn"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Болих</button>
            <button onclick="startSmsSending()" id="smsStartBtn"
                class="px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                Илгээх
            </button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= generateCSRFToken() ?>';
let smsRecipients = [];
let smsSending = false;

function openSmsModal() {
    document.getElementById('smsModal').classList.remove('hidden');
    document.getElementById('smsLoading').classList.remove('hidden');
    document.getElementById('smsContent').classList.add('hidden');
    document.getElementById('smsFooter').classList.add('hidden');
    document.getElementById('smsError').classList.add('hidden');
    document.getElementById('smsProgress').classList.add('hidden');
    document.getElementById('smsResultsList').innerHTML = '';
    smsRecipients = [];
    smsSending = false;
    loadSmsRecipients();
}

function closeSmsModal() {
    if (smsSending) return;
    document.getElementById('smsModal').classList.add('hidden');
}

async function loadSmsRecipients() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'sms_recipients');

    try {
        const res = await fetch('index.php?' + params.toString());
        const data = await res.json();
        document.getElementById('smsLoading').classList.add('hidden');

        if (data.error) {
            const el = document.getElementById('smsError');
            el.textContent = data.error;
            el.classList.remove('hidden');
            return;
        }

        smsRecipients = data.recipients;
        document.getElementById('smsCount').textContent = smsRecipients.length;

        const defaultMsg = <?= json_encode(
            $statusFilter === 'ready_pickup'   ? getSMSTemplate('order_ready_pickup', [],  'Сайн байна уу! Таны {order_number} захиалга очиж авахад бэлэн боллоо. - Runners World') :
            ($statusFilter === 'cargo_arrived' ? getSMSTemplate('order_general',      [],  'Сайн байна уу! Таны {order_number} захиалгын ачаа ирлээ. Ирж авна уу. - Runners World') :
            ($statusFilter === 'delivering'    ? getSMSTemplate('order_delivering',   [],  'Сайн байна уу! Таны {order_number} захиалга хүргэгдэж байна. - Runners World') :
            getSMSTemplate('order_general', [], 'Сайн байна уу! Таны {order_number} захиалгатай холбоотой мэдэгдэл. - Runners World')))
        ) ?>;
        document.getElementById('smsTemplate').value = defaultMsg;

        updatePreview();
        document.getElementById('smsContent').classList.remove('hidden');
        document.getElementById('smsFooter').classList.remove('hidden');
    } catch (e) {
        document.getElementById('smsLoading').classList.add('hidden');
        const el = document.getElementById('smsError');
        el.textContent = 'Серверийн алдаа гарлаа.';
        el.classList.remove('hidden');
    }
}

function applyTemplate(template, r) {
    return template
        .replace(/{name}/g, r.name)
        .replace(/{order_number}/g, r.order_numbers);
}

function updatePreview() {
    const template = document.getElementById('smsTemplate').value;
    document.getElementById('smsCharCount').textContent = template.length + ' тэмдэгт';

    const list = document.getElementById('recipientsList');
    if (smsRecipients.length === 0) {
        list.innerHTML = '<p class="text-xs text-gray-400">Хүлээн авагч байхгүй.</p>';
        return;
    }
    list.innerHTML = smsRecipients.slice(0, 5).map(r => {
        const text = applyTemplate(template, r);
        return `<div class="rounded-lg border border-gray-100 bg-gray-50 p-2.5">
            <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs font-semibold text-gray-700">${r.phone}</span>
                <span class="text-xs text-gray-400">${r.name}</span>
            </div>
            <div class="text-xs text-gray-600">${text}</div>
        </div>`;
    }).join('');
}

async function startSmsSending() {
    const template = document.getElementById('smsTemplate').value.trim();
    if (!template || smsRecipients.length === 0) return;

    smsSending = true;
    document.getElementById('smsStartBtn').disabled = true;
    document.getElementById('smsCancelBtn').disabled = true;
    document.getElementById('smsCloseBtn').disabled = true;

    const progress    = document.getElementById('smsProgress');
    const progressBar = document.getElementById('smsProgressBar');
    const progressTxt = document.getElementById('smsProgressText');
    const resultsList = document.getElementById('smsResultsList');

    progress.classList.remove('hidden');
    resultsList.innerHTML = '';

    let sent = 0, failed = 0;
    const total = smsRecipients.length;

    for (let i = 0; i < total; i++) {
        const r    = smsRecipients[i];
        const text = applyTemplate(template, r);

        progressBar.style.width = (((i) / total) * 100) + '%';
        progressTxt.textContent = `${i + 1} / ${total}`;

        try {
            const res  = await fetch('index.php?page=orders&action=send_order_sms', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone: r.phone, text, csrf_token: csrfToken }),
            });
            const data = await res.json();
            if (data.success) {
                sent++;
                resultsList.innerHTML += `<div class="text-green-600">✓ ${r.phone} — ${r.name}</div>`;
            } else {
                failed++;
                resultsList.innerHTML += `<div class="text-red-500">✗ ${r.phone}: ${data.error || 'Алдаа'}</div>`;
            }
        } catch (e) {
            failed++;
            resultsList.innerHTML += `<div class="text-red-500">✗ ${r.phone}: Алдаа</div>`;
        }

        resultsList.scrollTop = resultsList.scrollHeight;
        await new Promise(resolve => setTimeout(resolve, 150));
    }

    progressBar.style.width = '100%';
    progressTxt.textContent = `Дууслаа — ${sent} амжилттай, ${failed} алдаатай`;

    smsSending = false;
    document.getElementById('smsCloseBtn').disabled = false;
    document.getElementById('smsCancelBtn').disabled = false;
    document.getElementById('smsStartBtn').disabled = false;
    document.getElementById('smsStartBtn').textContent = 'Дахин илгээх';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
