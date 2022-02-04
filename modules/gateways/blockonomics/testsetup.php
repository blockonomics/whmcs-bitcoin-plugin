<?php

ini_set('display_errors', 1); 
error_reporting(E_ALL);

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;

$blockonomics = new Blockonomics();
$error = $blockonomics->testSetup();
exit(json_encode($error));
