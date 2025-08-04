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

if (!$monitor_class->is_vendor_enabled($user_id)) {
    ?>
    <div class="dokan-alert dokan-alert-warning">
        <strong>Accesso Negato:</strong> Il tuo account non è abilitato per l'utilizzo del Monitor Digitale. 
        Contatta l'amministratore per richiedere l'abilitazione.
    </div>
    <?php
    return;
}

// Get vendor info
$vendor = dokan()->vendor->get($user_id);
$shop_name = $vendor->get_shop_name();
$monitor_url = get_user_meta($user_id, 'monitor_url', true);
$associated_post = $monitor_class->get_associated_post($user_id);

get_header();
?>

<div class="dokan-dashboard-wrap">
    <?php
    dokan_get_template_part('global/dashboard-nav');
    ?>

    <div class="dokan-dashboard-content">
        <article class="dokan-dashboard-area">
            
            <header class="dokan-dashboard-header">
                <h1 class="entry-title">
                    <i class="dashicons dashicons-desktop"></i>
                    <?php _e('Monitor Digitale', 'dokan-mod'); ?>
                </h1>
            </header>

            <!-- Monitor Info Section -->
            <div class="dokan-monitor-info" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?php _e('Informazioni Monitor', 'dokan-mod'); ?></h3>
                        <p><strong><?php _e('Negozio:', 'dokan-mod'); ?></strong> <?php echo esc_html($shop_name); ?></p>
                        <p><strong><?php _e('URL Monitor:', 'dokan-mod'); ?></strong> 
                            <code><?php echo home_url('/monitor/display/' . $user_id . '/' . $monitor_url); ?></code>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyMonitorUrl()" title="Copia URL">
                                <i class="dashicons dashicons-admin-page"></i>
                            </button>
                        </p>
                    </div>
                    <div class="col-md-4 text-right">
                        <?php if ($associated_post): ?>
                            <div class="alert alert-success">
                                <strong><i class="dashicons dashicons-yes"></i> MONITOR ATTIVO</strong><br>
                                <small>Defunto associato: <?php echo get_the_title($associated_post); ?></small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong><i class="dashicons dashicons-warning"></i> MONITOR INATTIVO</strong><br>
                                <small>Nessun defunto associato</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="dokan-monitor-controls" style="margin-bottom: 20px;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" id="defunto-search" class="form-control" 
                                   placeholder="<?php _e('Cerca per nome defunto...', 'dokan-mod'); ?>">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="search-btn">
                                    <i class="dashicons dashicons-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 text-right">
                        <button type="button" class="btn btn-primary" id="refresh-list">
                            <i class="dashicons dashicons-update"></i> <?php _e('Aggiorna Lista', 'dokan-mod'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Defunti List -->
            <div class="dokan-monitor-defunti">
                <div class="card">
                    <div class="card-header">
                        <h4><?php _e('I Tuoi Annunci di Morte', 'dokan-mod'); ?></h4>
                        <small class="text-muted"><?php _e('Seleziona il defunto da mostrare sul monitor digitale', 'dokan-mod'); ?></small>
                    </div>
                    <div class="card-body">
                        <!-- Loading State -->
                        <div id="defunti-loading" class="text-center" style="display: none;">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Caricamento...</span>
                            </div>
                            <p><?php _e('Caricamento defunti...', 'dokan-mod'); ?></p>
                        </div>

                        <!-- Defunti Table -->
                        <div id="defunti-table-container">
                            <table class="table table-striped" id="defunti-table">
                                <thead>
                                    <tr>
                                        <th width="80"><?php _e('Foto', 'dokan-mod'); ?></th>
                                        <th><?php _e('Nome Defunto', 'dokan-mod'); ?></th>
                                        <th width="120"><?php _e('Data Morte', 'dokan-mod'); ?></th>
                                        <th width="120"><?php _e('Pubblicazione', 'dokan-mod'); ?></th>
                                        <th width="100" class="text-center"><?php _e('Stato', 'dokan-mod'); ?></th>
                                        <th width="150" class="text-center"><?php _e('Azioni', 'dokan-mod'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="defunti-tbody">
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div id="defunti-empty" class="text-center" style="display: none; padding: 40px;">
                            <i class="dashicons dashicons-format-status" style="font-size: 48px; color: #ccc;"></i>
                            <h4><?php _e('Nessun annuncio trovato', 'dokan-mod'); ?></h4>
                            <p class="text-muted"><?php _e('Non hai ancora pubblicato annunci di morte o la ricerca non ha prodotto risultati.', 'dokan-mod'); ?></p>
                        </div>

                        <!-- Pagination -->
                        <nav id="defunti-pagination" style="display: none;">
                            <ul class="pagination justify-content-center">
                                <!-- Pagination will be generated via JS -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <div id="monitor-messages" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

        </article>
    </div><!-- .dokan-dashboard-content -->
</div><!-- .dokan-dashboard-wrap -->

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle"><?php _e('Conferma Azione', 'dokan-mod'); ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="confirmModalBody">
                <!-- Content will be set via JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e('Annulla', 'dokan-mod'); ?></button>
                <button type="button" class="btn btn-primary" id="confirmModalAction"><?php _e('Conferma', 'dokan-mod'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
// Monitor URL Copy Function
function copyMonitorUrl() {
    const url = '<?php echo home_url('/monitor/display/' . $user_id . '/' . $monitor_url); ?>';
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            showMessage('URL copiato negli appunti!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showMessage('URL copiato negli appunti!', 'success');
    }
}

// Show message function
function showMessage(message, type = 'info') {
    const messagesContainer = document.getElementById('monitor-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type} alert-dismissible fade show`;
    messageDiv.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    messagesContainer.appendChild(messageDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize monitor vendor functionality if JS file is loaded
    if (typeof MonitorVendor !== 'undefined') {
        MonitorVendor.init();
    }
});
</script>

<style>
.dokan-monitor-info {
    border-left: 4px solid #007cba;
}

.dokan-monitor-controls .input-group {
    max-width: 400px;
}

#defunti-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.defunto-foto {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
}

.btn-associate {
    font-size: 12px;
    padding: 4px 8px;
}

.loading-overlay {
    position: relative;
}

.loading-overlay::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .dokan-monitor-info .row {
        text-align: center;
    }
    
    .dokan-monitor-controls .col-md-6 {
        margin-bottom: 15px;
    }
    
    #defunti-table {
        font-size: 14px;
    }
    
    #defunti-table th, 
    #defunti-table td {
        padding: 8px 4px;
    }
}
</style>

<?php
get_footer();
?>