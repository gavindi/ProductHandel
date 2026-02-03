<?php
if (!defined('ABSPATH')) {
    exit;
}

class Product_Handel_Shortcode {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('product_buy', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_filter('the_content', array($this, 'append_to_product'));
    }

    public function enqueue_styles() {
        wp_enqueue_style('product-handel-frontend', PRODUCT_HANDEL_PLUGIN_URL . 'assets/css/frontend-styles.css', array(), PRODUCT_HANDEL_VERSION);
    }

    public function append_to_product($content) {
        if (!is_singular('ph_product') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        return $content . $this->render_purchase_form(get_the_ID());
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        $product_id = intval($atts['id']);

        if (!$product_id || get_post_type($product_id) !== 'ph_product') {
            return '<p class="ph-error">Invalid product.</p>';
        }

        $product = get_post($product_id);
        if (!$product || $product->post_status !== 'publish') {
            return '<p class="ph-error">Product not found.</p>';
        }

        $price = get_post_meta($product_id, '_ph_product_price', true);
        if (empty($price)) {
            return '<p class="ph-error">Product price not set.</p>';
        }

        $currency = get_option('product_handel_currency', 'USD');
        $product_url = get_permalink($product_id);

        $thumbnail = get_the_post_thumbnail($product_id, 'medium', array('class' => 'ph-thumbnail'));
        $description = apply_filters('the_content', $product->post_content);

        ob_start();
        ?>
        <div class="ph-buy-form">
            <?php if ($thumbnail) : ?>
                <div class="ph-image"><?php echo $thumbnail; ?></div>
            <?php endif; ?>
            <h3 class="ph-title"><?php echo esc_html($product->post_title); ?></h3>
            <?php if ($description) : ?>
                <div class="ph-description"><?php echo $description; ?></div>
            <?php endif; ?>
            <p class="ph-price"><?php echo esc_html($currency . ' ' . number_format((float)$price, 2)); ?></p>
            <p><a href="<?php echo esc_url($product_url); ?>" class="ph-buy-button" style="display:inline-block;width:100%;padding:12px;background:#0073aa;color:#fff;border-radius:4px;font-size:16px;font-weight:700;text-align:center;text-decoration:none;box-sizing:border-box;">Buy Now</a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_purchase_form($product_id) {
        $product = get_post($product_id);
        if (!$product || get_post_type($product_id) !== 'ph_product') {
            return '';
        }

        $price = get_post_meta($product_id, '_ph_product_price', true);
        if (empty($price)) {
            return '';
        }

        $currency = get_option('product_handel_currency', 'USD');
        $nonce = wp_create_nonce('ph_buy_' . $product_id);
        $uid = 'ph_' . $product_id;

        ob_start();
        ?>
        <div class="ph-buy-form">
            <p class="ph-price"><?php echo esc_html($currency . ' ' . number_format((float)$price, 2)); ?></p>
            <form method="post" action="">
                <input type="hidden" name="ph_action" value="buy" />
                <input type="hidden" name="ph_product_id" value="<?php echo esc_attr($product_id); ?>" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>" />
                <div class="ph-field">
                    <label for="<?php echo $uid; ?>_name">Your Name <span class="ph-required">*</span></label>
                    <input type="text" id="<?php echo $uid; ?>_name" name="ph_buyer_name" required class="ph-input" />
                </div>
                <div class="ph-field">
                    <label for="<?php echo $uid; ?>_email">Your Email <span class="ph-required">*</span></label>
                    <input type="email" id="<?php echo $uid; ?>_email" name="ph_buyer_email" required class="ph-input" />
                    <p class="ph-field-note">Your email address will be used to create your account so you can later retrieve your purchase details.</p>
                </div>
                <div class="ph-form-error" id="<?php echo $uid; ?>_error" style="display:none;"></div>
                <button type="submit" class="ph-buy-button">Complete Purchase</button>
            </form>
        </div>
        <script>
        (function(){
            var form = document.getElementById('<?php echo $uid; ?>_name').closest('form');
            form.addEventListener('submit', function(e){
                var name = document.getElementById('<?php echo $uid; ?>_name').value.trim();
                var email = document.getElementById('<?php echo $uid; ?>_email').value.trim();
                var err = document.getElementById('<?php echo $uid; ?>_error');
                var msgs = [];
                if (!name) msgs.push('Name is required.');
                if (!email) msgs.push('Email is required.');
                else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) msgs.push('Please enter a valid email address.');
                if (msgs.length) {
                    e.preventDefault();
                    err.innerHTML = msgs.join('<br>');
                    err.style.display = 'block';
                } else {
                    err.style.display = 'none';
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
