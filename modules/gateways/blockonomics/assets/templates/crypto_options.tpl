<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css">

<div class="bnomics-order-container">
  <div class="bnomics-select-container">
    <table width="100%">
      <tr>
          {foreach $active_currencies as $crypto}
            <td class="bnomics-select-options">
              <a href="{$WEB_ROOT}/modules/gateways/blockonomics/payment.php?show_order={$order_hash}&crypto={$crypto['code']}" style="color: inherit; text-decoration: inherit;">
                <p>
                  {$_BLOCKLANG.payWith}
                </p>
                <span class="bnomics-icon-{$crypto['code']} bnomics-rotate-{$crypto['code']}"></span>
                  {$crypto['name']}<br>
                  <b>{$crypto['code']}</b>
                </p>
              </a>
            </td>
          {/foreach}
      </tr>
    </table>
  </div>
</div>