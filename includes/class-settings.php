<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_Settings {
    private static $instance = null;

    private static $currencies = array(
        'USD' => 'US Dollar (USD)',
        'EUR' => 'Euro (EUR)',
        'GBP' => 'British Pound (GBP)',
        'CAD' => 'Canadian Dollar (CAD)',
        'AUD' => 'Australian Dollar (AUD)',
        'JPY' => 'Japanese Yen (JPY)',
        'CHF' => 'Swiss Franc (CHF)',
        'NZD' => 'New Zealand Dollar (NZD)',
        'SEK' => 'Swedish Krona (SEK)',
        'NOK' => 'Norwegian Krone (NOK)',
        'DKK' => 'Danish Krone (DKK)',
        'PLN' => 'Polish Zloty (PLN)',
        'BRL' => 'Brazilian Real (BRL)',
        'MXN' => 'Mexican Peso (MXN)',
        'SGD' => 'Singapore Dollar (SGD)',
        'HKD' => 'Hong Kong Dollar (HKD)',
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'product-handel') !== false) {
            wp_enqueue_style('product-handel-admin', PRODUCT_HANDEL_PLUGIN_URL . 'admin/css/admin-styles.css', array(), PRODUCT_HANDEL_VERSION);
        }
    }

    public function add_menus() {
        add_options_page('ProductHandel Settings', 'ProductHandel', 'manage_options', 'product-handel-settings', array($this, 'render_settings_page'));
        add_menu_page('Product Orders', 'Product Orders', 'manage_options', 'product-handel-orders', array($this, 'render_orders_page'), 'dashicons-list-view', 30);
    }

    public function register_settings() {
        register_setting('product_handel_settings', 'product_handel_paypal_email', array('sanitize_callback' => 'sanitize_email'));
        register_setting('product_handel_settings', 'product_handel_currency', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('product_handel_settings', 'product_handel_return_url', array('sanitize_callback' => 'esc_url_raw'));
        register_setting('product_handel_settings', 'product_handel_cancel_url', array('sanitize_callback' => 'esc_url_raw'));
        register_setting('product_handel_settings', 'product_handel_sandbox_mode', array('sanitize_callback' => 'absint'));
        register_setting('product_handel_settings', 'product_handel_test_mode', array('sanitize_callback' => 'absint'));
        register_setting('product_handel_settings', 'product_handel_create_user', array('sanitize_callback' => 'absint'));

        add_settings_section('ph_paypal', 'PayPal Configuration', function () {
            echo '<p>Configure your PayPal account details to accept payments.</p>';
        }, 'product-handel-settings');

        add_settings_section('ph_account', 'Account Creation', function () {
            echo '<p>Optionally create a WordPress user account for each buyer on successful purchase.</p>';
        }, 'product-handel-settings');

        add_settings_field('paypal_email', 'PayPal Email', array($this, 'field_email'), 'product-handel-settings', 'ph_paypal');
        add_settings_field('currency', 'Currency', array($this, 'field_currency'), 'product-handel-settings', 'ph_paypal');
        add_settings_field('return_url', 'Return URL', array($this, 'field_return_url'), 'product-handel-settings', 'ph_paypal');
        add_settings_field('cancel_url', 'Cancel URL', array($this, 'field_cancel_url'), 'product-handel-settings', 'ph_paypal');
        add_settings_field('sandbox', 'Sandbox Mode', array($this, 'field_sandbox'), 'product-handel-settings', 'ph_paypal');
        add_settings_field('test_mode', 'Test Mode', array($this, 'field_test_mode'), 'product-handel-settings', 'ph_paypal');
        add_settings_field('create_user', 'Create User Account', array($this, 'field_create_user'), 'product-handel-settings', 'ph_account');
    }

    public function field_email() {
        $val = get_option('product_handel_paypal_email');
        echo '<input type="email" name="product_handel_paypal_email" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">Your PayPal account email address.</p>';
    }

    public function field_currency() {
        $val = get_option('product_handel_currency', 'USD');
        echo '<select name="product_handel_currency">';
        foreach (self::$currencies as $code => $label) {
            echo '<option value="' . esc_attr($code) . '"' . selected($val, $code, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function field_return_url() {
        $val = get_option('product_handel_return_url');
        echo '<input type="url" name="product_handel_return_url" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">Redirect after successful payment (optional).</p>';
    }

    public function field_cancel_url() {
        $val = get_option('product_handel_cancel_url');
        echo '<input type="url" name="product_handel_cancel_url" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">Redirect if payment is cancelled (optional).</p>';
    }

    public function field_sandbox() {
        $val = get_option('product_handel_sandbox_mode', 1);
        echo '<label><input type="checkbox" name="product_handel_sandbox_mode" value="1" ' . checked(1, $val, false) . ' /> Enable PayPal Sandbox for testing</label>';
    }

    public function field_test_mode() {
        $val = get_option('product_handel_test_mode', 0);
        echo '<label><input type="checkbox" name="product_handel_test_mode" value="1" ' . checked(1, $val, false) . ' /> Skip PayPal and simulate a successful payment</label>';
        echo '<p class="description" style="color:#d63638;">For development/testing only. Orders are immediately marked as completed without any PayPal transaction.</p>';
    }

    public function field_create_user() {
        $val = get_option('product_handel_create_user', 0);
        echo '<label><input type="checkbox" name="product_handel_create_user" value="1" ' . checked(1, $val, false) . ' /> Create a WordPress account (Subscriber) for new buyers</label>';
        echo '<p class="description">A username is derived from the email address. A random password is generated and emailed to the buyer along with their account details.</p>';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        $ipn_url = home_url('/?product_handel_ipn=1');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="notice notice-info"><p><strong>IPN Listener URL:</strong> <code><?php echo esc_url($ipn_url); ?></code><br>Configure this in your PayPal account under IPN notification settings.</p></div>
            <form method="post" action="options.php">
                <?php settings_fields('product_handel_settings'); ?>
                <?php do_settings_sections('product-handel-settings'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_orders_page() {
        if (!current_user_can('manage_options')) return;
        $orders = Product_Handel_Order_Manager::get_orders();
        ?>
        <div class="wrap">
            <h1>Product Orders</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Product</th>
                        <th>Buyer</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Transaction ID</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="8">No orders yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo esc_html($order->id); ?></td>
                                <td><?php echo esc_html(get_the_title($order->product_id)); ?></td>
                                <td><?php echo esc_html($order->buyer_name); ?></td>
                                <td><?php echo esc_html($order->buyer_email); ?></td>
                                <td><?php echo esc_html($order->currency . ' ' . number_format((float)$order->amount, 2)); ?></td>
                                <td><span class="ph-status ph-status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst($order->status)); ?></span></td>
                                <td><?php echo esc_html($order->transaction_id); ?></td>
                                <td><?php echo esc_html($order->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
