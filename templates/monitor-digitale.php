<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Dokan_Mods\Templates_MiscClass;

// Check if vendor is logged in and enabled for monitor
$template_class = new Templates_MiscClass();
$template_class->check_dokan_can_and_message_login();

$user_id = get_current_user_id();
$monitor_class = new Dokan_Mods\MonitorTotemClass();

if (!$monitor_class->is_vendor_enabled($user_id)) {
    ?>
    <div class="dokan-alert dokan-alert-warning">
        <strong>Accesso Negato:</strong> Il tuo account non è abilitato per l'utilizzo del Monitor Digitale. 
        Contatta l'amministratore per richiedere l'abilitazione.
    </div>
    <?php
    return;
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'associate_defunto' && wp_verify_nonce($_POST['monitor_nonce'], 'monitor_associate_defunto')) {
        $post_id = intval($_POST['post_id']);
        
        // Verify post belongs to vendor
        $post = get_post($post_id);
        if ($post && $post->post_author == $user_id && $post->post_type === 'annuncio-di-morte') {
            $monitor_class->associate_post($user_id, $post_id);
            $message = sprintf(__('"%s" è stato associato al monitor digitale.', 'dokan-mod'), get_the_title($post_id));
            $message_type = 'success';
        } else {
            $message = __('Errore: Annuncio non valido o non autorizzato.', 'dokan-mod');
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'remove_association' && wp_verify_nonce($_POST['monitor_nonce'], 'monitor_remove_association')) {
        $monitor_class->remove_association($user_id);
        $message = __('Associazione rimossa. Il monitor è ora inattivo.', 'dokan-mod');
        $message_type = 'success';
    }
}

// Get vendor info
$vendor = dokan()->vendor->get($user_id);
$shop_name = $vendor->get_shop_name();
$monitor_url = get_user_meta($user_id, 'monitor_url', true);
$associated_post = $monitor_class->get_associated_post($user_id);

// Get pagination
if (get_query_var('paged')) {
    $paged = get_query_var('paged');
} elseif (get_query_var('page')) {
    $paged = get_query_var('page');
} else {
    $paged = 1;
}

// Query args for defunti (only this vendor's posts)
$args = array(
    'post_type' => 'annuncio-di-morte',
    'post_status' => 'publish,pending,draft,future,private',
    'author' => $user_id, // Only current vendor's posts
    'posts_per_page' => 10,
    'paged' => $paged,
    's' => get_query_var('s'), // Search support
    'orderby' => 'date',
    'order' => 'DESC'
);

// Execute the query
$query = new WP_Query($args);

// Includi l'header
get_header();

$active_menu = 'monitor-digitale';
?>

