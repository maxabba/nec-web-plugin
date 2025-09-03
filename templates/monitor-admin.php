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
                            <th scope="row"><label for="vendor-search">Vendor *</label></th>
                            <td>
                                <div class="vendor-selection-wrapper">
                                    <!-- Selected Vendor Pill -->
                                    <div class="vendor-selected-pill" id="vendor-selected-pill" style="display: none;">
                                        <div class="vendor-pill-content">
                                            <div class="vendor-pill-avatar">
                                                <span class="vendor-pill-initial"></span>
                                            </div>
                                            <div class="vendor-pill-info">
                                                <div class="vendor-pill-name"></div>
                                                <div class="vendor-pill-details"></div>
                                            </div>
                                            <button type="button" class="vendor-pill-remove" onclick="clearVendorSelection()" title="Rimuovi selezione">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Search Container -->
                                    <div class="vendor-search-container" id="vendor-search-container" style="position: relative;">
                                        <input type="text" 
                                               id="vendor-search" 
                                               class="vendor-search-input" 
                                               placeholder="Cerca vendor per nome, username o email..." 
                                               style="width: 100%; padding-right: 30px;"
                                               autocomplete="off"
                                               required>
                                        <input type="hidden" id="vendor-id" name="vendor_id" required>
                                        <div class="vendor-search-spinner" id="vendor-search-spinner" style="display: none;">
                                            <span class="spinner" style="float: none; margin: 0;"></span>
                                        </div>
                                        <div class="vendor-search-results" id="vendor-search-results" style="display: none;">
                                            <!-- Results populated via AJAX -->
                                        </div>
                                    </div>
                                </div>
                                <p class="description" id="vendor-search-description">Inizia a digitare per cercare (minimo 2 caratteri)</p>
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
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease-out;
    backdrop-filter: blur(2px);
}

.monitor-modal.show {
    opacity: 1;
}

.monitor-modal-content {
    background-color: #fefefe;
    width: 70%;
    max-width: 800px;
    max-height: 90vh;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    overflow-y: auto;
    position: relative;
    margin: 0;
    /* Adjustments for WordPress admin area */
    transform: translateX(0) scale(0.9);
    transition: transform 0.3s ease-out;
}

.monitor-modal.show .monitor-modal-content {
    transform: translateX(0) scale(1);
}

/* Centra il modale rispetto al content area di WordPress, non al viewport completo */
@media screen and (min-width: 783px) {
    .monitor-modal-content {
        /* Considera il menu laterale WordPress (160px) */
        transform: translateX(calc(160px / 2)) scale(0.9);
        max-width: calc(100vw - 200px);
    }
    
    .monitor-modal.show .monitor-modal-content {
        transform: translateX(calc(160px / 2)) scale(1);
    }
}

/* Menu WordPress collassato (solo icone) - si attiva quando la finestra è stretta */
@media screen and (max-width: 960px) and (min-width: 783px) {
    .monitor-modal-content {
        /* Menu collassato WordPress (36px) */
        transform: translateX(calc(36px / 2)) scale(0.9);
        max-width: calc(100vw - 76px);
    }
    
    .monitor-modal.show .monitor-modal-content {
        transform: translateX(calc(36px / 2)) scale(1);
    }
}

/* Supporto per menu WordPress sempre collassato tramite user preference */
.folded .monitor-modal-content {
    /* Menu sempre collassato (36px) */
    transform: translateX(calc(36px / 2)) scale(0.9) !important;
    max-width: calc(100vw - 76px) !important;
}

.folded .monitor-modal.show .monitor-modal-content {
    transform: translateX(calc(36px / 2)) scale(1) !important;
}

/* Mobile - nessun menu laterale */
@media screen and (max-width: 782px) {
    .monitor-modal-content {
        transform: translateX(0);
        width: 95%;
        max-width: calc(100vw - 20px);
        max-height: 95vh;
    }
}

