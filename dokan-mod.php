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
function dokan_mods_load_and_instantiate_class($class_name, $file_path)
{
    if (!class_exists(__NAMESPACE__ . '\\' . $class_name)) {
        require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . $file_path;
        if (class_exists(__NAMESPACE__ . '\\' . $class_name)) {
            $class_with_namespace = __NAMESPACE__ . '\\' . $class_name;
            new $class_with_namespace();
        } else {
            error_log("Class $class_name not found in file $file_path.");
        }
    } else {
        error_log("Class $class_name already exists.");
    }
}

// Includi e istanzia le classi necessarie
dokan_mods_load_and_instantiate_class('ProductTemplateClass', 'classes/admin/ProductTemplateClass.php');
dokan_mods_load_and_instantiate_class('MigrationClass', 'classes/admin/MigrationClass.php');
//dokan_mods_load_and_instantiate_class('ParallelWPCron', 'classes/admin/ParallelWPCron.php');
dokan_mods_load_and_instantiate_class('Dokan_Select_Products', 'classes/dokan_select_products.php');
dokan_mods_load_and_instantiate_class('DashboardMenuClass', 'classes/DashboardMenuClass.php');
dokan_mods_load_and_instantiate_class('Miscellaneous', 'classes/Miscellaneous.php');
dokan_mods_load_and_instantiate_class('RegistrationForm', 'classes/RegistrationForm.php');
dokan_mods_load_and_instantiate_class('AnnuncioMorteClass', 'classes/AnnuncioMorteClass.php');
dokan_mods_load_and_instantiate_class('registerActivationClass', 'classes/registerActivationClass.php');
dokan_mods_load_and_instantiate_class('TrigesimiClass', 'classes/TrigesimiClass.php');
dokan_mods_load_and_instantiate_class('AnniversarioClass', 'classes/AnniversarioClass.php');
dokan_mods_load_and_instantiate_class('PensieriniClass', 'classes/PensieriniClass.php');
dokan_mods_load_and_instantiate_class('ManifestoClass', 'classes/ManifestoClass.php');
dokan_mods_load_and_instantiate_class('RingraziamentoClass', 'classes/RingraziamentoClass.php');
dokan_mods_load_and_instantiate_class('FioraiClass', 'classes/FioraiClass.php');

dokan_mods_load_and_instantiate_class('NecrologiFrontendClass', 'classes/NecrologiFrontendClass.php');
dokan_mods_load_and_instantiate_class('TrigesimiFrontendClass', 'classes/TrigesimiFrontendClass.php');
dokan_mods_load_and_instantiate_class('AnniversarioFrontendClass', 'classes/AnniversarioFrontendClass.php');
dokan_mods_load_and_instantiate_class('RingraziamentoFrontendClass', 'classes/RingraziamentoFrontendClass.php');
dokan_mods_load_and_instantiate_class('VendorFrontendClass', 'classes/VendorFrontendClass.php');

dokan_mods_load_and_instantiate_class('SearchFrontendClass', 'classes/SearchFrontendClass.php');
dokan_mods_load_and_instantiate_class('PrintManifestoClass', 'classes/PrintManifestoClass.php');


dokan_mods_load_and_instantiate_class('ElementorWidgetInit', 'includes/ElementorWidgetInit.php');


// Includi FiltersClass senza istanziarla
if (!class_exists(__NAMESPACE__ . '\FiltersClass')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/FiltersClass.php';
}


