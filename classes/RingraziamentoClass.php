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
            add_action('acf/save_post', array($this, 'ringraziamento_save_post'), 20);
        }

        public function ringraziamento_save_post($post_id)
        {

            if (get_post_type($post_id) !== 'ringraziamento') {
                return;
            }

            $post_id_annuncio = get_field('annuncio_di_morte', $post_id);
            //set the title post of $post_id as Trigesimo - $post_title
            $post_title = get_the_title($post_id_annuncio);
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