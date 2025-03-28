<?php
require_once dirname(__FILE__) . '/blockonomics/blockonomics.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


use Blockonomics\Blockonomics;

function blockonomics_config()
{

    // When loading plugin setup page, run custom JS
    add_hook(
        'AdminAreaFooterOutput',
        1,
        function () {
            // Check if the blockonomics module is activated
            try {
                // Detect module name from filename.
                $gatewayModuleName = basename(__FILE__, '.php');
                // Fetch gateway configuration parameters.
                $gatewayParams = getGatewayVariables($gatewayModuleName);
            }
            catch (exception $e) {
                return;
            }
            $blockonomics = new Blockonomics();
            include $blockonomics->getLangFilePath();
            $system_url = \App::getSystemURL();
            $secret = $blockonomics->getCallbackSecret();
            $active_currencies = json_encode($blockonomics->getActiveCurrencies());
            $callback_url = $blockonomics->getCallbackUrl($secret);
            $trans_text_system_url_error = $_BLOCKLANG['testSetup']['systemUrl']['error'];
            $trans_text_system_url_fix = $_BLOCKLANG['testSetup']['systemUrl']['fix'];
            $trans_text_success_btc = $_BLOCKLANG['testSetup']['success']['btc'];
            $trans_text_success_usdt = $_BLOCKLANG['testSetup']['success']['usdt'];
            $trans_text_protocol_error = $_BLOCKLANG['testSetup']['protocol']['error'];
            $trans_text_protocol_fix = $_BLOCKLANG['testSetup']['protocol']['fix'];
            $trans_text_testing = $_BLOCKLANG['testSetup']['testing'];

            return <<<HTML
		<script type="text/javascript">
			var secret = document.getElementsByName('field[CallbackSecret]');
			secret.forEach(function(element) {
				element.value = '$secret';
				element.readOnly = true;
				element.parentNode.parentNode.style.display = 'none';
			});
			/**
			 * Disable callback url editing
			 */
			var inputFields = document.getElementsByName('field[CallbackURL]');
			inputFields.forEach(function(element) {
				element.value = '$callback_url';
				element.readOnly = true;
			});

			/**
			 * Padding for config labels
			 */
			var inputLabels = document.getElementsByClassName('fieldlabel');

			for(var i = 0; i < inputLabels.length; i++) {
				inputLabels[i].style.paddingRight = '20px';
			}

			/**
			 * Set available values for margin setting
			 */
			var inputMargin = document.getElementsByName('field[Margin]');
			inputMargin.forEach(function(element) {
				element.type = 'number';
				element.min = 0;
				element.max = 4;
				element.step = 0.01;
			});
			var inputSlack = document.getElementsByName('field[Slack]');
			inputSlack.forEach(function(element) {
				element.type = 'number';
				element.min = 0;
				element.max = 10;
				element.step = 0.01;
			});

			/**
			 * Generate Settings and Currency Headers
			 */
            const blockonomicsTable = document.getElementById("Payment-Gateway-Config-blockonomics");
            const headerStyles = 'text-decoration: underline; margin-bottom: 2px';
            //Add Settings Row
            const settingsRow = blockonomicsTable.insertRow( 3 );
            settingsRow.insertCell(0);
            const settingsFieldArea = settingsRow.insertCell(1);

            const settingsHeader = document.createElement('h4');
            settingsHeader.style.cssText = headerStyles
            settingsHeader.textContent = 'Settings';
            settingsFieldArea.appendChild(settingsHeader);

            //Currency header

            /**
			 * Generate Advanced Settings Button and get the HTML elements
			 */
            const callbackUrl = blockonomicsTable.rows[7];
            const timePeriod = blockonomicsTable.rows[8];
            const extraMargin = blockonomicsTable.rows[9];
            const underSlack = blockonomicsTable.rows[10];
            const confirmations = blockonomicsTable.rows[11];
            const btccurrencySettings = blockonomicsTable.rows[12];
            const bchcurrencySettings = blockonomicsTable.rows[13];
            const usdtcurrencySettings = blockonomicsTable.rows[14];

            callbackUrl.style.display = "none";
            timePeriod.style.display = "none";
            extraMargin.style.display = "none";
            underSlack.style.display = "none";
            confirmations.style.display = "none";
            btccurrencySettings.style.display = "none";
            bchcurrencySettings.style.display = "none";
            usdtcurrencySettings.style.display = "none";

            var advancedSettingsRow = blockonomicsTable.insertRow(7);
			var advancedSettingsLabelCell = advancedSettingsRow.insertCell(0);
			var advancedSettingsFieldArea = advancedSettingsRow.insertCell(1);
            
            var advancedLink = document.createElement('a');
            advancedLink.classList.add('cursor');
            advancedLink.textContent = 'Advanced Settings ▼';
            advancedSettingsFieldArea.appendChild(advancedLink);

            let showingAdvancedSettings = false;
            /**
             * Get store name from Blockonomics API using API key and callback URL
             *
             * @return string Store name or empty string if error
             */

			advancedLink.onclick = function() {
                advancedLink.textContent = (showingAdvancedSettings) ? 'Advanced Settings ▼' : 'Advanced Settings ▲';
                if (showingAdvancedSettings) {
                    callbackUrl.style.display = "none";
                    timePeriod.style.display = "none";
                    extraMargin.style.display = "none";
                    underSlack.style.display = "none";
                    confirmations.style.display = "none";
                    bchcurrencySettings.style.display = "none";
                } else {
                    callbackUrl.style.display = "table-row";
                    timePeriod.style.display = "table-row";
                    extraMargin.style.display = "table-row";
                    underSlack.style.display = "table-row";
                    confirmations.style.display = "table-row";
                    bchcurrencySettings.style.display = "table-row";
                }
                showingAdvancedSettings = !showingAdvancedSettings;
			}

            // Inject Custom Styles

            let style = document.createElement('style');
            style.setAttribute('type', 'text/css');
            style.innerHTML = 'a.cursor {cursor: pointer; text-decoration: none;} a.cursor:hover {text-decoration: none;}';
            document.head.appendChild(style);

			/**
			 * Generate Test Setup button
			 */
            const saveButtonCell = blockonomicsTable.rows[ blockonomicsTable.rows.length - 1 ].children[1];
            saveButtonCell.style.backgroundColor = "white";
            const storeNameRow = blockonomicsTable.rows[5]; // Row after store name field
            const newRow = blockonomicsTable.insertRow(6);
            const labelCell = newRow.insertCell(0);
            const buttonCell = newRow.insertCell(1);
            buttonCell.style.backgroundColor = "white";

            const newBtn = document.createElement('BUTTON');
            newBtn.setAttribute('type', 'button');
            newBtn.className = "btn btn-primary";
            newBtn.textContent = "Test Setup";

            buttonCell.appendChild(newBtn);

            function handle_ajax_save(res, xhr, settings){
                if (settings.url == "configgateways.php?action=save" && settings.data.includes("module=blockonomics")) {
                    // We detected the Blockonomics Request

                    // Remove the listener
                    jQuery(document).off('ajaxComplete', handle_ajax_save)

                    // Do the Test
                    if (xhr.status == 200 && sessionStorage.getItem("runTest"))
                        doTest();
                    // Remove Test Session Key if exists
                    sessionStorage.removeItem("runTest");
                }
            }

			newBtn.onclick = function(e) {
                e.preventDefault();
                if(typeof jQuery != 'undefined') {
                    jQuery(document).on('ajaxComplete', handle_ajax_save)
                }
                sessionStorage.setItem("runTest", true);
                sessionStorage.setItem("updateStore", true);
                if(saveButtonCell.querySelector('button[type=submit]')){
                    saveButtonCell.querySelector('button[type=submit]').click();
                } else {
                    saveButtonCell.querySelector('input[type=submit]').click();
                }
            }
 
            const addTestResultRow = (rowsFromBottom) => {
                const testSetupResultRow = blockonomicsTable.insertRow(blockonomicsTable.rows.length - rowsFromBottom);
                testSetupResultRow.classList.add('blockonomics-test-row');

                const testSetupResultLabel = testSetupResultRow.insertCell(0);
                const testSetupResultCell = testSetupResultRow.insertCell(1);
                testSetupResultRow.style.display = "none";
                testSetupResultRow.style.display = "table-row";
                testSetupResultCell.className = "fieldarea";
                return testSetupResultCell;
            }

            function doTest() {
                const form = new FormData(saveButtonCell.closest('form'));
                // Remove any existing response div
                const existingResponse = document.getElementById('blockonomics-test-response');
                if (existingResponse) {
                    existingResponse.remove();
                }

                // Create new response div below the test button
                const responseDiv = document.createElement('div');
                responseDiv.id = 'blockonomics-test-response';
                responseDiv.style.marginTop = '10px';
                buttonCell.appendChild(responseDiv);

                var testSetupUrl = "$system_url";
                if (!testSetupUrl.endsWith('/')) {
                    testSetupUrl += '/';
                }
                testSetupUrl += "modules/gateways/blockonomics/testsetup.php";

                try {
                    var systemUrlProtocol = new URL("$system_url").protocol;
                } catch (err) {
                    console.error("Error parsing URL:", err);
                }

                if (systemUrlProtocol != location.protocol) {
                    responseDiv.innerHTML = `<label style='color:red;'>$trans_text_protocol_error</label> 
                        $trans_text_protocol_fix`;
                } else {
                    const xhr = new XMLHttpRequest();
                    xhr.addEventListener("load", function() {
                        if(this.status != 200) {
                            responseDiv.innerHTML = '<label style="color:red;">Error: Network error occurred</label>';
                        } else {
                            try {
                                const response = JSON.parse(this.responseText);
                                
                                responseDiv.innerHTML = response;

                            } catch (err) {
                                console.error("Parse error:", err);
                                responseDiv.innerHTML = `<label style='color:red;'>Error:</label> $trans_text_system_url_error`;
                            }
                        }
                        newBtn.disabled = false;
                    });

                    xhr.addEventListener("error", function(error) {
                        console.error("XHR Error:", error);
                        responseDiv.innerHTML = `<label style='color:red;'>Error:</label> Network Error Occurred.`;
                        newBtn.disabled = false;
                    });

                    xhr.open("GET", testSetupUrl);
                    newBtn.disabled = true;
                    responseDiv.innerHTML = "$trans_text_testing";
                    xhr.send();
                }
            }

            // For Non AJAX Based Submission
            if(sessionStorage.getItem("runTest") && !document.querySelector('#manage .errorbox')) {
                sessionStorage.removeItem("runTest");
                doTest()
            }

		</script>
HTML;
        }
    );

    $blockonomics = new Blockonomics();
    include $blockonomics->getLangFilePath();
    $blockonomics->createOrderTableIfNotExist();


    $settings_array = [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Blockonomics',
        ],
        [
            'FriendlyName' => '<span style="color:grey;">' . $_BLOCKLANG['version']['title'] . '</span>',
            'Description' => '<span style="color:grey;">' . $blockonomics->getVersion() . '</span>',
        ],
    ];
    $settings_array['ApiKey'] = [
        'FriendlyName' => $_BLOCKLANG['apiKey']['title'],
        'Description' => '<span class="api-key-description">' . $_BLOCKLANG['apiKey']['description'] . '</span>',
        'Type' => 'text',
    ];
    // Get current store name if it exists
    $currentStoreName = null;
    try {
        $gatewayParams = getGatewayVariables('blockonomics');
        $currentStoreName = $gatewayParams['StoreName'] ?? 'Your Blockonomics Store';
    } catch (Exception $e) {
        $currentStoreName = 'Your Blockonomics Store';
    }

    $settings_array['StoreName'] = [
        'FriendlyName' => $_BLOCKLANG['storeName']['title'],
        'Type' => 'label',
        'Description' => $currentStoreName ?: 'Your Blockonomics Store',
    ];

    $settings_array['CallbackSecret'] = [
        'FriendlyName' => $_BLOCKLANG['callbackSecret']['title'],
        'Type' => 'text',
    ];
    $settings_array['CallbackURL'] = [
        'FriendlyName' => $_BLOCKLANG['callbackUrl']['title'],
        'Type' => 'text',
    ];
    $settings_array['TimePeriod'] = [
        'FriendlyName' => $_BLOCKLANG['timePeriod']['title'],
        'Type' => 'dropdown',
        'Options' => [
            '10' => '10',
            '15' => '15',
            '20' => '20',
            '25' => '25',
            '30' => '30',
        ],
        'Description' => $_BLOCKLANG['timePeriod']['description'],
    ];
    $settings_array['Margin'] = [
        'FriendlyName' => $_BLOCKLANG['margin']['title'],
        'Type' => 'text',
        'Size' => '5',
        'Default' => 0,
        'Description' => $_BLOCKLANG['margin']['description'],
    ];
    $settings_array['Slack'] = [
        'FriendlyName' => $_BLOCKLANG['slack']['title'],
        'Type' => 'text',
        'Size' => '5',
        'Default' => 0,
        'Description' => $_BLOCKLANG['slack']['description'],
    ];
    $settings_array['Confirmations'] = [
        'FriendlyName' => $_BLOCKLANG['confirmations']['title'],
        'Type' => 'dropdown',
        'Default' => 2,
        'Options' => [
            '2' => '2 (' . $_BLOCKLANG['confirmations']['recommended'] . ')',
            '1' => '1',
            '0' => '0',
        ],
        'Description' => $_BLOCKLANG['confirmations']['description'],
    ];
    $blockonomics_currencies = $blockonomics->getSupportedCurrencies();
    foreach ($blockonomics_currencies as $code => $currency) {
        $settings_array[$code . 'Enabled'] = [
            'FriendlyName' => $_BLOCKLANG['enabled'][$code.'_title'],
            'Type' => 'yesno', 
            'Description' => $_BLOCKLANG['enabled'][$code.'_description'],
        ];
        if ($code == 'btc') {
            $settings_array[$code . 'Enabled']['Default'] = 'on';
        }
    }
    return $settings_array;
}

function blockonomics_link($params)
{
    if (false === isset($params) || true === empty($params)) {
        exit('[ERROR] In modules/gateways/blockonomics.php::Blockonomics_link() function: Missing or invalid $params data.');
    }

    $blockonomics = new Blockonomics();
    $order_params = $blockonomics->get_order_checkout_params($params);
    
    $form_url = \App::getSystemURL() . 'modules/gateways/blockonomics/payment.php';

    //pass only the uuid to the payment page
    $form = '<form action="' . $form_url . '" method="GET">';
    foreach ($order_params as $name => $param) {
        $form .= '<input type="hidden" name="' . $name . '" value="' . $param . '"/>';
    }
    $form .= '<input type="submit" value="' . $params['langpaynow'] . '"/>';
    $form .= '</form>';

    return $form;
}