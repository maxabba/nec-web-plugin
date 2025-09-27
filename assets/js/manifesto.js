(function ($) {
    'use strict';

    // Configuration - simplified for fixed font system
    const CONFIG = {
        CONTAINER_SIZE: 0.95,
        LINE_HEIGHT_RATIO: 1.2,

        // Layout settings
        LAYOUT: {
            DEFAULT_ASPECT_RATIO: '16 / 9',
            MAX_HEIGHT: '80vh',
            NO_BACKGROUND_PADDING: '5%'
        }
    };

    // Module state - simplified
    const ModuleState = {
        imageCache: new Map(),
        manifestiData: new Map(),
        // Batch optimization: cache batch background info
        batchCache: new Map(), // containerId -> { backgroundUrl, batchId, preloadedImage }
        currentBatchId: null
    };

    // Batch cache optimization functions
    function initializeBatch(containerId) {
        ModuleState.currentBatchId = `batch_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        console.log(`🔄 Inizializing new batch: ${ModuleState.currentBatchId} for container: ${containerId}`);
        return ModuleState.currentBatchId;
    }

    function setBatchBackground(containerId, backgroundUrl, batchId) {
        if (!backgroundUrl || !containerId || !batchId) return false;
        
        // Pre-load the image for the entire batch
        return loadImage(backgroundUrl).then(img => {
            ModuleState.batchCache.set(containerId, {
                backgroundUrl,
                batchId,
                preloadedImage: img,
                timestamp: Date.now()
            });
            console.log(`📦 Batch background cached for container ${containerId}: ${backgroundUrl}`);
            return img;
        }).catch(error => {
            console.error(`❌ Failed to cache batch background: ${error.message}`);
            return null;
        });
    }

    function getBatchBackground(containerId) {
        const cached = ModuleState.batchCache.get(containerId);
        if (cached && cached.preloadedImage) {
            console.log(`⚡ Using cached batch background for container ${containerId}`);
            PerformanceMonitor.recordBatchCacheHit();
            return cached.preloadedImage;
        }
        return null;
    }

    function isSameBatchBackground(containerId, backgroundUrl) {
        const cached = ModuleState.batchCache.get(containerId);
        return cached && cached.backgroundUrl === backgroundUrl;
    }

    // Clean old batch cache entries (run periodically to prevent memory leaks)
    function cleanupBatchCache(maxAge = 300000) { // 5 minutes default
        const now = Date.now();
        const toDelete = [];
        
        ModuleState.batchCache.forEach((value, key) => {
            if (now - value.timestamp > maxAge) {
                toDelete.push(key);
            }
        });
        
        toDelete.forEach(key => {
            ModuleState.batchCache.delete(key);
            console.log(`🧹 Cleaned old batch cache entry: ${key}`);
        });
        
        if (toDelete.length > 0) {
            console.log(`🧹 Cleaned ${toDelete.length} old batch cache entries`);
        }
    }

    // Load image with caching and batch optimization
    function loadImage(url) {
        if (!url) {
            return Promise.reject(new Error('Invalid URL provided'));
        }

        if (ModuleState.imageCache.has(url)) {
            PerformanceMonitor.recordCacheHit();
            return Promise.resolve(ModuleState.imageCache.get(url));
        }

        PerformanceMonitor.recordCacheMiss();
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onerror = () => reject(new Error(`Failed to load image: ${url}`));

            // Set a timeout to prevent hanging
            const timeout = setTimeout(() => {
                reject(new Error(`Image load timeout: ${url}`));
            }, 10000);

            img.onload = () => {
                clearTimeout(timeout);
                try {
                    ModuleState.imageCache.set(url, img);
                    resolve(img);
                } catch (error) {
                    reject(new Error(`Failed to cache image: ${error.message}`));
                }
            };

            img.src = url;
        });
    }



    // Simple font size application based on aspect ratio only
    function applyFontSize(textEditor, data) {
        if (!textEditor) return;
        
        // Get aspect ratio from background container
        const backgroundDiv = textEditor.closest('.text-editor-background') || textEditor.parentElement;
        const aspectRatio = backgroundDiv ? backgroundDiv.clientWidth / backgroundDiv.clientHeight : 1;
        
        // Fixed font size based only on aspect ratio
        let cssSize;
        if (aspectRatio > 1) {
            // Horizontal image - fixed large size
            cssSize = '8cqh';
        } else {
            // Vertical image - fixed large size
            cssSize = '4cqh';
        }
        
        // Apply to the text editor directly
        textEditor.style.fontSize = cssSize;
        textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
        
        console.log(`🔤 Applied fixed font size: ${cssSize} for aspect ratio: ${aspectRatio.toFixed(2)}`);
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

    // Main function to update manifesto with batch optimization
    function updateManifesto(data, containerElem, parentContainerId = null, isFirstInBatch = false) {
        if (!data || !containerElem?.length) {
            console.error('❌ Invalid data or container provided to updateManifesto');
            return;
        }

        try {
            // Store for potential future use
            const manifestoId = containerElem.attr('id') || `manifesto-${Date.now()}`;
            if (!containerElem.attr('id')) {
                containerElem.attr('id', manifestoId);
            }
            ModuleState.manifestiData.set(manifestoId, {data, containerElem});

            const textEditor = containerElem.find('.custom-text-editor')[0];

            if (data.manifesto_background) {
                if (textEditor) textEditor.classList.add('loading');

                // Batch optimization: check if this is the same background as cached
                if (parentContainerId && isSameBatchBackground(parentContainerId, data.manifesto_background)) {
                    // Use cached background immediately
                    const cachedImg = getBatchBackground(parentContainerId);
                    if (cachedImg) {
                        applyStyles(data, containerElem, cachedImg);
                        if (textEditor) textEditor.classList.remove('loading');
                        return;
                    }
                }

                // Load image (either new or not in batch cache)
                loadImage(data.manifesto_background)
                    .then(img => {
                        // If this is the first in batch, cache it for other elements
                        if (isFirstInBatch && parentContainerId) {
                            setBatchBackground(parentContainerId, data.manifesto_background, ModuleState.currentBatchId);
                        }
                        
                        applyStyles(data, containerElem, img);
                        if (textEditor) textEditor.classList.remove('loading');
                    })
                    .catch(error => {
                        console.error(`❌ Error loading background image: ${error.message}`);
                        applyStyles(data, containerElem);
                        if (textEditor) textEditor.classList.remove('loading');
                    });
            } else {
                if (textEditor) textEditor.classList.remove('loading');
                applyStyles(data, containerElem);
            }

        } catch (error) {
            console.error(`❌ Error updating manifesto: ${error.message}`);
        }
    }


    // Performance monitoring
    const PerformanceMonitor = {
        cacheHits: 0,
        cacheMisses: 0,
        batchCacheHits: 0,
        imagesLoaded: 0,
        
        recordCacheHit() {
            this.cacheHits++;
        },
        
        recordCacheMiss() {
            this.cacheMisses++;
            this.imagesLoaded++;
        },
        
        recordBatchCacheHit() {
            this.batchCacheHits++;
        },
        
        getStats() {
            const total = this.cacheHits + this.cacheMisses;
            const cacheRate = total > 0 ? ((this.cacheHits / total) * 100).toFixed(1) : 0;
            return {
                cacheHits: this.cacheHits,
                cacheMisses: this.cacheMisses,
                batchCacheHits: this.batchCacheHits,
                imagesLoaded: this.imagesLoaded,
                cacheRate: `${cacheRate}%`,
                total
            };
        },
        
        logStats() {
            const stats = this.getStats();
            console.log(`📊 Cache Performance: ${stats.cacheHits}H/${stats.cacheMisses}M (${stats.cacheRate}) | Batch: ${stats.batchCacheHits}H | Images: ${stats.imagesLoaded}`);
        }
    };

    // Initialize on document ready
    $(document).ready(function () {

        // Setup periodic cache cleanup and stats
        setInterval(() => {
            cleanupBatchCache();
            PerformanceMonitor.logStats();
        }, 60000); // Every minute


        // Handle manifesto containers
        $('.manifesto-container').each(function () {
            const container = $(this);
            const postId = container.data('postid');
            const tipoManifesto = container.data('tipo');
            let offset = 0;
            let loading = false;
            let allDataLoaded = false;

            // Setup infinite scroll sentinel
            let $sentinel = container.siblings('.sentinel');
            if ($sentinel.length === 0) {
                $sentinel = container.parent().find('.sentinel');
            }

            // Setup loader
            let $loader = container.siblings('.manifesto-loader');
            if ($loader.length === 0) {
                $loader = container.parent().find('.manifesto-loader');
            }

            function loadManifesti(isInfiniteScroll = false) {
                if (loading || allDataLoaded) return;

                loading = true;
                $loader?.show();

                $.ajax({
                    url: my_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'load_more_manifesti',
                        post_id: postId,
                        tipo_manifesto: tipoManifesto,
                        offset: offset
                    },
                    success(response) {
                        if (!response.success || !response.data.manifesti?.length) {
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
                            if ((!manifesti || manifesti.length === 0) && pagination['offset']
                                && pagination['offset'] === -1
                                && pagination['is_finished_current_author'] === true) {
                                allDataLoaded = true;
                                $sentinel && $sentinel.remove();
                                $loader && $loader.hide();
                                return;
                            }
                        } else {
                            console.log('Struttura della risposta non riconosciuta. Contatta l\'amministratore del sito.');
                        }

                        // Check if we have manifesti to display
                        if (!manifesti || manifesti.length === 0) {
                            allDataLoaded = true;
                            $sentinel && $sentinel.remove();
                            $loader && $loader.hide();
                            return;
                        }

                        // Initialize batch for this container
                        const containerId = container.attr('id') || `container-${Date.now()}`;
                        if (!container.attr('id')) {
                            container.attr('id', containerId);
                        }
                        const batchId = initializeBatch(containerId);

                        // Batch optimization: analyze first element for background caching
                        let firstBackgroundUrl = null;
                        if (manifesti.length > 0 && manifesti[0].vendor_data && manifesti[0].vendor_data.manifesto_background) {
                            firstBackgroundUrl = manifesti[0].vendor_data.manifesto_background;
                            console.log(`🎯 First element background detected: ${firstBackgroundUrl}`);
                        }

                        manifesti.forEach(function (item, index) {
                            if (!item || !item.html) return;

                            var newElement = $(item.html);
                            container.append(newElement);

                            // Rende visibile il manifesto_divider per la sezione
                            container.parent().parent().parent().parent().find('.manifesto_divider').show();

                            // Batch optimization: pass container info and first element flag
                            const isFirstInBatch = index === 0;
                            updateManifesto(item.vendor_data, newElement, containerId, isFirstInBatch);

                        });

                        offset = pagination['offset'];

                        if (pagination['is_finished_current_author'] == true) {
                            var divider = $('<div class="col-12" style="width: 90%;"><hr class="manifesto_divider" style="margin: 30px 0;"></div>');
                            container.append(divider);
                        }


                        loading = false;
                        $loader && $loader.hide();

                        // Auto-load for "top" type
                        if (tipoManifesto === 'top' && !isInfiniteScroll) {
                            loadManifesti();
                        }
                    },
                    error() {
                        loading = false;
                        $loader?.hide();
                    }
                });
            }

            // Initialize loading
            if (tipoManifesto === 'top') {
                loadManifesti();
            } else if ($sentinel?.length) {
                // Setup intersection observer for infinite scroll
                const observer = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting && !loading && !allDataLoaded) {
                            loadManifesti(true);
                        }
                    });
                }, {threshold: 0.1});

                observer.observe($sentinel[0]);
                loadManifesti(true);
            } else {
                loadManifesti(true);
            }
        });
    });

})(jQuery);