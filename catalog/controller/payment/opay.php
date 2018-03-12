<?php

class ControllerPaymentOpay extends Controller {
	
	public function index() {
		# Set the checkout form action
		$data = array();
		$data['opay_action'] = $this->url->link('payment/opay/redirect', '', 'SSL');
		
		# Get the translation
		$this->load->language('payment/opay');
		$data['opay_text_title'] = $this->language->get('opay_text_title');
		$data['opay_text_payment_methods'] = $this->language->get('opay_text_payment_methods');
		$data['opay_text_checkout_button'] = $this->language->get('opay_text_checkout_button');
		
		# Get the translation of payment methods
		$payment_methods = $this->config->get('opay_payment_methods');
		$data['payment_methods'] = array();
		foreach ($payment_methods as $payment_type => $value) {
			$data['payment_methods'][$payment_type] = $this->language->get('opay_text_' . $value);
		}
		
		# Get the template
		$config_template = $this->config->get('config_template');
		$payment_template = '';
		if (file_exists(DIR_TEMPLATE . $config_template)) {
			$payment_template = $config_template;
		} else {
			$payment_template = 'default';
		}
		$payment_template .= (strpos(VERSION, '2.2.') !== false) ? '/payment/opay.tpl' : '/template/payment/opay.tpl';
		
		return $this->load->view($payment_template, $data);
  }

	public function redirect() {
		try {
			# Load O'Pay translation
			$this->load->language('payment/opay');
			
			# Validate the payment
			$payment_type = $this->request->post['opay_choose_payment'];
			$this->load->model('payment/opay');
			$is_valid = $this->model_payment_opay->vaildatePayment($payment_type);
			if (!$is_valid) {
				throw new Exception($this->language->get('opay_error_invalid_payment'));
			} else {
				if (!isset($this->session->data['order_id'])) {
					throw new Exception($this->language->get('opay_error_order_id_miss'));
				} else {
					# Get the order info
					$order_id = $this->session->data['order_id'];
					$this->load->model('checkout/order');
					$order = $this->model_checkout_order->getOrder($order_id);

					
					# Generate the redirection form
					$form_html = '';
					$invoke_result = $this->model_payment_opay->invokeOpayModule();
					if (!$invoke_result) {
						throw new Exception($this->language->get('opay_error_module_miss'));
					} else {
						# Set O'Pay parameters
						$aio = new AllInOne();
						$aio->Send['MerchantTradeNo'] = '';
						$service_url = '';
						$aio->MerchantID = $this->config->get('opay_merchant_id');
						if ($this->model_payment_opay->isTestMode($aio->MerchantID)) {
							$service_url = 'https://payment-stage.opay.tw/Cashier/AioCheckOut/V4';
							$aio->Send['MerchantTradeNo'] = date('YmdHis');
						} else {
							$service_url = 'https://payment.opay.tw/Cashier/AioCheckOut/V4';
						}
						$aio->HashKey = $this->config->get('opay_hash_key');
						$aio->HashIV = $this->config->get('opay_hash_iv');
						$aio->ServiceURL = $service_url;
						$aio->Send['ReturnURL'] = $this->url->link('payment/opay/response', '', 'SSL');
						$aio->Send['ClientBackURL'] = str_replace('&amp;', '&', $this->url->link('account/order/info', 'order_id=' . $order_id, 'SSL'));
						$aio->Send['MerchantTradeNo'] .= $order_id;
						$aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
						
						# Set the product info
						$aio->Send['TotalAmount'] = $this->model_payment_opay->formatOrderTotal($order['total']);
						array_push(
							$aio->Send['Items'],
							array(
								'Name' => $this->language->get('opay_text_item_name'),
								'Price' => $aio->Send['TotalAmount'],
								'Currency' => '',
								'Quantity' => 1,
								'URL' => ''
							)
						);
						# Set the trade descriptions
						$aio->Send['TradeDesc'] = 'OPay_module_opencart_1.2.0209';
						
						# Get the chosen payment and installment
						$type_pieces = explode('_', $payment_type);
						$aio->Send['ChoosePayment'] = $type_pieces[0];
						$choose_installment = 0;
						if (isset($type_pieces[1])) {
							$choose_installment = $type_pieces[1];
						}

						# Set the extend information
						switch ($aio->Send['ChoosePayment']) {
							case PaymentMethod::Credit:
								# Do not support UnionPay
								$aio->SendExtend['UnionPay'] = false;
								
								# Credit installment parameters
								if (!empty($choose_installment)) {
									$aio->SendExtend['CreditInstallment'] = $choose_installment;
									$aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
									$aio->SendExtend['Redeem'] = false;
								}
								break;
							case PaymentMethod::WebATM:
								break;
							case PaymentMethod::ATM:
								$aio->SendExtend['ExpireDate'] = 3;
								$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
								break;
							case PaymentMethod::CVS:
								$aio->SendExtend['Desc_1'] = '';
								$aio->SendExtend['Desc_2'] = '';
								$aio->SendExtend['Desc_3'] = '';
								$aio->SendExtend['Desc_4'] = '';
								$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
								break;
							case PaymentMethod::TopUpUsed:
								break;
							default:
								break;
						}
					}
					
					# Update order status and comments
					$payment_methods = $this->config->get('opay_payment_methods');
					$order_create_status_id = $this->config->get('opay_create_status');
					$payment_desc = $this->language->get('opay_text_' . $payment_methods[$payment_type]);
					$this->model_checkout_order->addOrderHistory($order_id, $order_create_status_id, $payment_desc, false);
				
					# Clean the cart
					$this->cart->clear();

					# Add to activity log
					$this->load->model('account/activity');
					if ($this->customer->isLogged()) {
						$activity_data = array(
							'customer_id' => $this->customer->getId(),
							'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
							'order_id'    => $order_id
						);

						$this->model_account_activity->addActivity('order_account', $activity_data);
					} else {
						$activity_data = array(
							'name'     => $this->session->data['guest']['firstname'] . ' ' . $this->session->data['guest']['lastname'],
							'order_id' => $order_id
						);

						$this->model_account_activity->addActivity('order_guest', $activity_data);
					}

					# Clean the session
					unset($this->session->data['shipping_method']);
					unset($this->session->data['shipping_methods']);
					unset($this->session->data['payment_method']);
					unset($this->session->data['payment_methods']);
					unset($this->session->data['guest']);
					unset($this->session->data['comment']);
					unset($this->session->data['order_id']);
					unset($this->session->data['coupon']);
					unset($this->session->data['reward']);
					unset($this->session->data['voucher']);
					unset($this->session->data['vouchers']);
					unset($this->session->data['totals']);
					
					# Print the redirection form
					$aio->CheckOut();
					exit;
				}
			}
		} catch (Exception $e) {
			# Process the exception
			$this->session->data['error'] = $e->getMessage();
			$checkout_url = $this->url->link('checkout/checkout', '', 'SSL');
			$this->response->redirect($checkout_url);
		}
	}
	
