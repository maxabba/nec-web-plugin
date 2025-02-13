<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\TrigesimiClass')) {

    class TrigesimiClass
    {

        public function __construct()
        {
            add_filter('acf/fields/post_object/query/name=annuncio_di_morte', array($this, 'filter_annunci_post_for_trigesimi_by_user'), 10, 3);
            add_action('init', array($this, 'shortcode_register'));
            add_action('acf/save_post', array($this, 'trigesimi_save_post'), 20);
            add_action('acf/validate_save_post', array($this, 'validate_trigesimo_post'), 10, 0);

        }

        public function shortcode_register()
        {
            add_shortcode('acf_field_value', array($this, 'get_acf_field_value'));
            add_shortcode('acf_display_image', array($this, 'display_acf_image'));
        }


        public function validate_trigesimo_post()
        {
            // Check if the post type is 'trigesimo'
            if (isset($_POST['post_type']) && $_POST['post_type'] !== 'trigesimo') {
                return;
            }

            // Get the value of the 'field_66570739481f1' field
            $field_value = $_POST['acf']['field_66570739481f1'] ?? '';

            // Query for posts with the same 'field_66570739481f1' value
            $posts = get_posts(array(
                'post_type' => 'trigesimo',
                'author' => get_current_user_id(),
                'meta_query' => array(
                    array(
                        'key' => 'field_66570739481f1',
                        'value' => $field_value,
                    )
                )
            ));

            // If a post with the same 'field_66570739481f1' value exists, show an error message
            if (!empty($posts)) {
                acf_add_validation_error('field_66570739481f1', 'Un trigesimo con lo stesso annuncio di morte esiste già.');
            }
        }

        public function trigesimi_save_post($post_id)
        {
            if (get_post_type($post_id) !== 'trigesimo') {
                return;
            }

            $post_id_annuncio = get_field('annuncio_di_morte', $post_id);

            // Get the title for the trigesimo
            $post_title = get_the_title($post_id_annuncio);

            // Create post data array with both title and name (slug)
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $post_title,
                'post_name' => sanitize_title($post_title) // This ensures the proper permalink structure
            );

            // Remove the current hook to prevent infinite loop
            remove_action('acf/save_post', array($this, 'trigesimi_save_post'), 20);

            // Update the post
            wp_update_post($post_data);

            // Re-add the hook
            add_action('acf/save_post', array($this, 'trigesimi_save_post'), 20);

            // Update city and province meta
            $provincia = get_field('provincia', $post_id_annuncio);
            $citta = get_field('citta', $post_id_annuncio);

            update_field('provincia', $provincia, $post_id);
            update_field('citta', $citta, $post_id);
        }

        public function filter_annunci_post_for_trigesimi_by_user($args, $field, $post)
        {
            $args['author'] = get_current_user_id();
            return $args;
        }


        public function get_acf_field_value($atts)
        {
            // Estrai gli attributi passati allo shortcode
            $atts = shortcode_atts(array(
                'post_object_field' => '',
                'related_field' => ''
            ), $atts, 'acf_field_value');
            //get the post id
            $post_id = get_the_ID();
            // Verifica che i nomi dei campi siano stati forniti
            if (empty($atts['post_object_field']) || empty($atts['related_field'])) {
                return '';
            }

            // Ottieni l'ID del post dal campo post object (assumendo che sia un Post Object e ritorni l'ID del post)
            $related_post_id = get_field($atts['post_object_field'], $post_id);
            if (!$related_post_id) {
                return '';
            }

            // Ottieni il valore del campo ACF relativo al post object
            $related_field_value = get_field($atts['related_field'], $related_post_id);
            if (!$related_field_value) {
                return '';
            }

            // Restituisci il valore del campo ACF relativo
            return $related_field_value;
        }

        public function display_acf_image($atts)
        {
            // Estrai gli attributi passati allo shortcode
            $atts = shortcode_atts(array(
                'post_object_field' => '',
                'related_field' => '',
                'width' => '',
                'height' => ''
            ), $atts, 'acf_display_image');

            // Verifica che i nomi dei campi siano stati forniti
            if (empty($atts['post_object_field']) || empty($atts['related_field'])) {
                return '';
            }

            // Ottieni l'ID del post dal campo post object (assumendo che sia un Post Object e ritorni l'ID del post)
            $related_post_id = get_field($atts['post_object_field']);
            if (!$related_post_id) {
                return '';
            }

            // Ottieni il valore del campo ACF relativo al post object
            $related_field_value = get_field($atts['related_field'], $related_post_id);
            if (!$related_field_value || !is_array($related_field_value) || !isset($related_field_value['url'])) {
                return '';
            }

            // Prepara gli attributi di altezza e larghezza
            $width = !empty($atts['width']) ? ' width="' . esc_attr($atts['width']) . '"' : '';
            $height = !empty($atts['height']) ? ' height="' . esc_attr($atts['height']) . '"' : '';

            // Restituisci l'HTML dell'immagine
            return '<img src="' . esc_url($related_field_value['url']) . '"' . $width . $height . ' alt="Dynamic Image">';
        }



    }
}