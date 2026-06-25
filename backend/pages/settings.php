<?php
$pageTitle = 'Тохиргоо';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Буруу хүсэлт.');
        header('Location: index.php?page=settings');
        exit;
    }

    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'save_settings') {
        // Only allow known setting keys from the database
        $allSettingsRows = getAllSettings();
        $allowedKeys = array_column($allSettingsRows, 'setting_key');
        $settings = $_POST['settings'] ?? [];
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");

        // Handle boolean toggle settings (unchecked checkboxes are not sent in POST)
        $booleanKeys = array_column(array_filter($allSettingsRows, fn($s) => $s['type'] === 'boolean'), 'setting_key');
        foreach ($booleanKeys as $bk) {
            if (!isset($settings[$bk])) {
                $settings[$bk] = '0';
            }
        }

        // Validate: at least one payment method must be active
        $qpayOn  = ($settings['payment_qpay_enabled']  ?? '0') === '1';
        $transferOn = ($settings['payment_transfer_enabled'] ?? '0') === '1';
        $bonumOn = ($settings['payment_bonum_enabled'] ?? '0') === '1';
        $storepayOn = ($settings['payment_storepay_enabled'] ?? '0') === '1';
        if (!$qpayOn && !$transferOn && !$bonumOn && !$storepayOn) {
            setFlash('error', 'Хамгийн багадаа 1 төлбөрийн хэлбэр идэвхтэй байх ёстой.');
            header('Location: index.php?page=settings');
            exit;
        }

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                $stmt->execute([trim($value), $key]);
            }
        }

        $imageUploadKeys = [
            'site_logo',
            'site_favicon',
            'home_category_ready_image',
            'home_category_preorder_image',
            'home_category_discount_image',
            'home_category_new_image',
        ];
        foreach ($imageUploadKeys as $imageKey) {
            if (!empty($_FILES[$imageKey]) && $_FILES[$imageKey]['error'] === UPLOAD_ERR_OK) {
                $result = uploadMedia($_FILES[$imageKey]);
                if (!empty($result['success'])) {
                    $stmt->execute([$result['media']['filename'], $imageKey]);
                }
            }
        }

        // Clear cached settings
        setFlash('success', 'Тохиргоо хадгалагдлаа.');
        header('Location: index.php?page=settings');
        exit;
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $admin = $db->prepare("SELECT * FROM admins WHERE id = ?");
        $admin->execute([$currentAdmin['id']]);
        $adminData = $admin->fetch();

        if (!password_verify($currentPass, $adminData['password'])) {
            setFlash('error', 'Одоогийн нууц үг буруу байна.');
        } elseif (strlen($newPass) < 6) {
            setFlash('error', 'Шинэ нууц үг доод тал 6 тэмдэгт байх ёстой.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('error', 'Шинэ нууц үг төхөөрөө хоорондоо таарахгүй байна.');
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([$hash, $currentAdmin['id']]);
            setFlash('success', 'Нууц үг солигдлоо.');
        }
        header('Location: index.php?page=settings');
        exit;
    }
}

$seedSettings = [
    // Contact & social
    ['phone',          '', 'Утасны дугаар',           'text'],
    ['email',          '', 'И-мэйл хаяг',             'text'],
    ['address',        '', 'Хаяг',                    'text'],
    ['business_hours', '', 'Ажиллах цаг',             'text'],
    ['map_embed_url',  '', 'Google Maps Embed URL',   'text'],
    ['facebook_url',   '', 'Facebook хаяг (URL)',     'text'],
    ['instagram_url',  '', 'Instagram хаяг (URL)',    'text'],
    ['tiktok_url',     '', 'TikTok хаяг (URL)',       'text'],
    // Delivery
    ['delivery_fee_enabled', '1', 'Хүргэлтийн төлбөрийг тооцох', 'boolean'],
    ['home_categories_enabled', '1', 'Ангиллын хэсгийг харуулах', 'boolean'],
    ['home_category_ready_enabled', '1', 'Бэлэн бараа картыг харуулах', 'boolean'],
    ['home_category_preorder_enabled', '1', 'Урьдчилсан захиалга картыг харуулах', 'boolean'],
    ['home_category_discount_enabled', '1', 'Хямдрал картыг харуулах', 'boolean'],
    ['home_category_new_enabled', '1', 'Шинэ бараа картыг харуулах', 'boolean'],
    ['home_category_ready_image', '', 'Бэлэн бараа картны зураг', 'text'],
    ['home_category_preorder_image', '', 'Урьдчилсан захиалга картны зураг', 'text'],
    ['home_category_discount_image', '', 'Хямдрал картны зураг', 'text'],
    ['home_category_new_image', '', 'Шинэ бараа картны зураг', 'text'],
    ['login_email_enabled', '0', 'Имэйлээр нэвтрэх', 'boolean'],
    ['register_email_enabled', '0', 'Имэйлээр бүртгүүлэх', 'boolean'],
    ['order_number_prefix', 'NO', 'Захиалгын дугаарын угтвар', 'text'],
    ['reconciliation_time_window_minutes', '30', 'Тохирол: цагийн цонх (минут)', 'number'],
];
$insertSetting = $db->prepare("INSERT IGNORE INTO settings (`setting_key`, `setting_value`, `label`, `type`) VALUES (?, ?, ?, ?)");
foreach ($seedSettings as $seed) {
    $insertSetting->execute($seed);
}

