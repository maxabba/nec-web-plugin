jQuery(document).ready(function($) {
    
    // City autocomplete functionality
    let searchTimeout;
    let selectedCitySlug = '';
    let selectedCityName = '';
    
    // City search with debouncing
    $('#new-city-search').on('input', function() {
        const $input = $(this);
        const searchTerm = $input.val().trim();
        const $results = $('#city-search-results');
        const $spinner = $('.search-spinner');
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide results if less than 2 characters
        if (searchTerm.length < 2) {
            $results.hide();
            $spinner.removeClass('loading');
            return;
        }
        
        // Show spinner
        $spinner.addClass('loading');
        
        // Debounce search
        searchTimeout = setTimeout(function() {
            performCitySearch(searchTerm);
        }, 300);
    });
    
    // Perform city search AJAX
    function performCitySearch(searchTerm) {
        const $results = $('#city-search-results');
        const $spinner = $('.search-spinner');
        
        $.ajax({
            url: dokanDeadlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_cities',
                nonce: dokanDeadlineAjax.nonce,
                search: searchTerm
            },
            success: function(response) {
                $spinner.removeClass('loading');
                
                if (response.success && response.data.length > 0) {
                    displayCityResults(response.data);
                } else {
                    displayNoResults();
                }
            },
            error: function(xhr, status, error) {
                $spinner.removeClass('loading');
                displayNoResults('Errore nella ricerca');
            }
        });
    }
    
    // Display city search results
    function displayCityResults(cities) {
        const $results = $('#city-search-results');
        $results.empty();
        
        cities.forEach(function(city) {
            const $item = $('<li></li>')
                .text(city.text)
                .data('city-id', city.id)
                .data('city-name', city.text);
            
            $results.append($item);
        });
        
        $results.show();
    }
    
    // Display no results message
    function displayNoResults(message = 'Nessuna città trovata') {
        const $results = $('#city-search-results');
        $results.empty();
        
        const $item = $('<li class="no-results"></li>').text(message);
        $results.append($item);
        $results.show();
    }
    
    // Handle city selection
    $(document).on('click', '#city-search-results li:not(.no-results)', function() {
        const $item = $(this);
        const cityId = $item.data('city-id');
        const cityName = $item.data('city-name');
        
        selectedCitySlug = cityId;
        selectedCityName = cityName;
        
        // Update UI
        $('#new-city-search').val(cityName);
        $('#new-city-slug').val(cityId);
        $('#city-search-results').hide();
        
        // Show selected city
        showSelectedCity(cityName, cityId);
    });
    
    // Show selected city with clear option
    function showSelectedCity(cityName, citySlug) {
        const $container = $('.city-search-container');
        
        // Remove existing selected city display
        $container.find('.selected-city').remove();
        
        // Add selected city display
        const $selectedDiv = $('<div class="selected-city"></div>')
            .html('<span class="city-name">' + cityName + '</span> <a href="#" class="clear-city">×</a>');
        
        $container.append($selectedDiv);
        
        // Hide input temporarily
        $('#new-city-search').hide();
    }
    
    // Clear selected city
    $(document).on('click', '.clear-city', function(e) {
        e.preventDefault();
        
        selectedCitySlug = '';
        selectedCityName = '';
        
        $('#new-city-search').val('').show();
        $('#new-city-slug').val('');
        $('.selected-city').remove();
        $('#city-search-results').hide();
    });
    
    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.city-search-container').length) {
            $('#city-search-results').hide();
        }
    });
    
    // Keyboard navigation for city results
    $(document).on('keydown', '#new-city-search', function(e) {
        const $results = $('#city-search-results');
        const $items = $results.find('li:not(.no-results)');
        const $selected = $items.filter('.selected');
        
        if (!$results.is(':visible') || $items.length === 0) {
            return;
        }
        
        switch(e.keyCode) {
            case 38: // Up arrow
                e.preventDefault();
                if ($selected.length === 0) {
                    $items.last().addClass('selected');
                } else {
                    $selected.removeClass('selected');
                    const $prev = $selected.prev();
                    if ($prev.length > 0 && !$prev.hasClass('no-results')) {
                        $prev.addClass('selected');
                    } else {
                        $items.last().addClass('selected');
                    }
                }
                break;
                
            case 40: // Down arrow
                e.preventDefault();
                if ($selected.length === 0) {
                    $items.first().addClass('selected');
                } else {
                    $selected.removeClass('selected');
                    const $next = $selected.next();
                    if ($next.length > 0 && !$next.hasClass('no-results')) {
                        $next.addClass('selected');
                    } else {
                        $items.first().addClass('selected');
                    }
                }
                break;
                
            case 13: // Enter
                e.preventDefault();
                if ($selected.length > 0) {
                    $selected.click();
                }
                break;
                
            case 27: // Escape
                e.preventDefault();
                $results.hide();
                break;
        }
    });
    
    // Save city deadline
    $(document).on('click', '.save-city', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $row = $button.closest('tr');
        const citySlug = $row.data('city');
        const fiori = $row.find('.fiori').val();
        const manifesti = $row.find('.manifesti').val();
        
        
        $button.text('Salvataggio...').prop('disabled', true);
        
        $.ajax({
            url: dokanDeadlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_purchase_deadline',
                nonce: dokanDeadlineAjax.nonce,
                city: citySlug,
                fiori: fiori,
                manifesti: manifesti
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Salvato!').addClass('button-success');
                    setTimeout(function() {
                        $button.text('Salva').removeClass('button-success').prop('disabled', false);
                    }, 2000);
                } else {
                    alert('Errore: ' + response.data);
                    $button.text('Salva').prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $button.text('Salva').prop('disabled', false);
            }
        });
    });
    
    // Delete city
    $(document).on('click', '.delete-city', function(e) {
        e.preventDefault();
        
        if (!confirm('Sei sicuro di voler eliminare questa città?')) {
            return;
        }
        
        const $button = $(this);
        const $row = $button.closest('tr');
        const citySlug = $row.data('city');
        
        $button.text('Eliminazione...').prop('disabled', true);
        
        $.ajax({
            url: dokanDeadlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_purchase_deadline',
                nonce: dokanDeadlineAjax.nonce,
                city: citySlug
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('#cities-table tr').length === 0) {
                            $('#cities-table').append(
                                '<tr><td colspan="4">Nessuna città configurata. Aggiungi la prima città usando il form sottostante.</td></tr>'
                            );
                        }
                    });
                } else {
                    alert('Errore: ' + response.data);
                    $button.text('Elimina').prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $button.text('Elimina').prop('disabled', false);
            }
        });
    });
    
    // Add new city
    $('#add-new-city').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const citySlug = $('#new-city-slug').val().trim();
        const cityName = selectedCityName || $('#new-city-search').val().trim();
        const fiori = $('#new-city-fiori').val();
        const manifesti = $('#new-city-manifesti').val();
        
        if (!citySlug || !cityName) {
            alert('Seleziona una città dalla ricerca');
            return;
        }
        
        $button.text('Aggiunta...').prop('disabled', true);
        
        $.ajax({
            url: dokanDeadlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_purchase_deadline',
                nonce: dokanDeadlineAjax.nonce,
                city: citySlug,
                fiori: fiori,
                manifesti: manifesti
            },
            success: function(response) {
                if (response.success) {
                    // Remove empty message if exists
                    $('#cities-table tr:contains("Nessuna città")').remove();
                    
                    // Add new row
                    const newRow = `
                        <tr data-city="${citySlug}">
                            <td>
                                <strong>${cityName}</strong>
                                <br><small>${citySlug}</small>
                            </td>
                            <td>
                                <input type="number" 
                                       class="deadline-input fiori" 
                                       value="${fiori}" 
                                       min="0" 
                                       step="0.5" 
                                       style="width: 100px">
                                <span class="description">ore</span>
                            </td>
                            <td>
                                <input type="number" 
                                       class="deadline-input manifesti" 
                                       value="${manifesti}" 
                                       min="0" 
                                       step="0.5" 
                                       style="width: 100px">
                                <span class="description">ore</span>
                            </td>
                            <td>
                                <button class="button save-city">Salva</button>
                                <button class="button delete-city">Elimina</button>
                            </td>
                        </tr>
                    `;
                    
                    $('#cities-table').append(newRow);
                    
                    // Reset form
                    $('#new-city-search').val('').show();
                    $('#new-city-slug').val('');
                    $('#new-city-fiori').val('4');
                    $('#new-city-manifesti').val('3');
                    $('.selected-city').remove();
                    selectedCitySlug = '';
                    selectedCityName = '';
                    
                    $button.text('Aggiunta!').addClass('button-success');
                    setTimeout(function() {
                        $button.text('Aggiungi Città').removeClass('button-success').prop('disabled', false);
                    }, 2000);
                } else {
                    alert('Errore: ' + response.data);
                    $button.text('Aggiungi Città').prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $button.text('Aggiungi Città').prop('disabled', false);
            }
        });
    });
    
    // Save default values
    $('#save-defaults').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const fiori = $('#default-fiori').val();
        const manifesti = $('#default-manifesti').val();
        
        $button.text('Salvataggio...').prop('disabled', true);
        
        $.ajax({
            url: dokanDeadlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_default_deadlines',
                nonce: dokanDeadlineAjax.nonce,
                fiori: fiori,
                manifesti: manifesti
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Salvato!').addClass('button-success');
                    setTimeout(function() {
                        $button.text('Salva Valori Default').removeClass('button-success').prop('disabled', false);
                    }, 2000);
                } else {
                    alert('Errore: ' + response.data);
                    $button.text('Salva Valori Default').prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $button.text('Salva Valori Default').prop('disabled', false);
            }
        });
    });
    
    // Flush all cache
    $('#flush-all-cache').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        $button.text('Svuotamento...').prop('disabled', true);
        
        // Trigger a save on defaults to flush cache
        $.ajax({
            url: dokanDeadlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_default_deadlines',
                nonce: dokanDeadlineAjax.nonce,
                fiori: $('#default-fiori').val(),
                manifesti: $('#default-manifesti').val()
            },
            success: function(response) {
                $button.text('Cache Svuotata!').addClass('button-success');
                setTimeout(function() {
                    $button.text('Svuota Tutta la Cache').removeClass('button-success').prop('disabled', false);
                }, 2000);
            }
        });
    });
    
    // Highlight changed inputs
    $(document).on('change', '.deadline-input', function() {
        $(this).addClass('changed');
    });
    
    // Remove highlight after save
    $(document).on('click', '.save-city', function() {
        const $row = $(this).closest('tr');
        setTimeout(function() {
            $row.find('.deadline-input').removeClass('changed');
        }, 2100);
    });
});