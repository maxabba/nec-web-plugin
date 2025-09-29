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



// Get search term from URL parameter
$search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$meta_query = array(
    'relation' => 'AND',
    array(
        'key' => 'annuncio_di_morte_relativo',
        'value' => $post_id_annuncio,
        'compare' => '='
    )
);

// If there's a search term, add it to the meta_query to search ONLY in testo_manifesto field
if (!empty($search_term)) {
    $meta_query[] = array(
        'key' => 'testo_manifesto',
        'value' => $search_term,
        'compare' => 'LIKE'
    );
}

$args = array(
    'post_type' => 'manifesto',
    'post_status' => 'publish, pending, draft, future, private',
    'author' => $user_id,
    'posts_per_page' => 10, // Change this to the number of posts you want per page
    'paged' => $paged,
    'meta_query' => $meta_query,
    // Explicitly NOT including 's' parameter to avoid title/content search
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
                        
                        // Show success message for deletion
                        if (isset($_GET['deleted'])) {
                            echo '<div class="alert alert-success">🗑️ Elemento eliminato con successo.</div>';
                        }
                        
                        $template_class = new Templates_MiscClass();
                        ?>
                    </header>

                    <div class="product-edit-new-container product-edit-container" style="margin-bottom: 100px">

                        <!-- if the vendor status is enabled show the form -->
                        <?php if (!$disable_form) { ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <!-- Bottone Aggiungi Partecipazione a sinistra -->
                                <a href="<?php echo esc_url(home_url('/dashboard/crea-manifesto/?post_id_annuncio=' . $post_id_annuncio)); ?>"
                                   style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;">
                                    <i class="fas fa-plus"></i> <?php _e('Aggiungi Partecipazione', 'dokan-mod'); ?>
                                </a>
                                
                                <!-- Selettore formato e bottone stampa a destra -->
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <label for="page-format" style="margin: 0;">Formato:</label>
                                    <select id="page-format" style="padding: 5px 10px;">
                                        <option value="A3">A3</option>
                                        <option value="A4" selected>A4</option>
                                        <option value="A5">A5</option>
                                    </select>
                                    <button id="start-button" class="btn-print" title="Stampa tutte le partecipazioni" style="padding: 8px 12px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Modale per avviso stampa -->
                            <div id="print-modal" class="modal" style="display: none;">
                                <div class="modal-content">
                                    <span class="close">&times;</span>
                                    <h2 style="margin-bottom: 20px;">🖨️ Stampa Partecipazioni</h2>
                                    <div id="modal-message">
                                        <p>Verranno aperte due finestre di stampa separate:</p>
                                        <ul style="list-style: none; padding-left: 0;">
                                            <li style="margin: 10px 0;">📄 <strong>Finestra 1:</strong> Manifesti orizzontali (landscape)</li>
                                            <li style="margin: 10px 0;">📃 <strong>Finestra 2:</strong> Manifesti verticali (portrait)</li>
                                        </ul>
                                        <p style="margin-top: 20px;">Assicurati di consentire i popup nel browser per procedere con la stampa.</p>
                                    </div>
                                    <div style="text-align: center; margin-top: 30px;">
                                        <button id="proceed-print" style="padding: 10px 30px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 16px;">
                                            Procedi con la stampa
                                        </button>
                                        <button id="cancel-print" style="padding: 10px 30px; background: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; margin-left: 10px;">
                                            Annulla
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="hidden-container" data-postid="<?php echo $post_id_annuncio; ?>" data-title="<?php echo esc_attr($title); ?>" style="position: absolute; top: 100vh; left: -9999px; visibility: hidden;"></div>
                            <div id="progress-bar-container" style="display: none; width: 100%; margin-top: 10px;">
                                <div id="progress-bar" style="width: 0; height: 20px; background: green;"></div>
                            </div>


                            <form method="get" action="<?php echo esc_url(home_url('/dashboard/lista-manifesti')); ?>"
                                  style="display: flex;">
                                <input type="hidden" name="post_id_annuncio" value="<?php echo esc_attr($post_id_annuncio); ?>">
                                <input type="text" name="s" value="<?php echo esc_attr($search_term); ?>"
                                       placeholder="Cerca nel testo..." style="margin-right: 10px;">
                                <input type="submit" value="Cerca">
                            </form>
                            <div class="table-responsive">
                                <table>
                                <thead>
                                <tr>
                                    <th><?php _e('Testo', 'dokan-mod'); ?></th>
                                    <th><?php _e('Data publicazione', 'dokan-mod'); ?></th>
                                    <th><?php _e('Stato', 'dokan-mod'); ?></th>
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
                                            <td><?php 
                                                $testo = wp_strip_all_tags(get_field('testo_manifesto'));
                                                $words = explode(' ', $testo);
                                                if (count($words) > 5) {
                                                    $testo_troncato = implode(' ', array_slice($words, 0, 5)) . '...';
                                                } else {
                                                    $testo_troncato = $testo;
                                                }
                                                echo $testo_troncato;
                                            ?></td>
                                            <td><?php the_date(); ?></td>
                                            <td><?php echo $template_class->get_formatted_post_status(get_post_status()); ?></td>
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
                                        <td colspan="5"><?php _e('Nessun post trovato.', 'dokan-mod'); ?></td>
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
                                    // Preserve query parameters in pagination
                                    $pagination_args = array();
                                    if ($post_id_annuncio) {
                                        $pagination_args['post_id_annuncio'] = $post_id_annuncio;
                                    }
                                    if (!empty($search_term)) {
                                        $pagination_args['s'] = $search_term;
                                    }
                                    
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
                                        'add_args' => $pagination_args,
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

        /* Stili per il modale */
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close:hover,
        .close:focus {
            color: #000;
        }

        .btn-print:hover {
            opacity: 0.9;
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
        }

        .table-responsive table {
            min-width: 700px;
            white-space: nowrap;
        }

        .table-responsive th,
        .table-responsive td {
            padding: 8px 12px;
            min-width: 100px;
        }
        /* Pre-caricamento font per le finestre di stampa */
        @font-face {
            font-family: "PlayFair Display Mine";
            src: url("<?php echo DOKAN_SELECT_PRODUCTS_PLUGIN_URL; ?>assets/fonts/Playfair_Display/static/PlayfairDisplay-Regular.ttf") format("truetype");
            font-display: swap;
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
