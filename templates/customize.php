<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

$template_helper = new \Dokan_Mods\Templates_MiscClass();
$template_helper->check_dokan_can_and_message_login();
//$template_helper->enqueue_dashboard_common_styles();
$user_id = get_current_user_id();
$manifesto_background = get_user_meta($user_id, 'manifesto_background', true) !== '' ? get_user_meta($user_id, 'manifesto_background', true) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default.jpg';
$manifesto_orientation = get_user_meta($user_id, 'manifesto_orientation', true) !== '' ? get_user_meta($user_id, 'manifesto_orientation', true) : 'vertical';
$margin_top = get_user_meta($user_id, 'manifesto_margin_top', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_top', true) : '3.457838574264';
$margin_right = get_user_meta($user_id, 'manifesto_margin_right', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_right', true) : '4.8850069367098';
$margin_bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_bottom', true) : '3.457838574264';
$margin_left = get_user_meta($user_id, 'manifesto_margin_left', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_left', true) : '4.8850069367098';
$alignment = get_user_meta($user_id, 'manifesto_alignment', true) !== '' ? get_user_meta($user_id, 'manifesto_alignment', true) : 'center';
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

// Includi l'header
get_header();

$active_menu = 'settings/customize';

// Include the Dokan dashboard sidebar

?>
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

        /* Ensure container compatibility */
        body.dokan-dashboard .container,
        body.dokan-dashboard .elementor-container {
            max-width: none !important;
            width: 100% !important;
        }

        @media (max-width: 1024px) {
            body.dokan-dashboard.theme-hello-elementor .dokan-dashboard-wrap,
            body.dokan-dashboard .dokan-dashboard-wrap {
                max-width: calc(100% - 40px) !important;
                margin: 0 20px !important;
            }
        }

        @media (max-width: 768px) {
            body.dokan-dashboard.theme-hello-elementor .dokan-dashboard-wrap,
            body.dokan-dashboard .dokan-dashboard-wrap {
                margin: 0 10px !important;
                max-width: calc(100% - 20px) !important;
            }

            .dokan-dashboard-content {
                padding: 15px;
            }

            /* Stack form elements on mobile */
            form {
                flex-direction: column;
            }

            form input[type="text"] {
                margin-right: 0;
                margin-bottom: 10px;
            }

            /* Adjust pagination for mobile */
            .pagination {
                flex-wrap: wrap;
            }

            .tablenav-pages {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
    <main id="content" class="site-main post-58 page type-page status-publish hentry">

        <header class="page-header">
            <h1 class="entry-title"><?php __('Personalizza', 'dokan-mod') ?></h1></header>

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
                            <?php _e('Personalizza Partecipazione', 'dokan-mod'); ?> <span
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
                        } else {
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
                                <input type="hidden" id="margin_top_percent" name="margin_top_percent"
                                       value="<?php echo $margin_top; ?>">
                                <input type="hidden" id="margin_bottom_percent" name="margin_bottom_percent"
                                       value="<?php echo $margin_bottom; ?>">
                                <input type="hidden" id="margin_right_percent" name="margin_right_percent"
                                       value="<?php echo $margin_right; ?>">
                                <input type="hidden" id="margin_left_percent" name="margin_left_percent"
                                       value="<?php echo $margin_left; ?>">


                                <!-- add a wp.media input for select the image and limit to the user upload -->
                                <div class="dokan-form-group dokan-clearfix">
                                    <h4>Requisiti per il Caricamento delle Immagini</h4>
                                    <p>Per favore, assicurati che l'immagine da caricare rispetti i seguenti
                                        requisiti:</p>
                                    <h5>Formato A3:</h5>
                                    <ul>
                                        <li><strong>Dimensioni in pixel:</strong>
                                            <ul>
                                                <li><strong>Orizzontale (Landscape):</strong> 1075 x 1522 pixel</li>
                                                <li><strong>Verticale (Portrait):</strong> 1522 x 1075 pixel</li>
                                            </ul>
                                        </li>
                                    </ul>
                                    <p>Non importa l'orientamento, sia orizzontale che verticale sono accettati, a patto
                                        che rispettino le dimensioni sopra indicate.</p>
                                    <br>
                                    <br>
                                    <label class="dokan-w3 dokan-control-label"
                                           for="poster_image"><?php _e('Seleziona uno sfondo per le tue partecipazioni', 'dokan-mod'); ?></label>
                                    <div class="dokan-w5">
                                        <input type="button" class="button" value="Seleziona Immagine"
                                               id="upload_image_button">
                                        <input type="hidden" name="manifesto_background" id="manifesto_background"
                                               value="<?php echo $manifesto_background; ?>">

                                    </div>
                                </div>

                                <h3 class="entry-title">
                                    <?php _e('Modifica allineamento testo', 'dokan-mod'); ?>
                                </h3>
                                <p style="font-size:14px; margin-bottom:25px">
                                        Modifica l'allineamento del testo all'interno della partecipazione.
                                            </p>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="alignment">Allineamento</label>
                                    <div class="dokan-w5">
                                        <select name="manifesto_alignment" id="alignment">
                                            <option value="left" <?php echo $alignment == 'left' ? 'selected' : ''; ?>>
                                                Sinistra
                                            </option>
                                            <option value="center" <?php echo $alignment == 'center' ? 'selected' : ''; ?>>
                                                Centro
                                            </option>
                                            <option value="right" <?php echo $alignment == 'right' ? 'selected' : ''; ?>>
                                                Destra
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <h3 class="entry-title">
                                    <?php _e('Modifica margini', 'dokan-mod'); ?>
                                </h3>
                                <p style="font-size:14px; margin-bottom:25px">
                                        Modifica i margini di scrittura della partecipazione. Tali margini sono espressi in pixel.
                                            </p>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="top">Top</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_top" id="top"
                                               value="">

                                    </div>
                                </div>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="right">Right</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_right" id="right"
                                               value="">
                                    </div>
                                </div>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="bottom">Bottom</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_bottom" id="bottom"
                                               value="">
                                    </div>
                                </div>

                                <div class="dokan-form-group dokan-clearfix">
                                    <label class="dokan-w3 dokan-control-label" for="left">Left</label>
                                    <div class="dokan-w5">
                                        <input type="number" name="manifesto_margin_left" id="left"
                                               value="">
                                    </div>
                                </div>


                                <div class="dokan-form-group dokan-clearfix">
                                    <div class="dokan-w5">
                                        <p>L'anteprima sotto riportata è valida per i soli margini, la dimensione del
                                            testo non rispecchia la reale dimensione nelle partecipazioni online.</p>

                                        <div id="image_container"
                                             style="background-image: url('<?php echo $manifesto_background; ?>');
                                                     background-size: contain; background-repeat: no-repeat; background-position: center; position: relative; width: 80%; max-width: 100%; margin: 0 auto;">
                                            <div id="inner_container" contenteditable="true"
                                                 style="border: 2px solid #000; background-color: rgba(255, 255, 255, 0.5); position: absolute; font-size: 14px;overflow: hidden;">
                                                <p class="inner-text">Testo di
                                                    esempio</p>
                                                <p class="inner-text">
                                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. In eu dui
                                                    odio. Aenean tempor elementum fringilla. Praesent finibus
                                                    condimentum dictum. Aenean a augue erat. Integer urna nulla, mattis
                                                    quis bibendum consectetur, tincidunt id massa.
                                                </p>
                                                <p class="inner-text">Testo di
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
    <?php
    // Common dashboard JavaScript for alerts fade effect
    echo $template_helper->get_dashboard_common_scripts();
    ?>

    <script>
        jQuery(document).ready(function ($) {
            const topInput = $('#top');
            const rightInput = $('#right');
            const bottomInput = $('#bottom');
            const leftInput = $('#left');
            const alignmentSelect = $('#alignment');
            const innerContainer = $('#inner_container');
            const innerTextElements = $('.inner-text');
            const imageContainer = $('#image_container');
            const manifestoBackground = $('#manifesto_background');

            function initInputMargin() {
                const containerWidth = imageContainer.width();
                const containerHeight = imageContainer.height();

                const marginTopPer = parseFloat($('#margin_top_percent').val());
                const marginRightPer = parseFloat($('#margin_right_percent').val());
                const marginBottomPer = parseFloat($('#margin_bottom_percent').val());
                const marginLeftPer = parseFloat($('#margin_left_percent').val());

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

                topInput.val(marginTopPx);
                rightInput.val(marginRightPx);
                bottomInput.val(marginBottomPx);
                leftInput.val(marginLeftPx);
            }

            function updateMargins() {
                const containerWidth = imageContainer.width();
                const containerHeight = imageContainer.height();

                const marginTopPx = Math.round(parseFloat(topInput.val()));
                const marginRightPx = Math.round(parseFloat(rightInput.val()));
                const marginBottomPx = Math.round(parseFloat(bottomInput.val()));
                const marginLeftPx = Math.round(parseFloat(leftInput.val()));

                const marginTopPercent = (marginTopPx / containerHeight) * 100;
                const marginRightPercent = (marginRightPx / containerWidth) * 100;
                const marginBottomPercent = (marginBottomPx / containerHeight) * 100;
                const marginLeftPercent = (marginLeftPx / containerWidth) * 100;

                innerContainer.css({
                    top: `${marginTopPx}px`,
                    right: `${marginRightPx}px`,
                    bottom: `${marginBottomPx}px`,
                    left: `${marginLeftPx}px`
                });

                $('#margin_top_percent').val(marginTopPercent);
                $('#margin_right_percent').val(marginRightPercent);
                $('#margin_bottom_percent').val(marginBottomPercent);
                $('#margin_left_percent').val(marginLeftPercent);
            }

            function updateAspectRatio() {
                const img = new Image();
                img.src = manifestoBackground.val();
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
                const alignment = alignmentSelect.val();
                innerTextElements.css('text-align', alignment);
            }

            topInput.on('input', updateMargins);
            rightInput.on('input', updateMargins);
            bottomInput.on('input', updateMargins);
            leftInput.on('input', updateMargins);
            alignmentSelect.on('change', updateAlignment);

            if (manifestoBackground.val()) {
                updateAspectRatio();
            }

            updateAlignment();

            var custom_uploader;
            $('#upload_image_button').click(function (e) {
                e.preventDefault();
                if (custom_uploader) {
                    custom_uploader.open();
                    return;
                }
                custom_uploader = wp.media.frames.file_frame = wp.media({
                    title: 'Scegli Immagine',
                    button: {
                        text: 'Scegli Immagine'
                    },
                    multiple: false
                });
                custom_uploader.on('select', function () {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    var img = new Image();
                    img.src = attachment.url;
                    img.onload = function () {
                        var width = img.width;
                        var height = img.height;
                        var ratio = height / width;
                        if (Math.abs(ratio - 1.414) < 0.01 || Math.abs(ratio - (1 / 1.414)) < 0.01) {
                            manifestoBackground.val(attachment.url);
                            imageContainer.css('background-image', 'url(' + attachment.url + ')');
                            const containerWidth = imageContainer.width();
                            const aspectRatio = height / width;
                            const containerHeight = containerWidth * aspectRatio;
                            imageContainer.css('height', `${containerHeight}px`);
                            updateMargins();
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
                            manifestoBackground.val('');
                            imageContainer.css('background-image', '');
                            imageContainer.css('height', '0');
                            var alert = $('.alert-tmp');
                            alert.html('L\'immagine selezionata non rispetta la proporzione richiesta (ISO 216). <br> La proporzione corretta è 1:1.414 (circa) orizzontale o verticale.');
                            alert.addClass('alert alert-danger');
                            alert.removeClass('hide');
                            setTimeout(function () {
                                fadeOut(alert);
                                alert.html('');
                                alert.removeClass('alert alert-danger');
                                alert.addClass('hide');
                            }, 10000);
                        }
                    };
                });
                custom_uploader.open();
            });
        });

        jQuery(window).on('load', function () {
            var alerts = jQuery('.alert');
            setTimeout(function () {
                alerts.each(function () {
                    fadeOut(jQuery(this));
                });
            }, 5000);
        });

        function fadeOut(element) {
            var op = 1;
            var timer = setInterval(function () {
                if (op <= 0.1) {
                    clearInterval(timer);
                    element.addClass('hide');
                    element.css('opacity', '');
                    element.css('filter', '');
                } else {
                    element.css('opacity', op);
                    element.css('filter', `alpha(opacity=${op * 100})`);
                    op -= op * 0.1;
                }
            }, 50);
        }

    </script>
<?php

get_footer();
