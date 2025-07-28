<?php

namespace Dokan_Mods;

class RegistrationForm
{

    public function __construct()
    {
        add_filter('dokan_get_template_part', array($this, 'override_dokan_template'), 10, 3); // Override the template part
        add_action('dokan_new_seller_created', array($this, 'custom_save_dokan_seller_files'), 10, 2);
        add_action('woocommerce_register_form_tag', array($this, 'add_enctype_to_registration_form'));
        add_filter('manage_users_columns', array($this, 'custom_add_vendor_file_column'));
        add_action('manage_users_custom_column', array($this,'custom_display_vendor_file_link'), 10, 3);
        add_filter('dokan_vendors_list_table_columns', array($this,'custom_add_dokan_vendor_file_column'));
        add_action('dokan_vendors_list_table_custom_column', array($this,'custom_display_dokan_vendor_file_link'), 10, 3);

        // New hooks for phone numbers and description
        add_filter('dokan_seller_registration_required_fields', array($this, 'add_custom_registration_fields'));
        add_action('dokan_seller_registration_field_after', array($this, 'add_custom_registration_form_fields'));
        add_action('dokan_new_seller_created', array($this, 'save_custom_registration_fields'), 10, 2);

        // Hooks for store settings/profile edit
        add_filter('dokan_settings_form_bottom', array($this, 'add_custom_store_settings_fields'), 10, 2);
        add_action('dokan_store_profile_saved', array($this, 'save_custom_store_settings_fields'), 10, 2);

        add_action('show_user_profile', array($this, 'add_custom_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_custom_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_user_profile_fields'));

    }

    public function override_dokan_template($template, $slug, $name)
    {
        // Check if the template part is 'global/seller-registration-form'
        if ('global/seller-registration-form' === $slug) {
            // Path to the template in your plugin
            $plugin_template = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'templates/global/seller-registration-form.php';

            // Check if the template exists in your plugin
            if (file_exists($plugin_template)) {
                // Return the template in your plugin
                return $plugin_template;
            }
        }

        // Return the default template
        return $template;
    }

    public function add_enctype_to_registration_form()
    {
        echo 'enctype="multipart/form-data"';
    }

    public function custom_save_dokan_seller_files($vendor_id, $dokan_settings)
    {
        if (isset($_FILES['visura_camerale']) && $_FILES['visura_camerale']['error'] == 0) {
            // Check if the file is a PDF by checking its MIME type
            if ($_FILES['visura_camerale']['type'] != 'application/pdf') {
                error_log('Errore: Il file caricato non è un PDF.');
                return;
            }

            // Check if the file size exceeds 5MB
            if ($_FILES['visura_camerale']['size'] > 5 * 1024 * 1024) {
                error_log('Errore: Il file caricato supera i 5MB.');
                return;
            }

            include_once(ABSPATH . 'wp-admin/includes/file.php');

            $upload = wp_handle_upload($_FILES['visura_camerale'], array('test_form' => false));

            if ($upload && !isset($upload['error'])) {
                update_user_meta($vendor_id, 'visura_camerale', $upload['url']);
            } else {
                // Gestisci l'errore di upload
                error_log('Errore durante l\'upload del file: ' . $upload['error']);
            }
        }
    }

    public function custom_add_vendor_file_column($columns)
    {
        $columns['vendor_file'] = 'Visura Camerale';
        return $columns;
    }



    public function custom_display_vendor_file_link($value, $column_name, $user_id)
    {
        if ('vendor_file' == $column_name) {
            $file_url = get_user_meta($user_id, 'visura_camerale', true);
            if (!empty($file_url)) {
                return '<a href="' . esc_url($file_url) . '" target="_blank">Visualizza File</a>';
            } else {
                return 'Nessun file caricato';
            }
        }

        return $value;
    }



    public function custom_add_dokan_vendor_file_column($columns)
    {
        $columns['company_file'] = __('Visura Camerale', 'dokan-mod');
        return $columns;
    }


