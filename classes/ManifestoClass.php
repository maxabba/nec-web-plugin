<?php

namespace Dokan_Mods;
use WP_Query;
use Dokan_Mods\ManifestiLoader;

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
            
            // AJAX handlers for manifesto save
            add_action('wp_ajax_save_manifesto_ajax', array($this, 'save_manifesto_ajax'));
            add_action('wp_ajax_nopriv_save_manifesto_ajax', array($this, 'save_manifesto_ajax'));
            

            add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'));
            add_filter('woocommerce_get_item_data', array($this, 'display_manifesto_and_post_title_in_cart'), 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'display_manifesto_and_post_title_in_order_checkout'), 10, 4);

            //add action fired when a post is published
            add_action('transition_post_status', array($this, 'check_post_status_transition'), 10, 3);
            //add action fired whea post is deleted
            add_action('before_delete_post', array($this, 'delete_manifesto_post'), 10, 1);

            add_action('wp_ajax_load_more_manifesti', array($this, 'load_more_manifesti'));
            add_action('wp_ajax_nopriv_load_more_manifesti', array($this, 'load_more_manifesti'));



            // Add ACF save post hook for dashboard manifesto creation
            add_action('acf/save_post', array($this, 'manifesto_save_post'), 20);

            //require the class 'ManifestiLoader' => 'classes/ManifestiLoader.php',
            require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/ManifestiLoader.php';


        }

        public function my_enqueue_scripts()
        {
            $post_type = get_post_type();
            //if type post is annuncio_di_morte
            if ($post_type === 'annuncio-di-morte') {
                wp_enqueue_script('my-manifesto-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/manifesto.js', array('jquery'), '3.2.0', true);
                wp_localize_script('my-manifesto-script', 'my_ajax_object', array(
                    'ajax_url' => admin_url('admin-ajax.php')
                ));
                wp_enqueue_style('manifesto-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/manifesto.css');

            }



            if (get_query_var('manifesto-top') || get_query_var('manifesto-silver') || get_query_var('manifesto-online') ) {
                wp_enqueue_script('text-editor-manifesto-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/manifesto-text-editor.js', array('jquery'), '3.1.0', true);
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
            add_shortcode('selected_product_description', array($this, 'selected_product_description_shortcode'));
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
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                wp_die('Product not found');
            }

            // Ensure WooCommerce cart is loaded
            if (!WC()->cart) {
                wc_load_cart();
                wc_empty_cart();
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

                    $manifesto_id = wp_insert_post($post);
                    if ($manifesto_id) {
                        //set acf fields annuncio_di_morte_relativo with the post_id - salvato su manifesto come gli altri campi
                        update_field('annuncio_di_morte_relativo', $post_id, $manifesto_id);
                        //vendor_id
                        update_field('vendor_id', get_post_field('post_author', $product_id), $manifesto_id);
                        //testo_manifesto
                        update_field('testo_manifesto', $manifesto_html, $manifesto_id);
                        //tipo_manifesto
                        update_field('tipo_manifesto', $tipo_manifesto, $manifesto_id);
                        //provincia e citta
                        update_field('citta', $citta, $manifesto_id);
                        update_field('provincia', $provincia, $manifesto_id);
                        //add meta data to $manifesto_id with the id of the order
                        add_post_meta($manifesto_id, 'order_id', $order_id);
                        add_post_meta($manifesto_id, 'product_id', $product_id);

                        // Add the manifesto post ID to the order item metadata
                        $item->add_meta_data('_manifesto_id', $manifesto_id);
                        $item->save(); // Save the order item to persist the new metadata
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
                wp_die();
            }

            $product_id = intval($_POST['product_id']);
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
            $user_id = get_post_field('post_author', $product_id);

            if (!$user_id) {
                wp_send_json_error('Invalid Product ID');
            }

            $manifesto_array_data = (new UtilsAMClass())->get_vendor_data_by_id($user_id);
            
            // Aggiungi la descrizione del prodotto alla risposta
            $product = wc_get_product($product_id);
            if ($product) {
                //recupera anche il prezzo passando anche il post_id
                $manifesto_array_data['product_price'] = (new UtilsAMClass())->get_product_price($product_id, $post_id);
                $manifesto_array_data['product_description'] = $product->get_description();
                $manifesto_array_data['product_short_description'] = $product->get_short_description();
                $manifesto_array_data['product_name'] = $product->get_name();
            }

            wp_send_json_success($manifesto_array_data);
            wp_die();

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

            $author_id = get_post_field('post_author', $post_id);

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
            $products_count = $products->found_posts;
            ob_start();

            if ($products->have_posts()) {
                // Convert WP_Query results to an array
                $products_array = $products->posts;

                // Sort products by author_id to have the author's product first
                usort($products_array, function ($a, $b) use ($author_id) {
                    if ($a->post_author == $author_id) {
                        return -1;
                    }
                    if ($b->post_author == $author_id) {
                        return 1;
                    }
                    return 0;
                });

                ?>
                <div class="vendor-selector">
                    <?php
                    foreach ($products_array as $product) {
                        // Ottieni l'autore del prodotto
                        $product_id = $product->ID;

                        // Ottieni le informazioni del negozio Dokan
                        $store_info = (new UtilsAMClass())->get_dokan_store_info_by_product($product_id);
                        $store_name = $store_info['store_name'];
                        $store_banner = $store_info['store_banner'];
                        ?>
                        <div class="vendor-flex">

                            <input type="radio" name="product_id" id="product_<?php echo $product_id; ?>"
                                   value="<?php echo $product_id; ?>" disabled>
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
                            input.disabled = false;
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
                        <div class="editor-toolbar">
                            <button type="button" data-command="bold"><b>B</b></button>
                            <button type="button" data-command="italic"><i>I</i></button>
                            <button type="button" data-command="underline"><u>U</u></button>
                        </div>
                        <div id="text-editor-background" class="text-editor-background" style="background-image: none;container-type: size; ">
                            <div id="text-editor" contenteditable="true" class="custom-text-editor"></div>
                        </div>

                        <hr style="margin-top: 20px; margin-bottom: 20px;">
                        <input type="submit" value="Continua con l'ordine" class="button" >
                    </div>
                    <div class="loader" id="comments-loader"></div>
                </form>
            </div>

            <?php
            return ob_get_clean();
        }


        public function load_more_manifesti()
        {
            $post_id = intval($_POST['post_id']) !== null ? intval($_POST['post_id']) : null;
            $offset = intval($_POST['offset']) !== null ? intval($_POST['offset']) : 0;
            $tipo_manifesto = $_POST['tipo_manifesto'] ?? null;
            $current_author_id = isset($_POST['current_author_id']) ? intval($_POST['current_author_id']) : null;
            $author_offset = isset($_POST['author_offset']) ? intval($_POST['author_offset']) : 0;

            if (!$post_id) {
                wp_send_json_error('Invalid post ID');
                wp_die();
            }

            $loader = new ManifestiLoader($post_id, $offset, $tipo_manifesto);
            
            // Set author-specific pagination if provided
            if ($current_author_id !== null) {
                $loader->set_author_pagination($current_author_id, $author_offset);
            }
            
            $response = $loader->load_manifesti();

            wp_send_json_success($response);
            wp_die();
        }


        public function save_manifesto_ajax()
        {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'save_manifesto_nonce')) {
                wp_send_json_error('Security check failed');
                wp_die();
            }
            
            // Check if user is logged in
            if (!is_user_logged_in()) {
                wp_send_json_error('User not logged in');
                wp_die();
            }
            
            // Get and validate data
            $post_id = isset($_POST['post_id']) ? sanitize_text_field($_POST['post_id']) : '';
            $post_id_annuncio = isset($_POST['post_id_annuncio']) ? intval($_POST['post_id_annuncio']) : null;
            // Sanitize manifesto content while preserving structure and styles
            $raw_testo_manifesto = isset($_POST['testo_manifesto']) ? $_POST['testo_manifesto'] : '';
            
            // Custom sanitization for manifesto HTML - allow specific tags and attributes
            $allowed_html = array(
                'p' => array(
                    'style' => array(),
                    'class' => array(),
                ),
                'br' => array(),
                'span' => array(
                    'style' => array(),
                    'class' => array(),
                ),
                'b' => array(),
                'strong' => array(),
                'i' => array(),
                'em' => array(),
                'u' => array(),
            );
            
            // Use wp_kses with entity preservation
            $testo_manifesto = wp_kses($raw_testo_manifesto, $allowed_html);
            
            // Ensure &nbsp; entities are preserved
            $testo_manifesto = str_replace('&amp;nbsp;', '&nbsp;', $testo_manifesto);
            $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'publish';
            
            if (!$post_id_annuncio || !$testo_manifesto) {
                $error_details = array();
                if (!$post_id_annuncio) $error_details[] = 'post_id_annuncio missing';
                if (!$testo_manifesto) $error_details[] = 'testo_manifesto empty or missing';
                
                wp_send_json_error(array(
                    'message' => 'Missing required data: ' . implode(', ', $error_details)
                ));
                wp_die();
            }
            
            $user_id = get_current_user_id();
            
            // Check if vendor is enabled
            if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
                wp_send_json_error('Vendor account not enabled');
                wp_die();
            }
            
            try {
                if ($post_id === 'new_post') {
                    // Create new post
                    $post_data = array(
                        'post_title' => 'Manifesto per ' . get_the_title($post_id_annuncio),
                        'post_status' => $post_status,
                        'post_author' => $user_id,
                        'post_type' => 'manifesto',
                    );
                    
                    $new_post_id = wp_insert_post($post_data);
                    
                    if (is_wp_error($new_post_id)) {
                        wp_send_json_error('Error creating post: ' . $new_post_id->get_error_message());
                        wp_die();
                    }
                    
                    $post_id = $new_post_id;
                    $action = 'created';
                } else {
                    // Update existing post
                    $post_id = intval($post_id);
                    
                    // Verify user can edit this post
                    if (get_post_field('post_author', $post_id) != $user_id) {
                        wp_send_json_error('Permission denied');
                        wp_die();
                    }
                    
                    // Handle post deletion
                    if ($post_status === 'delete') {
                        // Use existing delete logic which handles WooCommerce orders
                        $this->delete_manifesto_post($post_id);
                        wp_delete_post($post_id, true);
                        
                        wp_send_json_success(array(
                            'message' => 'Manifesto deleted successfully',
                            'action' => 'deleted',
                            'redirect_url' => home_url('/dashboard/lista-manifesti/?post_id_annuncio=' . $post_id_annuncio . '&deleted=1')
                        ));
                        wp_die();
                    }
                    
                    // Update post title and status
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_title' => 'Manifesto per ' . get_the_title($post_id_annuncio),
                        'post_status' => $post_status
                    ));
                    $action = 'updated';
                }
                
                // Update ACF fields directly
                update_field('testo_manifesto', $testo_manifesto, $post_id);
                update_field('annuncio_di_morte_relativo', $post_id_annuncio, $post_id);
                update_field('vendor_id', $user_id, $post_id);
                update_field('tipo_manifesto', 'online', $post_id);
                
                // Copy city and province from annuncio
                $annuncio_city = get_field('citta', $post_id_annuncio);
                $annuncio_province = get_field('provincia', $post_id_annuncio);
                
                if (!empty($annuncio_city)) {
                    update_field('citta', $annuncio_city, $post_id);
                }
                if (!empty($annuncio_province)) {
                    update_field('provincia', $annuncio_province, $post_id);
                }
                
                // Return success response
                wp_send_json_success(array(
                    'message' => 'Manifesto saved successfully',
                    'post_id' => $post_id,
                    'action' => $action,
                    'redirect_url' => home_url('/dashboard/lista-manifesti/?post_id_annuncio=' . $post_id_annuncio . '&operation_result=success')
                ));
                
            } catch (\Exception $e) {
                wp_send_json_error('Error saving manifesto: ' . $e->getMessage());
            }
            
            wp_die();
        }

