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

// Check if vendor has any enabled monitors - redirect if not
if (!$monitor_class->is_vendor_enabled($user_id)) {
    wp_redirect(site_url('/dashboard'));
    exit;
}

// Get vendor monitors
$vendor_monitors = $db_manager->get_vendor_monitors($user_id);

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
            } elseif ($layout_type === 'manifesti') {
                $layout_config = [
                    'grid_rows' => intval($_POST['grid_rows'] ?? 1),
                    'grid_columns' => intval($_POST['grid_columns'] ?? 1),
                    'show_only_own_manifesti' => (bool)($_POST['show_only_own_manifesti'] ?? false)
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

                    <!-- Monitors Cards Grid -->
                    <div class="monitors-cards-container">
                        <div class="monitors-grid">
                            <?php foreach ($vendor_monitors as $monitor): 
                                $layout_config = dkmod_safe_decode_layout_config($monitor['layout_config']);
                                $associated_post_title = $monitor['associated_post_id'] ? get_the_title($monitor['associated_post_id']) : null;
                                $monitor_url = home_url('/monitor/display/' . $user_id . '/' . $monitor['id'] . '/' . $monitor['monitor_slug']);
                            ?>
                                <div class="monitor-card"
                                     data-monitor-id="<?php echo $monitor['id']; ?>"
                                     data-layout-config="<?php echo esc_attr(wp_json_encode($layout_config)); ?>">
                                    <!-- Card Header -->
                                    <div class="monitor-card-header">
                                        <div class="monitor-name-section">
                                            <h3 class="monitor-name"><?php echo esc_html($monitor['monitor_name']); ?></h3>
                                            <?php if ($monitor['monitor_description']): ?>
                                                <p class="monitor-description"><?php echo esc_html($monitor['monitor_description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="monitor-status">
                                            <?php if ($monitor['associated_post_id']): ?>
                                                <div class="status-badge status-active">
                                                    <i class="dashicons dashicons-yes"></i>
                                                    <span><?php _e('ATTIVO', 'dokan-mod'); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="status-badge status-inactive">
                                                    <i class="dashicons dashicons-warning"></i>
                                                    <span><?php _e('INATTIVO', 'dokan-mod'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Card Body -->
                                    <div class="monitor-card-body">
                                        <!-- URL Display Section -->
                                        <div class="card-section url-section">
                                            <label class="section-label"><?php _e('URL Display', 'dokan-mod'); ?></label>
                                            <div class="url-display">
                                                <?php 
                                                // Extract path and show up to monitor/display
                                                $parsed_url = parse_url($monitor_url);
                                                $display_path = $parsed_url['path'] ?? '';
                                                
                                                // Find the monitor/display part and include it
                                                if (strpos($display_path, 'monitor/display') !== false) {
                                                    $monitor_pos = strpos($display_path, 'monitor/display');
                                                    $short_display = substr($display_path, $monitor_pos);
                                                } else {
                                                    // Fallback to showing last 3 segments if monitor/display not found
                                                    $path_segments = array_filter(explode('/', $display_path));
                                                    $display_segments = array_slice($path_segments, -3);
                                                    $short_display = '.../' . implode('/', $display_segments);
                                                }
                                                ?>
                                                <code class="monitor-url-code" title="<?php echo esc_attr($monitor_url); ?>"><?php echo esc_html($short_display); ?></code>
                                                <button type="button" class="copy-url-btn" onclick="copyMonitorUrl('<?php echo $monitor['id']; ?>')" title="<?php _e('Copia URL completo', 'dokan-mod'); ?>">
                                                    <i class="dashicons dashicons-admin-page"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Association Section -->
                                        <div class="card-section association-section">
                                            <label class="section-label"><?php _e('Associazione', 'dokan-mod'); ?></label>
                                            <div class="association-content">
                                                <?php
                                                switch ($monitor['layout_type']) {
                                                    case 'solo_annuncio':
                                                    case 'manifesti':
                                                        if ($monitor['associated_post_id'] && $associated_post_title) {
                                                            echo '<div class="association-info association-single association-clickable" ';
                                                            echo 'onclick="openAssociationModal(' . $monitor['id'] . ', \'' . esc_js($monitor['layout_type']) . '\', \'' . esc_js($monitor['monitor_name']) . '\')" ';
                                                            echo 'title="' . __('Clicca per gestire associazioni', 'dokan-mod') . '">';
                                                            echo '<i class="dashicons dashicons-admin-post association-icon"></i>';
                                                            echo '<div class="association-text">';
                                                            echo '<strong>' . esc_html($associated_post_title) . '</strong>';
                                                            
                                                            // Show grid configuration for manifesti layout
                                                            if ($monitor['layout_type'] === 'manifesti') {
                                                                $grid_rows = intval($layout_config['grid_rows'] ?? 1);
                                                                $grid_columns = intval($layout_config['grid_columns'] ?? 1);
                                                                echo '<small class="grid-config-info">' . sprintf(__('Griglia: %dx%d', 'dokan-mod'), $grid_rows, $grid_columns) . '</small>';

                                                                // Show manifesti filter configuration
                                                                $show_only_own = !empty($layout_config['show_only_own_manifesti']);
                                                                $manifesti_filter_text = $show_only_own ? __('Solo i tuoi manifesti', 'dokan-mod') : __('Tutti i manifesti', 'dokan-mod');
                                                                echo '<small class="grid-config-info">' . esc_html($manifesti_filter_text) . '</small>';
                                                            }
                                                            
                                                            // Get post date for additional info
                                                            $post_date = get_the_date('d/m/Y', $monitor['associated_post_id']);
                                                            echo '<small>' . sprintf(__('Pubblicato: %s', 'dokan-mod'), $post_date) . '</small>';
                                                            echo '</div>';
                                                            echo '<i class="dashicons dashicons-edit association-edit-icon"></i>';
                                                            echo '</div>';
                                                        } else {
                                                            echo '<div class="association-info association-none association-clickable" ';
                                                            echo 'onclick="openAssociationModal(' . $monitor['id'] . ', \'' . esc_js($monitor['layout_type']) . '\', \'' . esc_js($monitor['monitor_name']) . '\')" ';
                                                            echo 'title="' . __('Clicca per aggiungere associazioni', 'dokan-mod') . '">';
                                                            echo '<i class="dashicons dashicons-minus association-icon"></i>';
                                                            echo '<span>' . __('Nessuna associazione', 'dokan-mod') . '</span>';
                                                            echo '<i class="dashicons dashicons-edit association-edit-icon"></i>';
                                                            echo '</div>';
                                                        }
                                                        break;
                                                        
                                                    case 'citta_multi':
                                                        echo '<div class="association-info association-multi association-clickable" ';
                                                        echo 'onclick="openAssociationModal(' . $monitor['id'] . ', \'' . esc_js($monitor['layout_type']) . '\', \'' . esc_js($monitor['monitor_name']) . '\')" ';
                                                        echo 'title="' . __('Clicca per configurare layout multi-agenzia', 'dokan-mod') . '">';
                                                        echo '<i class="dashicons dashicons-admin-multisite association-icon"></i>';
                                                        echo '<div class="association-text">';
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
                                                        echo '</div>';
                                                        echo '<i class="dashicons dashicons-edit association-edit-icon"></i>';
                                                        echo '</div>';
                                                        break;
                                                        
                                                    default:
                                                        echo '<div class="association-info association-none association-clickable" ';
                                                        echo 'onclick="openAssociationModal(' . $monitor['id'] . ', \'' . esc_js($monitor['layout_type']) . '\', \'' . esc_js($monitor['monitor_name']) . '\')" ';
                                                        echo 'title="' . __('Clicca per configurare associazioni', 'dokan-mod') . '">';
                                                        echo '<i class="dashicons dashicons-minus association-icon"></i>';
                                                        echo '<span>' . __('Non configurato', 'dokan-mod') . '</span>';
                                                        echo '<i class="dashicons dashicons-edit association-edit-icon"></i>';
                                                        echo '</div>';
                                                        break;
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <!-- Layout Section -->
                                        <div class="card-section layout-section">
                                            <label class="section-label"><?php _e('Layout', 'dokan-mod'); ?></label>
                                            <form method="post" class="layout-form">
                                                <?php wp_nonce_field('monitor_update_layout', 'monitor_nonce'); ?>
                                                <input type="hidden" name="action" value="update_layout">
                                                <input type="hidden" name="monitor_id" value="<?php echo $monitor['id']; ?>">
                                                
                                                <div class="layout-controls">
                                                    <select name="layout_type" class="layout-selector" onchange="this.form.submit()">
                                                        <?php foreach ($available_layouts as $layout_key => $layout_name): ?>
                                                            <option value="<?php echo $layout_key; ?>" <?php selected($monitor['layout_type'], $layout_key); ?>>
                                                                <?php echo esc_html($layout_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    
                                                    <?php if ($monitor['layout_type'] === 'citta_multi'): ?>
                                                        <button type="button" class="layout-config-btn" 
                                                                onclick="openLayoutConfig('<?php echo $monitor['id']; ?>', '<?php echo $monitor['layout_type']; ?>')" 
                                                                title="<?php _e('Configura Layout Città Multi-Agenzia', 'dokan-mod'); ?>">
                                                            <i class="dashicons dashicons-admin-settings"></i>
                                                        </button>
                                                    <?php elseif ($monitor['layout_type'] === 'manifesti'): ?>
                                                        <button type="button" class="layout-config-btn" 
                                                                onclick="openLayoutConfig('<?php echo $monitor['id']; ?>', '<?php echo $monitor['layout_type']; ?>')" 
                                                                title="<?php _e('Configura Grid Manifesti', 'dokan-mod'); ?>">
                                                            <i class="dashicons dashicons-admin-settings"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Card Footer -->
                                    <div class="monitor-card-footer">
                                        <a href="<?php echo esc_url($monitor_url); ?>" 
                                           target="_blank" 
                                           class="button button-primary monitor-live-btn"
                                           title="<?php _e('Visualizza Monitor Live', 'dokan-mod'); ?>">
                                            <i class="dashicons dashicons-visibility"></i>
                                            <span class="button-text"><?php _e('Monitor Live', 'dokan-mod'); ?></span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

            </div><!-- .dokan-dashboard-content -->

        </div><!-- .dokan-dashboard-wrap -->

        </div>
    </div>

</main>

<!-- CSS Styles for Modal -->
<style>
/* Monitor Cards Grid Layout */
.monitors-cards-container {
    width: 100%;
    margin: 20px 0;
}

.monitors-grid {
    display: grid;
    gap: 20px;
    grid-template-columns: 1fr; /* Mobile first: 1 column */
}

/* Responsive grid breakpoints */
@media (min-width: 768px) {
    .monitors-grid {
        grid-template-columns: repeat(2, 1fr); /* Tablet/Desktop: 2 columns */
    }
}

@media (min-width: 1400px) {
    .monitors-grid {
        grid-template-columns: repeat(2, 1fr); /* Large screens: 4 columns */
    }
}

/* Monitor Card Styling */
.monitor-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    min-height: 400px; /* Ensure minimum height for equal sizing */
    overflow: hidden;
}

.monitor-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

/* Card Header */
.monitor-card-header {
    padding: 20px 20px 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.monitor-name-section {
    flex: 1;
}

.monitor-name {
    margin: 0 0 8px 0;
    font-size: 1.2em;
    font-weight: 600;
    color: #2c3338;
    line-height: 1.3;
}

.monitor-description {
    margin: 0;
    font-size: 0.9em;
    color: #666;
    line-height: 1.4;
}

.monitor-status {
    flex-shrink: 0;
}

/* Card Body */
.monitor-card-body {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.card-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.section-label {
    font-weight: 600;
    color: #2c3338;
    font-size: 0.85em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0;
}

/* URL Section Styling */
.url-section .url-display {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.url-section .monitor-url-code {
    flex: 1;
    background: none;
    border: none;
    padding: 0;
    font-size: 0.85em;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    color: #0073aa;
    word-break: break-all;
    line-height: 1.4;
    min-width: 0; /* Prevent flex overflow */
    overflow-wrap: break-word;
    display: flex;
    align-items: center; /* Allineamento verticale al centro */
    justify-content: flex-start; /* Mantiene l'allineamento a sinistra del testo */
}

.url-section .copy-url-btn {
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.url-section .copy-url-btn:hover {
    background: #005a87;
    transform: scale(1.05);
}

/* Association Section */
.association-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.association-info {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #ddd;
    position: relative;
    transition: all 0.3s ease;
}

/* Clickable association info styling */
.association-info.association-clickable {
    cursor: pointer;
    user-select: none;
}

.association-info.association-clickable:hover {
    background: #e8f4f8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.association-info.association-clickable:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

/* Color variations based on association type */
.association-info.association-single {
    border-left-color: #46b450;
}

.association-info.association-single:hover {
    background: #f0f8f1;
    border-left-color: #2d7d32;
}

.association-info.association-multi {
    border-left-color: #0073aa;
}

.association-info.association-multi:hover {
    background: #e7f3ff;
    border-left-color: #005a87;
}

.association-info.association-none {
    border-left-color: #ff9800;
}

.association-info.association-none:hover {
    background: #fff8e1;
    border-left-color: #f57c00;
}

/* Association icon (left side) */
.association-icon {
    flex-shrink: 0;
    font-size: 18px;
    width: 18px;
    height: 18px;
}

/* Association text content */
.association-text {
    flex: 1;
    min-width: 0; /* Prevent flex item from overflowing */
}

.association-text strong {
    display: block;
    margin-bottom: 6px;
    color: #2c3338;
    font-weight: 600;
    line-height: 1.3;
}

.association-text small {
    color: #666;
    font-size: 0.85em;
    line-height: 1.4;
    display: block;
    margin-bottom: 4px;
}

.association-text small.grid-config-info {
    color: #0073aa;
    font-weight: 600;
    background: rgba(0, 115, 170, 0.1);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    display: inline-block;
    margin: 4px 0;
}

/* Edit icon (right side - matita) */
.association-edit-icon {
    position: absolute;
    top: 12px;
    right: 12px;
    font-size: 16px;
    width: 16px;
    height: 16px;
    opacity: 0.6;
    transition: all 0.2s ease;
}

/* Dynamic colors for edit icon based on association type */
.association-single .association-edit-icon {
    color: #46b450;
}

.association-multi .association-edit-icon {
    color: #0073aa;
}

.association-none .association-edit-icon {
    color: #ff9800;
}

/* Hover effects for edit icon */
.association-info.association-clickable:hover .association-edit-icon {
    opacity: 1;
    transform: scale(1.1);
}

.association-single:hover .association-edit-icon {
    color: #2d7d32;
}

.association-multi:hover .association-edit-icon {
    color: #005a87;
}

.association-none:hover .association-edit-icon {
    color: #f57c00;
}

/* Layout Section */
.layout-controls {
    display: flex;
    gap: 8px;
    align-items: center;
}

.layout-selector {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9em;
    background: white;
    cursor: pointer;
}

.layout-config-btn {
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.layout-config-btn:hover {
    background: #005a87;
    transform: scale(1.05);
}

/* Card Footer */
.monitor-card-footer {
    padding: 15px 20px;
    border-top: 1px solid #f0f0f0;
    background: #f8f9fa;
    margin-top: auto; /* Push to bottom */
}

.monitor-live-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    background: #0073aa;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
}

.monitor-live-btn:hover {
    background: #005a87;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,115,170,0.3);
}

/* Status Badges */
.status-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 0.75em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.status-badge.status-active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-badge.status-inactive {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Mobile Responsive Adjustments */
@media (max-width: 767px) {
    .monitor-card {
        min-height: 350px;
    }
    
    .monitor-card-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
        padding: 15px;
    }
    
    .monitor-status {
        align-self: flex-start;
    }
    
    .monitor-card-body {
        padding: 15px;
        gap: 15px;
    }
    
    .section-label {
        font-size: 0.8em;
    }
    
    .monitor-name {
        font-size: 1.1em;
    }
    
    .url-section .monitor-url-code {
        font-size: 0.75em;
        line-height: 1.3;
        word-break: break-all;
        display: flex;
        align-items: center; /* Allineamento verticale mobile */
        justify-content: flex-start;
    }
    
    .url-section .url-display {
        padding: 8px;
        align-items: center; /* Mantiene allineamento verticale su mobile */
    }
    
    .url-section .copy-url-btn {
        flex-shrink: 0;
    }
    
    .layout-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .layout-config-btn {
        align-self: center;
        padding: 10px 15px;
    }
    
    .monitor-card-footer {
        padding: 12px 15px;
    }
    
    .monitor-live-btn {
        padding: 10px 16px;
        font-size: 0.9em;
    }
    
    /* Association mobile adjustments */
    .association-info {
        padding: 14px;
    }
    
    .association-edit-icon {
        top: 10px;
        right: 10px;
        font-size: 15px;
        width: 15px;
        height: 15px;
    }
    
    .association-text strong {
        font-size: 0.95em;
    }
    
    .association-text small {
        font-size: 0.8em;
    }
}

/* Tablet Adjustments */
@media (min-width: 768px) and (max-width: 1199px) {
    .monitor-card {
        min-height: 420px;
    }
    
    .monitor-card-header {
        padding: 18px;
    }
    
    .monitor-card-body {
        padding: 18px;
    }
    
    /* URL optimizations for tablet */
    .url-section .monitor-url-code {
        font-size: 0.8em;
        line-height: 1.4;
        display: flex;
        align-items: center; /* Allineamento verticale tablet */
        justify-content: flex-start;
    }
}

/* Large Desktop Adjustments (4 columns) */
@media (min-width: 1400px) {
    .monitor-card {
        min-height: 450px;
    }
    
    .monitor-name {
        font-size: 1.1em;
    }
    
    .section-label {
        font-size: 0.8em;
    }
    
    /* URL optimizations for large screens (4 columns) */
    .url-section .monitor-url-code {
        font-size: 0.75em;
        line-height: 1.3;
        display: flex;
        align-items: center; /* Allineamento verticale desktop largo */
        justify-content: flex-start;
    }
    
    .url-section .url-display {
        padding: 10px 8px;
    }
}

/* Legacy table styles removed - now using card layout */

/* Legacy layout selector styles removed - now handled in card layout */

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
        try {
            const associationModal = document.getElementById('association-modal');
            const configModal = document.getElementById('config-modal');
            
            if (associationModal && associationModal.style.display !== 'none' && associationModal.classList.contains('show')) {
                closeAssociationModal();
            } else if (configModal && configModal.style.display !== 'none' && configModal.classList.contains('show')) {
                closeConfigModal();
            }
        } catch (error) {
            console.error('Error handling escape key:', error);
        }
    }
});

// Monitor URL Copy Function (Updated for card layout)
function copyMonitorUrl(monitorId) {
    const monitorCard = document.querySelector(`[data-monitor-id="${monitorId}"]`);
    if (!monitorCard) return;
    
    const urlCodeElement = monitorCard.querySelector('.monitor-url-code');
    if (!urlCodeElement) return;
    
    // Get the full URL from the title attribute
    const fullUrl = urlCodeElement.getAttribute('title') || urlCodeElement.textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(fullUrl).then(function() {
            // Show success feedback
            showSuccessMessage('URL copiato negli appunti!');
        }).catch(function() {
            fallbackCopyToClipboard(fullUrl);
        });
    } else {
        fallbackCopyToClipboard(fullUrl);
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

// ===== CONFIGURATION MODAL FUNCTIONS =====

// Legacy function for backward compatibility
function toggleCittaMultiConfig(monitorId) {
    openLayoutConfig(monitorId, 'citta_multi');
}

// Open configuration modal for any layout type
function openLayoutConfig(monitorId, layoutType) {
    console.log('Opening layout config for monitor:', monitorId, 'layout:', layoutType);

    // Find monitor card
    const monitorCard = document.querySelector(`[data-monitor-id="${monitorId}"]`);
    if (!monitorCard) {
        console.error('Monitor card not found:', monitorId);
        showErrorMessage('Errore: Monitor non trovato');
        return;
    }

    // Get monitor name
    let monitorName = 'Monitor';
    const nameElement = monitorCard.querySelector('.monitor-name');
    if (nameElement) {
        monitorName = nameElement.textContent.trim();
    }

    console.log('Found monitor name:', monitorName);

    // Get current configuration from data attribute
    let currentConfig = {};
    const layoutConfigData = monitorCard.getAttribute('data-layout-config');

    if (layoutConfigData) {
        try {
            currentConfig = JSON.parse(layoutConfigData);
            console.log('Loaded config from data attribute:', currentConfig);
        } catch (error) {
            console.error('Error parsing layout config:', error);
            currentConfig = {};
        }
    }

    openConfigModal(monitorId, monitorName, layoutType, currentConfig);
}

// Open configuration modal
function openConfigModal(monitorId, monitorName, layoutType, currentConfig = {}) {
    console.log('Opening config modal:', { monitorId, monitorName, layoutType, currentConfig });
    
    try {
        // Validate required elements exist
        const modal = document.getElementById('config-modal');
        const monitorNameSpan = document.getElementById('config-monitor-name');
        const monitorIdInput = document.getElementById('config-monitor-id');
        const layoutTypeInput = document.getElementById('config-layout-type');
        const contentDiv = document.getElementById('config-content');
        
        if (!modal || !monitorNameSpan || !monitorIdInput || !layoutTypeInput || !contentDiv) {
            console.error('Missing required modal elements');
            showErrorMessage('Errore: Elementi modal non trovati');
            return;
        }
        
        // Set modal title and monitor info
        monitorNameSpan.textContent = monitorName || 'Monitor';
        monitorIdInput.value = monitorId;
        layoutTypeInput.value = layoutType;
        
        // Generate config content based on layout type
        const configContent = generateConfigContent(layoutType, currentConfig);
        contentDiv.innerHTML = configContent;
        
        // Show modal
        document.body.classList.add('modal-open');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        console.log('Config modal opened successfully');
        
    } catch (error) {
        console.error('Error opening config modal:', error);
        showErrorMessage('Errore durante l\'apertura della configurazione: ' + error.message);
    }
}

// Close configuration modal
function closeConfigModal() {
    try {
        const modal = document.getElementById('config-modal');
        if (!modal) {
            console.warn('Config modal not found during close');
            return;
        }
        
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
        
        setTimeout(() => {
            modal.style.display = 'none';
            // Clear content
            const contentDiv = document.getElementById('config-content');
            if (contentDiv) {
                contentDiv.innerHTML = '';
            }
        }, 300);
        
        console.log('Config modal closed successfully');
        
    } catch (error) {
        console.error('Error closing config modal:', error);
    }
}

// Generate configuration content based on layout type
function generateConfigContent(layoutType, currentConfig) {
    console.log('Generating config content for layout:', layoutType, currentConfig);
    
    try {
        switch (layoutType) {
            case 'citta_multi':
                return generateCittaMultiConfig(currentConfig || {});
            case 'manifesti':
                return generateManifestiConfig(currentConfig || {});
            case 'solo_annuncio':
                return generateSoloAnnuncioConfig(currentConfig || {});
            default:
                console.warn('Unknown layout type:', layoutType);
                return `<div class="config-section">
                    <h3><i class="dashicons dashicons-admin-generic"></i> Layout: ${layoutType}</h3>
                    <p class="config-description">
                        <i class="dashicons dashicons-info"></i>
                        Nessuna configurazione specifica disponibile per questo tipo di layout.
                    </p>
                    <div class="config-info">
                        <p>Questo layout utilizza le impostazioni predefinite del sistema.</p>
                    </div>
                </div>`;
        }
    } catch (error) {
        console.error('Error generating config content:', error);
        return `<div class="config-section">
            <h3><i class="dashicons dashicons-warning"></i> Errore di Configurazione</h3>
            <p class="config-description" style="background: #ffebee; border-left-color: #f44336;">
                <i class="dashicons dashicons-warning"></i>
                Si è verificato un errore durante la generazione della configurazione.
            </p>
            <div class="config-info">
                <p><strong>Errore:</strong> ${error.message}</p>
                <p>Riprova ad aprire la configurazione o contatta il supporto tecnico.</p>
            </div>
        </div>`;
    }
}

// Generate Città Multi configuration form
function generateCittaMultiConfig(config) {
    const daysRange = parseInt(config.days_range || 7);
    const showAllAgencies = config.show_all_agencies || false;
    
    // Calculate end date (today minus selected days)
    const today = new Date();
    const endDate = new Date(today);
    endDate.setDate(today.getDate() - daysRange);
    
    const formatDate = (date) => {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    };
    
    return `
        <div class="config-section">
            <h3>
                <i class="dashicons dashicons-location-alt"></i>
                Configurazione Città Multi-Agenzia
            </h3>
            <p class="config-description">
                <i class="dashicons dashicons-info"></i>
                Questo layout mostra gli annunci di morte della tua città da tutte le agenzie o solo dalla tua.
                <br><strong>Periodo di visualizzazione:</strong> Seleziona per quanto tempo indietro mostrare gli annunci.
            </p>
            
            <div class="config-row">
                <label for="config-days-range">
                    <strong><i class="dashicons dashicons-calendar-alt"></i> Giorni da visualizzare:</strong>
                </label>
                <div class="days-input-container">
                    <input type="number" 
                           name="days_range" 
                           id="config-days-range" 
                           class="small-text" 
                           value="${daysRange}"
                           min="1" 
                           max="365"
                           step="1"
                           onchange="updateEndDate(this.value)"
                           oninput="updateEndDate(this.value)"
                           style="width: 80px; display: inline-block; vertical-align: middle;">
                    <span style="margin-left: 8px; font-weight: normal;">giorni</span>
                </div>
                <div class="days-shortcuts" style="margin-top: 10px;">
                    <small style="color: #666; margin-right: 8px;"><strong>Scelte rapide:</strong></small>
                    <button type="button" class="button-small days-shortcut-btn" onclick="setDaysValue(1)">1</button>
                    <button type="button" class="button-small days-shortcut-btn" onclick="setDaysValue(3)">3</button>
                    <button type="button" class="button-small days-shortcut-btn" onclick="setDaysValue(7)">7</button>
                    <button type="button" class="button-small days-shortcut-btn" onclick="setDaysValue(14)">14</button>
                    <button type="button" class="button-small days-shortcut-btn" onclick="setDaysValue(30)">30</button>
                </div>
                <p class="description" style="margin-top: 8px;">
                    <strong>Mostra fino al:</strong> <span id="end-date-display" style="color: #0073aa; font-weight: bold;">${formatDate(endDate)}</span>
                    <span style="color: #666; font-style: italic;"> (calcolato: oggi - giorni selezionati)</span>
                </p>
            </div>
            
            <div class="config-row">
                <label>
                    <input type="checkbox" name="show_all_agencies" value="1" ${showAllAgencies ? 'checked' : ''}>
                    <strong><i class="dashicons dashicons-building"></i> Mostra tutte le agenzie</strong>
                </label>
                <p class="description">
                    Se attivato, mostra annunci da tutte le agenzie della città. Se disattivato, mostra solo i tuoi annunci.
                </p>
            </div>
            
            <div class="config-preview">
                <h4><i class="dashicons dashicons-visibility"></i> Anteprima configurazione:</h4>
                <div class="preview-info">
                    <span class="preview-item">📅 Periodo: <strong id="preview-days">${daysRange} ${daysRange === 1 ? 'giorno' : 'giorni'}</strong></span>
                    <span class="preview-item">📆 Fino al: <strong id="preview-end-date">${formatDate(endDate)}</strong></span>
                    <span class="preview-item">🏢 Agenzie: <strong id="preview-agencies">${showAllAgencies ? 'Tutte le agenzie' : 'Solo la tua agenzia'}</strong></span>
                </div>
            </div>
        </div>
    `;
}

// Generate Manifesti configuration form
function generateManifestiConfig(config) {
    const gridRows = parseInt(config.grid_rows || 1);
    const gridColumns = parseInt(config.grid_columns || 1);
    const totalCells = gridRows * gridColumns;
    
    return `
        <div class="config-section">
            <h3>
                <i class="dashicons dashicons-format-gallery"></i>
                Configurazione Grid Manifesti
            </h3>
            <p class="config-description">
                <i class="dashicons dashicons-info"></i>
                Configura la griglia per visualizzare più manifesti contemporaneamente.
                <br><strong>Nota:</strong> I manifesti verranno disposti in una griglia e la slide successiva mostrerà i prossimi ${totalCells} manifesti.
            </p>
            
            <div class="config-row">
                <label for="config-grid-rows">
                    <strong><i class="dashicons dashicons-editor-table"></i> Numero di Righe:</strong>
                </label>
                <div class="grid-input-container">
                    <input type="number" 
                           name="grid_rows" 
                           id="config-grid-rows" 
                           class="small-text" 
                           value="${gridRows}"
                           min="1" 
                           max="10"
                           step="1"
                           onchange="updateGridPreview()"
                           oninput="updateGridPreview()"
                           style="width: 80px;">
                    <span style="margin-left: 8px;">righe</span>
                </div>
                <p class="description">
                    Numero di righe nella griglia (minimo 1)
                </p>
            </div>
            
            <div class="config-row">
                <label for="config-grid-columns">
                    <strong><i class="dashicons dashicons-columns"></i> Numero di Colonne:</strong>
                </label>
                <div class="grid-input-container">
                    <input type="number" 
                           name="grid_columns" 
                           id="config-grid-columns" 
                           class="small-text" 
                           value="${gridColumns}"
                           min="1" 
                           max="10"
                           step="1"
                           onchange="updateGridPreview()"
                           oninput="updateGridPreview()"
                           style="width: 80px;">
                    <span style="margin-left: 8px;">colonne</span>
                </div>
                <p class="description">
                    Numero di colonne nella griglia (minimo 1)
                </p>
            </div>
            
            <div class="config-row">
                <label for="config-show-only-own-manifesti">
                    <strong><i class="dashicons dashicons-visibility"></i> Visualizzazione Manifesti:</strong>
                </label>
                <div>
                    <label>
                        <input type="checkbox"
                               name="show_only_own_manifesti"
                               id="config-show-only-own-manifesti"
                               value="1"
                               ${config.show_only_own_manifesti ? 'checked' : ''}>
                        Mostra solo i tuoi manifesti
                    </label>
                    <p class="description">
                        Se abilitato, verranno visualizzati solo i tuoi manifesti. Altrimenti verranno mostrati tutti i manifesti relativi al defunto.
                    </p>
                </div>
            </div>

            <div class="grid-shortcuts" style="margin: 20px 0;">
                <small style="color: #666; margin-right: 8px;"><strong>Layout rapidi:</strong></small>
                <button type="button" class="button-small grid-shortcut-btn" onclick="setGridValues(1, 1)">1x1</button>
                <button type="button" class="button-small grid-shortcut-btn" onclick="setGridValues(2, 2)">2x2</button>
                <button type="button" class="button-small grid-shortcut-btn" onclick="setGridValues(2, 3)">2x3</button>
                <button type="button" class="button-small grid-shortcut-btn" onclick="setGridValues(3, 3)">3x3</button>
                <button type="button" class="button-small grid-shortcut-btn" onclick="setGridValues(3, 4)">3x4</button>
            </div>
            
            <div class="config-preview">
                <h4><i class="dashicons dashicons-visibility"></i> Anteprima Grid:</h4>
                <div class="grid-preview-wrapper">
                    <div id="grid-preview" class="grid-preview" style="display: grid; grid-template-rows: repeat(${gridRows}, 1fr); grid-template-columns: repeat(${gridColumns}, 1fr); gap: 10px; max-width: 400px; margin: 15px auto;">
                        ${generateGridCells(gridRows, gridColumns)}
                    </div>
                    <p class="preview-info" style="text-align: center; margin-top: 10px;">
                        <strong id="preview-total-cells">${totalCells}</strong> manifesti per slide
                        <br><small style="color: #666;">(${gridRows} righe × ${gridColumns} colonne)</small>
                    </p>
                </div>
            </div>
            
            <div class="config-info" style="margin-top: 20px;">
                <p><strong>Note importanti:</strong></p>
                <ul>
                    <li>📱 La griglia si adatterà automaticamente alle dimensioni del monitor</li>
                    <li>🔄 Se ci sono più manifesti delle celle disponibili, verranno create più slide</li>
                    <li>📐 Il testo dei manifesti verrà ridimensionato in base al numero di celle</li>
                    <li>🖥️ Layout responsive: la disposizione rimane invariata sia in landscape che portrait</li>
                </ul>
            </div>
        </div>
    `;
}

// Helper function to generate grid cells for preview
function generateGridCells(rows, columns) {
    let cells = '';
    for (let i = 1; i <= rows * columns; i++) {
        cells += `<div class="grid-cell" style="background: #f0f0f0; border: 1px solid #ccc; padding: 20px; display: flex; align-items: center; justify-content: center; border-radius: 4px; font-weight: bold; color: #666;">${i}</div>`;
    }
    return cells;
}

// Generate Solo Annuncio configuration form
function generateSoloAnnuncioConfig(config) {
    return `
        <div class="config-section">
            <h3>
                <i class="dashicons dashicons-format-aside"></i>
                Configurazione Layout Solo Annuncio
            </h3>
            <p class="config-description">
                <i class="dashicons dashicons-info"></i>
                Il layout Solo Annuncio mostra un singolo annuncio di morte in modo elegante e leggibile.
            </p>
            <div class="config-info">
                <p><strong>Caratteristiche:</strong></p>
                <ul>
                    <li>📋 Focus su singolo annuncio</li>
                    <li>🖼️ Visualizzazione ottimizzata delle immagini</li>
                    <li>📱 Design pulito e moderno</li>
                </ul>
            </div>
        </div>
    `;
}

// Helper function to get days range label
function getDaysRangeLabel(days) {
    const daysNum = parseInt(days);
    return daysNum === 1 ? '1 giorno' : `${daysNum} giorni`;
}

// Function to update end date display when days input changes
function updateEndDate(days) {
    try {
        const daysNum = parseInt(days) || 1;
        
        // Validate input range
        if (daysNum < 1) {
            document.getElementById('config-days-range').value = 1;
            return updateEndDate(1);
        }
        if (daysNum > 365) {
            document.getElementById('config-days-range').value = 365;
            return updateEndDate(365);
        }
        
        // Calculate new end date
        const today = new Date();
        const endDate = new Date(today);
        endDate.setDate(today.getDate() - daysNum);
        
        const formatDate = (date) => {
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        };
        
        // Update display elements
        const endDateDisplay = document.getElementById('end-date-display');
        const previewDays = document.getElementById('preview-days');
        const previewEndDate = document.getElementById('preview-end-date');
        
        if (endDateDisplay) {
            endDateDisplay.textContent = formatDate(endDate);
        }
        
        if (previewDays) {
            previewDays.textContent = `${daysNum} ${daysNum === 1 ? 'giorno' : 'giorni'}`;
        }
        
        if (previewEndDate) {
            previewEndDate.textContent = formatDate(endDate);
        }
        
        console.log('Updated end date for', daysNum, 'days:', formatDate(endDate));
        
    } catch (error) {
        console.error('Error updating end date:', error);
    }
}

// Function to set days value from shortcut buttons
function setDaysValue(days) {
    try {
        const input = document.getElementById('config-days-range');
        if (input) {
            input.value = days;
            updateEndDate(days);
            
            // Visual feedback on the button
            const buttons = document.querySelectorAll('.days-shortcut-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            const activeButton = document.querySelector(`.days-shortcut-btn[onclick="setDaysValue(${days})"]`);
            if (activeButton) {
                activeButton.classList.add('active');
                setTimeout(() => activeButton.classList.remove('active'), 200);
            }
        }
    } catch (error) {
        console.error('Error setting days value:', error);
    }
}

// Update preview when configuration changes
document.addEventListener('change', function(event) {
    if (event.target.name === 'days_range') {
        // The updateEndDate function already handles this
        updateEndDate(event.target.value);
    }
    
    if (event.target.name === 'show_all_agencies') {
        const previewAgencies = document.getElementById('preview-agencies');
        if (previewAgencies) {
            previewAgencies.textContent = event.target.checked ? 'Tutte le agenzie' : 'Solo la tua agenzia';
        }
    }
});

// Also handle input events for real-time updates
document.addEventListener('input', function(event) {
    if (event.target.name === 'days_range') {
        updateEndDate(event.target.value);
    }
});

// Function to update grid preview when values change
function updateGridPreview() {
    try {
        const rowsInput = document.getElementById('config-grid-rows');
        const columnsInput = document.getElementById('config-grid-columns');
        const gridPreview = document.getElementById('grid-preview');
        const totalCellsSpan = document.getElementById('preview-total-cells');
        
        if (!rowsInput || !columnsInput || !gridPreview || !totalCellsSpan) {
            return;
        }
        
        const rows = Math.max(1, parseInt(rowsInput.value) || 1);
        const columns = Math.max(1, parseInt(columnsInput.value) || 1);
        const totalCells = rows * columns;
        
        // Validate and enforce limits
        if (rows !== parseInt(rowsInput.value)) {
            rowsInput.value = rows;
        }
        if (columns !== parseInt(columnsInput.value)) {
            columnsInput.value = columns;
        }
        
        // Update grid preview
        gridPreview.style.gridTemplateRows = `repeat(${rows}, 1fr)`;
        gridPreview.style.gridTemplateColumns = `repeat(${columns}, 1fr)`;
        gridPreview.innerHTML = generateGridCells(rows, columns);
        
        // Update text
        totalCellsSpan.textContent = totalCells;
        
        // Update the description note
        const noteElement = document.querySelector('.config-description');
        if (noteElement) {
            const noteText = `Configura la griglia per visualizzare più manifesti contemporaneamente.<br><strong>Nota:</strong> I manifesti verranno disposti in una griglia e la slide successiva mostrerà i prossimi ${totalCells} manifesti.`;
            noteElement.innerHTML = `<i class="dashicons dashicons-info"></i>${noteText}`;
        }
        
    } catch (error) {
        console.error('Error updating grid preview:', error);
    }
}

// Function to set grid values from shortcut buttons
function setGridValues(rows, columns) {
    try {
        const rowsInput = document.getElementById('config-grid-rows');
        const columnsInput = document.getElementById('config-grid-columns');
        
        if (rowsInput && columnsInput) {
            rowsInput.value = rows;
            columnsInput.value = columns;
            updateGridPreview();
            
            // Visual feedback on the button
            const buttons = document.querySelectorAll('.grid-shortcut-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            const activeButton = document.querySelector(`.grid-shortcut-btn[onclick="setGridValues(${rows}, ${columns})"]`);
            if (activeButton) {
                activeButton.classList.add('active');
                setTimeout(() => activeButton.classList.remove('active'), 200);
            }
        }
    } catch (error) {
        console.error('Error setting grid values:', error);
    }
}

// Close config modal when clicking outside
window.addEventListener('click', function(event) {
    try {
        const configModal = document.getElementById('config-modal');
        if (configModal && event.target === configModal) {
            closeConfigModal();
        }
    } catch (error) {
        console.error('Error handling outside click:', error);
    }
});
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

<!-- Configuration Modal -->
<div id="config-modal" class="monitor-modal" style="display: none;">
    <div class="monitor-modal-content">
        <div class="monitor-modal-header">
            <h2 id="config-modal-title">
                <i class="dashicons dashicons-admin-settings"></i>
                Configurazione Layout - <span id="config-monitor-name">Monitor</span>
            </h2>
            <span class="monitor-modal-close" onclick="closeConfigModal()">&times;</span>
        </div>
        <div class="monitor-modal-body">
            <form id="config-form" method="post" action="">
                <input type="hidden" name="action" value="update_layout">
                <input type="hidden" name="monitor_nonce" value="<?php echo wp_create_nonce('monitor_update_layout'); ?>">
                <input type="hidden" name="monitor_id" id="config-monitor-id">
                <input type="hidden" name="layout_type" id="config-layout-type">
                
                <div id="config-content">
                    <!-- Dynamic content will be loaded here -->
                </div>
                
                <div class="config-actions" style="margin-top: 25px; text-align: center;">
                    <button type="submit" class="button button-primary button-large">
                        <i class="dashicons dashicons-yes"></i>
                        <?php _e('Salva Configurazione', 'dokan-mod'); ?>
                    </button>
                    <button type="button" class="button button-secondary button-large" onclick="closeConfigModal()">
                        <i class="dashicons dashicons-no"></i>
                        <?php _e('Annulla', 'dokan-mod'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Monitor-specific CSS - common dashboard styles now loaded via dokan-dashboard-common.css */

/* Configuration Modal Styles */
.config-section {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #0073aa;
}

.config-section h3 {
    margin-top: 0;
    color: #23282d;
    font-size: 1.2em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.config-description {
    background: #e7f3ff;
    padding: 12px;
    border-radius: 4px;
    margin: 15px 0;
    border-left: 3px solid #0073aa;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.config-row {
    margin: 20px 0;
    padding: 15px 0;
    border-bottom: 1px solid #e1e1e1;
}

.config-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.config-row label {
    display: block;
    margin-bottom: 8px;
    color: #23282d;
}

.config-row .description {
    color: #666;
    font-style: italic;
    font-size: 0.95em;
    margin-top: 5px;
}

.config-preview {
    background: #ffffff;
    border: 2px solid #0073aa;
    border-radius: 6px;
    padding: 15px;
    margin-top: 25px;
}

.config-preview h4 {
    margin: 0 0 10px 0;
    color: #0073aa;
    display: flex;
    align-items: center;
    gap: 8px;
}

.preview-info {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.preview-item {
    background: #f0f8ff;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #b3d9ff;
    font-size: 0.9em;
}

.config-info {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.config-info ul {
    margin: 10px 0 0 0;
    padding-left: 0;
    list-style: none;
}

.config-info li {
    margin: 8px 0;
    padding: 5px 0;
    border-bottom: 1px dotted #ccc;
}

.config-info li:last-child {
    border-bottom: none;
}

.config-actions {
    text-align: center;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.config-actions .button {
    margin: 0 10px;
    min-width: 150px;
    padding: 8px 20px !important;
    height: auto !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}

/* Layout Configuration Button */
.layout-config-btn {
    background: #0073aa !important;
    color: white !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 6px 8px !important;
    margin-left: 8px !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    vertical-align: middle !important;
    font-size: 14px !important;
    height: auto !important;
    line-height: 1 !important;
}

.layout-config-btn:hover {
    background: #005a87 !important;
    transform: scale(1.05);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.layout-config-btn:active {
    transform: scale(0.98);
}

.layout-config-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Days Input Container Styling */
.days-input-container {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.days-input-container input[type="number"] {
    border: 2px solid #ddd;
    border-radius: 4px;
    padding: 6px 8px;
    font-size: 14px;
    text-align: center;
    transition: border-color 0.2s ease;
}

.days-input-container input[type="number"]:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.days-input-container input[type="number"]:hover {
    border-color: #999;
}

/* Preview End Date Styling */
#end-date-display {
    background: #f0f8ff;
    padding: 4px 8px;
    border-radius: 3px;
    border: 1px solid #b3d9ff;
    font-family: monospace;
}

/* Preview Item Enhancements */
.preview-item {
    background: #f0f8ff;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #b3d9ff;
    font-size: 0.9em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.config-description {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
    border-left: 3px solid #0073aa;
    line-height: 1.5;
}

.config-description strong {
    color: #0073aa;
}

/* Days Shortcut Buttons */
.days-shortcuts {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.days-shortcut-btn {
    background: #f7f7f7;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
    color: #2c3338;
    cursor: pointer;
    display: inline-block;
    font-size: 11px;
    line-height: 1.4;
    margin: 0;
    padding: 3px 8px;
    text-decoration: none;
    white-space: nowrap;
    min-width: 24px;
    text-align: center;
    transition: all 0.2s ease;
}

.days-shortcut-btn:hover {
    background: #0073aa;
    border-color: #0073aa;
    color: #fff;
    transform: translateY(-1px);
}

.days-shortcut-btn:active,
.days-shortcut-btn.active {
    background: #005a87;
    border-color: #005a87;
    color: #fff;
    transform: scale(0.95);
}

.days-shortcuts small {
    flex-shrink: 0;
}

/* Global Dashboard Responsive Adjustments */
@media (max-width: 768px) {
    body.dokan-dashboard.theme-hello-elementor .dokan-dashboard-wrap,
    body.dokan-dashboard .dokan-dashboard-wrap {
        margin: 0 10px !important;
        max-width: calc(100% - 20px) !important;
    }
    
    .dokan-dashboard-content {
        padding: 15px;
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
    
    /* Mobile adjustments for configuration modal */
    .days-input-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .days-input-container input[type="number"] {
        width: 100px;
    }
    
    .days-shortcuts {
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .preview-info {
        flex-direction: column;
        gap: 10px;
    }
    
    .config-preview {
        margin-top: 15px;
    }
    
    .config-row {
        margin: 15px 0;
        padding: 12px 0;
    }
}
</style>

<script>
// Configuration forms use traditional POST submission

// Configuration forms now use traditional POST submission only
// No AJAX handling needed - forms submit directly to PHP
</script>

<?php
get_footer();
?>
