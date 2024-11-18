<?php
/**
 * Plugin Name: Selezione Prodotti Predefiniti per Dokan
 * Description: Aggiunge una funzionalità per permettere ai vendor di selezionare prodotti predefiniti.
 * Version: 1.0
 * Author: Marco Abbattista
 */

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

//verify if woocommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Verifica se il plugin Dokan è attivo
if (!in_array('dokan-lite/dokan.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}


// Definizione delle costanti del plugin
define('DOKAN_SELECT_PRODUCTS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DOKAN_SELECT_PRODUCTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DOKAN_MOD_MAIN_FILE', __FILE__);

require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'vendor/autoload.php';

// Includi e istanzia DbClass solo se non esiste già
if (!class_exists(__NAMESPACE__ . '\DbClass')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/DbClass.php';
    $main_file = DOKAN_MOD_MAIN_FILE; // Sostituisci con il percorso del file principale del plugin
    global $dbClassInstance;
    $dbClassInstance = new DbClass();
}

if (!class_exists(__NAMESPACE__ . '\UtilsAMClass')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/UtilsAMClass.php';
}

if (!class_exists(__NAMESPACE__ . '\RenderDokanSelectProducts')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/RenderDokanSelectProducts.php';
}

if (!class_exists(__NAMESPACE__ . '\Templates_MiscClass')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/Templates_MiscClass.php';
}

// Funzione per caricare e istanziare le classi
if (!class_exists(__NAMESPACE__ . '\ClassEnabler')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/ClassEnabler.php';
    new ClassEnabler();
}

// Includi FiltersClass senza istanziarla
if (!class_exists(__NAMESPACE__ . '\FiltersClass')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/FiltersClass.php';
}


