jQuery(document).ready(function ($) {
    const searchForm = $('#vendor-search-form');
    const resultsContainer = $('#vendor-results');
    const loadingIndicator = $('#vendor-loading');
    const provinceSelect = $('#province-select');
    const citySelect = $('#city-select');

    // Initialize city select based on province
    provinceSelect.on('change', function () {
        const selectedProvince = $(this).val();
        citySelect.prop('disabled', true);
        citySelect.html('<option value="">Caricamento...</option>');

        if (!selectedProvince) {
            citySelect.html('<option value="">Seleziona Città</option>');
            citySelect.prop('disabled', true);
            return;
        }

        $.ajax({
            url: vendorSearchAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_comuni_by_provincia',
                province: selectedProvince,
                nonce: vendorSearchAjax.nonce
            },
            success: function (response) {
                const cities = JSON.parse(response);
                let options = '<option value="">Tutte le città</option>';

                cities.forEach(function (city) {
                    options += `<option value="${city}">${city}</option>`;
                });

                citySelect.html(options);
                citySelect.prop('disabled', false);
            },
            error: function () {
                citySelect.html('<option value="">Errore nel caricamento</option>');
                citySelect.prop('disabled', true);
            }
        });
    });

    // Handle search form submission
    searchForm.on('submit', function (e) {
        e.preventDefault();
        loadingIndicator.show();
        resultsContainer.empty();

        const formData = {
            action: 'search_vendors',
            nonce: vendorSearchAjax.nonce,
            vendor_name: $('#vendor-name').val(),
            province: provinceSelect.val(),
            city: citySelect.val()
        };

        $.ajax({
            url: vendorSearchAjax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success && response.data) {
                    if (response.data.length === 0) {
                        resultsContainer.html('<p class="no-results">Nessun risultato trovato</p>');
                    } else {
                        response.data.forEach(function (vendor) {
                            resultsContainer.append(createVendorCard(vendor));
                        });
                    }
                } else {
                    resultsContainer.html('<p class="error">Errore nella ricerca</p>');
                }
            },
            error: function () {
                resultsContainer.html('<p class="error">Errore nella connessione</p>');
            },
            complete: function () {
                loadingIndicator.hide();
            }
        });
    });

    function createVendorCard(vendor) {
        return `
            <div class="vendor-card">
                <div class="vendor-banner">
                    <img src="${vendor.banner_url}" alt="${vendor.store_name}">
                </div>
                <div class="vendor-info">
                    <h3>${vendor.store_name}</h3>
                    <p class="vendor-location">${vendor.city}</p>
                    <p class="vendor-address">${vendor.address}</p>
                    ${vendor.phone ? `<p class="vendor-phone">${vendor.phone}</p>` : ''}
                    <a href="${vendor.profile_url}" class="vendor-profile-link">Visualizza Profilo</a>
                </div>
            </div>
        `;
    }
});