<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;

// Create Blockonomics instance and check admin access
$blockonomics = new Blockonomics();
$blockonomics->checkAdmin();

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