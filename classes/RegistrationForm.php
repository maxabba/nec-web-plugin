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
        $columns['company_file'] = __('Visura Camerale', 'dokan');
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

}