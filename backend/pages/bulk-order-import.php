<?php
$pageTitle = 'Банкны хуулгаас захиалга үүсгэх';
$db = getDB();

// ── Helper: Extract 8-digit Mongolian phone number from messy description ──
function extractPhone(string $desc): string {
    // Remove common prefixes like "EB -", "EB-"
    $clean = preg_replace('/^EB\s*-?\s*/i', '', $desc);
    // Find 8-digit number (Mongolian phone)
    if (preg_match('/\b([89]\d{7})\b/', $clean, $m)) return $m[1];
    if (preg_match('/\b(\d{8})\b/', $clean, $m)) return $m[1];
    return '';
}

// ── Helper: Extract name from description ──
function extractName(string $desc): string {
    // Remove EB prefix, phone, product keywords, ХААНААС block
    $clean = preg_replace('/^EB\s*-?\s*/i', '', $desc);
    $clean = preg_replace('/ХААНААС:.*$/u', '', $clean);
    $clean = preg_replace('/\b\d{8}\b/', '', $clean);
    // Remove product-related keywords
    $clean = preg_replace('/\b(pdrn|collagen|collegen|collegan|collegin|collacen|collogen|colleg[ea]n|pdm|pdnr|set|com|kom|ser|сэт|сет|коллаген|нүүрний|ком|age\s*recovery|bor|ruby|peptid|nuurnii|гоо\s*сайхны|насыг\s*ухраагч|shirheg|sh|ш)\b/iu', '', $clean);
    // Remove quantity indicators
    $clean = preg_replace('/\b\d+\s*(sh|ш|shirheg)\b/iu', '', $clean);
    // Remove "niit7sh" style
    $clean = preg_replace('/niit\d+sh/i', '', $clean);
    // Clean up separators and extra spaces
    $clean = preg_replace('/[+,.\-_]+/', ' ', $clean);
    $clean = preg_replace('/\s+/', ' ', trim($clean));
    return $clean ?: '';
}

// ── AJAX: Parse CSV ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'parse') {
    header('Content-Type: application/json');
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'CSRF token буруу']);
        exit;
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    if (!$productId) {
        echo json_encode(['error' => 'Бараа сонгоно уу']);
        exit;
    }

    $product = $db->prepare("SELECT id, name, price FROM products WHERE id = ?");
    $product->execute([$productId]);
    $product = $product->fetch();
    if (!$product) {
        echo json_encode(['error' => 'Бараа олдсонгүй']);
        exit;
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'CSV файл оруулна уу']);
        exit;
    }

    $price = (float)$product['price'];
    if ($price <= 0) {
        echo json_encode(['error' => 'Барааны үнэ 0 байна']);
        exit;
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['error' => 'Файл уншиж чадсангүй']);
        exit;
    }

    $rows = [];
    $lineNum = 0;
    while (($line = fgetcsv($handle)) !== false) {
        $lineNum++;
        if (count($line) < 7) continue;

        $date = trim($line[0]);
        $amount = (float)str_replace(',', '', $line[4]);
        $desc = trim($line[6], ' "');
        $txnRef = trim($line[7] ?? '');

        if ($amount <= 0) continue;

        $qty = max(1, (int)round($amount / $price));
        $phone = extractPhone($desc);
        $name = extractName($desc);

        // Check if customer exists
        $customerStatus = 'new';
        $customerId = null;
        if ($phone) {
            $custStmt = $db->prepare("SELECT id, name FROM customers WHERE phone = ?");
            $custStmt->execute([$phone]);
            $existing = $custStmt->fetch();
            if ($existing) {
                $customerStatus = 'existing';
                $customerId = (int)$existing['id'];
                if (!$name && $existing['name']) $name = $existing['name'];
            }
        }
        if (!$name && $phone) $name = $phone;

        // Check duplicate by txn ref
        $isDuplicate = false;
        if ($txnRef) {
            $dupStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE notes LIKE ?");
            $dupStmt->execute(['%TXN:' . $txnRef . '%']);
            $isDuplicate = (int)$dupStmt->fetchColumn() > 0;
        }

        $rows[] = [
            'line' => $lineNum,
            'date' => $date,
            'amount' => $amount,
            'qty' => $qty,
            'phone' => $phone,
            'name' => $name,
            'desc' => $desc,
            'txn_ref' => $txnRef,
            'customer_id' => $customerId,
            'customer_status' => $customerStatus,
            'is_duplicate' => $isDuplicate,
        ];
    }
    fclose($handle);

    echo json_encode([
        'success' => true,
        'product' => ['id' => (int)$product['id'], 'name' => $product['name'], 'price' => $price],
        'rows' => $rows,
        'total_rows' => count($rows),
    ]);
    exit;
}

