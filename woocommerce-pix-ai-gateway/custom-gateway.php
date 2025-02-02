<?php
/*
Plugin Name: Pix Ai Gateway
Description: A custom payment gateway for WooCommerce.
Version: 1.0.0
Author: Dev team Sevengits
Author URI: sevengits.com
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: woocommerce-pix-ai-gateway
Domain Path: /languages
*/

// Your plugin code goes here

add_action('plugins_loaded', 'woocommerce_myplugin', 0);
function woocommerce_myplugin()
{
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class 

    include(plugin_dir_path(__FILE__) . 'class-gateway.php');
}



add_action('wp_enqueue_scripts', function () {
    if (function_exists('is_checkout') && is_checkout()) {
        // wp_enqueue_script(
        //     'my_pix_ai_gateway',
        //     plugins_url('checkout.js', __FILE__),
        //     ['wp-element', 'wc-blocks-registry', 'wc-settings'], // Ensure dependencies
        //     filemtime(plugin_dir_path(__FILE__) . 'checkout.js'),
        //     true
        // );

        wp_register_script(
            'my_pix_ai_gateway',
            plugins_url('checkout.js', __FILE__),
            ['wp-element', 'wc-blocks-registry', 'wc-settings'], // Load dependencies
            filemtime(plugin_dir_path(__FILE__) . 'checkout.js'),
            true
        );

        // Pass AJAX URL and security nonce to JavaScript
        wp_localize_script('my_pix_ai_gateway', 'pixAiSettings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pix_ai_nonce')
        ]);

        // Finally enqueue the script
        wp_enqueue_script('my_pix_ai_gateway');
    }
});


add_filter('woocommerce_payment_gateways', 'add_my_pix_ai_gateway');

function add_my_pix_ai_gateway($gateways)
{
    $gateways[] = 'My_Custom_Gateway';
    return $gateways;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
 */
function declare_cart_checkout_blocks_compatibility()
{
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action('woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type');

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type()
{
    // Check if the required class exists
    if (! class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register(new My_Custom_Gateway_Blocks);
        }
    );
}
