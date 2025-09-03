<?php
/**
 * Monitor Layout: Manifesti (Traditional)
 * Template per visualizzare defunto + manifesti con slideshow (layout originale)
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="monitor-container">
    <!-- Header Section -->
    <header class="monitor-header">
        <div class="defunto-info">
            <?php if ($foto_defunto): ?>
                <?php 
                // Handle both array and string format for ACF image field
                $foto_url = is_array($foto_defunto) && isset($foto_defunto['url']) 
                    ? $foto_defunto['url'] 
                    : (is_string($foto_defunto) ? $foto_defunto : '');
                ?>
                <?php if ($foto_url): ?>
                    <img src="<?php echo esc_url($foto_url); ?>" 
                         alt="<?php echo esc_attr($defunto_title); ?>" 
                         class="defunto-foto">
                <?php endif; ?>
            <?php else: ?>
                <div class="defunto-foto-placeholder">
                    <i class="dashicons dashicons-admin-users"></i>
                </div>
            <?php endif; ?>
            
            <div class="defunto-details">
                <h1><?php echo esc_html($defunto_title); ?></h1>
                <p><?php echo esc_html($display_date); ?></p>
            </div>
        </div>
    </header>

    <!-- Body Section -->
    <main class="monitor-body">
        <div class="manifesti-slideshow" id="manifesti-slideshow">
            <!-- Loading Overlay -->
            <div class="loading-overlay" id="loading-overlay">
                <div class="spinner"></div>
                <p>Caricamento manifesti...</p>
            </div>

            <!-- Manifesti slides will be loaded here -->
            <div id="manifesti-container"></div>

            <!-- No manifesti state -->
            <div class="no-manifesti" id="no-manifesti" style="display: none;">
                <div class="no-manifesti-icon">📝</div>
                <h3>Nessun manifesto disponibile</h3>
                <p>I manifesti verranno visualizzati non appena saranno pubblicati</p>
            </div>

            <!-- Slideshow Controls (Hidden by default) -->
            <button class="slideshow-controls prev-btn" id="prev-btn" style="display: none;">
                <span>‹</span>
            </button>
            <button class="slideshow-controls next-btn" id="next-btn" style="display: none;">
                <span>›</span>
            </button>

            <!-- Slide Indicators (Hidden by default) -->
            <div class="slide-indicators" id="slide-indicators" style="display: none;"></div>
        </div>
    </main>

    <!-- Footer Section -->
    <footer class="monitor-footer">
        <div class="shop-info">
            <?php if ($shop_banner): ?>
                <img src="<?php echo esc_url($shop_banner); ?>" 
                     alt="<?php echo esc_attr($shop_name); ?>" 
                     class="shop-logo">
            <?php endif; ?>
            <div class="shop-name"><?php echo esc_html($shop_name); ?></div>
        </div>
        <div class="monitor-status">
            <small>Ultimo aggiornamento: <span id="last-update"><?php echo current_time('H:i'); ?></span></small>
        </div>
    </footer>
</div>

<style>
/* Traditional Monitor Layout Styles */
.monitor-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
    background: rgb(55, 55, 55);
}

/* Header Section - 20% */
.monitor-header {
    height: 20vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgb(55, 55, 55);
    padding: 20px;
    position: relative;
}

.defunto-info {
    display: flex;
    align-items: center;
    gap: 30px;
    max-width: 90%;
}

.defunto-foto {
    height: 350px;
    width: auto;
    max-width: 350px;
    object-fit: contain;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
}

.defunto-foto-placeholder {
    width: 350px;
    height: 350px;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: rgba(255, 255, 255, 0.5);
}

.defunto-details h1 {
    font-size: 2.5rem;
    font-weight: 300;
    margin-bottom: 10px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.defunto-details p {
    font-size: 1.2rem;
    opacity: 0.9;
    font-weight: 300;
}

/* Body Section - 75% */
.monitor-body {
    height: 75vh;
    position: relative;
    overflow: hidden;
    background: rgb(55, 55, 55);
}

.manifesti-slideshow {
    height: 100%;
    position: relative;
    overflow: hidden;
}

.manifesto-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1s ease-in-out;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.manifesto-slide.active {
    opacity: 1;
    z-index: 1;
}

.manifesto-content {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

/* Manifesto rendering from manifesto.css */
.manifesto-wrapper {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.text-editor-background {
    position: relative;
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
    overflow: hidden;
    margin: auto;
}

.custom-text-editor {
    width: 100%;
    height: 100%;
    border: none;
    background: transparent;
    color: #000;
    resize: none;
    box-sizing: border-box;
    outline: none;
    overflow: hidden;
    line-height: 1.6;
    position: absolute;
    top: 0;
    left: 0;
}

/* Footer Section - 5% */
.monitor-footer {
    height: 5vh;
    background: rgb(55, 55, 55);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
}

.shop-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.shop-logo {
    height: 30px;
    width: auto;
}

.shop-name {
    font-size: 1rem;
    font-weight: 300;
}

.monitor-status {
    font-size: 0.8rem;
    opacity: 0.7;
}

/* Loading State */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.loading-overlay.hidden {
    display: none;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top: 4px solid #fff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* No Manifesti State */
.no-manifesti {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.6);
    text-align: center;
    padding: 40px;
}

.no-manifesti-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-manifesti h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    font-weight: 300;
}

.no-manifesti p {
    font-size: 1rem;
    opacity: 0.8;
}

/* Responsive Design */
@media (max-width: 1366px) {
    .defunto-details h1 {
        font-size: 2rem;
    }
    
    .defunto-foto, .defunto-foto-placeholder {
        width: auto;
        height: 280px;
        max-width: 280px;
    }
    
    .manifesto-content {
        font-size: 1.5rem;
        padding: 40px;
    }
}

@media (max-height: 800px) {
    .defunto-details h1 {
        font-size: 1.8rem;
    }
    
    .manifesto-content {
        font-size: 1.4rem;
        padding: 30px;
    }
}

/* Portrait Orientation (Totem) */
@media (orientation: portrait) {
    .defunto-info {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .defunto-foto, .defunto-foto-placeholder {
        width: auto;
        height: 250px;
        max-width: 250px;
    }
    
    .defunto-details h1 {
        font-size: 2rem;
    }
    
    .manifesto-content {
        font-size: 1.6rem;
    }
}
</style>