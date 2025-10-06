<?php
/**
 * Monitor Layout: Solo Annuncio di Morte
 * Template per visualizzare solo l'annuncio di morte senza manifesti
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get monitor data passed from monitor-display.php
$monitor_data = $GLOBALS['monitor_data'];
$defunto_data = $monitor_data['defunto_data'];
$vendor_data = $monitor_data['vendor_data'];

?>

<div class="monitor-solo-annuncio">
    <!-- Header with defunto basic info -->
    <header class="solo-annuncio-header">
        <div class="defunto-header-info">
            <?php if (!empty($defunto_data['fotografia'])): ?>
                <?php 
                $foto_url = is_array($defunto_data['fotografia']) && isset($defunto_data['fotografia']['url']) 
                    ? $defunto_data['fotografia']['url'] 
                    : (is_string($defunto_data['fotografia']) ? $defunto_data['fotografia'] : '');
                ?>
                <?php if ($foto_url): ?>
                    <div class="defunto-foto-container">
                        <img src="<?php echo esc_url($foto_url); ?>" 
                             alt="<?php echo esc_attr($defunto_data['nome'] . ' ' . $defunto_data['cognome']); ?>" 
                             class="defunto-foto-solo">
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <!--
            <div class="defunto-nome-principale">
                <h1><?php echo esc_html(($defunto_data['nome'] ?? '') . ' ' . ($defunto_data['cognome'] ?? '')); ?></h1>
                <?php if (!empty($defunto_data['eta'])): ?>
                    <div class="defunto-eta">Anni <?php echo esc_html($defunto_data['eta']); ?></div>
                <?php endif; ?>
                <?php if (!empty($defunto_data['data_di_morte'])): ?>
                    <div class="defunto-data-morte">
                        <?php echo date('d/m/Y', strtotime($defunto_data['data_di_morte'])); ?>
                    </div>
                <?php endif; ?>
            </div> -->
        </div>
    </header>

    <!-- Main content area -->
    <main class="solo-annuncio-main">
        
        <!-- Primary annuncio image if available -->
        <?php if (!empty($defunto_data['immagine_annuncio_di_morte'])): ?>
            <?php 
            $annuncio_img_url = is_array($defunto_data['immagine_annuncio_di_morte']) && isset($defunto_data['immagine_annuncio_di_morte']['url']) 
                ? $defunto_data['immagine_annuncio_di_morte']['url'] 
                : (is_string($defunto_data['immagine_annuncio_di_morte']) ? $defunto_data['immagine_annuncio_di_morte'] : '');
            ?>
            <?php if ($annuncio_img_url): ?>
                <div class="annuncio-immagine-container">
                    <img src="<?php echo esc_url($annuncio_img_url); ?>" 
                         alt="Annuncio di morte" 
                         class="annuncio-immagine">
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Defunto info text -->
        <?php if (!empty($defunto_data['info'])): ?>
            <div class="defunto-info-text">
                <?php echo wp_kses_post(wpautop($defunto_data['info'])); ?>
            </div>
        <?php endif; ?>



    </main>

    <!-- Footer with vendor info -->
    <!--
    <footer class="solo-annuncio-footer">
        <div class="vendor-info">
            <?php if (!empty($vendor_data['banner'])): ?>
                <img src="<?php echo esc_url($vendor_data['banner']); ?>"
                     alt="<?php echo esc_attr($vendor_data['shop_name']); ?>"
                     class="vendor-logo-solo">
            <?php endif; ?>
        </div>
    </footer>
    -->
</div>

<style>
/* CSS Variables per Consistenza */
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
    --monitor-header-height: 10vh; /* Solo annuncio - header ridotto */
    --monitor-body-height: 75vh;
    --monitor-footer-height: 10vh;
    
    /* Transitions */
    --monitor-transition-fast: 0.3s ease;
    --monitor-transition-slow: 0.5s ease;
    
    /* Shadows */
    --monitor-shadow-light: 0 2px 4px rgba(0, 0, 0, 0.3);
    --monitor-shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.5);
}

/* Layout Solo Annuncio Styles */
.monitor-solo-annuncio {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--monitor-bg-primary) !important;
    color: var(--monitor-text-primary);
    font-family: var(--monitor-font-family);
    overflow: hidden;
}

