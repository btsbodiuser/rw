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
