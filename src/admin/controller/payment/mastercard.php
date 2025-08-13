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
 * @version  GIT: @1.3.4@
 * @link     https://github.com/fingent-corp/gateway-opencart-mastercard-module
 */

namespace Opencart\Admin\Controller\Extension\MasterCard\Payment;

use Opencart\Admin\Model\Extension\MasterCard\Payment;

use Opencart\Admin\Controller\Extension\MasterCard\Payment\MasterCardPaymentException;


class MasterCard extends \Opencart\System\Engine\Controller {

    public const PATHS = [
        'mastercard'       => 'extension/mastercard/payment/mastercard',
        'setting_model'    => 'setting/setting',
        'marketplace_model'=> 'marketplace/extension',
        'currency_model'   => 'localisation/currency',
        'model_sale_order' => 'sale/order',
        'repo_name'        => 'gateway-opencart-mastercard-module',
        'module_version'   => '1.3.4',
        'api_version'      => '100',
        'api_version_path' => 'api/rest/version/',
        'transaction_error' => 'An error has been occurred during the transaction.',
        'debig_log_file'   => 'extension/mastercard/payment/mastercard',
    ];

    public const DOCS = [
        'wiki_url'     =>
            'https://mpgs.fingent.wiki/target/opencart-mastercard-payment-gateway-services/
                release-notes/',
        'wiki_api_url' =>
            'https://mpgs.fingent.wiki/wp-json/mpgs/v2/update-repo-status',
        'wiki_api_token' =>
            '958a5f32a0439ac8e09bbc44ca6d9d66bd8fb785f10145f4a446ec0b4f00639',
    ];
    
    public const URL_PARAMS = [
        'user_token' => 'user_token=',
        'payment_type' => '&type=payment',
    ];

    public const API_REGIONS = [
        'america' => 'api_na',
        'europe'  => 'api_eu',
        'asia'    => 'api_ap',
        'mtf'     => 'api_mtf',
        'other'   => 'api_other',
    ];

    public const URI_SEGMENTS = [
        'merchant'     => '/merchant/',
        'order'        => '/order/',
        'transaction'  => '/transaction/',
    ];

    public const SQL = [
        'condition_oc_order_id'       => "' AND oc_order_id = '",
        'update_query_prefix'         => "UPDATE ",
        'limit_1'                     => " LIMIT 1",
        'update_order_status_query'   => "order SET order_status_id = '",
        'where_order_id'              => "' WHERE order_id = '",
    ];

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

    /**
     * Controller entry point for rendering the Mastercard module configuration page.
     *
     * This method performs the following actions:
     * - Installs any required setup via `install()`
     * - Loads necessary dependencies and initializes default data
     * - Handles form submission and validation if the request is a POST
     * - Prepares error messages, breadcrumbs, configuration fields, and logo previews
     * - Retrieves order statuses from the model
     * - Loads common header, sidebar, and footer controllers
     * - Renders the Mastercard configuration view with compiled data
     *
     * @return void
     */

    public function index(): void {
        $this->install();
        $this->loadDependencies();
        $data = $this->initializeData();
        if ($this->isPostRequest() && $this->validate()) {
            $this->handleFormSubmission();
        }
        $data = array_merge($data, $this->prepareErrors());
        $data = array_merge($data, $this->prepareBreadcrumbs());
        $data = array_merge($data, $this->loadConfigFields());
        $data = array_merge($data, $this->prepareLogoPreview());
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');


        $this->response->setOutput($this->load->view(self::PATHS['mastercard'], $data));
    }

    /**
     * Loads all required language files, models, styles, and scripts for the Mastercard module.
     *
     * This includes:
     * - Mastercard language file for translations
     * - Mastercard and setting models for configuration and data handling
     * - Order status model for status options
     * - Setting the page title using the language string
     * - Adding custom stylesheet and JavaScript for the admin interface
     *
     * @return void
     */
    private function loadDependencies(): void {
        $this->load->language(self::PATHS['mastercard']);
        $this->load->model(self::PATHS['mastercard']);
        $this->load->model(self::PATHS['setting_model']);
        $this->load->model('localisation/order_status');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('../extension/mastercard/admin/view/stylesheet/mastercard.css');
        $this->document->addScript('../extension/mastercard/admin/view/javascript/admin.js');
    }

    /**
     * Initializes and returns base data required by the Mastercard module configuration page.
     *
     * This includes:
     * - Latest available version from GitHub
     * - Update message based on version comparison
     * - Current module and API version from defined constants
     *
     * @return array The initialized configuration data
     */
    private function initializeData(): array {
        $latestVersion = $this->getLatestGitHubVersion();
        $data['latest_version'] = $latestVersion;
        $data['update_message'] = $this->compareVersions($latestVersion, '');
        $data['module_version'] = self::PATHS['module_version'];
        $data['api_version'] = self::PATHS['api_version'];
        return $data;
    }

    private function isPostRequest(): bool {
        return $this->request->server['REQUEST_METHOD'] === 'POST';
    }

    private function handleFormSubmission(): void {
        $this->load->model('localisation/country');
        $countryCode = $this->config->get('config_country_id');
        $countryInfo = $this->model_localisation_country->getCountry($countryCode);
        $this->model_setting_setting->editSetting('payment_mastercard', $this->request->post);
        $storedSettings = $this->model_setting_setting->getSetting('payment_mastercard');
        $merchantId = $storedSettings['payment_mastercard_live_merchant_id'] ?? null;
        $apiPassword = $storedSettings['payment_mastercard_live_api_password'] ?? null;
        $storedTagName = $storedSettings['payment_mastercard_version'] ?? null;
    
        if (!empty($merchantId) && !empty($apiPassword) && $storedTagName !== self::PATHS['module_version']) {
            $params = [
                'merchantId' => $merchantId,
                'apiPassword' => $apiPassword,
                'storedTagName' => $storedTagName,
                'tagName' => self::PATHS['module_version'],
                'countryInfo' => $countryInfo,
                'repoName' => self::PATHS['repo_name'],
                'pluginType' => 'enterprise',
                'latestRelease' => '1',
                'storedSettings' => $storedSettings,
            ];
    
            $this->updatePluginVersionIfNeeded($params);
        }
    
        $this->session->data['success'] = $this->language->get('text_success');
        $this->response->redirect($this->url->link(
            self::PATHS['marketplace_model'],
            self::URL_PARAMS['user_token'] . $this->session->data['user_token']
            . self::URL_PARAMS['payment_type'],
            true
        ));
    }
    
