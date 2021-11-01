<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;

$blockonomics = new Blockonomics();
$error = $blockonomics->testSetup();
echo json_encode($error);
