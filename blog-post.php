<?php
require_once __DIR__ . '/includes/config.php';

// Slug from clean URL rewrite or query string
$slug = $_GET['slug'] ?? '';
if (!$slug) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('~/blog/([^/?]+)~', $uri, $m)) {
        $slug = urldecode($m[1]);
    }
}

$db   = getDB();
$post = null;

try {
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
} catch (Throwable $e) {}

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Нийтлэл олдсонгүй';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:80px 0;text-align:center;"><h2>Нийтлэл олдсонгүй</h2><p><a href="' . url() . '" class="link">Нүүр хуудас руу буцах</a></p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Related posts
$related = [];
try {
    $stmt = $db->prepare("SELECT id, title_mn, title, slug, image, published_at, excerpt_mn FROM blog_posts WHERE is_published = 1 AND id != ? ORDER BY published_at DESC LIMIT 3");
    $stmt->execute([$post['id']]);
    $related = $stmt->fetchAll();
} catch (Throwable $e) {}

// Prev / next
$prevPost = $nextPost = null;
try {
    $stmt = $db->prepare("SELECT id, title_mn, title, slug, image FROM blog_posts WHERE is_published = 1 AND published_at < ? ORDER BY published_at DESC LIMIT 1");
    $stmt->execute([$post['published_at']]);
    $prevPost = $stmt->fetch() ?: null;

    $stmt = $db->prepare("SELECT id, title_mn, title, slug, image FROM blog_posts WHERE is_published = 1 AND published_at > ? ORDER BY published_at ASC LIMIT 1");
    $stmt->execute([$post['published_at']]);
    $nextPost = $stmt->fetch() ?: null;
} catch (Throwable $e) {}

$page_title       = $post['title_mn'] ?: $post['title'];
$page_description = $post['excerpt_mn'] ?: $post['excerpt'] ?: '';
$coverImg         = $post['image'] ? fixImageUrl($post['image']) : assetUrl('images/blog/blog-1.jpg');
$pubDate          = $post['published_at'] ? date('Y оны m сарын d', strtotime($post['published_at'])) : '';
$bodyHtml         = $post['body_mn'] ?: $post['body'] ?: '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Title -->
<section class="page-title-blog">
    <div class="bg-image">
        <div class="parallaxie" style="background-image: url('<?= htmlspecialchars($coverImg) ?>')"></div>
    </div>
    <div class="container position-relative z-5">
        <div class="content">
            <?php if ($pubDate): ?>
            <div class="entry_tag name-tag h6"><?= htmlspecialchars($pubDate) ?></div>
            <?php endif; ?>
            <h1 class="heading"><?= htmlspecialchars($page_title) ?></h1>
            <?php if ($page_description): ?>
            <div class="entry_author">
                <span class="h6"><?= htmlspecialchars(mb_strimwidth($page_description, 0, 120, '…')) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<!-- /Page Title -->

