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
        
        // Progress caching optimization
        private $progress_cache = null;
        private $progress_cache_time = 0;
        private $cache_ttl = 2; // Cache for 2 seconds
        private $log_cache = null;
        private $log_cache_time = 0;
        
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
            $ajax_actions = ['start_migration', 'check_migration_status', 'get_next_step', 'stop_migration','check_image_migration_status'];
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
            add_action('wp_ajax_cleanup_all_necrologi', array($this, 'cleanup_all_necrologi'));

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
            if ('dokan-mods_page_dokan-migration' !== $hook) {
                return;
            }

            wp_enqueue_script('migration-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/admin/migration.js', array('jquery'), '1.0', true);
            wp_localize_script('migration-script', 'migrationAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('migration_nonce')
            ));
            $this->debug_log("Script caricati per la pagina di migrazione");
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

                <!-- Bottone Pulizia Completa -->
                <div style="margin-top: 30px; padding: 20px; border: 2px solid #dc3545; border-radius: 5px; background-color: #f8f9fa;">
                    <h3 style="color: #dc3545;">⚠️ Zona Pericolosa</h3>
                    <p>Questa operazione eliminerà <strong>TUTTI</strong> i dati migrati permanentemente.</p>
                    <button id="cleanup-necrologi" class="button button-large" style="background-color: #dc3545; color: white; border-color: #dc3545;">
                        🗑️ Pulizia Completa Database Necrologi
                    </button>
                </div>

                <!-- Modale Pulizia -->
                <div id="cleanup-modal-overlay" class="cleanup-modal-overlay" style="display: none;"></div>
                <div id="cleanup-modal" class="cleanup-modal" style="display: none;">
                    <div class="cleanup-modal-content">
                        <div class="cleanup-header">
                            <h2>⚠️ ATTENZIONE: Pulizia Completa Database</h2>
                            <button class="cleanup-close">&times;</button>
                        </div>
                        
                        <div class="cleanup-body">
                            <div class="backup-warning">
                                <div class="warning-box">
                                    <h3>🚨 OBBLIGATORIO: Effettuare backup del database prima di procedere!</h3>
                                    <p>Questa operazione eliminerà <strong>PERMANENTEMENTE</strong>:</p>
                                    <ul>
                                        <li>✗ Tutti i necrologi (annunci di morte)</li>
                                        <li>✗ Tutti i manifesti collegati</li>
                                        <li>✗ Tutte le ricorrenze (anniversari e trigesimi)</li>
                                        <li>✗ Tutti i ringraziamenti</li>
                                        <li>✗ Tutte le immagini collegate</li>
                                    </ul>
                                    <p><strong>QUESTA AZIONE NON PUÒ ESSERE ANNULLATA!</strong></p>
                                </div>
                            </div>
                            
                            <div id="item-count" style="display: none;">
                                <h4>Elementi da eliminare:</h4>
                                <div id="count-details"></div>
                            </div>
                            
                            <div class="confirmation-section">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="backup-confirmed"> 
                                    <span class="checkmark"></span>
                                    Ho effettuato il backup del database
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="delete-confirmed"> 
                                    <span class="checkmark"></span>
                                    Confermo di voler eliminare TUTTI i dati elencati sopra
                                </label>
                            </div>
                            
                            <div id="cleanup-progress" style="display: none;">
                                <h3>Pulizia in corso...</h3>
                                <div class="progress-bar-container">
                                    <div class="progress-bar">
                                        <div id="cleanup-progress-bar" class="progress"></div>
                                    </div>
                                    <span id="cleanup-percentage">0%</span>
                                </div>
                                <div id="cleanup-current-step"></div>
                                <div id="cleanup-log" class="cleanup-log-container"></div>
                            </div>
                        </div>
                        
                        <div class="cleanup-footer">
                            <button id="start-cleanup" class="button button-primary button-large" disabled>
                                🗑️ Inizia Pulizia Completa
                            </button>
                            <button id="cancel-cleanup" class="button button-secondary button-large">
                                Annulla
                            </button>
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

                /* Cleanup Modal Styles */
                .cleanup-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    z-index: 9998;
                }

                .cleanup-modal {
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

                .cleanup-modal-content {
                    background: white;
                    border-radius: 8px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                }

                .cleanup-header {
                    padding: 20px;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #dc3545;
                    color: white;
                    border-radius: 8px 8px 0 0;
                }

                .cleanup-header h2 {
                    margin: 0;
                    color: white;
                }

                .cleanup-close {
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

                .cleanup-body {
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
                    margin: 10px 0;
                    cursor: pointer;
                    font-weight: bold;
                }

                .checkbox-label input[type="checkbox"] {
                    margin-right: 10px;
                    transform: scale(1.2);
                }

                .progress-bar-container {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin: 10px 0;
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

                .cleanup-footer {
                    padding: 20px;
                    border-top: 1px solid #ddd;
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                }

                #cleanup-current-step {
                    font-weight: bold;
                    color: #0073aa;
                    margin: 10px 0;
                }

                #count-details {
                    background: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 10px;
                    margin: 10px 0;
                }

                #count-details div {
                    margin: 5px 0;
                    display: flex;
                    justify-content: space-between;
                }

                .count-label {
                    font-weight: bold;
                }

                .count-number {
                    color: #dc3545;
                    font-weight: bold;
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
            
            // Check for stuck ongoing status with intelligent recovery
            $status = $this->get_progress_status($file);
            if ($status === 'ongoing') {
                $this->log("Migration status is 'ongoing' for $file - checking for timeout");
                
                // Check if we have a stuck ongoing status
                if ($this->is_status_stuck($file)) {
                    $this->log("Detected stuck 'ongoing' status for $file - attempting recovery");
                    
                    // Recovery intelligente basato sul progresso
                    $progress = $this->get_migration_progress($file);
                    if ($progress['total'] > 0 && $progress['processed'] >= 0) {
                        // Se abbiamo già un conteggio valido, continua da dove si è fermato
                        $this->log("Recovery: progresso esistente trovato - continua da {$progress['processed']}/{$progress['total']}");
                        $this->set_progress_status($file, 'completed');
                    } else {
                        // Se non abbiamo progresso valido, ricomincia
                        $this->log("Recovery: nessun progresso valido - restart completo");
                        $this->set_progress_status($file, 'not_started');
                    }
                } else {
                    $this->log("Status 'ongoing' is recent for $file - skipping this execution");
                    return;
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
            $progress = $this->read_progress_file();
            return isset($progress[$file]['status']) ? $progress[$file]['status'] : 'not_started';
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

            // Use cached data if available and recent
            $response_data = $this->get_cached_progress_data();
            
            if ($response_data !== null) {
                $this->debug_log("Utilizzo dati cache per check_migration_status");
                wp_send_json_success($response_data);
                return;
            }

            // Generate fresh data and cache it
            $response_data = $this->generate_fresh_progress_data();
            $this->cache_progress_data($response_data);

            $this->debug_log("Invio risposta JSON per check_migration_status (fresh data)");
            wp_send_json_success($response_data);
        }

        private function get_cached_progress_data()
        {
            $current_time = time();
            
            // Check if we have cached data and it's still fresh
            if ($this->progress_cache !== null && 
                ($current_time - $this->progress_cache_time) < $this->cache_ttl) {
                return $this->progress_cache;
            }
            
            return null;
        }

        private function cache_progress_data($data)
        {
            $this->progress_cache = $data;
            $this->progress_cache_time = time();
        }

        private function generate_fresh_progress_data()
        {
            // Read progress file efficiently
            $progress = $this->read_progress_file();
            
            // Read log efficiently
            $log_content = $this->read_log_efficiently();
            
            // Calculate overall progress
            $overall_percentage = $this->calculate_overall_percentage($progress);
            
            return [
                'progress' => $progress,
                'overall_percentage' => $overall_percentage,
                'log' => $log_content
            ];
        }

        private function read_progress_file()
        {
            $progress_content = file_get_contents($this->progress_file);
            return $progress_content ? json_decode($progress_content, true) : [];
        }

        private function read_log_efficiently()
        {
            // Check cache first
            $current_time = time();
            if ($this->log_cache !== null && 
                ($current_time - $this->log_cache_time) < $this->cache_ttl) {
                return $this->log_cache;
            }

            $log_lines = [];
            if (is_readable($this->log_file)) {
                // Use more efficient method for reading last lines
                $log_lines = $this->tail_file($this->log_file, 100);
            }
            
            $log_content = implode("\n", $log_lines);
            
            // Cache the result
            $this->log_cache = $log_content;
            $this->log_cache_time = $current_time;
            
            return $log_content;
        }

        private function tail_file($file_path, $lines = 100)
        {
            // More efficient tail implementation
            $file_size = filesize($file_path);
            if ($file_size === 0) {
                return [];
            }
            
            $chunk_size = 8192; // 8KB chunks
            $lines_found = [];
            $buffer = '';
            $pos = $file_size;
            
            $fp = fopen($file_path, 'rb');
            if (!$fp) {
                return [];
            }
            
            while ($pos > 0 && count($lines_found) < $lines) {
                $read_size = min($chunk_size, $pos);
                $pos -= $read_size;
                
                fseek($fp, $pos);
                $chunk = fread($fp, $read_size);
                $buffer = $chunk . $buffer;
                
                // Split into lines
                $new_lines = explode("\n", $buffer);
                $buffer = array_shift($new_lines); // Keep partial line for next iteration
                
                // Add complete lines to our result (in reverse order)
                foreach (array_reverse($new_lines) as $line) {
                    if (trim($line) !== '') {
                        array_unshift($lines_found, $line);
                        if (count($lines_found) >= $lines) {
                            break;
                        }
                    }
                }
            }
            
            // Add any remaining buffer content if we need more lines
            if (count($lines_found) < $lines && trim($buffer) !== '') {
                array_unshift($lines_found, $buffer);
            }
            
            fclose($fp);
            return array_slice($lines_found, -$lines); // Return last N lines
        }

        private function calculate_overall_percentage($progress)
        {
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

            return $total_files ? round($total_progress / $total_files, 2) : 0;
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

            // Use optimized progress reading
            $progress = $this->read_progress_file();

            // Filter for image download queue entries efficiently
            $image_download_queue = [];
            foreach ($progress as $key => $value) {
                if (strpos($key, 'image_download_queue') === 0) {
                    $image_download_queue[$key] = $value;
                }
            }

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
            
            // Invalidate cache when progress is updated
            $this->invalidate_progress_cache();
            
            $this->debug_log("Inizializzazione completata");
        }

        private function invalidate_progress_cache()
        {
            $this->progress_cache = null;
            $this->progress_cache_time = 0;
            $this->log_cache = null;
            $this->log_cache_time = 0;
        }

        public function update_progress_safely($file, $processed, $total, $status = null)
        {
            // Thread-safe progress update with file locking and validation
            $lock_file = $this->progress_file . '.lock';
            $lock_fp = fopen($lock_file, 'w');
            
            if (!$lock_fp || !flock($lock_fp, LOCK_EX)) {
                error_log("Could not acquire lock for progress update");
                return false;
            }
            
            try {
                // Read current progress
                $progress = $this->read_progress_file();
                
                // Update progress data with validation
                if (!isset($progress[$file])) {
                    $progress[$file] = [
                        'processed' => 0,
                        'total' => 0,
                        'percentage' => 0,
                        'status' => 'not_started'
                    ];
                }
                
                // Validazione: previeni regression nel progresso
                $current_processed = $progress[$file]['processed'] ?? 0;
                if ($processed < $current_processed && $status !== 'not_started') {
                    $this->log("ATTENZIONE: Tentativo di regressione progresso per $file: $processed < $current_processed");
                    $processed = max($processed, $current_processed);
                }
                
                $progress[$file]['processed'] = $processed;
                $progress[$file]['total'] = $total;
                $progress[$file]['percentage'] = $total > 0 ? round(($processed / $total) * 100, 2) : 0;
                
                if ($status !== null) {
                    $progress[$file]['status'] = $status;
                    $progress[$file]['status_timestamp'] = time();
                    $progress[$file]['last_update'] = date('Y-m-d H:i:s');
                }
                
                // Write updated progress
                $result = file_put_contents($this->progress_file, json_encode($progress, JSON_PRETTY_PRINT));
                
                if ($result !== false) {
                    // Invalidate cache on successful update
                    $this->invalidate_progress_cache();
                    return true;
                } else {
                    error_log("Failed to write progress update to file");
                    return false;
                }
                
            } finally {
                flock($lock_fp, LOCK_UN);
                fclose($lock_fp);
                if (file_exists($lock_file)) {
                    unlink($lock_file);
                }
            }
        }

        public function get_migration_progress($file = null)
        {
            $progress = $this->read_progress_file();
            
            if ($file !== null) {
                return isset($progress[$file]) ? $progress[$file] : [
                    'processed' => 0,
                    'total' => 0,
                    'percentage' => 0,
                    'status' => 'not_started'
                ];
            }
            
            return $progress;
        }

        private function is_status_stuck($file)
        {
            $progress = $this->read_progress_file();
            
            if (!isset($progress[$file]['status_timestamp'])) {
                // Se non c'è timestamp, considera bloccato
                $this->log("Nessun timestamp trovato per $file - considerato bloccato");
                return true;
            }
            
            $status_time = $progress[$file]['status_timestamp'];
            $current_time = time();
            $stuck_threshold = 120; // 2 minuti invece di 5
            $elapsed = $current_time - $status_time;
            
            $this->log("Status check per $file: elapsed {$elapsed}s, threshold {$stuck_threshold}s");
            
            return $elapsed > $stuck_threshold;
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
            } else {
                // Invalidate log cache when new log entry is added
                $this->log_cache = null;
                $this->log_cache_time = 0;
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

        public function cleanup_all_necrologi()
        {
            check_ajax_referer('migration_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Batch size ottimizzati per velocità mantenendo basso uso RAM
            $batch_sizes = [
                'necrologi' => 100,      // Con attachment, batch moderato
                'manifesti' => 300,      // Senza attachment, batch grandi
                'ricorrenze' => 300,     // Senza attachment, batch grandi  
                'ringraziamenti' => 300, // Senza attachment, batch grandi
                'images' => 200          // File system operations, batch medio
            ];
            $batch_size = $batch_sizes[$step] ?? 100;
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $step = sanitize_text_field($_POST['step'] ?? 'count');
            
            $this->log("Cleanup step: $step, offset: $offset");

            try {
                switch($step) {
                    case 'count':
                        wp_send_json_success($this->count_items_to_delete());
                        break;
                        
                    case 'necrologi':
                        wp_send_json_success($this->delete_necrologi_batch($offset, $batch_size));
                        break;
                        
                    case 'manifesti':
                        wp_send_json_success($this->delete_manifesti_batch($offset, $batch_size));
                        break;
                        
                    case 'ricorrenze':
                        wp_send_json_success($this->delete_ricorrenze_batch($offset, $batch_size));
                        break;
                        
                    case 'ringraziamenti':
                        wp_send_json_success($this->delete_ringraziamenti_batch($offset, $batch_size));
                        break;
                        
                    case 'images':
                        wp_send_json_success($this->delete_orphan_images_batch($offset, $batch_size));
                        break;
                        
                    // Verifica completezza per ogni step
                    case 'verify_necrologi':
                        wp_send_json_success(['remaining' => $this->count_posts_by_type('annuncio-di-morte')]);
                        break;
                        
                    case 'verify_manifesti':
                        wp_send_json_success(['remaining' => $this->count_posts_by_type('manifesto')]);
                        break;
                        
                    case 'verify_ricorrenze':
                        $anniversari = $this->count_posts_by_type('anniversario');
                        $trigesimi = $this->count_posts_by_type('trigesimo');
                        wp_send_json_success(['remaining' => $anniversari + $trigesimi]);
                        break;
                        
                    case 'verify_ringraziamenti':
                        wp_send_json_success(['remaining' => $this->count_posts_by_type('ringraziamento')]);
                        break;
                        
                    case 'verify_images':
                        wp_send_json_success(['remaining' => $this->count_necrologio_attachments()]);
                        break;
                        
                    default:
                        wp_send_json_error('Invalid step');
                }
            } catch (Exception $e) {
                $this->log("Errore durante cleanup: " . $e->getMessage());
                wp_send_json_error("Error during cleanup: " . $e->getMessage());
            }
        }

        private function count_items_to_delete()
        {
            $counts = [
                'necrologi' => $this->count_posts_by_type('annuncio-di-morte'),
                'manifesti' => $this->count_posts_by_type('manifesto'),
                'anniversari' => $this->count_posts_by_type('anniversario'),
                'trigesimi' => $this->count_posts_by_type('trigesimo'),
                'ringraziamenti' => $this->count_posts_by_type('ringraziamento'),
                'images' => $this->count_necrologio_attachments()
            ];
            
            $total = array_sum($counts);
            
            $this->log("Conteggio items da eliminare: " . json_encode($counts) . " - Totale: $total");
            
            return [
                'counts' => $counts,
                'total' => $total
            ];
        }

        private function count_posts_by_type($post_type)
        {
            global $wpdb;
            
            // Conta TUTTI i post di questo tipo, indipendentemente dallo status
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
                $post_type
            ));
            
            return intval($count);
        }

        private function count_necrologio_attachments()
        {
            global $wpdb;
            
            // Conta TUTTI gli attachment orfani (il cui parent non esiste più)
            $count = $wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
                WHERE p.post_type = 'attachment' 
                AND p.post_parent > 0
                AND parent.ID IS NULL
            ");
            
            return intval($count);
        }

        private function delete_necrologi_batch($offset, $batch_size)
        {
            global $wpdb;
            
            // Query SQL diretta per necrologi - TUTTI gli status, sempre offset 0 per "until empty"
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
                    'next_offset' => $offset,
                    'messages' => ['Nessun necrologio da eliminare'],
                    'step_total' => 0
                ];
            }

            $deleted = 0;
            $messages = [];
            
            // Preparazione placeholders per query bulk
            $ids_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
            
            // 1. Trova e elimina tutti gli attachment collegati ai necrologi
            $attachment_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND post_parent IN ($ids_placeholders)
            ", $post_ids));
            
            if (!empty($attachment_ids)) {
                // Elimina file fisici in batch
                foreach ($attachment_ids as $attachment_id) {
                    $file_path = get_attached_file($attachment_id);
                    if ($file_path && file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // Elimina attachment dal database in bulk
                $att_placeholders = implode(',', array_fill(0, count($attachment_ids), '%d'));
                $wpdb->query($wpdb->prepare("
                    DELETE FROM {$wpdb->postmeta} 
                    WHERE post_id IN ($att_placeholders)
                ", $attachment_ids));
                
                $wpdb->query($wpdb->prepare("
                    DELETE FROM {$wpdb->posts} 
                    WHERE ID IN ($att_placeholders)
                ", $attachment_ids));
                
                $messages[] = "Eliminati " . count($attachment_ids) . " attachment collegati";
            }
            
            // 2. Elimina postmeta dei necrologi
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->postmeta} 
                WHERE post_id IN ($ids_placeholders)
            ", $post_ids));
            
            // 3. Elimina i necrologi
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->posts} 
                WHERE ID IN ($ids_placeholders)
            ", $post_ids));

            // Memory cleanup
            wp_cache_flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Conta quanti necrologi rimangono per determinare se completato
            $remaining_count = $this->count_posts_by_type('annuncio-di-morte');
            $has_more = $remaining_count > 0;
            
            $batch_message = "Eliminati $deleted necrologi (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0, // Sempre 0 per logica "until empty"
                'messages' => $messages,
                'step_total' => $deleted,
                'remaining' => $remaining_count
            ];
        }

        private function delete_manifesti_batch($offset, $batch_size)
        {
            global $wpdb;
            
            // Query SQL diretta per massima velocità - TUTTI gli status, sempre offset 0
            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'manifesto'
                ORDER BY ID ASC
                LIMIT %d
            ", $batch_size));

            if (empty($post_ids)) {
                return [
                    'processed' => 0,
                    'completed' => true,
                    'next_offset' => $offset,
                    'messages' => ['Nessun manifesto da eliminare'],
                    'step_total' => 0
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

            // Conta quanti manifesti rimangono
            $remaining_count = $this->count_posts_by_type('manifesto');
            $has_more = $remaining_count > 0;
            
            $batch_message = "Eliminati $deleted manifesti (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0,
                'messages' => $messages,
                'step_total' => $deleted,
                'remaining' => $remaining_count
            ];
        }

        private function delete_ricorrenze_batch($offset, $batch_size)
        {
            global $wpdb;
            
            // Query SQL diretta per anniversari e trigesimi - TUTTI gli status, sempre offset 0
            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type IN ('anniversario', 'trigesimo')
                ORDER BY ID ASC
                LIMIT %d
            ", $batch_size));

            if (empty($post_ids)) {
                return [
                    'processed' => 0,
                    'completed' => true,
                    'next_offset' => $offset,
                    'messages' => ['Nessuna ricorrenza da eliminare'],
                    'step_total' => 0
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

            // Conta quante ricorrenze rimangono
            $anniversari = $this->count_posts_by_type('anniversario');
            $trigesimi = $this->count_posts_by_type('trigesimo');
            $remaining_count = $anniversari + $trigesimi;
            $has_more = $remaining_count > 0;
            
            $batch_message = "Eliminate $deleted ricorrenze (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0,
                'messages' => $messages,
                'step_total' => $deleted,
                'remaining' => $remaining_count
            ];
        }

        private function delete_ringraziamenti_batch($offset, $batch_size)
        {
            global $wpdb;
            
            // Query SQL diretta per ringraziamenti - TUTTI gli status, sempre offset 0
            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'ringraziamento'
                ORDER BY ID ASC
                LIMIT %d
            ", $batch_size));

            if (empty($post_ids)) {
                return [
                    'processed' => 0,
                    'completed' => true,
                    'next_offset' => $offset,
                    'messages' => ['Nessun ringraziamento da eliminare'],
                    'step_total' => 0
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

            // Conta quanti ringraziamenti rimangono
            $remaining_count = $this->count_posts_by_type('ringraziamento');
            $has_more = $remaining_count > 0;
            
            $batch_message = "Eliminati $deleted ringraziamenti (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0,
                'messages' => $messages,
                'step_total' => $deleted,
                'remaining' => $remaining_count
            ];
        }

        private function delete_orphan_images_batch($offset, $batch_size)
        {
            global $wpdb;
            
            // Trova attachment orfani ottimizzato - TUTTI gli status, sempre offset 0
            $attachment_data = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, pm.meta_value as file_path
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                WHERE p.post_type = 'attachment' 
                AND p.post_parent > 0
                AND parent.ID IS NULL
                LIMIT %d
            ", $batch_size), ARRAY_A);

            if (empty($attachment_data)) {
                return [
                    'processed' => 0,
                    'completed' => true,
                    'next_offset' => $offset,
                    'messages' => ['Nessuna immagine orfana da eliminare'],
                    'step_total' => 0
                ];
            }

            $deleted = 0;
            $messages = [];
            $attachment_ids = [];
            
            // Raccogli IDs e elimina file fisici in batch
            $upload_dir = wp_upload_dir();
            foreach ($attachment_data as $attachment) {
                $attachment_id = $attachment['ID'];
                $attachment_ids[] = $attachment_id;
                
                // Elimina file fisico se esiste
                if (!empty($attachment['file_path'])) {
                    $file_path = $upload_dir['basedir'] . '/' . $attachment['file_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            
            if (!empty($attachment_ids)) {
                // Eliminazione bulk attachment
                $ids_placeholders = implode(',', array_fill(0, count($attachment_ids), '%d'));
                
                // Elimina postmeta
                $wpdb->query($wpdb->prepare("
                    DELETE FROM {$wpdb->postmeta} 
                    WHERE post_id IN ($ids_placeholders)
                ", $attachment_ids));
                
                // Elimina posts
                $deleted = $wpdb->query($wpdb->prepare("
                    DELETE FROM {$wpdb->posts} 
                    WHERE ID IN ($ids_placeholders)
                ", $attachment_ids));
            }

            // Memory cleanup
            wp_cache_flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Conta quante immagini orfane rimangono
            $remaining_count = $this->count_necrologio_attachments();
            $has_more = $remaining_count > 0;
            
            $batch_message = "Eliminate $deleted immagini orfane (rimangono: $remaining_count)";
            $messages[] = $batch_message;
            $this->log($batch_message);

            return [
                'processed' => $deleted,
                'completed' => !$has_more,
                'next_offset' => 0,
                'messages' => $messages,
                'step_total' => $deleted,
                'remaining' => $remaining_count
            ];
        }

        private function delete_post_attachments($post_id)
        {
            $attachments = get_attached_media('', $post_id);
            
            foreach ($attachments as $attachment) {
                wp_delete_attachment($attachment->ID, true);
                $this->log("Eliminato attachment ID: {$attachment->ID} collegato al post $post_id");
            }
        }

    }
}