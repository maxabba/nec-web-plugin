<?php

namespace Dokan_Mods\Migration_Tasks;

use Exception;
use WP_Error;
use WP_Query;
use Dokan_Mods\Migration_Tasks\MigrationTasks;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\NecrologiMigration')) {
    class NecrologiMigration extends MigrationTasks
    {

        private string $image_cron_hook = 'dokan_mods_download_images';


        public function __construct(String $upload_dir, String $progress_file, String $log_file, Int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);


            add_action('dokan_mods_download_images_profile_photo', [$this, 'download_image_cron_job'], 10, 4);
            add_action('dokan_mods_download_images_manifesto_image', [$this, 'download_image_cron_job'], 10, 4);

            //add_action('wp_ajax_trigger_image_check_and_update', [$this, 'ajax_trigger_image_check_and_update']);

        }



        public function migrate_necrologi_batch($file_name)
        {
            $start_time = microtime(true);
            $this->set_progess_status($file_name, 'ongoing');

            $progress = $this->get_progress($file_name);
            $csvFile = $this->upload_dir . $file_name;
            $total_rows = $this->countCsvRows($csvFile);

            $file = fopen($this->upload_dir . $file_name, 'r');
            if ($file === FALSE) {
                $this->log("Errore nell'apertura del file di input");
                $this->set_progess_status($file_name, 'failed');
                return false;
            }
            $header = fgetcsv($file);


            if($progress['processed'] == $total_rows){
                $this->log("Tutti i record sono già stati processati");
                fclose($file);
                $this->set_progess_status($file_name, 'complited');
                return true;
            }

            if ($progress['processed'] == 0) {
                //make a list of all the old ids
                $old_ids = [];
                while (($data = fgetcsv($file)) !== FALSE) {
                    $old_ids[] = $data[array_search('ID', $header)];
                }
                $existing_posts = $this->get_existing_posts_by_old_ids($old_ids);

                //get the index of the last existing post
                $last_existing_post_index = array_search(end($old_ids), array_keys($existing_posts));
                fclose($file);
                $file = fopen($this->upload_dir . $file_name, 'r');
                $header = fgetcsv($file);
                if ($last_existing_post_index === false) {
                    //close the file and reopen it
                    $this->log("No existing posts found");
                } else {
                    $this->log("Last existing post index: $last_existing_post_index");
                    for ($i = 0; $i < $last_existing_post_index; $i++) {
                        fgetcsv($file);
                    }
                    $processed = $progress['processed'] + $last_existing_post_index;
                    $this->update_progress($file_name, $processed, $total_rows);

                }
            }elseif ($progress['processed'] > 0) {
                $skip_records = $progress['processed'];
                for ($i = 0; $i < $skip_records; $i++) {
                    fgetcsv($file);
                }
            }

            $processed = 0;
            $batch_post_old_ids = [];
            $batch_user_old_ids = [];
            $batch_data = [];

            // Collect data for the current batch
            while (($data = fgetcsv($file)) !== FALSE && $processed < $this->batch_size) {
                $id = $data[array_search('ID', $header)];
                $id_account = $data[array_search('IdAccount', $header)];

                $batch_post_old_ids[] = $id;
                $batch_user_old_ids[] = $id_account;
                $batch_data[] = $data;

                $processed++;
            }

            fclose($file);
            $progress = $this->get_progress($file_name);

            // Batch query existing posts and users
            $existing_posts = $this->get_existing_posts_by_old_ids($batch_post_old_ids);
            $existing_users = $this->get_existing_users_by_old_ids($batch_user_old_ids);

            foreach ($batch_data as $data) {

                $id = $data[array_search('ID', $header)];
                if (isset($existing_posts[$id])) {
                    $new_progress = ++$progress['processed'];
                    $this->update_progress($file_name, $new_progress, $total_rows);

                    $this->log("Annuncio di morte già esistente: ID {$existing_posts[$id]}");
                    continue;
                }

                $id_account = $data[array_search('IdAccount', $header)];
                $nome = $data[array_search('Nome', $header)];
                $cognome = $data[array_search('Cognome', $header)];
                $anni = $data[array_search('Anni', $header)];
                $data_morte = $data[array_search('DataMorte', $header)];
                $foto = $data[array_search('Foto', $header)];
                $testo = $data[array_search('Testo', $header)];
                $pubblicato = $data[array_search('Pubblicato', $header)];
                $luogo = $data[array_search('Luogo', $header)];
                $data_funerale = $data[array_search('DataFunerale', $header)];
                $immagine_manifesto = $data[array_search('ImmagineManifesto', $header)];
                $alt_testo = $data[array_search('AltTesto', $header)];

                $author_id = $existing_users[$id_account] ?? 1;

                // Check if post already exists

                $post_id = wp_insert_post(array(
                    'post_type' => 'annuncio-di-morte',
                    'post_status' => $pubblicato == 1 ? 'publish' : 'draft',
                    'post_title' => $nome . ' ' . $cognome,
                    'post_author' => $author_id ?: 1, // Use default author if not found
                    //publish date equal to $data_morte
                    'post_date' => date('Y-m-d H:i:s', strtotime($data_morte)),
                ));

                if (!is_wp_error($post_id)) {
                    update_field('field_670d4e008fc23', $id, $post_id);
                    update_field('field_6641d54cb4d9f', $nome, $post_id);
                    update_field('field_6641d566b4da0', $cognome, $post_id);
                    update_field('field_666ac79ed3c4e', intval($anni), $post_id);
                    update_field('field_6641d588b4da2', $data_morte, $post_id);
                    update_field('field_6641d6d7cc550', $testo ?: $alt_testo, $post_id);
                    update_field('field_662ca58a35da3', $this->format_luogo($luogo), $post_id);
                    update_field('field_6641d694cc548', $data_funerale, $post_id);

                    // Handle profile photo
                    // Delegare il download della foto del profilo
                    if ($foto & $post_id & $author_id) {
                        $foto_id = $this->get_image_id_by_url($foto);
                        if (!$foto_id) {
                            $this->schedule_image_download('profile_photo', $foto, $post_id, $author_id);
                        }
                    }

                    // Delegare il download dell'immagine del manifesto
                    if ($immagine_manifesto & $post_id & $author_id) {
                        $manifesto_id = $this->get_image_id_by_url($immagine_manifesto);
                        if (!$manifesto_id) {
                            $this->schedule_image_download('manifesto_image', $immagine_manifesto, $post_id, $author_id);
                        }
                    }
                    //get the current row index
                    $this->log("Annuncio di morte creato: ID $post_id, Nome $nome $cognome");


                } else {
                    $this->log("Errore nella creazione dell'annuncio di morte: " . $post_id->get_error_message());
                }
                $new_progress = ++$progress['processed'];
                $this->update_progress($file_name, $new_progress, $total_rows);
            }



            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");
            $this->set_progess_status($file_name, 'complited');

            return $new_progress >= $total_rows;
        }

