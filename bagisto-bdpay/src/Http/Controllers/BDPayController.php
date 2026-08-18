<?php

namespace Webkul\BDPay\Http\Controllers;

use Illuminate\Http\Request;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\BDPay\Payment\BDPay;

class BDPayController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected BDPay $bdpay
    ) {}

    public function redirect(Request $request)
    {
        $cart = Cart::getCart();
        if (!$cart) {
            return redirect()->route('shop.checkout.cart.index');
        }

        $order = $this->orderRepository->create(Cart::prepareDataForOrder());
        Cart::deActivateCart();

        $method = $request->get('method', 'QRIS');

        $result = $this->bdpay->createPayment([
            'order_id'       => $order->id,
            'grand_total'    => $order->grand_total,
            'customer_name'  => $order->customer_first_name . ' ' . $order->customer_last_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone ?? '081234567890',
        ], $method);

        if (!empty($result['success']) && !empty($result['url'])) {
            return redirect()->away($result['url']);
        }

        // Fallback: show instructions page or success with message
        session()->flash('error', $result['message'] ?? 'Gagal membuat pembayaran BDPay');
        return redirect()->route('shop.checkout.onepage.success');
    }

    public function callback(Request $request)
    {
        require_once dirname(__DIR__, 2) . '/BDPaySignature.php';
        require_once dirname(__DIR__, 2) . '/BDPayCallbackVerifier.php';

        $raw = $request->getContent();
        $data = \BDPayCallbackVerifier::parsePayload($raw, $request->all());

        $publicKey = core()->getConfigData('sales.payment_methods.bdpay.public_key') ?? '';
        $env = core()->getConfigData('sales.payment_methods.bdpay.environment') ?: 'sandbox';
        $verified = \BDPayCallbackVerifier::verify($data, $publicKey, $env === 'production');

        if (!$verified['valid']) {
            return response(json_encode(['status' => 'error', 'message' => $verified['message']]), 400)
                ->header('Content-Type', 'application/json');
        }

        $orderId = \BDPayCallbackVerifier::extractOrderId($verified['order_num']);
        if ($orderId && $verified['is_success']) {
            $order = $this->orderRepository->find($orderId);
            if ($order && $order->status !== 'processing' && $order->status !== 'completed') {
                $this->orderRepository->update(['status' => 'processing'], $orderId);
            }
        } elseif ($orderId && $verified['is_failed']) {
            $order = $this->orderRepository->find($orderId);
            if ($order) {
                $this->orderRepository->update(['status' => 'canceled'], $orderId);
            }
        }

        return response(\BDPayCallbackVerifier::okResponse(), 200);
    }
}
