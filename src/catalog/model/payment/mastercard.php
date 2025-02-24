<?php
/**
 * Copyright (c) 2023 Mastercard
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

namespace Opencart\Catalog\Model\Extension\Mastercard\Payment;

class Mastercard extends \Opencart\System\Engine\Model {

    const API_AMERICA = 'api_na';
    const API_EUROPE = 'api_eu';
    const API_ASIA = 'api_ap';
    const API_MTF = 'api_mtf';
    const API_OTHER = 'api_other';
    const MODULE_VERSION = '1.3.2';
    const API_VERSION = '100';
    const DEBUG_LOG_FILENAME = 'mpgs_gateway.log';
    const THREEDS_API_VERSION = '1.3.2';
        
    /**
     * getMethods
     *
     * @param  mixed $address
     * @return array
     */
    public function getMethods(array $address = []): array {

        // loading mastercard payment language
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
            // getting payment data using zeo zone
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('mastercard_payment_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

            // if the rows found the status set to True
            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];
        
        if ($status) {
            $option_data['mastercard'] = [
                'code' => 'mastercard.mastercard',
                'name' => $this->config->get('payment_mastercard_title') ?: 'Pay With Mastercard Payment Gateway Services',
            ];

            $method_data = [
                'code'       => 'mastercard',
                'name'       => $this->config->get('payment_mastercard_title') ?: 'Pay with Pay With Mastercard Payment Gateway Services',
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_mastercard_sort_order')
            ];
        }

        return $method_data;
    }

    /**
     * @return mixed
    */

    public function getIntegrationModel(){
        return $this->config->get('payment_mastercard_integration_model');
    }

    /**
     * @return string
     */
    public function getGatewayUri(){

        $gatewayUrl = ''; // Initialize $gatewayUrl before the conditional statements
        $apiGateway = $this->config->get('payment_mastercard_api_gateway');
        if ($apiGateway === self::API_AMERICA) {
            $gatewayUrl = 'https://na-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_EUROPE) {
            $gatewayUrl = 'https://eu-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_ASIA) {
            $gatewayUrl = 'https://ap-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_MTF) {
            $gatewayUrl = 'https://mtf.gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_OTHER) {
            $url = $this->config->get('payment_mastercard_api_gateway_other');
            if (!empty($url) && substr($url, -1) !== '/') {
                $url = $url . '/';
            }
            $gatewayUrl = $url;
        }
        return $gatewayUrl;
    }
    

    /**
     * @return string
     */
    public function getApiUri(){
        return $this->getGatewayUri() . 'api/rest/version/' . $this->getApiVersion() . '/merchant/' . $this->getMerchantId();
    }

    /**
     * @return mixed
     */
    public function getMerchantId(){
        if ($this->isTestModeEnabled()) {
            
            return $this->config->get('payment_mastercard_test_merchant_id');
        } else {
            return $this->config->get('payment_mastercard_live_merchant_id');
        }
    }

    /**
     * @return mixed
    **/

    public function getApiPassword(){
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mastercard_test_api_password');
        } else {
            return $this->config->get('payment_mastercard_live_api_password');
        }
    }

    /**
    * @return mixed
    */

    public function getWebhookSecret(){
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_notification_secret');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_notification_secret');
        }
    }

    /**
     * @return string
     */
    public function getApiVersion(){
        return self::API_VERSION;
    }

    /**
     * @return mixed
     */
    public function isTestModeEnabled(){
        return $this->config->get('payment_mastercard_test');
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled(){
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_debug') === '1';
        }
        return false;
    }

    /**
     * @return string
     */
    public function threeDSApiVersion(){
        return self::THREEDS_API_VERSION;
    }

    /**
     * @return string
     */
    public function getPaymentAction(){
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
    public function buildPartnerSolutionId(){
        return 'OC_' . VERSION . '_MASTERCARD_' . self::MODULE_VERSION;
    }

    /**
     * @param $method
     * @param $uri
     * @param array $data
     * @return mixed
     */
    public function apiRequest($method, $uri, $data = []){
        $userId = 'merchant.' . $this->getMerchantId();
        $requestLog = 'Send Request: "' . $method . ' ' . $uri . '" ';
        if (!empty($data)) {
            $requestLog .= json_encode(['request' => $data]);
          
        }
        $this->log($requestLog);
        $curl = curl_init();
        switch ($method){
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if (!empty($data)) {
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
        $httpResponseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $responseText = 'Receive Response: "' . $httpResponseCode . '" for the request: "' . $method . ' ' . $uri . '" ';
        $responseText .= json_encode(['response' => json_decode($output)]);
        $this->log($responseText);
        return json_decode($output, true);
    }

    /**
     * Clear data from session
     */
    public function clearCheckoutSession(){
        unset($this->session->data['mpgs_hosted_checkout']);
        unset($this->session->data['mpgs_hosted_session']);
        unset($this->session->data['mpgs_hosted_checkout']['successIndicator']);
        unset($this->session->data['mpgs_hosted_checkout']['successIndicator']);
    }

    /**
     * @param $customerId
     * @return mixed
     */
    public function getCustomerTokens($customerId){
        $tokensResult = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mpgs_hpf_token` WHERE customer_id='" . (int)$customerId . "'");
        return $tokensResult->rows;
    }

    /**
     * @param $message
     */
    public function log($message){
        if ($this->isDebugModeEnabled()) {
            $this->debugLog = new Log(self::DEBUG_LOG_FILENAME);
            $this->debugLog->write($message);
        }
    }

    public function getExtensions($type) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE `type` = '" . $this->db->escape($type) . "'");
		return $query->rows;
	}
}
