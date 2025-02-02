<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Pix_Ai_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'pix_ai_gateway';
        $this->icon               = ''; // URL for the Pix A logo
        $this->has_fields         = true;
        $this->method_title       = 'Pix Ai Payment';
        $this->method_description = 'Receive payments via Pix Ai';

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled');
        $this->api_key     = $this->get_option('api_key');

        // Save admin options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function supports($feature) {
        if ($feature === 'payment_block_checkout') {
            return true; // Enable block-based checkout support
        }
        return parent::supports($feature);
    }

    // Admin settings fields
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Pix Ai Payment',
                'default' => 'yes'
            ],
            'title' => [
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'Payment title shown to users',
                'default'     => 'Pay with Pix Ai',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Payment description shown to users',
                'default'     => 'Use Pix Ai to complete your payment.',
            ],
            'api_key' => [
                'title'       => 'API Key',
                'type'        => 'text',
                'description' => 'Your Pix Ai API Key',
                'default'     => '',
            ],
        ];
    }

    // Process the payment
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Generate a Pix QR Code
        $pix_response = $this->generate_pix_payment($order);

        if (is_wp_error($pix_response)) {
            wc_add_notice('Payment error: ' . $pix_response->get_error_message(), 'error');
            return;
        }

        update_post_meta($order_id, '_pix_qr_code', $pix_data['pix_qr_code']);

        $order->update_status('on-hold', 'Awaiting Pix payment');

        // Redirect to QR Code page
        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    // Function to call Pix A API
    private function generate_pix_payment($order) {
        $api_url = 'https://manager.pixai.com.br/api/integration/payment-initiation'; // Example endpoint
        $api_key = $this->api_key;

        $body = [
            'amount'       => $order->get_total(),
            'description'    => $order->get_billing_email(),
            'external_custom_integration_id'    => $order->get_id(),
        ];

        $response = wp_remote_post($api_url, [
            'method'    => 'POST',
            'body'      => json_encode($body),
            'headers'   => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($response_body['pix_url'])) {
            return new WP_Error('pix_error', 'Failed to generate Pix QR Code');
        }

        return [
            'pix_qr_code'  => $response_body['pix_qr_code'],  // QR Code Image URL
        ];
    }

    public function payment_fields() {
        echo '<p>Scan the QR Code below to complete your Pix payment.</p>';
    
        // Generate the QR Code when the checkout page loads
        $order_id = WC()->session->get('order_awaiting_payment');
        
        if ($order_id) {
            $qr_code = $this->generate_pix_payment(wc_get_order($order_id));
            
            if (!is_wp_error($qr_code) && isset($qr_code['pix_qr_code'])) {
                echo '<p><img src="' . esc_url($qr_code['pix_qr_code']) . '" alt="Pix QR Code" /></p>';
                echo '<p>Or copy the Pix code: <br><strong>' . esc_html($qr_code['pix_copy_paste']) . '</strong></p>';
            } else {
                echo '<p style="color:red;">Failed to generate QR Code. Please try again.</p>';
            }
        } else {
            echo '<p style="color:red;">No order found.</p>';
        }
    }
}
