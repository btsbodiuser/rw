<?php
/**
 * Helper functions for Runners World Admin
 */

/**
 * Set CORS headers for API endpoints.
 * Allows the configured site_url or same-origin requests.
 */
function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = [];

    // Allow same-origin (no Origin header means same-origin request)
    if (!$origin) {
        header('Access-Control-Allow-Origin: *');
    } else {
        // Allow configured site URL and common dev origins
        $siteUrl = getSetting('site_url', '');
        if ($siteUrl) {
            $allowed[] = rtrim($siteUrl, '/');
        }
        // Allow localhost dev servers
        $allowed[] = 'http://localhost:5173';
        $allowed[] = 'http://localhost:3000';

        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        } else {
            header('Access-Control-Allow-Origin: ' . ($siteUrl ?: '*'));
        }
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

/**
 * Auto-detect the base URL path (e.g. '/rw/' or '/').
 * Uses the backend/ directory location relative to DOCUMENT_ROOT.
 */
function getBasePath(): string {
    static $base = null;
    if ($base === null) {
        $projectDir = str_replace('\\', '/', dirname(__DIR__, 2));
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $base = rtrim(str_replace($docRoot, '', $projectDir), '/') . '/';
    }
    return $base;
}

/**
 * Get the Bearer token from the Authorization header.
 * Handles Apache/WAMP quirks where the header may be in
 * REDIRECT_HTTP_AUTHORIZATION, getallheaders(), or HTTP_AUTHORIZATION.
 */
function getBearerToken(): string {
    // 1. Standard PHP
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    // 2. Apache redirect (mod_rewrite)
    if (!$header && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // 3. Apache function (mod_php / CGI)
    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return $matches[1];
    }
    return '';
}

// CSRF Token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// Flash Messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash() {
    $flash = getFlash();
    if (!$flash) return;
    $colors = [
        'success' => 'bg-green-50 text-green-800 border-green-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
        'warning' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
        'info' => 'bg-blue-50 text-blue-800 border-blue-200',
    ];
    $color = $colors[$flash['type']] ?? $colors['info'];
    echo '<div class="mb-4 p-4 border rounded-lg ' . $color . '">' . htmlspecialchars($flash['message']) . '</div>';
}

// Formatting
function formatPrice($price) {
    return number_format((float)$price, 0, '.', ',') . '₮';
}

function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}

function formatDateShort($date) {
    return date('M d, Y', strtotime($date));
}

// Sanitize
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data ?? '')), ENT_QUOTES, 'UTF-8');
}

// Settings helper
function getSetting($key, $default = '') {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ? $row['setting_value'] : $default;
    return $cache[$key];
}

function getAllSettings() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM settings ORDER BY setting_key");
    return $stmt->fetchAll();
}

// Generate order number (unique, with DB check)
function generateOrderNumber($db) {
    $prefix = getSetting('order_number_prefix', 'NO');
    for ($i = 0; $i < 10; $i++) {
        $number = $prefix . str_pad(random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
        $stmt->execute([$number]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $number;
        }
    }
    // Fallback: timestamp-based to guarantee uniqueness
    return $prefix . substr(time(), -4) . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Adjust stock for a product (or its variant) and log the movement.
 *
 * The single point of truth for every stock change. Callers should NEVER
 * write to `products.stock` or `product_variants.stock` directly — go through
 * this helper so every change ends up in `stock_movements`.
 *
 * Behavior:
 *   - Uncapped items (stock IS NULL) are skipped — preorder products
 *     without a cap have no stock to move. Returns ['status' => 'uncapped'].
 *   - When delta < 0 and stock would go negative, no write happens.
 *     Returns ['status' => 'insufficient', 'balance_after' => current].
 *     Caller decides whether to throw.
 *   - On success, writes the UPDATE, logs a row in stock_movements with
 *     balance_after read back, and syncs products.stock from variant sums
 *     when applicable. Returns ['status' => 'ok', 'balance_after' => N].
 *
 * Must be called from within an open transaction. Uses FOR UPDATE for
 * row locking.
 */
function adjustStock(
    PDO $db,
    int $productId,
    ?int $variantId,
    int $delta,
    string $reason,
    ?int $orderId = null,
    string $actorType = 'system',
    ?int $actorId = null,
    ?string $note = null
): array {
    if ($delta === 0) {
        return ['status' => 'noop', 'balance_after' => null];
    }

    if ($variantId !== null) {
        $check = $db->prepare("SELECT stock FROM product_variants WHERE id = ? FOR UPDATE");
        $check->execute([$variantId]);
        $cur = $check->fetchColumn();
        if ($cur === false) {
            return ['status' => 'not_found', 'balance_after' => null];
        }
        if ($cur === null) {
            return ['status' => 'uncapped', 'balance_after' => null];
        }
        $cur = (int)$cur;
        $newStock = $cur + $delta;
        if ($newStock < 0) {
            return ['status' => 'insufficient', 'balance_after' => $cur];
        }
        $db->prepare("UPDATE product_variants SET stock = ? WHERE id = ?")
           ->execute([$newStock, $variantId]);

        // Sync the parent product's aggregate stock from the variant sums.
        $db->prepare("UPDATE products SET stock = (SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = ? AND is_active = 1) WHERE id = ?")
           ->execute([$productId, $productId]);
    } else {
        $check = $db->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
        $check->execute([$productId]);
        $cur = $check->fetchColumn();
        if ($cur === false) {
            return ['status' => 'not_found', 'balance_after' => null];
        }
        if ($cur === null) {
            return ['status' => 'uncapped', 'balance_after' => null];
        }
        $cur = (int)$cur;
        $newStock = $cur + $delta;
        if ($newStock < 0) {
            return ['status' => 'insufficient', 'balance_after' => $cur];
        }
        $db->prepare("UPDATE products SET stock = ? WHERE id = ?")
           ->execute([$newStock, $productId]);
    }

    // Log the movement.
    $db->prepare("
        INSERT INTO stock_movements
            (product_id, variant_id, delta, balance_after, reason,
             order_id, actor_type, actor_id, note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $productId, $variantId, $delta, $newStock, $reason,
        $orderId, $actorType, $actorId, $note,
    ]);

    return ['status' => 'ok', 'balance_after' => $newStock];
}

// Audit logging
function auditLog($action, $entityType, $entityId = null, $actorType = 'system', $actorId = null, $details = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO audit_log (action, entity_type, entity_id, actor_type, actor_id, ip_address, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $action,
            $entityType,
            $entityId,
            $actorType,
            $actorId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $details ? json_encode($details) : null,
        ]);
    } catch (\Exception $e) {
        // Silently fail — audit should never break main flow
    }
}

