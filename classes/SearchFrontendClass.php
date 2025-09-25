<?php

namespace Dokan_Mods;

use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\SearchFrontendClass')) {

    class SearchFrontendClass
    {
        public function __construct()
        {
            add_action('elementor/query/annunci_search', array($this, 'custom_search_query'));
        }

        public function custom_search_query($query)
        {
            if (!is_admin()) {
                $s = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                if ($s) {
                    // Implementa ordinamento condizionale basato sul tipo di post
                    $this->apply_post_type_ordering($query);

                    // Meta query performante per ACF fields: nome, cognome, provincia, citta
                    $meta_query = array(
                        'relation' => 'OR',
                        array(
                            'key' => 'nome',
                            'value' => $s,
                            'compare' => 'LIKE'
                        ),
                        array(
                            'key' => 'cognome',
                            'value' => $s,
                            'compare' => 'LIKE'
                        ),
                        array(
                            'key' => 'citta',
                            'value' => $s,
                            'compare' => 'LIKE'
                        ),
                        array(
                            'key' => 'provincia',
                            'value' => $s,
                            'compare' => 'LIKE'
                        )
                    );

                    // Integra con existing meta_query se presente
                    $existing_meta_query = $query->get('meta_query') ?: array();
                    if (!empty($existing_meta_query)) {
                        $existing_meta_query['relation'] = 'AND';
                        $existing_meta_query[] = $meta_query;
                    } else {
                        $existing_meta_query = $meta_query;
                    }
                    $query->set('meta_query', $existing_meta_query);
                }

                // Applica i filtri della classe FiltersClass
                (new FiltersClass())->custom_filter_query($query);
            }
        }

        private function apply_post_type_ordering($query)
        {
            $post_type = $query->get('post_type');

            if ($post_type === 'annuncio-di-morte' || (is_array($post_type) && in_array('annuncio-di-morte', $post_type))) {
                $query->set('meta_key', 'data_di_morte');
                $query->set('orderby', 'meta_value');
                $query->set('order', 'DESC');
                $query->set('meta_type', 'DATETIME');

                $existing_meta_query = $query->get('meta_query') ?: array();
                if (!empty($existing_meta_query)) {
                    $existing_meta_query['relation'] = 'AND';
                }

                $existing_meta_query[] = array(
                    'key' => 'data_di_morte',
                    'compare' => 'EXISTS'
                );

                $query->set('meta_query', $existing_meta_query);
            }
            elseif ($post_type === 'anniversario' || (is_array($post_type) && in_array('anniversario', $post_type))) {
                $acf_date_field = 'anniversario_data';
                
                $query->set('meta_key', $acf_date_field);
                $query->set('orderby', 'meta_value');
                $query->set('order', 'DESC');
                $query->set('meta_type', 'DATETIME');
                
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
            elseif ($post_type === 'trigesimo' || (is_array($post_type) && in_array('trigesimo', $post_type))) {
                $acf_date_field = 'trigesimo_data';
                
                $query->set('meta_key', $acf_date_field);
                $query->set('orderby', 'meta_value');
                $query->set('order', 'DESC');
                $query->set('meta_type', 'DATETIME');
                
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
        }
    }
}