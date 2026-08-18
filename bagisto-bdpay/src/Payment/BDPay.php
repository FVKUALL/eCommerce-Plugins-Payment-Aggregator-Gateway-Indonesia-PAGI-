<?php

namespace Webkul\BDPay\Payment;

use Webkul\Payment\Payment\Payment;
use Illuminate\Support\Facades\Http;

class BDPay extends Payment
{
    protected $code = 'bdpay';

    public function getRedirectUrl()
    {
        return route('bdpay.redirect');
    }

    /**
     * Create payment via BDPay Open API
     */
    public function createPayment(array $orderData, string $method = 'QRIS')
    {
        $config = [
            'merchant_code' => core()->getConfigData('sales.payment_methods.bdpay.merchant_code'),
            'secret_key'    => core()->getConfigData('sales.payment_methods.bdpay.secret_key'),
            'environment'   => core()->getConfigData('sales.payment_methods.bdpay.environment') ?: 'sandbox',
        ];

        $baseUrl = $config['environment'] === 'production'
            ? 'https://openapi.bdpay.co.id'
            : 'https://dev-openapi.bdpay.co.id';

        $body = [
            'merchantCode'  => $config['merchant_code'],
            'orderNum'      => $orderData['order_id'] . '-' . time(),
            'payMoney'      => (string) intval($orderData['grand_total']),
            'method'        => $method,
            'productDetail' => 'Order #' . $orderData['order_id'],
            'name'          => $orderData['customer_name'] ?? 'Customer',
            'email'         => $orderData['customer_email'] ?? '',
            'phone'         => $orderData['customer_phone'] ?? '081234567890',
            'notifyUrl'     => route('bdpay.callback'),
            'expiryPeriod'  => '60',
            'dateTime'      => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        require_once dirname(__DIR__) . '/BDPaySignature.php';
        $body['sign'] = \BDPaySignature::generate($body, $config['secret_key'], 'RSA');

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post($baseUrl . '/gateway/prepaidOrder', $body);

            $data = $response->json();

            if ($response->successful() && ($data['platRespCode'] ?? '') === 'SUCCESS') {
                return [
                    'success' => true,
                    'url' => $data['url'] ?? null,
                    'order_num' => $data['orderNum'] ?? $body['orderNum'],
                    'plat_order_num' => $data['platOrderNum'] ?? null,
                    'va_number' => $data['vaNumber'] ?? null,
                    'qr_string' => $data['qrString'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $data['platRespMessage'] ?? 'BDPay API error',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

}
