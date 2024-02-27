<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/blockonomics.php';

use Blockonomics\Blockonomics;


// Init Blockonomics class
$blockonomics = new Blockonomics();

function getTransactionReceipt($transactionHash)
{
    $apiUrl = "https://eth-sepolia.g.alchemy.com/v2/iSnijy8E7R8CYTM3Wn6x-jxLE25cAZ_J";
    $postData = [
        "method" => "eth_getTransactionReceipt",
        "params" => [$transactionHash],
        "id" => 1,
        "jsonrpc" => "2.0"
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    $response = curl_exec($ch);
    if ($response === FALSE) {
        die(curl_error($ch));
    }

    curl_close($ch);
    $res = json_decode($response, true);
    return $res['result'];
}

function process_finish_order($finish_order, $crypto, $to, $from, $txn, $status)
{
    global $blockonomics;
    if ($crypto === "usdt") {
        $order = $blockonomics->processOrderHash($finish_order, $crypto);

        $invoiceId = $order->id_order;
        $value = $order->value;
        $paymentFee = 0;
        $gatewayModuleName = 'blockonomics';
        $gatewayParams = getGatewayVariables($gatewayModuleName);

        $receipt = getTransactionReceipt($txn);
        $toAddress = $receipt['to'];
        $fromAddress = $receipt['from'];

        if (strtolower($toAddress) == strtolower($to) &&  strtolower($fromAddress) == strtolower($from)) {
            $new_address = $crypto . '-' . $invoiceId;
            $paymentAmount = get_payment_amount($order);
            $blockonomics->updateOrderInDb($new_address, $txn, $status, $value);
            $blockonomics->updateInvoiceNote($invoiceId, null);

            $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

            $txid = $txn . " - " . $from;

            if (!$blockonomics->checkIfTransactionExists($crypto . ' - ' . $txid)) {
                addInvoicePayment(
                    $invoiceId,
                    $crypto . ' - ' . $txid,
                    $paymentAmount,
                    $paymentFee,
                    $gatewayModuleName
                );
            }
        }
    }
}
function get_payment_amount($order)
{
    global $blockonomics;
    $underpayment_slack = $blockonomics->getUnderpaymentSlack() / 100 * $order->bits;
    if ($order->value < $order->bits - $underpayment_slack || $order->value > $order->bits) {
        $satoshiAmount = $order->value;
    } else {
        $satoshiAmount = $order->bits;
    }
    $percentPaid = $satoshiAmount / $order->bits * 100;
    $paymentAmount = $blockonomics->convertPercentPaidToInvoiceCurrency((array) $order, $percentPaid);
    return $paymentAmount;
}
