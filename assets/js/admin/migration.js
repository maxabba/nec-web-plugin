jQuery(document).ready(function ($) {
    let currentStep = 0;
    let totalSteps = 0;
    let currentFile = '';
    let checkStatusStarted = false;

    function initializeMigrationWizard() {
        checkStatusImage();
        getNextStep();
    }

    function getNextStep() {
        console.log('Requesting next step. Current step:', currentStep);
        jQuery.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_next_step',
                nonce: migrationAjax.nonce,
                current_step: currentStep,
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.step === 'complete') {
                        $('#current-step-content').html(response.data.content);
                        $('#step-indicator').hide();
                    } else {
                        currentStep = response.data.step;
                        totalSteps = response.data.total_steps;
                        updateStepIndicator();
                        $('#current-step-content').html(response.data.content);

                        if (response.data.current_file && response.data.current_file !== '') {
                            console.log('Current file:', response.data.current_file);
                            initializeStepForm(response.data.current_file);
                        } else {
                            console.error('No valid file returned for the current step.');
                        }
                    }
                } else {
                    handleWPError(response);
                }
            },

            error: handleAjaxError
        });
    }

    function updateStepIndicator() {
        $('#step-indicator').html('Step ' + currentStep + ' of ' + totalSteps);
    }

    function initializeStepForm(file) {
        console.log('Initializing step form for file:', file);
        currentFile = file;

        $('#migration-form').off('submit').on('submit', function (e) {
            e.preventDefault();
            console.log('Form submitted');

            const formData = new FormData(this);
            const fileInput = this.querySelector('input[type="file"]');
            const skipCheckbox = this.querySelector('input[name="skip_step"]');

            if (skipCheckbox.checked) {
                console.log('Skipping step for file:', currentFile);
                getNextStep();
                return;
            }

            if (fileInput && fileInput.files.length > 0) {
                console.log('File selected:', fileInput.files[0].name);
            } else {
                console.error('No file selected');
                alert('Please select a file before proceeding or select "Skip this step".');
                return;
            }

            jQuery.ajax({
                url: migrationAjax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function (evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = evt.loaded / evt.total * 100;
                            $('#progress').css('width', percentComplete + '%');
                            $('#progress-percentage').text(percentComplete.toFixed(2) + '%');
                        }
                    }, false);
                    return xhr;
                },
                beforeSend: function () {
                    $('#upload-progress').show();
                },
                success: function (response) {
                    if (response.success) {
                        $('#stop-migration').show();
                        $('#resume-migration').hide();
                        $('#migration-log').append('<p>' + response.data + '</p>');
                        startProgressCheck();
                    }else{
                        handleWPError(response);
                    }
                },
                error: handleAjaxError,
                complete: function () {
                    $('#upload-progress').hide();
                    $('#progress-container').show();
                }
            });
        });


    }

    function startProgressCheck() {
        console.log('startProgressCheck called');
        $('#progress-container').show();
        checkStatus();
    }

    function checkStatus() {
        console.log('checkStatus called - checking multiple progress files');

        jQuery.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_migration_status',
                nonce: migrationAjax.nonce
            },
            success: function (response) {
                if (response.success && response.data) {
                    const data = response.data;
                    if (typeof data.overall_percentage === 'number') {
                        updateOverallProgress(data.overall_percentage);
                    } else {
                        console.warn('Invalid overall_percentage:', data.overall_percentage);
                    }
                    if (data.progress && typeof data.progress === 'object') {
                        updateFileProgresses(data.progress);
                    } else {
                        console.warn('Invalid progress data:', data.progress);
                    }
                    if (typeof data.log === 'string') {
                        $('#migration-log').html(data.log.replace(/\n/g, '<br>'));
                    }

                    // Enhanced logic to handle multiple progress files
                    let shouldContinueChecking = false;
                    let currentFileFinished = false;
                    
                    // Check current file status
                    if (data.progress[currentFile]) {
                        const fileStatus = data.progress[currentFile].status;
                        console.log(`Current file ${currentFile} status: ${fileStatus}`);

                        if (fileStatus === 'ongoing' || fileStatus === 'completed') {
                            shouldContinueChecking = true;
                        } else if (fileStatus === 'finished') {
                            currentFileFinished = true;
                        } else {
                            console.warn('Unknown status for current file:', currentFile, fileStatus);
                            shouldContinueChecking = true; // Keep checking for unknown statuses
                        }
                    } else {
                        console.error('No progress data for current file:', currentFile);
                        shouldContinueChecking = true; // Keep checking if no data
                    }
                    
                    // Also check if any other migration files are still processing
                    for (const file in data.progress) {
                        const status = data.progress[file].status;
                        if (status === 'ongoing' || status === 'completed') {
                            console.log(`File ${file} still processing with status: ${status}`);
                            shouldContinueChecking = true;
                        }
                    }

                    if (currentFileFinished) {
                        console.log(`Current file ${currentFile} finished, proceeding to next step`);
                        $('#progress-container').hide();
                        $('#stop-migration, #resume-migration').hide();
                        getNextStep();
                    } else if (shouldContinueChecking) {
                        setTimeout(checkStatus, 1000);
                    }
                } else {
                    handleWPError(response);
                    $('#stop-migration').hide();
                    $('#resume-migration').show();
                }
            },
            error: handleAjaxError
        });
    }


    function checkStatusImage() {
        console.log('checkStatusImage called for multiple progress files');

        jQuery.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_image_migration_status',
                nonce: migrationAjax.nonce
            },
            success: function (response) {
                if (response.success && response.data) {
                    const data = response.data;
                    if (data.progress && typeof data.progress === 'object') {
                        updateImageProgressDisplay(data.progress);
                    } else {
                        console.warn('Invalid progress data:', data.progress);
                    }

                    // Check if all image queues have completed
                    let completed = true;
                    let hasActiveQueues = false;

                    for (const file in data.progress) {
                        hasActiveQueues = true;
                        const status = data.progress[file].status;
                        if (status !== 'finished' && data.progress[file].percentage < 100) {
                            completed = false;
                            break;
                        }
                    }

                    if (!completed && hasActiveQueues) {
                        console.log('Image queues still processing, checking again in 2 seconds');
                        setTimeout(checkStatusImage, 2000);
                    } else if (completed && hasActiveQueues) {
                        console.log('All image queues completed');
                        updateImageDownloadStatus('success', '✅ Tutti i download delle immagini sono stati completati!');
                    }

                } else {
                    handleWPError(response);
                    $('#stop-migration').hide();
                    $('#resume-migration').show();
                }
            },
            error: handleAjaxError
        });
    }

    function updateImageProgressDisplay(progressData) {
        // Enhanced function to handle multiple image queue progress files
        for (const file in progressData) {
            const progress = progressData[file];
            
            // Create unique ID for this progress display
            const fileId = file.replace(/[^a-zA-Z0-9]/g, '-');
            let fileProgressElement = $('#file-progress-' + fileId);
            
            if (fileProgressElement.length === 0) {
                // Create progress bar for this image queue
                const displayName = getImageQueueDisplayName(file);
                $('#migration-log').after(`
                    <div id="file-progress-${fileId}" class="file-progress image-queue-progress">
                        <div class="file-name">${displayName}: <span class="file-percentage">0%</span> 
                            <span class="queue-status">[${progress.status || 'unknown'}]</span>
                        </div>
                        <div class="progress-bar"><div class="progress"></div></div>
                        <div class="processed-row">
                            Processed: <span class="processed-count">0</span>/<span class="total-count">0</span>
                            <span class="queue-info"> | Status: <span class="status-text">unknown</span></span>
                        </div>
                    </div>
                `);
                fileProgressElement = $('#file-progress-' + fileId);
            }
            
            // Update progress display
            const percentage = Math.min(100, Math.max(0, progress.percentage || 0));
            const percentageText = percentage.toFixed(2) + '%';
            const percentageWidth = percentage + '%';
            
            fileProgressElement.find('.file-percentage').text(percentageText);
            fileProgressElement.find('.progress').css('width', percentageWidth);
            fileProgressElement.find('.processed-count').text(progress.processed || 0);
            fileProgressElement.find('.total-count').text(progress.total || 0);
            fileProgressElement.find('.status-text').text(progress.status || 'unknown');
            fileProgressElement.find('.queue-status').text('[' + (progress.status || 'unknown') + ']');
            
            // Add visual status indicators
            fileProgressElement.removeClass('status-ongoing status-completed status-finished status-error');
            if (progress.status) {
                fileProgressElement.addClass('status-' + progress.status);
            }
            
            // Hide completed queues after a delay
            if (progress.status === 'finished' && percentage >= 100) {
                setTimeout(() => {
                    fileProgressElement.fadeOut(2000, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    }
    
    function getImageQueueDisplayName(filename) {
        // Convert queue filenames to user-friendly names
        const displayNames = {
            'image_download_queue.csv': 'Immagini Necrologi',
            'image_download_queue_ricorrenze.csv': 'Immagini Ricorrenze',
            'image_download_queue_ringraziamenti.csv': 'Immagini Ringraziamenti',
            'image_download_queue_manifesti.csv': 'Immagini Manifesti'
        };
        
        return displayNames[filename] || filename.replace('_', ' ').replace('.csv', '');
    }


    function updateOverallProgress(percentage) {
        const displayPercentage = percentage < 1 ? percentage.toFixed(2) : Math.round(percentage);
        $('#overall-progress-percentage').text(displayPercentage + '%');
        $('#overall-progress-bar').css('width', displayPercentage + '%');
    }

    function updateFileProgresses(progresses) {
        if (!progresses || typeof progresses !== 'object') {
            console.error('Invalid progress data received:', progresses);
            return;
        }

        for (const file in progresses) {
            if (progresses.hasOwnProperty(file)) {
                const progress = progresses[file];
                const fileId = file.replace('.', '-');
                let fileProgressElement = $('#file-progress-' + fileId);

                if (fileProgressElement.length === 0) {
                    // Create progress bar if it doesn't exist
                    $('#file-progresses').append(`
                        <div id="file-progress-${fileId}" class="file-progress">
                            <div class="file-name">${file}: <span class="file-percentage">0%</span></div>
                            <div class="progress-bar"><div class="progress"></div></div>
                            <div class="processed-row">Processed: <span class="processed-count">0</span>/<span class="total-count">0</span></div>
                        </div>
                    `);
                    fileProgressElement = $('#file-progress-' + fileId);
                }

                if (progress && typeof progress.percentage === 'number' &&
                    typeof progress.processed === 'number' &&
                    typeof progress.total === 'number') {

                    const percentageText = progress.percentage.toFixed(2) + '%';
                    const percentageWidth = progress.percentage + '%';

                    fileProgressElement.find('.file-percentage').text(percentageText);
                    fileProgressElement.find('.progress').css('width', percentageWidth);
                    fileProgressElement.find('.processed-count').text(progress.processed);
                    fileProgressElement.find('.total-count').text(progress.total);
                } else {
                    console.warn('Invalid progress data for file:', file, progress);
                }
            }
        }
    }

    $('#stop-migration').on('click', function () {
        jQuery.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'stop_migration',
                nonce: migrationAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#stop-migration').hide();
                    $('#resume-migration').show();
                    alert('Migration stopped.');
                } else {
                    handleWPError(response);
                }
            },
            error: handleAjaxError
        });
    });

    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error Status:', status);
        console.error('Error:', error);

        try {
            const response = JSON.parse(xhr.responseText);
            console.error('Response:', response);
        } catch (e) {
            console.error('Raw Response:', xhr.responseText);
        }
    }

    function handleWPError(response) {
        // Create or get error notification container
        if (!$('#error-notification').length) {
            $('<div/>', {
                id: 'error-notification',
                class: 'error-notification',
                css: {
                    display: 'none',
                    backgroundColor: '#ff5252',
                    color: 'white',
                    padding: '10px 15px',
                    borderRadius: '4px',
                    marginTop: '10px',
                    marginBottom: '10px',
                    fontSize: '14px',
                    position: 'relative'
                }
            }).insertAfter($('#migration-log'));

            // Add CSS for fade animation and image queue progress
            $('<style>')
                .text(`
            .error-notification {
                opacity: 1;
                transition: opacity 0.3s ease-in-out;
            }
            .error-notification.fade-out {
                opacity: 0;
            }
            .image-queue-progress {
                border-left: 4px solid #0073aa;
                background: #f9f9f9;
                margin: 5px 0;
                padding: 10px;
            }
            .image-queue-progress.status-ongoing {
                border-left-color: #ffb900;
                background: #fff8e1;
            }
            .image-queue-progress.status-finished {
                border-left-color: #00a32a;
                background: #f0f8f0;
            }
            .image-queue-progress.status-error {
                border-left-color: #d63638;
                background: #fff0f0;
            }
            .queue-status {
                font-size: 0.9em;
                color: #666;
                font-weight: normal;
            }
            .queue-info {
                font-size: 0.9em;
                color: #666;
            }
        `)
                .appendTo('head');
        }

        let errorMessage = '';

        // Handle the response based on its structure
        if (Array.isArray(response.data)) {
            // Handle WP_Error case where data is array of error objects
            errorMessage = response.data.map(err =>
                `Error ${err.code}: ${err.message}`
            ).join('<br>');
        } else {
            // Handle simple error message case
            errorMessage = response.data || 'An unknown error occurred';
        }

        // Update both logs
        $('#migration-log').html(errorMessage);

        const $notification = $('#error-notification');
        $notification
            .html(errorMessage)
            .fadeIn(300);

        // Only set timeout if user is still on page
        let timeoutId = setTimeout(() => {
            if (document.hasFocus()) {
                $notification
                    .addClass('fade-out')
                    .fadeOut(300, function () {
                        $(this).removeClass('fade-out');
                    });
            }
        }, 15000);

        // Clear timeout if user navigates away
        $(window).on('beforeunload', function () {
            clearTimeout(timeoutId);
        });
    }

    $('#resume-migration').on('click', function () {
        $('#resume-migration').hide();
        $('#stop-migration').show();
        startProgressCheck();
    });

    initializeMigrationWizard();
    initializeAdvancedCleanup(); // Inizializzazione cleanup
    
    // ========================
    // CLEANUP BUTTON HANDLERS
    // ========================
    
    // Seleziona tutto
    $('#select-all-cleanup').on('click', function() {
        $('.cleanup-checkbox-label input[type="checkbox"]').prop('checked', true);
        updateTotalCount();
    });
    
    // Deseleziona tutto
    $('#deselect-all-cleanup').on('click', function() {
        $('.cleanup-checkbox-label input[type="checkbox"]').prop('checked', false);
        updateTotalCount();
    });
    
    // Solo post (no immagini)
    $('#select-only-posts').on('click', function() {
        $('.cleanup-checkbox-label input[type="checkbox"]').prop('checked', false);
        $('#cleanup-necrologi, #cleanup-trigesimi, #cleanup-anniversari, #cleanup-ringraziamenti, #cleanup-manifesti').prop('checked', true);
        updateTotalCount();
    });
    
    // Solo immagini
    $('#select-only-images').on('click', function() {
        $('.cleanup-checkbox-label input[type="checkbox"]').prop('checked', false);
        $('#cleanup-images-necrologi, #cleanup-images-trigesimi, #cleanup-images-anniversari, #cleanup-images-general').prop('checked', true);
        updateTotalCount();
    });
    
    // Aggiorna conteggio quando si cambiano le selezioni
    $('.cleanup-checkbox-label input[type="checkbox"]').on('change', function() {
        updateTotalCount();
    });

    function updateTotalCount() {
        let total = 0;
        $('.cleanup-checkbox-label input[type="checkbox"]:checked').each(function() {
            const id = $(this).attr('id');
            const countElement = $('#count-' + id.replace('cleanup-', '').replace('_', '-'));
            if (countElement.length) {
                total += parseInt(countElement.text()) || 0;
            }
        });
        $('#count-total').text(total);
    }

    // Debug: Check if migrationAjax is available
    console.log('migrationAjax available:', typeof migrationAjax !== 'undefined');
    if (typeof migrationAjax !== 'undefined') {
        console.log('migrationAjax:', migrationAjax);
        
        // Initialize Image Download Controls only if migrationAjax is available
        initializeImageDownloadControls();
    } else {
        console.log('Skipping image download controls initialization - migrationAjax not available on this page');
        // Hide the image download control panel if it exists
        $('#image-download-status').parent().hide();
    }

});

