<?php
namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\RenderDokanSelectProducts')) {
    class RenderDokanSelectProducts
    {

        function user_has_role($user_id, $role)
        {
            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }
            return in_array($role, (array)$user->roles);
        }

        public function render_product_row($product, $store_info, $user_city, $currency_symbol, $user_id)
        {
            $product_id = $product->ID;
            $product_name = $product->post_title;
            $product_wc = wc_get_product($product_id);
            $price = number_format($product_wc->get_price(), 2, '.', '');
            $sku = $product_id . '-' . $user_id;
            $product_description = $product->post_content;

            if ($product_wc->is_type('variable')) {
                return '';
            }

            // Caching the result of the product existence check
            $product_exist = wp_cache_get($sku, 'product_exist');
            if (false === $product_exist) {
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
                wp_cache_set($sku, $product_exist, 'product_exist');
            }

            $categoria_finale = get_field('categoria_finale', $product_id);

            if (str_contains($categoria_finale, 'Manifesto') && (!$this->user_has_role($user_id, 'seller') || $this->user_has_role($user_id, 'fiorai'))) {
                return null;
            }

            if (in_array($categoria_finale,['Composizione','Bouquet','Cuscino']) && (!$this->user_has_role($user_id, 'fiorai') && !$this->user_has_role($user_id, 'seller'))) {
                return null;
            }

            $check = '';
            $disabled = '';
            $product_exist_id = null;
            if ($product_exist) {
                $product_wc = wc_get_product($product_exist[0]->ID);
                $price = $product_wc->get_price();
                $product_description = $product_exist[0]->post_content;
                $product_exist_id = $product_exist[0]->ID;

                if ($product_exist[0]->post_status == 'pending') {
                    $product_name .= __(' (Pending)', 'dokan-mod');
                    $disabled = 'disabled';
                } else {
                    $product_name .= __(' (Already Added)', 'dokan-mod');
                    $check = 'checked';
                    $disabled = 'disabled';
                }
            }

            $terms = get_the_terms($product_id, 'product_cat');
            $terms_slug = array_map(function ($term) {
                return $term->slug;
            }, $terms);

            // Checking the conditions for rendering
            $is_editable_price = in_array('editable-price', $terms_slug);

            ob_start();
            ?>
            <tr>
                <td><?php echo $product_name; ?></td>
                <td>
                    <?php if (!empty($product_description) && !$is_editable_price): ?>
                        <strong><?php _e('Descrizione del servizio:', 'dokan-mod'); ?></strong> <?php echo $product_description; ?>
                    <?php endif; ?>

                    <?php if ($is_editable_price): ?>
                        <textarea id="product-<?php echo $product_id; ?>-description"
                                  name="product_description[<?php echo $product_id; ?>]" rows="4" cols="50"
                                  placeholder="Inserisci una descrizione per il servizio" <?php echo $disabled; ?>><?php echo $product_description ?? ""; ?></textarea>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_editable_price): ?>
                        <div class="dokan-form-group dokan-product-type-container">
                            <label
                                for="product-<?php echo $product_id; ?>-price">Prezzo: <?php echo $currency_symbol; ?></label>
                            <input type="number" id="product-<?php echo $product_id; ?>-price"
                                   name="product_price[<?php echo $product_id; ?>]" step="0.01" min="0"
                                   required value="<?php echo $price ?>" <?php echo $disabled; ?>>
                        </div>
                    <?php else: ?>
                        <strong><?php _e('Prezzo del servizio:', 'dokan-mod'); ?></strong><?php echo $currency_symbol; ?><?php echo $price; ?>
                        <p>Per questo servizio non è prevista la modifica del prezzo, per richiedere informazioni o
                            modifiche
                            utilizza l'apposito modulo di contatto</p>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="dokan-form-group dokan-product-type-container checkbox-container">
                        <input type="checkbox" id="product-<?php echo $product_id; ?>" name="product[]"
                               style="width: 20px; height: 20px; margin-right: 10px;"
                               value="<?php echo $product_id; ?>" <?php echo $check; ?> <?php echo $disabled; ?>>
                        <label for="product-<?php echo $product_id; ?>">
                            <?php _e('Aggiungi alla lista dei servizi', 'dokan-mod'); ?>
                        </label>
                    </div>
                    <?php if ($product_exist): ?>
                        <button class="remove-product-button" data-product-id="<?php echo $product_exist_id; ?>"
                                style="margin-top: 10px;">
                            <?php _e('Rimuovi ' . $product_name, 'dokan-mod'); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        public function render_product_row_with_variations($product, $store_info, $user_city, $currency_symbol, $user_id)
        {
            $product_id = $product->ID;
            $product_name = $product->post_title;
            $product_wc = wc_get_product($product_id);

            // Verifica che il prodotto sia di tipo variabile
            if (!$product_wc->is_type('variable')) {
                return '';
            }
            $wc_price = $product_wc->get_price();
            if ($wc_price) {
                $price = number_format($product_wc->get_price(), 2, '.', '');
            }else{
                $price = 0;
            }
            $sku = $product_id . '-' . $user_id;
            $product_description = wp_strip_all_tags($product->post_content);

            $product_exist = wp_cache_get($sku, 'product_exist');
            if (false === $product_exist) {
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
                wp_cache_set($sku, $product_exist, 'product_exist');
            }

            $categoria_finale = get_field('categoria_finale', $product_id);

            if (str_contains($categoria_finale, 'Manifesto') && (!$this->user_has_role($user_id, 'seller') || $this->user_has_role($user_id, 'fiorai'))) {
                return null;
            }

            if (in_array($categoria_finale, ['Composizione', 'Bouquet', 'Cuscino']) && (!$this->user_has_role($user_id, 'fiorai') && !$this->user_has_role($user_id, 'seller'))) {
                return null;
            }

            $check = '';
            $disabled = '';
            $product_exist_id = null;
            if ($product_exist) {
                $product_wc = wc_get_product($product_exist[0]->ID);
                $price = $product_wc->get_price();
                $product_description = wp_strip_all_tags($product_exist[0]->post_content);
                $product_exist_id = $product_exist[0]->ID;

                if ($product_exist[0]->post_status == 'pending') {
                    $product_name .= __(' (Pending)', 'dokan-mod');
                    $disabled = 'disabled';
                } else {
                    $product_name .= __(' (Already Added)', 'dokan-mod');
                    $check = 'checked';
                    $disabled = 'disabled';
                }
            }

            ob_start();
            ?>
            <tr>
                <td><?php echo $product_name; ?></td>
                <td>
            <textarea id="product-<?php echo $product_id; ?>-description"
                      name="product_description[<?php echo $product_id; ?>]" rows="4" cols="50"
                      placeholder="Inserisci una descrizione per il servizio" <?php echo $disabled; ?>><?php echo $product_description ?? ""; ?></textarea>
                </td>

                <td>
                    <div class="dokan-form-group dokan-product-type-container checkbox-container">
                        <input type="checkbox" id="product-<?php echo $product_id; ?>" name="product[]"
                               style="width: 20px; height: 20px; margin-right: 10px;"
                               value="<?php echo $product_id; ?>" <?php echo $check; ?> <?php echo $disabled; ?>>
                        <label for="product-<?php echo $product_id; ?>">
                            <?php _e('Aggiungi alla lista dei servizi', 'dokan-mod'); ?>
                        </label>
                    </div>
                    <?php if ($product_exist): ?>
                        <button class="remove-product-button" data-product-id="<?php echo $product_exist_id; ?>"
                                style="margin-top: 10px;">
                            <?php _e('Rimuovi ' . $product_name, 'dokan-mod'); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php $variations = $product_wc->get_available_variations(); ?>
            <tr class="product-variations" style="display:none;">
                <td colspan="4">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th><?php _e('Variante', 'dokan-mod'); ?></th>
                            <th><?php _e('Descrizione', 'dokan-mod'); ?></th>
                            <th><?php _e('Prezzo', 'dokan-mod'); ?></th>
                            <th><?php _e('Immagine', 'dokan-mod'); ?></th>
                            <th><?php _e('Abilita', 'dokan-mod'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($variations as $variation): ?>
                            <?php $variation_id = $variation['variation_id']; ?>
                            <?php $variation_wc = wc_get_product($variation_id); ?>
                            <tr>
                                <td><?php echo implode(', ', $variation_wc->get_attributes()); ?></td>
                                <td>
                            <textarea id="variation-<?php echo $variation_id; ?>-description"
                                      name="variation_description[<?php echo $variation_id; ?>]" rows="4" cols="50"
                                      placeholder="Inserisci una descrizione per la variante"><?php echo $variation_wc->get_description(); ?></textarea>
                                </td>
                                <td>
                                    <div class="dokan-form-group dokan-product-type-container">
                                        <label for="variation-<?php echo $variation_id; ?>-price">Prezzo: <?php echo $currency_symbol; ?></label>
                                        <input type="number" id="variation-<?php echo $variation_id; ?>-price"
                                               name="variation_price[<?php echo $variation_id; ?>]" step="0.01" min="0"
                                               value="<?php echo $variation_wc->get_price(); ?>">
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="upload_image_button"
                                            data-variation-id="<?php echo $variation_id; ?>">
                                        <?php _e('Seleziona Immagine', 'dokan-mod'); ?>
                                    </button>
                                    <input type="hidden" id="variation-<?php echo $variation_id; ?>-image"
                                           name="variation_image[<?php echo $variation_id; ?>]"
                                           value="<?php echo $variation_wc->get_image_id(); ?>">
                                    <img id="variation-<?php echo $variation_id; ?>-image-preview"
                                         src="<?php echo wp_get_attachment_url($variation_wc->get_image_id()); ?>"
                                         style="max-width:100px; max-height:100px; display:block; margin-top:10px;">
                                </td>
                                <td>
                                    <input type="checkbox" id="enable-variation-<?php echo $variation_id; ?>"
                                           name="enabled_variations[]" value="<?php echo $variation_id; ?>">
                                    <label for="enable-variation-<?php echo $variation_id; ?>"><?php _e('Abilita', 'dokan-mod'); ?></label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </td>
            </tr>
            <script>
                jQuery(document).ready(function ($) {
                    $('#product-<?php echo $product_id; ?>').on('change', function () {
                        if ($(this).is(':checked')) {
                            $(this).closest('tr').next('.product-variations').show();
                        } else {
                            $(this).closest('tr').next('.product-variations').hide();
                        }
                    });

                    $('.upload_image_button').on('click', function (e) {
                        e.preventDefault();
                        var button = $(this);
                        var variation_id = button.data('variation-id');
                        var image = wp.media({
                            title: 'Seleziona Immagine',
                            multiple: false
                        }).open()
                            .on('select', function () {
                                var uploaded_image = image.state().get('selection').first();
                                var image_url = uploaded_image.toJSON().url;
                                var image_id = uploaded_image.toJSON().id;
                                $('#variation-' + variation_id + '-image').val(image_id);
                                $('#variation-' + variation_id + '-image-preview').attr('src', image_url).show();
                            });
                    });
                });
            </script>
            <?php
            return ob_get_clean();
        }


    }
}