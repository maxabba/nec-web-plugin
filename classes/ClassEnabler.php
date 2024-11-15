<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\ClassEnabler')) {

    class ClassEnabler
    {

        private $classList = [];
        private $deactivableclasses = [];

        public function __construct()
        {
            $this->classList = [
                'ProductTemplateClass' => 'classes/admin/ProductTemplateClass.php',
                'MigrationClass' => 'classes/admin/MigrationClass.php',
                'DokanMappaturaLive' => 'classes/admin/DokanMappaturaLive.php',
                'CloudFlareGeo' => 'classes/CloudFlareGeoClass.php',
                'Dokan_Select_Products' => 'classes/dokan_select_products.php',
                'DashboardMenuClass' => 'classes/DashboardMenuClass.php',
                'Miscellaneous' => 'classes/Miscellaneous.php',
                'RegistrationForm' => 'classes/RegistrationForm.php',
                'AnnuncioMorteClass' => 'classes/AnnuncioMorteClass.php',
                'registerActivationClass' => 'classes/registerActivationClass.php',
                'TrigesimiClass' => 'classes/TrigesimiClass.php',
                'AnniversarioClass' => 'classes/AnniversarioClass.php',
                'PensieriniClass' => 'classes/PensieriniClass.php',
                'ManifestoClass' => 'classes/ManifestoClass.php',
                'RingraziamentoClass' => 'classes/RingraziamentoClass.php',
                'FioraiClass' => 'classes/FioraiClass.php',
                'NecrologiFrontendClass' => 'classes/NecrologiFrontendClass.php',
                'TrigesimiFrontendClass' => 'classes/TrigesimiFrontendClass.php',
                'AnniversarioFrontendClass' => 'classes/AnniversarioFrontendClass.php',
                'RingraziamentoFrontendClass' => 'classes/RingraziamentoFrontendClass.php',
                'VendorFrontendClass' => 'classes/VendorFrontendClass.php',
                'SearchFrontendClass' => 'classes/SearchFrontendClass.php',
                'PrintManifestoClass' => 'classes/PrintManifestoClass.php',
                'ElementorWidgetInit' => 'includes/ElementorWidgetInit.php'
            ];

            $this->deactivableclasses = [
                'MigrationClass' => 'Funzionalita di migrazione',
                'CloudFlareGeo' => 'Funzionalita di geolocalizzazione',
                'DokanMappaturaLive' => 'Funzionalita di mappatura xml',
            ];

            add_action('admin_menu', [$this, 'create_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_post_dokan_mods_save_settings', [$this, 'save_settings']); // Azione per gestire il salvataggio
            $this->loop_instanziate_class();
        }

        public function create_menu()
        {
            add_menu_page(
                'Dokan Mods',
                'Dokan Mods',
                'manage_options',
                'dokan-mod',
                [$this, 'settings_page'],
                'dashicons-tagcloud',
                20
            );
        }

        public function register_settings()
        {
            // Registra le opzioni per le classi disattivabili
            foreach ($this->deactivableclasses as $class_name => $description) {
                register_setting('dokan_mods_options', 'dokan_mods_deactivate_' . strtolower($class_name), 'sanitize_text_field');
            }
        }

        public function settings_page()
        {
            ?>
            <div class="wrap">
                <h1>Dokan Mods Settings</h1>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="dokan_mods_save_settings">
                    <?php wp_nonce_field('dokan_mods_save_settings_nonce', 'dokan_mods_nonce'); ?>

                    <table class="form-table">
                        <tbody>
                        <?php foreach ($this->deactivableclasses as $class_name => $description): ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($description); ?></th>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox"
                                               name="dokan_mods_deactivate_<?php echo esc_attr(strtolower($class_name)); ?>"
                                               value="1"
                                            <?php checked(get_option('dokan_mods_deactivate_' . strtolower($class_name)), true); ?>>
                                        <span class="slider round"></span>
                                    </label>
                                    <p class="description">Disabilita <?php echo esc_html($description); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php submit_button('Save Settings', 'primary', 'dokan_mods_save_settings'); ?>
                </form>
            </div>
            <style>
                .switch {
                    position: relative;
                    display: inline-block;
                    width: 60px;
                    height: 34px;
                }

                .switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }

                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                }

                .slider:before {
                    position: absolute;
                    content: "";
                    height: 26px;
                    width: 26px;
                    left: 4px;
                    bottom: 4px;
                    background-color: white;
                    transition: .4s;
                }

                input:checked + .slider {
                    background-color: #2196F3;
                }

                input:checked + .slider:before {
                    transform: translateX(26px);
                }

                .slider.round {
                    border-radius: 34px;
                }

                .slider.round:before {
                    border-radius: 50%;
                }
            </style>
            <?php
        }

        public function save_settings()
        {
            // Controllo delle autorizzazioni e verifica nonce
            if (!current_user_can('manage_options') || !isset($_POST['dokan_mods_nonce']) || !wp_verify_nonce($_POST['dokan_mods_nonce'], 'dokan_mods_save_settings_nonce')) {
                wp_die(__('Unauthorized request.', 'dokan-mods'));
            }

            // Salvataggio impostazioni disattivabili
            foreach ($this->deactivableclasses as $class_name => $description) {
                $option_name = 'dokan_mods_deactivate_' . strtolower($class_name);
                $value = isset($_POST[$option_name]) ? 1 : 0; // Assicura un valore booleano
                update_option($option_name, $value);
            }

            // Aggiunta del messaggio di salvataggio e redirect
            add_settings_error(
                'dokan_mods_messages',
                'dokan_mods_message',
                __('Settings Saved', 'dokan-mods'),
                'updated'
            );

            // Redirect per evitare repost dei dati
            $redirect_url = add_query_arg('settings-updated', 'true', wp_get_referer());
            wp_safe_redirect($redirect_url);
            exit;
        }

        private function loop_instanziate_class()
        {
            foreach ($this->classList as $class_name => $file_path) {
                if (!empty(get_option('dokan_mods_deactivate_' . strtolower($class_name)))) {
                    continue;
                }
                $this->dokan_mods_load_and_instantiate_class($class_name, $file_path);
            }
        }

        private function dokan_mods_load_and_instantiate_class($class_name, $file_path)
        {
            if (!class_exists(__NAMESPACE__ . '\\' . $class_name)) {
                require_once DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . $file_path;
                if (class_exists(__NAMESPACE__ . '\\' . $class_name)) {
                    $class_with_namespace = __NAMESPACE__ . '\\' . $class_name;
                    new $class_with_namespace();
                } else {
                    error_log("Class $class_name not found in file $file_path.");
                }
            } else {
                error_log("Class $class_name already exists.");
            }
        }
    }
}
