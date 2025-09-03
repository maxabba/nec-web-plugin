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

<div class="monitor-citta-multi">
    <!-- Header with city info -->
    <header class="citta-multi-header">
        <div class="city-info">
            <div class="city-icon">🏛️</div>
            <div class="city-details">
                <h1>Necrologi - <span id="city-name">Caricamento...</span></h1>
                <div class="date-range">
                    <span id="date-info">Ultimi giorni</span> • 
                    <span id="agency-info">Tutte le agenzie</span>
                </div>
            </div>
        </div>
        <div class="header-stats">
            <div class="stat-item">
                <div class="stat-number" id="total-count">0</div>
                <div class="stat-label">Annunci</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="today-count">0</div>
                <div class="stat-label">Oggi</div>
            </div>
        </div>
    </header>

    <!-- Main scrolling area -->
    <main class="citta-multi-main">
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

        <!-- Annunci container -->
        <div id="annunci-container" class="annunci-container" style="display: none;">
            <!-- Populated via AJAX -->
        </div>
    </main>

    <!-- Footer with vendor and update info -->
    <footer class="citta-multi-footer">
        <div class="vendor-info">
            <?php if (!empty($vendor_data['banner'])): ?>
                <img src="<?php echo esc_url($vendor_data['banner']); ?>" 
                     alt="<?php echo esc_attr($vendor_data['shop_name']); ?>" 
                     class="vendor-logo-citta">
            <?php endif; ?>
            <div class="vendor-details">
                <div class="vendor-name"><?php echo esc_html($vendor_data['shop_name']); ?></div>
                <div class="system-info">Sistema Monitor Digitale</div>
            </div>
        </div>
        <div class="update-info">
            <div class="auto-refresh">🔄 Auto-refresh attivo</div>
            <div class="last-update">Ultimo aggiornamento: <span id="last-update"><?php echo current_time('H:i:s'); ?></span></div>
        </div>
    </footer>
</div>

