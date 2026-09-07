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

namespace Mpgs\Payment;

/**
 * Trait MpgsCatalogHelperTrait
 *
 * Encapsulates address-building and order-item helpers for the MPGS catalog
 * controller. Extracted to reduce the main controller's coupling metric.
 */
trait MpgsCatalogHelperTrait {


    public static $pathCheckoutOrder = 'checkout/order';
    public static $pathExtensionPaymentMpgsHostedCheckout = 'extension/payment/mpgs_hosted_checkout';

    /**
     * @return array
     */
    protected function getBillingAddress() {

        $billingAddress = [];
        $paymentAddress = $this->session->data['payment_address'];

        if (!empty($paymentAddress['city'])) {
            $billingAddress['address']['city'] = utf8_substr($paymentAddress['city'], 0, 100);
        }

        if (!empty($paymentAddress['company'])) {
            $billingAddress['address']['company'] = $paymentAddress['company'];
        }

        if (!empty($paymentAddress['iso_code_3'])) {
            $billingAddress['address']['country'] = $paymentAddress['iso_code_3'];
        }

        if (!empty($paymentAddress['postcode'])) {
            $billingAddress['address']['postcodeZip'] = utf8_substr($paymentAddress['postcode'], 0, 10);
        }

        if (!empty($paymentAddress['zone'])) {
            $billingAddress['address']['stateProvince'] = utf8_substr($paymentAddress['zone'], 0, 20);
        }

        if (!empty($paymentAddress['address_1'])) {
            $billingAddress['address']['street'] = utf8_substr($paymentAddress['address_1'], 0, 100);
        }

        if (!empty($paymentAddress['address_2'])) {
            $billingAddress['address']['street2'] = utf8_substr($paymentAddress['address_2'], 0, 100);
        }

        return $billingAddress;
    }

    /**
     * @return array
     */
    protected function getShippingAddress() {

        $shippingAddress = [];

        if (!isset($this->session->data['shipping_address'])) {
            return $shippingAddress;
        }

        $shippingAddressData = $this->session->data['shipping_address'];

        if (!empty($shippingAddressData['city'])) {
            $shippingAddress['address']['city'] = utf8_substr($shippingAddressData['city'], 0, 100);
        }

        if (!empty($shippingAddressData['company'])) {
            $shippingAddress['address']['company'] = $shippingAddressData['company'];
        }

        if (!empty($shippingAddressData['iso_code_3'])) {
            $shippingAddress['address']['country'] = $shippingAddressData['iso_code_3'];
        }

        if (!empty($shippingAddressData['postcode'])) {
            $shippingAddress['address']['postcodeZip'] = utf8_substr($shippingAddressData['postcode'], 0, 10);
        }

        if (!empty($shippingAddressData['zone'])) {
            $shippingAddress['address']['stateProvince'] = utf8_substr($shippingAddressData['zone'], 0, 20);
        }

        if (!empty($shippingAddressData['address_1'])) {
            $shippingAddress['address']['street'] = utf8_substr($shippingAddressData['address_1'], 0, 100);
        }

        if (!empty($shippingAddressData['address_2'])) {
            $shippingAddress['address']['street2'] = utf8_substr($shippingAddressData['address_2'], 0, 100);
        }

        if (!empty($shippingAddressData['firstname'])) {
            $shippingAddress['contact']['firstName'] = utf8_substr($shippingAddressData['firstname'], 0, 50);
        }

        if (!empty($shippingAddressData['lastname'])) {
            $shippingAddress['contact']['lastName'] = utf8_substr($shippingAddressData['lastname'], 0, 50);
        }

        $this->applyShippingContactInfo($shippingAddress);

        return $shippingAddress;
    }

    /**
     * Populates contact email and phone on the shipping address array.
     * Resolves customer data from the logged-in customer or guest session.
     *
     * @param array $shippingAddress
     */
    private function applyShippingContactInfo(array &$shippingAddress) {

        if ($this->customer->isLogged()) {
            $this->load->model('account/customer');
            $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());

            if (!empty($customerModel['email'])) {
                $shippingAddress['contact']['email'] = $customerModel['email'];
            }

            if (!empty($customerModel['telephone'])) {
                $shippingAddress['contact']['phone'] = utf8_substr($customerModel['telephone'], 0, 20);
            }

            return;
        }

        $guestUser = $this->session->data['guest'] ?? [];

