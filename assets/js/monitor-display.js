/**
 * Monitor Display JavaScript
 * Handles slideshow, real-time updates, and user interactions for monitor display
 */

class MonitorDisplay {
    constructor() {
        this.manifesti = [];
        this.currentSlide = 0;
        this.slideInterval = null;
        this.pollingInterval = null;
        this.pauseTimeout = null;
        this.isPlaying = true;
        this.lastUpdateTime = null;
        
        // Cache locale per le immagini di sfondo
        this.imageCache = new Map();
        
        // Configuration from PHP
        this.config = window.MonitorData || {};
        this.slideTimeout = this.config.slideInterval || 5000;
        this.pollingTimeout = this.config.pollingInterval || 15000;
        
        // Infinite scroll configuration
        this.slideWidth = 102; // 100% + 2% gap between slides
        this.clonesCount = 2; // Number of clones on each side for infinite effect
        this.totalSlides = 0; // Will be set after cloning
        this.realSlidesCount = 0; // Original number of slides
        
        this.init();
    }

    // Funzione per caricare immagini con cache
    loadImageWithCache(url) {
        return new Promise((resolve, reject) => {
            if (this.imageCache.has(url)) {
                // Immagine già in cache, restituisci immediatamente
                console.log('🟢 MONITOR CACHE HIT for:', url);
                const cachedImg = this.imageCache.get(url);
                resolve(cachedImg);
            } else {
                // Carica l'immagine e mettila in cache
                console.log('🔴 MONITOR CACHE MISS for:', url);
                const img = new Image();
                img.onload = () => {
                    console.log('✅ Monitor image loaded and cached:', url);
                    this.imageCache.set(url, img);
                    resolve(img);
                };
                img.onerror = () => {
                    console.log('❌ Failed to load monitor image:', url);
                    reject(new Error('Failed to load image: ' + url));
                };
                img.src = url;
            }
        });
    }

