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

/**
 * ModelExtensionPaymentMpgsHostedCheckout
 */
class ModelExtensionPaymentMpgsHostedCheckout extends Model {

    const MODULE_VERSION            = '1.2.2';
    const REPO_NAME                 = 'gateway-opencart-mastercard-module';
    const WIKI_API_URL              = 'https://mpgs.fingent.wiki/wp-json/mpgs/v2/update-repo-status';
    const SETTING_CODE_SHOP_TRACKER = 'mpgs_shop_tracker';
    const TELEMETRY_EVENT_INSTALL   = 'install';
    const TELEMETRY_EVENT_CONFIGURED = 'configured';

    /**
     * Create database tables
     */
    public function createTable() {

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mpgs_hpf_token` (
                `hpf_token_id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
                `customer_id` INT(11) NOT NULL,
                `token` VARCHAR(50) NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`hpf_token_id`),
                KEY `customer_id` (`customer_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `".DB_PREFIX."mgps_order_transaction` (
			  `mgps_order_transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
              `order_id` varchar(255) NOT NULL,
              `oc_order_id` varchar(255) NOT NULL,
			  `transaction_id` varchar(255),
			  `date_added` DATETIME NOT NULL,
			  `type` varchar(255) DEFAULT NULL,
              `merchant_name` varchar(255) DEFAULT NULL,
              `merchant_id` varchar(255) DEFAULT NULL,
			  `status` varchar(255) DEFAULT NULL,
			  `amount` varchar(255) NOT NULL,
              `refunded_amount` varchar(255) DEFAULT NULL,
			  PRIMARY KEY (`mgps_order_transaction_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ");
    }

    /**
     * Add events
     */
    public function addEvents() {

        $this->load->model('setting/event');

        $events = [
            'catalog/controller/checkout/checkout/before' => 'extension/payment/mpgs_hosted_checkout/init',
            'admin/view/sale/order_info/before' => 'extension/payment/mpgs_hosted_checkout/addCustomTab',
        ];

        foreach ($events as $trigger => $action) {
            $this->model_setting_event->addEvent('payment_mpgs_hosted_checkout', $trigger, $action, 1, 0);
        }
    }

    /**
     * Remove events
     */
    public function removeEvents() {

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('payment_mpgs_hosted_checkout');
    }

    /**
     * Seed default order-status mapping when keys are missing (fresh install or legacy shops).
     * Resolves IDs by status name for the admin language; falls back to typical OC3 defaults.
     * Does not overwrite existing saved values.
     */
    public function ensureDefaultOrderStatusSettings(): void {

        $this->load->model('setting/setting');
        $storeId = 0;
        $code    = 'payment_mpgs_hosted_checkout';

        $map = [
            'payment_mpgs_hosted_checkout_approved_status_id' => [
                'names'    => ['Processing'],
                'fallback' => '2',
            ],
            'payment_mpgs_hosted_checkout_pending_status_id' => [
                'names'    => ['Pending'],
                'fallback' => '1',
            ],
            'payment_mpgs_hosted_checkout_declined_status_id' => [
                'names'    => ['Denied', 'Failed', 'Canceled', 'Cancelled'],
                'fallback' => '8',
            ],
            'payment_mpgs_hosted_checkout_refund_status_id' => [
                'names'    => ['Refunded'],
                'fallback' => '11',
            ],
            'payment_mpgs_hosted_checkout_risk_review_status_id' => [
                'names'    => ['Pending'],
                'fallback' => '1',
            ],
            'payment_mpgs_hosted_checkout_risk_declined_status_id' => [
                'names'    => ['Denied', 'Failed', 'Canceled', 'Cancelled'],
                'fallback' => '8',
            ],
        ];

        foreach ($map as $key => $meta) {
            $existing = $this->model_setting_setting->getSettingValue($key, $storeId);
            if ($existing !== null && $existing !== '') {
                continue;
            }

            $resolved = $this->findOrderStatusIdByNames($meta['names']);
            $value    = $resolved !== null ? (string)$resolved : $meta['fallback'];

            $query = $this->db->query(
                "SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$storeId .
                "' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '" .
                $this->db->escape($key) . "' LIMIT 1"
            );

            if ($query->num_rows) {
                $this->model_setting_setting->editSettingValue($code, $key, $value, $storeId);
            } else {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '" . (int)$storeId .
                    "', `code` = '" . $this->db->escape($code) . "', `key` = '" .
                    $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) .
                    "', serialized = '0'"
                );
            }

            $this->config->set($key, $value);
        }
    }

    /**
     * @param array $names Candidate status names (first match wins).
     * @return int|null
     */
    private function findOrderStatusIdByNames(array $names) {

        $languageId = (int)$this->config->get('config_language_id');
        if ($languageId < 1) {
            return null;
        }

        foreach ($names as $name) {
            $query = $this->db->query(
                "SELECT order_status_id FROM `" . DB_PREFIX . "order_status` WHERE language_id = '" .
                (int)$languageId . "' AND LOWER(TRIM(name)) = '" .
                $this->db->escape(strtolower(trim($name))) . "' LIMIT 1"
            );
            if ($query->num_rows) {
                return (int)$query->row['order_status_id'];
            }
        }

        return null;
    }

    /**
     * Get order information
     * @param string $orderId
     * @return array|null
     */
    public function getOrder($orderId) {

        $this->load->model('sale/order');

        // Extract numeric ID from prefixed order ID
        $pattern = '/\d+/';
        if (!preg_match($pattern, $orderId, $matches)) {
            return null;
        }

        $result = $matches[0];
        $orderInfo = $this->model_sale_order->getOrder($result);

        return $orderInfo ?: null;
    }

    /**
     * Get transactions
     * @param string $orderId
     * @return array|null
     */
    public function getTransactions($orderId) {

        $orderId       = (int)$orderId;
        $query          = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mgps_order_transaction`
         WHERE `order_id` = '" . $orderId . "'");
        $transactions   = array();

        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $transactions[] = $row;
            }
        }

