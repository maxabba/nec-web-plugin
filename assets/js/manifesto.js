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
            INITIAL_SETUP_DELAY: 100,
            // Mobile-specific delays
            MOBILE_SETUP_DELAY: 300,
            MOBILE_RECHECK_DELAY: 500,
            MOBILE_FORCE_REFLOW_DELAY: 50
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
        initialSizingStates: new Map(),
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

            const {text, divElements} = structure;
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

    // Mobile detection and utilities
    const MobileUtils = {
        isMobile() {
            return window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },
        
        isIOS() {
            return /iPad|iPhone|iPod/.test(navigator.userAgent);
        },
        
        forceReflow(element) {
            if (element) {
                // Multiple reflow triggers for mobile
                void element.offsetHeight;
                void element.offsetWidth;
                void element.scrollHeight;
                void element.scrollWidth;
            }
        },
        
        waitForStableSize(element, callback, maxAttempts = 10) {
            if (!element || !callback) return;
            
            let attempts = 0;
            let lastWidth = 0;
            let lastHeight = 0;
            
            const checkSize = () => {
                attempts++;
                const currentWidth = element.clientWidth;
                const currentHeight = element.clientHeight;
                
                if ((currentWidth === lastWidth && currentHeight === lastHeight && currentWidth > 0) || attempts >= maxAttempts) {
                    console.log(`📱 Size stable after ${attempts} attempts: ${currentWidth}x${currentHeight}`);
                    callback();
                    return;
                }
                
                lastWidth = currentWidth;
                lastHeight = currentHeight;
                setTimeout(checkSize, CONFIG.OPTIMIZATION.MOBILE_FORCE_REFLOW_DELAY);
            };
            
            checkSize();
        }
    };

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

            if (lineFactor < this.config.TEXT_THRESHOLDS.WIDE_TEXT_CHARS) {
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

        optimizeIterativelyMobile(textEditor, initialFontSize, analysis, maxWidth, maxHeight) {
            if (!textEditor) return initialFontSize;

            let fontSize = initialFontSize;
            let iterations = 0;
            const maxIterations = this.config.OPTIMIZATION.MAX_ITERATIONS;

            // Apply initial font size with forced reflow
            textEditor.style.fontSize = fontSize + 'px';
            textEditor.style.lineHeight = this.config.LINE_HEIGHT_RATIO;
            MobileUtils.forceReflow(textEditor);

            console.log(`📱 Starting mobile optimization from ${fontSize}px`);

            // First phase: reduce if doesn't fit (with extra checks for mobile)
            while (
                (textEditor.scrollHeight > maxHeight || textEditor.scrollWidth > maxWidth)
                && fontSize > this.config.MIN_FONT_SIZE
                && iterations < maxIterations
                ) {
                fontSize -= this.config.OPTIMIZATION.FONT_INCREMENT;
                textEditor.style.fontSize = fontSize + 'px';
                
                // Extra reflow for mobile reliability
                MobileUtils.forceReflow(textEditor);
                
                iterations++;
                console.log(`📱 Iteration ${iterations}: reduced to ${fontSize}px (scroll: ${textEditor.scrollHeight}/${maxHeight}, width: ${textEditor.scrollWidth}/${maxWidth})`);
            }

            // Second phase: for short texts, try to increase if there's space (mobile-adapted)
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
                    
                    // Mobile-specific reflow
                    MobileUtils.forceReflow(textEditor);
                    
                    iterations++;
                    console.log(`📱 Expansion iteration ${iterations}: testing ${testFontSize}px`);
                }
                // Return to last valid size
                textEditor.style.fontSize = fontSize + 'px';
                MobileUtils.forceReflow(textEditor);
            }

            console.log(`📱 Mobile optimization completed: ${fontSize}px after ${iterations} iterations`);
            return fontSize;
        }
    }

    // Create global font calculator instance
    const fontCalculator = new FontSizeCalculator(CONFIG);

    // Backward compatibility function
    function calculateFontSizeFromAnalysis(analysis, maxWidth, maxHeight) {
        return fontCalculator.calculateFromAnalysis(analysis, maxWidth, maxHeight);
    }

    // Adapt font size intelligently based on content (complete) with mobile optimizations
    function adaptFontSize(textEditor, backgroundDiv, forceRecalculate = false) {
        if (!textEditor || !backgroundDiv) return;

        // Mobile-specific handling
        if (MobileUtils.isMobile()) {
            console.log('📱 Mobile device detected, using enhanced font sizing');
            adaptFontSizeMobile(textEditor, backgroundDiv, forceRecalculate);
            return;
        }

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

    // Mobile-specific font sizing with enhanced reliability
    function adaptFontSizeMobile(textEditor, backgroundDiv, forceRecalculate = false) {
        if (!textEditor || !backgroundDiv) return;

        console.log('📱 Starting mobile font adaptation');

        // Wait for stable dimensions before calculating
        MobileUtils.waitForStableSize(backgroundDiv, () => {
            console.log('📱 Container size stabilized, proceeding with font calculation');
            
            // Force reflow to ensure accurate measurements
            MobileUtils.forceReflow(backgroundDiv);
            MobileUtils.forceReflow(textEditor);

            const maxHeight = backgroundDiv.clientHeight;
            const maxWidth = backgroundDiv.clientWidth;

            if (maxWidth === 0 || maxHeight === 0) {
                console.warn('📱 Container has zero dimensions, retrying...');
                setTimeout(() => adaptFontSizeMobile(textEditor, backgroundDiv, forceRecalculate), CONFIG.OPTIMIZATION.MOBILE_RECHECK_DELAY);
                return;
            }

            console.log(`📱 Mobile container dimensions: ${maxWidth}x${maxHeight}`);

            const analysis = analyzeText(textEditor, forceRecalculate);
            if (!analysis) {
                console.error('❌ Failed to analyze text on mobile');
                return;
            }

            // Use more conservative calculations for mobile
            const initialFontSize = fontCalculator.calculateFromAnalysis(analysis, maxWidth, maxHeight);
            
            // Apply font with forced reflows
            textEditor.style.fontSize = initialFontSize + 'px';
            textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
            MobileUtils.forceReflow(textEditor);

            // Mobile-specific iterative optimization with extra reflows
            const fontSize = fontCalculator.optimizeIterativelyMobile(
                textEditor,
                initialFontSize,
                analysis,
                maxWidth,
                maxHeight
            );

            console.log(`📱 Mobile font optimized: ${fontSize}px for ${maxWidth}x${maxHeight}`);

            // Store initial state
            const containerElem = $(backgroundDiv).closest('.flex-item');
            const containerId = containerElem.attr('id') || `container-mobile-${Date.now()}`;
            if (!containerElem.attr('id')) {
                containerElem.attr('id', containerId);
            }
            const textHash = analysis ? analysis.id : null;
            storeInitialSizingState(containerId, fontSize, maxWidth, maxHeight, textHash);
        });
    }

    // Responsive system functions

    function setupResponsiveFontSize(textEditor, backgroundDiv) {
        if (!textEditor || !backgroundDiv) return;

        const containerElem = $(backgroundDiv).closest('.flex-item');
        const containerId = containerElem.attr('id') || `container-${Date.now()}`;
        const isMobile = MobileUtils.isMobile();

        // Recalculate font size with intelligent cache
        function handleResize() {
            // Clear previous timeout for this container
            if (ModuleState.resizeTimeouts.has(containerId)) {
                clearTimeout(ModuleState.resizeTimeouts.get(containerId));
            }

            // Mobile uses longer debounce for stability
            const debounceTime = isMobile ? CONFIG.OPTIMIZATION.MOBILE_RECHECK_DELAY : CONFIG.OPTIMIZATION.RESIZE_DEBOUNCE;

            // Set new timeout
            ModuleState.resizeTimeouts.set(containerId, setTimeout(() => {
                console.log(`🔄 Intelligent resize for container ${containerId} (mobile: ${isMobile})`);

                if (isMobile) {
                    // For mobile, always use enhanced sizing - no proportional scaling
                    console.log(`📱 Mobile resize: using full recalculation`);
                    adaptFontSize(textEditor, backgroundDiv);
                } else {
                    // Try ultra-fast proportional scaling first (desktop only)
                    const usedScaling = scaleFontSizeProportionally(textEditor, backgroundDiv, containerId);

                    if (!usedScaling) {
                        // Fallback to complete calculation if no initial state
                        console.log(`🐌 Fallback to complete calculation for container ${containerId}`);
                        adaptFontSize(textEditor, backgroundDiv);
                    }
                }

                ModuleState.resizeTimeouts.delete(containerId);
            }, debounceTime));
        }

        // Enhanced observer system for mobile
        if (window.ResizeObserver && !isMobile) {
            // Desktop: use ResizeObserver
            const resizeObserver = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    if (entry.target === backgroundDiv) {
                        handleResize();
                        break;
                    }
                }
            });

            resizeObserver.observe(backgroundDiv);
            backgroundDiv._fontResizeObserver = resizeObserver;
        } else {
            // Mobile or no ResizeObserver: use multiple event listeners for reliability
            const events = isMobile 
                ? ['resize', 'orientationchange', 'load', 'DOMContentLoaded']
                : ['resize'];
            
            events.forEach(eventName => {
                $(window).on(`${eventName}.responsiveFont.${containerId}`, handleResize);
            });

            // Mobile-specific: also listen for viewport changes
            if (isMobile && 'visualViewport' in window) {
                window.visualViewport.addEventListener('resize', handleResize);
            }
        }

        // Initial calculation with mobile-specific timing
        const initialDelay = isMobile ? CONFIG.OPTIMIZATION.MOBILE_SETUP_DELAY : CONFIG.OPTIMIZATION.INITIAL_SETUP_DELAY;
        
        setTimeout(() => {
            console.log(`🚀 Initial font sizing for ${containerId} (mobile: ${isMobile})`);
            adaptFontSize(textEditor, backgroundDiv);
        }, initialDelay);

        // Mobile: additional recheck after a longer delay to ensure everything is stable
        if (isMobile) {
            setTimeout(() => {
                console.log(`📱 Mobile stability recheck for ${containerId}`);
                adaptFontSize(textEditor, backgroundDiv, true); // Force recalculate
            }, CONFIG.OPTIMIZATION.MOBILE_SETUP_DELAY + 500);
        }
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

        // No mobile forcing necessary - CSS Grid handles everything automatically

        // Handle manifesto containers
        $('.manifesto-container').each(function () {
            const container = $(this);
            const postId = container.data('postid');
            const tipoManifesto = container.data('tipo');
            let offset = 0;
            let loading = false;
            let allDataLoaded = false;
            let prevScrollPos = null;

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

                // Save current scroll position before loading new content
                if (window.innerWidth <= 768 && isInfiniteScroll) {
                    prevScrollPos = $(window).scrollTop();
                }

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


                        // Con grid layout non è più necessario modificare justify-content
                        // Il grid gestisce automaticamente il layout

                        // Su mobile, ripristina la posizione precedente: l'ultimo del batch precedente resta visibile,
                        // mentre i nuovi 5 vengono caricati offscreen
                        if (window.innerWidth <= 768 && prevScrollPos !== null) {
                            $(window).scrollTop(prevScrollPos);
                        }

                        // Forza un check dell'IntersectionObserver dopo il DOM update
                        setTimeout(function () {
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