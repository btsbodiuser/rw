<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');
$db       = getDB();

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: ' . url('blog'));
    exit;
}

$stmt = $db->prepare("
    SELECT id, title, title_mn, slug, excerpt, excerpt_mn, body, body_mn, image, published_at, created_at
    FROM blog_posts
    WHERE slug = ? AND is_published = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$title = $post['title_mn'] ?: $post['title'];
$body  = $post['body_mn']  ?: ($post['body'] ?: '');
$img   = $post['image']    ? fixImageUrl($post['image']) : null;
$when  = $post['published_at'] ?: $post['created_at'];

// Related posts — three most recent besides this one.
try {
    $rel = $db->prepare("
        SELECT title_mn, title, slug, image, published_at, created_at
        FROM blog_posts
        WHERE is_published = 1 AND id != ?
        ORDER BY sort_order ASC, COALESCE(published_at, created_at) DESC
        LIMIT 3
    ");
    $rel->execute([$post['id']]);
    $related = $rel->fetchAll();
} catch (Throwable) { $related = []; }

$page_title  = $title . ' — ' . $siteName;
$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-post-hero { aspect-ratio: 16 / 7; background: #f7f9fc; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
.rw-post-hero img { width: 100%; height: 100%; object-fit: cover; }
.rw-post-body { line-height: 1.8; color: #374151; font-size: 16px; }
.rw-post-body h2, .rw-post-body h3, .rw-post-body h4 { color: #0a0a0a; margin-top: 32px; margin-bottom: 12px; }
.rw-post-body p { margin-bottom: 16px; }
.rw-post-body a { color: var(--color-primary, #00B7FF); }
.rw-post-body img { max-width: 100%; height: auto; border-radius: 8px; margin: 16px 0; }
.rw-post-body ul, .rw-post-body ol { margin: 12px 0 16px; padding-left: 24px; }
.rw-post-body blockquote { border-left: 4px solid var(--color-primary, #00B7FF); padding: 8px 20px; margin: 16px 0; color: #4b5563; background: #f9fafb; border-radius: 4px; }
.rw-related-card { background:#fff; border:1px solid #eef1f5; border-radius:10px; overflow:hidden; }
.rw-related-img { aspect-ratio: 16/10; background:#f7f9fc; }
.rw-related-img img { width:100%; height:100%; object-fit: cover; }
</style>
CSS;

require __DIR__ . '/includes/header.php';
?>

<div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
    <div class="container">
        <div class="rbt-breadcrumb-inner text-left">
            <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                <li class="rbt-breadcrumb-item"><a href="<?= h(url('blog')) ?>">Мэдээ, нийтлэл</a></li>
                <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                <li class="rbt-breadcrumb-item active"><?= h(mb_strimwidth($title, 0, 60, '…')) ?></li>
            </ul>
        </div>
    </div>
</div>

<div class="rbt-component-area rbt-section-gapBottom rbt-bg-color-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <p class="text-muted b4 mb--8"><i class="fa-regular fa-calendar mr--4"></i> <?= h(date('Y.m.d', strtotime($when))) ?></p>
                <h1 class="title h3 mb--16"><?= h($title) ?></h1>
                <?php if ($img): ?>
                <div class="rw-post-hero"><img src="<?= h($img) ?>" alt="<?= h($title) ?>"></div>
                <?php endif; ?>
                <div class="rw-post-body">
                    <?= $body /* stored HTML from admin */ ?>
                </div>

                <div class="mt--32 pt--24 border-top">
                    <a href="<?= h(url('blog')) ?>" class="rbt-btn rbt-btn-border rbt-btn-sm"><i class="fa-regular fa-arrow-left mr--4"></i> Бүх нийтлэл</a>
                </div>

                <?php if ($related): ?>
                <h3 class="title h5 mt--40 mb--16">Бусад нийтлэл</h3>
                <div class="row row--16">
                    <?php foreach ($related as $r):
                        $rTitle = $r['title_mn'] ?: $r['title'];
                        $rImg   = $r['image'] ? fixImageUrl($r['image']) : fixImageUrl(null);
                        $rUrl   = url('blog/' . urlencode($r['slug']));
                    ?>
                    <div class="col-md-4 col-6 mt--16">
                        <div class="rw-related-card">
                            <a href="<?= h($rUrl) ?>" class="rw-related-img d-block"><img src="<?= h($rImg) ?>" alt="<?= h($rTitle) ?>" loading="lazy"></a>
                            <div class="p--16">
                                <p class="b3 mb-0"><a href="<?= h($rUrl) ?>" class="rbt-text-color-heading"><?= h(mb_strimwidth($rTitle, 0, 60, '…')) ?></a></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