<main id="content" class="site-main post-58 page type-page status-publish hentry">

    <header class="page-header">
        <h1 class="entry-title"><?php _e('Monitor Digitale', 'dokan-mod'); ?></h1>
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
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h1 class="entry-title">
                            <i class="dashicons dashicons-desktop"></i>
                            <?php _e('Monitor Digitale', 'dokan-mod'); ?>
                        </h1>
                    </div>
                    
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="dokan-alert dokan-alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
                            <strong>
                                <?php if ($message_type === 'success'): ?>
                                    <i class="dashicons dashicons-yes"></i> <?php _e('Successo!', 'dokan-mod'); ?>
                                <?php else: ?>
                                    <i class="dashicons dashicons-warning"></i> <?php _e('Errore!', 'dokan-mod'); ?>
                                <?php endif; ?>
                            </strong>
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                </header>

                <div class="product-edit-new-container product-edit-container" style="margin-bottom: 100px">

                    <!-- Monitor Info Section -->
                    <div class="dokan-monitor-info" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #007cba;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0 0 10px 0;"><?php _e('Informazioni Monitor', 'dokan-mod'); ?></h3>
                                <p style="margin: 5px 0;"><strong><?php _e('Negozio:', 'dokan-mod'); ?></strong> <?php echo esc_html($shop_name); ?></p>
                                <p style="margin: 5px 0;"><strong><?php _e('URL Monitor:', 'dokan-mod'); ?></strong> 
                                    <code><?php echo home_url('/monitor/display/' . $user_id . '/' . $monitor_url); ?></code>
                                    <button type="button" class="custom-widget-button" onclick="copyMonitorUrl()" title="Copia URL" style="margin-left: 10px; padding: 2px 8px;">
                                        <i class="dashicons dashicons-admin-page"></i>
                                    </button>
                                </p>
                            </div>
                            <div>
                                <?php if ($associated_post): ?>
                                    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; text-align: center;">
                                        <strong><i class="dashicons dashicons-yes"></i> MONITOR ATTIVO</strong><br>
                                        <small>Defunto: <?php echo get_the_title($associated_post); ?></small>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; text-align: center;">
                                        <strong><i class="dashicons dashicons-warning"></i> MONITOR INATTIVO</strong><br>
                                        <small>Nessun defunto associato</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Search Form -->
                    <form method="get" action="<?php echo site_url('/dashboard/monitor-digitale'); ?>" style="display: flex; margin-bottom: 15px;">
                        <input type="text" name="s" value="<?php echo esc_attr(get_query_var('s')); ?>" 
                               placeholder="<?php _e('Cerca per nome defunto...', 'dokan-mod'); ?>" 
                               style="margin-right: 10px; flex: 1; max-width: 400px;">
                        <input type="submit" value="<?php _e('Cerca', 'dokan-mod'); ?>">
                        <?php if (get_query_var('s')): ?>
                            <a href="<?php echo site_url('/dashboard/monitor-digitale'); ?>" class="custom-widget-button" style="margin-left: 10px;">
                                <?php _e('Reset', 'dokan-mod'); ?>
                            </a>
                        <?php endif; ?>
                    </form>
                    <table>
                        <thead>
                        <tr>
                            <th><?php _e('Foto', 'dokan-mod'); ?></th>
                            <th><?php _e('Nome Defunto', 'dokan-mod'); ?></th>
                            <th><?php _e('Data Morte', 'dokan-mod'); ?></th>
                            <th><?php _e('Pubblicazione', 'dokan-mod'); ?></th>
                            <th><?php _e('Stato Monitor', 'dokan-mod'); ?></th>
                            <th><?php _e('Azioni', 'dokan-mod'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        if ($query->have_posts()) :
                            while ($query->have_posts()) : $query->the_post();
                                $post_id = get_the_ID();
                                $foto_defunto = get_field('foto_defunto', $post_id);
                                $data_morte = get_field('data_di_morte', $post_id);
                                $is_associated = ($post_id == $associated_post);
                                ?>
                                <tr <?php echo $is_associated ? 'style="background-color: #d4edda;"' : ''; ?>>
                                    <td>
                                        <?php if ($foto_defunto): ?>
                                            <img src="<?php echo esc_url($foto_defunto['sizes']['thumbnail']); ?>" 
                                                 alt="<?php echo esc_attr(get_the_title()); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                <i class="dashicons dashicons-admin-users" style="color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php the_title(); ?></strong>
                                        <?php if ($is_associated): ?>
                                            <br><small style="color: #155724;">
                                                <i class="dashicons dashicons-yes"></i> <?php _e('Sul monitor', 'dokan-mod'); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $data_morte ? date('d/m/Y', strtotime($data_morte)) : get_the_date('d/m/Y'); ?></td>
                                    <td><?php echo get_the_date('d/m/Y'); ?></td>
                                    <td>
                                        <?php if ($is_associated): ?>
                                            <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">ATTIVO</span>
                                        <?php else: ?>
                                            <span style="background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">INATTIVO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_associated): ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('<?php _e('Sei sicuro di voler rimuovere questo defunto dal monitor?', 'dokan-mod'); ?>');">
                                                <?php wp_nonce_field('monitor_remove_association', 'monitor_nonce'); ?>
                                                <input type="hidden" name="action" value="remove_association">
                                                <button type="submit" class="custom-widget-button" style="background: #dc3545; color: white;">
                                                    <i class="dashicons dashicons-no"></i> <?php _e('Rimuovi', 'dokan-mod'); ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('<?php _e('Sei sicuro di voler associare questo defunto al monitor?', 'dokan-mod'); ?>');">
                                                <?php wp_nonce_field('monitor_associate_defunto', 'monitor_nonce'); ?>
                                                <input type="hidden" name="action" value="associate_defunto">
                                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                                <button type="submit" class="custom-widget-button" style="background: #007cba; color: white;">
                                                    <i class="dashicons dashicons-yes"></i> <?php _e('Associa', 'dokan-mod'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        else :
                            ?>
                            <tr>
                                <td colspan="6">
                                    <?php if (get_query_var('s')): ?>
                                        <?php _e('Nessun annuncio trovato per la ricerca.', 'dokan-mod'); ?>
                                    <?php else: ?>
                                        <?php _e('Non hai ancora pubblicato annunci di morte.', 'dokan-mod'); ?>
                                        <br><br>
                                        <a href="<?php echo site_url('/dashboard/crea-annuncio'); ?>" class="custom-widget-button">
                                            <i class="dashicons dashicons-plus"></i> <?php _e('Crea il tuo primo annuncio', 'dokan-mod'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php
                        endif;
                        wp_reset_postdata();
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
                                'add_args' => get_query_var('s') ? array('s' => get_query_var('s')) : false,
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

                </div>

            </div><!-- .dokan-dashboard-content -->

        </div><!-- .dokan-dashboard-wrap -->

        <div class="post-tags">
        </div>
    </div>

</main>

<script>
// Monitor URL Copy Function
function copyMonitorUrl() {
    const url = '<?php echo home_url('/monitor/display/' . $user_id . '/' . $monitor_url); ?>';
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('URL copiato negli appunti!');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('URL copiato negli appunti!');
    }
}
</script>

<style>
    .dokan-btn {
        display: inline-block;
        padding: 8px 16px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        border-radius: 3px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 1px solid transparent;
        text-align: center;
        line-height: 1.4;
    }

    .dokan-btn-theme {
        background-color: #007cba;
        color: #ffffff;
        border-color: #007cba;
    }

    .dokan-btn-theme:hover {
        background-color: #005a85;
        border-color: #005a85;
        color: #ffffff;
    }

    .custom-widget-button {
        display: inline-block;
        padding: 8px 16px;
        background-color: #007cba;
        color: #ffffff;
        text-decoration: none;
        border-radius: 3px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .custom-widget-button:hover {
        background-color: #005a85;
        color: #ffffff;
        text-decoration: none;
    }

    .dokan-alert {
        padding: 12px 16px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .dokan-alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .dokan-alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    @media (max-width: 768px) {
        .dokan-monitor-info > div {
            flex-direction: column !important;
            text-align: center;
        }
        
        .dokan-monitor-info > div > div {
            margin-bottom: 15px;
        }
        
        table {
            font-size: 14px;
        }
        
        table th, 
        table td {
            padding: 8px 4px;
        }
    }
</style>

<?php
get_footer();
?>