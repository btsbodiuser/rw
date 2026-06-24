<?php
requireRole('super_admin', 'admin');
$db = getDB();
$id = $_GET['id'] ?? null;
if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=categories');
    exit;
}

$productCount = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
$productCount->execute([$id]);
if ($productCount->fetchColumn() > 0) {
    setFlash('error', 'Бүтээгдэхүүнтэй ангиллыг устгах боломжгүй. Эхлээд бүтээгдэхүүнийг хасах буюу шилжүүлнэ үү.');
} else {
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    setFlash('success', 'Ангилал устгагдлаа.');
}
header('Location: index.php?page=categories');
exit;
