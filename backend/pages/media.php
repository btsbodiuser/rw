<?php
$pageTitle = 'Медиа сан';
$db = getDB();

// Handle upload from form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['files'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=media');
        exit;
    }

    $uploaded = 0;
    $errors = [];
    $files = $_FILES['files'];

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'size' => $files['size'][$i],
        ];

        $result = uploadMedia($file);
        if (isset($result['error'])) {
            $errors[] = $files['name'][$i] . ': ' . $result['error'];
        } else {
            $uploaded++;
        }
    }

    if ($uploaded > 0) setFlash('success', "$uploaded файл амжилттай хуулагдлаа.");
    if (!empty($errors)) setFlash('error', implode(', ', $errors));

    header('Location: index.php?page=media');
    exit;
}

// Pagination
$page = max(1, (int)($_GET['pg'] ?? 1));
$perPage = 24;

$total = (int)$db->query("SELECT COUNT(*) FROM media")->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$media = $db->prepare("SELECT m.*, 
    (SELECT COUNT(*) FROM products WHERE main_image_id = m.id) as main_usage,
    (SELECT COUNT(*) FROM products WHERE JSON_CONTAINS(COALESCE(image_ids, '[]'), CONCAT('\"', m.id, '\"'))) as gallery_usage
    FROM media m 
    ORDER BY m.created_at DESC 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$media->execute();
$mediaItems = $media->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Top Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <p class="text-sm text-gray-500"><?= $total ?> файл санд</p>
    </div>
</div>

<!-- Upload Area -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Зураг хуулах</label>
                <input type="file" name="files[]" accept="image/*" multiple required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-3 file:py-1.5 file:px-4 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 file:text-sm file:font-medium">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP, GIF. Тус бүр 10MB хүртэл. Олон файл сонгох боломжтой.</p>
            </div>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors mt-5">
                Хуулах
            </button>
        </div>
    </form>
</div>

<?php renderFlash(); ?>

<!-- Media Grid -->
<?php if (empty($mediaItems)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <p class="text-lg font-medium">Медиа байхгүй</p>
        <p class="text-sm mt-1">Зураг хуулж эхлээрэй</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <?php foreach ($mediaItems as $m): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group">
            <div class="aspect-square bg-gray-50">
                <img src="uploads/media/<?= e($m['filename']) ?>" alt="<?= e($m['alt_text'] ?: $m['original_name']) ?>"
                     class="w-full h-full object-cover" loading="lazy">
            </div>
            <div class="p-3">
                <p class="text-xs font-medium text-gray-900 truncate" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= formatFileSize($m['file_size']) ?></p>
                <div class="flex items-center justify-between mt-2">
                    <?php
                    $usage = (int)$m['main_usage'] + (int)$m['gallery_usage'];
                    ?>
                    <span class="text-xs <?= $usage > 0 ? 'text-blue-600' : 'text-gray-400' ?>">
                        <?= $usage > 0 ? "Хэрэглэсэн: $usage" : 'Хэрэглээгүй' ?>
                    </span>
                    <?php if (!hasRole('pos_cashier')): ?>
                    <a href="index.php?page=media-delete&id=<?= $m['id'] ?>&token=<?= generateCSRFToken() ?>" 
                       onclick="return confirm('Энэ зургийг устгах уу<?= $usage > 0 ? ' (' . $usage . ' бүтээгдэхүүнд хэрэглэгдэж байна)' : '' ?>?')"
                       class="text-xs text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity">Устгах</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php renderPagination($pagination, 'index.php?page=media'); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
