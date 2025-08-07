/**
 * ACF Location Updater - AJAX Interface
 * Handles the AJAX-based update process for città and provincia fields
 */

(function($) {
    'use strict';

    const AcfLocationUpdater = {
        // Configuration
        isRunning: false,
        isPaused: false,
        currentPhase: 1,
        currentOffset: 0,
        state: null,
        startTime: null,
        updateInterval: null,
        
        // Statistics
        stats: {
            totalProcessed: 0,
            totalUpdated: 0,
            totalErrors: 0,
            processingSpeed: 0
        },

        /**
         * Initialize the updater
         */
        init: function() {
            this.bindEvents();
            this.checkExistingState();
        },

        /**
         * Bind UI events
         */
        bindEvents: function() {
            $('#start-update').on('click', () => this.startUpdate());
            $('#pause-update').on('click', () => this.pauseUpdate());
            $('#resume-update').on('click', () => this.resumeUpdate());
            $('#reset-update').on('click', () => this.resetUpdate());
            $('#clear-log').on('click', () => this.clearLog());
            $('#download-report').on('click', () => this.downloadReport());
        },

        /**
         * Check for existing state
         */
        checkExistingState: function() {
            $.ajax({
                url: acf_updater.ajax_url,
                type: 'POST',
                data: {
                    action: 'acf_updater_get_stats',
                    nonce: acf_updater.nonce
                },
                success: (response) => {
                    if (response.success && response.data.state) {
                        this.state = response.data.state;
                        this.showResumeOption();
                    }
                }
            });
        },

        /**
         * Show resume option
         */
        showResumeOption: function() {
            if (confirm('È stato trovato un processo precedente non completato. Vuoi riprenderlo?')) {
                this.resumeUpdate();
            } else {
                this.resetUpdate();
            }
        },

        /**
         * Start the update process
         */
        startUpdate: function() {
            if (this.isRunning) return;
            
            this.isRunning = true;
            this.startTime = Date.now();
            
            $('#start-update').hide();
            $('#pause-update').show();
            $('#reset-update').show();
            $('#progress-dashboard, #log-console, #statistics-panel').slideDown();
            
            this.addLog('Inizializzazione processo di aggiornamento...', 'info');
            
            // Initialize the process
            $.ajax({
                url: acf_updater.ajax_url,
                type: 'POST',
                data: {
                    action: 'acf_updater_init',
                    nonce: acf_updater.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.state = response.data.state;
                        this.addLog('Inizializzazione completata. Trovati record da elaborare.', 'success');
                        this.updateUI();
                        this.startProcessing();
                        this.startTimer();
                    } else {
                        this.handleError('Errore durante l\'inizializzazione');
                    }
                },
                error: () => {
                    this.handleError('Errore di connessione durante l\'inizializzazione');
                }
            });
        },

        /**
         * Start processing batches
         */
        startProcessing: function() {
            if (!this.isRunning || this.isPaused) return;
            
            const phase = this.currentPhase;
            const offset = this.currentOffset;
            
            // Check if we need to move to next phase
            if (this.state && offset >= this.state.phase_counts[`phase${phase}`]) {
                if (phase < 4) {
                    this.currentPhase++;
                    this.currentOffset = 0;
                    this.addLog(`Fase ${phase} completata. Passaggio alla fase ${this.currentPhase}`, 'success');
                    this.startProcessing();
                    return;
                } else {
                    this.completeUpdate();
                    return;
                }
            }
            
            // Process batch
            this.processBatch(phase, offset);
        },

        /**
         * Process a single batch
         */
        processBatch: function(phase, offset) {
            const phaseNames = {
                1: 'Aggiornamento Città Esistenti',
                2: 'Inserimento Città Mancanti',
                3: 'Aggiornamento Provincie Esistenti',
                4: 'Inserimento Provincie Mancanti'
            };
            
            $.ajax({
                url: acf_updater.ajax_url,
                type: 'POST',
                data: {
                    action: 'acf_updater_process',
                    nonce: acf_updater.nonce,
                    phase: phase,
                    offset: offset
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        
                        // Update statistics
                        this.stats.totalProcessed += data.processed;
                        this.stats.totalUpdated += data.processed;
                        if (data.errors && data.errors.length > 0) {
                            this.stats.totalErrors += data.errors.length;
                            data.errors.forEach(error => this.addLog(error, 'error'));
                        }
                        
                        // Update current offset
                        this.currentOffset = offset + data.processed;
                        
                        // Update UI
                        this.updatePhaseProgress(phase, data.total_processed, this.state.phase_counts[`phase${phase}`]);
                        this.updateStatistics();
                        
                        // Log progress
                        if (data.processed > 0) {
                            this.addLog(`${phaseNames[phase]}: ${data.message}`, 'info');
                        }
                        
                        // Continue processing
                        if (data.phase_complete) {
                            this.currentPhase = phase < 4 ? phase + 1 : phase;
                            this.currentOffset = 0;
                        }
                        
                        // Process next batch
                        setTimeout(() => this.startProcessing(), 100);
                        
                    } else {
                        this.handleError(`Errore durante l'elaborazione della fase ${phase}`);
                    }
                },
                error: () => {
                    this.handleError(`Errore di connessione durante la fase ${phase}. Riprovo...`);
                    setTimeout(() => this.processBatch(phase, offset), 5000);
                }
            });
        },

        /**
         * Update phase progress
         */
        updatePhaseProgress: function(phase, processed, total) {
            const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
            const $container = $(`#phase-${phase}`);
            
            $container.find('.progress-bar').css('width', percentage + '%');
            $container.find('.progress-text').text(percentage + '%');
            $container.find('.processed').text(processed);
            $container.find('.total').text(total);
            
            // Update overall progress
            this.updateOverallProgress();
        },

        /**
         * Update overall progress
         */
        updateOverallProgress: function() {
            if (!this.state) return;
            
            let totalItems = 0;
            let totalProcessed = 0;
            
            for (let i = 1; i <= 4; i++) {
                totalItems += this.state.phase_counts[`phase${i}`] || 0;
                totalProcessed += this.state.processed[`phase${i}`] || 0;
            }
            
            const percentage = totalItems > 0 ? Math.round((totalProcessed / totalItems) * 100) : 0;
            
            $('.overall-progress .progress-bar').css('width', percentage + '%');
            $('.overall-progress .progress-text').text(percentage + '%');
            
            // Update estimated time
            if (this.stats.totalProcessed > 0 && percentage > 0) {
                const elapsed = Date.now() - this.startTime;
                const estimated = (elapsed / percentage) * (100 - percentage);
                $('#estimated-time').text(this.formatTime(estimated / 1000));
            }
        },

        /**
         * Update statistics panel
         */
        updateStatistics: function() {
            $('#total-processed').text(this.stats.totalProcessed);
            $('#total-updated').text(this.stats.totalUpdated);
            $('#total-errors').text(this.stats.totalErrors);
            
            // Calculate processing speed
            if (this.startTime) {
                const elapsed = (Date.now() - this.startTime) / 1000;
                if (elapsed > 0) {
                    this.stats.processingSpeed = (this.stats.totalProcessed / elapsed).toFixed(1);
                    $('#processing-speed').text(this.stats.processingSpeed);
                }
            }
        },

        /**
         * Update UI from state
         */
        updateUI: function() {
            if (!this.state) return;
            
            // Update phase totals
            for (let i = 1; i <= 4; i++) {
                const total = this.state.phase_counts[`phase${i}`] || 0;
                $(`#phase-${i} .total`).text(total);
                
                if (this.state.processed[`phase${i}`]) {
                    this.updatePhaseProgress(i, this.state.processed[`phase${i}`], total);
                }
            }
        },

        /**
         * Pause the update
         */
        pauseUpdate: function() {
            this.isPaused = true;
            this.isRunning = false;
            
            $('#pause-update').hide();
            $('#resume-update').show();
            
            this.addLog('Processo messo in pausa', 'warning');
        },

        /**
         * Resume the update
         */
        resumeUpdate: function() {
            this.isPaused = false;
            this.isRunning = true;
            
            $('#resume-update').hide();
            $('#pause-update').show();
            $('#start-update').hide();
            $('#progress-dashboard, #log-console, #statistics-panel').slideDown();
            
            this.addLog('Processo ripreso', 'info');
            
            if (!this.startTime) {
                this.startTime = Date.now();
                this.startTimer();
            }
            
            // Get current state and continue
            $.ajax({
                url: acf_updater.ajax_url,
                type: 'POST',
                data: {
                    action: 'acf_updater_get_stats',
                    nonce: acf_updater.nonce
                },
                success: (response) => {
                    if (response.success && response.data.state) {
                        this.state = response.data.state;
                        
                        // Find current phase and offset
                        for (let i = 1; i <= 4; i++) {
                            const phaseKey = `phase${i}`;
                            if (this.state.processed[phaseKey] < this.state.phase_counts[phaseKey]) {
                                this.currentPhase = i;
                                this.currentOffset = this.state.processed[phaseKey];
                                break;
                            }
                        }
                        
                        this.updateUI();
                        this.startProcessing();
                    }
                }
            });
        },

        /**
         * Reset the update
         */
        resetUpdate: function() {
            if (!confirm('Sei sicuro di voler resettare il processo? Tutti i progressi verranno persi.')) {
                return;
            }
            
            this.isRunning = false;
            this.isPaused = false;
            this.currentPhase = 1;
            this.currentOffset = 0;
            this.state = null;
            this.stats = {
                totalProcessed: 0,
                totalUpdated: 0,
                totalErrors: 0,
                processingSpeed: 0
            };
            
            // Cleanup server state
            $.ajax({
                url: acf_updater.ajax_url,
                type: 'POST',
                data: {
                    action: 'acf_updater_cleanup',
                    nonce: acf_updater.nonce
                }
            });
            
            // Reset UI
            $('#start-update').show();
            $('#pause-update, #resume-update, #reset-update').hide();
            $('#progress-dashboard, #log-console, #statistics-panel, #final-report').hide();
            $('.progress-bar').css('width', '0%');
            $('.progress-text').text('0%');
            $('.processed').text('0');
            $('#log-content').empty();
            
            this.stopTimer();
            this.addLog('Processo resettato', 'warning');
        },

        /**
         * Complete the update
         */
        completeUpdate: function() {
            this.isRunning = false;
            this.stopTimer();
            
            $('#pause-update').hide();
            $('#resume-update').hide();
            
            this.addLog('Aggiornamento completato con successo!', 'success');
            
            // Get final statistics
            $.ajax({
                url: acf_updater.ajax_url,
                type: 'POST',
                data: {
                    action: 'acf_updater_get_stats',
                    nonce: acf_updater.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showFinalReport(response.data.stats);
                    }
                }
            });
            
            // Cleanup
            $.ajax({
                url: acf_updater.ajax_url,
                type: 'POST',
                data: {
                    action: 'acf_updater_cleanup',
                    nonce: acf_updater.nonce
                }
            });
        },

        /**
         * Show final report
         */
        showFinalReport: function(stats) {
            let html = '<div class="report-summary">';
            html += '<h3>Riepilogo Aggiornamento</h3>';
            html += '<table class="widefat">';
            html += '<thead><tr><th>Tipo Post</th><th>Totale</th><th>Con Città</th><th>Con Provincia</th><th>Completezza</th></tr></thead>';
            html += '<tbody>';
            
            if (stats && stats.post_type_stats) {
                stats.post_type_stats.forEach(stat => {
                    const completeness = stat.total_posts > 0 
                        ? Math.round(((stat.posts_with_city + stat.posts_with_province) / (stat.total_posts * 2)) * 100)
                        : 0;
                    
                    html += `<tr>
                        <td>${stat.post_type}</td>
                        <td>${stat.total_posts}</td>
                        <td>${stat.posts_with_city}</td>
                        <td>${stat.posts_with_province}</td>
                        <td>${completeness}%</td>
                    </tr>`;
                });
            }
            
            html += '</tbody></table>';
            html += `<p><strong>Tempo totale:</strong> ${$('#elapsed-time').text()}</p>`;
            html += `<p><strong>Record processati:</strong> ${this.stats.totalProcessed}</p>`;
            html += `<p><strong>Record aggiornati:</strong> ${this.stats.totalUpdated}</p>`;
            html += `<p><strong>Errori:</strong> ${this.stats.totalErrors}</p>`;
            html += '</div>';
            
            $('#report-content').html(html);
            $('#final-report').slideDown();
        },

        /**
         * Download report
         */
        downloadReport: function() {
            const report = $('#report-content').html();
            const blob = new Blob([report], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `acf-location-update-report-${new Date().toISOString()}.html`;
            a.click();
            URL.revokeObjectURL(url);
        },

        /**
         * Add log entry
         */
        addLog: function(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const typeIcons = {
                'info': 'ℹ️',
                'success': '✅',
                'warning': '⚠️',
                'error': '❌'
            };
            
            const logEntry = `<div class="log-entry log-${type}">
                <span class="log-time">[${timestamp}]</span>
                <span class="log-icon">${typeIcons[type]}</span>
                <span class="log-message">${message}</span>
            </div>`;
            
            $('#log-content').prepend(logEntry);
            
            // Keep only last 100 entries
            $('#log-content .log-entry').slice(100).remove();
        },

        /**
         * Clear log
         */
        clearLog: function() {
            $('#log-content').empty();
            this.addLog('Log pulito', 'info');
        },

        /**
         * Handle errors
         */
        handleError: function(message) {
            this.addLog(message, 'error');
            console.error('ACF Updater Error:', message);
        },

        /**
         * Start timer
         */
        startTimer: function() {
            this.updateInterval = setInterval(() => {
                if (this.startTime) {
                    const elapsed = Date.now() - this.startTime;
                    $('#elapsed-time').text(this.formatTime(elapsed / 1000));
                }
            }, 1000);
        },

        /**
         * Stop timer
         */
        stopTimer: function() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
        },

        /**
         * Format time
         */
        formatTime: function(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = Math.floor(seconds % 60);
            
            return [hours, minutes, secs]
                .map(v => v < 10 ? '0' + v : v)
                .join(':');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#acf-updater-container').length > 0) {
            AcfLocationUpdater.init();
        }
    });

})(jQuery);