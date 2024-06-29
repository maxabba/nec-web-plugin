<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . 'DashboardMenuClass')) {
    class DashboardMenuClass
    {
        const PLUGIN_PATH = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/';

        private array $query_vars = ['seleziona-prodotti', 'crea-annuncio', 'lista-annunci', 'customize', 'trigesimo-add','lista-anniversari','crea-anniversario', 'lista-manifesti','crea-manifesto'];


        public function __construct()
        {
            add_action('init', array($this, 'dynamic_page_init')); // Inizializza le pagine dinamiche
            add_action('dokan_get_dashboard_nav', array($this, 'add_dashboard_menu')); // Aggiunge il menu alla dashboard di Dokan
            add_filter('query_vars', array($this, 'add_query_vars')); // Aggiunge le variabili di query
            add_filter('template_include', array($this, 'load_template'),12); // Include il template
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'),99999); // Mette in coda gli stili

        }

        public function dynamic_page_init(){

            foreach ($this->query_vars as $var) {
                add_rewrite_rule('^dashboard/' . $var . '/page/([0-9]+)/?', 'index.php?' . $var . '=true&paged=$matches[1]', 'top');
                add_rewrite_rule('^dashboard/' . $var . '/?', 'index.php?' . $var . '=true', 'top');
            }
        }

        public function enqueue_styles()
        {
            if (array_filter($this->query_vars, 'get_query_var')) {


                // Debugging: stampa tutti gli stili registrati
                // Prova a mettere in coda 'dokan-style'
                if (wp_style_is('dokan-style', 'registered')) {
                    wp_enqueue_style('dokan-style');
                    //set dokan-dashboard dokan-theme-hello-elementor class on body
                    add_filter('body_class', function ($classes) {
                        $classes = [];
                        $classes[] = 'page-template-default page page-id-58 logged-in theme-hello-elementor woocommerce-js elementor-default elementor-kit-6 dokan-dashboard dokan-theme-hello-elementor e--ua-blink e--ua-chrome e--ua-mac e--ua-webkit';
                        return $classes;
                    });
                } else {
                    echo 'The "dokan-style" is not registered.';
                }
                //enque all default template styles
                //check if acf is active and load the acf form css
                if (function_exists('acf_form_head')) {
                    acf_form_head();
                }

            }
            //check if query var is customize and load the customize css
            if (get_query_var('customize')) {
                wp_enqueue_media();
            }
        }

        public function add_dashboard_menu($urls)
        {
            unset($urls['products']);
            $urls['seleziona-prodotti'] = array(
                'title' => __('Aggiungi Servizi', 'dokan-mod'),
                'icon' => '<i class="fas fa-briefcase"></i>',
                'url' => site_url('/dashboard/seleziona-prodotti'), // Aggiungi qui l'URL del tuo template
                'pos' => 33,
                'permission' => 'dokan_view_product_menu'
            );

            $urls['annunci'] = array(
                'title' => __('Annunci', 'dokan-mod'),
                'icon' => '<i class="fas fa-cross"></i>',
                'url' => site_url('/dashboard/lista-annunci'),
                'pos' => 34,
                'submenu' => array(
                    'lista-annunci' => array(
                        'title' => __('Lista Annunci', 'dokan-mod'),
                        'icon' => '<i class="fas fa-list"></i>',
                        'url' => site_url('/dashboard/lista-annunci'),
                        'pos' => 35,
                        'permission' => 'dokan_view_product_menu'
                    ),
                    'crea-annuncio' => array(
                        'title' => __('Crea Annuncio', 'dokan-mod'),
                        'icon' => '<i class="fas fa-plus"></i>',
                        'url' => site_url('/dashboard/crea-annuncio'),
                        'pos' => 36,
                        'permission' => 'dokan_view_product_menu'
                    )
                )
            );

            if (isset($urls['settings'])) {
                $urls['settings']['submenu']['customize'] = array(
                    'title' => __('Personalizza', 'dokan-mod'),
                    'icon' => '<i class="fas fa-wrench"></i>',
                    'url' => site_url('/dashboard/customize'),
                    'pos' => 100,
                    'permission' => 'dokan_view_product_menu'
                );
            }
            return $urls;
        }

        public function add_query_vars($vars)
        {
            return array_merge($vars, $this->query_vars);
        }

        public function load_template($template)
        {
            global $wp_query;

            foreach ($this->query_vars as $var) {
                if (isset($wp_query->query_vars[$var])) {
                    $template_temp = self::PLUGIN_PATH . $var . '.php';
                    if (!file_exists($template_temp)) {
                        return $template;
                    }
                    return $template_temp;
                }
            }

            return $template;
        }


    }
}