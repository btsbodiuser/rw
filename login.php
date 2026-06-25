<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . url('account.php'));
    exit;
}

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

        <!-- Login / Register -->
        <section class="flat-spacing">
            <div class="container">
                <div class="s-log">
                    <!-- Left: Login -->
                    <div class="col-left">
                        <h1 class="heading">Нэвтрэх</h1>
                        <div id="login-alert" class="alert" style="display:none;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:.9rem;"></div>
                        <form class="form-login" id="loginForm" novalidate>
                            <div class="list-ver">
                                <fieldset>
                                    <input type="text" id="login_identifier" name="identifier" placeholder="Утасны дугаар эсвэл и-мэйл *" required>
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="login_password" name="password" placeholder="Нууц үг *" required>
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                            </div>
                            <button type="submit" class="tf-btn animate-btn w-100" id="btnLogin">
                                <span class="btn-text">Нэвтрэх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>

                    <!-- Right: Register -->
                    <div class="col-right">
                        <h1 class="heading">Шинэ хэрэглэгч</h1>
                        <p class="h6 text-sub">
                            Шинээр бүртгэл үүсгэж манай онлайн дэлгүүрийн бүрэн боломжийг ашигла. Захиалгаа хянах, мэдээлэл шинэчлэх боломжтой.
                        </p>
                        <div id="register-alert" class="alert" style="display:none;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:.9rem;"></div>
                        <form class="form-login" id="registerForm" novalidate>
                            <div class="list-ver">
                                <fieldset>
                                    <input type="text" id="reg_name" name="name" placeholder="Нэр *" required>
                                </fieldset>
                                <fieldset>
                                    <input type="text" id="reg_phone" name="phone" placeholder="Утасны дугаар *" required>
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="reg_password" name="password" placeholder="Нууц үг *" required>
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                                <fieldset class="password-wrapper mb-8">
                                    <input class="password-field" type="password" id="reg_confirm" name="confirm_password" placeholder="Нууц үг давтах *" required>
                                    <span class="toggle-pass icon-show-password"></span>
                                </fieldset>
                            </div>
                            <button type="submit" class="tf-btn animate-btn w-100" id="btnRegister">
                                <span class="btn-text">Бүртгүүлэх</span>
                                <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <!-- /Login / Register -->

<?php
$extra_scripts = <<<JS
<script>
(function () {
    const BASE = <?= json_encode(getBaseUrl()) ?>;

    function showAlert(elId, msg, type) {
        const el = document.getElementById(elId);
        el.style.display = 'block';
        el.style.background = type === 'success' ? '#d1fae5' : '#fee2e2';
        el.style.color = type === 'success' ? '#065f46' : '#991b1b';
        el.style.border = type === 'success' ? '1px solid #6ee7b7' : '1px solid #fca5a5';
        el.textContent = msg;
    }

    function hideAlert(elId) {
        document.getElementById(elId).style.display = 'none';
    }

    function setLoading(btn, loading) {
        btn.querySelector('.btn-text').style.display  = loading ? 'none' : '';
        btn.querySelector('.btn-loading').style.display = loading ? '' : 'none';
        btn.disabled = loading;
    }

    function doSession(token, user, redirect) {
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

    // Redirect target
    const params = new URLSearchParams(window.location.search);
    const redirect = params.get('redirect') || BASE + 'account.php';

    // Login form
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert('login-alert');
        const btn = document.getElementById('btnLogin');
        setLoading(btn, true);

        const identifier = document.getElementById('login_identifier').value.trim();
        const password   = document.getElementById('login_password').value;

        fetch(BASE + 'backend/api/auth/login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({identifier: identifier, password: password})
        })
        .then(r => r.json())
        .then(data => {
            if (data.token && data.user) {
                doSession(data.token, data.user, redirect);
            } else {
                showAlert('login-alert', data.message || 'Нэвтрэхэд алдаа гарлаа. Нэвтрэх нэр эсвэл нууц үгээ шалгана уу.', 'error');
                setLoading(btn, false);
            }
        })
        .catch(() => {
            showAlert('login-alert', 'Сүлжээний алдаа. Дахин оролдоно уу.', 'error');
            setLoading(btn, false);
        });
    });

    // Register form
    document.getElementById('registerForm').addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert('register-alert');

        const name     = document.getElementById('reg_name').value.trim();
        const phone    = document.getElementById('reg_phone').value.trim();
        const password = document.getElementById('reg_password').value;
        const confirm  = document.getElementById('reg_confirm').value;

        if (!name || !phone || !password) {
            showAlert('register-alert', 'Бүх талбарыг бөглөнө үү.', 'error');
            return;
        }
        if (password !== confirm) {
            showAlert('register-alert', 'Нууц үг таарахгүй байна.', 'error');
            return;
        }
        if (password.length < 6) {
            showAlert('register-alert', 'Нууц үг хамгийн багадаа 6 тэмдэгт байх ёстой.', 'error');
            return;
        }

        const btn = document.getElementById('btnRegister');
        setLoading(btn, true);

        fetch(BASE + 'backend/api/auth/register-direct.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name: name, phone: phone, password: password})
        })
        .then(r => r.json())
        .then(data => {
            if (data.token && data.user) {
                doSession(data.token, data.user, redirect);
            } else {
                showAlert('register-alert', data.message || 'Бүртгэлд алдаа гарлаа.', 'error');
                setLoading(btn, false);
            }
        })
        .catch(() => {
            showAlert('register-alert', 'Сүлжээний алдаа. Дахин оролдоно уу.', 'error');
            setLoading(btn, false);
        });
    });
}());
</script>
JS;
require_once __DIR__ . '/includes/footer.php';
?>
