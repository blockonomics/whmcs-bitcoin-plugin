<?php

namespace Blockonomics;

use WHMCS\Database\Capsule;

class Blockonomics {

	/*
	 * Get callback secret and SystemURL to form the callback URL
	 */
	public function getCallbackUrl() {
		$secret = $this->getCallbackSecret();
		$callback_url = $this->getSystemUrl() . 'modules/gateways/callback/blockonomics.php?secret=' . $secret;
		return $callback_url;
	}

	/*
	 * Try to get callback secret from db
	 * If no secret exists, create new
	 */
	public function getCallbackSecret() {

		$secret = '';

		try {
			$secret = Capsule::table('tblpaymentgateways')
					->where('gateway', 'blockonomics')
					->where('setting', 'CallbackSecret')
					->value('value');

		} catch(\Exception $e) {
			echo "Error, could not get Blockonomics secret from database. {$e->getMessage()}";
		}

		// Check if old format of callback is still in use
		if($secret == '') {
			try {
				$secret = Capsule::table('tblpaymentgateways')
						->where('gateway', 'blockonomics')
						->where('setting', 'ApiSecret')
						->value('value');

			} catch(\Exception $e) {
				echo "Error, could not get Blockonomics secret from database. {$e->getMessage()}";
			}
			// Get only the secret from the whole Callback URL
			$secret = substr($secret, -40);
		}
			
		if($secret == '') {
			$secret = $this->generateCallbackSecret();
		}

		return $secret;
	}

	/*
	 * Generate new callback secret using sha1, save it in db under tblpaymentgateways table
	 */
	private function generateCallbackSecret() {

		try {
			$callback_secret = sha1(openssl_random_pseudo_bytes(20));

			$secret = Capsule::table('tblpaymentgateways')->insert([
				['gateway' => 'blockonomics', 'setting' => 'CallbackSecret', 'value' => $callback_secret]
			]);

		} catch(\Exception $e) {
			echo "Error, could not get Blockonomics secret from database. {$e->getMessage()}";
		}

		return $callback_secret;
	}

	/*
	 * Get user configured API key from database
	 */
	public function getApiKey() {
		return Capsule::table('tblpaymentgateways')
			->where('gateway', 'blockonomics')
			->where('setting', 'ApiKey')
			->value('value');
	}

	/*
	 * Get user configured API key from database
	 */
	public function getAltcoins() {
		return Capsule::table('tblpaymentgateways')
			->where('gateway', 'blockonomics')
			->where('setting', 'Altcoins')
			->value('value');
	}

	/*
	 * Get user configured Time Period from database
	 */
	public function getTimePeriod() {
		return Capsule::table('tblpaymentgateways')
			->where('gateway', 'blockonomics')
			->where('setting', 'TimePeriod')
			->value('value');
	}

	/*
	 * Get user configured Confirmations from database
	 */
	public function getConfirmations() {
		$confirmations = Capsule::table('tblpaymentgateways')
			->where('gateway', 'blockonomics')
			->where('setting', 'Confirmations')
			->value('value');
		if(isset($confirmations)){
			return $confirmations;
		}
		return 2;
	}

	/*
	 * Update order status to 'Waiting for Bitcoin Confirmation'
	 */
	public function updateOrderStatus($orderId, $status) {
		Capsule::table('tblorders')
			->where('id', $orderId)
			->update(['status' => $status]);
	}

	/*
	 * Update invoice status
	 */
	public function updateInvoiceStatus($invoiceId, $status) {
		Capsule::table('tblinvoices')
			->where('id', $invoiceId)
			->update(['status' => $status]);
	}

	/*
	 * Update order note
	 */
	public function updateOrderNote($orderId, $note) {
		Capsule::table('tblorders')
			->where('id', $orderId)
			->update(['notes' => $note]);
	}

	/*
	 * Update invoice note
	 */
	public function updateInvoiceNote($invoiceid, $note) {
		Capsule::table('tblinvoices')
			->where('id', $invoiceid)
			->update(['notes' => $note]);
	}

	/*
	 * Get order id by invoice id
	 */
	public function getOrderIdByInvoiceId($invoiceId) {
		return Capsule::table('tblorders')
			->where('invoiceid', $invoiceId)
			->value('id');
	}

