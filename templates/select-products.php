<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */

// Assicurati che l'utente sia un vendor e abbia i permessi necessari
if ( ! current_user_can( 'dokan_view_product_menu' ) ) {
    wp_die( __( 'Non hai i permessi per visualizzare questa pagina', 'dokan' ) );
}

//get the list of all the products wc fitering it by role admin and category default-products
$args = array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'tax_query' => array(
        array(
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => 'default-products'
        )
    )
);

$products = get_posts($args);





// Includi l'header
get_header();

$active_menu = 'seleziona-prodotti';

// Include the Dokan dashboard sidebar

?>

    <main id="content" class="site-main post-58 page type-page status-publish hentry">

        <header class="page-header">
            <h1 class="entry-title"><?php __('Select Products','dokan') ?></h1></header>

        <div class="page-content">

            <div class="dokan-dashboard-wrap">

                  <?php
                    dokan_get_template_part('global/dashboard-nav', '', ['active_menu' => $active_menu]);
                    ?>

                <div class="dokan-dashboard-content dokan-product-edit">

                    <header class="dokan-dashboard-header dokan-clearfix">
                        <h1 class="entry-title">
                            <?php _e('Select Products', 'dokan'); ?> <span class="dokan-label  dokan-product-status-label">
                                            </span>
                        </h1>
                        <p><?php _e('Select the products you want to add to your store.', 'dokan'); ?></p>
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


                        <form class="dokan-product-edit-form" role="form" method="post" id="post">
                            <input type="hidden" name="selected_product_for_vendor" value="1">
                        <?php
                            //foreach product in the list generate a checkbox with the product title only
                            foreach ($products as $product) {
                                $product_id = $product->ID;
                                $product_name = $product->post_title;

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
                                if ($product_exist) {
                                    if ($product_exist[0]->post_status == 'pending') {
                                        $product_name .= __(' (Pending)', 'dokan');
                                    } else {
                                        $product_name .= __(' (Already Added)', 'dokan');
                                        $check = 'checked';

                                    }

                                }



                                ?>
                                <div class="dokan-form-group dokan-product-type-container checkbox-container">
                                    <input type="checkbox" id="product-<?php echo $product_id; ?>" name="product[]"
                                           style="width: 20px; height: 20px; margin-right: 10px;"
                                           value="<?php echo $product_id; ?>" <?php echo $check; ?> >
                                    <label for="product-<?php echo $product_id; ?>" style="font-size: 20px">
                                        <?php echo $product_name; ?>
                                    </label>
                                </div>
                                <?php
                            }
                            ?>
                            <input type="submit" value="<?php _e('Add Products', 'dokan'); ?>">
                        </form>
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
