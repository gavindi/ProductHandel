<?php
/**
 * Plugin Name: ProductHandel
 * Plugin URI:  https://gavingraham.com/product-handel
 * Description: Simple e-commerce plugin with PayPal Standard integration. No cart — buy directly.
 * Version:     1.9.7
 * Author:      Gavin Graham
 * Author URI:  https://gavingraham.com
 * License:     GPL v2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Text Domain: product-handel
 */

/*
Copyright (C) 2026 Gavin Graham

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <https://www.gnu.org/licenses/>.
*/

if (!defined('ABSPATH')) {
    exit;
}

define('PRODUCT_HANDEL_VERSION', '1.9.7');
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
        add_action('plugins_loaded', array($this, 'maybe_upgrade'));
    }

    private function load_dependencies() {
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-order-manager.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-product-post-type.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-settings.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-paypal-handler.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-ipn-listener.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-post-payment.php';
        require_once PRODUCT_HANDEL_PLUGIN_DIR . 'includes/class-invoice-page.php';
    }

    public function init() {
        Product_Handel_Post_Type::get_instance();
        Product_Handel_Settings::get_instance();
        Product_Handel_Shortcode::get_instance();
        Product_Handel_PayPal_Handler::get_instance();
        Product_Handel_Post_Payment::get_instance();
        Product_Handel_Invoice_Page::get_instance();
        Product_Handel_IPN_Listener::get_instance();
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
        add_option('product_handel_html_email', '0');
        add_option('product_handel_create_user', '0');
        add_option('product_handel_show_password_invoice', '1');
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function maybe_upgrade() {
        $current_version = get_option('product_handel_db_version', '1.0.0');

        if (version_compare($current_version, '1.1.0', '<')) {
            // Upgrade to 1.1.0: add invoice page columns
            Product_Handel_Order_Manager::create_table();
            add_option('product_handel_show_password_invoice', '1');
            flush_rewrite_rules();
            update_option('product_handel_db_version', '1.1.0');
        }

        if (version_compare($current_version, '1.4.0', '<')) {
            // Upgrade to 1.4.0: add license_key column
            Product_Handel_Order_Manager::create_table();
            update_option('product_handel_db_version', '1.4.0');
        }

        if (version_compare($current_version, '1.7.0', '<')) {
            // Upgrade to 1.7.0: split buyer_name into buyer_first_name + buyer_last_name
            global $wpdb;
            $table = $wpdb->prefix . 'product_handel_orders';

            // Let dbDelta add the new columns
            Product_Handel_Order_Manager::create_table();

            // Migrate existing data: copy buyer_name into buyer_first_name
            $col_exists = $wpdb->get_var(
                "SHOW COLUMNS FROM $table LIKE 'buyer_name'"
            );
            if ($col_exists) {
                $wpdb->query("UPDATE $table SET buyer_first_name = buyer_name WHERE buyer_first_name = '' AND buyer_name != ''");
                $wpdb->query("ALTER TABLE $table DROP COLUMN buyer_name");
            }

            update_option('product_handel_db_version', '1.7.0');
        }
    }
}

Product_Handel::get_instance();
