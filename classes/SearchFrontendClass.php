<?php

namespace Dokan_Mods;

use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\SearchFrontendClass')) {

    class SearchFrontendClass
    {
        public function __construct()
        {
            add_action('elementor/query/annunci_search', array($this, 'custom_search_query'));
        }

        public function custom_search_query($query)
        {
            // Verifica che non siamo nella dashboard
            if (!is_admin()) {
                // Ottieni il parametro 's' dalla URL in modo sicuro
                $s = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                // Verifica se la query di test è già stata eseguita
                $test_query_id = 'test_query_executed';

                if ($s && !$query->get($test_query_id)) {
                    $meta_query = array(
                        'relation' => 'OR',
                        array(
                            'key' => 'citta',
                            'value' => $s,
                            'compare' => 'LIKE'
                        ),
                        array(
                            'key' => 'provincia',
                            'value' => $s,
                            'compare' => 'LIKE'
                        )
                    );

                    // Imposta il meta_query


                    // Esegui una query per verificare se ci sono risultati
                    $test_query = new WP_Query(array(
                        'post_type' => $query->get('post_type'),
                        'meta_query' => $meta_query,
                        'posts_per_page' => 1,
                        'fields' => 'ids',  // Recupera solo gli ID per ridurre il consumo di memoria
                        $test_query_id => true  // Aggiungi un ID unico alla query di test
                    ));

                    if ($test_query->have_posts()) {
                        // Se ci sono risultati, non fare nulla
                        $query->set('meta_query', $meta_query);
                    } else {
                        // Se non ci sono risultati, esegui la ricerca completa
                        $query->set('s', $s);
                    }
                    $test_query->reset_postdata();

                }

                // Imposta la query principale con l'ID unico per evitare esecuzioni ripetute
                //$query->set($test_query_id, true);
            }
        }
    }
}