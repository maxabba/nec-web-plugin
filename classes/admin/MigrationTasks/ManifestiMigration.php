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

if (!class_exists(__NAMESPACE__ . '\ManifestiMigration')) {
    class ManifestiMigration extends MigrationTasks
    {
        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);
        }

        public function migrate_manifesti_batch($file_name)
        {
            if ($this->get_progress_status($file_name) == 'finished') {
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

            $header = $file->fgetcsv();             // Leggi l'header

            $file->seek($processed);                // Salta le righe già processate

            $batch_data = [];
            $batch_user_old_ids = [];
            $batch_post_old_ids = [];
            $batch_necrologi_ids = [];

            $id_account_index = array_search('IdAccount', $header);
            $id_necrologio_index = array_search('IdNecrologio', $header);
            $id_index = array_search('ID', $header);

            // Raccolta dati batch
            while (!$file->eof() && count($batch_data) < $this->batch_size) {
                $data = $file->fgetcsv();
                if (empty($data)) continue;

                $batch_data[] = $data;
                $batch_user_old_ids[] = $data[$id_account_index];
                $batch_post_old_ids[] = $data[$id_index];
                $batch_necrologi_ids[] = $data[$id_necrologio_index];
            }

            // Pre-fetch dei dati esistenti
            $existing_posts = $this->get_existing_posts_by_old_ids(array_unique($batch_post_old_ids), ['manifesto']);
            $existing_users = $this->get_existing_users_by_old_ids(array_unique($batch_user_old_ids));
            $existing_necrologi = $this->get_existing_posts_by_old_ids(array_unique($batch_necrologi_ids), ['annuncio-di-morte']);

            // Liberare memoria
            unset($batch_user_old_ids);
            unset($batch_post_old_ids);
            unset($batch_necrologi_ids);

            // Process batch
            foreach ($batch_data as $data) {
                $this->log("Ram Usage start: " . $this->get_memory_usage());

                $this->process_single_record(
                    $data,
                    $header,
                    $existing_posts,
                    $existing_users,
                    $existing_necrologi
                );

                $this->log("Ram Usage end: " . $this->get_memory_usage());
                $processed++;
                $this->update_progress($file_name, $processed, $total_rows);
            }

            // Cleanup
            unset($batch_data);
            $file = null;

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");

            $this->set_progress_status($file_name, 'completed');

            if ($processed >= $total_rows) {
                $this->set_progress_status($file_name, 'finished');
            }

            return $processed >= $total_rows;
        }

        private function process_single_record($data, $header, $existing_posts, $existing_users, $existing_necrologi)
        {
            static $field_indexes = null;

            // Cache degli indici dei campi
            if ($field_indexes === null) {
                $field_indexes = [
                    'ID' => array_search('ID', $header),
                    'IdNecrologio' => array_search('IdNecrologio', $header),
                    'Testo' => array_search('Testo', $header),
                    'DataInserimento' => array_search('DataInserimento', $header),
                    'Pubblicato' => array_search('Pubblicato', $header),
                    'IdAccount' => array_search('IdAccount', $header)
                ];
            }

            // Verifica se il post esiste già
            if (isset($existing_posts[$data[$field_indexes['ID']]])) {
                $this->log("Manifesto già esistente: ID {$data[$field_indexes['ID']]}");
                return false;
            }

            if ($data[$field_indexes['Testo']] == '' || $data[$field_indexes['Testo']] == null) {
                $this->log("Manifesto senza testo: ID {$data[$field_indexes['ID']]}");
                return false;
            }

            // Trova l'ID dell'utente
            $author_id = $existing_users[$data[$field_indexes['IdAccount']]] ?? 1;

            // Trova l'ID del necrologio associato
            $necrologio_id = $existing_necrologi[$data[$field_indexes['IdNecrologio']]] ?? null;

            $necrologio_title = $necrologio_id ? get_the_title($necrologio_id) : 'N/A';
            // Crea il post
            $post_id = wp_insert_post([
                'post_type' => 'manifesto',
                'post_title' => $necrologio_title . ' - ' . $data[$field_indexes['ID']],
                'post_status' => $data[$field_indexes['Pubblicato']] == 1 ? 'publish' : 'draft',
                'post_date' => date('Y-m-d H:i:s', strtotime($data[$field_indexes['DataInserimento']])),
                'post_author' => $author_id,
            ]);

            if (!is_wp_error($post_id)) {
                // Aggiorna i campi ACF
                update_field('field_671a68742fc07', $data[$field_indexes['ID']], $post_id); // id_old
                if ($necrologio_id) {
                    update_field('field_6666bf025040a', $necrologio_id, $post_id); // IdNecrologio
                }
                update_field('field_6669ea01b516d', $data[$field_indexes['Testo']], $post_id); // Testo
                update_field('field_6666bf6b5040b', $author_id, $post_id); // IdAccount
                update_field('field_6669ea01b516d', 'silver', $post_id); // Imposta il tipo come "silver"

                $this->log("Manifesto creato: ID $post_id");
            } else {
                $this->log("Errore nella creazione del manifesto: " . $post_id->get_error_message());
            }

            return $post_id;
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
    }
}