/*        public function ajax_trigger_image_check_and_update()
        {
            // Security check: Verify the AJAX nonce

            // Permission check: Ensure the user has the required capability
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Non hai i permessi per eseguire questa azione.');
                wp_die();
            }

            // Retrieve and sanitize input parameters
            $csv_file = isset($_POST['csv_file']) ? sanitize_text_field($_POST['csv_file']) : '';
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

            if (empty($csv_file)) {
                wp_send_json_error('Nome del file CSV non fornito.');
                wp_die();
            }

            // Execute the batch processing function
            $result = $this->batch_check_and_update_missing_images($csv_file, $offset);

            // Handle errors returned from the batch processing
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success($result);
            }

            wp_die();
        }

        public function batch_check_and_update_missing_images($csv_file, $offset = 0, $batch_size = 50)
        {
            $this->log("Iniziando la verifica e l'aggiornamento delle immagini mancanti (batch $offset)");

            // Construct the full path to the CSV file
            $csv_file_path = $this->upload_dir . $csv_file;

            // Verify that the file exists and is readable
            if (!file_exists($csv_file_path) || !is_readable($csv_file_path)) {
                $this->log("Errore: Il file CSV non esiste o non è leggibile");
                return new WP_Error('csv_file_error', "Errore nell'apertura del file CSV");
            }

            // Open the CSV file
            if (($file = fopen($csv_file_path, 'r')) === FALSE) {
                $this->log("Errore nell'apertura del file CSV");
                return new WP_Error('csv_file_error', "Errore nell'apertura del file CSV");
            }

            // Read the header row
            $header = fgetcsv($file);
            if ($header === FALSE) {
                fclose($file);
                $this->log("Errore: Il file CSV è vuoto o non contiene dati");
                return new WP_Error('csv_file_error', "Il file CSV è vuoto o non contiene dati");
            }

            // Remove Byte Order Mark (BOM) if present
            $bom = pack('H*', 'EFBBBF');
            $header[0] = preg_replace('/^' . $bom . '/', '', $header[0]);

            // Map header columns to indices
            $id_index = array_search("ID", $header);
            $foto_index = array_search("Foto", $header);
            $immagine_manifesto_index = array_search("ImmagineManifesto", $header);

            $this->log("Header CSV: " . implode(", ", $header));
            $this->log("Indici trovati - ID: $id_index, Foto: $foto_index, ImmagineManifesto: $immagine_manifesto_index");

            // Ensure required columns are present
            if ($id_index === false || $foto_index === false || $immagine_manifesto_index === false) {
                $this->log("Errore: Colonne necessarie non trovate nel CSV");
                fclose($file);
                return new WP_Error('csv_header_error', "Errore: Colonne necessarie non trovate nel CSV");
            }

            // Skip lines up to the current offset
            $current_line = 0;
            while ($current_line < $offset && ($data = fgetcsv($file)) !== FALSE) {
                $current_line++;
            }

            $csv_data = [];
            $count = 0;

            // Read up to batch_size records
            while (($data = fgetcsv($file)) !== FALSE && $count < $batch_size) {
                $id = $data[$id_index];
                $foto = $data[$foto_index];
                $immagine_manifesto = $data[$immagine_manifesto_index];

                // Skip records where both 'foto' and 'immagine_manifesto' are empty
                if (empty($foto) && empty($immagine_manifesto)) {
                    $current_line++;
                    continue;
                }

                $csv_data[$id] = [
                    'foto' => $foto !== '-' ? $foto : '',
                    'immagine_manifesto' => $immagine_manifesto !== '-' ? $immagine_manifesto : ''
                ];
                $count++;
                $current_line++;
            }

            // Determine if the end of the file has been reached
            $complete = feof($file);

            // Close the CSV file
            fclose($file);

            if (empty($csv_data)) {
                $this->log("Nessun dato da elaborare nel batch corrente.");
                return [
                    'complete' => $complete,
                    'offset' => $current_line,
                    'processed' => 0,
                    'message' => 'Nessun dato da elaborare nel batch corrente.'
                ];
            }

            // Query posts matching the IDs from the CSV data
            $args = [
                'post_type' => 'annuncio-di-morte',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'id_old',
                        'value' => array_keys($csv_data),
                        'compare' => 'IN'
                    ]
                ]
            ];
            $query = new WP_Query($args);

            $processed = 0;
            foreach ($query->posts as $post_id) {
                $old_id = get_field('id_old', $post_id);

                if (!isset($csv_data[$old_id])) {
                    continue;
                }

                try {
                    $this->check_and_update_image($post_id, 'field_key_for_foto', $csv_data[$old_id]['foto'], 'profile_photo');
                    $this->check_and_update_image($post_id, 'field_key_for_immagine_manifesto', $csv_data[$old_id]['immagine_manifesto'], 'manifesto_image');
                    $processed++;
                } catch (Exception $e) {
                    $this->log("Errore nell'elaborazione del post ID $post_id: " . $e->getMessage());
                    // Continue processing the next post despite the error
                }
            }

            $this->log("Verifica e aggiornamento delle immagini mancanti completato per il batch $offset. Elaborati: $processed");

            return [
                'complete' => $complete,
                'offset' => $current_line,
                'processed' => $processed,
                'message' => $complete ? 'Processo completato' : 'Batch elaborato con successo'
            ];
        }

        private function check_and_update_image($post_id, $acf_field_key, $image_path, $image_type)
        {
            try {
                $current_image = get_field($acf_field_key, $post_id);
                if (!$current_image && $image_path) {
                    $author_id = get_post_field('post_author', $post_id);
                    $this->log("Programmazione download immagine: post_id=$post_id, tipo=$image_type, percorso=$image_path");
                    $this->schedule_image_download($image_type, $image_path, $post_id, $author_id);
                    $this->log("Programmato il download dell'immagine $image_type per il post ID $post_id");
                } else {
                    $this->log("Immagine non aggiornata: post_id=$post_id, tipo=$image_type, immagine_corrente=" . ($current_image ? 'presente' : 'assente') . ", nuovo_percorso=" . ($image_path ?: 'assente'));
                }
            } catch (Exception $e) {
                $this->log("Errore durante l'aggiornamento dell'immagine per il post ID $post_id: " . $e->getMessage());
                // Rethrow the exception to be handled in the calling function
                throw $e;
            }
        }*/

        private function schedule_image_download($image_type, $image_url, $post_id, $author_id)
        {
            // Use the appropriate hook based on the image type
            $hook = 'dokan_mods_download_images_' . $image_type;

            // Schedule the job only if it doesn't already exist for this specific post and image type
            if (!wp_next_scheduled($hook, [$image_type, $image_url, $post_id, $author_id])) {
                wp_schedule_single_event(time() +10, $hook, [$image_type, $image_url, $post_id, $author_id]);
            }
        }


        public function download_image_cron_job($image_type, $image_url, $post_id, $author_id)
        {
            //$this->log("Inizio download dell'immagine $image_type per il post ID $post_id");

            // Scarica e carica l'immagine
            $image_id = $this->download_and_upload_image($image_url, $post_id, $author_id);

            if ($image_id) {
                // Aggiorna il post con l'ID dell'immagine
                if ($image_type === 'profile_photo') {
                    update_field('field_6641d593b4da3', $image_id, $post_id); // Campo per la foto del profilo
                } elseif ($image_type === 'manifesto_image') {
                    update_field('field_6641d6eecc551', $image_id, $post_id); // Campo per il manifesto
                }

                //$this->log("Download dell'immagine $image_type completato per il post ID $post_id");
            } else {
                $this->log("Errore nel download dell'immagine $image_type per il post ID $post_id");
            }
        }

        // New helper function to get image ID by URL
        private function get_image_id_by_url($image_url)
        {
            global $wpdb;
            // Rimuoviamo eventuali percorsi completi per cercare solo il nome del file
            $image_filename = basename($image_url);

            // Cerchiamo il nome del file nel campo guid
            $query = $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE guid LIKE %s",
                '%' . $wpdb->esc_like($image_filename) . '%'
            );

            $attachment = $wpdb->get_col($query);

            // Restituiamo l'ID se lo troviamo, altrimenti null
            return $attachment ? $attachment[0] : null;
        }


        private function download_and_upload_image($image_path, $post_id, $author_id)
        {
            $image_url = 'https://necrologi.sciame.it/necrologi/' . $image_path;
            $upload_dir = wp_upload_dir();
            $max_retries = 5;
            $retry_count = 0;
            $success = false;
            $attach_id = false;

            while ($retry_count < $max_retries && !$success) {
                $this->log("Tentativo di download dell'immagine ($retry_count): $image_url");
                $image_data = file_get_contents($image_url);

                if ($image_data === false) {
                    $this->log("Errore nel download dell'immagine al tentativo $retry_count: $image_url");
                    $retry_count++;
                    continue; // Retry
                }

                $filename = basename($image_path);
                $unique_filename = wp_unique_filename($upload_dir['path'], $filename);
                $upload_file = $upload_dir['path'] . '/' . $unique_filename;

                if (file_put_contents($upload_file, $image_data) === false) {
                    $this->log("Errore nel salvare l'immagine al tentativo $retry_count: $upload_file");
                    $retry_count++;
                    continue; // Retry
                }

                $wp_filetype = wp_check_filetype($filename, null);
                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_author' => $author_id ?: 1, // Use default author if not found
                );

                $attach_id = wp_insert_attachment($attachment, $upload_file, $post_id);
                if (is_wp_error($attach_id)) {
                    $this->log("Errore nell'inserimento dell'allegato al tentativo $retry_count: " . $attach_id->get_error_message());
                    $retry_count++;
                    continue; // Retry
                }

                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload_file);
                wp_update_attachment_metadata($attach_id, $attach_data);

                $success = true; // Success if all operations pass
            }

            if (!$success) {
                $this->log("Impossibile scaricare e caricare l'immagine dopo $max_retries tentativi: $image_url");
                return false;
            }

            // Verifica finale che tutte le operazioni siano state completate
            if (!file_exists($upload_file) || !$attach_id || is_wp_error($attach_id)) {
                $this->log("Errore: l'immagine non è stata correttamente salvata o allegata.");
                return false;
            }

            $this->log("Immagine scaricata e allegata con successo: $image_url");
            return $attach_id;
        }


        // New helper function to get post by old ID
        private function get_existing_posts_by_old_ids($old_ids)
        {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($old_ids), '%s'));
            $sql = "
            SELECT pm.post_id, pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'id_old'
            AND pm.meta_value IN ($placeholders)
            AND p.post_type = 'annuncio-di-morte'
        ";
            $prepared_sql = $wpdb->prepare($sql, $old_ids);
            $results = $wpdb->get_results($prepared_sql, ARRAY_A);
            $existing_posts = [];
            foreach ($results as $row) {
                $existing_posts[$row['meta_value']] = $row['post_id'];
            }
            return $existing_posts;
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


        private function format_luogo($luogo)
        {
            global $dbClassInstance;
            $luogo = strtolower($luogo);
            if (strpos($luogo, 'cimitero di') !== false) {
                $parts = explode('cimitero di', $luogo);
                $luogo = trim($parts[1]);
            }

            // Rimuovi eventuali caratteri speciali e numeri
            $luogo = preg_replace('/[^a-z\s]/', '', $luogo);

            // Cerca il comune nel database
            $result = $dbClassInstance->search_comune($luogo);

            if (!empty($result)) {
                // Se troviamo una corrispondenza, usiamo il nome del comune dal database
                $luogo = $result[0]['nome'];
            } else {
                // Se non troviamo una corrispondenza, formatta il nome originale
                $luogo = ucwords($luogo);
            }

            return $luogo;
        }


    }
}