	/*
	 * Get the BTC price that was calculated when the order price was last updated
	 */
	public function getPriceByExpected($invoiceId) {
		$query = Capsule::table('blockonomics_bitcoin_orders')
			->where('id_order', $invoiceId)
			->select('value');
		$prices = $query->addSelect('bits')->get();
		$fiat = $prices[0]->value;
		$btc = $prices[0]->bits / 1.0e8;
		$btc_price = $fiat / $btc;
		return round($btc_price, 2);
	}

	/*
	 * Get underpayment slack
	 */
	public function getUnderpaymentSlack() {
		return Capsule::table('tblpaymentgateways')
			->where('gateway', 'blockonomics')
			->where('setting', 'Slack')
			->value('value');
	}

	/*
	 * Get new address from Blockonomics Api
	 */
	public function getNewBitcoinAddress($reset=false) {

		$api_key = $this->getApiKey();
		$callback_secret = $this->getCallbackSecret();

		if($reset) {
				$get_params = "?match_callback=$callback_secret&reset=1";
		} 
		else {
				$get_params = "?match_callback=$callback_secret";
		}

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "https://www.blockonomics.co/api/new_address" . $get_params);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

		$header = "Authorization: Bearer " . $api_key;
		$headers = array();
		$headers[] = $header;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$contents = curl_exec($ch);
		if (curl_errno($ch)) {
				echo 'Error:' . curl_error($ch);
		}