/* Schermi molto piccoli - padding ridotto */
@media screen and (max-width: 480px) {
    .monitor-modal-content {
        width: 98%;
        max-width: calc(100vw - 10px);
        max-height: 98vh;
    }
    
    .monitor-modal-header,
    .monitor-modal-body,
    .monitor-modal-footer {
        padding: 15px;
    }
    
    .form-table th,
    .form-table td {
        display: block;
        width: 100%;
        padding: 5px 0;
    }
    
    .form-table th {
        font-weight: 600;
        margin-bottom: 5px;
    }
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

/* Vendor Search Field Styles */
.vendor-search-container {
    position: relative;
}

.vendor-search-input {
    position: relative;
    z-index: 1;
}

.vendor-search-spinner {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    z-index: 2;
}

.vendor-search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
}

.vendor-search-result {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.vendor-search-result:last-child {
    border-bottom: none;
}

.vendor-search-result:hover,
.vendor-search-result.selected {
    background-color: #f0f6fc;
}

.vendor-search-result.selected {
    background-color: #e7f3ff;
}

.vendor-result-name {
    font-weight: 600;
    color: #333;
}

.vendor-result-details {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

.vendor-result-shop {
    font-style: italic;
    color: #0073aa;
}

.vendor-search-no-results {
    padding: 15px;
    text-align: center;
    color: #666;
    font-style: italic;
}

.vendor-search-message {
    padding: 10px 15px;
    background-color: #fff3cd;
    border-bottom: 1px solid #ffeaa7;
    font-size: 12px;
    color: #856404;
}

/* Input validation states */
.vendor-search-input.valid {
    border-color: #46b450;
    box-shadow: 0 0 0 1px #46b450;
}

.vendor-search-input.invalid {
    border-color: #dc3232;
    box-shadow: 0 0 0 1px #dc3232;
}

/* Fix dashicon alignment in buttons */
.button .dashicons {
    vertical-align: middle;
    line-height: 1;
    margin-right: 5px;
}

.button .dashicons:before {
    vertical-align: top;
}

/* Vendor Selection Pill Styles */
.vendor-selection-wrapper {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.vendor-selected-pill {
    background: #e7f3ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 8px 12px;
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    animation: slideInFromTop 0.3s ease-out;
}

.vendor-pill-content {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}

.vendor-pill-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #0073aa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
    font-size: 14px;
    flex-shrink: 0;
}

.vendor-pill-info {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.vendor-pill-name {
    font-weight: 600;
    color: #0073aa;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vendor-pill-details {
    font-size: 12px;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

.vendor-pill-remove {
    background: none;
    border: none;
    cursor: pointer;
    color: #666;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
}

.vendor-pill-remove:hover {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.vendor-pill-remove .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin: 0;
}

@keyframes slideInFromTop {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Hide search container when vendor is selected */
.vendor-selection-wrapper.has-selection .vendor-search-container {
    display: none;
}

/* Show different description when vendor is selected */
.vendor-selection-wrapper.has-selection + .description {
    color: #46b450;
}

.vendor-selection-wrapper.has-selection + .description:before {
    content: "✓ ";
    color: #46b450;
}
</style>

<script>
// Global variables for monitor management
let monitorManagement = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('monitor_admin_nonce'); ?>',
    currentPage: 1,
    totalPages: 1,
    filters: {
        vendor: '',
        layout: '',
        status: ''
    }
};

// Modal functions for monitor management
function showCreateMonitorModal() {
    // Show modal directly and initialize vendor search
    const modal = document.getElementById('create-monitor-modal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
        // Initialize vendor search after modal is shown
        initializeVendorSearch();
    }, 10);
}

function closeCreateMonitorModal() {
    const modal = document.getElementById('create-monitor-modal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        document.getElementById('create-monitor-form').reset();
        
        // Reset vendor search and pill
        clearVendorSelection();
    }, 300);
}

function showBulkOperationsModal() {
    showMessage('Operazioni bulk in fase di sviluppo.', 'info');
}

// Initialize vendor search functionality
function initializeVendorSearch() {
    const searchInput = document.getElementById('vendor-search');
    const searchResults = document.getElementById('vendor-search-results');
    const vendorIdInput = document.getElementById('vendor-id');
    const spinner = document.getElementById('vendor-search-spinner');
    
    let searchTimeout;
    let selectedVendor = null;
    
    // Clear previous search when modal opens
    clearVendorSelection();
    
    // Focus the search input
    setTimeout(() => searchInput.focus(), 100);
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Reset validation state
        searchInput.classList.remove('valid', 'invalid');
        vendorIdInput.value = '';
        selectedVendor = null;
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            spinner.style.display = 'none';
            if (query.length > 0) {
                searchResults.innerHTML = '<div class="vendor-search-message">Inserisci almeno 2 caratteri per la ricerca</div>';
                searchResults.style.display = 'block';
            }
            return;
        }
        
        // Debounce search requests
        searchTimeout = setTimeout(() => {
            performVendorSearch(query);
        }, 300);
    });
    
    searchInput.addEventListener('blur', function() {
        // Hide results after a delay to allow clicks on results
        setTimeout(() => {
            if (!selectedVendor) {
                searchResults.style.display = 'none';
            }
        }, 150);
    });
    
    searchInput.addEventListener('focus', function() {
        if (searchResults.innerHTML && this.value.length >= 2) {
            searchResults.style.display = 'block';
        }
    });
    
    // Keyboard navigation support
    searchInput.addEventListener('keydown', function(e) {
        const results = searchResults.querySelectorAll('.vendor-search-result');
        const selectedResult = searchResults.querySelector('.vendor-search-result.selected');
        let newIndex = -1;
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (selectedResult) {
                    const currentIndex = Array.from(results).indexOf(selectedResult);
                    newIndex = Math.min(currentIndex + 1, results.length - 1);
                } else {
                    newIndex = 0;
                }
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                if (selectedResult) {
                    const currentIndex = Array.from(results).indexOf(selectedResult);
                    newIndex = Math.max(currentIndex - 1, 0);
                } else {
                    newIndex = results.length - 1;
                }
                break;
                
            case 'Enter':
                e.preventDefault();
                if (selectedResult) {
                    selectedResult.click();
                }
                break;
                
            case 'Escape':
                e.preventDefault();
                searchResults.style.display = 'none';
                break;
        }
        
        // Update selection
        if (newIndex >= 0 && results[newIndex]) {
            results.forEach(r => r.classList.remove('selected'));
            results[newIndex].classList.add('selected');
            // Scroll into view if needed
            results[newIndex].scrollIntoView({ block: 'nearest' });
        }
    });
    
    function performVendorSearch(query) {
        spinner.style.display = 'block';
        
        fetch(monitorManagement.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'monitor_search_vendors',
                nonce: monitorManagement.nonce,
                search: query
            })
        })
        .then(response => response.json())
        .then(data => {
            spinner.style.display = 'none';
            
            if (data.success) {
                displaySearchResults(data.data.vendors);
            } else {
                showSearchError(data.data || 'Errore nella ricerca');
            }
        })
        .catch(error => {
            spinner.style.display = 'none';
            showSearchError('Errore di rete: ' + error.message);
        });
    }
    
    function displaySearchResults(vendors) {
        if (vendors.length === 0) {
            searchResults.innerHTML = '<div class="vendor-search-no-results">Nessun vendor trovato</div>';
        } else {
            let resultsHTML = '';
            vendors.forEach(vendor => {
                resultsHTML += `
                    <div class="vendor-search-result" data-vendor-id="${vendor.ID}" data-vendor-name="${vendor.display_name}">
                        <div class="vendor-result-name">${vendor.display_name}</div>
                        <div class="vendor-result-details">
                            @${vendor.user_login} • ${vendor.user_email}
                        </div>
                        <div class="vendor-result-shop">${vendor.shop_name}</div>
                    </div>
                `;
            });
            searchResults.innerHTML = resultsHTML;
            
            // Add click handlers to results
            searchResults.querySelectorAll('.vendor-search-result').forEach(result => {
                result.addEventListener('click', function() {
                    const vendor = vendors.find(v => v.ID == this.dataset.vendorId);
                    selectVendor({
                        id: this.dataset.vendorId,
                        name: this.dataset.vendorName,
                        displayText: this.querySelector('.vendor-result-name').textContent,
                        username: vendor.user_login,
                        email: vendor.user_email,
                        shopName: vendor.shop_name
                    });
                });
            });
        }
        
        searchResults.style.display = 'block';
    }
    
    function selectVendor(vendor) {
        selectedVendor = vendor;
        
        // Hide search container and show pill
        const wrapper = document.querySelector('.vendor-selection-wrapper');
        const pill = document.getElementById('vendor-selected-pill');
        const description = document.getElementById('vendor-search-description');
        
        // Update pill content
        const pillName = pill.querySelector('.vendor-pill-name');
        const pillDetails = pill.querySelector('.vendor-pill-details');
        const pillInitial = pill.querySelector('.vendor-pill-initial');
        
        pillName.textContent = vendor.displayText;
        pillDetails.textContent = `@${vendor.username} • ${vendor.email}`;
        pillInitial.textContent = vendor.displayText.charAt(0).toUpperCase();
        
        // Set form values
        vendorIdInput.value = vendor.id;
        searchInput.classList.add('valid');
        
        // Show pill and hide search
        pill.style.display = 'block';
        wrapper.classList.add('has-selection');
        description.textContent = 'Vendor selezionato correttamente';
        
        // Hide search results
        searchResults.style.display = 'none';
    }
    
    function showSearchError(message) {
        searchResults.innerHTML = `<div class="vendor-search-no-results">⚠️ ${message}</div>`;
        searchResults.style.display = 'block';
    }
}

