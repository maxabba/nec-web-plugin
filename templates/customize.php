<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

(new \Dokan_Mods\Templates_MiscClass())->check_dokan_can_and_message_login();
$user_id = get_current_user_id();
$manifesto_background = get_user_meta($user_id, 'manifesto_background', true);//check if vendor status is enabled
$top = get_user_meta($user_id, 'manifesto_margin_top', true) ?:5;
$right = get_user_meta($user_id, 'manifesto_margin_right', true) ?: 5;
$bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true) ?: 5;
$left = get_user_meta($user_id, 'manifesto_margin_left', true) ?: 5;
$alignment = get_user_meta($user_id, 'manifesto_alignment', true) ?: 'center';
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

// Includi l'header
get_header();

$active_menu = 'settings/customize';

// Include the Dokan dashboard sidebar

?>

    <main id="content" class="site-main post-58 page type-page status-publish hentry">

        <header class="page-header">
            <h1 class="entry-title"><?php __('Personalizza', 'dokan') ?></h1></header>

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
                            <?php _e('Personalizza Manifesto', 'dokan'); ?> <span
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
                        }else{
                            echo '<div class="alert-tmp hide"></div>';
                        }
                        ?>
                    </header>

                    <div class="product-edit-new-container product-edit-container" style="margin-bottom: 100px">

                        <!-- if the vendor status is enabled show the form -->
                        <?php if (!$disable_form) { ?>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="customize_poster">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">


                                    <!-- add a wp.media input for select the image and limit to the user upload -->
                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label"
                                           for="poster_image"><?php _e('Immagine del Manifesto', 'dokan'); ?></label>
                                    <div class="dokan-w5">
                                        <input type="button" class="button" value="Seleziona Immagine"
                                               id="upload_image_button">
                                        <input type="hidden" name="manifesto_background" id="manifesto_background"
                                               value="<?php echo $manifesto_background; ?>">

                                    </div>
                                </div>

                                <h3 class="entry-title">
                                    <?php _e('Modifica allineamento testo', 'dokan'); ?>
                                </h3>
                                <span style="font-size:14px; margin-bottom:25px">
                                        Modifica l'allineamento del testo all'interno del manifesto.
                                            </span>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="alignment">Allineamento</label>
                                    <div class="dokan-w5">
                                        <select name="manifesto_alignment" id="alignment">
                                            <option value="left" <?php echo $alignment == 'left' ? 'selected' : ''; ?>>Sinistra</option>
                                            <option value="center" <?php echo $alignment == 'center' ? 'selected' : ''; ?>>Centro</option>
                                            <option value="right" <?php echo $alignment == 'right' ? 'selected' : ''; ?>>Destra</option>
                                        </select>
                                    </div>
                                </div>

                                <h3 class="entry-title">
                                    <?php _e('Modifica margini', 'dokan'); ?>
                                </h3>
                                <span style="font-size:14px; margin-bottom:25px">
                                        Modifica i margini di scrittura del manifesto. Tali margini sono espressi in pixel.
                                            </span>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="top">Top</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_top" id="top"
                                               value="<?php echo $top; ?>">

                                    </div>
                                </div>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="right">Right</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_right" id="right"
                                               value="<?php echo $right; ?>">
                                    </div>
                                </div>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="bottom">Bottom</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_bottom" id="bottom"
                                               value="<?php echo $bottom; ?>">
                                    </div>
                                </div>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="left">Left</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_left" id="left"
                                               value="<?php echo $left; ?>">
                                    </div>
                                </div>


                                <div class="dokan-form-group dokan-clearfix">
                                    <div class="dokan-w5">
                                        <div id="image_container"
                                             style="background-image: url('<?php echo $manifesto_background; ?>'); background-size: contain; background-repeat: no-repeat; background-position: center; position: relative; width: 80%; max-width: 100%; margin: 0 auto;">
                                            <div id="inner_container"
                                                 style="border: 2px solid #000; background-color: rgba(255, 255, 255, 0.5); position: absolute;">
                                                <p class="inner-text" style="margin: 0; padding: 10px;">Testo di
                                                    esempio</p>
                                                <p class="inner-text" style="margin: 0; padding: 10px;">
                                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. In eu dui
                                                    odio. Aenean tempor elementum fringilla. Praesent finibus
                                                    condimentum dictum. Aenean a augue erat. Integer urna nulla, mattis
                                                    quis bibendum consectetur, tincidunt id massa. Nulla vehicula
                                                    maximus mauris, ac ultricies lacus ullamcorper a. Pellentesque ut
                                                    odio metus. Fusce malesuada egestas luctus.
                                                </p>
                                                <p class="inner-text" style="margin: 0; padding: 10px;">Testo di
                                                    esempio</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <input type="submit" class="dokan-btn dokan-btn-theme" value="Salva">
                            </form>

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

        .alert.hide {
            display: none;
        }

        #controls_wrapper {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
        }

        .margin-input {
            margin-bottom: 5px;
        }

        .margin-input label {
            margin-right: 5px;
        }

        .margin-input input {
            width: 150px;
        }

        #image_container {
            width: 80%; /* Imposta la larghezza massima al 80% */
            max-width: 100%;
            position: relative;
            margin: 0 auto; /* Centra l'immagine */
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        #inner_container {
            position: absolute;
            top: <?php echo $top; ?>px;
            right: <?php echo $right; ?>px;
            bottom: <?php echo $bottom; ?>px;
            left: <?php echo $left; ?>px;
        }

    </style>

    <script>

        //add the script to wp.media for select the image
        jQuery(document).ready(function ($) {
            const topInput = document.getElementById('top');
            const rightInput = document.getElementById('right');
            const bottomInput = document.getElementById('bottom');
            const leftInput = document.getElementById('left');
            const alignmentSelect = document.getElementById('alignment');
            const innerContainer = document.getElementById('inner_container');
            const innerTextElements = document.querySelectorAll('.inner-text');
            const imageContainer = document.getElementById('image_container');
            const manifestoBackground = document.getElementById('manifesto_background');

            function updateMargins() {
                innerContainer.style.top = `${topInput.value}px`;
                innerContainer.style.right = `${rightInput.value}px`;
                innerContainer.style.bottom = `${bottomInput.value}px`;
                innerContainer.style.left = `${leftInput.value}px`;
            }

            function updateAspectRatio() {
                const img = new Image();
                img.src = manifestoBackground.value;
                img.onload = function () {
                    const containerWidth = imageContainer.clientWidth;
                    const aspectRatio = img.height / img.width;
                    const containerHeight = containerWidth * aspectRatio;
                    imageContainer.style.height = `${containerHeight}px`;
                }
            }

            function updateAlignment() {
                const alignment = alignmentSelect.value;
                innerTextElements.forEach(el => {
                    el.style.textAlign = alignment;
                });
            }

            topInput.addEventListener('input', updateMargins);
            rightInput.addEventListener('input', updateMargins);
            bottomInput.addEventListener('input', updateMargins);
            leftInput.addEventListener('input', updateMargins);
            alignmentSelect.addEventListener('change', updateAlignment);

            if (manifestoBackground.value) {
                updateAspectRatio();
            }

            // Inizializza con i valori correnti
            updateMargins();
            updateAlignment();

            var custom_uploader;
            $('#upload_image_button').click(function (e) {
                e.preventDefault();
                //If the uploader object has already been created, reopen the dialog
                if (custom_uploader) {
                    custom_uploader.open();
                    return;
                }
                //Extend the wp.media object
                custom_uploader = wp.media.frames.file_frame = wp.media({
                    title: 'Scegli Immagine',
                    button: {
                        text: 'Scegli Immagine'
                    },
                    multiple: false
                });
                custom_uploader.on('select', function () {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();

                    // Validate image dimensions
                    var img = new Image();
                    img.src = attachment.url;
                    img.onload = function () {
                        var width = img.width;
                        var height = img.height;
                        var ratio = height / width;

                        // Check if the ratio is approximately 1:1.414
                        if (Math.abs(ratio - 1.414) < 0.01 || Math.abs(ratio - (1 / 1.414)) < 0.01) {
                            $('#manifesto_background').val(attachment.url);
                            $('#image_container').css('background-image', 'url(' + attachment.url + ')');
                            const containerWidth = imageContainer.clientWidth;
                            const aspectRatio = height / width;
                            const containerHeight = containerWidth * aspectRatio;
                            $('#image_container').css('height', `${containerHeight}px`);
                            updateMargins();
                            // Determine the image orientation and add it to the form
                            var orientation = height > width ? 'vertical' : 'horizontal';
                            if (!$('#manifesto_orientation').length) {
                                $('<input>').attr({
                                    type: 'hidden',
                                    id: 'manifesto_orientation',
                                    name: 'manifesto_orientation',
                                    value: orientation
                                }).appendTo('form');
                            } else {
                                $('#manifesto_orientation').val(orientation);
                            }
                        } else {
                            $('#manifesto_background').val('');
                            $('#image_container').css('background-image', '');
                            $('#image_container').css('height', '0');
                            var alert = document.querySelector('.alert-tmp');
                            alert.innerHTML = 'L\'immagine selezionata non rispetta la proporzione richiesta (ISO 216). <br> La proporzione corretta è 1:1.414 (circa) orizzontale o verticale.';
                            alert.classList.add('alert');
                            alert.classList.add('alert-danger');
                            alert.classList.remove('hide');
                            setTimeout(function () {
                                fadeOut(alert);
                                alert.innerHTML = '';
                                alert.classList.remove('alert');
                                alert.classList.remove('alert-danger');
                                alert.classList.add('hide');
                            }, 10000);
                        }
                    };
                });

                //Open the uploader dialog
                custom_uploader.open();
            });
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
                    element.classList.add('hide');
                    element.style.opacity = '';
                    element.style.filter = '';
                } else {
                    element.style.opacity = op;
                    element.style.filter = 'alpha(opacity=' + op * 100 + ")";
                    op -= op * 0.1;
                }
            }, 50);
        }

    </script>
<?php

get_footer();
