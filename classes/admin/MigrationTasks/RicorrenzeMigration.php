<?php

namespace Dokan_Mods\Migration_Tasks;

use Exception;
use RuntimeException;
use SplFileObject;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\RicorrenzeMigration')) {
    class RicorrenzeMigration extends MigrationTasks
    {

        private string $image_cron_hook = 'dokan_mods_download_images';


        private string $image_queue_file = 'image_download_queue_ricorrenze.csv';
        private int $max_retries = 5;
        private int $images_per_cron = 500;

        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);

            add_action('dokan_mods_process_image_ricorrenze_queue', [$this, 'process_image_queue']);

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
            if (!wp_next_scheduled('dokan_mods_process_image_ricorrenze_queue')) {
                $this->log('Scheduling image processing event.');
                wp_schedule_event(time(), 'every_one_minute', 'dokan_mods_process_image_ricorrenze_queue');
            } else {
                $this->log('Image processing event already scheduled.');
            }
        }


        public function migrate_ricorrenze_batch($file_name)
        {
            $progress_status = $this->get_progress_status($file_name);

            // Se il processo è già completato completamente (tutti i batch)
            if ($progress_status == 'finished') {
                $this->schedule_image_processing();
                $this->log("Il file $file_name è già stato processato completamente.");
                return true;
            }

            // Se lo stato è 'not_started', eseguire la manipolazione e avviare il batch processing
            if ($progress_status == 'not_started') {
                // Se il file manipulation_done.txt esiste, cancellarlo per ripetere la manipolazione
                if (file_exists($this->upload_dir . 'manipulation_done.txt')) {
                    unlink($this->upload_dir . 'manipulation_done.txt');
                    $this->log("Resetting manipulation due to new migration run.");
                }

                $start_time = microtime(true);
                $this->set_progress_status($file_name, 'ongoing');

                // Esegui la manipolazione dei dati
                if (!($this->manipola_dati($this->upload_dir . $file_name))) {
                    $this->log("Errore nella manipolazione dei dati");
                    return false;
                } else {
                    file_put_contents($this->upload_dir . 'manipulation_done.txt', '');
                }
            }

            // Se lo stato è 'completed', significa che abbiamo finito un batch ma non l'intero processo,
            // quindi possiamo continuare con il prossimo batch
            if ($progress_status == 'completed' || $progress_status == 'not_started') {
                // Iniziare il batch processing
                if (!$file = $this->load_file($file_name)) {
                    return false;
                }

                if (!$progress = $this->first_call_check($file, $file_name)) {
                    return false;
                }

                $processed = $progress['processed'];
                $total_rows = $progress['total'];

                $header = $file->fgetcsv(); // Leggi l'header

                // Riposizionamento lettura CSV per continuare dal progresso attuale
                $file->seek($processed); // Salta le righe già processate

                $batch_data = [];
                $batch_post_old_ids = [];

                // Raccolta dei dati del batch corrente
                while (!$file->eof() && count($batch_data) < $this->batch_size) {
                    $data = $file->fgetcsv();
                    if (empty($data)) continue;

                    $batch_data[] = $data;
                    $batch_post_old_ids[] = $data[array_search('ID', $header)];
                }

                // Controllo post esistenti
                $existing_posts = $this->get_existing_posts_by_old_ids($batch_post_old_ids);
                unset($batch_post_old_ids);

                foreach ($batch_data as $data) {
                    $this->process_single_record($data, $header, $existing_posts, $image_queue);
                    $processed++;
                    $this->update_progress($file_name, $processed, $total_rows);
                }

                unset($batch_data);
                $file = null;

                if (!empty($image_queue)) {
                    $this->image_queue($image_queue);
                }
                unset($image_queue);

                $end_time = microtime(true);
                $execution_time = $end_time - $start_time;
                $this->log("Batch execution time: {$execution_time} seconds");

                // Se abbiamo finito tutte le righe
                if ($processed >= $total_rows) {
                    $this->set_progress_status($file_name, 'finished');
                    $this->schedule_image_processing();
                } else {
                    $this->set_progress_status($file_name, 'completed');
                }

                return $processed >= $total_rows;
            }

            return false; // Se lo stato non rientra in nessuno dei casi previsti
        }


        private function process_single_record($data, $header, $existing_posts, &$image_queue)
        {

            static $field_indexes = null;

            // Cache degli indici dei campi
            if ($field_indexes === null) {
                $field_indexes = [
                    'ID' => array_search('ID', $header),
                    'Tipo' => array_search('Tipo', $header),
                    'IdNecrologio' => array_search('IdNecrologio', $header),
                    'Data' => array_search('Data', $header),
                    'Foto' => array_search('Foto', $header),
                    'Testo' => array_search('Testo', $header),
                    'Pubblicato' => array_search('Pubblicato', $header),
                    'Anni' => array_search('Anni', $header),
                ];
            }

            $id = $data[$field_indexes['ID']];

            // Skip se esiste già
            if (isset($existing_posts[$id])) {
                $this->log("Ricorrenza già esistente: ID {$existing_posts[$id]}");
                return;
            }

            $tipo = $data[array_search('Tipo', $header)];
            if ($tipo == 0) {
                $this->log("Tipo non valido per ID: $id");
                return; // Salta questo record
            }


            $necrologio = $this->get_post_by_old_id($data[$field_indexes['IdNecrologio']]);

            if (!$necrologio) {
                $this->log("Annuncio di morte non trovato per ID: ". $data[$field_indexes['IdNecrologio']]);
                return;
            }

            // Recupero città
            $citta = get_field('citta', $necrologio->ID);

            // Determina tipo di post e autore
            $post_type = $tipo == 1 ? 'trigesimo' : 'anniversario';
            $author_id = $necrologio->post_author;

            // Calcolo della data di pubblicazione
            $pub_date = ($tipo == 1) ? date('Y-m-d H:i:s', strtotime($necrologio->post_date . ' +30 days')) :
                date('Y-m-d H:i:s', strtotime($necrologio->post_date . ' +' . $data[$field_indexes['Anni']] . ' year'));

            // Creazione del nuovo post
            $post_id = wp_insert_post(array(
                'post_type' => $post_type,
                'post_status' => $data[$field_indexes['Pubblicato']] == 1 ? 'publish' : 'draft',
                'post_title' => get_the_title($necrologio->ID),
                'post_author' => $author_id,
                'post_date' => $pub_date,
            ));

            if (!is_wp_error($post_id)) {
                // Aggiornamento campi
                update_field('annuncio_di_morte', $necrologio->ID, $post_id);

                if ($tipo == 1) { // Trigesimo
                    $date_value = $data[$field_indexes['Data']];

                    if (!empty($data[$field_indexes['Data']]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$field_indexes['Data']])) {
                        $date_value = str_replace('-', '', $data[$field_indexes['Data']]);
                    }

                    // Verifica se esiste il meta ACF associato
                    $existing_key = get_post_meta($post_id, '_trigesimo_data', true);

                    if (!empty($date_value)) {
                        update_post_meta($post_id, 'trigesimo_data', $date_value);

                        if (empty($existing_key) || $existing_key !== 'field_6734d2e598b99') {
                            update_post_meta($post_id, '_trigesimo_data', 'field_6734d2e598b99');
                        }
                    }

                    update_field('testo_annuncio_trigesimo', $data[$field_indexes['Testo']], $post_id);
                } else { // Anniversario
                    $date_value = $data[$field_indexes['Data']];

                    if (!empty($data[$field_indexes['Data']]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$field_indexes['Data']])) {
                        $date_value = str_replace('-', '', $data[$field_indexes['Data']]);
                    }

                    // Verifica se esiste il meta ACF associato
                    $existing_key = get_post_meta($post_id, '_anniversario_data', true);

                    if (!empty($date_value)) {
                        update_post_meta($post_id, 'anniversario_data', $date_value);

                        if (empty($existing_key) || $existing_key !== 'field_665ec95bca23d') {
                            update_post_meta($post_id, '_anniversario_data', 'field_665ec95bca23d');
                        }
                    }

                    update_field('testo_annuncio_anniversario', $data[$field_indexes['Testo']], $post_id);
                    update_field('anniversario_n_anniversario', $data[$field_indexes['Anni']], $post_id);
                }

                if ($citta) {
                    update_field('citta', $citta, $post_id);
                }

                // Gestione immagine
                if ($data[$field_indexes['Foto']]) {
                        $image_type = $tipo == 1 ? 'trigesimo' : 'anniversario';
                        $image_queue[] = [$image_type, $data[$field_indexes['Foto']], $post_id, $author_id];
                }

                $this->log("Ricorrenza creata: ID $post_id, Tipo: $post_type");
            } else {
                $this->log("Errore nella creazione della ricorrenza: " . $post_id->get_error_message());
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

                    if ($image_path == '') {
                        continue;
                    }
                    $image_url = 'https://necrologi.sciame.it/necrologi/' . $image_path;

                    // Controlliamo se l'immagine esiste già nella libreria
                    $existing_image_id = $existing_images[$image_url] ?? null;

                    if ($existing_image_id) {
                        $this->log("Image already exists in media library: $image_url");
                        // Aggiorniamo i campi necessari
                        if ($image_type === 'trigesimo') {
                            update_field('immagine_annuncio_trigesimo', $existing_image_id, $post_id);
                        } elseif ($image_type === 'anniversario') {
                            update_field('immagine_annuncio_anniversario', $existing_image_id, $post_id);
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

        protected function get_existing_posts_by_old_ids($old_ids, $post_types = ['trigesimo', 'anniversario'])
        {
            // Chiama il metodo della classe base con i tuoi post types
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


        public function process_image_queue()
        {

            $queue_file = $this->upload_dir . $this->image_queue_file;


            //if progress status is finished remove the schedule
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

                    // Se la riga è vuota o mancano campi, saltala
                    if (empty($data) || count($data) < 5) continue;

                    // Salta le righe già processate
                    if ($index < $processed) continue;

                    // Verifica se abbiamo raggiunto il limite del batch
                    if ($batch_count >= $this->images_per_cron) {
                        break; // Esci dal ciclo, il batch è completo
                    }

                    list($image_type, $image_url, $post_id, $author_id, $retry_count) = $data;

                    // Scarica e carica l'immagine
                    if($this->download_and_upload_image($image_type,$image_url, $post_id, $author_id)){
                        $processed = $index + 1;  // Aggiorna il numero di righe processate
                        $batch_count++;  // Incrementa il contatore del batch
                        $this->update_progress($this->image_queue_file, $processed, $total_rows);  // Aggiorna lo stato del progresso
                    }else{
                        $retry_count++;
                    }
                }

                $file = null;

                // Se abbiamo completato tutte le righe, possiamo segnare il progresso come "finished"
                if ($processed >= $total_rows) {
                    $this->set_progress_status($this->image_queue_file, 'finished');
                } else {
                    $this->set_progress_status($this->image_queue_file, 'completed');
                }

            }
        catch
            (Exception $e) {
                $this->log("Error: " . $e->getMessage());
                $this->set_progress_status($this->image_queue_file, 'error');
            }
        }


        private function download_and_upload_image($image_type, $image_url, $post_id, $author_id)
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
                    throw new RuntimeException("Download fallito con codice HTTP: $http_code");
                }

                // Preparazione per l'upload
                $upload_dir = wp_upload_dir();
                $filename = basename($image_url);
                $unique_filename = wp_unique_filename($upload_dir['path'], $filename);
                $upload_file = $upload_dir['path'] . '/' . $unique_filename;

                if (!file_put_contents($upload_file, $image_data)) {
                    throw new RuntimeException("Impossibile salvare l'immagine: $upload_file");
                }

                // Creazione dell'attachment
                $wp_filetype = wp_check_filetype($filename, null);
                $attachment = [
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_author' => $author_id ?: 1,
                ];

                // Inserimento dell'attachment
                $attach_id = wp_insert_attachment($attachment, $upload_file, $post_id);
                if (is_wp_error($attach_id)) {
                    throw new RuntimeException($attach_id->get_error_message());
                }

                // Generazione dei metadata dell'immagine
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload_file);
                wp_update_attachment_metadata($attach_id, $attach_data);

                // Aggiornamento dei campi ACF in base al tipo di immagine
                switch ($image_type) {
                    case 'trigesimo':
                        update_field('immagine_annuncio_trigesimo', $attach_id, $post_id);
                        break;
                    case 'anniversario':
                        update_field('immagine_annuncio_anniversario', $attach_id, $post_id);
                        break;
                    default:
                        $this->log("Tipo di immagine non riconosciuto: $image_type");
                        break;
                }

                return true;

            } catch (Exception $e) {
                $this->log("Errore nel processare l'immagine $image_url: " . $e->getMessage());
                return false;
            }
        }


        private function manipola_dati($input_file)
        {
            $output_file = $input_file . '_elaborato.csv';

            // Creazione degli oggetti SplFileObject per input e output
            try {
                $file_input = new SplFileObject($input_file, 'r');
            } catch (RuntimeException $e) {
                return false; // Errore nell'apertura del file di input
            }

            // Se il file di output esiste già, lo eliminiamo
            if (file_exists($output_file)) {
                unlink($output_file);
            }

            try {
                $file_output = new SplFileObject($output_file, 'w');
            } catch (RuntimeException $e) {
                return false; // Errore nella creazione del file di output
            }

            // Lettura dell'header
            $header = $file_input->fgetcsv();
            if ($header === false) {
                return false; // Errore nella lettura dell'header
            }

            $header[] = 'Tipo';
            $header[] = 'Anni';
            $file_output->fputcsv($header);

            $tipo_ricorrenza_index = array_search('TipoRicorrenza', $header);
            if ($tipo_ricorrenza_index === false) {
                return false; // Colonna 'TipoRicorrenza' non trovata
            }

            // Iterazione attraverso ogni riga del file di input
            while (!$file_input->eof()) {
                $row = $file_input->fgetcsv();
                if ($row === [null] || $row === false) { // Controlla righe vuote o errori
                    continue;
                }

                $tipo_ricorrenza = $row[$tipo_ricorrenza_index];
                $parole = $this->pulisci_e_dividi($tipo_ricorrenza);
                $numero = $this->trova_numero($parole);

                $row[] = '0';  // Tipo (default value)
                $row[] = '';   // Anni

                if ($numero === null && $this->contiene_triggesimo($parole)) {
                    $row[count($row) - 2] = '1';
                } elseif ($numero !== null && $this->contiene_anniversario($parole)) {
                    $row[count($row) - 2] = '2';
                    $row[count($row) - 1] = strval($numero);
                }

                $file_output->fputcsv($row);
            }

            // Chiudiamo i file prima di qualsiasi operazione di unlink o rename
            $file_input = null;
            $file_output = null;

            // Rinominare il file di output come il file originale
            if (rename($output_file, $input_file)) {
                //create a file called manipulation_done.txt
                return true; // Elaborazione completata con successo
            } else {
                return false; // Errore durante il rename
            }
        }


        private function pulisci_e_dividi($testo)
        {
            $testo_pulito = preg_replace('/[^\w\s]/', '', strtolower(trim($testo)));
            return explode(' ', $testo_pulito);
        }

        private function trova_numero($parole)
        {
            foreach ($parole as $parola) {
                if (ctype_digit($parola) && intval($parola) >= 1 && intval($parola) <= 20) {
                    return intval($parola);
                }
            }
            return null;
        }

        private function contiene_triggesimo($parole)
        {
            $varianti_triggesimo = ['trigesimo', 'triggesimo', 'trigesima', 'triggesima'];
            return count(array_intersect($parole, $varianti_triggesimo)) > 0;
        }

        private function contiene_anniversario($parole)
        {
            $varianti_anniversario = ['anniversario', 'annuale', 'ricorrenza'];
            return count(array_intersect($parole, $varianti_anniversario)) > 0;
        }




    }
}