// Shortcode PHP per generare il container e il loader
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
            <div id="manifesto-container-<?php echo $instance; ?>" class="manifesto-container flex-container row g-2"
                 data-postid="<?php echo $post_id; ?>" data-tipo="<?php echo $attrs['tipo_manifesto']; ?>"></div>
            <?php if ($attrs['tipo_manifesto'] !== 'top'): ?>
            <!-- Il loader visibile solo se vuoi mostrare uno spinner -->
            <div class="loader manifesto-loader" id="manifesto-loader-<?php echo $instance; ?>"></div>
            <!-- Elemento sentinella per l'infinite scroll -->
            <div class="sentinel" style="display: block; position:relative; height: 5px; background-color:transparent;" id="sentinel-<?php echo $instance; ?>"></div>
        <?php else: ?>
            <div class="loader manifesto-loader" id="manifesto-loader-<?php echo $instance; ?>"></div>
        <?php endif; ?>
            <?php
            return ob_get_clean();
        }

        public function manifesto_save_post($post_id)
        {
            if (get_post_type($post_id) !== 'manifesto') {
                return;
            }

            // Handle post status change from inline control (maintaining WooCommerce logic)
            $inline_status = $_POST['acf_post_status_control'] ?? null;
            
            if ($inline_status) {
                $new_status = sanitize_text_field($inline_status);
                
                if ($new_status === 'delete') {
                    // Get the post_id_annuncio to preserve it in redirect
                    $post_id_annuncio = get_field('annuncio_di_morte_relativo', $post_id);
                    
                    // Use existing delete logic which handles WooCommerce orders
                    $this->delete_manifesto_post($post_id);
                    wp_delete_post($post_id, true);
                    
                    // Build redirect URL with preserved post_id_annuncio parameter
                    $redirect_args = array('deleted' => '1');
                    if ($post_id_annuncio) {
                        $redirect_args['post_id_annuncio'] = $post_id_annuncio;
                    }
                    wp_redirect(add_query_arg($redirect_args, home_url('/dashboard/lista-manifesti/')));
                    exit;
                } elseif (in_array($new_status, ['draft', 'publish', 'pending'])) {
                    // Update post status - existing transition_post_status hook will handle WooCommerce logic
                    wp_update_post([
                        'ID' => $post_id, 
                        'post_status' => $new_status
                    ]);
                }
            }

            // Get vendor city from Dokan store info
            $vendor_id = get_post_field('post_author', $post_id);
            $store_info = dokan_get_store_info($vendor_id);
            $vendor_city = $store_info['address']['city'] ?? '';
            $vendor_state = $store_info['address']['state'] ?? '';

            // Get the title from the linked annuncio
            $post_id_annuncio = get_field('annuncio_di_morte_relativo', $post_id);
            if ($post_id_annuncio) {
                $post_title = get_the_title($post_id_annuncio);
                
                // Create post data array with both title and name (slug)
                $post_data = array(
                    'ID' => $post_id,
                    'post_title' => 'Manifesto per ' . $post_title,
                    'post_name' => sanitize_title('manifesto-per-' . $post_title)
                );

                // Remove the current hook to prevent infinite loop
                remove_action('acf/save_post', array($this, 'manifesto_save_post'), 20);

                // Update the post
                wp_update_post($post_data);

                // Re-add the hook
                add_action('acf/save_post', array($this, 'manifesto_save_post'), 20);

                // Copy city and province from annuncio if vendor data is empty
                if (empty($vendor_city)) {
                    $annuncio_city = get_field('citta', $post_id_annuncio);
                    $annuncio_province = get_field('provincia', $post_id_annuncio);
                    
                    if (!empty($annuncio_city)) {
                        $vendor_city = $annuncio_city;
                    }
                    if (!empty($annuncio_province)) {
                        $vendor_state = $annuncio_province;
                    }
                }
            }

            // Update city and province meta fields
            if (!empty($vendor_city)) {
                update_field('citta', $vendor_city, $post_id);
            }
            if (!empty($vendor_state)) {
                update_field('provincia', $vendor_state, $post_id);
            }
        }

        public function selected_product_description_shortcode($atts)
        {
            $atts = shortcode_atts(
                array(
                    'show_name' => 'false',
                    'show_short_description' => 'false',
                    'show_full_description' => 'true',
                    'container_class' => 'selected-product-description',
                ),
                $atts
            );

            ob_start();
            ?>
            <div id="selected-product-description" class="<?php echo esc_attr($atts['container_class']); ?>">
                <!-- Contenuto di fallback mostrato prima della selezione del vendor -->
                <div id="fallback-content" class="fallback-content">
                    <p>Seleziona una agenzia per visualizzare la descrizione...</p>
                </div>

                <!-- Contenuto dinamico mostrato dopo la selezione del vendor -->
                <div id="selected-content" class="selected-content" style="display: none;">
                    <?php if ($atts['show_name'] === 'true'): ?>
                        <h3 id="selected-product-name" class="product-name"></h3>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_short_description'] === 'true'): ?>
                        <div id="selected-product-short-description" class="product-short-description"></div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_full_description'] === 'true'): ?>
                        <div id="selected-product-full-description" class="product-full-description"></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }


    }
}