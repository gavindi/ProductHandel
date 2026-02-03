<?php
/**
 * Plugin Name: ProductHandel
 * Description: Simple e-commerce plugin with PayPal Standard integration. No cart — buy directly.
 * Version: 1.0.3
 * Author: Gavin Graham
 * License: GPL v2 or later
 * Text Domain: product-handel
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PRODUCT_HANDEL_VERSION', '1.0.3');
define('PRODUCT_HANDEL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRODUCT_HANDEL_PLUGIN_URL', plugin_dir_url(__FILE__));

class Product_Handel {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'));
    }

    private function load_dependencies() {
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-order-manager.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-product-post-type.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-settings.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-paypal-handler.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-ipn-listener.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-post-payment.php';
    }

    public function init() {
        Product_Handel_Post_Type::get_instance();
        Product_Handel_Settings::get_instance();
        Product_Handel_Shortcode::get_instance();
        Product_Handel_PayPal_Handler::get_instance();
        Product_Handel_IPN_Listener::get_instance();
        Product_Handel_Post_Payment::get_instance();
    }

    public function activate() {
        Product_Handel_Order_Manager::create_table();
        Product_Handel_Post_Type::register_post_type();
        flush_rewrite_rules();

        add_option('product_handel_paypal_email', '');
        add_option('product_handel_currency', 'USD');
        add_option('product_handel_return_url', '');
        add_option('product_handel_cancel_url', '');
        add_option('product_handel_sandbox_mode', '1');
        add_option('product_handel_test_mode', '0');
        add_option('product_handel_create_user', '0');
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

Product_Handel::get_instance();
