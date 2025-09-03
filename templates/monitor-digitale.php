<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Dokan_Mods\Templates_MiscClass;

// Check if vendor is logged in and enabled for monitor
$template_class = new Templates_MiscClass();
$template_class->check_dokan_can_and_message_login();

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
                                                <code class="monitor-url-code"><?php echo esc_html($monitor_url); ?></code>
                                                <button type="button" class="copy-url-btn" onclick="copyMonitorUrl('<?php echo $monitor['id']; ?>')" title="<?php _e('Copia URL', 'dokan-mod'); ?>">
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

                    <!-- Search Form -->
                    <form method="get" action="<?php echo site_url('/dashboard/monitor-digitale'); ?>" style="display: flex; margin-bottom: 15px;">
                        <input type="text" name="s" value="<?php echo esc_attr(get_query_var('s')); ?>" 
                               placeholder="<?php _e('Cerca per nome defunto...', 'dokan-mod'); ?>" 
                               style="margin-right: 10px; flex: 1; max-width: 400px;">
                        <input type="submit" value="<?php _e('Cerca', 'dokan-mod'); ?>">
                        <?php if (get_query_var('s')): ?>
                            <a href="<?php echo site_url('/dashboard/monitor-digitale'); ?>" class="custom-widget-button" style="margin-left: 10px;">
                                <?php _e('Reset', 'dokan-mod'); ?>
                            </a>
                        <?php endif; ?>
                    </form>

                    <div class="defunti-section">
                        <h3><?php _e('Gestisci Associazioni Defunti', 'dokan-mod'); ?></h3>
                        <table class="defunti-table">
                            <thead>
                            <tr>
                                <th><?php _e('Foto', 'dokan-mod'); ?></th>
                                <th><?php _e('Nome Defunto', 'dokan-mod'); ?></th>
                                <th><?php _e('Data Morte', 'dokan-mod'); ?></th>
                                <th><?php _e('Pubblicazione', 'dokan-mod'); ?></th>
                                <th><?php _e('Monitor Associati', 'dokan-mod'); ?></th>
                                <th><?php _e('Azioni', 'dokan-mod'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            if ($query->have_posts()) :
                                while ($query->have_posts()) : $query->the_post();
                                    $post_id = get_the_ID();
                                    $foto_defunto = get_field('fotografia', $post_id);
                                    $data_morte = get_field('data_di_morte', $post_id);
                                    
                                    // Check which monitors this defunto is associated with
                                    $associated_monitors = [];
                                    foreach ($vendor_monitors as $monitor) {
                                        if ($monitor['associated_post_id'] == $post_id) {
                                            $associated_monitors[] = $monitor;
                                        }
                                    }
                                    
                                    $has_associations = !empty($associated_monitors);
                                    ?>
                                    <tr <?php echo $has_associations ? 'class="has-associations"' : ''; ?>>
                                        <td>
                                            <?php if ($foto_defunto): ?>
                                                <?php 
                                                $foto_url = is_array($foto_defunto) && isset($foto_defunto['sizes']['thumbnail']) 
                                                    ? $foto_defunto['sizes']['thumbnail'] 
                                                    : (is_array($foto_defunto) && isset($foto_defunto['url']) ? $foto_defunto['url'] : $foto_defunto);
                                                ?>
                                                <img src="<?php echo esc_url($foto_url); ?>" 
                                                     alt="<?php echo esc_attr(get_the_title()); ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                    <i class="dashicons dashicons-admin-users" style="color: #ccc;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php the_title(); ?></strong>
                                        </td>
                                        <td><?php echo $data_morte ? date('d/m/Y', strtotime($data_morte)) : get_the_date('d/m/Y'); ?></td>
                                        <td><?php echo get_the_date('d/m/Y'); ?></td>
                                        <td>
                                            <?php if ($has_associations): ?>
                                                <?php foreach ($associated_monitors as $monitor): ?>
                                                    <span class="monitor-tag active"><?php echo esc_html($monitor['monitor_name']); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="monitor-tag inactive">Nessuno</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="defunto-actions">
                                                <!-- Association dropdowns for each applicable monitor -->
                                                <?php foreach ($vendor_monitors as $monitor): 
                                                    $is_associated = $monitor['associated_post_id'] == $post_id;
                                                    $can_associate = in_array($monitor['layout_type'], ['manifesti', 'solo_annuncio']);
                                                ?>
                                                    <?php if ($can_associate): ?>
                                                        <?php if ($is_associated): ?>
                                                            <form method="post" style="display: inline; margin: 2px;" onsubmit="return confirm('<?php _e('Rimuovere questo defunto dal monitor?', 'dokan-mod'); ?>');">
                                                                <?php wp_nonce_field('monitor_remove_association', 'monitor_nonce'); ?>
                                                                <input type="hidden" name="action" value="remove_association">
                                                                <input type="hidden" name="monitor_id" value="<?php echo $monitor['id']; ?>">
                                                                <button type="submit" class="custom-widget-button" style="background: #dc3545; color: white; font-size: 12px; padding: 4px 8px;">
                                                                    <i class="dashicons dashicons-no"></i> <?php echo esc_html($monitor['monitor_name']); ?>
                                                                </button>
                                                            </form>
                                                        <?php elseif (!$monitor['associated_post_id']): // Only show if monitor is free ?>
                                                            <form method="post" style="display: inline; margin: 2px;" onsubmit="return confirm('<?php _e('Associare questo defunto al monitor?', 'dokan-mod'); ?>');">
                                                                <?php wp_nonce_field('monitor_associate_defunto', 'monitor_nonce'); ?>
                                                                <input type="hidden" name="action" value="associate_defunto">
                                                                <input type="hidden" name="monitor_id" value="<?php echo $monitor['id']; ?>">
                                                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                                                <button type="submit" class="custom-widget-button" style="background: #007cba; color: white; font-size: 12px; padding: 4px 8px;">
                                                                    <i class="dashicons dashicons-yes"></i> <?php echo esc_html($monitor['monitor_name']); ?>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                endwhile;
                            else :
                                ?>
                                <tr>
                                    <td colspan="6">
                                        <?php if (get_query_var('s')): ?>
                                            <?php _e('Nessun annuncio trovato per la ricerca.', 'dokan-mod'); ?>
                                        <?php else: ?>
                                            <?php _e('Non hai ancora pubblicato annunci di morte.', 'dokan-mod'); ?>
                                            <br><br>
                                            <a href="<?php echo site_url('/dashboard/crea-annuncio'); ?>" class="custom-widget-button">
                                                <i class="dashicons dashicons-plus"></i> <?php _e('Crea il tuo primo annuncio', 'dokan-mod'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                            endif;
                            wp_reset_postdata();
                            ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo $query->found_posts; ?> elementi</span>
                            <span class="pagination-links">
                            <?php
                            $paginate_links = paginate_links(array(
                                'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                                'total' => $query->max_num_pages,
                                'current' => max(1, get_query_var('paged')),
                                'show_all' => false,
                                'type' => 'array',
                                'end_size' => 2,
                                'mid_size' => 1,
                                'prev_next' => true,
                                'prev_text' => '‹',
                                'next_text' => '›',
                                'add_args' => get_query_var('s') ? array('s' => get_query_var('s')) : false,
                                'add_fragment' => '',
                            ));

                            if ($paginate_links) {
                                $pagination = '';
                                foreach ($paginate_links as $link) {
                                    $pagination .= "<span class='paging-input'>$link</span>";
                                }
                                echo $pagination;
                            }
                            ?>
                        </span>
                        </div>
                    </div>

                </div>

            </div><!-- .dokan-dashboard-content -->

        </div><!-- .dokan-dashboard-wrap -->

        <div class="post-tags">
        </div>
    </div>

</main>

<script>
// Monitor URL Copy Function - Updated for table structure
function copyMonitorUrl(monitorId) {
    const monitorRow = document.querySelector(`[data-monitor-id="${monitorId}"]`);
    const url = monitorRow.querySelector('.monitor-url-code').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            // Show success feedback
            const button = monitorRow.querySelector('.copy-url-btn');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="dashicons dashicons-yes"></i>';
            button.style.color = '#46b450';
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.style.color = '#666';
            }, 1500);
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Show success feedback
        const button = monitorRow.querySelector('.copy-url-btn');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="dashicons dashicons-yes"></i>';
        button.style.color = '#46b450';
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.style.color = '#666';
        }, 1500);
    }
}

