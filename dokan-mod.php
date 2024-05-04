<?php
/**
 * Plugin Name: Selezione Prodotti Predefiniti per Dokan
 * Description: Aggiunge una funzionalità per permettere ai vendor di selezionare prodotti predefiniti.
 * Version: 1.0
 * Author: Marco Abbattista
 */
namespace Dokan_Mods;

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


if (!class_exists(__NAMESPACE__.'DbClass')) {
    include_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/DbClass.php';
    $main_file = __FILE__; // Replace with your main plugin file path
    global $dbClassInstance;
    $dbClassInstance = new DbClass($main_file);
}

if( ! class_exists(__NAMESPACE__ .'Dokan_Select_Products' ) ) {
    include_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/dokan_select_products.php';
    new Dokan_Select_Products();
}
if (!class_exists(__NAMESPACE__ .'Miscellaneous')) {
    include_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/Miscellaneous.php';
    new Miscellaneous();
}

if (!class_exists(__NAMESPACE__ .'Miscellaneous')) {
    include_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/RegistrationForm.php';
    new RegistrationForm();
}


// Initialize the plugin



