<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\ProductTemplateClass')) {
    class ProductTemplateClass
    {
        public function __construct()
        {
            add_action('admin_menu', array($this, 'create_menu'));
            add_action('admin_init', array($this, 'register_settings'));
        }

        public function create_menu()
        {
            // Crea la voce principale "Dokan Mods" senza renderla cliccabile
            add_menu_page(
                'Dokan Mods',
                'Dokan Mods',
                'manage_options',
                'dokan-mod',
                array($this, 'settings_page'),  // Rimuove la funzione callback, quindi non c'è una pagina principale associata
                'dashicons-tagcloud',
                20
            );



            // Aggiunge un altro sottomenu, se necessario
            add_submenu_page(
                'dokan-mod',
                'Product Templates',
                'Product Templates',
                'manage_options',
                'product-templates',
                array($this, 'settings_page')
            );
        }

        public function register_settings()
        {
            register_setting('product-templates-group', 'product_template_mapping', array(
                'sanitize_callback' => array($this, 'sanitize_product_template_mapping')
            ));

            //handle the creation of dummy post type manifesto
            //$this->handle_create_manifesto();
        }

        public function sanitize_product_template_mapping($input)
        {
            $sanitized_input = array();
            foreach ($input as $product_id => $template_id) {
                $sanitized_input[intval($product_id)] = intval($template_id);
            }
            return $sanitized_input;
        }


        //handle the creation of dummy post type manifesto
        public function handle_create_manifesto()
        {
            if (isset($_POST['create_manifesto'])) {
                $this->create_manifesto_post_type();
            }
        }

        //generate a function to create dummy post type manifesto
        public function create_manifesto_post_type()
        {
            $num_posts = isset($_POST['num_posts']) ? intval($_POST['num_posts']) : 10;
            $post_type = 'manifesto';
            $post_status = 'publish';
            $post_author = 10;
            $post_title = 'Manifesto';
            $testo_manifesto = '<p>Testo del manifesto</p><p>Testo del manifesto</p><p>Testo del manifesto</p><p>Testo del manifesto</p>';
            for ($i = 0; $i < $num_posts; $i++) {
                $post_id = wp_insert_post(array(
                    'post_title' => $post_title . ' ' . $_POST['tipo_manifesto'] . ' ' . $i,
                    'post_type' => $post_type,
                    'post_status' => $post_status,
                    'post_author' => $post_author,
                ));

                if ($post_id) {
                    update_field('vendor_id', $post_author, $post_id);
                    update_field('annuncio_di_morte_relativo', 262, $post_id);
                    update_field('tipo_manifesto', $_POST['tipo_manifesto'], $post_id);

                    update_field('testo_manifesto', $testo_manifesto, $post_id);
                }
            }
        }





        public function settings_page()
        {
            ?>
            <div class="wrap">
                <h1>Product Templates</h1>
                <p>In questa pagina puoi associare i prodotti della categoria <strong>default-products</strong> ai
                    template di Elementor. Seleziona un template dalla lista per ogni prodotto e salva le impostazioni.
                    Questo ti permetterà di personalizzare l'aspetto dei tuoi prodotti utilizzando i template di
                    Elementor.</p>

                <!--create a button to create dummy post type manifesto
                <form method="post" action="">
                    <label>
                        Numero di Manifesti da Creare:
                        <input type="number" name="num_posts" value="10">
                    </label>
                    <label>
                        <select name="tipo_manifesto">
                            <option value="top">Top</option>
                            <option value="silver" selected>Silver</option>
                            <option value="online">Online</option>
                        </select>
                    </label>

                    <input type="submit" name="create_manifesto" value="Create Manifesto" class="button button-primary">
                </form>
                -->
                <form method="post" action="options.php">
                    <?php
                    settings_fields('product-templates-group');
                    do_settings_sections('product-templates-group');
                    $product_template_mapping = get_option('product_template_mapping', array());

                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_cat',
                                'field' => 'slug',
                                'terms' => 'default-products',
                            ),
                        ),
                    );

                    $products = get_posts($args);

                    $elementor_templates = get_posts(array(
                        'post_type' => 'elementor_library',
                        'posts_per_page' => -1
                    ));
                    ?>
                    <div id="poststuff">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php _e('Product Template Mapping', 'dokan-mod'); ?></span>
                            </h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <th scope="row"><?php echo esc_html($product->post_title); ?></th>
                                            <td>
                                                <select
                                                    name="product_template_mapping[<?php echo esc_attr($product->ID); ?>]">
                                                    <option
                                                        value=""><?php _e('Select Template', 'dokan-mod'); ?></option>
                                                    <?php foreach ($elementor_templates as $template): ?>
                                                        <option
                                                            value="<?php echo esc_attr($template->ID); ?>" <?php selected(isset($product_template_mapping[$product->ID]) ? $product_template_mapping[$product->ID] : '', $template->ID); ?>>
                                                            <?php echo esc_html($template->post_title); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php submit_button(); ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php
        }





    }
}