/* Header Styles - Base (Portrait) */
.solo-annuncio-header {
    height: var(--monitor-header-height);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: var(--monitor-padding-small);
    padding-top: 10px;
    background: var(--monitor-bg-primary) !important;
    position: relative;
    overflow: visible;
}

/* Layout Orizzontale - Due colonne: Header a sinistra, Main a destra */
@media (orientation: landscape) {
    .monitor-solo-annuncio {
        flex-direction: row;
    }

    /* Header diventa una colonna laterale a sinistra */
    .solo-annuncio-header {
        width: 30%;
        height: 90vh;
        flex-direction: column;
        justify-content: center;
        padding: var(--monitor-padding-medium);
        flex-shrink: 0;
    }

    .defunto-header-info {
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 100%;
        gap: var(--monitor-gap-large);
        text-align: center;
        max-height: 85vh;
    }

    /* Main occupa lo spazio rimanente a destra */
    .solo-annuncio-main {
        width: 70%;
        height: 100vh !important;
        flex: 1;
    }

    /* Footer va in basso dell'header */
    .solo-annuncio-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 30%;
        height: 10vh;
        padding: var(--monitor-padding-small) var(--monitor-padding-medium);
        background: var(--monitor-bg-primary) !important;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.defunto-header-info {
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-large);
    text-align: center;
    max-width: 90%;
}

.defunto-foto-container {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.defunto-foto-solo {
    object-fit: contain;
    transition: var(--monitor-transition-fast);
    border-radius: 8px;
}

/* Orientamento Portrait - 20vh per adattarsi all'header */
@media (orientation: portrait) {
    .defunto-foto-solo {
        height: 20vh;
        width: auto;
        max-width: 85%;
    }
}

/* Orientamento Landscape - usa vh per adattamento automatico */
@media (orientation: landscape) {
    .defunto-foto-solo {
        max-height: 70vh; /* 70vh per lasciare spazio al footer */
        width: auto;
        max-width: 90%; /* Massima larghezza 90% del sidebar */
    }

    .defunto-foto-container {
        max-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.defunto-nome-principale h1 {
    font-size: 2.5rem;
    font-weight: 300;
    margin: 0 0 var(--monitor-gap-small) 0;
    text-shadow: var(--monitor-shadow-light);
    color: var(--monitor-text-primary);
}

.defunto-eta {
    font-size: 1.3rem;
    color: var(--monitor-text-secondary);
    margin-bottom: 8px;
    font-weight: 300;
}

.defunto-data-morte {
    font-size: 1.3rem;
    font-weight: 400;
    color: var(--monitor-text-secondary);
}

/* Main Content */
.solo-annuncio-main {
    height: var(--monitor-body-height);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--monitor-padding-medium);
    text-align: center;
    background: var(--monitor-bg-primary) !important;
}

.annuncio-immagine-container {
    margin: 0 auto var(--monitor-gap-large);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Dimensioni responsive per orientamento */
@media (orientation: portrait) {
    .annuncio-immagine-container {
        width: 85%; /* 85% della larghezza in verticale */
        max-height: 85vh;
    }
}

@media (orientation: landscape) {
    .annuncio-immagine-container {
        max-width: 80%;
        height: 85vh; /* 60% dell'altezza in orizzontale */
    }
}

.annuncio-immagine {
    width: 100%;
    height: 100%;
    display: block;
    border-radius: 8px;
    object-fit: contain;
    opacity: 0.9;
}

.defunto-info-text {
    max-width: 800px;
    font-size: 1.3rem;
    line-height: 1.8;
    margin: var(--monitor-gap-medium) 0;
    background: var(--monitor-bg-primary);
    padding: var(--monitor-gap-large);
    border-radius: 8px;
    color: var(--monitor-text-secondary);
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    -webkit-touch-callout: none;
}

.defunto-info-text p {
    margin-bottom: 1em;
}

.defunto-info-text p:last-child {
    margin-bottom: 0;
}

/* Memorial Elements */
.memorial-separator {
    margin: var(--monitor-padding-medium) 0;
    text-align: center;
}

.separator-ornament {
    font-size: 3rem;
    color: var(--monitor-text-muted);
    text-shadow: var(--monitor-shadow-light);
}

.memorial-message {
    max-width: 600px;
    margin: 0 auto;
}

.memorial-quote {
    font-style: italic;
    font-size: 1.2rem;
    line-height: 1.6;
    color: var(--monitor-text-secondary);
    background: var(--monitor-bg-primary);
    padding: 25px;
    border-radius: 8px;
    border-left: 4px solid var(--monitor-text-muted);
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    -webkit-touch-callout: none;
}

/* Footer */
.solo-annuncio-footer {
    height: var(--monitor-footer-height);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 var(--monitor-padding-medium);
    background: var(--monitor-bg-primary) !important;
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: var(--monitor-gap-medium);
}

.vendor-logo-solo {
    height: 10vh; /* Altezza uguale al footer */
    width: auto;
    border-radius: 8px;
    transition: var(--monitor-transition-fast);
}

.vendor-logo-solo:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
}

.vendor-name {
    font-size: 0.9rem;
    font-weight: 400;
    color: var(--monitor-text-muted);
}

.update-time {
    font-size: 0.9rem;
    color: var(--monitor-text-muted);
}

/* Responsive Design - Standard Breakpoints */
/* Desktop Standard */
@media (max-width: 1366px) {
    .defunto-nome-principale h1 {
        font-size: 1.9rem;
    }
}

/* Desktop Large */
@media (min-width: 1920px) and (max-width: 3839px) {
    .defunto-nome-principale h1 {
        font-size: 2.2rem;
    }
}

/* Tablet / Small Desktop */
@media (max-width: 768px) {
    .defunto-header-info {
        flex-direction: column;
        gap: var(--monitor-gap-medium);
    }
    
    .defunto-nome-principale h1 {
        font-size: 1.7rem;
    }
    
    .defunto-info-text {
        font-size: 1.1rem;
        padding: var(--monitor-padding-small);
    }
    
    .solo-annuncio-main {
        padding: var(--monitor-padding-small);
    }
    
    .solo-annuncio-footer {
        padding: 0 var(--monitor-padding-small);
        flex-direction: column;
        gap: var(--monitor-gap-small);
        text-align: center;
    }
}

/* Mobile */
@media (max-width: 480px) {
    .defunto-nome-principale h1 {
        font-size: 1.5rem;
    }
}

/* 4K Ultra HD */
@media (min-width: 3840px) {
    .defunto-nome-principale h1 {
        font-size: 4rem;
    }
}

/* Orientamento Portrait (Totem Mode) */
@media (orientation: portrait) {
    .solo-annuncio-header {
        height: 20vh; /* 20vh per header (+5vh) */
        align-items: flex-start;
        padding-top: 10px;
    }

    .solo-annuncio-main {
        height: 80vh; /* 80vh per main (+5vh) - totale: 20 + 80 = 100vh */
    }

    .solo-annuncio-footer {
        height: 0vh; /* Footer commentato */
    }

    .defunto-header-info {
        flex-direction: column;
        gap: var(--monitor-gap-small);
        align-items: center;
    }
}

/* Animazioni Standard */
@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--monitor-status-active);
    animation: blink 1.5s infinite;
}

