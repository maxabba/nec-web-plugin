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
            </div>
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

        <!-- Elegant separator -->
        <div class="memorial-separator">
            <div class="separator-ornament">✝</div>
        </div>

        <!-- Memorial message -->
        <div class="memorial-message">
            <div class="memorial-quote">
                <p><em>"La morte non è lo spegnimento della luce, ma lo spegnimento della lampada perché è arrivata l'alba."</em></p>
            </div>
        </div>

    </main>

    <!-- Footer with vendor info -->
    <footer class="solo-annuncio-footer">
        <div class="vendor-info">
            <?php if (!empty($vendor_data['banner'])): ?>
                <img src="<?php echo esc_url($vendor_data['banner']); ?>" 
                     alt="<?php echo esc_attr($vendor_data['shop_name']); ?>" 
                     class="vendor-logo-solo">
            <?php endif; ?>
            <div class="vendor-name"><?php echo esc_html($vendor_data['shop_name']); ?></div>
        </div>
        <div class="update-time">
            <small>Ultimo aggiornamento: <span id="last-update"><?php echo current_time('H:i'); ?></span></small>
        </div>
    </footer>
</div>

<style>
/* Layout Solo Annuncio Styles */
.monitor-solo-annuncio {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Header Styles */
.solo-annuncio-header {
    height: 25vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.defunto-header-info {
    display: flex;
    align-items: center;
    gap: 40px;
    text-align: center;
}

.defunto-foto-container {
    flex-shrink: 0;
}

.defunto-foto-solo {
    width: 200px;
    height: 200px;
    object-fit: cover;
    border-radius: 50%;
    border: 6px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.defunto-nome-principale h1 {
    font-size: 3.5rem;
    font-weight: 300;
    margin: 0 0 10px 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 1px;
}

.defunto-eta {
    font-size: 1.4rem;
    opacity: 0.9;
    margin-bottom: 8px;
    font-weight: 300;
}

.defunto-data-morte {
    font-size: 1.6rem;
    font-weight: 400;
    color: #f0f0f0;
}

/* Main Content */
.solo-annuncio-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    text-align: center;
}

.annuncio-immagine-container {
    max-width: 80%;
    max-height: 50vh;
    margin: 0 auto 30px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.4);
}

.annuncio-immagine {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 12px;
}

.defunto-info-text {
    max-width: 800px;
    font-size: 1.3rem;
    line-height: 1.8;
    margin: 20px 0;
    background: rgba(255, 255, 255, 0.1);
    padding: 30px;
    border-radius: 12px;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.defunto-info-text p {
    margin-bottom: 1em;
}

.defunto-info-text p:last-child {
    margin-bottom: 0;
}

/* Memorial Elements */
.memorial-separator {
    margin: 40px 0;
    text-align: center;
}

.separator-ornament {
    font-size: 3rem;
    opacity: 0.7;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.memorial-message {
    max-width: 600px;
    margin: 0 auto;
}

.memorial-quote {
    font-style: italic;
    font-size: 1.2rem;
    line-height: 1.6;
    opacity: 0.9;
    background: rgba(255, 255, 255, 0.05);
    padding: 25px;
    border-radius: 8px;
    border-left: 4px solid rgba(255, 255, 255, 0.3);
}

/* Footer */
.solo-annuncio-footer {
    height: 10vh;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10px);
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.vendor-logo-solo {
    height: 50px;
    width: auto;
    border-radius: 4px;
}

.vendor-name {
    font-size: 1.1rem;
    font-weight: 400;
}

.update-time {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .defunto-nome-principale h1 {
        font-size: 2.8rem;
    }
    
    .defunto-foto-solo {
        width: 150px;
        height: 150px;
    }
}

@media (max-width: 768px) {
    .defunto-header-info {
        flex-direction: column;
        gap: 20px;
    }
    
    .defunto-nome-principale h1 {
        font-size: 2.2rem;
    }
    
    .defunto-foto-solo {
        width: 120px;
        height: 120px;
    }
    
    .defunto-info-text {
        font-size: 1.1rem;
        padding: 20px;
    }
    
    .solo-annuncio-main {
        padding: 20px;
    }
    
    .solo-annuncio-footer {
        padding: 0 20px;
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
}

/* Portrait Orientation (Totem Mode) */
@media (orientation: portrait) {
    .solo-annuncio-header {
        height: 30vh;
    }
    
    .defunto-header-info {
        flex-direction: column;
        gap: 25px;
    }
    
    .defunto-nome-principale h1 {
        font-size: 2.5rem;
    }
    
    .annuncio-immagine-container {
        max-height: 40vh;
    }
}

/* Animation Effects */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.defunto-header-info,
.defunto-info-text,
.memorial-message {
    animation: fadeInUp 1s ease-out;
}

.defunto-info-text {
    animation-delay: 0.3s;
}

.memorial-message {
    animation-delay: 0.6s;
}

/* High contrast mode for better visibility */
@media (prefers-contrast: high) {
    .monitor-solo-annuncio {
        background: #000;
    }
    
    .defunto-info-text,
    .memorial-quote {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.5);
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