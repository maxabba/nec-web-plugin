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

<div class="monitor-citta-multi-slideshow">
    <!-- Loading state -->
    <div id="citta-loading" class="loading-state">
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
    <div id="slideshow-container" class="slideshow-container" style="display: none;">
        <!-- Slides populated via AJAX -->
    </div>
</div>

<style>
/* Layout Città Multi-Agenzia Styles */
:root {
    /* Colors */
    --monitor-bg-primary: rgb(55, 55, 55);
    --monitor-text-primary: #ffffff;
    --monitor-text-secondary: rgba(255, 255, 255, 0.85);
    --monitor-text-muted: rgba(255, 255, 255, 0.7);
    --monitor-status-active: #4CAF50;
    
    /* Typography */
    --monitor-font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    
    /* Spacing */
    --monitor-padding-small: 20px;
    --monitor-padding-medium: 40px;
    --monitor-gap-small: 10px;
    --monitor-gap-medium: 20px;
    --monitor-gap-large: 30px;
    
    /* Layout Heights */
    --monitor-header-height: 15vh;
    --monitor-body-height: 75vh;
    --monitor-footer-height: 10vh;
    
    /* Transitions */
    --monitor-transition-fast: 0.3s ease;
    --monitor-shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.5);
}

html, body {
    background: var(--monitor-bg-primary) !important;
}

.monitor-citta-multi {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--monitor-bg-primary);
    color: var(--monitor-text-primary);
    font-family: var(--monitor-font-family);
    overflow: hidden;
}

/* Header Styles */
.citta-multi-header {
    height: var(--monitor-header-height);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--monitor-padding-medium);
    background: var(--monitor-bg-primary);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.city-info {
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-large);
}

.city-icon {
    font-size: 4rem;
    opacity: 0.8;
}

.city-details h1 {
    font-size: 2.5rem;
    font-weight: 300;
    margin: 0;
    color: var(--monitor-text-primary);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.date-range {
    font-size: 1.3rem;
    color: var(--monitor-text-secondary);
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
    color: var(--monitor-status-active);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--monitor-text-muted);
    margin-top: 4px;
}

/* Main Content */
.citta-multi-main {
    height: var(--monitor-body-height);
    position: relative;
    overflow: hidden;
    background: var(--monitor-bg-primary);
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
    border-top: 4px solid var(--monitor-status-active);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: var(--monitor-padding-small);
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
    padding: var(--monitor-padding-small) var(--monitor-padding-medium);
    background: var(--monitor-bg-primary);
    
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
    margin-bottom: var(--monitor-gap-medium);
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-large);
    transition: var(--monitor-transition-fast);
    animation: slideInFromLeft 0.6s ease-out;
}

.annuncio-card:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: scale(1.02);
    box-shadow: var(--monitor-shadow-medium);
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
    box-shadow: var(--monitor-shadow-medium);
    transition: var(--monitor-transition-fast);
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
    color: var(--monitor-text-primary);
}

.annuncio-meta {
    display: flex;
    gap: var(--monitor-gap-medium);
    font-size: 0.95rem;
    color: var(--monitor-text-secondary);
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
    color: var(--monitor-text-muted);
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
    height: var(--monitor-footer-height);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--monitor-padding-medium);
    background: var(--monitor-bg-primary);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-medium);
}

.vendor-logo-citta {
    height: 30px;
    width: auto;
    border-radius: 8px;
    transition: var(--monitor-transition-fast);
}

.vendor-name {
    font-size: 1.1rem;
    font-weight: 400;
}

.system-info {
    font-size: 0.8rem;
    color: var(--monitor-text-muted);
    margin-top: 2px;
}

.update-info {
    text-align: right;
    font-size: 0.85rem;
}

.auto-refresh {
    color: var(--monitor-status-active);
    margin-bottom: 4px;
}

