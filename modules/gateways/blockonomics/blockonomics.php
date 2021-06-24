<?php

namespace Blockonomics;

use Exception;
use stdClass;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../includes/gatewayfunctions.php';

class Blockonomics
{
    private $version = '1.9.0';

    /*
     * Get the blockonomics version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /*
     * Get callback secret and SystemURL to form the callback URL
     */
    public function getCallbackUrl($secret)
    {
        return \App::getSystemURL() . 'modules/gateways/callback/blockonomics.php?secret=' . $secret;
    }

    /*
     * Try to get callback secret from db
     * If no secret exists, create new
     */
    public function getCallbackSecret()
    {
        $secret = '';

        try {
            $gatewayParams = getGatewayVariables('blockonomics');
            $secret = $gatewayParams['CallbackSecret'];
        } catch (Exception $e) {
            exit("Error, could not get Blockonomics secret from database. {$e->getMessage()}");
        }

        // Check if old format of callback is still in use
        if ($secret == '') {
            try {
                $gatewayParams = getGatewayVariables('blockonomics');
                $secret = $gatewayParams['ApiSecret'];
            } catch (Exception $e) {
                exit("Error, could not get Blockonomics secret from database. {$e->getMessage()}");
            }
            // Get only the secret from the whole Callback URL
            $secret = substr($secret, -40);
        }

        if ($secret == '') {
            $secret = $this->generateCallbackSecret();
        }

        return $secret;
    }

    /*
     * Generate new callback secret using sha1, save it in db under tblpaymentgateways table
     */
    private function generateCallbackSecret()
    {
        try {
            $callback_secret = sha1(openssl_random_pseudo_bytes(20));
        } catch (Exception $e) {
            exit("Error, could not generate callback secret. {$e->getMessage()}");
        }

        return $callback_secret;
    }

    /*
     * Get user configured API key from database
     */
    public function getApiKey()
    {
        $gatewayParams = getGatewayVariables('blockonomics');
        return $gatewayParams['ApiKey'];
    }

    /*
     * Get list of crypto currencies supported by Blockonomics
     */
    public function getSupportedCurrencies()
    {
        return array(
              'btc' => array(
                    'code' => 'btc',
                    'name' => 'Bitcoin',
                    'uri' => 'bitcoin'
              ),
              'bch' => array(
                    'code' => 'bch',
                    'name' => 'Bitcoin Cash',
                    'uri' => 'bitcoincash'
              )
          );
    }

    /*
     * Get list of active crypto currencies
     */
    public function getActiveCurrencies()
    {
        $active_currencies = [];
        $blockonomics_currencies = $this->getSupportedCurrencies();
        foreach ($blockonomics_currencies as $code => $currency) {
            if ($code == 'btc') {
                $enabled = true;
            } else {
                $gatewayParams = getGatewayVariables('blockonomics');
                $enabled = $gatewayParams[$code . 'Enabled'];
            }
            if ($enabled) {
                $active_currencies[$code] = $currency;
            }
        }
        return $active_currencies;
    }

    /*
     * Get user configured Time Period from database
     */
    public function getTimePeriod()
    {
        $gatewayParams = getGatewayVariables('blockonomics');
        return $gatewayParams['TimePeriod'];
    }

    /*
     * Get user configured Confirmations from database
     */
    public function getConfirmations()
    {
        $gatewayParams = getGatewayVariables('blockonomics');
        $confirmations = $gatewayParams['Confirmations'];
        if (isset($confirmations)) {
            return $confirmations;
        }
        return 2;
    }

    /*
     * Update invoice note
     */
    public function updateInvoiceNote($invoiceid, $note)
    {
        Capsule::table('tblinvoices')
            ->where('id', $invoiceid)
            ->update(['notes' => $note]);
    }

    /*
     * Get underpayment slack
     */
    public function getUnderpaymentSlack()
    {
        $gatewayParams = getGatewayVariables('blockonomics');
        return $gatewayParams['Slack'];
    }

