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
            add_filter('template_include', array($this, 'custom_dynamic_page_template'), 99);



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
            add_shortcode('product_price', array($this, 'get_product_price'));
            add_shortcode('product_title', array($this, 'get_product_title_shortcode'));
            add_shortcode('vendor_banner', array($this, 'display_vendor_banner'));

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
            remove_action('acf/save_post', 'annuncio_save_post', 20);

            // Controlla se il post è del tipo 'annuncio-di-morte'
            if (get_post_type($post_id) == 'annuncio-di-morte') {
                $user_id = get_current_user_id();
                //check if the user is a vendor
                if (!dokan_is_user_seller($user_id)) {
                    add_action('acf/save_post', 'annuncio_save_post', 20);
                    return;
                }
                
                // Handle post status change from inline control
                $inline_status = $_POST['acf_post_status_control'] ?? null;
                if ($inline_status) {
                    $new_status = sanitize_text_field($inline_status);
                    
                    if ($new_status === 'delete') {
                        // Handle deletion with confirmation
                        wp_delete_post($post_id, true);
                        wp_redirect(add_query_arg('deleted', '1', home_url('/dashboard/lista-annunci')));
                        exit;
                    } elseif (in_array($new_status, ['draft', 'publish', 'pending'])) {
                        // Update post status - this will override ACF field logic below
                        wp_update_post([
                            'ID' => $post_id, 
                            'post_status' => $new_status
                        ]);
                    }
                }
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

                // Recupera lo stato del post e la data di pubblicazione dai campi ACF
                $post_status = get_field('post_status', $post_id);
                $post_date = get_field('post_date', $post_id);
                $current_time = current_time('mysql');

                // Controlla se la data di pubblicazione è nel futuro
                if (!empty($post_date)) {
                    if (strtotime($post_date) > strtotime($current_time)) {
                        $post_data['post_status'] = 'future';
                        $post_data['post_date'] = $post_date;
                    } else {
                        $post_data['post_status'] = $post_status ? $post_status : 'publish';
                        $post_data['post_date'] = $post_date;
                    }
                } else {
                    $post_data['post_status'] = $post_status ? $post_status : 'publish';
                    // Non impostare automaticamente la data corrente se siamo nell'admin
                    if (!is_admin()) {
                        $post_data['post_date'] = $current_time;
                    }
                }



                wp_update_post($post_data);

                $store_info = dokan_get_store_info($user_id);
                $user_city = $store_info['address']['city'] ?? '';

                global $dbClassInstance;
                $user_provincia = $dbClassInstance->get_provincia_by_comune($user_city);

                update_field('citta', $user_city, $post_id);
                update_field('provincia', $user_provincia, $post_id);
            }
            add_action('acf/save_post', 'annuncio_save_post', 20);
        }



        public function temp_generate_comments_shortcode($atts)
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
            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : get_the_ID();

            $atts = shortcode_atts(
                array(
                    'product_id' => $product_id,
                ),
                $attr
            );
            if (is_numeric($atts['product_id'])) {
                $text_before = "Costo: ";
                $product_price = (new UtilsAMClass())->get_product_price($atts['product_id'], $post_id);
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

            <span id="custom-widget-price" class="custom-widget-price">
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


        public function display_vendor_banner($atts)
        {
            // Get vendor ID from multiple sources for Elementor compatibility
            $default_vendor_id = 0;
            
            // Try to get vendor ID from the current post author
            if (get_the_author_meta('ID')) {
                $default_vendor_id = get_the_author_meta('ID');
            }
            
            // If in single post context, get the post author
            if (is_singular() && !$default_vendor_id) {
                global $post;
                if ($post && isset($post->post_author)) {
                    $default_vendor_id = $post->post_author;
                }
            }
            
            // Parse shortcode attributes
            $atts = shortcode_atts(array(
                'vendor_id' => $default_vendor_id,
            ), $atts);

            // Get the vendor ID
            $vendor_id = intval($atts['vendor_id']);
            
            // Return empty if no vendor ID
            if (!$vendor_id) {
                return '';
            }

            // Get vendor instance
            $vendor = dokan()->vendor->get($vendor_id);
            if (!$vendor || !$vendor->id) {
                return '';
            }

            // Get vendor store URL and banner
            $store_url = dokan_get_store_url($vendor_id);
            $banner = $vendor->get_banner();
            $banner_url = $banner ;
            $store_name = $vendor->get_shop_name();

            ob_start();
            ?>
            <a href="<?php echo esc_url($store_url); ?>" class="vendor-banner-link">
                <div class="vendor-banner-wrapper" style="position: relative; margin-bottom: 20px;">
                <?php if ($banner_url): ?>
                    <img src="<?php echo esc_url($banner_url); ?>"
                         alt="<?php echo esc_attr($store_name); ?>"
                         style="width: 100%; height: auto; max-height: 200px; object-fit: cover;">
                <?php endif; ?>
                    <div class="vendor-banner-name"
                         style="position: absolute; bottom: 0; left: 0; right: 0;
                        background: rgba(0,0,0,0.7); color: white;
                        padding: 10px; text-align: center;">
                        <h6 style="margin: 0;">Agenzia: <?php echo esc_html($store_name); ?></h6>
                    </div>
                </div>
            </a>
            <?php
            return ob_get_clean();
        }

    }
}

