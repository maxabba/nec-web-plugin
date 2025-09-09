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



$args = array(
    'post_type' => 'manifesto',
    'post_status' => 'publish, pending, draft, future, private',
    'author' => $user_id,
    'posts_per_page' => 10, // Change this to the number of posts you want per page
    'paged' => $paged,
    's' => get_query_var('s'),
    'meta_query' => array(
        array(
            'key' => 'annuncio_di_morte_relativo',
            'value' => $post_id_annuncio,
            'compare' => '='
        )
    ),
);

// Execute the query

// Includi l'header
get_header();

$active_menu = '';

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
                            <?php _e('Lista Partecipazioni: ' . $title, 'dokan-mod') ?> <span
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
                            <a href="<?php echo esc_url(home_url('/dashboard/crea-manifesto/?post_id_annuncio=' . $post_id_annuncio)); ?>"
                               class="custom-widget-button" style="margin-bottom: 15px">
                            <i class="fas fa-plus"></i> <?php _e('Aggiungi Partecipazione', 'dokan-mod'); ?>
                            </a>

                            <div style="display: flex; flex-direction: column; width: 300px; margin-bottom: 15px">
                                <label for="page-format" style="margin-bottom: 5px;">Seleziona formato pagina:</label>
                                <div style="display: flex; align-items: center;">
                                    <select id="page-format" style="flex: 1; margin-right: 10px;">
                                        <option value="A3">A3</option>
                                        <option value="A4">A4</option>
                                        <option value="A5">A5</option>
                                    </select>
                                    <button id="start-button">Stampa tutte le partecipazioni</button>
                                </div>
                            </div>
                            <div id="hidden-container" data-postid="<?php echo $post_id_annuncio; ?>"></div>
                            <div id="progress-bar-container" style="display: none; width: 100%; margin-top: 10px;">
                                <div id="progress-bar" style="width: 0; height: 20px; background: green;"></div>
                            </div>


                            <form method="get" action="<?php echo esc_url(home_url('/dashboard/lista-manifesti')); ?>"
                                  style="display: flex;">
                                <input type="text" name="s" value="<?php echo get_query_var('s'); ?>"
                                       placeholder="Search..." style="margin-right: 10px;">
                                <input type="submit" value="Search">
                            </form>
                            <table>
                                <thead>
                                <tr>
                                    <th><?php _e('Testo', 'dokan-mod'); ?></th>
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
                                            <td><?php echo wp_strip_all_tags(get_field('testo_manifesto')); ?></td>
                                            <td><?php the_date(); ?></td>
                                            <td><?php echo get_post_meta(get_the_ID(), 'citta', true); ?></td>
                                            <td>
                                                <a href="<?php echo home_url('/dashboard/crea-manifesto?post_id=' . get_the_ID() . '&post_id_annuncio=' . $post_id_annuncio); ?>"><?php _e('Modifica', 'dokan-mod'); ?></a>
                                            </td>
                                        </tr>
                                    <?php
                                    endwhile;
                                else :
                                    ?>
                                    <tr>
                                        <td colspan="4"><?php _e('Nessun post trovato.', 'dokan-mod'); ?></td>
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
