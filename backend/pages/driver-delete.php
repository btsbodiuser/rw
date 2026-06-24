<?php
$db = getDB();
$id = $_GET['id'] ?? null;

if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=drivers');
    exit;
}

// Check if driver has active deliveries
$activeCount = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE driver_id = ? AND status IN ('assigned','picked_up')");
$activeCount->execute([$id]);
if ((int)$activeCount->fetchColumn() > 0) {
    setFlash('error', 'Идэвхтэй хүргэлттэй жолоочийг устгах боломжгүй.');
    header('Location: index.php?page=drivers');
    exit;
}

$stmt = $db->prepare("DELETE FROM delivery_drivers WHERE id = ?");
$stmt->execute([$id]);

setFlash('success', 'Жолооч устгагдлаа.');
header('Location: index.php?page=drivers');
exit;