        return $transactions;
    }

    /**
     * Get merchant ID
     * @return string|null
     */
    public function getMerchantId() {

        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_merchant_id');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_merchant_id');
        }
    }

    /**
     * Get order status ID by name
     * @param string $statusName
     * @return int|false
     */
    public function getOrderStatusIdByName($statusName) {

        $query = $this->db->query("SELECT order_status_id FROM " . DB_PREFIX . "order_status
         WHERE name = '" . $this->db->escape($statusName) . "'");
        if ($query->num_rows) {
            return $query->row['order_status_id'];
        } else {
            return false;
        }
    }

    /**
     * Add order history
     * @param string $orderId
     * @param int $orderStatusId
     * @param string $comment
     * @param bool $notify
     */
    public function addOrderHistory($orderId, $orderStatusId, $comment = '', $notify = false) {

        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET order_id = '" .
         (int)$orderId . "', order_status_id = '" . (int)$orderStatusId . "', notify = '" .
          (int)$notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
    }

    /**
     * Check if test mode is enabled
     * @return bool
     */
    public function isTestModeEnabled() {

        return $this->config->get('payment_mpgs_hosted_checkout_test');
    }

    /**
     * @return bool
     */
    public function needsInstallTelemetry(): bool {

        return $this->config->get('mpgs_shop_install_version') !== self::MODULE_VERSION;
    }

    /**
     * @return bool
     */
    public function needsConfiguredTelemetry(): bool {

        if (!$this->hasSavedLiveCredentials()) {
            return false;
        }

        if (empty($this->config->get('mpgs_shop_config_notified'))) {
            return true;
        }

        return $this->config->get('mpgs_shop_config_version') !== self::MODULE_VERSION;
    }

    /**
     * @return bool
     */
    public function hasSavedLiveCredentials(): bool {

        if (!empty($this->config->get('payment_mpgs_hosted_checkout_test'))) {
            return false;
        }

        $merchantId  = trim((string)$this->config->get('payment_mpgs_hosted_checkout_live_merchant_id'));
        $apiPassword = trim((string)$this->config->get('payment_mpgs_hosted_checkout_live_api_password'));

        return $merchantId !== '' && $apiPassword !== '';
    }

    /**
     * @param string $sessionId
     * @return string
     */
    public function buildInstallHash(string $sessionId): string {

        $context = $this->getShopTelemetryContext();

        return hash('sha256', $sessionId . $context['shop_name'] . $context['shop_url']);
    }

    /**
     * @param string $installHash
     * @return bool
     */
    public function sendInstallTelemetryAndPersistVersion(string $installHash): bool {

        $result = $this->sendInstallTelemetryRequest($installHash);

        if (!empty($result['success'])) {
            $this->persistShopTrackerValue('mpgs_shop_install_hash', $installHash);
            $this->persistShopTrackerValue('mpgs_shop_install_version', self::MODULE_VERSION);

            return true;
        }

        return false;
    }

    /**
     * @param string $installHash
     * @param string $sessionIdForRegen
     * @return void
     */
    public function sendConfiguredTelemetry(string $installHash, string $sessionIdForRegen = ''): void {

        $result = $this->attemptConfiguredTelemetry($installHash);

        if (!empty($result['success'])) {
            return;
        }

        if (!$this->shouldRegenerateInstallHash($result) || $sessionIdForRegen === '') {
            return;
        }

        $newHash = $this->buildInstallHash($sessionIdForRegen);

        if (!$this->sendInstallTelemetryAndPersistVersion($newHash)) {
            return;
        }

        $this->attemptConfiguredTelemetry($newHash);
    }

    /**
     * @param array $result
     * @return bool
     */
    public function shouldRegenerateInstallHash(array $result): bool {

        if (!empty($result['http_code']) && (int)$result['http_code'] === 401) {
            return true;
        }

        $message = (string)($result['message'] ?? '');

        if ($message === '') {
            return false;
        }

        return strpos($message, 'Shop install record not found') !== false
            || strpos($message, 'Unauthorized configured bearer token') !== false;
    }

    /**
     * @param string $installHash
     * @return array
     */
    private function sendInstallTelemetryRequest(string $installHash): array {

        return $this->sendTelemetryRequest(
            $this->buildInstallTelemetryPayload(),
            $installHash
        );
    }

    /**
     * @param string $installHash
     * @return array
     */
    private function attemptConfiguredTelemetry(string $installHash): array {

        $result = $this->sendTelemetryRequest(
            $this->buildConfiguredTelemetryPayload(),
            $installHash
        );

        if (!empty($result['success'])) {
            $this->persistShopTrackerValue('mpgs_shop_config_notified', '1');
            $this->persistShopTrackerValue('mpgs_shop_config_version', self::MODULE_VERSION);
        }

        return $result;
    }

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function persistShopTrackerValue(string $key, string $value): void {

        $this->load->model('setting/setting');

        $storeId = 0;
        $query   = $this->db->query(
            "SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$storeId .
            "' AND `code` = '" . $this->db->escape(self::SETTING_CODE_SHOP_TRACKER) . "' AND `key` = '" .
            $this->db->escape($key) . "' LIMIT 1"
        );

        if ($query->num_rows) {
            $this->model_setting_setting->editSettingValue(
                self::SETTING_CODE_SHOP_TRACKER,
                $key,
                $value,
                $storeId
            );
        } else {
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '" . (int)$storeId .
                "', `code` = '" . $this->db->escape(self::SETTING_CODE_SHOP_TRACKER) . "', `key` = '" .
                $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) .
                "', serialized = '0'"
            );
        }

        $this->config->set($key, $value);
    }

    /**
     * @return array{shop_name: string, shop_url: string, country_code: string, country: string}
     */
    private function getShopTelemetryContext(): array {

        $this->load->model('localisation/country');
        $countryId   = (int)$this->config->get('config_country_id');
        $countryInfo = $this->model_localisation_country->getCountry($countryId);

        return [
            'shop_name'    => (string)$this->config->get('config_name'),
            'shop_url'     => $this->normalizeShopUrl($this->getCatalogUrl()),
            'country_code' => $countryInfo['iso_code_2'] ?? '',
            'country'      => $countryInfo['name'] ?? '',
        ];
    }

    /**
     * @return string
     */
    private function getCatalogUrl(): string {

        if (defined('HTTP_CATALOG')) {
            return HTTP_CATALOG;
        }

        if ($this->config->get('config_secure') && defined('HTTPS_SERVER')) {
            return HTTPS_SERVER;
        }

        return defined('HTTP_SERVER') ? HTTP_SERVER : '';
    }

    /**
     * @param string|null $url
     * @return string
     */
    private function normalizeShopUrl($url): string {

        return rtrim(trim((string)$url), '/');
    }

    /**
     * @return string
     */
    private function buildInstallTelemetryPayload(): string {

        $context = $this->getShopTelemetryContext();

        return json_encode([
            'event'     => self::TELEMETRY_EVENT_INSTALL,
            'shop_name' => $context['shop_name'],
            'shop_url'  => $context['shop_url'],
        ]);
    }

    /**
     * @return string
     */
    private function buildConfiguredTelemetryPayload(): string {

        $context = $this->getShopTelemetryContext();

        return json_encode([
            'event'          => self::TELEMETRY_EVENT_CONFIGURED,
            'repo_name'      => self::REPO_NAME,
            'plugin_type'    => 'enterprise',
            'tag_name'       => self::MODULE_VERSION,
            'latest_release' => '1',
            'country_code'   => $context['country_code'],
            'country'        => $context['country'],
            'shop_name'      => $context['shop_name'],
            'shop_url'       => $context['shop_url'],
        ]);
    }

    /**
     * @param string|null $response
     * @return string
     */
    private function parseTelemetryResponseMessage($response): string {

        if (!is_string($response) || $response === '') {
            return '';
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || empty($decoded['message'])) {
            return '';
        }

        return (string)$decoded['message'];
    }

    /**
     * @param string $payload
     * @param string $bearerToken
     * @return array
     */
    private function sendTelemetryRequest(string $payload, string $bearerToken): array {

        $ch = curl_init(self::WIKI_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $bearerToken,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            curl_close($ch);

            return [
                'success'   => false,
                'http_code' => 0,
                'message'   => '',
            ];
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            return [
                'success'   => false,
                'http_code' => $httpCode,
                'message'   => $this->parseTelemetryResponseMessage($response),
            ];
        }

        return [
            'success'   => true,
            'http_code' => $httpCode,
            'message'   => $this->parseTelemetryResponseMessage($response),
            'response'  => $response,
        ];
    }
}
