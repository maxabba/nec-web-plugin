<?php
namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . 'FiltersClass')) {
    class FiltersClass
    {
        protected $city;
        protected $province;
        protected $geolocator;

        public function __construct($city = null, $province = null)
        {
            $this->geolocator = new CloudFlareGeo();

            // Se città e provincia non sono specificate, prova la geolocalizzazione
            if ($city === null && $province === null) {
                $location = $this->get_geolocation();
                $this->city = $location['city'] ?? null;
                $this->province = $location['province'] ?? null;
            } else {
                $this->city = $city;
                if ($province == null) {
                    global $dbClassInstance;
                    $this->province = $dbClassInstance->get_provincia_by_comune($city);
                } else {
                    $this->province = $province;
                }
            }

            if ($this->city == 'Tutte') {
                global $dbClassInstance;
                $this->city = $dbClassInstance->get_comuni_by_provincia($province);
            }
        }

        private function get_geolocation()
        {
            // Prima controlla se l'utente ha già fatto una selezione manuale
            $manual_city = get_transient('city_filter_' . session_id());
            $manual_province = get_transient('province' . session_id());

            if ($manual_city || $manual_province) {
                return [
                    'city' => $manual_city,
                    'province' => $manual_province
                ];
            }

            // Altrimenti usa la geolocalizzazione
            $location = $this->geolocator->get_location();
            if (!isset($location['error'])) {

                if($location['city'] == 'N/A') {
                    return ['city' => null, 'province' => null];
                }

                global $dbClassInstance;
                $city = $location['city'];
                $province = $dbClassInstance->get_provincia_by_comune($city);

                // Salva temporaneamente la localizzazione automatica
                set_transient('city_filter_' . session_id(), $city, DAY_IN_SECONDS);
                set_transient('province' . session_id(), $province, DAY_IN_SECONDS);

                return [
                    'city' => $city,
                    'province' => $province
                ];
            }

            return ['city' => null, 'province' => null];
        }


        public function customize_elementor_query($query)
        {
            // Early return se non necessario
            if (is_admin()) {
                return;
            }

            $city_filter = get_transient('city_filter_' . session_id());
            $province = get_transient('province' . session_id());

            if (empty($province) && empty($city_filter)) {
                return;
            }

            // Pre-fetch dei post IDs con query ottimizzata
            global $wpdb;

            $cache_key = 'necrologi_' . md5($city_filter . $province);
            $post_ids = wp_cache_get($cache_key);

            if (false === $post_ids) {
                // Query base
                $sql = "SELECT DISTINCT p.ID 
                FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'annuncio-di-morte' 
                AND p.post_status = 'publish'
                AND (";

                $where_conditions = [];
                $where_params = [];

                // Condizione città specifica
                if ($city_filter) {
                    $where_conditions[] = "(pm.meta_key = 'citta' AND pm.meta_value = %s)";
                    $where_params[] = $city_filter;
                }

                // Condizione "Tutte" per provincia
                if ($province) {
                    $where_conditions[] = "(pm.meta_key = 'provincia' AND pm.meta_value = %s AND 
                                  EXISTS (
                                      SELECT 1 FROM {$wpdb->postmeta} pm2 
                                      WHERE pm2.post_id = p.ID 
                                      AND pm2.meta_key = 'citta' 
                                      AND pm2.meta_value = 'Tutte'
                                  ))";
                    $where_params[] = $province;
                }

                $sql .= implode(" OR ", $where_conditions) . ")";

                $prepared_sql = $wpdb->prepare($sql, $where_params);
                $post_ids = $wpdb->get_col($prepared_sql);

                wp_cache_set($cache_key, $post_ids, '', 3600);
            }

            if (!empty($post_ids)) {
                $query->set('post__in', $post_ids);
                // Rimuovi le meta_query esistenti per evitare JOIN non necessari
                $query->set('meta_query', []);
            } else {
                // Forza nessun risultato
                $query->set('post__in', [0]);
            }

            // Ottimizzazioni aggiuntive
            $query->set('no_found_rows', true);
            $query->set('update_post_meta_cache', false);
            $query->set('update_post_term_cache', false);
        }



       public function get_city_filter_meta_query()
        {
            $city_filter = $this->city;
            $province_filter = $this->province;

            // Array base per meta_query
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => 'citta',
                    'value' => $city_filter,
                    'compare' => is_array($city_filter) ? 'IN' : '='
                ),
                array(
                    'key' => 'provincia',
                    'value' => "Tutte",
                    'compare' => '='
                )
            );
            return $meta_query;
        }




        public function get_arg_query_Select_product_form()
        {
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'tax_query' => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'operator' => 'IN',
                        'terms' => array('default-products'), // Products with 'default-products' category
                    ),
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'operator' => 'NOT IN',
                        'terms' => array('editable-price'), // Products with 'editable-price' category
                    ),

                ),
            );
            return $args;
        }

        public function get_arg_query_Select_product_editable()
        {
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'tax_query' => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'editable-price', // Products with 'default-products' category
                    ),
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'default-products', // Products with 'editable-price' category
                    ),
                ),
            );
            return $args;
        }


        function custom_filter_query($query)
        {
            // Verifica che non siamo nella dashboard e che questa sia la query principale
            if (!is_admin()) {

                if (empty($_GET['province'])) {
                    $city_filter = get_transient('city_filter_' . session_id()) ?? null;
                    $province = get_transient('province' . session_id()) ?? null;
                    if ((!empty($province) || !empty($city_filter) && !isset($_GET['province']))) {
                        $meta_query = (new FiltersClass($city_filter, $province))->get_city_filter_meta_query();
                        $query->set('meta_query', $meta_query);
                    }
                }

                $queryVars = $query->query_vars;
                // Filtra per periodo
                if (isset($_GET['date_filter']) && $_GET['date_filter'] != 'all') {
                    $date_filter = $_GET['date_filter'];
                    $date_query = array();

                    switch ($date_filter) {
                        case 'today':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('today')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                        case 'yesterday':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('yesterday')),
                                    'before' => date('Y-m-d', strtotime('today')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                        case 'last_week':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('-1 week')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                        case 'last_month':
                            $date_query = array(
                                array(
                                    'after' => date('Y-m-d', strtotime('-1 month')),
                                    'inclusive' => true,
                                ),
                            );
                            break;
                    }

                    $query->set('date_query', $date_query);
                }


                // Filtra per provincia
                $meta_query = array();
                if (!empty($_GET['province'])) {
                    global $dbClassInstance;

                    // Ottieni l'elenco dei comuni della provincia selezionata
                    $province = sanitize_text_field($_GET['province']);
                    $comuni = $dbClassInstance->get_comuni_by_provincia($province);

                    // Aggiungi il valore "Tutte" alla lista dei comuni

                    $meta_query = array(
                        'key' => 'citta',
                        'value' => $comuni, // Passa l'elenco di comuni come array
                        'compare' => 'IN'
                    );

                    $query->set('meta_query', $meta_query);
                }

                if (!empty($meta_query)) {
                    $query->set('meta_query', $meta_query);
                }
            }
        }


    }
}