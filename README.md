# Mastercard Gateway module for OpenCart
This plugin adds Mastercard Payment Gateway Services as a payment option on your OpenCart checkout page.

## System Requirments
The latest release of the plugin has the following system requirements:

- PHP version 8.0 or higher is mandatory.
- OpenCart version 4.0.2.3 or later is required. However, we strongly recommend using the latest available version of OpenCart for optimal performance.

## Documentation
Click here to access the documentation: https://mpgs.fingent.wiki/target/opencart-mastercard-payment-gateway-services/installation/

## Support
For customer support: https://mpgsfgs.atlassian.net/servicedesk/customer/portals

## Compatibility

Version v1.3.2
- Added PayPal (Supported in Hosted Checkout Only).
- Updated Mastercard API Version to 100.
- The plugin will capture the download count from GitHub and track active installations, including Store Name, Store URL, and Country, once Test Mode is disabled and live credentials are saved.

Version v1.3.1
- Implemented a notification feature to alert the OpenCart administrator whenever a new version is launched on GitHub.
- Updated Mastercard API Version to 78.

Version v1.3.0
- Enhanced Compatibility: Compatible with PHP 8.1 & OpenCart 4.0.2.x (tested with v4.0.2.3).
- Updated Mastercard API Version to 73.
- Added Payment Capture Option.
- Added Refund Option.
- Added Partial Refund Option.

## Customer Information Shared with Gateway

### This module shares the following customer information with the gateway:

- Customer Billing and Shipping Address
- Customer Name
- Customer Phone Number
- Customer Email Address
- Cart Line Items (optional)

##  Mastercard Payment Module Features

The Mastercard Payment Module is packed with tools to make payment processing easier and safer for your business. Here's a quick look at its main features:

## 1. Payment Methods
Defines the types of payment options supported, which are:

### • Card Payments
Easily and securely accept both credit and debit card payments. This feature works with all major card brands, making it simple and reliable for your customers to pay.

### • Google Pay (Supported in Hosted Checkout Only)
With Google Pay, customers can quickly and easily pay on the hosted checkout page. To enable this option, ensure your Merchant Identification (MID) is configured for Google Pay. This makes payments smooth and hassle-free, allowing customers to complete transactions with just a few taps.

### • PayPal (Supported in Hosted Checkout Only)
With PayPal, customers can make payments quickly and effortlessly through the hosted checkout page. To use this option, ensure that your Merchant Identification (MID) is set up for PayPal transactions. Once enabled, this feature provides a seamless and hassle-free payment experience, allowing customers to complete their purchases with just a few simple taps.

## 2. Checkout and Payment Integration
This feature focuses on the method of collecting payment details from customers:

### • Hosted Checkout
This feature lets your customers enter their payment details on a readymade secure checkout page provided directly by Mastercard. It keeps sensitive information safe while giving your customers a smooth and hassle-free payment experience.

## 3. Fraud Prevention and Security
This feature enhances security and protects against fraud:

### • Address Verification Service (AVS)
AVS helps prevent fraud by checking the billing address provided during a payment to make sure it matches the one on file with the cardholder's bank. This helps confirm that the person making the payment is the actual cardholder. To use AVS, it must be activated on your Merchant Identification (MID).

## 4. Transaction Management
These features support the processing and management of transactions:

### • Capture Payments
This feature lets you manually process payments for authorized orders directly from your system. It gives you more control over how payments are handled.

### • Full Refunds
You can refund the entire amount of the transaction back to the customer's account. This is helpful when a complete order needs to be canceled or returned.

### • Partial Refunds
This feature lets you refund only part of an order, giving the customer the specific amount they are entitled to.

## Installation
1. Make a backup of your site before applying new mods etc.
2. Go to Admin → Extensions → Installer and upload the downloaded .ocmod.zip file.
3. After uploading the OpenCart Module, you'll find it in the 'Installed Extensions' list. Simply use the '+' button to install the module.
4. Navigate to the "Payments" section by going to Admin → Extensions and choosing "Payments" from the extension type dropdown.
5. Search for the extension labeled "Mastercard Payment Extension" in the list and activate the module installation by clicking the plus symbol.

## Configuration
Please proceed with the following actions to configure the payment method:

1. Log in to your OpenCart administration application.
2. Go to Extension > Extensions.
3. From the extension type filter, choose Payments.
4. Scroll down until you find the 'Mastercard Payment Gateway Services' extension, and click on the Edit button.
5. Fill up the forms with all required fields.
6. After completing all required fields, click the 'Save' button to save the configurations. Once the form is successfully saved, it will redirect you to the 'Extensions' page.

## Disclaimer!

Starting from version 1.3.2, the plugin will collect analytics data, including the plugin download count from GitHub and the active installation count. The Active count, Store Name, Store URL, and the Country configured in the OpenCart Admin page will be captured once the Test Mode is set as No and the Live Merchant ID and Live API Password are saved in the configuration page.


## Hosted Payments

### Payment Gateway Selection

Choose 'Pay Using Mastercard Payment Gateway Services' from the list.

![Hosted Payments Button](docs/gateway_selection.png "Payment Gateway Selection")

Clicking the 'Confirm Order' button triggers the secure payment form, where customers can safely enter their card information.

![Hosted Payments Button](docs/confirm.png "Confirm Order")


### Redirect Payment Page

Users will be directed to a secure page to enter their card details.

![Hosted Payments Checkout View](docs/redirect_page.png "Redirect Page View")


### Embedded Payment Form

A secure payment form is presented directly on the checkout page, allowing customers to input their card details and submit the form to place an order.

![Embedded Payments Checkout View](docs/embeded_page.png "Embedded Payments Checkout View")

### Capture Transaction

To capture the order, navigate to the 'Mastercard Payment Gateway Services' within the 'History' section on the order details page, and then click the "Capture" button.

![Embedded Payments Checkout View](docs/capture.png "Mastercard Capture Order")

### Refund / Partial Refund Transactions

When the order has been captured successfully, the "Refund" and "Partial Refund" buttons will be visible to the admin on the order details page.

![Embedded Payments Checkout View](docs/refunds.png "Embedded Payments Checkout View")
