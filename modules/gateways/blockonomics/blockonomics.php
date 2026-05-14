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
    private $version = '2.1-rc1';

    const BASE_URL = 'https://www.blockonomics.co';
    const BCH_BASE_URL = 'https://bch.blockonomics.co';

    const STORES_URL = self::BASE_URL . '/api/v2/stores?wallets=true';
    const NEW_ADDRESS_URL = self::BASE_URL . '/api/new_address';
    const PRICE_URL = self::BASE_URL . '/api/price';

    const BCH_PRICE_URL = self::BCH_BASE_URL . '/api/price';
    const BCH_NEW_ADDRESS_URL = self::BCH_BASE_URL . '/api/new_address';


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

    // Build HTML for store info display (store name + crypto icons)
    public function buildStoreInfoHtml($store_name, $enabled_cryptos)
    {
        if (empty($store_name)) {
            return '';
        }
        $html = htmlspecialchars($store_name, ENT_QUOTES);
        $iconBase = '../modules/gateways/blockonomics/assets/img/';
        $validCryptos = array_keys($this->getSupportedCurrencies());
        foreach ($enabled_cryptos as $code) {
            $code = strtolower($code);
            if (in_array($code, $validCryptos)) {
                $html .= ' <img src="' . $iconBase . $code . '.svg" alt="' . strtoupper($code) . '" style="height:18px;vertical-align:middle;margin-left:4px;" title="' . strtoupper($code) . '" />';
            }
        }
        return $html;
    }

    // Get enabled cryptos from cache (populated by Test Setup)
    // Lazy-fills cache from Blockonomics stores API on miss — keeps checkout
    // working on fresh installs and after transient Test Setup failures
    public function getBlockonomicsEnabledCryptos()
    {
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

        $cryptos = $this->fetchEnabledCryptosFromApi();
        if (!empty($cryptos)) {
            $this->saveBlockonomicsEnabledCryptos($cryptos);
        }
        return $cryptos;
    }

    /*
     * Fetch enabled cryptos from Blockonomics stores API for lazy cache fill
     * Returns [] on any failure (no credentials, network error, no matching store)
     */
    private function fetchEnabledCryptosFromApi()
    {
        $gatewayParams = getGatewayVariables('blockonomics');
        $callback_url = $gatewayParams['CallbackURL'] ?? '';
        if (empty($this->getApiKey()) || empty($callback_url)) {
            return [];
        }
        try {
            $stores_response = json_decode($this->getStoreSetup());
            if (!isset($stores_response->data) || !is_array($stores_response->data)) {
                return [];
            }
            $matching_store = $this->findExactMatchingStore($stores_response->data, $callback_url);
            if (!$matching_store) {
                return [];
            }
            return $this->getStoreEnabledCryptos($matching_store);
        } catch (Exception $e) {
            error_log("Failed to lazy-fetch enabled cryptos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a store whose http_callback exactly equals our callback URL.
     * Strict match — secret, protocol, host, path, query all must agree.
     * Returns matching store object or null.
     */
    private function findExactMatchingStore($stores, $callback_url)
    {
        foreach ($stores as $store) {
            if ($store->http_callback === $callback_url) {
                return $store;
            }
        }
        return null;
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
     * Build price API URL for the given fiat currency and crypto
     */
    public function buildPriceUrl($currency, $blockonomics_currency)
    {
        if ($blockonomics_currency === 'bch') {
            return self::BCH_PRICE_URL . '?currency=' . $currency;
        }
        return self::PRICE_URL . '?currency=' . $currency . '&crypto=' . strtoupper($blockonomics_currency);
    }

    /*
     * Fetch price from Blockonomics API
     */
    public function fetchPrice($currency, $blockonomics_currency)
    {
        $url = $this->buildPriceUrl($currency, $blockonomics_currency);
        $result = $this->makeGetRequest($url);
        if ($result['http_code'] != 200) {
            throw new Exception("HTTP {$result['http_code']}: " . substr($result['response'], 0, 200));
        }
        $decoded = json_decode($result['response']);
        if (!isset($decoded->price) || empty($decoded->price)) {
            throw new Exception("Missing/empty price in response: " . substr($result['response'], 0, 200));
        }
        return $decoded->price;
    }

    /*
     * Build new_address API URL for the given crypto
     */
    public function buildNewAddressUrl($blockonomics_currency)
    {
        $url = ($blockonomics_currency === 'bch') ? self::BCH_NEW_ADDRESS_URL : self::NEW_ADDRESS_URL;

        $gatewayParams = getGatewayVariables('blockonomics');
        $callback_url = $gatewayParams['CallbackURL'];

        $params = ['crypto' => strtoupper($blockonomics_currency)];
        if ($callback_url) {
            $params['match_callback'] = $callback_url;
        }

        return $url . '?' . http_build_query($params);
    }

    /*
     * Fetch new_address and price in parallel via curl_multi
     * Returns ['address' => string, 'price' => float] on success, ['error' => string] on failure
     */
    public function fetchOrderDataParallel($currency, $blockonomics_currency)
    {
        $address_url = $this->buildNewAddressUrl($blockonomics_currency);
        $price_url = $this->buildPriceUrl($currency, $blockonomics_currency);

        $address_ch = curl_init();
        curl_setopt($address_ch, CURLOPT_URL, $address_url);
        curl_setopt($address_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($address_ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($address_ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getApiKey()
        ]);

        $price_ch = curl_init();
        curl_setopt($price_ch, CURLOPT_URL, $price_url);
        curl_setopt($price_ch, CURLOPT_RETURNTRANSFER, 1);

        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $address_ch);
        curl_multi_add_handle($mh, $price_ch);

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running) {
                if (curl_multi_select($mh) === -1) {
                    usleep(100);
                }
            }
        } while ($running > 0);

        $address_body = curl_multi_getcontent($address_ch);
        $address_code = curl_getinfo($address_ch, CURLINFO_HTTP_CODE);
        $address_curl_err = curl_error($address_ch);
        $price_body = curl_multi_getcontent($price_ch);
        $price_code = curl_getinfo($price_ch, CURLINFO_HTTP_CODE);
        $price_curl_err = curl_error($price_ch);

        curl_multi_remove_handle($mh, $address_ch);
        curl_multi_remove_handle($mh, $price_ch);
        curl_close($address_ch);
        curl_close($price_ch);
        curl_multi_close($mh);

        if ($address_code != 200) {
            $err = json_decode($address_body);
            if (isset($err->error->message)) {
                $msg = $err->error->message;
            } elseif (isset($err->message)) {
                $msg = $err->message;
            } elseif ($address_curl_err) {
                $msg = "Network error: $address_curl_err";
            } else {
                $msg = "Error: ($address_code) $address_body";
            }
            return ['error' => $msg];
        }
        $address_obj = json_decode($address_body);
        if (!isset($address_obj->address) || empty($address_obj->address)) {
            return ['error' => 'Could not generate address'];
        }

        if ($price_code != 200) {
            if ($price_curl_err) {
                return ['error' => "Network error getting price from Blockonomics: $price_curl_err"];
            }
            return ['error' => "Error getting price from Blockonomics: ($price_code) $price_body"];
        }
        $price_obj = json_decode($price_body);
        if (!isset($price_obj->price) || empty($price_obj->price)) {
            return ['error' => 'Could not get price from Blockonomics'];
        }

        return [
            'address' => $address_obj->address,
            'price' => $price_obj->price,
        ];
    }

    /*
     * Test new_address API for multiple cryptos in parallel via curl_multi
     * Returns ['success_messages' => [...], 'error_messages' => [...]]
     */
    public function testCryptos($enabled_cryptos)
    {
        $success_messages = [];
        $error_messages = [];

        // BCH not testable via stores API (separate infrastructure)
        $cryptos_to_test = array_filter($enabled_cryptos, function ($code) {
            return $code !== 'bch';
        });
        if (empty($cryptos_to_test)) {
            return ['success_messages' => $success_messages, 'error_messages' => $error_messages];
        }

        $mh = curl_multi_init();
        $handles = [];
        foreach ($cryptos_to_test as $code) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->buildNewAddressUrl($code));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getApiKey()
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$code] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running) {
                if (curl_multi_select($mh) === -1) {
                    usleep(100);
                }
            }
        } while ($running > 0);

        foreach ($handles as $code => $ch) {
            $body = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $obj = json_decode($body);

            if ($http_code == 200 && isset($obj->address) && !empty($obj->address)) {
                $success_messages[] = strtoupper($code) . " ✅";
            } else {
                if (isset($obj->error->message)) {
                    $msg = $obj->error->message;
                } elseif (isset($obj->message)) {
                    $msg = $obj->message;
                } else {
                    $msg = "Error: ($http_code) $body";
                }
                $error_messages[] = strtoupper($code) . ": " . $msg;
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return [
            'success_messages' => $success_messages,
            'error_messages' => $error_messages,
        ];
    }

    /*
     * Apply merchant margin and convert fiat amount to crypto smallest unit (satoshi/wei)
     */
    public function applyMarginAndConvertToBits($fiat_amount, $price, $blockonomics_currency)
    {
        $margin = floatval($this->getMargin());
        if ($margin > 0) {
            // lower price means customers need to pay more crypto for the same fiat amount
            $price = $price * 100 / (100 + $margin);
        }

        $supportedCurrency = $this->getSupportedCurrencies();
        $crypto = $supportedCurrency[$blockonomics_currency];

        $multiplier = pow(10, $crypto['decimals']);
        return intval($multiplier * $fiat_amount / $price);
    }

    /*
     * Convert fiat amount to Blockonomics currency
     */
    public function convertFiatToBlockonomicsCurrency($fiat_amount, $currency, $blockonomics_currency = 'btc')
    {
        try {
            $price = $this->fetchPrice($currency, $blockonomics_currency);
        } catch (Exception $e) {
            logModuleCall('blockonomics', 'price_api_error',
                ['currency' => $currency, 'crypto' => $blockonomics_currency],
                $e->getMessage(), $e->getMessage());
            exit("Error getting price from Blockonomics! {$e->getMessage()}");
        }
        return $this->applyMarginAndConvertToBits($fiat_amount, $price, $blockonomics_currency);
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
        $api_results = $this->fetchOrderDataParallel($order->currency, $blockonomics_currency);
        if (isset($api_results['error'])) {
            logModuleCall('blockonomics', 'checkout_api_error',
                ['currency' => $order->currency, 'crypto' => $blockonomics_currency],
                $api_results['error'], $api_results['error']);
            exit($api_results['error']);
        }

        if ($blockonomics_currency === 'usdt') {
            $order->addr = $api_results['address'] . '-' . $order->id_order;
        } else {
            $order->addr = $api_results['address'];
        }

        $order->blockonomics_currency = $blockonomics_currency;
        $order->bits = $this->applyMarginAndConvertToBits($order->value, $api_results['price'], $blockonomics_currency);
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

        if ($existing_order === null) {
            return false;
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

        if ($existing_order === null) {
            return false;
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

    // Store txhash without touching status, so a callback that arrives before monitor_tx returns can still correlate
    public function updateOrderTxidOnly($addr, $txid)
    {
        try {
            Capsule::table('blockonomics_orders')
                ->where('addr', $addr)
                ->update(['txid' => $txid]);
        } catch (Exception $e) {
            exit("Unable to update order txid in blockonomics_orders: {$e->getMessage()}");
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
     * @return array ['message' => string, 'store_info_html' => string|null]
     */
    public function testSetup()
    {
        include $this->getLangFilePath();

        $api_key = $this->getApiKey();
        if (empty($api_key)) {
            return ['message' => $_BLOCKLANG['testSetup']['emptyApi']];
        }

        // Stores API doubles as API-key validation (returns 401 on bad key)
        $stores_response = json_decode($this->getStoreSetup());

        if (!isset($stores_response)) {
            return ['message' => $_BLOCKLANG['testSetup']['blockedHttps']];
        }
        if (isset($stores_response->error)) {
            if (strpos($stores_response->error, 'Invalid API key') !== false) {
                return ['message' => $_BLOCKLANG['testSetup']['incorrectApi']];
            }
            return ['message' => $_BLOCKLANG['testSetup']['blockedHttps']];
        }

        if (empty($stores_response->data)) {
            return ['message' => $_BLOCKLANG['testSetup']['addStore']];
        }

        $gatewayParams = getGatewayVariables('blockonomics');
        $callback_url = $gatewayParams['CallbackURL'];

        $matching_store = $this->findExactMatchingStore($stores_response->data, $callback_url);
        if (!$matching_store) {
            return ['message' => $_BLOCKLANG['testSetup']['noExactMatch']];
        }

        // Save store name
        try {
            $storeName = $gatewayParams['StoreName'];
            if (!isset($storeName) || $storeName !== $matching_store->name) {
                Capsule::table('tblpaymentgateways')
                    ->updateOrInsert(
                        ['gateway' => 'blockonomics', 'setting' => 'StoreName'],
                        ['value' => $matching_store->name, 'order' => 0]
                    );
            }
        } catch (Exception $e) {
            error_log("Failed to save store name: " . $e->getMessage());
        }

        $enabled_cryptos = $this->getStoreEnabledCryptos($matching_store);
        if (empty($enabled_cryptos)) {
            return ['message' => $_BLOCKLANG['testSetup']['noCrypto']];
        }

        // Only overwrite cache after a validated exact-match store — failed setup leaves prior cache untouched
        $this->saveBlockonomicsEnabledCryptos($enabled_cryptos);

        $test_results = $this->testCryptos($enabled_cryptos);
        $all_messages = array_merge($test_results['error_messages'], $test_results['success_messages']);
        $message = !empty($all_messages) ? implode("<br>", $all_messages) : "No cryptocurrencies were tested";

        return [
            'message' => $message,
            'store_info_html' => $this->buildStoreInfoHtml($matching_store->name ?? '', $enabled_cryptos),
        ];
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
        // Inject plugin version so templates can cache-bust asset URLs (?v=...)
        $context['plugin_version'] = $this->getVersion();
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

        // Get crypto details from supported currencies
        if (is_string($crypto)) {
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
        $context = array(
            'time_period' => $time_period,
            'order' => $order,
            'order_hash' => $show_order,
            'crypto_rate_str' => $this->get_crypto_rate_from_params($order->value, $order->bits, $order->blockonomics_currency),
            'order_amount' => $order_amount,
            'payment_uri' => $this->get_payment_uri($crypto['uri'], $order->addr, $order_amount),
            'crypto' => $crypto,
        );

        if ($order->blockonomics_currency === 'usdt') {
            $address_parts = explode('-', $order->addr);
            $order_receive_address = $address_parts[0];
            $context['order_receive_address'] = $order_receive_address;
            $context['testmode'] = (strpos($order_receive_address, '0xTestUSDTAddress') === 0);
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
        logModuleCall(
            'blockonomics',
            'Get Store Setup - Request',
            ['url' => self::STORES_URL, 'method' => 'GET'],
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

    /*
     * Submit USDT txhash to Blockonomics monitor_tx, mark order waiting, write invoice note
     * Returns true on success (or idempotent re-submission), false on any failure
     */
    function process_token_order($finish_order, $crypto, $txhash) {
        include $this->getLangFilePath();

        // Validate txhash is provided
        if (empty($txhash)) {
            logModuleCall('blockonomics', 'process_token_order',
                ['finish_order' => $finish_order, 'crypto' => $crypto],
                'Error: txhash is empty', 'txhash parameter missing');
            return false;
        }

        $order = $this->processOrderHash($finish_order, $crypto);

        if (empty($order)) {
            logModuleCall('blockonomics', 'process_token_order',
                ['finish_order' => $finish_order, 'crypto' => $crypto, 'txhash' => $txhash],
                'Error: order is empty', 'Could not process order hash');
            return false;
        }

        $existing = Capsule::table('blockonomics_orders')
            ->where('txid', $txhash)
            ->first();
        if ($existing) {
            if ($existing->id_order != $order->id_order) {
                logModuleCall('blockonomics', 'process_token_order',
                    ['finish_order' => $finish_order, 'crypto' => $crypto, 'txhash' => $txhash, 'existing_id_order' => $existing->id_order, 'this_id_order' => $order->id_order],
                    'Error: txhash already used by a different order', 'txhash mismatch');
                return false;
            }
            // already monitored — idempotent; status -1 means a previous monitor_tx failed and we should retry
            if ($existing->status >= 0) {
                return true;
            }
        } else {
            $this->updateOrderTxidOnly($order->addr, $txhash);
        }

        $blockonomics_currency = $this->getSupportedCurrencies()[$crypto];

        $callback_secret = $this->getCallbackSecret();
        $callback_url = $this->getCallbackUrl($callback_secret);

        $monitor_url = self::BASE_URL . '/api/monitor_tx';
        $post_data = array(
            'txhash' => $txhash,
            'crypto' => strtoupper($crypto),
            'match_callback' => $callback_url,
        );

        logModuleCall('blockonomics', 'monitor_tx_request',
            ['url' => $monitor_url, 'data' => $post_data],
            null, null, [$this->getApiKey()]);

        try {
            $headers = [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-Type: application/json'
            ];

            $response = $this->makePostRequest($monitor_url, $post_data, $headers);

            logModuleCall('blockonomics', 'monitor_tx_response',
                ['url' => $monitor_url, 'data' => $post_data],
                $response, null, [$this->getApiKey()]);

            // txhash stays stored at status -1 on failure so callback can still correlate and retry remains possible
            if ($response['http_code'] !== 200) {
                logModuleCall('blockonomics', 'monitor_tx_error',
                    ['url' => $monitor_url, 'data' => $post_data],
                    'HTTP ' . $response['http_code'] . ': ' . $response['response'],
                    'monitor_tx API call failed', [$this->getApiKey()]);
                return false;
            }

            // Atomic -1 -> 0 transition: a fast callback may have already moved past -1, in which case we leave it alone
            $affected = Capsule::table('blockonomics_orders')
                ->where('addr', $order->addr)
                ->where('status', -1)
                ->update(['status' => 0]);

            if ($affected > 0) {
                $invoiceNote = '<b>' . $_BLOCKLANG['invoiceNote']['waiting'] . "</b>\r\r" .
                $blockonomics_currency['name'] . " transaction id:\r" .
                    '<a target="_blank" href="https://www.etherscan.io/tx/' . $txhash . '">' . $txhash . '</a>';
                $this->updateInvoiceNote($order->id_order, $invoiceNote);
            }
            return true;
        } catch (Exception $e) {
            logModuleCall('blockonomics', 'process_token_order_exception',
                ['finish_order' => $finish_order, 'crypto' => $crypto, 'txhash' => $txhash],
                $e->getMessage(), 'Exception occurred');
            return false;
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
