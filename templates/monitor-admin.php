<?php
/**
 * Monitor Admin Template
 * Admin interface for managing vendor monitor permissions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check admin permissions
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.', 'dokan-mod'));
}

// Get all vendors
$vendors_query = new WP_User_Query(array(
    'role' => 'seller',
    'orderby' => 'display_name',
    'order' => 'ASC',
    'number' => -1
));

$vendors = $vendors_query->get_results();
$monitor_class = new Dokan_Mods\MonitorTotemClass();

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['vendor_ids']) && wp_verify_nonce($_POST['_wpnonce'], 'monitor_bulk_action')) {
    $bulk_action = sanitize_text_field($_POST['bulk_action']);
    $vendor_ids = array_map('intval', $_POST['vendor_ids']);
    
    if ($bulk_action === 'enable' || $bulk_action === 'disable') {
        $enabled = ($bulk_action === 'enable');
        $count = 0;
        
        foreach ($vendor_ids as $vendor_id) {
            $monitor_class->set_vendor_enabled($vendor_id, $enabled);
            $count++;
        }
        
        $message = sprintf(
            _n(
                '%d vendor %s per il monitor.',
                '%d vendor %s per il monitor.',
                $count,
                'dokan-mod'
            ),
            $count,
            $enabled ? 'abilitato' : 'disabilitato'
        );
        
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <i class="dashicons dashicons-desktop"></i>
        <?php _e('Monitor Digitale - Gestione Vendor', 'dokan-mod'); ?>
    </h1>
    
    <hr class="wp-header-end">

    <!-- Stats Overview -->
    <div class="monitor-stats-cards" style="display: flex; gap: 20px; margin: 20px 0;">
        <?php
        $total_vendors = count($vendors);
        $enabled_vendors = 0;
        $active_monitors = 0;
        
        foreach ($vendors as $vendor) {
            if ($monitor_class->is_vendor_enabled($vendor->ID)) {
                $enabled_vendors++;
                if ($monitor_class->get_associated_post($vendor->ID)) {
                    $active_monitors++;
                }
            }
        }
        ?>
        
        <div class="monitor-stat-card">
            <div class="stat-number"><?php echo $total_vendors; ?></div>
            <div class="stat-label">Vendor Totali</div>
        </div>
        
        <div class="monitor-stat-card enabled">
            <div class="stat-number"><?php echo $enabled_vendors; ?></div>
            <div class="stat-label">Vendor Abilitati</div>
        </div>
        
        <div class="monitor-stat-card active">
            <div class="stat-number"><?php echo $active_monitors; ?></div>
            <div class="stat-label">Monitor Attivi</div>
        </div>
        
        <div class="monitor-stat-card inactive">
            <div class="stat-number"><?php echo $enabled_vendors - $active_monitors; ?></div>
            <div class="stat-label">Monitor Inattivi</div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="monitor-admin-filters" style="margin: 20px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 15px; align-items: center;">
                <input type="text" id="vendor-search" placeholder="<?php _e('Cerca vendor...', 'dokan-mod'); ?>" 
                       style="width: 250px;" class="regular-text">
                
                <select id="status-filter">
                    <option value=""><?php _e('Tutti gli stati', 'dokan-mod'); ?></option>
                    <option value="enabled"><?php _e('Abilitati', 'dokan-mod'); ?></option>
                    <option value="disabled"><?php _e('Disabilitati', 'dokan-mod'); ?></option>
                    <option value="active"><?php _e('Attivi', 'dokan-mod'); ?></option>
                    <option value="inactive"><?php _e('Inattivi', 'dokan-mod'); ?></option>
                </select>
                
                <button type="button" id="filter-btn" class="button">
                    <?php _e('Filtra', 'dokan-mod'); ?>
                </button>
                
                <button type="button" id="reset-filters" class="button">
                    <?php _e('Reset', 'dokan-mod'); ?>
                </button>
            </div>
            
            <div>
                <button type="button" id="refresh-data" class="button">
                    <i class="dashicons dashicons-update"></i>
                    <?php _e('Aggiorna', 'dokan-mod'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Actions and Vendor Table -->
    <form method="post" id="monitor-vendor-form">
        <?php wp_nonce_field('monitor_bulk_action'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Azioni di gruppo', 'dokan-mod'); ?></option>
                    <option value="enable"><?php _e('Abilita per Monitor', 'dokan-mod'); ?></option>
                    <option value="disable"><?php _e('Disabilita per Monitor', 'dokan-mod'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php _e('Applica', 'dokan-mod'); ?>">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped" id="monitor-vendor-table">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-vendor-id sortable" data-sort="id">
                        <a href="#"><span><?php _e('ID', 'dokan-mod'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-shop-name sortable" data-sort="name">
                        <a href="#"><span><?php _e('Nome Negozio', 'dokan-mod'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-email sortable" data-sort="email">
                        <a href="#"><span><?php _e('Email', 'dokan-mod'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-city">
                        <?php _e('Città', 'dokan-mod'); ?>
                    </th>
                    <th scope="col" class="manage-column column-monitor-status">
                        <?php _e('Stato Monitor', 'dokan-mod'); ?>
                    </th>
                    <th scope="col" class="manage-column column-monitor-url">
                        <?php _e('URL Monitor', 'dokan-mod'); ?>
                    </th>
                    <th scope="col" class="manage-column column-last-access sortable" data-sort="last_access">
                        <a href="#"><span><?php _e('Ultimo Accesso', 'dokan-mod'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Azioni', 'dokan-mod'); ?>
                    </th>
                </tr>
            </thead>
            
            <tbody id="vendor-table-body">
                <?php foreach ($vendors as $vendor): 
                    $vendor_obj = dokan()->vendor->get($vendor->ID);
                    $shop_name = $vendor_obj->get_shop_name();
                    $shop_address = $vendor_obj->get_address();
                    $city = isset($shop_address['city']) ? $shop_address['city'] : '';
                    
                    $is_enabled = $monitor_class->is_vendor_enabled($vendor->ID);
                    $monitor_url = get_user_meta($vendor->ID, 'monitor_url', true);
                    $last_access = get_user_meta($vendor->ID, 'monitor_last_access', true);
                    $associated_post = $monitor_class->get_associated_post($vendor->ID);
                    
                    $status_class = '';
                    $status_text = '';
                    
                    if ($is_enabled) {
                        if ($associated_post) {
                            $status_class = 'status-active';
                            $status_text = __('Attivo', 'dokan-mod');
                        } else {
                            $status_class = 'status-inactive';
                            $status_text = __('Inattivo', 'dokan-mod');
                        }
                    } else {
                        $status_class = 'status-disabled';
                        $status_text = __('Disabilitato', 'dokan-mod');
                    }
                ?>
                <tr data-vendor-id="<?php echo $vendor->ID; ?>" 
                    data-enabled="<?php echo $is_enabled ? '1' : '0'; ?>"
                    data-active="<?php echo $associated_post ? '1' : '0'; ?>">
                    
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="vendor_ids[]" value="<?php echo $vendor->ID; ?>">
                    </th>
                    
                    <td class="vendor-id">
                        <strong><?php echo $vendor->ID; ?></strong>
                    </td>
                    
                    <td class="shop-name">
                        <strong><?php echo esc_html($shop_name); ?></strong>
                        <div class="row-actions">
                            <span class="view">
                                <a href="<?php echo $vendor_obj->get_shop_url(); ?>" target="_blank">
                                    <?php _e('Visualizza Negozio', 'dokan-mod'); ?>
                                </a>
                            </span>
                        </div>
                    </td>
                    
                    <td class="email">
                        <a href="mailto:<?php echo esc_attr($vendor->user_email); ?>">
                            <?php echo esc_html($vendor->user_email); ?>
                        </a>
                    </td>
                    
                    <td class="city">
                        <?php echo esc_html($city); ?>
                    </td>
                    
                    <td class="monitor-status">
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                        <?php if ($associated_post): ?>
                            <br><small>
                                <?php echo esc_html(get_the_title($associated_post)); ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="monitor-url">
                        <?php if ($is_enabled && $monitor_url): ?>
                            <code><?php echo esc_html($monitor_url); ?></code>
                            <br>
                            <a href="<?php echo $monitor_class->get_monitor_display_url($vendor->ID); ?>" 
                               target="_blank" class="button button-small">
                                <i class="dashicons dashicons-external"></i>
                                <?php _e('Apri Monitor', 'dokan-mod'); ?>
                            </a>
                        <?php else: ?>
                            <span class="description"><?php _e('Non configurato', 'dokan-mod'); ?></span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="last-access">
                        <?php if ($last_access): ?>
                            <?php echo date('d/m/Y H:i', strtotime($last_access)); ?>
                        <?php else: ?>
                            <span class="description"><?php _e('Mai', 'dokan-mod'); ?></span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="actions">
                        <div class="monitor-toggle-container">
                            <label class="monitor-toggle-switch">
                                <input type="checkbox" 
                                       class="monitor-toggle-checkbox" 
                                       data-vendor-id="<?php echo $vendor->ID; ?>"
                                       <?php checked($is_enabled); ?>>
                                <span class="monitor-toggle-slider"></span>
                            </label>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector-bottom">
                    <option value="-1"><?php _e('Azioni di gruppo', 'dokan-mod'); ?></option>
                    <option value="enable"><?php _e('Abilita per Monitor', 'dokan-mod'); ?></option>
                    <option value="disable"><?php _e('Disabilita per Monitor', 'dokan-mod'); ?></option>
                </select>
                <input type="submit" id="doaction2" class="button action" value="<?php _e('Applica', 'dokan-mod'); ?>">
            </div>
        </div>
    </form>

    <!-- Info Box -->
    <div class="monitor-info-box" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #007cba;">
        <h3><?php _e('Come funziona il Monitor Digitale', 'dokan-mod'); ?></h3>
        <ul>
            <li><?php _e('Abilita i vendor che possono utilizzare il monitor digitale', 'dokan-mod'); ?></li>
            <li><?php _e('Ogni vendor abilitato riceve un URL univoco per il proprio monitor', 'dokan-mod'); ?></li>
            <li><?php _e('Il vendor può associare un defunto al monitor dal proprio pannello', 'dokan-mod'); ?></li>
            <li><?php _e('Il monitor si aggiorna automaticamente ogni 15 secondi', 'dokan-mod'); ?></li>
            <li><?php _e('Solo un defunto per volta può essere associato al monitor', 'dokan-mod'); ?></li>
        </ul>
    </div>
</div>

<!-- Loading Overlay -->
<div id="monitor-loading-overlay" class="monitor-loading-overlay" style="display: none;">
    <div class="monitor-spinner"></div>
    <p><?php _e('Operazione in corso...', 'dokan-mod'); ?></p>
</div>

<style>
/* Stats Cards */
.monitor-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-align: center;
    min-width: 120px;
    border-top: 4px solid #ddd;
}

