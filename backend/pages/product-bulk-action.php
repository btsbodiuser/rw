<?php
requireRole('super_admin', 'admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=products');
    exit;
}

$action = $_POST['bulk_action'] ?? '';
$ids    = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));

if (empty($ids)) {
    setFlash('error', 'Бүтээгдэхүүн сонгоогүй байна.');
    header('Location: index.php?page=products');
    exit;
}

$returnUrl = 'index.php?page=products';
if (!empty($_POST['return']) && str_starts_with($_POST['return'], 'index.php?page=products')) {
    $returnUrl = $_POST['return'];
}

// ── Bulk-set attribute (activity / shoe_type / run_type / cushioning / gait) or gender ──
if ($action === 'set_attribute') {
    $attrKey = $_POST['attr'] ?? '';
    $mode    = $_POST['mode'] ?? 'add';          // add | replace | clear
    $values  = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['values'] ?? [])))));

    // Gender is a scalar column, not a pivot — handle separately
    if ($attrKey === 'gender') {
        $g = $_POST['gender'] ?? '';
        if (!in_array($g, ['men', 'women', 'unisex', 'kids'], true)) {
            setFlash('error', 'Хүчингүй хүйс.');
            header('Location: ' . $returnUrl);
            exit;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE products SET gender = ? WHERE id IN ($ph)")
           ->execute(array_merge([$g], $ids));
        setFlash('success', count($ids) . ' бүтээгдэхүүний хүйс шинэчлэгдлээ.');
        header('Location: ' . $returnUrl);
        exit;
    }

    $attrMap = [
        'activity'   => ['product_activity_types', 'activity_type_id'],
        'shoe_type'  => ['product_shoe_types',     'shoe_type_id'],
        'run_type'   => ['product_run_types',      'run_type_id'],
        'cushioning' => ['product_cushionings',    'cushioning_id'],
        'gait'       => ['product_gait_types',     'gait_type_id'],
    ];
    if (!isset($attrMap[$attrKey])) {
        setFlash('error', 'Үл мэдэгдэх шинж чанар.');
        header('Location: ' . $returnUrl);
        exit;
    }
    [$pivot, $fk] = $attrMap[$attrKey];

    if ($mode !== 'clear' && empty($values)) {
        setFlash('error', 'Утга сонгоогүй байна.');
        header('Location: ' . $returnUrl);
        exit;
    }

    $delAll  = $db->prepare("DELETE FROM `$pivot` WHERE product_id = ?");
    $delOne  = $db->prepare("DELETE FROM `$pivot` WHERE product_id = ? AND `$fk` = ?");
    $ins     = $db->prepare("INSERT IGNORE INTO `$pivot` (product_id, `$fk`) VALUES (?, ?)");

    $touched = 0;
    foreach ($ids as $pid) {
        if ($mode === 'replace' || $mode === 'clear') {
            $delAll->execute([$pid]);
        }
        if ($mode !== 'clear') {
            foreach ($values as $vid) {
                if ($mode === 'add' || $mode === 'replace') {
                    $ins->execute([$pid, $vid]);
                }
            }
        }
        $touched++;
    }

    $modeLabel = ['add' => 'нэмсэн', 'replace' => 'солисон', 'clear' => 'арилгасан'][$mode] ?? '';
    setFlash('success', "$touched бүтээгдэхүүнд $modeLabel.");
    header('Location: ' . $returnUrl);
    exit;
}

if ($action === 'delete') {
    $deleted = 0;
    $deactivated = 0;
    foreach ($ids as $id) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) continue;

        $orderCheck = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $orderCheck->execute([$id]);
        if ($orderCheck->fetchColumn() > 0) {
            $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$id]);
            $deactivated++;
        } else {
            if ($product['image'] && !($product['main_image_id'] ?? null)) {
                deleteImage($product['image']);
            }
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            $deleted++;
        }
    }

    $msg = [];
    if ($deleted)     $msg[] = $deleted . ' бүтээгдэхүүн устгагдлаа';
    if ($deactivated) $msg[] = $deactivated . ' нь захиалгатай тул идэвхгүй болгогдлоо';
    setFlash($deactivated ? 'warning' : 'success', implode(', ', $msg) . '.');
}

header('Location: ' . $returnUrl);
exit;
