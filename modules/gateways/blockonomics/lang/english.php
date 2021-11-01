<?php
if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}
// Checkout pages
$_BLOCKLANG['orderId'] = 'Order #';
$_BLOCKLANG['error']['btc']['title'] = 'Could not generate new Bitcoin address.';
$_BLOCKLANG['error']['btc']['message'] = 'Note to webmaster: Please login to admin and go to Setup > Payments > Payment Gateways > Manage Existing Gateways and use the Test Setup button to diagnose the error.';
$_BLOCKLANG['error']['bch']['title'] = 'Could not generate new Bitcoin Cash address.';
$_BLOCKLANG['error']['bch']['message'] = 'Note to webmaster: Please login to admin and go to Setup > Payments > Payment Gateways > Manage Existing Gateways and use the Test Setup button to diagnose the error.';
$_BLOCKLANG['error']['pending']['title'] = 'Payment is pending';
$_BLOCKLANG['error']['pending']['message'] = 'Additional payments to invoice are only allowed after current pending transaction is confirmed.';
$_BLOCKLANG['payWith'] = 'Pay With';
$_BLOCKLANG['paymentExpired'] = 'Payment Expired';
$_BLOCKLANG['tryAgain'] = 'Click here to try again';
$_BLOCKLANG['paymentError'] = 'Payment Error';
$_BLOCKLANG['openWallet'] = 'Open in wallet';
$_BLOCKLANG['payAmount'] = 'To pay, send exactly this [[currency.code | uppercase]] amount';
$_BLOCKLANG['payAddress'] = 'To this [[currency.name | lowercase]] address';
$_BLOCKLANG['copyClipboard'] = 'Copied to clipboard';
$_BLOCKLANG['howToPay'] = 'How do I pay?';
$_BLOCKLANG['poweredBy'] = 'Powered by Blockonomics';

// Callback
$_BLOCKLANG['error']['secret'] = 'Secret verification failure';
$_BLOCKLANG['invoiceNote']['waiting'] = 'Waiting for Confirmation on';
$_BLOCKLANG['invoiceNote']['network'] = 'network';

// Admin Menu
$_BLOCKLANG['version']['title'] = 'Version';
$_BLOCKLANG['apiKey']['title'] = 'API Key';
$_BLOCKLANG['apiKey']['description'] = 'To get your API Key, click <b>Get Started for Free</b> on <a target="_blank" href="https://blockonomics.co/merchants">https://blockonomics.co/merchants</a>';
$_BLOCKLANG['enabled']['title'] = 'Enabled';
$_BLOCKLANG['enabled']['btc_description'] = 'To configure click <b>Get Started for Free</b> on <a target="_blank" href="https://blockonomics.co/merchants">https://blockonomics.co/merchants</a>';
$_BLOCKLANG['enabled']['bch_description'] = 'To configure click <b>Get Started for Free</b> on <a target="_blank" href="https://bch.blockonomics.co/merchants">https://bch.blockonomics.co/merchants</a>';
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

// Test Setup
$_BLOCKLANG['testSetup']['systemUrl']['error'] = 'Unable to locate/execute';
$_BLOCKLANG['testSetup']['systemUrl']['fix'] = 'Check your WHMCS System URL. Go to Setup > General Settings and verify your WHMCS System URL';
$_BLOCKLANG['testSetup']['success'] = 'Congrats! Setup is all done';
$_BLOCKLANG['testSetup']['protocol']['error'] = 'Error: System URL has a different protocol than current URL.';
$_BLOCKLANG['testSetup']['protocol']['fix'] = 'Go to Setup > General Settings and verify that WHMCS System URL has correct protocol set (HTTP or HTTPS).';
$_BLOCKLANG['testSetup']['testing'] = 'Testing setup...';
$_BLOCKLANG['testSetup']['blockedHttps'] = 'Your server is blocking outgoing HTTPS calls';
$_BLOCKLANG['testSetup']['emptyApi'] = 'API Key is not set. Please enter your API Key.';
$_BLOCKLANG['testSetup']['incorrectApi'] = 'API Key is incorrect';
$_BLOCKLANG['testSetup']['addStore'] = 'Please add a new store on blockonomics website';
