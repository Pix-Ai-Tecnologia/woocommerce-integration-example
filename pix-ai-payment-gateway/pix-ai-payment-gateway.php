<?php

/**
 * Plugin Name: Pix Ai Payment Gateway for WooCommerce
 * Plugin URI: https://pixai.com.br
 * Description: Accept payments using Pix Ai in WooCommerce
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://pixai.com.br
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Register payment gateway
function pix_ai_payment_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once 'includes/class-pix-ai-payment-gateway.php';

    function add_pix_ai_payment_gateway($methods)
    {
        $methods[] = 'WC_Pix_Ai_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_pix_ai_payment_gateway');
}

add_action('woocommerce_thankyou', 'display_pix_qr_code_after_checkout', 10, 1);

function display_pix_qr_code_after_checkout($order_id)
{
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    $payment_method = $order->get_payment_method();

    if ($payment_method === 'pix_a_gateway') {
        $pix_qr_code = get_post_meta($order_id, '_pix_qr_code', true);
        $pix_copy_paste = get_post_meta($order_id, '_pix_copy_paste', true);

        if ($pix_qr_code) {
            // echo '<h2>Scan the QR Code to Pay</h2>';
            // echo '<p><img src="' . esc_url($pix_qr_code) . '" alt="Pix QR Code"></p>';
            echo '<p>Or copy and paste this Pix code:</p>';
            echo '<strong>' . esc_html($pix_copy_paste) . '</strong>';
        } else {
            echo '<p style="color:red;">No QR Code found. Please check your email for payment instructions.</p>';
        }
    }
}

add_action('plugins_loaded', 'pix_ai_payment_gateway_init', 11);

add_action('woocommerce_blocks_loaded', function() {
    add_filter('woocommerce_blocks_register_payment_method_type', function($payment_methods) {
        $payment_methods['pix_a_gateway'] = [
            'title'       => 'Pix Ai Payment',
            'description' => 'Receive payments via Pix Ai',
            'supports'    => ['products', 'cart', 'checkout'],
        ];
        return $payment_methods;
    });
});