<style>
/* Layout Città Multi-Agenzia Styles */
.monitor-citta-multi {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: #fff;
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
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

.city-info {
    display: flex;
    align-items: center;
    gap: 25px;
}

.city-icon {
    font-size: 4rem;
    opacity: 0.8;
}

.city-details h1 {
    font-size: 2.2rem;
    font-weight: 300;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.date-range {
    font-size: 1rem;
    opacity: 0.9;
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
    color: #3498db;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
    margin-top: 4px;
}

/* Main Content */
.citta-multi-main {
    flex: 1;
    position: relative;
    overflow: hidden;
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
    border-top: 4px solid #3498db;
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
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 25px;
    transition: all 0.3s ease;
    animation: slideInFromLeft 0.6s ease-out;
}

.annuncio-card:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
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
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
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
    color: #fff;
}

.annuncio-meta {
    display: flex;
    gap: 20px;
    font-size: 0.95rem;
    opacity: 0.9;
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
    opacity: 0.8;
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
    height: 8vh;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.vendor-logo-citta {
    height: 40px;
    width: auto;
    border-radius: 4px;
}

.vendor-name {
    font-size: 1.1rem;
    font-weight: 400;
}

.system-info {
    font-size: 0.8rem;
    opacity: 0.7;
    margin-top: 2px;
}

.update-info {
    text-align: right;
    font-size: 0.85rem;
}

.auto-refresh {
    color: #2ecc71;
    margin-bottom: 4px;
}

.last-update {
    opacity: 0.8;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .city-details h1 {
        font-size: 1.8rem;
    }
    
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
        height: 18vh;
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
        background: #000;
    }
    
    .annuncio-card {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.5);
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
</style>

<script>
// Città Multi-Agenzia Layout JavaScript
class CittaMultiLayout {
    constructor() {
        this.annunci = [];
        this.pollingInterval = null;
        this.scrollInterval = null;
        this.autoScrollEnabled = true;
        this.config = window.MonitorData || {};
        
        this.init();
    }

    init() {
        console.log('Initializing Città Multi-Agenzia Layout');
        
        // Load initial data
        this.loadAnnunci();
        
        // Start polling for updates
        this.startPolling();
        
        // Start auto-scroll
        this.startAutoScroll();
        
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
            console.log('Loading annunci città multi...');
            
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
            console.log('Città Multi Response:', data);
            
            if (data.success) {
                this.annunci = data.data.annunci || [];
                this.updateHeader(data.data);
                
                if (this.annunci.length > 0) {
                    this.renderAnnunci();
                    this.showAnnunci();
                } else {
                    this.showNoData();
                }
                
                this.updateLastUpdateTime();
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

    renderAnnunci() {
        const container = document.getElementById('annunci-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        this.annunci.forEach((annuncio, index) => {
            const card = this.createAnnuncioCard(annuncio, index);
            container.appendChild(card);
        });
    }

    createAnnuncioCard(annuncio, index) {
        const card = document.createElement('div');
        card.className = `annuncio-card ${annuncio.is_own_vendor ? 'own-vendor' : ''}`;
        card.style.animationDelay = `${index * 0.1}s`;
        
        // Photo or placeholder
        const fotoHtml = annuncio.fotografia && annuncio.fotografia.url
            ? `<img src="${annuncio.fotografia.url}" alt="${annuncio.nome} ${annuncio.cognome}" class="annuncio-foto">`
            : `<div class="annuncio-foto-placeholder">👤</div>`;
        
        // Format dates
        const datamorte = annuncio.data_di_morte 
            ? new Date(annuncio.data_di_morte).toLocaleDateString('it-IT')
            : '';
        
        const dataPubblicazione = new Date(annuncio.data_pubblicazione).toLocaleDateString('it-IT');
        
        // Agency badge
        const agencyBadge = annuncio.is_own_vendor 
            ? `<span class="agency-badge own">La tua agenzia</span>`
            : `<span class="agency-badge">${annuncio.agenzia_nome}</span>`;
        
        card.innerHTML = `
            ${fotoHtml}
            <div class="annuncio-details">
                <div class="annuncio-nome">
                    ${annuncio.nome} ${annuncio.cognome}
                    ${annuncio.eta ? `<span style="opacity: 0.8; font-weight: 300;"> • ${annuncio.eta} anni</span>` : ''}
                </div>
                <div class="annuncio-meta">
                    ${datamorte ? `<div class="meta-item"><span class="meta-icon">⚱️</span> ${datamorte}</div>` : ''}
                    <div class="meta-item"><span class="meta-icon">📅</span> ${dataPubblicazione}</div>
                </div>
                <div class="annuncio-agency">
                    ${annuncio.agenzia_nome}
                    ${agencyBadge}
                </div>
            </div>
        `;
        
        return card;
    }

    updateHeader(data) {
        // Update city name
        document.getElementById('city-name').textContent = data.city || 'Città';
        
        // Update date info
        const daysRange = data.days_range || 7;
        const daysText = daysRange === 1 ? 'Oggi' : `Ultimi ${daysRange} giorni`;
        document.getElementById('date-info').textContent = daysText;
        
        // Update agency info
        const agencyText = data.show_all_agencies ? 'Tutte le agenzie' : 'Solo la tua agenzia';
        document.getElementById('agency-info').textContent = agencyText;
        
        // Update counts
        document.getElementById('total-count').textContent = data.count || 0;
        
        // Calculate today count
        const today = new Date().toDateString();
        const todayCount = this.annunci.filter(annuncio => {
            const pubDate = new Date(annuncio.data_pubblicazione).toDateString();
            return pubDate === today;
        }).length;
        document.getElementById('today-count').textContent = todayCount;
    }

    showLoading(show) {
        const loading = document.getElementById('citta-loading');
        if (loading) {
            loading.style.display = show ? 'flex' : 'none';
        }
    }

    showAnnunci() {
        const container = document.getElementById('annunci-container');
        const noData = document.getElementById('citta-no-data');
        
        if (container) container.style.display = 'block';
        if (noData) noData.style.display = 'none';
    }

    showNoData() {
        const container = document.getElementById('annunci-container');
        const noData = document.getElementById('citta-no-data');
        
        if (container) container.style.display = 'none';
        if (noData) noData.style.display = 'flex';
    }

    updateLastUpdateTime() {
        const lastUpdateSpan = document.getElementById('last-update');
        if (lastUpdateSpan) {
            lastUpdateSpan.textContent = new Date().toLocaleTimeString('it-IT', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }

    startPolling() {
        this.pollingInterval = setInterval(() => {
            this.loadAnnunci();
        }, this.config.pollingInterval || 30000); // 30 seconds for città multi
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    startAutoScroll() {
        if (!this.autoScrollEnabled || this.annunci.length <= 3) return;
        
        const container = document.getElementById('annunci-container');
        if (!container) return;
        
        this.scrollInterval = setInterval(() => {
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            const scrollTop = container.scrollTop;
            
            if (scrollTop + clientHeight >= scrollHeight - 10) {
                // Reached bottom, scroll back to top
                container.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                // Scroll down by one card height
                const cardHeight = container.querySelector('.annuncio-card')?.offsetHeight || 100;
                container.scrollBy({ top: cardHeight + 20, behavior: 'smooth' });
            }
        }, 5000); // Scroll every 5 seconds
    }

    stopAutoScroll() {
        if (this.scrollInterval) {
            clearInterval(this.scrollInterval);
            this.scrollInterval = null;
        }
    }

    pauseLayout() {
        this.stopPolling();
        this.stopAutoScroll();
    }

    resumeLayout() {
        this.startPolling();
        this.startAutoScroll();
    }

    destroy() {
        this.stopPolling();
        this.stopAutoScroll();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (window.MonitorData && window.MonitorData.layoutType === 'citta_multi') {
        window.cittaMultiLayout = new CittaMultiLayout();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.cittaMultiLayout) {
        window.cittaMultiLayout.destroy();
    }
});
</script>