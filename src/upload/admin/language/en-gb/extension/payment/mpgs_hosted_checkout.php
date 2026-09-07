<?php
/**
 * Copyright (c) 2020 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

if (!defined('TEXT_MERCHANT_ID')) {
    define('TEXT_MERCHANT_ID', 'Merchant ID');
}

// Heading
$_['heading_title']                 = 'Mastercard Gateway';

// Text
$_['text_extension']                = 'Extensions';
$_['text_success']                  = 'Success: You have modified Mastercard Gateway details!';
$_['text_edit']                     = 'Edit Mastercard Gateway';
$_['text_pay']                      = 'Purchase';
$_['text_authorize']                = 'Authorize';
$_['text_api_eu']                   = 'EU - Europe/UK/MEA';
$_['text_api_ap']                   = 'AP - Asia/Pacific';
$_['text_api_na']                   = 'NA - Americas';
$_['text_api_mtf']                  = 'MTF - MTF';
$_['text_api_other']                = 'Custom URL';
$_['text_redirect']                 = 'Redirect to Payment Page';
$_['text_modal']                    = 'Embedded Form';
$_['text_hostedcheckout']           = 'Hosted Checkout';
$_['text_hostedsession']            = 'Hosted Session';
$_['text_payment_txn_info']         = 'Transactions';
$_['text_payment_mail_info']        = 'Order Details';
$_['text_date_created']             = 'Date';
$_['text_order_ref']                = 'Order ID';
$_['text_order_merchant']           = 'Merchant Name';
$_['text_order_merchant_id']        = TEXT_MERCHANT_ID;
$_['text_capture_button']           = 'Capture Payment';
$_['text_void_button']              = 'Void Authorization';
$_['text_refund_button']            = 'Full Refund';
$_['text_txn_ref']                  = 'Transaction ID';
$_['text_txn_merchant_ref']         = TEXT_MERCHANT_ID;
$_['text_txn_type']                 = 'Transaction Type';
$_['text_txn_status']               = 'Transaction Status';
$_['text_txn_amount']               = 'Transaction Amount';
$_['text_refunded_amount']          = 'Refunded Amount';
$_['text_confirm_capture']          = 'Are you sure you want to capture this authorization?';
$_['text_capture_sucess']           = 'Transaction captured successfully';
$_['text_void_success']             = 'Transaction voided successfully';
$_['text_refund_sucess']            = 'Transaction refunded successfully';
$_['text_partial_refund_sucess']    = 'Refunded Partially';
$_['text_partial_refund_error']     = 'Requested amounts exceeds than order amount';
$_['text_confirm_refund_full']      = 'Are you sure you want to request refund?';
$_['text_confirm_void']             = 'Are you sure you want to cancel this AUthorization?';
$_['text_txn_actions']              = 'Actions';
$_['text_mpgs_hosted_checkout']     = <<<'HTML'
<a target="_BLANK" href="https://www.mastercard.com/">
    <img src="view/image/payment/mastercard.png"
         alt="Mastercard Gateway"
         title="Mastercard Gateway"
         style="border: 1px solid #EEEEEE;" />
</a>
HTML;

// Help
$_['help_debug_mode'] = 'Enable logging of all communication between your site and
the Mastercard Gateway.';

$_['help_title'] = 'Enter the name to be displayed to customers at checkout
for this payment method.';
$_['help_live_notification_secret'] = 'Enter the WebHook Secret from your Mastercard Gateway account.';
$_['help_test_notification_secret'] = 'Enter the test WebHook Secret from your Mastercard Gateway account.';
$_['help_order_id_prefix']          = 'Specify the order ID prefix.';
$_['help_send_line_items'] = 'Enable to send detailed order information (line items)
to the Mastercard Gateway.';

// Entry
$_['entry_module_version']          = 'Plugin Version:';
$_['entry_api_version']             = 'API Version:';
$_['entry_status']                  = 'Hosted Checkout';
$_['entry_live_merchant_id']        = TEXT_MERCHANT_ID;
$_['entry_live_api_password']       = 'API Password';
$_['entry_test_merchant_id']        = 'Test Merchant ID';
$_['entry_test_api_password']       = 'Test API Password';
$_['entry_live_notification_secret'] = 'Webhook Secret';
$_['entry_test_notification_secret'] = 'Test Webhook Secret';
$_['entry_api_gateway']             = 'Gateway Instance';
$_['entry_test']                    = 'Test Mode';
$_['entry_debug']                   = 'Debug Logging';
$_['entry_initial_transaction']     = 'Payment Action';
$_['entry_title']                   = 'Title';
$_['entry_api_gateway_other']       = 'Gateway URL';
$_['entry_sort_order']              = 'Sort Order';
$_['entry_send_line_items']         = 'Send Line Items';
$_['entry_hc_type']                 = 'Checkout Interaction';
$_['entry_integration_model']       = 'Integration Model';
$_['entry_saved_cards']             = 'Saved Cards';
$_['entry_order_id_prefix']         = 'Order ID prefix';
$_['entry_approved_status']         = 'Approved Status';
$_['entry_declined_status']         = 'Declined Status';
$_['entry_refund_status']           = 'Refund Status';
$_['entry_pending_status']          = 'Pending Status';
$_['entry_risk_review_status']      = 'Risk review required Status';
$_['entry_risk_declined_status']    = 'Declined by Risk Assessment';
$_['entry_merchant_head']           = 'Merchant Information';
$_['entry_merchant_name']           = 'Merchant Name';
$_['entry_merchant_adrs1']          = 'Address Line 1';
$_['entry_merchant_adrs2']          = 'Address Line 2';
$_['entry_merchant_city']           = 'City';
$_['entry_merchant_postcode']       = 'Postal / Zip Code';
$_['entry_merchant_country']        = 'Country / State';
$_['entry_merchant_email']          = 'Email';
$_['entry_merchant_phone']          = 'Phone';
$_['entry_merchant_logo']           = 'Logo';
$_['error_merchant_name']           = 'Please enter the Title for this payment method.';
$_['error_merchant_email']          = 'Please enter the valid email address.';
$_['error_merchant_phone']          = 'Please enter the valid phone number.';

// Tab
$_['tab_general']                   = 'General';
$_['tab_gateway']                   = 'Gateway Settings';
$_['tab_additional']                = 'Additional Options';

// Error
$_['error_permission']              = 'Warning: You do not have permission to modify Mastercard Gateway!';
$_['error_live_merchant_id']        = 'Merchant ID Required!';
$_['error_live_api_password']       = 'API Password Required!';
$_['error_test_merchant_id']        = 'Test Merchant ID Required!';
$_['error_test_api_password']       = 'Test API Password Required!';
$_['error_api_gateway_other']       = "Please enter the Gateway URL. ";
$_['error_live_merchant_id_prefix'] = 'Merchant ID must not have TEST prefix';
$_['error_credentials_validation']  = 'API credentials are not valid. Please provide valid credentials.';
$_['error_title']                   = 'Payment Title is required.';
$_['error_api_gateway_url_invalid'] = 'Gateway URL must be a valid URL format.';
$_['intgrn_sec_head']               = 'Integration Settings';
$_['intgrn_sec_desc']               = 'Configure core settings that control how the payment
method integrates with your store.';
$_['api_creds_head']                = 'Gateway - API Credentials';
$_['api_creds_desc']                = <<<'TEXT'
Enter the API credentials required to connect with the Mastercard Gateway. Learn how to access your
<a href="https://mpgs.fingent.wiki/enterprise/opencart-mastercard-gateway/configuration/api-configuration"
   target="_blank">Gateway API Credentials</a>.
TEXT;
$_['adtn_conf_head']                = 'Additional Configurations';
$_['adtn_conf_desc']                = 'Configure additional plugin parameters for customization.';
$_['info_hc'] = 'Enable to activate the configuration needed for this payment option
as well as enabling it in checkout.';
$_['info_initial_transaction']      = <<<'TEXT'
In “Purchase”, the customer is charged immediately.
In Authorize, the transaction is only reserved.
Capturing of funds is a manual process done via the OpenCart admin panel.
TEXT;
$_['info_checkout_interaction'] = <<<'TEXT'
Selecting "Redirect to Payment Page" will also allow you to configure your business logo and related information
in the Merchant Information section below.
TEXT;
$_['info_test_mode']                = 'Use this to enable Test mode with test credentials for testing purposes.';
$_['info_custom_gateway_url']       = 'Enter the Gateway URL shared by your payment service provider.';
$_['info_test_merchant_id']         = 'Enter your Test Merchant ID.';
$_['info_merchant_id']              = 'Enter your Merchant ID.';
$_['info_api_password']             = 'Enter the API Password obtained from your Mastercard Gateway account.';
$_['info_test_api_password']        = 'Enter the test API Password obtained from your Mastercard Gateway account.';
$_['info_order_id_prefix']          = 'Specify the order ID prefix.';
$_['info_approved_status']          = 'Declare the label for successfully placed orders.';
$_['info_declined_status']          = 'Declare the label for unsuccessful/failed orders.';
$_['info_pending_status']           = 'Declare the label for pending orders.';
$_['info_refund_status']            = 'Declare the label for refund orders.';
$_['info_sort_order']               = <<<'TEXT'
Define the display position of this payment method on the checkout page (lower number = higher priority).
TEXT;
$_['info_risk_review_required_status'] = 'Declare the label for suspicious orders.';
$_['info_declined_by_risk_assessment'] = 'Declare the label for declined orders.';
$_['info_merchant_desc'] = <<<'TEXT'
This section appears only when "Redirect to Payment Page" is selected for Checkout Interaction.
Configuring the details below allows them to be displayed on Mastercard’s redirected payment page.
TEXT;
$_['info_merchant_name']    = <<<'TEXT'
Name of your business (up to 40 characters) to be shown to the payer during the payment interaction.
TEXT;
$_['info_merchant_adrs1'] = <<<'TEXT'
The first line of your business address (up to 100 characters)
to be shown to the payer during the payment interaction.
TEXT;
$_['info_merchant_adrs2']   = <<<'TEXT'
The second line of your business address (up to 100 characters)
to be shown to the payer during the payment interaction.
TEXT;
$_['info_merchant_email']   = <<<'TEXT'
The email address of your business to be shown to the payer
during the payment interaction (e.g. customer service email).
TEXT;
$_['info_merchant_postcode'] = <<<'TEXT'
The postal or ZIP code of your business address (up to 100 characters)
to be shown to the payer during the payment interaction.
TEXT;
$_['info_merchant_country'] = <<<'TEXT'
The country or state of your business address (up to 100 characters)
to be shown to the payer during the payment interaction.
TEXT;
$_['info_merchant_phone']   = <<<'TEXT'
The phone number of your business (up to 20 characters)
to be shown to the payer during the payment interaction.
TEXT;
$_['info_merchant_logo']    = <<<'TEXT'
Upload your business logo (JPEG, PNG, or SVG) to be displayed to the
payer during the payment interaction. The logo must be 140×140 pixels.
Logos exceeding 140 pixels will be automatically resized.
TEXT;
