<?php
namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . 'FiltersClass')) {
    class FiltersClass
    {
        protected $city;
        protected $province;

        public function __construct($city = null, $province = null)
        {
            $this->city = $city;
            global $dbClassInstance;

            $this->province = $dbClassInstance->get_comuni_by_provincia($province);

        }


        public function get_city_filter_meta_query()
        {
            $city_filter = $this->city;

            $meta_query = array(
                'relation' => 'AND',
                array(
                    'key' => 'citta',
                    'value' => $city_filter,
                    'compare' => '='
                ),
                array(
                    'key' => 'citta',
                    'value' => 'Tutte',
                    'compare' => '='
                ),
                array(
                    'key' => 'citta',
                    'compare' => 'NOT EXISTS' // Questa condizione seleziona i post che non hanno il campo 'city'
                ),
                array(
                    'key' => 'citta',
                    'value' => '', // Questa condizione verifica i post con il campo 'city' vuoto
                    'compare' => '='
                )
            );

            // If $this->province is set and is an array, add a new condition
            if (isset($this->province) && is_array($this->province)) {
                $meta_query[] = array(
                    'key' => 'citta',
                    'value' => $this->province,
                    'compare' => 'IN'
                );
            }
            return $meta_query;
        }

        public function get_arg_query_Select_product_form()
        {
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'tax_query' => array(
                    'relation' => 'OR',
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'default-products'
                    ),
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'editable-price',
                        'operator' => 'IN'
                    )
                ),
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'citta',
                        'value' => $user_city,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'citta',
                        'value' => 'Tutte',
                        'compare' => '='
                    ),
                    array(
                        'key' => 'citta',
                        'compare' => 'NOT EXISTS' // Questa condizione seleziona i post che non hanno il campo 'city'
                    ),
                    array(
                        'key' => 'citta',
                        'value' => '', // Questa condizione verifica i post con il campo 'city' vuoto
                        'compare' => '='
                    )
                )
            );
            return $args;
        }
    }
}