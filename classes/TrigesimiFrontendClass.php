<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\TrigesimiFrontendClass')) {

    class TrigesimiFrontendClass
    {
        public function __construct()
        {
            //add_action('pre_get_posts', array($this, 'custom_filter_query'));
            add_action('elementor/query/trigesimo_loop', array($this, 'apply_custom_filter_query'));
        }


        public function apply_custom_filter_query($query)
        {
            (new FiltersClass())->custom_filter_query($query);
        }
    }
}