// Toggle Città Multi Configuration Popup
function toggleCittaMultiConfig(monitorId) {
    const popup = document.getElementById(`citta_multi_popup_${monitorId}`);
    const isVisible = popup.style.display !== 'none';
    
    // Hide all popups first
    document.querySelectorAll('.citta-multi-config-popup').forEach(p => {
        p.style.display = 'none';
    });
    
    // Toggle current popup
    if (!isVisible) {
        popup.style.display = 'block';
        
        // Close popup when clicking outside
        const closeHandler = function(event) {
            if (!popup.contains(event.target) && !event.target.closest('.layout-config-btn')) {
                popup.style.display = 'none';
                document.removeEventListener('click', closeHandler);
            }
        };
        
        setTimeout(() => {
            document.addEventListener('click', closeHandler);
        }, 100);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add enhanced interactions for table interface
    
    // Enhanced URL display with tooltip on hover
    document.querySelectorAll('.monitor-url-code').forEach(function(urlCode) {
        const fullUrl = urlCode.textContent;
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'url-tooltip';
        tooltip.textContent = fullUrl;
        tooltip.style.display = 'none';
        document.body.appendChild(tooltip);
        
        urlCode.addEventListener('mouseenter', function(e) {
            tooltip.style.display = 'block';
            tooltip.style.left = e.pageX + 10 + 'px';
            tooltip.style.top = e.pageY - 30 + 'px';
        });
        
        urlCode.addEventListener('mouseleave', function() {
            tooltip.style.display = 'none';
        });
        
        urlCode.addEventListener('mousemove', function(e) {
            tooltip.style.left = e.pageX + 10 + 'px';
            tooltip.style.top = e.pageY - 30 + 'px';
        });
    });
    
    // Enhanced layout selector feedback
    document.querySelectorAll('.layout-selector').forEach(function(selector) {
        selector.addEventListener('change', function() {
            // Show loading state
            this.style.opacity = '0.6';
            this.disabled = true;
            
            // Re-enable after form submission (fallback)
            setTimeout(() => {
                this.style.opacity = '1';
                this.disabled = false;
            }, 3000);
        });
    });
});
</script>

