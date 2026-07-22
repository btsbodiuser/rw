<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . url('account.php'));
    exit;
}

$phonePasswordEnabled      = sBool('login_phone_password_enabled', false);
$phoneOtpEnabled           = sBool('login_phone_otp_enabled', false);
$registerWithoutOtpEnabled = sBool('login_register_without_otp_enabled', false);
$emailEnabled              = sBool('login_email_enabled', false);
$registerEmailEnabled      = sBool('register_email_enabled', false);
$googleEnabled             = sBool('login_google_enabled', false);
$facebookEnabled           = sBool('login_facebook_enabled', false);
$googleClientId            = s('google_client_id', '');
$facebookAppId             = s('facebook_app_id', '');

$hasAnyPhoneMethod = $phonePasswordEnabled || $phoneOtpEnabled || $registerWithoutOtpEnabled;
$initialStep       = ($emailEnabled && !$hasAnyPhoneMethod) ? 'email' : 'phone';

$settingsJson = json_encode([
    'phone_password_enabled'       => $phonePasswordEnabled,
    'phone_otp_enabled'            => $phoneOtpEnabled,
    'register_without_otp_enabled' => $registerWithoutOtpEnabled,
    'email_enabled'                => $emailEnabled,
    'register_email_enabled'       => $registerEmailEnabled,
    'google_enabled'                => $googleEnabled && $googleClientId !== '',
    'facebook_enabled'              => $facebookEnabled && $facebookAppId !== '',
    'google_client_id'              => $googleClientId,
    'facebook_app_id'                => $facebookAppId,
    'has_any_phone_method'          => $hasAnyPhoneMethod,
    'initial_step'                   => $initialStep,
], JSON_UNESCAPED_SLASHES);

