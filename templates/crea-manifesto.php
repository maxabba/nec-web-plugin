<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */


$template_class = (new \Dokan_Mods\Templates_MiscClass());
$template_class->check_dokan_can_and_message_login();


$user_id = get_current_user_id();
$manifesto_background = get_user_meta($user_id, 'manifesto_background', true) !== '' ? get_user_meta($user_id, 'manifesto_background', true) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default.jpg';
$manifesto_orientation = get_user_meta($user_id, 'manifesto_orientation', true) !== '' ? get_user_meta($user_id, 'manifesto_orientation', true) : 'vertical';
$margin_top = get_user_meta($user_id, 'manifesto_margin_top', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_top', true) : '3.9188837174992';
$margin_right = get_user_meta($user_id, 'manifesto_margin_right', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_right', true) : '5.8620083240518';
$margin_bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_bottom', true) : '3.9188837174992';
$margin_left = get_user_meta($user_id, 'manifesto_margin_left', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_left', true) : '5.8620083240518';
$alignment = get_user_meta($user_id, 'manifesto_alignment', true) !== '' ? get_user_meta($user_id, 'manifesto_alignment', true) : 'center';

$post_id_annuncio = isset($_GET['post_id_annuncio']) ? intval($_GET['post_id_annuncio']) : null;
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 'new_post';


$form = $template_class->render_post_state_form_and_handle($post_id);
$redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : null;

//se redirect non è null il link di redirect è il redirect_to
if ($redirect_to !== null) {
    $redirect_to = $redirect_to;
}else{
    $redirect_to = home_url('/dashboard/lista-manifesti/?post_id_annuncio=' . $post_id_annuncio . '&operation_result=success');
}


$add_edit_text = $post_id === 'new_post' ? 'Crea' : 'Modifica';

//get the title
$post_title = get_the_title($post_id_annuncio);
//check if vendor status is enabled
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

// Includi l'header
get_header();

$active_menu = 'add-annuncio';

// Include the Dokan dashboard sidebar

?>

    <main id="content" class="site-main post-58 page type-page status-publish hentry">

        <header class="page-header">
        </header>

        <div class="page-content">

            <div class="dokan-dashboard-wrap">

                <?php
                /**
                 *  Adding dokan_dashboard_content_before hook
                 *
                 * @hooked dashboard_side_navigation
                 *
                 * @since 2.4
                 */
                do_action('dokan_dashboard_content_before');
                ?>

                <div class="dokan-dashboard-content dokan-product-edit">
                    <?php

                    /**
                     *  Adding dokan_dashboard_content_before hook
                     *
                     * @hooked get_dashboard_side_navigation
                     *
                     * @since 2.4
                     */
                    do_action('dokan_dashboard_content_inside_before');
                    do_action('dokan_before_listing_product');
                    ?>
                    <header class="dokan-dashboard-header dokan-clearfix">

                        <h1 class="entry-title">
                            <?php _e($add_edit_text . ' Partecipazione per: ' . $post_title, 'dokan-mod'); ?> <span
                                    class="dokan-label  dokan-product-status-label">
                                            </span>
                        </h1>
                        <?php
                        if (isset($_GET['operation_result'])) {
                            $operation_result = wp_kses($_GET['operation_result'], array());
                            if ($operation_result == 'success') {
                                echo '<div class="alert alert-success">Operazione eseguita con successo.</div>';
                            } else if ($operation_result == 'error') {
                                echo '<div class="alert alert-danger">Si è verificato un errore durante l\'operazione.</div>';
                            }
                        }
                        ?>
                    </header>


                    <div class="product-edit-new-container product-edit-container" style="margin-bottom: 100px">

                        <!-- if the vendor status is enabled show the form -->
                        <?php if (!$disable_form) { ?>
                            <?php
                            // Check if the user is logged in
                        if (is_user_logged_in()) {
                            // Show inline post state control for existing posts (but not for redirect_to flow)
                            if ($post_id !== 'new_post' and !isset($_GET['redirect_to'])) {
                                echo $template_class->render_post_state_inline_control($post_id);
                            }
                            
                            // Parameters for the ACF form

                            function set_annuncio_di_morte_field($field)
                            {

                                // Check if it's our post type
                                if ($field['key'] == 'field_6666bf025040a') {
                                    $post_id_annuncio = isset($_GET['post_id_annuncio']) ? intval($_GET['post_id_annuncio']) : 'new_post';

                                    $field['value'] = $post_id_annuncio;
                                    $field['readonly'] = true;
                                    $field['wrapper']['style'] = 'display: none;';

                                }
                                if ($field['key'] == 'field_6666bf6b5040b') {

                                    $user_id = get_current_user_id();

                                    $field['value'] = $user_id;
                                    $field['readonly'] = true;
                                    $field['wrapper']['style'] = 'display: none;';

                                }


                                return $field;
                            }

                            // Apply to fields named "annuncio_di_morte".
                            add_filter('acf/prepare_field/key=field_6666bf025040a', 'set_annuncio_di_morte_field');
                            add_filter('acf/prepare_field/key=field_6666bf6b5040b', 'set_annuncio_di_morte_field');


                            function manifesto_Render($field)
                            {
                                $user_id = get_current_user_id();
                                $manifesto_background = get_user_meta($user_id, 'manifesto_background', true) !== '' ? get_user_meta($user_id, 'manifesto_background', true) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default.jpg';
                                $alignment = get_user_meta($user_id, 'manifesto_alignment', true) !== '' ? get_user_meta($user_id, 'manifesto_alignment', true) : 'center';

                                // Get the post ID from URL parameter
                                $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 'new_post';

                                // Get existing manifesto content if editing
                                $existing_content = '';
                                if ($post_id !== 'new_post') {
                                    $existing_content = get_field('testo_manifesto', $post_id);
                                }

                                ob_start();
                                ?>
                                <div id="image_container"
                                     style="background-image: none;">
                                    <div id="inner_container"
                                         style="position: absolute; text-align: <?php echo $alignment; ?>;">
                                        <?php echo $existing_content; ?>
                                    </div>
                                </div>
                                <?php
                                echo ob_get_clean();
                            }
                            add_action('acf/render_field/name=testo_manifesto', 'manifesto_Render');

                            function set_manifesto_field($field)
                            {

                                // Check if it's our post type
                                if ($field['key'] == 'field_6669ea01b516d') {

                                    $field['value'] = 'online';
                                    $field['readonly'] = true;
                                    $field['wrapper']['style'] = 'display: none;';

                                }
                                return $field;
                            }

                            add_filter('acf/prepare_field/key=field_6669ea01b516d', 'set_manifesto_field');


                            // Generate hidden field for post status control
                            $current_status = ($post_id !== 'new_post') ? get_post_status($post_id) : 'draft';
                            $hidden_field_html = '<input type="hidden" id="acf_post_status_control" name="acf_post_status_control" value="' . esc_attr($current_status) . '" data-original="' . esc_attr($current_status) . '">';
                            
                            $form_args = array(
                                'post_id' => $post_id,
                                'new_post' => array(
                                    'post_type' => 'manifesto',
                                    'post_status' => isset($_GET['redirect_to']) ? 'draft' : 'publish', // Set status based on redirect_to
                                ),
                                'field_groups' => array('group_6666bf01a488b'),
                                'submit_value' => __($add_edit_text, 'Dokan-mod'),
                                'return' => $redirect_to,
                                'html_after_fields' => $hidden_field_html,
                            );

                            acf_form($form_args);

                        } else {
                            echo '<p>' . __('Devi essere loggato per compilare questo form.', 'dokan-mod') . '</p>';
                            wp_login_form();
                        }
                            ?>

                        <?php } else { ?>

                            <!-- else show a centered icon of deny -->
                            <div style="display: flex; justify-content: center; align-items: center; height: 250px">
                                <i class="fas fa-ban" style="font-size: 100px; color: red;"></i>
                            </div>
                        <?php } ?>


                    </div>

                </div><!-- .dokan-dashboard-content -->


            </div><!-- .dokan-dashboard-wrap -->


            <div class="post-tags">
            </div>
        </div>


    </main>
    <style>
    /* Override theme CSS per omogeneizzare con layout standard Dokan */
    body.dokan-dashboard.theme-hello-elementor .site-main,
    body.dokan-dashboard .site-main {
        max-width: none !important;
        width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    body.dokan-dashboard.theme-hello-elementor .page-content,
    body.dokan-dashboard .page-content {
        max-width: none !important;
        width: 100% !important;
    }
    
    body.dokan-dashboard.theme-hello-elementor .dokan-dashboard-wrap,
    body.dokan-dashboard .dokan-dashboard-wrap {
        width: 100% !important;
        max-width: 1140px !important;
        margin: 0 auto !important;
    }


        .dokan-form-group {
            margin-bottom: 20px;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
        }

        .alert {
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 5px;
            box-shadow: 0 2px 1px -1px rgba(0, 0, 0, 0.2), 0 1px 1px 0 rgba(0, 0, 0, 0.14), 0 1px 3px 0 rgba(0, 0, 0, 0.12);
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .hidden-field {
            display: none;
        }

        #image_container {
            position: relative;
            aspect-ratio: 16 / 9;
            height: clamp(300px, 50vh, 600px);
            width: 100%;
            background-size: contain;
            background-position: center;
            margin-inline: auto;
            overflow: hidden;
        }

        #inner_container {
            --font-size: clamp(14px, 1.5vw, 16px);

            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
            color: #000;
            resize: none;
            box-sizing: border-box;
            outline: none;
            overflow: visible;
            font-size: var(--font-size);
            font-family: var(--e-global-typography-text-font-family), Sans-serif;
            font-weight: var(--e-global-typography-text-font-weight);
        }
        }

    </style>

    <script>
        jQuery(document).ready(function ($) {
            const innerContainer = $('#inner_container');
            const innerTextElements = $('.inner-text');
            const imageContainer = $('#image_container');

            // New function to update editor background and margins
            function updateEditorBackground(data) {
                const backgroundDiv = imageContainer[0];
                const textEditor = innerContainer[0];

                if (data.manifesto_background) {
                    const img = new Image();
                    img.src = data.manifesto_background;
                    img.onload = function () {
                        const aspectRatio = img.width / img.height;
                        backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';

                        if (aspectRatio > 1) {
                            backgroundDiv.style.width = '100%';
                            backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
                        } else {
                            backgroundDiv.style.height = '350px';
                            backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
                        }

                        const marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                        const marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                        const marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                        const marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                        textEditor.style.paddingTop = `${marginTopPx}px`;
                        textEditor.style.paddingRight = `${marginRightPx}px`;
                        textEditor.style.paddingBottom = `${marginBottomPx}px`;
                        textEditor.style.paddingLeft = `${marginLeftPx}px`;
                        textEditor.style.textAlign = data.alignment || 'left';
                    }
                } else {
                    backgroundDiv.style.backgroundImage = 'none';
                }
            }

            // Existing function to initialize input margin (modified to use new approach)
            function initInputMargin() {
                const data = {
                    manifesto_background: "<?php echo $manifesto_background; ?>",
                    margin_top: <?php echo $margin_top; ?>,
                    margin_right: <?php echo $margin_right; ?>,
                    margin_bottom: <?php echo $margin_bottom; ?>,
                    margin_left: <?php echo $margin_left; ?>,
                    alignment: "<?php echo $alignment; ?>"
                };

                updateEditorBackground(data);
            }

            function updateAlignment() {
                const alignment = "<?php echo $alignment; ?>";
                innerTextElements.css('text-align', alignment);
            }

            initInputMargin();
            updateAlignment();

            function addChangeListenerToTinyMCE() {
                if (typeof tinymce !== 'undefined') {
                    tinymce.editors.forEach(function (editor) {
                        if (!editor.hasChangeListener) {
                            editor.on('change', function (e) {
                                var content = editor.getContent();
                                innerContainer.html(content);
                            });
                            editor.hasChangeListener = true;
                        }
                    });
                }
            }

            // Existing event listeners and intervals
            addChangeListenerToTinyMCE();

            $(document).on('acf/setup_fields', function (e, postbox) {
                addChangeListenerToTinyMCE();
            });

            setInterval(addChangeListenerToTinyMCE, 1000);
        });

        window.onload = function () {
            var alerts = document.querySelectorAll('.alert');
            setTimeout(function () {
                for (var i = 0; i < alerts.length; i++) {
                    fadeOut(alerts[i]);
                }
            }, 5000);
        }

        function fadeOut(element) {
            var op = 1;  // initial opacity
            var timer = setInterval(function () {
                if (op <= 0.1) {
                    clearInterval(timer);
                    element.style.display = 'none';
                }
                element.style.opacity = op;
                element.style.filter = 'alpha(opacity=' + op * 100 + ")";
                op -= op * 0.1;
            }, 50);
        }
    </script>
<?php

get_footer();
