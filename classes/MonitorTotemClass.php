<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\MonitorTotemClass')) {
    class MonitorTotemClass
    {
        private $db_manager;

        public function __construct()
        {
            $this->db_manager = MonitorDatabaseManager::get_instance();
            
            add_action('init', array($this, 'init'));
            add_action('admin_menu', array($this, 'register_admin_menu'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // AJAX Actions - Updated for multiple monitors
            add_action('wp_ajax_monitor_associate_defunto', array($this, 'ajax_associate_defunto'));
            add_action('wp_ajax_monitor_remove_association', array($this, 'ajax_remove_association'));
            add_action('wp_ajax_monitor_get_defunti', array($this, 'ajax_get_defunti'));
            add_action('wp_ajax_monitor_get_vendor_monitors', array($this, 'ajax_get_vendor_monitors'));
            add_action('wp_ajax_monitor_create_monitor', array($this, 'ajax_create_monitor'));
            add_action('wp_ajax_monitor_update_monitor', array($this, 'ajax_update_monitor'));
            add_action('wp_ajax_monitor_delete_monitor', array($this, 'ajax_delete_monitor'));
            add_action('wp_ajax_monitor_toggle_monitor', array($this, 'ajax_toggle_monitor'));
            
            // AJAX Actions for monitor display (no login required)
            add_action('wp_ajax_monitor_get_manifesti', array($this, 'ajax_get_manifesti'));
            add_action('wp_ajax_nopriv_monitor_get_manifesti', array($this, 'ajax_get_manifesti'));
            add_action('wp_ajax_monitor_get_solo_annuncio', array($this, 'ajax_get_solo_annuncio'));
            add_action('wp_ajax_nopriv_monitor_get_solo_annuncio', array($this, 'ajax_get_solo_annuncio'));
            add_action('wp_ajax_monitor_get_citta_multi', array($this, 'ajax_get_citta_multi'));
            add_action('wp_ajax_nopriv_monitor_get_citta_multi', array($this, 'ajax_get_citta_multi'));
            add_action('wp_ajax_monitor_check_association', array($this, 'ajax_check_association'));
            add_action('wp_ajax_nopriv_monitor_check_association', array($this, 'ajax_check_association'));
            
            // Preview system AJAX actions
            add_action('wp_ajax_monitor_preview_layout', array($this, 'ajax_preview_layout'));
            
            // Admin-only AJAX actions
            add_action('wp_ajax_monitor_get_vendors', array($this, 'ajax_get_vendors'));
            add_action('wp_ajax_monitor_search_vendors', array($this, 'ajax_search_vendors'));
            add_action('wp_ajax_monitor_get_all_monitors', array($this, 'ajax_get_all_monitors'));
            add_action('wp_ajax_monitor_get_monitor_details', array($this, 'ajax_get_monitor_details'));
            
            // New AJAX endpoints for modal functionality
            add_action('wp_ajax_monitor_get_defunti_for_association', array($this, 'ajax_get_defunti_for_association'));
            add_action('wp_ajax_monitor_toggle_defunto_association', array($this, 'ajax_toggle_defunto_association'));
            
            // Query vars and template loading
            add_filter('query_vars', array($this, 'add_query_vars'));
            add_filter('template_include', array($this, 'load_template'));
            
            // Migration handler
            add_action('admin_init', array($this, 'handle_migration_requests'));
        }

        /**
         * Safely decode layout_config JSON data
         * Handles both string JSON and already decoded arrays
         */
        private function safe_decode_layout_config($layout_config)
        {
            if (is_string($layout_config) && !empty($layout_config)) {
                $decoded = json_decode($layout_config, true);
                return is_array($decoded) ? $decoded : [];
            } elseif (is_array($layout_config)) {
                return $layout_config;
            } else {
                return [];
            }
        }

        public function init()
        {
            // Register rewrite rules for monitor display URLs
            $this->register_rewrite_rules();
        }

        /**
         * Register rewrite rules for monitor display URLs
         * New format: /monitor/display/{vendor_id}/{monitor_id}/{slug}
         */
        private function register_rewrite_rules()
        {
            add_rewrite_rule(
                '^monitor/display/([0-9]+)/([0-9]+)/([^/]+)/?$',
                'index.php?monitor_display=1&vendor_id=$matches[1]&monitor_id=$matches[2]&monitor_slug=$matches[3]',
                'top'
            );
            
            // Legacy support for old format
            add_rewrite_rule(
                '^monitor/([^/]+)/([0-9]+)/([^/]+)/?$',
                'index.php?monitor_display=1&monitor_type=$matches[1]&legacy_vendor_id=$matches[2]&monitor_slug=$matches[3]',
                'top'
            );

            // Flush rewrite rules on activation
            if (get_option('monitor_rewrite_rules_flushed') !== '4') {
                flush_rewrite_rules();
                update_option('monitor_rewrite_rules_flushed', '4');
            }
        }

        /**
         * Add query vars for monitor display functionality
         */
        public function add_query_vars($vars)
        {
            // Add monitor display query vars
            $vars[] = 'monitor_display';
            $vars[] = 'vendor_id';
            $vars[] = 'monitor_id';
            $vars[] = 'monitor_slug';
            // Legacy support
            $vars[] = 'monitor_type';
            $vars[] = 'legacy_vendor_id';
            $vars[] = 'legacy_post_id';
            return $vars;
        }

        /**
         * Load monitor display template
         */
        public function load_template($template)
        {
            if (get_query_var('monitor_display')) {
                $monitor_template = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/monitor-display-new.php';
                if (file_exists($monitor_template)) {
                    include($monitor_template);
                    exit;
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
                    '1.1.0',
                    true
                );
                
                wp_enqueue_style(
                    'monitor-display',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/monitor-display.css',
                    array(),
                    '1.1.0'
                );

                $monitor_id = get_query_var('monitor_id');
                $vendor_id = get_query_var('vendor_id');
                
                wp_localize_script('monitor-display', 'monitor_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('monitor_nonce'),
                    'monitor_id' => $monitor_id,
                    'vendor_id' => $vendor_id,
                    'monitor_slug' => get_query_var('monitor_slug'),
                    'polling_interval' => 15000
                ));
            }

            // Vendor dashboard scripts
            if (get_query_var('monitor-digitale')) {
                wp_enqueue_script(
                    'monitor-vendor',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/monitor-vendor.js',
                    array('jquery'),
                    '1.1.0',
                    true
                );

                // Enqueue preview system
                wp_enqueue_script(
                    'monitor-preview',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/monitor-preview.js',
                    array('jquery'),
                    '1.0.0',
                    true
                );

                wp_enqueue_style(
                    'monitor-preview',
                    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/monitor-preview.css',
                    array(),
                    '1.0.0'
                );

                // Add inline styles for vendor dashboard
                wp_add_inline_style('monitor-preview', '
                    .monitor-card {
                        background: white;
                        border: 1px solid #e9ecef;
                        border-radius: 8px;
                        margin-bottom: 20px;
                        padding: 20px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    }
                    .monitor-card-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin-bottom: 20px;
                        padding-bottom: 15px;
                        border-bottom: 1px solid #e9ecef;
                    }
                    .monitor-info h4 {
                        margin: 0 0 8px 0;
                        font-size: 18px;
                        font-weight: 600;
                    }
                    .monitor-url {
                        display: flex;
                        align-items: center;
                        margin-top: 5px;
                    }
                    .monitor-url code {
                        background: #f8f9fa;
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 11px;
                        max-width: 300px;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }
                    .monitor-status .status-active {
                        color: #28a745;
                        text-align: right;
                    }
                    .monitor-status .status-inactive {
                        color: #dc3545;
                        text-align: right;
                    }
                    .layout-config {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 6px;
                        margin-bottom: 15px;
                    }
                    .layout-config .form-row {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        margin-bottom: 10px;
                    }
                    .layout-config .form-row:last-child {
                        margin-bottom: 0;
                    }
                    .layout-config label {
                        min-width: 120px;
                        font-weight: 500;
                    }
                    .layout-specific-config {
                        margin-top: 15px;
                        padding-top: 15px;
                        border-top: 1px solid #dee2e6;
                    }
                    .monitor-actions {
                        display: flex;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .btn-preview-layout {
                        border: none !important;
                        color: white !important;
                        text-decoration: none !important;
                        font-weight: 500 !important;
                    }
                    .btn-preview-layout:hover {
                        background: #5a67d8 !important;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
                    }
                    @media (max-width: 768px) {
                        .monitor-card-header {
                            flex-direction: column;
                            gap: 15px;
                        }
                        .layout-config .form-row {
                            flex-direction: column;
                            align-items: stretch;
                            gap: 5px;
                        }
                        .layout-config label {
                            min-width: auto;
                        }
                        .monitor-url code {
                            max-width: none;
                        }
                    }
                ');

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
                    '1.1.0',
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
         * Check if vendor has any enabled monitors
         */
        public function is_vendor_enabled($user_id)
        {
            $monitors = $this->db_manager->get_vendor_monitors($user_id, true);
            return !empty($monitors);
        }

        /**
         * Check if specific monitor is enabled
         */
        public function is_monitor_enabled($monitor_id)
        {
            $monitor = $this->db_manager->get_monitor($monitor_id);
            return $monitor && $monitor['is_enabled'] == 1;
        }

        /**
         * Create default monitor for vendor (used in admin)
         */
        public function create_default_monitor($user_id)
        {
            $vendor = dokan()->vendor->get($user_id);
            $shop_name = $vendor->get_shop_name();
            
            $monitor_name = "Monitor Principale - " . $shop_name;
            
            return $this->db_manager->create_monitor($user_id, $monitor_name, 'Monitor principale per la visualizzazione di manifesti');
        }

        /**
         * Enable/disable specific monitor
         */
        public function set_monitor_enabled($monitor_id, $enabled = true)
        {
            return $this->db_manager->update_monitor($monitor_id, ['is_enabled' => $enabled ? 1 : 0]);
        }

        /**
         * Get currently associated post for specific monitor
         */
        public function get_associated_post($monitor_id)
        {
            $monitor = $this->db_manager->get_monitor($monitor_id);
            return $monitor ? (int) $monitor['associated_post_id'] : 0;
        }

        /**
         * Get vendor monitors with associations
         */
        public function get_vendor_monitors($vendor_id, $enabled_only = false)
        {
            return $this->db_manager->get_vendor_monitors($vendor_id, $enabled_only);
        }

        /**
         * Associate post to specific monitor
         */
        public function associate_post($monitor_id, $post_id)
        {
            return $this->db_manager->update_monitor($monitor_id, [
                'associated_post_id' => $post_id,
                'last_access' => current_time('mysql')
            ]);
        }

        /**
         * Remove association from specific monitor
         */
        public function remove_association($monitor_id)
        {
            return $this->db_manager->update_monitor($monitor_id, [
                'associated_post_id' => null,
                'last_access' => current_time('mysql')
            ]);
        }

        // ===== NEW AJAX ENDPOINTS =====

        /**
         * AJAX: Get vendor monitors
         */
        public function ajax_get_vendor_monitors()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $monitors = $this->get_vendor_monitors($user_id);

            wp_send_json_success([
                'monitors' => $monitors,
                'count' => count($monitors)
            ]);
        }

        /**
         * AJAX: Create new monitor (Admin only)
         */
        public function ajax_create_monitor()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            $vendor_id = intval($_POST['vendor_id']);
            $monitor_name = sanitize_text_field($_POST['monitor_name']);
            $monitor_description = sanitize_textarea_field($_POST['monitor_description']);
            $layout_type = sanitize_text_field($_POST['layout_type']);

            $result = $this->db_manager->create_monitor($vendor_id, $monitor_name, $monitor_description, $layout_type);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Monitor creato con successo',
                    'monitor_id' => $result['monitor_id'],
                    'monitor_slug' => $result['monitor_slug']
                ]);
            } else {
                wp_send_json_error('Errore nella creazione del monitor: ' . $result['error']);
            }
        }

        /**
         * AJAX: Update monitor configuration
         */
        public function ajax_update_monitor()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $monitor_id = intval($_POST['monitor_id']);
            $user_id = get_current_user_id();
            
            // Verify monitor belongs to current user (unless admin)
            if (!current_user_can('manage_options')) {
                $monitor = $this->db_manager->get_monitor($monitor_id);
                if (!$monitor || $monitor['vendor_id'] != $user_id) {
                    wp_send_json_error('Monitor non trovato o accesso negato');
                }
            }

            $update_data = [];
            
            if (isset($_POST['layout_type'])) {
                $update_data['layout_type'] = sanitize_text_field($_POST['layout_type']);
            }
            
            if (isset($_POST['layout_config'])) {
                $layout_config = $_POST['layout_config'];
                if (is_array($layout_config)) {
                    // Sanitize layout config based on layout type
                    $sanitized_config = $this->sanitize_layout_config($layout_config, $update_data['layout_type'] ?? '');
                    $update_data['layout_config'] = $sanitized_config;
                }
            }

            $result = $this->db_manager->update_monitor($monitor_id, $update_data);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Monitor aggiornato con successo',
                    'affected_rows' => $result['affected_rows']
                ]);
            } else {
                wp_send_json_error('Errore nell\'aggiornamento del monitor');
            }
        }

        /**
         * AJAX: Toggle monitor enable/disable (Admin only)
         */
        public function ajax_toggle_monitor()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            $monitor_id = intval($_POST['monitor_id']);
            $enabled = $_POST['is_enabled'] === '1';

            $result = $this->set_monitor_enabled($monitor_id, $enabled);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => $enabled ? 'Monitor abilitato' : 'Monitor disabilitato',
                    'monitor_id' => $monitor_id,
                    'enabled' => $enabled
                ]);
            } else {
                wp_send_json_error('Errore nell\'aggiornamento del monitor');
            }
        }

        /**
         * AJAX: Delete monitor (Admin only)
         */
        public function ajax_delete_monitor()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            $monitor_id = intval($_POST['monitor_id']);
            $result = $this->db_manager->delete_monitor($monitor_id);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Monitor eliminato con successo',
                    'monitor_id' => $monitor_id
                ]);
            } else {
                wp_send_json_error('Errore nell\'eliminazione del monitor');
            }
        }

        // ===== UPDATED AJAX ENDPOINTS =====

        /**
         * AJAX: Associate defunto to monitor (Updated for specific monitor)
         */
        public function ajax_associate_defunto()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $post_id = intval($_POST['post_id']);
            $monitor_id = intval($_POST['monitor_id']);

            // Verify monitor belongs to user
            $monitor = $this->db_manager->get_monitor($monitor_id);
            if (!$monitor || $monitor['vendor_id'] != $user_id) {
                wp_send_json_error('Monitor non trovato o accesso negato');
            }

            if (!$this->is_monitor_enabled($monitor_id)) {
                wp_send_json_error('Monitor non abilitato');
            }

            // Verify post belongs to vendor
            $post = get_post($post_id);
            if (!$post || $post->post_author != $user_id || $post->post_type !== 'annuncio-di-morte') {
                wp_send_json_error('Post non valido o accesso negato');
            }

            $result = $this->associate_post($monitor_id, $post_id);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Defunto associato al monitor con successo',
                    'post_id' => $post_id,
                    'monitor_id' => $monitor_id
                ]);
            } else {
                wp_send_json_error('Errore nell\'associazione');
            }
        }

        /**
         * AJAX: Remove association (Updated for specific monitor)
         */
        public function ajax_remove_association()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $monitor_id = intval($_POST['monitor_id']);

            // Verify monitor belongs to user
            $monitor = $this->db_manager->get_monitor($monitor_id);
            if (!$monitor || $monitor['vendor_id'] != $user_id) {
                wp_send_json_error('Monitor non trovato o accesso negato');
            }

            $result = $this->remove_association($monitor_id);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Associazione rimossa con successo',
                    'monitor_id' => $monitor_id
                ]);
            } else {
                wp_send_json_error('Errore nella rimozione dell\'associazione');
            }
        }

        /**
         * AJAX: Get vendor's defunti list (Updated for monitor selection)
         */
        public function ajax_get_defunti()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $monitor_id = isset($_POST['monitor_id']) ? intval($_POST['monitor_id']) : 0;

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
            
            // Get associated post for specific monitor
            $associated_post = 0;
            if ($monitor_id > 0) {
                $associated_post = $this->get_associated_post($monitor_id);
            }

            $defunti = array();
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $foto_defunto = get_field('fotografia', $post_id);
                    $data_morte = get_field('data_di_morte', $post_id);
                    
                    $defunti[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'foto' => $foto_defunto ? (is_array($foto_defunto) ? $foto_defunto['sizes']['thumbnail'] : $foto_defunto) : '',
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
                'current_page' => $page,
                'monitor_id' => $monitor_id
            ));
        }

        // ===== NEW LAYOUT ENDPOINTS =====

        /**
         * AJAX: Get solo annuncio data for monitor display
         */
        public function ajax_get_solo_annuncio()
        {
            $monitor_id = intval($_POST['monitor_id']);
            $vendor_id = intval($_POST['vendor_id']);

            if (!$monitor_id || !$vendor_id) {
                wp_send_json_error('Parametri mancanti');
            }

            // Verify monitor exists and is enabled
            $monitor = $this->db_manager->get_monitor($monitor_id);
            if (!$monitor || $monitor['vendor_id'] != $vendor_id || !$monitor['is_enabled']) {
                wp_send_json_error('Monitor non valido o disabilitato');
            }

            $associated_post_id = $monitor['associated_post_id'];
            if (!$associated_post_id) {
                wp_send_json_error('Nessun defunto associato');
            }

            // Get post data
            $post = get_post($associated_post_id);
            if (!$post || $post->post_type !== 'annuncio-di-morte') {
                wp_send_json_error('Defunto non valido');
            }

            $defunto_data = [
                'id' => $associated_post_id,
                'title' => get_the_title($associated_post_id),
                'nome' => get_field('nome', $associated_post_id),
                'cognome' => get_field('cognome', $associated_post_id),
                'info' => get_field('info', $associated_post_id),
                'eta' => get_field('eta', $associated_post_id),
                'fotografia' => get_field('fotografia', $associated_post_id),
                'immagine_annuncio_di_morte' => get_field('immagine_annuncio_di_morte', $associated_post_id),
                'data_di_morte' => get_field('data_di_morte', $associated_post_id)
            ];

            wp_send_json_success([
                'defunto' => $defunto_data,
                'last_update' => current_time('H:i:s')
            ]);
        }

        /**
         * AJAX: Get città multi-agenzia data for monitor display
         */
        public function ajax_get_citta_multi()
        {
            $monitor_id = intval($_POST['monitor_id']);
            $vendor_id = intval($_POST['vendor_id']);

            if (!$monitor_id || !$vendor_id) {
                wp_send_json_error('Parametri mancanti');
            }

            // Verify monitor exists and is enabled
            $monitor = $this->db_manager->get_monitor($monitor_id);
            if (!$monitor || $monitor['vendor_id'] != $vendor_id || !$monitor['is_enabled']) {
                wp_send_json_error('Monitor non valido o disabilitato');
            }

            // Get layout configuration
            $layout_config = $monitor['layout_config'];
            $days_range = isset($layout_config['days_range']) ? intval($layout_config['days_range']) : 7;
            $show_all_agencies = isset($layout_config['show_all_agencies']) ? $layout_config['show_all_agencies'] : false;

            // Get vendor city
            $store_info = dokan_get_store_info($vendor_id);
            $user_city = $store_info['address']['city'] ?? '';

            if (empty($user_city)) {
                wp_send_json_error('Città del vendor non configurata');
            }

            // Build query for annunci di morte
            $date_from = date('Y-m-d H:i:s', strtotime("-$days_range days"));
            
            $meta_query = [
                [
                    'key' => 'citta',
                    'value' => $user_city,
                    'compare' => '='
                ]
            ];

            $args = [
                'post_type' => 'annuncio-di-morte',
                'posts_per_page' => 50,
                'date_query' => [
                    [
                        'after' => $date_from,
                        'inclusive' => true,
                    ]
                ],
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => $meta_query
            ];

            // If not showing all agencies, restrict to current vendor
            if (!$show_all_agencies) {
                $args['author'] = $vendor_id;
            }

            $query = new \WP_Query($args);
            $annunci = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $author_id = get_the_author_meta('ID');
                    
                    // Get vendor info
                    $vendor = dokan()->vendor->get($author_id);
                    
                    $annunci[] = [
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'nome' => get_field('nome', $post_id),
                        'cognome' => get_field('cognome', $post_id),
                        'eta' => get_field('eta', $post_id),
                        'fotografia' => get_field('fotografia', $post_id),
                        'immagine_annuncio_di_morte' => get_field('immagine_annuncio_di_morte', $post_id),
                        'testo_annuncio_di_morte' => get_field('testo_annuncio_di_morte', $post_id),
                        'data_di_morte' => get_field('data_di_morte', $post_id),
                        'data_pubblicazione' => get_the_date('Y-m-d H:i:s'),
                        'agenzia_nome' => $vendor->get_shop_name(),
                        'is_own_vendor' => ($author_id == $vendor_id)
                    ];
                }
            }
            wp_reset_postdata();

            wp_send_json_success([
                'annunci' => $annunci,
                'count' => count($annunci),
                'city' => $user_city,
                'days_range' => $days_range,
                'show_all_agencies' => $show_all_agencies,
                'last_update' => current_time('H:i:s')
            ]);
        }

        // ===== EXISTING AJAX ENDPOINTS (Updated) =====

        /**
         * AJAX: Get manifesti for monitor display (Updated for new structure)
         */
        public function ajax_get_manifesti()
        {
            $vendor_id = intval($_POST['vendor_id']);
            $post_id = intval($_POST['post_id']);

            if (!$vendor_id || !$post_id) {
                wp_send_json_error('Parametri mancanti');
            }

            // Verify post belongs to vendor and is valid
            $post = get_post($post_id);
            if (!$post || $post->post_author != $vendor_id || $post->post_type !== 'annuncio-di-morte') {
                wp_send_json_error('Post non valido o accesso negato');
            }

            $associated_post_id = $post_id;

            // Load manifesti using ManifestiLoader
            $loader = new ManifestiLoader($associated_post_id, 0, 'top,silver,online');
            $manifesti = $loader->load_manifesti_for_monitor();

            wp_send_json_success(array(
                'manifesti' => $manifesti,
                'count' => count($manifesti),
                'last_update' => current_time('H:i:s')
            ));
        }

        /**
         * AJAX: Check if association has changed (Updated for new structure)
         */
        public function ajax_check_association()
        {
            $vendor_id = intval($_POST['vendor_id']);
            $current_post_id = intval($_POST['current_post_id']);

            if (!$vendor_id || !$current_post_id) {
                wp_send_json_error('Parametri mancanti');
            }

            // For manifesti layout, the association doesn't change - just confirm the post still exists
            $post = get_post($current_post_id);
            if (!$post || $post->post_author != $vendor_id || $post->post_type !== 'annuncio-di-morte') {
                wp_send_json_success(array(
                    'changed' => true,
                    'new_post_id' => null,
                    'redirect_to_waiting' => true
                ));
            } else {
                wp_send_json_success(array(
                    'changed' => false,
                    'last_check' => current_time('H:i:s')
                ));
            }
        }

        // ===== UTILITY METHODS =====

        /**
         * Sanitize layout configuration based on layout type
         */
        private function sanitize_layout_config($config, $layout_type)
        {
            $sanitized = [];

            switch ($layout_type) {
                case 'citta_multi':
                    $sanitized['days_range'] = isset($config['days_range']) ? intval($config['days_range']) : 7;
                    $sanitized['show_all_agencies'] = isset($config['show_all_agencies']) ? (bool) $config['show_all_agencies'] : false;
                    break;
                case 'solo_annuncio':
                case 'manifesti':
                default:
                    // No specific configuration needed
                    $sanitized = is_array($config) ? $config : [];
                    break;
            }

            return $sanitized;
        }

        /**
         * Get monitor display URL for specific monitor
         */
        public function get_monitor_display_url($vendor_id, $monitor_id, $monitor_slug)
        {
            return home_url("/monitor/display/{$vendor_id}/{$monitor_id}/{$monitor_slug}");
        }

        /**
         * Handle migration requests
         */
        public function handle_migration_requests()
        {
            if (isset($_GET['dkmod_migrate_monitors'])) {
                $this->db_manager->handle_migration_request();
            }
        }

        /**
         * Get monitor statistics for admin
         */
        public function get_monitor_stats()
        {
            return $this->db_manager->get_monitor_stats();
        }
        
        // ===== PREVIEW SYSTEM METHODS =====
        
        /**
         * AJAX: Generate layout preview for vendor dashboard
         */
        public function ajax_preview_layout()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_vendor_nonce')) {
                wp_send_json_error('Invalid nonce');
            }
            
            $user_id = get_current_user_id();
            $monitor_id = intval($_POST['monitor_id']);
            $preview_size = sanitize_text_field($_POST['preview_size'] ?? 'desktop');
            
            // Verify monitor belongs to user
            $monitor = $this->db_manager->get_monitor($monitor_id);
            if (!$monitor || $monitor['vendor_id'] != $user_id) {
                wp_send_json_error('Monitor non trovato o accesso negato');
            }
            
            $preview_data = $this->generate_preview_data($monitor);
            
            if ($preview_data) {
                wp_send_json_success([
                    'html' => $preview_data['html'],
                    'css' => $preview_data['css'],
                    'preview_size' => $preview_size,
                    'layout_type' => $monitor['layout_type'],
                    'monitor_name' => $monitor['monitor_name']
                ]);
            } else {
                wp_send_json_error('Errore nella generazione dell\'anteprima');
            }
        }
        
        /**
         * Generate preview data for a monitor layout
         */
        private function generate_preview_data($monitor)
        {
            $layout_type = $monitor['layout_type'];
            $layout_config = $this->safe_decode_layout_config($monitor['layout_config'] ?? '{}');
            
            // Get preview data based on layout type
            switch ($layout_type) {
                case 'manifesti':
                    return $this->generate_manifesti_preview($monitor);
                case 'solo_annuncio':
                    return $this->generate_solo_annuncio_preview($monitor);
                case 'citta_multi':
                    return $this->generate_citta_multi_preview($monitor, $layout_config);
                default:
                    return false;
            }
        }
        
        /**
         * Generate manifesti layout preview
         */
        private function generate_manifesti_preview($monitor)
        {
            // Use associated defunto or generate sample data
            $defunto_data = $this->get_preview_defunto_data($monitor);
            
            ob_start();
            ?>
            <div class="monitor-preview-container manifesti-preview">
                <header class="monitor-header">
                    <div class="defunto-info">
                        <?php if ($defunto_data['foto']): ?>
                            <img src="<?php echo esc_url($defunto_data['foto']); ?>" 
                                 alt="<?php echo esc_attr($defunto_data['nome'] . ' ' . $defunto_data['cognome']); ?>" 
                                 class="defunto-foto">
                        <?php else: ?>
                            <div class="defunto-foto-placeholder">
                                <i class="dashicons dashicons-admin-users"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="defunto-details">
                            <h1><?php echo esc_html($defunto_data['nome'] . ' ' . $defunto_data['cognome']); ?></h1>
                            <p><?php echo esc_html($defunto_data['data_morte']); ?></p>
                        </div>
                    </div>
                </header>
                
                <main class="monitor-body">
                    <div class="manifesti-slideshow">
                        <div class="manifesto-slide active">
                            <div class="manifesto-preview">
                                <div class="manifesto-content">
                                    <h2>Anteprima Manifesti</h2>
                                    <p>I manifesti verranno visualizzati qui in sequenza automatica</p>
                                    <div class="manifesti-info">
                                        <div class="info-item">📰 Manifesti caricati automaticamente</div>
                                        <div class="info-item">🔄 Rotazione automatica ogni 10 secondi</div>
                                        <div class="info-item">📱 Layout responsive</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <?php
            $html = ob_get_clean();
            
            return [
                'html' => $html,
                'css' => $this->get_manifesti_preview_css()
            ];
        }
        
        /**
         * Generate solo annuncio layout preview
         */
        private function generate_solo_annuncio_preview($monitor)
        {
            $defunto_data = $this->get_preview_defunto_data($monitor);
            
            ob_start();
            ?>
            <div class="monitor-preview-container solo-annuncio-preview">
                <header class="solo-annuncio-header">
                    <div class="defunto-header-info">
                        <?php if ($defunto_data['foto']): ?>
                            <div class="defunto-foto-container">
                                <img src="<?php echo esc_url($defunto_data['foto']); ?>" 
                                     alt="<?php echo esc_attr($defunto_data['nome'] . ' ' . $defunto_data['cognome']); ?>" 
                                     class="defunto-foto-solo">
                            </div>
                        <?php else: ?>
                            <div class="defunto-foto-placeholder">
                                <i class="dashicons dashicons-admin-users"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="defunto-nome-principale">
                            <h1><?php echo esc_html($defunto_data['nome'] . ' ' . $defunto_data['cognome']); ?></h1>
                            <?php if ($defunto_data['eta']): ?>
                                <div class="defunto-eta">Anni <?php echo esc_html($defunto_data['eta']); ?></div>
                            <?php endif; ?>
                            <div class="defunto-data-morte"><?php echo esc_html($defunto_data['data_morte']); ?></div>
                        </div>
                    </div>
                </header>
                
                <main class="solo-annuncio-body">
                    <?php if ($defunto_data['immagine_annuncio']): ?>
                        <div class="annuncio-principale">
                            <img src="<?php echo esc_url($defunto_data['immagine_annuncio']); ?>" 
                                 alt="Annuncio di morte" 
                                 class="annuncio-immagine">
                        </div>
                    <?php else: ?>
                        <div class="annuncio-placeholder">
                            <div class="placeholder-content">
                                <h2>Annuncio di Morte</h2>
                                <p><?php echo esc_html($defunto_data['nome'] . ' ' . $defunto_data['cognome']); ?></p>
                                <div class="annuncio-info">
                                    <div class="info-line">Età: <?php echo esc_html($defunto_data['eta'] ?? 'Non specificata'); ?> anni</div>
                                    <div class="info-line">Data: <?php echo esc_html($defunto_data['data_morte']); ?></div>
                                    <?php if ($defunto_data['info']): ?>
                                        <div class="info-line"><?php echo esc_html($defunto_data['info']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
            <?php
            $html = ob_get_clean();
            
            return [
                'html' => $html,
                'css' => $this->get_solo_annuncio_preview_css()
            ];
        }
        
        /**
         * Generate città multi-agenzia layout preview
         */
        private function generate_citta_multi_preview($monitor, $layout_config)
        {
            $days_range = $layout_config['days_range'] ?? 7;
            $show_all_agencies = $layout_config['show_all_agencies'] ?? false;
            
            // Get vendor city
            $vendor_id = $monitor['vendor_id'];
            $store_info = dokan_get_store_info($vendor_id);
            $user_city = $store_info['address']['city'] ?? 'Città Non Configurata';
            
            // Generate sample data or get real data
            $sample_annunci = $this->get_sample_citta_multi_data($vendor_id, $user_city, $days_range, $show_all_agencies);
            
            ob_start();
            ?>
            <div class="monitor-preview-container citta-multi-preview">
                <header class="citta-multi-header">
                    <div class="city-info">
                        <h1>Annunci di Morte - <?php echo esc_html($user_city); ?></h1>
                        <div class="config-info">
                            <span class="config-item">📅 Ultimi <?php echo $days_range; ?> giorni</span>
                            <span class="config-item">
                                <?php echo $show_all_agencies ? '🏢 Tutte le agenzie' : '🏢 Solo la tua agenzia'; ?>
                            </span>
                        </div>
                    </div>
                </header>
                
                <main class="citta-multi-body">
                    <div class="annunci-grid">
                        <?php foreach ($sample_annunci as $annuncio): ?>
                            <div class="annuncio-card <?php echo $annuncio['is_own_vendor'] ? 'own-vendor' : 'other-vendor'; ?>">
                                <div class="annuncio-foto">
                                    <?php if ($annuncio['foto']): ?>
                                        <img src="<?php echo esc_url($annuncio['foto']); ?>" 
                                             alt="<?php echo esc_attr($annuncio['nome'] . ' ' . $annuncio['cognome']); ?>">
                                    <?php else: ?>
                                        <div class="foto-placeholder">
                                            <i class="dashicons dashicons-admin-users"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="annuncio-info">
                                    <h3><?php echo esc_html($annuncio['nome'] . ' ' . $annuncio['cognome']); ?></h3>
                                    <div class="annuncio-eta">Anni <?php echo esc_html($annuncio['eta'] ?? 'N/A'); ?></div>
                                    <div class="annuncio-data"><?php echo esc_html($annuncio['data_morte']); ?></div>
                                    <div class="agenzia-nome"><?php echo esc_html($annuncio['agenzia_nome']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($sample_annunci)): ?>
                        <div class="no-annunci-message">
                            <h3>Nessun annuncio trovato</h3>
                            <p>Non ci sono annunci di morte per <?php echo esc_html($user_city); ?> negli ultimi <?php echo $days_range; ?> giorni.</p>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
            <?php
            $html = ob_get_clean();
            
            return [
                'html' => $html,
                'css' => $this->get_citta_multi_preview_css()
            ];
        }
        
        /**
         * Get preview defunto data (real or sample)
         */
        private function get_preview_defunto_data($monitor)
        {
            // If monitor has associated defunto, use real data
            if ($monitor['associated_post_id']) {
                $post_id = $monitor['associated_post_id'];
                return [
                    'nome' => get_field('nome', $post_id) ?: 'Mario',
                    'cognome' => get_field('cognome', $post_id) ?: 'Rossi',
                    'eta' => get_field('eta', $post_id) ?: '75',
                    'foto' => $this->get_field_url('fotografia', $post_id),
                    'immagine_annuncio' => $this->get_field_url('immagine_annuncio_di_morte', $post_id),
                    'data_morte' => date('d/m/Y', strtotime(get_field('data_di_morte', $post_id) ?: 'now')),
                    'info' => get_field('info', $post_id) ?: 'Informazioni aggiuntive sul defunto'
                ];
            }
            
            // Generate sample data
            return $this->generate_sample_defunto_data();
        }
        
        /**
         * Generate sample defunto data for preview
         */
        private function generate_sample_defunto_data()
        {
            $sample_names = [
                ['Mario', 'Rossi', '75'],
                ['Anna', 'Bianchi', '82'],
                ['Giuseppe', 'Verdi', '68'],
                ['Maria', 'Neri', '79'],
                ['Francesco', 'Russo', '71']
            ];
            
            $random_person = $sample_names[array_rand($sample_names)];
            
            return [
                'nome' => $random_person[0],
                'cognome' => $random_person[1],
                'eta' => $random_person[2],
                'foto' => null, // No sample photo
                'immagine_annuncio' => null, // No sample announcement image
                'data_morte' => date('d/m/Y', strtotime('-' . rand(1, 7) . ' days')),
                'info' => 'Esempio di informazioni aggiuntive per l\'anteprima del layout'
            ];
        }
        
        /**
         * Get sample città multi data
         */
        private function get_sample_citta_multi_data($vendor_id, $city, $days_range, $show_all_agencies)
        {
            // Try to get real data first
            $args = [
                'post_type' => 'annuncio-di-morte',
                'posts_per_page' => 6,
                'date_query' => [
                    [
                        'after' => date('Y-m-d H:i:s', strtotime("-$days_range days")),
                        'inclusive' => true,
                    ]
                ],
                'meta_query' => [
                    [
                        'key' => 'citta',
                        'value' => $city,
                        'compare' => '='
                    ]
                ]
            ];
            
            if (!$show_all_agencies) {
                $args['author'] = $vendor_id;
            }
            
            $query = new \WP_Query($args);
            $annunci = [];
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $author_id = get_the_author_meta('ID');
                    $vendor = dokan()->vendor->get($author_id);
                    
                    $annunci[] = [
                        'nome' => get_field('nome', $post_id) ?: 'Nome',
                        'cognome' => get_field('cognome', $post_id) ?: 'Cognome',
                        'eta' => get_field('eta', $post_id) ?: rand(60, 90),
                        'foto' => $this->get_field_url('fotografia', $post_id),
                        'data_morte' => date('d/m/Y', strtotime(get_field('data_di_morte', $post_id) ?: get_the_date())),
                        'agenzia_nome' => $vendor->get_shop_name(),
                        'is_own_vendor' => ($author_id == $vendor_id)
                    ];
                }
            }
            wp_reset_postdata();
            
            // If no real data or need more for preview, add sample data
            if (count($annunci) < 3) {
                $vendor = dokan()->vendor->get($vendor_id);
                $shop_name = $vendor->get_shop_name();
                
                $sample_agencies = $show_all_agencies ? 
                    ['Onoranze Funebri Rossi', 'Funeral Service Bianchi', $shop_name, 'Pompe Funebri Verdi'] :
                    [$shop_name];
                
                $sample_names = [
                    ['Mario', 'Rossi'], ['Anna', 'Bianchi'], ['Giuseppe', 'Verdi'],
                    ['Maria', 'Neri'], ['Francesco', 'Russo'], ['Elena', 'Ferrari']
                ];
                
                for ($i = count($annunci); $i < 6; $i++) {
                    $name = $sample_names[array_rand($sample_names)];
                    $agenzia = $sample_agencies[array_rand($sample_agencies)];
                    
                    $annunci[] = [
                        'nome' => $name[0],
                        'cognome' => $name[1],
                        'eta' => rand(60, 90),
                        'foto' => null,
                        'data_morte' => date('d/m/Y', strtotime('-' . rand(0, $days_range) . ' days')),
                        'agenzia_nome' => $agenzia,
                        'is_own_vendor' => ($agenzia === $shop_name)
                    ];
                }
            }
            
            return array_slice($annunci, 0, 6);
        }
        
        /**
         * Helper to get ACF field URL safely
         */
        private function get_field_url($field_name, $post_id)
        {
            $field = get_field($field_name, $post_id);
            if ($field) {
                if (is_array($field) && isset($field['url'])) {
                    return $field['url'];
                }
                if (is_string($field)) {
                    return $field;
                }
            }
            return null;
        }
        
        /**
         * Get CSS for manifesti preview
         */
        private function get_manifesti_preview_css()
        {
            return "
                .manifesti-preview .monitor-header { 
                    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
                    color: white; padding: 20px; 
                }
                .manifesti-preview .defunto-info { 
                    display: flex; align-items: center; gap: 20px; 
                }
                .manifesti-preview .defunto-foto, .defunto-foto-placeholder { 
                    width: 80px; height: 80px; border-radius: 50%; 
                    object-fit: cover; background: rgba(255,255,255,0.2); 
                    display: flex; align-items: center; justify-content: center; 
                }
                .manifesti-preview .manifesto-preview { 
                    background: #f8f9fa; padding: 40px; text-align: center; 
                    border: 2px dashed #dee2e6; margin: 20px; 
                }
                .manifesti-preview .manifesti-info { 
                    display: flex; flex-direction: column; gap: 10px; margin-top: 20px; 
                }
                .manifesti-preview .info-item { 
                    padding: 8px 12px; background: #e9ecef; border-radius: 6px; 
                }
            ";
        }
        
        /**
         * Get CSS for solo annuncio preview
         */
        private function get_solo_annuncio_preview_css()
        {
            return "
                .solo-annuncio-preview .solo-annuncio-header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; padding: 30px; text-align: center; 
                }
                .solo-annuncio-preview .defunto-foto-container { 
                    margin-bottom: 20px; 
                }
                .solo-annuncio-preview .defunto-foto-solo, .defunto-foto-placeholder { 
                    width: 120px; height: 120px; border-radius: 50%; 
                    object-fit: cover; margin: 0 auto; display: block; 
                    background: rgba(255,255,255,0.2); 
                }
                .solo-annuncio-preview .annuncio-placeholder { 
                    padding: 60px; text-align: center; background: #f8f9fa; 
                    margin: 20px; border: 2px dashed #dee2e6; 
                }
                .solo-annuncio-preview .annuncio-info { 
                    display: flex; flex-direction: column; gap: 8px; margin-top: 20px; 
                }
                .solo-annuncio-preview .info-line { 
                    padding: 6px 12px; background: #e9ecef; border-radius: 4px; 
                }
            ";
        }
        
        /**
         * Get CSS for città multi preview
         */
        private function get_citta_multi_preview_css()
        {
            return "
                .citta-multi-preview .citta-multi-header { 
                    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); 
                    color: white; padding: 25px; text-align: center; 
                }
                .citta-multi-preview .config-info { 
                    display: flex; justify-content: center; gap: 20px; margin-top: 10px; 
                }
                .citta-multi-preview .config-item { 
                    background: rgba(255,255,255,0.2); padding: 4px 12px; 
                    border-radius: 12px; font-size: 14px; 
                }
                .citta-multi-preview .annunci-grid { 
                    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
                    gap: 20px; padding: 20px; 
                }
                .citta-multi-preview .annuncio-card { 
                    background: white; border-radius: 8px; padding: 15px; 
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; 
                }
                .citta-multi-preview .annuncio-card.own-vendor { 
                    border-left: 4px solid #007cba; 
                }
                .citta-multi-preview .annuncio-foto img, .foto-placeholder { 
                    width: 60px; height: 60px; border-radius: 50%; 
                    object-fit: cover; margin-bottom: 10px; 
                    background: #f0f0f0; display: flex; align-items: center; 
                    justify-content: center; margin: 0 auto 10px; 
                }
                .citta-multi-preview .agenzia-nome { 
                    font-size: 12px; color: #666; margin-top: 5px; 
                }
            ";
        }

        // ===== ADMIN-ONLY AJAX FUNCTIONS =====

        /**
         * AJAX: Get all vendors for admin interface
         */
        public function ajax_get_vendors()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            $vendors = get_users([
                'role' => 'seller',
                'orderby' => 'display_name',
                'order' => 'ASC'
            ]);

            $vendor_data = [];
            foreach ($vendors as $vendor) {
                $vendor_obj = dokan()->vendor->get($vendor->ID);
                $shop_name = $vendor_obj->get_shop_name();
                
                $vendor_data[] = [
                    'ID' => $vendor->ID,
                    'display_name' => $vendor->display_name,
                    'shop_name' => $shop_name
                ];
            }

            wp_send_json_success([
                'vendors' => $vendor_data,
                'count' => count($vendor_data)
            ]);
        }

        /**
         * AJAX: Search vendors for admin interface (enhanced search)
         */
        public function ajax_search_vendors()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            $search_term = sanitize_text_field($_POST['search'] ?? '');
            
            // Require at least 2 characters
            if (strlen($search_term) < 2) {
                wp_send_json_success([
                    'vendors' => [],
                    'message' => 'Inserisci almeno 2 caratteri per la ricerca'
                ]);
                return;
            }

            // Search vendors by display_name, user_login, and user_email
            $vendors = get_users([
                'role' => 'seller',
                'search' => '*' . $search_term . '*',
                'search_columns' => ['display_name', 'user_login', 'user_email'],
                'number' => 20, // Limit results for performance
                'orderby' => 'display_name',
                'order' => 'ASC'
            ]);

            $vendor_data = [];
            foreach ($vendors as $vendor) {
                $vendor_obj = dokan()->vendor->get($vendor->ID);
                $shop_name = $vendor_obj->get_shop_name();
                
                // Additional matching on shop name if not found in user fields
                $matches_shop = stripos($shop_name, $search_term) !== false;
                $matches_user = stripos($vendor->display_name, $search_term) !== false ||
                               stripos($vendor->user_login, $search_term) !== false ||
                               stripos($vendor->user_email, $search_term) !== false;
                
                if ($matches_user || $matches_shop) {
                    $vendor_data[] = [
                        'ID' => $vendor->ID,
                        'display_name' => $vendor->display_name,
                        'user_login' => $vendor->user_login,
                        'user_email' => $vendor->user_email,
                        'shop_name' => $shop_name,
                        // For display purposes
                        'label' => sprintf('%s (%s) - %s', 
                            $vendor->display_name, 
                            $vendor->user_login, 
                            $shop_name
                        )
                    ];
                }
            }

            wp_send_json_success([
                'vendors' => $vendor_data,
                'count' => count($vendor_data)
            ]);
        }

        /**
         * AJAX: Get all monitors for admin table with filters and pagination
         */
        public function ajax_get_all_monitors()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            global $wpdb;
            $table_name = $this->db_manager->get_table_name();

            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
            $offset = ($page - 1) * $per_page;

            // Build WHERE clause based on filters
            $where_conditions = ['1=1'];
            $prepare_values = [];

            if (!empty($_POST['vendor_filter'])) {
                $where_conditions[] = 'mc.vendor_id = %d';
                $prepare_values[] = intval($_POST['vendor_filter']);
            }

            if (!empty($_POST['layout_filter'])) {
                $where_conditions[] = 'mc.layout_type = %s';
                $prepare_values[] = sanitize_text_field($_POST['layout_filter']);
            }

            if (!empty($_POST['status_filter'])) {
                switch ($_POST['status_filter']) {
                    case 'enabled':
                        $where_conditions[] = 'mc.is_enabled = 1';
                        break;
                    case 'disabled':
                        $where_conditions[] = 'mc.is_enabled = 0';
                        break;
                    case 'active':
                        $where_conditions[] = 'mc.is_enabled = 1 AND mc.associated_post_id IS NOT NULL';
                        break;
                }
            }

            $where_clause = implode(' AND ', $where_conditions);

            // Get total count
            $count_query = "SELECT COUNT(*) FROM {$table_name} mc WHERE {$where_clause}";
            if (!empty($prepare_values)) {
                $count_query = $wpdb->prepare($count_query, ...$prepare_values);
            }
            $total_monitors = $wpdb->get_var($count_query);

            // Get monitors with vendor information
            $query = "
                SELECT 
                    mc.*,
                    u.display_name as vendor_name,
                    u.user_email as vendor_email
                FROM {$table_name} mc 
                LEFT JOIN {$wpdb->users} u ON mc.vendor_id = u.ID
                WHERE {$where_clause}
                ORDER BY mc.created_at DESC
                LIMIT %d OFFSET %d
            ";

            $prepare_values[] = $per_page;
            $prepare_values[] = $offset;
            $monitors = $wpdb->get_results($wpdb->prepare($query, ...$prepare_values), ARRAY_A);

            // Process monitors data
            foreach ($monitors as &$monitor) {
                $monitor['layout_config'] = $this->safe_decode_layout_config($monitor['layout_config']);
            }

            // Get filter data (vendors and counts)
            $filter_data = [
                'vendors' => $wpdb->get_results("
                    SELECT DISTINCT mc.vendor_id, u.display_name as vendor_name
                    FROM {$table_name} mc 
                    LEFT JOIN {$wpdb->users} u ON mc.vendor_id = u.ID
                    ORDER BY u.display_name ASC
                ", ARRAY_A)
            ];

            $pagination = [
                'current_page' => $page,
                'total_pages' => ceil($total_monitors / $per_page),
                'per_page' => $per_page,
                'total_items' => $total_monitors
            ];

            wp_send_json_success([
                'monitors' => $monitors,
                'pagination' => $pagination,
                'filters' => $filter_data
            ]);
        }

        /**
         * AJAX: Get monitor details for editing
         */
        public function ajax_get_monitor_details()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_admin_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            $monitor_id = intval($_POST['monitor_id']);
            if (!$monitor_id) {
                wp_send_json_error('Monitor ID richiesto');
            }

            $monitor = $this->db_manager->get_monitor($monitor_id);
            if (!$monitor) {
                wp_send_json_error('Monitor non trovato');
            }

            // Get vendor information
            $vendor = get_userdata($monitor['vendor_id']);
            if ($vendor) {
                $vendor_obj = dokan()->vendor->get($vendor->ID);
                $monitor['vendor_name'] = $vendor->display_name;
                $monitor['shop_name'] = $vendor_obj->get_shop_name();
            }

            wp_send_json_success([
                'monitor' => $monitor
            ]);
        }

        /**
         * AJAX: Get defunti for association modal
         */
        public function ajax_get_defunti_for_association()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_association_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $monitor_id = isset($_POST['monitor_id']) ? intval($_POST['monitor_id']) : 0;
            $layout_type = isset($_POST['layout_type']) ? sanitize_text_field($_POST['layout_type']) : '';
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;

            // Verify monitor ownership
            $db_manager = \Dokan_Mods\MonitorDatabaseManager::get_instance();
            $monitor = $db_manager->get_monitor($monitor_id);
            
            if (!$monitor || $monitor['vendor_id'] != $user_id) {
                wp_send_json_error('Monitor not found or access denied');
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
            $defunti = array();

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    // Check if this defunto is associated with the current monitor
                    $is_associated = ($monitor['associated_post_id'] == $post_id);
                    
                    // Get defunto data
                    $foto_defunto = get_field('fotografia', $post_id);
                    $foto_url = '';
                    
                    if ($foto_defunto) {
                        if (is_array($foto_defunto) && isset($foto_defunto['sizes']['thumbnail'])) {
                            $foto_url = $foto_defunto['sizes']['thumbnail'];
                        } elseif (is_array($foto_defunto) && isset($foto_defunto['url'])) {
                            $foto_url = $foto_defunto['url'];
                        } elseif (is_string($foto_defunto)) {
                            $foto_url = $foto_defunto;
                        }
                    }

                    $data_morte = get_field('data_di_morte', $post_id);
                    
                    $defunti[] = array(
                        'id' => $post_id,
                        'nome' => get_the_title(),
                        'foto' => $foto_url,
                        'data_morte' => $data_morte ? date('d/m/Y', strtotime($data_morte)) : '',
                        'data_pubblicazione' => get_the_date('d/m/Y'),
                        'is_associated' => $is_associated
                    );
                }
                wp_reset_postdata();
            }

            wp_send_json_success(array(
                'defunti' => $defunti,
                'pagination' => array(
                    'current_page' => $page,
                    'total_pages' => $query->max_num_pages,
                    'total_items' => $query->found_posts
                )
            ));
        }

        /**
         * AJAX: Toggle defunto association with monitor
         */
        public function ajax_toggle_defunto_association()
        {
            // Ensure clean output buffer
            if (ob_get_level()) {
                ob_clean();
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'monitor_association_nonce')) {
                wp_send_json_error('Invalid nonce');
            }

            $user_id = get_current_user_id();
            $monitor_id = isset($_POST['monitor_id']) ? intval($_POST['monitor_id']) : 0;
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $action = isset($_POST['association_action']) ? sanitize_text_field($_POST['association_action']) : '';

            // Verify monitor ownership
            $db_manager = \Dokan_Mods\MonitorDatabaseManager::get_instance();
            $monitor = $db_manager->get_monitor($monitor_id);
            
            if (!$monitor || $monitor['vendor_id'] != $user_id) {
                wp_send_json_error('Monitor not found or access denied');
            }

            // Verify post ownership
            $post = get_post($post_id);
            if (!$post || $post->post_author != $user_id) {
                wp_send_json_error('Post not found or access denied');
            }

            // Verify layout type supports single associations
            if (!in_array($monitor['layout_type'], ['manifesti', 'solo_annuncio'])) {
                wp_send_json_error('This layout type does not support single defunto associations');
            }

            try {
                if ($action === 'add_defunto') {
                    // Add association using update_monitor
                    $result = $db_manager->update_monitor($monitor_id, [
                        'associated_post_id' => $post_id,
                        'last_access' => current_time('mysql')
                    ]);
                    if ($result['success']) {
                        wp_send_json_success('Defunto associato con successo al monitor');
                    } else {
                        wp_send_json_error('Errore durante l\'associazione del defunto');
                    }
                } elseif ($action === 'remove_defunto') {
                    // Remove association using update_monitor
                    $result = $db_manager->update_monitor($monitor_id, [
                        'associated_post_id' => null,
                        'last_access' => current_time('mysql')
                    ]);
                    if ($result['success']) {
                        wp_send_json_success('Associazione rimossa con successo');
                    } else {
                        wp_send_json_error('Errore durante la rimozione dell\'associazione');
                    }
                } else {
                    wp_send_json_error('Azione non valida');
                }
            } catch (Exception $e) {
                wp_send_json_error('Errore: ' . $e->getMessage());
            }
        }
    }
}