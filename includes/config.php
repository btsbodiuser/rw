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
    return getBaseUrl() . 'ochaka/' . ltrim($path, '/');
}

function url(string $path = ''): string {
    return getBaseUrl() . ltrim($path, '/');
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
