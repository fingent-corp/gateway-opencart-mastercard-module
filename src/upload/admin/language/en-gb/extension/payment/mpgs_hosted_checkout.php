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

// Heading
$_['heading_title']					 = 'Mastercard Gateway Extension';

// Text
$_['text_extension']				 = 'Extensions';
$_['text_success']				     = 'Success: You have modified Mastercard Gateway details!';
$_['text_edit']                      = 'Edit Mastercard Gateway';
$_['text_pay']                       = 'Purchase (Pay)';
$_['text_authorize']                 = 'Authorise';
$_['text_api_eu']                    = 'EU - Europe/UK/MEA';
$_['text_api_ap']                    = 'AP - Asia/Pacific';
$_['text_api_na']                    = 'NA - Americas';
$_['text_api_mtf']                   = 'MTF - MTF';
$_['text_api_other']                 = 'Custom URL';
$_['text_redirect']                  = 'Redirect Payment Page';
$_['text_modal']                     = 'Embedded Page';
$_['text_hostedcheckout']            = 'Hosted Checkout';
$_['text_hostedsession']             = 'Hosted Session';
$_['text_payment_txn_info']          = 'Transactions';
$_['text_payment_mail_info']         = 'Order Details';
$_['text_date_created']              = 'Date';
$_['text_order_ref']                 = 'Order ID';
$_['text_order_merchant']            = 'Merchant Name';
$_['text_order_merchant_id']         = 'Merchant ID';
$_['text_capture_button']            = 'Capture Payment';
$_['text_void_button']               = 'Void Authorization';
$_['text_refund_button']             = 'Full Refund';
$_['text_txn_ref']                   = 'Transaction ID';
$_['text_txn_merchant_ref']          = 'Merchant ID';
$_['text_txn_type']                  = 'Transaction Type';
$_['text_txn_status']                = 'Transaction Status';
$_['text_txn_amount']                = 'Transaction Amount';
$_['text_refunded_amount']           = 'Refunded Amount';
$_['text_confirm_capture']           = 'Are you sure you want to capture this authorization?';
$_['text_capture_sucess']            = 'Transaction captured successfully';
$_['text_refund_sucess']             = 'Transaction refunded successfully';
$_['text_partial_refund_sucess']     = 'Refunded Partially';
$_['text_partial_refund_error']      = 'Requested amounts exceeds than order amount';
$_['text_confirm_refund_full']       = 'Are you sure you want to request refund?';
$_['text_confirm_void']              = 'Are you sure you want to cancel this AUthorization?';
$_['text_txn_actions']               = 'Actions';
$_['text_mpgs_hosted_checkout']      = '<a target="_BLANK" href="https://www.mastercard.com/"><img src="view/image/payment/mastercard.png" alt="Mastercard Gateway" title="Mastercard Gateway" style="border: 1px solid #EEEEEE;" /></a>';

// Help
$_['help_title']                     = 'This controls the title which the user sees during checkout.';
$_['help_live_notification_secret']  = 'Be sure to enable the WebHook support in your MasterCard Merchant Administration';
$_['help_test_notification_secret']  = 'Be sure to enable the WebHook support in your MasterCard Merchant Administration';
$_['help_debug_mode']                = 'Debug logging only works with Sandbox mode. It will log all communication of Mastercard gateway into /storage/logs/mpgs_gateway.log file.';
$_['help_order_id_prefix']           = 'Should be specified in case multiple integrations use the same Merchant ID';
$_['help_send_line_items']           = 'Include line item details on gateway order';

// Help
$_['help_title']                     = 'This controls the title which the user sees during checkout.';
$_['help_live_notification_secret']  = 'Be sure to enable the WebHook support in your MasterCard Merchant Administration';
$_['help_test_notification_secret']  = 'Be sure to enable the WebHook support in your MasterCard Merchant Administration';
$_['help_debug_mode']                = 'Debug logging only works with Sandbox mode. It will log all communication of Mastercard gateway into /storage/logs/mpgs_gateway.log file.';
$_['help_order_id_prefix']           = 'Should be specified in case multiple integrations use the same Merchant ID';
$_['help_send_line_items']           = 'Include line item details on gateway order';

// Entry
$_['entry_module_version']           = 'Module Version:';
$_['entry_api_version']              = 'API Version:';
$_['entry_status']					 = 'Status';
$_['entry_live_merchant_id']	     = 'Live Merchant ID';
$_['entry_live_api_password']		 = 'Live API Password';
$_['entry_test_merchant_id']         = 'Test Merchant ID';
$_['entry_test_api_password']        = 'Test API Password';
$_['entry_live_notification_secret'] = 'Live Notification Secret';
$_['entry_test_notification_secret'] = 'Test Notification Secret';
$_['entry_api_gateway']              = 'Gateway Instance';
$_['entry_test']					 = 'Test Mode';
$_['entry_debug']					 = 'Debug';
$_['entry_initial_transaction']      = 'Initial Transaction';
$_['entry_title']                    = 'Title';
$_['entry_api_gateway_other']        = 'Custom Gateway URL';
$_['entry_sort_order']               = 'Sort Order';
$_['entry_send_line_items']          = 'Submit line item data to gateway';
$_['entry_hc_type']                  = 'Checkout Interaction';
$_['entry_integration_model']        = 'Integration Model';
$_['entry_saved_cards']              = 'Saved Cards';
$_['entry_order_id_prefix']          = 'Order ID prefix';
$_['entry_approved_status']          = 'Approved Status';
$_['entry_declined_status']          = 'Declined Status';
$_['entry_pending_status']           = 'Pending Status';
$_['entry_risk_review_status']       = 'Risk review required Status';
$_['entry_risk_declined_status']     = 'Declined by Risk Assessment';

// Tab
$_['tab_general']				     = 'General';
$_['tab_gateway']				     = 'Gateway Settings';
$_['tab_additional']				 = 'Additional Options';

// Error
$_['error_permission']	             = 'Warning: You do not have permission to modify Mastercard Gateway!';
$_['error_live_merchant_id']         = 'Live Merchant ID Required!';
$_['error_live_api_password']	     = 'Live API Password Required!';
$_['error_test_merchant_id']	     = 'Test Merchant ID Required!';
$_['error_test_api_password']	     = 'Test API Password Required!';
$_['error_api_gateway_other']	     = "Custom Gateway URL must be specified if Gateway is set to 'Custom URL'";
$_['error_test_merchant_id_prefix']	 = 'Test Merchant ID must be prefixed with TEST';
$_['error_live_merchant_id_prefix']	 = 'Live Merchant ID must not have TEST prefix';
$_['error_credentials_validation']   = 'API credentials are not valid. Please provide valid credentials.';
