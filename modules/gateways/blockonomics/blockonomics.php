<?php

namespace Blockonomics;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
use Exception;
use stdClass;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../includes/gatewayfunctions.php';

class Blockonomics
{
    private $version = '1.9.8';

    const BASE_URL = 'https://www.blockonomics.co/';
    const BCH_BASE_URL = 'https://bch.blockonomics.co';

    const STORES_URL = self::BASE_URL . '/api/v2/stores?wallets=true';
    const WALLETS_URL = self::BASE_URL . '/api/v2/wallets';
    const NEW_ADDRESS_URL = self::BASE_URL . '/api/new_address';
    const PRICE_URL = self::BASE_URL . '/api/price';

    const BCH_PRICE_URL = self::BCH_BASE_URL . '/api/price';
    const BCH_NEW_ADDRESS_URL = self::BCH_BASE_URL . '/api/new_address';

    const SET_CALLBACK_URL = self::BASE_URL . '/api/update_callback';
    const GET_CALLBACKS_URL = self::BASE_URL . '/api/address?&no_balance=true&only_xpub=true&get_callback=true';

    const BCH_SET_CALLBACK_URL = self::BCH_BASE_URL . '/api/update_callback';
    const BCH_GET_CALLBACKS_URL = self::BCH_BASE_URL . '/api/address?&no_balance=true&only_xpub=true&get_callback=true';

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
                'decimals' => 8,
            ],
            'bch' => [
                'code' => 'bch',
                'name' => 'Bitcoin Cash',
                'uri' => 'bitcoincash',
                'decimals' => 8,
            ],
            'usdt' => array(
                'code' => 'usdt',
                'name' => 'USDT',
                'uri' => 'USDT',
                'decimals' => 6,
            )
        ];
    }

    // save to cache, what cryptos are enabled on blockonomics store
    public function saveBlockonomicsEnabledCryptos($cryptos)
    {
        try {
            Capsule::table('tblpaymentgateways')
                ->updateOrInsert(
                    [
                        'gateway' => 'blockonomics',
                        'setting' => 'EnabledCryptos'
                    ],
                    [
                        'value' => implode(',', $cryptos),
                        'order' => 0
                    ]
                );
            return true;
        } catch (Exception $e) {
            error_log("Failed to save enabled cryptos: " . $e->getMessage());
            return false;
        }
    }

    // see from cache what cryptos are enabled on blockonomics store
    public function getBlockonomicsEnabledCryptos($forceApiCall = false)
    {
        // First try to get from cache unless forceApiCall is true
        if (!$forceApiCall) {
            try {
                $result = Capsule::table('tblpaymentgateways')
                    ->where('gateway', 'blockonomics')
                    ->where('setting', 'EnabledCryptos')
                    ->value('value');

                if ($result) {
                    return explode(',', $result);
                }
            } catch (Exception $e) {
                error_log("Failed to get enabled cryptos from cache: " . $e->getMessage());
            }
        }

        // If cache is empty or forceApiCall is true, make API call
        $enabled_cryptos = [];

        // Make API call to get store info
        $stores_response = json_decode($this->getStoreSetup());
        if (!isset($stores_response) || isset($stores_response->error) || empty($stores_response->data)) {
            return $enabled_cryptos;
        }

        $gatewayParams = getGatewayVariables('blockonomics');
        $callback_url = $gatewayParams['CallbackURL'];

        // Find the best matching store
        $store_to_use = $this->findMatchingStore($stores_response->data, $callback_url);

        // Get enabled cryptocurrencies from the store
        if ($store_to_use) {
            $enabled_cryptos = $this->getStoreEnabledCryptos($store_to_use);
        }

        // Save to cache for future use
        if (!empty($enabled_cryptos)) {
            $this->saveBlockonomicsEnabledCryptos($enabled_cryptos);
        }

        return $enabled_cryptos;
    }

    /**
     * Find a matching store based on callback URL
     *
     * @param array $stores List of stores from Blockonomics API
     * @param string $callback_url The callback URL to match
     * @return object|null Returns matching store or null if not found
     */
    private function findMatchingStore($stores, $callback_url)
    {
        $matching_store = null;
        $store_without_callback = null;
        $partial_match_store = null;

        foreach ($stores as $store) {
            // Exact match
            if ($store->http_callback === $callback_url) {
                return $store;
            }
            
            // Store without callback
            if (empty($store->http_callback)) {
                $store_without_callback = $store;
                continue;
            }

            // Partial match - only secret or protocol differs
            $store_base_url = preg_replace(['/https?:\/\//', '/\?.*$/'], '', $store->http_callback);
            $target_base_url = preg_replace(['/https?:\/\//', '/\?.*$/'], '', $callback_url);

            if ($store_base_url === $target_base_url) {
                $partial_match_store = $store;
            }
        }

        // Return best available match
        $result = $partial_match_store ?: $store_without_callback;
        return $result;
    }

    /**
     * Get enabled cryptocurrencies from a store's wallets
     *
     * @param object $store Store object from Blockonomics API
     * @return array List of enabled cryptocurrency codes
     */
    private function getStoreEnabledCryptos($store)
    {
        $enabled_cryptos = [];

        if (!empty($store->wallets)) {
            foreach ($store->wallets as $wallet) {
                if (isset($wallet->crypto)) {
                    $crypto = strtolower($wallet->crypto);
                    if (!in_array($crypto, $enabled_cryptos)) {
                        $enabled_cryptos[] = $crypto;
                    }
                }
            }
        }

        return $enabled_cryptos;
    }

    /**
     * Update store callback URL on Blockonomics
     *
     * @param object $store Store object to update
     * @param string $callback_url New callback URL
     * @return bool Success status
     */
    private function updateStoreCallback($store, $callback_url)
    {
        $post_content = json_encode([
            'name' => $store->name,
            'http_callback' => $callback_url
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::BASE_URL . '/api/v2/stores/' . $store->id);
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

        return $response_code === 200;
    }
    


    public function getCheckoutCurrencies()
    {
        // Get currencies enabled on Blockonomics store (from cache or API)
        $blockonomics_enabled = $this->getBlockonomicsEnabledCryptos();

        // Result currencies
        $checkout_currencies = [];
        $supported_currencies = $this->getSupportedCurrencies();

        // Add BCH if enabled in WHMCS settings
        $gatewayParams = getGatewayVariables('blockonomics');
        $bchEnabled = $gatewayParams['bchEnabled'];
        if ($bchEnabled && isset($supported_currencies['bch'])) {
            $checkout_currencies['bch'] = $supported_currencies['bch'];
        }

        // Add other currencies from Blockonomics cache
        foreach ($blockonomics_enabled as $code) {
            if ($code != 'bch' && isset($supported_currencies[$code])) {
                $checkout_currencies[$code] = $supported_currencies[$code];
            }
        }

        return $checkout_currencies;
    }
    

    /*
     * Get list of active crypto currencies
     */
    public function getActiveCurrencies()
    {
        $active_currencies = [];
        $blockonomics_currencies = $this->getSupportedCurrencies();

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
        $url = ($currency == 'bch') ? self::BCH_NEW_ADDRESS_URL : self::NEW_ADDRESS_URL;

        // Get the callback URL from gateway cache
        $gatewayParams = getGatewayVariables('blockonomics');
        $callback_url = $gatewayParams['CallbackURL'];

        // Build query parameters
        $params = array();
        if ($callback_url) {
            $params['match_callback'] = $callback_url;
        }
        if ($reset) {
            $params['reset'] = 1;
        }
        $params['crypto'] = strtoupper($currency);

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
     * Make a generic HTTP GET request with optional headers
     */
    private function makeGetRequest($url, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Error: ' . $error);
        }
        
        curl_close($ch);
        
        return [
            'response' => $response,
            'http_code' => $http_code
        ];
    }

    /*
     * Convert fiat amount to Blockonomics currency
     */
    public function convertFiatToBlockonomicsCurrency($fiat_amount, $currency, $blockonomics_currency = 'btc')
    {
        try {
            if ($blockonomics_currency === 'usdt') {
                $url = 'https://min-api.cryptocompare.com/data/price?fsym=' . $blockonomics_currency . '&tsyms=' . $currency;
                $result = $this->makeGetRequest($url);
                $data = json_decode($result['response']);
                $price = $data->{strtoupper($currency)};
            } else {
                $url = ($blockonomics_currency == 'btc') ?
                    self::PRICE_URL . '?currency=' . $currency :
                    self::BCH_PRICE_URL . '?currency=' . $currency;
                $result = $this->makeGetRequest($url);
                $price = json_decode($result['response'])->price;
            }

            $margin = floatval($this->getMargin());
            if ($margin > 0) {
                // lower price means customers need to pay more BTC for the same fiat amount
                $price = $price * 100 / (100 + $margin);
            }
        } catch (Exception $e) {
            exit("Error getting price from Blockonomics! {$e->getMessage()}");
        }

        $supportedCurrency = $this->getSupportedCurrencies();
        $crypto = $supportedCurrency[$blockonomics_currency];

        $multiplier = pow(10, $crypto['decimals']);
        return intval($multiplier * $fiat_amount / $price);
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
            // Check if it's a duplicate key error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                exit("Duplicate address generation error. Note to website administrator: please contact Blockonomics support portal for assistance.");
            }
            // For other database errors, show a generic message
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
            if ($blockonomics_currency === 'usdt') {
                $order->addr = $new_addresss_response->address . '-' . $order->id_order;
            } else {
                $order->addr = $new_addresss_response->address;
            }
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
            'addr' => $existing_order->addr,
        ];
    }

    /*
     * Try to get order row from db by txnid
     */
    public function getOrderBytxn($txnid)
    {
        try {
            $existing_order = Capsule::table('blockonomics_orders')
            ->where('txid', $txnid)
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
            'addr' => $existing_order->addr,
        ];
    }

    /*
     * Try to get order row from db by transaction
     */

     public function blockonomicsTransactionExists($txhash)
     {
         $existing_order = Capsule::table('blockonomics_orders')
             ->where('txid', $txhash)
             ->first();
 
         return $existing_order !== null;
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

    /*
    * Get the wallets from the API, also checks if API key is valid
    * @return array [
    *   'is_valid' => bool,    // Whether the API key is valid
    *   'error' => string,     // Error message if any
    *   'wallets' => array     // Array of configured wallet currencies
    * ]
    */
    public function get_wallets()
    {
        include $this->getLangFilePath();
        $api_key = $this->getApiKey();
        $result = [
            'is_valid' => false,
            'error' => '',
            'wallets' => []
        ];

        // check if API key is empty
        if (empty($api_key)) {
            $result['error'] = $_BLOCKLANG['testSetup']['emptyApi'];
            return $result;
        }
        //Make API call to get wallets and also consequently check if API key is valid
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::WALLETS_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($api_key),
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);

        // check network level errors like CORS, connection failure
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $result['error'] = $_BLOCKLANG['testSetup']['blockedHttps'];
            curl_close($ch);
            return $result;
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 401) {
            $result['error'] = $_BLOCKLANG['testSetup']['incorrectApi'];
            return $result;
        }

        if ($http_code !== 200) {
            // For any other error, return the API response as is
            $result['error'] = 'API Error: ' . $response;
            return $result;
        }

        $response_data = json_decode($response);

        // Check if response is valid JSON
        if (!$response_data) {
            return $_BLOCKLANG['testSetup']['invalidResponse'];
        }

        // API key is valid at this point
        $result['is_valid'] = true;

        // Process wallet information to get unique cryptocurrencies
        if (!empty($response_data->data)) {
            foreach ($response_data->data as $wallet) {
                if (isset($wallet->crypto)) {
                    $crypto = strtolower($wallet->crypto);
                    if (!in_array($crypto, $result['wallets'])) {
                        $result['wallets'][] = $crypto;
                    }
                }
            }
        }

        // If no wallets configured
        if (empty($result['wallets'])) {
            $result['error'] = $_BLOCKLANG['testSetup']['addWallet'];
            return $result;
        }

        return $result;

    }

    /**
     * Run the test setup
     *
     * @return string error message or success message
     */
    public function testSetup()
    {
        include $this->getLangFilePath();

        $api_key = $this->getApiKey();
        if (empty($api_key)) {
            return $_BLOCKLANG['testSetup']['emptyApi'];
        }

        // Check configured wallets and validate API key
        $wallet_result = $this->get_wallets();

        // If API key is invalid or any other error, return error
        if (!isset($wallet_result['is_valid']) || !$wallet_result['is_valid']) {
            return isset($wallet_result['error']) ? $wallet_result['error'] : $_BLOCKLANG['testSetup']['incorrectApi'];
        }

        // If no wallets configured, return error
        if (empty($wallet_result['wallets'])) {
            return $_BLOCKLANG['testSetup']['addWallet'];
        }

        // Now we check the configured stores on blockonomics dashboard
        $stores_response = json_decode($this->getStoreSetup());

        if (!isset($stores_response) || isset($stores_response->error)) {
            return $_BLOCKLANG['testSetup']['blockedHttps'];
        }

        if (empty($stores_response->data)) {
            return $_BLOCKLANG['testSetup']['addStore'];
        }
        $gatewayParams = getGatewayVariables('blockonomics');
        $callback_url = $gatewayParams['CallbackURL'];

        $matching_store = $this->findMatchingStore($stores_response->data, $callback_url);
        if (!$matching_store) {
            return $_BLOCKLANG['testSetup']['addStore'];
        }

        // If we found a store but it doesn't have an exact matching callback, update it
        if ($matching_store->http_callback !== $callback_url) {
            if (!$this->updateStoreCallback($matching_store, $callback_url)) {
                return "Could not update store callback";
            }
        }

        // Save store name
        try {
            $storeName = $gatewayParams['StoreName'];

            if (!isset($storeName) || $storeName !== $matching_store->name) {
                Capsule::table('tblpaymentgateways')
                    ->updateOrInsert(
                        [
                            'gateway' => 'blockonomics',
                            'setting' => 'StoreName'
                        ],
                        [
                            'value' => $matching_store->name,
                            'order' => 0
                        ]
                    );
            }
        } catch (Exception $e) {
            error_log("Failed to save store name: " . $e->getMessage());
            // Non-critical error, continue
        }

        // Get enabled cryptos from the store
        $enabled_cryptos = $this->getStoreEnabledCryptos($matching_store);

        if (empty($enabled_cryptos)) {
            return $_BLOCKLANG['testSetup']['noCrypto'];
        }

        // Save the enabled cryptos to WHMCS settings for later use in checkout
        $this->saveBlockonomicsEnabledCryptos($enabled_cryptos);

        // Test address generation for each enabled crypto (except BCH)
        $success_messages = [];
        $error_messages = [];

        foreach ($enabled_cryptos as $code) {
            // Skip BCH for testing
            if ($code == 'bch') {
                continue;
            }

            // Test address generation
            $response = $this->getNewAddress($code, true);

            if ($response->response_code == 200) {
                $success_messages[] = strtoupper($code) . " ✅";
            } else {
                $error_messages[] = strtoupper($code) . ": " . $response->message;
            }
        }

        // If we have errors, return them
        if (!empty($error_messages)) {
            return implode("<br>", $error_messages);
        }

        // If we have successes, return them
        if (!empty($success_messages)) {
            return implode("<br>", $success_messages);
        }

        // If we get here, something went wrong
        return "No cryptocurrencies were tested";
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
    
    public function fix_displaying_small_values($satoshi, $blockonomics_currency)
    {
        $supportedCurrency = $this->getSupportedCurrencies();
        $crypto = $supportedCurrency[$blockonomics_currency];
        $multiplier = pow(10, $crypto['decimals']);

        if ($blockonomics_currency == 'usdt') {
            // For USDT, you usually don't need to display very small fractional values.
            // Assuming $amount is in USDT, and typically 2 decimal places are sufficient.
            return rtrim(number_format($satoshi/$multiplier, 2, '.', ''));
        }
        if ($satoshi < 10000){
            return rtrim(number_format($satoshi/1.0e8, 8),0);
        } else {
            return $satoshi/1.0e8;
        }
    }

    public function get_crypto_rate_from_params($value, $satoshi, $blockonomics_currency) {
        $supportedCurrency = $this->getSupportedCurrencies();
        $crypto = $supportedCurrency[$blockonomics_currency];
        $multiplier = pow(10, $crypto['decimals']);

        // Crypto Rate is re-calculated here and may slightly differ from the rate provided by Blockonomics
        // This is required to be recalculated as the rate is not stored anywhere in $order, only the converted satoshi amount is.
        // This method also helps in having a constant conversion and formatting for both Initial Load and API Refresh
        return number_format($value*$multiplier/$satoshi, 2, '.', '');
    }
    
    public function load_checkout_template($ca, $show_order, $crypto)
    {
        $time_period_from_db = $this->getTimePeriod();
        $time_period = isset($time_period_from_db) ? $time_period_from_db : '10';

        $order = $this->processOrderHash($show_order, $crypto);
        $active_cryptos = $this->getCheckoutCurrencies();

        if (is_string($crypto) && isset($active_cryptos[$crypto])) {
            // If $crypto is a string key, get the full crypto object
            $crypto = $active_cryptos[$crypto];
        } else if (is_string($crypto)) {
            // If $crypto is a string but not found in active_cryptos,
            // create a basic crypto object with the necessary fields
            $supported_currencies = $this->getSupportedCurrencies();
            if (isset($supported_currencies[$crypto])) {
                $crypto = $supported_currencies[$crypto];
            } else {
                // Fallback to a basic structure if the crypto isn't in supported currencies
                $crypto = [
                    'code' => $crypto,
                    'name' => strtoupper($crypto),
                    'uri' => $crypto
                ];
            }
        }

        $order_amount = $this->fix_displaying_small_values($order->bits, $order->blockonomics_currency);
        $gatewayParams = getGatewayVariables('blockonomics');
        $checkoutMode = $gatewayParams['CheckoutMode'] ?? 'mainnet';
        $context = array(
            'time_period' => $time_period,
            'order' => $order,
            'order_hash' => $show_order,
            'crypto_rate_str' => $this->get_crypto_rate_from_params($order->value, $order->bits, $order->blockonomics_currency),
            'order_amount' => $order_amount,
            'payment_uri' => $this->get_payment_uri($crypto['uri'], $order->addr, $order_amount),
            'crypto' => $crypto,
            'is_testnet' => ($checkoutMode === 'testnet')
        );

        if ($order->blockonomics_currency === 'usdt') {
            $address_parts = explode('-', $order->addr);
            $order_receive_address = $address_parts[0];
            $context['order_receive_address'] = $order_receive_address;
        }

        $this->load_blockonomics_template($ca, 'checkout', $context);
    }

    public function get_order_checkout_params($params)
    {
        $order_hash = $this->getOrderHash($params['invoiceid'], $params['amount'], $params['currency'], $params['basecurrencyamount']);

        $order_params = [];
        $active_cryptos = $this->getCheckoutCurrencies();

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
        curl_setopt($ch, CURLOPT_URL, self::STORES_URL);
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
        // Log the API call
        logModuleCall(
            'blockonomics',
            'Get Store Setup - Request',
            [
                'url' => self::STORES_URL,
                'headers' => $headers,
                'method' => 'GET'
            ],
            null,
            null,
            [$api_key]
        );
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

    function process_token_order($finish_order, $crypto, $txhash) {
        include $this->getLangFilePath();

        $order = $this->processOrderHash($finish_order, $crypto);

        if (empty($order)) {
            return;
        }

        $transactionExists = $this->blockonomicsTransactionExists($txhash);

        if ($transactionExists) {
            return;
        }

        $blockonomics_currency = $this->getSupportedCurrencies()[$crypto];

        // Get callback URL for monitoring
        $callback_secret = $this->getCallbackSecret();
        $callback_url = $this->getCallbackUrl($callback_secret);
        $gatewayParams = getGatewayVariables('blockonomics');
        $checkoutMode = $gatewayParams['CheckoutMode'] ?? 'mainnet';

        // Prepare monitoring request
        $monitor_url = self::BASE_URL . '/api/monitor_tx';
        $post_data = array(
            'txhash' => $txhash,
            'crypto' => strtoupper($crypto),
            'match_callback' => $callback_url,
            'network' => ($checkoutMode === 'testnet') ? 'testnet' : 'mainnet',
        );

        try {
            $headers = [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-Type: application/json'
            ];
            
            $this->makePostRequest($monitor_url, $post_data, $headers);
            
            // Update invoice note
            $invoiceNote = '<b>' . $_BLOCKLANG['invoiceNote']['waiting'] . ' <img src="' . \App::getSystemURL() . 'modules/gateways/blockonomics/assets/img/usdt.png" style="max-width: 20px;"> ' . $blockonomics_currency['name'] . ' ' . $_BLOCKLANG['invoiceNote']['network'] . "</b>\r\r" .
            $blockonomics_currency['name'] . " transaction id:\r" .
                '<a target="_blank" href="https://www.etherscan.io/tx/' . $txhash . '">' . $txhash . '</a>';

            $this->updateOrderInDb($order->addr, $txhash, 0, 0);
            $this->updateInvoiceNote($invoiceId, $invoiceNote);
        } catch (Exception $e) {
            exit("Error processing token order: {$e->getMessage()}");
        }
    }

    /*
     * Make a generic HTTP POST request with JSON data and optional headers
     */
    private function makePostRequest($url, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Error: ' . $error);
        }
        
        curl_close($ch);
        
        return [
            'response' => $response,
            'http_code' => $http_code
        ];
    }
}
