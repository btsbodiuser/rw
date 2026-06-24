<?php
/**
 * API: StorePay (BNPL) Payment Gateway
 *
 * POST /api/storepay.php?action=create-invoice
 *   Body: { "order_number": "...", "amount": 12345, "mobile_number": "9999...", "description": "..." }
 *   Returns: { "success": true, "invoice_id": 9272 }
 *
 * POST /api/storepay.php?action=check-payment
 *   Body: { "invoice_id": 9272 }
 *   Returns: { "success": true, "paid": true|false }
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

// ── StorePay Merchant API Client ────────────────────────────────
class StorePayClient {
    private string $username;
    private string $password;
    private string $appUsername;
    private string $appPassword;
    private string $storeId;
    private string $authUrl  = 'https://service.storepay.mn/merchant-uaa/oauth/token';
    private string $baseUrl  = 'https://service.storepay.mn/lend-merchant';
    private ?string $accessToken = null;

    public function __construct(string $username, string $password, string $appUsername, string $appPassword, string $storeId) {
        $this->username    = $username;
        $this->password    = $password;
        $this->appUsername = $appUsername;
        $this->appPassword = $appPassword;
        $this->storeId     = $storeId;
    }

    private function tokenCacheFile(): string {
        return sys_get_temp_dir() . '/storepay_tok_' . md5($this->appUsername . '|' . $this->username) . '.json';
    }

    private function loadCachedToken(): void {
        $file = $this->tokenCacheFile();
        if (!file_exists($file)) return;
        $data = json_decode(file_get_contents($file), true);
        if (!empty($data['token']) && !empty($data['expires_at']) && $data['expires_at'] > time() + 60) {
            $this->accessToken = $data['token'];
        }
    }

    private function saveCachedToken(string $token, int $expiresIn): void {
        file_put_contents(
            $this->tokenCacheFile(),
            json_encode(['token' => $token, 'expires_at' => time() + $expiresIn - 60])
        );
    }

    private function authenticate(): void {
        $this->loadCachedToken();
        if ($this->accessToken) return;

        $url = $this->authUrl
             . '?grant_type=password'
             . '&username=' . urlencode($this->username)
             . '&password=' . urlencode($this->password);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($this->appUsername . ':' . $this->appPassword),
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
            throw new Exception('StorePay холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $msg = $data['error_description'] ?? $data['error'] ?? $data['message'] ?? 'Authentication failed';
            throw new Exception('StorePay нэвтрэх алдаа: ' . $msg);
        }

        $this->accessToken = $data['access_token'];
        $this->saveCachedToken($data['access_token'], (int)($data['expires_in'] ?? 7200));
    }

    public function createInvoice(string $orderNumber, float $amount, string $mobileNumber, string $description, string $callbackUrl): array {
        $this->authenticate();

        $payload = [
            'storeId'      => $this->storeId,
            'mobileNumber' => $mobileNumber,
            'description'  => $description,
            'amount'       => (string)$amount,
            'callbackUrl'  => $callbackUrl,
            'requestId'    => $orderNumber,
        ];

        $ch = curl_init($this->baseUrl . '/merchant/loan');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('StorePay нэхэмжлэх үүсгэхэд холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        $status = strtolower((string)($data['status'] ?? ''));

        if ($httpCode !== 200 || $status !== 'success' || !isset($data['value']) || $data['value'] === null) {
            $msg = 'Invoice creation failed';
            if (!empty($data['msgList']) && is_array($data['msgList'])) {
                $msg = $data['msgList'][0]['code'] ?? $data['msgList'][0]['text'] ?? $msg;
            }
            throw new Exception('StorePay нэхэмжлэх алдаа: ' . $msg);
        }

        return $data;
    }

    public function checkPayment(string $loanId): array {
        $this->authenticate();

        $ch = curl_init($this->baseUrl . '/merchant/loan/check/' . urlencode($loanId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
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
            throw new Exception('StorePay төлбөр шалгахад холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $msg = $data['msgList'][0]['code'] ?? $data['message'] ?? 'Payment check failed';
            throw new Exception('StorePay төлбөр шалгах алдаа: ' . $msg);
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
        return $this->getSiteBaseUrl() . '/backend/api/storepay-callback.php?order=' . urlencode($orderNumber);
    }
}

// ── Load credentials ────────────────────────────────────────────
$username    = getSetting('storepay_username');
$password    = getSetting('storepay_password');
$appUsername = getSetting('storepay_app_username');
$appPassword = getSetting('storepay_app_password');
$storeId     = getSetting('storepay_store_id');

if (empty($username) || empty($password) || empty($appUsername) || empty($appPassword) || empty($storeId)) {
    http_response_code(503);
    echo json_encode(['error' => 'StorePay тохиргоо хийгдээгүй байна. Админ тохиргооноос StorePay мэдээллийг оруулна уу.']);
    exit;
}

$storepay = new StorePayClient($username, $password, $appUsername, $appPassword, $storeId);
$db       = getDB();

try {
    switch ($action) {

        case 'create-invoice':
            $orderNumber  = trim($input['order_number']  ?? '');
            $amount       = (float)($input['amount']     ?? 0);
            $mobileNumber = trim($input['mobile_number'] ?? '');
            $description  = trim($input['description']   ?? '');

            if ($orderNumber === '' || $amount <= 0 || $mobileNumber === '') {
                http_response_code(400);
                echo json_encode(['error' => 'order_number, amount болон mobile_number шаардлагатай']);
                exit;
            }

            // StorePay requires an 8-digit Mongolian mobile number
            if (!preg_match('/^\d{8}$/', $mobileNumber)) {
                http_response_code(400);
                echo json_encode(['error' => 'mobile_number нь 8 оронтой утасны дугаар байх ёстой']);
                exit;
            }

            if ($description === '') {
                $description = 'Runners World захиалга #' . $orderNumber;
            }

            $callbackUrl = $storepay->buildCallbackUrl($orderNumber);
            $result      = $storepay->createInvoice($orderNumber, $amount, $mobileNumber, $description, $callbackUrl);

            $loanId = (string)$result['value'];

            $db->prepare("UPDATE orders SET storepay_invoice_id = ? WHERE order_number = ?")
               ->execute([$loanId, $orderNumber]);

            echo json_encode([
                'success'    => true,
                'invoice_id' => $loanId,
            ]);
            break;

        case 'check-payment':
            $loanId = trim((string)($input['invoice_id'] ?? ''));
            if ($loanId === '') {
                http_response_code(400);
                echo json_encode(['error' => 'invoice_id шаардлагатай']);
                exit;
            }

            $data    = $storepay->checkPayment($loanId);
            $status  = strtolower((string)($data['status'] ?? ''));
            $confVal = $data['value'] ?? null;
            $paid    = ($status === 'success') && ($confVal === true || $confVal === 'true' || $confVal === 1 || $confVal === '1');

            if ($paid) {
                $order = $db->prepare("SELECT id, order_number, status, payment_status FROM orders WHERE storepay_invoice_id = ?");
                $order->execute([$loanId]);
                $orderRow = $order->fetch();

                if ($orderRow && $orderRow['payment_status'] !== 'paid') {
                    $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'")
                       ->execute([$orderRow['id']]);

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

                    auditLog('payment_storepay', 'order', $orderRow['id'], 'system', null, [
                        'order_number' => $orderRow['order_number'],
                        'invoice_id'   => $loanId,
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
