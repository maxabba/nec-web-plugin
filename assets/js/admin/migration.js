jQuery(document).ready(function ($) {
    let currentStep = 0;
    let totalSteps = 0;
    let currentFile = '';
    let checkStatusStarted = false;
    
    // Progress monitoring optimization variables
    let progressCheckInterval = null;
    let currentCheckInterval = 1000; // Start with 1 second
    let consecutiveNoChanges = 0;
    let lastProgressData = null;
    let isCheckingStatus = false;
    let connectionRetries = 0;
    let maxRetries = 5;
    let baseRetryDelay = 1000;
    let lastProgressChangeTime = Date.now();
    let stuckDetectionTimeout = 300000; // 5 minutes in milliseconds

    function initializeMigrationWizard() {
        checkStatusImage();
        getNextStep();
    }

    function getNextStep() {
        console.log('Requesting next step. Current step:', currentStep);
        $.ajax({
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

            $.ajax({
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
        
        // Reset monitoring state
        stopProgressChecking();
        connectionRetries = 0;
        lastProgressData = null;
        
        // Start immediate check
        checkStatus();
    }

    function checkStatus() {
        // Prevent overlapping requests
        if (isCheckingStatus) {
            console.log('checkStatus: Already checking, skipping...');
            return;
        }
        
        isCheckingStatus = true;
        console.log('checkStatus called - interval:', currentCheckInterval);

        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            timeout: 30000, // 30 second timeout
            data: {
                action: 'check_migration_status',
                nonce: migrationAjax.nonce
            },
            success: function (response) {
                if (response.success && response.data) {
                    const data = response.data;
                    connectionRetries = 0; // Reset retry counter on success
                    
                    // Check if data has changed to optimize polling
                    const hasDataChanged = hasProgressDataChanged(data);
                    
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

                        // Continue checking if status is 'ongoing'
                        // OR 'completed' (which indicates a batch completed but more work to do)
                        if (fileStatus === 'ongoing') {
                            scheduleNextStatusCheck(hasDataChanged);
                        }
                        else if (fileStatus === 'completed') {
                            // Status 'completed' means a batch finished but migration may continue
                            // Check if we're actually done (percentage >= 100)
                            const percentage = data.progress[currentFile].percentage;
                            if (percentage >= 100) {
                                // If we're at 100%, wait a bit for status to change to 'finished'
                                setTimeout(() => {
                                    scheduleNextStatusCheck(true); // Force check
                                }, 2000);
                            } else {
                                // Still more work to do, continue checking normally
                                scheduleNextStatusCheck(hasDataChanged);
                            }
                        }
                        // Only proceed to next step if status is 'finished'
                        else if (fileStatus === 'finished') {
                            stopProgressChecking();
                            $('#progress-container').hide();
                            $('#stop-migration, #resume-migration').hide();
                            getNextStep();
                        }
                        // If status is undefined or unknown, keep checking
                        else {
                            console.warn('Unknown status for file:', currentFile);
                            scheduleNextStatusCheck(false);
                        }
                    } else {
                        console.error('No progress data for current file:', currentFile);
                        scheduleNextStatusCheck(false);
                    }
                    
                    lastProgressData = JSON.stringify(data);
                } else {
                    handleCheckStatusError(response);
                }
            },
            error: function(xhr, status, error) {
                handleCheckStatusError({error: error, status: status, xhr: xhr});
            },
            complete: function() {
                isCheckingStatus = false;
            }
        });
    }


    function checkStatusImage() {
        console.log('checkStatusImage called');

        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            timeout: 30000, // 30 second timeout
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
                        console.log('Setting timeout for next image status check'); // Debugging message for the timer
                        setTimeout(checkStatusImage, 3000); // 3 seconds interval for image checking
                    } else {
                        console.log('Image migration completed, cleaning up progress elements');
                        // Clean up image progress elements when completed
                        $('.file-progress').fadeOut(2000, function() {
                            $(this).remove();
                        });
                    }

                } else {
                    console.warn('Image status check failed:', response);
                    // Retry after a longer delay on error
                    setTimeout(checkStatusImage, 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error('Image status check error:', status, error);
                // Retry after a longer delay on error
                setTimeout(checkStatusImage, 5000);
            }
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

        // Keep track of active files to clean up old progress bars
        const activeFiles = new Set();

        for (const file in progresses) {
            if (progresses.hasOwnProperty(file)) {
                activeFiles.add(file);
                const progress = progresses[file];
                const fileId = file.replace(/[^a-zA-Z0-9]/g, '-'); // Better sanitization
                let fileProgressElement = $('#file-progress-' + fileId);

                if (fileProgressElement.length === 0) {
                    // Create progress bar if it doesn't exist
                    $('#file-progresses').append(`
                        <div id="file-progress-${fileId}" class="file-progress" data-file="${file}">
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
                    const percentageWidth = Math.min(100, progress.percentage) + '%';

                    fileProgressElement.find('.file-percentage').text(percentageText);
                    fileProgressElement.find('.progress').css('width', percentageWidth);
                    fileProgressElement.find('.processed-count').text(progress.processed);
                    fileProgressElement.find('.total-count').text(progress.total);

                    // Hide completed progress bars after a delay
                    if (progress.percentage >= 100 && progress.status === 'finished') {
                        setTimeout(() => {
                            fileProgressElement.fadeOut(1000, function() {
                                $(this).remove();
                            });
                        }, 2000);
                    }
                } else {
                    console.warn('Invalid progress data for file:', file, progress);
                }
            }
        }

        // Clean up progress bars for files no longer in the progress data
        $('.file-progress').each(function() {
            const fileName = $(this).data('file');
            if (fileName && !activeFiles.has(fileName)) {
                $(this).fadeOut(1000, function() {
                    $(this).remove();
                });
            }
        });
    }

    $('#stop-migration').on('click', function () {
        $.ajax({
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

    // New helper functions for optimized progress monitoring
    function hasProgressDataChanged(newData) {
        if (!lastProgressData) {
            lastProgressChangeTime = Date.now();
            return true;
        }
        
        try {
            const newDataStr = JSON.stringify(newData);
            const hasChanged = lastProgressData !== newDataStr;
            
            if (hasChanged) {
                lastProgressChangeTime = Date.now();
            } else {
                // Check for stuck status
                const timeSinceLastChange = Date.now() - lastProgressChangeTime;
                if (timeSinceLastChange > stuckDetectionTimeout) {
                    console.warn('Progress appears stuck for', Math.round(timeSinceLastChange / 1000), 'seconds');
                    // Force a data change to trigger more aggressive checking
                    lastProgressChangeTime = Date.now() - (stuckDetectionTimeout - 60000); // Reset but keep warning active
                }
            }
            
            return hasChanged;
        } catch (e) {
            console.warn('Error comparing progress data:', e);
            return true;
        }
    }

    function scheduleNextStatusCheck(hasChanged) {
        // Handle forced checks (when hasChanged is explicitly true)
        if (hasChanged === true && typeof hasChanged === 'boolean') {
            consecutiveNoChanges = 0;
            currentCheckInterval = 1000; // Force immediate check for status transitions
        }
        // Adaptive polling: adjust interval based on activity
        else if (hasChanged) {
            consecutiveNoChanges = 0;
            currentCheckInterval = 1000; // Reset to 1 second when there's activity
        } else {
            consecutiveNoChanges++;
            // Gradually increase interval: 1s -> 2s -> 5s -> 10s (max)
            if (consecutiveNoChanges >= 3) {
                currentCheckInterval = Math.min(10000, currentCheckInterval * 2);
            }
        }
        
        // Clear any existing timeout
        if (progressCheckInterval) {
            clearTimeout(progressCheckInterval);
        }
        
        // Schedule next check
        progressCheckInterval = setTimeout(checkStatus, currentCheckInterval);
        console.log('Next status check scheduled in', currentCheckInterval, 'ms', hasChanged === true ? '(FORCED)' : '');
    }

    function stopProgressChecking() {
        if (progressCheckInterval) {
            clearTimeout(progressCheckInterval);
            progressCheckInterval = null;
        }
        isCheckingStatus = false;
        currentCheckInterval = 1000;
        consecutiveNoChanges = 0;
        console.log('Progress checking stopped');
    }

    function handleCheckStatusError(errorData) {
        console.error('Status check error:', errorData);
        
        connectionRetries++;
        
        if (connectionRetries >= maxRetries) {
            console.error('Max retries reached, stopping progress checks');
            stopProgressChecking();
            $('#stop-migration').hide();
            $('#resume-migration').show();
            handleWPError({
                data: 'Connection lost after multiple retries. Click "Resume Migration" to continue.'
            });
            return;
        }
        
        // Exponential backoff for retries
        const retryDelay = baseRetryDelay * Math.pow(2, connectionRetries - 1);
        console.log(`Retrying in ${retryDelay}ms (attempt ${connectionRetries}/${maxRetries})`);
        
        // Clear any existing timeout
        if (progressCheckInterval) {
            clearTimeout(progressCheckInterval);
        }
        
        // Schedule retry with exponential backoff
        progressCheckInterval = setTimeout(checkStatus, retryDelay);
    }

    initializeMigrationWizard();

    // Cleanup Modal Functionality
    let cleanupInProgress = false;
    let cleanupProgress = {
        current_step: '',
        total_items: 0,
        processed_items: 0,
        step_items: 0,
        step_processed: 0
    };
    
    // Parallel processing configuration
    let maxWorkers = 2; // Max concurrent AJAX requests
    let activeWorkers = 0;
    let workerQueue = [];
    let cleanupCurrentStep = '';
    let stepCompleted = false;
    
    // Dynamic performance monitoring
    function getOptimalWorkerCount() {
        // Check memory usage (if available) and performance metrics
        if (performance.memory && performance.memory.usedJSHeapSize) {
            const memoryUsageMB = performance.memory.usedJSHeapSize / 1024 / 1024;
            if (memoryUsageMB > 100) {
                return 1; // Single worker for high memory usage
            }
        }
        return 2; // Default to 2 workers
    }

    // Open cleanup modal - using event delegation
    $(document).on('click', '#cleanup-necrologi', function() {
        $('#cleanup-modal-overlay').show();
        $('#cleanup-modal').show();
    });

    // Close cleanup modal - using event delegation
    $(document).on('click', '.cleanup-close, #cancel-cleanup, #cleanup-modal-overlay', function() {
        if (!cleanupInProgress) {
            $('#cleanup-modal').hide();
            $('#cleanup-modal-overlay').hide();
            resetCleanupModal();
        }
    });

    // Prevent modal close when clicking inside modal content
    $(document).on('click', '#cleanup-modal .cleanup-modal-content', function(e) {
        e.stopPropagation();
    });

    // Enable/disable start button based on checkboxes - using event delegation
    $(document).on('change', '#backup-confirmed, #delete-confirmed', function() {
        const backupConfirmed = $('#backup-confirmed').is(':checked');
        const deleteConfirmed = $('#delete-confirmed').is(':checked');
        $('#start-cleanup').prop('disabled', !(backupConfirmed && deleteConfirmed));
    });

    // Start cleanup process - using event delegation
    $(document).on('click', '#start-cleanup', function() {
        if (cleanupInProgress) return;
        
        startCleanupProcess();
    });

    function startCleanupProcess() {
        cleanupInProgress = true;
        $('#start-cleanup').prop('disabled', true);
        $('#cleanup-progress').show();
        $('.backup-warning').hide();
        
        // First, count items to delete
        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            timeout: 30000, // 30 seconds timeout
            data: {
                action: 'cleanup_all_necrologi',
                nonce: migrationAjax.nonce,
                step: 'count'
            },
            success: function(response) {
                console.log('Count response:', response);
                if (response.success) {
                    cleanupProgress.total_items = response.data.total;
                    addCleanupLogMessage('Elementi da eliminare: ' + cleanupProgress.total_items);
                    if (response.data.counts) {
                        for (const [type, count] of Object.entries(response.data.counts)) {
                            addCleanupLogMessage(`- ${type}: ${count}`);
                        }
                    }
                    updateCleanupProgress('Conteggio completato', 0, cleanupProgress.total_items);
                    
                    // Start with necrologi deletion using sequential processing
                    processCleanupStep('necrologi', 0);
                } else {
                    handleCleanupError('Errore nel conteggio degli elementi: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Count error:', status, error);
                if (status === 'timeout') {
                    handleCleanupError('Timeout durante il conteggio degli elementi');
                } else {
                    handleCleanupError('Errore di comunicazione durante il conteggio: ' + error);
                }
            }
        });
    }

    function startParallelCleanup(step) {
        cleanupCurrentStep = step;
        stepCompleted = false;
        activeWorkers = 0;
        workerQueue = [];
        
        const stepNames = {
            'necrologi': 'Eliminazione necrologi',
            'manifesti': 'Eliminazione manifesti',
            'ricorrenze': 'Eliminazione ricorrenze',
            'ringraziamenti': 'Eliminazione ringraziamenti',
            'images': 'Eliminazione immagini orfane'
        };
        
        cleanupProgress.current_step = stepNames[step] || step;
        
        // Dynamic worker count based on performance
        const optimalWorkers = getOptimalWorkerCount();
        maxWorkers = Math.min(maxWorkers, optimalWorkers);
        
        addCleanupLogMessage(`Iniziando ${cleanupProgress.current_step} con ${maxWorkers} worker paralleli`);
        
        // Start multiple workers if possible
        for (let i = 0; i < maxWorkers; i++) {
            startCleanupWorker(step, i * getBatchSize(step));
        }
    }

    function getBatchSize(step) {
        // Match the PHP batch sizes
        const batchSizes = {
            'necrologi': 100,
            'manifesti': 300,
            'ricorrenze': 300,
            'ringraziamenti': 300,
            'images': 200
        };
        return batchSizes[step] || 100;
    }

    function startCleanupWorker(step, offset) {
        if (stepCompleted || !cleanupInProgress) return;
        
        activeWorkers++;
        
        processCleanupStep(step, offset, function(data, hasMore) {
            activeWorkers--;
            
            if (hasMore && !stepCompleted) {
                // Queue next batch for this worker
                const nextOffset = data.next_offset || (offset + getBatchSize(step));
                setTimeout(() => startCleanupWorker(step, nextOffset), 100); // Small delay to prevent overload
            }
            
            // Check if step is complete (no more workers and no pending work)
            if (activeWorkers === 0 && !hasMore) {
                onStepCompleted(step);
            }
        });
    }

    function onStepCompleted(step) {
        if (stepCompleted) return;
        stepCompleted = true;
        
        addCleanupLogMessage(`✓ ${cleanupProgress.current_step} completato`);
        
        const nextSteps = {
            'necrologi': 'manifesti',
            'manifesti': 'ricorrenze', 
            'ricorrenze': 'ringraziamenti',
            'ringraziamenti': 'images',
            'images': null
        };
        
        const nextStep = nextSteps[step];
        if (nextStep) {
            setTimeout(() => startParallelCleanup(nextStep), 500); // Brief pause between steps
        } else {
            completeCleanup();
        }
    }

    function processCleanupStep(step, offset) {
        if (!cleanupInProgress) return;
        
        const stepNames = {
            'necrologi': 'Eliminazione necrologi',
            'manifesti': 'Eliminazione manifesti',
            'ricorrenze': 'Eliminazione ricorrenze',
            'ringraziamenti': 'Eliminazione ringraziamenti',
            'images': 'Eliminazione immagini orfane'
        };
        
        cleanupProgress.current_step = stepNames[step] || step;
        
        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            timeout: 60000, // 60 seconds timeout for deletion steps
            data: {
                action: 'cleanup_all_necrologi',
                nonce: migrationAjax.nonce,
                step: step,
                offset: offset
            },
            success: function(response) {
                console.log('Step response for', step, ':', response);
                if (response.success) {
                    const data = response.data;
                    const processed = data.processed || 0;
                    cleanupProgress.processed_items += processed;
                    
                    // Update progress with actual numbers
                    updateCleanupProgress(
                        cleanupProgress.current_step, 
                        cleanupProgress.processed_items, 
                        cleanupProgress.total_items
                    );
                    
                    // Add log messages
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(function(message) {
                            addCleanupLogMessage(message);
                        });
                    } else if (processed > 0) {
                        addCleanupLogMessage(`Processati ${processed} elementi in questo batch`);
                    }
                    
                    if (data.completed) {
                        // Verifica finale - conta elementi rimasti
                        verifyStepCompletion(step);
                    } else {
                        // Continua con prossimo batch - sempre offset 0 per logica "until empty"
                        addCleanupLogMessage(`Continuando eliminazione...`);
                        processCleanupStep(step, 0);
                    }
                } else {
                    console.error('Cleanup error response:', response);
                    handleCleanupError('Errore durante ' + cleanupProgress.current_step + ': ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Step error for', step, ':', status, error);
                if (status === 'timeout') {
                    handleCleanupError('Timeout durante ' + cleanupProgress.current_step);
                } else {
                    handleCleanupError('Errore di comunicazione durante ' + cleanupProgress.current_step + ': ' + error);
                }
            }
        });
    }

    function verifyStepCompletion(step) {
        addCleanupLogMessage(`Verifica completamento ${step}...`);
        
        // Richiedi conteggio finale per questo step
        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cleanup_all_necrologi',
                nonce: migrationAjax.nonce,
                step: 'verify_' + step
            },
            success: function(response) {
                if (response.success && response.data.remaining === 0) {
                    addCleanupLogMessage(`✓ ${cleanupProgress.current_step} completato - 0 elementi rimasti`);
                    moveToNextStep(step);
                } else {
                    const remaining = response.data.remaining || 0;
                    addCleanupLogMessage(`Trovati ${remaining} elementi rimasti, continuando...`);
                    processCleanupStep(step, 0); // Riprendi eliminazione
                }
            },
            error: function() {
                // Se verifica fallisce, procedi comunque al prossimo step
                addCleanupLogMessage(`⚠ Verifica fallita, procedendo al prossimo step`);
                moveToNextStep(step);
            }
        });
    }

    function moveToNextStep(step) {
        const nextSteps = {
            'necrologi': 'manifesti',
            'manifesti': 'ricorrenze', 
            'ricorrenze': 'ringraziamenti',
            'ringraziamenti': 'images',
            'images': null
        };
        
        const nextStep = nextSteps[step];
        if (nextStep) {
            setTimeout(() => processCleanupStep(nextStep, 0), 500); // Brief pause between steps
        } else {
            completeCleanup();
        }
    }

    function updateCleanupProgress(stepName, processed, total) {
        const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
        
        $('#cleanup-current-step').text(stepName + ': ' + processed + '/' + total);
        $('#cleanup-progress-bar').css('width', percentage + '%');
        $('#cleanup-percentage').text(percentage + '%');
        
        if (processed > 0) {
            addCleanupLogMessage('Progresso: ' + processed + '/' + total + ' elementi processati (' + percentage + '%)');
        }
    }

    function addCleanupLogMessage(message) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = '[' + timestamp + '] ' + message;
        
        const $logContainer = $('#cleanup-log');
        $logContainer.append('<div>' + logEntry + '</div>');
        $logContainer.scrollTop($logContainer[0].scrollHeight);
    }

    function completeCleanup() {
        cleanupInProgress = false;
        updateCleanupProgress('Pulizia completata', cleanupProgress.total_items, cleanupProgress.total_items);
        addCleanupLogMessage('✓ Pulizia completata con successo!');
        
        // Show close button
        $('<button>', {
            text: 'Chiudi',
            class: 'button button-primary',
            click: function() {
                $('#cleanup-modal').hide();
                $('#cleanup-modal-overlay').hide();
                resetCleanupModal();
                
                // Refresh the page to update any cached data
                window.location.reload();
            }
        }).appendTo('#cleanup-progress');
    }

    function handleCleanupError(errorMessage) {
        cleanupInProgress = false;
        addCleanupLogMessage('✗ ERRORE: ' + errorMessage);
        $('#start-cleanup').prop('disabled', false).text('Riprova');
    }

    function resetCleanupModal() {
        cleanupInProgress = false;
        cleanupProgress = {
            current_step: '',
            total_items: 0,
            processed_items: 0,
            step_items: 0,
            step_processed: 0
        };
        
        $('#backup-confirmed, #delete-confirmed').prop('checked', false);
        $('#start-cleanup').prop('disabled', true).text('🗑️ Inizia Pulizia Completa');
        $('#cleanup-progress').hide();
        $('.backup-warning').show();
        $('#cleanup-log').empty();
        $('#cleanup-progress .button').remove();
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


