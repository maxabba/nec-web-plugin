(function ($) {
    $(document).ready(function () {
        window.setProductID = function (productID) {
            $('.variant-product-container').addClass('hide');
            $('#comments-loader').show();
            $.ajax({
                url: my_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_variant_product_ajax_card',
                    product_id: productID,
                },
                success: function (response) {
                    if (response.success) {
                        $('#comments-loader').hide();
                        $('.inner_variant_product').html(response.data);
                        $('.variant-product-container').removeClass('hide');
                    } else {
                        alert('Errore nel caricamento dei dati del venditore: ' + response.data);
                    }
                },
                error: function (error) {
                    console.log('Error: ', error);
                }
            });

        }

        // Validazione submit form - verifica selezione variante
        $('.variant-container form').on('submit', function (e) {
            var selectedVariant = $('input[name="product_variation_id"]:checked').length;

            if (selectedVariant === 0) {
                e.preventDefault();
                alert('Per favore, seleziona una variante prima di continuare con l\'ordine.');
                return false;
            }

            return true;
        });
    });
})(jQuery);