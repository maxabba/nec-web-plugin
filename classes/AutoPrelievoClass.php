<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\AutoPrelievoClass')) {
    class AutoPrelievoClass
    {
        private $option_prefix = 'dokan_auto_prelievo_';
        private $log_option_name = 'dokan_auto_prelievo_logs';
        private $max_log_entries = 100; // Numero massimo di log da mantenere
        private $debug_mode = true;

        public function __construct()
        {
            // Registrazione menu
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('admin_init', [$this, 'register_settings']);

            // Hook per i cron
            add_filter('cron_schedules', [$this, 'setup_cron_schedules']);

            // Gestione attivazione/disattivazione del cron quando cambiano le impostazioni
            add_action('update_option_' . $this->option_prefix . 'enabled', [$this, 'handle_enabled_option_update'], 10, 2);
            add_action('update_option_' . $this->option_prefix . 'frequency', [$this, 'reschedule_withdrawals'], 10, 2);
            add_action('update_option_' . $this->option_prefix . 'day', [$this, 'reschedule_withdrawals'], 10, 2);

            add_action('dokan_auto_withdraw_event', [$this, 'process_automatic_withdrawals']);

            // Verifica iniziale dello stato del cron
            add_action('init', [$this, 'check_cron_status']);
            add_action('admin_init', [$this, 'handle_clear_logs']);

        }

        /**
         * Aggiunge il sottomenu a Dokan
         */
        public function add_submenu()
        {
            add_submenu_page(
                'dokan-mod',
                __('Prelievi Automatici', 'dokan-mod'),
                __('Prelievi Automatici', 'dokan-mod'),
                'manage_options',
                'dokan-auto-prelievo',
                [$this, 'render_settings_page']
            );
        }

        /**
         * Registra le impostazioni
         */
        public function register_settings()
        {
            register_setting($this->option_prefix . 'group', $this->option_prefix . 'enabled');
            register_setting($this->option_prefix . 'group', $this->option_prefix . 'frequency');
            register_setting($this->option_prefix . 'group', $this->option_prefix . 'day');
            register_setting($this->option_prefix . 'group', $this->option_prefix . 'min_amount');
        }


        /**
         * Aggiunge un log al sistema
         */

        private function add_log($message, $type = 'info')
        {
            if ($this->debug_mode) {
                $timestamp = current_time('Y-m-d H:i:s');
                $log_entry = "$timestamp$type$message";
                error_log($log_entry);
            }

            $logs = get_option($this->log_option_name, []);
            array_unshift($logs, [
                'timestamp' => current_time('mysql'),
                'message' => $message,
                'type' => $type
            ]);
            $logs = array_slice($logs, 0, $this->max_log_entries);
            update_option($this->log_option_name, $logs);
        }

        /**
         * Pulisce i log
         */
        private function clear_logs()
        {
            delete_option($this->log_option_name);
        }

        /**
         * Aggiunge schedulazioni personalizzate
         */
        public function setup_cron_schedules($schedules)
        {
            if (!is_array($schedules)) {
                $schedules = array();
            }

            $schedules['weekly'] = array(
                'interval' => 7 * 24 * 60 * 60,
                'display' => __('Once Weekly', 'dokan-mod')
            );

            $schedules['monthly'] = array(
                'interval' => 30 * 24 * 60 * 60,
                'display' => __('Once Monthly', 'dokan-mod')
            );

            return $schedules;
        }

        /**
         * Verifica lo stato del cron e lo inizializza se necessario
         */
        public function check_cron_status()
        {
            if (get_option($this->option_prefix . 'enabled') === 'yes' && !wp_next_scheduled('dokan_auto_withdraw_event')) {
                $this->schedule_withdrawals();
            }
        }

        /**
         * Gestisce l'aggiornamento dell'opzione enabled
         */
        public function handle_enabled_option_update($old_value, $new_value)
        {
            error_log('Auto withdraw enabled option updated from ' . $old_value . ' to ' . $new_value);

            if ($new_value === 'yes') {
                $this->schedule_withdrawals();
            } else {
                $this->remove_scheduled_withdrawals();
            }
        }

        /**
         * Rischedula i prelievi quando cambiano frequenza o giorno
         */
        public function reschedule_withdrawals($old_value, $new_value)
        {
            if (get_option($this->option_prefix . 'enabled') === 'yes') {
                $this->remove_scheduled_withdrawals();
                $this->schedule_withdrawals();
            }
        }

        /**
         * Programma i prelievi automatici
         */
        public function schedule_withdrawals()
        {
            // Rimuovi l'evento esistente per evitare duplicati
            $this->remove_scheduled_withdrawals();

            $frequency = get_option($this->option_prefix . 'frequency', 'monthly');
            $timestamp = $this->get_next_schedule_timestamp();

            // Verifica e crea l'evento
            wp_schedule_event($timestamp, $frequency, 'dokan_auto_withdraw_event');

            error_log('Scheduled new withdraw event: ' . date('Y-m-d H:i:s', $timestamp) . ' with frequency: ' . $frequency);
        }

        /**
         * Rimuove la schedulazione
         */
        public function remove_scheduled_withdrawals()
        {
            $timestamp = wp_next_scheduled('dokan_auto_withdraw_event');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'dokan_auto_withdraw_event');
            }
        }

        /**
         * Calcola il prossimo timestamp per la schedulazione
         */
        private function get_next_schedule_timestamp()
        {
            $frequency = get_option($this->option_prefix . 'frequency', 'monthly');
            $day = (int)get_option($this->option_prefix . 'day', '1');
            $current_time = current_time('timestamp');

            switch ($frequency) {
                case 'daily':
                    $timestamp = strtotime('tomorrow', $current_time);
                    break;

                case 'weekly':
                    $timestamp = strtotime('next monday', $current_time);
                    $timestamp += (($day - 1) * DAY_IN_SECONDS);
                    break;

                case 'monthly':
                default:
                    $next_month = strtotime('first day of next month', $current_time);
                    $timestamp = strtotime('+' . ($day - 1) . ' days', $next_month);
                    break;
            }

            error_log('Next schedule timestamp calculated: ' . date('Y-m-d H:i:s', $timestamp));

            return $timestamp;
        }

        /**
         * Processa i prelievi automatici
         */
        public function process_automatic_withdrawals()
        {
            try {
                $this->add_log('Inizio processo di prelievo automatico', 'info');

                if (get_option($this->option_prefix . 'enabled') !== 'yes') {
                    $this->add_log('Prelievi automatici disabilitati, processo terminato', 'warning');
                    return;
                }

                $min_amount = floatval(get_option($this->option_prefix . 'min_amount', '50'));
                $this->add_log("Importo minimo impostato: €{$min_amount}", 'info');

                // Get vendors with proper data structure
                $vendor_data = dokan_get_sellers(['number' => -1]);
                $vendors = isset($vendor_data['users']) ? $vendor_data['users'] : [];

                if (empty($vendors)) {
                    $this->add_log("Nessun venditore trovato", 'warning');
                    return;
                }

                $this->add_log("Trovati " . count($vendors) . " venditori da processare", 'info');

                $processed = 0;
                $successful = 0;
                $failed = 0;
                $skipped = 0;

                foreach ($vendors as $vendor) {
                    try {
                        $this->add_log("Inizio elaborazione venditore " . ($processed + 1), 'info');

                        $user_id = isset($vendor->ID) ? $vendor->ID : 0;
                        if (!$user_id) {
                            $this->add_log("ID venditore non valido", 'error');
                            $failed++;
                            continue;
                        }

                        $vendor_instance = dokan()->vendor->get($user_id);
                        if (!$vendor_instance) {
                            $this->add_log("Impossibile recuperare istanza venditore per ID: {$user_id}", 'error');
                            $failed++;
                            continue;
                        }

                        $store_name = $vendor_instance->get_shop_name();
                        $this->add_log("Recupero informazioni per venditore: {$store_name} (ID: {$user_id})", 'info');

                        $balance = dokan_get_seller_balance($user_id, false);
                        $this->add_log("Saldo venditore {$store_name}: €{$balance}", 'info');

                        if ($balance < $min_amount) {
                            $this->add_log("Venditore {$store_name} saltato: saldo €{$balance} inferiore al minimo €{$min_amount}", 'notice');
                            $skipped++;
                            continue;
                        }

                        $method = dokan_withdraw_get_default_method($user_id);
                        if (!$method) {
                            $this->add_log("Venditore {$store_name} saltato: nessun metodo di prelievo predefinito", 'warning');
                            $skipped++;
                            continue;
                        }

                        $this->add_log("Creazione richiesta di prelievo per {$store_name}", 'info');

                        $withdraw = new \WeDevs\Dokan\Withdraw\Withdraw();
                        $withdraw
                            ->set_user_id($user_id)
                            ->set_amount($balance)
                            ->set_date(dokan_current_datetime()->format('Y-m-d H:i:s'))
                            ->set_status(dokan()->withdraw->get_status_code('pending'))
                            ->set_method($method)
                            ->set_ip(dokan_get_client_ip())
                            ->set_note(__('Prelievo automatico programmato', 'dokan-mod'));

                        $this->add_log("Salvataggio richiesta di prelievo", 'info');
                        $result = $withdraw->save();

                        if (is_wp_error($result)) {
                            $this->add_log("Errore nel prelievo per {$store_name}: " . $result->get_error_message(), 'error');
                            $failed++;
                        } else {
                            $this->add_log("Prelievo creato con successo per {$store_name} - Importo: €{$balance}", 'success');
                            do_action('dokan_after_withdraw_request', $user_id, $balance, $method);

                            try {
                                if (class_exists(__NAMESPACE__ . '\EmailManagerClass')) {
                                    $email_manager = new EmailManagerClass();
                                    $email_manager->send_invoice_reminder_email($user_id, $balance);
                                    $this->add_log("Email di sollecito fatturazione inviata a {$store_name}", 'success');
                                } else {
                                    $this->add_log("Impossibile inviare email di sollecito fatturazione: classe EmailManagerClass non trovata", 'warning');
                                }
                            } catch (\Exception $e) {
                                $this->add_log("Errore nell'invio email di sollecito fatturazione a {$store_name}: " . $e->getMessage(), 'error');
                            }
                            $successful++;
                        }

                    } catch (\Exception $e) {
                        $this->add_log("Errore elaborazione venditore: " . $e->getMessage(), 'error');
                        $failed++;
                    }

                    $processed++;
                    $this->add_log("Completata elaborazione venditore " . $processed, 'info');
                }

                $this->add_log("Processo completato - Processati: {$processed}, Successo: {$successful}, Falliti: {$failed}, Saltati: {$skipped}", 'info');

            } catch (\Exception $e) {
                $this->add_log("Errore fatale: " . $e->getMessage(), 'error');
            }
        }

        /**
         * Renderizza i log nella pagina delle impostazioni
         */
        private function render_logs()
        {
            $logs = get_option($this->log_option_name, []);
            if (empty($logs)) {
                echo '<p>' . __('Nessun log disponibile.', 'dokan-mod') . '</p>';
                return;
            }

            echo '<div class="log-container" style="max-height: 400px; overflow-y: auto; margin-top: 20px;">';
            echo '<table class="widefat">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Data/Ora', 'dokan-mod') . '</th>';
            echo '<th>' . __('Tipo', 'dokan-mod') . '</th>';
            echo '<th>' . __('Messaggio', 'dokan-mod') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($logs as $log) {
                $class = '';
                switch ($log['type']) {
                    case 'error':
                        $class = 'color: #dc3545;';
                        break;
                    case 'warning':
                        $class = 'color: #ffc107;';
                        break;
                    case 'success':
                        $class = 'color: #28a745;';
                        break;
                    case 'notice':
                        $class = 'color: #17a2b8;';
                        break;
                }

                echo '<tr style="' . $class . '">';
                echo '<td>' . date_i18n('Y-m-d H:i:s', strtotime($log['timestamp'])) . '</td>';
                echo '<td>' . ucfirst($log['type']) . '</td>';
                echo '<td>' . esc_html($log['message']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }


        /**
         * Renderizza la pagina delle impostazioni
         */
        public function render_settings_page()
        {
            $enabled = get_option($this->option_prefix . 'enabled', 'no');
            $frequency = get_option($this->option_prefix . 'frequency', 'monthly');
            $day = get_option($this->option_prefix . 'day', '1');
            $min_amount = get_option($this->option_prefix . 'min_amount', '50');

            ?>
            <div class="wrap">
                <h1><?php _e('Impostazioni Prelievi Automatici', 'dokan-mod'); ?></h1>
                <form method="post" action="options.php" id="dokan-auto-withdraw-form">
                    <?php settings_fields($this->option_prefix . 'group'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Abilita Prelievi Automatici', 'dokan-mod'); ?></th>
                            <td>
                                <input type="checkbox" name="<?php echo $this->option_prefix; ?>enabled"
                                       value="yes" <?php checked($enabled, 'yes'); ?>>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Frequenza', 'dokan-mod'); ?></th>
                            <td>
                                <select name="<?php echo $this->option_prefix; ?>frequency" id="withdraw-frequency">
                                    <option value="daily" <?php selected($frequency, 'daily'); ?>><?php _e('Giornaliero', 'dokan-mod'); ?></option>
                                    <option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php _e('Settimanale', 'dokan-mod'); ?></option>
                                    <option value="monthly" <?php selected($frequency, 'monthly'); ?>><?php _e('Mensile', 'dokan-mod'); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr class="day-select">
                            <th scope="row"><?php _e('Giorno', 'dokan-mod'); ?></th>
                            <td>
                                <select name="<?php echo $this->option_prefix; ?>day" id="withdraw-day">
                                    <?php
                                    // Giorni del mese
                                    for ($i = 1; $i <= 31; $i++) {
                                        printf(
                                            '<option value="%d" %s>%d</option>',
                                            $i,
                                            selected($day, $i, false),
                                            $i
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description" id="day-description"></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Importo Minimo (€)', 'dokan-mod'); ?></th>
                            <td>
                                <input type="number" name="<?php echo $this->option_prefix; ?>min_amount"
                                       value="<?php echo esc_attr($min_amount); ?>" min="0" step="0.01">
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(); ?>
                </form>

                <hr>
                <h2><?php _e('Stato Cron', 'dokan-mod'); ?></h2>
                <?php
                $next_scheduled = wp_next_scheduled('dokan_auto_withdraw_event');
                if ($next_scheduled) {
                    echo '<p>' . sprintf(
                            __('Prossimo prelievo automatico programmato per: %s', 'dokan-mod'),
                            date_i18n('Y-m-d H:i:s', $next_scheduled)
                        ) . '</p>';
                } else {
                    echo '<p>' . __('Nessun prelievo automatico programmato.', 'dokan-mod') . '</p>';
                }


                ?>
                <hr>
                <h2><?php _e('Log Prelievi Automatici', 'dokan-mod'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('dokan_auto_prelievo_clear_logs', 'dokan_auto_prelievo_nonce'); ?>
                    <input type="submit" name="clear_logs" class="button button-secondary"
                           value="<?php _e('Pulisci Log', 'dokan-mod'); ?>"
                           onclick="return confirm('<?php _e('Sei sicuro di voler eliminare tutti i log?', 'dokan-mod'); ?>');">
                </form>
                <?php
                $this->render_logs();

                ?>

            </div>

            <script>
                jQuery(document).ready(function ($) {
                    function updateDayDescription() {
                        var frequency = $('#withdraw-frequency').val();
                        var description = '';

                        switch (frequency) {
                            case 'daily':
                                $('.day-select').hide();
                                break;
                            case 'weekly':
                                $('.day-select').show();
                                description = '1 = Lunedì, 7 = Domenica';
                                break;
                            case 'monthly':
                                $('.day-select').show();
                                description = 'Giorno del mese';
                                break;
                        }

                        $('#day-description').text(description);
                    }

                    $('#withdraw-frequency').on('change', updateDayDescription);
                    updateDayDescription();
                });
            </script>
            <?php
        }

        public function handle_clear_logs()
        {
            if (
                isset($_POST['clear_logs']) &&
                isset($_POST['dokan_auto_prelievo_nonce']) &&
                wp_verify_nonce($_POST['dokan_auto_prelievo_nonce'], 'dokan_auto_prelievo_clear_logs')
            ) {
                $this->clear_logs();
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p>' . __('Log puliti con successo.', 'dokan-mod') . '</p>';
                    echo '</div>';
                });
            }
        }

    }
}