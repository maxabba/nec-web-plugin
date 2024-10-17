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
            $progress = $this->get_progress($file_name);
            $file = fopen($this->upload_dir . $file_name, 'r');
            $header = fgetcsv($file);
            $total_rows = count(file($this->upload_dir . $file_name)) - 1;

            // Salta le righe già processate
            for ($i = 0; $i < $progress['processed']; $i++) {
                fgetcsv($file);
            }

            $processed = 0;
            while (($data = fgetcsv($file)) !== FALSE && $processed < $this->batch_size) {
                $id = $data[array_search('ID', $header)];
                $email = $data[array_search('Email', $header)];
                $tipologia = $data[array_search('Tipologia', $header)];
                $provincia = $data[array_search('Provincia', $header)];

                $user_id = wp_create_user($email, wp_generate_password(), $email);

                if (!is_wp_error($user_id)) {
                    update_user_meta($user_id, 'id_old', $id);
                    update_user_meta($user_id, 'provincia', $provincia);

                    $role = $this->get_role_from_tipologia($tipologia);
                    $user = new \WP_User($user_id);
                    $user->set_role($role);

                    $this->log("Utente creato: ID $user_id, Email $email, Ruolo $role");
                } else {
                    $this->log("Errore nella creazione dell'utente: " . $user_id->get_error_message());
                }

                $processed++;
            }

            fclose($file);

            $new_progress = $progress['processed'] + $processed;
            $this->update_progress($file_name, $new_progress, $total_rows);

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


    }
}