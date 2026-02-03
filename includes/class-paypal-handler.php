<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_PayPal_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('template_redirect', array($this, 'process'));
    }

    public function process() {
        if (!isset($_POST['ph_action']) || $_POST['ph_action'] !== 'buy') {
            return;
        }

        $product_id = isset($_POST['ph_product_id']) ? intval($_POST['ph_product_id']) : 0;

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ph_buy_' . $product_id)) {
            wp_die('Security check failed.');
        }

        $buyer_name  = sanitize_text_field($_POST['ph_buyer_name'] ?? '');
        $buyer_email = sanitize_email($_POST['ph_buyer_email'] ?? '');

        if (empty($buyer_name) || empty($buyer_email)) {
            wp_die('Please fill in all fields.');
        }

        $product = get_post($product_id);
        $price   = get_post_meta($product_id, '_ph_product_price', true);

        if (!$product || get_post_type($product_id) !== 'ph_product' || empty($price)) {
            wp_die('Invalid product.');
        }

        $currency     = get_option('product_handel_currency', 'USD');
        $paypal_email = get_option('product_handel_paypal_email');
        $return_url   = get_option('product_handel_return_url') ?: home_url();
        $cancel_url   = get_option('product_handel_cancel_url') ?: home_url();
        $sandbox      = get_option('product_handel_sandbox_mode', 1);

        if (empty($paypal_email)) {
            wp_die('PayPal is not configured. Please contact the site administrator.');
        }

        $order_id = Product_Handel_Order_Manager::create_order(array(
            'product_id'  => $product_id,
            'buyer_name'  => $buyer_name,
            'buyer_email' => $buyer_email,
            'amount'      => $price,
            'currency'    => $currency,
        ));

        // Test mode: skip PayPal, mark order completed immediately
        if (get_option('product_handel_test_mode', 0)) {
            Product_Handel_Order_Manager::update_order($order_id, array(
                'status'         => 'completed',
                'transaction_id' => 'TEST-' . $order_id . '-' . time(),
            ));
            do_action('product_handel_payment_completed', $order_id, array());
            wp_redirect($return_url);
            exit;
        }

        $paypal_url = $sandbox
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        $fields = array(
            'cmd'           => '_xclick',
            'business'      => $paypal_email,
            'item_name'     => $product->post_title,
            'item_number'   => $product_id,
            'amount'        => $price,
            'currency_code' => $currency,
            'return'        => $return_url,
            'cancel_return' => $cancel_url,
            'notify_url'    => home_url('/?product_handel_ipn=1'),
            'custom'        => $order_id,
            'no_shipping'   => '1',
            'email'         => $buyer_email,
        );

        ?><!DOCTYPE html>
<html><head><title>Redirecting to PayPal...</title></head>
<body><p>Redirecting to PayPal...</p>
<form id="ph_paypal" method="post" action="<?php echo esc_url($paypal_url); ?>">
<?php foreach ($fields as $k => $v): ?>
<input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>" />
<?php endforeach; ?>
</form>
<script>document.getElementById('ph_paypal').submit();</script>
</body></html><?php
        exit;
    }
}