    public function custom_display_dokan_vendor_file_link($column, $vendor_id)
    {
        if ($column == 'company_file') {
            $file_url = get_user_meta($vendor_id, 'visura_camerale', true);
            if (!empty($file_url)) {
                echo '<a href="' . esc_url($file_url) . '" target="_blank">Visualizza File</a>';
            } else {
                echo 'Nessun file caricato';
            }
        }
    }


    /**
     * Aggiungi campi personalizzati ai campi richiesti
     */
    public function add_custom_registration_fields($fields)
    {
        //$fields['phone_2'] = 'Secondo numero di telefono';
        //$fields['phone_3'] = 'Terzo numero di telefono';
        $fields['shop_description'] = 'Descrizione Agenzia';

        return $fields;
    }

    /**
     * Aggiungi campi personalizzati al form di registrazione
     */
    public function add_custom_registration_form_fields()
    {
        $fields = array(
            'phone_2' => array(
                'label' => 'Secondo numero di telefono',
                'type' => 'text',
                'required' => false
            ),
            'phone_3' => array(
                'label' => 'Terzo numero di telefono',
                'type' => 'text',
                'required' => false
            ),
            'shop_description' => array(
                'label' => 'Descrizione Agenzia',
                'type' => 'textarea',
                'required' => true
            )
        );

        foreach ($fields as $key => $field) : ?>
            <p class="form-row form-group">
                <label for="<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($field['label']); ?>
                    <?php echo $field['required'] ? ' <span class="required">*</span>' : ''; ?>
                </label>

                <?php if ($field['type'] === 'textarea') : ?>
                    <textarea
                        name="<?php echo esc_attr($key); ?>"
                        id="<?php echo esc_attr($key); ?>"
                        class="input-text form-control"
                        rows="4"
                        placeholder="Inserisci una descrizione dettagliata della tua agenzia"
                        <?php echo $field['required'] ? 'required' : ''; ?>
                    ></textarea>
                <?php else : ?>
                    <input
                        type="<?php echo esc_attr($field['type']); ?>"
                        class="input-text form-control"
                        name="<?php echo esc_attr($key); ?>"
                        id="<?php echo esc_attr($key); ?>"
                        placeholder="Inserisci il numero di telefono"
                        <?php echo $field['required'] ? 'required' : ''; ?>
                    />
                <?php endif; ?>
            </p>
        <?php endforeach;
    }

    /**
     * Salva i campi personalizzati durante la registrazione
     */
    public function save_custom_registration_fields($user_id, $data)
    {
        $store_info = array();
        // Salva i numeri di telefono aggiuntivi
        $store_info['phone_2'] = isset($_POST['phone_2']) ? sanitize_text_field($_POST['phone_2']) : '';
        $store_info['phone_3'] = isset($_POST['phone_3']) ? sanitize_text_field($_POST['phone_3']) : '';

        // Salva la descrizione
        $store_info['shop_description'] = isset($_POST['shop_description']) ?
            wp_kses_post($_POST['shop_description']) : '';

        update_user_meta($user_id, 'dokan_additional_info', $store_info);
    }

    /**
     * Aggiungi campi personalizzati alle impostazioni del negozio
     */
    public function add_custom_store_settings_fields($current_user, $store_info)
    {
        //get user meta key dokan_additional_info
        $store_info = get_user_meta($current_user, 'dokan_additional_info', true);

        ?>
        <div class="dokan-form-group">
            <label class="dokan-w3 dokan-control-label" for="phone_2">
                Secondo numero di telefono
            </label>
            <div class="dokan-w5">
                <input type="text" class="dokan-form-control" name="phone_2"
                       value="<?php echo isset($store_info['phone_2']) ? esc_attr($store_info['phone_2']) : ''; ?>"
                       placeholder="Inserisci il secondo numero di telefono">
            </div>
        </div>

        <div class="dokan-form-group">
            <label class="dokan-w3 dokan-control-label" for="phone_3">
                Terzo numero di telefono
            </label>
            <div class="dokan-w5">
                <input type="text" class="dokan-form-control" name="phone_3"
                       value="<?php echo isset($store_info['phone_3']) ? esc_attr($store_info['phone_3']) : ''; ?>"
                       placeholder="Inserisci il terzo numero di telefono">
            </div>
        </div>

        <div class="dokan-form-group">
            <label class="dokan-w3 dokan-control-label" for="shop_description">
                Descrizione Agenzia
                <span class="required"> *</span>
            </label>
            <div class="dokan-w8">
                <textarea class="dokan-form-control" name="shop_description" rows="5" required
                          placeholder="Inserisci una descrizione dettagliata della tua agenzia"><?php
                    echo isset($store_info['shop_description']) ? esc_textarea($store_info['shop_description']) : '';
                    ?></textarea>
            </div>
        </div>
        <?php
    }

