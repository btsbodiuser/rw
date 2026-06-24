<?php
/**
 * API: Cargo Fee Payment
 *
 * POST /api/cargo-payment.php?action=create
 *   Auth: customer bearer token
 *   Body: { "order_number": "...", "payment_method": "qpay|bonum|storepay", "mobile_number": "..." (storepay only) }
 *   Returns: { "success": true, "cargo_payment_id": N, "invoice_id": "...", "qr_image": "...", "urls": [...] }
 *
 * POST /api/cargo-payment.php?action=check
 *   Auth: customer bearer token
 *   Body: { "cargo_payment_id": N }
 *   Returns: { "success": true, "paid": true|false }
 *
 * GET/POST /api/cargo-payment.php?action=bonum-callback   — called by Bonum server
 * GET      /api/cargo-payment.php?action=qpay-callback&id=N — called by QPay server
 * POST     /api/cargo-payment.php?action=storepay-callback&id=N — called by StorePay server
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────

function getSiteBaseUrl(): string {
    $siteUrl = getSetting('site_url', '');
    if ($siteUrl) return rtrim($siteUrl, '/');
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function markCargoPaid(int $cargoPaymentId, PDO $db): void {
    $cp = $db->prepare("SELECT order_id FROM cargo_payments WHERE id = ?");
    $cp->execute([$cargoPaymentId]);
    $row = $cp->fetch();
    if (!$row) return;
    $orderId = (int)$row['order_id'];

    $db->prepare("UPDATE cargo_payments SET status='paid', paid_at=NOW() WHERE id = ? AND status != 'paid'")
       ->execute([$cargoPaymentId]);

    // Mark all unpaid hidden cargo fee items in the order as paid
    $db->prepare("
        UPDATE order_items SET cargo_fee_paid = 1
        WHERE order_id = ? AND hide_cargo_fee = 1 AND cargo_fee > 0 AND cargo_fee_paid = 0
    ")->execute([$orderId]);

    // Re-sync orders.cargo_fee_paid
    $unpaid = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
    $unpaid->execute([$orderId]);
    $allPaid = (int)$unpaid->fetchColumn() === 0 ? 1 : 0;
    $db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $orderId]);
}

// ── BonumClient ──────────────────────────────────────────────────

class CargoBonumClient {
    private string $terminalId;
    private string $appSecret;
    private string $baseUrl = 'https://apis.bonum.mn';
    private ?string $accessToken  = null;
    private ?string $refreshToken = null;

    public function __construct(string $terminalId, string $appSecret) {
        $this->terminalId = $terminalId;
        $this->appSecret  = $appSecret;
    }

    private function tokenCacheFile(): string {
        return sys_get_temp_dir() . '/bonum_tok_' . md5($this->terminalId) . '.json';
    }

    private function loadCachedToken(): void {
        $file = $this->tokenCacheFile();
        if (!file_exists($file)) return;
        $data = json_decode(file_get_contents($file), true);
        $now  = time();
        if (!empty($data['token']) && !empty($data['expires_at']) && $data['expires_at'] > $now + 60)
            $this->accessToken = $data['token'];
        if (!empty($data['refresh_token']) && !empty($data['refresh_expires_at']) && $data['refresh_expires_at'] > $now + 60)
            $this->refreshToken = $data['refresh_token'];
    }

    private function saveCachedToken(string $token, int $expiresIn, string $refreshToken = '', int $refreshExpiresIn = 0): void {
        $now = time();
        file_put_contents($this->tokenCacheFile(), json_encode([
            'token'              => $token,
            'expires_at'         => $now + $expiresIn - 60,
            'refresh_token'      => $refreshToken,
            'refresh_expires_at' => $refreshExpiresIn > 0 ? $now + $refreshExpiresIn - 60 : 0,
        ]));
    }

    private function refreshAccessToken(): bool {
        if (!$this->refreshToken) return false;
        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/auth/refresh');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->refreshToken,'Accept: application/json'],CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($httpCode !== 200) return false;
        $data = json_decode($response, true);
        if (empty($data['accessToken'])) return false;
        $this->accessToken  = $data['accessToken'];
        $this->refreshToken = $data['refreshToken'] ?? $this->refreshToken;
        $this->saveCachedToken($data['accessToken'], (int)($data['expiresIn'] ?? 1800), $this->refreshToken, (int)($data['refreshExpiresIn'] ?? 2000));
        return true;
    }

    private function authenticate(): void {
        $this->loadCachedToken();
        if ($this->accessToken) return;
        if ($this->refreshAccessToken()) return;
        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/auth/create');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['Authorization: AppSecret '.$this->appSecret,'X-TERMINAL-ID: '.$this->terminalId,'Accept: application/json'],CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('Bonum холболтын алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode === 429) throw new Exception('Bonum rate limit: '.($data['message'] ?? 'Too many requests'));
        if ($httpCode !== 200 || empty($data['accessToken'])) throw new Exception('Bonum нэвтрэх алдаа: '.($data['message'] ?? 'Failed'));
        $this->accessToken  = $data['accessToken'];
        $this->refreshToken = $data['refreshToken'] ?? '';
        $this->saveCachedToken($data['accessToken'], (int)($data['expiresIn'] ?? 1800), $this->refreshToken, (int)($data['refreshExpiresIn'] ?? 2000));
    }

    public function createInvoice(string $ref, float $amount, string $desc, string $callbackUrl): array {
        $this->authenticate();
        $payload = ['amount'=>$amount,'callback'=>$callbackUrl,'transactionId'=>$ref,'expiresIn'=>3600,'providers'=>['QPAY','E_COMMERCE'],'items'=>[['title'=>$desc,'remark'=>'Runners World','amount'=>$amount,'count'=>1]]];
        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/invoices');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->accessToken,'X-TERMINAL-ID: '.$this->terminalId,'Accept-Language: mn','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('Bonum нэхэмжлэх үүсгэхэд алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['invoiceId'])) throw new Exception('Bonum нэхэмжлэх алдаа: '.($data['message'] ?? 'Failed'));
        return $data;
    }

    public function getInvoiceStatus(string $invoiceId): array {
        $this->authenticate();
        $ch = curl_init($this->baseUrl . '/bonum-gateway/ecommerce/invoices/' . urlencode($invoiceId));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->accessToken,'X-TERMINAL-ID: '.$this->terminalId,'Accept: application/json'],CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('Bonum статус шалгахад алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode !== 200) throw new Exception('Bonum статус алдаа: '.($data['message'] ?? 'Failed'));
        return $data;
    }
}

// ── QPayClient ───────────────────────────────────────────────────

class CargoQPayClient {
    private string $username;
    private string $password;
    private string $invoiceCode;
    private string $baseUrl = 'https://merchant.qpay.mn/v2';
    private ?string $accessToken = null;

    public function __construct(string $u, string $p, string $ic) {
        $this->username = $u; $this->password = $p; $this->invoiceCode = $ic;
    }

    private function authenticate(): void {
        if ($this->accessToken) return;
        $ch = curl_init($this->baseUrl . '/auth/token');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Basic '.base64_encode($this->username.':'.$this->password)],CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('QPay холболтын алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) throw new Exception('QPay нэвтрэх алдаа: '.($data['message'] ?? 'Failed'));
        $this->accessToken = $data['access_token'];
    }

    public function createInvoice(string $ref, float $amount, string $desc, string $callbackUrl): array {
        $this->authenticate();
        $payload = ['invoice_code'=>$this->invoiceCode,'sender_invoice_no'=>$ref,'invoice_receiver_code'=>'terminal','invoice_description'=>$desc,'amount'=>$amount,'callback_url'=>$callbackUrl];
        $ch = curl_init($this->baseUrl . '/invoice');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->accessToken],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('QPay нэхэмжлэх үүсгэхэд алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['invoice_id'])) throw new Exception('QPay нэхэмжлэх алдаа: '.($data['message'] ?? 'Failed'));
        return $data;
    }

    public function checkPayment(string $invoiceId): array {
        $this->authenticate();
        $payload = ['object_type'=>'INVOICE','object_id'=>$invoiceId];
        $ch = curl_init($this->baseUrl . '/payment/check');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->accessToken],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('QPay шалгахад алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode !== 200) throw new Exception('QPay шалгах алдаа: '.($data['message'] ?? 'Failed'));
        return $data;
    }
}

// ── StorePayClient ───────────────────────────────────────────────

class CargoStorePayClient {
    private string $username; private string $password;
    private string $appUsername; private string $appPassword; private string $storeId;
    private string $authUrl = 'https://service.storepay.mn/merchant-uaa/oauth/token';
    private string $baseUrl = 'https://service.storepay.mn/lend-merchant';
    private ?string $accessToken = null;

    public function __construct(string $u, string $p, string $au, string $ap, string $sid) {
        $this->username=$u; $this->password=$p; $this->appUsername=$au; $this->appPassword=$ap; $this->storeId=$sid;
    }

    private function tokenCacheFile(): string {
        return sys_get_temp_dir() . '/storepay_tok_' . md5($this->appUsername.'|'.$this->username) . '.json';
    }

    private function loadCachedToken(): void {
        $file = $this->tokenCacheFile();
        if (!file_exists($file)) return;
        $data = json_decode(file_get_contents($file), true);
        if (!empty($data['token']) && !empty($data['expires_at']) && $data['expires_at'] > time() + 60)
            $this->accessToken = $data['token'];
    }

    private function authenticate(): void {
        $this->loadCachedToken();
        if ($this->accessToken) return;
        $url = $this->authUrl.'?grant_type=password&username='.urlencode($this->username).'&password='.urlencode($this->password);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'',CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($this->appUsername.':'.$this->appPassword),'Accept: application/json'],CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('StorePay холболтын алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) throw new Exception('StorePay нэвтрэх алдаа: '.($data['error_description'] ?? 'Failed'));
        $this->accessToken = $data['access_token'];
        file_put_contents($this->tokenCacheFile(), json_encode(['token'=>$data['access_token'],'expires_at'=>time()+(int)($data['expires_in']??7200)-60]));
    }

    public function createInvoice(string $ref, float $amount, string $mobile, string $desc, string $callbackUrl): array {
        $this->authenticate();
        $payload = ['storeId'=>$this->storeId,'mobileNumber'=>$mobile,'description'=>$desc,'amount'=>(string)$amount,'callbackUrl'=>$callbackUrl,'requestId'=>$ref];
        $ch = curl_init($this->baseUrl . '/merchant/loan');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->accessToken,'Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('StorePay нэхэмжлэх үүсгэхэд алдаа: '.$err);
        $data = json_decode($response, true);
        $status = strtolower((string)($data['status'] ?? ''));
        if ($httpCode !== 200 || $status !== 'success' || !isset($data['value'])) {
            $msg = $data['msgList'][0]['code'] ?? $data['msgList'][0]['text'] ?? 'Failed';
            throw new Exception('StorePay нэхэмжлэх алдаа: '.$msg);
        }
        return $data;
    }

    public function checkPayment(string $loanId): array {
        $this->authenticate();
        $ch = curl_init($this->baseUrl . '/merchant/loan/check/' . urlencode($loanId));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->accessToken,'Accept: application/json'],CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) throw new Exception('StorePay шалгахад алдаа: '.$err);
        $data = json_decode($response, true);
        if ($httpCode !== 200) throw new Exception('StorePay шалгах алдаа: '.($data['message'] ?? 'Failed'));
        return $data;
    }
}

// ── Router ───────────────────────────────────────────────────────

$action = $_GET['action'] ?? '';
$db     = getDB();

// Callbacks do not require customer auth
$callbackActions = ['bonum-callback', 'qpay-callback', 'storepay-callback'];
if (!in_array($action, $callbackActions)) {
    $token = getBearerToken();
    if (!$token) { http_response_code(401); echo json_encode(['error' => 'Нэвтэрч орно уу']); exit; }

    $sess = $db->prepare("SELECT c.id, c.phone FROM customer_sessions s JOIN customers c ON c.id = s.customer_id WHERE s.token = ? AND s.expires_at > NOW()");
    $sess->execute([$token]);
    $customer = $sess->fetch();
    if (!$customer) { http_response_code(401); echo json_encode(['error' => 'Хугацаа дууссан токен']); exit; }
    $customerId = (int)$customer['id'];
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    switch ($action) {

        // ── Create cargo fee invoice ─────────────────────────────
        case 'create':
            $orderNumber   = trim($input['order_number']   ?? '');
            $method        = trim($input['payment_method'] ?? '');
            $mobileNumber  = trim($input['mobile_number']  ?? '');

            if (!$orderNumber || !in_array($method, ['qpay','bonum','storepay'])) {
                http_response_code(400);
                echo json_encode(['error' => 'order_number болон payment_method шаардлагатай']); exit;
            }
            if ($method === 'storepay' && !preg_match('/^\d{8}$/', $mobileNumber)) {
                http_response_code(400);
                echo json_encode(['error' => 'StorePay-д 8 оронтой утасны дугаар шаардлагатай']); exit;
            }

            // Verify order belongs to customer and has unpaid cargo fee
            $order = $db->prepare("
                SELECT o.id, o.order_number, o.status
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                WHERE o.order_number = ? AND c.id = ?
                  AND o.status NOT IN ('cancelled','completed','delivered','picked_up')
            ");
            $order->execute([$orderNumber, $customerId]);
            $orderRow = $order->fetch();
            if (!$orderRow) {
                http_response_code(404);
                echo json_encode(['error' => 'Захиалга олдсонгүй']); exit;
            }
            $orderId = (int)$orderRow['id'];

            // Calculate unpaid cargo fee amount
            $amtStmt = $db->prepare("
                SELECT SUM(cargo_fee) FROM order_items
                WHERE order_id = ? AND hide_cargo_fee = 1 AND cargo_fee > 0 AND cargo_fee_paid = 0
            ");
            $amtStmt->execute([$orderId]);
            $amount = (float)$amtStmt->fetchColumn();

            if ($amount <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Төлөх ачааны хураамж байхгүй байна']); exit;
            }

            // Cancel any existing pending cargo payment for this order + method
            $db->prepare("UPDATE cargo_payments SET status='failed' WHERE order_id = ? AND status='pending'")
               ->execute([$orderId]);

            // Insert pending record
            $ins = $db->prepare("INSERT INTO cargo_payments (order_id, payment_method, amount) VALUES (?,?,?)");
            $ins->execute([$orderId, $method, $amount]);
            $cpId = (int)$db->lastInsertId();

            $ref         = 'CARGO-' . $orderNumber;
            $description = 'Ачааны хураамж #' . $orderNumber;
            $baseUrl     = getSiteBaseUrl();

            if ($method === 'bonum') {
                $terminalId = getSetting('bonum_terminal_id');
                $secretKey  = getSetting('bonum_secret_key');
                if (!$terminalId || !$secretKey) { http_response_code(503); echo json_encode(['error' => 'Bonum тохиргоогүй']); exit; }

                $bonum       = new CargoBonumClient($terminalId, $secretKey);
                $callbackUrl = $baseUrl . '/backend/api/cargo-payment.php?action=bonum-callback';
                $result      = $bonum->createInvoice($ref, $amount, $description, $callbackUrl);
                $invoiceId   = $result['invoiceId'];

                $db->prepare("UPDATE cargo_payments SET invoice_id=? WHERE id=?")->execute([$invoiceId, $cpId]);
                echo json_encode(['success'=>true,'cargo_payment_id'=>$cpId,'invoice_id'=>$invoiceId,'follow_up_link'=>$result['followUpLink'] ?? '']);

            } elseif ($method === 'qpay') {
                $username    = getSetting('qpay_username');
                $password    = getSetting('qpay_password');
                $invoiceCode = getSetting('qpay_invoice_code');
                if (!$username || !$password || !$invoiceCode) { http_response_code(503); echo json_encode(['error' => 'QPay тохиргоогүй']); exit; }

                $qpay        = new CargoQPayClient($username, $password, $invoiceCode);
                $callbackUrl = $baseUrl . '/backend/api/cargo-payment.php?action=qpay-callback&id=' . $cpId;
                $result      = $qpay->createInvoice($ref, $amount, $description, $callbackUrl);
                $invoiceId   = $result['invoice_id'];

                $db->prepare("UPDATE cargo_payments SET invoice_id=? WHERE id=?")->execute([$invoiceId, $cpId]);
                echo json_encode(['success'=>true,'cargo_payment_id'=>$cpId,'invoice_id'=>$invoiceId,'qr_image'=>$result['qr_image']??'','qr_text'=>$result['qr_text']??'','urls'=>$result['urls']??[]]);

            } else { // storepay
                $spUser   = getSetting('storepay_username');
                $spPass   = getSetting('storepay_password');
                $spAppU   = getSetting('storepay_app_username');
                $spAppP   = getSetting('storepay_app_password');
                $spStore  = getSetting('storepay_store_id');
                if (!$spUser || !$spPass || !$spAppU || !$spAppP || !$spStore) { http_response_code(503); echo json_encode(['error' => 'StorePay тохиргоогүй']); exit; }

                $storepay    = new CargoStorePayClient($spUser, $spPass, $spAppU, $spAppP, $spStore);
                $callbackUrl = $baseUrl . '/backend/api/cargo-payment.php?action=storepay-callback&id=' . $cpId;
                $result      = $storepay->createInvoice($ref, $amount, $mobileNumber, $description, $callbackUrl);
                $invoiceId   = (string)$result['value'];

                $db->prepare("UPDATE cargo_payments SET invoice_id=? WHERE id=?")->execute([$invoiceId, $cpId]);
                echo json_encode(['success'=>true,'cargo_payment_id'=>$cpId,'invoice_id'=>$invoiceId]);
            }
            break;

        // ── Check payment status (customer polls) ────────────────
        case 'check':
            $cpId = (int)($input['cargo_payment_id'] ?? 0);
            if (!$cpId) { http_response_code(400); echo json_encode(['error' => 'cargo_payment_id шаардлагатай']); exit; }

            $cp = $db->prepare("SELECT cp.*, o.order_number FROM cargo_payments cp JOIN orders o ON o.id = cp.order_id WHERE cp.id = ? AND o.customer_id = ?");
            $cp->execute([$cpId, $customerId]);
            $cpRow = $cp->fetch();
            if (!$cpRow) { http_response_code(404); echo json_encode(['error' => 'Олдсонгүй']); exit; }

            if ($cpRow['status'] === 'paid') { echo json_encode(['success'=>true,'paid'=>true]); break; }

            $invoiceId = $cpRow['invoice_id'];
            $method    = $cpRow['payment_method'];
            $paid      = false;

            if ($method === 'bonum') {
                $bonum  = new CargoBonumClient(getSetting('bonum_terminal_id'), getSetting('bonum_secret_key'));
                $data   = $bonum->getInvoiceStatus($invoiceId);
                $status = $data['status'] ?? ($data['body']['status'] ?? ($data['invoiceStatus'] ?? ''));
                $paid   = strtoupper((string)$status) === 'PAID';

            } elseif ($method === 'qpay') {
                $qpay   = new CargoQPayClient(getSetting('qpay_username'), getSetting('qpay_password'), getSetting('qpay_invoice_code'));
                $data   = $qpay->checkPayment($invoiceId);
                $paid   = !empty($data['rows']) && count($data['rows']) > 0;

            } else { // storepay
                $storepay = new CargoStorePayClient(getSetting('storepay_username'),getSetting('storepay_password'),getSetting('storepay_app_username'),getSetting('storepay_app_password'),getSetting('storepay_store_id'));
                $data   = $storepay->checkPayment($invoiceId);
                $status = strtolower((string)($data['status'] ?? ''));
                $val    = $data['value'] ?? null;
                $paid   = $status === 'success' && ($val === true || $val === 'true' || $val === 1 || $val === '1');
            }

            if ($paid) markCargoPaid($cpId, $db);

            echo json_encode(['success'=>true,'paid'=>$paid]);
            break;

        // ── Bonum server callback ────────────────────────────────
        case 'bonum-callback':
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $invoiceId = $body['invoiceId'] ?? $body['transactionId'] ?? '';
            if (!$invoiceId) { http_response_code(400); exit; }

            $cp = $db->prepare("SELECT id FROM cargo_payments WHERE invoice_id = ? AND payment_method = 'bonum' AND status = 'pending'");
            $cp->execute([$invoiceId]);
            $cpRow = $cp->fetch();

            if ($cpRow) {
                $bonum  = new CargoBonumClient(getSetting('bonum_terminal_id'), getSetting('bonum_secret_key'));
                $data   = $bonum->getInvoiceStatus($invoiceId);
                $status = $data['status'] ?? ($data['body']['status'] ?? ($data['invoiceStatus'] ?? ''));
                if (strtoupper((string)$status) === 'PAID') markCargoPaid((int)$cpRow['id'], $db);
            }

            echo json_encode(['success' => true]);
            break;

        // ── QPay server callback ─────────────────────────────────
        case 'qpay-callback':
            $cpId = (int)($_GET['id'] ?? 0);
            if (!$cpId) { http_response_code(400); exit; }

            $cp = $db->prepare("SELECT id, invoice_id FROM cargo_payments WHERE id = ? AND payment_method = 'qpay' AND status = 'pending'");
            $cp->execute([$cpId]);
            $cpRow = $cp->fetch();

            if ($cpRow) {
                $qpay = new CargoQPayClient(getSetting('qpay_username'), getSetting('qpay_password'), getSetting('qpay_invoice_code'));
                $data = $qpay->checkPayment($cpRow['invoice_id']);
                if (!empty($data['rows']) && count($data['rows']) > 0) markCargoPaid($cpId, $db);
            }

            echo json_encode(['success' => true]);
            break;

        // ── StorePay server callback ─────────────────────────────
        case 'storepay-callback':
            $cpId = (int)($_GET['id'] ?? 0);
            if (!$cpId) { http_response_code(400); exit; }

            $cp = $db->prepare("SELECT id, invoice_id FROM cargo_payments WHERE id = ? AND payment_method = 'storepay' AND status = 'pending'");
            $cp->execute([$cpId]);
            $cpRow = $cp->fetch();

            if ($cpRow) {
                $storepay = new CargoStorePayClient(getSetting('storepay_username'),getSetting('storepay_password'),getSetting('storepay_app_username'),getSetting('storepay_app_password'),getSetting('storepay_store_id'));
                $data   = $storepay->checkPayment($cpRow['invoice_id']);
                $status = strtolower((string)($data['status'] ?? ''));
                $val    = $data['value'] ?? null;
                if ($status === 'success' && ($val === true || $val === 'true' || $val === 1 || $val === '1'))
                    markCargoPaid($cpId, $db);
            }

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
