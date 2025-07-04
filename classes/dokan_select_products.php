<?php
/*
 * La classe Dokan_Select_Products è una classe personalizzata utilizzata nel plugin Dokan per gestire la selezione dei prodotti da parte dei venditori . Ecco una descrizione dettagliata dei suoi metodi:
 * __construct(): Questo è il costruttore della classe . Viene chiamato automaticamente quando si crea un'istanza della classe. Aggiunge diverse azioni e filtri di WordPress, tra cui l'aggiunta di un menu alla dashboard, l'aggiunta di regole di riscrittura, l'aggiunta di variabili di query, il caricamento del template e la gestione dell'invio del modulo.
 * enqueue_styles(): Questo metodo viene utilizzato per mettere in coda gli stili necessari quando la variabile di query 'seleziona - prodotti' è impostata.
 * add_dashboard_menu($urls): Questo metodo aggiunge un nuovo elemento al menu della dashboard di Dokan.
 * add_rewrite_rules(): Questo metodo aggiunge una nuova regola di riscrittura per gestire le richieste alla pagina 'seleziona - prodotti'.
 * add_query_vars($vars): Questo metodo aggiunge la variabile 'seleziona - prodotti' alle variabili di query di WordPress.
 * load_template($template): Questo metodo carica il template per la pagina 'seleziona - prodotti' se la variabile di query corrispondente è impostata.
 * handle_form_submission(): Questo metodo gestisce l'invio del modulo per la selezione dei prodotti . Crea una copia di ciascun prodotto selezionato e lo assegna al venditore corrente .*/
namespace Dokan_Mods;

