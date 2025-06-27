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
        private int $micro_batch_size = 20;
        private int $query_chunk_size = 100;
        private array $user_cache = [];
        private array $post_cache = [];
        private int $checkpoint_interval = 50;
        private int $progress_update_interval = 10;
        
        //private string $image_cron_hook = 'dokan_mods_download_images';

        private string $image_queue_file = 'image_download_queue.csv';
        private int $max_retries = 5;
        private int $images_per_cron = 1000; // Aumentato per migliori prestazioni
        private int $max_concurrent_downloads = 5; // Download paralleli

        public function __construct(String $upload_dir, String $progress_file, String $log_file, Int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);
            
            $this->memory_limit_mb = 256;
            $this->max_execution_time = 30;

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
            $this->log("=== INIZIO BATCH MIGRATION per $file_name ===");
            $this->set_progress_status($file_name, 'ongoing');

            if(!$file = $this->load_file($file_name)){
                $this->log("ERRORE: Impossibile caricare file $file_name");
                return false;
            }
            $this->log("File $file_name caricato con successo");

            $progress = $this->first_call_check($file);
            if (!$progress) {
                $this->log("ERRORE: first_call_check fallito per $file_name");
                return false;
            }
            $this->log("first_call_check completato - progresso: " . json_encode($progress));

            $processed = $progress['processed'];
            $total_rows = $progress['total'];

            // Controllo sanità: non processare oltre il totale
            if ($processed >= $total_rows) {
                $this->log("Tutti i record sono già stati processati: $processed di $total_rows");
                $this->set_progress_status($file_name, 'finished');
                $this->schedule_image_processing();
                return true;
            }

            $header = $file->fgetcsv();             // Leggi l'header

            // IMPORTANTE: seek deve considerare che abbiamo già letto l'header
            // Se processed = 0, siamo già alla posizione 1 dopo aver letto l'header
            if ($processed > 0) {
                $file->seek($processed + 1);        // +1 per saltare l'header
            }
            // Se processed = 0, non fare seek perché siamo già dopo l'header

            $batch_data = [];
            $batch_user_old_ids = [];
            $batch_post_old_ids = [];
            $id_account_index = array_search('IdAccount', $header);

            // Raccolta dati batch
            $rows_to_process = min($this->batch_size, $total_rows - $processed);
            while (!$file->eof() && count($batch_data) < $rows_to_process) {
                $data = $file->fgetcsv();
                if (empty($data) || $data === [null]) continue;

                // Ulteriore controllo per non superare il totale
                if ($processed + count($batch_data) >= $total_rows) {
                    $this->log("Raggiunto il limite totale di righe da processare");
                    break;
                }

                $batch_data[] = $data;
                $batch_user_old_ids[] = $data[$id_account_index];
                $batch_post_old_ids[] = $data[array_search('ID', $header)];
            }

            // Ottimizzazione: pre-fetch degli utenti esistenti con chunking
            $existing_posts = $this->get_existing_posts_by_old_ids_chunked(array_unique($batch_post_old_ids));
            $existing_users = $this->get_existing_users_by_old_ids(array_unique($batch_user_old_ids));
            unset($batch_user_old_ids); // Libera memoria
            unset($batch_post_old_ids); // Libera memoria

            $image_queue = [];

            // Process batch con micro-batching
            $batch_acf_updates = [];
            $micro_batches = array_chunk($batch_data, $this->micro_batch_size);
            
            foreach ($micro_batches as $micro_batch) {
                foreach ($micro_batch as $data) {
                    $this->process_single_record(
                        $data,
                        $header,
                        $existing_posts,
                        $existing_users,
                        $image_queue,
                        $batch_acf_updates
                    );
                    $processed++;
                    
                    if ($processed % $this->progress_update_interval === 0) {
                        $this->update_progress($file_name, $processed, $total_rows);
                    }
                    
                    if ($processed % $this->checkpoint_interval === 0) {
                        $this->log("Checkpoint: processati $processed di $total_rows record");
                        wp_cache_flush();
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                }
                
                // Batch insert ACF fields per questo micro-batch
                if (!empty($batch_acf_updates)) {
                    $this->batch_insert_acf_fields($batch_acf_updates);
                    $batch_acf_updates = [];
                }
                
                // Memory cleanup dopo ogni micro-batch
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
            
            // Final batch insert per eventuali ACF rimanenti
            if (!empty($batch_acf_updates)) {
                $this->batch_insert_acf_fields($batch_acf_updates);
            }

            // Cleanup
            unset($batch_data);
            $file = null; // Chiude il file

            // Solo aggiunta alla coda per processamento asincrono
            if (!empty($image_queue)) {
                $image_count = count($image_queue);
                $this->log("Aggiungendo $image_count immagini alla coda per download asincrono");
                
                $queue_start = microtime(true);
                $this->image_queue($image_queue);
                $queue_time = microtime(true) - $queue_start;
                $this->log("Immagini aggiunte alla coda in {$queue_time}s - saranno processate dal cron job dedicato");
                
                // Assicurati che il cron job sia schedulato
                if (!wp_next_scheduled('dokan_mods_process_image_queue')) {
                    $this->schedule_image_processing();
                }
            }
            unset($image_queue);

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");

            if ($processed >= $total_rows) {
                $this->set_progress_status($file_name, 'finished');
                $this->schedule_image_processing();
                return true;
            }

            $smart_status = $this->set_progress_status_smart($file_name, 'completed');
            
            if ($smart_status === 'auto_continue') {
                $this->log("Auto-continuazione immediata possibile per $file_name");
                return 'auto_continue';
            }

            return false;
        }



        private function process_single_record($data, $header, $existing_posts, $existing_users, &$image_queue, &$batch_acf_updates = null)
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
                if ($batch_acf_updates !== null) {
                    $this->prepare_acf_batch_update($post_id, $data, $field_indexes, $batch_acf_updates);
                } else {
                    $this->update_post_fields($post_id, $data, $field_indexes);
                }

                // Gestione immagini - validazione prima di aggiungere alla coda
                if (!empty($data[$field_indexes['Foto']]) && trim($data[$field_indexes['Foto']]) !== '') {
                    $image_queue[] = ['profile_photo', trim($data[$field_indexes['Foto']]), $post_id, $author_id];
                }

                if (!empty($data[$field_indexes['ImmagineManifesto']]) && trim($data[$field_indexes['ImmagineManifesto']]) !== '') {
                    $image_queue[] = ['manifesto_image', trim($data[$field_indexes['ImmagineManifesto']]), $post_id, $author_id];
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

        private function image_queue($image_queue)
        {
            $this->log("=== INIZIO IMAGE QUEUE PROCESSING ===");
            $queue_count = count($image_queue);
            $this->log("Processing $queue_count immagini per la coda");
            
            // Prepariamo un array di tutti gli URL per fare una singola query SQL
            $url_start = microtime(true);
            $image_urls = [];
            foreach ($image_queue as $image) {
                if (!empty($image[1])) {
                    $image_urls[] = 'https://necrologi.sciame.it/necrologi/' . trim($image[1]);
                }
            }
            $url_time = microtime(true) - $url_start;
            $this->log("URL mapping completato in {$url_time}s per " . count($image_urls) . " URLs validi");

            // Otteniamo tutti gli ID delle immagini esistenti con una singola query
            $query_start = microtime(true);
            $existing_images = $this->get_images_ids_by_urls($image_urls);
            $query_time = microtime(true) - $query_start;
            $this->log("Query immagini esistenti completata in {$query_time}s - trovate " . count($existing_images) . " immagini");

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

                // Buffer per scritture batch
                $new_entries = [];
                $updated_count = 0;

                foreach ($image_queue as $image) {
                    $image_type = $image[0];
                    $image_path = $image[1];
                    $post_id = $image[2];
                    $author_id = $image[3];

                    // Validazione del path immagine
                    if(empty($image_path) || trim($image_path) === ''){
                        continue;
                    }
                    
                    // Costruisci URL completo
                    $image_url = 'https://necrologi.sciame.it/necrologi/' . trim($image_path);

                    // Controlliamo se l'immagine esiste già nella libreria
                    $existing_image_id = isset($existing_images[$image_url]) ? $existing_images[$image_url] : null;

                    if ($existing_image_id) {
                        // Aggiorniamo i campi necessari
                        if ($image_type === 'profile_photo') {
                            update_field('fotografia', $existing_image_id, $post_id);
                        } elseif ($image_type === 'manifesto_image') {
                            update_field('immagine_annuncio_di_morte', $existing_image_id, $post_id);
                        }
                        $updated_count++;
                        continue;
                    }

                    // Controlliamo se l'immagine è già in coda
                    $queue_key = implode('|', [$image_type, $image_url, $post_id, $author_id]);
                    if (isset($existing_queue[$queue_key])) {
                        continue;
                    }

                    // Aggiungiamo al buffer
                    $new_entries[] = [$image_type, $image_url, $post_id, $author_id, 0];
                    $existing_queue[$queue_key] = true; // Aggiorna cache locale
                }
                
                // Scrittura batch di tutte le nuove entry
                if (!empty($new_entries)) {
                    $write_file = new SplFileObject($queue_file, 'a');
                    foreach ($new_entries as $entry) {
                        $write_file->fputcsv($entry);
                    }
                    $write_file = null;
                    $this->log("Added " . count($new_entries) . " new images to queue");
                }
                
                if ($updated_count > 0) {
                    $this->log("Updated $updated_count existing images in media library");
                }
                
                $this->log("=== FINE IMAGE QUEUE PROCESSING - SUCCESS ===");

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

            // Query ottimizzata per trovare immagini esistenti
            // Usa LIKE per gestire differenze nei percorsi
            $url_mapping = [];
            $chunks = array_chunk($image_urls, 100); // Processa in chunks per evitare query troppo grandi
            
            foreach ($chunks as $chunk) {
                $like_conditions = [];
                $values = [];
                
                foreach ($chunk as $url) {
                    $filename = basename($url);
                    $like_conditions[] = "guid LIKE %s";
                    $values[] = '%' . $wpdb->esc_like($filename);
                }
                
                if (!empty($like_conditions)) {
                    $query = "SELECT ID, guid FROM $wpdb->posts 
                             WHERE post_type = 'attachment' 
                             AND (" . implode(' OR ', $like_conditions) . ")";
                    
                    $prepared_query = $wpdb->prepare($query, $values);
                    $results = $wpdb->get_results($prepared_query);
                    
                    foreach ($results as $result) {
                        foreach ($chunk as $url) {
                            if (basename($url) === basename($result->guid)) {
                                $url_mapping[$url] = $result->ID;
                                break;
                            }
                        }
                    }
                }
            }
            
            $final_mapping = $url_mapping;

            return $final_mapping;
        }

        public function process_image_queue()
        {
            $queue_file = $this->upload_dir . $this->image_queue_file;

            if ($this->get_progress_status($this->image_queue_file) === 'finished') {
                $this->log("Image processing finished. Removing schedule.");
                wp_clear_scheduled_hook('dokan_mods_process_image_queue');
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

                // Raccogli batch di immagini da processare
                $batch_images = [];
                $batch_indexes = [];
                
                foreach ($file as $index => $data) {
                    // Se la riga è vuota o mancano campi, saltala
                    if (empty($data) || count($data) < 5) continue;

                    // Salta le righe già processate
                    if ($index < $processed) continue;

                    // Verifica se abbiamo raggiunto il limite del batch
                    if (count($batch_images) >= $this->images_per_cron) {
                        break;
                    }

                    list($image_type, $image_url, $post_id, $author_id, $retry_count) = $data;
                    
                    // Skip se troppi retry
                    if ($retry_count >= $this->max_retries) {
                        $this->log("Skipping image after $retry_count retries: $image_url");
                        $processed = $index + 1;
                        continue;
                    }
                    
                    $batch_images[] = [
                        'type' => $image_type,
                        'url' => $image_url,
                        'post_id' => $post_id,
                        'author_id' => $author_id,
                        'retry_count' => $retry_count,
                        'index' => $index
                    ];
                    $batch_indexes[] = $index;
                }

                $file = null;

                // Processa il batch con download paralleli
                if (!empty($batch_images)) {
                    $this->log("Processing batch of " . count($batch_images) . " images");
                    $results = $this->download_images_batch($batch_images);
                    
                    // Aggiorna il progresso basato sui risultati
                    $last_processed_index = $processed;
                    foreach ($results as $index => $success) {
                        if ($success) {
                            $last_processed_index = max($last_processed_index, $batch_images[$index]['index'] + 1);
                        }
                    }
                    
                    $processed = $last_processed_index;
                    $this->update_progress($this->image_queue_file, $processed, $total_rows);
                    
                    // Aggiorna il file della coda con i retry per le immagini fallite
                    $this->update_queue_file_with_retries($queue_file, $batch_images, $results);
                }

                // Se abbiamo completato tutte le righe, segna come finished
                if ($processed >= $total_rows) {
                    $this->set_progress_status($this->image_queue_file, 'finished');
                    $this->log("Image queue processing completed. Total processed: $processed");
                } else {
                    $this->set_progress_status($this->image_queue_file, 'completed');
                    $remaining = $total_rows - $processed;
                    $this->log("Batch completed. Processed: $processed, Remaining: $remaining");
                }

            } catch (Exception $e) {
                $this->log("Error in process_image_queue: " . $e->getMessage());
                $this->set_progress_status($this->image_queue_file, 'error');
            }
        }




        private function get_existing_posts_by_old_ids_chunked($old_ids, $post_types = ['annuncio-di-morte'])
        {
            if (empty($old_ids)) {
                return [];
            }

            $all_results = [];
            $chunks = array_chunk($old_ids, $this->query_chunk_size);
            
            foreach ($chunks as $chunk) {
                $chunk_results = parent::get_existing_posts_by_old_ids($chunk, $post_types);
                $all_results = array_merge($all_results, $chunk_results);
                
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
            
            return $all_results;
        }

        private function prepare_acf_batch_update($post_id, $data, $field_indexes, &$batch_acf_updates)
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
                $batch_acf_updates[] = [
                    'post_id' => $post_id,
                    'meta_key' => $field_key,
                    'meta_value' => $value
                ];
            }
        }

        private function batch_insert_acf_fields($batch_updates)
        {
            if (empty($batch_updates)) {
                return;
            }

            global $wpdb;
            
            $values = [];
            $placeholders = [];
            
            foreach ($batch_updates as $update) {
                $values[] = $update['post_id'];
                $values[] = $update['meta_key'];
                $values[] = maybe_serialize($update['meta_value']);
                $placeholders[] = '(%d, %s, %s)';
            }
            
            $sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . 
                   implode(', ', $placeholders) . 
                   " ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)";
            
            $prepared_sql = $wpdb->prepare($sql, $values);
            $wpdb->query($prepared_sql);
        }

        private function download_images_batch($batch_images)
        {
            if (empty($batch_images)) {
                return [];
            }

            $results = array_fill(0, count($batch_images), false);
            $multi_handle = curl_multi_init();
            $curl_handles = [];
            
            // Configurazione ottimizzata per performance
            curl_multi_setopt($multi_handle, CURLMOPT_MAXCONNECTS, $this->max_concurrent_downloads);
            curl_multi_setopt($multi_handle, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
            
            // Inizializza i handle cURL
            foreach ($batch_images as $index => $image) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $image['url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT => 'WordPress/' . get_bloginfo('version'),
                    CURLOPT_ENCODING => 'gzip, deflate',
                    CURLOPT_MAXREDIRS => 5
                ]);
                
                curl_multi_add_handle($multi_handle, $ch);
                $curl_handles[$index] = $ch;
            }
            
            // Esegui download paralleli
            $running = 0;
            do {
                $status = curl_multi_exec($multi_handle, $running);
                if ($running) {
                    curl_multi_select($multi_handle, 1.0);
                }
                
                // Processa handle completati
                while ($info = curl_multi_info_read($multi_handle)) {
                    $ch = $info['handle'];
                    $index = array_search($ch, $curl_handles, true);
                    
                    if ($index !== false) {
                        if ($info['result'] === CURLE_OK) {
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            if ($http_code === 200) {
                                $content = curl_multi_getcontent($ch);
                                if ($content && strlen($content) > 0) {
                                    $results[$index] = $this->save_downloaded_image(
                                        $content,
                                        $batch_images[$index]
                                    );
                                } else {
                                    $this->log("Empty content for: " . $batch_images[$index]['url']);
                                }
                            } else {
                                $this->log("HTTP $http_code for: " . $batch_images[$index]['url']);
                            }
                        } else {
                            $error = curl_error($ch);
                            $this->log("cURL error for " . $batch_images[$index]['url'] . ": $error");
                        }
                        
                        curl_multi_remove_handle($multi_handle, $ch);
                        curl_close($ch);
                    }
                }
            } while ($running > 0 || $status === CURLM_CALL_MULTI_PERFORM);
            
            curl_multi_close($multi_handle);
            
            return $results;
        }

        private function save_downloaded_image($image_data, $image_info)
        {
            try {
                $upload_dir = wp_upload_dir();
                $filename = wp_unique_filename($upload_dir['path'], basename($image_info['url']));
                $upload_file = $upload_dir['path'] . '/' . $filename;
                
                if (!file_put_contents($upload_file, $image_data)) {
                    throw new RuntimeException("Failed to save image file");
                }
                
                // Verifica che il file sia un'immagine valida
                $file_type = wp_check_filetype($filename, null);
                if (!in_array($file_type['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
                    unlink($upload_file);
                    throw new RuntimeException("Invalid image type: " . $file_type['type']);
                }
                
                $attachment = [
                    'post_mime_type' => $file_type['type'],
                    'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                    'post_status' => 'inherit',
                    'post_author' => $image_info['author_id'] ?: 1,
                ];
                
                $attach_id = wp_insert_attachment($attachment, $upload_file, $image_info['post_id']);
                if (is_wp_error($attach_id)) {
                    unlink($upload_file);
                    throw new RuntimeException($attach_id->get_error_message());
                }
                
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload_file);
                wp_update_attachment_metadata($attach_id, $attach_data);
                
                // Aggiorna il campo ACF appropriato
                if ($image_info['type'] === 'profile_photo') {
                    update_field('fotografia', $attach_id, $image_info['post_id']);
                } elseif ($image_info['type'] === 'manifesto_image') {
                    update_field('immagine_annuncio_di_morte', $attach_id, $image_info['post_id']);
                }
                
                $this->log("Successfully saved image: $filename for post " . $image_info['post_id']);
                return true;
                
            } catch (Exception $e) {
                $this->log("Error saving image: " . $e->getMessage());
                return false;
            }
        }

        private function update_queue_file_with_retries($queue_file, $batch_images, $results)
        {
            try {
                // Leggi tutto il file in memoria
                $all_rows = [];
                $file = new SplFileObject($queue_file, 'r');
                $file->setFlags(SplFileObject::READ_CSV);
                
                foreach ($file as $row) {
                    if (!empty($row) && count($row) >= 5) {
                        $all_rows[] = $row;
                    }
                }
                $file = null;
                
                // Aggiorna i retry count per le immagini fallite
                foreach ($batch_images as $index => $image) {
                    if (!$results[$index] && isset($all_rows[$image['index']])) {
                        $all_rows[$image['index']][4] = $image['retry_count'] + 1;
                    }
                }
                
                // Riscrivi il file
                $file = new SplFileObject($queue_file, 'w');
                foreach ($all_rows as $row) {
                    $file->fputcsv($row);
                }
                
            } catch (Exception $e) {
                $this->log("Error updating queue file: " . $e->getMessage());
            }
        }

        // New helper function to get post by old ID
        protected function get_existing_posts_by_old_ids($old_ids, $post_types = ['annuncio-di-morte'])
        {
            // Chiama il metodo della classe base
            return parent::get_existing_posts_by_old_ids($old_ids, $post_types);
        }


/*        private function get_existing_users_by_old_ids($old_ids)
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
        }*/


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