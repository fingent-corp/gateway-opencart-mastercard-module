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
use Mpgs\Payment\MpgsCatalogHelperTrait;

// Registers a class-map autoloader for the Mpgs\Payment namespace.
// OpenCart 3 has no PSR-4 autoloader for extension files; this
// one-time registration replaces top-level require_once calls and
// lets PHP resolve the trait and exception via the use imports above.
spl_autoload_register(static function (string $class): void {
    $map = [
        'Mpgs\\Payment\\MpgsPaymentException'   => __DIR__ . '/mpgs_payment_exception.php',
        'Mpgs\\Payment\\MpgsCatalogHelperTrait' => __DIR__ . '/mpgs_catalog_helper.php',
    ];
    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

class ControllerExtensionPaymentMpgsHostedCheckout extends Controller {

    use MpgsCatalogHelperTrait;

    const ORDER_CAPTURED            = '15';
    const ORDER_VOIDED              = '16';
    const ORDER_CANCELLED           = '7';
    const ORDER_REFUNDED            = '11';
    const ORDER_FAILED              = '10';
    const HEADER_WEBHOOK_SECRET     = 'HTTP_X_NOTIFICATION_SECRET';
    const HEADER_WEBHOOK_ATTEMPT    = 'HTTP_X_NOTIFICATION_ATTEMPT';
    const HEADER_WEBHOOK_ID         = 'HTTP_X_NOTIFICATION_ID';
    const PATH_EXTENSION            = 'extension/payment/mpgs_hosted_checkout';
    const PATH_CHECKOUT_ORDER       = 'checkout/order';
    const HTTP_500                  = 'HTTP/1.1 500 ';
    const PATH_ORDER                = '/order/';

    protected $orderAmount = 0;

    /**
     * @return mixed
     */
    public function index() {

        $this->load->language(self::PATH_EXTENSION);
        $this->load->model(self::PATH_EXTENSION);

        $data       = [];
        $gatewayUri = $this->model_extension_payment_mpgs_hosted_checkout->getGatewayUri();

        if (!empty($this->session->data['order_id']) &&
            !empty($this->session->data['currency']) &&
            !empty($this->session->data['payment_address'])) {
            try {
                unset($this->session->data['HostedCheckout_sessionId']);
                $built = $this->hasInitiatedCheckoutSession();
                if ($built === true) {
                    $data['configured_variables'] = json_encode($this->configureHostedCheckout());
                }
            } catch (Exception $e) {
                $data['error_session'] = $e->getMessage();
            }
        }

        if (!isset($data['error_session']) || empty($data['error_session'])) {
            $this->buildCallbackUrls($data, $gatewayUri);
        }

        if (isset($data['configured_variables'])) {
            $checkoutSessionId = json_decode($data['configured_variables']);
            if ($checkoutSessionId && isset($checkoutSessionId->session, $checkoutSessionId->merchant)) {
                $this->applyCheckoutSessionData($data, $checkoutSessionId);
                return $this->load->view('extension/payment/mpgs_hosted_checkout_template', $data);
            }
        }
    }

    /**
     * Populate callback URL data entries for the view.
     *
     * @param array  $data
     * @param string $gatewayUri
     */
    private function buildCallbackUrls(array &$data, $gatewayUri) {

        $cacheBust                    = (int)round(microtime(true));
        $data['hosted_checkout_js']   = $gatewayUri . 'static/checkout/checkout.min.js?_=' . $cacheBust;
        $data['checkout_interaction'] = $this->config->get('payment_mpgs_hosted_checkout_hc_type');
        $data['completeCallback']     = $this->url->link(
            'extension/payment/mpgs_hosted_checkout/processHostedCheckout',
            '',
            true
        );
        $data['cancelCallback']       = $this->url->link(
            'extension/payment/mpgs_hosted_checkout/cancelCallback',
            '',
            true
        );
        $data['errorCallback']        = $this->url->link(
            'extension/payment/mpgs_hosted_checkout/errorCallback',
            '',
            true
        );
    }

    /**
     * Populate view data from a decoded checkout session object.
     *
     * @param array    $data
     * @param stdClass $checkoutSessionId
     */
    private function applyCheckoutSessionData(array &$data, $checkoutSessionId) {

        $data['session_id']    = $checkoutSessionId->session->id;
        $data['merchant']      = $checkoutSessionId->merchant;
        $data['version']       = $checkoutSessionId->session->version ?? null;
        $data['mgps_order_id'] = $this->getOrderPrefix($this->session->data['order_id']);
        $data['order_id']      = $this->session->data['order_id'];

        if (isset($this->session->data['mpgs_hosted_checkout']['successIndicator'])) {
            $this->storeSuccessIndicator($data);
        }

        $data['OCSESSID']  = $this->request->cookie['OCSESSID'];
        $data['jsonData']  = json_encode($data);

        $isSecure = isset($this->request->server['HTTPS']) && $this->request->server['HTTPS'] === 'on';
        setcookie('OCSESSID', $data['OCSESSID'], [
            'expires'  => time() + (24 * 3600),
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Persist the hosted-checkout success indicator to the settings table.
     *
     * @param array $data
     */
    private function storeSuccessIndicator(array &$data) {

        $data['success_indicator'] = $this->session->data['mpgs_hosted_checkout']['successIndicator'];
        $key   = 'mpgs_success_indicator_' . (int)$data['order_id'];
        $value = $key . $data['success_indicator'];

        if (!empty($key)) {
            $this->db->query("REPLACE INTO `" . DB_PREFIX . "setting` SET
                `store_id` = '0',
                `code` = 'mpgs',
                `key` = '"  . $this->db->escape($key)   . "',
                `value` = '" . $this->db->escape($value) . "',
                `serialized` = 0");
        }
    }

    /**
     * @param $route
     */
    public function init($route) {

        $allowed = ['checkout/checkout3'];

        if (!in_array($route, $allowed)) {
            return;
        }

        $this->load->model(self::PATH_EXTENSION);
    }

    /**
     * @return bool
     * @throws MpgsPaymentException
     */
    public function hasInitiatedCheckoutSession() {
        $this->load->model(self::PATH_EXTENSION);
        $this->model_extension_payment_mpgs_hosted_checkout->clearCheckoutSession();

        $order = $this->getOrder();
        $txnId = uniqid(sprintf('%s-', $order['id']));

        $requestData = [
            'apiOperation'      => 'INITIATE_CHECKOUT',
            'partnerSolutionId' => $this->model_extension_payment_mpgs_hosted_checkout->buildPartnerSolutionId(),
            'order'             => array_merge($this->getOrder(), $this->getOrderItemsTaxAndTotals()),
            'interaction'       => $this->getInteraction(),
            'billing'           => $this->getBillingAddress(),
            'customer'          => $this->getCustomer(),
            'transaction'       => ['reference' => $txnId]
        ];

        $requestData['order']['notificationUrl'] = $this->url->link(
            'extension/mastercard/payment/mastercard/callback',
            '',
            true
        );

        if (!empty($this->getShippingAddress())) {
            $requestData['shipping'] = $this->getShippingAddress();
        }

        unset($this->session->data['HostedCheckout_sessionId']);

        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/session';
        $response = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('POST', $uri, $requestData);

        return $this->handleSessionResponse($response);
    }

    /**
     * Helper to reduce cognitive complexity and handle response logic
     */
    private function handleSessionResponse($response) {
        if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
            $this->session->data['mpgs_hosted_checkout'] = $response;
            $this->session->data['mgps_redirect_session'] = $response;
            return true;
        }

        if (!empty($response['result']) && $response['result'] === 'ERROR') {
            throw new MpgsPaymentException(json_encode($response['error']));
        }

        return false;
    }

    /**
     * @return mixed
     */
    protected function getInteraction() {

        $this->load->model(self::PATH_EXTENSION);

        $name = $this->config->get('payment_mpgs_hosted_checkout_merchant_name')
            ?: $this->config->get('config_name');

        $name = (strlen($name) > 40) ? substr($name, 0, 40) : $name;

        $operation = $this->model_extension_payment_mpgs_hosted_checkout->getPaymentAction();
        $returnUrl = $this->url->link(
            'extension/payment/mpgs_hosted_checkout/processHostedCheckout',
            '',
            true
        );

        $merchant = [
            'name' => $name
        ];

        if ((int)$this->config->get('payment_mpgs_hosted_checkout_merchant_info') === 1) {

            $merchantLogoUrl = $this->getMerchantLogoUrl();
            if ($merchantLogoUrl) {
                $merchant['logo'] = $merchantLogoUrl;
            }

            $address = $this->getMerchantAddress();
            if (!empty($address)) {
                $merchant['address'] = $address;
            }

            $this->addContactInfo($merchant);
        }

        return [
            'merchant'            => $merchant,
            'operation'           => $operation,
            'returnUrl'           => $returnUrl,
            'redirectMerchantUrl' => $returnUrl,
            'displayControl'      => [
                'shipping'       => 'HIDE',
                'billingAddress' => 'HIDE',
                'customerEmail'  => 'HIDE'
            ],
        ];
    }

    /**
     * @return mixed
     */
    protected function getOrder() {

        $orderId         = $this->getOrderPrefix($this->session->data['order_id']);
        $currency        = $this->session->data['currency'];
        $notificationUrl = $this->url->link(
            'extension/payment/mpgs_hosted_checkout/callback',
            '',
            true
        );
        $description     = $this->model_extension_payment_mpgs_hosted_checkout->getOrderSummaryText();

        return [
            'id'             => $orderId,
            'reference'      => $orderId,
            'currency'       => $currency,
            'notificationUrl'=> $notificationUrl,
            'description'    => $description
        ];
    }

    /**
     * Process Hosted Checkout Payment Method
    */
    public function processHostedCheckout() {

        $this->load->language(self::PATH_EXTENSION);
        $this->load->model(self::PATH_EXTENSION);

        $orderId            = $this->getOrderPrefix($this->session->data['order_id']);
        $key                = 'mpgs_success_indicator_' . (int)$this->session->data['order_id'];
        $requestIndicator   = $key . ($this->request->get['resultIndicator'] ?? '');
        $query              = $this->db->query("SELECT `value` FROM `" . DB_PREFIX .
            "setting` WHERE `key` = '" . $key . "' AND `code` = 'mpgs'");
        $storedIndicator    = $query->num_rows ? $query->row['value'] : null;

        // Retrieve order and transactions
        $retrievedOrder     = $this->retrieveOrder($orderId);
        $txns               = $retrievedOrder['transaction'] ?? [];
        $latestTxn          = end($txns);
        $authTxnId          = $latestTxn['authentication']['transactionId'] ?? null;
        $resultStatus       = strtoupper($latestTxn['result'] ?? '');

        try {
            $txn = $retrievedOrder['transaction'][0] ?? null;
            if ($txn === null || !isset($txn['transaction']['id'])) {
                throw new MpgsPaymentException($this->language->get('error_transaction_unsuccessful'));
            }

            $transactionId      = $txn['transaction']['id'];
            $transactionAmount  = $txn['transaction']['amount'];
            $transactionStatus  = $txn['order']['status'];
            $transactionOrderID = $txn['order']['id'];
            $merchantName       = $txn['merchant'];
            $merchantId         = $txn['transaction']['acquirer']['merchantId'];
            $customerEmail      = $txn['customer']['email'];
            $firstName          = $txn['customer']['firstName'] ?? $retrievedOrder['shipping']['contact']['firstName'] ?? '';
            $lastName           = $txn['customer']['lastName'] ?? $retrievedOrder['shipping']['contact']['lastName'] ?? '';
            $customerName       = trim($firstName . ' ' . $lastName);

            $isBrowserPayment = isset($latestTxn['browserPayment']);

            // --- Browser payment check ---
            if ($isBrowserPayment) {
                if ($resultStatus !== 'SUCCESS') {
                    throw new MpgsPaymentException($this->language->get('error_payment_declined'));
                }
            } else {
                // --- Non-browser payment check ---
                if (strtoupper($retrievedOrder['result'] ?? '') !== 'SUCCESS') {
                    throw new MpgsPaymentException($this->language->get('error_payment_declined'));
                }

                // Additional indicator check
                $this->assertSuccessIndicatorOrRetrieveTransaction(
                    $orderId,
                    $authTxnId,
                    $storedIndicator,
                    $requestIndicator
                );
            }

            // --- Normal flow (safe to process order) ---
            $this->processOrder($retrievedOrder, $txn);

            // Save transaction
            $this->db->query("INSERT INTO " . DB_PREFIX . "mgps_order_transaction
                SET order_id       = '" . (int)$this->session->data['order_id'] . "',
                    oc_order_id    = '" . $this->db->escape($transactionOrderID) . "',
                    transaction_id = '" . $this->db->escape($transactionId) . "',
                    type           = '" . $this->db->escape($transactionStatus) . "',
                    merchant_name  = '" . $this->db->escape($merchantName) . "',
                    merchant_id    = '" . $this->db->escape($merchantId) . "',
                    status         = '" . $this->db->escape($transactionStatus) . "',
                    amount         = '" . (float)$transactionAmount . "',
                    date_added     = NOW()");

            // Send email if enabled — isolated so a mail failure never rolls back a captured payment
            if ($this->config->get('config_mail_engine')) {
                $this->sendOrderEmailSafely($orderId, $customerEmail, $transactionStatus, $customerName);
            }

            // Clear cart and session
            $this->cart->clear();
            $this->clearTokenSaveCardSessionData();
            $this->model_extension_payment_mpgs_hosted_checkout->clearCheckoutSession();

            // Redirect to success page
            $this->response->redirect($this->url->link('checkout/success', '', true));

        } catch (Exception $e) {
            // On failure, log error, mark order failed, clear the order from session so
            // the next checkout attempt always creates a fresh order rather than reusing
            // an order that may have already been captured by the gateway.
            $this->session->data['error'] = $e->getMessage();
            $this->addOrderHistory($orderId, self::ORDER_FAILED, $e->getMessage());
            unset($this->session->data['order_id']);
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    /**
     * When the stored success indicator does not match the return URL, verify via GET transaction.
     *
     * @param string      $orderId
     * @param string|null $authTxnId
     * @param mixed       $storedIndicator
     * @param string      $requestIndicator
     * @throws MpgsPaymentException
     */
    private function assertSuccessIndicatorOrRetrieveTransaction(
        $orderId,
        $authTxnId,
        $storedIndicator,
        $requestIndicator
    ): void {
        if (!$storedIndicator || (string)$storedIndicator !== (string)$requestIndicator) {
            $response = $this->retrieveTransaction($orderId, $authTxnId);

            if (!isset($response['result']) || strtoupper($response['result']) !== 'SUCCESS') {
                throw new MpgsPaymentException($this->language->get('error_indicator_mismatch'));
            }
        }
    }

    /**
     * Read and JSON-decode the raw webhook request body.
     * Returns the decoded array, or null on parse failure (after logging + HTTP 500).
     *
     * @return array|null
     */
    protected function parseWebhookBody() {

        $content    = trim(file_get_contents('php://input'));
        $parsedData = json_decode($content, true);
        $jsonError  = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            $this->model_extension_payment_mpgs_hosted_checkout->log(
                'Could not parse response JSON, error: ' . $jsonError,
                json_encode(['rawContent' => $content])
            );
            header(self::HTTP_500 . $jsonError);
            return null;
        }

        return $parsedData;
    }

    /**
     * Validate all required webhook prerequisites.
     * Throws MpgsPaymentException on the first failed check.
     *
     * @param array $requestHeaders
     * @param array $parsedData
     * @throws MpgsPaymentException
     */
    protected function validateWebhook(array $requestHeaders, array $parsedData) {

        $webhookSecret = $requestHeaders[self::HEADER_WEBHOOK_SECRET];

        if ($requestHeaders['REQUEST_METHOD'] != 'POST') {
            throw new MpgsPaymentException($this->language->get('error_request_method'));
        }
        if (!$this->isSecure($requestHeaders)) {
            throw new MpgsPaymentException($this->language->get('error_insecure_connection'));
        }
        if ($this->model_extension_payment_mpgs_hosted_checkout->getWebhookSecret() !== $webhookSecret) {
            throw new MpgsPaymentException($this->language->get('error_secret_mismatch'));
        }
        if ($this->model_extension_payment_mpgs_hosted_checkout->getMerchantId() !== $parsedData['merchant']) {
            throw new MpgsPaymentException($this->language->get('error_merchant_mismatch'));
        }
        if (!isset($parsedData['order']) || !isset($parsedData['order']['id'])) {
            throw new MpgsPaymentException($this->language->get('error_invalid_order'));
        }
        if (!isset($parsedData['transaction']) || !isset($parsedData['transaction']['id'])) {
            throw new MpgsPaymentException($this->language->get('error_invalid_transaction'));
        }
    }

    /**
     * Write a structured webhook log entry.
     *
     * @param array $requestHeaders
     * @param array $parsedData
     */
    protected function logWebhookDetails(array $requestHeaders, array $parsedData) {

        $this->model_extension_payment_mpgs_hosted_checkout->log(
            'Webhook Response: ' . json_encode([
                'notification_id'      => $requestHeaders[self::HEADER_WEBHOOK_ID],
                'notification_attempt' => $requestHeaders[self::HEADER_WEBHOOK_ATTEMPT],
                'order.id'             => $parsedData['order']['id'],
                'transaction.id'       => $parsedData['transaction']['id'],
                'transaction.type'     => $parsedData['transaction']['type'],
                'response.gatewayCode' => $parsedData['response']['gatewayCode'],
            ])
        );
    }

    /**
     * Retrieve the gateway transaction and throw if the gateway reports an error.
     *
     * @param array $parsedData
     * @return array
     * @throws MpgsPaymentException
     */
    protected function verifyWebhookTransaction(array $parsedData) {

        $response = $this->retrieveTransaction(
            $parsedData['order']['id'],
            $parsedData['transaction']['id']
        );

        if (isset($response['result']) && $response['result'] == 'ERROR') {
            $error = $this->language->get('error_payment_declined');
            if (isset($response['error']['explanation'])) {
                $error = sprintf(
                    '%s: %s',
                    $response['error']['cause'],
                    $response['error']['explanation']
                );
            }
            throw new MpgsPaymentException($error);
        }

        return $response;
    }

    /**
     * Strip the configured order-ID prefix from the gateway order ID.
     *
     * @param array $response
     * @return string
     */
    protected function resolveOrderIdFromPrefix(array $response) {

        $mpgsOrderId = $response['order']['id'];
        $prefix      = trim($this->config->get('payment_mpgs_hosted_checkout_order_id_prefix'));

        if ($prefix) {
            $mpgsOrderId = substr($mpgsOrderId, strlen($prefix));
        }

        return $mpgsOrderId;
    }

    /**
     * Process the risk-review section of a webhook response.
     *
     * @param array $order
     * @param array $response
     */
    protected function handleWebhookRiskResponse(array $order, array $response) {
        $risk = $response['risk']['response'];

        switch ($risk['gatewayCode']) {
            case 'REJECTED':
                $this->handleRiskRejected($order, $response);
                break;
            case 'REVIEW_REQUIRED':
                $this->processRiskReview($order, $response);
                break;
            default:
                $this->setOrderHistoryTransactionType($order, $response);
                break;
        }
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function processRiskReview(array $order, array $response) {
        $risk = $response['risk']['response'];
        $decision = $risk['review']['decision'] ?? '';

        if (in_array($decision, ['NOT_REQUIRED', 'ACCEPTED'], true)) {
            $this->setOrderHistoryTransactionType($order, $response);
            return;
        }

        $statusId = $this->config->get('payment_mpgs_hosted_checkout_risk_review_status_id');
        $this->updateRiskHistory($response, $statusId, 'text_risk_review_required');
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function handleRiskRejected(array $order, array $response) {
        $statusId = $this->config->get('payment_mpgs_hosted_checkout_risk_declined_status_id');
        if ($order['order_status_id'] != $statusId) {
            $this->updateRiskHistory($response, $statusId, 'text_risk_review_rejected');
        }
    }

    /**
     * Helper to handle the redundant history logging logic
     *
     * @param array $response
     * @param int $statusId
     * @param string $langKey
     */
    private function updateRiskHistory(array $response, $statusId, $langKey) {
        $message = sprintf(
            $this->language->get($langKey),
            $response['risk']['response']['gatewayCode'],
            $response['transaction']['id'],
            $response['transaction']['type']
        );
        $this->addOrderHistory($response['order']['reference'], $statusId, $message);
    }

    /**
     * Dispatch to the appropriate per-type order-history handler.
     *
     * @param array $order
     * @param array $response
     */
    protected function setOrderHistoryTransactionType($order, $response) {

        switch ($response['transaction']['type']) {
            case 'AUTHORIZATION':
            case 'AUTHORIZATION_UPDATE':
                $this->handleAuthorizationWebhook($order, $response);
                break;

            case 'PAYMENT':
            case 'CAPTURE':
                $this->handleCaptureWebhook($order, $response);
                break;

            case 'REFUND_REQUEST':
            case 'REFUND':
                $this->handleRefundWebhook($order, $response);
                break;

            case 'VOID_AUTHORIZATION':
            case 'VOID_CAPTURE':
            case 'VOID_PAYMENT':
            case 'VOID_REFUND':
                $this->handleVoidWebhook($order, $response);
                break;

            case 'CANCELLED':
                $this->handleCancelledWebhook($order, $response);
                break;

            default:
                $this->handleUnknownWebhook($order, $response);
                break;
        }
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function handleAuthorizationWebhook(array $order, array $response) {

        $pendingStatusId = $this->config->get('payment_mpgs_hosted_checkout_pending_status_id');

        if ($order['order_status_id'] != $pendingStatusId) {
            $this->model_extension_payment_mpgs_hosted_checkout->log(
                sprintf(
                    $this->language->get('text_not_allow_authorization'),
                    $order['order_status']
                )
            );
            return;
        }

        $message       = sprintf(
            $this->language->get('text_webhook_authorize_capture'),
            $response['transaction']['type'],
            $response['result'],
            $response['transaction']['id'],
            $response['transaction']['authorizationCode']
        );
        $orderStatusId = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id');
        $this->addOrderHistory($order['order_id'], $orderStatusId, $message);
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function handleCaptureWebhook(array $order, array $response) {

        $approvedId = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id');
        $pendingId  = $this->config->get('payment_mpgs_hosted_checkout_pending_status_id');

        if ($order['order_status_id'] != $approvedId && $order['order_status_id'] != $pendingId) {
            $this->model_extension_payment_mpgs_hosted_checkout->log(
                sprintf(
                    $this->language->get('text_not_allow_capture'),
                    $order['order_status']
                )
            );
            return;
        }

        $message = sprintf(
            $this->language->get('text_webhook_authorize_capture'),
            $response['transaction']['type'],
            $response['result'],
            $response['transaction']['id'],
            $response['transaction']['authorizationCode']
        );
        $this->addOrderHistory($order['order_id'], self::ORDER_CAPTURED, $message);
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function handleRefundWebhook(array $order, array $response) {

        if ($order['order_status_id'] != self::ORDER_CAPTURED) {
            $this->model_extension_payment_mpgs_hosted_checkout->log(
                sprintf(
                    $this->language->get('text_not_allow_refund'),
                    $order['order_status']
                )
            );
            return;
        }

        $message = sprintf(
            $this->language->get('text_webhook_refund_void'),
            $response['transaction']['type'],
            $response['result'],
            $response['transaction']['id']
        );
        $this->addOrderHistory($order['order_id'], self::ORDER_REFUNDED, $message);
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function handleVoidWebhook(array $order, array $response) {

        $approvedId = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id');

        if ($order['order_status_id'] != $approvedId) {
            $this->model_extension_payment_mpgs_hosted_checkout->log(
                sprintf($this->language->get('text_not_allow_void'), $order['order_status'])
            );
            return;
        }

        $message = sprintf(
            $this->language->get('text_webhook_refund_void'),
            $response['transaction']['type'],
            $response['result'],
            $response['transaction']['id']
        );
        $this->addOrderHistory($order['order_id'], self::ORDER_VOIDED, $message);
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function handleCancelledWebhook(array $order, array $response) {

        if ($order['order_status_id'] == self::ORDER_CANCELLED) {
            return;
        }

        $message = sprintf(
            $this->language->get('text_webhook_refund_void'),
            $response['transaction']['type'],
            $response['result'],
            $response['transaction']['id']
        );

        $this->addOrderHistory($order['order_id'], self::ORDER_CANCELLED, $message);
    }

    /**
     * @param array $order
     * @param array $response
     */
    private function handleUnknownWebhook(array $order, array $response) {

        if ($order['order_status_id'] == self::ORDER_CANCELLED) {
            return;
        }

        $message = sprintf(
            $this->language->get('text_webhook_unknown'),
            $response['transaction']['type']
        );
        $this->addOrderHistory($order['order_id'], self::ORDER_CANCELLED, $message);
    }

    /**
     * @param $response
     * @return bool
     */
    public function isApproved($response) {

        $gatewayCode = $response['response']['gatewayCode'];

        if (!in_array($gatewayCode, array('APPROVED', 'APPROVED_AUTO'))) {
            return false;
        }

        return true;
    }

    /**
     * @param $headers
     * @return bool
     */
    protected function isSecure($headers) {

        $https      = $headers['HTTPS'];
        $serverPort = $headers['SERVER_PORT'];

        return (!empty($https) && $https === "1") || $serverPort === "443";
    }

    /**
     * @param $customerId
     * @return array
     */
    public function getTokenizeCards($customerId) {

        $this->load->language(self::PATH_EXTENSION);
        $this->load->model(self::PATH_EXTENSION);

        $customerTokens = $this->model_extension_payment_mpgs_hosted_checkout->getCustomerTokens($customerId);
        $uri            = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/token/';
        $cards          = [];

        foreach ($customerTokens as $token) {

            $response = $this->model_extension_payment_mpgs_hosted_checkout
                ->apiRequest('GET', $uri . urlencode($token['token']));

            if (($response['result'] ?? '') !== 'SUCCESS' || ($response['status'] ?? '') !== 'VALID') {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "mpgs_hpf_token`
                    WHERE hpf_token_id='" . (int)$token['hpf_token_id'] . "'");
                continue;
            }

            $cardData = $response['sourceOfFunds']['provided']['card'] ?? [];

            $cardNumber = isset($cardData['number'])
                ? substr($cardData['number'], -4)
                : '????';

            $expiryRaw = $cardData['expiry'] ?? '';

            if (strlen($expiryRaw) === 4 && ctype_digit($expiryRaw)) {
                $expiryMonth = substr($expiryRaw, 0, 2);
                $expiryYear  = substr($expiryRaw, 2, 2);
            } else {
                $expiryMonth = '??';
                $expiryYear  = '??';
            }

            $brand = isset($cardData['brand'])
                ? ucfirst(strtolower($cardData['brand']))
                : 'Card';

            $cards[] = [
                'id'     => (int)$token['hpf_token_id'],
                'type'   => sprintf(
                    $this->language->get('text_card_type'),
                    $brand
                ),
                'label'  => sprintf(
                    $this->language->get('text_card_label'),
                    $cardNumber
                ),
                'expiry' => sprintf(
                    $this->language->get('text_card_expiry'),
                    $expiryMonth . '/' . $expiryYear
                )
            ];
        }

        return $cards;
    }

    /**
     * @param $orderId
     * @param $amount
     * @return array
     * @throws MpgsPaymentException
     */
    public function createSession($orderId, $amount) {

        $this->load->model(self::PATH_EXTENSION);

        // Create Session
        $uri     = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/session';
        $session = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('POST', $uri);
        $txnId   = uniqid(sprintf('%s-', $orderId));

        // Update Session
        $uri                = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() .
         '/session/' .
          $session['session']['id'];
        $updateSessionData  = [
            'order' => [
                'amount'    => $amount,
                'currency'  => $this->session->data['currency'],
                'id'        => $orderId,
                'reference' => $orderId,
            ],
            'partnerSolutionId' => $this->model_extension_payment_mpgs_hosted_checkout->buildPartnerSolutionId(),
            'authentication'    => [
                'channel'               => 'PAYER_BROWSER',
                'purpose'               => 'PAYMENT_TRANSACTION',
                'redirectResponseUrl'   => str_replace(
                    '&amp;',
                    '&',
                    $this->url->link(
                        'extension/payment/mpgs_hosted_checkout/payerAuthComplete',
                        [
                        'session_id'        => $session['session']['id'],
                        'OCSESSID'          => $this->session->getId()
                        ],
                        true
                    )
                )
            ],
            'transaction'   => [
                    'reference' => $txnId
            ],
            'billing'       => $this->getBillingAddress(),
            'customer'      => $this->getCustomer(),
        ];

        if (!empty($this->getShippingAddress())) {
            $updateSessionData = array_merge($updateSessionData, ['shipping' => $this->getShippingAddress()]);
        }

        $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('PUT', $uri, $updateSessionData);

        return $session;
    }

    /**
     * Controller action
     * Payer returns from 3DS1 or 3DS2 auth challenge
     */
    public function payerAuthComplete() {

        $this->restartOCSession();

        $this->load->language(self::PATH_EXTENSION);
        $this->load->model(self::PATH_EXTENSION);

        $txnId                  = $this->request->post['transaction_id'] ?? '';
        $orderId                = $this->request->post['order_id'] ?? '';
        $gatewayRecommendation  = $this->request->post['response_gatewayRecommendation'] ?? '';
        $sessionId              = $this->request->request['session_id'] ?? '';

        try {
            if ($gatewayRecommendation !== 'PROCEED') {
                throw new MpgsPaymentException($this->language->get('error_payment_declined_3ds'));
            }

            $operation = 'AUTHORIZE';
            if ($this->model_extension_payment_mpgs_hosted_checkout->getPaymentAction() === 'PURCHASE') {
                $operation = 'PAY';
            }

            $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() .
             self::PATH_ORDER . $orderId . '/transaction/1';
            $operationData = [
                'apiOperation'  => $operation,
                'session'       => [
                    'id' => $sessionId
                ],
                'authentication' => [
                    'transactionId' => $txnId
                ],
                'transaction' => [
                    'reference' => $orderId
                ],
                'sourceOfFunds' => [
                    'type' => 'CARD'
                ],
                'partnerSolutionId' => $this->model_extension_payment_mpgs_hosted_checkout->buildPartnerSolutionId(),
                'order' => array_merge([
                    'reference'         => $orderId,
                    'currency'          => $this->session->data['currency'],
                    'notificationUrl'   => $this->url->link(
                        'extension/payment/mpgs_hosted_checkout/callback',
                        '',
                        true
                    ),
                ], $this->getOrderItemsTaxAndTotals()),
                'billing' => $this->getBillingAddress(),
                'customer' => $this->getCustomer(),
            ];

            if (!empty($this->getShippingAddress())) {
                $operationData = array_merge($operationData, ['shipping' => $this->getShippingAddress()]);
            }

            $response = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('PUT', $uri, $operationData);

            if (isset($response['result']) && ($response['result'] == 'ERROR' || $response['result'] == 'FAILURE')) {
                $error = $this->language->get('error_transaction_unsuccessful');
                if (isset($response['error']['explanation'])) {
                    $error = sprintf(
                        '%s: %s',
                        $response['error']['cause'],
                        $response['error']['explanation']
                    );
                }
                throw new MpgsPaymentException($error);
            }

            $this->processOrder($response['order'], $response);

            $enabled            = $this->model_extension_payment_mpgs_hosted_checkout->isSavedCardsEnabled();
            $payingWithToken    = isset($this->session->data['token_id'])
                ? (bool)$this->session->data['token_id']
                : false;
            if ($enabled && !$payingWithToken && isset($this->session->data['save_card']) &&
             $this->session->data['save_card'] === '1') {
                $this->saveCards([
                    'id' => $sessionId
                ]);
            }

            $this->clearTokenSaveCardSessionData();

            $this->model_extension_payment_mpgs_hosted_checkout->clearCheckoutSession();
            $this->response->redirect($this->url->link('checkout/success', '', true));

        } catch (Exception $e) {
            $this->clearTokenSaveCardSessionData();
            $this->session->data['error'] = $e->getMessage();
            $this->addOrderHistory($orderId, self::ORDER_FAILED, $e->getMessage());
            $this->response->redirect($this->url->link('checkout/checkout2', '', true));
        }
    }

    /**
     * Controller action
     * Process Hosted Session Payment Method
     */
    public function saveHostedSessionPayment() {

        $this->load->language(self::PATH_EXTENSION);
        $this->load->model(self::PATH_EXTENSION);

        if (isset($this->request->request['mpgs-payment-token']) &&
         $this->request->request['mpgs-payment-token'] !== 'new') {
            $tokenId = $this->request->request['mpgs-payment-token'];

            $this->session->data['token_id'] = $tokenId;
            $token = $this->getTokenById($tokenId);

            $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() .
             '/session/' .
              $this->request->request['session_id'];
            $result = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('PUT', $uri, [
                'sourceOfFunds' => [
                    'token' => $token['token'],
                    'type' => 'CARD'
                ]
            ]);

            if (!isset($result['session']) || $result['session']['updateStatus'] !== 'SUCCESS') {
                header('HTTP/1.0 500 Session Update Error');
                return;
            }
        }
        if (isset($this->request->request['mpgs-save-new-method']) &&
         $this->request->request['mpgs-save-new-method'] === '1') {
            $this->session->data['save_card'] = $this->request->request['mpgs-save-new-method'];
        }
    }

    /**
     * Clear values of Hosted Payment Form
     * fields from session
     */
    protected function clearTokenSaveCardSessionData() {

        unset($this->session->data['save_card']);
        unset($this->session->data['token_id']);
        unset($this->session->data['source_of_funds']);
    }

    /**
     * Cancel callback
     */
    public function cancelCallback() {

        $this->load->language(self::PATH_EXTENSION);
        $orderId = $this->session->data['order_id'] ?? null;

        if (!$orderId) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
            return;
        }

        $message = $this->language->get('text_payment_cancelled')
         ?: 'MasterCard Payment transaction has been CANCELLED by customer.';
        $this->addOrderHistory($orderId, self::ORDER_CANCELLED, $message);
        unset($this->session->data['order_id']);
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }
    
    /**
     * Controller action
     * Error callback
     */
    public function errorCallback() {

        $this->load->language(self::PATH_EXTENSION);
        $this->load->model(self::PATH_EXTENSION);

        $this->model_extension_payment_mpgs_hosted_checkout->log(
            'MPGS Hosted Checkout errorCallback triggered.'
        );
        $errorMessage = $this->language->get('error_payment_general');

        $this->session->data['error'] = $errorMessage;

        $this->response->redirect($this->url->link('checkout/checkout', '', true));
    }

    /**
     * Authorization code is returned on the AUTHORIZATION transaction, which may not
     * be the first entry in the order's transaction list (e.g. hosted checkout).
     *
     * @param array $orderData Retrieve-order API payload
     * @return string
     */
    protected function findAuthorizationCodeFromOrder(array $orderData): string {

        if (empty($orderData['transaction']) || !is_array($orderData['transaction'])) {
            return '';
        }

        $orderStatus    = strtoupper($orderData['status'] ?? '');
        $preferredTypes = ['AUTHORIZATION'];
        if ($orderStatus === 'CAPTURED') {
            $preferredTypes = ['PAYMENT', 'CAPTURE', 'AUTHORIZATION'];
        }

        foreach ($preferredTypes as $preferredType) {
            foreach ($orderData['transaction'] as $mpgsTxn) {
                if (strtoupper($mpgsTxn['transaction']['type'] ?? '') === $preferredType
                    && !empty($mpgsTxn['transaction']['authorizationCode'])) {
                    return (string)$mpgsTxn['transaction']['authorizationCode'];
                }
            }
        }

        foreach ($orderData['transaction'] as $mpgsTxn) {
            if (!empty($mpgsTxn['transaction']['authorizationCode'])) {
                return (string)$mpgsTxn['transaction']['authorizationCode'];
            }
        }

        return '';
    }

    /**
     * @param array      $retrievedOrder
     * @param array|null $txn
     * @throws MpgsPaymentException
     */
    protected function processOrder($retrievedOrder, $txn = null) {

        if ($txn === null) {
            $txn = $retrievedOrder['transaction'][0] ?? null;
        }

        if ($txn === null || !isset($txn['transaction']['id'])) {
            throw new MpgsPaymentException($this->language->get('error_transaction_unsuccessful'));
        }

        $authCode = $txn['transaction']['authorizationCode'] ?? '';
        if ($authCode === '') {
            $authCode = $this->findAuthorizationCodeFromOrder($retrievedOrder);
        }

        if ($retrievedOrder['status'] === 'CAPTURED') {
            $message        = sprintf(
                $this->language->get('text_payment_captured'),
                $txn['transaction']['id'],
                $authCode
            );
            $orderStatusId  = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id');
        } elseif ($retrievedOrder['status'] === 'AUTHORIZED') {
            $message        = sprintf(
                $this->language->get('text_payment_authorized'),
                $txn['transaction']['id'],
                $authCode
            );
            $orderStatusId  = $this->config->get('payment_mpgs_hosted_checkout_pending_status_id');
        } else {
            throw new MpgsPaymentException($this->language->get('error_transaction_unsuccessful'));
        }
        $this->addOrderHistory($this->session->data['order_id'], $orderStatusId, $message);
    }

    /**
     * @param $orderId
     * @return mixed
     */
    protected function retrieveOrder($orderId) {

        $this->load->model(self::PATH_EXTENSION);
        $uri        = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . self::PATH_ORDER . $orderId;
        return $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('GET', $uri);
    }

    /**
     * @param $orderId
     * @param $txnId
     * @return mixed
     */
    protected function retrieveTransaction($orderId, $txnId) {

        $this->load->model(self::PATH_EXTENSION);
        $uri        = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri()
            . self::PATH_ORDER . $orderId . '/transaction/' . $txnId;
        return $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('GET', $uri);
    }

    /**
     * @return array
     */
    public function configureHostedCheckout() {

        $this->load->helper('utf8');
        $this->load->model(self::PATH_EXTENSION);

        return [
            'merchant'  => $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId(),
            'session'   => [
                'id'        => $this->session->data['mpgs_hosted_checkout']['session']['id'],
                'version'   => $this->session->data['mpgs_hosted_checkout']['session']['version']
            ]
        ];
    }

    /**
     * @param $orderId
     * @return string
     */
    protected function getOrderPrefix($orderId) {

        $prefix = trim($this->config->get('payment_mpgs_hosted_checkout_order_id_prefix'));

        if (!empty($prefix)) {
            $orderId = $prefix . $orderId;
        }

        return $orderId;
    }

    /**
     * @return string
     */
    private function getMerchantLogoUrl() {

        $merchantLogo = $this->config->get('payment_mpgs_hosted_checkout_image');
        if (!empty($merchantLogo) && is_file(DIR_IMAGE . $merchantLogo)) {
            return HTTP_SERVER . 'image/' . $merchantLogo;
        }
        return '';
    }

    /**
     * @return array
     */
    private function getMerchantAddress() {

        $address = [];
        $this->maybeAdd($address, 'line1', 'payment_mpgs_hosted_checkout_merchant_adrs1');
        $this->maybeAdd($address, 'line2', 'payment_mpgs_hosted_checkout_merchant_adrs2');
        $this->maybeAdd($address, 'line3', 'payment_mpgs_hosted_checkout_merchant_postcode');
        $this->maybeAdd($address, 'line4', 'payment_mpgs_hosted_checkout_merchant_country');
        return $address;
    }

    /**
     * @param $merchant
     */
    private function addContactInfo(&$merchant) {

        $this->maybeAdd($merchant, 'email', 'payment_mpgs_hosted_checkout_merchant_email');
        $this->maybeAdd($merchant, 'phone', 'payment_mpgs_hosted_checkout_merchant_phone');
    }

    /**
     * @param $target
     * @param $key
     * @param $configKey
     */
    private function maybeAdd(&$target, $key, $configKey) {

        $value = $this->config->get($configKey);
        if (!empty($value)) {
            $target[$key] = $value;
        }
    }
}
