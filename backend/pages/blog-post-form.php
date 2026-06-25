<?php
$pageTitle  = isset($_GET['id']) ? 'Нийтлэл засах' : 'Нийтлэл нэмэх';
$extra_head = '
<link rel="stylesheet" href="https://unpkg.com/quill@1.3.7/dist/quill.snow.css">
<style>
    .ql-toolbar.ql-snow { border-radius: 0.5rem 0.5rem 0 0; border-color: #d1d5db; background: #f9fafb; }
    .ql-container.ql-snow { border-radius: 0 0 0.5rem 0.5rem; border-color: #d1d5db; font-size: 14px; }
    .ql-editor { min-height: 280px; }
    .ql-editor.ql-blank::before { color: #9ca3af; font-style: normal; }
</style>';

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$post = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) {
        setFlash('error', 'Нийтлэл олдсонгүй.');
        header('Location: index.php?page=blog-posts');
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

    $titleMn     = trim($_POST['title_mn'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $excerptMn   = trim($_POST['excerpt_mn'] ?? '');
    $excerpt     = trim($_POST['excerpt'] ?? '');
    $bodyMn      = trim($_POST['body_mn'] ?? '');
    $body        = trim($_POST['body'] ?? '');
    $image       = trim($_POST['image'] ?? '');
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $publishedAt = trim($_POST['published_at'] ?? '');

    if ($titleMn === '') $errors[] = 'Монгол гарчиг шаардлагатай.';
    if (mb_strlen($titleMn) > 200) $errors[] = 'Монгол гарчиг 200 тэмдэгтээс хэтэрч болохгүй.';
    if ($slug === '') $errors[] = 'Slug шаардлагатай.';
    if (mb_strlen($slug) > 200) $errors[] = 'Slug 200 тэмдэгтээс хэтэрч болохгүй.';

    $slug = preg_replace('/[^a-z0-9-]/', '-', mb_strtolower($slug));
    $slug = preg_replace('/-+/', '-', trim($slug, '-'));

    if (empty($errors)) {
        $slugCheck = $db->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $slugCheck->execute([$slug, $id ?: 0]);
        if ($slugCheck->fetch()) {
            $errors[] = 'Slug аль хэдийн ашиглагдсан байна. Өөр slug ашиглана уу.';
        }
    }

    if (empty($errors)) {
        $pubAt = ($publishedAt !== '') ? date('Y-m-d H:i:s', strtotime($publishedAt)) : null;
        $bodyMnVal = ($bodyMn !== '' && $bodyMn !== '<p><br></p>') ? $bodyMn : null;
        $bodyVal   = ($body   !== '' && $body   !== '<p><br></p>') ? $body   : null;

        if ($id) {
            $stmt = $db->prepare("UPDATE blog_posts SET title_mn=?, title=?, slug=?, excerpt_mn=?, excerpt=?, body_mn=?, body=?, image=?, sort_order=?, is_published=?, published_at=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$titleMn, $title ?: null, $slug, $excerptMn ?: null, $excerpt ?: null, $bodyMnVal, $bodyVal, $image ?: null, $sortOrder, $isPublished, $pubAt, $id]);
            setFlash('success', 'Нийтлэл шинэчлэгдлээ.');
        } else {
            $stmt = $db->prepare("INSERT INTO blog_posts (title_mn, title, slug, excerpt_mn, excerpt, body_mn, body, image, sort_order, is_published, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titleMn, $title ?: null, $slug, $excerptMn ?: null, $excerpt ?: null, $bodyMnVal, $bodyVal, $image ?: null, $sortOrder, $isPublished, $pubAt]);
            setFlash('success', 'Нийтлэл нэмэгдлээ.');
        }
        header('Location: index.php?page=blog-posts');
        exit;
    }

    $post = [
        'title_mn'     => $titleMn,
        'title'        => $title,
        'slug'         => $slug,
        'excerpt_mn'   => $excerptMn,
        'excerpt'      => $excerpt,
        'body_mn'      => $bodyMn,
        'body'         => $body,
        'image'        => $image,
        'sort_order'   => $sortOrder,
        'is_published' => $isPublished,
        'published_at' => $publishedAt,
    ];
}

$_pubAtRaw = $post['published_at'] ?? null;
$defaultPublishedAt = $_pubAtRaw && strtotime($_pubAtRaw)
    ? date('Y-m-d\TH:i', strtotime($_pubAtRaw))
    : date('Y-m-d\TH:i');

require_once __DIR__ . '/../includes/header.php';

$csrfToken = generateCSRFToken();
$basePath  = getBasePath();
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="index.php?page=blog-posts" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Блог руу буцах
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="blog-form" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5"
          x-data="blogPostForm(<?= htmlspecialchars(json_encode([
              'csrfToken' => $csrfToken,
              'image'     => $post['image'] ?? '',
              'basePath'  => $basePath,
          ]), ENT_QUOTES) ?>)">
        <?= csrfField() ?>

        <!-- Titles -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Монгол гарчиг *</label>
            <input type="text" name="title_mn" id="title_mn" value="<?= e($post['title_mn'] ?? '') ?>"
                   required maxlength="200"
                   @input="generateSlug($event.target.value)"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Англи / оригинал гарчиг</label>
            <input type="text" name="title" value="<?= e($post['title'] ?? '') ?>" maxlength="200"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <!-- Slug -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
            <input type="text" name="slug" id="blog-slug" x-model="slug" required maxlength="200"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono text-xs">
            <p class="text-xs text-gray-400 mt-1">URL дахь нэр. Зөвхөн a-z, 0-9, - зөвшөөрнө. Монгол гарчигаас автоматаар үүснэ.</p>
        </div>

        <!-- Excerpt -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Монгол товч тайлбар</label>
            <textarea name="excerpt_mn" rows="3" placeholder="Нийтлэлийн товч агуулга..."
                      class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-vertical"><?= e($post['excerpt_mn'] ?? '') ?></textarea>
        </div>

        <!-- Body WYSIWYG -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Нийтлэлийн агуулга</label>
            <input type="hidden" name="body_mn" id="body_mn_hidden">
            <div id="body_mn_editor"></div>
        </div>

        <!-- Cover Image -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Нүүр зураг</label>
            <input type="hidden" name="image" x-model="image">

            <template x-if="imagePreview">
                <div class="mb-3">
                    <img :src="imagePreview" alt="Preview" class="w-40 h-24 object-cover rounded-lg border border-gray-200">
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
            <p class="text-xs text-gray-400 mt-1">Зөвлөмж: 800×500px эсвэл 16:9 харьцаатай зураг.</p>
        </div>

        <!-- Meta -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дараалал</label>
                <input type="number" name="sort_order" value="<?= (int)($post['sort_order'] ?? 0) ?>" min="0"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <p class="text-xs text-gray-400 mt-1">Бага тоо урагш харагдана.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Нийтлэгдсэн огноо</label>
                <input type="datetime-local" name="published_at" value="<?= e($defaultPublishedAt) ?>"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>

        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_published" value="1" <?= ($post['is_published'] ?? 0) ? 'checked' : '' ?>
                   class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="text-sm font-medium text-gray-700">Нийтлэгдсэн (сайтад харагдана)</span>
        </label>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="index.php?page=blog-posts" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Болих</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <?= $id ? 'Шинэчлэх' : 'Үүсгэх' ?>
            </button>
        </div>
    </form>
</div>

<script src="https://unpkg.com/quill@1.3.7/dist/quill.min.js"></script>
<script>
// ── Quill WYSIWYG ──
const quillBodyMn = new Quill('#body_mn_editor', {
    theme: 'snow',
    placeholder: 'Нийтлэлийн агуулга энд бичнэ...',
    modules: {
        toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'link'],
            [{ align: [] }],
            ['clean']
        ]
    }
});

