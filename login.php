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

// ── LOGIN ─────────────────────────────────────────────────────
$page_title = 'Нэвтрэх — ' . $siteName;

$loginRedirect = (string)($_GET['redirect'] ?? $urlAccount);
if ($loginRedirect === '' || (!str_starts_with($loginRedirect, getBaseUrl()) && !str_starts_with($loginRedirect, '/'))) {
    $loginRedirect = $urlAccount;
}
$loginError = trim((string)($_GET['error'] ?? ''));

if ($loggedIn) {
    header('Location: ' . $loginRedirect);
    exit;
}

// Which identifier types the admin has approved for login (backend/pages/settings.php → "Нэвтрэх тохиргоо")
$loginPhoneEnabled = sBool('login_phone_password_enabled', false);
$loginEmailEnabled = sBool('login_email_enabled', false);
if (!$loginPhoneEnabled && !$loginEmailEnabled) {
    // Nothing configured — fail open on phone so the page still works rather than dead-ending.
    $loginPhoneEnabled = true;
}
$loginDefaultMethod = $loginPhoneEnabled ? 'phone' : 'email';

// Social login: admin toggle AND provider credentials both required, or the button is a dead end.
$loginGoogleClientId   = s('google_client_id', '');
$loginFacebookAppId    = s('facebook_app_id', '');
$loginGoogleEnabled    = sBool('login_google_enabled', false) && $loginGoogleClientId !== '';
$loginFacebookEnabled  = sBool('login_facebook_enabled', false) && $loginFacebookAppId !== '';

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
    <!-- LOGIN MAIN -->
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
                                <h3 class="rbt-title rbt-text-bold mb--16 h6">Нэвтрэх</h3>

                                <div id="rwLoginAlert" class="alert alert-danger" style="<?= $loginError ? '' : 'display:none;' ?>" role="alert"><?= h($loginError) ?></div>

                                <div id="rwLoginFormArea">
                                <div class="rbt-tab rbt-round-shape-tab">
                                    <?php if ($loginPhoneEnabled && $loginEmailEnabled): ?>
                                    <ul class="nav nav-tabs" id="rwLoginTab" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="rwLoginTabPhoneBtn" data-bs-toggle="tab" data-bs-target="#rwLoginPanePhone" type="button" role="tab">
                                                <i class="fa-sharp fa-regular fa-phone"></i> Утасны дугаар
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="rwLoginTabEmailBtn" data-bs-toggle="tab" data-bs-target="#rwLoginPaneEmail" type="button" role="tab">
                                                <i class="fa-sharp fa-regular fa-envelope"></i> И-мэйл
                                            </button>
                                        </li>
                                    </ul>
                                    <?php endif; ?>

                                    <form method="POST" action="<?= h(url('login-action')) ?>" id="rwLoginForm">
                                        <input type="hidden" name="redirect" value="<?= h($loginRedirect) ?>">
                                        <div class="tab-content" id="rwLoginTabContent">
                                            <?php if ($loginPhoneEnabled): ?>
                                            <div class="tab-pane fade show active" id="rwLoginPanePhone" role="tabpanel">
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="loginPhoneField">Утасны дугаар<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="text" inputmode="numeric" id="loginPhoneField" name="identifier" placeholder="99112233" required>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($loginEmailEnabled): ?>
                                            <div class="tab-pane fade <?= !$loginPhoneEnabled ? 'show active' : '' ?>" id="rwLoginPaneEmail" role="tabpanel">
                                                <div class="rbt-input-field-grp">
                                                    <label class="rbt-field-label" for="loginEmailField">И-мэйл<span class="rbt-text-color-danger">*</span></label>
                                                    <input class="rbt-input-field" type="email" id="loginEmailField" name="identifier" placeholder="you@example.com" <?= $loginPhoneEnabled ? 'disabled' : 'required' ?>>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rbt-input-field-grp">
                                            <label class="rbt-field-label" for="loginPassword">Нууц үг<span class="rbt-text-color-danger">*</span></label>
                                            <input class="rbt-input-field" type="password" id="loginPassword" name="password" required>
                                        </div>
                                        <button type="submit" class="rbt-btn d-block w-100 mt--24 mb--16">Нэвтрэх</button>
                                        <div class="rbt-check-group">
                                            <input id="loginRemember" type="checkbox" name="remember" value="1">
                                            <label for="loginRemember">Нэвтэрсэн байлгах</label>
                                        </div>
                                    </form>
                                </div>

                                <?php if ($loginGoogleEnabled || $loginFacebookEnabled): ?>
                                <div class="d-flex align-items-center justify-content-center mb--24 mt--24">
                                    <hr class="rbt-separator rbt-bg-color-gray-light mb--0">
                                    <span class="pl--8 pr--8 b4 rbt-text-medium">эсвэл</span>
                                    <hr class="rbt-separator rbt-bg-color-gray-light mb--0">
                                </div>
                                <?php if ($loginFacebookEnabled): ?>
                                <button type="button" id="rwFacebookLoginBtn" class="rbt-btn rbt-btn-border rbt-social-login-btn d-block w-100 mb--16">
                                    <i class="fa-brands fa-facebook" style="color:#1877F2;"></i> Facebook-ээр нэвтрэх
                                </button>
                                <?php endif; ?>
                                <?php if ($loginGoogleEnabled): ?>
                                <div id="rwGoogleLoginBtn" class="d-flex justify-content-center mb--16"></div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <div class="rbt-login-system-switch rbt-link-hover">
                                    Бүртгэлгүй юу?
                                    <a class="rbt-switch-btn" href="<?= h(url('signup')) ?>"><span>Бүртгүүлэх</span></a>
                                </div>
                                </div>

                                <!-- Social sign-in: link an existing phone when the social account is new -->
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

    <?php if ($loginGoogleEnabled): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
    <?php if ($loginFacebookEnabled): ?>
    <script src="https://connect.facebook.net/en_US/sdk.js" async defer></script>
    <?php endif; ?>

    <script>
    (function () {
        var redirectTo = <?= json_encode($loginRedirect) ?>;
        var alertBox = document.getElementById('rwLoginAlert');
        function showError(msg) { alertBox.textContent = msg; alertBox.style.display = 'block'; }
        function clearError() { alertBox.style.display = 'none'; }
        function postJson(url, body) {
            return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); });
        }

        <?php if ($loginPhoneEnabled && $loginEmailEnabled): ?>
        // Tabs: only the visible identifier field should submit.
        var phoneField = document.getElementById('loginPhoneField');
        var emailField = document.getElementById('loginEmailField');
        document.getElementById('rwLoginTabPhoneBtn').addEventListener('shown.bs.tab', function () {
            phoneField.disabled = false; phoneField.required = true;
            emailField.disabled = true; emailField.required = false;
        });
        document.getElementById('rwLoginTabEmailBtn').addEventListener('shown.bs.tab', function () {
            emailField.disabled = false; emailField.required = true;
            phoneField.disabled = true; phoneField.required = false;
        });
        <?php endif; ?>

        // ── Social sign-in ──
        var formArea = document.getElementById('rwLoginFormArea');
        var linkArea = document.getElementById('rwSocialLinkArea');
        var socialProvider = null;
        var socialToken = null;
        var socialPhone = '';

        function finishSocialLogin(token) {
            return postJson('session-bridge', { token: token, remember: true }).then(function (res) {
                if (res.ok && res.data.success) {
                    window.location.href = redirectTo;
                } else {
                    showError('Сесс үүсгэхэд алдаа гарлаа.');
                }
            });
        }

        function handleSocialResponse(res) {
            if (!res.ok || !res.data.success) {
                showError((res.data && res.data.error) || 'Нэвтрэхэд алдаа гарлаа.');
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

        <?php if ($loginGoogleEnabled): ?>
        window.addEventListener('load', function () {
            if (!window.google || !google.accounts) return;
            google.accounts.id.initialize({
                client_id: <?= json_encode($loginGoogleClientId) ?>,
                callback: function (response) {
                    socialProvider = 'google';
                    socialToken = response.credential;
                    postJson('backend/api/auth/social-login.php', { provider: 'google', token: socialToken }).then(handleSocialResponse);
                }
            });
            google.accounts.id.renderButton(document.getElementById('rwGoogleLoginBtn'), { theme: 'outline', size: 'large', width: 320, text: 'signin_with' });
        });
        <?php endif; ?>

        <?php if ($loginFacebookEnabled): ?>
        window.fbAsyncInit = function () {
            FB.init({ appId: <?= json_encode($loginFacebookAppId) ?>, cookie: true, xfbml: false, version: 'v19.0' });
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
