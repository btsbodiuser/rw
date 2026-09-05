<?php
/**
 * AJAX Media Upload Handler
 * POST: Upload file, return JSON
 * GET with action=list: Return all media as JSON
 */

// GET: Return media list for AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    header('Content-Type: application/json');
    $db = getDB();
    $media = $db->query("SELECT id, filename, original_name, mime_type, file_size FROM media ORDER BY created_at DESC")->fetchAll();
    foreach ($media as &$m) {
        $m['id'] = (int)$m['id'];
        $m['file_size'] = (int)$m['file_size'];
    }
    echo json_encode(['media' => $media]);
    exit;
}

// POST: Upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Буруу хүсэлт']);
        exit;
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Файл хуулагдаагүй']);
        exit;
    }

    // Optional override for the auto-resize cap. Slider/banner uploads pass
    // a bigger value (or 0) so hero images aren't downscaled to product-card size.
    $maxDim = isset($_POST['max_dim']) ? max(0, (int)$_POST['max_dim']) : 1000;
    // Sanity cap so nothing wild passes through
    if ($maxDim > 4000) $maxDim = 4000;

    $result = uploadMedia($_FILES['file'], $maxDim);
    echo json_encode($result);
    exit;
}

// Fallback
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid request method']);
