<?php

namespace Dokan_Mods\Migration_Tasks;

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

        protected function countCsvRows($filePath, $hasHeader = true)
        {
            $rowCount = 0;
            if (($handle = fopen($filePath, 'r')) !== false) {
                while (($data = fgetcsv($handle)) !== false) {
                    $rowCount++;
                }
                fclose($handle);
            }

            // Se c'è un'intestazione, sottrai 1 al conteggio totale
            return $hasHeader ? $rowCount - 1 : $rowCount;
        }

        protected function get_progress($file)
        {
            if (!file_exists($this->progress_file)) {
                return ['processed' => 0, 'total' => 0, 'percentage' => 0];
            }

            $file_content = file_get_contents($this->progress_file);
            if ($file_content === false) {
                return ['processed' => 0, 'total' => 0, 'percentage' => 0];
            }

            $progress = json_decode($file_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['processed' => 0, 'total' => 0, 'percentage' => 0];
            }

            if (!is_array($progress)) {
                return ['processed' => 0, 'total' => 0, 'percentage' => 0];
            }

            if (!isset($progress[$file])) {
                return ['processed' => 0, 'total' => 0, 'percentage' => 0];
            }

            $file_progress = $progress[$file];

            if (!isset($file_progress['processed']) || !isset($file_progress['total']) || !isset($file_progress['percentage'])) {
                return ['processed' => 0, 'total' => 0, 'percentage' => 0];
            }

            return $file_progress;
        }

        protected function log($message)
        {
            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[$timestamp] $message\n";

            if (file_put_contents($this->log_file, $log_entry, FILE_APPEND) === false) {
                // Se la scrittura fallisce, prova a usare error_log come fallback
                error_log("Failed to write to custom log file. Message was: $log_entry");
            }
        }

        protected function update_progress($file, $processed, $total)
        {
            $progress = json_decode(file_get_contents($this->progress_file), true);
            $progress[$file] = [
                'processed' => $processed,
                'total' => $total,
                'percentage' => round(($processed / $total) * 100, 2)
            ];
            file_put_contents($this->progress_file, json_encode($progress));
        }

        protected function set_progess_status($file, $status)
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

    }
}