function skip_changed(checkBox) {
    let fileInput = document.querySelector('.filename');

    if (checkBox.checked) {
        //query selector for file input with class filename

        fileInput.disabled = true;

        //change submit button text to "Skip this step"
        //query selector for submit button
        let submitButton = document.querySelector('#submit-button');
        submitButton.value = 'Skip this step';

    }else{
        fileInput.disabled = false;

        let submitButton = document.querySelector('#submit-button');
        submitButton.value = 'Upload and Migrate';
    }
}

// ========================
// ADVANCED CLEANUP SYSTEM
// ========================

// Variabili globali per cleanup
let cleanupInProgress = false;
let cleanupSteps = [];
let currentCleanupStep = 0;
let totalCleanupItems = 0;
let processedCleanupItems = 0;

// Inizializzazione Advanced Cleanup già gestita nella ready principale

function initializeAdvancedCleanup() {
    // Event handler per apertura modale
    jQuery('#advanced-cleanup-migration').on('click', function(e) {
        e.preventDefault();
        openAdvancedCleanupModal();
    });
    
    // Event handler per chiusura modale
    jQuery('.advanced-cleanup-close, #cancel-advanced-cleanup, #close-cleanup-modal').on('click', function() {
        closeAdvancedCleanupModal();
    });
    
    // Event handler per overlay click
    jQuery('#advanced-cleanup-modal-overlay').on('click', function() {
        closeAdvancedCleanupModal();
    });
    
    // Event handler per validazione form
    jQuery('#backup-confirmed-advanced, #delete-confirmed-advanced, #images-loss-confirmed, #delete-confirmation-text').on('change keyup', function() {
        validateCleanupForm();
    });
    
    // Event handler per cambio data
    jQuery('#cleanup-cutoff-date').on('change', function() {
        loadCleanupItemCounts();
    });
    
    
    // Event handler per avvio cleanup
    jQuery('#start-advanced-cleanup').on('click', function() {
        startAdvancedCleanup();
    });
    
    // Event handler per stop emergenza
    jQuery('#emergency-stop-cleanup').on('click', function() {
        stopEmergencyCleanup();
    });
}