    init() {
        console.log('Initializing Monitor Display', this.config);
        
        // Initialize DOM elements
        this.container = document.getElementById('manifesti-container');
        this.loadingOverlay = document.getElementById('loading-overlay');
        this.noManifesti = document.getElementById('no-manifesti');
        this.lastUpdateSpan = document.getElementById('last-update');
        
        // Bind events
        this.bindEvents();
        
        // Load initial manifesti
        this.loadManifesti();
        
        // Start polling for updates
        this.startPolling();
        
        // Handle visibility change (pause when not visible)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseSlideshow();
            } else {
                this.resumeSlideshow();
            }
        });
    }

    bindEvents() {
        // Keyboard navigation (keep for accessibility)
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    this.previousSlide();
                    this.pauseOnInteraction();
                    break;
                case 'ArrowRight':
                    this.nextSlide();
                    this.pauseOnInteraction();
                    break;
                case ' ':
                    e.preventDefault();
                    this.toggleSlideshow();
                    break;
                case 'Home':
                    e.preventDefault();
                    this.goToSlide(this.realSlidesCount > 1 ? this.clonesCount : 0);
                    this.pauseOnInteraction();
                    break;
                case 'End':
                    e.preventDefault();
                    this.goToSlide(this.realSlidesCount > 1 ? this.realSlidesCount + this.clonesCount - 1 : 0);
                    this.pauseOnInteraction();
                    break;
            }
        });
        
        // Touch/swipe and mouse support
        this.addTouchAndMouseSupport();
    }

    addTouchAndMouseSupport() {
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let currentY = 0;
        let isDragging = false;
        let startTime = 0;
        let containerWidth = 0;
        const snapThreshold = 0.15; // 15% of container width for triggering slide change
        
        if (!this.container) return;
        
        // Check if we're in grid mode
        const isGridMode = window.ManifestiGridConfig && typeof window.createManifestiGrid === 'function';
        
        // Unified start handler for both touch and mouse
        const handleStart = (e) => {
            isDragging = true;
            startTime = Date.now();
            containerWidth = this.container.offsetWidth;
            
            if (e.type === 'touchstart') {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            } else {
                startX = e.clientX;
                startY = e.clientY;
                e.preventDefault(); // Prevent text selection on mouse
            }
            
            currentX = startX;
            currentY = startY;
            
            // Add dragging class to disable transitions (not needed for grid mode)
            if (!isGridMode) {
                const slides = this.container.querySelectorAll('.manifesto-slide');
                slides.forEach(slide => slide.classList.add('dragging'));
            }
        };
        
        // Unified move handler - continuous drag (disabled for grid mode)
        const handleMove = (e) => {
            if (!isDragging) return;
            
            if (e.type === 'touchmove') {
                currentX = e.touches[0].clientX;
                currentY = e.touches[0].clientY;
            } else {
                currentX = e.clientX;
                currentY = e.clientY;
            }
            
            const deltaX = currentX - startX;
            const deltaY = Math.abs(currentY - startY);
            
            // Prevent default only for significant horizontal movement
            if (Math.abs(deltaX) > deltaY && Math.abs(deltaX) > 10) {
                e.preventDefault();
                
                // Only update slide positions in non-grid mode (grid uses fade transition)
                if (!isGridMode) {
                    // Update slide positions in real time
                    this.updateSlidePositions(deltaX, containerWidth);
                }
            }
        };
        
        // Unified end handler - snap logic
        const handleEnd = (e) => {
            if (!isDragging) return;
            
            isDragging = false;
            const deltaX = currentX - startX;
            const deltaY = Math.abs(currentY - startY);
            const timeDiff = Date.now() - startTime;
            const swipePercentage = Math.abs(deltaX) / containerWidth;
            
            // Remove dragging class to enable transitions (not needed for grid mode)
            if (!isGridMode) {
                const slides = this.container.querySelectorAll('.manifesto-slide');
                slides.forEach(slide => slide.classList.remove('dragging'));
            }
            
            // Check for tap/click (minimal movement and quick timing)
            if (Math.abs(deltaX) < 10 && Math.abs(deltaY) < 10 && timeDiff < 300) {
                if (!isGridMode) {
                    this.snapToCurrentSlide();
                }
                this.pauseOnInteraction();
                return;
            }
            
            // Check if horizontal swipe is significant
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                // If swipe is more than 15% of screen width, change slide
                if (swipePercentage > snapThreshold) {
                    if (deltaX < 0) {
                        // Swipe left - next slide
                        this.nextSlide();
                    } else {
                        // Swipe right - previous slide
                        this.previousSlide();
                    }
                    this.pauseOnInteraction();
                } else {
                    // Swipe not enough, snap back to current slide
                    if (isGridMode) {
                        // Grid mode doesn't need snap back
                        this.showGridSlide(this.currentSlide);
                    } else {
                        // Snap back to current position
                        this.snapToCurrentSlide();
                    }
                }
            } else {
                // Vertical swipe or small movement, snap back
                if (!isGridMode) {
                    this.snapToCurrentSlide();
                }
            }
        };
        
        // Touch events
        this.container.addEventListener('touchstart', handleStart, { passive: true });
        this.container.addEventListener('touchmove', handleMove, { passive: false });
        this.container.addEventListener('touchend', handleEnd, { passive: true });
        
        // Mouse events
        this.container.addEventListener('mousedown', handleStart);
        this.container.addEventListener('mousemove', handleMove);
        this.container.addEventListener('mouseup', handleEnd);
        
        // Prevent mouse leave issues
        this.container.addEventListener('mouseleave', () => {
            if (isDragging) {
                isDragging = false;
                const slides = this.container.querySelectorAll('.manifesto-slide');
                slides.forEach(slide => slide.classList.remove('dragging'));
                this.snapToCurrentSlide();
            }
        });
    }

    async loadManifesti() {
        try {
            console.log('Loading manifesti...', {
                vendorId: this.config.vendorId,
                monitorId: this.config.monitorId,
                postId: this.config.postId,
                ajaxUrl: this.config.ajaxUrl
            });
            
            this.showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'monitor_get_manifesti');
            formData.append('vendor_id', this.config.vendorId);
            formData.append('post_id', this.config.postId);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            console.log('AJAX Response:', data);
            
            if (data.success) {
                this.manifesti = data.data.manifesti || [];
                this.lastUpdateTime = data.data.last_update;
                
                console.log('Loaded manifesti:', this.manifesti.length);
                console.log('Manifesti data:', this.manifesti);
                
                // Check if we're in grid mode to force grid layout even with 0 manifesti
                const isGridMode = window.ManifestiGridConfig && typeof window.createManifestiGrid === 'function';
                
                if (this.manifesti.length > 0 || isGridMode) {
                    this.renderSlideshow();
                    
                    // Only start slideshow if we have manifesti
                    if (this.manifesti.length > 0) {
                        this.startSlideshow();
                    }
                } else {
                    console.log('No manifesti found, showing no-manifesti screen');
                    this.showNoManifesti();
                }
                
                this.updateLastUpdateTime();
            } else {
                console.error('Error loading manifesti:', data.data);
                this.showNoManifesti();
            }
        } catch (error) {
            console.error('Network error loading manifesti:', error);
            this.showNoManifesti();
        } finally {
            this.showLoading(false);
        }
    }

    renderSlideshow() {
        if (!this.container) return;

        console.log(window.ManifestiGridConfig);
        // Check if we have grid configuration (new grid mode)
        if (window.ManifestiGridConfig && typeof window.createManifestiGrid === 'function') {
            // Force grid mode even with 0 manifesti to maintain grid layout
            // If no manifesti, createManifestiGrid will create an empty grid
            // Use grid system from template
            window.createManifestiGrid(this.manifesti);

            // Wait for DOM to be ready, then apply backgrounds to all cells across all slides
            setTimeout(() => {
                const gridConfig = window.ManifestiGridConfig;
                const slides = this.container.querySelectorAll('.manifesto-slide');
                
                // Process each slide
                slides.forEach((slide, slideIndex) => {
                    const cells = slide.querySelectorAll('.manifesti-grid-cell:not(.empty)');
                    
                    // Apply background to each cell in the slide
                    cells.forEach((cell, cellIndex) => {
                        const manifestoIndex = slideIndex * gridConfig.totalCells + cellIndex;
                        
                        if (manifestoIndex < this.manifesti.length) {
                            const manifesto = this.manifesti[manifestoIndex];
                            if (manifesto.vendor_data) {
                                const wrapper = cell.querySelector('.manifesto-wrapper');
                                if (wrapper) {
                                    this.updateEditorBackground(manifesto.vendor_data, wrapper);
                                } else {
                                    this.updateEditorBackground(manifesto.vendor_data, cell);
                                }
                            }
                        }
                    });
                });
            }, 150);

            // For grid mode, we need different slide counting logic
            const gridConfig = window.ManifestiGridConfig;
            this.realSlidesCount = Math.ceil(this.manifesti.length / gridConfig.totalCells);
            
            // In grid mode, we don't use clones - just cycle through slides
            this.totalSlides = this.realSlidesCount;
            
            // Always start at first slide in grid mode
            this.currentSlide = 0;
            
            // Make first slide visible immediately
            setTimeout(() => {
                const firstSlide = this.container.querySelector('.manifesto-slide');
                if (firstSlide) {
                    firstSlide.classList.add('active');
                }
            }, 50);
            
            this.hideNoManifesti();
            return;
        }
        
        // Original single manifesto per slide mode (fallback)
        // Clear existing slides
        this.container.innerHTML = '';
        
        this.realSlidesCount = this.manifesti.length;
        
        // For infinite scroll, we need at least 2 clones on each side
        // But only if we have more than 1 slide
        if (this.realSlidesCount > 1) {
            // Create clone slides at the beginning (last slides)
            for (let i = 0; i < this.clonesCount; i++) {
                const cloneIndex = this.realSlidesCount - this.clonesCount + i;
                const manifesto = this.manifesti[cloneIndex];
                const slide = this.createSlide(manifesto, -(this.clonesCount - i), true);
                this.container.appendChild(slide);
                
                // Apply background after the slide is in the DOM
                if (manifesto.vendor_data) {
                    setTimeout(() => {
                        const wrapper = slide.querySelector('.manifesto-wrapper');
                        if (wrapper) {
                            this.updateEditorBackground(manifesto.vendor_data, wrapper);
                        }
                    }, 100);
                }
            }
        }
        
        // Create original slides
        this.manifesti.forEach((manifesto, index) => {
            const slide = this.createSlide(manifesto, index);
            this.container.appendChild(slide);
            
            // Apply background after the slide is in the DOM
            if (manifesto.vendor_data) {
                setTimeout(() => {
                    const wrapper = slide.querySelector('.manifesto-wrapper');
                    if (wrapper) {
                        this.updateEditorBackground(manifesto.vendor_data, wrapper);
                    }
                }, 100);
            }
        });
        
        // Create clone slides at the end (first slides)
        if (this.realSlidesCount > 1) {
            for (let i = 0; i < this.clonesCount; i++) {
                const manifesto = this.manifesti[i];
                const slide = this.createSlide(manifesto, this.realSlidesCount + i, true);
                this.container.appendChild(slide);
                
                // Apply background after the slide is in the DOM
                if (manifesto.vendor_data) {
                    setTimeout(() => {
                        const wrapper = slide.querySelector('.manifesto-wrapper');
                        if (wrapper) {
                            this.updateEditorBackground(manifesto.vendor_data, wrapper);
                        }
                    }, 100);
                }
            }
        }
        
        // Calculate total slides including clones
        this.totalSlides = this.realSlidesCount > 1 
            ? this.realSlidesCount + (this.clonesCount * 2)
            : this.realSlidesCount;
        
        // Position all slides and show first real slide
        this.currentSlide = this.realSlidesCount > 1 ? this.clonesCount : 0;
        this.updateAllSlidePositions();
        this.hideNoManifesti();
    }

    createSlide(manifesto, index, isClone = false) {
        const slide = document.createElement('div');
        slide.className = 'manifesto-slide' + (isClone ? ' clone' : '');
        slide.dataset.slideIndex = index;
        slide.dataset.isClone = isClone;
        
        const content = document.createElement('div');
        content.className = 'manifesto-content';
        content.innerHTML = manifesto.html;
        
        slide.appendChild(content);
        
        return slide;
    }
    
    updateEditorBackground(data, containerElem) {
        if (!data || !containerElem) {
            console.warn('Missing data or container for updateEditorBackground');
            return;
        }

        const backgroundDiv = containerElem.querySelector('.text-editor-background');
        const textEditor = containerElem.querySelector('.custom-text-editor');

        if (!backgroundDiv || !textEditor) {
            console.warn('Required elements not found in container');
            return;
        }

        console.log(data);

        console.log('Data_manifesto_backgrund' + data.manifesto_background);

        if (data.manifesto_background) {
            // Nasconde il testo durante il caricamento
            textEditor.classList.add('loading');

            // Usa la cache per caricare l'immagine
            this.loadImageWithCache(data.manifesto_background)
                .then((img) => {
                    const aspectRatio = img.width / img.height;
                    backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                    console.log(data.manifesto_background);
                    // Use the same logic as manifesto.js for consistent sizing
                    // Get available space from container
                    const containerWidth = backgroundDiv.parentElement.clientWidth;
                    const containerHeight = backgroundDiv.parentElement.clientHeight;
                    
                    // Calculate optimal dimensions that fit in container while respecting aspect ratio
                    let optimalWidth, optimalHeight;
                    
                    if (aspectRatio > (containerWidth / containerHeight)) {
                        // Image is wider relative to container - constrain by width
                        optimalWidth = containerWidth; // Max reasonable size for monitor
                        optimalHeight = optimalWidth / aspectRatio;
                    } else {
                        // Image is taller relative to container - constrain by height
                        optimalHeight = containerHeight; // Max reasonable size for monitor
                        optimalWidth = optimalHeight * aspectRatio;
                    }
                    
                    // Apply the calculated dimensions like manifesto.js
                    backgroundDiv.style.width = optimalWidth + 'px';
                    backgroundDiv.style.height = optimalHeight + 'px';

                    // Calculate margins exactly like manifesto.js using clientWidth/Height AFTER setting dimensions
                    const marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                    const marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                    const marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                    const marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                    // Apply padding to text editor exactly like manifesto.js
                    textEditor.style.paddingTop = `${marginTopPx}px`;
                    textEditor.style.paddingRight = `${marginRightPx}px`;
                    textEditor.style.paddingBottom = `${marginBottomPx}px`;
                    textEditor.style.paddingLeft = `${marginLeftPx}px`;
                    textEditor.style.textAlign = data.alignment || 'left';
                    
                    // Calculate font-size proportional to background dimensions
                    const currentWidth = parseInt(backgroundDiv.style.width);
                    const currentHeight = parseInt(backgroundDiv.style.height);
                    
                    // Determine image orientation
                    const isLandscape = currentWidth > currentHeight;
                    const isPortrait = currentHeight > currentWidth;
                    
                    // Calculate consistent font-size based on image dimensions and orientation only
                    let baseFontSize;
                    let lineHeightMultiplier = 1.3; // default line-height multiplier
                    
                    // Use area-based calculation for more consistent results
                    const area = currentWidth * currentHeight;

                    // Base font size calculation using area
                    let areaBasedFontSize = Math.sqrt(area) * 0.07; // Base calculation from area
                    
                    // Apply orientation-based adjustments
                    if (isPortrait) {
                        baseFontSize = areaBasedFontSize * 0.9; // Smaller font for portrait
                        lineHeightMultiplier = 1.2; // Tighter line-height for portrait
                    } else if (isLandscape) {
                        baseFontSize = areaBasedFontSize * 1; // Same font for landscape
                        lineHeightMultiplier = 1.2; // Same line-height for landscape
                    } else {
                        baseFontSize = areaBasedFontSize; // Square images use base calculation
                    }
                    
                    textEditor.style.fontSize = `${baseFontSize}px`;
                    textEditor.style.lineHeight = `${baseFontSize * lineHeightMultiplier}px`;
                    
                    // Ensure text is visible
                    textEditor.style.color = '#000';
                    textEditor.style.position = 'absolute';
                    textEditor.style.top = '0';
                    textEditor.style.left = '0';
                    textEditor.style.width = '100%';
                    textEditor.style.height = '100%';

                    // Mostra il testo dopo che tutto è pronto
                    textEditor.classList.remove('loading');
                    
                    // Fallback: iteratively reduce font-size until content fits
                    setTimeout(() => {
                        let iterations = 0;
                        const maxIterations = 10;
                        
                        while (textEditor.scrollHeight > textEditor.clientHeight && iterations < maxIterations) {
                            const currentFontSize = parseFloat(textEditor.style.fontSize);
                            const reductionFactor = Math.max(0.8, textEditor.clientHeight / textEditor.scrollHeight);
                            const newFontSize = Math.max(6, currentFontSize * reductionFactor);
                            const newLineHeight = newFontSize * lineHeightMultiplier;
                            
                            textEditor.style.fontSize = `${newFontSize}px`;
                            textEditor.style.lineHeight = `${newLineHeight}px`;
                            iterations++;
                            
                            if (iterations === 1) {
                                console.log(`Font-size reduced from ${currentFontSize}px to fit content`);
                            }
                        }
                        
                        if (iterations >= maxIterations) {
                            console.warn('Max iterations reached for font-size reduction');
                        }
                    }, 300);
                })
                .catch((error) => {
                    console.warn('Failed to load monitor background image:', error);
                    backgroundDiv.style.backgroundImage = 'none';
                    // Mostra il testo anche in caso di errore
                    textEditor.classList.remove('loading');
                });
        } else {
            // No background image - set default proportional font size
            backgroundDiv.style.backgroundImage = 'none';
            // Assicurati che il testo sia visibile se non c'è sfondo
            textEditor.classList.remove('loading');
            
            // For no-background manifesto, calculate font-size based on container dimensions
            const containerHeight = containerElem.parentElement.clientHeight || window.innerHeight * 0.75;
            const containerWidth = containerElem.parentElement.clientWidth || window.innerWidth;
            
            // Calculate font-size with small screen optimization (no background case)
            const isSmallScreen = window.innerWidth < 768 || window.innerHeight < 600;
            const isVerySmallScreen = window.innerWidth < 480 || window.innerHeight < 400;
            
            let baseFontSize;
            if (isVerySmallScreen) {
                baseFontSize = Math.max(8, Math.min(containerWidth * 0.015, containerWidth * 0.025));
            } else if (isSmallScreen) {
                baseFontSize = Math.max(10, Math.min(containerWidth * 0.02, containerWidth * 0.03));
            } else {
                baseFontSize = Math.max(14, Math.min(containerWidth * 0.025, containerWidth * 0.035));
            }
            
            textEditor.style.fontSize = `${baseFontSize}px`;
            
            // Paragraph margins are now handled by CSS
            textEditor.style.textAlign = data.alignment || 'center';
            
            // Fallback: iteratively reduce font-size until content fits
            setTimeout(() => {
                let iterations = 0;
                const maxIterations = 10;
                const defaultLineHeightMultiplier = 1.3; // Default line-height multiplier for no-background case

                while (textEditor.scrollHeight > textEditor.clientHeight && iterations < maxIterations) {
                    const currentFontSize = parseFloat(textEditor.style.fontSize);
                    const reductionFactor = Math.max(0.8, textEditor.clientHeight / textEditor.scrollHeight);
                    const newFontSize = Math.max(6, currentFontSize * reductionFactor);
                    const newLineHeight = newFontSize * defaultLineHeightMultiplier;
                    
                    textEditor.style.fontSize = `${newFontSize}px`;
                    textEditor.style.lineHeight = `${newLineHeight}px`;
                    iterations++;
                    
                    if (iterations === 1) {
                        console.log(`Font-size reduced from ${currentFontSize}px to fit content (no background)`);
                    }
                }
                
                if (iterations >= maxIterations) {
                    console.warn('Max iterations reached for font-size reduction (no background)');
                }
            }, 300);
        }
    }

    showSlide(index, direction = null) {
        if (!this.container || this.manifesti.length === 0) return;
        
        this.currentSlide = index;
        this.updateAllSlidePositions();
    }
    
    updateAllSlidePositions(withTransition = true) {
        const slides = this.container.querySelectorAll('.manifesto-slide');
        
        slides.forEach((slide, globalIndex) => {
            const slideIndex = parseInt(slide.dataset.slideIndex);
            const position = this.getSlidePosition(globalIndex);
            
            // Temporarily disable transition if needed
            if (!withTransition) {
                slide.classList.add('no-transition');
            }
            
            slide.style.transform = `translateX(${position}%)`;
            
            // Re-enable transition after a short delay
            if (!withTransition) {
                setTimeout(() => {
                    slide.classList.remove('no-transition');
                }, 50);
            }
            
            // Update z-index for active slide area
            const isInActiveArea = globalIndex >= this.currentSlide - 1 && globalIndex <= this.currentSlide + 1;
            if (isInActiveArea) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });
    }
    
    getSlidePosition(slideIndex) {
        const diff = slideIndex - this.currentSlide;
        return diff * this.slideWidth;
    }
    
    updateSlidePositions(deltaX, containerWidth) {
        const slides = this.container.querySelectorAll('.manifesto-slide');
        const dragPercentage = (deltaX / containerWidth) * this.slideWidth;
        
        slides.forEach((slide, index) => {
            const basePosition = this.getSlidePosition(index);
            const newPosition = basePosition + dragPercentage;
            slide.style.transform = `translateX(${newPosition}%)`;
        });
    }
    
    snapToCurrentSlide() {
        this.updateAllSlidePositions();
    }


    ensureValidSlidePosition() {
        // Make sure currentSlide is always an integer and within bounds
        this.currentSlide = Math.round(this.currentSlide);
        
        // If we somehow got into an invalid state, snap to nearest valid slide
        if (this.realSlidesCount > 1) {
            // Ensure we're within the valid range including clones
            if (this.currentSlide < -this.clonesCount) {
                this.currentSlide = -this.clonesCount;
            } else if (this.currentSlide >= this.totalSlides + this.clonesCount) {
                this.currentSlide = this.totalSlides + this.clonesCount - 1;
            }
        } else {
            this.currentSlide = 0;
        }
    }

    nextSlide() {
        if (this.manifesti.length === 0) return;
        
        // Check if we're in grid mode
        const isGridMode = window.ManifestiGridConfig && typeof window.createManifestiGrid === 'function';
        
        if (isGridMode) {
            // For grid mode, use simple modulo but with proper forward animation
            const nextIndex = (this.currentSlide + 1) % this.realSlidesCount;
            this.showGridSlideWithDirection(nextIndex, 'forward');
            this.currentSlide = nextIndex;
        } else {
            // Original behavior for non-grid mode
            this.currentSlide++;
            this.ensureValidSlidePosition();
            this.goToSlide(this.currentSlide);
            this.checkInfiniteLoop();
        }
    }

    previousSlide() {
        if (this.manifesti.length === 0) return;
        
        // Check if we're in grid mode
        const isGridMode = window.ManifestiGridConfig && typeof window.createManifestiGrid === 'function';
        
        if (isGridMode) {
            // For grid mode, use simple modulo but with proper backward animation
            const prevIndex = (this.currentSlide - 1 + this.realSlidesCount) % this.realSlidesCount;
            this.showGridSlideWithDirection(prevIndex, 'backward');
            this.currentSlide = prevIndex;
        } else {
            // Original behavior for non-grid mode
            this.currentSlide--;
            this.ensureValidSlidePosition();
            this.goToSlide(this.currentSlide);
            this.checkInfiniteLoop();
        }
    }
    
    showGridSlide(index) {
        // Use translation for grid mode similar to normal mode with clone support
        const slides = this.container.querySelectorAll('.manifesto-slide');
        const totalSlides = slides.length;
        
        slides.forEach((slide, i) => {
            const position = (i - index) * this.slideWidth;
            slide.style.transform = `translateX(${position}%)`;
            slide.style.transition = 'transform 0.5s ease-in-out';
            
            // Keep active class for z-index management
            if (i === index) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });
    }
    
    showGridSlideWithDirection(targetIndex, direction) {
        const slides = this.container.querySelectorAll('.manifesto-slide');
        const currentIndex = this.currentSlide;
        
        if (direction === 'forward') {
            // Check if we're going from last to first (wrap around)
            if (currentIndex === this.realSlidesCount - 1 && targetIndex === 0) {
                // Create the illusion of continuous forward movement
                // First, position target slide to the right
                const targetSlide = slides[targetIndex];
                targetSlide.style.transition = 'none';
                targetSlide.style.transform = `translateX(${this.slideWidth}%)`;
                
                // Force reflow
                targetSlide.offsetHeight;
                
                // Now animate all slides to the left
                setTimeout(() => {
                    slides.forEach((slide, i) => {
                        slide.style.transition = 'transform 0.5s ease-in-out';
                        if (i === targetIndex) {
                            slide.style.transform = 'translateX(0%)';
                            slide.classList.add('active');
                        } else if (i === currentIndex) {
                            slide.style.transform = `translateX(-${this.slideWidth}%)`;
                            slide.classList.remove('active');
                        } else {
                            // Position other slides appropriately
                            const position = (i - targetIndex) * this.slideWidth;
                            slide.style.transform = `translateX(${position}%)`;
                            slide.classList.remove('active');
                        }
                    });
                }, 10);
            } else {
                // Normal forward animation
                this.showGridSlide(targetIndex);
            }
        } else if (direction === 'backward') {
            // Check if we're going from first to last (wrap around)
            if (currentIndex === 0 && targetIndex === this.realSlidesCount - 1) {
                // Create the illusion of continuous backward movement
                // First, position target slide to the left
                const targetSlide = slides[targetIndex];
                targetSlide.style.transition = 'none';
                targetSlide.style.transform = `translateX(-${this.slideWidth}%)`;
                
                // Force reflow
                targetSlide.offsetHeight;
                
                // Now animate all slides to the right
                setTimeout(() => {
                    slides.forEach((slide, i) => {
                        slide.style.transition = 'transform 0.5s ease-in-out';
                        if (i === targetIndex) {
                            slide.style.transform = 'translateX(0%)';
                            slide.classList.add('active');
                        } else if (i === currentIndex) {
                            slide.style.transform = `translateX(${this.slideWidth}%)`;
                            slide.classList.remove('active');
                        } else {
                            // Position other slides appropriately
                            const position = (i - targetIndex) * this.slideWidth;
                            slide.style.transform = `translateX(${position}%)`;
                            slide.classList.remove('active');
                        }
                    });
                }, 10);
            } else {
                // Normal backward animation
                this.showGridSlide(targetIndex);
            }
        }
    }

    goToSlide(index) {
        // Check if we're in grid mode
        const isGridMode = window.ManifestiGridConfig && typeof window.createManifestiGrid === 'function';
        
        if (isGridMode) {
            this.currentSlide = index % this.realSlidesCount;
            this.showGridSlide(this.currentSlide);
        } else {
            this.currentSlide = index;
            this.ensureValidSlidePosition();
            this.showSlide(this.currentSlide);
        }
        this.restartSlideshow(); // Reset auto-advance timer
    }

    checkInfiniteLoop() {
        if (this.realSlidesCount <= 1) return;
        
        // Skip infinite loop logic for grid mode
        const isGridMode = window.ManifestiGridConfig && typeof window.createManifestiGrid === 'function';
        if (isGridMode) return;
        
        // If we're at or beyond the end clones, jump to beginning
        if (this.currentSlide >= this.realSlidesCount + this.clonesCount) {
            setTimeout(() => {
                // Jump to the equivalent position at the beginning
                const overShoot = this.currentSlide - (this.realSlidesCount + this.clonesCount);
                this.currentSlide = this.clonesCount + overShoot;
                this.updateAllSlidePositions(false); // No transition
            }, 300); // Wait for transition to complete
        }
        // If we're at or before the beginning clones, jump to end
        else if (this.currentSlide < 0) {
            setTimeout(() => {
                // Jump to the equivalent position at the end
                const underShoot = Math.abs(this.currentSlide);
                this.currentSlide = this.realSlidesCount + this.clonesCount - 1 - underShoot;
                this.updateAllSlidePositions(false); // No transition
            }, 300); // Wait for transition to complete
        }
    }

    startSlideshow() {
        if (this.manifesti.length <= 1) return;
        
        this.slideInterval = setInterval(() => {
            if (this.isPlaying) {
                // Automatic slideshow goes from right to left (previous slide)
                this.nextSlide();
            }
        }, this.slideTimeout);
    }

    stopSlideshow() {
        if (this.slideInterval) {
            clearInterval(this.slideInterval);
            this.slideInterval = null;
        }
    }

    pauseSlideshow() {
        this.isPlaying = false;
    }

    resumeSlideshow() {
        this.isPlaying = true;
    }

    restartSlideshow() {
        this.stopSlideshow();
        this.startSlideshow();
    }

    toggleSlideshow() {
        if (this.isPlaying) {
            this.pauseSlideshow();
        } else {
            this.resumeSlideshow();
        }
    }
    
    pauseOnInteraction() {
        console.log('Slideshow paused for 30 seconds due to user interaction');
        
        // Pause slideshow
        this.pauseSlideshow();
        
        // Clear any existing pause timeout
        if (this.pauseTimeout) {
            clearTimeout(this.pauseTimeout);
        }
        
        // Set timeout to resume after 30 seconds
        this.pauseTimeout = setTimeout(() => {
            console.log('Resuming slideshow after 30 second pause');
            this.resumeSlideshow();
            this.restartSlideshow(); // Restart the interval
            this.pauseTimeout = null;
        }, 30000); // 30 seconds
    }

    showLoading(show) {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.toggle('hidden', !show);
        }
    }

    showNoManifesti() {
        if (this.noManifesti) {
            this.noManifesti.style.display = 'flex';
        }
        
        if (this.container) {
            this.container.style.display = 'none';
        }
        
        // Hide the header when no manifesti are available
        const monitorHeader = document.querySelector('.monitor-header');
        if (monitorHeader) {
            monitorHeader.style.display = 'none';
        }
    }

    hideNoManifesti() {
        if (this.noManifesti) {
            this.noManifesti.style.display = 'none';
        }
        
        if (this.container) {
            this.container.style.display = 'block';
        }
        
        // Show the header when manifesti are available
        const monitorHeader = document.querySelector('.monitor-header');
        if (monitorHeader) {
            monitorHeader.style.display = 'flex';
        }
    }

    updateLastUpdateTime() {
        if (this.lastUpdateSpan && this.lastUpdateTime) {
            this.lastUpdateSpan.textContent = this.lastUpdateTime;
        }
    }

    startPolling() {
        this.pollingInterval = setInterval(() => {
            this.checkForUpdates();
        }, this.pollingTimeout);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    async checkForUpdates() {
        try {
            const formData = new FormData();
            formData.append('action', 'monitor_check_association');
            formData.append('vendor_id', this.config.vendorId);
            formData.append('current_post_id', this.config.postId);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.data.changed) {
                    if (data.data.redirect_to_waiting) {
                        // No post associated - redirect to waiting screen
                        window.location.reload();
                    } else {
                        // New post associated - reload page with new data
                        this.handleNewDefunto(data.data);
                    }
                } else {
                    // Check for new manifesti for the same defunto
                    this.checkForNewManifesti();
                    
                    // Update last check time
                    if (data.data.last_check && this.lastUpdateSpan) {
                        this.lastUpdateSpan.textContent = data.data.last_check;
                    }
                }
            }
        } catch (error) {
            console.error('Error checking for updates:', error);
        }
    }

    async checkForNewManifesti() {
        try {
            const formData = new FormData();
            formData.append('action', 'monitor_get_manifesti');
            formData.append('vendor_id', this.config.vendorId);
            formData.append('post_id', this.config.postId);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                const newManifesti = data.data.manifesti || [];
                const currentCount = this.manifesti.length;
                const newCount = newManifesti.length;
                
                // Check if there are new manifesti
                if (newCount > currentCount) {
                    console.log(`Found ${newCount - currentCount} new manifesti. Updating slideshow...`);
                    
                    // Update manifesti array
                    this.manifesti = newManifesti;
                    this.lastUpdateTime = data.data.last_update;
                    
                    // Re-render slideshow with new manifesti
                    this.stopSlideshow();
                    this.renderSlideshow();
                    this.startSlideshow();
                    
                    this.updateLastUpdateTime();
                } else if (newCount < currentCount) {
                    // Some manifesti were removed
                    console.log(`${currentCount - newCount} manifesti were removed. Updating slideshow...`);
                    
                    this.manifesti = newManifesti;
                    this.lastUpdateTime = data.data.last_update;
                    
                    // Handle case where all manifesti were removed
                    if (newManifesti.length === 0) {
                        this.showNoManifesti();
                    } else {
                        this.stopSlideshow();
                        this.renderSlideshow();
                        this.startSlideshow();
                    }
                    
                    this.updateLastUpdateTime();
                }
            }
        } catch (error) {
            console.error('Error checking for new manifesti:', error);
        }
    }

    handleNewDefunto(data) {
        // Update page data
        this.config.postId = data.new_post_id;
        
        // Update header information
        this.updateHeader(data.new_post_data);
        
        // Reload manifesti for new defunto
        this.stopSlideshow();
        this.loadManifesti();
    }

    updateHeader(defuntoData) {
        // Update defunto name
        const nameElement = document.querySelector('.defunto-details h1');
        if (nameElement) {
            nameElement.textContent = defuntoData.title;
        }
        
        // Update date
        const dateElement = document.querySelector('.defunto-details p');
        if (dateElement) {
            dateElement.textContent = defuntoData.data_morte;
        }
        
        // Update photo
        const photoElement = document.querySelector('.defunto-foto');
        const photoPlaceholder = document.querySelector('.defunto-foto-placeholder');
        
        if (defuntoData.foto) {
            if (photoElement) {
                photoElement.src = defuntoData.foto;
                photoElement.alt = defuntoData.title;
            } else if (photoPlaceholder) {
                // Replace placeholder with actual image
                const img = document.createElement('img');
                img.src = defuntoData.foto;
                img.alt = defuntoData.title;
                img.className = 'defunto-foto';
                photoPlaceholder.parentNode.replaceChild(img, photoPlaceholder);
            }
        }
        
        // Update page title
        document.title = `${defuntoData.title} - ${this.config.defuntoTitle}`;
    }

    destroy() {
        this.stopSlideshow();
        this.stopPolling();
        
        // Remove event listeners
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        document.removeEventListener('keydown', this.handleKeydown);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (window.MonitorData) {
        window.monitorDisplay = new MonitorDisplay();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.monitorDisplay) {
        window.monitorDisplay.destroy();
    }
});