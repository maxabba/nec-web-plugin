<?php

namespace Dokan_Mods;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
class UtilsAMClass
{

    private $pages;
    private $pages_slug;

    public function __construct()
    {
        $this->pages = [
            'pensierini' => [334, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/pensierini.php'],
            'manifesto-top' => [402, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/manifesto.php'],
            'manifesto-silver' => [402, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/manifesto.php'],
            'manifesto-online' => [402, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/manifesto.php'],
        ];
        $this->pages_slug = array_keys($this->pages);
        $links = array_map(function ($slug) {
            return [$slug => __($slug, 'Dokan_mod')];
        }, $this->pages_slug);

        $this->pages_slug = call_user_func_array('array_merge', $links);

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
    public function get_product_price($product_id)
    {
        $product = wc_get_product($product_id);
        return $product->get_price_html();
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
}