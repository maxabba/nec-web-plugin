<?php

namespace Dokan_Mods;

use SimpleXMLElement;
use WP_Error;
use WP_Query;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\DokanMappaturaLive')) {
    class DokanMappaturaLive {

        private string $option_name = 'mappatura_live';

        public function __construct() {
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('wp_ajax_get_comuni', [$this, 'ajax_get_comuni']);
            add_action('wp_ajax_save_mappatura', [$this, 'save_mappatura']);
            add_action('rest_api_init', [$this, 'register_api_route']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_select2']);

        }

        // Aggiungi la sottovoce di menu per la mappatura
        public function add_submenu() {
            add_submenu_page(
                'dokan-mod',
                'Mappatura Live.it',
                'Mappatura Live.it',
                'manage_options',
                'mappatura-live',
                [$this, 'render_mappatura_page']
            );
        }

        public function enqueue_select2($hook)
        {
            if ('dokan-mods_page_mappatura-live' !== $hook) {
                return;
            }

            // Controlla se Select2 è già disponibile su WordPress
            if (wp_script_is('select2', 'registered')) {
                wp_enqueue_script('select2');
                wp_enqueue_style('select2');
            } else {
                // Registra e carica Select2 se non è già disponibile in WordPress
                wp_register_script(
                    'select2',
                    'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
                    ['jquery'],
                    '4.0.13',
                    true
                );
                wp_register_style(
                    'select2',
                    'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
                    [],
                    '4.0.13'
                );
                wp_enqueue_script('select2');
                wp_enqueue_style('select2');
            }

            // Registra e localizza il file JavaScript personalizzato per il back-end
            wp_register_script('mappatura-live-admin', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/admin/mappatura-live.js', ['jquery', 'select2'], false, true);
            wp_localize_script('mappatura-live-admin', 'mappaturaLive', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('get_comuni')
            ]);
            wp_enqueue_script('mappatura-live-admin');
        }


        // Renderizza la pagina di mappatura con tabella e select con ricerca Ajax
        public function render_mappatura_page()
        {
            $saved_mappings = get_option($this->option_name, []);
            ?>
            <div class="wrap">
                <h1>Mappatura Live.it</h1>
                <p>Questa pagina ti permette di mappare i portal ID con i comuni per ottenere i necrologi.</p>
                <p>Ricorda di sostituire l'id portale di esempio "1234" con l'id corretto</p>
                <?php
                    $current_domain = home_url();
                    $example_portalid = 1234;
                    $necrologi_url = $current_domain . "/wp-json/mappatura-live/v1/necrologi?portalid=" . $example_portalid;
                    $ricorrenze_url = $current_domain . "/wp-json/mappatura-live/v1/ricorrenze?portalid=" . $example_portalid;
                    $ringraziamenti_url = $current_domain . "/wp-json/mappatura-live/v1/ringraziamenti?portalid=" . $example_portalid;
                ?>
                <p>
                    <label for="necrologi-url">URL Necrologi API:</label>
                    <input id="necrologi-url" type="text" value="<?php echo esc_attr($necrologi_url); ?>" readonly style="width: 100%;" onclick="this.select();" />
                </p>
                <p>
                    <label for="ricorrenze-url">URL Ricorrenze API:</label>
                    <input id="ricorrenze-url" type="text" value="<?php echo esc_attr($ricorrenze_url); ?>" readonly style="width: 100%;" onclick="this.select();" />
                </p>
                <p>
                    <label for="ricorrenze-url">URL Ringraziamenti API:</label>
                    <input id="ricorrenze-url" type="text" value="<?php echo esc_attr($ringraziamenti_url); ?>" readonly
                           style="width: 100%;" onclick="this.select();"/>
                </p>

                <p>Puoi copiare gli URL sopra per interrogare le API con il portal ID mappato.</p>
                <table class="form-table" id="mappatura-live-table">
                    <thead>
                    <tr>
                        <th>Portal ID</th>
                        <th>Comune</th>
                        <th>Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($saved_mappings)): ?>
                        <?php foreach ($saved_mappings as $mapping): ?>
                            <tr class="repeater-row">
                                <td><input type="text" name="portalid[]"
                                           value="<?php echo esc_attr($mapping['portal_id']); ?>"/></td>
                                <td>
                                    <select name="comune[]" class="ajax-comune-search" style="width: 100%;">
                                        <option value="<?php echo esc_attr($mapping['comune']); ?>"
                                                selected><?php echo esc_html($mapping['comune']); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <button class="button remove-row">Rimuovi</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="repeater-row">
                        <td><input type="text" name="portalid[]"/></td>
                        <td>
                            <select name="comune[]" class="ajax-comune-search" style="width: 100%;">
                                <option value="">Seleziona un comune...</option>
                            </select>
                        </td>
                        <td>
                            <button class="button remove-row">Rimuovi</button>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="mappatura-actions" style="margin-top: 20px;">
                    <button id="add-row" class="button">Aggiungi Riga</button>
                    <button id="save-mappatura" class="button button-primary" style="margin-left: 10px;">Salva
                        Mappatura
                    </button>
                </div>
            </div>

            <?php
        }

        // Funzione per ottenere i comuni con ricerca Ajax
        public function ajax_get_comuni() {
            global $dbClassInstance;

            $comuni = $dbClassInstance->get_comune_by_typing($_GET['search']);

            wp_send_json($comuni);
        }

        // Salva la mappatura utilizzando update_option
        public function save_mappatura() {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Non autorizzato', 403);
            }

            $mappings = isset($_POST['mappings']) ? $_POST['mappings'] : [];
            update_option($this->option_name, $mappings);

            wp_send_json_success();
        }

        // Registra la rotta API
        public function register_api_route()
        {
            register_rest_route('mappatura-live/v1', '/necrologi', [
                'methods' => 'GET',
                'callback' => [$this, 'get_necrologi_data'],
                'permission_callback' => '__return_true',
                'args' => [
                    'portalid' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ]
                ]
            ]);

            register_rest_route('mappatura-live/v1', '/ricorrenze', [
                'methods' => 'GET',
                'callback' => [$this, 'get_ricorrenze_data'],
                'permission_callback' => '__return_true',
                'args' => [
                    'portalid' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ]
                ]
            ]);


            register_rest_route('mappatura-live/v1', '/ringraziamenti', [
                'methods' => 'GET',
                'callback' => [$this, 'get_ringraziamenti_data'],
                'permission_callback' => '__return_true',
                'args' => [
                    'portalid' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ]
                ]
            ]);

        }


        // Callback della rotta API per generare l'XML dei necrologi
        public function get_necrologi_data($request)
        {
            $portal_id = $request->get_param('portalid');

            if (!$portal_id) {
                return new WP_Error('missing_portalid', 'Il parametro portalid è richiesto', ['status' => 400]);
            }

            // Ottieni la mappatura del comune per il portal_id
            $mappings = get_option($this->option_name, []);
            $comune = null;
            foreach ($mappings as $mapping) {
                if ($mapping['portal_id'] == $portal_id) {
                    $comune = $mapping['comune'];
                    break;
                }
            }

            if (!$comune) {
                return new WP_Error('no_mapping', 'Nessuna mappatura trovata per questo portal ID', ['status' => 404]);
            }

            // Ottieni i necrologi per il comune (simulazione di dati qui)
            $necrologi = $this->get_necrologi_by_comune($comune);
            $xml = new SimpleXMLElement('<ArrayOfNecrologiDto xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://schemas.datacontract.org/2004/07/Necrologi.Api.Models"/>');

            foreach ($necrologi as $necrologio) {
                $necrologiDto = $xml->addChild('NecrologiDto');
                $necrologiDto->addChild('Agenzia', htmlspecialchars($necrologio['agenzia']));
                $necrologiDto->addChild('DataMorte', htmlspecialchars($necrologio['data_morte']));
                $necrologiDto->addChild('Foto', htmlspecialchars($necrologio['foto']));
                $necrologiDto->addChild('Id', htmlspecialchars($necrologio['id']));
                $necrologiDto->addChild('Link', htmlspecialchars($necrologio['link']));
                $necrologiDto->addChild('LogoAgenzia', htmlspecialchars($necrologio['logo_agenzia']));
                $necrologiDto->addChild('LogoAgenziaWeb', htmlspecialchars($necrologio['logo_agenzia_web']));
                $necrologiDto->addChild('Manifesto', htmlspecialchars($necrologio['manifesto']));
                $necrologiDto->addChild('NomeDefunto', htmlspecialchars($necrologio['nome_defunto']));
                $necrologiDto->addChild('TotaleManifesti', htmlspecialchars($necrologio['totale_manifesti']));
            }

            // Imposta l'header XML e restituisci il contenuto XML
            header('Content-Type: application/xml; charset=utf-8');
            echo $xml->asXML();
            exit; // Termina l'esecuzione per evitare output extra di WordPress
        }


        public function get_ricorrenze_data($request)
        {
            $portal_id = $request->get_param('portalid');

            if (!$portal_id) {
                return new WP_Error('missing_portalid', 'Il parametro portalid è richiesto', ['status' => 400]);
            }

            // Ottieni la mappatura del comune per il portal_id
            $mappings = get_option($this->option_name, []);
            $comune = null;
            foreach ($mappings as $mapping) {
                if ($mapping['portal_id'] == $portal_id) {
                    $comune = $mapping['comune'];
                    break;
                }
            }

            if (!$comune) {
                return new WP_Error('no_mapping', 'Nessuna mappatura trovata per questo portal ID', ['status' => 404]);
            }

            // Ottieni i necrologi per il comune (simulazione di dati qui)
            $ricorrenze = $this->get_ricorrenze_by_comune($comune);
            $xml = new SimpleXMLElement('<ArrayOfRicorrenzeDto xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://schemas.datacontract.org/2004/07/Necrologi.Api.Models"/>');

            foreach ($ricorrenze as $ricorrenza) {
                $necrologiDto = $xml->addChild('RicorrenzeDto');
                $necrologiDto->addChild('Anni', htmlspecialchars(''));
                $necrologiDto->addChild('Data', htmlspecialchars($ricorrenza['data']));
                $necrologiDto->addChild('Foto', htmlspecialchars($ricorrenza['foto']));
                $necrologiDto->addChild('Id', htmlspecialchars($ricorrenza['id']));
                $necrologiDto->addChild('IdNecrologio', htmlspecialchars($ricorrenza['id_necrologio']));
                $necrologiDto->addChild('Link', htmlspecialchars($ricorrenza['link']));
                $necrologiDto->addChild('LogoAgenzia', htmlspecialchars($ricorrenza['logo_agenzia']));
                $necrologiDto->addChild('LogoAgenziaWeb', htmlspecialchars($ricorrenza['logo_agenzia_web']));
                $necrologiDto->addChild('NomeDefunto', htmlspecialchars($ricorrenza['nome_defunto']));
                $necrologiDto->addChild('TipoRicorrenze', htmlspecialchars($ricorrenza['tipo_ricorrenze']));
            }


            // Imposta l'header XML e restituisci il contenuto XML
            header('Content-Type: application/xml; charset=utf-8');
            echo $xml->asXML();
            exit; // Termina l'esecuzione per evitare output extra di WordPress
        }


        public function get_ringraziamenti_data($request)
        {
            $portal_id = $request->get_param('portalid');

            if (!$portal_id) {
                return new WP_Error('missing_portalid', 'Il parametro portalid è richiesto', ['status' => 400]);
            }

            // Ottieni la mappatura del comune per il portal_id
            $mappings = get_option($this->option_name, []);
            $comune = null;
            foreach ($mappings as $mapping) {
                if ($mapping['portal_id'] == $portal_id) {
                    $comune = $mapping['comune'];
                    break;
                }
            }

            if (!$comune) {
                return new WP_Error('no_mapping', 'Nessuna mappatura trovata per questo portal ID', ['status' => 404]);
            }

            // Ottieni i necrologi per il comune (simulazione di dati qui)
            $ricorrenze = $this->get_ringraziamenti_by_comune($comune);
            $xml = new SimpleXMLElement('<ArrayOfRingraziamentiDto xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://schemas.datacontract.org/2004/07/Necrologi.Api.Models"/>');

            foreach ($ricorrenze as $ricorrenza) {
                $necrologiDto = $xml->addChild('RingraziamentiDto');
                $necrologiDto->addChild('Data', htmlspecialchars($ricorrenza['data']));
                $necrologiDto->addChild('Foto', htmlspecialchars($ricorrenza['foto']));
                $necrologiDto->addChild('Id', htmlspecialchars($ricorrenza['id']));
                $necrologiDto->addChild('IdNecrologio', htmlspecialchars($ricorrenza['id_necrologio']));
                $necrologiDto->addChild('Link', htmlspecialchars($ricorrenza['link']));
                $necrologiDto->addChild('LogoAgenzia', htmlspecialchars($ricorrenza['logo_agenzia']));
                $necrologiDto->addChild('LogoAgenziaWeb', htmlspecialchars($ricorrenza['logo_agenzia_web']));
                $necrologiDto->addChild('NomeDefunto', htmlspecialchars($ricorrenza['nome_defunto']));
            }

            // Imposta l'header XML e restituisci il contenuto XML
            header('Content-Type: application/xml; charset=utf-8');
            echo $xml->asXML();
            exit; // Termina l'esecuzione per evitare output extra di WordPress
        }

        // Esempio di funzione per ottenere necrologi per comune (simulata)
        private function get_necrologi_by_comune($comune) {


            //get all annunctio-di-morte with metakey citta and value $comune publicated and limit 20

            $args = array(
                'post_type' => 'annuncio-di-morte',
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'meta_query' => array(
                    array(
                        'key' => 'citta',
                        'value' => $comune,
                        'compare' => '='
                    )
                )
            );


            $necrologi = get_posts($args);

            $necrologi_data = [];

            foreach ($necrologi as $necrologio) {
                //use the inverse of             update_user_meta($user_id, 'dokan_store_name', $store_data['shopname']); starting from the post author id
                $agenzia = get_user_meta($necrologio->post_author, 'dokan_store_name', true);


                $vendor = dokan()->vendor->get($necrologio->post_author);
                $banner = $vendor->get_banner();
                $banner_url = $banner ? $banner : 'https://via.placeholder.com/150';

                //get_field('nome', $necrologio->ID) . ' ' . get_field('cognome', $necrologio->ID) . ' di anni ' . get_field('eta', $necrologio->ID)
                //genera tutte le possibile varianti per mancanza di dati con if
                if(get_field('nome', $necrologio->ID) && get_field('cognome', $necrologio->ID) && get_field('eta', $necrologio->ID)){
                    $nome_defunto = get_field('nome', $necrologio->ID) . ' ' . get_field('cognome', $necrologio->ID) . ' di anni ' . get_field('eta', $necrologio->ID);
                }elseif(get_field('nome', $necrologio->ID) && get_field('cognome', $necrologio->ID)){
                    $nome_defunto = get_field('nome', $necrologio->ID) . ' ' . get_field('cognome', $necrologio->ID);
                }elseif(get_field('nome', $necrologio->ID)){
                    $nome_defunto = get_field('nome', $necrologio->ID);
                }elseif(get_field('cognome', $necrologio->ID)){
                    $nome_defunto = get_field('cognome', $necrologio->ID);
                }else{
                    $nome_defunto = 'Defunto';
                }


                //get count of all post type manifesto with metakey annuncio_di_morte_relativo and value $necrologio->ID
                //get count of all post type manifesto with metakey annuncio_di_morte_relativo and value $necrologio->ID
                $count_query = new WP_Query(array(
                    'post_type' => 'manifesto',
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'meta_query' => array(
                        array(
                            'key' => 'annuncio_di_morte_relativo',
                            'value' => $necrologio->ID,
                            'compare' => '='
                        )
                    )
                ));

                $totale_manifesti = $count_query->found_posts;

                $necrologi_data[] = [
                    'agenzia' => $agenzia,
                    'data_morte' => get_field('data_di_morte', $necrologio->ID),
                    'foto' => get_field('fotografia', $necrologio->ID)['url'],
                    'id' => $necrologio->ID,
                    'link' => get_permalink($necrologio->ID),
                    'logo_agenzia' => $banner_url,
                    'logo_agenzia_web' => $banner_url,
                    'manifesto' => get_field('immagine_annuncio_di_morte', $necrologio->ID)['url'],
                    'nome_defunto' => $nome_defunto,
                    'totale_manifesti' => $totale_manifesti
                ];
            }

            return $necrologi_data;
        }


        private function get_ricorrenze_by_comune($comune)
        {


            //get all annunctio-di-morte with metakey citta and value $comune publicated and limit 20

            $args = array(
                'post_type' => array('anniversario','trigesimo'),
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'orderby' => 'date',
                'meta_query' => array(
                    array(
                        'key' => 'citta',
                        'value' => $comune,
                        'compare' => '='
                    )
                )
            );


            $ricorrenze = get_posts($args);

            $ricorrenze_data = [];

            foreach ($ricorrenze as $ricorrenza) {
                //use the inverse of             update_user_meta($user_id, 'dokan_store_name', $store_data['shopname']); starting from the post author id
                $agenzia = get_user_meta($ricorrenza->post_author, 'dokan_store_name', true);


                $vendor = dokan()->vendor->get($ricorrenza->post_author);
                $banner = $vendor->get_banner();
                $banner_url = $banner ? $banner : 'https://via.placeholder.com/150';


                $annuncio_relativo = get_field('annuncio_di_morte', $ricorrenza->ID);



                //get_field('nome', $ricorrenza->ID) . ' ' . get_field('cognome', $ricorrenza->ID) . ' di anni ' . get_field('eta', $ricorrenza->ID)
                //genera tutte le possibile varianti per mancanza di dati con if
                if (get_field('nome', $annuncio_relativo) && get_field('cognome', $annuncio_relativo) && get_field('eta', $annuncio_relativo)) {
                    $nome_defunto = get_field('nome', $annuncio_relativo) . ' ' . get_field('cognome', $annuncio_relativo) . ' di anni ' . get_field('eta', $annuncio_relativo);
                } elseif (get_field('nome', $annuncio_relativo) && get_field('cognome', $annuncio_relativo)) {
                    $nome_defunto = get_field('nome', $annuncio_relativo) . ' ' . get_field('cognome', $annuncio_relativo);
                } elseif (get_field('nome', $annuncio_relativo)) {
                    $nome_defunto = get_field('nome', $annuncio_relativo);
                } elseif (get_field('cognome', $annuncio_relativo)) {
                    $nome_defunto = get_field('cognome', $annuncio_relativo);
                } else {
                    $nome_defunto = 'Defunto';
                }


                if ($ricorrenza->post_type == 'anniversario') {

                    $anno_anniversario = get_field('anniversario_n_anniversario', $ricorrenza->ID);

                    $tipo_ricorrenza = $anno_anniversario .'° ANNIVERSARIO';

                    $data = get_field('anniversario_data', $ricorrenza->ID);
                }else
                {
                    $tipo_ricorrenza = 'TRIGESIMO';

                    $data = get_field('trigesimo_data', $ricorrenza->ID);
                }




                $ricorrenze_data[] = [
                    'agenzia' => $agenzia,
                    'data' => $data,
                    'foto' => get_field('fotografia', $annuncio_relativo)['url'],
                    'id' => $ricorrenza->ID,
                    'id_necrologio' => $annuncio_relativo,
                    'link' => get_permalink($ricorrenza->ID),
                    'logo_agenzia' => $banner_url,
                    'logo_agenzia_web' => $banner_url,
                    'nome_defunto' => $nome_defunto,
                    'tipo_ricorrenze' => $tipo_ricorrenza,
                ];
            }

            return $ricorrenze_data;
        }


        private function get_ringraziamenti_by_comune($comune)
        {


            //get all annunctio-di-morte with metakey citta and value $comune publicated and limit 20

            $args = array(
                'post_type' => 'ringraziamento',
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'orderby' => 'date',
                'meta_query' => array(
                    array(
                        'key' => 'citta',
                        'value' => $comune,
                        'compare' => '='
                    )
                )
            );


            $ringraziamenti = get_posts($args);

            $ricorrenze_data = [];

            foreach ($ringraziamenti as $ringraziamento) {
                //use the inverse of             update_user_meta($user_id, 'dokan_store_name', $store_data['shopname']); starting from the post author id
                $agenzia = get_user_meta($ringraziamento->post_author, 'dokan_store_name', true);


                $vendor = dokan()->vendor->get($ringraziamento->post_author);
                $banner = $vendor->get_banner();
                $banner_url = $banner ? $banner : 'https://via.placeholder.com/150';


                $annuncio_relativo = get_field('annuncio_di_morte', $ringraziamento->ID);


                //get_field('nome', $ringraziamento->ID) . ' ' . get_field('cognome', $ringraziamento->ID) . ' di anni ' . get_field('eta', $ringraziamento->ID)
                //genera tutte le possibile varianti per mancanza di dati con if
                if (get_field('nome', $annuncio_relativo) && get_field('cognome', $annuncio_relativo) && get_field('eta', $annuncio_relativo)) {
                    $nome_defunto = get_field('nome', $annuncio_relativo) . ' ' . get_field('cognome', $annuncio_relativo) . ' di anni ' . get_field('eta', $annuncio_relativo);
                } elseif (get_field('nome', $annuncio_relativo) && get_field('cognome', $annuncio_relativo)) {
                    $nome_defunto = get_field('nome', $annuncio_relativo) . ' ' . get_field('cognome', $annuncio_relativo);
                } elseif (get_field('nome', $annuncio_relativo)) {
                    $nome_defunto = get_field('nome', $annuncio_relativo);
                } elseif (get_field('cognome', $annuncio_relativo)) {
                    $nome_defunto = get_field('cognome', $annuncio_relativo);
                } else {
                    $nome_defunto = 'Defunto';
                }


                $ricorrenze_data[] = [
                    'data' => get_the_date('l, d F Y', $ringraziamento->ID),
                    'foto' => get_field('fotografia', $annuncio_relativo)['url'],
                    'id' => $ringraziamento->ID,
                    'id_necrologio' => $annuncio_relativo,
                    'link' => get_permalink($ringraziamento->ID),
                    'logo_agenzia' => $banner_url,
                    'logo_agenzia_web' => $banner_url,
                    'nome_defunto' => $nome_defunto,
                ];
            }

            return $ricorrenze_data;
        }


    }
}