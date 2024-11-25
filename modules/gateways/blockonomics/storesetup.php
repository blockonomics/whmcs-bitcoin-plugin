<?php

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include WHMCS configuration
require_once(__DIR__ . '/../../../init.php');
require_once(__DIR__ . '/../../../includes/gatewayfunctions.php');

// Get API key from request
$api_key = $_GET['api_key'] ?? '';
if (empty($api_key)) {
    http_response_code(400);
    die(json_encode(['error' => 'API key is required']));
}

// Make request to Blockonomics
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.blockonomics.co/api/v2/stores?wallets=true');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . trim($api_key),
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    http_response_code(500);
    die(json_encode(['error' => 'cURL Error: ' . $error]));
}

curl_close($ch);

// Handle response based on status code
if ($http_code === 401) {
    http_response_code(401);
    die(json_encode(['error' => 'Invalid API key or unauthorized access']));
} elseif ($http_code !== 200) {
    http_response_code($http_code);
    die(json_encode(['error' => 'Blockonomics API error', 'status' => $http_code, 'response' => $response]));
}

// Return successful response
header('Content-Type: application/json');
echo $response;