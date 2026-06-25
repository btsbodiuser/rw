<?php
requireRole('super_admin', 'admin');
$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=sliders');
    exit;
}

$db->prepare("DELETE FROM sliders WHERE id = ?")->execute([$id]);
setFlash('success', 'Слайд устгагдлаа.');
header('Location: index.php?page=sliders');
exit;
