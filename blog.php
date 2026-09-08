<?php
require_once __DIR__ . '/includes/config.php';

$siteName   = s('site_name', 'Runners World');
$page_title = 'Мэдээ, нийтлэл — ' . $siteName;
$db         = getDB();

$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

try {
    $total = (int)$db->query("SELECT COUNT(*) FROM blog_posts WHERE is_published = 1")->fetchColumn();
    $stmt  = $db->prepare("
        SELECT id, title_mn, title, slug, excerpt_mn, excerpt, image, published_at, created_at
        FROM blog_posts
        WHERE is_published = 1
        ORDER BY sort_order ASC, COALESCE(published_at, created_at) DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (Throwable) { $posts = []; $total = 0; }

$totalPages = max(1, (int)ceil($total / $perPage));

$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-blog-card { background:#fff; border:1px solid #eef1f5; border-radius:12px; overflow:hidden; transition: transform .15s, box-shadow .15s; height: 100%; display:flex; flex-direction:column; }
.rw-blog-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.rw-blog-img { aspect-ratio: 16 / 10; background: #f7f9fc; overflow: hidden; }
.rw-blog-img img { width: 100%; height: 100%; object-fit: cover; }
.rw-blog-body { padding: 20px; display:flex; flex-direction:column; flex: 1; }
.rw-blog-body h3 { font-size: 17px; margin-bottom: 8px; line-height: 1.4; }
.rw-blog-body h3 a { color: #0a0a0a; }
.rw-blog-body h3 a:hover { color: var(--color-primary, #00B7FF); }
.rw-blog-body .rw-blog-excerpt { color: #6b7280; font-size: 14px; line-height: 1.6; flex: 1; margin-bottom: 12px; }
.rw-blog-meta { font-size: 12px; color: #9ca3af; }
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
                <li class="rbt-breadcrumb-item active">Мэдээ, нийтлэл</li>
            </ul>
            <h1 class="title h3 mt--10">Мэдээ, нийтлэл</h1>
        </div>
    </div>
</div>

<div class="rbt-component-area rbt-section-gapBottom rbt-bg-color-white">
    <div class="container">
        <?php if (!$posts): ?>
        <div class="text-center py-5">
            <i class="fa-regular fa-newspaper" style="font-size:2.5rem;color:#ddd;"></i>
            <p class="mt--16 text-muted mb-0">Одоогоор нийтлэгдсэн нийтлэл алга байна.</p>
        </div>
        <?php else: ?>
        <div class="row row--16">
            <?php foreach ($posts as $p):
                $title = $p['title_mn'] ?: $p['title'];
                $exc   = $p['excerpt_mn'] ?: ($p['excerpt'] ?: '');
                $img   = $p['image'] ? fixImageUrl($p['image']) : fixImageUrl(null);
                $url   = url('blog/' . urlencode($p['slug']));
                $when  = $p['published_at'] ?: $p['created_at'];
            ?>
            <div class="col-lg-4 col-md-6 col-12 mt--24">
                <div class="rw-blog-card">
                    <a href="<?= h($url) ?>" class="rw-blog-img d-block">
                        <img src="<?= h($img) ?>" alt="<?= h($title) ?>" loading="lazy">
                    </a>
                    <div class="rw-blog-body">
                        <h3><a href="<?= h($url) ?>"><?= h($title) ?></a></h3>
                        <?php if ($exc): ?>
                        <p class="rw-blog-excerpt"><?= h(mb_strimwidth($exc, 0, 140, '…')) ?></p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="rw-blog-meta"><i class="fa-regular fa-calendar mr--4"></i><?= h(date('Y.m.d', strtotime($when))) ?></span>
                            <a href="<?= h($url) ?>" class="b4 rbt-text-color-primary">Унших <i class="fa-regular fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt--40">
            <ul class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h(url('blog?page=' . $p)) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
