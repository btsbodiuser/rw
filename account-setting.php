<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . url('login.php?redirect=' . urlencode(url('account-setting.php'))));
    exit;
}

$user = getSessionUser();
$activeAccountPage = 'settings';
$page_title = 'Тохиргоо';
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Тохиргоо</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('account.php') ?>" class="h6 link">Бүртгэл</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Тохиргоо</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- Account -->
        <section class="flat-spacing">
            <div class="container">
                <div class="row">
                    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>

                    <!-- Main Content -->
                    <div class="col-xl-9">
                        <div class="my-account-content">

                            <h2 class="account-title type-semibold">Хувийн мэдээлэл</h2>
                            <div id="settings-alert" class="alert" style="display:none;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:.9rem;"></div>

                            <form onsubmit="return false;">

                            <!-- Name -->
                            <div class="d-flex justify-content-between align-items-center py-3" style="border-bottom:1px solid #f1f1f1;">
                                <div id="name-display">
                                    <p class="text-small text-main mb-1">Нэр</p>
                                    <p class="h6 fw-semibold mb-0"><?= htmlspecialchars($user['name'] ?? '') ?></p>
                                </div>
                                <button type="button" id="btnEditName" class="link h6 text-primary" style="border:none;background:none;">Засах</button>
                            </div>
                            <div id="name-edit" style="display:none;padding:12px 0;border-bottom:1px solid #f1f1f1;">
                                <fieldset class="mb-12">
                                    <input type="text" id="new_name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Нэр">
                                </fieldset>
                                <div class="d-flex gap-2">
                                    <button type="button" class="tf-btn style-2" id="btnCancelName">Болих</button>
                                    <button type="button" class="tf-btn animate-btn" id="btnSaveName">Хадгалах</button>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="d-flex justify-content-between align-items-center py-3" style="border-bottom:1px solid #f1f1f1;" id="phone-display">
                                <div>
                                    <p class="text-small text-main mb-1">Утас</p>
                                    <p class="h6 fw-semibold mb-0"><?= htmlspecialchars($user['phone'] ?? '—') ?></p>
                                </div>
                                <button type="button" id="btnEditPhone" class="link h6 text-primary" style="border:none;background:none;">Засах</button>
                            </div>
                            <div id="phone-input" style="display:none;padding:12px 0;border-bottom:1px solid #f1f1f1;">
                                <fieldset class="mb-12">
                                    <input type="tel" id="new_phone" placeholder="Шинэ утасны дугаар" maxlength="8">
                                </fieldset>
                                <div class="d-flex gap-2">
                                    <button type="button" class="tf-btn style-2" id="btnCancelPhone1">Болих</button>
                                    <button type="button" class="tf-btn animate-btn" id="btnPhoneNext">
                                        <span class="btn-text">Үргэлжлүүлэх</span>
                                        <span class="btn-loading" style="display:none;">Түр хүлээнэ үү...</span>
                                    </button>
                                </div>
                            </div>
                            <div id="phone-otp" style="display:none;padding:12px 0;border-bottom:1px solid #f1f1f1;">
                                <p class="text-small text-main mb-2" id="phone-otp-hint"></p>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box phone-otp-box" data-idx="0" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box phone-otp-box" data-idx="1" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box phone-otp-box" data-idx="2" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box phone-otp-box" data-idx="3" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="tf-btn style-2" id="btnCancelPhone2">Болих</button>
                                    <button type="button" class="tf-btn animate-btn" id="btnPhoneSave">
                                        <span class="btn-text">Хадгалах</span>
                                        <span class="btn-loading" style="display:none;">Хадгалж байна...</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="d-flex justify-content-between align-items-center py-3" id="email-display">
                                <div>
                                    <p class="text-small text-main mb-1">И-мэйл</p>
                                    <p class="h6 fw-semibold mb-0"><?= htmlspecialchars($user['email'] ?? '—') ?></p>
                                </div>
                                <button type="button" id="btnEditEmail" class="link h6 text-primary" style="border:none;background:none;"><?= !empty($user['email']) ? 'Засах' : 'Нэмэх' ?></button>
                            </div>
                            <div id="email-input" style="display:none;padding:12px 0;">
                                <fieldset class="mb-12">
                                    <input type="email" id="new_email" placeholder="example@email.com" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                </fieldset>
                                <div class="d-flex gap-2">
                                    <button type="button" class="tf-btn style-2" id="btnCancelEmail1">Болих</button>
                                    <button type="button" class="tf-btn animate-btn" id="btnEmailNext">
                                        <span class="btn-text">Код илгээх</span>
                                        <span class="btn-loading" style="display:none;">Илгээж байна...</span>
                                    </button>
                                </div>
                            </div>
                            <div id="email-otp" style="display:none;padding:12px 0;">
                                <p class="text-small text-main mb-2" id="email-otp-hint"></p>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box email-otp-box" data-idx="0" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box email-otp-box" data-idx="1" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box email-otp-box" data-idx="2" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box email-otp-box" data-idx="3" style="width:48px;height:48px;text-align:center;font-size:1.25rem;">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="tf-btn style-2" id="btnCancelEmail2">Болих</button>
                                    <button type="button" class="tf-btn animate-btn" id="btnEmailSave">
                                        <span class="btn-text">Хадгалах</span>
                                        <span class="btn-loading" style="display:none;">Хадгалж байна...</span>
                                    </button>
                                </div>
                            </div>

                            </form>

                            <!-- Connected Accounts -->
                            <h2 class="account-title type-semibold mt-4">Холбогдсон бүртгэлүүд</h2>
                            <div class="d-flex justify-content-between align-items-center py-3" style="background:#f9fafb;border-radius:8px;padding:12px 16px;">
                                <span class="h6 fw-semibold mb-0">Google</span>
                                <span id="google-connected-badge" class="text-small">—</span>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /Account -->

