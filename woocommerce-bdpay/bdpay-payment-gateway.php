<?php
/**
 * Plugin Name: Payment Aggregator & Gateway Indonesia (PAGI)
 * Plugin URI:  https://github.com/FVKUALL/eCommerce-Plugins-Payment-Aggregator-Gateway-Indonesia-PAGI-
 * Description: Payment Aggregator & Gateway Indonesia (PAGI) — Virtual Account, QRIS, e-Wallet (DANA/OVO) via BDPay Open API.
 * Version:     1.0
 * Author:      Wiriasto
 * Author URI:  https://github.com/FVKUALL
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pagi-gateway
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * Copyright (C) 2026 Wiriasto
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

defined('ABSPATH') || exit;

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

define('BDPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BDPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BDPAY_VERSION', '1.0');

/**
 * Initialize the gateway
 */
add_action('plugins_loaded', 'bdpay_init_gateway', 11);

function bdpay_init_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once BDPAY_PLUGIN_PATH . 'includes/class-wc-gateway-bdpay.php';
    require_once BDPAY_PLUGIN_PATH . 'includes/class-bdpay-api.php';

    add_filter('woocommerce_payment_gateways', 'bdpay_add_gateway');
}

function bdpay_add_gateway($gateways) {
    $gateways[] = 'WC_Gateway_BDPay';
    return $gateways;
}

/**
 * Register webhook endpoint
 */
add_action('rest_api_init', function () {
    register_rest_route('bdpay/v1', '/callback', array(
        'methods'             => 'POST',
        'callback'            => 'bdpay_handle_callback',
        'permission_callback' => '__return_true',
    ));
});

function bdpay_handle_callback(WP_REST_Request $request) {
    $api = new BDPay_API();
    return $api->process_callback($request->get_json_params() ?: $request->get_body_params());
}

/**
 * Plugin activation
 */
register_activation_hook(__FILE__, function () {
    // Flush rewrite rules for REST endpoint
    flush_rewrite_rules();
});
