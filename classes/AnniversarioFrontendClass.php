<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\AnniversarioFrontendClass')) {

    class AnniversarioFrontendClass
    {
        public function __construct()
        {
            //add_action('pre_get_posts', array($this, 'custom_filter_query'));
            add_action('elementor/query/anniversario_loop', array($this, 'apply_custom_filter_query'));

            //add shortcode get_anniversario_count
            add_shortcode('get_nuber_of_anniversary', array($this, 'get_nuber_of_anniversary'));
            add_shortcode('get_anniversary_date', array($this, 'get_anniversary_date'));


        }

        public function apply_custom_filter_query($query)
        {
            // Debug temporaneo
            $post_type = $query->get('post_type');

            // Verifica che siamo nel post type corretto
            if ($post_type === 'anniversario' || (is_array($post_type) && in_array('anniversario', $post_type))) {
                
                $acf_date_field = 'anniversario_data';
                
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

        public function get_nuber_of_anniversary($atts)
        {
            //attr can be post_id
            $atts = shortcode_atts( array(
                'post_id' => get_the_ID(),
            ), $atts, 'get_anniversario_count' );

            $post_id = $atts['post_id'];

            if (!$post_id) {
                return "";
            }

            //get the afc field anniversario_n_anniversario

            $n_anniversario = get_field('anniversario_n_anniversario', $post_id);
            if (!$n_anniversario) {
                return "0";
            }

            return $n_anniversario. '° Anniversario';

        }


        public function  get_anniversary_date($atts)
        {
            $atts = shortcode_atts( array(
                'post_id' => get_the_ID(),
            ), $atts, 'get_anniversay_date' );

            $post_id = $atts['post_id'];

            if (!$post_id) {
                return "";
            }

            //get the afc field anniversario_data
            $anniversario_data = get_field('anniversario_data', $post_id);
            if (!$anniversario_data) {
                return "";
            }

            return date('d/m/Y', strtotime($anniversario_data));

        }

    }
}