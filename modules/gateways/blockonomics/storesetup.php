<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;

// Create Blockonomics instance and check admin access
$blockonomics = new Blockonomics();
// $blockonomics->checkAdmin();

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Call the store setup method
$response = $blockonomics->checkStoreSetup();

// Return response
header('Content-Type: application/json');
echo json_encode($response);