    /**
     * Salva i campi personalizzati nelle impostazioni del negozio
     */
    public function save_custom_store_settings_fields($store_id, $dokan_settings)
    {
        $dokan_additional_info = get_user_meta($store_id, 'dokan_additional_info', true);

        if (isset($_POST['phone_2'])) {
            $dokan_settings['phone_2'] = sanitize_text_field($_POST['phone_2']);
        }
        if (isset($_POST['phone_3'])) {
            $dokan_settings['phone_3'] = sanitize_text_field($_POST['phone_3']);
        }
        if (isset($_POST['shop_description'])) {
            $dokan_settings['shop_description'] = wp_kses_post($_POST['shop_description']);
        }

        update_user_meta($store_id, 'dokan_additional_info', $dokan_settings);
    }

    public function add_custom_user_profile_fields($user)
    {
        // Verifica se l'utente è un venditore
        if (!dokan_is_user_seller($user->ID)) {
            return;
        }
        //get the user meta key dokan_additional_info
        $store_info = get_user_meta($user->ID, 'dokan_additional_info', true);
        ?>
        <h3>Informazioni Aggiuntive Agenzia</h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="dokan_store_info[phone_2]">Secondo numero di telefono</label>
                </th>
                <td>
                    <input type="text"
                           name="dokan_store_info[phone_2]"
                           id="dokan_store_info[phone_2]"
                           value="<?php echo isset($store_info['phone_2']) ? esc_attr($store_info['phone_2']) : ''; ?>"
                           class="regular-text"
                    />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="dokan_store_info[phone_3]">Terzo numero di telefono</label>
                </th>
                <td>
                    <input type="text"
                           name="dokan_store_info[phone_3]"
                           id="dokan_store_info[phone_3]"
                           value="<?php echo isset($store_info['phone_3']) ? esc_attr($store_info['phone_3']) : ''; ?>"
                           class="regular-text"
                    />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="dokan_store_info[shop_description]">Descrizione Agenzia</label>
                </th>
                <td>
                <textarea name="dokan_store_info[shop_description]"
                          id="dokan_store_info[shop_description]"
                          rows="5"
                          cols="50"
                          class="regular-text"
                          required><?php echo isset($store_info['shop_description']) ? esc_textarea($store_info['shop_description']) : ''; ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Salva i campi personalizzati dal profilo utente WordPress
     */
    public function save_custom_user_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (!dokan_is_user_seller($user_id)) {
            return false;
        }

        if (!isset($_POST['dokan_store_info'])) {
            return false;
        }

        // Ottieni le informazioni esistenti del negozio
        $store_info = array();

        // Aggiorna solo i campi personalizzati mantenendo gli altri dati
        $store_info['phone_2'] = isset($_POST['dokan_store_info']['phone_2']) ?
            sanitize_text_field($_POST['dokan_store_info']['phone_2']) : '';
        $store_info['phone_3'] = isset($_POST['dokan_store_info']['phone_3']) ?
            sanitize_text_field($_POST['dokan_store_info']['phone_3']) : '';
        $store_info['shop_description'] = isset($_POST['dokan_store_info']['shop_description']) ?
            wp_kses_post($_POST['dokan_store_info']['shop_description']) : '';

        // Aggiorna le informazioni del negozio
        update_user_meta($user_id, 'dokan_additional_info', $store_info);

        // Pulisci la cache
        wp_cache_delete('store_info_' . $user_id, 'dokan_additional_info');

        return true;
    }

}