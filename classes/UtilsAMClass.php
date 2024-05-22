<?php

namespace Dokan_Mods;
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
            'pensierini' => [334, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/pensierini.php'], // Sostituisci 123 con l'ID del tuo template di Elementor
            'manifesto-top' => [402, DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/manifesto.php'],
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


}