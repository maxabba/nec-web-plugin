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
        private int $micro_batch_size = 20;
        private int $query_chunk_size = 100;
        private int $checkpoint_interval = 50;
        private int $progress_update_interval = 10;
        
        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size * 2);
            
            $this->memory_limit_mb = 256;
            $this->max_execution_time = 30;
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

            $id_account_index = array_search('idAccount', $header);
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

            // Pre-fetch dei dati esistenti con chunking
            $existing_posts = $this->get_existing_posts_by_old_ids_chunked(array_unique($batch_post_old_ids), ['manifesto']);
            $existing_users = $this->get_existing_users_by_old_ids(array_unique($batch_user_old_ids));
            $existing_necrologi = $this->get_existing_posts_by_old_ids_chunked(array_unique($batch_necrologi_ids), ['annuncio-di-morte']);

            // Liberare memoria
            unset($batch_user_old_ids);
            unset($batch_post_old_ids);
            unset($batch_necrologi_ids);

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
                        $existing_necrologi,
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
            $file = null;

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");

            if ($processed >= $total_rows) {
                $this->set_progress_status($file_name, 'finished');
                return true;
            }

            $smart_status = $this->set_progress_status_smart($file_name, 'completed');

            if ($smart_status === 'auto_continue') {
                $this->log("Auto-continuazione immediata possibile per $file_name");
                return 'auto_continue';
            }

            return false;
        }

        private function process_single_record($data, $header, $existing_posts, $existing_users, $existing_necrologi, &$batch_acf_updates = null)
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
                    'IdAccount' => array_search('idAccount', $header)
                ];
            }

            // Se il post esiste già
            if (isset($existing_posts[$data[$field_indexes['ID']]])) {
                $existing_post_id = $existing_posts[$data[$field_indexes['ID']]];

                // Trova l'ID dell'utente basato su id_old
                $author_id = $existing_users[$data[$field_indexes['IdAccount']]] ?? 1;

                // Aggiorna post_author
                $updated = wp_update_post([
                    'ID' => $existing_post_id,
                    'post_author' => $author_id
                ]);

                if ($updated) {
                    $this->log("Post author aggiornato per manifesto ID {$existing_post_id} con nuovo author ID {$author_id}");
                } else {
                    $this->log("Errore nell'aggiornamento dell'autore per manifesto ID {$existing_post_id}");
                }

                return $updated;
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
                if ($batch_acf_updates !== null) {
                    $this->prepare_acf_batch_update($post_id, $data, $field_indexes, $necrologio_id, $author_id, $batch_acf_updates);
                } else {
                    // Aggiorna i campi ACF
                    update_field('id_old', $data[$field_indexes['ID']], $post_id);
                    if ($necrologio_id) {
                        update_field('annuncio_di_morte_relativo', $necrologio_id, $post_id);
                    }

                    update_field('testo_manifesto', $this->cleanText($data[$field_indexes['Testo']]), $post_id);
                    update_field('vendor_id', $author_id, $post_id);
                    update_field('tipo_manifesto', 'silver', $post_id);
                }

                $this->log("Manifesto creato: ID $post_id");
            } else {
                $this->log("Errore nella creazione del manifesto: " . $post_id->get_error_message());
            }

            return $post_id;
        }

        function cleanText(string $text): string
        {
            // Tag permessi
            $allowedTags = '<strong><em><br><div>';

            $patterns = [
                // Rimuovi heading vuoti o con solo &nbsp;
                ['pattern' => '/<h[1-6][^>]*>(\s|&nbsp;)*<\/h[1-6]>/i', 'replacement' => '<br>'],

                // Converti heading non vuoti in div
                ['pattern' => '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', 'replacement' => '<div>$1</div>'],

                // Converti paragrafi in div o br se vuoti
                ['pattern' => '/<p[^>]*>(\s|&nbsp;)*<\/p>/i', 'replacement' => '<br>'],
                ['pattern' => '/<p[^>]*>(.*?)<\/p>/is', 'replacement' => '<div>$1</div>'],

                // Gestione degli spazi HTML
                ['pattern' => '/&nbsp;/', 'replacement' => ' '],

                // Normalizza gli spazi multipli
                ['pattern' => '/\s+/', 'replacement' => ' '],

                // Gestione degli a capo multipli
                ['pattern' => '/(<br>\s*){2,}/', 'replacement' => '<br><br>'],

                // Spazi intorno ai tag strong/em quando necessario
                ['pattern' => '/(<\/(?:strong|em)>)([a-zA-Z0-9])/', 'replacement' => '$1 $2'],
                ['pattern' => '/([a-zA-Z0-9])(<(?:strong|em)>)/', 'replacement' => '$1 $2'],

                // Gestione div vuoti
                ['pattern' => '/<div>\s*<\/div>/', 'replacement' => '<br>'],

                // Normalizza spazi tra div
                ['pattern' => '/<\/div>\s*<div>/', 'replacement' => "</div><br><br><div>"],

                // Pulizia finale br
                ['pattern' => '/(<br>)+$/', 'replacement' => ''],
                ['pattern' => '/^(<br>)+/', 'replacement' => ''],
                ['pattern' => '/(<br>){3,}/', 'replacement' => '<br><br>']
            ];

            // Applica le trasformazioni
            foreach ($patterns as $pattern) {
                $text = preg_replace($pattern['pattern'], $pattern['replacement'], $text);
            }

            // Rimuovi tutti i tag non permessi
            $text = strip_tags($text, $allowedTags);

            // Pulizia finale
            $text = trim($text);

            return $text;
        }

        private function get_existing_posts_by_old_ids_chunked($old_ids, $post_types = ['manifesto'])
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

        private function prepare_acf_batch_update($post_id, $data, $field_indexes, $necrologio_id, $author_id, &$batch_acf_updates)
        {
            $batch_acf_updates[] = [
                'post_id' => $post_id,
                'meta_key' => 'id_old',
                'meta_value' => $data[$field_indexes['ID']]
            ];
            
            if ($necrologio_id) {
                $batch_acf_updates[] = [
                    'post_id' => $post_id,
                    'meta_key' => 'annuncio_di_morte_relativo',
                    'meta_value' => $necrologio_id
                ];
            }
            
            $batch_acf_updates[] = [
                'post_id' => $post_id,
                'meta_key' => 'testo_manifesto',
                'meta_value' => $this->cleanText($data[$field_indexes['Testo']])
            ];
            
            $batch_acf_updates[] = [
                'post_id' => $post_id,
                'meta_key' => 'vendor_id',
                'meta_value' => $author_id
            ];
            
            $batch_acf_updates[] = [
                'post_id' => $post_id,
                'meta_key' => 'tipo_manifesto',
                'meta_value' => 'silver'
            ];
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
    }
}