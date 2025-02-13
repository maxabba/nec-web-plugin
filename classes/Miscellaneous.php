<?php

namespace Dokan_Mods;

use JetBrains\PhpStorm\NoReturn;
use WP_User;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . 'Miscellaneous')) {
    class Miscellaneous
    {
        //define constructor
        public function __construct()
        {


/*            add_action('init', function () {
                add_action('elementor/query/tutti_necrologi', [new FiltersClass(), 'customize_elementor_query']);
            }, 0);*/

            add_filter('elementor_pro/display_conditions/dynamic_tags/custom_fields_meta_limit', array($this, 'custom_custom_fields_meta_limit'));
            add_action('plugins_loaded', array($this, 'trasferisci_azione_dokan_seller'));

            //add_filter('acf/load_value/key=citta', array($this, 'auto_fill_acf_field_based_on_user'), 10, 3);
            add_filter('acf/load_field/key=field_6638e3e77ffa0', array($this, 'load_province_choices'));
            //add_filter('acf/load_field/name=city', array($this, 'load_city_choices'));

            add_filter('dokan_vendor_own_product_purchase_restriction', array($this, 'dokan_vendor_own_product_purchase_restriction'), 1, 2);

            //add_action('init', array($this, 'set_city_filter'));
            add_action('init', array($this, 'handle_filter_form_submission'));

            add_action('init', array($this, 'register_shortcodes'));
            add_action('pre_get_posts', array($this, 'apply_city_filter'),5,1);



            add_action('acf/init', array($this, 'add_acf_options_page'));
            add_filter('acf/load_value/name=fotografia', array($this, 'photo_placeholder'), 10, 3);

            add_action('acf/input/admin_enqueue_scripts', array($this, 'enqueue_ajax_script'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_ajax_script'));

            add_action('wp_ajax_get_comuni_by_provincia', array($this, 'get_comuni_by_provincia_callback'));
            add_action('wp_ajax_nopriv_get_comuni_by_provincia', array($this, 'get_comuni_by_provincia_callback'));

            add_action('wp_ajax_update_acf_field', array($this, 'handle_acf_update_request'));
            add_action('wp_ajax_nopriv_update_acf_field', array($this, 'handle_acf_update_request'));

            add_action('wp_ajax_get_current_citta_value_if_is_set', array($this, 'get_current_citta_value_if_is_set'));
            add_action('wp_ajax_nopriv_get_current_citta_value_if_is_set', array($this, 'get_current_citta_value_if_is_set'));

            add_action('dokan_order_detail_after_order_items', array($this, 'display_post_title_in_order_detail'), 10, 1);

            add_filter('acf/prepare_field/key=field_670d4e008fc23', array($this, 'hide_id_old'));
            add_filter('acf/prepare_field/key=field_671a68742fc07', array($this, 'hide_id_old'));


            add_action('dokan_seller_wizard_after_payment_setup_form', function ($setup_wizard) {
                ?>
                <div class="dokan-payment-setup-info"
                     style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-left: 4px solid #0073aa;">
                    <p style="margin-bottom: 15px;">
                        Per garantire una corretta e tempestiva erogazione dei pagamenti relativi alle vendite
                        effettuate sulla nostra piattaforma Necrologiweb, è fondamentale inserire le proprie coordinate
                        bancarie all'interno del proprio profilo Agenzia Funebre.
                    </p>

                    <p style="margin-bottom: 15px;">
                        Queste informazioni sono necessarie esclusivamente per permetterci di effettuare i bonifici a
                        suo favore in modo sicuro e trasparente.
                    </p>

                    <p style="margin-bottom: 15px;">
                        Senza di esse, non sarà possibile procedere con l'accredito degli importi maturati.
                    </p>

                    <p style="margin-bottom: 0;">
                        Per qualsiasi dubbio o necessità contattaci alla mail <a href="mailto:info@necrologiweb.it">info@necrologiweb.it</a>
                    </p>
                </div>
                <?php
            });


        }

        function custom_custom_fields_meta_limit($limit)
        {
            $new_limit = 100; // Change this to your desired limit
            return $new_limit;
        }

        function trasferisci_azione_dokan_seller()
        {
            // Rimuovi l'azione originale
            remove_action('woocommerce_register_form', 'dokan_seller_reg_form_fields');

            // Aggiungi l'azione all'inizio del form di registrazione
            add_action('woocommerce_register_form_start', 'dokan_seller_reg_form_fields');
        }

        public function dokan_vendor_own_product_purchase_restriction($is_purchasable, $product_id)
        {

            return true;
        }

        public function enqueue_select2_jquery()
        {
            wp_register_style('select2css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_register_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
            wp_enqueue_style('select2css');
            wp_enqueue_script('select2');

        }


        function enqueue_ajax_script()
        {
            wp_register_script('ajax-script-dokan-mod', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . '/assets/js/ajax-script.js', array('jquery'), '1.0', true);

            // Then enqueue the script
            wp_enqueue_script('ajax-script-dokan-mod');
            wp_localize_script('ajax-script-dokan-mod', 'ajax_object', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'post_id' => get_the_ID(),
            ));
        }

        public function add_acf_options_page()
        {
            acf_add_options_page([
                'page_title' => 'Global Site Settings',
                'menu_title' => 'Site Settings',
                'menu_slug' => 'site-settings',
                'capability' => 'manage_options',
                'position' => 30,
                'icon_url' => 'dashicons-admin-generic',
            ]);
        }

        public function register_shortcodes()
        {
            add_shortcode('show_acf_select_values', array($this, 'show_acf_select_values')); // Registra lo shortcode per mostrare i valori di un campo ACF select as [show_acf_select_values field_key="nome_campo"]
            add_shortcode('acf_gmaps_link', array($this, 'generate_google_maps_link')); // Registra lo shortcode per generare un link a Google Maps come [acf_gmaps_link acf_field="nome_campo"]
            add_shortcode('dokan_store_name_print', array($this, 'dokan_store_name_print')); // Registra lo shortcode per stampare il nome del negozio come [dokan_store_name_print]
            add_shortcode('show_selected_location', array($this, 'show_selected_location_shortcode'));

        }


        public function photo_placeholder($value, $post_id, $field)
        {
            if (!$value && $field['name'] == 'fotografia') {
                return '1464';
            }
            return $value;
        }

        public function generate_google_maps_link($atts)
        {
            // Estrai gli attributi passati allo shortcode
            $atts = shortcode_atts(array(
                'acf_field' => '',
                'placeholder_image' => DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/gmaps.webp',
            ), $atts, 'acf_gmaps_link');

            // Recupera l'indirizzo dal campo ACF
            $address = get_field($atts['acf_field']);

            // Se l'indirizzo è vuoto, restituisci un messaggio di errore
            if (empty($address)) {
                return '';
            }

            // Formatta l'indirizzo per l'URL di Google Maps
            $formatted_address = urlencode($address);
            $maps_url = "https://www.google.com/maps/search/?api=1&query={$formatted_address}";

            // Inizia l'output buffering
            ob_start();
            ?>
            <a href="<?php echo esc_url($maps_url); ?>" target="_blank">
                <img src="<?php echo esc_url($atts['placeholder_image']); ?>" width="100px"
                     alt="Mappa di <?php echo esc_attr($address); ?>">
            </a>
            <?php

            // Restituisce l'output bufferizzato
            return ob_get_clean();
        }

        public function dokan_store_name_print()
        {
            $autor_id = get_the_author_meta('ID');
            $store_info = dokan_get_store_info($autor_id);
            if ($store_info) {
                return $store_info['store_name'];
            }
            return '';
        }


        public function show_selected_location_shortcode($atts)
        {
            // Merge degli attributi di default con quelli forniti
            $atts = shortcode_atts(array(
                'default_text' => 'Seleziona Città',
                'template' => 'Annunci in {city},{province}', // {city} e {province} sono placeholder
                'all_cities_text' => 'Tutte le città', // testo da usare quando è selezionato "Tutte"
                'wrapper_class' => 'selected-location-text',
                'highlight_class' => 'location-highlight'
            ), $atts);

            $utilsAMClass = new UtilsAMClass();
            $city_key = $utilsAMClass->get_transient_key('city');
            $province_key = $utilsAMClass->get_transient_key('province');

            // Recupera i valori dai transient
            $city = get_transient($city_key);
            $province = get_transient($province_key);

            // Se non ci sono valori nei transient, ritorna il testo di default
            if (empty($city) && empty($province)) {
                return sprintf('<div class="%s">%s</div>',
                    esc_attr($atts['wrapper_class']),
                    esc_html($atts['default_text'])
                );
            }

            // Prepara il testo della città
            $city_text = $city === 'Tutte' ? $atts['all_cities_text'] : $city;

            // Prepara gli elementi del template
            $template_parts = [];

            // Aggiungi la parte della città se non vuota
            if ($city_text) {
                $template_parts[] = sprintf('<span class="%s">%s</span>',
                    esc_attr($atts['highlight_class']),
                    esc_html($city_text)
                );
            }

            // Aggiungi la parte della provincia se non vuota
            if ($province) {
                $template_parts[] = sprintf('<span class="%s">%s</span>',
                    esc_attr($atts['highlight_class']),
                    esc_html($province)
                );
            }

            // Costruisci il testo del template
            $location_text = str_replace(
                ['{city}', ',{province}'],
                [
                    $city_text ? $city_text : '',
                    $province ? ', ' . $province : ''
                ],
                $atts['template']
            );

            // Restituisci il testo formattato
            return sprintf('<div class="%s">%s</div>',
                esc_attr($atts['wrapper_class']),
                $location_text
            );
        }





/*        public function set_city_filter()
        {
            if (!empty($_POST['province'])) {
                // Imposta un transient per memorizzare la provincia scelta con una durata di 1 giorno
                set_transient('province' . session_id(), sanitize_text_field($_POST['province']), DAY_IN_SECONDS);
            } elseif (isset($_POST['province'])) {
                delete_transient('province' . session_id());
            }

            //check if the transient is set
            if (!empty($_POST['city_filter'])) {
                // Imposta un transient per memorizzare la città scelta con una durata di 1 giorno
                set_transient('city_filter_' . session_id(), sanitize_text_field($_POST['city_filter']), DAY_IN_SECONDS);
            } elseif (isset($_POST['province'])) {
                delete_transient('city_filter_' . session_id());
            }

        }*/


        public function apply_city_filter($query)
        {
            if (!is_admin() && $query->is_main_query()) {
                $utilsAMClass = new UtilsAMClass();
                $city_key = $utilsAMClass->get_transient_key('city');
                $province_key = $utilsAMClass->get_transient_key('province');

                $city_filter = get_transient($city_key);
                $province = get_transient($province_key);

                $meta_query = (new FiltersClass($city_filter, $province))->get_city_filter_meta_query();

                if (!empty($meta_query)) {
                    $query->set('meta_query', $meta_query);
                }
            }
        }


        public function load_province_choices($field)
        {
            global $dbClassInstance;

            $provinces = $dbClassInstance->get_all_Province();
            $field['choices'] = array();
            $field['choices']['Tutte'] = 'Tutte';
            foreach ($provinces as $province) {
                $field['choices'][$province['provincia_nome']] = $province['provincia_nome'];
            }

            return $field;
        }

        public function handle_acf_update_request()
        {
            $value_received = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : null;
            if (empty($value_received)) {
                echo json_encode([]);
                wp_die();
            }
            // Supponendo che tu debba elaborare $value_received per ottenere il nuovo valore
            global $dbClassInstance;

            $processed_value = $dbClassInstance->get_comuni_by_provincia($value_received); // Personalizza questa funzione
            //the processed_value is an array, print it to see the result
            echo json_encode($processed_value);
            wp_die(); // termina correttamente la richiesta AJAX
        }

        public function get_current_citta_value_if_is_set()
        {
            global $dbClassInstance;
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
            if (empty($post_id)) {
                echo json_encode([]);
                wp_die();
            }
            $city_filter = get_field('citta', $post_id);

            //if null check if user logged is a vendor
            if (empty($city_filter)) {
                $current_user = wp_get_current_user();
                if ($current_user instanceof WP_User) {

                    $store_info = dokan_get_store_info($current_user->ID);

                    if ($store_info) {
                        $user_city = $store_info['address']['city'] ?? '';
                        if (!empty($user_city)) {
                            $city_filter = $user_city;
                        }
                    }
                }
            }

            $province = $dbClassInstance->get_provincia_by_comune($city_filter);

            $response = [
                'city' => $city_filter,
                'province' => $province
            ];

            echo json_encode($response);
            wp_die();
        }

        public function auto_fill_acf_field_based_on_user($value, $post_id, $field)
        {
            // Assicurati che il campo sia vuoto prima di autocompilarlo
            if (empty($value) || $value === "Tutte") {
                $current_user = wp_get_current_user();
                if ($current_user instanceof WP_User) {

                    $store_info = dokan_get_store_info($current_user->ID);

                    if ($store_info) {
                        $user_city = $store_info['address']['city'] ?? '';
                        if (!empty($user_city)) {
                            $value = $user_city;
                        }
                    }
                }
            }
            return $value;
        }


        private function get_all_acf_select_values($field_key, $post_id = null)
        {

            $field = get_field_object($field_key, 'option', true);
            if ($field) {
                return $field['choices'];
            }
            return [];
        }


        public function show_acf_select_values($field)
        {
            global $dbClassInstance;
            $all_provinces = $dbClassInstance->get_all_Province();

            // Get current URL
            $current_url = remove_query_arg(['province', 'city_filter']); // Remove existing params

            $utilsAMClass = new UtilsAMClass();
            $city_key = $utilsAMClass->get_transient_key('city');
            $province_key = $utilsAMClass->get_transient_key('province');

            $city_filter = get_transient($city_key);
            $province_filter = get_transient($province_key);

            $random_id = uniqid();

            ob_start();
            ?>
            <div id="<?= $random_id ?>"
                 style="display: flex; flex-direction: column; align-items: center; width: 80%; margin: auto;">
                <form action="<?php echo esc_url($current_url); ?>" method="POST" style="width: 100%;">
                    <!-- Add hidden input for current page URL -->
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">

                    <label for="province" style="text-align: center;">Seleziona una provincia:</label>
                    <select class="select2" name="province" style="width: 100%;">
                        <option value="">Scegli una provincia...</option>
                        <?php foreach ($all_provinces as $province): ?>
                            <option value="<?= $province['provincia_nome'] ?>" <?= $province['provincia_nome'] == $province_filter ? 'selected' : '' ?>>
                                <?= $province['provincia_nome'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="city_filter" style="text-align: center;">Seleziona una città:</label>
                    <?php if ($province_filter): ?>
                        <select class="select2" name="city_filter" style="width: 100%;">
                            <option value="Tutte">Tutte</option>
                            <?php
                            $cities = $dbClassInstance->get_comuni_by_provincia($province_filter);
                            foreach ($cities as $city): ?>
                                <option value="<?= $city ?>" <?= $city == $city_filter ? 'selected' : '' ?>><?= $city ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <select class="select2" name="city_filter" style="width: 100%;" disabled>
                            <option value="">Scegli una città...</option>
                        </select>
                    <?php endif; ?>
                    <input type="submit" value="Cerca" style="width: 100%; margin-top: 10px;">
                </form>

                <!-- Reset form -->
                <form action="<?php echo esc_url($current_url); ?>" method="POST" style="width: 100%;">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">
                    <input type="hidden" name="province" value="">
                    <input type="hidden" name="city_filter" value="">
                    <input type="submit" value="Reset" style="width: 100%; margin-top: 10px;">
                </form>
            </div>
            <!-- Rest of JavaScript code remains the same -->
            <script>
                jQuery(document).ready(function ($) {
                    $("select[name='province']").change(function () {
                        var selectedProvince = $(this).val();
                        $.ajax({
                            url: ajax_object.ajax_url,
                            type: "POST",
                            data: {
                                action: "get_comuni_by_provincia",
                                province: selectedProvince
                            },
                            beforeSend: function () {
                                $("select[name='city_filter']").attr("disabled", true);
                                $("select[name='city_filter']").html('<option value="">Loading...</option>');
                            },
                            success: function (data) {
                                console.log(data);
                                var cities = JSON.parse(data);
                                var options = '<option value="Tutte">Tutte</option>';
                                for (var i = 0; i < cities.length; i++) {
                                    if (cities[i].nome == "<?= $city_filter ?>") {
                                        options += '<option value="' + cities[i] + '" selected>' + cities[i] + '</option>';
                                    } else {
                                        options += '<option value="' + cities[i] + '">' + cities[i] + '</option>';
                                    }
                                }
                                $("select[name='city_filter']").html(options);
                                $("select[name='city_filter']").attr("disabled", false);
                            }
                        });
                    });
                });
            </script>
            <?php
            return ob_get_clean();
        }

        public function handle_filter_form_submission()
        {
            // Skip if it's a login page request
            if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
                return;
            }

            if (isset($_POST['redirect_to'])) {
                $redirect_url = wp_validate_redirect($_POST['redirect_to'], home_url());

                $utilsAMClass = new UtilsAMClass();

                // Set transients using the new method
                if (isset($_POST['province'])) {
                    if (!empty($_POST['province'])) {
                        $province_key = $utilsAMClass->get_transient_key('province');
                        set_transient($province_key, sanitize_text_field($_POST['province']), WEEK_IN_SECONDS);
                    } else {
                        $province_key = $utilsAMClass->get_transient_key('province');
                        delete_transient($province_key);
                    }
                }

                if (isset($_POST['city_filter'])) {
                    if (!empty($_POST['city_filter'])) {
                        $city_key = $utilsAMClass->get_transient_key('city');
                        set_transient($city_key, sanitize_text_field($_POST['city_filter']), WEEK_IN_SECONDS);
                    } else {
                        $city_key = $utilsAMClass->get_transient_key('city');
                        delete_transient($city_key);
                    }
                }

                wp_safe_redirect($redirect_url);
                exit;
            }
        }

            #[NoReturn] function get_comuni_by_provincia_callback(): void
        {
            $province_name = $_POST['province'];
            if (empty($province_name)) {
                echo json_encode([]);
                wp_die();
            }
            global $dbClassInstance;
            $result = $dbClassInstance->get_comuni_by_provincia($province_name);
            echo json_encode($result);
            wp_die(); // this is required to terminate immediately and return a proper response
        }


        public function display_post_title_in_order_detail($order)
        {
            $order = wc_get_order($order);
            $items = $order->get_items();
            foreach ($items as $item) {
                $post_id = $item->get_meta('_post_id');
                if ($post_id) {
                    $post_title = get_the_title($post_id);
                    $manifesto_id = $item->get_meta('_manifesto_id');
                    //url di redirect per tornare all'ordine con wpnonce
                    $redirect_url = home_url('/dashboard/orders/?order_id=' . $order->get_id(). '&_wpnonce=' . wp_create_nonce('dokan_view_order'));

                    $manifesto_url = home_url('/dashboard/crea-manifesto/?post_id=' . $manifesto_id . '&post_id_annuncio=' . $post_id . '&redirect_to=' . urlencode($redirect_url));

                    ob_start();
                    ?>
                    <div class="dokan-panel dokan-panel-default">
                        <div class="dokan-panel-heading">
                            <strong>Informazioni Annuncio di morte collegato</strong>
                        </div>
                        <div class="dokan-panel-body" id="woocommerce-order-items">
                                <table class="dokan-table order-items">
                                    <thead>
                                    <tr>
                                        <th class="item" colspan="2"><?php esc_html_e('Annuncio', 'dokan-mod'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody id="order_items_list">
                                    <tr class="item">
                                        <td class="item-name" colspan="2">
                                                <a href="<?php echo get_permalink($post_id); ?>"><?php echo $post_title; ?></a>
                                        </td>
                                    </tbody>

                                </table>
                        </div>
                    </div>

                    <div class="dokan-panel dokan-panel-default">
                        <div class="dokan-panel-heading">
                            <strong>Informazioni Manifesto</strong>
                        </div>
                        <div class="dokan-panel-body" id="woocommerce-order-items">
                            <table class="dokan-table order-items">
                                <thead>
                                <tr>
                                    <th class="item" colspan="2"><?php esc_html_e('Manifesto', 'dokan-mod'); ?></th>
                                </tr>
                                </thead>
                                <tbody id="order_items_list">
                                <tr class="item">
                                    <td class="item-name" colspan="2">
                                        <a href="<?php echo esc_url($manifesto_url); ?>"
                                           class="custom-widget-button" style="margin-bottom: 15px">Visualizza/Modifica Manifesto</a>
                                    </td>
                                </tbody>

                            </table>

                        </div>
                    </div>

                    <?php
                    echo ob_get_clean();
                }
            }
        }


        public function hide_id_old($field)
        {
            if ($field['_name'] == 'id_old') {
                $field['wrapper']['style'] = 'display: none;';
            }
            return $field;
        }


    }
}