    private function prepareErrors(): array {
        $errorKeys = [
            'title',
            'live_merchant_id',
            'live_api_password',
            'test_merchant_id',
            'merchant_name',
            'api_gateway_other',
            'merchant_address_one',
            'merchant_address_two',
            'merchant_address_postal_zip_code',
            'merchant_country_state',
            'merchant_email',
            'merchant_phone',
            'invalid_image',
            'test_api_password',
            'credentials_validation',
            'payment_mastercard_merchant_logo',
            'validation_errors'
        ];

        $errors = [];
        foreach ($errorKeys as $key) {
            $dataKey = ($key === 'validation_errors') ? 'error_warning' : 'error_' . $key;
            $errors[$dataKey] = $this->error[$key] ?? '';
        }

        return $errors;
    }

    private function prepareBreadcrumbs(): array {
        return [
            'breadcrumbs' => [
                [
                    'text' => $this->language->get('text_home'),
                    'href' => $this->url->link(
                        'common/dashboard',
                        self::URL_PARAMS['user_token'] . $this->session->data['user_token'],
                        true
                    )
                ],
                [
                    'text' => $this->language->get('text_extension'),
                    'href' => $this->url->link(
                        self::PATHS['marketplace_model'],
                        self::URL_PARAMS['user_token'] . $this->session->data['user_token'] .
                        self::URL_PARAMS['payment_type'],
                        true
                    )
                ],
                [
                    'text' => $this->language->get('heading_title'),
                    'href' => $this->url->link(
                        self::PATHS['mastercard'],
                        self::URL_PARAMS['user_token'] . $this->session->data['user_token'],
                        true
                    )
                ]
            ],
            'action' => $this->url->link(
                self::PATHS['mastercard'],
                self::URL_PARAMS['user_token'] . $this->session->data['user_token'],
                true
            ),
            'cancel' => $this->url->link(
                self::PATHS['marketplace_model'],
                self::URL_PARAMS['user_token'] . $this->session->data['user_token'] .
                self::URL_PARAMS['payment_type'],
                true
            )
        ];
    }
    
    private function loadConfigFields(): array {
        $fields = [
            'payment_mastercard_status',
            'payment_mastercard_merchant_info',
            'payment_mastercard_initial_transaction' => 'authorize',
            'payment_mastercard_title' => 'Pay Using Mastercard Gateway',
            'payment_mastercard_live_merchant_id',
            'payment_mastercard_live_api_password',
            'payment_mastercard_test_merchant_id',
            'payment_mastercard_test_api_password',
            'payment_mastercard_live_notification_secret',
            'payment_mastercard_test_notification_secret',
            'payment_mastercard_merchant_name',
            'payment_mastercard_merchant_address_one',
            'payment_mastercard_merchant_address_two',
            'payment_mastercard_merchant_address_postal_zip_code',
            'payment_mastercard_merchant_country_state',
            'payment_mastercard_address_line_email',
            'payment_mastercard_address_line_phone',
            'payment_mastercard_api_gateway_other',
            'payment_mastercard_test',
            'payment_mastercard_integration_model' => 'hostedcheckout',
            'payment_mastercard_hc_type' => 'redirect',
            'payment_mastercard_send_line_items',
            'payment_mastercard_sort_order',
            'payment_mastercard_debug',
            'payment_mastercard_order_id_prefix',
            'payment_mastercard_approved_status_id' => '2',
            'payment_mastercard_declined_status_id' => '8',
            'payment_mastercard_pending_status_id' => '1',
            'payment_mastercard_risk_review_status_id'
        ];
    
        $result = [];
        foreach ($fields as $key => $default) {
            if (is_int($key)) {
                $key = $default;
                $default = null;
            }
    
            $value = $this->request->post[$key] ??
                     ($this->config->get($key) !== null ? $this->config->get($key) : $default);
    
            // Normalize URL field
            if ($key === 'payment_mastercard_api_gateway_other' && !empty($value)) {
                // Ensure it starts with https://
                if (!preg_match('#^https://#', $value)) {
                    $value = 'https://' . ltrim($value, '/');
                }
                // Ensure it ends with a slash
                if (substr($value, -1) !== '/') {
                    $value .= '/';
                }
            }
    
            $result[$key] = $value;
        }
    
        return $result;
    }
    

    private function prepareLogoPreview(): array {
        $this->load->model('tool/image');
        $merchantLogo = $this->config->get('payment_mastercard_merchant_logo');

        if (!empty($merchantLogo) && is_file(DIR_IMAGE . $merchantLogo)) {
            return [
                'thumb' => $this->model_tool_image->resize($merchantLogo, 100, 100),
                'image_path' => HTTP_CATALOG . 'image/' . $merchantLogo
            ];
        }

        return [
            'thumb' => $this->model_tool_image->resize('.././extension/mastercard/admin/view/image/payment/mclogo.svg', 100, 100),
            'image_path' => '.././extension/mastercard/admin/view/image/payment/mclogo.svg'
        ];
    }

    private function updatePluginVersionIfNeeded($params) {
        if (!$this->shouldUpdateVersion(
            $params['merchantId'],
            $params['apiPassword'],
            $params['storedTagName'],
            $params['tagName']
        )) {
            return;
        }
        $payload = $this->buildPayload(
            $params['countryInfo'],
            $params['repoName'],
            $params['pluginType'],
            $params['tagName'],
            $params['latestRelease']
        );
        $response = $this->sendVersionUpdateRequest($payload);
        if ($response['success']) {
            $params['storedSettings']['payment_mastercard_version'] = $params['tagName'];
            $this->model_setting_setting->editSetting(
                'payment_mastercard',
                $params['storedSettings']
            );
        }
    }
    
