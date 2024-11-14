jQuery(document).ready(function ($) {
    function initSelect2() {
        $('.ajax-comune-search').select2({
            ajax: {
                url: mappaturaLive.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                        action: 'get_comuni',
                        _ajax_nonce: mappaturaLive.nonce
                    };
                },
                processResults: function (data) {
                    return {results: data};
                },
                cache: true
            },
            minimumInputLength: 2,
            width: '100%'
        });
    }

    // Initialize Select2 on page load
    $.when($.fn.select2).done(initSelect2);

    $('#add-row').on('click', function () {
        let newRow = `
            <tr class="repeater-row">
                <td><input type="text" name="portalid[]" /></td>
                <td>
                    <select name="comune[]" class="ajax-comune-search" style="width: 100%;">
                        <option value="">Seleziona un comune...</option>
                    </select>
                </td>
                <td>
                    <button class="button remove-row">Rimuovi</button>
                </td>
            </tr>
        `;
        $('#mappatura-live-table tbody').append(newRow);
        // Initialize Select2 on the new row
        initSelect2();
    });

    // Remove row
    $(document).on('click', '.remove-row', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    // Save mappatura
    $('#save-mappatura').on('click', function () {
        let mappings = [];
        $('#mappatura-live-table tbody tr').each(function () {
            let portalId = $(this).find('input[name="portalid[]"]').val();
            let comune = $(this).find('select[name="comune[]"]').val();
            if (portalId && comune) {
                mappings.push({portal_id: portalId, comune: comune});
            }
        });

        $.post(mappaturaLive.ajax_url, {
            action: 'save_mappatura',
            mappings: mappings,
            _ajax_nonce: mappaturaLive.nonce
        }, function (response) {
            alert(response.success ? 'Mappatura salvata con successo!' : 'Errore nel salvataggio');
        });
    });
});
