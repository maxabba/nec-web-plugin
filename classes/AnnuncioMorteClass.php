<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . 'AnnuncioMorteClass')) {
    class AnnuncioMorteClass
    {
        private $pages;
        private $pages_slug;

        public function __construct()
        {
            $this->pages = [
                'pensierini' => DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/pensierini.php',
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
        }

        public function get_pages()
        {
            return $this->pages;
        }

        public function check_and_create_product()
        {
            // Get all pages
            $pages = $this->get_pages();

            // Loop through each page
            foreach ($pages as $slug => $template_path) {
                // Get the current page slug via the key
                $current_page_slug = $slug;

                // Check if a product with the same slug exists
                $args = array(
                    'name' => $current_page_slug,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'numberposts' => 1
                );
                $products = get_posts($args);
                //check if exist the product category with the same sulg, if not create
                $term = term_exists($current_page_slug, 'product_cat');
                if (!$term) {
                    //create the category with name uppercase of the slug and the slug as slug
                    wp_insert_term(ucfirst($current_page_slug), 'product_cat', array('slug' => $current_page_slug));
                }
                // If the product doesn't exist, create it
                if (empty($products)) {
                    $product_data = array(
                        'post_title' => ucfirst($current_page_slug),
                        'post_content' => '',
                        'post_status' => 'publish',
                        'post_author' => 1,
                        'post_type' => 'product',
                    );

                    // Insert the product post
                    $product_id = wp_insert_post($product_data);

                    // Get the term id of the product category
                    $term = get_term_by('slug', $current_page_slug, 'product_cat');

                    // Assign the product to the category
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
            add_shortcode('place_order_for', array($this, 'generate_custom_button'));
        }


        public function dynamic_page_init()
        {
            foreach ($this->pages as $query_var => $template_path) {
                add_rewrite_rule('^' . $query_var . '/?$', 'index.php?' . $query_var . '=1', 'top');
            }
        }

        public function query_vars($vars)
        {
            foreach ($this->pages as $query_var => $template_path) {
                $vars[] = $query_var;
            }
            return $vars;
        }

        public function custom_dynamic_page_template($template)
        {
            foreach ($this->pages as $query_var => $template_path) {
                $is_custom_page = intval(get_query_var($query_var, 0));
                if ($is_custom_page === 1 && file_exists($template_path)) {
                    return $template_path;
                }
            }
            return $template;
        }


       public function generate_custom_button($atts)
        {
            $atts = shortcode_atts(
                array(
                    'path' => '', // Default value
                    'text' => 'Ordina', // Default value
                ),
                $atts,
                'place_order_for'
            );

            $path = $atts['path']; // Get the path from the shortcode attributes
            $text = $atts['text']; // Get the text from the shortcode attributes
            $post_id = get_the_ID(); // Get the current post ID
            $url = home_url($path . '?post_id=' . $post_id); // Construct the URL

            // Create the button with inline styles
            $output = '<div class="elementor-button-wrapper" style="text-align: right;">';
            $output .= '<a class="elementor-button elementor-button-link elementor-size-sm" href="' . esc_url($url) . '" style="
                color: var(--e-global-color-secondary);
                background-color: #FFFFFF;
                border: 1px solid var(--e-global-color-secondary);
                border-radius: 5px;
                font-family: var(--e-global-typography-secondary-font-family);
                font-size: var(--e-global-typography-secondary-font-size);
                font-weight: var(--e-global-typography-secondary-font-weight);
                text-transform: var(--e-global-typography-secondary-text-transform);
                font-style: var(--e-global-typography-secondary-font-style);
                text-decoration: var(--e-global-typography-secondary-text-decoration);
                line-height: var(--e-global-typography-secondary-line-height);
                letter-spacing: var(--e-global-typography-secondary-letter-spacing);
            ">';
            $output .= '<span class="elementor-button-content-wrapper">';
            $output .= '<span class="elementor-button-text">'.$text.'</span>';
            $output .= '</span>';
            $output .= '</a>';
            $output .= '</div>';

            return $output;
        }


    }
}




