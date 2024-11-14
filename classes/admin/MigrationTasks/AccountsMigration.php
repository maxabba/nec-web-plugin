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
                $this->log("Errore critico nella migrazione: " . $e->getMessage());
                $this->set_progress_status($file_name, 'error');
                return false;
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
                // Preparazione dati utente
                $user_data = $this->prepare_user_data($data, $field_indexes, $id);

                // Preparazione dati store
                $store_data = $this->prepare_store_data($user_data['azienda']);
                $dokan_settings = $this->prepare_dokan_settings(
                    $user_data['azienda'],
                    $user_data['indirizzo'],
                    $user_data['citta'],
                    $user_data['provincia']
                );

                //log the user data
                $this->log("User data: " . json_encode($user_data));


                if (isset($existing_users[$id])) {
                    $user_id = $existing_users[$id];
                    $this->log("Aggiornamento utente esistente: $user_id (ID vecchio: $id)");
                    return $this->update_existing_user(
                        $user_id,
                        $user_data['tipologia'],
                        $store_data,
                        $dokan_settings
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
            return [
                'username' => $this->sanitize_username($data[$field_indexes['Username']], $id),
                'email' => sanitize_email($data[$field_indexes['Email']]),
                'tipologia' => $data[$field_indexes['Tipologia']],
                'citta' => $this->format_luogo($data[$field_indexes['Citta']]),
                'provincia' => $this->get_provincia($data[$field_indexes['Provincia']]),
                'azienda' => $data[$field_indexes['Azienda']] ?: 'Store-' . $id,
                'indirizzo' => sanitize_text_field($data[$field_indexes['Info']]),
            ];
        }

        private function update_existing_user($user_id, $tipologia, $store_data, $dokan_settings)
        {
            global $wpdb;

            $wpdb->query('START TRANSACTION');

            try {
                $user = get_user_by('ID', $user_id);
                if (!$user) {
                    throw new Exception("Utente non trovato: $user_id");
                }

                $role = $this->get_role_from_tipologia($tipologia);

                // Aggiorna ruolo
                $current_roles = $user->roles;
                foreach ($current_roles as $current_role) {
                    $user->remove_role($current_role);
                }
                $user->add_role($role);

                if ($role === 'seller' || $role === 'fiorai') {
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