<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\ManifestoClass')) {
    class ManifestoClass
    {

        public function __construct()
        {
            add_action('init', array($this, 'register_shortcodes'));
            add_action('wp_enqueue_scripts', array($this, 'my_enqueue_scripts'));

            add_action('wp_ajax_get_vendor_data', array($this, 'get_vendor_data'));
            add_action('wp_ajax_nopriv_get_vendor_data', array($this, 'get_vendor_data'));

            add_action('admin_post_save_custom_text_editor', array($this, 'save_custom_text_editor'));
            add_action('admin_post_nopriv_save_custom_text_editor', array($this, 'save_custom_text_editor'));

            add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'));
            add_filter('woocommerce_get_item_data', array($this, 'display_manifesto_and_post_title_in_cart'), 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'display_manifesto_and_post_title_in_order_checkout'), 10, 4);

            //add action fired when a post is published
            add_action('transition_post_status', array($this, 'check_post_status_transition'), 10, 3);
            //add action fired whea post is deleted
            add_action('before_delete_post', array($this, 'delete_manifesto_post'), 10, 1);

            add_action('wp_ajax_load_more_manifesti', array($this, 'load_more_manifesti'));
            add_action('wp_ajax_nopriv_load_more_manifesti', array($this, 'load_more_manifesti'));
        }

        public function my_enqueue_scripts()
        {
            $post_type = get_post_type();
            //if type post is annuncio_di_morte
            if ($post_type === 'annuncio-di-morte') {
                wp_enqueue_script('my-manifesto-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/manifesto.js', array('jquery'), null, true);
                wp_localize_script('my-manifesto-script', 'my_ajax_object', array(
                    'ajax_url' => admin_url('admin-ajax.php')
                ));
                wp_enqueue_style('manifesto-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/manifesto.css');

            }

            if (get_query_var('manifesto-top') || get_query_var('manifesto-silver') || get_query_var('manifesto-online')) {
                wp_enqueue_script('text-editor-manifesto-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/manifesto-text-editor.js', array('jquery'), null, true);
                wp_localize_script('text-editor-manifesto-script', 'my_ajax_object', array(
                    'ajax_url' => admin_url('admin-ajax.php')
                ));
                wp_enqueue_style('vendor-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/vendor-selector.css');
                wp_enqueue_style('manifesto-text-editor-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/manifesto-text-editor.css');
            }
        }



        public function register_shortcodes()
        {
            add_shortcode('vendor_selector', array($this, 'shortcode_vendor_selector'));
            add_shortcode('custom_text_editor', array($this, 'create_custom_text_editor_shortcode'));
            add_shortcode('render_manifesti', array($this, 'generate_manifesti_shortcode'));
        }


        public function save_custom_text_editor()
        {
            if (!isset($_POST['product_id'])) {
                wp_send_json_error('Product ID missing');
            }

            if (!isset($_POST['custom_text'])) {
                wp_send_json_error('Custom text missing');
            }

            if (!isset($_POST['post_id'])) {
                wp_send_json_error('Post ID missing');
            }

            $product_id = intval($_POST['product_id']);
            $custom_text = $_POST['custom_text'];
            $post_id = intval($_POST['post_id']);

            if (!get_post($product_id)) {
                wp_send_json_error('Invalid Product ID');
            }

            if (!get_post($post_id)) {
                wp_send_json_error('Invalid Post ID');
            }

            // Check if WooCommerce is available
            if (!class_exists('WooCommerce')) {
                // Redirect back to the page with error message
                wp_redirect(get_permalink($post_id));
                exit;
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                wp_die('Product not found');
            }

            // Ensure WooCommerce cart is loaded
            if (!WC()->cart) {
                wc_load_cart();
            }

            //get the product category
            $product_category = get_the_terms($product_id, 'product_cat');
            //split by - and get the last element
            $product_category = explode('-', $product_category[0]->slug);
            $is_purchasable = $product->is_purchasable();
            if ($is_purchasable) {
                $cart_item_data = array(
                    'manifesto_html' => $custom_text,
                    'tipo_manifesto' => $product_category[1],
                    'manifesto_post_id' => $post_id,
                );

                WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
                wp_redirect(wc_get_cart_url());
                exit;
            } else {
                wp_die('Product not purchasable');
            }
        }


        public function display_manifesto_and_post_title_in_cart($item_data, $cart_item)
        {
            if (isset($cart_item['manifesto_html'])) {
                $sanitized_html = sanitize_text_field($cart_item['manifesto_html']);
                $item_data[] = array(
                    'key' => 'Manifesto',
                    'value' => sprintf(
                        '%s',
                        esc_html($sanitized_html)),
                );
            }

            if (isset($cart_item['manifesto_post_id'])) {
                $post_id = $cart_item['manifesto_post_id'];


                // Get the post title
                $post_title = get_the_title($post_id);
                $item_data[] = array(
                    'key' => 'Dedicato a',
                    'value' => sprintf(
                        '%s',
                        esc_html($post_title),
                    ),
                );
            }



            return $item_data;
        }


        public function display_manifesto_and_post_title_in_order_checkout($item, $cart_item_key, $values, $order)
        {
            if (isset($values['manifesto_html'])) {
                $item->add_meta_data('_manifesto_html', $values['manifesto_html']);
                $item->add_meta_data('Manifesto', $values['manifesto_html']);
            }

            if (isset($values['manifesto_post_id'])) {
                $post_id = $values['manifesto_post_id'];

                // Get the post title
                $post_title = get_the_title($post_id);
                $item->add_meta_data('_post_id', $post_id);
                $item->add_meta_data('Dedicato a', $post_title);
            }

            if (isset($values['tipo_manifesto'])) {
                $tipo_manifesto = $values['tipo_manifesto'];
                $item->add_meta_data('_tipo_manifesto', $tipo_manifesto);
            }
        }


        public function handle_payment_complete($order_id)
        {
            $order = wc_get_order($order_id);
            $items = $order->get_items();

            foreach ($items as $item) {
                if ($item->get_meta('_tipo_manifesto', true) && $item->get_meta('_manifesto_html', true) && $item->get_meta('_post_id', true)) {

                    $product_id = $item->get_product_id();
                    $product = wc_get_product($product_id);
                    $tipo_manifesto = $item->get_meta('_tipo_manifesto', true);
                    $manifesto_html = $item->get_meta('_manifesto_html', true);
                    $post_id = $item->get_meta('_post_id', true);
                    //get vendor id
                    $vendor_id = get_post_field('post_author', $product_id);
                    //get post_id citta and provincia
                    $citta = get_field('citta', $post_id);
                    $provincia = get_field('provincia', $post_id);

                    // create post type manifesto
                    $post = array(
                        'post_title' => 'Manifesto per ' . get_the_title($post_id),
                        'post_status' => 'draft',
                        'post_author' => $vendor_id,
                        'post_type' => 'manifesto',
                    );


                    $post_id = wp_insert_post($post);
                    if ($post_id) {
                        //set acf fields annuncio_di_morte_relativo with the post_id
                        update_field('annuncio_di_morte_relativo', $post_id, $product_id);
                        //vendor_id
                        update_field('vendor_id', get_post_field('post_author', $product_id), $post_id);
                        //testo_manifesto
                        update_field('testo_manifesto', $manifesto_html, $post_id);
                        //tipo_manifesto
                        update_field('tipo_manifesto', $tipo_manifesto, $post_id);
                        //provincia e citta
                        update_field('provincia', $citta, $post_id);
                        update_field('citta', $provincia, $post_id);
                        //add meta data to $post_id with the id of the order
                        add_post_meta($post_id, 'order_id', $order_id);
                        add_post_meta($post_id, 'product_id', $product_id);
                    }
                }

            }
        }


        public function check_post_status_transition($new_status, $old_status, $post)
        {
            if ($post->post_type === 'manifesto' && $old_status == 'draft' && $new_status == 'publish'){
                $order_id = get_post_meta($post->ID, 'order_id', true);
                if ($order_id) {
                    // Get the order object
                    $order = wc_get_order($order_id);

                    // Check if the order exists and is not already completed
                    if ($order && $order->get_status() != 'completed') {
                        // Update the order status to completed
                        $order->update_status('completed');
                    }
                }
            }
        }

        public function delete_manifesto_post($post_id)
        {
            if (get_post_type($post_id) === 'manifesto') {
                $order_id = get_post_meta($post_id, 'order_id', true);
                if ($order_id) {
                    // Get the order object
                    $order = wc_get_order($order_id);

                    // Check if the order exists and is not already completed
                    if ($order && $order->get_status() != 'completed') {
                        // Update the order status to completed
                        $order->update_status('cancelled');
                    }
                }
            }
        }


        function get_vendor_data()
        {
            if (!isset($_POST['product_id'])) {
                wp_send_json_error('Product ID missing');
            }

            $product_id = intval($_POST['product_id']);
            $user_id = get_post_field('post_author', $product_id);

            if (!$user_id) {
                wp_send_json_error('Invalid Product ID');
            }

            $manifesto_background = get_user_meta($user_id, 'manifesto_background', true);
            $manifesto_orientation = get_user_meta($user_id, 'manifesto_orientation', true);
            $margin_top = get_user_meta($user_id, 'manifesto_margin_top', true);
            $margin_right = get_user_meta($user_id, 'manifesto_margin_right', true);
            $margin_bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true);
            $margin_left = get_user_meta($user_id, 'manifesto_margin_left', true);
            $alignment = get_user_meta($user_id, 'manifesto_alignment', true);

            wp_send_json_success([
                'manifesto_background' => $manifesto_background,
                'manifesto_orientation' => $manifesto_orientation,
                'margin_top' => $margin_top,
                'margin_right' => $margin_right,
                'margin_bottom' => $margin_bottom,
                'margin_left' => $margin_left,
                'alignment' => $alignment,
            ]);
        }


        public function get_vendor_data_by_id($user_id){

            $manifesto_background = get_user_meta($user_id, 'manifesto_background', true);
            $manifesto_orientation = get_user_meta($user_id, 'manifesto_orientation', true);
            $margin_top = get_user_meta($user_id, 'manifesto_margin_top', true);
            $margin_right = get_user_meta($user_id, 'manifesto_margin_right', true);
            $margin_bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true);
            $margin_left = get_user_meta($user_id, 'manifesto_margin_left', true);
            $alignment = get_user_meta($user_id, 'manifesto_alignment', true);

            return [
                'manifesto_background' => $manifesto_background,
                'manifesto_orientation' => $manifesto_orientation,
                'margin_top' => $margin_top,
                'margin_right' => $margin_right,
                'margin_bottom' => $margin_bottom,
                'margin_left' => $margin_left,
                'alignment' => $alignment,
            ];

        }


        public function shortcode_vendor_selector($attr)
        {
            $product_id = isset($_GET['product_id']) && $_GET['product_id'] != 0 ? intval($_GET['product_id']) : '66';
            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
            $atts = shortcode_atts(
                array(
                    'product_id' => $product_id,
                ),
                $attr
            );

            // Ottieni il campo ACF 'categoria_finale'
            $categoria_finale = get_field('categoria_finale', $atts['product_id']);
            $categoria_finale = strtolower(str_replace(' ', '-', $categoria_finale));
            $citta = get_field('citta', $post_id);

            // Get all vendors with the city equal to $citta
            $args_vendors = array(
                'role' => 'seller',
                'meta_query' => array(
                    array(
                        'key' => 'dokan_profile_settings',
                        'value' => sprintf(':"%s";', $citta),
                        'compare' => 'LIKE',
                    ),
                ),
            );
            $vendors = get_users($args_vendors);

            // Get the IDs of the vendors
            $vendor_ids = array();
            foreach ($vendors as $vendor) {
                $vendor_ids[] = $vendor->ID;
            }

            // Get all products from these vendors in the specified category
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'author__in' => $vendor_ids, // Only products from these vendors
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => $categoria_finale,
                    ),
                ),
            );
            $products = new \WP_Query($args);
            ob_start();

            if ($products->have_posts()) {
                ?>
                <div class="vendor-selector">
                    <?php
                    while ($products->have_posts()) {
                        // Ottieni l'autore del prodotto
                        $products->the_post();
                        $product_id = get_the_ID();

                        // Ottieni le informazioni del negozio Dokan
                        $store_info = (new UtilsAMClass())->get_dokan_store_info_by_product($product_id);
                        $store_name = $store_info['store_name'];
                        $store_banner = $store_info['store_banner'];
                        ?>
                        <div class="vendor-flex">

                                <input type="radio" name="product_id" id="product_<?php echo $product_id; ?>"
                                       value="<?php echo $product_id; ?>">
                                <label for="product_<?php echo $product_id; ?>" class="vendor-card">
                                    <div class="card">
                                        <?php if ($store_banner) : ?>
                                            <img src="<?php echo $store_banner; ?>" alt="<?php echo $store_name; ?>"
                                                 class="card-img-top" width="250px" height="250px">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo $store_name; ?></h5>
                                        </div>
                                    </div>
                                </label>

                        </div>
                        <?php
                    }
                    ?>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        document.querySelectorAll('input[name="product_id"]').forEach(function (input) {
                            input.addEventListener('change', function () {
                                if (this.checked) {
                                    var productID = this.value;
                                    if (typeof setProductID === 'function') {
                                        setProductID(productID);
                                    }
                                }
                            });
                        });
                    });
                </script>
                <?php
                wp_reset_postdata();
            }
            return ob_get_clean();
        }


        function create_custom_text_editor_shortcode($atts)
        {
            ob_start();
            ?>
            <div class="text-editor-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      id="custom-text-editor-form" class="full-width-form">
                    <input type="hidden" name="action" value="save_custom_text_editor">
                    <input type="hidden" name="product_id" id="product_id" value="<?php echo $_GET['product_id']; ?>">
                    <input type="hidden" name="post_id" value="<?php echo $_GET['post_id'] ?? get_the_ID(); ?>">

                    <div style="margin:auto;" class="manifesti-container hide">
                        <div id="text-editor-background" class="text-editor-background" style="background-image: none;">
                            <div id="text-editor" contenteditable="true" class="custom-text-editor"></div>
                        </div>

                        <input type="submit" value="Salva" class="button">
                    </div>
                    <div class="loader" id="comments-loader"></div>

                </form>
            </div>
            <?php
            return ob_get_clean();
        }


        function load_more_manifesti()
        {
            $post_id = intval($_POST['post_id']) !== null ? intval($_POST['post_id']) : null;
            $offset = intval($_POST['offset']) !== null ? intval($_POST['offset']) : 0;
            $tipo_manifesto = $_POST['tipo_manifesto'] ?? null;
            $limit = 5; // Numero di commenti da caricare per volta

            // get post type manifesto where meta key annuncio_di_morte_relativo is equal to $post_id with offset and limit
            $args = array(
                'post_type' => 'manifesto',
                'meta_query' => array(
                        'relation' => 'AND',
                    array(
                        'key' => 'annuncio_di_morte_relativo',
                        'value' => $post_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'tipo_manifesto',
                        'value' => $tipo_manifesto,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => $limit,
                'offset' => $offset,
            );

            $manifesti = new \WP_Query($args);
            $response = [];

            if ($manifesti->have_posts()) {
                while ($manifesti->have_posts()) {
                    $manifesti->the_post();
                    $post_id = get_the_ID();
                    $vendor_id = get_field('vendor_id');

                    // Get vendor data
                    $vendor_data = $this->get_vendor_data_by_id($vendor_id);

                    ob_start();
                    ?>
                    <div class="col-6 col-lg-3">
                        <div class="text-editor-background" style="background-image: none"
                             data-postid="<?php echo $post_id; ?>" data-vendorid="<?php echo $vendor_id; ?>">
                            <div class="custom-text-editor">
                                <?php the_field('testo_manifesto'); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    $response[] = [
                        'html' => ob_get_clean(),
                        'vendor_data' => $vendor_data,
                    ];
                }
            }

            wp_send_json_success($response);

            wp_die();
        }

        public function generate_manifesti_shortcode($attrs)
        {
            static $instance = 0;
            $instance++;

            $attrs = shortcode_atts(
                array(
                    'tipo_manifesto' => 'top',
                ),
                $attrs
            );

            $post_id = get_the_ID();
            ob_start();
            ?>

            <div id="manifesto-container-<?php echo $instance; ?>" class="manifesto-container row g-2"
                 data-postid="<?php echo $post_id; ?>" data-tipo="<?php echo $attrs['tipo_manifesto']; ?>"></div>
            <div class="loader manifesto-loader" id="manifesto-loader-<?php echo $instance; ?>"></div>

            <?php
            return ob_get_clean();
        }



    }
}