		$responseObj = json_decode($contents);
		//Create response object if it does not exist
		if (!isset($responseObj)) $responseObj = new \stdClass();
		$responseObj->{'response_code'} = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);
		return $responseObj;
	}
	/*
	 * Get user configured margin from database
	 */
	public function getMargin() {
		return Capsule::table('tblpaymentgateways')
			->where('gateway', 'blockonomics')
			->where('setting', 'Margin')
			->value('value');
	}
	/*
	 * Convert fiat amount to BTC
	 */
	public function getBitcoinAmount($fiat_amount, $currency) {
		try {
			if($currency == 'mBTC'){
				$price = 1000;
			}elseif($currency == 'BTC'){
				$price = 1;
			}else{
				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, "https://www.blockonomics.co/api/price?currency=".$currency);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

				$contents = curl_exec($ch);
				if (curl_errno($ch)) {
						echo 'Error:' . curl_error($ch);
				}
				curl_close ($ch);
				$price = json_decode($contents)->price;
			}
			$margin = floatval($this->getMargin());
			if($margin > 0){
				$price = $price * 100/(100+$margin);
			}
		} catch (\Exception $e) {
			echo "Error getting price from Blockonomics! {$e->getMessage()}";
		}

		return intval(1.0e8 * $fiat_amount/$price);
	}

	/*
	 * If no Blockonomics order table exists, create it
	 */
	public function createOrderTableIfNotExist() {

		if (!Capsule::schema()->hasTable('blockonomics_bitcoin_orders')) {

			try {
				Capsule::schema()->create( 'blockonomics_bitcoin_orders', function ($table) {
							$table->increments('id');
							$table->integer('id_order');
							$table->text('txid');
							$table->integer('timestamp');
							$table->text('addr');
							$table->integer('status');
							$table->float('value');
							$table->integer('bits');
							$table->integer('bits_payed');
							$table->text('flyp_id');
						}
				);
			} catch (\Exception $e) {
					echo "Unable to create blockonomics_bitcoin_orders: {$e->getMessage()}";
			}
		}else if(!Capsule::schema()->hasColumn('blockonomics_bitcoin_orders', 'flyp_id')){
			 Capsule::schema()->table('blockonomics_bitcoin_orders', function($table){
				$table->text('flyp_id');
			 });
		}
	}
	/*
	 * Try to insert new order to database
	 * If order exists, return with false
	 */
	public function insertOrderToDb($id_order, $address, $value, $bits) {

		try {
			$existing_order = Capsule::table('blockonomics_bitcoin_orders')
				->where('id_order', $id_order)
				->value('id');
		} catch (\Exception $e) {
				echo "Unable to select order from blockonomics_bitcoin_orders: {$e->getMessage()}";
		}

		if($existing_order) {
			return false;
		}

		try {
			Capsule::table('blockonomics_bitcoin_orders')->insert(
				[
					'id_order' => $id_order,
					'addr' => $address,
					'timestamp' => time(),
					'status' => -1,
					'value' => $value,
					'bits' => $bits,
				]
			);
		} catch (\Exception $e) {
				echo "Unable to insert new order into blockonomics_bitcoin_orders: {$e->getMessage()}";
		}

		return true;
	}

	/*
	 * Try to get order row from db by address
	 */
	public function getOrderByAddress($bitcoinAddress) {
		try {
			$existing_order = Capsule::table('blockonomics_bitcoin_orders')
				->where('addr', $bitcoinAddress)
				->orderBy('timestamp', 'desc')
				->first();
		} catch (\Exception $e) {
				echo "Unable to select order from blockonomics_bitcoin_orders: {$e->getMessage()}";
		}

		$row_in_array = array(
			"id" => $existing_order->id,
			"order_id" => $existing_order->id_order,
			"timestamp"=> $existing_order->timestamp,
			"status" => $existing_order->status,
			"value" => $existing_order->value,
			"bits" => $existing_order->bits,
			"bits_payed" => $existing_order->bits_payed
		);

		return $row_in_array;
	}

	/*
	 * Try to get order row from db by uuid
	 */
	public function getOrderByUuid($uuid) {
		try {
			$existing_order = Capsule::table('blockonomics_bitcoin_orders')
				->where('flyp_id', $uuid)
				->orderBy('timestamp', 'desc')
				->first();
		} catch (\Exception $e) {
				echo "Unable to select order from blockonomics_bitcoin_orders: {$e->getMessage()}";
		}

		$row_in_array = array(
			"id" => $existing_order->id,
			"order_id" => $existing_order->id_order,
			"timestamp"=> $existing_order->timestamp,
			"address" => $existing_order->addr,
			"status" => $existing_order->status,
			"value" => $existing_order->value,
			"bits" => $existing_order->bits,
			"bits_payed" => $existing_order->bits_payed
		);

		return $row_in_array;
	}

	/*
	 * Try to get order row from db by order id
	 */
	public function getOrderById($orderId) {
		try {
			$existing_order = Capsule::table('blockonomics_bitcoin_orders')
				->where('id_order', $orderId)
				->orderBy('timestamp', 'desc')
				->first();
		} catch (\Exception $e) {
				echo "Unable to select order from blockonomics_bitcoin_orders: {$e->getMessage()}";
		}

		$row_in_array = array(
			"id" => $existing_order->id,
			"order_id" => $existing_order->id_order,
			"address"=> $existing_order->addr,
			"bits" => $existing_order->bits,
			"status" => $existing_order->status,
			"txid" => $existing_order->txid,
			"timestamp" => $existing_order->timestamp
		);

		return $row_in_array;
	}

	/*
	 * Try to get order row from db by uuid
	 */
	public function updateFlypIdInDb($orderId, $flypId) {
		try {
			Capsule::table('blockonomics_bitcoin_orders')
					->where('id_order', $orderId)
					->update([
						'flyp_id' => $flypId
					]
				);
			} catch (\Exception $e) {
				echo "Unable to update flyp id to blockonomics_bitcoin_orders: {$e->getMessage()}";
		}
	}

	/*
	 * Update existing order information. Use BTC payment address as key
	 */
	public function updateOrderInDb($addr, $txid, $status, $bits_payed) {
		try {
			Capsule::table('blockonomics_bitcoin_orders')
					->where('addr', $addr)
					->update([
						'txid' => $txid,
						'status' => $status,
						'bits_payed' => $bits_payed
					]
				);
			} catch (\Exception $e) {
				echo "Unable to update order to blockonomics_bitcoin_orders: {$e->getMessage()}";
		}
	}

	/*
	 * Update existing order's expected amount and FIAT amount. Use WHMCS invoice id as key
	 */
	public function updateOrderExpected($id_order, $timestamp, $expected, $fiat_amount) {
		try {
			Capsule::table('blockonomics_bitcoin_orders')
					->where('id_order', $id_order)
					->update([
						'bits' => $expected,
						'timestamp' => $timestamp,
						'value' => $fiat_amount
					]
				);
			} catch (\Exception $e) {
				echo "Unable to update order to blockonomics_bitcoin_orders: {$e->getMessage()}";
		}
	}

	/*
	 * Update existing order's address. Set status, txid and bits_payed to default values. Use WHMCS invoice id as key
	 */
	public function updateOrderAddress($id_order, $address) {
		try {
			Capsule::table('blockonomics_bitcoin_orders')
					->where('id_order', $id_order)
					->update([
						'addr' => $address,
						'status' => -1,
						'txid' => null,
						'bits_payed' => null
					]
				);
			} catch (\Exception $e) {
				echo "Unable to update order to blockonomics_bitcoin_orders: {$e->getMessage()}";
		}
	}

	/*
	 * Get URL of the WHMCS installation
	 */
	public function getSystemUrl() {
		return Capsule::table('tblconfiguration')
				->where('setting', 'SystemURL')
				->value('value');
	}

	public function checkForErrors($responseObj) {
		if(!isset($responseObj->response_code)) {
				$error = true;
		} else {
				switch ($responseObj->response_code) {
					case '200':
							break;
					default:
							$error = true;
							break;
				}
		}
		if(isset($error)) {
			return $error;
		}
		// No errors
		return false;
	}

 public function doCurlCall($url, $post_content='') {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if ($post_content)
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_content);
		}
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '. $this->getApiKey(),
				'Content-type: application/x-www-form-urlencoded'
			));
		$data = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$responseObj = new \stdClass();
		$responseObj->data = json_decode($data);
		$responseObj->response_code = $httpcode;
		return $responseObj;
	}


	public function testSetup($new_api)	{

		$xpub_fetch_url = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
		$set_callback_url = 'https://www.blockonomics.co/api/update_callback';
		$error_str = '';

		$response = $this->doCurlCall($xpub_fetch_url);

		$callback_url = $this->getCallbackUrl();
		$api_key = $this->getApiKey();
		if ($api_key != $new_api) {
			$error_str = 'New API Key: Save your changes and then click \'Test Setup\'';//API key changed
		}
		elseif (!isset($response->response_code)) {
			$error_str = 'Your server is blocking outgoing HTTPS calls';
		}
		elseif ($response->response_code==401)
			$error_str = 'API Key is incorrect';
		elseif ($response->response_code!=200)
			$error_str = $response->data;
		elseif (!isset($response->data) || count($response->data) == 0)
		{
			$error_str = 'You have not entered an xpub';
		}
		elseif (count($response->data) == 1)
		{
			if(!$response->data[0]->callback || $response->data[0]->callback == null)
			{
				//No callback URL set, set one 
				$post_content = '{"callback": "'.$callback_url.'", "xpub": "'.$response->data[0]->address.'"}';
				$this->doCurlCall($set_callback_url, $post_content);  
			}
			elseif($response->data[0]->callback != $callback_url)
			{
				// Check if only secret differs
				$base_url = substr($callback_url, 0, -48);
				if(strpos($response->data[0]->callback, $base_url) !== false)
				{
					//Looks like the user regenrated callback by mistake
					//Just force Update_callback on server
					$post_content = '{"callback": "'.$callback_url.'", "xpub": "'.$response->data[0]->address.'"}';
					$this->doCurlCall($set_callback_url, $post_content);  
				}
				else
					$error_str = "Your have an existing callback URL. Refer instructions on integrating multiple websites";
			}
		}
		else 
		{
			$error_str = "Your have an existing callback URL or multiple xPubs. Refer instructions on integrating multiple websites";

			foreach ($response->data as $resObj)
				if($resObj->callback == $callback_url)
					// Matching callback URL found, set error back to empty
					$error_str = '';
		}

		if ($error_str == '') {
			// Test new address generation
			$new_addresss_response = $this->getNewBitcoinAddress(true);
			if ($new_addresss_response->status != 200){
				$error_str = $new_addresss_response->message;
			}
		}

		return $error_str;
	}
}