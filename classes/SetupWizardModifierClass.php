<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\SetupWizardModifierClass')) {

    class SetupWizardModifierClass
    {

        public function __construct()
        {
            // Modifica i passi del wizard
            add_filter('dokan_seller_wizard_steps', array($this, 'modify_wizard_steps'));

            // Aggiungi il nuovo step
            add_action('dokan_setup_wizard_after_store_setup_save', array($this, 'redirect_to_pending_message'));

            add_action('admin_post_dokan_logout_and_redirect', array($this, 'handle_logout_and_redirect'));
            add_action('admin_post_nopriv_dokan_logout_and_redirect', array($this, 'handle_logout_and_redirect'));
        }

        /**
         * Modifica i passi del wizard sostituendo next_steps con pending_review
         */
        public function modify_wizard_steps($steps)
        {
            // Rimuovi lo step next_steps
            if (isset($steps['next_steps'])) {
                unset($steps['next_steps']);
            }

            // Aggiungi il nuovo step
            $steps['pending_review'] = [
                'name' => __('In Revisione', 'dokan-lite'),
                'view' => array($this, 'dokan_setup_pending_review'),
                'handler' => '',
            ];

            return $steps;
        }

        /**
         * Gestisce il logout e il redirect
         */
        public function handle_logout_and_redirect()
        {
            // Verifica il nonce
            if (!wp_verify_nonce($_REQUEST['logout_nonce'], 'logout-redirect')) {
                wp_die('Operazione non autorizzata', 'Errore', array('response' => 403));
            }

            // Prendi l'URL di redirect
            $redirect_to = !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : home_url();

            // Esegui il logout
            wp_logout();

            // Pulisci la sessione
            wp_destroy_current_session();

            // Cancella i cookie
            wp_clear_auth_cookie();

            // Redirect alla home
            wp_safe_redirect($redirect_to);
            exit();
        }


        /**
         * Visualizza il messaggio di attesa revisione
         */
        public function dokan_setup_pending_review()
        {

            $logout_nonce = wp_create_nonce('logout-redirect');
            // Crea l'URL per il logout con redirect
            $logout_url = wp_nonce_url(add_query_arg(
                array(
                    'action' => 'dokan_logout_and_redirect',
                    'redirect_to' => urlencode(home_url()),
                ),
                admin_url('admin-post.php')
            ), 'logout-redirect', 'logout_nonce');

            ?>
            <div class="dokan-setup-content">
                <div class="dokan-setup-done" style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #333; font-size: 24px; margin-bottom: 20px;">
                        <?php esc_html_e('Registrazione Completata', 'dokan-lite'); ?>
                    </h1>
                </div>

                <div class="dokan-setup-done-content" style="text-align: center; max-width: 600px; margin: 0 auto;">
                    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                        La tua agenzia è attualmente in attesa di revisione. Un amministratore esaminerà la tua richiesta
                        e ti contatterà nel più breve tempo possibile per fornirti aggiornamenti e completare l'attivazione.
                    </p>

                    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 30px;">
                        Ti ringraziamo per la pazienza e la collaborazione. Per qualsiasi necessità, il nostro team è a
                        disposizione.
                    </p>

                    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 30px;">
                        Il Team di Necrologiweb
                    </p>

                    <p class="wc-setup-actions step">
                        <a class="button button-primary dokan-btn-theme"
                           href="<?php echo esc_url($logout_url); ?>"
                           style="font-size: 16px; padding: 10px 30px;">
                            <?php esc_html_e('Torna alla Home', 'dokan-lite'); ?>
                        </a>
                    </p>
                </div>
            </div>

            <style>
                .dokan-setup-content {
                    padding: 40px;
                    background-color: #fff;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.13);
                    margin: 0 auto;
                    border-radius: 4px;
                }

                .dokan-setup-done-content{
                    display: flex;
                    justify-content: center;
                    flex-direction: column;
                }
                .dokan-btn-theme {
                    background-color: #f2d600 !important;
                    border-color: #f2d600 !important;
                    color: #333 !important;
                    transition: all 0.3s ease;
                }

                .dokan-btn-theme:hover {
                    background-color: #ddc200 !important;
                    border-color: #ddc200 !important;
                }
            </style>
            <?php
        }

        /**
         * Reindirizza al messaggio di pending dopo il salvataggio dello store
         */
        public function redirect_to_pending_message()
        {
            wp_redirect(add_query_arg('step', 'pending_review'));
            exit;
        }
    }
}