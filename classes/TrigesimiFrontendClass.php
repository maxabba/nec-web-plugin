<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\TrigesimiFrontendClass')) {

    class TrigesimiFrontendClass
    {
        public function __construct()
        {
            //add_action('pre_get_posts', array($this, 'custom_filter_query'));
            add_action('elementor/query/trigesimo_loop', array($this, 'apply_custom_filter_query'));

            //add shortcode get_trigesimo_date
            add_shortcode('get_trigesimo_date', array($this, 'get_trigesimo_date'));
        }


        public function apply_custom_filter_query($query)
        {
            // Debug temporaneo
            $post_type = $query->get('post_type');

            // Verifica che siamo nel post type corretto
            if ($post_type === 'trigesimo' || (is_array($post_type) && in_array('trigesimo', $post_type))) {
                
                $acf_date_field = 'trigesimo_data';
                
                // Ordina sempre per data ACF ma senza filtri di data

                    $query->set('meta_key', $acf_date_field);
                    $query->set('orderby', 'meta_value');
                    $query->set('order', 'DESC');
                    $query->set('meta_type', 'DATETIME');
                    
                    // Aggiungi meta_query per assicurare che i post abbiano il campo data
                    $existing_meta_query = $query->get('meta_query') ?: array();
                    if (!empty($existing_meta_query)) {
                        $existing_meta_query['relation'] = 'AND';
                    }
                    
                    $existing_meta_query[] = array(
                        'key' => $acf_date_field,
                        'compare' => 'EXISTS'
                    );
                    
                    $query->set('meta_query', $existing_meta_query);

            }

            (new FiltersClass())->custom_filter_query($query);
        }


        public  function get_trigesimo_date($attrs) {
            //attr can be post_id
            $atts = shortcode_atts(
                array(
                    'post_id' => get_the_ID(),
                ),
                $attrs,
                'get_trigesimo_date'
            );

            $post_id = $atts['post_id'];

            // Get the ACF date field value
            $date_value = get_field('trigesimo_data', $post_id);

            if ($date_value) {
                // Convert the date to a timestamp
                $timestamp = strtotime($date_value);

                if ($timestamp !== false) {
                    // Format the date as "d/m/Y"
                    return date('d/m/Y', $timestamp);
                }
            }

            return ''; // Return empty string if no valid date found
        }

    }
}