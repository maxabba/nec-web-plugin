<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . 'RingraziamentoClass')) {
    class RingraziamentoClass
    {
        public function __construct()
        {
            add_filter('acf/fields/post_object/query/name=annuncio_di_morte', array($this, 'filter_annunci_post_for_ringraziamenti_by_user'), 10, 3);
            add_action('acf/save_post', array($this, 'ringraziamento_save_post'), 20);
            add_action('acf/validate_save_post', array($this, 'validate_ringraziamento_post'), 10, 0);
        }

        public function ringraziamento_save_post($post_id)
        {
            if (get_post_type($post_id) !== 'ringraziamento') {
                return;
            }

            //se è admin, pulisci i transient e ritorna
            if (current_user_can('administrator')) {
                // Pulisci il transient specifico per questo post
                $transient_key = 'city_data_' . $post_id;
                delete_transient($transient_key);
                return;
            }

            // Handle post status change from inline control
            $inline_status = $_POST['acf_post_status_control'] ?? null;
            if ($inline_status) {
                $new_status = sanitize_text_field($inline_status);
                
                if ($new_status === 'delete') {
                    // Handle deletion with confirmation
                    wp_delete_post($post_id, true);
                    wp_redirect(add_query_arg('deleted', '1', home_url('/dashboard/lista-annunci')));
                    exit;
                } elseif (in_array($new_status, ['draft', 'publish', 'pending'])) {
                    // Update post status
                    wp_update_post([
                        'ID' => $post_id, 
                        'post_status' => $new_status
                    ]);
                }
            }

            $post_id_annuncio = get_field('annuncio_di_morte', $post_id);

            // Get the title for the ringraziamento
            $post_title = get_the_title($post_id_annuncio);

            // Create post data array with both title and name (slug)
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $post_title,
                'post_name' => sanitize_title($post_title) // This ensures the proper permalink structure
            );

            // Remove the current hook to prevent infinite loop
            remove_action('acf/save_post', array($this, 'ringraziamento_save_post'), 20);

            // Update the post
            wp_update_post($post_data);

            // Re-add the hook
            add_action('acf/save_post', array($this, 'ringraziamento_save_post'), 20);

            // Update city and province from annuncio di morte
            $provincia = get_field('provincia', $post_id_annuncio);
            $citta = get_field('citta', $post_id_annuncio);

            // Fallback al vendor se annuncio ha valori vuoti o "Tutte"
            if (empty($provincia) || strtolower($provincia) === 'tutte' || empty($citta) || strtolower($citta) === 'tutte') {
                $user_id = get_current_user_id();
                $store_info = dokan_get_store_info($user_id);

                if (empty($citta) || strtolower($citta) === 'tutte') {
                    $citta = $store_info['address']['city'] ?? '';
                }

                if (empty($provincia) || strtolower($provincia) === 'tutte') {
                    global $dbClassInstance;
                    $user_city = $store_info['address']['city'] ?? '';
                    $provincia = $dbClassInstance->get_provincia_by_comune($user_city);
                }
            }

            // Salva solo se validi e non "Tutte"
            if (!empty($provincia) && strtolower($provincia) !== 'tutte') {
                update_field('provincia', $provincia, $post_id);
            }

            if (!empty($citta) && strtolower($citta) !== 'tutte') {
                update_field('citta', $citta, $post_id);
            }
        }

        public function validate_ringraziamento_post()
        {
            // Check if the post type is 'ringraziamento'
            if (isset($_POST['post_type']) && $_POST['post_type'] !== 'ringraziamento') {
                return;
            }

            // Get the value of the 'annuncio_di_morte' field
            $field_value = $_POST['acf']['annuncio_di_morte'] ?? '';

            // Query for posts with the same 'annuncio_di_morte' value
            $posts = get_posts(array(
                'post_type' => 'ringraziamento',
                'author' => get_current_user_id(),
                'meta_query' => array(
                    array(
                        'key' => 'annuncio_di_morte',
                        'value' => $field_value,
                    )
                )
            ));

            // If a post with the same 'annuncio_di_morte' value exists, show an error message
            if (!empty($posts)) {
                acf_add_validation_error('annuncio_di_morte', 'Un ringraziamento con lo stesso annuncio di morte esiste già.');
            }
        }

        public function filter_annunci_post_for_ringraziamenti_by_user($args, $field, $post)
        {
            $args['author'] = get_current_user_id();
            return $args;
        }

    }
}