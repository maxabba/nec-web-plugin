<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\MonitorTotemClass')) {
    class MonitorTotemClass
    {

        public function __construct()
        {
            add_action('init', array($this, 'init'));
            add_action('admin_menu', array($this, 'register_admin_menu'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // AJAX Actions
            add_action('wp_ajax_monitor_associate_defunto', array($this, 'ajax_associate_defunto'));
            add_action('wp_ajax_monitor_remove_association', array($this, 'ajax_remove_association'));
            add_action('wp_ajax_monitor_get_defunti', array($this, 'ajax_get_defunti'));
            add_action('wp_ajax_monitor_toggle_vendor', array($this, 'ajax_toggle_vendor'));
            
            // AJAX Actions for monitor display (no login required)
            add_action('wp_ajax_monitor_get_manifesti', array($this, 'ajax_get_manifesti'));
            add_action('wp_ajax_nopriv_monitor_get_manifesti', array($this, 'ajax_get_manifesti'));
            add_action('wp_ajax_monitor_check_association', array($this, 'ajax_check_association'));
            add_action('wp_ajax_nopriv_monitor_check_association', array($this, 'ajax_check_association'));
            
            // Query vars and template loading (following DashboardMenuClass pattern)
            add_filter('query_vars', array($this, 'add_query_vars'));
            add_filter('template_include', array($this, 'load_template'));
            
            // Menu integration is now handled by DashboardMenuClass
        }

        public function init()
        {
            // Setup user meta fields for monitor functionality
            $this->setup_user_meta_fields();
            
            // Register rewrite rules for monitor display URLs
            $this->register_rewrite_rules();
        }


        /**
         * Setup user meta fields for monitor functionality
         */
        private function setup_user_meta_fields()
        {
            // These will be used to store monitor settings for each vendor
            // monitor_enabled (bool) - Whether vendor can use monitor
            // monitor_url (string) - Unique URL slug for the vendor's monitor  
            // monitor_last_access (datetime) - Last time monitor was accessed
            // monitor_associated_post (int) - Currently associated post ID
        }

        /**
         * Register rewrite rules for monitor display URLs
         */
        private function register_rewrite_rules()
        {
            add_rewrite_rule(
                '^monitor/([^/]+)/([0-9]+)/([^/]+)/?$',
                'index.php?monitor_display=1&monitor_type=$matches[1]&monitor_id=$matches[2]&monitor_slug=$matches[3]',
                'top'
            );
            
            // Flush rewrite rules on activation (handle this in plugin activation hook)
            if (get_option('monitor_rewrite_rules_flushed') !== '3') {
                flush_rewrite_rules();
                update_option('monitor_rewrite_rules_flushed', '3');
            }
        }

        /**
         * Add query vars for monitor display functionality
         */
        public function add_query_vars($vars)
        {
            // Add monitor display query vars
            $vars[] = 'monitor_display';
            $vars[] = 'monitor_type';
            $vars[] = 'monitor_id';
            $vars[] = 'monitor_slug';
            return $vars;
        }

        /**
         * Load monitor display template
         */
        public function load_template($template)
        {
            // Check for monitor display template
            if (get_query_var('monitor_display')) {
                $monitor_template = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/monitor-display.php';
                if (file_exists($monitor_template)) {
                    // Instead of just returning the template, force WordPress to load it
                    // This ensures the template is executed even if other filters interfere
                    include($monitor_template);
                    exit; // Stop WordPress from loading any other template
                }
            }
            
            return $template;
        }

        /**
         * Register admin menu for monitor management
         */
        public function register_admin_menu()
        {
            add_submenu_page(
                'dokan-mod',
                __('Monitor Digitale', 'dokan-mod'),
                __('Monitor Digitale', 'dokan-mod'),
                'manage_options',
                'dokan-monitor-digitale',
                array($this, 'admin_page_callback')
            );
        }


        /**
         * Enqueue scripts for frontend monitor display
         */
        public function enqueue_scripts()
        {
            if (get_query_var('monitor_display')) {
                wp_enqueue_script(
                    'monitor-display',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/monitor-display.js',
                    array('jquery'),
                    '1.0.0',
                    true
                );
                
                wp_enqueue_style(
                    'monitor-display',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/monitor-display.css',
                    array(),
                    '1.0.0'
                );

                wp_localize_script('monitor-display', 'monitor_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('monitor_nonce'),
                    'monitor_id' => get_query_var('monitor_id'),
                    'monitor_slug' => get_query_var('monitor_slug'),
                    'polling_interval' => 15000 // 15 seconds
                ));
            }

            // Vendor dashboard scripts - only enqueue on monitor-digitale page
            if (get_query_var('monitor-digitale')) {
                wp_enqueue_script(
                    'monitor-vendor',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/monitor-vendor.js',
                    array('jquery'),
                    '1.0.0',
                    true
                );

                wp_localize_script('monitor-vendor', 'monitor_vendor_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('monitor_vendor_nonce')
                ));
            }
        }

        /**
         * Enqueue admin scripts
         */
        public function enqueue_admin_scripts($hook)
        {
            if ($hook === 'dokan-mods_page_dokan-monitor-digitale') {
                wp_enqueue_script(
                    'monitor-admin',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/monitor-admin.js',
                    array('jquery'),
                    '1.0.0',
                    true
                );

                wp_localize_script('monitor-admin', 'monitor_admin_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('monitor_admin_nonce')
                ));
            }
        }

        /**
         * Admin page callback
         */
        public function admin_page_callback()
        {
            $template_path = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/monitor-admin.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="notice notice-error"><p>Template monitor-admin.php not found.</p></div>';
            }
        }

        /**
         * Check if vendor is enabled for monitor
         */
        public function is_vendor_enabled($user_id)
        {
            return (bool) get_user_meta($user_id, 'monitor_enabled', true);
        }

        /**
         * Enable/disable vendor for monitor
         */
        public function set_vendor_enabled($user_id, $enabled = true)
        {
            update_user_meta($user_id, 'monitor_enabled', $enabled ? 1 : 0);
            
            if ($enabled) {
                // Set default monitor URL if not exists
                $monitor_url = get_user_meta($user_id, 'monitor_url', true);
                if (empty($monitor_url)) {
                    $vendor = dokan()->vendor->get($user_id);
                    $shop_name = $vendor->get_shop_name();
                    $default_url = sanitize_title($shop_name) . '-' . $user_id;
                    update_user_meta($user_id, 'monitor_url', $default_url);
                }
            }
        }

        /**
         * Get currently associated post for vendor
         */
        public function get_associated_post($user_id)
        {
            return (int) get_user_meta($user_id, 'monitor_associated_post', true);
        }

        /**
         * Associate post to vendor monitor
         */
        public function associate_post($user_id, $post_id)
        {
            update_user_meta($user_id, 'monitor_associated_post', $post_id);
            update_user_meta($user_id, 'monitor_last_access', current_time('mysql'));
        }

        /**
         * Remove association
         */
        public function remove_association($user_id)
        {
            delete_user_meta($user_id, 'monitor_associated_post');
            update_user_meta($user_id, 'monitor_last_access', current_time('mysql'));
        }

        /**
         * AJAX: Associate defunto to monitor
         */
        public function ajax_associate_defunto()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $post_id = intval($_POST['post_id']);

            if (!$this->is_vendor_enabled($user_id)) {
                wp_send_json_error('Vendor not enabled for monitor');
            }

            // Verify post belongs to vendor
            $post = get_post($post_id);
            if (!$post || $post->post_author != $user_id || $post->post_type !== 'annuncio-di-morte') {
                wp_send_json_error('Invalid post or insufficient permissions');
            }

            $this->associate_post($user_id, $post_id);
            
            wp_send_json_success(array(
                'message' => 'Defunto associato al monitor con successo',
                'post_id' => $post_id
            ));
        }

        /**
         * AJAX: Remove association
         */
        public function ajax_remove_association()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();

            if (!$this->is_vendor_enabled($user_id)) {
                wp_send_json_error('Vendor not enabled for monitor');
            }

            $this->remove_association($user_id);
            
            wp_send_json_success(array(
                'message' => 'Associazione rimossa con successo'
            ));
        }

        /**
         * AJAX: Get vendor's defunti list
         */
        public function ajax_get_defunti()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

            if (!$this->is_vendor_enabled($user_id)) {
                wp_send_json_error('Vendor not enabled for monitor');
            }

            $args = array(
                'post_type' => 'annuncio-di-morte',
                'author' => $user_id,
                'posts_per_page' => 20,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'DESC'
            );

            if (!empty($search)) {
                $args['s'] = $search;
            }

            $query = new \WP_Query($args);
            $associated_post = $this->get_associated_post($user_id);

            $defunti = array();
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $foto_defunto = get_field('foto_defunto', $post_id);
                    $data_morte = get_field('data_di_morte', $post_id);
                    
                    $defunti[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'foto' => $foto_defunto ? $foto_defunto['sizes']['thumbnail'] : '',
                        'data_morte' => $data_morte ? $data_morte : get_the_date('Y-m-d'),
                        'data_pubblicazione' => get_the_date('Y-m-d'),
                        'is_associated' => ($post_id == $associated_post)
                    );
                }
            }
            wp_reset_postdata();

            wp_send_json_success(array(
                'defunti' => $defunti,
                'total_pages' => $query->max_num_pages,
                'current_page' => $page
            ));
        }

        /**
         * AJAX: Toggle vendor enable/disable (Admin only)
         */
        public function ajax_toggle_vendor()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            $vendor_id = intval($_POST['vendor_id']);
            $enabled = $_POST['enabled'] === 'true';

            $this->set_vendor_enabled($vendor_id, $enabled);

            wp_send_json_success(array(
                'message' => $enabled ? 'Vendor abilitato per monitor' : 'Vendor disabilitato per monitor',
                'vendor_id' => $vendor_id,
                'enabled' => $enabled
            ));
        }

        /**
         * AJAX: Get manifesti for monitor display
         */
        public function ajax_get_manifesti()
        {
            $vendor_id = intval($_POST['vendor_id']);
            $post_id = intval($_POST['post_id']);

            if (!$vendor_id || !$post_id) {
                wp_send_json_error('Missing required parameters');
            }

            // Verify vendor is enabled and post is associated
            if (!$this->is_vendor_enabled($vendor_id)) {
                wp_send_json_error('Vendor not enabled for monitor');
            }

            $associated_post = $this->get_associated_post($vendor_id);
            if ($associated_post != $post_id) {
                wp_send_json_error('Post not associated to this monitor');
            }

            // Load manifesti using ManifestiLoader
            $loader = new ManifestiLoader($post_id, 0, 'top,silver,online');
            $manifesti = $loader->load_manifesti_for_monitor();

            wp_send_json_success(array(
                'manifesti' => $manifesti,
                'count' => count($manifesti),
                'last_update' => current_time('H:i:s')
            ));
        }

        /**
         * AJAX: Check if association has changed
         */
        public function ajax_check_association()
        {
            $vendor_id = intval($_POST['vendor_id']);
            $current_post_id = intval($_POST['current_post_id']);

            if (!$vendor_id) {
                wp_send_json_error('Missing vendor ID');
            }

            if (!$this->is_vendor_enabled($vendor_id)) {
                wp_send_json_error('Vendor not enabled for monitor');
            }

            $associated_post = $this->get_associated_post($vendor_id);
            $has_changed = ($associated_post != $current_post_id);

            if ($has_changed) {
                if ($associated_post) {
                    // New post associated
                    $post = get_post($associated_post);
                    $foto_defunto = get_field('foto_defunto', $associated_post);
                    $data_di_morte = get_field('data_di_morte', $associated_post);
                    $data_pubblicazione = get_the_date('d/m/Y', $associated_post);
                    
                    wp_send_json_success(array(
                        'changed' => true,
                        'new_post_id' => $associated_post,
                        'new_post_data' => array(
                            'title' => get_the_title($associated_post),
                            'foto' => $foto_defunto ? $foto_defunto['sizes']['medium'] : '',
                            'data_morte' => $data_di_morte ? date('d/m/Y', strtotime($data_di_morte)) : $data_pubblicazione
                        )
                    ));
                } else {
                    // No post associated - redirect to waiting screen
                    wp_send_json_success(array(
                        'changed' => true,
                        'new_post_id' => null,
                        'redirect_to_waiting' => true
                    ));
                }
            } else {
                wp_send_json_success(array(
                    'changed' => false,
                    'last_check' => current_time('H:i:s')
                ));
            }
        }

        /**
         * Get monitor URL for vendor
         */
        public function get_monitor_url($vendor_id)
        {
            $monitor_url = get_user_meta($vendor_id, 'monitor_url', true);
            if (empty($monitor_url)) {
                // Generate default URL
                $vendor = dokan()->vendor->get($vendor_id);
                $shop_name = $vendor->get_shop_name();
                $monitor_url = sanitize_title($shop_name) . '-' . $vendor_id;
                update_user_meta($vendor_id, 'monitor_url', $monitor_url);
            }
            return $monitor_url;
        }

        /**
         * Get full monitor display URL
         */
        public function get_monitor_display_url($vendor_id)
        {
            $monitor_url = $this->get_monitor_url($vendor_id);
            return home_url("/monitor/display/{$vendor_id}/{$monitor_url}");
        }
    }
}