    private function shouldUpdateVersion($merchantId, $apiPassword, $storedTagName, $tagName) {
        return !empty($merchantId) && !empty($apiPassword) && $storedTagName !== $tagName;
    }
    
    private function buildPayload($countryInfo, $repoName, $pluginType, $tagName, $latestRelease) {
        return json_encode([
            'repo_name'      => $repoName,
            'plugin_type'    => $pluginType,
            'tag_name'       => $tagName,
            'latest_release' => $latestRelease,
            'country_code'   => $countryInfo['iso_code_2'] ?? '',
            'country'        => $countryInfo['name'] ?? '',
            'shop_name'      => $this->config->get('config_name'),
            'shop_url'       => HTTP_CATALOG,
        ]);
    }
    
    private function sendVersionUpdateRequest($payload) {
        $ch = curl_init(self::DOCS['wiki_api_url']);
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
            'Authorization: Bearer ' . self::DOCS['wiki_api_token'],
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        if (curl_errno($ch)) {
            curl_close($ch);
            return ['success' => false];
        }
    
        curl_close($ch);
    
        if ($httpCode !== 200) {
            return ['success' => false];
        }
    
        return ['success' => true, 'response' => $response];
    }
    
    protected function validate() {
        if (!$this->user->hasPermission('modify', self::PATHS['mastercard'])) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }
    
        if (!$this->validateCredentials()) {
            return false;
        }
    
        $this->validateMerchantFields();
        $this->validateOptionalFields();
    
        if (!empty($this->request->post['payment_mastercard_merchant_info'])
            && $this->request->post['payment_mastercard_merchant_info'] === '1') {
            $this->validateMerchantRedirectionCredentials();
        }
    
        if (empty($this->error)) {
            $this->validateCredentialsViaApi();
        }
    
