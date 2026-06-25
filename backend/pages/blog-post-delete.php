<?php
requireRole('super_admin', 'admin');
$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=blog-posts');
    exit;
}

$db->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
setFlash('success', 'Нийтлэл устгагдлаа.');
header('Location: index.php?page=blog-posts');
exit;
