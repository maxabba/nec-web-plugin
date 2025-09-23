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
        private array $necrologio_cache = [];

        private string $image_cron_hook = 'dokan_mods_download_images';


        private string $image_queue_file = 'image_download_queue_ricorrenze.csv';
        private string $cron_hook = 'dokan_mods_process_image_ricorrenze_queue';
        private int $images_per_cron = 500;

        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);
            
            $this->memory_limit_mb = 256;
            $this->max_execution_time = 30;

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
            $this->scheduleImageProcessing($this->cron_hook);
        }


        public function migrate_ricorrenze_batch($file_name)
        {
            try {
                $start_time = microtime(true);
                $progress_status = $this->get_progress_status($file_name);

            // Se il processo è già completato completamente (tutti i batch)
            if ($progress_status == 'finished') {
                $this->schedule_image_processing();
                $this->log("Il file $file_name è già stato processato completamente.");
                return true;
            }

            // Controllo se il processo è attivo o se è bloccato
            if ($progress_status == 'ongoing') {
                if (!$this->is_process_active($file_name)) {
                    $this->log("Processo 'ongoing' rilevato come inattivo per $file_name - continuazione automatica");
                    $this->set_progress_status($file_name, 'completed');
                    $progress_status = 'completed';
                }
            }

            // Se lo stato è 'not_started', eseguire la manipolazione e avviare il batch processing
            if ($progress_status == 'not_started') {
                // Se il file manipulation_done.txt esiste, cancellarlo per ripetere la manipolazione
                if (file_exists($this->upload_dir . 'manipulation_done.txt')) {
                    unlink($this->upload_dir . 'manipulation_done.txt');
                    $this->log("Resetting manipulation due to new migration run.");
                }

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

                if (!$progress = $this->first_call_check($file)) {
                    return false;
                }

                $processed = $progress['processed'];
                $total_rows = $progress['total'];

                // IMPORTANTE: Prima di leggere l'header, dobbiamo posizionarci all'inizio del file
                // perché first_call_check potrebbe aver già letto l'header
                $file->rewind();
                
                // CRITICO: Ristabilisce le impostazioni CSV control dopo rewind
                $file->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                $this->log("DEBUG: CSV control set to delimiter: '{$this->csv_delimiter}', enclosure: '{$this->csv_enclosure}'");
                
                $header = $file->fgetcsv(); // Leggi l'header
                $this->log("DEBUG: Header read: " . print_r($header, true));
                
                // Verifica che l'header sia stato letto correttamente
                if (!$header || !is_array($header)) {
                    $this->log("ERRORE: Impossibile leggere l'header del file CSV o header non valido");
                    $this->set_progress_status($file_name, 'error');
                    return false;
                }

                // Ora salta all'offset corretto (processed + 1 per saltare l'header)
                if ($processed > 0) {
                    $file->seek($processed + 1);  // +1 per saltare l'header
                    // Ensure CSV control is maintained after seek
                    $file->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                }

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

                $image_queue = [];

                foreach ($batch_data as $data) {
                    $this->process_single_record($data, $header, $existing_posts, $image_queue);
                    $processed++;
                    $this->update_progress($file_name, $processed, $total_rows);
                }

                unset($batch_data);
                $file = null;

                if (!empty($image_queue)) {
                    $this->addToImageQueueSimple($image_queue, $this->image_queue_file);
                }
                unset($image_queue);

                $end_time = microtime(true);
                $execution_time = $end_time - $start_time;
                $this->log("Batch execution time: {$execution_time} seconds");

                // Se abbiamo finito tutte le righe
                if ($processed >= $total_rows) {
                    $this->set_progress_status($file_name, 'finished');
                    
                    // Controlla se ci sono ancora immagini da processare
                    $queue_path = $this->upload_dir . $this->image_queue_file;
                    if (file_exists($queue_path)) {
                        $queue_progress = $this->getImageQueueProgress($this->image_queue_file);
                        if ($queue_progress['processed'] < $queue_progress['total']) {
                            $this->log("Migrazione ricorrenze completata, ma ci sono ancora immagini da processare: {$queue_progress['processed']}/{$queue_progress['total']}");
                            $this->schedule_image_processing();
                        } else {
                            $this->log("Migrazione ricorrenze e download immagini completati");
                        }
                    } else {
                        $this->schedule_image_processing();
                    }
                } else {
                    $this->set_progress_status($file_name, 'completed');
                }

                return $processed >= $total_rows;
            }

            return false; // Se lo stato non rientra in nessuno dei casi previsti
            
            } catch (Exception $e) {
                $error_message = "Errore durante la migrazione ricorrenze: " . $e->getMessage();
                $this->log("ERRORE CRITICO: " . $error_message);
                $this->log("Stack trace: " . $e->getTraceAsString());
                
                // Aggiorna lo status del progresso come errore
                $this->set_progress_status($file_name, 'error');
                
                // Re-throw per permettere al sistema di loggare in wc-logs
                throw $e;
            }
        }

        // OLD is_process_active method removed - now using inherited method from parent class


        // OLD get_memory_usage and get_memory_usage_mb methods removed - now using inherited methods from parent class


        private function process_single_record($data, $header, $existing_posts, &$image_queue)
        {

            static $field_indexes = null;

            // Cache degli indici dei campi
            if ($field_indexes === null) {
                $field_indexes = [
                    'ID' => array_search('ID', $header),
                    'TipoRicorrenza' => array_search('TipoRicorrenza', $header),
                    'IdNecrologio' => array_search('IdNecrologio', $header),
                    'Data' => array_search('Data', $header),
                    'Foto' => array_search('Foto', $header),
                    'Testo' => array_search('Testo', $header),
                    'Pubblicato' => array_search('Pubblicato', $header),
                    'AltreInfo' => array_search('AltreInfo', $header),
                    'Layout' => array_search('Layout', $header)
                ];
            }

            $id = $data[$field_indexes['ID']];

            // Skip se esiste già
            if (isset($existing_posts[$id])) {
                $existing_post_id = $existing_posts[$id];
                $this->log("Ricorrenza già esistente: ID $id, Post ID: $existing_post_id");
                
                // Verifica e gestisci immagini mancanti per post esistenti
                $this->handle_missing_images_for_existing_ricorrenza($existing_post_id, $data, $field_indexes, $image_queue);
                return;
            }

            // Analizza il tipo di ricorrenza dal CSV
            $tipo_ricorrenza_string = $data[$field_indexes['TipoRicorrenza']];
            $is_trigesimo = $this->classify_ricorrenza_type($tipo_ricorrenza_string);
            
            if (empty($tipo_ricorrenza_string)) {
                $this->log("TipoRicorrenza vuoto per ID: $id");
                return; // Salta questo record
            }
            
            $this->log("DEBUG ID $id: TipoRicorrenza='$tipo_ricorrenza_string' -> " . ($is_trigesimo ? 'trigesimo' : 'anniversario'));


            $necrologio_old_id = $data[$field_indexes['IdNecrologio']];
            
            // Usa cache per evitare query ripetute
            if (!isset($this->necrologio_cache[$necrologio_old_id])) {
                $this->necrologio_cache[$necrologio_old_id] = $this->get_post_by_old_id($necrologio_old_id);
            }
            
            $necrologio = $this->necrologio_cache[$necrologio_old_id];

            if (!$necrologio) {
                $this->log("Annuncio di morte non trovato per ID: $necrologio_old_id");
                return;
            }

            // Recupero città
            $citta = get_field('citta', $necrologio->ID);
            
            // Recupero data di morte o usa data pubblicazione come fallback
            $data_di_morte = get_field('data_di_morte', $necrologio->ID);
            $base_date = $data_di_morte ? $data_di_morte : $necrologio->post_date;
            $this->log("ID $id: Base date per calcolo: $base_date (morte: " . ($data_di_morte ? 'si' : 'no') . ")");

            // Determina tipo di post e autore
            $post_type = $is_trigesimo ? 'trigesimo' : 'anniversario';
            $author_id = $necrologio->post_author;
            
            // Estrai numero anniversario (anche per trigesimi, per consistenza)
            $anniversary_number = null;
            if (!$is_trigesimo) {
                $anniversary_number = $this->extract_anniversary_number($tipo_ricorrenza_string);
                if ($anniversary_number === null) {
                    $anniversary_number = 1; // Default se non trovato
                    $this->log("ID $id: Numero anniversario non trovato, uso default: 1");
                }
            }

            // Data di pubblicazione del post: sempre il campo "Data" dal CSV
            $pub_date = $data[$field_indexes['Data']];
            
            // Calcolo delle date ACF basate sulla data dell'annuncio di morte
            // Converti la data base in formato corretto se necessario
            $base_date_formatted = $this->parse_italian_date($base_date);
            $this->log("ID $id: Data base convertita: '$base_date' -> '$base_date_formatted'");
            
            if ($is_trigesimo) {
                // Trigesimo: data annuncio di morte + 30 giorni
                $timestamp = strtotime($base_date_formatted . ' +30 days');
                if ($timestamp === false) {
                    $this->log("ERRORE ID $id: Impossibile calcolare data trigesimo da '$base_date_formatted'");
                    $acf_date = $data[$field_indexes['Data']];
                } else {
                    $acf_date = date('Y-m-d H:i:s', $timestamp);
                }
                $this->log("ID $id: Calcolata data ACF trigesimo: $acf_date (base: $base_date)");
            } else {
                // Anniversario: data annuncio di morte + N anni
                $timestamp = strtotime($base_date_formatted . ' +' . $anniversary_number . ' year');
                if ($timestamp === false) {
                    $this->log("ERRORE ID $id: Impossibile calcolare data anniversario da '$base_date_formatted'");
                    $acf_date = $data[$field_indexes['Data']];
                } else {
                    $acf_date = date('Y-m-d H:i:s', $timestamp);
                    
                    // Validazione data ACF
                    $year = date('Y', $timestamp);
                    if ($year > 2100) {
                        $this->log("ERRORE ID $id: Anno calcolato non valido: $year. Uso data originale dal CSV.");
                        $acf_date = $data[$field_indexes['Data']]; // Usa la data dal CSV come fallback
                    }
                }
                $this->log("ID $id: Calcolata data ACF anniversario n.$anniversary_number: $acf_date (base: $base_date)");
            }

            // Creazione del nuovo post
            $post_id = wp_insert_post(array(
                'post_type' => $post_type,
                'post_status' => $data[$field_indexes['Pubblicato']] == 1 ? 'publish' : 'draft',
                'post_title' => get_the_title($necrologio->ID),
                'post_author' => $author_id,
                'post_date' => $pub_date, // Usa sempre il campo "Data" dal CSV
            ));

            if (!is_wp_error($post_id)) {
                // Aggiornamento campi
                update_field('annuncio_di_morte', $necrologio->ID, $post_id);
                update_field('id_old', $id, $post_id);

                if ($is_trigesimo) { // Trigesimo
                    update_field('trigesimo_data', $acf_date, $post_id); // Data calcolata (morte + 30 giorni)
                    update_field('testo_annuncio_trigesimo', $data[$field_indexes['Testo']], $post_id);
                } else { // Anniversario
                    update_field('anniversario_data', $acf_date, $post_id); // Data calcolata (morte + N anni)
                    update_field('testo_annuncio_anniversario', $data[$field_indexes['Testo']], $post_id);
                    // Usa il numero estratto invece del campo 'Anni' inesistente
                    update_field('anniversario_n_anniversario', $anniversary_number, $post_id);
                    $this->log("ID $id: Salvato numero anniversario: $anniversary_number");
                }

                if ($citta) {
                    update_field('citta', $citta, $post_id);
                }

                // Gestione immagine con formato standardizzato
                if ($data[$field_indexes['Foto']]) {
                        $image_type = $is_trigesimo ? 'trigesimo' : 'anniversario';
                        $image_queue[] = [$image_type, 'https://necrologi.sciame.it/necrologi/' . $data[$field_indexes['Foto']], $post_id, $author_id];
                }

                $this->log("Ricorrenza creata: ID $post_id, Tipo: $post_type");
            } else {
                $this->log("Errore nella creazione della ricorrenza: " . $post_id->get_error_message());
            }
        }



        // OLD image_queue method removed - now using centralized addToImageQueue in parent class





        // OLD get_images_ids_by_urls method removed - existing image detection now handled by centralized system






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
         * Mark image processing as finished if already complete
         */
        public function mark_as_finished()
        {
            $queue_path = $this->upload_dir . $this->image_queue_file;
            if (file_exists($queue_path)) {
                $queue_progress = $this->getImageQueueProgress($this->image_queue_file);
                $status = $this->getImageQueueProgressStatus($this->image_queue_file);
                
                $this->log("Marking as finished - Current status: $status, Progress: {$queue_progress['processed']}/{$queue_progress['total']}");
                
                // Set status to finished and clean up
                $this->setImageQueueProgressStatus($this->image_queue_file, 'finished');
                wp_clear_scheduled_hook($this->cron_hook);
                
                $this->log("Image processing marked as finished and cron job cleared for ricorrenze");
                return true;
            } else {
                $this->log("No image queue file found for ricorrenze");
                return false;
            }
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
                
                $this->log("FORCE RESTART: Current status: $status, Progress: {$queue_progress['processed']}/{$queue_progress['total']}");
                
                // FORCE restart: reset progress to 0 and start over
                $this->log("Forcing complete restart - resetting JSON progress to 0");
                
                // Reset progress to 0 - CSV is not modified
                $this->updateImageQueueProgress($this->image_queue_file, 0, $queue_progress['total']);
                
                // Reset status to allow processing to continue
                $this->setImageQueueProgressStatus($this->image_queue_file, 'completed');
                
                // Clear any existing cron job
                wp_clear_scheduled_hook($this->cron_hook);
                
                // Schedule new processing
                $this->schedule_image_processing();
                
                $this->log("Image processing FORCE RESTARTED for ricorrenze - JSON progress reset to 0/{$queue_progress['total']}");
                return true;
            } else {
                $this->log("No image queue file found for ricorrenze");
                return false;
            }
        }

        // OLD download_and_upload_image method removed - now using centralized downloadAndAttachImage in parent class


        private function manipola_dati($input_file)
        {
            $this->log("MANIPOLA_DATI: Inizio manipolazione dati per file: $input_file");
            $output_file = $input_file . '_elaborato.csv';

            // Verifica esistenza file di input
            if (!file_exists($input_file)) {
                $this->log("MANIPOLA_DATI: ERRORE - File di input non trovato: $input_file");
                return false;
            }

            $this->log("MANIPOLA_DATI: File di input trovato, dimensione: " . filesize($input_file) . " bytes");

            // Prima rileva il formato CSV dal file path
            $csv_format = $this->detectCsvFormat($input_file);
            
            // Imposta i parametri CSV rilevati
            $this->csv_delimiter = $csv_format['delimiter'];
            $this->csv_enclosure = $csv_format['enclosure'];
            $this->csv_escape = $csv_format['escape'];
            
            // Creazione degli oggetti SplFileObject per input e output
            try {
                $file_input = new SplFileObject($input_file, 'r');
                $file_input->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                $this->log("MANIPOLA_DATI: File di input aperto con successo, delimiter rilevato: '{$this->csv_delimiter}'");
            } catch (RuntimeException $e) {
                $this->log("MANIPOLA_DATI: ERRORE - Impossibile aprire file di input: " . $e->getMessage());
                return false;
            }

            // Se il file di output esiste già, lo eliminiamo
            if (file_exists($output_file)) {
                $this->log("MANIPOLA_DATI: File di output esistente eliminato: $output_file");
                unlink($output_file);
            }

            try {
                $file_output = new SplFileObject($output_file, 'w');
                $file_output->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                $this->log("MANIPOLA_DATI: File di output creato con successo: $output_file");
            } catch (RuntimeException $e) {
                $this->log("MANIPOLA_DATI: ERRORE - Impossibile creare file di output: " . $e->getMessage());
                return false;
            }

            // Lettura dell'header
            $header = $file_input->fgetcsv();
            if ($header === false) {
                $this->log("MANIPOLA_DATI: ERRORE - Impossibile leggere header del file CSV");
                return false;
            }

            // Remove BOM from first header element if present
            if (!empty($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // Remove UTF-8 BOM
                $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]); // Remove UTF-16 BOM
            }

            $this->log("MANIPOLA_DATI: Header letto con successo: " . print_r($header, true));
            $this->log("MANIPOLA_DATI: Numero di colonne nell'header: " . count($header));

            $header[] = 'Tipo';
            $header[] = 'Anni';
            
            if (!$file_output->fputcsv($header)) {
                $this->log("MANIPOLA_DATI: ERRORE - Impossibile scrivere header nel file di output");
                return false;
            }

            $tipo_ricorrenza_index = array_search('TipoRicorrenza', $header);
            if ($tipo_ricorrenza_index === false) {
                $this->log("MANIPOLA_DATI: ERRORE - Colonna 'TipoRicorrenza' non trovata nell'header");
                return false;
            }

            $this->log("MANIPOLA_DATI: Indice colonna TipoRicorrenza: $tipo_ricorrenza_index");

            // Iterazione attraverso ogni riga del file di input
            $row_count = 0;
            $processed_rows = 0;
            
            while (!$file_input->eof()) {
                $row = $file_input->fgetcsv();
                $row_count++;
                
                if ($row === [null] || $row === false) {
                    $this->log("MANIPOLA_DATI: Riga $row_count saltata (vuota o errore)");
                    continue;
                }

                if ($row_count <= 5) { // Log delle prime 5 righe per debug
                    $this->log("MANIPOLA_DATI: Processando riga $row_count: " . print_r($row, true));
                }

                if (!isset($row[$tipo_ricorrenza_index])) {
                    $this->log("MANIPOLA_DATI: ERRORE - Indice TipoRicorrenza non trovato nella riga $row_count");
                    continue;
                }

                $tipo_ricorrenza = $row[$tipo_ricorrenza_index];
                $parole = $this->pulisci_e_dividi($tipo_ricorrenza);
                $numero = $this->trova_numero($parole);

                if ($row_count <= 5) {
                    $this->log("MANIPOLA_DATI: Riga $row_count - TipoRicorrenza: '$tipo_ricorrenza', Numero: " . ($numero ?: 'null'));
                }

                $row[] = '0';  // Tipo (default value)
                $row[] = '';   // Anni

                if ($numero === null && $this->contiene_triggesimo($parole)) {
                    $row[count($row) - 2] = '1';
                    if ($row_count <= 5) {
                        $this->log("MANIPOLA_DATI: Riga $row_count classificata come TRIGESIMO");
                    }
                } elseif ($numero !== null && $this->contiene_anniversario($parole)) {
                    $row[count($row) - 2] = '2';
                    $row[count($row) - 1] = strval($numero);
                    if ($row_count <= 5) {
                        $this->log("MANIPOLA_DATI: Riga $row_count classificata come ANNIVERSARIO n.$numero");
                    }
                }

                if (!$file_output->fputcsv($row)) {
                    $this->log("MANIPOLA_DATI: ERRORE - Impossibile scrivere riga $row_count nel file di output");
                    return false;
                }
                
                $processed_rows++;
            }

            $this->log("MANIPOLA_DATI: Processate $processed_rows righe su $row_count totali");

            // Chiudiamo i file prima di qualsiasi operazione di unlink o rename
            $file_input = null;
            $file_output = null;

            // Verifica che il file di output sia stato creato
            if (!file_exists($output_file)) {
                $this->log("MANIPOLA_DATI: ERRORE - File di output non creato: $output_file");
                return false;
            }

            $this->log("MANIPOLA_DATI: File di output creato, dimensione: " . filesize($output_file) . " bytes");

            // Copiare il file di output come il file originale (mantenendo anche l'elaborato)
            if (copy($output_file, $input_file)) {
                $this->log("MANIPOLA_DATI: Copia completata con successo da $output_file a $input_file");
                $this->log("MANIPOLA_DATI: File elaborato mantenuto: $output_file");
                return true;
            } else {
                $this->log("MANIPOLA_DATI: ERRORE - Impossibile copiare $output_file in $input_file");
                $this->log("MANIPOLA_DATI: Verifica permessi directory: " . dirname($input_file));
                return false;
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

        /**
         * Classifica il tipo di ricorrenza basandosi sulla stringa TipoRicorrenza del CSV
         * @param string $tipo_ricorrenza_string Il valore della colonna TipoRicorrenza
         * @return bool true se è un trigesimo, false se è un anniversario
         */
        private function classify_ricorrenza_type($tipo_ricorrenza_string)
        {
            if (empty($tipo_ricorrenza_string)) {
                return false; // Default ad anniversario se vuoto
            }
            
            // Converte in minuscolo per il confronto case-insensitive
            $tipo_lower = strtolower(trim($tipo_ricorrenza_string));
            
            // Pattern per identificare trigesimi (incluse variazioni e errori di battitura)
            $trigesimo_patterns = [
                'trigesimo',
                'triggesimo', 
                'trigesima',
                'triggesima',
                'tigesimo',    // Errore di battitura comune nel CSV
                'trigsimo',
                'tricesimo',
                'trigeseimo',
                'triigesimo',
                '30',
                'trent'
            ];
            
            // Controlla se contiene pattern di trigesimo
            foreach ($trigesimo_patterns as $pattern) {
                if (strpos($tipo_lower, $pattern) !== false) {
                    return true;
                }
            }
            
            // Se non è trigesimo, è anniversario (default)
            return false;
        }

        /**
         * Estrae il numero dell'anniversario dalla stringa TipoRicorrenza
         * Gestisce tutti i pattern trovati nell'analisi del CSV
         * @param string $tipo_ricorrenza_string Il valore della colonna TipoRicorrenza
         * @return int|null Il numero dell'anniversario o null se non trovato/non applicabile
         */
        private function extract_anniversary_number($tipo_ricorrenza_string)
        {
            if (empty($tipo_ricorrenza_string)) {
                return null;
            }

            // Array per memorizzare tutti i numeri trovati
            $found_numbers = [];

            // Pattern 1: N° ANNIVERSARIO o N°ANNIVERSARIO (con simbolo grado)
            if (preg_match_all('/(\d+)°\s*ANNIVERSARIO/i', $tipo_ricorrenza_string, $matches)) {
                foreach ($matches[1] as $num) {
                    $found_numbers[] = intval($num);
                }
            }

            // Pattern 2: Nº ANNIVERSARIO (con simbolo ordinale)
            if (preg_match_all('/(\d+)º\s*ANNIVERSARIO/i', $tipo_ricorrenza_string, $matches)) {
                foreach ($matches[1] as $num) {
                    $found_numbers[] = intval($num);
                }
            }

            // Pattern 3: N ANNIVERSARIO (solo numero e spazio)
            if (preg_match_all('/(\d+)\s+ANNIVERSARIO/i', $tipo_ricorrenza_string, $matches)) {
                foreach ($matches[1] as $num) {
                    $found_numbers[] = intval($num);
                }
            }

            // Pattern 4: anniversarioN (numero alla fine)
            if (preg_match('/anniversario\s*(\d+)/i', $tipo_ricorrenza_string, $matches)) {
                $found_numbers[] = intval($matches[1]);
            }

            // Pattern 5: Abbreviazioni (ann, ANN) con numero
            if (preg_match('/(\d+)\s*ann/i', $tipo_ricorrenza_string, $matches)) {
                $found_numbers[] = intval($matches[1]);
            }

            // Pattern 6: Gestione errori di battitura comuni
            $typo_patterns = [
                '/(\d+)°\s*ANNIV[A-Z]*SARIO/i',  // ANNIVESARIO, ANNIIVERSARIO, etc.
                '/(\d+)°\s*AMMIVERSARIO/i',        // AMMIVERSARIO
                '/(\d+)°\s*ANIVERSARIO/i',         // ANIVERSARIO
                '/(\d+)°\s*ANNIVERSAIO/i',         // ANNIVERSAIO
                '/(\d+)°\s*ANNIVERSAQRIO/i'        // ANNIVERSAQRIO
            ];

            foreach ($typo_patterns as $pattern) {
                if (preg_match($pattern, $tipo_ricorrenza_string, $matches)) {
                    $found_numbers[] = intval($matches[1]);
                }
            }

            // Se abbiamo trovato dei numeri, restituisci il primo valido
            if (!empty($found_numbers)) {
                // Filtra numeri validi (range ragionevole 1-100)
                foreach ($found_numbers as $num) {
                    if ($num >= 1 && $num <= 100) {
                        $this->log("Estratto numero anniversario: $num da '$tipo_ricorrenza_string'");
                        return $num;
                    }
                }
            }

            // Se non troviamo numeri ma è chiaramente un anniversario, default a 1
            if (stripos($tipo_ricorrenza_string, 'ann') !== false && !$this->classify_ricorrenza_type($tipo_ricorrenza_string)) {
                $this->log("Anniversario senza numero specifico, default a 1: '$tipo_ricorrenza_string'");
                return 1;
            }

            return null;
        }

        /**
         * Converte date italiane (DD/MM/YYYY) in formato compatibile con strtotime()
         * @param string $date_string La data da convertire
         * @return string Data in formato YYYY-MM-DD compatibile con strtotime()
         */
        private function parse_italian_date($date_string)
        {
            // Se è già in formato ISO (YYYY-MM-DD), restituiscila così com'è
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date_string)) {
                return $date_string;
            }
            
            // Se è in formato americano (YYYY-MM-DD HH:MM:SS), estraici solo la data
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $date_string, $matches)) {
                return $matches[1];
            }
            
            // Prova formato italiano DD/MM/YYYY o DD-MM-YYYY
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $date_string, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $year = $matches[3];
                return "$year-$month-$day";
            }
            
            // Se non riesco a convertire, prova strtotime diretto e vedi se funziona
            $timestamp = strtotime($date_string);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
            
            // Fallback: restituisci la data originale
            $this->log("ATTENZIONE: Impossibile convertire data '$date_string', uso originale");
            return $date_string;
        }

        /**
         * Gestisce le immagini mancanti per una ricorrenza esistente
         * Verifica se i campi ACF delle immagini sono vuoti e li aggiunge alla coda di download
         */
        private function handle_missing_images_for_existing_ricorrenza($post_id, $data, $field_indexes, &$image_queue)
        {
            // Determina il tipo di ricorrenza per sapere quale campo ACF controllare
            $post_type = get_post_type($post_id);
            $foto_value = $data[$field_indexes['Foto']] ?? '';
            
            if (empty($foto_value)) {
                return; // Nessuna immagine nel CSV da processare
            }
            
            $image_field = null;
            $image_type = null;
            
            if ($post_type === 'trigesimo') {
                $image_field = 'immagine_annuncio_trigesimo';
                $image_type = 'trigesimo';
            } elseif ($post_type === 'anniversario') {
                $image_field = 'immagine_annuncio_anniversario'; 
                $image_type = 'anniversario';
            } else {
                $this->log("Tipo di post non riconosciuto per ricorrenza ID: $post_id (tipo: $post_type)");
                return;
            }
            
            // Verifica se il campo ACF è vuoto
            $current_image = get_field($image_field, $post_id);
            
            if (empty($current_image)) {
                $author_id = get_post_field('post_author', $post_id);
                $image_queue[] = [$image_type, 'https://necrologi.sciame.it/necrologi/' . $foto_value, $post_id, $author_id];
                $this->log("Aggiunta immagine $image_type alla coda per post esistente ID: $post_id (foto: $foto_value)");
            } else {
                $this->log("Post ID $post_id ($post_type) ha già immagine collegata: " . (is_array($current_image) ? $current_image['ID'] : $current_image));
            }
        }

    }
}