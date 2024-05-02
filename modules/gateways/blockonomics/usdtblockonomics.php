<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;


// Init Blockonomics class
$blockonomics = new Blockonomics();
$systemUrl = \App::getSystemURL();

function process_finish_order($finish_order, $crypto, $txid)
{
    if ($crypto !== "usdt") {
        return;
    }

    global $blockonomics;
    require_once $blockonomics->getLangFilePath();

    $order = $blockonomics->processOrderHash($finish_order, $crypto);

    if (empty($order)) {
        return;
    }

    $transactionExists = $blockonomics->blockonomicsTransactionExists($txid);

    if ($transactionExists) {
        return;
    }

    $invoiceId = $order->id_order;
    $new_address = $crypto . '-' . $invoiceId;
    
    $subdomain = $blockonomics->getNetworkType() === "sepolia" ? "sepolia" : "www";

    $blockonomics_currency = $blockonomics->getSupportedCurrencies()[$crypto];

    $invoiceNote = '<b>' . $_BLOCKLANG['invoiceNote']['waiting'] . ' <img src="' . $systemUrl . 'modules/gateways/blockonomics/assets/img/usdt.png" style="max-width: 20px;"> ' . $blockonomics_currency->name . ' ' . $_BLOCKLANG['invoiceNote']['network'] . "</b>\r\r" .
    $blockonomics_currency->name . " transaction id:\r" .
        '<a target="_blank" href="https://' . $subdomain . ".etherscan.io/tx/$txid\">$txid</a>";

    $blockonomics->updateOrderInDb($new_address, $txid, 0, 0);
    $blockonomics->updateInvoiceNote($invoiceId, $invoiceNote);
}
