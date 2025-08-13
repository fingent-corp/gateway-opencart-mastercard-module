<?php
/**
 * Copyright (c) 2019-2026 Mastercard
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
 *
 * @package  Mastercard
 * @version  GIT: @1.3.4@
 * @link     https://github.com/fingent-corp/gateway-opencart-mastercard-module
 */

$_['heading_title']					 = 'Mastercard Gateway';
$_['text_extension']				 = 'Extensions';
$_['text_success']				     = 'Success: You have modified Mastercard Gateway details!';
$_['text_edit']                      = 'Edit Mastercard Gateway';
$_['text_pay']                       = 'Purchase';
$_['text_authorize']                 = 'Authorize';
$_['text_api_eu']                    = 'EU - Europe/UK/MEA';
$_['text_api_ap']                    = 'AP - Asia/Pacific';
$_['text_api_na']                    = 'NA - America';
$_['text_api_mtf']                   = 'MTF - MTF';
$_['text_api_other']                 = 'Gateway URL';
$_['text_redirect']                  = 'Redirect to Payment Page';
$_['text_modal']                     = 'Embedded Form';
$_['text_hostedcheckout']            = 'Hosted Checkout';
$_['text_hostedsession']             = 'Hosted Session';
$_['text_payment_txn_info']          = 'Transactions';
$_['text_payment_mail_info']         = 'Order Details';
$_['text_date_created']              = 'Date';
$_['text_order_ref']                 = 'Order ID';
$_['text_order_merchant']            = 'Merchant Name';
$_['text_order_merchant_id']         = 'Merchant ID';
$_['text_capture_button']            = 'Capture Payment';
$_['text_void_button']               = 'Void';
$_['text_refund_button']             = 'Full Refund';
$_['text_txn_ref']                   = 'Transaction ID';
$_['text_txn_merchant_ref']          = $_['text_order_merchant_id'];
$_['text_txn_type']                  = 'Transaction Type';
$_['text_txn_status']                = 'Transaction Status';
$_['text_txn_amount']                = 'Transaction Amount';
$_['text_refunded_amount']           = 'Refunded Amount';
$_['text_confirm_capture']           = 'Are you sure you want to capture this authorization?';
$_['text_capture_sucess']            = "Mastercard payment CAPTURED (ID: %s, Auth Code: %s)";
$_['text_payment_captured_no_auth']  = "Mastercard payment CAPTURED (ID: %s)";
$_['text_void_sucess']               = "Mastercard payment VOIDED (ID: %s, Auth Code: %s)";
$_['text_void_sucess_no_auth']       = "Mastercard payment VOIDED (ID: %s)";
$_['text_refund_sucess']             = "Mastercard payment REFUNDED (ID: %s, Auth Code: %s)";
$_['text_refund_sucess_no_auth']     = "Mastercard payment REFUNDED (ID: %s)";
$_['text_partial_refund_sucess']     = "Partialy REFUNDED ";
$_['text_partial_refund_error']      = 'Requested amounts exceeds than order amount';
$_['text_confirm_refund_full']       = 'Are you sure you want to request refund?';
$_['text_confirm_void']              = 'Are you sure you want to cancel this Authorization?';
$_['text_txn_actions']               = 'Actions';
$_['text_mastercard'] =
    '<a target="_BLANK" href="https://www.mastercard.com/">
        <img src=".././extension/mastercard/admin/view/image/payment/mastercard.png"
            alt="Mastercard Gateway"
            title="Mastercard Gateway"
            style="border: 1px solid #EEEEEE;" />
    </a>';
$data['placeholder'] = '.././extension/mastercard/admin/view/image/payment/mclogo.svg'; 
$_['help_title']                    = 'This controls the title which the user sees during checkout.';
$_['help_live_notification_secret'] = 'Be sure to enable the WebHook support in
your MasterCard Merchant Administration';
$_['help_test_notification_secret'] = 'Be sure to enable the WebHook support in
your MasterCard Merchant Administration';

$_['help_debug_mode'] = 'Debug logging only works with Sandbox mode.
                        It will log all communication of Mastercard gateway
                        into /storage/logs/mpgs_gateway.log file.';