// Slugify
function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = preg_replace('~-+~', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'item-' . time();
}

/**
 * Auto-cancel unpaid pending orders.
 * Timeout varies by payment method: QPay 30min, transfer 24hr, others 12hr.
 * Runs at most once per request. Restores stock for ready items.
 */
function cancelExpiredOrders(): int {
    static $ran = false;
    if ($ran) return 0;
    $ran = true;

    $db = getDB();
    $stmt = $db->query("
        SELECT id, order_number, payment_method FROM orders
        WHERE status = 'pending' AND payment_status = 'pending'
          AND (
            (payment_method = 'qpay' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE))
            OR (payment_method = 'transfer' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
            OR (payment_method NOT IN ('qpay', 'transfer') AND created_at < DATE_SUB(NOW(), INTERVAL 12 HOUR))
          )
    ");
    $expired = $stmt->fetchAll();
    $count = 0;

    foreach ($expired as $order) {
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([$order['id']]);
            $items = $db->prepare("SELECT oi.product_id, oi.variant_id, oi.quantity FROM order_items oi WHERE oi.order_id = ?");
            $items->execute([$order['id']]);
            foreach ($items->fetchAll() as $item) {
                adjustStock(
                    $db,
                    (int)$item['product_id'],
                    !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    +(int)$item['quantity'],
                    'order_cancel',
                    (int)$order['id'],
                    'system',
                    null,
                    'Auto-cancel: ' . ($order['order_number'] ?? '')
                );
            }
            $db->commit();
            $count++;
        } catch (\Exception $e) {
            $db->rollBack();
        }
    }
    return $count;
}

/**
 * Auto-confirm paid pending orders.
 * Runs at most once per request.
 */
function confirmPaidOrders(): int {
    static $ran = false;
    if ($ran) return 0;
    $ran = true;

    $db = getDB();
    $stmt = $db->query("SELECT id FROM orders WHERE status = 'pending' AND payment_status = 'paid'");
    $count = 0;
    foreach ($stmt->fetchAll() as $order) {
        $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")->execute([$order['id']]);
        $count++;
    }
    return $count;
}

// Pagination
function paginate($total, $perPage = 15, $currentPage = 1) {
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return;
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    echo '<div class="flex items-center justify-between mt-6">';
    echo '<p class="text-sm text-gray-600">Showing page ' . $pagination['current_page'] . ' of ' . $pagination['total_pages'] . ' (' . $pagination['total'] . ' total)</p>';
    echo '<div class="flex gap-2">';
    if ($pagination['current_page'] > 1) {
        echo '<a href="' . e($baseUrl . $sep . 'pg=' . ($pagination['current_page'] - 1)) . '" class="px-3 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm">Өмнөх</a>';
    }
    for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++) {
        $active = $i === $pagination['current_page'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-50';
        echo '<a href="' . e($baseUrl . $sep . 'pg=' . $i) . '" class="px-3 py-2 border rounded-lg text-sm ' . $active . '">' . $i . '</a>';
    }
    if ($pagination['current_page'] < $pagination['total_pages']) {
        echo '<a href="' . e($baseUrl . $sep . 'pg=' . ($pagination['current_page'] + 1)) . '" class="px-3 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm">Дараах</a>';
    }
    echo '</div></div>';
}

