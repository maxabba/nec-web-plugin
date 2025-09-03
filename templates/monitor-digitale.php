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
            dokan_get_template_part('global/dashboard-nav', '', ['active_menu' => $active_menu]);
            ?>

            <div class="dokan-dashboard-content dokan-product-edit">
                <?php
                /**
                 *  Adding dokan_dashboard_content_before hook
                 *
                 * @hooked get_dashboard_side_navigation
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

                    <!-- Monitors List -->
                    <div class="monitors-container">
                        <?php foreach ($vendor_monitors as $monitor): 
                            $layout_config = dkmod_safe_decode_layout_config($monitor['layout_config']);
                            $associated_post_title = $monitor['associated_post_id'] ? get_the_title($monitor['associated_post_id']) : null;
                        ?>
                            <div class="monitor-card" data-monitor-id="<?php echo $monitor['id']; ?>">
                                <div class="monitor-card-header">
                                    <div class="monitor-info">
                                        <h4><?php echo esc_html($monitor['monitor_name']); ?></h4>
                                        <div class="monitor-url">
                                            <code><?php echo home_url('/monitor/display/' . $user_id . '/' . $monitor['id'] . '/' . $monitor['monitor_slug']); ?></code>
                                            <button type="button" class="custom-widget-button" onclick="copyMonitorUrl('<?php echo $monitor['id']; ?>')" title="Copia URL" style="margin-left: 10px; padding: 2px 8px;">
                                                <i class="dashicons dashicons-admin-page"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="monitor-status">
                                        <?php if ($monitor['associated_post_id']): ?>
                                            <div class="status-active">
                                                <strong><i class="dashicons dashicons-yes"></i> ATTIVO</strong><br>
                                                <small><?php echo esc_html($associated_post_title); ?></small>
                                            </div>
                                        <?php else: ?>
                                            <div class="status-inactive">
                                                <strong><i class="dashicons dashicons-warning"></i> INATTIVO</strong><br>
                                                <small>Nessun defunto associato</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="monitor-card-body">
                                    <!-- Layout Configuration -->
                                    <div class="layout-config">
                                        <form method="post" class="layout-form">
                                            <?php wp_nonce_field('monitor_update_layout', 'monitor_nonce'); ?>
                                            <input type="hidden" name="action" value="update_layout">
                                            <input type="hidden" name="monitor_id" value="<?php echo $monitor['id']; ?>">
                                            
                                            <div class="form-row">
                                                <label for="layout_type_<?php echo $monitor['id']; ?>"><?php _e('Layout:', 'dokan-mod'); ?></label>
                                                <select name="layout_type" id="layout_type_<?php echo $monitor['id']; ?>" onchange="toggleLayoutConfig(<?php echo $monitor['id']; ?>)">
                                                    <?php foreach ($available_layouts as $layout_key => $layout_name): ?>
                                                        <option value="<?php echo $layout_key; ?>" <?php selected($monitor['layout_type'], $layout_key); ?>>
                                                            <?php echo esc_html($layout_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="custom-widget-button" style="margin-left: 10px;">
                                                    <?php _e('Aggiorna', 'dokan-mod'); ?>
                                                </button>
                                            </div>
                                            
                                            <!-- Città Multi Configuration -->
                                            <div id="citta_multi_config_<?php echo $monitor['id']; ?>" class="layout-specific-config" style="<?php echo $monitor['layout_type'] !== 'citta_multi' ? 'display: none;' : ''; ?>">
                                                <div class="form-row">
                                                    <label for="days_range_<?php echo $monitor['id']; ?>"><?php _e('Giorni da visualizzare:', 'dokan-mod'); ?></label>
                                                    <select name="days_range" id="days_range_<?php echo $monitor['id']; ?>">
                                                        <option value="1" <?php selected($layout_config['days_range'] ?? 7, 1); ?>><?php _e('Solo oggi', 'dokan-mod'); ?></option>
                                                        <option value="3" <?php selected($layout_config['days_range'] ?? 7, 3); ?>><?php _e('Ultimi 3 giorni', 'dokan-mod'); ?></option>
                                                        <option value="7" <?php selected($layout_config['days_range'] ?? 7, 7); ?>><?php _e('Ultimi 7 giorni', 'dokan-mod'); ?></option>
                                                        <option value="14" <?php selected($layout_config['days_range'] ?? 7, 14); ?>><?php _e('Ultimi 14 giorni', 'dokan-mod'); ?></option>
                                                        <option value="30" <?php selected($layout_config['days_range'] ?? 7, 30); ?>><?php _e('Ultimo mese', 'dokan-mod'); ?></option>
                                                    </select>
                                                </div>
                                                <div class="form-row">
                                                    <label>
                                                        <input type="checkbox" name="show_all_agencies" value="1" 
                                                               <?php checked($layout_config['show_all_agencies'] ?? false); ?>>
                                                        <?php _e('Mostra annunci di tutte le agenzie (non solo la tua)', 'dokan-mod'); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Monitor Actions -->
                                    <div class="monitor-actions">
                                        <button type="button" class="btn-preview-layout custom-widget-button" 
                                                data-monitor-id="<?php echo $monitor['id']; ?>"
                                                data-monitor-name="<?php echo esc_attr($monitor['monitor_name']); ?>"
                                                data-layout-type="<?php echo esc_attr($monitor['layout_type']); ?>"
                                                style="background: #667eea; margin-right: 8px;">
                                            <i class="dashicons dashicons-desktop"></i> <?php _e('Anteprima Layout', 'dokan-mod'); ?>
                                        </button>
                                        <a href="<?php echo home_url('/monitor/display/' . $user_id . '/' . $monitor['id'] . '/' . $monitor['monitor_slug']); ?>" 
                                           target="_blank" class="custom-widget-button" style="background: #28a745;">
                                            <i class="dashicons dashicons-visibility"></i> <?php _e('Monitor Live', 'dokan-mod'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
// Monitor URL Copy Function
function copyMonitorUrl(monitorId) {
    const monitorCard = document.querySelector(`[data-monitor-id="${monitorId}"]`);
    const url = monitorCard.querySelector('.monitor-url code').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('URL copiato negli appunti!');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('URL copiato negli appunti!');
    }
}

// Toggle layout-specific configuration
function toggleLayoutConfig(monitorId) {
    const layoutSelect = document.getElementById(`layout_type_${monitorId}`);
    const cittaMultiConfig = document.getElementById(`citta_multi_config_${monitorId}`);
    
    if (layoutSelect.value === 'citta_multi') {
        cittaMultiConfig.style.display = 'block';
    } else {
        cittaMultiConfig.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize layout configs
    <?php foreach ($vendor_monitors as $monitor): ?>
    toggleLayoutConfig(<?php echo $monitor['id']; ?>);
    <?php endforeach; ?>
});
</script>

<style>
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

    /* Monitor Cards */
    .monitors-container {
        display: grid;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .monitor-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .monitor-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    
    .monitor-info h4 {
        margin: 0 0 10px 0;
        color: #333;
        font-size: 18px;
    }
    
    .monitor-url {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .monitor-url code {
        background: #f8f9fa;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        word-break: break-all;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
        padding: 10px 15px;
        border-radius: 5px;
        text-align: center;
        min-width: 120px;
    }
    
    .status-inactive {
        background: #fff3cd;
        color: #856404;
        padding: 10px 15px;
        border-radius: 5px;
        text-align: center;
        min-width: 120px;
    }
    
    .layout-config {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    
    .form-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    
    .form-row label {
        min-width: 120px;
        font-weight: 600;
    }
    
    .form-row select {
        min-width: 200px;
    }
    
    .layout-specific-config {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
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
    
    @media (max-width: 768px) {
        .monitor-card-header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        
        .monitor-url {
            justify-content: center;
        }
        
        .form-row {
            flex-direction: column;
            align-items: stretch;
        }
        
        .form-row label {
            min-width: auto;
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