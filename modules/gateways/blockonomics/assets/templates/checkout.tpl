<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css">
{if $crypto['code'] eq 'usdt'}
<script type="text/javascript" src="https://stagingtest.blockonomics.co/js/web3-payment.js"></script>
{/if}

<div id="blockonomics_checkout">
    <div class="bnomics-order-container">
        
        <!-- Spinner -->
        <div class="bnomics-spinner-wrapper">
            <div class="bnomics-spinner"></div>
        </div>

        <!-- Display Error -->
        <div class="bnomics-display-error">
            <h2>{$_BLOCKLANG.error.render_error.title}</h2>
            <p>{$_BLOCKLANG.error.render_error.message}</p>
        </div>
        
        <!-- Blockonomics Checkout Panel -->    
        <div class="bnomics-order-panel">
            <table>
                <tr>
                    <th class="bnomics-header">
                        <!-- Order Header -->
                        <span class="bnomics-order-id">
                            {$_BLOCKLANG.orderId}{$order->id_order}
                        </span>
                        
                        <div>
                            <span class="blockonomics-icon-cart"></span>
                            {$order->value} {$order->currency}
                        </div>
                    </th>
                </tr>
            </table>
            {if $crypto['code'] eq 'usdt'}
                <web3-payment
                    order_amount="{$order_amount}"
                    receive_address="{$order_receive_address}"
                    redirect_url="{$WEB_ROOT}/modules/gateways/blockonomics/payment.php?finish_order={$order_hash}&crypto=usdt"
                    testnet=1
                ></web3-payment>
            {else}
                <table>
                    <tr>
                        <th>
                            <!-- Order Address -->
                            <label class="bnomics-address-text">{$_BLOCKLANG.payAmount1}{strtolower($crypto['name'])}{$_BLOCKLANG.payAmount2}</label>
                            <label class="bnomics-copy-address-text">{$_BLOCKLANG.copyClipboard}</label>
                            <div class="bnomics-copy-container">
                                <input type="text" value="{$order->addr}" id="bnomics-address-input" readonly/>
                                <span id="bnomics-address-copy" class="blockonomics-icon-copy"></span>
                                <span id="bnomics-show-qr" class="blockonomics-icon-qr"></span>
                            </div>
                            
                            <div class="bnomics-qr-code">
                                <div class="bnomics-qr">
                                    <a href="{$payment_uri}" target="_blank" class="bnomics-qr-link">
                                        <canvas id="bnomics-qr-code"></canvas>
                                    </a>
                                </div>
                                <small class="bnomics-qr-code-hint">
                                    <a href="{$payment_uri}" target="_blank" class="bnomics-qr-link">{$_BLOCKLANG.openWallet}</a>
                                </small>
                            </div>

                        </th>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th>
                            <label class="bnomics-amount-text">{$_BLOCKLANG.payAddress1}{strtolower($crypto['name'])} ({strtoupper($crypto['code'])}){$_BLOCKLANG.payAddress2}</label>
                            <label class="bnomics-copy-amount-text">{$_BLOCKLANG.copyClipboard}</label>

                            <div class="bnomics-copy-container" id="bnomics-amount-copy-container">
                                <input type="text" value="{$order_amount}" id="bnomics-amount-input" readonly/>
                                <span id="bnomics-amount-copy" class="blockonomics-icon-copy"></span>
                                <span id="bnomics-refresh" class="blockonomics-icon-refresh"></span>
                            </div>

                            <small class="bnomics-crypto-price-timer">
                                1 {strtoupper($crypto['code'])} = <span id="bnomics-crypto-rate"> {$crypto_rate_str}</span> {$order->currency} {$_BLOCKLANG.updateIn} <span class="bnomics-time-left">00:00 min</span>
                            </small>
                        </th>
                    </tr>

                </table>
            {/if}
        </div>
    </div>
</div>

<script>
var blockonomics_data = JSON.stringify({
    time_period: {$time_period},
    crypto: JSON.parse('{json_encode($crypto)}'),
    crypto_address: '{$order->addr}',
    get_order_amount_url: '{$WEB_ROOT}/modules/gateways/blockonomics/payment.php?get_order={$order_hash}&crypto={$crypto['code']}',
    finish_order_url: '{$WEB_ROOT}/modules/gateways/blockonomics/payment.php?finish_order={$order_hash}',
    payment_uri: '{$payment_uri}',
})
</script>

<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/vendors/reconnecting-websocket.min.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/vendors/qrious.min.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/checkout.js "></script>
