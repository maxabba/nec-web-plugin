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
        console.log('checkStatus called');

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

                    // Check if there's progress data for the current file
                    if (data.progress[currentFile]) {
                        const fileStatus = data.progress[currentFile].status;

                        // Continue checking if status is 'ongoing' or 'completed'
                        if (fileStatus === 'ongoing' || fileStatus === 'completed') {
                            setTimeout(checkStatus, 1000);
                        }
                        // Only proceed to next step if status is 'finished'
                        else if (fileStatus === 'finished') {
                            $('#progress-container').hide();
                            $('#stop-migration, #resume-migration').hide();
                            getNextStep();
                        }
                        // If status is undefined or unknown, keep checking
                        else {
                            console.warn('Unknown status for file:', currentFile);
                            setTimeout(checkStatus, 1000);
                        }
                    } else {
                        console.error('No progress data for current file:', currentFile);
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
        console.log('checkStatus called');

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
                        for (const file in data.progress) {
                            if (data.progress[file].percentage <= 100) {
                                // If the progress bar for the file doesn't exist, create it
                                const fileId = file.replace('.', '-');
                                let fileProgressElement = $('#file-progress-' + fileId);
                                if (fileProgressElement.length === 0) {
                                    $('#migration-log').after(`
                                    <div id="file-progress-${fileId}" class="file-progress">
                                        <div class="file-name">${file}: <span class="file-percentage">0%</span></div>
                                        <div class="progress-bar"><div class="progress"></div></div>
                                        <div class="processed-row">Processed: <span class="processed-count">0</span>/<span class="total-count">0</span></div>
                                    </div>
                                `);
                                    fileProgressElement = $('#file-progress-' + fileId);
                                }
                                const progress = data.progress[file];
                                const percentageText = progress.percentage.toFixed(2) + '%';
                                const percentageWidth = progress.percentage + '%';
                                fileProgressElement.find('.file-percentage').text(percentageText);
                                fileProgressElement.find('.progress').css('width', percentageWidth);
                                fileProgressElement.find('.processed-count').text(progress.processed);
                                fileProgressElement.find('.total-count').text(progress.total);
                            }
                        }
                    } else {
                        console.warn('Invalid progress data:', data.progress);
                    }

                    // Check if all files have completed the migration
                    let completed = true;

                    for (const file in data.progress) {
                        if (data.progress[file].percentage < 100) {
                            completed = false;
                            break;
                        }
                    }

                    if (!completed) {
                        console.log('Setting timeout for next status check'); // Debugging message for the timer
                        setTimeout(checkStatusImage, 2000); // 2 seconds interval
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

            // Add CSS for fade animation
            $('<style>')
                .text(`
            .error-notification {
                opacity: 1;
                transition: opacity 0.3s ease-in-out;
            }
            .error-notification.fade-out {
                opacity: 0;
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
    
    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'bulk_cleanup_migration',
            nonce: migrationAjax.nonce,
            step: 'count'
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
                jQuery('#count-images').text(counts.images || 0);
                jQuery('#count-necrologi').text(counts.necrologi || 0);
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
    
    cleanupInProgress = true;
    currentCleanupStep = 0;
    processedCleanupItems = 0;
    
    // Switch to progress step
    jQuery('#backup-confirmation-step').hide();
    jQuery('#cleanup-progress-step').show();
    
    // Reset progress bars
    updateOverallProgress(0);
    updateStepProgress(0, 'Preparazione...');
    
    // Start cleanup process
    processNextCleanupStep();
}

function processNextCleanupStep() {
    if (!cleanupInProgress) {
        console.log('Cleanup stopped by user');
        return;
    }
    
    const stepNames = ['ringraziamenti', 'anniversari', 'trigesimi', 'manifesti', 'images', 'necrologi'];
    
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
    
    jQuery.ajax({
        url: migrationAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'bulk_cleanup_migration',
            nonce: migrationAjax.nonce,
            step: stepName,
            offset: offset
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
        'images': 'Immagini',
        'necrologi': 'Necrologi'
    };
    
    return displayNames[stepName] || stepName;
}

function getCleanupDuration() {
    // Placeholder per durata - potresti implementare un timer
    return 'N/A';
}


