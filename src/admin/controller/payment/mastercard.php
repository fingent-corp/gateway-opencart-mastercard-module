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
namespace Opencart\Admin\Controller\Extension\MasterCard\Payment;
use Opencart\Admin\Model\Extension\MasterCard\Payment;

class MasterCard extends \Opencart\System\Engine\Controller {
    
    const API_VERSION = '100';
    const MODULE_VERSION = '1.3.2';
    const API_AMERICA = 'api_na';
    const API_EUROPE = 'api_eu';
    const API_ASIA = 'api_ap';
    const API_MTF = 'api_mtf';
    const API_OTHER = 'api_other';
    const DEBUG_LOG_FILENAME = 'mpgs_gateway.log';
    private array $error = [];
    private $separator = '';
    public function __construct($registry) {
        parent::__construct($registry);

		if (VERSION >= '4.0.2.0') {
			$this->separator = '.';
		} else {
			$this->separator = '|';
		}
    }

    public function index(): void {
        $this->install();
        $this->load->language('extension/mastercard/payment/mastercard');
        $this->load->model('extension/mastercard/payment/mastercard');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('../extension/mastercard/admin/view/stylesheet/mastercard.css');
        $this->load->model('setting/setting');
        $latestVersion = $this->getLatestGitHubVersion();
        $data['latest_version'] =  $latestVersion ;
        $currentVersion = "";
        $data['update_message'] = $this->compareVersions($latestVersion, $currentVersion);

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->load->model('localisation/country');
            $this->load->model('setting/setting');
        
            $countryCode = $this->config->get('config_country_id');
            $countryInfo = $this->model_localisation_country->getCountry($countryCode);
        
            $this->model_setting_setting->editSetting('payment_mastercard', $this->request->post);
            $repoName = 'gateway-opencart-mastercard-module';
            $pluginType = 'enterprise';
            $tagName = self::MODULE_VERSION;
            $latestRelease = '1';
        
            // Retrieve the saved version and credentials from the settings
            $storedSettings = $this->model_setting_setting->getSetting('payment_mastercard');
            $storedTagName = isset($storedSettings['payment_mastercard_version']) ? $storedSettings['payment_mastercard_version'] : null;
            $merchantId = isset($storedSettings['payment_mastercard_live_merchant_id']) ? $storedSettings['payment_mastercard_live_merchant_id'] : null;
            $apiPassword = isset($storedSettings['payment_mastercard_live_api_password']) ? $storedSettings['payment_mastercard_live_api_password'] : null;
        
            // Check if credentials are available and version is different
            if (!empty($merchantId) && !empty($apiPassword) && $storedTagName !== $tagName) {
                $country = isset($countryInfo['name']) ? $countryInfo['name'] : '';
                $countryCode = isset($countryInfo['iso_code_2']) ? $countryInfo['iso_code_2'] : '';
                $shopName = $this->config->get('config_name');
                $shopUrl = HTTP_CATALOG;
                $apiUrl = 'https://mpgs.fingent.wiki/wp-json/mpgs/v2/update-repo-status';
                $apiToken = '9b0f63f09df782c94cfa517da614ca510c40f5bbff9990752caa6f5961aadd1c';
        
                $payload = json_encode([
                    'repo_name'      => $repoName,
                    'plugin_type'    => $pluginType,
                    'tag_name'       => $tagName,
                    'latest_release' => $latestRelease,
                    'country_code'   => $countryCode,
                    'country'        => $country,
                    'shop_name'      => $shopName,
                    'shop_url'       => $shopUrl,
                ]);
        
                $ch = curl_init($apiUrl);
        
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiToken,
                ]);
        
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
                if (curl_errno($ch)) {
                    $errorMessage = curl_error($ch);
                    $this->log('cURL Error: ' . $errorMessage);
                } else {
                    if ($httpCode !== 200) {
                        $this->log('API Request failed with HTTP code: ' . $httpCode);
                    }
                }
        
                curl_close($ch);
        