// ── AJAX: Create orders ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'create-orders') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'CSRF token буруу']);
        exit;
    }

    $productId = (int)($data['product_id'] ?? 0);
    $product = $db->prepare("SELECT id, name, price, type, weight_kg, cargo_batch_id, hide_cargo_fee FROM products WHERE id = ?");
    $product->execute([$productId]);
    $product = $product->fetch();
    if (!$product) {
        echo json_encode(['error' => 'Бараа олдсонгүй']);
        exit;
    }

    $rows = $data['rows'] ?? [];
    if (empty($rows)) {
        echo json_encode(['error' => 'Мөр сонгоно уу']);
        exit;
    }

    // Load cargo rate if preorder
    $cargoRate = 0;
    $batchId = null;
    if ($product['type'] === 'preorder' && $product['cargo_batch_id']) {
        $batchId = (int)$product['cargo_batch_id'];
        $bStmt = $db->prepare("SELECT cargo_rate_per_kg FROM cargo_batches WHERE id = ?");
        $bStmt->execute([$batchId]);
        $batchRow = $bStmt->fetch();
        $cargoRate = $batchRow ? (float)$batchRow['cargo_rate_per_kg'] : 0;
    }

    global $currentAdmin;
    $created = 0;
    $errors = [];

    foreach ($rows as $idx => $row) {
        $phone = preg_replace('/[^0-9]/', '', $row['phone'] ?? '');
        $name = trim($row['name'] ?? '');
        $qty = max(1, (int)($row['qty'] ?? 1));
        $txnRef = trim($row['txn_ref'] ?? '');
        $date = trim($row['date'] ?? '');

        if (strlen($phone) !== 8) {
            $errors[] = "Мөр #{$row['line']}: Утас буруу ({$phone})";
            continue;
        }

        try {
            $db->beginTransaction();

            // Find or create customer
            $custStmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
            $custStmt->execute([$phone]);
            $existing = $custStmt->fetch();

            if ($existing) {
                $customerId = (int)$existing['id'];
            } else {
                $db->prepare("INSERT INTO customers (phone, name, password) VALUES (?, ?, '')")
                   ->execute([$phone, htmlspecialchars($name, ENT_QUOTES, 'UTF-8')]);
                $customerId = (int)$db->lastInsertId();
            }

            $itemPrice = (float)$product['price'];
            $subtotal = $itemPrice * $qty;

            // Cargo fee
            $cargoFee = 0;
            $visibleCargoFee = 0;
            if ($product['type'] === 'preorder' && $product['weight_kg'] && $batchId) {
                $cargoFee = round((float)$product['weight_kg'] * $cargoRate * $qty, 2);
                if (empty($product['hide_cargo_fee'])) {
                    $visibleCargoFee = $cargoFee;
                }
            }

            $total = $subtotal + $visibleCargoFee;
            $orderNumber = generateOrderNumber($db);

            $notes = "Банкны хуулгаас импорт";
            if ($txnRef) $notes .= " | TXN:{$txnRef}";

            $confirmedAt = $date ?: date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                INSERT INTO orders (order_number, order_type, fulfillment, status,
                    customer_id, customer_name, customer_phone,
                    subtotal, delivery_fee, cargo_fee, total,
                    cargo_batch_id, payment_method, payment_status, payment_transfer, notes, confirmed_at, created_at)
                VALUES (?, 'online', 'pickup', 'confirmed',
                    ?, ?, ?,
                    ?, 0, ?, ?,
                    ?, 'transfer', 'paid', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderNumber,
                $customerId,
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                $phone,
                $subtotal, $cargoFee, $total,
                $batchId, $subtotal, $notes,
                $confirmedAt, $confirmedAt,
            ]);
            $orderId = (int)$db->lastInsertId();

            // Insert order item
            $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, weight_kg, cargo_fee, cargo_batch_id, hide_cargo_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$orderId, $product['id'], $product['name'], $itemPrice, $qty, $product['weight_kg'], $cargoFee, $batchId, !empty($product['hide_cargo_fee']) ? 1 : 0]);

            // Deduct stock via the ledger
            $r = adjustStock(
                $db,
                (int)$product['id'],
                null,
                -(int)$qty,
                'import',
                (int)$orderId,
                'admin',
                $currentAdmin['id'] ?? null,
                'Bulk import: ' . $orderNumber
            );
            if ($r['status'] === 'insufficient') {
                throw new Exception("'{$product['name']}' нөөц хүрэлцэхгүй. Боломжит: {$r['balance_after']}");
            }

            auditLog('create', 'order', $orderId, 'admin', $currentAdmin['id'] ?? null, [
                'order_number' => $orderNumber,
                'source' => 'bulk_csv_import',
                'customer_phone' => $phone,
                'total' => $total,
                'txn_ref' => $txnRef,
            ]);

            $db->commit();
            $created++;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Мөр #{$row['line']}: {$e->getMessage()}";
        }
    }

    echo json_encode([
        'success' => true,
        'created' => $created,
        'errors' => $errors,
    ]);
    exit;
}

