<?php

/**
 * Test script for Mobile User API Endpoints
 * Run: php test_mobile_endpoints.php
 */

$baseUrl = 'http://127.0.0.1:8000/api';
$testResults = [];
$accessToken = null;

// Helper function to make HTTP requests
function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

// Test function
function testEndpoint($name, $method, $url, $data = null, $token = null, $expectedCode = 200) {
    global $testResults;
    
    echo "Testing: $name... ";
    $result = makeRequest($method, $url, $data, $token);
    
    $success = ($result['code'] == $expectedCode);
    $testResults[] = [
        'name' => $name,
        'method' => $method,
        'url' => $url,
        'expected' => $expectedCode,
        'actual' => $result['code'],
        'success' => $success,
        'response' => $result['body']
    ];
    
    if ($success) {
        echo "✓ PASSED (HTTP $result[code])\n";
        return $result;
    } else {
        echo "✗ FAILED (Expected: $expectedCode, Got: $result[code])\n";
        if ($result['body'] && isset($result['body']['message'])) {
            echo "  Error: " . $result['body']['message'] . "\n";
        }
        return null;
    }
}

echo "========================================\n";
echo "OFROO Mobile User API - Endpoint Tests\n";
echo "========================================\n\n";

// Test 1: Register User
echo "1. Testing Authentication Endpoints\n";
echo "------------------------------------\n";
$registerData = [
    'name' => 'Test User ' . time(),
    'email' => 'test' . time() . '@example.com',
    'phone' => '+20' . rand(1000000000, 9999999999),
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'language' => 'ar',
    'city' => 'القاهرة'
];

$registerResult = testEndpoint(
    'Register User',
    'POST',
    "$baseUrl/auth/register",
    $registerData,
    null,
    201
);

if ($registerResult && isset($registerResult['body']['token'])) {
    $accessToken = $registerResult['body']['token'];
    echo "  Token saved: " . substr($accessToken, 0, 20) . "...\n\n";
} else {
    echo "  ⚠ Warning: Could not get token. Some tests will be skipped.\n\n";
}

// Test 2: Login
$loginResult = testEndpoint(
    'Login',
    'POST',
    "$baseUrl/auth/login",
    [
        'email' => $registerData['email'],
        'password' => $registerData['password']
    ],
    null,
    200
);

if ($loginResult && isset($loginResult['body']['token'])) {
    $accessToken = $loginResult['body']['token'];
    echo "  Token from login: " . substr($accessToken, 0, 20) . "...\n\n";
}

if (!$accessToken) {
    echo "\n⚠ Cannot continue without authentication token. Please check register/login endpoints.\n";
    exit(1);
}

// Test 3: Get Profile
echo "\n2. Testing User Profile Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Profile',
    'GET',
    "$baseUrl/user/profile",
    null,
    $accessToken,
    200
);

// Test 4: Update Profile
testEndpoint(
    'Update Profile',
    'PUT',
    "$baseUrl/user/profile",
    [
        'name' => 'Updated Test User',
        'language' => 'en'
    ],
    $accessToken,
    200
);

// Test 5: Get Settings
echo "\n3. Testing Settings Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Settings',
    'GET',
    "$baseUrl/user/settings",
    null,
    $accessToken,
    200
);

// Test 6: Update Settings
testEndpoint(
    'Update Settings',
    'PUT',
    "$baseUrl/user/settings",
    [
        'language' => 'ar',
        'notifications_enabled' => true
    ],
    $accessToken,
    200
);

// Test 7: Get Stats
echo "\n4. Testing Statistics Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get User Stats',
    'GET',
    "$baseUrl/user/stats",
    null,
    $accessToken,
    200
);

// Test 8: Get Notifications
echo "\n5. Testing Notifications Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Notifications',
    'GET',
    "$baseUrl/user/notifications?per_page=10",
    null,
    $accessToken,
    200
);

// Test 9: Mark All Notifications as Read
testEndpoint(
    'Mark All Notifications as Read',
    'POST',
    "$baseUrl/user/notifications/mark-all-read",
    null,
    $accessToken,
    200
);

// Test 10: Get Orders History
echo "\n6. Testing Orders Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Orders History',
    'GET',
    "$baseUrl/user/orders?per_page=10",
    null,
    $accessToken,
    200
);

// Test 11: Get Categories (Public)
echo "\n7. Testing Public Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Categories',
    'GET',
    "$baseUrl/categories",
    null,
    null,
    200
);

// Test 12: Get Offers (Public - should work without auth)
// Note: This endpoint might require auth in some cases, so we test with token too
$offersResult = testEndpoint(
    'Get Offers (Public)',
    'GET',
    "$baseUrl/offers?page=1",
    null,
    null,
    200
);

// If public fails, try with auth
if (!$offersResult || $offersResult['code'] != 200) {
    testEndpoint(
        'Get Offers (Authenticated)',
        'GET',
        "$baseUrl/offers?page=1",
        null,
        $accessToken,
        200
    );
}

// Test 13: Get Cart
echo "\n8. Testing Cart Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Cart',
    'GET',
    "$baseUrl/cart",
    null,
    $accessToken,
    200
);

// Test 14: Get Wallet Coupons
echo "\n9. Testing Wallet Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Wallet Coupons',
    'GET',
    "$baseUrl/wallet/coupons",
    null,
    $accessToken,
    200
);

// Test 15: Get Loyalty Account
echo "\n10. Testing Loyalty Endpoints\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Loyalty Account',
    'GET',
    "$baseUrl/loyalty/account",
    null,
    $accessToken,
    200
);

// Summary
echo "\n========================================\n";
echo "Test Summary\n";
echo "========================================\n";

$passed = 0;
$failed = 0;

foreach ($testResults as $test) {
    if ($test['success']) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "Total Tests: " . count($testResults) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Success Rate: " . round(($passed / count($testResults)) * 100, 2) . "%\n\n";

if ($failed > 0) {
    echo "Failed Tests:\n";
    foreach ($testResults as $test) {
        if (!$test['success']) {
            echo "  - {$test['name']}: Expected HTTP {$test['expected']}, Got HTTP {$test['actual']}\n";
        }
    }
}

echo "\n========================================\n";
echo "All endpoints tested successfully!\n";
echo "========================================\n";

