<?php

namespace Dokan_Mods\Migration_Tasks;

use Exception;
use RuntimeException;
use SplFileObject;
use WP_Error;
use WP_Query;
use Dokan_Mods\Migration_Tasks\MigrationTasks;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\NecrologiMigration')) {
    class NecrologiMigration extends MigrationTasks
    {
        //private string $image_cron_hook = 'dokan_mods_download_images';

        private string $image_queue_file = 'image_download_queue.csv';
        private string $cron_hook = 'dokan_mods_process_image_queue';
        private int $images_per_cron = 500;

        public function __construct(String $upload_dir, String $progress_file, String $log_file, Int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);

            add_action('dokan_mods_process_image_queue', [$this, 'process_image_queue']);

            add_filter('cron_schedules', function ($schedules) {
                // Controlla se l'intervallo 'every_one_minute' non esiste già
                if (!isset($schedules['every_one_minute'])) {
                    $schedules['every_one_minute'] = array(
                        'interval' => 60, // ogni 60 secondi
                        'display' => __('Ogni minuto')
                    );
                }
                return $schedules;
            });


        }

        private function schedule_image_processing()
        {
            $this->scheduleImageProcessing($this->cron_hook);
        }


        public function migrate_necrologi_batch($file_name)
        {

            if($this->get_progress_status($file_name) == 'finished'){
                $this->schedule_image_processing();

                $this->log("Il file $file_name è già stato processato completamente.");
                return true;
            }

            $start_time = microtime(true);
            $this->set_progress_status($file_name, 'ongoing');

            if(!$file = $this->load_file($file_name)){
                return false;
            }

            if (!$progress = $this->first_call_check($file)) {
                return false;
            }

            $processed = $progress['processed'];
            $total_rows = $progress['total'];

            $header = $file->fgetcsv();             // Leggi l'header

            $file->seek($processed);                // Salta le righe già processate

            $batch_data = [];
            $batch_user_old_ids = [];
            $batch_post_old_ids = [];
            $id_account_index = array_search('IdAccount', $header);

            // Raccolta dati batch
            while (!$file->eof() && count($batch_data) < $this->batch_size) {
                $data = $file->fgetcsv();
                if (empty($data)) continue;

                $batch_data[] = $data;
                $batch_user_old_ids[] = $data[$id_account_index];
                $batch_post_old_ids[] = $data[array_search('ID', $header)];
            }

            // Ottimizzazione: pre-fetch degli utenti esistenti
            $existing_posts = $this->get_existing_posts_by_old_ids(array_unique($batch_post_old_ids));
            $existing_users = $this->get_existing_users_by_old_ids(array_unique($batch_user_old_ids));
            unset($batch_user_old_ids); // Libera memoria
            unset($batch_post_old_ids); // Libera memoria

            $image_queue = [];

            // Process batch
            foreach ($batch_data as $data) {

                $this->process_single_record(
                    $data,
                    $header,
                    $existing_posts,
                    $existing_users,
                    $image_queue
                );
                $processed++;
                $this->update_progress($file_name, $processed, $total_rows);
            }

            // Cleanup
            unset($batch_data);
            $file = null; // Chiude il file

            // Add images to processing queue
            if (!empty($image_queue)) {
                $result = $this->addToImageQueueSimple($image_queue, $this->image_queue_file);
                $this->log("Added " . count($image_queue) . " images to queue: " . ($result ? 'SUCCESS' : 'FAILED'));
            }
            unset($image_queue);

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");

            $this->set_progress_status($file_name, 'completed');

            if ($processed >= $total_rows) {
                $this->set_progress_status($file_name, 'finished');
                $this->schedule_image_processing();
            }

            return $processed >= $total_rows;
        }



        private function process_single_record($data, $header, $existing_posts, $existing_users, &$image_queue)
        {
            static $field_indexes = null;

            // Cache degli indici dei campi
            if ($field_indexes === null) {
                $field_indexes = [
                    'ID' => array_search('ID', $header),
                    'IdAccount' => array_search('IdAccount', $header),
                    'Nome' => array_search('Nome', $header),
                    'Cognome' => array_search('Cognome', $header),
                    'Anni' => array_search('Anni', $header),
                    'DataMorte' => array_search('DataMorte', $header),
                    'Foto' => array_search('Foto', $header),
                    'Testo' => array_search('Testo', $header),
                    'Pubblicato' => array_search('Pubblicato', $header),
                    'Luogo' => array_search('Luogo', $header),
                    'DataFunerale' => array_search('DataFunerale', $header),
                    'ImmagineManifesto' => array_search('ImmagineManifesto', $header),
                    'AltTesto' => array_search('AltTesto', $header)
                ];
            }

            //check if ID is in array $existing_posts
            if (isset($existing_posts[$data[$field_indexes['ID']]])) {
                $this->log("Annuncio di morte già esistente: ID {$data[$field_indexes['ID']]}");
                return false;
            }

            $author_id = $this->getAuthorIdWithFallback($data[$field_indexes['IdAccount']], $existing_users);

            $post_id = wp_insert_post([
                'post_type' => 'annuncio-di-morte',
                'post_status' => $data[$field_indexes['Pubblicato']] == 1 ? 'publish' : 'draft',
                'post_title' => $data[$field_indexes['Nome']] . ' ' . $data[$field_indexes['Cognome']],
                'post_author' => $author_id,
                'post_date' => date('Y-m-d H:i:s', strtotime($data[$field_indexes['DataMorte']]))
            ]);

            if (!is_wp_error($post_id)) {
                $this->update_post_fields($post_id, $data, $field_indexes);

                // Add images to queue if they exist
                $foto_value = $data[$field_indexes['Foto']] ?? '';
                $manifesto_value = $data[$field_indexes['ImmagineManifesto']] ?? '';
                
                if ($foto_value) {
                    $image_queue[] = ['profile_photo', 'https://necrologi.sciame.it/necrologi/' . $foto_value, $post_id, $author_id];
                }

                if ($manifesto_value) {
                    $image_queue[] = ['manifesto_image', 'https://necrologi.sciame.it/necrologi/' . $manifesto_value, $post_id, $author_id];
                }

                $this->log("Annuncio di morte creato: ID $post_id, Nome {$data[$field_indexes['Nome']]} {$data[$field_indexes['Cognome']]}");
            } else {
                $this->log("Errore nella creazione dell'annuncio di morte: " . $post_id->get_error_message());
            }

            return $post_id;
        }

        private function update_post_fields($post_id, $data, $field_indexes)
        {
            $fields = [
                'id_old' => $data[$field_indexes['ID']],
                'nome' => $data[$field_indexes['Nome']],
                'cognome' => $data[$field_indexes['Cognome']],
                'eta' => intval($data[$field_indexes['Anni']]),
                'data_di_morte' => $data[$field_indexes['DataMorte']],
                'testo_annuncio_di_morte' => $data[$field_indexes['Testo']] ?: $data[$field_indexes['AltTesto']],
                'citta' => $this->format_luogo($data[$field_indexes['Luogo']]),
                'funerale_data' => $data[$field_indexes['DataFunerale']]
            ];

            foreach ($fields as $field_key => $value) {
                update_field($field_key, $value, $post_id);
            }
        }

        // OLD image_queue method removed - now using centralized addToImageQueue in parent class

        // OLD get_images_ids_by_urls method removed - existing image detection now handled by centralized system

        // OLD is_process_active method removed - now using inherited method from parent class

        public function process_image_queue()
        {
            // Use centralized image queue processing
            $this->processImageQueue($this->image_queue_file, $this->cron_hook, $this->images_per_cron);
        }




        // OLD download_and_attach_image method removed - now using centralized downloadAndAttachImage in parent class

        // New helper function to get post by old ID
        protected function get_existing_posts_by_old_ids($old_ids, $post_types = ['annuncio-di-morte'])
        {
            // Chiama il metodo della classe base
            return parent::get_existing_posts_by_old_ids($old_ids, $post_types);
        }


        // get_existing_users_by_old_ids method moved to parent class MigrationTasks


        private function format_luogo($luogo)
        {
            global $dbClassInstance;
            $luogo = strtolower($luogo);
            if (strpos($luogo, 'cimitero di') !== false) {
                $parts = explode('cimitero di', $luogo);
                $luogo = trim($parts[1]);
            }

            // Rimuovi eventuali caratteri speciali e numeri
            $luogo = preg_replace('/[^a-z\s]/', '', $luogo);

            // Cerca il comune nel database
            $result = $dbClassInstance->search_comune($luogo);

            if (!empty($result)) {
                // Se troviamo una corrispondenza, usiamo il nome del comune dal database
                $luogo = $result[0]['nome'];
            } else {
                // Se non troviamo una corrispondenza, formatta il nome originale
                $luogo = ucwords($luogo);
            }

            return $luogo;
        }

        public function force_restart_image_queue()
        {
            try {
                $queue_file = $this->upload_dir . $this->image_queue_file;
                
                // Stop any running cron
                wp_clear_scheduled_hook('dokan_mods_process_image_queue');
                
                // Reset progress status
                $this->set_progress_status($this->image_queue_file, 'pending');
                $this->update_progress($this->image_queue_file, 0, 0);
                
                // Get queue stats before restart
                $stats = $this->get_detailed_queue_status();
                
                // Reschedule the cron job
                if (file_exists($queue_file) && filesize($queue_file) > 0) {
                    $this->schedule_image_processing();
                    $message = 'Image download queue restarted successfully. Processing will begin shortly.';
                } else {
                    $message = 'No image queue file found. Queue restart not needed.';
                }
                
                $this->log("Image queue force restart: " . $message);
                
                return [
                    'success' => true,
                    'message' => $message,
                    'stats' => $stats
                ];
                
            } catch (Exception $e) {
                $error_msg = 'Failed to restart image queue: ' . $e->getMessage();
                $this->log($error_msg);
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }
        }

        public function stop_image_processing()
        {
            try {
                // Stop the cron job
                wp_clear_scheduled_hook('dokan_mods_process_image_queue');
                
                // Set status to stopped
                $this->set_progress_status($this->image_queue_file, 'stopped');
                
                // Get final stats
                $stats = $this->get_detailed_queue_status();
                
                $message = 'Image download processing stopped successfully.';
                $this->log("Image processing stopped by user request");
                
                return [
                    'success' => true,
                    'message' => $message,
                    'stats' => $stats
                ];
                
            } catch (Exception $e) {
                $error_msg = 'Failed to stop image processing: ' . $e->getMessage();
                $this->log($error_msg);
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }
        }

        public function get_detailed_queue_status()
        {
            $queue_file = $this->upload_dir . $this->image_queue_file;
            $progress = $this->get_progress($this->image_queue_file);
            $status = $this->get_progress_status($this->image_queue_file);
            
            // If progress total is 0 but file exists, count rows
            $total_rows = $progress['total'] ?? 0;
            if ($total_rows == 0 && file_exists($queue_file) && filesize($queue_file) > 0) {
                try {
                    $file = new SplFileObject($queue_file, 'r');
                    $file->setFlags(SplFileObject::READ_CSV);
                    $file->seek(PHP_INT_MAX);
                    $total_rows = $file->key();
                    $file = null;
                } catch (Exception $e) {
                    $this->log("Error counting queue file rows: " . $e->getMessage());
                }
            }
            
            $result = [
                'queue_exists' => file_exists($queue_file),
                'file_size' => file_exists($queue_file) ? filesize($queue_file) : 0,
                'status' => $status,
                'processed' => $progress['processed'] ?? 0,
                'total' => $total_rows,
                'percentage' => $total_rows > 0 ? round(($progress['processed'] ?? 0) / $total_rows * 100, 2) : 0,
                'cron_scheduled' => wp_next_scheduled('dokan_mods_process_image_queue') !== false,
                'next_cron_time' => wp_next_scheduled('dokan_mods_process_image_queue'),
                'last_update' => $progress['last_update'] ?? null
            ];
            
            // Calculate additional stats
            if ($result['total'] > 0) {
                $result['remaining'] = $result['total'] - $result['processed'];
                $result['completion_rate'] = round(($result['processed'] / $result['total']) * 100, 2);
            } else {
                $result['remaining'] = 0;
                $result['completion_rate'] = 0;
            }
            
            // Add estimated time if processing
            if ($result['status'] === 'ongoing' && $result['processed'] > 0) {
                $elapsed_time = time() - strtotime($result['last_update'] ?? 'now');
                if ($elapsed_time > 0 && $result['remaining'] > 0) {
                    $avg_time_per_image = $elapsed_time / $result['processed'];
                    $result['estimated_completion'] = $result['remaining'] * $avg_time_per_image;
                }
            }
            
            return $result;
        }


    }
}