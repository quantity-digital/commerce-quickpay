<p><img src="./src/icon.svg" width="100" height="100" alt="QuickPay for Craft Commerce icon"></p>

<h1>QuickPay for Craft Commerce</h1>

This plugin provides an [QuickPay](https://www.quickpay.net/) integration for [Craft Commerce](https://craftcms.com/commerce).

## Requirements

This plugin requires Craft CMS 3.5 and Craft Commerce 3.2.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “QuickPay for Craft Commerce”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require quantity-digital/commerce-quickpay

# tell Craft to install the plugin
./craft install/plugin quantity-digital/commerce-quickpay
```

## Setup

To add an QuickPay payment gateway, go to Commerce → Settings → Gateways, create a new gateway, and set the gateway type to “QuickPay”.

> **Tip:** The API Key and Private key key settings can be set to environment variables. See [Environmental Configuration](https://docs.craftcms.com/v3/config/environments.html) in the Craft docs for more information.

## Roadmap

* Autocapture on authorize (Purchase)
* Capture on status change
* Translate to aditional languages
* On manual order, send paymentlink to customer
