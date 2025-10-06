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

            add_action('wp_ajax_check_manifesti_orientations', [$this, 'check_manifesti_orientations']);
            add_action('wp_ajax_nopriv_check_manifesti_orientations', [$this, 'check_manifesti_orientations']);

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
                        'value' => array('top', 'silver', 'online'),
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
                        'value' => array('top', 'silver','online'),
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

                    // Check se è un manifesto "old"
                    $is_old = get_field('id_old') ? true : false;

                    ob_start();
                    ?>
                        <div class="text-editor-background" style="background-image: none"
                             data-postid="<?php echo $post_id; ?>" data-vendorid="<?php echo $vendor_id; ?>"<?php echo $is_old ? ' data-info="is_old"' : ''; ?>>
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

        function check_manifesti_orientations()
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
                'posts_per_page' => -1,
            );

            $manifesti = new WP_Query($args);
            $hasLandscape = false;
            $hasPortrait = false;
            $firstOrientation = null;
            $orientations = array();

            if ($manifesti->have_posts()) {
                while ($manifesti->have_posts()) {
                    $manifesti->the_post();
                    $vendor_id = get_field('vendor_id');
                    
                    // Get vendor data to check background image orientation
                    $vendor_data = (new UtilsAMClass())->get_vendor_data_by_id($vendor_id);
                    
                    if ($vendor_data && !empty($vendor_data['manifesto_background'])) {
                        // Recupera le dimensioni dell'immagine per determinare l'orientamento
                        $image_path = $vendor_data['manifesto_background'];
                        
                        // Prova a ottenere le dimensioni dell'immagine
                        $image_size = @getimagesize($image_path);
                        $orientation = 'portrait'; // default
                        
                        if ($image_size) {
                            $aspect_ratio = $image_size[0] / $image_size[1];
                            $orientation = ($aspect_ratio > 1) ? 'landscape' : 'portrait';
                        }
                        
                        // Salva il primo orientamento trovato
                        if ($firstOrientation === null) {
                            $firstOrientation = $orientation;
                        }
                        
                        $orientations[] = $orientation;
                        
                        if ($orientation === 'landscape') {
                            $hasLandscape = true;
                        } else {
                            $hasPortrait = true;
                        }
                    } else {
                        // Se non c'è background, considera portrait come default
                        if ($firstOrientation === null) {
                            $firstOrientation = 'portrait';
                        }
                        $orientations[] = 'portrait';
                        $hasPortrait = true;
                    }
                }
            }

            wp_send_json_success(array(
                'hasLandscape' => $hasLandscape,
                'hasPortrait' => $hasPortrait,
                'firstOrientation' => $firstOrientation,
                'totalManifesti' => $manifesti->found_posts,
                'orientations' => $orientations
            ));

            wp_die();
        }


    }
}