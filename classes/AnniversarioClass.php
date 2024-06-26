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

            if (get_post_type($post_id) !== 'anniversario') {
                return;
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