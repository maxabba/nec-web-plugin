<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\DMClass')) {
    /**
     * Sistema di controllo licenza via GitHub Gist
     */
    class DMClass
    {

        // URL del tuo Gist
        private $gist_url = "";

        // ID univoco per questo cliente
        private $client_id = 'vrTLmXq66UGroWQ5PI4kj5TOXJ4l';

        // Cache duration
        private $cache_hours = 12;

        public function __construct()
        {
            $this->gist_url = base64_decode('aHR0cHM6Ly9naXN0LmdpdGh1YnVzZXJjb250ZW50LmNvbS9tYXhhYmJhL2JhZWEwMWJkMWI0MTJlZmU4MjEwODlmY2MzODFjMDk3L3Jhdy9zdGF0dXMuanNvbg==');

            // Controlla solo se non è già stato marcato come pagato
            if (get_option('_plt_verified', false) !== true) {
                add_action('init', array($this, 'verify_status'));
            }
        }

        public function verify_status()
        {
            // Nome cache offuscato
            $cache_key = '_plt_cache_' . md5($this->client_id);
            $cached = get_transient($cache_key);

            //se lo status è blocked esegui subito fetch_and_verify
            if ($cached === 'blocked') {
                $this->fetch_and_verify();
                return;
            }

            // Usa cache se disponibile
            if ($cached !== false) {
                $this->handle_status($cached);
                return;
            }

            // Altrimenti fai richiesta
            $this->fetch_and_verify();
        }

        private function fetch_and_verify()
        {
            // Timeout breve per non rallentare il sito
            $args = array(
                'timeout' => 3,
                'sslverify' => true,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            );

            $response = wp_remote_get($this->gist_url, $args);

            // Se GitHub è down o errore, permetti funzionamento
            if (is_wp_error($response)) {
                $this->cache_status('active');
                return;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Verifica status del cliente
            $status = isset($data[$this->client_id]) ? $data[$this->client_id] : 'active';

            // Se pagato, salva permanentemente
            if ($status === 'done') {
                update_option('_plt_verified', true);
                delete_transient('_plt_cache_' . md5($this->client_id));
                return;
            }

            // Cache del risultato
            if ($status !== 'blocked') {
                $this->cache_status($status);
            }
            // Gestisci status
            $this->handle_status($status);
        }

        private function cache_status($status)
        {
            $cache_key = '_plt_cache_' . md5($this->client_id);
            set_transient($cache_key, $status, $this->cache_hours * HOUR_IN_SECONDS);
        }

        private function handle_status($status)
        {
            if ($status === 'blocked') {
                // Disattiva funzionalità principale
                $this->disable_plugin_features();
            }
        }

        private function disable_plugin_features()
        {
            add_action('init', function () {
                // Rimuovi solo i tuoi shortcodes specifici
                remove_shortcode('render_manifesti');
                remove_shortcode('vendor_selector');
            }, 999);

        }
    }
}