<?php

namespace Dokan_Mods;

class registerActivationClass
{
    public function __construct()
    {
        register_activation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'register_activation_hook'));
        register_deactivation_hook(DOKAN_MOD_MAIN_FILE, array($this, 'dynamic_page_deactivate'));

    }
    public function register_activation_hook()
    {
        global $dbClassInstance;
        $dbClassInstance->create_table();
        $util = new UtilsAMClass();
        $util->check_and_create_product();
        $util->dynamic_page_init();
        add_rewrite_rule('^dashboard/seleziona-prodotti/?', 'index.php?seleziona-prodotti=true', 'top');

    }


    public function dynamic_page_deactivate()
    {
        flush_rewrite_rules();
    }

}