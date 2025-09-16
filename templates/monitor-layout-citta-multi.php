<?php
/**
 * Monitor Layout: Città Multi-Agenzia
 * Template per visualizzare slideshow di immagini annuncio di morte con logiche responsive
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get monitor data passed from monitor-display.php
$monitor_data = $GLOBALS['monitor_data'];
$layout_config = $monitor_data['layout_config'];
$vendor_data = $monitor_data['vendor_data'];

?>

<div class="citta-multi-container" id="citta-multi-container">
    <!-- Loading state -->
    <div id="citta-loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Caricamento annunci...</p>
    </div>

    <!-- No data state -->
    <div id="citta-no-data" class="no-data-state" style="display: none;">
        <div class="no-data-icon">📋</div>
        <h3>Nessun annuncio disponibile</h3>
        <p>Non ci sono annunci con immagine per il periodo selezionato</p>
    </div>

    <!-- Slideshow container -->
    <div id="slideshow-container" class="slideshow-container" style="display: none;">
        <!-- Slides will be populated via AJAX and JavaScript -->
    </div>
</div>

<style>
/* Città Multi Layout Styles */
.citta-multi-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: var(--monitor-bg-primary, rgb(55, 55, 55));
    color: var(--monitor-text-primary, #ffffff);
    overflow: hidden;
    z-index: 1000;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100vh;
    background: var(--monitor-bg-primary, rgb(55, 55, 55));
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top: 4px solid var(--monitor-status-active, #4CAF50);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-state p {
    font-size: 1.2rem;
    color: var(--monitor-text-secondary, rgba(255, 255, 255, 0.85));
}

/* No Data State */
.no-data-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100vh;
    text-align: center;
    padding: 40px;
}

.no-data-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.7;
}

.no-data-state h3 {
    font-size: 1.8rem;
    margin-bottom: 15px;
    font-weight: 300;
}

.no-data-state p {
    font-size: 1.1rem;
    color: var(--monitor-text-secondary, rgba(255, 255, 255, 0.85));
    opacity: 0.8;
}

/* Slideshow Container */
.slideshow-container {
    width: 100%;
    height: 100vh;
    position: relative;
    overflow: hidden;
    touch-action: pan-x; /* Enable horizontal touch gestures */
    cursor: grab;
}

.slideshow-container:active {
    cursor: grabbing;
}

/* Individual Slides */
.citta-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    transform: translateX(100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--monitor-bg-primary, rgb(55, 55, 55));
    will-change: transform;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    perspective: 1000px;
    -webkit-perspective: 1000px;
}

.citta-slide.active {
    transform: translate3d(0, 0, 0);
    z-index: 2;
}

.citta-slide.prev {
    transform: translate3d(-100%, 0, 0);
    z-index: 1;
}

.citta-slide.next {
    transform: translate3d(100%, 0, 0);
    z-index: 1;
}

.citta-slide.dragging {
    transition: none !important;
}

.citta-slide.no-transition {
    transition: none !important;
}