/* Transizioni hover uniformi */


/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    .defunto-foto-solo:hover,
    .vendor-logo-solo:hover {
        transform: none;
    }
    
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
}

/* Accessibilità Standard */
/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .defunto-info-text,
    .memorial-quote {
        border: 3px solid #fff;
        background: var(--monitor-bg-primary) !important;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .defunto-info-text,
    .memorial-quote {
        background: var(--monitor-bg-primary) !important;
        color: #e0e0e0;
    }
}
</style>

<script>
// Real-time update handling for solo annuncio layout
document.addEventListener('DOMContentLoaded', function() {
    if (window.MonitorData && window.MonitorData.layoutType === 'solo_annuncio') {
        // Set up polling for defunto data updates
        setInterval(function() {
            checkForDefuntoUpdates();
        }, window.MonitorData.pollingInterval || 15000);
    }
});

function checkForDefuntoUpdates() {
    fetch(window.MonitorData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'monitor_get_solo_annuncio',
            monitor_id: window.MonitorData.monitorId,
            vendor_id: window.MonitorData.vendorId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update last update time
            document.getElementById('last-update').textContent = data.data.last_update;
            
            // Here you could add logic to update content if defunto data changes
            // For now, we'll just refresh the page if there are significant changes
        }
    })
    .catch(error => {
        console.error('Error checking for updates:', error);
    });
}
</script>