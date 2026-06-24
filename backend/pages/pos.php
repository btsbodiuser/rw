<?php
$pageTitle = 'POS Терминал';
$db = getDB();

// ── AJAX: Load order items for editing ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'load-order') {
    header('Content-Type: application/json');
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) { echo json_encode(['error' => 'Invalid order ID']); exit; }

    $order = $db->prepare("SELECT * FROM orders WHERE id = ? AND order_type = 'pos'");
    $order->execute([$orderId]);
    $order = $order->fetch();
    if (!$order) { echo json_encode(['error' => 'Захиалга олдсонгүй']); exit; }
    if ($order['status'] === 'cancelled') { echo json_encode(['error' => 'Цуцлагдсан захиалга засах боломжгүй']); exit; }

    $items = $db->prepare("SELECT oi.*, p.stock as current_stock, p.is_active, p.has_variants, s.name as shop_name, c.name as category_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN shops s ON p.shop_id = s.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?");
    $items->execute([$orderId]);

    $cartItems = [];
    foreach ($items->fetchAll() as $oi) {
        $variantStock = 0;
        if ($oi['variant_id']) {
            $vs = $db->prepare("SELECT stock FROM product_variants WHERE id = ?");
            $vs->execute([$oi['variant_id']]);
            $variantStock = (int)($vs->fetchColumn() ?: 0);
        }
        $availableStock = $oi['variant_id']
            ? $variantStock + (int)$oi['quantity']
            : (int)$oi['current_stock'] + (int)$oi['quantity'];

        $cartItems[] = [
            'id' => (int)$oi['product_id'],
            'name' => $oi['product_name'],
            'price' => (float)$oi['product_price'],
            'qty' => (int)$oi['quantity'],
            'stock' => $availableStock,
            'customTotal' => $oi['line_total'] ? (float)$oi['line_total'] : null,
            'category' => $oi['category_name'] ?? '',
            'shop' => $oi['shop_name'] ?? '',
            'variant_id' => $oi['variant_id'] ? (int)$oi['variant_id'] : null,
            'variant_label' => $oi['variant_label'] ?? null,
        ];
    }

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'payment_method' => $order['payment_method'],
            'vat_included' => (bool)$order['vat_included'],
            'total' => (float)$order['total'],
        ],
        'items' => $cartItems,
    ]);
    exit;
}

