<?php
/**
 * BDPay API Handler
 * Signature implemented according to BDPay Open API patterns (RSA-SHA256 / HMAC fallback)
 * Docs: https://dc.bdpay.co.id/docs/  |  https://document.bdpay.co.id/docs/api/
 */

defined('ABSPATH') || exit;

require_once dirname(__FILE__) . '/BDPaySignature.php';
require_once dirname(__FILE__) . '/BDPayCallbackVerifier.php';

class BDPay_API {

    private $merchant_code;
    private $secret_key;   // used as private key (PEM) or HMAC secret
    private $public_key;   // platform public key for verifying platSign
    private $environment;
    private $base_url;
    private $sign_algo;    // 'RSA' or 'HMAC'

    public function __construct($config = array()) {
        $this->merchant_code = $config['merchant_code'] ?? '';
        $this->secret_key    = $config['secret_key'] ?? '';
        $this->public_key    = $config['public_key'] ?? '';
        $this->environment   = $config['environment'] ?? 'sandbox';
        $this->sign_algo     = $config['sign_algo'] ?? 'RSA';
        $this->base_url      = ($this->environment === 'production')
            ? 'https://openapi.bdpay.co.id'
            : 'https://dev-openapi.bdpay.co.id';
    }

    /**
     * Create payment (VA / QRIS / e-Wallet / Payment Link)
     * POST /gateway/prepaidOrder
     */
    public function create_payment($params) {
        $order_num = $params['order_id'] ?? ('ORD-' . time());
        $amount    = number_format((float) $params['amount'], 0, '', '');
        $method    = $params['method'] ?? 'QRIS';

        $body = array(
            'merchantCode'  => $this->merchant_code,
            'orderNum'      => $order_num,
            'payMoney'      => $amount,
            'method'        => $method,
            'productDetail' => $params['description'] ?? 'Pembayaran Order',
            'name'          => $params['customer_name'] ?? 'Customer',
            'email'         => $params['customer_email'] ?? '',
            'phone'         => $params['customer_phone'] ?? '',
            'notifyUrl'     => $params['notify_url'] ?? '',
            'expiryPeriod'  => '60',
            'dateTime'      => gmdate('Y-m-d\TH:i:s\Z'),
        );

        try {
            $body['sign'] = BDPaySignature::generate($body, $this->secret_key, $this->sign_algo);
        } catch (Exception $e) {
            return new WP_Error('bdpay_sign_error', $e->getMessage());
        }

        $response = $this->request('POST', '/gateway/prepaidOrder', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Optional: verify platSign if public key provided
        if (!empty($this->public_key) && !empty($response['platSign'])) {
            if (!BDPaySignature::verify($response, $this->public_key)) {
                return new WP_Error('bdpay_verify_error', 'Invalid platSign from BDPay response');
            }
        }

        if (isset($response['platRespCode']) && $response['platRespCode'] === 'SUCCESS') {
            return array(
                'success'        => true,
                'order_num'      => $response['orderNum'] ?? $order_num,
                'plat_order_num' => $response['platOrderNum'] ?? '',
                'url'            => $response['url'] ?? '',
                'va_number'      => $response['vaNumber'] ?? $response['accountNumber'] ?? '',
                'qr_string'      => $response['qrString'] ?? $response['qrCode'] ?? '',
                'raw'            => $response,
            );
        }

        return array(
            'success' => false,
            'message' => $response['platRespMessage'] ?? 'Unknown error from BDPay',
            'raw'     => $response,
        );
    }

    /**
     * Process callback / webhook — verifikasi platSign + update order
     */
    public function process_callback($data) {
        if (!is_array($data)) {
            $data = BDPayCallbackVerifier::parsePayload(is_string($data) ? $data : null, (array) $data);
        }

        $require_sign = ($this->environment === 'production');
        $verified = BDPayCallbackVerifier::verify($data, $this->public_key, $require_sign);

        if (!$verified['valid']) {
            return new WP_REST_Response(array(
                'status'  => 'error',
                'message' => $verified['message'],
            ), 400);
        }

        $order_num = $verified['order_num'];
        $orders = wc_get_orders(array(
            'limit'      => 1,
            'meta_key'   => '_bdpay_order_num',
            'meta_value' => $order_num,
        ));
        $order = !empty($orders) ? $orders[0] : null;
        if (!$order) {
            $oid = BDPayCallbackVerifier::extractOrderId($order_num);
            if ($oid) {
                $order = wc_get_order($oid);
            }
        }
        if (!$order) {
            return new WP_REST_Response(array('status' => 'order not found'), 404);
        }

        // Idempotent: jangan double-complete
        if ($verified['is_success']) {
            if (!in_array($order->get_status(), array('processing', 'completed'), true)) {
                $order->payment_complete($verified['plat_order_num']);
                $order->add_order_note(
                    sprintf(
                        __('Pembayaran BDPay berhasil. OrderNum: %s | Ref: %s | Amount: %s', 'bdpay-gateway'),
                        $order_num,
                        $verified['plat_order_num'],
                        $verified['amount']
                    )
                );
                $order->update_meta_data('_bdpay_paid', 'yes');
                $order->update_meta_data('_bdpay_callback_status', $verified['status']);
                $order->save();
            }
        } elseif ($verified['is_failed']) {
            if (!in_array($order->get_status(), array('cancelled', 'failed'), true)) {
                $order->update_status('cancelled', __('Pembayaran BDPay gagal/expired: ', 'bdpay-gateway') . $verified['status']);
            }
        }

        // Selalu 200 OK agar BDPay berhenti retry
        return new WP_REST_Response(array('status' => BDPayCallbackVerifier::okResponse()), 200);
    }

    private function request($method, $endpoint, $body = array()) {
        $url = rtrim($this->base_url, '/') . $endpoint;

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body' => $method !== 'GET' ? wp_json_encode($body) : null,
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new WP_Error('bdpay_api_error', 'HTTP ' . $code . ': ' . ($body['platRespMessage'] ?? 'Error'), $body);
        }

        return $body ?: array();
    }
}
