<?php
/*
Plugin Name: Pix Ai Gateway
Description: Plugin Pix Aí para o WooCommerce.
Version: 1.0.2
Author: Pix Aí Tecnologia
Author URI: www.pixai.com.br
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: woocommerce-pix-ai-gateway
*/

add_action('plugins_loaded', 'woocommerce_myplugin', 0);
function woocommerce_myplugin()
{
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class 

    include(plugin_dir_path(__FILE__) . 'class-gateway.php');
}

add_action('wp_ajax_pix_ai_update_order', 'pix_ai_update_order_status');
add_action('wp_ajax_nopriv_pix_ai_update_order', 'pix_ai_update_order_status');

add_action('rest_api_init', function () {
    register_rest_route('pixai/v1', '/verify-order/(?P<order_id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'verify_order',
        'permission_callback' => '__return_true', // Adjust permissions as needed
    ]);
});
function verify_order(WP_REST_Request $request)
{
    // Retrieve order_id from the URL parameter
    $order_id = $request->get_param('order_id');

    if (!$order_id) {
        return new WP_REST_Response(['error' => 'Missing order ID'], 400);
    }

    // Load WooCommerce order
    $order = wc_get_order(intval($order_id));

    if (!$order) {
        return new WP_REST_Response(['error' => 'Invalid order'], 404);
    }

    // Update order status to completed
    $order->update_status('completed', __('Payment confirmed via Pix Ai.', 'my-woocommerce-pix-ai-gateway'));
    $order->save();

    return new WP_REST_Response(['message' => 'Order marked as completed'], 200);
}

function pix_ai_update_order_status()
{
    if (!isset($_POST['order_id'])) {
        wp_send_json_error(['message' => 'Missing order ID']);
        exit;
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Invalid order']);
        exit;
    }

    // Update order status to completed
    $order->update_status('completed', __('Payment confirmed via Pix Ai.', 'my-woocommerce-pix-ai-gateway'));
    $order->save();

    wp_send_json_success(['message' => 'Order marked as completed']);
}

add_action('woocommerce_checkout_order_processed', 'pix_ai_prevent_duplicate_order', 10, 1);

function pix_ai_prevent_duplicate_order($order_id)
{
    $already_processed = get_post_meta($order_id, '_pixai_order_processed', true);

    error_log("validando order");

    if ($already_processed) {
        error_log("Ordem já processada: " . $order_id);
        return;
    }

    update_post_meta($order_id, '_pixai_order_processed', 'yes');

    // Aqui entra a lógica de atualização do pedido normalmente
}



add_action('wp_enqueue_scripts', function () {
    if (function_exists('is_checkout') && is_checkout()) {
        wp_enqueue_script(
            'my_pix_ai_gateway',
            plugins_url('checkout.js', __FILE__),
            ['wp-element', 'wc-blocks-registry', 'wc-settings'], // Ensure dependencies
            filemtime(plugin_dir_path(__FILE__) . 'checkout.js'),
            true
        );


        $nonce = wp_create_nonce('wc_store_api');
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;


        // Pass AJAX URL and security nonce to JavaScript
        wp_localize_script('my_pix_ai_gateway', 'pixAiSettings', [
            'storeApiUrl' => get_rest_url(null, 'wc/store/v1'),
            'orderApiUrl' => get_rest_url(null, 'wc/v3/orders'),
            'checkApiUrl' => get_rest_url(null, 'pixai/v1/verify-order'),
            'nonce'    => $nonce,
            'order_id' => $order_id
        ]);

        error_log("WooCommerce Nonce Generated: " . $nonce);
    }
});


add_filter('woocommerce_payment_gateways', 'add_my_pix_ai_gateway');

add_action('wp_ajax_get_pixai_payment_data', 'get_pixai_payment_data');
add_action('wp_ajax_nopriv_get_pixai_payment_data', 'get_pixai_payment_data'); // Allow guest users

function get_pixai_payment_data()
{
    if (WC()->session) {
        $payment_data = WC()->session->get('pixai_payment_data');

        if (!empty($payment_data)) {
            wp_send_json(json_decode($payment_data, true)); // Send stored session data
        } else {
            wp_send_json_error('No payment data found');
        }
    } else {
        wp_send_json_error('Session not initialized');
    }
}

add_action('wp_ajax_clear_pixai_payment_data', 'clear_pixai_payment_data');
add_action('wp_ajax_nopriv_clear_pixai_payment_data', 'clear_pixai_payment_data'); // Allow guests

function clear_woocommerce_cart()
{
    if (WC()->cart) {
        WC()->cart->empty_cart();
    }
}


function clear_pixai_payment_data()
{

    clear_woocommerce_cart();

    if (WC()->session) {
        WC()->session->__unset('pixai_payment_data'); // Remove session data
        wp_send_json_success(array('message' => 'Session data cleared successfully'));
    } else {
        wp_send_json_error('Session not initialized');
    }
}

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
