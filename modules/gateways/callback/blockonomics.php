<?php
// Require libraries needed for gateway module functions.
require '../../../init.php';
require '../../../includes/gatewayfunctions.php';
require '../../../includes/invoicefunctions.php';
require '../blockonomics/blockonomics.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Blockonomics\Blockonomics;

// Init Blockonomics class
$blockonomics = new Blockonomics();

$gatewayModuleName = 'blockonomics';

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    exit('Module Not Activated');
}

require_once $blockonomics->getLangFilePath();

// Retrieve data returned in payment gateway callback
$secret = htmlspecialchars($_GET['secret']);
$status = htmlspecialchars($_GET['status']);
$addr = htmlspecialchars($_GET['addr']);
$value = htmlspecialchars($_GET['value']);
$txid = htmlspecialchars($_GET['txid']);
$rbf = !empty($_GET['rbf']);

/**
 * Validate callback authenticity.
 */
$secret_value = $blockonomics->getCallbackSecret();

if (empty($secret_value) || !hash_equals($secret_value, $secret)) {
    logModuleCall('blockonomics', 'callback_secret_mismatch',
        ['addr' => $addr, 'txid' => $txid, 'status' => $status],
        'Secret mismatch - callback rejected');

    echo $_BLOCKLANG['error']['secret'];
    exit();
}

$order = $blockonomics->getOrderByAddress($addr);
if (!$order || !$order['order_id']) {
    $order = $blockonomics->getOrderBytxn($txid);
}

if (!$order || !$order['order_id']) {
    logModuleCall('blockonomics', 'callback_no_matching_order',
        ['addr' => $addr, 'txid' => $txid, 'status' => $status, 'value' => $value],
        'No order found for callback');
    exit();
}

if ($rbf && $status < 2) {
    logModuleCall('blockonomics', 'callback_rbf_unconfirmed',
        ['addr' => $addr, 'txid' => $txid, 'status' => $status, 'value' => $value, 'rbf' => $rbf],
        'RBF callback ignored below confirmed status');
    exit();
}

if ($order['status'] == 2) {
    logModuleCall('blockonomics', 'callback_order_already_confirmed',
        ['addr' => $addr, 'txid' => $txid, 'status' => $status, 'value' => $value, 'order_id' => $order['order_id'], 'order_status' => $order['status']],
        'Order already confirmed, callback ignored');
    exit();
}

$invoiceId = $order['order_id'];
$bits = $order['bits'];

$confirmations = $blockonomics->getConfirmations();



$blockonomics_currency_code = $order['blockonomics_currency'];
$blockonomics_currency = $blockonomics->getSupportedCurrencies()[$blockonomics_currency_code];
if ($blockonomics_currency_code == 'btc' || $blockonomics_currency_code == 'usdt') {
    $subdomain = 'www';
} else {
    $subdomain = $blockonomics_currency_code;
}

if ($status < $confirmations) {
    if ($blockonomics_currency_code == 'usdt') {
        $invoiceNote = '<b>' . $_BLOCKLANG['invoiceNote']['waiting'] . "</b>\r\r" .
        $blockonomics_currency['name'] . " transaction id:\r" .
            '<a target="_blank" href="https://www.etherscan.io/tx/' . $txid . '">' . $txid . '</a>';
    } else {
        $invoiceNote = '<b>' . $_BLOCKLANG['invoiceNote']['waiting'] . "</b>\r\r" .
        $blockonomics_currency['name'] . " transaction id:\r" .
            '<a target="_blank" href="https://' . $subdomain . ".blockonomics.co/api/tx?txid=$txid&addr=$addr\">$txid</a>";
    }
    $blockonomics->updateOrderInDb($order['addr'], $txid, $status, $value);
    $blockonomics->updateInvoiceNote($invoiceId, $invoiceNote);
    if ($status >= 0) {
        $blockonomics->markInvoicePending($invoiceId);
    }
    logModuleCall('blockonomics', 'callback_payment_update',
        ['addr' => $addr, 'txid' => $txid, 'status' => $status, 'value' => $value, 'order_id' => $invoiceId],
        'Order updated - awaiting confirmations');

    exit();
}

$underpayment_slack = ceil($blockonomics->getUnderpaymentSlack() / 100 * $bits);
if ($value < $bits - $underpayment_slack || $value > $bits) {
    $satoshiAmount = $value;
} else {
    $satoshiAmount = $bits;
}
$percentPaid = $satoshiAmount / $bits * 100;
$paymentAmount = $blockonomics->convertPercentPaidToInvoiceCurrency($order, $percentPaid);

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a exit upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

$callback_txid = $txid;

if ($txid == 'WarningThisIsAGeneratedTestPaymentAndNotARealBitcoinTransaction') {
    // If this is test transaction, generate new transaction ID
    $txid = 'WarningThisIsATestTransaction - ' . $addr;
} else {
    /**
     * Add address to txid, this is because multiple addresses may have 
     * same transaction ids (due to how bitcoin operates, see ref), which 
     * causes invoices with such cases to be skipped due to which they are 
     * not marked as paid in WHMCS.
     * Ref: https://bitcoin.stackexchange.com/a/43136
     * Ref: https://github.com/blockonomics/whmcs-bitcoin-plugin/issues/79
     * 
     * Adding address to the txid makes the transaction id unique in 
     * WHMCS which solves the above issue.
     * 
     */ 
    $txid = $txid . " - " . $addr;
}

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a exit upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */

if ($blockonomics->checkIfTransactionExists($blockonomics_currency_code . ' - ' . $txid)) {
    $blockonomics->updateOrderInDb($order['addr'], $callback_txid, 2, $value);
    $blockonomics->releaseInvoicePendingIfNoPendingCryptoOrders($invoiceId, $confirmations);
    $blockonomics->updateInvoiceNote($invoiceId, null);
    exit();
}

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], array_merge($_GET, ['secret' => '***']), 'Successful');

$paymentFee = 0;

/**
 * Add Invoice Payment.
 *
 * Applies a payment transaction entry to the given invoice ID.
 *
 * @param int $invoiceId         Invoice ID
 * @param string $transactionId  Transaction ID
 * @param float $paymentAmount   Amount paid (defaults to full balance)
 * @param float $paymentFee      Payment fee (optional)
 * @param string $gatewayModule  Gateway module name
 */
addInvoicePayment(
    $invoiceId,
    $blockonomics_currency_code . ' - ' . $txid,
    $paymentAmount,
    $paymentFee,
    $gatewayModuleName
);

$blockonomics->updateOrderInDb($order['addr'], $callback_txid, 2, $value);
$blockonomics->releaseInvoicePendingIfNoPendingCryptoOrders($invoiceId, $confirmations);
$blockonomics->updateInvoiceNote($invoiceId, null);