/* Image Layout Containers */
.single-image-layout {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.double-image-layout {
    width: 100%;
    height: 100%;
    display: flex;
    gap: 20px;
    padding: 20px;
}

/* Horizontal monitor + vertical images: side by side */
@media (orientation: landscape) {
    .double-image-layout {
        flex-direction: row;
        align-items: center;
        justify-content: center;
    }
}

/* Vertical monitor + horizontal images: stacked */
@media (orientation: portrait) {
    .double-image-layout {
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
}

/* Image Styling */
.citta-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover; /* Cover invece di contain per riempire più spazio */
    transition: transform 0.3s ease;
    
    /* Disable image dragging and selection */
    -webkit-user-drag: none;
    -khtml-user-drag: none;
    -moz-user-drag: none;
    -o-user-drag: none;
    user-drag: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    pointer-events: none;
}

/* For horizontal images, adjust size and prevent cropping */
.citta-image.horizontal-image {
    object-fit: contain !important; /* Contain per non tagliare le immagini orizzontali */
}

@media (orientation: landscape) {
    /* Monitor orizzontale: immagini orizzontali usano min-height */
    .citta-image.horizontal-image {
        min-height: 85vh;
    }
}

@media (orientation: portrait) {
    /* Monitor verticale: immagini orizzontali usano min-width */
    .citta-image.horizontal-image {
        min-width: 95vw;
        height: 100%;
    }
}

/* For vertical images, adjust size with inverse behavior and prevent cropping */
.citta-image.vertical-image {
    object-fit: contain !important; /* Contain per non tagliare le immagini verticali */
}

@media (orientation: landscape) {
    /* Monitor orizzontale: immagini verticali usano min-width */
    .citta-image.vertical-image {
        min-width: 85vw;
        height: 100%;
    }
}

@media (orientation: portrait) {
    /* Monitor verticale: immagini verticali usano min-height */
    .citta-image.vertical-image {
        min-height: 90vh;
        width: 100%;
    }
}

.double-image-layout .citta-image {
    flex: 1;
    max-width: calc(50% - 10px);
}

/* Override size limits for images in double layout */
@media (orientation: landscape) {
    /* Monitor orizzontale: immagini verticali in coppia - limita la larghezza */
    .double-image-layout .citta-image.vertical-image {
        min-width: 40vw !important; /* Ridotto da 85vw per stare in 2 */
        max-width: calc(50% - 10px) !important;
    }
    
    /* Monitor orizzontale: immagini orizzontali in coppia - mantieni altezza ridotta */
    .double-image-layout .citta-image.horizontal-image {
        min-height: 60vh !important; /* Ridotto da 85vh per layout doppio */
    }
}

@media (orientation: portrait) {
    /* Monitor verticale: immagini orizzontali in coppia - limita la larghezza */  
    .double-image-layout .citta-image.horizontal-image {
        min-width: 45vw !important; /* Ridotto da 95vw per stare in 2 */
        max-width: calc(90% - 10px) !important;
    }
    
    /* Monitor verticale: immagini verticali in coppia - mantieni altezza ridotta */
    .double-image-layout .citta-image.vertical-image {
        min-height: 65vh !important; /* Ridotto da 90vh per layout doppio */
    }
}

@media (orientation: portrait) {
    .double-image-layout .citta-image {
        max-width: 100%;
        max-height: calc(50% - 10px);
    }
}

/* Hover effects (for touch displays) */
.citta-image:hover {
    transform: scale(1.02);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .double-image-layout {
        gap: 10px;
        padding: 10px;
    }
    
    .single-image-layout {
        padding: 10px;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .citta-slide {
        transition: opacity 0.3s linear; /* Linear anche per reduced motion */
    }
    
    .citta-image {
        transition: none;
    }
    
    .citta-image:hover {
        transform: none;
    }
}
</style>

<script>
class CittaMultiSlideshow {
    constructor() {
        this.container = document.getElementById('slideshow-container');
        this.loadingElement = document.getElementById('citta-loading');
        this.noDataElement = document.getElementById('citta-no-data');
        this.slides = [];
        this.currentSlideIndex = 0;
        this.slideInterval = null;
        this.resumeTimeout = null;
        this.config = window.MonitorData || {};
        
        // Touch/drag handling properties
        this.startX = 0;
        this.startY = 0;
        this.currentX = 0;
        this.endX = 0;
        this.endY = 0;
        this.startTime = 0;
        this.minSwipeDistance = 50;
        this.isDragging = false;
        
        this.init();
    }

    async init() {
        console.log('Initializing Città Multi Slideshow', this.config);
        
        // Load annunci data
        await this.loadAnnunci();
        
        // Start slideshow if we have data
        if (this.slides.length > 0) {
            this.showSlideshow();
            this.startSlideshow();
        } else {
            this.showNoData();
        }
    }

    async loadAnnunci() {
        try {
            this.showLoading();
            
            const formData = new FormData();
            formData.append('action', 'monitor_get_citta_multi');
            formData.append('monitor_id', this.config.monitorId);
            formData.append('vendor_id', this.config.vendorId);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            console.log('AJAX Response:', data);
            
            if (data.success && data.data.annunci) {
                await this.processAnnunci(data.data.annunci);
            } else {
                console.error('Error loading annunci:', data.data);
                this.slides = [];
            }
        } catch (error) {
            console.error('Network error loading annunci:', error);
            this.slides = [];
        }
    }

    async processAnnunci(annunci) {
        // Filter only annunci with immagine_annuncio (check both URL string and ACF object)
        const validAnnunci = annunci.filter(annuncio => {
            const hasImageUrl = annuncio.immagine_annuncio && (
                typeof annuncio.immagine_annuncio === 'string' ||
                (typeof annuncio.immagine_annuncio === 'object' && annuncio.immagine_annuncio.url)
            );
            
            // Also check immagine_annuncio_di_morte field directly
            const hasImageField = annuncio.immagine_annuncio_di_morte && (
                typeof annuncio.immagine_annuncio_di_morte === 'string' ||
                (typeof annuncio.immagine_annuncio_di_morte === 'object' && annuncio.immagine_annuncio_di_morte.url)
            );
            
            return hasImageUrl || hasImageField;
        });
        
        console.log(`Filtered ${validAnnunci.length} annunci with images from ${annunci.length} total`);
        console.log('Valid annunci:', validAnnunci);
        
        if (validAnnunci.length === 0) {
            this.slides = [];
            return;
        }

        // Load images and detect orientations
        const imageData = await this.loadImageOrientations(validAnnunci);
        
        // Group images based on orientations and create slides
        this.createSlidesFromImages(imageData);
    }

    async loadImageOrientations(annunci) {
        const imagePromises = annunci.map(async (annuncio) => {
            return new Promise((resolve) => {
                // Get image URL from either field
                let imageUrl = null;
                if (annuncio.immagine_annuncio) {
                    imageUrl = typeof annuncio.immagine_annuncio === 'string' ? 
                        annuncio.immagine_annuncio : 
                        annuncio.immagine_annuncio.url;
                } else if (annuncio.immagine_annuncio_di_morte) {
                    imageUrl = typeof annuncio.immagine_annuncio_di_morte === 'string' ? 
                        annuncio.immagine_annuncio_di_morte : 
                        annuncio.immagine_annuncio_di_morte.url;
                }
                
                if (!imageUrl) {
                    console.warn('No valid image URL found for annuncio:', annuncio);
                    resolve(null);
                    return;
                }
                
                const img = new Image();
                img.onload = () => {
                    resolve({
                        annuncio: annuncio,
                        imageUrl: imageUrl,
                        width: img.width,
                        height: img.height,
                        isHorizontal: img.width > img.height,
                        aspectRatio: img.width / img.height
                    });
                };
                img.onerror = () => {
                    console.warn('Failed to load image:', imageUrl);
                    resolve(null);
                };
                img.src = imageUrl;
            });
        });

        const results = await Promise.all(imagePromises);
        return results.filter(result => result !== null);
    }

    createSlidesFromImages(imageData) {
        this.slides = [];
        
        if (imageData.length === 0) return;

        const monitorIsHorizontal = window.innerWidth > window.innerHeight;
        let i = 0;

        console.log(`Monitor orientation: ${monitorIsHorizontal ? 'Horizontal' : 'Vertical'}`);

        while (i < imageData.length) {
            const currentImage = imageData[i];
            console.log(`Processing image ${i}: ${currentImage.isHorizontal ? 'Horizontal' : 'Vertical'}`);
            
            // Check if we can pair this image with the next one
            if (i < imageData.length - 1) {
                const nextImage = imageData[i + 1];
                console.log(`Next image ${i + 1}: ${nextImage.isHorizontal ? 'Horizontal' : 'Vertical'}`);
                
                const canPair = this.canPairImages(currentImage, nextImage, monitorIsHorizontal);
                console.log(`Can pair images ${i} and ${i + 1}: ${canPair}`);
                
                if (canPair) {
                    // Create double image slide
                    this.slides.push({
                        type: 'double',
                        images: [currentImage, nextImage]
                    });
                    console.log(`Created double slide with images ${i} and ${i + 1}`);
                    i += 2;
                    continue;
                }
            }
            
            // Create single image slide
            this.slides.push({
                type: 'single',
                images: [currentImage]
            });
            console.log(`Created single slide with image ${i}`);
            i += 1;
        }

        console.log(`Created ${this.slides.length} slides from ${imageData.length} images`);
        console.log('Slides breakdown:', this.slides.map((slide, idx) => ({
            slide: idx,
            type: slide.type,
            images: slide.images.length
        })));
    }

    canPairImages(img1, img2, monitorIsHorizontal) {
        console.log(`canPairImages: img1=${img1.isHorizontal ? 'H' : 'V'}, img2=${img2.isHorizontal ? 'H' : 'V'}, monitor=${monitorIsHorizontal ? 'H' : 'V'}`);
        
        // RULE: Orientamenti misti → sempre 1 immagine
        if (img1.isHorizontal !== img2.isHorizontal) {
            console.log('  → Mixed orientations: NO PAIR');
            return false;
        }

        // RULE: Monitor orizzontale + immagini verticali → 2 immagini affiancate
        if (monitorIsHorizontal && !img1.isHorizontal && !img2.isHorizontal) {
            console.log('  → Horizontal monitor + vertical images: PAIR');
            return true;
        }

        // RULE: Monitor verticale + immagini orizzontali → 2 immagini in colonna
        if (!monitorIsHorizontal && img1.isHorizontal && img2.isHorizontal) {
            console.log('  → Vertical monitor + horizontal images: PAIR');
            return true;
        }

        // RULE: Monitor orizzontale + immagini orizzontali → 1 immagine
        if (monitorIsHorizontal && img1.isHorizontal && img2.isHorizontal) {
            console.log('  → Horizontal monitor + horizontal images: NO PAIR');
            return false;
        }

        // RULE: Monitor verticale + immagini verticali → 1 immagine
        if (!monitorIsHorizontal && !img1.isHorizontal && !img2.isHorizontal) {
            console.log('  → Vertical monitor + vertical images: NO PAIR');
            return false;
        }

        // Fallback: don't pair
        console.log('  → Fallback: NO PAIR');
        return false;
    }

    renderSlides() {
        if (!this.container || this.slides.length === 0) return;

        this.container.innerHTML = '';

        this.slides.forEach((slide, index) => {
            const slideElement = document.createElement('div');
            // Set initial positions - current is active, others are next
            if (index === 0) {
                slideElement.className = 'citta-slide active';
            } else if (index === this.slides.length - 1) {
                slideElement.className = 'citta-slide prev';
            } else {
                slideElement.className = 'citta-slide next';
            }
            
            if (slide.type === 'single') {
                const imageClass = `citta-image ${slide.images[0].isHorizontal ? 'horizontal-image' : 'vertical-image'}`;
                slideElement.innerHTML = `
                    <div class="single-image-layout">
                        <img src="${slide.images[0].imageUrl}" 
                             alt="Annuncio ${slide.images[0].annuncio.nome} ${slide.images[0].annuncio.cognome}"
                             class="${imageClass}">
                    </div>
                `;
            } else {
                const imageClass1 = `citta-image ${slide.images[0].isHorizontal ? 'horizontal-image' : 'vertical-image'}`;
                const imageClass2 = `citta-image ${slide.images[1].isHorizontal ? 'horizontal-image' : 'vertical-image'}`;
                slideElement.innerHTML = `
                    <div class="double-image-layout">
                        <img src="${slide.images[0].imageUrl}" 
                             alt="Annuncio ${slide.images[0].annuncio.nome} ${slide.images[0].annuncio.cognome}"
                             class="${imageClass1}">
                        <img src="${slide.images[1].imageUrl}" 
                             alt="Annuncio ${slide.images[1].annuncio.nome} ${slide.images[1].annuncio.cognome}"
                             class="${imageClass2}">
                    </div>
                `;
            }

            this.container.appendChild(slideElement);
        });
    }

    showSlideshow() {
        this.hideLoading();
        this.hideNoData();
        this.renderSlides();
        this.setupTouchControls();
        this.container.style.display = 'block';
    }

    showLoading() {
        this.loadingElement.style.display = 'flex';
        this.container.style.display = 'none';
        this.noDataElement.style.display = 'none';
    }

    hideLoading() {
        this.loadingElement.style.display = 'none';
    }

    showNoData() {
        this.hideLoading();
        this.container.style.display = 'none';
        this.noDataElement.style.display = 'flex';
    }

    hideNoData() {
        this.noDataElement.style.display = 'none';
    }

    startSlideshow() {
        if (this.slides.length <= 1) return;
        
        // Always clear any existing timer first
        this.pauseSlideshow();

        this.slideInterval = setInterval(() => {
            this.nextSlide();
        }, this.config.slideInterval || 5000);
        
        console.log('Slideshow started with interval:', this.config.slideInterval || 5000);
    }

    nextSlide() {
        if (this.slides.length <= 1) return;

        // Update index first
        this.currentSlideIndex = (this.currentSlideIndex + 1) % this.slides.length;
        this.updateSlideClasses();
    }

    previousSlide() {
        if (this.slides.length <= 1) return;

        // Update index first
        this.currentSlideIndex = this.currentSlideIndex === 0 ? 
            this.slides.length - 1 : 
            this.currentSlideIndex - 1;
        this.updateSlideClasses();
    }
    
    updateSlideClasses() {
        const slides = Array.from(this.container.children);
        const totalSlides = this.slides.length;
        
        slides.forEach((slide, index) => {
            slide.classList.remove('active', 'prev', 'next');
            
            if (index === this.currentSlideIndex) {
                // Current slide is active
                slide.classList.add('active');
            } else {
                // Calculate relative position
                const prevIndex = (this.currentSlideIndex - 1 + totalSlides) % totalSlides;
                const nextIndex = (this.currentSlideIndex + 1) % totalSlides;
                
                if (index === prevIndex) {
                    // Previous slide
                    slide.classList.add('prev');
                } else if (index === nextIndex || totalSlides === 2) {
                    // Next slide (or opposite slide if only 2 slides)
                    slide.classList.add('next');
                } else {
                    // All other slides go to the right
                    slide.classList.add('next');
                }
            }
        });
    }

    setupTouchControls() {
        if (!this.container) return;

        // Touch events
        this.container.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
        this.container.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        this.container.addEventListener('touchend', (e) => this.handleTouchEnd(e), { passive: true });
        
        // Mouse events for desktop testing
        this.container.addEventListener('mousedown', (e) => this.handleMouseStart(e));
        this.container.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        this.container.addEventListener('mouseup', (e) => this.handleMouseEnd(e));
        
        // Prevent context menu on long press
        this.container.addEventListener('contextmenu', (e) => e.preventDefault());
        
        // Handle mouse leave
        this.container.addEventListener('mouseleave', () => {
            if (this.isDragging) {
                this.isDragging = false;
                // Remove dragging class from all slides
                const slides = this.container.querySelectorAll('.citta-slide');
                slides.forEach(slide => slide.classList.remove('dragging'));
                // Snap back to current slide
                this.snapToCurrentSlide();
            }
        });
    }

    handleTouchStart(e) {
        this.isDragging = true;
        this.startX = e.touches[0].clientX;
        this.startY = e.touches[0].clientY;
        this.currentX = this.startX;
        this.startTime = Date.now();
        
        // Add dragging class to disable transitions
        const slides = this.container.querySelectorAll('.citta-slide');
        slides.forEach(slide => slide.classList.add('dragging'));
        
        // Pause auto slideshow during touch
        this.pauseSlideshow();
    }

    handleTouchMove(e) {
        if (!this.isDragging) return;
        
        this.currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const deltaX = this.currentX - this.startX;
        const deltaY = Math.abs(currentY - this.startY);
        
        // Determine if this is primarily a horizontal swipe
        if (Math.abs(deltaX) > 5) { // Lower threshold for more responsive detection
            // Only prevent default if horizontal movement is dominant
            if (Math.abs(deltaX) > deltaY * 1.5) {
                e.preventDefault();
            }
            // Always update positions if there's horizontal movement
            this.updateSlidePositions(deltaX);
        }
    }

    handleTouchEnd(e) {
        if (!this.isDragging) return;
        
        this.isDragging = false;
        this.endX = e.changedTouches[0].clientX;
        this.endY = e.changedTouches[0].clientY;
        
        // Remove dragging class to enable transitions
        const slides = this.container.querySelectorAll('.citta-slide');
        slides.forEach(slide => slide.classList.remove('dragging'));
        
        this.handleSwipe();
        
        // Resume auto slideshow after touch
        this.resumeSlideshow();
    }

    handleMouseStart(e) {
        this.isDragging = true;
        this.startX = e.clientX;
        this.startY = e.clientY;
        this.currentX = this.startX;
        this.startTime = Date.now();
        
        // Add dragging class
        const slides = this.container.querySelectorAll('.citta-slide');
        slides.forEach(slide => slide.classList.add('dragging'));
        
        this.pauseSlideshow();
    }

    handleMouseMove(e) {
        if (!this.isDragging) return;
        
        this.currentX = e.clientX;
        const currentY = e.clientY;
        const deltaX = this.currentX - this.startX;
        const deltaY = Math.abs(currentY - this.startY);
        
        // Lower threshold and more responsive detection
        if (Math.abs(deltaX) > 5) {
            if (Math.abs(deltaX) > deltaY * 1.5) {
                e.preventDefault();
            }
            this.updateSlidePositions(deltaX);
        }
    }

    handleMouseEnd(e) {
        if (!this.isDragging) return;
        
        this.isDragging = false;
        this.endX = e.clientX;
        this.endY = e.clientY;
        
        // Remove dragging class
        const slides = this.container.querySelectorAll('.citta-slide');
        slides.forEach(slide => slide.classList.remove('dragging'));
        
        this.handleSwipe();
        this.resumeSlideshow();
    }

    updateSlidePositions(deltaX) {
        const slides = this.container.querySelectorAll('.citta-slide');
        const containerWidth = this.container.offsetWidth;
        const isLandscape = window.innerWidth > window.innerHeight;
        
        // Different thresholds for landscape vs portrait
        const swipeThreshold = isLandscape 
            ? containerWidth * 0.25  // 25% for landscape
            : containerWidth * 0.15; // 15% for portrait
        
        // Calculate drag percentage with resistance after threshold
        let dragPercentage = (deltaX / containerWidth) * 100;
        
        // Add resistance when approaching/exceeding threshold for visual feedback
        if (Math.abs(deltaX) > swipeThreshold) {
            const excess = Math.abs(deltaX) - swipeThreshold;
            const resistance = 1 - (excess / containerWidth) * 0.5; // Gradually increase resistance
            const resistedExcess = excess * Math.max(0.3, resistance);
            const sign = deltaX > 0 ? 1 : -1;
            const thresholdPercentage = (swipeThreshold / containerWidth) * 100;
            dragPercentage = sign * (thresholdPercentage + (resistedExcess / containerWidth) * 100);
        }
        
        // Apply positions to slides
        slides.forEach((slide) => {
            let basePosition = 0;
            if (slide.classList.contains('active')) {
                basePosition = 0;
            } else if (slide.classList.contains('prev')) {
                basePosition = -100;
            } else if (slide.classList.contains('next')) {
                basePosition = 100;
            }
            
            const newPosition = basePosition + dragPercentage;
            // Use translate3d for hardware acceleration
            slide.style.transform = `translate3d(${newPosition}%, 0, 0)`;
        });
    }

    snapToCurrentSlide() {
        const slides = this.container.querySelectorAll('.citta-slide');
        slides.forEach(slide => {
            // Remove inline transform to restore CSS class positions
            slide.style.transform = '';
        });
    }

    handleSwipe() {
        const deltaX = this.endX - this.startX;
        const deltaY = Math.abs(this.endY - this.startY);
        const containerWidth = this.container.offsetWidth;
        const isLandscape = window.innerWidth > window.innerHeight;
        
        // Different thresholds for landscape vs portrait
        const swipeThreshold = isLandscape 
            ? containerWidth * 0.25  // 25% for landscape (wider screens need more movement)
            : containerWidth * 0.15; // 15% for portrait (narrower screens need less)
        
        const timeDiff = Date.now() - this.startTime;
        const velocity = Math.abs(deltaX) / timeDiff; // pixels per millisecond
        
        // First, remove inline transforms to enable smooth CSS transition
        this.snapToCurrentSlide();
        
        // Check if horizontal swipe is significant enough
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            // Consider both distance and velocity for natural swipe
            const isQuickSwipe = velocity > 0.5 && Math.abs(deltaX) > 50; // Quick flick gesture
            const isLongSwipe = Math.abs(deltaX) > swipeThreshold; // Long deliberate swipe
            
            if (isQuickSwipe || isLongSwipe) {
                // Threshold exceeded - move to next/prev slide with smooth transition
                setTimeout(() => {
                    if (deltaX > 0) {
                        // Swipe right - previous slide
                        this.previousSlide();
                    } else {
                        // Swipe left - next slide
                        this.nextSlide();
                    }
                }, 10); // Small delay to ensure snap has taken effect
            }
            // If threshold not exceeded, we already snapped back to current slide
        }
        // If vertical swipe, we already snapped back to current slide
    }

    pauseSlideshow() {
        if (this.slideInterval) {
            console.log('Slideshow paused');
            clearInterval(this.slideInterval);
            this.slideInterval = null;
        }
    }

    resumeSlideshow() {
        // Clear any existing resume timeout
        if (this.resumeTimeout) {
            clearTimeout(this.resumeTimeout);
            this.resumeTimeout = null;
        }
        
        // Resume after a delay to avoid immediate transition
        this.resumeTimeout = setTimeout(() => {
            console.log('Slideshow resuming after touch...');
            this.startSlideshow();
            this.resumeTimeout = null;
        }, 2000);
    }

    destroy() {
        // Clear all timers
        if (this.slideInterval) {
            clearInterval(this.slideInterval);
            this.slideInterval = null;
        }
        
        if (this.resumeTimeout) {
            clearTimeout(this.resumeTimeout);
            this.resumeTimeout = null;
        }
        
        // Remove event listeners
        if (this.container) {
            this.container.removeEventListener('touchstart', this.handleTouchStart);
            this.container.removeEventListener('touchend', this.handleTouchEnd);
            this.container.removeEventListener('mousedown', this.handleMouseStart);
            this.container.removeEventListener('mouseup', this.handleMouseEnd);
            this.container.removeEventListener('contextmenu', (e) => e.preventDefault());
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (window.MonitorData && window.MonitorData.layoutType === 'citta_multi') {
        window.cittaMultiSlideshow = new CittaMultiSlideshow();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.cittaMultiSlideshow) {
        window.cittaMultiSlideshow.destroy();
    }
});
</script>