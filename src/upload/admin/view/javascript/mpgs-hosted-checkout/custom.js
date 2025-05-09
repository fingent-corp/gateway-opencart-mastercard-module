/*
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

$(function ($) {
    'use strict';
    if ($('#tab-mpgs_custom_tab').length > 0) {
        $('a[href="#tab-mpgs_custom_tab"]').tab('show');
    }
    var mpgs_admin_config = {
        init: function () {
            var liveMerchantId = $('#live-merchant-container'),
                livePassword = $('#live-password-container'),
                liveWebhookSecret = $('#live-webhook-container'),
                testMerchantId = $('#test-merchant-container'),
                testPassword = $('#test-password-container'),
                testWebhookSecret = $('#test-webhook-container'),
                gateway_url = $('#custom-url-container'),
                saved_cards = $('#saved-cards-container'),
                hc_type = $('#hc-type-container');

            $('#test-mode').on('change', function () {
                if ($(this).val() === '1') {
                    testMerchantId.show();
                    testPassword.show();
                    testWebhookSecret.show();
                    testMerchantId.addClass('required');
                    testPassword.addClass('required');

                    // Hide Live Merchant ID, Password & Webhook Secret
                    liveMerchantId.hide();
                    livePassword.hide();
                    liveWebhookSecret.hide();
                    liveMerchantId.removeClass('required');
                    livePassword.removeClass('required');
                } else {
                    liveMerchantId.show();
                    livePassword.show();
                    liveWebhookSecret.show();
                    liveMerchantId.addClass('required');
                    livePassword.addClass('required');

                    // Hide Test Merchant ID, Password & Webhook Secret
                    testMerchantId.hide();
                    testPassword.hide();
                    testWebhookSecret.hide();
                    testMerchantId.removeClass('required');
                    testPassword.removeClass('required');
                }
            }).change();

            $('#select-api-gateway').on('change', function () {
                if ($(this).val() === 'api_other') {
                    gateway_url.show();
                } else {
                    gateway_url.hide();
                }
            }).change();

            $('#integration-model').on('change', function () {
                if ($(this).val() === 'hostedcheckout') {
                    saved_cards.hide();
                    hc_type.show();
                } else {
                    hc_type.hide();
                    saved_cards.show();
                }
            }).change();
        }
    };
    mpgs_admin_config.init();

    $(document).ready(function() {
        var userToken = $('script[src$="custom.js"]').data('user-token');
        $("#partialrefundButton").on("click", function() {
            var maxrefundAmount = parseInt($(this).data("amount"));
            $('.refund_reason_container').show();
            $("#refundInput").val(""); 
            $("#refundBoxContainer").show();
            $('.payment_button_wrapper').hide();
            $('.refund_form_wrapper').show();
            $('#partialrefundButton,#refundButton').hide();
            $('#partail_refund_form,.action_wrapper').show();
        });

    
        $('#refundInput').on("keypress", function(event) {
            const allowedCharsRegex = /^[0-9.]+$/;
            const enteredChar = String.fromCharCode(event.which);
            if (!allowedCharsRegex.test(enteredChar)) {
            event.preventDefault();
            }
        });

        $('#partail_refund_form').on('submit', function(event) {
            event.preventDefault(); 
        });

        $('.cancel_refund_button').click(function(){
            $('#refundButton,#partialrefundButton').show();
            $('#partail_refund_form,.action_wrapper').hide();
        });
        
        $('.refundReason').on('input', function() {
            var maxLength = 500;
            var currentLength = $(this).val().length;
            var remaining = maxLength - currentLength;
    
            if (remaining >= 0) {
                $('#charCount').text(remaining);
            } else {
                var text = $(this).val().substring(0, maxLength);
                $(this).val(text);
            }
        });

        $(".refund_amount").on("input", function () {
            // Remove any non-numeric characters except the decimal point
            $(this).val($(this).val().replace(/[^0-9.]/g, ''));
    
            // Ensure that there's only one decimal point
            if ($(this).val().split('.').length > 2) {
                $(this).val($(this).val().slice(0, -1)); // Remove the last character
            }
    
            // Limit to a maximum of two decimal places
            const parts = $(this).val().split('.');
            if (parts[1] && parts[1].length > 2) {
                parts[1] = parts[1].substring(0, 2);
                $(this).val(parts.join('.'));
            }
        });
        
    });
});