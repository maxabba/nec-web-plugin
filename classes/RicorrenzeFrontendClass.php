<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\RicorrenzeFrontendClass')) {

    class RicorrenzeFrontendClass
    {
        public function __construct()
        {
            //add_action('pre_get_posts', array($this, 'custom_filter_query'));
            add_action('elementor/query/ricorrenze_carousel', array($this, 'apply_custom_filter_query'));

            add_shortcode('get_date_of_ricorrenza', array($this, 'get_date_of_ricorrenza'));
        }

        public function apply_custom_filter_query($query)
        {
            // Imposta i post type
            $query->set('post_type', ['anniversario', 'trigesimo']);

            // Data attuale in formato corretto per DATETIME
            $today = date('Y-m-d');
            $today_no_dash = date('Ymd');

            $meta_query = [
                'relation' => 'OR',
                [
                    'relation' => 'OR',
                    [
                        'key' => 'anniversario_data',
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'DATE',
                    ],
                    [
                        'key' => 'anniversario_data',
                        'value' => $today_no_dash,
                        'compare' => '>=',
                        'type' => 'NUMERIC',
                    ]
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'trigesimo_data',
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'DATE',
                    ],
                    [
                        'key' => 'trigesimo_data',
                        'value' => $today_no_dash,
                        'compare' => '>=',
                        'type' => 'NUMERIC',
                    ]
                ]
            ];

            $query->set('meta_query', $meta_query);

            // Mantieni la post-elaborazione per l'ordinamento e il filtro avanzato
            add_filter('the_posts', [$this, 'sort_and_alternate_posts'], 10, 2);
        }

        /**
         * Custom filter to sort and alternate posts based on their dates
         *
         * @param array $posts Array of posts returned by the query
         * @param WP_Query $query The WP_Query instance
         * @return array Modified array of posts
         */
        public function sort_and_alternate_posts($posts, $query)
        {

            //error log the number of posts and the query vars
            error_log('Number of posts : ' . count($posts));
            //error_log('Query vars: ' . print_r($query->query_vars, true));

            // Get current date for comparison
            $current_date = current_time('Y-m-d');

            // Calculate date 7 days from now
            $end_date = date('Y-m-d', strtotime($current_date . ' +7 days'));

            // Combined array for all eligible posts
            $eligible_posts = [];

            // Also keep track of all posts with dates >= today for fallback
            $all_future_posts = [];

            foreach ($posts as $post) {
                $post_date = null;

                if ($post->post_type === 'anniversario') {
                    $post_date = get_post_meta($post->ID, 'anniversario_data', true);
                    if (!$post_date) {
                        $post_date = get_field('anniversario_data', $post->ID);
                    }
                } else if ($post->post_type === 'trigesimo') {

                    $post_date = get_post_meta($post->ID, 'trigesimo_data', true);
                    if (!$post_date) {
                        $post_date = get_field('trigesimo_data', $post->ID);
                    }
                }

                if ($post_date) {
                    // Store the date in the post object for later sorting
                    $post->custom_date = $post_date;

                    // Add to eligible posts if within 7 days
                    if ($post_date >= $current_date && $post_date <= $end_date) {
                        $eligible_posts[] = $post;
                    }

                    // Add to fallback array if in the future
                    if ($post_date >= $current_date) {
                        $all_future_posts[] = $post;
                    }
                }
            }

            // Sort both arrays by date in ascending order
            $sort_by_date = function ($a, $b) {
                return strtotime($a->custom_date) - strtotime($b->custom_date);
            };

            usort($eligible_posts, $sort_by_date);
            usort($all_future_posts, $sort_by_date);

            // In debug mode, if we have fewer than 3 posts in the next 7 days,
            // use posts from future dates instead
/*            if (defined('WP_DEBUG') && WP_DEBUG && count($eligible_posts) < 3 && count($all_future_posts) >= 3) {
                $eligible_posts = array_slice($all_future_posts, 0, 7);
            }*/

            // If we need to limit the number of posts
            if (isset($query->query_vars['posts_per_page']) && $query->query_vars['posts_per_page'] > 0) {
                $eligible_posts = array_slice($eligible_posts, 0, $query->query_vars['posts_per_page']);
            }

            // Remove our filter to prevent infinite loops
            remove_filter('the_posts', [$this, 'sort_and_alternate_posts'], 10);

            return $eligible_posts;
        }



        public function get_date_of_ricorrenza($attr){

            //attr can be post_id
            $atts = shortcode_atts(
                array(
                    'post_id' => null,
                ),
                $attr,
                'get_date_of_ricorrenza'
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

            $post_type = get_post_type($post_id);
            
            if ($post_type === 'anniversario') {
                // Prima prova con get_post_meta direttamente
                $date_value = get_post_meta($post_id, 'anniversario_data', true);
                
                // Se vuoto, prova con get_field
                if(empty($date_value)) {
                    $date_value = get_field('anniversario_data', $post_id);
                }
                
                // Se ancora vuoto, prova con la field key
                if(empty($date_value)) {
                    $date_value = get_field('field_665ec95bca23d', $post_id);
                }
            } elseif ($post_type === 'trigesimo') {
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
            } else {
                return "";
            }

            if ($date_value) {
                // Convert the date to a timestamp
                $timestamp = strtotime($date_value);
                if ($timestamp) {
                    // Return the date in 27 Settembre, 2025 format
                    return date_i18n('j F, Y', $timestamp);
                }
            }

            return "";


        }

    }
}