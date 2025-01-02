<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;

$blockonomics = new Blockonomics();
$blockonomics->checkAdmin();

$error = $blockonomics->testSetup();
echo json_encode($error);
