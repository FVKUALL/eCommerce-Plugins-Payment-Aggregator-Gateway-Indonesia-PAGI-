<?php
namespace BDPay\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Callback extends Action implements CsrfAwareActionInterface
{
    protected $orderRepository;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        require_once dirname(__DIR__, 2) . '/BDPaySignature.php';
        require_once dirname(__DIR__, 2) . '/BDPayCallbackVerifier.php';

        $raw = $this->getRequest()->getContent();
        $data = \BDPayCallbackVerifier::parsePayload($raw, $this->getRequest()->getParams());

        $publicKey = (string) $this->scopeConfig->getValue('payment/bdpay/public_key', ScopeInterface::SCOPE_STORE);
        $env = (string) $this->scopeConfig->getValue('payment/bdpay/environment', ScopeInterface::SCOPE_STORE) ?: 'sandbox';
        $verified = \BDPayCallbackVerifier::verify($data, $publicKey, $env === 'production');

        $response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);

        if (!$verified['valid']) {
            $response->setHttpResponseCode(400);
            $response->setContents(json_encode(['status' => 'error', 'message' => $verified['message']]));
            return $response;
        }

        $orderId = \BDPayCallbackVerifier::extractOrderId($verified['order_num']);
        // Magento often uses increment_id in orderNum; try load by increment_id first
        try {
            if ($orderId) {
                $order = $this->orderRepository->get($orderId);
            } else {
                $order = null;
            }
        } catch (\Exception $e) {
            $order = null;
        }

        if ($order && $verified['is_success']) {
            if ($order->getState() !== Order::STATE_PROCESSING && $order->getState() !== Order::STATE_COMPLETE) {
                $order->setState(Order::STATE_PROCESSING)
                    ->setStatus(Order::STATE_PROCESSING)
                    ->addCommentToStatusHistory('BDPay Payment Success. Ref: ' . $verified['plat_order_num']);
                $this->orderRepository->save($order);
            }
        } elseif ($order && $verified['is_failed']) {
            $order->setState(Order::STATE_CANCELED)
                ->setStatus(Order::STATE_CANCELED)
                ->addCommentToStatusHistory('BDPay Payment Failed: ' . $verified['status']);
            $this->orderRepository->save($order);
        }

        $response->setContents(\BDPayCallbackVerifier::okResponse());
        return $response;
    }
}
