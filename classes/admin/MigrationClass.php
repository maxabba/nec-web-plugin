<?php

namespace Dokan_Mods;

use Dokan_Mods\Migration_Tasks\NecrologiMigration;
use Dokan_Mods\Migration_Tasks\AccountsMigration;
use Dokan_Mods\Migration_Tasks\RicorrenzeMigration;
use Exception;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\MigrationClass')) {
    class MigrationClass
    {
        private $allowed_files = ['accounts.csv', 'necrologi.csv', 'ricorrenze.csv'];
        private $upload_dir;
        private $log_file;
        private $debug_log_file;
        private $progress_file;
        private $current_step = 0;
        private $batch_size = 1000; // Numero di record da processare per batch
        private $cron_hooks = [];

        private $accounts_migration_task;
        private $necrologi_migration_task;
        private $ricorrenze_migration_task;

        public function __construct()
        {
            add_action('admin_menu', array($this, 'add_submenu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_ajax_start_migration', array($this, 'handle_file_upload'));
            add_action('wp_ajax_check_migration_status', array($this, 'check_migration_status'));
            add_action('wp_ajax_get_next_step', array($this, 'get_next_step'));
            add_action('wp_ajax_stop_migration', array($this, 'stop_migration'));

            add_action('init', array($this, 'init'));



            $this->upload_dir = wp_upload_dir()['basedir'] . '/temp_migration/';
            $this->log_file = $this->upload_dir . 'migration_log.txt';
            $this->debug_log_file = $this->upload_dir . 'debug_log.txt';
            $this->progress_file = $this->upload_dir . 'migration_progress.json';


            if (!file_exists($this->upload_dir)) {
                if (mkdir($this->upload_dir, 0755, true)) {
                    $this->log("Directory temp_migration creata con successo");
                } else {
                    $this->log("Impossibile creare la directory temp_migration");
                }
            }

            $this->debug_log("MigrationClass costruttore chiamato");

            $this->load_migration_tasks();

            $this->necrologi_migration_task = new NecrologiMigration($this->upload_dir, $this->progress_file, $this->log_file, $this->batch_size);
            $this->accounts_migration_task = new AccountsMigration($this->upload_dir, $this->progress_file, $this->log_file, $this->batch_size);
            $this->ricorrenze_migration_task = new RicorrenzeMigration($this->upload_dir, $this->progress_file, $this->log_file, $this->batch_size);
        }

        public function init()
        {
            if (isset($_COOKIE['XDEBUG_SESSION'])) {
                update_option('xdebug_session', $_COOKIE['XDEBUG_SESSION']);
            }

            foreach ($this->allowed_files as $file) {
                $hook = __NAMESPACE__ . "_run_migration_batch_" . sanitize_title($file);
                $this->cron_hooks[$file] = $hook;

                // Aggiungi l'azione per gestire l'evento
                add_action($hook, array($this, 'process_migration_batch'), 10, 1);
            }

            // Aggiungi l'intervallo personalizzato ogni minuto
            add_filter('cron_schedules', function ($schedules) {
                if (!isset($schedules['every_minute'])) {
                    $schedules['every_minute'] = array(
                        'interval' => 60, // Intervallo di 60 secondi (1 minuto)
                        'display' => __('Every Minute')
                    );
                }
                return $schedules;
            });
        }

        private function load_migration_tasks()
        {
            // Assicurati di caricare la classe base MigrationTasks
            require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/admin/MigrationTasks/MigrationTasks.php';

            // Poi carica gli altri task di migrazione
            $migration_tasks_dir = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'classes/admin/MigrationTasks/';
            foreach (glob($migration_tasks_dir . '*.php') as $task_file) {
                if (basename($task_file) !== 'MigrationTasks.php') {
                    require_once $task_file;
                    $this->debug_log("Caricato file di migrazione: {$task_file}");
                }
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



        public function handle_file_upload()
        {

            //TODO: eliminare il file relativo se esiste gia
            $this->debug_log("handle_file_upload chiamato");

            try {
                if (!check_admin_referer('migration_nonce', 'migration_nonce')) {
                    throw new Exception("Verifica di sicurezza fallita");
                }

                if (!current_user_can('manage_options')) {
                    throw new Exception("Permessi insufficienti");
                }

                if (!isset($_POST['current_file'])) {
                    throw new Exception("current_file non impostato nella richiesta POST");
                }

                $current_file = $_POST['current_file'];
                $this->debug_log("File corrente atteso: " . $current_file);

                // Verifichiamo se lo step è stato saltato
                if (isset($_POST['skip_step']) && $_POST['skip_step'] === 'on') {
                    $this->log("Step per il file {$current_file} saltato");
                    wp_send_json_success("Step per {$current_file} saltato");
                    return;
                }

                // Cercare il file caricato in $_FILES
                $uploaded_file = null;
                foreach ($_FILES as $input_name => $file_data) {
                    if (!empty($file_data['name'])) {
                        $uploaded_file = $file_data;
                        break;
                    }
                }

                if (!$uploaded_file) {
                    throw new Exception("Nessun file caricato trovato nella richiesta");
                }

                $this->debug_log("Dettagli file ricevuto: " . print_r($uploaded_file, true));

                if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Errore nel caricamento del file: " . $this->get_upload_error_message($uploaded_file['error']));
                }

                // Verifica dei permessi della directory di upload
                if (!is_dir($this->upload_dir)) {
                    throw new Exception("La directory di upload non esiste: " . $this->upload_dir);
                }

                if (!is_writable($this->upload_dir)) {
                    throw new Exception("La directory di upload non è scrivibile: " . $this->upload_dir);
                }

                $this->debug_log("Permessi della directory di upload: " . substr(sprintf('%o', fileperms($this->upload_dir)), -4));

                // Verifica del file temporaneo
                if (!file_exists($uploaded_file['tmp_name'])) {
                    throw new Exception("Il file temporaneo non esiste: " . $uploaded_file['tmp_name']);
                }

                if (!is_readable($uploaded_file['tmp_name'])) {
                    throw new Exception("Il file temporaneo non è leggibile: " . $uploaded_file['tmp_name']);
                }

                $this->debug_log("Permessi del file temporaneo: " . substr(sprintf('%o', fileperms($uploaded_file['tmp_name'])), -4));

                $destination = $this->upload_dir . $current_file;

                //check if the file already exists
                if (file_exists($destination)) {
                    unlink($destination);
                }

                $upload_result = move_uploaded_file($uploaded_file['tmp_name'], $destination);
                $this->debug_log("Risultato caricamento {$current_file}: " . ($upload_result ? "successo" : "fallito"));

                if (!$upload_result) {
                    throw new Exception("Errore nel caricamento del file {$current_file}. Verifica i permessi della directory di upload.");
                }

                // Verifica finale del file caricato
                if (!file_exists($destination)) {
                    throw new Exception("Il file caricato non esiste nella destinazione: " . $destination);
                }

                $this->debug_log("Permessi del file caricato: " . substr(sprintf('%o', fileperms($destination)), -4));

                $this->log("File {$current_file} caricato con successo");
                if ($upload_result) {
                    // Inizializza il file di progresso per questo specifico file
                   // $this->initialize_progress_file($current_file);

                    // Avvia il processo di migrazione in background
                    $this->start_migration($current_file);

                    $this->log("File {$current_file} caricato con successo. Migrazione avviata in background.");
                    wp_send_json_success("File {$current_file} caricato. Migrazione avviata.");
                } else {
                    throw new Exception("Errore nel caricamento del file {$current_file}. Verifica i permessi della directory di upload.");
                }

            } catch (Exception $e) {
                $this->debug_log("Errore: " . $e->getMessage());
                $debug_info = [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'upload_dir' => $this->upload_dir,
                    'php_version' => PHP_VERSION,
                    'wp_version' => get_bloginfo('version'),
                ];
                wp_send_json_error($e->getMessage(), 500, ['debug' => $debug_info]);
            }
        }

        private function start_migration($file)
        {
            $this->debug_log("start_migration chiamato per {$file}");
            $this->log("Migrazione iniziata per {$file}");

            // Elimina il file stopMigration se esiste
            if (file_exists($this->upload_dir . 'stopMigration.txt')) {
                unlink($this->upload_dir . 'stopMigration.txt');
            }

            // Inizializza il file di progresso per questo specifico file
            $this->initialize_progress_file($file);

            // Programma il batch ricorrente ogni minuto solo per il file specificato
            if (isset($this->cron_hooks[$file])) {
                $hook = $this->cron_hooks[$file];

                // Rimuovi eventuali eventi programmati esistenti per questo hook
                wp_clear_scheduled_hook($hook, array($file));

                // Programma un nuovo evento
                $next_run = time() + 60; // Imposta il prossimo run tra 60 secondi
                wp_schedule_event($next_run, 'every_minute', $hook, array($file));

                $this->log("Nuovo batch programmato per {$file} alle " . date('Y-m-d H:i:s', $next_run));

                // Esegui immediatamente il primo batch
                do_action($hook, $file);
            } else {
                $this->log("Errore: Nessun cron hook definito per {$file}");
            }
        }



        public function process_migration_batch($file)
        {
            if($this->get_progress_status($file) === 'ongoing'){
                return;
            }

            $hook = $this->cron_hooks[$file];
            $this->log("Processing migration batch for {$file}");

            $completed = false;
            switch ($file) {
                case 'accounts.csv':
                    $completed = $this->accounts_migration_task->migrate_accounts_batch('accounts.csv');
                    break;
                case 'necrologi.csv':
                    $completed = $this->necrologi_migration_task->migrate_necrologi_batch('necrologi.csv');
                    break;
                case 'ricorrenze.csv':
                    $completed = $this->ricorrenze_migration_task->migrate_ricorrenze_batch('ricorrenze.csv');
                    break;
            }

            if ($completed) {
                wp_unschedule_hook($hook);
                $this->log("Batch cleared for {$file}");

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

            if (!check_ajax_referer('migration_nonce', 'nonce', false)) {
                $this->debug_log("Verifica nonce fallita in check_migration_status");
                wp_send_json_error('Invalid nonce.');
                return;
            }
            if (!current_user_can('manage_options')) {
                $this->debug_log("Permessi insufficienti in check_migration_status");
                wp_send_json_error('Permesso negato.');
            }

            $progress = json_decode(file_get_contents($this->progress_file), true);

            $log = file_get_contents($this->log_file);
            // set in $log only the last row of the log

            $log = explode("\n", $log);
            $log = array_filter($log);
            $log = array_slice((array)$log, -100);
            $log = implode("\n", $log);


            // Calcola il progresso totale
            $total_progress = 0;
            $total_files = 0;
            if (is_array($progress)) {
                foreach ($progress as $file => $file_progress) {
                    if (is_array($file_progress) && isset($file_progress['percentage'])) {
                        $total_progress += $file_progress['percentage'];
                        $total_files++;
                    }
                }
            }
            $overall_percentage = $total_files > 0 ? $total_progress / $total_files : 0;

            $this->debug_log("Invio risposta JSON per check_migration_status");
            wp_send_json_success([
                'progress' => $progress,
                'overall_percentage' => round($overall_percentage, 2),
                'log' => $log
            ]);
        }


        public function get_next_step()
        {
            if (!check_ajax_referer('migration_nonce', 'nonce', false)) {
                wp_send_json_error('Invalid nonce.');
                return;
            }
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permesso negato.');
            }

            $current_step = isset($_POST['current_step']) ? intval($_POST['current_step']) : 0;
            $next_step = $current_step + 1;

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
            $content = "
            <h2>Step {$this->current_step}: Upload {$file_name}</h2>
            <form id='migration-form' method='post' enctype='multipart/form-data'>
                " . wp_nonce_field('migration_nonce', 'migration_nonce', true, false) . "
                <input type='hidden' name='action' value='start_migration'>
                <input type='hidden' name='current_file' value='" . esc_attr($file) . "'>
                <label for='" . esc_attr($file) . "'>{$file_name}: </label>
                <input type='file' name='" . esc_attr($file) . "' id='" . esc_attr($file) . "' accept='.csv'><br><br>
                <label for='skip_step'>
                    <input type='checkbox' name='skip_step' id='skip_step'> Skip this step
                </label><br><br>
                <input type='submit' value='Upload and Migrate' class='button button-primary'>
            </form>
            <div id='upload-progress' style='display:none;'>
                <p>Upload in progress: <span id='progress-percentage'>0%</span></p>
                <div class='progress-bar'>
                    <div id='progress' class='progress'></div>
                </div>
            </div>";
            return $content;
        }

        private function debug_log($message)
        {
            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[$timestamp] $message\n";
            file_put_contents($this->debug_log_file, $log_entry, FILE_APPEND);
        }

        private function initialize_progress_file($file)
        {
            $this->debug_log("Inizializzazione del file di progresso per: $file");

            if (!file_exists($this->progress_file)) {
                $this->debug_log("Il file di progresso non esiste, lo creeremo");
                $progress = [];
            } else {
                $progress = json_decode(file_get_contents($this->progress_file), true) ?: [];
                $this->debug_log("Contenuto esistente del file di progresso: " . print_r($progress, true));
            }

            $progress[$file] = [
                'processed' => 0,
                'total' => 0,
                'percentage' => 0,
                'status' => 'not_started'
            ];

            $json_progress = json_encode($progress);
            if ($json_progress === false) {
                $this->debug_log("Errore nella codifica JSON del progresso");
                return;
            }

            $write_result = file_put_contents($this->progress_file, $json_progress);
            if ($write_result === false) {
                $this->debug_log("Errore nella scrittura del file di progresso");
            } else {
                $this->debug_log("File di progresso inizializzato con successo per $file");
            }

            //clear the log file
            file_put_contents($this->log_file, '');
        }

        protected function log($message)
        {
            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[$timestamp] $message\n";

            if (file_put_contents($this->log_file, $log_entry, FILE_APPEND) === false) {
                error_log("Failed to write to custom log file. Message was: $log_entry");
            }
        }

        private function get_upload_error_message($error_code)
        {
            switch ($error_code) {
                case UPLOAD_ERR_INI_SIZE:
                    return "Il file caricato supera la direttiva upload_max_filesize in php.ini";
                case UPLOAD_ERR_FORM_SIZE:
                    return "Il file caricato supera la direttiva MAX_FILE_SIZE specificata nel form HTML";
                case UPLOAD_ERR_PARTIAL:
                    return "Il file è stato caricato solo parzialmente";
                case UPLOAD_ERR_NO_FILE:
                    return "Nessun file è stato caricato";
                case UPLOAD_ERR_NO_TMP_DIR:
                    return "Manca una cartella temporanea";
                case UPLOAD_ERR_CANT_WRITE:
                    return "Impossibile scrivere il file su disco";
                case UPLOAD_ERR_EXTENSION:
                    return "Un'estensione PHP ha interrotto il caricamento del file";
                default:
                    return "Errore sconosciuto nel caricamento del file";
            }
        }



    }
}