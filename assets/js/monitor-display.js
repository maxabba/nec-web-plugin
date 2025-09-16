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
        const snapThreshold = 0.3; // 30% of container width
        
        if (!this.container) return;
        
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
            
            // Add dragging class to disable transitions
            const slides = this.container.querySelectorAll('.manifesto-slide');
            slides.forEach(slide => slide.classList.add('dragging'));
        };
        
        // Unified move handler - continuous drag
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
                
                // Update slide positions in real time
                this.updateSlidePositions(deltaX, containerWidth);
            }
        };
        
        // Unified end handler - snap logic
        const handleEnd = (e) => {
            if (!isDragging) return;
            
            isDragging = false;
            const deltaX = currentX - startX;
            const deltaY = Math.abs(currentY - startY);
            const timeDiff = Date.now() - startTime;
            
            // Remove dragging class to enable transitions
            const slides = this.container.querySelectorAll('.manifesto-slide');
            slides.forEach(slide => slide.classList.remove('dragging'));
            
            // Check for tap/click (minimal movement and quick timing)
            if (Math.abs(deltaX) < 10 && Math.abs(deltaY) < 10 && timeDiff < 300) {
                this.snapToCurrentSlide();
                this.pauseOnInteraction();
                return;
            }
            
            // Always snap to the nearest complete slide
            if (Math.abs(deltaX) > 10 && Math.abs(deltaX) > Math.abs(deltaY)) {
                // Calculate which slide should be centered after this drag
                const targetSlide = this.calculateNearestSlideFromDrag(deltaX, containerWidth);
                
                // Snap to the calculated target slide
                this.currentSlide = targetSlide;
                this.updateAllSlidePositions();
                this.checkInfiniteLoop();
                this.pauseOnInteraction();
            } else {
                // For small movements, snap to nearest slide
                this.snapToNearestSlide();
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
                
                if (this.manifesti.length > 0) {
                    this.hideNoManifesti();
                    this.renderSlideshow();
                    this.startSlideshow();
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
        if (!this.container || this.manifesti.length === 0) return;
        
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

        if (data.manifesto_background) {
            const img = new Image();
            img.src = data.manifesto_background;
            img.onload = function () {
                const aspectRatio = img.width / img.height;
                backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                
                // Use the same logic as manifesto.js for consistent sizing
                // Get available space from container
                const containerWidth = backgroundDiv.parentElement.clientWidth || window.innerWidth * 0.8;
                const containerHeight = backgroundDiv.parentElement.clientHeight || window.innerHeight * 0.75;
                
                // Calculate optimal dimensions that fit in container while respecting aspect ratio
                let optimalWidth, optimalHeight;
                
                if (aspectRatio > (containerWidth / containerHeight)) {
                    // Image is wider relative to container - constrain by width
                    optimalWidth = containerWidth * 0.9; // Max reasonable size for monitor
                    optimalHeight = optimalWidth / aspectRatio;
                } else {
                    // Image is taller relative to container - constrain by height
                    optimalHeight = containerHeight * 0.9; // Max reasonable size for monitor
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
                
                // Calculate font-size proportional to background dimensions (responsive to monitor size)
                const baseSize = Math.min(optimalWidth, optimalHeight);
                let baseFontSize;
                
                // Scale font size based on manifesto dimensions
                if (baseSize < 300) {
                    baseFontSize = Math.max(12, baseSize * 0.06);
                } else if (baseSize < 450) {
                    baseFontSize = Math.max(16, baseSize * 0.05);
                } else {
                    baseFontSize = Math.max(20, baseSize * 0.04);
                }
                
                textEditor.style.fontSize = `${baseFontSize}px`;
                
                // Paragraph margins are now handled by CSS
                
                // Ensure text is visible
                textEditor.style.color = '#000';
                textEditor.style.position = 'absolute';
                textEditor.style.top = '0';
                textEditor.style.left = '0';
                textEditor.style.width = '100%';
                textEditor.style.height = '100%';
                
                // Fallback: iteratively reduce font-size until content fits
                setTimeout(() => {
                    let iterations = 0;
                    const maxIterations = 10;
                    
                    while (textEditor.scrollHeight > textEditor.clientHeight && iterations < maxIterations) {
                        const currentFontSize = parseFloat(textEditor.style.fontSize);
                        const reductionFactor = Math.max(0.8, textEditor.clientHeight / textEditor.scrollHeight);
                        const newFontSize = Math.max(6, currentFontSize * reductionFactor);
                        textEditor.style.fontSize = `${newFontSize}px`;
                        iterations++;
                        
                        if (iterations === 1) {
                            console.log(`Font-size reduced from ${currentFontSize}px to fit content`);
                        }
                    }
                    
                    if (iterations >= maxIterations) {
                        console.warn('Max iterations reached for font-size reduction');
                    }
                }, 300);
            }
        } else {
            // No background image - set default proportional font size
            backgroundDiv.style.backgroundImage = 'none';
            
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
                
                while (textEditor.scrollHeight > textEditor.clientHeight && iterations < maxIterations) {
                    const currentFontSize = parseFloat(textEditor.style.fontSize);
                    const reductionFactor = Math.max(0.8, textEditor.clientHeight / textEditor.scrollHeight);
                    const newFontSize = Math.max(6, currentFontSize * reductionFactor);
                    textEditor.style.fontSize = `${newFontSize}px`;
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

    // renderIndicators removed - no longer needed

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

    snapToNearestSlide() {
        if (this.realSlidesCount <= 1) {
            this.snapToCurrentSlide();
            return;
        }

        // Find the slide closest to center (position 0)
        const slides = this.container.querySelectorAll('.manifesto-slide');
        let closestDistance = Infinity;
        let closestSlideIndex = this.currentSlide;

        slides.forEach((slide, index) => {
            const slideIndex = parseInt(slide.dataset.slideIndex);
            const position = this.getSlidePosition(index);
            const distanceFromCenter = Math.abs(position);
            
            if (distanceFromCenter < closestDistance) {
                closestDistance = distanceFromCenter;
                closestSlideIndex = index;
            }
        });

        // Snap to the closest slide
        this.currentSlide = closestSlideIndex;
        this.updateAllSlidePositions();
        this.checkInfiniteLoop();
    }

    calculateNearestSlideFromDrag(deltaX, containerWidth) {
        // Calculate how much the current position would change
        const dragPercentage = (deltaX / containerWidth) * this.slideWidth;
        
        // Find which slide would be closest to center after this drag
        const effectiveCurrentSlide = this.currentSlide - (dragPercentage / this.slideWidth);
        
        // Round to nearest integer (nearest slide)
        return Math.round(effectiveCurrentSlide);
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
        
        this.currentSlide++;
        this.ensureValidSlidePosition();
        this.goToSlide(this.currentSlide);
        this.checkInfiniteLoop();
    }

    previousSlide() {
        if (this.manifesti.length === 0) return;
        
        this.currentSlide--;
        this.ensureValidSlidePosition();
        this.goToSlide(this.currentSlide);
        this.checkInfiniteLoop();
    }

    goToSlide(index) {
        this.currentSlide = index;
        this.ensureValidSlidePosition();
        this.showSlide(this.currentSlide);
        this.restartSlideshow(); // Reset auto-advance timer
    }

    checkInfiniteLoop() {
        if (this.realSlidesCount <= 1) return;
        
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

    // showControls removed - no UI controls needed

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