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
$apiKey = $blockonomics->getEtherscanapikey();
$networkType =  $blockonomics->getNetworktype();
$domain = ($networkType === 'Test') ? 'https://api-sepolia.etherscan.io' : 'https://api.etherscan.io';

function logMessage($action, $Request, $Response) {
    $logFile = __DIR__ . '/poll_log.txt';
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);

    if (function_exists('logModuleCall')) {
        logModuleCall(
            "Blockonomics",
            $action,
            $Request,
            $Response,
        );
    }
}


function pollTransactionStatus($txHash) {
    global $apiKey;
    global $domain;

    $url = "$domain/api?module=transaction&action=getstatus&txhash=$txHash&apikey=$apiKey";

    logMessage("Polling ","Request sent to etherscan server started for transaction: $txHash ","Start");
    
    do {
        sleep(10); // Wait for 10 seconds

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);



        if ($data['message'] === "NOTOK") {
            logMessage("Polling","Requested Transaction $txHash failed.",$data['message']);
            break;
        }

        if ($data['status'] === "1") {
            process($txHash);
            logMessage("Polling", " Requested Transaction $txHash to check whether it is completed successfully or not.", "Completed");
            break;
        }
    } while (true);

    logMessage("Polling","Request checks whether transaction ended: $txHash","Ended");
    die();
}

function process($txHash) {
    global $blockonomics;
    global $gatewayParams;
    global $gatewayModuleName;
    global $apiKey;
    global $domain;

    $order = $blockonomics->getOrderByTransaction($txHash);

    if (empty($order)) {
        logMessage("Validating","Order not found for transaction: $txHash","Not Found");
        return;
    }

    $url = "$domain/api?module=proxy&action=eth_getTransactionByHash&txhash=$txHash&apikey=$apiKey";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $result = $data['result'];
    $inputData = $result['input'];

    if (!(substr($inputData, 0, 10) === '0xa9059cbb')) {
        logMessage("Validating","Identify whether the ERC20 transaction ","Transcation not of type is not an ERC20 token transfer.: $txHash");
        return;
    }
    $toAddress =  $blockonomics->getUsdtaddress();
    $txnToAddress = '0x' . substr($inputData, 34, 40);
    $tokenAmountHex = substr($inputData, 74, 64);
    $tokenAmount = hexdec($tokenAmountHex);

    
    if(strtolower($toAddress) != strtolower($txnToAddress)) {
        logMessage("Validating","To check whether the address  match for transaction: $txHash","Transaction didn't match");
        return;
    }
    
    $invoiceId = $order['order_id'];
    $bits = $order['bits'];

    $underpayment_slack = $blockonomics->getUnderpaymentSlack() / 100 * $bits;
    if ($tokenAmount < $bits - $underpayment_slack || $tokenAmount > $bits) {
        $satoshiAmount = $tokenAmount;
    } else {
        $satoshiAmount = $bits;
    }
    $percentPaid = $satoshiAmount / $bits * 100;
    $paymentAmount = $blockonomics->convertPercentPaidToInvoiceCurrency($order, $percentPaid);

    $blockonomics->updateInvoiceNote($invoiceId, null);
    $blockonomics->updateOrderInDb($order['addr'], $txHash, 2, $tokenAmount);

    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    $blockonomics_currency_code = 'usdt';

    $txid = $txHash . " - " . $order['addr'];

    if ($blockonomics->checkIfTransactionExists($blockonomics_currency_code . ' - ' . $txid)) {
        logMessage("Validating","Checking whether transaction already exists  or not in the order: $blockonomics_currency_code  - $txid","Transaction already exsist $txid");
        return;
    }

    $paymentFee = 0;

    addInvoicePayment(
        $invoiceId,
        $blockonomics_currency_code . ' - ' . $txid,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );
}


// Extract transaction hash and API key from command line arguments
$txHash = $argv[1];
pollTransactionStatus($txHash);