<?php
$pageTitle = isset($_GET['id']) ? 'Бүтээгдэхүүн засах' : 'Бүтээгдэхүүн нэмэх';
$db = getDB();
$id = $_GET['id'] ?? null;
$product = null;

// Build safe return URL (only allow internal index.php links)
$returnUrl = 'index.php?page=products';
if (!empty($_GET['return']) && strpos($_GET['return'], 'index.php?page=products') === 0) {
    $returnUrl = $_GET['return'];
} elseif (!empty($_POST['return_url']) && strpos($_POST['return_url'], 'index.php?page=products') === 0) {
    $returnUrl = $_POST['return_url'];
}

if ($id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        setFlash('error', 'Бүтээгдэхүүн олдсонгүй.');
        header('Location: ' . $returnUrl);
        exit;
    }
}

$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Build a parent → children ordered list so the dropdown shows hierarchy
$_catById = [];
foreach ($categories as $_c) { $_catById[(int)$_c['id']] = $_c; }
$_catParents = array_values(array_filter($categories, fn($c) => empty($c['parent_id'])));
$categoriesOrdered = [];
foreach ($_catParents as $p) {
    $pid = (int)$p['id'];
    $p['_depth'] = 0;
    $categoriesOrdered[] = $p;
    foreach ($categories as $c) {
        if ((int)($c['parent_id'] ?? 0) === $pid) {
            $c['_depth'] = 1;
            $categoriesOrdered[] = $c;
        }
    }
}
// Append any orphaned subcategories (parent inactive/missing) so nothing disappears
foreach ($categories as $c) {
    if (!empty($c['parent_id']) && !isset($_catById[(int)$c['parent_id']])) {
        $c['_depth'] = 0;
        $categoriesOrdered[] = $c;
    }
}
$shops = $db->query("SELECT * FROM shops WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$openBatches = $db->query("SELECT id, name, status, cargo_rate_per_kg FROM cargo_batches WHERE status = 'open' ORDER BY created_at DESC")->fetchAll();

// Load colors and sizes for variant management
$allColors = $db->query("SELECT * FROM product_colors ORDER BY sort_order")->fetchAll();
$allSizes = $db->query("SELECT * FROM product_sizes ORDER BY sort_order")->fetchAll();

// Gender & activity
$allActivityTypes = $db->query("SELECT * FROM activity_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$productActivityIds = [];
if ($id) {
    $aStmt = $db->prepare("SELECT activity_type_id FROM product_activity_types WHERE product_id = ?");
    $aStmt->execute([$id]);
    $productActivityIds = $aStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Running attributes: shoe_types / run_types / cushionings / gait_types
// Config: form-field-name → [master table, pivot table, pivot fk column]
$runningAttrs = [
    'shoe_type_ids'  => ['shoe_types',  'product_shoe_types',  'shoe_type_id'],
    'run_type_ids'   => ['run_types',   'product_run_types',   'run_type_id'],
    'cushioning_ids' => ['cushionings', 'product_cushionings', 'cushioning_id'],
    'gait_type_ids'  => ['gait_types',  'product_gait_types',  'gait_type_id'],
];
$runningAttrLabels = [
    'shoe_type_ids'  => 'Гутлын төрөл',
    'run_type_ids'   => 'Гүйлтийн төрөл',
    'cushioning_ids' => 'Зөөлөвч',
    'gait_type_ids'  => 'Алхааны төрөл',
];
$runningAttrOptions   = [];
$runningAttrSelected = [];
foreach ($runningAttrs as $field => [$table, $pivot, $fk]) {
    $runningAttrOptions[$field]  = $db->query("SELECT id, name, name_mn FROM `$table` WHERE is_active = 1 ORDER BY sort_order, name_mn")->fetchAll();
    $runningAttrSelected[$field] = [];
    if ($id) {
        $ps = $db->prepare("SELECT `$fk` FROM `$pivot` WHERE product_id = ?");
        $ps->execute([$id]);
        $runningAttrSelected[$field] = array_map('intval', $ps->fetchAll(PDO::FETCH_COLUMN));
    }
}

// Load existing variants for edit mode
$existingVariants = [];
$existingColorImages = [];
if ($id) {
    $vStmt = $db->prepare("SELECT pv.*, pc.name_mn as color_name, ps.name as size_name FROM product_variants pv LEFT JOIN product_colors pc ON pv.color_id = pc.id LEFT JOIN product_sizes ps ON pv.size_id = ps.id WHERE pv.product_id = ? ORDER BY pc.sort_order, ps.sort_order");
    $vStmt->execute([$id]);
    $existingVariants = $vStmt->fetchAll();

    $ciStmt = $db->prepare("SELECT pci.color_id, pci.media_id, pci.sort_order FROM product_color_images pci WHERE pci.product_id = ? ORDER BY pci.color_id, pci.sort_order");
    $ciStmt->execute([$id]);
    foreach ($ciStmt->fetchAll() as $ci) {
        $cid = (int)$ci['color_id'];
        if (!isset($existingColorImages[$cid])) $existingColorImages[$cid] = [];
        $existingColorImages[$cid][] = (int)$ci['media_id'];
    }
}

// Load existing price tiers for edit mode
$existingTiers = [];
if ($id) {
    $tStmt = $db->prepare("SELECT min_qty, unit_price FROM product_price_tiers WHERE product_id = ? ORDER BY min_qty ASC");
    $tStmt->execute([$id]);
    $existingTiers = $tStmt->fetchAll();
}

// Helper: save price tiers from POST data
function saveProductPriceTiers(PDO $db, int $productId, array $post): void {
    $tiersJson = $post['price_tiers_data'] ?? '[]';
    $tiers = json_decode($tiersJson, true);
    if (!is_array($tiers)) $tiers = [];

    $db->prepare("DELETE FROM product_price_tiers WHERE product_id = ?")->execute([$productId]);

    if (!$tiers) return;

    $seen = [];
    $ins = $db->prepare("INSERT INTO product_price_tiers (product_id, min_qty, unit_price) VALUES (?, ?, ?)");
    foreach ($tiers as $t) {
        $minQty    = isset($t['min_qty']) ? (int)$t['min_qty'] : 0;
        $unitPrice = isset($t['unit_price']) && $t['unit_price'] !== '' ? (float)$t['unit_price'] : 0;
        if ($minQty < 1 || $unitPrice <= 0) continue;
        if (isset($seen[$minQty])) continue; // UNIQUE(product_id, min_qty) — skip duplicates
        $seen[$minQty] = true;
        $ins->execute([$productId, $minQty, $unitPrice]);
    }
}

// Helper: save variants from POST data. Captures stock deltas for the ledger.
function saveProductVariants(PDO $db, int $productId, array $post): void {
    global $currentAdmin;
    $adminId = $currentAdmin['id'] ?? null;

    $variantsJson = $post['variants_data'] ?? '[]';
    $variants = json_decode($variantsJson, true);
    if (!is_array($variants)) $variants = [];

    $colorImagesJson = $post['color_images_data'] ?? '{}';
    $colorImagesData = json_decode($colorImagesJson, true);
    if (!is_array($colorImagesData)) $colorImagesData = [];

    // Snapshot old variant stocks so we can compute deltas.
    $existing = $db->prepare("SELECT id, stock FROM product_variants WHERE product_id = ?");
    $existing->execute([$productId]);
    $oldVariantStocks = [];
    $existingIds = [];
    foreach ($existing->fetchAll() as $row) {
        $vid = (int)$row['id'];
        $existingIds[] = $vid;
        $oldVariantStocks[$vid] = $row['stock'] === null ? null : (int)$row['stock'];
    }
    $keepIds = [];

    // Stock changes we discovered, applied AFTER all writes are committed.
    // [productId, variantId, delta, note]
    $pendingMovements = [];

    foreach ($variants as $v) {
        $colorId = !empty($v['color_id']) ? (int)$v['color_id'] : null;
        $sizeId = !empty($v['size_id']) ? (int)$v['size_id'] : null;
        $sku = !empty($v['sku']) ? trim($v['sku']) : null;
        $priceOverride = isset($v['price_override']) && $v['price_override'] !== '' ? (float)$v['price_override'] : null;
        $vStockRaw = $v['stock'] ?? '';
        $stock = ($vStockRaw !== '') ? (int)$vStockRaw : null;
        $isActive = isset($v['is_active']) ? (int)$v['is_active'] : 1;
        $existingId = !empty($v['id']) ? (int)$v['id'] : 0;

        if ($existingId && in_array($existingId, $existingIds)) {
            $db->prepare("UPDATE product_variants SET color_id=?, size_id=?, sku=?, price_override=?, stock=?, is_active=? WHERE id=? AND product_id=?")
                ->execute([$colorId, $sizeId, $sku, $priceOverride, $stock, $isActive, $existingId, $productId]);
            $keepIds[] = $existingId;
            // Compute delta only when both old and new are tracked (non-NULL).
            $old = $oldVariantStocks[$existingId];
            if ($old !== null && $stock !== null && $old !== $stock) {
                $pendingMovements[] = [$productId, $existingId, $stock - $old, 'Variant stock edited'];
            }
        } else {
            $db->prepare("INSERT INTO product_variants (product_id, color_id, size_id, sku, price_override, stock, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$productId, $colorId, $sizeId, $sku, $priceOverride, $stock, $isActive]);
            $newVariantId = (int)$db->lastInsertId();
            // Initial capacity set — log as +stock if capped.
            if ($stock !== null && $stock !== 0) {
                $pendingMovements[] = [$productId, $newVariantId, $stock, 'Variant created'];
            }
        }
    }

    // Remove variants not in the new list — restore their stock as a negative movement
    // (the variant is going away; treat its stock as zeroed-out before deletion).
    $removeIds = array_diff($existingIds, $keepIds);
    if ($removeIds) {
        foreach ($removeIds as $rid) {
            $old = $oldVariantStocks[$rid] ?? null;
            if ($old !== null && $old !== 0) {
                $pendingMovements[] = [$productId, null, -$old, 'Variant removed (id ' . $rid . ')'];
            }
        }
        $ph = implode(',', array_fill(0, count($removeIds), '?'));
        $db->prepare("DELETE FROM product_variants WHERE id IN ($ph) AND product_id = ?")->execute([...array_values($removeIds), $productId]);
    }

    // Write the movements (manual_adjust) — directly, without calling adjustStock()
    // because the actual stock UPDATE already happened above. We just want the audit row.
    foreach ($pendingMovements as [$pid, $vid, $delta, $note]) {
        // Read balance_after from current state
        if ($vid !== null) {
            $b = $db->prepare("SELECT stock FROM product_variants WHERE id = ?");
            $b->execute([$vid]);
            $balAfter = $b->fetchColumn();
            $balAfter = $balAfter === false || $balAfter === null ? null : (int)$balAfter;
        } else {
            $balAfter = null;
        }
        $db->prepare("INSERT INTO stock_movements (product_id, variant_id, delta, balance_after, reason, order_id, actor_type, actor_id, note) VALUES (?, ?, ?, ?, 'manual_adjust', NULL, 'admin', ?, ?)")
           ->execute([$pid, $vid, $delta, $balAfter, $adminId, $note]);
    }

    // Save color images
    $db->prepare("DELETE FROM product_color_images WHERE product_id = ?")->execute([$productId]);
    $ciStmt = $db->prepare("INSERT INTO product_color_images (product_id, color_id, media_id, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($colorImagesData as $colorId => $mediaIds) {
        if (!is_array($mediaIds)) continue;
        foreach ($mediaIds as $sort => $mediaId) {
            $ciStmt->execute([$productId, (int)$colorId, (int)$mediaId, $sort]);
        }
    }

    // Sync products.stock with variant totals.
    // SUM returns NULL when every active variant has NULL stock — preserve that to mean "unlimited".
    $db->prepare("UPDATE products SET stock = (SELECT SUM(stock) FROM product_variants WHERE product_id = ? AND is_active = 1) WHERE id = ? AND has_variants = 1")
        ->execute([$productId, $productId]);
}

// Load all media for picker
$allMedia = $db->query("SELECT id, filename, original_name, mime_type, file_size FROM media ORDER BY created_at DESC")->fetchAll();
foreach ($allMedia as &$m) {
    $m['id'] = (int)$m['id'];
    $m['file_size'] = (int)$m['file_size'];
}
unset($m);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'clone_as_ready') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '') || !$id || !$product) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $returnUrl);
        exit;
    }

    // Generate unique slug
    $newSlug = slugify($product['name']);
    $slugCheck = $db->prepare("SELECT id FROM products WHERE slug = ?");
    $slugCheck->execute([$newSlug]);
    if ($slugCheck->fetch()) {
        $newSlug .= '-' . time();
    }

    // Clone product as ready type with stock=0, inactive, hidden from store
    $stmt = $db->prepare("INSERT INTO products 
        (name, name_mn, slug, category_id, shop_id, type, price, original_price, weight_kg, barcode, image, main_image_id, image_ids, description, description_mn, stock, has_variants, preorder_date, order_status, cargo_batch_id, rating, reviews, is_active, show_in_store, hide_cargo_fee)
        VALUES (?, ?, ?, ?, ?, 'ready', ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NULL, 'open', NULL, 0, 0, 0, 0, 0)");
    $stmt->execute([
        $product['name'], $product['name_mn'], $newSlug, $product['category_id'], $product['shop_id'],
        $product['price'], $product['original_price'], $product['weight_kg'], $product['barcode'],
        $product['image'], $product['main_image_id'], $product['image_ids'],
        $product['description'], $product['description_mn'],
        $product['has_variants'],
    ]);
    $newProductId = (int)$db->lastInsertId();

    // Clone variants if any
    if ($product['has_variants'] && !empty($existingVariants)) {
        $viStmt = $db->prepare("INSERT INTO product_variants (product_id, color_id, size_id, sku, price_override, stock, is_active) VALUES (?, ?, ?, ?, ?, 0, ?)");
        foreach ($existingVariants as $v) {
            $viStmt->execute([$newProductId, $v['color_id'], $v['size_id'], $v['sku'], $v['price_override'], $v['is_active']]);
        }
    }

    // Clone color images if any
    if (!empty($existingColorImages)) {
        $ciInsert = $db->prepare("INSERT INTO product_color_images (product_id, color_id, media_id, sort_order) VALUES (?, ?, ?, ?)");
        foreach ($existingColorImages as $colorId => $mediaIds) {
            foreach ($mediaIds as $sort => $mediaId) {
                $ciInsert->execute([$newProductId, $colorId, $mediaId, $sort]);
            }
        }
    }

    // Clone price tiers (only if cloned product has no variants — matches our tier policy)
    if (!$product['has_variants'] && !empty($existingTiers)) {
        $tInsert = $db->prepare("INSERT INTO product_price_tiers (product_id, min_qty, unit_price) VALUES (?, ?, ?)");
        foreach ($existingTiers as $t) {
            $tInsert->execute([$newProductId, (int)$t['min_qty'], (float)$t['unit_price']]);
        }
    }

    // Clone activity types
    if (!empty($productActivityIds)) {
        $aIns = $db->prepare("INSERT IGNORE INTO product_activity_types (product_id, activity_type_id) VALUES (?, ?)");
        foreach ($productActivityIds as $aid) { $aIns->execute([$newProductId, (int)$aid]); }
    }

    // Clone running attributes (shoe/run/cushioning/gait)
    foreach ($runningAttrs as $field => [$_t, $pivot, $fk]) {
        $sel = $runningAttrSelected[$field] ?? [];
        if (!$sel) continue;
        $rIns = $db->prepare("INSERT IGNORE INTO `$pivot` (product_id, `$fk`) VALUES (?, ?)");
        foreach ($sel as $vid) { $rIns->execute([$newProductId, (int)$vid]); }
    }

    setFlash('success', 'Бүтээгдэхүүн хуулагдсан (Нөөцтэй төрөл). Нөөц тоог оруулж, идэвхжүүлж, вэбсайтад харуулахыг тэмдэглэнэ үү.');
    header('Location: index.php?page=product-form&id=' . $newProductId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $nameMn = trim($_POST['name_mn'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $type = $_POST['type'] ?? 'ready';
    $price = (float)($_POST['price'] ?? 0);
    $originalPrice = !empty($_POST['original_price']) ? (float)$_POST['original_price'] : null;
    $weightKg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;
    $barcode = trim($_POST['barcode'] ?? '') ?: null;
    $description = trim($_POST['description'] ?? '');
    $descriptionMn = trim($_POST['description_mn'] ?? '');
    $stockRaw = $_POST['stock'] ?? '';
    $stock = ($stockRaw !== '') ? (int)$stockRaw : null;
    $hasVariants = isset($_POST['has_variants']) ? 1 : 0;
    $preorderDate = !empty($_POST['preorder_date']) ? $_POST['preorder_date'] : null;
    $orderStatus = $_POST['order_status'] ?? 'open';
    $cargoBatchId = !empty($_POST['cargo_batch_id']) ? (int)$_POST['cargo_batch_id'] : null;
    $rating = (float)($_POST['rating'] ?? 0);
    $reviews = (int)($_POST['reviews'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $showInStore = isset($_POST['show_in_store']) ? 1 : 0;
    $hideCargoFee = isset($_POST['hide_cargo_fee']) ? 1 : 0;
    $gender = in_array($_POST['gender'] ?? '', ['men','women','unisex','kids']) ? $_POST['gender'] : 'unisex';
    $activityIds = array_filter(array_map('intval', (array)($_POST['activity_ids'] ?? [])));
    // Collect running-attribute selections
    $runningAttrPost = [];
    foreach ($runningAttrs as $field => $_cfg) {
        $runningAttrPost[$field] = array_values(array_unique(array_filter(array_map('intval', (array)($_POST[$field] ?? [])))));
    }
    $slug = slugify($name);

    // Validation
    $errors = [];
    if (!$name) $errors[] = 'Бүтээгдэхүүний нэр шаардлагатай.';
    if (!$nameMn) $errors[] = 'Монгол нэр шаардлагатай.';
    if (!$categoryId) $errors[] = 'Ангилал сонгоно уу.';
    if (!$shopId) $errors[] = 'Брэнд сонгоно уу.';
    if ($price <= 0) $errors[] = 'Үнэ 0-ээс их байх ёстой.';
    if ($type === 'preorder' && !$weightKg) $errors[] = 'Урьдчилсан захиалгын бүтээгдэхүүнд жин шаардлагатай (ачааны төлбөр тооцоолох).';

    // Handle media selection
    $mainImageId = !empty($_POST['main_image_id']) ? (int)$_POST['main_image_id'] : null;
    $imageIdsInput = $_POST['image_ids'] ?? '[]';
    $imageIdsArr = json_decode($imageIdsInput, true);
    if (!is_array($imageIdsArr)) $imageIdsArr = [];
    $imageIdsJson = !empty($imageIdsArr) ? json_encode(array_map('intval', $imageIdsArr)) : null;

    // Sync image column for backward compat
    $imagePath = $product['image'] ?? '';
    if ($mainImageId) {
        $mStmt = $db->prepare("SELECT filename FROM media WHERE id = ?");
        $mStmt->execute([$mainImageId]);
        $mRow = $mStmt->fetch();
        if ($mRow) {
            $imagePath = 'uploads/media/' . $mRow['filename'];
        }
    }

    if (empty($errors)) {
        // Ensure unique slug
        if ($id) {
            $slugCheck = $db->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
            $slugCheck->execute([$slug, $id]);
        } else {
            $slugCheck = $db->prepare("SELECT id FROM products WHERE slug = ?");
            $slugCheck->execute([$slug]);
        }
        if ($slugCheck->fetch()) {
            $slug .= '-' . time();
        }

        if ($id) {
            // Snapshot old values before update
            $oldStockRow = $db->prepare("SELECT stock, has_variants, weight_kg, hide_cargo_fee, type FROM products WHERE id = ?");
            $oldStockRow->execute([$id]);
            $oldStockRowRes = $oldStockRow->fetch();
            $oldProductStock  = $oldStockRowRes && $oldStockRowRes['stock'] !== null ? (int)$oldStockRowRes['stock'] : null;
            $wasHasVariants   = $oldStockRowRes ? (int)$oldStockRowRes['has_variants'] : 0;
            $oldWeightKg      = $oldStockRowRes ? (float)$oldStockRowRes['weight_kg'] : 0.0;
            $wasHideCargoFee  = $oldStockRowRes ? (int)$oldStockRowRes['hide_cargo_fee'] : 0;
            $wasPreorder      = $oldStockRowRes && $oldStockRowRes['type'] === 'preorder';

            $stmt = $db->prepare("UPDATE products SET
                name=?, name_mn=?, slug=?, category_id=?, shop_id=?, type=?,
                price=?, original_price=?, weight_kg=?, barcode=?, image=?, main_image_id=?, image_ids=?,
                description=?, description_mn=?, stock=?, has_variants=?, preorder_date=?, order_status=?, cargo_batch_id=?, rating=?, reviews=?, is_active=?, show_in_store=?, hide_cargo_fee=?, gender=?
                WHERE id=?");
            $stmt->execute([$name, $nameMn, $slug, $categoryId, $shopId, $type,
                $price, $originalPrice, $weightKg, $barcode, $imagePath, $mainImageId, $imageIdsJson,
                $description, $descriptionMn, $stock, $hasVariants, $preorderDate, $orderStatus, $cargoBatchId, $rating, $reviews, $isActive, $showInStore, $hideCargoFee, $gender, $id]);

            // Log manual stock delta for no-variant products only — variant products
            // get their movements via saveProductVariants. Skip if was/now has variants
            // (the aggregate stock is derived, not a direct edit).
            if (!$hasVariants && !$wasHasVariants && $oldProductStock !== null && $stock !== null && $oldProductStock !== $stock) {
                $db->prepare("INSERT INTO stock_movements (product_id, variant_id, delta, balance_after, reason, order_id, actor_type, actor_id, note) VALUES (?, NULL, ?, ?, 'manual_adjust', NULL, 'admin', ?, ?)")
                   ->execute([$id, $stock - $oldProductStock, $stock, $currentAdmin['id'] ?? null, 'Product stock edited']);
            }

            // Recalculate deferred cargo fees when weight changes on a hide_cargo_fee preorder product
            $weightChanged = $wasPreorder && $wasHideCargoFee && $weightKg && abs((float)$weightKg - $oldWeightKg) > 0.0001;
            if ($weightChanged) {
                $cargoRate = (float)getSetting('cargo_rate_per_kg', 0);
                if ($cargoRate > 0) {
                    // Update order_items: recalculate cargo_fee for all unpaid hidden-fee items of this product
                    $db->prepare("
                        UPDATE order_items oi
                        JOIN orders o ON o.id = oi.order_id
                        SET oi.weight_kg   = ?,
                            oi.cargo_fee   = ROUND(? * oi.quantity, 2)
                        WHERE oi.product_id   = ?
                          AND oi.hide_cargo_fee = 1
                          AND oi.cargo_fee_paid = 0
                          AND o.status NOT IN ('cancelled','picked_up','delivered','completed')
                    ")->execute([$weightKg, $weightKg * $cargoRate, $id]);

                    // Re-sync orders.cargo_fee for affected orders
                    $affectedOrders = $db->prepare("
                        SELECT DISTINCT oi.order_id FROM order_items oi
                        JOIN orders o ON o.id = oi.order_id
                        WHERE oi.product_id = ? AND oi.hide_cargo_fee = 1
                          AND o.status NOT IN ('cancelled','picked_up','delivered','completed')
                    ");
                    $affectedOrders->execute([$id]);
                    foreach ($affectedOrders->fetchAll(PDO::FETCH_COLUMN) as $affectedOrderId) {
                        $newCargoFee = $db->prepare("SELECT SUM(cargo_fee) FROM order_items WHERE order_id = ?");
                        $newCargoFee->execute([(int)$affectedOrderId]);
                        $cargoFeeSum = (float)$newCargoFee->fetchColumn();
                        $db->prepare("
                            UPDATE orders
                            SET cargo_fee = ?,
                                total = subtotal + delivery_fee + ?
                            WHERE id = ?
                        ")->execute([$cargoFeeSum, $cargoFeeSum, (int)$affectedOrderId]);
                    }
                }
            }

            // Save variants
            if ($hasVariants) {
                saveProductVariants($db, (int)$id, $_POST);
            } else {
                // Remove all variants if disabled
                $db->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$id]);
                $db->prepare("DELETE FROM product_color_images WHERE product_id = ?")->execute([$id]);
            }

            // Save price tiers (tiers apply only to no-variant products — see decision in y.md)
            if (!$hasVariants) {
                saveProductPriceTiers($db, (int)$id, $_POST);
            } else {
                $db->prepare("DELETE FROM product_price_tiers WHERE product_id = ?")->execute([$id]);
            }

            // Sync activity types
            $db->prepare("DELETE FROM product_activity_types WHERE product_id = ?")->execute([$id]);
            foreach ($activityIds as $aid) {
                $db->prepare("INSERT IGNORE INTO product_activity_types (product_id, activity_type_id) VALUES (?, ?)")->execute([$id, $aid]);
            }

            // Sync running attributes (shoe_type / run_type / cushioning / gait)
            foreach ($runningAttrs as $field => [$_t, $pivot, $fk]) {
                $db->prepare("DELETE FROM `$pivot` WHERE product_id = ?")->execute([$id]);
                $ins = $db->prepare("INSERT IGNORE INTO `$pivot` (product_id, `$fk`) VALUES (?, ?)");
                foreach ($runningAttrPost[$field] as $vid) {
                    $ins->execute([$id, $vid]);
                }
            }

            setFlash('success', 'Бүтээгдэхүүн шинэчлэгдсэн.');
        } else {
            $stmt = $db->prepare("INSERT INTO products
                (name, name_mn, slug, category_id, shop_id, type, price, original_price, weight_kg, barcode, image, main_image_id, image_ids, description, description_mn, stock, has_variants, preorder_date, order_status, cargo_batch_id, rating, reviews, is_active, show_in_store, hide_cargo_fee, gender)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $nameMn, $slug, $categoryId, $shopId, $type,
                $price, $originalPrice, $weightKg, $barcode, $imagePath, $mainImageId, $imageIdsJson,
                $description, $descriptionMn, $stock, $hasVariants, $preorderDate, $orderStatus, $cargoBatchId, $rating, $reviews, $isActive, $showInStore, $hideCargoFee, $gender]);

            $newProductId = (int)$db->lastInsertId();

            // Log initial product stock if capped (no-variant case).
            if (!$hasVariants && $stock !== null && $stock !== 0) {
                $db->prepare("INSERT INTO stock_movements (product_id, variant_id, delta, balance_after, reason, order_id, actor_type, actor_id, note) VALUES (?, NULL, ?, ?, 'manual_adjust', NULL, 'admin', ?, ?)")
                   ->execute([$newProductId, $stock, $stock, $currentAdmin['id'] ?? null, 'Product created with initial stock']);
            }

            // Save variants for new product
            if ($hasVariants) {
                saveProductVariants($db, $newProductId, $_POST);
            }

            // Save price tiers for new product (no-variant only)
            if (!$hasVariants) {
                saveProductPriceTiers($db, $newProductId, $_POST);
            }

            // Save activity types for new product
            foreach ($activityIds as $aid) {
                $db->prepare("INSERT IGNORE INTO product_activity_types (product_id, activity_type_id) VALUES (?, ?)")->execute([$newProductId, $aid]);
            }

            // Save running attributes for new product
            foreach ($runningAttrs as $field => [$_t, $pivot, $fk]) {
                $ins = $db->prepare("INSERT IGNORE INTO `$pivot` (product_id, `$fk`) VALUES (?, ?)");
                foreach ($runningAttrPost[$field] as $vid) {
                    $ins->execute([$newProductId, $vid]);
                }
            }

            setFlash('success', 'Бүтээгдэхүүн үүсгэгдсэн.');
        }
        header('Location: ' . $returnUrl);
        exit;
    } else {
        // Keep submitted data on error
        $product = $_POST;
        $product['image'] = $imagePath;
        $product['main_image_id'] = $mainImageId;
        $product['image_ids'] = $imageIdsJson;
        // Preserve activity + running attribute selections for re-render
        $productActivityIds = $activityIds;
        foreach ($runningAttrs as $field => $_cfg) {
            $runningAttrSelected[$field] = $runningAttrPost[$field];
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-1 sm:px-0">
    <div class="mb-6">
        <a href="<?= e($returnUrl) ?>" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Бүтээгдэхүүн рүү буцах
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <?= csrfField() ?>
        <input type="hidden" name="return_url" value="<?= e($returnUrl) ?>">

        <!-- Basic Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Үндсэн мэдээлэл</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нэр (Англи) *</label>
                    <input type="text" name="name" value="<?= e($product['name'] ?? '') ?>" required
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нэр (Монгол) *</label>
                    <input type="text" name="name_mn" value="<?= e($product['name_mn'] ?? '') ?>" required
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ангилал *</label>
                    <select name="category_id" required class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">Ангилал сонгох</option>
                        <?php foreach ($categoriesOrdered as $c): ?>
                            <?php $indent = ($c['_depth'] ?? 0) ? '— ' : ''; ?>
                            <option value="<?= $c['id'] ?>" <?= ($product['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= e($indent . trim(($c['icon'] ?? '') . ' ' . ($c['name_mn'] ?: $c['name']))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Брэнд *</label>
                    <select name="shop_id" required class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">Брэнд сонгох</option>
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($product['shop_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Төрөл *</label>
                    <select name="type" id="product-type" required class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 outline-none"
                            onchange="var v=this.value==='preorder'?'':'none'; document.getElementById('preorder-date-field').style.display=v; document.getElementById('cargo-batch-field').style.display=v;">
                        <option value="ready" <?= ($product['type'] ?? '') === 'ready' ? 'selected' : '' ?>>Бэлэн (Нөөцөнд)</option>
                        <option value="preorder" <?= ($product['type'] ?? '') === 'preorder' ? 'selected' : '' ?>>Урьдчилсан (Солонгосоос)</option>
                    </select>
                </div>
                <div id="preorder-date-field" style="<?= ($product['type'] ?? '') !== 'preorder' ? 'display:none' : '' ?>">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Хүргэх огноо</label>
                    <input type="date" name="preorder_date" value="<?= e($product['preorder_date'] ?? '') ?>"
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div id="cargo-batch-field" style="<?= ($product['type'] ?? '') !== 'preorder' ? 'display:none' : '' ?>">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ачааны багц</label>
                    <select name="cargo_batch_id" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">— Багц сонгоогүй —</option>
                        <?php foreach ($openBatches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ($product['cargo_batch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['name']) ?> (<?= formatPrice($b['cargo_rate_per_kg']) ?>/кг)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Урьдчилсан захиалгын бараа энэ багцаар тээвэрлэгдэнэ</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Захиалгын төлөв</label>
                    <select name="order_status" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="open" <?= ($product['order_status'] ?? 'open') === 'open' ? 'selected' : '' ?>>Нээлттэй</option>
                        <option value="closed" <?= ($product['order_status'] ?? '') === 'closed' ? 'selected' : '' ?>>Хаалттай</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Хүйс</label>
                    <select name="gender" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="unisex" <?= ($product['gender'] ?? 'unisex') === 'unisex' ? 'selected' : '' ?>>Унисекс</option>
                        <option value="men"    <?= ($product['gender'] ?? '') === 'men'    ? 'selected' : '' ?>>Эрэгтэй</option>
                        <option value="women"  <?= ($product['gender'] ?? '') === 'women'  ? 'selected' : '' ?>>Эмэгтэй</option>
                        <option value="kids"   <?= ($product['gender'] ?? '') === 'kids'   ? 'selected' : '' ?>>Хүүхэд</option>
                    </select>
                </div>
            </div>
            <?php if (!empty($allActivityTypes)): ?>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Үйл ажиллагааны төрөл</label>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($allActivityTypes as $at): ?>
                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 cursor-pointer hover:border-blue-400 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-colors">
                        <input type="checkbox" name="activity_ids[]" value="<?= $at['id'] ?>"
                               <?= in_array($at['id'], $productActivityIds) ? 'checked' : '' ?>
                               class="w-4 h-4 rounded border-gray-300 text-blue-600">
                        <span class="text-sm"><?= $at['icon'] ? $at['icon'] . ' ' : '' ?><?= e($at['name_mn']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Running attributes: Shoe / Run / Cushioning / Gait -->
            <?php foreach ($runningAttrs as $field => $_cfg): ?>
                <?php $opts = $runningAttrOptions[$field]; if (empty($opts)) continue; ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= e($runningAttrLabels[$field]) ?></label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($opts as $opt): ?>
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 cursor-pointer hover:border-purple-400 has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50 transition-colors">
                            <input type="checkbox" name="<?= e($field) ?>[]" value="<?= (int)$opt['id'] ?>"
                                   <?= in_array((int)$opt['id'], $runningAttrSelected[$field], true) ? 'checked' : '' ?>
                                   class="w-4 h-4 rounded border-gray-300 text-purple-600">
                            <span class="text-sm"><?= e($opt['name_mn'] ?: $opt['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        // Prepare media data for Alpine.js
        $initMainImageId = (int)($product['main_image_id'] ?? 0);
        $initImageIds = '[]';
        if (!empty($product['image_ids'])) {
            $decoded = json_decode($product['image_ids'], true);
            if (is_array($decoded)) {
                $initImageIds = json_encode(array_map('intval', $decoded));
            }
        }
        ?>
        <!-- Images -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6"
             x-data="mediaPicker({
                 mainImageId: <?= $initMainImageId ?>,
                 imageIds: <?= $initImageIds ?>,
                 allMedia: <?= htmlspecialchars(json_encode($allMedia), ENT_QUOTES, 'UTF-8') ?>,
                 csrfToken: '<?= generateCSRFToken() ?>'
             })">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Зураг</h3>

            <!-- Main Image -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Үндсэн зураг</label>
                <div class="flex items-start gap-3">
                    <template x-if="mainImageId && getMedia(mainImageId)">
                        <div class="relative">
                            <img :src="'uploads/media/' + getMedia(mainImageId).filename" class="w-28 h-28 sm:w-32 sm:h-32 rounded-lg object-cover border border-gray-200">
                            <button type="button" @click="mainImageId = 0" class="absolute -top-2 -right-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-sm hover:bg-red-600 shadow">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="openPicker('main')"
                            class="w-28 h-28 sm:w-32 sm:h-32 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center text-gray-400 hover:border-blue-400 hover:text-blue-600 active:border-blue-500 active:text-blue-700 transition-colors">
                        <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-xs" x-text="mainImageId ? 'Солих' : 'Сонгох'"></span>
                    </button>
                </div>
                <input type="hidden" name="main_image_id" :value="mainImageId || ''">
            </div>

            <!-- Gallery Images -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Галерей зураг</label>
                <div class="flex flex-wrap gap-3 mb-1">
                    <template x-for="imgId in imageIds" :key="imgId">
                        <div class="relative" x-show="getMedia(imgId)">
                            <template x-if="getMedia(imgId)?.mime_type?.startsWith('video/')">
                                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg overflow-hidden border border-gray-200 bg-black flex items-center justify-center relative">
                                    <video :src="'uploads/media/' + (getMedia(imgId)?.filename || '')" class="w-full h-full object-cover" muted playsinline preload="metadata"></video>
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <svg class="w-8 h-8 text-white drop-shadow" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!getMedia(imgId)?.mime_type?.startsWith('video/')">
                                <img :src="'uploads/media/' + (getMedia(imgId)?.filename || '')" class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg object-cover border border-gray-200">
                            </template>
                            <button type="button" @click="removeGallery(imgId)" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 shadow">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="openPicker('gallery')"
                            class="w-20 h-20 sm:w-24 sm:h-24 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center text-gray-400 hover:border-blue-400 hover:text-blue-600 active:border-blue-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="text-xs mt-1">Нэмэх</span>
                    </button>
                </div>
                <input type="hidden" name="image_ids" :value="JSON.stringify(imageIds)">
            </div>

            <!-- Media Picker Modal -->
            <div x-show="pickerOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center sm:p-4" @keydown.escape.window="pickerOpen = false">
                <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-4xl h-[92vh] sm:max-h-[85vh] flex flex-col" @click.outside="pickerOpen = false">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-100">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900" x-text="pickerMode === 'main' ? 'Үндсэн зураг сонгох' : 'Галерей зураг сонгох'"></h3>
                        <div class="flex items-center gap-2 sm:gap-3">
                            <label class="px-3 sm:px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 active:bg-blue-800 cursor-pointer transition-colors">
                                <span x-show="!uploading" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <span class="hidden sm:inline">Шинэ оруулах</span>
                                    <span class="sm:hidden">Оруулах</span>
                                </span>
                                <span x-show="uploading">Оруулж байна...</span>
                                <input type="file" accept="image/*,video/mp4,video/webm" multiple class="hidden" @change="uploadFiles($event)" :disabled="uploading">
                            </label>
                            <button type="button" @click="pickerOpen = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3 sm:p-6">
                        <div x-show="allMedia.length === 0" class="text-center py-12 text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <p>Медиа байхгүй.</p>
                            <p class="text-sm mt-1">"Оруулах" дээр дарж камераар зураг авна уу.</p>
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 sm:gap-3">
                            <template x-for="media in allMedia" :key="media.id">
                                <button type="button" @click="toggleSelect(media)"
                                        :class="isSelected(media.id) ? 'ring-2 ring-blue-500 ring-offset-1 sm:ring-offset-2' : ''"
                                        class="relative aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-50 transition-all active:scale-95">
                                    <template x-if="media.mime_type?.startsWith('video/')">
                                        <div class="w-full h-full bg-black flex items-center justify-center">
                                            <video :src="'uploads/media/' + media.filename" class="w-full h-full object-cover" muted playsinline preload="metadata"></video>
                                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                <svg class="w-8 h-8 text-white drop-shadow" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!media.mime_type?.startsWith('video/')">
                                        <img :src="'uploads/media/' + media.filename" :alt="media.original_name" class="w-full h-full object-cover">
                                    </template>
                                    <div x-show="isSelected(media.id)" class="absolute top-1 right-1 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center shadow">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div x-show="pickerMode === 'gallery'" class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-100 flex items-center justify-between">
                        <p class="text-sm text-gray-500"><span x-text="imageIds.length"></span> зураг сонгогдсон</p>
                        <button type="button" @click="pickerOpen = false" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 active:bg-blue-800">Болсон</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Үнэ & Нөөц</h3>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Үнэ (₮) *</label>
                    <input type="number" name="price" value="<?= e($product['price'] ?? '') ?>" min="0" step="10" required inputmode="numeric"
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Хуучин үнэ (₮)</label>
                    <input type="number" name="original_price" value="<?= e($product['original_price'] ?? '') ?>" min="0" step="10" inputmode="numeric"
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                           placeholder="Хямдрал">
                </div>
                <div id="stock-field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Нөөц</label>
                    <input type="number" name="stock" value="<?= e($product['stock'] ?? '') ?>" min="0" inputmode="numeric"
                           placeholder="Хязгааргүй"
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <p class="text-xs text-gray-400 mt-1" id="stock-hint-variants" style="display:none">Хувилбартай бол нөөцийг хувилбар бүрт тохируулна</p>
                    <p class="text-xs text-gray-400 mt-1" id="stock-hint-preorder">Хоосон орхивол захиалга хязгааргүй</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Жин (kg)</label>
                    <input type="number" name="weight_kg" value="<?= e($product['weight_kg'] ?? '') ?>" min="0" step="0.001" inputmode="decimal"
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                           placeholder="1.500">
                </div>
            </div>
            <div class="mt-4 flex items-center gap-6">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Баркод</label>
                    <div class="flex gap-2 sm:gap-3">
                        <input type="text" name="barcode" value="<?= e($product['barcode'] ?? '') ?>"
                               class="flex-1 px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono"
                               placeholder="Баркод уншуулах / дугаар оруулах">
                    </div>
                </div>
                <div class="pt-5">
                    <label class="flex items-center gap-3 cursor-pointer py-2">
                        <input type="checkbox" name="has_variants" value="1" id="has-variants-toggle"
                               <?= ($product['has_variants'] ?? 0) ? 'checked' : '' ?>
                               onchange="document.getElementById('variants-section').style.display = this.checked ? '' : 'none'; document.getElementById('price-tiers-section').style.display = this.checked ? 'none' : '';"
                               class="w-6 h-6 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span class="text-sm font-medium text-gray-700">Хувилбартай (өнгө/размер)</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Price Tiers (quantity-based bulk discount) -->
        <?php
        $initTiers = json_encode(array_map(function($t) {
            return ['min_qty' => (int)$t['min_qty'], 'unit_price' => (float)$t['unit_price']];
        }, $existingTiers), JSON_HEX_APOS | JSON_HEX_TAG);
        ?>
        <div id="price-tiers-section" style="<?= ($product['has_variants'] ?? 0) ? 'display:none' : '' ?>"
             x-data="priceTierManager({ tiers: <?= htmlspecialchars($initTiers ?: '[]', ENT_QUOTES, 'UTF-8') ?> })">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-900">Олноор авбал хямд (Tier үнэ)</h3>
                    <button type="button" @click="addTier()" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">+ Хэсэг нэмэх</button>
                </div>
                <p class="text-xs text-gray-500 mb-4">Жишээ нь: 3+ ширхэг — 9,000₮ / 10+ ширхэг — 8,000₮. Тоо хэмжээнээс хамаарч үнэ автоматаар буурна. Хувилбартай бараанд хамаарахгүй.</p>
                <div class="space-y-2">
                    <template x-for="(t, idx) in tiers" :key="idx">
                        <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center gap-2 flex-1">
                                <span class="text-sm text-gray-600">Хамгийн багадаа</span>
                                <input type="number" x-model.number="t.min_qty" min="1" step="1" inputmode="numeric"
                                       class="w-20 px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <span class="text-sm text-gray-600">ш →</span>
                                <input type="number" x-model.number="t.unit_price" min="0" step="10" inputmode="numeric" placeholder="Нэгж үнэ"
                                       class="w-28 px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <span class="text-sm text-gray-600">₮</span>
                            </div>
                            <button type="button" @click="tiers.splice(idx, 1)" class="text-red-400 hover:text-red-600 text-sm font-bold px-2">✕</button>
                        </div>
                    </template>
                    <p x-show="tiers.length === 0" class="text-sm text-gray-400 italic">Tier үнэ байхгүй — үндсэн үнэ хэрэглэгдэнэ.</p>
                </div>
            </div>
            <input type="hidden" name="price_tiers_data" :value="JSON.stringify(tiers)">
        </div>

        <!-- Variants Section -->
        <?php
        $initVariants = json_encode(array_map(function($v) {
            return [
                'id' => (int)$v['id'],
                'color_id' => $v['color_id'] ? (int)$v['color_id'] : '',
                'size_id' => $v['size_id'] ? (int)$v['size_id'] : '',
                'sku' => $v['sku'] ?? '',
                'price_override' => $v['price_override'] !== null ? (float)$v['price_override'] : '',
                'stock' => $v['stock'] !== null ? (int)$v['stock'] : '',
                'is_active' => (int)$v['is_active'],
            ];
        }, $existingVariants), JSON_HEX_APOS | JSON_HEX_TAG);
        $initColorImages = json_encode(array_map(function($ids) { return array_map('intval', $ids); }, $existingColorImages), JSON_HEX_APOS | JSON_HEX_TAG);
        $colorsJson = json_encode(array_map(function($c) {
            return ['id' => (int)$c['id'], 'name' => $c['name'], 'name_mn' => $c['name_mn'], 'hex_code' => $c['hex_code']];
        }, $allColors), JSON_HEX_APOS | JSON_HEX_TAG);
        $sizesJson = json_encode(array_map(function($s) {
            return ['id' => (int)$s['id'], 'name' => $s['name']];
        }, $allSizes), JSON_HEX_APOS | JSON_HEX_TAG);
        ?>
        <div id="variants-section" style="<?= ($product['has_variants'] ?? 0) ? '' : 'display:none' ?>"
             x-data="variantManager({
                 variants: <?= htmlspecialchars($initVariants, ENT_QUOTES, 'UTF-8') ?>,
                 colorImages: <?= htmlspecialchars($initColorImages ?: '{}', ENT_QUOTES, 'UTF-8') ?>,
                 colors: <?= htmlspecialchars($colorsJson, ENT_QUOTES, 'UTF-8') ?>,
                 sizes: <?= htmlspecialchars($sizesJson, ENT_QUOTES, 'UTF-8') ?>,
                 allMedia: <?= htmlspecialchars(json_encode($allMedia), ENT_QUOTES, 'UTF-8') ?>,
                 csrfToken: '<?= generateCSRFToken() ?>'
             })">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Хувилбарууд</h3>
                    <button type="button" @click="addVariant()" class="px-3 py-1.5 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">+ Хувилбар нэмэх</button>
                </div>

                <!-- Quick Generate -->
                <div class="mb-4 p-3 bg-purple-50 rounded-lg border border-purple-100">
                    <p class="text-sm font-medium text-purple-800 mb-2">Хурдан үүсгэх</p>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="c in colors" :key="c.id">
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                <input type="checkbox" :value="c.id" x-model="genColors" class="rounded border-gray-300 text-purple-600">
                                <span class="w-3 h-3 rounded-full border border-gray-300 inline-block" :style="'background:' + (c.hex_code || '#ccc')"></span>
                                <span x-text="c.name_mn"></span>
                            </label>
                        </template>
                    </div>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="s in sizes" :key="s.id">
                            <label class="flex items-center gap-1 text-xs cursor-pointer">
                                <input type="checkbox" :value="s.id" x-model="genSizes" class="rounded border-gray-300 text-purple-600">
                                <span x-text="s.name"></span>
                            </label>
                        </template>
                    </div>
                    <button type="button" @click="generateVariants()" class="px-3 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-medium hover:bg-purple-700">Үүсгэх</button>
                </div>

                <!-- Variant rows -->
                <div class="space-y-2">
                    <template x-for="(v, idx) in variants" :key="idx">
                        <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg border border-gray-100">
                            <select x-model="v.color_id" class="px-2 py-1.5 border border-gray-300 rounded text-sm flex-1 min-w-0">
                                <option value="">Өнгөгүй</option>
                                <template x-for="c in colors" :key="c.id">
                                    <option :value="c.id" x-text="c.name_mn" :selected="v.color_id == c.id"></option>
                                </template>
                            </select>
                            <select x-model="v.size_id" class="px-2 py-1.5 border border-gray-300 rounded text-sm w-20">
                                <option value="">Хэмжээгүй</option>
                                <template x-for="s in sizes" :key="s.id">
                                    <option :value="s.id" x-text="s.name" :selected="v.size_id == s.id"></option>
                                </template>
                            </select>
                            <input type="text" x-model="v.sku" placeholder="SKU" class="px-2 py-1.5 border border-gray-300 rounded text-sm w-24 font-mono">
                            <input type="number" x-model.number="v.price_override" placeholder="Үнэ" class="px-2 py-1.5 border border-gray-300 rounded text-sm w-24" min="0" step="10">
                            <input type="number" x-model="v.stock" placeholder="∞" title="Хоосон = хязгааргүй" class="px-2 py-1.5 border border-gray-300 rounded text-sm w-20" min="0">
                            <label class="flex items-center gap-1">
                                <input type="checkbox" x-model="v.is_active" :true-value="1" :false-value="0" class="rounded border-gray-300 text-green-600">
                                <span class="text-xs text-gray-500">On</span>
                            </label>
                            <button type="button" @click="variants.splice(idx, 1)" class="text-red-400 hover:text-red-600 text-sm font-bold px-1">✕</button>
                        </div>
                    </template>
                </div>
                <p x-show="variants.length > 0" class="text-xs text-gray-400 mt-2">Нийт нөөц: <strong x-text="variants.reduce((s,v) => s + (parseInt(v.stock)||0), 0)"></strong></p>

                <!-- Color Images -->
                <template x-if="uniqueColors.length > 0">
                    <div class="mt-6 border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Өнгөний зурагнууд</h4>
                        <template x-for="cid in uniqueColors" :key="cid">
                            <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-4 h-4 rounded-full border border-gray-300" :style="'background:' + getColorHex(cid)"></span>
                                    <span class="text-sm font-medium" x-text="getColorName(cid)"></span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(mid, i) in (colorImages[cid] || [])" :key="i">
                                        <div class="relative" x-show="getMedia(mid)">
                                            <img :src="'uploads/media/' + (getMedia(mid)?.filename||'')" class="w-16 h-16 rounded-lg object-cover border border-gray-200">
                                            <button type="button" @click="removeColorImage(cid, i)" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs">&times;</button>
                                        </div>
                                    </template>
                                    <button type="button" @click="openColorImagePicker(cid)"
                                            class="w-16 h-16 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center text-gray-400 hover:border-purple-400 hover:text-purple-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Color Image Picker Modal -->
                        <div x-show="colorPickerOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center sm:p-4" @keydown.escape.window="colorPickerOpen = false">
                            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-4xl h-[80vh] sm:max-h-[75vh] flex flex-col" @click.outside="colorPickerOpen = false">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                    <h3 class="text-base font-semibold text-gray-900">Өнгөний зураг сонгох</h3>
                                    <button type="button" @click="colorPickerOpen = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <div class="flex-1 overflow-y-auto p-4">
                                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                                        <template x-for="media in allMedia" :key="media.id">
                                            <button type="button" @click="toggleColorImage(media.id)"
                                                    :class="isColorImageSelected(media.id) ? 'ring-2 ring-purple-500 ring-offset-1' : ''"
                                                    class="relative aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                                                <img :src="'uploads/media/' + media.filename" class="w-full h-full object-cover">
                                                <div x-show="isColorImageSelected(media.id)" class="absolute top-1 right-1 w-5 h-5 bg-purple-500 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <div class="px-4 py-3 border-t border-gray-100 flex justify-end">
                                    <button type="button" @click="colorPickerOpen = false" class="px-5 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">Болсон</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <input type="hidden" name="variants_data" :value="JSON.stringify(variants)">
            <input type="hidden" name="color_images_data" :value="JSON.stringify(colorImages)">
        </div>

        <!-- Description -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Тайлбар</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тайлбар (Англи)</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-vertical"><?= e($product['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тайлбар (Монгол)</label>
                    <textarea name="description_mn" rows="3"
                              class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-vertical"><?= e($product['description_mn'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Rating & Status -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Үнэлгээ & Төлөв</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Үнэлгээ (0-5)</label>
                    <input type="number" name="rating" value="<?= e($product['rating'] ?? 0) ?>" min="0" max="5" step="0.1" inputmode="decimal"
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Сэтгэгдэл тоо</label>
                    <input type="number" name="reviews" value="<?= e($product['reviews'] ?? 0) ?>" min="0" inputmode="numeric"
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="col-span-2 md:col-span-1 flex items-end">
                    <label class="flex items-center gap-3 cursor-pointer py-2">
                        <input type="checkbox" name="is_active" value="1" <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?>
                               class="w-6 h-6 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">Идэвхтэй</span>
                    </label>
                </div>
                <div class="col-span-2 md:col-span-1 flex items-end">
                    <label class="flex items-center gap-3 cursor-pointer py-2">
                        <input type="checkbox" name="show_in_store" value="1" <?= ($product['show_in_store'] ?? 1) ? 'checked' : '' ?>
                               class="w-6 h-6 rounded border-gray-300 text-green-600 focus:ring-green-500">
                        <span class="text-sm font-medium text-gray-700">Вэбсайтад харуулах</span>
                    </label>
                </div>
                <div class="col-span-2 md:col-span-1 flex items-end">
                    <label class="flex items-center gap-3 cursor-pointer py-2">
                        <input type="checkbox" name="hide_cargo_fee" value="1" <?= ($product['hide_cargo_fee'] ?? 0) ? 'checked' : '' ?>
                               class="w-6 h-6 rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                        <span class="text-sm font-medium text-gray-700">Ачааны төлбөр нуух (ирэхэд төлнө)</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="sticky bottom-0 bg-gray-100/95 backdrop-blur-sm py-3 sm:py-4 -mx-1 px-1 sm:mx-0 sm:px-0 sm:relative sm:bg-transparent sm:backdrop-blur-none flex items-center justify-end gap-3">
            <a href="<?= e($returnUrl) ?>" class="px-5 sm:px-6 py-3 sm:py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100">Болих</a>
            <button type="submit" id="product-submit-btn" class="flex-1 sm:flex-none px-6 py-3 sm:py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 active:bg-blue-800 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
                <?= $id ? 'Шинэчлэх' : 'Үүсгэх' ?>
            </button>
        </div>
    </form>
<script>
// Prevent double-submit on product form
document.querySelector('form')?.addEventListener('submit', function() {
    const btn = document.getElementById('product-submit-btn');
    if (btn.disabled) { event.preventDefault(); return; }
    btn.disabled = true;
    btn.textContent = 'Боловсруулж байна...';
});
</script>
</div>

<?php if ($id && $product && ($product['type'] ?? '') === 'preorder'): ?>
<!-- Clone as Ready Product -->
<div class="max-w-4xl mx-auto px-1 sm:px-0 mt-8 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-2">Нөөцтэй бараа болгон хуулах</h3>
        <p class="text-sm text-gray-500 mb-4">Энэ урьдчилсан захиалгын бүтээгдэхүүнийг нөөцтэй (ready) төрлөөр шинээр үүсгэнэ. Бүх мэдээлэл, хувилбар, зураг хуулагдана. Нөөц тоог дараа нь оруулна.</p>
        <form method="POST" action="index.php?page=product-form&id=<?= $id ?>&action=clone_as_ready" onsubmit="if(!confirm('Энэ бүтээгдэхүүнийг нөөцтэй бараа болгон хуулах уу?')) return false; this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').textContent='Хуулж байна...'; return true;">
            <?= csrfField() ?>
            <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors flex items-center gap-2 disabled:bg-gray-400 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                Нөөцтэй бараа болгон хуулах
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function mediaPicker(config) {
    return {
        mainImageId: config.mainImageId || 0,
        imageIds: Array.isArray(config.imageIds) ? [...config.imageIds] : [],
        allMedia: config.allMedia || [],
        pickerMode: '',
        pickerOpen: false,
        uploading: false,

        getMedia(id) {
            return this.allMedia.find(m => m.id === id);
        },

        openPicker(mode) {
            this.pickerMode = mode;
            this.pickerOpen = true;
        },

        toggleSelect(media) {
            if (this.pickerMode === 'main') {
                this.mainImageId = media.id;
                this.pickerOpen = false;
            } else {
                const idx = this.imageIds.indexOf(media.id);
                if (idx > -1) {
                    this.imageIds.splice(idx, 1);
                } else {
                    this.imageIds.push(media.id);
                }
            }
        },

        isSelected(id) {
            if (this.pickerMode === 'main') return this.mainImageId === id;
            return this.imageIds.includes(id);
        },

        removeGallery(id) {
            this.imageIds = this.imageIds.filter(i => i !== id);
        },

        async uploadFiles(event) {
            const files = event.target.files;
            if (!files.length) return;
            this.uploading = true;

            for (const file of files) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('csrf_token', config.csrfToken);

                try {
                    const res = await fetch('index.php?page=media-upload', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.allMedia.unshift(data.media);
                    } else {
                        alert(data.error || 'Upload failed');
                    }
                } catch(e) {
                    alert('Upload error');
                }
            }

            this.uploading = false;
            event.target.value = '';
        }
    };
}

function priceTierManager(config) {
    return {
        tiers: Array.isArray(config.tiers) ? [...config.tiers] : [],
        addTier() {
            const next = this.tiers.length === 0 ? 3 : (Math.max(...this.tiers.map(t => parseInt(t.min_qty) || 0)) + 1);
            this.tiers.push({ min_qty: next, unit_price: '' });
        },
    };
}

function variantManager(config) {
    return {
        variants: config.variants || [],
        colorImages: config.colorImages || {},
        colors: config.colors || [],
        sizes: config.sizes || [],
        allMedia: config.allMedia || [],
        genColors: [],
        genSizes: [],
        colorPickerOpen: false,
        activeColorId: null,

        addVariant() {
            this.variants.push({ id: 0, color_id: '', size_id: '', sku: '', price_override: '', stock: '', is_active: 1 });
        },

        generateVariants() {
            const colors = this.genColors.length > 0 ? this.genColors.map(Number) : [null];
            const sizes = this.genSizes.length > 0 ? this.genSizes.map(Number) : [null];
            for (const c of colors) {
                for (const s of sizes) {
                    const exists = this.variants.some(v =>
                        (v.color_id || null) == (c || null) && (v.size_id || null) == (s || null)
                    );
                    if (!exists) {
                        this.variants.push({ id: 0, color_id: c || '', size_id: s || '', sku: '', price_override: '', stock: '', is_active: 1 });
                    }
                }
            }
        },

        get uniqueColors() {
            const colorIds = [...new Set(this.variants.map(v => v.color_id).filter(Boolean).map(Number))];
            return colorIds;
        },

        getColorName(cid) {
            const c = this.colors.find(c => c.id === Number(cid));
            return c ? c.name_mn : 'Өнгө #' + cid;
        },

        getColorHex(cid) {
            const c = this.colors.find(c => c.id === Number(cid));
            return c?.hex_code || '#ccc';
        },

        getMedia(id) {
            return this.allMedia.find(m => m.id === id);
        },

        openColorImagePicker(colorId) {
            this.activeColorId = colorId;
            this.colorPickerOpen = true;
        },

        toggleColorImage(mediaId) {
            const cid = this.activeColorId;
            if (!cid) return;
            if (!this.colorImages[cid]) this.colorImages[cid] = [];
            const idx = this.colorImages[cid].indexOf(mediaId);
            if (idx > -1) {
                this.colorImages[cid].splice(idx, 1);
            } else {
                this.colorImages[cid].push(mediaId);
            }
        },

        isColorImageSelected(mediaId) {
            const cid = this.activeColorId;
            return cid && this.colorImages[cid] && this.colorImages[cid].includes(mediaId);
        },

        removeColorImage(cid, idx) {
            if (this.colorImages[cid]) {
                this.colorImages[cid].splice(idx, 1);
            }
        },
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
