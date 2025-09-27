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
            // Assegna orientamento basato sull'aspect ratio delle immagini (non delle dimensioni DOM)
            container.find('.text-editor-background').each(function(index, element) {
                const postId = $(element).data('postid');
                const vendorId = $(element).data('vendorid');
                
                // Trova l'aspect ratio dall'immagine di background utilizzando la cache
                let aspectRatio = 1;
                const bgImage = $(element).css('background-image');
                if (bgImage && bgImage !== 'none') {
                    const urlMatch = bgImage.match(/url\(['"]?([^'")]+)['"]?\)/);
                    if (urlMatch && urlMatch[1]) {
                        const cachedData = imageCache.get(urlMatch[1]);
                        if (cachedData) {
                            aspectRatio = cachedData.aspectRatio;
                        }
                    }
                }
                
                const orientation = aspectRatio > 1 ? 'landscape' : 'portrait';
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
                    page-orientation: landscape;
                }
                
                .text-editor-background[data-orientation="portrait"] {
                    page: portrait;
                    page-orientation: portrait;
                }
                
                .text-editor-background:last-child {
                    break-after: avoid;
                }
                
                @media print {
                    /* Forza l'orientamento landscape per manifesti orizzontali */
                    .text-editor-background[data-orientation="landscape"] {
                        max-width: 100%;
                        max-height: 100%;
                        width: 100vw !important;
                        height: 100vh !important;
                        background-repeat: no-repeat !important;
                        background-size: contain !important;
                        background-position: center center !important;
                        display: block !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        position: relative !important;
                        top: 0 !important;
                        left: 0 !important;
                        page: landscape !important;
                        transform: rotate(0deg) !important;
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
                        page: portrait !important;
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
                
                backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                const dimensions = pageFormatDimensions[pageFormat.toLowerCase()];

                if (aspectRatio > 1) {
                    // Landscape orientation
                    const landscapeMaxWidth = dimensions.height;
                    const landscapeMaxHeight = dimensions.width;
                    const widthFromHeight = landscapeMaxHeight * aspectRatio;
                    
                    if (widthFromHeight <= landscapeMaxWidth) {
                        backgroundDiv.style.height = `${landscapeMaxHeight}px`;
                        backgroundDiv.style.width = `${widthFromHeight}px`;
                    } else {
                        backgroundDiv.style.width = `${landscapeMaxWidth}px`;
                        backgroundDiv.style.height = `${landscapeMaxWidth / aspectRatio}px`;
                    }
                } else {
                    // Portrait orientation
                    backgroundDiv.style.height = `${dimensions.height}px`;
                    backgroundDiv.style.width = `${dimensions.height * aspectRatio}px`;
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
