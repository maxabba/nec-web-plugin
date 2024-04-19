<?php
/**
 * Plugin Name: Selezione Prodotti Predefiniti per Dokan
 * Description: Aggiunge una funzionalità per permettere ai vendor di selezionare prodotti predefiniti.
 * Version: 1.0
 * Author: Marco Abbattista
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
//if the plugin dokan is not active, then don't load the plugin
if ( ! in_array( 'dokan-lite/dokan.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}
//define all the constants of the plugin
define( 'DOKAN_SELECT_PRODUCTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOKAN_SELECT_PRODUCTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

include DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/dokan_select_products.php';

// Initialize the plugin
new Dokan_Select_Products();



