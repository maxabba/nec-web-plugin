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
                    $query->set('order', 'ASC');
                    $query->set('meta_type', 'DATE');
                    $query->set('meta_query',
                        array(
                            'key' => $acf_date_field,
                            'value' => date('Ymd'),
                            'compare' => '>=',
                            'type' => 'DATE',
                        ),
                    );
/*
                    //start from today
                    $today = date('Y-m-d');
                    $today_no_dash = date('Ymd');

                    // Aggiungi meta_query per assicurare che i post abbiano il campo data
                    $existing_meta_query = $query->get('meta_query') ?: array();
                    if (!empty($existing_meta_query)) {
                        $existing_meta_query['relation'] = 'AND';
                    }

                    $existing_meta_query[] =
                    array(
                        'key' => $acf_date_field,
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'NUMERIC',
                    );

                    $existing_meta_query[] = array(
                        'key' => $acf_date_field,
                        'compare' => 'EXISTS'
                    );

                    $query->set('meta_query', $existing_meta_query);*/

            }

            (new FiltersClass())->custom_filter_query($query);
        }


        public  function get_trigesimo_date($attrs) {
            //attr can be post_id
            $atts = shortcode_atts(
                array(
                    'post_id' => null,
                ),
                $attrs,
                'get_trigesimo_date'
            );

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
            $date_value = get_post_meta($post_id, 'trigesimo_data', true);
            
            // Se vuoto, prova con get_field
            if(empty($date_value)) {
                $date_value = get_field('trigesimo_data', $post_id);
            }

            // Se ancora vuoto, prova con la field key
            if(empty($date_value)) {
                $date_value = get_field('field_6734d2e598b99', $post_id);
            }

            if ($date_value) {
                // Convert the date to a timestamp
                $timestamp = strtotime($date_value);

                if ($timestamp !== false) {
                    // Format the date as "d/m/Y" date('j F, Y', $timestamp); italian format, i
                    return date_i18n('j F, Y', $timestamp);
                }
            }

            return ''; // Return empty string if no valid date found
        }

    }
}