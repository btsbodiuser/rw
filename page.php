<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');

// Whitelist of allowed slugs so this can't be pointed at arbitrary settings.
// Each entry: settings-key stem + default title. Admin can edit the two
// settings (title + body) from backend/index.php?page=settings to publish
// real content; until then a placeholder + link to contact renders.
$pages = [
    'about'    => 'Бидний тухай',
    'privacy'  => 'Нууцлалын бодлого',
    'terms'    => 'Үйлчилгээний нөхцөл',
    'shipping' => 'Хүргэлтийн мэдээлэл',
    'return'   => 'Буцаах бодлого',
];

$slug = trim((string)($_GET['slug'] ?? ''));
if (!isset($pages[$slug])) {
    http_response_code(404);
    header('Location: ' . $urlHome);
    exit;
}

$titleKey = 'page_' . $slug . '_title_mn';
$bodyKey  = 'page_' . $slug . '_body_mn';
$pageName = s($titleKey, $pages[$slug]);
$pageBody = trim(s($bodyKey, ''));

$page_title  = $pageName . ' — ' . $siteName;
$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-page-content { background:#fff; border:1px solid #eef1f5; border-radius:12px; padding: 40px; line-height: 1.75; color: #374151; }
.rw-page-content h1, .rw-page-content h2, .rw-page-content h3, .rw-page-content h4 { color:#0a0a0a; margin-top: 24px; margin-bottom: 12px; }
.rw-page-content h2:first-child, .rw-page-content h3:first-child { margin-top: 0; }
.rw-page-content ul, .rw-page-content ol { margin: 12px 0; padding-left: 24px; }
.rw-page-content p { margin-bottom: 12px; }
.rw-page-content a { color: var(--color-primary, #00B7FF); }
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
                <li class="rbt-breadcrumb-item active"><?= h($pageName) ?></li>
            </ul>
            <h1 class="title h3 mt--10"><?= h($pageName) ?></h1>
        </div>
    </div>
</div>

<div class="rbt-component-area rbt-section-gapBottom rbt-bg-color-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="rw-page-content">
                    <?php if ($pageBody !== ''): ?>
                        <?= $pageBody /* stored as trusted HTML by admin */ ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fa-regular fa-file-lines" style="font-size:2.5rem;color:#ddd;"></i>
                            <h3 class="h5 mt--16"><?= h($pageName) ?> — удахгүй</h3>
                            <p class="text-muted mb--16">Энэ хуудсын агуулга удахгүй нийтлэгдэнэ. Мэдээлэл шаардлагатай бол бидэнтэй холбогдоно уу.</p>
                            <a href="<?= h(url('contact')) ?>" class="rbt-btn rbt-btn-border">Бидэнтэй холбогдох</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
