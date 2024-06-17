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
            add_action('elementor/query/anniversario_loop', array($this, 'custom_filter_query'));
        }


        function custom_filter_query($query)
        {
            // Verifica che non siamo nella dashboard e che questa sia la query principale
            if (!is_admin()) {

                $city_filter = get_transient('city_filter_' . session_id()) ?? null;
                $province = get_transient('province' . session_id()) ?? null;
                if ((!empty($province) || !empty($city_filter) && !isset($_GET['province']))) {
                    $meta_query = (new FiltersClass($city_filter, $province))->get_city_filter_meta_query();
                    $query->set('meta_query', $meta_query);
                }

                $queryVars = $query->query_vars;
                // Filtra per periodo
                if (isset($_GET['date_filter']) && $_GET['date_filter'] != 'all') {
                    $date_filter = $_GET['date_filter'];
                    $date_query = array();

                    switch ($date_filter) {
                        case 'today':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('today')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                        case 'yesterday':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('yesterday')),
                                    'before' => date('Y-m-d', strtotime('today')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                        case 'last_week':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('-1 week')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                        case 'last_month':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('-1 month')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                    }

                    $query->set('date_query', $date_query);
                }


                // Filtra per provincia
                $meta_query = array();
                if (isset($_GET['province']) && !empty($_GET['province'])) {
                    $meta_query = array(
                        'relation' => 'AND',
                        array(
                            'key' => 'provincia',
                            'value' => sanitize_text_field($_GET['province']),
                            'compare' => '='
                        ),
                        array(
                            'key' => 'citta',
                            'value' => 'Tutte',
                            'compare' => '='
                        )
                    );

                    $query->set('meta_query', $meta_query);
                }

                if (!empty($meta_query)) {
                    $query->set('meta_query', $meta_query);
                }
            }
        }
    }
}