<?php
/**
 * Copyright (c) 2026 Mastercard
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

use Mpgs\Payment\MpgsPaymentException;

class ControllerExtensionPaymentMpgsHostedCheckout extends Controller {

    const API_VERSION           = '100';
    const MODULE_VERSION        = '1.2.2';
    const API_AMERICA           = 'api_na';
    const API_EUROPE            = 'api_eu';
    const API_ASIA              = 'api_ap';
    const API_MTF               = 'api_mtf';
    const API_OTHER             = 'api_other';
    const DEBUG_LOG_FILENAME    = 'mpgs_gateway.log';
    const REPO_NAME             = 'gateway-opencart-mastercard-module';
    const BASE_URL              = 'https://mpgs.fingent.wiki/enterprise/opencart-mastercard';
    const PATH_RELEASE_NOTES    = '-gateway/release-notes/';
    const WIKI_URL              = self::BASE_URL . self::PATH_RELEASE_NOTES;
    const WIKI_API_URL          = 'https://mpgs.fingent.wiki/wp-json/mpgs/v2/update-repo-status';
    const SETTING_CODE_SHOP_TRACKER     = 'mpgs_shop_tracker';
    const TELEMETRY_EVENT_INSTALL       = 'install';
    const TELEMETRY_EVENT_CONFIGURED    = 'configured';
    const ROUTE_MPGS            = 'extension/payment/mpgs_hosted_checkout';
    const ROUTE_EXT             = 'marketplace/extension';
    const URI_TOKEN             = 'user_token=';
    const URI_PAY               = '&type=payment';
    const ROUTE_COMMON_DASHBOARD    = 'common/dashboard';
    private $error                  = [];
    const MODEL_LOCALISATION_CURRENCY = 'localisation/currency';
    const ENDPOINT_MERCHANT     = '/merchant/';
    const API_PATH_VERSION      = 'api/rest/version/';
    const MODEL_SALE_ORDER      = 'sale/order';
    const ENDPOINT_ORDER        = '/order/';
    const ENDPOINT_TRANSACTION  = '/transaction/';
    const SQL_UPDATE            = "UPDATE ";
    const SQL_LIMIT_1           = "' LIMIT 1";
    const SQL_WHERE_ORDER       = "' AND oc_order_id = '";
    const SQL_WHERE_ID          = "' WHERE order_id = '";
    const SQL_SET_ORDER_STATUS  = "order SET order_status_id = '";
    const TEXT_TRANSACTION_REFUND_FAILED = 'Transaction refund failed';

    /**
     * Registers a class-map autoloader for the Mpgs\Payment namespace.
     * OpenCart 3 has no PSR-4 autoloader for extension files; this
     * constructor-scoped registration ensures MpgsPaymentException is
     * resolvable from catch blocks without a top-level require_once.
     */
    public function __construct($registry) {
        parent::__construct($registry);
        spl_autoload_register(static function (string $class): void {
            $map = [
                'Mpgs\\Payment\\MpgsPaymentException' => __DIR__ . '/mpgs_payment_exception.php',
            ];
            if (isset($map[$class])) {
                require_once $map[$class];
            }
        });
    }

    /**
     * Display the payment module configuration page and handle form submission
     * @return void
     */
    public function index() {

        $this->load->language(self::ROUTE_MPGS);
        $this->load->model(self::ROUTE_MPGS );
        $this->load->model('setting/setting');

        $this->model_extension_payment_mpgs_hosted_checkout->ensureDefaultOrderStatusSettings();

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('view/javascript/mpgs-hosted-checkout/custom.js');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->isvalidate()) {
            $this->model_setting_setting->editSetting('payment_mpgs_hosted_checkout', $this->request->post);
            $this->maybeSendConfiguredTelemetry();
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect(
                $this->url->link(
                    self::ROUTE_EXT,
                    self::URI_TOKEN . $this->session->data['user_token'] . self::URI_PAY,
                    true
                )
            );
        }

        $data = $this->initializeData();

        $this->setupErrors($data);
        $this->setupBreadcrumbs($data);
        $this->setupConfigurationValues($data);
        $this->setupImages($data);

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/mpgs_hosted_checkout_bknd_template', $data));
    }

    /**
     * Maps configuration keys from POST or Config settings.
     * Reduces complexity by using a loop over keys.
     */
    private function setupConfigurationValues(&$data) {

        $keys = [
            'payment_mpgs_hosted_checkout_status',
            'payment_mpgs_hosted_checkout_live_merchant_id',
            'payment_mpgs_hosted_checkout_live_api_password',
            'payment_mpgs_hosted_checkout_test_merchant_id',
            'payment_mpgs_hosted_checkout_test_api_password',
            'payment_mpgs_hosted_checkout_live_notification_secret',
            'payment_mpgs_hosted_checkout_test_notification_secret',
            'payment_mpgs_hosted_checkout_api_gateway_other',
            'payment_mpgs_hosted_checkout_test',
            'payment_mpgs_hosted_checkout_send_line_items',
            'payment_mpgs_hosted_checkout_sort_order',
            'payment_mpgs_hosted_checkout_debug',
            'payment_mpgs_hosted_checkout_order_id_prefix',
            'payment_mpgs_hosted_checkout_title',
            'payment_mpgs_hosted_checkout_hc_type',
            'payment_mpgs_hosted_checkout_approved_status_id',
            'payment_mpgs_hosted_checkout_declined_status_id',
            'payment_mpgs_hosted_checkout_pending_status_id',
            'payment_mpgs_hosted_checkout_refund_status_id',
            'payment_mpgs_hosted_checkout_risk_review_status_id',
            'payment_mpgs_hosted_checkout_risk_declined_status_id',
            'payment_mpgs_hosted_checkout_merchant_info',
            'payment_mpgs_hosted_checkout_merchant_name',
            'payment_mpgs_hosted_checkout_merchant_adrs1',
            'payment_mpgs_hosted_checkout_merchant_adrs2',
            'payment_mpgs_hosted_checkout_merchant_postcode',
            'payment_mpgs_hosted_checkout_merchant_country',
            'payment_mpgs_hosted_checkout_merchant_email',
            'payment_mpgs_hosted_checkout_merchant_phone',
            'payment_mpgs_hosted_checkout_image'
        ];

        foreach ($keys as $key) {
            $data[$key] = $this->getSettingValue($key);
        }

        $data['payment_mpgs_hosted_checkout_initial_transaction']   =
            $this->getSettingValue('payment_mpgs_hosted_checkout_initial_transaction', 'authorize');
        $data['payment_mpgs_hosted_checkout_send_line_items']       =
            $this->getSettingValue('payment_mpgs_hosted_checkout_send_line_items', '1');
        $data['payment_mpgs_hosted_checkout_title']                 =
            $this->getSettingValue('payment_mpgs_hosted_checkout_title', 'Mastercard Hosted Checkout');
        $data['payment_mpgs_hosted_checkout_hc_type']               =
            $this->getSettingValue('payment_mpgs_hosted_checkout_hc_type', 'redirect');
        $data['payment_mpgs_hosted_checkout_integration_model']     =
            $this->getSettingValue('payment_mpgs_hosted_checkout_integration_model', 'hostedcheckout');
        $data['is_test_mode']                                       =
            (bool)$data['payment_mpgs_hosted_checkout_test'];
    }

    /**
     * Helper to check post data then config data
     */
    private function getSettingValue($key, $default = null) {

        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }
        return $this->config->get($key) ?? $default;
    }

    /**
     * @param array $data
     */
    private function setupErrors(&$data) {

        $errorKeys = [
            'live_merchant_id', 'live_api_password', 'test_merchant_id',
            'merchant_name', 'test_api_password', 'credentials_validation',
            'api_gateway_other', 'merchant_email', 'merchant_phone', 'title'
        ];

        foreach ($errorKeys as $key) {
            $data['error_' . $key] = $this->error[$key] ?? '';
        }
    }

    /**
     * @param array $data
     */
    private function setupBreadcrumbs(&$data) {

        $token                  = $this->session->data['user_token'];
        $data['breadcrumbs']    = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link(self::ROUTE_COMMON_DASHBOARD, self::URI_TOKEN . $token, true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link(self::ROUTE_EXT, self::URI_TOKEN . $token . self::URI_PAY, true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(self::ROUTE_MPGS, self::URI_TOKEN . $token, true)
        ];

        $data['action']         = $this->url->link(self::ROUTE_MPGS, self::URI_TOKEN . $token, true);
        $data['cancel']         = $this->url->link(self::ROUTE_EXT, self::URI_TOKEN . $token . self::URI_PAY, true);
    }

    /**
     * @param array $data
     */
    private function setupImages(&$data) {

        $this->load->model('tool/image');
        $data['image'] = $this->getSettingValue('payment_mpgs_hosted_checkout_image');

        if ($data['image'] && is_file(DIR_IMAGE . $data['image'])) {
            $data['thumb'] = $this->model_tool_image->resize($data['image'], 140, 140);
        } else {
            $data['thumb'] = 'view/image/payment/mclogo.svg';
        }
        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 140, 140);
    }


    /**
     * Validate the configuration form inputs
     * @return bool
     */
    protected function isvalidate() {

        if (!$this->user->hasPermission('modify', self::ROUTE_MPGS)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $this->validateCredentials();
        $this->validatePaymentTitle();
        $this->validateContactInfo();
        $this->validateGatewaySettings();

        if (!$this->error) {
            $this->validateApiConnection();
        }

        return !$this->error;
    }

    /**
     * Validates Merchant ID and API Password based on Test/Live mode
     */
    private function validateCredentials() {

        $post       = $this->request->post;
        $isTestMode = !empty($post['payment_mpgs_hosted_checkout_test']);

        if ($isTestMode) {
            $testMerchantId = trim($post['payment_mpgs_hosted_checkout_test_merchant_id'] ?? '');
            $testApiPassword = trim($post['payment_mpgs_hosted_checkout_test_api_password'] ?? '');

            if ($testMerchantId === '') {
                $this->error['test_merchant_id'] = $this->language->get('error_test_merchant_id');
            }
            if ($testApiPassword === '') {
                $this->error['test_api_password'] = $this->language->get('error_test_api_password');
            }
        } else {
            $merchantId = trim($post['payment_mpgs_hosted_checkout_live_merchant_id'] ?? '');

            if ($merchantId === '') {
                $this->error['live_merchant_id'] = $this->language->get('error_live_merchant_id');
                return;
            }

            if (stripos($merchantId, 'TEST') !== false) {
                $this->error['live_merchant_id'] = $this->language->get('error_live_merchant_id_prefix');
                return;
            }

            $apiPassword = trim($post['payment_mpgs_hosted_checkout_live_api_password'] ?? '');

            if ($apiPassword === '') {
                $this->error['live_api_password'] = $this->language->get('error_live_api_password');
            }
        }
    }

    /**
     * Validates Payment Title (mandatory)
     */
    private function validatePaymentTitle() {

        $post   = $this->request->post;
        $title  = trim($post['payment_mpgs_hosted_checkout_title'] ?? '');

        if (empty($title)) {
            $this->error['title'] = $this->language->get('error_title');
        }
    }

    /**
     * Validates Email, Phone, and Merchant Name requirements
     */
    private function validateContactInfo() {

        $post = $this->request->post;

        $isRedirect = ($post['payment_mpgs_hosted_checkout_hc_type'] == 'redirect');
        $hasInfoEnabled = ($post['payment_mpgs_hosted_checkout_merchant_info'] == '1');

        if (empty($post['payment_mpgs_hosted_checkout_merchant_name']) && $isRedirect && $hasInfoEnabled) {
            $this->error['merchant_name'] = $this->language->get('error_merchant_name');
        }

        $email = trim($post['payment_mpgs_hosted_checkout_merchant_email'] ?? '');
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error['merchant_email'] = $this->language->get('error_merchant_email');
        }

        $phone = trim($post['payment_mpgs_hosted_checkout_merchant_phone'] ?? '');
        if ($phone !== '') {

            if (strlen($phone) > 20) {
                $this->error['merchant_phone'] = $this->language->get('error_merchant_phone');
                return;
            }

            if (!preg_match('/^[+]?[0-9\s\-\(\)]{6,20}$/', $phone)) {
                $this->error['merchant_phone'] = $this->language->get('error_merchant_phone');
            }
        }
    }

    /**
     * Validates Gateway URL settings (mandatory field)
     */
    private function validateGatewaySettings() {

        $apiGatewayUrl = trim($this->request->post['payment_mpgs_hosted_checkout_api_gateway_other'] ?? '');

        if (empty($apiGatewayUrl)) {
            $this->error['api_gateway_other'] = $this->language->get('error_api_gateway_other');
            return;
        }

        if (!filter_var($apiGatewayUrl, FILTER_VALIDATE_URL)) {
            $this->error['api_gateway_other'] = $this->language->get('error_api_gateway_url_invalid');
        }
    }

    /**
     * Performs external API check
     */
    private function validateApiConnection() {

        $response = $this->paymentOptionsInquiry();

        if (isset($response['result']) && $response['result'] === 'ERROR') {
            $explanation = $response['error']['explanation'] ?? '';

            if ($explanation === 'Invalid credentials.') {
                $this->error['credentials_validation'] = $this->language->get('error_credentials_validation');
            } else {
                $this->error['credentials_validation'] = sprintf('%s: %s', $response['error']['cause'], $explanation);
            }
        }
    }

    /**
     * Load necessary styles for the order page
     * @return array
     */
    public function order() {

        $this->document->addStyle('view/stylesheet/mgps/mastercard.css');
    }

    /**
     * @param $route
     * @param $data
     * @param $output
     * @return void
     */
    public function addCustomTab(&$route, &$data, &$output) {

        $orderId = $this->request->get['order_id'] ?? 0;
        $this->load->model(self::ROUTE_MPGS);
        $this->load->model(self::MODEL_SALE_ORDER);

        $orderInfo = $this->model_sale_order->getOrder($orderId);

        if (!$orderInfo || $orderInfo['payment_code'] !== 'mpgs_hosted_checkout') {
            return;
        }

        $this->load->language(self::ROUTE_MPGS);
        $this->session->data['admin_order_id'] = $orderId;

        $this->setupCurrencyData($data);
        $this->setupOrderTransactions($data, $orderId);

        $data['order_id']   = $orderId;
        $data['user_token'] = $this->request->get['user_token'];

        $template = 'extension/payment/mpgs_hosted_checkout_order_custom_tab_template';

        $data['tabs'][] = [
            'code'    => 'mpgs_custom_tab',
            'title'   => $this->language->get('heading_title'),
            'content' => $this->load->view($template, $data)
        ];
    }

    /**
     * Handles currency symbol logic
     */
    private function setupCurrencyData(&$data) {

        $this->load->model(self::MODEL_LOCALISATION_CURRENCY);
        $currencyInfo = $this->model_localisation_currency->getCurrencyByCode($this->config->get('config_currency'));

        if ($currencyInfo) {
            $data['currency'] = !empty($currencyInfo['symbol_left'])
                ? $currencyInfo['symbol_left']
                : $currencyInfo['symbol_right'];
        }
    }

    /**
     * Handles fetching transaction history
     */
    private function setupOrderTransactions(&$data, $orderId) {

        $transactions = $this->model_extension_payment_mpgs_hosted_checkout->getTransactions($orderId);

        $data['mgps_hosted_checkout_order'] = [
            'transactions' => $transactions
        ];
    }

    /**
     * @return void
     */
    public function install() {

        $this->load->model(self::ROUTE_MPGS);
        $this->load->model('setting/setting');
        $this->model_extension_payment_mpgs_hosted_checkout->createTable();
        $this->model_extension_payment_mpgs_hosted_checkout->ensureDefaultOrderStatusSettings();
        $this->hook_events();

        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "layout_module` WHERE `code` LIKE 'mpgs_hosted_checkout.%' OR `code` = 'mpgs_hosted_checkout'"
        );

        $this->cache->delete('layout');

        $this->maybeSendInstallTelemetry();
    }

    /**
     * @return void
     */
    public function uninstall() {

        $this->load->model(self::ROUTE_MPGS);
        $this->model_extension_payment_mpgs_hosted_checkout->removeEvents();
    }

    /**
     * @return void
     */
    public function hook_events() {

        $this->load->model(self::ROUTE_MPGS);
        $this->model_extension_payment_mpgs_hosted_checkout->removeEvents();
        $this->model_extension_payment_mpgs_hosted_checkout->addEvents();
    }

    /**
     * @return mixed
     */
    public function paymentOptionsInquiry() {

        $uri         = $this->getApiUri() . '/paymentOptionsInquiry';
        return $this->apiRequest('POST', $uri);
    }

    /**
     * @param $apiGateway
     * @return string
     */
    public function getGatewayUri($apiGateway) {

        switch ($apiGateway) {
            case self::API_AMERICA:
                $gatewayUrl = 'https://na-gateway.mastercard.com/';
                break;

            case self::API_EUROPE:
                $gatewayUrl = 'https://eu-gateway.mastercard.com/';
                break;

            case self::API_ASIA:
                $gatewayUrl = 'https://ap-gateway.mastercard.com/';
                break;

            case self::API_MTF:
                $gatewayUrl = 'https://mtf.gateway.mastercard.com/';
                break;

            case self::API_OTHER:
            default:
                $gatewayUrl = $this->config->get(
                    'payment_mpgs_hosted_checkout_api_gateway_other'
                );
                break;
        }

        if (!empty($gatewayUrl) && substr($gatewayUrl, -1) !== '/') {
            $gatewayUrl .= '/';
        }

        return $gatewayUrl;
    }

    /**
     * @return string
     */
    public function getApiUri() {

        $apiGateway = $this->request->post['payment_mpgs_hosted_checkout_api_gateway_other'];

        return $this->getGatewayUri($apiGateway) . self::API_PATH_VERSION . self::API_VERSION .
           self::ENDPOINT_MERCHANT . $this->getMerchantId();
    }

    /**
     * @return mixed
     */
    public function getMerchantId() {

        if ($this->request->post['payment_mpgs_hosted_checkout_test']) {
            return $this->request->post['payment_mpgs_hosted_checkout_test_merchant_id'];
        } else {
            return $this->request->post['payment_mpgs_hosted_checkout_live_merchant_id'];
        }
    }

    /**
     * @return mixed
     */
    public function getApiPassword() {

        if ($this->request->post['payment_mpgs_hosted_checkout_test']) {
            return $this->request->post['payment_mpgs_hosted_checkout_test_api_password'];
        } else {
            return $this->request->post['payment_mpgs_hosted_checkout_live_api_password'];
        }
    }

    /**
     * @return mixed
     */
    public function isTestModeEnabled() {

        return isset($this->request->post['payment_mpgs_hosted_checkout_test'])
         ? (bool)$this->request->post['payment_mpgs_hosted_checkout_test']
         : false;
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled() {

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
    public function apiRequest($method, $uri, $data = []) {

        $userId     = 'merchant.' . $this->getMerchantId();
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

        $output             = curl_exec($curl);
        $httpResponseCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $responseText = 'Receive Response: "' . $httpResponseCode . '" for the request: "' .
                $method . ' ' . $uri . '" ';
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
    public function adminApiRequest($method, $uri, $data = []) {
        $testMode       = $this->config->get('payment_mpgs_hosted_checkout_test');
        $apiPassword    = $testMode
            ? $this->config->get('payment_mpgs_hosted_checkout_test_api_password')
            : $this->config->get('payment_mpgs_hosted_checkout_live_api_password');

        $merchantId = $testMode
            ? $this->config->get('payment_mpgs_hosted_checkout_test_merchant_id')
            : $this->config->get('payment_mpgs_hosted_checkout_live_merchant_id');

        $userId     = 'merchant.' . $merchantId;
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
        curl_setopt($curl, CURLOPT_USERPWD, $userId . ':' . $apiPassword);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $output             = curl_exec($curl);
        $httpResponseCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $responseText = 'Receive Response: "' . $httpResponseCode . '" for the request: "'
            . $method . ' ' . $uri . '" ';
        $responseText .= json_encode(['response' => json_decode($output)]);
        $this->log($responseText);

        return json_decode($output, true);
    }

    /**
     * Get Capture URI based on API Gateway
     * @return void
     * @throws Exception
     */
    public function capture() {

        $this->load->model(self::ROUTE_MPGS);
        try {
            $this->load->language(self::ROUTE_MPGS);
            $this->load->model(self::MODEL_SALE_ORDER);

            $comment            = $this->language->get('text_capture_sucess');
            $merchantId         = $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();
            $orderId = $this->request->post['order_id']
                ?? $this->request->get['order_id']
                ?? null;

            if (!$orderId) {
                $json = array(
                    'error' => true,
                    'msg' => 'Invalid order ID'
                );
                $this->response->setOutput(json_encode($json));
                return;
            }
            $captureOrderId     = $this->request->post['order_id'];
            $transactionHistory = $this->model_extension_payment_mpgs_hosted_checkout
                ->getTransactions($this->session->data['admin_order_id']);
            $captureAmount = null;
            $captureTransactionId = null;

            foreach ($transactionHistory as $transaction) {
                if ($transaction['type'] === 'AUTHORIZED' &&
                $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $captureAmount          = $transaction['amount'];
                    $captureTransactionId   = $transaction['transaction_id'];
                }
            }
            
            if ($captureAmount === null || $captureTransactionId === null) {
                throw new MpgsPaymentException('No authorized transaction found for capture.');
            }

            $completedStatusId      = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id');
            $newOrderId             = $this->extractOrderNumberFromString($captureOrderId);
            $newTxnId               = $this->getUniqueTransactionId($captureOrderId);
            $url                    = $this->getCaptureUri() . self::API_PATH_VERSION . self::API_VERSION .
                self::ENDPOINT_MERCHANT . $merchantId . self::ENDPOINT_ORDER .
                $captureOrderId . self::ENDPOINT_TRANSACTION . $newTxnId;
            $this->load->model(self::MODEL_LOCALISATION_CURRENCY);
            $defaultCurrencyCode    = $this->config->get('config_currency');
            $notify                 = "0";
            $requestData            = [
                'apiOperation'  => 'CAPTURE',
                'transaction'   => [
                    'amount'        => $captureAmount,
                    'currency'      => $defaultCurrencyCode,
                ],
                'order'         => [
                    'reference'     => $captureOrderId,
                ],
            ];
            $this->log( $requestData);
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            $this->log($response);

            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $status     = $response['order']['status'];
                $this->db->query(
                    self::SQL_UPDATE . DB_PREFIX . "mgps_order_transaction SET status ='" .
                    $this->db->escape($status) . "' , type = 'Captured' WHERE transaction_id = '" .
                    $this->db->escape($captureTransactionId) . self::SQL_WHERE_ORDER .
                    $this->db->escape($captureOrderId) . self::SQL_LIMIT_1
                );
                $this->db->query(
                    self::SQL_UPDATE . DB_PREFIX . "order SET order_status_id = '" .
                    (int)$completedStatusId . "' WHERE order_id = '" . (int)$newOrderId . "'"
                );
                $this->model_extension_payment_mpgs_hosted_checkout
                    ->addOrderHistory($newOrderId, $completedStatusId, $comment, $notify);
                $this->sendCustomerEmailFromResponse($response, $status, $newOrderId, 'Capture');
                $json = array(
                    'error' => false,
                    'msg'   => 'Transaction captured successfully'
                );
                $this->response->setOutput(json_encode($json));
            } else {
                throw new MpgsPaymentException('Transaction capture failed.');
            }
        } catch (MpgsPaymentException $e) {
            $this->log('Capture failed: ' . $e->getMessage());

            // Return meaningful message to user
            $json = array(
                'error' => true,
                'msg'   => 'Transaction capture failed. Please contact support.',
                'debug' => ($this->isDebugModeEnabled() ? $e->getMessage() : null)
            );
            $this->response->setOutput(json_encode($json));
        }
    }

    /**
     * Get Capture URI based on API Gateway
     * @return string
     * @throws Exception
     */
    public function void() {

        $this->load->model(self::ROUTE_MPGS);
        try {
            $this->load->language(self::ROUTE_MPGS);
            $this->load->model(self::MODEL_SALE_ORDER);

            $comment            = $this->language->get('text_void_success');
            $merchantId         = $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();
            $voidOrderId        = $this->request->post['order_id'];
            $transactionHistory = $this->model_extension_payment_mpgs_hosted_checkout
                ->getTransactions($this->session->data['admin_order_id'] );

            $voidDbTransactionId = null;

            foreach ($transactionHistory as $transaction) {
                if (strtoupper($transaction['type']) === 'AUTHORIZED' &&
                    $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $voidDbTransactionId = $transaction['transaction_id'];
                }
            }

            if ($voidDbTransactionId === null) {
                throw new MpgsPaymentException('No AUTHORIZED transaction found to void.');
            }

            $orderUrl = $this->getCaptureUri() . self::API_PATH_VERSION . self::API_VERSION .
                self::ENDPOINT_MERCHANT . $merchantId . self::ENDPOINT_ORDER . $voidOrderId;
            $orderData = $this->adminApiRequest('GET', $orderUrl);

            $voidTargetTxnId = $this->findAuthorizationTxnIdFromOrderData($orderData);

            if (empty($voidTargetTxnId)) {
                throw new MpgsPaymentException('Void failed: No authorization transaction found on the MPGS order.');
            }

            $completedStatusId      = $this->model_extension_payment_mpgs_hosted_checkout
                ->getOrderStatusIdByName('Voided');
            if (!$completedStatusId) {
                $completedStatusId = $this->config->get('payment_mpgs_hosted_checkout_declined_status_id');
            }
            $newOrderId             = $this->extractOrderNumberFromString($voidOrderId);
            $newTxnId               = $this->getUniqueTransactionId($voidOrderId);
            $url                    = $this->getCaptureUri() . self::API_PATH_VERSION .
                self::API_VERSION . self::ENDPOINT_MERCHANT . $merchantId .
                self::ENDPOINT_ORDER . $voidOrderId . self::ENDPOINT_TRANSACTION . $newTxnId;
            $this->load->model(self::MODEL_LOCALISATION_CURRENCY);
            $notify                 = "0";
            $requestData = array(
                'apiOperation'  => 'VOID',
                'transaction'   => [
                    'targetTransactionId' => $voidTargetTxnId,
                    'reference' => $newTxnId
                ]
            );
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $status     = $response['order']['status'];

                $this->db->query(
                    self::SQL_UPDATE . DB_PREFIX . "mgps_order_transaction SET status ='" .
                    $this->db->escape($status) . "' , type = 'Void' WHERE transaction_id = '" .
                    $this->db->escape($voidDbTransactionId) . self::SQL_WHERE_ORDER .
                    $this->db->escape($voidOrderId) . self::SQL_LIMIT_1
                );
                $this->db->query(
                    self::SQL_UPDATE . DB_PREFIX . self::SQL_SET_ORDER_STATUS .
                    (int)$completedStatusId . self::SQL_WHERE_ID . (int)$newOrderId . "'"
                );
                $this->model_extension_payment_mpgs_hosted_checkout
                    ->addOrderHistory($newOrderId, $completedStatusId, $comment, $notify);
                $json = array(
                    'error' => false,
                    'msg'   => 'Transaction voided successfully'
                );
                $this->response->setOutput(json_encode($json));
            } else {
                throw new MpgsPaymentException('Transaction void failed.');
            }
        } catch (MpgsPaymentException $e) {
            $this->log('Void failed: ' . $e->getMessage());

            // Return meaningful message to user
            $json = array(
                'error' => true,
                'msg'   => 'An error occurred while voiding the transaction.',
                'debug' => ($this->isDebugModeEnabled() ? $e->getMessage() : null)
            );
            $this->response->setOutput(json_encode($json));
        }
    }

    /**
     * Scan the MPGS order-data payload and return the sequential AUTHORIZATION
     * transaction ID, or null if none is found.
     *
     * @param array $orderData
     * @return string|null
     */
    private function findAuthorizationTxnIdFromOrderData(array $orderData): ?string {

        if (empty($orderData['transaction'])) {
            return null;
        }
        foreach ($orderData['transaction'] as $mpgsTxn) {
            if (strtoupper($mpgsTxn['transaction']['type'] ?? '') === 'AUTHORIZATION') {
                return $mpgsTxn['transaction']['id'];
            }
        }
        return null;
    }

    /**
     * @param $orderReference
     * @return string
     */
    private function getUniqueTransactionId($orderReference) {

        $uniqId = substr(uniqid(), 7, 6);

        return sprintf('%s-%s', $orderReference, $uniqId);
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

    /**
     * @param $completeOrderNumber
     * @return string|null
     */
    public function extractOrderNumberFromString($completeOrderNumber) {

        $orderPrefix = $this->config->get('payment_mpgs_hosted_checkout_order_id_prefix');

        if (!empty($orderPrefix)) {
            $prefixPos = strpos($completeOrderNumber, $orderPrefix);
            if ($prefixPos !== false) {
                $substring = substr($completeOrderNumber, $prefixPos + strlen($orderPrefix));
                preg_match('/\d+/', $substring, $matches);
                if (isset($matches[0])) {
                    return $matches[0];
                }
            }
        }

        // No prefix configured: the MPGS order ID is the OC order number itself
        preg_match('/\d+/', $completeOrderNumber, $matches);
        return $matches[0] ?? null;
    }

    /**
     * Get the URI for capturing transactions
     * @return string
     */
    public function getCaptureUri() {

        $apiGateway = $this->config->get('payment_mpgs_hosted_checkout_api_gateway');
        return $this->getGatewayUri($apiGateway);
    }

    /**
     * Process refund for a captured transaction
     *
     * @return void
     * @throws Exception
     */
    public function requestRefund() {

        try {
            $this->load->language(self::ROUTE_MPGS);
            $this->load->model(self::ROUTE_MPGS);
            $this->load->model(self::MODEL_SALE_ORDER);

            $captureOrderId     = $this->request->post['order_id'];
            $newTxnId           = $this->getUniqueTransactionId($captureOrderId);
            $newOrderId         = $this->extractOrderNumberFromString($captureOrderId);
            $transactionHistory = $this->model_extension_payment_mpgs_hosted_checkout
                ->getTransactions($this->session->data['admin_order_id'] );

            $captureAmount          = null;
            $captureTransactionId   = null;

            foreach ($transactionHistory as $transaction) {

                if (($transaction['type'] === 'Captured' || $transaction['type'] === 'CAPTURED')
                     && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                    $captureAmount          = $transaction['amount'];
                    $captureTransactionId   = $transaction['transaction_id'];
                }
            }

            if ($captureAmount === null || $captureTransactionId === null) {
                throw new MpgsPaymentException('No CAPTURED transaction found.');
            }

            $comment                = $this->language->get('text_refund_sucess');
            $merchantId             = $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();
            $refundStatusId         = $this->config->get('payment_mpgs_hosted_checkout_refund_status_id');
            $deniedStatusId         = $this->config->get('payment_mpgs_hosted_checkout_declined_status_id');
            $this->load->model(self::MODEL_LOCALISATION_CURRENCY);
            $defaultCurrencyCode    = $this->config->get('config_currency');
            $notify                 = "0";
            $url                    = $this->getCaptureUri() . self::API_PATH_VERSION .
                self::API_VERSION . self::ENDPOINT_MERCHANT . $merchantId .
                self::ENDPOINT_ORDER . $captureOrderId . self::ENDPOINT_TRANSACTION . $newTxnId ;
            $requestData = [
                'apiOperation' => 'REFUND',
                'transaction'  => [
                    'amount'   => $captureAmount,
                    'currency' => $defaultCurrencyCode,
                ]
            ];
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $transactionStatus  = $response['order']['status'];
                $refundedAmount     = $response['order']['totalRefundedAmount'];
                $this->db->query(
                    self::SQL_UPDATE . DB_PREFIX . "mgps_order_transaction SET status = '" .
                    $this->db->escape($transactionStatus) . "' , refunded_amount = '" .
                    $this->db->escape($refundedAmount) . "' , type = 'Captured' WHERE transaction_id = '" .
                    $this->db->escape($captureTransactionId) . self::SQL_WHERE_ORDER .
                    $this->db->escape($captureOrderId) . self::SQL_LIMIT_1
                );
                $this->db->query(
                    self::SQL_UPDATE . DB_PREFIX . self::SQL_SET_ORDER_STATUS .
                    (int)$refundStatusId . self::SQL_WHERE_ID . (int)$newOrderId . "'"
                );
                $this->model_extension_payment_mpgs_hosted_checkout
                    ->addOrderHistory($newOrderId, $refundStatusId, $comment, $notify);
                $this->sendCustomerEmailFromResponse($response, $transactionStatus, $newOrderId, 'Refund');
                $json = array(
                    'error' => false,
                    'msg'   => 'Transaction refunded successfully'
                );
                $this->response->setOutput(json_encode($json));
            } else {
                $errorMessage = self::TEXT_TRANSACTION_REFUND_FAILED;
                if (!empty($response['error']['explanation'])) {
                        $errorMessage .= ': ' . $response['error']['explanation'];
                }
                $json = array(
                    'error' => true,
                    'msg'   => $errorMessage
                );
                $this->response->setOutput(json_encode($json));
                $this->db->query(
                    self::SQL_UPDATE . DB_PREFIX . self::SQL_SET_ORDER_STATUS .
                    (int) $deniedStatusId . self::SQL_WHERE_ID . (int)$newOrderId . "'"
                );
                $this->model_extension_payment_mpgs_hosted_checkout
                ->addOrderHistory($newOrderId, $deniedStatusId, $errorMessage, $notify);
            }
        } catch (MpgsPaymentException $e) {
            $this->log('Refund failed: ' . $e->getMessage());

            $json = array(
                'error' => true,
                'msg'   => 'An error occurred while processing the refund.',
                'debug' => ($this->isDebugModeEnabled() ? $e->getMessage() : null)
            );
            $this->response->setOutput(json_encode($json));
        }
    }

    /**
     * Process partial refund for a captured transaction
     * @return void
     * @throws Exception
     */
    public function requestPartialRefund() {

        try {
            $this->load->language(self::ROUTE_MPGS);
            $this->load->model(self::ROUTE_MPGS);
            $this->load->model(self::MODEL_SALE_ORDER);
            $this->load->model(self::MODEL_LOCALISATION_CURRENCY);

            $captureOrderId = $this->request->post['order_id'];
            $newTxnId       = $this->getUniqueTransactionId($captureOrderId);
            $newOrderId     = $this->extractOrderNumberFromString($captureOrderId);
            $merchantId     = $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();

            $transactionHistory = $this->model_extension_payment_mpgs_hosted_checkout
                ->getTransactions($this->session->data['admin_order_id']);

            $captureTransactionId = '';
            foreach ($transactionHistory as $transaction) {
                // Match the original captured row or any subsequent partial-refund row
                $isCaptured = in_array(strtoupper($transaction['type']), ['CAPTURED', 'REFUNDED']);
                if ($isCaptured && $transaction['oc_order_id'] === $captureOrderId) {
                    $captureTransactionId = $transaction['transaction_id'];
                    break;
                }
            }

            $captureAmount          = $this->request->post['amount'];
            $refundStatusId         = $this->config->get('payment_mpgs_hosted_checkout_refund_status_id');
            $defaultCurrencyCode    = $this->config->get('config_currency');
            $currencyInfo           = $this->model_localisation_currency->getCurrencyByCode($defaultCurrencyCode);

            $currencySymbol = ($currencyInfo['symbol_left'] ?: $currencyInfo['symbol_right'])
             ?: '';
            $comment = $currencySymbol . $captureAmount . ' ' .
             $this->language->get('text_partial_refund_sucess');

            if (!empty($this->request->post['reason'])) {
                $comment .= "\nRefund reason: " . $this->request->post['reason'];
            }

            $url = $this->getCaptureUri() . self::API_PATH_VERSION . self::API_VERSION .
                self::ENDPOINT_MERCHANT . $merchantId . self::ENDPOINT_ORDER .
                $captureOrderId . self::ENDPOINT_TRANSACTION . $newTxnId;

            $requestData = [
                'apiOperation' => 'REFUND',
                'transaction'  => [
                    'amount'   => $captureAmount,
                    'currency' => $defaultCurrencyCode,
                    'taxAmount' => '0'
                ]
            ];

            $response = $this->adminApiRequest('PUT', $url, $requestData);
            $this->handleRefundResponse($response, [
                'newOrderId'           => $newOrderId,
                'captureOrderId'       => $captureOrderId,
                'captureTransactionId' => $captureTransactionId,
                'refundStatusId'       => $refundStatusId,
                'comment'              => $comment
            ]);

        } catch (MpgsPaymentException $e) {
            $this->log('Partial Refund failed: ' . $e->getMessage());
            $this->response->setOutput(json_encode([
                'error' => true,
                'msg'   => 'An error occurred while processing the refund.',
                'debug' => ($this->isDebugModeEnabled() ? $e->getMessage() : null)
            ]));
        }
    }

    /**
     * @param $response
     * @param $data
     */
    private function handleRefundResponse($response, $data) {
        $notify = "0";

        if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
            $sql = "UPDATE " . DB_PREFIX . "mgps_order_transaction SET
                status = '" . $this->db->escape($response['order']['status']) . "',
                refunded_amount = '" . $this->db->escape($response['order']['totalRefundedAmount']) . "',
                type = 'Refunded'
                WHERE transaction_id = '" . $this->db->escape($data['captureTransactionId']) . "'
                AND oc_order_id = '" . $this->db->escape($data['captureOrderId']) . "'
                LIMIT 1";

            $this->db->query($sql);
            $this->db->query(
                self::SQL_UPDATE . DB_PREFIX . self::SQL_SET_ORDER_STATUS .
                (int)$data['refundStatusId'] . self::SQL_WHERE_ID . (int)$data['newOrderId'] . "'"
            );
            $this->model_extension_payment_mpgs_hosted_checkout
                ->addOrderHistory($data['newOrderId'], $data['refundStatusId'], $data['comment'], $notify);
            $this->sendCustomerEmailFromResponse(
                $response,
                $response['order']['status'],
                $data['newOrderId'],
                'Refund'
            );

            return $this->response->setOutput(json_encode([
                'error' => false,
                'msg' => 'Transaction Partially Refunded'
            ]));
        }

        $errorMessage = self::TEXT_TRANSACTION_REFUND_FAILED;
        if (!empty($response['error']['explanation'])) {
            $errorMessage .= ': ' . $response['error']['explanation'];
        }

        if (!empty($response['result']) && $response['result'] === 'ERROR') {
            $this->model_extension_payment_mpgs_hosted_checkout
                ->addOrderHistory($data['newOrderId'], $data['refundStatusId'], $errorMessage, $notify);
        }

        $this->response->setOutput(json_encode([
            'error' => true,
            'msg' => $errorMessage
        ]));
    }

    /**
     * Send capture/refund customer email from gateway response. Mail failures are
     * logged and never fail the already-successful payment operation.
     *
     * @param array  $response
     * @param string $status
     * @param string $newOrderId
     * @param string $mailType Capture|Refund
     * @return void
     */
    private function sendCustomerEmailFromResponse(array $response, $status, $newOrderId, $mailType) {
        if (!$this->config->get('config_mail_engine')) {
            return;
        }

        $customerEmail = $response['customer']['email'] ?? '';
        $customerName  = trim(
            ($response['customer']['firstName'] ?? '') . ' ' . ($response['customer']['lastName'] ?? '')
        );

        if ($customerEmail === '') {
            return;
        }

        try {
            $this->sendCustomEmail($customerEmail, '', $status, $customerName, $newOrderId, $mailType);
        } catch (\Exception $mailEx) {
            $this->log($mailType . ' email failed: ' . $mailEx->getMessage());
        }
    }

    /**
     * Send custom capture/refund notification email to the customer.
     *
     * @param string $receiverAddress
     * @param string $ocOrderId
     * @param string $subject
     * @param string $customerName
     * @param string $newOrderId
     * @param string $mailType
     * @return void
     */
    private function sendCustomEmail($receiverAddress, $ocOrderId, $subject, $customerName, $newOrderId, $mailType) {
        $this->load->model(self::ROUTE_MPGS);
        $this->load->model(self::MODEL_LOCALISATION_CURRENCY);

        $ocOrderId = $this->session->data['admin_order_id'];
        $order = $this->model_extension_payment_mpgs_hosted_checkout->getOrder($newOrderId);
        $storeName = $this->config->get('config_name');
        $defaultCurrencyCode = $this->config->get('config_currency');
        $currencyInfo = $this->model_localisation_currency->getCurrencyByCode($defaultCurrencyCode);

        $data = [];
        if ($currencyInfo) {
            $data['currency'] = $currencyInfo['symbol_left'] ?: $currencyInfo['symbol_right'];
        }

        if (!$order || !$this->config->get('config_mail_engine')) {
            return;
        }

        $this->load->language(self::ROUTE_MPGS);
        $data['mgps_hosted_checkout_order'] = [
            'transactions' => $this->model_extension_payment_mpgs_hosted_checkout->getTransactions($ocOrderId)
        ];
        $data['order_id'] = $newOrderId;
        $data['user_token'] = $this->request->get['user_token'];
        $data['customer_name'] = $customerName;
        $data['receiver_address'] = $receiverAddress;
        $data['order_status'] = $subject;
        $data['mail_type'] = $mailType;

        $mail = new Mail($this->config->get('config_mail_engine'));
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode(
            $this->config->get('config_mail_smtp_password'),
            ENT_QUOTES,
            'UTF-8'
        );
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $mail->setTo($data['receiver_address']);
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender(html_entity_decode($storeName, ENT_QUOTES, 'UTF-8'));
        $mail->setSubject(html_entity_decode(
            $storeName . ' - Payment ' . ucwords(strtolower(str_replace('_', ' ', $subject))),
            ENT_QUOTES,
            'UTF-8'
        ));
        $mail->setHtml($this->load->view('extension/payment/mgps_hosted_checkout_email_template', $data));
        $mail->send();
    }

    /**
     * Send install telemetry once per shop. Builds sha256(session + shop_name +
     * shop_url), persists it, and posts shop details with the hash as bearer token.
     * Fires and fails silently — never interrupts install.
     *
     * @return void
     */
    private function maybeSendInstallTelemetry(): void {

        $installHash = $this->resolveInstallHashForTelemetry();

        if ($installHash === null) {
            return;
        }

        $this->model_extension_payment_mpgs_hosted_checkout
            ->sendInstallTelemetryAndPersistVersion($installHash);
    }

    /**
     * Send configured telemetry when live credentials are saved for the first
     * time or after a module version upgrade. Fires and fails silently.
     *
     * @return void
     */
    private function maybeSendConfiguredTelemetry(): void {

        if (!$this->shouldSendConfiguredTelemetry()) {
            return;
        }

        $installHash = $this->resolveInstallHashForTelemetry();
        if ($installHash === null) {
            return;
        }

        if (empty($this->config->get('mpgs_shop_install_hash'))) {
            if (!$this->model_extension_payment_mpgs_hosted_checkout
                ->sendInstallTelemetryAndPersistVersion($installHash)) {
                return;
            }

            $installHash = $this->getStoredInstallHash();
            if ($installHash === null) {
                return;
            }
        }

        $this->sendConfiguredTelemetry($installHash);
    }

    /**
     * @param string $installHash
     * @return void
     */
    private function sendConfiguredTelemetry(string $installHash): void {

        $this->model_extension_payment_mpgs_hosted_checkout->sendConfiguredTelemetry(
            $installHash,
            $this->getAdminSessionCookieValue()
        );
    }

    /**
     * @return bool
     */
    private function shouldSendConfiguredTelemetry(): bool {

        if (!$this->isLiveCredentialsSave()) {
            return false;
        }

        if (empty($this->config->get('mpgs_shop_install_hash')) && !$this->getAdminSessionCookieValue()) {
            return false;
        }

        if (empty($this->config->get('mpgs_shop_config_notified'))) {
            return true;
        }

        return $this->config->get('mpgs_shop_config_version') !== self::MODULE_VERSION;
    }

    /**
     * True when the form is saved in live mode with live merchant credentials present.
     *
     * @return bool
     */
    private function isLiveCredentialsSave(): bool {

        $post = $this->request->post;

        if (!empty($post['payment_mpgs_hosted_checkout_test'])) {
            return false;
        }

        $merchantId  = trim($post['payment_mpgs_hosted_checkout_live_merchant_id'] ?? '');
        $apiPassword = trim($post['payment_mpgs_hosted_checkout_live_api_password'] ?? '');

        return $merchantId !== '' && $apiPassword !== '';
    }

    /**
     * @return string|null
     */
    private function getStoredInstallHash(): ?string {

        $installHash = $this->config->get('mpgs_shop_install_hash');

        if (empty($installHash)) {
            return null;
        }

        return (string)$installHash;
    }

    /**
     * @return string|null
     */
    private function buildInstallHashForSession(): ?string {

        $sessionId = $this->getAdminSessionCookieValue();
        if ($sessionId === '') {
            return null;
        }

        return $this->model_extension_payment_mpgs_hosted_checkout
            ->buildInstallHash($sessionId);
    }

    /**
     * @return string|null
     */
    private function resolveInstallHashForTelemetry(): ?string {

        $installHash = $this->getStoredInstallHash();
        if ($installHash !== null) {
            return $installHash;
        }

        return $this->buildInstallHashForSession();
    }

    /**
     * @return string
     */
    private function getAdminSessionCookieValue(): string {

        $sessionName = $this->config->get('session_name') ?: 'OCSESSID';

        if (!empty($this->request->cookie[$sessionName])) {
            return (string)$this->request->cookie[$sessionName];
        }

        return (string)$this->session->getId();
    }

    /**
     * @return array|null
     */
    private function initializeData(): array {

        $latestVersion          = $this->getLatestGitHubVersion();
        $data['latest_version'] = $latestVersion;
        $data['update_message'] = $this->compareVersions(
            $latestVersion,
            self::MODULE_VERSION
        );
        $data['module_version'] = self::MODULE_VERSION;
        $data['api_version']    = self::API_VERSION;

        return $data;
    }

    /**
     * @return string
     */
    private function getLatestGitHubVersion() {

        $currentVersion = self::MODULE_VERSION;
        $currentVersionNormalized = ltrim((string)$currentVersion, 'vV');
        $parts = explode('.', $currentVersionNormalized);

        if (count($parts) < 2) {
            return null;
        }

        $currentSeries = $parts[0] . '.' . $parts[1];

        $owner  = 'fingent-corp';
        $repo   = self::REPO_NAME;
        $url    = "https://api.github.com/repos/{$owner}/{$repo}/releases?per_page=100";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mastercard');
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $releases = json_decode((string)$response, true);
        if (!is_array($releases)) {
            return null;
        }

        $bestVersion = null;
        foreach ($releases as $release) {
            if (!is_array($release)) {
                continue;
            }

            $tagName = $release['tag_name'] ?? null;
            if (!is_string($tagName) || $tagName === '') {
                continue;
            }

            // Skip drafts and pre-releases.
            if (!empty($release['draft']) || !empty($release['prerelease'])) {
                continue;
            }

            $tagNormalized = ltrim($tagName, 'vV');
            $tagParts = explode('.', $tagNormalized);
            if (count($tagParts) < 2) {
                continue;
            }

            $tagSeries = $tagParts[0] . '.' . $tagParts[1];
            if ($tagSeries !== $currentSeries) {
                continue;
            }

            if ($bestVersion === null || version_compare($tagNormalized, $bestVersion, '>')) {
                $bestVersion = $tagNormalized;
            }
        }

        return $bestVersion;
    }

    /**
     * @param string|null $latestVersion
     * @param string $currentVersion
     * @return string
     */
    private function compareVersions($latestVersion, $currentVersion): ?string {

        $releaseNotesLink = self::WIKI_URL;

        if ($latestVersion !== null && version_compare($latestVersion, $currentVersion, '>')) {
            $message = "A new version ({$latestVersion}) of the module is now available! ";
            $message .= "Please refer to the ";
            $message .= "<a href='{$releaseNotesLink}' target='_blank'>Release Notes</a> ";
            $message .= "section for information about its compatibility and features.";
            return $message;
        }

        return null;
    }
}
