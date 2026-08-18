<?php
/**
 * BDPay Callback Controller
 * Verifikasi platSign lalu update status order.
 */
class BdpayCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_header = false;
    public $display_footer = false;

    public function postProcess()
    {
        require_once dirname(__FILE__) . '/../../BDPaySignature.php';
        require_once dirname(__FILE__) . '/../../BDPayCallbackVerifier.php';

        $raw  = Tools::file_get_contents('php://input');
        $data = BDPayCallbackVerifier::parsePayload($raw, Tools::getAllValues());

        $publicKey   = Configuration::get('BDPAY_PUBLIC_KEY');
        $env         = Configuration::get('BDPAY_ENV');
        $requireSign = ($env === 'production');

        $verified = BDPayCallbackVerifier::verify($data, $publicKey ?: '', $requireSign);

        if (!$verified['valid']) {
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => $verified['message']]));
        }

        $cartId = BDPayCallbackVerifier::extractOrderId($verified['order_num']);
        if ($cartId && $verified['is_success']) {
            $orderId = Order::getIdByCartId($cartId);
            if ($orderId) {
                $order = new Order((int) $orderId);
                if ((int) $order->current_state !== (int) Configuration::get('PS_OS_PAYMENT')) {
                    $history = new OrderHistory();
                    $history->id_order = (int) $order->id;
                    $history->changeIdOrderState((int) Configuration::get('PS_OS_PAYMENT'), $order);
                    $history->addWithemail(true);
                }
            }
        } elseif ($cartId && $verified['is_failed']) {
            $orderId = Order::getIdByCartId($cartId);
            if ($orderId) {
                $order = new Order((int) $orderId);
                $history = new OrderHistory();
                $history->id_order = (int) $order->id;
                $history->changeIdOrderState((int) Configuration::get('PS_OS_CANCELED'), $order);
                $history->addWithemail(false);
            }
        }

        header('Content-Type: text/plain');
        die(BDPayCallbackVerifier::okResponse());
    }
}
