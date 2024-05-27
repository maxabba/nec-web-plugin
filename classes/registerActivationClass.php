<?php

namespace Dokan_Mods;

class registerActivationClass
{
    private $utils;
    public function __construct()
    {
        register_activation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'register_activation_hook'));
        register_deactivation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'dynamic_page_deactivate'));
        add_action('init', array($this, 'add_custom_rewrite_rules'));
        $this->utils = new UtilsAMClass();

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
        add_rewrite_rule('^dashboard/seleziona-prodotti/?', 'index.php?seleziona-prodotti=true', 'top');
        add_rewrite_rule('^dashboard/crea-annuncio/?', 'index.php?crea-annuncio=true', 'top');
        add_rewrite_rule('^dashboard/annunci/?', 'index.php?annunci=true', 'top');

    }

    public function dynamic_page_deactivate()
    {
        flush_rewrite_rules();
    }

}