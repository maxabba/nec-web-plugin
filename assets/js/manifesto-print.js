(function ($) {
    $(document).ready(function () {
        var totalPosts = 0;
        var loadedPosts = 0;
        var container = $('#hidden-container');
        var post_id = container.data('postid');
        var loading = false;
        var pageFormat = 'A4'; // Default page format
        
        // Image cache with dimensions: url -> {image, width, height, aspectRatio}
        var imageCache = new Map();

        function updateProgressBar(percentage) {
            $('#progress-bar').css('width', percentage + '%');
        }

        // Analyze all background URLs and extract unique ones
        function analyzeBackgroundUrls(manifestiData) {
            var uniqueUrls = new Set();
            var urlManifestiMap = new Map(); // url -> array of manifesto indices
            
            manifestiData.forEach(function(item, index) {
                if (item.vendor_data && item.vendor_data.manifesto_background) {
                    var url = item.vendor_data.manifesto_background;
                    uniqueUrls.add(url);
                    
                    if (!urlManifestiMap.has(url)) {
                        urlManifestiMap.set(url, []);
                    }
                    urlManifestiMap.get(url).push(index);
                }
            });
            
            console.log('Analysis complete:', {
                totalManifesti: manifestiData.length,
                uniqueImages: uniqueUrls.size,
                urlMapping: Array.from(urlManifestiMap.entries()).map(([url, indices]) => ({
                    url: url.substring(url.lastIndexOf('/') + 1), // just filename for readability
                    manifestiCount: indices.length
                }))
            });
            
            return {
                uniqueUrls: Array.from(uniqueUrls),
                urlManifestiMap: urlManifestiMap
            };
        }

        // Pre-load unique images with dimensions caching
        function preloadUniqueImages(uniqueUrls) {
            var promises = uniqueUrls.map(function(url) {
                return new Promise(function(resolve, reject) {
                    // Check if already in cache
                    if (imageCache.has(url)) {
                        console.log('🟢 CACHE HIT for:', url.substring(url.lastIndexOf('/') + 1));
                        resolve(imageCache.get(url));
                        return;
                    }
                    
                    console.log('🔴 CACHE MISS - Loading:', url.substring(url.lastIndexOf('/') + 1));
                    
                    var img = new Image();
                    img.onload = function() {
                        var imageData = {
                            image: img,
                            width: img.width,
                            height: img.height,
                            aspectRatio: img.width / img.height
                        };
                        
                        imageCache.set(url, imageData);
                        console.log('✅ Cached:', url.substring(url.lastIndexOf('/') + 1), 
                                  `${img.width}x${img.height} (ratio: ${imageData.aspectRatio.toFixed(2)})`);
                        resolve(imageData);
                    };
                    
                    img.onerror = function() {
                        console.warn('❌ Failed to load:', url);
                        // Remove from cache if it was there (for retry logic)
                        imageCache.delete(url);
                        reject(new Error('Failed to load image: ' + url));
                    };
                    
                    img.src = url;
                });
            });
            
            return Promise.all(promises);
        }

        function loadManifesti() {
            if (loading) return;
            loading = true;

            $.ajax({
                url: my_ajax_object.ajax_url,
                type: 'post',
                data: {
                    action: 'load_manifesti_print',
                    post_id: post_id
                },
                success: function (response) {
                    if (!response.success) {
                        console.error('Error loading manifesti:', response);
                        alert('Errore nel caricamento dei manifesti.');
                        $('#progress-bar-container').hide();
                        location.reload();
                        return;
                    }
                    
                    if (response.data.length === 0) {
                        alert('Nessun manifesto pubblicato trovato per la stampa.');
                        $('#progress-bar-container').hide();
                        location.reload();
                        return;
                    }

                    console.log('Loaded manifesti data:', response.data.length);
                    loadedPosts = response.data.length;
                    
                    // Update totalPosts to match actual loaded for progress bar accuracy
                    if (loadedPosts !== totalPosts) {
                        console.warn('Loaded manifesti count differs from expected. Expected:', totalPosts, 'Loaded:', loadedPosts);
                        totalPosts = loadedPosts;
                    }

                    // Step 1: Analyze background URLs
                    const urlAnalysis = analyzeBackgroundUrls(response.data);
                    
                    // Step 2: Pre-load unique images with caching
                    preloadUniqueImages(urlAnalysis.uniqueUrls)
                        .then(function() {
                            console.log('✅ All unique images cached. Processing manifesti...');
                            
                            // Step 3: Process all manifesti using cached images
                            var processingPromises = [];
                            
                            response.data.forEach(function (item, index) {
                                var newElement = $(item.html);
                                container.append(newElement);
                                
                                var processingPromise = new Promise(function (resolve) {
                                    updateEditorBackground(item.vendor_data, newElement, resolve);
                                });
                                processingPromises.push(processingPromise);
                                
                                // Update progress bar for each manifesto processed (including cache hits)
                                updateProgressBar(((index + 1) / totalPosts) * 100);
                            });

                            loading = false;

                            // Wait for all manifesti processing to complete
                            Promise.all(processingPromises).then(function () {
                                console.log('✅ All manifesti processed. Opening print popup...');
                                $('#progress-bar-container').hide();
                                openPrintPopup();
                            });
                        })
                        .catch(function(error) {
                            console.error('Error pre-loading images:', error);
                            alert('Errore nel caricamento delle immagini di sfondo. Riprovare.');
                            $('#progress-bar-container').hide();
                            loading = false;
                            location.reload();
                        });
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error loading manifesti:', error, status);
                    alert('Errore di connessione nel caricamento dei manifesti. Riprovare.');
                    $('#progress-bar-container').hide();
                    loading = false;
                    location.reload();
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
                        console.log('Total manifesti to print:', totalPosts);
                        
                        if (totalPosts === 0) {
                            alert('Nessun manifesto pubblicato trovato per la stampa.');
                            $('#progress-bar-container').hide();
                            // Reload current page to reset state
                            location.reload();
                            return;
                        }
                        
                        $('#progress-bar-container').show();
                        loadManifesti();
                    } else {
                        alert('Errore nel recupero del numero totale di manifesti.');
                        console.error('Error getting total posts:', response);
                        location.reload();
                    }
                },
                error: function(xhr, status, error) {
                    alert('Errore di connessione nel recupero del totale manifesti.');
                    console.error('AJAX Error getting total posts:', error);
                    location.reload();
                }
            });
        }

        function openPrintPopup() {
            // L'attributo data-orientation è già stato impostato in updateEditorBackground
            // quindi non serve ricalcolarlo qui
            console.log('Opening print popup with pre-set orientations...');
            
            // Verifica e log degli orientamenti
            let landscapeCount = 0;
            let portraitCount = 0;
            
            container.find('.text-editor-background').each(function(index, element) {
                const orientation = $(element).attr('data-orientation');
                const bgImage = $(element).css('background-image');
                
                if (!orientation) {
                    console.warn(`Missing orientation for manifesto ${index}, defaulting to portrait`);
                    $(element).attr('data-orientation', 'portrait');
                    portraitCount++;
                } else {
                    console.log(`Manifesto ${index}: orientation=${orientation}, bg=${bgImage ? 'present' : 'missing'}`);
                    if (orientation === 'landscape') landscapeCount++;
                    else portraitCount++;
                }
            });
            
            console.log(`Print summary: ${landscapeCount} landscape, ${portraitCount} portrait manifesti`);
            
            // Prepara contenuti con wrapper per forzare orientamento
            var processedContents = '';
            var currentOrientation = null;
            var pageGroup = [];
            
            container.find('.text-editor-background').each(function(index, element) {
                const orientation = $(element).attr('data-orientation');
                const elementHtml = element.outerHTML;
                
                // Se cambia orientamento o è il primo elemento, crea nuovo gruppo
                if (orientation !== currentOrientation) {
                    // Chiudi gruppo precedente se esiste
                    if (pageGroup.length > 0) {
                        processedContents += '<div class="print-group-' + currentOrientation + '">' + pageGroup.join('') + '</div>';
                        pageGroup = [];
                    }
                    currentOrientation = orientation;
                }
                
                pageGroup.push(elementHtml);
            });
            
            // Chiudi ultimo gruppo
            if (pageGroup.length > 0) {
                processedContents += '<div class="print-group-' + currentOrientation + '">' + pageGroup.join('') + '</div>';
            }
            
            var printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print Manifesti</title>');
            printWindow.document.write('<script>document.addEventListener("DOMContentLoaded", function() { setTimeout(function() { window.print(); }, 2000); });<\/script>');
            
            // Stili di stampa con formato e orientamento automatici
            var printStyles = generatePrintStyles();
            printWindow.document.write('<style>body{font-family: Arial, sans-serif; margin: 0; padding: 0;}</style>');

            //add the ttf font to the print window
            printWindow.document.write('<style>@font-face {font-family: "PlayFair Display Mine"; src: url("' + my_ajax_object.plugin_url + 'assets/fonts/Playfair_Display/static/PlayfairDisplay-Regular.ttf") format("truetype");}</style>');
            printWindow.document.write('<link rel="stylesheet" type="text/css" href="' + my_ajax_object.plugin_url + 'assets/css/manifesto-print.css">');
            printWindow.document.write('<style>' + printStyles + '</style>');
            
            // Aggiungi stili specifici per forzare orientamento
            const format = pageFormat.toLowerCase();
            printWindow.document.write(`<style>
                /* Forza orientamento landscape per i manifesti orizzontali */
                @page landscape-page {
                    size: ${format} landscape !important;
                    margin: 0;
                }
                
                @page portrait-page {
                    size: ${format} portrait !important;
                    margin: 0;
                }
                
                @media print {
                    /* Applica page rules ai gruppi */
                    .print-group-landscape {
                        page: landscape-page;
                        page-break-before: always;
                    }
                    
                    .print-group-portrait {
                        page: portrait-page;
                        page-break-before: always;
                    }
                    
                    /* Forza ogni manifesto landscape a ruotare se necessario */
                    .text-editor-background[data-orientation="landscape"] {
                        size: landscape !important;
                        page-orientation: rotate-right !important;
                    }
                }
            </style>`);
            
            printWindow.document.write('</head><body>');
            printWindow.document.write(processedContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();

            //reload current page - DISABLED FOR DEBUGGING
            // location.reload();
        }


        function generatePrintStyles() {
            const format = pageFormat.toLowerCase();
            
            // Dimensioni fisiche per i formati carta (in mm)
            const formatDimensions = {
                'a5': {width: 148, height: 210},
                'a4': {width: 210, height: 297},
                'a3': {width: 297, height: 420}
            };
            
            const dims = formatDimensions[format] || formatDimensions['a4'];
            
            return `
                /* Configurazione base delle pagine con approccio aggressivo */
                @page {
                    size: ${format};
                    margin: 0;
                }
                
                @page landscape-forced {
                    size: ${format} landscape !important;
                    margin: 0;
                }
                
                @page portrait-forced {
                    size: ${format} portrait !important;
                    margin: 0;
                }
                
                /* Reset globale per la stampa */
                @media print {
                    * {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    
                    html, body {
                        width: 100% !important;
                        height: 100% !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    
                    /* APPROCCIO 1: Forza dimensioni viewport per landscape */
                    .text-editor-background[data-orientation="landscape"] {
                        page: landscape-forced !important;
                        page-break-after: always !important;
                        page-break-inside: avoid !important;
                        
                        /* Forza viewport landscape */
                        width: 100vw !important;
                        height: 100vh !important;
                        max-width: none !important;
                        max-height: none !important;
                        
                        /* Assicura contenimento */
                        position: relative !important;
                        overflow: hidden !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        
                        /* Background */
                        background-size: contain !important;
                        background-position: center center !important;
                        background-repeat: no-repeat !important;
                        
                        /* FALLBACK: se page non funziona, forza trasformazione */
                        transform-origin: center center !important;
                    }
                    
                    /* APPROCCIO 2: Dimensioni specifiche come fallback */
                    @supports not (page: landscape-forced) {
                        .text-editor-background[data-orientation="landscape"] {
                            width: ${dims.height}mm !important;
                            height: ${dims.width}mm !important;
                            transform: rotate(90deg) !important;
                            transform-origin: center center !important;
                        }
                    }
                    
                    /* Portrait normale */
                    .text-editor-background[data-orientation="portrait"] {
                        page: portrait-forced !important;
                        page-break-after: always !important;
                        page-break-inside: avoid !important;
                        
                        width: 100vw !important;
                        height: 100vh !important;
                        max-width: none !important;
                        max-height: none !important;
                        
                        position: relative !important;
                        overflow: hidden !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        
                        background-size: contain !important;
                        background-position: center center !important;
                        background-repeat: no-repeat !important;
                    }
                    
                    /* Text editor universale */
                    .text-editor-background .custom-text-editor {
                        width: 100% !important;
                        height: 100% !important;
                        margin: 0 !important;
                        box-sizing: border-box !important;
                        position: relative !important;
                    }
                    
                    /* Ultima pagina senza page break */
                    .text-editor-background:last-child {
                        page-break-after: avoid !important;
                    }
                }
            `;
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
            };
            const backgroundDiv = container.get(0);
            const textEditor = container.find('.custom-text-editor').get(0);

            // Remove existing format classes
            backgroundDiv.classList.remove('page-a3', 'page-a4', 'page-a5');
            // Add selected format class
            backgroundDiv.classList.add('page-' + pageFormat.toLowerCase());

            if (data.manifesto_background) {
                // Use cached image data instead of creating new Image
                const cachedImageData = imageCache.get(data.manifesto_background);
                
                if (!cachedImageData) {
                    console.error('Image not found in cache:', data.manifesto_background);
                    // Fallback: try to load image (should not happen if pre-loading worked)
                    resolve();
                    return;
                }
                
                // Use cached dimensions directly - no async loading needed
                const aspectRatio = cachedImageData.aspectRatio;
                const imageWidth = cachedImageData.width;
                const imageHeight = cachedImageData.height;
                
                // IMPORTANTE: Imposta l'orientamento qui, subito dopo aver ottenuto l'aspect ratio
                const orientation = aspectRatio > 1 ? 'landscape' : 'portrait';
                backgroundDiv.setAttribute('data-orientation', orientation);
                console.log(`Manifesto orientation set: ${orientation} (AR: ${aspectRatio.toFixed(2)})`);
                
                backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                const dimensions = pageFormatDimensions[pageFormat.toLowerCase()];
                
                // Dimensioni fisiche in mm per la stampa
                const mmDimensions = {
                    'a5': {width: 148, height: 210},
                    'a4': {width: 210, height: 297},
                    'a3': {width: 297, height: 420}
                };
                const mmDims = mmDimensions[pageFormat.toLowerCase()];

                if (aspectRatio > 1) {
                    // Landscape orientation - usa mm per la stampa
                    backgroundDiv.style.width = `${mmDims.height}mm`;
                    backgroundDiv.style.height = `${mmDims.width}mm`;
                    
                    // Mantieni anche dimensioni in pixel per compatibilità display
                    backgroundDiv.dataset.widthPx = dimensions.height;
                    backgroundDiv.dataset.heightPx = dimensions.width;
                } else {
                    // Portrait orientation - usa mm per la stampa
                    backgroundDiv.style.width = `${mmDims.width}mm`;
                    backgroundDiv.style.height = `${mmDims.height}mm`;
                    
                    // Mantieni anche dimensioni in pixel per compatibilità display
                    backgroundDiv.dataset.widthPx = dimensions.width;
                    backgroundDiv.dataset.heightPx = dimensions.height;
                }

                // Small timeout to ensure dimensions are applied
                setTimeout(() => {
                    // Calculate margins using cached dimensions
                    const marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                    const marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                    const marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientWidth;
                    const marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                    // Apply styling
                    textEditor.style.paddingTop = `${marginTopPx}px`;
                    textEditor.style.paddingRight = `${marginRightPx}px`;
                    textEditor.style.paddingBottom = `${marginBottomPx}px`;
                    textEditor.style.paddingLeft = `${marginLeftPx}px`;
                    textEditor.style.textAlign = data.alignment || 'left';

                    // Fixed font-size based on cached aspect ratio
                    const fontSize = aspectRatio > 1 ? '8cqh' : '4cqh';
                    textEditor.style.fontSize = fontSize;
                    textEditor.style.lineHeight = '1.2';

                    resolve();
                }, 10); // Reduced timeout since no image loading
            } else {
                backgroundDiv.style.backgroundImage = 'none';
                resolve();
            }
        }
    });
})(jQuery);
