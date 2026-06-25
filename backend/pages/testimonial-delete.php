<?php
requireRole('super_admin', 'admin');
$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=testimonials');
    exit;
}

$db->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
setFlash('success', 'Сэтгэгдэл устгагдлаа.');
header('Location: index.php?page=testimonials');
exit;
