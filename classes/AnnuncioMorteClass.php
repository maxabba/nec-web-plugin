<?php

namespace Dokan_Mods;

use WC_Cart;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\AnnuncioMorteClass')) {
    class AnnuncioMorteClass
    {
        private $pages;
        private $pages_slug;

        public function __construct()
        {
            $this->pages = [
                'pensierini' => [334 , DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/pensierini.php'], // Sostituisci 123 con l'ID del tuo template di Elementor
            ];
            $this->pages_slug = array_keys($this->pages);
            $links = array_map(function ($slug) {
                return [$slug => __($slug, 'Dokan_mod')];
            }, $this->pages_slug);

            $this->pages_slug = call_user_func_array('array_merge', $links);

            add_action('init', array($this, 'dynamic_page_init'));
            add_action('init', array($this, 'register_shortcodes'));
            add_action('init', array($this, 'check_and_create_product'));
            add_filter('query_vars', array($this, 'query_vars'));
            add_filter('template_include', array($this, 'custom_dynamic_page_template'));
            register_activation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'dynamic_page_activate'));
            register_deactivation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'dynamic_page_deactivate'));

            add_action('wp_loaded', array($this, 'handle_form_submission'));
            add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'));

        }

        public function get_pages()
        {
            return $this->pages;
        }

        public function check_and_create_product()
        {
            $pages = $this->get_pages();

            foreach ($pages as $slug => $template_id) {
                $current_page_slug = $slug;

                $args = array(
                    'name' => $current_page_slug,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'numberposts' => 1
                );
                $products = get_posts($args);
                $term = term_exists($current_page_slug, 'product_cat');
                if (!$term) {
                    wp_insert_term(ucfirst($current_page_slug), 'product_cat', array('slug' => $current_page_slug));
                }
                if (empty($products)) {
                    $product_data = array(
                        'post_title' => ucfirst($current_page_slug),
                        'post_content' => '',
                        'post_status' => 'publish',
                        'post_author' => 1,
                        'post_type' => 'product',
                    );

                    $product_id = wp_insert_post($product_data);

                    $term = get_term_by('slug', $current_page_slug, 'product_cat');
                    if ($term !== false) {
                        wp_set_object_terms($product_id, $term->term_id, 'product_cat');
                    }
                }
            }
        }

        public function get_pages_slug()
        {
            return $this->pages_slug;
        }

        public function register_shortcodes()
        {
            add_shortcode('pensierino_form', array($this, 'generate_pensierino_form'));
        }

        public function dynamic_page_init()
        {
            foreach ($this->pages as $query_var => $template_id) {
                add_rewrite_rule('^' . $query_var . '/?$', 'index.php?' . $query_var . '=1', 'top');
            }
        }

        public function query_vars($vars)
        {
            foreach ($this->pages as $query_var => $template_id) {
                $vars[] = $query_var;
            }
            return $vars;
        }

        public function custom_dynamic_page_template($template)
        {
            foreach ($this->pages as $query_var => $template_id) {
                $is_custom_page = intval(get_query_var($query_var, 0));
                if ($is_custom_page === 1) {
                    // Verifica che Elementor sia attivo e la classe disponibile
                    if (class_exists('\Elementor\Plugin')) {
                        // Ottieni l'ID del post passato come parametro GET
                        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

                        //check if the template id is set and the template exists


                        if (isset($template_id[0]) && get_post($template_id[0])) {
                            // Imposta l'ID del post come variabile globale per Elementor
                            if ($post_id) {
                                global $post;
                                $post = get_post($post_id);
                                setup_postdata($post);
                            }
                            // Includi l'header del tema
                            get_header();

                            // Usa il contenuto del template di Elementor
                            echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id[0]);

                            // Includi il footer del tema
                            get_footer();

                            // Reimposta i dati del post globale
                            if ($post_id) {
                                wp_reset_postdata();
                            }

                            exit; // Termina l'esecuzione per evitare il caricamento del template predefinito
                        } else {
                            // Elementor non è attivo
                            return $template_id[1];
                        }


                    } else {
                        // Elementor non è attivo
                        error_log('Elementor is not activated. Please activate Elementor plugin to use this feature.');
                        wp_die('Elementor is not activated. Please activate Elementor plugin to use this feature.');
                    }
                }
            }
            return $template;
        }

        public function generate_pensierino_form()
        {
            ob_start(); // Start output buffering

            ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="full-width-form">
                <input type="hidden" name="action" value="pensierino_form">
                <input type="hidden" name="post_id" value="<?php echo get_the_ID(); ?>">
                <textarea name="pensierino" id="pensierino_comment_id" maxlength="200" required class="styled-textarea"></textarea>
                <input type="submit" value="Continua">
            </form>
            <style>
                .full-width-form input[type="submit"] {
                    display: block;
                    margin: 0 auto;
                }

                .full-width-form {
                    width: 100%;
                }
                .styled-textarea {
                    width: 100%;
                    margin-bottom: 10px;
                    resize: none;
                    height: 150px;
                    border: none; /* Remove all borders */
                    border-bottom: 1px solid #000; /* Add only bottom border */
                    border-radius: 0; /* Remove border radius */
                    font-family: var(--e-global-typography-text-font-family), Sans-serif;
                    font-weight: var(--e-global-typography-text-font-weight);
                }
            </style>
            <?php

            return ob_get_clean(); // End output buffering and return the form HTML
        }


        public function handle_form_submission()
        {
            // Check if the form is submitted and the textarea text is set
            if (isset($_POST['pensierino'])) {
                // Get the textarea text
                $comment_text = sanitize_text_field($_POST['pensierino']);

                // Get the post ID
                $post_id = intval($_POST['post_id']);

                // Get the product ID of Pensierini
                $product_id = $this->get_product_id_by_slug('pensierini');
                if (class_exists('WooCommerce')) {
                    $product = wc_get_product($product_id);
                    if (!WC()->cart) {
                        wc_load_cart();
                    }
                    if ($product) {
                        if ($product->is_purchasable()) {
                            $cart_item_data = array(
                                'pensierino_comment_text' => $comment_text,
                                'pensierino_comment_post_id' => $post_id,
                            );

                            WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
                            wp_redirect(wc_get_cart_url());
                            exit;
                        } else {
                            wp_die('Product not purchasable');
                        }
                    }else{
                        wp_die('Product not found');
                    }
                }else {
                   //redirect back to the page whit error message
                    wp_redirect(get_permalink($post_id));
                    exit;

                }
            }
        }

        private function wc_load_cart()
        {
            if (!WC()->cart) {
                WC()->cart = new WC_Cart();
                WC()->session->set('cart', WC()->cart->get_cart());
            }
        }

        public function handle_payment_complete($order_id)
        {
            // Get the order
            $order = wc_get_order($order_id);

            // Loop through the order items
            foreach ($order->get_items() as $item_id => $item) {
                // Get the product ID
                $product_id = $item->get_product_id();

                // Check if the product is Pensierini
                if ($product_id == $this->get_product_id_by_slug('pensierini')) {
                    // Get the comment text and post ID from the cart item data
                    $comment_text = $item->get_meta('pensierino_comment_text');
                    $post_id = $item->get_meta('pensierino_comment_post_id');

                    // Get the order
                    $order = wc_get_order($order_id);

                    // Get the billing first name and last name
                    $first_name = $order->get_billing_first_name();
                    $last_name = $order->get_billing_last_name();

                    // Check if the comment text and post ID are set
                    if ($comment_text && $post_id) {
                        // Prepare the comment data
                        $comment_data = array(
                            'comment_post_ID' => $post_id,
                            'comment_author' => $first_name . ' ' . $last_name,
                            'comment_content' => $comment_text,
                            'comment_type' => 'comment',
                            'comment_approved' => 0, // Set to 0 to make the comment unapproved
                        );

                        // Insert the comment
                        wp_insert_comment($comment_data);
                    }
                }
            }
        }

        private function get_product_id_by_slug($slug)
        {
            $args = array(
                'name' => $slug,
                'post_type' => 'product',
                'post_status' => 'publish',
                'numberposts' => 1
            );
            $products = get_posts($args);

            if (!empty($products)) {
                return $products[0]->ID;
            }

            return false;
        }


        public function dynamic_page_activate()
        {
            $this->dynamic_page_init();
            flush_rewrite_rules();
        }

        public function dynamic_page_deactivate()
        {
            flush_rewrite_rules();
        }
    }
}

