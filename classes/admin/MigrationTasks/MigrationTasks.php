<?php

namespace Dokan_Mods\Migration_Tasks;

use Exception;
use RuntimeException;
use SplFileObject;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\MigrationTasks')) {
    class MigrationTasks
    {

        protected $upload_dir;
        protected $progress_file;
        protected $log_file;
        protected $batch_size;
        protected $memory_limit_mb = 256;
        protected $max_execution_time = 30;
        
        // CSV format parameters
        protected $csv_delimiter = null; // null = auto-detect
        protected $csv_enclosure = '"';
        protected $csv_escape = '\\';
        
        // Cache per conteggio CSV
        protected $csv_count_cache = [];
        protected $csv_count_cache_file;

        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            $this->upload_dir = $upload_dir;
            $this->progress_file = $progress_file;
            $this->log_file = $log_file;
            $this->batch_size = $batch_size;
            $this->csv_count_cache_file = $upload_dir . 'csv_count_cache.json';
            $this->load_csv_count_cache();
        }

        protected function countCsvRows(SplFileObject $file)
        {
            $file_path = $file->getRealPath();
            $file_name = basename($file_path);
            $file_mtime = filemtime($file_path);
            $file_size = filesize($file_path);
            
            // Controlla se abbiamo una cache valida
            $cache_key = $file_name;
            if (isset($this->csv_count_cache[$cache_key])) {
                $cached = $this->csv_count_cache[$cache_key];
                if ($cached['mtime'] === $file_mtime && $cached['size'] === $file_size) {
                    $this->log("Utilizzando conteggio CSV dalla cache per $file_name: {$cached['count']} righe");
                    return $cached['count'];
                }
            }
            
            $this->log("Inizio conteggio righe CSV per file: $file_path");
            
            // Prima detecta il formato del CSV
            $csv_format = $this->detectCsvFormat($file_path);
            
            // Usa il conteggio CSV per accuratezza con delimitatori complessi
            $csv_count = $this->countCsvRowsWithFormat($file, $csv_format);
            
            // Salva nella cache
            $this->csv_count_cache[$cache_key] = [
                'count' => $csv_count,
                'mtime' => $file_mtime,
                'size' => $file_size,
                'timestamp' => time()
            ];
            $this->save_csv_count_cache();
            
            // Log informativo sulla struttura del file
            if ($csv_format['line_count'] > 0) {
                $ratio = $csv_format['line_count'] > 0 ? $csv_format['line_count'] / ($csv_count + 1) : 0;
                
                $this->log("Conteggio linee fisiche: {$csv_format['line_count']}, Record CSV validi: " . ($csv_count + 1));
                
                if ($ratio > 5) {
                    $this->log("NOTA: File con campi multi-riga (ratio: " . round($ratio, 1) . ":1) - Normale per CSV con testo quotato");
                }
            }
            
            $this->log("Conteggio CSV completato e salvato in cache: $csv_count righe dati");
            return $csv_count;
        }

        protected function countCsvRowsWithFormat(SplFileObject $file, array $csv_format)
        {
            $count = 0;
            $start_time = microtime(true);
            $max_execution_time = 25; // Limite di 25 secondi per il conteggio
            $last_check_time = $start_time;
            $check_interval = 1000; // Controlla timeout ogni 1000 righe
            
            $file->rewind();
            
            // Configura i parametri CSV corretti
            $file->setCsvControl(
                $csv_format['delimiter'],
                $csv_format['enclosure'],
                $csv_format['escape']
            );
            
            $this->log("Conteggio CSV con parametri - Delimiter: '{$csv_format['delimiter']}', Enclosure: '{$csv_format['enclosure']}'");
            
            try {
                while (!$file->eof()) {
                    $row = $file->fgetcsv();
                    
                    // Conta solo righe valide (non false, non null, non array vuoto)
                    if ($row !== false && $row !== null && $row !== [null]) {
                        // Verifica che almeno un campo non sia vuoto
                        $has_content = false;
                        foreach ($row as $field) {
                            if ($field !== null && $field !== '') {
                                $has_content = true;
                                break;
                            }
                        }
                        
                        if ($has_content) {
                            $count++;
                        }
                    }
                    
                    // Controllo timeout periodico per evitare blocchi
                    if ($count % $check_interval === 0) {
                        $current_time = microtime(true);
                        $elapsed = $current_time - $start_time;
                        
                        if ($elapsed > $max_execution_time) {
                            $this->log("TIMEOUT: Conteggio CSV interrotto dopo {$elapsed}s con $count righe processate");
                            // Usa conteggio parziale + stima
                            $file_position = $file->ftell();
                            $file_size = $file->getSize();
                            $estimated_total = $file_size > 0 ? round($count * ($file_size / $file_position)) : $count;
                            $this->log("Stima totale basata su progresso: $estimated_total righe");
                            $count = $estimated_total;
                            break;
                        }
                        
                        if ($current_time - $last_check_time > 5) { // Log ogni 5 secondi
                            $this->log("Conteggio in corso: $count righe processate in {$elapsed}s");
                            $last_check_time = $current_time;
                        }
                    }
                }
                
                $file->rewind();
                
                // Resetta i parametri CSV ai default
                $file->setCsvControl(',', '"', '\\');
                
                $data_rows = max(0, $count - 1);
                $total_time = microtime(true) - $start_time;
                $this->log("Conteggio CSV completato: $count righe totali, $data_rows righe dati (tempo: {$total_time}s)");
                
                return $data_rows;
                
            } catch (Exception $e) {
                $this->log("Errore nel conteggio CSV: " . $e->getMessage());
                $file->rewind();
                return 0;
            }
        }

        protected function countCsvRowsFallback(SplFileObject $file)
        {
            $this->log("Utilizzo metodo fallback per conteggio righe CSV");
            
            $count = 0;
            $file->rewind();
            
            // Conta ogni riga che non è vuota
            while (($line = $file->fgets()) !== false) {
                if (trim($line) !== '') {
                    $count++;
                }
            }
            
            $file->rewind();
            
            // Sottrai 1 per l'header
            $data_rows = max(0, $count - 1);
            
            $this->log("Fallback completato: $count righe totali, $data_rows righe dati");
            
            return $data_rows;
        }

        protected function verifyCsvRowCount(SplFileObject $file, $expected_count)
        {
            $this->log("Verifica conteggio CSV: conteggio = $expected_count");
            
            // Validazione base del conteggio
            if ($expected_count < 0) {
                $this->log("ERRORE: Conteggio negativo rilevato: $expected_count");
                return false;
            }
            
            if ($expected_count == 0) {
                $this->log("ATTENZIONE: File CSV vuoto o senza dati");
                return true;
            }
            
            // Per file con campi multi-riga, il conteggio CSV è sempre la fonte di verità
            $this->log("Conteggio CSV accettato: $expected_count righe dati");
            return true;
        }

        protected function resetProgressForFile($file_name)
        {
            $this->log("Reset progresso per file: $file_name");
            
            if (!file_exists($this->progress_file)) {
                $this->log("File di progresso non esiste, creazione nuovo");
                $progress = [];
            } else {
                $progress = json_decode(file_get_contents($this->progress_file), true) ?: [];
            }
            
            // Reset solo per il file specificato
            $progress[$file_name] = [
                'processed' => 0,
                'total' => 0,
                'percentage' => 0,
                'status' => 'not_started'
            ];
            
            if (file_put_contents($this->progress_file, json_encode($progress)) === false) {
                $this->log("ERRORE: Impossibile scrivere il reset del progresso");
                return false;
            }
            
            $this->log("Progresso resettato con successo per $file_name");
            return true;
        }

        protected function detectCsvFormat($file_path)
        {
            $this->log("Detecting CSV format for: $file_path");
            
            try {
                // Leggi un campione del file per analizzarlo
                $handle = fopen($file_path, 'r');
                if (!$handle) {
                    throw new RuntimeException("Cannot open file for format detection");
                }
                
                // Leggi le prime 5 righe per analisi
                $sample_lines = [];
                $line_count = 0;
                while (($line = fgets($handle)) !== false && $line_count < 5) {
                    $sample_lines[] = $line;
                    $line_count++;
                }
                fclose($handle);
                
                if (empty($sample_lines)) {
                    throw new RuntimeException("File appears to be empty");
                }
                
                // Detecta encoding
                $sample_text = implode('', $sample_lines);
                $encoding = mb_detect_encoding($sample_text, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
                $this->log("Detected encoding: " . ($encoding ?: 'unknown'));
                
                // Detecta delimitatore analizzando la frequenza dei caratteri
                $delimiters = [',', ';', "\t", '|'];
                $delimiter_counts = [];
                
                foreach ($delimiters as $delimiter) {
                    $count = 0;
                    foreach ($sample_lines as $line) {
                        $count += substr_count($line, $delimiter);
                    }
                    $delimiter_counts[$delimiter] = $count;
                }
                
                // Il delimitatore più frequente è probabilmente quello corretto
                arsort($delimiter_counts);
                $detected_delimiter = key($delimiter_counts);
                
                $this->log("Delimiter counts: " . json_encode($delimiter_counts));
                $this->log("Detected delimiter: " . ($detected_delimiter === "\t" ? "TAB" : $detected_delimiter));
                
                // Verifica se ci sono quote nel file
                $has_quotes = strpos($sample_text, '"') !== false;
                $enclosure = $has_quotes ? '"' : '';
                
                // Conta le righe reali usando il delimitatore detectato
                $test_count = $this->countLinesSimple($file_path);
                $this->log("Simple line count: $test_count lines");
                
                return [
                    'delimiter' => $detected_delimiter,
                    'enclosure' => $enclosure,
                    'escape' => '\\',
                    'encoding' => $encoding,
                    'line_count' => $test_count
                ];
                
            } catch (Exception $e) {
                $this->log("Error detecting CSV format: " . $e->getMessage());
                // Ritorna valori di default
                return [
                    'delimiter' => ',',
                    'enclosure' => '"',
                    'escape' => '\\',
                    'encoding' => 'UTF-8',
                    'line_count' => 0
                ];
            }
        }

        protected function countLinesSimple($file_path)
        {
            $count = 0;
            $handle = fopen($file_path, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $count++;
                }
                fclose($handle);
            }
            return $count;
        }

        protected function load_file($file_name)
        {
            $csvFile = $this->upload_dir . $file_name;

            try {
                $this->log("Loading file: $csvFile");
                
                // Prima detecta il formato CSV
                $csv_format = $this->detectCsvFormat($csvFile);
                
                // Crea SplFileObject
                $file = new SplFileObject($csvFile, 'r');
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
                
                // Configura i parametri CSV corretti
                $file->setCsvControl(
                    $csv_format['delimiter'],
                    $csv_format['enclosure'],
                    $csv_format['escape']
                );
                
                $this->log("File loaded with CSV parameters - Delimiter: '{$csv_format['delimiter']}', Enclosure: '{$csv_format['enclosure']}', Line count: {$csv_format['line_count']}");
                
                // Salva i parametri CSV per uso futuro
                $this->csv_delimiter = $csv_format['delimiter'];
                $this->csv_enclosure = $csv_format['enclosure'];
                $this->csv_escape = $csv_format['escape'];
                
                return $file;
                
            } catch (RuntimeException $e) {
                $this->log("Errore nell'apertura del file di input: " . $e->getMessage());
                $this->set_progress_status($file_name, 'failed');
                return false;
            }
        }


        // In classes/admin/MigrationTasks/MigrationTasks.php

        protected function get_existing_users_by_old_ids($old_ids)
        {
            if (empty($old_ids)) {
                $this->log("ATTENZIONE: Array old_ids vuoto in get_existing_users_by_old_ids");
                return [];
            }

            global $wpdb;

            try {
                // Log per debug
                $this->log("Cercando utenti per i seguenti old_ids: " . implode(', ', $old_ids));

                // Prepara i placeholders per la query
                $placeholders = implode(',', array_fill(0, count($old_ids), '%s'));

                // Query per ottenere gli utenti
                $sql = $wpdb->prepare(
                    "SELECT um.user_id, um.meta_value 
            FROM {$wpdb->usermeta} um 
            WHERE um.meta_key = 'id_old' 
            AND um.meta_value IN ($placeholders)",
                    $old_ids
                );

                // Log della query per debug
                $this->log("Query SQL eseguita: " . $sql);

                // Esegui la query
                $results = $wpdb->get_results($sql, ARRAY_A);

                if ($wpdb->last_error) {
                    throw new RuntimeException("Errore database: " . $wpdb->last_error);
                }

                // Log dei risultati
                $this->log("Risultati query: " . print_r($results, true));

                // Crea l'array associativo
                $existing_users = [];
                foreach ($results as $row) {
                    $existing_users[$row['meta_value']] = $row['user_id'];
                }

                // Log dell'array finale
                $this->log("Array existing_users creato: " . print_r($existing_users, true));

                return $existing_users;

            } catch (Exception $e) {
                $this->log("Errore nel recupero degli utenti esistenti: " . $e->getMessage());
                return [];
            }
        }

        protected function first_call_check(SplFileObject $file)
        {
            $file_name = $file->getFilename();
            $progress = $this->get_progress($file_name);

            $processed = $progress['processed'];
            $total_rows = $progress['total'];
            
            // Controllo sanità: se processed > total, reset necessario
            if ($total_rows > 0 && $processed > $total_rows) {
                $this->log("ERRORE CRITICO: Processed ($processed) > Total ($total_rows) per $file_name");
                $this->log("Reset automatico del progresso per correggere l'incoerenza");
                
                $this->resetProgressForFile($file_name);
                
                // Ricomincia da zero
                return ['processed' => 0, 'total' => 0, 'percentage' => 0];
            }

            // Prima chiamata: conteggio righe
            if ($processed == 0 && $total_rows == 0) {
                $this->log("Prima chiamata per $file_name: inizializzazione conteggio righe");
                
                $total_rows = $this->countCsvRows($file);
                
                // Verifica del conteggio
                if (!$this->verifyCsvRowCount($file, $total_rows)) {
                    $this->log("ERRORE: Verifica conteggio fallita per $file_name");
                    $this->set_progress_status($file_name, 'failed');
                    return false;
                }
                
                $this->update_progress($file->getFilename(), 0, $total_rows);
                $header = $file->fgetcsv();
                
                if ($file_name != 'accounts.csv') {
                    $this->process_existing_posts($file, $header, $processed, $total_rows, $file_name);
                }
                
                // NON settare completed qui - lascia che il normale flusso di batch processing continui
                // Il status verrà settato correttamente quando il processing è completato
                return ['processed' => 0, 'total' => $total_rows, 'percentage' => 0];
            }

            // Controllo sanità: valida range ragionevole del conteggio
            if ($total_rows > 500000) { // Limite massimo ragionevole
                $this->log("ATTENZIONE: Conteggio righe molto alto per $file_name: $total_rows righe");
                $this->log("Verifica che il file CSV non abbia problemi di formato");
            } else if ($total_rows < 10) { // Minimum ragionevole
                $this->log("ATTENZIONE: Conteggio righe molto basso per $file_name: $total_rows righe");
            }

            if ($processed >= $total_rows) {
                $this->log("Tutti i record sono già stati processati per $file_name");
                $this->set_progress_status($file_name, 'finished');
                return true;
            }

            return $progress;
        }


        protected function process_existing_posts(SplFileObject $file, array $header, &$processed, $total_rows, $file_name)
        {
            $all_ids = [];
            $id_index = array_search('ID', $header);

            while (!$file->eof()) {
                $data = $file->fgetcsv();
                if (empty($data)) continue;
                $all_ids[] = $data[$id_index];
            }

            $existing_posts = $this->get_existing_posts_by_old_ids($all_ids);

            foreach ($all_ids as $id) {
                if (!isset($existing_posts[$id])) {
                    break;
                }
                $processed++;
            }

            $this->update_progress($file_name, $processed, $total_rows);
            unset($all_ids, $existing_posts);
        }


        protected function get_existing_posts_by_old_ids($old_ids, $post_types = ['annuncio-di-morte'])
        {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($old_ids), '%s'));
            $post_type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

                $sql = "
            SELECT pm.post_id, pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'id_old'
            AND pm.meta_value IN ($placeholders)
            AND p.post_type IN ($post_type_placeholders)
            ";

            // Combina i valori di $old_ids e $post_types per la query preparata
            $prepared_sql = $wpdb->prepare($sql, array_merge($old_ids, $post_types));
            $results = $wpdb->get_results($prepared_sql, ARRAY_A);

            $existing_posts = [];
            foreach ($results as $row) {
                $existing_posts[$row['meta_value']] = $row['post_id'];
            }

            return $existing_posts;
        }


        protected function get_progress($file)
        {
            $default = ['processed' => 0, 'total' => 0, 'percentage' => 0];

            if (!file_exists($this->progress_file)) {
                return $default;
            }

            $progress = @json_decode(file_get_contents($this->progress_file), true);

            return isset($progress[$file]['processed'], $progress[$file]['total'], $progress[$file]['percentage'])
                ? $progress[$file]
                : $default;
        }

        protected function log($message)
        {
            try {
                // Verifica se la directory del log esiste, altrimenti creala
                $logDir = dirname($this->log_file);
                if (!is_dir($logDir)) {
                    if (!mkdir($logDir, 0755, true)) {
                        throw new RuntimeException("Impossibile creare la directory di log: $logDir");
                    }
                }

                // Verifica i permessi di scrittura
                if (file_exists($this->log_file) && !is_writable($this->log_file)) {
                    throw new RuntimeException("File di log non scrivibile: {$this->log_file}");
                }

                $file = new SplFileObject($this->log_file, 'a');

                // Formatta il messaggio di log
                $timestamp = date('Y-m-d H:i:s');
                $logEntry = sprintf(
                    "[%s] %s%s",
                    $timestamp,
                    $this->sanitizeLogMessage($message),
                    PHP_EOL
                );

                // Scrivi il log con lock del file
                $file->flock(LOCK_EX);
                $file->fwrite($logEntry);
                $file->flock(LOCK_UN);

                // Assicurati che il file venga chiuso correttamente
                $file = null;

            } catch (RuntimeException $e) {
                // Log di fallback usando error_log
                error_log("Failed to write to custom log file: {$e->getMessage()}");
                error_log("Original message was: $message");
            }
        }

        /**
         * Sanitizza il messaggio di log per prevenire injection
         */
        protected function sanitizeLogMessage($message)
        {
            // Rimuovi caratteri di controllo e newline
            $message = preg_replace('/[\x00-\x1F\x7F]/', '', $message);

            // Converti array/oggetti in stringa
            if (!is_string($message)) {
                $message = print_r($message, true);
            }

            return trim($message);
        }

        protected function update_progress($file, $processed, $total)
        {
            // Validazione: processed non può superare total
            if ($processed > $total) {
                $this->log("ERRORE: Tentativo di impostare processed ($processed) > total ($total) per $file");
                $processed = $total; // Cap al massimo
            }
            
            // Calcola percentuale in modo sicuro
            $percentage = $total > 0 ? round(($processed / $total) * 100, 2) : 0;
            
            // Validazione percentuale
            if ($percentage > 100) {
                $this->log("ERRORE: Percentuale calcolata > 100% ($percentage%) per $file");
                $percentage = 100;
            }
            
            $progress = json_decode(file_get_contents($this->progress_file), true);
            $progress[$file] = [
                'processed' => $processed,
                'total' => $total,
                'percentage' => $percentage,
                'status' => $progress[$file]['status'] ?? 'ongoing'
            ];
            file_put_contents($this->progress_file, json_encode($progress));
        }

        protected function set_progress_status($file, $status)
        {
            $progress = json_decode(file_get_contents($this->progress_file), true);
            $progress[$file]['status'] = $status;
            $progress[$file]['status_timestamp'] = time(); // Aggiungi timestamp per tracciare quando è stato impostato
            file_put_contents($this->progress_file, json_encode($progress));
        }

        protected function get_progress_status($file)
        {
            $progress = json_decode(file_get_contents($this->progress_file), true);
            return isset($progress[$file]['status']) ? $progress[$file]['status'] : 'not_started';
        }

        protected function get_memory_usage()
        {
            $mem_usage = memory_get_usage(true);

            if ($mem_usage < 1024) {
                $memory = $mem_usage . " bytes";
            } elseif ($mem_usage < 1048576) {
                $memory = round($mem_usage / 1024, 2) . " KB";
            } else {
                $memory = round($mem_usage / 1048576, 2) . " MB";
            }

            return $memory;
        }

        protected function get_memory_usage_mb()
        {
            return round(memory_get_usage(true) / 1048576, 2);
        }

        protected function should_continue_immediately($file_name)
        {
            $current_memory = $this->get_memory_usage_mb();
            $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            
            $progress = $this->get_progress($file_name);
            $has_more_work = $progress['processed'] < $progress['total'];
            
            $memory_ok = $current_memory < $this->memory_limit_mb;
            $time_ok = $execution_time < $this->max_execution_time;
            
            $this->log("Memory check: {$current_memory}MB/{$this->memory_limit_mb}MB - Time: {$execution_time}s/{$this->max_execution_time}s - More work: " . ($has_more_work ? 'yes' : 'no'));
            
            return $has_more_work && $memory_ok && $time_ok;
        }

        protected function should_auto_trigger($file_name)
        {
            if (!$this->should_continue_immediately($file_name)) {
                return false;
            }
            
            $status = $this->get_progress_status($file_name);
            $progress = $this->get_progress($file_name);
            
            // Auto-trigger solo se:
            // 1. Status è completed o not_started
            // 2. C'è ancora lavoro da fare (processed < total)
            return in_array($status, ['completed', 'not_started']) && 
                   $progress['processed'] < $progress['total'] && 
                   $progress['total'] > 0;
        }

        protected function set_progress_status_smart($file, $status, $auto_continue = true)
        {
            $this->set_progress_status($file, $status);
            
            if ($auto_continue && $status === 'completed' && $this->should_auto_trigger($file)) {
                $this->log("Auto-triggering immediate continuation for $file");
                return 'auto_continue';
            }
            
            return $status;
        }
        
        protected function load_csv_count_cache()
        {
            if (file_exists($this->csv_count_cache_file)) {
                $cache_content = file_get_contents($this->csv_count_cache_file);
                $this->csv_count_cache = json_decode($cache_content, true) ?: [];
                
                // Pulisci cache vecchia (più di 24 ore)
                $current_time = time();
                foreach ($this->csv_count_cache as $key => $cached) {
                    if (isset($cached['timestamp']) && ($current_time - $cached['timestamp']) > 86400) {
                        unset($this->csv_count_cache[$key]);
                    }
                }
            }
        }
        
        protected function save_csv_count_cache()
        {
            $cache_content = json_encode($this->csv_count_cache, JSON_PRETTY_PRINT);
            file_put_contents($this->csv_count_cache_file, $cache_content);
        }

    }
}