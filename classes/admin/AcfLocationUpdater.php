<?php

namespace Dokan_Mods;

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
        add_action('wp_ajax_acf_updater_test', [$this, 'ajax_test']);
        
        // Enqueue scripts on admin page
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Handle legacy direct execution
        add_action('admin_init', [$this, 'handle_legacy_execution']);
        
        // Log that the class is loaded
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AcfLocationUpdater class loaded successfully');
        }
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
        try {
            check_ajax_referer('acf_location_updater', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized');
                return;
            }
            
            // Get initial statistics
            $stats = $this->getPreUpdateStatistics();
            
            // Count total items for each phase (only missing fields)
            $phase_counts = [
                'phase1' => $this->countPhase1Items(), // Insert missing cities
                'phase2' => $this->countPhase2Items()  // Insert missing provinces
            ];
            
            // Initialize state
            $state = [
                'current_phase' => 1,
                'current_offset' => 0,
                'phase_counts' => $phase_counts,
                'processed' => [
                    'phase1' => 0,  // Cities inserted
                    'phase2' => 0   // Provinces inserted
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
            
        } catch (\Exception $e) {
            error_log('ACF Updater ajax_init error: ' . $e->getMessage());
            wp_send_json_error('Internal error: ' . $e->getMessage());
        }
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
        
        // Process batch based on phase (only missing fields)
        $result = [];
        switch ($phase) {
            case 1:
                $result = $this->processBatchPhase1($offset); // Insert missing cities
                break;
            case 2:
                $result = $this->processBatchPhase2($offset); // Insert missing provinces
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
        try {
            check_ajax_referer('acf_location_updater', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized');
                return;
            }
            
            $stats = $this->getPostUpdateStatistics();
            $state = get_transient($this->transient_key);
            
            wp_send_json_success([
                'stats' => $stats,
                'state' => $state
            ]);
            
        } catch (\Exception $e) {
            error_log('ACF Updater ajax_get_stats error: ' . $e->getMessage());
            wp_send_json_error('Internal error: ' . $e->getMessage());
        }
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
     * AJAX: Test connection (debug)
     */
    public function ajax_test()
    {
        wp_send_json_success([
            'message' => 'AJAX connection working',
            'class' => get_class($this),
            'time' => current_time('mysql')
        ]);
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
     * Count items for Phase 1 - Insert missing cities only
     */
    private function countPhase1Items()
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
     * Process batch for Phase 1 - Insert missing cities only
     */
    private function processBatchPhase1($offset)
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
            'message' => "Inserite {$processed} città mancanti su " . count($posts)
        ];
    }
    
    
    /**
     * Count items for Phase 2 - Insert missing provinces only
     */
    private function countPhase2Items()
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
     * Process batch for Phase 2 - Insert missing provinces only
     */
    private function processBatchPhase2($offset)
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
            'message' => "Inserite {$processed} provincie mancanti su " . count($post_ids)
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
    
    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            'ACF Location Updater',
            'ACF Location Update',
            'manage_options',
            'acf-location-updater',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Handle legacy direct execution
     */
    public function handle_legacy_execution()
    {
        if (isset($_GET['run_acf_update']) && $_GET['run_acf_update'] == '1') {
            if (!current_user_can('manage_options')) {
                wp_die('Accesso negato. Privilegi di amministratore richiesti.');
            }
            
            // For legacy support - redirect to new AJAX interface
            wp_redirect(admin_url('tools.php?page=acf-location-updater'));
            exit;
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page()
    {
            ?>
            <div class="wrap acf-updater-wrap">
                <h1>ACF Location Updater <span class="version-badge">v2.1 Solo Campi Mancanti</span></h1>
                <p>Questo strumento <strong>aggiunge SOLO</strong> i campi città e provincia mancanti per tutti i post type personalizzati, senza sovrascrivere i dati esistenti. Utilizza i metodi nativi di WordPress e ACF con elaborazione AJAX.</p>
                
                <div class="notice notice-info">
                    <p><strong>ℹ️ Modalità Sicura:</strong> Il processo aggiunge SOLO campi vuoti o inesistenti. I campi già compilati non vengono modificati.</p>
                </div>
                
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
                            <h3><span class="phase-icon">🏙️</span> Fase 1: Inserimento Città Mancanti</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar" data-phase="1"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="phase-stats">
                                <span class="processed">0</span> / <span class="total">0</span> record processati
                            </div>
                            <div class="phase-description">
                                <small>Aggiunge il campo città ai post che non ce l'hanno, utilizzando i dati del profilo vendor.</small>
                            </div>
                        </div>
                        
                        <!-- Phase 2 -->
                        <div class="phase-container" id="phase-2">
                            <h3><span class="phase-icon">🗺️</span> Fase 2: Inserimento Provincie Mancanti</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar" data-phase="2"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="phase-stats">
                                <span class="processed">0</span> / <span class="total">0</span> record processati
                            </div>
                            <div class="phase-description">
                                <small>Aggiunge il campo provincia ai post che hanno la città ma non la provincia.</small>
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
}

// Register WP-CLI command if available
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('dokan-mod acf-update', function() {
        // Note: This won't work anymore as we need AJAX. Redirect to info message
        \WP_CLI::line('This command now requires the AJAX interface. Please use the admin interface at Tools -> ACF Location Update');
    });
}