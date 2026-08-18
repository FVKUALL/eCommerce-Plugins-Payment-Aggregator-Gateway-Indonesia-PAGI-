<?php
class BdpayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        $cart = $this->context->cart;
        if (!$this->module->active || $cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $method = Tools::getValue('method', 'QRIS');
        $result = $this->module->createPayment($cart, $method);

        if (!empty($result['success']) && !empty($result['url'])) {
            Tools::redirect($result['url']);
        }

        // Validate order as pending then show instructions
        $customer = new Customer($cart->id_customer);
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $this->module->validateOrder(
            (int) $cart->id,
            Configuration::get('PS_OS_PREPARATION'),
            $total,
            $this->module->displayName,
            null,
            [],
            (int) $this->context->currency->id,
            false,
            $customer->secure_key
        );

        $this->context->smarty->assign([
            'result' => $result,
            'method' => $method,
        ]);
        $this->setTemplate('module:bdpay/views/templates/front/payment_result.tpl');
    }
}
