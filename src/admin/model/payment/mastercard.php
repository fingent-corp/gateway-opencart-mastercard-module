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
 * @version  GIT: @1.5.0@
 * @link     https://github.com/fingent-corp/gateway-opencart-mastercard-module
 */

namespace Opencart\Admin\Model\Extension\MasterCard\Payment;

class MasterCard extends \Opencart\System\Engine\Model {
    const SETTING_EVENT_MODEL = 'setting/event';

    public function install() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `".DB_PREFIX."mgps_order_transaction` (
                `mgps_order_transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
                `order_id` varchar(255) NOT NULL,
                `oc_order_id` varchar(255) NOT NULL,
                `transaction_id` varchar(255),
                `void_transaction_id` varchar(255),
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
    
    public function deleteEvents(): void {
        $this->load->model(self::SETTING_EVENT_MODEL);
        $this->model_setting_event->deleteEventByCode('mastercard_update_page_header');
    }

    public function addEvents() {
        $this->load->model(self::SETTING_EVENT_MODEL);
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
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."mgps_order_transaction`;");
        $this->load->model(self::SETTING_EVENT_MODEL);
		$this->model_setting_event->deleteEventByCode('mastercard');
		if (VERSION < '4.0.2.0') {
			$this->model_setting_event->deleteEventByCode('mastercard_extension_get_extensions_by_type');
			$this->model_setting_event->deleteEventByCode('mastercard_extension_get_extension_by_code');
		}
    }

    public function getOrder($orderId) {
        $pattern = '/\d+/';
        preg_match($pattern, $orderId, $matches);
    
        if (isset($matches[0])) {
            $result = $matches[0];
        } else {
            return null;
        }
        $this->load->model('sale/order');
        $orderinfo = $this->model_sale_order->getOrder($result);
        if (
            $orderinfo &&
            is_array($orderinfo) &&
            isset($orderinfo['payment_method']['code']) &&
            $orderinfo['payment_method']['code'] === 'mastercard.mastercard'
        ) {
            return $orderinfo;
        }
        return null;
    }

    public function addOrderHistory($orderId, $orderStatusId, $comment = '', $notify = false) {
        $sql = sprintf(
            "INSERT INTO `%sorder_history` SET order_id = '%d',
            order_status_id = '%d', notify = '%d', comment = '%s', date_added = NOW()",
            DB_PREFIX,
            (int)$orderId,
            (int)$orderStatusId,
            (int)$notify,
            $this->db->escape($comment)
        );
    
        $this->db->query($sql);
    }
    
    public function getOrderStatusIdByName($statusName) {
        $sql = sprintf(
            "SELECT order_status_id FROM `%sorder_status` WHERE name = '%s'",
            DB_PREFIX,
            $this->db->escape($statusName)
        );
        $query = $this->db->query($sql);
        if ($query->num_rows) {
            return $query->row['order_status_id'];
        } else {
            return false;
        }
    }
    
    public function getTransactions($orderId) {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "mgps_order_transaction` WHERE `order_id` = '" . $orderId . "'"
        );
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

    public function dropTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "mpgs_hpf_token`");
    }

    public function getMerchantId() {
        if ($this->isTestModeEnabled()) {
            return $this->config->get('payment_mastercard_test_merchant_id');
        } else {
            return $this->config->get('payment_mastercard_live_merchant_id');
        }
    }

    public function isTestModeEnabled() {
        return $this->config->get('payment_mastercard_test');
    }
}