// Clear vendor selection function
function clearVendorSelection() {
    const wrapper = document.querySelector('.vendor-selection-wrapper');
    const pill = document.getElementById('vendor-selected-pill');
    const searchInput = document.getElementById('vendor-search');
    const vendorIdInput = document.getElementById('vendor-id');
    const description = document.getElementById('vendor-search-description');
    
    // Hide pill and show search
    pill.style.display = 'none';
    wrapper.classList.remove('has-selection');
    
    // Reset form values
    searchInput.value = '';
    vendorIdInput.value = '';
    searchInput.classList.remove('valid', 'invalid');
    
    // Reset description
    description.textContent = 'Inizia a digitare per cercare (minimo 2 caratteri)';
    
    // Focus search input
    setTimeout(() => searchInput.focus(), 100);
}

// Create new monitor
function createMonitor() {
    const form = document.getElementById('create-monitor-form');
    const formData = new FormData(form);
    
    // Validate required fields
    const vendorId = formData.get('vendor_id');
    const monitorName = formData.get('monitor_name');
    const layoutType = formData.get('layout_type');
    
    if (!vendorId || !monitorName || !layoutType) {
        showMessage('Tutti i campi obbligatori devono essere compilati.', 'error');
        return;
    }
    
    // Additional validation for vendor search
    const vendorSearchInput = document.getElementById('vendor-search');
    if (!vendorSearchInput.classList.contains('valid')) {
        showMessage('Seleziona un vendor valido dalla ricerca.', 'error');
        vendorSearchInput.classList.add('invalid');
        return;
    }
    
    // Show loading
    const createBtn = document.querySelector('#create-monitor-modal .button-primary');
    createBtn.textContent = 'Creazione...';
    createBtn.disabled = true;
    
    // Send AJAX request
    fetch(monitorManagement.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'monitor_create_monitor',
            nonce: monitorManagement.nonce,
            vendor_id: vendorId,
            monitor_name: monitorName,
            monitor_description: formData.get('monitor_description') || '',
            layout_type: layoutType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Monitor creato con successo!', 'success');
            closeCreateMonitorModal();
            refreshMonitorList();
        } else {
            showMessage('Errore: ' + (data.data || 'Creazione fallita'), 'error');
        }
    })
    .catch(error => {
        showMessage('Errore di rete: ' + error.message, 'error');
    })
    .finally(() => {
        createBtn.textContent = 'Crea Monitor';
        createBtn.disabled = false;
    });
}

