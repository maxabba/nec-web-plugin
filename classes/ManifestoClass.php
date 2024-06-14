<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\ManifestoClass')) {
    class ManifestoClass
    {

        public function __construct()
        {
            add_action('init', array($this, 'register_shortcodes'));

            add_action('wp_ajax_get_vendor_data', array($this, 'get_vendor_data'));
            add_action('wp_ajax_nopriv_get_vendor_data', array($this, 'get_vendor_data'));

            add_action('admin_post_save_custom_text_editor', array($this, 'save_custom_text_editor'));
            add_action('admin_post_nopriv_save_custom_text_editor', array($this, 'save_custom_text_editor'));

            add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'));
            add_filter('woocommerce_get_item_data', array($this, 'display_manifesto_and_post_title_in_cart'), 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'display_manifesto_and_post_title_in_order_checkout'), 10, 4);

        }

        public function register_shortcodes()
        {
            add_shortcode('vendor_selector', array($this, 'shortcode_vendor_selector'));
            add_shortcode('custom_text_editor', array($this, 'create_custom_text_editor_shortcode'));
        }


        public function save_custom_text_editor()
        {
            if (!isset($_POST['product_id'])) {
                wp_send_json_error('Product ID missing');
            }

            if (!isset($_POST['custom_text'])) {
                wp_send_json_error('Custom text missing');
            }

            if (!isset($_POST['post_id'])) {
                wp_send_json_error('Post ID missing');
            }

            $product_id = intval($_POST['product_id']);
            $custom_text = $_POST['custom_text'];
            $post_id = intval($_POST['post_id']);

            if (!get_post($product_id)) {
                wp_send_json_error('Invalid Product ID');
            }

            if (!get_post($post_id)) {
                wp_send_json_error('Invalid Post ID');
            }

            // Check if WooCommerce is available
            if (!class_exists('WooCommerce')) {
                // Redirect back to the page with error message
                wp_redirect(get_permalink($post_id));
                exit;
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                wp_die('Product not found');
            }

            // Ensure WooCommerce cart is loaded
            if (!WC()->cart) {
                wc_load_cart();
            }

            //get the product category
            $product_category = get_the_terms($product_id, 'product_cat');
            //split by - and get the last element
            $product_category = explode('-', $product_category[0]->slug);
            $is_purchasable = $product->is_purchasable();
            if ($is_purchasable) {
                $cart_item_data = array(
                    'manifesto_html' => $custom_text,
                    'tipo_manifesto' => $product_category[1],
                    'manifesto_post_id' => $post_id,
                );

                WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
                wp_redirect(wc_get_cart_url());
                exit;
            } else {
                wp_die('Product not purchasable');
            }
        }


        public function display_manifesto_and_post_title_in_cart($item_data, $cart_item)
        {
            if (isset($cart_item['manifesto_html'])) {
                $sanitized_html = sanitize_text_field($cart_item['manifesto_html']);
                $item_data[] = array(
                    'key' => 'Manifesto',
                    'value' => sprintf(
                        '%s',
                        esc_html($sanitized_html)),
                );
            }

            if (isset($cart_item['manifesto_post_id'])) {
                $post_id = $cart_item['manifesto_post_id'];


                // Get the post title
                $post_title = get_the_title($post_id);
                $item_data[] = array(
                    'key' => 'Dedicato a',
                    'value' => sprintf(
                        '%s',
                        esc_html($post_title),
                    ),
                );
            }



            return $item_data;
        }


        public function display_manifesto_and_post_title_in_order_checkout($item, $cart_item_key, $values, $order)
        {
            if (isset($values['manifesto_html'])) {
                $item->add_meta_data('_manifesto_html', $values['manifesto_html']);
                $item->add_meta_data('Manifesto', $values['manifesto_html']);
            }

            if (isset($values['manifesto_post_id'])) {
                $post_id = $values['manifesto_post_id'];

                // Get the post title
                $post_title = get_the_title($post_id);
                $item->add_meta_data('_post_id', $post_id);
                $item->add_meta_data('Dedicato a', $post_title);
            }

            if (isset($values['tipo_manifesto'])) {
                $tipo_manifesto = $values['tipo_manifesto'];
                $item->add_meta_data('_tipo_manifesto', $tipo_manifesto);
            }
        }


        public function handle_payment_complete($order_id)
        {
            $order = wc_get_order($order_id);
            $items = $order->get_items();

            foreach ($items as $item) {
                if ($item->get_meta('_tipo_manifesto', true) && $item->get_meta('_manifesto_html', true) && $item->get_meta('_post_id', true)) {

                    $product_id = $item->get_product_id();
                    $product = wc_get_product($product_id);
                    $tipo_manifesto = $item->get_meta('_tipo_manifesto', true);
                    $manifesto_html = $item->get_meta('_manifesto_html', true);
                    $post_id = $item->get_meta('_post_id', true);
                    //get vendor id
                    $vendor_id = get_post_field('post_author', $product_id);
                    //get post_id citta and provincia
                    $citta = get_field('citta', $post_id);
                    $provincia = get_field('provincia', $post_id);

                    // create post type manifesto
                    $post = array(
                        'post_title' => 'Manifesto per ' . get_the_title($post_id),
                        'post_status' => 'draft',
                        'post_author' => $vendor_id,
                        'post_type' => 'manifesto',
                    );


                    $post_id = wp_insert_post($post);
                    if ($post_id) {
                        //set acf fields annuncio_di_morte_relativo with the post_id
                        update_field('annuncio_di_morte_relativo', $post_id, $product_id);
                        //vendor_id
                        update_field('vendor_id', get_post_field('post_author', $product_id), $post_id);
                        //testo_manifesto
                        update_field('testo_manifesto', $manifesto_html, $post_id);
                        //tipo_manifesto
                        update_field('tipo_manifesto', $tipo_manifesto, $post_id);
                        //provincia e citta
                        update_field('provincia', $citta, $post_id);
                        update_field('citta', $provincia, $post_id);
                        //add meta data to $post_id with the id of the order
                        add_post_meta($post_id, 'order_id', $order_id);
                        add_post_meta($post_id, 'product_id', $product_id);
                    }
                }

            }
        }


        function get_vendor_data()
        {
            if (!isset($_POST['product_id'])) {
                wp_send_json_error('Product ID missing');
            }

            $product_id = intval($_POST['product_id']);
            $user_id = get_post_field('post_author', $product_id);

            if (!$user_id) {
                wp_send_json_error('Invalid Product ID');
            }

            $manifesto_background = get_user_meta($user_id, 'manifesto_background', true);
            $manifesto_orientation = get_user_meta($user_id, 'manifesto_orientation', true);
            $margin_top = get_user_meta($user_id, 'manifesto_margin_top', true);
            $margin_right = get_user_meta($user_id, 'manifesto_margin_right', true);
            $margin_bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true);
            $margin_left = get_user_meta($user_id, 'manifesto_margin_left', true);
            $alignment = get_user_meta($user_id, 'manifesto_alignment', true);

            wp_send_json_success([
                'manifesto_background' => $manifesto_background,
                'manifesto_orientation' => $manifesto_orientation,
                'margin_top' => $margin_top,
                'margin_right' => $margin_right,
                'margin_bottom' => $margin_bottom,
                'margin_left' => $margin_left,
                'alignment' => $alignment,
            ]);
        }


        public function shortcode_vendor_selector($attr)
        {
            $product_id = isset($_GET['product_id']) && $_GET['product_id'] != 0 ? intval($_GET['product_id']) : '66';
            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
            $atts = shortcode_atts(
                array(
                    'product_id' => $product_id,
                ),
                $attr
            );

            // Ottieni il campo ACF 'categoria_finale'
            $categoria_finale = get_field('categoria_finale', $atts['product_id']);
            $categoria_finale = strtolower(str_replace(' ', '-', $categoria_finale));
            $citta = get_field('citta', $post_id);

            // Get all vendors with the city equal to $citta
            $args_vendors = array(
                'role' => 'seller',
                'meta_query' => array(
                    array(
                        'key' => 'dokan_profile_settings',
                        'value' => sprintf(':"%s";', $citta),
                        'compare' => 'LIKE',
                    ),
                ),
            );
            $vendors = get_users($args_vendors);

            // Get the IDs of the vendors
            $vendor_ids = array();
            foreach ($vendors as $vendor) {
                $vendor_ids[] = $vendor->ID;
            }

            // Get all products from these vendors in the specified category
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'author__in' => $vendor_ids, // Only products from these vendors
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => $categoria_finale,
                    ),
                ),
            );
            $products = new \WP_Query($args);
            ob_start();

            if ($products->have_posts()) {
                ?>
                <div class="vendor-selector">
                    <?php
                    while ($products->have_posts()) {
                        // Ottieni l'autore del prodotto
                        $products->the_post();
                        $product_id = get_the_ID();

                        // Ottieni le informazioni del negozio Dokan
                        $store_info = (new UtilsAMClass())->get_dokan_store_info_by_product($product_id);
                        $store_name = $store_info['store_name'];
                        $store_banner = $store_info['store_banner'];
                        ?>
                        <div class="vendor-flex">

                                <input type="radio" name="product_id" id="product_<?php echo $product_id; ?>"
                                       value="<?php echo $product_id; ?>">
                                <label for="product_<?php echo $product_id; ?>" class="vendor-card">
                                    <div class="card">
                                        <?php if ($store_banner) : ?>
                                            <img src="<?php echo $store_banner; ?>" alt="<?php echo $store_name; ?>"
                                                 class="card-img-top" width="250px" height="250px">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo $store_name; ?></h5>
                                        </div>
                                    </div>
                                </label>

                        </div>
                        <?php
                    }
                    ?>
                </div>
                <style>
                    .vendor-selector {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 20px;
                        justify-content: center;
                    }

                    .vendor-flex {
                        flex: 1 1 100%;
                        max-width: 100%;
                        overflow: hidden;
                        position: relative;
                        text-align: center;
                    }

                    .vendor-card {
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        border-radius: 5px;
                        overflow: hidden;
                        width: auto; /* Adatta la larghezza al contenuto */
                        margin: 0 auto; /* Centra la carta */
                    }

                    .vendor-flex input[type="radio"] {
                        position: absolute;
                        opacity: 0;
                        cursor: pointer;
                    }

                    .vendor-flex input[type="radio"]:checked + label .card {
                        border: 2px solid #007bff;
                    }

                    .card {
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        overflow: hidden;
                        text-align: center;
                    }

                    .card img {
                        display: block;
                        margin: 0 auto;
                        max-width: 100%;
                        height: auto;
                    }

                    .card-body {
                        padding: 10px;
                    }

                    .card-title {
                        font-size: 16px;
                        font-weight: bold;
                        margin: 0;
                    }

                    /* Media queries per il layout responsive */
                    @media (min-width: 576px) {
                        .vendor-flex {
                            flex: 1 1 48%;
                            max-width: 48%;
                        }
                    }

                    @media (min-width: 768px) {
                        .vendor-flex {
                            flex: 1 1 32%;
                            max-width: 32%;
                        }
                    }

                    @media (min-width: 992px) {
                        .vendor-flex {
                            flex: 1 1 24%;
                            max-width: 24%;
                        }
                    }
                </style>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        document.querySelectorAll('input[name="product_id"]').forEach(function (input) {
                            input.addEventListener('change', function () {
                                if (this.checked) {
                                    var productID = this.value;
                                    if (typeof setProductID === 'function') {
                                        setProductID(productID);
                                    }
                                }
                            });
                        });
                    });
                </script>
                <?php
                wp_reset_postdata();
            }
            return ob_get_clean();
        }


        function create_custom_text_editor_shortcode($atts)
        {
            ob_start();
            ?>
            <div class="text-editor-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      id="custom-text-editor-form" class="full-width-form">
                    <input type="hidden" name="action" value="save_custom_text_editor">
                    <input type="hidden" name="product_id" id="product_id" value="<?php echo $_GET['product_id']; ?>">
                    <input type="hidden" name="post_id" value="<?php echo $_GET['post_id'] ?? get_the_ID(); ?>">

                    <div style="margin:auto;" class="manifesti-container hide">
                        <div id="text-editor-background" class="text-editor-background" style="background-image: none;">
                            <div id="text-editor" contenteditable="true" class="custom-text-editor"></div>
                        </div>

                        <input type="submit" value="Salva" class="button">
                    </div>
                    <div class="loader" id="comments-loader"></div>

                </form>
            </div>
            <style>

                .loader {
                    border: 8px solid #f3f3f3; /* Light grey */
                    border-top: 8px solid #3498db; /* Blue */
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 2s linear infinite;
                    display: none; /* Nascosto inizialmente */
                    margin: 20px auto;
                }

                @keyframes spin {
                    0% {
                        transform: rotate(0deg);
                    }
                    100% {
                        transform: rotate(360deg);
                    }
                }
                .full-width-form input[type="submit"] {
                    display: block;
                    margin: 0 auto;
                }

                .full-width-form {
                    width: 100%;
                }
                .text-editor-container {
                    position: relative;
                    padding: 20px;
                    width: 100%;
                    max-width: 800px;
                    margin: auto;
                }

                .text-editor-background {
                    background-size: contain;
                    background-position: center;
                    height: 50vh; /* Usare vh per altezza responsiva */
                    width: 100%; /* Usare larghezza completa */
                    max-width: 100%;
                    position: relative;
                    margin: auto;
                }

                .custom-text-editor {
                    width: 100%;
                    height: 100%;
                    border: none;
                    background: transparent;
                    color: #000;
                    resize: none;
                    box-sizing: border-box;
                    outline: none;
                    overflow: visible;
                    font-size: calc(0.6vw + 0.6vh ); /* Dimensione del font reattiva e leggermente più piccola di 16px */
                    font-family: var(--e-global-typography-text-font-family), Sans-serif;
                    font-weight: var(--e-global-typography-text-font-weight);
                }

                .editor-toolbar {
                    position: absolute;
                    background: #fff;
                    border: 1px solid #ccc;
                    padding: 5px;
                    display: none;
                    z-index: 1000;
                    border-radius: 5px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                }

                .editor-toolbar button {
                    background: none;
                    border: none;
                    cursor: pointer;
                    font-size: 16px;
                    margin: 0 2px;
                    padding: 2px 5px;
                }

                .editor-toolbar button:hover {
                    background: #f0f0f0;
                }

                @media (max-width: 768px) {
                    .text-editor-container {
                        padding: 10px;
                    }

                    .editor-toolbar {
                        font-size: 14px;
                    }

                    .custom-text-editor {
                        font-size: calc(0.9vw + 0.9vh ); /* Dimensione del font reattiva per schermi piccoli */
                    }
                }
            </style>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var marginTopPx = 0;
                    var marginRightPx = 0;
                    var marginBottomPx = 0;
                    var marginLeftPx = 0;

                    function updateEditorBackground(data) {

                        const backgroundDiv = document.getElementById('text-editor-background');
                        const textEditor = document.getElementById('text-editor');

                        if (data.manifesto_background) {
                            const img = new Image();
                            img.src = data.manifesto_background;
                            img.onload = function () {
                                const aspectRatio = img.width / img.height;
                                backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                                if (aspectRatio > 1) {
                                    // Landscape
                                    backgroundDiv.style.width = '100%';
                                    backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
                                } else {
                                    // Portrait
                                    backgroundDiv.style.height = '400px';
                                    backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
                                }

                                // Calcola i margini in pixel basati sulla percentuale
                                marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                                marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                                marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                                marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                                // Applica i margini e l'allineamento
                                textEditor.style.paddingTop = `${marginTopPx}px`;
                                textEditor.style.paddingRight = `${marginRightPx}px`;
                                textEditor.style.paddingBottom = `${marginBottomPx}px`;
                                textEditor.style.paddingLeft = `${marginLeftPx}px`;
                                textEditor.style.textAlign = data.alignment ? data.alignment : 'left';

                            }

                        } else {
                            backgroundDiv.style.backgroundImage = 'none';
                        }


                    }

                    window.setProductID = function (productID) {
                        document.getElementById('product_id').value = productID;
                        jQuery('#comments-loader').show();
                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'get_vendor_data',
                                product_id: productID,
                            },
                            success: function (response) {
                                if (response.success) {
                                    jQuery('#comments-loader').hide();
                                    updateEditorBackground(response.data);
                                    jQuery('.manifesti-container').removeClass('hide');
                                } else {
                                    alert('Errore nel caricamento dei dati del venditore: ' + response.data);
                                }
                            },
                            error: function () {
                                alert('Errore nella richiesta AJAX.');
                            }
                        });
                    }

                    // Initialize contenteditable div with a <p> if it's empty
                    const textEditor = document.getElementById('text-editor');
                    const toolbar = document.createElement('div');
                    toolbar.className = 'editor-toolbar';
                    toolbar.innerHTML = `
                <button type="button" data-command="bold"><b>B</b></button>
                <button type="button" data-command="italic"><i>I</i></button>
                <button type="button" data-command="underline"><u>U</u></button>
            `;
                    document.body.appendChild(toolbar);

                    function showToolbar(event) {
                        const selection = window.getSelection();
                        if (selection.rangeCount > 0 && !selection.isCollapsed) {
                            const range = selection.getRangeAt(0).getBoundingClientRect();
                            toolbar.style.display = 'block';
                            toolbar.style.top = `${range.top + window.scrollY - toolbar.offsetHeight - 5}px`;
                            toolbar.style.left = `${range.left + window.scrollX + range.width / 2 - toolbar.offsetWidth / 2}px`;

                        } else {
                            toolbar.style.display = 'none';

                        }
                    }

                    function applyCommand(command) {
                        document.execCommand(command, false, null);
                    }

                    document.addEventListener('mouseup', showToolbar);
                    document.addEventListener('touchend', showToolbar);
                    toolbar.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        applyCommand(event.target.closest('button').getAttribute('data-command'));
                       // setTimeout(showToolbar, 50); // Aggiungi un ritardo per permettere alla selezione di stabilizzarsi
                    });

                    if (textEditor.innerHTML.trim() === '') {
                        textEditor.innerHTML = '<p><br></p>';
                    }

                    // Handle Enter key to create new paragraphs
                    textEditor.addEventListener('keypress', function (event) {
                        const editorMaxHeight = textEditor.clientHeight;
                        if (event.key === 'Enter') {

                            //create a p element with a id
                            const p = document.createElement('p');
                            p.id = 'p' + Math.floor(Math.random() * 1000000);
                            //add br to the p element
                            p.innerHTML = '<br>';
                            textEditor.appendChild(p);

                            if (textEditor.scrollHeight > editorMaxHeight) { // 20px buffer for new paragraph
                                event.preventDefault();
                                textEditor.removeChild(p);
                            } else {
                                textEditor.removeChild(p);
                                document.execCommand('formatBlock', false, 'p');
                            }
                        } else {
                            if (textEditor.scrollHeight > editorMaxHeight) {
                                alert('Il testo è troppo lungo per l\'editor.');
                                textEditor.innerHTML = textEditor.innerHTML.substring(0, textEditor.innerHTML.length - 1);
                            }
                        }
                    });

                    // Convert newlines to <p> tags when submitting the form
                    document.getElementById('custom-text-editor-form').addEventListener('submit', function (event) {
                        const textEditor = document.getElementById('text-editor');
                        const paragraphs = textEditor.innerHTML.split('\n').map(line => `<p>${line}</p>`).join('');
                        const hiddenTextarea = document.createElement('textarea');
                        hiddenTextarea.name = 'custom_text';
                        hiddenTextarea.style.display = 'none';
                        hiddenTextarea.value = paragraphs;
                        document.getElementById('custom-text-editor-form').appendChild(hiddenTextarea);
                    });
                });
            </script>
            <?php
            return ob_get_clean();
        }




    }
}