<!-- Blog Detail -->
<section class="s-blog-detail flat-spacing">
    <div class="container">
        <div class="row flex-wrap-reverse">

            <!-- Sidebar: date + share -->
            <div class="col-xl-3">
                <div class="blog-detail_info mt-5 mt-xl-0 sticky-top" style="top:100px;">
                    <?php if ($pubDate): ?>
                    <div class="date-post">
                        <p class="title-label h6">Огноо</p>
                        <h6 class="entry_date"><?= htmlspecialchars($pubDate) ?></h6>
                    </div>
                    <?php endif; ?>
                    <div class="share-post">
                        <p class="title-label h6">Хуваалцах</p>
                        <ul class="tf-social-icon">
                            <li>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(url('blog/' . $post['slug'])) ?>"
                                   target="_blank" rel="noopener" class="social-facebook">
                                    <span class="icon"><i class="icon-fb"></i></span>
                                </a>
                            </li>
                            <li>
                                <a href="https://www.instagram.com/" target="_blank" rel="noopener" class="social-instagram">
                                    <span class="icon"><i class="icon-instagram-logo"></i></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <a href="<?= url() ?>" class="link h6 text-main">← Нүүр хуудас</a>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-xl-9">
                <div class="blog-detail_content tf-grid-layout">

                    <?php if ($bodyHtml): ?>
                    <!-- WYSIWYG body -->
                    <div class="article-body">
                        <?= $bodyHtml ?>
                    </div>

                    <?php else: ?>
                    <!-- Fallback: excerpt only -->
                    <?php if ($page_description): ?>
                    <div class="box-text">
                        <p class="h4 text-black"><?= htmlspecialchars($page_description) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <span class="br-line mt-4"></span>

                    <!-- Prev / Next navigation -->
                    <?php if ($prevPost || $nextPost): ?>
                    <div class="group-direc">
                        <?php if ($prevPost):
                            $prevImg = $prevPost['image'] ? fixImageUrl($prevPost['image']) : assetUrl('images/blog/blog-1.jpg');
                        ?>
                        <a href="<?= url('blog/' . htmlspecialchars($prevPost['slug'])) ?>" class="btn-direc prev link">
                            <img src="<?= htmlspecialchars($prevImg) ?>" data-src="<?= htmlspecialchars($prevImg) ?>" alt="" class="lazyload">
                            <div class="content">
                                <p class="fw-medium text-uppercase">Өмнөх нийтлэл</p>
                                <p class="name-post h6"><?= htmlspecialchars($prevPost['title_mn'] ?: $prevPost['title']) ?></p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if ($prevPost && $nextPost): ?>
                        <span class="br-line"></span>
                        <?php endif; ?>
                        <?php if ($nextPost):
                            $nextImg = $nextPost['image'] ? fixImageUrl($nextPost['image']) : assetUrl('images/blog/blog-1.jpg');
                        ?>
                        <a href="<?= url('blog/' . htmlspecialchars($nextPost['slug'])) ?>" class="btn-direc next link">
                            <div class="content">
                                <p class="fw-medium text-uppercase">Дараагийн нийтлэл</p>
                                <p class="name-post h6"><?= htmlspecialchars($nextPost['title_mn'] ?: $nextPost['title']) ?></p>
                            </div>
                            <img src="<?= htmlspecialchars($nextImg) ?>" data-src="<?= htmlspecialchars($nextImg) ?>" alt="" class="lazyload">
                        </a>
                        <?php endif; ?>
                    </div>
                    <span class="br-line"></span>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</section>
<!-- /Blog Detail -->

<?php if (!empty($related)): ?>
<!-- Related Posts -->
<section class="flat-spacing pt-0">
    <div class="container">
        <div class="sect-title">
            <h4 class="fw-medium">Холбоотой нийтлэлүүд</h4>
        </div>
        <div dir="ltr" class="swiper tf-swiper"
             data-preview="3" data-tablet="2" data-mobile-sm="1" data-mobile="1"
             data-space-lg="30" data-space-md="20" data-space="15"
             data-pagination="1" data-pagination-sm="1" data-pagination-md="2" data-pagination-lg="3">
            <div class="swiper-wrapper">
                <?php foreach ($related as $r):
                    $rImg   = $r['image'] ? fixImageUrl($r['image']) : assetUrl('images/blog/blog-1.jpg');
                    $rTitle = htmlspecialchars($r['title_mn'] ?: $r['title']);
                    $rDate  = $r['published_at'] ? date('Y.m.d', strtotime($r['published_at'])) : '';
                ?>
                <div class="swiper-slide">
                    <div class="article-blog hover-img4">
                        <div class="blog-image">
                            <a href="<?= url('blog/' . htmlspecialchars($r['slug'])) ?>" class="entry_image img-style4">
                                <img src="<?= $rImg ?>" data-src="<?= $rImg ?>" alt="<?= $rTitle ?>" class="lazyload">
                            </a>
                        </div>
                        <div class="blog-content p-0">
                            <?php if ($rDate): ?>
                            <div class="entry_tag"><span class="name-tag h6"><?= $rDate ?></span></div>
                            <?php endif; ?>
                            <a href="<?= url('blog/' . htmlspecialchars($r['slug'])) ?>" class="entry_name link h4"><?= $rTitle ?></a>
                            <?php if ($r['excerpt_mn']): ?>
                            <p class="h6 text-main mt-1"><?= htmlspecialchars(mb_strimwidth($r['excerpt_mn'], 0, 80, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sw-dot-default tf-sw-pagination"></div>
        </div>
    </div>
</section>
<!-- /Related Posts -->
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
