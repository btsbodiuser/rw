<?php
/**
 * Open Graph meta tag renderer for social media sharing.
 * Detects social media crawlers and serves OG meta tags with product/page data.
 * Regular users get the normal SPA (dist/index.html).
 */

// Auto-detect base path (e.g. '/rw/' on localhost, '/' on production)
$basePath = rtrim(str_replace('\\', '/', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']))), '/') . '/';

// Check if the request is from a social media crawler
function isSocialBot(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bots = [
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'Viber',
        'TelegramBot',
        'Slackbot',
        'Discordbot',
        'Pinterest',
        'Googlebot',
        'bingbot',
    ];
    foreach ($bots as $bot) {
        if (stripos($ua, $bot) !== false) return true;
    }
    return false;
}

if (!isSocialBot()) {
    // Serve the normal SPA with settings pre-injected to avoid flash
    require_once __DIR__ . '/backend/config/database.php';
    require_once __DIR__ . '/backend/includes/functions.php';

    $db = getDB();
    $rows = $db->query("SELECT setting_key, setting_value, type FROM settings WHERE is_public = 1")->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $value = $row['setting_value'];
        if ($row['type'] === 'number') {
            $value = is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : $value;
        } elseif ($row['type'] === 'boolean') {
            $value = ($value === '1' || $value === 'true');
        }
        $settings[$row['setting_key']] = $value;
    }

    $html = file_get_contents(__DIR__ . '/dist/index.html');

    // Inject settings as window.__SETTINGS__ so React can use them instantly
    $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $inject = "<script>window.__SETTINGS__={$settingsJson}</script>";

    // Generate primary color CSS variables to avoid color flash
    $hex = $settings['site_primary_color'] ?? '';
    if ($hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;
        $max = max($r, $g, $b); $min = min($r, $g, $b);
        $h = 0; $s = 0; $l = ($max + $min) / 2;
        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            if ($max === $r) $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
            elseif ($max === $g) $h = (($b - $r) / $d + 2) / 6;
            else $h = (($r - $g) / $d + 4) / 6;
        }
        $h = round($h * 360); $s = round($s * 100);
        $shades = ['50'=>97,'100'=>93,'200'=>86,'300'=>76,'400'=>64,'500'=>53,'600'=>46,'700'=>40,'800'=>34,'900'=>28,'950'=>20];
        $vars = '';
        foreach ($shades as $shade => $lightness) {
            $sat = ($shade === '50' || $shade === '100') ? min($s + 10, 100) : $s;
            $satF = $sat / 100; $lF = $lightness / 100;
            $a = $satF * min($lF, 1 - $lF);
            $f = function($n) use ($h, $lF, $a) {
                $k = fmod($n + $h / 30, 12);
                $c = $lF - $a * max(min($k - 3, 9 - $k, 1), -1);
                return str_pad(dechex((int)round(255 * max(0, min(1, $c)))), 2, '0', STR_PAD_LEFT);
            };
            $color = '#' . $f(0) . $f(8) . $f(4);
            $vars .= "--brand-{$shade}:{$color};";
        }
        $inject .= "<style>:root{{$vars}}</style>";
    }

    $html = str_replace('</head>', $inject . '</head>', $html);

    // Replace the default title with the DB site name
    $siteName = $settings['site_name'] ?? '';
    if ($siteName) {
        $html = str_replace('<title>Runners World</title>', '<title>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</title>', $html);
    }

    echo $html;
    exit;
}

// ── Social bot detected: serve OG meta tags ──

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/functions.php';

$db = getDB();
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
// Remove base path prefix
$path = preg_replace('#^' . preg_quote($basePath, '#') . '#', '', $path);
$path = ltrim($path, '/');

// Defaults from settings
$siteName = getSetting('site_name', 'Runners World');
$siteNameMn = getSetting('site_name_mn', $siteName);
$siteSlogan = getSetting('site_slogan', '');
$siteLogo = getSetting('site_logo', '');
$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') 
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$ogTitle = $siteNameMn ?: $siteName;
$ogDescription = $siteSlogan;
$ogImage = $siteLogo ? $baseUrl . $basePath . 'backend/uploads/media/' . $siteLogo : '';
$ogUrl = $baseUrl . $basePath . $path;
$ogType = 'website';
$ogPrice = '';
$ogCurrency = 'MNT';

