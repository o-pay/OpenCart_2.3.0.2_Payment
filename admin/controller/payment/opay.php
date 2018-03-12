<?php

class ControllerPaymentOpay extends Controller {
	
	private $error = array();
	private $require_settings = array('merchant_id', 'hash_key', 'hash_iv');

	public function index() {
		# Load the translation file
		$this->load->language('payment/opay');
		
		# Set the title
		$heading_title = $this->language->get('heading_title');
		$this->document->setTitle($heading_title);
		$data['heading_title'] = $heading_title;
		
		# Load the Setting
		$this->load->model('setting/setting');
		
		# Process the saving setting
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			# Save the setting
			$this->model_setting_setting->editSetting('opay', $this->request->post);
			
			# Define the success message
			$this->session->data['success'] = $this->language->get('opay_text_success');
			
			# Back to the payment list
			$this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
		}
		
		# Get the translation
		$data['opay_text_status'] = $this->language->get('opay_text_status');
		$data['opay_text_enabled'] = $this->language->get('opay_text_enabled');
		$data['opay_text_disabled'] = $this->language->get('opay_text_disabled');
		$data['opay_text_merchant_id'] = $this->language->get('opay_text_merchant_id');
		$data['opay_text_hash_key'] = $this->language->get('opay_text_hash_key');
		$data['opay_text_hash_iv'] = $this->language->get('opay_text_hash_iv');
		$data['opay_text_payment_methods'] = $this->language->get('opay_text_payment_methods');
		$data['opay_text_credit'] = $this->language->get('opay_text_credit');
		$data['opay_text_credit_3'] = $this->language->get('opay_text_credit_3');
		$data['opay_text_credit_6'] = $this->language->get('opay_text_credit_6');
		$data['opay_text_credit_12'] = $this->language->get('opay_text_credit_12');
		$data['opay_text_credit_18'] = $this->language->get('opay_text_credit_18');
		$data['opay_text_credit_24'] = $this->language->get('opay_text_credit_24');
		$data['opay_text_webatm'] = $this->language->get('opay_text_webatm');
		$data['opay_text_atm'] = $this->language->get('opay_text_atm');
		$data['opay_text_cvs'] = $this->language->get('opay_text_cvs');
		$data['opay_text_topupused'] = $this->language->get('opay_text_topupused');

		$data['opay_text_create_status'] = $this->language->get('opay_text_create_status');
		$data['opay_text_success_status'] = $this->language->get('opay_text_success_status');
		$data['opay_text_failed_status'] = $this->language->get('opay_text_failed_status');

		$data['opay_text_geo_zone'] = $this->language->get('opay_text_geo_zone');
		$data['opay_text_all_zones'] = $this->language->get('opay_text_all_zones');
		$data['opay_text_sort_order'] = $this->language->get('opay_text_sort_order');
		
		# Get the error
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		# Get the error of the require fields
		foreach ($this->require_settings as $setting_name) {
			$tmp_error_name = 'opay_error_' . $setting_name;
			if(isset($this->error[$tmp_error_name])) {
				$data[$tmp_error_name] = $this->error[$tmp_error_name];
			} else {
				$data[$tmp_error_name] = '';
			}
		}
		
		# Set the breadcrumbs
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('opay_text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('opay_text_payment'),
			'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL')
		);
		$data['breadcrumbs'][] = array(
			'text' => $heading_title,
			'href' => $this->url->link('payment/opay', 'token=' . $this->session->data['token'], 'SSL')
		);
		
		# Set the form action
		$data['allapy_action'] = $this->url->link('payment/opay', 'token=' . $this->session->data['token'], 'SSL');
		
		# Set the cancel button
		$data['opay_cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');
		
		# Get O'Pay setting
		$opay_settings = array(
			'status',
			'merchant_id',
			'hash_key',
			'hash_iv',
			'payment_methods',
            'create_status',
            'success_status',
            'failed_status',
			'geo_zone_id',
			'sort_order'
		);
		foreach ($opay_settings as $setting_name) {
			$tmp_setting_name = 'opay_' . $setting_name;
			if (isset($this->request->post[$tmp_setting_name])) {
				$data[$tmp_setting_name] = $this->request->post[$tmp_setting_name];
			} else {
				$data[$tmp_setting_name] = $this->config->get($tmp_setting_name);
			}
		}
		
		// Default value
        $default_config = array(
            'opay_merchant_id' => '2000132',
            'opay_hash_key' => '5294y06JbISpM5x9',
            'opay_hash_iv' => 'v77hoKGq4kWxNNIS',
            'opay_create_status' => 1,
            'opay_success_status' => 15,
        );
        foreach ($default_config as $name => $value) {
            if (is_null($data[$name])) {
                $data[$name] = $value;
            }
        }

        # Get the order statuses
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		# Get the geo zone
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		
		# View's setting
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('payment/opay.tpl', $data));
	}
	
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'payment/opay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		foreach ($this->require_settings as $setting_name) {
			if (!$this->request->post['opay_' . $setting_name]) {
				$this->error['opay_error_' . $setting_name] = $this->language->get('opay_error_' . $setting_name);
			}
		}
		
		return !$this->error; 
	}
}
