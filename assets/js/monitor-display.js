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
        this.prevBtn = document.getElementById('prev-btn');
        this.nextBtn = document.getElementById('next-btn');
        this.indicators = document.getElementById('slide-indicators');
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
        // Navigation buttons
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.previousSlide());
        }
        
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.nextSlide());
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    this.previousSlide();
                    break;
                case 'ArrowRight':
                    this.nextSlide();
                    break;
                case ' ':
                    e.preventDefault();
                    this.toggleSlideshow();
                    break;
            }
        });
        
        // Touch/swipe support
        this.addTouchSupport();
    }

    addTouchSupport() {
        let startX = 0;
        let startY = 0;
        let endX = 0;
        let endY = 0;
        
        if (this.container) {
            this.container.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            });
            
            this.container.addEventListener('touchend', (e) => {
                endX = e.changedTouches[0].clientX;
                endY = e.changedTouches[0].clientY;
                
                const deltaX = startX - endX;
                const deltaY = startY - endY;
                
                // Minimum swipe distance
                if (Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY)) {
                    if (deltaX > 0) {
                        this.nextSlide();
                    } else {
                        this.previousSlide();
                    }
                }
            });
        }
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
        });
        
        // Show controls if more than one slide
        if (this.manifesti.length > 1) {
            this.showControls(true);
            this.renderIndicators();
        } else {
            this.showControls(false);
        }
        
        // Show first slide
        this.currentSlide = 0;
        this.showSlide(this.currentSlide);
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

    renderIndicators() {
        if (!this.indicators || this.manifesti.length <= 1) return;
        
        this.indicators.innerHTML = '';
        
        this.manifesti.forEach((_, index) => {
            const indicator = document.createElement('div');
            indicator.className = 'slide-indicator';
            indicator.dataset.slideIndex = index;
            
            indicator.addEventListener('click', () => {
                this.goToSlide(index);
            });
            
            this.indicators.appendChild(indicator);
        });
        
        this.indicators.style.display = 'flex';
    }

    showSlide(index) {
        if (!this.container || this.manifesti.length === 0) return;
        
        const slides = this.container.querySelectorAll('.manifesto-slide');
        const indicators = this.indicators ? this.indicators.querySelectorAll('.slide-indicator') : [];
        
        // Hide all slides
        slides.forEach(slide => slide.classList.remove('active'));
        indicators.forEach(indicator => indicator.classList.remove('active'));
        
        // Show current slide
        if (slides[index]) {
            slides[index].classList.add('active');
        }
        
        if (indicators[index]) {
            indicators[index].classList.add('active');
        }
        
        this.currentSlide = index;
    }

    nextSlide() {
        if (this.manifesti.length === 0) return;
        
        const nextIndex = (this.currentSlide + 1) % this.manifesti.length;
        this.goToSlide(nextIndex);
    }

    previousSlide() {
        if (this.manifesti.length === 0) return;
        
        const prevIndex = (this.currentSlide - 1 + this.manifesti.length) % this.manifesti.length;
        this.goToSlide(prevIndex);
    }

    goToSlide(index) {
        if (index >= 0 && index < this.manifesti.length) {
            this.showSlide(index);
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

    showControls(show) {
        if (this.prevBtn) this.prevBtn.style.display = show ? 'flex' : 'none';
        if (this.nextBtn) this.nextBtn.style.display = show ? 'flex' : 'none';
        if (this.indicators) this.indicators.style.display = show ? 'flex' : 'none';
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
        
        this.showControls(false);
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