                if ($response !== false && $httpCode === 200) {
                    // Save the new version in the settings
                    $storedSettings['payment_mastercard_version'] = $tagName;
                    $this->model_setting_setting->editSetting('payment_mastercard', $storedSettings);
                } else {
                    $this->log('Active status API error');
                }
            }

            $this->session->data['success'] = $this->language->get('text_success');
        
            $this->response->redirect(
                $this->url->link(
                    'marketplace/extension',
                    'user_token=' . $this->session->data['user_token'] . '&type=payment',
                    true
                )
            );
        }

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
        if (isset($this->error['validation_errors'])) {
            $data['error_warning'] = $this->error['error_warning'];
        } else {
            $data['error_warning'] = '';
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
            'href' => $this->url->link('extension/mastercard/payment/mastercard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['action'] = $this->url->link('extension/mastercard/payment/mastercard',
        'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['module_version'] = self::MODULE_VERSION;
        $data['api_version'] = self::API_VERSION;

        if (isset($this->request->post['payment_mastercard_status'])) {
            $data['payment_mastercard_status'] = $this->request->post['payment_mastercard_status'];
        } else {
            $data['payment_mastercard_status'] = $this->config->get('payment_mastercard_status');
        }

        if (isset($this->request->post['payment_mastercard_initial_transaction'])) {
            $data['payment_mastercard_initial_transaction'] = $this->request->post['payment_mastercard_initial_transaction'];
        } else {
            $data['payment_mastercard_initial_transaction'] = $this->config->get('payment_mastercard_initial_transaction') ? : 'authorize';
        }

        if (isset($this->request->post['payment_mastercard_title'])) {
            $data['payment_mastercard_title'] = $this->request->post['payment_mastercard_title'];
        } else {
            $data['payment_mastercard_title'] = $this->config->get('payment_mastercard_title') ? : 'Pay Using Mastercard Payment Gateway Services';
        }

        if (isset($this->request->post['payment_mastercard_live_merchant_id'])) {
            $data['payment_mastercard_live_merchant_id'] = $this->request->post['payment_mastercard_live_merchant_id'];
        } else {
            $data['payment_mastercard_live_merchant_id'] = $this->config->get('payment_mastercard_live_merchant_id');
        }

        if (isset($this->request->post['payment_mastercard_live_api_password'])) {
            $data['payment_mastercard_live_api_password'] = $this->request->post['payment_mastercard_live_api_password'];
        } else {
            $data['payment_mastercard_live_api_password'] = $this->config->get('payment_mastercard_live_api_password');
        }

        if (isset($this->request->post['payment_mastercard_test_merchant_id'])) {
            $data['payment_mastercard_test_merchant_id'] = $this->request->post['payment_mastercard_test_merchant_id'];
        } else {
            $data['payment_mastercard_test_merchant_id'] = $this->config->get('payment_mastercard_test_merchant_id');
        }

        if (isset($this->request->post['payment_mastercard_test_api_password'])) {
            $data['payment_mastercard_test_api_password'] = $this->request->post['payment_mastercard_test_api_password'];
        } else {
            $data['payment_mastercard_test_api_password'] = $this->config->get('payment_mastercard_test_api_password');
        }

        if (isset($this->request->post['payment_mastercard_live_notification_secret'])) {
            $data['payment_mastercard_live_notification_secret'] = $this->request->post['payment_mastercard_live_notification_secret'];
        } else {
            $data['payment_mastercard_live_notification_secret'] = $this->config->get('payment_mastercard_live_notification_secret');
        }

        if (isset($this->request->post['payment_mastercard_test_notification_secret'])) {
            $data['payment_mastercard_test_notification_secret'] = $this->request->post['payment_mastercard_test_notification_secret'];
        } else {
            $data['payment_mastercard_test_notification_secret'] = $this->config->get('payment_mastercard_test_notification_secret');
        }

        if (isset($this->request->post['payment_mastercard_api_gateway'])) {
            $data['payment_mastercard_api_gateway'] = $this->request->post['payment_mastercard_api_gateway'];
        } else {
            $data['payment_mastercard_api_gateway'] = $this->config->get('payment_mastercard_api_gateway') ? : 'api_eu';
        }

        if (isset($this->request->post['payment_mastercard_api_gateway_other'])) {
            $data['payment_mastercard_api_gateway_other'] = $this->request->post['payment_mastercard_api_gateway_other'];
        } else {
            $data['payment_mastercard_api_gateway_other'] = $this->config->get('payment_mastercard_api_gateway_other');
        }

        if (isset($this->request->post['payment_mastercard_test'])) {
            $data['payment_mastercard_test'] = $this->request->post['payment_mastercard_test'];
        } else {
            $data['payment_mastercard_test'] = $this->config->get('payment_mastercard_test');
        }

        if (isset($this->request->post['payment_mastercard_integration_model'])) {
            $data['payment_mastercard_integration_model'] = $this->request->post['payment_mastercard_integration_model'];
        } else {
            $data['payment_mastercard_integration_model'] = $this->config->get('payment_mastercard_integration_model') ? : 'hostedcheckout';
        }

        if (isset($this->request->post['payment_mastercard_hc_type'])) {
            $data['payment_mastercard_hc_type'] = $this->request->post['payment_mastercard_hc_type'];
        } else {
            $data['payment_mastercard_hc_type'] = $this->config->get('payment_mastercard_hc_type') ? : 'redirect';
        }

        

        if (isset($this->request->post['payment_mastercard_send_line_items'])) {
            $data['payment_ mastercard_send_line_items'] = $this->request->post['payment_mastercard_send_line_items'];
        } else {
            $data['payment_mastercard_send_line_items'] = $this->config->get('payment_mastercard_send_line_items');
        }

        if (isset($this->request->post['payment_mastercard_sort_order'])) {
            $data['payment_mastercard_sort_order'] = $this->request->post['payment_mastercard_sort_order'];
        } else {
            $data['payment_mastercard_sort_order'] = $this->config->get('payment_mastercard_sort_order');
        }

        if (isset($this->request->post['payment_mastercard_debug'])) {
            $data['payment_mastercard_debug'] = $this->request->post['payment_mastercard_debug'];
        } else {
            $data['payment_mastercard_debug'] = $this->config->get('payment_mastercard_debug');
        }

        if (isset($this->request->post['payment_mastercard_order_id_prefix'])) {
            $data['payment_mastercard_order_id_prefix'] = $this->request->post['payment_mastercard_order_id_prefix'];
        } else {
            $data['payment_mastercard_order_id_prefix'] = $this->config->get('payment_mastercard_order_id_prefix');
        }

        if (isset($this->request->post['payment_mastercard_approved_status_id'])) {
            $data['payment_mastercard_approved_status_id'] = $this->request->post['payment_mastercard_approved_status_id'];
        } else {
            $data['payment_mastercard_approved_status_id'] = $this->config->get('payment_mastercard_approved_status_id') ? : '2';
        }

        if (isset($this->request->post['payment_mastercard_declined_status_id'])) {
            $data['payment_mastercard_declined_status_id'] = $this->request->post['payment_mastercard_declined_status_id'];
        } else {
            $data['payment_mastercard_declined_status_id'] = $this->config->get('payment_mastercard_declined_status_id') ? : '8';
        }

        if (isset($this->request->post['payment_mastercard_pending_status_id'])) {
            $data['payment_mastercard_pending_status_id'] = $this->request->post['payment_mastercard_pending_status_id'];
        } else {
            $data['payment_mastercard_pending_status_id'] = $this->config->get('payment_mastercard_pending_status_id') ? : '1';
        }

        if (isset($this->request->post['payment_mastercard_risk_review_status_id'])) {
            $data['payment_mastercard_risk_review_status_id'] = $this->request->post['payment_mastercard_risk_review_status_id'];
        } else {
            $data['payment_mastercard_risk_review_status_id'] = $this->config->get('payment_mastercard_risk_review_status_id') ? : '1';
        }

        if (isset($this->request->post['payment_mastercard_risk_declined_status_id'])) {
            $data['payment_mastercard_risk_declined_status_id'] = $this->request->post['payment_mastercard_risk_declined_status_id'];
        } else {
            $data['payment_mastercard_risk_declined_status_id'] = $this->config->get('payment_mastercard_risk_declined_status_id') ? : '8';
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/mastercard/payment/mastercard', $data));
    }
    
    protected function validate(){
        if (!$this->user->hasPermission('modify', 'extension/mastercard/payment/mastercard')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ($this->request->post['payment_mastercard_test']) {
            if (!$this->request->post['payment_mastercard_test_merchant_id']) {
                $this->error['test_merchant_id'] = $this->language->get('error_test_merchant_id');
                $this->error['credentials_validation'] = $this->language->get('error_warning');
            } elseif (!empty($this->request->post['payment_mastercard_test_merchant_id'])) {
                $testMerchantId = $this->request->post['payment_mastercard_test_merchant_id'];
                if (stripos($testMerchantId, 'TEST') === false) {
                    $this->error['test_merchant_id'] = $this->language->get('error_test_merchant_id_prefix');
                    $this->error['credentials_validation'] = $this->language->get('error_warning');
                }
            }
            if (!$this->request->post['payment_mastercard_test_api_password']) {
                $this->error['test_api_password'] = $this->language->get('error_test_api_password');
                $this->error['credentials_validation'] = $this->language->get('error_warning');
            }
        } else {
            if (!$this->request->post['payment_mastercard_live_merchant_id']) {
                $this->error['live_merchant_id'] = $this->language->get('error_live_merchant_id');
                $this->error['credentials_validation'] = $this->language->get('error_warning');
            } elseif (!empty($this->request->post['payment_mastercard_live_merchant_id'])) {
                $liveMerchantId = $this->request->post['payment_mastercard_live_merchant_id'];
                if (stripos($liveMerchantId, 'TEST') !== false) {
                    $this->error['live_merchant_id'] = $this->language->get('error_live_merchant_id_prefix');
                    $this->error['credentials_validation'] = $this->language->get('error_warning');
                }
            }
            if (!$this->request->post['payment_mastercard_live_api_password']) {
                $this->error['live_api_password'] = $this->language->get('error_live_api_password');
                $this->error['credentials_validation'] = $this->language->get('error_warning');
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
        $this->load->model('extension/mastercard/payment/mastercard');
        $this->load->model('localisation/currency');
        $this->document->addScript('../extension/mastercard/admin/view/javascript/custom.js');
        $this->document->addStyle('../extension/mastercard/admin/view/stylesheet/mastercard.css');
        $this->session->data['admin_order_id']  =  $this->request->get['order_id'];
        $order = $this->model_extension_mastercard_payment_mastercard->getOrder(
            $this->request->get['order_id'] 
        );
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
            $this->load->language('extension/mastercard/payment/mastercard');
            $data['mgps_hosted_checkout_order'] = array(
                'transactions' => $this->model_extension_mastercard_payment_mastercard->getTransactions(
                    $this->request->get['order_id']
                )
            );
            $data['order_id'] = $this->request->get['order_id'];
            $data['user_token'] = $this->request->get['user_token'];
            return $this->load->view('extension/mastercard/payment/mastercard_order',$data);
        }  
    }
    
    public function install(){
        $this->load->model('extension/mastercard/payment/mastercard');
        $this->model_extension_mastercard_payment_mastercard->install();
        $this->model_extension_mastercard_payment_mastercard->deleteEvents();
        $this->model_extension_mastercard_payment_mastercard->addEvents();
    }

    public function uninstall() {
        $this->load->model('extension/mastercard/payment/mastercard');
        $this->load->model('setting/event');
        $this->model_extension_mastercard_payment_mastercard->uninstall();
        $this->model_extension_mastercard_payment_mastercard->deleteEvents();
    }
    

    public function paymentOptionsInquiry() {
        $uri = $this->getApiUri() . '/paymentOptionsInquiry';
        $response = $this->apiRequest('POST', $uri);
        return $response;
    }
    
    public function getGatewayUri($apiGateway) {
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
            $url = $this->config->get('payment_mastercard_api_gateway_other');
            if (!empty($url)) {
                if (substr($url, -1) !== '/') {
                    $url = $url . '/';
                }
            }
            $gatewayUrl = $url;
        }
    
        return $gatewayUrl;
    }
    

    public function getApiUri() {
        $apiGateway = $this->request->post['payment_mastercard_api_gateway'];
        return $this->getGatewayUri($apiGateway) . 'api/rest/version/' . self::API_VERSION . '/merchant/' . $this->getMerchantId();
    }
    

    public function getCaptureUri(){
      
        $apiGateway = $this->config->get('payment_mastercard_api_gateway');
        return $this->getGatewayUri($apiGateway);
    }

    public function getMerchantId(){
        if ($this->request->post['payment_mastercard_test']) {
            return $this->request->post['payment_mastercard_test_merchant_id'];
        } else {
            return $this->request->post['payment_mastercard_live_merchant_id'];
        }
    }

    public function getApiPassword(){
        if ($this->request->post['payment_mastercard_test']) {
            return $this->request->post['payment_mastercard_test_api_password'];
        } else {
            return $this->request->post['payment_mastercard_live_api_password'];
        }
    }

    public function isTestModeEnabled(){
        return $this->config->get('payment_mastercard_test');
    }

    public function isDebugModeEnabled(){
        return $this->config->get('payment_mastercard_debug');
        
    }

    public function extractOrderNumberFromString($completeOrderNumber) {
        $order_prefix = $this->config->get('payment_mastercard_order_id_prefix');
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
        $test_mode = $this->config->get('payment_mastercard_test');
        $api_password = $test_mode ? $this->config->get('payment_mastercard_test_api_password') : $this->config->get('payment_mastercard_live_api_password');
        $merchant_id = $test_mode ? $this->config->get('payment_mastercard_test_merchant_id') : $this->config->get('payment_mastercard_live_merchant_id');
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

        /**
     * @param $message
     */
    public function log($message){
        if ($this->isDebugModeEnabled()) {
            $this->debugLog = new \Opencart\System\Library\Log(self::DEBUG_LOG_FILENAME);
            $this->debugLog->write($message);
        }
    }

    public function capture() {
        $this->load->model('extension/mastercard/payment/mastercard');
        try {
            $this->load->language('extension/mastercard/payment/mastercard');
            $this->load->model('sale/order');
            $comment = $this->language->get('text_capture_sucess');
            $merchant_id = $this->model_extension_mastercard_payment_mastercard->getMerchantId();
            $capture_order_id = $this->request->post['order_id'];
            $transactionHistory = $this->model_extension_mastercard_payment_mastercard->getTransactions($this->session->data['admin_order_id'] );
            foreach ($transactionHistory as $transaction) {
                if ($transaction['type'] === 'AUTHORIZED' && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $capture_amount = $transaction['amount'];
                    $capture_transaction_id = $transaction['transaction_id'];
                    
                }
            }
            $completed_status_id = $this->model_extension_mastercard_payment_mastercard->getOrderStatusIdByName("Complete");
            $new_order_id = $this->extractOrderNumberFromString($capture_order_id);
            $newTxnId = $this->getUniqueTransactionId($capture_order_id);
            $url =  $this->getCaptureUri() . 'api/rest/version/' . self::API_VERSION . '/merchant/' . $merchant_id . '/order/' . $capture_order_id . '/transaction/' . $newTxnId ;
            $this->load->model('localisation/currency');
            $defaultCurrencyCode = $this->config->get('config_currency');
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
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $status = $response['order']['status'];
                $mail_type = "Capture";
                $oc_orderId = "";
                $customer_email       = $response['customer']['email'];
                $customer_name = isset($response['customer']['firstName'], $response['customer']['lastName']) ? $response['customer']['firstName'] . ' ' . $response['customer']['lastName']: 'N/A';      
                $this->db->query("UPDATE " . DB_PREFIX . "mgps_order_transaction SET status ='".$status."' , type = 'Captured' WHERE transaction_id = '".$capture_transaction_id."' AND oc_order_id = '".$capture_order_id."' LIMIT 1");
                $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = '" . (int)$completed_status_id . "' WHERE order_id = '" . (int)$new_order_id . "'");
                $this->model_extension_mastercard_payment_mastercard->addOrderHistory($new_order_id, $completed_status_id, $comment, $notify);
                if ($this->config->get('config_mail_engine')) {
                    $this->log("inside");
                    $this->sendCustomEmail($customer_email, $oc_orderId, $status, $customer_name , $new_order_id,$mail_type );
                }else{
                    $this->log("Error Send");
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
        $this->load->model('extension/mastercard/payment/mastercard');
        $this->load->model('localisation/currency');
        $processed_order_id =  $new_order_id ;
        $oc_orderId =  $this->session->data['admin_order_id'] ;
        $order = $this->model_extension_mastercard_payment_mastercard->getOrder(
            $new_order_id 
        );
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
           $this->load->language('extension/mastercard/payment/mastercard');
            $data['mgps_hosted_checkout_order'] = array(
                'transactions' => $this->model_extension_mastercard_payment_mastercard->getTransactions(
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
                $mail_option = [
                    'parameter'     => $this->config->get('config_mail_parameter'),
                    'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
                    'smtp_username' => $this->config->get('config_mail_smtp_username'),
                    'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
                    'smtp_port'     => $this->config->get('config_mail_smtp_port'),
                    'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
                ];
            
                $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
                $mail->setTo($reciever_address);
                $mail->setFrom($this->config->get('config_email'));
                $mail->setSender($this->config->get('config_name'));
            
                // Apply string transformation before interpolation
                $formattedSubject = ucwords(strtolower(str_replace('_', ' ', $subject)));
                $mail->setSubject(html_entity_decode("Payment {$formattedSubject}", ENT_QUOTES, 'UTF-8'));
            
                $mail->setHtml($this->load->view('extension/mastercard/payment/mgps_hosted_checkout_mail', $data));
                
                if ($mail->send()) {
                    $this->log("Email Successfully Sent");
                    return;
                } else {
                    $this->log('Email failed to send: ' . $mail->ErrorInfo);
                    return;
                }
            } 
        }
    }

    private function getUniqueTransactionId($orderReference){
     $uniqId = substr(uniqid(), 7, 6);
        return sprintf('%s-%s', $orderReference, $uniqId);
    }

    public function RequestRefund(){
       
        try {
           $this->load->language('extension/mastercard/payment/mastercard');
            $this->load->model('extension/mastercard/payment/mastercard');
            $this->load->model('sale/order');
            $capture_order_id = $this->request->post['order_id'];
            $newTxnId = $this->getUniqueTransactionId($capture_order_id);
            $new_order_id = $this->extractOrderNumberFromString($capture_order_id);
            $transactionHistory = $this->model_extension_mastercard_payment_mastercard->getTransactions($this->session->data['admin_order_id'] );
            foreach ($transactionHistory as $transaction) {
               
                if ($transaction['type'] === 'Captured' || 'CAPTURED' && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $capture_amount = $transaction['amount'];
                    $capture_transaction_id = $transaction['transaction_id']; 
                }
            }
            $comment = $this->language->get('text_refund_sucess');
            $merchant_id =$this->model_extension_mastercard_payment_mastercard->getMerchantId();
            $refund_status_id = $this->model_extension_mastercard_payment_mastercard->getOrderStatusIdByName("Refunded");
            $this->load->model('localisation/currency');
            $defaultCurrencyCode = $this->config->get('config_currency');
            $notify = "0";
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
                $customer_first_name = isset($response['customer']['firstName']) ? $response['customer']['firstName'] : '';
                $customer_last_name = isset($response['customer']['lastName']) ? $response['customer']['lastName'] : '';
                $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
                $refundedAmount = $response['order']['totalRefundedAmount'];
                $this->db->query("UPDATE " . DB_PREFIX . "mgps_order_transaction SET status = '".$transactionStatus."' , refunded_amount = '".$refundedAmount."' , type = 'Captured' WHERE transaction_id = '".$capture_transaction_id."' AND oc_order_id = '".$capture_order_id."' LIMIT 1");
                $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = '" . (int)$refund_status_id . "' WHERE order_id = '" . (int)$new_order_id . "'");
                $this->model_extension_mastercard_payment_mastercard->addOrderHistory($new_order_id, $refund_status_id, $comment, $notify);
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
             $this->load->language('extension/mastercard/payment/mastercard');
            $this->load->model('extension/mastercard/payment/mastercard');
            $this->load->model('sale/order');
            $this->load->model('localisation/currency');
            $capture_order_id = $this->request->post['order_id'];
            $newTxnId = $this->getUniqueTransactionId($capture_order_id);
            $new_order_id = $this->extractOrderNumberFromString($capture_order_id);
            $merchant_id = $this->model_extension_mastercard_payment_mastercard->getMerchantId();
            $transactionHistory = $this->model_extension_mastercard_payment_mastercard->getTransactions($this->session->data['admin_order_id'] );
            foreach ($transactionHistory as $transaction) {
                if ($transaction['type'] === 'Captured' || 'CAPTURED' && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $capture_transaction_id = $transaction['transaction_id']; 
                }
            }
            $capture_amount  = $this->request->post['amount'];
            $refund_status_id = $this->model_extension_mastercard_payment_mastercard->getOrderStatusIdByName("Refunded");
            $defaultCurrencyCode = $this->config->get('config_currency');
            $currencyInfo = $this->model_localisation_currency->getCurrencyByCode($defaultCurrencyCode);
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
                $customer_first_name = isset($response['customer']['firstName']) ? $response['customer']['firstName'] : '';
                $customer_last_name = isset($response['customer']['lastName']) ? $response['customer']['lastName'] : '';
                $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
                $this->db->query("UPDATE " . DB_PREFIX . "mgps_order_transaction SET status = '".$transactionStatus."' ,refunded_amount = '".$refundedAmount."', type = 'Captured' WHERE transaction_id = '".$capture_transaction_id."' AND oc_order_id = '".$capture_order_id."' LIMIT 1");
                $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = '" . (int)$refund_status_id . "' WHERE order_id = '" . (int)$new_order_id . "'");
                $this->model_extension_mastercard_payment_mastercard->addOrderHistory($new_order_id, $refund_status_id, $comment, $notify);
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

    public function save(): void {
        $this->load->language('extension/mastercard/payment/mastercard');
        $json = [];
        // checking file modification permission
        if (!$this->user->hasPermission('modify', 'extension/mastercard/payment/mastercard')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_mastercard', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function getLatestGitHubVersion() {
        $owner = 'fingent-corp';
        $repo = 'gateway-opencart-mastercard-module';
        $url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mastercard');
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return null; 
        }
        curl_close($ch);
        $data = json_decode($response, true);
    
        if (isset($data['tag_name'])) {
            return $data['tag_name'];
        } else {
            return null; 
        }
    }
    
    private function compareVersions($latestVersion, $currentVersion) {
        $releaseNotesLink = "https://mpgs.fingent.wiki/target/opencart-mastercard-payment-gateway-services/release-notes/";
        
        if ($latestVersion !== null && version_compare($latestVersion, $currentVersion, '>')) {
            $message = "A new version ({$latestVersion}) of the module is now available! Please refer to the <a href='{$releaseNotesLink}' target='_blank'>Release Notes</a> section for information about its compatibility and features.";
            return $message;
        }
    
        return null;
    }
    

    
}
