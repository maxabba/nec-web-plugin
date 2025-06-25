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
            
            if ($this->get_progress_status($file) === 'ongoing') {
                return;
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
            return $progress[$file]['status'];
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

    }
}