function openAdvancedCleanupModal() {
    console.log('Opening advanced cleanup modal');
    
    // Reset modal state
    resetCleanupModal();
    
    // Show modal
    jQuery('#advanced-cleanup-modal-overlay').fadeIn(300);
    jQuery('#advanced-cleanup-modal').fadeIn(300);
    
    // Load item counts
    loadCleanupItemCounts();
}

function closeAdvancedCleanupModal() {
    if (cleanupInProgress) {
        if (!confirm('La pulizia è in corso. Sei sicuro di voler chiudere?')) {
            return;
        }
        stopEmergencyCleanup();
    }
    
    jQuery('#advanced-cleanup-modal').fadeOut(300);
    jQuery('#advanced-cleanup-modal-overlay').fadeOut(300);
    
    setTimeout(() => {
        resetCleanupModal();
    }, 300);
}

function resetCleanupModal() {
    // Reset form
    jQuery('#backup-confirmed-advanced, #delete-confirmed-advanced, #images-loss-confirmed').prop('checked', false);
    jQuery('#delete-confirmation-text').val('');
    jQuery('#start-advanced-cleanup').prop('disabled', true);
    
    // Reset progress
    cleanupInProgress = false;
    currentCleanupStep = 0;
    totalCleanupItems = 0;
    processedCleanupItems = 0;
    
    // Show confirmation step, hide others
    jQuery('#backup-confirmation-step').show();
    jQuery('#cleanup-progress-step').hide();
    jQuery('#cleanup-complete-step').hide();
    
    // Clear logs
    jQuery('#cleanup-log-advanced').empty();
}

