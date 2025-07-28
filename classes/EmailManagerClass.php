<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\EmailManagerClass')) {

    class EmailManagerClass
    {
        private $option_prefix = 'dokan_email_template_';
        private $email_types = [];

        public function __construct()
        {
            // Initialize email types with their details
            $this->email_types = [
                'welcome' => [
                    'name' => __('Email di Benvenuto', 'dokan-mod'),
                    'description' => __('Inviata quando un nuovo venditore si registra.', 'dokan-mod'),
                    'placeholders' => [
                        '{name}' => __('Nome del venditore', 'dokan-mod'),
                        '{email}' => __('Email del venditore', 'dokan-mod'),
                        '{site_name}' => __('Nome del sito', 'dokan-mod')
                    ]
                ],
                'approval' => [
                    'name' => __('Email di Approvazione', 'dokan-mod'),
                    'description' => __('Inviata quando un venditore viene approvato.', 'dokan-mod'),
                    'placeholders' => [
                        '{name}' => __('Nome del venditore', 'dokan-mod'),
                        '{email}' => __('Email del venditore', 'dokan-mod'),
                        '{site_name}' => __('Nome del sito', 'dokan-mod'),
                        '{dashboard_url}' => __('URL del pannello di controllo', 'dokan-mod')
                    ]
                ],
                'rejection' => [
                    'name' => __('Email di Rifiuto', 'dokan-mod'),
                    'description' => __('Inviata quando un venditore viene rifiutato.', 'dokan-mod'),
                    'placeholders' => [
                        '{name}' => __('Nome del venditore', 'dokan-mod'),
                        '{email}' => __('Email del venditore', 'dokan-mod'),
                        '{site_name}' => __('Nome del sito', 'dokan-mod'),
                    ]
                ],
                'invoice' => [
                    'name' => __('Promemoria Fattura', 'dokan-mod'),
                    'description' => __('Inviata per richiedere l\'emissione della fattura.', 'dokan-mod'),
                    'placeholders' => [
                        '{name}' => __('Nome del venditore', 'dokan-mod'),
                        '{email}' => __('Email del venditore', 'dokan-mod'),
                        '{amount}' => __('Importo da fatturare', 'dokan-mod'),
                        '{site_name}' => __('Nome del sito', 'dokan-mod'),
                    ]
                ]
            ];

            // Register settings
            add_action('admin_init', [$this, 'register_settings']);

            // Disabilita email di default per ruoli specifici
            add_filter('wp_new_user_notification_email', [$this, 'disable_default_welcome_email'], 10, 3);

            // Hook per la registrazione di nuovi utenti
            add_action('user_register', [$this, 'send_custom_welcome_email'], 10, 1);

            // Hook per l'approvazione del vendor
            add_action('dokan_vendor_approved', [$this, 'send_vendor_approval_email'], 10, 1);

            // Hook per il rifiuto del vendor
            add_action('dokan_vendor_rejected', [$this, 'send_vendor_rejection_email'], 10, 1);

            // Aggiungi il menu per i template delle email
            add_action('admin_menu', [$this, 'add_email_template_menu']);

            // Hook per gestire il form di test
            add_action('admin_init', [$this, 'handle_email_test_form']);

            // Hook per gestire il salvataggio dei template
            add_action('admin_init', [$this, 'handle_email_template_save']);

            // Aggiungi script e stili
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }

        /**
         * Registra le impostazioni per i template email
         */
        public function register_settings()
        {
            foreach ($this->email_types as $type => $data) {
                register_setting($this->option_prefix . 'group', $this->option_prefix . $type . '_subject');
                register_setting($this->option_prefix . 'group', $this->option_prefix . $type . '_content');
            }
        }

        /**
         * Carica script e stili necessari
         */
        public function enqueue_admin_scripts($hook)
        {
            if ($hook != 'dokan-mod_page_dokan-email-templates') {
                return;
            }

            // Aggiungi l'editor WYSIWYG di WordPress
            wp_enqueue_editor();

            // Aggiungi stili personalizzati
            wp_enqueue_style('dokan-email-templates', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . '/assets/css/email-templates.css', [], '1.0.0');

            // Aggiungi gli script personalizzati
            wp_enqueue_script('dokan-email-templates', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . '/assets/js/admin/email-templates.js', ['jquery'], '1.0.0', true);
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
         * Ottiene il contenuto dell'email processando i placeholder
         */
        private function get_email_content($type, $user, $extra_data = [])
        {
            $default_templates = $this->get_default_templates();

            // Get custom email subject and content from options
            $subject = get_option($this->option_prefix . $type . '_subject', $default_templates[$type]['subject']);
            $content = get_option($this->option_prefix . $type . '_content', $default_templates[$type]['content']);

            // Replace placeholders
            $placeholders = [
                '{name}' => $user->display_name,
                '{email}' => $user->user_email,
                '{site_name}' => get_bloginfo('name'),
            ];

            // Add extra placeholders based on email type
            if ($type === 'approval') {
                $placeholders['{dashboard_url}'] = dokan_get_navigation_url();
            }

            if ($type === 'invoice' && isset($extra_data['amount'])) {
                $placeholders['{amount}'] = number_format($extra_data['amount'], 2, ',', '.');
            }

            // Replace all placeholders in subject
            foreach ($placeholders as $placeholder => $value) {
                $subject = str_replace($placeholder, $value, $subject);
            }

            // Replace all placeholders in content
            foreach ($placeholders as $placeholder => $value) {
                $content = str_replace($placeholder, $value, $content);
            }

            return [
                'subject' => $subject,
                'content' => $content
            ];
        }

        /**
         * Ottiene i template di default per le email
         */
        private function get_default_templates()
        {
            return [
                'welcome' => [
                    'subject' => 'Benvenuto su ' . get_bloginfo('name'),
                    'content' => '<p>Gentile {name},</p>
                    <p>La tua agenzia è attualmente in attesa di revisione. Un amministratore esaminerà la tua richiesta
                    e ti contatterà nel più breve tempo possibile per fornirti aggiornamenti e completare l\'attivazione.</p>
                    <p>Ti ringraziamo per la pazienza e la collaborazione. Per qualsiasi necessità, il nostro team è a
                    disposizione.</p>
                    <p>Cordiali saluti,<br>
                    Il Team di ' . get_bloginfo('name') . '</p>'
                ],
                'approval' => [
                    'subject' => 'La tua agenzia è stata approvata',
                    'content' => '<p>Gentile {name},</p>
                    <p>Siamo lieti di informarti che la tua agenzia è stata approvata.
                    Puoi ora accedere al tuo pannello di controllo e iniziare a utilizzare i nostri servizi.</p>
                    <p>Per qualsiasi necessità, il nostro team è a disposizione.</p>
                    <p>Cordiali saluti,<br>
                    Il Team di ' . get_bloginfo('name') . '</p>'
                ],
                'rejection' => [
                    'subject' => 'Aggiornamento sulla richiesta della tua agenzia',
                    'content' => '<p>Gentile {name},</p>
                    <p>Ci dispiace informarti che la richiesta per la tua agenzia non è stata approvata.</p>
                    <p>Per maggiori informazioni o chiarimenti, non esitare a contattarci.</p>
                    <p>Cordiali saluti,<br>
                    Il Team di ' . get_bloginfo('name') . '</p>'
                ],
                'invoice' => [
                    'subject' => 'Richiesta emissione fattura - ' . get_bloginfo('name'),
                    'content' => '<p>Gentile {name},</p>
                    <p>la invitiamo a emettere fattura per l\'importo maturato sulle vendite effettuate tramite il nostro
                    sito nel mese appena concluso, pari a <strong>&euro; {amount}</strong>. Le
                    ricordiamo che la fattura dovrà essere emessa entro 5 giorni dalla ricezione di questa
                    comunicazione.</p>
                    <p>Precisiamo, inoltre, che l\'importo indicato è già comprensivo di IVA.</p>
                    <p>Grazie per la collaborazione.</p>
                    <p>Cordiali saluti,<br>
                    Il Team di ' . get_bloginfo('name') . '</p>'
                ]
            ];
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
            $email_content = $this->get_email_content('welcome', $user);
            $subject = $email_content['subject'];
            $message = $this->get_email_template($email_content['content']);
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            wp_mail($to, $subject, $message, $headers);
        }

        /**
         * Invia email di approvazione vendor
         */
        public function send_vendor_approval_email($user_id)
        {
            $user = get_user_by('id', $user_id);
            $to = $user->user_email;
            $email_content = $this->get_email_content('approval', $user);
            $subject = $email_content['subject'];
            $message = $this->get_email_template($email_content['content']);
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            wp_mail($to, $subject, $message, $headers);
        }

        /**
         * Invia email di rifiuto vendor
         */
        public function send_vendor_rejection_email($user_id)
        {
            $user = get_user_by('id', $user_id);
            $to = $user->user_email;
            $email_content = $this->get_email_content('rejection', $user);
            $subject = $email_content['subject'];
            $message = $this->get_email_template($email_content['content']);
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            wp_mail($to, $subject, $message, $headers);
        }

        /**
         * Invia promemoria fattura
         */
        public function send_invoice_reminder_email($user_id, $amount)
        {
            $user = get_user_by('id', $user_id);

            if (!$user) {
                return;
            }

            $to = $user->user_email;
            $email_content = $this->get_email_content('invoice', $user, ['amount' => $amount]);
            $subject = $email_content['subject'];
            $message = $this->get_email_template($email_content['content']);
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            wp_mail($to, $subject, $message, $headers);
        }

        /**
         * Ottiene il template HTML completo per l'email
         */
        private function get_email_template($content)
        {
            ob_start();
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
            </head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="<?php echo esc_url(get_site_icon_url(96, admin_url('images/w-logo-blue.png'))); ?>"
                         alt="<?php echo esc_attr(get_bloginfo('name')); ?>" style="max-height: 50px;">
                </div>
                <div style="padding: 20px;">
                    <?php echo wp_kses_post($content); ?>
                </div>
                <div style="text-align: center; padding-top: 20px; margin-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999;">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo esc_html(get_bloginfo('name')); ?>. Tutti i diritti
                        riservati.</p>
                </div>
            </div>
            </body>
            </html>
            <?php
            return ob_get_clean();
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
                case 'invoice':
                    $this->send_invoice_reminder_email($user_id, 100.00); // Test con importo di esempio
                    break;
            }
        }

        /**
         * Aggiunge il menu per i template delle email
         */
        public function add_email_template_menu()
        {
            add_submenu_page(
                'dokan-mod',
                __('Template Mail', 'dokan-mod'),
                __('Template Mail', 'dokan-mod'),
                'manage_options',
                'dokan-email-templates',
                [$this, 'render_email_template_page']
            );
        }

        /**
         * Gestisce il form di test delle email
         */
        public function handle_email_test_form()
        {
            if (
                isset($_POST['send_test_email']) &&
                isset($_POST['email_test_nonce']) &&
                wp_verify_nonce($_POST['email_test_nonce'], 'dokan_email_test_nonce')
            ) {
                $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
                $user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
                $email_type = isset($_POST['email_type']) ? sanitize_text_field($_POST['email_type']) : 'welcome';

                // Se è stata fornita un'email, cerca l'utente per email
                if (empty($user_id) && !empty($user_email)) {
                    $user = get_user_by('email', $user_email);
                    if ($user) {
                        $user_id = $user->ID;
                    }
                }

                // Se abbiamo un user_id valido, invia l'email di test
                if (!empty($user_id)) {
                    $this->test_email($user_id, $email_type);
                    wp_redirect(add_query_arg(['page' => 'dokan-email-templates', 'tab' => 'test', 'email_sent' => '1']));
                    exit;
                } else {
                    wp_redirect(add_query_arg(['page' => 'dokan-email-templates', 'tab' => 'test', 'email_sent' => '0']));
                    exit;
                }
            }
        }

        /**
         * Gestisce il salvataggio dei template email
         */
        public function handle_email_template_save()
        {
            if (
                isset($_POST['save_email_template']) &&
                isset($_POST['email_template_nonce']) &&
                wp_verify_nonce($_POST['email_template_nonce'], 'dokan_email_template_nonce')
            ) {
                $email_type = isset($_POST['email_type']) ? sanitize_text_field($_POST['email_type']) : '';

                if (!empty($email_type) && array_key_exists($email_type, $this->email_types)) {
                    $subject = isset($_POST['email_subject']) ? sanitize_text_field($_POST['email_subject']) : '';
                    $content = isset($_POST['email_content']) ? wp_kses_post($_POST['email_content']) : '';

                    update_option($this->option_prefix . $email_type . '_subject', $subject);
                    update_option($this->option_prefix . $email_type . '_content', $content);

                    wp_redirect(add_query_arg(['page' => 'dokan-email-templates', 'tab' => $email_type, 'updated' => '1']));
                    exit;
                }
            }
        }

        /**
         * Renderizza la pagina dei template email
         */
        public function render_email_template_page()
        {
            $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'welcome';

            // Verifica che la tab sia valida
            if ($current_tab !== 'test' && !array_key_exists($current_tab, $this->email_types)) {
                $current_tab = 'welcome';
            }

            // Ottieni i template di default
            $default_templates = $this->get_default_templates();

            ?>
            <div class="wrap">
                <h1><?php _e('Template Email', 'dokan-mod'); ?></h1>

                <?php
                // Mostra eventuali notifiche
                if (isset($_GET['updated']) && $_GET['updated'] == '1') {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Template email aggiornato con successo!', 'dokan-mod') . '</p></div>';
                }

                if (isset($_GET['email_sent']) && $_GET['email_sent'] == '1') {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Email inviata con successo!', 'dokan-mod') . '</p></div>';
                } elseif (isset($_GET['email_sent']) && $_GET['email_sent'] == '0') {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Errore nell\'invio dell\'email. Utente non trovato.', 'dokan-mod') . '</p></div>';
                }
                ?>

                <h2 class="nav-tab-wrapper">
                    <?php
                    // Tabs per ogni tipo di email
                    foreach ($this->email_types as $type => $data) {
                        $class = ($current_tab === $type) ? 'nav-tab-active' : '';
                        echo '<a href="' . esc_url(admin_url('admin.php?page=dokan-email-templates&tab=' . $type)) . '" class="nav-tab ' . $class . '">' . esc_html($data['name']) . '</a>';
                    }

                    // Tab per il test email
                    $test_class = ($current_tab === 'test') ? 'nav-tab-active' : '';
                    echo '<a href="' . esc_url(admin_url('admin.php?page=dokan-email-templates&tab=test')) . '" class="nav-tab ' . $test_class . '">' . __('Test Email', 'dokan-mod') . '</a>';
                    ?>
                </h2>

                <div class="tab-content">
                    <?php
                    if ($current_tab === 'test') {
                        $this->render_test_email_tab();
                    } else {
                        $email_type = $current_tab;
                        $email_data = $this->email_types[$email_type];
                        $subject = get_option($this->option_prefix . $email_type . '_subject', $default_templates[$email_type]['subject']);
                        $content = get_option($this->option_prefix . $email_type . '_content', $default_templates[$email_type]['content']);

                        $this->render_email_template_form($email_type, $email_data, $subject, $content);
                    }
                    ?>
                </div>
            </div>

            <style>
                .email-template-form {
                    margin-top: 20px;
                }

                .email-template-form .form-table th {
                    width: 200px;
                }

                .placeholders-box {
                    background: #f9f9f9;
                    border: 1px solid #e5e5e5;
                    padding: 15px;
                    margin-bottom: 20px;
                }

                .placeholders-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                }

                .placeholder-item {
                    background: #fff;
                    border: 1px solid #ddd;
                    padding: 5px 10px;
                    border-radius: 3px;
                    cursor: pointer;
                    transition: all 0.2s;
                }

                .placeholder-item:hover {
                    background: #f0f0f0;
                    border-color: #aaa;
                }

                .email-preview {
                    margin-top: 20px;
                    border: 1px solid #ddd;
                    padding: 20px;
                    background: #fff;
                }

                #email_content_ifr {
                    min-height: 300px !important;
                }
            </style>

            <script>
                jQuery(document).ready(function ($) {
                    // Funzione per inserire un placeholder nel campo di testo
                    $('.placeholder-item').on('click', function () {
                        const placeholder = $(this).data('placeholder');
                        const editor = tinyMCE.get('email_content');

                        if (editor) {
                            editor.execCommand('mceInsertContent', false, placeholder);
                        }
                    });

                    // Funzione per visualizzare l'anteprima dell'email
                    $('.preview-button').on('click', function (e) {
                        e.preventDefault();

                        const content = tinyMCE.get('email_content').getContent();
                        $('.email-preview-content').html(content);
                        $('.email-preview').show();
                    });
                });
            </script>
            <?php
        }

        /**
         * Renderizza il form per la modifica del template email
         */
        private function render_email_template_form($email_type, $email_data, $subject, $content)
        {
            ?>
            <div class="email-template-form">
                <form method="post" action="">
                    <?php wp_nonce_field('dokan_email_template_nonce', 'email_template_nonce'); ?>
                    <input type="hidden" name="email_type" value="<?php echo esc_attr($email_type); ?>">

                    <div class="placeholders-box">
                        <h3><?php _e('Placeholders disponibili', 'dokan-mod'); ?></h3>
                        <p><?php _e('Clicca su un placeholder per inserirlo nel contenuto dell\'email:', 'dokan-mod'); ?></p>

                        <div class="placeholders-list">
                            <?php foreach ($email_data['placeholders'] as $placeholder => $description) : ?>
                                <div class="placeholder-item" data-placeholder="<?php echo esc_attr($placeholder); ?>">
                                    <strong><?php echo esc_html($placeholder); ?></strong>: <?php echo esc_html($description); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Descrizione', 'dokan-mod'); ?></th>
                            <td><p><?php echo esc_html($email_data['description']); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label
                                        for="email_subject"><?php _e('Oggetto Email', 'dokan-mod'); ?></label></th>
                            <td>
                                <input type="text" name="email_subject" id="email_subject" class="regular-text"
                                       value="<?php echo esc_attr($subject); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label
                                        for="email_content"><?php _e('Contenuto Email', 'dokan-mod'); ?></label></th>
                            <td>
                                <?php
                                wp_editor(
                                    $content,
                                    'email_content',
                                    [
                                        'media_buttons' => false,
                                        'textarea_rows' => 15,
                                        'teeny' => false,
                                        'tinymce' => [
                                            'plugins' => 'paste,lists,link,textcolor,wordpress',
                                            'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,link,unlink,forecolor',
                                            'toolbar2' => '',
                                        ],
                                    ]
                                );
                                ?>
                                <p>
                                    <button class="button preview-button"><?php _e('Anteprima', 'dokan-mod'); ?></button>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div class="email-preview" style="display: none;">
                        <h3><?php _e('Anteprima Email', 'dokan-mod'); ?></h3>
                        <div class="email-preview-content"></div>
                    </div>

                    <p class="submit">
                        <input type="submit" name="save_email_template" class="button button-primary"
                               value="<?php _e('Salva Template', 'dokan-mod'); ?>">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=dokan-email-templates&tab=' . $email_type . '&reset=1')); ?>"
                           class="button"
                           onclick="return confirm('<?php _e('Sei sicuro di voler ripristinare il template predefinito?', 'dokan-mod'); ?>');"><?php _e('Ripristina Predefinito', 'dokan-mod'); ?></a>
                    </p>
                </form>
            </div>
            <?php
        }

        /**
         * Renderizza la tab di test email
         */
        /**
         * Renderizza la tab di test email
         * Continuazione del file EmailManagerClass.php
         */
        private function render_test_email_tab()
        {
            // Ottieni tutti gli utenti con ruolo 'seller'
            $vendors = get_users([
                'role' => 'seller',
                'orderby' => 'display_name',
                'order' => 'ASC'
            ]);

            ?>
            <div class="email-test-form">
                <form method="post" action="">
                    <?php wp_nonce_field('dokan_email_test_nonce', 'email_test_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Seleziona Utente', 'dokan-mod'); ?></th>
                            <td>
                                <select name="user_id" id="user-select">
                                    <option value=""><?php _e('-- Seleziona un utente --', 'dokan-mod'); ?></option>
                                    <?php foreach ($vendors as $vendor) : ?>
                                        <option value="<?php echo esc_attr($vendor->ID); ?>"><?php echo esc_html($vendor->display_name . ' (' . $vendor->user_email . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Seleziona un utente dall\'elenco', 'dokan-mod'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('OPPURE Inserisci Email', 'dokan-mod'); ?></th>
                            <td>
                                <input type="email" name="user_email" id="user-email" class="regular-text"
                                       placeholder="email@esempio.com">
                                <p class="description"><?php _e('In alternativa, inserisci un indirizzo email per cercare l\'utente', 'dokan-mod'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Tipo di Email', 'dokan-mod'); ?></th>
                            <td>
                                <select name="email_type">
                                    <option value="welcome"><?php _e('Email di Benvenuto', 'dokan-mod'); ?></option>
                                    <option value="approval"><?php _e('Email di Approvazione', 'dokan-mod'); ?></option>
                                    <option value="rejection"><?php _e('Email di Rifiuto', 'dokan-mod'); ?></option>
                                    <option value="invoice"><?php _e('Promemoria Fattura', 'dokan-mod'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="send_test_email" class="button button-primary"
                               value="<?php _e('Invia Email di Test', 'dokan-mod'); ?>">
                    </p>
                </form>
            </div>

            <script>
                jQuery(document).ready(function ($) {
                    // Disabilita l'altro campo quando uno è in uso
                    $('#user-select').on('change', function () {
                        if ($(this).val()) {
                            $('#user-email').prop('disabled', true);
                        } else {
                            $('#user-email').prop('disabled', false);
                        }
                    });

                    $('#user-email').on('input', function () {
                        if ($(this).val()) {
                            $('#user-select').prop('disabled', true);
                        } else {
                            $('#user-select').prop('disabled', false);
                        }
                    });
                });
            </script>
            <?php
        }

    }
}