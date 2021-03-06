# Blockonomics WHMCS plugin #
Accept bitcoins on your WHMCS, payments go directly into your wallet

## Description ##
- Accept bitcoin payments on your website with ease
- No security risk, payments go directly into your own bitcoin wallet  

## Installation ##
[Blog Tutorial](https://blog.blockonomics.co/friendly-bitcoin-payments-for-web-hosting-businesses-using-whmcs-88de8eef4e81) | [Video Tutorial](https://www.youtube.com/watch?v=jORcxsV-OOg)

- Copy the folder `modules` to your root WHMCS directory
- Go to your WHMCS admin, Setup -> Payments -> Payment Gateways
- Activate Blockonomics in All Payment Gateways
- Set your API key in Manage Existing Gateways
- After setting API Key refresh page
- Copy your Callback to Blockonomics Merchants > Settings
- Cleanup on upgrade from 1.8.X to 1.9.X  (Optional): If you are upgrading from 1.8.X to 1.9.X, you can run *upgrade.php* to cleanup unecessary files. Execute the script *modules/gateways/blockonomics/upgrade.php* using your browser. Example: https://xxxxxxx.ccc/modules/gateways/blockonomics/upgrade.php (replace xxxxxxx.ccc with your own WHMCS domain)

## Screenshots ##

![](screenshots/screenshot-1.png)
Checkout option

![](screenshots/screenshot-2.png)
Payment screen

![](screenshots/screenshot-3.png)
Blockonomics configuration

[![huntr](https://cdn.huntr.dev/huntr_security_badge_mono.svg)](https://huntr.dev)
