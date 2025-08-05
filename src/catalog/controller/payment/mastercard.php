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
 * @version  GIT: @1.3.3@
 * @link     https://github.com/fingent-corp/gateway-opencart-mastercard-module
 */

namespace Opencart\Catalog\Controller\Extension\Mastercard\Payment;

use Opencart\Catalog\Controller\Extension\Mastercard\Payment\MasterCardPaymentException;

class Mastercard extends \Opencart\System\Engine\Controller {
    const ORDER_CAPTURED = '15';
    const ORDER_VOIDED = '16';
    const ORDER_CANCELLED = '7';
    const ORDER_REFUNDED = '11';
    const ORDER_FAILED = '10';
    const HEADER_WEBHOOK_SECRET = 'HTTP_X_NOTIFICATION_SECRET';
    const HEADER_WEBHOOK_ATTEMPT = 'HTTP_X_NOTIFICATION_ATTEMPT';
    const HEADER_WEBHOOK_ID = 'HTTP_X_NOTIFICATION_ID';
    const MASTER_CARD_MODEL_PATH = 'extension/mastercard/payment/mastercard';
    const ACCOUNT_CUSTOMER_MODEL_PATH = 'account/customer';
    protected $orderAmount = 0;

    /**
     * @return mixed
     */
    public function index() {
        $this->load->language(self::MASTER_CARD_MODEL_PATH);
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
    
        $gatewayUri = $this->model_extension_mastercard_payment_mastercard->getGatewayUri();
        $integrationModel = $this->model_extension_mastercard_payment_mastercard->getIntegrationModel();
    
        $view = null;
    
        if ($this->isValidSession($integrationModel)) {
            try {
                unset($this->session->data['HostedCheckout_sessionId']);
                $data['configured_variables'] = $this->getConfiguredVariables();
            } catch (\Exception $e) {
                $data['error_session'] = $e->getMessage();
            }
    
            if (empty($data['error_session']) && $integrationModel === 'hostedcheckout') {
                
                $data['hosted_checkout_js'] = $this->getHostedCheckoutJsUri($gatewayUri);
                $data['checkout_interaction'] = $this->config->get('payment_mastercard_hc_type');
                $data['completeCallback'] = $this->url->link(
                    'extension/mastercard/payment/mastercard.processHostedCheckout',
                    '',
                    false
                );
                $data['cancelCallback'] = $this->url->link(
                    'extension/mastercard/payment/mastercard.cancelCallback',
                    '',
                    true
                );
                $data['errorCallback'] = $this->url->link(
                    'extension/mastercard/payment/mastercard.errorCallback',
                    '',
                    true
                );
    
                if ($this->isConfiguredVariablesValid($data)) {
                    $data = array_merge($data, $this->getCheckoutSessionData($data));
                    $data['jsonData'] = json_encode($data);
                    $this->setSessionDataCookie($data);
                    $this->document->addScript($data['hosted_checkout_js']);
                    $view = $this->load->view('extension/mastercard/payment/mgps_hosted_checkout', $data);
                }
            }
        }
    
        return $view;
    }
    
    
    private function isValidSession($integrationModel) {
        return !empty($this->session->data['order_id']) &&
               !empty($this->session->data['currency']) &&
               !empty($this->session->data['shipping_address']) &&
               $integrationModel === 'hostedcheckout';
    }
    
    private function getConfiguredVariables() {
        $built = $this->hasCheckoutSession();
        if ($built === true) {
            return json_encode($this->configureHostedCheckout());
        }
        return null;
    }
    
    private function getHostedCheckoutJsUri($gatewayUri) {
        $cacheBust = (int)round(microtime(true));
        return $gatewayUri . 'static/checkout/checkout.min.js?_=' . $cacheBust;
    }
    
    private function isConfiguredVariablesValid($data) {
        return isset($data['configured_variables']);
    }
    
