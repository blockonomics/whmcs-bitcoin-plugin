<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css">

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

            <table id="wallet-setup-table">
                <tr>
                    <td>
                        <div class="form-group mt-3">
                            <button id="connect-wallet" class="btn btn-success w-100" style="display:block;">
                                Connect wallet
                            </button>
                            <div
                            class="alert alert-info mt-2"
                            id="connectResponse"
                            style="display: none"
                            ></div>
                        </div>
                    </td>
                </tr>
            </table>

            <table id="wallet-info" style="display:none">
                <tr>
                    <th style="text-align:left;">
                        <div style="display: flex;align-items: center;justify-content: space-between;">
                            <p class="m-0 p-0">Your address:</p>
                                <small
                                class="
                                    bg-light
                                    text-muted text-lowercase
                                    p-2
                                    px-3
                                    rounded-pill
                                "
                                id="userAddress"
                                style="width: 200px;overflow: hidden;text-overflow: ellipsis;"
                                >0x..add-here</small
                                >
                        </div>
                    </th>
                </tr>

                <tr>
                    <th>
                        <div style="display: flex;align-items: center;justify-content: space-between;">
                            <p class="m-0 p-0">To send:</p>
                            <small
                            class="
                                bg-light
                                text-muted
                                p-2
                                px-3
                                rounded-pill
                            "
                            id="userAmount"
                            style="width: 200px;text-align:right;"
                            >0.00 USDT</small
                            >
                        </div>
                    </th>
                </tr>

                <tr>
                    <td>
                        <form
                            class="mt-4"
                            id="transferForm"
                        >
                            <div class="form-group mt-3">
                                <button class="btn btn-success w-100" type="submit" style="display:block;">
                                    Pay Now
                                </button>
                            </div>
                        </form>

                        <div
                        class="alert alert-info mt-2"
                        id="transferResponse"
                        style="display: none"
                        ></div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<script>
var blockonomics_data = JSON.stringify({
    finish_order_url: '{$WEB_ROOT}/modules/gateways/blockonomics/payment.php?finish_order={$order_hash}',
    contract_address: '{$contract_address}',
    order_amount: '{$order_amount}',
    usdt_receivers_address: '{$usdt_address}',
    chain_id:'{$chain_id}',
    crypto: JSON.parse('{json_encode($crypto)}'),
})
</script>

<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/vendors/ethers.min.js"></script>
<script type="text/javascript" src="{$WEB_ROOT}/modules/gateways/blockonomics/assets/js/web3_checkout.js"></script>
