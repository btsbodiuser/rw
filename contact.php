<?php
require_once __DIR__ . '/includes/config.php';

$page_title       = 'Холбоо барих';
$page_description = 'Бидэнтэй холбоо барих';
require_once __DIR__ . '/includes/header.php';

$address = s('address', '');
$email   = s('email', '');
$phone   = s('phone', '');
$hours   = s('business_hours', '');
$mapUrl  = s('map_embed_url', '');
$fb      = s('facebook_url', '');
$ig      = s('instagram_url', '');
$tt      = s('tiktok_url', '');
?>

<!-- Page Title -->
<section class="s-page-title">
    <div class="container">
        <div class="content">
            <h1 class="title-page">Холбоо барих</h1>
            <ul class="breadcrumbs-page">
                <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                <li><h6 class="current-page fw-normal">Холбоо барих</h6></li>
            </ul>
        </div>
    </div>
</section>
<!-- /Page Title -->

<!-- Contact Us -->
<section class="s-contact-us flat-spacing">

    <!-- Map -->
    <div class="wg-map d-flex">
        <?php if ($mapUrl): ?>
        <iframe src="<?= htmlspecialchars($mapUrl) ?>"
                width="100%" height="461" style="border:0;" allowfullscreen="" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
        <?php elseif ($address): ?>
        <iframe
            src="https://maps.google.com/maps?q=<?= urlencode($address) ?>&output=embed"
            width="100%" height="461" style="border:0;" allowfullscreen="" loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"></iframe>
        <?php else: ?>
        <div style="width:100%;height:300px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;">
            <div class="text-center">
                <i class="icon icon-map-pin" style="font-size:3rem;color:#9ca3af;"></i>
                <p class="h6 text-main mt-2">Улаанбаатар, Монгол</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- /Map -->

    <div class="container">
        <div class="row">

            <!-- Left: Store Info -->
            <div class="col-xxl-5 offset-xxl-1 col-lg-6">
                <div class="left-col mb-lg-0">
                    <h3 class="title fw-normal">Манай дэлгүүр</h3>
                    <ul class="store-info-list">
                        <?php if ($address): ?>
                        <li>
                            <p class="h6 text-black fw-medium">Хаяг:</p>
                            <span class="text-main"><?= htmlspecialchars($address) ?></span>
                        </li>
                        <?php endif; ?>
                        <?php if ($email): ?>
                        <li>
                            <p class="h6 text-black fw-medium">И-мэйл:</p>
                            <a href="mailto:<?= htmlspecialchars($email) ?>" class="link text-main"><?= htmlspecialchars($email) ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($phone): ?>
                        <li>
                            <p class="h6 text-black fw-medium">Утас:</p>
                            <a href="tel:<?= htmlspecialchars($phone) ?>" class="link text-main"><?= htmlspecialchars($phone) ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($hours): ?>
                        <li>
                            <p class="h6 text-black fw-medium">Ажиллах цаг:</p>
                            <p class="text-main"><?= nl2br(htmlspecialchars($hours)) ?></p>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($fb || $ig || $tt): ?>
                    <ul class="tf-social-icon mt-4">
                        <?php if ($fb): ?>
                        <li><a href="<?= htmlspecialchars($fb) ?>" target="_blank" rel="noopener" class="social-facebook"><span class="icon"><i class="icon-fb"></i></span></a></li>
                        <?php endif; ?>
                        <?php if ($ig): ?>
                        <li><a href="<?= htmlspecialchars($ig) ?>" target="_blank" rel="noopener" class="social-instagram"><span class="icon"><i class="icon-instagram-logo"></i></span></a></li>
                        <?php endif; ?>
                        <?php if ($tt): ?>
                        <li><a href="<?= htmlspecialchars($tt) ?>" target="_blank" rel="noopener" class="social-tiktok"><span class="icon"><i class="icon-tiktok"></i></span></a></li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Contact Form -->
            <div class="col-xl-5 col-lg-6">
                <div class="right-col">
                    <h3 class="title fw-normal">Холбоо барих</h3>
                    <p class="sub-title text-main-4">
                        Асуулт, санал хүсэлтээ илгээгээрэй. Бид ажлын цагаар аль болох хурдан хариулах болно.
                    </p>

                    <div id="contact-success" style="display:none;padding:14px 18px;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:8px;margin-bottom:20px;">
                        <i class="icon icon-check-circle me-2"></i>
                        Таны мессеж амжилттай илгээгдлээ. Бид тантай удахгүй холбогдох болно.
                    </div>

                    <form class="form-contact style-border" id="contactForm" novalidate>
                        <div class="form-content">
                            <div class="cols tf-grid-layout sm-col-2">
                                <fieldset>
                                    <input id="c_name" type="text" placeholder="Нэр *" required>
                                </fieldset>
                                <fieldset>
                                    <input id="c_email" type="email" placeholder="И-мэйл *" required>
                                </fieldset>
                            </div>
                            <fieldset>
                                <input id="c_phone" type="text" placeholder="Утасны дугаар">
                            </fieldset>
                            <textarea id="c_message" placeholder="Мессеж *" style="height:229px;" required></textarea>
                        </div>
                        <div id="contact-error" class="form_message text-center" style="display:none;color:#991b1b;margin-bottom:12px;font-size:.875rem;"></div>
                        <button type="submit" class="tf-btn btn-fill animate-btn w-100" id="btnContact">
                            <span class="btn-text">ИЛГЭЭХ</span>
                            <span class="btn-loading" style="display:none;">Илгээж байна...</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>
<!-- /Contact Us -->

<?php
$extra_scripts = <<<'JS'
<script>
(function () {
    document.getElementById('contactForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var name    = document.getElementById('c_name').value.trim();
        var email   = document.getElementById('c_email').value.trim();
        var message = document.getElementById('c_message').value.trim();
        var errEl   = document.getElementById('contact-error');
        var btn     = document.getElementById('btnContact');

        errEl.style.display = 'none';

        if (!name || !email || !message) {
            errEl.textContent   = 'Нэр, и-мэйл, мессежийг бөглөнө үү.';
            errEl.style.display = 'block';
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errEl.textContent   = 'И-мэйл хаягаа зөв оруулна уу.';
            errEl.style.display = 'block';
            return;
        }

        btn.querySelector('.btn-text').style.display    = 'none';
        btn.querySelector('.btn-loading').style.display = '';
        btn.disabled = true;

        setTimeout(function () {
            document.getElementById('contactForm').style.display     = 'none';
            document.getElementById('contact-success').style.display = 'block';
        }, 800);
    });
}());
</script>
JS;

require_once __DIR__ . '/includes/footer.php';
?>
