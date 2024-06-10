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


        }

        public function register_shortcodes()
        {
            add_shortcode('vendor_selector', array($this, 'shortcode_vendor_selector'));
            add_shortcode('custom_text_editor', array($this, 'create_custom_text_editor_shortcode'));
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
                        <div class="vendor-card">
                            <input type="radio" name="product_id" id="product_<?php echo $product_id; ?>"
                                   value="<?php echo $product_id; ?>">
                            <label for="product_<?php echo $product_id; ?>">
                                <div class="card">
                                    <?php if ($store_banner) : ?>
                                        <img src="<?php echo $store_banner; ?>" alt="<?php echo $store_name; ?>"
                                             class="card-img-top">
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

                    .vendor-card {
                        flex: 1 1 30%;
                        max-width: 30%;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        border-radius: 5px;
                        overflow: hidden;
                        position: relative;
                        text-align: center;
                    }

                    .vendor-card input[type="radio"] {
                        position: absolute;
                        opacity: 0;
                        cursor: pointer;
                    }

                    .vendor-card input[type="radio"]:checked + label .card {
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
                      id="custom-text-editor-form">
                    <input type="hidden" name="action" value="save_custom_text_editor">
                    <input type="hidden" name="product_id" id="product_id" value="">
                    <input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>">

                    <div style="width:70%;margin:auto;" class="manifesti-container hide">
                        <div id="text-editor-background" class="text-editor-background" style="background-image: none;">
                            <textarea id="custom_text" name="custom_text" class="custom-text-editor"></textarea>
                        </div>

                        <input type="submit" value="Salva" class="button">
                    </div>
                </form>
            </div>
            <style>
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
                    height: 400px;
                    width: auto;
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
                    font-size: 16px;
                    resize: none;
                    padding: 20px;
                    box-sizing: border-box;
                    outline: none;
                    font-family: var(--e-global-typography-text-font-family), Sans-serif;
                    font-weight: var(--e-global-typography-text-font-weight);
                }
            </style>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    function updateEditorBackground(data) {
                        const backgroundDiv = document.getElementById('text-editor-background');
                        const textarea = document.getElementById('custom_text');

                        if (data.manifesto_background) {
                            const img = new Image();
                            img.src = data.manifesto_background;
                            img.onload = function () {
                                const aspectRatio = img.width / img.height;
                                backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                                if (aspectRatio > 1) {
                                    // Landscape
                                    backgroundDiv.style.height = '400px';
                                    backgroundDiv.style.width = `${400 * aspectRatio}px`;
                                } else {
                                    // Portrait
                                    backgroundDiv.style.height = `${400 / aspectRatio}px`;
                                    backgroundDiv.style.width = '400px';
                                }
                            }
                        } else {
                            backgroundDiv.style.backgroundImage = 'none';
                        }

                        if (data.manifesto_orientation === 'vertical') {
                            textarea.style.writingMode = 'vertical-lr';
                        } else {
                            textarea.style.writingMode = 'horizontal-tb';
                        }

                        // Applica i margini e l'allineamento
                        textarea.style.marginTop = data.margin_top ? data.margin_top + 'px' : '0';
                        textarea.style.marginRight = data.margin_right ? data.margin_right + 'px' : '0';
                        textarea.style.marginBottom = data.margin_bottom ? data.margin_bottom + 'px' : '0';
                        textarea.style.marginLeft = data.margin_left ? data.margin_left + 'px' : '0';
                        textarea.style.textAlign = data.alignment ? data.alignment : 'left';
                    }

                    window.setProductID = function (productID) {
                        document.getElementById('product_id').value = productID;

                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'get_vendor_data',
                                product_id: productID,
                            },
                            success: function (response) {
                                if (response.success) {
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

                    // Convert newlines to <p> tags when submitting the form
                    document.getElementById('custom-text-editor-form').addEventListener('submit', function (event) {
                        const textarea = document.getElementById('custom_text');
                        const text = textarea.value;
                        textarea.value = text.split('\n').map(line => `<p>${line}</p>`).join('');
                    });
                });
            </script>
            <?php

            return ob_get_clean();
        }


    }
}