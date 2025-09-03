<?php
/**
 * Monitor Admin Template - Enhanced for Multiple Monitors
 * Admin interface for managing vendor monitor permissions and configurations
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check admin permissions
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.', 'dokan-mod'));
}

$monitor_class = new Dokan_Mods\MonitorTotemClass();
$db_manager = Dokan_Mods\MonitorDatabaseManager::get_instance();

// Handle migration success/error messages
if (isset($_GET['migration_success'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['migration_success'])) . '</p></div>';
}
if (isset($_GET['migration_error'])) {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['migration_error'])) . '</p></div>';
}

// Check if table exists
$table_exists = $db_manager->table_exists();

// Get statistics
$stats = $table_exists ? $db_manager->get_monitor_stats() : null;

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <i class="dashicons dashicons-desktop"></i>
        <?php _e('Monitor Digitale - Gestione Sistema', 'dokan-mod'); ?>
    </h1>
    
    <hr class="wp-header-end">

    <?php if (!$table_exists): ?>
        <!-- Migration Required Section -->
        <div class="notice notice-warning">
            <h2>🚀 Sistema Database Non Inizializzato</h2>
            <p>Il nuovo sistema di gestione monitor multipli richiede l'inizializzazione della tabella database dedicata.</p>
            
            <div style="margin: 20px 0;">
                <p><strong>Procedura di migrazione:</strong></p>
                <ol>
                    <li><strong>Crea la tabella</strong> - Clicca sul bottone qui sotto per creare la nuova tabella database</li>
                    <li><strong>Migra i dati</strong> - Dopo la creazione, migra i dati esistenti dal sistema precedente</li>
                    <li><strong>Gestisci monitor</strong> - Accedi alle nuove funzionalità di gestione monitor multipli</li>
                </ol>
            </div>

            <div class="migration-buttons" style="margin: 20px 0;">
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dokan-monitor-digitale&dkmod_migrate_monitors=create_table'), 'dkmod_migrate_monitors'); ?>" 
                   class="button button-primary button-hero">
                    📊 Crea Tabella Database
                </a>
                <p class="description">
                    <strong>Sicuro:</strong> Questa operazione crea solo la nuova tabella senza modificare dati esistenti.
                </p>
            </div>
        </div>

    <?php else: ?>
        <!-- Main Dashboard -->
        
        <!-- Stats Overview -->
        <div class="monitor-stats-section" style="margin: 20px 0;">
            <h2>📊 Statistiche Sistema</h2>
            <div class="monitor-stats-cards" style="display: flex; gap: 20px; flex-wrap: wrap;">
                
                <div class="monitor-stat-card" style="background: #f8f9fa; padding: 20px; border-radius: 8px; min-width: 200px; border-left: 4px solid #007cba;">
                    <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #007cba;">
                        <?php echo intval($stats['total_monitors']); ?>
                    </div>
                    <div class="stat-label" style="color: #666;">Monitor Totali</div>
                </div>
                
                <div class="monitor-stat-card" style="background: #f8f9fa; padding: 20px; border-radius: 8px; min-width: 200px; border-left: 4px solid #46b450;">
                    <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #46b450;">
                        <?php echo intval($stats['enabled_monitors']); ?>
                    </div>
                    <div class="stat-label" style="color: #666;">Monitor Abilitati</div>
                </div>
                
                <div class="monitor-stat-card" style="background: #f8f9fa; padding: 20px; border-radius: 8px; min-width: 200px; border-left: 4px solid #00a32a;">
                    <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #00a32a;">
                        <?php echo intval($stats['active_monitors']); ?>
                    </div>
                    <div class="stat-label" style="color: #666;">Monitor Attivi</div>
                </div>
                
                <div class="monitor-stat-card" style="background: #f8f9fa; padding: 20px; border-radius: 8px; min-width: 200px; border-left: 4px solid #ff8c00;">
                    <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #ff8c00;">
                        <?php echo intval($stats['vendors_with_monitors']); ?>
                    </div>
                    <div class="stat-label" style="color: #666;">Vendor con Monitor</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="monitor-actions-section" style="margin: 30px 0;">
            <h2>⚡ Azioni Rapide</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <button class="button button-primary" onclick="showCreateMonitorModal()">
                    <i class="dashicons dashicons-plus"></i> Crea Nuovo Monitor
                </button>
                <button class="button button-secondary" onclick="refreshMonitorList()">
                    <i class="dashicons dashicons-update"></i> Aggiorna Lista
                </button>
                <button class="button button-secondary" onclick="showBulkOperationsModal()">
                    <i class="dashicons dashicons-admin-settings"></i> Operazioni Bulk
                </button>
            </div>
        </div>

        <!-- Monitor Management Table -->
        <div class="monitor-table-section" style="margin: 30px 0;">
            <h2>🖥️ Gestione Monitor</h2>
            
            <!-- Filters -->
            <div class="monitor-filters" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <div>
                        <label for="vendor-filter"><strong>Vendor:</strong></label>
                        <select id="vendor-filter" style="margin-left: 10px;">
                            <option value="">Tutti i Vendor</option>
                            <!-- Populated via AJAX -->
                        </select>
                    </div>
                    <div>
                        <label for="layout-filter"><strong>Layout:</strong></label>
                        <select id="layout-filter" style="margin-left: 10px;">
                            <option value="">Tutti i Layout</option>
                            <option value="manifesti">Manifesti</option>
                            <option value="solo_annuncio">Solo Annuncio</option>
                            <option value="citta_multi">Città Multi-Agenzia</option>
                        </select>
                    </div>
                    <div>
                        <label for="status-filter"><strong>Stato:</strong></label>
                        <select id="status-filter" style="margin-left: 10px;">
                            <option value="">Tutti</option>
                            <option value="enabled">Abilitati</option>
                            <option value="disabled">Disabilitati</option>
                            <option value="active">Attivi</option>
                        </select>
                    </div>
                    <button type="button" class="button" onclick="applyFilters()">Filtra</button>
                    <button type="button" class="button" onclick="resetFilters()">Reset</button>
                </div>
            </div>

            <!-- Loading -->
            <div id="monitor-loading" style="display: none; text-align: center; padding: 40px;">
                <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                <p>Caricamento monitor...</p>
            </div>

            <!-- Monitor Table -->
            <div id="monitor-table-container">
                <table class="wp-list-table widefat fixed striped" id="monitor-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="monitor-select-all">
                            </td>
                            <th class="manage-column">Monitor</th>
                            <th class="manage-column">Vendor</th>
                            <th class="manage-column">Layout</th>
                            <th class="manage-column">Stato</th>
                            <th class="manage-column">Associazione</th>
                            <th class="manage-column">URL Display</th>
                            <th class="manage-column">Ultimo Accesso</th>
                            <th class="manage-column">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="monitor-table-body">
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="monitor-pagination" style="margin: 20px 0; text-align: center;">
                <!-- Populated via AJAX -->
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Modal: Create Monitor -->
<div id="create-monitor-modal" class="monitor-modal" style="display: none;">
    <div class="monitor-modal-content">
        <div class="monitor-modal-header">
            <h2>🆕 Crea Nuovo Monitor</h2>
            <span class="monitor-modal-close" onclick="closeCreateMonitorModal()">&times;</span>
        </div>
        <div class="monitor-modal-body">
            <form id="create-monitor-form">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="vendor-select">Vendor *</label></th>
                            <td>
                                <select id="vendor-select" name="vendor_id" required style="width: 100%;">
                                    <option value="">Seleziona Vendor...</option>
                                    <!-- Populated via AJAX -->
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="monitor-name">Nome Monitor *</label></th>
                            <td>
                                <input type="text" id="monitor-name" name="monitor_name" required 
                                       style="width: 100%;" placeholder="Es: Monitor Sala Principale">
                                <p class="description">Nome descrittivo per identificare il monitor</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="monitor-description">Descrizione</label></th>
                            <td>
                                <textarea id="monitor-description" name="monitor_description" 
                                         style="width: 100%;" rows="3" 
                                         placeholder="Descrizione opzionale del monitor..."></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="layout-type">Tipo Layout *</label></th>
                            <td>
                                <select id="layout-type" name="layout_type" required style="width: 100%;">
                                    <option value="manifesti">Manifesti (Default)</option>
                                    <option value="solo_annuncio">Solo Annuncio di Morte</option>
                                    <option value="citta_multi">Città Multi-Agenzia</option>
                                </select>
                                <p class="description">Seleziona il tipo di contenuto da visualizzare</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </div>
        <div class="monitor-modal-footer">
            <button type="button" class="button button-secondary" onclick="closeCreateMonitorModal()">Annulla</button>
            <button type="button" class="button button-primary" onclick="createMonitor()">Crea Monitor</button>
        </div>
    </div>
</div>

<!-- Modal: Monitor Details -->
<div id="monitor-details-modal" class="monitor-modal" style="display: none;">
    <div class="monitor-modal-content">
        <div class="monitor-modal-header">
            <h2>📊 Dettagli Monitor</h2>
            <span class="monitor-modal-close" onclick="closeMonitorDetailsModal()">&times;</span>
        </div>
        <div class="monitor-modal-body" id="monitor-details-content">
            <!-- Populated dynamically -->
        </div>
        <div class="monitor-modal-footer">
            <button type="button" class="button button-secondary" onclick="closeMonitorDetailsModal()">Chiudi</button>
        </div>
    </div>
</div>

<!-- Messages -->
<div id="monitor-messages" style="margin: 20px 0;"></div>

<!-- CSS Styles -->
<style>
.monitor-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.monitor-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    width: 70%;
    max-width: 800px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
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
}

.monitor-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.monitor-modal-close:hover,
.monitor-modal-close:focus {
    color: #000;
}

.monitor-modal-body {
    padding: 25px;
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

.monitor-status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.monitor-status-enabled {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.monitor-status-disabled {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.monitor-status-active {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.monitor-url-display {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 11px;
    background-color: #f1f1f1;
    padding: 4px 8px;
    border-radius: 4px;
    max-width: 250px;
    word-break: break-all;
}

.monitor-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.monitor-actions .button {
    padding: 4px 8px;
    font-size: 12px;
    height: auto;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .monitor-modal-content {
        width: 95%;
        margin: 2% auto;
    }
    
    .monitor-stats-cards {
        flex-direction: column;
    }
    
    .monitor-filters > div {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
// Modal functions for monitor management
function showCreateMonitorModal() {
    alert('La funzionalità di creazione monitor è in sviluppo.\n\nPer ora, i monitor vengono creati automaticamente quando un vendor viene abilitato.');
    // TODO: Implement modal for creating monitors
}

function editMonitor(monitorId) {
    alert('Modifica monitor #' + monitorId + ' - Funzionalità in sviluppo.');
    // TODO: Implement edit functionality
}

function deleteMonitor(monitorId) {
    if (confirm('Sei sicuro di voler eliminare questo monitor?')) {
        // TODO: Implement delete functionality via AJAX
        alert('Eliminazione monitor #' + monitorId + ' - Funzionalità in sviluppo.');
    }
}

function viewMonitor(monitorId) {
    // This function can open the monitor in a new tab
    const monitorRow = document.querySelector(`tr[data-monitor-id="${monitorId}"]`);
    if (monitorRow) {
        const monitorUrl = monitorRow.querySelector('.monitor-url-display').textContent;
        if (monitorUrl) {
            window.open(monitorUrl, '_blank');
        }
    }
}

// Copy monitor URL to clipboard
function copyMonitorUrl(url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('URL copiato negli appunti!');
        }).catch(function(err) {
            // Fallback
            copyToClipboardFallback(url);
        });
    } else {
        copyToClipboardFallback(url);
    }
}

function copyToClipboardFallback(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        alert('URL copiato negli appunti!');
    } catch (err) {
        alert('Impossibile copiare l\'URL. Seleziona e copia manualmente: ' + text);
    }
    document.body.removeChild(textArea);
}

// Initialize monitor admin functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for monitor action buttons
    document.querySelectorAll('.copy-url-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            if (url) copyMonitorUrl(url);
        });
    });
    
    // Initialize tooltips if needed
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        el.style.cursor = 'help';
    });
});
</script>