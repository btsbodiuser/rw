<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/config/database.php';

function getBaseUrl(): string {
    static $base = null;
    if ($base === null) {
        $projectDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $docRoot    = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $base       = rtrim(str_replace($docRoot, '', $projectDir), '/') . '/';
    }
    return $base;
}

function assetUrl(string $path = ''): string {
    return getBaseUrl() . 'assets/' . ltrim($path, '/');
}

function url(string $path = ''): string {
    // Strip .php from frontend page paths so URLs stay clean.
    // Leave backend/ paths untouched (admin panel still uses .php URLs).
    $trimmed = ltrim($path, '/');
    if (!str_starts_with($trimmed, 'backend/') && !str_starts_with($trimmed, 'assets/')) {
        // Match "foo.php" or "foo.php?bar=1" — strip .php before ?
        $trimmed = preg_replace('/\.php(\?|$)/', '$1', $trimmed);
    }
    return getBaseUrl() . $trimmed;
}

function fixImageUrl(?string $img): string {
    if (!$img) return assetUrl('images/products/product-1.jpg');
    if (str_starts_with($img, 'http')) return $img;
    if (str_starts_with($img, '/backend/')) return getBaseUrl() . ltrim($img, '/');
    // "uploads/media/..." or "uploads/categories/..." — lives under backend/
    if (str_starts_with($img, 'uploads/')) return getBaseUrl() . 'backend/' . $img;
    // Bare filename (no slash) stored in settings — lives in uploads/media/
    if (!str_contains($img, '/')) return getBaseUrl() . 'backend/uploads/media/' . $img;
    return $img;
}

function formatPrice(float|int $price): string {
    return number_format((float)$price, 0, '.', ',') . '₮';
}

// A translucent tint of a shop/brand color, for hero banners and shop cards.
function hexToLight(string $hex, float $alpha = 0.1): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return "rgba(240,240,240,{$alpha})";
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    return "rgba({$r}, {$g}, {$b}, {$alpha})";
}

function cartCount(): int {
    if (empty($_SESSION['cart'])) return 0;
    return (int)array_sum(array_column($_SESSION['cart'], 'qty'));
}

