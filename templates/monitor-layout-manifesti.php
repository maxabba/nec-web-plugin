<?php
/**
 * Monitor Layout: Manifesti (Traditional)
 * Template per visualizzare defunto + manifesti con slideshow (layout originale)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get logo image URL from plugin assets for no-manifesti state - no dependency on site media library
$logo_url = plugin_dir_url(__FILE__) . '../assets/images/Necrologi-oro.png';

// Get additional ACF fields for fallback logic
$immagine_annuncio = get_field('immagine_annuncio_di_morte', $associated_post_id);

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

            <!-- No manifesti state - Full layout with defunto info -->
            <div class="no-manifesti" id="no-manifesti" style="display: none;">
                <div class="no-manifesti-content">
                    <!-- Sezione sinistra: Foto defunto e info (visible in landscape) -->
                    <div class="no-manifesti-left">
                        <!-- Defunto Image with cascading fallback: fotografia -> immagine_annuncio_di_morte -> logo -->
                        <?php 
                        // Cascading fallback logic for defunto image
                        $foto_display_url = '';
                        $foto_alt_text = esc_attr($defunto_title);
                        $foto_class = 'defunto-foto-waiting';
                        
                        // 1. First try 'fotografia' ACF field
                        if ($foto_defunto) {
                            $foto_display_url = is_array($foto_defunto) && isset($foto_defunto['url']) 
                                ? $foto_defunto['url'] 
                                : (is_string($foto_defunto) ? $foto_defunto : '');
                        }
                        
                        // 2. Fallback to 'immagine_annuncio_di_morte' ACF field
                        if (empty($foto_display_url) && $immagine_annuncio) {
                            $foto_display_url = is_array($immagine_annuncio) && isset($immagine_annuncio['url']) 
                                ? $immagine_annuncio['url'] 
                                : (is_string($immagine_annuncio) ? $immagine_annuncio : '');
                        }
                        
                        // 3. Final fallback to main logo
                        if (empty($foto_display_url)) {
                            $foto_display_url = $logo_url;
                            $foto_alt_text = 'Logo';
                            $foto_class = 'defunto-foto-waiting logo-fallback'; // Different class for logo fallback
                        }
                        ?>
                        
                        <?php if ($foto_display_url): ?>
                            <img src="<?php echo esc_url($foto_display_url); ?>" 
                                 alt="<?php echo $foto_alt_text; ?>" 
                                 class="<?php echo $foto_class; ?>">
                        <?php else: ?>
                            <div class="defunto-foto-waiting-placeholder">
                                <i class="dashicons dashicons-admin-users"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Defunto Name and Date -->
                        <div class="defunto-info-waiting">
                            <h1><?php echo esc_html($defunto_title); ?></h1>
                            <p><?php echo esc_html($display_date); ?></p>
                        </div>
                    </div>
                    
                    <!-- Sezione destra: Logo e messaggio (center in landscape, below in portrait) -->
                    <div class="no-manifesti-right">
                        <!-- Main Logo above the message -->
                        <img src="<?php echo esc_url($logo_url); ?>" 
                             alt="Logo" 
                             class="logo-image-waiting">
                        
                        <!-- Waiting Message -->
                        <div class="waiting-message-manifesti">
                            <h3>Le partecipazioni saranno presto disponibili</h3>
                        </div>
                    </div>
                </div>
                
                <!-- Footer info in no-manifesti state -->
                <div class="no-manifesti-footer">
                    <?php if ($shop_banner): ?>
                        <img src="<?php echo esc_url($shop_banner); ?>" 
                             alt="<?php echo esc_attr($shop_name); ?>" 
                             class="shop-logo-waiting">
                    <?php endif; ?>
                    <div class="shop-name-waiting"><?php echo esc_html($shop_name); ?></div>
                </div>
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
/* CSS Variables for Design Standards Compliance */
:root {
    /* Colors */
    --monitor-bg-primary: rgb(55, 55, 55);
    --monitor-text-primary: #ffffff;
    --monitor-text-secondary: rgba(255, 255, 255, 0.85);
    --monitor-text-muted: rgba(255, 255, 255, 0.7);
    --monitor-status-active: #4CAF50;
    
    /* Typography */
    --monitor-font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    --monitor-font-manifesti: "PlayFair Display Mine", serif;
    
    /* Spacing */
    --monitor-padding-small: 20px;
    --monitor-padding-medium: 40px;
    --monitor-gap-small: 10px;
    --monitor-gap-medium: 20px;
    --monitor-gap-large: 30px;
    
    /* Layout Heights */
    --monitor-header-height: 20vh;
    --monitor-body-height: 75vh;
    --monitor-footer-height: 5vh;
    
    /* Transitions */
    --monitor-transition-fast: 0.3s ease;
    --monitor-transition-slow: 0.5s ease;
    
    /* Shadows */
    --monitor-shadow-light: 0 2px 4px rgba(0, 0, 0, 0.3);
    --monitor-shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.5);
}

/* Traditional Monitor Layout Styles */
.monitor-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
    background: var(--monitor-bg-primary);
    color: var(--monitor-text-primary);
    font-family: var(--monitor-font-family);
}

