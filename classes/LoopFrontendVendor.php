<?php

namespace Dokan_Mods;

use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class LoopFrontendVendor
{
    private $utils;

    public function __construct()
    {
        $this->utils = new UtilsAMClass();

        // Register shortcodes
        add_action('init', array($this, 'register_shortcodes'));

        // AJAX handlers
        add_action('wp_ajax_search_vendors', array($this, 'handle_vendor_search'));
        add_action('wp_ajax_nopriv_search_vendors', array($this, 'handle_vendor_search'));

        add_filter('dokan_locate_template', array($this, 'override_store_template'), 10, 3);

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function register_shortcodes()
    {
        add_shortcode('vendor_search', array($this, 'render_vendor_search'));
        add_shortcode('vendor_profile', array($this, 'render_vendor_profile'));
    }

    public function enqueue_assets()
    {
        wp_enqueue_style(
            'vendor-search-style',
            DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/vendor-search.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'vendor-search-script',
            DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/vendor-search.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('vendor-search-script', 'vendorSearchAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vendor-search-nonce')
        ));
    }

    public function render_vendor_search($atts)
    {
        global $dbClassInstance;
        $provinces = $dbClassInstance->get_all_Province();

        ob_start();
        ?>
        <div class="vendor-search-container">
            <form id="vendor-search-form" class="vendor-search-form">
                <div class="search-fields">
                    <div class="search-field">
                        <input type="text"
                               id="vendor-name"
                               name="vendor_name"
                               placeholder="Nome venditore...">
                    </div>

                    <div class="search-field">
                        <select name="province" id="province-select">
                            <option value="">Seleziona Provincia</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo esc_attr($province['provincia_nome']); ?>">
                                    <?php echo esc_html($province['provincia_nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-field">
                        <select name="city" id="city-select" disabled>
                            <option value="">Seleziona Città</option>
                        </select>
                    </div>

                    <button type="submit" class="search-button">Cerca</button>
                </div>
            </form>

            <div id="vendor-results" class="vendor-results-grid"></div>
            <div id="vendor-loading" class="vendor-loading" style="display:none;">
                <div class="loader"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_vendor_search()
    {
        check_ajax_referer('vendor-search-nonce', 'nonce');

        $vendor_name = sanitize_text_field($_POST['vendor_name'] ?? '');
        $province = sanitize_text_field($_POST['province'] ?? '');
        $city = sanitize_text_field($_POST['city'] ?? '');

        $args = array(
            'role__in' => array('seller', 'fiorai'),  // Usa role__in invece di role
            'meta_query' => array('relation' => 'AND'),
        );

        if (!empty($vendor_name)) {
            $args['meta_query'][] = array(
                'key' => 'dokan_store_name',
                'value' => $vendor_name,
                'compare' => 'LIKE'
            );
        }

        if (!empty($city)) {
            $args['meta_query'][] = array(
                'key' => 'dokan_profile_settings',
                'value' => $city,
                'compare' => 'LIKE'
            );
        } elseif (!empty($province)) {
            // Get all cities in province and create an OR query
            global $dbClassInstance;
            $cities = $dbClassInstance->get_comuni_by_provincia($province);
            $city_queries = array('relation' => 'OR');
            foreach ($cities as $city) {
                $city_queries[] = array(
                    'key' => 'dokan_profile_settings',
                    'value' => $city,
                    'compare' => 'LIKE'
                );
            }
            $args['meta_query'][] = $city_queries;
        }

        $vendors = get_users($args);
        $results = array();

        foreach ($vendors as $vendor) {
            $store_info = dokan_get_store_info($vendor->ID);
            $store_name = $store_info['store_name'] ?? '';
            $store_banner = wp_get_attachment_url($store_info['banner'] ?? '');
            $store_address = $store_info['address'] ?? array();

            $results[] = array(
                'id' => $vendor->ID,
                'store_name' => $store_name,
                'banner_url' => $store_banner ?: DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default-banner.jpg',
                'city' => $store_address['city'] ?? '',
                'address' => $store_address['street_1'] ?? '',
                'phone' => $store_info['phone'] ?? '',
                'profile_url' => dokan_get_store_url($vendor->ID)
            );
        }

        wp_send_json_success($results);
    }

    public function render_vendor_profile($atts)
    {
        $atts = shortcode_atts(array(
            'vendor_id' => get_query_var('vendor_id')
        ), $atts);

        if (empty($atts['vendor_id'])) {
            return '<p>Agenzia non specificata</p>';
        }

        $vendor = dokan()->vendor->get($atts['vendor_id']);
        if (!$vendor) {
            return '<p>Agenzia non trovata</p>';
        }

        $store_info = $vendor->get_shop_info();
        $banner_url = $vendor->get_banner() ?: DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default-banner.jpg';

        ob_start();
        ?>
        <div class="vendor-profile-container">
            <div class="vendor-header">
                <div class="vendor-banner">
                    <img src="<?php echo esc_url($banner_url); ?>"
                         alt="<?php echo esc_attr($store_info['store_name']); ?>">
                </div>
                <h1><?php echo esc_html($store_info['store_name']); ?></h1>
            </div>

            <div class="vendor-details">
                <div class="vendor-info">
                    <h3>Informazioni</h3>
                    <p><strong>Indirizzo:</strong> <?php echo esc_html($store_info['address']['street_1'] ?? ''); ?></p>
                    <p><strong>Città:</strong> <?php echo esc_html($store_info['address']['city'] ?? ''); ?></p>
                    <p><strong>Telefono:</strong> <?php echo esc_html($store_info['phone'] ?? ''); ?></p>
                </div>

                <?php if (!empty($store_info['description'])): ?>
                    <div class="vendor-description">
                        <h3>Chi siamo</h3>
                        <?php echo wp_kses_post($store_info['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Sostituisce il template del negozio di Dokan
     */
    public function override_store_template($template, $template_name, $template_path)
    {
        // Intercetta solo il template store.php
        if ($template_name === 'store.php') {
            $custom_template = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/store.php';

            // Verifica che il template esista
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

}