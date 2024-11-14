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
        private int $max_retries = 5;
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
            if (!wp_next_scheduled('dokan_mods_process_image_ringraziamenti_queue')) {
                $this->log('Scheduling image processing event.');
                wp_schedule_event(time(), 'every_one_minute', 'dokan_mods_process_image_ringraziamenti_queue');
            } else {
                $this->log('Image processing event already scheduled.');
            }
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

            $header = $file->fgetcsv();
            $file->seek($processed);

            $batch_data = [];
            $batch_necrologi_ids = [];

            // Cache degli indici
            $id_necrologio_index = array_search('IdNecrologio', $header);

            while (!$file->eof() && count($batch_data) < $this->batch_size) {
                $data = $file->fgetcsv();
                if (empty($data)) continue;

                $batch_data[] = $data;
                $batch_necrologi_ids[] = $data[$id_necrologio_index];
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

            // Process image queue
            if (!empty($image_queue)) {
                $this->image_queue($image_queue);
            }

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");

            if ($processed >= $total_rows) {
                $this->set_progress_status($file_name, 'finished');
                $this->schedule_image_processing();
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
            $citta = get_field('field_662ca58a35da3', $necrologio_id);

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
                    'field_666c0006827cd' => $necrologio_id,                // IdNecrologio
                    'field_666c0032827ce' => $data[$field_indexes['Testo']], // Testo
                    'field_662ca58a35da3' => $citta,                        // Città
                    'field_6638e3e77ffa0' => $provincia,                    // Provincia
                ];

                foreach ($fields_to_update as $field_key => $value) {
                    if (!empty($value)) {
                        update_field($field_key, $value, $post_id);
                    }
                }

                // Handle image
                if (!empty($data[$field_indexes['Foto']])) {
                    $image_queue[] = ['ringraziamento_image', $data[$field_indexes['Foto']], $post_id, get_post_field('post_author', $necrologio_id)];
                }

                $this->log("Ringraziamento creato: ID $post_id, Città: $citta, Provincia: $provincia");
                return true;
            } else {
                $this->log("Errore nella creazione del ringraziamento: " . $post_id->get_error_message());
                return false;
            }
        }

        private function image_queue($image_queue)
        {
            // Prepare image URLs
            $image_urls = array_map(function ($image) {
                return 'https://necrologi.sciame.it/necrologi/' . $image[1];
            }, $image_queue);

            // Get existing images
            $existing_images = $this->get_images_ids_by_urls($image_urls);
            $queue_file = $this->upload_dir . $this->image_queue_file;

            try {
                // Read existing queue
                $existing_queue = [];
                if (file_exists($queue_file)) {
                    $read_file = new SplFileObject($queue_file, 'r');
                    $read_file->setFlags(SplFileObject::READ_CSV);
                    foreach ($read_file as $data) {
                        if (!$data || count($data) < 4) continue;
                        $key = implode('|', array_slice($data, 0, 4));
                        $existing_queue[$key] = true;
                    }
                    $read_file = null;
                }

                $write_file = new SplFileObject($queue_file, 'a');

                foreach ($image_queue as $image) {
                    [$image_type, $image_path, $post_id, $author_id] = $image;

                    if (empty($image_path)) continue;

                    $image_url = 'https://necrologi.sciame.it/necrologi/' . $image_path;
                    $existing_image_id = $existing_images[$image_url] ?? null;

                    if ($existing_image_id) {
                        update_field('field_6731f274afd80', $existing_image_id, $post_id);
                        $this->log("Image already exists in media library: $image_url");
                        continue;
                    }

                    $queue_key = implode('|', [$image_type, $image_url, $post_id, $author_id]);
                    if (isset($existing_queue[$queue_key])) {
                        $this->log("Image already in queue: $image_url");
                        continue;
                    }

                    $write_file->fputcsv([$image_type, $image_url, $post_id, $author_id, 0]);
                    $this->log("Image added to queue: $image_url");
                }

                $write_file = null;
                return true;

            } catch (RuntimeException $e) {
                $this->log("Errore nella gestione del file di coda delle immagini: " . $e->getMessage());
                return false;
            }
        }

        private function get_images_ids_by_urls($image_urls)
        {
            global $wpdb;

            if (empty($image_urls)) {
                return [];
            }

            $filenames = array_map('basename', $image_urls);
            $placeholders = implode(',', array_fill(0, count($filenames), '%s'));

            $query = "
                SELECT ID, guid 
                FROM $wpdb->posts 
                WHERE guid IN ($placeholders)";

            $prepared_query = $wpdb->prepare($query, ...$filenames);
            $results = $wpdb->get_results($prepared_query);

            $url_to_id = [];
            foreach ($results as $result) {
                $url_to_id[basename($result->guid)] = $result->ID;
            }

            $final_mapping = [];
            foreach ($image_urls as $url) {
                $filename = basename($url);
                if (isset($url_to_id[$filename])) {
                    $final_mapping[$url] = $url_to_id[$filename];
                }
            }

            return $final_mapping;
        }

        public function process_image_queue()
        {
            $queue_file = $this->upload_dir . $this->image_queue_file;

            if ($this->get_progress_status($this->image_queue_file) === 'finished') {
                $this->log("Image processing finished. Removing schedule.");
                wp_clear_scheduled_hook('dokan_mods_process_image_ringraziamenti_queue');
                return;
            }

            if (!file_exists($queue_file)) {
                $this->log("Queue file does not exist");
                return;
            }

            if ($this->get_progress_status($this->image_queue_file) === 'ongoing') {
                $this->log("Image processing already in progress");
                return;
            }

            try {
                $file = new SplFileObject($queue_file, 'r');
                $file->setFlags(SplFileObject::READ_CSV);

                $file->seek(PHP_INT_MAX);
                $total_rows = $file->key();
                $file->rewind();

                if ($total_rows === 0) {
                    unlink($queue_file);
                    $this->log("Empty queue file removed");
                    return;
                }

                $this->set_progress_status($this->image_queue_file, 'ongoing');

                $progress = $this->get_progress($this->image_queue_file);
                $processed = $progress['processed'];
                $batch_count = 0;

                foreach ($file as $index => $data) {
                    if (empty($data) || count($data) < 5) continue;
                    if ($index < $processed) continue;
                    if ($batch_count >= $this->images_per_cron) break;

                    list($image_type, $image_url, $post_id, $author_id, $retry_count) = $data;

                    if ($this->download_and_attach_image($image_url, $post_id, $author_id)) {
                        $processed = $index + 1;
                        $batch_count++;
                        $this->update_progress($this->image_queue_file, $processed, $total_rows);
                    } else {
                        $retry_count++;
                    }
                }

                $file = null;

                if ($processed >= $total_rows) {
                    $this->set_progress_status($this->image_queue_file, 'finished');
                } else {
                    $this->set_progress_status($this->image_queue_file, 'completed');
                }

            } catch (Exception $e) {
                $this->log("Error: " . $e->getMessage());
                $this->set_progress_status($this->image_queue_file, 'error');
            }
        }

        private function download_and_attach_image($image_url, $post_id, $author_id)
        {
            try {
                $ch = curl_init($image_url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30
                ]);

                $image_data = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code !== 200 || !$image_data) {
                    throw new RuntimeException("Download failed with HTTP code: $http_code");
                }

                $upload_dir = wp_upload_dir();
                $filename = basename($image_url);
                $unique_filename = wp_unique_filename($upload_dir['path'], $filename);
                $upload_file = $upload_dir['path'] . '/' . $unique_filename;

                if (!file_put_contents($upload_file, $image_data)) {
                    throw new RuntimeException("Failed to save image");
                }

                $attachment = [
                    'post_mime_type' => wp_check_filetype($filename, null)['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_author' => $author_id ?: 1,
                ];

                $attach_id = wp_insert_attachment($attachment, $upload_file, $post_id);
                if (is_wp_error($attach_id)) {
                    throw new RuntimeException($attach_id->get_error_message());
                }

                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload_file);
                wp_update_attachment_metadata($attach_id, $attach_data);

                // Set the image as the ringraziamento image
                update_field('field_6731f274afd80', $attach_id, $post_id);

                $this->log("Image successfully attached: $image_url to post $post_id");
                return true;

            } catch (Exception $e) {
                $this->log("Error processing image $image_url: " . $e->getMessage());
                return false;
            }
        }

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