// Edit monitor functionality
function editMonitor(monitorId) {
    // Load monitor details and show edit modal
    fetch(monitorManagement.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'monitor_get_monitor_details',
            nonce: monitorManagement.nonce,
            monitor_id: monitorId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showEditModal(data.data.monitor);
        } else {
            showMessage('Errore caricamento dettagli: ' + data.data, 'error');
        }
    })
    .catch(error => {
        showMessage('Errore: ' + error.message, 'error');
    });
}

// Delete monitor functionality
function deleteMonitor(monitorId) {
    if (!confirm('Sei sicuro di voler eliminare questo monitor? Questa azione non può essere annullata.')) {
        return;
    }
    
    fetch(monitorManagement.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'monitor_delete_monitor',
            nonce: monitorManagement.nonce,
            monitor_id: monitorId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Monitor eliminato con successo!', 'success');
            refreshMonitorList();
        } else {
            showMessage('Errore eliminazione: ' + data.data, 'error');
        }
    })
    .catch(error => {
        showMessage('Errore: ' + error.message, 'error');
    });
}

// Toggle monitor status (enable/disable)
function toggleMonitor(monitorId, currentStatus) {
    const newStatus = currentStatus === '1' ? '0' : '1';
    const action = newStatus === '1' ? 'abilitare' : 'disabilitare';
    
    if (!confirm(`Confermi di voler ${action} questo monitor?`)) {
        return;
    }
    
    fetch(monitorManagement.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'monitor_toggle_monitor',
            nonce: monitorManagement.nonce,
            monitor_id: monitorId,
            is_enabled: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`Monitor ${newStatus === '1' ? 'abilitato' : 'disabilitato'} con successo!`, 'success');
            refreshMonitorList();
        } else {
            showMessage('Errore: ' + data.data, 'error');
        }
    })
    .catch(error => {
        showMessage('Errore: ' + error.message, 'error');
    });
}

