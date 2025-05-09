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
    const ORDER_CAPTURED = '15';
    const ORDER_VOIDED = '16';
    const ORDER_CANCELLED = '7';
    const ORDER_REFUNDED = '11';
    const ORDER_FAILED = '10';
    const HEADER_WEBHOOK_SECRET = 'HTTP_X_NOTIFICATION_SECRET';
    const HEADER_WEBHOOK_ATTEMPT = 'HTTP_X_NOTIFICATION_ATTEMPT';
    const HEADER_WEBHOOK_ID = 'HTTP_X_NOTIFICATION_ID';

    protected $orderAmount = 0;

    /**
     * @return mixed
     */
    public function index() {
        $this->load->language('extension/payment/mpgs_hosted_checkout');
        $this->load->model('extension/payment/mpgs_hosted_checkout');
    
        $gatewayUri = $this->model_extension_payment_mpgs_hosted_checkout->getGatewayUri();
        $apiVersion = $this->model_extension_payment_mpgs_hosted_checkout->getApiVersion();
        $integrationModel = $this->model_extension_payment_mpgs_hosted_checkout->getIntegrationModel();
    
        if (!empty($this->session->data['order_id']) && !empty($this->session->data['currency']) && !empty($this->session->data['payment_address'])) {
            try {
                if ($integrationModel === 'hostedcheckout') {
                    unset($this->session->data['HostedCheckout_sessionId']);
                  
                    $built = $this->buildCheckoutSession();
                    if ($built === true) {
                        $data['configured_variables'] = json_encode($this->configureHostedCheckout());
                    }
                }
            } catch (Exception $e) {
                $data['error_session'] = $e->getMessage();
            }
        }
    
        if (empty($data['error_session'])) {
            if ($integrationModel === 'hostedcheckout') {
                $cacheBust = (int)round(microtime(true));
                $data['hosted_checkout_js'] = $gatewayUri . 'static/checkout/checkout.min.js?_=' . $cacheBust;
                $data['checkout_interaction'] = $this->config->get('payment_mpgs_hosted_checkout_hc_type');
                $data['completeCallback'] = $this->url->link('extension/payment/mpgs_hosted_checkout/processHostedCheckout', '', true);
                $data['cancelCallback'] = $this->url->link('extension/payment/mpgs_hosted_checkout/cancelCallback', '', true);
                $data['errorCallback'] = $this->url->link('extension/payment/mpgs_hosted_checkout/errorCallback', '', true);
            }
        }
    
        if ($integrationModel === 'hostedcheckout') {
            if (isset($data['configured_variables'])) {
                $checkout_session_id = json_decode($data['configured_variables']);
                if ($checkout_session_id && isset($checkout_session_id->session, $checkout_session_id->merchant)) {
                    $data['session_id'] = $checkout_session_id->session->id;
                    $data['merchant'] = $checkout_session_id->merchant;
                    $data['version'] = isset($checkout_session_id->session->version) ? $checkout_session_id->session->version : null;
                    $data['mgps_order_id'] = $this->getOrderPrefix($this->session->data['order_id']);
                    $data['order_id'] = $this->session->data['order_id'];
    
                    if (isset($this->session->data['mpgs_hosted_checkout']['successIndicator'])) {
                        $data['success_indicator'] = $this->session->data['mpgs_hosted_checkout']['successIndicator'];
                    }
    
                    $data['OCSESSID'] = $_COOKIE['OCSESSID'];
                    $jsonData = json_encode($data);
                    $data['jsonData'] = $jsonData;
                    setcookie('OCSESSID', $data['OCSESSID'], time() + 24 * 3600, '/');
                    return $this->load->view('extension/payment/mpgs_hosted_checkout_template', $data);
                }
            }
        }
    }

    /**
     * @param $route
     */
    public function init($route)
    {
        $allowed = ['checkout/checkout'];

        if (!in_array($route, $allowed)) {
            return;
        }

        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $gatewayUri = $this->model_extension_payment_mpgs_hosted_checkout->getGatewayUri();
        $apiVersion = $this->model_extension_payment_mpgs_hosted_checkout->getApiVersion();
        $integrationModel = $this->model_extension_payment_mpgs_hosted_checkout->getIntegrationModel();
        $apiUsername = $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId();

        if ($integrationModel === 'hostedsession') {
            $hostedSessionJs = $gatewayUri . 'form/version/' . $apiVersion . '/merchant/' . $apiUsername . '/session.js';
            $this->document->addScript($hostedSessionJs);
            $threeDsApiVersion = $this->model_extension_payment_mpgs_hosted_checkout->threeDSApiVersion();
            $threeDsJS = $gatewayUri . 'static/threeDS/' . $threeDsApiVersion . '/three-ds.min.js';
            $this->document->addScript($threeDsJS);
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function buildCheckoutSession()
    {
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $this->model_extension_payment_mpgs_hosted_checkout->clearCheckoutSession();
        $order = $this->getOrder();
        $txnId = uniqid(sprintf('%s-', $order['id']));

        $requestData = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'partnerSolutionId' => $this->model_extension_payment_mpgs_hosted_checkout->buildPartnerSolutionId(),
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
        $requestData['order']['notificationUrl'] =$this->url->link('extension/mastercard/payment/mastercard.callback', '', true);

        if (!empty($this->getShippingAddress())) {
            $requestData = array_merge($requestData, ['shipping' => $this->getShippingAddress()]);
        }
        unset($this->session->data['HostedCheckout_sessionId']);

        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/session';
        $response = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('POST', $uri, $requestData);


        if (!empty($response['result']) && $response['result'] === 'SUCCESS') {
           
            if ($this->model_extension_payment_mpgs_hosted_checkout->getIntegrationModel() === 'hostedcheckout') {
                $this->session->data['mpgs_hosted_checkout'] = $response;
                if (isset($this->session->data['mpgs_hosted_checkout'])) {
                    $this->session->data['mgps_redirect_session'] = $this->session->data['mpgs_hosted_checkout'];
                }
            }
            
            return true;
        } elseif (!empty($response['result']) && $response['result'] === 'ERROR') {
            throw new \Exception(json_encode($response['error']));
        }

        return false;
    }

    /**
     * @return mixed
     */
    protected function getInteraction()
    {
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $integration['merchant']['name'] = $this->config->get('config_name');
        $integration['operation'] = $this->model_extension_payment_mpgs_hosted_checkout->getPaymentAction();
        $integration['returnUrl'] = $this->url->link('extension/payment/mpgs_hosted_checkout/processHostedCheckout', '', true);
        $integration['displayControl']['shipping'] = 'HIDE';
        $integration['displayControl']['billingAddress'] = 'HIDE';
        $integration['displayControl']['customerEmail'] = 'HIDE';

        return $integration;
    }

    /**
     * @return mixed
     */
    protected function getOrder()
    {
        $orderId = $this->getOrderPrefix($this->session->data['order_id']);
        $orderData['id'] = $orderId;
        $orderData['reference'] = $orderId;
        $orderData['currency'] = $this->session->data['currency'];
        $orderData['description'] = 'Ordered goods';
        $orderData['notificationUrl'] = $this->url->link('extension/payment/mpgs_hosted_checkout/callback', '', true);

        return $orderData;
    }

    /**
     * Order items, tax and order totals
     *
     * @return array
     */
    protected function getOrderItemsTaxAndTotals()
    {
        $orderData = [];
        $sendLineItems = $this->config->get('payment_mpgs_hosted_checkout_send_line_items');
        if ($sendLineItems) {
            $this->load->model('catalog/product');
            foreach ($this->cart->getProducts() as $product) {
                $productModel = $this->model_catalog_product->getProduct($product['product_id']);

                $items = [];
                if ($productModel['manufacturer']) {
                    $items['brand'] = utf8_substr($productModel['manufacturer'], 0, 127);
                }

                $description = [];
                foreach ($product['option'] as $option) {
                    if ($option['type'] != 'file') {
                        $value = isset($option['value']) ? $option['value'] : '';
                    } else {
                        $uploadInfo = $this->model_tool_upload->getUploadByCode($option['value']);

                        if ($uploadInfo) {
                            $value = $uploadInfo['name'];
                        } else {
                            $value = '';
                        }
                    }
                    $description[] = $option['name'] . ':' . (utf8_strlen($value) > 20 ? utf8_substr($value, 0,
                                20) . '..' : $value);
                }
                if (!empty($description)) {
                    $items['description'] = utf8_substr(implode(', ', $description), 0, 127);
                } elseif ($product['model']) {
                    $items['description'] = utf8_substr($product['model'], 0, 127);
                }
                $items['name'] = utf8_substr($product['name'], 0, 127);
                $items['quantity'] = $product['quantity'];
                if ($product['model']) {
                    $items['sku'] = utf8_substr($product['model'], 0, 127);
                }
                $items['unitPrice'] = round($product['price'], 2);

                $orderData['item'][] = $items;
            }
        }

        /** Tax, Shipping, Discount and Order Total */
        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep var references so we put them into an array.
        $totalData = [
            'totals' => &$totals,
            'taxes' => &$taxes,
            'total' => &$total
        ];

        $this->load->model('setting/extension');

        // Display prices
        $sorOrder = [];
        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
            $sorOrder[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sorOrder, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);

                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_total_' . $result['code']}->getTotal($totalData);
            }

            $sorOrder = [];
            foreach ($totals as $key => $value) {
                $sorOrder[$key] = $value['sort_order'];
            }

            array_multisort($sorOrder, SORT_ASC, $totals);
        }

        $skipTotals = [
            'sub_total',
            'total',
            'tax'
        ];

        $formattedTotal = round($total, 2);
        $subTotal = 0;
        $tax = 0;
        $taxInfo = [];
        $shipping = 0;

        foreach ($totals as $key => $value) {
            $formattedValue = round($value['value'], 2);

            if ($value['code'] == 'sub_total') {
                $subTotal += $formattedValue;
            }

            if ($value['code'] == 'tax') {
                $tax += $formattedValue;
                $taxInfo[] = [
                    'amount' => $formattedValue,
                    'type' => $value['title']
                ];
            }

            if (!in_array($value['code'], $skipTotals)) {
                $shipping += $formattedValue;
            }
        }

        $finalTotal = $subTotal + $tax + $shipping;
        if ($finalTotal == $formattedTotal) {
            $this->orderAmount = $formattedTotal;
            $orderData['amount'] = $formattedTotal;
            if ($sendLineItems) {
                $orderData['itemAmount'] = $subTotal;
                $orderData['shippingAndHandlingAmount'] = $shipping;
                $orderData['taxAmount'] = $tax;
            }
        }

        /** Order Tax Details */
        if (!empty($taxInfo) && $sendLineItems) {
            $orderData['tax'] = $taxInfo;
        }

        return $orderData;
    }

    /**
     * @return array
     */
    protected function getBillingAddress()
    {
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
    protected function getShippingAddress()
    {
        $shippingAddress = [];
        if (isset($this->session->data['shipping_address'])) {
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

            if ($this->customer->isLogged()) {
                $this->load->model('account/customer');

                $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());

                $shippingAddress['contact']['email'] = $customerModel['email'];
                $shippingAddress['contact']['phone'] = utf8_substr($customerModel['telephone'], 0, 20);

            } else {
                $guestUser = $this->session->data['guest'];

                $shippingAddress['contact']['email'] = $guestUser['email'];
                $shippingAddress['contact']['phone'] = utf8_substr($guestUser['telephone'], 0, 20);

            }
        }

        return $shippingAddress;
    }

    /**
     * @return array
     */
    protected function getCustomer()
    {
        $customerData = [];
        if ($this->customer->isLogged()) {
            $this->load->model('account/customer');

            $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());

            $customerData['firstName'] = utf8_substr($customerModel['firstname'], 0, 50);
            $customerData['lastName'] = utf8_substr($customerModel['lastname'], 0, 50);
            $customerData['email'] = $customerModel['email'];
            $customerData['phone'] = utf8_substr($customerModel['telephone'], 0, 20);
        } elseif (isset($this->session->data['guest'])) {
            $guestUser = $this->session->data['guest'];

            $customerData['firstName'] = utf8_substr($guestUser['firstname'], 0, 50);
            $customerData['lastName'] = utf8_substr($guestUser['lastname'], 0, 50);
            $customerData['email'] = $guestUser['email'];
            $customerData['phone'] = utf8_substr($guestUser['telephone'], 0, 20);
        }

        return $customerData;
    }

    /**
     * Process Hosted Checkout Payment Method
     */
    public function processHostedCheckout()
    {
        
        $this->load->language('extension/payment/mpgs_hosted_checkout');
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $requestIndicator = $this->request->get['resultIndicator'];
        $mpgsSuccessIndicator = $this->session->data['mpgs_hosted_checkout']['successIndicator'];
        $orderId = $this->getOrderPrefix($this->session->data['order_id']);

    
        try {
            if ($mpgsSuccessIndicator !== $requestIndicator) {
                throw new Exception($this->language->get('error_indicator_mismatch'));
            }

          

            $retrievedOrder = $this->retrieveOrder($orderId);
           
            if ($retrievedOrder['result'] !== 'SUCCESS') {
                throw new Exception($this->language->get('error_payment_declined'));
            }

           

            $txn = $retrievedOrder['transaction'][0];
            $transactionId = isset($txn['authentication']['3ds']['transactionId']) ? $txn['authentication']['3ds']['transactionId'] : $txn['transaction']['id'];
            $transactionAmount    =  $txn['transaction']['amount'];
            $transactionCurrency  = $txn['transaction']['currency'];
            $transactionStatus    = $txn['order']['status'];
            $transactionOrderID   = $txn['order']['id'];
            $merchantName         = $txn['merchant'];
            $merchantId           = $txn['transaction']['acquirer']['merchantId'];
            $customer_email       = $txn['customer']['email'];
            $customer_name        = $txn['customer']['firstName'] . ' ' . $txn['customer']['lastName'];
            $email_status         = "Payment" . '' . $transactionStatus ;
            $transactionType      = "";

           
            $this->processOrder($retrievedOrder, $txn);
           
            $this->db->query("INSERT INTO ".DB_PREFIX."mgps_order_transaction SET order_id ='".$this->session->data['order_id'] ."', oc_order_id ='".$transactionOrderID ."', transaction_id = '".$transactionId."', type = '".$transactionStatus."', merchant_name = '".$merchantName."', merchant_id = '".$merchantId."' ,status = '".$transactionStatus ."', amount = '".$transactionAmount."', date_added = NOW()");
           
            if ($this->config->get('config_mail_engine')) {
                $this->sendCustomEmail($orderId, $customer_email,$transactionStatus, $customer_name );
            }
            $this->cart->clear();
            $this->clearTokenSaveCardSessionData();
            $this->model_extension_payment_mpgs_hosted_checkout->clearCheckoutSession();
            $this->response->redirect($this->url->link('checkout/success', '', true));
        } catch (Exception $e) {
            $this->session->data['error'] = $e->getMessage();
            $this->addOrderHistory($orderId, self::ORDER_FAILED, $e->getMessage());
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    public function callback()
    {
        $this->load->language('extension/payment/mpgs_hosted_checkout');
        $this->load->model('extension/payment/mpgs_hosted_checkout');
        $requestHeaders = $this->request->server;

        $webhookSecret = $requestHeaders[self::HEADER_WEBHOOK_SECRET];
        $webhookAttempt = $requestHeaders[self::HEADER_WEBHOOK_ATTEMPT];
        $webhookId = $requestHeaders[self::HEADER_WEBHOOK_ID];

        $content = file_get_contents('php://input');
        $content = trim($content);

        $parsedData = @json_decode($content, true);

        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            $this->model_extension_payment_mpgs_hosted_checkout->log('Could not parse response JSON, error: '. $jsonError, json_encode(['rawContent' => $content]));
            header('HTTP/1.1 500 ' . $jsonError);
            exit;
        }

        try {
            if ($requestHeaders['REQUEST_METHOD'] != 'POST') {
                throw new Exception($this->language->get('error_request_method'));
            }

            if (!$this->isSecure($requestHeaders)) {
                throw new Exception($this->language->get('error_insecure_connection'));
            }

            if ($this->model_extension_payment_mpgs_hosted_checkout->getWebhookSecret() !== $webhookSecret) {
                throw new Exception($this->language->get('error_secret_mismatch'));
            }

            if ($this->model_extension_payment_mpgs_hosted_checkout->getMerchantId() !== $parsedData['merchant']) {
                throw new Exception($this->language->get('error_merchant_mismatch'));
            }

            if (!isset($parsedData['order']) || !isset($parsedData['order']['id'])) {
                throw new Exception($this->language->get('error_invalid_order'));
            }

            if (!isset($parsedData['transaction']) || !isset($parsedData['transaction']['id'])) {
                throw new Exception($this->language->get('error_invalid_transaction'));
            }

        } catch (Exception $e) {
            $errorMessage = sprintf("WebHook Exception: '%s'", $e->getMessage());
            $this->model_extension_payment_mpgs_hosted_checkout->log($errorMessage);
            header('HTTP/1.1 500 ' . $e->getMessage());
            exit;
        }

        $webhookResponse = json_encode([
            'notification_id' => $webhookId,
            'notification_attempt' => $webhookAttempt,
            'order.id' => $parsedData['order']['id'],
            'transaction.id' => $parsedData['transaction']['id'],
            'transaction.type' => $parsedData['transaction']['type'],
            'response.gatewayCode' => $parsedData['response']['gatewayCode']
        ]);
        $this->model_extension_payment_mpgs_hosted_checkout->log("Webhook Response: " . $webhookResponse);

        try {
            $response = $this->retrieveTransaction($parsedData['order']['id'], $parsedData['transaction']['id']);

            if (isset($response['result']) && $response['result'] == 'ERROR') {
                $error = $this->language->get('error_payment_declined');
                if (isset($response['error']['explanation'])) {
                    $error = sprintf('%s: %s', $response['error']['cause'], $response['error']['explanation']);
                }
                throw new Exception($error);
            }
        } catch (Exception $e) {
            $this->model_extension_payment_mpgs_hosted_checkout->log('Gateway Error: ' . $e->getMessage());
            header('HTTP/1.1 500 ' . 'Gateway Error');
            exit;
        }

        if (!$this->isApproved($response)) {
            $this->model_extension_payment_mpgs_hosted_checkout->log(sprintf('Unexpected gateway code "%s"', $response['response']['gatewayCode']));
            exit;
        }

        $mpgsOrderId = $response['order']['id'];
        $prefix = trim($this->config->get('payment_mpgs_hosted_checkout_order_id_prefix'));
        if ($prefix) {
            $mpgsOrderId = substr($mpgsOrderId, strlen($prefix));
        }

        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($mpgsOrderId);

        if (isset($response['risk']['response'])) {
            $risk = $response['risk']['response'];
            switch ($risk['gatewayCode']) {
                case 'REJECTED':
                    if ($order['order_status_id'] != $this->config->get('payment_mpgs_hosted_checkout_risk_declined_status_id')) {
                        $message = sprintf($this->language->get('text_risk_review_rejected'), $risk['gatewayCode'], $response['transaction']['id'], $response['transaction']['type']);
                        $this->addOrderHistory($response['order']['reference'], $this->config->get('payment_mpgs_hosted_checkout_risk_declined_status_id'), $message);
                    }
                    break;
                case 'REVIEW_REQUIRED':
                    if (!empty($risk['review']['decision']) && in_array($risk['review']['decision'], ['NOT_REQUIRED', 'ACCEPTED'])) {
                        $this->setOrderHistoryTransactionType($order, $response);
                    } else {
                        $message = sprintf($this->language->get('text_risk_review_required'), $risk['gatewayCode'], $response['transaction']['id'], $response['transaction']['type']);
                        $this->addOrderHistory($response['order']['reference'], $this->config->get('payment_mpgs_hosted_checkout_risk_review_status_id'), $message);
                    }
                    break;
                default:
                    $this->setOrderHistoryTransactionType($order, $response);
                    break;
            }

            $this->model_extension_payment_mpgs_hosted_checkout->log('webhook completed (200 OK)');
            exit;
        }
    }

    /**
     * @param $order
     * @param $response
     */
    protected function setOrderHistoryTransactionType($order, $response)
    {
        switch ($response['transaction']['type']) {
            case 'AUTHORIZATION':
            case 'AUTHORIZATION_UPDATE':
                if ($order['order_status_id'] != $this->config->get('payment_mpgs_hosted_checkout_pending_status_id')) {
                    $this->model_extension_payment_mpgs_hosted_checkout->log(sprintf($this->language->get('text_not_allow_authorization'), $order['order_status_id']));
                } else {
                    $message = sprintf($this->language->get('text_webhook_authorize_capture'), $response['transaction']['type'], $response['result'], $response['transaction']['id'], $response['transaction']['authorizationCode']);
                    $orderStatusId = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id');
                    $this->addOrderHistory($order['order_id'], $orderStatusId, $message);
                }
                break;

            case 'PAYMENT':
            case 'CAPTURE':
                if ($order['order_status_id'] != $this->config->get('payment_mpgs_hosted_checkout_approved_status_id') && $order['order_status_id'] != $this->config->get('payment_mpgs_hosted_checkout_pending_status_id')) {
                    $this->model_extension_payment_mpgs_hosted_checkout->log(sprintf($this->language->get('text_not_allow_capture'), $order['order_status']));
                } else {
                    $message = sprintf($this->language->get('text_webhook_authorize_capture'), $response['transaction']['type'], $response['result'], $response['transaction']['id'], $response['transaction']['authorizationCode']);
                    $orderStatusId = self::ORDER_CAPTURED;
                    $this->addOrderHistory($order['order_id'], $orderStatusId, $message);
                }
                break;

            case 'REFUND_REQUEST':
            case 'REFUND':
                if ($order['order_status_id'] != self::ORDER_CAPTURED) {
                    $this->model_extension_payment_mpgs_hosted_checkout->log(sprintf($this->language->get('text_not_allow_refund'), $order['order_status']));
                } else {
                    $message = sprintf($this->language->get('text_webhook_refund_void'), $response['transaction']['type'], $response['result'], $response['transaction']['id']);
                    $orderStatusId = self::ORDER_REFUNDED;
                    $this->addOrderHistory($order['order_id'], $orderStatusId, $message);
                }
                break;

            case 'VOID_AUTHORIZATION':
            case 'VOID_CAPTURE':
            case 'VOID_PAYMENT':
            case 'VOID_REFUND':
                if ($order['order_status_id'] != $this->config->get('payment_mpgs_hosted_checkout_approved_status_id')) {
                    $this->model_extension_payment_mpgs_hosted_checkout->log(sprintf($this->language->get('text_not_allow_void'), $order['order_status']));
                } else {
                    $message = sprintf($this->language->get('text_webhook_refund_void'), $response['transaction']['type'], $response['result'], $response['transaction']['id']);
                    $orderStatusId = self::ORDER_VOIDED;
                    $this->addOrderHistory($order['order_id'], $orderStatusId, $message);
                }
                break;

            case 'CANCELLED':
                if ($order['order_status_id'] != self::ORDER_CANCELLED) {
                    $message = sprintf($this->language->get('text_webhook_refund_void'), $response['transaction']['type'], $response['result'], $response['transaction']['id']);
                    $orderStatusId = self::ORDER_CANCELLED;
                    $this->addOrderHistory($order['order_id'], $orderStatusId, $message);

                }
                break;

            default:
                if ($order['order_status_id'] != self::ORDER_CANCELLED) {
                    $orderStatusId = self::ORDER_CANCELLED;
                    $message = sprintf($this->language->get('text_webhook_unknown'), $response['transaction']['type']);
                    $this->addOrderHistory($order['order_id'], $orderStatusId, $message);
                }
                break;
        }
    }

    /**
     * @param $response
     * @return bool
     */
    public function isApproved($response)
    {
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
    protected function isSecure($headers)
    {
        $https = $headers['HTTPS'];
        $serverPort = $headers['SERVER_PORT'];
        return (!empty($https) && $https === "1") || $serverPort === "443";
    }

    /**
     * @param $customerId
     * @return array
     */
    public function getTokenizeCards($customerId)
    {
        $this->load->language('extension/payment/mpgs_hosted_checkout');
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $customerTokens = $this->model_extension_payment_mpgs_hosted_checkout->getCustomerTokens($customerId);
        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/token/';

        $cards = [];

        foreach ($customerTokens as $token) {
            $response = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('GET', $uri . urlencode($token['token']));

            if ($response['result'] !== 'SUCCESS' || $response['status'] !== 'VALID') {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "mpgs_hpf_token` WHERE hpf_token_id='" . (int)$token['hpf_token_id'] . "'");
            } else {
                $expiry = [];
                $cardNumber = substr($response['sourceOfFunds']['provided']['card']['number'], - 4);
                preg_match( '/^(\d{2})(\d{2})$/', $response['sourceOfFunds']['provided']['card']['expiry'], $expiry);

                $cards[] = [
                    'id' => (int)$token['hpf_token_id'],
                    'type' => sprintf($this->language->get('text_card_type'), ucfirst(strtolower($response['sourceOfFunds']['provided']['card']['brand']))),
                    'label' => sprintf($this->language->get('text_card_label'), $cardNumber),
                    'expiry' => sprintf($this->language->get('text_card_expiry'), $expiry[1] . '/' . $expiry[2])
                ];
            }
        }

        return $cards;
    }

    protected function getTokenById($tokenId)
    {
        $tokensResult = $this->db->query("SELECT token FROM `" . DB_PREFIX . "mpgs_hpf_token` WHERE hpf_token_id='" . (int)$tokenId . "'");
        return $tokensResult->row;
    }

    public function createSession($orderId, $amount)
    {
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        // Create Session
        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/session';
        $session = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('POST', $uri);

        $txnId = uniqid(sprintf('%s-', $orderId));
        // Update Session
        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/session/' . $session['session']['id'];
        $updateSessionData = [
            'order' => [
                'amount' => $amount,
                'currency' => $this->session->data['currency'],
                'id' => $orderId,
                'reference' => $orderId,
            ],
            'partnerSolutionId' => $this->model_extension_payment_mpgs_hosted_checkout->buildPartnerSolutionId(),
            'authentication' => [
                'channel' => 'PAYER_BROWSER',
                'purpose' => 'PAYMENT_TRANSACTION',
                'redirectResponseUrl' => str_replace('&amp;', '&',
                    $this->url->link('extension/payment/mpgs_hosted_checkout/payerAuthComplete', [
                        'session_id' => $session['session']['id'],
                        'OCSESSID' => $this->session->getId()
                    ], true)
                )
            ],
            'transaction' => [
                'reference' => $txnId
            ],
            'billing' => $this->getBillingAddress(),
            'customer' => $this->getCustomer(),
        ];

        if (!empty($this->getShippingAddress())) {
            $updateSessionData = array_merge($updateSessionData, ['shipping' => $this->getShippingAddress()]);
        }

        $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('PUT', $uri, $updateSessionData);

        return $session;
    }

    /**
     * Up session from get parameter
     *
     * Known issue with MasterCard API
     */
    private function restartOCSession()
    {
        if (empty($this->request->get['OCSESSID'])) {
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

        (new ControllerStartupStartup($this->registry))->index();
    }

    /**
     * Controller action
     * Payer returns from 3DS1 or 3DS2 auth challenge
     */
    public function payerAuthComplete()
    {
        $this->restartOCSession();

        $this->load->language('extension/payment/mpgs_hosted_checkout');
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $txnId = $this->request->post['transaction_id'];
        $orderId = $this->request->post['order_id'];
        $gatewayRecommendation = $this->request->post['response_gatewayRecommendation'];
        $sessionId = $this->request->request['session_id'];

        try {
            if ($gatewayRecommendation !== 'PROCEED') {
                throw new Exception($this->language->get('error_payment_declined_3ds'));
            }

            $operation = 'AUTHORIZE';
            if ($this->model_extension_payment_mpgs_hosted_checkout->getPaymentAction() === 'PURCHASE') {
                $operation = 'PAY';
            }

            $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/order/' . $orderId . '/transaction/1';
            $operationData = [
                'apiOperation' => $operation,
                'session' => [
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
                    'reference' => $orderId,
                    'currency' => $this->session->data['currency'],
                    'notificationUrl' => $this->url->link('extension/payment/mpgs_hosted_checkout/callback', '', true),
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
                    $error = sprintf('%s: %s', $response['error']['cause'], $response['error']['explanation']);
                }
                throw new Exception($error);
            }

            $this->processOrder($response['order'], $response);

            $enabled = $this->model_extension_payment_mpgs_hosted_checkout->isSavedCardsEnabled();
            $payingWithToken = isset($this->session->data['token_id']) ? (bool)$this->session->data['token_id'] : false;
            if ($enabled && !$payingWithToken && isset($this->session->data['save_card']) && $this->session->data['save_card'] === '1') {
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
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    /**
     * Controller action
     * Process Hosted Session Payment Method
     */
    public function saveHostedSessionPayment()
    {
        $this->load->language('extension/payment/mpgs_hosted_checkout');
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        if (isset($this->request->request['mpgs-payment-token']) && $this->request->request['mpgs-payment-token'] !== 'new') {
            $tokenId = $this->request->request['mpgs-payment-token'];

            $this->session->data['token_id'] = $tokenId;
            $token = $this->getTokenById($tokenId);

            $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/session/' . $this->request->request['session_id'];
            $result = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('PUT', $uri, [
                'sourceOfFunds' => [
                    'token' => $token['token'],
                    'type' => 'CARD'
                ]
            ]);

            if (!isset($result['session']) || $result['session']['updateStatus'] !== 'SUCCESS') {
                header('HTTP/1.0 500 Session Update Error');
                exit();
            }
        }
        if (isset($this->request->request['mpgs-save-new-method']) && $this->request->request['mpgs-save-new-method'] === '1') {
            $this->session->data['save_card'] = $this->request->request['mpgs-save-new-method'];
        }
    }

    /**
     * Clear values of Hosted Payment Form
     * fields from session
     */
    protected function clearTokenSaveCardSessionData()
    {
        unset($this->session->data['save_card']);
        unset($this->session->data['token_id']);
        unset($this->session->data['source_of_funds']);
    }

    /**
     * @param $session
     * @throws Exception
     */
    protected function saveCards($session)
    {
        $tokenResponse = $this->createCardToken($session['id']);

        if (!isset($tokenResponse['token']) || empty($tokenResponse['token'])) {
            throw new Exception($this->language->get('error_token_not_present'));
        }

        if ($this->customer->isLogged()) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "mpgs_hpf_token` SET `customer_id`='" . (int)$this->customer->getId() . "', `token`='" . $this->db->escape($tokenResponse['token']) . "', `created_at`=NOW()");
        }
    }

    /**
     * @param $sessionId
     * @return mixed
     */
    protected function createCardToken($sessionId)
    {
        $requestData = [
            'session' => [
                'id' => $sessionId
            ],
            'sourceOfFunds' => [
                'type' => 'CARD'
            ]
        ];

        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/token';
        $response = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('POST', $uri, $requestData);

        return $response;
    }

    /**
     * Cancel callback
     */
    public function cancelCallback()
    {
        $this->addOrderHistory($this->session->data['order_id'], self::ORDER_CANCELLED, 'MasterCard Payment transaction has been CANCELLED by customer.');
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    /**
     * @param $retrievedOrder
     * @param $txn
     * @throws Exception
     */
    protected function processOrder($retrievedOrder, $txn)
    {
        if ($retrievedOrder['status'] === 'CAPTURED') {
            $message = sprintf($this->language->get('text_payment_captured'), $txn['transaction']['id'], isset($txn['transaction']['authorizationCode']) ? $txn['transaction']['authorizationCode'] : '');
            $orderStatusId = self::ORDER_CAPTURED;
        } elseif ($retrievedOrder['status'] === 'AUTHORIZED') {
            $message = sprintf($this->language->get('text_payment_authorized'), $txn['transaction']['id'], isset($txn['transaction']['authorizationCode']) ? $txn['transaction']['authorizationCode'] : '');
            $orderStatusId = $this->config->get('payment_mpgs_hosted_checkout_approved_status_id');
        } else {
            throw new Exception($this->language->get('error_transaction_unsuccessful'));
        }
        $this->addOrderHistory($this->session->data['order_id'], $orderStatusId, $message);
    }

    /**
     * @param $orderId
     * @param $orderStatusId
     * @param $message
     */
    protected function addOrderHistory($orderId, $orderStatusId, $message)
    {
       
        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory($orderId, $orderStatusId, $message);
        
    }

    /**
     * @param $orderId
     * @return mixed
     */
    protected function retrieveOrder($orderId)
    {
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/order/' . $orderId;

        $response = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('GET', $uri);
        return $response;
    }

    /**
     * @param $orderId
     * @param $txnId
     * @return mixed
     */
    protected function retrieveTransaction($orderId, $txnId)
    {
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $uri = $this->model_extension_payment_mpgs_hosted_checkout->getApiUri() . '/order/' . $orderId . '/transaction/' . $txnId;

        $response = $this->model_extension_payment_mpgs_hosted_checkout->apiRequest('GET', $uri);
        return $response;
    }

    /**
     * @return array
     */
    public function configureHostedCheckout()
    {
        $this->load->helper('utf8');
        $this->load->model('extension/payment/mpgs_hosted_checkout');

        $params = [
            'merchant' => $this->model_extension_payment_mpgs_hosted_checkout->getMerchantId(),
            'session' => [
                'id' => $this->session->data['mpgs_hosted_checkout']['session']['id'],
                'version' => $this->session->data['mpgs_hosted_checkout']['session']['version']
            ]
        ];

        return $params;
    }

    /**
     * @param $orderId
     * @return string
     */
    protected function getOrderPrefix($orderId){
        $prefix = trim($this->config->get('payment_mpgs_hosted_checkout_order_id_prefix'));
        if (!empty($prefix)) {
            $orderId = $prefix . $orderId;
        }
        return $orderId;
    }

    private function sendCustomEmail($orderId, $receiver_address, $subject, $customer_name) {
        $data['order_id'] = $orderId;
        $data['receiver_address'] = $receiver_address;
        $data['order_status'] = $subject;
        $data['customer_name'] = $customer_name;
        $store_name = $this->config->get('config_name');
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
		$mail->setHtml($this->load->view('extension/payment/mpgs_email', $data));
		$mail->send();
    }
    
}
