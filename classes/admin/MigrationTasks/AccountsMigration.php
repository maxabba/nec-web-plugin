<?php

namespace Dokan_Mods\Migration_Tasks;

use Exception;
use RuntimeException;
use SplFileObject;
use WP_User;

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

                $header = $file->fgetcsv();
                $file->seek($processed);

                //foreach header remove spaces
                $header = array_map(function ($header) {
                    return str_replace(' ', '', $header);
                }, $header);

                $batch_data = [];
                $batch_user_old_ids = [];
                $id_index = array_search('ID', $header);

                // Raccolta dati batch
                while (!$file->eof() && count($batch_data) < $this->batch_size) {
                    $data = $file->fgetcsv();
                    if (empty($data)) continue;

                    $old_id = $data[$id_index];
                    if (!empty($old_id)) {
                        $batch_data[] = $data;
                        $batch_user_old_ids[] = $old_id;
                    }
                }

                if (empty($batch_data)) {
                    $this->set_progress_status($file_name, 'finished');
                    return true;
                }

                // Pre-fetch degli utenti esistenti
                $existing_users = $this->get_existing_users_by_old_ids($batch_user_old_ids);
                //log the number of $existing_users
                $this->log("Existing users: " . count($existing_users));
                // Process each record in the batch
                foreach ($batch_data as $data) {
                    $old_id = $data[$id_index];

                    // Log dell'inizio dell'elaborazione
                    $this->log("Inizio elaborazione utente con ID vecchio: $old_id");

                    try {
                        $result = $this->process_single_record(
                            $data,
                            $header,
                            $existing_users
                        );

                        if ($result) {
                            $this->log("Elaborazione completata per ID vecchio: $old_id");
                        } else {
                            $this->log("Errore nell'elaborazione per ID vecchio: $old_id");
                        }

                    } catch (Exception $e) {
                        $this->log("Eccezione durante l'elaborazione dell'utente $old_id: " . $e->getMessage());
                        continue; // Continua con il prossimo record anche in caso di errore
                    }

                    $processed++;
                    $this->update_progress($file_name, $processed, $total_rows);
                }

                // Cleanup
                unset($batch_data, $batch_user_old_ids, $existing_users);
                $file = null;

                $execution_time = microtime(true) - $start_time;
                $this->log("Tempo di esecuzione batch: {$execution_time} secondi");

                if ($processed >= $total_rows) {
                    $this->set_progress_status($file_name, 'finished');
                    return true;
                } else {
                    $this->set_progress_status($file_name, 'completed');
                    return false;
                }

            } catch (Exception $e) {
                $error_message = "Errore durante la migrazione accounts: " . $e->getMessage();
                $this->log("ERRORE CRITICO: " . $error_message);
                $this->log("Stack trace: " . $e->getTraceAsString());
                
                // Aggiorna lo status del progresso come errore
                $this->set_progress_status($file_name, 'error');
                
                // Re-throw per permettere al sistema di loggare in wc-logs
                throw $e;
            }
        }

        private function process_single_record($data, $header, $existing_users)
        {
            static $field_indexes = null;

            if ($field_indexes === null) {
                $field_indexes = [
                    'ID' => array_search('ID', $header),
                    'Username' => array_search('Username', $header),
                    'Email' => array_search('Email', $header),
                    'Tipologia' => array_search('Tipologia', $header),
                    'Citta' => array_search('Citta', $header),
                    'Provincia' => array_search('Provincia', $header),
                    'Azienda' => array_search('Azienda', $header),
                    'Info' => array_search('Info', $header),
                ];
            }

            $id = $data[$field_indexes['ID']];

            if (empty($id)) {
                $this->log("ID mancante nel record");
                return false;
            }

            try {
                $user_data = $this->prepare_user_data($data, $field_indexes, $id);
                $store_data = $this->prepare_store_data($user_data['azienda']);
                $dokan_settings = $this->prepare_dokan_settings(
                    $user_data['azienda'],
                    $user_data['indirizzo'],
                    $user_data['citta'],
                    $user_data['provincia']
                );

                // Verifica se esiste già un utente con questa email
                $existing_email_user = get_user_by('email', $user_data['email']);
                $new_role = $this->get_role_from_tipologia($user_data['tipologia']);

                // Se esiste un utente con questa email
                if ($existing_email_user) {
                    $is_existing_fioraio = in_array('fiorai', $existing_email_user->roles);
                    $is_new_fioraio = ($new_role === 'fiorai');
                    $is_existing_seller = in_array('seller', $existing_email_user->roles);

                    // Se uno dei due è fioraio, procediamo con il merge
                    if ($is_existing_fioraio || $is_new_fioraio) {
                        // Se l'utente che stiamo processando esiste già nel nostro sistema
                        if (isset($existing_users[$id])) {
                            $duplicate_user_id = $existing_users[$id];

                            // Se l'utente duplicato è diverso dall'utente esistente con la stessa email
                            if ($duplicate_user_id != $existing_email_user->ID) {
                                $this->log("Eliminazione utente duplicato ID: {$duplicate_user_id} per merge con ID: {$existing_email_user->ID}");
                                require_once(ABSPATH . 'wp-admin/includes/user.php');
                                wp_delete_user($duplicate_user_id, $existing_email_user->ID);
                            }
                        }

                        // Determina quale username e ID old mantenere
                        $keep_old_id = $id;
                        $keep_username = $user_data['username'];

                        if ($is_existing_seller) {
                            $keep_old_id = get_user_meta($existing_email_user->ID, 'id_old', true);
                            $keep_username = $existing_email_user->user_login;
                        }

                        // Aggiorna l'utente esistente MANTENENDO i ruoli esistenti
                        $user = new WP_User($existing_email_user->ID);

                        // Se il nuovo ruolo è fiorai e l'utente non ce l'ha già, aggiungiamolo
                        if ($new_role === 'fiorai' && !$is_existing_fioraio) {
                            $user->add_role('fiorai');
                        }
                        // Se il nuovo ruolo è seller e l'utente non ce l'ha già, aggiungiamolo
                        if ($new_role === 'seller' && !$is_existing_seller) {
                            $user->add_role('seller');
                        }

                        return $this->update_existing_user(
                            $existing_email_user->ID,
                            $keep_username,
                            'both', // Indichiamo che manteniamo entrambi i ruoli
                            $store_data,
                            $dokan_settings,
                            $keep_old_id
                        );
                    }
                }

                // Procedi con la logica standard se non c'è merge
                if (isset($existing_users[$id])) {
                    $user_id = $existing_users[$id];
                    $this->log("Aggiornamento utente esistente: $user_id (ID vecchio: $id)");
                    return $this->update_existing_user(
                        $user_id,
                        $user_data['username'],
                        $user_data['tipologia'],
                        $store_data,
                        $dokan_settings,
                        $id
                    );
                } else {
                    $this->log("Creazione nuovo utente per ID vecchio: $id");
                    return $this->create_new_user(
                        $user_data['username'],
                        $user_data['email'],
                        $id,
                        $user_data['tipologia'],
                        $store_data,
                        $dokan_settings
                    );
                }

            } catch (Exception $e) {
                $this->log("Errore nell'elaborazione del record $id: " . $e->getMessage());
                return false;
            }
        }

        private function prepare_user_data($data, $field_indexes, $id)
        {
            $azienda = $data[$field_indexes['Azienda']] ?: 'Store-' . $id;
            // Genera username dall'azienda: sostituisce spazi con punti e converti in lowercase
            $username = strtolower(str_replace(' ', '.', $azienda));

            return [
                'username' => $this->sanitize_username($username, $id),
                'email' => sanitize_email($data[$field_indexes['Email']]),
                'tipologia' => $data[$field_indexes['Tipologia']],
                'citta' => $this->format_luogo($data[$field_indexes['Citta']]),
                'provincia' => $this->get_provincia($data[$field_indexes['Provincia']]),
                'azienda' => $azienda,
                'indirizzo' => sanitize_text_field($data[$field_indexes['Info']]),
            ];
        }


        private function update_existing_user($user_id, $username, $tipologia, $store_data, $dokan_settings, $old_id)
        {
            global $wpdb;

            $wpdb->query('START TRANSACTION');

            try {
                $user = get_user_by('ID', $user_id);
                if (!$user) {
                    throw new Exception("Utente non trovato: $user_id");
                }

                // Aggiorna username
                $wpdb->update(
                    $wpdb->users,
                    ['user_login' => $username],
                    ['ID' => $user_id]
                );
                update_user_meta($user_id, 'id_old', $old_id);

                // Se non è 'both', gestisci i ruoli normalmente
                if ($tipologia !== 'both') {
                    $role = $this->get_role_from_tipologia($tipologia);
                    // In questo caso non rimuoviamo i ruoli esistenti
                    $user->add_role($role);
                }

                // Setup vendor data se l'utente ha il ruolo seller
                if (in_array('seller', $user->roles) || in_array('fiorai', $user->roles)) {
                    $this->setup_vendor_data($user, $user_id, $store_data, $dokan_settings);
                }

                $wpdb->query('COMMIT');
                clean_user_cache($user_id);

                return true;

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                $this->log("Errore nell'aggiornamento dell'utente $user_id: " . $e->getMessage());
                return false;
            }
        }

        private function sanitize_username($username, $id)
        {
            $username = sanitize_user($username);
            return $username === 'admin' ? 'admin' . $id : $username;
        }

        private function prepare_store_data($azienda)
        {
            return [
                'fname' => $azienda,
                'lname' => '',
                'shopname' => $azienda,
                'phone' => '',
                'shopurl' => sanitize_title($azienda),
            ];
        }

        private function prepare_dokan_settings($azienda, $indirizzo, $citta, $provincia)
        {
            return [
                'store_name' => $azienda,
                'social' => [],
                'payment' => [],
                'phone' => '',
                'show_email' => 'no',
                'location' => '',
                'find_address' => '',
                'dokan_category' => '',
                'banner' => 0,
                'store_ppp' => 10,
                'address' => [
                    'street_1' => $indirizzo,
                    'street_2' => '',
                    'city' => $citta,
                    'zip' => '',
                    'country' => 'IT',
                    'state' => $provincia,
                ],
                'store_open_close' => [
                    'enabled' => 'no',
                    'time' => [],
                ],
            ];
        }


        private function create_new_user($username, $email, $old_id, $tipologia, $store_data, $dokan_settings)
        {
            global $wpdb;

            $wpdb->query('START TRANSACTION');

            try {
                $user_id = wp_create_user($username, wp_generate_password(), $email);
                if (is_wp_error($user_id)) {
                    throw new Exception($user_id->get_error_message());
                }

                $user = new WP_User($user_id);
                update_user_meta($user_id, 'id_old', $old_id);

                $role = $this->get_role_from_tipologia($tipologia);
                $user->set_role($role);

                if ($role === 'seller' || $role === 'fiorai') {
                    $this->setup_vendor_data($user, $user_id, $store_data, $dokan_settings);
                }

                $wpdb->query('COMMIT');
                clean_user_cache($user_id);

                return true;

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                $this->log("Errore nella creazione del nuovo utente: " . $e->getMessage());
                return false;
            }
        }

        private function setup_vendor_data($user, $user_id, $store_data, $dokan_settings)
        {
            dokan_user_update_to_seller($user, $store_data);
            update_user_meta($user_id, 'dokan_profile_settings', $dokan_settings);
            update_user_meta($user_id, 'dokan_store_name', $store_data['shopname']);
            update_user_meta($user_id, '_store_address', $dokan_settings['address']);
            update_user_meta($user_id, 'dokan_enable_selling', 'yes');
            update_user_meta($user_id, 'dokan_publishing', 'yes');
        }

        private function get_role_from_tipologia($tipologia)
        {
            $tipologia = strtolower($tipologia);
            if (in_array($tipologia, ['onoranza', 'onoranza_old', '_onoranza_'])) {
                return 'seller';
            } elseif (in_array($tipologia, ['fioraio', 'fioraio_old', 'fioriricordo'])) {
                return 'fiorai';
            }
            return 'subscriber';
        }

        private function get_provincia($sigla)
        {
            global $dbClassInstance;
            return $dbClassInstance->get_provincia_by_sigla($sigla) ?: '';
        }

        private function format_luogo($luogo)
        {
            global $dbClassInstance;
            $luogo = strtolower(trim($luogo));
            $luogo = preg_replace('/[^a-z\s]/', '', $luogo);

            $result = $dbClassInstance->search_comune($luogo);
            return !empty($result) ? $result[0]['nome'] : ucwords($luogo);
        }


    }
}