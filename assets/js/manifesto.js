(function ($) {
    'use strict';

    // Configuration
    const CONFIG = {
        CONTAINER_SIZE: 0.95,
        LINE_HEIGHT_RATIO: 1.2,
        LAYOUT: {
            DEFAULT_ASPECT_RATIO: '16 / 9',
            MAX_HEIGHT: '80vh',
            NO_BACKGROUND_PADDING: '5%'
        },
        // Timer settings
        TIMER_INTERVAL: 2000, // Check every 2 seconds
        CACHE_CLEANUP_INTERVAL: 60000, // Clean cache every minute
        IMAGE_LOAD_TIMEOUT: 10000
    };

    // Module state con cache avanzata
    const ModuleState = {
        imageCache: new Map(), // URL -> Image object
        manifestiData: new Map(),
        uniqueImages: new Set(), // Track unique image URLs
        imageDimensions: new Map(), // URL -> {width, height, aspectRatio}
        preloadPromises: new Map(), // URL -> Promise
        loadTimer: null,
        isPreloading: false
    };

    // Sistema di cache avanzato per immagini
    function analyzeManifestiImages(manifesti) {
        if (!manifesti || !Array.isArray(manifesti)) return;
        
        manifesti.forEach(item => {
            if (item?.vendor_data?.manifesto_background) {
                const url = item.vendor_data.manifesto_background;
                if (!ModuleState.uniqueImages.has(url)) {
                    ModuleState.uniqueImages.add(url);
                }
            }
        });
    }

    // Pre-carica tutte le immagini uniche
    async function preloadUniqueImages() {
        if (ModuleState.isPreloading) return;
        ModuleState.isPreloading = true;
        
        const promises = [];
        for (const url of ModuleState.uniqueImages) {
            if (!ModuleState.preloadPromises.has(url)) {
                const promise = loadImage(url);
                ModuleState.preloadPromises.set(url, promise);
                promises.push(promise);
            }
        }
        
        try {
            await Promise.all(promises);
        } finally {
            ModuleState.isPreloading = false;
        }
    }

    // Load image con cache intelligente
    function loadImage(url) {
        if (!url) {
            return Promise.reject(new Error('Invalid URL provided'));
        }

        // Check cache esistente
        if (ModuleState.imageCache.has(url)) {
            PerformanceMonitor.recordCacheHit();
            return Promise.resolve(ModuleState.imageCache.get(url));
        }

        // Check promise pendente
        if (ModuleState.preloadPromises.has(url)) {
            return ModuleState.preloadPromises.get(url);
        }

        PerformanceMonitor.recordCacheMiss();
        const promise = new Promise((resolve, reject) => {
            const img = new Image();
            
            img.onerror = () => {
                ModuleState.preloadPromises.delete(url);
                reject(new Error(`Failed to load image: ${url}`));
            };

            const timeout = setTimeout(() => {
                ModuleState.preloadPromises.delete(url);
                reject(new Error(`Image load timeout: ${url}`));
            }, CONFIG.IMAGE_LOAD_TIMEOUT);

            img.onload = () => {
                clearTimeout(timeout);
                try {
                    // Cache immagine e dimensioni
                    ModuleState.imageCache.set(url, img);
                    ModuleState.imageDimensions.set(url, {
                        width: img.width,
                        height: img.height,
                        aspectRatio: img.width / img.height
                    });
                    resolve(img);
                } catch (error) {
                    reject(new Error(`Failed to cache image: ${error.message}`));
                } finally {
                    ModuleState.preloadPromises.delete(url);
                }
            };

            img.src = url;
        });
        
        ModuleState.preloadPromises.set(url, promise);
        return promise;
    }

    // Pulizia cache periodica
    function cleanupCache() {
        // Mantieni solo le immagini usate di recente
        if (ModuleState.imageCache.size > 50) {
            const toKeep = Array.from(ModuleState.uniqueImages).slice(-30);
            const newCache = new Map();
            const newDimensions = new Map();
            
            toKeep.forEach(url => {
                if (ModuleState.imageCache.has(url)) {
                    newCache.set(url, ModuleState.imageCache.get(url));
                }
                if (ModuleState.imageDimensions.has(url)) {
                    newDimensions.set(url, ModuleState.imageDimensions.get(url));
                }
            });
            
            ModuleState.imageCache = newCache;
            ModuleState.imageDimensions = newDimensions;
        }
    }

    // Font size semplificato
    function applyFontSize(textEditor, data) {
        if (!textEditor) return;
        
        const backgroundDiv = textEditor.closest('.text-editor-background') || textEditor.parentElement;
        const aspectRatio = backgroundDiv ? backgroundDiv.clientWidth / backgroundDiv.clientHeight : 1;
        
        let cssSize;
        if (aspectRatio > 1) {
            cssSize = '8cqh';
        } else {
            cssSize = '4cqh';
        }
        
        textEditor.style.fontSize = cssSize;
        textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
    }

    // Apply manifesto styles - simplified
    function applyStyles(data, containerElem, img = null) {
        if (!data || !containerElem?.length) {
            console.error('❌ Invalid data or container provided to applyStyles');
            return;
        }

        const backgroundDiv = containerElem.find('.text-editor-background')[0];
        const textEditor = containerElem.find('.custom-text-editor')[0];

        if (!backgroundDiv || !textEditor) {
            console.error('❌ Required DOM elements not found in container');
            return;
        }

        try {
            if (data.manifesto_background && img) {
                setupBackground(backgroundDiv, textEditor, data, img);
            } else {
                setupNoBackground(backgroundDiv, textEditor, data);
            }

            // Apply font size based on user selection
            applyFontSize(textEditor, data);

        } catch (error) {
            console.error(`❌ Error applying styles: ${error.message}`);
        }
    }

    function setupBackground(backgroundDiv, textEditor, data, img) {
        // Set background image
        backgroundDiv.style.backgroundImage = `url(${data.manifesto_background})`;

        // CSS-based responsive sizing with aspect ratio
        backgroundDiv.style.aspectRatio = `${img.width} / ${img.height}`;
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto'; // Let CSS handle height via aspect-ratio
        backgroundDiv.style.maxWidth = '100%';
        backgroundDiv.style.maxHeight = CONFIG.LAYOUT.MAX_HEIGHT;

        // Calculate margins as percentages of image dimensions
        const marginTop = data.margin_top || 0;
        const marginRight = data.margin_right || 0;
        const marginBottom = data.margin_bottom || 0;
        const marginLeft = data.margin_left || 0;

        // Apply margins as padding percentages - CSS will scale automatically
        textEditor.style.padding = `${marginTop}% ${marginRight}% ${marginBottom}% ${marginLeft}%`;
        textEditor.style.textAlign = data.alignment || 'left';
        textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
    }

    function setupNoBackground(backgroundDiv, textEditor, data) {
        backgroundDiv.style.backgroundImage = 'none';
        backgroundDiv.style.aspectRatio = CONFIG.LAYOUT.DEFAULT_ASPECT_RATIO;
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto';

        // Simple padding for no-background case
        textEditor.style.padding = CONFIG.LAYOUT.NO_BACKGROUND_PADDING;
        textEditor.style.textAlign = data.alignment || 'center';
        textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
    }

    // Funzione principale aggiornata con cache intelligente
    function updateManifesto(data, containerElem) {
        if (!data || !containerElem?.length) return;

        try {
            const manifestoId = containerElem.attr('id') || `manifesto-${Date.now()}`;
            if (!containerElem.attr('id')) {
                containerElem.attr('id', manifestoId);
            }
            ModuleState.manifestiData.set(manifestoId, {data, containerElem});

            const textEditor = containerElem.find('.custom-text-editor')[0];

            if (data.manifesto_background) {
                if (textEditor) textEditor.classList.add('loading');

                // Usa cache intelligente - instant se già caricata
                loadImage(data.manifesto_background)
                    .then(img => {
                        applyStyles(data, containerElem, img);
                        if (textEditor) textEditor.classList.remove('loading');
                    })
                    .catch(error => {
                        applyStyles(data, containerElem);
                        if (textEditor) textEditor.classList.remove('loading');
                    });
            } else {
                if (textEditor) textEditor.classList.remove('loading');
                applyStyles(data, containerElem);
            }

        } catch (error) {
            // Error handling
        }
    }

    // Performance monitoring semplificato
    const PerformanceMonitor = {
        cacheHits: 0,
        cacheMisses: 0,
        imagesLoaded: 0,
        
        recordCacheHit() {
            this.cacheHits++;
        },
        
        recordCacheMiss() {
            this.cacheMisses++;
            this.imagesLoaded++;
        },
        
        getStats() {
            const total = this.cacheHits + this.cacheMisses;
            const cacheRate = total > 0 ? ((this.cacheHits / total) * 100).toFixed(1) : 0;
            return {
                cacheHits: this.cacheHits,
                cacheMisses: this.cacheMisses,
                imagesLoaded: this.imagesLoaded,
                cacheRate: `${cacheRate}%`,
                total
            };
        }
    };

    // Initialize on document ready
    $(document).ready(function () {

        // Setup pulizia cache periodica
        setInterval(() => {
            cleanupCache();
        }, CONFIG.CACHE_CLEANUP_INTERVAL);

        // Handle manifesto containers con nuovo sistema
        $('.manifesto-container').each(function () {
            const container = $(this);
            const postId = container.data('postid');
            const tipoManifesto = container.data('tipo');
            let offset = 0;
            let loading = false;
            let allDataLoaded = false;

            // Setup loader - sempre visibile durante caricamento progressivo
            let $loader = container.siblings('.manifesto-loader');
            if ($loader.length === 0) {
                $loader = container.parent().find('.manifesto-loader');
            }

            // Funzione di caricamento ottimizzata
            async function loadManifesti() {
                if (loading || allDataLoaded) return;

                loading = true;
                if ($loader) $loader.show();

                try {
                    const response = await $.ajax({
                        url: my_ajax_object.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'load_more_manifesti',
                            post_id: postId,
                            tipo_manifesto: tipoManifesto,
                            offset: offset
                        }
                    });

                    if (!response.success || !response.data.manifesti?.length) {
                        allDataLoaded = true;
                        if ($loader) $loader.hide();
                        clearInterval(ModuleState.loadTimer);
                        return;
                    }

                    const manifesti = response.data.manifesti;
                    const pagination = response.data.pagination;

                    // Check fine dati
                    if ((!manifesti || manifesti.length === 0) && 
                        pagination?.offset === -1 && 
                        pagination?.is_finished_current_author === true) {
                        allDataLoaded = true;
                        if ($loader) $loader.hide();
                        clearInterval(ModuleState.loadTimer);
                        return;
                    }

                    if (!manifesti || manifesti.length === 0) {
                        allDataLoaded = true;
                        if ($loader) $loader.hide();
                        clearInterval(ModuleState.loadTimer);
                        return;
                    }

                    // SISTEMA CACHE AVANZATO: Analizza tutte le immagini prima del caricamento
                    analyzeManifestiImages(manifesti);
                    
                    // Pre-carica le immagini uniche in parallelo
                    await preloadUniqueImages();

                    // Rendering veloce con cache
                    manifesti.forEach(function (item) {
                        if (!item || !item.html) return;

                        const newElement = $(item.html);
                        container.append(newElement);

                        // Mostra divider
                        container.parent().parent().parent().parent().find('.manifesto_divider').show();

                        // Applicazione istantanea grazie alla cache
                        updateManifesto(item.vendor_data, newElement);
                    });

                    offset = pagination.offset;

                    if (pagination.is_finished_current_author === true) {
                        const divider = $('<div class="col-12" style="width: 90%;"><hr class="manifesto_divider" style="margin: 30px 0;"></div>');
                        container.append(divider);
                    }

                } catch (error) {
                    // Error handling
                } finally {
                    loading = false;
                    
                    // Per tipo "top": caricamento continuo fino a completamento
                    // Per altri tipi: solo se non tutto caricato, nascondi loader
                    if (tipoManifesto !== 'top' && allDataLoaded) {
                        if ($loader) $loader.hide();
                        clearInterval(ModuleState.loadTimer);
                    }
                }
            }

            // Sistema di caricamento differenziato
            if (tipoManifesto === 'top') {
                // Caricamento completo immediato per "top"
                (async function loadAllTop() {
                    while (!allDataLoaded) {
                        await loadManifesti();
                    }
                })();
            } else {
                // Timer-based loading per altri tipi (sostituisce IntersectionObserver)
                loadManifesti(); // Caricamento iniziale
                
                ModuleState.loadTimer = setInterval(() => {
                    if (!allDataLoaded) {
                        loadManifesti();
                    } else {
                        clearInterval(ModuleState.loadTimer);
                    }
                }, CONFIG.TIMER_INTERVAL);
            }
        });
    });

})(jQuery);