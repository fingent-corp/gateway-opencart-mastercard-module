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

namespace Opencart\Catalog\Controller\Extension\Mastercard\Payment;

class Mastercard extends \Opencart\System\Engine\Controller{
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
    public function index(){
        $this->load->language('extension/mastercard/payment/mastercard');
        $this->load->model('extension/mastercard/payment/mastercard');
        $gatewayUri = $this->model_extension_mastercard_payment_mastercard->getGatewayUri();
        $integrationModel = $this->model_extension_mastercard_payment_mastercard->getIntegrationModel();
        if (!empty($this->session->data['order_id']) && !empty($this->session->data['currency']) && !empty($this->session->data['shipping_address'])) {
            try {
                if ($integrationModel === 'hostedcheckout') {
                    unset($this->session->data['HostedCheckout_sessionId']);
                    $built = $this->buildCheckoutSession();
                    if ($built === true) {
                        $data['configured_variables'] = json_encode($this->configureHostedCheckout());
                    }
                } 
            } catch (\Exception $e) {
                $data['error_session'] = $e->getMessage();
            }
        }
        if (empty($data['error_session'])) {
            if ($integrationModel === 'hostedcheckout') { 

                $cacheBust = (int)round(microtime(true));               
                $data['hosted_checkout_js'] = $gatewayUri . 'static/checkout/checkout.min.js?_='.$cacheBust;
                $data['checkout_interaction'] = $this->config->get('payment_mastercard_hc_type');
                $data['completeCallback'] = $this->url->link('extension/mastercard/payment/mastercard.processHostedCheckout', '', false);
                $data['cancelCallback'] = $this->url->link('extension/mastercard/payment/mastercard.cancelCallback', '', true);
                $data['errorCallback'] = $this->url->link('extension/mastercard/payment/mastercard.errorCallback', '', true);   
                
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
                    $this->document->addScript($data['hosted_checkout_js']);
                    return $this->load->view('extension/mastercard/payment/mgps_hosted_checkout', $data);
                }
            }
        }           
    }
    
    /**
     * @param $route
     */
    public function init($route){
        $allowed = ['checkout/checkout'];
        if (!in_array($route, $allowed)) {
            return;
        }
        $this->load->model('extension/mastercard/payment/mastercard');
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function buildCheckoutSession(){
        $this->load->model('extension/mastercard/payment/mastercard');
        $this->model_extension_mastercard_payment_mastercard->clearCheckoutSession();
        $order = $this->getOrder();
        $txnId = uniqid(sprintf('%s-', $order['id']));
        $requestData = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'partnerSolutionId' => $this->model_extension_mastercard_payment_mastercard->buildPartnerSolutionId(),
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
            throw new \Exception(json_encode($response['error']));
        }
        return false;
    }

    /**
     * @return mixed
     */
    protected function getInteraction(){
        $this->load->model('extension/mastercard/payment/mastercard');
        $integration['merchant']['name'] = $this->config->get('config_name');
        $integration['operation'] = $this->model_extension_mastercard_payment_mastercard->getPaymentAction();
        $integration['returnUrl'] = $this->url->link('extension/mastercard/payment/mastercard.processHostedCheckout', '', true);
        $integration['displayControl']['shipping'] = 'HIDE';
        $integration['displayControl']['billingAddress'] = 'HIDE';
        $integration['displayControl']['customerEmail'] = 'HIDE';
        return $integration;
    }

    /**
     * @return mixed
     */
    protected function getOrder(){
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
    protected function getOrderItemsTaxAndTotals(){
    	    $this->load->helper('utf8');
            $orderData = [];
            $sendLineItems = $this->config->get('payment_mastercard_send_line_items');
            if ($sendLineItems) {
               
                $this->load->model('catalog/product');
                foreach ($this->cart->getProducts() as $product) {
                    $items = [];
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
                        $items['description'] = substr(implode(', ', $description), 0, 127);
                    } elseif ($product['model']) {
                        $items['description'] = substr($product['model'], 0, 127);
                    }
                    $items['name'] = substr($product['name'], 0, 127);
                    $items['quantity'] = $product['quantity'];
                    if ($product['model']) {
                        $items['sku'] = substr($product['model'], 0, 127);
                    }
                    $items['unitPrice'] = round($product['price'], 2);
    
                    $orderData['item'][] = $items;
                }
            }
            /** Tax, Shipping, Discount and Order Total */
            $totals = [];
            $taxes = $this->cart->getTaxes();
            $total = 0;
            $this->load->model('setting/extension');
    
            // Display prices
            $sort_order = [];
            $results = $this->model_setting_extension->getExtensionsByType('total');
            foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
			}
            array_multisort($sort_order, SORT_ASC, $results);
            foreach ($results as $result) {
				if ($this->config->get('total_' . $result['code'] . '_status')) {
					$this->load->model('extension/' . $result['extension'] . '/total/' . $result['code']);
					// __call can not pass-by-reference so we get PHP to call it as an anonymous function.
					($this->{'model_extension_' . $result['extension'] . '_total_' . $result['code']}->getTotal)($totals, $taxes, $total);
				}
			}

            $sort_order = [];
            foreach ($totals as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $totals);
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
    protected function getBillingAddress(){

        $this->load->model('account/customer');
		$this->load->model('account/address');
        $this->load->model('account/order');
        $billingAddress = [];
        if ($this->customer->isLogged() && $this->customer->getAddressId()) {
            if (VERSION >= '4.0.2.0') {
                $paymentAddress = $this->model_account_address->getAddress($this->customer->getId(),$this->customer->getAddressId());
                $this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getId(),$this->customer->getAddressId());
            } else {
                $this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
            }

            if (!empty($paymentAddress['city'])) {
                $billingAddress['address']['city'] = substr($paymentAddress['city'], 0, 100);
            }
    
            if (!empty($paymentAddress['company'])) {
                $billingAddress['address']['company'] = $paymentAddress['company'];
            }
    
            if (!empty($paymentAddress['iso_code_3'])) {
                $billingAddress['address']['country'] = $paymentAddress['iso_code_3'];
            }
    
            if (!empty($paymentAddress['postcode'])) {
                $billingAddress['address']['postcodeZip'] = substr($paymentAddress['postcode'], 0, 10);
            }
    
            if (!empty($paymentAddress['zone'])) {
                $billingAddress['address']['stateProvince'] = substr($paymentAddress['zone'], 0, 20);
            }
    
            if (!empty($paymentAddress['address_1'])) {
                $billingAddress['address']['street'] = substr($paymentAddress['address_1'], 0, 100);
            }
    
            if (!empty($paymentAddress['address_2'])) {
                $billingAddress['address']['street2'] = substr($paymentAddress['address_2'], 0, 100);
            }
        } 
        else{
            if (isset($this->session->data['payment_address'])) {
                $paymentAddress = $this->session->data['payment_address'];
                if (!empty($paymentAddress['city'])) {
                    $billingAddress['address']['city'] = substr($paymentAddress['city'], 0, 100);
                }
                if (!empty($paymentAddress['company'])) {
                    $billingAddress['address']['company'] = $paymentAddress['company'];
                }
                if (!empty($paymentAddress['iso_code_3'])) {
                    $billingAddress['address']['country'] = $paymentAddress['iso_code_3'];
                }
                if (!empty($paymentAddress['postcode'])) {
                    $billingAddress['address']['postcodeZip'] = substr($paymentAddress['postcode'], 0, 10);
                }
                if (!empty($paymentAddress['zone'])) {
                    $billingAddress['address']['stateProvince'] = substr($paymentAddress['zone'], 0, 20);
                }
                if (!empty($paymentAddress['address_1'])) {
                    $billingAddress['address']['street'] = substr($paymentAddress['address_1'], 0, 100);
                }
                if (!empty($paymentAddress['address_2'])) {
                    $billingAddress['address']['street2'] = substr($paymentAddress['address_2'], 0, 100);
                }
            } elseif (isset($this->session->data['shipping_address'])){
                    $paymentAddress = $this->session->data['shipping_address'];
                    if (!empty($paymentAddress['city'])) {
                        $billingAddress['address']['city'] = substr($paymentAddress['city'], 0, 100);
                    }
                    if (!empty($paymentAddress['company'])) {
                        $billingAddress['address']['company'] = $paymentAddress['company'];
                    }
                    if (!empty($paymentAddress['iso_code_3'])) {
                        $billingAddress['address']['country'] = $paymentAddress['iso_code_3'];
                    }
                    if (!empty($paymentAddress['postcode'])) {
                        $billingAddress['address']['postcodeZip'] = substr($paymentAddress['postcode'], 0, 10);
                    }
                    if (!empty($paymentAddress['zone'])) {
                        $billingAddress['address']['stateProvince'] = substr($paymentAddress['zone'], 0, 20);
                    }
                    if (!empty($paymentAddress['address_1'])) {
                        $billingAddress['address']['street'] = substr($paymentAddress['address_1'], 0, 100);
                    }
                    if (!empty($paymentAddress['address_2'])) {
                        $billingAddress['address']['street2'] = substr($paymentAddress['address_2'], 0, 100);
                    }
            }    
    }

        return $billingAddress;
    }

    /**
     * @return array
     */
    protected function getShippingAddress(){
        $shippingAddress = [];
        if (isset($this->session->data['shipping_address'])) {
            $shippingAddressData = $this->session->data['shipping_address'];

            if (!empty($shippingAddressData['city'])) {
                $shippingAddress['address']['city'] = substr($shippingAddressData['city'], 0, 100);
            }

            if (!empty($shippingAddressData['company'])) {
                $shippingAddress['address']['company'] = $shippingAddressData['company'];
            }

            if (!empty($shippingAddressData['iso_code_3'])) {
                $shippingAddress['address']['country'] = $shippingAddressData['iso_code_3'];
            }

            if (!empty($shippingAddressData['postcode'])) {
                $shippingAddress['address']['postcodeZip'] = substr($shippingAddressData['postcode'], 0, 10);
            }

            if (!empty($shippingAddressData['zone'])) {
                $shippingAddress['address']['stateProvince'] = substr($shippingAddressData['zone'], 0, 20);
            }

            if (!empty($shippingAddressData['address_1'])) {
                $shippingAddress['address']['street'] = substr($shippingAddressData['address_1'], 0, 100);
            }

            if (!empty($shippingAddressData['address_2'])) {
                $shippingAddress['address']['street2'] = substr($shippingAddressData['address_2'], 0, 100);
            }

            if (!empty($shippingAddressData['firstname'])) {
                $shippingAddress['contact']['firstName'] = substr($shippingAddressData['firstname'], 0, 50);
            }

            if (!empty($shippingAddressData['lastname'])) {
                $shippingAddress['contact']['lastName'] = substr($shippingAddressData['lastname'], 0, 50);
            }

            if ($this->customer->isLogged()) {
                $this->load->model('account/customer');
                $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());
                $shippingAddress['contact']['email'] = $customerModel['email'];
            } else {
                $order_id = $this->session->data['order_id'];
                $query = $this->db->query("SELECT * FROM `oc_order` WHERE `order_id` = $order_id");
                $shippingData = $query->row;
                $shippingAddress['contact']['email'] = $shippingData['email'];
            }

        }

        return $shippingAddress;
    }

    /**
     * @return array
     */
    protected function getCustomer(){
       if ($this->customer->isLogged()) {
            $this->load->model('account/customer');
            $customerModel = $this->model_account_customer->getCustomer($this->customer->getId());
            $customerData['firstName'] = substr($customerModel['firstname'], 0, 50);
            $customerData['lastName'] = substr($customerModel['lastname'], 0, 50);
            $customerData['email'] = $customerModel['email'];
       } else{
            $order_id = $this->session->data['order_id'];
            $query = $this->db->query("SELECT * FROM `oc_order` WHERE `order_id` = $order_id");
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
    public function processHostedCheckout(){
        setcookie("OCSESSID", "", time() - 1, "/");
        $this->load->language('extension/mastercard/payment/mastercard');
        $this->load->model('extension/mastercard/payment/mastercard');
        $this->document->addScript('view/javascript/mastercard/custom.js');
        $requestIndicator = $this->request->get['resultIndicator'];
        $mgpsSuccessIndicator =  $_COOKIE['mgps_sucesss_indicator'];;
        if (isset($_COOKIE['mgps_order']) && isset($_COOKIE['mgps_sucesss_indicator'])) {
            $mgpsSuccessIndicator = $_COOKIE['mgps_sucesss_indicator'];
            $orderId = $_COOKIE['mgps_order'];
            $ocessid = $_COOKIE['mgps_OCSESSID'];
            $ocOrderId = $_COOKIE['order_id'];
            $this->session->data['mgps_order_id']  = $orderId;
            $this->session->data['order_id']  =  $ocOrderId;
            setcookie('OCSESSID', $ocessid, time() + 24 * 3600, '/');
            setcookie('mgps_order', '', time() - 3600, '/');
            setcookie('mgps_sucesss_indicator', '', time() - 3600, '/');
        }
        try {
            if ($mgpsSuccessIndicator !== $requestIndicator) {
                throw new \Exception($this->language->get('error_indicator_mismatch'));
            }
            $retrievedOrder = $this->retrieveOrder($orderId);
            if ($retrievedOrder['result'] !== 'SUCCESS') {
                throw new \Exception($this->language->get('error_payment_declined'));
            }
            $txn = $retrievedOrder['transaction'][0];
            $transactionId = $txn['authentication']['3ds']['transactionId'] ?? $txn['transaction']['acquirer']['transactionId'] ?? $txn['transaction']['transactionId'] ?? $txn['transaction']['id'] ?? '';        
            $transactionAmount    =  $txn['transaction']['amount'];
            $transactionStatus    = $txn['order']['status'];
            $transactionOrderID   = $txn['order']['id'];
            $merchantName         = $txn['merchant'];
            $merchantId           = $txn['transaction']['acquirer']['merchantId'];
            $customer_email       = $txn['customer']['email'];
            $firstName = $txn['customer']['firstName'] ?? $retrievedOrder['shipping']['contact']['firstName'] ?? '';
            $lastName  = $txn['customer']['lastName'] ?? $retrievedOrder['shipping']['contact']['lastName'] ?? '';
            $customer_name = trim($firstName . ' ' . $lastName);
            $this->processOrder($retrievedOrder, $txn);
            $this->db->query("INSERT INTO ".DB_PREFIX."mgps_order_transaction SET order_id ='".$this->session->data['order_id'] ."', oc_order_id ='".$transactionOrderID ."', transaction_id = '".$transactionId."', type = '".$transactionStatus."', merchant_name = '".$merchantName."', merchant_id = '".$merchantId."' ,status = '".$transactionStatus ."', amount = '".$transactionAmount."', date_added = NOW()");
            if ($this->config->get('config_mail_engine')) {
               $this->sendCustomEmail($orderId, $customer_email,$transactionStatus, $customer_name );
            }
            $this->cart->clear();
            $this->clearTokenSaveCardSessionData();
            $this->model_extension_mastercard_payment_mastercard->clearCheckoutSession();
            echo "<script>
                sessionStorage.clear();
                window.location.href = '" . $this->url->link('checkout/success', '', true) . "';
            </script>";
            exit;
        } catch (\Exception $e) {
           
            $this->session->data['error'] = $e->getMessage();
            $this->addOrderHistory($orderId, self::ORDER_FAILED, $e->getMessage());
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    private function sendCustomEmail($orderId, $reciever_address, $subject, $customer_name) {
        $data['order_id'] = $orderId;
        $data['receiver_address'] = $reciever_address;
        $data['order_status'] = $subject;
        $data['customer_name'] = $customer_name;
    
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
    
            // Use sprintf to combine the strings
            $mail->setSubject(html_entity_decode(sprintf("Payment %s", ucwords(strtolower(str_replace('_', ' ', $subject)))), ENT_QUOTES, 'UTF-8'));
    
            $mail->setHtml($this->load->view('extension/mastercard/payment/mgps_hosted_authorize_mail', $data));
            $mail->send();
        }
    }
    

    public function callback(){
        $this->load->language('extension/mastercard/payment/mastercard');
        $this->load->model('extension/mastercard/payment/mastercard');
        $requestHeaders = $this->request->server;
        $webhookSecret = isset($requestHeaders[self::HEADER_WEBHOOK_SECRET]) ? $requestHeaders[self::HEADER_WEBHOOK_SECRET] : null;
        $webhookAttempt = isset($requestHeaders[self::HEADER_WEBHOOK_ATTEMPT]) ? $requestHeaders[self::HEADER_WEBHOOK_ATTEMPT] : null;
        $webhookId = isset($requestHeaders[self::HEADER_WEBHOOK_ID]) ? $requestHeaders[self::HEADER_WEBHOOK_ID] : null;
        $content = file_get_contents('php://input');
        $content = trim($content);
        $parsedData = @json_decode($content, true);
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            $this->model_extension_mastercard_payment_mastercard->log('Could not parse response JSON, error: '. $jsonError, json_encode(['rawContent' => $content]));
            header('HTTP/1.1 500 ' . $jsonError);
            exit;
        }
        try {
            if ($requestHeaders['REQUEST_METHOD'] != 'POST') {
                throw new \Exception($this->language->get('error_request_method'));
            }

            if (!$this->isSecure($requestHeaders)) {
                throw new \Exception($this->language->get('error_insecure_connection'));
            }

            if ($this->model_extension_mastercard_payment_mastercard->getWebhookSecret() !== $webhookSecret) {
                throw new \Exception($this->language->get('error_secret_mismatch'));
            }

            if ($this->model_extension_mastercard_payment_mastercard->getMerchantId() !== $parsedData['merchant']) {
                throw new \Exception($this->language->get('error_merchant_mismatch'));
            }

            if (!isset($parsedData['order']) || !isset($parsedData['order']['id'])) {
                throw new \Exception($this->language->get('error_invalid_order'));
            }

            if (!isset($parsedData['transaction']) || !isset($parsedData['transaction']['id'])) {
                throw new \Exception($this->language->get('error_invalid_transaction'));
            }

        } catch (\Exception $e) {
            $errorMessage = sprintf("WebHook Exception: '%s'", $e->getMessage());
            $this->model_extension_mastercard_payment_mastercard->log($errorMessage);
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
        $this->model_extension_mastercard_payment_mastercard->log("Webhook Response: " . $webhookResponse);
        try {
            $response = $this->retrieveTransaction($parsedData['order']['id'], $parsedData['transaction']['id']);

            if (isset($response['result']) && $response['result'] == 'ERROR') {
                $error = $this->language->get('error_payment_declined');
                if (isset($response['error']['explanation'])) {
                    $error = sprintf('%s: %s', $response['error']['cause'], $response['error']['explanation']);
                }
                throw new \Exception($error);
            }
        } catch (\Exception $e) {
            $this->model_extension_mastercard_payment_mastercard->log("Gateway Error: {$e->getMessage()}");
            header("HTTP/1.1 500 Gateway Error");
            exit;
        }

        if (!$this->isApproved($response)) {
            $this->model_extension_mastercard_payment_mastercard->log(sprintf('Unexpected gateway code "%s"', $response['response']['gatewayCode']));
            exit;
        }

        $mpgsOrderId = $response['order']['id'];
        $prefix = trim($this->config->get('payment_mastercard_order_id_prefix'));
        if ($prefix) {
            $mpgsOrderId = substr($mpgsOrderId, strlen($prefix));
        }

        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($mpgsOrderId);

        if (isset($response['risk']['response'])) {
            $risk = $response['risk']['response'];
            switch ($risk['gatewayCode']) {
                case 'REVIEW_REQUIRED':
                    if (!empty($risk['review']['decision']) && in_array($risk['review']['decision'], ['NOT_REQUIRED', 'ACCEPTED'])) {
                        $this->setOrderHistoryTransactionType($order, $response);
                    }
                    break;
                default:
                    $this->setOrderHistoryTransactionType($order, $response);
                    break;
            }

            $this->model_extension_mastercard_payment_mastercard->log('webhook completed (200 OK)');
            exit;
        }
    }

    /**
     * @param $order
     * @param $response
     */
    protected function setOrderHistoryTransactionType($order, $response){
        switch ($response['transaction']['type']) {
            case 'AUTHORIZATION':
            case 'AUTHORIZATION_UPDATE':
                if ($order['order_status_id'] != $this->config->get('payment_mastercard_pending_status_id')) {
                    $this->model_extension_mastercard_payment_mastercard->log(sprintf($this->language->get('text_not_allow_authorization'), $order['order_status_id']));
                }
                break;

            case 'PAYMENT':
            case 'CAPTURE':
                if ($order['order_status_id'] != $this->config->get('payment_mastercard_approved_status_id') && $order['order_status_id'] != $this->config->get('payment_mastercard_pending_status_id')) {
                    $this->model_extension_mastercard_payment_mastercard->log(sprintf($this->language->get('text_not_allow_capture'), $order['order_status']));
                }
                break;

            case 'REFUND_REQUEST':
            case 'REFUND':
                if ($order['order_status_id'] != self::ORDER_CAPTURED) {
                    $this->model_extension_mastercard_payment_mastercard->log(sprintf($this->language->get('text_not_allow_refund'), $order['order_status']));
                } else {
                    $order['order_status'] = self::ORDER_REFUNDED;
                }
                break;

            case 'VOID_AUTHORIZATION':
            case 'VOID_CAPTURE':
            case 'VOID_PAYMENT':
            case 'VOID_REFUND':
                if ($order['order_status_id'] != $this->config->get('payment_mastercard_approved_status_id')) {
                    $this->model_extension_mastercard_payment_mastercard->log(sprintf($this->language->get('text_not_allow_void'), $order['order_status']));
                } else {
                    $order['order_status'] = self::ORDER_VOIDED;
                }
                break;

            case 'CANCELLED':
                if ($order['order_status_id'] != self::ORDER_CANCELLED) {
                    $order['order_status'] = self::ORDER_CANCELLED;
                }
                break;

            default:
                $order['order_status'] = self::ORDER_CANCELLED;
                break;
        }
    }

    /**
     * @param $response
     * @return bool
     */
    public function isApproved($response){
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
    protected function isSecure($headers){
        $https = $headers['HTTPS'];
        $serverPort = $headers['SERVER_PORT'];
        return (!empty($https) && $https === "1") || $serverPort === "443";
    }

    /**
     * @param $customerId
     * @return array
     */
    public function getTokenizeCards($customerId){
        $this->load->language('extension/mastercard/payment/mastercard');
        $this->load->model('extension/mastercard/payment/mastercard');

        $customerTokens = $this->model_extension_mastercard_payment_mastercard->getCustomerTokens($customerId);
        $uri = $this->model_extension_mastercard_payment_mastercard->getApiUri() . '/token/';

        $cards = [];

        foreach ($customerTokens as $token) {
            $response = $this->model_extension_mastercard_payment_mastercard->apiRequest('GET', $uri . urlencode($token['token']));

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

    protected function getTokenById($tokenId) {
        $tokensResult = $this->db->query("SELECT token FROM `" . DB_PREFIX . "mpgs_hpf_token` WHERE hpf_token_id='" . (int)$tokenId . "'");
        return $tokensResult->row;
    }

    
    /**
     * Clear values of Hosted Payment Form
     * fields from session
     */
    protected function clearTokenSaveCardSessionData(){
        unset($this->session->data['save_card']);
        unset($this->session->data['token_id']);
        unset($this->session->data['source_of_funds']);
    }


    /**
     * Cancel callback
     */
    public function cancelCallback(){
        $ocessid = $_COOKIE['mgps_OCSESSID'];
        setcookie('OCSESSID', $ocessid, time() + 24 * 3600, '/');
    }

    /**
     * Cancel callback
     */
    public function errorCallback(){
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    /**
     * @param $retrievedOrder
     * @param $txn
     * @throws Exception
     */
    protected function processOrder($retrievedOrder, $txn){
        if ($retrievedOrder['status'] === 'CAPTURED') {
            $message = sprintf($this->language->get('text_payment_captured'), $txn['transaction']['id'], isset($txn['transaction']['authorizationCode']) ? $txn['transaction']['authorizationCode'] : '');
            $orderStatusId = self::ORDER_CAPTURED;
        } elseif ($retrievedOrder['status'] === 'AUTHORIZED') {
            $message = sprintf($this->language->get('text_payment_authorized'), $txn['transaction']['id'], isset($txn['transaction']['authorizationCode']) ? $txn['transaction']['authorizationCode'] : '');
            $orderStatusId = $this->config->get('payment_mastercard_approved_status_id');
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
    protected function addOrderHistory($orderId, $orderStatusId, $message){
        $this->load->model('checkout/order');
        $this->model_checkout_order->addHistory($orderId, $orderStatusId , $message);
    }

    /**
     * @param $orderId
     * @return mixed
     */
    protected function retrieveOrder($orderId){
        
        $this->load->model('extension/mastercard/payment/mastercard');

        $uri = $this->model_extension_mastercard_payment_mastercard->getApiUri() . '/order/' . $orderId;

        $response = $this->model_extension_mastercard_payment_mastercard->apiRequest('GET', $uri);
        
        return $response;
    }

    /**
     * @param $orderId
     * @param $txnId
     * @return mixed
     */
    protected function retrieveTransaction($orderId, $txnId){
        $this->load->model('extension/mastercard/payment/mastercard');
        $uri = $this->model_extension_mastercard_payment_mastercard->getApiUri() . '/order/' . $orderId . '/transaction/' . $txnId;
        $response = $this->model_extension_mastercard_payment_mastercard->apiRequest('GET', $uri);
        return $response;
    }

    /**
     * @return array
     */
    public function configureHostedCheckout(){
        $this->load->helper('utf8');
        $this->load->model('extension/mastercard/payment/mastercard');
        $params = [
            'merchant' => $this->model_extension_mastercard_payment_mastercard->getMerchantId(),
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