// View monitor in new tab
function viewMonitor(monitorId) {
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
            showMessage('URL copiato negli appunti!', 'success');
        }).catch(function(err) {
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
        showMessage('URL copiato negli appunti!', 'success');
    } catch (err) {
        showMessage('Impossibile copiare automaticamente. URL: ' + text, 'info');
    }
    document.body.removeChild(textArea);
}

// Refresh monitor list
function refreshMonitorList() {
    loadMonitorTable();
}

// Load monitor table with current filters and pagination
function loadMonitorTable(page = 1) {
    document.getElementById('monitor-loading').style.display = 'block';
    document.getElementById('monitor-table-container').style.opacity = '0.5';
    
    fetch(monitorManagement.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'monitor_get_all_monitors',
            nonce: monitorManagement.nonce,
            page: page,
            per_page: 20,
            vendor_filter: monitorManagement.filters.vendor,
            layout_filter: monitorManagement.filters.layout,
            status_filter: monitorManagement.filters.status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderMonitorTable(data.data.monitors);
            renderPagination(data.data.pagination);
            updateFilterLists(data.data.filters);
        } else {
            showMessage('Errore caricamento: ' + data.data, 'error');
        }
    })
    .catch(error => {
        showMessage('Errore di rete: ' + error.message, 'error');
    })
    .finally(() => {
        document.getElementById('monitor-loading').style.display = 'none';
        document.getElementById('monitor-table-container').style.opacity = '1';
    });
}

