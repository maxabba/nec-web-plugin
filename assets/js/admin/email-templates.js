/**
 * Script per la gestione dei template email
 */
jQuery(document).ready(function ($) {
    // Inserisce i placeholder nel campo di testo
    $('.placeholder-item').on('click', function () {
        const placeholder = $(this).data('placeholder');
        const editor = tinyMCE.get('email_content');

        if (editor) {
            editor.execCommand('mceInsertContent', false, placeholder);
        } else {
            // Fallback se l'editor non è inizializzato
            const textArea = $('#email_content');
            const cursorPos = textArea.prop('selectionStart');
            const v = textArea.val();
            const textBefore = v.substring(0, cursorPos);
            const textAfter = v.substring(cursorPos, v.length);

            textArea.val(textBefore + placeholder + textAfter);
        }
    });

    // Funzione per visualizzare l'anteprima dell'email
    $('.preview-button').on('click', function (e) {
        e.preventDefault();

        let content = '';
        const editor = tinyMCE.get('email_content');

        if (editor) {
            content = editor.getContent();
        } else {
            content = $('#email_content').val();
        }

        // Sostituisce i placeholder con valori di esempio per l'anteprima
        content = content.replace(/{name}/g, 'Mario Rossi');
        content = content.replace(/{email}/g, 'mario.rossi@example.com');
        content = content.replace(/{site_name}/g, 'Necrologiweb');
        content = content.replace(/{amount}/g, '120,00');
        content = content.replace(/{dashboard_url}/g, '#');

        $('.email-preview-content').html(content);
        $('.email-preview').show();

        // Scroll alla preview
        $('html, body').animate({
            scrollTop: $('.email-preview').offset().top - 50
        }, 500);
    });

    // Gestione del reset template
    $('a.reset-template').on('click', function (e) {
        return confirm('Sei sicuro di voler ripristinare il template predefinito? Tutte le modifiche andranno perse.');
    });

    // Inizializza l'interfaccia delle tab
    function initTabs() {
        // Se c'è un hash nell'URL, attiva la tab corrispondente
        let hash = window.location.hash;
        if (hash) {
            hash = hash.replace('#', '');
            $('.nav-tab-wrapper a[href="#' + hash + '"]').addClass('nav-tab-active');
            $('.tab-content > div').hide();
            $('.tab-content > div#' + hash).show();
        }

        // Gestione dei click sulle tab
        $('.nav-tab-wrapper a').on('click', function (e) {
            const target = $(this).attr('href').replace('#', '');

            if (target !== 'undefined') {
                e.preventDefault();

                // Attiva la tab
                $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Mostra il contenuto
                $('.tab-content > div').hide();
                $('.tab-content > div#' + target).show();

                // Aggiorna l'URL
                window.location.hash = target;
            }
        });
    }

    // Nel tab di test, gestisci i campi per la selezione dell'utente
    if ($('.email-test-form').length) {
        $('#user-select').on('change', function () {
            if ($(this).val()) {
                $('#user-email').prop('disabled', true);
            } else {
                $('#user-email').prop('disabled', false);
            }
        });

        $('#user-email').on('input', function () {
            if ($(this).val()) {
                $('#user-select').prop('disabled', true);
            } else {
                $('#user-select').prop('disabled', false);
            }
        });
    }
});