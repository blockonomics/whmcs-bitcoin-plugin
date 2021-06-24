<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;
use WHMCS\ClientArea;

define('CLIENTAREA', true);

// Init Blockonomics class
$blockonomics = new Blockonomics();
require $blockonomics->getLangFilePath(isset($_REQUEST['language']) ? htmlspecialchars($_REQUEST['language']) : '');

$ca = new ClientArea();
$ca->setPageTitle('Bitcoin Payment');
$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('payment.php', 'Bitcoin Payment');
$ca->initPage();

/*
 * SET POST PARAMETERS TO VARIABLES AND CHECK IF THEY EXIST
 */
$show_order = isset($_GET["show_order"]) ? htmlspecialchars($_GET['show_order']) : "";
$crypto = isset($_GET["crypto"]) ? htmlspecialchars($_GET['crypto']) : "";
$select_crypto = isset($_GET["select_crypto"]) ? htmlspecialchars($_GET['select_crypto']) : "";
$finish_order = isset($_GET["finish_order"]) ? htmlspecialchars($_GET['finish_order']) : "";
$get_order = isset($_GET['get_order']) ? htmlspecialchars($_GET['get_order']) : "";

if($crypto === "empty"){
    $ca->setTemplate('/modules/gateways/blockonomics/assets/templates/no_crypto_selected.tpl');
}else if ($show_order && $crypto) {
    $time_period_from_db = $blockonomics->getTimePeriod();
    $time_period = isset($time_period_from_db) ? $time_period_from_db : '10';
    $ca->assign('time_period', $time_period);
    $active_currencies = $blockonomics->getActiveCurrencies();
    $ca->assign('active_currencies', json_encode($active_currencies));
    $ca->setTemplate('/modules/gateways/blockonomics/assets/templates/checkout.tpl');
}else if ($select_crypto) {
    $active_currencies = $blockonomics->getActiveCurrencies();
    $ca->assign('active_currencies', $active_currencies);
    $ca->assign('order_hash', $select_crypto);
    $ca->setTemplate('/modules/gateways/blockonomics/assets/templates/crypto_options.tpl');
}else if ($finish_order) {
    $finish_url = App::getSystemURL() . 'viewinvoice.php?id=' . $finish_order . '&paymentsuccess=true';
    header("Location: $finish_url");
    exit();
}else if ($get_order && $crypto) {
    $existing_order = $blockonomics->processOrderHash($get_order, $crypto);
    // No order exists, exit
    if (is_null($existing_order->id_order)) {
        exit();
    } else {
        header('Content-Type: application/json');
        exit(json_encode($existing_order));
    }
}

$ca->assign('_BLOCKLANG', $_BLOCKLANG);
$ca->output();

exit();
