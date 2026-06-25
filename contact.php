<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Холбоо барих';
require_once __DIR__ . '/includes/header.php';
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
                <?php $mapEmbed = s('map_embed_url', ''); ?>
                <?php if ($mapEmbed): ?>
                <iframe src="<?= htmlspecialchars($mapEmbed) ?>"
                        width="100%" height="461" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                <?php else: ?>
                <div style="width:100%;height:300px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;">
                    <div class="text-center">
                        <i class="icon icon-map-pin" style="font-size:3rem;color:#9ca3af;"></i>
                        <p class="h6 text-main mt-2"><?= htmlspecialchars(s('contact_address', 'Улаанбаатар, Монгол')) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- /Map -->

            <div class="container">
                <div class="row">
                    <!-- Left: Contact Info -->
                    <div class="col-xxl-5 offset-xxl-1 col-lg-6">
                        <div class="left-col mb-lg-0">
                            <h3 class="title fw-normal">Манай дэлгүүр</h3>
                            <ul class="store-info-list">
                                <li>
                                    <p class="h6 text-black fw-medium">Хаяг:</p>
                                    <?php $address = s('contact_address', 'Улаанбаатар, Монгол'); ?>
                                    <span class="text-main"><?= htmlspecialchars($address) ?></span>
                                </li>
                                <?php $email = s('contact_email', ''); if ($email): ?>
                                <li>
                                    <p class="h6 text-black fw-medium">И-мэйл:</p>
                                    <a href="mailto:<?= htmlspecialchars($email) ?>" class="link text-main">
                                        <?= htmlspecialchars($email) ?>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php $phone = s('contact_phone', ''); if ($phone): ?>
                                <li>
                                    <p class="h6 text-black fw-medium">Утас:</p>
                                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="link text-main">
                                        <?= htmlspecialchars($phone) ?>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php $hours = s('business_hours', ''); if ($hours): ?>
                                <li>
                                    <p class="h6 text-black fw-medium">Ажиллах цаг:</p>
                                    <p class="text-main"><?= nl2br(htmlspecialchars($hours)) ?></p>
                                </li>
                                <?php endif; ?>
                            </ul>
                            <ul class="tf-social-icon mt-4">
                                <?php $fb = s('social_facebook', ''); if ($fb): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($fb) ?>" target="_blank" class="social-facebook">
                                        <span class="icon"><i class="icon-fb"></i></span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php $ig = s('social_instagram', ''); if ($ig): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($ig) ?>" target="_blank" class="social-instagram">
                                        <span class="icon"><i class="icon-instagram-logo"></i></span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Right: Contact Form -->
                    <div class="col-xl-5 col-lg-6">
                        <div class="right-col">
                            <h3 class="title fw-normal">Бидэнтэй холбоо бар</h3>
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
                                            <input id="contact_name" type="text" placeholder="Нэр *" required>
                                        </fieldset>
                                        <fieldset>
                                            <input id="contact_email" type="email" placeholder="И-мэйл *" required>
                                        </fieldset>
                                    </div>
                                    <fieldset>
                                        <input id="contact_phone" type="text" placeholder="Утасны дугаар (заавал биш)">
                                    </fieldset>
                                    <textarea id="contact_message" placeholder="Мессеж *" style="height: 229px;" required></textarea>
                                </div>
                                <div class="form_message text-center" id="contact-error" style="display:none;color:#991b1b;margin-bottom:12px;"></div>
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
$extra_scripts = '<script>
(function () {
    document.getElementById("contactForm").addEventListener("submit", function (e) {
        e.preventDefault();

        const name    = document.getElementById("contact_name").value.trim();
        const email   = document.getElementById("contact_email").value.trim();
        const message = document.getElementById("contact_message").value.trim();
        const errEl   = document.getElementById("contact-error");
        const btn     = document.getElementById("btnContact");

        errEl.style.display = "none";

        if (!name || !email || !message) {
            errEl.style.display = "block";
            errEl.textContent   = "Нэр, и-мэйл, мессежийг бөглөнө үү.";
            return;
        }

        const re = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
        if (!re.test(email)) {
            errEl.style.display = "block";
            errEl.textContent   = "И-мэйл хаягаа зөв оруулна уу.";
            return;
        }

        btn.querySelector(".btn-text").style.display   = "none";
        btn.querySelector(".btn-loading").style.display = "";
        btn.disabled = true;

        // Simulate sending (no backend yet)
        setTimeout(function () {
            document.getElementById("contactForm").style.display    = "none";
            document.getElementById("contact-success").style.display = "block";
        }, 800);
    });
}());
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
