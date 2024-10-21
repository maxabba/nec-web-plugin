<?php

namespace Dokan_Mods\Migration_Tasks;

use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\RicorrenzeMigration')) {
    class RicorrenzeMigration extends MigrationTasks
    {

        private string $image_cron_hook = 'dokan_mods_download_images';


        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);

            add_action('dokan_mods_download_images_trigesimo', [$this, 'download_image_cron_job'], 20, 4);
            add_action('dokan_mods_download_images_anniversario', [$this, 'download_image_cron_job'], 20, 4);
        }


        public function migrate_ricorrenze_batch($file_name)
        {

            $start_time = microtime(true);
            $this->set_progess_status($file_name, 'ongoing');

            $progress = $this->get_progress($file_name);

            $file_name_output = $file_name . '_elaborato.csv';

            if ($progress['processed'] == 0) {
                $this->log("Inizio elaborazione file: $file_name");
                $file_name_output =  $this->manipola_dati($this->upload_dir . $file_name, $file_name_output);
                $this->log("File elaborato: $file_name_output");
            }
            $csvFile = $this->upload_dir . $file_name_output;
            $total_rows = $this->countCsvRows($csvFile);

            $file = fopen($this->upload_dir . $file_name_output, 'r');
            if ($file === FALSE) {
                $this->log("Errore nell'apertura del file di input");
                $this->set_progess_status($file_name, 'failed');
                return false;
            }

            $header = fgetcsv($file);



            if ($progress['processed'] == $total_rows) {
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
                fclose($file);
                $file = fopen($this->upload_dir . $file_name_output, 'r');
                $header = fgetcsv($file);

                $existing_posts = $this->get_existing_posts_by_old_ids($old_ids);

                //get the index of the last existing post
                $last_existing_post_index = array_search(end($old_ids), array_keys($existing_posts));

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
            } elseif ($progress['processed'] > 0) {
                $skip_records = $progress['processed'];
                for ($i = 0; $i < $skip_records; $i++) {
                    fgetcsv($file);
                }
                $this->log("Skipped after $skip_records records");
            }

            $processed = 0;
            $batch_post_old_ids = [];
            $batch_data = [];

            // Collect data for the current batch
            while (($data = fgetcsv($file)) !== FALSE && $processed < $this->batch_size) {
                $id = $data[array_search('ID', $header)];
                $batch_post_old_ids[] = $id;
                $batch_data[] = $data;

                $processed++;
            }


            fclose($file);
            $progress = $this->get_progress($file_name);

            // Batch query existing posts and users
            $existing_posts = $this->get_existing_posts_by_old_ids($batch_post_old_ids);
            foreach ($batch_data as $data) {
                $id = $data[array_search('ID', $header)];

                if (isset($existing_posts[$id])) {
                    $new_progress = $progress['processed']++;
                    $this->update_progress($file_name, $new_progress, $total_rows);

                    $this->log("Ricorrenza già esistente: ID {$existing_posts[$id]}");
                    continue;
                }

                $tipo = $data[array_search('Tipo', $header)];
                if ($tipo == 0) {
                    $new_progress = $progress['processed']++;
                    $this->update_progress($file_name, $new_progress, $total_rows);
                    $this->log("Tipo non valido per ID: $id");

                    continue; // Salta questo record
                }

                $id_necrologio = $data[array_search('IdNecrologio', $header)];
                $data_ricorrenza = $data[array_search('Data', $header)];
                $foto = $data[array_search('Foto', $header)];
                $testo = $data[array_search('Testo', $header)];
                $pubblicato = $data[array_search('Pubblicato', $header)];
                $anni = $data[array_search('Anni', $header)];

                $necrologio = $this->get_post_by_old_id($id_necrologio);
                if (!$necrologio) {
                    $this->log("Annuncio di morte non trovato per ID: $id_necrologio");
                    $new_progress = $progress['processed']++;
                    $this->update_progress($file_name, $new_progress, $total_rows);
                    continue;
                }

                //get the acf field cont $necrologio->ID field_662ca58a35da3

                $citta = get_field('field_662ca58a35da3', $necrologio->ID);

                $post_type = $tipo == 1 ? 'trigesimo' : 'anniversario';
                $author_id = $necrologio->post_author;

                // Calcola la data di pubblicazione
                if( $tipo == 1){
                    $pub_date = date('Y-m-d H:i:s', strtotime($necrologio->post_date . ' +30 days'));
                }elseif($tipo == 2){
                    $pub_date = date('Y-m-d H:i:s', strtotime($necrologio->post_date . ' +'. $anni . ' year'));
                }

                $post_id = wp_insert_post(array(
                    'post_type' => $post_type,
                    'post_status' => $pubblicato == 1 ? 'publish' : 'draft',
                    'post_title' => get_the_title($necrologio->ID),
                    'post_author' => $author_id,
                    'post_date' => $pub_date,
                ));

                if (!is_wp_error($post_id)) {
                    // Campo comune per entrambi i tipi
                    $id_field = $tipo == 1 ? 'field_66570739481f1' : 'field_665ec95bc65ad';
                    update_field($id_field, $necrologio->ID, $post_id);

                    // Campi specifici per tipo
                    if ($tipo == 1) { // Trigesimo
                        update_field('field_6657095c5cdbc', $data_ricorrenza, $post_id);
                        update_field('field_6657670ab5c2a', $testo, $post_id);
                    } else { // Anniversario
                        update_field('field_665ec95bca23d', $data_ricorrenza, $post_id);
                        update_field('field_665ec95bc662a', $testo, $post_id);
                        update_field('field_665ec9c7037b2', $anni, $post_id);
                    }

                    if ($citta){
                        update_field('field_662ca58a35da3', $citta, $post_id);
                    }

                    // Gestione della foto
                    if ($foto) {
                        $foto_id = $this->get_image_id_by_url($foto);
                        if (!$foto_id) {
                            if ($tipo == 1) {
                                $this->schedule_image_download('trigesimo', $foto, $post_id, $author_id);
                            } else {
                                $this->schedule_image_download('anniversario', $foto, $post_id, $author_id);
                            }
                        }
                    }

                    $this->log("Ricorrenza creata: ID $post_id, Tipo: $post_type");
                } else {
                    $this->log("Errore nella creazione della ricorrenza: " . $post_id->get_error_message());
                }
                $new_progress = $progress['processed']++;
                $this->update_progress($file_name, $new_progress, $total_rows);
            }

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");
            $this->set_progess_status($file_name, 'complited');
            $this->log("Procedo all'esecuzione del download delle immagini, tempo stimato 10 minuti, attendi...");

            //1000 secondi in minuti sono 16.6666666667


            $new_progress = $this->get_progress($file_name);
            $new_progress = $new_progress['processed'];
            return $new_progress >= $total_rows;
        }

        private function get_existing_posts_by_old_ids($old_ids)
        {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($old_ids), '%s'));

            // Definisci i tipi di post che vuoi cercare
            $post_types = ['trigesimo', 'anniversario'];
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


        private function schedule_image_download($image_type, $image_url, $post_id, $author_id)
        {
            // Use the appropriate hook based on the image type
            $hook = $this->image_cron_hook . '_' . $image_type;

            // Schedule the job only if it doesn't already exist for this specific post and image type
            if (!wp_next_scheduled($hook, [$image_type, $image_url, $post_id, $author_id])) {
                wp_schedule_single_event(time() + 10, $hook, [$image_type, $image_url, $post_id, $author_id]);
            }
        }


        public function download_image_cron_job($image_type, $image_url, $post_id, $author_id)
        {
            //$this->log("Inizio download dell'immagine $image_type per il post ID $post_id");

            // Scarica e carica l'immagine
            $image_id = $this->download_and_upload_image($image_url, $post_id, $author_id);

            if ($image_id) {
                // Aggiorna il post con l'ID dell'immagine
                if ($image_type === 'trigesimo') {
                    update_field('field_66576726b5c2b', $image_id, $post_id); // Campo per la foto del profilo
                } elseif ($image_type === 'anniversario') {
                    update_field('field_665ec95bc666c', $image_id, $post_id); // Campo per il manifesto
                }

            } else {
                $this->log("Errore nel download dell'immagine $image_type per il post ID $post_id");
            }
        }

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
                'post_author' => $author_id,
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

        private function manipola_dati($input_file , $file_name_output)
        {
            $info = pathinfo($input_file);
            $output_file = $info['dirname'] . '/' . $file_name_output;

            if (($handle = fopen($input_file, "r")) === FALSE) {
                return false; // Errore nell'apertura del file di input
            }

            //if output file already exists, delete it
            if (file_exists($output_file)) {
                unlink($output_file);
            }

            $output = fopen($output_file, "w");
            if ($output === FALSE) {
                fclose($handle);
                return false; // Errore nella creazione del file di output
            }

            // Read the header
            $header = fgetcsv($handle);
            $header[] = 'Tipo';
            $header[] = 'Anni';
            fputcsv($output, $header);

            $tipo_ricorrenza_index = array_search('TipoRicorrenza', $header);
            if ($tipo_ricorrenza_index === false) {
                fclose($handle);
                fclose($output);
                return false; // Colonna 'TipoRicorrenza' non trovata
            }

            while (($row = fgetcsv($handle)) !== FALSE) {
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

                fputcsv($output, $row);
            }

            fclose($handle);
            fclose($output);

            return $file_name_output; // Elaborazione completata con successo
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




    }
}