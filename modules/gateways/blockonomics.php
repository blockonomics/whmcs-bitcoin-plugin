<?php

function blockonomics_config() {
  return array(
    'FriendlyName' => array(
      'Type'       => 'System',
      'Value'      => 'Blockonomics'
    ),
    'ApiKey' => array(
      'FriendlyName' => 'API Key',
      'Description'  => 'API Key from Blockonomics API Apps.',
      'Type'         => 'text'
    ),
    'ApiSecret' => array(
      'FriendlyName' => 'API Secret',
      'Description'  => 'API Secret from Blockonomics API Apps.',
      'Type'         => 'text'
    )
  );
}

function blockonomics_link($params) {
  if (false === isset($params) || true === empty($params)) {
    die('[ERROR] In modules/gateways/Blockonomics.php::Blockonomics_link() function: Missing or invalid $params data.');
  }

  $blockonomics_params = array(
    'order_id'         => $params['invoiceid'],
    'price'            => number_format($params['amount'], 2, '.', ''),
    'currency'         => $params['currency'],
    'receive_currency' => $params['ReceiveCurrency'],
    'cancel_url'       => $params['systemurl'] . '/clientarea.php',
    'callback_url'     => $params['systemurl'] . '/modules/gateways/callback/Blockonomics.php',
    'success_url'      => $params['systemurl'] . '/viewinvoice.php?id=' . $params['invoiceid'],
    'title'            => $params['companyname'],
    'description'      => $params['description']
  );

  $authentication = array(
    'app_id' => $params['AppID'],
    'api_key' => $params['ApiKey'],
    'api_secret' => $params['ApiSecret'],
    'environment' => $params['Environment'],
    'user_agent' => 'Blockonomics - WHMCS Extension',
  );

  $order = \Blockonomics\Merchant\Order::createOrFail($blockonomics_params, array(), $authentication);

  $form = '<form action="' . $order->payment_url . '" method="GET">';
  $form .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
  $form .= '</form>';

  return $form;
}