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
            // Aggiungi il nuovo hook ottimizzato
            add_action('elementor/query/tutti_necrologi_pagina_692', array($this, 'apply_custom_filter_query'), 10, 2);

            add_filter('query_vars', array($this, 'add_custom_query_vars_filter'));


            // add_action('init', array($this, 'custom_rewrite_rules'));
        }

        public function enqueue_select2()
        {
            wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
        }


        public function shortcode_register()
        {
            add_shortcode('custom_filter', array($this, 'custom_filter_shortcode'));
        }

        public function custom_rewrite_rules()
        {
            add_rewrite_rule('^tutti-i-necrologi/([^/]*)/([^/]*)/?', 'index.php?pagename=tutti-i-necrologi&date_filter=$matches[1]&province=$matches[2]', 'top');
        }


        public function add_custom_query_vars_filter($vars)
        {
            $vars[] = 'date_filter';
            $vars[] = 'province';
            return $vars;
        }

        public function custom_filter_shortcode()
        {
            global $dbClassInstance;
            $provinces = $dbClassInstance->get_all_Province();

            ob_start();
            ?>
            <form id="filter" method="GET" action="">
                <div class="filter-group">
                    <label>Filtra per periodo</label>
                    <ul id="date_filter" style="list-style-type: none; padding-left: 0;">
                        <?php
                        // Get the current date filter from URL parameter, default to 'all' if not set
                        $current_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';

                        // Define filter options
                        $filter_options = [
                            'all' => 'Tutti',
                            'today' => 'Oggi',
                            'yesterday' => 'Ieri',
                            'last_week' => 'Ultima settimana',
                            'last_month' => 'Ultimo mese'
                        ];

                        // Loop through each option and create the list item
                        foreach ($filter_options as $value => $label) {
                            // Check if this is the current active filter
                            $active_class = ($value === $current_filter) ? 'active' : '';

                            // Output the list item with conditional active class
                            echo '<li><a href="#" data-value="' . esc_attr($value) . '" class="' . esc_attr($active_class) . '">' . esc_html($label) . '</a></li>';
                        }
                        ?>
                    </ul>
                    <input type="hidden" name="date_filter" id="date_filter_input" value="all">
                </div>

                <div class="filter-group">
                    <label for="province">Filtra per Provincia</label>
                    <select name="province" id="province">
                        <option value="">Tutte</option>
                        <?php foreach ($provinces as $province) : ?>
                            <option value="<?php echo esc_attr($province['provincia_nome']); ?>" <?php if (isset($_GET['province']) && $_GET['province'] == $province['provincia_nome']) echo "selected"; ?>>
                                <?php echo esc_html($province['provincia_nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group" id="city-filter-group">
                    <label for="city_filter">Filtra per Città</label>
                    <select name="city_filter" id="city_filter" disabled>
                        <option value="">Tutte</option>
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

                #date_filter a.active {
                    font-weight: bold;
                }

            </style>

            <script>
                jQuery(function ($) {
                    // Initialize Select2 on the province and city select elements
                    $('#province, #city_filter').select2({
                        placeholder: 'Tutte',
                        allowClear: true
                    });

                    // Handle date filter click events
                    $('#date_filter li a').on('click', function (e) {
                        e.preventDefault();
                        var value = $(this).data('value');
                        $('#date_filter_input').val(value);
                        $('#filter').submit();
                    });

                    // Dynamic city population
                    $('#province').on('change', function () {
                        var selectedProvince = $(this).val();
                        var $citySelect = $('#city_filter');

                        if (selectedProvince) {
                            // AJAX call to get cities
                            $.ajax({
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                type: 'POST',
                                data: {
                                    action: 'get_comuni_by_provincia',
                                    province: selectedProvince
                                },
                                beforeSend: function () {
                                    $citySelect.prop('disabled', true);
                                    $citySelect.html('<option value="">Caricamento...</option>');
                                },
                                success: function (response) {
                                    var cities = JSON.parse(response);
                                    var options = '<option value="">Tutte</option>';

                                    // Add current city filter selection if exists
                                    var currentCity = '<?php echo isset($_GET["city_filter"]) ? esc_js($_GET["city_filter"]) : ""; ?>';

                                    cities.forEach(function (city) {
                                        var selected = city === currentCity ? 'selected' : '';
                                        options += `<option value="${city}" ${selected}>${city}</option>`;
                                    });

                                    $citySelect.html(options);
                                    $citySelect.prop('disabled', false);
                                },
                                error: function () {
                                    $citySelect.html('<option value="">Errore nel caricamento</option>');
                                }
                            });
                        } else {
                            // Reset city filter
                            $citySelect.html('<option value="">Tutte</option>');
                            $citySelect.prop('disabled', true);
                        }
                    });

                    // Trigger province change if a province is pre-selected
                    var preSelectedProvince = $('#province').val();
                    if (preSelectedProvince) {
                        $('#province').trigger('change');
                    }
                });
            </script>
            <?php
            return ob_get_clean();
        }


        public function apply_custom_filter_query($query)
        {
            // Implementa ordinamento condizionale basato sul tipo di post
            $post_type = $query->get('post_type');

            // Se il post type è 'annuncio-di-morte', ordina per data di morte
            if ($post_type === 'annuncio-di-morte' || (is_array($post_type) && in_array('annuncio-di-morte', $post_type))) {
                // Solo se non è già specificato un ordinamento personalizzato
                $query->set('meta_key', 'data_di_morte');
                $query->set('orderby', 'meta_value');
                $query->set('order', 'DESC');

                // Aggiungi meta_query per assicurare che i post abbiano il campo data_di_morte
                $existing_meta_query = $query->get('meta_query') ?: array();
                if (!empty($existing_meta_query)) {
                    $existing_meta_query['relation'] = 'AND';
                }

                $existing_meta_query[] = array(
                    'key' => 'data_di_morte',
                    'compare' => 'EXISTS'
                );

                $query->set('meta_query', $existing_meta_query);

            } else {
                // Per altri tipi di post, usa l'ordinamento per data di pubblicazione
                if (!$query->get('orderby')) {
                    $query->set('orderby', 'date');
                    $query->set('order', 'DESC');
                }
            }

            // Applica i filtri della classe FiltersClass
            (new FiltersClass())->custom_filter_query($query);
        }

    }
}