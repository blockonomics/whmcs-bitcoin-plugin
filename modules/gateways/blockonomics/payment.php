<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/blockonomics.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Blockonomics\Blockonomics;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

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
$get_order = isset($_GET['get_order']) ? htmlspecialchars($_GET['get_order']) : "";
$txhash = isset($_GET['txhash']) ? htmlspecialchars($_GET['txhash']) : "";

function blockonomics_get_non_payable_invoice($blockonomics, $order_hash)
{
    if (empty($order_hash)) {
        return null;
    }

    $order_info = $blockonomics->decryptHash($order_hash);
    if (!is_object($order_info) || empty($order_info->id_order)) {
        return null;
    }

    $invoice = Capsule::table('tblinvoices')
        ->select('id', 'status')
        ->where('id', $order_info->id_order)
        ->first();

    if (!$invoice || !in_array($invoice->status, ['Paid', 'Cancelled', 'Refunded'])) {
        return null;
    }

    return $invoice;
}

$order_hash = $show_order ?: $select_crypto ?: $finish_order ?: $get_order;
$non_payable_invoice = blockonomics_get_non_payable_invoice($blockonomics, $order_hash);

if ($non_payable_invoice && $get_order) {
    http_response_code(409);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'invoice_not_payable']));
} else if ($non_payable_invoice && $finish_order) {
    header('Location: ' . \App::getSystemURL() . 'viewinvoice.php?id=' . $non_payable_invoice->id);
    exit();
} else if ($non_payable_invoice) {
    $blockonomics->load_blockonomics_template($ca, 'invoice_not_payable', [
        'invoice_id' => $non_payable_invoice->id,
        'invoice_status' => $non_payable_invoice->status,
        'invoice_url' => \App::getSystemURL() . 'viewinvoice.php?id=' . $non_payable_invoice->id,
    ]);
} else if($crypto === "empty"){
    $blockonomics->load_blockonomics_template($ca, 'no_crypto_selected');
}else if ($show_order && $crypto) {
    $blockonomics->load_checkout_template($ca, $show_order, $crypto);
}else if ($select_crypto) {
    $blockonomics->load_blockonomics_template($ca, 'crypto_options', array(
        "cryptos" => $blockonomics->getCheckoutCurrencies(),
        "order_hash" => $select_crypto
    ));
}else if ($finish_order) {
    if ($crypto == "usdt") {
        if (!$blockonomics->process_token_order($finish_order, $crypto, $txhash)) {
            exit($_BLOCKLANG['error']['paymentFailed']);
        }
    }
    $blockonomics->redirect_finish_order($finish_order);
}else if ($get_order && $crypto) {
    $existing_order = $blockonomics->processOrderHash($get_order, $crypto);
    
    // No order exists, exit
    if (is_null($existing_order->id_order)) {
        exit();
    } else {
        $order_amount = $blockonomics->fix_displaying_small_values($existing_order->bits, $existing_order->blockonomics_currency);
        $response = [
            "order_amount" => $order_amount,
            "crypto_rate_str" => $blockonomics->get_crypto_rate_from_params($existing_order->value, $existing_order->bits, $existing_order->blockonomics_currency),
            "payment_uri" => $blockonomics->get_payment_uri($blockonomics->getSupportedCurrencies()[$crypto]['uri'], $existing_order->addr, $order_amount)
        ];
        header('Content-Type: application/json');
        exit(json_encode($response));
    }
}

$ca->assign('_BLOCKLANG', $_BLOCKLANG);

$ca->output();

exit();
