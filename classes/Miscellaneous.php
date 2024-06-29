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

            add_filter('elementor_pro/display_conditions/dynamic_tags/custom_fields_meta_limit', array($this, 'custom_custom_fields_meta_limit'));
            add_action('plugins_loaded', array($this, 'trasferisci_azione_dokan_seller'));

            //add_filter('acf/load_value/key=field_662ca58a35da3', array($this, 'auto_fill_acf_field_based_on_user'), 10, 3);
            add_filter('acf/load_field/key=field_6638e3e77ffa0', array($this, 'load_province_choices'));
            //add_filter('acf/load_field/name=city', array($this, 'load_city_choices'));

            add_filter('dokan_vendor_own_product_purchase_restriction', array($this, 'dokan_vendor_own_product_purchase_restriction'), 1, 2);

            add_action('init', array($this, 'set_city_filter'));
            add_action('pre_get_posts', array($this, 'apply_city_filter'));



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


            $this->register_shortcodes();
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


        public function set_city_filter()
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

        }

        public function apply_city_filter($query)
        {
            if (!is_admin() && $query->is_main_query()) {
                $city_filter = get_transient('city_filter_' . session_id()) ?? null;
                $province = get_transient('province' . session_id()) ?? null;
                if (!empty($province) || !empty($city_filter)) {
                    $meta_query = (new FiltersClass($city_filter, $province))->get_city_filter_meta_query();
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

            // Get all provinces
            $all_provinces = $dbClassInstance->get_all_Province();
            $city_filter = get_transient('city_filter_' . session_id());
            $province_filter = get_transient('province' . session_id());
            // Start output
            $random_id = uniqid();
            $output = '<div id="' . $random_id . '" style="display: flex; flex-direction: column; align-items: center; width: 80%; margin: auto;">';
            $output .= '<form action="" method="POST" style="width: 100%;">';
            $output .= '<label for="province" style="text-align: center;">Seleziona una provincia:</label>';

            // Province select input
            $output .= '<select class="select2" name="province" style="width: 100%;">';
            $output .= '<option value="">Scegli una provincia...</option>';
            foreach ($all_provinces as $province) {
                if ($province['provincia_nome'] == $province_filter) {
                    $output .= '<option value="' . $province['provincia_nome'] . '" selected>' . $province['provincia_nome'] . '</option>';
                } else {
                    $output .= '<option value="' . $province['provincia_nome'] . '">' . $province['provincia_nome'] . '</option>';
                }
            }
            $output .= '</select>';
            $output .= '<label for="city_filter" style="text-align: center;">Seleziona una città:</label>';

            // City select input

            if ($province_filter) {
                $output .= '<select class="select2" name="city_filter" style="width: 100%;" >';
                $cities = $dbClassInstance->get_comuni_by_provincia($province_filter);
                $output .= '<option value="Tutte">Tutte</option>';
                foreach ($cities as $city) {
                    if ($city == $city_filter) {
                        $output .= '<option value="' . $city . '" selected>' . $city . '</option>';
                    } else {
                        $output .= '<option value="' . $city . '">' . $city . '</option>';
                    }
                }
            } else {
                $output .= '<select class="select2" name="city_filter" style="width: 100%;" disabled>';
                $output .= '<option value="">Scegli una città...</option>';
            }
            $output .= '</select>';
            $output .= '<input type="submit" value="Cerca" style="width: 100%; margin-top: 10px;">';

            $output .= '</form>';
            $output .= '<form action="" method="POST" style="width: 100%;">';
            $output .= '<input type="hidden" name="province" value="">';
            $output .= '<input type="hidden" name="city_filter" value="">';
            $output .= '<input type="submit" value="Reset" style="width: 100%; margin-top: 10px;">';
            $output .= '</form>';
            $output .= '</div>';
            //add a reset form button

            // Add script to handle city select input population based on selected province
            $output .= '<script>
            jQuery(document).ready(function($) {
                $("select[name=\'province\']").change(function() {
                    var selectedProvince = $(this).val();
                    $.ajax({
                        url: ajax_object.ajax_url, // WordPress defines this variable for you, it points to /wp-admin/admin-ajax.php
                        type: "POST",
                        data: {
                            action: "get_comuni_by_provincia", // this is the part of the action hook name after wp_ajax_
                            province: selectedProvince
                        },
                        //on load add a loading spinner
                        beforeSend: function() {
                            $("select[name=\'city_filter\']").attr("disabled", true);
                            $("select[name=\'city_filter\']").html(\'<option value="">Loading...</option>\');
                        },
                        success: function(data) {
                            console.log(data);
                            var cities = JSON.parse(data);
                            var options = \'<option value="Tutte">Tutte</option>\';
                            for (var i = 0; i < cities.length; i++) {
                                if (cities[i].nome == "' . $city_filter . '") {
                                    options += \'<option value="\' + cities[i] + \'" selected>\' + cities[i] + \'</option>\';
                                } else {
                                options += \'<option value="\' + cities[i] + \'">\' + cities[i] + \'</option>\';
                                }
                            }
                            $("select[name=\'city_filter\']").html(options);
                            $("select[name=\'city_filter\']").attr("disabled", false);
                        }
                    });
                });
            });
            </script>';

            return $output;
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

    }
}