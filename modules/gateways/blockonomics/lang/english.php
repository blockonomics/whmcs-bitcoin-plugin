<?php
if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

// new todo:
$_BLOCKLANG['updateIn'] = 'updates in';

// Checkout pages
$_BLOCKLANG['orderId'] = 'Order #';
$_BLOCKLANG['error']['btc']['title'] = 'Could not generate new Bitcoin address.';
$_BLOCKLANG['error']['btc']['message'] = 'Note to webmaster: Please login to admin and go to Setup > Payments > Payment Gateways > Manage Existing Gateways and use the Test Setup button to diagnose the error.';
$_BLOCKLANG['error']['usdt']['title'] = 'Could not generate new USDT address.';
$_BLOCKLANG['error']['usdt']['message'] = 'Note to webmaster: Please login to admin and go to Setup > Payments > Payment Gateways > Manage Existing Gateways and use the Test Setup button to diagnose the error.';
$_BLOCKLANG['error']['bch']['title'] = 'Could not generate new Bitcoin Cash address.';
$_BLOCKLANG['error']['bch']['message'] = 'Note to webmaster: Please login to admin and go to Setup > Payments > Payment Gateways > Manage Existing Gateways and use the Test Setup button to diagnose the error.';
$_BLOCKLANG['error']['pending']['title'] = 'Payment is pending';
$_BLOCKLANG['error']['pending']['message'] = 'Additional payments to invoice are only allowed after current pending transaction is confirmed.';
$_BLOCKLANG['error']['addressGeneration']['title'] = 'Could not generate new address';
$_BLOCKLANG['payWith'] = 'Pay With';
$_BLOCKLANG['paymentExpired'] = 'Payment Expired';
$_BLOCKLANG['tryAgain'] = 'Click here to try again';
$_BLOCKLANG['paymentError'] = 'Payment Error';
$_BLOCKLANG['openWallet'] = 'Open in wallet';
$_BLOCKLANG['payAmount1'] = 'To pay, send ';
$_BLOCKLANG['payAmount2'] = ' to this adress';
$_BLOCKLANG['payAddress1'] = 'Amount of '; 
$_BLOCKLANG['payAddress2'] = ' to send';
$_BLOCKLANG['copyClipboard'] = 'Copied to clipboard';
$_BLOCKLANG['howToPay'] = 'How do I pay?';
$_BLOCKLANG['poweredBy'] = 'Powered by Blockonomics';
$_BLOCKLANG['noCrypto']['title'] = 'No crypto currencies are enabled for checkout';
$_BLOCKLANG['noCrypto']['message'] = 'Note to webmaster: Can be enabled via Setup > Payments > Payment Gateways > Blockonomics > Currencies';

// Callback
$_BLOCKLANG['error']['secret'] = 'Secret verification failure';
$_BLOCKLANG['invoiceNote']['waiting'] = 'Invoice will be automatically marked as paid on transaction confirm by the network. No further action is required.';

// Admin Menu
$_BLOCKLANG['storeName']['title'] = 'Store Name';
$_BLOCKLANG['storeName']['description'] = 'Your Blockonomics store name';
$_BLOCKLANG['version']['title'] = 'Version';
$_BLOCKLANG['apiKey']['title'] = 'API Key';
$_BLOCKLANG['apiKey']['description'] = 'Get your API Key from <a target="_blank" href="https://www.blockonomics.co/dashboard#/store">Blockonomics Dashboard</a>';
$_BLOCKLANG['enabled']['title'] = 'Enabled';
$_BLOCKLANG['enabled']['btc_description'] = 'To configure click <b>Get Started for Free</b> on <a target="_blank" href="https://blockonomics.co/merchants">https://blockonomics.co/merchants</a>';
$_BLOCKLANG['enabled']['bch_title'] = 'Enable BCH';
$_BLOCKLANG['enabled']['bch_description'] = 'Allow customers to pay with Bitcoin Cash';
$_BLOCKLANG['enabled']['usdt_title'] = 'Enable USDT';
$_BLOCKLANG['enabled']['usdt_description'] = 'Allow customers to pay with USDT';
$_BLOCKLANG['callbackSecret']['title'] = 'Callback Secret';
$_BLOCKLANG['callbackUrl']['title'] = 'Callback URL';
$_BLOCKLANG['AvancedSettings']['title'] = 'Advanced Settings ▼';
$_BLOCKLANG['timePeriod']['title'] = 'Time Period';
$_BLOCKLANG['timePeriod']['description'] = 'Time period of countdown timer on payment page (in minutes)';
$_BLOCKLANG['margin']['title'] = 'Extra Currency<br>Rate Margin %';
$_BLOCKLANG['margin']['description'] = 'Increase live fiat to BTC rate by small percent';
$_BLOCKLANG['slack']['title'] = 'Underpayment<br> Slack %';
$_BLOCKLANG['slack']['description'] = 'Allow payments that are off by a small percentage';
$_BLOCKLANG['confirmations']['title'] = 'Confirmations';
$_BLOCKLANG['confirmations']['description'] = 'Network Confirmations required for payment to complete';
$_BLOCKLANG['confirmations']['recommended'] = 'recommended';
$_BLOCKLANG['checkoutMode']['title'] = 'USDT Checkout Mode';
$_BLOCKLANG['checkoutMode']['mainnet'] = 'Production (Ethereum Mainnet)';
$_BLOCKLANG['checkoutMode']['testnet'] = 'Sandbox (Sepolia Testnet)';

// Test Setup
$_BLOCKLANG['testSetup']['systemUrl']['error'] = 'Unable to locate/execute';
$_BLOCKLANG['testSetup']['systemUrl']['fix'] = 'Check your WHMCS System URL. Go to Setup > General Settings and verify your WHMCS System URL';
$_BLOCKLANG['testSetup']['success']['btc'] = 'BTC ✅';
$_BLOCKLANG['testSetup']['success']['usdt'] = 'USDT ✅';
$_BLOCKLANG['testSetup']['protocol']['error'] = 'Error: System URL has a different protocol than current URL.';
$_BLOCKLANG['testSetup']['protocol']['fix'] = 'Go to Setup > General Settings and verify that WHMCS System URL has correct protocol set (HTTP or HTTPS).';
$_BLOCKLANG['testSetup']['testing'] = 'Testing setup...';
$_BLOCKLANG['testSetup']['blockedHttps'] = 'Your server is blocking outgoing HTTPS calls';
$_BLOCKLANG['testSetup']['emptyApi'] = 'API Key is not set. Please enter your API Key.';
$_BLOCKLANG['testSetup']['incorrectApi'] = 'API Key is incorrect';
$_BLOCKLANG['testSetup']['addStore'] = 'Please add a <a href="https://www.blockonomics.co/dashboard#/store" target="_blank"><i>Store</i></a> on Blockonomics Dashboard';
$_BLOCKLANG['testSetup']['addWallet'] = 'Please add a <a href="https://www.blockonomics.co/dashboard#/wallet" target="_blank"><i>Wallet</i></a> on Blockonomics Dashboard';
$_BLOCKLANG['testSetup']['invalidResponse'] = 'Invalid response was received. Please retry.';
$_BLOCKLANG['testSetup']['noCrypto'] = 'Please enable Payment method on <a href="https://www.blockonomics.co/dashboard#/store" target="_blank"><i>Stores</i></a>';
$_BLOCKLANG['testSetup']['setCallback'] = 'Please copy Callback URL from Advanced Settings and paste it as your <a href="https://www.blockonomics.co/dashboard#/store" target="_blank">Store Callback URL</a>.';
