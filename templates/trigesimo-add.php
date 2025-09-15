<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */


$template_class = (new \Dokan_Mods\Templates_MiscClass());
$template_class->check_dokan_can_and_message_login();


$user_id = get_current_user_id();

$post_id_annuncio = isset($_GET['post_id_annuncio']) ? intval($_GET['post_id_annuncio']) : 'new_post';
//check if exist a post type trigesimo that has the field post_object annuncio_di_morte
$args = array(
    'post_type' => 'trigesimo',
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'author' => $user_id,
    'posts_per_page' => 1,
    'meta_query' => array(
        array(
            'key' => 'annuncio_di_morte',
            'value' => $post_id_annuncio,
            'compare' => '='
        )
    )
);

// Execute the query
$trigesimo_query = new WP_Query($args);
//if the post exist set the post_id to the post_id of the trigesimo
if ($trigesimo_query->have_posts()) {
    $trigesimo_query->the_post();
    $post_id = get_the_ID();
}

$post_id = isset($post_id) ? intval($post_id) : 'new_post';
$form = $template_class->render_post_state_form_and_handle($post_id);

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
                            <?php _e($add_edit_text . ' Trigesimo per: ' . $post_title, 'dokan-mod'); ?> <span
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
                                // Show inline post state control for existing posts
                                if ($post_id !== 'new_post') {
                                    echo $template_class->render_post_state_inline_control($post_id);
                                }
                                
                                // Parameters for the ACF form
                                
                                function set_annuncio_di_morte_field_trigesimo($field)
                                {
                                    $post_id_annuncio = isset($_GET['post_id_annuncio']) ? intval($_GET['post_id_annuncio']) : 'new_post';

                                    // Check if it's our post type
                                    if ($field['key'] == 'field_66570739481f1') {
                                        $field['value'] = $post_id_annuncio;
                                        $field['readonly'] = true;
                                        $field['wrapper']['style'] = 'display: none;';
                                    }

                                    return $field;
                                }

                                // Apply to fields - always apply the filter
                                add_filter('acf/prepare_field/key=field_66570739481f1', 'set_annuncio_di_morte_field_trigesimo');

                                // Generate hidden field for post status control
                                $current_status = ($post_id !== 'new_post') ? get_post_status($post_id) : 'publish';
                                $hidden_field_html = '<input type="hidden" id="acf_post_status_control" name="acf_post_status_control" value="' . esc_attr($current_status) . '" data-original="' . esc_attr($current_status) . '">';
                                
                                $form_args = array(
                                    'post_id' => $post_id,
                                    'new_post' => array(
                                        'post_type' => 'trigesimo',
                                        'post_status' => 'publish',
                                    ),
                                    'field_groups' => array('group_6657073924698'),
                                    'submit_value' => __($add_edit_text, 'Dokan-mod'),
                                    'return' => home_url('/dashboard/lista-annunci'),
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
    </style>

    <script>
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
