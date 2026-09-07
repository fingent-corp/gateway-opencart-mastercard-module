/**
  Copyright (c) 2020 Mastercard
 
    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at
 
    http://www.apache.org/licenses/LICENSE-2.0
 
    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
 */

(function ($, window, document) {
    'use strict';

    const MPGSAdmin = {

        cache: {},
        credentials: {},

        init() {
            this.cacheDom();
            this.activateTab();
            this.bindEvents();
            this.initDefaultStates();
        },

        cacheDom() {
            this.cache = {
                tab: $('#tab-mpgs_custom_tab'),

                testMode: $('#test-mode'),
                apiGateway: $('#select-api-gateway'),
                hcType: $('#hc-type'),
                merchantInfo: $('#merchant-info'),
                integrationModel: $('#integration-model'),

                liveMerchant: $('#live-merchant-container'),
                livePassword: $('#live-password-container'),
                liveWebhook: $('#live-webhook-container'),

                testMerchant: $('#test-merchant-container'),
                testPassword: $('#test-password-container'),
                testWebhook: $('#test-webhook-container'),

                gatewayUrl: $('#custom-url-container'),
                savedCards: $('#saved-cards-container'),
                hcTypeContainer: $('#hc-type-container'),

                merchantInfoToggle: $('#merchant-info-toggle-container'),
                merchantInfoSection: $('#merchant-information-section'),
                browserWarning: $('.browser-warning'),

                partialRefundBtn: $('#partialrefundButton'),
                refundBtn: $('#refundButton'),
                refundForm: $('#partail_refund_form'),
                refundInput: $('#refundInput'),
                refundReason: $('.refundReason'),
                refundAmount: $('.refund_amount'),
                cancelRefundBtn: $('.cancel_refund_button'),

                refundBox: $('#refundBoxContainer'),
                refundReasonWrap: $('.refund_reason_container'),
                paymentButtons: $('.payment_button_wrapper'),
                refundFormWrap: $('.refund_form_wrapper'),
                actionWrapper: $('.action_wrapper'),
                charCount: $('#charCount')
            };
        },

        activateTab() {
            if (this.cache.tab.length) {
                $('a[href="#tab-mpgs_custom_tab"]').tab('show');
            }
        },

        bindEvents() {
            this.cache.testMode.on('change', this.toggleTestMode.bind(this));
            this.cache.apiGateway.on('change', this.toggleGatewayUrl.bind(this));
            this.cache.hcType.on('change', this.toggleHCType.bind(this));
            this.cache.merchantInfo.on('change', this.toggleMerchantInfo.bind(this));
            this.cache.integrationModel.on('change', this.toggleIntegrationModel.bind(this));

            this.cache.partialRefundBtn.on('click', this.openPartialRefund.bind(this));
            this.cache.refundInput.on('keypress', this.allowNumbersOnly);
            this.cache.refundForm.on('submit', e => e.preventDefault());
            this.cache.cancelRefundBtn.on('click', this.cancelRefund.bind(this));
            this.cache.refundReason.on('input', this.updateCharCount.bind(this));
            this.cache.refundAmount.on('input', this.validateRefundAmount);
        },

        initDefaultStates() {
            this.cache.testMode.trigger('change');
            this.cache.apiGateway.trigger('change');
            this.cache.hcType.trigger('change');
            this.cache.merchantInfo.trigger('change');
            this.cache.integrationModel.trigger('change');
        },

        toggleTestMode() {
            const isTest = this.cache.testMode.val() === '1';

            if (isTest) {
                // Test mode is ON - show test credentials, hide live credentials
                this.toggleFields(
                    [this.cache.testMerchant, this.cache.testPassword, this.cache.testWebhook],
                    [this.cache.liveMerchant, this.cache.livePassword, this.cache.liveWebhook]
                );
                this.setCredentialVariables('test');
            } else {
                // Test mode is OFF - show live credentials, hide test credentials
                this.toggleFields(
                    [this.cache.liveMerchant, this.cache.livePassword, this.cache.liveWebhook],
                    [this.cache.testMerchant, this.cache.testPassword, this.cache.testWebhook]
                );
                this.setCredentialVariables('live');
            }
        },

        setCredentialVariables(mode) {

            if (mode === 'test') {
                // Test mode variables
                this.credentials = {
                    mode: 'test',
                    merchantId: $('#payment_mpgs_hosted_checkout_test_merchant_id').val() || '',
                    apiPassword: $('#payment_mpgs_hosted_checkout_test_api_password').val() || '',
                    webhookSecret: $('#payment_mpgs_hosted_checkout_test_notification_secret').val() || '',
                    environment: 'sandbox'
                };
            } else if (mode === 'live') {
                // Live mode variables
                this.credentials = {
                    mode: 'live',
                    merchantId: $('#payment_mpgs_hosted_checkout_live_merchant_id').val() || '',
                    apiPassword: $('#payment_mpgs_hosted_checkout_live_api_password').val() || '',
                    webhookSecret: $('#payment_mpgs_hosted_checkout_live_notification_secret').val() || '',
                    environment: 'production'
                };
            }
        },

        getActiveCredentials() {

            return this.credentials;
        },

        getCurrentMode() {

            return this.cache.testMode.val() === '1' ? 'test' : 'live';
        },

        toggleGatewayUrl() {
            this.cache.gatewayUrl.toggle(this.cache.apiGateway.val() === 'api_other');
        },

        toggleHCType() {
            const isModal = this.cache.hcType.val() === 'modal';

            this.cache.browserWarning.toggle(isModal);
            this.cache.merchantInfoToggle.toggle(!isModal);
            
            if (!isModal) {
                // Switching to redirect - restore merchant section based on merchant_info value
                this.toggleMerchantInfo();
            } else {
                // Switching to modal - hide merchant info section
                this.cache.merchantInfoSection.hide();
            }

            if (isModal) {
                setTimeout(() => this.cache.browserWarning.fadeOut(), 30000);
            }
        },

        toggleMerchantInfo() {
            const shouldShow = this.cache.merchantInfo.val() === '1';
            const isMerchantInfoVisible = this.cache.merchantInfoToggle.is(':visible');
            
            // Only toggle if the merchant info toggle container is visible (not in modal mode)
            if (isMerchantInfoVisible) {
                this.cache.merchantInfoSection.toggle(shouldShow);
            }
        },

        toggleIntegrationModel() {
            const isHosted = this.cache.integrationModel.val() === 'hostedcheckout';

            this.cache.savedCards.toggle(!isHosted);
            this.cache.hcTypeContainer.toggle(isHosted);
        },

        toggleFields(showEls, hideEls) {
            showEls.forEach(el => el.show());
            hideEls.forEach(el => el.hide());
        },

        openPartialRefund() {
            this.cache.refundInput.val('');
            this.cache.refundReasonWrap.show();
            this.cache.refundBox.show();
            this.cache.paymentButtons.hide();
            this.cache.refundFormWrap.show();
            this.cache.partialRefundBtn.add(this.cache.refundBtn).hide();
            this.cache.refundForm.add(this.cache.actionWrapper).show();
        },

        cancelRefund() {
            this.cache.refundBtn.add(this.cache.partialRefundBtn).show();
            this.cache.refundForm.add(this.cache.actionWrapper).hide();
        },

        allowNumbersOnly(e) {
            if (!/^[0-9.]$/.test(String.fromCharCode(e.which))) {
                e.preventDefault();
            }
        },

        updateCharCount() {
            const max = 500;
            const val = this.cache.refundReason.val();
            const remaining = max - val.length;

            if (remaining >= 0) {
                this.cache.charCount.text(remaining);
            } else {
                this.cache.refundReason.val(val.substring(0, max));
            }
        },

        validateRefundAmount() {
            let value = $(this).val().replace(/[^0-9.]/g, '');
            const parts = value.split('.');

            if (parts.length > 2) {
                value = value.slice(0, -1);
            }
            if (parts[1] && parts[1].length > 2) {
                parts[1] = parts[1].substring(0, 2);
                value = parts.join('.');
            }
            $(this).val(value);
        }
    };

    $(document).ready(() => MPGSAdmin.init());

})(jQuery, window, document);