// ── Page Data ──
$products = $db->query("SELECT id, name, price, type FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <h1 class="text-xl font-bold text-gray-800 mb-6">Банкны хуулгаас захиалга үүсгэх</h1>

    <!-- Step 1: Upload -->
    <div id="step-upload" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">1. Бараа сонгож CSV файл оруулах</h2>
        <form id="parseForm" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            <input type="hidden" name="action" value="parse">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Бараа сонгох</label>
                    <select name="product_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">-- Сонгох --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= e($p['name']) ?> — <?= formatPrice($p['price']) ?> (<?= $p['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">CSV файл (банкны хуулга)</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-3 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-blue-700">
                </div>
            </div>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                CSV задлах
            </button>
        </form>
    </div>

    <!-- Step 2: Preview -->
    <div id="step-preview" class="hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-700">2. Мэдээлэл шалгах</h2>
                <div class="flex items-center gap-3 text-sm">
                    <span id="productInfo" class="text-gray-500"></span>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="selectAll" checked class="rounded border-gray-300 text-blue-600">
                        <span class="text-gray-600">Бүгд</span>
                    </label>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-2 text-left w-8">✓</th>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Огноо</th>
                            <th class="px-3 py-2 text-right">Дүн</th>
                            <th class="px-3 py-2 text-center">Тоо</th>
                            <th class="px-3 py-2 text-left">Утас</th>
                            <th class="px-3 py-2 text-left">Нэр</th>
                            <th class="px-3 py-2 text-left">Тайлбар</th>
                            <th class="px-3 py-2 text-center">Хэрэглэгч</th>
                        </tr>
                    </thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-4 mb-8">
            <button id="btnCreateOrders" class="px-6 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">
                Захиалга үүсгэх
            </button>
            <span id="selectedCount" class="text-sm text-gray-500"></span>
            <button id="btnReset" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Буцах</button>
        </div>
    </div>

    <!-- Step 3: Result -->
    <div id="step-result" class="hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">3. Үр дүн</h2>
            <div id="resultContent"></div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(generateCSRFToken()) ?>';
let parsedData = null;

// Step 1: Parse CSV
document.getElementById('parseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Уншиж байна...';

    const formData = new FormData(this);
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.error) { alert(data.error); return; }

        parsedData = data;
        renderPreview(data);
        document.getElementById('step-upload').classList.add('hidden');
        document.getElementById('step-preview').classList.remove('hidden');
    } catch (err) {
        alert('Алдаа: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'CSV задлах';
    }
});

function renderPreview(data) {
    document.getElementById('productInfo').textContent = `${data.product.name} — ${formatPrice(data.product.price)} × ${data.total_rows} мөр`;
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = '';

    data.rows.forEach((row, i) => {
        const tr = document.createElement('tr');
        tr.className = row.is_duplicate ? 'bg-yellow-50' : (row.phone ? '' : 'bg-red-50');
        tr.innerHTML = `
            <td class="px-3 py-2"><input type="checkbox" class="row-check rounded border-gray-300 text-blue-600" data-idx="${i}" ${row.is_duplicate ? '' : (row.phone ? 'checked' : '')}></td>
            <td class="px-3 py-2 text-gray-400">${row.line}</td>
            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">${row.date ? row.date.slice(0,10) : '-'}</td>
            <td class="px-3 py-2 text-right font-medium">${formatPrice(row.amount)}</td>
            <td class="px-3 py-2 text-center">
                <input type="number" class="qty-input w-14 px-1 py-0.5 border rounded text-center text-sm" data-idx="${i}" value="${row.qty}" min="1">
            </td>
            <td class="px-3 py-2">
                <input type="text" class="phone-input w-24 px-1 py-0.5 border rounded text-sm ${row.phone ? '' : 'border-red-400'}" data-idx="${i}" value="${row.phone}">
            </td>
            <td class="px-3 py-2">
                <input type="text" class="name-input w-32 px-1 py-0.5 border rounded text-sm" data-idx="${i}" value="${escHtml(row.name)}">
            </td>
            <td class="px-3 py-2 text-xs text-gray-400 max-w-[200px] truncate" title="${escHtml(row.desc)}">${escHtml(row.desc)}</td>
            <td class="px-3 py-2 text-center">
                ${row.is_duplicate ? '<span class="text-xs text-yellow-600 font-medium">Давхардсан</span>' :
                  (row.customer_status === 'existing' ? '<span class="text-xs text-green-600">Бүртгэлтэй</span>' :
                  (row.phone ? '<span class="text-xs text-blue-600">Шинэ</span>' : '<span class="text-xs text-red-500">Утасгүй</span>'))}
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateSelectedCount();
}

// Select all
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateSelectedCount();
});
document.getElementById('previewBody').addEventListener('change', function(e) {
    if (e.target.classList.contains('row-check')) updateSelectedCount();
});

function updateSelectedCount() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    const total = document.querySelectorAll('.row-check').length;
    document.getElementById('selectedCount').textContent = `${checked} / ${total} сонгогдсон`;
}

// Step 2: Create orders
document.getElementById('btnCreateOrders').addEventListener('click', async function() {
    const checkedBoxes = document.querySelectorAll('.row-check:checked');
    if (!checkedBoxes.length) { alert('Мөр сонгоно уу'); return; }
    if (!confirm(`${checkedBoxes.length} захиалга үүсгэх үү?`)) return;

    this.disabled = true;
    this.textContent = 'Үүсгэж байна...';

    const rows = [];
    checkedBoxes.forEach(cb => {
        const idx = parseInt(cb.dataset.idx);
        const row = { ...parsedData.rows[idx] };
        // Read edited values from inputs
        const phoneInput = document.querySelector(`.phone-input[data-idx="${idx}"]`);
        const nameInput = document.querySelector(`.name-input[data-idx="${idx}"]`);
        const qtyInput = document.querySelector(`.qty-input[data-idx="${idx}"]`);
        if (phoneInput) row.phone = phoneInput.value.trim();
        if (nameInput) row.name = nameInput.value.trim();
        if (qtyInput) row.qty = parseInt(qtyInput.value) || 1;
        rows.push(row);
    });

    try {
        const res = await fetch(window.location.href + '&action=create-orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                product_id: parsedData.product.id,
                rows: rows,
            }),
        });
        const data = await res.json();
        if (data.error) { alert(data.error); return; }

        document.getElementById('step-preview').classList.add('hidden');
        document.getElementById('step-result').classList.remove('hidden');

        let html = `<div class="text-green-700 font-semibold text-lg mb-3">✓ ${data.created} захиалга амжилттай үүсгэлээ</div>`;
        if (data.errors && data.errors.length > 0) {
            html += `<div class="mt-3 text-red-600 text-sm"><p class="font-medium mb-1">Алдаатай мөрүүд:</p><ul class="list-disc pl-5">`;
            data.errors.forEach(e => html += `<li>${escHtml(e)}</li>`);
            html += '</ul></div>';
        }
        html += `<a href="index.php?page=orders" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Захиалгууд руу очих</a>`;
        document.getElementById('resultContent').innerHTML = html;
    } catch (err) {
        alert('Алдаа: ' + err.message);
    } finally {
        this.disabled = false;
        this.textContent = 'Захиалга үүсгэх';
    }
});

// Reset
document.getElementById('btnReset').addEventListener('click', function() {
    document.getElementById('step-preview').classList.add('hidden');
    document.getElementById('step-upload').classList.remove('hidden');
    parsedData = null;
});

function formatPrice(n) { return Number(n).toLocaleString('mn-MN') + '₮'; }
function escHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
