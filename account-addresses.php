<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . url('login.php?redirect=' . urlencode(url('account-addresses.php'))));
    exit;
}

$user = getSessionUser();
$activeAccountPage = 'addresses';
$page_title = 'Миний хаяг';
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Миний хаяг</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><a href="<?= url('account.php') ?>" class="h6 link">Бүртгэл</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Хаяг</h6></li>
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
                            <h2 class="account-title type-semibold">Миний хаяг</h2>
                            <div id="address-alert" class="alert" style="display:none;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:.9rem;"></div>

                            <div class="account-my_address" id="addresses-wrap">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-secondary" role="status"></div>
                                    <p class="h6 text-main mt-2">Хаягуудыг ачаалж байна...</p>
                                </div>
                            </div>

                            <div style="margin-top:32px;">
                                <h5 class="mb-3" id="addressFormTitle">Шинэ хаяг нэмэх</h5>
                                <form id="addressForm" class="form-login">
                                    <div class="list-ver">
                                        <fieldset>
                                            <label class="h6 fw-medium mb-8 d-block">Нэр (жишээ: Гэр, Ажил)</label>
                                            <input type="text" id="addr_label" name="label" placeholder="Хаягийн нэр">
                                        </fieldset>
                                        <div class="cols tf-grid-layout sm-col-2">
                                            <fieldset>
                                                <label class="h6 fw-medium mb-8 d-block">Дүүрэг</label>
                                                <div class="tf-select">
                                                    <select id="addr_district_id" name="district_id" class="w-100" required>
                                                        <option value="" disabled selected>Дүүрэг сонгох...</option>
                                                    </select>
                                                </div>
                                            </fieldset>
                                            <fieldset>
                                                <label class="h6 fw-medium mb-8 d-block">Хороо</label>
                                                <div class="tf-select">
                                                    <select id="addr_khoroo_id" name="khoroo_id" class="w-100" disabled required>
                                                        <option value="" disabled selected>Хороо сонгох...</option>
                                                    </select>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <fieldset>
                                            <label class="h6 fw-medium mb-8 d-block">Хаяг</label>
                                            <input type="text" id="addr_address" name="address"
                                                   placeholder="Гудамж, байр, орц, давхар *" required>
                                        </fieldset>
                                        <fieldset>
                                            <label class="h6 fw-medium mb-8 d-block">Нэмэлт мэдээлэл</label>
                                            <input type="text" id="addr_detail_address" name="detail_address"
                                                   placeholder="Тоот, орцны код гэх мэт">
                                        </fieldset>
                                        <fieldset class="d-flex align-items-center gap-2">
                                            <input type="checkbox" id="addr_is_default" name="is_default" style="width:auto;">
                                            <label class="h6 fw-medium mb-0" for="addr_is_default">Үндсэн хаяг болгох</label>
                                        </fieldset>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="tf-btn animate-btn" id="btnSaveAddress">
                                            <span class="btn-text">Хаяг нэмэх</span>
                                            <span class="btn-loading" style="display:none;">Хадгалж байна...</span>
                                        </button>
                                        <button type="button" class="tf-btn style-2" id="btnCancelEditAddress" style="display:none;">Цуцлах</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /Account -->

