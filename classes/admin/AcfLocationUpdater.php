<?php

namespace Dokan_Mods\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ACF Location Updater - Native WordPress/ACF implementation with AJAX
 * Updates città and provincia fields using WordPress and ACF native methods
 */
class AcfLocationUpdater
{
    private $start_time;
    private $post_types = ['annuncio-di-morte', 'manifesto', 'anniversario', 'trigesimo', 'ringraziamento'];
    private $batch_size = 50; // Reduced for AJAX processing
    private $processed_stats = [];
    private $errors = [];
    private $transient_key = 'acf_location_updater_state';
    
    public function __construct()
    {
        $this->start_time = microtime(true);
        
        // Register AJAX handlers
        add_action('wp_ajax_acf_updater_init', [$this, 'ajax_init']);
        add_action('wp_ajax_acf_updater_process', [$this, 'ajax_process']);
        add_action('wp_ajax_acf_updater_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_acf_updater_cleanup', [$this, 'ajax_cleanup']);
        
        // Enqueue scripts on admin page
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Enqueue scripts for AJAX interface
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'tools_page_acf-location-updater') {
            return;
        }
        
        wp_enqueue_script(
            'acf-location-updater',
            DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/acf-location-updater.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('acf-location-updater', 'acf_updater', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acf_location_updater'),
            'batch_size' => $this->batch_size
        ]);
        
