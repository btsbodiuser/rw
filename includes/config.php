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

function getActivityTypes(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db    = getDB();
        $cache = $db->query("SELECT id, slug, name, name_mn, icon FROM activity_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
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
