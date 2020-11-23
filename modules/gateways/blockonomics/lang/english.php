<?php
if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}
// Checkout pages
$_BLOCKLANG['orderId'] = 'Order #';
$_BLOCKLANG['error']['btc']['title'] = 'Could not generate new Bitcoin address.';
$_BLOCKLANG['error']['btc']['message'] = 'Note to webmaster: Please login to admin and go to Setup > Payments > Payment Gateways > Manage Existing Gateways and use the Test Setup button to diagnose the error.';
$_BLOCKLANG['error']['bch']['title'] = 'Could not generate new Bitcoin Cash address.';
$_BLOCKLANG['error']['bch']['message'] = 'Note to webmaster: Please follow the instructions <a href="https://help.blockonomics.co/support/solutions/articles/33000251576-bch-setup-on-whmcs" target="_blank">here</a> to configure BCH payments.';
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
$_BLOCKLANG['error']['mising_params'] = 'The URL is mising requiered parameters';
$_BLOCKLANG['invoiceNote']['waiting'] = 'Waiting for Confirmation on';
$_BLOCKLANG['invoiceNote']['network'] = 'network';

// Admin Menu
$_BLOCKLANG['version']['title'] = 'Version';
$_BLOCKLANG['apiKey']['title'] = 'API Key';
$_BLOCKLANG['apiKey']['description'] = 'BLOCKONOMICS API KEY (Click "Get Started For Free" on <a target="_blank" href="https://www.blockonomics.co/blockonomics#/merchants">Merchants</a> and follow setup wizard)';
$_BLOCKLANG['enabled']['title'] = 'Enabled';
$_BLOCKLANG['enabled']['description'] = 'Select if you want to accept';
$_BLOCKLANG['callbackSecret']['title'] = 'Callback Secret';
$_BLOCKLANG['callbackUrl']['title'] = 'Callback URL';
$_BLOCKLANG['timePeriod']['title'] = 'Time Period';
$_BLOCKLANG['timePeriod']['description'] = 'Time period of countdown timer on payment page (in minutes)';
$_BLOCKLANG['margin']['title'] = 'Extra Currency Rate Margin %';
$_BLOCKLANG['margin']['description'] = 'Increase live fiat to BTC rate by small percent';
$_BLOCKLANG['slack']['title'] = 'Underpayment Slack %';
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
$_BLOCKLANG['testSetup']['newApi'] = "New API Key: Save your changes and then click 'Test Setup'";
$_BLOCKLANG['testSetup']['blockedHttps'] = 'Your server is blocking outgoing HTTPS calls';
$_BLOCKLANG['testSetup']['incorrectApi'] = 'API Key is incorrect';
$_BLOCKLANG['testSetup']['noXpub'] = 'You have not entered an xpub';
$_BLOCKLANG['testSetup']['existingCallbackUrl'] = 'Your have an existing callback URL. Refer instructions on integrating multiple websites';
$_BLOCKLANG['testSetup']['multipleXpubs'] = 'Your have an existing callback URL or multiple xPubs. Refer instructions on integrating multiple websites';
