<?php
// tests/api_test.php
// Simple automated test for backend/api.php

function apiRequest($postFields, $csrfToken = null) {
    $ch = curl_init('http://localhost/backend/api.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    if ($csrfToken) {
        $postFields['csrf_token'] = $csrfToken;
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 1. Fetch CSRF token
$csrfResp = apiRequest(['action' => 'get_csrf_token']);
assert(isset($csrfResp['csrf_token']), 'CSRF token fetch failed');
$csrfToken = $csrfResp['csrf_token'];
echo "CSRF token: $csrfToken\n";

// 2. Test login (no CSRF required)
$loginResp = apiRequest([
    'action' => 'login',
    'email' => 'test@example.com',
    'password' => 'testpassword'
]);
assert(isset($loginResp['success']), 'Login response missing');
echo "Login: ", json_encode($loginResp), "\n";

// 3. Test protected endpoint (get_pets)
$getPetsResp = apiRequest([
    'action' => 'get_pets',
    'user_id' => 1 // Use a valid user_id for your DB
], $csrfToken);
assert(isset($getPetsResp['success']), 'get_pets response missing');
echo "get_pets: ", json_encode($getPetsResp), "\n";

// 4. Test CSRF protection (should fail)
$failResp = apiRequest([
    'action' => 'get_pets',
    'user_id' => 1
]);
assert(!$failResp['success'], 'CSRF protection failed');
echo "CSRF protection test: ", json_encode($failResp), "\n";

// Add more endpoint tests as needed 