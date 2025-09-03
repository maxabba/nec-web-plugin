<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\MonitorDatabaseManager')) {
    class MonitorDatabaseManager
    {
        private static $instance = null;
        private $table_name;
        private $charset_collate;

        public function __construct()
        {
            global $wpdb;
            $this->table_name = $wpdb->prefix . 'dkmod_monitor_config';
            $this->charset_collate = $wpdb->get_charset_collate();
        }

        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Get table name
         */
        public function get_table_name()
        {
            return $this->table_name;
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

        /**
         * Check if table exists
         */
        public function table_exists()
        {
            global $wpdb;
            $table_name = $this->table_name;
            $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
            return $wpdb->get_var($query) === $table_name;
        }

        /**
         * Create monitor configuration table
         * FIXED: Removed foreign key constraint to avoid MySQL storage engine issues
         */
        public function create_table()
        {
            global $wpdb;

            if ($this->table_exists()) {
                return ['success' => false, 'message' => 'Tabella già esistente'];
            }

            // WordPress-compliant table creation without foreign key constraints
            // Foreign keys cause issues with mixed storage engines (MyISAM/InnoDB)
            $sql = "CREATE TABLE {$this->table_name} (
                id int(11) NOT NULL AUTO_INCREMENT,
                vendor_id bigint(20) unsigned NOT NULL,
                monitor_name varchar(255) NOT NULL,
                monitor_description text DEFAULT NULL,
                monitor_slug varchar(255) NOT NULL,
                layout_type enum('manifesti', 'solo_annuncio', 'citta_multi') NOT NULL DEFAULT 'manifesti',
                is_enabled tinyint(1) NOT NULL DEFAULT 1,
                associated_post_id bigint(20) unsigned DEFAULT NULL,
                layout_config longtext DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_access datetime DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY unique_monitor_slug (vendor_id, monitor_slug),
                INDEX idx_vendor_enabled (vendor_id, is_enabled),
                INDEX idx_layout_type (layout_type),
                INDEX idx_associated_post (associated_post_id),
                INDEX idx_vendor_id (vendor_id)
            ) {$this->charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Check if table was created successfully
            if ($this->table_exists()) {
                return ['success' => true, 'message' => 'Tabella creata con successo'];
            } else {
                return ['success' => false, 'message' => 'Errore nella creazione della tabella'];
            }
        }

        /**
         * Validate vendor_id exists in wp_users table
         * Replaces foreign key constraint with application-level validation
         */
        public function validate_vendor_id($vendor_id)
        {
            global $wpdb;
            $user_exists = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE ID = %d", $vendor_id)
            );
            return $user_exists > 0;
        }

        /**
         * Clean up orphaned records when vendor is deleted
         * Replaces CASCADE DELETE from foreign key with application-level cleanup
         */
        public function cleanup_orphaned_monitors($vendor_id)
        {
            global $wpdb;
            
            if (!$this->validate_vendor_id($vendor_id)) {
                // Vendor doesn't exist, remove their monitors
                $result = $wpdb->delete(
                    $this->table_name,
                    ['vendor_id' => $vendor_id],
                    ['%d']
                );
                
                return ['success' => true, 'removed_monitors' => $result];
            }
            
            return ['success' => true, 'removed_monitors' => 0];
        }

        /**
         * Migrate existing user_meta data to new table
         */
        public function migrate_existing_data()
        {
            global $wpdb;

            if (!$this->table_exists()) {
                return ['success' => false, 'message' => 'Tabella non esistente'];
            }

            // Get all vendors with monitor data
            $vendors = get_users([
                'role' => 'seller',
                'meta_query' => [
                    [
                        'key' => 'monitor_enabled',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);

            $migrated_count = 0;
            $errors = [];

            foreach ($vendors as $vendor) {
                $vendor_id = $vendor->ID;
                
                // Validate vendor exists (application-level foreign key check)
                if (!$this->validate_vendor_id($vendor_id)) {
                    $errors[] = "Vendor ID $vendor_id non trovato nella tabella users";
                    continue;
                }
                
                // Get existing meta data
                $monitor_enabled = get_user_meta($vendor_id, 'monitor_enabled', true);
                $monitor_url = get_user_meta($vendor_id, 'monitor_url', true);
                $associated_post = get_user_meta($vendor_id, 'monitor_associated_post', true);
                $last_access = get_user_meta($vendor_id, 'monitor_last_access', true);

                if ($monitor_enabled) {
                    // Generate default monitor name and slug
                    $vendor_obj = dokan()->vendor->get($vendor_id);
                    $shop_name = $vendor_obj->get_shop_name();
                    
                    $monitor_name = "Monitor Principale - " . $shop_name;
                    $monitor_slug = !empty($monitor_url) ? $monitor_url : sanitize_title($shop_name) . '-' . $vendor_id;

                    // Insert into new table
                    $result = $wpdb->insert(
                        $this->table_name,
                        [
                            'vendor_id' => $vendor_id,
                            'monitor_name' => $monitor_name,
                            'monitor_description' => 'Monitor migrato automaticamente dal sistema precedente',
                            'monitor_slug' => $monitor_slug,
                            'layout_type' => 'manifesti',
                            'is_enabled' => 1,
                            'associated_post_id' => !empty($associated_post) ? $associated_post : null,
                            'layout_config' => json_encode(['migrated' => true]),
                            'last_access' => !empty($last_access) ? $last_access : null
                        ],
                        ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
                    );

                    if ($result) {
                        $migrated_count++;
                        
                        // Clean up old meta data
                        delete_user_meta($vendor_id, 'monitor_enabled');
                        delete_user_meta($vendor_id, 'monitor_url');
                        delete_user_meta($vendor_id, 'monitor_associated_post');
                        delete_user_meta($vendor_id, 'monitor_last_access');
                    } else {
                        $errors[] = "Errore migrazione vendor ID: $vendor_id - " . $wpdb->last_error;
                    }
                }
            }

            return [
                'success' => true,
                'message' => "Migrati $migrated_count vendor",
                'migrated_count' => $migrated_count,
                'errors' => $errors
            ];
        }

        /**
         * Create new monitor configuration
         * ENHANCED: Added vendor validation
         */
        public function create_monitor($vendor_id, $monitor_name, $monitor_description = '', $layout_type = 'manifesti')
        {
            global $wpdb;

            // Validate vendor exists (application-level foreign key check)
            if (!$this->validate_vendor_id($vendor_id)) {
                return ['success' => false, 'error' => 'Vendor ID non valido o inesistente'];
            }

            // Generate unique slug
            $base_slug = sanitize_title($monitor_name);
            $monitor_slug = $this->generate_unique_slug($vendor_id, $base_slug);

            $result = $wpdb->insert(
                $this->table_name,
                [
                    'vendor_id' => $vendor_id,
                    'monitor_name' => $monitor_name,
                    'monitor_description' => $monitor_description,
                    'monitor_slug' => $monitor_slug,
                    'layout_type' => $layout_type,
                    'is_enabled' => 1,
                    'layout_config' => json_encode([])
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
            );

            if ($result) {
                return [
                    'success' => true,
                    'monitor_id' => $wpdb->insert_id,
                    'monitor_slug' => $monitor_slug
                ];
            }

            return ['success' => false, 'error' => $wpdb->last_error];
        }

        /**
         * Get monitor by ID
         */
        public function get_monitor($monitor_id)
        {
            global $wpdb;
            
            $result = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $monitor_id),
                ARRAY_A
            );

            if ($result) {
                $result['layout_config'] = $this->safe_decode_layout_config($result['layout_config']);
            }

            return $result;
        }

        /**
         * Get all monitors for a vendor
         */
        public function get_vendor_monitors($vendor_id, $enabled_only = false)
        {
            global $wpdb;

            $where_clause = "WHERE vendor_id = %d";
            $params = [$vendor_id];

            if ($enabled_only) {
                $where_clause .= " AND is_enabled = 1";
            }

            $results = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$this->table_name} $where_clause ORDER BY created_at DESC", ...$params),
                ARRAY_A
            );

            // Decode layout_config for each monitor
            foreach ($results as &$monitor) {
                $monitor['layout_config'] = $this->safe_decode_layout_config($monitor['layout_config']);
            }

            return $results;
        }

        /**
         * Get monitor by vendor and slug
         */
        public function get_monitor_by_slug($vendor_id, $monitor_slug)
        {
            global $wpdb;

            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE vendor_id = %d AND monitor_slug = %s",
                    $vendor_id,
                    $monitor_slug
                ),
                ARRAY_A
            );

            if ($result) {
                $result['layout_config'] = $this->safe_decode_layout_config($result['layout_config']);
            }

            return $result;
        }

        /**
         * Update monitor configuration
         * ENHANCED: Added vendor validation for security
         */
        public function update_monitor($monitor_id, $data)
        {
            global $wpdb;

            // If updating vendor_id, validate it exists
            if (isset($data['vendor_id']) && !$this->validate_vendor_id($data['vendor_id'])) {
                return ['success' => false, 'error' => 'Vendor ID non valido'];
            }

            // Prepare data for update
            $update_data = [];
            $formats = [];

            $allowed_fields = [
                'monitor_name' => '%s',
                'monitor_description' => '%s', 
                'layout_type' => '%s',
                'is_enabled' => '%d',
                'associated_post_id' => '%d',
                'layout_config' => '%s',
                'last_access' => '%s'
            ];

            foreach ($data as $key => $value) {
                if (array_key_exists($key, $allowed_fields)) {
                    if ($key === 'layout_config' && is_array($value)) {
                        $update_data[$key] = json_encode($value);
                    } else {
                        $update_data[$key] = $value;
                    }
                    $formats[] = $allowed_fields[$key];
                }
            }

            if (empty($update_data)) {
                return ['success' => false, 'error' => 'Nessun dato valido da aggiornare'];
            }

            $result = $wpdb->update(
                $this->table_name,
                $update_data,
                ['id' => $monitor_id],
                $formats,
                ['%d']
            );

            return ['success' => $result !== false, 'affected_rows' => $result];
        }

        /**
         * Delete monitor
         */
        public function delete_monitor($monitor_id)
        {
            global $wpdb;

            $result = $wpdb->delete(
                $this->table_name,
                ['id' => $monitor_id],
                ['%d']
            );

            return ['success' => $result !== false, 'affected_rows' => $result];
        }

        /**
         * Generate unique monitor slug for vendor
         */
        private function generate_unique_slug($vendor_id, $base_slug, $counter = 0)
        {
            global $wpdb;

            $slug = $counter > 0 ? $base_slug . '-' . $counter : $base_slug;

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE vendor_id = %d AND monitor_slug = %s",
                    $vendor_id,
                    $slug
                )
            );

            if ($exists > 0) {
                return $this->generate_unique_slug($vendor_id, $base_slug, $counter + 1);
            }

            return $slug;
        }

        /**
         * Get monitor statistics
         */
        public function get_monitor_stats()
        {
            global $wpdb;

            $stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_monitors,
                    COUNT(CASE WHEN is_enabled = 1 THEN 1 END) as enabled_monitors,
                    COUNT(CASE WHEN associated_post_id IS NOT NULL THEN 1 END) as active_monitors,
                    COUNT(DISTINCT vendor_id) as vendors_with_monitors
                FROM {$this->table_name}
            ", ARRAY_A);

            return $stats;
        }

        /**
         * Admin migration link handler
         */
        public function handle_migration_request()
        {
            if (!current_user_can('manage_options')) {
                wp_die('Non hai i permessi necessari');
            }

            if (!isset($_GET['dkmod_migrate_monitors']) || !wp_verify_nonce($_GET['_wpnonce'], 'dkmod_migrate_monitors')) {
                wp_die('Richiesta non valida');
            }

            $action = sanitize_text_field($_GET['dkmod_migrate_monitors']);

            switch ($action) {
                case 'create_table':
                    $result = $this->create_table();
                    break;
                case 'migrate_data':
                    $result = $this->migrate_existing_data();
                    break;
                default:
                    wp_die('Azione non riconosciuta');
            }

            // Redirect with result
            $redirect_url = admin_url('admin.php?page=dokan-monitor-digitale');
            if ($result['success']) {
                $redirect_url = add_query_arg('migration_success', urlencode($result['message']), $redirect_url);
            } else {
                $redirect_url = add_query_arg('migration_error', urlencode($result['message']), $redirect_url);
            }

            wp_redirect($redirect_url);
            exit;
        }
    }
}