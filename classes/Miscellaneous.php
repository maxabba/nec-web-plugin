<?php
namespace Dokan_Mods;
use JetBrains\PhpStorm\NoReturn;
use WP_User;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__.'Miscellaneous')) {
    class Miscellaneous
    {
        //define constructor
        public function __construct()
        {
            add_filter('acf/load_value/key=citta', array($this, 'auto_fill_acf_field_based_on_user'), 10, 3);
            add_action('init', array($this, 'set_city_filter'));
            add_action('pre_get_posts', array($this, 'apply_city_filter'));
            add_action('acf/init', array($this, 'add_acf_options_page'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_select2_jquery'));
            add_action('wp_ajax_get_comuni_by_provincia', array($this,'get_comuni_by_provincia_callback'));
            add_action('wp_ajax_nopriv_get_comuni_by_provincia', array($this,'get_comuni_by_provincia_callback'));


            $this->register_shortcodes();
        }

        public function enqueue_select2_jquery()
        {
            wp_register_style('select2css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_register_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
            wp_enqueue_style('select2css');
            wp_enqueue_script('select2');
            $script_data_array = array(
                'ajaxurl' => admin_url('admin-ajax.php'),
            );
            wp_localize_script('select2', 'my_ajax_object', $script_data_array);
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
        }

        public function set_city_filter()
        {
            if (isset($_POST['province']) && isset($_POST['city_filter']) && $_POST['city_filter'] == "Tutte") {
                // Imposta un transient per memorizzare la provincia scelta con una durata di 1 giorno
                set_transient('province' . session_id(), sanitize_text_field($_POST['province']), DAY_IN_SECONDS);
                return;
            }

            //check if the transient is set
            if (isset($_POST['city_filter'])) {
                // Imposta un transient per memorizzare la città scelta con una durata di 1 giorno
                set_transient('city_filter_' . session_id(), sanitize_text_field($_POST['city_filter']), DAY_IN_SECONDS);
            }

        }

        public function apply_city_filter($query)
        {
            if (!is_admin() && $query->is_main_query()) {
                $city_filter = get_transient('city_filter_' . session_id());
                $province = get_transient('province' . session_id());
                if (!empty($province)) {
                    $meta_query = (new FiltersClass(null, $province))->get_city_filter_meta_query();
                    $query->set('meta_query', $meta_query);
                }
                if (!empty($city_filter)) {
                    $meta_query = (new FiltersClass($city_filter))->get_city_filter_meta_query();
                    $query->set('meta_query', $meta_query);
                }
            }
        }


        private function auto_fill_acf_field_based_on_user($value, $post_id, $field)
        {
            // Assicurati che il campo sia vuoto prima di autocompilarlo
            if (empty($value)) {
                $current_user = wp_get_current_user();
                if ($current_user instanceof WP_User) {
                    //select the city based on the user city
                    $city = get_user_meta($current_user->ID, 'city', true);
                    if (!empty($city)) {
                        $value = $city;
                    }
                }
            }
            return $value;
        }


        private function get_all_acf_select_values($field_key, $post_id = null)
        {

            $field = get_field_object($field_key, 'option',true);
            if ($field) {
                return $field['choices'];
            }
            return [];
        }


/*        public function show_acf_select_values($atts): string
        {
            $atts = shortcode_atts([
                'field_key' => '',
                'post_id' => ''
            ], $atts);
            //get transients if set
            $city_filter = get_transient('city_filter_' . session_id());
            $values = $this->get_all_acf_select_values($atts['field_key'], $atts['post_id']);
            if (!empty($values)) {
                //return select input with options
                $random_id = uniqid();
                $output = '<div id="'. $random_id .'" style="display: flex; flex-direction: column; align-items: center; width: 80%; margin: auto;">';
                $output .= '<form action="" method="POST" style="width: 100%;">';
                $output .= '<label for="city_filter" style="text-align: center;">Seleziona una città:</label>';
                $output .= '<select name="city_filter" id="city_filter" class="select2" style="width: 100%;">';
                $output .= '<option value="">Scegli una città...</option>';
                foreach ($values as $value) {
                    if ($city_filter == $value) {
                        $output .= '<option value="' . $value . '" selected>' . $value . '</option>';
                    } else {
                        $output .= '<option value="' . $value . '">' . $value . '</option>';
                    }
                }
                $output .= '</select>';
                //add submit button
                $output .= '<input type="submit" value="Cerca" style="width: 100%; margin-top: 10px;">';
                $output .= '</form>';
                $output .= '</div>';
                $output .= '<script>
                            jQuery(document).ready(function($) {
                            //select the div with the random id and apply select2 to the select input
                            if ($.fn.elementorSelect2) {
                                $("#' . $random_id . ' .select2").elementorSelect2();
                            }
                            });
                </script>';
                return $output;

            }
            return 'No values found';
        }*/

        public function show_acf_select_values($field)
        {
            global $dbClassInstance;

            // Get all provinces
            $all_provinces = $dbClassInstance->get_all_Province();

            // Start output
            $random_id = uniqid();
            $output = '<div id="' . $random_id . '" style="display: flex; flex-direction: column; align-items: center; width: 80%; margin: auto;">';
            $output .= '<form action="" method="POST" style="width: 100%;">';
            $output .= '<label for="province" style="text-align: center;">Seleziona una provincia:</label>';

            // Province select input
            $output .= '<select class="select2" name="province" style="width: 100%;">';
            $output .= '<option value="">Scegli una provincia...</option>';
            foreach ($all_provinces as $province) {
                $output .= '<option value="' . $province['provincia_nome'] . '">' . $province['provincia_nome'] . '</option>';
            }
            $output .= '</select>';
            $output .= '<label for="city_filter" style="text-align: center;">Seleziona una città:</label>';

            // City select input
            $output .= '<select class="select2" name="city_filter" style="width: 100%;">';
            $output .= '<option value="">Scegli una città...</option>';
            $output .= '</select>';

            $output .= '</form>';
            $output .= '</div>';

            // Add script to handle city select input population based on selected province
            $output .= '<script>
            jQuery(document).ready(function($) {
                $("select[name=\'province\']").change(function() {
                    var selectedProvince = $(this).val();
                    console.log(my_ajax_object.ajaxurl);
                    $.ajax({
                        url: my_ajax_object.ajaxurl, // WordPress defines this variable for you, it points to /wp-admin/admin-ajax.php
                        type: "POST",
                        data: {
                            action: "get_comuni_by_provincia", // this is the part of the action hook name after wp_ajax_
                            province: selectedProvince
                        },
                        //on load add a loading spinner
                        beforeSend: function() {
                            $("select[name=\'city_filter\']").html(\'<option value="">Loading...</option>\');
                        },
                        success: function(data) {
                            var cities = JSON.parse(data);
                            var options = \'<option value="Tutte">Tutte</option>\';
                            for (var i = 0; i < cities.length; i++) {
                                options += \'<option value="\' + cities[i].nome + \'">\' + cities[i].nome + \'</option>\';
                            }
                            $("select[name=\'city_filter\']").html(options);
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
            global $dbClassInstance;
            $result = $dbClassInstance->get_comuni_by_provincia($province_name);
            echo json_encode($result);
            wp_die(); // this is required to terminate immediately and return a proper response
        }

    }
}