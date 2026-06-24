<?php
// Test email authentication endpoints with a fresh email
echo "Testing email authentication endpoints with fresh email...\n\n";

$testEmail = 'test2@example.com';

// Test 1: Send email OTP for registration
echo "1. Testing send-email-otp.php for registration...\n";
$ch = curl_init('http://localhost/rw/backend/api/auth/send-email-otp.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $testEmail,
    'purpose' => 'register'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 2: Check OTP in database
echo "2. Checking OTP in database...\n";
require_once 'config/database.php';
$db = getDB();
$stmt = $db->prepare("SELECT code FROM otp_codes WHERE identifier = ? AND type = 'email' ORDER BY id DESC LIMIT 1");
$stmt->execute([$testEmail]);
$otpRow = $stmt->fetch();

if ($otpRow) {
    $otp = $otpRow['code'];
    echo "OTP found: " . (password_verify('123456', $otp) ? 'matches 123456' : 'does not match 123456') . "\n";

    // Test 3: Verify the OTP
    echo "3. Testing verify-email-otp.php...\n";
    $ch = curl_init('http://localhost/rw/backend/api/auth/verify-email-otp.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $testEmail,
        'otp' => '123456'
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
    'email' => $testEmail,
    'name' => 'Test User 2',
    'phone' => '99112234',
    'password' => 'testpassword123'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 5: Login with email
echo "5. Testing login.php with email...\n";
$ch = curl_init('http://localhost/rw/backend/api/auth/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'identifier' => $testEmail,
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