.monitor-stat-card.enabled {
    border-top-color: #46b450;
}

.monitor-stat-card.active {
    border-top-color: #00a0d2;
}

.monitor-stat-card.inactive {
    border-top-color: #ffb900;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Status Badges */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-block;
}

.status-badge.status-active {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-inactive {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-disabled {
    background: #f8d7da;
    color: #721c24;
}

/* Toggle Switch */
.monitor-toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.monitor-toggle-checkbox {
    opacity: 0;
    width: 0;
    height: 0;
}

.monitor-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 24px;
}

.monitor-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

.monitor-toggle-checkbox:checked + .monitor-toggle-slider {
    background-color: #46b450;
}

.monitor-toggle-checkbox:checked + .monitor-toggle-slider:before {
    transform: translateX(26px);
}

/* Table Enhancements */
.wp-list-table .column-vendor-id {
    width: 60px;
}

.wp-list-table .column-monitor-status {
    width: 150px;
}

.wp-list-table .column-actions {
    width: 80px;
}

.wp-list-table .column-last-access {
    width: 130px;
}

/* Loading Overlay */
.monitor-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    color: white;
}

.monitor-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 782px) {
    .monitor-stats-cards {
        flex-direction: column;
        gap: 10px !important;
    }
    
    .monitor-stat-card {
        min-width: auto;
    }
    
    .monitor-admin-filters > div {
        flex-direction: column;
        gap: 15px;
        align-items: stretch !important;
    }
    
    .monitor-admin-filters > div > div {
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>

<?php
// Enqueue admin scripts and styles
wp_enqueue_script('jquery');
wp_enqueue_style('dashicons');
?>