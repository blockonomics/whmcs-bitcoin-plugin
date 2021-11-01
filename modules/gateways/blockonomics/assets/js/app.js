service = angular.module("shoppingcart.services", ["ngResource"]);

service.factory('Order', function($resource) {
    param = {};
    var item = $resource(window.location.pathname, param);
    return item;
});

app = angular.module('shopping-cart-demo', ["monospaced.qrcode", "shoppingcart.services"],function($interpolateProvider) {
            $interpolateProvider.startSymbol('[[');
            $interpolateProvider.endSymbol(']]');
});

app.config(function($compileProvider) {
    $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|mailto|data|chrome-extension|bitcoin|bitcoincash):/);
    // Angular before v1.2 uses $compileProvider.urlSanitizationWhitelist(...)
});

//CheckoutController
app.controller('CheckoutController', function($scope, $interval, Order, $httpParamSerializer, $timeout) {
    //get order uuid from url
    var time_period_div = document.getElementById("time_period");
    var blockonomics_time_period = time_period_div.dataset.time_period;
    var totalTime = blockonomics_time_period * 60;
    var totalProgress = 100;
    
    var order_uuid_div = document.getElementById("order_uuid");
    $scope.order_uuid = order_uuid_div.dataset.order_uuid;

    var order_id_div = document.getElementById("order_id");
    $scope.order_id = order_id_div.dataset.order_id;
    
    var active_currencies_div = document.getElementById("active_currencies");
    var active_currencies = JSON.parse(active_currencies_div.dataset.active_currencies);
    $scope.active_currencies = active_currencies;

    $scope.copyshow = false;
    $scope.display_problems = true;
    //Create url when the order is received 
    $scope.finish_order_url = function() {
        var params = {};
        params.finish_order = $scope.order_id;
        url = window.location.pathname;
        var serializedParams = $httpParamSerializer(params);
        if (serializedParams.length > 0) {
            url += ((url.indexOf('?') === -1) ? '?' : '&') + serializedParams;
        }
        return url;
    }

    //Increment bitcoin timer 
    $scope.tick = function() {
        $scope.clock = $scope.clock - 1;
        $scope.progress = Math.floor($scope.clock * totalProgress / totalTime);
        if ($scope.clock < 0) {
            $scope.clock = 0;
            //Order expired
            $scope.order.status = -3;
        }
        $scope.progress = Math.floor($scope.clock * totalProgress / totalTime);
    };

    //Select Blockonomics currency
    $scope.select_blockonomics_currency = function(blockonomics_currency) {
        $scope.currency_selecter  = false;
        $scope.currency = $scope.active_currencies[blockonomics_currency];
        $scope.currency.code = blockonomics_currency;
        check_blockonomics_uuid();
    }

    //Fetch the blockonomics_currency symbol from name
    function getAltKeyByValue(object, value) {
        return Object.keys(object).find(key => object[key] === value);
    }

    //Proccess the order data
    function proccess_order_data(data) {
        $scope.order = data;
        if(data.blockonomics_currency === 'btc'){
            var subdomain = 'www';
        }else{
            var subdomain = data.blockonomics_currency;
        }
        //Check the status of the order
        if ($scope.order.status == -1) {
            $scope.clock = $scope.order.time_remaining;
            //Mark order as expired if we ran out of time
            if ($scope.clock < 0) {
                $scope.order.status = -3;
                return;
            }
            $scope.tick_interval = $interval($scope.tick, 1000);
            //Connect and Listen on websocket for payment notification
            var ws = new ReconnectingWebSocket("wss://" + subdomain + ".blockonomics.co/payment/" + $scope.order.addr + "?timestamp=" + $scope.order.timestamp);
            ws.onmessage = function(evt) {
                ws.close();
                $interval(function() {
                    //Redirect to order received page if message from socket
                    window.location = $scope.finish_order_url();
                //Wait for 2 seconds for order status to update on server
                }, 2000, 1);
            }
        }
    }
    
    //Check if the blockonomics uuid is present
    function check_blockonomics_uuid() {
        $scope.spinner = true;
        if (typeof $scope.order_uuid != 'undefined') {
            //Fetch the order using uuid
            Order.get({
                "get_order": $scope.order_uuid,
                "blockonomics_currency": $scope.currency.code
            }, function(data) {
                $scope.spinner = false;
                if(data.txid !== undefined && data.txid !== ""){
                    $scope.txid = data.txid;
                    $scope.pending_error = true;
                }else if(data.addr !== undefined){
                    proccess_order_data(data);
                    $scope.checkout_panel  = true;
                }else if($scope.currency.code === 'btc'){
                    $scope.address_error_btc = true;
                }else if($scope.currency.code === 'bch'){
                    $scope.address_error_bch = true;
                }
            });
        }
    }
    
    $scope.spinner = true;
    if(Object.keys($scope.active_currencies).length === 1){
        // Auto select btc if 1 activated currency
        let $active_currency = Object.keys($scope.active_currencies)[0];
        $scope.currency = $scope.active_currencies[$active_currency];
        $scope.currency.code = $active_currency;
        check_blockonomics_uuid();
    }else if(Object.keys($scope.active_currencies).length >= 1){
        //Show user currency selector if > 1 activated currency
        $scope.currency_selecter  = true;
        $scope.spinner = false;
    }

    function select_text(divid)
    {
        selection = window.getSelection();
        var div = document.createRange();

        div.setStartBefore(document.getElementById(divid));
        div.setEndAfter(document.getElementById(divid)) ;
        selection.removeAllRanges();
        selection.addRange(div);
    }

    function copy_to_clipboard(divid)
    {
        // Create a new textarea element and give it id='temp_element'
        var textarea = document.createElement('textarea');
        textarea.id = 'temp_element';
        // Optional step to make less noise on the page, if any!
        textarea.style.height = 0;
        // Now append it to your page somewhere, I chose <body>
        document.body.appendChild(textarea);
        // Give our textarea a value of whatever inside the div of id=containerid
        textarea.value = document.getElementById(divid).innerText;
        // Now copy whatever inside the textarea to clipboard
        var selector = document.querySelector('#temp_element');
        selector.select();
        document.execCommand('copy');
        // Remove the textarea
        document.body.removeChild(textarea);

        select_text(divid);

        if (divid == "bnomics-address-copy") {
            $scope.address_copyshow = true;
            $timeout(function() {
                $scope.address_copyshow = false;
                //Close copy to clipboard message after 2 sec
            }, 2000);
        }else{
            $scope.amount_copyshow = true;
            $timeout(function() {
                $scope.amount_copyshow = false;
                //Close copy to clipboard message after 2 sec
            }, 2000);            
        }
    }

    //Copy bitcoin address to clipboard
    $scope.blockonomics_address_click = function() {
        copy_to_clipboard("bnomics-address-copy");
    }

    //Copy bitcoin amount to clipboard
    $scope.blockonomics_amount_click = function() {
        copy_to_clipboard("bnomics-amount-copy");
    }
    //Copy bitcoin address to clipboard
    $scope.try_again_click = function() {
        location.reload();
    }

});
