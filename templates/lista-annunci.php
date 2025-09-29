<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

(new \Dokan_Mods\Templates_MiscClass())->check_dokan_can_and_message_login();

$user_id = get_current_user_id();
$store_info = dokan_get_store_info($user_id);
$user_city = $store_info['address']['city'] ?? '';


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
$args = array(
    'post_type' => 'annuncio-di-morte',
    'post_status' => 'publish,pending,draft,future,private',
    //'author' => $user_id,
    'meta_query' => array(
        array(
            'key' => 'citta',
            'value' => $user_city,
            'compare' => '=',
        ),
    ),
    'posts_per_page' => 10, // Change this to the number of posts you want per page
    'paged' => $paged,
    's' => get_query_var('s'),
);

$filter_all_posts = isset($_GET['all_posts']) ? $_GET['all_posts'] : false;

if (!$filter_all_posts) {
    // Default: mostra solo i post dell'utente corrente
    $args['author'] = $user_id;
    // Rimuoviamo la meta_query per mostrare solo i post dell'utente corrente
    unset($args['meta_query']);
} else {
    // Quando all_posts è attivo, escludi i post dell'utente corrente
    $args['author__not_in'] = array($user_id);
}

// Execute the query

// Includi l'header
get_header();

$active_menu = 'annunci/lista-annunci';

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
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h1 class="entry-title">
                                <?php _e('Lista Annunci di Morte', 'dokan-mod'); ?>
                            </h1>
                            <div>
                                <?php if ($filter_all_posts): ?>
                                    <a href="<?php echo esc_url(home_url('/dashboard/lista-annunci')); ?>"
                                       class="dokan-btn dokan-btn-theme">
                                        <?php _e('I tuoi Annunci', 'dokan-mod'); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(add_query_arg('all_posts', '1')); ?>"
                                       class="dokan-btn dokan-btn-theme">
                                        <?php _e('Tutti gli Annunci', 'dokan-mod'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        if (isset($_GET['operation_result'])) {
                            $operation_result = wp_kses($_GET['operation_result'], array());
                            if ($operation_result == 'success') {
                                echo '<div class="alert alert-success">Operazione eseguita con successo.</div>';
                            } else if ($operation_result == 'error') {
                                echo '<div class="alert alert-danger">Si è verificato un errore durante l\'operazione.</div>';
                            }
                        }
                        
                        // Show success message for deletion
                        if (isset($_GET['deleted'])) {
                            echo '<div class="alert alert-success">🗑️ Elemento eliminato con successo.</div>';
                        }
                        
                        $template_class = new \Dokan_Mods\Templates_MiscClass();
                        ?>
                    </header>

                    <div class="product-edit-new-container product-edit-container" style="margin-bottom: 100px">

                        <!-- if the vendor status is enabled show the form -->
                        <?php if (!$disable_form) { ?>
                            <a href="<?php echo home_url('dashboard/crea-annuncio/'); ?>" 
                               style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; margin-bottom: 15px;">
                                <i class="fas fa-plus"></i> <?php _e('Aggiungi Annuncio', 'dokan-mod'); ?>
                            </a>
                            <form method="get" action="<?php echo esc_url(home_url('/dashboard/lista-annunci')); ?>"
                                  style="display: flex;">
                                <input type="text" name="s" value="<?php echo get_query_var('s'); ?>"
                                       placeholder="Search..." style="margin-right: 10px;">
                                <input type="submit" value="Search">
                            </form>
                            <div class="table-responsive">
                                <table>
                                <thead>
                                <tr>
                                    <th><?php _e('Titolo', 'dokan-mod'); ?></th>
                                    <th><?php _e('Data publicazione', 'dokan-mod'); ?></th>
                                    <th><?php _e('Stato', 'dokan-mod'); ?></th>
                                    <th><?php _e('Agenzia', 'dokan-mod'); ?></th>
                                    <th><?php _e('Città', 'dokan-mod'); ?></th>
                                    <th><?php _e('Azioni', 'dokan-mod'); ?></th>
                                    <th><?php _e('Partecipazioni', 'dokan-mod'); ?></th>
                                    <th><?php _e('Trigesimo', 'dokan-mod'); ?></th>
                                    <th><?php _e('Ringraziamento', 'dokan-mod'); ?></th>
                                    <th><?php _e('Anniversari', 'dokan-mod'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $query = new WP_Query($args);

                                if ($query->have_posts()) :
                                    while ($query->have_posts()) : $query->the_post();

                                        $the_author_id = get_the_author_meta('ID');
                                        $modify_post_url_disabled = false;
                                        if ($the_author_id != $user_id) {
                                            $modify_post_url_disabled = true;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php the_title(); ?></td>
                                            <td><?php the_date(); ?></td>
                                            <td><?php echo $template_class->get_formatted_post_status(get_post_status()); ?></td>
                                            <td>
                                                <?php echo get_the_author() ?>
                                            </td>
                                            <td><?php echo get_post_meta(get_the_ID(), 'citta', true); ?></td>
                                            <td>
                                                <?php if ($modify_post_url_disabled): ?>
                                                    <span style="color: #999; cursor: not-allowed;"><?php _e('Modifica', 'dokan-mod'); ?></span>
                                                <?php else: ?>
                                                    <a href="<?php echo home_url('/dashboard/crea-annuncio?post_id=' . get_the_ID()); ?>"><?php _e('Modifica', 'dokan-mod'); ?></a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo home_url('/dashboard/lista-manifesti?post_id_annuncio=' . get_the_ID()); ?>"><?php _e('Visualizza lista', 'dokan-mod'); ?></a>
                                            </td>
                                            <td>
                                                <?php if ($modify_post_url_disabled): ?>
                                                    <span style="color: #999; cursor: not-allowed;"><?php _e('Aggiungi/Modifica', 'dokan-mod'); ?></span>
                                                <?php else: ?>
                                                    <a href="<?php echo home_url('/dashboard/trigesimo-add?post_id_annuncio=' . get_the_ID()); ?>"><?php _e('Aggiungi/Modifica', 'dokan-mod'); ?></a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($modify_post_url_disabled): ?>
                                                    <span style="color: #999; cursor: not-allowed;"><?php _e('Aggiungi/Modifica', 'dokan-mod'); ?></span>
                                                <?php else: ?>
                                                    <a href="<?php echo home_url('/dashboard/ringraziamento-add?post_id_annuncio=' . get_the_ID()); ?>"><?php _e('Aggiungi/Modifica', 'dokan-mod'); ?></a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($modify_post_url_disabled): ?>
                                                    <span style="color: #999; cursor: not-allowed;"><?php _e('Visualizza lista', 'dokan-mod'); ?></span>
                                                <?php else: ?>
                                                    <a href="<?php echo home_url('/dashboard/lista-anniversari?post_id_annuncio=' . get_the_ID()); ?>"><?php _e('Visualizza lista', 'dokan-mod'); ?></a>
                                                <?php endif; ?>
                                            </td>

                                        </tr>
                                    <?php
                                    endwhile;
                                else :
                                    ?>
                                    <tr>
                                        <td colspan="10"><?php _e('Nessun post trovato.', 'dokan-mod'); ?></td>
                                    </tr>
                                <?php
                                endif;

                                ?>
                                </tbody>
                            </table>
                            </div>

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



        .dokan-btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dokan-btn-theme {
            background-color: #f2f2f2;
            color: #333;
            border: 1px solid #ddd;
        }

        .dokan-btn-theme:hover {
            background-color: #e6e6e6;
            color: #333;
            text-decoration: none;
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


        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .tablenav-pages {
            display: flex;
            align-items: center;
        }

        .displaying-num {
            margin-right: 10px;
            font-weight: bold;
        }

        .pagination-links {
            display: flex;
        }

        .tablenav-pages-navspan {
            background-color: #f1f1f1;
            border: 1px solid #ccc;
            padding: 5px 10px;
            margin-right: 5px;
            cursor: not-allowed;
        }

        .paging-input {
            display: flex;
            align-items: center;
        }

        .paging-input input {
            margin: 0 5px;
        }

        .paging-input .total-pages {
            font-weight: bold;
        }

        .paging-input a {
            background-color: #0073aa;
            color: #fff;
            padding: 5px 10px;
            margin-right: 5px;
            text-decoration: none;
        }

        .paging-input span {
            padding: 5px 10px;
            margin-right: 5px;
            text-decoration: none;
        }
        .paging-input a:hover {
            background-color: #009fd4;
        }

        form {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }

        form input[type="text"] {
            flex-grow: 1;
            margin-right: 10px;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            position: relative;
        }

        .table-responsive table {
            min-width: 1200px;
            white-space: nowrap;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-responsive th,
        .table-responsive td {
            padding: 8px 12px;
            min-width: 100px;
            background-color: white;
            border-bottom: 1px solid #ddd;
        }

        /* Prima colonna fissa */
        .table-responsive th:first-child,
        .table-responsive td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            min-width: 250px;
            max-width: 300px;
            white-space: normal;
            word-break: break-word;
            background-color: white;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .table-responsive th:first-child {
            z-index: 3;
            background-color: #f5f5f5;
        }

        .table-responsive thead th {
            background-color: #f5f5f5;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table-responsive thead th:first-child {
            z-index: 4;
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
