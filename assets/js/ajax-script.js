function loadCity($,element) {

    console.log('change');
    var selectedValue = $(element).val();
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
                $('#acf-field_662ca58a35da3').append('<option value="' + value + '">' + value + '</option>');
            });
        }
    });
};



jQuery(document).ready(function ($) {
    $('#acf-field_6638e3e77ffa0').ready(loadCity($,this));
    $('#acf-field_6638e3e77ffa0').change(function () {
        loadCity($,this);
    });
});
