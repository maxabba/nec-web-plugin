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
            }
        });
        
        // Touch/swipe and mouse support
        this.addTouchAndMouseSupport();
    }

    addTouchAndMouseSupport() {
        let startX = 0;
        let startY = 0;
        let endX = 0;
        let endY = 0;
        let isDragging = false;
        let startTime = 0;
        
        if (!this.container) return;
        
        // Unified start handler for both touch and mouse
        const handleStart = (e) => {
            isDragging = true;
            startTime = Date.now();
            
            if (e.type === 'touchstart') {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            } else {
                startX = e.clientX;
                startY = e.clientY;
                e.preventDefault(); // Prevent text selection on mouse
            }
        };
        
        // Unified move handler
        const handleMove = (e) => {
            if (!isDragging) return;
            
            if (e.type === 'touchmove') {
                endX = e.touches[0].clientX;
                endY = e.touches[0].clientY;
            } else {
                endX = e.clientX;
                endY = e.clientY;
            }
            
            // Prevent default only for significant horizontal movement
            const deltaX = Math.abs(startX - endX);
            const deltaY = Math.abs(startY - endY);
            
            if (deltaX > deltaY && deltaX > 10) {
                e.preventDefault();
            }
        };
        
        // Unified end handler
        const handleEnd = (e) => {
            if (!isDragging) return;
            
            isDragging = false;
            const deltaX = startX - endX;
            const deltaY = startY - endY;
            const timeDiff = Date.now() - startTime;
            
            // Check for tap/click (minimal movement and quick timing)
            if (Math.abs(deltaX) < 10 && Math.abs(deltaY) < 10 && timeDiff < 300) {
                this.pauseOnInteraction();
                return;
            }
            
            // Check for swipe/drag
            const minSwipeDistance = 50;
            if (Math.abs(deltaX) > minSwipeDistance && Math.abs(deltaX) > Math.abs(deltaY)) {
                if (deltaX > 0) {
                    this.nextSlide(); // Swipe left = next slide
                } else {
                    this.previousSlide(); // Swipe right = previous slide
                }
                this.pauseOnInteraction();
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
            isDragging = false;
        });
    }

    async loadManifesti() {
        try {
            console.log('Loading manifesti...', {
                vendorId: this.config.vendorId,
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
                
                if (this.manifesti.length > 0) {
                    this.renderSlideshow();
                    this.startSlideshow();
                } else {
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
        
        // Create slides
        this.manifesti.forEach((manifesto, index) => {
            const slide = this.createSlide(manifesto, index);
            this.container.appendChild(slide);
            
            // Apply background after the slide is in the DOM
            if (manifesto.vendor_data) {
                // Use setTimeout to ensure the DOM has rendered
                setTimeout(() => {
                    const wrapper = slide.querySelector('.manifesto-wrapper');
                    if (wrapper) {
                        this.updateEditorBackground(manifesto.vendor_data, wrapper);
                    }
                }, 100);
            }
        });
        
        // No UI controls needed - using touch/mouse gestures only
        
        // Show first slide without animation
        this.currentSlide = 0;
        this.showSlide(this.currentSlide, null);
    }

    createSlide(manifesto, index) {
        const slide = document.createElement('div');
        slide.className = 'manifesto-slide';
        slide.dataset.slideIndex = index;
        
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
                
                // Get actual container dimensions to ensure fit
                const containerElement = containerElem.parentElement;
                const containerRect = containerElement.getBoundingClientRect();
                const availableHeight = containerRect.height - 20; // Leave 20px margin
                const availableWidth = containerRect.width - 20;   // Leave 20px margin
                
                let optimalWidth, optimalHeight;
                
                // Calculate dimensions that fit within actual container
                if (aspectRatio > (availableWidth / availableHeight)) {
                    // Image is wider - constrain by width
                    optimalWidth = Math.min(availableWidth, availableWidth);
                    optimalHeight = optimalWidth / aspectRatio;
                } else {
                    // Image is taller - constrain by height  
                    optimalHeight = Math.min(availableHeight, availableHeight);
                    optimalWidth = optimalHeight * aspectRatio;
                }
                
                // Double check that dimensions don't exceed container
                if (optimalWidth > availableWidth) {
                    optimalWidth = availableWidth;
                    optimalHeight = optimalWidth / aspectRatio;
                }
                if (optimalHeight > availableHeight) {
                    optimalHeight = availableHeight;
                    optimalWidth = optimalHeight * aspectRatio;
                }
                
                // Apply the calculated dimensions
                backgroundDiv.style.width = optimalWidth + 'px';
                backgroundDiv.style.height = optimalHeight + 'px';
                backgroundDiv.style.margin = 'auto';
                backgroundDiv.style.maxWidth = '100%';
                backgroundDiv.style.maxHeight = '100%';
                
                // Use the actual dimensions for margin calculations
                const actualHeight = optimalHeight;
                const actualWidth = optimalWidth;

                // Calculate margins in pixels based on percentages with safety limits
                const marginTopPx = Math.min((parseFloat(data.margin_top) / 100) * actualHeight, actualHeight * 0.4);
                const marginRightPx = Math.min((parseFloat(data.margin_right) / 100) * actualWidth, actualWidth * 0.4);
                const marginBottomPx = Math.min((parseFloat(data.margin_bottom) / 100) * actualHeight, actualHeight * 0.4);
                const marginLeftPx = Math.min((parseFloat(data.margin_left) / 100) * actualWidth, actualWidth * 0.4);

                // Apply padding to text editor
                textEditor.style.paddingTop = `${marginTopPx}px`;
                textEditor.style.paddingRight = `${marginRightPx}px`;
                textEditor.style.paddingBottom = `${marginBottomPx}px`;
                textEditor.style.paddingLeft = `${marginLeftPx}px`;
                textEditor.style.textAlign = data.alignment || 'center';
                
                // Calculate font-size proportional to manifesto dimensions with small screen optimization
                const isSmallScreen = window.innerWidth < 768 || window.innerHeight < 600;
                const isVerySmallScreen = window.innerWidth < 480 || window.innerHeight < 400;
                
                let baseFontSize;
                if (isVerySmallScreen) {
                    // Very small screens - more aggressive scaling
                    baseFontSize = Math.max(8, Math.min(optimalWidth * 0.025, optimalWidth * 0.04));
                } else if (isSmallScreen) {
                    // Small screens - reduced scaling
                    baseFontSize = Math.max(10, Math.min(optimalWidth * 0.03, optimalWidth * 0.06));
                } else {
                    // Normal screens - original scaling
                    baseFontSize = Math.max(14, Math.min(optimalWidth * 0.055, optimalWidth * 0.1));
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
        
        const slides = this.container.querySelectorAll('.manifesto-slide');
        
        // Hide all slides and remove animation classes
        slides.forEach(slide => {
            slide.classList.remove('active', 'slide-in-left', 'slide-in-right');
        });
        
        // Show current slide with animation based on direction
        if (slides[index]) {
            slides[index].classList.add('active');
            
            if (direction === 'next') {
                slides[index].classList.add('slide-in-left');
            } else if (direction === 'prev') {
                slides[index].classList.add('slide-in-right');
            }
            
            // Remove animation class after animation completes
            setTimeout(() => {
                slides[index].classList.remove('slide-in-left', 'slide-in-right');
            }, 800); // Match animation duration
        }
        
        this.currentSlide = index;
    }

    nextSlide() {
        if (this.manifesti.length === 0) return;
        
        const nextIndex = (this.currentSlide + 1) % this.manifesti.length;
        this.goToSlide(nextIndex, 'next');
    }

    previousSlide() {
        if (this.manifesti.length === 0) return;
        
        const prevIndex = (this.currentSlide - 1 + this.manifesti.length) % this.manifesti.length;
        this.goToSlide(prevIndex, 'prev');
    }

    goToSlide(index, direction = null) {
        if (index >= 0 && index < this.manifesti.length) {
            this.showSlide(index, direction);
            this.restartSlideshow(); // Reset auto-advance timer
        }
    }

    startSlideshow() {
        if (this.manifesti.length <= 1) return;
        
        this.slideInterval = setInterval(() => {
            if (this.isPlaying) {
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
    }

    hideNoManifesti() {
        if (this.noManifesti) {
            this.noManifesti.style.display = 'none';
        }
        
        if (this.container) {
            this.container.style.display = 'block';
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