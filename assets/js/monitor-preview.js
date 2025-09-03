/**
 * Monitor Preview System
 * Handles layout preview functionality for vendor dashboard
 */

const MonitorPreview = {
    currentMonitor: null,
    previewModal: null,
    $: null, // jQuery reference
    
    init: function($) {
        this.$ = $;
        this.createPreviewModal();
        this.bindEvents();
    },

    bindEvents: function() {
        const self = this;
        const $ = this.$;

        // Preview button click
        $(document).on('click', '.btn-preview-layout', function(e) {
            e.preventDefault();
            const monitorId = $(this).data('monitor-id');
            const monitorName = $(this).data('monitor-name');
            const layoutType = $(this).data('layout-type');
            
            self.showPreview(monitorId, monitorName, layoutType);
        });

        // Preview size toggle
        $(document).on('click', '.preview-size-btn', function() {
            $('.preview-size-btn').removeClass('active');
            $(this).addClass('active');
            
            const size = $(this).data('size');
            self.updatePreviewSize(size);
        });

        // Refresh preview
        $(document).on('click', '.btn-refresh-preview', function() {
            if (self.currentMonitor) {
                self.loadPreview(self.currentMonitor);
            }
        });

        // Close preview modal
        $(document).on('click', '.preview-modal-close, .preview-modal-backdrop', function() {
            self.closePreview();
        });

        // Prevent modal close when clicking inside content
        $(document).on('click', '.preview-modal-content', function(e) {
            e.stopPropagation();
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            if (self.previewModal && self.previewModal.is(':visible')) {
                if (e.key === 'Escape') {
                    self.closePreview();
                }
                // Size shortcuts: 1=mobile, 2=tablet, 3=desktop
                if (e.key >= '1' && e.key <= '3') {
                    const sizes = ['mobile', 'tablet', 'desktop'];
                    const sizeIndex = parseInt(e.key) - 1;
                    if (sizes[sizeIndex]) {
                        $('.preview-size-btn[data-size="' + sizes[sizeIndex] + '"]').click();
                    }
                }
            }
        });
    },

    createPreviewModal: function() {
        const $ = this.$;
        const modalHtml = `
            <div id="monitor-preview-modal" class="preview-modal" style="display: none;">
                <div class="preview-modal-backdrop"></div>
                <div class="preview-modal-container">
                    <div class="preview-modal-header">
                        <div class="preview-modal-title">
                            <h2 id="preview-modal-title">Anteprima Layout Monitor</h2>
                            <div class="preview-modal-info">
                                <span id="preview-layout-type" class="preview-badge"></span>
                                <span id="preview-monitor-name" class="preview-monitor-name"></span>
                            </div>
                        </div>
                        
                        <div class="preview-controls">
                            <div class="preview-size-controls">
                                <button type="button" class="preview-size-btn" data-size="mobile" title="Mobile (1)">
                                    📱 Mobile
                                </button>
                                <button type="button" class="preview-size-btn active" data-size="tablet" title="Tablet (2)">
                                    📱 Tablet
                                </button>
                                <button type="button" class="preview-size-btn" data-size="desktop" title="Desktop (3)">
                                    🖥️ Desktop
                                </button>
                            </div>
                            
                            <div class="preview-actions">
                                <button type="button" class="btn btn-sm btn-secondary btn-refresh-preview" title="Aggiorna Anteprima">
                                    🔄 Aggiorna
                                </button>
                                <button type="button" class="preview-modal-close" title="Chiudi (Esc)">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-modal-body">
                        <div id="preview-loading" class="preview-loading">
                            <div class="spinner"></div>
                            <p>Generazione anteprima...</p>
                        </div>
                        
                        <div id="preview-error" class="preview-error" style="display: none;">
                            <div class="error-content">
                                <h3>Errore Anteprima</h3>
                                <p id="preview-error-message">Si è verificato un errore durante la generazione dell'anteprima.</p>
                                <button type="button" class="btn btn-primary btn-retry-preview">Riprova</button>
                            </div>
                        </div>
                        
                        <div id="preview-container" class="preview-container">
                            <div id="preview-viewport" class="preview-viewport">
                                <div id="preview-content" class="preview-content">
                                    <!-- Dynamic preview content -->
                                </div>
                            </div>
                            
                            <div class="preview-info-panel">
                                <h4>Informazioni Layout</h4>
                                <div id="preview-layout-info">
                                    <!-- Dynamic layout information -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-modal-footer">
                        <div class="preview-footer-info">
                            <small>💡 Suggerimento: Usa 1/2/3 per cambiare velocemente la dimensione di anteprima</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        this.previewModal = $('#monitor-preview-modal');
    },

    showPreview: function(monitorId, monitorName, layoutType) {
        const $ = this.$;
        if (!monitorId) {
            this.showError('ID monitor non valido');
            return;
        }

        this.currentMonitor = {
            id: monitorId,
            name: monitorName,
            layout_type: layoutType
        };

        // Update modal title
        $('#preview-modal-title').text(`Anteprima: ${monitorName}`);
        $('#preview-layout-type').text(this.getLayoutTypeLabel(layoutType)).attr('class', 'preview-badge preview-badge-' + layoutType);
        $('#preview-monitor-name').text(monitorName);

        // Show modal
        this.previewModal.fadeIn(300);
        $('body').addClass('preview-modal-open');

        // Load preview
        this.loadPreview(this.currentMonitor);
    },

    loadPreview: function(monitor) {
        const $ = this.$;
        this.showLoading();

        const previewSize = $('.preview-size-btn.active').data('size') || 'tablet';

        $.ajax({
            url: monitor_vendor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'monitor_preview_layout',
                nonce: monitor_vendor_ajax.nonce,
                monitor_id: monitor.id,
                preview_size: previewSize
            },
            success: (response) => {
                if (response.success) {
                    this.renderPreview(response.data);
                } else {
                    this.showError(response.data || 'Errore nella generazione dell\'anteprima');
                }
            },
            error: (xhr) => {
                console.error('Preview error:', xhr);
                this.showError('Errore di connessione. Verifica la tua connessione internet.');
            }
        });
    },

    renderPreview: function(data) {
        const $ = this.$;
        this.hideLoading();
        
        // Render HTML content
        $('#preview-content').html(data.html);
        
        // Apply CSS styles
        this.applyPreviewStyles(data.css);
        
        // Update info panel
        this.updateInfoPanel(data);
        
        // Update preview size
        this.updatePreviewSize(data.preview_size || 'tablet');
        
        $('#preview-container').show();
    },

    applyPreviewStyles: function(css) {
        const $ = this.$;
        // Remove existing preview styles
        $('#preview-dynamic-styles').remove();
        
        // Add new styles
        if (css) {
            const styleElement = $('<style id="preview-dynamic-styles">' + css + '</style>');
            $('head').append(styleElement);
        }
    },

    updateInfoPanel: function(data) {
        const $ = this.$;
        const layoutInfo = this.getLayoutInfo(data.layout_type, data);
        $('#preview-layout-info').html(layoutInfo);
    },

    getLayoutInfo: function(layoutType, data) {
        const baseInfo = `
            <div class="info-item">
                <strong>Tipo Layout:</strong> ${this.getLayoutTypeLabel(layoutType)}
            </div>
            <div class="info-item">
                <strong>Monitor:</strong> ${data.monitor_name}
            </div>
        `;

        let specificInfo = '';
        
        switch (layoutType) {
            case 'manifesti':
                specificInfo = `
                    <div class="info-item">
                        <strong>Caratteristiche:</strong>
                        <ul>
                            <li>📰 Slideshow automatico manifesti</li>
                            <li>🔄 Rotazione ogni 10 secondi</li>
                            <li>📱 Layout completamente responsive</li>
                        </ul>
                    </div>
                `;
                break;
                
            case 'solo_annuncio':
                specificInfo = `
                    <div class="info-item">
                        <strong>Caratteristiche:</strong>
                        <ul>
                            <li>📋 Focus su singolo annuncio</li>
                            <li>🖼️ Visualizzazione ottimizzata immagini</li>
                            <li>📱 Design pulito e moderno</li>
                        </ul>
                    </div>
                `;
                break;
                
            case 'citta_multi':
                specificInfo = `
                    <div class="info-item">
                        <strong>Caratteristiche:</strong>
                        <ul>
                            <li>🏙️ Vista città multi-agenzia</li>
                            <li>🔄 Aggiornamento automatico</li>
                            <li>🏢 Evidenziazione propria agenzia</li>
                        </ul>
                    </div>
                `;
                break;
        }

        return baseInfo + specificInfo;
    },

    updatePreviewSize: function(size) {
        const $ = this.$;
        const viewport = $('#preview-viewport');
        
        // Remove existing size classes
        viewport.removeClass('preview-mobile preview-tablet preview-desktop');
        
        // Add new size class
        viewport.addClass('preview-' + size);
        
        // Update active button
        $('.preview-size-btn').removeClass('active');
        $('.preview-size-btn[data-size="' + size + '"]').addClass('active');
    },

    showLoading: function() {
        const $ = this.$;
        $('#preview-loading').show();
        $('#preview-error').hide();
        $('#preview-container').hide();
    },

    hideLoading: function() {
        const $ = this.$;
        $('#preview-loading').hide();
    },

    showError: function(message) {
        const $ = this.$;
        this.hideLoading();
        $('#preview-error-message').text(message);
        $('#preview-error').show();
        $('#preview-container').hide();
    },

    closePreview: function() {
        const $ = this.$;
        this.previewModal.fadeOut(300);
        $('body').removeClass('preview-modal-open');
        this.currentMonitor = null;
        
        // Clean up styles
        $('#preview-dynamic-styles').remove();
    },

    getLayoutTypeLabel: function(type) {
        const labels = {
            'manifesti': 'Manifesti',
            'solo_annuncio': 'Solo Annuncio',
            'citta_multi': 'Città Multi-Agenzia'
        };
        return labels[type] || type;
    },

    // Public method for external integration
    previewMonitor: function(monitorId, monitorName, layoutType) {
        this.showPreview(monitorId, monitorName, layoutType);
    }
};

// Initialize when document is ready
jQuery(document).ready(function($) {
    if (typeof monitor_vendor_ajax !== 'undefined') {
        MonitorPreview.init($);
    }
});

// Export for external use
window.MonitorPreview = MonitorPreview;