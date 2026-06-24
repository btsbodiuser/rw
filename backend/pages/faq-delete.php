<?php
requireRole('super_admin', 'admin');
$db = getDB();
$id = $_GET['id'] ?? null;
if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=faqs');
    exit;
}

$db->prepare("DELETE FROM faqs WHERE id = ?")->execute([$id]);
setFlash('success', 'Асуулт устгагдлаа.');
header('Location: index.php?page=faqs');
exit;
