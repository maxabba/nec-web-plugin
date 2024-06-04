<?php

namespace Dokan_Mods;

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
            $UtilsAMClass = new UtilsAMClass();

            $this->pages = $UtilsAMClass->get_pages();
            $this->pages_slug = $UtilsAMClass->get_pages_slug();

            //add_action('init', array($this, 'dynamic_page_init'));
            add_action('init', array($this, 'register_shortcodes'));
            //add_action('init', array($this, 'check_and_create_product'));
            add_action('acf/save_post', array($this, 'annuncio_save_post'), 20);

            add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_bootstrap'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_bootstrap'));

            add_filter('query_vars', array($this, 'query_vars'));
            add_filter('template_include', array($this, 'custom_dynamic_page_template'));

            //add_action('wp_loaded', array($this, 'handle_form_submission'));
            add_action('admin_post_pensierino_form', array($this, 'handle_form_submission'));
            add_action('admin_post_nopriv_pensierino_form', array($this, 'handle_form_submission'));

            add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'));
            add_filter('woocommerce_get_item_data', array($this, 'display_comment_and_post_title_in_cart'), 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'display_comment_and_post_title_in_order_checkout'), 10, 4);

            add_action('wp_set_comment_status', array($this, 'handle_comment_approval'), 10, 2);
            add_action('delete_comment', array($this, 'handle_comment_deletion'));

        }


        public function enqueue_bootstrap()
        {
            // Enqueue Bootstrap CSS
            wp_register_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css');

           // wp_register_style('necro-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/necro-style.css');

            // Register Bootstrap JS
            wp_register_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.min.js', array('jquery'), null, true);

            // Enqueue Bootstrap CSS
            wp_enqueue_style('bootstrap-css');

           // wp_enqueue_style('necro-style');
            // Enqueue Bootstrap JS
            wp_enqueue_script('bootstrap-js');
        }


        public function get_pages()
        {
            return $this->pages;
        }


        public function get_pages_slug()
        {
            return $this->pages_slug;
        }

        public function register_shortcodes()
        {
            add_shortcode('pensierino_form', array($this, 'generate_pensierino_form'));
            add_shortcode('pensierino_comments', array($this, 'generate_comments_shortcode'));
            add_shortcode('product_price', array($this, 'get_product_price'));
            add_shortcode('product_title', array($this, 'get_product_title_shortcode'));
            add_shortcode('vendor_selector', array($this, 'shortcode_vendor_selector'));
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

        function annuncio_save_post($post_id)
        {
            // Controlla se il post è del tipo 'custom_type'
            if (get_post_type($post_id) == 'annuncio-di-morte') {
                // Recupera i valori dei campi ACF
                $nome = get_field('nome', $post_id);
                $cognome = get_field('cognome', $post_id);

                // Combina i campi "Nome" e "Cognome" per creare il titolo del post
                $post_title = $nome . ' ' . $cognome;

                // Aggiorna il titolo del post
                $post_data = array(
                    'ID' => $post_id,
                    'post_title' => $post_title,
                );
                wp_update_post($post_data);

            }
        }
        public function handle_form_submission()
        {
            // Check if the form is submitted and the textarea text is set
            if (isset($_POST['pensierino'])) {
                // Get the textarea text and sanitize it
                $comment_text = sanitize_text_field($_POST['pensierino']);

                // Get the post ID and validate it
                $post_id = intval($_POST['post_id']);
                if (!$post_id) {
                    wp_die('Invalid post ID');
                }

                // Get the product ID of Pensierini
                $product_id = $this->get_product_id_by_slug('pensierini');
                if (!$product_id) {
                    wp_die('Product not found');
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
            } else {
                wp_die('Form submission error');
            }
        }


        public function handle_comment_approval($comment_id, $comment_status)
        {
            if ($comment_status == 'approve') {
                // Get the comment object
                $comment = get_comment($comment_id);

                // Get the order ID from the comment meta
                $order_id = get_comment_meta($comment_id, 'order_id', true);

                // Check if the order ID exists
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

        public function handle_comment_deletion($comment_id)
        {
            // Get the order ID from the comment meta
            $order_id = get_comment_meta($comment_id, 'order_id', true);

            // Check if the order ID exists
            if ($order_id) {
                // Get the order object
                $order = wc_get_order($order_id);

                // Check if the order exists and is not already cancelled
                if ($order && $order->get_status() != 'cancelled') {
                    // Update the order status to cancelled
                    $order->update_status('cancelled');
                }
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
                    $comment_text = $item->get_meta('_pensierino_comment_text');
                    $post_id = $item->get_meta('_pensierino_comment_post_id');

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
                        $comment_id =wp_insert_comment($comment_data);

                        if ($comment_id) {
                            add_comment_meta($comment_id, 'order_id', $order_id, true);
                            add_comment_meta($comment_id, 'product_id', $product_id, true);
                        }
                    }

                }
            }
        }

        public function display_comment_and_post_title_in_cart($item_data, $cart_item)
        {
            // Get the product ID
            $product_id = $cart_item['product_id'];

            // Check if the product is Pensierini
            if ($product_id == $this->get_product_id_by_slug('pensierini')) {
                // Get the comment text and post ID from the cart item data
                $comment_text = $cart_item['pensierino_comment_text'];
                $post_id = $cart_item['pensierino_comment_post_id'];


                // Get the post title
                $post_title = get_the_title($post_id);

                // Append the comment and post title to the product name
                $item_data[] = array(
                    'key' => 'Pensierino',
                    'value' => sprintf(
                        '%s',
                        esc_html($comment_text),
                    ),
                );
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

        public function display_comment_and_post_title_in_order_checkout($item, $cart_item_key, $values, $order)
        {
            // Get the product ID
            $product_id = $item->get_product_id();

            // Check if the product is Pensierini
            if ($product_id == $this->get_product_id_by_slug('pensierini')) {
                // Get the comment text and post ID from the cart item data
                $comment_text = $values['pensierino_comment_text'];
                $post_id = $values['pensierino_comment_post_id'];

                // Get the post title
                $post_title = get_the_title($post_id);
                $item->add_meta_data('_pensierino_comment_text', $comment_text, true);
                $item->add_meta_data('_pensierino_comment_post_id', $post_id, true);
                // Append the comment and post title to the product name
                $item->add_meta_data('Pensierino', $comment_text, true);
                $item->add_meta_data('Dedicato a', $post_title, true);
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


        public function generate_comments_shortcode($atts)
        {
            // Get the current post ID
            $post_id = get_the_ID();

            // Get all comments for the current post
            $comments = get_comments(array(
                'post_id' => $post_id,
                'status' => 'approve', // Only show approved comments
            ));

            // Start the output buffer
            ob_start();
            ?>
            <style>
                .nrc-user {
                    font-size: 12px;
                }

                .nrc-user p {
                    color: #dcbe52;
                    font-weight: 700;
                }

                .nrc-user span {
                    color: #565656;
                }

                .card-body p {
                    font-size: 15px;
                    font-style: italic;
                }

                .card {
                    background-color: #e9efee;
                    height: 100%;
                }
            </style>
                <?php
            // Start the container div with horizontal scroll
            echo '<div class="row g-2">';

            // Loop through each comment
            foreach ($comments as $comment) {
                // Generate the HTML for each comment
                ?>
                <div class="col-6 col-lg-3">
                    <div class="p-3" style="display: inline-block;">
                        <div class="nrc-user d-flex justify-content-between">
                            <p><?php echo (empty($comment->comment_author) || $comment->comment_author == '') ? 'Anonimo' : esc_html($comment->comment_author); ?></p>
                            <span>ha scritto il <?php echo get_comment_date('j F', $comment->comment_ID); ?>:</span>
                        </div>
                        <div class="card border-0 speech-bubble-card mt-3">
                            <div class="card-body">
                                <p><?php echo esc_html($comment->comment_content); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }

            // End the container div
            echo '</div>';

            // Return the generated HTML
            return ob_get_clean();
        }

        //make a shortcode to display the price of the product
        public function get_product_price($attr)
        {
            $product_id = isset($_GET['product_id']) && $_GET['product_id'] != 0 ? intval($_GET['product_id']) : '66';

            $atts = shortcode_atts(
                array(
                    'product_id' => $product_id,
                ),
                $attr
            );
            if (is_numeric($atts['product_id'])) {
                $text_before = "Costo: ";
                $product_price = (new UtilsAMClass())->get_product_price($atts['product_id']);
            } else {
                $text_before = '';
                $product_price = '';
            }

            ob_start();
            ?>
            <style>
                .price-text {
                    font-weight: 700;
                    color: #dcbe52;
                }
            </style>

            <span class="custom-widget-price">
                <?php echo esc_html($text_before); ?><span class="price-text"><?php echo $product_price; ?></span>
            </span>
            <?php
            return ob_get_clean();

        }

        public function get_product_title_shortcode($attr)
        {
            $product_id = isset($_GET['product_id']) && $_GET['product_id'] != 0 ? intval($_GET['product_id']) : '66';

            $atts = shortcode_atts(
                array(
                    'product_id' => $product_id,
                ),
                $attr
            );
            if (is_numeric($atts['product_id'])) {
                $product_name = (new UtilsAMClass())->get_product_name($atts['product_id']);
            } else {
                $product_name = '';
            }
            //upper case all
            $product_name = strtoupper($product_name);
            ob_start();
            ?>

                <?php echo esc_html($product_name); ?>


            <?php
            return ob_get_clean();
        }

        public function generate_pensierino_form()
        {
            ob_start(); // Start output buffering

            ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="full-width-form">
                <input type="hidden" name="action" value="pensierino_form">
                <input type="hidden" name="post_id" value="<?php echo $_POST['post_id'] ?? get_the_ID(); ?>">
                <textarea name="pensierino" id="pensierino_comment_id" maxlength="200" required
                          class="styled-textarea"></textarea>
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


        public function shortcode_vendor_selector($attr)
        {
            $product_id = isset($_GET['product_id']) && $_GET['product_id'] != 0 ? intval($_GET['product_id']) : '66';

            $atts = shortcode_atts(
                array(
                    'product_id' => $product_id,
                ),
                $attr
            );

            // Ottieni il campo ACF 'categoria_finale'
            $categoria_finale = get_field('categoria_finale', $atts['product_id']);
            // Converti la stringa in minuscolo e sostituisci gli spazi con un trattino
            $categoria_finale = strtolower(str_replace(' ', '-', $categoria_finale));

            // Ottieni tutti i prodotti della categoria
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
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
                        <div class="vendor-card">
                            <input type="radio" name="product_id" id="product_<?php echo $product_id; ?>"
                                   value="<?php echo $product_id; ?>">
                            <label for="product_<?php echo $product_id; ?>">
                                <div class="card">
                                    <?php if ($store_banner) : ?>
                                        <img src="<?php echo $store_banner; ?>" alt="<?php echo $store_name; ?>"
                                             class="card-img-top">
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
                <style>
                    .vendor-selector {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 20px;
                        justify-content: center;
                    }

                    .vendor-card {
                        flex: 1 1 30%;
                        max-width: 30%;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        border-radius: 5px;
                        overflow: hidden;
                        position: relative;
                        text-align: center;
                    }

                    .vendor-card input[type="radio"] {
                        position: absolute;
                        opacity: 0;
                        cursor: pointer;
                    }

                    .vendor-card input[type="radio"]:checked + label .card {
                        border: 2px solid #007bff;
                    }

                    .card {
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        overflow: hidden;
                        text-align: center;
                    }

                    .card img {
                        display: block;
                        margin: 0 auto;
                        max-width: 100%;
                        height: auto;
                    }

                    .card-body {
                        padding: 10px;
                    }

                    .card-title {
                        font-size: 16px;
                        font-weight: bold;
                        margin: 0;
                    }
                </style>

                <?php
                wp_reset_postdata();
            }
            return ob_get_clean();
        }


        /**
         * @param $product_id
         * @param \WC_Order_Item $item
         * @param $order_id
         * @return void
         */
        public function checkIfTheProductIsPensierini($product_id, \WC_Order_Item $item, $order_id): void
        {
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
}