	public function response() {
		# Load the model and translation
		$this->load->language('payment/opay');
		$this->load->model('payment/opay');
		$this->load->model('checkout/order');
		
		# Set the default result message
		$result_message = '1|OK';
		$cart_order_id = null;
		$order = null;
		try {
			# Retrieve the checkout result
			$invoke_result = $this->model_payment_opay->invokeOpayModule();
			if (!$invoke_result) {
				throw new Exception('O\'Pay module is missing.');
			} else {
				$aio = new AllInOne();
				$aio->HashKey = $this->config->get('opay_hash_key');
				$aio->HashIV = $this->config->get('opay_hash_iv');
				$opay_feedback = $aio->CheckOutFeedback();
				unset($aio);
				
				# Process O'Pay feedback
				if(count($opay_feedback) < 1) {
					throw new Exception('Get O\'Pay feedback failed.');
				} else {
					# Get the cart order id
					$cart_order_id = $this->model_payment_opay->getCartOrderID($opay_feedback['MerchantTradeNo'], $this->config->get('opay_merchant_id'));
				
					# Get the cart order amount
					$order = $this->model_checkout_order->getOrder($cart_order_id);
					$cart_amount = $this->model_payment_opay->formatOrderTotal($order['total']);
					
					# Check the amounts
					$opay_amount = $opay_feedback['TradeAmt'];
					if ($cart_amount != $opay_amount) {
						throw new Exception(sprintf('Order %s amount are not identical.', $cart_order_id));
					} else {
						# Set the common comments
						$comments = sprintf(
							$this->language->get('opay_text_common_comments'),
							$opay_feedback['PaymentType'],
							$opay_feedback['TradeDate']
						);
						
						#  Set the getting code comments
						$return_message = $opay_feedback['RtnMsg'];
						$return_code = $opay_feedback['RtnCode'];
						$get_code_result_comments = sprintf(
							$this->language->get('opay_text_get_code_result_comments'),
							$return_code,
							$return_message
						);
						
						#  Set the payment result comments
						$payment_result_comments = sprintf(
							$this->language->get('opay_text_payment_result_comments'),
							$return_code,
							$return_message
						);
						
						# Get O'Pay payment method
						$type_pieces = explode('_', $opay_feedback['PaymentType']);
						$opay_payment_method = $type_pieces[0];
						
						# Update the order status and comments
						$fail_message = sprintf('Order %s Exception.(%s: %s)', $cart_order_id, $return_code, $return_message);
						$order_create_status_id = $this->config->get('opay_create_status');
						$paid_succeeded_status_id = $this->config->get('opay_success_status');
						
						switch($opay_payment_method) {
							case PaymentMethod::Credit:
							case PaymentMethod::WebATM:
							case PaymentMethod::TopUpUsed:
								if ($return_code != 1 and $return_code != 800) {
									throw new Exception($fail_message);
								} else {
									# Only finish the order when the status is processing 
									if ($order['order_status_id'] != $order_create_status_id) {
										# The order already paid or not in the standard procedure, do nothing
									} else {
										
										$this->model_checkout_order->addOrderHistory(
											$cart_order_id,
											$paid_succeeded_status_id,
											$payment_result_comments,
											true
										);
										
										
										// 判斷電子發票是否啟動 START
										$nInvoice_Status  = $this->config->get('opayinvoice_status');
										if($nInvoice_Status == 1)
										{
											$this->load->model('payment/opayinvoice');
											$nInvoice_Autoissue 	= $this->config->get('opayinvoice_autoissue');
											$sCheck_Invoice_SDK	= $this->model_payment_opayinvoice->check_invoice_sdk();
											if( $nInvoice_Autoissue == 1 && $sCheck_Invoice_SDK != false )
											{	
												$this->model_payment_opayinvoice->createInvoiceNo($cart_order_id, $sCheck_Invoice_SDK);
											}
										}
										// 判斷電子發票是否啟動 END
	
									}
								}
								break;
							case PaymentMethod::ATM:
								if ($return_code != 1 and $return_code != 2 and $return_code != 800) {
									throw new Exception($fail_message);
								} else {
									if ($return_code == 2) {
										# Set the getting code result
										$comments .= sprintf(
											$this->language->get('opay_text_atm_comments'),
											$opay_feedback['BankCode'],
											$opay_feedback['vAccount'],
											$opay_feedback['ExpireDate']
										);
										$this->model_checkout_order->addOrderHistory(
											$cart_order_id,
											$order_create_status_id,
											$comments . $get_code_result_comments,
											true
										);
									} else {
										# Only finish the order when the status is processing 
										if ($order['order_status_id'] != $order_create_status_id) {
											# The order already paid or not in the standard procedure, do nothing
										} else {
											
											$this->model_checkout_order->addOrderHistory(
												$cart_order_id,
												$paid_succeeded_status_id,
												$payment_result_comments,
												true
											);
											
											// 判斷電子發票是否啟動 START
											$nInvoice_Status  = $this->config->get('opayinvoice_status');
											if($nInvoice_Status == 1)
											{
												$this->load->model('payment/opayinvoice');
												$nInvoice_Autoissue 	= $this->config->get('opayinvoice_autoissue');
												$sCheck_Invoice_SDK	= $this->model_payment_opayinvoice->check_invoice_sdk();
												if( $nInvoice_Autoissue == 1 && $sCheck_Invoice_SDK != false )
												{	
													$this->model_payment_opayinvoice->createInvoiceNo($cart_order_id, $sCheck_Invoice_SDK);
												}
											}
											// 判斷電子發票是否啟動 END
											
										}
									}
								}
								break;
							case PaymentMethod::CVS:
								if ($return_code != 1 and $return_code != 800 and $return_code != 10100073) {
									throw new Exception($fail_message);
								} else {
									if ($return_code == 10100073) {
										$comments .= sprintf(
											$this->language->get('opay_text_cvs_comments'),
											$opay_feedback['PaymentNo'],
											$opay_feedback['ExpireDate']
										);
										$this->model_checkout_order->addOrderHistory(
											$cart_order_id,
											$order_create_status_id,
											$comments . $get_code_result_comments,
											true
										);
									} else {
										# Only finish the order when the status is processing 
										if ($order['order_status_id'] != $order_create_status_id) {
											# The order already paid or not in the standard procedure, do nothing
										} else {
											$this->model_checkout_order->addOrderHistory(
												$cart_order_id,
												$paid_succeeded_status_id,
												$payment_result_comments,
												true
											);
											
											
											// 判斷電子發票是否啟動 START
											$nInvoice_Status  = $this->config->get('opayinvoice_status');
											if($nInvoice_Status == 1)
											{
												$this->load->model('payment/opayinvoice');
												$nInvoice_Autoissue 	= $this->config->get('opayinvoice_autoissue');
												$sCheck_Invoice_SDK	= $this->model_payment_opayinvoice->check_invoice_sdk();
												if( $nInvoice_Autoissue == 1 && $sCheck_Invoice_SDK != false )
												{	
													$this->model_payment_opayinvoice->createInvoiceNo($cart_order_id, $sCheck_Invoice_SDK);
												}
											}
											// 判斷電子發票是否啟動 END
										}
									}
								}
								break;
							default:
								throw new Exception(sprintf('Order %s, payment method is invalid.', $cart_order_id));
								break;
						}
					}
				}
			}
		} catch (Exception $e) {
			$error = $e->getMessage();
			if (!empty($order)) {
				$paid_failed_status_id = $this->config->get('opay_failed_status');
				$comments = sprintf($this->language->get('opay_text_failure_comments'), $error);
				$this->model_checkout_order->addOrderHistory($cart_order_id, $paid_failed_status_id, $comments);
			}
			
			# Set the failure result
			$result_message = '0|' . $error;
		}
		# Return URL log
		$this->model_payment_opay->logMessage('Order ' . $cart_order_id . ' process O\'Pay response result : ' . $result_message);
		
		echo $result_message;
		exit;
	}

}
