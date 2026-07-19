<?php
requireRole('super_admin', 'admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=cargo-batches');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Багц олдсонгүй.');
    header('Location: index.php?page=cargo-batches');
    exit;
}

$batch = $db->prepare("SELECT * FROM cargo_batches WHERE id = ?");
$batch->execute([$id]);
$batch = $batch->fetch();

if (!$batch) {
    setFlash('error', 'Багц олдсонгүй.');
    header('Location: index.php?page=cargo-batches');
    exit;
}

// NULL out all references before deleting
$db->prepare("UPDATE order_items       SET cargo_batch_id = NULL WHERE cargo_batch_id = ?")->execute([$id]);
$db->prepare("UPDATE orders            SET cargo_batch_id = NULL WHERE cargo_batch_id = ?")->execute([$id]);
$db->prepare("UPDATE products          SET cargo_batch_id = NULL WHERE cargo_batch_id = ?")->execute([$id]);
$db->prepare("UPDATE inventory_arrivals SET cargo_batch_id = NULL WHERE cargo_batch_id = ?")->execute([$id]);

$db->prepare("DELETE FROM cargo_batches WHERE id = ?")->execute([$id]);

setFlash('success', '"' . $batch['name'] . '" багц устгагдлаа.');
header('Location: index.php?page=cargo-batches');
exit;
