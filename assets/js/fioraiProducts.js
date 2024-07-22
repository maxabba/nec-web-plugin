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
    });
})(jQuery);