        return empty($this->error);
    }

    private function validateCredentials(): bool {
        if (!empty($this->request->post['payment_mastercard_test']) &&
            $this->request->post['payment_mastercard_test'] == '1') {
            return $this->validateTestCredentials();
        } else {
            return $this->validateLiveCredentials();
        }
    }

    private function validateMerchantRedirectionCredentials(): bool {
        $send_merchant_info = $this->request->post['payment_mastercard_merchant_info'] ?? '0';
        $merchant_name = $this->request->post['payment_mastercard_merchant_name'] ?? '';
        $hc_type = $this->request->post['payment_mastercard_hc_type'] ?? '';
        $hasError = false;
    
        if ($send_merchant_info === '1' && $hc_type === 'redirect' && empty($merchant_name)) {
            $this->addCredentialError('merchant_name', 'error_red_merchant_name');
            $hasError = true;
        }
    
        return !$hasError;
    }
    
    
    private function validateTestCredentials() {
        $title = $this->request->post['payment_mastercard_title'] ?? '';
        $id = $this->request->post['payment_mastercard_test_merchant_id'] ?? '';
        $password = $this->request->post['payment_mastercard_test_api_password'] ?? '';
        $custom_gateway_url = $this->request->post['payment_mastercard_api_gateway_other'] ?? '';
        $hasError = false;
        
        if (empty($title)) {
            $this->addCredentialError('test_title', 'error_entry_title');
            $hasError = true;
        }

        if (empty($id)) {
            $this->addCredentialError('test_merchant_id', 'error_test_merchant_id');
            $hasError = true;
        } elseif (stripos($id, 'TEST') === false) {
            $this->addCredentialError('test_merchant_id', 'error_test_merchant_id_prefix');
            $hasError = true;
        }

        if (empty($custom_gateway_url)) {
            $this->addCredentialError('api_gateway_other', 'error_api_gateway_other');
            $hasError = true;
        }

        if (empty($password)) {
            $this->addCredentialError('test_api_password', 'error_test_api_password');
            $hasError = true;
        }

        return !$hasError;
    }
    
    private function validateLiveCredentials(): bool {
        $id = $this->request->post['payment_mastercard_live_merchant_id'] ?? '';
        $password = $this->request->post['payment_mastercard_live_api_password'] ?? '';
        $custom_gateway_url = $this->request->post['payment_mastercard_api_gateway_other'] ?? '';
        $hasError = false;
    
        if (empty($id)) {
            $this->addCredentialError('live_merchant_id', 'error_live_merchant_id');
            $hasError = true;
        } elseif (stripos($id, 'TEST') !== false) {
            $this->addCredentialError('live_merchant_id', 'error_live_merchant_id_prefix');
            $hasError = true;
        }
    
        if (empty($password)) {
            $this->addCredentialError('live_api_password', 'error_live_api_password');
            $hasError = true;
        }
    
        if (empty($custom_gateway_url)) {
            $this->addCredentialError('api_gateway_other', 'error_api_gateway_other');
            $hasError = true;
        }
    
        return !$hasError;
    }
    
    private function addCredentialError($fieldKey, $errorKey) {
        $this->error[$fieldKey] = $this->language->get($errorKey);
        $this->error['credentials_validation'] = $this->language->get('error_warning');
    }
    
    private function validateMerchantFields() {
        $this->validateFieldLength(
            'payment_mastercard_merchant_name',
            'merchant_name',
            40
        );
        $this->validateFieldLength(
            'payment_mastercard_merchant_address_one',
            'merchant_address_one',
            100
        );
        $this->validateFieldLength(
            'payment_mastercard_merchant_address_two',
            'merchant_address_two',
            100
        );
        $this->validateFieldLength(
            'payment_mastercard_merchant_address_postal_zip_code',
            'merchant_address_postal_zip_code',
            100
        );
        $this->validateFieldLength(
            'payment_mastercard_merchant_country_state',
            'merchant_country_state',
            100
        );
        $this->validateFieldLength(
            'payment_mastercard_address_line_phone',
            'merchant_phone',
            20
        );
    }

    private function validateOptionalFields() {
        if (!empty($this->request->post['payment_mastercard_address_line_email'])) {
            $this->validateEmail('payment_mastercard_address_line_email', 'merchant_email');
        }
    
        if (!empty($this->request->post['payment_mastercard_merchant_logo'])) {
            $this->validateLogo('payment_mastercard_merchant_logo');
        }
    }
    
    private function validateCredentialsViaApi() {
        $response = $this->paymentOptionsInquiry();
        if (!isset($response['result']) || $response['result'] !== 'ERROR') {
            return;
        }
        $explanation = $response['error']['explanation'] ?? '';
        $cause = $response['error']['cause'] ?? '';
        if ($explanation === 'Invalid credentials.') {
            $this->error['credentials_validation'] =
            $this->language->get('error_credentials_validation');
        } else {
            $this->error['credentials_validation'] = sprintf('%s: %s', $cause, $explanation);
        }
    }
    
    private function validateFieldLength($field, $errorKey, $maxLength) {
        if (!empty($this->request->post[$field])) {
            $fieldValue = $this->request->post[$field];
            if (strlen($fieldValue) > $maxLength) {
                $this->error[$errorKey] = $this->language->get("error_{$errorKey}");
                $this->error['credentials_validation'] = $this->language->get('error_warning');
            }
        }
    }
    
    private function validateEmail($field, $errorKey) {
        $email = $this->request->post[$field];
    
        if (strlen($email) < 4) {
            $this->error[$errorKey] = $this->language->get("error_{$errorKey}_length");
            $this->error['credentials_validation'] = $this->language->get('error_warning');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error[$errorKey] = $this->language->get("error_{$errorKey}");
            $this->error['credentials_validation'] = $this->language->get('error_warning');
        }
    }
    
    private function validateLogo($field) {
        $relativePath = str_replace('catalog/', '', $this->request->post[$field]);
        $imagePath = DIR_IMAGE . 'catalog/' . $relativePath;
    
        if (is_file($imagePath)) {
            $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $mime = mime_content_type($imagePath);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'svg'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/svg+xml'];
    
            if (!in_array($extension, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                $this->error['credentials_validation'] = $this->language->get('error_warning');
                $this->error[$field] = $this->language->get('error_invalid_image');
            }
        } else {
            $this->error[$field] = $this->language->get('error_invalid_image');
        }
    }
    

    public function order() {
        $this->load->model(self::PATHS['mastercard']);
        $this->load->model(self::PATHS['currency_model']);
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
            $this->load->language(self::PATHS['mastercard']);
            $data['mgps_hosted_checkout_order'] = array(
                'transactions' => $this->model_extension_mastercard_payment_mastercard->
                getTransactions(
                    $this->request->get['order_id']
                )
            );
            $data['order_id'] = $this->request->get['order_id'];
            $data['user_token'] = $this->request->get['user_token'];
            return $this->load->view('extension/mastercard/payment/mastercard_order',$data);
        }
    }
    
    public function install() {
        $this->load->model(self::PATHS['mastercard']);
        $this->model_extension_mastercard_payment_mastercard->install();
        $this->model_extension_mastercard_payment_mastercard->deleteEvents();
        $this->model_extension_mastercard_payment_mastercard->addEvents();
    }

    public function uninstall() {
        $this->load->model(self::PATHS['mastercard']);
        $this->load->model('setting/event');
        $this->model_extension_mastercard_payment_mastercard->uninstall();
        $this->model_extension_mastercard_payment_mastercard->deleteEvents();
    }
    

    public function paymentOptionsInquiry() {
        return $this->apiRequest('POST', $this->getApiUri() . '/paymentOptionsInquiry');
    }

    /**
     * Get the Mastercard API Gateway URL based on the specified region.
     *
     * @param string $apiGateway Region key from self::API_REGIONS (e.g., 'america', 'europe').
     *
     * @return string Gateway base URL ending with a trailing slash.
     *
     * @throws \MasterCardPaymentException If the specified region is not recognized.
     */
    public function getGatewayUri($apiGateway) {
        $gatewayUrl = '';
    
        if ($apiGateway === self::API_REGIONS['america']) {
            $gatewayUrl = 'https://na-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_REGIONS['europe']) {
            $gatewayUrl = 'https://eu-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_REGIONS['asia']) {
            $gatewayUrl = 'https://ap-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_REGIONS['mtf']) {
            $gatewayUrl = 'https://mtf.gateway.mastercard.com/';
        } else{
            $url = $this->config->get('payment_mastercard_api_gateway_other');
            if (!empty($url)) {
                if (substr($url, -1) !== '/') {
                    $url .= '/';
                }
                $gatewayUrl = $url;
            }

        }
        return $gatewayUrl;
    }
    
    /**
     * Builds and returns the full Mastercard API URI based on configuration.
     *
     * Retrieves the selected API gateway region from the request,
     * resolves its base URI, and appends version and merchant segments
     * to form the complete API endpoint.
     *
     * @return string Full Mastercard API endpoint URI.
     */
    public function getApiUri(): string {
        if (!empty($this->request->post['payment_mastercard_api_gateway_other'])) {
            $baseUri = rtrim($this->request->post['payment_mastercard_api_gateway_other'], '/');
            if (strpos($baseUri, 'https://') !== 0) {
                $baseUri = 'https://' . $baseUri;
            }
        } else {
            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('payment_mastercard');
            $baseUri = rtrim($settings['payment_mastercard_api_gateway_other'] ?? '', '/');
            if (strpos($baseUri, 'https://') !== 0) {
                $baseUri = 'https://' . $baseUri;
            }
        }

        if (substr($baseUri, -1) !== '/') {
            $baseUri .= '/';
        }
    
        return $baseUri . ltrim(self::PATHS['api_version_path'], '/')
            . self::PATHS['api_version']
            . self::URI_SEGMENTS['merchant']
            . $this->getMerchantId();
    }
    
    /**
     * Retrieves the base URI for capture requests based on configured API gateway region.
     *
     * @return string The base URI for the Mastercard API capture endpoint.
     */
    public function getCaptureUri() {
        $apiGateway = $this->config->get('payment_mastercard_api_gateway_other');
        return $apiGateway;
    }

    public function getMerchantId() {
        if ($this->request->post['payment_mastercard_test']) {
            return $this->request->post['payment_mastercard_test_merchant_id'];
        } else {
            return $this->request->post['payment_mastercard_live_merchant_id'];
        }
    }

    public function getApiPassword() {
        if ($this->request->post['payment_mastercard_test']) {
            return $this->request->post['payment_mastercard_test_api_password'];
        } else {
            return $this->request->post['payment_mastercard_live_api_password'];
        }
    }

    public function isTestModeEnabled() {
        return $this->config->get('payment_mastercard_test');
    }

    public function isDebugModeEnabled() {
        return $this->config->get('payment_mastercard_debug');
    }
    
    public function extractOrderNumberFromString($completeOrderNumber) {
        $orderPrefix = $this->config->get('payment_mastercard_order_id_prefix');
        $prefixPos = false;
            
        if (!empty($orderPrefix)) {
            $prefixPos = strpos($completeOrderNumber, $orderPrefix);
        }
            
        if ($prefixPos !== false) {
                $substring = substr($completeOrderNumber, $prefixPos + strlen($orderPrefix));
                $pattern = '/\d+/';
                preg_match($pattern, $substring, $matches);
        
                if (isset($matches[0])) {
                    return $matches[0];
                }
            }
        
        return null;
    }

    /**
     * Sends an HTTP request to the Mastercard API using cURL.
     *
     * Supports POST and PUT methods with optional JSON-encoded payload.
     * Uses basic authentication with merchant credentials.
     *
     * @param string $method The HTTP method ('POST' or 'PUT').
     * @param string $uri The full API endpoint URI.
     * @param array  $data Optional request payload to be JSON-encoded.
     *
     * @return array|null The decoded JSON response from the API, or null on failure.
     */
    public function apiRequest($method, $uri, $data = []) {
        $userId = 'merchant.' . $this->getMerchantId();
        $curl = curl_init();
    
        switch ($method) {
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
        curl_close($curl);
    
        return json_decode($output, true);
    }
    
    /**
     * @param $method
     * @param $uri
     * @param array $data
     * @return mixed
     */
    public function adminApiRequest($method, $uri, $data = []) {
        $testMode = $this->config->get('payment_mastercard_test');
        $apiPassword = $testMode
                    ? $this->config->get('payment_mastercard_test_api_password')
                    : $this->config->get('payment_mastercard_live_api_password');
        $merchantId = $testMode
                    ? $this->config->get('payment_mastercard_test_merchant_id')
                    : $this->config->get('payment_mastercard_live_merchant_id');
        $userId = 'merchant.' . $merchantId;
    
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
        curl_setopt($curl, CURLOPT_USERPWD, $userId . ':' . $apiPassword);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    
        return json_decode($output, true);
    }

    /**
     * @param $message
     */
    public function log($message) {
        if ($this->isDebugModeEnabled()) {
            $this->debugLog = new \Opencart\System\Library\Log(self::PATHS['debig_log_file']);
            $this->debugLog->write($message);
        }
    }

    public function capture() {
        $this->load->model(self::PATHS['mastercard']);
        try {
            $this->load->language(self::PATHS['mastercard']);
            $this->load->model(self::PATHS['model_sale_order']);
            $merchantId = $this->model_extension_mastercard_payment_mastercard->getMerchantId();
            $captureOrderId = $this->request->post['order_id'];
            $transactionHistory = $this->model_extension_mastercard_payment_mastercard
                                ->getTransactions($this->session->data['admin_order_id']);
            foreach ($transactionHistory as $transaction) {
                if ($transaction['type'] === 'AUTHORIZED'
                    && $transaction['oc_order_id'] === $this->request->post['order_id']) {
                        $captureAmount = $transaction['amount'];
                        $captureTransactionId = $transaction['transaction_id'];
                }
            }
            $completedStatusId = $this->config->get('payment_mastercard_approved_status_id');
            $newOrderId = $this->extractOrderNumberFromString($captureOrderId);
            $newTxnId = $this->getUniqueTransactionId($captureOrderId);
            $url = $this->getCaptureUri()
                . self::PATHS['api_version_path']
                . self::PATHS['api_version']
                . self::URI_SEGMENTS['merchant']
                . $merchantId
                . self::URI_SEGMENTS['order']
                . $captureOrderId
                . self::URI_SEGMENTS['transaction']
                . $newTxnId;
            $this->load->model(self::PATHS['currency_model']);
            $defaultCurrencyCode = $this->config->get('config_currency');
            $notify = "0";
            $requestData = [
                'apiOperation' => 'CAPTURE',
                'transaction' => [
                    'amount' => $captureAmount,
                    'currency' => $defaultCurrencyCode,
                ],
                'order'             => array(

                    'reference'       => $captureOrderId,
                ),
            ];
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $status = $response['order']['status'];
                $mailType = "Capture";
                $customerEmail       = $response['customer']['email'];
                $customerName = isset($response['customer']['firstName'],
                                 $response['customer']['lastName'])
                                ? $response['customer']['firstName'] . ' '
                                . $response['customer']['lastName']
                                : 'N/A';
                $transactionId = isset($response['transaction']['id']) ? $response['transaction']['id'] : '';
                $authCode = isset($response['transaction']['authorizationCode']) ? $response['transaction']['authorizationCode'] : '';
                                
                if (!empty($authCode)) {
                    $comment = sprintf($this->language->get('text_capture_sucess'), $transactionId, $authCode);
                } else {
                    $comment = sprintf($this->language->get('text_payment_captured_no_auth'), $transactionId);
                }
                                
                $this->db->query(
                    self::SQL['update_query_prefix'] . DB_PREFIX
                    . "mgps_order_transaction  SET status = '" .
                     $status . "', type = 'Captured'
                     WHERE transaction_id = '" . $captureTransactionId
                     . self::SQL['condition_oc_order_id'] . $captureOrderId . "' "
                     . self::SQL['limit_1']
                );
                $this->db->query(
                    self::SQL['update_query_prefix'] . DB_PREFIX
                    . self::SQL['update_order_status_query']
                    . (int)$completedStatusId . self::SQL['where_order_id']
                    . (int)$newOrderId . "'"
                );
                $this->model_extension_mastercard_payment_mastercard->addOrderHistory(
                    $newOrderId,
                    $completedStatusId,
                    $comment,
                    $notify
                );
                if ($this->config->get('config_mail_engine')) {
                    $this->sendCustomEmail(
                        $customerEmail,
                        $newOrderId,
                        $status,
                        $customerName,
                        $mailType
                    );
                }
                $json = array(
                    'error' => false,
                    'msg' => 'Transaction captured successfully'
                );
                $this->response->setOutput(json_encode($json));
            } else {
                throw new \MasterCardPaymentException('Transaction capture failed.');
            }
        } catch (\MasterCardPaymentException $e) {
            $json = array(
                'error' => true,
                'msg' => self::PATHS['transaction_error']
            );
            $this->response->setOutput(json_encode($json));
        }
    }

    private function sendCustomEmail(
        $recieverAddress,
        $newOrderId,
        $subject,
        $customerName,
        $mailType
    ) {
        $this->load->model(self::PATHS['mastercard']);
        $this->load->model(self::PATHS['currency_model']);
        $processedOrderid = $newOrderId;
        $adminOrderId = $this->session->data['admin_order_id'];
        $order = $this->model_extension_mastercard_payment_mastercard->getOrder($newOrderId);
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
            $this->load->language(self::PATHS['mastercard']);
            $data['mgps_hosted_checkout_order'] = [
                'transactions' => $this->model_extension_mastercard_payment_mastercard
                    ->getTransactions($adminOrderId)
            ];
            $data['order_id'] = $processedOrderid;
            $data['user_token'] = $this->request->get['user_token'];
            $data['customer_name'] = $customerName;
            $data['receiver_address'] = $recieverAddress;
            $data['order_status'] = $subject;
            $data['mail_type'] = $mailType;
    
            if ($this->config->get('config_mail_engine')) {
                $mailOption = [
                    'parameter'     => $this->config->get('config_mail_parameter'),
                    'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
                    'smtp_username' => $this->config->get('config_mail_smtp_username'),
                    'smtp_password' => html_entity_decode(
                        $this->config->get('config_mail_smtp_password'),
                        ENT_QUOTES,
                        'UTF-8'
                    ),
                    'smtp_port'     => $this->config->get('config_mail_smtp_port'),
                    'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
                ];
                $mail = new \Opencart\System\Library\Mail(
                    $this->config->get('config_mail_engine'),
                    $mailOption
                );
                $mail->setTo($recieverAddress);
                $mail->setFrom($this->config->get('config_email'));
                $mail->setSender($this->config->get('config_name'));
                $formattedSubject = ucwords(strtolower(str_replace('_', ' ', $subject)));
                $mail->setSubject(
                    html_entity_decode("Payment {$formattedSubject}", ENT_QUOTES, 'UTF-8')
                );
                $mail->setHtml(
                    $this->load->view(
                        'extension/mastercard/payment/mgps_hosted_checkout_mail',
                        $data
                    )
                );
                $mail->send();
            }
        }
    }
    
    private function getUniqueTransactionId($orderReference) {
        $uniqId = substr(uniqid(), 7, 6);
        return sprintf('%s-%s', $orderReference, $uniqId);
    }

    public function RequestRefund() {
        $result = null;
        try {
            $this->initializeRefundDependencies();
            $captureOrderId = $this->request->post['order_id'];
            $newTxnId = $this->getUniqueTransactionId($captureOrderId);
            $newOrderId = $this->extractOrderNumberFromString($captureOrderId);
            $transactionData = $this->getCaptureTransactionDetails($captureOrderId);
            if (!$transactionData) {
                $result = $this->respondWithError('Captured transaction not found');
            } else {
                $response = $this->sendRefundRequest(
                    $captureOrderId,
                    $newTxnId,
                    $transactionData['amount']
                );
    
                if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                    $this->handleSuccessfulRefund(
                        $response,
                        $transactionData['transaction_id'],
                        $captureOrderId,
                        $newOrderId
                    );
    
                    $result = ['success' => true, 'message' => 'Refund successful'];
                } else {
                    $result = $this->respondWithError('Transaction refund failed');
                }
            }
        } catch (\MasterCardPaymentException $e) {
            $result = $this->respondWithError(self::PATHS['transaction_error']);
        }
    
        return $result;
    }
    
    private function initializeRefundDependencies() {
        $this->load->language(self::PATHS['mastercard']);
        $this->load->model(self::PATHS['mastercard']);
        $this->load->model(self::PATHS['model_sale_order']);
        $this->load->model(self::PATHS['currency_model']);
    }
    
    private function getCaptureTransactionDetails($orderId) {
        $transactions = $this->model_extension_mastercard_payment_mastercard
                             ->getTransactions($this->session->data['admin_order_id']);
    
        foreach ($transactions as $transaction) {
            if (
                in_array(strtoupper($transaction['type']), ['CAPTURED']) &&
                $transaction['oc_order_id'] === $orderId
            ) {
                return [
                    'amount' => $transaction['amount'],
                    'transaction_id' => $transaction['transaction_id']
                ];
            }
        }
        return null;
    }
    
    private function sendRefundRequest($captureOrderId, $newTxnId, $amount) {
        $merchantId = $this->model_extension_mastercard_payment_mastercard->getMerchantId();
        $currencyCode = $this->config->get('config_currency');
    
        $url = $this->getCaptureUri() . self::PATHS['api_version_path']
             . self::PATHS['api_version'] . self::URI_SEGMENTS['merchant'] . $merchantId
             . self::URI_SEGMENTS['order'] . $captureOrderId . self::URI_SEGMENTS['transaction']
             . $newTxnId;
    
        $requestData = [
            'apiOperation' => 'REFUND',
            'transaction' => [
                'amount' => $amount,
                'currency' => $currencyCode,
            ]
        ];

        return $this->adminApiRequest('PUT', $url, $requestData);
    }
    
    private function handleSuccessfulRefund(
        $response,
        $captureTransactionId,
        $captureOrderId,
        $newOrderId
    ) {
        $status = $response['order']['status'];
        $refundedAmount = $response['order']['totalRefundedAmount'];
        $refundStatusId = $this
            ->model_extension_mastercard_payment_mastercard
            ->getOrderStatusIdByName("Refunded");
        $this->updateTransactionAndOrderStatus(
            $status,
            $refundedAmount,
            $captureTransactionId,
            $captureOrderId,
            $newOrderId,
            $refundStatusId
        );
        $this->addOrderHistoryAndNotifyCustomer($response, $newOrderId, $refundStatusId, $status);
        $this->response->setOutput(json_encode([
            'error' => false,
            'msg' => 'Transaction refunded successfully'
        ]));
    }
    
    private function updateTransactionAndOrderStatus(
        $status,
        $refundedAmount,
        $txnId,
        $captureOrderId,
        $newOrderId,
        $refundStatusId
    ) {
        $this->db->query(
            self::SQL['update_query_prefix'] . DB_PREFIX . "mgps_order_transaction
             SET status = '" . $status . "',
                 refunded_amount = '" . $refundedAmount . "',
                 type = 'Captured'
             WHERE transaction_id = '" . $txnId
             . self::SQL['condition_oc_order_id'] . $captureOrderId . "'
             " . self::SQL['limit_1']
        );
    
        $this->db->query(
            self::SQL['update_query_prefix'] . DB_PREFIX .
            self::SQL['update_order_status_query'] . (int)$refundStatusId .
            self::SQL['where_order_id'] . (int)$newOrderId . "'"
        );
    }
    
    private function addOrderHistoryAndNotifyCustomer(
        $response,
        $newOrderId,
        $refundStatusId,
        $status
    ) {

        $authCode = isset($response['transaction']['authorizationCode']) ? $response['transaction']['authorizationCode'] : '';
        if (!empty($authCode)) {
            $comment = sprintf($this->language->get('text_refund_sucess'), $refundStatusId, $authCode);
        } else{
            $comment = sprintf($this->language->get('text_refund_sucess_no_auth'), $refundStatusId);
        }
        $notify = "0";
    
        $this->model_extension_mastercard_payment_mastercard->addOrderHistory(
            $newOrderId,
            $refundStatusId,
            $comment,
            $notify
        );
    
        if ($this->config->get('config_mail_engine')) {
            $firstName = $response['customer']['firstName'] ?? '';
            $lastName  = $response['customer']['lastName'] ?? '';
            $customerName = trim($firstName . ' ' . $lastName);
            $this->sendCustomEmail(
                $response['customer']['email'],
                $newOrderId,
                $status,
                $customerName,
                'Refund'
            );
            
        }
    }
    
    private function respondWithError($message) {
        $this->response->setOutput(json_encode([
            'error' => true,
            'msg' => $message
        ]));
    
        return null;
    }
    
    public function RequestPartialRefund() {
        try {
            $this->initializeRefundDependencies();
            $captureOrderId = $this->request->post['order_id'];
            $newTxnId = $this->getUniqueTransactionId($captureOrderId);
            $newOrderId = $this->extractOrderNumberFromString($captureOrderId);
            $merchantId = $this->model_extension_mastercard_payment_mastercard->getMerchantId();
            $orderId = $this->session->data['admin_order_id'];
    
            $captureTransactionId = $this->getCaptureTransactionId($orderId, $captureOrderId);
            $captureAmount = $this->request->post['amount'];
            $currencyCode = $this->config->get('config_currency');
            $currencySymbol = $this->getCurrencySymbol($currencyCode);
            $url = $this->buildRefundUrl($merchantId, $captureOrderId, $newTxnId);
            $requestData = $this->buildRefundRequestData($captureAmount, $currencyCode);
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            $authCode = isset($response['transaction']['authorizationCode']) ? $response['transaction']['authorizationCode'] : '';
            $comment = $this->buildRefundComment($currencySymbol, $captureAmount, $authCode);
            $this->handleRefundResponse(
                $response,
                $newOrderId,
                $captureOrderId,
                $captureTransactionId,
                $comment
            );
        } catch (\MasterCardPaymentException $e) {
            $this->response->setOutput(json_encode([
                'error' => true,
                'msg' => self::PATHS['transaction_error']
            ]));
        }
    }
    
    private function getCaptureTransactionId($orderId, $captureOrderId) {
        $transactions =
            $this->model_extension_mastercard_payment_mastercard->getTransactions($orderId);
        foreach ($transactions as $transaction) {
            if (
                (strcasecmp($transaction['type'], 'Captured') === 0) &&
                $transaction['oc_order_id'] === $captureOrderId
            ) {
                return $transaction['transaction_id'];
            }
        }
        return null;
    }
    
    private function getCurrencySymbol($currencyCode) {
        $currencyInfo = $this->model_localisation_currency->getCurrencyByCode($currencyCode);
        return $currencyInfo['symbol_left'] ?: $currencyInfo['symbol_right'];
    }
    
    private function buildRefundComment($symbol, $amount,$authCode = '') {
        $comment = $symbol . $amount . ' ' . $this->language->get('text_partial_refund_sucess');
        if (!empty($authCode)) {
            $comment .= ' (Auth Code: ' . $authCode . ')';
        }
        if (!empty($this->request->post['reason'])) {
            $comment .= "\nRefund reason: " . $this->request->post['reason'];
        }
        return $comment;
    }
    
    private function buildRefundUrl($merchantId, $orderId, $txnId) {
        return $this->getCaptureUri() . self::PATHS['api_version_path']
        . self::PATHS['api_version'] .
            self::URI_SEGMENTS['merchant'] . $merchantId .
            self::URI_SEGMENTS['order'] . $orderId .
            self::URI_SEGMENTS['transaction'] . $txnId;
    }
    
    private function buildRefundRequestData($amount, $currencyCode) {
        return [
            'apiOperation' => 'REFUND',
            'transaction' => [
                'amount' => $amount,
                'currency' => $currencyCode,
                'taxAmount' => '0'
            ]
        ];
    }
    
    private function handleRefundResponse(
        $response,
        $newOrderId,
        $captureOrderId,
        $captureTransactionId,
        $comment
    ) {
        if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
            $status = $response['order']['status'];
            $refundedAmount = $response['order']['totalRefundedAmount'];
            $customerEmail = $response['customer']['email'] ?? '';
            $firstName = $response['customer']['firstName'] ?? '';
            $lastName = $response['customer']['lastName'] ?? '';
            $customerName = trim($firstName . ' ' . $lastName);
            $refundStatusId = $this
            ->model_extension_mastercard_payment_mastercard
            ->getOrderStatusIdByName("Refunded");

            $this->db->query(
                self::SQL['update_query_prefix'] . DB_PREFIX . "mgps_order_transaction
                    SET status = '" . $status . "',
                        refunded_amount = '" . $refundedAmount . "',
                        type = 'Captured'
                    WHERE transaction_id = '" . $captureTransactionId .
                    self::SQL['condition_oc_order_id'] . $captureOrderId . "' " .
                    self::SQL['limit_1']
            );
    
            $this->db->query(
                self::SQL['update_query_prefix'] . DB_PREFIX . self::SQL['update_order_status_query'] .
                (int)$refundStatusId . self::SQL['where_order_id'] . (int)$newOrderId . "'"
            );
    
            $this->model_extension_mastercard_payment_mastercard->addOrderHistory(
                $newOrderId,
                $refundStatusId,
                $comment,
                "0"
            );
    
            if ($this->config->get('config_mail_engine')) {
                $this->sendCustomEmail(
                    $customerEmail,
                    $newOrderId,
                    $status,
                    $customerName,
                    "Refund"
                );
            }
    
            $this->response->setOutput(json_encode([
                'error' => false,
                'msg' => 'Transaction Partially Refunded'
            ]));
        } elseif (!empty($response['result']) && $response['result'] === 'ERROR') {
            $this->response->setOutput(json_encode([
                'error' => false,
                'msg' => 'Requested amount Exceeds than order amount'
            ]));
        } else {
            $this->response->setOutput(json_encode([
                'error' => true,
                'msg' => self::PATHS['transaction_error']
            ]));
        }
    }
    
    public function save(): void {
        $this->load->language(self::PATHS['mastercard']);
        $json = [];
        if (!$this->user->hasPermission('modify', self::PATHS['mastercard'])) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }
        if (!$json) {
            $this->load->model(self::PATHS['setting_model']);
            $this->model_setting_setting->editSetting('payment_mastercard',$this->request->post);
            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function getLatestGitHubVersion() {
        $owner = 'fingent-corp';
        $repo = self::PATHS['repo_name'];
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
        $releaseNotesLink = self::DOCS['wiki_url'];
        if ($latestVersion !== null && version_compare($latestVersion, $currentVersion, '>')) {
            $message = "A new version ({$latestVersion}) of the module is now available! ";
            $message .= "Please refer to the ";
            $message .= "<a href='{$releaseNotesLink}' target='_blank'>Release Notes</a> ";
            $message .= "section for information about its compatibility and features.";
            return $message;
        }
        
        return null;
    }

    public function void() {
        $this->load->model(self::PATHS['mastercard']);
        try {
            $this->load->language(self::PATHS['mastercard']);
            $this->load->model(self::PATHS['model_sale_order']);
            $comment = $this->language->get('text_void_sucess');
            $merchantId = $this->model_extension_mastercard_payment_mastercard->getMerchantId();
            $voidOrderId = $this->request->post['order_id'];
            $transactionHistory = $this
                ->model_extension_mastercard_payment_mastercard
                ->getTransactions($this->session->data['admin_order_id']);
            
            foreach ($transactionHistory as $transaction) {
                if (
                    $transaction['type'] === 'AUTHORIZED' &&
                    $transaction['oc_order_id'] === $this->request->post['order_id']
                ) {
                    $voidtransactionId = $transaction['void_transaction_id'];
                }
            }
            
            $completedStatusId = $this
                ->model_extension_mastercard_payment_mastercard
                ->getOrderStatusIdByName("Voided");
            
            $newOrderId = $this->extractOrderNumberFromString($voidOrderId);
            $newTxnId = $this->getUniqueTransactionId($voidOrderId);
            
            $url =  $this->getCaptureUri() . self::PATHS['api_version_path']
                . self::PATHS['api_version']
                . self::URI_SEGMENTS['merchant'] . $merchantId
                . self::URI_SEGMENTS['order'] . $voidOrderId
                . self::URI_SEGMENTS['transaction'] . $newTxnId ;
            
           
            $notify = "0";
            $requestData = [
                'apiOperation' => 'VOID',
                'transaction' => [
                    'targetTransactionId' => $voidtransactionId,
                ]
            ];
            
            $response = $this->adminApiRequest('PUT', $url, $requestData);
            $authCode = isset($response['transaction']['authorizationCode']) ? $response['transaction']['authorizationCode'] : '';
            if (!empty($authCode)) {
                $comment = sprintf($this->language->get('text_void_sucess'), $voidtransactionId, $authCode);
            } else {
                $comment = sprintf($this->language->get('text_void_sucess_no_auth'), $voidtransactionId);
            }
            if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
                $status = $response['order']['status'];
                $this->db->query(
                    self::SQL['update_query_prefix'] . DB_PREFIX . "mgps_order_transaction
                    SET status = '" . $status . "',
                    type = 'Void'
                    WHERE void_transaction_id = '" . $voidtransactionId
                    . self::SQL['condition_oc_order_id'] . $voidOrderId . "'
                    " . self::SQL['limit_1']
                );
                $this->db->query( self::SQL['update_query_prefix'] . DB_PREFIX
                    . self::SQL['update_order_status_query'] . (int)$completedStatusId
                    . self::SQL['where_order_id'] . (int)$newOrderId . "'" );
                $this->model_extension_mastercard_payment_mastercard
                    ->addOrderHistory($newOrderId, $completedStatusId, $comment, $notify);
                $json = array(
                    'error' => false,
                    'msg' => 'The transaction has been successfully voided'
                );
                $this->response->setOutput(json_encode($json));
            } else {
                throw new \MasterCardPaymentException(
                    'An error has been occurred during the transaction.'
                );
            }
        } catch (\MasterCardPaymentException $e) {
            $json = array(
                'error' => true,
                'msg' => self::MSG_TRANSACTION_ERROR
            );
            $this->response->setOutput(json_encode($json));
        }
    }
}
