<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Dokan_Mods\Templates_MiscClass;

// Check if vendor is logged in and enabled for monitor
$template_class = new Templates_MiscClass();
$template_class->check_dokan_can_and_message_login();
$template_class->enqueue_dashboard_common_styles();

// Manually enqueue monitor scripts and localize nonce
wp_enqueue_script(
    'monitor-vendor',
    DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/monitor-vendor.js',
    array('jquery'),
    '1.1.0',
    true
);

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

// Localize the correct nonce for JavaScript
wp_localize_script('monitor-vendor', 'monitor_vendor_ajax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('monitor_vendor_nonce')
));

$user_id = get_current_user_id();
$monitor_class = new Dokan_Mods\MonitorTotemClass();
$db_manager = Dokan_Mods\MonitorDatabaseManager::get_instance();

// Get vendor monitors
$vendor_monitors = $db_manager->get_vendor_monitors($user_id);

// Check if vendor has any monitors assigned
if (empty($vendor_monitors)) {
    ?>
    <div class="dokan-alert dokan-alert-warning">
        <strong>Nessun Monitor Assegnato:</strong> Il tuo account non ha monitor digitali assegnati. 
        Contatta l'amministratore per richiedere l'assegnazione di un monitor.
    </div>
    <?php
    return;
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'associate_defunto' && wp_verify_nonce($_POST['monitor_nonce'], 'monitor_associate_defunto')) {
        $monitor_id = intval($_POST['monitor_id']);
        $post_id = intval($_POST['post_id']);
        
        // Verify post belongs to vendor and monitor belongs to vendor
        $post = get_post($post_id);
        $monitor = $db_manager->get_monitor($monitor_id);
        
        if ($post && $post->post_author == $user_id && $post->post_type === 'annuncio-di-morte' &&
            $monitor && $monitor['vendor_id'] == $user_id) {
            
            $result = $db_manager->update_monitor($monitor_id, [
                'associated_post_id' => $post_id
            ]);
            
            if ($result) {
                $message = sprintf(__('"%s" è stato associato al monitor "%s".', 'dokan-mod'), 
                    get_the_title($post_id), $monitor['monitor_name']);
                $message_type = 'success';
            } else {
                $message = __('Errore durante l\'associazione.', 'dokan-mod');
                $message_type = 'error';
            }
        } else {
            $message = __('Errore: Annuncio o monitor non valido.', 'dokan-mod');
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'remove_association' && wp_verify_nonce($_POST['monitor_nonce'], 'monitor_remove_association')) {
        $monitor_id = intval($_POST['monitor_id']);
        $monitor = $db_manager->get_monitor($monitor_id);
        
        if ($monitor && $monitor['vendor_id'] == $user_id) {
            $result = $db_manager->update_monitor($monitor_id, [
                'associated_post_id' => null
            ]);
            
            if ($result) {
                $message = sprintf(__('Associazione rimossa dal monitor "%s".', 'dokan-mod'), $monitor['monitor_name']);
                $message_type = 'success';
            } else {
                $message = __('Errore durante la rimozione.', 'dokan-mod');
                $message_type = 'error';
            }
        }
    } elseif ($_POST['action'] === 'update_layout' && wp_verify_nonce($_POST['monitor_nonce'], 'monitor_update_layout')) {
        $monitor_id = intval($_POST['monitor_id']);
        $layout_type = sanitize_text_field($_POST['layout_type']);
        $monitor = $db_manager->get_monitor($monitor_id);
        
        if ($monitor && $monitor['vendor_id'] == $user_id) {
            // Prepare layout config based on layout type
            $layout_config = [];
            if ($layout_type === 'citta_multi') {
                $layout_config = [
                    'days_range' => intval($_POST['days_range'] ?? 7),
                    'show_all_agencies' => (bool)($_POST['show_all_agencies'] ?? false)
                ];
            }
            
            $result = $db_manager->update_monitor($monitor_id, [
                'layout_type' => $layout_type,
                'layout_config' => wp_json_encode($layout_config)
            ]);
            
            if ($result) {
                $message = sprintf(__('Layout aggiornato per il monitor "%s".', 'dokan-mod'), $monitor['monitor_name']);
                $message_type = 'success';
                // Refresh monitors data
                $vendor_monitors = $db_manager->get_vendor_monitors($user_id);
            } else {
                $message = __('Errore durante l\'aggiornamento del layout.', 'dokan-mod');
                $message_type = 'error';
            }
        }
    }
}

// Get vendor info
$vendor = dokan()->vendor->get($user_id);
$shop_name = $vendor->get_shop_name();

