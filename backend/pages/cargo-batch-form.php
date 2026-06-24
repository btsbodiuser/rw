<?php
$pageTitle = isset($_GET['id']) ? 'Ачааны багц засах' : 'Шинэ ачааны багц';
$db = getDB();
$id = $_GET['id'] ?? null;
$batch = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM cargo_batches WHERE id = ?");
    $stmt->execute([$id]);
    $batch = $stmt->fetch();
    if (!$batch) {
        setFlash('error', 'Багц олдсонгүй.');
        header('Location: index.php?page=cargo-batches');
        exit;
    }
}

// ── AJAX: Test single SMS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'test_sms') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']);
        exit;
    }

    $phone = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');
    if (strlen($phone) !== 8) {
        echo json_encode(['error' => 'Утас 8 оронтой байх ёстой']);
        exit;
    }

    $apiKey = '';
    $fromNumber = '';
    $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('messagepro_api_key', 'messagepro_from_number')")->fetchAll();
    foreach ($rows as $row) {
        if ($row['setting_key'] === 'messagepro_api_key') $apiKey = $row['setting_value'];
        if ($row['setting_key'] === 'messagepro_from_number') $fromNumber = $row['setting_value'];
    }
    if (!$apiKey) {
        echo json_encode(['error' => 'messagepro_api_key тохиргоо хоосон']);
        exit;
    }
    if (!$fromNumber) {
        echo json_encode(['error' => 'messagepro_from_number тохиргоо хоосон']);
        exit;
    }

    $siteName = getSetting('site_name', 'Runners World');
    $smsText = "{$siteName} тест мессеж. Энэ бол туршилтын мэдэгдэл.";

    $url = 'https://api-text.callpro.mn/v1/sms/send?' . http_build_query([
        'from' => $fromNumber,
        'to' => $phone,
        'text' => $smsText,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["x-api-key: $apiKey"],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['error' => 'cURL алдаа: ' . $curlError, 'details' => ['from' => $fromNumber, 'to' => $phone]]);
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true, 'message' => "SMS илгээгдлээ: {$phone}", 'http_code' => $httpCode, 'response' => $response]);
    } else {
        echo json_encode(['error' => "HTTP {$httpCode}", 'response' => mb_substr($response, 0, 500), 'details' => ['from' => $fromNumber, 'to' => $phone, 'api_key_prefix' => mb_substr($apiKey, 0, 6) . '...']]);
    }
    exit;
}

// ── AJAX: Get SMS recipient list ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'sms_recipients') {
    header('Content-Type: application/json');
    if (!$batch || !in_array($batch['status'], ['receiving', 'arrived'])) {
        echo json_encode(['error' => 'Зөвхөн хүлээн авч байгаа эсвэл дууссан багцад мэдэгдэл илгээх боломжтой.']);
        exit;
    }

    $apiKey = '';
    $fromNumber = '';
    $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('messagepro_api_key', 'messagepro_from_number')")->fetchAll();
    foreach ($rows as $row) {
        if ($row['setting_key'] === 'messagepro_api_key') $apiKey = $row['setting_value'];
        if ($row['setting_key'] === 'messagepro_from_number') $fromNumber = $row['setting_value'];
    }
    if (!$apiKey || !$fromNumber) {
        echo json_encode(['error' => 'SMS тохиргоо (MessagePro) хийгдээгүй байна.']);
        exit;
    }

    $siteUrl = rtrim(getSetting('site_url', ''), '/');
    $siteName = getSetting('site_name', 'Runners World');

    // Only notify orders where ALL items have arrived (cargo_arrived status)
    $stmt = $db->prepare("
        SELECT o.customer_phone, GROUP_CONCAT(DISTINCT o.order_number ORDER BY o.order_number SEPARATOR ', ') as order_numbers
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        WHERE oi.cargo_batch_id = ?
          AND o.payment_status = 'paid'
          AND o.status = 'cargo_arrived'
          AND o.customer_phone IS NOT NULL
          AND o.customer_phone != ''
        GROUP BY o.customer_phone
    ");
    $stmt->execute([$id]);
    $recipients = [];

    foreach ($stmt->fetchAll() as $r) {
        $phone = preg_replace('/[^0-9]/', '', $r['customer_phone']);
        if (strlen($phone) !== 8) continue;

        $orderNums = $r['order_numbers'];
        /*
        $numCount = substr_count($orderNums, ',') + 1;
        if ($numCount <= 3) {
            $smsText = "Сайн байна уу! Таны ачаа ирлээ ({$orderNums}). Ирж авна уу.";
        } else {
            $smsText = "Сайн байна уу! Таны {$numCount} захиалгын ачаа ирлээ. Ирж авна уу.";
        }
        $smsText .= " 72228880.";
        */
        $smsText = getSMSTemplate('batch_notify', [], 'Tanii zahialsan baraa Solongosoos irlee. Hayag: BGD 26-r horoo Narnii horoolol 9-r bair, 72228880. Tue-Fri 10:00-19:00, Sat 11:00-19:00.');

        $recipients[] = ['phone' => $phone, 'text' => $smsText];
    }

    echo json_encode(['recipients' => $recipients, 'total' => count($recipients)]);
    exit;
}

