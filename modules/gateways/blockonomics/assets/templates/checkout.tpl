<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css">

<div id="active_cryptos" data-active_cryptos='{$active_currencies}'></div>
<div id="time_period" data-time_period="{$time_period}"></div>

<div ng-app="BlockonomicsApp">
  <div ng-controller="CheckoutController">
    <div class="bnomics-order-container">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <div class="bnomics-order-id">
            <span class="bnomics-order-number" ng-cloak>Order #[[order.id_order]]</span>
          </div>
        </div>
      </div>
      <!-- Spinner -->
      <div class="bnomics-spinner-wrapper" ng-show="spinner" ng-cloak>
        <div class="bnomics-spinner"></div>
      </div>
      <!-- Display Error -->
      <div id="display-error" class="bnomics-display-error" ng-hide="no_display_error">
        <h2>Display Error</h2>
        <p>Unable to render correctly, Note to Administrator: Please enable lite mode in the Blockonomics plugin.</p>
      </div>
      <!-- Address Error -->
      <div id="address-error-btc" ng-show="address_error_btc" ng-cloak>
        <h2>Could not generate new Bitcoin address</h2>
        <p>Note to webmaster: Please login to your admin panel, navigate to Settings > Blockonomics > Currencies and click <i>Test Setup</i> to diagnose the issue.</p>
      </div>
      <!-- Gap limit Error -->
      <div id="address-error-btc-gaplimit" ng-show="btc_gaplimit_error" ng-cloak>
        <h2>Could not generate new Bitcoin address</h2>
       <p>Note to webmaster: [[btc_gaplimit_error]].</p>
      </div>
      <!-- BCH Address Error -->
      <div id="address-error-bch" ng-show="address_error_bch" ng-cloak>
        <h2>Could not generate new Bitcoin Cash address</h2>
        <p>Note to webmaster: Please follow the instructions <a href="https://help.blockonomics.co/en/support/solutions/articles/33000253348-bch-setup-on-woocommerce" target="_blank">here</a> to configure BCH payments.</p>
      </div>
      <!-- Payment Expired -->
      <div class="bnomics-order-expired-wrapper" ng-show="order.status == -3" ng-cloak>
        <h3 class="warning bnomics-status-warning">Payment Expired</h3><br>
        <p><a ng-click="try_again_click()">Click here to try again</a></p>
      </div>
      <!-- Payment Error -->
      <div class="bnomics-order-error-wrapper" ng-show="order.status == -2" ng-cloak>
        <h3 class="warning bnomics-status-warning">Paid order BTC amount is less than expected. Contact merchant</h3>
      </div>
      <!-- Blockonomics Checkout Panel -->
      <div class="bnomics-order-panel" ng-show="order.status == -1" ng-cloak>
        <div class="bnomics-order-info">
          <div class="bnomics-bitcoin-pane">
            <div class="bnomics-btc-info">
              <!-- Left Side -->
              <!-- QR and Open in wallet -->
              <div class="bnomics-qr-code">
                <div class="bnomics-qr">
                  <a href="[[crypto.uri]]:[[order.addr]]?amount=[[order.bits/1.0e8]]" target="_blank">
                    <qrcode data="[[crypto.uri]]:[[order.addr]]?amount=[[order.bits/1.0e8]]" size="160" version="6">
                      <canvas class="qrcode"></canvas>
                    </qrcode>
                  </a>
                </div>
                <div class="bnomics-qr-code-hint"><a href="[[crypto.uri]]:[[order.addr]]?amount=[[order.bits/1.0e8]]" target="_blank">Open in wallet</a></div>
              </div>
              <!-- Right Side -->
              <div class="bnomics-amount">
                <div class="bnomics-bg">
                  <!-- Order Amounts -->
                  <div class="bnomics-amount">
                    <div class="bnomics-amount-text" ng-hide="amount_copyshow" ng-cloak>To pay, send exactly this [[crypto.code | uppercase]] amount</div>
                    <div class="bnomics-copy-amount-text" ng-show="amount_copyshow" ng-cloak>Copied to clipboard</div>
                    <ul ng-click="blockonomics_amount_click()" id="bnomics-amount-input" class="bnomics-amount-input">
                        <li id="bnomics-amount-copy">[[order.bits/1.0e8]]</li>
                        <li>[[crypto.code | uppercase]]</li>
                        <li class="bnomics-grey"> ≈ </li>
                        <li class="bnomics-grey">[[order.value]]</li>
                        <li class="bnomics-grey">[[order.currency]]</li>
                    </ul>
                  </div>
                  <!-- Order Address -->
                  <div class="bnomics-address">
                    <div class="bnomics-address-text" ng-hide="address_copyshow" ng-cloak>To this [[crypto.name | lowercase]] address</div>
                    <div class="bnomics-copy-address-text" ng-show="address_copyshow" ng-cloak>Copied to clipboard</div>
                    <ul ng-click="blockonomics_address_click()" id="bnomics-address-input" class="bnomics-address-input">
                          <li id="bnomics-address-copy">[[order.addr]]</li>
                    </ul>
                  </div>
                  <!-- Order Countdown Timer -->
                  <div class="bnomics-progress-bar-wrapper">
                    <div class="bnomics-progress-bar-container">
                      <div class="bnomics-progress-bar" style="width: [[progress]]%;"></div>
                    </div>
                  </div>
                  <span class="ng-cloak bnomics-time-left">[[clock*1000 | date:'mm:ss' : 'UTC']] min</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Blockonomics How to pay + Credit -->
      <div class="bnomics-powered-by">
        <a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank">How do I pay? | Check reviews of this shop</a><br>
        <div class="bnomics-powered-by-text bnomics-grey">{$_BLOCKLANG.poweredBy}</div>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/angular.min.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/angular-resource.min.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/app.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/qrcode-generator/qrcode.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/qrcode-generator/qrcode_UTF8.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/angular-qrcode/angular-qrcode.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/reconnecting-websocket.min.js"></script>