<style>
    /* Override theme CSS per omogeneizzare con layout standard Dokan */
    body.dokan-dashboard.theme-hello-elementor .site-main,
    body.dokan-dashboard .site-main {
        max-width: none !important;
        width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    body.dokan-dashboard.theme-hello-elementor .page-content,
    body.dokan-dashboard .page-content {
        max-width: none !important;
        width: 100% !important;
    }
    
    body.dokan-dashboard.theme-hello-elementor .dokan-dashboard-wrap,
    body.dokan-dashboard .dokan-dashboard-wrap {
        width: 100% !important;
        max-width: 1140px !important;
        margin: 0 auto !important;
    }


    .dokan-btn {
        display: inline-block;
        padding: 8px 16px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        border-radius: 3px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 1px solid transparent;
        text-align: center;
        line-height: 1.4;
    }

    .dokan-btn-theme {
        background-color: #007cba;
        color: #ffffff;
        border-color: #007cba;
    }

    .dokan-btn-theme:hover {
        background-color: #005a85;
        border-color: #005a85;
        color: #ffffff;
    }

    .custom-widget-button {
        display: inline-block;
        padding: 8px 16px;
        background-color: #007cba;
        color: #ffffff;
        text-decoration: none;
        border-radius: 3px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .custom-widget-button:hover {
        background-color: #005a85;
        color: #ffffff;
        text-decoration: none;
    }

    .dokan-alert {
        padding: 12px 16px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .dokan-alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .dokan-alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    /* Monitor Table */
    .monitors-table-container {
        margin-bottom: 30px;
        overflow-x: auto;
    }
    
    .monitors-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .monitors-table th,
    .monitors-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e1e1e1;
    }
    
    .monitors-table th {
        background: #f8f9fa;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }
    
    .monitor-row:hover {
        background-color: #f8f9fa;
    }
    
    /* Table Column Styles */
    .monitor-name-col {
        width: 20%;
    }
    
    .monitor-url-col {
        width: 25%;
    }
    
    .monitor-status-col {
        width: 12%;
    }
    
    .monitor-association-col {
        width: 20%;
    }
    
    .monitor-layout-col {
        width: 13%;
    }
    
    .monitor-actions-col {
        width: 10%;
    }
    
    /* Monitor Name Cell */
    .monitor-name-cell strong {
        display: block;
        font-size: 14px;
        color: #333;
        margin-bottom: 4px;
    }
    
    .monitor-description {
        font-size: 12px;
        color: #666;
        font-style: italic;
    }
    
    /* URL Display */
    .url-display {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .monitor-url-code {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        word-break: break-all;
        flex: 1;
        max-width: 280px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .copy-url-btn {
        background: none;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 4px 6px;
        cursor: pointer;
        color: #666;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    
    .copy-url-btn:hover {
        background: #f0f0f0;
        color: #333;
    }
    
    .copy-url-btn .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    
    /* URL Tooltip */
    .url-tooltip {
        position: absolute;
        background: #333;
        color: #fff;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        white-space: nowrap;
        z-index: 10000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        pointer-events: none;
        max-width: 400px;
        word-break: break-all;
    }
    
    .url-tooltip:before {
        content: '';
        position: absolute;
        bottom: 100%;
        left: 20px;
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-bottom: 5px solid #333;
    }
    
    /* Status Badge */
    .status-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
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
        font-size: 16px;
        width: 16px;
        height: 16px;
    }
    
    .status-text strong {
        display: block;
        margin-bottom: 2px;
    }
    
    .status-text small {
        font-size: 11px;
        opacity: 0.8;
    }
    
    /* Association Info */
    .association-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
    }
    
    .association-info .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }
    
    .association-content {
        flex: 1;
        min-width: 0;
    }
    
    .association-content strong {
        display: block;
        color: #333;
        margin-bottom: 2px;
        font-size: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .association-content small {
        font-size: 11px;
        color: #666;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Association type specific styles */
    .association-single .dashicons {
        color: #0073aa;
    }
    
    .association-multi .dashicons {
        color: #46b450;
    }
    
    .association-none .dashicons {
        color: #999;
    }
    
    .association-none span {
        color: #999;
        font-style: italic;
    }
    
    /* Layout Selector */
    .layout-form-inline {
        margin: 0;
        position: relative;
    }
    
    .layout-selector-wrapper {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .layout-selector {
        font-size: 12px;
        padding: 4px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fff;
    }
    
    .layout-config-btn {
        background: none;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 4px 6px;
        cursor: pointer;
        color: #666;
        transition: all 0.2s ease;
    }
    
    .layout-config-btn:hover {
        background: #f0f0f0;
        color: #333;
    }
    
    .layout-config-btn .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    
    /* Città Multi Configuration Popup */
    .citta-multi-config-popup {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 1000;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 15px;
        min-width: 250px;
        margin-top: 5px;
    }
    
    .config-popup-content h4 {
        margin: 0 0 12px 0;
        font-size: 13px;
        color: #333;
    }
    
    .config-row {
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .config-row label {
        font-size: 12px;
        color: #666;
        min-width: 60px;
    }
    
    .config-row select {
        font-size: 12px;
        padding: 3px 6px;
    }
    
    .config-actions {
        margin-top: 12px;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
    
    .config-actions .button-small {
        padding: 4px 8px;
        font-size: 11px;
        height: auto;
        line-height: 1.4;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 6px;
    }
    
    .monitor-live-btn {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 6px 10px;
        font-size: 12px;
        text-decoration: none;
        border-radius: 4px;
        height: auto;
        line-height: 1;
    }
    
    .monitor-live-btn .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    
    .button-text {
        display: inline;
    }
    
    /* Defunti Section */
    .defunti-section {
        margin-top: 40px;
    }
    
    .defunti-section h3 {
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #007cba;
    }
    
    .defunti-table {
        width: 100%;
    }
    
    .defunti-table tr.has-associations {
        background-color: #f0f8ff;
    }
    
    .monitor-tag {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        margin: 1px;
    }
    
    .monitor-tag.active {
        background: #d4edda;
        color: #155724;
    }
    
    .monitor-tag.inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .defunto-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .monitor-url-code {
            max-width: 200px;
        }
        
        .button-text {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .monitors-table-container {
            overflow-x: auto;
        }
        
        .monitors-table {
            min-width: 1000px;
        }
        
        .monitors-table th,
        .monitors-table td {
            padding: 8px;
            font-size: 12px;
        }
        
        .monitor-url-code {
            max-width: 150px;
        }
        
        .status-badge {
            padding: 4px 6px;
        }
        
        .status-badge .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        
        .layout-selector {
            font-size: 11px;
            padding: 3px 6px;
        }
        
        .monitor-live-btn {
            padding: 4px 6px;
            font-size: 11px;
        }
        
        .association-content strong,
        .association-content small {
            max-width: 120px;
        }
        
        .association-info {
            gap: 6px;
        }
        
        .citta-multi-config-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 300px;
            z-index: 10000;
        }
        
        .defunti-table {
            font-size: 14px;
        }
        
        .defunti-table th, 
        .defunti-table td {
            padding: 8px 4px;
        }
        
        .defunto-actions {
            flex-direction: column;
        }
    }
</style>

<?php
get_footer();
?>