// ── AJAX: Queue SMS (was: send_single_sms — now just queues, sending from sms-queue page) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'send_single_sms') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']); exit;
    }
    if (!$batch || !in_array($batch['status'], ['receiving', 'arrived'])) {
        echo json_encode(['error' => 'Зөвхөн хүлээн авч байгаа эсвэл дууссан багцад мэдэгдэл илгээх боломжтой.']); exit;
    }

    $phone   = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');
    $smsText = trim($data['text'] ?? '');
    if (strlen($phone) !== 8 || $smsText === '') {
        echo json_encode(['error' => 'Буруу утга']); exit;
    }

    $qid = queueSMS($phone, $smsText, 'batch_notify');
    if ($qid) {
        echo json_encode(['success' => true, 'queued' => true, 'phone' => $phone]);
    } else {
        echo json_encode(['error' => 'Дугаар буруу байна']);
    }
    exit;
}

// ── AJAX: Mark batch SMS sent ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'mark_sms_sent') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']);
        exit;
    }
    if ($batch) {
        $db->prepare("UPDATE cargo_batches SET sms_sent_at = NOW() WHERE id = ?")->execute([$id]);
        global $currentAdmin;
        auditLog('sms_send', 'cargo_batch', $id, 'admin', $currentAdmin['id'], [
            'sent' => (int)($data['sent'] ?? 0),
            'failed' => (int)($data['failed'] ?? 0),
        ]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'status') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=cargo-batches');
        exit;
    }
    $newStatus = $_POST['new_status'] ?? '';
    $validTransitions = [
        'open'      => 'closed',
        'closed'    => 'shipping',
        'shipping'  => 'receiving',
        'receiving' => 'arrived',
    ];
    if ($batch && isset($validTransitions[$batch['status']]) && $validTransitions[$batch['status']] === $newStatus) {
        $stmt = $db->prepare("UPDATE cargo_batches SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        // When batch closes, mark its products as order_status='closed'
        if ($newStatus === 'closed') {
            $db->prepare("UPDATE products SET order_status = 'closed' WHERE cargo_batch_id = ? AND type = 'preorder'")->execute([$id]);
        }

        // When shipping: mark all items as 'shipping' so recalc shows cargo_shipping to customers
        if ($newStatus === 'shipping') {
            $db->prepare("UPDATE order_items SET cargo_status = 'shipping' WHERE cargo_batch_id = ? AND cargo_status = 'pending'")->execute([$id]);
            $affectedOrders = $db->prepare("SELECT DISTINCT oi.order_id FROM order_items oi WHERE oi.cargo_batch_id = ?");
            $affectedOrders->execute([$id]);
            foreach ($affectedOrders->fetchAll(PDO::FETCH_COLUMN) as $affOrderId) {
                recalcOrderCargoStatus($db, (int)$affOrderId);
            }
        }

        // 'receiving' and 'arrived' do NOT bulk-update order_items.
        // Fulfillment happens through inventory arrivals (FIFO).

        $statusLabels = [
            'closed'    => 'Хаагдсан',
            'shipping'  => 'Тээвэрлэж буй',
            'receiving' => 'Хүлээн авч байна',
            'arrived'   => 'Дууссан',
        ];
        setFlash('success', 'Багцын төлөв "' . ($statusLabels[$newStatus] ?? $newStatus) . '" болж шинэчлэгдлээ.');
    } else {
        setFlash('error', 'Буруу төлөв шилжилт.');
    }
    header('Location: index.php?page=cargo-batches');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $dueDate = $_POST['due_date'] ?? '';
    $cargoRate = (float)($_POST['cargo_rate_per_kg'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $errors = [];
    if (!$name) $errors[] = 'Багцын нэр шаардлагатай.';
    if (!$dueDate) $errors[] = 'Хугацаа шаардлагатай.';
    if ($cargoRate <= 0) $errors[] = 'Ачааны тариф 0-ээс их байх ёстой.';

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE cargo_batches SET name=?, due_date=?, cargo_rate_per_kg=?, notes=? WHERE id=?");
            $stmt->execute([$name, $dueDate, $cargoRate, $notes, $id]);
            setFlash('success', 'Багц шинэчлэгдлээ.');
        } else {
            $stmt = $db->prepare("INSERT INTO cargo_batches (name, due_date, cargo_rate_per_kg, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $dueDate, $cargoRate, $notes]);
            setFlash('success', 'Багц үүсгэгдлээ.');
            header('Location: index.php?page=cargo-batches');
            exit;
        }
        if (empty($errors)) {
            header('Location: index.php?page=cargo-batches');
            exit;
        }
    }
    $batch = $_POST;
}

$currentRate = getSetting('cargo_rate_per_kg', '8000');

// "Until" date filter — show orders created up to and including this day
$untilDate = trim($_GET['until'] ?? '');
$untilCutoff = null;
if ($untilDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $untilDate)) {
    $untilCutoff = $untilDate . ' 23:59:59';
}