function validateCleanupForm() {
    const backupConfirmed = jQuery('#backup-confirmed-advanced').is(':checked');
    const deleteConfirmed = jQuery('#delete-confirmed-advanced').is(':checked');
    const imagesConfirmed = jQuery('#images-loss-confirmed').is(':checked');
    const textConfirmed = jQuery('#delete-confirmation-text').val().toUpperCase() === 'DELETE';
    
    const allConfirmed = backupConfirmed && deleteConfirmed && imagesConfirmed && textConfirmed;
    
    jQuery('#start-advanced-cleanup').prop('disabled', !allConfirmed);
    
    // Visual feedback
    if (allConfirmed) {
        jQuery('#start-advanced-cleanup').removeClass('button-secondary').addClass('button-primary');
    } else {
        jQuery('#start-advanced-cleanup').removeClass('button-primary').addClass('button-secondary');
    }
}

function loadCleanupItemCounts() {
    console.log('Loading cleanup item counts...');
    
    // Ottieni la data di cutoff se presente
    const cutoffDate = jQuery('#cleanup-cutoff-date').val();
    
    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'bulk_cleanup_migration',
            nonce: migrationAjax.nonce,
            step: 'count',
            cutoff_date: cutoffDate || ''
        },
        success: function(response) {
            if (response.success) {
                const counts = response.data.counts;
                const total = response.data.total;
                
                // Aggiorna i contatori nel modale
                jQuery('#count-ringraziamenti').text(counts.ringraziamenti || 0);
                jQuery('#count-anniversari').text(counts.anniversari || 0);
                jQuery('#count-trigesimi').text(counts.trigesimi || 0);
                jQuery('#count-manifesti').text(counts.manifesti || 0);
                jQuery('#count-necrologi').text(counts.necrologi || 0);
                jQuery('#count-images-necrologi').text(counts.images_necrologi || 0);
                jQuery('#count-images-trigesimi').text(counts.images_trigesimi || 0);
                jQuery('#count-images-anniversari').text(counts.images_anniversari || 0);
                jQuery('#count-images-general').text(counts.images_general || 0);
                jQuery('#count-total').text(total || 0);
                
                // Salva i dati per il cleanup
                cleanupSteps = response.data.steps;
                totalCleanupItems = total;
                
                console.log('Loaded counts:', counts, 'Total:', total);
            } else {
                console.error('Error loading counts:', response.data);
                alert('Errore nel caricamento dei contatori: ' + (response.data || 'Errore sconosciuto'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error loading counts:', error);
            alert('Errore di comunicazione nel caricamento dei contatori.');
        }
    });
}