// ── Product page: /product/{slug-or-id} ──
if (preg_match('#^product/(.+)$#', $path, $m)) {
    $productIdOrSlug = $m[1];
    
    if (is_numeric($productIdOrSlug)) {
        $stmt = $db->prepare("SELECT p.*, m.filename as main_image_filename, s.name as shop_name
            FROM products p
            LEFT JOIN media m ON p.main_image_id = m.id
            LEFT JOIN shops s ON p.shop_id = s.id
            WHERE p.id = ? AND p.is_active = 1");
        $stmt->execute([(int)$productIdOrSlug]);
    } else {
        $stmt = $db->prepare("SELECT p.*, m.filename as main_image_filename, s.name as shop_name
            FROM products p
            LEFT JOIN media m ON p.main_image_id = m.id
            LEFT JOIN shops s ON p.shop_id = s.id
            WHERE p.slug = ? AND p.is_active = 1");
        $stmt->execute([$productIdOrSlug]);
    }
    
    $product = $stmt->fetch();
    
    if ($product) {
        $ogTitle = ($product['name_mn'] ?: $product['name']) . ' - ' . ($siteNameMn ?: $siteName);
        $ogDescription = mb_substr(strip_tags($product['description_mn'] ?: $product['description'] ?: ''), 0, 200);
        if (!$ogDescription) {
            $ogDescription = number_format((float)$product['price']) . '₮';
            if ($product['shop_name']) $ogDescription .= ' | ' . $product['shop_name'];
        }
        $ogType = 'product';
        $ogPrice = number_format((float)$product['price'], 2, '.', '');
        
        // Product image
        if ($product['main_image_filename']) {
            $ogImage = $baseUrl . $basePath . 'backend/uploads/media/' . $product['main_image_filename'];
        } elseif ($product['image'] && !str_starts_with($product['image'], 'http')) {
            $ogImage = $baseUrl . $basePath . 'backend/' . $product['image'];
        } elseif ($product['image']) {
            $ogImage = $product['image'];
        }
    }
}

// ── Category page: /category/{slug} ──
elseif (preg_match('#^category/(.+)$#', $path, $m)) {
    $catSlug = $m[1];
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$catSlug]);
    $cat = $stmt->fetch();
    if ($cat) {
        $ogTitle = ($cat['name_mn'] ?: $cat['name']) . ' - ' . ($siteNameMn ?: $siteName);
        $ogDescription = ($cat['icon'] ?? '') . ' ' . ($cat['name_mn'] ?: $cat['name']) . ' категори';
    }
}

// ── Shop page: /shop/{slug} ──
elseif (preg_match('#^shop/(.+)$#', $path, $m)) {
    $shopSlug = $m[1];
    $stmt = $db->prepare("SELECT * FROM shops WHERE slug = ? AND is_active = 1");
    $stmt->execute([$shopSlug]);
    $shop = $stmt->fetch();
    if ($shop) {
        $ogTitle = ($shop['name_mn'] ?: $shop['name']) . ' - ' . ($siteNameMn ?: $siteName);
        $ogDescription = mb_substr(strip_tags($shop['description_mn'] ?: $shop['description'] ?: ''), 0, 200);
        if ($shop['logo']) {
            $ogImage = $baseUrl . $basePath . 'backend/uploads/media/' . $shop['logo'];
        }
    }
}

// Ensure image is absolute URL
if ($ogImage && !str_starts_with($ogImage, 'http')) {
    $ogImage = $baseUrl . $ogImage;
}

// Escape for HTML output
$e = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
?><!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($ogTitle) ?></title>
<meta name="description" content="<?= $e($ogDescription) ?>">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?= $e($ogType) ?>">
<meta property="og:url" content="<?= $e($ogUrl) ?>">
<meta property="og:title" content="<?= $e($ogTitle) ?>">
<meta property="og:description" content="<?= $e($ogDescription) ?>">
<meta property="og:site_name" content="<?= $e($siteNameMn ?: $siteName) ?>">
<?php if ($ogImage): ?>
<meta property="og:image" content="<?= $e($ogImage) ?>">
<meta property="og:image:width" content="600">
<meta property="og:image:height" content="600">
<?php endif; ?>

<?php if ($ogType === 'product' && $ogPrice): ?>
<!-- Product specific -->
<meta property="product:price:amount" content="<?= $e($ogPrice) ?>">
<meta property="product:price:currency" content="<?= $e($ogCurrency) ?>">
<?php endif; ?>

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($ogTitle) ?>">
<meta name="twitter:description" content="<?= $e($ogDescription) ?>">
<?php if ($ogImage): ?>
<meta name="twitter:image" content="<?= $e($ogImage) ?>">
<?php endif; ?>

</head>
<body>
<h1><?= $e($ogTitle) ?></h1>
<p><?= $e($ogDescription) ?></p>
<?php if ($ogImage): ?>
<img src="<?= $e($ogImage) ?>" alt="<?= $e($ogTitle) ?>">
<?php endif; ?>
</body>
</html>
