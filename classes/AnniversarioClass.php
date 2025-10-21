<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . 'AnniversarioClass')) {
    class AnniversarioClass
    {
        public function __construct(){
            //add_action('init', array($this, 'shortcode_register'));
            add_action('acf/save_post', array($this, 'anniversario_save_post'), 20);
            //add_action('acf/validate_save_post', array($this, 'validate_anniversario_post'), 10, 0);
        }

        public function anniversario_save_post($post_id){


            if (current_user_can('administrator')) {
                return;
            }

            if (get_post_type($post_id) !== 'anniversario') {
                return;
            }

            // Handle post status change from inline control
            $inline_status = $_POST['acf_post_status_control'] ?? null;
            if ($inline_status) {
                $new_status = sanitize_text_field($inline_status);

                if ($new_status === 'delete') {
                    // Handle deletion with confirmation
                    wp_delete_post($post_id, true);
                    wp_redirect(add_query_arg('deleted', '1', home_url('/dashboard/lista-anniversari')));
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

            // Validate that post_id_annuncio is valid
            if (empty($post_id_annuncio) || !get_post($post_id_annuncio)) {
                error_log("AnniversarioClass: post_id_annuncio non valido per anniversario {$post_id}");
                return;
            }

            // Get the title for the anniversario
            $post_title = get_the_title($post_id_annuncio);
            $n_anniversario  = get_field('anniversario_n_anniversario', $post_id);

            // Create post data array with both title and name (slug)
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $post_title,
                'post_name' => sanitize_title($post_title)
            );

            // Remove the current hook to prevent infinite loop
            remove_action('acf/save_post', array($this, 'anniversario_save_post'), 20);

            // Update the post
            wp_update_post($post_data);

            // Re-add the hook
            add_action('acf/save_post', array($this, 'anniversario_save_post'), 20);

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

    }
}