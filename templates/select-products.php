<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

// Assicurati che l'utente sia un vendor e abbia i permessi necessari
if ( ! current_user_can( 'dokan_view_product_menu' ) ) {
    wp_die( __( 'Non hai i permessi per visualizzare questa pagina', 'dokan' ) );
}

//get the list of all the products wc fitering it by user creator admin and both category default-products and sub category editable-price
$args = array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'tax_query' => array(
        'relation' => 'OR',
        array(
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => 'default-products'
        ),
        array(
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => 'editable-price',
            'operator' => 'IN'
        )
    )
);
$products = get_posts($args);

$user_id = get_current_user_id();

//check if vendor status is enabled
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

// get the currency symbol of the store
$currency_symbol = get_woocommerce_currency_symbol();


// Includi l'header
get_header();

$active_menu = 'seleziona-prodotti';

// Include the Dokan dashboard sidebar

?>

    <main id="content" class="site-main post-58 page type-page status-publish hentry">

        <header class="page-header">
            <h1 class="entry-title"><?php __('Aggiungi i servizi offerti','dokan') ?></h1></header>

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
                            <?php _e('Aggiungi i servizi offerti', 'dokan'); ?> <span class="dokan-label  dokan-product-status-label">
                                            </span>
                        </h1>
                        <p><?php _e('Scegli quali servizi aggiungere dalla lista sottostante', 'dokan'); ?></p>
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

                    <div class="product-edit-new-container product-edit-container">

                    <!-- if the vendor status is enabled show the form -->
                    <?php if (!$disable_form) { ?>
                        <form class="dokan-product-edit-form" role="form" method="post" id="post">
                            <input type="hidden" name="selected_product_for_vendor" value="1">
                        <?php
                            //foreach product in the list generate a checkbox with the product title only
                            foreach ($products as $product) {
                                $product_id = $product->ID;
                                $product_name = $product->post_title;
                                $product_wc = wc_get_product($product_id);

                                // Get the price of the product in decimal format
                                $price = $product_wc->get_price();
                                $price = number_format($price, 2, '.', '');


                                //get if exist a product by sky composed $product_id . '-' . $user_id and status pending
                                $sku = $product_id . '-' . get_current_user_id();
                                $args = array(
                                    'post_type' => 'product',
                                    'post_status' => 'any',
                                    'posts_per_page' => 1,
                                    'meta_query' => array(
                                        array(
                                            'key' => '_sku',
                                            'value' => $sku
                                        )
                                    )
                                );
                                $product_exist = get_posts($args);

                                $check = '';
                                $disabled = '';
                                if ($product_exist) {
                                    if ($product_exist[0]->post_status == 'pending') {
                                        $product_name .= __(' (Pending)', 'dokan');
                                        $disabled = 'disabled';
                                    } else {
                                        $product_name .= __(' (Already Added)', 'dokan');
                                        $check = 'checked';

                                    }

                                }



                                ?>
                                    <!-- add header with the product name -->
                                    <h2><?php echo $product_name; ?></h2>
                                <!-- print the description of the product if is present-->
                                <?php
                                $product_description = $product->post_content;
                                if (!empty($product_description)) {
                                    ?>
                                    <p><strong><?php  _e('Descrizione del servizio:','dokan'); ?></strong> <?php echo $product_description; ?></p>
                                    <?php
                                }
                                ?>
                                <div class="dokan-form-group dokan-product-type-container checkbox-container">
                                    <input type="checkbox" id="product-<?php echo $product_id; ?>" name="product[]"
                                           style="width: 20px; height: 20px; margin-right: 10px;"
                                           value="<?php echo $product_id; ?>" <?php echo $check; ?> <?php echo $disabled; ?> >
                                    <label for="product-<?php echo $product_id; ?>" style="font-size: 20px">
                                        <?php _e('Aggiungi alla lista dei servizi', 'dokan'); ?>
                                    </label>
                                </div>
                                <?php
                                // if the product has the  category editable-price show the price input
                                $terms = get_the_terms($product_id, 'product_cat');
                                $terms_slug = array_map(function ($term) {
                                    return $term->slug;
                                }, $terms);
                                if (in_array('editable-price', $terms_slug)) {

                                    ?>
                                    <div class="dokan-form-group dokan-product-type-container">
                                        <label for="product-<?php echo $product_id; ?>-price">Prezzo: <?php echo $currency_symbol; ?></label>
                                        <input type="number" id="product-<?php echo $product_id; ?>-price" name="product_price[<?php echo $product_id; ?>]" step="0.01" min="0" required value="<?php echo $price ?>">
                                    </div>

                                    <!-- add separator line -->
                                    <hr style="border: 1px solid #f1f1f1; margin: 20px 0;">
                                    <?php
                                }else{
                                    // print the price of the product
                                    ?>
                                    <p><strong><?php  _e('Prezzo del servizio:','dokan'); ?></strong><?php echo $currency_symbol; ?> <?php echo $price; ?></p>
                                    <?php
                                }


                            }
                            ?>
                            <input type="submit" value="<?php _e('Add Products', 'dokan'); ?>">
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
