<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/gateways/blockonomics/assets/css/order.css?v={$plugin_version}">

<div class="bnomics-order-container">
    <div class="bnomics-select-container">
        <h3>{$_BLOCKLANG.invoiceNotPayable.title}</h3>
        <p>{$_BLOCKLANG.invoiceNotPayable.message}</p>
        <p>
            <strong>{$_BLOCKLANG.invoiceNotPayable.statusLabel}:</strong>
            {$invoice_status|escape}
        </p>
        <a href="{$invoice_url}" class="btn btn-primary">
            {$_BLOCKLANG.invoiceNotPayable.viewInvoice}
        </a>
    </div>
</div>
