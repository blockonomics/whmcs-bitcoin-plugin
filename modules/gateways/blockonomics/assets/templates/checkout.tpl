<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css">

<div id="system-url" data-url="{$systemurl}"></div>
<div id="time_period" data-time_period="{$time_period}"></div>
<div id="active_currencies" data-active_currencies='{$active_currencies}'></div>
<div id="order_uuid" data-order_uuid="{$order_uuid}"></div>
<div id="order_id" data-order_id="{$order_id}"></div>

<div ng-app="shopping-cart-demo">
    <div ng-controller="CheckoutController">
        <div class="bnomics-order-container">
            <!-- Heading row -->
            <div class="bnomics-order-heading">
                <div class="bnomics-order-heading-wrapper">
                    <div class="bnomics-order-id">
                        <span class="bnomics-order-number" ng-cloak> {$_BLOCKLANG.orderId}[[order_id]]</span>
                    </div>
                </div>
            </div>
            <!-- Spinner -->
            <div class="bnomics-spinner-wrapper" ng-show="spinner" ng-cloak>
                <div class="bnomics-spinner"></div>
            </div>
            <!-- Address Error -->
            <div id="address-error-btc" ng-show="address_error_btc" ng-cloak>
                <h2>{$_BLOCKLANG.error.btc.title}</h2>
                <p>{$_BLOCKLANG.error.btc.message}</p>
            </div>
            <!-- BCH Address Error -->
            <div id="address-error-bch" ng-show="address_error_bch" ng-cloak>
                <h2>{$_BLOCKLANG.error.bch.title}</h2>
                <p>{$_BLOCKLANG.error.bch.message}</p>
            </div>
            <!-- Pending payment -->
            <div id="pending-error" ng-show="pending_error" ng-cloak>
                <h2>{$_BLOCKLANG.error.pending.title}</h2>
                <i>{$_BLOCKLANG.error.pending.message}</i>
            </div>
            <!-- Blockonomics Currency Selecter -->
            <div class="bnomics-select-container" ng-show="currency_selecter" ng-cloak>
                <h2>{$_BLOCKLANG.payWith}</h2>
                <table width="100%">
                    <tr class="bnomics-select-options" ng-repeat="(active_code, active_currency) in active_currencies"
                        ng-click="select_blockonomics_currency(active_code)">
                        <td align="left"><img src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/img/[[active_code]].png"
                                class="rotateimg[[active_code]]" alt="[[active_currency.name]] Logo">
                            <h3>[[active_currency.name]]</h3> <span class="bnomics-select-currency-button"><button
                                    type="button"
                                    class="btn btn-lg bnomics-select-currency-code">[[active_code]]</button></span>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Payment Expired -->
            <div class="bnomics-order-expired-wrapper" ng-show="order.status == -3" ng-cloak>
                <h3 class="warning bnomics-status-warning">{$_BLOCKLANG.paymentExpired}</h3><br>
                <p><a href="#" ng-click="try_again_click()">{$_BLOCKLANG.tryAgain}</a></p>
            </div>
            <!-- Payment Error -->
            <div class="bnomics-order-error-wrapper" ng-show="order.status == -2" ng-cloak>
                <h3 class="warning bnomics-status-warning">{$_BLOCKLANG.paymentError}</h3>
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
                                    <a href="[[currency.uri]]:[[order.addr]]?amount=[[order.bits/1.0e8]]"
                                        target="_blank">
                                        <qrcode data="[[currency.uri]]:[[order.addr]]?amount=[[order.bits/1.0e8]]"
                                            size="160" version="6">
                                            <canvas class="qrcode"></canvas>
                                        </qrcode>
                                    </a>
                                </div>
                                <div class="bnomics-qr-code-hint"><a
                                        href="[[currency.uri]]:[[order.addr]]?amount=[[order.bits/1.0e8]]"
                                        target="_blank">{$_BLOCKLANG.openWallet}</a></div>
                            </div>
                            <!-- Right Side -->
                            <div class="bnomics-amount">
                                <div class="bnomics-bg">
                                    <!-- Order Amounts -->
                                    <div class="bnomics-amount">
                                        <div class="bnomics-amount-text" ng-hide="amount_copyshow" ng-cloak>
                                            {$_BLOCKLANG.payAmount}</div>
                                        <div class="bnomics-copy-amount-text" ng-show="amount_copyshow" ng-cloak>
                                            {$_BLOCKLANG.copyClipboard}</div>
                                        <ul ng-click="blockonomics_amount_click()" id="bnomics-amount-input"
                                            class="bnomics-amount-input">
                                            <li id="bnomics-amount-copy">[[order.bits/1.0e8]]</li>
                                            <li>[[order.blockonomics_currency | uppercase]]</li>
                                            <li class="bnomics-grey"> ≈ </li>
                                            <li class="bnomics-grey">[[order.value]]</li>
                                            <li class="bnomics-grey">[[order.currency]]</li>
                                        </ul>
                                        <!-- <input ng-click="blockonomics_amount_click()" id="bnomics-amount-input" class="bnomics-amount-input" type="text" ng-value="amount_string" readonly="readonly" style="cursor: pointer;"> -->
                                    </div>
                                    <!-- Order Address -->
                                    <div class="bnomics-address">
                                        <div class="bnomics-address-text" ng-hide="address_copyshow" ng-cloak>
                                            {$_BLOCKLANG.payAddress}</div>
                                        <div class="bnomics-copy-address-text" ng-show="address_copyshow" ng-cloak>
                                            {$_BLOCKLANG.copyClipboard}</div>
                                        <ul ng-click="blockonomics_address_click()" id="bnomics-address-input"
                                            class="bnomics-address-input">
                                            <li id="bnomics-address-copy">[[order.addr]]</li>
                                        </ul>
                                    </div>
                                    <!-- Order Countdown Timer -->
                                    <div class="bnomics-progress-bar-wrapper">
                                        <div class="bnomics-progress-bar-container">
                                            <div class="bnomics-progress-bar" style="width: [[progress]]%;"></div>
                                        </div>
                                    </div>
                                    <span class="ng-cloak bnomics-time-left">[[clock*1000 | date:'mm:ss' : 'UTC']]
                                        min</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Blockonomics How to pay + Credit -->
            <div class="bnomics-powered-by">
                <a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c"
                    target="_blank">{$_BLOCKLANG.howToPay}</a><br>
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