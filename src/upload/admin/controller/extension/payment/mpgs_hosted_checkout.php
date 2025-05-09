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
 * Class ControllerExtensionPaymentMpgsHostedCheckout
 */
class ControllerExtensionPaymentMpgsHostedCheckout extends Controller
{
    const API_VERSION = '100';
    const MODULE_VERSION = '1.2.0';
    const API_AMERICA = 'api_na';
    const API_EUROPE = 'api_eu';
    const API_ASIA = 'api_ap';
    const API_MTF = 'api_mtf';
    const API_OTHER = 'api_other';
    const DEBUG_LOG_FILENAME = 'mpgs_gateway.log';

    private $error = [];

    public function index()
    {
      
       
        $this->load->language('extension/payment/mpgs_hosted_checkout');
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $this->model_setting_setting->editSetting('payment_mpgs_hosted_checkout', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $this->document->addScript('view/javascript/mpgs-hosted-checkout/custom.js');

        if (isset($this->error['live_merchant_id'])) {
            $data['error_live_merchant_id'] = $this->error['live_merchant_id'];
        } else {
            $data['error_live_merchant_id'] = '';
        }

        if (isset($this->error['live_api_password'])) {
            $data['error_live_api_password'] = $this->error['live_api_password'];
        } else {
            $data['error_live_api_password'] = '';
        }

        if (isset($this->error['test_merchant_id'])) {
            $data['error_test_merchant_id'] = $this->error['test_merchant_id'];
        } else {
            $data['error_test_merchant_id'] = '';
        }

        if (isset($this->error['test_api_password'])) {
            $data['error_test_api_password'] = $this->error['test_api_password'];
        } else {
            $data['error_test_api_password'] = '';
        }

        if (isset($this->error['credentials_validation'])) {
            $data['error_credentials_validation'] = $this->error['credentials_validation'];
        } else {
            $data['error_credentials_validation'] = '';
        }

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/mpgs_hosted_checkout', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['action'] = $this->url->link('extension/payment/mpgs_hosted_checkout', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $data['module_version'] = self::MODULE_VERSION;
        $data['api_version'] = self::API_VERSION;

        if (isset($this->request->post['payment_mpgs_hosted_checkout_status'])) {
            $data['payment_mpgs_hosted_checkout_status'] = $this->request->post['payment_mpgs_hosted_checkout_status'];
        } else {
            $data['payment_mpgs_hosted_checkout_status'] = $this->config->get('payment_mpgs_hosted_checkout_status');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_initial_transaction'])) {
            $data['payment_mpgs_hosted_checkout_initial_transaction'] = $this->request->post['payment_mpgs_hosted_checkout_initial_transaction'];
        } else {
            $data['payment_mpgs_hosted_checkout_initial_transaction'] = $this->config->get('payment_mpgs_hosted_checkout_initial_transaction') ? : 'authorize';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_title'])) {
            $data['payment_mpgs_hosted_checkout_title'] = $this->request->post['payment_mpgs_hosted_checkout_title'];
        } else {
            $data['payment_mpgs_hosted_checkout_title'] = $this->config->get('payment_mpgs_hosted_checkout_title') ? : 'Pay using Mastercard Gateway';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_live_merchant_id'])) {
            $data['payment_mpgs_hosted_checkout_live_merchant_id'] = $this->request->post['payment_mpgs_hosted_checkout_live_merchant_id'];
        } else {
            $data['payment_mpgs_hosted_checkout_live_merchant_id'] = $this->config->get('payment_mpgs_hosted_checkout_live_merchant_id');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_live_api_password'])) {
            $data['payment_mpgs_hosted_checkout_live_api_password'] = $this->request->post['payment_mpgs_hosted_checkout_live_api_password'];
        } else {
            $data['payment_mpgs_hosted_checkout_live_api_password'] = $this->config->get('payment_mpgs_hosted_checkout_live_api_password');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_test_merchant_id'])) {
            $data['payment_mpgs_hosted_checkout_test_merchant_id'] = $this->request->post['payment_mpgs_hosted_checkout_test_merchant_id'];
        } else {
            $data['payment_mpgs_hosted_checkout_test_merchant_id'] = $this->config->get('payment_mpgs_hosted_checkout_test_merchant_id');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_test_api_password'])) {
            $data['payment_mpgs_hosted_checkout_test_api_password'] = $this->request->post['payment_mpgs_hosted_checkout_test_api_password'];
        } else {
            $data['payment_mpgs_hosted_checkout_test_api_password'] = $this->config->get('payment_mpgs_hosted_checkout_test_api_password');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_live_notification_secret'])) {
            $data['payment_mpgs_hosted_checkout_live_notification_secret'] = $this->request->post['payment_mpgs_hosted_checkout_live_notification_secret'];
        } else {
            $data['payment_mpgs_hosted_checkout_live_notification_secret'] = $this->config->get('payment_mpgs_hosted_checkout_live_notification_secret');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_test_notification_secret'])) {
            $data['payment_mpgs_hosted_checkout_test_notification_secret'] = $this->request->post['payment_mpgs_hosted_checkout_test_notification_secret'];
        } else {
            $data['payment_mpgs_hosted_checkout_test_notification_secret'] = $this->config->get('payment_mpgs_hosted_checkout_test_notification_secret');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_api_gateway'])) {
            $data['payment_mpgs_hosted_checkout_api_gateway'] = $this->request->post['payment_mpgs_hosted_checkout_api_gateway'];
        } else {
            $data['payment_mpgs_hosted_checkout_api_gateway'] = $this->config->get('payment_mpgs_hosted_checkout_api_gateway') ? : 'api_eu';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_api_gateway_other'])) {
            $data['payment_mpgs_hosted_checkout_api_gateway_other'] = $this->request->post['payment_mpgs_hosted_checkout_api_gateway_other'];
        } else {
            $data['payment_mpgs_hosted_checkout_api_gateway_other'] = $this->config->get('payment_mpgs_hosted_checkout_api_gateway_other');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_test'])) {
            $data['payment_mpgs_hosted_checkout_test'] = $this->request->post['payment_mpgs_hosted_checkout_test'];
        } else {
            $data['payment_mpgs_hosted_checkout_test'] = $this->config->get('payment_mpgs_hosted_checkout_test');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_integration_model'])) {
            $data['payment_mpgs_hosted_checkout_integration_model'] = $this->request->post['payment_mpgs_hosted_checkout_integration_model'];
        } else {
            $data['payment_mpgs_hosted_checkout_integration_model'] = $this->config->get('payment_mpgs_hosted_checkout_integration_model') ? : 'hostedcheckout';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_hc_type'])) {
            $data['payment_mpgs_hosted_checkout_hc_type'] = $this->request->post['payment_mpgs_hosted_checkout_hc_type'];
        } else {
            $data['payment_mpgs_hosted_checkout_hc_type'] = $this->config->get('payment_mpgs_hosted_checkout_hc_type') ? : 'redirect';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_saved_cards'])) {
            $data['payment_mpgs_hosted_checkout_saved_cards'] = $this->request->post['payment_mpgs_hosted_checkout_saved_cards'];
        } else {
            $data['payment_mpgs_hosted_checkout_saved_cards'] = ($this->config->get('payment_mpgs_hosted_checkout_saved_cards') === '0') ? '0' : '1';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_send_line_items'])) {
            $data['payment_mpgs_hosted_checkout_send_line_items'] = $this->request->post['payment_mpgs_hosted_checkout_send_line_items'];
        } else {
            $data['payment_mpgs_hosted_checkout_send_line_items'] = $this->config->get('payment_mpgs_hosted_checkout_send_line_items');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_sort_order'])) {
            $data['payment_mpgs_hosted_checkout_sort_order'] = $this->request->post['payment_mpgs_hosted_checkout_sort_order'];
        } else {
            $data['payment_mpgs_hosted_checkout_sort_order'] = $this->config->get('payment_mpgs_hosted_checkout_sort_order');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_debug'])) {
            $data['payment_mpgs_hosted_checkout_debug'] = $this->request->post['payment_mpgs_hosted_checkout_debug'];
        } else {
            $data['payment_mpgs_hosted_checkout_debug'] = $this->config->get('payment_mpgs_hosted_checkout_debug');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_order_id_prefix'])) {
            $data['payment_mpgs_hosted_checkout_order_id_prefix'] = $this->request->post['payment_mpgs_hosted_checkout_order_id_prefix'];
        } else {
            $data['payment_mpgs_hosted_checkout_order_id_prefix'] = $this->config->get('payment_mpgs_hosted_checkout_order_id_prefix');
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_approved_status_id'])) {
            $data['payment_mpgs_hosted_checkout_approved_status_id'] = $this->request->post['payment_mpgs_hosted_checkout_approved_status_id'];
        } else {
            $data['payment_mpgs_hosted_checkout_approved_status_id'] = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id') ? : '2';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_declined_status_id'])) {
            $data['payment_mpgs_hosted_checkout_declined_status_id'] = $this->request->post['payment_mpgs_hosted_checkout_declined_status_id'];
        } else {
            $data['payment_mpgs_hosted_checkout_declined_status_id'] = $this->config->get('payment_mpgs_hosted_checkout_declined_status_id') ? : '8';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_pending_status_id'])) {
            $data['payment_mpgs_hosted_checkout_pending_status_id'] = $this->request->post['payment_mpgs_hosted_checkout_pending_status_id'];
        } else {
            $data['payment_mpgs_hosted_checkout_pending_status_id'] = $this->config->get('payment_mpgs_hosted_checkout_pending_status_id') ? : '1';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_risk_review_status_id'])) {
            $data['payment_mpgs_hosted_checkout_risk_review_status_id'] = $this->request->post['payment_mpgs_hosted_checkout_risk_review_status_id'];
        } else {
            $data['payment_mpgs_hosted_checkout_risk_review_status_id'] = $this->config->get('payment_mpgs_hosted_checkout_risk_review_status_id') ? : '1';
        }

