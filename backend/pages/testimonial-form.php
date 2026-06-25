<?php
$pageTitle = isset($_GET['id']) ? 'Сэтгэгдэл засах' : 'Сэтгэгдэл нэмэх';
$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$testimonial = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM testimonials WHERE id = ?");
    $stmt->execute([$id]);
    $testimonial = $stmt->fetch();
    if (!$testimonial) {
        setFlash('error', 'Сэтгэгдэл олдсонгүй.');
        header('Location: index.php?page=testimonials');
        exit;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $customerName  = trim($_POST['customer_name'] ?? '');
    $customerAvatar = trim($_POST['customer_avatar'] ?? '');
    $rating        = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $title         = trim($_POST['title'] ?? '');
    $body          = trim($_POST['body'] ?? '');
    $sortOrder     = (int)($_POST['sort_order'] ?? 0);
    $isActive      = isset($_POST['is_active']) ? 1 : 0;

    if ($customerName === '') $errors[] = 'Нэр шаардлагатай.';
    if (mb_strlen($customerName) > 100) $errors[] = 'Нэр 100 тэмдэгтээс хэтэрч болохгүй.';
    if ($body === '') $errors[] = 'Сэтгэгдлийн текст шаардлагатай.';

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE testimonials SET customer_name=?, customer_avatar=?, rating=?, title=?, body=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->execute([$customerName, $customerAvatar ?: null, $rating, $title ?: null, $body, $sortOrder, $isActive, $id]);
            setFlash('success', 'Сэтгэгдэл шинэчлэгдлээ.');
        } else {
            $stmt = $db->prepare("INSERT INTO testimonials (customer_name, customer_avatar, rating, title, body, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customerName, $customerAvatar ?: null, $rating, $title ?: null, $body, $sortOrder, $isActive]);
            setFlash('success', 'Сэтгэгдэл нэмэгдлээ.');
        }
        header('Location: index.php?page=testimonials');
        exit;
    }

    $testimonial = [
        'customer_name'   => $customerName,
        'customer_avatar' => $customerAvatar,
        'rating'          => $rating,
        'title'           => $title,
        'body'            => $body,
        'sort_order'      => $sortOrder,
        'is_active'       => $isActive,
    ];
}

require_once __DIR__ . '/../includes/header.php';

$csrfToken = generateCSRFToken();
$basePath  = getBasePath();
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=testimonials" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Сэтгэгдэл рүү буцах
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4"
          x-data="testimonialForm(<?= htmlspecialchars(json_encode([
              'csrfToken' => $csrfToken,
              'avatar'    => $testimonial['customer_avatar'] ?? '',
              'basePath'  => $basePath,
          ]), ENT_QUOTES) ?>)">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Нэр *</label>
            <input type="text" name="customer_name" value="<?= e($testimonial['customer_name'] ?? '') ?>"
                   required maxlength="100"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Аватар зураг</label>
            <!-- Hidden input stores the filename/url -->
            <input type="hidden" name="customer_avatar" x-model="avatar">

            <!-- Image preview -->
            <template x-if="avatarPreview">
                <div class="mb-3">
                    <img :src="avatarPreview" alt="Avatar preview" class="w-16 h-16 rounded-full object-cover border border-gray-200">
                </div>
            </template>

            <div class="flex gap-3 items-center flex-wrap">
                <!-- File upload button -->
                <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                       :class="{'opacity-50 pointer-events-none': uploading}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span x-text="uploading ? 'Хуулж байна...' : 'Зураг сонгох'"></span>
                    <input type="file" accept="image/*" class="hidden" @change="uploadAvatar($event)" :disabled="uploading">
                </label>

                <!-- Direct URL input -->
                <span class="text-xs text-gray-400">эсвэл URL оруулах:</span>
                <input type="text" placeholder="https://..." x-model="avatarUrl"
                       @input="avatar = avatarUrl"
                       class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">

                <template x-if="avatar">
                    <button type="button" @click="clearAvatar()" class="text-xs text-red-500 hover:text-red-700">Арилгах</button>
                </template>
            </div>
            <p class="text-xs text-gray-400 mt-1">Зөвлөмж: 200×200px дугуй зураг.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Үнэлгээ</label>
            <select name="rating" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                    <option value="<?= $s ?>" <?= (int)($testimonial['rating'] ?? 5) === $s ? 'selected' : '' ?>>
                        <?= $s ?> од <?= str_repeat('★', $s) . str_repeat('☆', 5 - $s) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Гарчиг</label>
            <input type="text" name="title" value="<?= e($testimonial['title'] ?? '') ?>" maxlength="200"
                   placeholder="Жишээ: Маш сайн бүтээгдэхүүн!"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Сэтгэгдэл *</label>
            <textarea name="body" required rows="5"
                      class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-vertical"><?= e($testimonial['body'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Дараалал</label>
            <input type="number" name="sort_order" value="<?= (int)($testimonial['sort_order'] ?? 0) ?>" min="0"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <p class="text-xs text-gray-400 mt-1">Бага тоо урагш харагдана.</p>
        </div>

        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= ($testimonial['is_active'] ?? 1) ? 'checked' : '' ?>
                   class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="text-sm font-medium text-gray-700">Идэвхтэй (хэрэглэгчид харагдана)</span>
        </label>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="index.php?page=testimonials" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Болих</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <?= $id ? 'Шинэчлэх' : 'Үүсгэх' ?>
            </button>
        </div>
    </form>
</div>

<script>
function testimonialForm(config) {
    return {
        csrfToken: config.csrfToken,
        avatar: config.avatar || '',
        avatarUrl: config.avatar && config.avatar.startsWith('http') ? config.avatar : '',
        uploading: false,

        get avatarPreview() {
            if (!this.avatar) return null;
            if (this.avatar.startsWith('http')) return this.avatar;
            return config.basePath + 'backend/uploads/media/' + this.avatar;
        },

        clearAvatar() {
            this.avatar = '';
            this.avatarUrl = '';
        },

        async uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.uploading = true;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', this.csrfToken);

            try {
                const res = await fetch('index.php?page=media-upload', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success && data.media) {
                    this.avatar = data.media.filename;
                    this.avatarUrl = '';
                } else {
                    alert(data.error || 'Зураг хуулахад алдаа гарлаа');
                }
            } catch (e) {
                alert('Зураг хуулахад алдаа гарлаа');
            }

            this.uploading = false;
            event.target.value = '';
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
