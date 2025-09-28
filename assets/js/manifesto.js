function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

(function ($) {
    // === INIZIALIZZAZIONE CACHE ===
    // Cache globale per le immagini di sfondo con struttura migliorata
    var imageCache = new Map();
    // Struttura: URL -> {image, width, height, aspectRatio}

    // === ANALISI URL UNIVOCI ===
    // Funzione per analizzare e raggruppare gli URL delle immagini di sfondo
    function analyzeBackgroundUrls(manifesti) {
        var urlToIndices = new Map(); // URL -> array di indici dei manifesti
        var uniqueUrls = new Set();
        
        manifesti.forEach(function(manifesto, index) {
            if (manifesto.vendor_data && manifesto.vendor_data.manifesto_background) {
                var url = manifesto.vendor_data.manifesto_background;
                uniqueUrls.add(url);
                
                if (!urlToIndices.has(url)) {
                    urlToIndices.set(url, []);
                }
                urlToIndices.get(url).push(index);
            }
        });
        
        console.log('📊 Analisi URL completata:', {
            totalManifesti: manifesti.length,
            urlUnivoci: uniqueUrls.size,
            risparmioCache: manifesti.length - uniqueUrls.size
        });
        
        return {
            uniqueUrls: Array.from(uniqueUrls),
            urlToIndices: urlToIndices
        };
    }

    // === PRE-CARICAMENTO CON CACHE ===
    // Funzione per pre-caricare solo le immagini uniche
    function preloadUniqueImages(uniqueUrls) {
        var promises = uniqueUrls.map(function(url) {
            return new Promise(function(resolve) {
                // 1. VERIFICA CACHE
                if (imageCache.has(url)) {
                    // Cache HIT - usa i dati già memorizzati
                    console.log('🟢 CACHE HIT per:', url.substring(url.lastIndexOf('/') + 1));
                    resolve(imageCache.get(url));
                    return;
                }
                
                // 2. CACHE MISS - CARICA IMMAGINE
                console.log('🔴 CACHE MISS - Loading:', url.substring(url.lastIndexOf('/') + 1));
                var img = new Image();
                
                img.onload = function() {
                    // 3. MEMORIZZA IN CACHE con struttura completa
                    var imageData = {
                        image: img,
                        width: img.width,
                        height: img.height,
                        aspectRatio: img.width / img.height
                    };
                    imageCache.set(url, imageData);
                    console.log('✅ Cached:', url.substring(url.lastIndexOf('/') + 1), {
                        width: img.width,
                        height: img.height,
                        ratio: imageData.aspectRatio.toFixed(2)
                    });
                    resolve(imageData);
                };
                
                img.onerror = function() {
                    // 4. RIMUOVI DA CACHE SE ERRORE
                    console.log('❌ Failed to load:', url.substring(url.lastIndexOf('/') + 1));
                    imageCache.delete(url);
                    resolve(null);
                };
                
                img.src = url;
            });
        });
        
        return Promise.all(promises);
    }

    $(document).ready(function () {
        $('.manifesto-container').each(function () {
            var container = $(this);
            var post_id = container.data('postid');
            var tipo_manifesto = container.data('tipo');
            var offset = 0;
            var loading = false;
            var allDataLoaded = false;
            var totalManifesti = 0;
            
            // Author-aware pagination variables
            var currentAuthorId = null;
            var authorOffset = 0;
            // Trova l'elemento loader con una strategia simile
            var $loader = container.siblings('.manifesto-loader');
            if ($loader.length === 0) {
                $loader = container.parent().find('.manifesto-loader');
            }
            if ($loader.length === 0) {
                var containerId = container.attr('id');
                if (containerId) {
                    var instanceMatch = containerId.match(/manifesto-container-(\d+)/);
                    if (instanceMatch) {
                        $loader = $('#manifesto-loader-' + instanceMatch[1]);
                    }
                }
            }

            // === UTILIZZO DELLA CACHE ===
            function updateEditorBackground(data, containerElem, resolve) {
                if (!data || !containerElem || !containerElem.length) {
                    console.warn('Missing data or container for updateEditorBackground');
                    if (resolve) resolve();
                    return;
                }

                const backgroundDiv = containerElem.find('.text-editor-background').get(0);
                const textEditor = containerElem.find('.custom-text-editor').get(0);

                if (!backgroundDiv || !textEditor) {
                    console.warn('Required elements not found in container');
                    if (resolve) resolve();
                    return;
                }

                if (data.manifesto_background) {
                    // Usa i dati dalla cache invece di caricare nuova immagine
                    const cachedImageData = imageCache.get(data.manifesto_background);
                    
                    if (!cachedImageData) {
                        // Fallback se non in cache (non dovrebbe succedere se pre-caricamento funziona)
                        console.warn('⚠️ Image not in cache, loading now:', data.manifesto_background);
                        // Nasconde il testo durante il caricamento
                        textEditor.classList.add('loading');
                        
                        var img = new Image();
                        img.onload = function() {
                            // Memorizza in cache per usi futuri
                            var imageData = {
                                image: img,
                                width: img.width,
                                height: img.height,
                                aspectRatio: img.width / img.height
                            };
                            imageCache.set(data.manifesto_background, imageData);
                            applyBackgroundStyles(imageData);
                        };
                        img.onerror = function() {
                            console.warn('Failed to load background image:', data.manifesto_background);
                            backgroundDiv.style.backgroundImage = 'none';
                            textEditor.classList.remove('loading');
                            if (resolve) resolve();
                        };
                        img.src = data.manifesto_background;
                        return;
                    }
                    
                    // Usa dimensioni dalla cache direttamente
                    applyBackgroundStyles(cachedImageData);
                    
                    function applyBackgroundStyles(imageData) {
                        const aspectRatio = imageData.aspectRatio;
                        const imageWidth = imageData.width;
                        const imageHeight = imageData.height;
                        
                        backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                        
                        if (aspectRatio > 1) {
                            backgroundDiv.style.width = '350px';
                            backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
                        } else {
                            backgroundDiv.style.height = '350px';
                            backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
                        }

                        const marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                        const marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                        const marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                        const marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                        textEditor.style.paddingTop = `${marginTopPx}px`;
                        textEditor.style.paddingRight = `${marginRightPx}px`;
                        textEditor.style.paddingBottom = `${marginBottomPx}px`;
                        textEditor.style.paddingLeft = `${marginLeftPx}px`;
                        textEditor.style.textAlign = data.alignment || 'left';

                        // Mostra il testo dopo che tutto è pronto (nessun ritardo perché cache)
                        textEditor.classList.remove('loading');
                        
                        if (resolve) resolve();
                    }
                } else {
                    backgroundDiv.style.backgroundImage = 'none';
                    // Assicurati che il testo sia visibile se non c'è sfondo
                    textEditor.classList.remove('loading');
                    if (resolve) resolve();
                }
            }

            function loadManifesti() {
                if (loading || allDataLoaded) return;

                // Su mobile: registra la posizione dell'ultimo elemento del batch precedente
                var prevScrollPos = null;
                if (window.innerWidth <= 768) {
                    var lastChild = container.children().last();
                    if (lastChild.length) {
                        prevScrollPos = lastChild.offset().top + lastChild.outerHeight();
                    }
                }

                loading = true;
                $loader && $loader.show();

                // Build AJAX data with author-aware pagination
                var ajaxData = {
                    action: 'load_more_manifesti',
                    post_id: post_id,
                    tipo_manifesto: tipo_manifesto,
                    offset: offset
                };

                console.log ('Loading manifesti with data:', ajaxData);
                // Add author pagination if available
                if (currentAuthorId !== null) {
                    ajaxData.current_author_id = currentAuthorId;
                    ajaxData.author_offset = authorOffset;
                }

                $.ajax({
                    url: my_ajax_object.ajax_url,
                    type: 'post',
                    data: ajaxData,
                    success: function (response) {
                        if (!response.success || !response.data) {
                            allDataLoaded = true;
                            $loader && $loader.hide();
                            return;
                        }

                        // Handle new response structure with pagination metadata
                        var manifesti = null;
                        var pagination = null;

                        // Check if response contains pagination metadata (new structure)
                        if (response.data.manifesti && response.data.pagination) {
                            manifesti = response.data.manifesti;
                            pagination = response.data.pagination;
                            console.log('Received manifesti:', manifesti.length);

                            //se response.data.manifesti è vuoto e pagination offset e -1 e is_finished_current_author true, significa che abbiamo finito tutto
                            if((!manifesti || manifesti.length === 0) && pagination['offset']
                                && pagination['offset'] === -1
                                && pagination['is_finished_current_author'] === true) {
                                    allDataLoaded = true;
                                    $loader && $loader.hide();
                                    return;
                                }
                        }else {
                            console.log('Struttura della risposta non riconosciuta. Contatta l\'amministratore del sito.');
                        }

                        // Check if we have manifesti to display
                        if (!manifesti || manifesti.length === 0) {
                            allDataLoaded = true;
                            $loader && $loader.hide();
                            return;
                        }

                        // === FLUSSO DI ESECUZIONE ===
                        // 1. Analizza tutti gli URL per trovare quelli unici
                        var analysisResult = analyzeBackgroundUrls(manifesti);
                        
                        // 2. Pre-carica solo le immagini uniche nella cache
                        preloadUniqueImages(analysisResult.uniqueUrls).then(function() {
                            console.log('🎯 Pre-caricamento completato, applicazione manifesti...');
                            
                            // 3. Processa tutti i manifesti usando la cache
                            var updatePromises = [];
                            
                            manifesti.forEach(function (item) {
                                if (!item || !item.html) return;

                                var newElement = $(item.html);
                                container.append(newElement);

                                // Rende visibile il manifesto_divider per la sezione
                                container.parent().parent().parent().parent().find('.manifesto_divider').show();

                                // 4. Nessun caricamento duplicato - massima efficienza
                                // Wrappa l'update in una Promise per tracking
                                updatePromises.push(new Promise(function(resolve) {
                                    updateEditorBackground(item.vendor_data, newElement, resolve);
                                }));
                            });
                            
                            // Attendi che tutti gli aggiornamenti siano completati
                            Promise.all(updatePromises).then(function() {
                                console.log('✨ Tutti i manifesti aggiornati con successo');
                                
                                // Gestione post-caricamento dopo che tutti i manifesti sono pronti
                                offset = pagination['offset'];

                                if(pagination['is_finished_current_author'] == true)
                                {
                                    var divider = $('<div class="col-12" style="width: 90%;"><hr class="manifesto_divider" style="margin: 30px 0;"></div>');
                                    container.append(divider);
                                }

                                totalManifesti += pagination['offset'];
                                loading = false;
                                $loader && $loader.hide();
                                
                                // Con grid layout non è più necessario modificare justify-content
                                // Il grid gestisce automaticamente il layout

                                // Su mobile, ripristina la posizione precedente: l'ultimo del batch precedente resta visibile,
                                // mentre i nuovi 5 vengono caricati offscreen
                                if (window.innerWidth <= 768 && prevScrollPos !== null) {
                                    $(window).scrollTop(prevScrollPos);
                                }
                            });
                        });
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("Error during loading:", textStatus, errorThrown);
                        loading = false;
                        $loader && $loader.hide();
                    }
                });
            }

            if (tipo_manifesto === 'top') {
                // Per i top: caricamento continuo fino a completamento
                loadManifesti();
            } else {
                // Per gli altri tipi: usa un timer ogni 2 secondi
                var loadInterval = null;
                
                // Funzione per controllare se caricare più manifesti
                function checkAndLoad() {
                    if (!loading && !allDataLoaded) {
                        console.log('⏰ Timer check: caricamento manifesti...');
                        // Mostra il loader prima di iniziare il caricamento
                        $loader && $loader.show();
                        loadManifesti();
                    } else if (allDataLoaded && loadInterval) {
                        // Se abbiamo finito, ferma il timer e nasconde il loader
                        console.log('✅ Tutti i manifesti caricati, timer fermato');
                        $loader && $loader.hide();
                        clearInterval(loadInterval);
                        loadInterval = null;
                    }
                }
                
                // Carica il primo batch immediatamente
                loadManifesti();
                
                // Poi attiva il timer per controllare ogni 2 secondi
                loadInterval = setInterval(checkAndLoad, 2000);
                
                // Pulizia quando la pagina viene lasciata
                $(window).on('beforeunload', function() {
                    if (loadInterval) {
                        clearInterval(loadInterval);
                    }
                });
                
                // Log per debug
                console.log('📅 Timer attivato per tipo:', tipo_manifesto, 'Container:', container.attr('id'));
            }
        });
    });
})(jQuery);