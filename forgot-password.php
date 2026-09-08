<?php
require_once __DIR__ . '/includes/config.php';

$siteName = s('site_name', 'Runners World');

// If already logged in there's nothing to reset from here.
if (isLoggedIn()) {
    header('Location: ' . url('account'));
    exit;
}

$page_title  = 'Нууц үг сэргээх — ' . $siteName;
$extraStyles = <<<'CSS'
<style>
.mainmenu > li > a { white-space: nowrap; }
.rw-fp-card { max-width: 460px; margin: 0 auto; background:#fff; border:1px solid #eef1f5; border-radius:12px; padding: 32px; }
.rw-fp-step { display:none; }
.rw-fp-step.active { display:block; }
.rw-fp-steps { display:flex; justify-content:space-between; margin-bottom:24px; }
.rw-fp-step-pip {
    flex:1; height:4px; background:#e5e9ef; margin: 0 4px; border-radius: 2px; transition: background .2s;
}
.rw-fp-step-pip.active { background: var(--color-primary, #00B7FF); }
.rw-otp-input { letter-spacing: 12px; text-align:center; font-size:20px; }
</style>
CSS;

require __DIR__ . '/includes/header.php';
?>

<div class="rbt-component-area rbt-section-gap rbt-bg-color-white">
    <div class="container">
        <div class="rw-fp-card">
            <h2 class="title h4 text-center mb--12">Нууц үг сэргээх</h2>

            <div class="rw-fp-steps">
                <div class="rw-fp-step-pip active" data-pip="1"></div>
                <div class="rw-fp-step-pip" data-pip="2"></div>
                <div class="rw-fp-step-pip" data-pip="3"></div>
            </div>

            <div id="rwFpAlert"></div>

            <!-- Step 1: Phone -->
            <div class="rw-fp-step active" data-step="1">
                <p class="b3 text-muted mb--16">Бүртгэлтэй утасны дугаараа оруулна уу. Танд 4 оронтой код илгээх болно.</p>
                <form id="rwFpPhoneForm" novalidate>
                    <div class="rbt-input-field-grp mb--16">
                        <label class="rbt-field-label">Утасны дугаар</label>
                        <input type="tel" name="phone" id="rwFpPhone" class="rbt-input-field" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="99112233" required>
                    </div>
                    <button type="submit" class="rbt-btn d-block w-100">Код илгээх</button>
                    <p class="text-center mt--16 mb-0"><a href="<?= h($urlLogin) ?>" class="b4">← Нэвтрэх хуудас руу буцах</a></p>
                </form>
            </div>

            <!-- Step 2: OTP code -->
            <div class="rw-fp-step" data-step="2">
                <p class="b3 text-muted mb--16">Утсанд ирсэн 4 оронтой кодоо оруулна уу.</p>
                <form id="rwFpOtpForm" novalidate>
                    <div class="rbt-input-field-grp mb--16">
                        <input type="text" id="rwFpOtp" class="rbt-input-field rw-otp-input" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="••••" required>
                    </div>
                    <button type="submit" class="rbt-btn d-block w-100">Баталгаажуулах</button>
                    <p class="text-center mt--16 mb-0"><a href="#" id="rwFpResend" class="b4">Код дахин илгээх</a> · <a href="#" data-goto-step="1" class="b4">Дугаар өөрчлөх</a></p>
                </form>
            </div>

            <!-- Step 3: New password -->
            <div class="rw-fp-step" data-step="3">
                <p class="b3 text-muted mb--16">Шинэ нууц үгээ оруулна уу.</p>
                <form id="rwFpPwdForm" novalidate>
                    <div class="rbt-input-field-grp mb--16">
                        <label class="rbt-field-label">Шинэ нууц үг (доод тал нь 6 тэмдэгт)</label>
                        <input type="password" id="rwFpPwd" class="rbt-input-field" minlength="6" required>
                    </div>
                    <div class="rbt-input-field-grp mb--16">
                        <label class="rbt-field-label">Дахин оруулна уу</label>
                        <input type="password" id="rwFpPwd2" class="rbt-input-field" minlength="6" required>
                    </div>
                    <button type="submit" class="rbt-btn d-block w-100">Нууц үг шинэчлэх</button>
                </form>
            </div>

            <!-- Step 4: Done -->
            <div class="rw-fp-step" data-step="4">
                <div class="text-center py-3">
                    <i class="fa-regular fa-circle-check" style="font-size:48px;color:#16a34a;"></i>
                    <h3 class="h5 mt--16">Нууц үг амжилттай шинэчлэгдлээ</h3>
                    <p class="text-muted b3 mb--16">Одоо шинэ нууц үгээрээ нэвтэрч болно.</p>
                    <a href="<?= h($urlLogin) ?>" class="rbt-btn w-100">Нэвтрэх</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var apiBase = <?= json_encode(getBaseUrl() . 'backend/api/auth/') ?>;
    var state   = { phone: '', otpToken: '' };

    function post(url, body) {
        return fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body || {})
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, code: r.status, data: d }; }); });
    }

    var alertBox = document.getElementById('rwFpAlert');
    function alertMsg(type, msg) {
        alertBox.innerHTML = '<div class="alert alert-' + (type === 'error' ? 'danger' : type) + ' mb--16">' + msg + '</div>';
    }
    function clearAlert() { alertBox.innerHTML = ''; }

    function gotoStep(n) {
        clearAlert();
        document.querySelectorAll('.rw-fp-step').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-step') === String(n));
        });
        document.querySelectorAll('.rw-fp-step-pip').forEach(function (el) {
            el.classList.toggle('active', parseInt(el.getAttribute('data-pip'), 10) <= n);
        });
    }

    // Step nav — anchors with data-goto-step
    document.querySelectorAll('[data-goto-step]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            gotoStep(parseInt(a.getAttribute('data-goto-step'), 10));
        });
    });

    // Step 1 → send OTP
    document.getElementById('rwFpPhoneForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var phone = (document.getElementById('rwFpPhone').value || '').replace(/\D/g, '');
        if (phone.length !== 8) { alertMsg('error', 'Утас 8 оронтой байх ёстой.'); return; }
        var btn = e.target.querySelector('button[type=submit]');
        btn.disabled = true; btn.innerHTML = '<i class="fa-regular fa-spinner-third fa-spin mr--4"></i> Илгээж байна...';
        post(apiBase + 'send-otp.php', { phone: phone }).then(function (r) {
            btn.disabled = false; btn.innerHTML = 'Код илгээх';
            if (!r.ok || !r.data.success) { alertMsg('error', r.data.error || 'Код илгээхэд алдаа гарлаа.'); return; }
            state.phone = phone;
            gotoStep(2);
        });
    });

    // Resend
    document.getElementById('rwFpResend').addEventListener('click', function (e) {
        e.preventDefault();
        if (!state.phone) { gotoStep(1); return; }
        post(apiBase + 'send-otp.php', { phone: state.phone }).then(function (r) {
            if (!r.ok || !r.data.success) { alertMsg('error', r.data.error || 'Код илгээхэд алдаа гарлаа.'); return; }
            alertMsg('success', 'Шинэ код илгээлээ.');
        });
    });

    // Step 2 → verify OTP → get token
    document.getElementById('rwFpOtpForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var code = (document.getElementById('rwFpOtp').value || '').replace(/\D/g, '');
        if (code.length !== 4) { alertMsg('error', 'Код 4 оронтой.'); return; }
        var btn = e.target.querySelector('button[type=submit]');
        btn.disabled = true; btn.innerHTML = '<i class="fa-regular fa-spinner-third fa-spin mr--4"></i> Шалгаж байна...';
        post(apiBase + 'verify-otp.php', { phone: state.phone, code: code }).then(function (r) {
            btn.disabled = false; btn.innerHTML = 'Баталгаажуулах';
            if (!r.ok || !r.data.verified) { alertMsg('error', r.data.error || 'Код буруу байна.'); return; }
            state.otpToken = r.data.otp_token || '';
            if (!state.otpToken) { alertMsg('error', 'Token авч чадсангүй. Дахин оролдоно уу.'); return; }
            gotoStep(3);
        });
    });

    // Step 3 → reset password
    document.getElementById('rwFpPwdForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var p1 = document.getElementById('rwFpPwd').value;
        var p2 = document.getElementById('rwFpPwd2').value;
        if (p1.length < 6) { alertMsg('error', 'Нууц үг доод тал нь 6 тэмдэгт байх ёстой.'); return; }
        if (p1 !== p2)     { alertMsg('error', 'Хоёр нууц үг таарахгүй байна.'); return; }
        var btn = e.target.querySelector('button[type=submit]');
        btn.disabled = true; btn.innerHTML = '<i class="fa-regular fa-spinner-third fa-spin mr--4"></i> Шинэчилж байна...';
        post(apiBase + 'reset-password.php', { phone: state.phone, otp_token: state.otpToken, password: p1 }).then(function (r) {
            btn.disabled = false; btn.innerHTML = 'Нууц үг шинэчлэх';
            if (!r.ok || !r.data.success) { alertMsg('error', r.data.error || 'Шинэчлэхэд алдаа гарлаа.'); return; }
            gotoStep(4);
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
