<?php
/**
 * Plugin Name: WooCommerce jhpay.online Payment Gateway
 * Plugin URI: https://jhpay.online
 * Description: Платёжный шлюз jhpay.online для WooCommerce
 * Author: Kudratulloh Mukhammedov
 * Author URI: https://t.me/kmukhammedov
 * Version: 1.0.0
 **/

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    require_once __DIR__ . '/vendor/autoload.php';
    include_once __DIR__ . '/includes/WcJhpayGateway.php';

    add_filter('woocommerce_payment_gateways', function (array $gateways): array
    {
        $gateways[] = 'WcJhpayGateway';
        return $gateways;
    });
});