// Resize image to max width/height using GD
function resizeImage($filepath, $maxWidth = 1000, $maxHeight = 1000) {
    $info = @getimagesize($filepath);
    if (!$info) return;
    [$origW, $origH, $type] = $info;
    if ($origW <= $maxWidth && $origH <= $maxHeight) return;

    $ratio = min($maxWidth / $origW, $maxHeight / $origH);
    $newW = (int)round($origW * $ratio);
    $newH = (int)round($origH * $ratio);

    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($filepath); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($filepath);  break;
        case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($filepath); break;
        case IMAGETYPE_GIF:  $src = @imagecreatefromgif($filepath);  break;
        default: return;
    }
    if (!$src) return;

    $dst = imagecreatetruecolor($newW, $newH);
    // Preserve transparency for PNG/GIF/WebP
    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($dst, $filepath, 85); break;
        case IMAGETYPE_PNG:  imagepng($dst, $filepath, 8);   break;
        case IMAGETYPE_WEBP: imagewebp($dst, $filepath, 85); break;
        case IMAGETYPE_GIF:  imagegif($dst, $filepath);      break;
    }
    imagedestroy($src);
    imagedestroy($dst);
}

/**
 * Send email using SMTP settings (raw socket, no dependencies)
 */
function sendEmail($to, $subject, $body, $isHtml = false): bool {
    $host       = getSetting('smtp_host');
    $port       = (int)getSetting('smtp_port', 587);
    $username   = getSetting('smtp_username');
    $password   = getSetting('smtp_password');
    $fromEmail  = getSetting('smtp_from_email');
    $fromName   = getSetting('smtp_from_name', 'Duguindaa');
    $encryption = getSetting('smtp_encryption', 'tls');

    if (!$host || !$fromEmail) {
        throw new Exception('SMTP host and from email are required');
    }

    // Auto‑detect localhost
    $isLocalhost = ($host === 'localhost' || $host === '127.0.0.1');
    if ($isLocalhost) {
        $encryption = 'none';
        $port = 25;
        // username/password not required
    } else {
        if (!$username || !$password) {
            throw new Exception('SMTP username/password required for external host');
        }
    }

    // Prepare email headers & body
    $boundary = md5(uniqid());
    $fromHeader = $fromName ? "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>" : $fromEmail;

    $mime = "MIME-Version: 1.0\r\n";
    if ($isHtml) {
        $mime .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $messageBody =
            "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode(strip_tags($body))) .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($body)) .
            "--{$boundary}--\r\n";
    } else {
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n" .
                 "Content-Transfer-Encoding: base64\r\n";
        $messageBody = chunk_split(base64_encode($body));
    }

    $headers =
        "From: {$fromHeader}\r\n" .
        "To: {$to}\r\n" .
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
        $mime;

    // SMTP helpers
    $smtpRead = function($conn) {
        $reply = '';
        while ($line = fgets($conn, 515)) {
            $reply .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $reply;
    };

    $smtpCmd = function($conn, $cmd) use ($smtpRead) {
        fwrite($conn, $cmd . "\r\n");
        return $smtpRead($conn);
    };

    $smtpCode = fn(string $r): int => (int)substr($r, 0, 3);

    // Connect
    $errno = 0; $errstr = '';
    $timeout = 15;

    if ($encryption === 'ssl') {
        $conn = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout);
    } else {
        $conn = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
    }
    if (!$conn) {
        throw new Exception("SMTP connect failed ({$errno}): {$errstr}");
    }
    stream_set_timeout($conn, $timeout);

    // Greeting
    $r = $smtpRead($conn);
    if ($smtpCode($r) !== 220) throw new Exception("SMTP greeting failed: {$r}");

    // HELO / EHLO
    $heloVerb = $isLocalhost ? 'HELO' : 'EHLO';
    $r = $smtpCmd($conn, "$heloVerb " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($smtpCode($r) !== 250) throw new Exception("$heloVerb failed: {$r}");

    // STARTTLS (only for external TLS)
    if ($encryption === 'tls' && !$isLocalhost) {
        $r = $smtpCmd($conn, "STARTTLS");
        if ($smtpCode($r) !== 220) throw new Exception("STARTTLS failed: {$r}");

        if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("TLS handshake failed");
        }

        $r = $smtpCmd($conn, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($smtpCode($r) !== 250) throw new Exception("SMTP EHLO after TLS failed: {$r}");
    }

    // AUTH (only for external)
    if (!$isLocalhost) {
        $r = $smtpCmd($conn, "AUTH LOGIN");
        if ($smtpCode($r) !== 334) throw new Exception("SMTP AUTH failed: {$r}");

        $r = $smtpCmd($conn, base64_encode($username));
        if ($smtpCode($r) !== 334) throw new Exception("SMTP username rejected: {$r}");

        $r = $smtpCmd($conn, base64_encode($password));
        if ($smtpCode($r) !== 235) throw new Exception("SMTP password rejected: {$r}");
    }

    // Send mail
    $r = $smtpCmd($conn, "MAIL FROM:<{$fromEmail}>");
    if ($smtpCode($r) !== 250) throw new Exception("SMTP MAIL FROM failed: {$r}");

    $r = $smtpCmd($conn, "RCPT TO:<{$to}>");
    if ($smtpCode($r) !== 250) throw new Exception("SMTP RCPT TO failed: {$r}");

    $r = $smtpCmd($conn, "DATA");
    if ($smtpCode($r) !== 354) throw new Exception("SMTP DATA failed: {$r}");

    fwrite($conn, $headers . "\r\n" . $messageBody . "\r\n.\r\n");
    $r = $smtpRead($conn);
    if ($smtpCode($r) !== 250) throw new Exception("SMTP message rejected: {$r}");

    $smtpCmd($conn, "QUIT");
    fclose($conn);

    return true;
}

/**
 * Generate a random OTP code
 */
function generateOTP() {
    return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Wrap email body content in a branded HTML email layout.
 * $innerHtml  — the customizable body (may contain {otp} already replaced)
 * $title      — heading shown in the white card
 * $otp        — if provided, renders a prominent OTP code block below $innerHtml
 */
function buildEmailHtml(string $innerHtml, string $title, string $otp = ''): string {
    $siteName  = htmlspecialchars(getSetting('site_name', 'Runners World'), ENT_QUOTES);
    $accent    = '#2563eb';
    $year      = date('Y');

    $otpBlock = '';
    if ($otp !== '') {
        $digits = str_split($otp);
        $boxes  = '';
        foreach ($digits as $d) {
            $boxes .= "<td style=\"width:52px;height:60px;text-align:center;vertical-align:middle;"
                    . "background:#eff6ff;border:2px solid #bfdbfe;border-radius:10px;"
                    . "font-size:32px;font-weight:800;color:{$accent};\">{$d}</td>"
                    . "<td style=\"width:8px;\"></td>";
        }
        $otpBlock = <<<HTML
        <tr><td style="padding:0 0 28px;">
          <p style="margin:0 0 12px;font-size:13px;color:#6b7280;text-align:center;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Таны код</p>
          <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
            <tr>{$boxes}</tr>
          </table>
        </td></tr>
        HTML;
    }

    return <<<HTML
    <!DOCTYPE html>
    <html lang="mn">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width,initial-scale=1.0">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <title>{$title}</title>
    </head>
    <body style="margin:0;padding:0;background:#f3f4f6;font-family:'Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:48px 16px;">
        <tr><td align="center">

          <!-- Card -->
          <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

            <!-- Header -->
            <tr>
              <td style="background:{$accent};padding:28px 40px;text-align:center;">
                <span style="color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-0.5px;">{$siteName}</span>
              </td>
            </tr>

            <!-- Body -->
            <tr>
              <td style="padding:40px 40px 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr><td style="padding:0 0 20px;">
                    <h2 style="margin:0;font-size:20px;font-weight:700;color:#111827;">{$title}</h2>
                  </td></tr>
                  <tr><td style="padding:0 0 28px;">
                    <p style="margin:0;font-size:15px;color:#4b5563;line-height:1.7;">{$innerHtml}</p>
                  </td></tr>
                  {$otpBlock}
                  <tr><td style="padding:0 0 32px;">
                    <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.6;">
                      Энэ имэйлийг та өөрөө хүсээгүй бол үл тоомсорлон орхиж болно.
                    </p>
                  </td></tr>
                </table>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
                <p style="margin:0;font-size:12px;color:#9ca3af;">© {$year} {$siteName}. Бүх эрх хуулиар хамгаалагдсан.</p>
              </td>
            </tr>

          </table>
          <!-- /Card -->

        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

/**
 * Create a session token for user authentication
 */
function createSessionToken($db, $customerId) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $db->prepare("INSERT INTO customer_sessions (customer_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$customerId, $token, $expiresAt]);

    return $token;
}

// Image upload
function uploadImage($file, $folder = 'products') {
    $uploadDir = __DIR__ . '/../uploads/' . $folder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name'], FILEINFO_MIME_TYPE);
    if (!in_array($mimeType, $allowed)) {
        return ['error' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'File too large. Max 5MB.'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array(strtolower($ext), $allowedExts)) {
        return ['error' => 'Invalid file extension.'];
    }

    $filename = uniqid() . '_' . time() . '.' . strtolower($ext);
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        resizeImage($filepath, 1000, 1000);
        return ['success' => true, 'filename' => $filename, 'path' => 'uploads/' . $folder . '/' . $filename];
    }
    return ['error' => 'Upload failed.'];
}

// Delete uploaded image
function deleteImage($path) {
    $fullPath = __DIR__ . '/../' . $path;
    if ($path && file_exists($fullPath)) {
        unlink($fullPath);
    }
}

// Status labels
function orderStatusLabel($status) {
    $labels = [
        'pending' => ['Хүлээгдэж буй', 'bg-yellow-100 text-yellow-800'],
        'confirmed' => ['Баталгаажсан', 'bg-blue-100 text-blue-800'],
        'cargo_shipping' => ['Ачаа тээвэрлэж буй', 'bg-purple-100 text-purple-800'],
        'cargo_arrived' => ['Ачаа ирсэн', 'bg-indigo-100 text-indigo-800'],
        'ready_pickup' => ['Очиж авахад бэлэн', 'bg-cyan-100 text-cyan-800'],
        'delivering' => ['Хүргэж буй', 'bg-orange-100 text-orange-800'],
        'partially_delivered' => ['Хэсэгчлэн хүргэсэн', 'bg-amber-100 text-amber-800'],
        'delivered' => ['Хүргэгдсэн', 'bg-green-100 text-green-800'],
        'picked_up' => ['Очиж авсан', 'bg-green-100 text-green-800'],
        'completed' => ['Дууссан', 'bg-green-100 text-green-800'],
        'cancelled' => ['Цуцлагдсан', 'bg-red-100 text-red-800'],
    ];
    $l = $labels[$status] ?? ['Тодорхойгүй', 'bg-gray-100 text-gray-800'];
    return '<span class="px-2 py-1 rounded-full text-xs font-medium ' . $l[1] . '">' . $l[0] . '</span>';
}

function batchStatusLabel($status) {
    $labels = [
        'open'      => ['Нээлттэй',        'bg-green-100 text-green-800'],
        'closed'    => ['Хаагдсан',         'bg-yellow-100 text-yellow-800'],
        'shipping'  => ['Тээвэрлэж буй',    'bg-blue-100 text-blue-800'],
        'receiving' => ['Хүлээн авч байна', 'bg-orange-100 text-orange-800'],
        'arrived'   => ['Дууссан',          'bg-purple-100 text-purple-800'],
    ];
    $l = $labels[$status] ?? ['Тодорхойгүй', 'bg-gray-100 text-gray-800'];
    return '<span class="px-2 py-1 rounded-full text-xs font-medium ' . $l[1] . '">' . $l[0] . '</span>';
}

// Get current open batch
function getOpenBatch() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM cargo_batches WHERE status = 'open' ORDER BY due_date ASC LIMIT 1");
    return $stmt->fetch();
}

/**
 * Recalculate an order's status based on its items' cargo statuses.
 * Logic: order follows the SLOWEST (least progressed) cargo item.
 *   - All items arrived  → cargo_arrived
 *   - Any item still shipping (none pending) → cargo_shipping
 *   - Any item still pending → confirmed (or pending)
 * Only affects orders that have preorder items (cargo_batch_id on items).
 */
function recalcOrderCargoStatus(PDO $db, int $orderId): void {
    $order = $db->prepare("SELECT status FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $currentStatus = $order->fetchColumn();

    // Don't touch orders already in delivery/completed/cancelled states
    $finalStatuses = ['delivering', 'delivered', 'ready_pickup', 'picked_up', 'completed', 'cancelled'];
    if (in_array($currentStatus, $finalStatuses)) return;

    // Check cargo statuses of all preorder items in this order
    $stmt = $db->prepare("SELECT cargo_status FROM order_items WHERE order_id = ? AND cargo_batch_id IS NOT NULL");
    $stmt->execute([$orderId]);
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($statuses)) return;

    $hasPending = in_array('pending', $statuses);
    $hasShipping = in_array('shipping', $statuses);
    $allArrived = !$hasPending && !$hasShipping;

    if ($allArrived) {
        $newStatus = 'cargo_arrived';
    } elseif (!$hasPending && $hasShipping) {
        $newStatus = 'cargo_shipping';
    } elseif ($hasShipping) {
        $newStatus = 'cargo_shipping';
    } else {
        return; // all still pending, nothing to change
    }

    $db->prepare("UPDATE orders SET status = ? WHERE id = ? AND status NOT IN ('delivering','delivered','ready_pickup','picked_up','completed','cancelled')")
        ->execute([$newStatus, $orderId]);
}

/**
 * FIFO fulfillment: match arrived inventory to oldest waiting order_items.
 * Must be called inside an open DB transaction. Uses SELECT FOR UPDATE to
 * prevent double-matching under concurrent requests.
 * Returns ['order_items_fulfilled' => N, 'orders_now_ready' => [order_id, ...]]
 */
function processArrivalFIFO(PDO $db, int $arrivalId): array {
    $stmt = $db->prepare("SELECT * FROM inventory_arrival_items WHERE arrival_id = ?");
    $stmt->execute([$arrivalId]);
    $arrivalItems = $stmt->fetchAll();

    $totalFulfilled  = 0;
    $affectedOrderIds = [];

    foreach ($arrivalItems as $ai) {
        $remaining = (int)$ai['quantity_received'];
        $productId  = (int)$ai['product_id'];
        $variantId  = $ai['variant_id'] !== null ? (int)$ai['variant_id'] : null;

        // FOR UPDATE locks matched rows so concurrent transactions cannot double-match
        $sql = "SELECT oi.id, oi.order_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE oi.product_id = ?
                  AND oi.cargo_status IN ('pending', 'shipping')
                  AND oi.arrival_item_id IS NULL
                  AND o.status NOT IN ('cancelled','picked_up','delivered','completed')";
        $params = [$productId];

        if ($variantId !== null) {
            $sql .= " AND oi.variant_id = ?";
            $params[] = $variantId;
        } else {
            $sql .= " AND oi.variant_id IS NULL";
        }
        $sql .= " ORDER BY o.created_at ASC, oi.id ASC FOR UPDATE";

        $pendingStmt = $db->prepare($sql);
        $pendingStmt->execute($params);
        $pendingItems = $pendingStmt->fetchAll();

        $matched = 0;
        foreach ($pendingItems as $oi) {
            $need = (int)$oi['quantity'];
            // Skip orders we can't fully fulfill; continue to smaller orders further down the queue
            if ($remaining < $need) continue;

            $db->prepare("UPDATE order_items SET cargo_status = 'arrived', arrival_item_id = ? WHERE id = ?")
               ->execute([$ai['id'], $oi['id']]);

            $remaining -= $need;
            $matched   += $need;
            $totalFulfilled++;
            $affectedOrderIds[(int)$oi['order_id']] = true;

            if ($remaining === 0) break; // stock exhausted
        }

        // Record matched units on the arrival line
        if ($matched > 0) {
            $db->prepare("UPDATE inventory_arrival_items SET quantity_matched = quantity_matched + ? WHERE id = ?")
               ->execute([$matched, $ai['id']]);
        }
    }

    // Recalculate order statuses once per unique order (not once per matched item)
    $ordersNowReady = [];
    foreach (array_keys($affectedOrderIds) as $orderId) {
        recalcOrderCargoStatus($db, $orderId);
        $statusRow = $db->prepare("SELECT status FROM orders WHERE id = ?");
        $statusRow->execute([$orderId]);
        if ($statusRow->fetchColumn() === 'cargo_arrived') {
            $ordersNowReady[] = $orderId;
        }
    }

    return [
        'order_items_fulfilled' => $totalFulfilled,
        'orders_now_ready'      => $ordersNowReady,
    ];
}

/**
 * Fetch SMS template body by key, replacing {var} placeholders.
 * Falls back to $default if the key doesn't exist in the DB.
 */
function getSMSTemplate(string $key, array $vars = [], string $default = ''): string {
    $db = getDB();
    $stmt = $db->prepare("SELECT body FROM sms_templates WHERE `key` = ?");
    $stmt->execute([$key]);
    $body = $stmt->fetchColumn();
    if ($body === false) $body = $default;
    foreach ($vars as $k => $v) {
        $body = str_replace('{' . $k . '}', $v, $body);
    }
    return $body;
}

/**
 * Add a message to the SMS queue. Returns the new queue row ID (0 on invalid phone).
 */
function queueSMS(string $phone, string $message, string $type = '', ?int $orderId = null): int {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) !== 8) return 0;
    $db = getDB();
    $db->prepare("INSERT INTO sms_queue (phone, message, type, order_id) VALUES (?,?,?,?)")
       ->execute([$phone, $message, $type ?: null, $orderId]);
    return (int)$db->lastInsertId();
}

