<?php

namespace Dokan_Mods\Migration_Tasks;

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

            $new_progress = $progress['processed'] + $processed;
            foreach ($batch_data as $data) {

                $id = $data[array_search('ID', $header)];
                if (isset($existing_posts[$id])) {
                    $new_progress = $progress['processed']++;
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
                    if ($foto) {
                        $foto_id = $this->get_image_id_by_url($foto);
                        if (!$foto_id) {
                            $this->schedule_image_download('profile_photo', $foto, $post_id, $author_id);
                        }
                    }

                    // Delegare il download dell'immagine del manifesto
                    if ($immagine_manifesto) {
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
                $new_progress = $progress['processed']++;
                $this->update_progress($file_name, $new_progress, $total_rows);
            }



            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");
            $this->set_progess_status($file_name, 'complited');

            return $new_progress >= $total_rows;
        }


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
            $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url));
            return $attachment ? $attachment[0] : null;
        }

        private function download_and_upload_image($image_path, $post_id, $author_id)
        {
            $image_url = 'https://necrologi.sciame.it/necrologi/' . $image_path;
            $upload_dir = wp_upload_dir();
            $image_data = file_get_contents($image_url);

            if ($image_data === false) {
                $this->log("Errore nel download dell'immagine: $image_url");
                return false;
            }

            $filename = basename($image_path);
            $unique_filename = wp_unique_filename($upload_dir['path'], $filename);
            $upload_file = $upload_dir['path'] . '/' . $unique_filename;

            if (file_put_contents($upload_file, $image_data) === false) {
                $this->log("Errore nel salvare l'immagine: $upload_file");
                return false;
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
                $this->log("Errore nell'inserimento dell'allegato: " . $attach_id->get_error_message());
                return false;
            }

            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload_file);
            wp_update_attachment_metadata($attach_id, $attach_data);

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