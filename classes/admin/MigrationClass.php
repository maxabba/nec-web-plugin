<?php

namespace Dokan_Mods;

use DateTime;
use Dokan_Mods\Migration_Tasks\ManifestiMigration;
use Dokan_Mods\Migration_Tasks\NecrologiMigration;
use Dokan_Mods\Migration_Tasks\AccountsMigration;
use Dokan_Mods\Migration_Tasks\RicorrenzeMigration;
use Dokan_Mods\Migration_Tasks\RingraziamentiMigration;
use Exception;
use SplFileObject;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\MigrationClass')) {
    class MigrationClass
    {
        private $allowed_files = [
            'accounts.csv',
            'necrologi.csv',
            'ricorrenze.csv',
            'manifesti.csv',
            'ringraziamenti.csv'  // Aggiunto nuovo file
        ];
        private $upload_dir;
        private $log_file;
        private $debug_log_file;
        private $progress_file;
        private $current_step = 0;
        private $batch_size = 1000; // Numero di record da processare per batch
        private $cron_hooks = [];

        private $migration_tasks = [];
        private $base_path;
        private $memory_limit_mb = 256;
        private $max_execution_time = 30;
        
        public function __construct()
        {
            // Setup paths
            $base_dir = wp_upload_dir()['basedir'];
            $this->upload_dir = $base_dir . '/temp_migration/';
            $this->log_file = $this->upload_dir . 'migration_log.txt';
            $this->debug_log_file = $this->upload_dir . 'debug_log.txt';
            $this->progress_file = $this->upload_dir . 'migration_progress.json';
            $this->base_path = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/admin/MigrationTasks/';

            // Create directory if needed
            if (!is_dir($this->upload_dir) && !mkdir($this->upload_dir, 0755, true)) {
                error_log("Errore: Impossibile creare la directory temp_migration");
                return;
            }

            // Register hooks
            $this->registerHooks();

            // Load tasks files and initialize them
            $this->loadMigrationFiles();
            $this->initializeTasks();
        }

        private function registerHooks(): void
        {
            // Admin hooks
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('init', [$this, 'setupCronJobs']);

            // Ajax hooks
            $ajax_actions = ['start_migration', 'check_migration_status', 'get_next_step', 'stop_migration','check_image_migration_status', 'restart_image_downloads', 'stop_image_downloads', 'check_image_download_status'];
            foreach ($ajax_actions as $action) {
                add_action("wp_ajax_$action", [$this, $action]);
            }

            // Add custom cron interval
            add_filter('cron_schedules', function ($schedules) {
                $schedules['every_minute'] = [
                    'interval' => 60,
                    'display' => __('Every Minute')
                ];
                return $schedules;
            });

            add_action('wp_ajax_update_locations', array($this, 'handle_update_locations'));
            add_action('wp_ajax_nopriv_update_locations', array($this, 'handle_update_locations'));

            add_action('wp_ajax_bulk_update_anniversario_trigesimo', array($this,'bulk_update_anniversario_trigesimo'));
            add_action('wp_ajax_nopriv_bulk_update_anniversario_trigesimo', array($this,'bulk_update_anniversario_trigesimo'));

            add_action('wp_ajax_reset_migration_progress', array($this, 'reset_migration_progress'));
            add_action('wp_ajax_bulk_cleanup_migration', array($this, 'bulk_cleanup_migration'));

        }

        private function loadMigrationFiles(): void
        {
            // Load base class first
            require_once $this->base_path . 'MigrationTasks.php';

            // Load specific task files
            $task_files = [
                'NecrologiMigration.php',
                'AccountsMigration.php',
                'RicorrenzeMigration.php',
                'ManifestiMigration.php',
                'RingraziamentiMigration.php'  // Aggiunto nuovo file
            ];

            foreach ($task_files as $file) {
                $file_path = $this->base_path . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                } else {
                    error_log("Errore: File di migrazione non trovato: " . $file_path);
                }
            }
        }

        private function initializeTasks(): void
        {
            $common_params = [
                $this->upload_dir,
                $this->progress_file,
                $this->log_file,
                $this->batch_size
            ];

            // Initialize task instances
            $this->migration_tasks = [
                'necrologi' => new NecrologiMigration(...$common_params),
                'accounts' => new AccountsMigration(...$common_params),
                'ricorrenze' => new RicorrenzeMigration(...$common_params),
                'manifesti' => new ManifestiMigration(...$common_params),
                'ringraziamenti' => new RingraziamentiMigration(...$common_params)  // Aggiunta nuova istanza

            ];
        }

        public function setupCronJobs(): void
        {
            if (isset($_COOKIE['XDEBUG_SESSION'])) {
                update_option('xdebug_session', $_COOKIE['XDEBUG_SESSION']);
            }

            // Setup cron hooks for each task
            foreach ($this->allowed_files as $file) {
                $hook = __NAMESPACE__ . "_run_migration_batch_" . sanitize_title($file);
                $this->cron_hooks[$file] = $hook;
                // Aggiungi l'azione per gestire l'evento
                add_action($hook, array($this, 'process_migration_batch'), 10, 1);
            }
        }




        public function add_submenu()
        {
            add_submenu_page(
                'dokan-mod',
                'Migrazione',
                'Migrazione',
                'manage_options',
                'dokan-migration',
                array($this, 'migration_page')
            );
            $this->debug_log("Sottomenu aggiunto");
        }

        public function enqueue_scripts($hook)
        {
            $this->debug_log("Hook verificato: $hook");
            
            if ('dokan-mods_page_dokan-migration' !== $hook) {
                $this->debug_log("Hook non corrispondente, script non caricati per: $hook");
                return;
            }

            wp_enqueue_script('migration-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/admin/migration.js', array('jquery'), '1.1', true);
            wp_localize_script('migration-script', 'migrationAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('migration_nonce')
            ));
            $this->debug_log("Script caricati per la pagina di migrazione con hook: $hook");
        }

        public function migration_page()
        {
            $this->debug_log("Migration page rendered");
            $max_upload_size = min(
                $this->convert_to_bytes(ini_get('upload_max_filesize')),
                $this->convert_to_bytes(ini_get('post_max_size'))
            );
            $max_upload_size_formatted = $this->format_bytes($max_upload_size);
                ?>

            <div class="wrap">
                <h1>Data Migration</h1>
                <p>Maximum allowed upload size: <?php echo esc_html($max_upload_size_formatted); ?></p>

                <div id="migration-wizard">
                    <div id="step-indicator"></div>
                    <div id="current-step-content"></div>
                </div>

                <div id="progress-container" style="display: none;">
                    <h3>Migration Progress</h3>
                    <div class="progress-bar">
                        <div id="overall-progress-bar" class="progress"></div>
                    </div>
                    <p>Overall Progress: <span id="overall-progress-percentage">0%</span></p>
                    <div id="file-progresses"></div>
                </div>

                <button id="stop-migration" class="button button-secondary" style="display: none;">Stop Migration
                </button>

                <button id="resume-migration" class="button button-secondary" >Resume Migration
                </button>

                <!-- Bottone Pulizia Completa Ottimizzata -->
                <div style="margin-top: 30px; padding: 20px; border: 2px solid #dc3545; border-radius: 5px; background-color: #f8f9fa;">
                    <h3 style="color: #dc3545;">⚠️ Zona Pericolosa - Pulizia Completa</h3>
                    <p>Questa operazione eliminerà <strong>PERMANENTEMENTE</strong> tutti i dati migrati:</p>
                    <ul style="color: #721c24; font-weight: bold;">
                        <li>✗ Tutti i necrologi (annunci di morte)</li>
                        <li>✗ Tutti i manifesti collegati</li>
                        <li>✗ Tutte le ricorrenze (anniversari e trigesimi)</li>
                        <li>✗ Tutti i ringraziamenti</li>
                        <li>✗ Tutte le immagini collegate (PERSE PER SEMPRE)</li>
                    </ul>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 15px 0;">
                        <strong>🚨 OBBLIGATORIO:</strong> Effettuare backup completo del database prima di procedere!
                    </div>
                    <button id="advanced-cleanup-migration" class="button button-large" style="background-color: #dc3545; color: white; border-color: #dc3545;">
                        🗑️ Pulizia Completa Ottimizzata
                    </button>
                </div>

                <!-- Modale Pulizia Avanzata -->
                <div id="advanced-cleanup-modal-overlay" class="advanced-cleanup-modal-overlay" style="display: none;"></div>
                <div id="advanced-cleanup-modal" class="advanced-cleanup-modal" style="display: none;">
                    <div class="advanced-cleanup-modal-content">
                        <div class="advanced-cleanup-header">
                            <h2>⚠️ ATTENZIONE: Pulizia Completa Ottimizzata</h2>
                            <button class="advanced-cleanup-close">&times;</button>
                        </div>
                        
                        <!-- Step 1: Conferma Backup -->
                        <div id="backup-confirmation-step" class="cleanup-step">
                            <div class="advanced-cleanup-body">
                                <div class="backup-warning">
                                    <div class="warning-box">
                                        <h3>🚨 OBBLIGATORIO: Backup Database</h3>
                                        <p>Prima di procedere, è <strong>OBBLIGATORIO</strong> effettuare un backup completo del database.</p>
                                        <p><strong>SELEZIONA COSA ELIMINARE PERMANENTEMENTE:</strong></p>
                                        <div class="cleanup-selection-grid">
                                            <div class="cleanup-category">
                                                <h4>📝 Post Types</h4>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-necrologi" checked> 
                                                    Annunci di morte (<span id="count-necrologi">0</span>)
                                                </label>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-trigesimi" checked> 
                                                    Trigesimi (<span id="count-trigesimi">0</span>)
                                                </label>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-anniversari" checked> 
                                                    Anniversari (<span id="count-anniversari">0</span>)
                                                </label>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-ringraziamenti" checked> 
                                                    Ringraziamenti (<span id="count-ringraziamenti">0</span>)
                                                </label>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-manifesti" checked> 
                                                    Manifesti (<span id="count-manifesti">0</span>)
                                                </label>
                                            </div>
                                            
                                            <div class="cleanup-category">
                                                <h4>🖼️ Immagini (PERSE PER SEMPRE)</h4>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-images-necrologi" checked> 
                                                    Immagini annunci di morte (<span id="count-images-necrologi">0</span>)
                                                </label>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-images-trigesimi" checked> 
                                                    Immagini trigesimi (<span id="count-images-trigesimi">0</span>)
                                                </label>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-images-anniversari" checked> 
                                                    Immagini anniversari (<span id="count-images-anniversari">0</span>)
                                                </label>
                                                <label class="cleanup-checkbox-label">
                                                    <input type="checkbox" id="cleanup-images-general" checked> 
                                                    Altre immagini migrazione (<span id="count-images-general">0</span>)
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="cleanup-selection-actions">
                                            <button type="button" id="select-all-cleanup" class="button button-secondary">Seleziona tutto</button>
                                            <button type="button" id="deselect-all-cleanup" class="button button-secondary">Deseleziona tutto</button>
                                            <button type="button" id="select-only-posts" class="button button-secondary">Solo post (no immagini)</button>
                                            <button type="button" id="select-only-images" class="button button-secondary">Solo immagini</button>
                                        </div>
                                        
                                        <p style="color: #dc3545; font-weight: bold; font-size: 1.1em;">TOTALE: <span id="count-total">0</span> ELEMENTI</p>
                                    </div>
                                </div>
                                
                                <div class="confirmation-section">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="backup-confirmed-advanced"> 
                                        <span class="checkmark"></span>
                                        Ho effettuato il backup completo del database
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="delete-confirmed-advanced"> 
                                        <span class="checkmark"></span>
                                        Confermo di voler eliminare TUTTI i dati elencati sopra
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="images-loss-confirmed"> 
                                        <span class="checkmark"></span>
                                        Comprendo che le immagini saranno perse PER SEMPRE
                                    </label>
                                </div>
                                
                                <div class="optional-cleanup-date" style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                                    <p><strong>Opzionale:</strong> Elimina anche TUTTI i media non collegati caricati dopo questa data:</p>
                                    <input type="date" id="cleanup-cutoff-date" style="width: 200px; padding: 8px; font-size: 14px;">
                                    <p style="font-size: 0.9em; color: #666; margin-top: 5px;">⚠️ Se inserisci una data, verranno eliminati TUTTI i media caricati dopo quella data, anche se non collegati alla migrazione!</p>
                                </div>
                                
                                <div class="final-confirmation" style="margin-top: 20px; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                                    <p><strong>Conferma finale:</strong> Digita <code style="background: #dc3545; color: white; padding: 2px 6px;">DELETE</code> per confermare:</p>
                                    <input type="text" id="delete-confirmation-text" placeholder="Digita DELETE" style="width: 200px; padding: 8px; font-size: 14px;">
                                </div>
                            </div>
                            
                            <div class="advanced-cleanup-footer">
                                <button id="start-advanced-cleanup" class="button button-primary button-large" disabled>
                                    🗑️ Inizia Pulizia Completa
                                </button>
                                <button id="cancel-advanced-cleanup" class="button button-secondary button-large">
                                    Annulla
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 2: Progress -->
                        <div id="cleanup-progress-step" class="cleanup-step" style="display: none;">
                            <div class="advanced-cleanup-body">
                                <h3>Pulizia in corso...</h3>
                                <div class="progress-container">
                                    <div class="overall-progress">
                                        <h4>Progresso Generale</h4>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar">
                                                <div id="overall-cleanup-progress-bar" class="progress"></div>
                                            </div>
                                            <span id="overall-cleanup-percentage">0%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="step-progress">
                                        <h4 id="current-cleanup-step-title">Preparazione...</h4>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar">
                                                <div id="step-cleanup-progress-bar" class="progress"></div>
                                            </div>
                                            <span id="step-cleanup-percentage">0%</span>
                                        </div>
                                        <div id="step-cleanup-details"></div>
                                    </div>
                                </div>
                                
                                <div id="cleanup-log-advanced" class="cleanup-log-container"></div>
                            </div>
                            
                            <div class="advanced-cleanup-footer">
                                <button id="emergency-stop-cleanup" class="button button-secondary button-large">
                                    ⏹️ Stop Emergenza
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 3: Completamento -->
                        <div id="cleanup-complete-step" class="cleanup-step" style="display: none;">
                            <div class="advanced-cleanup-body">
                                <div style="text-align: center; padding: 20px;">
                                    <h3 style="color: #28a745;">✅ Pulizia Completata!</h3>
                                    <p>Tutti i dati di migrazione sono stati eliminati con successo.</p>
                                    <div id="cleanup-final-stats" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin: 15px 0;">
                                        <!-- Stats finali verranno inserite qui -->
                                    </div>
                                </div>
                            </div>
                            
                            <div class="advanced-cleanup-footer">
                                <button id="close-cleanup-modal" class="button button-primary button-large">
                                    Chiudi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Image Download Control Panel -->
                <div style="margin-top: 30px; padding: 20px; border: 2px solid #007cba; border-radius: 5px; background-color: #f0f8ff;">
                    <h3 style="color: #007cba;">📥 Controllo Download Immagini</h3>
                    <p>Gestisci il processo di download delle immagini per necrologi e manifesti.</p>
                    
                    <!-- Status Display -->
                    <div id="image-download-status" class="notice notice-info" style="margin: 15px 0;">
                        <p>ℹ️ Caricamento status...</p>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div id="image-download-progress" style="display: none; margin: 15px 0;">
                        <h4>Progresso Download</h4>
                        <div style="background: #f1f1f1; border-radius: 4px; padding: 3px; margin: 10px 0;">
                            <div id="image-download-progress-bar" style="background: #007cba; height: 24px; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 14px; color: #666;">
                            <span>Processate: <strong id="image-processed-count">0</strong>/<strong id="image-total-count">0</strong></span>
                            <span id="image-progress-percentage">0%</span>
                        </div>
                        <div style="margin-top: 5px; font-size: 12px; color: #666;">
                            <span>Status: <span id="image-status-text">-</span></span>
                            <span style="margin-left: 15px;">Cron: <span id="image-cron-status">-</span></span>
                            <span style="margin-left: 15px;">Rimanenti: <span id="image-remaining-count">-</span></span>
                        </div>
                    </div>
                    
                    <!-- Control Buttons -->
                    <div style="margin-top: 15px;">
                        <button id="start-image-downloads" class="button button-primary" style="margin-right: 10px;">
                            ▶️ Avvia Download
                        </button>
                        <button id="stop-image-downloads" class="button button-secondary" style="margin-right: 10px;">
                            ⏹️ Ferma Download
                        </button>
                        <button id="restart-image-downloads" class="button button-secondary" style="margin-right: 10px;">
                            🔄 Riavvia Download
                        </button>
                        <button id="refresh-image-status" class="button button-secondary">
                            🔍 Aggiorna Status
                        </button>
                    </div>
                    
                    <!-- Detailed Stats -->
                    <div id="image-download-stats" style="display: none; margin-top: 20px; padding: 15px; background: #fafafa; border-radius: 4px;">
                        <h4>Statistiche Dettagliate</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong>File Coda:</strong><br>
                                <span id="queue-file-status">-</span><br>
                                <span id="queue-file-size">-</span>
                            </div>
                            <div>
                                <strong>Ultimo Aggiornamento:</strong><br>
                                <span id="last-update-time">-</span>
                            </div>
                            <div>
                                <strong>Prossimo Cron:</strong><br>
                                <span id="next-cron-time">-</span>
                            </div>
                            <div>
                                <strong>Completamento:</strong><br>
                                <span id="completion-rate">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="migration-log"></div>

                <?php if(true === false) { ?>
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('Update Locations', 'dokan-mod'); ?></span></h2>
                    <div class="inside">
                        <p><?php _e('Select the post type and click the button below to update cities and provinces for all posts.', 'dokan-mod'); ?></p>

                        <!-- Aggiungi un menu a tendina per selezionare il tipo di post -->
                        <label for="post-type-select"><?php _e('Post Type:', 'dokan-mod'); ?></label>
                        <select id="post-type-select">
                            <option value="ringraziamento"><?php _e('Ringraziamento', 'dokan-mod'); ?></option>
                            <option value="anniversario"><?php _e('Anniversario', 'dokan-mod'); ?></option>
                            <option value="manifesto"><?php _e('Manifesto', 'dokan-mod'); ?></option>
                            <option value="trigesimo"><?php _e('Trigesimo', 'dokan-mod'); ?></option>
                        </select>

                        <button id="update-locations" class="button button-primary">Update Locations</button>

                        <div id="update-progress" style="display:none;">
                            <progress value="0" max="100"></progress>
                            <span class="percentage">0%</span>
                        </div>

                        <div id="update-log"></div>
                    </div>
                </div>


                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Bulk Update for Anniversario or Trigesimo', 'dokan-mod'); ?></span></h2>
                    <div class="inside">
                        <p><?php _e('Select "Anniversario" or "Trigesimo" and click the button below to perform a bulk update.', 'dokan-mod'); ?></p>
                        <label for="bulk-type-select"><?php _e('Bulk Type:', 'dokan-mod'); ?></label>
                        <select id="bulk-type-select">
                            <option value="anniversario"><?php _e('Anniversario', 'dokan-mod'); ?></option>
                            <option value="trigesimo"><?php _e('Trigesimo', 'dokan-mod'); ?></option>
                        </select>
                        <button id="bulk-update" class="button button-primary">Bulk Update</button>
                        <div id="bulk-update-progress" style="display:none;">
                            <progress value="0" max="100"></progress>
                            <span class="percentage">0%</span>
                        </div>
                        <div id="bulk-update-log"></div>
                    </div>
                </div>
                <?php } ?>
                <script>

                    jQuery(document).ready(function ($) {
                        $('#bulk-update').on('click', function () {
                            $(this).prop('disabled', true);
                            $('#bulk-update-progress').show();

                            const bulkType = $('#bulk-type-select').val();
                            performBulkUpdate(0, 0, bulkType);
                        });

                        function performBulkUpdate(offset, processed, bulkType) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'bulk_update_anniversario_trigesimo',
                                    nonce: '<?php echo wp_create_nonce("bulk_update_nonce"); ?>',
                                    offset: offset,
                                    processed: processed,
                                    bulk_type: bulkType
                                },
                                success: function (response) {
                                    if (response.success) {
                                        $('#bulk-update-log').append('<p class="success">' + response.data.message + '</p>');

                                        if (response.data.continue) {
                                            performBulkUpdate(response.data.offset, response.data.processed, response.data.bulk_type);
                                        } else {
                                            $('#bulk-update-progress progress').val(100);
                                            $('#bulk-update-progress .percentage').text('100%');
                                            $('#bulk-update').prop('disabled', false);
                                        }
                                    } else {
                                        $('#bulk-update-log').append('<p class="error">' + response.data.message + '</p>');
                                        $('#bulk-update').prop('disabled', false);
                                    }
                                },
                                error: function () {
                                    $('#bulk-update-log').append('<p class="error">An error occurred.</p>');
                                    $('#bulk-update').prop('disabled', false);
                                }
                            });
                        }
                    });



                    jQuery(document).ready(function ($) {
                        $('#update-locations').on('click', function () {
                            $(this).prop('disabled', true);
                            $('#update-progress').show();

                            // Ottieni il tipo di post selezionato
                            const postType = $('#post-type-select').val();
                            updateLocations(0, 0, postType);
                        });

                        function updateLocations(offset, processed, postType) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'update_locations',
                                    nonce: '<?php echo wp_create_nonce("update_locations_nonce"); ?>',
                                    offset: offset,
                                    processed: processed,
                                    post_type: postType // Passa il tipo di post
                                },
                                success: function (response) {
                                    if (response.success) {
                                        $('#update-log').append('<p class="success">' + response.data.message + '</p>');

                                        if (response.data.continue) {
                                            updateLocations(response.data.offset, response.data.processed, response.data.post_type);
                                        } else {
                                            $('#update-progress progress').val(100);
                                            $('#update-progress .percentage').text('100%');
                                            $('#update-locations').prop('disabled', false);
                                        }
                                    } else {
                                        $('#update-log').append('<p class="error">' + response.data.message + '</p>');
                                        $('#update-locations').prop('disabled', false);
                                    }
                                },
                                error: function () {
                                    $('#update-log').append('<p class="error">An error occurred.</p>');
                                    $('#update-locations').prop('disabled', false);
                                }
                            });
                        }
                    });

                </script>


            </div>

            <style>
                .wrap {
                    max-width: 800px;
                }

                #migration-wizard {
                    margin-top: 20px;
                }

                #step-indicator {
                    font-size: 1.1em;
                    margin-bottom: 15px;
                    color: #2271b1;
                }

                #current-step-content {
                    background-color: #f0f0f1;
                    padding: 20px;
                    border-radius: 4px;
                }

                .progress-bar {
                    height: 20px;
                    background-color: #f0f0f1;
                    border-radius: 4px;
                    overflow: hidden;
                    margin-top: 10px;
                }

                .progress {
                    height: 100%;
                    background-color: #00a32a;
                    transition: width 0.5s ease-in-out;
                }

                #migration-log {
                    margin-top: 20px;
                    padding: 15px;
                    background-color: #f9f9f9;
                    border: 1px solid #e5e5e5;
                    border-radius: 4px;
                    max-height: 200px;
                    overflow-y: auto;
                }

                .file-progress {
                    margin-bottom: 15px;
                }

                .file-name {
                    margin-bottom: 5px;
                }

                .processed-row {
                    font-size: 0.9em;
                    color: #666;
                    margin-top: 5px;
                }
                
                /* Advanced Cleanup Modal Styles */
                .advanced-cleanup-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    z-index: 9998;
                }

                .advanced-cleanup-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .advanced-cleanup-modal-content {
                    background: white;
                    border-radius: 8px;
                    max-width: 700px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                }

                .advanced-cleanup-header {
                    padding: 20px;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #dc3545;
                    color: white;
                    border-radius: 8px 8px 0 0;
                }

                .advanced-cleanup-header h2 {
                    margin: 0;
                    color: white;
                }

                .advanced-cleanup-close {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: white;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .advanced-cleanup-body {
                    padding: 20px;
                }

                .warning-box {
                    background: #fff3cd;
                    border: 2px solid #ffeaa7;
                    border-radius: 5px;
                    padding: 15px;
                    margin-bottom: 20px;
                }

                .warning-box h3 {
                    color: #856404;
                    margin-top: 0;
                }

                .warning-box ul {
                    margin: 10px 0;
                    padding-left: 20px;
                }

                .warning-box li {
                    margin: 5px 0;
                    color: #721c24;
                    font-weight: bold;
                }

                .confirmation-section {
                    margin: 20px 0;
                }

                .checkbox-label {
                    display: flex;
                    align-items: center;
                    margin: 15px 0;
                    cursor: pointer;
                    font-weight: bold;
                }

                .checkbox-label input[type="checkbox"] {
                    margin-right: 10px;
                    transform: scale(1.3);
                }

                /* Nuovi stili per selezione granulare cleanup */
                .cleanup-selection-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin: 20px 0;
                }

                .cleanup-category {
                    border: 1px solid #ddd;
                    padding: 15px;
                    border-radius: 5px;
                    background: #f9f9f9;
                }

                .cleanup-category h4 {
                    margin: 0 0 10px 0;
                    color: #333;
                    font-size: 14px;
                    font-weight: bold;
                }

                .cleanup-checkbox-label {
                    display: block;
                    margin: 8px 0;
                    cursor: pointer;
                    font-weight: normal;
                    font-size: 13px;
                    color: #666;
                }

                .cleanup-checkbox-label input[type="checkbox"] {
                    margin-right: 8px;
                    transform: scale(1.1);
                }

                .cleanup-selection-actions {
                    margin: 15px 0;
                    text-align: center;
                }

                .cleanup-selection-actions .button {
                    margin: 0 5px;
                    font-size: 12px;
                    padding: 4px 8px;
                    height: auto;
                }

                @media (max-width: 768px) {
                    .cleanup-selection-grid {
                        grid-template-columns: 1fr;
                        gap: 15px;
                    }
                }

                .progress-container {
                    margin: 20px 0;
                }

                .overall-progress, .step-progress {
                    margin-bottom: 20px;
                }

                .progress-bar-container {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin: 10px 0;
                }

                .progress-bar {
                    flex-grow: 1;
                    height: 25px;
                    background-color: #f0f0f1;
                    border-radius: 4px;
                    overflow: hidden;
                }

                .progress {
                    height: 100%;
                    background: linear-gradient(90deg, #28a745, #20c997);
                    transition: width 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                }

                .cleanup-log-container {
                    max-height: 200px;
                    overflow-y: auto;
                    background: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 10px;
                    margin-top: 15px;
                    font-family: monospace;
                    font-size: 12px;
                }

                .advanced-cleanup-footer {
                    padding: 20px;
                    border-top: 1px solid #ddd;
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                }

                #current-cleanup-step-title {
                    color: #0073aa;
                    margin-bottom: 10px;
                }

                #step-cleanup-details {
                    font-size: 0.9em;
                    color: #666;
                    margin-top: 5px;
                }

                .cleanup-step {
                    min-height: 400px;
                }

                .final-confirmation input {
                    margin-top: 10px;
                }
            </style>
            <?php
        }

        public function start_migration()
        {
            try {
                // Verifiche di sicurezza di base
                if (!check_admin_referer('migration_nonce', 'migration_nonce') ||
                    !current_user_can('manage_options') ||
                    !isset($_POST['current_file'])) {
                    throw new Exception("Errore di sicurezza o parametri mancanti");
                }

                $current_file = $_POST['current_file'];

                // Gestione skip
                if (isset($_POST['skip_step']) && $_POST['skip_step'] === 'on') {
                    $this->log("Step per il file {$current_file} saltato");
                    wp_send_json_success("Step saltato");
                }

                // Verifica file upload
                $uploaded_file = current($_FILES);
                if (!$uploaded_file || $uploaded_file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Errore upload: " . ($uploaded_file['error'] ?? 'File non trovato'));
                }

                // Verifica directory
                if (!is_dir($this->upload_dir) || !is_writable($this->upload_dir)) {
                    throw new Exception("Errore accesso directory upload");
                }

                $destination = $this->upload_dir . $current_file;
                file_exists($destination) && unlink($destination);

                if (!move_uploaded_file($uploaded_file['tmp_name'], $destination)) {
                    throw new Exception("Errore nel salvataggio del file");
                }

                // Avvia migrazione
                $this->init_migration($current_file);
                wp_send_json_success("File caricato e migrazione avviata");

            } catch (Exception $e) {
                $debug_info = [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
                wp_send_json_error($e->getMessage(), 500, ['debug' => $debug_info]);
            }
        }

        private function init_migration($file)
        {
            $this->debug_log("start_migration chiamato per {$file}");
            $this->log("Migrazione iniziata per {$file}");

            if (file_exists($this->upload_dir . 'stopMigration.txt')) {    // Elimina il file stopMigration se esiste
                unlink($this->upload_dir . 'stopMigration.txt');
            }

            $this->initialize_progress_file($file);    // Inizializza il file di progresso per questo specifico file

            if (!in_array($file, $this->allowed_files)) {    // Verifica che il file caricato sia tra quelli permessi
                $this->log("Errore: Il file {$file} non è tra i file permessi per la migrazione.");
                wp_send_json_error("Il file {$file} non è tra i file permessi per la migrazione.");
            }

            if (isset($this->cron_hooks[$file])) {    // Programma il batch ricorrente ogni minuto solo per il file specificato
                $hook = $this->cron_hooks[$file];

                wp_clear_scheduled_hook($hook, array($file));    // Rimuovi eventuali eventi programmati esistenti per questo hook

                $next_run = time() + 60;    // Imposta il prossimo run tra 60 secondi
                if (file_exists($this->upload_dir . $file)) {
                    wp_schedule_event($next_run, 'every_minute', $hook, array($file));    // Programma un nuovo evento solo per il file corrente
                    $this->log("Nuovo batch programmato per {$file} alle " . date('Y-m-d H:i:s', $next_run));
                } else {
                    $this->log("Errore: Il file {$file} non esiste.");
                }

                $this->log("Nuovo batch programmato per {$file} alle " . date('Y-m-d H:i:s', $next_run));

                //do_action($hook, $file);    // Esegui immediatamente il primo batch
            } else {
                $this->log("Errore: Nessun cron hook definito per {$file}");
                wp_send_json_error("Errore interno: Nessun cron hook definito per {$file}");
            }
        }

        private function get_memory_usage_mb()
        {
            return round(memory_get_usage(true) / 1048576, 2);
        }

        private function should_continue_immediately($file)
        {
            $current_memory = $this->get_memory_usage_mb();
            $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            
            $progress = $this->get_progress($file);
            $has_more_work = $progress['processed'] < $progress['total'];
            
            $memory_ok = $current_memory < $this->memory_limit_mb;
            $time_ok = $execution_time < $this->max_execution_time;
            
            $this->log("Auto-continuation check for $file: Memory {$current_memory}MB/{$this->memory_limit_mb}MB - Time: {$execution_time}s/{$this->max_execution_time}s - More work: " . ($has_more_work ? 'yes' : 'no'));
            
            return $has_more_work && $memory_ok && $time_ok;
        }

        private function get_progress($file)
        {
            $default = ['processed' => 0, 'total' => 0, 'percentage' => 0];

            if (!file_exists($this->progress_file)) {
                return $default;
            }

            $progress = @json_decode(file_get_contents($this->progress_file), true);

            return isset($progress[$file]['processed'], $progress[$file]['total'], $progress[$file]['percentage'])
                ? $progress[$file]
                : $default;
        }

        public function process_migration_batch($file)
        {
            if (!file_exists($this->upload_dir . $file)) {
                $this->log("Errore: Il file {$file} non esiste.");
                return;
            }

            wp_cache_flush();
            gc_collect_cycles();
            
            // Controllo status ongoing con verifica timestamp
            $status = $this->get_progress_status($file);
            if ($status === 'ongoing') {
                $this->log("Status 'ongoing' rilevato per $file - verifica se processo è attivo");
                
                // Verifica se il processo è realmente attivo tramite timestamp
                if ($this->is_process_active($file)) {
                    $this->log("Processo attivo per $file - skip esecuzione");
                    return;
                } else {
                    $this->log("Processo non attivo per $file - procedo con esecuzione");
                }
            }

            $hook = $this->cron_hooks[$file];
            $this->log("Processing migration batch for {$file}");

            // Execute the migration batch and get result
            $result = $this->execute_migration_batch($file);
            
            // Handle different result types
            if ($result === true) {
                // Migration fully completed
                wp_unschedule_hook($hook);
                $this->log("Migration completed for {$file}. Batch cleared.");
            } elseif ($result === 'auto_continue') {
                // Can continue immediately if resources allow
                if ($this->should_continue_immediately($file)) {
                    $this->log("Auto-continuing migration for {$file}");
                    // Recursive call for immediate continuation
                    $this->process_migration_batch($file);
                } else {
                    $this->log("Auto-continuation not possible for {$file}, falling back to cron scheduling");
                    // Will continue via cron at next scheduled time
                }
            } elseif ($result === false) {
                // Continue via cron at next scheduled time
                $this->log("Batch processed for {$file}, waiting for next cron execution");
            }
        }

        private function execute_migration_batch($file)
        {
            switch ($file) {
                case 'accounts.csv':
                    return $this->migration_tasks['accounts']->migrate_accounts_batch('accounts.csv');
                case 'necrologi.csv':
                    return $this->migration_tasks['necrologi']->migrate_necrologi_batch('necrologi.csv');
                case 'ricorrenze.csv':
                    return $this->migration_tasks['ricorrenze']->migrate_ricorrenze_batch('ricorrenze.csv');
                case 'manifesti.csv':
                    return $this->migration_tasks['manifesti']->migrate_manifesti_batch('manifesti.csv');
                case 'ringraziamenti.csv':
                    return $this->migration_tasks['ringraziamenti']->migrate_ringraziamenti_batch('ringraziamenti.csv');
                default:
                    $this->log("Errore: Il file {$file} non è riconosciuto per la migrazione.");
                    return false;
            }
        }

        public function stop_migration()
        {
            file_put_contents($this->upload_dir . 'stopMigration.txt', 'stop');

            foreach ($this->allowed_files as $file) {
                $hook = $this->cron_hooks[$file];
                wp_unschedule_hook($hook);
                $this->log("Batch cleared for {$file}");
            }

            $this->log("Migration stopped by user");
            wp_send_json_success("Migration stopped");
        }


        private function get_progress_status($file)
        {
            $progress = json_decode(file_get_contents($this->progress_file), true);
            return isset($progress[$file]['status']) ? $progress[$file]['status'] : 'not_started';
        }
        
        private function is_process_active($file)
        {
            $progress = json_decode(file_get_contents($this->progress_file), true);
            
            if (!isset($progress[$file]['status_timestamp'])) {
                $this->log("Nessun timestamp trovato per $file - considerato non attivo");
                return false;
            }
            
            $timestamp = $progress[$file]['status_timestamp'];
            $current_time = time();
            $elapsed = $current_time - $timestamp;
            $timeout_threshold = 120; // 2 minuti
            
            $this->log("Controllo attività per $file: elapsed {$elapsed}s, threshold {$timeout_threshold}s");
            
            return $elapsed <= $timeout_threshold;
        }

        public function check_migration_status()
        {
            $this->debug_log("check_migration_status chiamato");

            // Verifica sicurezza con early return
            if (!check_ajax_referer('migration_nonce', 'nonce', false)) {
                $this->debug_log("Verifica nonce fallita in check_migration_status");
                wp_send_json_error('Invalid nonce.');
                return;
            }
            if (!current_user_can('manage_options')) {
                $this->debug_log("Permessi insufficienti in check_migration_status");
                wp_send_json_error('Permesso negato.');
                return;
            }

            // Verifica esistenza file con early return
            if (!is_readable($this->progress_file)) {
                $this->debug_log("Il file di progresso non esiste in check_migration_status");
                wp_send_json_success('Progress file not found.');
                return;
            }

            // Lettura file ottimizzata con cache dei risultati

            $progress_content = file_get_contents($this->progress_file);
            $progress = $progress_content ? json_decode($progress_content, true) : [];


            // Ottimizzazione lettura log con SplFileObject
            $log_lines = [];
            if (is_readable($this->log_file)) {
                $file = new SplFileObject($this->log_file, 'r');
                $file->seek(PHP_INT_MAX);
                $total_lines = $file->key();

                $start_line = max(0, $total_lines - 100);
                $file->seek($start_line);

                while (!$file->eof()) {
                    $line = $file->current();
                    if (trim($line) !== '') {
                        $log_lines[] = $line;
                    }
                    $file->next();
                }
            }

            // Calcolo progresso ottimizzato
            $total_progress = 0;
            $total_files = 0;

            if (is_array($progress)) {
                foreach ($progress as $file_progress) {
                    if (isset($file_progress['percentage'])) {
                        $total_progress += $file_progress['percentage'];
                        $total_files++;
                    }
                }
            }

            $overall_percentage = $total_files ? round($total_progress / $total_files, 2) : 0;


            $this->debug_log("Invio risposta JSON per check_migration_status");
            wp_send_json_success([
                'progress' => $progress,
                'overall_percentage' => $overall_percentage,
                'log' => implode("\n", $log_lines)
            ]);
        }


        public function check_image_migration_status()
        {
            $this->debug_log("check_image_migration_status chiamato");

            // Verifica sicurezza con early return
            if (!check_ajax_referer('migration_nonce', 'nonce', false)) {
                $this->debug_log("Verifica nonce fallita in check_image_migration_status");
                wp_send_json_error('Invalid nonce.');
                return;
            }
            if (!current_user_can('manage_options')) {
                $this->debug_log("Permessi insufficienti in check_image_migration_status");
                wp_send_json_error('Permesso negato.');
                return;
            }

            // Verifica esistenza file con early return
            if (!is_readable($this->progress_file)) {
                $this->debug_log("Il file di progresso non esiste in check_image_migration_status");
                wp_send_json_success('Progress file not found.');
                return;
            }

            // Lettura file ottimizzata con cache dei risultati

            $progress_content = file_get_contents($this->progress_file);
            $progress = $progress_content ? json_decode($progress_content, true) : [];

            //if in progress exist key starting with image_download_queue
            $image_download_queue = array_filter($progress, function($key) {
                return strpos($key, 'image_download_queue') !== false;
            }, ARRAY_FILTER_USE_KEY);

            $this->debug_log("Invio risposta JSON per check_image_migration_status");
            wp_send_json_success([
                'progress' => $image_download_queue
            ]);


        }

        private function initialize_progress_file($file)
        {
            $this->debug_log("Inizializzazione progresso: $file");

            $progress = file_exists($this->progress_file)
                ? json_decode(file_get_contents($this->progress_file), true) ?: []
                : [];

            $progress[$file] = [
                'processed' => 0,
                'total' => 0,
                'percentage' => 0,
                'status' => 'not_started'
            ];

            if (file_put_contents($this->progress_file, json_encode($progress)) === false) {
                $this->debug_log("Errore nella scrittura del progresso");
                return;
            }

            file_put_contents($this->log_file, '');
            $this->debug_log("Inizializzazione completata");
        }


        public function get_next_step()
        {
            if (!check_ajax_referer('migration_nonce', 'nonce', false)) {
                wp_send_json_error('Invalid nonce.');
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permesso negato.');
            }

            $this->current_step = isset($_POST['current_step']) ? intval($_POST['current_step']) : 0;

            $next_step = $this->current_step + 1;


            if ($next_step <= count($this->allowed_files)) {
                $current_file = $this->allowed_files[$next_step - 1];
                $step_content = $this->get_step_content($current_file);
                wp_send_json_success(array(
                    'step' => $next_step,
                    'total_steps' => count($this->allowed_files),
                    'content' => $step_content,
                    'current_file' => $current_file
                ));
            } else {
                wp_send_json_success(array(
                    'step' => 'complete',
                    'content' => '<h2>Migrazione Completata</h2><p>Tutti i file sono stati elaborati con successo.</p>'
                ));
            }
        }


        private function get_step_content($file)
        {
            $file_name = ucfirst(str_replace('.csv', '', $file));
            $step = $this->current_step + 1;
            $content = "
            <h2>Step {$step}: Upload {$file_name}</h2>
            <form id='migration-form' method='post' enctype='multipart/form-data'>
                " . wp_nonce_field('migration_nonce', 'migration_nonce', true, false) . "
                <input type='hidden' name='action' value='start_migration'>
                <input type='hidden' name='current_file' value='" . esc_attr($file) . "'>
                <label for='" . esc_attr($file) . "'>{$file_name}: </label>
                <input type='file' name='" . esc_attr($file) . "' id='" . esc_attr($file) . "' class='filename' accept='.csv'><br><br>
                <label for='skip_step'>
                    <input type='checkbox' name='skip_step' id='skip_step' onchange='skip_changed(this);'> Skip this step
                </label><br><br>
                <input type='submit' id='submit-button' value='Upload and Migrate' class='button button-primary'>
            </form>
            <div id='upload-progress' style='display:none;'>
                <p>Upload in progress: <span id='progress-percentage'>0%</span></p>
                <div class='progress-bar'>
                    <div id='progress' class='progress'></div>
                </div>
            </div>";
            return $content;
        }

        protected function log($message)
        {
            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[$timestamp] $message\n";

            if (file_put_contents($this->log_file, $log_entry, FILE_APPEND) === false) {
                error_log("Failed to write to custom log file. Message was: $log_entry");
            }
        }

        private function debug_log($message)
        {
            if(!defined('DOKAN_SELECT_PRODUCTS_DEBUG') || !DOKAN_SELECT_PRODUCTS_DEBUG) {
                return;
            }

            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[$timestamp] $message\n";
            file_put_contents($this->debug_log_file, $log_entry, FILE_APPEND);
        }

        private function convert_to_bytes($value)
        {
            $unit = strtolower(substr($value, -1));
            $value = (int)$value;
            switch ($unit) {
                case 'g':
                    $value *= 1024;
                case 'm':
                    $value *= 1024;
                case 'k':
                    $value *= 1024;
            }
            return $value;
        }

        private function format_bytes($bytes, $precision = 2)
        {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= (1 << (10 * $pow));
            return round($bytes, $precision) . ' ' . $units[$pow];
        }

        public function handle_update_locations()
        {
            check_ajax_referer('update_locations_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }

            $batch_size = 5000; // Numero di post da processare per volta
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $processed = isset($_POST['processed']) ? intval($_POST['processed']) : 0;
            $current_post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : null;

            global $dbClassInstance;
            $errors = array();

            // Array dei post types e dei loro campi ACF
            $post_types = array(
                'ringraziamento' => 'annuncio_di_morte',
                'anniversario' => 'annuncio_di_morte',
                'manifesto' => 'annuncio_di_morte_relativo',
                'trigesimo' => 'annuncio_di_morte'
            );

            // Controlla se il tipo di post attuale è valido
            if (!$current_post_type || !array_key_exists($current_post_type, $post_types)) {
                wp_send_json_error(array('message' => 'Invalid post type'));
                return;
            }

            $acf_field = $post_types[$current_post_type];
            $args = array(
                'post_type' => $current_post_type,
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'post_status' => 'any',
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            );

            $post_ids = get_posts($args);
            $updated = 0;
            $continue = !empty($post_ids);

            foreach ($post_ids as $post_id) {
                $city = get_field('citta', $post_id);
                $province = get_field('provincia', $post_id);

                if ($city && $province && $dbClassInstance->is_a_valid_comune($city) && $dbClassInstance->is_a_valid_provincia($province)) {
                    $updated++;
                    $processed++;
                    continue;
                }

                try {
                    $necrologio_id = get_field($acf_field, $post_id);

                    if (!$necrologio_id) {
                        $errors[] = "No necrologio ID found for {$current_post_type} {$post_id}";
                        continue;
                    }

                    $city = get_field('citta', $necrologio_id);
                    if (!$city || !$dbClassInstance->is_a_valid_comune($city)) {
                        $author_id = get_post_field('post_author', $post_id);
                        $user_city = get_user_meta($author_id, 'dokan_profile_settings', true);
                        $address = $user_city['address'];
                        $user_city = $address['city'];

                        if (isset($user_city) && $user_city) {
                            $city = $user_city;
                        } else {
                            $errors[] = "No city found for author of {$current_post_type} {$post_id}";
                            continue;
                        }
                    }

                    $province = $dbClassInstance->get_provincia_by_comune($city);
                    if (!$province) {
                        $errors[] = "No province found for city {$city}";
                        continue;
                    }

                    if (!get_field('provincia', $necrologio_id)) {
                        update_field('provincia', $province, $necrologio_id);
                    }

                    update_field('citta', $city, $post_id);
                    update_field('provincia', $province, $post_id);

                    $updated++;
                    $processed++;
                } catch (Exception $e) {
                    $errors[] = "Error processing {$current_post_type} {$post_id}: " . $e->getMessage();
                }

                wp_cache_flush();
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            $message = sprintf(
                'Processed %d posts %s in this batch. Total processed: %d. %s',
                $updated,
                $current_post_type,
                $processed,
                !empty($errors) ? "\nErrors encountered: " . implode("\n", array_slice($errors, -5)) : ''
            );

            wp_send_json_success(array(
                'continue' => $continue,
                'offset' => $offset + $batch_size,
                'processed' => $processed,
                'message' => $message,
                'post_type' => $current_post_type
            ));
        }

        public function bulk_update_anniversario_trigesimo()
        {
            check_ajax_referer('bulk_update_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }

            $batch_size = 1000;
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $processed = isset($_POST['processed']) ? intval($_POST['processed']) : 0;
            $current_post_type = isset($_POST['bulk_type']) ? sanitize_text_field($_POST['bulk_type']) : null;

            $post_types = array(
                'anniversario' => 'annuncio_di_morte',
                'trigesimo' => 'annuncio_di_morte'
            );

            if (!$current_post_type || !array_key_exists($current_post_type, $post_types)) {
                wp_send_json_error(array('message' => 'Invalid post type'));
                return;
            }

            $acf_field = $post_types[$current_post_type];

            $args = array(
                'post_type' => $current_post_type,
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'post_status' => 'any',
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            );

            $post_ids = get_posts($args);
            $updated = 0;
            $continue = !empty($post_ids);
            $errors = array();

            foreach ($post_ids as $post_id) {
                try {
                    $necrologio_id = get_field($acf_field, $post_id);
                    if (!$necrologio_id) {
                        $errors[] = "No necrologio ID found for {$current_post_type} {$post_id}";
                        continue;
                    }

                    // Tentativi di creazione di oggetto DateTime da $date_field con vari formati
                    $date_field = get_field('data_di_morte', $necrologio_id) ?: get_the_date('Y-m-d', $necrologio_id);
                    $date_formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Ymd', 'd.m.Y'];
                    $date_obj = null;

                    foreach ($date_formats as $format) {
                        $date_obj = DateTime::createFromFormat($format, $date_field);
                        if ($date_obj !== false) {
                            break;
                        }
                    }

                    // Se $date_obj è ancora null, logga un errore e passa al prossimo post
                    if (!$date_obj) {
                        $errors[] = "Invalid date format for {$current_post_type} {$post_id} - date: {$date_field}";
                        continue;
                    }

                    $date_str = $date_obj->format('Y-m-d');

                    if ($current_post_type === 'trigesimo') {
                        $trigesimo_field = get_field('trigesimo_data', $post_id);
                        if (!$trigesimo_field || $trigesimo_field < strtotime('1980-01-01')) {
                            $new_date = $date_obj->modify('+1 month')->format('Y-m-d');
                            update_field('trigesimo_data', $new_date, $post_id);
                        }
                    } elseif ($current_post_type === 'anniversario') {
                        $anniversario_field = get_field('anniversario_n_anniversario', $post_id);
                        if (!$anniversario_field) {
                            $years_to_add = get_field('anniversario_data', $post_id);
                            if ($years_to_add) {
                                $new_date = $date_obj->modify("+$years_to_add years")->format('Y-m-d');
                                update_field('anniversario_data', $new_date, $post_id);
                            } else {
                                $errors[] = "Missing years to add for anniversario {$post_id}";
                            }
                        }
                    }

                    $updated++;
                    $processed++;
                } catch (Exception $e) {
                    $errors[] = "Error processing {$current_post_type} {$post_id}: " . $e->getMessage();
                }

                wp_cache_flush();
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            $message = sprintf(
                'Processed %d posts in this batch. Total processed: %d. %s',
                $updated,
                $processed,
                !empty($errors) ? "\nErrors encountered: " . implode("\n", array_slice($errors, -5)) : ''
            );

            wp_send_json_success(array(
                'continue' => $continue,
                'offset' => $offset + $batch_size,
                'processed' => $processed,
                'message' => $message,
                'bulk_type' => $current_post_type
            ));
        }

        public function reset_migration_progress()
        {
            check_ajax_referer('migration_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $file_name = sanitize_text_field($_POST['file_name'] ?? '');
            
            if (empty($file_name) || !in_array($file_name, $this->allowed_files)) {
                wp_send_json_error('Invalid file name');
                return;
            }

            $this->log("Reset manuale progresso richiesto per: $file_name");

            // Reset del progresso per il file specificato
            if (!file_exists($this->progress_file)) {
                $progress = [];
            } else {
                $progress = json_decode(file_get_contents($this->progress_file), true) ?: [];
            }

            $progress[$file_name] = [
                'processed' => 0,
                'total' => 0,
                'percentage' => 0,
                'status' => 'not_started'
            ];

            if (file_put_contents($this->progress_file, json_encode($progress)) === false) {
                $this->log("ERRORE: Impossibile resettare il progresso per $file_name");
                wp_send_json_error("Failed to reset progress for $file_name");
                return;
            }

            // Cancella anche eventuali cron jobs per questo file
            if (isset($this->cron_hooks[$file_name])) {
                $hook = $this->cron_hooks[$file_name];
                wp_unschedule_hook($hook);
                $this->log("Cron job cancellato per $file_name");
            }

            $this->log("Progresso resettato con successo per $file_name");
            wp_send_json_success("Progress reset successfully for $file_name");
        }

        public function reset_image_queue_progress()
        {
            check_ajax_referer('migration_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $this->log("Reset image queue progress richiesto");
            
            $queue_file = $this->upload_dir . 'image_download_queue.csv';
            $force = isset($_POST['force']) && $_POST['force'];
            
            // Check se il file queue esiste (skip se force)
            if (!$force && !file_exists($queue_file)) {
                wp_send_json_error('No image queue file found');
                return;
            }

            // Se force, cancella completamente il file queue e ricostruisci
            if ($force) {
                $this->log("Force restart richiesto - cancellazione completa del file queue");
                if (file_exists($queue_file)) {
                    unlink($queue_file);
                    $this->log("File queue cancellato");
                }
                
                // Cancella anche log di errore se esiste
                $error_log = $this->upload_dir . 'image_errors.log';
                if (file_exists($error_log)) {
                    unlink($error_log);
                    $this->log("Log errori cancellato");
                }
            }

            // Reset del progresso per il file queue
            if (!file_exists($this->progress_file)) {
                $progress = [];
            } else {
                $progress = json_decode(file_get_contents($this->progress_file), true) ?: [];
            }

            // Reset progress per image_download_queue.csv
            $progress['image_download_queue.csv'] = [
                'processed' => 0,
                'total' => 0,
                'percentage' => 0,
                'status' => 'not_started'
            ];

            if (file_put_contents($this->progress_file, json_encode($progress)) === false) {
                $this->log("ERRORE: Impossibile resettare il progresso image queue");
                wp_send_json_error("Failed to reset image queue progress");
                return;
            }

            // Force clear all caches
            wp_cache_flush();
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Cancella TUTTI i possibili cron jobs per le immagini
            $image_hooks = [
                'dokan_mods_process_image_queue',
                'dokan_mods_process_image_ricorrenze_queue', 
                'dokan_mods_process_image_ringraziamenti_queue'
            ];
            
            foreach ($image_hooks as $hook) {
                // Cancella tutte le istanze programmate
                while (wp_next_scheduled($hook)) {
                    wp_unschedule_event(wp_next_scheduled($hook), $hook);
                }
                wp_unschedule_hook($hook);
            }
            $this->log("Tutti i cron jobs delle immagini cancellati");
            
            // Reset retry counts nel CSV se il file esiste
            if (file_exists($queue_file)) {
                $this->reset_retry_counts_in_queue($queue_file);
            }

            // Riprogramma il cron job per riavviare il download solo se il file esiste
            if (file_exists($queue_file)) {
                // Programma per esecuzione immediata (tra 60 secondi per stabilità)
                wp_schedule_single_event(time() + 60, 'dokan_mods_process_image_queue');
                $this->log("Cron job image queue riprogrammato per riavvio in 60 secondi");
            } else {
                $this->log("Nessun file queue trovato - cron job non riprogrammato");
            }

            $message = "Image queue progress reset successfully";
            if ($force) {
                $message .= " (force mode - queue file deleted)";
            }
            
            $this->log($message);
            wp_send_json_success($message);
        }

        public function check_image_queue_status()
        {
            check_ajax_referer('migration_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $queue_file = $this->upload_dir . 'image_download_queue.csv';
            
            if (!file_exists($queue_file)) {
                wp_send_json_success([
                    'queue_exists' => false,
                    'total_images' => 0,
                    'processed' => 0,
                    'status' => 'no_queue'
                ]);
                return;
            }

            // Conta righe totali nel CSV
            $total_lines = 0;
            if (($handle = fopen($queue_file, 'r')) !== FALSE) {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $total_lines++;
                }
                fclose($handle);
            }

            // Ottieni progress
            $progress = [];
            if (file_exists($this->progress_file)) {
                $progress = json_decode(file_get_contents($this->progress_file), true) ?: [];
            }

            $queue_progress = $progress['image_download_queue.csv'] ?? [
                'processed' => 0,
                'total' => $total_lines,
                'percentage' => 0,
                'status' => 'not_started'
            ];

            // Check se il cron è programmato
            $cron_scheduled = wp_next_scheduled('dokan_mods_process_image_queue') !== false;

            wp_send_json_success([
                'queue_exists' => true,
                'total_images' => $total_lines,
                'processed' => $queue_progress['processed'],
                'percentage' => $queue_progress['percentage'],
                'status' => $queue_progress['status'],
                'cron_scheduled' => $cron_scheduled
            ]);
        }

        private function reset_retry_counts_in_queue($queue_file)
        {
            $temp_file = $queue_file . '.tmp';
            
            if (($input_handle = fopen($queue_file, 'r')) !== FALSE && 
                ($output_handle = fopen($temp_file, 'w')) !== FALSE) {
                
                while (($data = fgetcsv($input_handle)) !== FALSE) {
                    // Reset retry count (ultima colonna) a 0
                    if (count($data) >= 5) {
                        $data[4] = '0'; // Reset retry count
                    }
                    fputcsv($output_handle, $data);
                }
                
                fclose($input_handle);
                fclose($output_handle);
                
                // Sostituisci il file originale
                if (rename($temp_file, $queue_file)) {
                    $this->log("Retry counts resettati nel queue file");
                } else {
                    $this->log("Errore nel reset retry counts");
                    if (file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                }
            }
        }

        public function test_image_download()
        {
            check_ajax_referer('migration_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $this->log("Test image download richiesto");
            
            // Prendi una URL di test dal CSV se esiste
            $queue_file = $this->upload_dir . 'image_download_queue.csv';
            $test_url = 'https://necrologi.sciame.it/necrologi/22102013/0d30d7bafd974e2fa5f40270caaeed4b_Crop.jpg'; // Default test URL
            
            if (file_exists($queue_file)) {
                if (($handle = fopen($queue_file, 'r')) !== FALSE) {
                    $data = fgetcsv($handle);
                    if ($data && isset($data[1])) {
                        $test_url = $data[1];
                    }
                    fclose($handle);
                }
            }

            $start_time = microtime(true);
            
            // Test con wp_remote_get (nuovo metodo)
            $args = [
                'timeout' => 15,
                'redirection' => 5,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ];
            
            $response = wp_remote_get($test_url, $args);
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            
            if (!is_wp_error($response)) {
                $http_code = wp_remote_retrieve_response_code($response);
                $content_length = strlen(wp_remote_retrieve_body($response));
                
                if ($http_code === 200) {
                    wp_send_json_success([
                        'message' => "✅ Test successful! Downloaded {$content_length} bytes in {$duration}s",
                        'url' => $test_url,
                        'duration' => $duration,
                        'size' => $content_length,
                        'method' => 'wp_remote_get'
                    ]);
                } else {
                    wp_send_json_error("HTTP error: $http_code");
                }
            } else {
                wp_send_json_error("Download failed: " . $response->get_error_message());
            }
        }

        public function bulk_cleanup_migration()
        {
            check_ajax_referer('migration_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $step = sanitize_text_field($_POST['step'] ?? 'count');
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $cutoff_date = sanitize_text_field($_POST['cutoff_date'] ?? '');
            $selected_items = $_POST['selected_items'] ?? [];
            
            // Batch sizes ottimizzati per velocità
            $batch_sizes = [
                'ringraziamenti' => 500,
                'anniversari' => 500, 
                'trigesimi' => 500,
                'manifesti' => 500,
                'images_necrologi' => 200,
                'images_trigesimi' => 200,
                'images_anniversari' => 200,
                'images_general' => 200,
                'necrologi' => 100  // Più lento per via degli attachment
            ];
            
            $batch_size = $batch_sizes[$step] ?? 100;
            
            $this->log("Bulk cleanup step: $step, offset: $offset, batch_size: $batch_size");

            try {
                // Se non è 'count', verifica che lo step sia selezionato dall'utente
                if ($step !== 'count' && !empty($selected_items) && !in_array($step, $selected_items)) {
                    // Step non selezionato, skip e segnala come completato
                    wp_send_json_success(['processed' => 0, 'total' => 0, 'completed' => true, 'skipped' => true]);
                    return;
                }
                
                switch($step) {
                    case 'count':
                        wp_send_json_success($this->count_all_migration_items($cutoff_date));
                        break;
                        
                    case 'ringraziamenti':
                        wp_send_json_success($this->delete_ringraziamenti_optimized($offset, $batch_size));
                        break;
                        
                    case 'anniversari':
                        wp_send_json_success($this->delete_anniversari_optimized($offset, $batch_size));
                        break;
                        
                    case 'trigesimi':
                        wp_send_json_success($this->delete_trigesimi_optimized($offset, $batch_size));
                        break;
                        
                    case 'manifesti':
                        wp_send_json_success($this->delete_manifesti_optimized($offset, $batch_size));
                        break;
                        
                    case 'images_necrologi':
                        wp_send_json_success($this->delete_images_by_type('annuncio-di-morte', $offset, $batch_size));
                        break;
                        
                    case 'images_trigesimi':
                        wp_send_json_success($this->delete_images_by_type('trigesimo', $offset, $batch_size));
                        break;
                        
                    case 'images_anniversari':
                        wp_send_json_success($this->delete_images_by_type('anniversario', $offset, $batch_size));
                        break;
                        
                    case 'images_general':
                        wp_send_json_success($this->delete_general_migration_images($offset, $batch_size, $cutoff_date));
                        break;
                        
                    case 'necrologi':
                        wp_send_json_success($this->delete_necrologi_optimized($offset, $batch_size));
                        break;
                        
                    default:
                        wp_send_json_error('Invalid step');
                }
            } catch (Exception $e) {
                $this->log("Errore durante bulk cleanup: " . $e->getMessage());
                wp_send_json_error("Error during cleanup: " . $e->getMessage());
            }
        }

        private function count_all_migration_items($cutoff_date = '')
        {
            global $wpdb;
            
            // Conteggi con logging dettagliato per debug
            $counts = [];
            
            $counts['ringraziamenti'] = $this->count_posts_by_type_optimized('ringraziamento');
            $this->log("DEBUG Conteggio ringraziamenti: " . $counts['ringraziamenti']);
            
            $counts['anniversari'] = $this->count_posts_by_type_optimized('anniversario');
            $this->log("DEBUG Conteggio anniversari: " . $counts['anniversari']);
            
            $counts['trigesimi'] = $this->count_posts_by_type_optimized('trigesimo');
            $this->log("DEBUG Conteggio trigesimi: " . $counts['trigesimi']);
            
            $counts['manifesti'] = $this->count_posts_by_type_optimized('manifesto');
            $this->log("DEBUG Conteggio manifesti: " . $counts['manifesti']);
            
            $counts['necrologi'] = $this->count_posts_by_type_optimized('annuncio-di-morte');
            $this->log("DEBUG Conteggio necrologi: " . $counts['necrologi']);
            
            $counts['images_necrologi'] = $this->count_images_by_post_type('annuncio-di-morte');
            $this->log("DEBUG Conteggio images_necrologi: " . $counts['images_necrologi']);
            
            $counts['images_trigesimi'] = $this->count_images_by_post_type('trigesimo');
            $this->log("DEBUG Conteggio images_trigesimi: " . $counts['images_trigesimi']);
            
            $counts['images_anniversari'] = $this->count_images_by_post_type('anniversario');
            $this->log("DEBUG Conteggio images_anniversari: " . $counts['images_anniversari']);
            
            $counts['images_general'] = $this->count_general_migration_images($cutoff_date);
            $this->log("DEBUG Conteggio images_general: " . $counts['images_general']);
            
            $total = array_sum($counts);
            
            $this->log("Conteggio granulare items migrazione: " . json_encode($counts) . " - Totale: $total");
            
            return [
                'counts' => $counts,
                'total' => $total,
                'steps' => [
                    'ringraziamenti' => $counts['ringraziamenti'],
                    'anniversari' => $counts['anniversari'], 
                    'trigesimi' => $counts['trigesimi'],
                    'manifesti' => $counts['manifesti'],
                    'images_necrologi' => $counts['images_necrologi'],
                    'images_trigesimi' => $counts['images_trigesimi'],
                    'images_anniversari' => $counts['images_anniversari'],
                    'images_general' => $counts['images_general'],
                    'necrologi' => $counts['necrologi']
                ]
            ];
        }

        private function count_posts_by_type_optimized($post_type)
        {
            global $wpdb;
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
                $post_type
            ));
            
            return intval($count);
        }

        private function count_migration_attachments($cutoff_date = '')
        {
            global $wpdb;
            
            if (!empty($cutoff_date)) {
                // Se c'è una data, conta TUTTI gli attachment caricati dopo quella data
                $count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT ID) 
                    FROM {$wpdb->posts}
                    WHERE post_type = 'attachment' 
                    AND post_date > %s
                ", $cutoff_date));
                
                $this->log("Conteggio immagini con data cutoff $cutoff_date: $count");
            } else {
                // Altrimenti conta solo quelli collegati alla migrazione
                // Query 1: Attachment con post_parent = annuncio-di-morte
                $parent_count = $wpdb->get_var("
                    SELECT COUNT(DISTINCT p.ID) 
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
                    WHERE p.post_type = 'attachment' 
                    AND parent.post_type = 'annuncio-di-morte'
                ");
                
                // Query 2: Attachment collegati tramite ACF fields
                $acf_count = $wpdb->get_var("
                    SELECT COUNT(DISTINCT meta_value)
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = 'annuncio-di-morte'
                    AND pm.meta_key IN ('fotografia', 'immagine_annuncio_di_morte')
                    AND pm.meta_value != ''
                    AND pm.meta_value IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM {$wpdb->posts} 
                        WHERE ID = pm.meta_value 
                        AND post_type = 'attachment'
                    )
                ");
                
                $count = intval($parent_count) + intval($acf_count);
                $this->log("Conteggio immagini migrazione: $parent_count (parent) + $acf_count (ACF) = $count totali");
            }
            
            return intval($count);
        }

        private function count_images_by_post_type($post_type)
        {
            global $wpdb;
            
            $this->log("DEBUG: Conteggio immagini per post_type: $post_type");
            
            switch($post_type) {
                case 'annuncio-di-morte':
                    // Immagini annunci di morte - usando query separate per evitare problemi con UNION
                    
                    // Query 1: Immagini con parent relationship
                    $parent_query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT p.ID)
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
                        WHERE p.post_type = 'attachment' 
                        AND p.post_status = 'inherit'
                        AND parent.post_type = %s
                    ", $post_type);
                    $this->log("DEBUG: Query parent - $parent_query");
                    $parent_count = $wpdb->get_var($parent_query);
                    if ($wpdb->last_error) {
                        $this->log("ERRORE SQL parent: " . $wpdb->last_error);
                    }
                    
                    // Query 2: Immagini ACF 'fotografia'
                    $fotografia_query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        INNER JOIN {$wpdb->posts} att ON CAST(pm.meta_value AS UNSIGNED) = att.ID
                        WHERE pm.meta_key = 'fotografia'
                        AND p.post_type = %s
                        AND pm.meta_value REGEXP '^[0-9]+$'
                        AND pm.meta_value > 0
                        AND att.post_type = 'attachment'
                        AND att.post_status = 'inherit'
                    ", $post_type);
                    $this->log("DEBUG: Query fotografia - $fotografia_query");
                    $fotografia_count = $wpdb->get_var($fotografia_query);
                    if ($wpdb->last_error) {
                        $this->log("ERRORE SQL fotografia: " . $wpdb->last_error);
                    }
                    
                    // Query 3: Immagini ACF 'immagine_annuncio_di_morte'  
                    $immagine_query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        INNER JOIN {$wpdb->posts} att ON CAST(pm.meta_value AS UNSIGNED) = att.ID
                        WHERE pm.meta_key = 'immagine_annuncio_di_morte'
                        AND p.post_type = %s
                        AND pm.meta_value REGEXP '^[0-9]+$'
                        AND pm.meta_value > 0
                        AND att.post_type = 'attachment'
                        AND att.post_status = 'inherit'
                    ", $post_type);
                    $this->log("DEBUG: Query immagine - $immagine_query");
                    $immagine_count = $wpdb->get_var($immagine_query);
                    if ($wpdb->last_error) {
                        $this->log("ERRORE SQL immagine: " . $wpdb->last_error);
                    }
                    
                    // Somma (nota: potrebbero esserci duplicati, ma per ora accettiamo l'approssimazione)
                    $count = intval($parent_count) + intval($fotografia_count) + intval($immagine_count);
                    
                    $this->log("DEBUG annuncio-di-morte images: parent=$parent_count, fotografia=$fotografia_count, immagine=$immagine_count, totale=$count");
                    break;
                    
                case 'trigesimo':
                    // Immagini trigesimi - ACF 'immagine_annuncio_trigesimo'
                    $query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        INNER JOIN {$wpdb->posts} att ON CAST(pm.meta_value AS UNSIGNED) = att.ID
                        WHERE pm.meta_key = 'immagine_annuncio_trigesimo'
                        AND p.post_type = %s
                        AND pm.meta_value REGEXP '^[0-9]+$'
                        AND pm.meta_value > 0
                        AND att.post_type = 'attachment'
                        AND att.post_status = 'inherit'
                    ", $post_type);
                    $this->log("DEBUG: Query trigesimo - $query");
                    $count = $wpdb->get_var($query);
                    if ($wpdb->last_error) {
                        $this->log("ERRORE SQL trigesimo: " . $wpdb->last_error);
                    }
                    $this->log("DEBUG trigesimo images: count=$count");
                    break;
                    
                case 'anniversario':
                    // Immagini anniversari - ACF 'immagine_annuncio_anniversario'
                    $query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        INNER JOIN {$wpdb->posts} att ON CAST(pm.meta_value AS UNSIGNED) = att.ID
                        WHERE pm.meta_key = 'immagine_annuncio_anniversario'
                        AND p.post_type = %s
                        AND pm.meta_value REGEXP '^[0-9]+$'
                        AND pm.meta_value > 0
                        AND att.post_type = 'attachment'
                        AND att.post_status = 'inherit'
                    ", $post_type);
                    $this->log("DEBUG: Query anniversario - $query");
                    $count = $wpdb->get_var($query);
                    if ($wpdb->last_error) {
                        $this->log("ERRORE SQL anniversario: " . $wpdb->last_error);
                    }
                    $this->log("DEBUG anniversario images: count=$count");
                    break;
                    
                default:
                    $count = 0;
                    break;
            }
            
            return intval($count);
        }

        private function count_general_migration_images($cutoff_date = '')
        {
            global $wpdb;
            
            $this->log("DEBUG: Conteggio immagini generali, cutoff_date: '$cutoff_date'");
            
            if (!empty($cutoff_date)) {
                // Con cutoff date, conta tutti gli attachment dopo quella data
                $query = $wpdb->prepare("
                    SELECT COUNT(DISTINCT ID) 
                    FROM {$wpdb->posts}
                    WHERE post_type = 'attachment' 
                    AND post_status = 'inherit'
                    AND post_date > %s
                ", $cutoff_date);
                $this->log("DEBUG: Query images general (con cutoff) - $query");
                $count = $wpdb->get_var($query);
                if ($wpdb->last_error) {
                    $this->log("ERRORE SQL general images (cutoff): " . $wpdb->last_error);
                }
            } else {
                // Senza cutoff date, usa conteggio semplificato
                $query = "
                    SELECT COUNT(DISTINCT p.ID)
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
                    WHERE p.post_type = 'attachment'
                    AND p.post_status = 'inherit'
                    AND (
                        parent.post_type IN ('annuncio-di-morte', 'trigesimo', 'anniversario', 'ringraziamento', 'manifesto')
                        OR p.post_parent = 0
                    )
                ";
                $this->log("DEBUG: Query images general (senza cutoff) - $query");
                $count = $wpdb->get_var($query);
                if ($wpdb->last_error) {
                    $this->log("ERRORE SQL general images: " . $wpdb->last_error);
                }
            }
            
            $this->log("DEBUG general images: count=$count");
            return intval($count);
        }

        private function delete_ringraziamenti_optimized($offset, $batch_size)
        {
            return $this->delete_posts_batch_optimized('ringraziamento', $offset, $batch_size);
        }

        private function delete_anniversari_optimized($offset, $batch_size)
        {
            return $this->delete_posts_batch_optimized('anniversario', $offset, $batch_size);
        }

        private function delete_trigesimi_optimized($offset, $batch_size)
        {
            return $this->delete_posts_batch_optimized('trigesimo', $offset, $batch_size);
        }

        private function delete_manifesti_optimized($offset, $batch_size)
        {
            return $this->delete_posts_batch_optimized('manifesto', $offset, $batch_size);
        }

        private function delete_posts_batch_optimized($post_type, $offset, $batch_size)
        {
            global $wpdb;
            
            // Query SQL diretta per massima velocità - sempre offset 0 per "until empty"
            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = %s
                ORDER BY ID ASC
                LIMIT %d
            ", $post_type, $batch_size));

            if (empty($post_ids)) {
                return [
                    'processed' => 0,
                    'completed' => true,
                    'next_offset' => 0,
                    'messages' => ["Nessun $post_type da eliminare"],
                    'remaining' => 0
                ];
            }

            $deleted = 0;
            $messages = [];
            
            // Preparazione placeholders per query bulk
            $ids_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
            
            // Eliminazione bulk postmeta
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->postmeta} 
                WHERE post_id IN ($ids_placeholders)
            ", $post_ids));
            
            // Eliminazione bulk posts
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->posts} 
                WHERE ID IN ($ids_placeholders)
            ", $post_ids));

            // Memory cleanup
            wp_cache_flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Conta quanti rimangono
            $remaining_count = $this->count_posts_by_type_optimized($post_type);
            $has_more = $remaining_count > 0;
            
            $batch_message = "Eliminati $deleted $post_type (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0,
                'messages' => $messages,
                'remaining' => $remaining_count
            ];
        }

        private function delete_images_by_type($post_type, $offset, $batch_size)
        {
            global $wpdb;
            
            $attachment_ids = [];
            
            switch($post_type) {
                case 'annuncio-di-morte':
                    // Trova immagini annunci di morte
                    $attachment_ids = $wpdb->get_col($wpdb->prepare("
                        SELECT DISTINCT attachment_id FROM (
                            SELECT p.ID as attachment_id
                            FROM {$wpdb->posts} p
                            INNER JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
                            WHERE p.post_type = 'attachment' 
                            AND p.post_status = 'inherit'
                            AND parent.post_type = %s
                            
                            UNION
                            
                            SELECT CAST(pm.meta_value AS UNSIGNED) as attachment_id
                            FROM {$wpdb->postmeta} pm
                            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                            INNER JOIN {$wpdb->posts} att ON CAST(pm.meta_value AS UNSIGNED) = att.ID
                            WHERE pm.meta_key IN ('fotografia', 'immagine_annuncio_di_morte')
                            AND p.post_type = %s
                            AND pm.meta_value REGEXP '^[0-9]+$'
                            AND pm.meta_value > 0
                            AND att.post_type = 'attachment'
                            AND att.post_status = 'inherit'
                        ) as combined_images
                        WHERE attachment_id > 0
                        ORDER BY attachment_id
                        LIMIT %d OFFSET %d
                    ", $post_type, $post_type, $batch_size, $offset));
                    break;
                    
                case 'trigesimo':
                    // Trova immagini trigesimi
                    $attachment_ids = $wpdb->get_col($wpdb->prepare("
                        SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) as attachment_id
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        INNER JOIN {$wpdb->posts} att ON CAST(pm.meta_value AS UNSIGNED) = att.ID
                        WHERE pm.meta_key = 'immagine_annuncio_trigesimo'
                        AND p.post_type = %s
                        AND pm.meta_value REGEXP '^[0-9]+$'
                        AND pm.meta_value > 0
                        AND att.post_type = 'attachment'
                        AND att.post_status = 'inherit'
                        ORDER BY attachment_id
                        LIMIT %d OFFSET %d
                    ", $post_type, $batch_size, $offset));
                    break;
                    
                case 'anniversario':
                    // Trova immagini anniversari
                    $attachment_ids = $wpdb->get_col($wpdb->prepare("
                        SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) as attachment_id
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        INNER JOIN {$wpdb->posts} att ON CAST(pm.meta_value AS UNSIGNED) = att.ID
                        WHERE pm.meta_key = 'immagine_annuncio_anniversario'
                        AND p.post_type = %s
                        AND pm.meta_value REGEXP '^[0-9]+$'
                        AND pm.meta_value > 0
                        AND att.post_type = 'attachment'
                        AND att.post_status = 'inherit'
                        ORDER BY attachment_id
                        LIMIT %d OFFSET %d
                    ", $post_type, $batch_size, $offset));
                    break;
                    
                default:
                    $attachment_ids = [];
                    break;
            }
            
            if (empty($attachment_ids)) {
                return [
                    'processed' => 0,
                    'total' => 0,
                    'completed' => true,
                    'messages' => []
                ];
            }
            
            // Clear cache before processing
            wp_cache_flush();
            
            $messages = [];
            $deleted_count = 0;
            
            foreach ($attachment_ids as $attach_id) {
                // Ensure attachment ID is an integer
                $attach_id = intval($attach_id);
                if ($attach_id <= 0) {
                    $messages[] = "Errore: ID attachment non valido: $attach_id";
                    continue;
                }
                
                // Clear specific post cache
                clean_post_cache($attach_id);
                
                // Verifica se l'attachment esiste prima di tentare l'eliminazione
                $attachment_post = get_post($attach_id);
                
                // Enhanced debugging
                if (!$attachment_post) {
                    // Try direct database query as fallback
                    $direct_check = $wpdb->get_row($wpdb->prepare(
                        "SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE ID = %d",
                        $attach_id
                    ));
                    
                    if ($direct_check) {
                        $messages[] = "Errore eliminazione immagine $post_type ID: $attach_id - Post trovato nel DB ma get_post() fallito. Tipo: {$direct_check->post_type}, Status: {$direct_check->post_status}";
                    } else {
                        $messages[] = "Errore eliminazione immagine $post_type ID: $attach_id - Attachment non trovato nel database";
                    }
                    continue;
                }
                
                if ($attachment_post->post_type !== 'attachment') {
                    $messages[] = "Errore eliminazione immagine $post_type ID: $attach_id - Tipo post errato: {$attachment_post->post_type}";
                    continue;
                }
                
                // Check post status
                if ($attachment_post->post_status === 'trash') {
                    $messages[] = "Avviso: Immagine $post_type ID: $attach_id già nel cestino - Skip";
                    continue;
                }
                
                if (wp_delete_attachment($attach_id, true)) {
                    $deleted_count++;
                    $messages[] = "Eliminata immagine $post_type ID: $attach_id";
                } else {
                    $file_path = get_attached_file($attach_id);
                    $file_exists = $file_path && file_exists($file_path) ? 'presente' : 'assente';
                    $messages[] = "Errore eliminazione immagine $post_type ID: $attach_id - File: $file_exists, Status: {$attachment_post->post_status}";
                }
            }
            
            $total_count = $this->count_images_by_post_type($post_type);
            $remaining = max(0, $total_count - ($offset + $deleted_count));
            
            return [
                'processed' => $deleted_count,
                'total' => $total_count,
                'completed' => $remaining == 0,
                'next_offset' => $remaining > 0 ? $offset + $deleted_count : 0,
                'messages' => $messages,
                'remaining' => $remaining
            ];
        }

        private function delete_general_migration_images($offset, $batch_size, $cutoff_date = '')
        {
            global $wpdb;
            
            $attachment_ids = [];
            
            if (!empty($cutoff_date)) {
                // Con cutoff date, trova tutti gli attachment dopo quella data
                $attachment_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT ID 
                    FROM {$wpdb->posts}
                    WHERE post_type = 'attachment' 
                    AND post_status = 'inherit'
                    AND post_date > %s
                    ORDER BY ID
                    LIMIT %d OFFSET %d
                ", $cutoff_date, $batch_size, $offset));
            } else {
                // Trova immagini generiche della migrazione (non già categorizzate)
                $attachment_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT DISTINCT p.ID
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
                    LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = CAST(pm1.meta_value AS UNSIGNED) AND pm1.meta_key IN ('fotografia', 'immagine_annuncio_di_morte') AND pm1.meta_value REGEXP '^[0-9]+$'
                    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = CAST(pm2.meta_value AS UNSIGNED) AND pm2.meta_key IN ('immagine_annuncio_trigesimo', 'immagine_annuncio_anniversario') AND pm2.meta_value REGEXP '^[0-9]+$'
                    WHERE p.post_type = 'attachment'
                    AND p.post_status = 'inherit'
                    AND (
                        (parent.post_type IS NOT NULL AND parent.post_type IN ('annuncio-di-morte', 'trigesimo', 'anniversario', 'ringraziamento', 'manifesto'))
                        OR pm1.meta_value IS NOT NULL
                        OR pm2.meta_value IS NOT NULL
                    )
                    AND pm1.meta_value IS NULL  -- Esclude quelle già contate per necrologi
                    AND pm2.meta_value IS NULL  -- Esclude quelle già contate per trigesimi/anniversari
                    ORDER BY p.ID
                    LIMIT %d OFFSET %d
                ", $batch_size, $offset));
            }
            
            if (empty($attachment_ids)) {
                return [
                    'processed' => 0,
                    'total' => 0,
                    'completed' => true,
                    'messages' => []
                ];
            }
            
            // Clear cache before processing
            wp_cache_flush();
            
            $messages = [];
            $deleted_count = 0;
            
            foreach ($attachment_ids as $attach_id) {
                // Ensure attachment ID is an integer
                $attach_id = intval($attach_id);
                if ($attach_id <= 0) {
                    $messages[] = "Errore: ID attachment non valido: $attach_id";
                    continue;
                }
                
                // Clear specific post cache
                clean_post_cache($attach_id);
                
                // Verifica se l'attachment esiste prima di tentare l'eliminazione
                $attachment_post = get_post($attach_id);
                
                // Enhanced debugging
                if (!$attachment_post) {
                    // Try direct database query as fallback
                    $direct_check = $wpdb->get_row($wpdb->prepare(
                        "SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE ID = %d",
                        $attach_id
                    ));
                    
                    if ($direct_check) {
                        $messages[] = "Errore eliminazione immagine generale ID: $attach_id - Post trovato nel DB ma get_post() fallito. Tipo: {$direct_check->post_type}, Status: {$direct_check->post_status}";
                    } else {
                        $messages[] = "Errore eliminazione immagine generale ID: $attach_id - Attachment non trovato nel database";
                    }
                    continue;
                }
                
                if ($attachment_post->post_type !== 'attachment') {
                    $messages[] = "Errore eliminazione immagine generale ID: $attach_id - Tipo post errato: {$attachment_post->post_type}";
                    continue;
                }
                
                // Check post status
                if ($attachment_post->post_status === 'trash') {
                    $messages[] = "Avviso: Immagine generale ID: $attach_id già nel cestino - Skip";
                    continue;
                }
                
                if (wp_delete_attachment($attach_id, true)) {
                    $deleted_count++;
                    $messages[] = "Eliminata immagine generale ID: $attach_id";
                } else {
                    $file_path = get_attached_file($attach_id);
                    $file_exists = $file_path && file_exists($file_path) ? 'presente' : 'assente';
                    $messages[] = "Errore eliminazione immagine generale ID: $attach_id - File: $file_exists, Status: {$attachment_post->post_status}";
                }
            }
            
            $total_count = $this->count_general_migration_images($cutoff_date);
            $remaining = max(0, $total_count - ($offset + $deleted_count));
            
            return [
                'processed' => $deleted_count,
                'total' => $total_count,
                'completed' => $remaining == 0,
                'next_offset' => $remaining > 0 ? $offset + $deleted_count : 0,
                'messages' => $messages,
                'remaining' => $remaining
            ];
        }

        private function delete_all_migration_images($offset, $batch_size, $cutoff_date = '')
        {
            global $wpdb;
            
            $attachment_data = [];
            
            if (!empty($cutoff_date)) {
                // Se c'è una data, prendi TUTTI gli attachment caricati dopo quella data
                $attachment_data = $wpdb->get_results($wpdb->prepare("
                    SELECT p.ID, pm.meta_value as file_path
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                    WHERE p.post_type = 'attachment' 
                    AND p.post_date > %s
                    LIMIT %d
                ", $cutoff_date, $batch_size), ARRAY_A);
                
                $this->log("Eliminazione immagini con cutoff date $cutoff_date");
            } else {
                // Altrimenti usa query complessa per trovare solo quelli della migrazione
                // Combina 3 query con UNION per trovare tutti gli attachment
                $query = "
                    SELECT DISTINCT p.ID, pm_file.meta_value as file_path
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
                    WHERE p.ID IN (
                        -- Query 1: Attachment con post_parent = annuncio-di-morte
                        SELECT DISTINCT p1.ID
                        FROM {$wpdb->posts} p1
                        INNER JOIN {$wpdb->posts} parent ON p1.post_parent = parent.ID
                        WHERE p1.post_type = 'attachment' 
                        AND parent.post_type = 'annuncio-di-morte'
                        
                        UNION
                        
                        -- Query 2: Attachment collegati tramite campo 'fotografia'
                        SELECT DISTINCT CAST(pm1.meta_value AS UNSIGNED) as ID
                        FROM {$wpdb->postmeta} pm1
                        INNER JOIN {$wpdb->posts} p2 ON pm1.post_id = p2.ID
                        WHERE p2.post_type = 'annuncio-di-morte'
                        AND pm1.meta_key = 'fotografia'
                        AND pm1.meta_value != ''
                        AND pm1.meta_value REGEXP '^[0-9]+$'
                        
                        UNION
                        
                        -- Query 3: Attachment collegati tramite campo 'immagine_annuncio_di_morte'
                        SELECT DISTINCT CAST(pm2.meta_value AS UNSIGNED) as ID
                        FROM {$wpdb->postmeta} pm2
                        INNER JOIN {$wpdb->posts} p3 ON pm2.post_id = p3.ID
                        WHERE p3.post_type = 'annuncio-di-morte'
                        AND pm2.meta_key = 'immagine_annuncio_di_morte'
                        AND pm2.meta_value != ''
                        AND pm2.meta_value REGEXP '^[0-9]+$'
                    )
                    AND p.post_type = 'attachment'
                    LIMIT %d
                ";
                
                $attachment_data = $wpdb->get_results($wpdb->prepare($query, $batch_size), ARRAY_A);
                $this->log("Eliminazione immagini della migrazione (parent + ACF)");
            }

            if (empty($attachment_data)) {
                return [
                    'processed' => 0,
                    'completed' => true,
                    'next_offset' => 0,
                    'messages' => ['Nessuna immagine da eliminare'],
                    'remaining' => 0
                ];
            }

            $deleted = 0;
            $messages = [];
            $attachment_ids = [];
            
            // Elimina file fisici e raccogli IDs
            $upload_dir = wp_upload_dir();
            foreach ($attachment_data as $attachment) {
                $attachment_id = $attachment['ID'];
                $attachment_ids[] = $attachment_id;
                
                // Usa wp_delete_attachment per eliminare file e thumbnails
                $force_delete = true;
                wp_delete_attachment($attachment_id, $force_delete);
                $deleted++;
            }
            
            // Nota: wp_delete_attachment già gestisce l'eliminazione di post e postmeta

            // Memory cleanup
            wp_cache_flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Conta quante immagini rimangono
            $remaining_count = $this->count_migration_attachments($cutoff_date);
            $has_more = $remaining_count > 0;
            
            // Se non ci sono più immagini, pulisci anche il file della coda
            if (!$has_more && empty($cutoff_date)) {
                $this->cleanup_image_queue_files();
            }
            
            $batch_message = "Eliminate $deleted immagini (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0,
                'messages' => $messages,
                'remaining' => $remaining_count
            ];
        }

        private function cleanup_image_queue_files()
        {
            try {
                // Elimina il file della coda immagini
                $queue_file = $this->upload_dir . 'image_download_queue.csv';
                if (file_exists($queue_file)) {
                    unlink($queue_file);
                    $this->log("File coda immagini eliminato: $queue_file");
                }
                
                // Reset del progresso per la coda immagini nel file di progresso
                if (file_exists($this->progress_file)) {
                    $progress = json_decode(file_get_contents($this->progress_file), true);
                    if (isset($progress['image_download_queue.csv'])) {
                        unset($progress['image_download_queue.csv']);
                        file_put_contents($this->progress_file, json_encode($progress));
                        $this->log("Progresso coda immagini resettato");
                    }
                }
                
                // Cancella anche eventuali scheduled cron per le immagini
                wp_clear_scheduled_hook('dokan_mods_process_image_queue');
                $this->log("Cron job processamento immagini cancellato");
                
            } catch (Exception $e) {
                $this->log("Errore nella pulizia dei file della coda immagini: " . $e->getMessage());
            }
        }
        
        private function delete_necrologi_optimized($offset, $batch_size)
        {
            global $wpdb;
            
            // Query per necrologi - sempre offset 0 per logica "until empty"
            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'annuncio-di-morte'
                ORDER BY ID ASC
                LIMIT %d
            ", $batch_size));

            if (empty($post_ids)) {
                return [
                    'processed' => 0,
                    'completed' => true,
                    'next_offset' => 0,
                    'messages' => ['Nessun necrologio da eliminare'],
                    'remaining' => 0
                ];
            }

            $deleted = 0;
            $messages = [];
            
            // Preparazione placeholders
            $ids_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
            
            // Elimina postmeta
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->postmeta} 
                WHERE post_id IN ($ids_placeholders)
            ", $post_ids));
            
            // Elimina i necrologi
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->posts} 
                WHERE ID IN ($ids_placeholders)
            ", $post_ids));

            // Memory cleanup
            wp_cache_flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Conta quanti necrologi rimangono
            $remaining_count = $this->count_posts_by_type_optimized('annuncio-di-morte');
            $has_more = $remaining_count > 0;
            
            $batch_message = "Eliminati $deleted necrologi (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0,
                'messages' => $messages,
                'remaining' => $remaining_count
            ];
        }

        public function restart_image_downloads()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'migration_nonce')) {
                wp_die('Security check failed');
            }

            try {
                $necro_migration = $this->migration_tasks['necrologi'] ?? null;
                if (!$necro_migration) {
                    wp_send_json_error('Migration task not found');
                    return;
                }

                $result = $necro_migration->force_restart_image_queue();
                
                if ($result['success']) {
                    wp_send_json_success([
                        'message' => $result['message'],
                        'stats' => $result['stats'] ?? []
                    ]);
                } else {
                    wp_send_json_error($result['message']);
                }
            } catch (Exception $e) {
                wp_send_json_error('Error restarting image downloads: ' . $e->getMessage());
            }
        }

        public function stop_image_downloads()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'migration_nonce')) {
                wp_die('Security check failed');
            }

            try {
                $necro_migration = $this->migration_tasks['necrologi.csv'] ?? null;
                if (!$necro_migration) {
                    wp_send_json_error('Migration task not found');
                    return;
                }

                $result = $necro_migration->stop_image_processing();
                
                if ($result['success']) {
                    wp_send_json_success([
                        'message' => $result['message'],
                        'stats' => $result['stats'] ?? []
                    ]);
                } else {
                    wp_send_json_error($result['message']);
                }
            } catch (Exception $e) {
                wp_send_json_error('Error stopping image downloads: ' . $e->getMessage());
            }
        }

        public function check_image_download_status()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'migration_nonce')) {
                wp_die('Security check failed');
            }

            try {
                $necro_migration = $this->migration_tasks['necrologi.csv'] ?? null;
                if (!$necro_migration) {
                    wp_send_json_error('Migration task not found');
                    return;
                }

                $status = $necro_migration->get_detailed_queue_status();
                wp_send_json_success($status);
            } catch (Exception $e) {
                wp_send_json_error('Error checking status: ' . $e->getMessage());
            }
        }

    }
}