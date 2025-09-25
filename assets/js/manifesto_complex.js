(function ($) {
    'use strict';
    
    // Configuration
    const CONFIG = {
        CONTAINER_SIZE: 0.8, // 80%
        FONT_BASE_MULTIPLIER: 0.1,
        PORTRAIT_FONT_REDUCE: 0.9,
        LINE_HEIGHT_MULTIPLIER: 1.1,
        NO_BG_LINE_HEIGHT: 1.3,
        MAX_FONT_ITERATIONS: 15, // Increased for binary search
        MIN_FONT_SIZE: 8, // Slightly increased for better readability
        MAX_FONT_SIZE: 120, // Maximum font size cap
        PRECISION: 0.5, // Font size precision in pixels
        TEXT_DENSITY: {
            MIN_FACTOR: 0.6, // Minimum text density reduction
            BASE_LENGTH: 100 // Base text length for density calculation
        }
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
    
    // Calculate responsive font size based on container dimensions and text density
    function calculateFontSize(width, height, textLength = CONFIG.TEXT_DENSITY.BASE_LENGTH) {
        const isPortrait = height > width;
        const area = width * height;
        let baseFontSize = Math.sqrt(area) * CONFIG.FONT_BASE_MULTIPLIER;
        
        // Adjust for text density - more text = smaller starting font
        const textDensityFactor = Math.min(1, Math.max(
            CONFIG.TEXT_DENSITY.MIN_FACTOR, 
            CONFIG.TEXT_DENSITY.BASE_LENGTH / Math.sqrt(textLength)
        ));
        baseFontSize *= textDensityFactor;
        
        // Apply orientation adjustment
        if (isPortrait) {
            baseFontSize *= CONFIG.PORTRAIT_FONT_REDUCE;
        }
        
        // Ensure size is within bounds
        return Math.min(CONFIG.MAX_FONT_SIZE, Math.max(CONFIG.MIN_FONT_SIZE, baseFontSize));
    }
    
    // Apply styles to manifesto container
    function applyStyles(data, containerElem, img = null) {
        const backgroundDiv = containerElem.find('.text-editor-background')[0];
        const textEditor = containerElem.find('.custom-text-editor')[0];
        
        if (!backgroundDiv || !textEditor) return;
        
        if (data.manifesto_background && img) {
            // Handle background image case
            setupBackgroundImage(data, containerElem, backgroundDiv, textEditor, img);
        } else {
            // Handle no background case
            setupNoBackground(data, containerElem, backgroundDiv, textEditor);
        }
    }
    
    function setupBackgroundImage(data, containerElem, backgroundDiv, textEditor, img) {
        const aspectRatio = img.width / img.height;
        backgroundDiv.style.backgroundImage = `url(${data.manifesto_background})`;
        
        // Calculate container dimensions
        const parentRect = containerElem[0].getBoundingClientRect();
        const containerWidth = parentRect.width || window.innerWidth * 0.8;
        const containerHeight = parentRect.height || window.innerHeight * 0.6;
        
        // Set dimensions based on aspect ratio - fit within container bounds
        const maxWidth = containerWidth * CONFIG.CONTAINER_SIZE;
        const maxHeight = containerHeight * CONFIG.CONTAINER_SIZE;
        
        // Calculate dimensions fitting both constraints
        let targetWidth, targetHeight;
        
        if (aspectRatio < 1) { // Portrait
            // Portrait: try width-based sizing first
            targetWidth = maxWidth;
            targetHeight = targetWidth / aspectRatio;
            
            // If height exceeds container, switch to height-based sizing
            if (targetHeight > maxHeight) {
                targetHeight = maxHeight;
                targetWidth = targetHeight * aspectRatio;
            }
        } else { // Landscape or square
            // Landscape: try width-based sizing first
            targetWidth = maxWidth;
            targetHeight = targetWidth / aspectRatio;
            
            // If height is too small (very wide images), use height-based sizing
            if (targetHeight < maxHeight * 0.4) { // Minimum 40% of available height
                targetHeight = maxHeight * 0.6; // Use 60% of available height
                targetWidth = targetHeight * aspectRatio;
                // If width exceeds, go back to width-based
                if (targetWidth > maxWidth) {
                    targetWidth = maxWidth;
                    targetHeight = targetWidth / aspectRatio;
                }
            }
        }
        
        backgroundDiv.style.width = `${targetWidth}px`;
        backgroundDiv.style.height = `${targetHeight}px`;
        
        // Apply margins as padding
        applyMargins(data, backgroundDiv, textEditor);
        
        // Calculate and apply font size based on content length
        const finalWidth = backgroundDiv.offsetWidth;
        const finalHeight = backgroundDiv.offsetHeight;
        const textLength = textEditor.textContent?.length || textEditor.innerText?.length || 100;
        const fontSize = calculateFontSize(finalWidth, finalHeight, textLength);
        
        setTextStyles(textEditor, fontSize, CONFIG.LINE_HEIGHT_MULTIPLIER, data.alignment);
        optimizeFontSize(textEditor, CONFIG.LINE_HEIGHT_MULTIPLIER);
    }
    
    function setupNoBackground(data, containerElem, backgroundDiv, textEditor) {
        backgroundDiv.style.backgroundImage = 'none';
        
        const containerWidth = containerElem.parent().width() || window.innerWidth;
        let fontSize;
        
        if (window.innerWidth < 480) {
            fontSize = Math.max(8, containerWidth * 0.02);
        } else if (window.innerWidth < 768) {
            fontSize = Math.max(10, containerWidth * 0.025);
        } else {
            fontSize = Math.max(14, containerWidth * 0.03);
        }
        
        setTextStyles(textEditor, fontSize, CONFIG.NO_BG_LINE_HEIGHT, data.alignment || 'center');
        optimizeFontSize(textEditor, CONFIG.NO_BG_LINE_HEIGHT);
    }
    
    function applyMargins(data, backgroundDiv, textEditor) {
        const width = backgroundDiv.offsetWidth;
        const height = backgroundDiv.offsetHeight;
        
        textEditor.style.paddingTop = `${(data.margin_top / 100) * height}px`;
        textEditor.style.paddingRight = `${(data.margin_right / 100) * width}px`;
        textEditor.style.paddingBottom = `${(data.margin_bottom / 100) * height}px`;
        textEditor.style.paddingLeft = `${(data.margin_left / 100) * width}px`;
    }
    
    function setTextStyles(textEditor, fontSize, lineHeightMultiplier, alignment) {
        Object.assign(textEditor.style, {
            fontSize: `${fontSize}px`,
            lineHeight: `${fontSize * lineHeightMultiplier}px`,
            textAlign: alignment || 'left',
            color: '#000',
            position: 'absolute',
            top: '0',
            left: '0',
            width: '100%',
            height: '100%'
        });
    }
    
    function optimizeFontSize(textEditor, lineHeightMultiplier) {
        requestAnimationFrame(() => {
            const availableHeight = textEditor.clientHeight;
            const availableWidth = textEditor.clientWidth;
            
            if (!availableHeight || !availableWidth) return;
            
            let currentFontSize = parseFloat(textEditor.style.fontSize);
            let iterations = 0;
            const maxIterations = 15;
            
            // Binary search approach for optimal font size
            let minFont = CONFIG.MIN_FONT_SIZE;
            let maxFont = Math.min(CONFIG.MAX_FONT_SIZE, currentFontSize * 1.5); // Allow some increase if space permits
            
            while (iterations < CONFIG.MAX_FONT_ITERATIONS) {
                const testFontSize = (minFont + maxFont) / 2;
                textEditor.style.fontSize = `${testFontSize}px`;
                textEditor.style.lineHeight = `${testFontSize * lineHeightMultiplier}px`;
                
                const contentHeight = textEditor.scrollHeight;
                const contentWidth = textEditor.scrollWidth;
                
                // Check if content fits both height and width with some margin
                const fitsHeight = contentHeight <= availableHeight * 0.98; // 2% margin
                const fitsWidth = contentWidth <= availableWidth * 0.98; // 2% margin
                const fits = fitsHeight && fitsWidth;
                
                if (fits) {
                    minFont = testFontSize; // Content fits, try larger
                } else {
                    maxFont = testFontSize; // Content doesn't fit, try smaller
                }
                
                // Stop if we're close enough
                if (maxFont - minFont < CONFIG.PRECISION) break;
                
                iterations++;
            }
            
            // Apply the optimal font size (slightly smaller for safety)
            const optimalFontSize = Math.max(CONFIG.MIN_FONT_SIZE, minFont - CONFIG.PRECISION);
            textEditor.style.fontSize = `${optimalFontSize}px`;
            textEditor.style.lineHeight = `${optimalFontSize * lineHeightMultiplier}px`;
        });
    }
    
    // Main function to update manifesto
    function updateManifesto(data, containerElem) {
        if (!data || !containerElem?.length) return;
        
        // Store for resize handling
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
    
    // Resize handler
    function handleResize() {
        requestAnimationFrame(() => {
            manifestiData.forEach((manifestInfo, manifestoId) => {
                const { data, containerElem } = manifestInfo;
                
                if (containerElem?.length && $.contains(document, containerElem[0])) {
                    if (data.manifesto_background) {
                        loadImage(data.manifesto_background)
                            .then(img => applyStyles(data, containerElem, img))
                            .catch(() => applyStyles(data, containerElem));
                    } else {
                        applyStyles(data, containerElem);
                    }
                } else {
                    manifestiData.delete(manifestoId);
                }
            });
        });
    }
    
    // Initialize on document ready
    $(document).ready(function () {
        let resizeRAF;
        
        // Setup resize handler with debouncing
        $(window).on('resize', () => {
            if (resizeRAF) cancelAnimationFrame(resizeRAF);
            resizeRAF = requestAnimationFrame(handleResize);
        });
        
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
                                
                                if (item.vendor_data) {
                                    updateManifesto(item.vendor_data, newElement);
                                }
                            }
                        });
                        
                        offset += response.data.length;
                        loading = false;
                        $loader?.hide();
                        
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