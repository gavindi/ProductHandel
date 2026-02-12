<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_Order_Manager {

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'product_handel_orders';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            buyer_first_name varchar(255) NOT NULL DEFAULT '',
            buyer_last_name varchar(255) NOT NULL DEFAULT '',
            buyer_email varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            status varchar(50) NOT NULL DEFAULT 'pending',
            transaction_id varchar(255) DEFAULT '',
            payment_data text,
            access_token varchar(64) DEFAULT NULL,
            access_token_expires datetime DEFAULT NULL,
            temp_password text DEFAULT NULL,
            temp_password_expires datetime DEFAULT NULL,
            license_key varchar(64) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY status (status),
            KEY access_token (access_token)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function create_order($data) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'product_handel_orders',
            array(
                'product_id'      => intval($data['product_id']),
                'buyer_first_name' => sanitize_text_field($data['buyer_first_name']),
                'buyer_last_name'  => sanitize_text_field($data['buyer_last_name']),
                'buyer_email'     => sanitize_email($data['buyer_email']),
                'amount'          => floatval($data['amount']),
                'currency'        => sanitize_text_field($data['currency']),
                'status'          => 'pending',
            ),
            array('%d', '%s', '%s', '%s', '%f', '%s', '%s')
        );
        return $wpdb->insert_id;
    }

    public static function update_order($order_id, $data) {
        global $wpdb;
        $update = array();
        $formats = array();

        foreach (array('status', 'transaction_id', 'payment_data', 'buyer_first_name', 'buyer_last_name', 'buyer_email', 'license_key') as $field) {
            if (isset($data[$field])) {
                if ($field === 'buyer_email') {
                    $update[$field] = sanitize_email($data[$field]);
                } elseif ($field === 'payment_data') {
                    $update[$field] = $data[$field];
                } else {
                    $update[$field] = sanitize_text_field($data[$field]);
                }
                $formats[] = '%s';
            }
        }

        if ($update) {
            $wpdb->update(
                $wpdb->prefix . 'product_handel_orders',
                $update,
                array('id' => intval($order_id)),
                $formats,
                array('%d')
            );
        }
    }

    public static function delete_order($order_id) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . 'product_handel_orders',
            array('id' => intval($order_id)),
            array('%d')
        );
    }

    public static function get_order($order_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_handel_orders WHERE id = %d",
            $order_id
        ));
    }

    public static function get_orders($args = array()) {
        global $wpdb;
        $defaults = array('status' => '', 'limit' => 50, 'offset' => 0);
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = array();
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $prepare_args[] = $args['status'];
        }

        $prepare_args[] = intval($args['limit']);
        $prepare_args[] = intval($args['offset']);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_handel_orders WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$prepare_args
        ));
    }

    public static function generate_access_token($order_id, $hours_valid = 48) {
        global $wpdb;

        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + ($hours_valid * 3600));

        $wpdb->update(
            $wpdb->prefix . 'product_handel_orders',
            array(
                'access_token' => $token,
                'access_token_expires' => $expires,
            ),
            array('id' => intval($order_id)),
            array('%s', '%s'),
            array('%d')
        );

        return $token;
    }

    public static function get_order_by_token($token) {
        global $wpdb;

        if (empty($token) || strlen($token) !== 64) {
            return null;
        }

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_handel_orders
             WHERE access_token = %s
             AND access_token_expires > %s",
            $token,
            gmdate('Y-m-d H:i:s')
        ));

        return $order;
    }

    public static function store_temp_password($order_id, $plaintext_password, $minutes_valid = 30) {
        global $wpdb;

        $encrypted = self::encrypt_password($plaintext_password);
        if (!$encrypted) {
            return false;
        }

        $expires = gmdate('Y-m-d H:i:s', time() + ($minutes_valid * 60));

        $wpdb->update(
            $wpdb->prefix . 'product_handel_orders',
            array(
                'temp_password' => $encrypted,
                'temp_password_expires' => $expires,
            ),
            array('id' => intval($order_id)),
            array('%s', '%s'),
            array('%d')
        );

        return true;
    }

    public static function get_temp_password($order_id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT temp_password, temp_password_expires
             FROM {$wpdb->prefix}product_handel_orders
             WHERE id = %d",
            $order_id
        ));

        if (!$row || empty($row->temp_password)) {
            return null;
        }

        if (strtotime($row->temp_password_expires) < time()) {
            self::clear_temp_password($order_id);
            return null;
        }

        return self::decrypt_password($row->temp_password);
    }

    public static function clear_temp_password($order_id) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'product_handel_orders',
            array(
                'temp_password' => null,
                'temp_password_expires' => null,
            ),
            array('id' => intval($order_id)),
            array('%s', '%s'),
            array('%d')
        );
    }

    public static function generate_license_key($first_name, $last_name, $email, $salt, $transaction_id) {
        $hash = hash('sha256', strtolower(trim($first_name)) . strtolower(trim($last_name)) . strtolower(trim($email)) . strtolower(trim($transaction_id)) . $salt);
        // Format as XXXX-XXXX-XXXX-XXXX (first 16 chars, uppercased)
        $key = strtoupper(substr($hash, 0, 16));
        return substr($key, 0, 4) . '-' . substr($key, 4, 4) . '-' . substr($key, 8, 4) . '-' . substr($key, 12, 4);
    }

    private static function get_encryption_key() {
        if (defined('AUTH_KEY') && AUTH_KEY) {
            return hash('sha256', AUTH_KEY . 'product_handel_temp_pw', true);
        }
        // Generate and persist a random fallback key rather than deriving from the public site URL
        $stored_key = get_option('product_handel_encryption_key');
        if (!$stored_key) {
            $stored_key = bin2hex(random_bytes(32));
            add_option('product_handel_encryption_key', $stored_key, '', 'no');
        }
        return hash('sha256', $stored_key . 'product_handel_temp_pw', true);
    }

    private static function encrypt_password($plaintext) {
        $key = self::get_encryption_key();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return null;
        }

        return base64_encode($iv . $encrypted);
    }

    private static function decrypt_password($encrypted_data) {
        $key = self::get_encryption_key();
        $data = base64_decode($encrypted_data);

        if ($data === false || strlen($data) < 17) {
            return null;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : null;
    }
}
