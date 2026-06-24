<?php
/**
 * API: QPay Payment Gateway
 * 
 * POST /api/qpay.php?action=create-invoice
 *   Body: { "order_number": "...", "amount": 12345, "description": "..." }
 *   Returns: { "invoice_id": "...", "qr_image": "base64...", "urls": [...] }
 * 
 * POST /api/qpay.php?action=check-payment
 *   Body: { "invoice_id": "..." }
 *   Returns: { "paid": true/false, "payment_info": {...} }
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
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// ── QPay V2 API Helper ──
class QPayClient {
    private string $username;
    private string $password;
    private string $invoiceCode;
    private string $baseUrl = 'https://merchant.qpay.mn/v2';
    private ?string $accessToken = null;

    public function __construct(string $username, string $password, string $invoiceCode) {
        $this->username = $username;
        $this->password = $password;
        $this->invoiceCode = $invoiceCode;
    }

    private function authenticate(): void {
        if ($this->accessToken) return;

        $ch = curl_init($this->baseUrl . '/auth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('QPay холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $msg = $data['message'] ?? $data['error'] ?? 'Authentication failed';
            throw new Exception('QPay нэвтрэх алдаа: ' . $msg);
        }

        $this->accessToken = $data['access_token'];
    }

    public function createInvoice(string $orderNumber, float $amount, string $description, string $callbackUrl = ''): array {
        $this->authenticate();

        $payload = [
            'invoice_code' => $this->invoiceCode,
            'sender_invoice_no' => $orderNumber,
            'invoice_receiver_code' => 'terminal',
            'invoice_description' => $description,
            'amount' => $amount,
            'callback_url' => $callbackUrl ?: ($this->getBaseUrl() . '/api/qpay-callback.php?order=' . urlencode($orderNumber)),
        ];

        $ch = curl_init($this->baseUrl . '/invoice');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('QPay нэхэмжлэх үүсгэхэд холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['invoice_id'])) {
            $msg = $data['message'] ?? $data['error'] ?? 'Invoice creation failed';
            throw new Exception('QPay нэхэмжлэх алдаа: ' . $msg);
        }

        return $data;
    }

    public function checkPayment(string $invoiceId): array {
        $this->authenticate();

        $payload = [
            'object_type' => 'INVOICE',
            'object_id' => $invoiceId,
        ];

        $ch = curl_init($this->baseUrl . '/payment/check');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('QPay төлбөр шалгахад холболтын алдаа: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $msg = $data['message'] ?? $data['error'] ?? 'Payment check failed';
            throw new Exception('QPay төлбөр шалгах алдаа: ' . $msg);
        }

        return $data;
    }

    private function getBaseUrl(): string {
        // Use configured site URL to avoid HTTP_HOST spoofing
        $siteUrl = getSetting('site_url', '');
        if ($siteUrl) {
            return rtrim($siteUrl, '/');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}

// ── Load QPay credentials ──
$qpayUsername = getSetting('qpay_username');
$qpayPassword = getSetting('qpay_password');
$qpayInvoiceCode = getSetting('qpay_invoice_code');

if (empty($qpayUsername) || empty($qpayPassword) || empty($qpayInvoiceCode)) {
    http_response_code(503);
    echo json_encode(['error' => 'QPay тохиргоо хийгдээгүй байна. Админ тохиргооноос QPay мэдээллийг оруулна уу.']);
    exit;
}

$qpay = new QPayClient($qpayUsername, $qpayPassword, $qpayInvoiceCode);

try {
    switch ($action) {
        case 'create-invoice':
            $orderNumber = trim($input['order_number'] ?? '');
            $amount = (float)($input['amount'] ?? 0);
            $description = trim($input['description'] ?? '');

            if (empty($orderNumber) || $amount <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'order_number болон amount шаардлагатай']);
                exit;
            }

            if (empty($description)) {
                $description = 'Runners World захиалга #' . $orderNumber;
            }

            $result = $qpay->createInvoice($orderNumber, $amount, $description);

            // Store invoice_id in orders table for tracking
            $db = getDB();
            $stmt = $db->prepare("UPDATE orders SET qpay_invoice_id = ? WHERE order_number = ?");
            $stmt->execute([$result['invoice_id'], $orderNumber]);

            echo json_encode([
                'success' => true,
                'invoice_id' => $result['invoice_id'],
                'qr_image' => $result['qr_image'] ?? '',
                'qr_text' => $result['qr_text'] ?? '',
                'urls' => $result['urls'] ?? [],
            ]);
            break;

        case 'check-payment':
            $invoiceId = trim($input['invoice_id'] ?? '');
            if (empty($invoiceId)) {
                http_response_code(400);
                echo json_encode(['error' => 'invoice_id шаардлагатай']);
                exit;
            }

            $result = $qpay->checkPayment($invoiceId);

            $paid = false;
            $paymentInfo = null;
            if (!empty($result['rows']) && count($result['rows']) > 0) {
                $paid = true;
                $paymentInfo = $result['rows'][0];

                // Update order payment status
                $db = getDB();
                $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE qpay_invoice_id = ? AND payment_status != 'paid'");
                $stmt->execute([$invoiceId]);
            }

            echo json_encode([
                'success' => true,
                'paid' => $paid,
                'payment_info' => $paymentInfo,
            ]);
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
