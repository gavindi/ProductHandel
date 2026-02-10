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
        add_action('admin_init', array($this, 'handle_csv_export'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('wp_ajax_ph_update_order', array($this, 'ajax_update_order'));
        add_action('wp_ajax_ph_resend_invoice', array($this, 'ajax_resend_invoice'));
        add_action('wp_ajax_ph_delete_order', array($this, 'ajax_delete_order'));
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
        register_setting('product_handel_settings', 'product_handel_html_email', array('sanitize_callback' => 'absint'));
        register_setting('product_handel_settings', 'product_handel_thank_you_message', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('product_handel_settings', 'product_handel_create_user', array('sanitize_callback' => 'absint'));
        register_setting('product_handel_settings', 'product_handel_show_password_invoice', array('sanitize_callback' => 'absint'));

        add_settings_section('ph_paypal', 'PayPal Configuration', function () {
            echo '<p>Configure your PayPal account details to accept payments.</p>';
        }, 'product-handel-settings');

        add_settings_section('ph_email', 'Email Settings', function () {
            echo '<p>Configure how purchase emails are sent.</p>';
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
        add_settings_field('html_email', 'HTML Email', array($this, 'field_html_email'), 'product-handel-settings', 'ph_email');
        add_settings_field('thank_you_message', 'Thank You Message', array($this, 'field_thank_you_message'), 'product-handel-settings', 'ph_email');
        add_settings_field('create_user', 'Create User Account', array($this, 'field_create_user'), 'product-handel-settings', 'ph_account');
        add_settings_field('show_password_invoice', 'Show Password on Invoice', array($this, 'field_show_password_invoice'), 'product-handel-settings', 'ph_account');
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

    public function field_html_email() {
        $val = get_option('product_handel_html_email', 0);
        echo '<label><input type="checkbox" name="product_handel_html_email" value="1" ' . checked(1, $val, false) . ' /> Send emails in HTML format</label>';
        echo '<p class="description">When enabled, purchase confirmation and account emails use a styled HTML template matching the invoice page.</p>';
    }

    public function field_thank_you_message() {
        $val = get_option('product_handel_thank_you_message', 'Thank you for your purchase.');
        echo '<input type="text" name="product_handel_thank_you_message" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">Custom message shown in purchase confirmation emails.</p>';
    }

    public function field_create_user() {
        $val = get_option('product_handel_create_user', 0);
        echo '<label><input type="checkbox" name="product_handel_create_user" value="1" ' . checked(1, $val, false) . ' /> Create a WordPress account (Subscriber) for new buyers</label>';
        echo '<p class="description">A username is derived from the email address. A random password is generated and emailed to the buyer along with their account details.</p>';
    }

    public function field_show_password_invoice() {
        $val = get_option('product_handel_show_password_invoice', 1);
        echo '<label><input type="checkbox" name="product_handel_show_password_invoice" value="1" ' . checked(1, $val, false) . ' /> Display the generated password on the invoice page</label>';
        echo '<p class="description">When enabled, the buyer\'s password is shown on the post-purchase invoice page. The password is also always sent via email. Only applies when "Create User Account" is enabled.</p>';
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

    public function handle_csv_export() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'product-handel-orders') return;
        if (!isset($_GET['export']) || $_GET['export'] !== 'csv') return;
        if (!current_user_can('manage_options')) return;
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ph_export_csv')) return;

        $orders = Product_Handel_Order_Manager::get_orders(array('limit' => 999999));

        $filename = 'product-orders-' . gmdate('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Product', 'First Name', 'Last Name', 'Email', 'Amount', 'Currency', 'Status', 'Transaction ID', 'License Key', 'Date'));

        foreach ($orders as $order) {
            fputcsv($output, array(
                $order->id,
                get_the_title($order->product_id),
                $order->buyer_first_name,
                $order->buyer_last_name,
                $order->buyer_email,
                $order->amount,
                $order->currency,
                $order->status,
                $order->transaction_id,
                $order->license_key ?? '',
                $order->created_at,
            ));
        }

        fclose($output);
        exit;
    }

    public function render_orders_page() {
        if (!current_user_can('manage_options')) return;
        $orders = Product_Handel_Order_Manager::get_orders();
        ?>
        <div class="wrap">
            <h1>Product Orders</h1>
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=product-handel-orders&export=csv'), 'ph_export_csv')); ?>" class="button button-secondary">Download CSV</a>
            </p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Product</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Transaction ID</th>
                        <th>Invoice</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="10">No orders yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo esc_html($order->id); ?></td>
                                <td><?php echo esc_html(get_the_title($order->product_id)); ?></td>
                                <td>
                                    <?php echo esc_html($order->buyer_first_name); ?>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="#" class="ph-edit-order"
                                               data-order-id="<?php echo esc_attr($order->id); ?>"
                                               data-buyer-first-name="<?php echo esc_attr($order->buyer_first_name); ?>"
                                               data-buyer-last-name="<?php echo esc_attr($order->buyer_last_name); ?>"
                                               data-buyer-email="<?php echo esc_attr($order->buyer_email); ?>">Edit</a>
                                        </span>
                                        <?php if ($order->status === 'completed'): ?>
                                        | <span class="resend">
                                            <a href="#" class="ph-resend-invoice"
                                               data-order-id="<?php echo esc_attr($order->id); ?>"
                                               data-buyer-email="<?php echo esc_attr($order->buyer_email); ?>">Resend Invoice</a>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (get_option('product_handel_test_mode', 0)): ?>
                                        | <span class="delete">
                                            <a href="#" class="ph-delete-order"
                                               data-order-id="<?php echo esc_attr($order->id); ?>">Delete</a>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($order->buyer_last_name); ?></td>
                                <td><?php echo esc_html($order->buyer_email); ?></td>
                                <td><?php echo esc_html($order->currency . ' ' . number_format((float)$order->amount, 2)); ?></td>
                                <td><span class="ph-status ph-status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst($order->status)); ?></span></td>
                                <td><?php echo esc_html($order->transaction_id); ?></td>
                                <td>
                                    <?php if (!empty($order->access_token)): ?>
                                        <?php $invoice_url = home_url('/invoice/' . $order->access_token); ?>
                                        <a href="<?php echo esc_url($invoice_url); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($order->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="ph-edit-modal" class="ph-modal" style="display:none;">
            <div class="ph-modal-content">
                <h2>Edit Order</h2>
                <form id="ph-edit-form">
                    <input type="hidden" name="order_id" id="ph-edit-order-id">
                    <?php wp_nonce_field('ph_edit_order', 'ph_edit_nonce'); ?>
                    <p>
                        <label for="ph-edit-buyer-first-name">First Name</label>
                        <input type="text" name="buyer_first_name" id="ph-edit-buyer-first-name" class="regular-text" required>
                    </p>
                    <p>
                        <label for="ph-edit-buyer-last-name">Last Name</label>
                        <input type="text" name="buyer_last_name" id="ph-edit-buyer-last-name" class="regular-text" required>
                    </p>
                    <p>
                        <label for="ph-edit-buyer-email">Email Address</label>
                        <input type="email" name="buyer_email" id="ph-edit-buyer-email" class="regular-text" required>
                    </p>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Changes</button>
                        <button type="button" class="button ph-modal-close">Cancel</button>
                    </p>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.ph-edit-order').on('click', function(e) {
                e.preventDefault();
                var $link = $(this);
                $('#ph-edit-order-id').val($link.data('order-id'));
                $('#ph-edit-buyer-first-name').val($link.data('buyer-first-name'));
                $('#ph-edit-buyer-last-name').val($link.data('buyer-last-name'));
                $('#ph-edit-buyer-email').val($link.data('buyer-email'));
                $('#ph-edit-modal').show();
            });

            $('.ph-modal-close').on('click', function() {
                $('#ph-edit-modal').hide();
            });

            $('.ph-modal').on('click', function(e) {
                if (e.target === this) $(this).hide();
            });

            $('.ph-delete-order').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Delete this order permanently?')) {
                    return;
                }
                var $link = $(this);
                $link.css('pointer-events', 'none').text('Deleting...');
                $.post(ajaxurl, {
                    action: 'ph_delete_order',
                    order_id: $link.data('order-id'),
                    _wpnonce: $('#ph_edit_nonce').val()
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || 'Error deleting order');
                        $link.css('pointer-events', '').text('Delete');
                    }
                }).fail(function() {
                    alert('Request failed. Please try again.');
                    $link.css('pointer-events', '').text('Delete');
                });
            });

            $('.ph-resend-invoice').on('click', function(e) {
                e.preventDefault();
                var $link = $(this);
                var email = $link.data('buyer-email');

                if (!confirm('Resend invoice email to ' + email + '?')) {
                    return;
                }

                var originalText = $link.text();
                $link.css('pointer-events', 'none').text('Sending...');

                $.post(ajaxurl, {
                    action: 'ph_resend_invoice',
                    order_id: $link.data('order-id'),
                    _wpnonce: $('#ph_edit_nonce').val()
                }, function(response) {
                    if (response.success) {
                        $link.text('Sent!');
                        setTimeout(function() {
                            $link.css('pointer-events', '').text(originalText);
                        }, 2000);
                    } else {
                        alert(response.data || 'Error sending invoice');
                        $link.css('pointer-events', '').text(originalText);
                    }
                }).fail(function() {
                    alert('Request failed. Please try again.');
                    $link.css('pointer-events', '').text(originalText);
                });
            });

            $('#ph-edit-form').on('submit', function(e) {
                e.preventDefault();
                var $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true).text('Saving...');

                $.post(ajaxurl, {
                    action: 'ph_update_order',
                    order_id: $('#ph-edit-order-id').val(),
                    buyer_first_name: $('#ph-edit-buyer-first-name').val(),
                    buyer_last_name: $('#ph-edit-buyer-last-name').val(),
                    buyer_email: $('#ph-edit-buyer-email').val(),
                    _wpnonce: $('#ph_edit_nonce').val()
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || 'Error saving changes');
                        $btn.prop('disabled', false).text('Save Changes');
                    }
                }).fail(function() {
                    alert('Request failed. Please try again.');
                    $btn.prop('disabled', false).text('Save Changes');
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_update_order() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ph_edit_order')) {
            wp_send_json_error('Invalid security token');
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $buyer_first_name = sanitize_text_field($_POST['buyer_first_name'] ?? '');
        $buyer_last_name  = sanitize_text_field($_POST['buyer_last_name'] ?? '');
        $buyer_email      = sanitize_email($_POST['buyer_email'] ?? '');

        if (!$order_id || empty($buyer_first_name) || empty($buyer_last_name) || empty($buyer_email)) {
            wp_send_json_error('Missing required fields');
        }

        Product_Handel_Order_Manager::update_order($order_id, array(
            'buyer_first_name' => $buyer_first_name,
            'buyer_last_name'  => $buyer_last_name,
            'buyer_email'      => $buyer_email,
        ));

        wp_send_json_success();
    }

    public function ajax_resend_invoice() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ph_edit_order')) {
            wp_send_json_error('Invalid security token');
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }

        $order = Product_Handel_Order_Manager::get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }

        if ($order->status !== 'completed') {
            wp_send_json_error('Can only resend invoice for completed orders');
        }

        $product_title = get_the_title($order->product_id);
        Product_Handel_Post_Payment::get_instance()->send_purchase_email($order, $product_title, $order->license_key);

        wp_send_json_success();
    }

    public function ajax_delete_order() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ph_edit_order')) {
            wp_send_json_error('Invalid security token');
        }

        if (!get_option('product_handel_test_mode', 0)) {
            wp_send_json_error('Delete is only available in test mode');
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }

        Product_Handel_Order_Manager::delete_order($order_id);

        wp_send_json_success();
    }
}