        if (!empty($guestUser['email'])) {
            $shippingAddress['contact']['email'] = $guestUser['email'];
        }

        if (!empty($guestUser['telephone'])) {
            $shippingAddress['contact']['phone'] = utf8_substr($guestUser['telephone'], 0, 20);
        }
    }

    /**
     * @return array
     */
    protected function getCustomer() {

        if ($this->customer->isLogged()) {
            return $this->getLoggedInCustomerData();
        }

        if (isset($this->session->data['guest'])) {
            return $this->getGuestCustomerData();
        }

        return $this->getOrderCustomerData();
    }

    /**
     * @return array
     */
    private function getLoggedInCustomerData() {

        $this->load->model('account/customer');
        $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());
        $customerData  = [];

        if (!empty($customerModel['firstname'])) {
            $customerData['firstName'] = utf8_substr($customerModel['firstname'], 0, 50);
        }
        if (!empty($customerModel['lastname'])) {
            $customerData['lastName'] = utf8_substr($customerModel['lastname'], 0, 50);
        }
        if (!empty($customerModel['email'])) {
            $customerData['email'] = $customerModel['email'];
        }
        if (!empty($customerModel['telephone'])) {
            $customerData['phone'] = utf8_substr($customerModel['telephone'], 0, 20);
        }

        return $customerData;
    }

    /**
     * @return array
     */
    private function getGuestCustomerData() {

        $guestUser    = $this->session->data['guest'];
        $customerData = [];

        if (!empty($guestUser['firstname'])) {
            $customerData['firstName'] = utf8_substr($guestUser['firstname'], 0, 50);
        }
        if (!empty($guestUser['lastname'])) {
            $customerData['lastName'] = utf8_substr($guestUser['lastname'], 0, 50);
        }
        if (!empty($guestUser['email'])) {
            $customerData['email'] = $guestUser['email'];
        }
        if (!empty($guestUser['telephone'])) {
            $customerData['phone'] = utf8_substr($guestUser['telephone'], 0, 20);
        }

        return $customerData;
    }

    /**
     * @return array
     */
    private function getOrderCustomerData() {

        $customerData = [];

        if (empty($this->session->data['order_id'])) {
            return $customerData;
        }

        $this->load->model(static::$pathCheckoutOrder);
        $orderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        if (!$orderInfo) {
            return $customerData;
        }

        if (!empty($orderInfo['firstname'])) {
            $customerData['firstName'] = $orderInfo['firstname'];
        }
        if (!empty($orderInfo['lastname'])) {
            $customerData['lastName'] = $orderInfo['lastname'];
        }
        if (!empty($orderInfo['email'])) {
            $customerData['email'] = $orderInfo['email'];
        }
        if (!empty($orderInfo['telephone'])) {
            $customerData['phone'] = utf8_substr($orderInfo['telephone'], 0, 20);
        }

        return $customerData;
    }

    /**
     * Order items, tax and order totals.
     *
     * @return array
     */
    protected function getOrderItemsTaxAndTotals() {

        $orderData     = [];
        $sendLineItems = $this->config->get('payment_mpgs_hosted_checkout_send_line_items');
        $subTotal      = 0;
        $lineItems     = [];

        foreach ($this->cart->getProducts() as $product) {
            $subTotal += $product['price'] * $product['quantity'];

            if ($sendLineItems) {
                $lineItems[] = $this->buildLineItem($product);
            }
        }

        list($totals, $taxes, $total) = $this->calculateTotals();

        $shipping = 0;
        $discount = 0;

        foreach ($totals as $t) {

            if (in_array($t['code'], ['shipping', 'handling'])) {
                $shipping += round($t['value'], 2);
            }

            if (in_array($t['code'], ['coupon', 'voucher', 'reward', 'discount'])) {
                $discount += abs(round($t['value'], 2));
            }
        }

        $totalTax = array_sum($taxes);

        $grandTotal = round($total, 2);
        $orderData['amount'] = $grandTotal;

        if ($sendLineItems && $lineItems) {
            $roundedSubTotal = round($subTotal, 2);
            $roundedTax      = round($totalTax, 2);
            $roundedShipping = round($shipping, 2);
            $roundedDiscount = round($discount, 2);

            $formulaCheck = round($roundedSubTotal + $roundedTax + $roundedShipping - $roundedDiscount, 2);

            if ($formulaCheck === $grandTotal) {
                $orderData['item']      = $lineItems;
                $this->appendOptionalLineItemFields($orderData, $roundedShipping, $roundedTax, $roundedDiscount);
            }
        }

        return $orderData;
    }

    /**
     * Append optional line-item cost fields to $orderData when their values are non-zero.
     *
     * @param array $orderData  Passed by reference so fields are written directly.
     * @param float $shipping
     * @param float $totalTax
     * @param float $discount
     */
    private function appendOptionalLineItemFields(
        array &$orderData,
        float $shipping,
        float $totalTax,
        float $discount
    ): void {
        if ($shipping > 0) {
            $orderData['shippingAndHandlingAmount'] = $shipping;
        }
        if ($totalTax > 0) {
            $orderData['taxAmount'] = $totalTax;
        }
        if ($discount > 0) {
            $orderData['discount']['amount'] = $discount;
        }
    }

    /**
     * Build a single line-item array for a cart product.
     *
     * @param array $product
     * @return array
     */
    private function buildLineItem(array $product) {

        $description = [];
        foreach ($product['option'] as $option) {
            if ($option['type'] != 'file') {
                $value = isset($option['value']) ? $option['value'] : '';
            } else {
                $this->load->model('tool/upload');
                $uploadInfo = $this->model_tool_upload->getUploadByCode($option['value']);
                $value      = $uploadInfo ? $uploadInfo['name'] : '';
            }
            $description[] = $option['name'] . ':' . (utf8_strlen($value) > 20
                ? utf8_substr($value, 0, 20) . '..'
                : $value);
        }

        return [
            'description' => !empty($description)
                ? utf8_substr(implode(', ', $description), 0, 127)
                : utf8_substr($product['model'], 0, 127),
            'name'        => utf8_substr($product['name'], 0, 127),
            'quantity'    => $product['quantity'],
            'sku'         => utf8_substr($product['model'], 0, 127),
            'unitPrice'   => round($product['price'], 2),
        ];
    }

    /**
     * Execute all enabled total extensions and return totals, taxes and grand total.
     *
     * @return array [totals, taxes, total]
     */
    private function calculateTotals() {

        $totals = [];
        $taxes  = $this->cart->getTaxes();
        $total  = 0;

        $this->load->model('setting/extension');
        $results = $this->model_setting_extension->getExtensions('total');

        $sortOrder = [];
        foreach ($results as $key => $value) {
            $sortOrder[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }
        array_multisort($sortOrder, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);
                $this->{'model_extension_total_' . $result['code']}->getTotal([
                    'totals' => &$totals,
                    'taxes'  => &$taxes,
                    'total'  => &$total,
                ]);
            }
        }

        return [$totals, $taxes, $total];
    }

    // -------------------------------------------------------------------------
    // Webhook / order-history helpers
    // -------------------------------------------------------------------------

    /**
     * Persist an order history entry via the OC checkout-order model.
     *
     * @param $orderId
     * @param $orderStatusId
     * @param $message
     */
    protected function addOrderHistory($orderId, $orderStatusId, $message) {

        $this->load->model(static::$pathCheckoutOrder);
        $this->model_checkout_order->addOrderHistory($orderId, $orderStatusId, $message);
    }

    /**
     * Webhook callback handler for Mastercard Gateway notifications.
     */
    public function callback() {

        $this->load->language(static::$pathExtensionPaymentMpgsHostedCheckout);
        $this->load->model(static::$pathExtensionPaymentMpgsHostedCheckout);
    
        $requestHeaders = $this->request->server;
        $parsedData = $this->parseWebhookBody();
    
        if ($parsedData === null) {
            return;
        }
    
        try {
            $this->validateWebhook($requestHeaders, $parsedData);
            $this->logWebhookDetails($requestHeaders, $parsedData);
            $response = $this->verifyWebhookTransaction($parsedData);
    
            if ($this->isApproved($response)) {
                $mpgsOrderId = $this->resolveOrderIdFromPrefix($response);
                $this->load->model(static::$pathCheckoutOrder);
                $order = $this->model_checkout_order->getOrder($mpgsOrderId);
    
                if (isset($response['risk']['response'])) {
                    $this->handleWebhookRiskResponse($order, $response);
                    $this->model_extension_payment_mpgs_hosted_checkout->log('webhook completed (200 OK)');
                }
            } else {
                $this->model_extension_payment_mpgs_hosted_checkout->log(
                    sprintf('Unexpected gateway code "%s"', $response['response']['gatewayCode'])
                );
            }
        } catch (MpgsPaymentException $e) {

            $this->model_extension_payment_mpgs_hosted_checkout->log('Webhook/Gateway Error: ' . $e->getMessage());
            header('HTTP/1.1 500 ' . $e->getMessage());
            return;
        }
    }

    // -------------------------------------------------------------------------
    // Session restart helper
    // -------------------------------------------------------------------------

    /**
     * Resume the OC session identified by the OCSESSID GET parameter.
     * Required because MasterCard redirects back with a plain GET parameter.
     */
    private function restartOCSession() {

        if (empty($this->request->get['OCSESSID'])) {
            return;
        }

        if (!preg_match('/^[\p{L}\p{N}]{20,64}$/u', $this->request->get['OCSESSID'])) {
            return;
        }
        $this->session->start($this->request->get['OCSESSID']);

        setcookie(
            $this->config->get('session_name'),
            $this->session->getId(),
            ini_get('session.cookie_lifetime'),
            ini_get('session.cookie_path'),
            ini_get('session.cookie_domain')
        );

        if (class_exists('\ControllerStartupStartup')) {
            (new \ControllerStartupStartup($this->registry))->index();
        }
    }

    // -------------------------------------------------------------------------
    // Card / token helpers
    // -------------------------------------------------------------------------

    /**
     * @param $tokenId
     * @return array
     */
    protected function getTokenById($tokenId) {

        $tokensResult = $this->db->query("SELECT token FROM `" . DB_PREFIX . "mpgs_hpf_token`
         WHERE hpf_token_id='" . (int)$tokenId . "'");
        return $tokensResult->row;
    }

    /**
     * @param $session
     * @throws MpgsPaymentException
     */
    protected function saveCards($session) {

        $tokenResponse = $this->createCardToken($session['id']);

        if (!isset($tokenResponse['token']) || empty($tokenResponse['token'])) {
            throw new MpgsPaymentException($this->language->get('error_token_not_present'));
        }

        if ($this->customer->isLogged()) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "mpgs_hpf_token`
             SET `customer_id`='" . (int)$this->customer->getId() . "', `token`='" .
              $this->db->escape($tokenResponse['token']) . "', `created_at`=NOW()");
        }
    }

    /**
     * @param $sessionId
     * @return mixed
     */
    protected function createCardToken($sessionId) {

        $requestData = [
            'session' => [
                'id' => $sessionId
            ],
            'sourceOfFunds' => [
                'type' => 'CARD'
            ]
        ];

        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/token';
        return $this->model_extension_payment_mpgs_hosted_checkout->apiRequest(
            'POST',
            $uri,
            $requestData
        );
    }

    // -------------------------------------------------------------------------
    // Email helpers
    // -------------------------------------------------------------------------

    /**
     * Attempt to send the order confirmation email, logging any failure without
     * re-throwing so a mail error never rolls back an already-captured payment.
     */
    private function sendOrderEmailSafely(
        string $orderId,
        string $customerEmail,
        string $transactionStatus,
        string $customerName
    ): void {
        try {
            $this->sendCustomEmail($orderId, $customerEmail, $transactionStatus, $customerName);
        } catch (\Exception $mailEx) {
            $this->model_extension_payment_mpgs_hosted_checkout->log(
                'Order email failed (payment already captured): ' . $mailEx->getMessage()
            );
        }
    }

    /**
     * @param $orderId
     * @param $receiverAddress
     * @param $subject
     * @param $customerName
     */
    private function sendCustomEmail($orderId, $receiverAddress, $subject, $customerName) {

        $data['order_id']           = $orderId;
        $data['receiver_address']   = $receiverAddress;
        $data['order_status']       = $subject;
        $data['customer_name']      = $customerName;
        $storeName                  = $this->config->get('config_name');
        $mail                       = new \Mail($this->config->get('config_mail_engine'));
        $mail->parameter            = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname        = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username        = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode(
            $this->config->get('config_mail_smtp_password'),
            ENT_QUOTES,
            'UTF-8'
        );
        $mail->smtp_port            = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout         = $this->config->get('config_mail_smtp_timeout');

        $mail->setTo($data['receiver_address']);
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
        $mail->setSubject(html_entity_decode(
            $storeName . " - Payment " . ucwords(strtolower(str_replace('_', ' ', $subject))),
            ENT_QUOTES,
            'UTF-8'
        ));
        $mail->setHtml($this->load->view('extension/payment/mpgs_email', $data));
        $mail->send();
    }
}
