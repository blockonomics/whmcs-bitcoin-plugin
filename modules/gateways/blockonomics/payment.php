<?php
ini_set('display_errors', 1); 
error_reporting(E_ALL);

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;
use WHMCS\ClientArea;

define('CLIENTAREA', true);

// Init Blockonomics class
$blockonomics = new Blockonomics();
require $blockonomics->getLangFilePath(isset($_REQUEST['language']) ? $_REQUEST['language'] : '');

$ca = new ClientArea();

$ca->setPageTitle('Bitcoin Payment');

$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('payment.php', 'Bitcoin Payment');

$ca->initPage();

/*
 * SET POST PARAMETERS TO VARIABLES AND CHECK IF THEY EXIST
 */
$get_order = htmlspecialchars(isset($_REQUEST['get_order']) ? $_REQUEST['get_order'] : '');
$finish_order = htmlspecialchars(isset($_REQUEST['finish_order']) ? $_REQUEST['finish_order'] : '');

$order_hash = htmlspecialchars(isset($_REQUEST['order']) ? $_REQUEST['order'] : '');

$system_url = App::getSystemURL();

if ($get_order) {
    $blockonomics_currency = htmlspecialchars(isset($_REQUEST['blockonomics_currency']) ? $_REQUEST['blockonomics_currency'] : '');
    $existing_order = $blockonomics->processOrderHash($get_order, $blockonomics_currency);
    // No order exists, exit
    if (is_null($existing_order->id_order)) {
        exit();
    } else {
        header('Content-Type: application/json');
        exit(json_encode($existing_order));
    }
} elseif ($finish_order) {
    $finish_url = $system_url . 'viewinvoice.php?id=' . $finish_order . '&paymentsuccess=true';
    header("Location: $finish_url");
    exit();
} elseif (!$order_hash) {
    echo '<b>Error: Failed to fetch order data.</b> <br>
				Note to admin: Please check that your System URL is configured correctly.
				If you are using SSL, verify that System URL is set to use HTTPS and not HTTP. <br>
				To configure System URL, please go to WHMCS admin > Setup > General Settings > General';
    exit();
}

$ca->assign('order_uuid', $order_hash);

$time_period_from_db = $blockonomics->getTimePeriod();
$time_period = isset($time_period_from_db) ? $time_period_from_db : '10';
$ca->assign('time_period', $time_period);

$active_currencies = $blockonomics->getActiveCurrencies();
if ($active_currencies) {
    $ca->assign('active_currencies', json_encode($active_currencies));
} else {
    echo '<b>Error: No active blockonomics currencies.</b> <br>
				Note to admin: Check your API keys are configured.';
    exit();
}

$order_id = $blockonomics->getOrderIdByHash($order_hash);
$ca->assign('order_id', $order_id);
$ca->assign('_BLOCKLANG', $_BLOCKLANG);

$ca->setTemplate('/modules/gateways/blockonomics/payment.tpl');

$ca->output();
