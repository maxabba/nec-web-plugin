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

/*        public function __construct($city = null, $province = null)
        {
            $class_name = 'CloudFlareGeo';

            if (empty(get_option('dokan_mods_deactivate_' . strtolower($class_name)))) {
                $this->geolocator = new CloudFlareGeo();
            }

            // Se è presente un filtro GET per la provincia, non applicare la geolocalizzazione o i transient
            if (!empty($_GET['province'])) {
                $this->city = null;
                $this->province = null;
                return;
            }

            // Se sono forniti città e provincia come parametri, usali
            if ($city !== null || $province !== null) {
                $this->city = $city;
                if ($province == null) {
                    global $dbClassInstance;
                    $this->province = $dbClassInstance->get_provincia_by_comune($city);
                } else {
                    $this->province = $province;
                }

                if ($this->city == 'Tutte') {
                    global $dbClassInstance;
                    $this->city = $dbClassInstance->get_comuni_by_provincia($province);
                }
            } // Altrimenti prova la geolocalizzazione
            else {
                $location = $this->get_geolocation();
                $this->city = $location['city'] ?? null;
                $this->province = $location['province'] ?? null;
            }
        }*/


        public function __construct($city = null, $province = null)
        {
            // Inizializza il geolocator se abilitato
            $class_name = 'CloudFlareGeo';
            if (empty(get_option('dokan_mods_deactivate_' . strtolower($class_name)))) {
                $this->geolocator = new CloudFlareGeo();
            }

            // Se sono forniti parametri espliciti, usali
            if ($city !== null || $province !== null) {
                $this->setLocation($city, $province);
                return;
            }

            // Altrimenti, cerca una localizzazione esistente o usa la geolocalizzazione
            $this->initializeLocation();
        }


        private function get_transient_key($type)
        {
            return (new UtilsAMClass())->get_transient_key($type);
        }

        private function setLocation($city, $province)
        {
            $this->city = $city;

            if ($province === null && $city !== null) {
                global $dbClassInstance;
                $this->province = $dbClassInstance->get_provincia_by_comune($city);
            } else {
                $this->province = $province;
            }

            if ($this->city === 'Tutte' && $this->province) {
                global $dbClassInstance;
                $this->city = $dbClassInstance->get_comuni_by_provincia($this->province);
            }
        }

        private function initializeLocation()
        {
            // Prova prima a recuperare dai transient
            $city = get_transient($this->get_transient_key('city'));
            $province = get_transient($this->get_transient_key('province'));

            if ($city || $province) {
                $this->setLocation($city, $province);
                return;
            }

            // Se non ci sono transient e il geolocator è disponibile, usa la geolocalizzazione
            if ($this->geolocator) {
                $location = $this->geolocator->get_location();
                if (!isset($location['error']) && $location['city'] !== 'N/A') {
                    $this->setLocation($location['city'], null);

                    // Salva in transient
                    set_transient($this->get_transient_key('city'), $this->city, WEEK_IN_SECONDS);
                    set_transient($this->get_transient_key('province'), $this->province, WEEK_IN_SECONDS);
                }
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
            if ($this->geolocator) {
                $location = $this->geolocator->get_location();
                if (!isset($location['error'])) {

                    if ($location['city'] == 'N/A') {
                        return ['city' => null, 'province' => null];
                    }

                    global $dbClassInstance;
                    $city = $location['city'];
                    $province = $dbClassInstance->get_provincia_by_comune($city);

                    // Salva temporaneamente la localizzazione automatica
                    set_transient($this->get_transient_key('city'), $city, DAY_IN_SECONDS);
                    set_transient($this->get_transient_key('province'), $province, DAY_IN_SECONDS);

                    return [
                        'city' => $city,
                        'province' => $province
                    ];
                }
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


/*        public function get_city_filter_meta_query()
        {
            $city_filter = $this->city;
            $province_filter = $this->province;

            // Base meta query array
            $meta_query = array('relation' => 'OR');

            // Se abbiamo una città specifica
            if ($city_filter && $city_filter !== 'Tutte') {
                if (is_array($city_filter)) {
                    $meta_query[] = array(
                        'key' => 'citta',
                        'value' => $city_filter,
                        'compare' => 'IN'
                    );
                } else {
                    $meta_query[] = array(
                        'key' => 'citta',
                        'value' => $city_filter,
                        'compare' => '='
                    );
                }
            }

            // Se abbiamo una provincia o la città è impostata su "Tutte"
            if ($province_filter || ($city_filter === 'Tutte' && $province_filter)) {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'provincia',
                        'value' => $province_filter,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'citta',
                        'value' => 'Tutte',
                        'compare' => '='
                    )
                );
            }

            // Se non abbiamo né città né provincia, mostra tutti i post
            if (empty($city_filter) && empty($province_filter)) {
                $meta_query = array(); // Reset meta query to show all posts
            }

            return $meta_query;
        }*/


        public function get_city_filter_meta_query()
        {
            $city_filter = $this->city;
            $province_filter = $this->province;

            if (empty($city_filter) && empty($province_filter)) {
                return array();
            }

            $meta_query = array('relation' => 'OR');
            if(is_array($city_filter)){
                if ($province_filter) {
                    $meta_query[] = array(
                        'key' => 'provincia',
                        'value' => $province_filter,
                        'compare' => '='
                    );
                }
                $meta_query[] = array(
                    'key' => 'citta',
                    'value' => $city_filter,
                    'compare' => 'IN'
                );
            }else{
                if ($city_filter && $city_filter !== 'Tutte') {
                    $meta_query[] = array(
                        'key' => 'citta',
                        'value' =>  $city_filter,
                        'compare' => '='
                    );
                }
            }

            // Aggiungi sempre la condizione per "Tutte"
            $meta_query[] = array(
                'key' => 'citta',
                'value' => 'Tutte',
                'compare' => '='
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


        /*function custom_filter_query($query)
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
        }*/
        function custom_filter_query($query)
        {
            // Verifica che non siamo nella dashboard e che sia la query principale
            if (!is_admin()) {

                // Estrai i filtri da GET
                $province = !empty($_GET['province']) ? sanitize_text_field($_GET['province']) : null;
                $city = !empty($_GET['city_filter']) ? sanitize_text_field($_GET['city_filter']) : null;

                if($city == null && !empty($province))
                {
                    global $dbClassInstance;
                    $city = $dbClassInstance->get_comuni_by_provincia($province);
                }


                // Se almeno uno dei filtri è presente, costruisci la meta_query
                if (!empty($city) || !empty($province)) {
                    $meta_query = ['relation' => 'OR'];

                    if (is_array($city)) {
                        if (!empty($province)) {
                            $meta_query[] = [
                                'key' => 'provincia',
                                'value' => $province,
                                'compare' => '='
                            ];
                        }
                        $meta_query[] = [
                            'key' => 'citta',
                            'value' => $city,
                            'compare' => 'IN'
                        ];
                    } elseif (!empty($city) && $city !== 'Tutte') {
                        $meta_query[] = [
                            'key' => 'citta',
                            'value' => $city,
                            'compare' => '='
                        ];
                    }

                    // Aggiungi sempre la condizione per "Tutte"
                    $meta_query[] = [
                        'key' => 'citta',
                        'value' => 'Tutte',
                        'compare' => '='
                    ];

                    $query->set('meta_query', $meta_query);
                }

                // Filtra per periodo (invariato)
                if (isset($_GET['date_filter']) && $_GET['date_filter'] !== 'all') {
                    $date_filter = $_GET['date_filter'];
                    $date_query = [];

                    switch ($date_filter) {
                        case 'today':
                            $date_query = [
                                [
                                    'after' => date('Y-m-d', strtotime('today')),
                                    'inclusive' => true,
                                ],
                            ];
                            break;
                        case 'yesterday':
                            $date_query = [
                                [
                                    'after' => date('Y-m-d', strtotime('yesterday')),
                                    'before' => date('Y-m-d', strtotime('today')),
                                    'inclusive' => true,
                                ],
                            ];
                            break;
                        case 'last_week':
                            $date_query = [
                                [
                                    'after' => date('Y-m-d', strtotime('-1 week')),
                                    'inclusive' => true,
                                ],
                            ];
                            break;
                        case 'last_month':
                            $date_query = [
                                [
                                    'after' => date('Y-m-d', strtotime('-1 month')),
                                    'inclusive' => true,
                                ],
                            ];
                            break;
                    }

                    $query->set('date_query', $date_query);
                }
            }
        }
    }
}