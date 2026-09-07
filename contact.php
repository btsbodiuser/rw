<?php
require_once __DIR__ . '/includes/config.php';

$page_title     = 'Холбоо барих — ' . s('site_name', 'Runners World');
$contactPhone   = s('phone', '');
$contactEmail   = s('email', '');
$contactAddress = s('address', '');
$contactHours   = s('business_hours', '');
$mapEmbed       = s('map_embed_url', '');
$facebookUrl    = s('facebook_url', '');
$instagramUrl   = s('instagram_url', '');
$tiktokUrl      = s('tiktok_url', '');

$extraStyles = <<<CSS
        /* ── Contact ─────────────────────────────────────── */
        /* Honeypot: kept in the DOM for bots, invisible to humans (position:absolute + off-screen). */
        .rw-hp {
            position: absolute;
            left: -9999px;
            top: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        .rw-form-alert {
            border-radius: 6px;
            padding: 12px 16px;
            font-size: 14px;
            margin-top: 16px;
        }
        .rw-form-alert-success { background: #e6f4ea; color: #137333; border: 1px solid #b6dfc1; }
        .rw-form-alert-error   { background: #fdecea; color: #b3261e; border: 1px solid #f5c2c0; }
        #rw-contact-form textarea.rbt-contact-input-field { min-height: 140px; padding: 14px; }
        #rw-contact-form .rbt-contact-input-field {
            border: 1px solid var(--color-gray-200, #e5e5e5);
            border-radius: 6px;
            padding: 12px 14px;
        }
CSS;

$baseForJs = h(getBaseUrl());
$extraScripts = <<<HTML
    <script>
    (function () {
        var form = document.getElementById('rw-contact-form');
        if (!form) return;
        var status = document.getElementById('rw-contact-status');
        var submit = document.getElementById('rw-contact-submit');
        var renderedAt = Date.now();

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            status.innerHTML = '';
            var payload = {
                name:        form.name.value.trim(),
                email:       form.email.value.trim(),
                phone:       form.phone.value.trim(),
                message:     form.message.value.trim(),
                website:     form.website.value,
                rendered_at: renderedAt,
            };
            submit.disabled = true;
            var originalText = submit.textContent;
            submit.textContent = 'Илгээж байна…';

            fetch('{$baseForJs}backend/api/contact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(function (r) { return r.json().then(function (d) { return { code: r.status, data: d }; }); })
            .then(function (res) {
                if (res.code === 200 && res.data && res.data.success) {
                    status.innerHTML = '<div class="rw-form-alert rw-form-alert-success">Таны мессежийг хүлээж авлаа. Бид тун удахгүй холбогдох болно.</div>';
                    form.reset();
                } else {
                    var msg = (res.data && res.data.error) ? res.data.error : 'Мессеж илгээхэд алдаа гарлаа. Дахин оролдоно уу.';
                    status.innerHTML = '<div class="rw-form-alert rw-form-alert-error">' + msg + '</div>';
                }
            })
            .catch(function () {
                status.innerHTML = '<div class="rw-form-alert rw-form-alert-error">Сүлжээний алдаа. Дахин оролдоно уу.</div>';
            })
            .finally(function () {
                submit.disabled = false;
                submit.textContent = originalText;
            });
        });
    })();
    </script>
HTML;

require __DIR__ . '/includes/header.php';
?>

    <!-- CONTACT BREADCRUMB -->
    <div class="rbt-breadcrumb-two rbt-bg-color-gray-light pt--40 pb--20">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active">Холбоо барих</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- CONTACT MAIN -->
    <div class="rbt-component-area rbt-bg-color-gray-light">
        <div class="container">
            <div class="row row--24 mt_dec--24">
                <div class="col-12 col-xl-6 mt--24">
                    <div class="rbt-component-section-title rbt-gap--4 mb--24 p-0 border-0 text-left">
                        <h1 class="rbt-title h1 mb--16"><span class="rbt-bold--text">Холбоо барих</span></h1>
                        <p class="desc mb--0">Асуулт, санал хүсэлт байвал утсаар, имэйлээр эсвэл доорх маягтаар холбогдоно уу.</p>
                    </div>

                    <div class="rbt-btn-grp justify-content-between rbt-gap--16 flex-wrap">
                        <?php if ($contactPhone): ?>
                        <a href="tel:<?= h(preg_replace('/\s+/', '', $contactPhone)) ?>" class="rbt-trns-modern-btn tooltips" data-tooltip="Утсаар холбогдох" data-tooltip-position="top">
                            <span class="icon"><i class="fa-regular fa-phone"></i></span>
                            <?= h($contactPhone) ?>
                        </a>
                        <?php endif; ?>

                        <?php if ($contactEmail): ?>
                        <a href="mailto:<?= h($contactEmail) ?>" class="rbt-trns-modern-btn tooltips" data-tooltip="Имэйлээр холбогдох" data-tooltip-position="top">
                            <span class="icon"><i class="fa-regular fa-envelope"></i></span>
                            <?= h($contactEmail) ?>
                        </a>
                        <?php endif; ?>

                        <?php if ($facebookUrl): ?>
                        <a href="<?= h($facebookUrl) ?>" target="_blank" rel="noopener" class="rbt-trns-modern-btn tooltips" data-tooltip="Facebook" data-tooltip-position="top">
                            <span class="icon"><i class="fa-brands fa-facebook-f"></i></span>
                            Facebook
                        </a>
                        <?php endif; ?>

                        <?php if ($instagramUrl): ?>
                        <a href="<?= h($instagramUrl) ?>" target="_blank" rel="noopener" class="rbt-trns-modern-btn tooltips" data-tooltip="Instagram" data-tooltip-position="top">
                            <span class="icon"><i class="fa-brands fa-instagram"></i></span>
                            Instagram
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="row row--12 mt--24 mt_sm--0 mt_md--0">
                        <?php if ($contactAddress): ?>
                        <div class="col-12 mt--24">
                            <div class="rbt-location-card style-two">
                                <div class="inner">
                                    <h2 class="rbt-location-card-title h6"><i class="fa-sharp fa-regular fa-location-dot mr--4"></i>Дэлгүүрийн байршил</h2>
                                    <p class="rbt-location-card-text"><?= h($contactAddress) ?></p>
                                    <ul class="rbt-contact-info-list">
                                        <?php if ($contactPhone): ?>
                                        <li>
                                            <span>Утас : </span>
                                            <a href="tel:<?= h(preg_replace('/\s+/', '', $contactPhone)) ?>" class="rbt-contact-info-single color-primary"><?= h($contactPhone) ?></a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($contactEmail): ?>
                                        <li>
                                            <span>И-мэйл : </span>
                                            <a href="mailto:<?= h($contactEmail) ?>" class="rbt-contact-info-single color-primary"><?= h($contactEmail) ?></a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($contactHours): ?>
                                        <li>
                                            <span>Ажиллах цаг : </span>
                                            <span class="rbt-contact-info-single"><?= h($contactHours) ?></span>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-xl-6 mt--24">
                    <div class="rbt-contact-form">
                        <div class="rbt-fshape-box-outline-style">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="rbt-component-section-title rbt-contact-form-title rbt-bg-color-white rbt-border-color-gray-100">
                                        <h2 class="rbt-title h6"><span class="rbt-bold--text">Санал хүсэлт илгээх</span></h2>
                                        <span class="rbt-fshape-right-portion">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="52" height="50"
                                                viewBox="0 0 52 50" fill="none">
                                                <path
                                                    d="M51.5337 49.984C-64.8544 49.9977 116.427 49.9764 0.0390625 49.9901C0.0390625 31.262 0.0390625 20.7619 0.0390625 2.03378C11.2391 1.63419 16.5034 4.56468 19.5034 10.5602L30.0034 38.5311C34.0374 47.934 45.4209 49.4481 51.5337 49.984Z"
                                                    fill="var(--color-white)" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M13.246 1.97519C16.582 3.50685 18.8114 5.90944 20.3979 9.07997L20.4213 9.12681L30.9315 37.1248C33.053 42.053 36.807 44.7979 40.7367 46.3047C44.6934 47.8219 48.798 48.068 51.4731 47.987C51.4731 47.987 51.51 49.2041 51.5337 49.984C48.7087 50.0695 44.3134 49.8162 40.02 48.17C35.7052 46.5155 31.4643 43.4388 29.0842 37.891L29.0751 37.8698C29.0751 37.8698 19.997 12.7279 18.5857 9.92689C17.1743 7.12591 15.2591 5.09828 12.4108 3.79055C8.49554 1.49902 0.0390625 2.03378 0.0390625 2.03378C0.0390625 20.7619 0.0390625 31.262 0.0390625 49.9901L0.0408325 0.0348727C5.70805 -0.16568 9.9493 0.461575 13.246 1.97519Z"
                                                    fill="var(--color-gray-100)" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="rbt-fshape-box rbt-bg-color-white rbt-contact-form-fshape rbt-border-color-gray-100">
                                <form id="rw-contact-form" class="rainbow-dynamic-form" novalidate>
                                    <div class="row">
                                        <div class="col-12 mb--16">
                                            <div class="rbt-input-field-grp form-group">
                                                <label for="rw-contact-name">Нэр *</label>
                                                <input class="rbt-contact-input-field" type="text" id="rw-contact-name" name="name" required maxlength="150">
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-12 mb--16">
                                            <div class="rbt-input-field-grp form-group">
                                                <label for="rw-contact-email">И-мэйл *</label>
                                                <input class="rbt-contact-input-field" type="email" id="rw-contact-email" name="email" required maxlength="190">
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-12 mb--16">
                                            <div class="rbt-input-field-grp form-group">
                                                <label for="rw-contact-phone">Утас</label>
                                                <input class="rbt-contact-input-field" type="tel" id="rw-contact-phone" name="phone" maxlength="30">
                                            </div>
                                        </div>
                                        <div class="col-12 mb--16">
                                            <div class="rbt-input-field-grp form-group">
                                                <label for="rw-contact-message">Мессеж *</label>
                                                <textarea class="rbt-contact-input-field" id="rw-contact-message" name="message" required minlength="10" maxlength="5000" rows="5"></textarea>
                                            </div>
                                        </div>
                                        <div class="rw-hp" aria-hidden="true">
                                            <label for="rw-contact-website">Website</label>
                                            <input type="text" id="rw-contact-website" name="website" tabindex="-1" autocomplete="off">
                                        </div>

                                        <div class="col-12" id="rw-contact-status"></div>

                                        <div class="col-12 d-block mt--0">
                                            <button type="submit" id="rw-contact-submit" class="rbt-btn rbt-btn-md d-block text-center w-100">Илгээх</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($mapEmbed): ?>
    <!-- CONTACT MAP -->
    <div class="rbt-component-area rbt-bg-color-gray-light">
        <div class="container">
            <div class="rbt-google-map bg-color-white rbt-section-gap2Top">
                <iframe class="w-100" src="<?= h($mapEmbed) ?>" height="500" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