use WC_Admin_Duplicate_Product;
use WC_Product_Variation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__.'Dokan_Select_Products')) {
    class Dokan_Select_Products
    {
        // Constructor

        public function __construct()
        {
            //add_action('init', array($this, 'add_rewrite_rules')); // Aggiunge le regole di riscrittura all'inizializzazione
            //add_action('init', array($this, 'handle_form_submission')); // Gestisce l'invio del modulo all'inizializzazione
            add_action('admin_post_add_product_dokan_vendor', array($this, 'handle_form_submission'));
            add_action('admin_post_nopriv_add_product_dokan_vendor', array($this, 'handle_form_submission'));

            add_action('admin_post_remove_product_dokan_vendor', array($this, 'handle_remove_product_form'));
            add_action('admin_post_nopriv_remove_product_dokan_vendor', array($this, 'handle_remove_product_form'));


            add_action('woocommerce_product_options_general_product_data', array($this, 'dcw_add_withdraw_fields_to_product'));
            add_action('woocommerce_process_product_meta', array($this, 'dcw_save_withdraw_fields_to_product'));
            add_filter('dokan_get_seller_earnings', array($this,'dcw_apply_product_withdraw_rate'), 10, 3);

            add_action('admin_post_customize_poster', array($this, 'handle_customize_poster_form_submission'));
            add_action('admin_post_nopriv_customize_poster', array($this, 'handle_customize_poster_form_submission'));
        }


        public function handle_form_submission()
        {
            // Check if the form is submitted
            if (isset($_POST['selected_product_for_vendor'])) {
                // Sanitize and process the form data
                $data = $_POST;
                $selected_products = isset($data['product']) ? array_map('intval', $data['product']) : array();
                $price = isset($data['product_price']) ? array_map('floatval', $data['product_price']) : array();
                $description = isset($data['product_description']) ? array_map('wp_kses_post', $data['product_description']) : array();
                $variation_price = isset($data['variation_price']) ? array_map('floatval', $data['variation_price']) : array();
                $variation_description = isset($data['variation_description']) ? array_map('wp_kses_post', $data['variation_description']) : array();
                $variation_image = isset($data['variation_image']) ? array_map('intval', $data['variation_image']) : array();
                $enabled_variations = isset($data['enabled_variations']) ? array_map('intval', $data['enabled_variations']) : array();

                // Get the current user
                $user_id = get_current_user_id();
                $vendor_store_name = get_user_meta($user_id, 'dokan_store_name', true);

                foreach ($selected_products as $product_id) {
                    $product_title = get_the_title($product_id);

                    // Use a transient to cache the result of this query for a short period
                    $transient_key = 'vendor_product_check_' . $user_id . '_' . $product_id;
                    $product_exists = get_transient($transient_key);
                    $existing_product_id = "";
                    if ($product_exists === false) {
                        // Check if there is a product with the same title and the same vendor
                        $args = array(
                            'post_type' => 'product',
                            'post_status' => 'publish',
                            'posts_per_page' => 1,
                            'meta_query' => array(
                                array(
                                    'key' => '_vendor_id',
                                    'value' => $user_id
                                )
                            ),
                            'title' => $product_title
                        );

                        $existing_product = get_posts($args);
                        $product_exists = !empty($existing_product);

                        // Get the existing product id
                        if ($product_exists) {
                            $existing_product_id = $existing_product[0]->ID;
                        }

                        // Cache the result for 5 minutes
                        set_transient($transient_key, $product_exists, 5 * MINUTE_IN_SECONDS);
                    }

                    if (!$product_exists) {
                        // Create a copy of the product and assign it to the vendor with the category as title
                        $wo_dup = new WC_Admin_Duplicate_Product();

                        // Compatibility for WC 3.0.0+
                        $product = wc_get_product($product_id);
                        $clone_product = $wo_dup->product_duplicate($product);

                        // Get the price form product_price field if exists and set it as price
                        if (isset($price[$product_id])) {
                            $clone_product->set_regular_price($price[$product_id]);
                            $clone_product->save(); // Save the product data
                        }

                        // Set the description
                        if (!empty($description)) {
                            $clone_product->set_description($description[$product_id]);
                            $clone_product->save(); // Save the product data
                        }

                        $clone_product_id = $clone_product->get_id();
                        $product_status = dokan_get_default_product_status();

                        // Update the post status and title
                        $new_product_title = $product_title . ' - ' . $vendor_store_name;
                        wp_update_post(array(
                            'ID' => intval($clone_product_id),
                            'post_status' => 'pending',
                            'post_title' => $new_product_title
                        ));

                        // Update meta fields
                        update_post_meta($clone_product_id, '_sku', $product_id . '-' . $user_id);
                        update_post_meta($clone_product_id, '_vendor_id', $user_id);

                        // Set the category
                        $categoria_finale = get_field('categoria_finale', $product_id);
                        wp_set_object_terms($clone_product_id, $categoria_finale, 'product_cat', true);
                        wp_remove_object_terms($clone_product_id, 'default-products', 'product_cat');

                        // Handle variations
                        if ($product->is_type('variable')) {
                            $cloned_variations = $clone_product->get_children();
                            foreach ($cloned_variations as $cloned_variation_id) {
                                wp_delete_post($cloned_variation_id, true);
                            }

                            $variations = $product->get_children();
                            foreach ($variations as $variation_id) {
                                // Skip disabled variations
                                if (!in_array($variation_id, $enabled_variations)) {
                                    continue;
                                }

                                $variation_wc = wc_get_product($variation_id);

                                // Duplicate the variation
                                $clone_variation = new WC_Product_Variation();
                                $clone_variation->set_parent_id($clone_product_id);
                                $clone_variation->set_attributes($variation_wc->get_attributes());

                                if (isset($variation_price[$variation_id])) {
                                    $clone_variation->set_regular_price($variation_price[$variation_id]);
                                }

                                if (isset($variation_description[$variation_id])) {
                                    $clone_variation->set_description($variation_description[$variation_id]);
                                }

                                if (isset($variation_image[$variation_id])) {
                                    $clone_variation->set_image_id($variation_image[$variation_id]);
                                }

                                $clone_variation->save();
                            }
                        }

                        $operation_successful = true;
                    } else {
                        // Update the existing product with the new price
                        $existing_product = wc_get_product($existing_product_id);
                        $existing_product->set_regular_price($price[$product_id]);

                        // Set the description
                        if (!empty($description)) {
                            $existing_product->set_description($description[$product_id]);
                        }

                        $existing_product->save(); // Save the product data

                        // Handle variations
                        if ($existing_product->is_type('variable')) {
                            $variations = $existing_product->get_children();
                            foreach ($variations as $variation_id) {
                                // Skip disabled variations
                                if (!in_array($variation_id, $enabled_variations)) {
                                    continue;
                                }

                                $existing_variation = wc_get_product($variation_id);

                                if (isset($variation_price[$variation_id])) {
                                    $existing_variation->set_regular_price($variation_price[$variation_id]);
                                }

                                if (isset($variation_description[$variation_id])) {
                                    $existing_variation->set_description($variation_description[$variation_id]);
                                }

                                if (isset($variation_image[$variation_id])) {
                                    $existing_variation->set_image_id($variation_image[$variation_id]);
                                }

                                $existing_variation->save();
                            }
                        }

                        $operation_successful = true;
                    }
                }

                // Redirect to prevent form resubmission
                if (isset($operation_successful) && $operation_successful) {
                    wp_redirect(add_query_arg(array('operation_result' => 'success'), wp_get_referer()));
                } else {
                    wp_redirect(add_query_arg(array('operation_result' => 'error'), wp_get_referer()));
                }
                exit;
            }
        }


        public function handle_remove_product_form()
        {
            // Check if the form is submitted
            if (isset($_POST['remove_product'])) {
                // Sanitize and process the form data
                $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

                //cestina il prodotto
                $response = wp_trash_post($product_id);

                if ($response) {
                    wp_redirect(add_query_arg(array('operation_result' => 'success'), wp_get_referer()));
                } else {
                    wp_redirect(add_query_arg(array('operation_result' => 'error'), wp_get_referer()));
                }
                exit;
            }
        }

        public function dcw_add_withdraw_fields_to_product()
        {
            global $post;

            echo '<div class="options_group">';
            woocommerce_wp_select(
                array(
                    'id' => 'dokan_withdraw_type',
                    'label' => __('Withdraw Type', 'dokan-mod'),
                    'description' => __('Seleziona il tipo di withdrawal: percentuale o importo fisso.', 'dokan-mod'),
                    'options' => array(
                        'percentage' => __('Percentuale', 'dokan-mod'),
                        'fixed' => __('Importo fisso', 'dokan-mod')
                    ),
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id' => 'dokan_withdraw_rate',
                    'label' => __('Withdraw Rate (%)', 'dokan-mod'),
                    'desc_tip' => 'true',
                    'description' => __('Inserisci la percentuale di withdrawal per questo prodotto.', 'dokan-mod'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0',
                        'max' => '100',
                    ),
                    'class' => 'dcw_withdraw_field dcw_withdraw_percentage'
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id' => 'dokan_withdraw_fixed_amount',
                    'label' => __('Withdraw Amount (Fixed)', 'dokan-mod'),
                    'desc_tip' => 'true',
                    'description' => __('Inserisci l\'importo fisso di withdrawal per questo prodotto.', 'dokan-mod'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0',
                    ),
                    'class' => 'dcw_withdraw_field dcw_withdraw_fixed'
                )
            );
            echo '</div>';

            // Aggiungi lo script per mostrare/nascondere i campi
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    function toggleWithdrawFields() {
                        var type = $('#dokan_withdraw_type').val();
                        if (type === 'percentage') {
                            $('.dcw_withdraw_percentage').show();
                            $('.dcw_withdraw_fixed').hide();
                        } else if (type === 'fixed') {
                            $('.dcw_withdraw_percentage').hide();
                            $('.dcw_withdraw_fixed').show();
                        }
                    }

                    // Esegui al caricamento della pagina
                    toggleWithdrawFields();

                    // Esegui al cambiamento del valore
                    $('#dokan_withdraw_type').change(function () {
                        toggleWithdrawFields();
                    });
                });
            </script>
            <?php
        }

        public function dcw_save_withdraw_fields_to_product($post_id)
        {
            $withdraw_type = isset($_POST['dokan_withdraw_type']) ? sanitize_text_field($_POST['dokan_withdraw_type']) : 'percentage';
            update_post_meta($post_id, '_dokan_withdraw_type', $withdraw_type);

            $withdraw_rate = isset($_POST['dokan_withdraw_rate']) ? floatval($_POST['dokan_withdraw_rate']) : '';
            update_post_meta($post_id, '_dokan_withdraw_rate', $withdraw_rate);

            $withdraw_fixed_amount = isset($_POST['dokan_withdraw_fixed_amount']) ? floatval($_POST['dokan_withdraw_fixed_amount']) : '';
            update_post_meta($post_id, '_dokan_withdraw_fixed_amount', $withdraw_fixed_amount);
        }

        public function dcw_apply_product_withdraw_rate($earnings, $seller_id, $order_id)
        {
            $order = wc_get_order($order_id);
            $items = $order->get_items();

            foreach ($items as $item) {
                $product_id = $item->get_product_id();
                $withdraw_type = get_post_meta($product_id, '_dokan_withdraw_type', true);
                $withdraw_rate = get_post_meta($product_id, '_dokan_withdraw_rate', true);
                $withdraw_fixed_amount = get_post_meta($product_id, '_dokan_withdraw_fixed_amount', true);

                if ($withdraw_type === 'percentage' && $withdraw_rate) {
                    $product_price = $item->get_total();
                    $earnings -= ($product_price * ($withdraw_rate / 100));
                } elseif ($withdraw_type === 'fixed' && $withdraw_fixed_amount) {
                    $earnings -= $withdraw_fixed_amount;
                }
            }

            return $earnings;
        }

        public function handle_customize_poster_form_submission()
        {
            // Check if the submitted form is the customize poster form
            if (isset($_POST['action']) && $_POST['action'] == 'customize_poster') {
                // Sanitize the user_id
                $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

                // Sanitize the manifesto_background
                $manifesto_background = isset($_POST['manifesto_background']) ? sanitize_text_field($_POST['manifesto_background']) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/img/default.jpg';

                // Sanitize the manifesto_orientation
                $manifesto_orientation = isset($_POST['manifesto_orientation']) ? sanitize_text_field($_POST['manifesto_orientation']) : 'vertical';

                // Recupera i margini in percentuale dal form
                $top_percent = isset($_POST['margin_top_percent']) ? floatval($_POST['margin_top_percent']) : '3.9188837174992';
                $right_percent = isset($_POST['margin_right_percent']) ? floatval($_POST['margin_right_percent']) : '5.8620083240518';
                $bottom_percent = isset($_POST['margin_bottom_percent']) ? floatval($_POST['margin_bottom_percent']) : '3.9188837174992';
                $left_percent = isset($_POST['margin_left_percent']) ? floatval($_POST['margin_left_percent']) : '5.8620083240518';

                // Sanitize the alignment
                $alignment = isset($_POST['manifesto_alignment']) ? sanitize_text_field($_POST['manifesto_alignment']) : 'center';

                // Update the user meta
                update_user_meta($user_id, 'manifesto_background', $manifesto_background);
                update_user_meta($user_id, 'manifesto_orientation', $manifesto_orientation);

                // Update the user meta with margins in percentage
                update_user_meta($user_id, 'manifesto_margin_top', $top_percent);
                update_user_meta($user_id, 'manifesto_margin_right', $right_percent);
                update_user_meta($user_id, 'manifesto_margin_bottom', $bottom_percent);
                update_user_meta($user_id, 'manifesto_margin_left', $left_percent);
                update_user_meta($user_id, 'manifesto_alignment', $alignment);

                // Redirect back to the form page with a success message
                wp_redirect(add_query_arg('operation_result', 'success', wp_get_referer()));
                exit;
            }
        }


    }
//END CLASS
}//END ID
//END NAMESPACE