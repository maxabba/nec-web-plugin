<?php
/**
 * Monitor Layout: Città Multi-Agenzia  
 * Template per visualizzare annunci di morte della città con filtri configurabili
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get monitor data passed from monitor-display.php
$monitor_data = $GLOBALS['monitor_data'];
$layout_config = $monitor_data['layout_config'];
$vendor_data = $monitor_data['vendor_data'];

?>

<div class="monitor-citta-multi-slideshow" style="height: 100vh; width: 100vw; position: fixed; top: 0; left: 0; z-index: 1000;">
    <!-- Loading state -->
    <div id="citta-loading" class="loading-state" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Caricamento annunci...</p>
    </div>

    <!-- No data state -->
    <div id="citta-no-data" class="no-data-state" style="display: none;">
        <div class="no-data-icon">📋</div>
        <h3>Nessun annuncio disponibile</h3>
        <p>Non ci sono annunci di morte per il periodo selezionato</p>
    </div>

    <!-- Slideshow container -->
    <div id="slideshow-container" class="slideshow-container" style="display: none; height: 100vh; width: 100vw;">
        <!-- Slides populated via AJAX -->
    </div>
</div>

<style>
/* Layout Città Multi-Agenzia Styles - Specific to this layout only */

html, body {
    background: rgb(55, 55, 55) !important;
    margin: 0;
    padding: 0;
    height: 100vh;
    overflow: hidden;
}

.monitor-citta-multi {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: rgb(55, 55, 55);
    color: #ffffff;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    overflow: hidden;
}