    /*
     * See if given txid is applied to any invoice
     */
    public function checkIfTransactionExists($txid)
    {
        $transaction = Capsule::table('tblaccounts')
            ->where('gateway', 'blockonomics')
            ->where('transid', $txid)
            ->value('id');

        return isset($transaction);
    }

    /*
     * Get new address from Blockonomics Api
     */
    public function getNewAddress($currency = 'btc', $reset = false)
    {
        if ($currency == 'btc') {
            $subdomain = 'www';
        } else {
            $subdomain = $currency;
        }

        $api_key = $this->getApiKey();
        $callback_secret = $this->getCallbackSecret();

        if ($reset) {
            $get_params = "?match_callback=$callback_secret&reset=1";
        } else {
            $get_params = "?match_callback=$callback_secret";
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://' . $subdomain . '.blockonomics.co/api/new_address' . $get_params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        $header = 'Authorization: Bearer ' . $api_key;
        $headers = [];
        $headers[] = $header;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            exit('Error:' . curl_error($ch));
        }

        $responseObj = json_decode($contents);
        //Create response object if it does not exist
        if (!isset($responseObj)) {
            $responseObj = new stdClass();
        }
        $responseObj->{'response_code'} = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $responseObj;
    }

    /*
     * Get user configured margin from database
     */
    public function getMargin()
    {
        $gatewayParams = getGatewayVariables('blockonomics');
        return $gatewayParams['Margin'];
    }

    /*
     * Convert fiat amount to Blockonomics currency
     */
    public function convertFiatToBlockonomicsCurrency($fiat_amount, $currency, $blockonomics_currency = 'btc')
    {
        try {
            if ($blockonomics_currency == 'btc') {
                $subdomain = 'www';
            } else {
                $subdomain = $blockonomics_currency;
            }

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://' . $subdomain . '.blockonomics.co/api/price?currency=' . $currency);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $contents = curl_exec($ch);
            if (curl_errno($ch)) {
                exit('Error:' . curl_error($ch));
            }
            curl_close($ch);
            $price = json_decode($contents)->price;
            $margin = floatval($this->getMargin());
            if ($margin > 0) {
                $price = $price * 100 / (100 + $margin);
            }
        } catch (Exception $e) {
            exit("Error getting price from Blockonomics! {$e->getMessage()}");
        }

        return intval(1.0e8 * $fiat_amount / $price);
    }

    /**
     * Convert received btc percentage to correct invoice currency
     * Uses percent paid to ensure no rounding issues during conversions
     *
     * @param array $order
     * @param string $percentPaid
     * @return float converted value
     */
    public function convertPercentPaidToInvoiceCurrency($order, $percentPaid)
    {
        // Check if the invoice was converted during checkout
        if (floatval($order['basecurrencyamount']) > 0) {
            $order_total = $order['basecurrencyamount'];
        }else {
            $order_total = $order['value'];
        }
        $paymentAmount = $percentPaid / 100 * $order_total;
        return round(floatval($paymentAmount), 2);
    }

    /*
     * If no Blockonomics order table exists, create it
     */
    public function createOrderTableIfNotExist()
    {
        if (!Capsule::schema()->hasTable('blockonomics_orders')) {
            try {
                Capsule::schema()->create(
                    'blockonomics_orders',
                    function ($table) {
                        $table->integer('id_order');
                        $table->text('txid');
                        $table->integer('timestamp');
                        $table->string('addr');
                        $table->integer('status');
                        $table->decimal('value', 10, 2);
                        $table->integer('bits');
                        $table->integer('bits_payed');
                        $table->string('blockonomics_currency');
                        $table->primary('addr');
                        $table->decimal('basecurrencyamount', 10, 2);
                        $table->index(['id_order', 'blockonomics_currency']);
                    }
                );
            } catch (Exception $e) {
                exit("Unable to create blockonomics_orders: {$e->getMessage()}");
            }
        } else if (!Capsule::schema()->hasColumn('blockonomics_orders', 'basecurrencyamount')) {
            try {
                // basecurrencyamount fixes payment amounts when convertToForProcessing is activated
                // https://github.com/blockonomics/whmcs-bitcoin-plugin/pull/103
                Capsule::schema()->table('blockonomics_orders', function ($table) {
                    $table->decimal('basecurrencyamount', 10, 2);
                });
            } catch (Exception $e) {
                exit("Unable to update blockonomics_orders: {$e->getMessage()}");
            }
        }
    }

