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
            $this->pages = [
                'pensierini' => 334, // Sostituisci 123 con l'ID del tuo template di Elementor
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
            add_shortcode('place_order_for', array($this, 'generate_custom_button'));
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

                        // Imposta l'ID del post come variabile globale per Elementor
                        if ($post_id) {
                            global $post;
                            $post = get_post($post_id);
                            setup_postdata($post);
                        }

                        // Usa il contenuto del template di Elementor
                        echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id);

                        // Reimposta i dati del post globale
                        if ($post_id) {
                            wp_reset_postdata();
                        }

                        exit; // Termina l'esecuzione per evitare il caricamento del template predefinito
                    } else {
                        // Elementor non è attivo
                        wp_die('Elementor is not activated. Please activate Elementor plugin to use this feature.');
                    }
                }
            }
            return $template;
        }

        public function generate_custom_button($atts)
        {
            $atts = shortcode_atts(
                array(
                    'path' => '', // Valore predefinito
                    'text' => 'Ordina', // Valore predefinito
                ),
                $atts,
                'place_order_for'
            );

            $path = $atts['path']; // Ottieni il path dagli attributi dello shortcode
            $text = $atts['text']; // Ottieni il testo dagli attributi dello shortcode
            $post_id = get_the_ID(); // Ottieni l'ID del post corrente
            $url = home_url($path . '?post_id=' . $post_id); // Costruisci l'URL

            // Crea il pulsante con stili inline
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
            $output .= '<span class="elementor-button-text">' . $text . '</span>';
            $output .= '</span>';
            $output .= '</a>';
            $output .= '</div>';

            return $output;
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

