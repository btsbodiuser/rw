<?php
requireRole('super_admin', 'admin');
$db = getDB();
$id = $_GET['id'] ?? null;

// Build safe return URL
$returnUrl = 'index.php?page=products';
if (!empty($_GET['return']) && strpos($_GET['return'], 'index.php?page=products') === 0) {
    $returnUrl = $_GET['return'];
}

if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: ' . $returnUrl);
    exit;
}

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'Бүтээгдэхүүн олдсонгүй.');
    header('Location: ' . $returnUrl);
    exit;
}

// Check if product has order items
$orderCheck = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
$orderCheck->execute([$id]);
if ($orderCheck->fetchColumn() > 0) {
    // Soft delete - just deactivate
    $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('warning', 'Бүтээгдэхүүнд захиалга байгаа тул устгахын оронд идэвхгүй болголоо.');
} else {
    // Delete old direct-upload image (not media library images)
    if ($product['image'] && !$product['main_image_id']) {
        deleteImage($product['image']);
    }
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Бүтээгдэхүүн устгагдлаа.');
}

header('Location: ' . $returnUrl);
exit;
