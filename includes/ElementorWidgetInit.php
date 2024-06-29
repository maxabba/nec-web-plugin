<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ElementorWidgetInit
{
    public function __construct()
    {
        add_action('elementor/widgets/widgets_registered', function () {
            require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'includes/WidgetBuyElementor/widget_buy.php';
            \Elementor\Plugin::instance()->widgets_manager->register(new \Dokan_Mods\Annunci_Widget());

            require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'includes/AddPDefault/add-p-default-widget.php';
            \Elementor\Plugin::instance()->widgets_manager->register(new \Elementor\Add_P_Default_Widget());

            require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'includes/DynManifestoText/Elementor_Dynamic_Input_Slider_Widget.php';
            \Elementor\Plugin::instance()->widgets_manager->register(new \Elementor\Elementor_Dynamic_Input_Slider_Widget());
        });

        add_action('elementor/frontend/before_render', [$this, 'enqueue_styles_and_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'register_styles_and_scripts']);
    }

    public function register_styles_and_scripts()
    {
        // Registrazione degli stili
        wp_register_style('my-elementor-annunci-widget-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/WidgetBuyElementor/css/style.css');
        wp_register_style('swiper', 'https://unpkg.com/swiper/swiper-bundle.min.css');

        // Registrazione degli script
        wp_register_script('my-elementor-annunci-widget-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/WidgetBuyElementor/js/script.js', ['jquery'], null, true);
        wp_register_script('add-p-default-widget-js', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/AddPDefault/js/add-p-default-widget.js', ['elementor-frontend'], '1.0', true);
        wp_register_script('swiper', 'https://unpkg.com/swiper/swiper-bundle.min.js', [], false, true);
        wp_register_script('custom-slider', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'includes/DynManifestoText/js/slider.js', ['swiper'], false, true);
    }

    public function enqueue_styles_and_scripts($element)
    {
        // Enqueue degli stili e degli script basati sul tipo di widget
        if ($element->get_name() === 'annunci_widget') {
            wp_enqueue_style('my-elementor-annunci-widget-style');
            wp_enqueue_script('my-elementor-annunci-widget-script');
        }

        if ($element->get_name() === 'add_p_default_widget') {
            wp_enqueue_script('add-p-default-widget-js');
        }

        if ($element->get_name() === 'elementor_dynamic_input_slider_widget') {
            wp_enqueue_style('swiper');
            wp_enqueue_script('swiper');
            wp_enqueue_script('custom-slider');
        }
    }
}