function startAdvancedCleanup() {
    console.log('Starting advanced cleanup...');
    
    // Raccogliere selezioni utente
    const selectedItems = [];
    
    if (jQuery('#cleanup-necrologi').is(':checked')) selectedItems.push('necrologi');
    if (jQuery('#cleanup-trigesimi').is(':checked')) selectedItems.push('trigesimi');
    if (jQuery('#cleanup-anniversari').is(':checked')) selectedItems.push('anniversari');
    if (jQuery('#cleanup-ringraziamenti').is(':checked')) selectedItems.push('ringraziamenti');
    if (jQuery('#cleanup-manifesti').is(':checked')) selectedItems.push('manifesti');
    if (jQuery('#cleanup-images-necrologi').is(':checked')) selectedItems.push('images_necrologi');
    if (jQuery('#cleanup-images-trigesimi').is(':checked')) selectedItems.push('images_trigesimi');
    if (jQuery('#cleanup-images-anniversari').is(':checked')) selectedItems.push('images_anniversari');
    if (jQuery('#cleanup-images-general').is(':checked')) selectedItems.push('images_general');
    
    // Validazione
    if (selectedItems.length === 0) {
        alert('Seleziona almeno un elemento da eliminare!');
        return;
    }
    
    // Salvare per uso successivo
    window.selectedCleanupItems = selectedItems;
    console.log('Selected cleanup items:', selectedItems);
    
    cleanupInProgress = true;
    currentCleanupStep = 0;
    processedCleanupItems = 0;
    
    // Salva la data di cutoff per uso successivo
    const cutoffDate = jQuery('#cleanup-cutoff-date').val();
    jQuery('#cleanup-cutoff-date').data('saved-date', cutoffDate);
    
    if (cutoffDate) {
        console.log('Cleanup will include all media uploaded after:', cutoffDate);
    }
    
    // Switch to progress step
    jQuery('#backup-confirmation-step').hide();
    jQuery('#cleanup-progress-step').show();
    
    // Reset progress bars
    updateOverallProgress(0);
    updateStepProgress(0, 'Preparazione...');
    
    // Ricarica i conteggi con la data se presente
    loadCleanupItemCounts();
    
    // Start cleanup process after a short delay
    setTimeout(() => processNextCleanupStep(), 500);
}

