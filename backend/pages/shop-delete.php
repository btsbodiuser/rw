<?php
requireRole('super_admin', 'admin');
$db = getDB();
$id = $_GET['id'] ?? null;
if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=shops');
    exit;
}

$productCount = $db->prepare("SELECT COUNT(*) FROM products WHERE shop_id = ?");
$productCount->execute([$id]);
if ($productCount->fetchColumn() > 0) {
    setFlash('error', 'Бүтээгдэхүүнтэй дэлгүүрийг устгах боломжгүй. Эхлээд бүтээгдэхүүнийг хасах буюу шилжүүлнэ үү.');
} else {
    $stmt = $db->prepare("SELECT logo FROM shops WHERE id = ?");
    $stmt->execute([$id]);
    $shop = $stmt->fetch();
    if ($shop && $shop['logo']) deleteImage($shop['logo']);
    $db->prepare("DELETE FROM shops WHERE id = ?")->execute([$id]);
    setFlash('success', 'Дэлгүүр устгагдлаа.');
}
header('Location: index.php?page=shops');
exit;
