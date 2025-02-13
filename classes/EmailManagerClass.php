<?php

namespace Dokan_Mods;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\EmailManagerClass')) {

    class EmailManagerClass
    {

        public function __construct()
        {
            // Disabilita email di default per ruoli specifici
            add_filter('wp_new_user_notification_email', array($this, 'disable_default_welcome_email'), 10, 3);

            // Hook per la registrazione di nuovi utenti
            add_action('user_register', array($this, 'send_custom_welcome_email'), 10, 1);

            // Hook per l'approvazione del vendor
            add_action('dokan_vendor_approved', array($this, 'send_vendor_approval_email'), 10, 1);

            // Hook per il rifiuto del vendor
            add_action('dokan_vendor_rejected', array($this, 'send_vendor_rejection_email'), 10, 1);
        }

        /**
         * Disabilita l'email di benvenuto di default per ruoli specifici
         */
        public function disable_default_welcome_email($email, $user, $blogname)
        {
            if (in_array('seller', $user->roles)) {
                return false;
            }
            return $email;
        }

        /**
         * Invia email di benvenuto personalizzata
         */
        public function send_custom_welcome_email($user_id)
        {
            $user = get_user_by('id', $user_id);

            if (!in_array('seller', $user->roles)) {
                return;
            }

            $to = $user->user_email;
            $subject = 'Benvenuto su Necrologiweb';
            $headers = array('Content-Type: text/html; charset=UTF-8');

            ob_start();
            ?>
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <p>Gentile <?php echo $user->display_name; ?>,</p>

                <p>La tua agenzia è attualmente in attesa di revisione. Un amministratore esaminerà la tua richiesta
                    e ti contatterà nel più breve tempo possibile per fornirti aggiornamenti e completare l'attivazione.</p>

                <p>Ti ringraziamo per la pazienza e la collaborazione. Per qualsiasi necessità, il nostro team è a
                    disposizione.</p>

                <p>Cordiali saluti,<br>
                    Il Team di Necrologiweb</p>
            </div>
            </body>
            </html>
            <?php
            $message = ob_get_clean();

            wp_mail($to, $subject, $message, $headers);
        }

        /**
         * Invia email di approvazione vendor
         */
        public function send_vendor_approval_email($user_id)
        {
            $user = get_user_by('id', $user_id);
            $to = $user->user_email;
            $subject = 'La tua agenzia è stata approvata';
            $headers = array('Content-Type: text/html; charset=UTF-8');

            ob_start();
            ?>
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <p>Gentile <?php echo $user->display_name; ?>,</p>

                <p>Siamo lieti di informarti che la tua agenzia è stata approvata.
                    Puoi ora accedere al tuo pannello di controllo e iniziare a utilizzare i nostri servizi.</p>

                <p>Per qualsiasi necessità, il nostro team è a disposizione.</p>

                <p>Cordiali saluti,<br>
                    Il Team di Necrologiweb</p>
            </div>
            </body>
            </html>
            <?php
            $message = ob_get_clean();

            wp_mail($to, $subject, $message, $headers);
        }

        /**
         * Invia email di rifiuto vendor
         */
        public function send_vendor_rejection_email($user_id)
        {
            $user = get_user_by('id', $user_id);
            $to = $user->user_email;
            $subject = 'Aggiornamento sulla richiesta della tua agenzia';
            $headers = array('Content-Type: text/html; charset=UTF-8');

            ob_start();
            ?>
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <p>Gentile <?php echo $user->display_name; ?>,</p>

                <p>Ci dispiace informarti che la richiesta per la tua agenzia non è stata approvata.</p>

                <p>Per maggiori informazioni o chiarimenti, non esitare a contattarci.</p>

                <p>Cordiali saluti,<br>
                    Il Team di Necrologiweb</p>
            </div>
            </body>
            </html>
            <?php
            $message = ob_get_clean();

            wp_mail($to, $subject, $message, $headers);
        }

        /**
         * Helper per testare l'invio delle email
         */
        public function test_email($user_id, $type = 'welcome')
        {
            switch ($type) {
                case 'welcome':
                    $this->send_custom_welcome_email($user_id);
                    break;
                case 'approval':
                    $this->send_vendor_approval_email($user_id);
                    break;
                case 'rejection':
                    $this->send_vendor_rejection_email($user_id);
                    break;
            }
        }
    }
}