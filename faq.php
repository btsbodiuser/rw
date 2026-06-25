<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Асуулт & Хариулт';

// Fetch FAQs from backend
$faqs = [];
try {
    $apiUrl = 'http://localhost' . getBaseUrl() . 'backend/api/faqs.php';
    $ctx    = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $raw    = @file_get_contents($apiUrl, false, $ctx);
    if ($raw) {
        $json = json_decode($raw, true);
        if (!empty($json['faqs']) && is_array($json['faqs'])) {
            $faqs = $json['faqs'];
        } elseif (is_array($json) && isset($json[0]['question'])) {
            $faqs = $json;
        }
    }
} catch (Throwable $e) {
    $faqs = [];
}

// Static fallback FAQs if DB is empty
if (empty($faqs)) {
    $faqs = [
        ['id' => 1, 'category' => 'Хүргэлт', 'question' => 'Хүргэлт хэдэн хоногт ирдэг вэ?', 'answer' => 'Улаанбаатар хот доторх хүргэлт 1-2 ажлын өдөрт хийгддэг. Орон нутагт 3-5 ажлын өдөр шаардлагатай.'],
        ['id' => 2, 'category' => 'Хүргэлт', 'question' => 'Хүргэлтийн төлбөр хэд вэ?', 'answer' => 'Улаанбаатар хотод 5,000₮, орон нутагт 10,000₮ байна. 100,000₮-аас дээш захиалгад үнэгүй хүргэлт хийгддэг.'],
        ['id' => 3, 'category' => 'Хүргэлт', 'question' => 'Захиалгаа яаж хянах вэ?', 'answer' => 'Захиалгын баталгаажуулалтын имэйлд захиалгын дугаар ирсэн байгаа. Тэр дугаараар "Захиалга хянах" хэсэгт хайлт хийнэ үү.'],
        ['id' => 4, 'category' => 'Буцаалт', 'question' => 'Бараа буцааж болох уу?', 'answer' => 'Тиймээ. Бараа хүлээж авснаас хойш 7 хоногийн дотор буцааж болно. Бараа нь гэмтэлгүй, анхны савлагаатай байх ёстой.'],
        ['id' => 5, 'category' => 'Буцаалт', 'question' => 'Буцаасан мөнгөө хэзээ авах вэ?', 'answer' => 'Буцаалт баталгаажсанаас хойш 3-5 ажлын өдрийн дотор мөнгийг буцааж олгодог.'],
        ['id' => 6, 'category' => 'Төлбөр', 'question' => 'Ямар төлбөрийн аргууд байдаг вэ?', 'answer' => 'Бид QPay, монгол банкуудын шилжүүлгийг хүлээн авдаг. Мөн хүргэлтийн үеэр мөнгөн төлбөр хийж болно.'],
        ['id' => 7, 'category' => 'Төлбөр', 'question' => 'Урьдчилсан захиалгад хэдэн хувь төлөх вэ?', 'answer' => 'Урьдчилсан захиалгад нийт дүнгийн 30%-ийг урьдчилж төлнө. Үлдсэн дүнг бараа ирсний дараа төлнө.'],
        ['id' => 8, 'category' => 'Бүртгэл', 'question' => 'Бүртгэлгүйгээр захиалга өгч болох уу?', 'answer' => 'Тиймээ, та бүртгэлгүйгээр захиалга өгч болно. Гэхдээ бүртгэлтэй бол захиалгуудаа нэг дор харах, хялбар дахин захиалах боломжтой.'],
    ];
}

// Group by category
$grouped = [];
foreach ($faqs as $faq) {
    $cat = $faq['category'] ?? 'Ерөнхий';
    $grouped[$cat][] = $faq;
}

require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Асуулт & Хариулт</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">FAQ</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- FAQ -->
        <section class="flat-spacing">
            <div class="container">
                <div class="row">
                    <!-- FAQ Content -->
                    <div class="col-lg-9">
                        <ul class="faq-list">
                        <?php $catIndex = 0; foreach ($grouped as $category => $items): ?>
                            <?php $catSlug = 'faq-cat-' . $catIndex; ?>
                            <li class="faq-item" id="<?= htmlspecialchars($catSlug) ?>">
                                <h2 class="faq_title"><?= htmlspecialchars($category) ?></h2>
                                <div class="faq_wrap" id="<?= htmlspecialchars($catSlug) ?>-wrap">
                                <?php foreach ($items as $idx => $faq): ?>
                                    <?php $faqId = 'faq-' . $catIndex . '-' . $idx; ?>
                                    <div class="accordion-faq accor-mn-pl">
                                        <div class="accordion-title collapsed"
                                             data-bs-target="#<?= $faqId ?>"
                                             role="button"
                                             data-bs-toggle="collapse"
                                             aria-expanded="false"
                                             aria-controls="<?= $faqId ?>">
                                            <span class="text h5"><?= ($idx + 1) ?>. <?= htmlspecialchars($faq['question']) ?></span>
                                            <span class="icon">
                                                <span class="ic-accordion-custom"></span>
                                            </span>
                                        </div>
                                        <div id="<?= $faqId ?>" class="collapse"
                                             data-bs-parent="#<?= htmlspecialchars($catSlug) ?>-wrap">
                                            <div class="accordion-body">
                                                <p class="h6"><?= nl2br(htmlspecialchars($faq['answer'])) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </li>
                        <?php $catIndex++; endforeach; ?>
                        </ul>
                    </div>

                    <!-- Sidebar: category nav -->
                    <div class="col-lg-3 d-none d-lg-block">
                        <div class="sidebar-content-wrap sticky-top" style="top:100px;">
                            <h5 class="fw-semibold mb-3">Ангилалууд</h5>
                            <ul class="footer-menu-list">
                                <?php $ci = 0; foreach (array_keys($grouped) as $cat): ?>
                                <li>
                                    <a href="#faq-cat-<?= $ci ?>" class="link h6">
                                        <i class="icon icon-caret-right me-1"></i>
                                        <?= htmlspecialchars($cat) ?>
                                    </a>
                                </li>
                                <?php $ci++; endforeach; ?>
                            </ul>

                            <div class="mt-4 p-3" style="background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb;">
                                <h6 class="fw-semibold mb-2">Тусламж хэрэгтэй юу?</h6>
                                <p class="h6 text-main mb-3">Хариулт олдсонгүй бол бидэнтэй холбоо бариарай.</p>
                                <a href="<?= url('contact.php') ?>" class="tf-btn type-small animate-btn w-100 text-center">Холбоо барих</a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <!-- /FAQ -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