// Render monitor table
function renderMonitorTable(monitors) {
    const tbody = document.getElementById('monitor-table-body');
    
    if (monitors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8">Nessun monitor trovato.</td></tr>';
        return;
    }
    
    tbody.innerHTML = monitors.map(monitor => {
        const statusBadge = getStatusBadge(monitor.is_enabled, monitor.associated_post_id);
        const layoutLabel = getLayoutLabel(monitor.layout_type);
        const monitorUrl = `${window.location.origin}/monitor/display/${monitor.vendor_id}/${monitor.id}/${monitor.monitor_slug}`;
        const lastAccess = monitor.last_access ? formatDate(monitor.last_access) : 'Mai';
        
        return `
            <tr data-monitor-id="${monitor.id}">
                <th scope="row" class="check-column">
                    <input type="checkbox" value="${monitor.id}" name="monitor-select[]">
                </th>
                <td>
                    <strong>${monitor.monitor_name}</strong>
                    <div class="row-actions">
                        <span class="edit"><a href="#" onclick="editMonitor(${monitor.id})">Modifica</a> |</span>
                        <span class="view"><a href="#" onclick="viewMonitor(${monitor.id})">Visualizza</a> |</span>
                        <span class="delete"><a href="#" onclick="deleteMonitor(${monitor.id})" style="color: #d63638;">Elimina</a></span>
                    </div>
                </td>
                <td>
                    <strong>${monitor.vendor_name}</strong>
                    <div style="font-size: 12px; color: #666;">ID: ${monitor.vendor_id}</div>
                </td>
                <td>
                    <span class="layout-badge layout-${monitor.layout_type}">${layoutLabel}</span>
                </td>
                <td>${statusBadge}</td>
                <td>
                    ${monitor.associated_post_id ? 
                        `<a href="/wp-admin/post.php?post=${monitor.associated_post_id}&action=edit" target="_blank">Post #${monitor.associated_post_id}</a>` : 
                        '<span style="color: #999;">Nessuna</span>'
                    }
                </td>
                <td>
                    <div class="monitor-url-display">${monitorUrl}</div>
                    <button type="button" class="button button-small copy-url-btn" data-url="${monitorUrl}" style="margin-top: 4px;">Copia</button>
                </td>
                <td>${lastAccess}</td>
                <td>
                    <div class="monitor-actions">
                        <button type="button" class="button button-small" onclick="toggleMonitor(${monitor.id}, '${monitor.is_enabled}')">
                            ${monitor.is_enabled === '1' ? 'Disabilita' : 'Abilita'}
                        </button>
                        <button type="button" class="button button-small" onclick="editMonitor(${monitor.id})" title="Modifica">✏️</button>
                        <button type="button" class="button button-small" onclick="viewMonitor(${monitor.id})" title="Visualizza">👁️</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    // Reattach copy URL event listeners
    document.querySelectorAll('.copy-url-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            if (url) copyMonitorUrl(url);
        });
    });
}

// Get status badge HTML
function getStatusBadge(isEnabled, associatedPostId) {
    if (isEnabled === '1') {
        if (associatedPostId) {
            return '<span class="monitor-status-badge monitor-status-active">Attivo</span>';
        } else {
            return '<span class="monitor-status-badge monitor-status-enabled">Abilitato</span>';
        }
    } else {
        return '<span class="monitor-status-badge monitor-status-disabled">Disabilitato</span>';
    }
}

// Get layout label
function getLayoutLabel(layoutType) {
    const labels = {
        'manifesti': 'Manifesti',
        'solo_annuncio': 'Solo Annuncio',
        'citta_multi': 'Città Multi-Agenzia'
    };
    return labels[layoutType] || layoutType;
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('it-IT');
}

// Apply filters
function applyFilters() {
    monitorManagement.filters.vendor = document.getElementById('vendor-filter').value;
    monitorManagement.filters.layout = document.getElementById('layout-filter').value;
    monitorManagement.filters.status = document.getElementById('status-filter').value;
    loadMonitorTable(1);
}

// Reset filters
function resetFilters() {
    document.getElementById('vendor-filter').value = '';
    document.getElementById('layout-filter').value = '';
    document.getElementById('status-filter').value = '';
    monitorManagement.filters = { vendor: '', layout: '', status: '' };
    loadMonitorTable(1);
}

