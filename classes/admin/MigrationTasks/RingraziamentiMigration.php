<?php

namespace Dokan_Mods\Migration_Tasks;

use Exception;
use RuntimeException;
use SplFileObject;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\RingraziamentiMigration')) {
    class RingraziamentiMigration extends MigrationTasks
    {
        private string $image_queue_file = 'image_download_queue_ringraziamenti.csv';
        private string $cron_hook = 'dokan_mods_process_image_ringraziamenti_queue';
        private int $images_per_cron = 500;

        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);

            add_action('dokan_mods_process_image_ringraziamenti_queue', [$this, 'process_image_queue']);

            add_filter('cron_schedules', function ($schedules) {
                if (!isset($schedules['every_one_minute'])) {
                    $schedules['every_one_minute'] = array(
                        'interval' => 60,
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

        public function migrate_ringraziamenti_batch($file_name)
        {
            if ($this->get_progress_status($file_name) == 'finished') {
                $this->schedule_image_processing();
                $this->log("Il file $file_name è già stato processato completamente.");
                return true;
            }

            $start_time = microtime(true);
            $this->set_progress_status($file_name, 'ongoing');

            if (!$file = $this->load_file($file_name)) {
                return false;
            }

            if (!$progress = $this->first_call_check($file)) {
                return false;
            }

            $processed = $progress['processed'];
            $total_rows = $progress['total'];

            // Ensure CSV control is set correctly before reading header
            $file->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
            $this->log("DEBUG: CSV control set to delimiter: '{$this->csv_delimiter}', enclosure: '{$this->csv_enclosure}'");
            
            $header = $file->fgetcsv();
            $this->log("DEBUG: Header read: " . print_r($header, true));

            $file->seek($processed);
            
            // Ensure CSV control is maintained after seek
            $file->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);

            $batch_data = [];
            $batch_necrologi_ids = [];

            // Cache degli indici
            $id_necrologio_index = array_search('IdNecrologio', $header);
            $this->log("DEBUG: Field indexes - IdNecrologio: $id_necrologio_index");

            // Raccolta dati batch
            $row_count = 0;
            while (!$file->eof() && count($batch_data) < $this->batch_size) {
                $data = $file->fgetcsv();
                if (empty($data)) continue;

                // Debug: log first few rows to see the actual data structure
                if ($row_count < 3) {
                    $this->log("DEBUG: Row $row_count data: " . print_r($data, true));
                    $this->log("DEBUG: Row $row_count data count: " . count($data));
                    if (!empty($data)) {
                        $this->log("DEBUG: Row $row_count IdNecrologio index $id_necrologio_index value: " . ($data[$id_necrologio_index] ?? 'INDEX_NOT_FOUND'));
                    }
                }

                $batch_data[] = $data;
                $batch_necrologi_ids[] = $data[$id_necrologio_index] ?? '';
                $row_count++;
            }

            // Pre-fetch dei dati esistenti
            $existing_necrologi = $this->get_existing_posts_by_old_ids(
                array_unique($batch_necrologi_ids),
                ['annuncio-di-morte']
            );

            // Process batch
            foreach ($batch_data as $data) {
                $this->process_single_record($data, $header, $existing_necrologi, $image_queue);
                $processed++;
                $this->update_progress($file_name, $processed, $total_rows);
            }

            // Process image queue with centralized method
            if (!empty($image_queue)) {
                $this->addToImageQueueSimple($image_queue, $this->image_queue_file);
            }

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");

            if ($processed >= $total_rows) {
                $this->set_progress_status($file_name, 'finished');
                
                // Controlla se ci sono ancora immagini da processare
                $queue_path = $this->upload_dir . $this->image_queue_file;
                if (file_exists($queue_path)) {
                    $queue_progress = $this->getImageQueueProgress($this->image_queue_file);
                    if ($queue_progress['processed'] < $queue_progress['total']) {
                        $this->log("Migrazione ringraziamenti completata, ma ci sono ancora immagini da processare: {$queue_progress['processed']}/{$queue_progress['total']}");
                        $this->schedule_image_processing();
                    } else {
                        $this->log("Migrazione ringraziamenti e download immagini completati");
                    }
                } else {
                    $this->schedule_image_processing();
                }
            } else {
                $this->set_progress_status($file_name, 'completed');
            }

            return $processed >= $total_rows;
        }

        private function process_single_record($data, $header, $existing_necrologi, &$image_queue)
        {
            static $field_indexes = null;

            if ($field_indexes === null) {
                $field_indexes = [
                    'IdNecrologio' => array_search('IdNecrologio', $header),
                    'Data' => array_search('Data', $header),
                    'Foto' => array_search('Foto', $header),
                    'Testo' => array_search('Testo', $header),
                    'Pubblicato' => array_search('Pubblicato', $header),
                ];
            }

            // Get the associated necrologio
            $necrologio_id = $existing_necrologi[$data[$field_indexes['IdNecrologio']]] ?? null;
            if (!$necrologio_id) {
                $this->log("Necrologio non trovato per ID: " . $data[$field_indexes['IdNecrologio']]);
                return false;
            }

            // Get città from necrologio and get provincia based on città
            $citta = get_field('citta', $necrologio_id);

            // Get provincia using the città
            global $dbClassInstance;
            $provincia = $dbClassInstance->get_provincia_by_comune($citta);

            // Create the ringraziamento post
            $post_data = [
                'post_type' => 'ringraziamento',
                'post_status' => $data[$field_indexes['Pubblicato']] == 1 ? 'publish' : 'draft',
                'post_title' => get_the_title($necrologio_id),
                'post_date' => date('Y-m-d H:i:s', strtotime($data[$field_indexes['Data']])),
                'post_author' => get_post_field('post_author', $necrologio_id),
            ];

            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id)) {
                // Update ACF fields
                $fields_to_update = [
                    'annuncio_di_morte' => $necrologio_id,                // IdNecrologio
                    'testo_ringraziamento' => $data[$field_indexes['Testo']], // Testo
                    'citta' => $citta,                        // Città
                    'provincia' => $provincia,                    // Provincia
                ];

                foreach ($fields_to_update as $field_key => $value) {
                    if (!empty($value)) {
                        update_field($field_key, $value, $post_id);
                    }
                }

                // Handle image with standardized format
                if (!empty($data[$field_indexes['Foto']])) {
                    $image_queue[] = ['ringraziamento', 'https://necrologi.sciame.it/necrologi/' . $data[$field_indexes['Foto']], $post_id, get_post_field('post_author', $necrologio_id)];
                }

                $this->log("Ringraziamento creato: ID $post_id, Città: $citta, Provincia: $provincia");
                return true;
            } else {
                $this->log("Errore nella creazione del ringraziamento: " . $post_id->get_error_message());
                return false;
            }
        }

        // OLD image_queue method removed - now using centralized addToImageQueue in parent class

        // OLD get_images_ids_by_urls method removed - existing image detection now handled by centralized system

        public function process_image_queue()
        {
            // Check if there are unprocessed images that might have been stuck
            $queue_path = $this->upload_dir . $this->image_queue_file;
            if (file_exists($queue_path)) {
                $queue_progress = $this->getImageQueueProgress($this->image_queue_file);
                $status = $this->getImageQueueProgressStatus($this->image_queue_file);
                
                // If status is completed but there are still images to process, reset to continue
                if ($status === 'completed' && $queue_progress['processed'] < $queue_progress['total']) {
                    $this->log("Detected incomplete image processing (status: $status, progress: {$queue_progress['processed']}/{$queue_progress['total']}) - restarting");
                    $this->setImageQueueProgressStatus($this->image_queue_file, 'completed');
                }
            }
            
            // Use centralized image queue processing
            $this->processImageQueue($this->image_queue_file, $this->cron_hook, $this->images_per_cron);
        }

        /**
         * Force restart image processing for stuck queues
         */
        public function force_restart_image_processing()
        {
            $queue_path = $this->upload_dir . $this->image_queue_file;
            if (file_exists($queue_path)) {
                $queue_progress = $this->getImageQueueProgress($this->image_queue_file);
                $status = $this->getImageQueueProgressStatus($this->image_queue_file);
                
                $this->log("Forcing restart of image processing - Current status: $status, Progress: {$queue_progress['processed']}/{$queue_progress['total']}");
                
                // Reset status to allow processing to continue
                $this->setImageQueueProgressStatus($this->image_queue_file, 'completed');
                
                // Clear any existing cron job
                wp_clear_scheduled_hook($this->cron_hook);
                
                // Schedule new processing
                $this->schedule_image_processing();
                
                $this->log("Image processing restarted for ringraziamenti");
                return true;
            } else {
                $this->log("No image queue file found for ringraziamenti");
                return false;
            }
        }

        // OLD download_and_attach_image method removed - now using centralized downloadAndAttachImage in parent class

        protected function get_existing_posts_by_old_ids($old_ids, $post_types = ['ringraziamento'])
        {
            return parent::get_existing_posts_by_old_ids($old_ids, $post_types);
        }

        private function get_post_by_old_id($old_id)
        {
            global $wpdb;
            $query = $wpdb->prepare(
                "SELECT p.*
                FROM {$wpdb->prefix}posts p
                INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
                WHERE pm.meta_key = 'id_old' 
                AND pm.meta_value = %s
                AND p.post_type = 'annuncio-di-morte'
                LIMIT 1",
                $old_id
            );
            $post = $wpdb->get_row($query);
            return $post ?: null;
        }
    }
}