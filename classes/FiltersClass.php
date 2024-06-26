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
            if ($province == null) {
                global $dbClassInstance;
                $this->province = $dbClassInstance->get_provincia_by_comune($city);
            }else{
                $this->province = $province;
            }
            if ($this->city == 'Tutte') {
                global $dbClassInstance;
                $this->city = $dbClassInstance->get_comuni_by_provincia($province);
            }
        }


        public function get_city_filter_meta_query()
        {
            $city_filter = $this->city;
            $province_filter = $this->province;

            // Array base per meta_query
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => 'citta',
                    'value' => $city_filter,
                    'compare' => is_array($city_filter) ? 'IN' : '='
                ),
                array(
                    'key' => 'citta',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'citta',
                    'value' => '',
                    'compare' => '='
                )
            );

            // Aggiunta di condizioni specifiche
            if (is_array($city_filter)) {
                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key' => 'provincia',
                        'value' => $province_filter,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'citta',
                        'value' => 'Tutte',
                        'compare' => '='
                    )
                );
                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key' => 'provincia',
                        'value' => "Tutte",
                        'compare' => '='
                    ),
                    array(
                        'key' => 'citta',
                        'value' => 'Tutte',
                        'compare' => '='
                    )
                );
            } else {
                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key' => 'provincia',
                        'value' => 'Tutte',
                        'compare' => '='
                    ),
                    array(
                        'key' => 'citta',
                        'value' => 'Tutte',
                        'compare' => '='
                    )
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
                        'terms' => array('default-products', 'editable-price'), // Combined terms for better performance
                    )
                ),
/*                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'citta',
                        'value' => $this->city,
                        'compare' => '='
                    ),
                    array(
                        'relation' => 'AND',
                        array(
                            'key' => 'provincia',
                            'value' => $this->province,
                            'compare' => '='
                        ),
                        array(
                            'key' => 'citta',
                            'value' => 'Tutte',
                            'compare' => '='
                        )
                    ),
                    array(
                        'relation' => 'AND',
                        array(
                            'key' => 'provincia',
                            'value' => 'Tutte',
                            'compare' => '='
                        ),
                        array(
                            'key' => 'citta',
                            'value' => 'Tutte',
                            'compare' => '='
                        )
                    )

                )*/
            );
            return $args;
        }
    }
}