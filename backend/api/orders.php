<?php
/**
 * API: Create Order
 * POST /api/orders.php
 * 
 * Request body (JSON):
 * {
 *   "customer_name": "...",
 *   "customer_phone": "...",
 *   "fulfillment": "delivery" | "pickup",
 *   "district_id": 1,            // required if delivery
 *   "khoroo_id": 1,              // required if delivery
 *   "address": "...",            // required if delivery
 *   "detail_address": "...",     // optional
 *   "payment_method": "qpay" | "card" | "cash",
 *   "notes": "...",              // optional
 *   "save_address": true,        // optional, save address to customer profile
 *   "items": [
 *     { "product_id": 1, "quantity": 2 },
 *     ...
 *   ]
 * }
 * 
 * Header (optional): Authorization: Bearer <token>
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDB();

// Release stock from expired unpaid orders before checking availability
cancelExpiredOrders();

// ── Optional auth: get customer_id if token provided ──
$customerId = null;
$bearerToken = getBearerToken();
if ($bearerToken) {
    $stmt = $db->prepare("
        SELECT c.id, c.phone, c.name FROM customer_sessions s
        JOIN customers c ON c.id = s.customer_id
        WHERE s.token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$bearerToken]);
    $authCustomer = $stmt->fetch();
    if ($authCustomer) {
        $customerId = (int)$authCustomer['id'];
    }
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// ── Action routing ──────────────────────────────────────
$action = $_GET['action'] ?? 'create';

if ($action === 'cancel') {
    $orderNumber = trim($input['order_number'] ?? '');
    if (!$orderNumber) {
        http_response_code(400);
        echo json_encode(['error' => 'order_number шаардлагатай']);
        exit;
    }
    $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE order_number = ? AND payment_status = 'pending' AND status NOT IN ('cancelled', 'completed', 'delivered')");
    $stmt->execute([$orderNumber]);
    auditLog('order_cancelled', 'order', 0, $customerId ? 'customer' : 'system', $customerId, ['order_number' => $orderNumber]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'update-payment-method') {
    $orderNumber    = trim($input['order_number'] ?? '');
    $paymentMethod  = trim($input['payment_method'] ?? '');
    $allowed        = ['qpay', 'bonum', 'storepay', 'transfer', 'cash', 'card'];
    if (!$orderNumber || !in_array($paymentMethod, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'order_number болон payment_method шаардлагатай']);
        exit;
    }
    $db->prepare("UPDATE orders SET payment_method = ? WHERE order_number = ? AND payment_status = 'pending'")
       ->execute([$paymentMethod, $orderNumber]);
    echo json_encode(['success' => true]);
    exit;
}

// Validate required fields
$errors = [];
if (empty(trim($input['customer_name'] ?? ''))) $errors[] = 'Customer name is required';
if (empty(trim($input['customer_phone'] ?? ''))) $errors[] = 'Customer phone is required';
$phoneClean = preg_replace('/[^0-9]/', '', trim($input['customer_phone'] ?? ''));
if (!empty(trim($input['customer_phone'] ?? '')) && strlen($phoneClean) !== 8) $errors[] = 'Phone must be 8 digits';

$fulfillment = $input['fulfillment'] ?? 'delivery';
if (!in_array($fulfillment, ['delivery', 'pickup'])) $errors[] = 'Invalid fulfillment type';

if ($fulfillment === 'delivery') {
    if (empty($input['district_id'])) $errors[] = 'District is required for delivery';
    if (empty($input['khoroo_id'])) $errors[] = 'Khoroo is required for delivery';
    if (empty(trim($input['address'] ?? ''))) $errors[] = 'Address is required for delivery';
}

if (empty($input['items']) || !is_array($input['items'])) $errors[] = 'Order items are required';

$paymentMethod = $input['payment_method'] ?? 'qpay';
if (!in_array($paymentMethod, ['qpay', 'card', 'cash', 'transfer', 'bonum', 'storepay'])) $errors[] = 'Invalid payment method';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed', 'errors' => $errors]);
    exit;
}

$db = getDB();

// ── Duplicate order detection ──
// If same phone has a pending order with same items created within last 5 minutes, return it
$itemsKey = [];
foreach ($input['items'] as $item) {
    $vid = !empty($item['variant_id']) ? (int)$item['variant_id'] : 0;
    $itemsKey[] = (int)$item['product_id'] . ':' . $vid . ':' . (int)$item['quantity'];
}
sort($itemsKey);
$itemsHash = md5(implode('|', $itemsKey));

$dupStmt = $db->prepare("
    SELECT o.id, o.order_number, o.total, o.subtotal, o.delivery_fee, o.cargo_fee
    FROM orders o
    WHERE o.customer_phone = ?
      AND o.status = 'pending'
      AND o.payment_status = 'pending'
      AND o.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY o.created_at DESC
    LIMIT 5
");
$dupStmt->execute([$phoneClean]);
$recentOrders = $dupStmt->fetchAll();

foreach ($recentOrders as $recent) {
    // Compare items
    $existingItems = $db->prepare("SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id");
    $existingItems->execute([$recent['id']]);
    $existingKey = [];
    foreach ($existingItems->fetchAll() as $ei) {
        $vid = (int)($ei['variant_id'] ?? 0);
        $existingKey[] = (int)$ei['product_id'] . ':' . $vid . ':' . (int)$ei['quantity'];
    }
    sort($existingKey);
    if (md5(implode('|', $existingKey)) === $itemsHash) {
        // Same order exists — return it instead of creating duplicate
        echo json_encode([
            'success' => true,
            'order_number' => $recent['order_number'],
            'total' => (float)$recent['total'],
            'subtotal' => (float)$recent['subtotal'],
            'delivery_fee' => (float)$recent['delivery_fee'],
            'cargo_fee' => (float)$recent['cargo_fee'],
            'duplicate' => true,
        ]);
        exit;
    }
}

// Verify items and gather product data
$productIds = array_map(function($item) { return (int)$item['product_id']; }, $input['items']);
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$stmt = $db->prepare("SELECT id, name, price, type, weight_kg, stock, has_variants, is_active, cargo_batch_id, hide_cargo_fee FROM products WHERE id IN ($placeholders)");
$stmt->execute($productIds);
$products = [];
foreach ($stmt->fetchAll() as $p) {
    $products[(int)$p['id']] = $p;
}

// Load cargo rates for batches referenced by products
$batchRates = [];
$batchIds = array_unique(array_filter(array_column($products, 'cargo_batch_id')));
if (!empty($batchIds)) {
    $bPlaceholders = implode(',', array_fill(0, count($batchIds), '?'));
    $bStmt = $db->prepare("SELECT id, cargo_rate_per_kg FROM cargo_batches WHERE id IN ($bPlaceholders) AND status = 'open'");
    $bStmt->execute(array_values($batchIds));
    foreach ($bStmt->fetchAll() as $b) {
        $batchRates[(int)$b['id']] = (float)$b['cargo_rate_per_kg'];
    }
}

// Load price tiers for no-variant products (tiers do not apply to variant products — see y.md)
$productTiers = []; // product_id => [['min_qty' => int, 'unit_price' => float], ...] sorted ASC
$noVariantIds = [];
foreach ($products as $p) {
    if (empty($p['has_variants'])) $noVariantIds[] = (int)$p['id'];
}
if (!empty($noVariantIds)) {
    $tPlaceholders = implode(',', array_fill(0, count($noVariantIds), '?'));
    $tStmt = $db->prepare("SELECT product_id, min_qty, unit_price FROM product_price_tiers WHERE product_id IN ($tPlaceholders) ORDER BY product_id, min_qty ASC");
    $tStmt->execute($noVariantIds);
    foreach ($tStmt->fetchAll() as $t) {
        $productTiers[(int)$t['product_id']][] = [
            'min_qty'    => (int)$t['min_qty'],
            'unit_price' => (float)$t['unit_price'],
        ];
    }
}

// effective per-unit price given a quantity (highest matching tier wins, fallback to base price)
function effectiveTierPrice(float $basePrice, array $tiers, int $qty): float {
    $price = $basePrice;
    foreach ($tiers as $t) {
        if ($qty >= $t['min_qty']) {
            $price = (float)$t['unit_price'];
        } else {
            break; // tiers are sorted ASC by min_qty
        }
    }
    return $price;
}

$hasPreorder = false;
$subtotal = 0;
$totalCargoFee = 0;
$visibleCargoFee = 0;
$orderItems = [];

foreach ($input['items'] as $item) {
    $pid = (int)($item['product_id'] ?? 0);
    $qty = (int)($item['quantity'] ?? 0);
    $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

    if ($qty <= 0) {
        http_response_code(400);
        echo json_encode(['error' => "Invalid quantity for product ID {$pid}"]);
        exit;
    }

    if (!isset($products[$pid])) {
        http_response_code(400);
        echo json_encode(['error' => "Product not found: ID {$pid}"]);
        exit;
    }

    $product = $products[$pid];

    if (!$product['is_active']) {
        http_response_code(400);
        echo json_encode(['error' => "Product '{$product['name']}' is not available"]);
        exit;
    }

    // Variant validation
    $variantLabel = null;
    $variantPrice = null;
    $shouldDeductStock = false;
    if ($product['has_variants']) {
        if (!$variantId) {
            http_response_code(400);
            echo json_encode(['error' => "Variant selection required for '{$product['name']}'"]);
            exit;
        }
        $vStmt = $db->prepare("
            SELECT pv.*, pc.name_mn as color_name, ps.name as size_name, pv.price_override
            FROM product_variants pv
            LEFT JOIN product_colors pc ON pv.color_id = pc.id
            LEFT JOIN product_sizes ps ON pv.size_id = ps.id
            WHERE pv.id = ? AND pv.product_id = ? AND pv.is_active = 1
        ");
        $vStmt->execute([$variantId, $pid]);
        $variant = $vStmt->fetch();
        if (!$variant) {
            http_response_code(400);
            echo json_encode(['error' => "Invalid variant for '{$product['name']}'"]);
            exit;
        }
        $shouldDeductStock = $product['type'] === 'ready' || ($product['type'] === 'preorder' && $variant['stock'] !== null);
        if ($shouldDeductStock && (int)$variant['stock'] < $qty) {
            http_response_code(400);
            echo json_encode(['error' => "Insufficient stock for '{$product['name']}' variant. Available: {$variant['stock']}"]);
            exit;
        }
        $variantLabel = trim(($variant['color_name'] ?? '') . ($variant['color_name'] && $variant['size_name'] ? ' / ' : '') . ($variant['size_name'] ?? ''));
        $variantPrice = $variant['price_override'] !== null ? (float)$variant['price_override'] : null;
    } else {
        $shouldDeductStock = $product['type'] === 'ready' || ($product['type'] === 'preorder' && $product['stock'] !== null);
        if ($shouldDeductStock && (int)$product['stock'] < $qty) {
            http_response_code(400);
            echo json_encode(['error' => "Insufficient stock for '{$product['name']}'. Available: {$product['stock']}"]);
            exit;
        }
    }

    if ($product['type'] === 'preorder') {
        $hasPreorder = true;
        $pBatchId = $product['cargo_batch_id'] ? (int)$product['cargo_batch_id'] : null;
        if (!$pBatchId || !isset($batchRates[$pBatchId])) {
            http_response_code(400);
            echo json_encode(['error' => "Product '{$product['name']}' is not assigned to an open cargo batch"]);
            exit;
        }
    }

    if ($variantPrice !== null) {
        // Variant overrides win — tiers do not apply to variant products
        $itemPrice = $variantPrice;
    } elseif (empty($product['has_variants']) && !empty($productTiers[$pid])) {
        // Apply quantity-based tier price for no-variant product
        $itemPrice = effectiveTierPrice((float)$product['price'], $productTiers[$pid], $qty);
    } else {
        $itemPrice = (float)$product['price'];
    }
    $itemSubtotal = $itemPrice * $qty;
    $subtotal += $itemSubtotal;

    $cargoFee = 0;
    $itemBatchId = null;
    if ($product['type'] === 'preorder' && $product['weight_kg'] && $product['cargo_batch_id']) {
        $itemBatchId = (int)$product['cargo_batch_id'];
        $rate = $batchRates[$itemBatchId] ?? 0;
        $cargoFee = round((float)$product['weight_kg'] * $rate * $qty, 2);
        $totalCargoFee += $cargoFee;
        if (empty($product['hide_cargo_fee'])) {
            $visibleCargoFee += $cargoFee;
        }
    }

    $orderItems[] = [
        'product_id' => $pid,
        'variant_id' => $variantId,
        'variant_label' => $variantLabel,
        'product_name' => $product['name'],
        'product_price' => $itemPrice,
        'quantity' => $qty,
        'weight_kg' => $product['weight_kg'],
        'cargo_fee' => $cargoFee,
        'cargo_batch_id' => $itemBatchId,
        'type' => $product['type'],
        'has_variants' => (int)$product['has_variants'],
        'hide_cargo_fee' => !empty($product['hide_cargo_fee']) ? 1 : 0,
        'deduct_stock' => $shouldDeductStock,
    ];
}

// Calculate delivery fee
$deliveryFee = 0;
if ($fulfillment === 'delivery' && getSetting('delivery_fee_enabled', '1') === '1') {
    $feeAmount = (float)getSetting('delivery_fee', 5000);
    $freeThreshold = (float)getSetting('free_delivery_threshold', 50000);
    if ($subtotal < $freeThreshold) {
        $deliveryFee = $feeAmount;
    }
}

$total = $subtotal + $visibleCargoFee + $deliveryFee; // hidden cargo fees are saved in DB but not charged via QPay

// Create order in transaction
$db->beginTransaction();

try {
    // Re-verify stock with row lock inside transaction
    $lockStmt = $db->prepare("SELECT id, stock, type, has_variants FROM products WHERE id IN ($placeholders) FOR UPDATE");
    $lockStmt->execute($productIds);
    $lockedProducts = [];
    foreach ($lockStmt->fetchAll() as $lp) {
        $lockedProducts[(int)$lp['id']] = $lp;
    }
    // Lock variant rows too
    $variantIds = array_filter(array_column($orderItems, 'variant_id'));
    if ($variantIds) {
        $vPlaceholders = implode(',', array_fill(0, count($variantIds), '?'));
        $db->prepare("SELECT id, stock FROM product_variants WHERE id IN ($vPlaceholders) FOR UPDATE")->execute(array_values($variantIds));
    }
    foreach ($orderItems as $oi) {
        $pid = $oi['product_id'];
        $qty = $oi['quantity'];
        if (isset($lockedProducts[$pid]) && ($oi['deduct_stock'] ?? false)) {
            if ($lockedProducts[$pid]['has_variants'] && $oi['variant_id']) {
                $vCheck = $db->prepare("SELECT stock FROM product_variants WHERE id = ?");
                $vCheck->execute([$oi['variant_id']]);
                $vStock = $vCheck->fetchColumn();
                if ($vStock !== null && (int)$vStock < $qty) {
                    throw new Exception("Insufficient stock for variant of product ID {$pid}");
                }
            } elseif (!$lockedProducts[$pid]['has_variants']) {
                $lStock = $lockedProducts[$pid]['stock'];
                if ($lStock !== null && (int)$lStock < $qty) {
                    throw new Exception("Insufficient stock for product ID {$pid}");
                }
            }
        }
    }

    $orderNumber = generateOrderNumber($db);

    // Determine primary cargo_batch_id for the order (latest batch = highest ID)
    $orderBatchIds = array_unique(array_filter(array_column($orderItems, 'cargo_batch_id')));
    $primaryBatchId = !empty($orderBatchIds) ? max($orderBatchIds) : null;

    $stmt = $db->prepare("
        INSERT INTO orders (order_number, order_type, fulfillment, status,
            customer_id, customer_name, customer_phone,
            district_id, khoroo_id, address, detail_address,
            subtotal, delivery_fee, cargo_fee, total,
            cargo_batch_id, payment_method, payment_status, notes)
        VALUES (?, 'online', ?, 'pending',
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, 'pending', ?)
    ");

    $stmt->execute([
        $orderNumber,
        $fulfillment,
        $customerId,
        htmlspecialchars(trim($input['customer_name']), ENT_QUOTES, 'UTF-8'),
        $phoneClean,
        $fulfillment === 'delivery' ? (int)$input['district_id'] : null,
        $fulfillment === 'delivery' ? (int)$input['khoroo_id'] : null,
        $fulfillment === 'delivery' ? trim($input['address']) : null,
        $fulfillment === 'delivery' ? trim($input['detail_address'] ?? '') : null,
        $subtotal,
        $deliveryFee,
        $totalCargoFee,
        $total,
        $primaryBatchId,
        $paymentMethod,
        trim($input['notes'] ?? ''),
    ]);

    $orderId = $db->lastInsertId();

    // Insert order items
    $stmtItem = $db->prepare("
        INSERT INTO order_items (order_id, product_id, variant_id, variant_label, product_name, product_price, quantity, weight_kg, cargo_fee, cargo_batch_id, cargo_status, hide_cargo_fee)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($orderItems as $oi) {
        $stmtItem->execute([
            $orderId,
            $oi['product_id'],
            $oi['variant_id'],
            $oi['variant_label'],
            $oi['product_name'],
            $oi['product_price'],
            $oi['quantity'],
            $oi['weight_kg'],
            $oi['cargo_fee'],
            $oi['cargo_batch_id'],
            $oi['cargo_batch_id'] ? 'pending' : null,
            $oi['hide_cargo_fee'],
        ]);

        $r = adjustStock(
            $db,
            (int)$oi['product_id'],
            $oi['variant_id'] ? (int)$oi['variant_id'] : null,
            -(int)$oi['quantity'],
            'order_sale',
            (int)$orderId,
            $customerId ? 'customer' : 'system',
            $customerId ?: null,
            'Order ' . $orderNumber
        );
        if ($r['status'] === 'insufficient') {
            throw new Exception("Stock depleted for '{$oi['product_name']}'");
        }
    }

    $db->commit();

    // Save address for customer if delivery order:
    // - always save when customer has no addresses yet (first order)
    // - save when explicitly requested via save_address flag
    if ($customerId && $fulfillment === 'delivery') {
        try {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?");
            $countStmt->execute([$customerId]);
            $addressCount = (int)$countStmt->fetchColumn();

            if ($addressCount === 0 || !empty($input['save_address'])) {
                $stmtAddr = $db->prepare("
                    INSERT INTO customer_addresses (customer_id, label, district_id, khoroo_id, address, detail_address, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $isFirst = $addressCount === 0;

                $stmtAddr->execute([
                    $customerId,
                    '',
                    (int)$input['district_id'],
                    (int)$input['khoroo_id'],
                    trim($input['address']),
                    trim($input['detail_address'] ?? ''),
                    $isFirst ? 1 : 0,
                ]);
            }
        } catch (Exception $e) {
            // Non-critical, don't fail the order
        }
    }

    echo json_encode([
        'success' => true,
        'order_number' => $orderNumber,
        'total' => $total,
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'cargo_fee' => $totalCargoFee,
    ]);

    auditLog('order_created', 'order', $orderId, $customerId ? 'customer' : 'system', $customerId, [
        'order_number' => $orderNumber,
        'total' => $total,
        'items_count' => count($orderItems),
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
