<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');
$db       = getDB();

// ── Page-specific prep ───────────────────────────────────────

// Banner slider(s)
try {
    $sliders = $db->query("SELECT * FROM sliders WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();
} catch (Throwable) { $sliders = []; }

// Parent categories only (for the "Shop By Categories" swiper)
try {
    $homeCategories = $db->query("
        SELECT id, slug, name, name_mn, image
        FROM categories
        WHERE is_active = 1 AND parent_id IS NULL
        ORDER BY sort_order, name_mn
    ")->fetchAll();
} catch (Throwable) { $homeCategories = []; }


/**
 * Render a single product card. Used by both the tab panels below and any
 * future product grids.
 */

// ── SIGNUP ────────────────────────────────────────────────────
$page_title = 'Бүртгүүлэх — ' . $siteName;

$signupRedirect = (string)($_GET['redirect'] ?? $urlAccount);
if ($signupRedirect === '' || (!str_starts_with($signupRedirect, getBaseUrl()) && !str_starts_with($signupRedirect, '/'))) {
    $signupRedirect = $urlAccount;
}

if ($loggedIn) {
    header('Location: ' . $signupRedirect);
    exit;
}

// Which registration methods the admin has approved (backend/pages/settings.php → "Нэвтрэх тохиргоо")
$signupPhoneOtpEnabled    = sBool('login_phone_otp_enabled', false);
$signupPhoneDirectEnabled = sBool('login_register_without_otp_enabled', false);
$signupPhoneEnabled       = $signupPhoneOtpEnabled || $signupPhoneDirectEnabled;
$signupEmailEnabled       = sBool('register_email_enabled', false);
if (!$signupPhoneEnabled && !$signupEmailEnabled) {
    // Nothing configured — fail open on phone+OTP so the page still works rather than dead-ending.
    $signupPhoneEnabled    = true;
    $signupPhoneOtpEnabled = true;
}
$signupDefaultMethod = $signupPhoneEnabled ? 'phone' : 'email';

// Social login: admin toggle AND provider credentials both required, or the button is a dead end.
$signupGoogleClientId  = s('google_client_id', '');
$signupFacebookAppId   = s('facebook_app_id', '');
$signupGoogleEnabled   = sBool('login_google_enabled', false) && $signupGoogleClientId !== '';
$signupFacebookEnabled = sBool('login_facebook_enabled', false) && $signupFacebookAppId !== '';

$extraStyles = <<<'EXTRA_CSS'
    <!-- Site-specific overrides -->
    <style>
        /* Multi-word nav labels (e.g. "Гүйлтийн гутал") must not wrap: this
           theme's nav row has a fixed line-height, so a wrapped second line
           renders outside the clipped header bounds and becomes invisible. */
        .mainmenu > li > a {
            white-space: nowrap;
        }
        /* Product card images: force 1:1 for a consistent grid. */
        .rbt-card-img {
            aspect-ratio: 1 / 1;
            position: relative;
            overflow: hidden;
        }
        .rbt-card-img > a,
        .rbt-card-img > img,
        .rbt-card-img .rbt-prd-img,
        .rbt-card-img .rbt-hover-img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        /* Theme bug: .rbt-login-form-inner references a typo'd CSS var
           (--colro-white), so its white card background never applies. */
        .rbt-login-form-inner {
            background: var(--color-white, #fff);
        }
    </style>
EXTRA_CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- SHOP BREADCRUMB -->
    <!-- SIGNUP MAIN -->
    <div class="rbt-component-area rbt-section-gap2Bottom rbt-section-gap2Top">
        <div class="container">
            <div class="row">
                <div class="col-12 col-md-8 col-lg-6 col-xl-5 mx-auto">
                    <div class="rbt-login-form">
                        <div class="rbt-login-form-inner">
                            <div class="rbt-login-form-top">
                                <div class="logo">
                                    <a href="<?= h($urlHome) ?>"><img src="<?= h($logoUrl) ?>" alt="<?= h($siteName) ?>" style="max-height:48px;"></a>
                                </div>
                                <h3 class="rbt-title rbt-text-bold mb--16 h6">Бүртгүүлэх</h3>

                                <div id="rwSignupAlert" class="alert alert-danger" style="display:none;" role="alert"></div>

                                <div id="rwSignupFormArea">
                                <div class="rbt-tab rbt-round-shape-tab">
                                    <?php if ($signupPhoneEnabled && $signupEmailEnabled): ?>
                                    <ul class="nav nav-tabs" id="rwSignupTab" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?= $signupDefaultMethod === 'phone' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#rwSignupPanePhone" type="button" role="tab">
                                                <i class="fa-sharp fa-regular fa-phone"></i> Утасны дугаар
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?= $signupDefaultMethod === 'email' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#rwSignupPaneEmail" type="button" role="tab">
                                                <i class="fa-sharp fa-regular fa-envelope"></i> И-мэйл
                                            </button>
                                        </li>
                                    </ul>
                                    <?php endif; ?>

                                    <div class="tab-content" id="rwSignupTabContent">
                                        <?php if ($signupPhoneEnabled): ?>
                                        <!-- ══════════ Phone method ══════════ -->
                                        <div class="tab-pane fade <?= $signupDefaultMethod === 'phone' ? 'show active' : '' ?>" id="rwSignupPanePhone" role="tabpanel">
                                            <?php if ($signupPhoneOtpEnabled): ?>
                                            <!-- Step 1: phone -->
                                            <form id="rwStepPhone">
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupPhone">Утасны дугаар<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" inputmode="numeric" id="signupPhone" placeholder="99112233" required>
                                                </div>
                                                <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwSendOtpBtn">Код авах</button>
                                            </form>

                                            <!-- Step 2: otp code -->
                                            <form id="rwStepCode" style="display:none;">
                                                <p class="text-muted mb--16">Таны <strong id="rwCodePhoneEcho"></strong> дугаарт илгээсэн 4 оронтой кодыг оруулна уу.</p>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupCode">Баталгаажуулах код<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" inputmode="numeric" maxlength="4" id="signupCode" required>
                                                </div>
                                                <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwVerifyOtpBtn">Баталгаажуулах</button>
                                                <button type="button" class="rbt-btn rbt-btn-border d-block w-100 mt--8" id="rwBackToPhoneBtn">Дугаар солих</button>
                                            </form>

                                            <!-- Step 3: name + password -->
                                            <form id="rwStepFinish" style="display:none;">
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupName">Нэр<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" id="signupName" required>
                                                </div>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupPassword">Нууц үг<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="password" id="signupPassword" minlength="6" required>
                                                </div>
                                                <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwFinishBtn">Бүртгэл дуусгах</button>
                                                <div class="rbt-check-group">
                                                    <input id="rwPhoneRemember" type="checkbox" checked>
                                                    <label for="rwPhoneRemember">Нэвтэрсэн байлгах</label>
                                                </div>
                                            </form>
                                            <?php else: ?>
                                            <!-- Direct (no OTP) — admin has disabled SMS-verified signup -->
                                            <form id="rwStepDirect">
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupDirectName">Нэр<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" id="signupDirectName" required>
                                                </div>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupDirectPhone">Утасны дугаар<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" inputmode="numeric" id="signupDirectPhone" placeholder="99112233" required>
                                                </div>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupDirectPassword">Нууц үг<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="password" id="signupDirectPassword" minlength="6" required>
                                                </div>
                                                <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwDirectBtn">Бүртгэл дуусгах</button>
                                                <div class="rbt-check-group">
                                                    <input id="rwDirectRemember" type="checkbox" checked>
                                                    <label for="rwDirectRemember">Нэвтэрсэн байлгах</label>
                                                </div>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($signupEmailEnabled): ?>
                                        <!-- ══════════ Email method ══════════ -->
                                        <div class="tab-pane fade <?= $signupDefaultMethod === 'email' ? 'show active' : '' ?>" id="rwSignupPaneEmail" role="tabpanel">
                                            <!-- Step 1: email + name + phone + password -->
                                            <form id="rwEmailStep1">
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupEmail">И-мэйл<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="email" id="signupEmail" placeholder="you@example.com" required>
                                                </div>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupEmailName">Нэр<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" id="signupEmailName" required>
                                                </div>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupEmailPhone">Утасны дугаар<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" inputmode="numeric" id="signupEmailPhone" placeholder="99112233" required>
                                                </div>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupEmailPassword">Нууц үг<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="password" id="signupEmailPassword" minlength="6" required>
                                                </div>
                                                <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwSendEmailOtpBtn">Код авах</button>
                                            </form>

                                            <!-- Step 2: email otp code -->
                                            <form id="rwEmailStep2" style="display:none;">
                                                <p class="text-muted mb--16">Таны <strong id="rwCodeEmailEcho"></strong> хаяг руу илгээсэн 4 оронтой кодыг оруулна уу.</p>
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="signupEmailCode">Баталгаажуулах код<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" inputmode="numeric" maxlength="4" id="signupEmailCode" required>
                                                </div>
                                                <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwVerifyEmailOtpBtn">Бүртгэл дуусгах</button>
                                                <button type="button" class="rbt-btn rbt-btn-border d-block w-100 mt--8" id="rwBackToEmailBtn">И-мэйл солих</button>
                                                <div class="rbt-check-group">
                                                    <input id="rwEmailRemember" type="checkbox" checked>
                                                    <label for="rwEmailRemember">Нэвтэрсэн байлгах</label>
                                                </div>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!$signupPhoneEnabled && !$signupEmailEnabled): ?>
                                <p class="text-muted text-center mb-0">Одоогоор бүртгүүлэх боломжгүй байна. Дараа дахин оролдоно уу.</p>
                                <?php endif; ?>

                                <?php if ($signupGoogleEnabled || $signupFacebookEnabled): ?>
                                <div class="d-flex align-items-center justify-content-center mb--24 mt--24">
                                    <hr class="rbt-separator rbt-bg-color-gray-light mb--0">
                                    <span class="pl--8 pr--8 b4 rbt-text-medium">эсвэл</span>
                                    <hr class="rbt-separator rbt-bg-color-gray-light mb--0">
                                </div>
                                <?php if ($signupFacebookEnabled): ?>
                                <button type="button" id="rwFacebookLoginBtn" class="rbt-btn rbt-btn-border rbt-social-login-btn d-block w-100 mb--16">
                                    <i class="fa-brands fa-facebook" style="color:#1877F2;"></i> Facebook-ээр бүртгүүлэх
                                </button>
                                <?php endif; ?>
                                <?php if ($signupGoogleEnabled): ?>
                                <div id="rwGoogleLoginBtn" class="d-flex justify-content-center mb--16"></div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <div class="rbt-login-system-switch rbt-link-hover">
                                    Бүртгэлтэй юу?
                                    <a class="rbt-switch-btn" href="<?= h(url('login')) ?>"><span>Нэвтрэх</span></a>
                                </div>
                                </div>

                                <!-- Social sign-up: link a phone when the social account is new -->
                                <div id="rwSocialLinkArea" style="display:none;">
                                    <p class="text-muted mb--16">Энэ <span id="rwSocialProviderEcho"></span> акаунт анх удаа холбогдож байна. Утасны дугаараа баталгаажуулна уу.</p>
                                    <form id="rwSocialPhoneForm">
                                        <div class="rbt-input-field-grp">
                                            <label class="rbt-field-label" for="rwSocialPhone">Утасны дугаар<span class="rbt-text-color-danger">*</span></label>
                                            <input class="rbt-input-field" type="text" inputmode="numeric" id="rwSocialPhone" placeholder="99112233" required>
                                        </div>
                                        <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwSocialSendOtpBtn">Код авах</button>
                                    </form>
                                    <form id="rwSocialCodeForm" style="display:none;">
                                        <p class="text-muted mb--16">Таны <strong id="rwSocialPhoneEcho"></strong> дугаарт илгээсэн 4 оронтой кодыг оруулна уу.</p>
                                        <div class="rbt-input-field-grp">
                                            <label class="rbt-field-label" for="rwSocialCode">Баталгаажуулах код<span class="rbt-text-color-danger">*</span></label>
                                            <input class="rbt-input-field" type="text" inputmode="numeric" maxlength="4" id="rwSocialCode" required>
                                        </div>
                                        <button type="submit" class="rbt-btn d-block w-100 mt--16" id="rwSocialVerifyBtn">Баталгаажуулах</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($signupGoogleEnabled): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
    <?php if ($signupFacebookEnabled): ?>
    <script src="https://connect.facebook.net/en_US/sdk.js" async defer></script>
    <?php endif; ?>

    <script>
    (function () {
        var redirectTo = <?= json_encode($signupRedirect) ?>;

        var alertBox = document.getElementById('rwSignupAlert');

        function showError(msg) {
            alertBox.textContent = msg;
            alertBox.style.display = 'block';
        }
        function clearError() {
            alertBox.style.display = 'none';
        }
        function setBusy(btn, busy) {
            btn.disabled = busy;
            if (busy) { btn.dataset.label = btn.textContent; btn.textContent = 'Түр хүлээнэ үү...'; }
            else if (btn.dataset.label) { btn.textContent = btn.dataset.label; }
        }
        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body || {})
            }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); });
        }
        function finishLogin(btn, token, remember) {
            return postJson('session-bridge', { token: token, remember: !!remember }).then(function (bridgeRes) {
                if (bridgeRes && bridgeRes.ok && bridgeRes.data.success) {
                    window.location.href = redirectTo;
                } else {
                    setBusy(btn, false);
                    showError('Сесс үүсгэхэд алдаа гарлаа.');
                }
            });
        }

        <?php if ($signupPhoneEnabled && $signupPhoneOtpEnabled): ?>
        // ── Phone method: OTP-verified ──
        var phone = '';
        var otpToken = '';
        var stepPhone = document.getElementById('rwStepPhone');
        var stepCode = document.getElementById('rwStepCode');
        var stepFinish = document.getElementById('rwStepFinish');

        function showPhoneStep(step) {
            stepPhone.style.display = step === 'phone' ? '' : 'none';
            stepCode.style.display = step === 'code' ? '' : 'none';
            stepFinish.style.display = step === 'finish' ? '' : 'none';
        }

        stepPhone.addEventListener('submit', function (e) {
            e.preventDefault();
            clearError();
            var val = document.getElementById('signupPhone').value.replace(/\D/g, '');
            if (val.length !== 8) { showError('8 оронтой утасны дугаар оруулна уу.'); return; }
            phone = val;
            var btn = document.getElementById('rwSendOtpBtn');
            setBusy(btn, true);
            postJson('backend/api/auth/send-otp.php', { phone: phone }).then(function (res) {
                setBusy(btn, false);
                if (!res.ok) { showError(res.data.error || 'Код илгээхэд алдаа гарлаа.'); return; }
                document.getElementById('rwCodePhoneEcho').textContent = phone;
                showPhoneStep('code');
            }).catch(function () {
                setBusy(btn, false);
                showError('Сүлжээний алдаа гарлаа.');
            });
        });

        document.getElementById('rwBackToPhoneBtn').addEventListener('click', function () {
            clearError();
            showPhoneStep('phone');
        });

        stepCode.addEventListener('submit', function (e) {
            e.preventDefault();
            clearError();
            var code = document.getElementById('signupCode').value.trim();
            if (code.length !== 4) { showError('4 оронтой код оруулна уу.'); return; }
            var btn = document.getElementById('rwVerifyOtpBtn');
            setBusy(btn, true);
            postJson('backend/api/auth/verify-otp.php', { phone: phone, code: code }).then(function (res) {
                setBusy(btn, false);
                if (!res.ok || !res.data.verified) { showError(res.data.error || 'Код буруу байна.'); return; }
                otpToken = res.data.otp_token;
                showPhoneStep('finish');
            }).catch(function () {
                setBusy(btn, false);
                showError('Сүлжээний алдаа гарлаа.');
            });
        });

        stepFinish.addEventListener('submit', function (e) {
            e.preventDefault();
            clearError();
            var name = document.getElementById('signupName').value.trim();
            var password = document.getElementById('signupPassword').value;
            var remember = document.getElementById('rwPhoneRemember').checked;
            if (!name) { showError('Нэрээ оруулна уу.'); return; }
            if (password.length < 6) { showError('Нууц үг дор хаяж 6 тэмдэгт байх ёстой.'); return; }
            var btn = document.getElementById('rwFinishBtn');
            setBusy(btn, true);
            postJson('backend/api/auth/register.php', { phone: phone, otp_token: otpToken, password: password, name: name }).then(function (res) {
                if (!res.ok || !res.data.success) {
                    setBusy(btn, false);
                    showError(res.data.error || 'Бүртгэхэд алдаа гарлаа.');
                    return;
                }
                return finishLogin(btn, res.data.token, remember);
            }).catch(function () {
                setBusy(btn, false);
                showError('Сүлжээний алдаа гарлаа.');
            });
        });
        <?php elseif ($signupPhoneEnabled && $signupPhoneDirectEnabled): ?>
        // ── Phone method: direct, no OTP ──
        var stepDirect = document.getElementById('rwStepDirect');
        stepDirect.addEventListener('submit', function (e) {
            e.preventDefault();
            clearError();
            var name = document.getElementById('signupDirectName').value.trim();
            var phoneVal = document.getElementById('signupDirectPhone').value.replace(/\D/g, '');
            var password = document.getElementById('signupDirectPassword').value;
            var remember = document.getElementById('rwDirectRemember').checked;
            if (!name) { showError('Нэрээ оруулна уу.'); return; }
            if (phoneVal.length !== 8) { showError('8 оронтой утасны дугаар оруулна уу.'); return; }
            if (password.length < 6) { showError('Нууц үг дор хаяж 6 тэмдэгт байх ёстой.'); return; }
            var btn = document.getElementById('rwDirectBtn');
            setBusy(btn, true);
            postJson('backend/api/auth/register-direct.php', { phone: phoneVal, password: password, name: name }).then(function (res) {
                if (!res.ok || !res.data.success) {
                    setBusy(btn, false);
                    showError(res.data.error || 'Бүртгэхэд алдаа гарлаа.');
                    return;
                }
                return finishLogin(btn, res.data.token, remember);
            }).catch(function () {
                setBusy(btn, false);
                showError('Сүлжээний алдаа гарлаа.');
            });
        });
        <?php endif; ?>

        <?php if ($signupEmailEnabled): ?>
        // ── Email method ──
        var emailData = {};
        var emailStep1 = document.getElementById('rwEmailStep1');
        var emailStep2 = document.getElementById('rwEmailStep2');

        function showEmailStep(step) {
            emailStep1.style.display = step === 1 ? '' : 'none';
            emailStep2.style.display = step === 2 ? '' : 'none';
        }

        emailStep1.addEventListener('submit', function (e) {
            e.preventDefault();
            clearError();
            var email = document.getElementById('signupEmail').value.trim();
            var name = document.getElementById('signupEmailName').value.trim();
            var phoneVal = document.getElementById('signupEmailPhone').value.replace(/\D/g, '');
            var password = document.getElementById('signupEmailPassword').value;
            if (!email || email.indexOf('@') === -1) { showError('И-мэйл хаягаа зөв оруулна уу.'); return; }
            if (!name) { showError('Нэрээ оруулна уу.'); return; }
            if (phoneVal.length !== 8) { showError('8 оронтой утасны дугаар оруулна уу.'); return; }
            if (password.length < 6) { showError('Нууц үг дор хаяж 6 тэмдэгт байх ёстой.'); return; }
            emailData = { email: email, name: name, phone: phoneVal, password: password };
            var btn = document.getElementById('rwSendEmailOtpBtn');
            setBusy(btn, true);
            postJson('backend/api/auth/send-email-otp.php', { email: email, purpose: 'register' }).then(function (res) {
                setBusy(btn, false);
                if (!res.ok || !res.data.success) { showError(res.data.error || 'Код илгээхэд алдаа гарлаа.'); return; }
                document.getElementById('rwCodeEmailEcho').textContent = email;
                showEmailStep(2);
            }).catch(function () {
                setBusy(btn, false);
                showError('Сүлжээний алдаа гарлаа.');
            });
        });

        document.getElementById('rwBackToEmailBtn').addEventListener('click', function () {
            clearError();
            showEmailStep(1);
        });

        emailStep2.addEventListener('submit', function (e) {
            e.preventDefault();
            clearError();
            var code = document.getElementById('signupEmailCode').value.trim();
            var remember = document.getElementById('rwEmailRemember').checked;
            if (code.length !== 4) { showError('4 оронтой код оруулна уу.'); return; }
            var btn = document.getElementById('rwVerifyEmailOtpBtn');
            setBusy(btn, true);
            postJson('backend/api/auth/verify-email-otp.php', { email: emailData.email, otp: code }).then(function (res) {
                if (!res.ok) { setBusy(btn, false); showError(res.data.error || 'Код буруу байна.'); return; }
                return postJson('backend/api/auth/register-email.php', emailData).then(function (regRes) {
                    if (!regRes.ok || !regRes.data.token) {
                        setBusy(btn, false);
                        showError(regRes.data.error || 'Бүртгэхэд алдаа гарлаа.');
                        return;
                    }
                    return finishLogin(btn, regRes.data.token, remember);
                });
            }).catch(function () {
                setBusy(btn, false);
                showError('Сүлжээний алдаа гарлаа.');
            });
        });
        <?php endif; ?>

        // ── Social sign-up ──
        var formArea = document.getElementById('rwSignupFormArea');
        var linkArea = document.getElementById('rwSocialLinkArea');
        var socialProvider = null;
        var socialToken = null;
        var socialPhone = '';

        function finishSocialLogin(token) {
            return postJson('session-bridge', { token: token, remember: true }).then(function (bridgeRes) {
                if (bridgeRes && bridgeRes.ok && bridgeRes.data.success) {
                    window.location.href = redirectTo;
                } else {
                    showError('Сесс үүсгэхэд алдаа гарлаа.');
                }
            });
        }

        function handleSocialResponse(res) {
            if (!res.ok || !res.data.success) {
                showError((res.data && res.data.error) || 'Бүртгэхэд алдаа гарлаа.');
                return;
            }
            if (res.data.token) {
                finishSocialLogin(res.data.token);
                return;
            }
            if (res.data.needs_phone) {
                clearError();
                document.getElementById('rwSocialProviderEcho').textContent = socialProvider === 'google' ? 'Google' : 'Facebook';
                formArea.style.display = 'none';
                linkArea.style.display = 'block';
            }
        }

        var socialPhoneForm = document.getElementById('rwSocialPhoneForm');
        if (socialPhoneForm) {
            socialPhoneForm.addEventListener('submit', function (e) {
                e.preventDefault();
                clearError();
                var val = document.getElementById('rwSocialPhone').value.replace(/\D/g, '');
                if (val.length !== 8) { showError('8 оронтой утасны дугаар оруулна уу.'); return; }
                socialPhone = val;
                postJson('backend/api/auth/send-otp.php', { phone: socialPhone }).then(function (res) {
                    if (!res.ok) { showError(res.data.error || 'Код илгээхэд алдаа гарлаа.'); return; }
                    document.getElementById('rwSocialPhoneEcho').textContent = socialPhone;
                    document.getElementById('rwSocialPhoneForm').style.display = 'none';
                    document.getElementById('rwSocialCodeForm').style.display = '';
                });
            });
        }
        var socialCodeForm = document.getElementById('rwSocialCodeForm');
        if (socialCodeForm) {
            socialCodeForm.addEventListener('submit', function (e) {
                e.preventDefault();
                clearError();
                var code = document.getElementById('rwSocialCode').value.trim();
                if (code.length !== 4) { showError('4 оронтой код оруулна уу.'); return; }
                postJson('backend/api/auth/verify-otp.php', { phone: socialPhone, code: code }).then(function (res) {
                    if (!res.ok || !res.data.verified) { showError(res.data.error || 'Код буруу байна.'); return; }
                    return postJson('backend/api/auth/social-login.php', {
                        provider: socialProvider, token: socialToken, phone: socialPhone, otp_token: res.data.otp_token
                    }).then(handleSocialResponse);
                });
            });
        }

        <?php if ($signupGoogleEnabled): ?>
        window.addEventListener('load', function () {
            if (!window.google || !google.accounts) return;
            google.accounts.id.initialize({
                client_id: <?= json_encode($signupGoogleClientId) ?>,
                callback: function (response) {
                    socialProvider = 'google';
                    socialToken = response.credential;
                    postJson('backend/api/auth/social-login.php', { provider: 'google', token: socialToken }).then(handleSocialResponse);
                }
            });
            google.accounts.id.renderButton(document.getElementById('rwGoogleLoginBtn'), { theme: 'outline', size: 'large', width: 320, text: 'signup_with' });
        });
        <?php endif; ?>

        <?php if ($signupFacebookEnabled): ?>
        window.fbAsyncInit = function () {
            FB.init({ appId: <?= json_encode($signupFacebookAppId) ?>, cookie: true, xfbml: false, version: 'v19.0' });
        };
        var fbBtn = document.getElementById('rwFacebookLoginBtn');
        if (fbBtn) {
            fbBtn.addEventListener('click', function () {
                if (!window.FB) { showError('Facebook SDK ачаалагдаагүй байна. Дахин оролдоно уу.'); return; }
                FB.login(function (response) {
                    if (!response.authResponse) return;
                    socialProvider = 'facebook';
                    socialToken = response.authResponse.accessToken;
                    postJson('backend/api/auth/social-login.php', { provider: 'facebook', token: socialToken }).then(handleSocialResponse);
                }, { scope: 'public_profile,email' });
            });
        }
        <?php endif; ?>
    })();
    </script>


    <!-- ALL CATEGORIES -->


<?php require __DIR__ . '/includes/footer.php'; ?>
