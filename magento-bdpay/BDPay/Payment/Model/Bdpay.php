<?php
namespace BDPay\Payment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Bdpay extends AbstractMethod
{
    const CODE = 'bdpay';

    protected $_code = self::CODE;
    protected $_isOffline = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_isInitializeNeeded = true;

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }

    public function getConfigData($field, $storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'payment/bdpay/' . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Create payment via BDPay Open API with proper signature
     */
    public function createPayment(array $orderData, string $method = 'QRIS'): array
    {
        $env = $this->getConfigData('environment') ?: 'sandbox';
        $baseUrl = $env === 'production' ? 'https://openapi.bdpay.co.id' : 'https://dev-openapi.bdpay.co.id';
        $merchantCode = $this->getConfigData('merchant_code');
        $secret = $this->getConfigData('secret_key');
        $algo = $this->getConfigData('sign_algo') ?: 'RSA';

        require_once dirname(__DIR__) . '/BDPaySignature.php';

        $body = [
            'merchantCode'  => $merchantCode,
            'orderNum'      => $orderData['increment_id'] . '-' . time(),
            'payMoney'      => (string) intval($orderData['grand_total']),
            'method'        => $method,
            'productDetail' => 'Order #' . $orderData['increment_id'],
            'name'          => $orderData['customer_name'] ?? 'Customer',
            'email'         => $orderData['customer_email'] ?? '',
            'phone'         => $orderData['customer_phone'] ?? '081234567890',
            'notifyUrl'     => $orderData['notify_url'] ?? '',
            'expiryPeriod'  => '60',
            'dateTime'      => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $body['sign'] = \BDPaySignature::generate($body, $secret, $algo);

        $ch = curl_init($baseUrl . '/gateway/prepaidOrder');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);

        if ($code >= 200 && $code < 300 && ($data['platRespCode'] ?? '') === 'SUCCESS') {
            return [
                'success' => true,
                'url' => $data['url'] ?? null,
                'order_num' => $data['orderNum'] ?? $body['orderNum'],
                'va_number' => $data['vaNumber'] ?? null,
                'qr_string' => $data['qrString'] ?? null,
            ];
        }
        return ['success' => false, 'message' => $data['platRespMessage'] ?? 'API Error'];
    }
}
