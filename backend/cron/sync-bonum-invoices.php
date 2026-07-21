<?php
// Fallback sync for Bonum invoices — catches payments when webhook is missed
// or the customer closes the bank app without returning to the site.
// Cron: */2 * * * * php /home/fitzonem/runnersworld.mn/backend/cron/sync-bonum-invoices.php
// Web:  https://runnersworld.mn/backend/cron/sync-bonum-invoices.php?key=YOUR_SECRET

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Detect CLI/cron: shared hosting sometimes invokes `php` in CGI mode from cron,
// so also treat absence of a real HTTP request as CLI.
$isCli = (php_sapi_name() === 'cli')
      || (php_sapi_name() === 'cli-server')
      || (!isset($_SERVER['REQUEST_METHOD']) && !isset($_SERVER['HTTP_HOST']));

if (!$isCli) {
    $cronKey = getSetting('cron_secret_key', '');
    if (!$cronKey || ($_GET['key'] ?? '') !== $cronKey) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$db         = getDB();
$terminalId = getSetting('bonum_terminal_id');
$secretKey  = getSetting('bonum_secret_key');
$baseUrl    = 'https://apis.bonum.mn';

if (!$terminalId || !$secretKey) {
    if ($isCli) echo date('Y-m-d H:i:s') . " — Bonum тохиргоо байхгүй, дуусгав.\n";
    exit;
}

// ── Token helpers (mirror BonumClient in api/bonum.php) ──────────
$tokenFile = sys_get_temp_dir() . '/bonum_tok_' . md5($terminalId) . '.json';

function bonum_load_token(string $file): ?string {
    if (!file_exists($file)) return null;
    $d = json_decode(file_get_contents($file), true);
    if (!empty($d['token']) && !empty($d['expires_at']) && $d['expires_at'] > time() + 60) {
        return $d['token'];
    }
    return null;
}

function bonum_load_refresh(string $file): ?string {
    if (!file_exists($file)) return null;
    $d = json_decode(file_get_contents($file), true);
    if (!empty($d['refresh_token']) && !empty($d['refresh_expires_at']) && $d['refresh_expires_at'] > time() + 60) {
        return $d['refresh_token'];
    }
    return null;
}

function bonum_save_token(string $file, string $token, int $expiresIn, string $refreshToken = '', int $refreshExpiresIn = 0): void {
    $now = time();
    file_put_contents($file, json_encode([
        'token'              => $token,
        'expires_at'         => $now + $expiresIn - 60,
        'refresh_token'      => $refreshToken,
        'refresh_expires_at' => $refreshExpiresIn > 0 ? $now + $refreshExpiresIn - 60 : 0,
    ]));
}

function bonum_authenticate(string $baseUrl, string $terminalId, string $secretKey, string $tokenFile): ?string {
    $access = bonum_load_token($tokenFile);
    if ($access) return $access;

    // Try refresh first (avoids the 25-min rate limit on /auth/create)
    $refresh = bonum_load_refresh($tokenFile);
    if ($refresh) {
        $ch = curl_init($baseUrl . '/bonum-gateway/ecommerce/auth/refresh');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $refresh, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $d = json_decode($resp, true);
            if (!empty($d['accessToken'])) {
                bonum_save_token($tokenFile, $d['accessToken'], (int)($d['expiresIn'] ?? 1800),
                    $d['refreshToken'] ?? $refresh, (int)($d['refreshExpiresIn'] ?? 2000));
                return $d['accessToken'];
            }
        }
    }

    // Full auth (careful: Bonum rate-limits this to ~25 min)
    $ch = curl_init($baseUrl . '/bonum-gateway/ecommerce/auth/create');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: AppSecret ' . $secretKey,
            'X-TERMINAL-ID: '           . $terminalId,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return null;

    $d = json_decode($resp, true);
    if (empty($d['accessToken'])) return null;

    bonum_save_token($tokenFile, $d['accessToken'], (int)($d['expiresIn'] ?? 1800),
        $d['refreshToken'] ?? '', (int)($d['refreshExpiresIn'] ?? 2000));
    return $d['accessToken'];
}

function bonum_invoice_status(string $baseUrl, string $terminalId, string $token, string $invoiceId): ?string {
    $ch = curl_init($baseUrl . '/bonum-gateway/ecommerce/invoices/' . urlencode($invoiceId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'X-TERMINAL-ID: '        . $terminalId,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return null;
    $d = json_decode($resp, true);
    return strtoupper($d['status'] ?? ($d['body']['status'] ?? ($d['invoiceStatus'] ?? '')));
}

// ── Find candidate orders ────────────────────────────────────────
// Bonum invoices expire in 1 hour; check 3 hours back to catch late confirmations.
$stmt = $db->query("
    SELECT id, order_number, bonum_invoice_id
    FROM orders
    WHERE payment_method = 'bonum'
      AND payment_status != 'paid'
      AND status = 'pending'
      AND bonum_invoice_id IS NOT NULL
      AND created_at > DATE_SUB(NOW(), INTERVAL 3 HOUR)
");
$orders = $stmt->fetchAll();

if (empty($orders)) {
    if ($isCli) echo date('Y-m-d H:i:s') . " — Шалгах Bonum захиалга байхгүй.\n";
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents($logDir . '/sync-bonum.log',
        date('Y-m-d H:i:s') . " — Checked: 0, Paid: 0\n", FILE_APPEND | LOCK_EX);
    if (!$isCli) { header('Content-Type: application/json'); echo json_encode(['checked' => 0, 'paid' => 0]); }
    exit;
}

$token = bonum_authenticate($baseUrl, $terminalId, $secretKey, $tokenFile);
if (!$token) {
    if ($isCli) echo date('Y-m-d H:i:s') . " — Bonum-д нэвтэрч чадсангүй.\n";
    if (!$isCli) { http_response_code(502); echo 'Bonum auth failed'; }
    exit;
}

$checked = 0; $paid = 0;

foreach ($orders as $o) {
    $checked++;
    $status = bonum_invoice_status($baseUrl, $terminalId, $token, $o['bonum_invoice_id']);
    if ($status !== 'PAID') continue;

    // Mark paid + confirm (mirrors bonum-callback.php)
    $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'")
       ->execute([$o['id']]);

    $db->prepare("
        UPDATE order_items oi
        JOIN products p ON oi.product_id = p.id
        SET oi.cargo_fee_paid = 1
        WHERE oi.order_id = ? AND oi.cargo_fee > 0 AND p.hide_cargo_fee = 0
    ")->execute([$o['id']]);

    $unpaid = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
    $unpaid->execute([$o['id']]);
    $allPaid = (int)$unpaid->fetchColumn() === 0 ? 1 : 0;
    $db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $o['id']]);

    $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")
       ->execute([$o['id']]);

    auditLog('payment_bonum_sync', 'order', $o['id'], 'system', null, [
        'order_number' => $o['order_number'],
        'invoice_id'   => $o['bonum_invoice_id'],
        'source'       => 'cron-sync',
    ]);

    $paid++;
    if ($isCli) echo date('Y-m-d H:i:s') . " — Paid: {$o['order_number']}\n";
}

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents($logDir . '/sync-bonum.log',
    date('Y-m-d H:i:s') . " — Checked: {$checked}, Paid: {$paid}\n", FILE_APPEND | LOCK_EX);

if ($isCli) {
    echo date('Y-m-d H:i:s') . " — Bonum sync: Checked {$checked}, Paid {$paid}.\n";
} else {
    header('Content-Type: application/json');
    echo json_encode(['checked' => $checked, 'paid' => $paid]);
}