        wp_enqueue_style(
            'acf-location-updater',
            DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/acf-location-updater.css',
            [],
            '1.0.0'
        );
    }
    
    /**
     * AJAX: Initialize the update process
     */
    public function ajax_init()
    {
        check_ajax_referer('acf_location_updater', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get initial statistics
        $stats = $this->getPreUpdateStatistics();
        
        // Count total items for each phase
        $phase_counts = [
            'phase1' => $this->countPhase1Items(),
            'phase2' => $this->countPhase2Items(),
            'phase3' => $this->countPhase3Items(),
            'phase4' => $this->countPhase4Items()
        ];
        
        // Initialize state
        $state = [
            'current_phase' => 1,
            'current_offset' => 0,
            'phase_counts' => $phase_counts,
            'processed' => [
                'phase1' => 0,
                'phase2' => 0,
                'phase3' => 0,
                'phase4' => 0
            ],
            'errors' => [],
            'start_time' => time(),
            'pre_stats' => $stats
        ];
        
        set_transient($this->transient_key, $state, HOUR_IN_SECONDS);
        
        wp_send_json_success([
            'state' => $state,
            'message' => 'Inizializzazione completata'
        ]);
    }
    
    /**
     * AJAX: Process a batch
     */
    public function ajax_process()
    {
        check_ajax_referer('acf_location_updater', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $state = get_transient($this->transient_key);
        if (!$state) {
            wp_send_json_error('State not found. Please reinitialize.');
        }
        
        $phase = isset($_POST['phase']) ? intval($_POST['phase']) : $state['current_phase'];
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : $state['current_offset'];
        
        // Process batch based on phase
        $result = [];
        switch ($phase) {
            case 1:
                $result = $this->processBatchPhase1($offset);
                break;
            case 2:
                $result = $this->processBatchPhase2($offset);
                break;
            case 3:
                $result = $this->processBatchPhase3($offset);
                break;
            case 4:
                $result = $this->processBatchPhase4($offset);
                break;
            default:
                wp_send_json_error('Invalid phase');
        }
        
        // Update state
        $state['current_phase'] = $phase;
        $state['current_offset'] = $offset + $result['processed'];
        $state['processed']['phase' . $phase] += $result['processed'];
        
        if (!empty($result['errors'])) {
            $state['errors'] = array_merge($state['errors'], $result['errors']);
        }
        
        set_transient($this->transient_key, $state, HOUR_IN_SECONDS);
        
        // Check if phase is complete
        $phase_complete = ($state['current_offset'] >= $state['phase_counts']['phase' . $phase]);
        
        wp_send_json_success([
            'processed' => $result['processed'],
            'total_processed' => $state['processed']['phase' . $phase],
            'phase_complete' => $phase_complete,
            'errors' => $result['errors'],
            'message' => $result['message'],
            'state' => $state
        ]);
    }
    
    /**
     * AJAX: Get current statistics
     */
    public function ajax_get_stats()
    {
        check_ajax_referer('acf_location_updater', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $stats = $this->getPostUpdateStatistics();
        $state = get_transient($this->transient_key);
        
        wp_send_json_success([
            'stats' => $stats,
            'state' => $state
        ]);
    }
    
    /**
     * AJAX: Cleanup after completion
     */
    public function ajax_cleanup()
    {
        check_ajax_referer('acf_location_updater', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        delete_transient($this->transient_key);
        wp_cache_flush();
        
        wp_send_json_success(['message' => 'Cleanup completato']);
    }
    
    /**
     * Get pre-update statistics
     */
    private function getPreUpdateStatistics()
    {
        $stats = [
            'posts_needing_city' => 0,
            'posts_needing_province' => 0,
            'post_type_stats' => []
        ];
        
        foreach ($this->post_types as $post_type) {
            $args = [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ];
            
            $query = new \WP_Query($args);
            $post_ids = $query->posts;
            
            $with_city = 0;
            $with_province = 0;
            
            foreach ($post_ids as $post_id) {
                $city = get_field('citta', $post_id);
                $province = get_field('provincia', $post_id);
                
                if (!empty($city)) {
                    $with_city++;
                } else {
                    $stats['posts_needing_city']++;
                }
                
                if (!empty($province)) {
                    $with_province++;
                } else {
                    $stats['posts_needing_province']++;
                }
            }
            
            $stats['post_type_stats'][] = (object)[
                'post_type' => $post_type,
                'total_posts' => count($post_ids),
                'posts_with_city' => $with_city,
                'posts_with_province' => $with_province
            ];
        }
        
        return $stats;
    }
    
    /**
     * Count items for Phase 1
     */
    private function countPhase1Items()
    {
        $args = [
            'post_type' => $this->post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'citta',
                    'value' => '',
                    'compare' => '='
                ],
                [
                    'key' => 'citta',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];
        
        $query = new \WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Process batch for Phase 1
     */
    private function processBatchPhase1($offset)
    {
        $processed = 0;
        $errors = [];
        
        $args = [
            'post_type' => $this->post_types,
            'post_status' => 'publish',
            'posts_per_page' => $this->batch_size,
            'offset' => $offset,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'citta',
                    'value' => '',
                    'compare' => '='
                ],
                [
                    'key' => 'citta',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];
        
        $query = new \WP_Query($args);
        $post_ids = $query->posts;
        
        foreach ($post_ids as $post_id) {
            try {
                $author_id = get_post_field('post_author', $post_id);
                $vendor_settings = get_user_meta($author_id, 'dokan_profile_settings', true);
                
                if (!empty($vendor_settings['address']['city'])) {
                    $city = sanitize_text_field($vendor_settings['address']['city']);
                    
                    if ($this->isValidComune($city)) {
                        update_field('citta', $city, $post_id);
                        $processed++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Post ID {$post_id}: " . $e->getMessage();
            }
        }
        
        return [
            'processed' => count($post_ids),
            'updated' => $processed,
            'errors' => $errors,
            'message' => "Processati {$processed} record su " . count($post_ids)
        ];
    }
    
    /**
     * Phase 1: Update existing città fields from vendor profile (legacy)
     */
    private function updateExistingCityFields()
    {
        $updated_count = 0;
        $errors = [];
        
        try {
            // Get posts with empty città field
            $args = [
                'post_type' => $this->post_types,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => 'citta',
                        'value' => '',
                        'compare' => '='
                    ],
                    [
                        'key' => 'citta',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ];
            
            $query = new \WP_Query($args);
            $post_ids = $query->posts;
            
            foreach ($post_ids as $post_id) {
                $author_id = get_post_field('post_author', $post_id);
                
                // Get vendor profile settings
                $vendor_settings = get_user_meta($author_id, 'dokan_profile_settings', true);
                
                if (!empty($vendor_settings['address']['city'])) {
                    $city = sanitize_text_field($vendor_settings['address']['city']);
                    
                    // Verify city exists in comuni table
                    if ($this->isValidComune($city)) {
                        update_field('citta', $city, $post_id);
                        $updated_count++;
                        
                        // Process in batches to avoid memory issues
                        if ($updated_count % $this->batch_size == 0) {
                            wp_cache_flush();
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
            error_log('Phase 1 Error: ' . $e->getMessage());
        }
        
        return [
            'success' => empty($errors),
            'affected_rows' => $updated_count,
            'error' => implode(', ', $errors)
        ];
    }
    
    /**
     * Count items for Phase 2
     */
    private function countPhase2Items()
    {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'citta'
                WHERE p.post_type IN ('" . implode("','", $this->post_types) . "')
                AND p.post_status = 'publish'
                AND pm.meta_id IS NULL";
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * Process batch for Phase 2
     */
    private function processBatchPhase2($offset)
    {
        global $wpdb;
        $processed = 0;
        $errors = [];
        
        $sql = "SELECT DISTINCT p.ID, p.post_author
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'citta'
                WHERE p.post_type IN ('" . implode("','", $this->post_types) . "')
                AND p.post_status = 'publish'
                AND pm.meta_id IS NULL
                LIMIT %d OFFSET %d";
        
        $posts = $wpdb->get_results($wpdb->prepare($sql, $this->batch_size, $offset));
        
        foreach ($posts as $post) {
            try {
                $vendor_settings = get_user_meta($post->post_author, 'dokan_profile_settings', true);
                
                if (!empty($vendor_settings['address']['city'])) {
                    $city = sanitize_text_field($vendor_settings['address']['city']);
                    
                    if ($this->isValidComune($city)) {
                        update_field('citta', $city, $post->ID);
                        $processed++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Post ID {$post->ID}: " . $e->getMessage();
            }
        }
        
        return [
            'processed' => count($posts),
            'updated' => $processed,
            'errors' => $errors,
            'message' => "Inseriti {$processed} campi città su " . count($posts)
        ];
    }
    
    /**
     * Phase 2: Insert missing città fields (legacy)
     */
    private function insertMissingCityFields()
    {
        $inserted_count = 0;
        $errors = [];
        
        try {
            // Get posts without città metadata
            global $wpdb;
            
            $sql = "SELECT DISTINCT p.ID, p.post_author
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'citta'
                    WHERE p.post_type IN ('" . implode("','", $this->post_types) . "')
                    AND p.post_status = 'publish'
                    AND pm.meta_id IS NULL";
            
            $posts = $wpdb->get_results($sql);
            
            foreach ($posts as $post) {
                $vendor_settings = get_user_meta($post->post_author, 'dokan_profile_settings', true);
                
                if (!empty($vendor_settings['address']['city'])) {
                    $city = sanitize_text_field($vendor_settings['address']['city']);
                    
                    if ($this->isValidComune($city)) {
                        // Use update_field to create the field if it doesn't exist
                        update_field('citta', $city, $post->ID);
                        $inserted_count++;
                        
                        if ($inserted_count % $this->batch_size == 0) {
                            wp_cache_flush();
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
            error_log('Phase 2 Error: ' . $e->getMessage());
        }
        
        return [
            'success' => empty($errors),
            'affected_rows' => $inserted_count,
            'error' => implode(', ', $errors)
        ];
    }
    
    /**
     * Count items for Phase 3
     */
    private function countPhase3Items()
    {
        $args = [
            'post_type' => $this->post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'citta',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        
        $query = new \WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Process batch for Phase 3
     */
    private function processBatchPhase3($offset)
    {
        $processed = 0;
        $errors = [];
        
        $args = [
            'post_type' => $this->post_types,
            'post_status' => 'publish',
            'posts_per_page' => $this->batch_size,
            'offset' => $offset,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'citta',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        
        $query = new \WP_Query($args);
        $post_ids = $query->posts;
        
        foreach ($post_ids as $post_id) {
            try {
                $city = get_field('citta', $post_id);
                $current_province = get_field('provincia', $post_id);
                
                if (!empty($city)) {
                    $province = $this->getProvinciaByComune($city);
                    
                    if (!empty($province) && $province !== $current_province) {
                        update_field('provincia', $province, $post_id);
                        $processed++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Post ID {$post_id}: " . $e->getMessage();
            }
        }
        
        return [
            'processed' => count($post_ids),
            'updated' => $processed,
            'errors' => $errors,
            'message' => "Aggiornate {$processed} provincie su " . count($post_ids)
        ];
    }
    
    /**
     * Phase 3: Update existing provincia fields based on città (legacy)
     */
    private function updateExistingProvinceFields()
    {
        $updated_count = 0;
        $errors = [];
        
        try {
            // Get posts with città but invalid or missing provincia
            $args = [
                'post_type' => $this->post_types,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'citta',
                        'value' => '',
                        'compare' => '!='
                    ]
                ]
            ];
            
            $query = new \WP_Query($args);
            $post_ids = $query->posts;
            
            foreach ($post_ids as $post_id) {
                $city = get_field('citta', $post_id);
                $current_province = get_field('provincia', $post_id);
                
                if (!empty($city)) {
                    $province = $this->getProvinciaByComune($city);
                    
                    if (!empty($province) && $province !== $current_province) {
                        update_field('provincia', $province, $post_id);
                        $updated_count++;
                        
                        if ($updated_count % $this->batch_size == 0) {
                            wp_cache_flush();
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
            error_log('Phase 3 Error: ' . $e->getMessage());
        }
        
        return [
            'success' => empty($errors),
            'affected_rows' => $updated_count,
            'error' => implode(', ', $errors)
        ];
    }
    
    /**
     * Count items for Phase 4
     */
    private function countPhase4Items()
    {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = 'citta'
                LEFT JOIN {$wpdb->postmeta} pm_prov ON p.ID = pm_prov.post_id AND pm_prov.meta_key = 'provincia'
                WHERE p.post_type IN ('" . implode("','", $this->post_types) . "')
                AND p.post_status = 'publish'
                AND pm_city.meta_value != ''
                AND pm_prov.meta_id IS NULL";
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * Process batch for Phase 4
     */
    private function processBatchPhase4($offset)
    {
        global $wpdb;
        $processed = 0;
        $errors = [];
        
        $sql = "SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = 'citta'
                LEFT JOIN {$wpdb->postmeta} pm_prov ON p.ID = pm_prov.post_id AND pm_prov.meta_key = 'provincia'
                WHERE p.post_type IN ('" . implode("','", $this->post_types) . "')
                AND p.post_status = 'publish'
                AND pm_city.meta_value != ''
                AND pm_prov.meta_id IS NULL
                LIMIT %d OFFSET %d";
        
        $post_ids = $wpdb->get_col($wpdb->prepare($sql, $this->batch_size, $offset));
        
        foreach ($post_ids as $post_id) {
            try {
                $city = get_field('citta', $post_id);
                
                if (!empty($city)) {
                    $province = $this->getProvinciaByComune($city);
                    
                    if (!empty($province)) {
                        update_field('provincia', $province, $post_id);
                        $processed++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Post ID {$post_id}: " . $e->getMessage();
            }
        }
        
        return [
            'processed' => count($post_ids),
            'updated' => $processed,
            'errors' => $errors,
            'message' => "Inserite {$processed} provincie su " . count($post_ids)
        ];
    }
    
    /**
     * Phase 4: Insert missing provincia fields (legacy)
     */
    private function insertMissingProvinceFields()
    {
        $inserted_count = 0;
        $errors = [];
        
        try {
            // Get posts with città but no provincia
            global $wpdb;
            
            $sql = "SELECT DISTINCT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = 'citta'
                    LEFT JOIN {$wpdb->postmeta} pm_prov ON p.ID = pm_prov.post_id AND pm_prov.meta_key = 'provincia'
                    WHERE p.post_type IN ('" . implode("','", $this->post_types) . "')
                    AND p.post_status = 'publish'
                    AND pm_city.meta_value != ''
                    AND pm_prov.meta_id IS NULL";
            
            $post_ids = $wpdb->get_col($sql);
            
            foreach ($post_ids as $post_id) {
                $city = get_field('citta', $post_id);
                
                if (!empty($city)) {
                    $province = $this->getProvinciaByComune($city);
                    
                    if (!empty($province)) {
                        update_field('provincia', $province, $post_id);
                        $inserted_count++;
                        
                        if ($inserted_count % $this->batch_size == 0) {
                            wp_cache_flush();
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
            error_log('Phase 4 Error: ' . $e->getMessage());
        }
        
        return [
            'success' => empty($errors),
            'affected_rows' => $inserted_count,
            'error' => implode(', ', $errors)
        ];
    }
    
    /**
     * Get post-update statistics
     */
    private function getPostUpdateStatistics()
    {
        return $this->getPreUpdateStatistics();
    }
    
    /**
     * Check if a comune is valid
     */
    private function isValidComune($comune)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dkm_comuni';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE nome = %s",
            $comune
        ));
        
        return $result > 0;
    }
    
    /**
     * Get provincia by comune name
     */
    private function getProvinciaByComune($comune)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dkm_comuni';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT provincia_nome FROM {$table} WHERE nome = %s LIMIT 1",
            $comune
        ));
    }
    
    /**
     * Output HTML header
     */
    private function outputHtmlHeader()
    {
        $timestamp = current_time('d/m/Y H:i:s');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>ACF Location Update Report - Native Methods</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
                    margin: 20px; 
                    background: #f5f5f5; 
                    line-height: 1.6; 
                }
                .container { 
                    max-width: 1200px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 30px; 
                    border-radius: 8px; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                }
                h1 { 
                    color: #1e40af; 
                    border-bottom: 3px solid #3b82f6; 
                    padding-bottom: 10px; 
                    margin-bottom: 20px; 
                }
                h2 { 
                    color: #1f2937; 
                    background: #e5e7eb; 
                    padding: 10px 15px; 
                    border-left: 4px solid #6366f1; 
                    margin: 25px 0 15px; 
                }
                .phase-header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 15px; 
                    margin: 20px 0 10px; 
                    border-radius: 5px; 
                    font-weight: 600; 
                }
                .result-box { 
                    background: #f0f9ff; 
                    border: 1px solid #0ea5e9; 
                    padding: 15px; 
                    margin: 10px 0; 
                    border-radius: 5px; 
                }
                .success { 
                    background: #f0fdf4; 
                    border-color: #10b981; 
                }
                .error { 
                    background: #fef2f2; 
                    border-color: #ef4444; 
                }
                .warning {
                    background: #fffbeb;
                    border-color: #f59e0b;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 15px 0; 
                }
                th, td { 
                    padding: 8px 12px; 
                    text-align: left; 
                    border-bottom: 1px solid #e5e7eb; 
                }
                th { 
                    background: #f9fafb; 
                    font-weight: 600; 
                    color: #374151; 
                }
                tr:hover { 
                    background: #f9fafb; 
                }
                .timestamp { 
                    color: #6b7280; 
                    font-size: 14px; 
                }
                .summary-stats { 
                    display: flex; 
                    gap: 20px; 
                    margin: 20px 0; 
                    flex-wrap: wrap; 
                }
                .stat-card { 
                    flex: 1; 
                    min-width: 150px; 
                    background: #f8fafc; 
                    padding: 15px; 
                    border-radius: 5px; 
                    text-align: center; 
                    border: 1px solid #e2e8f0; 
                }
                .stat-number { 
                    font-size: 24px; 
                    font-weight: bold; 
                    color: #1e40af; 
                }
                .stat-label { 
                    color: #64748b; 
                    font-size: 14px; 
                    margin-top: 5px; 
                }
                .icon { 
                    font-size: 18px; 
                    margin-right: 8px; 
                }
                .method-badge {
                    display: inline-block;
                    background: #6366f1;
                    color: white;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    margin-left: 10px;
                }
            </style>
        </head>
        <body>
        <div class="container">
            <h1>
                <span class="icon">🔄</span>Rapporto Aggiornamento Campi ACF
                <span class="method-badge">Native WordPress/ACF Methods</span>
            </h1>
            <p class="timestamp">Eseguito il: <?php echo esc_html($timestamp); ?></p>
        <?php
    }
    
    /**
     * Output phase header
     */
    private function outputPhaseHeader($phase, $title)
    {
        echo '<div class="phase-header"><strong>Fase ' . esc_html($phase) . ':</strong> ' . esc_html($title) . '</div>';
    }
    
    /**
     * Output pre-statistics
     */
    private function outputPreStatistics($stats)
    {
        ?>
        <div class="result-box">
            <h3><span class="icon">📊</span>Statistiche Pre-Aggiornamento</h3>
            <div class="summary-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($stats['posts_needing_city']); ?></div>
                    <div class="stat-label">Post senza Città</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($stats['posts_needing_province']); ?></div>
                    <div class="stat-label">Post senza Provincia</div>
                </div>
            </div>
            
            <?php if (!empty($stats['post_type_stats'])): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tipo Post</th>
                        <th>Totale Post</th>
                        <th>Con Città</th>
                        <th>Con Provincia</th>
                        <th>% Completezza</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['post_type_stats'] as $stat): ?>
                    <?php 
                        $completeness = $stat->total_posts > 0 
                            ? round((($stat->posts_with_city + $stat->posts_with_province) / ($stat->total_posts * 2)) * 100, 1) 
                            : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst(str_replace('-', ' ', $stat->post_type))); ?></td>
                        <td><?php echo esc_html($stat->total_posts); ?></td>
                        <td><?php echo esc_html($stat->posts_with_city); ?></td>
                        <td><?php echo esc_html($stat->posts_with_province); ?></td>
                        <td><?php echo esc_html($completeness); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Output phase result
     */
    private function outputPhaseResult($result)
    {
        $class = $result['success'] ? 'success' : 'error';
        $icon = $result['success'] ? '✅' : '❌';
        ?>
        <div class="result-box <?php echo esc_attr($class); ?>">
            <p>
                <strong><span class="icon"><?php echo $icon; ?></span>Risultato:</strong>
                <?php if ($result['success']): ?>
                    Operazione completata con successo. 
                    <strong><?php echo esc_html($result['affected_rows']); ?></strong> record modificati.
                <?php else: ?>
                    Errore durante l'operazione: <?php echo esc_html($result['error']); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Output post-statistics
     */
    private function outputPostStatistics($stats)
    {
        ?>
        <div class="result-box success">
            <h3><span class="icon">📈</span>Statistiche Post-Aggiornamento</h3>
            <div class="summary-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($stats['posts_needing_city']); ?></div>
                    <div class="stat-label">Post ancora senza Città</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($stats['posts_needing_province']); ?></div>
                    <div class="stat-label">Post ancora senza Provincia</div>
                </div>
            </div>
            
            <?php if (!empty($stats['post_type_stats'])): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tipo Post</th>
                        <th>Totale Post</th>
                        <th>Con Città</th>
                        <th>Con Provincia</th>
                        <th>% Completezza</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['post_type_stats'] as $stat): ?>
                    <?php 
                        $completeness = $stat->total_posts > 0 
                            ? round((($stat->posts_with_city + $stat->posts_with_province) / ($stat->total_posts * 2)) * 100, 1) 
                            : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst(str_replace('-', ' ', $stat->post_type))); ?></td>
                        <td><?php echo esc_html($stat->total_posts); ?></td>
                        <td><?php echo esc_html($stat->posts_with_city); ?></td>
                        <td><?php echo esc_html($stat->posts_with_province); ?></td>
                        <td><?php echo esc_html($completeness); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Output final summary
     */
    private function outputFinalSummary($results)
    {
        $total_updates = 0;
        $success_phases = 0;
        
        $phase_names = [
            'phase1' => 'Aggiornamento Città Esistenti',
            'phase2' => 'Inserimento Città Mancanti', 
            'phase3' => 'Aggiornamento Provincie Esistenti',
            'phase4' => 'Inserimento Provincie Mancanti'
        ];
        ?>
        
        <h2><span class="icon">📋</span>Riepilogo Finale</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Fase</th>
                    <th>Stato</th>
                    <th>Record Aggiornati</th>
                    <th>Errori</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $key => $result): ?>
                <?php
                    $status = $result['success'] 
                        ? '<span class="icon">✅</span> Successo' 
                        : '<span class="icon">❌</span> Errore';
                    $updates = $result['affected_rows'];
                    $error_msg = $result['error'] ? esc_html($result['error']) : '-';
                    $total_updates += $updates;
                    if ($result['success']) $success_phases++;
                ?>
                <tr>
                    <td><?php echo esc_html($phase_names[$key]); ?></td>
                    <td><?php echo $status; ?></td>
                    <td><strong><?php echo esc_html($updates); ?></strong></td>
                    <td><?php echo $error_msg; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($total_updates); ?></div>
                <div class="stat-label">Totale Aggiornamenti</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($success_phases); ?>/4</div>
                <div class="stat-label">Fasi Completate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo round(microtime(true) - $this->start_time, 2); ?>s</div>
                <div class="stat-label">Tempo Esecuzione</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo round(memory_get_peak_usage(true) / 1024 / 1024, 2); ?>MB</div>
                <div class="stat-label">Memoria Peak</div>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Output error message
     */
    private function outputError($message)
    {
        ?>
        <div class="result-box error">
            <p>
                <strong><span class="icon">❌</span>Errore Critico:</strong> 
                <?php echo esc_html($message); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Output HTML footer
     */
    private function outputHtmlFooter()
    {
        ?>
            <div class="result-box warning">
                <p><strong><span class="icon">ℹ️</span>Note sull'implementazione:</strong></p>
                <ul>
                    <li>Questa versione utilizza i metodi nativi di WordPress e ACF</li>
                    <li>Le prestazioni potrebbero essere leggermente inferiori rispetto alle query SQL dirette</li>
                    <li>Maggiore compatibilità con hook e filtri WordPress</li>
                    <li>Gestione cache ottimizzata per grandi volumi di dati</li>
                </ul>
            </div>
            <p><strong><span class="icon">✅</span>Processo completato!</strong></p>
            <p><small>ACF Location Updater - Versione con metodi nativi WordPress/ACF</small></p>
        </div>
        </body>
        </html>
        <?php
    }
}

// Register WP-CLI command if available
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('dokan-mod acf-update', function() {
        $updater = new AcfLocationUpdater();
        $updater->run();
    });
}

// Allow direct execution via URL parameter (admin only)
add_action('admin_init', function() {
    if (isset($_GET['run_acf_update']) && $_GET['run_acf_update'] == '1') {
        if (!current_user_can('manage_options')) {
            wp_die('Accesso negato. Privilegi di amministratore richiesti.');
        }
        
        $updater = new AcfLocationUpdater();
        $updater->run();
        exit;
    }
});

// Add admin menu item
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'ACF Location Updater',
        'ACF Location Update',
        'manage_options',
        'acf-location-updater',
        function() {
            ?>
            <div class="wrap acf-updater-wrap">
                <h1>ACF Location Updater <span class="version-badge">v2.0 AJAX</span></h1>
                <p>Questo strumento aggiorna i campi città e provincia per tutti i post type personalizzati utilizzando i metodi nativi di WordPress e ACF con elaborazione AJAX.</p>
                
                <div id="acf-updater-container">
                    <!-- Control Panel -->
                    <div class="control-panel">
                        <button id="start-update" class="button button-primary button-hero">
                            <span class="dashicons dashicons-update"></span> Avvia Aggiornamento
                        </button>
                        <button id="pause-update" class="button button-secondary button-hero" style="display:none;">
                            <span class="dashicons dashicons-pause"></span> Pausa
                        </button>
                        <button id="resume-update" class="button button-secondary button-hero" style="display:none;">
                            <span class="dashicons dashicons-controls-play"></span> Riprendi
                        </button>
                        <button id="reset-update" class="button button-link-delete" style="display:none;">
                            <span class="dashicons dashicons-trash"></span> Reset
                        </button>
                    </div>
                    
                    <!-- Progress Dashboard -->
                    <div id="progress-dashboard" style="display:none;">
                        <h2>Progresso Aggiornamento</h2>
                        
                        <!-- Phase 1 -->
                        <div class="phase-container" id="phase-1">
                            <h3><span class="phase-icon">📝</span> Fase 1: Aggiornamento Città Esistenti</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar" data-phase="1"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="phase-stats">
                                <span class="processed">0</span> / <span class="total">0</span> record processati
                            </div>
                        </div>
                        
                        <!-- Phase 2 -->
                        <div class="phase-container" id="phase-2">
                            <h3><span class="phase-icon">➕</span> Fase 2: Inserimento Città Mancanti</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar" data-phase="2"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="phase-stats">
                                <span class="processed">0</span> / <span class="total">0</span> record processati
                            </div>
                        </div>
                        
                        <!-- Phase 3 -->
                        <div class="phase-container" id="phase-3">
                            <h3><span class="phase-icon">🔄</span> Fase 3: Aggiornamento Provincie Esistenti</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar" data-phase="3"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="phase-stats">
                                <span class="processed">0</span> / <span class="total">0</span> record processati
                            </div>
                        </div>
                        
                        <!-- Phase 4 -->
                        <div class="phase-container" id="phase-4">
                            <h3><span class="phase-icon">🗺️</span> Fase 4: Inserimento Provincie Mancanti</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar" data-phase="4"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="phase-stats">
                                <span class="processed">0</span> / <span class="total">0</span> record processati
                            </div>
                        </div>
                        
                        <!-- Overall Progress -->
                        <div class="overall-progress">
                            <h3>Progresso Totale</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar overall"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="time-stats">
                                <span>Tempo trascorso: <span id="elapsed-time">00:00:00</span></span>
                                <span>Tempo stimato: <span id="estimated-time">--:--:--</span></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Log Console -->
                    <div id="log-console" style="display:none;">
                        <h3>Log Operazioni</h3>
                        <div class="log-container">
                            <div id="log-content"></div>
                        </div>
                        <button id="clear-log" class="button button-small">Pulisci Log</button>
                    </div>
                    
                    <!-- Statistics -->
                    <div id="statistics-panel" style="display:none;">
                        <h2>Statistiche</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value" id="total-processed">0</div>
                                <div class="stat-label">Record Processati</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="total-updated">0</div>
                                <div class="stat-label">Record Aggiornati</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="total-errors">0</div>
                                <div class="stat-label">Errori</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="processing-speed">0</div>
                                <div class="stat-label">Record/sec</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Final Report -->
                    <div id="final-report" style="display:none;">
                        <h2>Report Finale</h2>
                        <div id="report-content"></div>
                        <button id="download-report" class="button button-primary">
                            <span class="dashicons dashicons-download"></span> Scarica Report
                        </button>
                    </div>
                </div>
            </div>
            <?php
        }
    );
});