// Get available layouts
$available_layouts = [
    'manifesti' => __('Layout Manifesti (Tradizionale)', 'dokan-mod'),
    'solo_annuncio' => __('Layout Solo Annuncio', 'dokan-mod'),
    'citta_multi' => __('Layout Città Multi-Agenzia', 'dokan-mod')
];

// Helper function for safe JSON decoding
if (!function_exists('dkmod_safe_decode_layout_config')) {
    function dkmod_safe_decode_layout_config($layout_config) {
        if (is_string($layout_config) && !empty($layout_config)) {
            $decoded = json_decode($layout_config, true);
            return is_array($decoded) ? $decoded : [];
        } elseif (is_array($layout_config)) {
            return $layout_config;
        } else {
            return [];
        }
    }
}

// Get pagination
if (get_query_var('paged')) {
    $paged = get_query_var('paged');
} elseif (get_query_var('page')) {
    $paged = get_query_var('page');
} else {
    $paged = 1;
}

// Query args for defunti (only this vendor's posts)
$args = array(
    'post_type' => 'annuncio-di-morte',
    'post_status' => 'publish,pending,draft,future,private',
    'author' => $user_id, // Only current vendor's posts
    'posts_per_page' => 10,
    'paged' => $paged,
    's' => get_query_var('s'), // Search support
    'orderby' => 'date',
    'order' => 'DESC'
);

// Execute the query
$query = new WP_Query($args);

// Includi l'header
get_header();

$active_menu = 'monitor-digitale';
?>

