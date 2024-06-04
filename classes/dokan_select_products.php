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


            add_action('woocommerce_product_options_general_product_data', array($this, 'dcw_add_withdraw_fields_to_product'));
            add_action('woocommerce_process_product_meta', array($this, 'dcw_save_withdraw_fields_to_product'));
            add_filter('dokan_get_seller_earnings', array($this,'dcw_apply_product_withdraw_rate'), 10, 3);

            add_action('admin_post_customize_poster', array($this, 'handle_customize_poster_form_submission'));
            add_action('admin_post_nopriv_customize_poster', array($this, 'handle_customize_poster_form_submission'));
        }

        //get all products id with class default-products








        //add query var

        //show the template if the query var is set




        public function handle_form_submission()
        {
            // Check if the form is submitted
            if (isset($_POST['selected_product_for_vendor'])) {
                // Sanitize and process the form data
                $data = filter_input_array(INPUT_POST, [
                    'product' => [
                        'filter' => FILTER_VALIDATE_INT,
                        'flags' => FILTER_REQUIRE_ARRAY,
                    ],
                    'product_price' => [
                        'filter' => FILTER_VALIDATE_FLOAT,
                        'flags' => FILTER_REQUIRE_ARRAY,
                    ]
                ]);

                $selected_products = $data['product'] ?? array();
                $price = $data['product_price'] ?? array();

                // Get the current user
                $user_id = get_current_user_id();
                $vendor_store_name = get_user_meta($user_id, 'dokan_store_name', true);

                foreach ($selected_products as $product_id) {
                    $product_title = get_the_title($product_id);

                    // Use a transient to cache the result of this query for a short period
                    $transient_key = 'vendor_product_check_' . $user_id . '_' . $product_id;
                    $product_exists = get_transient($transient_key);

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

                        $clone_product_id = $clone_product->get_id();
                        $product_status = dokan_get_default_product_status();

                        // Update the post status and title
                        $new_product_title = $product_title . ' - ' . $vendor_store_name;
                        wp_update_post(array(
                            'ID' => intval($clone_product_id),
                            'post_status' => $product_status,
                            'post_title' => $new_product_title
                        ));

                        // Update meta fields
                        update_post_meta($clone_product_id, '_sku', $product_id . '-' . $user_id);
                        update_post_meta($clone_product_id, '_vendor_id', $user_id);

                        // Set the category
                        $categoria_finale = get_field('categoria_finale', $product_id);
                        wp_set_object_terms($clone_product_id, $categoria_finale, 'product_cat', true);
                        wp_remove_object_terms($clone_product_id, 'default-products', 'product_cat');

                        $operation_successful = true;
                    } else {
                        $operation_successful = false;
                    }
                }
                    // Redirect to prevent form resubmission
                    if ($operation_successful) {
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
                    'label' => __('Withdraw Type', 'dokan'),
                    'description' => __('Seleziona il tipo di withdrawal: percentuale o importo fisso.', 'dokan'),
                    'options' => array(
                        'percentage' => __('Percentuale', 'dokan'),
                        'fixed' => __('Importo fisso', 'dokan')
                    ),
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id' => 'dokan_withdraw_rate',
                    'label' => __('Withdraw Rate (%)', 'dokan'),
                    'desc_tip' => 'true',
                    'description' => __('Inserisci la percentuale di withdrawal per questo prodotto.', 'dokan'),
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
                    'label' => __('Withdraw Amount (Fixed)', 'dokan'),
                    'desc_tip' => 'true',
                    'description' => __('Inserisci l\'importo fisso di withdrawal per questo prodotto.', 'dokan'),
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
                $manifesto_background = isset($_POST['manifesto_background']) ? sanitize_text_field($_POST['manifesto_background']) : '';

                // Update the user meta
                update_user_meta($user_id, 'manifesto_background', $manifesto_background);

                // Redirect back to the form page with a success message
                wp_redirect(add_query_arg('operation_result', 'success', wp_get_referer()));
                exit;
            }
        }
    }
//END CLASS
}//END ID
//END NAMESPACE