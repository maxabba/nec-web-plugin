<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

(new \Dokan_Mods\Templates_MiscClass())->check_dokan_can_and_message_login();
$user_id = get_current_user_id();
$manifesto_background = get_user_meta($user_id, 'manifesto_background', true);//check if vendor status is enabled
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

// Includi l'header
get_header();

$active_menu = 'customize';

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
                                        <label class="dokan-w3 dokan-control-label" for="poster_image"><?php _e('Immagine del Manifesto', 'dokan'); ?></label>
                                        <div class="dokan-w5">
                                            <input type="button" class="button" value="Seleziona Immagine" id="upload_image_button">
                                            <input type="hidden" name="manifesto_background" id="manifesto_background"
                                                   value="<?php echo $manifesto_background; ?>">
                                            <div id="image_container">
                                                <?php if ($manifesto_background) { ?>
                                                    <img src="<?php echo $manifesto_background; ?>" style="max-width: 50%; height: auto;">
                                                <?php } ?>
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
    </style>

    <script>

        //add the script to wp.media for select the image
        jQuery(document).ready(function ($) {
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
                //When a file is selected, grab the URL and set it as the text field's value
                custom_uploader.on('select', function () {
                    attachment = custom_uploader.state().get('selection').first().toJSON();
                    $('#manifesto_background').val(attachment.url);
                    $('#image_container').html('<img src="' + attachment.url + '" style="max-width: 100%; height: auto;">');
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
