<?php
require_once dirname(__FILE__) . '/blockonomics/blockonomics.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


use Blockonomics\Blockonomics;
use WHMCS\Database\Capsule;

function blockonomics_config()
{
    // Clean up legacy CheckoutMode setting (removed in v2.1, was Sepolia testnet toggle)
    // TODO: safe to remove from v2.3+
    try {
        Capsule::table('tblpaymentgateways')
            ->where('gateway', 'blockonomics')
            ->where('setting', 'CheckoutMode')
            ->delete();
    } catch (Exception $e) {
        logActivity("Blockonomics: could not remove legacy CheckoutMode setting: " . $e->getMessage());
    }

    // Clean up legacy convertto setting (removed in v2.0, was multi-currency conversion)
    // TODO: safe to remove from v2.3+
    try {
        Capsule::table('tblpaymentgateways')
            ->where('gateway', 'blockonomics')
            ->where('setting', 'convertto')
            ->delete();
    } catch (Exception $e) {
        logActivity("Blockonomics: could not remove legacy convertto setting: " . $e->getMessage());
    }

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
            $callback_url = $blockonomics->getCallbackUrl($secret);
            $trans_text_system_url_error = $_BLOCKLANG['testSetup']['systemUrl']['error'];
            $trans_text_system_url_fix = $_BLOCKLANG['testSetup']['systemUrl']['fix'];
            $trans_text_protocol_error = $_BLOCKLANG['testSetup']['protocol']['error'];
            $trans_text_protocol_fix = $_BLOCKLANG['testSetup']['protocol']['fix'];
            $trans_text_testing = $_BLOCKLANG['testSetup']['testing'];
            $trans_text_store_name = $_BLOCKLANG['storeName']['title'];

            // Build store info for initial display (JS will update after Test Setup)
            $currentStoreName = '';
            try {
                $gatewayParams = getGatewayVariables('blockonomics');
                $currentStoreName = $gatewayParams['StoreName'] ?? '';
            } catch (Exception $e) {
                $currentStoreName = '';
            }
            $enabledCryptos = $blockonomics->getBlockonomicsEnabledCryptos();
            $initial_store_info = $blockonomics->buildStoreInfoHtml($currentStoreName, $enabledCryptos);

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

            // Helper: find a config row by its field name instead of hardcoded index
            function getFieldRow(fieldName) {
                var input = blockonomicsTable.querySelector('[name="field[' + fieldName + ']"]');
                if (!input) return null;
                return input.closest('tr');
            }

            const headerStyles = 'text-decoration: underline; margin-bottom: 2px';
            // Add Settings Row (after API Key row)
            const apiKeyRow = getFieldRow('ApiKey');
            const settingsRow = blockonomicsTable.insertRow(apiKeyRow ? apiKeyRow.rowIndex + 1 : 3);
            settingsRow.insertCell(0);
            const settingsFieldArea = settingsRow.insertCell(1);

            const settingsHeader = document.createElement('h4');
            settingsHeader.style.cssText = headerStyles
            settingsHeader.textContent = 'Settings';
            settingsFieldArea.appendChild(settingsHeader);

            // Store Name row (created via JS so WHMCS doesn't clear it on form save)
            const storeNameRow = blockonomicsTable.insertRow(settingsRow.rowIndex + 1);
            const storeNameLabel = storeNameRow.insertCell(0);
            storeNameLabel.className = 'fieldlabel';
            storeNameLabel.textContent = '$trans_text_store_name';
            const storeNameField = storeNameRow.insertCell(1);
            storeNameField.className = 'fieldarea';
            storeNameField.innerHTML = '<span id="blockonomics-store-info">$initial_store_info</span>';

            /**
			 * Advanced Settings — find rows by field name
			 */
            const advancedRows = [
                getFieldRow('CallbackURL'),
                getFieldRow('TimePeriod'),
                getFieldRow('Margin'),
                getFieldRow('Slack'),
                getFieldRow('Confirmations'),
                getFieldRow('bchEnabled')
            ];
            advancedRows.forEach(function(row) { if (row) row.style.display = "none"; });

            // Insert toggle before the first advanced row
            var firstAdvancedRow = advancedRows.find(function(r) { return r !== null; });
            var advancedSettingsRow = blockonomicsTable.insertRow(firstAdvancedRow ? firstAdvancedRow.rowIndex : blockonomicsTable.rows.length - 1);
			advancedSettingsRow.insertCell(0);
			var advancedSettingsFieldArea = advancedSettingsRow.insertCell(1);

            var advancedLink = document.createElement('a');
            advancedLink.classList.add('cursor');
            advancedLink.textContent = 'Advanced Settings ▼';
            advancedSettingsFieldArea.appendChild(advancedLink);

            let showingAdvancedSettings = false;

			advancedLink.onclick = function() {
                advancedLink.textContent = showingAdvancedSettings ? 'Advanced Settings ▼' : 'Advanced Settings ▲';
                var display = showingAdvancedSettings ? "none" : "table-row";
                advancedRows.forEach(function(row) { if (row) row.style.display = display; });
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
            // Insert Test Setup button row after Store Name row
            const newRow = blockonomicsTable.insertRow(storeNameRow.rowIndex + 1);
            newRow.insertCell(0);
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
                if(saveButtonCell.querySelector('button[type=submit]')){
                    saveButtonCell.querySelector('button[type=submit]').click();
                } else {
                    saveButtonCell.querySelector('input[type=submit]').click();
                }
            }

            function doTest() {
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

                var systemUrlHostname = '';
                try { systemUrlHostname = new URL("$system_url").hostname; } catch(e) {}

                if (systemUrlProtocol != location.protocol) {
                    responseDiv.innerHTML = `<label style='color:red;'>$trans_text_protocol_error</label>
                        $trans_text_protocol_fix`;
                } else if (systemUrlHostname && systemUrlHostname !== location.hostname) {
                    responseDiv.innerHTML = '<label style="color:red;">Error:</label> System URL hostname (<b>' + systemUrlHostname + '</b>) does not match current hostname (<b>' + location.hostname + '</b>). Update System URL in WHMCS General Settings.';
                } else {
                    const xhr = new XMLHttpRequest();
                    xhr.addEventListener("load", function() {
                        if(this.status != 200) {
                            responseDiv.innerHTML = '<label style="color:red;">Error (HTTP ' + this.status + '):</label> Could not reach test setup endpoint.';
                        } else {
                            try {
                                const data = JSON.parse(this.responseText);
                                responseDiv.innerHTML = data.message;
                                if (data.store_info_html) {
                                    var el = document.getElementById('blockonomics-store-info');
                                    if (el) el.innerHTML = data.store_info_html;
                                }
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
    $settings_array['bchEnabled'] = [
        'FriendlyName' => $_BLOCKLANG['enabled']['bch_title'],
        'Type' => 'yesno',
        'Description' => $_BLOCKLANG['enabled']['bch_description'],
    ];

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