$privateSeedSettings = [
    ['email_template_subject_register', 'Runners World имэйл баталгаажуулах код', 'Имэйл баталгаажуулах сэдэв', 'text', 0],
    ['email_template_body_register', 'Таны баталгаажуулах код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Имэйл баталгаажуулах бие', 'text', 0],
    ['email_template_subject_reset', 'Runners World нууц үг сэргээх код', 'Нууц үг сэргээх сэдэв', 'text', 0],
    ['email_template_body_reset', 'Таны нууц үг сэргээх код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Нууц үг сэргээх бие', 'text', 0],
    ['bonum_checksum_key', '', 'Bonum Checksum Key', 'text', 0],
];
$insertPrivateSetting = $db->prepare("INSERT IGNORE INTO settings (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES (?, ?, ?, ?, ?)");
foreach ($privateSeedSettings as $seed) {
    $insertPrivateSetting->execute($seed);
}

$settings = getAllSettings();

// Group settings
$feeSettings = [];
$siteSettings = [];
$contactSettings = [];
$homepageSettings = [];
$homepageToggleSettings = [];
$messageproSettings = [];
$qpaySettings = [];
$bonumSettings = [];
$storepaySettings = [];
$smtpSettings = [];
$bankSettings = [];
$paymentToggleSettings = [];
$loginToggleSettings = [];
$emailTemplateSettings = [];
$loginCredentialSettings = [];
foreach ($settings as $s) {
    if (in_array($s['setting_key'], ['delivery_fee_enabled', 'delivery_fee', 'free_delivery_threshold', 'cargo_rate_per_kg'])) {
        $feeSettings[] = $s;
    } elseif (in_array($s['setting_key'], ['site_name', 'site_name_mn', 'site_slogan', 'site_logo', 'site_favicon', 'site_primary_color', 'currency', 'order_number_prefix'])) {
        $siteSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'home_') && $s['type'] === 'boolean') {
        $homepageToggleSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'hero_') || str_starts_with($s['setting_key'], 'shops_') || str_starts_with($s['setting_key'], 'feature') || str_starts_with($s['setting_key'], 'top_bar_') || str_starts_with($s['setting_key'], 'home_')) {
        $homepageSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'messagepro_')) {
        $messageproSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'qpay_')) {
        $qpaySettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'bonum_')) {
        $bonumSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'storepay_')) {
        $storepaySettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'smtp_')) {
        $smtpSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'bank_')) {
        $bankSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'payment_')) {
        $paymentToggleSettings[] = $s;
    } elseif (str_contains($s['setting_key'], 'email_template_')) {
        $emailTemplateSettings[] = $s;
    } elseif (str_starts_with($s['setting_key'], 'login_') || $s['setting_key'] === 'register_email_enabled') {
        $loginToggleSettings[] = $s;
    } elseif (in_array($s['setting_key'], ['google_client_id', 'facebook_app_id'])) {
        $loginCredentialSettings[] = $s;
    } else {
        $contactSettings[] = $s;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <!-- Fee Settings -->
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Хүргэлт & Үнэ</h3>
            <p class="text-sm text-gray-500 mb-4">Хүргэлт, ачааны төлбөр тохируулах</p>
            <div class="space-y-4">
                <?php foreach ($feeSettings as $s): ?>
                    <?php if ($s['type'] === 'boolean'): ?>
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                        <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="0">
                        <input type="checkbox" name="settings[<?= e($s['setting_key']) ?>]" value="1" <?= $s['setting_value'] === '1' ? 'checked' : '' ?>
                               class="w-5 h-5 mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-medium text-gray-900"><?= e($s['label']) ?></span>
                            <?php if ($s['setting_key'] === 'delivery_fee_enabled'): ?>
                                <p class="text-xs text-gray-500">Идэвхтэй үед: захиалгад хүргэлтийн төлбөр нэмэгдэнэ. Идэвхгүй үед: "Хүргэлтийн үед бодогдоно" гэсэн мэдээлэл харагдана.</p>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php else: ?>
                    <div class="grid md:grid-cols-2 gap-4 items-center">
                        <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                        <input type="<?= $s['type'] === 'number' ? 'number' : 'text' ?>"
                               name="settings[<?= e($s['setting_key']) ?>]"
                               value="<?= e($s['setting_value']) ?>"
                               class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                               <?= $s['type'] === 'number' ? 'min="0" step="100"' : '' ?>>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Сайтын мэдээлэл</h3>
            <p class="text-sm text-gray-500 mb-4">Дэлгүүрийн ерөнхий тохиргоо</p>
            <div class="space-y-4">
                <?php foreach ($siteSettings as $s): ?>
                    <?php if (in_array($s['setting_key'], ['site_logo', 'site_favicon'])): ?>
                    <div class="grid md:grid-cols-2 gap-4 items-start">
                        <label class="text-sm font-medium text-gray-700 pt-2"><?= e($s['label']) ?></label>
                        <div>
                            <?php if ($s['setting_value']): ?>
                            <div class="mb-2 flex items-center gap-3">
                                <img src="<?= e(getBasePath() . 'backend/uploads/media/' . $s['setting_value']) ?>" alt="<?= e($s['label']) ?>"
                                     class="<?= $s['setting_key'] === 'site_favicon' ? 'w-8 h-8' : 'h-10' ?> object-contain rounded border border-gray-200 bg-gray-50 p-1">
                                <span class="text-xs text-gray-500"><?= e($s['setting_value']) ?></span>
                            </div>
                            <?php endif; ?>
                            <input type="file" name="<?= e($s['setting_key']) ?>" accept="image/*"
                                   class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>">
                        </div>
                    </div>
                    <?php elseif ($s['setting_key'] === 'site_primary_color'): ?>
                    <div class="grid md:grid-cols-2 gap-4 items-center">
                        <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value'] ?: '#2563eb') ?>"
                                   class="w-12 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5"
                                   id="primaryColorPicker">
                            <input type="text" value="<?= e($s['setting_value'] ?: '#2563eb') ?>" readonly
                                   id="primaryColorHex"
                                   class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm w-28 bg-gray-50 font-mono">
                            <div id="primaryColorPreview" class="h-10 flex-1 rounded-lg border border-gray-200" style="background-color: <?= e($s['setting_value'] ?: '#2563eb') ?>"></div>
                        </div>
                        <script>
                            document.getElementById('primaryColorPicker').addEventListener('input', function(e) {
                                document.getElementById('primaryColorHex').value = e.target.value;
                                document.getElementById('primaryColorPreview').style.backgroundColor = e.target.value;
                            });
                        </script>
                    </div>
                    <?php else: ?>
                    <div class="grid md:grid-cols-2 gap-4 items-center">
                        <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                        <input type="text" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                               class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Холбоо барих</h3>
            <p class="text-sm text-gray-500 mb-4">Дэлгүүрийн холбоо барих мэдээлэл</p>
            <div class="space-y-4">
                <?php foreach ($contactSettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="text" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($homepageToggleSettings)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Нүүр хуудасны ангиллын хэсэг</h3>
            <p class="text-sm text-gray-500 mb-4">Категори grid хэсгийг идэвхжүүлэх эсвэл унтраах.</p>
            <div class="space-y-3">
                <?php foreach ($homepageToggleSettings as $s): ?>
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                    <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="0">
                    <input type="checkbox" name="settings[<?= e($s['setting_key']) ?>]" value="1" <?= $s['setting_value'] === '1' ? 'checked' : '' ?>
                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900"><?= e($s['label']) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Нүүр хуудсын текст</h3>
            <p class="text-sm text-gray-500 mb-4">Нүүр хэсэг, дэлгүүр, онцлог, дээд мөр тохируулах</p>
            <div class="space-y-4">
                <?php foreach ($homepageSettings as $s): ?>
                <?php if (str_ends_with($s['setting_key'], '_image')): ?>
                <div class="grid md:grid-cols-2 gap-4 items-start">
                    <label class="text-sm font-medium text-gray-700 pt-2"><?= e($s['label']) ?></label>
                    <div>
                        <?php if ($s['setting_value']): ?>
                        <div class="mb-2 flex items-center gap-3">
                            <img src="<?= e(getBasePath() . 'backend/uploads/media/' . $s['setting_value']) ?>" alt="<?= e($s['label']) ?>"
                                 class="h-20 object-contain rounded border border-gray-200 bg-gray-50 p-1">
                            <span class="text-xs text-gray-500"><?= e($s['setting_value']) ?></span>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="<?= e($s['setting_key']) ?>" accept="image/*"
                               class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>">
                    </div>
                </div>
                <?php else: ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <?php if ($s['type'] === 'boolean'): ?>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                        <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="0">
                        <input type="checkbox" name="settings[<?= e($s['setting_key']) ?>]" value="1" <?= $s['setting_value'] === '1' ? 'checked' : '' ?>
                               class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700"><?= e($s['setting_value']) ?></span>
                    </label>
                    <?php elseif (str_contains($s['setting_key'], 'description') || str_contains($s['setting_key'], 'desc')): ?>
                    <textarea name="settings[<?= e($s['setting_key']) ?>]" rows="2"
                              class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-vertical"><?= e($s['setting_value']) ?></textarea>
                    <?php else: ?>
                    <input type="text" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Төлбөрийн хэлбэр</h3>
            <p class="text-sm text-gray-500 mb-4">Хэрэглэгч ямар төлбөрийн хэлбэрээр төлөх боломжтойг тохируулна. Хамгийн багадаа 1 идэвхтэй байх ёстой.</p>
            <div class="space-y-3">
                <?php foreach ($paymentToggleSettings as $s): ?>
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                    <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="0">
                    <input type="checkbox" name="settings[<?= e($s['setting_key']) ?>]" value="1" <?= $s['setting_value'] === '1' ? 'checked' : '' ?>
                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900"><?= e($s['label']) ?></span>
                        <?php if ($s['setting_key'] === 'payment_qpay_enabled'): ?>
                            <p class="text-xs text-gray-500">QPay QR код ба банкны апп-аар төлөх</p>
                        <?php elseif ($s['setting_key'] === 'payment_transfer_enabled'): ?>
                            <p class="text-xs text-gray-500">Банкны дансруу шилжүүлэг хийх</p>
                        <?php elseif ($s['setting_key'] === 'payment_bonum_enabled'): ?>
                            <p class="text-xs text-gray-500">Bonum гарцаар QR болон линкээр төлөх</p>
                        <?php elseif ($s['setting_key'] === 'payment_storepay_enabled'): ?>
                            <p class="text-xs text-gray-500">StorePay-ээр 4 хуваан төлөх (BNPL). Хэрэглэгч StorePay-д бүртгэлтэй утасны дугаараар нэхэмжлэх авна.</p>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Нэвтрэх тохиргоо</h3>
            <p class="text-sm text-gray-500 mb-4">Хэрэглэгчийн нэвтрэх аргуудыг тохируулна</p>
            <div class="space-y-3">
                <?php foreach ($loginToggleSettings as $s): ?>
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                    <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="0">
                    <input type="checkbox" name="settings[<?= e($s['setting_key']) ?>]" value="1" <?= $s['setting_value'] === '1' ? 'checked' : '' ?>
                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900"><?= e($s['label']) ?></span>
                        <?php if ($s['setting_key'] === 'login_phone_password_enabled'): ?>
                            <p class="text-xs text-gray-500">Утасны дугаар + нууц үгээр нэвтрэх</p>
                        <?php elseif ($s['setting_key'] === 'login_phone_otp_enabled'): ?>
                            <p class="text-xs text-gray-500">Утасны дугаар + SMS код баталгаажуулалтаар нэвтрэх</p>
                        <?php elseif ($s['setting_key'] === 'login_register_without_otp_enabled'): ?>
                            <p class="text-xs text-gray-500">SMS код авах боломжгүй хэрэглэгч утас + нууц үгээр шууд бүртгүүлэх. <span class="text-amber-600">Анхаар: утасны эзэмшил баталгаажихгүй тул дугаар давхардвал жинхэнэ эзэмшигч нь нэвтрэх боломжгүй болж магадгүй.</span></p>
                        <?php elseif ($s['setting_key'] === 'login_email_enabled'): ?>
                            <p class="text-xs text-gray-500">Имэйл хаяг ашиглан нэвтрэх болон бүртгүүлэх боломжыг идэвхжүүлнэ.</p>
                        <?php elseif ($s['setting_key'] === 'register_email_enabled'): ?>
                            <p class="text-xs text-gray-500">Имэйлээр шинэ хэрэглэгчийг бүртгүүлэх боломжийг идэвхжүүлнэ.</p>
                        <?php elseif ($s['setting_key'] === 'login_google_enabled'): ?>
                            <p class="text-xs text-gray-500">Google акаунтаар нэвтрэх (Client ID шаардлагатай)</p>
                        <?php elseif ($s['setting_key'] === 'login_facebook_enabled'): ?>
                            <p class="text-xs text-gray-500">Facebook акаунтаар нэвтрэх (App ID шаардлагатай)</p>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($emailTemplateSettings)): ?>
            <div class="mt-4 pt-4 border-t border-gray-100 space-y-4">
                <p class="text-sm font-medium text-gray-700">Email шаблон тохиргоо</p>
                <?php foreach ($emailTemplateSettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-start">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <?php if (str_ends_with($s['setting_key'], '_body')): ?>
                    <div class="space-y-2">
                        <textarea name="settings[<?= e($s['setting_key']) ?>]" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"><?= e($s['setting_value']) ?></textarea>
                        <p class="text-xs text-gray-500">{otp}, {email}, {purpose} placeholder-уудыг ашиглана. HTML дэмждэг.</p>
                    </div>
                    <?php else: ?>
                    <input type="text" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($loginCredentialSettings)): ?>
            <div class="mt-4 pt-4 border-t border-gray-100 space-y-4">
                <p class="text-sm font-medium text-gray-700">Social Login тохиргоо</p>
                <?php foreach ($loginCredentialSettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="text" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           placeholder="<?= $s['setting_key'] === 'google_client_id' ? 'xxxxx.apps.googleusercontent.com' : '123456789012345' ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Банкны данс (Шилжүүлэг)</h3>
            <p class="text-sm text-gray-500 mb-4">Хэрэглэгч шилжүүлгээр төлбөр төлөх үед харуулах дансны мэдээлэл</p>
            <div class="space-y-4">
                <?php foreach ($bankSettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="text" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">MessagePro</h3>
            <p class="text-sm text-gray-500 mb-4">MessagePro SMS үйлчилгээний нэвтрэх мэдээлэл</p>
            <div class="space-y-4">
                <?php foreach ($messageproSettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="<?= str_contains($s['setting_key'], 'password') ? 'password' : 'text' ?>"
                           name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                           autocomplete="off">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">QPay</h3>
            <p class="text-sm text-gray-500 mb-4">QPay төлбөрийн гарцын нэвтрэх мэдээлэл</p>
            <div class="space-y-4">
                <?php foreach ($qpaySettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="<?= str_contains($s['setting_key'], 'password') ? 'password' : 'text' ?>"
                           name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                           autocomplete="off">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($bonumSettings)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Bonum</h3>
            <p class="text-sm text-gray-500 mb-4">Bonum төлбөрийн гарцын нэвтрэх мэдээлэл</p>
            <div class="space-y-4">
                <?php foreach ($bonumSettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="<?= str_contains($s['setting_key'], 'secret') ? 'password' : 'text' ?>"
                           name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                           autocomplete="off">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($storepaySettings)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">StorePay</h3>
            <p class="text-sm text-gray-500 mb-4">StorePay BNPL (4 хуваан төлөх) гарцын нэвтрэх мэдээлэл. Store ID, system хэрэглэгч, app credentials шаардлагатай.</p>
            <div class="space-y-4">
                <?php foreach ($storepaySettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="<?= str_contains($s['setting_key'], 'password') ? 'password' : 'text' ?>"
                           name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                           autocomplete="off">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">SMTP И-мэйл</h3>
            <p class="text-sm text-gray-500 mb-4">И-мэйл илгээх тохиргоо</p>
            <div class="space-y-4">
                <?php foreach ($smtpSettings as $s): ?>
                <div class="grid md:grid-cols-2 gap-4 items-center">
                    <label class="text-sm font-medium text-gray-700"><?= e($s['label']) ?></label>
                    <input type="<?= str_contains($s['setting_key'], 'password') ? 'password' : ($s['type'] === 'number' ? 'number' : 'text') ?>"
                           name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>"
                           class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                           autocomplete="off">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex justify-end mt-4">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Тохиргоо хадгалах
            </button>
        </div>
    </form>

    <!-- Change Password -->
    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">

        <h3 class="text-lg font-semibold text-gray-900 mb-1">Нууц үг солих</h3>
        <p class="text-sm text-gray-500 mb-4">Админ нууц үгээ шинэчлэх</p>

        <div class="space-y-4 max-w-md">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Одоогийн нууц үг</label>
                <input type="password" name="current_password" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Шинэ нууц үг</label>
                <input type="password" name="new_password" required minlength="6"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Шинэ нууц үг давтах</label>
                <input type="password" name="confirm_password" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">
                Нууц үг солих
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
