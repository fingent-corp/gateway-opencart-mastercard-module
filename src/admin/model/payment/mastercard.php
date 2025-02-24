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

namespace Opencart\Admin\Model\Extension\MasterCard\Payment;

class MasterCard extends \Opencart\System\Engine\Model{
    public function install() {
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
    
    public function deleteEvents(): void{
        $this->load->model('setting/event');
    
        $this->model_setting_event->deleteEventByCode('mastercard_update_page_header');
    }

    public function addEvents(){
        $this->load->model('setting/event');
        $eventData = array(
            'code'        => 'mastercard_update_page_header',
            'trigger'     => 'catalog/controller/common/header/before',
            'action'      => 'extension/mastercard/payment/mastercard.update_page_header',
            'status'      => 1,
            'sort_order'  => 0,
            'description' => ''
        );
    
        $this->model_setting_event->addEvent($eventData);
    }

    public function createTable(){
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
    }

    public function uninstall(){
        $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."mgps_order_transaction`;");
        $this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('mastercard');
		if (VERSION < '4.0.2.0') {
			$this->model_setting_event->deleteEventByCode('mastercard_extension_get_extensions_by_type');
			$this->model_setting_event->deleteEventByCode('mastercard_extension_get_extension_by_code');
		}
    }

    public function getOrder($order_id){   
        $pattern = '/\d+/';
        preg_match($pattern, $order_id, $matches);
        if (isset($matches[0])) {
            $result = $matches[0]; 
        }
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($result);
       
        if ($order_info && is_array($order_info) && isset($order_info['payment_method']['code']) && $order_info['payment_method']['code'] === 'mastercard.mastercard') {
            return $order_info;
        }
        
        return null;
    }

    public function addOrderHistory($order_id, $order_status_id, $comment = '', $notify = false) {
        
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', notify = '" . (int)$notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
    }

    public function getOrderStatusIdByName($statusName){
        $query = $this->db->query("SELECT order_status_id FROM " . DB_PREFIX . "order_status WHERE name = '" . $this->db->escape($statusName) . "'");
        if ($query->num_rows) {
            return $query->row['order_status_id'];
        } else {
            return false;
        }
    }

    public function getTransactions($order_id){
        
        $query = $this->db->query("SELECT * FROM `".DB_PREFIX."mgps_order_transaction` WHERE `order_id` = '".$order_id."'");
        $transactions = array();
        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $transactions[] = $this->rowTxn($row);
            }
        }
  
        return $transactions;
    }

    protected function rowTxn($row) {
        return $row;
    }
    

    public function dropTable(){
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "mpgs_hpf_token`");
    }

    public function getMerchantId(){
        if ($this->isTestModeEnabled()) {
            
            return $this->config->get('payment_mastercard_test_merchant_id');
        } else {
            return $this->config->get('payment_mastercard_live_merchant_id');
        }
    }

    public function isTestModeEnabled(){
        return $this->config->get('payment_mastercard_test');
    }
}