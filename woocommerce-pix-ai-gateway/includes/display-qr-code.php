
<?php
add_action('woocommerce_thankyou', 'display_pix_qr_code', 10);

function display_pix_qr_code($order_id)
{
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    $pix_qr_code = $order->get_meta('_pix_qr_code');

    if ($pix_qr_code) {
        echo '<div id="pix-ai-qr-code">';
        echo '<h2>Pagamento via Pix</h2>';
        echo '<p>Escaneie o QR Code para concluir o pagamento:</p>';
        echo '<img src="https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($pix_qr_code) . '&size=200x200" alt="QR Code Pix" />';
        echo '</div>';
    }
}
