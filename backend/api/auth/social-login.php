<?php
/**
 * API: Social Login (Google / Facebook)
 * POST /api/auth/social-login.php
 * Body: { "provider": "google"|"facebook", "token": "...", "phone"?: "99112233", "otp_token"?: "..." }
 *
 * Flow:
 * 1. Verify social token with provider
 * 2. If social_id already linked → instant login, return token + user
 * 3. If not linked but phone provided + otp_token → find/create customer, link social, return token + user
 * 4. If not linked and no phone → return needs_phone: true with social profile info
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$provider = $input['provider'] ?? '';
$socialToken = $input['token'] ?? '';
$phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
$otpToken = trim($input['otp_token'] ?? '');

if (!in_array($provider, ['google', 'facebook']) || !$socialToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Provider and token required']);
    exit;
}

$db = getDB();

// Check if this login method is enabled
$enabledKey = $provider === 'google' ? 'login_google_enabled' : 'login_facebook_enabled';
if (getSetting($enabledKey, '0') !== '1') {
    http_response_code(403);
    echo json_encode(['error' => 'Энэ нэвтрэх арга идэвхгүй байна']);
    exit;
}

// ── Verify social token ──
$socialProfile = null;
if ($provider === 'google') {
    $socialProfile = verifyGoogleToken($socialToken);
} else {
    $socialProfile = verifyFacebookToken($socialToken);
}

if (!$socialProfile) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid social token']);
    exit;
}

$socialId = $socialProfile['id'];
$socialEmail = $socialProfile['email'] ?? null;
$socialName = $socialProfile['name'] ?? '';
$socialAvatar = $socialProfile['avatar'] ?? null;

// ── Step 1: Check if social_id already linked to a customer ──
$idColumn = $provider === 'google' ? 'google_id' : 'facebook_id';
$stmt = $db->prepare("SELECT id, phone, name, email, avatar FROM customers WHERE {$idColumn} = ? LIMIT 1");
$stmt->execute([$socialId]);
$existingCustomer = $stmt->fetch();

if ($existingCustomer) {
    // Already linked → instant login
    $token = createSessionToken($db, $existingCustomer['id']);

    // Update avatar/email if changed
    $db->prepare("UPDATE customers SET avatar = COALESCE(?, avatar), email = COALESCE(?, email) WHERE id = ?")
        ->execute([$socialAvatar, $socialEmail, $existingCustomer['id']]);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => (int)$existingCustomer['id'],
            'phone' => $existingCustomer['phone'],
            'name' => $existingCustomer['name'],
        ],
    ]);
    exit;
}

// ── Step 2: Not linked yet. Need phone? ──
if (!$phone) {
    // Return social profile so frontend can show phone input step
    echo json_encode([
        'success' => true,
        'needs_phone' => true,
        'social_profile' => [
            'name' => $socialName,
            'email' => $socialEmail,
            'avatar' => $socialAvatar,
            'provider' => $provider,
        ],
    ]);
    exit;
}

// ── Step 3: Phone provided. Verify OTP token ──
if (strlen($phone) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => '8 оронтой утасны дугаар оруулна уу']);
    exit;
}

if (!$otpToken) {
    http_response_code(400);
    echo json_encode(['error' => 'OTP баталгаажуулалт шаардлагатай']);
    exit;
}

// Verify OTP token
$otpStmt = $db->prepare("SELECT id FROM otp_codes WHERE phone = ? AND otp_token = ? AND is_used = 1 AND otp_token_expires > NOW()");
$otpStmt->execute([$phone, $otpToken]);
if (!$otpStmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'OTP токен буруу эсвэл хугацаа дууссан']);
    exit;
}

// ── Step 4: Find or create customer by phone, then link social ──
$stmt = $db->prepare("SELECT id, phone, name FROM customers WHERE phone = ? LIMIT 1");
$stmt->execute([$phone]);
$customer = $stmt->fetch();

if ($customer) {
    // Existing phone customer → link social account
    $db->prepare("UPDATE customers SET {$idColumn} = ?, email = COALESCE(?, email), avatar = COALESCE(?, avatar), name = CASE WHEN name = '' OR name IS NULL THEN ? ELSE name END WHERE id = ?")
        ->execute([$socialId, $socialEmail, $socialAvatar, $socialName, $customer['id']]);

    $customerId = $customer['id'];
    $customerName = $customer['name'] ?: $socialName;
    $customerPhone = $customer['phone'];
} else {
    // New customer → create with social link. Empty password ('' not NULL since column is NOT NULL)
    // signals "no password set" — check-phone returns has_password=false so user can later
    // set a real password via the OTP / forgot-password flow.
    $stmt = $db->prepare("INSERT INTO customers (phone, password, name, email, {$idColumn}, avatar) VALUES (?, '', ?, ?, ?, ?)");
    $stmt->execute([$phone, $socialName, $socialEmail, $socialId, $socialAvatar]);

    $customerId = $db->lastInsertId();
    $customerName = $socialName;
    $customerPhone = $phone;
}

// Clean up OTP tokens
$db->prepare("DELETE FROM otp_codes WHERE phone = ?")->execute([$phone]);

// Create session
$token = createSessionToken($db, $customerId);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => (int)$customerId,
        'phone' => $customerPhone,
        'name' => $customerName,
    ],
]);

// ══════════════════════════════════════════════════
//  Helper functions
// ══════════════════════════════════════════════════

function createSessionToken(PDO $db, int $customerId): string {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
    $db->prepare("INSERT INTO customer_sessions (customer_id, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$customerId, $token, $expiresAt]);
    return $token;
}

function verifyGoogleToken(string $idToken): ?array {
    $clientId = getSetting('google_client_id', '');
    if (!$clientId) return null;

    // Verify with Google's tokeninfo endpoint
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $data = json_decode($response, true);
    if (!$data || ($data['aud'] ?? '') !== $clientId) return null;

    return [
        'id' => $data['sub'],
        'email' => $data['email'] ?? null,
        'name' => $data['name'] ?? '',
        'avatar' => $data['picture'] ?? null,
    ];
}

function verifyFacebookToken(string $accessToken): ?array {
    $appId = getSetting('facebook_app_id', '');
    if (!$appId) return null;

    // Get user profile from Facebook Graph API
    $url = 'https://graph.facebook.com/me?fields=id,name,email,picture.type(large)&access_token=' . urlencode($accessToken);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $data = json_decode($response, true);
    if (!$data || !isset($data['id'])) return null;

    return [
        'id' => $data['id'],
        'email' => $data['email'] ?? null,
        'name' => $data['name'] ?? '',
        'avatar' => $data['picture']['data']['url'] ?? null,
    ];
}