function cartTotal(): float {
    if (empty($_SESSION['cart'])) return 0.0;
    return array_reduce($_SESSION['cart'], fn($sum, $item) => $sum + ($item['price'] * $item['qty']), 0.0);
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function getSessionUser(): ?array {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

function customerToken(): ?string {
    return $_SESSION['customer_token'] ?? null;
}

function loginCustomerSession(array $user, string $token): void {
    $_SESSION['user_id']        = (int)$user['id'];
    $_SESSION['user']           = $user;
    $_SESSION['customer_token'] = $token;
}

function logoutCustomerSession(): void {
    unset($_SESSION['user_id'], $_SESSION['user'], $_SESSION['customer_token']);
    forgetCustomerCookie();
}

/**
 * "Stay Logged In" — a long-lived cookie (independent of the PHP session
 * cookie) holding the same 7-day customer_sessions token the API already
 * issues. Lets a returning visitor get silently re-authenticated below even
 * after their PHP session has expired or the browser was restarted.
 */
function rememberCustomer(string $token): void {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('rw_remember', $token, [
        'expires'  => time() + 7 * 24 * 3600,
        'path'     => rtrim(getBaseUrl(), '/') . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function forgetCustomerCookie(): void {
    setcookie('rw_remember', '', [
        'expires'  => time() - 3600,
        'path'     => rtrim(getBaseUrl(), '/') . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Internal call to the existing customer-facing JSON API under backend/api/.
 * Bridges our server-rendered pages to the already-built auth/orders/address
 * endpoints instead of re-implementing password hashing, OTP, etc.
 * Returns ['code' => int, 'data' => array].
 */
function apiCall(string $method, string $path, ?array $body = null, ?string $token = null): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url    = $scheme . '://' . $host . rtrim(getBaseUrl(), '/') . '/backend/api/' . ltrim($path, '/');

    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$raw, true);
    return ['code' => $code, 'data' => is_array($data) ? $data : []];
}

function getSettings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db   = getDB();
        $rows = $db->query("SELECT setting_key, setting_value, type FROM settings WHERE is_public = 1")->fetchAll();
        $cache = [];
        foreach ($rows as $row) {
            $v = $row['setting_value'];
            if ($row['type'] === 'number' && is_numeric($v)) {
                $v = str_contains($v, '.') ? (float)$v : (int)$v;
            } elseif ($row['type'] === 'boolean') {
                $v = ($v === '1' || $v === 'true');
            }
            $cache[$row['setting_key']] = $v;
        }
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}

function s(string $key, string $fallback = ''): string {
    return (string)(getSettings()[$key] ?? $fallback);
}

function sBool(string $key, bool $fallback = true): bool {
    $settings = getSettings();
    if (!array_key_exists($key, $settings)) return $fallback;
    $v = $settings[$key];
    if (is_bool($v)) return $v;
    return !in_array($v, ['0', 'false', false, 0], true);
}

function getCategories(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db    = getDB();
        $cache = $db->query("SELECT id, slug, name, name_mn, icon, image FROM categories ORDER BY sort_order, name_mn")->fetchAll();
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}

/**
 * Fetch all active banners for a given location slug (e.g. 'hero_home').
 * Returns an empty array when the location doesn't exist / has no active banners.
 * Callers can render 0/1/N banners without extra guards.
 */
function getBannersForLocation(string $slug): array {
    static $cache = [];
    if (isset($cache[$slug])) return $cache[$slug];
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT s.id, s.title_mn, s.subtitle_mn, s.btn_text, s.btn_url,
                   s.image, s.text_dark, s.sort_order
            FROM sliders s
            JOIN banner_locations bl ON bl.id = s.location_id
            WHERE s.is_active = 1
              AND bl.is_active = 1
              AND bl.slug = ?
            ORDER BY s.sort_order, s.id
        ");
        $stmt->execute([$slug]);
        $cache[$slug] = $stmt->fetchAll();
    } catch (Throwable) {
        $cache[$slug] = [];
    }
    return $cache[$slug];
}

function getShops(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db    = getDB();
        $cache = $db->query("SELECT id, slug, name, name_mn, color, logo FROM shops WHERE is_active = 1 ORDER BY sort_order, name_mn, name")->fetchAll();
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}

/**
 * Curated "popular" brands — sort_order < 100 is the admin-set cutoff that
 * separates the hand-picked top brands from the bulk-imported catalog.
 * Used on the home page and in the nav's Брэнд megamenu.
 */
function getPopularShops(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db    = getDB();
        $cache = $db->query("SELECT id, slug, name, name_mn, color, logo FROM shops WHERE is_active = 1 AND sort_order < 100 ORDER BY sort_order, name_mn, name")->fetchAll();
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}


/**
 * One representative product image per gender (men/women), for the
 * "Shop now" banner cards in the Shop mega menu. Picks the newest
 * in-stock product with a photo for each gender.
 */
function getGenderShowcaseImages(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        $db = getDB();
        foreach (['men', 'women'] as $gender) {
            $stmt = $db->prepare("
                SELECT p.image, m.filename AS main_image_filename
                FROM products p
                LEFT JOIN media m ON p.main_image_id = m.id
                WHERE p.show_in_store = 1 AND p.gender = ?
                  AND (p.image IS NOT NULL OR p.main_image_id IS NOT NULL)
                ORDER BY p.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$gender]);
            $row = $stmt->fetch();
            if ($row) {
                $cache[$gender] = !empty($row['main_image_filename'])
                    ? 'uploads/media/' . $row['main_image_filename']
                    : $row['image'];
            }
        }
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}

// ── Remember-me bootstrap ────────────────────────────────────
// Runs once per request. If the PHP session doesn't already carry a logged-in
// customer but a long-lived "rw_remember" cookie does, silently validate it
// against the API and restore the session — this is what makes "Stay Logged
// In" survive a closed browser / expired PHP session.
if (!isLoggedIn() && !empty($_COOKIE['rw_remember'])) {
    $rememberRes = apiCall('GET', 'auth/me.php', null, $_COOKIE['rw_remember']);
    if ($rememberRes['code'] === 200 && !empty($rememberRes['data']['success'])) {
        loginCustomerSession($rememberRes['data']['user'], $_COOKIE['rw_remember']);
    } else {
        forgetCustomerCookie();
    }
    unset($rememberRes);
}
