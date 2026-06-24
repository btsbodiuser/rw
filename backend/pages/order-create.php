<?php
$pageTitle = 'Захиалга үүсгэх';
$db = getDB();

// ── AJAX: Customer phone lookup ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'lookup-customer') {
    header('Content-Type: application/json');
    $phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
    if (strlen($phone) !== 8) {
        echo json_encode(['found' => false]);
        exit;
    }
    $stmt = $db->prepare("SELECT c.id, c.phone, c.name FROM customers c WHERE c.phone = ?");
    $stmt->execute([$phone]);
    $customer = $stmt->fetch();
    if ($customer) {
        // Get default address
        $addrStmt = $db->prepare("
            SELECT a.*, d.name_mn as district_name, COALESCE(k.number,'') as khoroo_number, COALESCE(k.name,'') as khoroo_name
            FROM customer_addresses a
            LEFT JOIN districts d ON d.id = a.district_id
            LEFT JOIN khoroos k ON k.id = a.khoroo_id
            WHERE a.customer_id = ?
            ORDER BY a.is_default DESC, a.created_at DESC
        ");
        $addrStmt->execute([$customer['id']]);
        $addresses = $addrStmt->fetchAll();
        echo json_encode([
            'found' => true,
            'customer' => [
                'id' => (int)$customer['id'],
                'name' => $customer['name'],
                'phone' => $customer['phone'],
            ],
            'addresses' => array_map(function($a) {
                return [
                    'id' => (int)$a['id'],
                    'district_id' => (int)$a['district_id'],
                    'khoroo_id' => (int)$a['khoroo_id'],
                    'address' => $a['address'],
                    'detail_address' => $a['detail_address'] ?? '',
                    'district_name' => $a['district_name'] ?? '',
                    'khoroo_number' => $a['khoroo_number'] ?? '',
                    'khoroo_name' => $a['khoroo_name'] ?? '',
                    'is_default' => (int)$a['is_default'],
                ];
            }, $addresses),
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit;
}

// ── AJAX: Fetch order for editing ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'fetch-order') {
    header('Content-Type: application/json');
    $orderId = (int)($_GET['id'] ?? 0);
    if (!$orderId) { echo json_encode(['error' => 'ID шаардлагатай']); exit; }

    $order = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $order = $order->fetch();
    if (!$order) { echo json_encode(['error' => 'Захиалга олдсонгүй']); exit; }

    $items = $db->prepare("
        SELECT oi.*, p.stock, p.is_active, p.type as current_type
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $items->execute([$orderId]);
    $items = $items->fetchAll();

    echo json_encode([
        'order' => [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'fulfillment' => $order['fulfillment'],
            'district_id' => $order['district_id'] ? (int)$order['district_id'] : null,
            'khoroo_id' => $order['khoroo_id'] ? (int)$order['khoroo_id'] : null,
            'address' => $order['address'] ?? '',
            'detail_address' => $order['detail_address'] ?? '',
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'notes' => $order['notes'] ?? '',
            'status' => $order['status'],
        ],
        'items' => array_map(function($i) {
            return [
                'id' => (int)$i['product_id'],
                'name' => $i['product_name'],
                'price' => (float)$i['product_price'],
                'qty' => (int)$i['quantity'],
                'stock' => (int)($i['stock'] ?? 9999),
                'type' => $i['current_type'] ?? 'preorder',
                'variant_id' => $i['variant_id'] ? (int)$i['variant_id'] : null,
                'variant_label' => $i['variant_label'] ?? null,
            ];
        }, $items),
    ]);
    exit;
}

// ── AJAX: Quick update order (status / payment / cancel) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'quick-update') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']);
        exit;
    }

    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) { echo json_encode(['error' => 'Захиалга олдсонгүй']); exit; }

    $order = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $order = $order->fetch();
    if (!$order) { echo json_encode(['error' => 'Захиалга олдсонгүй']); exit; }

    $updateType = $data['type'] ?? '';
    $allowedStatuses = ['pending','confirmed','cargo_shipping','cargo_arrived','ready_pickup','delivering','delivered','picked_up','completed','cancelled'];

    try {
        $db->beginTransaction();

        if ($updateType === 'status') {
            $newStatus = $data['value'] ?? '';
            if (!in_array($newStatus, $allowedStatuses)) throw new Exception('Буруу төлөв');
            $db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
            // Restore stock if cancelled
            if ($newStatus === 'cancelled' && $order['status'] !== 'cancelled') {
                $items = $db->prepare("SELECT oi.product_id, oi.quantity, oi.variant_id FROM order_items oi WHERE oi.order_id = ?");
                $items->execute([$orderId]);
                global $currentAdmin;
                $adminId = $currentAdmin['id'] ?? null;
                foreach ($items->fetchAll() as $item) {
                    adjustStock(
                        $db,
                        (int)$item['product_id'],
                        $item['variant_id'] ? (int)$item['variant_id'] : null,
                        +(int)$item['quantity'],
                        'order_cancel',
                        (int)$orderId,
                        'admin',
                        $adminId,
                        'Order ' . $order['order_number'] . ' cancelled (quick)'
                    );
                }
            }
        } elseif ($updateType === 'payment') {
            $newPayment = $data['value'] ?? '';
            if (!in_array($newPayment, ['pending', 'paid', 'refunded'])) throw new Exception('Буруу төлбөрийн төлөв');
            $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?")->execute([$newPayment, $orderId]);
        } else {
            throw new Exception('Буруу хүсэлт');
        }

        global $currentAdmin;
        auditLog('update', 'order', $orderId, 'admin', $currentAdmin['id'] ?? null, [
            'source' => 'admin_quick_update',
            'type' => $updateType,
            'old_value' => $updateType === 'status' ? $order['status'] : $order['payment_status'],
            'new_value' => $data['value'],
        ]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $ex) {
        $db->rollBack();
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

// ── AJAX: Update existing order (edit items) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update-order') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']);
        exit;
    }

    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) { echo json_encode(['error' => 'Захиалга олдсонгүй']); exit; }

    $order = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $order = $order->fetch();
    if (!$order) { echo json_encode(['error' => 'Захиалга олдсонгүй']); exit; }

    $items = $data['items'] ?? [];
    $fulfillment = $data['fulfillment'] ?? $order['fulfillment'];
    $customerName = trim($data['customer_name'] ?? $order['customer_name']);
    $customerPhone = preg_replace('/[^0-9]/', '', $data['customer_phone'] ?? $order['customer_phone']);
    $districtId = $fulfillment === 'delivery' ? ((int)($data['district_id'] ?? 0) ?: null) : null;
    $khorooId = $fulfillment === 'delivery' ? ((int)($data['khoroo_id'] ?? 0) ?: null) : null;
    $address = $fulfillment === 'delivery' ? trim($data['address'] ?? '') : null;
    $detailAddress = $fulfillment === 'delivery' ? trim($data['detail_address'] ?? '') : null;
    $paymentMethod = $data['payment_method'] ?? $order['payment_method'];
    $paymentStatus = in_array($data['payment_status'] ?? '', ['paid', 'pending']) ? $data['payment_status'] : $order['payment_status'];
    $notes = trim($data['notes'] ?? '');

    $errors = [];
    if ($customerName === '') $errors[] = 'Хэрэглэгчийн нэр шаардлагатай';
    if (strlen($customerPhone) !== 8) $errors[] = 'Утас 8 оронтой байх ёстой';
    if (empty($items)) $errors[] = 'Бараа сонгоно уу';
    if ($fulfillment === 'delivery') {
        if (!$districtId) $errors[] = 'Дүүрэг сонгоно уу';
        if (!$khorooId) $errors[] = 'Хороо сонгоно уу';
        if ($address === '') $errors[] = 'Хаяг оруулна уу';
    }
    if (!empty($errors)) {
        echo json_encode(['error' => implode(', ', $errors)]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Get old order items for stock adjustment
        $oldItems = $db->prepare("SELECT product_id, quantity, variant_id FROM order_items WHERE order_id = ?");
        $oldItems->execute([$orderId]);
        $oldItemsList = $oldItems->fetchAll();

        // Fetch products with lock
        $productIds = array_map(fn($i) => (int)$i['id'], $items);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $lockStmt = $db->prepare("SELECT id, name, price, type, weight_kg, stock, is_active, cargo_batch_id, hide_cargo_fee, has_variants FROM products WHERE id IN ($placeholders) FOR UPDATE");
        $lockStmt->execute($productIds);
        $products = [];
        foreach ($lockStmt->fetchAll() as $p) {
            $products[(int)$p['id']] = $p;
        }

        // Load cargo rates
        $batchRates = [];
        $batchIds = array_unique(array_filter(array_column($products, 'cargo_batch_id')));
        if (!empty($batchIds)) {
            $bPlaceholders = implode(',', array_fill(0, count($batchIds), '?'));
            $bStmt = $db->prepare("SELECT id, cargo_rate_per_kg FROM cargo_batches WHERE id IN ($bPlaceholders)");
            $bStmt->execute(array_values($batchIds));
            foreach ($bStmt->fetchAll() as $b) {
                $batchRates[(int)$b['id']] = (float)$b['cargo_rate_per_kg'];
            }
        }

        $subtotal = 0;
        $totalCargoFee = 0;
        $visibleCargoFee = 0;
        $newOrderItems = [];

        foreach ($items as $item) {
            $pid = (int)$item['id'];
            $qty = (int)($item['qty'] ?? 1);
            $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : null;
            if ($qty <= 0) continue;

            if (!isset($products[$pid])) throw new Exception("Бараа олдсонгүй: ID $pid");
            $p = $products[$pid];
            if (!$p['is_active']) throw new Exception("'{$p['name']}' идэвхгүй байна");

            $itemPrice = (float)$p['price'];
            $variantLabel = null;

            if ($p['has_variants']) {
                if (!$variantId) throw new Exception("'{$p['name']}' барааны өнгө/хэмжээг сонгоно уу");
                $vs = $db->prepare("SELECT pv.*, pc.name_mn as color_name, ps.name as size_name FROM product_variants pv LEFT JOIN product_colors pc ON pv.color_id = pc.id LEFT JOIN product_sizes ps ON pv.size_id = ps.id WHERE pv.id = ? AND pv.product_id = ? FOR UPDATE");
                $vs->execute([$variantId, $pid]);
                $variant = $vs->fetch();
                if (!$variant) throw new Exception("Хувилбар олдсонгүй: ID $variantId");
                if ($variant['price_override']) $itemPrice = (float)$variant['price_override'];
                $variantLabel = trim(($variant['color_name'] ?? '') . ' ' . ($variant['size_name'] ?? ''));
            }

            $subtotal += $itemPrice * $qty;

            $cargoFee = 0;
            $itemBatchId = null;
            if ($p['type'] === 'preorder' && $p['weight_kg'] && $p['cargo_batch_id']) {
                $itemBatchId = (int)$p['cargo_batch_id'];
                $rate = $batchRates[$itemBatchId] ?? 0;
                $cargoFee = round((float)$p['weight_kg'] * $rate * $qty, 2);
                $totalCargoFee += $cargoFee;
                if (empty($p['hide_cargo_fee'])) {
                    $visibleCargoFee += $cargoFee;
                }
            }

            $newOrderItems[] = [
                'product_id' => $pid,
                'product_name' => $p['name'],
                'product_price' => $itemPrice,
                'quantity' => $qty,
                'weight_kg' => $p['weight_kg'],
                'cargo_fee' => $cargoFee,
                'cargo_batch_id' => $itemBatchId,
                'hide_cargo_fee' => !empty($p['hide_cargo_fee']) ? 1 : 0,
                'type' => $p['type'],
                'variant_id' => $variantId,
                'variant_label' => $variantLabel,
            ];
        }

        global $currentAdmin;
        $adminId = $currentAdmin['id'] ?? null;
        $editNote = 'Order ' . $order['order_number'] . ' edited';

        // Restore old quantities first, then deduct new quantities. The ledger
        // helper handles uncapped (skip) and insufficient (throw) for us.
        foreach ($oldItemsList as $oi) {
            adjustStock(
                $db,
                (int)$oi['product_id'],
                $oi['variant_id'] ? (int)$oi['variant_id'] : null,
                +(int)$oi['quantity'],
                'order_edit_restore',
                (int)$orderId,
                'admin',
                $adminId,
                $editNote
            );
        }
        foreach ($newOrderItems as $oi) {
            $r = adjustStock(
                $db,
                (int)$oi['product_id'],
                $oi['variant_id'] ? (int)$oi['variant_id'] : null,
                -(int)$oi['quantity'],
                'order_edit_deduct',
                (int)$orderId,
                'admin',
                $adminId,
                $editNote
            );
            if ($r['status'] === 'insufficient') {
                throw new Exception("'{$oi['product_name']}' нөөц хүрэлцэхгүй. Боломжит: {$r['balance_after']}");
            }
        }

        // Delivery fee
        $deliveryFee = 0;
        if ($fulfillment === 'delivery' && getSetting('delivery_fee_enabled', '1') === '1') {
            $feeAmount = (float)getSetting('delivery_fee', 5000);
            $freeThreshold = (float)getSetting('free_delivery_threshold', 50000);
            if ($subtotal < $freeThreshold) {
                $deliveryFee = $feeAmount;
            }
        }

        $total = $subtotal + $visibleCargoFee + $deliveryFee;

        // Primary cargo batch
        $orderBatchIds = array_unique(array_filter(array_column($newOrderItems, 'cargo_batch_id')));
        $primaryBatchId = !empty($orderBatchIds) ? max($orderBatchIds) : null;

        // Update order
        $stmt = $db->prepare("
            UPDATE orders SET
                fulfillment = ?, customer_name = ?, customer_phone = ?,
                district_id = ?, khoroo_id = ?, address = ?, detail_address = ?,
                subtotal = ?, delivery_fee = ?, cargo_fee = ?, total = ?,
                cargo_batch_id = ?, payment_method = ?, payment_status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $fulfillment,
            htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
            $customerPhone,
            $districtId, $khorooId, $address, $detailAddress,
            $subtotal, $deliveryFee, $totalCargoFee, $total,
            $primaryBatchId, $paymentMethod, $paymentStatus, $notes,
            $orderId,
        ]);

        // Delete old items and insert new
        $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
        $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, weight_kg, cargo_fee, cargo_batch_id, hide_cargo_fee, variant_id, variant_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($newOrderItems as $oi) {
            $itemStmt->execute([$orderId, $oi['product_id'], $oi['product_name'], $oi['product_price'], $oi['quantity'], $oi['weight_kg'], $oi['cargo_fee'], $oi['cargo_batch_id'], $oi['hide_cargo_fee'], $oi['variant_id'], $oi['variant_label']]);
        }

        global $currentAdmin;
        auditLog('update', 'order', $orderId, 'admin', $currentAdmin['id'] ?? null, [
            'source' => 'admin_edit_order',
            'order_number' => $order['order_number'],
            'old_total' => (float)$order['total'],
            'new_total' => $total,
            'items_count' => count($newOrderItems),
        ]);

        $db->commit();
        echo json_encode([
            'success' => true,
            'order_number' => $order['order_number'],
            'order_id' => $orderId,
            'total' => $total,
        ]);
    } catch (Exception $ex) {
        $db->rollBack();
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

// ── AJAX: Submit order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']);
        exit;
    }

    $items = $data['items'] ?? [];
    $fulfillment = $data['fulfillment'] ?? 'delivery';
    $customerPhone = preg_replace('/[^0-9]/', '', $data['customer_phone'] ?? '');
    $customerName = trim($data['customer_name'] ?? '');
    $districtId = $fulfillment === 'delivery' ? ((int)($data['district_id'] ?? 0) ?: null) : null;
    $khorooId = $fulfillment === 'delivery' ? ((int)($data['khoroo_id'] ?? 0) ?: null) : null;
    $address = $fulfillment === 'delivery' ? trim($data['address'] ?? '') : null;
    $detailAddress = $fulfillment === 'delivery' ? trim($data['detail_address'] ?? '') : null;
    $paymentStatus = in_array($data['payment_status'] ?? '', ['paid', 'pending']) ? $data['payment_status'] : 'pending';
    $paymentMethod = $data['payment_method'] ?? 'cash';
    $notes = trim($data['notes'] ?? '');
    $force = !empty($data['force']); // Skip cargo batch warning
    $fromTxId = (int)($data['from_tx'] ?? 0);

    // Validate
    $errors = [];
    if ($customerName === '') $errors[] = 'Хэрэглэгчийн нэр шаардлагатай';
    if (strlen($customerPhone) !== 8) $errors[] = 'Утас 8 оронтой байх ёстой';
    if (empty($items)) $errors[] = 'Бараа сонгоно уу';
    if ($fulfillment === 'delivery') {
        if (!$districtId) $errors[] = 'Дүүрэг сонгоно уу';
        if (!$khorooId) $errors[] = 'Хороо сонгоно уу';
        if ($address === '') $errors[] = 'Хаяг оруулна уу';
    }
    if (!empty($errors)) {
        echo json_encode(['error' => implode(', ', $errors)]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Fetch products with lock
        $productIds = array_map(fn($i) => (int)$i['id'], $items);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $lockStmt = $db->prepare("SELECT id, name, price, type, weight_kg, stock, is_active, cargo_batch_id, hide_cargo_fee, has_variants FROM products WHERE id IN ($placeholders) FOR UPDATE");
        $lockStmt->execute($productIds);
        $products = [];
        foreach ($lockStmt->fetchAll() as $p) {
            $products[(int)$p['id']] = $p;
        }

        // Load cargo rates (from all batches, not just open — admin can create orders for any batch)
        $batchRates = [];
        $batchStatuses = [];
        $batchIds = array_unique(array_filter(array_column($products, 'cargo_batch_id')));
        if (!empty($batchIds)) {
            $bPlaceholders = implode(',', array_fill(0, count($batchIds), '?'));
            $bStmt = $db->prepare("SELECT id, cargo_rate_per_kg, status FROM cargo_batches WHERE id IN ($bPlaceholders)");
            $bStmt->execute(array_values($batchIds));
            foreach ($bStmt->fetchAll() as $b) {
                $batchRates[(int)$b['id']] = (float)$b['cargo_rate_per_kg'];
                $batchStatuses[(int)$b['id']] = $b['status'];
            }
        }

        $subtotal = 0;
        $totalCargoFee = 0;
        $visibleCargoFee = 0;
        $orderItems = [];
        $warnings = []; // Cargo batch warnings (non-blocking for admin)

        foreach ($items as $item) {
            $pid = (int)$item['id'];
            $qty = (int)($item['qty'] ?? 1);
            $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : null;
            if ($qty <= 0) continue;

            if (!isset($products[$pid])) throw new Exception("Бараа олдсонгүй: ID $pid");
            $p = $products[$pid];
            if (!$p['is_active']) throw new Exception("'{$p['name']}' идэвхгүй байна");

            $itemPrice = (float)$p['price'];
            $variantLabel = null;

            if ($p['has_variants']) {
                if (!$variantId) throw new Exception("'{$p['name']}' барааны өнгө/хэмжээг сонгоно уу");
                $vs = $db->prepare("SELECT pv.*, pc.name_mn as color_name, ps.name as size_name FROM product_variants pv LEFT JOIN product_colors pc ON pv.color_id = pc.id LEFT JOIN product_sizes ps ON pv.size_id = ps.id WHERE pv.id = ? AND pv.product_id = ? FOR UPDATE");
                $vs->execute([$variantId, $pid]);
                $variant = $vs->fetch();
                if (!$variant) throw new Exception("Хувилбар олдсонгүй: ID $variantId");
                // Capacity check: ready always has a cap; preorder has a cap when stock is non-NULL.
                if ($variant['stock'] !== null && (int)$variant['stock'] < $qty) {
                    throw new Exception("'{$p['name']}' хувилбарын нөөц хүрэлцэхгүй. Боломжит: {$variant['stock']}");
                }
                if ($variant['price_override']) $itemPrice = (float)$variant['price_override'];
                $variantLabel = trim(($variant['color_name'] ?? '') . ' ' . ($variant['size_name'] ?? ''));
            } elseif ($p['stock'] !== null && (int)$p['stock'] < $qty) {
                throw new Exception("'{$p['name']}' нөөц хүрэлцэхгүй. Боломжит: {$p['stock']}");
            }

            if ($p['type'] === 'preorder') {
                $pBatchId = $p['cargo_batch_id'] ? (int)$p['cargo_batch_id'] : null;
                if (!$pBatchId || !isset($batchRates[$pBatchId])) {
                    $warnings[] = "'{$p['name']}' ачааны багцгүй — ачааны хураамж ₮0";
                } elseif (($batchStatuses[$pBatchId] ?? '') !== 'open') {
                    $warnings[] = "'{$p['name']}' ачааны багц нээлттэй бус ({$batchStatuses[$pBatchId]}) — хураамж төлөгдөөгүй";
                }
            }

            $itemPrice = (float)$p['price'];
            $subtotal += $itemPrice * $qty;

            $cargoFee = 0;
            $itemBatchId = null;
            if ($p['type'] === 'preorder' && $p['weight_kg'] && $p['cargo_batch_id']) {
                $itemBatchId = (int)$p['cargo_batch_id'];
                $rate = $batchRates[$itemBatchId] ?? 0;
                $cargoFee = round((float)$p['weight_kg'] * $rate * $qty, 2);
                $totalCargoFee += $cargoFee;
                if (empty($p['hide_cargo_fee'])) {
                    $visibleCargoFee += $cargoFee;
                }
            }

            $orderItems[] = [
                'product_id' => $pid,
                'product_name' => $p['name'],
                'product_price' => $itemPrice,
                'quantity' => $qty,
                'weight_kg' => $p['weight_kg'],
                'cargo_fee' => $cargoFee,
                'cargo_batch_id' => $itemBatchId,
                'hide_cargo_fee' => !empty($p['hide_cargo_fee']) ? 1 : 0,
                'type' => $p['type'],
                'variant_id' => $variantId,
                'variant_label' => $variantLabel,
            ];
        }

        // If there are cargo warnings and admin hasn't confirmed, ask for confirmation
        if (!empty($warnings) && !$force) {
            $db->rollBack();
            echo json_encode(['confirm' => true, 'warnings' => $warnings]);
            exit;
        }

        // (Stock deduction is deferred to after order_items insert so the ledger
        // entries can reference the new order_id.)

        // Delivery fee
        $deliveryFee = 0;
        if ($fulfillment === 'delivery' && getSetting('delivery_fee_enabled', '1') === '1') {
            $feeAmount = (float)getSetting('delivery_fee', 5000);
            $freeThreshold = (float)getSetting('free_delivery_threshold', 50000);
            if ($subtotal < $freeThreshold) {
                $deliveryFee = $feeAmount;
            }
        }

        $total = $subtotal + $visibleCargoFee + $deliveryFee;

        $orderNumber = generateOrderNumber($db);

        // Determine primary cargo batch
        $orderBatchIds = array_unique(array_filter(array_column($orderItems, 'cargo_batch_id')));
        $primaryBatchId = !empty($orderBatchIds) ? max($orderBatchIds) : null;

        // Link to customer if exists
        $customerId = null;
        $custStmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
        $custStmt->execute([$customerPhone]);
        $existingCustomer = $custStmt->fetch();
        if ($existingCustomer) {
            $customerId = (int)$existingCustomer['id'];
        }

        $status = $paymentStatus === 'paid' ? 'confirmed' : 'pending';

        $stmt = $db->prepare("
            INSERT INTO orders (order_number, order_type, fulfillment, status,
                customer_id, customer_name, customer_phone,
                district_id, khoroo_id, address, detail_address,
                subtotal, delivery_fee, cargo_fee, total,
                cargo_batch_id, payment_method, payment_status, notes, confirmed_at)
            VALUES (?, 'online', ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderNumber, $fulfillment, $status,
            $customerId,
            htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
            $customerPhone,
            $districtId, $khorooId, $address, $detailAddress,
            $subtotal, $deliveryFee, $totalCargoFee, $total,
            $primaryBatchId, $paymentMethod, $paymentStatus, $notes,
            $status === 'pending' ? null : date('Y-m-d H:i:s'),
        ]);
        $orderId = $db->lastInsertId();

        // Insert order items
        $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, weight_kg, cargo_fee, cargo_batch_id, hide_cargo_fee, variant_id, variant_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($orderItems as $oi) {
            $itemStmt->execute([$orderId, $oi['product_id'], $oi['product_name'], $oi['product_price'], $oi['quantity'], $oi['weight_kg'], $oi['cargo_fee'], $oi['cargo_batch_id'], $oi['hide_cargo_fee'], $oi['variant_id'], $oi['variant_label']]);
        }

        global $currentAdmin;
        $adminId = $currentAdmin['id'] ?? null;
        // Deduct stock via the ledger now that we have the order_id
        foreach ($orderItems as $oi) {
            $r = adjustStock(
                $db,
                (int)$oi['product_id'],
                $oi['variant_id'] ? (int)$oi['variant_id'] : null,
                -(int)$oi['quantity'],
                'order_sale',
                (int)$orderId,
                'admin',
                $adminId,
                'Order ' . $orderNumber
            );
            if ($r['status'] === 'insufficient') {
                throw new Exception("'{$oi['product_name']}' нөөц хүрэлцэхгүй. Боломжит: {$r['balance_after']}");
            }
        }

        // Audit log
        auditLog('create', 'order', $orderId, 'admin', $adminId, [
            'order_number' => $orderNumber,
            'source' => 'admin_create',
            'customer_phone' => $customerPhone,
            'total' => $total,
            'items_count' => count($orderItems),
        ]);

        // Link bank transaction if order was created from one
        $linkedTx = null;
        if ($fromTxId > 0) {
            $txStmt = $db->prepare("SELECT id, credit_amount FROM bank_transactions WHERE id = ? AND order_id IS NULL");
            $txStmt->execute([$fromTxId]);
            if ($tx = $txStmt->fetch()) {
                $matchStatus = abs((float)$tx['credit_amount'] - (float)$total) < 1 ? 'matched' : 'amount_mismatch';
                $db->prepare("UPDATE bank_transactions SET order_id = ?, order_number = ?, order_total = ?, expected_amount = ?, match_status = ?, match_method = 'manual_create' WHERE id = ?")
                   ->execute([$orderId, $orderNumber, $total, $total, $matchStatus, $fromTxId]);
                $linkedTx = ['tx_id' => $fromTxId, 'match_status' => $matchStatus];
            }
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'order_number' => $orderNumber,
            'order_id' => $orderId,
            'total' => $total,
            'linked_tx' => $linkedTx,
        ]);
    } catch (Exception $ex) {
        $db->rollBack();
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

// ── Page Data ──
$products = $db->query("SELECT p.*, p.has_variants, s.name as shop_name, c.name as category_name
    FROM products p
    LEFT JOIN shops s ON p.shop_id = s.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 AND (p.type = 'ready' AND (p.stock > 0 OR p.has_variants = 1) OR p.type = 'preorder')
    ORDER BY p.name ASC")->fetchAll();

// Load variants for variant products
$variantProductIds = array_filter(array_column($products, 'id'), function($id) use ($products) {
    foreach ($products as $p) { if ($p['id'] == $id && $p['has_variants']) return true; }
    return false;
});
$ocVariantsMap = [];
if (!empty($variantProductIds)) {
    $placeholders = implode(',', array_fill(0, count($variantProductIds), '?'));
    $vStmt = $db->prepare("SELECT pv.*, pc.name_mn as color_name, pc.hex_code, ps.name as size_name
        FROM product_variants pv
        LEFT JOIN product_colors pc ON pv.color_id = pc.id
        LEFT JOIN product_sizes ps ON pv.size_id = ps.id
        WHERE pv.product_id IN ($placeholders) AND pv.is_active = 1
        ORDER BY pv.product_id, pc.sort_order, ps.sort_order");
    $vStmt->execute(array_values($variantProductIds));
    foreach ($vStmt->fetchAll() as $v) {
        $ocVariantsMap[(int)$v['product_id']][] = [
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

$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$districts = $db->query("SELECT id, name, name_mn FROM districts WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$khoroos = $db->query("SELECT id, district_id, number, name FROM khoroos ORDER BY district_id, number")->fetchAll();
$khorooMap = [];
foreach ($khoroos as $k) {
    $khorooMap[(int)$k['district_id']][] = [
        'id' => (int)$k['id'],
        'number' => (int)$k['number'],
        'name' => $k['name'] ?? '',
    ];
}

$productsJson = json_encode(array_map(function($p) use ($ocVariantsMap) {
    $variants = $ocVariantsMap[(int)$p['id']] ?? [];
    $totalVariantStock = array_sum(array_column($variants, 'stock'));
    return [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'name_mn' => $p['name_mn'],
        'price' => (float)$p['price'],
        'stock' => $p['has_variants'] ? $totalVariantStock : (int)$p['stock'],
        'type' => $p['type'],
        'category' => $p['category_name'] ?? '',
        'shop' => $p['shop_name'] ?? '',
        'barcode' => $p['barcode'] ?? '',
        'has_variants' => (bool)$p['has_variants'],
        'variants' => $variants,
    ];
}, $products), JSON_HEX_APOS | JSON_HEX_TAG);

$districtsJson = json_encode(array_map(function($d) use ($khorooMap) {
    return [
        'id' => (int)$d['id'],
        'name' => $d['name'],
        'name_mn' => $d['name_mn'],
        'khoroos' => $khorooMap[(int)$d['id']] ?? [],
    ];
}, $districts), JSON_HEX_APOS | JSON_HEX_TAG);

// Recent orders created by this admin (from audit log)
$recentOrders = $db->prepare("
    SELECT o.id, o.order_number, o.status, o.customer_name, o.customer_phone,
           o.total, o.payment_status, o.fulfillment, o.created_at,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
    FROM orders o
    JOIN audit_log al ON al.entity_type = 'order' AND al.entity_id = o.id
        AND al.action = 'create' AND al.actor_type = 'admin' AND al.actor_id = ?
    WHERE al.details LIKE '%admin_create%'
    ORDER BY o.created_at DESC
    LIMIT 20
");
$recentOrders->execute([$currentAdmin['id']]);
$recentOrders = $recentOrders->fetchAll();

// Prefill from bank transaction (when admin clicks "Захиалга үүсгэх" on an unmatched tx)
$prefillFromTx = null;
$fromTxId = (int)($_GET['from_tx'] ?? 0);
if ($fromTxId > 0) {
    $txStmt = $db->prepare("SELECT id, import_id, transaction_date, credit_amount, description FROM bank_transactions WHERE id = ?");
    $txStmt->execute([$fromTxId]);
    if ($tx = $txStmt->fetch()) {
        $phone = '';
        if (preg_match('/\b(\d{8})\b/', $tx['description'], $pm)) $phone = $pm[1];
        $prefillFromTx = [
            'tx_id' => (int)$tx['id'],
            'import_id' => (int)$tx['import_id'],
            'phone' => $phone,
            'amount' => (float)$tx['credit_amount'],
            'description' => $tx['description'],
            'date' => $tx['transaction_date'],
            'note' => 'Банкны гүйлгээ ' . substr($tx['transaction_date'], 0, 16) . ' / ' . number_format((float)$tx['credit_amount'], 0) . '₮',
        ];
    }
}
$prefillFromTxJson = json_encode($prefillFromTx, JSON_HEX_APOS | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

require_once __DIR__ . '/../includes/header.php';
?>

<div x-data="orderCreateApp()" x-cloak @edit-order.window="loadOrderForEdit($event.detail.orderId)">
    <template x-if="prefillFromTx">
        <div class="mb-4 p-4 bg-pink-50 border border-pink-200 rounded-xl flex items-start gap-3">
            <svg class="w-5 h-5 text-pink-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <div class="flex-1 text-sm">
                <p class="font-semibold text-pink-800">Банкны гүйлгээнээс захиалга үүсгэж байна</p>
                <p class="text-pink-700 mt-0.5">
                    <span x-text="prefillFromTx.date?.substring(0,16)"></span> ·
                    <span class="font-mono font-bold" x-text="formatP(prefillFromTx.amount)"></span> ·
                    <span class="text-pink-600 italic" x-text="prefillFromTx.description"></span>
                </p>
                <p class="text-xs text-pink-600 mt-1">Захиалгыг хадгалсны дараа гүйлгээ автоматаар тохирно.</p>
            </div>
        </div>
    </template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ═══ LEFT: Customer + Products ═══ -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Customer Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Хэрэглэгч
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Утас *</label>
                        <div class="relative">
                            <input type="text" x-model="customer.phone" maxlength="8" inputmode="numeric"
                                   @input="onPhoneInput()"
                                   placeholder="9999xxxx"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none pr-20">
                            <span x-show="customerLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Хайж байна...</span>
                            <span x-show="customerFound && !customerLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-green-600 font-medium">✓ Олдсон</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Нэр *</label>
                        <input type="text" x-model="customer.name" placeholder="Хэрэглэгчийн нэр"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <!-- Saved Addresses -->
                <template x-if="savedAddresses.length > 0">
                    <div class="mt-4">
                        <label class="block text-xs font-medium text-gray-500 mb-2">Хадгалсан хаягууд</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="addr in savedAddresses" :key="addr.id">
                                <button @click="selectSavedAddress(addr)" type="button"
                                        :class="selectedAddressId === addr.id ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300'"
                                        class="text-left px-3 py-2 border rounded-lg text-xs transition-colors">
                                    <span class="font-medium text-gray-900" x-text="addr.district_name"></span>
                                    <span class="text-gray-500" x-text="addr.khoroo_name || (addr.khoroo_number ? addr.khoroo_number + '-р хороо' : '')"></span>
                                    <template x-if="addr.is_default">
                                        <span class="ml-1 text-blue-600">⭐</span>
                                    </template>
                                    <div class="text-gray-400 mt-0.5 truncate max-w-[200px]" x-text="addr.address"></div>
                                </button>
                            </template>
                            <button @click="clearSavedAddress()" type="button" x-show="selectedAddressId"
                                    class="px-3 py-2 border border-gray-200 rounded-lg text-xs text-gray-500 hover:bg-gray-50">
                                ✕ Шинэ хаяг
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Product Search & Cart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    Бараа сонгох
                </h3>
                <div class="flex gap-2 mb-4">
                    <div class="flex-1 relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="productSearch" x-ref="productInput" placeholder="Бараа хайх (нэр, баркод)..."
                               @focus="showProductDropdown = true" @input="showProductDropdown = true; dropdownIdx = 0"
                               @keydown.arrow-down.prevent="dropdownIdx = Math.min(dropdownIdx + 1, filteredProducts.length - 1)"
                               @keydown.arrow-up.prevent="dropdownIdx = Math.max(dropdownIdx - 1, 0)"
                               @keydown.enter.prevent="if(filteredProducts[dropdownIdx]) { addToCart(filteredProducts[dropdownIdx]); productSearch = ''; showProductDropdown = false; }"
                               @keydown.escape="showProductDropdown = false"
                               class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <div x-show="showProductDropdown && productSearch.length > 0 && filteredProducts.length > 0"
                             @click.outside="showProductDropdown = false" x-cloak
                             class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 shadow-xl rounded-lg max-h-64 overflow-y-auto z-50">
                            <template x-for="(p, idx) in filteredProducts" :key="p.id">
                                <button @click="addToCart(p); productSearch = ''; showProductDropdown = false"
                                        class="w-full text-left px-3 py-2.5 flex items-center justify-between hover:bg-blue-50 transition-colors border-b border-gray-50 last:border-0"
                                        :class="dropdownIdx === idx ? 'bg-blue-50' : ''">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900" x-text="p.name"></span>
                                        <span class="text-xs text-gray-400 ml-1" x-text="p.shop"></span>
                                        <span x-show="p.type === 'preorder'" class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-purple-100 text-purple-700">Урьдчилсан</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-sm font-bold text-blue-600" x-text="formatP(p.price)"></span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full"
                                              :class="p.type === 'preorder' ? 'bg-purple-100 text-purple-600' : (p.stock <= 5 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500')"
                                              x-text="p.type === 'preorder' ? 'Захиал' : p.stock + ' ш'"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                    <select x-model="categoryFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none">
                        <option value="">Бүгд</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['name']) ?>"><?= e($c['icon'] . ' ' . $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cart Items -->
                <template x-if="cart.length === 0">
                    <div class="text-center py-10 text-gray-400 text-sm">Бараа нэмнэ үү</div>
                </template>
                <div class="space-y-2">
                    <template x-for="(item, i) in cart" :key="item.id + '-' + (item.variant_id||0)">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="item.name"></p>
                                <p class="text-xs text-gray-400">
                                    <span x-text="formatP(item.price)"></span>
                                    <template x-if="item.variant_label">
                                        <span class="ml-1 text-purple-600 font-medium" x-text="item.variant_label"></span>
                                    </template>
                                    <span x-show="item.type === 'preorder'" class="ml-1 text-purple-600">Урьдчилсан</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button @click="updateQty(i, -1)" class="w-7 h-7 rounded-lg bg-white border border-gray-300 flex items-center justify-center text-gray-500 hover:bg-gray-100">−</button>
                                <input type="number" :value="item.qty" @change="setQty(i, $event.target.value)" min="1"
                                       :max="item.type === 'preorder' ? 9999 : item.stock"
                                       class="w-12 text-center border border-gray-300 rounded-lg text-sm py-1 outline-none">
                                <button @click="updateQty(i, 1)" class="w-7 h-7 rounded-lg bg-white border border-gray-300 flex items-center justify-center text-gray-500 hover:bg-gray-100">+</button>
                            </div>
                            <span class="text-sm font-bold text-gray-900 w-24 text-right" x-text="formatP(item.price * item.qty)"></span>
                            <button @click="removeFromCart(i)" class="text-gray-400 hover:text-red-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ═══ RIGHT: Delivery + Summary ═══ -->
        <div class="space-y-4">

            <!-- Fulfillment & Address -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-4">Хүргэлт</h3>
                <div class="flex gap-2 mb-4">
                    <button @click="fulfillment = 'delivery'" type="button"
                            :class="fulfillment === 'delivery' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors">
                        🚚 Хүргэлт
                    </button>
                    <button @click="fulfillment = 'pickup'" type="button"
                            :class="fulfillment === 'pickup' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors">
                        🏪 Очиж авах
                    </button>
                </div>

                <template x-if="fulfillment === 'delivery'">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Дүүрэг / Аймаг *</label>
                            <select x-model="delivery.district_id" @change="delivery.khoroo_id = ''; updateKhoroos()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Сонгох...</option>
                                <template x-for="d in districts" :key="d.id">
                                    <option :value="d.id" x-text="d.name_mn"></option>
                                </template>
                            </select>
                        </div>
                        <div x-show="currentKhoroos.length > 0">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Хороо / Сум *</label>
                            <select x-model="delivery.khoroo_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Сонгох...</option>
                                <template x-for="k in currentKhoroos" :key="k.id">
                                    <option :value="k.id" x-text="k.name || (k.number + '-р хороо')"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Хаяг *</label>
                            <input type="text" x-model="delivery.address" placeholder="Дэлгэрэнгүй хаяг"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Нэмэлт хаяг</label>
                            <input type="text" x-model="delivery.detail_address" placeholder="Орц, давхар, тоот..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </template>
            </div>

            <!-- Payment & Notes -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-4">Төлбөр</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Төлбөрийн арга</label>
                        <select x-model="paymentMethod" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="cash">Бэлэн мөнгө</option>
                            <option value="qpay">QPay</option>
                            <option value="card">Карт</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Төлбөрийн төлөв</label>
                        <div class="flex gap-2">
                            <button @click="paymentStatus = 'pending'" type="button"
                                    :class="paymentStatus === 'pending' ? 'bg-yellow-100 text-yellow-800 ring-2 ring-yellow-300' : 'bg-gray-100 text-gray-600'"
                                    class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors">
                                Хүлээгдэж буй
                            </button>
                            <button @click="paymentStatus = 'paid'" type="button"
                                    :class="paymentStatus === 'paid' ? 'bg-green-100 text-green-800 ring-2 ring-green-300' : 'bg-gray-100 text-gray-600'"
                                    class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors">
                                Төлсөн
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Тэмдэглэл</label>
                        <textarea x-model="notes" rows="2" placeholder="Нэмэлт тэмдэглэл..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Нэгтгэл</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Дүн (<span x-text="cart.length"></span> бараа)</span>
                        <span class="font-medium" x-text="formatP(subtotal)"></span>
                    </div>
                    <template x-if="fulfillment === 'delivery'">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Хүргэлт</span>
                            <?php if (getSetting('delivery_fee_enabled', '1') !== '1'): ?>
                            <span class="text-amber-600 text-sm">Хүргэлтийн үед бодогдоно</span>
                            <?php else: ?>
                            <span class="font-medium" x-text="deliveryFee > 0 ? formatP(deliveryFee) : 'Үнэгүй'"></span>
                            <?php endif; ?>
                        </div>
                    </template>
                    <template x-if="totalCargoFee > 0">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ачааны хураамж</span>
                            <span class="font-medium" x-text="formatP(totalCargoFee)"></span>
                        </div>
                    </template>
                    <div class="pt-2 border-t border-gray-200 flex justify-between">
                        <span class="font-bold text-gray-900">Нийт</span>
                        <span class="font-bold text-xl text-blue-600" x-text="formatP(grandTotal)"></span>
                    </div>
                </div>

                <!-- Edit mode banner -->
                <div x-show="editingOrderId" x-cloak class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-amber-800 font-medium text-sm">✏️ Засварлаж байна:</span>
                            <span class="text-amber-700 text-sm font-bold" x-text="editingOrderNumber"></span>
                        </div>
                        <button @click="cancelEdit()" class="text-xs text-amber-600 hover:text-amber-800 font-medium">✕ Болих</button>
                    </div>
                </div>

                <!-- Submit -->
                <button @click="submitOrder()" :disabled="submitting || cart.length === 0"
                        class="w-full mt-4 py-3 rounded-xl text-white font-bold text-sm transition-colors disabled:opacity-50"
                        :class="submitting ? 'bg-gray-400' : (editingOrderId ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700')">
                    <span x-show="!submitting && !editingOrderId">Захиалга үүсгэх</span>
                    <span x-show="!submitting && editingOrderId">Захиалга шинэчлэх</span>
                    <span x-show="submitting">Боловсруулж байна...</span>
                </button>
                <button x-show="editingOrderId" @click="cancelEdit()" x-cloak
                        class="w-full mt-2 py-2 rounded-xl text-gray-600 font-medium text-sm bg-gray-100 hover:bg-gray-200 transition-colors">
                    Болих — Шинэ захиалга руу буцах
                </button>

                <!-- Error -->
                <div x-show="errorMsg" x-cloak class="mt-3 p-3 bg-red-50 text-red-700 rounded-lg text-sm" x-text="errorMsg"></div>

                <!-- Success -->
                <template x-if="successOrder">
                    <div class="mt-3 p-4 bg-green-50 border border-green-200 rounded-lg text-center">
                        <p class="text-green-700 font-bold text-lg" x-text="'✓ ' + successOrder.order_number"></p>
                        <p class="text-green-600 text-sm mt-1" x-text="editingOrderId ? 'Захиалга амжилттай шинэчлэгдлээ' : 'Захиалга амжилттай үүслээ'"></p>
                        <template x-if="successOrder.linked_tx">
                            <p class="text-xs mt-1" :class="successOrder.linked_tx.match_status === 'matched' ? 'text-green-700' : 'text-yellow-700'">
                                <span x-show="successOrder.linked_tx.match_status === 'matched'">✓ Банкны гүйлгээтэй холбогдлоо</span>
                                <span x-show="successOrder.linked_tx.match_status === 'amount_mismatch'">⚠ Гүйлгээтэй холбогдсон ч дүн зөрүүтэй</span>
                            </p>
                        </template>
                        <div class="flex gap-2 mt-3">
                            <a :href="'index.php?page=order-detail&id=' + successOrder.order_id + '&return=' + encodeURIComponent('index.php?page=order-create')"
                               class="flex-1 px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium text-center hover:bg-blue-700">Харах</a>
                            <template x-if="prefillFromTx">
                                <a :href="'index.php?page=reconciliation' + (prefillFromTx.import_id ? '&import_id=' + prefillFromTx.import_id : '')" class="flex-1 px-3 py-2 bg-pink-600 text-white rounded-lg text-sm font-medium text-center hover:bg-pink-700">Тохиролт руу буцах</a>
                            </template>
                            <template x-if="!prefillFromTx">
                                <button @click="resetForm()" class="flex-1 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Шинэ захиалга</button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Variant Picker Modal -->
    <div x-show="showVariantPicker" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeVariantPicker()">
        <div class="bg-white rounded-2xl p-5 max-w-md w-full" @click.outside="closeVariantPicker()">
            <h3 class="text-base font-bold text-gray-900 mb-1">Хувилбар сонгох</h3>
            <p class="text-sm text-gray-500 mb-3" x-text="variantPickerProduct?.name"></p>

            <!-- Step 1: Color -->
            <template x-if="variantColors.length > 0">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Өнгө <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="c in variantColors" :key="c.name || 'none'">
                            <button type="button" @click="selectedColor = c.name; selectedSize = null"
                                    :class="selectedColor === c.name ? 'border-purple-500 bg-purple-50 ring-2 ring-purple-200' : 'border-gray-200 hover:border-purple-300'"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg border transition-colors text-sm">
                                <span x-show="c.hex" class="w-4 h-4 rounded-full border border-gray-300" :style="'background:' + (c.hex||'#ccc')"></span>
                                <span class="font-medium text-gray-800" x-text="c.name || '—'"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Step 2: Size -->
            <template x-if="variantSizes.length > 0">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Хэмжээ <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="s in availableSizes" :key="s.name || 'none'">
                            <button type="button" @click="selectedSize = s.name"
                                    :disabled="!s.available"
                                    :class="selectedSize === s.name ? 'border-purple-500 bg-purple-50 ring-2 ring-purple-200' : (s.available ? 'border-gray-200 hover:border-purple-300' : 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed')"
                                    class="px-3 py-2 rounded-lg border text-sm font-medium">
                                <span x-text="s.name || '—'"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Selected variant info -->
            <template x-if="pickedVariant">
                <div class="mb-3 p-3 bg-purple-50 border border-purple-200 rounded-lg flex items-center justify-between text-sm">
                    <span class="text-purple-700 font-medium" x-text="pickedVariant.label || 'Сонгогдсон'"></span>
                    <div class="flex items-center gap-2">
                        <span x-show="pickedVariant.price_override" class="font-semibold text-blue-600" x-text="formatP(pickedVariant.price_override)"></span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-white text-gray-600 border border-gray-200" x-text="pickedVariant.stock + ' ш'"></span>
                    </div>
                </div>
            </template>

            <div class="flex gap-2 mt-3">
                <button @click="closeVariantPicker()" type="button" class="flex-1 py-2 rounded-lg text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200">Болих</button>
                <button @click="confirmVariant()" type="button" :disabled="!pickedVariant"
                        :class="pickedVariant ? 'bg-purple-600 text-white hover:bg-purple-700' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors">Нэмэх</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Recent Orders Created by Admin ═══ -->
<?php if (!empty($recentOrders)): ?>
<div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100" x-data="recentOrdersApp()">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Миний үүсгэсэн захиалгууд
        </h3>
        <a href="index.php?page=orders" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Бүгдийг харах →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <th class="px-4 py-3 text-left font-medium">Дугаар</th>
                    <th class="px-4 py-3 text-left font-medium">Хэрэглэгч</th>
                    <th class="px-4 py-3 text-center font-medium">Бараа</th>
                    <th class="px-4 py-3 text-right font-medium">Дүн</th>
                    <th class="px-4 py-3 text-center font-medium">Төлөв</th>
                    <th class="px-4 py-3 text-center font-medium">Төлбөр</th>
                    <th class="px-4 py-3 text-right font-medium">Огноо</th>
                    <th class="px-4 py-3 text-center font-medium">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($recentOrders as $idx => $o): ?>
                <tr class="hover:bg-gray-50 transition-colors" :class="orders[<?= $idx ?>].status === 'cancelled' ? 'opacity-50' : ''">
                    <td class="px-4 py-3">
                        <a href="index.php?page=order-detail&id=<?= (int)$o['id'] ?>&return=<?= urlencode('index.php?page=order-create') ?>" class="font-medium text-blue-600 hover:text-blue-700">
                            <?= e($o['order_number']) ?>
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900"><?= e($o['customer_name']) ?></div>
                        <div class="text-xs text-gray-400"><?= e($o['customer_phone']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                            <?= (int)$o['item_count'] ?> ш
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900">
                        <?= number_format((float)$o['total']) ?>₮
                    </td>
                    <td class="px-4 py-3 text-center">
                        <select @change="updateOrder(<?= $idx ?>, 'status', $event.target.value)"
                                x-model="orders[<?= $idx ?>].status"
                                :disabled="orders[<?= $idx ?>].loading"
                                class="text-xs border border-gray-200 rounded-lg px-2 py-1 outline-none focus:ring-2 focus:ring-blue-500 bg-white cursor-pointer disabled:opacity-50">
                            <option value="pending">Хүлээгдэж буй</option>
                            <option value="confirmed">Баталгаажсан</option>
                            <option value="cargo_shipping">Ачаа тээвэрлэж</option>
                            <option value="cargo_arrived">Ачаа ирсэн</option>
                            <option value="ready_pickup">Бэлэн</option>
                            <option value="delivering">Хүргэж буй</option>
                            <option value="delivered">Хүргэгдсэн</option>
                            <option value="picked_up">Очиж авсан</option>
                            <option value="completed">Дууссан</option>
                            <option value="cancelled">Цуцлагдсан</option>
                        </select>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button @click="togglePayment(<?= $idx ?>)"
                                :disabled="orders[<?= $idx ?>].loading"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer disabled:opacity-50"
                                :class="orders[<?= $idx ?>].payment === 'paid' ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'"
                                x-text="orders[<?= $idx ?>].payment === 'paid' ? 'Төлсөн ✓' : 'Хүлээгдэж'">
                        </button>
                    </td>
                    <td class="px-4 py-3 text-right text-gray-500 text-xs">
                        <?= date('m/d H:i', strtotime($o['created_at'])) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <button @click="$dispatch('edit-order', { orderId: <?= (int)$o['id'] ?> })"
                                    x-show="orders[<?= $idx ?>].status !== 'cancelled'"
                                    class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Засах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <a href="index.php?page=order-detail&id=<?= (int)$o['id'] ?>&return=<?= urlencode('index.php?page=order-create') ?>"
                               class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Дэлгэрэнгүй">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <button @click="cancelOrder(<?= $idx ?>)"
                                    x-show="orders[<?= $idx ?>].status !== 'cancelled'"
                                    :disabled="orders[<?= $idx ?>].loading"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50" title="Цуцлах">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div x-show="orders[<?= $idx ?>].msg" x-cloak
                             class="absolute mt-1 text-[10px] whitespace-nowrap"
                             :class="orders[<?= $idx ?>].msgType === 'error' ? 'text-red-500' : 'text-green-500'"
                             x-text="orders[<?= $idx ?>].msg"
                             x-init="$watch('orders[<?= $idx ?>].msg', v => { if(v) setTimeout(() => orders[<?= $idx ?>].msg = '', 2000) })">
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function orderCreateApp() {
    return {
        // Data
        allProducts: <?= $productsJson ?>,
        districts: <?= $districtsJson ?>,
        csrfToken: '<?= generateCSRFToken() ?>',
        prefillFromTx: <?= $prefillFromTxJson ?: 'null' ?>,

        init() {
            if (this.prefillFromTx) {
                if (this.prefillFromTx.phone) {
                    this.customer.phone = this.prefillFromTx.phone;
                    this.lookupCustomer(this.prefillFromTx.phone);
                }
                this.paymentMethod = 'transfer';
                this.paymentStatus = 'paid';
                this.notes = this.prefillFromTx.note || '';
                // Layer 1: seed product search with the tx description so admin sees matches instantly
                if (this.prefillFromTx.description) {
                    this.productSearch = this.prefillFromTx.description;
                    this.showProductDropdown = true;
                }
            }
        },

        // Customer
        customer: { phone: '', name: '' },
        customerId: null,
        customerFound: false,
        customerLoading: false,
        savedAddresses: [],
        selectedAddressId: null,
        phoneLookupTimer: null,

        // Cart
        cart: [],
        productSearch: '',
        showProductDropdown: false,
        dropdownIdx: 0,
        categoryFilter: '',

        // Delivery
        fulfillment: 'delivery',
        delivery: { district_id: '', khoroo_id: '', address: '', detail_address: '' },
        currentKhoroos: [],

        // Payment
        paymentMethod: 'cash',
        paymentStatus: 'pending',
        notes: '',

        // UI State
        submitting: false,
        errorMsg: '',
        successOrder: null,

        // Edit mode
        editingOrderId: null,
        editingOrderNumber: '',

        // ── Computed ──
        get filteredProducts() {
            let q = this.productSearch.toLowerCase().trim();
            let list = this.allProducts;
            if (this.categoryFilter) {
                list = list.filter(p => p.category === this.categoryFilter);
            }
            if (q) {
                list = list.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    (p.name_mn && p.name_mn.toLowerCase().includes(q)) ||
                    (p.barcode && p.barcode.includes(q))
                );
            }
            return list.slice(0, 20);
        },

        get subtotal() {
            return this.cart.reduce((s, item) => s + item.price * item.qty, 0);
        },

        get deliveryFee() {
            // Calculated server-side, show estimate
            return 0; // Will be in final total from server
        },

        get totalCargoFee() {
            return 0; // Calculated server-side
        },

        get grandTotal() {
            return this.subtotal;
        },

        // ── Customer phone lookup ──
        onPhoneInput() {
            clearTimeout(this.phoneLookupTimer);
            this.customerFound = false;
            this.savedAddresses = [];
            this.selectedAddressId = null;
            this.customerId = null;

            let phone = this.customer.phone.replace(/\D/g, '');
            if (phone.length === 8) {
                this.customerLoading = true;
                this.phoneLookupTimer = setTimeout(() => this.lookupCustomer(phone), 300);
            }
        },

        async lookupCustomer(phone) {
            try {
                const res = await fetch(`index.php?page=order-create&action=lookup-customer&phone=${phone}`);
                const data = await res.json();
                this.customerLoading = false;
                if (data.found) {
                    this.customerFound = true;
                    this.customerId = data.customer.id;
                    if (!this.customer.name) {
                        this.customer.name = data.customer.name;
                    }
                    this.savedAddresses = data.addresses;
                    // Auto-select default address
                    const def = data.addresses.find(a => a.is_default);
                    if (def) this.selectSavedAddress(def);
                }
            } catch (e) {
                this.customerLoading = false;
            }
        },

        selectSavedAddress(addr) {
            this.selectedAddressId = addr.id;
            this.delivery.district_id = String(addr.district_id);
            this.updateKhoroos();
            this.$nextTick(() => {
                this.delivery.khoroo_id = String(addr.khoroo_id);
            });
            this.delivery.address = addr.address;
            this.delivery.detail_address = addr.detail_address;
            this.fulfillment = 'delivery';
        },

        clearSavedAddress() {
            this.selectedAddressId = null;
            this.delivery = { district_id: '', khoroo_id: '', address: '', detail_address: '' };
            this.currentKhoroos = [];
        },

        // ── District / Khoroo ──
        updateKhoroos() {
            const d = this.districts.find(d => String(d.id) === String(this.delivery.district_id));
            this.currentKhoroos = d ? d.khoroos : [];
        },

        // ── Cart ──
        showVariantPicker: false,
        variantPickerProduct: null,
        selectedColor: null,
        selectedSize: null,

        get variantColors() {
            const vs = this.variantPickerProduct?.variants || [];
            const seen = new Map();
            for (const v of vs) {
                const key = v.color_name || '';
                if (!seen.has(key) && (v.color_name || v.color_name === '')) {
                    seen.set(key, { name: v.color_name, hex: v.hex_code });
                }
            }
            // Only show color step if any variant actually has a color
            const hasAnyColor = vs.some(v => v.color_name);
            return hasAnyColor ? Array.from(seen.values()) : [];
        },

        get variantSizes() {
            const vs = this.variantPickerProduct?.variants || [];
            return vs.some(v => v.size_name) ? [...new Set(vs.map(v => v.size_name).filter(Boolean))].map(n => ({ name: n })) : [];
        },

        get availableSizes() {
            const vs = this.variantPickerProduct?.variants || [];
            const isPreorder = this.variantPickerProduct?.type === 'preorder';
            return this.variantSizes.map(s => {
                const match = vs.find(v => v.size_name === s.name && (this.variantColors.length === 0 || v.color_name === this.selectedColor));
                return { name: s.name, available: !!match && (isPreorder || match.stock > 0) };
            });
        },

        get pickedVariant() {
            const vs = this.variantPickerProduct?.variants || [];
            const needColor = this.variantColors.length > 0;
            const needSize = this.variantSizes.length > 0;
            if (needColor && !this.selectedColor) return null;
            if (needSize && !this.selectedSize) return null;
            return vs.find(v =>
                (!needColor || v.color_name === this.selectedColor) &&
                (!needSize || v.size_name === this.selectedSize)
            ) || null;
        },

        closeVariantPicker() {
            this.showVariantPicker = false;
            this.variantPickerProduct = null;
            this.selectedColor = null;
            this.selectedSize = null;
        },

        addToCart(product) {
            if (product.has_variants && product.variants && product.variants.length > 0) {
                this.variantPickerProduct = product;
                this.selectedColor = null;
                this.selectedSize = null;
                this.showVariantPicker = true;
                return;
            }
            if (product.has_variants) {
                this.errorMsg = `'${product.name}' барааны хувилбар байхгүй байна.`;
                return;
            }
            const existing = this.cart.find(c => c.id === product.id && !c.variant_id);
            if (existing) {
                if (product.type === 'ready' && existing.qty >= product.stock) return;
                existing.qty++;
            } else {
                this.cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    qty: 1,
                    stock: product.stock,
                    type: product.type,
                    variant_id: null,
                    variant_label: null,
                });
            }
        },

        confirmVariant() {
            const variant = this.pickedVariant;
            const product = this.variantPickerProduct;
            if (!variant || !product) return;
            const price = variant.price_override || product.price;
            const existing = this.cart.find(c => c.id === product.id && c.variant_id === variant.id);
            if (existing) {
                if (product.type === 'ready' && existing.qty >= variant.stock) return;
                existing.qty++;
            } else {
                this.cart.push({
                    id: product.id,
                    name: product.name,
                    price: price,
                    qty: 1,
                    stock: variant.stock,
                    type: product.type,
                    variant_id: variant.id,
                    variant_label: variant.label,
                });
            }
            this.closeVariantPicker();
        },

        updateQty(index, delta) {
            const item = this.cart[index];
            const newQty = item.qty + delta;
            if (newQty < 1) return;
            if (item.type === 'ready' && newQty > item.stock) return;
            item.qty = newQty;
        },

        setQty(index, val) {
            const item = this.cart[index];
            let qty = parseInt(val) || 1;
            if (qty < 1) qty = 1;
            if (item.type === 'ready' && qty > item.stock) qty = item.stock;
            item.qty = qty;
        },

        removeFromCart(index) {
            this.cart.splice(index, 1);
        },

        // ── Submit ──
        async submitOrder(forceSubmit = false) {
            this.errorMsg = '';
            this.successOrder = null;
            this.submitting = true;

            try {
                const payload = {
                    csrf_token: this.csrfToken,
                    customer_name: this.customer.name,
                    customer_phone: this.customer.phone,
                    fulfillment: this.fulfillment,
                    district_id: this.delivery.district_id || null,
                    khoroo_id: this.delivery.khoroo_id || null,
                    address: this.delivery.address,
                    detail_address: this.delivery.detail_address,
                    payment_method: this.paymentMethod,
                    payment_status: this.paymentStatus,
                    notes: this.notes,
                    items: this.cart.map(c => {
                        const o = { id: c.id, qty: c.qty };
                        if (c.variant_id) o.variant_id = c.variant_id;
                        return o;
                    }),
                    force: forceSubmit,
                    from_tx: this.prefillFromTx?.tx_id || null,
                };

                let url = 'index.php?page=order-create';
                if (this.editingOrderId) {
                    url = 'index.php?page=order-create&action=update-order';
                    payload.order_id = this.editingOrderId;
                }

                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.confirm && data.warnings) {
                    const msg = '⚠️ Анхааруулга:\n\n' + data.warnings.join('\n') + '\n\nҮргэлжлүүлэх үү?';
                    if (confirm(msg)) {
                        this.submitting = false;
                        return this.submitOrder(true);
                    }
                } else if (data.error) {
                    this.errorMsg = data.error;
                } else if (data.success) {
                    this.successOrder = data;
                }
            } catch (e) {
                this.errorMsg = 'Серверийн алдаа';
            }
            this.submitting = false;
        },

        // ── Edit order ──
        async loadOrderForEdit(orderId) {
            this.errorMsg = '';
            this.successOrder = null;

            try {
                const res = await fetch(`index.php?page=order-create&action=fetch-order&id=${orderId}`);
                const data = await res.json();
                if (data.error) {
                    this.errorMsg = data.error;
                    return;
                }

                const o = data.order;
                this.editingOrderId = o.id;
                this.editingOrderNumber = o.order_number;

                // Fill customer
                this.customer.phone = o.customer_phone;
                this.customer.name = o.customer_name;

                // Fill delivery
                this.fulfillment = o.fulfillment;
                if (o.fulfillment === 'delivery') {
                    this.delivery.district_id = o.district_id ? String(o.district_id) : '';
                    this.updateKhoroos();
                    this.$nextTick(() => {
                        this.delivery.khoroo_id = o.khoroo_id ? String(o.khoroo_id) : '';
                    });
                    this.delivery.address = o.address || '';
                    this.delivery.detail_address = o.detail_address || '';
                }

                // Fill payment
                this.paymentMethod = o.payment_method;
                this.paymentStatus = o.payment_status;
                this.notes = o.notes || '';

                // Fill cart
                this.cart = data.items.map(i => ({
                    id: i.id,
                    name: i.name,
                    price: i.price,
                    qty: i.qty,
                    stock: i.stock,
                    type: i.type,
                    variant_id: i.variant_id || null,
                    variant_label: i.variant_label || null,
                }));

                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                this.errorMsg = 'Захиалга ачаалахад алдаа гарлаа';
            }
        },

        cancelEdit() {
            this.editingOrderId = null;
            this.editingOrderNumber = '';
            this.resetForm();
        },

        resetForm() {
            this.editingOrderId = null;
            this.editingOrderNumber = '';
            this.customer = { phone: '', name: '' };
            this.customerId = null;
            this.customerFound = false;
            this.savedAddresses = [];
            this.selectedAddressId = null;
            this.cart = [];
            this.fulfillment = 'delivery';
            this.delivery = { district_id: '', khoroo_id: '', address: '', detail_address: '' };
            this.currentKhoroos = [];
            this.paymentMethod = 'cash';
            this.paymentStatus = 'pending';
            this.notes = '';
            this.errorMsg = '';
            this.successOrder = null;
            this.$refs.productInput?.focus();
        },

        // ── Helpers ──
        formatP(n) {
            return Number(n).toLocaleString() + '₮';
        },
    };
}

function recentOrdersApp() {
    return {
        csrfToken: '<?= generateCSRFToken() ?>',
        orders: <?= json_encode(array_map(function($o) {
            return [
                'id' => (int)$o['id'],
                'status' => $o['status'],
                'payment' => $o['payment_status'],
                'loading' => false,
                'msg' => '',
                'msgType' => '',
            ];
        }, $recentOrders), JSON_HEX_APOS | JSON_HEX_TAG) ?>,

        async updateOrder(idx, type, value) {
            const order = this.orders[idx];
            const oldValue = type === 'status' ? order.status : order.payment;
            order.loading = true;
            order.msg = '';

            try {
                const res = await fetch('index.php?page=order-create&action=quick-update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: this.csrfToken,
                        order_id: order.id,
                        type: type,
                        value: value,
                    }),
                });
                const data = await res.json();
                if (data.error) {
                    // Revert
                    if (type === 'status') order.status = oldValue;
                    else order.payment = oldValue;
                    order.msg = data.error;
                    order.msgType = 'error';
                } else {
                    order.msg = '✓';
                    order.msgType = 'success';
                }
            } catch (e) {
                if (type === 'status') order.status = oldValue;
                else order.payment = oldValue;
                order.msg = 'Алдаа';
                order.msgType = 'error';
            }
            order.loading = false;
        },

        togglePayment(idx) {
            const order = this.orders[idx];
            const newVal = order.payment === 'paid' ? 'pending' : 'paid';
            order.payment = newVal;
            this.updateOrder(idx, 'payment', newVal);
        },

        cancelOrder(idx) {
            if (!confirm('Энэ захиалгыг цуцлах уу?')) return;
            this.orders[idx].status = 'cancelled';
            this.updateOrder(idx, 'status', 'cancelled');
        },
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
