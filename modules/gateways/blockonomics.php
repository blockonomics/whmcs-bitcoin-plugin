<?php

require_once dirname(__FILE__) . '/blockonomics/blockonomics.php';

use Blockonomics\Blockonomics;

function blockonomics_config()
{

    // When loading plugin setup page, run custom JS
    add_hook(
        'AdminAreaFooterOutput',
        1,
        function ($vars) {
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
            $trans_text_success = $_BLOCKLANG['testSetup']['success'];
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
            var settingsTable = document.getElementById("Payment-Gateway-Config-blockonomics");

            //Settings header
            var settingsRow = settingsTable.insertRow( 3 );
			var advancedSettingsLabelCell = settingsRow.insertCell(0);
			var advancedSettingsFieldArea = settingsRow.insertCell(1);

            var settingsHeader = document.createElement('h4');
            settingsHeader.style.textDecoration = 'underline';
            settingsHeader.style.marginBottom = '3px';
            settingsHeader.textContent = 'Settings';
            advancedSettingsFieldArea.appendChild(settingsHeader);

            //Currency header
            var currencyRow = settingsTable.insertRow( 11 );
			var advancedSettingsLabelCell = currencyRow.insertCell(0);
			var advancedSettingsFieldArea = currencyRow.insertCell(1);
            
            var currencyHeader = document.createElement('h4');
            currencyHeader.style.textDecoration = 'underline';
            currencyHeader.style.marginBottom = '3px';
            currencyHeader.textContent = 'Currencies';
            advancedSettingsFieldArea.appendChild(currencyHeader);

            /**
			 * Generate Advanced Settings Button
			 */
            //get advanced settings HTML elements 
            const timePeriod = settingsTable.rows[7];
            const extraMargin = settingsTable.rows[8];
            const underSlack = settingsTable.rows[9];
            const confirmations = settingsTable.rows[10];

            timePeriod.style.display = "none";
            extraMargin.style.display = "none";
            underSlack.style.display = "none";
            confirmations.style.display = "none";

            var settingsTable = document.getElementById("Payment-Gateway-Config-blockonomics");
            var advancedSettingsRow = settingsTable.insertRow(7);
			var advancedSettingsLabelCell = advancedSettingsRow.insertCell(0);
			var advancedSettingsFieldArea = advancedSettingsRow.insertCell(1);
            
            var advancedLink = document.createElement('a');
            advancedLink.textContent = 'Advanced Settings ▼';
            advancedSettingsFieldArea.appendChild(advancedLink);

            let showingAdvancedSettings = false;
			advancedLink.onclick = function() {
                advancedLink.textContent = (showingAdvancedSettings) ? 'Advanced Settings ▼' : 'Advanced Settings ▲';
                if (showingAdvancedSettings) {
                    timePeriod.style.display = "none";
                    extraMargin.style.display = "none";
                    underSlack.style.display = "none";
                    confirmations.style.display = "none";
                } else {
                    timePeriod.style.display = "table-row";
                    extraMargin.style.display = "table-row";
                    underSlack.style.display = "table-row";
                    confirmations.style.display = "table-row";
                }
                showingAdvancedSettings = !showingAdvancedSettings;
			}

			/**
			 * Generate Test Setup button and setup result field
			 */
			var testSetupResultRow = settingsTable.insertRow(settingsTable.rows.length - 1);
			testSetupResultRow.style.display = "none";
			var testSetupResultLabel = testSetupResultRow.insertCell(0);
			var testSetupResultCell = testSetupResultRow.insertCell(1);
			testSetupResultCell.className = "fieldarea";

            const saveButtonCell = settingsTable.rows[ settingsTable.rows.length - 1 ].children[1]
            saveButtonCell.style.backgroundColor = "white";

			var newBtn = document.createElement('BUTTON');
			newBtn.className = "btn btn-primary";
            newBtn.textContent = "Test Setup";

			saveButtonCell.appendChild(newBtn);

			function reqListener () {
				var responseObj = {};
				try {
					responseObj = JSON.parse(this.responseText);
				} catch (err) {
					var testSetupUrl = "$system_url" + "modules/gateways/blockonomics/testsetup.php";
					responseObj.error = true;
					responseObj.errorStr = '$trans_text_system_url_error ' + testSetupUrl + '. $trans_text_system_url_fix';
				}
				if (responseObj.error) {
					testSetupResultCell.innerHTML = "<label style='color:red;'>Error:</label> " + responseObj.errorStr +
					"<br>For more information, please consult <a href='https://blockonomics.freshdesk.com/support/solutions/articles/33000215104-troubleshooting-unable-to-generate-new-address' target='_blank'>this troubleshooting article</a>";
				} else {
					testSetupResultCell.innerHTML = "<label style='color:green;'>$trans_text_success</label>";
				}
				newBtn.disabled = false;
			}

			newBtn.onclick = function() {
				testSetupResultRow.style.display = "table-row";
				var apiKeyField = document.getElementsByName('field[ApiKey]')[0];
				var testSetupUrl = "$system_url" + "modules/gateways/blockonomics/testsetup.php"+"?new_api="+apiKeyField.value;

				try {
					var systemUrlProtocol = new URL("$system_url").protocol;
				} catch (err) {
					var systemUrlProtocol = '';
				}

				if (systemUrlProtocol != location.protocol) {
					testSetupResultCell.innerHTML = "<label style='color:red;'>$trans_text_protocol_error</label> \
							$trans_text_protocol_fix";
					return false;
				}

				var oReq = new XMLHttpRequest();
				oReq.addEventListener("load", reqListener);
				oReq.open("GET", testSetupUrl);
				oReq.send();

				newBtn.disabled = true;
				testSetupResultCell.innerHTML = "$trans_text_testing";

				return false;
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
        'Description' => $_BLOCKLANG['apiKey']['description'],
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

    $blockonomics_currencies = $blockonomics->getSupportedCurrencies();
    foreach ($blockonomics_currencies as $code => $currency) {
        $settings_array[$code . 'Enabled'] = [
            'FriendlyName' => $currency['name'] .' (' . strtoupper($code) . ')',
            'Type' => 'yesno',
            'Description' => $_BLOCKLANG['enabled'][$code.'_description'],
        ];

    }

    return $settings_array;
}

function blockonomics_link($params)
{
    if (false === isset($params) || true === empty($params)) {
        exit('[ERROR] In modules/gateways/blockonomics.php::Blockonomics_link() function: Missing or invalid $params data.');
    }

    $blockonomics = new Blockonomics();
    $order_hash = $blockonomics->getOrderHash($params['invoiceid'], $params['amount'], $params['currency'], $params['basecurrencyamount']);

    $system_url = \App::getSystemURL();
    $form_url = $system_url . 'modules/gateways/blockonomics/payment.php';

    //pass only the uuid to the payment page
    $form = '<form action="' . $form_url . '" method="GET">';
    $form .= '<input type="hidden" name="order" value="' . $order_hash . '"/>';
    $form .= '<input type="submit" value="' . $params['langpaynow'] . '"/>';
    $form .= '</form>';

    return $form;
}