$page_title = 'Нэвтрэх';
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Нэвтрэх</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Нэвтрэх</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- Login -->
        <section class="flat-spacing">
            <div class="container">
                <div style="max-width:460px;margin:0 auto;">

                    <button type="button" id="auth-back" style="display:none;background:none;border:none;padding:0;margin-bottom:14px;align-items:center;gap:6px;cursor:pointer;color:#6b7280;font-size:.9rem;">
                        <i class="icon icon-caret-left"></i> Буцах
                    </button>

                    <h1 class="heading" id="auth-title">Нэвтрэх</h1>
                    <p class="h6 text-sub" id="auth-subtitle" style="margin-bottom:20px;"></p>

                    <div id="auth-alert" class="alert" style="display:none;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:.9rem;"></div>

                    <!-- PHONE -->
                    <div class="auth-step" data-step="phone">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset>
                                    <input type="text" id="phone_input" placeholder="Утасны дугаар *" inputmode="tel" autocomplete="tel">
                                </fieldset>
                            </div>
                            <div style="margin-bottom:14px;">
                                <a href="#" id="phone-forgot-link" class="h6 link" style="text-decoration:underline;">Нууц үг мартсан уу?</a>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-phone-submit">
                                <span class="btn-text">Үргэлжлүүлэх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                        <div id="phone-alt-methods" style="margin-top:18px;text-align:center;"></div>
                    </div>

                    <!-- EMAIL -->
                    <div class="auth-step" data-step="email">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset>
                                    <input type="email" id="email_input" placeholder="И-мэйл хаяг *" autocomplete="email">
                                </fieldset>
                            </div>
                            <div style="margin-bottom:14px;">
                                <a href="#" id="email-forgot-link" class="h6 link" style="text-decoration:underline;">Нууц үг мартсан уу?</a>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-email-submit">
                                <span class="btn-text">Үргэлжлүүлэх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                        <div id="email-alt-methods" style="margin-top:18px;text-align:center;"></div>
                    </div>

                    <!-- PHONE OTP -->
                    <div class="auth-step" data-step="otp">
                        <div class="otp-boxes" id="otp-boxes-phone" style="display:flex;gap:10px;margin-bottom:16px;"></div>
                        <div style="margin-bottom:14px;font-size:.85rem;color:#6b7280;" id="otp-resend-wrap-phone">
                            Код ирээгүй юу? <span id="otp-timer-phone">180</span>с дараа <a href="#" id="otp-resend-phone" style="display:none;text-decoration:underline;">дахин илгээх</a>
                        </div>
                        <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-otp-phone-submit">
                            <span class="btn-text">Баталгаажуулах</span>
                            <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                        </button>
                    </div>

                    <!-- EMAIL OTP -->
                    <div class="auth-step" data-step="email-otp">
                        <div class="otp-boxes" id="otp-boxes-email" style="display:flex;gap:10px;margin-bottom:16px;"></div>
                        <div style="margin-bottom:14px;font-size:.85rem;color:#6b7280;" id="otp-resend-wrap-email">
                            Код ирээгүй юу? <span id="otp-timer-email">180</span>с дараа <a href="#" id="otp-resend-email" style="display:none;text-decoration:underline;">дахин илгээх</a>
                        </div>
                        <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-otp-email-submit">
                            <span class="btn-text">Баталгаажуулах</span>
                            <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                        </button>
                    </div>

                    <!-- PHONE PASSWORD -->
                    <div class="auth-step" data-step="password">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="password_input" placeholder="Нууц үг *" autocomplete="current-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-password-submit">
                                <span class="btn-text">Нэвтрэх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>

                    <!-- EMAIL PASSWORD -->
                    <div class="auth-step" data-step="email-password">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="email_password_input" placeholder="Нууц үг *" autocomplete="current-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-email-password-submit">
                                <span class="btn-text">Нэвтрэх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>

                    <!-- PHONE SET PASSWORD (register / reset) -->
                    <div class="auth-step" data-step="set-password">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset id="set-password-name-field" style="display:none;">
                                    <input type="text" id="set_password_name" placeholder="Нэр *">
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="set_password_new" placeholder="Шинэ нууц үг *" autocomplete="new-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="set_password_confirm" placeholder="Нууц үг давтах *" autocomplete="new-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-set-password-submit">
                                <span class="btn-text">Хадгалах</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>

                    <!-- EMAIL SET PASSWORD (register / reset) -->
                    <div class="auth-step" data-step="email-set-password">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset id="email-set-password-name-field" style="display:none;">
                                    <input type="text" id="email_set_password_name" placeholder="Нэр *">
                                </fieldset>
                                <fieldset id="email-set-password-phone-field" style="display:none;">
                                    <input type="text" id="email_set_password_phone" placeholder="Утасны дугаар *" inputmode="tel">
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="email_set_password_new" placeholder="Шинэ нууц үг *" autocomplete="new-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="email_set_password_confirm" placeholder="Нууц үг давтах *" autocomplete="new-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-email-set-password-submit">
                                <span class="btn-text">Хадгалах</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>

                    <!-- REGISTER CHOOSE -->
                    <div class="auth-step" data-step="register-choose">
                        <div class="list-ver" style="display:flex;flex-direction:column;gap:12px;">
                            <button type="button" class="tf-btn animate-btn w-100" id="btn-choose-direct">Утасны дугаараар шууд бүртгүүлэх</button>
                            <button type="button" class="tf-btn style-line w-100" id="btn-choose-otp">SMS кодоор баталгаажуулж бүртгүүлэх</button>
                        </div>
                    </div>

                    <!-- DIRECT REGISTER (phone) -->
                    <div class="auth-step" data-step="direct-register">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset>
                                    <input type="text" id="direct_reg_name" placeholder="Нэр *">
                                </fieldset>
                                <fieldset>
                                    <input type="text" id="direct_reg_phone" placeholder="Утасны дугаар *" inputmode="tel">
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="direct_reg_password" placeholder="Нууц үг *" autocomplete="new-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="direct_reg_confirm" placeholder="Нууц үг давтах *" autocomplete="new-password">
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-direct-register-submit">
                                <span class="btn-text">Бүртгүүлэх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>

                    <!-- SOCIAL PHONE -->
                    <div class="auth-step" data-step="social-phone">
                        <form class="form-login" onsubmit="return false;">
                            <div class="list-ver">
                                <fieldset>
                                    <input type="text" id="social_phone_input" placeholder="Утасны дугаар *" inputmode="tel">
                                </fieldset>
                            </div>
                            <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-social-phone-submit">
                                <span class="btn-text">Код илгээх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>

                    <!-- SOCIAL OTP -->
                    <div class="auth-step" data-step="social-otp">
                        <div class="otp-boxes" id="otp-boxes-social" style="display:flex;gap:10px;margin-bottom:16px;"></div>
                        <div style="margin-bottom:14px;font-size:.85rem;color:#6b7280;" id="otp-resend-wrap-social">
                            Код ирээгүй юу? <span id="otp-timer-social">180</span>с дараа <a href="#" id="otp-resend-social" style="display:none;text-decoration:underline;">дахин илгээх</a>
                        </div>
                        <button type="button" class="tf-btn animate-btn w-100 auth-submit-btn" id="btn-otp-social-submit">
                            <span class="btn-text">Баталгаажуулах</span>
                            <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                        </button>
                    </div>

                    <div style="text-align:center;margin-top:26px;font-size:.9rem;color:#6b7280;">
                        Бүртгэлгүй юу? Утас эсвэл и-мэйлээ оруулснаар бид танд тохирсон бүртгүүлэх аргыг санал болгоно.
                    </div>

                </div>
            </div>
        </section>
        <!-- /Login -->

