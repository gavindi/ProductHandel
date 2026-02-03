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
        add_action('init', array($this, 'listen'));
    }

    public function listen() {
        if (!isset($_GET['product_handel_ipn'])) {
            return;
        }

        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            status_header(400);
            exit;
        }

        parse_str($raw, $ipn_data);

        if (!$this->verify($ipn_data)) {
            status_header(400);
            exit;
        }

        $this->process($ipn_data);
        status_header(200);
        exit;
    }

    private function verify($ipn_data) {
        $sandbox = get_option('product_handel_sandbox_mode', 1);
        $url = $sandbox
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        $body = 'cmd=_notify-validate';
        foreach ($ipn_data as $key => $value) {
            $body .= '&' . urlencode($key) . '=' . urlencode($value);
        }

        $response = wp_safe_remote_post($url, array(
            'body'      => $body,
            'timeout'   => 60,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            $this->log('IPN verification failed: ' . $response->get_error_message());
            return false;
        }

        return trim(wp_remote_retrieve_body($response)) === 'VERIFIED';
    }

    private function process($ipn_data) {
        $payment_status = $ipn_data['payment_status'] ?? '';
        $txn_id         = $ipn_data['txn_id'] ?? '';
        $order_id       = intval($ipn_data['custom'] ?? 0);
        $receiver_email = $ipn_data['receiver_email'] ?? '';

        if (strcasecmp($receiver_email, get_option('product_handel_paypal_email')) !== 0) {
            $this->log('Receiver email mismatch: ' . $receiver_email);
            return;
        }

        $status = strtolower($payment_status) === 'completed' ? 'completed' : sanitize_text_field($payment_status);

        Product_Handel_Order_Manager::update_order($order_id, array(
            'status'         => $status,
            'transaction_id' => $txn_id,
            'payment_data'   => wp_json_encode($ipn_data),
        ));

        if ($status === 'completed') {
            do_action('product_handel_payment_completed', $order_id, $ipn_data);
        }
    }

    private function log($msg) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ProductHandel: ' . $msg);
        }
    }
}
