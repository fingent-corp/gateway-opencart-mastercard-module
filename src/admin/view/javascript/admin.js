$(document).ready(function () {
    const $gatewaySelect = $('#select-api-gateway');
    const $customUrlContainer = $('#custom-url-container');
    const $sendMerchantCheckbox = $('#input-status');
    const $merchantInfoBlock = $('.merchant-information-data-items');
    
    const $testModeSelect = $('#test-mode');
    const $testMerchantContainer = $('#test-merchant-container');
    const $testPasswordContainer = $('#test-password-container');
    const $liveMerchantContainer = $('#live-merchant-container');
    const $livePasswordContainer = $('#live-password-container');
    
    const $liveWebhookContainer = $('#live-webhook-container');
    const $testWebhookContainer = $('#test-webhook-container');
    
    const $hcTypeSelect = $('#hc-type');
    const $customMerchantInfoContainer = $('.merchant-info-parent-wrapper');
    const $merchantInfoWrapper = $('#merchant-info-wrapper'); 
    
    
    function toggleCredentialFields() {
        const isTest = $testModeSelect.val() === '1';
        $testMerchantContainer.css('display', isTest ? 'flex' : 'none');
        $testPasswordContainer.css('display', isTest ? 'flex' : 'none');
        $testWebhookContainer.css('display', isTest ? 'flex' : 'none');

        $liveMerchantContainer.css('display', isTest ? 'none' : 'flex');
        $livePasswordContainer.css('display', isTest ? 'none' : 'flex');
        $liveWebhookContainer.css('display', isTest ? 'none' : 'flex');
    }

    function toggleCustomMerchantInfo() {
        const isModal = $hcTypeSelect.val() === 'redirect';

        if (isModal) {
            $customMerchantInfoContainer.show(); 
            if ($sendMerchantCheckbox.is(':checked')) {
                $merchantInfoBlock.show();
            } else {
                $merchantInfoBlock.hide();
            }
        } else {
            $customMerchantInfoContainer.hide(); 
            $merchantInfoBlock.hide(); 
        }
    }

    function onMerchantCheckboxChange() {
        if ($sendMerchantCheckbox.is(':checked') && $hcTypeSelect.val() === 'redirect') {
            $merchantInfoBlock.show();
        } else {
            $merchantInfoBlock.hide();
        }
    }

  
    $('#input-status').on('change', function() {
        var $statusLabel = $(this).closest('.form-check').find('.status-label'); 
        if ($(this).prop('checked')) {
            $statusLabel.text('Enabled');
        } else {
            $statusLabel.text('Disabled');
        }
    });

   
    function initializeCheckboxStatus() {
        var $statusLabel = $('#input-status').closest('.form-check').find('.status-label');
        if ($('#input-status').prop('checked')) {
            $statusLabel.text('Enabled');
        } else {
            $statusLabel.text('Disabled');
        }
    }


    $('#payment_mastercard_status').on('change', function() {
        var $statusLabel = $(this).closest('.form-check').find('.status-label'); 
        if ($(this).prop('checked')) {
            $statusLabel.text('Enabled');
        } else {
            $statusLabel.text('Disabled');
        }
    });

    
    function initializePaymentMastercardStatus() {
        var $statusLabel = $('#payment_mastercard_status').closest('.form-check').find('.status-label');
        if ($('#payment_mastercard_status').prop('checked')) {
            $statusLabel.text('Enabled');
        } else {
            $statusLabel.text('Disabled');
        }
    }

 
    toggleCredentialFields();
    toggleCustomMerchantInfo();
    initializeCheckboxStatus(); 
    initializePaymentMastercardStatus(); 

    $testModeSelect.on('change', toggleCredentialFields);
    $hcTypeSelect.on('change', toggleCustomMerchantInfo);
    $sendMerchantCheckbox.on('change', onMerchantCheckboxChange);
});
