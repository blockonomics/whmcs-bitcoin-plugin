<?php

namespace Blockonomics;

use Exception;
use stdClass;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../includes/gatewayfunctions.php';

class Blockonomics
{
    private $version = '1.9.8';

    const SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    const GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';

    const BCH_SET_CALLBACK_URL = 'https://bch.blockonomics.co/api/update_callback';
    const BCH_GET_CALLBACKS_URL = 'https://bch.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';

    private const BASE_URL = 'https://www.blockonomics.co/api';
    private const BCH_BASE_URL = 'https://bch.blockonomics.co/api';

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
        return [
            'btc' => [
                'code' => 'btc',
                'name' => 'Bitcoin',
                'uri' => 'bitcoin',
            ],
            'bch' => [
                'code' => 'bch',
                'name' => 'Bitcoin Cash',
                'uri' => 'bitcoincash',
            ],
        ];
    }

    /*
     * Get list of active crypto currencies
     */
    public function getActiveCurrencies()
    {
        $active_currencies = [];
        $blockonomics_currencies = $this->getSupportedCurrencies();
        $active_currencies['btc'] = $blockonomics_currencies['btc'];
        
        foreach ($blockonomics_currencies as $code => $currency) {
            $gatewayParams = getGatewayVariables('blockonomics');
            $enabled = $gatewayParams[$code . 'Enabled'];
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

    /**
     * Check if Admin or Exit
     * Ref: https://developers.whmcs.com/advanced/authentication/
     */
    public function checkAdmin()
    {
        global $CONFIG;

        $isAdmin = FALSE;

        if (version_compare($CONFIG['Version'], "8.0.0") >= 0) {
            $currentUser = new \WHMCS\Authentication\CurrentUser;
            $isAdmin = $currentUser->isAuthenticatedAdmin();
        } else {
            $isAdmin = !is_null($_SESSION['adminid']);
        }

        if ($isAdmin) {
            return TRUE;
        } else {
            http_response_code(403);
            exit("Permission Denied. You should be an admin to perform this action!");
        }
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
        // Determine base URL based on currency
        $base_url = ($currency == 'btc') ? self::BASE_URL : self::BCH_BASE_URL;
        $url = $base_url . '/new_address';

        // Get the callback URL with secret
        $callback_secret = $this->getCallbackSecret();
        $callback_url = $this->getCallbackUrl($callback_secret);

        // Build query parameters
        $params = array();
        if ($callback_url) {
            $params['match_callback'] = $callback_url;
        }
        if ($reset) {
            $params['reset'] = 1;
        }

        // Append query parameters to URL
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getApiKey()
        ]);

        // Execute request
        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            exit('Error:' . curl_error($ch));
        }

        // Create response object
        $responseObj = json_decode($contents);
        if (!isset($responseObj)) {
            $responseObj = new stdClass();
        }

        // Add response code
        $responseObj->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Add error message if needed
        if (!isset($responseObj->message)) {
            if (isset($responseObj->error_code) && $responseObj->error_code == 1002) {
                $responseObj->message = 'Multiple wallets found. Please ensure callback URL is set correctly.';
            } else {
                $responseObj->message = 'Error: (' . $responseObj->response_code . ') ' . $contents;
            }
        }

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
                        $table->index('id_order');
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
        $log_data = array(
            'invoice_id' => $new_order->id_order,
            'address' => $new_order->addr,
            'crypto' => $new_order->blockonomics_currency
        );
        $gatewayParams = getGatewayVariables('blockonomics');
        logTransaction($gatewayParams['name'], $log_data, 'New Order Created');
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
        // Allow only a-z
        if (!preg_match('/^[a-z]+$/', $language)) {
            return dirname(__FILE__) . '/lang/english.php';
        }
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
     * @return string error message
     */
    public function testSetup()
    {
        $test_results = array();
        $active_currencies = $this->getActiveCurrencies();

        foreach ($active_currencies as $code => $currency) {
            $result = $this->test_one_currency($code);

            if (is_array($result) && isset($result['error'])) {
                $test_results[$code] = $result['error'];
            } else {
                $test_results[$code] = $result;
                if ($result === false) {
                    // Success case - get store data from API
                    $store_setup = $this->checkStoreSetup();
                    if (isset($store_setup['success'])) {
                        try {
                            $gatewayParams = getGatewayVariables('blockonomics');
                            $storeName = $gatewayParams['StoreName'];

                            // Only update if store name has changed
                            if (!isset($storeName) || $storeName !== $store_setup['store_name']) {
                                Capsule::table('tblpaymentgateways')
                                    ->updateOrInsert(
                                        [
                                            'gateway' => 'blockonomics',
                                            'setting' => 'StoreName'
                                        ],
                                        [
                                            'value' => $store_setup['store_name'],
                                            'order' => 0
                                        ]
                                    );
                                }
                        } catch (Exception $e) {
                            $test_results[$code] = "Failed to save store configuration";
                        }
                    }
                }
            }
        }
        
        return $test_results;
    }

    public function test_one_currency($currency)
    {
        include $this->getLangFilePath();
        $api_key = $this->getApiKey();

        // Function to process store metadata and enabled cryptos
        $process_store = function($store) use ($currency) {
            // Extract enabled cryptos from wallets
            $enabled_cryptos = array();
            if (!empty($store->wallets)) {
                foreach ($store->wallets as $wallet) {
                    if (isset($wallet->crypto)) {
                        $enabled_cryptos[] = strtolower($wallet->crypto);
                    }
                }
            }

            if (empty($enabled_cryptos)) {
                return 'No crypto enabled for this store';
            }

            $error = $this->test_new_address_gen($currency);
            return $error ? array('error' => $error) : false;
        };

        if (!$api_key) {
            return $_BLOCKLANG['testSetup']['emptyApi'];
        }

        if ($currency !== 'btc') {
            return 'Test Setup only supports BTC';
        }

        // Get store setup from API
        $stores_response = json_decode($this->getStoreSetup());

        // Check if the API key is valid
        if (!isset($stores_response)) {
            return $_BLOCKLANG['testSetup']['blockedHttps'];
        }

        if (isset($stores_response->error)) {
            if ($stores_response->error === 'Invalid API key or unauthorized access') {
                return $_BLOCKLANG['testSetup']['incorrectApi'];
            }
            return $stores_response->error;
        }

        if (empty($stores_response->data)) {
            return $_BLOCKLANG['testSetup']['addStore'];
        }

        // Find matching store or store without callback
        $callback_secret = $this->getCallbackSecret();
        $callback_url = $this->getCallbackUrl($callback_secret);
        $base_url = preg_replace('/https?:\/\//', '', $callback_url);

        $matching_store = null;
        $store_without_callback = null;
        $partial_match_store = null;

        foreach ($stores_response->data as $store) {
            if ($store->http_callback === $callback_url) {
                $matching_store = $store;
                break;
            }
            if (empty($store->http_callback)) {
                $store_without_callback = $store;
                continue;
            }
            // Check for partial match - only secret or protocol differs
            // Extract base URL by removing protocol and query parameters
            $store_base_url = preg_replace(['/https?:\/\//', '/\?.*$/'], '', $store->http_callback);
            $target_base_url = preg_replace(['/https?:\/\//', '/\?.*$/'], '', $callback_url);

            if ($store_base_url === $target_base_url) {
                $partial_match_store = $store;
            }
        }

        // If we found an exact match, process it
        if ($matching_store) {
            return $process_store($matching_store);
        }

        // If we found a partial match or store without callback, update its callback
        $store_to_update = $partial_match_store ?: $store_without_callback;
        if ($store_to_update) {
            $post_content = json_encode([
                'name' => $store_to_update->name,
                'http_callback' => $callback_url
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.blockonomics.co/api/v2/stores/' . $store_to_update->id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_content);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response_code !== 200) {
                return 'Could not update store callback';
            }

            return $process_store($store_to_update);
        }

        return $_BLOCKLANG['testSetup']['addStore'];
    }

    public function get_callbacks($currency)
    {
        if ($currency == 'btc'){
            $url = Blockonomics::GET_CALLBACKS_URL;
        }else{
            $url = Blockonomics::BCH_GET_CALLBACKS_URL;
        }
        $response = $this->doCurlCall($url);
        return $response;
    }

    public function check_callback_urls_or_set_one($currency, $response) 
    {
        $api_key = $this->getApiKey();

        //check the current callback and detect any potential errors
        $error_str = $this->check_get_callbacks_response_code($response, $currency);

        if(!$error_str){
            //if needed, set the callback.
            $error_str = $this->check_get_callbacks_response_body($response, $currency);
        }
        return $error_str;
    }

    public function check_get_callbacks_response_code($response)
    {
        
        include $this->getLangFilePath();
        
        $error_str = '';
        
        if (!isset($response->response_code)) {
            $error_str = $_BLOCKLANG['testSetup']['blockedHttps'];
        } elseif ($response->response_code == 401) {
            $error_str = $_BLOCKLANG['testSetup']['incorrectApi'];
        } elseif ($response->response_code != 200) {
            $error_str = $response->data;
        }
        
        return $error_str;
    }
    
    public function check_get_callbacks_response_body($response, $currency)
    {
        include $this->getLangFilePath();

        $error_str = '';

        if (!isset($response->data) || count($response->data) == 0) {
            $error_str = $_BLOCKLANG['testSetup']['addStore'];
        }
        //if merchant has at least one xPub on his Blockonomics account
        elseif (count($response->data) >= 1)
        {
            $error_str = $this->examine_server_callback_urls($response, $currency);
        }
        return $error_str;
    }

    // checks each existing xpub callback URL to update and/or use
    public function examine_server_callback_urls($response, $currency)
    {
        include $this->getLangFilePath();

        $callback_secret = $this->getCallbackSecret();
        $callback_url = $this->getCallbackUrl($callback_secret);
        // Extract String before get parameters
        $site_url = strtok($callback_url, "?");
        // Replace http:// or https:// from the $site_url to get base_url
        $base_url = preg_replace('/https?:\/\//', '', $site_url);
        $error_str = '';

        $available_xpub = '';
        $partial_match = '';

        //Go through all xpubs on the server and examine their callback url
        foreach($response->data as $one_response){
            $server_callback_url = isset($one_response->callback) ? $one_response->callback : '';
            $server_base_url = preg_replace('/https?:\/\//', '', $server_callback_url);
            $xpub = isset($one_response->address) ? $one_response->address : '';

            if(!$server_callback_url){
                // No callback
                $available_xpub = $xpub;
            }else if($server_callback_url == $callback_url){
                // Exact match
                return '';
            }
            else if(strpos($server_base_url, $base_url) === 0 ){
                // Partial Match - Only secret or protocol differ
                $partial_match = $xpub;
            }
        }

        // Use the available xpub
        if($partial_match || $available_xpub){
            $update_xpub = $partial_match ? $partial_match : $available_xpub;
            $response = $this->update_callback($callback_url, $currency, $update_xpub);
            if ($response->response_code != 200) {
                return $response->message;
            }
            return '';
        }

        // No match and no empty callbac        
        $error_str = $_BLOCKLANG['testSetup']['addStore'];

        return $error_str;
    }

    public function update_callback($callback_url, $currency, $xpub)
    {
        if ($currency == 'btc'){
            $url = Blockonomics::SET_CALLBACK_URL;
        }else{
            $url = Blockonomics::BCH_SET_CALLBACK_URL;
        }

        $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $xpub . '"}';
        $response = $this->doCurlCall($url, $post_content);
        return $response;
    }

    public function test_new_address_gen($currency)
    {
        $error_str = '';
        $response = $this->getNewAddress($currency, true);
        if ($response->response_code != 200){ 
            $error_str = $response->message;
        }
        return $error_str;
    }

    public function redirect_finish_order($order_hash)
    {
        $order_id = $this->decryptHash($order_hash)->id_order;
        $finish_url = \App::getSystemURL() . 'viewinvoice.php?id=' . $order_id . '&paymentsuccess=true';
        header("Location: $finish_url");
        exit();
    }

    public function load_blockonomics_template($ca, $template, $context=array())
    {
        foreach ($context as $key => $value) {
            $ca->assign($key, $value);
        }
        $ca->setTemplate("/modules/gateways/blockonomics/assets/templates/$template.tpl");
    }

    public function get_payment_uri($uri, $addr, $amount)
    {
        return $uri . ':' . $addr . '?amount=' . $amount;
    }
    
    public function fix_displaying_small_values($satoshi)
    {
        if ($satoshi < 10000){
            return rtrim(number_format($satoshi/1.0e8, 8),0);
        } else {
            return $satoshi/1.0e8;
        }
    }

    public function get_crypto_rate_from_params($value, $satoshi) {
        // Crypto Rate is re-calculated here and may slightly differ from the rate provided by Blockonomics
        // This is required to be recalculated as the rate is not stored anywhere in $order, only the converted satoshi amount is.
        // This method also helps in having a constant conversion and formatting for both Initial Load and API Refresh
        return number_format($value*1.0e8/$satoshi, 2, '.', '');
    }
    
    public function load_checkout_template($ca, $show_order, $crypto)
    {
        $time_period_from_db = $this->getTimePeriod();
        $time_period = isset($time_period_from_db) ? $time_period_from_db : '10';

        $order = $this->processOrderHash($show_order, $crypto);
        $active_cryptos = $this->getActiveCurrencies();

        $crypto = $active_cryptos[$crypto];
        $order_amount = $this->fix_displaying_small_values($order->bits);
        
        $context = array(
            'time_period' => $time_period,
            'order' => $order,
            'order_hash' => $show_order,
            'crypto_rate_str' => $this->get_crypto_rate_from_params($order->value, $order->bits),
            'order_amount' => $order_amount,
            'payment_uri' => $this->get_payment_uri($crypto['uri'], $order->addr, $order_amount),
            'crypto' => $crypto
        );

        $this->load_blockonomics_template($ca, 'checkout', $context);
    }

    public function get_order_checkout_params($params)
    {
        $order_hash = $this->getOrderHash($params['invoiceid'], $params['amount'], $params['currency'], $params['basecurrencyamount']);

        $order_params = [];
        $active_cryptos = $this->getActiveCurrencies();

        // Check how many crypto currencies are activated
        if (count($active_cryptos) > 1) {
            $order_params = ['select_crypto' => $order_hash];
        } elseif (count($active_cryptos) === 1) {
            $order_params = [
                'show_order' => $order_hash,
                'crypto' => array_keys($active_cryptos)[0]
            ];
        } elseif (count($active_cryptos) === 0) {
            $order_params = [
                'crypto' => 'empty'
            ];
        }

        return $order_params;
    }

    /*
     * Get store setup from Blockonomics API
     */
    public function getStoreSetup() 
    {
        $api_key = $this->getApiKey();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.blockonomics.co/api/v2/stores?wallets=true');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($api_key),
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            http_response_code(500);
            return json_encode(['error' => 'cURL Error: ' . $error]);
        }

        curl_close($ch);

        // Handle response based on status code
        if ($http_code === 401) {
            return json_encode(['error' => 'Invalid API key or unauthorized access']);
        } elseif ($http_code !== 200) {
            http_response_code($http_code);
            return json_encode(['error' => 'Blockonomics API error',  'response' => $response]);
        }

        return $response;
    }

    public function checkStoreSetup()
    {
        $response = json_decode($this->getStoreSetup());
        
        // Check if we got a valid response with stores data
        if (!isset($response->data) || empty($response->data)) {
            return array('needs_store' => true);
        }

        $secret = $this->getCallbackSecret();
        $callback_url = $this->getCallbackUrl($secret);

        foreach ($response->data as $store) {
            if ($store->http_callback === $callback_url) {
                return array(
                    'success' => true,
                    'store_name' => $store->name
                );
            }
        }

        // No matching store found
        return array('needs_store' => true);
    }
}
