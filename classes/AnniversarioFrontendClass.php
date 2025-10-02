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
                    $query->set('order', 'ASC');
                    $query->set('meta_type', 'DATE');
                    $query->set('meta_query',
                        array(
                            'key' => $acf_date_field,
                            'value' => date('Ymd'),
                            'compare' => '>=',
                            'type' => 'NUMERIC',
                        ),
                    );
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
                'post_id' => null,
            ), $atts, 'get_anniversary_date' );

            // Gestione migliorata del post_id per Elementor
            $post_id = $atts['post_id'];
            
            if (!$post_id) {
                // Prima prova con il global $post
                global $post;
                if ($post && isset($post->ID)) {
                    $post_id = $post->ID;
                } else {
                    // Fallback a get_the_ID()
                    $post_id = get_the_ID();
                }
            }
            
            // Se ancora non abbiamo un post_id valido, ritorna vuoto
            if (!$post_id) {
                return '';
            }

            // Prima prova con get_post_meta direttamente
            $anniversario_data = get_post_meta($post_id, 'anniversario_data', true);
            
            // Se vuoto, prova con get_field
            if(empty($anniversario_data)) {
                $anniversario_data = get_field('anniversario_data', $post_id);
            }

            // Se ancora vuoto, prova con la field key
            if(empty($anniversario_data)) {
                $anniversario_data = get_field('field_665ec95bca23d', $post_id);
            }

            if($anniversario_data){
                $timestamp = strtotime($anniversario_data);
                if($timestamp){

                    error_log("data anniversario formattata da timestamp".(date_i18n("j F, Y", $timestamp)));


                    return date_i18n("j F, Y", $timestamp);
                }
            }
            return "";
        }

    }
}