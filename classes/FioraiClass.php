<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\FioraiClass')) {
    class FioraiClass
    {

        public function __construct()
        {
            add_action('init', array($this, 'register_shortcodes'));

            add_action('wp_enqueue_scripts', array($this, 'my_enqueue_scripts'));

            add_action('wp_ajax_get_variant_product_ajax_card', array($this, 'get_variant_product_ajax_card'));
            add_action('wp_ajax_nopriv_get_variant_product_ajax_card', array($this, 'get_variant_product_ajax_card'));

            add_action('admin_post_fiorai_place_order', array($this, 'fiorai_place_order'));
            add_action('admin_post_nopriv_fiorai_place_order', array($this, 'fiorai_place_order'));

            add_filter('woocommerce_get_item_data', array($this, 'display_post_title_in_cart'), 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'display_post_title_in_order_checkout'), 10, 4);

        }

        public function register_shortcodes()
        {
            add_shortcode('variant_product', array($this, 'variant_product'));
        }

        public function my_enqueue_scripts()
        {
            //if type post is annuncio_di_morte

            if (get_query_var('cuscino') || get_query_var('composizione-floreale') || get_query_var('bouquet')) {
                wp_enqueue_script('variant_script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/fioraiProducts.js', array('jquery'), null, true);
                wp_localize_script('variant_script', 'my_ajax_object', array(
                    'ajax_url' => admin_url('admin-ajax.php')
                ));
                wp_enqueue_style('vendor-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/vendor-selector.css');
                wp_enqueue_style('variant-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/variant-style.css');
            }
        }

        public function variant_product()
        {
            ob_start();
            ?>
            <div class="variant-container">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="full-width-form">
                    <input type="hidden" name="action" value="fiorai_place_order">
                    <input type="hidden" name="post_id" value="<?php echo $_GET['post_id']; ?>">
                    <div class="variant-product-container hide">
                        <div class="inner_variant_product">
                        </div>
                        <input type="submit" class="button" value="Continua con l'ordine" >
                    </div>
                    <div class="loader" id="comments-loader"></div>
                </form>
            </div>

            <?php

            return ob_get_clean();
        }

        public function get_variant_product_ajax_card()
        {
            $product_id = $_POST['product_id'];
            //sanitize
            $product_id = filter_var($product_id, FILTER_SANITIZE_NUMBER_INT);

            $product = wc_get_product($product_id);
            $product_variations = $product->get_available_variations();
            //description, image and price
            $product_variations = array_map(function ($variation) {
                $variation['description'] = get_post_meta($variation['variation_id'], '_variation_description', true);
                $variation['image'] = wp_get_attachment_image_src($variation['image_id'], 'thumbnail');
                $variation['price'] = get_post_meta($variation['variation_id'], '_price', true);
                $variation['id'] = $variation['variation_id'];
                return $variation;
            }, $product_variations);
            ob_start();
            ?>
            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <?php foreach ($product_variations as $variation) : ?>
                <div class="variant-flex">
                    <input type="radio" name="product_variation_id" id="product_variation_id_<?php echo $variation['id']; ?>"
                           value="<?php echo $variation['id']; ?>">
                    <label for="product_variation_id_<?php echo $variation['id']; ?>">
                        <div class="variant-card">
                            <div class="variant-card-image">
                                <img src="<?php echo $variation['image'][0]; ?>" width="200px" alt="">
                            </div>
                            <div class="variant-card-description">
                                <p><?php echo $variation['description']; ?></p>
                            </div>
                            <div class="variant-card-price">
                                <p><?php echo do_shortcode('[product_price product_id="'. $variation['id'].'"]') ?></p>
                            </div>
                        </div>
                    </label>
                </div>
                <?php endforeach; ?>
            <?php
            wp_send_json_success( ob_get_clean());
            wp_die();

        }


        public function fiorai_place_order()
        {
            //sanitize product_variation_id
            $product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
            $product_variation_id = filter_var($_POST['product_variation_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_var($_POST['post_id'], FILTER_SANITIZE_NUMBER_INT);

            //check if product_variation_id is set
            if (!isset($product_variation_id)) {
                wp_redirect(get_permalink($post_id));
                exit;
            }

            //check if product_id is valid
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_redirect(get_permalink($post_id));
                exit;
            }

            //check if product_variation_id is valid
            $product_variant = wc_get_product($product_variation_id);
            if (!$product_variant) {
                wp_redirect(get_permalink($post_id));
                exit;
            }

            //check if product is in stock
            if (!$product_variant->is_in_stock()) {
                wp_redirect(get_permalink($post_id));
                exit;
            }

            //check if product is purchasable
            if (!$product_variant->is_purchasable()) {
                wp_redirect(get_permalink($post_id));
                exit;
            }

            //check Woocommer class
            if (!class_exists('WooCommerce')) {
                wp_redirect(get_permalink($post_id));
                exit;
            }

            //check if product is in cart
            if(!WC()->cart){
                wc_load_cart();
                wc_empty_cart();
            }

            $is_purchasable = $product_variant->is_purchasable();
            if (!$is_purchasable) {
                wp_redirect(get_permalink($post_id));
                exit;
            }
            //get the main product of the variation product with wc

            //add cart item data the post_id
            WC()->cart->add_to_cart($product_id, 1, $product_variation_id, array(), array('post_id' => $post_id));
            wp_redirect(wc_get_cart_url());
            exit;
        }

        public function display_post_title_in_cart($cart_data, $cart_item)
        {
            if (empty($cart_item['post_id'])) {
                return $cart_data;
            }
            $post_id = $cart_item['post_id'];
            $post_title = get_the_title($post_id);
            $cart_data[] = array(
                'name' => 'Dedicato a:',
                'value' => sprintf(
                    '%s',
                    esc_html($post_title)),
            );
            return $cart_data;
        }

        public function display_post_title_in_order_checkout($item, $cart_item_key, $values, $order)
        {
            if (empty($values['post_id'])) {
                return;
            }

            $post_id = $values['post_id'];
            $post_title = get_the_title($post_id);
            $item->add_meta_data('Post Title', $post_title);
            $item->add_meta_data('_post_id', $post_id);
        }



    }
}