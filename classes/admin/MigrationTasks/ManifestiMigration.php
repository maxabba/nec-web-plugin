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
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size * 2);
        }

        public function migrate_manifesti_batch($file_name)
        {
            try {
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

                // IMPORTANTE: Prima di leggere l'header, dobbiamo posizionarci all'inizio del file
                // perché first_call_check potrebbe aver già letto l'header
                $file->rewind();
                
                // CRITICO: Ristabilisce le impostazioni CSV control dopo rewind
                $file->setCsvControl($this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                $this->log("DEBUG: CSV control set to delimiter: '{$this->csv_delimiter}', enclosure: '{$this->csv_enclosure}'");
                
                $header = $file->fgetcsv();             // Leggi l'header
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
                $batch_user_old_ids = [];
                $batch_post_old_ids = [];
                $batch_necrologi_ids = [];

                $id_account_index = array_search('idAccount', $header);
                $id_necrologio_index = array_search('IdNecrologio', $header);
                $id_index = array_search('ID', $header);
                
                $this->log("DEBUG: Field indexes - idAccount: $id_account_index, IdNecrologio: $id_necrologio_index, ID: $id_index");
                
                // If indexes are false, try case-insensitive search
                if ($id_account_index === false) {
                    foreach ($header as $i => $field) {
                        if (strtolower($field) === 'idaccount') {
                            $id_account_index = $i;
                            $this->log("DEBUG: Found idAccount at index $i using case-insensitive search");
                            break;
                        }
                    }
                }

                // Raccolta dati batch
                $row_count = 0;
                while (!$file->eof() && count($batch_data) < $this->batch_size) {
                    $data = $file->fgetcsv();
                    if (empty($data)) continue;

                    // Debug: log first few rows to see the actual data structure
                    if ($row_count < 3) {
                        $this->log("DEBUG: Row $row_count data: " . print_r($data, true));
                        $this->log("DEBUG: Row $row_count data count: " . count($data));
                        if (!empty($data)) {
                            $this->log("DEBUG: Row $row_count IdAccount index $id_account_index value: " . ($data[$id_account_index] ?? 'INDEX_NOT_FOUND'));
                            $this->log("DEBUG: Row $row_count ID index $id_index value: " . ($data[$id_index] ?? 'INDEX_NOT_FOUND'));
                        }
                    }

                    $batch_data[] = $data;
                    $batch_user_old_ids[] = $data[$id_account_index] ?? '';
                    $batch_post_old_ids[] = $data[$id_index] ?? '';
                    $batch_necrologi_ids[] = $data[$id_necrologio_index] ?? '';
                    $row_count++;
                }

                // Pre-fetch dei dati esistenti
                $this->log("DEBUG: batch_user_old_ids prima del unique: " . print_r($batch_user_old_ids, true));
                $unique_user_ids = array_unique($batch_user_old_ids);
                $this->log("DEBUG: unique_user_ids: " . print_r($unique_user_ids, true));
                
                $existing_posts = $this->get_existing_posts_by_old_ids(array_unique($batch_post_old_ids), ['manifesto']);
                $existing_users = $this->get_existing_users_by_old_ids($unique_user_ids);
                $existing_necrologi = $this->get_existing_posts_by_old_ids(array_unique($batch_necrologi_ids), ['annuncio-di-morte']);
                
                $this->log("DEBUG: existing_users result: " . print_r($existing_users, true));

                // Liberare memoria
                unset($batch_user_old_ids);
                unset($batch_post_old_ids);
                unset($batch_necrologi_ids);

                // Process batch
                foreach ($batch_data as $data) {

                    $this->process_single_record(
                        $data,
                        $header,
                        $existing_posts,
                        $existing_users,
                        $existing_necrologi
                    );

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
                
            } catch (Exception $e) {
                $error_message = "Errore durante la migrazione manifesti: " . $e->getMessage();
                $this->log("ERRORE CRITICO: " . $error_message);
                $this->log("Stack trace: " . $e->getTraceAsString());
                
                // Aggiorna lo status del progresso come errore
                $this->set_progress_status($file_name, 'error');
                
                // Re-throw per permettere al sistema di loggare in wc-logs
                throw $e;
            }
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
                    'IdAccount' => array_search('idAccount', $header),
                    'Foto' => array_search('Foto', $header)
                ];
            }

            // Se il post esiste già
            if (isset($existing_posts[$data[$field_indexes['ID']]])) {
                $existing_post_id = $existing_posts[$data[$field_indexes['ID']]];
                
                $this->log("DEBUG: Update - Cercando autore per IdAccount: " . $data[$field_indexes['IdAccount']]);
                $this->log("DEBUG: Update - existing_users keys: " . implode(', ', array_keys($existing_users)));
                
                // Trova l'ID dell'utente basato su id_old con fallback migliorato
                $author_id = $this->getAuthorIdWithFallback($data[$field_indexes['IdAccount']], $existing_users);
                $this->log("DEBUG: Update - author_id assegnato: " . $author_id);

                // Aggiorna post_author
                $updated = wp_update_post([
                    'ID' => $existing_post_id,
                    'post_author' => $author_id
                ]);

                //ottieni il campo testo_manifesto
                $existing_text = get_field('testo_manifesto', $existing_post_id);
                //se il campo testo_manifesto è vuoto e il campo Foto non è vuoto aggiorna il campo immagine_manifesto_old con il valore di Foto
                if (($existing_text == '' || $existing_text == null) && ($data[$field_indexes['Foto']] != '' && $data[$field_indexes['Foto']] != null)) {
                    update_field('immagine_manifesto_old', $data[$field_indexes['Foto']], $existing_post_id);
                    $this->log("Aggiornato immagine_manifesto_old per manifesto ID {$existing_post_id} con Foto {$data[$field_indexes['Foto']]}");
                }



/*                if ($data[$field_indexes['Foto']] != '' && $data[$field_indexes['Foto']] != null ) {
                    //update imaggine_manifesto_old filed acf with Foto value
                    update_field('immagine_manifesto_old', $data[$field_indexes['Foto']], $existing_post_id);
                } else {
                    $this->log("Manifesto con foto non gestita: ID {$data[$field_indexes['ID']]}");
                }*/




                if ($updated) {
                    $this->log("Post author aggiornato per manifesto ID {$existing_post_id} con nuovo author ID {$author_id}");
                } else {
                    $this->log("Errore nell'aggiornamento dell'autore per manifesto ID {$existing_post_id}");
                }

                return $updated;
            }

/*            if ($data[$field_indexes['Testo']] == '' || $data[$field_indexes['Testo']] == null) {

                if ($data[$field_indexes['Foto']] != '' && $data[$field_indexes['Foto']] != null) {
                    //update imaggine_manifesto_old filed acf with Foto value
                    update_field('immagine_manifesto_old', $data[$field_indexes['Foto']], $existing_post_id);
                } else {
                    $this->log("Manifesto con foto non gestita: ID {$data[$field_indexes['ID']]}");
                }

                $this->log("Manifesto senza testo: ID {$data[$field_indexes['ID']]}");
                return false;
            }*/

            // Trova l'ID dell'utente con fallback migliorato
            $this->log("DEBUG: Cercando autore per IdAccount: " . $data[$field_indexes['IdAccount']]);
            $this->log("DEBUG: existing_users keys: " . implode(', ', array_keys($existing_users)));
            $this->log("DEBUG: existing_users isset check: " . (isset($existing_users[$data[$field_indexes['IdAccount']]]) ? 'TRUE' : 'FALSE'));
            
            $author_id = $this->getAuthorIdWithFallback($data[$field_indexes['IdAccount']], $existing_users);
            $this->log("DEBUG: author_id assegnato: " . $author_id);

            // Trova l'ID del necrologio associato
            $necrologio_id = $existing_necrologi[$data[$field_indexes['IdNecrologio']]] ?? null;

            $necrologio_title = $necrologio_id ? get_the_title($necrologio_id) : 'N/A';

            // Crea il post
            $this->log("DEBUG: Pubblicato field value: '" . $data[$field_indexes['Pubblicato']] . "' (type: " . gettype($data[$field_indexes['Pubblicato']]) . ")");
            $status = $data[$field_indexes['Pubblicato']] == 1 ? 'publish' : 'draft';
            $this->log("DEBUG: Post status assegnato: " . $status);
            
            $post_id = wp_insert_post([
                'post_type' => 'manifesto',
                'post_title' => $necrologio_title . ' - ' . $data[$field_indexes['ID']],
                'post_status' => $status,
                'post_date' => date('Y-m-d H:i:s', strtotime($data[$field_indexes['DataInserimento']])),
                'post_author' => $author_id,
            ]);

            if (!is_wp_error($post_id)) {
                // Aggiorna i campi ACF
                update_field('id_old', $data[$field_indexes['ID']], $post_id);
                if ($necrologio_id) {
                    update_field('annuncio_di_morte_relativo', $necrologio_id, $post_id);
                }


                if ($data[$field_indexes['Testo']] == '' || $data[$field_indexes['Testo']] == null) {
                    if ($data[$field_indexes['Foto']] != '' && $data[$field_indexes['Foto']] != null) {
                        //update imaggine_manifesto_old filed acf with Foto value
                        update_field('immagine_manifesto_old', $data[$field_indexes['Foto']], $post_id);
                    } else {
                        $this->log("Manifesto con foto non gestita: ID {$data[$field_indexes['ID']]}");
                    }
                    $this->log("Manifesto senza testo: ID {$data[$field_indexes['ID']]}");
                }else{
                    update_field('testo_manifesto', $this->cleanText($data[$field_indexes['Testo']]), $post_id);
                }
                update_field('vendor_id', $author_id, $post_id);
                update_field('tipo_manifesto', 'silver', $post_id);

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

                // get_existing_users_by_old_ids method moved to parent class MigrationTasks
    }
}