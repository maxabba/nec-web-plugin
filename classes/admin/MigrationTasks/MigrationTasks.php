<?php

namespace Dokan_Mods\Migration_Tasks;

use Exception;
use RuntimeException;
use SplFileObject;
use WP_Query;

// Custom exception for permanent failures
class PermanentFailureException extends Exception {}

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

        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            $this->upload_dir = $upload_dir;
            $this->progress_file = $progress_file;
            $this->log_file = $log_file;
            $this->batch_size = $batch_size;
        }

        protected function countCsvRows(SplFileObject $file)
        {
            $file_path = $file->getRealPath();
            $this->log("Inizio conteggio righe CSV per file: $file_path");
            
            // Prima detecta il formato del CSV
            $csv_format = $this->detectCsvFormat($file_path);
            
            // SEMPRE usa il conteggio CSV per accuratezza, specialmente con delimitatore ';'
            $csv_count = $this->countCsvRowsWithFormat($file, $csv_format);
            
            // Confronta con conteggio semplice solo per logging
            if ($csv_format['line_count'] > 0) {
                $simple_data_rows = max(0, $csv_format['line_count'] - 1);
                $ratio = $simple_data_rows > 0 ? $csv_format['line_count'] / ($csv_count + 1) : 0;
                
                $this->log("Conteggio linee fisiche: {$csv_format['line_count']}, Conteggio record CSV: " . ($csv_count + 1));
                
                if ($ratio > 5) {
                    $this->log("NOTA: Il file contiene campi multi-riga (ratio: " . round($ratio, 1) . ":1)");
                    $this->log("Questo è normale per CSV con testo su più righe dentro virgolette");
                }
            }
            
            $this->log("Conteggio CSV finale: $csv_count righe dati (escludendo header)");
            return $csv_count;
        }

        protected function countCsvRowsWithFormat(SplFileObject $file, array $csv_format)
        {
            $count = 0;
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
                }
                
                $file->rewind();
                
                // Resetta i parametri CSV ai default
                $file->setCsvControl(',', '"', '\\');
                
                $data_rows = max(0, $count - 1);
                $this->log("Conteggio CSV completato: $count righe totali, $data_rows righe dati (escludendo header)");
                
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
            $this->log("Verifica conteggio CSV: conteggio atteso = $expected_count");
            
            // Usa il conteggio semplice delle linee per verifica
            $file_path = $file->getRealPath();
            $simple_count = $this->countLinesSimple($file_path);
            $simple_data_rows = max(0, $simple_count - 1);
            
            $this->log("Verifica con conteggio semplice: $simple_count righe totali, $simple_data_rows righe dati");
            
            // Se il conteggio semplice è nell'ordine di grandezza corretto (20k invece di 200k)
            if ($simple_data_rows < 50000 && $expected_count > 100000) {
                $this->log("ERRORE CRITICO: Ordine di grandezza errato! Semplice: $simple_data_rows, CSV: $expected_count");
                $this->log("Il conteggio CSV sembra errato, usando il conteggio semplice");
                return false; // Forza il ricalcolo
            }
            
            // Altrimenti verifica la differenza normale
            $difference = abs($expected_count - $simple_data_rows);
            $tolerance = max(100, $expected_count * 0.05); // 5% di tolleranza o min 100 righe
            
            if ($difference > $tolerance) {
                $this->log("ATTENZIONE: Discrepanza nel conteggio righe! CSV: $expected_count, Semplice: $simple_data_rows, Differenza: $difference");
                return true; // Accetta comunque se non è un errore di ordine di grandezza
            }
            
            $this->log("Verifica conteggio CSV superata (differenza: $difference righe)");
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

        // get_existing_users_by_old_ids method moved to USER MANAGEMENT UTILITIES section below

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
                
                // Verifica del conteggio con doppio controllo
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
                
                // NON impostare completed qui - lascia che il normale flusso continui
                // Il progresso è ora inizializzato correttamente
                $this->log("Inizializzazione completata per $file_name - pronto per processamento");
                return ['processed' => 0, 'total' => $total_rows, 'percentage' => 0];
            }

            // Controllo sanità: se il conteggio sembra errato
            if ($total_rows > 100000) { // Se supera 100k righe, potrebbe essere un errore
                $this->log("ATTENZIONE: Conteggio righe sospetto per $file_name: $total_rows righe. Riconteggio...");
                
                $new_count = $this->countCsvRows($file);
                
                if (abs($new_count - $total_rows) > ($total_rows * 0.1)) { // Se differenza > 10%
                    $this->log("CORREZIONE: Rilevato conteggio errato. Vecchio: $total_rows, Nuovo: $new_count. Reset progresso.");
                    
                    $this->resetProgressForFile($file_name);
                    
                    // Ricomincia con il conteggio corretto
                    $this->update_progress($file_name, 0, $new_count);
                    $this->set_progress_status($file_name, 'completed');
                    
                    return ['processed' => 0, 'total' => $new_count, 'percentage' => 0];
                }
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
            $progress[$file]['status_timestamp'] = time();
            $progress[$file]['last_update'] = date('Y-m-d H:i:s');
            
            file_put_contents($this->progress_file, json_encode($progress));
            
            $this->log("Status aggiornato per $file: $status (timestamp: {$progress[$file]['status_timestamp']})");
        }

        protected function get_progress_status($file)
        {
            $progress = json_decode(file_get_contents($this->progress_file), true);
            return $progress[$file]['status'];
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
            
            return in_array($status, ['completed', 'not_started']);
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

        // ============================================================================
        // CENTRALIZED IMAGE QUEUE MANAGEMENT
        // ============================================================================

        /**
         * Mapping from image types to ACF field names
         */
        protected static $image_type_mapping = [
            // Necrologi
            'profile_photo' => 'fotografia',
            'manifesto_image' => 'immagine_annuncio_di_morte',
            
            // Ricorrenze  
            'trigesimo' => 'immagine_annuncio_trigesimo',
            'anniversario' => 'immagine_annuncio_anniversario',
            
            // Ringraziamenti
            'ringraziamento' => 'immagine_ringraziamento',
            
            // Manifesti (if needed)
            'manifesto' => 'immagine_manifesto'
        ];

        /**
         * Add images to queue with standardized CSV format - SIMPLE VERSION
         * Format: image_type,image_url,post_id,author_id,retry_count,processed
         * Simplified version for reliable CSV writing without complex optimizations
         */
        protected function addToImageQueueSimple(array $images, string $queue_file): bool
        {
            if (empty($images)) {
                $this->log("No images to add to queue");
                return true;
            }

            $queue_path = $this->upload_dir . $queue_file;
            $this->log("Adding " . count($images) . " images to queue: $queue_file");
            
            // Ensure directory exists
            if (!is_dir($this->upload_dir)) {
                if (!mkdir($this->upload_dir, 0755, true)) {
                    $this->log("ERROR: Failed to create upload directory: " . $this->upload_dir);
                    return false;
                }
            }
            
            if (!is_writable($this->upload_dir)) {
                $this->log("ERROR: Upload directory is not writable: " . $this->upload_dir);
                return false;
            }
            
            try {
                // Simple duplicate check - read last 1000 lines if file exists
                $existing_keys = [];
                if (file_exists($queue_path)) {
                    $lines = file($queue_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $check_lines = array_slice($lines, -1000); // Only check last 1000 entries

                    foreach ($check_lines as $line) {
                        $data = str_getcsv($line);
                        if (count($data) >= 4) {
                            // Usa md5 per un checksum veloce della chiave
                            $key_parts = array_slice($data, 0, 4);
                            $key = md5(implode('|', $key_parts));
                            $existing_keys[$key] = true;
                        }
                    }
                }

                // Filter out duplicates
                $unique_images = [];
                $duplicates_count = 0;

                foreach ($images as $image) {
                    if (count($image) < 4) continue;

                    list($image_type, $image_url, $post_id, $author_id) = $image;
                    if (empty($image_url)) continue;

                    // Crea checksum per la chiave corrente
                    $key = md5("$image_type|$image_url|$post_id|$author_id");
                    if (isset($existing_keys[$key])) {
                        $duplicates_count++;
                        continue;
                    }

                    $existing_keys[$key] = true; // Prevent duplicates within this batch
                    $unique_images[] = [$image_type, $image_url, $post_id, $author_id, 0, 0];
                }
                
                if (empty($unique_images)) {
                    $this->log("All " . count($images) . " images were duplicates, nothing to add");
                    return true;
                }
                
                // Optimized file append using SplFileObject
                try {
                    $file = new SplFileObject($queue_path, 'a');
                    $file->setCsvControl(',', '"', '\\');
                    
                    $written_count = 0;
                    foreach ($unique_images as $image_data) {
                        if ($file->fputcsv($image_data) !== false) {
                            $written_count++;
                        } else {
                            $this->log("ERROR: Failed to write image data: " . implode(',', $image_data));
                        }
                    }
                    
                    // SplFileObject automatically handles file closure
                    $file = null;
                    
                } catch (RuntimeException $e) {
                    $this->log("ERROR: Cannot open queue file for writing: $queue_path - " . $e->getMessage());
                    return false;
                }
                
                $this->log("Successfully added $written_count images to queue (skipped $duplicates_count duplicates)");
                return $written_count > 0;
                
            } catch (Exception $e) {
                $this->log("ERROR: Exception in addToImageQueueSimple: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Add images to queue with standardized CSV format - OPTIMIZED VERSION
         * Format: image_type,image_url,post_id,author_id,retry_count,processed
         * Features: file locking, efficient duplicate detection, batch processing
         * NOTE: This complex version is kept for backward compatibility but addToImageQueueSimple() is preferred
         */
        protected function addToImageQueue(array $images, string $queue_file): bool
        {
            $this->log("DEBUG: addToImageQueue called with " . count($images) . " images for file: $queue_file");
            
            if (empty($images)) {
                $this->log("DEBUG: Images array is empty, returning true");
                return true;
            }

            $queue_path = $this->upload_dir . $queue_file;
            $this->log("DEBUG: Queue path: $queue_path");
            
            // Ensure directory exists and is writable
            if (!is_dir($this->upload_dir)) {
                $this->log("DEBUG: Creating upload directory: " . $this->upload_dir);
                if (!mkdir($this->upload_dir, 0755, true)) {
                    $this->log("ERROR: Failed to create upload directory: " . $this->upload_dir);
                    return false;
                }
            }
            
            $this->log("DEBUG: Upload dir exists: " . (is_dir($this->upload_dir) ? 'YES' : 'NO'));
            $this->log("DEBUG: Upload dir writable: " . (is_writable($this->upload_dir) ? 'YES' : 'NO'));
            
            if (!is_writable($this->upload_dir)) {
                $this->log("ERROR: Upload directory is not writable: " . $this->upload_dir);
                return false;
            }
            
            $start_time = microtime(true);
            $added_count = 0;
            $duplicate_count = 0;
            
            try {
                // Step 1: Build efficient duplicate index
                $this->log("DEBUG: Building duplicate index...");
                $existing_queue = $this->buildDuplicateIndex($queue_path);
                $this->log("DEBUG: Duplicate index built with " . count($existing_queue) . " entries");
                
                // Step 2: Filter out duplicates from incoming batch
                $unique_images = [];
                foreach ($images as $image) {
                    list($image_type, $image_url, $post_id, $author_id) = $image;
                    
                    if (empty($image_url)) {
                        continue;
                    }

                    $queue_key = implode('|', [$image_type, $image_url, $post_id, $author_id]);
                    if (isset($existing_queue[$queue_key])) {
                        $duplicate_count++;
                        continue;
                    }
                    
                    // Mark as seen to prevent duplicates within this batch
                    $existing_queue[$queue_key] = true;
                    $unique_images[] = [$image_type, $image_url, $post_id, $author_id];
                }
                
                if (empty($unique_images)) {
                    $this->log("DEBUG: No new images to add (all $duplicate_count were duplicates)");
                    return true;
                }
                
                $this->log("DEBUG: Found " . count($unique_images) . " unique images to add, $duplicate_count duplicates skipped");
                
                // Step 3: Acquire file lock and append new images (with fallback)
                $this->log("DEBUG: Attempting to acquire file lock for: $queue_path");
                $file_handle = $this->lockFile($queue_path, 'a');
                $using_fallback = false;
                
                if (!$file_handle) {
                    $this->log("WARNING: Failed to acquire lock for $queue_file, trying fallback without locking");
                    $file_handle = fopen($queue_path, 'a');
                    $using_fallback = true;
                    
                    if (!$file_handle) {
                        $this->log("ERROR: Failed to open file even without locking: $queue_path - " . (error_get_last()['message'] ?? 'Unknown error'));
                        return false;
                    }
                }
                
                $this->log("DEBUG: File opened successfully " . ($using_fallback ? '(WITHOUT locking)' : '(WITH locking)'));
                $this->log("DEBUG: File handle valid: " . (is_resource($file_handle) ? 'YES' : 'NO'));
                
                // Validate file handle before proceeding
                if (!is_resource($file_handle)) {
                    $this->log("ERROR: File handle is not a valid resource");
                    return false;
                }
                
                // Write unique images with CSV control and error capture
                $this->log("DEBUG: Writing " . count($unique_images) . " images to CSV");
                foreach ($unique_images as $idx => $image) {
                    try {
                        $csv_row = [$image[0], $image[1], $image[2], $image[3], 0, 0];
                        $this->log("DEBUG: Attempting to write row $idx: " . implode(',', $csv_row));
                        
                        $bytes_written = fputcsv($file_handle, $csv_row);
                        
                        if ($bytes_written === false) {
                            $last_error = error_get_last();
                            $this->log("ERROR: fputcsv failed for image $idx - " . ($last_error['message'] ?? 'Unknown error'));
                            
                            // Try direct fwrite as fallback
                            $csv_string = '"' . implode('","', $csv_row) . '"' . PHP_EOL;
                            $fallback_bytes = fwrite($file_handle, $csv_string);
                            if ($fallback_bytes !== false) {
                                $this->log("DEBUG: Fallback fwrite succeeded for image $idx, wrote $fallback_bytes bytes");
                                $added_count++;
                            } else {
                                $this->log("ERROR: Even fallback fwrite failed for image $idx");
                            }
                        } else {
                            $this->log("DEBUG: Image $idx written successfully, $bytes_written bytes");
                            $added_count++;
                        }
                        
                    } catch (Exception $e) {
                        $this->log("ERROR: Exception writing image $idx: " . $e->getMessage());
                    }
                }
                
                // Step 4: Update duplicate index file
                $index_file = $queue_path . '.index';
                $this->log("DEBUG: Updating index file: $index_file");
                $index_result = file_put_contents($index_file, serialize($existing_queue));
                $this->log("DEBUG: Index file updated, bytes written: " . ($index_result ?: 'FAILED'));
                
                // Step 5: Clean up file handle
                if (is_resource($file_handle)) {
                    if (!$using_fallback) {
                        $this->log("DEBUG: Releasing file lock");
                        $this->unlockFile($file_handle);
                    } else {
                        $this->log("DEBUG: Closing file (was opened without lock)");
                        fclose($file_handle);
                    }
                } else {
                    $this->log("WARNING: File handle was not a resource during cleanup");
                }
                
                $execution_time = microtime(true) - $start_time;
                $this->log("Image queue updated: $added_count added, $duplicate_count duplicates skipped in " . round($execution_time, 3) . "s");
                
                return true;

            } catch (Exception $e) {
                $this->log("ERROR: Exception in addToImageQueue: " . $e->getMessage());
                $this->log("ERROR: Stack trace: " . $e->getTraceAsString());
                return false;
            }
        }

        /**
         * Process image queue with standardized logic
         */
        protected function processImageQueue(string $queue_file, string $cron_hook, int $max_per_batch = 500): void
        {
            $queue_path = $this->upload_dir . $queue_file;

            // Check if processing is finished (using dedicated image queue methods)
            if ($this->getImageQueueProgressStatus($queue_file) === 'finished') {
                $this->log("Image processing finished. Removing schedule.");
                wp_clear_scheduled_hook($cron_hook);
                return;
            }

            if (!file_exists($queue_path)) {
                $this->log("Queue file does not exist: $queue_file");
                return;
            }

            // Check if process is already active (using dedicated image queue methods)
            if ($this->getImageQueueProgressStatus($queue_file) === 'ongoing') {
                if ($this->isImageQueueProcessActive($queue_file)) {
                    $this->log("Image processing already in progress for $queue_file");
                    return;
                } else {
                    $this->log("Image processing detected as stuck for $queue_file - auto-continuing");
                    $this->setImageQueueProgressStatus($queue_file, 'completed');
                }
            }

            try {
                // Initialize progress if not exists and get current progress
                $progress = $this->getImageQueueProgress($queue_file);
                $processed_count = $progress['processed'] ?? 0;
                
                // Initialize progress file if first time processing
                if ($progress['total'] === 0) {
                    $total_rows_count = $this->countCsvRowsEfficient($queue_path);
                    $this->updateImageQueueProgress($queue_file, $processed_count, $total_rows_count);
                    $this->log("Initialized image queue progress: 0/$total_rows_count");
                }
                
                // CSV sync no longer needed - progress tracked in JSON only
                
                // Read next batch of rows starting from current JSON progress position
                $rows_to_process = [];
                $total_rows = 0;
                $skipped_rows = 0;
                
                $file = new SplFileObject($queue_path, 'r');
                $file->setFlags(SplFileObject::READ_CSV);
                $file->setCsvControl(',', '"', '\\');
                
                foreach ($file as $data) {
                    if (empty($data) || count($data) < 4) {
                        continue;
                    }
                    
                    $total_rows++;
                    
                    // Skip rows that have already been processed according to JSON progress
                    if ($total_rows <= $processed_count) {
                        $skipped_rows++;
                        continue;
                    }
                    
                    // Collect next batch to process
                    if (count($rows_to_process) < $max_per_batch) {
                        $rows_to_process[] = $data;
                    } else {
                        break; // We have enough for this batch
                    }
                }
                $file = null;
                
                $this->log("Sequential processing: skipped $skipped_rows already processed rows, found " . count($rows_to_process) . " rows to process from total $total_rows");

                if (empty($rows_to_process)) {
                    $this->setImageQueueProgressStatus($queue_file, 'finished');
                    $this->log("All images processed for $queue_file");
                    if (file_exists($queue_path)) {
                        unlink($queue_path);
                        $this->log("Empty queue file removed: $queue_file");
                    }
                    wp_clear_scheduled_hook($cron_hook);
                    $this->log("Cron job cleared: $cron_hook");
                    return;
                }

                $this->setImageQueueProgressStatus($queue_file, 'ongoing');

                // Process batch with real-time updates
                $batch_count = 0;
                
                // Calculate actual processed count from progress (more reliable than calculation)
                $current_processed = $progress['processed'] ?? 0;

                foreach ($rows_to_process as $index => $data) {
                    if ($batch_count >= $max_per_batch) {
                        break;
                    }

                    list($image_type, $image_url, $post_id, $author_id, $retry_count) = $data;
                    
                    try {
                        if ($this->downloadAndAttachImage($image_type, $image_url, (int)$post_id, (int)$author_id)) {
                            // Increment JSON progress only
                            $current_progress = $this->getImageQueueProgress($queue_file);
                            $new_processed = $current_progress['processed'] + 1;
                            $this->updateImageQueueProgress($queue_file, $new_processed, $current_progress['total']);
                            $batch_count++;
                            
                            // Get updated progress for logging
                            $updated_progress = $this->getImageQueueProgress($queue_file);
                            $current_processed = $updated_progress['processed'];
                            $percentage = $total_rows > 0 ? round(($current_processed / $total_rows) * 100, 2) : 0;
                            
                            $this->log("Image processed successfully: $image_url - Progress: $current_processed/$total_rows ({$percentage}%)");
                        } else {
                            $retry_count++;
                            $this->log("Error downloading image $image_url (attempt $retry_count/5)");
                            
                            // Retry count tracking no longer needed - progress tracked in JSON only
                            
                            if ($retry_count >= 5) {
                                // Mark failed image as processed in JSON progress only
                                $current_progress = $this->getImageQueueProgress($queue_file);
                                $new_processed = $current_progress['processed'] + 1;
                                $this->updateImageQueueProgress($queue_file, $new_processed, $current_progress['total']);
                                $batch_count++;
                                
                                // Get updated progress for logging
                                $updated_progress = $this->getImageQueueProgress($queue_file);
                                $current_processed = $updated_progress['processed'];
                                $percentage = $total_rows > 0 ? round(($current_processed / $total_rows) * 100, 2) : 0;
                                
                                $this->log("Max retries reached for $image_url - Progress: $current_processed/$total_rows ({$percentage}%)");
                            } else {
                                // Update retry count in the row - but don't mark as processed yet
                                $rows_to_process[$index][4] = $retry_count;
                            }
                        }
                    } catch (PermanentFailureException $e) {
                        // Permanent failures (404, 403, malformed URL) - mark as processed immediately
                        $this->log("Permanent failure for $image_url: " . $e->getMessage());
                        
                        // Update JSON progress only
                        $current_progress = $this->getImageQueueProgress($queue_file);
                        $new_processed = $current_progress['processed'] + 1;
                        $this->updateImageQueueProgress($queue_file, $new_processed, $current_progress['total']);
                        $batch_count++;
                        
                        // Get updated progress for logging
                        $updated_progress = $this->getImageQueueProgress($queue_file);
                        $current_processed = $updated_progress['processed'];
                        $percentage = $total_rows > 0 ? round(($current_processed / $total_rows) * 100, 2) : 0;
                        
                        $this->log("Skipped permanently failed image - Progress: $current_processed/$total_rows ({$percentage}%)");
                    }
                }

                // Progress is tracked in JSON only - no CSV modification needed
                
                // Final progress check and logging
                $updated_progress = $this->getImageQueueProgress($queue_file);
                $current_processed = $updated_progress['processed'];
                $final_percentage = $total_rows > 0 ? round(($current_processed / $total_rows) * 100, 2) : 0;
                $this->log("Batch completed: processed $batch_count images - Total progress: $current_processed/$total_rows ({$final_percentage}%)");

                if ($current_processed >= $total_rows) {
                    $this->setImageQueueProgressStatus($queue_file, 'finished');
                    wp_clear_scheduled_hook($cron_hook);
                    $this->log("Image processing completed for $queue_file - cron job cleared: $cron_hook");
                } else {
                    $this->setImageQueueProgressStatus($queue_file, 'completed');
                    $this->log("Image batch completed: $current_processed/$total_rows processed ({$final_percentage}%) - Next batch will continue from position " . ($current_processed + 1));
                }

            } catch (Exception $e) {
                $this->log("ERROR: Exception in image queue processing: " . $e->getMessage());
                $this->log("ERROR: Stack trace: " . $e->getTraceAsString());
                $this->setImageQueueProgressStatus($queue_file, 'error');
            }
        }
        
        /**
         * Validate image queue processing integrity
         */
        protected function validateImageQueueIntegrity(string $queue_file): array
        {
            $queue_path = $this->upload_dir . $queue_file;
            $validation_result = [
                'total_rows' => 0,
                'processed_rows' => 0,
                'unprocessed_rows' => 0,
                'invalid_rows' => 0,
                'issues' => []
            ];
            
            if (!file_exists($queue_path)) {
                $validation_result['issues'][] = "Queue file does not exist: $queue_file";
                return $validation_result;
            }
            
            try {
                $file = new SplFileObject($queue_path, 'r');
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $file->setCsvControl(',', '"', '\\');
                
                $row_index = 0;
                foreach ($file as $data) {
                    $row_index++;
                    
                    if (empty($data) || count($data) < 6) {
                        $validation_result['invalid_rows']++;
                        $validation_result['issues'][] = "Invalid row $row_index: " . json_encode($data);
                        continue;
                    }
                    
                    $validation_result['total_rows']++;
                    $processed = (int)($data[5] ?? 0);
                    
                    if ($processed === 1) {
                        $validation_result['processed_rows']++;
                    } else {
                        $validation_result['unprocessed_rows']++;
                    }
                }
                
                $file = null;
                
                // Compare with progress file
                $progress = $this->getImageQueueProgress($queue_file);
                if ($progress['processed'] !== $validation_result['processed_rows']) {
                    $validation_result['issues'][] = "Progress mismatch: progress file shows {$progress['processed']} but CSV has {$validation_result['processed_rows']} processed rows";
                }
                
                if ($progress['total'] !== $validation_result['total_rows']) {
                    $validation_result['issues'][] = "Total mismatch: progress file shows {$progress['total']} but CSV has {$validation_result['total_rows']} total rows";
                }
                
            } catch (Exception $e) {
                $validation_result['issues'][] = "Error validating queue: " . $e->getMessage();
            }
            
            return $validation_result;
        }
        
        /**
         * Test and validate sequential processing works correctly
         */
        protected function testSequentialProcessing(string $queue_file): array
        {
            $queue_path = $this->upload_dir . $queue_file;
            $test_result = [
                'success' => false,
                'issues' => [],
                'statistics' => [
                    'total_rows' => 0,
                    'processed_rows' => 0,
                    'sequential_blocks' => 0,
                    'gap_count' => 0,
                    'first_unprocessed' => null,
                    'last_processed' => null
                ]
            ];
            
            if (!file_exists($queue_path)) {
                $test_result['issues'][] = "Queue file does not exist: $queue_file";
                return $test_result;
            }
            
            try {
                $file = new SplFileObject($queue_path, 'r');
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $file->setCsvControl(',', '"', '\\');
                
                $row_index = 0;
                $last_processed_index = -1;
                $in_processed_block = false;
                $blocks = 0;
                $gaps = 0;
                $first_unprocessed = null;
                
                foreach ($file as $data) {
                    if (empty($data) || count($data) < 6) {
                        $row_index++;
                        continue;
                    }
                    
                    $test_result['statistics']['total_rows']++;
                    $processed = (int)($data[5] ?? 0);
                    
                    if ($processed === 1) {
                        $test_result['statistics']['processed_rows']++;
                        $test_result['statistics']['last_processed'] = $row_index;
                        
                        if (!$in_processed_block) {
                            $blocks++;
                            $in_processed_block = true;
                        }
                        
                        // Check for gaps
                        if ($last_processed_index >= 0 && $row_index > $last_processed_index + 1) {
                            $gaps++;
                        }
                        
                        $last_processed_index = $row_index;
                    } else {
                        if ($first_unprocessed === null) {
                            $test_result['statistics']['first_unprocessed'] = $row_index;
                        }
                        $in_processed_block = false;
                    }
                    
                    $row_index++;
                }
                
                $file = null;
                
                $test_result['statistics']['sequential_blocks'] = $blocks;
                $test_result['statistics']['gap_count'] = $gaps;
                
                // Validation logic
                $test_result['success'] = true;
                
                // Check for excessive gaps (more than 5% of processed rows suggests non-sequential processing)
                if ($test_result['statistics']['processed_rows'] > 0) {
                    $gap_ratio = $gaps / $test_result['statistics']['processed_rows'];
                    if ($gap_ratio > 0.05) {
                        $test_result['issues'][] = "High gap ratio detected: {$gap_ratio} (gaps: $gaps, processed: {$test_result['statistics']['processed_rows']})";
                        $test_result['success'] = false;
                    }
                }
                
                // Check if we have too many sequential blocks (should be 1 for perfect sequential processing)
                if ($blocks > 3) {
                    $test_result['issues'][] = "Too many sequential blocks: $blocks (indicates non-sequential processing)";
                    $test_result['success'] = false;
                }
                
                // Compare with progress file
                $progress = $this->getImageQueueProgress($queue_file);
                if ($progress['processed'] !== $test_result['statistics']['processed_rows']) {
                    $test_result['issues'][] = "Progress file mismatch: progress shows {$progress['processed']}, CSV has {$test_result['statistics']['processed_rows']}";
                    $test_result['success'] = false;
                }
                
                if ($test_result['success']) {
                    $this->log("Sequential processing validation PASSED for $queue_file");
                } else {
                    $this->log("Sequential processing validation FAILED for $queue_file: " . implode(', ', $test_result['issues']));
                }
                
            } catch (Exception $e) {
                $test_result['issues'][] = "Error during validation: " . $e->getMessage();
                $test_result['success'] = false;
            }
            
            return $test_result;
        }
        
        /**
         * Mark a single row as processed immediately (real-time CSV update)
         */
        protected function markSingleRowAsProcessed(string $queue_path, int $absolute_row_index): bool
        {
            $temp_file = $queue_path . '.single_tmp';
            
            try {
                $read_file = new SplFileObject($queue_path, 'r');
                $read_file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $read_file->setCsvControl(',', '"', '\\');
                
                $write_file = new SplFileObject($temp_file, 'w');
                
                $current_row_index = 0;
                $updated = false;
                
                foreach ($read_file as $data) {
                    if (empty($data) || count($data) < 6) {
                        $current_row_index++;
                        continue;
                    }
                    
                    // Mark this specific row as processed
                    if ($current_row_index === $absolute_row_index && $data[5] == 0) {
                        $data[5] = 1; // Set processed flag
                        $updated = true;
                        $this->log("Marked CSV row $absolute_row_index as processed");
                    }
                    
                    $write_file->fputcsv($data);
                    $current_row_index++;
                }
                
                $read_file = null;
                $write_file = null;
                
                // Atomic replacement only if we actually updated something
                if ($updated && rename($temp_file, $queue_path)) {
                    return true;
                } else {
                    if (file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                    return false;
                }
                
            } catch (Exception $e) {
                $this->log("ERROR: Failed to mark single row as processed: " . $e->getMessage());
                
                // Cleanup on error
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                
                return false;
            }
        }


        /**
         * Validate image URL format
         */
        protected function isValidImageUrl(string $url): bool
        {
            // Basic URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }
            
            // Check for malformed URLs ending with just '-'
            if (preg_match('/\/necrologi\/-$/', $url)) {
                return false;
            }
            
            // Check that URL has a proper filename with image extension
            $path_parts = pathinfo(parse_url($url, PHP_URL_PATH));
            
            // Must have a filename
            if (empty($path_parts['filename']) || $path_parts['filename'] === '-') {
                return false;
            }
            
            // Check for valid image extensions
            if (!isset($path_parts['extension'])) {
                return false;
            }
            
            $valid_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            if (!in_array(strtolower($path_parts['extension']), $valid_extensions)) {
                return false;
            }
            
            return true;
        }

        /**
         * Download image and attach to post with ACF field update
         */
        protected function downloadAndAttachImage(string $image_type, string $image_url, int $post_id, int $author_id): bool
        {
            try {
                // Validate URL before attempting download
                if (!$this->isValidImageUrl($image_url)) {
                    throw new PermanentFailureException("Invalid or malformed image URL: $image_url");
                }
                
                // Download image
                $ch = curl_init($image_url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30
                ]);

                $image_data = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                // Check for curl errors
                if ($curl_error) {
                    $this->log("cURL error downloading $image_url: $curl_error");
                    throw new RuntimeException("Network error: $curl_error");
                }

                // Handle specific HTTP errors
                if ($http_code === 404 || $http_code === 403) {
                    throw new PermanentFailureException("Permanent failure for $image_url: HTTP $http_code");
                }
                
                if ($http_code !== 200 || !$image_data) {
                    throw new RuntimeException("Download failed with HTTP code: $http_code");
                }

                // Save to uploads directory
                $upload_dir = wp_upload_dir();
                $filename = wp_unique_filename($upload_dir['path'], basename($image_url));
                $upload_file = $upload_dir['path'] . '/' . $filename;

                if (!file_put_contents($upload_file, $image_data)) {
                    throw new RuntimeException("Failed to save image");
                }

                // Create attachment
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

                // Generate attachment metadata
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata(
                    $attach_id,
                    wp_generate_attachment_metadata($attach_id, $upload_file)
                );

                // Update ACF field based on image type
                return $this->updateImageField($image_type, $attach_id, $post_id);

            } catch (PermanentFailureException $e) {
                // Log permanent failures and re-throw to handle in caller
                $this->log("Permanent failure: " . $e->getMessage());
                throw $e;
            } catch (Exception $e) {
                $this->log("Error processing image $image_url: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Update ACF field based on image type mapping
         */
        protected function updateImageField(string $image_type, int $attachment_id, int $post_id): bool
        {
            if (!isset(self::$image_type_mapping[$image_type])) {
                $this->log("Unknown image type: $image_type");
                return false;
            }
            
            $acf_field = self::$image_type_mapping[$image_type];
            update_field($acf_field, $attachment_id, $post_id);
            
            $this->log("ACF field '$acf_field' updated with attachment ID $attachment_id for post $post_id");
            return true;
        }


        /**
         * Mark specific rows as processed in the queue file
         */
        protected function markRowsAsProcessed(string $queue_path, array &$all_rows, array $indices_to_mark): bool
        {
            try {
                // Mark rows as processed
                foreach ($indices_to_mark as $index) {
                    if (isset($all_rows[$index])) {
                        $all_rows[$index][5] = 1; // Set processed flag
                    }
                }

                // Rewrite entire file with updated data
                $temp_file = $queue_path . '.tmp';
                $write_file = new SplFileObject($temp_file, 'w');
                $write_file->setCsvControl(',', '"', '\\');
                
                // Read original file and write updated version
                $original_file = new SplFileObject($queue_path, 'r');
                $original_file->setFlags(SplFileObject::READ_CSV);
                $original_file->setCsvControl(',', '"', '\\');
                
                $row_index = 0;
                foreach ($original_file as $data) {
                    if (empty($data) || count($data) < 6) continue;
                    
                    // Use updated row if it was modified, otherwise use original
                    $processed = (int)($data[5] ?? 0);
                    if ($processed === 0 && isset($all_rows[$row_index])) {
                        $write_file->fputcsv($all_rows[$row_index]);
                    } else {
                        $write_file->fputcsv($data);
                    }
                    $row_index++;
                }
                
                $original_file = null;
                $write_file = null;

                // Atomic replacement
                return rename($temp_file, $queue_path);

            } catch (Exception $e) {
                $this->log("Error marking rows as processed: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Schedule image processing cron
         */
        protected function scheduleImageProcessing(string $cron_hook): void
        {
            if (!wp_next_scheduled($cron_hook)) {
                $this->log("Scheduling image processing: $cron_hook");
                wp_schedule_event(time(), 'every_one_minute', $cron_hook);
            } else {
                $this->log("Image processing already scheduled: $cron_hook");
            }
        }

        /**
         * Check if process is active based on timestamp
         */
        protected function is_process_active(string $file): bool
        {
            if (!file_exists($this->progress_file)) {
                return false;
            }
            
            $progress = json_decode(file_get_contents($this->progress_file), true);
            
            if (!isset($progress[$file]['status_timestamp'])) {
                $this->log("No timestamp found for $file - considered inactive");
                return false;
            }
            
            $elapsed = time() - $progress[$file]['status_timestamp'];
            $timeout_threshold = 90; // 90 seconds
            
            $this->log("Activity check for $file: elapsed {$elapsed}s, threshold {$timeout_threshold}s");
            
            return $elapsed <= $timeout_threshold;
        }

        /**
         * Efficient duplicate detection using hash index file
         * This method builds and maintains a lightweight index for duplicate detection
         */
        protected function buildDuplicateIndex(string $queue_path): array
        {
            $index_file = $queue_path . '.index';
            $existing_queue = [];
            
            // Try to load existing index first for performance
            if (file_exists($index_file) && filemtime($index_file) >= filemtime($queue_path)) {
                $index_data = file_get_contents($index_file);
                if ($index_data) {
                    $existing_queue = unserialize($index_data) ?: [];
                    $this->log("Loaded duplicate index with " . count($existing_queue) . " entries");
                    return $existing_queue;
                }
            }
            
            // Rebuild index if not available or outdated
            if (!file_exists($queue_path)) {
                return [];
            }
            
            $this->log("Building duplicate index for queue file...");
            $start_time = microtime(true);
            
            try {
                $read_file = new SplFileObject($queue_path, 'r');
                $read_file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $read_file->setCsvControl(',', '"', '\\');
                
                $row_count = 0;
                foreach ($read_file as $data) {
                    if (!$data || count($data) < 4) continue;
                    
                    $key = implode('|', array_slice($data, 0, 4)); // image_type|url|post_id|author_id
                    $existing_queue[$key] = true;
                    $row_count++;
                    
                    // Progress logging for large files
                    if ($row_count % 50000 === 0) {
                        $this->log("Index building progress: $row_count rows processed");
                    }
                }
                
                $read_file = null;
                
                // Save index for future use
                file_put_contents($index_file, serialize($existing_queue));
                
                $build_time = microtime(true) - $start_time;
                $this->log("Duplicate index built: " . count($existing_queue) . " unique entries from $row_count rows in " . round($build_time, 2) . "s");
                
            } catch (Exception $e) {
                $this->log("Error building duplicate index: " . $e->getMessage());
                return [];
            }
            
            return $existing_queue;
        }

        /**
         * Safe file locking for concurrent access prevention
         */
        protected function lockFile(string $file_path, string $mode = 'a'): ?resource
        {
            $this->log("DEBUG: lockFile called for: $file_path (mode: $mode)");
            $attempts = 0;
            $max_attempts = 10; // Reduced timeout from 30 to 10 seconds
            
            while ($attempts < $max_attempts) {
                $this->log("DEBUG: Lock attempt $attempts/$max_attempts");
                $handle = fopen($file_path, $mode);
                
                if (!$handle) {
                    $this->log("ERROR: Failed to open file: $file_path - " . error_get_last()['message']);
                    return null;
                }
                
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    $this->log("DEBUG: File lock acquired successfully on attempt $attempts");
                    return $handle;
                }
                
                $this->log("DEBUG: Lock attempt $attempts failed, file is locked by another process");
                fclose($handle);
                
                usleep(1000000); // Wait 1 second
                $attempts++;
            }
            
            $this->log("ERROR: Failed to acquire file lock after $max_attempts attempts: $file_path");
            return null;
        }

        /**
         * Release file lock safely
         */
        protected function unlockFile($handle): void
        {
            if ($handle) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }

        /**
         * Image Queue Progress Management Methods
         * These methods handle progress for image queue files independently from main migration progress
         */

        /**
         * Get image queue specific progress file path
         */
        protected function getImageQueueProgressFile(string $queue_file): string
        {
            $queue_progress_file = str_replace('.csv', '_progress.json', $queue_file);
            return $this->upload_dir . $queue_progress_file;
        }

        /**
         * Get progress for image queue
         */
        protected function getImageQueueProgress(string $queue_file): array
        {
            $progress_file = $this->getImageQueueProgressFile($queue_file);
            $default = ['processed' => 0, 'total' => 0, 'percentage' => 0];
            
            if (!file_exists($progress_file)) {
                return $default;
            }
            
            $progress_data = json_decode(file_get_contents($progress_file), true);
            return $progress_data[$queue_file] ?? $default;
        }

        /**
         * Update progress for image queue
         */
        protected function updateImageQueueProgress(string $queue_file, int $processed, int $total): void
        {
            $progress_file = $this->getImageQueueProgressFile($queue_file);
            
            try {
                // Validation
                if ($processed > $total) {
                    $this->log("WARNING: Processed ($processed) > Total ($total) for $queue_file - adjusting");
                    $processed = $total;
                }
                
                if ($processed < 0) {
                    $this->log("WARNING: Negative processed count ($processed) for $queue_file - setting to 0");
                    $processed = 0;
                }
                
                if ($total < 0) {
                    $this->log("WARNING: Negative total count ($total) for $queue_file - setting to 0");
                    $total = 0;
                }
                
                $percentage = $total > 0 ? round(($processed / $total) * 100, 2) : 0;
                
                // Ensure directory exists
                $progress_dir = dirname($progress_file);
                if (!is_dir($progress_dir)) {
                    if (!wp_mkdir_p($progress_dir)) {
                        $this->log("ERROR: Failed to create progress directory: $progress_dir");
                        return;
                    }
                }
                
                // Load existing progress data with error handling
                $progress_data = [];
                if (file_exists($progress_file)) {
                    $file_contents = file_get_contents($progress_file);
                    if ($file_contents === false) {
                        $this->log("ERROR: Failed to read progress file: $progress_file");
                        return;
                    }
                    
                    $progress_data = json_decode($file_contents, true);
                    if ($progress_data === null) {
                        $this->log("WARNING: Invalid JSON in progress file: $progress_file - creating new");
                        $progress_data = [];
                    }
                }
                
                // Update progress for this queue
                $progress_data[$queue_file] = [
                    'processed' => $processed,
                    'total' => $total,
                    'percentage' => $percentage,
                    'status' => $progress_data[$queue_file]['status'] ?? 'ongoing',
                    'status_timestamp' => $progress_data[$queue_file]['status_timestamp'] ?? time(),
                    'last_update' => date('Y-m-d H:i:s')
                ];
                
                // Write with atomic operation using temp file
                $temp_file = $progress_file . '.tmp';
                $json_data = json_encode($progress_data);
                
                if ($json_data === false) {
                    $this->log("ERROR: Failed to encode JSON for progress file: $progress_file");
                    return;
                }
                
                $bytes_written = file_put_contents($temp_file, $json_data);
                if ($bytes_written === false) {
                    $this->log("ERROR: Failed to write temp progress file: $temp_file");
                    return;
                }
                
                if (!rename($temp_file, $progress_file)) {
                    $this->log("ERROR: Failed to rename temp progress file: $temp_file to $progress_file");
                    if (file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                    return;
                }
                
                $this->log("Image queue progress updated: $processed/$total ($percentage%) for $queue_file");
                
            } catch (Exception $e) {
                $this->log("ERROR: Exception updating image queue progress: " . $e->getMessage());
                
                // Cleanup temp file if exists
                $temp_file = $progress_file . '.tmp';
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
            }
        }

        /**
         * Set status for image queue
         */
        protected function setImageQueueProgressStatus(string $queue_file, string $status): void
        {
            $progress_file = $this->getImageQueueProgressFile($queue_file);
            
            // Load existing progress data
            $progress_data = [];
            if (file_exists($progress_file)) {
                $progress_data = json_decode(file_get_contents($progress_file), true) ?: [];
            }
            
            // Ensure entry exists
            if (!isset($progress_data[$queue_file])) {
                $progress_data[$queue_file] = [
                    'processed' => 0,
                    'total' => 0,
                    'percentage' => 0
                ];
            }
            
            // Update status with timestamp
            $progress_data[$queue_file]['status'] = $status;
            $progress_data[$queue_file]['status_timestamp'] = time();
            $progress_data[$queue_file]['last_update'] = date('Y-m-d H:i:s');
            
            file_put_contents($progress_file, json_encode($progress_data));
            $this->log("Image queue status set to '$status' for $queue_file");
        }

        /**
         * Get status for image queue
         */
        protected function getImageQueueProgressStatus(string $queue_file): string
        {
            $progress_file = $this->getImageQueueProgressFile($queue_file);
            
            if (!file_exists($progress_file)) {
                return 'not_started';
            }
            
            $progress_data = json_decode(file_get_contents($progress_file), true);
            return $progress_data[$queue_file]['status'] ?? 'not_started';
        }

        /**
         * Check if image queue process is active
         */
        protected function isImageQueueProcessActive(string $queue_file): bool
        {
            $progress_file = $this->getImageQueueProgressFile($queue_file);
            
            if (!file_exists($progress_file)) {
                return false;
            }
            
            $progress_data = json_decode(file_get_contents($progress_file), true);
            
            if (!isset($progress_data[$queue_file]['status_timestamp'])) {
                $this->log("No timestamp found for $queue_file - considered inactive");
                return false;
            }
            
            $elapsed = time() - $progress_data[$queue_file]['status_timestamp'];
            
            // More generous timeout: 10 minutes for image processing (downloads can be slow)
            // Also check if we have recent progress updates (last_update field)
            $timeout_threshold = 600; // 10 minutes
            
            // Check if there was recent progress activity (last_update vs status_timestamp)
            $has_recent_progress = false;
            if (isset($progress_data[$queue_file]['last_update'])) {
                $last_update_time = strtotime($progress_data[$queue_file]['last_update']);
                $progress_elapsed = time() - $last_update_time;
                $has_recent_progress = $progress_elapsed <= 300; // 5 minutes for recent progress
                
                $this->log("Image queue activity check for $queue_file: elapsed {$elapsed}s, progress_elapsed {$progress_elapsed}s, has_recent_progress: " . ($has_recent_progress ? 'yes' : 'no'));
            } else {
                $this->log("Image queue activity check for $queue_file: elapsed {$elapsed}s, threshold {$timeout_threshold}s");
            }
            
            // Consider active if either the status is recent OR there was recent progress
            return ($elapsed <= $timeout_threshold) || $has_recent_progress;
        }

        /**
         * OPTIMIZED BATCH PROCESSING METHODS
         * Methods for efficient image queue processing with batch CSV updates
         */

        /**
         * Update processed index for a row without touching CSV
         * Uses batch processing to minimize I/O operations
         */
        protected function updateProcessedIndex(string $queue_file, int $row_index): void
        {
            $progress_file = $this->getImageQueueProgressFile($queue_file);
            
            // Load existing progress data
            $progress_data = [];
            if (file_exists($progress_file)) {
                $file_contents = file_get_contents($progress_file);
                if ($file_contents !== false) {
                    $progress_data = json_decode($file_contents, true) ?: [];
                }
            }
            
            // Initialize queue data if needed
            if (!isset($progress_data[$queue_file])) {
                $progress_data[$queue_file] = [
                    'processed' => 0,
                    'total' => 0,
                    'percentage' => 0,
                    'status' => 'ongoing',
                    'status_timestamp' => time(),
                    'last_update' => date('Y-m-d H:i:s')
                ];
            }
            
            // Update counters
            $progress_data[$queue_file]['processed']++;
            $progress_data[$queue_file]['percentage'] = $progress_data[$queue_file]['total'] > 0 
                ? round(($progress_data[$queue_file]['processed'] / $progress_data[$queue_file]['total']) * 100, 2) 
                : 0;
            $progress_data[$queue_file]['last_update'] = date('Y-m-d H:i:s');
            
            // Save progress (fast JSON write)
            $this->saveProgressData($progress_file, $progress_data);
        }

        /**
         * Check if a row index is already processed (fast check from JSON)
         */
        protected function isRowAlreadyProcessed(string $queue_file, int $row_index): bool
        {
            $progress = $this->getImageQueueProgress($queue_file);
            
            // If we have a checkpoint of processed indices, check there
            if (isset($progress['processed_indices']) && in_array($row_index, $progress['processed_indices'])) {
                return true;
            }
            
            return false;
        }


        /**
         * Save progress data to JSON file with atomic operation
         */
        protected function saveProgressData(string $progress_file, array $progress_data): bool
        {
            try {
                // Ensure directory exists
                $progress_dir = dirname($progress_file);
                if (!is_dir($progress_dir)) {
                    if (!wp_mkdir_p($progress_dir)) {
                        $this->log("ERROR: Failed to create progress directory: $progress_dir");
                        return false;
                    }
                }
                
                // Write with atomic operation using temp file
                $temp_file = $progress_file . '.tmp';
                $json_data = json_encode($progress_data, JSON_PRETTY_PRINT);
                
                if ($json_data === false) {
                    $this->log("ERROR: Failed to encode JSON for progress file: $progress_file");
                    return false;
                }
                
                $bytes_written = file_put_contents($temp_file, $json_data);
                if ($bytes_written === false) {
                    $this->log("ERROR: Failed to write temp progress file: $temp_file");
                    return false;
                }
                
                if (!rename($temp_file, $progress_file)) {
                    $this->log("ERROR: Failed to rename temp progress file: $temp_file to $progress_file");
                    if (file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                    return false;
                }
                
                return true;
                
            } catch (Exception $e) {
                $this->log("ERROR: Exception saving progress data: " . $e->getMessage());
                
                // Cleanup temp file if exists
                $temp_file = $progress_file . '.tmp';
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                
                return false;
            }
        }


        /**
         * USER MANAGEMENT UTILITIES
         * Methods for handling user lookup and fallback logic
         */

        /**
         * Get admin user with id_old=1 as fallback instead of hardcoded ID 1
         * This provides better consistency with migrated user data
         */
        protected function getAdminUserFallback(): int
        {
            static $admin_fallback_id = null;
            
            if ($admin_fallback_id !== null) {
                return $admin_fallback_id;
            }
            
            // First try to find admin user with id_old = 1
            global $wpdb;
            $admin_query = $wpdb->prepare("
                SELECT u.ID 
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = 'id_old' 
                    AND um.meta_value = '1'
                    AND u.user_status = 0
                LIMIT 1
            ");
            
            $admin_with_old_id = $wpdb->get_var($admin_query);
            
            if ($admin_with_old_id) {
                $admin_fallback_id = (int)$admin_with_old_id;
                $this->log("Found admin user with id_old=1: ID $admin_fallback_id");
                return $admin_fallback_id;
            }
            
            // Fallback to first admin user if no id_old=1 found
            $admin_users = get_users([
                'role' => 'administrator',
                'number' => 1,
                'fields' => 'ID'
            ]);
            
            if (!empty($admin_users)) {
                $admin_fallback_id = (int)$admin_users[0];
                $this->log("Using first admin user as fallback: ID $admin_fallback_id");
                return $admin_fallback_id;
            }
            
            // Last resort - hardcoded fallback
            $this->log("WARNING: No admin users found, using hardcoded fallback ID 1");
            $admin_fallback_id = 1;
            return $admin_fallback_id;
        }

        /**
         * Enhanced user lookup with better fallback logic
         */
        protected function get_existing_users_by_old_ids($old_ids)
        {
            if (empty($old_ids)) {
                return [];
            }
            
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($old_ids), '%s'));
            $sql = "
                SELECT um.user_id, um.meta_value
                FROM {$wpdb->usermeta} um
                INNER JOIN {$wpdb->users} u ON um.user_id = u.ID
                WHERE um.meta_key = 'id_old' 
                    AND um.meta_value IN ($placeholders)
                    AND u.user_status = 0
            ";
            $prepared_sql = $wpdb->prepare($sql, $old_ids);
            $results = $wpdb->get_results($prepared_sql, ARRAY_A);
            
            $existing_users = [];
            foreach ($results as $row) {
                $existing_users[$row['meta_value']] = (int)$row['user_id'];
            }
            
            $this->log("Found " . count($existing_users) . " existing users for " . count($old_ids) . " old IDs");
            return $existing_users;
        }

        /**
         * Get author ID with improved fallback logic
         */
        protected function getAuthorIdWithFallback($id_account, $existing_users): int
        {
            // First try to get from existing users cache
            if (isset($existing_users[$id_account])) {
                return (int)$existing_users[$id_account];
            }
            
            // Fallback to admin user with id_old=1
            $fallback_id = $this->getAdminUserFallback();
            $this->log("Using admin fallback for IdAccount $id_account: user ID $fallback_id");
            
            return $fallback_id;
        }

        /**
         * PERFORMANCE OPTIMIZATION TOOLS
         * Stream processing methods for handling large CSV files efficiently
         */

        /**
         * Process large CSV files in chunks to prevent memory exhaustion
         */
        protected function processLargeCsvInChunks(string $file_path, callable $processor, int $chunk_size = 1000): array
        {
            $stats = [
                'total_rows' => 0,
                'processed_rows' => 0,
                'errors' => 0,
                'chunks' => 0
            ];
            
            if (!file_exists($file_path)) {
                return $stats;
            }
            
            try {
                $file = new SplFileObject($file_path, 'r');
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $file->setCsvControl(',', '"', '\\');
                
                $chunk = [];
                
                foreach ($file as $data) {
                    if (!$data || count($data) < 4) {
                        continue;
                    }
                    
                    $stats['total_rows']++;
                    $chunk[] = $data;
                    
                    if (count($chunk) >= $chunk_size) {
                        $result = call_user_func($processor, $chunk, $stats['chunks']);
                        $stats['processed_rows'] += $result['processed'] ?? count($chunk);
                        $stats['errors'] += $result['errors'] ?? 0;
                        $stats['chunks']++;
                        
                        $chunk = [];
                        
                        // Memory cleanup
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                }
                
                // Process remaining items
                if (!empty($chunk)) {
                    $result = call_user_func($processor, $chunk, $stats['chunks']);
                    $stats['processed_rows'] += $result['processed'] ?? count($chunk);
                    $stats['errors'] += $result['errors'] ?? 0;
                    $stats['chunks']++;
                }
                
                $file = null;
                
            } catch (Exception $e) {
                $this->log("Error in chunk processing: " . $e->getMessage());
                $stats['errors']++;
            }
            
            return $stats;
        }

        /**
         * Optimized version of markRowsAsProcessed for large files
         */
        protected function markRowsAsProcessedOptimized(string $queue_path, array $processed_indices): bool
        {
            if (empty($processed_indices)) {
                return true;
            }
            
            $temp_file = $queue_path . '.tmp';
            $processed_set = array_flip($processed_indices); // Convert to hash set for O(1) lookup
            
            try {
                $read_file = new SplFileObject($queue_path, 'r');
                $read_file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $read_file->setCsvControl(',', '"', '\\');
                
                $write_file = new SplFileObject($temp_file, 'w');
                
                $row_index = 0;
                $updated_count = 0;
                
                foreach ($read_file as $data) {
                    if (empty($data) || count($data) < 6) {
                        continue;
                    }
                    
                    // Mark row as processed if it's in our list
                    if (isset($processed_set[$row_index]) && $data[5] == 0) {
                        $data[5] = 1; // Set processed flag
                        $updated_count++;
                    }
                    
                    $write_file->fputcsv($data);
                    $row_index++;
                }
                
                $read_file = null;
                $write_file = null;
                
                // Atomic replacement
                if (rename($temp_file, $queue_path)) {
                    $this->log("Marked $updated_count rows as processed");
                    
                    // Update index file
                    $index_file = $queue_path . '.index';
                    if (file_exists($index_file)) {
                        unlink($index_file); // Force rebuild on next access
                    }
                    
                    return true;
                } else {
                    $this->log("Failed to replace queue file after marking processed rows");
                    return false;
                }
                
            } catch (Exception $e) {
                $this->log("Error marking rows as processed: " . $e->getMessage());
                
                // Cleanup on error
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                
                return false;
            }
        }

        /**
         * Memory-efficient row counting for large CSV files
         */
        protected function countCsvRowsEfficient(string $file_path): int
        {
            if (!file_exists($file_path)) {
                return 0;
            }
            
            $count = 0;
            $handle = fopen($file_path, 'r');
            
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if (trim($line) !== '') {
                        $count++;
                    }
                }
                fclose($handle);
            }
            
            return $count;
        }

        /**
         * Get queue statistics without loading entire file
         */
        public function getImageQueueStatistics(string $queue_file): array
        {
            $queue_path = $this->upload_dir . $queue_file;
            $stats = [
                'file_exists' => file_exists($queue_path),
                'file_size' => 0,
                'total_rows' => 0,
                'processed_rows' => 0,
                'pending_rows' => 0,
                'last_modified' => null,
                'progress' => $this->getImageQueueProgress($queue_file)
            ];
            
            if (!$stats['file_exists']) {
                return $stats;
            }
            
            $stats['file_size'] = filesize($queue_path);
            $stats['last_modified'] = date('Y-m-d H:i:s', filemtime($queue_path));
            
            // Count rows efficiently
            try {
                $processed_count = 0;
                $total_count = 0;
                
                $file = new SplFileObject($queue_path, 'r');
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $file->setCsvControl(',', '"', '\\');
                
                // Sample-based estimation for very large files
                if ($stats['file_size'] > 50 * 1024 * 1024) { // > 50MB
                    $this->log("Large file detected, using sampling for statistics");
                    
                    $sample_size = 10000;
                    $sample_processed = 0;
                    $sample_total = 0;
                    
                    foreach ($file as $data) {
                        if (!$data || count($data) < 6) continue;
                        
                        $sample_total++;
                        if ((int)($data[5] ?? 0) === 1) {
                            $sample_processed++;
                        }
                        
                        if ($sample_total >= $sample_size) {
                            break;
                        }
                    }
                    
                    // Estimate total based on file size
                    $avg_line_length = $stats['file_size'] / max(1, $sample_total);
                    $estimated_total = (int)($stats['file_size'] / $avg_line_length);
                    $estimated_processed = (int)(($sample_processed / max(1, $sample_total)) * $estimated_total);
                    
                    $stats['total_rows'] = $estimated_total;
                    $stats['processed_rows'] = $estimated_processed;
                    $stats['estimated'] = true;
                    
                } else {
                    // Full count for smaller files
                    foreach ($file as $data) {
                        if (!$data || count($data) < 6) continue;
                        
                        $total_count++;
                        if ((int)($data[5] ?? 0) === 1) {
                            $processed_count++;
                        }
                    }
                    
                    $stats['total_rows'] = $total_count;
                    $stats['processed_rows'] = $processed_count;
                    $stats['estimated'] = false;
                }
                
                $file = null;
                
            } catch (Exception $e) {
                $this->log("Error getting queue statistics: " . $e->getMessage());
            }
            
            $stats['pending_rows'] = $stats['total_rows'] - $stats['processed_rows'];
            $stats['completion_percentage'] = $stats['total_rows'] > 0 
                ? round(($stats['processed_rows'] / $stats['total_rows']) * 100, 2) 
                : 0;
            
            return $stats;
        }


        /**
         * Clean up empty image queue files and reset system
         */
        public function cleanupEmptyImageQueueFiles(): array
        {
            $stats = [
                'files_removed' => 0,
                'files_found' => 0,
                'errors' => []
            ];
            
            try {
                $patterns = [
                    'image_download_queue*.csv',
                    'image_download_queue*.csv.index',
                    'image_download_queue*.csv.tmp'
                ];
                
                foreach ($patterns as $pattern) {
                    $files = glob($this->upload_dir . $pattern);
                    foreach ($files as $file) {
                        $stats['files_found']++;
                        $this->log("DEBUG CLEANUP: Found file: $file (size: " . filesize($file) . " bytes)");
                        
                        // Remove empty files or files with only whitespace
                        if (filesize($file) == 0 || trim(file_get_contents($file)) == '') {
                            if (unlink($file)) {
                                $stats['files_removed']++;
                                $this->log("DEBUG CLEANUP: Removed empty file: $file");
                            } else {
                                $stats['errors'][] = "Failed to remove: $file";
                            }
                        }
                    }
                }
                
                // Also cleanup any orphaned progress files
                $progress_files = glob($this->upload_dir . '*_progress.json');
                foreach ($progress_files as $file) {
                    if (strpos($file, 'image_download_queue') !== false) {
                        $stats['files_found']++;
                        if (filesize($file) == 0) {
                            if (unlink($file)) {
                                $stats['files_removed']++;
                                $this->log("DEBUG CLEANUP: Removed empty progress file: $file");
                            }
                        }
                    }
                }
                
            } catch (Exception $e) {
                $stats['errors'][] = $e->getMessage();
                $this->log("ERROR CLEANUP: " . $e->getMessage());
            }
            
            return $stats;
        }

        /**
         * CLEANUP AND MAINTENANCE TOOLS
         * Methods for cleaning corrupted CSV files and rebuilding from database
         */

        /**
         * Clean up corrupted image queue by removing duplicates and fixing format
         */
        public function cleanupCorruptedImageQueue(string $queue_file): array
        {
            $queue_path = $this->upload_dir . $queue_file;
            $backup_path = $queue_path . '.backup.' . date('Y-m-d_H-i-s');
            $temp_path = $queue_path . '.cleaning';
            
            $stats = [
                'original_rows' => 0,
                'unique_rows' => 0,
                'duplicates_removed' => 0,
                'invalid_rows' => 0,
                'success' => false,
                'execution_time' => 0
            ];
            
            $start_time = microtime(true);
            
            try {
                if (!file_exists($queue_path)) {
                    $stats['success'] = true;
                    $this->log("Queue file does not exist: $queue_file");
                    return $stats;
                }
                
                // Step 1: Backup original file
                if (!copy($queue_path, $backup_path)) {
                    throw new Exception("Failed to create backup");
                }
                $this->log("Backup created: $backup_path");
                
                // Step 2: Process file in chunks to handle large files
                $unique_entries = [];
                $chunk_size = 10000;
                $processed = 0;
                
                $read_file = new SplFileObject($queue_path, 'r');
                $read_file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $read_file->setCsvControl(',', '"', '\\');
                
                $write_file = new SplFileObject($temp_path, 'w');
                
                $this->log("Starting deduplication process...");
                
                foreach ($read_file as $line_num => $data) {
                    $stats['original_rows']++;
                    
                    // Validate row format
                    if (!$data || count($data) < 4) {
                        $stats['invalid_rows']++;
                        continue;
                    }
                    
                    // Ensure we have exactly 6 columns
                    if (count($data) === 4) {
                        $data = array_merge($data, [0, 0]); // Add retry_count and processed
                    } elseif (count($data) === 5) {
                        $data[] = 0; // Add processed flag
                    } elseif (count($data) > 6) {
                        $data = array_slice($data, 0, 6); // Trim to 6 columns
                    }
                    
                    // Create unique key
                    $key = implode('|', array_slice($data, 0, 4));
                    
                    if (isset($unique_entries[$key])) {
                        $stats['duplicates_removed']++;
                        continue;
                    }
                    
                    $unique_entries[$key] = true;
                    $write_file->fputcsv($data);
                    $stats['unique_rows']++;
                    
                    $processed++;
                    if ($processed % $chunk_size === 0) {
                        $this->log("Processed $processed rows, found {$stats['duplicates_removed']} duplicates");
                    }
                }
                
                $read_file = null;
                $write_file = null;
                
                // Step 3: Replace original with cleaned version
                if (!rename($temp_path, $queue_path)) {
                    throw new Exception("Failed to replace original file");
                }
                
                // Step 4: Rebuild index
                $index_file = $queue_path . '.index';
                if (file_exists($index_file)) {
                    unlink($index_file);
                }
                $this->buildDuplicateIndex($queue_path);
                
                // Step 5: Reset progress
                $this->setImageQueueProgressStatus($queue_file, 'not_started');
                $this->updateImageQueueProgress($queue_file, 0, $stats['unique_rows']);
                
                $stats['success'] = true;
                $stats['execution_time'] = microtime(true) - $start_time;
                
                $this->log("Cleanup completed: {$stats['original_rows']} -> {$stats['unique_rows']} rows (removed {$stats['duplicates_removed']} duplicates) in " . round($stats['execution_time'], 2) . "s");
                
            } catch (Exception $e) {
                // Cleanup on error
                if (file_exists($temp_path)) {
                    unlink($temp_path);
                }
                
                $stats['error'] = $e->getMessage();
                $this->log("Cleanup failed: " . $e->getMessage());
            }
            
            return $stats;
        }

        /**
         * Rebuild image queue from database posts
         */
        public function rebuildImageQueueFromDatabase(string $queue_file, array $post_types = ['annuncio-di-morte', 'trigesimo', 'anniversario', 'ringraziamento']): bool
        {
            $queue_path = $this->upload_dir . $queue_file;
            $backup_path = $queue_path . '.backup.' . date('Y-m-d_H-i-s');
            
            try {
                // Backup existing file if it exists
                if (file_exists($queue_path)) {
                    copy($queue_path, $backup_path);
                    $this->log("Backup created: $backup_path");
                }
                
                // Query posts that need images
                global $wpdb;
                
                $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
                $query = $wpdb->prepare("
                    SELECT p.ID, p.post_author, p.post_type,
                           GROUP_CONCAT(DISTINCT pm.meta_key) as existing_images
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                        AND pm.meta_key IN ('immagine_annuncio_di_morte', 'profile_photo', 'immagine_annuncio_trigesimo', 
                                           'immagine_annuncio_anniversario', 'immagine_ringraziamento', 'immagine_manifesto')
                        AND pm.meta_value != ''
                    WHERE p.post_type IN ($post_types_placeholders)
                        AND p.post_status IN ('publish', 'draft')
                    GROUP BY p.ID
                    HAVING existing_images IS NULL OR existing_images = ''
                    ORDER BY p.ID
                ", $post_types);
                
                $posts_needing_images = $wpdb->get_results($query);
                
                if (empty($posts_needing_images)) {
                    $this->log("No posts found that need images");
                    return true;
                }
                
                $this->log("Found " . count($posts_needing_images) . " posts that might need images");
                
                // Create new queue file
                $write_file = new SplFileObject($queue_path, 'w');
                $added_count = 0;
                
                foreach ($posts_needing_images as $post) {
                    // Get original image URLs from post meta if available
                    $image_urls = $this->getOriginalImageUrlsForPost($post->ID, $post->post_type);
                    
                    foreach ($image_urls as $image_type => $url) {
                        if (!empty($url)) {
                            $write_file->fputcsv([$image_type, $url, $post->ID, $post->post_author, 0, 0]);
                            $added_count++;
                        }
                    }
                }
                
                $write_file = null;
                
                // Reset progress and rebuild index
                $this->setImageQueueProgressStatus($queue_file, 'not_started');
                $this->updateImageQueueProgress($queue_file, 0, $added_count);
                $this->buildDuplicateIndex($queue_path);
                
                $this->log("Rebuilt queue from database: $added_count images for " . count($posts_needing_images) . " posts");
                return true;
                
            } catch (Exception $e) {
                $this->log("Failed to rebuild queue from database: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Get original image URLs for a post based on post type
         */
        protected function getOriginalImageUrlsForPost(int $post_id, string $post_type): array
        {
            $urls = [];
            
            // This would need to be customized based on how original image URLs are stored
            // For now, this is a placeholder that could check old_meta fields or other sources
            
            switch ($post_type) {
                case 'annuncio-di-morte':
                    // Check for original photo and manifesto image URLs
                    $photo_url = get_post_meta($post_id, 'original_photo_url', true);
                    $manifesto_url = get_post_meta($post_id, 'original_manifesto_url', true);
                    
                    if ($photo_url) $urls['profile_photo'] = $photo_url;
                    if ($manifesto_url) $urls['manifesto_image'] = $manifesto_url;
                    break;
                    
                case 'trigesimo':
                    $trigesimo_url = get_post_meta($post_id, 'original_trigesimo_url', true);
                    if ($trigesimo_url) $urls['trigesimo'] = $trigesimo_url;
                    break;
                    
                case 'anniversario':
                    $anniversario_url = get_post_meta($post_id, 'original_anniversario_url', true);
                    if ($anniversario_url) $urls['anniversario'] = $anniversario_url;
                    break;
                    
                case 'ringraziamento':
                    $ringraziamento_url = get_post_meta($post_id, 'original_ringraziamento_url', true);
                    if ($ringraziamento_url) $urls['ringraziamento'] = $ringraziamento_url;
                    break;
            }
            
            return $urls;
        }

    }
}