    private function getCheckoutSessionData($data) {
        $checkoutSessionId = json_decode($data['configured_variables']);
        return [
            'session_id' => $checkoutSessionId->session->id,
            'merchant' => $checkoutSessionId->merchant,
            'version' => $checkoutSessionId->session->version ?? null,
            'mgps_order_id' => $this->getOrderPrefix($this->session->data['order_id']),
            'order_id' => $this->session->data['order_id'],
            'success_indicator' => $this->session->data['mpgs_hosted_checkout']['successIndicator'] ?? null,
            'OCSESSID' => $this->request->cookie['OCSESSID'] ?? ''
        ];
    }

    private function setSessionDataCookie($data) {
        setcookie('OCSESSID', $data['OCSESSID'], time() + 24 * 3600, '/');
    }
    
    /**
     * @param $route
     */
    public function init($route) {
        $allowed = ['checkout/checkout'];
        if (!in_array($route, $allowed)) {
            return;
        }
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function hasCheckoutSession() {
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
        $this->model_extension_mastercard_payment_mastercard->clearCheckoutSession();
        $order = $this->getOrder();
        $txnId = uniqid(sprintf('%s-', $order['id']));
        $requestData = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'partnerSolutionId' => $this->model_extension_mastercard_payment_mastercard
                ->buildPartnerSolutionId(),
            'order' => array_merge(
              
                $this->getOrder(),
                $this->getOrderItemsTaxAndTotals(),
                
            ),
            'interaction' => $this->getInteraction(),
            'billing' => $this->getBillingAddress(),
            'customer' => $this->getCustomer(),
            'transaction' => [
                'reference' => $txnId
            ]
        ];

        $requestData['order']['notificationUrl'] =
            $this->url->link('extension/mastercard/payment/mastercard.callback', '', true);
        if (!empty($this->getShippingAddress())) {
            $requestData = array_merge($requestData, ['shipping' => $this->getShippingAddress()]);
        }
        unset($this->session->data['HostedCheckout_sessionId']);
        $uri = $this->model_extension_mastercard_payment_mastercard->getApiUri() . '/session';
        $response = $this->model_extension_mastercard_payment_mastercard->apiRequest('POST', $uri, $requestData);
        if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
            if ($this->model_extension_mastercard_payment_mastercard->getIntegrationModel() === 'hostedcheckout') {
                $this->session->data['mpgs_hosted_checkout'] = $response;
                if (isset($this->session->data['mpgs_hosted_checkout'])) {
                    $this->session->data['mgps_redirect_session'] = $this->session->data['mpgs_hosted_checkout'];
                }
            }
            return true;
        } elseif (!empty($response['result']) && $response['result'] === 'ERROR') {
            throw new \MasterCardPaymentException(json_encode($response['error']));
        }
        return false;
    }

    /**
     * @return mixed
     */
    protected function getInteraction() {
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
        $this->load->model('tool/image');
    
        $merchantLogoUrl = $this->getMerchantLogoUrl();
        $integration['merchant']['name'] = $this->config->get('payment_mastercard_merchant_name');
    
        if ($this->config->get('payment_mastercard_merchant_info') == 1) {
            if ($merchantLogoUrl) {
                $integration['merchant']['logo'] = $merchantLogoUrl;
            }
    
            $integration['merchant']['address'] = $this->getMerchantAddress();
            $this->addContactInfo($integration['merchant']);
        } else {
            $integration['merchant']['name'] = $this->config->get('config_name');
        }
    
        $integration['operation'] = $this->model_extension_mastercard_payment_mastercard
        ->getPaymentAction();
        $integration['returnUrl'] = $this->url->link(
            'extension/mastercard/payment/mastercard.processHostedCheckout',
            '',
            true
        );
        $integration['displayControl'] = [
            'shipping' => 'HIDE',
            'billingAddress' => 'HIDE',
            'customerEmail' => 'HIDE'
        ];
        return $integration;
    }
    
    private function getMerchantLogoUrl() {
        $merchantLogo = $this->config->get('payment_mastercard_merchant_logo');
        if (!empty($merchantLogo) && is_file(DIR_IMAGE . $merchantLogo)) {
            return HTTP_SERVER . 'image/' . $merchantLogo;
        }
        return '';
    }
    
    private function getMerchantAddress() {
        $address = [];
        $this->maybeAdd($address, 'line1', 'payment_mastercard_merchant_address_one');
        $this->maybeAdd($address, 'line2', 'payment_mastercard_merchant_address_two');
        $this->maybeAdd($address, 'line3', 'payment_mastercard_merchant_address_postal_zip_code');
        $this->maybeAdd($address, 'line4', 'payment_mastercard_merchant_country_state');
        return $address;
    }
    
    private function addContactInfo(&$merchant) {
        $this->maybeAdd($merchant, 'email', 'payment_mastercard_address_line_email');
        $this->maybeAdd($merchant, 'phone', 'payment_mastercard_address_line_phone');
    }
    
    private function maybeAdd(&$target, $key, $configKey) {
        $value = $this->config->get($configKey);
        if (!empty($value)) {
            $target[$key] = $value;
        }
    }
    
    /**
     * @return mixed
     */
    protected function getOrder() {
        $orderId = $this->getOrderPrefix($this->session->data['order_id']);
        $orderData['id'] = $orderId;
        $orderData['reference'] = $orderId;
        $orderData['currency'] = $this->session->data['currency'];
        $orderData['description'] = 'Ordered goods';
        $orderData['notificationUrl'] = $this->url->link('extension/mastercard/payment/mastercard.callback', '', true);
        return $orderData;
    }

    /**
     * Order items, tax and order totals
     *
     * @return array
     */
    protected function getOrderItemsTaxAndTotals() {
        $this->load->helper('utf8');
        $orderData = [];
        $sendLineItems = $this->config->get('payment_mastercard_send_line_items');
    
        if ($sendLineItems) {
            $orderData['item'] = $this->getLineItemsFromCart();
        }
    
        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;
    
        $results = $this->getSortedTotalExtensions();
    
        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/' . $result['extension'] . '/total/'
                . $result['code']);
                $modelMethod = $this->{'model_extension_'
                    . $result['extension'] . '_total_'
                    . $result['code']}->getTotal;
                $modelMethod($totals, $taxes, $total);
            }
        }
        $totals = $this->sortTotalsByOrder($totals);
        $totalsParsed = $this->parseTotalsForBreakdown($totals, $total);
         
        if ($totalsParsed['final_total'])  {
            $this->orderAmount = $totalsParsed['formatted_total'];
            $orderData['amount'] = $totalsParsed['formatted_total'];
    
            if ($sendLineItems) {
                $orderData['itemAmount'] = $totalsParsed['sub_total'];
                $orderData['shippingAndHandlingAmount'] = $totalsParsed['shipping'];
                $orderData['taxAmount'] = $totalsParsed['tax'];
            }
        }
        
        if (!empty($totalsParsed['tax_info']) && $sendLineItems) {
            $orderData['tax'] = $totalsParsed['tax_info'];
        }
    
        return $orderData;
    }
    
    private function getLineItemsFromCart() {
        $this->load->model('catalog/product');
        $this->load->model('tool/upload');
        $lineItems = [];
    
        foreach ($this->cart->getProducts() as $product) {
            $item = [];
    
            $item['description'] = $this->buildDescription($product);
            $item['name'] = $this->truncate($product['name']);
            $item['quantity'] = $product['quantity'];
            $item['sku'] = $product['model'] ? $this->truncate($product['model']) : '';
            $item['unitPrice'] = round($product['price'], 2);
    
            $lineItems[] = $item;
        }
    
        return $lineItems;
    }
    
    private function buildDescription($product) {
        $description = [];
    
        foreach ($product['option'] as $option) {
            $value = $this->getOptionValue($option);
            $shortValue = utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20)
            . '..' : $value;
            $description[] = $option['name'] . ':' . $shortValue;
        }
    
        if (!empty($description)) {
            return substr(implode(', ', $description), 0, 127);
        }
    
        if (!empty($product['model'])) {
            return $this->truncate($product['model']);
        }
    
        return '';
    }
    
    private function getOptionValue($option) {
        if ($option['type'] !== 'file') {
            return isset($option['value']) ? $option['value'] : '';
        }
    
        $uploadInfo = $this->model_tool_upload->getUploadByCode($option['value']);
        return $uploadInfo ? $uploadInfo['name'] : '';
    }
    
    private function truncate($value, $length = 127) {
        return substr($value, 0, $length);
    }
    
    
    private function getSortedTotalExtensions() {
        $this->load->model('setting/extension');
        $results = $this->model_setting_extension->getExtensionsByType('total');
    
        $sortOrder = [];
        foreach ($results as $key => $value) {
            $sortOrder[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }
    
        array_multisort($sortOrder, SORT_ASC, $results);
        return $results;
    }
    
    private function sortTotalsByOrder($totals) {
        $sortOrder = [];
    
        foreach ($totals as $key => $value) {
            $sortOrder[$key] = $value['sort_order'];
        }
    
        array_multisort($sortOrder, SORT_ASC, $totals);
        return $totals;
    }
    
    private function parseTotalsForBreakdown($totals, $formattedTotal) {
        $skipTotals = ['sub_total', 'total', 'tax'];
        $subTotal = 0;
        $tax = 0;
        $shipping = 0;
        $taxInfo = [];
    
        foreach ($totals as $value) {
            $formattedValue = round($value['value'], 2);
    
            if ($value['code'] === 'sub_total') {
                $subTotal += $formattedValue;
            } elseif ($value['code'] === 'tax') {
                $tax += $formattedValue;
                $taxInfo[] = [
                    'amount' => $formattedValue,
                    'type' => $value['title']
                ];
            } else {
                
                if (!in_array($value['code'], $skipTotals)) {
                    $shipping += $formattedValue;
                }
            }
        }
    
        $finalTotal = $subTotal + $tax + $shipping;
    
        return [
            'sub_total' => $subTotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'tax_info' => $taxInfo,
            'final_total' => $finalTotal,
            'formatted_total' => round($formattedTotal, 2)
        ];
    }
    
    /**
     * @return array
     */
    protected function getBillingAddress() {
        $this->load->model(self::ACCOUNT_CUSTOMER_MODEL_PATH);
        $this->load->model('account/address');
        $this->load->model('account/order');
    
        $billingAddress = [];
        $paymentAddress = $this->resolvePaymentAddress();
    
        if (!empty($paymentAddress)) {
            $billingAddress['address'] = $this->extractAddressFields($paymentAddress);
        }
    
        return $billingAddress;
    }
    
    private function resolvePaymentAddress() {
        $address = null;
        if ($this->customer->isLogged() && $this->customer->getAddressId()) {
            $address = $this->getCustomerPaymentAddress();
        } elseif (!empty($this->session->data['payment_address'])) {
            $address = $this->session->data['payment_address'];
        } elseif (!empty($this->session->data['shipping_address'])) {
            $address = $this->session->data['shipping_address'];
        } else {
            $address = null;
        }
    
        return $address;
    }
    
    private function getCustomerPaymentAddress() {
        $customerId = $this->customer->getId();
        $addressId = $this->customer->getAddressId();
    
        if (VERSION >= '4.0.2.0') {
            $address = $this->model_account_address->getAddress($customerId, $addressId);
            $this->session->data['payment_address'] = $address;
            return $address;
        }
    
        $address = $this->model_account_address->getAddress($addressId);
        $this->session->data['payment_address'] = $address;
        return $address;
    }
    
    private function extractAddressFields($address) {
        $fields = [];
    
        if (!empty($address['city'])) {
            $fields['city'] = substr($address['city'], 0, 100);
        }
        if (!empty($address['company'])) {
            $fields['company'] = $address['company'];
        }
        if (!empty($address['iso_code_3'])) {
            $fields['country'] = $address['iso_code_3'];
        }
        if (!empty($address['postcode'])) {
            $fields['postcodeZip'] = substr($address['postcode'], 0, 10);
        }
        if (!empty($address['zone'])) {
            $fields['stateProvince'] = substr($address['zone'], 0, 20);
        }
        if (!empty($address['address_1'])) {
            $fields['street'] = substr($address['address_1'], 0, 100);
        }
        if (!empty($address['address_2'])) {
            $fields['street2'] = substr($address['address_2'], 0, 100);
        }
    
        return $fields;
    }
    
    /**
     * @return array
     */
    protected function getShippingAddress() {
        $shippingAddress = [];
    
        if (!isset($this->session->data['shipping_address'])) {
            return $shippingAddress;
        }
    
        $data = $this->session->data['shipping_address'];
    
        $map = [
            'city'       => ['address.city', 100],
            'company'    => ['address.company', null],
            'iso_code_3' => ['address.country', null],
            'postcode'   => ['address.postcodeZip', 10],
            'zone'       => ['address.stateProvince', 20],
            'address_1'  => ['address.street', 100],
            'address_2'  => ['address.street2', 100],
            'firstname'  => ['contact.firstName', 50],
            'lastname'   => ['contact.lastName', 50],
        ];
    
        foreach ($map as $key => [$targetPath, $maxLength]) {
            if (!empty($data[$key])) {
                $value = $maxLength ? substr($data[$key], 0, $maxLength) : $data[$key];
                $this->setNestedValue($shippingAddress, $targetPath, $value);
            }
        }
    
        if ($this->customer->isLogged()) {
            $this->load->model(self::ACCOUNT_CUSTOMER_MODEL_PATH);
            $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());
            $shippingAddress['contact']['email'] = $customerModel['email'];
        } else {
            $orderId = (int)$this->session->data['order_id'];
            $query = $this->db->query("SELECT * FROM `oc_order` WHERE `order_id` = $orderId");
            $shippingAddress['contact']['email'] = $query->row['email'] ?? '';
        }
    
        return $shippingAddress;
    }
    
    protected function setNestedValue(array &$array, string $path, $value) {
        $keys = explode('.', $path);
        $ref = &$array;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;
    }
    
    /**
     * @return array
     */
    protected function getCustomer() {
       if ($this->customer->isLogged()) {
            $this->load->model(self::ACCOUNT_CUSTOMER_MODEL_PATH);
            $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());
            $customerData['firstName'] = substr($customerModel['firstname'], 0, 50);
            $customerData['lastName'] = substr($customerModel['lastname'], 0, 50);
            $customerData['email'] = $customerModel['email'];
       } else {
            $orderId = $this->session->data['order_id'];
            $query = $this->db->query("SELECT * FROM `oc_order` WHERE `order_id` = $orderId");
            $shippingData = $query->row;
            $customerData['firstName'] = substr($shippingData['firstname'], 0, 50);
            $customerData['lastName'] = substr($shippingData['lastname'], 0, 50);
            $customerData['email'] = $shippingData['email'];
        }

        return $customerData;
            
    }

    /**
     * Process Hosted Checkout Payment Method
     */
    public function processHostedCheckout() {
        setcookie("OCSESSID", "", time() - 1, "/");
        $this->load->language(self::MASTER_CARD_MODEL_PATH);
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
        $this->document->addScript('view/javascript/mastercard/custom.js');
        
        $requestIndicator = $this->request->get['resultIndicator'];
        $mgpsSuccessIndicator = $this->request->cookie['mgps_sucesss_indicator'] ?? '';
    
        if (isset($this->request->cookie['mgps_order']) &&
        isset($this->request->cookie['mgps_sucesss_indicator'])) {
            $mgpsSuccessIndicator = $this->request->cookie['mgps_sucesss_indicator'];
            $orderId = $this->request->cookie['mgps_order'];
            $ocessid = $this->request->cookie['mgps_OCSESSID'];
            $ocOrderId = $this->request->cookie['order_id'];
            $this->session->data['mgps_order_id'] = $orderId;
            $this->session->data['order_id'] = $ocOrderId;
            setcookie('OCSESSID', $ocessid, time() + 24 * 3600, '/');
            setcookie('mgps_order', '', time() - 3600, '/');
            setcookie('mgps_sucesss_indicator', '', time() - 3600, '/');
        }
        
        try {
            if ($mgpsSuccessIndicator !== $requestIndicator) {
                throw new \MasterCardPaymentException($this->language->get('error_indicator_mismatch'));
            }
            
            $retrievedOrder = $this->retrieveOrder($orderId);
            $voidtxnId = '';
            foreach ($retrievedOrder['transaction'] as $txn) {
                if ($txn['transaction']['type'] === 'AUTHORIZATION' && $txn['result'] == 'SUCCESS') {
                    $voidtxnId = $txn['transaction']['id'];
                }
            }
            
            if ($retrievedOrder['result'] !== 'SUCCESS') {
                throw new \MasterCardPaymentException($this->language->get('error_payment_declined'));
            }
            
            $txns = $retrievedOrder['transaction'];
            
            if( $txns ) {
				foreach ( $txns as $txn ) {
					if ( isset( $txn['transaction']['authorizationCode'] ) ) {
						$transaction['transaction']['authorizationCode'] = $txn['transaction']['authorizationCode'];
					} else {
						$transaction['transaction']['id']        = $txn['transaction']['id'];
						$transaction['transaction']['reference'] = $txn['transaction']['reference'];
					}
				}
			}
            $transactionId = $transaction['transaction']['id'];
            $transactionAmount = $txn['transaction']['amount'];
            $transactionStatus = $txn['order']['status'];
            $transactionOrderID = $txn['order']['id'];
            $merchantName = $txn['merchant'];
            $merchantId = $txn['transaction']['acquirer']['merchantId'];
            $customerEmail = $txn['customer']['email'];
            $firstName = $txn['customer']['firstName'] ?? $retrievedOrder['shipping']['contact']['firstName'] ?? '';
            $lastName = $txn['customer']['lastName'] ?? $retrievedOrder['shipping']['contact']['lastName'] ?? '';
            $customerName = trim($firstName . ' ' . $lastName);
            
            $this->processOrder($retrievedOrder,$txn,$transactionId);
            $this->db->query(
                "INSERT INTO " . DB_PREFIX . "mgps_order_transaction
                SET order_id = '" . $this->session->data['order_id'] . "',
                    oc_order_id = '" . $transactionOrderID . "',
                    transaction_id = '" . $transactionId . "',
                    void_transaction_id = '" . $voidtxnId . "',
                    type = '" . $transactionStatus . "',
                    merchant_name = '" . $merchantName . "',
                    merchant_id = '" . $merchantId . "',
                    status = '" . $transactionStatus . "',
                    amount = '" . $transactionAmount . "',
                    date_added = NOW()"
            );
            
            if ($this->config->get('config_mail_engine')) {
                $this->sendCustomEmail($orderId, $customerEmail, $transactionStatus, $customerName);
            }
            
            $this->cart->clear();
            $this->clearTokenSaveCardSessionData();
            $this->model_extension_mastercard_payment_mastercard->clearCheckoutSession();
            echo "<script>
                sessionStorage.clear();
                window.location.href = '" . $this->url->link('checkout/success', '', true) . "';
            </script>";
            
        } catch (\Exception $e) {
            $this->session->data['error'] = $e->getMessage();
            $this->addOrderHistory($orderId, self::ORDER_FAILED, $e->getMessage());
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }
    

    private function sendCustomEmail($orderId, $recieverAddress, $subject, $customerName) {
        $data['order_id'] = $orderId;
        $data['receiver_address'] = $recieverAddress;
        $data['order_status'] = $subject;
        $data['customer_name'] = $customerName;
    
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
            $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mailOption);
            $mail->setTo($recieverAddress);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender($this->config->get('config_name'));
            $mail->setSubject(
                html_entity_decode(
                    sprintf(
                        "Payment %s",
                        ucwords(strtolower(str_replace('_', ' ', $subject)))
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
            $mail->setHtml($this->load->view('extension/mastercard/payment/mgps_hosted_authorize_mail', $data));
            $mail->send();
        }
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
        $https = $headers['HTTPS'];
        $serverPort = $headers['SERVER_PORT'];
        return (!empty($https) && $https === "1") || $serverPort === "443";
    }

    /**
     * @param $customerId
     * @return array
     */
    public function getTokenizeCards($customerId) {
        $this->load->language(self::MASTER_CARD_MODEL_PATH);
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
        $customerTokens = $this->model_extension_mastercard_payment_mastercard->getCustomerTokens($customerId);
        $uri = $this->model_extension_mastercard_payment_mastercard->getApiUri() . '/token/';
        $cards = [];
        foreach ($customerTokens as $token) {
            $requestUri = $uri . urlencode($token['token']);
            $response = $this->model_extension_mastercard_payment_mastercard->apiRequest('GET', $requestUri);
            if ($response['result'] !== 'SUCCESS' || $response['status'] !== 'VALID') {
                $query = "DELETE FROM `" . DB_PREFIX . "mpgs_hpf_token`
                    WHERE hpf_token_id='" . (int)$token['hpf_token_id'] . "'";
                $this->db->query($query);
            } else {
                $expiry = [];
                $cardNumber = substr($response['sourceOfFunds']['provided']['card']['number'], - 4);
                preg_match( '/^(\d{2})(\d{2})$/', $response['sourceOfFunds']['provided']['card']['expiry'], $expiry);
                $cards[] = [
                    'id'    => (int) $token['hpf_token_id'],
                    'type'  => sprintf(
                        $this->language->get('text_card_type'),
                        ucfirst(strtolower($response['sourceOfFunds']['provided']['card']['brand']))
                    ),
                    'label' => sprintf(
                        $this->language->get('text_card_label'),
                        $cardNumber
                    ),
                    'expiry' => sprintf(
                        $this->language->get('text_card_expiry'),
                        $expiry[1] . '/' . $expiry[2]
                    ),
                ];
            }
        }

        return $cards;
    }

    protected function getTokenById($tokenId) {
        $sql  = "SELECT token FROM `" . DB_PREFIX . "mpgs_hpf_token` ";
        $sql .= "WHERE hpf_token_id = '" . (int)$tokenId . "'";
        $tokensResult = $this->db->query($sql);
        return $tokensResult->row;
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
        $ocessid = $this->request->cookie['mgps_OCSESSID'] ?? '';
        setcookie('OCSESSID', $ocessid, time() + 24 * 3600, '/');
    }
    
    /**
     * Cancel callback
     */
    public function errorCallback() {
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    /**
     * @param $retrievedOrder
     * @param $txn
     * @throws Exception
     */
    protected function processOrder($retrievedOrder, $txn ,$transactionId) {
        $status = strtoupper(trim($retrievedOrder['status']));
        $firstKey = array_key_first($txn);
        $authCode = isset($txn['transaction']['authorizationCode']) ? $txn['transaction']['authorizationCode'] : '';
        if ($status === 'CAPTURED') {
            if ($authCode !== '') {
                $message = sprintf($this->language->get('text_payment_captured'), $transactionId, $authCode);
            } else {
                $message = sprintf($this->language->get('text_payment_captured_no_auth'), $transactionId);
            }
            $orderStatusId = $this->config->get('payment_mastercard_approved_status_id');
        } elseif ($status === 'AUTHORIZED') {
            if ($authCode !== '') {
                $message = sprintf($this->language->get('text_payment_authorized'), $transactionId, $authCode);
            } else {
                $message = sprintf($this->language->get('text_payment_authorized_no_auth'), $transactionId);
            }
            $orderStatusId = $this->config->get('payment_mastercard_pending_status_id');
        } else {
            throw new MasterCardPaymentException(
                $this->language->get('error_transaction_unsuccessful')
            );
        }
    
        $isPayPal = isset($retrievedOrder['sourceOfFunds']['type']) && strtoupper($retrievedOrder['sourceOfFunds']['type']) === 'PAYPAL';
    
        $order_id = (int)$this->session->data['order_id'];

        if ($isPayPal) {
            // Check if an order_history record exists
            $query = $this->db->query("
                SELECT `order_history_id` 
                FROM `" . DB_PREFIX . "order_history`
                WHERE `order_id` = '" . $order_id . "'
                ORDER BY `date_added` DESC
                LIMIT 1
            ");

            if ($query->num_rows) {
                // Update the most recent order_history record
                $order_history_id = (int)$query->row['order_history_id'];

                $this->db->query("
                    UPDATE `" . DB_PREFIX . "order_history`
                    SET 
                        `order_status_id` = '" . (int)$orderStatusId . "',
                        `comment` = '" . $this->db->escape($message) . "'
                    WHERE `order_history_id` = '" . $order_history_id . "'
                ");
            } else {
                // No history exists, insert a new one
                $this->addOrderHistory($order_id, $orderStatusId, $message);
            }

            // Always update the order status in the main `order` table
            $this->db->query("
                UPDATE `" . DB_PREFIX . "order` 
                SET `order_status_id` = '" . (int)$orderStatusId . "' 
                WHERE `order_id` = '" . $order_id . "'
            ");
        } else {
            $this->addOrderHistory($order_id, $orderStatusId, $message);
        }

    }
    
    
    /**
     * @param $orderId
     * @param $orderStatusId
     * @param $message
     */
    protected function addOrderHistory($orderId, $orderStatusId, $message) {
        $this->load->model('checkout/order');
        $this->model_checkout_order->addHistory($orderId, $orderStatusId, $message);
    }

    /**
     * @param $orderId
     * @return mixed
     */
    protected function retrieveOrder($orderId) {
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
        $apiUri = $this->model_extension_mastercard_payment_mastercard->getApiUri();
        $uri = $apiUri . '/order/' . $orderId;
        return $this->model_extension_mastercard_payment_mastercard->apiRequest('GET', $uri);
    }
    

    /**
     * @param $orderId
     * @param $txnId
     * @return mixed
     */
    protected function retrieveTransaction($orderId, $txnId) {
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
        return $this->model_extension_mastercard_payment_mastercard->apiRequest(
            'GET',
            $this->model_extension_mastercard_payment_mastercard->getApiUri()
            . '/order/' . $orderId . '/transaction/' . $txnId
        );
    }
    
    /**
     * @return array
     */
    public function configureHostedCheckout() {
        $this->load->helper('utf8');
        $this->load->model(self::MASTER_CARD_MODEL_PATH);
        return [
            'merchant' => $this->model_extension_mastercard_payment_mastercard->getMerchantId(),
            'session' => [
                'id' => $this->session->data['mpgs_hosted_checkout']['session']['id'],
                'version' => $this->session->data['mpgs_hosted_checkout']['session']['version']
            ]
        ];
    }

    /**
     * @param $orderId
     * @return string
     */
    protected function getOrderPrefix($orderId) {
        $prefix = trim($this->config->get('payment_mastercard_order_id_prefix'));
        if (!empty($prefix)) {
            $orderId = $prefix . $orderId;
        }
        return $orderId;
    }
    
    public function getWebhookUrl() {
        return $this->url->link('extension/mastercard/payment/mastercard.webhook', '', 'SSL');
    }
}
