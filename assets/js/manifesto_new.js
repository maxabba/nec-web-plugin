(function ($) {
    'use strict';
    
    // Configuration with all magic numbers extracted
    const CONFIG = {
        CONTAINER_SIZE: 0.95,
        MAX_FONT_SIZE: 20, // Hard limit in px
        MIN_FONT_SIZE: 8,  // Minimum readable size
        LINE_HEIGHT_RATIO: 1.2,
        
        // Font scaling factors for different text types
        FONT_SCALING: {
            SHORT_TEXT_FACTOR: 0.6,
            LONG_TEXT_FACTOR: 2.2,
            WIDE_TEXT_FACTOR_UNDER_100: 0.5,
            WIDE_TEXT_FACTOR_OVER_100: 0.4,
            MEDIUM_TEXT_FACTOR: 0.4,
            HEIGHT_FACTOR: 3.5
        },
        
        // Text classification thresholds
        TEXT_THRESHOLDS: {
            SHORT_TEXT_CHARS: 100,
            SHORT_TEXT_LINES: 10,
            WIDE_TEXT_CHARS: 100
        },
        
        // Optimization settings
        OPTIMIZATION: {
            MAX_ITERATIONS: 25,
            FONT_INCREMENT: 0.5,
            RESIZE_DEBOUNCE: 50,
            INITIAL_SETUP_DELAY: 100
        },
        
        // Layout settings
        LAYOUT: {
            DEFAULT_ASPECT_RATIO: '16 / 9',
            MAX_HEIGHT: '80vh',
            NO_BACKGROUND_PADDING: '5%',
            // Firefox-specific fixes
            FIREFOX_HEIGHT_FALLBACK: '75vh' // Slightly smaller for Firefox compatibility
        }
    };
    
    // Module state - encapsulated variables
    const ModuleState = {
        imageCache: new Map(),
        manifestiData: new Map(),
        textAnalysisCache: new Map(),
        resizeTimeouts: new Map(),
        // New: Store initial sizing states for proportional scaling
        initialSizingStates: new Map()
    };
    
    // Load image with caching
    function loadImage(url) {
        if (!url) {
            return Promise.reject(new Error('Invalid URL provided'));
        }
        
        if (ModuleState.imageCache.has(url)) {
            return Promise.resolve(ModuleState.imageCache.get(url));
        }
        
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                try {
                    ModuleState.imageCache.set(url, img);
                    resolve(img);
                } catch (error) {
                    reject(new Error(`Failed to cache image: ${error.message}`));
                }
            };
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
    
    
    // TextAnalyzer class - encapsulates all text analysis logic
    class TextAnalyzer {
        constructor() {
            this.cache = ModuleState.textAnalysisCache;
        }
        
        generateTextId(textEditor) {
            if (!textEditor) return null;
            
            const text = textEditor.textContent || textEditor.innerText || '';
            const html = textEditor.innerHTML || '';
            
            // Create simple hash of content for unique ID
            let hash = 0;
            const content = text + html;
            for (let i = 0; i < content.length; i++) {
                const char = content.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32-bit integer
            }
            
            return `text_${Math.abs(hash)}`;
        }
        
        parseTextStructure(textEditor) {
            if (!textEditor) return null;
            
            const text = textEditor.textContent || textEditor.innerText || '';
            const divElements = textEditor.querySelectorAll('div');
            
            return {
                text,
                totalLength: text.length,
                divElements,
                divCount: divElements.length
            };
        }
        
        calculateTextMetrics(structure) {
            if (!structure) return null;
            
            const { text, divElements } = structure;
            let actualLines = 0;
            let longestLineLength = 0;
            let longestLineNumber = 0;
            let currentLine = 0;
            
            if (divElements.length > 0) {
                // Analyze each div and count internal lines (br, \n)
                divElements.forEach((div) => {
                    const htmlDiv = div.innerHTML;
                    const textDiv = div.textContent || div.innerText || '';
                    
                    // Count <br> tags and \n characters inside this div
                    const brCount = (htmlDiv.match(/<br\s*\/?>/gi) || []).length;
                    const nlCount = (textDiv.match(/\n/g) || []).length;
                    const linesInDiv = Math.max(1, brCount + nlCount + 1); // +1 for base line of div
                    
                    actualLines += linesInDiv;
                    
                    // For longest line, split div content by br/\n
                    const subLines = textDiv.split(/\n/);
                    subLines.forEach((subLine) => {
                        currentLine++;
                        if (subLine.length > longestLineLength) {
                            longestLineLength = subLine.length;
                            longestLineNumber = currentLine;
                        }
                    });
                });
            } else {
                // Fallback: if no divs, analyze lines separated by \n or <br>
                const linesArray = text.split(/\n|<br\s*\/?>/i);
                actualLines = linesArray.length;
                
                linesArray.forEach((line, index) => {
                    if (line.length > longestLineLength) {
                        longestLineLength = line.length;
                        longestLineNumber = index + 1;
                    }
                });
            }
            
            const lines = Math.max(actualLines, 1); // Minimum 1 line
            
            return {
                totalLength: structure.totalLength,
                longestLineLength,
                longestLineNumber,
                lines,
                divCount: structure.divCount
            };
        }
        
        categorizeText(metrics) {
            if (!metrics) return null;
            
            return {
                isShortText: metrics.longestLineLength <= CONFIG.TEXT_THRESHOLDS.SHORT_TEXT_CHARS && metrics.lines <= CONFIG.TEXT_THRESHOLDS.SHORT_TEXT_LINES,
                isLongText: metrics.lines > CONFIG.TEXT_THRESHOLDS.SHORT_TEXT_LINES,
                isWideText: metrics.longestLineLength > CONFIG.TEXT_THRESHOLDS.WIDE_TEXT_CHARS
            };
        }
        
        cacheAnalysis(textId, analysis) {
            if (!textId || !analysis) return;
            
            this.cache.set(textId, {
                ...analysis,
                timestamp: Date.now()
            });
            
            console.log(`💾 Cached analysis for ID: ${textId} (${analysis.lines} lines, max line: ${analysis.longestLineLength})`);
        }
        
        getCachedAnalysis(textId) {
            return this.cache.get(textId) || null;
        }
        
        hasCachedAnalysis(textId) {
            return this.cache.has(textId);
        }
        
        analyzeText(textEditor, forceRecalculate = false) {
            if (!textEditor) {
                console.error('❌ Invalid textEditor provided to analyzeText');
                return null;
            }
            
            const textId = this.generateTextId(textEditor);
            if (!textId) {
                console.error('❌ Could not generate textId');
                return null;
            }
            
            // Check if we already have the analysis in cache
            if (!forceRecalculate && this.hasCachedAnalysis(textId)) {
                const cachedAnalysis = this.getCachedAnalysis(textId);
                console.log(`💾 Cache hit for text ID: ${textId}`);
                return cachedAnalysis;
            }
            
            console.log(`🔍 Text analysis for ID: ${textId}`);
            
            try {
                const structure = this.parseTextStructure(textEditor);
                if (!structure) {
                    throw new Error('Failed to parse text structure');
                }
                
                const metrics = this.calculateTextMetrics(structure);
                if (!metrics) {
                    throw new Error('Failed to calculate text metrics');
                }
                
                const categories = this.categorizeText(metrics);
                if (!categories) {
                    throw new Error('Failed to categorize text');
                }
                
                const analysis = {
                    id: textId,
                    totalLength: metrics.totalLength,
                    longestLineLength: metrics.longestLineLength,
                    longestLineNumber: metrics.longestLineNumber,
                    lines: metrics.lines,
                    divCount: metrics.divCount,
                    isShortText: categories.isShortText,
                    isLongText: categories.isLongText,
                    isWideText: categories.isWideText,
                    timestamp: Date.now()
                };
                
                // Cache the analysis
                this.cacheAnalysis(textId, analysis);
                
                // Associate the ID with the element for future reference
                textEditor.dataset.textAnalysisId = textId;
                
                return analysis;
                
            } catch (error) {
                console.error(`❌ Error analyzing text: ${error.message}`);
                return null;
            }
        }
    }
    
    // Create global text analyzer instance
    const textAnalyzer = new TextAnalyzer();
    
    // Backward compatibility function - use TextAnalyzer instance
    function analyzeText(textEditor, forceRecalculate = false) {
        return textAnalyzer.analyzeText(textEditor, forceRecalculate);
    }
    
    // Ultra-fast proportional scaling for resize
    function scaleFontSizeProportionally(textEditor, backgroundDiv, containerId) {
        if (!textEditor || !backgroundDiv || !containerId) return false;
        
        // Get initial state
        const initialState = ModuleState.initialSizingStates.get(containerId);
        if (!initialState) {
            console.log(`⚠️ No initial state for container: ${containerId}`);
            return false;
        }
        
        // Check if text content has changed (optional validation)
        const currentTextId = textEditor.dataset.textAnalysisId;
        if (initialState.textHash && currentTextId && initialState.textHash !== currentTextId) {
            console.log(`🔄 Text content changed, clearing state for: ${containerId}`);
            clearInitialSizingState(containerId);
            return false; // Force recalculation
        }
        
        // Get current dimensions
        const currentWidth = backgroundDiv.clientWidth;
        const currentHeight = backgroundDiv.clientHeight;
        
        // Calculate scale factor (use the smaller scale to maintain proportions)
        const scaleX = currentWidth / initialState.width;
        const scaleY = currentHeight / initialState.height;
        const scaleFactor = Math.min(scaleX, scaleY);
        
        // Apply proportional scaling
        let newFontSize = initialState.fontSize * scaleFactor;
        
        // Apply limits
        newFontSize = Math.max(CONFIG.MIN_FONT_SIZE, Math.min(CONFIG.MAX_FONT_SIZE, newFontSize));
        
        // Apply to element
        textEditor.style.fontSize = newFontSize + 'px';
        textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
        
        // Log only if significant change (reduce console noise)
        const fontChange = Math.abs(newFontSize - initialState.fontSize * scaleFactor);
        if (fontChange > 0.5 || CONFIG.OPTIMIZATION.RESIZE_DEBOUNCE) {
            console.log(`⚡ Scale: ${newFontSize.toFixed(1)}px (${scaleFactor.toFixed(2)}x) | ${currentWidth}x${currentHeight}`);
        }
        
        return true;
    }
    
    // Store initial sizing state for future scaling
    function storeInitialSizingState(containerId, fontSize, width, height, textHash = null) {
        if (!containerId) return;
        
        const state = {
            fontSize: fontSize,
            width: width,
            height: height,
            aspectRatio: width / height,
            textHash: textHash, // To detect content changes
            timestamp: Date.now()
        };
        
        ModuleState.initialSizingStates.set(containerId, state);
        console.log(`📏 Stored initial state for ${containerId}: ${fontSize.toFixed(1)}px @ ${width}x${height}`);
    }
    
    // Clear initial state (useful when content changes)
    function clearInitialSizingState(containerId) {
        if (ModuleState.initialSizingStates.has(containerId)) {
            ModuleState.initialSizingStates.delete(containerId);
            console.log(`🗎️ Cleared initial state for ${containerId}`);
        }
    }
    
    // FontSizeCalculator class with strategy pattern
    class FontSizeCalculator {
        constructor(config) {
            this.config = config;
        }
        
        calculateFromAnalysis(analysis, maxWidth, maxHeight) {
            if (!analysis || !maxWidth || !maxHeight) {
                console.error('❌ Invalid parameters for font size calculation');
                return this.config.MIN_FONT_SIZE;
            }
            
            let fontSize;
            
            if (analysis.isShortText) {
                fontSize = this.calculateShortTextStrategy(analysis, maxWidth);
            } else if (analysis.isLongText) {
                fontSize = this.calculateLongTextStrategy(analysis, maxHeight);
            } else if (analysis.isWideText) {
                fontSize = this.calculateWideTextStrategy(analysis, maxWidth, maxHeight);
            } else {
                fontSize = this.calculateMediumTextStrategy(analysis, maxWidth, maxHeight);
            }
            
            return this.applyLimits(fontSize);
        }
        
        calculateShortTextStrategy(analysis, maxWidth) {
            const lineFactor = Math.max(analysis.longestLineLength, 1);
            return maxWidth / (lineFactor * this.config.FONT_SCALING.SHORT_TEXT_FACTOR);
        }
        
        calculateLongTextStrategy(analysis, maxHeight) {
            return maxHeight / (analysis.lines * this.config.FONT_SCALING.LONG_TEXT_FACTOR);
        }
        
        calculateWideTextStrategy(analysis, maxWidth, maxHeight) {
            const lineFactor = analysis.longestLineLength;
            
            if(lineFactor < this.config.TEXT_THRESHOLDS.WIDE_TEXT_CHARS) {
                return Math.min(
                    maxWidth / (lineFactor * this.config.FONT_SCALING.WIDE_TEXT_FACTOR_UNDER_100),
                    maxHeight / (analysis.lines * this.config.FONT_SCALING.HEIGHT_FACTOR)
                );
            } else {
                return Math.max(
                    maxWidth / (lineFactor * this.config.FONT_SCALING.WIDE_TEXT_FACTOR_OVER_100),
                    maxHeight / (analysis.lines * this.config.FONT_SCALING.HEIGHT_FACTOR)
                );
            }
        }
        
        calculateMediumTextStrategy(analysis, maxWidth, maxHeight) {
            return Math.min(
                maxWidth / (analysis.longestLineLength * this.config.FONT_SCALING.MEDIUM_TEXT_FACTOR),
                maxHeight / (analysis.lines * this.config.FONT_SCALING.HEIGHT_FACTOR)
            );
        }
        
        applyLimits(fontSize) {
            return Math.max(this.config.MIN_FONT_SIZE, Math.min(this.config.MAX_FONT_SIZE, fontSize));
        }
        
        optimizeIteratively(textEditor, initialFontSize, analysis, maxWidth, maxHeight) {
            if (!textEditor) return initialFontSize;
            
            let fontSize = initialFontSize;
            let iterations = 0;
            const maxIterations = this.config.OPTIMIZATION.MAX_ITERATIONS;
            
            // Apply initial font size
            textEditor.style.fontSize = fontSize + 'px';
            textEditor.style.lineHeight = this.config.LINE_HEIGHT_RATIO;
            
            // First phase: reduce if doesn't fit
            while (
                (textEditor.scrollHeight > maxHeight || textEditor.scrollWidth > maxWidth) 
                && fontSize > this.config.MIN_FONT_SIZE 
                && iterations < maxIterations
            ) {
                fontSize -= this.config.OPTIMIZATION.FONT_INCREMENT;
                textEditor.style.fontSize = fontSize + 'px';
                iterations++;
                void textEditor.offsetHeight; // Force reflow
            }
            
            // Second phase: for short texts, try to increase if there's space
            if (analysis.isShortText && iterations < maxIterations) {
                let testFontSize = fontSize;
                while (
                    textEditor.scrollHeight <= maxHeight && 
                    textEditor.scrollWidth <= maxWidth &&
                    testFontSize < this.config.MAX_FONT_SIZE &&
                    iterations < maxIterations
                ) {
                    fontSize = testFontSize;
                    testFontSize += this.config.OPTIMIZATION.FONT_INCREMENT;
                    textEditor.style.fontSize = testFontSize + 'px';
                    iterations++;
                    void textEditor.offsetHeight; // Force reflow
                }
                // Return to last valid size
                textEditor.style.fontSize = fontSize + 'px';
            }
            
            return fontSize;
        }
    }
    
    // Create global font calculator instance
    const fontCalculator = new FontSizeCalculator(CONFIG);
    
    // Backward compatibility function
    function calculateFontSizeFromAnalysis(analysis, maxWidth, maxHeight) {
        return fontCalculator.calculateFromAnalysis(analysis, maxWidth, maxHeight);
    }
    
    // Adapt font size intelligently based on content (complete)
    function adaptFontSize(textEditor, backgroundDiv, forceRecalculate = false) {
        if (!textEditor || !backgroundDiv) return;
        
        const maxHeight = backgroundDiv.clientHeight;
        const maxWidth = backgroundDiv.clientWidth;
        const analysis = analyzeText(textEditor, forceRecalculate);
        if (!analysis) {
            console.error('❌ Failed to analyze text, aborting font size calculation');
            return;
        }
        
        // Font sizing strategy based on content
        const initialFontSize = fontCalculator.calculateFromAnalysis(analysis, maxWidth, maxHeight);
        
        // Log strategy for debugging
        let strategy = 'MEDIUM TEXT - intelligent balancing';
        if (analysis.isShortText) {
            strategy = `SHORT TEXT - optimize for width (longest line: ${analysis.longestLineLength} chars)`;
        } else if (analysis.isLongText) {
            strategy = `LONG TEXT - optimize for height (${analysis.lines} lines)`;
        } else if (analysis.isWideText) {
            strategy = `WIDE TEXT - balancing (longest line: ${analysis.longestLineLength} chars)`;
        }
        console.log(`Strategy: ${strategy}`);
        
        // Iterative optimization for perfect fit
        const fontSize = fontCalculator.optimizeIteratively(
            textEditor, 
            initialFontSize, 
            analysis, 
            maxWidth, 
            maxHeight
        );
        
        // Detailed log for debugging
        console.log(`Font optimized: ${fontSize}px | Analysis: ${analysis.lines} total lines (${analysis.divCount} divs), longest line: ${analysis.longestLineLength} chars (line #${analysis.longestLineNumber}) | Container: ${maxWidth}x${maxHeight}`);
        
        // Store initial state for proportional scaling on resize
        const containerElem = $(backgroundDiv).closest('.flex-item');
        const containerId = containerElem.attr('id') || `container-${Date.now()}`;
        if (!containerElem.attr('id')) {
            containerElem.attr('id', containerId);
        }
        // Include text hash to detect content changes
        const textHash = analysis ? analysis.id : null;
        storeInitialSizingState(containerId, fontSize, maxWidth, maxHeight, textHash);
    }
    
    // Responsive system functions
    
    function setupResponsiveFontSize(textEditor, backgroundDiv) {
        if (!textEditor || !backgroundDiv) return;
        
        const containerElem = $(backgroundDiv).closest('.flex-item');
        const containerId = containerElem.attr('id') || `container-${Date.now()}`;
        
        // Recalculate font size with intelligent cache
        function handleResize() {
            // Clear previous timeout for this container
            if (ModuleState.resizeTimeouts.has(containerId)) {
                clearTimeout(ModuleState.resizeTimeouts.get(containerId));
            }
            
            // Set new timeout
            ModuleState.resizeTimeouts.set(containerId, setTimeout(() => {
                console.log(`🔄 Intelligent resize for container ${containerId}`);
                
                // Try ultra-fast proportional scaling first
                const usedScaling = scaleFontSizeProportionally(textEditor, backgroundDiv, containerId);
                
                if (!usedScaling) {
                    // Fallback to complete calculation if no initial state
                    console.log(`🐌 Fallback to complete calculation for container ${containerId}`);
                    adaptFontSize(textEditor, backgroundDiv);
                }
                
                ModuleState.resizeTimeouts.delete(containerId);
            }, CONFIG.OPTIMIZATION.RESIZE_DEBOUNCE));
        }
        
        // Observer for text-editor-background dimension changes
        if (window.ResizeObserver) {
            const resizeObserver = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    if (entry.target === backgroundDiv) {
                        handleResize();
                        break;
                    }
                }
            });
            
            resizeObserver.observe(backgroundDiv);
            
            // Store observer for future cleanup
            backgroundDiv._fontResizeObserver = resizeObserver;
        } else {
            // Fallback for browsers without ResizeObserver - use window resize
            $(window).on(`resize.responsiveFont.${containerId}`, handleResize);
        }
        
        // Initial calculation - same identical process
        setTimeout(() => {
            adaptFontSize(textEditor, backgroundDiv);
        }, CONFIG.OPTIMIZATION.INITIAL_SETUP_DELAY);
    }
    
    // Apply manifesto styles - VERY SIMPLIFIED
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
            
            // Setup responsive system - recalculate on resize
            setupResponsiveFontSize(textEditor, backgroundDiv);
            
        } catch (error) {
            console.error(`❌ Error applying styles: ${error.message}`);
        }
    }
    
    function setupBackground(backgroundDiv, textEditor, data, img) {
        const aspectRatio = img.width / img.height;
        
        // Set background image
        backgroundDiv.style.backgroundImage = `url(${data.manifesto_background})`;
        
        // CSS-based responsive sizing with aspect ratio (original simple approach)
        backgroundDiv.style.aspectRatio = `${img.width} / ${img.height}`;
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto'; // Let CSS handle height via aspect-ratio
        backgroundDiv.style.maxWidth = '100%';
        backgroundDiv.style.maxHeight = CONFIG.LAYOUT.MAX_HEIGHT;
        
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
        backgroundDiv.style.aspectRatio = CONFIG.LAYOUT.DEFAULT_ASPECT_RATIO;
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto';
        
        // Simple padding for no-background case
        textEditor.style.padding = CONFIG.LAYOUT.NO_BACKGROUND_PADDING;
        textEditor.style.textAlign = data.alignment || 'center';
        
        // Set CSS custom properties
        backgroundDiv.style.setProperty('--max-font-size', `${CONFIG.MAX_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--min-font-size', `${CONFIG.MIN_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--line-height-ratio', CONFIG.LINE_HEIGHT_RATIO);
    }
    
    // Main function to update manifesto
    function updateManifesto(data, containerElem) {
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
            ModuleState.manifestiData.set(manifestoId, { data, containerElem });
            
            const textEditor = containerElem.find('.custom-text-editor')[0];
            
            if (data.manifesto_background) {
                if (textEditor) textEditor.classList.add('loading');
                
                loadImage(data.manifesto_background)
                    .then(img => {
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
    
    
    // Initialize on document ready
    $(document).ready(function () {
        
        // No mobile forcing necessary - CSS Grid handles everything automatically
        
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
                        
                        // Process manifesti and count only real manifesti (not dividers)
                        let realManifestiCount = 0;
                        response.data.forEach(item => {
                            if (item?.html) {
                                const newElement = $(item.html);
                                container.append(newElement);
                                
                                // Show manifesto divider for the section
                                container.parent().parent().parent().parent().find('.manifesto_divider').show();
                                
                                // Count only real manifesti, not dividers
                                if (item.vendor_data) {
                                    updateManifesto(item.vendor_data, newElement);
                                    realManifestiCount++;
                                }
                                // Dividers have vendor_data = null, so they won't be counted
                            }
                        });
                        
                        offset += realManifestiCount;
                        loading = false;
                        $loader?.hide();
                        
                        // Automatic CSS Grid layout - no forcing necessary
                        
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