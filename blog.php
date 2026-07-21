<?php
require_once __DIR__ . '/includes/config.php';

// Apache can auto-resolve "/blog/<slug>" to this script (matching the "blog"
// URL segment to blog.php) before the .htaccess clean-URL rule for
// blog-post.php gets a chance. Detect that case and hand off to the actual
// post-detail script, same pattern product.php already uses for /product/<slug>.
if (empty($_GET['slug'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('~/blog/([^/?]+)~', $uri, $m)) {
        $_GET['slug'] = urldecode($m[1]);
        require __DIR__ . '/blog-post.php';
        exit;
    }
}

$page_title       = 'Блог & Мэдээ';
$page_description = s('site_description', '');

$posts = [];
try {
    $posts = getDB()->query("SELECT id, title_mn, title, slug, image, published_at, excerpt_mn FROM blog_posts WHERE is_published = 1 ORDER BY sort_order ASC, published_at DESC")->fetchAll();
} catch (Throwable $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Title -->
<section class="s-page-title parallaxie has-bg" style="background-image: url('<?= assetUrl('images/section/blog.jpg') ?>')">
    <div class="container position-relative z-5">
        <div class="content">
            <h1 class="title-page text-white">Блог & Мэдээ</h1>
            <ul class="breadcrumbs-page">
                <li><a href="<?= url() ?>" class="h6 text-white link">Нүүр</a></li>
                <li class="d-flex"><i class="icon icon-caret-right text-white"></i></li>
                <li><h6 class="current-page fw-normal text-white">Блог</h6></li>
            </ul>
        </div>
    </div>
</section>

<!-- Blog Grid -->
<div class="flat-spacing">
    <div class="container">
        <?php if (empty($posts)): ?>
        <div class="text-center py-5">
            <p class="h5 text-main">Одоогоор нийтлэл байхгүй байна.</p>
        </div>
        <?php else: ?>
        <div class="tf-grid-layout sm-col-2 lg-col-3">
            <?php foreach ($posts as $p):
                $img   = $p['image'] ? fixImageUrl($p['image']) : assetUrl('images/blog/blog-1.jpg');
                $title = htmlspecialchars($p['title_mn'] ?: $p['title']);
                $date  = $p['published_at'] ? date('Y.m.d', strtotime($p['published_at'])) : '';
                $href  = url('blog/' . htmlspecialchars($p['slug']));
            ?>
            <div class="article-blog hover-img4">
                <div class="blog-image">
                    <a href="<?= $href ?>" class="entry_image img-style4">
                        <img src="<?= $img ?>" data-src="<?= $img ?>" alt="<?= $title ?>" class="lazyload">
                    </a>
                    <?php if ($date): ?>
                    <div class="entry_tag">
                        <span class="name-tag h6"><?= $date ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="blog-content">
                    <a href="<?= $href ?>" class="entry_name link h4"><?= $title ?></a>
                    <?php if ($p['excerpt_mn']): ?>
                    <p class="h6 text-main mt-1"><?= htmlspecialchars(mb_strimwidth($p['excerpt_mn'], 0, 100, '…')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
