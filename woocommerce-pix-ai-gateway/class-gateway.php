<?php
class My_Custom_Gateway extends WC_Payment_Gateway
{

  // Constructor method
  public function __construct()
  {
    $this->id                 = 'my_pix_ai_gateway';
    $this->method_title       = __('Pix Ai Gateway', 'my-woocommerce-pix-ai-gateway');
    $this->method_description = __('Accept payments through My Pix Ai Gateway', 'my-woocommerce-pix-ai-gateway');

    // Other initialization code goes here

    $this->init_form_fields();
    $this->init_settings();

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => __('Enable/Disable', 'my-woocommerce-pix-ai-gateway'),
        'type'    => 'checkbox',
        'label'   => __('Enable My Pix Ai Gateway', 'my-woocommerce-pix-ai-gateway'),
        'default' => 'yes',
      ),
      'api_token' => array(
        'title'       => __('API Token', 'my-woocommerce-pix-ai-gateway'),
        'type'        => 'text',
        'description' => __('Enter your API token for Pix Ai Gateway.', 'my-woocommerce-pix-ai-gateway'),
        'default'     => '',
      ),
      // Add more settings fields as needed
    );
  }

  public function payment_fields()
  {
    echo '<p>Escolha Pix Ai para gerar um QR Code e pagar rapidamente.</p>';
    echo '<div id="pix-ai-qr-container" style="display: none; text-align: center;">
            <h4>Escaneie o QR Code</h4>
            <img id="pix-ai-qr-code" src="" alt="QR Code Pix" />
          </div>';
  }

  // Process the payment
  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);
    $amount = $order->get_total() * 100; // Convert to cents
    $api_token = $this->get_option('api_token');

    error_log('API Token: ' . $api_token);

    // API request to create a Pix payment
    $response = wp_remote_post('https://manager.pixai.com.br/api/integration/payment-initiation', array(
      'method'    => 'POST',
      'body'      => json_encode(array(
        'amount' => $amount,
        'description' => 'Order from ' . get_bloginfo('name') . ' - Order ID: ' . $order_id,
        'external_custom_integration_id' => (string) $order_id,
      )),
      'headers'   => array(
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $api_token,
      ),
    ));

    if (is_wp_error($response)) {
      wc_add_notice('Error creating Pix payment.', 'error');
      return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);


    if (isset($body['localPayment']['pix_qr_code'])) {
      // Save the payment data
      update_post_meta($order_id, '_pix_qrcode', $body['localPayment']['pix_qr_code']);

      // Mark the order as on-hold
      $order->update_status('on-hold', __('Awaiting Pix payment.', 'my-woocommerce-pix-ai-gateway'));
      $order->update_meta_data('_pix_qr_code', $body['localPayment']['pix_qr_code']);
      $order->save();

      // Redirect to the payment page
      error_log('body: ' . $body['localPayment']['pix_qr_code']);
      // return array(
      //   'result'   => 'success',
      //   'redirect' => $this->get_return_url($order),
      // );

      return array(
        'result'   => 'success',
        'qr_code'  => $body['localPayment']['pix_qr_code'], // Send QR Code to JS
        'payment_identification' => $body['localPayment']['payment_identification'], // Send payment identification to JS
        'order_id' => $order_id, // Pass order ID for tracking
        'api_token' => $api_token, // Pass API token for tracking
      );
    }

    wc_add_notice('Failed to process Pix payment.', 'error');
    return;
  }
}