/* Header Styles */
.citta-multi-header {
    height: 15vh;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    background: rgb(55, 55, 55);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.city-info {
    display: flex;
    align-items: center;
    gap: 30px;
}

.city-icon {
    font-size: 4rem;
    opacity: 0.8;
}

.city-details h1 {
    font-size: 2.5rem;
    font-weight: 300;
    margin: 0;
    color: #ffffff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.date-range {
    font-size: 1.3rem;
    color: rgba(255, 255, 255, 0.85);
    margin-top: 8px;
    font-weight: 300;
}

.header-stats {
    display: flex;
    gap: 30px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    line-height: 1;
    color: #4CAF50;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-label {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
    margin-top: 4px;
}

/* Main Content */
.citta-multi-main {
    height: 75vh;
    position: relative;
    overflow: hidden;
    background: rgb(55, 55, 55);
}

/* Slideshow Container - come nei manifesti */
.slideshow-container {
    width: 100%;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    box-sizing: border-box;
    opacity: 0;
    transition: opacity 1s ease-in-out;
}

.slide.active {
    opacity: 1;
}

/* Single Image Slide */
.slide.single-image {
    justify-content: center;
}

.slide.single-image .slide-image {
    width: 80vw;
    height: 80vh;
    object-fit: contain;
    object-position: center;
    border-radius: 8px;
}

/* Double Image Slides */
.slide.double-images {
    gap: 20px;
}

/* Horizontal monitor + Vertical images = side by side */
@media (orientation: landscape) {
    .slide.double-images.vertical-images {
        flex-direction: row;
    }
    
    .slide.double-images.vertical-images .slide-image {
        width: 40vw;
        height: 80vh;
        object-fit: contain;
        object-position: center;
    }
}

/* Vertical monitor + Horizontal images = stacked */
@media (orientation: portrait) {
    .slide.double-images.horizontal-images {
        flex-direction: column;
    }
    
    .slide.double-images.horizontal-images .slide-image {
        width: 80vw;
        height: 37vh;
        object-fit: contain;
        object-position: center;
    }
}

.slide-image {
    border-radius: 8px;
    transition: 0.5s ease;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.slide-image.loaded {
    opacity: 1;
}

/* Text Fallback Slide */
.slide.text-slide {
    flex-direction: column;
    text-align: center;
    justify-content: center;
    padding: 40px;
}

.slide.text-slide .text-content {
    font-family: "PlayFair Display Mine", serif;
    font-size: 2rem;
    line-height: 1.6;
    color: #ffffff;
    max-width: 80%;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

@media (max-width: 768px) {
    .slide.text-slide .text-content {
        font-size: 1.5rem;
    }
}

/* Touch and Drag Support - disabilitato per transizioni opacity */
.slideshow-container.dragging .slide {
    transition: none;
}

.slideshow-container.transitioning .slide {
    transition: opacity 1s ease-in-out;
}

.loading-state, .no-data-state {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top: 4px solid #4CAF50;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.no-data-icon {
    font-size: 5rem;
    opacity: 0.5;
    margin-bottom: 20px;
}

.no-data-state h3 {
    font-size: 1.8rem;
    margin: 0 0 10px 0;
    font-weight: 300;
}

.no-data-state p {
    font-size: 1.1rem;
    opacity: 0.8;
}

/* Annunci Container */
.annunci-container {
    height: 100%;
    overflow-y: auto;
    padding: 20px 40px;
    background: rgb(55, 55, 55);
    
    /* Custom scrollbar */
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
}

.annunci-container::-webkit-scrollbar {
    width: 8px;
}

.annunci-container::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.annunci-container::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.annunci-container::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Individual Annuncio Card */
.annuncio-card {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 30px;
    transition: 0.3s ease;
    animation: slideInFromLeft 0.6s ease-out;
}

.annuncio-card:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: scale(1.02);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
}

.annuncio-card.own-vendor {
    border-left: 4px solid #f39c12;
    background: rgba(243, 156, 18, 0.1);
}

@keyframes slideInFromLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.annuncio-foto {
    flex-shrink: 0;
    width: 80px;
    height: 80px;
    border-radius: 8px;
    object-fit: contain;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
    transition: 0.3s ease;
}

.annuncio-foto-placeholder {
    flex-shrink: 0;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.6);
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.annuncio-details {
    flex: 1;
    min-width: 0; /* Prevent flex overflow */
}

.annuncio-nome {
    font-size: 1.5rem;
    font-weight: 400;
    margin: 0 0 8px 0;
    color: #ffffff;
}

.annuncio-meta {
    display: flex;
    gap: 20px;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 8px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.meta-icon {
    opacity: 0.7;
}

.annuncio-agency {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
    font-style: italic;
}

.agency-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    margin-left: 10px;
}

.agency-badge.own {
    background: rgba(243, 156, 18, 0.3);
    color: #f39c12;
}

/* Footer */
.citta-multi-footer {
    height: 10vh;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    background: rgb(55, 55, 55);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.vendor-logo-citta {
    height: 30px;
    width: auto;
    border-radius: 8px;
    transition: 0.3s ease;
}

.vendor-name {
    font-size: 1.1rem;
    font-weight: 400;
}

.system-info {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    margin-top: 2px;
}

.update-info {
    text-align: right;
    font-size: 0.85rem;
}

.auto-refresh {
    color: #4CAF50;
    margin-bottom: 4px;
}

.last-update {
    color: rgba(255, 255, 255, 0.7);
}

/* Responsive Design - Desktop Large */
@media (min-width: 1920px) and (max-width: 3839px) {
    .city-details h1 { font-size: 2.2rem; }
}

/* Desktop Standard */
@media (max-width: 1366px) {
    .city-details h1 { font-size: 1.9rem; }
    
    .annunci-container {
        padding: 15px 30px;
    }
    
    .annuncio-card {
        padding: 20px;
    }
}

@media (max-width: 768px) {
    .citta-multi-header {
        flex-direction: column;
        height: 20vh;
        gap: 15px;
        padding: 15px 20px;
        text-align: center;
    }
    
    .city-info {
        flex-direction: column;
        gap: 15px;
    }
    
    .city-details h1 {
        font-size: 1.5rem;
    }
    
    .header-stats {
        gap: 20px;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .annunci-container {
        padding: 15px 20px;
    }
    
    .annuncio-card {
        flex-direction: column;
        text-align: center;
        gap: 15px;
        padding: 20px 15px;
    }
    
    .annuncio-meta {
        flex-direction: column;
        gap: 8px;
        align-items: center;
    }
    
    .citta-multi-footer {
        flex-direction: column;
        height: 12vh;
        gap: 10px;
        text-align: center;
        padding: 10px 20px;
    }
}

/* Portrait Orientation (Totem Mode) */
@media (orientation: portrait) {
    .citta-multi-header {
        height: 15vh;
    }
    
    .citta-multi-main {
        height: 75vh;
    }
    
    .citta-multi-footer {
        height: 10vh;
    }
    
    .annuncio-card {
        padding: 20px;
    }
    
    .annuncio-nome {
        font-size: 1.3rem;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .monitor-citta-multi {
        background: rgb(55, 55, 55);
    }
    
    .annuncio-card {
        background: rgba(255, 255, 255, 0.2);
        border: 3px solid #fff;
    }
}

/* Smooth scrolling for better UX */
.annunci-container {
    scroll-behavior: smooth;
}

/* Auto-scroll animation class */
.auto-scrolling {
    animation: autoScroll 120s linear infinite;
}

@keyframes autoScroll {
    0% { scroll-behavior: auto; }
    100% { scroll-behavior: auto; }
}

/* Custom scrollbar styles (if needed for debugging) */
::-webkit-scrollbar {
    display: none;
}

html {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

<script>
// Città Multi-Agenzia Slideshow Layout JavaScript
class CittaMultiSlideshowLayout {
    constructor() {
        this.annunci = [];
        this.slides = [];
        this.currentSlideIndex = 0;
        this.pollingInterval = null;
        this.slideshowInterval = null;
        this.config = window.MonitorData || {};
        
        // Touch/Drag properties
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.currentX = 0;
        this.initialTransform = 0;
        this.threshold = 50; // Minimum drag distance for slide
        
        // Timing properties
        this.autoAdvanceTime = 10000; // 10s single, 15s double
        this.manualInteractionTimeout = null;
        this.isManualMode = false;
        
        // Monitor and image orientation
        this.monitorIsLandscape = window.innerWidth > window.innerHeight;
        
        this.init();
    }

    init() {
        console.log('Initializing Città Multi-Agenzia Slideshow Layout');
        
        // Load initial data
        this.loadAnnunci();
        
        // Setup touch/drag events
        this.setupTouchEvents();
        
        // Handle window resize for orientation changes
        window.addEventListener('resize', this.handleResize.bind(this));
        
        // Start polling for updates
        this.startPolling();
        
        // Handle visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseLayout();
            } else {
                this.resumeLayout();
            }
        });
    }

    async loadAnnunci() {
        try {
            // Only show loading on first load
            if (this.slides.length === 0) {
                this.showLoading(true);
                console.log('Initial loading annunci città multi slideshow...');
            }
            
            const formData = new FormData();
            formData.append('action', 'monitor_get_citta_multi');
            formData.append('monitor_id', this.config.monitorId);
            formData.append('vendor_id', this.config.vendorId);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                const newAnnunci = data.data.annunci || [];
                
                // Check if data has changed (compare length and first few IDs)
                const hasChanged = this.hasDataChanged(newAnnunci);
                
                if (hasChanged || this.slides.length === 0) {
                    console.log('Data changed, processing', newAnnunci.length, 'annunci');
                    this.annunci = newAnnunci;
                    
                    if (this.annunci.length > 0) {
                        const currentSlideIndex = this.currentSlideIndex;
                        const wasPlaying = this.slideshowInterval !== null;
                        
                        await this.processAnnunciForSlideshow();
                        
                        if (this.slides.length > 0) {
                            this.renderSlideshow();
                            this.showSlideshow();
                            
                            // Restore slide position
                            this.currentSlideIndex = Math.min(currentSlideIndex, this.slides.length - 1);
                            this.updateSlideVisibility();
                            
                            // Start progressive preloading
                            this.startProgressivePreloading();
                            
                            if (wasPlaying || this.slides.length === 1) {
                                this.startSlideshow();
                            }
                        } else {
                            this.showNoData();
                        }
                    } else {
                        this.showNoData();
                    }
                } else {
                    console.log('No data changes detected, continuing slideshow');
                }
            } else {
                console.error('Error loading annunci:', data.data);
                if (this.slides.length === 0) this.showNoData();
            }
        } catch (error) {
            console.error('Network error loading annunci:', error);
            if (this.slides.length === 0) this.showNoData();
        } finally {
            // Always hide loading after first load attempt
            this.showLoading(false);
        }
    }

    async processAnnunciForSlideshow() {
        this.slides = [];
        const imageOrientations = [];
        
        // First pass: determine image orientations
        for (let annuncio of this.annunci) {
            
            // Handle immagine_annuncio_di_morte - single object or array
            if (annuncio.immagine_annuncio_di_morte) {
                let imageUrl = null;
                const imageField = annuncio.immagine_annuncio_di_morte;
                
                // Handle different ACF image formats
                if (Array.isArray(imageField) && imageField.length > 0) {
                    // Array of images - process first one
                    const firstImage = imageField[0];
                    if (typeof firstImage === 'string') {
                        imageUrl = firstImage; // Direct URL
                    } else if (firstImage && firstImage.url) {
                        imageUrl = firstImage.url; // ACF image object
                    } else if (firstImage && firstImage.sizes && firstImage.sizes.large) {
                        imageUrl = firstImage.sizes.large; // ACF with sizes
                    }
                } else if (typeof imageField === 'string') {
                    imageUrl = imageField; // Direct URL string
                } else if (imageField && imageField.url) {
                    imageUrl = imageField.url; // Single ACF image object
                } else if (imageField && imageField.sizes && imageField.sizes.large) {
                    imageUrl = imageField.sizes.large; // Single ACF with sizes
                }
                
                if (imageUrl) {
                    const orientation = await this.getImageOrientation(imageUrl);
                    imageOrientations.push({
                        annuncio: annuncio,
                        type: 'image',
                        orientation: orientation,
                        url: imageUrl
                    });
                    continue; // Skip text fallback for this annuncio
                }
            }
            
            // Text fallback only if no image found
            if (this.shouldShowTextFallback() && annuncio.testo_annuncio_di_morte) {
                imageOrientations.push({
                    annuncio: annuncio,
                    type: 'text',
                    text: annuncio.testo_annuncio_di_morte
                });
            }
        }
        
        // Second pass: group images for optimal display
        this.slides = this.groupImagesForSlides(imageOrientations);
        
        console.log(`Processed ${this.annunci.length} annunci into ${this.slides.length} slides`);
    }

    async getImageOrientation(imageUrl) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const orientation = img.width > img.height ? 'landscape' : 'portrait';
                resolve(orientation);
            };
            img.onerror = () => resolve('unknown');
            img.src = imageUrl;
        });
    }

    groupImagesForSlides(items) {
        const slides = [];
        let i = 0;
        
        while (i < items.length) {
            const currentItem = items[i];
            
            // Text slides are always single
            if (currentItem.type === 'text') {
                slides.push({
                    type: 'single',
                    content: [currentItem],
                    duration: 10000 // 10s for text
                });
                i++;
                continue;
            }
            
            // Check if we can pair with next image
            const nextItem = items[i + 1];
            if (nextItem && nextItem.type === 'image' && this.canPairImages(currentItem, nextItem)) {
                slides.push({
                    type: 'double',
                    content: [currentItem, nextItem],
                    duration: 15000 // 15s for double
                });
                i += 2;
            } else {
                slides.push({
                    type: 'single',
                    content: [currentItem],
                    duration: 10000 // 10s for single
                });
                i++;
            }
        }
        
        return slides;
    }

    canPairImages(item1, item2) {
        // Only pair images with same orientation
        if (item1.orientation !== item2.orientation) {
            return false;
        }
        
        // Check monitor vs image orientation compatibility for pairing
        if (this.monitorIsLandscape && item1.orientation === 'portrait') {
            return true; // Landscape monitor + portrait images = side by side
        }
        
        if (!this.monitorIsLandscape && item1.orientation === 'landscape') {
            return true; // Portrait monitor + landscape images = stacked
        }
        
        // Don't pair when monitor and image orientations are the same
        return false; 
    }

    hasDataChanged(newAnnunci) {
        if (!this.annunci || this.annunci.length !== newAnnunci.length) {
            return true;
        }
        
        // Compare first 3 IDs to detect changes
        for (let i = 0; i < Math.min(3, newAnnunci.length); i++) {
            if (this.annunci[i]?.id !== newAnnunci[i]?.id) {
                return true;
            }
        }
        
        return false;
    }

    startProgressivePreloading() {
        console.log('🖼️ Starting progressive image preloading');
        
        // Preload next 5 images
        const startIndex = Math.max(0, this.currentSlideIndex - 2);
        const endIndex = Math.min(this.slides.length, this.currentSlideIndex + 8);
        
        for (let i = startIndex; i < endIndex; i++) {
            if (i !== this.currentSlideIndex) {
                this.preloadSlideImages(i);
            }
        }
    }

    preloadSlideImages(slideIndex) {
        if (!this.slides[slideIndex]) return;
        
        const slide = this.slides[slideIndex];
        slide.content.forEach(item => {
            if (item.type === 'image' && item.url) {
                const img = new Image();
                img.onload = () => console.log(`📥 Preloaded image for slide ${slideIndex + 1}`);
                img.onerror = () => console.warn(`❌ Failed to preload image for slide ${slideIndex + 1}`);
                img.src = item.url;
            }
        });
    }

    shouldShowTextFallback() {
        // Check if PHP constant is defined and enabled
        return window.MONITOR_CITTA_SHOW_TEXT_FALLBACK !== false;
    }

    renderSlideshow() {
        const container = document.getElementById('slideshow-container');
        if (!container || this.slides.length === 0) return;
        
        container.innerHTML = this.slides.map((slide, index) => this.createSlideHTML(slide, index)).join('');
        
        // Set first slide as active
        if (this.slides.length > 0) {
            this.updateSlideVisibility();
        }
    }

    createSlideHTML(slide, index) {
        const activeClass = index === this.currentSlideIndex ? ' active' : '';
        
        if (slide.type === 'single' && slide.content[0].type === 'text') {
            return `
                <div class="slide text-slide${activeClass}" data-index="${index}">
                    <div class="text-content">${slide.content[0].text}</div>
                </div>
            `;
        }
        
        if (slide.type === 'single') {
            return `
                <div class="slide single-image${activeClass}" data-index="${index}">
                    <img src="${slide.content[0].url}" class="slide-image" alt="Annuncio di morte" onload="this.classList.add('loaded')">
                </div>
            `;
        }
        
        if (slide.type === 'double') {
            const orientationClass = slide.content[0].orientation === 'portrait' ? 'vertical-images' : 'horizontal-images';
            return `
                <div class="slide double-images ${orientationClass}${activeClass}" data-index="${index}">
                    <img src="${slide.content[0].url}" class="slide-image" alt="Annuncio di morte" onload="this.classList.add('loaded')">
                    <img src="${slide.content[1].url}" class="slide-image" alt="Annuncio di morte" onload="this.classList.add('loaded')">
                </div>
            `;
        }
        
        return '';
    }

    showLoading(show) {
        const loading = document.getElementById('citta-loading');
        if (loading) {
            loading.style.display = show ? 'flex' : 'none';
        }
    }

    showSlideshow() {
        const container = document.getElementById('slideshow-container');
        const noData = document.getElementById('citta-no-data');
        
        if (container) container.style.display = 'block';
        if (noData) noData.style.display = 'none';
    }

    showNoData() {
        const container = document.getElementById('slideshow-container');
        const noData = document.getElementById('citta-no-data');
        
        if (container) container.style.display = 'none';
        if (noData) noData.style.display = 'flex';
    }

    startSlideshow() {
        if (this.slides.length === 0) return;
        
        this.stopSlideshow();
        this.scheduleNextSlide();
    }

    stopSlideshow() {
        if (this.slideshowInterval) {
            clearTimeout(this.slideshowInterval);
            this.slideshowInterval = null;
        }
    }

    scheduleNextSlide() {
        if (this.slides.length === 0) return;
        
        // IMPORTANTE: Fermati prima di programmare il prossimo
        this.stopSlideshow();
        
        const currentSlide = this.slides[this.currentSlideIndex];
        const duration = this.isManualMode ? currentSlide.duration * 2 : currentSlide.duration;
        
        // Debug timing più dettagliato
        console.log(`⏰ Scheduling slide ${this.currentSlideIndex + 1}/${this.slides.length} in ${duration}ms (${duration/1000}s)`);
        console.log(`   Manual mode: ${this.isManualMode}, Slide type: ${currentSlide.type}, Base duration: ${currentSlide.duration}ms`);
        
        this.slideshowInterval = setTimeout(() => {
            console.log(`🎬 Executing slide transition from ${this.currentSlideIndex} to ${(this.currentSlideIndex + 1) % this.slides.length}`);
            this.nextSlide();
        }, duration);
    }

    nextSlide() {
        if (this.slides.length === 0) return;
        
        this.currentSlideIndex = (this.currentSlideIndex + 1) % this.slides.length;
        this.updateSlideVisibility();
        
        // Preload upcoming images
        const nextIndex = (this.currentSlideIndex + 2) % this.slides.length;
        const nextNextIndex = (this.currentSlideIndex + 3) % this.slides.length;
        this.preloadSlideImages(nextIndex);
        this.preloadSlideImages(nextNextIndex);
        
        this.scheduleNextSlide();
    }

    previousSlide() {
        if (this.slides.length === 0) return;
        
        this.currentSlideIndex = this.currentSlideIndex === 0 ? this.slides.length - 1 : this.currentSlideIndex - 1;
        this.updateSlideVisibility();
        this.onManualInteraction();
    }

    updateSlideVisibility() {
        const slides = document.querySelectorAll('.slide');
        if (slides.length === 0) return;
        
        slides.forEach((slide, index) => {
            if (index === this.currentSlideIndex) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });
        
        console.log(`🎬 Switched to slide ${this.currentSlideIndex + 1}/${this.slides.length}`);
    }

    setupTouchEvents() {
        const container = document.getElementById('slideshow-container');
        if (!container) return;
        
        // Mouse events
        container.addEventListener('mousedown', this.handleStart.bind(this));
        container.addEventListener('mousemove', this.handleMove.bind(this));
        container.addEventListener('mouseup', this.handleEnd.bind(this));
        container.addEventListener('mouseleave', this.handleEnd.bind(this));
        
        // Touch events
        container.addEventListener('touchstart', this.handleStart.bind(this), { passive: false });
        container.addEventListener('touchmove', this.handleMove.bind(this), { passive: false });
        container.addEventListener('touchend', this.handleEnd.bind(this));
        
        // Click events
        container.addEventListener('click', this.handleClick.bind(this));
    }

    handleStart(e) {
        e.preventDefault();
        this.isDragging = true;
        
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        
        this.startX = clientX;
        this.startY = clientY;
        this.currentX = clientX;
        
        // Con sistema opacity non abbiamo track da trascinare
        this.initialTransform = 0;
        
        document.querySelector('.slideshow-container')?.classList.add('dragging');
    }

    handleMove(e) {
        if (!this.isDragging) return;
        e.preventDefault();
        
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        this.currentX = clientX;
        
        // Con sistema opacity non implementiamo drag visuale live
        // Solo registriamo il movimento per il threshold
    }

    handleEnd(e) {
        if (!this.isDragging) return;
        
        this.isDragging = false;
        const deltaX = this.currentX - this.startX;
        const container = document.querySelector('.slideshow-container');
        
        container?.classList.remove('dragging');
        container?.classList.add('transitioning');
        
        // Determine if swipe threshold was met
        if (Math.abs(deltaX) > this.threshold) {
            if (deltaX > 0) {
                this.previousSlide();
            } else {
                this.nextSlide();
            }
            this.onManualInteraction();
        } else {
            // Snap back to current slide
            this.updateSlideVisibility();
        }
        
        setTimeout(() => {
            container?.classList.remove('transitioning');
        }, 300);
    }

    handleClick(e) {
        // Click to advance (if not dragging)
        if (Math.abs(this.currentX - this.startX) < 10) {
            this.nextSlide();
            this.onManualInteraction();
        }
    }

    handleResize() {
        this.monitorIsLandscape = window.innerWidth > window.innerHeight;
        
        // Reprocess slides if needed
        if (this.annunci.length > 0) {
            this.processAnnunciForSlideshow().then(() => {
                this.renderSlideshow();
                this.currentSlideIndex = 0;
                this.updateSlideVisibility();
            });
        }
    }

    onManualInteraction() {
        this.isManualMode = true;
        
        // Clear existing timeout
        if (this.manualInteractionTimeout) {
            clearTimeout(this.manualInteractionTimeout);
        }
        
        // Reset to auto mode after 30 seconds of no interaction
        this.manualInteractionTimeout = setTimeout(() => {
            this.isManualMode = false;
        }, 30000);
    }

    startPolling() {
        // Less frequent polling - only check for data changes every 5 minutes
        this.pollingInterval = setInterval(() => {
            console.log('🔄 Checking for data updates...');
            this.loadAnnunci();
        }, this.config.pollingInterval || 300000); // 5 minutes for data change check
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    pauseLayout() {
        this.stopPolling();
        this.stopSlideshow();
    }

    resumeLayout() {
        this.startPolling();
        this.startSlideshow();
    }

    destroy() {
        this.stopPolling();
        this.stopSlideshow();
        if (this.manualInteractionTimeout) {
            clearTimeout(this.manualInteractionTimeout);
        }
    }
}

// Pass PHP constant to JavaScript
window.MONITOR_CITTA_SHOW_TEXT_FALLBACK = <?php echo defined('MONITOR_CITTA_SHOW_TEXT_FALLBACK') ? (MONITOR_CITTA_SHOW_TEXT_FALLBACK ? 'true' : 'false') : 'true'; ?>;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (window.MonitorData && window.MonitorData.layoutType === 'citta_multi') {
        window.cittaMultiSlideshowLayout = new CittaMultiSlideshowLayout();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.cittaMultiSlideshowLayout) {
        window.cittaMultiSlideshowLayout.destroy();
    }
});
</script>