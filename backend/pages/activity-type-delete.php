<?php
requireRole('super_admin', 'admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=activity-types');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$s = $db->prepare("SELECT * FROM activity_types WHERE id = ?");
$s->execute([$id]);
$item = $s->fetch();

if (!$item) {
    setFlash('error', 'Төрөл олдсонгүй.');
    header('Location: index.php?page=activity-types');
    exit;
}

// Pivot rows deleted automatically via CASCADE FK
$db->prepare("DELETE FROM activity_types WHERE id = ?")->execute([$id]);
setFlash('success', '"' . $item['name_mn'] . '" устгагдлаа.');
header('Location: index.php?page=activity-types');
exit;