    /**
     * Decrypts a string using the application secret.
     *
     * @param  $hash
     * @return object
     */
    public function decryptHash($hash)
    {
        $encryption_algorithm = 'AES-128-CBC';
        $hashing_algorith = 'sha256';
        $secret = $this->getCallbackSecret();
        // prevent decrypt failing when $hash is not hex or has odd length
        if (strlen($hash) % 2 || !ctype_xdigit($hash)) {
            return '';
        }

        // we'll need the binary cipher
        $binaryInput = hex2bin($hash);
        $iv = substr($secret, 0, 16);
        $cipherText = $binaryInput;
        $key = hash($hashing_algorith, $secret, true);

        $decrypted = openssl_decrypt(
            $cipherText,
            $encryption_algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        $parts = explode(':', $decrypted);
        $order_info = new stdClass();
        $order_info->id_order = intval($parts[0]);
        $order_info->value = floatval($parts[1]);
        $order_info->currency = $parts[2];
        $order_info->basecurrencyamount = floatval($parts[3]);
        return $order_info;
    }

    /**
     * Encrypts a string using the application secret. This returns a hex representation of the binary cipher text
     *
     * @param  $input
     * @return string
     */
    public function encryptHash($input)
    {
        $encryption_algorithm = 'AES-128-CBC';
        $hashing_algorith = 'sha256';
        $secret = $this->getCallbackSecret();
        $key = hash($hashing_algorith, $secret, true);
        $iv = substr($secret, 0, 16);

        $cipherText = openssl_encrypt(
            $input,
            $encryption_algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return bin2hex($cipherText);
    }

    /*
     * Add a new skeleton order in the db
     */
    public function getOrderHash($id_order, $amount, $currency, $basecurrencyamount)
    {
        return $this->encryptHash($id_order . ':' . $amount . ':' . $currency. ':' . $basecurrencyamount);
    }

    /*
     * Get all orders linked to id
     */
    public function getAllOrdersById($order_id)
    {
        try {
            return Capsule::table('blockonomics_orders')
                ->where('id_order', $order_id)
                ->orderBy('timestamp', 'desc')->get();
        } catch (Exception $e) {
            exit("Unable to get orders from blockonomics_orders: {$e->getMessage()}");
        }
    }

    /*
     * Check for pending orders and return if exists
     */
    public function getPendingOrder($orders)
    {
        $network_confirmations = $this->getConfirmations();
        foreach ($orders as $order) {
            //check if status 0 or 1
            if ($order->status > -1 && $order->status < $network_confirmations) {
                return $order;
            }
        }
        return false;
    }

    /*
     * Fetch unused order for the blockonomics_currency and update order values
     */
    public function getAndUpdateWaitingOrder($orders, $supplied_info, $blockonomics_currency)
    {
        foreach ($orders as $order) {
            //check for currency address already waiting
            if ($order->blockonomics_currency == $blockonomics_currency && $order->status == -1) {
                $order->value = $supplied_info->value;
                $order->currency = $supplied_info->currency;
                $order->bits = $this->convertFiatToBlockonomicsCurrency($order->value, $order->currency, $blockonomics_currency);
                $order->timestamp = time();
                $order->time_remaining = $this->getTimePeriod()*60;
                $this->updateOrderExpected($order->addr, $order->blockonomics_currency, $order->timestamp, $order->value, $order->bits);
                return $order;
            }
        }
        return false;
    }

    /*
     * Try to insert new order to database
     * If order exists, return with false
     */
    public function insertOrderToDb($id_order, $blockonomics_currency, $address, $value, $bits, $basecurrencyamount)
    {
        try {
            Capsule::table('blockonomics_orders')->insert(
                [
                    'id_order' => $id_order,
                    'blockonomics_currency' => $blockonomics_currency,
                    'addr' => $address,
                    'timestamp' => time(),
                    'status' => -1,
                    'value' => $value,
                    'bits' => $bits,
                    'basecurrencyamount' => $basecurrencyamount,
                ]
            );
        } catch (Exception $e) {
            exit("Unable to insert new order into blockonomics_orders: {$e->getMessage()}");
        }
        return true;
    }

    /*
     * Check for unused address or create new
     */
    public function createNewCryptoOrder($order, $blockonomics_currency)
    {
        $new_addresss_response = $this->getNewAddress($blockonomics_currency);
        if ($new_addresss_response->response_code == 200) {
            $order->addr = $new_addresss_response->address;
        } else {
            exit($new_addresss_response->message);
        }

        $order->blockonomics_currency = $blockonomics_currency;
        $order->bits = $this->convertFiatToBlockonomicsCurrency($order->value, $order->currency, $order->blockonomics_currency);
        $order->timestamp = time();
        $order->status = -1;
        $order->time_remaining = $this->getTimePeriod()*60;
        $this->insertOrderToDb($order->id_order, $order->blockonomics_currency, $order->addr, $order->value, $order->bits, $order->basecurrencyamount);
        return $order;
    }

    /*
     * Find an existing order or create a new order
     */
    public function processOrderHash($order_hash, $blockonomics_currency)
    {
        $order_info = $this->decryptHash($order_hash);
        // Fetch all orders by id
        $orders = $this->getAllOrdersById($order_info->id_order);
        if ($orders) {
            // Check for pending payments and return the order
            $pending_payment = $this->getPendingOrder($orders);
            if ($pending_payment) {
                return $pending_payment;
            }
            // Check for existing address
            $address_waiting = $this->getAndUpdateWaitingOrder($orders, $order_info, $blockonomics_currency);
            if ($address_waiting) {
                return $address_waiting;
            }
        }
        // Process a new order for the id and blockonomics currency
        $new_order = $this->createNewCryptoOrder($order_info, $blockonomics_currency);
        if ($new_order) {
            return $new_order;
        }
        return false;
    }

    /*
     * Try to get order row from db by address
     */
    public function getOrderByAddress($bitcoinAddress)
    {
        try {
            $existing_order = Capsule::table('blockonomics_orders')
                ->where('addr', $bitcoinAddress)
                ->first();
        } catch (Exception $e) {
            exit("Unable to select order from blockonomics_orders: {$e->getMessage()}");
        }

        return [
            'order_id' => $existing_order->id_order,
            'timestamp' => $existing_order->timestamp,
            'status' => $existing_order->status,
            'value' => $existing_order->value,
            'bits' => $existing_order->bits,
            'bits_payed' => $existing_order->bits_payed,
            'blockonomics_currency' => $existing_order->blockonomics_currency,
            'txid' => $existing_order->txid,
            'basecurrencyamount' => $existing_order->basecurrencyamount,
        ];
    }

    /*
     * Get the order id using the order hash
     */
    public function getOrderIdByHash($order_hash)
    {
        $order_info = $this->decryptHash($order_hash);
        return $order_info->id_order;
    }

    /*
     * Update existing order information. Use BTC payment address as key
     */
    public function updateOrderInDb($addr, $txid, $status, $bits_payed)
    {
        try {
            Capsule::table('blockonomics_orders')
                ->where('addr', $addr)
                ->update(
                    [
                        'txid' => $txid,
                        'status' => $status,
                        'bits_payed' => $bits_payed,
                    ]
                );
        } catch (Exception $e) {
            exit("Unable to update order to blockonomics_orders: {$e->getMessage()}");
        }
    }

    /*
     * Update existing order's expected amount and FIAT amount. Use WHMCS invoice id as key
     */
    public function updateOrderExpected($address, $blockonomics_currency, $timestamp, $value, $bits)
    {
        try {
            Capsule::table('blockonomics_orders')
                ->where('addr', $address)
                ->update(
                    [
                        'blockonomics_currency' => $blockonomics_currency,
                        'value' => $value,
                        'bits' => $bits,
                        'timestamp' => $timestamp,
                    ]
                );
        } catch (Exception $e) {
            exit("Unable to update order to blockonomics_orders: {$e->getMessage()}");
        }
    }

    /*
     * Make a request using curl
     */
    public function doCurlCall($url, $post_content = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($post_content) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_content);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-type: application/x-www-form-urlencoded',
            ]
        );
        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseObj = new stdClass();
        $responseObj->data = json_decode($data);
        $responseObj->response_code = $httpcode;
        return $responseObj;
    }

    public function getLangFilePath($language = false)
    {
        if ($language && file_exists(dirname(__FILE__) . '/lang/' . $language . '.php')) {
            $langfilepath = dirname(__FILE__) . '/lang/' . $language . '.php';
        } else {
            global $CONFIG;
            $language = isset($CONFIG['Language']) ? $CONFIG['Language'] : '';
            $langfilepath = dirname(__FILE__) . '/lang/' . $language . '.php';
            if (!file_exists($langfilepath)) {
                $langfilepath = dirname(__FILE__) . '/lang/english.php';
            }
        }
        return $langfilepath;
    }

    /**
     * Run the test setup
     *
     * @param string $new_api
     * @return string error message
     */
    public function testSetup($new_api)
    {
        include_once $this->getLangFilePath();

        $xpub_fetch_url = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
        $set_callback_url = 'https://www.blockonomics.co/api/update_callback';
        $error_str = '';

        $response = $this->doCurlCall($xpub_fetch_url);

        $secret = $this->getCallbackSecret();
        $callback_url = $this->getCallbackUrl($secret);
        $api_key = $this->getApiKey();
        if ($api_key != $new_api) {
            $error_str = $_BLOCKLANG['testSetup']['newApi']; //API key changed
        } elseif (!isset($response->response_code)) {
            $error_str = $_BLOCKLANG['testSetup']['blockedHttps'];
        } elseif ($response->response_code == 401) {
            $error_str = $_BLOCKLANG['testSetup']['incorrectApi'];
        } elseif ($response->response_code != 200) {
            $error_str = $response->data;
        } elseif (!isset($response->data) || count($response->data) == 0) {
            $error_str = $_BLOCKLANG['testSetup']['noXpub'];
        } elseif (count($response->data) == 1) {
            if (!$response->data[0]->callback || $response->data[0]->callback == null) {
                //No callback URL set, set one
                $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $response->data[0]->address . '"}';
                $this->doCurlCall($set_callback_url, $post_content);
            } elseif ($response->data[0]->callback != $callback_url) {
                // Check if only secret differs
                $base_url = substr($callback_url, 0, -48);
                if (strpos($response->data[0]->callback, $base_url) !== false) {
                    //Looks like the user regenrated callback by mistake
                    //Just force Update_callback on server
                    $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $response->data[0]->address . '"}';
                    $this->doCurlCall($set_callback_url, $post_content);
                } else {
                    $error_str = $_BLOCKLANG['testSetup']['existingCallbackUrl'];
                }
            }
        } else {
            $error_str = $_BLOCKLANG['testSetup']['multipleXpubs'];

            foreach ($response->data as $resObj) {
                if ($resObj->callback == $callback_url) {
                    // Matching callback URL found, set error back to empty
                    $error_str = '';
                }
            }
        }

        if ($error_str == '') {
            // Test new address generation
            $new_addresss_response = $this->getNewAddress('btc', true);
            if ($new_addresss_response->status != 200) {
                $error_str = $new_addresss_response->message;
            }
        }

        return $error_str;
    }
}
