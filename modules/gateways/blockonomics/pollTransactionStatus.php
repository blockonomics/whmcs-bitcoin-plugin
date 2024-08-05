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
$networkType =  $blockonomics->getTokenNetwork();
$subDomain = ($networkType === 'sepolia') ? 'api-sepolia' : 'api';
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
function pollTransactionStatus($order) {
    global $apiKey, $domain;
    
    $txHash = $order->txid;
    $url = "$domain/api?module=transaction&action=getstatus&txhash=$txHash&apikey=$apiKey";

    logMessage("Polling ","Request sent to etherscan server started for transaction: $txHash ","Start");
    
    $response = performCurlRequest($url);
    $data = json_decode($response, true);

    if ($data['message'] === "NOTOK" || $data['status'] === "0") {
        logMessage("Polling","Requested Transaction $txHash failed.",$data['message']);
        return;
    }

    if ($data['status'] === "1") {
        process($order);
        logMessage("Polling", " Requested Transaction $txHash to check whether it is completed successfully or not.", "Completed");
        return;
    }

    logMessage("Polling","Request checks whether transaction ended: $txHash","Ended");
}

function process($order) {
    global $blockonomics;
    global $gatewayParams;
    global $gatewayModuleName;
    
    $txHash = $order->txid;

    $result = fetchTransactionData($txHash);
    $inputData = $result['input'];
    $toAddress = $result['to'];
    $selectedNetwork = $blockonomics->getTokenNetwork();
    $selectedTokenNetworks = $blockonomics->getTokenNetworkDetails($selectedNetwork);
    $contract_address = $selectedTokenNetworks["tokens"]["usdt"];

    if (!(substr($inputData, 0, 10) === '0xa9059cbb')) {
        logMessage("Validating","Identify whether the ERC20 transaction ","Transcation not of type is not an ERC20 token transfer.: $txHash");
        return;
    }
    
    if(strtolower($toAddress) != strtolower($contract_address)){
        logMessage("Validating","Check contract address","Transaction $txHash 'to' address does not match the expected token contract address: $contract_address");
        return;
    } 

    if (!isValidTransaction($inputData)) {
        logMessage("Validating","To check whether the address  match for transaction: $txHash","Transaction didn't match");
        return;
    }

    $tokenAmountHex = substr($inputData, 74, 64);
    $tokenAmount = hexdec($tokenAmountHex);

    $blockonomics->updateInvoiceNote($order->id_order, null);
    $blockonomics->updateOrderInDb($order->addr, $txHash, 2, $tokenAmount);
    
    $blockonomics_currency_code = 'usdt';
    $txid = $txHash . " - " . $order->addr;

    if ($blockonomics->checkIfTransactionExists($blockonomics_currency_code . ' - ' . $txid)) {
        logMessage("Validating","Checking whether transaction already exists  or not in the order: $blockonomics_currency_code  - $txid","Transaction already exsist $txid");
        return;
    }
    
    logMessage("Sucessful", "The transaction has been successful $blockonomics_currency_code  - $txid", "Transaction $txid");

    $paymentAmount = getPaymentAmount($order->bits, $tokenAmount, $order);
    $paymentFee = 0;
    $invoiceId = checkCbInvoiceID($order->id_order, $gatewayParams['name']);

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
    $paymentAmount = $blockonomics->convertPercentPaidToInvoiceCurrency((array)$order, $percentPaid);

    return $paymentAmount;
}


do {
    // Get the server's max execution time
    $max_execution_time = ini_get('max_execution_time');
    
    // If max_execution_time is 0 (unlimited) or greater than 30, use 20 seconds
    // Otherwise, use half of the max_execution_time, but not less than 10 seconds
    $sleep_time = ($max_execution_time == 0 || $max_execution_time > 30) ? 20 : max(10, $max_execution_time / 2);
    
    sleep($sleep_time);
    
    // Log the sleep time for debugging
    logMessage("Sleep Time", "Server max execution time: $max_execution_time", "Actual sleep time: $sleep_time");
    $unconfirmedOrders = $blockonomics->getUnconfirmedUSDTOrders();
    
    if (!$unconfirmedOrders->isEmpty()) {
        foreach ($unconfirmedOrders as $order) {
            pollTransactionStatus($order);
            // Use a shorter sleep time between processing each order
            $order_sleep_time = min(1, $sleep_time / 10);
            sleep($order_sleep_time);
        }
    }
} while (true);