const initialBodyMn = <?= json_encode($post['body_mn'] ?? '') ?>;
if (initialBodyMn) {
    quillBodyMn.clipboard.dangerouslyPasteHTML(initialBodyMn);
}

document.getElementById('blog-form').addEventListener('submit', function () {
    const html = quillBodyMn.root.innerHTML;
    document.getElementById('body_mn_hidden').value = (html === '<p><br></p>') ? '' : html;
});

// ── Alpine component ──
function blogPostForm(config) {
    return {
        csrfToken: config.csrfToken,
        image: config.image || '',
        uploading: false,
        slug: <?= json_encode($post['slug'] ?? '') ?>,
        slugManuallyEdited: <?= json_encode(!empty($post['slug'])) ?>,

        get imagePreview() {
            if (!this.image) return null;
            if (this.image.startsWith('http')) return this.image;
            return config.basePath + 'backend/uploads/media/' + this.image;
        },

        generateSlug(titleMn) {
            if (this.slugManuallyEdited && this.slug !== '') return;
            const map = {
                'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'j','з':'z','и':'i',
                'й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t',
                'у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'i','ь':'',
                'э':'e','ю':'yu','я':'ya','ү':'u','ө':'o'
            };
            let s = titleMn.toLowerCase();
            s = s.split('').map(c => map[c] !== undefined ? map[c] : c).join('');
            s = s.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
            this.slug = s;
        },

        async uploadImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.uploading = true;
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', this.csrfToken);
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
