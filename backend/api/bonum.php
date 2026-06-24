<?php
/**
 * API: Bonum Payment Gateway
 *
 * POST /api/bonum.php?action=create-invoice
 *   Body: { "order_number": "...", "amount": 12345, "description": "..." }
 *   Returns: { "invoice_id": "...", "follow_up_link": "https://..." }
 *
 * POST /api/bonum.php?action=check-payment
 *   Body: { "invoice_id": "..." }
 *   Returns: { "paid": true/false }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// ── Bonum Ecommerce API Client ──────────────────────────────────
class BonumClient {
    private string $terminalId;
    private string $appSecret;
    private string $baseUrl = 'https://apis.bonum.mn';
    private ?string $accessToken  = null;
    private ?string $refreshToken = null;

    public function __construct(string $terminalId, string $appSecret) {
        $this->terminalId = $terminalId;
        $this->appSecret  = $appSecret;
    }

    // ── Token cache using a temp file per terminal ──────────────
    private function tokenCacheFile(): string {
        return sys_get_temp_dir() . '/bonum_tok_' . md5($this->terminalId) . '.json';
    }

    private function loadCachedToken(): void {
        $file = $this->tokenCacheFile();
        if (!file_exists($file)) return;
        $data = json_decode(file_get_contents($file), true);
        $now  = time();
        if (!empty($data['token']) && !empty($data['expires_at']) && $data['expires_at'] > $now + 60) {
            $this->accessToken = $data['token'];
        }
        if (!empty($data['refresh_token']) && !empty($data['refresh_expires_at']) && $data['refresh_expires_at'] > $now + 60) {
            $this->refreshToken = $data['refresh_token'];
        }
    }

    private function saveCachedToken(string $token, int $expiresIn, string $refreshToken = '', int $refreshExpiresIn = 0): void {
        $now = time();
        file_put_contents(
            $this->tokenCacheFile(),
            json_encode([
                'token'              => $token,
                'expires_at'         => $now + $expiresIn - 60,
                'refresh_token'      => $refreshToken,
                'refresh_expires_at' => $refreshExpiresIn > 0 ? $now + $refreshExpiresIn - 60 : 0,
            ])
        );
    }

    // ── Refresh accessToken using refreshToken ──────────────────
    private function refreshAccessToken(): bool {
        if (!$this->refreshToken) return false;

        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/auth/refresh');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->refreshToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) return false;

        $data = json_decode($response, true);
        if (empty($data['accessToken'])) return false;

        $this->accessToken  = $data['accessToken'];
        $this->refreshToken = $data['refreshToken'] ?? $this->refreshToken;
        $this->saveCachedToken(
            $data['accessToken'],
            (int)($data['expiresIn']        ?? 1800),
            $this->refreshToken,
            (int)($data['refreshExpiresIn'] ?? 2000)
        );
        return true;
    }

    // ── Authentication ──────────────────────────────────────────
    private function authenticate(): void {
        $this->loadCachedToken();
        if ($this->accessToken) return;

        // accessToken expired — try refresh before calling /auth/create
        if ($this->refreshAccessToken()) return;

        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/auth/create');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: AppSecret ' . $this->appSecret,
                'X-TERMINAL-ID: '           . $this->terminalId,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Bonum холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);

        // 429 = rate-limited; old token should still be valid but cache missed
        if ($httpCode === 429) {
            throw new Exception('Bonum rate limit: ' . ($data['message'] ?? 'Too many token requests'));
        }

        if ($httpCode !== 200 || empty($data['accessToken'])) {
            throw new Exception('Bonum нэвтрэх алдаа: ' . ($data['message'] ?? 'Authentication failed'));
        }

        $this->accessToken  = $data['accessToken'];
        $this->refreshToken = $data['refreshToken'] ?? '';
        $this->saveCachedToken(
            $data['accessToken'],
            (int)($data['expiresIn']        ?? 1800),
            $this->refreshToken,
            (int)($data['refreshExpiresIn'] ?? 2000)
        );
    }

    // ── Create invoice → returns invoiceId + followUpLink ───────
    public function createInvoice(string $orderNumber, float $amount, string $description, string $callbackUrl = ''): array {
        $this->authenticate();

        $payload = [
            'amount'        => $amount,
            'callback'      => $callbackUrl,
            'transactionId' => $orderNumber,
            'expiresIn'     => 3600,
            'providers'     => ['QPAY', 'E_COMMERCE'],
            'items'         => [[
                'title'  => $description ?: ('Runners World захиалга #' . $orderNumber),
                'remark' => 'Runners World',
                'amount' => $amount,
                'count'  => 1,
            ]],
        ];

        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/invoices');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
                'X-TERMINAL-ID: '        . $this->terminalId,
                'Accept-Language: mn',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Bonum нэхэмжлэх үүсгэхэд холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['invoiceId'])) {
            throw new Exception('Bonum нэхэмжлэх алдаа: ' . ($data['message'] ?? 'Invoice creation failed'));
        }

        return $data;
    }

    // ── Get invoice status ───────────────────────────────────────
    public function getInvoiceStatus(string $invoiceId): array {
        $this->authenticate();

        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/invoices/' . urlencode($invoiceId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'X-TERMINAL-ID: '        . $this->terminalId,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Bonum статус шалгахад холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            throw new Exception('Bonum статус шалгах алдаа: ' . ($data['message'] ?? 'Status check failed'));
        }

        return $data;
    }

    private function getSiteBaseUrl(): string {
        $siteUrl = getSetting('site_url', '');
        if ($siteUrl) return rtrim($siteUrl, '/');
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public function buildCallbackUrl(string $orderNumber): string {
        return $this->getSiteBaseUrl() . '/backend/api/bonum-callback.php?order=' . urlencode($orderNumber);
    }
}

// ── Load credentials ────────────────────────────────────────────
$terminalId = getSetting('bonum_terminal_id');
$secretKey  = getSetting('bonum_secret_key');

if (empty($terminalId) || empty($secretKey)) {
    http_response_code(503);
    echo json_encode(['error' => 'Bonum тохиргоо хийгдээгүй байна. Админ тохиргооноос Bonum мэдээллийг оруулна уу.']);
    exit;
}

$bonum = new BonumClient($terminalId, $secretKey);
$db    = getDB();

try {
    switch ($action) {

        // ── Create invoice ───────────────────────────────────────
        case 'create-invoice':
            $orderNumber = trim($input['order_number'] ?? '');
            $amount      = (float)($input['amount'] ?? 0);
            $description = trim($input['description'] ?? '');

            if (empty($orderNumber) || $amount <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'order_number болон amount шаардлагатай']);
                exit;
            }

            if (empty($description)) {
                $description = 'Runners World захиалга #' . $orderNumber;
            }

            $callbackUrl = $bonum->buildCallbackUrl($orderNumber);
            $result      = $bonum->createInvoice($orderNumber, $amount, $description, $callbackUrl);

            // Store invoice_id in orders for tracking
            $db->prepare("UPDATE orders SET bonum_invoice_id = ? WHERE order_number = ?")
               ->execute([$result['invoiceId'], $orderNumber]);

            echo json_encode([
                'success'         => true,
                'invoice_id'      => $result['invoiceId'],
                'follow_up_link'  => $result['followUpLink'] ?? '',
            ]);
            break;

        // ── Check payment status ─────────────────────────────────
        case 'check-payment':
            $invoiceId = trim($input['invoice_id'] ?? '');
            if (empty($invoiceId)) {
                http_response_code(400);
                echo json_encode(['error' => 'invoice_id шаардлагатай']);
                exit;
            }

            $data = $bonum->getInvoiceStatus($invoiceId);

            // Status field may be nested or at top level depending on API version
            $status = $data['status'] ?? ($data['body']['status'] ?? ($data['invoiceStatus'] ?? ''));
            $paid   = strtoupper((string)$status) === 'PAID';

            if ($paid) {
                // Find the order by invoice ID and mark as paid
                $order = $db->prepare("SELECT id, order_number, status FROM orders WHERE bonum_invoice_id = ?");
                $order->execute([$invoiceId]);
                $orderRow = $order->fetch();

                if ($orderRow && $orderRow['status'] !== 'paid') {
                    $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE bonum_invoice_id = ? AND payment_status != 'paid'")
                       ->execute([$invoiceId]);

                    // Auto-mark visible cargo fees paid (included in Bonum total)
                    $db->prepare("
                        UPDATE order_items oi
                        JOIN products p ON oi.product_id = p.id
                        SET oi.cargo_fee_paid = 1
                        WHERE oi.order_id = ? AND oi.cargo_fee > 0 AND p.hide_cargo_fee = 0
                    ")->execute([$orderRow['id']]);

                    $unpaid = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
                    $unpaid->execute([$orderRow['id']]);
                    $allPaid = (int)$unpaid->fetchColumn() === 0 ? 1 : 0;
                    $db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $orderRow['id']]);

                    if ($orderRow['status'] === 'pending') {
                        $db->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ? AND status = 'pending'")
                           ->execute([$orderRow['id']]);
                    }

                    auditLog('payment_bonum', 'order', $orderRow['id'], 'system', null, [
                        'order_number' => $orderRow['order_number'],
                        'invoice_id'   => $invoiceId,
                    ]);
                }
            }

            echo json_encode(['success' => true, 'paid' => $paid]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action. Use: create-invoice, check-payment']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
