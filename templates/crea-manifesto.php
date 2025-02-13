<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */


$template_class = (new \Dokan_Mods\Templates_MiscClass());
$template_class->check_dokan_can_and_message_login();


$user_id = get_current_user_id();
$manifesto_background = get_user_meta($user_id, 'manifesto_background', true) !== '' ? get_user_meta($user_id, 'manifesto_background', true) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/img/default.jpg';
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
            <h1 class="entry-title"><?php __($add_edit_text . ' Manifesto per: ' . $post_title, 'dokan-mod') ?></h1>
        </header>

        <div class="page-content">

            <div class="dokan-dashboard-wrap">

                <?php
                dokan_get_template_part('global/dashboard-nav', '', ['active_menu' => $active_menu]);
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
                            <?php if ($post_id !== 'new_post' and !isset($_GET['redirect_to'])) {
                                echo $form;
                            } ?>
                            <?php
                            // Check if the user is logged in
                        if (is_user_logged_in()) {
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
                                $manifesto_background = get_user_meta($user_id, 'manifesto_background', true) !== '' ? get_user_meta($user_id, 'manifesto_background', true) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/img/default.jpg';
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
                                     style="background-image: url('<?php echo $manifesto_background; ?>');
                                             background-size: contain; background-repeat: no-repeat; background-position: center; position: relative; margin: 0 auto;">
                                    <div id="inner_container"
                                         style="position: absolute; font-size: 14px; text-align: <?php echo $alignment; ?>;">
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


                            $form_args = array(
                                'post_id' => $post_id,
                                'new_post' => array(
                                    'post_type' => 'manifesto',
                                    'post_status' => isset($_GET['redirect_to']) ? 'draft' : 'publish', // Set status based on redirect_to
                                ),
                                'field_groups' => array('group_6666bf01a488b'),
                                'submit_value' => __($add_edit_text, 'Dokan-mod'),
                                'return' => $redirect_to,
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
            width: 350px; /* Imposta la larghezza massima al 80% */
            position: relative;
            margin: 0 auto; /* Centra l'immagine */
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        #inner_container {
            position: absolute;
            top: <?php echo $margin_top; ?>px;
            right: <?php echo $margin_right; ?>px;
            bottom: <?php echo $margin_bottom; ?>px;
            left: <?php echo $margin_left; ?>px;
        }
    </style>

    <script>


        jQuery(document).ready(function ($) {
            const innerContainer = $('#inner_container');
            const innerTextElements = $('.inner-text');
            const imageContainer = $('#image_container');

            function initInputMargin() {
                const containerWidth = imageContainer.width();
                const containerHeight = imageContainer.height();

                const marginTopPer = <?php echo $margin_top; ?>;
                const marginRightPer = <?php echo $margin_right; ?>;
                const marginBottomPer = <?php echo $margin_bottom; ?>;
                const marginLeftPer = <?php echo $margin_left; ?>;

                const marginTopPx = Math.round((marginTopPer / 100) * containerHeight);
                const marginRightPx = Math.round((marginRightPer / 100) * containerWidth);
                const marginBottomPx = Math.round((marginBottomPer / 100) * containerHeight);
                const marginLeftPx = Math.round((marginLeftPer / 100) * containerWidth);

                innerContainer.css({
                    top: `${marginTopPx}px`,
                    right: `${marginRightPx}px`,
                    bottom: `${marginBottomPx}px`,
                    left: `${marginLeftPx}px`
                });


            }


            function updateAspectRatio() {
                const img = new Image();
                img.src = "<?php echo $manifesto_background; ?>";
                img.onload = function () {
                    const containerWidth = imageContainer.width();
                    const aspectRatio = img.height / img.width;
                    const containerHeight = containerWidth * aspectRatio;
                    imageContainer.css('height', `${containerHeight}px`);

                    // Call initInputMargin after setting the height
                    initInputMargin();
                }
            }

            function updateAlignment() {
                const alignment = "<?php echo $alignment; ?>";
                innerTextElements.css('text-align', alignment);
            }

            updateAspectRatio();
            updateAlignment();

            //find the id of the div with class mce-tinymce


            function addChangeListenerToTinyMCE() {
                // Verifica se tinymce è definito
                if (typeof tinymce !== 'undefined') {
                    // Itera su tutti gli editor TinyMCE
                    tinymce.editors.forEach(function (editor) {
                        if (!editor.hasChangeListener) { // Evita di aggiungere più volte lo stesso listener
                            // Aggiungi un listener per l'evento 'change'
                            editor.on('change', function (e) {
                                // Ottieni il contenuto dell'editor
                                var content = editor.getContent();
                                innerContainer.html(content);
                                // Puoi fare altre azioni qui, come inviare il contenuto tramite AJAX
                            });
                            editor.hasChangeListener = true; // Segna che il listener è stato aggiunto
                        }
                    });
                }
            }

            // Aggiungi i listener quando la pagina è pronta
            addChangeListenerToTinyMCE();

            // ACF può aggiungere campi dinamicamente, quindi intercetta l'evento 'acf/setup_fields'
            $(document).on('acf/setup_fields', function (e, postbox) {
                addChangeListenerToTinyMCE();
            });

            // Inoltre, verifica periodicamente se ci sono nuovi editor TinyMCE inizializzati
            setInterval(addChangeListenerToTinyMCE, 1000); // Ogni secondo

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
