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
    // Cache globale per le immagini di sfondo
    const imageCache = new Map();

    // Funzione per caricare immagini con cache
    function loadImageWithCache(url) {
        return new Promise((resolve, reject) => {
            if (imageCache.has(url)) {
                // Immagine già in cache, restituisci immediatamente
                console.log('🟢 CACHE HIT for:', url);
                const cachedImg = imageCache.get(url);
                resolve(cachedImg);
            } else {
                // Carica l'immagine e mettila in cache
                console.log('🔴 CACHE MISS for:', url);
                const img = new Image();
                img.onload = function() {
                    console.log('✅ Image loaded and cached:', url);
                    imageCache.set(url, img);
                    resolve(img);
                };
                img.onerror = function() {
                    console.log('❌ Failed to load image:', url);
                    reject(new Error('Failed to load image: ' + url));
                };
                img.src = url;
            }
        });
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

            // Se non è la sezione "top" usiamo una sentinella per l'infinite scroll
            var $sentinel = null;
            if (tipo_manifesto !== 'top') {
                var containerId = container.attr('id');
                var instanceId = null;
                
                if (containerId) {
                    var instanceMatch = containerId.match(/manifesto-container-(\d+)/);
                    if (instanceMatch) {
                        instanceId = instanceMatch[1];
                    }
                }

                console.log('Searching for sentinel element:', {
                    containerId: containerId,
                    instanceId: instanceId,
                    tipo_manifesto: tipo_manifesto
                });

                // Prova prima come sibling
                $sentinel = container.siblings('.sentinel');
                console.log('Found siblings .sentinel:', $sentinel.length);

                // Se non trovato come sibling, prova nel parent
                if ($sentinel.length === 0) {
                    $sentinel = container.parent().find('.sentinel');
                    console.log('Found in parent .sentinel:', $sentinel.length);
                }

                // Se ancora non trovato, prova con l'ID specifico
                if ($sentinel.length === 0 && instanceId) {
                    $sentinel = $('#sentinel-' + instanceId);
                    console.log('Found by ID #sentinel-' + instanceId + ':', $sentinel.length);
                }
            }
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

            function updateEditorBackground(data, containerElem) {
                if (!data || !containerElem || !containerElem.length) {
                    console.warn('Missing data or container for updateEditorBackground');
                    return;
                }

                const backgroundDiv = containerElem.find('.text-editor-background').get(0);
                const textEditor = containerElem.find('.custom-text-editor').get(0);

                if (!backgroundDiv || !textEditor) {
                    console.warn('Required elements not found in container');
                    return;
                }

                if (data.manifesto_background) {
                    // Nasconde il testo durante il caricamento
                    textEditor.classList.add('loading');

                    // Usa la cache per caricare l'immagine
                    loadImageWithCache(data.manifesto_background)
                        .then(function(img) {
                            const aspectRatio = img.width / img.height;
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

                            // Mostra il testo dopo che tutto è pronto
                            textEditor.classList.remove('loading');
                        })
                        .catch(function(error) {
                            console.warn('Failed to load background image:', error);
                            backgroundDiv.style.backgroundImage = 'none';
                            // Mostra il testo anche in caso di errore
                            textEditor.classList.remove('loading');
                        });
                } else {
                    backgroundDiv.style.backgroundImage = 'none';
                    // Assicurati che il testo sia visibile se non c'è sfondo
                    textEditor.classList.remove('loading');
                }
            }

            function loadManifesti(isInfiniteScroll = false) {
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
                            $sentinel && $sentinel.remove();
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
                                    $sentinel && $sentinel.remove();
                                    $loader && $loader.hide();
                                    return;
                                }
                        }else {
                            console.log('Struttura della risposta non riconosciuta. Contatta l\'amministratore del sito.');
                        }

                        // Check if we have manifesti to display
                        if (!manifesti || manifesti.length === 0) {
                            allDataLoaded = true;
                            $sentinel && $sentinel.remove();
                            $loader && $loader.hide();
                            return;
                        }

                        manifesti.forEach(function (item) {
                            if (!item || !item.html) return;

                            var newElement = $(item.html);
                            container.append(newElement);

                            // Rende visibile il manifesto_divider per la sezione
                            container.parent().parent().parent().parent().find('.manifesto_divider').show();

                            // Count only real manifesti, not dividers - check both vendor_data and HTML content

                            updateEditorBackground(item.vendor_data, newElement);

                        });

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

                        // Forza un check dell'IntersectionObserver dopo il DOM update
                        setTimeout(function() {
                            if ($sentinel && $sentinel.length > 0 && !allDataLoaded) {
                                var sentinelRect = $sentinel[0].getBoundingClientRect();
                                var windowHeight = window.innerHeight;
                                console.log('Sentinel position check:', {
                                    sentinelTop: sentinelRect.top,
                                    windowHeight: windowHeight,
                                    isVisible: sentinelRect.top < windowHeight + 200
                                });
                            }
                        }, 200);


                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("Error during loading:", textStatus, errorThrown);
                        loading = false;
                        $loader && $loader.hide();
                    }
                });
            }

            if (tipo_manifesto === 'top') {
                loadManifesti();
            } else {
                // Usa l'infinite scroll osservando la sentinella
// Modifica la sezione dell'IntersectionObserver nel tuo codice
                if ($sentinel && $sentinel.length > 0) {
                    // Configurazione ottimizzata per mobile
                    var isMobile = window.innerWidth <= 768;

                    // Debounce per evitare chiamate multiple
                    var debouncedLoad = debounce(function () {
                        if (!loading && !allDataLoaded) {
                            console.log('🔄 Triggering infinite scroll load');
                            loadManifesti(true);
                        }
                    }, isMobile ? 500 : 300);

                    var observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            console.log('Sentinel intersection:', {
                                isIntersecting: entry.isIntersecting,
                                loading: loading,
                                allDataLoaded: allDataLoaded,
                                offset: offset
                            });

                            if (entry.isIntersecting) {
                                // Usa il debounced load su mobile
                                if (isMobile) {
                                    debouncedLoad();
                                } else if (!loading && !allDataLoaded) {
                                    loadManifesti(true);
                                }
                            }
                        });
                    }, {
                        root: null,
                        // Pre-carica prima su mobile per evitare lag
                        rootMargin: isMobile ? '50px' : '0px',
                        threshold: isMobile ? 0.1 : 0.1
                    });

                    observer.observe($sentinel[0]);

                    // Ottimizzazioni mobile aggiuntive
                    if (isMobile) {
                        // Previeni scroll durante il caricamento su mobile
                        var originalScrollPos;
                        $(document).on('ajaxStart', function () {
                            if (loading) {
                                originalScrollPos = $(window).scrollTop();
                            }
                        });

                        // Smooth scroll per mobile
                        container.css({
                            '-webkit-overflow-scrolling': 'touch',
                            'will-change': 'transform'
                        });
                    }

                    // Carica il primo batch
                    loadManifesti(true);
                } else {
                    console.warn('Sentinel element not found for infinite scroll.', {
                        container: container,
                        containerId: container.attr('id'),
                        tipo_manifesto: tipo_manifesto,
                        siblings: container.siblings().length,
                        siblingClasses: container.siblings().map(function () {
                            return this.className;
                        }).get()
                    });
                    // Carica il primo batch anche se non c'è la sentinella
                    loadManifesti(true);
                }
            }
        });
    });
})(jQuery);