        if (isset($this->request->post['payment_mpgs_hosted_checkout_risk_declined_status_id'])) {
            $data['payment_mpgs_hosted_checkout_risk_declined_status_id'] = $this->request->post['payment_mpgs_hosted_checkout_risk_declined_status_id'];
        } else {
            $data['payment_mpgs_hosted_checkout_risk_declined_status_id'] = $this->config->get('payment_mpgs_hosted_checkout_risk_declined_status_id') ? : '8';
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

      

        $this->response->setOutput($this->load->view('extension/payment/mpgs_hosted_checkout_bknd_template', $data));
    }

    /**
     * @return bool
     */
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/mpgs_hosted_checkout')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ($this->request->post['payment_mpgs_hosted_checkout_test']) {
            if (!$this->request->post['payment_mpgs_hosted_checkout_test_merchant_id']) {
                $this->error['test_merchant_id'] = $this->language->get('error_test_merchant_id');
            } elseif (!empty($this->request->post['payment_mpgs_hosted_checkout_test_merchant_id'])) {
                $testMerchantId = $this->request->post['payment_mpgs_hosted_checkout_test_merchant_id'];
                if (stripos($testMerchantId, 'TEST') === FALSE) {
                    $this->error['test_merchant_id'] = $this->language->get('error_test_merchant_id_prefix');
                }
            }
            if (!$this->request->post['payment_mpgs_hosted_checkout_test_api_password']) {
                $this->error['test_api_password'] = $this->language->get('error_test_api_password');
            }
        } else {
            if (!$this->request->post['payment_mpgs_hosted_checkout_live_merchant_id']) {
                $this->error['live_merchant_id'] = $this->language->get('error_live_merchant_id');
            } elseif (!empty($this->request->post['payment_mpgs_hosted_checkout_live_merchant_id'])) {
                $liveMerchantId = $this->request->post['payment_mpgs_hosted_checkout_live_merchant_id'];
                if (stripos($liveMerchantId, 'TEST') !== FALSE) {
                    $this->error['live_merchant_id'] = $this->language->get('error_live_merchant_id_prefix');
                }
            }
            if (!$this->request->post['payment_mpgs_hosted_checkout_live_api_password']) {
                $this->error['live_api_password'] = $this->language->get('error_live_api_password');
            }
        }

        if (!$this->error) {
            $response = $this->paymentOptionsInquiry();

            if (isset($response['result']) && $response['result'] === 'ERROR') {
                if (isset($response['error']['explanation']) && $response['error']['explanation'] == 'Invalid credentials.') {
                    $this->error['credentials_validation'] = $this->language->get('error_credentials_validation');
                } else {
                    $this->error['credentials_validation'] = sprintf('%s: %s', $response['error']['cause'], $response['error']['explanation']);
                }
            }
        }

        return !$this->error;
    }

    public function order(){
        $this->document->addStyle('view/stylesheet/mgps/mastercard.css');
    }

    public function addCustomTab(&$route, &$data, &$output){

        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $this->load->model('localisation/currency');
        $this->session->data['admin_order_id'] = $this->request->get['order_id'];
        $orderIDPrefix = $this->config->get('payment_mpgs_hosted_checkout_order_id_prefix');
        $processed_order_id = $orderIDPrefix . $this->request->get['order_id'];
        $order = $this->model_extension_payment_mpgs_hosted_checkout->getOrder($this->request->get['order_id']);
        $order_info = $this->model_sale_order->getOrder($order);
        $currencies = $this->model_localisation_currency->getCurrencies();
        $defaultCurrencyCode = $this->config->get('config_currency');
        $currencyInfo = $this->model_localisation_currency->getCurrencyByCode($defaultCurrencyCode);
        if ($currencyInfo) {
            $currencySymbol = $currencyInfo['symbol_left'];
            $data['currency'] = $currencyInfo['symbol_left'];
            if (empty($currencySymbol)) {
                $data['currency'] = $currencyInfo['symbol_right'];
            }
        }
        if ($order) {
            $this->load->language('extension/payment/mpgs_hosted_checkout');
            $data['mgps_hosted_checkout_order'] = array(
                'transactions' => $this->model_extension_payment_mpgs_hosted_checkout->getTransactions($this->request->get['order_id'])
            );
            $data['order_id'] = $this->request->get['order_id'];
            $data['user_token'] = $this->request->get['user_token'];
            $this->cache->delete('twig');
            $content = $this->load->view('extension/payment/mpgs_hosted_checkout_order_custom_tab_template', $data);
            if ($order_info && $order_info['payment_code'] == 'mpgs_hosted_checkout') {
                $data['tabs'][] = [
                    'code'    => 'mpgs_custom_tab',
                    'title'   => $this->language->get('heading_title'), 
                    'content' =>$content
                ];
            }
           
        }
    }

    public function install(){
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $this->model_extension_payment_mpgs_hosted_checkout->createTable();
        $this->hook_events();
    }

    public function uninstall(){
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $this->model_extension_payment_mpgs_hosted_checkout->dropTable();
        $this->model_extension_payment_mpgs_hosted_checkout->removeEvents();
    }

    public function hook_events()
    {
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $this->model_extension_payment_mpgs_hosted_checkout->removeEvents();
        $this->model_extension_payment_mpgs_hosted_checkout->addEvents();
    }

    public function paymentOptionsInquiry()
    {
        $uri = $this->getApiUri() . '/paymentOptionsInquiry';
        $requestData = $data['correlationId'] = "sasg753225dut";
        $response = $this->apiRequest('POST', $uri);
        return $response;
    }

    /**
     * @param $apiGateway
     * @return string
     */
    public function getGatewayUri($apiGateway){
        $gatewayUrl = '';
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
    public function getApiUri(){
        $apiGateway = $this->request->post['payment_mpgs_hosted_checkout_api_gateway'];
        return $this->getGatewayUri($apiGateway) . 'api/rest/version/' . self::API_VERSION . '/merchant/' . $this->getMerchantId();
    }

    /**
     * @return mixed
     */
    public function getMerchantId(){
        if ($this->request->post['payment_mpgs_hosted_checkout_test']) {
            return $this->request->post['payment_mpgs_hosted_checkout_test_merchant_id'];
        } else {
            return $this->request->post['payment_mpgs_hosted_checkout_live_merchant_id'];
        }
    }

    /**
     * @return mixed
     */
    public function getApiPassword(){
        if ($this->request->post['payment_mpgs_hosted_checkout_test']) {
            return $this->request->post['payment_mpgs_hosted_checkout_test_api_password'];
        } else {
            return $this->request->post['payment_mpgs_hosted_checkout_live_api_password'];
        }
    }

    /**
     * @return mixed
     */
    public function isTestModeEnabled(){
        return isset($this->request->post['payment_mpgs_hosted_checkout_test']) ? $this->request->post['payment_mpgs_hosted_checkout_test'] : false;
    }


    /**
     * @return bool
     */
    public function isDebugModeEnabled(){
        if ($this->isTestModeEnabled()) {
            return $this->request->post['payment_mpgs_hosted_checkout_debug'] === '1';
        }
        return false;
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
     * @param $method
     * @param $uri
     * @param array $data
     * @return mixed
     */
    public function adminApiRequest($method, $uri, $data = []){
        $apiurlConfig = $this->config->get('payment_mpgs_hosted_checkout_api_gateway');
        $test_mode = $this->config->get('payment_mpgs_hosted_checkout_test');
        $api_password = $test_mode ? $this->config->get('payment_mpgs_hosted_checkout_test_api_password') : $this->config->get('payment_mpgs_hosted_checkout_live_api_password');
        $merchant_id = $test_mode ? $this->config->get('payment_mpgs_hosted_checkout_test_merchant_id') : $this->config->get('payment_mpgs_hosted_checkout_live_merchant_id');
        $userId = 'merchant.' . $merchant_id;
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
        curl_setopt($curl, CURLOPT_USERPWD, $userId . ':' . $api_password);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $output = curl_exec($curl);
        $httpResponseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $responseText = 'Receive Response: "' . $httpResponseCode . '" for the request: "' . $method . ' ' . $uri . '" ';
        $responseText .= json_encode(['response' => json_decode($output)]);
        $this->log($responseText);

        return json_decode($output, true);
    }

    public function capture() {
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        try {
            $this->load->language('extension/payment/mpgs_hosted_checkout');
            $this->load->model('sale/order');
            $comment = $this->language->get('text_capture_sucess');
       
            $merchant_id = $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();
            $capture_order_id = $this->request->post['order_id'];
            $transactionHistory = $this->model_extension_payment_mpgs_hosted_checkout->getTransactions($this->session->data['admin_order_id'] );
         
            foreach ($transactionHistory as $transaction) {
                if ($transaction['type'] === 'AUTHORIZED' && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $capture_amount = $transaction['amount'];
                    $capture_transaction_id = $transaction['transaction_id'];
                    
                }
            }
            $api_version = self::API_VERSION;
            $completed_status_id = $this->model_extension_payment_mpgs_hosted_checkout->getOrderStatusIdByName("Complete");
            $new_order_id = $this->extractOrderNumberFromString($capture_order_id);
            $newTxnId = $this->getUniqueTransactionId($capture_order_id);
            $url =  $this->getCaptureUri() . 'api/rest/version/' . self::API_VERSION . '/merchant/' . $merchant_id . '/order/' . $capture_order_id . '/transaction/' . $newTxnId ;
            $this->load->model('localisation/currency');
            $currencies = $this->model_localisation_currency->getCurrencies();
            $defaultCurrencyCode = $this->config->get('config_currency');
            $smtpHostname = $this->config->get('config_mail_smtp_hostname');
            $smtpPort = $this->config->get('config_mail_smtp_port');
            $mailEngine = $this->config->get('config_mail_engine');
            $notify = "0";
            $requestData = [
                'apiOperation' => 'CAPTURE',
                'transaction' => [
                    'amount' => $capture_amount,
                    'currency' => $defaultCurrencyCode,
                ],
                'order'             => array(

                    'reference'       => $capture_order_id,
                ),
            ];
            $this->log( $requestData);
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            $this->log($response);
    
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $status = $response['order']['status'];
                $mail_type = "Capture";
                $oc_orderId = "";
                $customer_email       = $response['customer']['email'];
                $customer_name        = $response['customer']['firstName'] . ' ' . $response['customer']['lastName'];
                $email_status         = "Payment" . '' .   $status ;
                $this->db->query("UPDATE " . DB_PREFIX . "mgps_order_transaction SET status ='".$status."' , type = 'Captured' WHERE transaction_id = '".$capture_transaction_id."' AND oc_order_id = '".$capture_order_id."' LIMIT 1");
                $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = '" . (int)$completed_status_id . "' WHERE order_id = '" . (int)$new_order_id . "'");
                $this->model_extension_payment_mpgs_hosted_checkout->addOrderHistory($new_order_id, $completed_status_id, $comment, $notify);
                if ($this->config->get('config_mail_engine')) {
                    $this->sendCustomEmail($customer_email, $oc_orderId, $status, $customer_name , $new_order_id,$mail_type );
                }
                $json = array(
                    'error' => false,
                    'msg' => 'Transaction captured successfully'
                );
                $this->response->setOutput(json_encode($json));
            } else {
                throw new \Exception('Transaction capture failed.');
            }
        } catch (\Exception $e) {
            $json = array(
                'error' => true,
                'msg' => 'An error occurred while capturing the transaction'
            );
            $this->response->setOutput(json_encode($json));
        }
    }

    private function sendCustomEmail($reciever_address, $oc_orderId, $subject ,  $customer_name ,  $new_order_id , $mail_type) {
       
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $this->load->model('localisation/currency');
        $orderIDPrefix = $this->config->get('payment_mpgs_hosted_checkout_order_id_prefix');
        $processed_order_id =  $new_order_id ;
        $oc_orderId =  $this->session->data['admin_order_id'] ;
        $order = $this->model_extension_payment_mpgs_hosted_checkout->getOrder(
            $new_order_id 
        );
        $store_name = $this->config->get('config_name');
        $currencies = $this->model_localisation_currency->getCurrencies();
        $defaultCurrencyCode = $this->config->get('config_currency');
        $currencyInfo = $this->model_localisation_currency->getCurrencyByCode($defaultCurrencyCode);
        if ($currencyInfo) {
            $currencySymbol = $currencyInfo['symbol_left'];
            $data['currency'] = $currencyInfo['symbol_left'];
            if (empty($currencySymbol)) {
                $data['currency'] = $currencyInfo['symbol_right'];
            }
        }

        if ($order) {
            $this->load->language('extension/payment/mpgs_hosted_checkout');
            $data['mgps_hosted_checkout_order'] = array(
                'transactions' => $this->model_extension_payment_mpgs_hosted_checkout->getTransactions(
                    $oc_orderId 
                )
            );

            $data['order_id'] = $processed_order_id ;
            $data['user_token'] = $this->request->get['user_token'];
            $data['customer_name']  =$customer_name;
            $data['receiver_address']  = $reciever_address;
            $data['order_status']  = $subject;
            $data['mail_type'] = $mail_type;
           
                if ($this->config->get('config_mail_engine')) {
                    $mail = new Mail($this->config->get('config_mail_engine'));
                    $mail->parameter = $this->config->get('config_mail_parameter');
                    $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                    $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                    $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                    $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                    $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

                    $mail->setTo($data['receiver_address']);
                    $mail->setFrom($this->config->get('config_email'));
                    $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
                    $mail->setSubject(html_entity_decode($store_name . " - Payment " . ucwords(strtolower(str_replace('_', ' ', $subject))), ENT_QUOTES, 'UTF-8'));
                    $mail->setHtml($this->load->view('extension/payment/mgps_hosted_checkout_email_template', $data));
                    $mail->send();
            }
        }
    }

    private function getUniqueTransactionId($orderReference){
     $uniqId = substr(uniqid(), 7, 6);
        return sprintf('%s-%s', $orderReference, $uniqId);
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

    public function extractOrderNumberFromString($completeOrderNumber) {
        $order_prefix = $this->config->get('payment_mpgs_hosted_checkout_order_id_prefix');
        $prefixPos = false; // Initialize with a default value
        
        if (!empty($order_prefix)) {
            $prefixPos = strpos($completeOrderNumber, $order_prefix);
        }
        
        if ($prefixPos !== false) {
            $substring = substr($completeOrderNumber, $prefixPos + strlen($order_prefix));
            $pattern = '/\d+/';
            preg_match($pattern, $substring, $matches);
    
            if (isset($matches[0])) {
                return $matches[0];
            }
        }
    
        return null;
    }

    public function getCaptureUri(){
      
        $apiGateway = $this->config->get('payment_mpgs_hosted_checkout_api_gateway');
        return $this->getGatewayUri($apiGateway);
    }

    public function RequestRefund(){
        try {
            $this->load->language('extension/payment/mpgs_hosted_checkout');
            $this->load->model('extension/payment/mpgs_hosted_checkout');
            $this->load->model('sale/order');
            $capture_order_id = $this->request->post['order_id'];
            $newTxnId = $this->getUniqueTransactionId($capture_order_id);
            $new_order_id = $this->extractOrderNumberFromString($capture_order_id);
            $transactionHistory = $this->model_extension_payment_mpgs_hosted_checkout->getTransactions($this->session->data['admin_order_id'] );
            foreach ($transactionHistory as $transaction) {
               
                if ($transaction['type'] === 'Captured' || 'CAPTURED' && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $capture_amount = $transaction['amount'];
                    $capture_transaction_id = $transaction['transaction_id']; 
                }
            }
            $comment = $this->language->get('text_refund_sucess');
            $merchant_id =$this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();
            $api_version = self::API_VERSION;
            $refund_status_id = $this->model_extension_payment_mpgs_hosted_checkout->getOrderStatusIdByName("Refunded");
            $this->load->model('localisation/currency');
            $currencies = $this->model_localisation_currency->getCurrencies();
            $defaultCurrencyCode = $this->config->get('config_currency');
            $notify = "0";
            $mailEngine = $this->config->get('config_mail_engine');
            $smtpHostname = $this->config->get('config_mail_smtp_hostname');
            $smtpPort = $this->config->get('config_mail_smtp_port');
            $url =  $this->getCaptureUri() . 'api/rest/version/' . self::API_VERSION . '/merchant/' . $merchant_id . '/order/' . $capture_order_id . '/transaction/' . $newTxnId ;
            $requestData = [
                'apiOperation' => 'REFUND',
                'transaction' => [
                    'amount' => $capture_amount,
                    'currency' => $defaultCurrencyCode,
                ]
            ];
            $this->log( $requestData);
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            $this->log( $response);
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $transactionStatus = $response['order']['status'];
                $status = $response['order']['status'];
                $mail_type = "Refund";
                $oc_orderId = "";
                $customer_email       = $response['customer']['email'];
                $customer_name        = $response['customer']['firstName'] . ' ' . $response['customer']['lastName'];
                $email_status         = "Payment" . '' .   $status ;
                $refundedAmount = $response['order']['totalRefundedAmount'];
                $this->db->query("UPDATE " . DB_PREFIX . "mgps_order_transaction SET status = '".$transactionStatus."' , refunded_amount = '".$refundedAmount."' , type = 'Captured' WHERE transaction_id = '".$capture_transaction_id."' AND oc_order_id = '".$capture_order_id."' LIMIT 1");
                $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = '" . (int)$refund_status_id . "' WHERE order_id = '" . (int)$new_order_id . "'");
                $this->model_extension_payment_mpgs_hosted_checkout->addOrderHistory($new_order_id, $refund_status_id, $comment, $notify);
                if ($this->config->get('config_mail_engine')) {
                    
                    $this->sendCustomEmail($customer_email,$oc_orderId, $status, $customer_name , $new_order_id,$mail_type );
                }
        
                $json = array(
                    'error' => false,
                    'msg' => 'Transaction refunded successfully'
                );
                $this->response->setOutput(json_encode($json));
            } else {
                $json = array(
                    'error' => true,
                    'msg' => 'Transaction refund failed'
                );
                $this->response->setOutput(json_encode($json));
            }

          
        } catch (\Exception $e) {
            $json = array(
                'error' => true,
                'msg' => 'An error occurred while processing the refund'
            );
            $this->response->setOutput(json_encode($json));
        }
    }

    public function RequestPartialRefund() {
        try {
            $this->load->language('extension/payment/mpgs_hosted_checkout');
            $this->load->model('extension/payment/mpgs_hosted_checkout');
            $this->load->model('sale/order');
            $this->load->model('localisation/currency');
            $capture_order_id = $this->request->post['order_id'];
            $newTxnId = $this->getUniqueTransactionId($capture_order_id);
            $new_order_id = $this->extractOrderNumberFromString($capture_order_id);
            $merchant_id = $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();
            $transactionHistory = $this->model_extension_payment_mpgs_hosted_checkout->getTransactions($this->session->data['admin_order_id'] );
            foreach ($transactionHistory as $transaction) {
                if ($transaction['type'] === 'Captured' || 'CAPTURED' && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $capture_transaction_id = $transaction['transaction_id']; 
                }
            }
            $capture_amount  = $this->request->post['amount'];
            $api_version = self::API_VERSION;
            $refund_status_id = $this->model_extension_payment_mpgs_hosted_checkout->getOrderStatusIdByName("Refunded");
            $currencies = $this->model_localisation_currency->getCurrencies();
            $defaultCurrencyCode = $this->config->get('config_currency');
            $currencyInfo = $this->model_localisation_currency->getCurrencyByCode($defaultCurrencyCode);
            $smtpHostname = $this->config->get('config_mail_smtp_hostname');
            $smtpPort = $this->config->get('config_mail_smtp_port');
            $mailEngine = $this->config->get('config_mail_engine');
            if ($currencyInfo) {
                $currencySymbol = $currencyInfo['symbol_left'];
                if (empty($currencySymbol)) {
                    $currencySymbol = $currencyInfo['symbol_right'];
                }
            }
            $comment = $currencySymbol .$capture_amount . ' ' . $this->language->get('text_partial_refund_sucess');
            if (!empty($this->request->post['reason'])) {
                $comment .= "\nRefund reason: " . $this->request->post['reason'];
            }
            $notify = "0";
    
            $url =  $this->getCaptureUri() . 'api/rest/version/' . self::API_VERSION . '/merchant/' . $merchant_id . '/order/' . $capture_order_id . '/transaction/' . $newTxnId ;
            $requestData = [
                'apiOperation' => 'REFUND',
                'transaction' => [
                    'amount' => $capture_amount ,
                    'currency' => $defaultCurrencyCode,
                    'taxAmount' => '0'
                ]
            ];
            $this->log( $requestData);
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            $this->log( $response);
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $transactionStatus = $response['order']['status'];
                $refundedAmount = $response['order']['totalRefundedAmount'];
                $status = $response['order']['status'];
                $mail_type = "Refund";
                $oc_orderId = "";
                $customer_email       = $response['customer']['email'];
                $customer_name        = $response['customer']['firstName'] . ' ' . $response['customer']['lastName'];
                $email_status         = "Payment" . '' .   $status ;
                $this->db->query("UPDATE " . DB_PREFIX . "mgps_order_transaction SET status = '".$transactionStatus."' ,refunded_amount = '".$refundedAmount."', type = 'Captured' WHERE transaction_id = '".$capture_transaction_id."' AND oc_order_id = '".$capture_order_id."' LIMIT 1");
                $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = '" . (int)$refund_status_id . "' WHERE order_id = '" . (int)$new_order_id . "'");
                $this->model_extension_payment_mpgs_hosted_checkout->addOrderHistory($new_order_id, $refund_status_id, $comment, $notify);
                if ($this->config->get('config_mail_engine')) {
                    $this->sendCustomEmail($customer_email,$oc_orderId, $status, $customer_name , $new_order_id,$mail_type );
                }
    
                $json = array(
                    'error' => false,
                    'msg' => 'Transaction Partially Refunded'
                );
                $this->response->setOutput(json_encode($json));
            } elseif (!empty($response['result']) && $response['result'] === 'ERROR') {
                $json = array(
                    'error' => false,
                    'msg' => 'Requested amount Exceeds than order amount'
                );
                $this->response->setOutput(json_encode($json));
            }
        } catch (\Exception $e) {
            // Handle exceptions here, e.g., log the error or display a user-friendly message
            $json = array(
                'error' => true,
                'msg' => 'An error occurred while processing the refund'
            );
            $this->response->setOutput(json_encode($json));
        }
    }
}
