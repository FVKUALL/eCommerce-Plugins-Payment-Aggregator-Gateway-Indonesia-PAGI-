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

class ModelExtensionPaymentBdpay extends Model {
    public function getMethod($address, $total) {
        $this->load->language('extension/payment/bdpay');

        $status = true;
        if (!$this->config->get('payment_bdpay_status')) {
            $status = false;
        }

        $method_data = array();
        if ($status) {
            $method_data = array(
                'code'       => 'bdpay',
                'title'      => $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('payment_bdpay_sort_order')
            );
        }
        return $method_data;
    }

    public function createPayment($order_info, $method = 'QRIS') {
        $merchant_code = $this->config->get('payment_bdpay_merchant_code');
        $secret_key    = $this->config->get('payment_bdpay_secret_key');
        $environment   = $this->config->get('payment_bdpay_environment') ?: 'sandbox';
        $base_url      = ($environment === 'production') ? 'https://openapi.bdpay.co.id' : 'https://dev-openapi.bdpay.co.id';

        $amount = number_format($order_info['total'], 0, '', '');
        $order_num = $order_info['order_id'] . '-' . time();

        $body = array(
            'merchantCode'  => $merchant_code,
            'orderNum'      => $order_num,
            'payMoney'      => $amount,
            'method'        => $method,
            'productDetail' => 'Order #' . $order_info['order_id'],
            'name'          => $order_info['firstname'] . ' ' . $order_info['lastname'],
            'email'         => $order_info['email'],
            'phone'         => $order_info['telephone'] ?: '081234567890',
            'notifyUrl'     => $this->url->link('extension/payment/bdpay/callback', '', true),
            'expiryPeriod'  => '60',
            'dateTime'      => gmdate('Y-m-d\TH:i:s\Z'),
        );

        require_once DIR_APPLICATION . 'model/extension/payment/BDPaySignature.php';
        $body['sign'] = BDPaySignature::generate($body, $secret_key, 'RSA');

        $ch = curl_init($base_url . '/gateway/prepaidOrder');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Accept: application/json'),
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 30,
        ));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300 && isset($data['platRespCode']) && $data['platRespCode'] === 'SUCCESS') {
            return array(
                'success' => true,
                'url' => $data['url'] ?? '',
                'order_num' => $data['orderNum'] ?? $order_num,
                'plat_order_num' => $data['platOrderNum'] ?? '',
                'va_number' => $data['vaNumber'] ?? '',
                'qr_string' => $data['qrString'] ?? '',
            );
        }

        return array(
            'success' => false,
            'message' => $data['platRespMessage'] ?? 'API Error HTTP ' . $http_code,
        );
    }

    public function processCallback($data) {
        require_once DIR_APPLICATION . 'model/extension/payment/BDPayCallbackVerifier.php';

        if (!is_array($data)) {
            $data = BDPayCallbackVerifier::parsePayload(is_string($data) ? $data : null, array());
        }
        if (empty($data)) return;

        $publicKey = $this->config->get('payment_bdpay_public_key');
        $env = $this->config->get('payment_bdpay_environment') ?: 'sandbox';
        $verified = BDPayCallbackVerifier::verify($data, $publicKey ?: '', $env === 'production');

        if (!$verified['valid']) {
            // Log invalid callback but still return (controller echoes OK)
            return;
        }

        $order_id = BDPayCallbackVerifier::extractOrderId($verified['order_num']);
        if (!$order_id) return;

        $this->load->model('checkout/order');

        if ($verified['is_success']) {
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                $this->config->get('payment_bdpay_order_status_id'),
                'BDPay Payment Success. Ref: ' . $verified['plat_order_num'] . ' Amount: ' . $verified['amount'],
                true
            );
        } elseif ($verified['is_failed']) {
            // Optional: set cancelled status if configured
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                7, // default Cancelled in many OC installs – adjust as needed
                'BDPay Payment Failed/Expired: ' . $verified['status'],
                true
            );
        }
    }
}