function processNextCleanupStep() {
    if (!cleanupInProgress) {
        console.log('Cleanup stopped by user');
        return;
    }
    
    const stepNames = ['ringraziamenti', 'anniversari', 'trigesimi', 'manifesti', 'images_necrologi', 'images_trigesimi', 'images_anniversari', 'images_general', 'necrologi'];
    
    if (currentCleanupStep >= stepNames.length) {
        // Cleanup completato
        completeCleanup();
        return;
    }
    
    const stepName = stepNames[currentCleanupStep];
    const stepDisplayName = getStepDisplayName(stepName);
    
    console.log(`Processing cleanup step: ${stepName}`);
    
    updateStepProgress(0, `Eliminazione ${stepDisplayName}...`);
    logCleanupMessage(`🗑️ Iniziando eliminazione ${stepDisplayName}...`);
    
    processCleanupBatch(stepName, 0);
}

function processCleanupBatch(stepName, offset) {
    if (!cleanupInProgress) {
        return;
    }
    
    // Ottieni la data di cutoff salvata
    const cutoffDate = jQuery('#cleanup-cutoff-date').data('saved-date') || '';
    
    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'bulk_cleanup_migration',
            nonce: migrationAjax.nonce,
            step: stepName,
            offset: offset,
            cutoff_date: cutoffDate,
            selected_items: window.selectedCleanupItems || []
        },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                const processed = data.processed || 0;
                const remaining = data.remaining || 0;
                const completed = data.completed || false;
                
                processedCleanupItems += processed;
                
                // Aggiorna progress bars
                const overallProgress = (processedCleanupItems / totalCleanupItems) * 100;
                updateOverallProgress(overallProgress);
                
                const stepDisplayName = getStepDisplayName(stepName);
                
                if (completed) {
                    // Step completato
                    updateStepProgress(100, `${stepDisplayName} completato`);
                    logCleanupMessage(`✅ ${stepDisplayName} eliminati: ${processed} elementi`);
                    
                    // Passa al prossimo step
                    currentCleanupStep++;
                    setTimeout(() => processNextCleanupStep(), 500);
                } else {
                    // Continua con il prossimo batch
                    const stepProgress = remaining > 0 ? ((processed / (processed + remaining)) * 100) : 100;
                    updateStepProgress(stepProgress, `${stepDisplayName}: ${processed} eliminati, ${remaining} rimanenti`);
                    
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => logCleanupMessage(msg));
                    }
                    
                    // Continua con offset 0 (logica "until empty")
                    setTimeout(() => processCleanupBatch(stepName, 0), 100);
                }
            } else {
                console.error('Cleanup batch error:', response.data);
                logCleanupMessage(`❌ Errore: ${response.data || 'Errore sconosciuto'}`);
                
                if (confirm('Si è verificato un errore. Vuoi continuare con il prossimo step?')) {
                    currentCleanupStep++;
                    processNextCleanupStep();
                } else {
                    stopEmergencyCleanup();
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in cleanup batch:', error);
            logCleanupMessage(`❌ Errore di comunicazione: ${error}`);
            
            if (confirm('Errore di comunicazione. Vuoi riprovare?')) {
                setTimeout(() => processCleanupBatch(stepName, offset), 2000);
            } else {
                stopEmergencyCleanup();
            }
        }
    });
}

function completeCleanup() {
    console.log('Cleanup completed successfully');
    
    cleanupInProgress = false;
    
    // Switch to completion step
    jQuery('#cleanup-progress-step').hide();
    jQuery('#cleanup-complete-step').show();
    
    // Mostra statistiche finali
    const statsHtml = `
        <h4>Statistiche Pulizia:</h4>
        <p><strong>Elementi eliminati:</strong> ${processedCleanupItems}</p>
        <p><strong>Tempo totale:</strong> ${getCleanupDuration()}</p>
        <p><strong>Status:</strong> <span style="color: #28a745;">✅ Completato con successo</span></p>
    `;
    
    jQuery('#cleanup-final-stats').html(statsHtml);
    
    logCleanupMessage('🎉 Pulizia completata con successo!');
}

