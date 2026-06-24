<?php
// Test email authentication endpoints
echo "Testing email authentication endpoints...\n\n";

// Test 1: Send email OTP
echo "1. Testing send-email-otp.php...\n";
$ch = curl_init('http://localhost/rw/backend/api/auth/send-email-otp.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'test@example.com',
    'purpose' => 'register'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 2: Verify email OTP (we'll need to get the OTP from database first)
echo "2. Checking OTP in database...\n";
require_once 'config/database.php';
$db = getDB();
$stmt = $db->prepare("SELECT code FROM otp_codes WHERE identifier = ? AND type = 'email' ORDER BY id DESC LIMIT 1");
$stmt->execute(['test@example.com']);
$otp = $stmt->fetch();

if ($otp) {
    echo "OTP found: " . $otp['code'] . "\n";

    // Test 3: Verify the OTP
    echo "3. Testing verify-email-otp.php...\n";
    $ch = curl_init('http://localhost/rw/backend/api/auth/verify-email-otp.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'test@example.com',
        'otp' => $otp['code']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
} else {
    echo "No OTP found in database\n\n";
}

// Test 4: Register with email
echo "4. Testing register-email.php...\n";
$ch = curl_init('http://localhost/rw/backend/api/auth/register-email.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'test@example.com',
    'name' => 'Test User',
    'phone' => '99112233',
    'password' => 'testpassword123'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

echo "Test completed.\n";