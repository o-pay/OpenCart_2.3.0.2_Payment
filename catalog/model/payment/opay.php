<?php

class ModelPaymentOpay extends Model {
	private $trans = array();
	
	public function getMethod($address, $total) {
		# Condition check
		$opay_geo_zone_id = $this->config->get('opay_geo_zone_id');
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE geo_zone_id = '" . (int)$opay_geo_zone_id . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
		$status = false;
		if ($total <= 0) {
			$status = false;
		} elseif (!$opay_geo_zone_id) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		# Set the payment method parameters
		$this->load->language('payment/opay');
		$method_data = array();
		if ($status) {
			$method_data = array(
				'code' => 'opay',
				'title' => $this->language->get('opay_text_title'),
				'terms' => '',
				'sort_order' => $this->config->get('opay_sort_order')
			);
		}
		
		return $method_data;
	}
	
	public function vaildatePayment($payment) {
		$payment_methods = $this->config->get('opay_payment_methods');
		if (isset($payment_methods[$payment])) {
			return true;
		} else {
			return false;
		}
	}
	
	public function invokeOpayModule() {
		if (!class_exists('AllInOne', false)) {
			if (!include('AllPay.Payment.Integration.php')) {
				$this->load->language('payment/opay');
				return false;
			}
		}
		
		return true;
	}
	
	public function isTestMode($opay_merchant_id) {
		if ($opay_merchant_id == '2000132' or $opay_merchant_id == '2000214') {
			return true;
		} else {
			return false;
		}
	}
	
	public function getCartOrderID($merchant_trade_no, $opay_merchant_id) {
		$cart_order_id = $merchant_trade_no;
		if ($this->isTestMode($opay_merchant_id)) {
			$cart_order_id = substr($merchant_trade_no, 14);
		}
		
		return $cart_order_id;
	}
	
	public function formatOrderTotal($order_total) {
		return intval(round($order_total));
	}
	
	public function getPaymentMethod($payment_type) {
		$info_pieces = explode('_', $payment_type);
		
		return $info_pieces[0];
	}

	public function logMessage($message) {
		$log = new Log('opay_return_url.log');
		$log->write($message);
	}
	
}