<?php
$hasEmail = !empty($user['email']);
$extra_scripts = '<script>
(function () {
    const BASE  = ' . json_encode(getBaseUrl()) . ';
    const TOKEN = ' . json_encode($_SESSION['token'] ?? '') . ';
    const HAS_EMAIL = ' . json_encode($hasEmail) . ';
    const CURRENT_PHONE = ' . json_encode($user['phone'] ?? '') . ';
    const CURRENT_EMAIL = ' . json_encode($user['email'] ?? '') . ';

    function showAlert(msg, ok) {
        const el = document.getElementById("settings-alert");
        el.style.display = "block";
        el.style.background = ok ? "#d1fae5" : "#fee2e2";
        el.style.color      = ok ? "#065f46" : "#991b1b";
        el.style.border     = ok ? "1px solid #6ee7b7" : "1px solid #fca5a5";
        el.textContent = msg;
        window.scrollTo({top: 0, behavior: "smooth"});
    }

    function setLoading(btn, loading) {
        btn.querySelector(".btn-text").style.display    = loading ? "none" : "";
        btn.querySelector(".btn-loading").style.display  = loading ? "" : "none";
        btn.disabled = loading;
    }

    // ── Load connected accounts status ──
    fetch(BASE + "backend/api/auth/me.php", {headers: {"Authorization": "Bearer " + TOKEN}})
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById("google-connected-badge");
            if (data.user && data.user.google_connected) {
                badge.textContent = "Холбогдсон";
                badge.style.color = "#16a34a";
            } else {
                badge.textContent = "Холбогдоогүй";
                badge.style.color = "#9ca3af";
            }
        })
        .catch(() => {});

    // ── Name edit ──
    document.getElementById("btnEditName").addEventListener("click", function () {
        document.getElementById("name-display").parentElement.style.display = "none";
        document.getElementById("name-edit").style.display = "";
    });
    document.getElementById("btnCancelName").addEventListener("click", function () {
        document.getElementById("name-edit").style.display = "none";
        document.getElementById("name-display").parentElement.style.display = "";
    });
    document.getElementById("btnSaveName").addEventListener("click", function () {
        const name = document.getElementById("new_name").value.trim();
        if (!name) return;
        fetch(BASE + "backend/api/auth/me.php", {
            method: "PUT",
            headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
            body: JSON.stringify({name: name, phone: CURRENT_PHONE, email: CURRENT_EMAIL})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelector("#name-display p.h6").textContent = name;
                document.getElementById("name-edit").style.display = "none";
                document.getElementById("name-display").parentElement.style.display = "";
                showAlert("Нэр амжилттай шинэчлэгдлээ.", true);
            } else {
                showAlert(data.error || "Алдаа гарлаа.", false);
            }
        })
        .catch(() => showAlert("Сүлжээний алдаа. Дахин оролдоно уу.", false));
    });

    // ── OTP box auto-advance helper ──
    function wireOtpBoxes(selector) {
        const boxes = Array.from(document.querySelectorAll(selector));
        boxes.forEach((box, i) => {
            box.addEventListener("input", function () {
                this.value = this.value.replace(/\\D/g, "").slice(-1);
                if (this.value && i < boxes.length - 1) boxes[i + 1].focus();
            });
            box.addEventListener("keydown", function (e) {
                if (e.key === "Backspace" && !this.value && i > 0) boxes[i - 1].focus();
            });
        });
        return () => boxes.map(b => b.value).join("");
    }
    const getPhoneOtp = wireOtpBoxes(".phone-otp-box");
    const getEmailOtp = wireOtpBoxes(".email-otp-box");

    // ── Phone edit ──
    document.getElementById("btnEditPhone").addEventListener("click", function () {
        document.getElementById("phone-display").style.display = "none";
        document.getElementById("phone-input").style.display = "";
        document.getElementById("new_phone").value = "";
        document.getElementById("new_phone").focus();
    });
    function resetPhoneEdit() {
        document.getElementById("phone-input").style.display = "none";
        document.getElementById("phone-otp").style.display = "none";
        document.getElementById("phone-display").style.display = "";
    }
    document.getElementById("btnCancelPhone1").addEventListener("click", resetPhoneEdit);
    document.getElementById("btnCancelPhone2").addEventListener("click", resetPhoneEdit);

    function savePhone(otpCode) {
        const btn = document.getElementById(otpCode === undefined ? "btnPhoneNext" : "btnPhoneSave");
        const phone = document.getElementById("new_phone").value.replace(/\\D/g, "");
        setLoading(btn, true);
        fetch(BASE + "backend/api/auth/update-phone.php", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
            body: JSON.stringify({new_phone: phone, otp_code: otpCode || ""})
        })
        .then(r => r.json())
        .then(data => {
            setLoading(btn, false);
            if (data.success) {
                document.querySelector("#phone-display p.h6").textContent = data.phone;
                resetPhoneEdit();
                showAlert("Утасны дугаар амжилттай шинэчлэгдлээ.", true);
            } else {
                showAlert(data.error || "Алдаа гарлаа.", false);
            }
        })
        .catch(() => { setLoading(btn, false); showAlert("Сүлжээний алдаа. Дахин оролдоно уу.", false); });
    }

    document.getElementById("btnPhoneNext").addEventListener("click", function () {
        const phone = document.getElementById("new_phone").value.replace(/\\D/g, "");
        if (phone.length !== 8) { showAlert("8 оронтой утасны дугаар оруулна уу.", false); return; }

        if (HAS_EMAIL) {
            // Email-registered accounts: identity already proven, save directly
            savePhone("");
            return;
        }

        // Phone-registered: send OTP to the new number first
        const btn = document.getElementById("btnPhoneNext");
        setLoading(btn, true);
        fetch(BASE + "backend/api/auth/send-otp.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({phone: phone})
        })
        .then(r => r.json())
        .then(data => {
            setLoading(btn, false);
            if (data.success) {
                document.getElementById("phone-input").style.display = "none";
                document.getElementById("phone-otp").style.display = "";
                document.getElementById("phone-otp-hint").textContent = phone + " дугаарт илгээсэн 4 оронтой код";
                document.querySelector(".phone-otp-box[data-idx=\"0\"]").focus();
            } else {
                showAlert(data.error || "Код илгээхэд алдаа гарлаа.", false);
            }
        })
        .catch(() => { setLoading(btn, false); showAlert("Сүлжээний алдаа. Дахин оролдоно уу.", false); });
    });

    document.getElementById("btnPhoneSave").addEventListener("click", function () {
        const code = getPhoneOtp();
        if (code.length !== 4) { showAlert("4 оронтой код оруулна уу.", false); return; }
        savePhone(code);
    });

    // ── Email edit ──
    document.getElementById("btnEditEmail").addEventListener("click", function () {
        document.getElementById("email-display").style.display = "none";
        document.getElementById("email-input").style.display = "";
        document.getElementById("new_email").focus();
    });
    function resetEmailEdit() {
        document.getElementById("email-input").style.display = "none";
        document.getElementById("email-otp").style.display = "none";
        document.getElementById("email-display").style.display = "";
    }
    document.getElementById("btnCancelEmail1").addEventListener("click", resetEmailEdit);
    document.getElementById("btnCancelEmail2").addEventListener("click", resetEmailEdit);

    document.getElementById("btnEmailNext").addEventListener("click", function () {
        const email = document.getElementById("new_email").value.trim();
        if (!/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(email)) { showAlert("Зөв и-мэйл хаяг оруулна уу.", false); return; }

        const btn = document.getElementById("btnEmailNext");
        setLoading(btn, true);
        fetch(BASE + "backend/api/auth/send-email-otp.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({email: email, purpose: "update"})
        })
        .then(r => r.json())
        .then(data => {
            setLoading(btn, false);
            if (data.success) {
                document.getElementById("email-input").style.display = "none";
                document.getElementById("email-otp").style.display = "";
                document.getElementById("email-otp-hint").textContent = email + " хаягт илгээсэн 4 оронтой код";
                document.querySelector(".email-otp-box[data-idx=\"0\"]").focus();
            } else {
                showAlert(data.error || "Код илгээхэд алдаа гарлаа.", false);
            }
        })
        .catch(() => { setLoading(btn, false); showAlert("Сүлжээний алдаа. Дахин оролдоно уу.", false); });
    });

    document.getElementById("btnEmailSave").addEventListener("click", function () {
        const code = getEmailOtp();
        if (code.length !== 4) { showAlert("4 оронтой код оруулна уу.", false); return; }
        const email = document.getElementById("new_email").value.trim();
        const btn = document.getElementById("btnEmailSave");
        setLoading(btn, true);
        fetch(BASE + "backend/api/auth/update-email.php", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
            body: JSON.stringify({new_email: email, otp_code: code})
        })
        .then(r => r.json())
        .then(data => {
            setLoading(btn, false);
            if (data.success) {
                document.querySelector("#email-display p.h6").textContent = data.email;
                document.getElementById("btnEditEmail").textContent = "Засах";
                resetEmailEdit();
                showAlert("И-мэйл хаяг амжилттай шинэчлэгдлээ.", true);
            } else {
                showAlert(data.error || "Алдаа гарлаа.", false);
            }
        })
        .catch(() => { setLoading(btn, false); showAlert("Сүлжээний алдаа. Дахин оролдоно уу.", false); });
    });
}());
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
