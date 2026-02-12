<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_IPN_Listener {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->listen();
    }

    public function listen() {
        if (!isset($_GET['product_handel_ipn'])) {
            return;
        }

        // Only accept POST requests for IPN
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            status_header(405);
            exit;
        }

        // Rate limiting: max 20 IPN requests per minute per IP
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $transient_key = 'ph_ipn_rate_' . md5($ip);
        $request_count = (int) get_transient($transient_key);
        if ($request_count >= 20) {
            status_header(429);
            exit;
        }
        set_transient($transient_key, $request_count + 1, 60);

        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            status_header(400);
            exit;
        }

        parse_str($raw, $ipn_data);

        if (!$this->verify($raw)) {
            status_header(400);
            exit;
        }

        $this->process($ipn_data);
        status_header(200);
        exit;
    }

    private function verify($raw_post) {
        $sandbox = get_option('product_handel_sandbox_mode', 1);
        $url = $sandbox
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $body = 'cmd=_notify-validate&' . $raw_post;

        $response = wp_remote_post($url, array(
            'body'      => $body,
            'timeout'   => 60,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $result = trim(wp_remote_retrieve_body($response));
        return $result === 'VERIFIED';
    }

    private function process($ipn_data) {
        $payment_status = $ipn_data['payment_status'] ?? '';
        $txn_id         = sanitize_text_field($ipn_data['txn_id'] ?? '');
        $order_id       = intval($ipn_data['custom'] ?? 0);
        $receiver_email = sanitize_text_field($ipn_data['receiver_email'] ?? '');

        if (strcasecmp($receiver_email, get_option('product_handel_paypal_email')) !== 0) {
            return;
        }

        $order = Product_Handel_Order_Manager::get_order($order_id);
        if (!$order) {
            return;
        }

        // Prevent duplicate transaction processing
        if ($order->status === 'completed' && $order->transaction_id === $txn_id) {
            return;
        }

        $status = strtolower($payment_status) === 'completed' ? 'completed' : sanitize_text_field($payment_status);

        // Verify payment amount and currency match the order
        if ($status === 'completed') {
            $paid_amount = floatval($ipn_data['mc_gross'] ?? 0);
            $paid_currency = sanitize_text_field($ipn_data['mc_currency'] ?? '');

            if ($paid_amount < floatval($order->amount)) {
                return;
            }
            if (strcasecmp($paid_currency, $order->currency) !== 0) {
                return;
            }
        }

        // Sanitize IPN data values before encoding for storage
        $sanitized_ipn = array_map('sanitize_text_field', $ipn_data);

        $update_data = array(
            'status'         => $status,
            'transaction_id' => $txn_id,
            'payment_data'   => wp_json_encode($sanitized_ipn),
        );

        // Override buyer details with PayPal's verified data on successful payment
        if ($status === 'completed') {
            if (!empty($ipn_data['first_name'])) {
                $update_data['buyer_first_name'] = sanitize_text_field($ipn_data['first_name']);
            }
            if (!empty($ipn_data['last_name'])) {
                $update_data['buyer_last_name'] = sanitize_text_field($ipn_data['last_name']);
            }
            if (!empty($ipn_data['payer_email'])) {
                $update_data['buyer_email'] = sanitize_email($ipn_data['payer_email']);
            }
        }

        Product_Handel_Order_Manager::update_order($order_id, $update_data);

        if ($status === 'completed') {
            do_action('product_handel_payment_completed', $order_id, $ipn_data);
        }
    }

}
