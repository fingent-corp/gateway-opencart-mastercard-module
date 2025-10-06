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

// Text
$_['text_credit_card']               = 'Credit Card Details';
$_['text_card_type']                 = '%s';
$_['text_card_label']                = 'ending in %s';
$_['text_card_expiry']               = '(expires %s)';
$_['text_use_new_card']              = 'Use a new payment method';
$_['text_save_card']                 = 'Save to account';
$_['text_secure_payment']            = 'Processing Secure Payment';
$_['text_risk_review_rejected']      = 'Mastercard payment transaction %s by Risk Assessment (ID: %s, Type: %s)';
$_['text_risk_review_required']      = 'Mastercard payment transaction risk %s (ID: %s, Type: %s)';
$_['text_not_allow_authorization']   = "Order state '%s' does not allow authorization";
$_['text_not_allow_capture']         = "Order state '%s' does not allow capture";
$_['text_not_allow_refund']          = "Order state '%s' does not allow refund";
$_['text_not_allow_void']            = "Order state '%s' does not allow void";
$_['text_webhook_authorize_capture'] = "Mastercard payment '%s' %s from gateway (ID: %s, AuthCode: %s) by Webhook";
$_['text_webhook_refund_void']       = "Mastercard payment '%s' %s from gateway (ID: %s) by Webhook";
$_['text_webhook_unknown']           = "Received unknown transaction.type '%s' by Webhook";
$_['text_payment_captured']          = 'Mastercard payment CAPTURED (ID: %s, Auth Code: %s)';
$_['text_payment_authorized']        = "Mastercard payment AUTHORIZED (ID: %s, Auth Code: %s)";
$_['text_payment_unknown']           = "Mastercard payment %s (ID: %s)";

// Entry
$_['entry_cc_number']                = 'Card number';
$_['entry_expiry_month']             = 'Expiry (MM)';
$_['entry_expiry_year']              = 'Expiry (YY)';
$_['entry_security_code']            = 'Card Security Code';
$_['entry_cardholder_name']          = 'Name on Card';

// Error
$_['error_indicator_mismatch']       = 'Result indicator has mismatched.';
$_['error_card_number']              = 'Invalid Card Number';
$_['error_expiry_month']             = 'Invalid Expiry Month';
$_['error_expiry_year']              = 'Invalid Expiry Year';
$_['error_security_code']            = 'Invalid Security Code';
$_['error_payment_declined']         = 'Payment has declined';
$_['error_token_not_present']        = 'Token not present in response';
$_['error_unexpected']               = 'Unexpected payment condition error.';
$_['error_payment_declined_3ds']     = 'Payment declined (3DS). Please try another card.';
$_['error_payment_general']          = 'Payment general error, please try again.';
$_['error_request_method']           = 'Only POST is allowed';
$_['error_insecure_connection']      = 'Failed - Connection is not secure';
$_['error_secret_mismatch']          = 'Invalid or missing webhook secret';
$_['error_merchant_mismatch']        = 'Webhook merchant ID does not match with configured merchant ID';
$_['error_invalid_order']            = 'Invalid data received (order.id)';
$_['error_invalid_transaction']      = 'Invalid data received (transaction.id)';
$_['error_transaction_unsuccessful'] = 'Your transaction was unsuccessful, please check your details and try again.';
