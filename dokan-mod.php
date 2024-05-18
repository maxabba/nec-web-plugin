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

// Includi e istanzia DbClass solo se non esiste già
if (!class_exists(__NAMESPACE__ . '\DbClass')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/DbClass.php';
    $main_file = DOKAN_MOD_MAIN_FILE; // Sostituisci con il percorso del file principale del plugin
    global $dbClassInstance;
    $dbClassInstance = new DbClass($main_file);
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
dokan_mods_load_and_instantiate_class('Dokan_Select_Products', 'classes/dokan_select_products.php');
dokan_mods_load_and_instantiate_class('Miscellaneous', 'classes/Miscellaneous.php');
dokan_mods_load_and_instantiate_class('RegistrationForm', 'classes/RegistrationForm.php');
dokan_mods_load_and_instantiate_class('AnnuncioMorteClass', 'classes/AnnuncioMorteClass.php');

// Includi FiltersClass senza istanziarla
if (!class_exists(__NAMESPACE__ . '\FiltersClass')) {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/FiltersClass.php';
}


//register the incluses widget to elementor
add_action('elementor/widgets/widgets_registered', function () {
    require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'includes/WidgetBuyElementor/widget_buy.php';
    \Elementor\Plugin::instance()->widgets_manager->register(new \Dokan_Mods\Annunci_Widget());
});
function my_elementor_annunci_Widget_scripts()
{
    wp_enqueue_style('my-elementor-annunci-widget-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/WidgetBuyElementor/css/style.css');
    wp_enqueue_script('my-elementor-annunci-widget-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL. 'includes/WidgetBuyElementor/js/script.js', ['jquery'], null, true);
}

add_action('wp_enqueue_scripts', 'Dokan_Mods\my_elementor_annunci_Widget_scripts');