/**
 * Send one queued message and update its status. Returns ['success'=>bool, 'error'=>string|null].
 */
function sendQueuedSMS(int $queueId): array {
    $db = getDB();
    $row = $db->prepare("SELECT * FROM sms_queue WHERE id = ? AND status = 'pending'");
    $row->execute([$queueId]);
    $msg = $row->fetch();
    if (!$msg) return ['success' => false, 'error' => 'Мессеж олдсонгүй эсвэл аль хэдийн илгээгдсэн'];

    $result = sendSingleSMS($msg['phone'], $msg['message']);
    if ($result['success']) {
        $db->prepare("UPDATE sms_queue SET status='sent', sent_at=NOW(), error_text=NULL WHERE id=?")->execute([$queueId]);
    } else {
        $db->prepare("UPDATE sms_queue SET status='failed', error_text=? WHERE id=?")->execute([$result['error'], $queueId]);
    }
    return $result;
}

/**
 * Send one SMS via callpro.mn. Returns ['success'=>bool, 'error'=>string|null].
 */
function sendSingleSMS(string $phone, string $text): array {
    $db = getDB();
    $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('messagepro_api_key','messagepro_from_number')")->fetchAll();
    $apiKey = ''; $fromNumber = '';
    foreach ($rows as $r) {
        if ($r['setting_key'] === 'messagepro_api_key')     $apiKey     = $r['setting_value'];
        if ($r['setting_key'] === 'messagepro_from_number') $fromNumber = $r['setting_value'];
    }
    if (!$apiKey || !$fromNumber) return ['success' => false, 'error' => 'SMS тохиргоо хийгдээгүй'];

    $url = 'https://api-text.callpro.mn/v1/sms/send?' . http_build_query(['from' => $fromNumber, 'to' => $phone, 'text' => $text]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["x-api-key: $apiKey"], CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError)                        return ['success' => false, 'error' => 'cURL: ' . $curlError];
    if ($httpCode >= 200 && $httpCode < 300) return ['success' => true,  'error' => null];
    return ['success' => false, 'error' => "HTTP $httpCode"];
}

