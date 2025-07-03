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

                // Ensure CSV control is set correctly before reading header
                $file->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                $this->log("DEBUG: CSV control set to delimiter: '{$this->csv_delimiter}', enclosure: '{$this->csv_enclosure}'");
                
                $header = $file->fgetcsv(); // Leggi l'header
                $this->log("DEBUG: Header read: " . print_r($header, true));

                // Riposizionamento lettura CSV per continuare dal progresso attuale
                $file->seek($processed); // Salta le righe già processate
                
                // Ensure CSV control is maintained after seek
                $file->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);

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
                    $this->schedule_image_processing();
                } else {
                    $this->set_progress_status($file_name, 'completed');
                }

                return $processed >= $total_rows;
            }

            return false; // Se lo stato non rientra in nessuno dei casi previsti
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
                $this->log("Ricorrenza già esistente: ID {$existing_posts[$id]}");
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

            // Calcolo della data di pubblicazione
            if ($is_trigesimo) {
                // Trigesimo: base_date + 30 giorni
                $pub_date = date('Y-m-d H:i:s', strtotime($base_date . ' +30 days'));
                $this->log("ID $id: Calcolata data trigesimo: $pub_date");
            } else {
                // Calcola data: base_date + N anni
                $pub_date = date('Y-m-d H:i:s', strtotime($base_date . ' +' . $anniversary_number . ' year'));
                $this->log("ID $id: Calcolata data anniversario n.$anniversary_number: $pub_date");
                
                // Validazione data
                $year = date('Y', strtotime($pub_date));
                if ($year > 2100) {
                    $this->log("ERRORE ID $id: Anno calcolato non valido: $year. Uso data originale dal CSV.");
                    $pub_date = $data[$field_indexes['Data']]; // Usa la data dal CSV come fallback
                }
            }

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
                update_field('id_old', $id, $post_id);

                if ($is_trigesimo) { // Trigesimo
                    update_field('_data', $data[$field_indexes['Data']], $post_id);
                    update_field('testo_annuncio_trigesimo', $data[$field_indexes['Testo']], $post_id);
                } else { // Anniversario
                    update_field('anniversario_data', $data[$field_indexes['Data']], $post_id);
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
            // Use centralized image queue processing
            $this->processImageQueue($this->image_queue_file, $this->cron_hook, $this->images_per_cron);
        }


        // OLD download_and_upload_image method removed - now using centralized downloadAndAttachImage in parent class


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




    }
}