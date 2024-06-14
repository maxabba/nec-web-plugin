<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\NecrologiFrontendClass')) {

    class NecrologiFrontendClass
    {

        public function __construct()
        {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_select2'));
            add_action('init', array($this, 'shortcode_register'));
            //add_action('pre_get_posts', array($this, 'custom_filter_query'));
            add_action('elementor/query/tutti_necrologi_pagina_692', array($this, 'custom_filter_query'));

            add_filter('query_vars', array($this, 'add_custom_query_vars_filter'));
           // add_action('init', array($this, 'custom_rewrite_rules'));
        }

        function enqueue_select2()
        {
            wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
        }


        public function shortcode_register()
        {
            add_shortcode('custom_filter', array($this, 'custom_filter_shortcode'));
        }

        function custom_rewrite_rules()
        {
            add_rewrite_rule('^tutti-i-necrologi/([^/]*)/([^/]*)/?', 'index.php?pagename=tutti-i-necrologi&date_filter=$matches[1]&province=$matches[2]', 'top');
        }


        function add_custom_query_vars_filter($vars)
        {
            $vars[] = 'date_filter';
            $vars[] = 'province';
            return $vars;
        }

        function custom_filter_shortcode()
        {
            global $dbClassInstance;
            $provinces = $dbClassInstance->get_all_Province();

            ob_start();
            ?>
            <form id="filter" method="GET" action="">

                <div class="filter-group">
                    <label>Filtra per periodo</label>
                    <ul id="date_filter" style="list-style-type: none; padding-left: 0;">
                        <li><a href="#" data-value="all">Tutti</a></li>
                        <li><a href="#" data-value="today">Oggi</a></li>
                        <li><a href="#" data-value="yesterday">Ieri</a></li>
                        <li><a href="#" data-value="last_week">Ultima settimana</a></li>
                        <li><a href="#" data-value="last_month">Ultimo mese</a></li>
                    </ul>
                    <input type="hidden" name="date_filter" id="date_filter_input" value="all">
                </div>

                <div class="filter-group">
                    <label for="province">Filtra per Provincia</label>
                    <select name="province" id="province">
                        <option value="">Tutte</option>
                        <?php foreach ($provinces as $province) : ?>
                            <option value="<?php echo esc_attr($province['provincia_nome']); ?>" <?php if(isset($_GET['province']) && $_GET['province'] == $province['provincia_nome']) echo "selected" ;?>>
                                <?php echo esc_html($province['provincia_nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="submit" value="Filtra">
                <a href="<?php echo get_permalink(); ?>">Reset</a>
            </form>

            <style>
                .filter-group {
                    margin-bottom: 1em;
                }

                .filter-group label {
                    display: block;
                    margin-bottom: 0.5em;
                }

                .filter-group select, .filter-group ul {
                    width: 100%;
                    padding: 0.5em;
                }

                #date_filter li {
                    margin: 0.5em 0;
                }

                #date_filter li a {
                    text-decoration: none;
                    color: black;
                    display: flex;
                    align-items: center;
                }

                #date_filter li a::before {
                    content: "\25B6"; /* Unicode character for right arrow */
                    color: gold;
                    margin-right: 0.5em;
                }
            </style>

            <script>
                jQuery(function ($) {
                    // Initialize Select2 on the province select element
                    $('#province').select2({
                        placeholder: 'Seleziona una provincia',
                        allowClear: true
                    });

                    // Handle date filter click events
                    $('#date_filter li a').on('click', function (e) {
                        e.preventDefault();
                        var value = $(this).data('value');
                        $('#date_filter_input').val(value);
                        $('#filter').submit();
                    });
                });
            </script>
            <?php
            return ob_get_clean();
        }


        function custom_filter_query($query)
        {
            // Verifica che non siamo nella dashboard e che questa sia la query principale
            if (!is_admin()) {

                $city_filter = get_transient('city_filter_' . session_id()) ?? null;
                $province = get_transient('province' . session_id()) ?? null;
                if (!empty($province) || !empty($city_filter)) {
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