(function ($) {
    'use strict';
    
    // Simple configuration
    const CONFIG = {
        CONTAINER_SIZE: 0.95, // 80%
        MAX_FONT_SIZE: 50, // Hard limit in px
        MIN_FONT_SIZE: 4,  // Minimum readable size
        LINE_HEIGHT_RATIO: 1.2
    };
    
    // Image cache for performance
    const imageCache = new Map();
    const manifestiData = new Map();
    
    // Load image with caching
    function loadImage(url) {
        if (imageCache.has(url)) {
            return Promise.resolve(imageCache.get(url));
        }
        
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                imageCache.set(url, img);
                resolve(img);
            };
            img.onerror = () => reject(new Error(`Failed to load: ${url}`));
            img.src = url;
        });
    }
    
    
    // Apply manifesto styles - VERY SIMPLIFIED
    function applyStyles(data, containerElem, img = null) {
        const backgroundDiv = containerElem.find('.text-editor-background')[0];
        const textEditor = containerElem.find('.custom-text-editor')[0];
        
        if (!backgroundDiv || !textEditor) return;
        
        if (data.manifesto_background && img) {
            setupBackground(backgroundDiv, textEditor, data, img);
        } else {
            setupNoBackground(backgroundDiv, textEditor, data);
        }
    }
    
    function setupBackground(backgroundDiv, textEditor, data, img) {
        const aspectRatio = img.width / img.height;
        
        // Set background image
        backgroundDiv.style.backgroundImage = `url(${data.manifesto_background})`;
        
        // CSS-based responsive sizing with aspect ratio
        backgroundDiv.style.aspectRatio = `${img.width} / ${img.height}`;
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto'; // Let CSS handle height via aspect-ratio
        backgroundDiv.style.maxWidth = '100%';
        backgroundDiv.style.maxHeight = '80vh'; // Prevent too tall images
        
        // Calculate margins as percentages of image dimensions (like old system)
        const marginTop = data.margin_top || 0;
        const marginRight = data.margin_right || 0;
        const marginBottom = data.margin_bottom || 0;
        const marginLeft = data.margin_left || 0;
        
        // Apply margins as padding percentages - CSS will scale automatically
        textEditor.style.padding = `${marginTop}% ${marginRight}% ${marginBottom}% ${marginLeft}%`;
        textEditor.style.textAlign = data.alignment || 'left';
        
        // Set CSS custom properties for responsive font sizing
        backgroundDiv.style.setProperty('--max-font-size', `${CONFIG.MAX_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--min-font-size', `${CONFIG.MIN_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--line-height-ratio', CONFIG.LINE_HEIGHT_RATIO);
    }
    
    function setupNoBackground(backgroundDiv, textEditor, data) {
        backgroundDiv.style.backgroundImage = 'none';
        backgroundDiv.style.aspectRatio = '16 / 9'; // Default A3 ratio
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto';
        
        // Simple padding for no-background case
        textEditor.style.padding = '5%';
        textEditor.style.textAlign = data.alignment || 'center';
        
        // Set CSS custom properties
        backgroundDiv.style.setProperty('--max-font-size', `${CONFIG.MAX_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--min-font-size', `${CONFIG.MIN_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--line-height-ratio', CONFIG.LINE_HEIGHT_RATIO);
    }
    
    // Main function to update manifesto
    function updateManifesto(data, containerElem) {
        if (!data || !containerElem?.length) return;
        
        // Store for potential future use
        const manifestoId = containerElem.attr('id') || `manifesto-${Date.now()}`;
        if (!containerElem.attr('id')) {
            containerElem.attr('id', manifestoId);
        }
        manifestiData.set(manifestoId, { data, containerElem });
        
        const textEditor = containerElem.find('.custom-text-editor')[0];
        
        if (data.manifesto_background) {
            if (textEditor) textEditor.classList.add('loading');
            
            loadImage(data.manifesto_background)
                .then(img => {
                    applyStyles(data, containerElem, img);
                    if (textEditor) textEditor.classList.remove('loading');
                })
                .catch(() => {
                    applyStyles(data, containerElem);
                    if (textEditor) textEditor.classList.remove('loading');
                });
        } else {
            if (textEditor) textEditor.classList.remove('loading');
            applyStyles(data, containerElem);
        }
    }
    
    
    // Initialize on document ready
    $(document).ready(function () {
        
        // Fix mobile layout on initialization
        if (window.innerWidth <= 639) {
            $('.manifesto-container').each(function() {
                $(this).css({
                    'position': 'relative',
                    'display': 'block',
                    'height': 'auto',
                    'min-height': 'min-content',
                    'overflow': 'visible',
                    'margin-bottom': '2rem'
                });
            });
        }
        
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
                        if (!response.success || !response.data?.length) {
                            allDataLoaded = true;
                            $sentinel?.remove();
                            $loader?.hide();
                            return;
                        }
                        
                        response.data.forEach(item => {
                            if (item?.html) {
                                const newElement = $(item.html);
                                container.append(newElement);
                                
                                // Show manifesto divider for the section
                                container.parent().parent().parent().parent().find('.manifesto_divider').show();
                                
                                if (item.vendor_data) {
                                    updateManifesto(item.vendor_data, newElement);
                                }
                            }
                        });
                        
                        offset += response.data.length;
                        loading = false;
                        $loader?.hide();
                        
                        // Force layout recalculation after adding new items (especially for mobile)
                        requestAnimationFrame(() => {
                            // Trigger reflow on container and its parents
                            container[0].offsetHeight;
                            container.parent()[0].offsetHeight;
                            
                            // Force height recalculation on mobile
                            if (window.innerWidth <= 639) {
                                container.css({
                                    'min-height': 'min-content',
                                    'height': 'auto',
                                    'position': 'relative',
                                    'display': 'block',
                                    'overflow': 'visible'
                                });
                                container.parent().css({
                                    'min-height': 'min-content',
                                    'height': 'auto',
                                    'position': 'relative'
                                });
                                
                                // Ensure no overlapping with footer by adding explicit margin
                                if (container.is(':last-child') || container.siblings('.loader, .sentinel').length === 0) {
                                    container.css('margin-bottom', '2rem');
                                }
                            }
                        });
                        
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
                }, { threshold: 0.1 });
                
                observer.observe($sentinel[0]);
                loadManifesti(true);
            } else {
                loadManifesti(true);
            }
        });
    });
    
})(jQuery);