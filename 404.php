<?php
require_once __DIR__ . '/includes/config.php';

http_response_code(404);
$siteName    = s('site_name', 'Runners World');
$page_title  = '404 — ' . $siteName;
$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-404-card { text-align:center; padding: 64px 32px; background:#fff; border:1px solid #eef1f5; border-radius:12px; }
.rw-404-code {
    font-family: 'Oswald', 'Impact', sans-serif;
    font-size: 140px; font-weight: 700; line-height: 1;
    background: linear-gradient(135deg, #00B7FF 0%, #0284C7 100%);
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent; color: transparent;
    margin: 0;
}
.rw-404-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
</style>
CSS;

require __DIR__ . '/includes/header.php';
?>

<div class="rbt-component-area rbt-section-gap rbt-bg-color-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="rw-404-card">
                    <p class="rw-404-code">404</p>
                    <h2 class="title h4 mt--16">Хуудас олдсонгүй</h2>
                    <p class="text-muted b3 mt--12">Таны хайсан хуудас олдсонгүй эсвэл шилжсэн байж болзошгүй.</p>
                    <div class="rw-404-actions">
                        <a href="<?= h($urlHome) ?>" class="rbt-btn"><i class="fa-regular fa-house mr--4"></i> Нүүр хуудас</a>
                        <a href="<?= h($urlShop) ?>" class="rbt-btn rbt-btn-border"><i class="fa-regular fa-bag-shopping mr--4"></i> Дэлгүүр</a>
                        <a href="<?= h(url('contact')) ?>" class="rbt-btn rbt-btn-border"><i class="fa-regular fa-envelope mr--4"></i> Холбогдох</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
