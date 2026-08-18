<?php
/**
 * Payment Aggregator & Gateway Indonesia (PAGI)
 *
 * @author    Wiriasto
 * @copyright 2026 Wiriasto
 * @license   GPL-2.0-or-later
 * @version   1.0
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

class ControllerExtensionPaymentBdpay extends Controller {
    public function index() {
        $this->load->language('extension/payment/bdpay');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_loading'] = $this->language->get('text_loading');

        $methods = $this->config->get('payment_bdpay_methods');
        if (!is_array($methods) || empty($methods)) {
            $methods = array('QRIS', 'VA_BCA', 'VA_MANDIRI', 'EWALLET_DANA');
        }

        $data['methods'] = array();
        $labels = array(
            'QRIS' => 'QRIS (Semua e-Wallet & Bank)',
            'VA_BCA' => 'Virtual Account BCA',
            'VA_MANDIRI' => 'Virtual Account Mandiri',
            'VA_BNI' => 'Virtual Account BNI',
            'VA_BRI' => 'Virtual Account BRI',
            'EWALLET_DANA' => 'DANA',
            'EWALLET_OVO' => 'OVO'
        );
        foreach ($methods as $code) {
            $data['methods'][] = array(
                'code'  => $code,
                'label' => isset($labels[$code]) ? $labels[$code] : $code
            );
        }

        $data['action'] = $this->url->link('extension/payment/bdpay/confirm', '', true);

        return $this->load->view('extension/payment/bdpay', $data);
    }

    public function confirm() {
        $json = array();

        if ($this->session->data['payment_method']['code'] == 'bdpay') {
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/bdpay');

            $order_id = $this->session->data['order_id'];
            $order_info = $this->model_checkout_order->getOrder($order_id);

            $method = isset($this->request->post['bdpay_method']) ? $this->request->post['bdpay_method'] : 'QRIS';

            $result = $this->model_extension_payment_bdpay->createPayment($order_info, $method);

            if ($result && !empty($result['success'])) {
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_bdpay_order_status_id'), 'BDPay: Menunggu pembayaran. Method: ' . $method, true);

                // Store meta if needed (OpenCart uses order history / custom table)
                if (!empty($result['url'])) {
                    $json['redirect'] = $result['url'];
                } else {
                    $json['redirect'] = $this->url->link('checkout/success', '', true);
                }
            } else {
                $json['error'] = isset($result['message']) ? $result['message'] : 'Gagal membuat pembayaran BDPay';
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function callback() {
        $this->load->model('extension/payment/bdpay');
        $this->model_extension_payment_bdpay->processCallback($this->request->post ?: json_decode(file_get_contents('php://input'), true));
        echo 'OK';
    }
}
