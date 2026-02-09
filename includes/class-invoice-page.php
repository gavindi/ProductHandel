<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_Invoice_Page {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_invoice_request'));
        add_action('wp_ajax_ph_check_order_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_nopriv_ph_check_order_status', array($this, 'ajax_check_status'));
    }

    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^invoice/([a-f0-9]{64})/?$',
            'index.php?ph_invoice_token=$matches[1]',
            'top'
        );

        // Auto-flush rewrite rules if our rule isn't registered yet
        if (get_option('product_handel_rewrite_version') !== '1.2.0') {
            flush_rewrite_rules();
            update_option('product_handel_rewrite_version', '1.2.0');
        }
    }

    public function add_query_vars($vars) {
        $vars[] = 'ph_invoice_token';
        return $vars;
    }

    public function handle_invoice_request() {
        $token = get_query_var('ph_invoice_token');

        // Fallback: parse URL directly if rewrite rules haven't flushed yet
        if (empty($token)) {
            $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            if (preg_match('#^invoice/([a-f0-9]{64})$#', $request_uri, $matches)) {
                $token = $matches[1];
            }
        }

        if (empty($token)) {
            return;
        }

        $order = Product_Handel_Order_Manager::get_order_by_token($token);

        if (!$order) {
            $this->render_error('Invalid or Expired Link', 'This invoice link is no longer valid. Please check your email for order confirmation details.');
            exit;
        }

        if ($order->status === 'completed') {
            $password = null;
            if (get_option('product_handel_create_user', 0) && get_option('product_handel_show_password_invoice', 1)) {
                $password = Product_Handel_Order_Manager::get_temp_password($order->id);
                if ($password) {
                    Product_Handel_Order_Manager::clear_temp_password($order->id);
                }
            }
            $this->render_invoice($order, $password);
        } else {
            $this->render_pending($order, $token);
        }

        exit;
    }

    public function ajax_check_status() {
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (empty($token) || !wp_verify_nonce($_POST['nonce'] ?? '', 'ph_check_status_' . $token)) {
            wp_send_json_error('Invalid request');
        }

        $order = Product_Handel_Order_Manager::get_order_by_token($token);

        if (!$order) {
            wp_send_json_error('Invalid token');
        }

        wp_send_json_success(array(
            'status' => $order->status,
            'ready' => ($order->status === 'completed'),
        ));
    }

    private function render_invoice($order, $password = null) {
        $product_title = get_the_title($order->product_id);
        $site_name = get_bloginfo('name');
        $home_url = home_url();
        $login_url = wp_login_url();

        $username = null;
        if ($password) {
            $user = get_user_by('email', $order->buyer_email);
            if ($user) {
                $username = $user->user_login;
            }
        }

        $this->render_page_header('Purchase Receipt');
        ?>
        <div class="ph-invoice">
            <div class="ph-invoice-header">
                <h1><?php echo esc_html($site_name); ?></h1>
                <div class="ph-invoice-title">Purchase Receipt</div>
                <div class="ph-invoice-order-id">Order #<?php echo esc_html($order->id); ?></div>
                <div class="ph-invoice-status ph-status-completed">Payment Complete</div>
            </div>

            <div class="ph-invoice-section">
                <h2>Order Details</h2>
                <table class="ph-invoice-table">
                    <tr>
                        <th>First Name</th>
                        <td><?php echo esc_html($order->buyer_first_name); ?></td>
                    </tr>
                    <tr>
                        <th>Last Name</th>
                        <td><?php echo esc_html($order->buyer_last_name); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo esc_html($order->buyer_email); ?></td>
                    </tr>
                    <tr>
                        <th>Product</th>
                        <td><?php echo esc_html($product_title); ?></td>
                    </tr>
                    <tr>
                        <th>Amount Paid</th>
                        <td><?php echo esc_html($order->currency); ?> <?php echo esc_html(number_format((float) $order->amount, 2)); ?></td>
                    </tr>
                    <tr>
                        <th>Transaction ID</th>
                        <td><?php echo esc_html($order->transaction_id); ?></td>
                    </tr>
                    <tr>
                        <th>Purchase Date</th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order->created_at))); ?></td>
                    </tr>
                </table>
            </div>

            <?php if ($username && $password): ?>
            <div class="ph-invoice-section ph-invoice-credentials">
                <h2>Your Account Has Been Created</h2>
                <div class="ph-invoice-warning">
                    <strong>Important:</strong> Save this password now! It will not be shown again.
                </div>
                <table class="ph-invoice-table">
                    <tr>
                        <th>Username</th>
                        <td><code><?php echo esc_html($username); ?></code></td>
                    </tr>
                    <tr>
                        <th>Password</th>
                        <td><code class="ph-password"><?php echo esc_html($password); ?></code></td>
                    </tr>
                </table>
                <p class="ph-invoice-login-prompt">
                    <a href="<?php echo esc_url($login_url); ?>" class="ph-button ph-button-primary">Log In Now</a>
                </p>
                <p class="ph-invoice-note">We recommend changing your password after your first login. Your login details have also been sent to your email.</p>
            </div>
            <?php elseif (get_option('product_handel_create_user', 0) && !$password): ?>
                <?php $user_exists = get_user_by('email', $order->buyer_email); ?>
                <?php if ($user_exists): ?>
                <div class="ph-invoice-section ph-invoice-credentials">
                    <h2>Account Information</h2>
                    <p>You already have an account with us. <a href="<?php echo esc_url($login_url); ?>">Log in here</a> using your existing credentials.</p>
                </div>
                <?php else: ?>
                <div class="ph-invoice-section ph-invoice-credentials">
                    <h2>Account Information</h2>
                    <p>Your account login details have been sent to your email address.</p>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($order->license_key)): ?>
            <div class="ph-invoice-section ph-invoice-license">
                <h2>Your License Key</h2>
                <div class="ph-license-key">
                    <code><?php echo esc_html($order->license_key); ?></code>
                </div>
                <p class="ph-invoice-note">Save this license key for your records.</p>
            </div>
            <?php endif; ?>

            <?php
            $download_url = get_post_meta($order->product_id, '_ph_download_url', true);
            $show_download = get_post_meta($order->product_id, '_ph_show_download_link', true);
            if ($download_url && $show_download):
            ?>
            <div class="ph-invoice-section ph-invoice-download">
                <h2>Download Your Product</h2>
                <div class="ph-download-link">
                    <a href="<?php echo esc_url($download_url); ?>" class="ph-button ph-button-primary">Download</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="ph-invoice-actions">
                <button type="button" class="ph-button" onclick="window.print();">Print Receipt</button>
                <a href="<?php echo esc_url($home_url); ?>" class="ph-button">Return to Site</a>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }

    private function render_pending($order, $token) {
        $product_title = get_the_title($order->product_id);
        $nonce = wp_create_nonce('ph_check_status_' . $token);
        $ajax_url = admin_url('admin-ajax.php');

        $this->render_page_header('Processing Payment');
        ?>
        <div class="ph-invoice ph-invoice-pending">
            <div class="ph-invoice-header">
                <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                <div class="ph-invoice-title">Processing Your Payment</div>
            </div>

            <div class="ph-invoice-section ph-pending-content">
                <div class="ph-spinner"></div>
                <p class="ph-pending-message">Your payment is being confirmed. This usually takes just a moment.</p>

                <table class="ph-invoice-table">
                    <tr>
                        <th>Order</th>
                        <td>#<?php echo esc_html($order->id); ?></td>
                    </tr>
                    <tr>
                        <th>Product</th>
                        <td><?php echo esc_html($product_title); ?></td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td><?php echo esc_html($order->currency); ?> <?php echo esc_html(number_format((float) $order->amount, 2)); ?></td>
                    </tr>
                </table>

                <p class="ph-pending-status" id="ph-pending-status">Checking payment status...</p>
                <p class="ph-pending-note">Don't worry — you'll receive a confirmation email once your payment is complete.</p>
            </div>
        </div>

        <script>
        (function() {
            var token = <?php echo wp_json_encode($token); ?>;
            var nonce = <?php echo wp_json_encode($nonce); ?>;
            var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
            var attempts = 0;
            var maxAttempts = 20;
            var interval = 3000;

            function checkStatus() {
                attempts++;
                var statusEl = document.getElementById('ph-pending-status');

                if (attempts > maxAttempts) {
                    statusEl.textContent = 'Payment confirmation is taking longer than expected. Please check your email for confirmation.';
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'ph_check_order_status');
                formData.append('token', token);
                formData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.data.ready) {
                        statusEl.textContent = 'Payment confirmed! Loading your receipt...';
                        window.location.reload();
                    } else {
                        statusEl.textContent = 'Still processing... (attempt ' + attempts + '/' + maxAttempts + ')';
                        setTimeout(checkStatus, interval);
                    }
                })
                .catch(function() {
                    statusEl.textContent = 'Connection issue. Retrying...';
                    setTimeout(checkStatus, interval);
                });
            }

            setTimeout(checkStatus, interval);
        })();
        </script>
        <?php
        $this->render_page_footer();
    }

    private function render_error($title, $message) {
        $this->render_page_header($title);
        ?>
        <div class="ph-invoice ph-invoice-error">
            <div class="ph-invoice-header">
                <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                <div class="ph-invoice-title"><?php echo esc_html($title); ?></div>
            </div>

            <div class="ph-invoice-section">
                <p><?php echo esc_html($message); ?></p>
                <p><a href="<?php echo esc_url(home_url()); ?>" class="ph-button">Return to Site</a></p>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }

    private function render_page_header($title) {
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title); ?> — <?php echo esc_html(get_bloginfo('name')); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #f1f1f1;
            color: #333;
            line-height: 1.6;
            padding: 40px 20px;
        }
        .ph-invoice {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .ph-invoice-header {
            background: #0073aa;
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .ph-invoice-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .ph-invoice-title {
            font-size: 18px;
            opacity: 0.9;
        }
        .ph-invoice-order-id {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 5px;
        }
        .ph-invoice-status {
            display: inline-block;
            margin-top: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .ph-status-completed {
            background: rgba(255,255,255,0.2);
        }
        .ph-invoice-section {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
        }
        .ph-invoice-section:last-child {
            border-bottom: none;
        }
        .ph-invoice-section h2 {
            font-size: 16px;
            color: #0073aa;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ph-invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ph-invoice-table th,
        .ph-invoice-table td {
            padding: 10px 0;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        .ph-invoice-table th {
            width: 40%;
            font-weight: 500;
            color: #666;
        }
        .ph-invoice-table tr:last-child th,
        .ph-invoice-table tr:last-child td {
            border-bottom: none;
        }
        .ph-invoice-credentials {
            background: #f7f9fa;
        }
        .ph-invoice-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .ph-invoice-credentials code {
            background: #fff;
            padding: 4px 8px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 14px;
            border: 1px solid #ddd;
        }
        .ph-invoice-license {
            background: #e8f4f8;
        }
        .ph-license-key {
            text-align: center;
            padding: 15px;
            background: #fff;
            border: 2px dashed #0073aa;
            border-radius: 4px;
            margin: 15px 0;
        }
        .ph-license-key code {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #0073aa;
            background: none;
            border: none;
            padding: 0;
        }
        .ph-invoice-download {
            background: #e8f4e8;
        }
        .ph-download-link {
            text-align: center;
            padding: 15px;
        }
        .ph-password {
            font-weight: bold;
            color: #0073aa;
        }
        .ph-invoice-login-prompt {
            margin: 20px 0 15px;
            text-align: center;
        }
        .ph-invoice-note {
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        .ph-invoice-actions {
            padding: 20px 30px;
            text-align: center;
            background: #fafafa;
        }
        .ph-button {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            border: 1px solid #0073aa;
            border-radius: 4px;
            background: #fff;
            color: #0073aa;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ph-button:hover {
            background: #0073aa;
            color: #fff;
        }
        .ph-button-primary {
            background: #0073aa;
            color: #fff;
        }
        .ph-button-primary:hover {
            background: #005a87;
            border-color: #005a87;
        }
        .ph-pending-content {
            text-align: center;
            padding: 40px 30px;
        }
        .ph-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f0f0f0;
            border-top-color: #0073aa;
            border-radius: 50%;
            margin: 0 auto 20px;
            animation: ph-spin 1s linear infinite;
        }
        @keyframes ph-spin {
            to { transform: rotate(360deg); }
        }
        .ph-pending-message {
            font-size: 16px;
            margin-bottom: 25px;
        }
        .ph-pending-status {
            color: #666;
            font-size: 14px;
            margin: 20px 0;
        }
        .ph-pending-note {
            font-size: 13px;
            color: #888;
            margin-top: 20px;
        }
        .ph-invoice-error .ph-invoice-section {
            text-align: center;
            padding: 40px 30px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .ph-invoice { box-shadow: none; }
            .ph-invoice-actions { display: none; }
            .ph-invoice-header { background: #333; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
        <?php
    }

    private function render_page_footer() {
        ?>
</body>
</html>
        <?php
    }
}