<?php
$baseUrlJson = json_encode(getBaseUrl());
$extra_scripts = <<<JS
<script>
(function () {
    const BASE = {$baseUrlJson};
    const SETTINGS = {$settingsJson};

    const params = new URLSearchParams(window.location.search);
    const redirect = params.get('redirect') || BASE + 'account';

    const state = {
        step: SETTINGS.initial_step,
        prevSteps: [],
        isForgotPassword: false,
        isNewUser: false,
        phone: '',
        email: '',
        otpToken: '',
        emailOtpCode: '',
        socialProvider: '',
        socialToken: '',
        socialProfile: null
    };

    const STEP_META = {
        'phone':               ['Нэвтрэх', 'Утасны дугаараа оруулж vргэлжлvvлнэ vv'],
        'email':               ['Нэвтрэх', 'И-мэйл хаягаа оруулж vргэлжлvvлнэ vv'],
        'otp':                 ['Баталгаажуулах код', 'Таны утсанд илгээсэн 4 оронтой кодыг оруулна уу'],
        'email-otp':           ['Баталгаажуулах код', 'Таны и-мэйл хаяг руу илгээсэн 4 оронтой кодыг оруулна уу'],
        'password':            ['Нэвтрэх', 'Нууц vгээ оруулна уу'],
        'email-password':      ['Нэвтрэх', 'Нууц vгээ оруулна уу'],
        'set-password':        ['', ''],
        'email-set-password':  ['', ''],
        'register-choose':     ['Бvртгvvлэх', 'Та бvртгэлээ хэрхэн баталгаажуулах вэ?'],
        'direct-register':     ['Бvртгэл vvсгэх', 'Мэдээллээ бөглөж бvртгvvлнэ vv'],
        'social-phone':        ['Утасны дугаар холбох', 'Бvртгэлээ баталгаажуулахын тулд утасны дугаараа оруулна уу'],
        'social-otp':          ['Баталгаажуулах код', 'Таны утсанд илгээсэн кодыг оруулна уу']
    };

    function showAlert(msg, type) {
        const el = document.getElementById('auth-alert');
        el.style.display = 'block';
        el.style.background = type === 'success' ? '#d1fae5' : '#fee2e2';
        el.style.color = type === 'success' ? '#065f46' : '#991b1b';
        el.style.border = type === 'success' ? '1px solid #6ee7b7' : '1px solid #fca5a5';
        el.textContent = msg;
    }
    function hideAlert() {
        document.getElementById('auth-alert').style.display = 'none';
    }
    function setLoading(btn, loading) {
        btn.querySelector('.btn-text').style.display = loading ? 'none' : '';
        btn.querySelector('.btn-loading').style.display = loading ? '' : 'none';
        btn.disabled = loading;
    }
    function apiPost(path, body) {
        return fetch(BASE + path, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        }).then(async r => {
            let data = {};
            try { data = await r.json(); } catch (e) {}
            return {ok: r.ok, status: r.status, data: data};
        });
    }
    function errMsg(data, fallback) {
        return data.message || data.error || fallback;
    }
    function cleanPhone(v) {
        return (v || '').replace(/\\D/g, '');
    }
    function isValidEmail(v) {
        return /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+\$/.test(v || '');
    }

    function doSession(token, user) {
        return fetch(BASE + 'login-action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({token: token, user: user})
        }).then(r => r.json()).then(data => {
            if (data.success) {
                window.location.href = redirect;
            }
        });
    }

    function goToStep(step, opts) {
        opts = opts || {};
        if (opts.push !== false) state.prevSteps.push(state.step);
        state.step = step;
        hideAlert();
        document.querySelectorAll('.auth-step').forEach(el => {
            el.style.display = el.dataset.step === step ? '' : 'none';
        });
        const meta = STEP_META[step] || ['', ''];
        document.getElementById('auth-title').textContent = meta[0];
        document.getElementById('auth-subtitle').textContent = meta[1];

        if (step === 'set-password') {
            document.getElementById('auth-title').textContent = state.isNewUser ? 'Бvртгэл vvсгэх' : 'Шинэ нууц vг';
            document.getElementById('auth-subtitle').textContent = state.isNewUser ? 'Нэр болон нууц vгээ оруулна уу' : 'Шинэ нууц vгээ оруулна уу';
            document.getElementById('set-password-name-field').style.display = state.isNewUser ? '' : 'none';
        }
        if (step === 'email-set-password') {
            document.getElementById('auth-title').textContent = state.isNewUser ? 'Бvртгэл vvсгэх' : 'Шинэ нууц vг';
            document.getElementById('auth-subtitle').textContent = state.isNewUser ? 'Мэдээллээ бөглөж бvртгvvлнэ vv' : 'Шинэ нууц vгээ оруулна уу';
            document.getElementById('email-set-password-name-field').style.display = state.isNewUser ? '' : 'none';
            document.getElementById('email-set-password-phone-field').style.display = state.isNewUser ? '' : 'none';
        }

        document.getElementById('auth-back').style.display =
            (step === 'phone' || step === 'email') ? 'none' : 'flex';

        if (step === 'otp') startResendTimer('phone', resendPhoneOtp);
        if (step === 'email-otp') startResendTimer('email', resendEmailOtp);
        if (step === 'social-otp') startResendTimer('social', resendSocialOtp);
    }

    document.getElementById('auth-back').addEventListener('click', function () {
        const prev = state.prevSteps.pop() || SETTINGS.initial_step;
        goToStep(prev, {push: false});
    });

    // ---------- OTP boxes ----------
    function renderOtpBoxes(containerId) {
        const box = document.getElementById(containerId);
        box.innerHTML = '';
        for (let i = 0; i < 4; i++) {
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.inputMode = 'numeric';
            inp.maxLength = 1;
            inp.className = 'otp-box-input';
            inp.style.cssText = 'width:56px;height:56px;text-align:center;font-size:1.4rem;border:1px solid #e5e7eb;border-radius:8px;';
            inp.addEventListener('input', function () {
                this.value = this.value.replace(/\\D/g, '');
                if (this.value && this.nextElementSibling) this.nextElementSibling.focus();
            });
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !this.value && this.previousElementSibling) {
                    this.previousElementSibling.focus();
                }
            });
            inp.addEventListener('paste', function (e) {
                e.preventDefault();
                const digits = (e.clipboardData.getData('text') || '').replace(/\\D/g, '').split('');
                const inputs = Array.from(box.children);
                inputs.forEach((el, idx) => { el.value = digits[idx] || ''; });
                (inputs[Math.min(digits.length, 4) - 1] || inputs[0]).focus();
            });
            box.appendChild(inp);
        }
    }
    function getOtpValue(containerId) {
        return Array.from(document.getElementById(containerId).children).map(i => i.value).join('');
    }
    function clearOtpBoxes(containerId) {
        Array.from(document.getElementById(containerId).children).forEach(i => i.value = '');
    }
    ['otp-boxes-phone', 'otp-boxes-email', 'otp-boxes-social'].forEach(renderOtpBoxes);

    const timers = {};
    function startResendTimer(prefix, resendFn) {
        clearInterval(timers[prefix]);
        let secs = 180;
        const timerEl = document.getElementById('otp-timer-' + prefix);
        const linkEl = document.getElementById('otp-resend-' + prefix);
        linkEl.style.display = 'none';
        timerEl.style.display = '';
        timerEl.textContent = secs;
        timers[prefix] = setInterval(() => {
            secs--;
            if (secs <= 0) {
                clearInterval(timers[prefix]);
                timerEl.style.display = 'none';
                linkEl.style.display = '';
            } else {
                timerEl.textContent = secs;
            }
        }, 1000);
        linkEl.onclick = function (e) {
            e.preventDefault();
            resendFn();
        };
    }
    function resendPhoneOtp() {
        apiPost('backend/api/auth/send-otp.php', {phone: state.phone}).then(({ok, data}) => {
            if (ok) { clearOtpBoxes('otp-boxes-phone'); startResendTimer('phone', resendPhoneOtp); }
            else showAlert(errMsg(data, 'Код илгээхэд алдаа гарлаа.'), 'error');
        });
    }
    function resendEmailOtp() {
        const purpose = state.isForgotPassword ? 'reset' : 'register';
        apiPost('backend/api/auth/send-email-otp.php', {email: state.email, purpose: purpose}).then(({ok, data}) => {
            if (ok) { clearOtpBoxes('otp-boxes-email'); startResendTimer('email', resendEmailOtp); }
            else showAlert(errMsg(data, 'Код илгээхэд алдаа гарлаа.'), 'error');
        });
    }
    function resendSocialOtp() {
        apiPost('backend/api/auth/send-otp.php', {phone: state.phone}).then(({ok, data}) => {
            if (ok) { clearOtpBoxes('otp-boxes-social'); startResendTimer('social', resendSocialOtp); }
            else showAlert(errMsg(data, 'Код илгээхэд алдаа гарлаа.'), 'error');
        });
    }

    // ---------- Alt method links (email/phone toggle + social buttons) ----------
    function renderAltMethods() {
        const phoneWrap = document.getElementById('phone-alt-methods');
        const emailWrap = document.getElementById('email-alt-methods');
        let html = '';
        if (SETTINGS.has_any_phone_method && SETTINGS.email_enabled) {
            html += '<div style="margin-bottom:12px;"><a href="#" class="h6 link auth-switch-email" style="text-decoration:underline;">И-мэйлээр нэвтрэх</a></div>';
        }
        html += renderSocialButtons();
        phoneWrap.innerHTML = html;

        let ehtml = '';
        if (SETTINGS.has_any_phone_method && SETTINGS.email_enabled) {
            ehtml += '<div style="margin-bottom:12px;"><a href="#" class="h6 link auth-switch-phone" style="text-decoration:underline;">Утасны дугаараар нэвтрэх</a></div>';
        }
        ehtml += renderSocialButtons();
        emailWrap.innerHTML = ehtml;

        document.querySelectorAll('.auth-switch-email').forEach(a => a.addEventListener('click', e => {
            e.preventDefault(); state.isForgotPassword = false; goToStep('email');
        }));
        document.querySelectorAll('.auth-switch-phone').forEach(a => a.addEventListener('click', e => {
            e.preventDefault(); state.isForgotPassword = false; goToStep('phone');
        }));
        document.querySelectorAll('.auth-google-btn').forEach(b => b.addEventListener('click', handleGoogleLogin));
        document.querySelectorAll('.auth-facebook-btn').forEach(b => b.addEventListener('click', handleFacebookLogin));
    }
    function renderSocialButtons() {
        if (!SETTINGS.google_enabled && !SETTINGS.facebook_enabled) return '';
        let html = '<div style="display:flex;flex-direction:column;gap:10px;margin-top:6px;">';
        if (SETTINGS.google_enabled) {
            html += '<button type="button" class="tf-btn style-line w-100 auth-google-btn"><i class="icon icon-google"></i> Google-ээр нэвтрэх</button>';
        }
        if (SETTINGS.facebook_enabled) {
            html += '<button type="button" class="tf-btn style-line w-100 auth-facebook-btn"><i class="icon icon-facebook"></i> Facebook-ээр нэвтрэх</button>';
        }
        html += '</div>';
        return html;
    }
    renderAltMethods();

    // ---------- PHONE step ----------
    document.getElementById('phone-forgot-link').addEventListener('click', function (e) {
        e.preventDefault();
        state.isForgotPassword = !state.isForgotPassword;
        this.textContent = state.isForgotPassword ? 'Энгийн нэвтрэх рvv буцах' : 'Нууц vг мартсан уу?';
        document.querySelector('#btn-phone-submit .btn-text').textContent =
            state.isForgotPassword ? 'Нууц vг сэргээх' : 'Vргэлжлvvлэх';
    });

    document.getElementById('btn-phone-submit').addEventListener('click', function () {
        hideAlert();
        const phone = cleanPhone(document.getElementById('phone_input').value);
        if (phone.length < 8) { showAlert('Утасны дугаараа зөв оруулна уу.', 'error'); return; }
        if (!SETTINGS.has_any_phone_method) { showAlert('Одоогоор утасны дугаараар нэвтрэх боломжгvй байна.', 'error'); return; }

        state.phone = phone;
        const btn = this;
        setLoading(btn, true);

        apiPost('backend/api/auth/check-phone.php', {phone: phone}).then(({ok, data}) => {
            setLoading(btn, false);
            if (!ok) { showAlert(errMsg(data, 'Алдаа гарлаа.'), 'error'); return; }

            const exists = !!data.exists;
            const hasPassword = !!data.has_password;
            const passwordLoginAllowed = SETTINGS.phone_password_enabled || SETTINGS.register_without_otp_enabled;

            if (state.isForgotPassword) {
                if (!exists) { showAlert('Энэ дугаараар бvртгэл олдсонгvй.', 'error'); return; }
                if (!SETTINGS.phone_otp_enabled) {
                    showAlert('Нууц vг сэргээх боломжгvй байна. Манай дэмжлэгийн багтай холбогдоно уу.', 'error');
                    return;
                }
                state.isNewUser = false;
                sendPhoneOtpAndGo();
                return;
            }

            if (exists && hasPassword && passwordLoginAllowed) {
                goToStep('password');
                return;
            }
            if (exists && !hasPassword) {
                if (!SETTINGS.phone_otp_enabled) { showAlert('Нэвтрэх боломжгvй байна.', 'error'); return; }
                state.isNewUser = false;
                sendPhoneOtpAndGo();
                return;
            }
            if (exists) {
                showAlert('Нэвтрэх боломжгvй байна. Дэмжлэгийн багтай холбогдоно уу.', 'error');
                return;
            }

            // New user — decide registration path
            state.isNewUser = true;
            if (SETTINGS.phone_otp_enabled && SETTINGS.register_without_otp_enabled) {
                goToStep('register-choose');
            } else if (SETTINGS.register_without_otp_enabled) {
                document.getElementById('direct_reg_phone').value = phone;
                goToStep('direct-register');
            } else if (SETTINGS.phone_otp_enabled) {
                sendPhoneOtpAndGo();
            } else {
                showAlert('Бvртгэл vvсгэх боломжгvй байна.', 'error');
            }
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    function sendPhoneOtpAndGo() {
        apiPost('backend/api/auth/send-otp.php', {phone: state.phone}).then(({ok, data}) => {
            if (ok) { clearOtpBoxes('otp-boxes-phone'); goToStep('otp'); }
            else showAlert(errMsg(data, 'Код илгээхэд алдаа гарлаа.'), 'error');
        });
    }

    document.getElementById('btn-otp-phone-submit').addEventListener('click', function () {
        hideAlert();
        const code = getOtpValue('otp-boxes-phone');
        if (code.length !== 4) { showAlert('4 оронтой кодоо бvрэн оруулна уу.', 'error'); return; }
        const btn = this;
        setLoading(btn, true);
        apiPost('backend/api/auth/verify-otp.php', {phone: state.phone, code: code}).then(({ok, data}) => {
            setLoading(btn, false);
            if (!ok) { showAlert(errMsg(data, 'Код буруу байна.'), 'error'); return; }
            state.otpToken = data.otp_token;
            goToStep('set-password');
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    document.getElementById('btn-password-submit').addEventListener('click', function () {
        hideAlert();
        const password = document.getElementById('password_input').value;
        if (!password) { showAlert('Нууц vгээ оруулна уу.', 'error'); return; }
        const btn = this;
        setLoading(btn, true);
        apiPost('backend/api/auth/login.php', {identifier: state.phone, password: password}).then(({ok, data}) => {
            setLoading(btn, false);
            if (ok && data.token && data.user) { doSession(data.token, data.user); }
            else showAlert(errMsg(data, 'Нэвтрэхэд алдаа гарлаа. Нууц vгээ шалгана уу.'), 'error');
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    document.getElementById('btn-set-password-submit').addEventListener('click', function () {
        hideAlert();
        const name = document.getElementById('set_password_name').value.trim();
        const pw = document.getElementById('set_password_new').value;
        const confirm = document.getElementById('set_password_confirm').value;
        if (state.isNewUser && !name) { showAlert('Нэрээ оруулна уу.', 'error'); return; }
        if (pw.length < 6) { showAlert('Нууц vг хамгийн багадаа 6 тэмдэгт байх ёстой.', 'error'); return; }
        if (pw !== confirm) { showAlert('Нууц vг таарахгvй байна.', 'error'); return; }

        const btn = this;
        setLoading(btn, true);
        const req = state.isNewUser
            ? apiPost('backend/api/auth/register.php', {phone: state.phone, otp_token: state.otpToken, password: pw, name: name})
            : apiPost('backend/api/auth/reset-password.php', {phone: state.phone, otp_token: state.otpToken, password: pw});
        req.then(({ok, data}) => {
            setLoading(btn, false);
            if (ok && data.token && data.user) { doSession(data.token, data.user); }
            else showAlert(errMsg(data, 'Алдаа гарлаа.'), 'error');
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    // ---------- REGISTER CHOOSE / DIRECT REGISTER ----------
    document.getElementById('btn-choose-direct').addEventListener('click', function () {
        document.getElementById('direct_reg_phone').value = state.phone;
        goToStep('direct-register');
    });
    document.getElementById('btn-choose-otp').addEventListener('click', function () {
        sendPhoneOtpAndGo();
    });

    document.getElementById('btn-direct-register-submit').addEventListener('click', function () {
        hideAlert();
        const name = document.getElementById('direct_reg_name').value.trim();
        const phone = cleanPhone(document.getElementById('direct_reg_phone').value);
        const pw = document.getElementById('direct_reg_password').value;
        const confirm = document.getElementById('direct_reg_confirm').value;
        if (!name || phone.length < 8 || !pw) { showAlert('Бvх талбарыг бөглөнө vv.', 'error'); return; }
        if (pw !== confirm) { showAlert('Нууц vг таарахгvй байна.', 'error'); return; }
        if (pw.length < 6) { showAlert('Нууц vг хамгийн багадаа 6 тэмдэгт байх ёстой.', 'error'); return; }

        const btn = this;
        setLoading(btn, true);
        apiPost('backend/api/auth/register-direct.php', {name: name, phone: phone, password: pw}).then(({ok, data}) => {
            setLoading(btn, false);
            if (ok && data.token && data.user) { doSession(data.token, data.user); }
            else showAlert(errMsg(data, 'Бvртгэлд алдаа гарлаа.'), 'error');
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    // ---------- EMAIL step ----------
    document.getElementById('email-forgot-link').addEventListener('click', function (e) {
        e.preventDefault();
        state.isForgotPassword = !state.isForgotPassword;
        this.textContent = state.isForgotPassword ? 'Энгийн нэвтрэх рvv буцах' : 'Нууц vг мартсан уу?';
        document.querySelector('#btn-email-submit .btn-text').textContent =
            state.isForgotPassword ? 'Нууц vг сэргээх' : 'Vргэлжлvvлэх';
    });

    document.getElementById('btn-email-submit').addEventListener('click', function () {
        hideAlert();
        const email = document.getElementById('email_input').value.trim();
        if (!isValidEmail(email)) { showAlert('И-мэйл хаягаа зөв оруулна уу.', 'error'); return; }
        if (!SETTINGS.email_enabled) { showAlert('Одоогоор и-мэйлээр нэвтрэх боломжгvй байна.', 'error'); return; }
        state.email = email;
        const btn = this;
        setLoading(btn, true);

        if (state.isForgotPassword) {
            apiPost('backend/api/auth/send-email-otp.php', {email: email, purpose: 'reset'}).then(({ok, data, status}) => {
                setLoading(btn, false);
                if (ok) { state.isNewUser = false; clearOtpBoxes('otp-boxes-email'); goToStep('email-otp'); }
                else if (status === 404) {
                    showAlert('Энэ и-мэйл хаягаар бvртгэл олдсонгvй.', 'error');
                } else {
                    showAlert(errMsg(data, 'Код илгээхэд алдаа гарлаа.'), 'error');
                }
            }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
            return;
        }

        if (SETTINGS.register_email_enabled) {
            apiPost('backend/api/auth/send-email-otp.php', {email: email, purpose: 'register'}).then(({ok, data, status}) => {
                setLoading(btn, false);
                if (ok) {
                    state.isNewUser = true;
                    clearOtpBoxes('otp-boxes-email');
                    goToStep('email-otp');
                } else if (status === 409) {
                    goToStep('email-password');
                } else {
                    showAlert(errMsg(data, 'Алдаа гарлаа.'), 'error');
                }
            }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
        } else {
            setLoading(btn, false);
            goToStep('email-password');
        }
    });

    document.getElementById('btn-otp-email-submit').addEventListener('click', function () {
        hideAlert();
        const code = getOtpValue('otp-boxes-email');
        if (code.length !== 4) { showAlert('4 оронтой кодоо бvрэн оруулна уу.', 'error'); return; }
        const btn = this;

        if (state.isForgotPassword) {
            // reset-password-email.php verifies the raw OTP code itself — keep it, skip re-verification.
            state.emailOtpCode = code;
            goToStep('email-set-password');
            return;
        }

        setLoading(btn, true);
        apiPost('backend/api/auth/verify-email-otp.php', {email: state.email, otp: code}).then(({ok, data}) => {
            setLoading(btn, false);
            if (!ok) { showAlert(errMsg(data, 'Код буруу байна.'), 'error'); return; }
            state.isNewUser = true;
            goToStep('email-set-password');
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    document.getElementById('btn-email-password-submit').addEventListener('click', function () {
        hideAlert();
        const password = document.getElementById('email_password_input').value;
        if (!password) { showAlert('Нууц vгээ оруулна уу.', 'error'); return; }
        const btn = this;
        setLoading(btn, true);
        apiPost('backend/api/auth/login.php', {identifier: state.email, password: password}).then(({ok, data}) => {
            setLoading(btn, false);
            if (ok && data.token && data.user) { doSession(data.token, data.user); }
            else showAlert(errMsg(data, 'Нэвтрэхэд алдаа гарлаа. Нууц vгээ шалгана уу.'), 'error');
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    document.getElementById('btn-email-set-password-submit').addEventListener('click', function () {
        hideAlert();
        const pw = document.getElementById('email_set_password_new').value;
        const confirm = document.getElementById('email_set_password_confirm').value;
        if (pw.length < 6) { showAlert('Нууц vг хамгийн багадаа 6 тэмдэгт байх ёстой.', 'error'); return; }
        if (pw !== confirm) { showAlert('Нууц vг таарахгvй байна.', 'error'); return; }

        const btn = this;

        if (state.isNewUser) {
            const name = document.getElementById('email_set_password_name').value.trim();
            const phone = cleanPhone(document.getElementById('email_set_password_phone').value);
            if (!name) { showAlert('Нэрээ оруулна уу.', 'error'); return; }
            if (phone.length < 8) { showAlert('Утасны дугаараа зөв оруулна уу.', 'error'); return; }
            setLoading(btn, true);
            apiPost('backend/api/auth/register-email.php', {email: state.email, name: name, phone: phone, password: pw}).then(({ok, data}) => {
                setLoading(btn, false);
                if (ok && data.token && data.user) { doSession(data.token, data.user); }
                else showAlert(errMsg(data, 'Бvртгэлд алдаа гарлаа.'), 'error');
            }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
        } else {
            setLoading(btn, true);
            apiPost('backend/api/auth/reset-password-email.php', {email: state.email, otp: state.emailOtpCode, new_password: pw, password: pw}).then(({ok, data}) => {
                setLoading(btn, false);
                if (ok) {
                    showAlert('Нууц vг амжилттай солигдлоо. Одоо нэвтэрнэ vv.', 'success');
                    setTimeout(() => { goToStep('email-password', {push: false}); }, 1200);
                } else {
                    showAlert(errMsg(data, 'Алдаа гарлаа.'), 'error');
                }
            }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
        }
    });

    // ---------- Social login ----------
    let gsiLoaded = false, fbLoaded = false;
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = src; s.async = true; s.defer = true;
            s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }
    function handleGoogleLogin() {
        if (!SETTINGS.google_enabled) return;
        const ready = gsiLoaded ? Promise.resolve() : loadScript('https://accounts.google.com/gsi/client').then(() => { gsiLoaded = true; });
        ready.then(() => {
            google.accounts.id.initialize({
                client_id: SETTINGS.google_client_id,
                callback: (resp) => completeSocialLogin('google', resp.credential)
            });
            google.accounts.id.prompt();
        }).catch(() => showAlert('Google нэвтрэлт ачаалахад алдаа гарлаа.', 'error'));
    }
    function handleFacebookLogin() {
        if (!SETTINGS.facebook_enabled) return;
        const ready = fbLoaded ? Promise.resolve() : new Promise((resolve, reject) => {
            window.fbAsyncInit = function () {
                FB.init({appId: SETTINGS.facebook_app_id, cookie: true, xfbml: false, version: 'v19.0'});
                fbLoaded = true;
                resolve();
            };
            loadScript('https://connect.facebook.net/en_US/sdk.js').catch(reject);
        });
        ready.then(() => {
            FB.login((response) => {
                if (response.authResponse) {
                    completeSocialLogin('facebook', response.authResponse.accessToken);
                } else {
                    showAlert('Facebook нэвтрэлт цуцлагдлаа.', 'error');
                }
            }, {scope: 'public_profile,email'});
        }).catch(() => showAlert('Facebook нэвтрэлт ачаалахад алдаа гарлаа.', 'error'));
    }
    function completeSocialLogin(provider, token, phone, otpToken) {
        state.socialProvider = provider;
        state.socialToken = token;
        apiPost('backend/api/auth/social-login.php', {provider: provider, token: token, phone: phone, otp_token: otpToken}).then(({ok, data}) => {
            if (ok && data.token && data.user) {
                doSession(data.token, data.user);
            } else if (ok && data.needs_phone) {
                state.socialProfile = data.social_profile || null;
                goToStep('social-phone');
            } else {
                showAlert(errMsg(data, 'Нэвтрэхэд алдаа гарлаа.'), 'error');
            }
        }).catch(() => showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'));
    }
    document.getElementById('btn-social-phone-submit').addEventListener('click', function () {
        hideAlert();
        const phone = cleanPhone(document.getElementById('social_phone_input').value);
        if (phone.length < 8) { showAlert('Утасны дугаараа зөв оруулна уу.', 'error'); return; }
        state.phone = phone;
        const btn = this;
        setLoading(btn, true);
        apiPost('backend/api/auth/send-otp.php', {phone: phone}).then(({ok, data}) => {
            setLoading(btn, false);
            if (ok) { clearOtpBoxes('otp-boxes-social'); goToStep('social-otp'); }
            else showAlert(errMsg(data, 'Код илгээхэд алдаа гарлаа.'), 'error');
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });
    document.getElementById('btn-otp-social-submit').addEventListener('click', function () {
        hideAlert();
        const code = getOtpValue('otp-boxes-social');
        if (code.length !== 4) { showAlert('4 оронтой кодоо бvрэн оруулна уу.', 'error'); return; }
        const btn = this;
        setLoading(btn, true);
        apiPost('backend/api/auth/verify-otp.php', {phone: state.phone, code: code}).then(({ok, data}) => {
            if (!ok) { setLoading(btn, false); showAlert(errMsg(data, 'Код буруу байна.'), 'error'); return; }
            completeSocialLogin(state.socialProvider, state.socialToken, state.phone, data.otp_token);
            setLoading(btn, false);
        }).catch(() => { setLoading(btn, false); showAlert('Сvлжээний алдаа. Дахин оролдоно уу.', 'error'); });
    });

    // ---------- init ----------
    goToStep(SETTINGS.initial_step, {push: false});
}());
</script>
JS;
require_once __DIR__ . '/includes/footer.php';
?>
