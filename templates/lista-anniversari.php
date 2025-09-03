<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

use Dokan_Mods\Templates_MiscClass;

(new Templates_MiscClass())->check_dokan_can_and_message_login();

$user_id = get_current_user_id();
$store_info = dokan_get_store_info($user_id);
$user_city = $store_info['address']['city'] ?? '';

$post_id_annuncio = isset($_GET['post_id_annuncio']) ? intval($_GET['post_id_annuncio']) : null;
$title = get_the_title($post_id_annuncio);
//check if vendor status is enabled
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

if (get_query_var('paged')) {
    $paged = get_query_var('paged');
} elseif (get_query_var('page')) {
    $paged = get_query_var('page');
} else {
    $paged = 1;
}

$highest_anniversario_query = new WP_Query(array(
    'post_type' => 'anniversario',
    'author' => $user_id,
    'posts_per_page' => 1,
    'meta_key' => 'anniversario_n_anniversario',
    'orderby' => 'meta_value_num',
    'order' => 'DESC'
));

// Get the 'anniversario_n_anniversario' field of the first post
if ($highest_anniversario_query->have_posts()) {
    $highest_anniversario_query->the_post();
    $highest_anniversario = intval(get_field('anniversario_n_anniversario')) + 1;
} else {
    $highest_anniversario = '';
}
$url = home_url('dashboard/crea-anniversario?post_id_annuncio=' . $post_id_annuncio . '&n_anniversario=' . $highest_anniversario);

$args = array(
    'post_type' => 'anniversario',
    'author' => $user_id,
    'posts_per_page' => 10, // Change this to the number of posts you want per page
    'paged' => $paged,
    's' => get_query_var('s'),
    'meta_query' => array(
        array(
            'key' => 'annuncio_di_morte',
            'value' => $post_id_annuncio,
            'compare' => '='
        )
    ),
    'meta_key' => 'anniversario_n_anniversario',
    'orderby' => 'meta_value_num',
    'order' => 'DESC'
);

// Execute the query

// Includi l'header
get_header();

$active_menu = '';

// Include the Dokan dashboard sidebar

?>

    <main id="content" class="site-main post-58 page type-page status-publish hentry">

        <header class="page-header">
            <h1 class="entry-title"><?php __('Lista Anniversari: ' . $title, 'dokan-mod') ?></h1></header>

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
                            <?php _e('Lista Anniversari: ' . $title, 'dokan-mod')  ?> <span
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
                            <a href="<?php echo $url; ?>" class="custom-widget-button" style="margin-bottom: 15px">
                                <i class="fas fa-plus"></i> <?php _e('Aggiungi Anniversario', 'dokan-mod'); ?>
                            </a>
                            <form method="get" action="<?php echo esc_url(home_url('/dashboard/anniversari')); ?>"
                                  style="display: flex;">
                                <input type="text" name="s" value="<?php echo get_query_var('s'); ?>"
                                       placeholder="Search..." style="margin-right: 10px;">
                                <input type="submit" value="Search">
                            </form>
                            <table>
                                <thead>
                                <tr>
                                    <th><?php _e('Anniversario', 'dokan-mod'); ?></th>
                                    <th><?php _e('Titolo', 'dokan-mod'); ?></th>
                                    <th><?php _e('Data publicazione', 'dokan-mod'); ?></th>
                                    <th><?php _e('Città', 'dokan-mod'); ?></th>
                                    <th><?php _e('Azioni', 'dokan-mod'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $query = new WP_Query($args);

                                if ($query->have_posts()) :
                                    while ($query->have_posts()) : $query->the_post();
                                        ?>
                                        <tr>
                                            <td><?php echo get_field('anniversario_n_anniversario'); ?></td>
                                            <td><?php the_title(); ?></td>
                                            <td><?php the_date(); ?></td>
                                            <td><?php echo get_post_meta(get_the_ID(), 'citta', true); ?></td>
                                            <td>
                                                <a href="<?php echo home_url('/dashboard/crea-anniversario?post_id=' . get_the_ID() . '&post_id_annuncio=' . $post_id_annuncio); ?>"><?php _e('Modifica', 'dokan-mod'); ?></a>
                                            </td>
                                        </tr>
                                    <?php
                                    endwhile;
                                else :
                                    ?>
                                    <tr>
                                        <td colspan="5"><?php _e('Nessun post trovato.', 'dokan-mod'); ?></td>
                                    </tr>
                                <?php
                                endif;

                                ?>
                                </tbody>
                            </table>

                            <div class="pagination">
                                <div class="tablenav-pages">
                                    <span class="displaying-num"><?php echo $query->found_posts; ?> elementi</span>
                                    <span class="pagination-links">
                                    <?php
                                    $paginate_links = paginate_links(array(
                                        'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                                        'total' => $query->max_num_pages,
                                        'current' => max(1, get_query_var('paged')),
                                        'show_all' => false,
                                        'type' => 'array',
                                        'end_size' => 2,
                                        'mid_size' => 1,
                                        'prev_next' => true,
                                        'prev_text' => '‹',
                                        'next_text' => '›',
                                        'add_args' => false,
                                        'add_fragment' => '',
                                    ));

                                    if ($paginate_links) {
                                        $pagination = '';
                                        foreach ($paginate_links as $link) {
                                            $pagination .= "<span class='paging-input'>$link</span>";
                                        }
                                        echo $pagination;
                                    }
                                    ?>
                                </span>
                                </div>
                            </div>
                            <?php

                        } else { ?>

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
<?php

get_footer();
