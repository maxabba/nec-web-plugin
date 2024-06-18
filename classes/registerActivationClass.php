<?php

namespace Dokan_Mods;

class registerActivationClass
{
    private  $utils;
    public function __construct()
    {
        register_activation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'register_activation_hook'));
        register_deactivation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'dynamic_page_deactivate'));
        add_action('init', array($this, 'add_custom_rewrite_rules'));
        $this->utils = new UtilsAMClass();
        add_action('plugins_loaded', array($this, 'load_textdomain'));

    }
    public function load_textdomain()
    {
        load_plugin_textdomain('dokan-mod', false, dirname(plugin_basename(DOKAN_MOD_MAIN_FILE)) . '/languages/');
    }

    public function register_activation_hook()
    {
        global $dbClassInstance;
        $dbClassInstance->create_table();
        $this->utils->check_and_create_product();

    }

    public function add_custom_rewrite_rules()
    {
        $this->utils->dynamic_page_init();
    }

    public function dynamic_page_deactivate()
    {
        flush_rewrite_rules();
    }

}