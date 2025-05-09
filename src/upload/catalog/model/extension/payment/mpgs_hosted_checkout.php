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

/**
 * Class ModelExtensionPaymentMpgsHostedCheckout
 */
class ModelExtensionPaymentMpgsHostedCheckout extends Model
{
    const API_AMERICA = 'api_na';
    const API_EUROPE = 'api_eu';
    const API_ASIA = 'api_ap';
    const API_MTF = 'api_mtf';
    const API_OTHER = 'api_other';
    const MODULE_VERSION = '1.2.0';
    const API_VERSION = '100';
    const DEBUG_LOG_FILENAME = 'mpgs_gateway.log';
    const THREEDS_API_VERSION = '1.3.0';

    /**
     * @return array
     */
    public function getMethod()
    {
        $method_data = [
            'code' => 'mpgs_hosted_checkout',
            'title' => $this->config->get('payment_mpgs_hosted_checkout_title'),
            'terms' => '',
            'sort_order' => $this->config->get('payment_mpgs_hosted_checkout_sort_order')
        ];

        return $method_data;
    }

    /**
     * @return mixed
     */
    public function getIntegrationModel()
    {
        return $this->config->get('payment_mpgs_hosted_checkout_integration_model');
    }

    /**
     * @return string
     */
    public function getGatewayUri()
    {
        $apiGateway = $this->config->get('payment_mpgs_hosted_checkout_api_gateway');
        if ($apiGateway === self::API_AMERICA) {
            $gatewayUrl = 'https://na-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_EUROPE) {
            $gatewayUrl = 'https://eu-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_ASIA) {
            $gatewayUrl = 'https://ap-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_MTF) {
            $gatewayUrl = 'https://mtf.gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_OTHER) {
            $url = $this->config->get('payment_mpgs_hosted_checkout_api_gateway_other');
            if (!empty($url)) {
                if (substr($url, -1) !== '/') {
                    $url = $url . '/';
                }
            }
            $gatewayUrl = $url;
        }

        return $gatewayUrl;
    }

    /**
     * @return string
     */
    public function getApiUri()
    {
        return $this->getGatewayUri() . 'api/rest/version/' . $this->getApiVersion() . '/merchant/' . $this->getMerchantId();
    }

    /**
     * @return mixed
     */
    public function getMerchantId()
    {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_merchant_id');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_merchant_id');
        }
    }

    /**
     * @return mixed
     */
    public function getApiPassword()
    {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_api_password');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_api_password');
        }
    }

    /**
     * @return mixed
     */
    public function getWebhookSecret()
    {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_notification_secret');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_notification_secret');
        }
    }

    /**
     * @return string
     */
    public function getApiVersion()
    {
        return self::API_VERSION;
    }

    /**
     * @return mixed
     */
    public function isTestModeEnabled()
    {
        return $this->config->get('payment_mpgs_hosted_checkout_test');
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled()
    {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_debug') === '1';
        }
        return false;
    }

    /**
     * @return string
     */
    public function threeDSApiVersion()
    {
        return self::THREEDS_API_VERSION;
    }

    /**
     * @return string
     */
    public function getPaymentAction()
    {
        $paymentAction = $this->config->get('payment_mpgs_hosted_checkout_initial_transaction');
        if ($paymentAction === 'pay') {
            return 'PURCHASE';
        } else {
            return 'AUTHORIZE';
        }
    }

    /**
     * @return string
     */
    public function buildPartnerSolutionId()
    {
        return 'OC_' . VERSION . '_ONTAP_' . self::MODULE_VERSION;
    }

    /**
     * @param $method
     * @param $uri
     * @param array $data
     * @return mixed
     */
    public function apiRequest($method, $uri, $data = [])
    {
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
    public function clearCheckoutSession()
    {
        unset($this->session->data['mpgs_hosted_checkout']);
        unset($this->session->data['mpgs_hosted_session']);
        unset($this->session->data['mpgs_hosted_checkout']['successIndicator']);
    }

    /**
     * @param $customerId
     * @return mixed
     */
    public function getCustomerTokens($customerId)
    {
        $tokensResult = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mpgs_hpf_token` WHERE customer_id='" . (int)$customerId . "'");
        return $tokensResult->rows;
    }

    /**
     * @return mixed
     */
    public function isSavedCardsEnabled()
    {
        return $this->config->get('payment_mpgs_hosted_checkout_saved_cards');
    }

    /**
     * @param $message
     */
    public function log($message)
    {
        if ($this->isDebugModeEnabled()) {
            $this->debugLog = new Log(self::DEBUG_LOG_FILENAME);
            $this->debugLog->write($message);
        }
    }
}