// Handle POS sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    $action = $data['action'] ?? 'new';
    $items = $data['items'] ?? [];
    $paymentMethod = $data['payment_method'] ?? 'cash';
    $vatIncluded = !empty($data['vat_included']) ? 1 : 0;
    $paymentSplit = $data['payment_split'] ?? null;

    if (empty($items)) {
        echo json_encode(['error' => 'Cart is empty']);
        exit;
    }

    // ── Edit existing order ──
    if ($action === 'edit') {
        $editOrderId = (int)($data['edit_order_id'] ?? 0);
        $editReason = trim($data['edit_reason'] ?? '');
        if ($editOrderId <= 0) { echo json_encode(['error' => 'Invalid order']); exit; }
        if ($editReason === '') { echo json_encode(['error' => 'Засварын шалтгаан оруулна уу']); exit; }

        try {
            $db->beginTransaction();

            // Get existing order
            $existingOrder = $db->prepare("SELECT * FROM orders WHERE id = ? AND order_type = 'pos' AND status != 'cancelled'");
            $existingOrder->execute([$editOrderId]);
            $existingOrder = $existingOrder->fetch();
            if (!$existingOrder) { throw new Exception('Захиалга олдсонгүй'); }

            global $currentAdmin;
            $posAdminId = $currentAdmin['id'] ?? null;

            // Restore stock from old items via the ledger
            $oldItems = $db->prepare("SELECT product_id, quantity, variant_id FROM order_items WHERE order_id = ?");
            $oldItems->execute([$editOrderId]);
            foreach ($oldItems->fetchAll() as $oi) {
                adjustStock(
                    $db,
                    (int)$oi['product_id'],
                    $oi['variant_id'] ? (int)$oi['variant_id'] : null,
                    +(int)$oi['quantity'],
                    'pos_edit_restore',
                    (int)$editOrderId,
                    'admin',
                    $posAdminId,
                    'POS edit: ' . $existingOrder['order_number']
                );
            }

            // Delete old items
            $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$editOrderId]);

            // Process new items
            $subtotal = 0;
            $orderItems = [];

            foreach ($items as $item) {
                $productId = (int)$item['id'];
                $qty = (int)$item['qty'];
                $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : null;
                if ($qty <= 0) continue;

                $product = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1 AND type = 'ready' FOR UPDATE");
                $product->execute([$productId]);
                $p = $product->fetch();

                if (!$p) { throw new Exception("Product not found: ID $productId"); }

                $unitPrice = (float)$p['price'];
                $variantLabel = null;

                if ($variantId) {
                    $vs = $db->prepare("SELECT pv.*, pc.name_mn as color_name, ps.name as size_name FROM product_variants pv LEFT JOIN product_colors pc ON pv.color_id = pc.id LEFT JOIN product_sizes ps ON pv.size_id = ps.id WHERE pv.id = ? AND pv.product_id = ? FOR UPDATE");
                    $vs->execute([$variantId, $productId]);
                    $variant = $vs->fetch();
                    if (!$variant) { throw new Exception("Variant not found: ID $variantId"); }
                    if ($variant['price_override']) $unitPrice = (float)$variant['price_override'];
                    $variantLabel = trim(($variant['color_name'] ?? '') . ' ' . ($variant['size_name'] ?? ''));
                }
                $r = adjustStock(
                    $db,
                    (int)$p['id'],
                    $variantId ?: null,
                    -$qty,
                    'pos_edit_deduct',
                    (int)$editOrderId,
                    'admin',
                    $posAdminId,
                    'POS edit: ' . $existingOrder['order_number']
                );
                if ($r['status'] === 'insufficient') {
                    throw new Exception("Insufficient stock for {$p['name']}. Available: {$r['balance_after']}");
                }

                $customPrice = isset($item['custom_price']) ? (float)$item['custom_price'] : null;
                if ($customPrice !== null && $customPrice >= 0) $unitPrice = $customPrice;
                $lineTotal = $unitPrice * $qty;
                $customTotal = isset($item['custom_total']) ? (float)$item['custom_total'] : null;
                $billingTotal = ($customTotal !== null && $customTotal > 0) ? $customTotal : $lineTotal;
                $subtotal += $billingTotal;

                $orderItems[] = [
                    'product_id' => $p['id'],
                    'product_name' => $p['name'],
                    'product_price' => $unitPrice,
                    'quantity' => $qty,
                    'line_total' => ($customTotal !== null && $customTotal > 0) ? $customTotal : null,
                    'weight_kg' => $p['weight_kg'],
                    'variant_id' => $variantId,
                    'variant_label' => $variantLabel,
                ];
            }

            // (variant→product stock sync is handled inside adjustStock)

            // Calculate VAT
            $vatAmount = 0;
            $total = $subtotal;
            if ($vatIncluded) {
                $vatAmount = round($subtotal * 0.10, 2);
                $total = $subtotal + $vatAmount;
            }

            // Payment amounts
            $paymentCash = 0; $paymentCard = 0; $paymentTransfer = 0; $paymentTransferNomin = 0;
            if ($paymentMethod === 'split' && is_array($paymentSplit)) {
                $paymentCash = round((float)($paymentSplit['cash'] ?? 0), 2);
                $paymentCard = round((float)($paymentSplit['card'] ?? 0), 2);
                $paymentTransfer = round((float)($paymentSplit['transfer'] ?? 0), 2);
                $paymentTransferNomin = round((float)($paymentSplit['transfer_nomin'] ?? 0), 2);
                $splitSum = $paymentCash + $paymentCard + $paymentTransfer + $paymentTransferNomin;
                if (abs($splitSum - $total) > 1) { throw new Exception("Split amounts don't match total"); }
            } else {
                match ($paymentMethod) {
                    'cash' => $paymentCash = $total,
                    'card' => $paymentCard = $total,
                    'transfer' => $paymentTransfer = $total,
                    'transfer_nomin' => $paymentTransferNomin = $total,
                    default => $paymentCash = $total,
                };
            }

            // Update order
            $db->prepare("UPDATE orders SET subtotal = ?, total = ?, vat_amount = ?, vat_included = ?, payment_method = ?, payment_cash = ?, payment_card = ?, payment_transfer = ?, payment_transfer_nomin = ? WHERE id = ?")
                ->execute([$subtotal, $total, $vatAmount, $vatIncluded, $paymentMethod, $paymentCash, $paymentCard, $paymentTransfer, $paymentTransferNomin, $editOrderId]);

            // Insert new items
            $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, line_total, weight_kg, variant_id, variant_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($orderItems as $oi) {
                $itemStmt->execute([$editOrderId, $oi['product_id'], $oi['product_name'], $oi['product_price'], $oi['quantity'], $oi['line_total'], $oi['weight_kg'], $oi['variant_id'], $oi['variant_label']]);
            }

            // Audit log
            global $currentAdmin;
            auditLog('edit', 'order', $editOrderId, 'admin', $currentAdmin['id'] ?? null, [
                'order_number' => $existingOrder['order_number'],
                'reason' => $editReason,
                'old_total' => (float)$existingOrder['total'],
                'new_total' => $total,
                'old_payment' => $existingOrder['payment_method'],
                'new_payment' => $paymentMethod,
            ]);

            $db->commit();

            echo json_encode([
                'success' => true,
                'edited' => true,
                'order_number' => $existingOrder['order_number'],
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'vat_included' => $vatIncluded,
                'total' => $total,
                'items_count' => count($orderItems),
                'payment_method' => $paymentMethod,
                'payment_cash' => $paymentCash,
                'payment_card' => $paymentCard,
                'payment_transfer' => $paymentTransfer,
                'payment_transfer_nomin' => $paymentTransferNomin,
            ]);
        } catch (Exception $ex) {
            $db->rollBack();
            echo json_encode(['error' => $ex->getMessage()]);
        }
        exit;
    }

    // ── New sale ──
    try {
        $db->beginTransaction();

        $subtotal = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $productId = (int)$item['id'];
            $qty = (int)$item['qty'];
            $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : null;
            if ($qty <= 0) continue;

            $product = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1 AND type = 'ready' FOR UPDATE");
            $product->execute([$productId]);
            $p = $product->fetch();

            if (!$p) {
                throw new Exception("Product not found: ID $productId");
            }

            $unitPrice = (float)$p['price'];
            $variantLabel = null;

            if ($variantId) {
                $vs = $db->prepare("SELECT pv.*, pc.name_mn as color_name, ps.name as size_name FROM product_variants pv LEFT JOIN product_colors pc ON pv.color_id = pc.id LEFT JOIN product_sizes ps ON pv.size_id = ps.id WHERE pv.id = ? AND pv.product_id = ? FOR UPDATE");
                $vs->execute([$variantId, $productId]);
                $variant = $vs->fetch();
                if (!$variant) { throw new Exception("Variant not found: ID $variantId"); }
                if ($variant['price_override']) $unitPrice = (float)$variant['price_override'];
                $variantLabel = trim(($variant['color_name'] ?? '') . ' ' . ($variant['size_name'] ?? ''));
            }

            $customPrice = isset($item['custom_price']) ? (float)$item['custom_price'] : null;
            if ($customPrice !== null && $customPrice >= 0) $unitPrice = $customPrice;
            $lineTotal = $unitPrice * $qty;
            $customTotal = isset($item['custom_total']) ? (float)$item['custom_total'] : null;
            $billingTotal = ($customTotal !== null && $customTotal > 0) ? $customTotal : $lineTotal;
            $subtotal += $billingTotal;

            $orderItems[] = [
                'product_id' => $p['id'],
                'product_name' => $p['name'],
                'product_price' => $unitPrice,
                'quantity' => $qty,
                'line_total' => ($customTotal !== null && $customTotal > 0) ? $customTotal : null,
                'weight_kg' => $p['weight_kg'],
                'variant_id' => $variantId,
                'variant_label' => $variantLabel,
            ];
        }
        // (stock deduction deferred until order_id exists)

        // Calculate VAT if enabled
        $vatAmount = 0;
        $total = $subtotal;
        if ($vatIncluded) {
            $vatAmount = round($subtotal * 0.10, 2);
            $total = $subtotal + $vatAmount;
        }

        // Create order
        $orderNumber = generateOrderNumber($db);

        // Calculate split payment amounts
        $paymentCash = 0;
        $paymentCard = 0;
        $paymentTransfer = 0;
        $paymentTransferNomin = 0;

        if ($paymentMethod === 'split' && is_array($paymentSplit)) {
            $paymentCash = round((float)($paymentSplit['cash'] ?? 0), 2);
            $paymentCard = round((float)($paymentSplit['card'] ?? 0), 2);
            $paymentTransfer = round((float)($paymentSplit['transfer'] ?? 0), 2);
            $paymentTransferNomin = round((float)($paymentSplit['transfer_nomin'] ?? 0), 2);
            $splitSum = $paymentCash + $paymentCard + $paymentTransfer + $paymentTransferNomin;
            if (abs($splitSum - $total) > 1) {
                throw new Exception("Split payment amounts ({$splitSum}) don't match total ({$total})");
            }
        } else {
            // Single payment method — put full total in the matching column
            match ($paymentMethod) {
                'cash' => $paymentCash = $total,
                'card' => $paymentCard = $total,
                'transfer' => $paymentTransfer = $total,
                'transfer_nomin' => $paymentTransferNomin = $total,
                default => $paymentCash = $total,
            };
        }

        $stmt = $db->prepare("INSERT INTO orders (order_number, order_type, fulfillment, status, subtotal, total, vat_amount, vat_included, payment_method, payment_cash, payment_card, payment_transfer, payment_transfer_nomin, payment_status, confirmed_at, created_at)
            VALUES (?, 'pos', 'pickup', 'completed', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW(), NOW())");
        $stmt->execute([$orderNumber, $subtotal, $total, $vatAmount, $vatIncluded, $paymentMethod, $paymentCash, $paymentCard, $paymentTransfer, $paymentTransferNomin]);
        $orderId = $db->lastInsertId();

        // Insert items
        $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, line_total, weight_kg, variant_id, variant_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($orderItems as $oi) {
            $itemStmt->execute([$orderId, $oi['product_id'], $oi['product_name'], $oi['product_price'], $oi['quantity'], $oi['line_total'], $oi['weight_kg'], $oi['variant_id'], $oi['variant_label']]);
        }

        // Deduct stock via the ledger now that we have order_id
        global $currentAdmin;
        $posAdminId = $currentAdmin['id'] ?? null;
        foreach ($orderItems as $oi) {
            $r = adjustStock(
                $db,
                (int)$oi['product_id'],
                $oi['variant_id'] ? (int)$oi['variant_id'] : null,
                -(int)$oi['quantity'],
                'pos_sale',
                (int)$orderId,
                'admin',
                $posAdminId,
                'POS: ' . $orderNumber
            );
            if ($r['status'] === 'insufficient') {
                throw new Exception("Insufficient stock for '{$oi['product_name']}'. Available: {$r['balance_after']}");
            }
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'order_number' => $orderNumber,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'vat_included' => $vatIncluded,
            'total' => $total,
            'items_count' => count($orderItems),
            'payment_method' => $paymentMethod,
            'payment_cash' => $paymentCash,
            'payment_card' => $paymentCard,
            'payment_transfer' => $paymentTransfer,
            'payment_transfer_nomin' => $paymentTransferNomin,
        ]);
    } catch (Exception $ex) {
        $db->rollBack();
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

// Get ready products for POS (include variant products even if base stock=0)
$products = $db->query("SELECT p.*, p.has_variants, s.name as shop_name, c.name as category_name 
    FROM products p
    LEFT JOIN shops s ON p.shop_id = s.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 AND p.type = 'ready' AND (p.stock > 0 OR p.has_variants = 1)
    ORDER BY p.name ASC")->fetchAll();

// Load variants for variant products
$variantProductIds = array_filter(array_column($products, 'id'), function($id) use ($products) {
    foreach ($products as $p) { if ($p['id'] == $id && $p['has_variants']) return true; }
    return false;
});
$variantsMap = [];
if (!empty($variantProductIds)) {
    $placeholders = implode(',', array_fill(0, count($variantProductIds), '?'));
    $vStmt = $db->prepare("SELECT pv.*, pc.name_mn as color_name, pc.hex_code, ps.name as size_name 
        FROM product_variants pv 
        LEFT JOIN product_colors pc ON pv.color_id = pc.id 
        LEFT JOIN product_sizes ps ON pv.size_id = ps.id 
        WHERE pv.product_id IN ($placeholders) AND pv.is_active = 1 AND pv.stock > 0
        ORDER BY pv.product_id, pc.sort_order, ps.sort_order");
    $vStmt->execute(array_values($variantProductIds));
    foreach ($vStmt->fetchAll() as $v) {
        $variantsMap[(int)$v['product_id']][] = [
            'id' => (int)$v['id'],
            'color_name' => $v['color_name'],
            'hex_code' => $v['hex_code'],
            'size_name' => $v['size_name'],
            'stock' => (int)$v['stock'],
            'price_override' => $v['price_override'] ? (float)$v['price_override'] : null,
            'label' => trim(($v['color_name'] ?? '') . ' ' . ($v['size_name'] ?? '')),
        ];
    }
}

// Build barcode lookup map for POS scanner
$barcodeMap = [];
foreach ($products as $p) {
    if (!empty($p['barcode'])) {
        $barcodeMap[$p['barcode']] = [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'price' => (float)$p['price'],
            'stock' => (int)$p['stock'],
            'category' => $p['category_name'],
            'shop' => $p['shop_name'],
            'has_variants' => (bool)$p['has_variants'],
            'variants' => $variantsMap[(int)$p['id']] ?? [],
        ];
    }
}

$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Products as JSON for autocomplete
$productsJson = json_encode(array_map(function($p) use ($variantsMap) {
    $variants = $variantsMap[(int)$p['id']] ?? [];
    $totalVariantStock = array_sum(array_column($variants, 'stock'));
    return [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'price' => (float)$p['price'],
        'stock' => $p['has_variants'] ? $totalVariantStock : (int)$p['stock'],
        'category' => $p['category_name'],
        'shop' => $p['shop_name'],
        'barcode' => $p['barcode'] ?? '',
        'has_variants' => (bool)$p['has_variants'],
        'variants' => $variants,
    ];
}, $products), JSON_HEX_APOS | JSON_HEX_TAG);

// Today's recent POS orders for history panel
$todayOrders = $db->query("SELECT o.*, 
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count 
    FROM orders o 
    WHERE o.order_type = 'pos' AND DATE(o.created_at) = CURDATE()
    ORDER BY o.created_at DESC")->fetchAll();
$todaySummary = $db->query("SELECT 
    COUNT(*) as cnt, 
    COALESCE(SUM(total),0) as revenue 
    FROM orders 
    WHERE order_type = 'pos' AND DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetch();
$paymentLabels = ['cash' => 'Бэлэн', 'card' => 'Карт', 'transfer' => 'Шилжүүлэг', 'transfer_nomin' => 'Номин шилжүүлэг', 'split' => 'Хуваасан'];

require_once __DIR__ . '/../includes/header.php';
?>

<div x-data="posApp()" x-init="initScanner()" x-cloak class="flex flex-col -mt-2" style="height: calc(100vh - 140px);">
    <!-- Top Bar: Barcode + Product Search + Category -->
    <div class="flex gap-2 mb-3">
        <div class="relative" style="width:200px">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h2v16H3V4zm4 0h1v16H7V4zm3 0h2v16h-2V4zm4 0h1v16h-1V4zm3 0h3v16h-3V4z"/>
            </svg>
            <input type="text" x-model="barcodeInput" x-ref="barcodeInput" placeholder="Баркод (F1)"
                   @keydown.enter.prevent="scanBarcode()"
                   inputmode="numeric"
                   class="w-full pl-10 pr-3 py-2 border-2 border-green-400 bg-green-50 rounded-lg text-sm font-mono focus:ring-2 focus:ring-green-500 outline-none">
        </div>
        <!-- Product search with autocomplete -->
        <div class="flex-1 relative" style="max-width:360px">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" x-ref="searchInput" placeholder="Бараа хайх... (F2)"
                   @focus="showDropdown = true" @input="showDropdown = true"
                   @keydown.arrow-down.prevent="dropdownNav(1)" @keydown.arrow-up.prevent="dropdownNav(-1)"
                   @keydown.enter.prevent="dropdownSelect()" @keydown.escape="showDropdown = false"
                   class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <!-- Autocomplete dropdown -->
            <div x-show="showDropdown && search.length > 0 && filteredProducts.length > 0"
                 @click.outside="showDropdown = false" x-cloak
                 class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 shadow-xl rounded-lg max-h-64 overflow-y-auto z-50">
                <template x-for="(p, idx) in filteredProducts" :key="p.id">
                    <button @click="addToCart(p); search = ''; showDropdown = false"
                            class="w-full text-left px-3 py-2 flex items-center justify-between hover:bg-blue-50 transition-colors border-b border-gray-50 last:border-0"
                            :class="dropdownIdx === idx ? 'bg-blue-50' : ''">
                        <div>
                            <span class="text-sm font-medium text-gray-900" x-text="p.name"></span>
                            <span class="text-xs text-gray-400 ml-1" x-text="p.shop"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-blue-600" x-text="formatP(p.price)"></span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full" 
                                  :class="p.stock <= 5 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500'"
                                  x-text="p.stock + ' ш'"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>
        <select x-model="categoryFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">Бүгд</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['name']) ?>"><?= e($c['icon'] . ' ' . $c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span id="scan-feedback" class="self-center text-xs px-3 py-1.5 rounded-lg font-medium transition-opacity" style="opacity:0"></span>
        <!-- Shortcuts help -->
        <div class="relative ml-auto" x-data="{ showHelp: false }">
            <button @click="showHelp = !showHelp" @click.outside="showHelp = false"
                    class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 text-sm font-bold transition-colors" title="Товчлол">?</button>
            <div x-show="showHelp" x-cloak x-transition
                 class="absolute right-0 top-11 bg-gray-900 text-white rounded-xl p-4 shadow-xl z-50 w-56">
                <p class="font-bold text-sm mb-2 text-gray-300">⌨️ Товчлол</p>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-gray-400">Баркод</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F1</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">Хайлт</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F2</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">💵 Бэлэн</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F3</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">💳 Карт</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F4</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">📲 Шилжүүлэг</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F5</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">🏪 Номин</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F6</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">✂️ Хуваах</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F7</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">Цэвэрлэх</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">F8</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">Батлах</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">Enter</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">Болих</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">Esc</kbd></div>
                    <div class="flex justify-between"><span class="text-gray-400">Хэвлэх</span><kbd class="bg-gray-700 px-1.5 rounded text-xs">P</kbd></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main: 3/4 Cart + 1/4 Payment & History -->
    <div class="flex-1 flex gap-3 min-h-0">
        <!-- LEFT: Cart (3/4) -->
        <div class="flex-[3] flex flex-col bg-white rounded-xl shadow-sm border border-gray-100 min-h-0">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 text-sm flex items-center gap-2">
                    🛒 Сагс
                    <span x-show="cart.length > 0" class="text-xs font-normal text-gray-400" x-text="totalItems + ' бараа'"></span>
                </h3>
                <div class="flex items-center gap-2">
                    <button @click="cancelEdit()" x-show="editingOrder" class="text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 px-2 py-1 rounded transition-colors">✕ Засвар болих</button>
                    <button @click="clearCart()" x-show="cart.length > 0" class="text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded transition-colors">Цэвэрлэх (F8)</button>
                </div>
            </div>

            <!-- Edit mode banner -->
            <div x-show="editingOrder" x-cloak class="px-4 py-2.5 bg-amber-50 border-b border-amber-200">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold text-amber-800">✏️ Засварлаж байна: <span x-text="editingOrder?.order_number"></span></span>
                    <span class="text-[10px] text-amber-600" x-text="'Өмнөх дүн: ' + formatP(editingOrder?.old_total || 0)"></span>
                </div>
                <input type="text" x-model="editReason" placeholder="Засварын шалтгаан бичнэ үү... *"
                       class="w-full px-3 py-1.5 text-xs border border-amber-300 rounded-lg bg-white focus:ring-2 focus:ring-amber-500 outline-none placeholder-amber-400">
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto">
                <template x-if="cart.length === 0">
                    <div class="flex flex-col items-center justify-center h-full text-gray-400">
                        <svg class="w-16 h-16 mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <p class="text-sm">Баркод уншуулах эсвэл бараа хайж нэмнэ үү</p>
                        <p class="text-xs text-gray-300 mt-1">F1 — Баркод  |  F2 — Хайлт</p>
                    </div>
                </template>
                <table x-show="cart.length > 0" class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100 sticky top-0">
                        <tr>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Бараа</th>
                            <th class="text-center px-2 py-2 text-xs font-medium text-gray-500 uppercase w-20">Үнэ</th>
                            <th class="text-center px-2 py-2 text-xs font-medium text-gray-500 uppercase w-28">Тоо</th>
                            <th class="text-right px-4 py-2 text-xs font-medium text-gray-500 uppercase w-28">Дүн</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="(item, index) in cart" :key="item.id + '-' + (item.variant_id||0)">
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-2">
                                    <p class="text-sm font-medium text-gray-900" x-text="item.name"></p>
                                    <p class="text-[11px] text-gray-400">
                                        <span x-text="item.shop"></span>
                                        <template x-if="item.variant_label">
                                            <span class="ml-1 text-purple-600 font-medium" x-text="'· ' + item.variant_label"></span>
                                        </template>
                                    </p>
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <input type="number" :value="item.price" min="0" inputmode="numeric"
                                           @change="setPrice(index, $event.target.value)" @focus="$event.target.select()"
                                           class="w-20 text-center text-sm text-gray-600 border border-transparent hover:border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="updateQty(index, -1)" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 text-sm font-bold">−</button>
                                        <input type="number" :value="item.qty" min="1" :max="item.stock" inputmode="numeric"
                                               @change="setQty(index, $event.target.value)" @focus="$event.target.select()"
                                               class="w-10 text-center text-sm font-semibold border border-transparent hover:border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                        <button @click="updateQty(index, 1)" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 text-sm font-bold">+</button>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <template x-if="editingIndex !== index">
                                        <button @click="startEditTotal(index)" class="text-sm font-medium hover:underline"
                                                :class="item.customTotal != null ? 'text-orange-600' : 'text-gray-900'">
                                            <span x-show="item.customTotal != null" class="text-[10px] line-through text-gray-300 block" x-text="formatP(item.price * item.qty)"></span>
                                            <span x-text="formatP(getLineTotal(item))"></span>
                                        </button>
                                    </template>
                                    <template x-if="editingIndex === index">
                                        <div class="flex items-center justify-end gap-1">
                                            <input type="number" x-ref="editTotalInput" x-model.number="editTotalValue" min="0" inputmode="numeric"
                                                   @keydown.enter.prevent="saveEditTotal(index)"
                                                   @keydown.escape.prevent="cancelEditTotal()"
                                                   @blur="saveEditTotal(index)"
                                                   class="w-24 px-2 py-1 border-2 border-orange-400 rounded text-sm text-right font-medium focus:ring-2 focus:ring-orange-500 outline-none">
                                            <button @mousedown.prevent="resetLineTotal(index)" class="text-xs text-gray-400 hover:text-blue-600">↩</button>
                                        </div>
                                    </template>
                                </td>
                                <td class="pr-3 py-2">
                                    <button @click="removeFromCart(index)" class="text-red-300 hover:text-red-600 transition-colors text-sm">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT: Payment + History (1/4) -->
        <div class="flex-1 flex flex-col gap-3 min-h-0" style="min-width:240px; max-width:300px">
            <!-- Payment Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
                <!-- Totals -->
                <div class="space-y-1 mb-2.5">
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span x-text="totalItems + ' бараа'"></span>
                        <span x-text="formatP(subtotalPrice)"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">НӨАТ 10%</span>
                            <button @click="vatEnabled = !vatEnabled" 
                                    :class="vatEnabled ? 'bg-green-600' : 'bg-gray-300'"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none">
                                <span :class="vatEnabled ? 'translate-x-5' : 'translate-x-0.5'"
                                      class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"></span>
                            </button>
                        </div>
                        <span x-show="vatEnabled" class="text-sm font-medium text-orange-600" x-text="formatP(vatAmount)"></span>
                    </div>
                    <div class="flex justify-between items-baseline pt-1.5 border-t border-gray-100">
                        <span class="font-bold text-gray-900">Нийт</span>
                        <span class="text-xl font-bold text-blue-600" x-text="formatP(totalPrice)"></span>
                    </div>
                </div>

                <!-- Payment Buttons -->
                <div class="grid grid-cols-3 gap-1.5">
                    <button @click="confirmPay('cash')"
                            :disabled="cart.length === 0 || processing"
                            class="py-2.5 rounded-lg font-bold text-xs transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed bg-green-600 text-white hover:bg-green-700 flex items-center justify-center gap-1">
                        💵 Бэлэн
                    </button>
                    <button @click="confirmPay('card')"
                            :disabled="cart.length === 0 || processing"
                            class="py-2.5 rounded-lg font-bold text-xs transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed bg-blue-600 text-white hover:bg-blue-700 flex items-center justify-center gap-1">
                        💳 Карт
                    </button>
                    <button @click="confirmPay('transfer')"
                            :disabled="cart.length === 0 || processing"
                            class="py-2.5 rounded-lg font-bold text-xs transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed bg-purple-600 text-white hover:bg-purple-700 flex items-center justify-center gap-1">
                        📲 Шилжүүлэг
                    </button>
                    <button @click="confirmPay('transfer_nomin')"
                            :disabled="cart.length === 0 || processing"
                            class="py-2.5 rounded-lg font-bold text-xs transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed bg-teal-600 text-white hover:bg-teal-700 flex items-center justify-center gap-1">
                        🏪 Номин
                    </button>
                    <button @click="openSplitPay()"
                            :disabled="cart.length === 0 || processing"
                            class="py-2.5 rounded-lg font-bold text-xs transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed bg-orange-500 text-white hover:bg-orange-600 flex items-center justify-center gap-1 col-span-2">
                        ✂️ Хуваах
                    </button>
                </div>
            </div>

            <!-- Today's History -->
            <div class="flex-1 bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col min-h-0 overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Өнөөдрийн борлуулалт</h3>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-green-600"><?= formatPrice($todaySummary['revenue']) ?></span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500"><?= $todaySummary['cnt'] ?></span>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto divide-y divide-gray-50">
                    <?php if (empty($todayOrders)): ?>
                        <div class="p-6 text-center text-gray-400 text-xs">Өнөөдөр борлуулалт байхгүй</div>
                    <?php endif; ?>
                    <?php foreach ($todayOrders as $to): 
                        $toCancelled = ($to['status'] === 'cancelled');
                    ?>
                    <?php if ($toCancelled): ?>
                    <div class="flex items-center justify-between px-4 py-2 opacity-40">
                        <div>
                            <span class="text-xs font-medium line-through text-gray-400"><?= e($to['order_number']) ?></span>
                            <span class="text-[10px] text-gray-400 ml-1"><?= date('H:i', strtotime($to['created_at'])) ?></span>
                            <span class="text-[9px] text-red-500 ml-1">цуцал</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-400"><?= $to['item_count'] ?>ш</span>
                            <span class="text-xs font-semibold line-through text-gray-400"><?= formatPrice($to['total']) ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <button @click="loadOrder(<?= $to['id'] ?>)" type="button"
                       class="w-full flex items-center justify-between px-4 py-2 hover:bg-blue-50 transition-colors text-left">
                        <div>
                            <span class="text-xs font-medium text-gray-700"><?= e($to['order_number']) ?></span>
                            <span class="text-[10px] text-gray-400 ml-1"><?= date('H:i', strtotime($to['created_at'])) ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-400"><?= $to['item_count'] ?>ш</span>
                            <span class="text-xs font-semibold text-gray-700"><?= formatPrice($to['total']) ?></span>
                            <span class="text-[10px]"><?php 
                                echo match($to['payment_method']) {
                                    'cash' => '💵', 'card' => '💳', 'transfer' => '📲', 'transfer_nomin' => '🏪', 'split' => '✂️', default => ''
                                };
                            ?></span>
                            <span class="text-[10px] text-blue-400">✏️</span>
                        </div>
                    </button>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if (count($todayOrders) > 0): ?>
                <div class="px-4 py-2 border-t border-gray-100 flex-shrink-0">
                    <a href="index.php?page=pos-history" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Бүх түүх →</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Variant Picker Modal -->
    <div x-show="showVariantPicker" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @keydown.escape.window="showVariantPicker = false">
        <div class="bg-white rounded-2xl p-5 max-w-md w-full" @click.outside="showVariantPicker = false">
            <h3 class="text-base font-bold text-gray-900 mb-1">Хувилбар сонгох</h3>
            <p class="text-sm text-gray-500 mb-3" x-text="variantPickerProduct?.name"></p>
            <div class="max-h-64 overflow-y-auto space-y-1.5">
                <template x-for="v in (variantPickerProduct?.variants || [])" :key="v.id">
                    <button type="button" @click="selectVariant(v)"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg border border-gray-200 hover:bg-purple-50 hover:border-purple-300 transition-colors text-left">
                        <div class="flex items-center gap-2">
                            <span x-show="v.hex_code" class="w-4 h-4 rounded-full border border-gray-300 flex-shrink-0" :style="'background:' + (v.hex_code||'#ccc')"></span>
                            <span class="text-sm font-medium text-gray-800" x-text="v.label"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span x-show="v.price_override" class="text-xs font-semibold text-blue-600" x-text="formatP(v.price_override)"></span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full" 
                                  :class="v.stock <= 5 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500'"
                                  x-text="v.stock + ' ш'"></span>
                        </div>
                    </button>
                </template>
            </div>
            <button @click="showVariantPicker = false" class="w-full mt-3 py-2 rounded-lg text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200">Болих</button>
        </div>
    </div>

    <!-- Payment Confirm Modal -->
    <div x-show="showPayConfirm" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @keydown.escape.window="showPayConfirm = false">
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center" @click.outside="showPayConfirm = false">
            <h3 class="text-lg font-bold text-gray-900 mb-4" x-text="editingOrder ? 'Засвар баталгаажуулах' : 'Төлбөр баталгаажуулах'"></h3>

            <!-- Edit mode info -->
            <template x-if="editingOrder">
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-3 text-left">
                    <p class="text-xs font-bold text-amber-800">✏️ <span x-text="editingOrder.order_number"></span> засварлах</p>
                    <p class="text-[11px] text-amber-600 mt-1">Шалтгаан: <span x-text="editReason"></span></p>
                </div>
            </template>

            <div class="rounded-xl p-4 mb-4" :class="paymentColors[paymentMethod]">
                <p class="text-3xl text-white mb-1" x-text="paymentLabels[paymentMethod]"></p>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 mb-5 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Бараа</span>
                    <span class="font-medium" x-text="totalItems + ' ширхэг'"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Дүн</span>
                    <span class="font-medium" x-text="formatP(subtotalPrice)"></span>
                </div>
                <template x-if="vatEnabled">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">НӨАТ (10%)</span>
                        <span class="font-medium text-orange-600" x-text="formatP(vatAmount)"></span>
                    </div>
                </template>
                <div class="flex justify-between text-xl font-bold">
                    <span>Нийт дүн</span>
                    <span class="text-gray-900" x-text="formatP(totalPrice)"></span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button @click="showPayConfirm = false"
                        class="py-3 rounded-xl font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
                    Буцах <span class="text-xs text-gray-400">(Esc)</span>
                </button>
                <button @click="showPayConfirm = false; completeSale()" :disabled="processing"
                        class="py-3 rounded-xl font-bold text-white transition-colors"
                        :class="paymentColors[paymentMethod] + ' hover:opacity-90'">
                    <span x-show="!processing">Батлах <span class="text-xs opacity-70">(Enter)</span></span>
                    <span x-show="processing">...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Split Payment Modal -->
    <div x-show="showSplitPay" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @keydown.escape.window="showSplitPay = false">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full" @click.outside="showSplitPay = false">
            <h3 class="text-lg font-bold text-gray-900 mb-1 text-center">✂️ Хуваасан төлбөр</h3>
            <p class="text-center text-sm text-gray-500 mb-4">Нийт: <strong class="text-gray-900" x-text="formatP(totalPrice)"></strong></p>

            <div class="space-y-3 mb-4">
                <div>
                    <label class="flex items-center justify-between text-sm font-medium text-gray-700 mb-1">
                        <span>💵 Бэлэн</span>
                        <button @click="splitCash = totalPrice - splitCard - splitTransfer - splitTransferNomin; if(splitCash < 0) splitCash = 0"
                                class="text-xs text-blue-500 hover:underline">Үлдэгдэл</button>
                    </label>
                    <input type="number" x-model.number="splitCash" min="0" :max="totalPrice" step="100" inputmode="numeric"
                           class="w-full px-4 py-3 border-2 border-green-300 rounded-xl text-lg font-medium focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none bg-green-50">
                </div>
                <div>
                    <label class="flex items-center justify-between text-sm font-medium text-gray-700 mb-1">
                        <span>💳 Карт</span>
                        <button @click="splitCard = totalPrice - splitCash - splitTransfer - splitTransferNomin; if(splitCard < 0) splitCard = 0"
                                class="text-xs text-blue-500 hover:underline">Үлдэгдэл</button>
                    </label>
                    <input type="number" x-model.number="splitCard" min="0" :max="totalPrice" step="100" inputmode="numeric"
                           class="w-full px-4 py-3 border-2 border-blue-300 rounded-xl text-lg font-medium focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-blue-50">
                </div>
                <div>
                    <label class="flex items-center justify-between text-sm font-medium text-gray-700 mb-1">
                        <span>📲 Шилжүүлэг</span>
                        <button @click="splitTransfer = totalPrice - splitCash - splitCard - splitTransferNomin; if(splitTransfer < 0) splitTransfer = 0"
                                class="text-xs text-blue-500 hover:underline">Үлдэгдэл</button>
                    </label>
                    <input type="number" x-model.number="splitTransfer" min="0" :max="totalPrice" step="100" inputmode="numeric"
                           class="w-full px-4 py-3 border-2 border-purple-300 rounded-xl text-lg font-medium focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none bg-purple-50">
                </div>
                <div>
                    <label class="flex items-center justify-between text-sm font-medium text-gray-700 mb-1">
                        <span>🏪 Номин шилжүүлэг</span>
                        <button @click="splitTransferNomin = totalPrice - splitCash - splitCard - splitTransfer; if(splitTransferNomin < 0) splitTransferNomin = 0"
                                class="text-xs text-blue-500 hover:underline">Үлдэгдэл</button>
                    </label>
                    <input type="number" x-model.number="splitTransferNomin" min="0" :max="totalPrice" step="100" inputmode="numeric"
                           class="w-full px-4 py-3 border-2 border-teal-300 rounded-xl text-lg font-medium focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none bg-teal-50">
                </div>
            </div>

            <!-- Remaining indicator -->
            <div class="rounded-xl p-3 mb-4 text-center"
                 :class="splitRemaining === 0 ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                <template x-if="splitRemaining === 0">
                    <span class="text-green-700 font-medium text-sm">✓ Төлбөр бүрэн хуваагдсан</span>
                </template>
                <template x-if="splitRemaining > 0">
                    <span class="text-red-600 font-medium text-sm">Үлдэгдэл: <span x-text="formatP(splitRemaining)"></span></span>
                </template>
                <template x-if="splitRemaining < 0">
                    <span class="text-red-600 font-medium text-sm">Илүүдэл: <span x-text="formatP(Math.abs(splitRemaining))"></span></span>
                </template>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button @click="showSplitPay = false"
                        class="py-3 rounded-xl font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
                    Буцах
                </button>
                <button @click="confirmSplitPay()" :disabled="splitRemaining !== 0 || processing"
                        class="py-3 rounded-xl font-bold text-white bg-orange-500 hover:bg-orange-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    Батлах
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal with Receipt -->
    <div x-show="showReceipt" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden flex flex-col" style="max-height: calc(100vh - 2rem)">
            <!-- Header -->
            <div class="bg-green-600 px-6 py-4 text-center text-white flex-shrink-0">
                <svg class="w-10 h-10 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <h3 class="text-lg font-bold" x-text="lastOrder.edited ? 'Захиалга засварлагдлаа!' : 'Борлуулалт амжилттай!'"></h3>
            </div>

            <!-- Receipt Preview -->
            <div id="receipt-content" class="px-6 py-4 overflow-y-auto flex-1 min-h-0">
                <div class="text-center border-b border-dashed border-gray-300 pb-3 mb-3">
                    <p class="font-bold text-lg"><?= e(getSetting('site_name', 'Runners World')) ?></p>
                    <p class="text-xs text-gray-500"><?= e(getSetting('address', '')) ?></p>
                    <p class="text-xs text-gray-500"><?= e(getSetting('phone', '')) ?></p>
                </div>

                <div class="flex justify-between text-xs text-gray-500 mb-2">
                    <span>Order #<span x-text="lastOrder.order_number"></span></span>
                    <span x-text="new Date().toLocaleString('mn-MN')"></span>
                </div>

                <div class="border-b border-dashed border-gray-300 pb-2 mb-2">
                    <template x-for="item in lastCart" :key="item.id + '-' + (item.variant_id||0)">
                        <div class="flex justify-between text-sm py-1">
                            <div class="flex-1">
                                <span x-text="item.name"></span>
                                <template x-if="item.variant_label">
                                    <span class="text-xs text-gray-500 ml-1" x-text="'(' + item.variant_label + ')'"></span>
                                </template>
                                <span class="text-gray-400 ml-1" x-text="'x' + item.qty"></span>
                                <template x-if="item.originalPrice && item.price !== item.originalPrice">
                                    <span class="text-xs text-orange-500 ml-1" x-text="'@' + formatP(item.price)"></span>
                                </template>
                            </div>
                            <div class="text-right">
                                <template x-if="item.customTotal != null && item.customTotal !== item.price * item.qty">
                                    <span class="text-[10px] line-through text-gray-300 block" x-text="formatP(item.price * item.qty)"></span>
                                </template>
                                <span class="font-medium" x-text="formatP(getLineTotal(item))"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-between text-sm text-gray-600 py-1">
                    <span>Дүн</span>
                    <span x-text="formatP(lastOrder.subtotal)"></span>
                </div>
                <template x-if="lastOrder.vat_included">
                    <div class="flex justify-between text-sm text-orange-600 py-1">
                        <span>НӨАТ (10%)</span>
                        <span x-text="formatP(lastOrder.vat_amount)"></span>
                    </div>
                </template>
                <div class="flex justify-between font-bold text-lg py-1">
                    <span>Нийт</span>
                    <span x-text="formatP(lastOrder.total)"></span>
                </div>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>Төлбөр</span>
                    <span x-text="paymentLabels[paymentMethod]"></span>
                </div>
                <template x-if="paymentMethod === 'split'">
                    <div class="space-y-1 mt-1">
                        <template x-if="lastOrder.payment_cash > 0">
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>💵 Бэлэн</span>
                                <span x-text="formatP(lastOrder.payment_cash)"></span>
                            </div>
                        </template>
                        <template x-if="lastOrder.payment_card > 0">
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>💳 Карт</span>
                                <span x-text="formatP(lastOrder.payment_card)"></span>
                            </div>
                        </template>
                        <template x-if="lastOrder.payment_transfer > 0">
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>📲 Шилжүүлэг</span>
                                <span x-text="formatP(lastOrder.payment_transfer)"></span>
                            </div>
                        </template>
                        <template x-if="lastOrder.payment_transfer_nomin > 0">
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>🏪 Номин шилжүүлэг</span>
                                <span x-text="formatP(lastOrder.payment_transfer_nomin)"></span>
                            </div>
                        </template>
                    </div>
                </template>

                <p class="text-center text-xs text-gray-400 mt-3 border-t border-dashed border-gray-300 pt-3">Баярлалаа! Дахин тавтай морилно уу!</p>
            </div>

            <!-- Buttons -->
            <div class="px-6 pb-5 pt-3 grid grid-cols-2 gap-3 flex-shrink-0 border-t border-gray-100">
                <button @click="showReceipt = false; newSale()"
                        class="py-3 rounded-xl font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
                    Хэвлэхгүй <span class="text-xs text-gray-400">(Esc)</span>
                </button>
                <button @click="printReceipt()"
                        class="py-3 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                    🖨️ Хэвлэх <span class="text-xs opacity-70">(P)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function posApp() {
    return {
        cart: [],
        search: '',
        categoryFilter: '',
        vatEnabled: false,
        paymentMethod: 'cash',
        processing: false,
        showReceipt: false,
        showPayConfirm: false,
        lastOrder: {},
        lastCart: [],
        paymentLabels: { cash: '💵 Бэлэн', card: '💳 Карт', transfer: '📲 Шилжүүлэг', transfer_nomin: '🏪 Номин шилжүүлэг', split: '✂️ Хуваасан' },
        paymentColors: { cash: 'bg-green-600', card: 'bg-blue-600', transfer: 'bg-purple-600', transfer_nomin: 'bg-teal-600', split: 'bg-orange-500' },
        csrfToken: '<?= generateCSRFToken() ?>',
        barcodeMap: <?= json_encode($barcodeMap, JSON_HEX_APOS | JSON_HEX_TAG) ?>,
        allProducts: <?= $productsJson ?>,
        barcodeInput: '',
        editingIndex: -1,
        editTotalValue: 0,
        showSplitPay: false,
        splitCash: 0,
        splitCard: 0,
        splitTransfer: 0,
        splitTransferNomin: 0,
        showDropdown: false,
        dropdownIdx: -1,
        editingOrder: null,
        editReason: '',
        _loadingOrder: false,
        _barcodeBuffer: '',
        _barcodeTimer: null,
        _lastScanFeedback: null,
        showVariantPicker: false,
        variantPickerProduct: null,

        initScanner() {
            // Restore cart from localStorage
            try {
                const saved = localStorage.getItem('pos_cart');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    if (Array.isArray(parsed) && parsed.length > 0) this.cart = parsed;
                }
            } catch(e) {}

            // Auto-save cart on changes
            this.$watch('cart', (val) => {
                try {
                    if (val.length > 0) {
                        localStorage.setItem('pos_cart', JSON.stringify(val));
                    } else {
                        localStorage.removeItem('pos_cart');
                    }
                } catch(e) {}
            }, { deep: true });

            // Focus barcode input on load
            this.$nextTick(() => this.$refs.barcodeInput.focus());

            document.addEventListener('keydown', (e) => {
                // F1 → barcode input
                if (e.key === 'F1') {
                    e.preventDefault();
                    this.$refs.barcodeInput.focus();
                    this.$refs.barcodeInput.select();
                    return;
                }
                // F2 → search input
                if (e.key === 'F2') {
                    e.preventDefault();
                    this.$refs.searchInput.focus();
                    this.$refs.searchInput.select();
                    return;
                }
                // F3 → Cash sale
                if (e.key === 'F3') {
                    e.preventDefault();
                    if (this.cart.length > 0 && !this.processing) this.confirmPay('cash');
                    return;
                }
                // F4 → Card sale
                if (e.key === 'F4') {
                    e.preventDefault();
                    if (this.cart.length > 0 && !this.processing) this.confirmPay('card');
                    return;
                }
                // F5 → Transfer sale
                if (e.key === 'F5') {
                    e.preventDefault();
                    if (this.cart.length > 0 && !this.processing) this.confirmPay('transfer');
                    return;
                }
                // F6 → Transfer Nomin sale
                if (e.key === 'F6') {
                    e.preventDefault();
                    if (this.cart.length > 0 && !this.processing) this.confirmPay('transfer_nomin');
                    return;
                }
                // F7 → Split payment
                if (e.key === 'F7') {
                    e.preventDefault();
                    if (this.cart.length > 0 && !this.processing) this.openSplitPay();
                    return;
                }
                // F8 → Clear cart
                if (e.key === 'F8') {
                    e.preventDefault();
                    if (this.cart.length > 0) this.clearCart();
                    return;
                }
                // Esc → Close modal or clear search
                if (e.key === 'Escape') {
                    if (this.showSplitPay) {
                        this.showSplitPay = false;
                    } else if (this.showPayConfirm) {
                        this.showPayConfirm = false;
                    } else if (this.showReceipt) {
                        this.showReceipt = false;
                        this.newSale();
                    } else {
                        this.search = '';
                        this.barcodeInput = '';
                        this.$refs.barcodeInput.focus();
                    }
                    return;
                }
                // P → Print receipt when receipt modal is open
                if ((e.key === 'p' || e.key === 'P') && this.showReceipt && !e.ctrlKey) {
                    e.preventDefault();
                    this.printReceipt();
                    return;
                }
                // Enter → confirm payment if modal open
                if (e.key === 'Enter' && this.showPayConfirm) {
                    e.preventDefault();
                    this.showPayConfirm = false;
                    this.completeSale();
                    return;
                }

                // Hardware scanner detection: rapid keystrokes ending with Enter
                // (works even when no input is focused)
                const tag = e.target.tagName;
                const isBarcodeInput = e.target === this.$refs.barcodeInput;
                const isSearchInput = e.target === this.$refs.searchInput;
                const isOtherInput = (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') && !isBarcodeInput && !isSearchInput;
                if (isOtherInput) return;

                if (e.key === 'Enter' && this._barcodeBuffer.length >= 4 && !isBarcodeInput) {
                    e.preventDefault();
                    this.handleBarcodeScan(this._barcodeBuffer);
                    this._barcodeBuffer = '';
                    if (isSearchInput) this.search = '';
                    return;
                }

                if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey && !isBarcodeInput) {
                    this._barcodeBuffer += e.key;
                    clearTimeout(this._barcodeTimer);
                    this._barcodeTimer = setTimeout(() => { this._barcodeBuffer = ''; }, 150);
                }
            });
        },

        scanBarcode() {
            const code = this.barcodeInput.trim();
            if (!code) return;
            this.handleBarcodeScan(code);
            this.barcodeInput = '';
            this.$refs.barcodeInput.focus();
        },

        handleBarcodeScan(barcode) {
            const product = this.barcodeMap[barcode];
            if (product) {
                this.addToCart({...product});
                this.showScanFeedback(product.name, true);
            } else {
                this.showScanFeedback('Баркод олдсонгүй: ' + barcode, false);
            }
        },

        showScanFeedback(msg, success) {
            clearTimeout(this._lastScanFeedback);
            const el = document.getElementById('scan-feedback');
            el.textContent = (success ? '✓ ' : '✗ ') + msg;
            el.className = 'text-xs px-3 py-1.5 rounded-lg font-medium transition-opacity ' +
                (success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
            el.style.opacity = '1';
            this._lastScanFeedback = setTimeout(() => { el.style.opacity = '0'; }, 2500);
        },

        matchesFilter(category, name, shop, barcode) {
            if (this.categoryFilter && category !== this.categoryFilter) return false;
            if (this.search) {
                const s = this.search.toLowerCase();
                return name.toLowerCase().includes(s) || shop.toLowerCase().includes(s) || (barcode && barcode.includes(s));
            }
            return true;
        },

        get filteredProducts() {
            if (!this.search || this.search.length === 0) return [];
            const s = this.search.toLowerCase();
            return this.allProducts.filter(p => {
                if (this.categoryFilter && p.category !== this.categoryFilter) return false;
                return p.name.toLowerCase().includes(s) || p.shop.toLowerCase().includes(s) || (p.barcode && p.barcode.includes(s));
            }).slice(0, 12);
        },

        dropdownNav(dir) {
            if (!this.showDropdown || this.filteredProducts.length === 0) return;
            this.dropdownIdx = Math.max(-1, Math.min(this.filteredProducts.length - 1, this.dropdownIdx + dir));
        },

        dropdownSelect() {
            if (this.dropdownIdx >= 0 && this.dropdownIdx < this.filteredProducts.length) {
                this.addToCart(this.filteredProducts[this.dropdownIdx]);
                this.search = '';
                this.showDropdown = false;
                this.dropdownIdx = -1;
            }
        },

        addToCart(product) {
            // If product has variants, open variant picker
            if (product.has_variants && product.variants && product.variants.length > 0) {
                this.variantPickerProduct = product;
                this.showVariantPicker = true;
                return;
            }
            const existing = this.cart.find(i => i.id === product.id && !i.variant_id);
            if (existing) {
                if (existing.qty < product.stock) {
                    existing.qty++;
                    existing.customTotal = null; // reset override when qty changes
                } else {
                    alert('Нөөцийн дээд хэмжээ: ' + product.stock);
                }
            } else {
                this.cart.push({ ...product, qty: 1, customTotal: null, originalPrice: product.price, variant_id: null, variant_label: null });
            }
        },

        selectVariant(variant) {
            const product = this.variantPickerProduct;
            if (!product) return;
            const price = variant.price_override || product.price;
            const cartKey = product.id + '-' + variant.id;
            const existing = this.cart.find(i => i.id === product.id && i.variant_id === variant.id);
            if (existing) {
                if (existing.qty < variant.stock) {
                    existing.qty++;
                    existing.customTotal = null;
                } else {
                    alert('Нөөцийн дээд хэмжээ: ' + variant.stock);
                }
            } else {
                this.cart.push({
                    id: product.id, name: product.name, price: price,
                    stock: variant.stock, qty: 1, customTotal: null, originalPrice: price,
                    category: product.category, shop: product.shop,
                    variant_id: variant.id, variant_label: variant.label,
                });
            }
            this.showVariantPicker = false;
            this.variantPickerProduct = null;
        },

        updateQty(index, delta) {
            const item = this.cart[index];
            const newQty = item.qty + delta;
            if (newQty <= 0) {
                this.cart.splice(index, 1);
            } else if (newQty <= item.stock) {
                item.qty = newQty;
                item.customTotal = null; // reset override when qty changes
            } else {
                alert('Нөөцийн дээд: ' + item.stock);
            }
        },

        setQty(index, value) {
            const item = this.cart[index];
            const newQty = parseInt(value) || 0;
            if (newQty <= 0) {
                this.cart.splice(index, 1);
            } else if (newQty <= item.stock) {
                item.qty = newQty;
                item.customTotal = null;
            } else {
                item.qty = item.stock;
                item.customTotal = null;
                alert('Нөөцийн дээд: ' + item.stock);
            }
        },

        setPrice(index, value) {
            const item = this.cart[index];
            const newPrice = parseFloat(value) || 0;
            if (newPrice >= 0) {
                item.price = newPrice;
                item.customTotal = null;
            }
        },

        removeFromCart(index) {
            this.cart.splice(index, 1);
        },

        clearCart() {
            if (confirm('Бүх барааг хасах уу?')) {
                this.cart = [];
                this.editingIndex = -1;
                this.editingOrder = null;
                this.editReason = '';
                localStorage.removeItem('pos_cart');
            }
        },

        cancelEdit() {
            this.editingOrder = null;
            this.editReason = '';
            this.cart = [];
            this.editingIndex = -1;
            localStorage.removeItem('pos_cart');
        },

        async loadOrder(orderId) {
            if (this._loadingOrder) return;
            if (this.cart.length > 0 && !confirm(this.editingOrder ? 'Одоогийн засварыг орхих уу?' : 'Сагсанд бараа байна. Орлуулах уу?')) return;
            this._loadingOrder = true;
            try {
                const res = await fetch('index.php?page=pos&action=load-order&id=' + orderId);
                const data = await res.json();
                if (data.error) { alert(data.error); return; }
                this.cart = data.items.map(i => ({ ...i, originalPrice: i.price, variant_id: i.variant_id || null, variant_label: i.variant_label || null }));
                this.editingOrder = {
                    id: data.order.id,
                    order_number: data.order.order_number,
                    old_total: data.order.total,
                    payment_method: data.order.payment_method,
                };
                this.vatEnabled = data.order.vat_included;
                this.editReason = '';
                this.editingIndex = -1;
            } catch (e) {
                alert('Алдаа гарлаа');
            }
            this._loadingOrder = false;
        },

        confirmPay(method) {
            if (this.editingOrder && !this.editReason.trim()) {
                alert('Засварын шалтгаан оруулна уу!');
                return;
            }
            this.paymentMethod = method;
            this.showPayConfirm = true;
        },

        openSplitPay() {
            if (this.editingOrder && !this.editReason.trim()) {
                alert('Засварын шалтгаан оруулна уу!');
                return;
            }
            this.splitCash = 0;
            this.splitCard = 0;
            this.splitTransfer = 0;
            this.splitTransferNomin = 0;
            this.showSplitPay = true;
        },

        confirmSplitPay() {
            if (this.splitRemaining !== 0) return;
            this.paymentMethod = 'split';
            this.showSplitPay = false;
            this.completeSale();
        },

        get totalItems() {
            return this.cart.reduce((sum, i) => sum + i.qty, 0);
        },

        get subtotalPrice() {
            return this.cart.reduce((sum, i) => sum + this.getLineTotal(i), 0);
        },

        get vatAmount() {
            return this.vatEnabled ? Math.round(this.subtotalPrice * 0.10) : 0;
        },

        get totalPrice() {
            return this.subtotalPrice + this.vatAmount;
        },

        get splitRemaining() {
            return this.totalPrice - (this.splitCash + this.splitCard + this.splitTransfer + this.splitTransferNomin);
        },

        formatP(price) {
            return new Intl.NumberFormat().format(price) + '₮';
        },

        getLineTotal(item) {
            return (item.customTotal != null && item.customTotal > 0) ? item.customTotal : item.price * item.qty;
        },

        startEditTotal(index) {
            this.editingIndex = index;
            this.editTotalValue = this.getLineTotal(this.cart[index]);
            this.$nextTick(() => {
                const input = this.$refs.editTotalInput;
                if (input) { input.focus(); input.select(); }
            });
        },

        saveEditTotal(index) {
            if (this.editingIndex !== index) return;
            const item = this.cart[index];
            const calculated = item.price * item.qty;
            if (this.editTotalValue > 0 && this.editTotalValue !== calculated) {
                item.customTotal = Math.round(this.editTotalValue);
            } else {
                item.customTotal = null;
            }
            this.editingIndex = -1;
        },

        cancelEditTotal() {
            this.editingIndex = -1;
        },

        resetLineTotal(index) {
            this.cart[index].customTotal = null;
            this.editingIndex = -1;
        },

        async completeSale() {
            if (this.cart.length === 0 || this.processing) return;
            this.processing = true;

            const payload = {
                csrf_token: this.csrfToken,
                items: this.cart.map(i => {
                    const o = { id: i.id, qty: i.qty };
                    if (i.customTotal != null && i.customTotal > 0) o.custom_total = i.customTotal;
                    if (i.price !== i.originalPrice) o.custom_price = i.price;
                    if (i.variant_id) o.variant_id = i.variant_id;
                    return o;
                }),
                payment_method: this.paymentMethod,
                vat_included: this.vatEnabled,
                payment_split: this.paymentMethod === 'split' ? {
                    cash: this.splitCash,
                    card: this.splitCard,
                    transfer: this.splitTransfer,
                    transfer_nomin: this.splitTransferNomin,
                } : null,
            };

            if (this.editingOrder) {
                payload.action = 'edit';
                payload.edit_order_id = this.editingOrder.id;
                payload.edit_reason = this.editReason.trim();
            }

            try {
                const res = await fetch('index.php?page=pos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    this.lastOrder = data;
                    this.lastCart = [...this.cart];
                    this.showReceipt = true;
                    localStorage.removeItem('pos_cart');
                }
            } catch (err) {
                alert('Network error. Please try again.');
            }
            this.processing = false;
        },

        newSale() {
            window.location.reload();
        },

        printReceipt() {
            const content = document.getElementById('receipt-content').innerHTML;
            const win = window.open('', '_blank', 'width=300,height=600');
            win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Courier New', monospace; font-size: 12px; padding: 8px; width: 80mm; }
                    .text-center { text-align: center; }
                    .text-xs { font-size: 10px; }
                    .text-sm { font-size: 12px; }
                    .text-lg { font-size: 16px; }
                    .font-bold { font-weight: bold; }
                    .font-medium { font-weight: 500; }
                    .text-gray-400, .text-gray-500 { color: #888; }
                    .border-b { border-bottom: 1px dashed #ccc; }
                    .border-t { border-top: 1px dashed #ccc; }
                    .border-dashed { border-style: dashed; }
                    .border-gray-300 { border-color: #ccc; }
                    .py-1 { padding: 2px 0; }
                    .pb-2, .pb-3 { padding-bottom: 8px; }
                    .pt-3 { padding-top: 8px; }
                    .mb-2, .mb-3 { margin-bottom: 8px; }
                    .mt-3 { margin-top: 8px; }
                    .ml-1 { margin-left: 4px; }
                    .flex { display: flex; }
                    .flex-1 { flex: 1; }
                    .justify-between { justify-content: space-between; }
                    @media print { body { width: 80mm; } }
                </style></head><body>` + content + `</body></html>`);
            win.document.close();
            win.onload = () => { win.print(); win.onafterprint = () => win.close(); };
            this.showReceipt = false;
            this.newSale();
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