$_['help_order_id_prefix']           = 'Should be specified in case multiple integrations use the
same Merchant ID';
$_['help_send_line_items']           = 'Include line item details on gateway order';
$_['entry_module_version']           = 'Plugin Version:';
$_['entry_api_version']              = 'API Version:';
$_['entry_status']					 = 'Hosted Checkout';
$_['entry_live_merchant_id']	     = $_['text_order_merchant_id'];
$_['entry_live_api_password']		 = 'API Password';
$_['entry_test_merchant_id']         = 'Test Merchant ID';
$_['entry_test_api_password']        = 'Test API Password';
$_['entry_live_notification_secret'] = 'Webhook Secret';
$_['entry_test_notification_secret'] = 'Test Webhook Secret';
$_['entry_api_gateway']              = 'Gateway';
$_['entry_test']					 = 'Test Mode';
$_['entry_debug']					 = 'Debug Logging';
$_['entry_initial_transaction']      = 'Payment Action';
$_['entry_title']                    = 'Title';
$_['entry_api_gateway_other']        = 'Gateway URL';
$_['entry_sort_order']               = 'Sort Order';
$_['entry_send_line_items']          = 'Send Line Items';
$_['entry_hc_type']                  = 'Checkout Interaction';
$_['entry_integration_model']        = 'Integration Model';
$_['entry_saved_cards']              = 'Saved Cards';
$_['entry_order_id_prefix']          = 'Order ID prefix';
$_['entry_approved_status']          = 'Approved Status';
$_['entry_declined_status']          = 'Declined Status';
$_['entry_pending_status']           = 'Pending Status';
$_['entry_risk_review_status']       = 'Risk Review Required Status';
$_['entry_risk_declined_status']     = 'Declined by Risk Assessment';
$_['send_merchant_info_label']       = 'Merchant Information';
$_['merchant_name_label']            = 'Merchant Name';
$_['merchant_adrs1_label']           = 'Address Line 1';
$_['merchant_adrs2_label']           = 'Address Line 2';
$_['merchant_adrs3_label']           = 'Postal / Zip Code';
$_['merchant_adrs4_label']           = 'Country / State';
$_['merchant_email_label']           = 'Email';
$_['merchant_phone_label']           = 'Phone';
$_['merchant_logo_label']            = 'Logo';
$_['intgrn_sec_head']                = 'Integration Settings';
$_['intgrn_sec_desc']                = 'Configure core settings that control how the payment method
integrates with your store.';
$_['api_creds_head']                 = 'Gateway - API Credentials';
$_['api_creds_desc'] = 'Enter the API credentials required to connect with
the Mastercard Gateway. Learn how to access your
<a href="https://mpgs.fingent.wiki/enterprise/opencart-mastercard-gateway/
configuration/api-configuration" target="_blank">
Gateway API Credentials</a>.';
$_['adtn_conf_head']                 = 'Additional Configurations';
$_['adtn_conf_desc']                 = 'Configure additional plugin parameters for customization.';
$_['error_permission']	             = 'Warning: You do not have permission to modify Mastercard Gateway!';
$_['error_live_merchant_id']         = 'Merchant ID is required.';
$_['error_live_api_password']	     = 'API Password is required.';
$_['error_test_merchant_id']	     = 'Test Merchant ID is required';
$_['error_red_merchant_name']	     = 'Please enter the Merchant Name.';
$_['error_test_api_password']	     = 'Test API Password is Required.';
$_['error_api_gateway_other']	     = "Please enter the Gateway URL.";
$_['error_test_merchant_id_prefix']	 = 'Test Merchant ID must be prefixed with TEST';
$_['error_live_merchant_id_prefix']	 = 'Live Merchant ID must not have TEST prefix';
$_['error_credentials_validation']   = 'API credentials are not valid. Please provide valid credentials.';
$_['error_entry_title']              = 'Enter a Title';
$_['error_warning']                  = 'Warning: Please check the form carefully for errors!';
$_['error_merchant_name']            = 'Merchant name should not exceed 40 characters.';
$_['error_merchant_address_one']     = 'Address line 1 should not  exceed 100 characters.';
$_['error_merchant_address_two']     = 'Address line 2 should not  exceed 100 characters.';
$_['error_merchant_address_postal_zip_code'] = 'Zip code should not  exceed 100 characters.';
$_['error_merchant_country_state']   = 'Country  should not  exceed 100 characters.';
$_['error_merchant_email']           = 'Please enter a valid email address.';
$_['error_merchant_phone']           = 'Please enter a valid phone number.';
$_['error_invalid_image']            = 'Invalid file type. Please upload a supported file format.';
$_['info_title'] = 'Enter the name to be displayed to customers at checkout for this payment method.';
$_['info_hc'] = 'Enable to activate the configuration needed for this payment option as well as enabling the same in the checkout page.';
$_['info_initial_transaction'] = ' In “Purchase”, the customer is charged immediately.
In Authorize, the transaction is only reserved and the capturing of funds
is a manual process that you do using the OpenCart admin panel.';
$_['info_checkout_interaction'] = 'Selecting "Redirect to Payment Page" will also
allow you to configure your business logo and related information in  the Merchant
Information section below.';
$_['info_gateway'] = 'Select the appropriate gateway instance based on your account’s region.';
$_['info_custom_gateway_url'] = ' Enter the Gateway URL shared by your payment service provider. Enter the URL with https prefix. For example <a href="https://na.gateway.mastercard.com/" target="_blank">https://na.gateway.mastercard.com/</a>';
$_['info_debug'] = 'Enable logging of all communication between your site and the Mastercard Gateway.';
$_['info_send_line_items'] = 'Enable to send detailed order information (line items) to the Mastercard Gateway.';
$_['info_test_mode'] = 'Use this to enable Test mode with test credentials for testing purposes.';
$_['info_merchant_id'] = 'Enter your Merchant ID.';
$_['info_test_merchant_id'] = 'Enter your Test Merchant ID.';
$_['info_api_password'] = 'Enter the API Password obtained from your Mastercard Gateway account.
Learn how to access your <a href="https://mpgs.fingent.wiki/enterprise/opencart-mastercard-gateway/
configuration/api-configuration" target="_blank">
Gateway API Credentials</a>.';
$_['info_test_api_password'] = 'Enter the API Password obtained from your Mastercard Gateway account.
Learn how to access your <a href="https://mpgs.fingent.wiki/enterprise/opencart-mastercard-gateway/
configuration/api-configuration" target="_blank">
Gateway API Credentials</a>.';
$_['info_webhook_secret'] = 'Enter the WebHook Secret from your Mastercard Gateway
account. Learn how to access your
<a href="https://mpgs.fingent.wiki/enterprise/opencart-mastercard-gateway/
configuration/api-configuration#webhooksecret" target="_blank">
Webhook Secret</a>.';
$_['info_test_webhook_secret'] = 'Enter the WebHook Secret from your Mastercard Gateway
account. Learn how to access your
<a href="https://mpgs.fingent.wiki/enterprise/opencart-mastercard-gateway/
configuration/api-configuration#webhooksecret" target="_blank">
Webhook Secret</a>.';
$_['info_order_id_prefix'] = 'Specify the order ID prefix.';
$_['info_approved_status'] = 'Declare the label for successfully placed orders.';
$_['info_declined_status'] = 'Declare the label for unsuccessful/failed orders.';
$_['info_pending_status'] = 'Declare the label for pending orders.';
$_['info_risk_review_required_status'] = 'Declare the label for suspicious orders.';
$_['info_declined_by_risk_assessment'] = 'Declare the label for declined orders.';
$_['info_sort_order'] = 'Define the display position of this payment method on the checkout page
(lower number = higher priority).';
$_['info_merchant_info'] = 'This section appears only when
"Redirect to Payment Page" is selected for Checkout Interaction.
Configuring the details below allows them to be displayed on Mastercard’s redirected payment page.';
$_['info_merchant_name'] = 'Name of your business (up to 40 characters) to
be shown to the payer during the payment interaction.';
$_['info_adrs_line_1'] = 'The first line of your business address
(up to 100 characters) to be shown to the payer during the payment interaction.';
$_['info_adrs_line_2'] = 'The second line of your business address (up to 100 characters)
to be shown to the payer during the payment interaction.';
$_['info_postal_code'] = 'The postal or ZIP code of your business address (up to 100 characters)
to be shown to the during the payment interaction.';
$_['info_country_state'] = 'The country or state of your business address (up to 100 characters)
to be shown to the during the payment interaction.';
$_['info_merchant_email'] = 'The email address of your business to be shown to
the payer during the payment interaction
(e.g. an email address for customer service).';
$_['info_merchant_phone'] = 'The phone number of your business (up to 20 characters)
to be shown to the payer during the payment interaction.';
$_['info_merchant_logo'] = 'Upload your business logo (JPEG, PNG, or SVG) to be displayed to the payer during the payment interaction. The logo must be 140×140 pixels.<br>
Logos exceeding 140 pixels will be automatically resized.';






