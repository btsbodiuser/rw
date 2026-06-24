<?php
requireRole('super_admin', 'admin');
$id = (int)($_GET['id'] ?? 0);

if (!$id || !verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('error', 'Буруу хүсэлт.');
    header('Location: index.php?page=media');
    exit;
}

$result = deleteMedia($id);

if (isset($result['error'])) {
    setFlash('error', $result['error']);
} else {
    setFlash('success', 'Медиа устгагдлаа.');
}

header('Location: index.php?page=media');
exit;
