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
        private int $max_retries = 5;
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

            // Programma l'evento se non è già programmato
            if (!wp_next_scheduled('dokan_mods_process_image_queue')) {
                $this->log('Scheduling image processing event.');
                wp_schedule_event(time(), 'every_one_minute', 'dokan_mods_process_image_queue');
            } else {
                $this->log('Image processing event already scheduled.');
            }
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
                $this->log("Ram Usage start: " . $this->get_memory_usage());

                $this->process_single_record(
                    $data,
                    $header,
                    $existing_posts,
                    $existing_users,
                    $image_queue
                );
                $this->log("Ram Usage end: " . $this->get_memory_usage());
                $processed++;
                $this->update_progress($file_name, $processed, $total_rows);
            }

            // Cleanup
            unset($batch_data);
            $file = null; // Chiude il file

            // Processamento immagini
            if (!empty($image_queue)) {
                $this->image_queue($image_queue);
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

            $author_id = $existing_users[$data[$field_indexes['IdAccount']]] ?? 1;

            $post_id = wp_insert_post([
                'post_type' => 'annuncio-di-morte',
                'post_status' => $data[$field_indexes['Pubblicato']] == 1 ? 'publish' : 'draft',
                'post_title' => $data[$field_indexes['Nome']] . ' ' . $data[$field_indexes['Cognome']],
                'post_author' => $author_id,
                'post_date' => date('Y-m-d H:i:s', strtotime($data[$field_indexes['DataMorte']]))
            ]);

            if (!is_wp_error($post_id)) {
                $this->update_post_fields($post_id, $data, $field_indexes);

                // Gestione immagini
                if ($data[$field_indexes['Foto']]) {
                    $image_queue[] = ['profile_photo', $data[$field_indexes['Foto']], $post_id, $author_id];
                }

                if ($data[$field_indexes['ImmagineManifesto']]) {
                    $image_queue[] = ['manifesto_image', $data[$field_indexes['ImmagineManifesto']], $post_id, $author_id];
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
                'field_670d4e008fc23' => $data[$field_indexes['ID']],
                'field_6641d54cb4d9f' => $data[$field_indexes['Nome']],
                'field_6641d566b4da0' => $data[$field_indexes['Cognome']],
                'field_666ac79ed3c4e' => intval($data[$field_indexes['Anni']]),
                'field_6641d588b4da2' => $data[$field_indexes['DataMorte']],
                'field_6641d6d7cc550' => $data[$field_indexes['Testo']] ?: $data[$field_indexes['AltTesto']],
                'field_662ca58a35da3' => $this->format_luogo($data[$field_indexes['Luogo']]),
                'field_6641d694cc548' => $data[$field_indexes['DataFunerale']]
            ];

            foreach ($fields as $field_key => $value) {
                update_field($field_key, $value, $post_id);
            }
        }

        private function image_queue($image_queue)
        {
            // Prepariamo un array di tutti gli URL per fare una singola query SQL
            $image_urls = array_map(function ($image) {
                return 'https://necrologi.sciame.it/necrologi/' . $image[1];
            }, $image_queue);

            // Otteniamo tutti gli ID delle immagini esistenti con una singola query
            $existing_images = $this->get_images_ids_by_urls($image_urls);

            // Prepariamo il file per la coda una sola volta
            $queue_file = $this->upload_dir . $this->image_queue_file;

            try {
                // Leggiamo il contenuto esistente della coda
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

                // Apriamo il file per la scrittura
                $write_file = new SplFileObject($queue_file, 'a');

                foreach ($image_queue as $image) {
                    $image_type = $image[0];
                    $image_path = $image[1];
                    $post_id = $image[2];
                    $author_id = $image[3];

                    if($image_path == ''){
                        continue;
                    }
                    $image_url = 'https://necrologi.sciame.it/necrologi/' . $image_path;

                    // Controlliamo se l'immagine esiste già nella libreria
                    $existing_image_id = isset($existing_images[$image_url]) ? $existing_images[$image_url] : null;

                    if ($existing_image_id) {
                        $this->log("Image already exists in media library: $image_url");
                        // Aggiorniamo i campi necessari
                        if ($image_type === 'profile_photo') {
                            update_field('field_6641d593b4da3', $existing_image_id, $post_id);
                        } elseif ($image_type === 'manifesto_image') {
                            update_field('field_6641d6eecc551', $existing_image_id, $post_id);
                        }
                        continue;
                    }

                    // Controlliamo se l'immagine è già in coda
                    $queue_key = implode('|', [$image_type, $image_url, $post_id, $author_id]);
                    if (isset($existing_queue[$queue_key])) {
                        $this->log("Image already in queue: $image_url");
                        continue;
                    }

                    // Aggiungiamo alla coda
                    $write_file->fputcsv([$image_type, $image_url, $post_id, $author_id, 0]);
                    $this->log("Image added to queue: $image_url");
                }

                // Chiudiamo il file
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

            // Prepariamo i filename per la ricerca
            $filenames = array_map('basename', $image_urls);

            // Costruiamo la query dinamicamente con un'operazione di corrispondenza esatta
            $placeholders = implode(',', array_fill(0, count($filenames), '%s'));

            // Eseguiamo la query
            $query = "
                SELECT ID, guid 
                FROM $wpdb->posts 
                WHERE guid IN ($placeholders)";

            // Prepariamo i parametri per la query
            $prepared_query = $wpdb->prepare($query, ...$filenames);
            $results = $wpdb->get_results($prepared_query);

            // Creiamo un array associativo url => ID
            $url_to_id = [];
            foreach ($results as $result) {
                $url_to_id[basename($result->guid)] = $result->ID;
            }

            // Mappiamo gli URL originali agli ID
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
            $this->log("Ram Usage start: " . $this->get_memory_usage());

            if ($this->get_progress_status($this->image_queue_file) === 'finished') {
                $this->log("Image processing finished. Removing schedule.");
                wp_clear_scheduled_hook('dokan_mods_process_image_ricorrenze_queue');
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

                // Conta il numero totale di righe
                $file->seek(PHP_INT_MAX);
                $total_rows = $file->key();
                $file->rewind();

                if ($total_rows === 0) {
                    unlink($queue_file);
                    $this->log("Empty queue file removed");
                    return;
                }

                $this->set_progress_status($this->image_queue_file, 'ongoing');

                // Inizializza il tracking del progresso
                $progress = $this->get_progress($this->image_queue_file);
                $processed = $progress['processed'];

                $batch_count = 0;  // Contatore per il numero di immagini processate in questo batch

                foreach ($file as $index => $data) {
                    $this->log("Ram Usage foreach: " . $this->get_memory_usage());

                    // Se la riga è vuota o mancano campi, saltala
                    if (empty($data) || count($data) < 5) continue;

                    // Salta le righe già processate
                    if ($index < $processed) continue;

                    // Verifica se abbiamo raggiunto il limite del batch
                    if ($batch_count >= $this->images_per_cron) {
                        break; // Esci dal ciclo, il batch è completo
                    }

                    list($image_type, $image_url, $post_id, $author_id, $retry_count) = $data;

                    // Prova a scaricare e allegare l'immagine
                    if ($this->download_and_attach_image($image_type, $image_url, $post_id, $author_id)) {
                        $processed = $index + 1;  // Aggiorna il numero di righe processate
                        $batch_count++;  // Incrementa il contatore del batch
                        $this->update_progress($this->image_queue_file, $processed, $total_rows);  // Aggiorna lo stato del progresso
                    } else {
                        $retry_count++;
                        // Non riscrivi la riga qui, il file rimane intatto e puoi tentare di nuovo nei batch successivi
                    }
                }

                // Chiudi il file
                $file = null;

                // Se abbiamo completato tutte le righe, possiamo segnare il progresso come "finished"
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

        private function download_and_attach_image($image_type, $image_url, $post_id, $author_id)
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
                $filename = wp_unique_filename($upload_dir['path'], basename($image_url));
                $upload_file = $upload_dir['path'] . '/' . $filename;

                if (!file_put_contents($upload_file, $image_data)) {
                    throw new RuntimeException("Failed to save image");
                }

                $attachment = [
                    'post_mime_type' => wp_check_filetype($filename, null)['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_status' => 'inherit',
                    'post_author' => $author_id ?: 1,
                ];

                $attach_id = wp_insert_attachment($attachment, $upload_file, $post_id);
                if (is_wp_error($attach_id)) {
                    throw new RuntimeException($attach_id->get_error_message());
                }

                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata(
                    $attach_id,
                    wp_generate_attachment_metadata($attach_id, $upload_file)
                );

                update_field(
                    $image_type === 'profile_photo' ? 'field_6641d593b4da3' : 'field_6641d6eecc551',
                    $attach_id,
                    $post_id
                );

                return true;

            } catch (Exception $e) {
                $this->log("Error processing $image_url: " . $e->getMessage());
                return false;
            }
        }



        // New helper function to get post by old ID
        protected function get_existing_posts_by_old_ids($old_ids, $post_types = ['annuncio-di-morte'])
        {
            // Chiama il metodo della classe base
            return parent::get_existing_posts_by_old_ids($old_ids, $post_types);
        }


        private function get_existing_users_by_old_ids($old_ids)
        {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($old_ids), '%s'));
            $sql = "
                SELECT um.user_id, um.meta_value
                FROM {$wpdb->usermeta} um
                WHERE um.meta_key = 'id_old' AND um.meta_value IN ($placeholders)
            ";
            $prepared_sql = $wpdb->prepare($sql, $old_ids);
            $results = $wpdb->get_results($prepared_sql, ARRAY_A);
            $existing_users = [];
            foreach ($results as $row) {
                $existing_users[$row['meta_value']] = $row['user_id'];
            }
            return $existing_users;
        }


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



    }
}