.last-update {
    color: var(--monitor-text-muted);
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
        padding: var(--monitor-padding-small);
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
        height: var(--monitor-header-height);
    }
    
    .citta-multi-main {
        height: var(--monitor-body-height);
    }
    
    .citta-multi-footer {
        height: var(--monitor-footer-height);
    }
    
    .annuncio-card {
        padding: var(--monitor-padding-small);
    }
    
    .annuncio-nome {
        font-size: 1.3rem;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .monitor-citta-multi {
        background: var(--monitor-bg-primary);
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
            console.log('Loading annunci città multi slideshow...');
            
            this.showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'monitor_get_citta_multi');
            formData.append('monitor_id', this.config.monitorId);
            formData.append('vendor_id', this.config.vendorId);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            console.log('Città Multi Slideshow Response:', data);
            
            if (data.success) {
                this.annunci = data.data.annunci || [];
                
                if (this.annunci.length > 0) {
                    await this.processAnnunciForSlideshow();
                    
                    if (this.slides.length > 0) {
                        this.renderSlideshow();
                        this.showSlideshow();
                        this.startSlideshow();
                    } else {
                        console.log('No valid slides created from annunci');
                        this.showNoData();
                    }
                } else {
                    console.log('No annunci received');
                    this.showNoData();
                }
            } else {
                console.error('Error loading annunci:', data.data);
                this.showNoData();
            }
        } catch (error) {
            console.error('Network error loading annunci:', error);
            this.showNoData();
        } finally {
            this.showLoading(false);
        }
    }

    async processAnnunciForSlideshow() {
        this.slides = [];
        const imageOrientations = [];
        
        console.log('Processing annunci for slideshow:', this.annunci);
        console.log('Text fallback enabled:', this.shouldShowTextFallback());
        
        // First pass: determine image orientations
        for (let annuncio of this.annunci) {
            console.log('Processing annuncio:', {
                id: annuncio.id || 'no-id',
                nome: annuncio.nome || 'no-name',
                immagine_annuncio_di_morte: annuncio.immagine_annuncio_di_morte,
                testo_annuncio_di_morte: annuncio.testo_annuncio_di_morte ? 'HAS_TEXT' : 'NO_TEXT'
            });
            
            if (annuncio.immagine_annuncio_di_morte && annuncio.immagine_annuncio_di_morte.url) {
                console.log('Found image:', annuncio.immagine_annuncio_di_morte.url);
                const orientation = await this.getImageOrientation(annuncio.immagine_annuncio_di_morte.url);
                imageOrientations.push({
                    annuncio: annuncio,
                    type: 'image',
                    orientation: orientation,
                    url: annuncio.immagine_annuncio_di_morte.url
                });
            } else if (this.shouldShowTextFallback() && annuncio.testo_annuncio_di_morte) {
                console.log('Using text fallback for:', annuncio.nome);
                imageOrientations.push({
                    annuncio: annuncio,
                    type: 'text',
                    text: annuncio.testo_annuncio_di_morte
                });
            } else {
                console.log('Skipping annuncio (no image, no text):', annuncio.nome);
            }
        }
        
        console.log('Image orientations found:', imageOrientations);
        
        // Second pass: group images for optimal display
        this.slides = this.groupImagesForSlides(imageOrientations);
        
        console.log(`Processed ${this.annunci.length} annunci into ${this.slides.length} slides`);
        console.log('Final slides:', this.slides);
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
        // Don't pair if orientations are different
        if (item1.orientation !== item2.orientation) {
            return false;
        }
        
        // Check monitor vs image orientation compatibility
        if (this.monitorIsLandscape && item1.orientation === 'portrait') {
            return true; // Landscape monitor + vertical images = side by side
        }
        
        if (!this.monitorIsLandscape && item1.orientation === 'landscape') {
            return true; // Vertical monitor + horizontal images = stacked
        }
        
        return false; // Don't pair same orientations
    }

    shouldShowTextFallback() {
        // Check if PHP constant is defined and enabled
        return window.MONITOR_CITTA_SHOW_TEXT_FALLBACK !== false;
    }

    renderSlideshow() {
        const container = document.getElementById('slideshow-container');
        if (!container || this.slides.length === 0) return;
        
        container.innerHTML = `
            <div class="slideshow-track" style="transform: translateX(0%)">
                ${this.slides.map((slide, index) => this.createSlideHTML(slide, index)).join('')}
            </div>
        `;
    }

    createSlideHTML(slide, index) {
        if (slide.type === 'single' && slide.content[0].type === 'text') {
            return `
                <div class="slide text-slide" data-index="${index}">
                    <div class="text-content">${slide.content[0].text}</div>
                </div>
            `;
        }
        
        if (slide.type === 'single') {
            return `
                <div class="slide single-image" data-index="${index}">
                    <img src="${slide.content[0].url}" class="slide-image" alt="Annuncio di morte">
                </div>
            `;
        }
        
        if (slide.type === 'double') {
            const orientationClass = slide.content[0].orientation === 'portrait' ? 'vertical-images' : 'horizontal-images';
            return `
                <div class="slide double-images ${orientationClass}" data-index="${index}">
                    <img src="${slide.content[0].url}" class="slide-image" alt="Annuncio di morte">
                    <img src="${slide.content[1].url}" class="slide-image" alt="Annuncio di morte">
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
        
        const currentSlide = this.slides[this.currentSlideIndex];
        const duration = this.isManualMode ? currentSlide.duration * 2 : currentSlide.duration;
        
        this.slideshowInterval = setTimeout(() => {
            this.nextSlide();
        }, duration);
    }

    nextSlide() {
        if (this.slides.length === 0) return;
        
        this.currentSlideIndex = (this.currentSlideIndex + 1) % this.slides.length;
        this.updateSlidePosition();
        this.scheduleNextSlide();
    }

    previousSlide() {
        if (this.slides.length === 0) return;
        
        this.currentSlideIndex = this.currentSlideIndex === 0 ? this.slides.length - 1 : this.currentSlideIndex - 1;
        this.updateSlidePosition();
        this.onManualInteraction();
    }

    updateSlidePosition() {
        const track = document.querySelector('.slideshow-track');
        if (!track) return;
        
        const translateX = -this.currentSlideIndex * 100;
        track.style.transform = `translateX(${translateX}%)`;
        
        console.log(`Switched to slide ${this.currentSlideIndex + 1}/${this.slides.length}`);
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
        
        const track = document.querySelector('.slideshow-track');
        if (track) {
            const transform = window.getComputedStyle(track).transform;
            const matrix = new DOMMatrix(transform);
            this.initialTransform = matrix.m41;
        }
        
        document.querySelector('.slideshow-container')?.classList.add('dragging');
    }

    handleMove(e) {
        if (!this.isDragging) return;
        e.preventDefault();
        
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        this.currentX = clientX;
        
        const deltaX = this.currentX - this.startX;
        const track = document.querySelector('.slideshow-track');
        
        if (track) {
            const newTransform = this.initialTransform + deltaX;
            track.style.transform = `translateX(${newTransform}px)`;
        }
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
            this.updateSlidePosition();
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
        console.log(`Monitor orientation changed: ${this.monitorIsLandscape ? 'landscape' : 'portrait'}`);
        
        // Reprocess slides if needed
        if (this.annunci.length > 0) {
            this.processAnnunciForSlideshow().then(() => {
                this.renderSlideshow();
                this.currentSlideIndex = 0;
                this.updateSlidePosition();
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
            console.log('Returning to auto-advance mode');
        }, 30000);
        
        console.log('Manual interaction - timing doubled for next slides');
    }

    startPolling() {
        this.pollingInterval = setInterval(() => {
            this.loadAnnunci();
        }, this.config.pollingInterval || 60000); // 60 seconds for slideshow
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