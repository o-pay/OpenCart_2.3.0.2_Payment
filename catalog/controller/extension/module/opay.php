<?php
$model_path = 'extension/payment/opay';

// Set the checkout form action
$data['opay_action'] = $this->url->link($model_path . '/redirect', '', true);

// Get the translations
$this->load->language($model_path);

// ecpay shipping collection action
$shippingMethod = $this->session->data['shipping_method'];
$shipping = explode('.', $shippingMethod['code']);
$ecpayShippingType = [
    'fami_collection',
    'unimart_collection' ,
    'hilife_collection',
];
$data['ecpay_isCollection'] = false;
if (in_array($shipping[1], $ecpayShippingType)) {
    $data['ecpay_isCollection'] = true;
}

// Get the translation of payment methods
$opay_payment_methods = $this->config->get('opay_payment_methods');
if (is_array($opay_payment_methods)) {
    foreach ($opay_payment_methods as $payment_type => $value) {
        $data['opay_payment_methods'][$payment_type] = $this->language->get('opay_text_' . $value);
    }
}
?>