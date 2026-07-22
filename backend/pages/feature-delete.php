<?php
requireRole('super_admin', 'admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=features');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$s = $db->prepare("SELECT * FROM features WHERE id = ?");
$s->execute([$id]);
$item = $s->fetch();

if (!$item) {
    setFlash('error', 'Хайрцаг олдсонгүй.');
    header('Location: index.php?page=features');
    exit;
}

$db->prepare("DELETE FROM features WHERE id = ?")->execute([$id]);
setFlash('success', '"' . $item['title_mn'] . '" устгагдлаа.');
header('Location: index.php?page=features');
exit;
