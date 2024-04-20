<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;

// Initialization of the Blockonomics class
$blockonomics = new Blockonomics();

// Fetch module and API configuration
$gatewayModuleName = 'blockonomics';
$gatewayParams = getGatewayVariables($gatewayModuleName);
$apiKey = $blockonomics->getEtherscanApiKey();
$networkType =  $blockonomics->getNetworkType();
$subDomain = ($networkType === 'Test') ? 'api-sepolia' : 'api';
$domain = 'https://'. $subDomain .'.etherscan.io';

// Log messages for debugging
function logMessage($action, $request, $response) {
    if (function_exists('logModuleCall')) {
        logModuleCall( "Blockonomics", $action, $request, $response);
    }
}

// Function to handle curl requests
function performCurlRequest($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Main function to poll transaction status
function pollTransactionStatus($txHash) {
    global $apiKey, $domain;

    $url = "$domain/api?module=transaction&action=getstatus&txhash=$txHash&apikey=$apiKey";

    logMessage("Polling ","Request sent to etherscan server started for transaction: $txHash ","Start");
    
    do {
        sleep(10); // Wait for 10 seconds
        $response = performCurlRequest($url);
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

    $order = $blockonomics->getOrderByTransaction($txHash);

    if (empty($order)) {
        logMessage("Validating","Order not found for transaction: $txHash","Not Found");
        return;
    }

    $result = fetchTransactionData($txHash);
    $inputData = $result['input'];

    if (!(substr($inputData, 0, 10) === '0xa9059cbb')) {
        logMessage("Validating","Identify whether the ERC20 transaction ","Transcation not of type is not an ERC20 token transfer.: $txHash");
        return;
    }

    if (!isValidTransaction($inputData)) {
        logMessage("Validating","To check whether the address  match for transaction: $txHash","Transaction didn't match");
        return;
    }

    $tokenAmountHex = substr($inputData, 74, 64);
    $tokenAmount = hexdec($tokenAmountHex);

    $blockonomics->updateInvoiceNote($order['order_id'], null);
    $blockonomics->updateOrderInDb($order['addr'], $txHash, 2, $tokenAmount);
    
    $blockonomics_currency_code = 'usdt';
    $txid = $txHash . " - " . $order['addr'];

    if ($blockonomics->checkIfTransactionExists($blockonomics_currency_code . ' - ' . $txid)) {
        logMessage("Validating","Checking whether transaction already exists  or not in the order: $blockonomics_currency_code  - $txid","Transaction already exsist $txid");
        return;
    }

    $paymentAmount = getPaymentAmount($order['bits'], $tokenAmount, $order);
    $paymentFee = 0;
    $invoiceId = checkCbInvoiceID($order['order_id'], $gatewayParams['name']);

    addInvoicePayment(
        $invoiceId,
        $blockonomics_currency_code . ' - ' . $txid,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );
}

// Fetch transaction data from etherscan
function fetchTransactionData($txHash) {
    global $apiKey, $domain;
    $url = "$domain/api?module=proxy&action=eth_getTransactionByHash&txhash=$txHash&apikey=$apiKey";
    $response = performCurlRequest($url);
    $data = json_decode($response, true);
    return $data['result'];
}

// Validate transaction details
function isValidTransaction($inputData) {
    global $blockonomics;
    $toAddress = $blockonomics->getUSDTAddress();
    $txnToAddress = '0x' . substr($inputData, 34, 40);
    return strtolower($toAddress) === strtolower($txnToAddress);
}

function getPaymentAmount($bits, $tokenAmount, $order) {
    global $blockonomics;

    $underpayment_slack = $blockonomics->getUnderpaymentSlack() / 100 * $bits;
    if ($tokenAmount < $bits - $underpayment_slack || $tokenAmount > $bits) {
        $satoshiAmount = $tokenAmount;
    } else {
        $satoshiAmount = $bits;
    }
    $percentPaid = $satoshiAmount / $bits * 100;
    $paymentAmount = $blockonomics->convertPercentPaidToInvoiceCurrency($order, $percentPaid);

    return $paymentAmount;
}

// Extract transaction hash and API key from command line arguments
$txHash = $argv[1];
pollTransactionStatus($txHash);