// Show messages
function showMessage(message, type = 'info') {
    const messageContainer = document.getElementById('monitor-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `notice notice-${type} is-dismissible`;
    messageDiv.innerHTML = `<p>${message}</p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text">Chiudi</span></button>`;
    
    messageContainer.appendChild(messageDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentElement) {
            messageDiv.remove();
        }
    }, 5000);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.monitor-modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                // Reset forms if applicable
                if (modal.id === 'create-monitor-modal') {
                    document.getElementById('create-monitor-form').reset();
                    // Reset vendor search and pill
                    clearVendorSelection();
                }
            }, 300);
        }
    });
}

// Render pagination
function renderPagination(pagination) {
    const paginationContainer = document.getElementById('monitor-pagination');
    
    if (pagination.total_pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let paginationHTML = '<div class="tablenav-pages">';
    paginationHTML += `<span class="displaying-num">${pagination.total_items} elementi</span>`;
    
    if (pagination.current_page > 1) {
        paginationHTML += `<button class="button" onclick="loadMonitorTable(${pagination.current_page - 1})">« Precedente</button>`;
    }
    
    // Page numbers
    for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
        if (i === pagination.current_page) {
            paginationHTML += `<span class="button button-primary" style="margin: 0 2px;">${i}</span>`;
        } else {
            paginationHTML += `<button class="button" style="margin: 0 2px;" onclick="loadMonitorTable(${i})">${i}</button>`;
        }
    }
    
    if (pagination.current_page < pagination.total_pages) {
        paginationHTML += `<button class="button" onclick="loadMonitorTable(${pagination.current_page + 1})">Successiva »</button>`;
    }
    
    paginationHTML += '</div>';
    paginationContainer.innerHTML = paginationHTML;
}

// Update filter lists
function updateFilterLists(filters) {
    const vendorFilter = document.getElementById('vendor-filter');
    
    // Update vendor filter options
    let vendorOptions = '<option value="">Tutti i Vendor</option>';
    filters.vendors.forEach(vendor => {
        const selected = monitorManagement.filters.vendor == vendor.vendor_id ? 'selected' : '';
        vendorOptions += `<option value="${vendor.vendor_id}" ${selected}>${vendor.vendor_name}</option>`;
    });
    vendorFilter.innerHTML = vendorOptions;
}

