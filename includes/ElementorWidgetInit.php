<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
class ElementorWidgetInit
{

    public function __construct()
    {
        add_action('elementor/widgets/widgets_registered', function ()
        {
            require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'includes/WidgetBuyElementor/widget_buy.php';
            \Elementor\Plugin::instance()->widgets_manager->register(new \Dokan_Mods\Annunci_Widget());

            require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'includes/AddPDefault/add-p-default-widget.php';

            \Elementor\Plugin::instance()->widgets_manager->register(new \Elementor\Add_P_Default_Widget());
        });
        add_action('wp_enqueue_scripts', [$this, 'my_elementor_annunci_Widget_scripts']);
    }


    public function my_elementor_annunci_Widget_scripts()
    {
        wp_enqueue_style('my-elementor-annunci-widget-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/WidgetBuyElementor/css/style.css');
        wp_enqueue_script('my-elementor-annunci-widget-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/WidgetBuyElementor/js/script.js', ['jquery'], null, true);

        wp_register_script('add-p-default-widget-js', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/AddPDefault/js/add-p-default-widget.js' , ['elementor-frontend'], '1.0', true);


    }



}