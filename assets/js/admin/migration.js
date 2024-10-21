jQuery(document).ready(function ($) {
    let currentStep = 0;
    let totalSteps = 0;
    let currentFile = '';

    function initializeMigrationWizard() {
        checkInitialStatus();
    }

    function checkInitialStatus() {
        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_migration_status',
                nonce: migrationAjax.nonce
            },
            success: function (response) {
                if (response.success && response.data) {
                    const data = response.data;
                    if (data.progress && typeof data.progress === 'object') {
                        let fileToResume = null;
                        for (const file in data.progress) {
                            const progress = data.progress[file];
                            if (progress.status === 'ongoing' ||
                                (progress.status === 'not_started' && progress.processed < progress.total) ||
                                (progress.status === 'completed' && progress.processed < progress.total)) {
                                fileToResume = file;
                                break;
                            }
                        }
                        if (fileToResume) {
                            currentFile = fileToResume;
                            console.log('Resuming migration from file:', fileToResume);
                            getNextStep(fileToResume);
                        } else {
                            getNextStep();
                        }
                    } else {
                        getNextStep();
                    }

                    // Initialize progress display
                    if (typeof data.overall_percentage === 'number') {
                        updateOverallProgress(data.overall_percentage);
                    }
                    if (data.progress) {
                        updateFileProgresses(data.progress);
                    }
                    $('#progress-container').show();
                } else {
                    console.error('Error in initial status check:', response);
                    //alert('Error in initial status check: ' + JSON.stringify(response));
                    getNextStep();
                }
            },
            error: function (xhr, status, error) {
                console.error('Initial status check error:', xhr.responseText);
                alert('Error in initial status check. Details: ' + error);
                getNextStep();
            }
        });
    }

    function getNextStep(specificFile = null) {
        console.log('Requesting next step. Current step:', currentStep, 'Specific file:', specificFile);
        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_next_step',
                nonce: migrationAjax.nonce,
                current_step: currentStep,
                specific_file: specificFile
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
                        initializeStepForm(response.data.current_file);
                        if (specificFile) {
                            startProgressCheck();
                        }
                    }
                } else {
                    console.error('Error fetching next step:', response.data);
                    alert('Error fetching next step: ' + JSON.stringify(response.data));
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error in getNextStep:', error);
                alert('AJAX error in getNextStep: ' + error);
            }
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
                    } else {
                        console.error('Error during file upload:', response);
                        alert('Error during file upload: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX error in form submission:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);

                    let errorMessage = 'An error occurred during migration.';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage += ' Details: ' + xhr.responseJSON.data;
                    } else {
                        errorMessage += ' Details: ' + error;
                    }

                    alert(errorMessage);
                },
                complete: function () {
                    $('#upload-progress').hide();
                    $('#progress-container').show();
                }
            });
        });

        if (!$('#resume-migration').length) {
            $('<button>', {
                id: 'resume-migration',
                text: 'Resume Migration',
                click: function (e) {
                    e.preventDefault();
                    resumeMigration();
                }
            }).insertAfter('#stop-migration');
        }
    }

    function startProgressCheck() {
        console.log('startProgressCheck called');
        $('#progress-container').show();
        checkStatus();
    }

    function checkStatus() {
        console.log('checkStatus called');

        $.ajax({
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

                    if (data.overall_percentage < 99) {
                        setTimeout(checkStatus, 2000); // Check every 2 seconds
                    } else {
                        $('#progress-container').hide();
                        $('#stop-migration, #resume-migration').hide();
                        getNextStep();
                    }
                } else {
                    console.error('Error in status check:', response);
                    alert('Error in status check: ' + JSON.stringify(response));
                    $('#resume-migration').show();
                }
            },
            error: function (xhr, status, error) {
                console.error('Status check error:', xhr.responseText);
                alert('Error in status check. Details: ' + error);
                $('#resume-migration').show();
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
                    console.error('Error stopping migration:', response);
                    alert('Error stopping migration.');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error stopping migration:', error);
                alert('Error stopping migration. Details: ' + error);
            }
        });
    });

    function resumeMigration() {
        $('#resume-migration').hide();
        $('#stop-migration').show();
        startProgressCheck();
    }

    initializeMigrationWizard();

    /*$('#trigger-image-check').on('click', function (e) {
        e.preventDefault();

        var csvFile = $('#csv-file-input').val();

        if (!csvFile) {
            alert('Per favore, inserisci il nome del file CSV.');
            return;
        }

        // Disabilita il pulsante durante l'elaborazione
        $(this).prop('disabled', true).text('Elaborazione in corso...');

        // Crea un elemento per mostrare il progresso
        var progressElement = $('<div id="batch-progress"></div>').insertAfter(this);

        processBatch(csvFile, 0, progressElement);
    });

    function processBatch(csvFile, offset, progressElement) {
        $.ajax({
            url: migrationAjax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'trigger_image_check_and_update',
                csv_file: csvFile,
                offset: offset,
            },
            success: function (response) {
                if (response.success) {
                    var result = response.data;
                    console.log('Processed:', result.processed, 'Total:', result.offset);

                    // Update the progress element
                    progressElement.text('Elaborati: ' + result.offset + ' record');

                    if (!result.complete) {
                        // Continue with the next batch
                        processBatch(csvFile, result.offset, progressElement);
                    } else {
                        progressElement.text('Processo completato. Totale record elaborati: ' + result.offset);
                        alert('Processo completato: ' + result.message);
                        // Re-enable the button
                        $('#trigger-image-check').prop('disabled', false).text('Avvia verifica immagini');
                    }
                } else {
                    // Handle server-side errors
                    var errorMessage = 'Errore: ' + (response.data ? response.data : 'Errore sconosciuto dal server.');
                    handleError(errorMessage, progressElement);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var errorMessage = 'Si è verificato un errore durante la richiesta: ' + textStatus + ' - ' + errorThrown;
                handleError(errorMessage, progressElement);
            }
        });
    }

    function handleError(errorMessage, progressElement) {
        console.error(errorMessage);
        alert(errorMessage);
        // Re-enable the button in case of error
        $('#trigger-image-check').prop('disabled', false).text('Avvia verifica immagini');
        progressElement.text('Processo interrotto a causa di un errore.');
    }*/



});