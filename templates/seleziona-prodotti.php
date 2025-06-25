<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

(new \Dokan_Mods\Templates_MiscClass())->check_dokan_can_and_message_login();

$user_id = get_current_user_id();
$store_info = dokan_get_store_info($user_id);
$user_city = $store_info['address']['city'] ?? '';

$FiltersClass = new Dokan_Mods\FiltersClass($user_city);

$args = $FiltersClass->get_arg_query_Select_product_form();
$products_not_editable = get_posts($args);

$args = $FiltersClass->get_arg_query_Select_product_editable();
$products_editable =  get_posts($args);

$disable_form = !dokan_is_user_seller($user_id) || !dokan_is_seller_enabled($user_id);

$currency_symbol = get_woocommerce_currency_symbol();

get_header();
$active_menu = 'seleziona-prodotti';

$RenderDokanSelectProducts = new Dokan_Mods\RenderDokanSelectProducts();

?>

<main id="content" class="site-main post-58 page type-page status-publish hentry">

    <header class="page-header">
        <h1 class="entry-title"><?php __('Aggiungi i servizi offerti', 'dokan-mod') ?></h1></header>

    <div class="page-content">

        <div class="dokan-dashboard-wrap">

            <?php dokan_get_template_part('global/dashboard-nav', '', ['active_menu' => $active_menu]); ?>

            <div class="dokan-dashboard-content dokan-product-edit">
                <?php do_action('dokan_dashboard_content_inside_before'); ?>
                <?php do_action('dokan_before_listing_product'); ?>

                <header class="dokan-dashboard-header dokan-clearfix">
                    <h1 class="entry-title"><?php _e('Aggiungi i servizi offerti', 'dokan-mod'); ?></h1>
                    <p><?php _e('Scegli quali servizi aggiungere dalla lista sottostante', 'dokan-mod'); ?></p>
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
                    <?php if (!$disable_form): ?>
                        <form class="dokan-product-edit-form" role="form" method="post"
                              action="<?php echo admin_url('admin-post.php'); ?>" id="post">
                            <input type="hidden" name="action" value="add_product_dokan_vendor">
                            <input type="hidden" name="selected_product_for_vendor" value="1">

                            <?php
                            // Buffer per raccogliere i prodotti non editabili
                            $non_editable_content = '';
                            foreach ($products_not_editable as $product) {
                                $row = $RenderDokanSelectProducts->render_product_row($product, $store_info, $user_city, $currency_symbol, $user_id);
                                if ($row) {
                                    $non_editable_content .= $row;
                                }
                            }

                            if ($non_editable_content): ?>
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th><?php _e('Prodotto', 'dokan-mod'); ?></th>
                                        <th><?php _e('Descrizione', 'dokan-mod'); ?></th>
                                        <th><?php _e('Prezzo', 'dokan-mod'); ?></th>
                                        <th><?php _e('Azione', 'dokan-mod'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php echo $non_editable_content; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <?php
                            // Buffer per raccogliere i prodotti editabili
                            $editable_content = '';
                            foreach ($products_editable as $product) {
                                $row = $RenderDokanSelectProducts->render_product_row($product, $store_info, $user_city, $currency_symbol, $user_id);
                                if ($row) {
                                    $editable_content .= $row;
                                }
                            }

                            if ($editable_content): ?>
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th><?php _e('Prodotto', 'dokan-mod'); ?></th>
                                        <th><?php _e('Descrizione', 'dokan-mod'); ?></th>
                                        <th><?php _e('Prezzo', 'dokan-mod'); ?></th>
                                        <th><?php _e('Azione', 'dokan-mod'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php echo $editable_content; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <?php
                            // Buffer per raccogliere i prodotti con variazioni
                            $variations_content = '';
                            foreach ($products_editable as $product) {
                                $row = $RenderDokanSelectProducts->render_product_row_with_variations($product, $store_info, $user_city, $currency_symbol, $user_id);
                                if ($row) {
                                    $variations_content .= $row;
                                }
                            }

                            if ($variations_content): ?>
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th><?php _e('Prodotto', 'dokan-mod'); ?></th>
                                        <th><?php _e('Descrizione', 'dokan-mod'); ?></th>
                                        <th><?php _e('Azione', 'dokan-mod'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php echo $variations_content; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <?php if ($non_editable_content || $editable_content || $variations_content): ?>
                                <input type="submit" value="<?php _e('Add Products', 'dokan-mod'); ?>"
                                       style="margin-top: 50px">
                            <?php else: ?>
                                <p><?php _e('Non ci sono servizzi attivabili per la tua agenzia, contatta il supporto.', 'dokan-mod'); ?></p>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <div style="display: flex; justify-content: center; align-items: center; height: 250px">
                            <i class="fas fa-ban" style="font-size: 100px; color: red;"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="post-tags"></div>
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
    jQuery(document).ready(function ($) {
        $('.remove-product-button').on('click', function (e) {
            e.preventDefault();
            var productId = $(this).data('product-id');

            // Creiamo un form nascosto per la submit
            var $form = $('<form>', {
                action: '<?php echo admin_url('admin-post.php'); ?>',
                method: 'POST'
            }).append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'remove_product_dokan_vendor'
            })).append($('<input>', {
                type: 'hidden',
                name: 'remove_product',
                value: 1
            })).append($('<input>', {
                type: 'hidden',
                name: 'product_id',
                value: productId
            }));

            // Appendiamo il form al body e facciamo submit
            $('body').append($form);
            $form.submit();
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
?>
