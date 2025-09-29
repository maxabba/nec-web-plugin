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
        
        // Font sizes per manifesti "old"
        var oldManifestoFonts = {
            VERTICAL: '3cqh',    // Immagini verticali (aspect ratio < 1)
            HORIZONTAL: '5cqh'   // Immagini orizzontali (aspect ratio >= 1)
        };

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
            
            // Separa i manifesti per orientamento
            let landscapeManifesti = [];
            let portraitManifesti = [];
            
            container.find('.text-editor-background').each(function(index, element) {
                const orientation = $(element).attr('data-orientation');
                const bgImage = $(element).css('background-image');
                
                if (!orientation) {
                    console.warn(`Missing orientation for manifesto ${index}, defaulting to portrait`);
                    $(element).attr('data-orientation', 'portrait');
                    portraitManifesti.push(element.outerHTML);
                } else {
                    console.log(`Manifesto ${index}: orientation=${orientation}, bg=${bgImage ? 'present' : 'missing'}`);
                    if (orientation === 'landscape') {
                        landscapeManifesti.push(element.outerHTML);
                    } else {
                        portraitManifesti.push(element.outerHTML);
                    }
                }
            });
            
            console.log(`Print summary: ${landscapeManifesti.length} landscape, ${portraitManifesti.length} portrait manifesti`);
            
            // Funzione helper per creare una finestra di stampa
            function createPrintWindow(manifesti, orientationType) {
                if (manifesti.length === 0) {
                    console.log(`No ${orientationType} manifesti to print`);
                    return null;
                }
                
                var printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write('<html><head><title>Print Manifesti - ' + orientationType + '</title>');
                printWindow.document.write('<script>document.addEventListener("DOMContentLoaded", function() { setTimeout(function() { window.print(); window.close(); }, 2000); });<\/script>');
                
                // Stili base
                printWindow.document.write('<style>body{font-family: Arial, sans-serif; margin: 0; padding: 0;}</style>');

                // Aggiungi il font TTF
                printWindow.document.write('<style>@font-face {font-family: "PlayFair Display Mine"; src: url("' + my_ajax_object.plugin_url + 'assets/fonts/Playfair_Display/static/PlayfairDisplay-Regular.ttf") format("truetype");}</style>');
                
                // Aggiungi CSS specifico per l'orientamento
                if (orientationType === 'landscape') {
                    printWindow.document.write('<style>@page { size: landscape; margin: 0; } @media print { body { margin: 0; } }</style>');
                } else {
                    printWindow.document.write('<style>@page { size: portrait; margin: 0; } @media print { body { margin: 0; } }</style>');
                }
                
                // Il CSS principale con tutte le regole
                printWindow.document.write('<link rel="stylesheet" type="text/css" href="' + my_ajax_object.plugin_url + 'assets/css/manifesto-print.css">');
                
                printWindow.document.write('</head><body>');
                printWindow.document.write(manifesti.join(''));
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                
                return printWindow;
            }
            
            // Crea finestra per manifesti orizzontali
            if (landscapeManifesti.length > 0) {
                console.log('Creating landscape print window...');
                let landscapeWindow = createPrintWindow(landscapeManifesti, 'landscape');
                
                // Attendi che la finestra landscape sia completata prima di aprire portrait
                setTimeout(function() {
                    if (portraitManifesti.length > 0) {
                        console.log('Creating portrait print window...');
                        createPrintWindow(portraitManifesti, 'portrait');
                    }
                }, 3000); // Ritardo di 3 secondi tra le due finestre
            } else if (portraitManifesti.length > 0) {
                console.log('Creating portrait print window...');
                createPrintWindow(portraitManifesti, 'portrait');
            }

            // Ricarica la pagina dopo un delay per assicurarsi che le finestre di stampa siano aperte
            setTimeout(function() {
                location.reload();
            }, 6000);
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
            const backgroundDiv = container.get(0);
            const textEditor = container.find('.custom-text-editor').get(0);

            // Remove existing format classes  
            backgroundDiv.classList.remove('page-a3', 'page-a4', 'page-a5', 'page-a3-landscape', 'page-a4-landscape', 'page-a5-landscape', 'page-a3-portrait', 'page-a4-portrait', 'page-a5-portrait');

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
                
                // Aggiungi la classe completa formato-orientamento
                const formatClass = `page-${pageFormat.toLowerCase()}-${orientation}`;
                backgroundDiv.classList.add(formatClass);
                console.log(`Manifesto orientation set: ${orientation} (AR: ${aspectRatio.toFixed(2)}), class: ${formatClass}`);
                
                backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                
                // Le dimensioni sono ora gestite dalle classi CSS
                // Non serve più impostare width/height via JavaScript

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

                    // Font-size con gestione manifesti "old"
                    // Check se è un manifesto "old"
                    const isOld = backgroundDiv && backgroundDiv.getAttribute('data-info') === 'is_old';
                    
                    let fontSize;
                    if (isOld) {
                        // Usa font size specifici per manifesti "old"
                        fontSize = aspectRatio >= 1 ? 
                            oldManifestoFonts.HORIZONTAL : 
                            oldManifestoFonts.VERTICAL;
                    } else {
                        // Font size standard per manifesti normali
                        fontSize = aspectRatio > 1 ? '8cqh' : '4cqh';
                    }
                    
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
