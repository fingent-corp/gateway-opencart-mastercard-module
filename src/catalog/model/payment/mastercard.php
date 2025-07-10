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
 * @version  GIT: @1.3.3@
 * @link     https://github.com/fingent-corp/gateway-opencart-mastercard-module
 */

namespace Opencart\Catalog\Model\Extension\Mastercard\Payment;

class Mastercard extends \Opencart\System\Engine\Model {

    const API_AMERICA = 'api_na';
    const API_EUROPE = 'api_eu';
    const API_ASIA = 'api_ap';
    const API_MTF = 'api_mtf';
    const API_OTHER = 'api_other';
    const MODULE_VERSION = '1.3.3';
    const API_VERSION = '100';
    const DEBUG_LOG_FILENAME = 'mpgs_gateway.log';
    const THREEDS_API_VERSION = '1.3.3';
        
    /**
     * getMethods
     *
     * @param  mixed $address
     * @return array
     */
    public function getMethods(array $address = []): array {
        $this->load->language('extension/mastercard/payment/mastercard');
    
        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->cart->hasShipping()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('mastercard_payment_geo_zone_id')) {
            $status = true;
        } else {
            $geoZoneId = (int)$this->config->get('mastercard_payment_geo_zone_id');
            $countryId = (int)$address['country_id'];
            $zoneId = (int)$address['zone_id'];
    
            $query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone`
                 WHERE `geo_zone_id` = '{$geoZoneId}'
                 AND `country_id` = '{$countryId}'
                 AND (`zone_id` = '{$zoneId}' OR `zone_id` = '0')"
            );
    
            $status = $query->num_rows > 0;
        }
    
        $methodData = [];
    
        if ($status) {
            $title = $this->config->get('payment_mastercard_title') ?: 'Pay with Pay With Mastercard Gateway';
    
            $optionData['mastercard'] = [
                'code' => 'mastercard.mastercard',
                'name' => $title,
            ];
    
            $methodData = [
                'code'       => 'mastercard',
                'name'       => $title,
                'option'     => $optionData,
                'sort_order' => $this->config->get('payment_mastercard_sort_order')
            ];
        }
    
        return $methodData;
    }
    

    /**
     * @return mixed
    */

    public function getIntegrationModel() {
        return 'hostedcheckout';
    }

    /**
     * @return string
     */
    public function getGatewayUri() {
        $gatewayUrl = $this->config->get('payment_mastercard_api_gateway_other');
        return $gatewayUrl;
    }
    
    /**
     * @return string
     */
    public function getApiUri() {
        return $this->getGatewayUri()
             . 'api/rest/version/' . $this->getApiVersion()
             . '/merchant/' . $this->getMerchantId();
    }

    /**
     * @return mixed
     */
    public function getMerchantId() {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mastercard_test_merchant_id');
        } else {
            return $this->config->get('payment_mastercard_live_merchant_id');
        }
    }

    /**
     * @return mixed
    **/

    public function getApiPassword() {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mastercard_test_api_password');
        } else {
            return $this->config->get('payment_mastercard_live_api_password');
        }
    }

    /**
    * @return mixed
    */
    public function getWebhookSecret() {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_notification_secret');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_notification_secret');
        }
    }

    /**
     * @return string
     */
    public function getApiVersion() {
        return self::API_VERSION;
    }

    /**
     * @return mixed
     */
    public function isTestModeEnabled() {
        return $this->config->get('payment_mastercard_test');
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled() {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_debug') === '1';
        }
        return false;
    }

    /**
     * @return string
     */
    public function threeDSApiVersion() {
        return self::THREEDS_API_VERSION;
    }

    /**
     * @return string
     */
    public function getPaymentAction() {
        $paymentAction = $this->config->get('payment_mastercard_initial_transaction');
        if ($paymentAction === 'pay') {
            return 'PURCHASE';
        } else {
            return 'AUTHORIZE';
        }
    }

    /**
     * @return string
     */
    public function buildPartnerSolutionId() {
        return 'OC_' . VERSION . '_MASTERCARD_' . self::MODULE_VERSION;
    }

    /**
     * @param $method
     * @param $uri
     * @param array $data
     * @return mixed
     */
    public function apiRequest($method, $uri, $data = []) {
        $userId = 'merchant.' . $this->getMerchantId();
    
        $curl = curl_init();
        switch ($method) {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if (!empty($data)){
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            default:
                break;
        }
    
        curl_setopt($curl, CURLOPT_URL, $uri);
        curl_setopt($curl, CURLOPT_USERPWD, $userId . ':' . $this->getApiPassword());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    
        return json_decode($output, true);
    }
    
    /**
     * Clear data from session
     */
    public function clearCheckoutSession() {
        unset($this->session->data['mpgs_hosted_checkout']);
        unset($this->session->data['mpgs_hosted_session']);
        unset($this->session->data['mpgs_hosted_checkout']['successIndicator']);
        unset($this->session->data['mpgs_hosted_checkout']['successIndicator']);
    }

    /**
     * @param $customerId
     * @return mixed
     */
    public function getCustomerTokens($customerId) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "mpgs_hpf_token`
                WHERE customer_id = '" . (int)$customerId . "'";
        $tokensResult = $this->db->query($sql);
        return $tokensResult->rows;
    }


    /**
     * @param $message
     */
    public function log($message) {
        if ($this->isDebugModeEnabled()) {
            $this->debugLog = new Log(self::DEBUG_LOG_FILENAME);
            $this->debugLog->write($message);
        }
    }

    public function getExtensions($type) {
        $escapedType = $this->db->escape($type);
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE `type` = '" . $escapedType . "'");
        return $query->rows;
    }

}
