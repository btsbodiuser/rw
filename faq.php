<?php
require_once __DIR__ . '/includes/config.php';

$siteName   = s('site_name', 'Runners World');
$page_title = 'Түгээмэл асуулт хариулт — ' . $siteName;
$db         = getDB();

try {
    $faqs = $db->query("
        SELECT id, category, question, answer
        FROM faqs
        WHERE is_active = 1
        ORDER BY category ASC, sort_order ASC, id ASC
    ")->fetchAll();
} catch (Throwable) { $faqs = []; }

// Group by category so we can render one accordion per section.
$byCategory = [];
foreach ($faqs as $f) {
    $cat = $f['category'] ?: 'Ерөнхий';
    $byCategory[$cat][] = $f;
}

$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-faq-item { border: 1px solid #eef1f5; border-radius: 10px; margin-bottom: 12px; overflow: hidden; }
.rw-faq-q {
    padding: 16px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;
    background: #fff; font-weight: 600; font-size: 15px;
}
.rw-faq-q .rw-faq-chev { transition: transform .2s; color: #6b7280; }
.rw-faq-item.open .rw-faq-chev { transform: rotate(180deg); color: var(--color-primary, #00B7FF); }
.rw-faq-a { padding: 0 20px; max-height: 0; overflow: hidden; transition: max-height .25s ease, padding .25s ease; color: #4b5563; line-height: 1.7; }
.rw-faq-item.open .rw-faq-a { padding: 0 20px 18px; max-height: 500px; }
.rw-faq-cat-title { font-size: 13px; letter-spacing: 0.05em; text-transform: uppercase; color: #6b7280; margin: 32px 0 12px; }
.rw-faq-cat-title:first-of-type { margin-top: 0; }
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
                <li class="rbt-breadcrumb-item active">Түгээмэл асуулт хариулт</li>
            </ul>
            <h1 class="title h3 mt--10">Түгээмэл асуулт хариулт</h1>
        </div>
    </div>
</div>

<div class="rbt-component-area rbt-section-gapBottom rbt-bg-color-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (!$faqs): ?>
                <div class="text-center py-5">
                    <i class="fa-regular fa-circle-question" style="font-size:2.5rem;color:#ddd;"></i>
                    <p class="mt--16 text-muted mb--16">Одоогоор нийтлэгдсэн асуулт алга байна.</p>
                    <a href="<?= h(url('contact')) ?>" class="rbt-btn rbt-btn-border">Бидэнтэй холбогдох</a>
                </div>
                <?php else: ?>
                    <?php foreach ($byCategory as $catName => $items): ?>
                    <h2 class="rw-faq-cat-title"><?= h($catName) ?></h2>
                    <?php foreach ($items as $f): ?>
                    <div class="rw-faq-item">
                        <div class="rw-faq-q">
                            <span><?= h($f['question']) ?></span>
                            <i class="fa-regular fa-chevron-down rw-faq-chev"></i>
                        </div>
                        <div class="rw-faq-a"><?= nl2br(h($f['answer'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="text-center mt--32">
                    <p class="text-muted mb--8">Асуултынхаа хариултыг олсонгүй юу?</p>
                    <a href="<?= h(url('contact')) ?>" class="rbt-btn rbt-btn-border">Бидэнтэй холбогдох</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.rw-faq-q').forEach(function (q) {
    q.addEventListener('click', function () {
        var item = q.parentElement;
        // Optional: close others in the same section. Simpler: independent accordion — just toggle self.
        item.classList.toggle('open');
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
