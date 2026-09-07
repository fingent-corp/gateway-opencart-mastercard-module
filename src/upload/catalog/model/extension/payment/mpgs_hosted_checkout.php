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

    const API_AMERICA           = 'api_na';
    const API_EUROPE            = 'api_eu';
    const API_ASIA              = 'api_ap';
    const API_MTF               = 'api_mtf';
    const API_OTHER             = 'api_other';
    const MODULE_VERSION        = '1.2.2';
    const API_VERSION           = '100';
    const DEBUG_LOG_FILENAME    = 'mpgs_gateway.log';
    const THREEDS_API_VERSION   = '1.3.0';
    const REPO_NAME             = 'gateway-opencart-mastercard-module';
    const WIKI_API_URL          = 'https://mpgs.fingent.wiki/wp-json/mpgs/v2/update-repo-status';
    const SETTING_CODE_SHOP_TRACKER     = 'mpgs_shop_tracker';
    const TELEMETRY_EVENT_INSTALL       = 'install';
    const TELEMETRY_EVENT_CONFIGURED    = 'configured';
    const ORDERED_GOODS         = 'Ordered Goods';
    const ORDERED_GOODS_LOCAL   = 'Bestilte varer';

    /**
     * @return array|null
     */
    public function getMethod() {

        $this->maybeSendCheckoutTelemetry();

        return [
            'code'       => 'mpgs_hosted_checkout',
            'title'      => $this->config->get('payment_mpgs_hosted_checkout_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_mpgs_hosted_checkout_sort_order')
        ];
    }

    /**
     * Checkout fallback when install/configured telemetry was never sent or the
     * module version changed. Backfills hash from customer session only when
     * install version is stale; never regenerates hash on configured failure.
     *
     * @return void
     */
    private function maybeSendCheckoutTelemetry(): void {

        if (empty($this->config->get('payment_mpgs_hosted_checkout_status'))) {
            return;
        }

        if (!empty($this->session->data['mpgs_telemetry_attempted'])) {
            return;
        }

        $this->session->data['mpgs_telemetry_attempted'] = true;

        $installHash = (string)$this->config->get('mpgs_shop_install_hash');

        if ($installHash === '') {
            if (!$this->needsInstallTelemetry()) {
                return;
            }

            $sessionId = $this->getSessionId();
            if ($sessionId === '') {
                return;
            }

            $installHash = $this->buildInstallHash($sessionId);

            if (!$this->sendInstallTelemetryAndPersistVersion($installHash)) {
                return;
            }

            $installHash = (string)$this->config->get('mpgs_shop_install_hash');
            if ($installHash === '') {
                return;
            }
        } elseif ($this->needsInstallTelemetry()) {
            $this->sendInstallTelemetryAndPersistVersion($installHash);
        }

        if ($this->needsConfiguredTelemetry()) {
            $this->sendConfiguredTelemetry($installHash);
        }
    }

    /**
     * @return string
     */
    private function getSessionId(): string {

        $sessionName = $this->config->get('session_name') ?: 'OCSESSID';

        if (!empty($this->request->cookie[$sessionName])) {
            return (string)$this->request->cookie[$sessionName];
        }

        return (string)$this->session->getId();
    }

    /**
     * @param string $sessionId
     * @return string
     */
    private function buildInstallHash(string $sessionId): string {

        $context = $this->getShopTelemetryContext();

        return hash('sha256', $sessionId . $context['shop_name'] . $context['shop_url']);
    }

    /**
     * @return bool
     */
    private function needsInstallTelemetry(): bool {

        return $this->config->get('mpgs_shop_install_version') !== self::MODULE_VERSION;
    }

    /**
     * @return bool
     */
    private function needsConfiguredTelemetry(): bool {

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
    private function hasSavedLiveCredentials(): bool {

        if (!empty($this->config->get('payment_mpgs_hosted_checkout_test'))) {
            return false;
        }

        $merchantId  = trim((string)$this->config->get('payment_mpgs_hosted_checkout_live_merchant_id'));
        $apiPassword = trim((string)$this->config->get('payment_mpgs_hosted_checkout_live_api_password'));

        return $merchantId !== '' && $apiPassword !== '';
    }

    /**
     * @param string $installHash
     * @return void
     */
    private function sendConfiguredTelemetry(string $installHash): void {

        $this->attemptConfiguredTelemetry($installHash);
    }

    /**
     * @param string $installHash
     * @return bool
     */
    private function attemptConfiguredTelemetry(string $installHash): bool {

        $result = $this->sendTelemetryRequest(
            $this->buildConfiguredTelemetryPayload(),
            $installHash
        );

        if (!empty($result['success'])) {
            $this->persistShopTrackerValue('mpgs_shop_config_notified', '1');
            $this->persistShopTrackerValue('mpgs_shop_config_version', self::MODULE_VERSION);

            return true;
        }

        return false;
    }

    /**
     * @param string $installHash
     * @return bool
     */
    private function sendInstallTelemetryAndPersistVersion(string $installHash): bool {

        $result = $this->sendTelemetryRequest(
            $this->buildInstallTelemetryPayload(),
            $installHash
        );

        if (!empty($result['success'])) {
            $this->persistShopTrackerValue('mpgs_shop_install_hash', $installHash);
            $this->persistShopTrackerValue('mpgs_shop_install_version', self::MODULE_VERSION);

            return true;
        }

        return false;
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
     * @param string $key
     * @param string $value
     * @return void
     */
    private function persistShopTrackerValue(string $key, string $value): void {

        $storeId = 0;
        $query   = $this->db->query(
            "SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$storeId .
            "' AND `code` = '" . $this->db->escape(self::SETTING_CODE_SHOP_TRACKER) . "' AND `key` = '" .
            $this->db->escape($key) . "' LIMIT 1"
        );

        if ($query->num_rows) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape($value) .
                "', serialized = '0' WHERE setting_id = '" . (int)$query->row['setting_id'] . "'"
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

    /**
     * @return string|null
     */
    public function getGatewayUri() {

        $apiGateway     = $this->config->get('payment_mpgs_hosted_checkout_api_gateway');
        
        if ($apiGateway === self::API_AMERICA) {
            $gatewayUrl = 'https://na-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_EUROPE) {
            $gatewayUrl = 'https://eu-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_ASIA) {
            $gatewayUrl = 'https://ap-gateway.mastercard.com/';
        } elseif ($apiGateway === self::API_MTF) {
            $gatewayUrl = 'https://mtf.gateway.mastercard.com/';
        } else {
            $url = $this->config->get('payment_mpgs_hosted_checkout_api_gateway_other');
            if (!empty($url) && substr($url, -1) !== '/') {
                $url .= '/';
            }
            $gatewayUrl = $url;
        }

        return $gatewayUrl;
    }

    /**
     * @return string|null
     */
    public function getApiUri() {

        return $this->getGatewayUri() . 'api/rest/version/' . $this->getApiVersion() .
         '/merchant/' . $this->getMerchantId();
    }

    /**
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
     * @return string|null
     */
    public function getApiPassword() {

        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_api_password');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_api_password');
        }
    }

    /**
     * @return string|null
     */
    public function getWebhookSecret() {

        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mpgs_hosted_checkout_test_notification_secret');
        } else {
            return $this->config->get('payment_mpgs_hosted_checkout_live_notification_secret');
        }
    }

    /**
     * @return string|null
     */
    public function getApiVersion() {

        return self::API_VERSION;
    }

    /**
     * @return bool
     */
    public function isTestModeEnabled() {

        return $this->config->get('payment_mpgs_hosted_checkout_test');
    }

    /**
     * @return boolean
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
     * @return string|null
     */
    public function getPaymentAction() {

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
    public function buildPartnerSolutionId() {

        return 'OC_' . VERSION . '_FINGENT_' . self::MODULE_VERSION;
    }

    /**
     * @param string    $method
     * @param string    $uri
     * @param array $data
     * @return array|null
     */
    public function apiRequest($method, $uri, $data = []) {

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

        $responseText = 'Receive Response: "' . $httpResponseCode . '" for the request: "' .
         $method . ' ' . $uri . '" ';
        $responseText .= json_encode(['response' => json_decode($output)]);
        $this->log($responseText);

        return json_decode($output, true);
    }

    /**
     * Clear data from session
     */
    public function clearCheckoutSession() {

        unset($this->session->data['mpgs_hosted_checkout']);
        unset($this->session->data['mpgs_hosted_session']);
        unset($this->session->data['mpgs_hosted_checkout']['successIndicator']);
    }

    /**
     * @param int $customerId
     * @return array|null
     */
    public function getCustomerTokens($customerId) {

        $tokensResult = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mpgs_hpf_token`
         WHERE customer_id='" . (int)$customerId . "'");
        return $tokensResult->rows;
    }

    /**
     * @return boolean
     */
    public function isSavedCardsEnabled() {

        return $this->config->get('payment_mpgs_hosted_checkout_saved_cards');
    }

    /**
     * @param string $message
     */
    public function log($message) {

        if ($this->isDebugModeEnabled()) {
            $this->debugLog = new Log(self::DEBUG_LOG_FILENAME);
            $this->debugLog->write($message);
        }
    }

    /**
     * @return array
     */
    public function getOrderSummaryText() {

        return self::ORDERED_GOODS;
        
	}
}