<main id="content" class="site-main post-58 page type-page status-publish hentry">

    <header class="page-header">
        <h1 class="entry-title"><?php _e('Monitor Digitale', 'dokan-mod'); ?></h1>
    </header>

    <div class="page-content">

        <div class="dokan-dashboard-wrap">

            <?php
            /**
             *  Adding dokan_dashboard_content_before hook
             *
             * @hooked dashboard_side_navigation
             *
             * @since 2.4
             */
            do_action('dokan_dashboard_content_before');
            ?>

            <div class="dokan-dashboard-content dokan-product-edit">
                <?php
                /**
                 *  Adding dokan_dashboard_content_inside_before hook
                 *
                 * @hooked show_seller_dashboard_notice
                 *
                 * @since 2.4
                 */
                do_action('dokan_dashboard_content_inside_before');
                do_action('dokan_before_listing_product');
                ?>
                
                <header class="dokan-dashboard-header dokan-clearfix">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h1 class="entry-title">
                            <i class="dashicons dashicons-desktop"></i>
                            <?php _e('Monitor Digitale', 'dokan-mod'); ?>
                        </h1>
                    </div>
                    
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="dokan-alert dokan-alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
                            <strong>
                                <?php if ($message_type === 'success'): ?>
                                    <i class="dashicons dashicons-yes"></i> <?php _e('Successo!', 'dokan-mod'); ?>
                                <?php else: ?>
                                    <i class="dashicons dashicons-warning"></i> <?php _e('Errore!', 'dokan-mod'); ?>
                                <?php endif; ?>
                            </strong>
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                </header>

                <div class="product-edit-new-container product-edit-container" style="margin-bottom: 100px">

                    <!-- Monitor Info Section -->
                    <div class="dokan-monitor-info" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #007cba;">
                        <h3 style="margin: 0 0 15px 0;"><?php _e('I Tuoi Monitor Digitali', 'dokan-mod'); ?></h3>
                        <p><strong><?php _e('Negozio:', 'dokan-mod'); ?></strong> <?php echo esc_html($shop_name); ?></p>
                        <p><strong><?php _e('Monitor Assegnati:', 'dokan-mod'); ?></strong> <?php echo count($vendor_monitors); ?></p>
                    </div>

                    <!-- Monitors Table -->
                    <div class="monitors-table-container">
                        <table class="wp-list-table widefat fixed striped monitors-table">
                            <thead>
                                <tr>
                                    <th class="manage-column monitor-name-col"><?php _e('Nome Monitor', 'dokan-mod'); ?></th>
                                    <th class="manage-column monitor-url-col"><?php _e('URL Display', 'dokan-mod'); ?></th>
                                    <th class="manage-column monitor-status-col"><?php _e('Stato', 'dokan-mod'); ?></th>
                                    <th class="manage-column monitor-association-col"><?php _e('Associazione', 'dokan-mod'); ?></th>
                                    <th class="manage-column monitor-layout-col"><?php _e('Layout', 'dokan-mod'); ?></th>
                                    <th class="manage-column monitor-actions-col"><?php _e('Azioni', 'dokan-mod'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendor_monitors as $monitor): 
                                    $layout_config = dkmod_safe_decode_layout_config($monitor['layout_config']);
                                    $associated_post_title = $monitor['associated_post_id'] ? get_the_title($monitor['associated_post_id']) : null;
                                    $monitor_url = home_url('/monitor/display/' . $user_id . '/' . $monitor['id'] . '/' . $monitor['monitor_slug']);
                                ?>
                                    <tr data-monitor-id="<?php echo $monitor['id']; ?>" class="monitor-row">
                                        <!-- Monitor Name -->
                                        <td class="monitor-name-cell">
                                            <strong><?php echo esc_html($monitor['monitor_name']); ?></strong>
                                            <?php if ($monitor['monitor_description']): ?>
                                                <div class="monitor-description"><?php echo esc_html($monitor['monitor_description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- URL Display -->
                                        <td class="monitor-url-cell">
                                            <div class="url-display">
                                                <?php 
                                                // Extract only the path after domain for display
                                                $parsed_url = parse_url($monitor_url);
                                                $display_path = $parsed_url['path'] ?? '';
                                                // Show only the monitor-specific part (last 3 segments)
                                                $path_segments = array_filter(explode('/', $display_path));
                                                $display_segments = array_slice($path_segments, -3); // vendor_id/monitor_id/slug
                                                $short_display = '.../' . implode('/', $display_segments);
                                                ?>
                                                <code class="monitor-url-code" title="<?php echo esc_attr($monitor_url); ?>"><?php echo esc_html($short_display); ?></code>
                                                <button type="button" class="copy-url-btn compact" onclick="copyMonitorUrl('<?php echo $monitor['id']; ?>')" title="<?php _e('Copia URL completo', 'dokan-mod'); ?>">
                                                    <i class="dashicons dashicons-admin-page"></i>
                                                </button>
                                            </div>
                                        </td>
                                        
                                        <!-- Status -->
                                        <td class="monitor-status-cell">
                                            <?php if ($monitor['associated_post_id']): ?>
                                                <div class="status-badge status-active">
                                                    <i class="dashicons dashicons-yes"></i>
                                                    <span class="status-text">
                                                        <strong><?php _e('ATTIVO', 'dokan-mod'); ?></strong>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="status-badge status-inactive">
                                                    <i class="dashicons dashicons-warning"></i>
                                                    <span class="status-text">
                                                        <strong><?php _e('INATTIVO', 'dokan-mod'); ?></strong>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Association -->
                                        <td class="monitor-association-cell">
                                            <?php
                                            switch ($monitor['layout_type']) {
                                                case 'solo_annuncio':
                                                case 'manifesti':
                                                    if ($monitor['associated_post_id'] && $associated_post_title) {
                                                        echo '<div class="association-info association-single">';
                                                        echo '<i class="dashicons dashicons-admin-post"></i>';
                                                        echo '<div class="association-content">';
                                                        echo '<strong>' . esc_html($associated_post_title) . '</strong>';
                                                        
                                                        // Get post date for additional info
                                                        $post_date = get_the_date('d/m/Y', $monitor['associated_post_id']);
                                                        echo '<small>' . sprintf(__('Pubblicato: %s', 'dokan-mod'), $post_date) . '</small>';
                                                        echo '</div></div>';
                                                    } else {
                                                        echo '<div class="association-info association-none">';
                                                        echo '<i class="dashicons dashicons-minus"></i>';
                                                        echo '<span>' . __('Nessuna associazione', 'dokan-mod') . '</span>';
                                                        echo '</div>';
                                                    }
                                                    break;
                                                    
                                                case 'citta_multi':
                                                    echo '<div class="association-info association-multi">';
                                                    echo '<i class="dashicons dashicons-admin-multisite"></i>';
                                                    echo '<div class="association-content">';
                                                    echo '<strong>' . __('Multi annuncio', 'dokan-mod') . '</strong>';
                                                    
                                                    // Build configuration description
                                                    $config_parts = [];
                                                    
                                                    // Days range
                                                    $days_range = $layout_config['days_range'] ?? 7;
                                                    if ($days_range == 1) {
                                                        $config_parts[] = __('Solo oggi', 'dokan-mod');
                                                    } else {
                                                        $config_parts[] = sprintf(__('Ultimi %d giorni', 'dokan-mod'), $days_range);
                                                    }
                                                    
                                                    // Agency scope
                                                    if ($layout_config['show_all_agencies'] ?? false) {
                                                        $config_parts[] = __('Tutte le agenzie', 'dokan-mod');
                                                    } else {
                                                        $config_parts[] = __('Solo la tua agenzia', 'dokan-mod');
                                                    }
                                                    
                                                    echo '<small>' . implode(' • ', $config_parts) . '</small>';
                                                    echo '</div></div>';
                                                    break;
                                                    
                                                default:
                                                    echo '<div class="association-info association-none">';
                                                    echo '<i class="dashicons dashicons-minus"></i>';
                                                    echo '<span>' . __('Non configurato', 'dokan-mod') . '</span>';
                                                    echo '</div>';
                                                    break;
                                            }
                                            ?>
                                            
                                            <!-- Manage Association Button -->
                                            <div class="association-actions" style="margin-top: 8px;">
                                                <button type="button" 
                                                        class="button button-small manage-association-btn"
                                                        onclick="openAssociationModal(<?php echo $monitor['id']; ?>, '<?php echo esc_js($monitor['layout_type']); ?>', '<?php echo esc_js($monitor['monitor_name']); ?>')" 
                                                        title="<?php _e('Gestisci associazioni per questo monitor', 'dokan-mod'); ?>">
                                                    <i class="dashicons dashicons-admin-users"></i>
                                                    <?php _e('Gestisci', 'dokan-mod'); ?>
                                                </button>
                                            </div>
                                        </td>
                                        
                                        <!-- Layout Selector -->
                                        <td class="monitor-layout-cell">
                                            <form method="post" class="layout-form-inline">
                                                <?php wp_nonce_field('monitor_update_layout', 'monitor_nonce'); ?>
                                                <input type="hidden" name="action" value="update_layout">
                                                <input type="hidden" name="monitor_id" value="<?php echo $monitor['id']; ?>">
                                                
                                                <div class="layout-selector-wrapper">
                                                    <select name="layout_type" class="layout-selector" onchange="this.form.submit()">
                                                        <?php foreach ($available_layouts as $layout_key => $layout_name): ?>
                                                            <option value="<?php echo $layout_key; ?>" <?php selected($monitor['layout_type'], $layout_key); ?>>
                                                                <?php echo esc_html($layout_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    
                                                    <?php if ($monitor['layout_type'] === 'citta_multi'): ?>
                                                        <button type="button" class="layout-config-btn" onclick="toggleCittaMultiConfig('<?php echo $monitor['id']; ?>')" title="<?php _e('Configura', 'dokan-mod'); ?>">
                                                            <i class="dashicons dashicons-admin-settings"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Hidden Città Multi Configuration -->
                                                <?php if ($monitor['layout_type'] === 'citta_multi'): ?>
                                                    <div id="citta_multi_popup_<?php echo $monitor['id']; ?>" class="citta-multi-config-popup" style="display: none;">
                                                        <div class="config-popup-content">
                                                            <h4><?php _e('Configurazione Città Multi-Agenzia', 'dokan-mod'); ?></h4>
                                                            <div class="config-row">
                                                                <label for="days_range_<?php echo $monitor['id']; ?>"><?php _e('Giorni:', 'dokan-mod'); ?></label>
                                                                <select name="days_range" id="days_range_<?php echo $monitor['id']; ?>">
                                                                    <option value="1" <?php selected($layout_config['days_range'] ?? 7, 1); ?>><?php _e('Solo oggi', 'dokan-mod'); ?></option>
                                                                    <option value="3" <?php selected($layout_config['days_range'] ?? 7, 3); ?>><?php _e('3 giorni', 'dokan-mod'); ?></option>
                                                                    <option value="7" <?php selected($layout_config['days_range'] ?? 7, 7); ?>><?php _e('7 giorni', 'dokan-mod'); ?></option>
                                                                    <option value="14" <?php selected($layout_config['days_range'] ?? 7, 14); ?>><?php _e('14 giorni', 'dokan-mod'); ?></option>
                                                                    <option value="30" <?php selected($layout_config['days_range'] ?? 7, 30); ?>><?php _e('30 giorni', 'dokan-mod'); ?></option>
                                                                </select>
                                                            </div>
                                                            <div class="config-row">
                                                                <label>
                                                                    <input type="checkbox" name="show_all_agencies" value="1" <?php checked($layout_config['show_all_agencies'] ?? false); ?>>
                                                                    <?php _e('Tutte le agenzie', 'dokan-mod'); ?>
                                                                </label>
                                                            </div>
                                                            <div class="config-actions">
                                                                <button type="submit" class="button button-primary button-small"><?php _e('Salva', 'dokan-mod'); ?></button>
                                                                <button type="button" class="button button-secondary button-small" onclick="toggleCittaMultiConfig('<?php echo $monitor['id']; ?>')"><?php _e('Annulla', 'dokan-mod'); ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="monitor-actions-cell">
                                            <div class="action-buttons">
                                                <a href="<?php echo esc_url($monitor_url); ?>" 
                                                   target="_blank" 
                                                   class="button button-primary button-small monitor-live-btn"
                                                   title="<?php _e('Visualizza Monitor Live', 'dokan-mod'); ?>">
                                                    <i class="dashicons dashicons-visibility"></i>
                                                    <span class="button-text"><?php _e('Monitor Live', 'dokan-mod'); ?></span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div><!-- .dokan-dashboard-content -->

        </div><!-- .dokan-dashboard-wrap -->

        </div>
    </div>

</main>

<!-- CSS Styles for Modal -->
<style>
/* Table Column Widths Optimization */
.monitors-table {
    table-layout: fixed;
    width: 100%;
}

.monitor-name-col {
    width: 20%;
}

.monitor-url-col {
    width: 15%; /* Reduced from default */
}

.monitor-status-col {
    width: 12%;
}

.monitor-association-col {
    width: 18%;
}

.monitor-layout-col {
    width: 20%; /* Increased for better dropdown visibility */
}

.monitor-actions-col {
    width: 15%;
}

/* URL Display Optimization */
.url-display {
    display: flex;
    align-items: center;
    gap: 6px;
}

.monitor-url-code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 11px;
    background-color: #f1f1f1;
    padding: 4px 6px;
    border-radius: 3px;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: help;
    flex: 1;
}

.copy-url-btn {
    padding: 2px 4px;
    font-size: 12px;
    line-height: 1;
    min-height: auto;
    height: 24px;
    width: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 3px;
}

.copy-url-btn.compact {
    padding: 1px 2px;
    height: 20px;
    width: 20px;
}

.copy-url-btn .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.copy-url-btn.compact .dashicons {
    font-size: 12px;
    width: 12px;
    height: 12px;
}

/* Layout Selector Optimization */
.layout-selector-wrapper {
    min-width: 140px; /* Ensure minimum width for dropdown */
}

.layout-selector {
    width: 100%;
    min-width: 140px;
    font-size: 13px;
    padding: 4px 8px;
}

/* Monitor Modal Styles */
.monitor-modal {
    position: fixed;
    z-index: 999999 !important;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease-out;
    backdrop-filter: blur(2px);
    padding: 20px;
    box-sizing: border-box;
}

.monitor-modal.show {
    opacity: 1;
}

.monitor-modal-content {
    background-color: #fefefe;
    width: 80%;
    max-width: 900px;
    max-height: 90vh;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    position: relative;
    margin: 0 auto;
    transform: scale(0.9);
    transition: transform 0.3s ease-out;
    display: flex;
    flex-direction: column;
}

.monitor-modal.show .monitor-modal-content {
    transform: scale(1);
}

.monitor-modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.monitor-modal-header h2 {
    margin: 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.monitor-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    background: none;
    border: none;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.monitor-modal-close:hover,
.monitor-modal-close:focus {
    color: #000;
    background-color: rgba(0, 0, 0, 0.05);
}

.monitor-modal-body {
    padding: 25px;
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.monitor-modal-footer {
    padding: 15px 25px;
    border-top: 1px solid #ddd;
    text-align: right;
    background-color: #f8f9fa;
    border-radius: 0 0 8px 8px;
}

.monitor-modal-footer button {
    margin-left: 10px;
}

/* Association Modal Specific Styles */
.association-modal-content {
    /* Inherits from .monitor-modal-content */
}

.modal-layout-explanation {
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    padding: 15px 25px;
    margin: 0;
}

.modal-layout-explanation p {
    margin: 0;
    font-size: 1rem;
    color: #666;
    text-align: center;
}

.modal-layout-explanation strong {
    color: #2271b1;
    font-weight: 600;
}

.search-form-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-form-wrapper input {
    flex: 1;
    max-width: 400px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.defunti-table {
    width: 100%;
    border-collapse: collapse;
}

.defunti-table th {
    background: #f1f1f1;
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.defunti-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.defunto-foto {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
}

.defunto-foto-placeholder {
    width: 50px;
    height: 50px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #ccc;
}

.association-action-btn {
    margin-right: 8px;
    margin-bottom: 4px;
}

.association-action-btn.associated {
    background-color: #d63638;
    border-color: #d63638;
    color: white !important;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.association-action-btn.not-associated {
    background-color: #2271b1;
    border-color: #2271b1;
    color: white;
}

.association-actions .button {
    margin: 2px;
}

.manage-association-btn {
    background-color: #50575e;
    border-color: #50575e;
    color: white;
}

.manage-association-btn:hover {
    background-color: #3c434a;
    border-color: #3c434a;
}

/* Prevent body scroll when modal is open */
body.modal-open {
    overflow: hidden !important;
}

/* Ensure footer is always visible */
body.dokan-dashboard {
    min-height: 100vh !important;
    display: flex !important;
    flex-direction: column !important;
}

body.dokan-dashboard main {
    flex: 1 !important;
}

/* Ensure modal is above WordPress admin bar */
@media screen and (min-width: 783px) {
    .admin-bar .monitor-modal {
        z-index: 99999 !important;
    }
}

@media screen and (max-width: 782px) {
    .admin-bar .monitor-modal {
        z-index: 99999 !important;
    }
}

@media (max-width: 768px) {
    .association-modal-content {
        max-width: 95%;
        max-height: 95vh;
    }
    
    .search-form-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-form-wrapper input {
        max-width: 100%;
        margin-bottom: 10px;
    }
}
</style>

<script>
// Global variables for modal management
let currentModalMonitorId = null;
let currentModalLayoutType = null;
let currentModalMonitorName = null;
let currentDefuntiPage = 1;
let defuntiSearchTerm = '';

// Open Association Modal
function openAssociationModal(monitorId, layoutType, monitorName) {
    currentModalMonitorId = monitorId;
    currentModalLayoutType = layoutType;
    currentModalMonitorName = monitorName;
    
    // Update modal title and layout explanation
    document.getElementById('modal-monitor-name').textContent = monitorName;
    
    const layoutNameElement = document.getElementById('modal-layout-name');
    layoutNameElement.textContent = getLayoutDisplayName(layoutType);
    
    // Reset search
    document.getElementById('defunto-search').value = '';
    defuntiSearchTerm = '';
    currentDefuntiPage = 1;
    
    // Show modal
    const modal = document.getElementById('association-modal');
    document.body.classList.add('modal-open');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
        loadDefuntiForModal();
    }, 10);
}

// Close Association Modal
function closeAssociationModal() {
    const modal = document.getElementById('association-modal');
    modal.classList.remove('show');
    document.body.classList.remove('modal-open');
    setTimeout(() => {
        modal.style.display = 'none';
        currentModalMonitorId = null;
        currentModalLayoutType = null;
        currentModalMonitorName = null;
    }, 300);
}

// Load defunti data for modal via AJAX
function loadDefuntiForModal(page = 1) {
    document.getElementById('defunti-loading').style.display = 'block';
    document.getElementById('defunti-modal-table').style.opacity = '0.5';
    
    const formData = new FormData();
    formData.append('action', 'monitor_get_defunti_for_association');
    formData.append('nonce', '<?php echo wp_create_nonce("monitor_association_nonce"); ?>');
    formData.append('monitor_id', currentModalMonitorId);
    formData.append('layout_type', currentModalLayoutType);
    formData.append('search', defuntiSearchTerm);
    formData.append('page', page);
    
    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderDefuntiTable(data.data.defunti);
            renderDefuntiPagination(data.data.pagination);
            currentDefuntiPage = page;
        } else {
            console.error('Error loading defunti:', data.data);
            document.getElementById('defunti-table-body').innerHTML = 
                '<tr><td colspan="5">Errore nel caricamento defunti: ' + (data.data || 'Errore sconosciuto') + '</td></tr>';
        }
    })
    .catch(error => {
        console.error('Network error:', error);
        document.getElementById('defunti-table-body').innerHTML = 
            '<tr><td colspan="5">Errore di rete nel caricamento defunti</td></tr>';
    })
    .finally(() => {
        document.getElementById('defunti-loading').style.display = 'none';
        document.getElementById('defunti-modal-table').style.opacity = '1';
    });
}

// Render defunti table
function renderDefuntiTable(defunti) {
    const tbody = document.getElementById('defunti-table-body');
    
    if (defunti.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5">Nessun defunto trovato.</td></tr>';
        return;
    }
    
    tbody.innerHTML = defunti.map(defunto => {
        const fotoHtml = defunto.foto ? 
            `<img src="${defunto.foto}" alt="${defunto.nome}" class="defunto-foto">` :
            `<div class="defunto-foto-placeholder"><i class="dashicons dashicons-admin-users"></i></div>`;
            
        const actions = createAssociationActions(defunto);
        
        return `
            <tr>
                <td>${fotoHtml}</td>
                <td><strong>${defunto.nome}</strong></td>
                <td>${defunto.data_morte || defunto.data_pubblicazione}</td>
                <td>${defunto.data_pubblicazione}</td>
                <td class="association-actions">${actions}</td>
            </tr>
        `;
    }).join('');
}

// Create association action buttons
function createAssociationActions(defunto) {
    const isAssociated = defunto.is_associated;
    const layoutDisplay = getLayoutDisplayName(currentModalLayoutType);
    
    if (currentModalLayoutType === 'citta_multi') {
        return '<span class="button button-secondary button-small disabled">N/A per layout città multi</span>';
    }
    
    if (isAssociated) {
        return `
            <button type="button" 
                    class="button button-secondary button-small association-action-btn associated" 
                    onclick="toggleDefuntoAssociation(${defunto.id}, false)"
                    title="Rimuovi da ${currentModalMonitorName}">
                <i class="dashicons dashicons-minus"></i>
                Rimuovi da ${layoutDisplay}
            </button>
        `;
    } else {
        return `
            <button type="button" 
                    class="button button-primary button-small association-action-btn not-associated" 
                    onclick="toggleDefuntoAssociation(${defunto.id}, true)"
                    title="Associa a ${currentModalMonitorName}">
                <i class="dashicons dashicons-plus"></i>
                Associa a ${layoutDisplay}
            </button>
        `;
    }
}

// Toggle defunto association
function toggleDefuntoAssociation(defuntoId, associate) {
    const action = associate ? 'add_defunto' : 'remove_defunto';
    const actionText = associate ? 'Associa' : 'Rimuovi';
    
    if (!confirm(`${actionText} questo defunto ${associate ? 'a' : 'da'} ${currentModalMonitorName}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'monitor_toggle_defunto_association');
    formData.append('nonce', '<?php echo wp_create_nonce("monitor_association_nonce"); ?>');
    formData.append('monitor_id', currentModalMonitorId);
    formData.append('post_id', defuntoId);
    formData.append('association_action', action);
    
    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload both modal and main table
            loadDefuntiForModal(currentDefuntiPage);
            location.reload(); // Refresh main page to show updated associations
        } else {
            alert('Errore: ' + (data.data || 'Operazione fallita'));
        }
    })
    .catch(error => {
        console.error('Network error:', error);
        alert('Errore di rete durante l\'operazione');
    });
}

// Render pagination for modal
function renderDefuntiPagination(pagination) {
    const container = document.getElementById('defunti-pagination');
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<div class="tablenav-pages">';
    html += `<span class="displaying-num">${pagination.total_items} elementi</span>`;
    
    if (pagination.current_page > 1) {
        html += `<button class="button" onclick="loadDefuntiForModal(${pagination.current_page - 1})">« Precedente</button>`;
    }
    
    // Page numbers (show 5 pages max)
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === pagination.current_page) {
            html += `<span class="button button-primary" style="margin: 0 2px;">${i}</span>`;
        } else {
            html += `<button class="button" style="margin: 0 2px;" onclick="loadDefuntiForModal(${i})">${i}</button>`;
        }
    }
    
    if (pagination.current_page < pagination.total_pages) {
        html += `<button class="button" onclick="loadDefuntiForModal(${pagination.current_page + 1})">Successiva »</button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('defunto-search');
    const searchBtn = document.getElementById('search-defunto-btn');
    const resetBtn = document.getElementById('reset-search-btn');
    
    if (searchInput) {
        // Search on button click
        searchBtn.addEventListener('click', function() {
            defuntiSearchTerm = searchInput.value.trim();
            currentDefuntiPage = 1;
            loadDefuntiForModal(1);
        });
        
        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchBtn.click();
            }
        });
        
        // Reset search
        resetBtn.addEventListener('click', function() {
            searchInput.value = '';
            defuntiSearchTerm = '';
            currentDefuntiPage = 1;
            loadDefuntiForModal(1);
        });
    }
});

