<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Dokan_Select_Products')) {
    class Dokan_Select_Products
    {
        // Constructor

        public function __construct()
        {
            add_action('dokan_get_dashboard_nav', array($this, 'add_dashboard_menu'));
            add_action('init', array($this, 'add_rewrite_rules'));
            add_filter('query_vars', array($this, 'add_query_vars'));
            add_filter('template_include', array($this, 'load_template'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'), 9999);
            add_action('init', array($this, 'handle_form_submission'));

        }

        public function enqueue_styles()
        {
            if (get_query_var('seleziona-prodotti')) {
                // Debugging: stampa tutti gli stili registrati
                global $wp_styles;

                // Prova a mettere in coda 'dokan-style'
                if (wp_style_is('dokan-style', 'registered')) {
                    wp_enqueue_style('dokan-style');

                    //set dokan-dashboard dokan-theme-hello-elementor class on body
                    add_filter('body_class', function ($classes) {
                        $classes[] = 'dokan-dashboard dokan-theme-hello-elementor';
                        return $classes;
                    });
                } else {
                    echo 'The "dokan-style" is not registered.';
                }
            }
        }


        public function add_dashboard_menu($urls)
        {
            //unset($urls['products']);
            $urls['seleziona-prodotti'] = array(
                'title' => __('Seleziona Prodotti', 'dokan'),
                'icon' => '<i class="fas fa-briefcase"></i>',
                'url' => site_url('/dashboard/seleziona-prodotti'), // Aggiungi qui l'URL del tuo template
                'pos' => 30,
                'permission' => 'dokan_view_product_menu'
            );
            return $urls;
        }

        //create a function to load the template

        //add rewrite rules
        public function add_rewrite_rules()
        {
            add_rewrite_rule('^dashboard/seleziona-prodotti/?', 'index.php?seleziona-prodotti=true', 'top');
        }

        //add query var
        public function add_query_vars($vars)
        {
            $vars[] = 'seleziona-prodotti';
            return $vars;
        }
        //show the template if the query var is set
        public function load_template($template)
        {
            global $wp_query;
            if (isset($wp_query->query_vars['seleziona-prodotti'])) {
                return DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/select-products.php';
            }
            return $template;
        }


        ///form subbmition function to save the selected products as product of the current vendor
        /// this function will be called when the form is submitted
        public function handle_form_submission()
        {
            // Check if the form is submitted
            if (isset($_POST['selected_product_for_vendor'])) {
                echo 'Form submitted';
                // Sanitize and process the form data
                $selected_products = isset($_POST['product']) ? array_map('intval', $_POST['product']) : array();

                // Get the current user
                $user_id = get_current_user_id();
                foreach ($selected_products as $product_id) {
                    // Check if there is a product with the same title and the same vendor
                    $args = array(
                        'post_type' => 'product',
                        'post_status' => 'publish',
                        'posts_per_page' => 1,
                        'meta_query' => array(
                            array(
                                'key' => '_vendor_id',
                                'value' => $user_id
                            )
                        ),
                        'title' => get_the_title($product_id)
                    );
                    //if the product is not already added by the vendor then add the product to the vendor with category as title
                    if (!get_posts($args)) {
                        //create a copy of the product and assign it to the vendor with the category as title
                        $wo_dup = new WC_Admin_Duplicate_Product();

                        // Compatibility for WC 3.0.0+
                        if (version_compare(WC_VERSION, '2.7', '>')) {
                            $product = wc_get_product($product_id);
                            $clone_product = $wo_dup->product_duplicate($product);
                            $clone_product_id = $clone_product->get_id();
                        } else {

                            $clone_product_id = $wo_dup->duplicate_product($post);
                        }

                        $product_status = dokan_get_default_product_status();
                        // Update the post status
                        wp_update_post(array('ID' => intval($clone_product_id), 'post_status' => $product_status));
                        //update title with Title - Vendor Name - Vendor ID
                        $product_title = get_the_title($product_id) . ' - ' . get_user_meta($user_id, 'dokan_store_name', true) . ' - ' . $user_id;
                        wp_update_post(array('ID' => intval($clone_product_id), 'post_title' => $product_title));
                        //update the sky with the product id and the vendor id
                        update_post_meta($clone_product_id, '_sku', $product_id . '-' . $user_id);
                        //update the vendor id
                        update_post_meta($clone_product_id, '_vendor_id', $user_id);

                        //get form acf categoria_finale and set it as category
                        $categoria_finale = get_field('categoria_finale', $product_id);
                        wp_set_object_terms($clone_product_id, $categoria_finale, 'product_cat', true);
                        //remove category default-products
                        wp_remove_object_terms($clone_product_id, 'default-products', 'product_cat');


                        $operation_successful = true;
                    } else {
                        $operation_successful = false;

                    }


                    // Redirect to prevent form resubmission
                    if ($operation_successful) {
                        wp_redirect(add_query_arg(array('operation_result' => 'success'), $_SERVER['REQUEST_URI']));
                    } else {
                        wp_redirect(add_query_arg(array('operation_result' => 'error'), $_SERVER['REQUEST_URI']));
                    }
                    exit;
                }
            }

        }

    }
}