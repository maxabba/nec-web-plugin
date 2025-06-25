<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\AnniversarioBulkUpdate')) {
    class AnniversarioBulkUpdate
    {
        public function __construct()
        {
            // Aggiungi la voce di menu a Dokan Mod
            add_action('admin_menu', [$this, 'add_submenu'], 20);

            // Gestisci la richiesta AJAX per l'aggiornamento
            add_action('wp_ajax_update_anniversario_dates', [$this, 'ajax_update_anniversario_dates']);
        }

        /**
         * Aggiunge una voce di sottomenu al menu Dokan Mod
         */
        public function add_submenu()
        {
            add_submenu_page(
                'dokan-mod', // Slug del menu principale di Dokan Mod
                'Aggiorna Date Anniversario',
                'Aggiorna Date Anniversario',
                'manage_options',
                'dokan-update-anniversario-dates',
                [$this, 'render_admin_page']
            );
        }

        /**
         * Renderizza la pagina di amministrazione
         */
        public function render_admin_page()
        {
            ?>
            <div class="wrap">
                <h1>Aggiorna Date Anniversario</h1>

                <div class="notice notice-info">
                    <p>
                        Questa utility aggiorna i campi <code>anniversario_data</code> dal formato "YYYY-MM-DD" al
                        formato
                        "YYYYMMDD" (senza trattini).
                    </p>
                </div>

                <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
                    <h2>Esegui Aggiornamento</h2>
                    <p>Clicca il pulsante sotto per aggiornare tutti i post di tipo "anniversario".</p>

                    <div id="progress-container" style="display: none; margin: 20px 0;">
                        <div class="progress-bar"
                             style="height: 20px; background-color: #f1f1f1; border-radius: 3px; overflow: hidden;">
                            <div id="progress-bar-fill"
                                 style="width: 0%; height: 100%; background-color: #0073aa; transition: width 0.3s;"></div>
                        </div>
                        <p id="progress-text">0%</p>
                    </div>

                    <div id="results-container"
                         style="display: none; margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 5px solid #46b450;">
                        <h3>Risultati</h3>
                        <p>Post totali: <span id="total-count">0</span></p>
                        <p>Post aggiornati: <span id="updated-count">0</span></p>
                        <p>Post saltati: <span id="skipped-count">0</span></p>
                        <p>Post falliti: <span id="failed-count">0</span></p>
                    </div>

                    <div id="error-container"
                         style="display: none; margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 5px solid #dc3232;">
                        <h3>Errore</h3>
                        <p id="error-message"></p>
                    </div>

                    <button id="start-update" class="button button-primary">Avvia Aggiornamento</button>
                </div>
            </div>

            <script>
                jQuery(document).ready(function ($) {
                    $('#start-update').on('click', function () {
                        // Disabilita il pulsante durante l'elaborazione
                        $(this).prop('disabled', true).text('Elaborazione in corso...');

                        // Resetta e mostra il container di progresso
                        $('#progress-container').show();
                        $('#progress-bar-fill').css('width', '0%');
                        $('#progress-text').text('0%');

                        // Nascondi i risultati precedenti
                        $('#results-container').hide();
                        $('#error-container').hide();

                        // Esegui la richiesta AJAX
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'update_anniversario_dates',
                                nonce: '<?php echo wp_create_nonce('update_anniversario_dates'); ?>'
                            },
                            success: function (response) {
                                // Ripristina il pulsante
                                $('#start-update').prop('disabled', false).text('Avvia Aggiornamento');

                                if (response.success) {
                                    // Aggiorna la barra di progresso al 100%
                                    $('#progress-bar-fill').css('width', '100%');
                                    $('#progress-text').text('100%');

                                    // Mostra i risultati
                                    $('#total-count').text(response.data.total);
                                    $('#updated-count').text(response.data.updated);
                                    $('#skipped-count').text(response.data.skipped);
                                    $('#failed-count').text(response.data.failed);
                                    $('#results-container').show();
                                } else {
                                    // Mostra l'errore
                                    $('#error-message').text(response.data.message || 'Si è verificato un errore durante l\'aggiornamento.');
                                    $('#error-container').show();
                                }
                            },
                            error: function () {
                                // Ripristina il pulsante
                                $('#start-update').prop('disabled', false).text('Avvia Aggiornamento');

                                // Mostra l'errore
                                $('#error-message').text('Errore di connessione al server.');
                                $('#error-container').show();
                            }
                        });
                    });
                });
            </script>
            <?php
        }

        /**
         * Gestisce la richiesta AJAX per aggiornare le date
         */
        public function ajax_update_anniversario_dates()
        {
            // Verifica il nonce per la sicurezza
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_anniversario_dates')) {
                wp_send_json_error(['message' => 'Verifica di sicurezza fallita.']);
                return;
            }

            // Verifica i permessi
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permessi insufficienti.']);
                return;
            }

            // Esegui l'aggiornamento
            $results = $this->bulk_update_anniversario_dates();

            // Restituisci i risultati
            wp_send_json_success($results);
        }

        /**
         * Esegue l'aggiornamento in blocco dei campi data
         *
         * @return array Risultati dell'operazione
         */
        private function bulk_update_anniversario_dates()
        {
            // Inizializza i contatori per il report
            $results = [
                'total' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0
            ];

            // Query per tutti i post di tipo anniversario
            $args = [
                'post_type' => 'anniversario',
                'posts_per_page' => -1, // Recupera tutti i post
                'post_status' => 'any', // Includi tutti gli stati
                'fields' => 'ids', // Recupera solo gli ID per efficienza
            ];

            $anniversario_posts = get_posts($args);
            $results['total'] = count($anniversario_posts);

            foreach ($anniversario_posts as $post_id) {
                // Recupera il valore corrente della data direttamente dal meta
                $current_date = get_post_meta($post_id, 'anniversario_data', true);

                // Verifica se esiste il meta ACF associato
                $existing_key = get_post_meta($post_id, '_anniversario_data', true);

                // Prepara il valore della data
                $date_value = $current_date;

                // Se la data è nel formato YYYY-MM-DD, convertiamola in YYYYMMDD
                if (!empty($current_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $current_date)) {
                    $date_value = str_replace('-', '', $current_date);
                }

                // Aggiorna/imposta il valore della data se necessario
                if ($date_value !== $current_date) {
                    update_post_meta($post_id, 'anniversario_data', $date_value);
                }

                // Imposta/aggiorna sempre il meta per la chiave ACF se non esiste o è diverso
                if (empty($existing_key) || $existing_key !== 'field_665ec95bca23d') {
                    update_post_meta($post_id, '_anniversario_data', 'field_665ec95bca23d');
                    $results['updated']++;
                } else if ($date_value !== $current_date) {
                    // Se solo il valore della data è stato aggiornato
                    $results['updated']++;
                } else {
                    // Se non è stato necessario aggiornare nulla
                    $results['skipped']++;
                }
            }

            return $results;
        }
    }
}