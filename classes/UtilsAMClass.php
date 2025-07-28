<?php
namespace Dokan_Mods;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\UtilsAMClass')) {

    class UtilsAMClass
    {

        private array $pages;
        private mixed $pages_slug;

        public function __construct()
        {

            $product_template_mapping = get_option('product_template_mapping', array());
            $this->pages = [
                'pensierini' => [334, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/pensierini.php'],
                'manifesto-top' => [402, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/manifesto.php'],
                'manifesto-silver' => [402, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/manifesto.php'],
                'manifesto-online' => [402, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/manifesto.php'],
            ];
            if (!empty($product_template_mapping)) {
                foreach ($product_template_mapping as $product_id => $template_id) {
                    //clean the pages
                    $product = get_post($product_id);
                    $template = get_post($template_id);
                    if ($product && $template) {
                        $product_slug = $product->post_name;
                        $template_id = $template->ID;

                        // Usa l'accoppiata $product_slug => $template_id come necessario


                        $this->pages[$product_slug] = [$template_id, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/' . $product_slug . '.php'];
                    }
                }
            }

            $this->pages_slug = array_keys($this->pages);
            $links = array_map(function ($slug) {
                return [$slug => __($slug, 'Dokan_mod')];
            }, $this->pages_slug);

            $this->pages_slug = call_user_func_array('array_merge', $links);

        }


        public function get_visitor_id()
        {
            $cookie_name = 'dokan_visitor_id';

            // Check if headers have already been sent
            if (headers_sent()) {
                // If headers are already sent, try to get the existing cookie
                return isset($_COOKIE[$cookie_name])
                    ? sanitize_text_field($_COOKIE[$cookie_name])
                    : wp_generate_uuid4();
            }

            // Try to get existing ID from cookie
            if (isset($_COOKIE[$cookie_name])) {
                return sanitize_text_field($_COOKIE[$cookie_name]);
            }

            // Generate new unique ID
            $visitor_id = wp_generate_uuid4();

            // Use WordPress setcookie function
            setcookie(
                $cookie_name,
                $visitor_id,
                [
                    'expires' => time() + (7 * DAY_IN_SECONDS),
                    'path' => COOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax' // Added for modern browser compatibility
                ]
            );

            return $visitor_id;
        }
        public function get_transient_key($type)
        {
            $visitor_id = $this->get_visitor_id();
            return "dokan_location_{$type}_{$visitor_id}";
        }

        public function clear_location_preferences()
        {
            delete_transient($this->get_transient_key('city'));
            delete_transient($this->get_transient_key('province'));

            // Remove cookie as well
            if (isset($_COOKIE['dokan_visitor_id'])) {
                setcookie('dokan_visitor_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
        }


        public function get_pages()
        {
            return $this->pages;
        }

        public function get_pages_slug()
        {
            return $this->pages_slug;
        }

        public function get_default_products()
        {
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'default-products'
                    )
                )
            );
            $products = get_posts($args);
            $products_id = array();
            foreach ($products as $product) {
                $products_id[] = $product->ID;
            }

            //get the product pensierini and add to the array
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'pensierini'
                    )
                )
            );

            $products = get_posts($args);
            foreach ($products as $product) {
                $products_id[] = $product->ID;
            }

            return $products_id;
        }

        //get array of all the products name by id array
        public function get_product_name($product_id)
        {
            return get_the_title($product_id);
        }

        //create an array product_id => product_name
        public function get_products_name($products_id)
        {
            $products_name = array();
            foreach ($products_id as $product_id) {
                $products_name[$product_id] = $this->get_product_name($product_id);
            }
            return $products_name;
        }

        //get price of the product by id
/*        public function get_product_price($product_id)
        {



            $product = wc_get_product($product_id);
            return $product->get_price_html();
        }*/


        public function get_product_price($product_id)
        {
            // Get base SKU pattern
            $base_sku = $product_id . '-';

            // Initialize arrays for all prices
            $all_prices = array();

            // Query for products with SKU starting with our pattern
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $base_sku,
                        'compare' => 'LIKE'
                    )
                )
            );

            $products = get_posts($args);

            foreach ($products as $product) {
                $product_wc = wc_get_product($product->ID);

                if (!$product_wc) {
                    continue;
                }

                // Handle variable products
                if ($product_wc->is_type('variable')) {
                    $variations = $product_wc->get_available_variations();
                    foreach ($variations as $variation) {
                        $variation_obj = wc_get_product($variation['variation_id']);
                        if ($variation_obj && $variation_obj->get_price()) {
                            $all_prices[] = floatval($variation_obj->get_price());
                        }
                    }
                } // Handle simple products
                else {
                    if ($product_wc->get_price()) {
                        $all_prices[] = floatval($product_wc->get_price());
                    }
                }
            }

            // Also check the original product
            $original_product = wc_get_product($product_id);
            if ($original_product) {
                if ($original_product->is_type('variable')) {
                    $variations = $original_product->get_available_variations();
                    foreach ($variations as $variation) {
                        $variation_obj = wc_get_product($variation['variation_id']);
                        if ($variation_obj && $variation_obj->get_price()) {
                            $all_prices[] = floatval($variation_obj->get_price());
                        }
                    }
                } else {
                    if ($original_product->get_price()) {
                        $all_prices[] = floatval($original_product->get_price());
                    }
                }
            }

            // Remove any duplicate prices
            $all_prices = array_unique($all_prices);

            // If no prices found
            if (empty($all_prices)) {
                return '';
            }

            // Get min and max prices
            $min_price = min($all_prices);
            $max_price = max($all_prices);

            // Format the output
            if ($min_price === $max_price) {
                return wc_price($min_price);
            } else {
                return wc_price($min_price) . ' - ' . wc_price($max_price);
            }
        }

        public function check_and_create_product()
        {
            $pages = $this->get_pages();

            // Otteniamo i termini esistenti per evitare ripetute chiamate al database
            $existing_terms = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'fields' => 'slugs'
            ));

            foreach ($pages as $slug => $template_id) {
                $current_page_slug = $slug;
                // Split by - and take the first element
                $current_page_slug = explode('-', $current_page_slug)[0];

                $args = array(
                    'name' => $current_page_slug,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'numberposts' => 1
                );

                // Utilizziamo WP_Query invece di get_posts per un'operazione più efficiente
                $query = new WP_Query($args);
                if (!$query->have_posts()) {
                    // Verifica se il termine esiste nella lista ottenuta
                    if (!in_array($current_page_slug, $existing_terms)) {
                        wp_insert_term(ucfirst($current_page_slug), 'product_cat', array('slug' => $current_page_slug));
                        // Aggiorna la lista dei termini esistenti
                        $existing_terms[] = $current_page_slug;
                    }

                    $product_data = array(
                        'post_title' => ucfirst($current_page_slug),
                        'post_content' => '',
                        'post_status' => 'publish',
                        'post_author' => 1,
                        'post_type' => 'product',
                    );

                    $product_id = wp_insert_post($product_data);

                    // Otteniamo il termine appena creato per associarlo al prodotto
                    $term = get_term_by('slug', $current_page_slug, 'product_cat');
                    if ($term !== false) {
                        wp_set_object_terms($product_id, $term->term_id, 'product_cat');
                    }
                }
            }

            // Ripuliamo la query per evitare conflitti
            wp_reset_postdata();
        }


        public function dynamic_page_init()
        {
            foreach ($this->pages as $query_var => $template_id) {
                add_rewrite_rule('^' . $query_var . '/?$', 'index.php?' . $query_var . '=1', 'top');
                add_rewrite_tag('%' . $query_var . '%', '([^&]+)');
            }
        }

        public function get_dokan_store_info_by_product($product_id)
        {
            // Controlla se Dokan è attivo.
            if (!function_exists('dokan_get_store_info')) {
                return [];
            }

            // Ottieni l'ID del venditore associato al prodotto.
            $vendor_id = get_post_field('post_author', $product_id);

            // Ottieni le informazioni del negozio.
            $store_info = dokan_get_store_info($vendor_id);

            // Aggiungi l'URL dell'immagine del negozio se disponibile.
            $store_banner = isset($store_info['banner']) ? wp_get_attachment_url($store_info['banner']) : '';

            return [
                'store_name' => isset($store_info['store_name']) ? $store_info['store_name'] : '',
                'store_banner' => $store_banner,
            ];
        }

        public function get_product_id_by_slug($slug)
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

        public function get_vendor_data_by_id($user_id)
        {
            $manifesto_background = get_user_meta($user_id, 'manifesto_background', true) !== '' ? get_user_meta($user_id, 'manifesto_background', true) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default.jpg';
            
            // Fallback per correggere percorsi errati assets/img -> assets/images
            if (strpos($manifesto_background, 'assets/img/') !== false) {
                $corrected_background = str_replace('assets/img/', 'assets/images/', $manifesto_background);
                // Aggiorna il database con il percorso corretto
                update_user_meta($user_id, 'manifesto_background', $corrected_background);
                $manifesto_background = $corrected_background;
            }
            
            $manifesto_orientation = get_user_meta($user_id, 'manifesto_orientation', true) !== '' ? get_user_meta($user_id, 'manifesto_orientation', true) : 'vertical';
            $margin_top = get_user_meta($user_id, 'manifesto_margin_top', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_top', true) : '3.9188837174992';
            $margin_right = get_user_meta($user_id, 'manifesto_margin_right', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_right', true) : '5.8620083240518';
            $margin_bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_bottom', true) : '3.9188837174992';
            $margin_left = get_user_meta($user_id, 'manifesto_margin_left', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_left', true) : '5.8620083240518';
            $alignment = get_user_meta($user_id, 'manifesto_alignment', true) !== '' ? get_user_meta($user_id, 'manifesto_alignment', true) : 'center';

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

    }
}