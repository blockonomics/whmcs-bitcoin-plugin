<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/blockonomics.php';
require_once __DIR__ . '/usdtblockonomics.php';

use Blockonomics\Blockonomics;
use WHMCS\ClientArea;

define('CLIENTAREA', true);

// Init Blockonomics class
$blockonomics = new Blockonomics();
require $blockonomics->getLangFilePath(isset($_GET['language']) ? htmlspecialchars($_GET['language']) : '');

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
$to = isset($_GET["to"]) ? htmlspecialchars($_GET['to']) : "";
$from = isset($_GET["from"]) ? htmlspecialchars($_GET['from']) : "";
$txn = isset($_GET["txn"]) ? htmlspecialchars($_GET['txn']) : "";
$status = isset($_GET["status"]) ? htmlspecialchars($_GET['status']) : "";
$get_order = isset($_GET['get_order']) ? htmlspecialchars($_GET['get_order']) : "";

if($crypto === "empty"){
    $blockonomics->load_blockonomics_template($ca, 'no_crypto_selected');
}else if ($show_order && $crypto) {
    $blockonomics->load_checkout_template($ca, $show_order, $crypto);
}else if ($select_crypto) {
    $blockonomics->load_blockonomics_template($ca, 'crypto_options', array(
        "cryptos" => $blockonomics->getActiveCurrencies(),
        "order_hash" => $select_crypto
    ));
}else if ($finish_order) {
    process_finish_order($finish_order, $crypto, $to ,$from,$txn,$status);
    $blockonomics->redirect_finish_order($finish_order);
}else if ($get_order && $crypto) {
    $existing_order = $blockonomics->processOrderHash($get_order, $crypto);
    
            // No order exists, exit
    if (is_null($existing_order->id_order)) {
        exit();
    } else {
    $response = [
     "order_amount" => $blockonomics->fix_displaying_small_values($existing_order->bits, $existing_order->blockonomics_currency),
     "crypto_rate_str" => $blockonomics->get_crypto_rate_from_params($existing_order->value, $existing_order->bits, $existing_order->blockonomics_currency),
     "payment_uri" => $blockonomics->get_payment_uri($blockonomics->getSupportedCurrencies()[$crypto]['uri'], $existing_order->addr, $existing_order->bits)
                 ];
    header('Content-Type: application/json');
    exit(json_encode($response));
    }
}

$ca->assign('_BLOCKLANG', $_BLOCKLANG);

$ca->output();

exit();
