<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_Post_Type {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        self::register_post_type();
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_ph_product', array($this, 'save_product_meta'));
        add_filter('manage_ph_product_posts_columns', array($this, 'add_price_column'));
        add_action('manage_ph_product_posts_custom_column', array($this, 'render_price_column'), 10, 2);
        add_action('wp_head', array($this, 'product_page_styles'));
        self::register_block_template();
    }

    public function product_page_styles() {
        if (is_singular('ph_product')) {
            echo '<style>
                .single-ph_product .entry-meta, .single-ph_product .post-meta,
                .single-ph_product .byline, .single-ph_product .posted-on,
                .single-ph_product .cat-links, .single-ph_product .tags-links,
                .single-ph_product .entry-footer, .single-ph_product .post-date,
                .single-ph_product .wp-block-post-date, .single-ph_product .wp-block-post-author { display: none !important; }
                .wp-post-image, img.attachment-post-thumbnail { max-width: 320px !important; height: auto !important; display: block !important; margin-left: auto !important; margin-right: auto !important; }
            </style>';
        }
    }

    public static function register_block_template() {
        add_filter('get_block_templates', array(__CLASS__, 'inject_block_template'), 10, 3);
        add_filter('pre_get_block_file_template', array(__CLASS__, 'provide_block_template'), 10, 3);
    }

    public static function inject_block_template($query_result, $query, $template_type) {
        if ($template_type !== 'wp_template') {
            return $query_result;
        }

        // Check if the theme already provides single-ph_product
        foreach ($query_result as $t) {
            if ($t->slug === 'single-ph_product') {
                return $query_result;
            }
        }

        // Only inject when relevant
        if (!empty($query['slug__in']) && !in_array('single-ph_product', $query['slug__in'], true)) {
            return $query_result;
        }

        $template = self::build_block_template();
        if ($template) {
            $query_result[] = $template;
        }
        return $query_result;
    }

    public static function provide_block_template($template, $id, $template_type) {
        if ($template_type !== 'wp_template') {
            return $template;
        }

        $theme_slug = get_stylesheet();
        if ($id !== $theme_slug . '//single-ph_product') {
            return $template;
        }

        return self::build_block_template();
    }

    private static function build_block_template() {
        $theme_slug = get_stylesheet();

        $template = new WP_Block_Template();
        $template->id = $theme_slug . '//single-ph_product';
        $template->theme = $theme_slug;
        $template->slug = 'single-ph_product';
        $template->title = 'Single Product';
        $template->description = 'Displays a single product without date/author metadata.';
        $template->type = 'wp_template';
        $template->status = 'publish';
        $template->has_theme_file = false;
        $template->is_custom = true;
        $template->source = 'plugin';
        $template->content =
            '<!-- wp:template-part {"slug":"header","area":"header"} /-->' .
            '<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->' .
            '<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50)">' .
            '<!-- wp:post-featured-image {"align":"wide"} /-->' .
            '<!-- wp:post-title {"level":1} /-->' .
            '<!-- wp:post-content {"layout":{"type":"constrained"}} /-->' .
            '</main>' .
            '<!-- /wp:group -->' .
            '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->';

        return $template;
    }

    public static function register_post_type() {
        register_post_type('ph_product', array(
            'labels' => array(
                'name'               => 'Products',
                'singular_name'      => 'Product',
                'add_new'            => 'Add New Product',
                'add_new_item'       => 'Add New Product',
                'edit_item'          => 'Edit Product',
                'view_item'          => 'View Product',
                'all_items'          => 'All Products',
                'search_items'       => 'Search Products',
                'not_found'          => 'No products found',
                'not_found_in_trash' => 'No products found in trash',
            ),
            'public'       => true,
            'has_archive'  => true,
            'supports'     => array('title', 'editor', 'thumbnail'),
            'menu_icon'    => 'dashicons-cart',
            'rewrite'      => array('slug' => 'products'),
            'show_in_rest' => true,
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'ph_product_price',
            'Product Price',
            array($this, 'render_price_meta_box'),
            'ph_product',
            'side',
            'high'
        );
        add_meta_box(
            'ph_product_shortcode',
            'Shortcode',
            array($this, 'render_shortcode_meta_box'),
            'ph_product',
            'side',
            'default'
        );
        add_meta_box(
            'ph_product_key_settings',
            'License Key Settings',
            array($this, 'render_key_settings_meta_box'),
            'ph_product',
            'side',
            'default'
        );
        add_meta_box(
            'ph_product_download_settings',
            'Download Settings',
            array($this, 'render_download_settings_meta_box'),
            'ph_product',
            'side',
            'default'
        );
    }

    public function render_price_meta_box($post) {
        wp_nonce_field('ph_save_price', 'ph_price_nonce');
        $price = get_post_meta($post->ID, '_ph_product_price', true);
        $currency = get_option('product_handel_currency', 'USD');
        ?>
        <p>
            <label for="ph_product_price">Price (<?php echo esc_html($currency); ?>):</label>
            <input type="number" step="0.01" min="0" name="ph_product_price" id="ph_product_price"
                   value="<?php echo esc_attr($price); ?>" style="width: 100%;" />
        </p>
        <?php
    }

    public function render_shortcode_meta_box($post) {
        if ($post->post_status === 'publish') {
            echo '<code>[product_buy id="' . esc_html($post->ID) . '"]</code>';
            echo '<p class="description">Copy this shortcode to any page or post.</p>';
        } else {
            echo '<p class="description">Publish the product to get the shortcode.</p>';
        }
    }

    public function render_key_settings_meta_box($post) {
        $enabled = get_post_meta($post->ID, '_ph_generate_license_key', true);
        $salt = get_post_meta($post->ID, '_ph_license_key_salt', true);
        $random_salt = wp_generate_password(32, false);
        ?>
        <p>
            <label>
                <input type="checkbox" name="ph_generate_license_key" value="1" <?php checked(1, $enabled); ?> />
                Generate license key for buyers
            </label>
        </p>
        <p>
            <label for="ph_license_key_salt">License Key Salt:</label>
            <input type="text" name="ph_license_key_salt" id="ph_license_key_salt"
                   value="<?php echo esc_attr($salt); ?>" style="width: 100%;" />
        </p>
        <p>
            <button type="button" class="button" onclick="document.getElementById('ph_license_key_salt').value = '<?php echo esc_js($random_salt); ?>';">Generate Salt</button>
        </p>
        <p class="description">The license key is generated from buyer email + this salt. Keep the salt secret.</p>
        <?php
    }

    public function render_download_settings_meta_box($post) {
        $download_url = get_post_meta($post->ID, '_ph_download_url', true);
        $show_download = get_post_meta($post->ID, '_ph_show_download_link', true);
        ?>
        <p>
            <label for="ph_download_url">Download URL:</label>
            <input type="url" name="ph_download_url" id="ph_download_url"
                   value="<?php echo esc_attr($download_url); ?>" style="width: 100%;" />
        </p>
        <p>
            <label>
                <input type="checkbox" name="ph_show_download_link" value="1" <?php checked(1, $show_download); ?> />
                Show download link on invoice and email
            </label>
        </p>
        <?php
    }

    public function save_product_meta($post_id) {
        if (!isset($_POST['ph_price_nonce']) ||
            !wp_verify_nonce($_POST['ph_price_nonce'], 'ph_save_price')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['ph_product_price'])) {
            update_post_meta($post_id, '_ph_product_price', sanitize_text_field($_POST['ph_product_price']));
        }

        // Save license key settings
        $generate_key = isset($_POST['ph_generate_license_key']) ? 1 : 0;
        update_post_meta($post_id, '_ph_generate_license_key', $generate_key);

        if (isset($_POST['ph_license_key_salt'])) {
            update_post_meta($post_id, '_ph_license_key_salt', sanitize_text_field($_POST['ph_license_key_salt']));
        }

        // Save download settings
        if (isset($_POST['ph_download_url'])) {
            update_post_meta($post_id, '_ph_download_url', esc_url_raw($_POST['ph_download_url']));
        }
        $show_download = isset($_POST['ph_show_download_link']) ? 1 : 0;
        update_post_meta($post_id, '_ph_show_download_link', $show_download);
    }

    public function add_price_column($columns) {
        $columns['ph_price'] = 'Price';
        return $columns;
    }

    public function render_price_column($column, $post_id) {
        if ($column === 'ph_price') {
            $price = get_post_meta($post_id, '_ph_product_price', true);
            $currency = get_option('product_handel_currency', 'USD');
            echo $price ? esc_html($currency . ' ' . number_format((float)$price, 2)) : '—';
        }
    }
}