/* Layout Orizzontale - Restructure completo */
@media (orientation: landscape) {
    .monitor-container {
        flex-direction: column; /* Header/Body/Footer rimangono verticali */
    }
    
    /* Header compatto con foto e info affiancate */
    .monitor-header {
        height: auto;
        padding: var(--monitor-padding-small) var(--monitor-padding-medium);
    }
    
    .defunto-info {
        justify-content: flex-start;
        width: 100%;
        gap: var(--monitor-gap-large);
    }
    
    /* Body occupa tutto lo spazio disponibile per manifesti */
    .monitor-body {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .manifesti-slideshow {
        width: 100%;
        height: 100%;
    }
}

/* Header Section - 20% */
.monitor-header {
    height: var(--monitor-header-height);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--monitor-bg-primary);
    padding: var(--monitor-padding-small);
    position: relative;
}

.defunto-info {
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-large);
    max-width: 90%;
}

.defunto-foto {
    object-fit: contain;
    box-shadow: var(--monitor-shadow-medium);
    transition: var(--monitor-transition-fast);
}

/* Orientamento Portrait - 25% altezza schermo */
@media (orientation: portrait) {
    .defunto-foto {
        height: 25vh;
        width: auto;
        max-width: 90%;
    }
}

/* Orientamento Landscape - 30% larghezza schermo */
@media (orientation: landscape) {
    .defunto-foto {
        width: 30vw;
        height: auto;
        max-height: 350px;
    }
}

.defunto-foto:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
}

.defunto-foto-placeholder {
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: var(--monitor-text-secondary);
    box-shadow: var(--monitor-shadow-medium);
    transition: var(--monitor-transition-fast);
}

/* Orientamento Portrait - 25% altezza schermo */
@media (orientation: portrait) {
    .defunto-foto-placeholder {
        height: 25vh;
        width: auto;
        max-width: 90%;
    }
}

/* Orientamento Landscape - 30% larghezza schermo */
@media (orientation: landscape) {
    .defunto-foto-placeholder {
        width: 30vw;
        height: auto;
        max-height: 350px;
    }
}

.defunto-foto-placeholder:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
}

.defunto-details h1 {
    font-size: 2.5rem;
    font-weight: 300;
    margin-bottom: var(--monitor-gap-small);
    text-shadow: var(--monitor-shadow-light);
    color: var(--monitor-text-primary);
}

.defunto-details p {
    font-size: 1.3rem;
    color: var(--monitor-text-secondary);
    font-weight: 300;
}

/* Body Section - 75% */
.monitor-body {
    height: var(--monitor-body-height);
    position: relative;
    overflow: hidden;
    background: var(--monitor-bg-primary);
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
    padding: var(--monitor-padding-small);
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
    font-family: var(--monitor-font-manifesti);
    font-weight: 400;
    /* Make content unselectable for touch displays */
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    -webkit-touch-callout: none;
}

/* Footer Section - 5% */
.monitor-footer {
    height: var(--monitor-footer-height);
    background: var(--monitor-bg-primary);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--monitor-padding-medium);
}

.shop-info {
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-medium);
}

.shop-logo {
    height: 30px;
    width: auto;
    border-radius: 8px;
    transition: var(--monitor-transition-fast);
}

.shop-logo:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
}

.shop-name {
    font-size: 0.9rem;
    font-weight: 300;
    color: var(--monitor-text-muted);
}

.monitor-status {
    font-size: 0.9rem;
    color: var(--monitor-text-muted);
}

/* Loading State */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    backdrop-filter: blur(10px);
}

.loading-overlay.hidden {
    display: none;
}

.loading-overlay p {
    color: var(--monitor-text-primary);
    font-size: 1.1rem;
    margin: 0;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top: 4px solid var(--monitor-status-active);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: var(--monitor-gap-medium);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* No Manifesti State - Full Layout with Defunto Info */
.no-manifesti {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--monitor-bg-primary);
    color: var(--monitor-text-primary);
    text-align: center;
    padding: var(--monitor-padding-medium);
    position: relative;
}

.no-manifesti-content {
    max-width: 800px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
}

/* Layout Orizzontale No-Manifesti: Immagine a sinistra, contenuto a destra */
@media (orientation: landscape) {
    .no-manifesti {
        flex-direction: row;
        align-items: center;
        text-align: left;
        padding: var(--monitor-padding-medium) var(--monitor-padding-medium) 0;
    }
    
    .no-manifesti-content {
        max-width: none;
        flex-direction: row;
        align-items: center;
        width: 100%;
        gap: var(--monitor-gap-large);
    }
    
    .no-manifesti-left {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
        gap: var(--monitor-gap-medium);
        text-align: center; /* Centra tutti i testi nella sezione sinistra */
    }
    
    .no-manifesti-right {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1;
        text-align: center;
        gap: var(--monitor-gap-large);
    }
}

/* Layout Portrait: Disposizione verticale tradizionale */
@media (orientation: portrait) {
    .no-manifesti-content {
        flex-direction: column;
        text-align: center; /* Mantiene il centro per il layout verticale */
    }
    
    .no-manifesti-left, .no-manifesti-right {
        display: contents; /* Gli elementi appaiono come se fossero parte del parent */
    }
}

