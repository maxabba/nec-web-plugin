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

        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            $this->upload_dir = $upload_dir;
            $this->progress_file = $progress_file;
            $this->log_file = $log_file;
            $this->batch_size = $batch_size;
        }

        protected function countCsvRows(SplFileObject $file)
        {
            try {
                $file->seek(PHP_INT_MAX);
                $total_rows = $file->key();
                $file->rewind();
                return $total_rows;
            } catch (RuntimeException $e) {
                // Fallback method for very large files
                $rows = 0;
                $file->rewind();
                while (!$file->eof()) {
                    $file->fgets();
                    $rows++;
                }
                $file->rewind();
                return $rows - 1; // -1 per l'header
            }
        }


        protected function load_file($file_name)
        {
            $csvFile = $this->upload_dir . $file_name;

            try {
                // Utilizzo di SplFileObject per una migliore gestione della memoria
                $file = new SplFileObject($csvFile, 'r');
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
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
                return [];
            }

            global $wpdb;

            try {
                // Crea i placeholder per la query preparata
                $placeholders = implode(',', array_fill(0, count($old_ids), '%s'));

                // Prepara la query
                $sql = $wpdb->prepare(
                    "SELECT um.user_id, um.meta_value 
            FROM {$wpdb->usermeta} um 
            WHERE um.meta_key = 'id_old' 
            AND um.meta_value IN ($placeholders)",
                    $old_ids
                );

                // Esegui la query
                $results = $wpdb->get_results($sql, ARRAY_A);

                if ($wpdb->last_error) {
                    throw new RuntimeException("Database error: " . $wpdb->last_error);
                }

                // Crea un array associativo id_old => user_id
                $existing_users = [];
                foreach ($results as $row) {
                    $existing_users[$row['meta_value']] = $row['user_id'];
                }

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

            if ($processed == 0 && $total_rows == 0) {
                $total_rows = $this->countCsvRows($file);
                $this->update_progress($file->getFilename(), 0, $total_rows);
                $header = $file->fgetcsv();
                if ($file_name != 'accounts.csv') {
                    $this->process_existing_posts($file, $header, $processed, $total_rows, $file_name);
                }
                //set status completed
                $this->set_progress_status($file_name, 'completed');
                return false;
            }

            if ($processed >= $total_rows) {
                $this->log("Tutti i record sono già stati processati");
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
            $progress = json_decode(file_get_contents($this->progress_file), true);
            $progress[$file] = [
                'processed' => $processed,
                'total' => $total,
                'percentage' => round(($processed / $total) * 100, 2),
                'status' => $progress[$file]['status']
            ];
            file_put_contents($this->progress_file, json_encode($progress));
        }

        protected function set_progress_status($file, $status)
        {
            $progress = json_decode(file_get_contents($this->progress_file), true);
            $progress[$file]['status'] = $status;
            file_put_contents($this->progress_file, json_encode($progress));
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

    }
}