<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css?v={$plugin_version}">

<div class="bnomics-order-container">
  <div class="bnomics-select-container">
    <tr>
      {foreach $cryptos as $code => $crypto}
        <a href="{$WEB_ROOT}/modules/gateways/blockonomics/payment.php?show_order={$order_hash}&crypto={$code}">
          <button class="bnomics-select-options button btn btn-lg">
            <img
              src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/img/{$code}.svg"
              alt="{strtoupper($code)}"
              title="{strtoupper($code)}"
              class="bnomics-select-icon"
              width="38"
              height="38"
            />
            <span class="vertical-line">
              {$_BLOCKLANG.payWith} {$crypto['name']}
            </span>
          </button>
        </a>
      {/foreach}
    </tr>
  </div>
</div>
