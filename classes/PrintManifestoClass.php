<?php

namespace Dokan_Mods;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\PrintManifestoClass')) {
    class PrintManifestoClass
    {
        public function __construct()
        {
            add_action('wp_enqueue_scripts', array($this, 'my_enqueue_scripts'));


            add_action('wp_ajax_load_manifesti_print', [$this, 'load_manifesti_print']);
            add_action('wp_ajax_nopriv_load_manifesti_print', [$this, 'load_manifesti_print']);

            add_action('wp_ajax_get_total_posts', [$this, 'get_total_posts']);
            add_action('wp_ajax_nopriv_get_total_posts', [$this, 'get_total_posts']);

        }


        public function my_enqueue_scripts()
        {
            if (get_query_var('lista-manifesti')) {
                wp_enqueue_script('print-manifesto-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/manifesto-print.js', array('jquery'), null, true);
                wp_localize_script('print-manifesto-script', 'my_ajax_object', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'plugin_url' => DOKAN_SELECT_PRODUCTS_PLUGIN_URL,
                ));
                wp_enqueue_style('manifesto-print-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/manifesto-print.css');
            }
        }


        function get_total_posts()
        {
            $post_id = intval($_POST['post_id']);

            $args = array(
                'post_type' => 'manifesto',
                'post_status' => 'publish',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'annuncio_di_morte_relativo',
                        'value' => $post_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'tipo_manifesto',
                        'value' => array('top', 'silver'),
                        'compare' => 'IN',
                    ),
                ),
                'meta_key' => 'vendor_id',
                'orderby' => 'meta_value_num',
                'order' => 'ASC',
                'fields' => 'ids',
            );

            $manifesti = new WP_Query($args);
            wp_send_json_success(array('total_posts' => $manifesti->found_posts));

            wp_die();
        }


        function load_manifesti_print()
        {
            $post_id = intval($_POST['post_id']);

            $args = array(
                'post_type' => 'manifesto',
                'post_status' => 'publish',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'annuncio_di_morte_relativo',
                        'value' => $post_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'tipo_manifesto',
                        'value' => array('top', 'silver'),
                        'compare' => 'IN',
                    ),
                ),
                'meta_key' => 'vendor_id',
                'orderby' => 'meta_value_num',
                'order' => 'ASC',
                'posts_per_page' => -1, // Get all posts at once
            );

            $manifesti = new WP_Query($args);
            $response = [];

            if ($manifesti->have_posts()) {
                while ($manifesti->have_posts()) {
                    $manifesti->the_post();
                    $post_id = get_the_ID();
                    $vendor_id = get_field('vendor_id');

                    // Get vendor data
                    $vendor_data = (new UtilsAMClass())->get_vendor_data_by_id($vendor_id);

                    ob_start();
                    ?>
                        <div class="text-editor-background" style="background-image: none"
                             data-postid="<?php echo $post_id; ?>" data-vendorid="<?php echo $vendor_id; ?>">
                            <div class="custom-text-editor">
                                <?php the_field('testo_manifesto'); ?>
                            </div>
                        </div>
                    <?php
                    $response[] = [
                        'html' => ob_get_clean(),
                        'vendor_data' => $vendor_data,
                    ];
                }
            }

            wp_send_json_success($response);

            wp_die();
        }


    }
}