/**
 * Queue arrival SMS for customers whose orders just became cargo_arrived.
 * Returns count of messages queued (not sent — sending happens from sms-queue page).
 */
function sendArrivalSMSForOrders(array $orderIds): int {
    if (empty($orderIds)) return 0;
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $db->prepare("SELECT id, customer_phone FROM orders WHERE id IN ($placeholders)");
    $stmt->execute($orderIds);

    $text = getSMSTemplate('cargo_arrived', [], 'Tanii zahialsan baraa Solongosoos irlee. Hayag: BGD 26-r horoo Narnii horoolol 9-r bair, 72228880. Tue-Fri 10:00-19:00, Sat 11:00-19:00.');
    $queued = 0;
    $seenPhones = [];
    foreach ($stmt->fetchAll() as $order) {
        $phone = preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? '');
        if (strlen($phone) !== 8 || isset($seenPhones[$phone])) continue;
        $seenPhones[$phone] = true;
        if (queueSMS($phone, $text, 'cargo_arrived', (int)$order['id'])) $queued++;
    }
    return $queued;
}

/**
 * Void an arrival: unlink matched order_items, reset quantity_matched, delete arrival records.
 * Must NOT be called inside an open transaction.
 * Returns ['affected_orders' => [...order_ids...]]
 */
