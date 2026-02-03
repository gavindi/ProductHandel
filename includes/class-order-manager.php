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
            buyer_name varchar(255) NOT NULL,
            buyer_email varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            status varchar(50) NOT NULL DEFAULT 'pending',
            transaction_id varchar(255) DEFAULT '',
            payment_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function create_order($data) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'product_handel_orders',
            array(
                'product_id'  => intval($data['product_id']),
                'buyer_name'  => sanitize_text_field($data['buyer_name']),
                'buyer_email' => sanitize_email($data['buyer_email']),
                'amount'      => floatval($data['amount']),
                'currency'    => sanitize_text_field($data['currency']),
                'status'      => 'pending',
            ),
            array('%d', '%s', '%s', '%f', '%s', '%s')
        );
        return $wpdb->insert_id;
    }

    public static function update_order($order_id, $data) {
        global $wpdb;
        $update = array();
        $formats = array();

        foreach (array('status', 'transaction_id', 'payment_data') as $field) {
            if (isset($data[$field])) {
                $update[$field] = $field === 'payment_data' ? $data[$field] : sanitize_text_field($data[$field]);
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
}
