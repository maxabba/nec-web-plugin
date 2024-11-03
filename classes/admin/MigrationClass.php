<?php

namespace Dokan_Mods;

use Dokan_Mods\Migration_Tasks\ManifestiMigration;
use Dokan_Mods\Migration_Tasks\NecrologiMigration;
use Dokan_Mods\Migration_Tasks\AccountsMigration;
use Dokan_Mods\Migration_Tasks\RicorrenzeMigration;
use Exception;
use SplFileObject;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\MigrationClass')) {
    class MigrationClass
    {
        private $allowed_files = ['accounts.csv', 'necrologi.csv', 'ricorrenze.csv','manifesti.csv'];
        private $upload_dir;
        private $log_file;
        private $debug_log_file;
        private $progress_file;
        private $current_step = 0;
        private $batch_size = 1000; // Numero di record da processare per batch
        private $cron_hooks = [];

        private $migration_tasks = [];
        private $base_path;
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
                'ManifestiMigration.php'
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
                'manifesti' => new ManifestiMigration(...$common_params)
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
                'product-templates',
                'Migrazione',
                'Migrazione',
                'manage_options',
                'product-templates-migration',
                array($this, 'migration_page')
            );
            $this->debug_log("Sottomenu aggiunto");
        }

        public function enqueue_scripts($hook)
        {
            if ('product-templates_page_product-templates-migration' !== $hook) {
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


        public function process_migration_batch($file)
        {
            if (file_exists($this->upload_dir . $file)) {

                wp_cache_flush();
                gc_collect_cycles();
                if ($this->get_progress_status($file) === 'ongoing') {
                    return;
                }

                $hook = $this->cron_hooks[$file];
                $this->log("Processing migration batch for {$file}");

                $completed = false;
                switch ($file) {
                    case 'accounts.csv':
                        $completed = $this->migration_tasks['accounts']->migrate_accounts_batch('accounts.csv');
                        break;
                    case 'necrologi.csv':
                        $completed = $this->migration_tasks['necrologi']->migrate_necrologi_batch('necrologi.csv');
                        break;
                    case 'ricorrenze.csv':
                        $completed = $this->migration_tasks['ricorrenze']->migrate_ricorrenze_batch('ricorrenze.csv');
                        break;
                    case 'manifesti.csv':
                        $completed = $this->migration_tasks['manifesti']->migrate_manifesti_batch('manifesti.csv');
                        break;
                    default:
                        $this->log("Errore: Il file {$file} non è riconosciuto per la migrazione.");
                        return;
                }

                if ($completed) {
                    wp_unschedule_hook($hook);
                    $this->log("Batch cleared for {$file}");
                }
            } else {
                $this->log("Errore: Il file {$file} non esiste.");
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


    }
}