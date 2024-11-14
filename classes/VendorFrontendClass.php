<?php

namespace Dokan_Mods;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\VendorFrontendClass')) {

    class VendorFrontendClass
    {

        public function __construct()
        {
            add_action('init', array($this, 'shortcode_register'));
            //add_action('pre_get_posts', array($this, 'custom_filter_query'));
            add_action('elementor/query/vendor_filter_query', array($this, 'custom_filter_query'));


        }

        public function shortcode_register()
        {
            add_shortcode('custom_filter_vendor', array($this, 'custom_filter_shortcode'));
            add_shortcode('render_vendor_banner_image', array($this, 'render_vendor_banner_image'));
        }

        public function render_vendor_banner_image($attr){

            $attrs = shortcode_atts(
                array(
                    'vendor_id' => get_the_ID(),
                ),
                $attr
            );

            $vendor = dokan()->vendor->get($attrs['vendor_id']);
            $banner = $vendor->get_banner();
            $banner_url = $banner ? $banner : 'https://via.placeholder.com/150';
            return "<img src='$banner_url' alt='vendor banner' width='150px' height='150px'>";
        }


        function custom_filter_shortcode()
        {
            global $dbClassInstance;
            $provinces = $dbClassInstance->get_all_Province();

            ob_start();
            ?>
            <form id="filter" method="GET" action="">

                <div class="filter-group">
                    <label for="province">Filtra per Provincia</label>
                    <select name="province" id="province">
                        <option value="">Tutte</option>
                        <?php foreach ($provinces as $province) : ?>
                            <option
                                value="<?php echo esc_attr($province['provincia_nome']); ?>" <?php if (isset($_GET['province']) && $_GET['province'] == $province['provincia_nome']) echo "selected"; ?>>
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

            $query_id = 'vendor_filter_query_executed';
            //create the args to get all users that is a vendor that is approved
            if( !is_admin() && !$query->get($query_id) ){

            $args = array(
                'role' => 'vendor',
                $query_id => true,
                'meta_query' => array(
                    'relation' => 'AND', // Aggiungi la relazione AND per maggiore chiarezza
                    array(
                        'key' => 'dokan_enable_selling',
                        'value' => 'yes',
                        'compare' => '='
                    ),
                    array(
                        'key' => 'dokan_admin_approved',
                        'value' => 'yes',
                        'compare' => '='
                    )
                )
            );
                $query = new WP_Query($args);

                if ($query->have_posts()) {
                    $query->set('meta_query', $args['meta_query']);
                }
            }

            (new FiltersClass())->custom_filter_query($query);

        }
    }
}