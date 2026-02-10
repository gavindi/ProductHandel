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

        // Generate license key if enabled for this product
        $license_key = null;
        $generate_key = get_post_meta($order->product_id, '_ph_generate_license_key', true);
        if ($generate_key) {
            $salt = get_post_meta($order->product_id, '_ph_license_key_salt', true);
            if ($salt) {
                $license_key = Product_Handel_Order_Manager::generate_license_key($order->buyer_first_name, $order->buyer_last_name, $order->buyer_email, $salt);
                Product_Handel_Order_Manager::update_order($order_id, array('license_key' => $license_key));
            }
        }

        // Send purchase confirmation email
        $this->send_purchase_email($order, $product_title, $license_key);

        // Optionally create a WordPress user account
        if (get_option('product_handel_create_user', 0)) {
            $this->maybe_create_user($order, $product_title);
        }
    }

    public function send_purchase_email($order, $product_title, $license_key = null) {
        $site_name = get_bloginfo('name');
        $subject = sprintf('Purchase Confirmation — %s', $product_title);
        $full_name = trim($order->buyer_first_name . ' ' . $order->buyer_last_name);
        $download_url = get_post_meta($order->product_id, '_ph_download_url', true);
        $show_download = get_post_meta($order->product_id, '_ph_show_download_link', true);
        $thank_you_message = get_option('product_handel_thank_you_message', 'Thank you for your purchase.');

        if (get_option('product_handel_html_email', 0)) {
            $rows = sprintf(
                '<tr><th style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;width:40%%;font-weight:500;color:#666;">First Name</th><td style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;">%s</td></tr>' .
                '<tr><th style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;width:40%%;font-weight:500;color:#666;">Last Name</th><td style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;">%s</td></tr>' .
                '<tr><th style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;width:40%%;font-weight:500;color:#666;">Email</th><td style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;">%s</td></tr>' .
                '<tr><th style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;width:40%%;font-weight:500;color:#666;">Product</th><td style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;">%s</td></tr>' .
                '<tr><th style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;width:40%%;font-weight:500;color:#666;">Amount Paid</th><td style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;">%s %s</td></tr>' .
                '<tr><th style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;width:40%%;font-weight:500;color:#666;">Transaction ID</th><td style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;">%s</td></tr>' .
                '<tr><th style="padding:10px 0;text-align:left;width:40%%;font-weight:500;color:#666;">Date</th><td style="padding:10px 0;text-align:left;">%s</td></tr>',
                esc_html($order->buyer_first_name),
                esc_html($order->buyer_last_name),
                esc_html($order->buyer_email),
                esc_html($product_title),
                esc_html($order->currency),
                esc_html(number_format((float) $order->amount, 2)),
                esc_html($order->transaction_id),
                esc_html($order->created_at)
            );

            $extra = '';
            $registration_note = get_post_meta($order->product_id, '_ph_registration_note', true);
            if ($license_key) {
                $license_html = sprintf(
                    '<h2 style="font-size:16px;color:#0073aa;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.5px;">Your License Key</h2>' .
                    '<div style="text-align:center;padding:15px;background:#fff;border:2px dashed #0073aa;border-radius:4px;">' .
                    '<code style="font-size:18px;font-weight:bold;letter-spacing:2px;color:#0073aa;">%s</code>' .
                    '</div>',
                    esc_html($license_key)
                );
                if (!empty($registration_note)) {
                    $license_html .= sprintf('<p style="margin:15px 0 0;color:#555;">%s</p>', nl2br(esc_html($registration_note)));
                }
                $extra .= '<div style="padding:25px 30px;border-bottom:1px solid #eee;background:#e8f4f8;">' . $license_html . '</div>';
            }
            if ($download_url && $show_download) {
                $extra .= sprintf(
                    '<div style="padding:25px 30px;border-bottom:1px solid #eee;background:#e8f4e8;">' .
                    '<h2 style="font-size:16px;color:#0073aa;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.5px;">Download Your Product</h2>' .
                    '<div style="text-align:center;padding:15px;">' .
                    '<a href="%s" style="display:inline-block;padding:10px 20px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;font-size:14px;">Download</a>' .
                    '</div></div>',
                    esc_url($download_url)
                );
            }

            $message = $this->build_html_email(
                'Purchase Receipt',
                sprintf('<p style="margin:0 0 20px;">Hi %s,</p><p style="margin:0 0 20px;">%s</p>', esc_html($order->buyer_first_name), esc_html($thank_you_message)) .
                '<h2 style="font-size:16px;color:#0073aa;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.5px;">Order Details</h2>' .
                '<table style="width:100%;border-collapse:collapse;">' . $rows . '</table>',
                $extra
            );
            $headers = array('Content-Type: text/html; charset=UTF-8');
        } else {
            $message = sprintf(
                "Hi %s,\n\n" .
                "%s\n\n" .
                "Order Details:\n" .
                "  First Name: %s\n" .
                "  Last Name: %s\n" .
                "  Email: %s\n" .
                "  Product: %s\n" .
                "  Amount: %s %s\n" .
                "  Transaction ID: %s\n" .
                "  Date: %s\n",
                $order->buyer_first_name,
                $thank_you_message,
                $order->buyer_first_name,
                $order->buyer_last_name,
                $order->buyer_email,
                $product_title,
                $order->currency,
                number_format((float) $order->amount, 2),
                $order->transaction_id,
                $order->created_at
            );

            $registration_note = get_post_meta($order->product_id, '_ph_registration_note', true);
            if ($license_key) {
                $message .= sprintf("\nYour License Key: %s\n", $license_key);
                if (!empty($registration_note)) {
                    $message .= $registration_note . "\n";
                }
            }
            if ($download_url && $show_download) {
                $message .= sprintf("\nDownload Your Product: %s\n", $download_url);
            }

            $message .= sprintf(
                "\nIf you have any questions, please contact us.\n\n" .
                "— %s",
                $site_name
            );
            $headers = array('Content-Type: text/plain; charset=UTF-8');
        }

        wp_mail($order->buyer_email, $subject, $message, $headers);
    }

    private function build_html_email($title, $body_content, $extra_sections = '') {
        $site_name = esc_html(get_bloginfo('name'));

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>' .
            '<body style="margin:0;padding:0;background:#f1f1f1;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;color:#333;line-height:1.6;">' .
            '<div style="max-width:600px;margin:40px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">' .
            '<div style="background:#0073aa;color:#fff;padding:30px;text-align:center;">' .
            '<h1 style="font-size:24px;margin:0 0 10px;">' . $site_name . '</h1>' .
            '<div style="font-size:18px;opacity:0.9;">' . esc_html($title) . '</div>' .
            '</div>' .
            '<div style="padding:25px 30px;border-bottom:1px solid #eee;">' .
            $body_content .
            '</div>' .
            $extra_sections .
            '<div style="padding:20px 30px;text-align:center;background:#fafafa;font-size:13px;color:#666;">' .
            '<p style="margin:0;">If you have any questions, please contact us.</p>' .
            '</div>' .
            '</div>' .
            '</body></html>';
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

        // Store encrypted password for invoice display
        if (get_option('product_handel_show_password_invoice', 1)) {
            Product_Handel_Order_Manager::store_temp_password($order->id, $password);
        }

        $full_name = trim($order->buyer_first_name . ' ' . $order->buyer_last_name);
        $user_id = wp_insert_user(array(
            'user_login'   => $username,
            'user_email'   => $order->buyer_email,
            'user_pass'    => $password,
            'display_name' => $full_name,
            'first_name'   => $order->buyer_first_name,
            'last_name'    => $order->buyer_last_name,
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

        if (get_option('product_handel_html_email', 0)) {
            $rows = sprintf(
                '<tr><th style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;width:40%%;font-weight:500;color:#666;">Username</th><td style="padding:10px 0;text-align:left;border-bottom:1px solid #f0f0f0;"><code style="background:#fff;padding:4px 8px;border-radius:3px;font-family:monospace;font-size:14px;border:1px solid #ddd;">%s</code></td></tr>' .
                '<tr><th style="padding:10px 0;text-align:left;width:40%%;font-weight:500;color:#666;">Password</th><td style="padding:10px 0;text-align:left;"><code style="background:#fff;padding:4px 8px;border-radius:3px;font-family:monospace;font-size:14px;border:1px solid #ddd;font-weight:bold;color:#0073aa;">%s</code></td></tr>',
                esc_html($username),
                esc_html($password)
            );

            $credentials = '<div style="padding:25px 30px;border-bottom:1px solid #eee;background:#f7f9fa;">' .
                '<h2 style="font-size:16px;color:#0073aa;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.5px;">Your Login Details</h2>' .
                '<div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:12px 15px;border-radius:4px;margin-bottom:20px;font-size:14px;">' .
                '<strong>Important:</strong> Save your password — it will not be shown again.' .
                '</div>' .
                '<table style="width:100%;border-collapse:collapse;">' . $rows . '</table>' .
                '<div style="text-align:center;margin:20px 0 10px;">' .
                '<a href="' . esc_url($login_url) . '" style="display:inline-block;padding:10px 20px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;font-size:14px;">Log In Now</a>' .
                '</div>' .
                '<p style="font-size:13px;color:#666;font-style:italic;margin:15px 0 0;">We recommend changing your password after your first login.</p>' .
                '</div>';

            $message = $this->build_html_email(
                'Your Account',
                sprintf('<p style="margin:0 0 20px;">Hi %s,</p><p style="margin:0 0 20px;">An account has been created for you at %s.</p>', esc_html($full_name), esc_html($site_name)),
                $credentials
            );
            $headers = array('Content-Type: text/html; charset=UTF-8');
        } else {
            $message = sprintf(
                "Hi %s,\n\n" .
                "An account has been created for you at %s.\n\n" .
                "Your login details:\n" .
                "  Username: %s\n" .
                "  Password: %s\n" .
                "  Login URL: %s\n\n" .
                "We recommend changing your password after your first login.\n\n" .
                "— %s",
                $full_name,
                $site_name,
                $username,
                $password,
                $login_url,
                $site_name
            );
            $headers = array('Content-Type: text/plain; charset=UTF-8');
        }

        wp_mail($order->buyer_email, $subject, $message, $headers);
    }
}
