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
        }


        public function apply_custom_filter_query($query)
        {

            $acf_date_field = 'trigesimo_data';

            if (!isset($_GET['date_filter'])) {
                $query->set('meta_key', $acf_date_field);
                $query->set('orderby', 'meta_value');
                $query->set('order', 'ASC');
                $query->set('meta_query', [
                    [
                        'key' => 'anniversario_data',
                        'value' => date('Ymd'),
                        'compare' => '>=',
                        'type' => 'NUMERIC',
                    ]
                ]);
                // Log per debug (opzionale)
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ordinamento anniversario per data ACF: ' . $acf_date_field);
                }
            }

            (new FiltersClass())->custom_filter_query($query);
        }
    }
}