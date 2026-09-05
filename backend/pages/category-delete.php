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
$childCount = $db->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
$childCount->execute([$id]);

if ($productCount->fetchColumn() > 0) {
    setFlash('error', 'Бүтээгдэхүүнтэй ангиллыг устгах боломжгүй. Эхлээд бүтээгдэхүүнийг хасах буюу шилжүүлнэ үү.');
} elseif ($childCount->fetchColumn() > 0) {
    setFlash('error', 'Дэд ангилалтай ангиллыг устгах боломжгүй. Эхлээд дэд ангилалуудыг устгах буюу өөр ангилал руу шилжүүлнэ үү.');
} else {
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    setFlash('success', 'Ангилал устгагдлаа.');
}
header('Location: index.php?page=categories');
exit;
