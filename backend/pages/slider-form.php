<?php
$pageTitle = isset($_GET['id']) ? 'Баннер засах' : 'Баннер нэмэх';
$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$sl = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM sliders WHERE id = ?");
    $stmt->execute([$id]);
    $sl = $stmt->fetch();
    if (!$sl) {
        setFlash('error', 'Слайд олдсонгүй.');
        header('Location: index.php?page=sliders');
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

    $titleMn    = trim($_POST['title_mn']   ?? '');
    $subtitleMn = trim($_POST['subtitle_mn'] ?? '');
    $btnText    = trim($_POST['btn_text']   ?? '');
    $btnUrl     = trim($_POST['btn_url']    ?? '');
    $image      = trim($_POST['image']      ?? '');
    $textDark   = isset($_POST['text_dark']) ? 1 : 0;
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;

    if ($image === '') $errors[] = 'Зураг шаардлагатай.';
    if (!$locationId)  $errors[] = 'Байршил сонгоно уу.';

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE sliders SET location_id=?, title_mn=?, subtitle_mn=?, btn_text=?, btn_url=?, image=?, text_dark=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->execute([$locationId, $titleMn ?: null, $subtitleMn ?: null, $btnText ?: null, $btnUrl ?: null, $image, $textDark, $sortOrder, $isActive, $id]);
            setFlash('success', 'Баннер шинэчлэгдлээ.');
        } else {
            $stmt = $db->prepare("INSERT INTO sliders (location_id, title_mn, subtitle_mn, btn_text, btn_url, image, text_dark, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$locationId, $titleMn ?: null, $subtitleMn ?: null, $btnText ?: null, $btnUrl ?: null, $image, $textDark, $sortOrder, $isActive]);
            setFlash('success', 'Баннер нэмэгдлээ.');
        }
        header('Location: index.php?page=sliders');
        exit;
    }

    $sl = ['location_id'=>$locationId,'title_mn'=>$titleMn,'subtitle_mn'=>$subtitleMn,'btn_text'=>$btnText,'btn_url'=>$btnUrl,'image'=>$image,'text_dark'=>$textDark,'sort_order'=>$sortOrder,'is_active'=>$isActive];
}

// Load locations for the dropdown (only active ones)
$locations = $db->query("SELECT id, slug, label_mn, label_en FROM banner_locations WHERE is_active = 1 ORDER BY sort_order, label_mn")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
$csrfToken = generateCSRFToken();
$basePath  = getBasePath();
?>

<div class="max-w-xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=sliders" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Баннер руу буцах
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
          x-data="sliderForm(<?= htmlspecialchars(json_encode([
              'csrfToken' => $csrfToken,
              'image'     => $sl['image'] ?? '',
              'basePath'  => $basePath,
          ]), ENT_QUOTES) ?>)">
        <?= csrfField() ?>

        <!-- Location -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Байршил *</label>
            <?php if (empty($locations)): ?>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                    Байршил бүртгэгдээгүй байна.
                    <a href="index.php?page=banner-locations" class="font-medium underline">Байршил үүсгэх →</a>
                </div>
            <?php else: ?>
            <select name="location_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Сонгох —</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>" <?= ((int)($sl['location_id'] ?? 0) === (int)$loc['id']) ? 'selected' : '' ?>>
                        <?= e($loc['label_mn']) ?><?php if ($loc['label_en'] && $loc['label_en'] !== $loc['label_mn']): ?> · <?= e($loc['label_en']) ?><?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Баннер аль хэсэгт харагдахыг тодорхойлно. <a href="index.php?page=banner-locations" class="text-blue-600 hover:underline">Байршил удирдах</a></p>
            <?php endif; ?>
        </div>

        <!-- Image upload -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Зураг (дэвсгэр) *</label>
            <input type="hidden" name="image" x-model="image">

            <template x-if="imagePreview">
                <div class="mb-3">
                    <img :src="imagePreview" alt="Preview" class="w-full h-36 object-cover rounded-lg border border-gray-200">
                </div>
            </template>

            <div class="flex gap-3 items-center flex-wrap">
                <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                       :class="{'opacity-50 pointer-events-none': uploading}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span x-text="uploading ? 'Хуулж байна...' : 'Зураг сонгох'"></span>
                    <input type="file" accept="image/*" class="hidden" @change="uploadImage($event)" :disabled="uploading">
                </label>
                <template x-if="image">
                    <button type="button" @click="image = ''" class="text-xs text-red-500 hover:text-red-700">Арилгах</button>
                </template>
            </div>
            <p class="text-xs text-gray-400 mt-1">Зөвлөмж: 1920×800px эсвэл 16:9 хэмжээтэй зураг.</p>
        </div>

        <!-- Title -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Гарчиг</label>
            <input type="text" name="title_mn" value="<?= e($sl['title_mn'] ?? '') ?>" maxlength="200"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <!-- Subtitle -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Дэд гарчиг</label>
            <input type="text" name="subtitle_mn" value="<?= e($sl['subtitle_mn'] ?? '') ?>" maxlength="400"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <!-- Button -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Товчны текст</label>
                <input type="text" name="btn_text" value="<?= e($sl['btn_text'] ?? '') ?>" maxlength="100"
                       placeholder="Дэлгүүр үзэх"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Товчны холбоос</label>
                <input type="text" name="btn_url" value="<?= e($sl['btn_url'] ?? '') ?>" maxlength="500"
                       placeholder="shop.php"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>

        <!-- Options row -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дараалал</label>
                <input type="number" name="sort_order" value="<?= (int)($sl['sort_order'] ?? 0) ?>" min="0"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="flex flex-col gap-3 pt-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="text_dark" value="1" <?= ($sl['text_dark'] ?? 1) ? 'checked' : '' ?>
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Харанхуй текст</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" <?= ($sl['is_active'] ?? 1) ? 'checked' : '' ?>
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Идэвхтэй</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="index.php?page=sliders" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Болих</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <?= $id ? 'Шинэчлэх' : 'Үүсгэх' ?>
            </button>
        </div>
    </form>
</div>

<script>
function sliderForm(config) {
    return {
        image: config.image || '',
        uploading: false,

        get imagePreview() {
            if (!this.image) return null;
            if (this.image.startsWith('http')) return this.image;
            return config.basePath + 'backend/uploads/media/' + this.image;
        },

        async uploadImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.uploading = true;
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', config.csrfToken);
            // Banners need full width — bypass the default 1000px product cap
            formData.append('max_dim', '2400');
            try {
                const res = await fetch('index.php?page=media-upload', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success && data.media) {
                    this.image = data.media.filename;
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