function stopEmergencyCleanup() {
    console.log('Emergency stop requested');
    
    cleanupInProgress = false;
    
    logCleanupMessage('⏹️ Pulizia interrotta dall\'utente');
    
    jQuery('#emergency-stop-cleanup').prop('disabled', true).text('Arresto in corso...');
    
    setTimeout(() => {
        jQuery('#emergency-stop-cleanup').prop('disabled', false).text('🛑 Stop Emergenza');
    }, 2000);
}

function updateOverallProgress(percentage) {
    percentage = Math.min(100, Math.max(0, percentage));
    jQuery('#overall-cleanup-progress-bar').css('width', percentage + '%');
    jQuery('#overall-cleanup-percentage').text(Math.round(percentage) + '%');
}

function updateStepProgress(percentage, title) {
    percentage = Math.min(100, Math.max(0, percentage));
    jQuery('#step-cleanup-progress-bar').css('width', percentage + '%');
    jQuery('#step-cleanup-percentage').text(Math.round(percentage) + '%');
    
    if (title) {
        jQuery('#current-cleanup-step-title').text(title);
    }
}

function logCleanupMessage(message) {
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = `[${timestamp}] ${message}`;
    
    const logContainer = jQuery('#cleanup-log-advanced');
    logContainer.append(`<div>${logEntry}</div>`);
    
    // Auto-scroll to bottom
    logContainer.scrollTop(logContainer[0].scrollHeight);
    
    console.log('Cleanup log:', logEntry);
}

function getStepDisplayName(stepName) {
    const displayNames = {
        'ringraziamenti': 'Ringraziamenti',
        'anniversari': 'Anniversari',
        'trigesimi': 'Trigesimi',
        'manifesti': 'Manifesti',
        'images_necrologi': 'Immagini Annunci di Morte',
        'images_trigesimi': 'Immagini Trigesimi',
        'images_anniversari': 'Immagini Anniversari',
        'images_general': 'Altre Immagini Migrazione',
        'necrologi': 'Necrologi'
    };
    
    return displayNames[stepName] || stepName;
}

function getCleanupDuration() {
    // Placeholder per durata - potresti implementare un timer
    return 'N/A';
}


// ================================
// IMAGE DOWNLOAD CONTROL SYSTEM
// ================================

function initializeImageDownloadControls() {
    // Check if migrationAjax is available before initializing
    if (typeof migrationAjax === 'undefined') {
        console.log('migrationAjax not available, skipping image download controls initialization');
        return;
    }
    
    // Initial status check
    checkImageDownloadStatus();
    
    // Event handlers for buttons
    jQuery('#start-image-downloads').on('click', function() {
        startImageDownloads();
    });

    jQuery('#stop-image-downloads').on('click', function() {
        stopImageDownloads();
    });

    jQuery('#restart-image-downloads').on('click', function() {
        restartImageDownloads();
    });

    jQuery('#refresh-image-status').on('click', function() {
        checkImageDownloadStatus();
    });
    
    // Auto-refresh every 30 seconds when visible
    setInterval(function() {
        if (!jQuery('#progress-container').is(':visible') && typeof migrationAjax !== 'undefined' && migrationAjax) {
            checkImageDownloadStatus();
        }
    }, 30000);
}

function startImageDownloads() {
    if (typeof migrationAjax === 'undefined') {
        alert('Migration AJAX not available');
        return;
    }

    jQuery('#start-image-downloads').prop('disabled', true).text('▶️ Avvio...');

    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'restart_image_downloads',
            nonce: migrationAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                updateImageDownloadStatus('success', '✅ Download immagini avviato con successo!');
                setTimeout(checkImageDownloadStatus, 2000);
            } else {
                updateImageDownloadStatus('error', '❌ Errore nell\'avvio: ' + response.data);
            }
        },
        error: function() {
            updateImageDownloadStatus('error', '❌ Errore di connessione');
        },
        complete: function() {
            jQuery('#start-image-downloads').prop('disabled', false).text('▶️ Avvia Download');
        }
    });
}

function stopImageDownloads() {
    if (typeof migrationAjax === 'undefined' || !migrationAjax) {
        alert('Migration AJAX not available');
        return;
    }
    
    if (!confirm('Sei sicuro di voler fermare il download delle immagini? Il processo può essere riavviato in qualsiasi momento.')) {
        return;
    }

    jQuery('#stop-image-downloads').prop('disabled', true).text('⏹️ Fermando...');

    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'stop_image_downloads',
            nonce: migrationAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                updateImageDownloadStatus('warning', '⏹️ Download immagini fermato con successo');
                setTimeout(checkImageDownloadStatus, 1000);
            } else {
                updateImageDownloadStatus('error', '❌ Errore nel fermare: ' + response.data);
            }
        },
        error: function() {
            updateImageDownloadStatus('error', '❌ Errore di connessione');
        },
        complete: function() {
            $('#stop-image-downloads').prop('disabled', false).text('⏹️ Ferma Download');
        }
    });
}