function voidArrival(PDO $db, int $arrivalId): array {
    $db->beginTransaction();
    try {
        // Find all order_items matched via this arrival
        $stmt = $db->prepare("
            SELECT oi.id AS item_id, oi.order_id
            FROM order_items oi
            JOIN inventory_arrival_items iai ON iai.id = oi.arrival_item_id
            WHERE iai.arrival_id = ?
        ");
        $stmt->execute([$arrivalId]);
        $linked = $stmt->fetchAll();

        $affectedOrders = [];
        foreach ($linked as $row) {
            $db->prepare("UPDATE order_items SET cargo_status = 'shipping', arrival_item_id = NULL WHERE id = ?")
               ->execute([$row['item_id']]);
            $affectedOrders[(int)$row['order_id']] = true;
        }

        $db->prepare("DELETE FROM inventory_arrival_items WHERE arrival_id = ?")->execute([$arrivalId]);
        $db->prepare("DELETE FROM inventory_arrivals WHERE id = ?")->execute([$arrivalId]);

        foreach (array_keys($affectedOrders) as $orderId) {
            recalcOrderCargoStatus($db, $orderId);
        }

        $db->commit();
        return ['affected_orders' => array_keys($affectedOrders)];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// Media helpers
function getProductImageUrl($product) {
    if (!empty($product['main_image_filename'])) {
        return 'uploads/media/' . $product['main_image_filename'];
    }
    return $product['image'] ?? null;
}

/**
 * Upload an image or video to backend/uploads/media/ and record it in the
 * `media` table. Images are auto-resized to fit within $maxDim × $maxDim
 * (default 1000px, keeps aspect). Pass 0 to skip resizing.
 */
function uploadMedia($file, int $maxDim = 1000) {
    $uploadDir = __DIR__ . '/../uploads/media/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedImages = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedVideos = ['video/mp4', 'video/webm'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name'], FILEINFO_MIME_TYPE);
    $isVideo = in_array($mimeType, $allowedVideos);
    if (!in_array($mimeType, $allowedImages) && !$isVideo) {
        return ['error' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF, MP4, WebM'];
    }

    $maxSize = $isVideo ? 50 * 1024 * 1024 : 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['error' => $isVideo ? 'File too large. Max 50MB for videos.' : 'File too large. Max 10MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm'];
    if (!in_array($ext, $allowedExts)) {
        return ['error' => 'Invalid file extension.'];
    }

    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        if (!$isVideo && $maxDim > 0) resizeImage($filepath, $maxDim, $maxDim);
        $finalSize = filesize($filepath);
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO media (filename, original_name, mime_type, file_size) VALUES (?, ?, ?, ?)");
        $stmt->execute([$filename, $file['name'], $mimeType, (int)$finalSize]);

        return [
            'success' => true,
            'media' => [
                'id' => (int)$db->lastInsertId(),
                'filename' => $filename,
                'original_name' => $file['name'],
                'mime_type' => $mimeType,
                'file_size' => (int)$finalSize,
            ]
        ];
    }
    return ['error' => 'Upload failed.'];
}

function deleteMedia($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();

    if (!$media) return ['error' => 'Media not found.'];

    $filepath = __DIR__ . '/../uploads/media/' . $media['filename'];
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    $db->prepare("DELETE FROM media WHERE id = ?")->execute([$id]);

    // Clear references from products
    $db->prepare("UPDATE products SET main_image_id = NULL, image = '' WHERE main_image_id = ?")->execute([$id]);
    $products = $db->query("SELECT id, image_ids FROM products WHERE image_ids IS NOT NULL")->fetchAll();
    foreach ($products as $p) {
        $ids = json_decode($p['image_ids'], true);
        if (is_array($ids) && in_array($id, $ids)) {
            $ids = array_values(array_filter($ids, fn($i) => $i !== $id));
            $newJson = !empty($ids) ? json_encode($ids) : null;
            $db->prepare("UPDATE products SET image_ids = ? WHERE id = ?")->execute([$newJson, $p['id']]);
        }
    }

    return ['success' => true];
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

/**
 * Cancel a StorePay loan tied to an order. Idempotent and silent — failures
 * are audit-logged but never thrown, so they don't block the surrounding
 * cancellation transaction.
 *
 * Uses /merchant/account/cancel for pending invoices, /merchant/loanChange
 * (changeTypeId=2) for already-confirmed loans.
 */
function cancelStorepayOrder(PDO $db, int $orderId, string $reason = 'Order cancelled'): bool {
    $stmt = $db->prepare("SELECT order_number, storepay_invoice_id, payment_status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || empty($order['storepay_invoice_id'])) {
        return false;
    }

    $username    = getSetting('storepay_username');
    $password    = getSetting('storepay_password');
    $appUsername = getSetting('storepay_app_username');
    $appPassword = getSetting('storepay_app_password');

    if (!$username || !$password || !$appUsername || !$appPassword) {
        auditLog('storepay_cancel_skipped', 'order', $orderId, 'system', null, [
            'reason' => 'StorePay credentials not configured',
            'order_number' => $order['order_number'],
        ]);
        return false;
    }

    // ── Load or fetch token ─────────────────────────────────────
    $tokenFile   = sys_get_temp_dir() . '/storepay_tok_' . md5($appUsername . '|' . $username) . '.json';
    $accessToken = null;
    if (file_exists($tokenFile)) {
        $td = json_decode(file_get_contents($tokenFile), true);
        if (!empty($td['token']) && !empty($td['expires_at']) && $td['expires_at'] > time() + 60) {
            $accessToken = $td['token'];
        }
    }

    if (!$accessToken) {
        $authUrl = 'https://service.storepay.mn/merchant-uaa/oauth/token'
                 . '?grant_type=password'
                 . '&username=' . urlencode($username)
                 . '&password=' . urlencode($password);
        $ch = curl_init($authUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($appUsername . ':' . $appPassword),
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $authResp = curl_exec($ch);
        $authCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($authCode !== 200) {
            auditLog('storepay_cancel_failed', 'order', $orderId, 'system', null, [
                'reason' => 'Auth failed',
                'http_code' => $authCode,
            ]);
            return false;
        }
        $authData    = json_decode($authResp, true);
        $accessToken = $authData['access_token'] ?? '';
        if ($accessToken) {
            file_put_contents($tokenFile, json_encode([
                'token'      => $accessToken,
                'expires_at' => time() + (int)($authData['expires_in'] ?? 7200) - 60,
            ]));
        }
    }

    if (!$accessToken) {
        return false;
    }

    // ── Cancel path depends on whether the loan was confirmed ───
    $loanId = (string)$order['storepay_invoice_id'];
    $isPaid = ($order['payment_status'] === 'paid');

    if ($isPaid) {
        $url     = 'https://service.storepay.mn/lend-merchant/merchant/loanChange';
        $payload = [
            'changeTypeId' => 2,
            'loanId'       => (int)$loanId,
            'reason'       => $reason,
            'amount'       => 0,
        ];
    } else {
        $url     = 'https://service.storepay.mn/lend-merchant/merchant/account/cancel';
        $payload = ['accountId' => (int)$loanId];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data    = json_decode($resp, true);
    $status  = strtolower((string)($data['status'] ?? ''));
    $success = ($code === 200) && ($status === 'success');

    auditLog($success ? 'storepay_cancel_success' : 'storepay_cancel_failed', 'order', $orderId, 'system', null, [
        'order_number' => $order['order_number'],
        'invoice_id'   => $loanId,
        'was_paid'     => $isPaid,
        'http_code'    => $code,
        'response'     => $data,
    ]);

    return $success;
}