// Show edit modal
function showEditModal(monitor) {
    // Create edit modal if it doesn't exist
    if (!document.getElementById('edit-monitor-modal')) {
        createEditModal();
    }
    
    // Populate form with monitor data
    document.getElementById('edit-monitor-id').value = monitor.id;
    document.getElementById('edit-monitor-name').value = monitor.monitor_name;
    document.getElementById('edit-monitor-description').value = monitor.monitor_description || '';
    document.getElementById('edit-layout-type').value = monitor.layout_type;
    
    // Show additional config fields based on layout type
    toggleEditLayoutConfig(monitor.layout_type);
    
    if (monitor.layout_config) {
        if (monitor.layout_type === 'citta_multi') {
            if (monitor.layout_config.days_range) {
                document.getElementById('edit-days-range').value = monitor.layout_config.days_range;
            }
            if (monitor.layout_config.show_all_agencies !== undefined) {
                document.getElementById('edit-show-all-agencies').checked = monitor.layout_config.show_all_agencies;
            }
        }
    }
    
    const modal = document.getElementById('edit-monitor-modal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

// Create edit modal HTML
function createEditModal() {
    const modalHTML = `
        <div id="edit-monitor-modal" class="monitor-modal" style="display: none;">
            <div class="monitor-modal-content">
                <div class="monitor-modal-header">
                    <h2>✏️ Modifica Monitor</h2>
                    <span class="monitor-modal-close" onclick="closeEditMonitorModal()">&times;</span>
                </div>
                <div class="monitor-modal-body">
                    <form id="edit-monitor-form">
                        <input type="hidden" id="edit-monitor-id" name="monitor_id">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="edit-monitor-name">Nome Monitor *</label></th>
                                    <td>
                                        <input type="text" id="edit-monitor-name" name="monitor_name" required 
                                               style="width: 100%;" placeholder="Es: Monitor Sala Principale">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-monitor-description">Descrizione</label></th>
                                    <td>
                                        <textarea id="edit-monitor-description" name="monitor_description" 
                                                 style="width: 100%;" rows="3"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-layout-type">Tipo Layout *</label></th>
                                    <td>
                                        <select id="edit-layout-type" name="layout_type" required style="width: 100%;" onchange="toggleEditLayoutConfig(this.value)">
                                            <option value="manifesti">Manifesti (Default)</option>
                                            <option value="solo_annuncio">Solo Annuncio di Morte</option>
                                            <option value="citta_multi">Città Multi-Agenzia</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="edit-citta-multi-config" style="display: none;">
                                    <th scope="row"><label>Configurazione Città Multi-Agenzia</label></th>
                                    <td>
                                        <label for="edit-days-range">Giorni da visualizzare:</label>
                                        <input type="number" id="edit-days-range" name="days_range" min="1" max="30" value="7" style="width: 80px;">
                                        <br><br>
                                        <label>
                                            <input type="checkbox" id="edit-show-all-agencies" name="show_all_agencies">
                                            Mostra anche vendor non abilitati al monitor
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>
                </div>
                <div class="monitor-modal-footer">
                    <button type="button" class="button button-secondary" onclick="closeEditMonitorModal()">Annulla</button>
                    <button type="button" class="button button-primary" onclick="saveMonitorChanges()">Salva Modifiche</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Close edit modal
function closeEditMonitorModal() {
    const modal = document.getElementById('edit-monitor-modal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// Toggle layout config fields in edit modal
function toggleEditLayoutConfig(layoutType) {
    const cittaMultiConfig = document.getElementById('edit-citta-multi-config');
    if (layoutType === 'citta_multi') {
        cittaMultiConfig.style.display = 'table-row';
    } else {
        cittaMultiConfig.style.display = 'none';
    }
}

// Save monitor changes
function saveMonitorChanges() {
    const form = document.getElementById('edit-monitor-form');
    const formData = new FormData(form);
    
    const monitorId = formData.get('monitor_id');
    const monitorName = formData.get('monitor_name');
    const layoutType = formData.get('layout_type');
    
    if (!monitorId || !monitorName || !layoutType) {
        showMessage('Tutti i campi obbligatori devono essere compilati.', 'error');
        return;
    }
    
    const saveBtn = document.querySelector('#edit-monitor-modal .button-primary');
    saveBtn.textContent = 'Salvando...';
    saveBtn.disabled = true;
    
    // Prepare layout config
    let layoutConfig = {};
    if (layoutType === 'citta_multi') {
        layoutConfig.days_range = parseInt(formData.get('days_range')) || 7;
        layoutConfig.show_all_agencies = formData.has('show_all_agencies');
    }
    
    // Send AJAX request
    fetch(monitorManagement.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'monitor_update_monitor',
            nonce: monitorManagement.nonce,
            monitor_id: monitorId,
            monitor_name: monitorName,
            monitor_description: formData.get('monitor_description') || '',
            layout_type: layoutType,
            layout_config: JSON.stringify(layoutConfig)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Monitor aggiornato con successo!', 'success');
            closeEditMonitorModal();
            refreshMonitorList();
        } else {
            showMessage('Errore: ' + (data.data || 'Aggiornamento fallito'), 'error');
        }
    })
    .catch(error => {
        showMessage('Errore di rete: ' + error.message, 'error');
    })
    .finally(() => {
        saveBtn.textContent = 'Salva Modifiche';
        saveBtn.disabled = false;
    });
}

// Initialize monitor admin functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Load monitor table if we're on the right page and table exists
    if (document.getElementById('monitor-table-body')) {
        loadMonitorTable();
    }
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        el.style.cursor = 'help';
    });
});
</script>