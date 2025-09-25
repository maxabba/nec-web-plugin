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
            //set the title post of $post_id as Trigesimo - $post_title
            $post_title = get_the_title($post_id_annuncio);
            $n_anniversario  = get_field('anniversario_n_anniversario', $post_id);
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $post_title
            );
            wp_update_post($post_data);


            $provincia = get_field('provincia', $post_id_annuncio);
            $citta = get_field('citta', $post_id_annuncio);

            update_field('provincia', $provincia, $post_id);
            update_field('citta', $citta, $post_id);
        }

    }
}