function restartImageDownloads() {
    if (typeof migrationAjax === 'undefined' || !migrationAjax) {
        alert('Migration AJAX not available');
        return;
    }
    
    if (!confirm('Sei sicuro di voler riavviare il download delle immagini? Questo resetterà il progresso e ricomincerà dall\'inizio.')) {
        return;
    }

    jQuery('#restart-image-downloads').prop('disabled', true).text('🔄 Riavviando...');

    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'restart_image_downloads',
            nonce: migrationAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                updateImageDownloadStatus('success', '🔄 Download immagini riavviato con successo!');
                setTimeout(checkImageDownloadStatus, 2000);
            } else {
                updateImageDownloadStatus('error', '❌ Errore nel riavvio: ' + response.data);
            }
        },
        error: function() {
            updateImageDownloadStatus('error', '❌ Errore di connessione');
        },
        complete: function() {
            jQuery('#restart-image-downloads').prop('disabled', false).text('🔄 Riavvia Download');
        }
    });
}

function checkImageDownloadStatus() {
    if (typeof migrationAjax === 'undefined' || !migrationAjax) {
        console.log('migrationAjax not available, skipping image download status check');
        return;
    }

    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'check_image_download_status',
            nonce: migrationAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                updateImageDownloadUI(response.data);
            } else {
                updateImageDownloadStatus('error', 'Errore nel controllo status: ' + response.data);
            }
        },
        error: function() {
            updateImageDownloadStatus('error', 'Errore di connessione nel controllo status');
        }
    });
}

function updateImageDownloadUI(data) {
    // Update main status message
    let statusClass = 'notice-info';
    let statusMessage = '';
    let showProgress = false;
    
    if (!data.queue_exists) {
        statusClass = 'notice-info';
        statusMessage = 'ℹ️ Nessun file di coda trovato. Le immagini vengono scaricate automaticamente durante la migrazione.';
    } else if (data.status === 'finished') {
        statusClass = 'notice-success';
        statusMessage = '✅ Tutte le immagini sono state scaricate con successo!';
        showProgress = true;
    } else if (data.status === 'ongoing') {
        statusClass = 'notice-warning';
        statusMessage = '⚠️ Download immagini in corso. Controlla i log per i dettagli.';
        showProgress = true;
    } else if (data.status === 'stopped') {
        statusClass = 'notice-warning';
        statusMessage = '⏸️ Download immagini fermato manualmente.';
        showProgress = true;
    } else if (!data.cron_scheduled) {
        statusClass = 'notice-error';
        statusMessage = '❌ Cron job non programmato. Clicca "Avvia Download" per iniziare.';
        showProgress = true;
    } else {
        statusClass = 'notice-info';
        statusMessage = 'ℹ️ Coda pronta per il download. ' + data.total + ' immagini in attesa.';
        showProgress = true;
    }
    
    // Update status display
    $('#image-download-status').removeClass('notice-info notice-warning notice-error notice-success').addClass(statusClass);
    $('#image-download-status p').text(statusMessage);
    
    // Show/hide and update progress bar
    if (showProgress && data.total > 0) {
        $('#image-download-progress').show();
        $('#image-processed-count').text(data.processed);
        $('#image-total-count').text(data.total);
        $('#image-progress-percentage').text(data.percentage.toFixed(1) + '%');
        $('#image-download-progress-bar').css('width', data.percentage + '%');
        $('#image-status-text').text(data.status);
        $('#image-cron-status').text(data.cron_scheduled ? '✅ Attivo' : '❌ Non programmato');
        $('#image-remaining-count').text(data.remaining);
        
        // Show detailed stats
        $('#image-download-stats').show();
        $('#queue-file-status').text(data.queue_exists ? 'Presente' : 'Assente');
        $('#queue-file-size').text(formatBytes(data.file_size));
        $('#last-update-time').text(data.last_update ? formatTime(data.last_update) : 'Mai');
        $('#next-cron-time').text(data.next_cron_time ? formatTime(data.next_cron_time) : 'Non programmato');
        $('#completion-rate').text(data.completion_rate + '%');
    } else {
        $('#image-download-progress').hide();
        $('#image-download-stats').hide();
    }
}

function updateImageDownloadStatus(type, message) {
    const statusClass = 'notice-' + type;
    jQuery('#image-download-status').removeClass('notice-info notice-warning notice-error notice-success').addClass(statusClass);
    jQuery('#image-download-status p').text(message);
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatTime(timestamp) {
    if (!timestamp) return 'N/A';
    const date = new Date(timestamp * 1000);
    return date.toLocaleString('it-IT');
}



