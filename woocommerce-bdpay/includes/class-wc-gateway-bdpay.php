<?php
/**
 * WC_Gateway_BDPay Class
 */

defined('ABSPATH') || exit;

class WC_Gateway_BDPay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'bdpay';
        $this->icon               = BDPAY_PLUGIN_URL . 'assets/bdpay-logo.png';
        $this->has_fields         = true;
        $this->method_title       = __('PAGI', 'pagi-gateway');
        $this->method_description = __('Terima pembayaran via Virtual Account, QRIS, dan e-Wallet (DANA, OVO) menggunakan BDPay Open API.', 'pagi-gateway');
        $this->supports           = array('products');

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title              = $this->get_option('title', 'PAGI — VA / QRIS / e-Wallet');
        $this->description        = $this->get_option('description', 'Bayar dengan Virtual Account, QRIS, DANA, atau OVO.');
        $this->enabled            = $this->get_option('enabled');
        $this->merchant_code      = $this->get_option('merchant_code');
        $this->secret_key         = $this->get_option('secret_key');
        $this->public_key         = $this->get_option('public_key');
        $this->environment        = $this->get_option('environment', 'sandbox');
        $this->enabled_methods    = $this->get_option('enabled_methods', array('QRIS', 'VA_BCA', 'VA_MANDIRI', 'VA_BNI', 'VA_BRI', 'EWALLET_DANA', 'EWALLET_OVO'));

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_bdpay_callback', array($this, 'check_callback')); // legacy
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
    }

    /**
     * Admin form fields (CRUD settings)
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'pagi-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Aktifkan Payment Aggregator & Gateway Indonesia (PAGI)', 'pagi-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'pagi-gateway'),
                'type'        => 'text',
                'description' => __('Judul yang muncul di halaman checkout.', 'pagi-gateway'),
                'default'     => 'PAGI — VA / QRIS / e-Wallet',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'pagi-gateway'),
                'type'        => 'textarea',
                'description' => __('Deskripsi metode pembayaran.', 'pagi-gateway'),
                'default'     => 'Bayar dengan mudah menggunakan Virtual Account bank, QRIS, DANA, atau OVO.',
            ),
            'environment' => array(
                'title'       => __('Environment', 'pagi-gateway'),
                'type'        => 'select',
                'description' => __('Pilih mode Sandbox atau Production.', 'pagi-gateway'),
                'default'     => 'sandbox',
                'options'     => array(
                    'sandbox'    => 'Sandbox (Testing)',
                    'production' => 'Production (Live)',
                ),
            ),
            'merchant_code' => array(
                'title'       => __('Merchant Code', 'pagi-gateway'),
                'type'        => 'text',
                'description' => __('Kode merchant dari dashboard BDPay.', 'pagi-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'secret_key' => array(
                'title'       => __('Secret Key', 'pagi-gateway'),
                'type'        => 'password',
                'description' => __('Secret Key dari BDPay (untuk signature).', 'pagi-gateway'),
                'default'     => '',
            ),
            'public_key' => array(
                'title'       => __('Public Key', 'pagi-gateway'),
                'type'        => 'textarea',
                'description' => __('Public Key dari BDPay (opsional, tergantung dokumentasi signature).', 'pagi-gateway'),
                'default'     => '',
            ),
            'enabled_methods' => array(
                'title'       => __('Metode Pembayaran Aktif', 'pagi-gateway'),
                'type'        => 'multiselect',
                'description' => __('Pilih metode yang ingin ditampilkan ke customer.', 'pagi-gateway'),
                'options'     => array(
                    'QRIS'          => 'QRIS (Semua e-Wallet & Bank)',
                    'VA_BCA'        => 'Virtual Account BCA',
                    'VA_MANDIRI'    => 'Virtual Account Mandiri',
                    'VA_BNI'        => 'Virtual Account BNI',
                    'VA_BRI'        => 'Virtual Account BRI',
                    'VA_PERMATA'    => 'Virtual Account Permata',
                    'VA_CIMB'       => 'Virtual Account CIMB',
                    'VA_DANAMON'    => 'Virtual Account Danamon',
                    'EWALLET_DANA'  => 'e-Wallet DANA',
                    'EWALLET_OVO'   => 'e-Wallet OVO',
                    'RETAIL_ALFAMART' => 'Alfamart',
                ),
                'default'     => array('QRIS', 'VA_BCA', 'VA_MANDIRI', 'VA_BNI', 'VA_BRI', 'EWALLET_DANA', 'EWALLET_OVO'),
            ),
            'callback_url' => array(
                'title'       => __('Callback URL', 'pagi-gateway'),
                'type'        => 'text',
                'description' => __('Salin URL ini ke dashboard BDPay sebagai Notify/Callback URL.', 'pagi-gateway'),
                'default'     => home_url('/wp-json/bdpay/v1/callback'),
                'custom_attributes' => array('readonly' => 'readonly'),
            ),
        );
    }

    /**
     * Payment fields on checkout (method selection)
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        $methods = (array) $this->enabled_methods;
        if (empty($methods)) {
            echo '<p>' . esc_html__('Tidak ada metode pembayaran yang diaktifkan.', 'pagi-gateway') . '</p>';
            return;
        }

        echo '<fieldset id="bdpay-payment-methods">';
        echo '<legend>' . esc_html__('Pilih Metode Pembayaran', 'pagi-gateway') . '</legend>';

        $first = true;
        foreach ($methods as $code) {
            $label = $this->get_method_label($code);
            echo '<label style="display:block;margin:6px 0;">';
            echo '<input type="radio" name="bdpay_payment_method" value="' . esc_attr($code) . '" ' . ($first ? 'checked' : '') . '> ';
            echo esc_html($label);
            echo '</label>';
            $first = false;
        }
        echo '</fieldset>';
    }

    private function get_method_label($code) {
        $labels = array(
            'QRIS'            => 'QRIS (Scan semua e-Wallet & Bank)',
            'VA_BCA'          => 'Virtual Account BCA',
            'VA_MANDIRI'      => 'Virtual Account Mandiri',
            'VA_BNI'          => 'Virtual Account BNI',
            'VA_BRI'          => 'Virtual Account BRI',
            'VA_PERMATA'      => 'Virtual Account Permata',
            'VA_CIMB'         => 'Virtual Account CIMB',
            'VA_DANAMON'      => 'Virtual Account Danamon',
            'EWALLET_DANA'    => 'DANA',
            'EWALLET_OVO'     => 'OVO',
            'RETAIL_ALFAMART' => 'Alfamart',
        );
        return isset($labels[$code]) ? $labels[$code] : $code;
    }

    /**
     * Validate fields
     */
    public function validate_fields() {
        if (empty($_POST['bdpay_payment_method'])) {
            wc_add_notice(__('Silakan pilih metode pembayaran BDPay.', 'pagi-gateway'), 'error');
            return false;
        }
        return true;
    }

    /**
     * Process payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return array('result' => 'fail');
        }

        $method = sanitize_text_field($_POST['bdpay_payment_method'] ?? 'QRIS');

        $api = new BDPay_API(array(
            'merchant_code' => $this->merchant_code,
            'secret_key'    => $this->secret_key,
            'public_key'    => $this->public_key,
            'environment'   => $this->environment,
        ));

        $result = $api->create_payment(array(
            'order_id'      => $order->get_order_number(),
            'amount'        => $order->get_total(),
            'method'        => $method,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email'=> $order->get_billing_email(),
            'customer_phone'=> $order->get_billing_phone() ?: '081234567890',
            'description'   => 'Order #' . $order->get_order_number(),
            'notify_url'    => home_url('/wp-json/bdpay/v1/callback'),
            'return_url'    => $this->get_return_url($order),
        ));

        if (is_wp_error($result) || empty($result['success'])) {
            $msg = is_wp_error($result) ? $result->get_error_message() : ($result['message'] ?? 'Gagal membuat pembayaran');
            wc_add_notice(__('BDPay Error: ', 'pagi-gateway') . $msg, 'error');
            return array('result' => 'fail');
        }

        // Save transaction data
        $order->update_meta_data('_bdpay_order_num', $result['order_num'] ?? '');
        $order->update_meta_data('_bdpay_plat_order_num', $result['plat_order_num'] ?? '');
        $order->update_meta_data('_bdpay_payment_url', $result['url'] ?? '');
        $order->update_meta_data('_bdpay_method', $method);
        $order->update_meta_data('_bdpay_va_number', $result['va_number'] ?? '');
        $order->update_meta_data('_bdpay_qr_string', $result['qr_string'] ?? '');
        $order->save();

        $order->update_status('pending', __('Menunggu pembayaran BDPay.', 'pagi-gateway'));

        // Reduce stock
        wc_reduce_stock_levels($order_id);

        // Empty cart
        WC()->cart->empty_cart();

        // Redirect to payment page or thank you with instructions
        if (!empty($result['url'])) {
            return array(
                'result'   => 'success',
                'redirect' => $result['url'],
            );
        }

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Thank you / receipt page - show payment instructions
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $method   = $order->get_meta('_bdpay_method');
        $va       = $order->get_meta('_bdpay_va_number');
        $qr       = $order->get_meta('_bdpay_qr_string');
        $url      = $order->get_meta('_bdpay_payment_url');

        echo '<div class="bdpay-payment-instructions" style="background:#f8f9fa;padding:20px;border-radius:8px;margin:20px 0;">';
        echo '<h3>' . esc_html__('Instruksi Pembayaran BDPay', 'pagi-gateway') . '</h3>';

        if ($method && strpos($method, 'VA_') === 0 && $va) {
            echo '<p><strong>Nomor Virtual Account:</strong> <code style="font-size:1.3em;">' . esc_html($va) . '</code></p>';
            echo '<p>Silakan transfer sesuai nominal order ke nomor VA di atas sebelum kadaluarsa.</p>';
        } elseif ($method === 'QRIS' && $qr) {
            echo '<p>Scan QRIS berikut dengan aplikasi e-Wallet atau mobile banking Anda:</p>';
            // In production, generate QR image from $qr string
            echo '<p><em>(QR String tersimpan. Generate QR image di frontend atau gunakan library.)</em></p>';
        } elseif ($url) {
            echo '<p><a href="' . esc_url($url) . '" class="button" target="_blank">Lanjutkan ke Halaman Pembayaran</a></p>';
        }

        echo '</div>';
    }
}