/* Defunto Photo with responsive sizing like logo-image */
.defunto-foto-waiting {
    object-fit: contain;
    opacity: 0.9;
    margin-bottom: var(--monitor-gap-medium);
    border-radius: 8px;
}

/* Orientamento Portrait - 25% altezza schermo (coerente con header) */
@media (orientation: portrait) {
    .defunto-foto-waiting {
        height: 25vh;
        width: auto;
        max-width: 90%;
    }
}

/* Orientamento Landscape - 30% larghezza schermo (coerente con header) */
@media (orientation: landscape) {
    .defunto-foto-waiting {
        width: 30vw;
        height: auto;
        max-height: 350px;
    }
}

.defunto-foto-waiting-placeholder {
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    color: var(--monitor-text-secondary);
    border-radius: 50%;
    margin-bottom: var(--monitor-gap-medium);
    box-shadow: var(--monitor-shadow-medium);
}

/* Orientamento Portrait - 25% altezza schermo */
@media (orientation: portrait) {
    .defunto-foto-waiting-placeholder {
        height: 25vh;
        width: auto;
        max-width: 90%;
        aspect-ratio: 1;
    }
}

/* Orientamento Landscape - 30% larghezza schermo */
@media (orientation: landscape) {
    .defunto-foto-waiting-placeholder {
        width: 30vw;
        height: auto;
        max-height: 350px;
        aspect-ratio: 1;
    }
}

/* Logo above message - smaller size */
.logo-image-waiting {
    object-fit: contain;
    opacity: 0.8;
    margin: var(--monitor-gap-large) 0 var(--monitor-gap-medium) 0;
    max-height: 80px;
    width: auto;
}

.defunto-info-waiting h1 {
    font-size: 2.5rem;
    font-weight: 300;
    margin-bottom: var(--monitor-gap-small);
    text-shadow: var(--monitor-shadow-light);
    color: var(--monitor-text-primary);
}

.defunto-info-waiting p {
    font-size: 1.3rem;
    color: var(--monitor-text-secondary);
    margin-bottom: var(--monitor-gap-large);
}

.waiting-message-manifesti h3 {
    font-size: 1.5rem;
    font-weight: 300;
    color: var(--monitor-text-primary);
    margin: var(--monitor-gap-large) 0;
}

/* Footer for no-manifesti state */
.no-manifesti-footer {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-medium);
    opacity: 0.8;
}

.shop-logo-waiting {
    height: 30px;
    width: auto;
    border-radius: 4px;
}

.shop-name-waiting {
    font-size: 0.9rem;
    color: var(--monitor-text-muted);
    font-weight: 300;
}

/* Responsive adjustments for no-manifesti state */
@media (max-width: 768px) {
    .defunto-info-waiting h1 {
        font-size: 1.8rem;
    }
    
    .defunto-info-waiting p {
        font-size: 1.1rem;
    }
    
    .waiting-message-manifesti h3 {
        font-size: 1.3rem;
    }
    
    .defunto-foto-waiting-placeholder {
        font-size: 3rem;
    }
    
    .logo-image-waiting {
        max-height: 60px;
    }
}

/* Logo fallback styling - smaller and more subtle when logo is used as defunto photo fallback */
.defunto-foto-waiting.logo-fallback {
    opacity: 0.7;
    filter: grayscale(20%);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    padding: 10px;
}

@media (max-width: 480px) {
    
    .defunto-info-waiting h1 {
        font-size: 1.6rem;
    }
    
    .waiting-message-manifesti h3 {
        font-size: 1.2rem;
    }
    
    .logo-image-waiting {
        max-height: 50px;
    }
    
    .defunto-foto-waiting.logo-fallback {
        width: 60vw;
        max-height: 20vh;
        padding: 8px;
    }
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
    .monitor-header {
        height: 15vh;
    }
    
    .monitor-body {
        height: 75vh;
    }
    
    .monitor-footer {
        height: 10vh;
    }
    
    .defunto-info {
        flex-direction: column;
        text-align: center;
        gap: var(--monitor-gap-small);
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

/* Accessibility Standards */
/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .manifesto-content {
        border: 3px solid var(--monitor-text-primary);
        background: var(--monitor-bg-primary);
    }
    
    .defunto-foto, .defunto-foto-placeholder {
        border: 3px solid var(--monitor-text-primary);
    }
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    .manifesto-slide {
        transition: opacity 0.3s ease;
    }
    
    .defunto-foto, .defunto-foto-placeholder, .shop-logo, 
    .defunto-foto-waiting {
        transition: none;
    }
    
    .defunto-foto:hover, .defunto-foto-placeholder:hover, .shop-logo:hover {
        transform: none;
    }
    
    @keyframes spin {
        0%, 100% { transform: rotate(0deg); opacity: 1; }
        50% { transform: rotate(180deg); opacity: 0.7; }
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .manifesto-content {
        background: var(--monitor-bg-primary);
        color: #e0e0e0;
    }
}
</style>