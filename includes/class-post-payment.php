<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_Post_Payment {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('product_handel_payment_completed', array($this, 'handle'), 10, 2);
    }

    public function handle($order_id, $ipn_data) {
        $order = Product_Handel_Order_Manager::get_order($order_id);
        if (!$order) {
            return;
        }

        $product_title = get_the_title($order->product_id);

        // Send purchase confirmation email
        $this->send_purchase_email($order, $product_title);

        // Optionally create a WordPress user account
        if (get_option('product_handel_create_user', 0)) {
            $this->maybe_create_user($order, $product_title);
        }
    }

    private function send_purchase_email($order, $product_title) {
        $site_name = get_bloginfo('name');
        $subject = sprintf('Purchase Confirmation — %s', $product_title);

        $message = sprintf(
            "Hi %s,\n\n" .
            "Thank you for your purchase!\n\n" .
            "Order Details:\n" .
            "  Product: %s\n" .
            "  Amount: %s %s\n" .
            "  Transaction ID: %s\n" .
            "  Date: %s\n\n" .
            "If you have any questions, please contact us.\n\n" .
            "— %s",
            $order->buyer_name,
            $product_title,
            $order->currency,
            number_format((float) $order->amount, 2),
            $order->transaction_id,
            $order->created_at,
            $site_name
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($order->buyer_email, $subject, $message, $headers);
    }

    private function maybe_create_user($order, $product_title) {
        // If user already exists with this email, skip creation
        if (email_exists($order->buyer_email)) {
            return;
        }

        // Derive username from email (part before @), ensure uniqueness
        $base_username = sanitize_user(strstr($order->buyer_email, '@', true), true);
        $username = $base_username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base_username . $i;
            $i++;
        }

        $password = wp_generate_password(12, true, false);

        $user_id = wp_insert_user(array(
            'user_login'   => $username,
            'user_email'   => $order->buyer_email,
            'user_pass'    => $password,
            'display_name' => $order->buyer_name,
            'first_name'   => $order->buyer_name,
            'role'         => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ProductHandel: Failed to create user for ' . $order->buyer_email . ': ' . $user_id->get_error_message());
            }
            return;
        }

        // Send account details email
        $site_name = get_bloginfo('name');
        $login_url = wp_login_url();
        $subject = sprintf('Your Account at %s', $site_name);

        $message = sprintf(
            "Hi %s,\n\n" .
            "An account has been created for you at %s.\n\n" .
            "Your login details:\n" .
            "  Username: %s\n" .
            "  Password: %s\n" .
            "  Login URL: %s\n\n" .
            "We recommend changing your password after your first login.\n\n" .
            "— %s",
            $order->buyer_name,
            $site_name,
            $username,
            $password,
            $login_url,
            $site_name
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($order->buyer_email, $subject, $message, $headers);
    }
}
