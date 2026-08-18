<?php
/**
 * Payment Aggregator & Gateway Indonesia (PAGI)
 * PrestaShop Module — Virtual Account, QRIS, e-Wallet (DANA/OVO)
 * Signature: RSA-SHA256 / HMAC sesuai dokumentasi BDPay
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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/BDPaySignature.php';

class Bdpay extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'bdpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0';
        $this->author = 'Wiriasto';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Payment Aggregator & Gateway Indonesia (PAGI)');
        $this->description = $this->l('PAGI — Terima pembayaran Virtual Account, QRIS, DANA, OVO via BDPay Open API.');
        $this->confirmUninstall = $this->l('Yakin ingin menghapus modul PAGI?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && Configuration::updateValue('BDPAY_ENV', 'sandbox')
            && Configuration::updateValue('BDPAY_MERCHANT_CODE', '')
            && Configuration::updateValue('BDPAY_SECRET_KEY', '')
            && Configuration::updateValue('BDPAY_PUBLIC_KEY', '')
            && Configuration::updateValue('BDPAY_SIGN_ALGO', 'RSA')
            && Configuration::updateValue('BDPAY_METHODS', json_encode(['QRIS', 'VA_BCA', 'VA_MANDIRI', 'VA_BNI', 'VA_BRI', 'EWALLET_DANA', 'EWALLET_OVO']));
    }

    public function uninstall()
    {
        return Configuration::deleteByName('BDPAY_ENV')
            && Configuration::deleteByName('BDPAY_MERCHANT_CODE')
            && Configuration::deleteByName('BDPAY_SECRET_KEY')
            && Configuration::deleteByName('BDPAY_PUBLIC_KEY')
            && Configuration::deleteByName('BDPAY_SIGN_ALGO')
            && Configuration::deleteByName('BDPAY_METHODS')
            && parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitBdpay')) {
            Configuration::updateValue('BDPAY_ENV', Tools::getValue('BDPAY_ENV'));
            Configuration::updateValue('BDPAY_MERCHANT_CODE', Tools::getValue('BDPAY_MERCHANT_CODE'));
            Configuration::updateValue('BDPAY_SECRET_KEY', Tools::getValue('BDPAY_SECRET_KEY'));
            Configuration::updateValue('BDPAY_PUBLIC_KEY', Tools::getValue('BDPAY_PUBLIC_KEY'));
            Configuration::updateValue('BDPAY_SIGN_ALGO', Tools::getValue('BDPAY_SIGN_ALGO'));
            $methods = Tools::getValue('BDPAY_METHODS');
            Configuration::updateValue('BDPAY_METHODS', json_encode(is_array($methods) ? $methods : []));
            $output .= $this->displayConfirmation($this->l('Pengaturan berhasil disimpan.'));
        }
        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $methods = json_decode(Configuration::get('BDPAY_METHODS'), true) ?: [];
        $allMethods = [
            'QRIS' => 'QRIS',
            'VA_BCA' => 'VA BCA',
            'VA_MANDIRI' => 'VA Mandiri',
            'VA_BNI' => 'VA BNI',
            'VA_BRI' => 'VA BRI',
            'EWALLET_DANA' => 'DANA',
            'EWALLET_OVO' => 'OVO',
        ];

        $fields = [
            'form' => [
                'legend' => ['title' => $this->l('Pengaturan BDPay')],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Environment'),
                        'name' => 'BDPAY_ENV',
                        'options' => [
                            'query' => [
                                ['id' => 'sandbox', 'name' => 'Sandbox'],
                                ['id' => 'production', 'name' => 'Production'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    ['type' => 'text', 'label' => $this->l('Merchant Code'), 'name' => 'BDPAY_MERCHANT_CODE', 'required' => true],
                    ['type' => 'textarea', 'label' => $this->l('Secret / Private Key'), 'name' => 'BDPAY_SECRET_KEY', 'required' => true],
                    ['type' => 'textarea', 'label' => $this->l('Public Key (platform)'), 'name' => 'BDPAY_PUBLIC_KEY'],
                    [
                        'type' => 'select',
                        'label' => $this->l('Signature Algorithm'),
                        'name' => 'BDPAY_SIGN_ALGO',
                        'options' => [
                            'query' => [
                                ['id' => 'RSA', 'name' => 'RSA-SHA256 (recommended)'],
                                ['id' => 'HMAC', 'name' => 'HMAC-SHA256'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Metode Aktif'),
                        'name' => 'BDPAY_METHODS',
                        'values' => [
                            'query' => array_map(function ($k, $v) {
                                return ['id' => $k, 'name' => $v, 'val' => $k];
                            }, array_keys($allMethods), $allMethods),
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => ['title' => $this->l('Simpan')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitBdpay';
        $helper->fields_value = [
            'BDPAY_ENV' => Configuration::get('BDPAY_ENV'),
            'BDPAY_MERCHANT_CODE' => Configuration::get('BDPAY_MERCHANT_CODE'),
            'BDPAY_SECRET_KEY' => Configuration::get('BDPAY_SECRET_KEY'),
            'BDPAY_PUBLIC_KEY' => Configuration::get('BDPAY_PUBLIC_KEY'),
            'BDPAY_SIGN_ALGO' => Configuration::get('BDPAY_SIGN_ALGO'),
        ];
        foreach ($allMethods as $code => $label) {
            $helper->fields_value['BDPAY_METHODS_' . $code] = in_array($code, $methods) ? 'on' : '';
        }

        return $helper->generateForm([$fields]);
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        $methods = json_decode(Configuration::get('BDPAY_METHODS'), true) ?: ['QRIS'];
        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('Bayar dengan BDPay (VA / QRIS / e-Wallet)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setAdditionalInformation($this->fetch('module:bdpay/views/templates/hook/payment_info.tpl'));

        return [$option];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        return $this->fetch('module:bdpay/views/templates/hook/payment_return.tpl');
    }

    /**
     * Create payment via BDPay API
     */
    public function createPayment($cart, $method = 'QRIS')
    {
        $env = Configuration::get('BDPAY_ENV');
        $baseUrl = $env === 'production' ? 'https://openapi.bdpay.co.id' : 'https://dev-openapi.bdpay.co.id';
        $merchantCode = Configuration::get('BDPAY_MERCHANT_CODE');
        $secret = Configuration::get('BDPAY_SECRET_KEY');
        $algo = Configuration::get('BDPAY_SIGN_ALGO') ?: 'RSA';

        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_invoice);
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $orderNum = $cart->id . '-' . time();

        $body = [
            'merchantCode'  => $merchantCode,
            'orderNum'      => $orderNum,
            'payMoney'      => (string) intval($total),
            'method'        => $method,
            'productDetail' => 'Order Cart #' . $cart->id,
            'name'          => $customer->firstname . ' ' . $customer->lastname,
            'email'         => $customer->email,
            'phone'         => $address->phone ?: '081234567890',
            'notifyUrl'     => $this->context->link->getModuleLink($this->name, 'callback', [], true),
            'expiryPeriod'  => '60',
            'dateTime'      => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $body['sign'] = BDPaySignature::generate($body, $secret, $algo);

        $ch = curl_init($baseUrl . '/gateway/prepaidOrder');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);

        if (isset($data['platRespCode']) && $data['platRespCode'] === 'SUCCESS') {
            return [
                'success' => true,
                'url' => $data['url'] ?? null,
                'order_num' => $data['orderNum'] ?? $orderNum,
                'va_number' => $data['vaNumber'] ?? null,
                'qr_string' => $data['qrString'] ?? null,
            ];
        }
        return ['success' => false, 'message' => $data['platRespMessage'] ?? 'API Error'];
    }
}
