<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css?v={$plugin_version}">

<div class="bnomics-order-container">
  <div class="bnomics-select-container">
    <tr>
      {foreach $cryptos as $code => $crypto}
        <a href="{$WEB_ROOT}/modules/gateways/blockonomics/payment.php?show_order={$order_hash}&crypto={$code}">
          <button class="bnomics-select-options button btn btn-lg">
            <span class="bnomics-icon-{$code} bnomics-rotate-{$code}"></span>
            <span class="vertical-line">
              {$_BLOCKLANG.payWith} {$crypto['name']}
            </span>
          </button>
        </a>
      {/foreach}
    </tr>
  </div>
</div>
