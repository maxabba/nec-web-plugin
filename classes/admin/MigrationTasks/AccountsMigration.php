<?php

namespace Dokan_Mods\Migration_Tasks;

use WP_Query;
use Dokan_Mods\MigrationClass;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\AccountsMigration')) {
    class AccountsMigration extends MigrationTasks
    {
        public function __construct(string $upload_dir, string $progress_file, string $log_file, int $batch_size)
        {
            parent::__construct($upload_dir, $progress_file, $log_file, $batch_size);


        }

        public function migrate_accounts_batch($file_name)
        {
            $start_time = microtime(true);
            $this->set_progess_status($file_name, 'ongoing');

            $csvFile = $this->upload_dir . $file_name;
            $progress = $this->get_progress($file_name);
            $total_rows = $this->countCsvRows($csvFile);

            // Se il file non può essere aperto, gestisci l'errore
            if (($file = fopen($csvFile, 'r')) === FALSE) {
                $this->log("Errore nell'apertura del file di input");
                $this->set_progess_status($file_name, 'failed');
                return false;
            }
            $header = fgetcsv($file);

            // Salta le righe già processate
            for ($i = 0; $i < $progress['processed']; $i++) {
                fgetcsv($file);
            }

            $processed = 0;
            while (($data = fgetcsv($file)) !== FALSE && $processed < $this->batch_size) {
                $id = $data[array_search('ID', $header)];
                $username = $data[array_search('Username', $header)];
                $email = $data[array_search('Email', $header)];
                $tipologia = $data[array_search('Tipologia', $header)];
                $citta = $this->format_luogo($data[array_search('Citta', $header)]);
                $provincia = $this->get_provincia($data[array_search('Provincia', $header)]);
                $azienda = $data[array_search('Azienda', $header)];
                $indirizzo = strip_tags($data[array_search('Info', $header)]);

                // Verifica se l'utente esiste già tramite email
                $existing_user = get_user_by('email', $email);

                if ($existing_user) {
                    // L'utente esiste già, verifica il ruolo
                    $existing_roles = $existing_user->roles;
                    $new_role = $this->get_role_from_tipologia($tipologia);

                    if ($new_role && !in_array($new_role, $existing_roles)) {
                        // Aggiungi il nuovo ruolo
                        $existing_user->add_role($new_role);
                        $this->log("Ruolo $new_role aggiunto all'utente esistente con email $email (ID: {$existing_user->ID})");
                    } elseif ($new_role == 'subscriber') {
                        // Se il ruolo derivato da tipologia non è valido, non fare nulla
                        $this->log("Ruolo non valido per l'utente con email $email, nessuna azione eseguita.");
                    } else {
                        // Se l'email esiste ma non c'è differenza di ruoli, modifica l'email
                        $new_email = "{$id}_{$email}";
                        wp_update_user(array('ID' => $existing_user->ID, 'user_email' => $new_email));
                        $this->log("Email modificata per l'utente con ID {$existing_user->ID}, nuova email: $new_email");
                    }
                } else {
                    // Crea il nuovo utente se non esiste
                    if ($username == 'admin') {
                        $username = 'admin' . $id;
                    }
                    $user_id = wp_create_user($username, wp_generate_password(), $email);

                    if (!is_wp_error($user_id)) {
                        update_user_meta($user_id, 'id_old', $id);
                        update_user_meta($user_id, 'provincia', $provincia);

                        $role = $this->get_role_from_tipologia($tipologia);
                        $user = new \WP_User($user_id);

                        $data = array(
                            'shopname' => $azienda,
                            'address' => $indirizzo,
                            'city' => $citta,
                            'state' => $provincia,
                            'country' => 'IT',
                        );

                        dokan_user_update_to_seller($user_id, $data);

                        // Rimuovi il ruolo di seller se esistente
                        $user->remove_role('seller');
                        $user->set_role($role);

                        $this->log("Utente creato: ID $user_id, Email $email, Ruolo $role");
                    } else {
                        $this->log("Errore nella creazione dell'utente: " . $user_id->get_error_message());
                    }
                }

                $processed++;
            }

            fclose($file);

            $new_progress = $progress['processed'] + $processed;
            $this->update_progress($file_name, $new_progress, $total_rows);
            $execution_time = microtime(true) - $start_time;
            $this->log("Batch execution time: {$execution_time} seconds");
            $this->set_progess_status($file_name, 'complited');

            return $new_progress >= $total_rows;
        }

        private function get_role_from_tipologia($tipologia)
        {
            $tipologia = strtolower($tipologia);
            if (in_array($tipologia, ['onoranza', 'onoranza_old', '_onoranza_'])) {
                return 'seller';
            } elseif (in_array($tipologia, ['fioraio', 'fioraio_old', 'fioriricordo'])) {
                return 'fiorai';
            }
            return 'subscriber'; // default role
        }

        private function get_provincia($sigla)
        {
            global $dbClassInstance;

            // Cerca la provincia nel database
            $result = $dbClassInstance->get_provincia_by_sigla($sigla);

            return $result;
        }


        private function format_luogo($luogo)
        {
            global $dbClassInstance;
            $luogo = strtolower($luogo);

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