// Helper function to get layout display name
function getLayoutDisplayName(layoutType) {
    const layoutNames = {
        'manifesti': 'Manifesti',
        'solo_annuncio': 'Solo Annuncio',
        'citta_multi': 'Città Multi-Agenzia'
    };
    return layoutNames[layoutType] || layoutType;
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('association-modal');
    if (event.target === modal) {
        closeAssociationModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('association-modal');
        if (modal && modal.style.display !== 'none') {
            closeAssociationModal();
        }
    }
});

// Monitor URL Copy Function
function copyMonitorUrl(monitorId) {
    const monitorRow = document.querySelector(`[data-monitor-id="${monitorId}"]`);
    if (!monitorRow) return;
    
    const url = monitorRow.querySelector('.monitor-url-code').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            // Show success feedback
            showSuccessMessage('URL copiato negli appunti!');
        }).catch(function() {
            fallbackCopyToClipboard(url);
        });
    } else {
        fallbackCopyToClipboard(url);
    }
}

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        showSuccessMessage('URL copiato negli appunti!');
    } catch (err) {
        showErrorMessage('Impossibile copiare automaticamente. URL: ' + text);
    }
    document.body.removeChild(textArea);
}

function showSuccessMessage(message) {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #46b450;
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 100001;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

function showErrorMessage(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #d63638;
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 100001;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}
</script>

<!-- Association Modal - Positioned outside main structure for proper overlay -->
<div id="association-modal" class="monitor-modal" style="display: none;">
    <div class="monitor-modal-content association-modal-content">
        <div class="monitor-modal-header">
            <h2 id="modal-title">
                <i class="dashicons dashicons-admin-users"></i>
                Gestisci Associazioni - <span id="modal-monitor-name">Monitor</span>
            </h2>
            <span class="monitor-modal-close" onclick="closeAssociationModal()">&times;</span>
        </div>
        <div class="modal-layout-explanation">
            <p id="modal-layout-info-text">Stai associando defunti al layout <strong id="modal-layout-name">Layout</strong></p>
        </div>
        <div class="monitor-modal-body">
            <!-- Search Form -->
            <div class="defunto-search-section" style="margin-bottom: 20px;">
                <div class="search-form-wrapper">
                    <input type="text" 
                           id="defunto-search" 
                           placeholder="<?php _e('Cerca per nome defunto...', 'dokan-mod'); ?>" 
                           style="width: 100%; margin-bottom: 10px;">
                    <button type="button" id="search-defunto-btn" class="button">
                        <i class="dashicons dashicons-search"></i>
                        <?php _e('Cerca', 'dokan-mod'); ?>
                    </button>
                    <button type="button" id="reset-search-btn" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('Reset', 'dokan-mod'); ?>
                    </button>
                </div>
            </div>

            <!-- Defunti Table -->
            <div id="defunti-table-container" class="defunti-section">
                <div id="defunti-loading" class="loading-state" style="display: none; text-align: center; padding: 40px;">
                    <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                    <p>Caricamento defunti...</p>
                </div>
                
                <table class="defunti-table wp-list-table widefat fixed striped" id="defunti-modal-table">
                    <thead>
                        <tr>
                            <th><?php _e('Foto', 'dokan-mod'); ?></th>
                            <th><?php _e('Nome Defunto', 'dokan-mod'); ?></th>
                            <th><?php _e('Data Morte', 'dokan-mod'); ?></th>
                            <th><?php _e('Pubblicazione', 'dokan-mod'); ?></th>
                            <th><?php _e('Azioni', 'dokan-mod'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="defunti-table-body">
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
                
                <!-- Pagination for modal -->
                <div id="defunti-pagination" class="pagination" style="margin: 20px 0; text-align: center;">
                    <!-- Populated via AJAX -->
                </div>
            </div>
        </div>
        <div class="monitor-modal-footer">
            <button type="button" class="button button-secondary" onclick="closeAssociationModal()">
                <?php _e('Chiudi', 'dokan-mod'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Monitor-specific CSS - common dashboard styles now loaded via dokan-dashboard-common.css */

/* Responsive adjustments */
@media (max-width: 1024px) {
    /* Adjust column widths for medium screens */
    .monitor-url-col {
        width: 12%;
    }
    
    .monitor-layout-col {
        width: 18%;
    }
    
    .monitor-url-code {
        max-width: 100px;
        font-size: 10px;
    }
}

@media (max-width: 768px) {
    body.dokan-dashboard.theme-hello-elementor .dokan-dashboard-wrap,
    body.dokan-dashboard .dokan-dashboard-wrap {
        margin: 0 10px !important;
        max-width: calc(100% - 20px) !important;
    }
    
    .dokan-dashboard-content {
        padding: 15px;
    }
    
    /* Stack table columns on mobile */
    .monitors-table {
        table-layout: auto;
    }
    
    .monitor-url-cell .url-display {
        flex-direction: column;
        gap: 4px;
        align-items: flex-start;
    }
    
    .monitor-url-code {
        max-width: none;
        width: 100%;
        font-size: 9px;
    }
    
    .copy-url-btn.compact {
        height: 18px;
        width: 18px;
        align-self: flex-start;
    }
    
    .layout-selector {
        min-width: 120px;
        font-size: 12px;
    }
    
    /* Modal responsive adjustments */
    .monitor-modal-content {
        width: 95%;
        max-width: 95%;
        margin: 2% auto;
        max-height: 95vh;
    }
    
    .monitor-modal-header {
        padding: 15px 20px;
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .monitor-modal-header h2 {
        font-size: 1.3em;
    }
    
    .monitor-modal-body {
        padding: 20px;
        max-height: calc(95vh - 120px);
    }
    
    .monitor-modal-footer {
        padding: 15px 20px;
    }
    
    .search-form-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-form-wrapper input {
        max-width: 100%;
        margin-bottom: 10px;
    }
}
</style>

<?php
get_footer();
?>