// ── Export: CSV ──
$exportType = $_GET['export'] ?? '';
if ($exportType === 'csv' && $id && $batch) {
    // Re-fetch the data (same queries as below)
    $sqlUntil = $untilCutoff ? "AND o.created_at <= ?" : '';
    $params = $untilCutoff ? [$id, $untilCutoff] : [$id];
    $productSummaryStmt = $db->prepare("
        SELECT
            oi.product_id,
            p.name_mn,
            p.barcode,
            p.image,
            SUM(oi.quantity) as total_qty,
            SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity ELSE 0 END) as paid_qty,
            SUM(CASE WHEN o.payment_status != 'paid' THEN oi.quantity ELSE 0 END) as unpaid_qty,
            SUM(oi.quantity * oi.product_price) as total_amount,
            SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity * oi.product_price ELSE 0 END) as paid_amount
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.cargo_batch_id = ? $sqlUntil
        GROUP BY oi.product_id, p.name_mn, p.barcode, p.image
        ORDER BY total_qty DESC
    ");
    $productSummaryStmt->execute($params);
    $productSummary = $productSummaryStmt->fetchAll();

    // Variant breakdown for export (mirrors the on-page table)
    $variantStmt = $db->prepare("
        SELECT
            oi.product_id,
            oi.variant_id,
            oi.variant_label,
            SUM(oi.quantity) as total_qty,
            SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity ELSE 0 END) as paid_qty,
            SUM(CASE WHEN o.payment_status != 'paid' THEN oi.quantity ELSE 0 END) as unpaid_qty,
            SUM(oi.quantity * oi.product_price) as total_amount,
            SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity * oi.product_price ELSE 0 END) as paid_amount
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.cargo_batch_id = ? AND oi.variant_id IS NOT NULL $sqlUntil
        GROUP BY oi.product_id, oi.variant_id, oi.variant_label
        ORDER BY oi.product_id, oi.variant_label
    ");
    $variantStmt->execute($params);
    $variantsByProduct = [];
    foreach ($variantStmt->fetchAll() as $vb) {
        $variantsByProduct[(int)$vb['product_id']][] = $vb;
    }

    $dateSuffix = $untilDate ? '_until_' . $untilDate : '';
    $filename = 'Cargo_Batch_Product_Summary_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $batch['name']) . $dateSuffix . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 recognition
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers
    fputcsv($out, ['Бараа', 'Хувилбар', 'Баркод', 'Нийт тоо', 'Төлсөн', 'Төлөөгүй', 'Нийт дүн', 'Төлсөн дүн']);

    // Data rows
    foreach ($productSummary as $ps) {
        fputcsv($out, [
            $ps['name_mn'],
            '',
            $ps['barcode'] ?: '',
            $ps['total_qty'],
            $ps['paid_qty'],
            $ps['unpaid_qty'],
            $ps['total_amount'],
            $ps['paid_amount'],
        ]);
        $variants = $variantsByProduct[(int)$ps['product_id']] ?? [];
        foreach ($variants as $v) {
            fputcsv($out, [
                '  └ ' . $ps['name_mn'],
                $v['variant_label'] ?: ('Хувилбар #' . $v['variant_id']),
                '',
                $v['total_qty'],
                $v['paid_qty'],
                $v['unpaid_qty'],
                $v['total_amount'],
                $v['paid_amount'],
            ]);
        }
    }

    // Summary row
    fputcsv($out, []);
    fputcsv($out, [
        'НИЙТ',
        '',
        '',
        array_sum(array_column($productSummary, 'total_qty')),
        array_sum(array_column($productSummary, 'paid_qty')),
        array_sum(array_column($productSummary, 'unpaid_qty')),
        array_sum(array_column($productSummary, 'total_amount')),
        array_sum(array_column($productSummary, 'paid_amount')),
    ]);

    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=cargo-batches" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Ачааны багц руу буцах
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Багцын нэр *</label>
            <input type="text" name="name" value="<?= e($batch['name'] ?? '') ?>" required
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                   placeholder="e.g. Week 12 - March 2026">
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Хугацаа *</label>
                <input type="datetime-local" name="due_date" 
                       value="<?= e($batch ? date('Y-m-d\TH:i', strtotime($batch['due_date'] ?? 'now')) : '') ?>" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <p class="text-xs text-gray-400 mt-1">Урьдчилсан захиалга энэ хугацаанд хаагдана</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ачааны тариф КГ (₮) *</label>
                <input type="number" name="cargo_rate_per_kg" value="<?= e($batch['cargo_rate_per_kg'] ?? $currentRate) ?>" min="0" step="100" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <p class="text-xs text-gray-400 mt-1">Одоогийн ерөнхий тариф: <?= formatPrice($currentRate) ?>/кг</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Тэмдэглэл</label>
            <textarea name="notes" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-vertical"
                      placeholder="Тээвэрлэлтийн тухай тэмдэглэл..."><?= e($batch['notes'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="index.php?page=cargo-batches" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Болих</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <?= $id ? 'Шинэчлэх' : 'Үүсгэх' ?> багц
            </button>
        </div>
    </form>
</div>

<?php if ($id && $batch): ?>
<?php
// Fetch orders belonging to this batch (via order_items)
$batchOrders = $db->prepare("
    SELECT DISTINCT o.*, d.name_mn as district_name, k.number as khoroo_number, COALESCE(k.name, '') as khoroo_name
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id AND oi.cargo_batch_id = ?
    LEFT JOIN districts d ON o.district_id = d.id
    LEFT JOIN khoroos k ON o.khoroo_id = k.id
    ORDER BY o.created_at DESC
");
$batchOrders->execute([$id]);
$batchOrders = $batchOrders->fetchAll();

// Separate paid vs unpaid
$paidOrders = array_filter($batchOrders, fn($o) => $o['payment_status'] === 'paid');
$unpaidOrders = array_filter($batchOrders, fn($o) => $o['payment_status'] !== 'paid');

// Product summary: how many of each product ordered & paid
$psSqlUntil = $untilCutoff ? "AND o.created_at <= ?" : '';
$psParams = $untilCutoff ? [$id, $untilCutoff] : [$id];
$productSummary = $db->prepare("
    SELECT
        oi.product_id,
        p.name_mn,
        p.barcode,
        p.image,
        SUM(oi.quantity) as total_qty,
        SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity ELSE 0 END) as paid_qty,
        SUM(CASE WHEN o.payment_status != 'paid' THEN oi.quantity ELSE 0 END) as unpaid_qty,
        SUM(oi.quantity * oi.product_price) as total_amount,
        SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity * oi.product_price ELSE 0 END) as paid_amount
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.cargo_batch_id = ? $psSqlUntil
    GROUP BY oi.product_id, p.name_mn, p.barcode, p.image
    ORDER BY total_qty DESC
");
$productSummary->execute($psParams);
$productSummary = $productSummary->fetchAll();

// Variant breakdown per product (only for products that have variants)
$variantBreakdown = $db->prepare("
    SELECT
        oi.product_id,
        oi.variant_id,
        oi.variant_label,
        SUM(oi.quantity) as total_qty,
        SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity ELSE 0 END) as paid_qty,
        SUM(CASE WHEN o.payment_status != 'paid' THEN oi.quantity ELSE 0 END) as unpaid_qty
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.cargo_batch_id = ? AND oi.variant_id IS NOT NULL $psSqlUntil
    GROUP BY oi.product_id, oi.variant_id, oi.variant_label
    ORDER BY oi.product_id, oi.variant_label
");
$variantBreakdown->execute($psParams);

// Previous-day deltas: total_qty as of (untilDate - 1 day), or as of yesterday if no filter
$prevAnchorDate = $untilDate !== '' ? $untilDate : date('Y-m-d');
$prevCutoff = date('Y-m-d 23:59:59', strtotime($prevAnchorDate . ' -1 day'));
$prevQtyStmt = $db->prepare("
    SELECT oi.product_id, SUM(oi.quantity) as prev_qty
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.cargo_batch_id = ? AND o.created_at <= ?
    GROUP BY oi.product_id
");
$prevQtyStmt->execute([$id, $prevCutoff]);
$prevQtyMap = [];
foreach ($prevQtyStmt->fetchAll() as $r) {
    $prevQtyMap[(int)$r['product_id']] = (int)$r['prev_qty'];
}

// Variant-level deltas
$prevVariantStmt = $db->prepare("
    SELECT oi.product_id, oi.variant_id, SUM(oi.quantity) as prev_qty
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.cargo_batch_id = ? AND oi.variant_id IS NOT NULL AND o.created_at <= ?
    GROUP BY oi.product_id, oi.variant_id
");
$prevVariantStmt->execute([$id, $prevCutoff]);
$prevVariantQtyMap = [];
foreach ($prevVariantStmt->fetchAll() as $r) {
    $prevVariantQtyMap[(int)$r['product_id'] . '_' . (int)$r['variant_id']] = (int)$r['prev_qty'];
}
$variantsByProduct = [];
foreach ($variantBreakdown->fetchAll() as $vb) {
    $variantsByProduct[(int)$vb['product_id']][] = $vb;
}
?>

<?php if ($batch['status'] === 'arrived'): ?>
<div class="max-w-4xl mx-auto mt-8">
    <a href="index.php?page=batch-handout&id=<?= $id ?>" class="block w-full py-4 bg-indigo-600 text-white text-center rounded-xl text-lg font-bold hover:bg-indigo-700 transition-colors">
        📦 Түгээлт эхлэх →
    </a>
</div>
<?php endif; ?>

<!-- ═══════════ Summary Cards ═══════════ -->
<div class="max-w-4xl mx-auto mt-8 grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-gray-900"><?= count($batchOrders) ?></p>
        <p class="text-xs text-gray-500 mt-1">Нийт захиалга</p>
    </div>
    <div class="bg-green-50 rounded-xl border border-green-200 p-4 text-center">
        <p class="text-2xl font-bold text-green-700"><?= count($paidOrders) ?></p>
        <p class="text-xs text-green-600 mt-1">Төлсөн</p>
    </div>
    <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-4 text-center">
        <p class="text-2xl font-bold text-yellow-700"><?= count($unpaidOrders) ?></p>
        <p class="text-xs text-yellow-600 mt-1">Төлөөгүй</p>
    </div>
    <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 text-center">
        <p class="text-2xl font-bold text-blue-700"><?= formatPrice(array_sum(array_column($paidOrders, 'total'))) ?></p>
        <p class="text-xs text-blue-600 mt-1">Төлсөн дүн</p>
    </div>
</div>

<!-- ═══════════ Product Summary ═══════════ -->
<div class="max-w-4xl mx-auto mt-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <h3 class="text-lg font-semibold text-gray-900">📦 Бараа бүтээгдэхүүний нэгтгэл</h3>
            <form method="GET" class="flex items-end gap-2">
                <input type="hidden" name="page" value="cargo-batch-form">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">Хүртэл (оруулсан)</label>
                    <input type="date" name="until" value="<?= e($untilDate) ?>"
                        class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Шүүх</button>
                <?php if ($untilDate): ?>
                <a href="?page=cargo-batch-form&id=<?= $id ?>" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Цэвэрлэх</a>
                <?php endif; ?>
            </form>
            <a href="?page=cargo-batch-form&id=<?= $id ?>&export=csv<?= $untilDate ? '&until=' . urlencode($untilDate) : '' ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Excel татах
            </a>
        </div>

        <?php if (empty($productSummary)): ?>
            <p class="text-sm text-gray-400 text-center py-6">Бараа байхгүй.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-3 font-medium text-gray-600">Бараа</th>
                            <th class="text-center py-3 px-3 font-medium text-gray-600">Нийт тоо</th>
                            <th class="text-center py-3 px-3 font-medium text-green-600">Төлсөн</th>
                            <th class="text-center py-3 px-3 font-medium text-yellow-600">Төлөөгүй</th>
                            <th class="text-right py-3 px-3 font-medium text-gray-600">Нийт дүн</th>
                            <th class="text-right py-3 px-3 font-medium text-green-600">Төлсөн дүн</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($productSummary as $ps): ?>
                        <?php $variants = $variantsByProduct[(int)$ps['product_id']] ?? []; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-3">
                                    <?php if ($ps['image']): ?>
                                        <img src="<?= e($ps['image']) ?>" class="w-8 h-8 rounded object-cover" alt="">
                                    <?php else: ?>
                                        <div class="w-8 h-8 bg-gray-100 rounded flex items-center justify-center text-gray-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="text-gray-900 font-medium"><?= e($ps['name_mn']) ?></span>
                                        <?php if ($ps['barcode']): ?>
                                            <span class="text-xs text-gray-400 block"><?= e($ps['barcode']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-gray-900">
                                <?= $ps['total_qty'] ?>
                                <?php $delta = (int)$ps['total_qty'] - ($prevQtyMap[(int)$ps['product_id']] ?? 0); ?>
                                <?php if ($delta > 0): ?>
                                    <span class="ml-1 inline-block text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700" title="Өчигдрөөс хойш">+<?= $delta ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-center font-semibold text-green-700"><?= $ps['paid_qty'] ?></td>
                            <td class="py-3 px-3 text-center font-semibold text-yellow-700"><?= $ps['unpaid_qty'] ?></td>
                            <td class="py-3 px-3 text-right text-gray-700"><?= formatPrice($ps['total_amount']) ?></td>
                            <td class="py-3 px-3 text-right text-green-700"><?= formatPrice($ps['paid_amount']) ?></td>
                        </tr>
                        <?php if (!empty($variants)): ?>
                            <?php foreach ($variants as $v): ?>
                            <tr class="bg-gray-50/50">
                                <td class="py-2 px-3 pl-14">
                                    <span class="text-xs text-purple-600 font-medium">┗ <?= e($v['variant_label'] ?: 'Хувилбар #' . $v['variant_id']) ?></span>
                                </td>
                                <td class="py-2 px-3 text-center text-xs font-semibold text-gray-700">
                                    <?= $v['total_qty'] ?>
                                    <?php $vDelta = (int)$v['total_qty'] - ($prevVariantQtyMap[(int)$v['product_id'] . '_' . (int)$v['variant_id']] ?? 0); ?>
                                    <?php if ($vDelta > 0): ?>
                                        <span class="ml-1 inline-block text-[9px] font-semibold px-1 py-0 rounded-full bg-green-100 text-green-700">+<?= $vDelta ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-3 text-center text-xs font-medium text-green-600"><?= $v['paid_qty'] ?></td>
                                <td class="py-2 px-3 text-center text-xs font-medium text-yellow-600"><?= $v['unpaid_qty'] ?></td>
                                <td class="py-2 px-3"></td>
                                <td class="py-2 px-3"></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 bg-gray-50">
                            <td class="py-3 px-3 font-semibold text-gray-700">Нийт:</td>
                            <td class="py-3 px-3 text-center font-bold text-gray-900">
                                <?= array_sum(array_column($productSummary, 'total_qty')) ?>
                                <?php
                                $totalDelta = 0;
                                foreach ($productSummary as $ps) {
                                    $totalDelta += (int)$ps['total_qty'] - ($prevQtyMap[(int)$ps['product_id']] ?? 0);
                                }
                                ?>
                                <?php if ($totalDelta > 0): ?>
                                    <span class="ml-1 inline-block text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700" title="Өчигдрөөс хойш">+<?= $totalDelta ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-green-700"><?= array_sum(array_column($productSummary, 'paid_qty')) ?></td>
                            <td class="py-3 px-3 text-center font-bold text-yellow-700"><?= array_sum(array_column($productSummary, 'unpaid_qty')) ?></td>
                            <td class="py-3 px-3 text-right font-bold text-gray-900"><?= formatPrice(array_sum(array_column($productSummary, 'total_amount'))) ?></td>
                            <td class="py-3 px-3 text-right font-bold text-green-700"><?= formatPrice(array_sum(array_column($productSummary, 'paid_amount'))) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════ Orders by Payment Status (Tabs) ═══════════ -->
<div class="max-w-4xl mx-auto mt-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <!-- Tabs -->
        <div class="flex gap-1 mb-4 bg-gray-100 rounded-lg p-1 w-fit">
            <button onclick="showTab('all')" id="tab-all" class="tab-btn px-4 py-2 rounded-md text-sm font-medium bg-white shadow text-gray-900">
                Бүгд (<?= count($batchOrders) ?>)
            </button>
            <button onclick="showTab('paid')" id="tab-paid" class="tab-btn px-4 py-2 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700">
                ✅ Төлсөн (<?= count($paidOrders) ?>)
            </button>
            <button onclick="showTab('unpaid')" id="tab-unpaid" class="tab-btn px-4 py-2 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700">
                ⏳ Төлөөгүй (<?= count($unpaidOrders) ?>)
            </button>
        </div>

        <?php if (empty($batchOrders)): ?>
            <p class="text-sm text-gray-400 text-center py-8">Энэ багцад захиалга байхгүй.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-3 font-medium text-gray-500">Захиалга №</th>
                            <th class="text-left py-3 px-3 font-medium text-gray-500">Хэрэглэгч</th>
                            <th class="text-left py-3 px-3 font-medium text-gray-500">Төлөв</th>
                            <th class="text-left py-3 px-3 font-medium text-gray-500">Төлбөр</th>
                            <th class="text-left py-3 px-3 font-medium text-gray-500">Байршил</th>
                            <th class="text-right py-3 px-3 font-medium text-gray-500">Нийт</th>
                            <th class="text-right py-3 px-3 font-medium text-gray-500">Ачаа</th>
                            <th class="text-left py-3 px-3 font-medium text-gray-500">Огноо</th>
                            <th class="py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($batchOrders as $order): ?>
                        <tr class="hover:bg-gray-50 order-row" data-payment="<?= $order['payment_status'] === 'paid' ? 'paid' : 'unpaid' ?>">
                            <td class="py-3 px-3 font-medium text-gray-900"><?= e($order['order_number']) ?></td>
                            <td class="py-3 px-3">
                                <div class="text-gray-900"><?= e($order['customer_name']) ?></div>
                                <div class="text-xs text-gray-400"><?= e($order['customer_phone']) ?></div>
                            </td>
                            <td class="py-3 px-3"><?= orderStatusLabel($order['status']) ?></td>
                            <td class="py-3 px-3">
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Төлсөн</span>
                                <?php elseif ($order['payment_status'] === 'refunded'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Буцаалт</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Хүлээгдэж буй</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-gray-600">
                                <?php if ($order['district_name']): ?>
                                    <?= e($order['district_name']) ?><?= $order['khoroo_name'] ? ', ' . e($order['khoroo_name']) : ($order['khoroo_number'] ? ', ' . $order['khoroo_number'] . '-р хороо' : '') ?>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-right font-medium text-gray-900"><?= formatPrice($order['total']) ?></td>
                            <td class="py-3 px-3 text-right text-orange-600"><?= formatPrice($order['cargo_fee']) ?></td>
                            <td class="py-3 px-3 text-gray-500"><?= formatDate($order['created_at']) ?></td>
                            <td class="py-3 px-3">
                                <a href="index.php?page=order-detail&id=<?= $order['id'] ?>" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Дэлгэрэнгүй</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200">
                            <td colspan="5" class="py-3 px-3 text-right font-medium text-gray-700">Нийт:</td>
                            <td class="py-3 px-3 text-right font-bold text-gray-900"><?= formatPrice(array_sum(array_column($batchOrders, 'total'))) ?></td>
                            <td class="py-3 px-3 text-right font-bold text-orange-600"><?= formatPrice(array_sum(array_column($batchOrders, 'cargo_fee'))) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-white', 'shadow', 'text-gray-900');
        btn.classList.add('text-gray-500');
    });
    document.getElementById('tab-' + tab).classList.add('bg-white', 'shadow', 'text-gray-900');
    document.getElementById('tab-' + tab).classList.remove('text-gray-500');

    document.querySelectorAll('.order-row').forEach(row => {
        if (tab === 'all') {
            row.style.display = '';
        } else {
            row.style.display = row.dataset.payment === tab ? '' : 'none';
        }
    });
}
</script>
<?php endif; ?>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
