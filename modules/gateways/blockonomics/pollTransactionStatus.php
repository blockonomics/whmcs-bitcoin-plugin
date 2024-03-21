<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;

// Init Blockonomics class
$blockonomics = new Blockonomics();

$gatewayModuleName = 'blockonomics';
$gatewayParams = getGatewayVariables($gatewayModuleName);

function logMessage($message) {
    $logFile = __DIR__ . '/poll_log.txt';
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

function pollTransactionStatus($txHash) {
    $apiKey = "5BBCNZFBGWZCE9B3PHPS7KGFJS48HSMWV9";
    $url = "https://api-sepolia.etherscan.io/api?module=transaction&action=getstatus&txhash=$txHash&apikey=$apiKey";

    logMessage("Polling started for transaction: $txHash");

    do {
        sleep(10); // Wait for 10 seconds

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($data['message'] === "NOTOK") {
            break;
        }

        if ($data['status'] === "1") {
            process($txHash);
            logMessage("Transaction $txHash completed successfully.");
            break;
        }
    } while (true);

    logMessage("Polling ended for transaction: $txHash");
    die();
}

// Extract transaction hash and API key from command line arguments
$txHash = $argv[1];
pollTransactionStatus($txHash);

function process($txHash) {
    global $blockonomics;
    global $gatewayParams;
    global $gatewayModuleName;
    $order = $blockonomics->getOrderByTransaction($txHash);

    if (empty($order)) {
        logMessage("Order not found for transaction: $txHash");
        return;
    }

    $invoiceId = $order['order_id'];

    $blockonomics->updateInvoiceNote($invoiceId, null);
    $blockonomics->updateOrderInDb($order['addr'], $txHash, 2, $order['value']);

    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    $blockonomics_currency_code = 'usdt';

    $txid = $txHash . " - " . $order['addr'];

    if ($blockonomics->checkIfTransactionExists($blockonomics_currency_code . ' - ' . $txid)) {
        return;
    }

    $paymentFee = 0;

    addInvoicePayment(
        $invoiceId,
        $blockonomics_currency_code . ' - ' . $txid,
        $order['value'],
        $paymentFee,
        $gatewayModuleName
    );
}