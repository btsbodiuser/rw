<?php
$pageTitle = isset($_GET['id']) ? 'Асуулт засах' : 'Асуулт нэмэх';
$db = getDB();
$id = $_GET['id'] ?? null;
$faq = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM faqs WHERE id = ?");
    $stmt->execute([$id]);
    $faq = $stmt->fetch();
    if (!$faq) {
        setFlash('error', 'Асуулт олдсонгүй.');
        header('Location: index.php?page=faqs');
        exit;
    }
}

// Existing categories for the dropdown suggestion
$existingCategories = $db->query("SELECT DISTINCT category FROM faqs ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $category = trim($_POST['category'] ?? '');
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($category === '') $errors[] = 'Ангилал шаардлагатай.';
    if (mb_strlen($category) > 100) $errors[] = 'Ангилал 100 тэмдэгтээс хэтэрч болохгүй.';
    if ($question === '') $errors[] = 'Асуулт шаардлагатай.';
    if (mb_strlen($question) > 500) $errors[] = 'Асуулт 500 тэмдэгтээс хэтэрч болохгүй.';
    if ($answer === '') $errors[] = 'Хариулт шаардлагатай.';

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE faqs SET category=?, question=?, answer=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->execute([$category, $question, $answer, $sortOrder, $isActive, $id]);
            setFlash('success', 'Асуулт шинэчлэгдлээ.');
        } else {
            $stmt = $db->prepare("INSERT INTO faqs (category, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$category, $question, $answer, $sortOrder, $isActive]);
            setFlash('success', 'Асуулт нэмэгдлээ.');
        }
        header('Location: index.php?page=faqs');
        exit;
    }
    // re-populate form on validation error
    $faq = [
        'category' => $category,
        'question' => $question,
        'answer' => $answer,
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=faqs" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            ТАХ руу буцах
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ангилал *</label>
            <input type="text" name="category" value="<?= e($faq['category'] ?? '') ?>" required maxlength="100"
                   list="faq-categories"
                   placeholder="Жишээ: Захиалга, Төлбөр, Хүргэлт..."
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <?php if (!empty($existingCategories)): ?>
            <datalist id="faq-categories">
                <?php foreach ($existingCategories as $cat): ?>
                    <option value="<?= e($cat) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <p class="text-xs text-gray-400 mt-1">Шинэ ангилал бичих эсвэл байгаагаас сонгож болно.</p>
            <?php endif; ?>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Асуулт *</label>
            <input type="text" name="question" value="<?= e($faq['question'] ?? '') ?>" required maxlength="500"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Хариулт *</label>
            <textarea name="answer" required rows="6"
                      class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-vertical"><?= e($faq['answer'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Дараалал</label>
            <input type="number" name="sort_order" value="<?= (int)($faq['sort_order'] ?? 0) ?>" min="0"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <p class="text-xs text-gray-400 mt-1">Бага тоо урагш гарна. Нэг ангилал доторх асуултын дараалалд ашиглагдана.</p>
        </div>

        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= ($faq['is_active'] ?? 1) ? 'checked' : '' ?>
                   class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="text-sm font-medium text-gray-700">Идэвхтэй (хэрэглэгчид харагдана)</span>
        </label>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="index.php?page=faqs" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Болих</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <?= $id ? 'Шинэчлэх' : 'Үүсгэх' ?>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
