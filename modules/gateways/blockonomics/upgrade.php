<?php
/**
 * This script will delete old files from previuos 1.8.2 version.
 * First delete files and folder that we know belong to blockonomics only
 * Then we delete the folders if they are empty
 * We also check if the screeshots folder and README.md file was installed and belongs to blockonomics so we can delete them too.
 */
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Blockonomics\Blockonomics;

$blockonomics = new Blockonomics();
$blockonomics->checkAdmin();

if (doubleval($blockonomics->getVersion()) < 1.9) {
    exit('Version 1.9 or higher must be installed before executing this upgrader.');
}

// Remove legacy convertto field from tblpaymentgateways table
try {
    if (Capsule::schema()->hasTable('tblpaymentgateways')) {
        Capsule::table('tblpaymentgateways')
            ->where('gateway', 'blockonomics')
            ->where('setting', 'convertto')
            ->delete();
    }
} catch (Exception $e) {
    logActivity("Blockonomics upgrade warning: Could not remove legacy 'convertto' field: " . $e->getMessage());
}

// List of files to be deleted
$filesToDelete = [
    '/payment.php',
    '/testSetup.php',
    '/css/order.css',
    '/img/bch.png',
    '/img/btc.png',
    '/img/usdt.png',
    '/js/angular-resource.min.js',
    '/js/angular.min.js',
    '/js/app.js',
    '/js/reconnecting-websocket.min.js',
    '/screenshots/screenshot-1.png',
    '/screenshots/screenshot-2.png',
    '/screenshots/screenshot-3.png',
];

// List of folders to delete, those are safe to be deleted since it belongs to Blockonomics only.
$foldersToDeleteNonCheck = [
    '/js/angular-qrcode',
    '/js/qrcode-generator',
    '/templates/blockonomics',
    '/modules/gateways/Blockonomics',
];

// List of folders to be deleted, after check if are empty and not affecting any other plugin
$foldersToDeleteChecking = [
    '/css',
    '/img',
    '/js',
    '/screenshots',
];

try {
    // Start deleting individual files
    foreach ($filesToDelete as $fileToDelete) {
        $path = ROOTDIR . $fileToDelete;
        if (file_exists($path)) {
            $FileManager = new \WHMCS\File($path);
            $FileManager->delete();
        }
    }

    // Delete safe folders
    foreach ($foldersToDeleteNonCheck as $folderToDeleteNonCheck) {
        $path = ROOTDIR . $folderToDeleteNonCheck;
        if (file_exists($path)) {
            $FileUtility = new WHMCS\Utility\File();
            $FileUtility->recursiveDelete($path, [], true);
        }
    }

    // Inspect if remaining folders are empty before delete them.
    foreach ($foldersToDeleteChecking as $folderToDeleteChecking) {
        $path = ROOTDIR . $folderToDeleteChecking;
        if (file_exists($path) && (count(scandir($path)) == 2)) {
            $FileUtility = new WHMCS\Utility\File();
            $FileUtility->recursiveDelete($path, [], true);
        }
    }

    // Finally the README.md file should not be in the root folder either and due the previous
    // installation instructions some user may have this file there
    $path = ROOTDIR . '/README.md';
    $readmeIsBlockonomics = false;
    if (file_exists($path)) {
        if (strpos(file_get_contents($path), 'Blockonomics') !== false) {
            $readmeIsBlockonomics = true;
        }

        if (file_exists($path) && $readmeIsBlockonomics) {
            $FileManager = new \WHMCS\File($path);
            $FileManager->delete();
        }
    }

    exit('Upgrade process has been completed.');
} catch (\Throwable $th) {
    //throw $th;
    echo $th->getMessage();
}
