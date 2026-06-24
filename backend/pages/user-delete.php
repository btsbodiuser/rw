<?php
/**
 * User Delete — Delete admin user (Super Admin only)
 */
requireRole('super_admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=users');
    exit;
}

// Prevent self-deletion
if ($id === $currentAdmin['id']) {
    setFlash('error', 'Өөрийгөө устгах боломжгүй.');
    header('Location: index.php?page=users');
    exit;
}

$db = getDB();
$stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
$stmt->execute([$id]);

setFlash('success', 'Хэрэглэгч устгагдлаа.');
header('Location: index.php?page=users');
exit;
