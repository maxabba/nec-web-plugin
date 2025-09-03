<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

$template_class = (new \Dokan_Mods\Templates_MiscClass());
$template_class->check_dokan_can_and_message_login();
$template_class->enqueue_dashboard_common_styles();


$user_id = get_current_user_id();
$store_info = dokan_get_store_info($user_id);
$user_city = $store_info['address']['city'] ?? '';
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 'new_post';
$form = $template_class->schedule_post_and_update_status($post_id);

//check if the current user is the autor of the post
if ($post_id !== 'new_post') {
    $post_author_id = get_post_field('post_author', $post_id);
    $post_author_id = intval($post_author_id);
    // Dovremmo controllare anche il vendor_id oltre all'autore
    if ($post_author_id !== $user_id ) {
        //redirect to the dashboard lista-annunci solo se l'utente non è né l'autore né il vendor
        wp_redirect(home_url('/dashboard/lista-annunci'));
        exit; // Aggiungiamo exit dopo il redirect
    }
}

//check if vendor status is enabled
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

// Includi l'header
get_header();

$active_menu = 'annunci/crea-annuncio';

// Include the Dokan dashboard sidebar

?>

    <main id="content" class="site-main post-58 page type-page status-publish hentry">

        <header class="page-header">
            <h1 class="entry-title"><?php __('Crea Nuovo Annuncio di Morte', 'dokan-mod') ?></h1></header>

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
                            <?php _e('Crea Nuovo Annuncio di Morte', 'dokan-mod'); ?> <span
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


                                function acf_load_post_date_field($field)
                                {
                                    if (empty($field['value'])) {
                                        $field['value'] = current_time('Y-m-d H:i:s');
                                    }
                                    return $field;
                                }

                                add_filter('acf/load_field/name=post_date', 'acf_load_post_date_field');

                                // Parameters for the ACF form
                                $add_edit_text = $post_id === 'new_post' ? 'Crea' : 'Modifica';
                                $form_args = array(
                                    'post_id' => $post_id,
                                    'new_post' => array(
                                        'post_type' => 'annuncio-di-morte',
                                        'post_status' => 'draft',
                                    ),
                                    'field_groups' => array('group_6641d54c5f58d', 'group_666ef28ce50a3'),
                                    'submit_value' => __($add_edit_text, 'Dokan-mod'),
                                    'return' => add_query_arg(array(
                                        'operation_result' => 'success'
                                    ), home_url('/dashboard/lista-annunci')),
                                    'updated_message' => __('Annuncio aggiornato con successo.', 'dokan-mod'),
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
    <?php
    // Common dashboard JavaScript for alerts fade effect
    echo $template_class->get_dashboard_common_scripts();
    ?>

<?php

get_footer();