<?php
$extra_scripts = '<script>
(function () {
    const BASE  = ' . json_encode(getBaseUrl()) . ';
    const TOKEN = ' . json_encode($_SESSION['token'] ?? '') . ';

    // Districts / Khoroos for address form
    let districtsData = [];
    function loadDistricts() {
        fetch(BASE + "backend/api/districts.php")
            .then(r => r.json())
            .then(data => {
                districtsData = data.districts || [];
                const sel = document.getElementById("addr_district_id");
                districtsData.forEach(d => {
                    const opt = document.createElement("option");
                    opt.value = d.id;
                    opt.textContent = d.name_mn || d.name;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }
    loadDistricts();

    document.getElementById("addr_district_id").addEventListener("change", function () {
        const did = parseInt(this.value);
        const kSel = document.getElementById("addr_khoroo_id");
        kSel.innerHTML = \'<option value="" disabled selected>Хороо сонгох...</option>\';
        kSel.disabled = true;

        const district = districtsData.find(d => d.id === did);
        if (!district || !district.khoroos || district.khoroos.length === 0) return;

        district.khoroos.forEach(k => {
            const opt = document.createElement("option");
            opt.value = k.id;
            opt.textContent = (k.number ? k.number + "-р хороо" : "") + (k.name ? " " + k.name : "");
            kSel.appendChild(opt);
        });
        kSel.disabled = false;
    });

    let addressesData = [];
    let editingAddressId = null;

    function loadAddresses() {
        fetch(BASE + "backend/api/addresses.php", {
            headers: {"Authorization": "Bearer " + TOKEN}
        })
        .then(r => r.json())
        .then(data => {
            const wrap = document.getElementById("addresses-wrap");
            const addresses = data.addresses || [];
            addressesData = addresses;
            if (!Array.isArray(addresses) || addresses.length === 0) {
                wrap.innerHTML = \'<div class="box-text_empty type-shop_cart text-center py-4"><span class="icon"><i class="icon-map-pin" style="font-size:2.5rem;color:#ccc;"></i></span><h6 class="text-main mt-3">Хадгалсан хаяг байхгүй байна</h6></div>\';
                return;
            }
            let html = "";
            addresses.forEach(a => {
                const khoroo = a.khoroo_number ? (a.khoroo_number + "-р хороо" + (a.khoroo_name ? " " + a.khoroo_name : "")) : "";
                html += `<div class="account-address-item file-delete">
                    <div class="address-item_content">
                        <h4 class="address-title">${a.label || "Хаяг"}${a.is_default == 1 ? \' <span class="text-primary" style="font-size:.75rem;">(Үндсэн)</span>\' : ""}</h4>
                        <div class="address-info">
                            <h5 class="fw-semibold">${a.district_name || ""}${khoroo ? ", " + khoroo : ""}</h5>
                            <p class="h6">${a.address || ""}${a.detail_address ? ", " + a.detail_address : ""}</p>
                        </div>
                    </div>
                    <div class="address-item_action">
                        <a href="javascript:void(0)" class="tf-btn animate-btn btn-edit-address" data-id="${a.id}">Засах</a>
                        <a href="javascript:void(0)" class="tf-btn style-line remove btn-delete-address" data-id="${a.id}">Устгах</a>
                    </div>
                </div>`;
            });
            wrap.innerHTML = html;

            wrap.querySelectorAll(".btn-delete-address").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    if (!confirm("Энэ хаягийг устгах уу?")) return;
                    fetch(BASE + "backend/api/addresses.php?id=" + this.dataset.id, {
                        method: "DELETE",
                        headers: {"Authorization": "Bearer " + TOKEN}
                    })
                    .then(r => r.json())
                    .then(() => {
                        if (editingAddressId === this.dataset.id) cancelEditAddress();
                        loadAddresses();
                    })
                    .catch(() => alert("Устгахад алдаа гарлаа."));
                });
            });

            wrap.querySelectorAll(".btn-edit-address").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    startEditAddress(this.dataset.id);
                });
            });
        })
        .catch(() => {
            document.getElementById("addresses-wrap").innerHTML = \'<p class="text-center py-4 text-main">Хаягуудыг ачаалж чадсангүй.</p>\';
        });
    }
    loadAddresses();

    function startEditAddress(id) {
        const addr = addressesData.find(a => String(a.id) === String(id));
        if (!addr) return;

        editingAddressId = String(id);
        document.getElementById("addressFormTitle").textContent = "Хаяг засах";
        document.getElementById("btnSaveAddress").querySelector(".btn-text").textContent = "Хадгалах";
        document.getElementById("btnCancelEditAddress").style.display = "";

        document.getElementById("addr_label").value = addr.label || "";
        document.getElementById("addr_address").value = addr.address || "";
        document.getElementById("addr_detail_address").value = addr.detail_address || "";
        document.getElementById("addr_is_default").checked = addr.is_default == 1;

        const dSel = document.getElementById("addr_district_id");
        dSel.value = addr.district_id;
        dSel.dispatchEvent(new Event("change"));
        document.getElementById("addr_khoroo_id").value = addr.khoroo_id;

        document.getElementById("addressForm").scrollIntoView({behavior: "smooth", block: "start"});
    }

    function cancelEditAddress() {
        editingAddressId = null;
        document.getElementById("addressFormTitle").textContent = "Шинэ хаяг нэмэх";
        document.getElementById("btnSaveAddress").querySelector(".btn-text").textContent = "Хаяг нэмэх";
        document.getElementById("btnCancelEditAddress").style.display = "none";
        document.getElementById("addressForm").reset();
        document.getElementById("addr_khoroo_id").innerHTML = \'<option value="" disabled selected>Хороо сонгох...</option>\';
        document.getElementById("addr_khoroo_id").disabled = true;
    }

    document.getElementById("btnCancelEditAddress").addEventListener("click", cancelEditAddress);

    document.getElementById("addressForm").addEventListener("submit", function (e) {
        e.preventDefault();
        const btn   = document.getElementById("btnSaveAddress");
        const alert = document.getElementById("address-alert");

        btn.querySelector(".btn-text").style.display    = "none";
        btn.querySelector(".btn-loading").style.display = "";
        btn.disabled = true;
        alert.style.display = "none";

        const payload = {
            label: document.getElementById("addr_label").value.trim(),
            district_id: parseInt(document.getElementById("addr_district_id").value) || null,
            khoroo_id: parseInt(document.getElementById("addr_khoroo_id").value) || null,
            address: document.getElementById("addr_address").value.trim(),
            detail_address: document.getElementById("addr_detail_address").value.trim(),
            is_default: document.getElementById("addr_is_default").checked
        };

        const isEditing = editingAddressId !== null;
        const url = isEditing
            ? BASE + "backend/api/addresses.php?id=" + editingAddressId
            : BASE + "backend/api/addresses.php";

        fetch(url, {
            method: isEditing ? "PUT" : "POST",
            headers: {"Content-Type": "application/json", "Authorization": "Bearer " + TOKEN},
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            alert.style.display = "block";
            if (data.success) {
                alert.style.background = "#d1fae5";
                alert.style.color      = "#065f46";
                alert.style.border     = "1px solid #6ee7b7";
                alert.textContent      = isEditing ? "Хаяг амжилттай шинэчлэгдлээ." : "Хаяг амжилттай нэмэгдлээ.";
                if (isEditing) {
                    cancelEditAddress();
                } else {
                    document.getElementById("addressForm").reset();
                    document.getElementById("addr_khoroo_id").innerHTML = \'<option value="" disabled selected>Хороо сонгох...</option>\';
                    document.getElementById("addr_khoroo_id").disabled = true;
                }
                loadAddresses();
            } else {
                alert.style.background = "#fee2e2";
                alert.style.color      = "#991b1b";
                alert.style.border     = "1px solid #fca5a5";
                alert.textContent      = (data.errors && data.errors.join(", ")) || data.error || "Алдаа гарлаа.";
            }
        })
        .catch(() => {
            alert.style.display  = "block";
            alert.style.background = "#fee2e2";
            alert.style.color      = "#991b1b";
            alert.style.border     = "1px solid #fca5a5";
            alert.textContent      = "Сүлжээний алдаа. Дахин оролдоно уу.";
        })
        .finally(() => {
            btn.querySelector(".btn-text").style.display    = "";
            btn.querySelector(".btn-loading").style.display = "none";
            btn.disabled = false;
        });
    });
}());
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
