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
    private $error = array();

    public function index() {
        $this->load->language('extension/payment/bdpay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_bdpay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_sandbox'] = 'Sandbox';
        $data['text_production'] = 'Production';

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_merchant_code'] = 'Merchant Code';
        $data['entry_secret_key'] = 'Secret Key';
        $data['entry_public_key'] = 'Public Key';
        $data['entry_environment'] = 'Environment';
        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['entry_methods'] = 'Enabled Methods';

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/bdpay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/bdpay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $fields = array('status', 'merchant_code', 'secret_key', 'public_key', 'environment', 'order_status_id', 'sort_order', 'methods');
        foreach ($fields as $field) {
            if (isset($this->request->post['payment_bdpay_' . $field])) {
                $data['payment_bdpay_' . $field] = $this->request->post['payment_bdpay_' . $field];
            } else {
                $data['payment_bdpay_' . $field] = $this->config->get('payment_bdpay_' . $field);
            }
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['method_options'] = array(
            'QRIS' => 'QRIS',
            'VA_BCA' => 'VA BCA',
            'VA_MANDIRI' => 'VA Mandiri',
            'VA_BNI' => 'VA BNI',
            'VA_BRI' => 'VA BRI',
            'EWALLET_DANA' => 'DANA',
            'EWALLET_OVO' => 'OVO'
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/bdpay', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/bdpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    public function install() {
        // Optional: create table if needed
    }

    public function uninstall() {
        // Cleanup
    }
}
