function loadCity($,element, data = null) {

    //console.log('loadCity');
    var selectedValue = "";

    if(data) {
        city = data.city;
        province = data.province;
        if (province) {
            $(element).val(province);
        }
    }
    selectedValue = $(element).val();


    //console.log(selectedValue);
    $.ajax({
        url: ajax_object.ajax_url,
        type: 'POST',
        data: {
            action: 'update_acf_field',
            value: selectedValue
        },
        beforeSend: function () {
            //show the loader
            $('#acf-field_662ca58a35da3').empty();
            $('#acf-field_662ca58a35da3').append('<option value="">Caricando...</option>');
        },
        success: function (response) {
            var data = JSON.parse(response);
            //the response is a json object
            //it contains the new options for the select field
            //we need to replace the options of the select field with the new options

            //$('#acf-field_662ca58a35da3')
            //clear the select options
            $('#acf-field_662ca58a35da3').empty();
            //add the new options
            $('#acf-field_662ca58a35da3').append('<option value="Tutte">Tutte</option>');
            $.each(data, function (index, value) {
                if (city === value) {
                    $('#acf-field_662ca58a35da3').append('<option value="' + value + '" selected>' + value + '</option>');
                } else
                    $('#acf-field_662ca58a35da3').append('<option value="' + value + '">' + value + '</option>');

            });
        }
    });
};


function get_city_if_is_set($,element) {
    //console.log('get_city_if_is_set');

    // Use REST API GET endpoint (cacheable)
    var restUrl = '/wp-json/dokan-mod/v1/city/' + ajax_object.post_id;

    $.ajax({
        url: restUrl,
        type: 'GET',
        cache: true, // Enable browser cache for GET requests
        beforeSend: function () {
            //show the loader
            $('#acf-field_662ca58a35da3').empty();
            $('#acf-field_662ca58a35da3').append('<option value="">Caricando...</option>');
        },
        success: function (response) {
            // REST API returns data directly, no need to parse
            var data = response;

            //set city value as the data response value
            loadCity($, element, data);
        },
        error: function() {
            // Fallback to POST AJAX if REST fails (backward compatibility)
            $.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_current_citta_value_if_is_set',
                    post_id: ajax_object.post_id
                },
                success: function (response) {
                    var data = JSON.parse(response);
                    loadCity($, element, data);
                }
            });
        }
    });
}

jQuery('#acf-field_6638e3e77ffa0').ready(function ($) {
    // Always execute - PHP backend will handle admin logic
    get_city_if_is_set($, jQuery('#acf-field_6638e3e77ffa0'));
});

jQuery(document).ready(function ($) {
    // Always allow manual province changes to update city dropdown
    $('#acf-field_6638e3e77ffa0').change(function () {
        loadCity($,this);
    });
});
