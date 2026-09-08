<?php
/**
 * Shared storefront footer partial.
 *
 * Emits shared template modals (welcome banner, quickview, popup cart,
 * coupon collection, comparison drawer), the site <footer>, copyright bar,
 * all vendor + theme <script> tags, and closes </body></html>.
 *
 * Callers may set (before requiring):
 *   $extraScripts — page-specific <script> tags injected before </body>
 */

$extraScripts = $extraScripts ?? '';
?>

    <div class="rbt-cookies">
        <div class="rbt-icon">
            <img src="assets/images/icons/cookie.svg" alt="Icon">
        </div>
        <div class="rbt-content">
            <div class="rbt-cookie-info">
                <p class="b2 mb--4 rbt-text-bold rbt-text-color-heading">We Care About Your Privacy</p>
                <p class="b4 mb--0 rbt-text-color-gray-600">
                    We use cookies & similar technologies to provide the
                    best experience on our website. <a href="#!"
                        class="rbt-btn-link rbt-text-color-heading rbt-text-bold b4">Privacy Policy</a>
                </p>
            </div>
            <div class="rbt-gap--8 rbt-btn-group">
                <button class="rbt-btn rbt-btn-md rbt-btn-gray-light rbt-cookies-decline-btn">Decline</button>
                <button class="rbt-btn rbt-btn-md rbt-cookies-accept-btn">Accept</button>
            </div>
        </div>
        <button class="rbt-close-btn">
            <i class="fa-sharp fa-solid fa-xmark"></i>
        </button>
    </div>
    <!-- Start Wishlist Modal Area  -->
    <div class="rbt-default-modal modal fade has-rbt-top-folder-shape" id="socialShareModal" tabindex="-1" role="dialog"
        aria-modal="true" aria-labelledby="socialShareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered xxs-size">
            <div class="modal-content">
                <div class="rbt-folder-shape-right-portion">
                    <svg xmlns="http://www.w3.org/2000/svg" width="85" height="90" viewBox="0 0 85 90" fill="none">
                        <path
                            d="M0 0H11.1844C14.5695 0 17.7971 1.42971 20.0716 3.93671L82.1927 72.4059C83.9992 74.397 84.9999 76.9893 84.9999 79.6778C84.9999 85.6547 85.0001 90 85.0001 90H0V0Z"
                            fill="white" />
                    </svg>
                </div>

                <div class="modal-header">
                    <button type="button" class="rbt-round-btn rbt-modal-dis-btn" data-bs-dismiss="modal"
                        aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="rbt-top-folder-shape-wrapper">
                    <div class="rbt-bg-color-white rbt-content-trs-portion">
                        <div class="rbt-title mb--8 rbt-text-bold" id="socialShareModalLabel">Share Options</div>
                        <div class="rbt-social-share-wrapper">

                            <ul
                                class="social-icon rbt-social-default mt--16 mt_sm--0 rbt-social-default-v1 lg-size justify-content-start">
                                <li>
                                    <a class="facebook-btn" href="https://www.facebook.com">
                                        <i class="fa-brands fa-facebook-f"></i>
                                    </a>
                                </li>
                                <li>
                                    <a class="tiktok-btn" href="https://www.tiktok.com">
                                        <i class="fa-brands fa-tiktok"></i>
                                    </a>
                                </li>
                                <li>
                                    <a class="pinterest-btn" href="https://www.pinterest.com">
                                        <i class="fa-brands fa-pinterest-p"></i>
                                    </a>
                                </li>
                                <li>
                                    <a class="tumblr-btn" href="https://www.tumblr.com/">
                                        <i class="fa-brands fa-tumblr"></i>
                                    </a>
                                </li>
                                <li>
                                    <a class="telegram-btn" href="https://www.telegram.com">
                                        <i class="fa-brands fa-telegram"></i>
                                    </a>
                                </li>
                                <li>
                                    <a class="whatsapp-btn" href="https://www.whatsapp.com/">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </a>
                                </li>
                                <li>
                                    <a class="email-btn" href="mailto:someone@example.com">
                                        <i class="fa-regular fa-envelope"></i>
                                    </a>
                                </li>
                            </ul>

                            <div class="rbt-copy-link-part rbt-text-copy-activation mt--24 mt_sm--8 w-100">
                                <input class="rbt-copy-value-field w-100" type="text"
                                    value="https://unimart.template/wishlist" readonly>
                                <button class="rbt-btn rbt-btn-xs has-left-icon rbt-copy-btn"
                                    data-tooltip="Copy to clipboard">
                                    <i class="fa-regular fa-copy"></i>
                                    <span class="rbt-btn-text">Copy</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Wishlist Modal Area  -->

    <!-- Newsletter strip moved into Footer Style Two below -->

    <div class="rbt-toolbar rbt-toolbar--bottom d-block d-xl-none">
        <div class="container p--0">
            <div class="row row row--0">
                <div class="col-md-12">
                    <ul class="rbt-quick-access onepagenav">
                        <li class="rbt-access-box">
                            <a href="<?= h($urlHome) ?>" class="rbt-round-btn has-rbt-md-fsize">
                                <i class="fa-regular fa-house"></i>
                                <span class="rbt-toolbar-label"> Нүүр</span>
                            </a>
                        </li>

                        <li class="rbt-access-box">
                            <a href="<?= h($urlShop) ?>" class="rbt-round-btn has-rbt-md-fsize">
                                <i class="fa-regular fa-bag-shopping"></i>
                                <span class="rbt-toolbar-label"> Дэлгүүр</span>
                            </a>
                        </li>

                        <li class="rbt-access-box">
                            <a href="<?= h($urlCart) ?>" class="rbt-round-btn has-rbt-md-fsize">
                                <i class="fa-regular fa-cart-shopping"></i>
                                <?php if ($cartCount > 0): ?>
                                <div class="access-box-count"><?= $cartCount ?></div>
                                <?php endif; ?>
                                <span class="rbt-toolbar-label"> Сагс</span>
                            </a>
                        </li>

                        <li class="rbt-access-box">
                            <a href="<?= h($loggedIn ? $urlAccount : $urlLogin) ?>" class="rbt-round-btn has-rbt-md-fsize">
                                <i class="fa-regular fa-user"></i>
                                <span class="rbt-toolbar-label"> <?= $loggedIn ? 'Профайл' : 'Нэвтрэх' ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- End Footer aera -->
    <div class="rbt-toaster rbt-toaster-compare" role="alert" aria-atomic="true" aria-live="assertive"><i
            class="fa-regular fa-check mr--8"></i>Added in Compare</div>
    <div class="rbt-progress-parent">
        <svg class="rbt-back-circle svg-inner" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
    </div>

    <a class="close_side_menu catagories-close_side_menu" href="javascript:void(0);"></a>
    <a href="javascript:void(0);" class="common-close_search_dropdown"></a>
    <script src="assets/js/vendor/modernizr.min.js"></script>
    <script src="assets/js/vendor/jquery.js"></script>
    <script src="assets/js/vendor/bootstrap.min.js"></script>
    <script src="assets/js/vendor/swiper.js"></script>
    <script src="assets/js/vendor/jquery-appear.js"></script>
    <script src="assets/js/vendor/fancybox.min.js"></script>
    <script src="assets/js/vendor/animation.js"></script>
    <script src="assets/js/vendor/text-type.js"></script>
    <script src="assets/js/vendor/odometer.js"></script>
    <script src="assets/js/vendor/backtotop.js"></script>
    <script src="assets/js/vendor/jquery-ui.js"></script>
    <script src="assets/js/vendor/bootstrap-select.min.js"></script>
    <script src="assets/js/vendor/countdown.js"></script>
    <script src="assets/js/vendor/progressbar.min.js"></script>
    <script src="assets/js/vendor/isotope.pkgd.min.js"></script>
    <script src="assets/js/vendor/imageloaded.js"></script>
    <script src="assets/js/vendor/jquery.waypoints.min.js"></script>
    <script src="assets/js/plugins/color-swatches.js"></script>
    <script src="assets/js/vendor/bootstrap-datepicker.min.js"></script>

    <!-- Main JS -->
    <script src="assets/js/main.min.js"></script>

    <!-- Home-page tab switching -->
    <script>
    (function () {
        var tabs = document.querySelectorAll('#dealsTabs [data-deals-tab]');
        var panels = document.querySelectorAll('.deals-tab-panel');
        tabs.forEach(function (t) {
            t.addEventListener('click', function (e) {
                e.preventDefault();
                var key = t.getAttribute('data-deals-tab');
                tabs.forEach(function (x) { x.classList.remove('active'); });
                t.classList.add('active');
                panels.forEach(function (p) {
                    p.style.display = (p.getAttribute('data-deals-panel') === key) ? '' : 'none';
                });
            });
        });
    })();
    </script>
    <!-- Newsletter subscribe (shared: footer strip + welcome popup) -->
    <script>
    (function () {
        var forms = document.querySelectorAll('.rw-newsletter-form');
        if (!forms.length) return;
        var endpoint = '<?= h(getBaseUrl()) ?>backend/api/subscribe.php';

        // Auto-open welcome popup if enabled, after a delay, unless the user
        // dismissed or subscribed within the cooldown window (localStorage).
        var modalEl = document.getElementById('welcomebannerModal');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            var delayMs   = <?= (int)s('newsletter_modal_delay_ms', '6000') ?>;
            var cooldown  = <?= (int)s('newsletter_modal_cooldown_days', '7') ?> * 86400000;
            var seenKey   = 'rw_newsletter_seen_at';
            var seenAt    = parseInt(localStorage.getItem(seenKey) || '0', 10);
            var now       = Date.now();
            if (!seenAt || (now - seenAt) > cooldown) {
                setTimeout(function () {
                    try {
                        var m = bootstrap.Modal.getOrCreateInstance(modalEl);
                        m.show();
                        modalEl.addEventListener('hidden.bs.modal', function () {
                            localStorage.setItem(seenKey, String(Date.now()));
                        });
                    } catch (e) {}
                }, delayMs);
            }
        }

        // Auto-hide any status message after a delay; cancels the pending
        // hide if the user submits again before it finishes.
        var hideTimers = new WeakMap();
        function setStatus(el, cls, text) {
            if (!el) return;
            var prev = hideTimers.get(el);
            if (prev) { clearTimeout(prev.fade); clearTimeout(prev.clear); }
            el.className = 'rw-newsletter-status ' + cls;
            el.textContent = text;
            var fade = setTimeout(function () { el.classList.add('rw-fade-out'); }, 4000);
            var clear = setTimeout(function () {
                el.className = 'rw-newsletter-status';
                el.textContent = '';
            }, 4400);
            hideTimers.set(el, { fade: fade, clear: clear });
        }
        function clearStatus(el) {
            if (!el) return;
            var prev = hideTimers.get(el);
            if (prev) { clearTimeout(prev.fade); clearTimeout(prev.clear); hideTimers.delete(el); }
            el.className = 'rw-newsletter-status';
            el.textContent = '';
        }

        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var key = form.getAttribute('data-newsletter-form');
                var status = document.querySelector('.rw-newsletter-status[data-newsletter-status="' + key + '"]');
                var input  = form.querySelector('input[type=email]');
                var button = form.querySelector('button[type=submit]');
                if (!input || !button) return;
                var email = (input.value || '').trim();
                clearStatus(status);

                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    setStatus(status, 'rw-newsletter-error', 'Хүчинтэй имэйл хаяг оруулна уу.');
                    return;
                }

                var originalLabel = button.innerHTML;
                button.disabled = true;
                button.innerHTML = 'Илгээж байна…';

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email }),
                })
                .then(function (r) { return r.json().then(function (d) { return { code: r.status, data: d }; }); })
                .then(function (res) {
                    var ok = res.code === 200 && res.data && res.data.success;
                    var msg = (res.data && (res.data.message || res.data.error)) || (ok ? 'Баярлалаа!' : 'Алдаа гарлаа.');
                    setStatus(status, ok ? 'rw-newsletter-success' : 'rw-newsletter-error', msg);
                    if (ok) input.value = '';
                })
                .catch(function () {
                    setStatus(status, 'rw-newsletter-error', 'Сүлжээний алдаа. Дахин оролдоно уу.');
                })
                .finally(function () {
                    button.disabled = false;
                    button.innerHTML = originalLabel;
                });
            });
        });
    })();
    </script>
    <style>
        /* Pill-style status so it reads clearly on any parent background
           (white modal + blue footer strip). Zero height when empty. */
        .rw-newsletter-status { font-size: 13px; }
        .rw-newsletter-status:not(:empty) {
            margin-top: 10px;
            display: inline-block;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }
        .rw-newsletter-status.rw-fade-out { opacity: 0; }
        .rw-newsletter-success { background: #ffffff; color: #137333; border: 1px solid #b6dfc1; }
        .rw-newsletter-error   { background: #ffffff; color: #b3261e; border: 1px solid #f5c2c0; }
    </style>

<?= $extraScripts ?>
</body>


</html>
