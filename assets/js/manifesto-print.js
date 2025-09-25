(function ($) {
    $(document).ready(function () {
        var totalPosts = 0;
        var loadedPosts = 0;
        var container = $('#hidden-container');
        var post_id = container.data('postid');
        var offset = 0;
        var loading = false;
        var pageFormat = 'A4'; // Default page format

        function updateProgressBar(percentage) {
            $('#progress-bar').css('width', percentage + '%');
        }

        function loadManifesti() {
            var imagePromises = []; // 1. Create an array to hold all the promises

            if (loading) return;
            loading = true;

            $.ajax({
                url: my_ajax_object.ajax_url,
                type: 'post',
                data: {
                    action: 'load_manifesti_print',
                    post_id: post_id,
                    offset: offset
                },
                success: function (response) {
                    if (!response.success || response.data.length === 0) {
                        $('#progress-bar-container').hide();
                        openPrintPopup();
                        return;
                    }

                    response.data.forEach(function (item) {
                        var newElement = $(item.html);
                        //find all occurrences of the class .custom-text-editor
                        container.append(newElement);
                        var imagePromise = new Promise(function (resolve) {
                            updateEditorBackground(item.vendor_data, newElement, resolve);
                        });
                        imagePromises.push(imagePromise);
                    });

                    loadedPosts += response.data.length;
                    offset += response.data.length;
                    updateProgressBar((loadedPosts / totalPosts) * 100);

                    loading = false;

                    if (loadedPosts < totalPosts) {
                        loadManifesti();
                    } else {
                        Promise.all(imagePromises).then(function () {
                            $('#progress-bar-container').hide();
                            openPrintPopup();
                        });
                    }
                },
                error: function () {
                    loading = false;
                }
            });
        }

        function getTotalPosts() {
            $.ajax({
                url: my_ajax_object.ajax_url,
                type: 'post',
                data: {
                    action: 'get_total_posts',
                    post_id: post_id,
                },
                success: function (response) {
                    if (response.success) {
                        totalPosts = response.data.total_posts;
                        $('#progress-bar-container').show();
                        loadManifesti();
                    }
                }
            });
        }

        function openPrintPopup() {
            // Assegna orientamento basato sulle dimensioni effettive dei manifesti
            container.find('.text-editor-background').each(function(index, element) {
                const width = $(element).outerWidth();
                const height = $(element).outerHeight();
                const orientation = width > height ? 'landscape' : 'portrait';
                $(element).attr('data-orientation', orientation);
            });
            
            var printContents = container.html();
            var printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print Manifesti</title>');
            printWindow.document.write('<script>document.addEventListener("DOMContentLoaded", function() { setTimeout(function() { window.print(); }, 2000); });<\/script>');
            
            // Stili di stampa con formato e orientamento automatici
            var printStyles = generatePrintStyles();
            printWindow.document.write('<style>body{font-family: Arial, sans-serif;} .text-editor-background{background-size: contain; background-position: center;}</style>');

            //add the ttf font to the print window
            printWindow.document.write('<style>@font-face {font-family: "PlayFair Display Mine"; src: url("' + my_ajax_object.plugin_url + 'assets/fonts/Playfair_Display/static/PlayfairDisplay-Regular.ttf") format("truetype");}</style>');
            printWindow.document.write('<link rel="stylesheet" type="text/css" href="' + my_ajax_object.plugin_url + 'assets/css/manifesto-print.css">');
            printWindow.document.write('<style>' + printStyles + '</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();

            //reload current page
            location.reload();
        }

        function addPageBreaks() {
            // Aggiungi solo la classe per la stampa, senza div aggiuntivi
            container.find('.text-editor-background').each(function(index, element) {
                $(element).addClass('print-page');
                // Determina orientamento specifico per ogni manifesto
                const width = $(element).outerWidth();
                const height = $(element).outerHeight();
                const orientation = width > height ? 'landscape' : 'portrait';
                $(element).attr('data-orientation', orientation);
            });
        }

        function generatePrintStyles() {
            const format = pageFormat.toLowerCase();
            
            return `
                @page {
                    size: ${format};
                    margin: 0;
                }
                
                @page landscape {
                    size: ${format} landscape;
                    margin: 0;
                }
                
                @page portrait {
                    size: ${format} portrait;
                    margin: 0;
                }
                
                .text-editor-background {
                    break-inside: avoid;
                    break-after: page;
                }
                
                .text-editor-background[data-orientation="landscape"] {
                    page: landscape;
                }
                
                .text-editor-background[data-orientation="portrait"] {
                    page: portrait;
                }
                
                .text-editor-background:last-child {
                    break-after: avoid;
                }
                
                @media print {
                    .text-editor-background[data-orientation="landscape"] {
                        max-width: 100%;
                        max-height: 100%;
                        width: auto;
                        height: auto;
                        background-repeat: no-repeat !important;
                        background-size: contain !important;
                        background-position: top center !important;
                        display: block !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                        position: relative !important;
                        top: 0 !important;
                        left: 0 !important;
                    }
                    
                    .text-editor-background[data-orientation="landscape"] .custom-text-editor {
                        width: 100% !important;
                        height: 100% !important;
                        margin: 0 !important;
                        box-sizing: border-box !important;
                        position: relative !important;
                        top: 0 !important;
                        left: 0 !important;
                    }
                    
                    .text-editor-background[data-orientation="portrait"] {
                        max-width: 100%;
                        max-height: 100%;
                        background-repeat: no-repeat !important;
                    }
                }
            `;
        }

        function detectOrientation() {
            let landscapeCount = 0;
            let portraitCount = 0;
            
            container.find('.text-editor-background').each(function() {
                const width = $(this).outerWidth();
                const height = $(this).outerHeight();
                
                if (width > height) {
                    landscapeCount++;
                } else {
                    portraitCount++;
                }
            });
            
            // Restituisce l'orientamento più comune
            return landscapeCount > portraitCount ? 'landscape' : 'portrait';
        }

        $('#start-button').click(function () {
            pageFormat = $('#page-format').val();
            // Sposta il div nascosto alla fine del body e rende visibile
            //
            $('body').append(container);
            // Blocca lo scroll
            $('body').addClass('no-scroll');
            //remove style display none
            container.css('display', 'block');
            getTotalPosts();
        });

        function updateEditorBackground(data, container, resolve) {
            const pageFormatDimensions = {
                'a5': {width: Math.round(148 * 3.78), height: Math.round(210 * 3.78)},
                'a4': {width: Math.round(210 * 3.78), height: Math.round(297 * 3.78)},
                'a3': {width: Math.round(297 * 3.78), height: Math.round(420 * 3.78)}
                // Add more formats as needed
            };
            const backgroundDiv = container.get(0);
            const textEditor = container.find('.custom-text-editor').get(0);

            // Rimuovi le classi di formato esistenti
            backgroundDiv.classList.remove('page-a3', 'page-a4', 'page-a5');

            // Aggiungi la classe di formato selezionata
            backgroundDiv.classList.add('page-' + pageFormat.toLowerCase());

            if (data.manifesto_background) {
                const img = new Image();
                img.src = data.manifesto_background;
                img.onload = function () {
                    const aspectRatio = img.width / img.height;
                    backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                    const dimensions = pageFormatDimensions[pageFormat.toLowerCase()];

                    if (aspectRatio > 1) {
                        // Landscape - la pagina orientata ha larghezza = altezza formato e altezza = larghezza formato
                        const landscapeMaxWidth = dimensions.height; // In landscape, la larghezza massima è l'altezza del formato
                        const landscapeMaxHeight = dimensions.width; // In landscape, l'altezza massima è la larghezza del formato
                        
                        // Calcola basato sull'altezza disponibile in landscape
                        const widthFromHeight = landscapeMaxHeight * aspectRatio;
                        
                        if (widthFromHeight <= landscapeMaxWidth) {
                            // Sta dentro i limiti
                            backgroundDiv.style.height = `${landscapeMaxHeight}px`;
                            backgroundDiv.style.width = `${widthFromHeight}px`;
                        } else {
                            // Troppo largo, usa la larghezza come limite
                            backgroundDiv.style.width = `${landscapeMaxWidth}px`;
                            backgroundDiv.style.height = `${landscapeMaxWidth / aspectRatio}px`;
                        }
                    } else {
                        // Portrait - manteniamo la logica che funziona
                        backgroundDiv.style.height = `${dimensions.height}px`;
                        backgroundDiv.style.width = `${dimensions.height * aspectRatio}px`;
                    }

                    // Timeout per assicurarsi che le dimensioni siano stabilizzate
                    setTimeout(() => {
                        // Calcola i margini in pixel basati sulla percentuale
                        const marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                        const marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                        const marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                        const marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                        // Applica i margini e l'allineamento
                        textEditor.style.paddingTop = `${marginTopPx}px`;
                        textEditor.style.paddingRight = `${marginRightPx}px`;
                        textEditor.style.paddingBottom = `${marginBottomPx}px`;
                        textEditor.style.paddingLeft = `${marginLeftPx}px`;
                        textEditor.style.textAlign = data.alignment ? data.alignment : 'left';

                        // Font size basato sull'orientamento del manifesto
                        if (aspectRatio > 1) {
                            // Landscape - usa la larghezza come riferimento
                            const actualWidth = backgroundDiv.clientWidth || parseFloat(backgroundDiv.style.width);
                            textEditor.style.fontSize = `${(actualWidth / 100) * 3.28}px`;
                        } else {
                            // Portrait - usa l'altezza come riferimento
                            const actualHeight = backgroundDiv.clientHeight || parseFloat(backgroundDiv.style.height);
                            textEditor.style.fontSize = `${(actualHeight / 100) * 3.28}px`;
                        }

                        resolve();
                    }, 50);

                }
            } else {
                backgroundDiv.style.backgroundImage = 'none';
                resolve(); // Resolve the promise immediately if there's no image

            }
        }
    });
})(jQuery);
