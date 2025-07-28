document.addEventListener('DOMContentLoaded', function () {
    // Elements
    const textEditor = document.getElementById('text-editor');
    const acfTextField = document.querySelector('textarea[data-key="field_6666bf7b5040c"]');
    const backgroundDiv = document.getElementById('text-editor-background');
    let marginTopPx = 0;
    let marginRightPx = 0;
    let marginBottomPx = 0;
    let marginLeftPx = 0;

    // Hide the original ACF field container
    if (acfTextField) {
        acfTextField.closest('.acf-field').style.display = 'none';
    }

    // Update editor background and dimensions
    function updateEditorBackground(data) {
        if (backgroundDiv && textEditor) {
            if (data.manifesto_background) {
                const img = new Image();
                img.src = data.manifesto_background;
                img.onload = function () {
                    const aspectRatio = img.width / img.height;
                    backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';

                    if (aspectRatio > 1) {
                        // Landscape
                        backgroundDiv.style.width = '100%';
                        backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
                    } else {
                        // Portrait
                        backgroundDiv.style.height = '400px';
                        backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
                    }

                    // Calculate margins based on percentage
                    marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                    marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                    marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                    marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                    // Apply margins and alignment
                    textEditor.style.paddingTop = `${marginTopPx}px`;
                    textEditor.style.paddingRight = `${marginRightPx}px`;
                    textEditor.style.paddingBottom = `${marginBottomPx}px`;
                    textEditor.style.paddingLeft = `${marginLeftPx}px`;
                    textEditor.style.textAlign = data.alignment ? data.alignment : 'left';

                    // Set font size relative to container height (about 4% of container height)
                    textEditor.style.fontSize = `${(backgroundDiv.clientHeight / 100) * 4}px`;
                    textEditor.style.fontFamily = "'PlayFair Display Mine', serif";
                }
            } else {
                backgroundDiv.style.backgroundImage = 'none';
            }
        }
    }

    // Get vendor settings and update editor
    function loadVendorSettings() {
        if (window.vendorSettings) {
            updateEditorBackground(window.vendorSettings);
        } else {
            // Fallback to AJAX if settings aren't available
            jQuery.ajax({
                url: my_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_vendor_data',
                    product_id: jQuery('#product_id').val()
                },
                success: function (response) {
                    if (response.success) {
                        updateEditorBackground(response.data);
                    }
                }
            });
        }
    }

    // Initialize editor content
    if (textEditor && acfTextField && acfTextField.value) {
        textEditor.innerHTML = acfTextField.value;
    }

    // Content sync handlers
    if (textEditor) {
        // Handle direct input
        textEditor.addEventListener('input', function () {
            if (acfTextField) {
                acfTextField.value = this.innerHTML;
                const event = new Event('change', {bubbles: true});
                acfTextField.dispatchEvent(event);
            }
        });

        // Format buttons
        document.querySelectorAll('.editor-toolbar button').forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                const command = this.getAttribute('data-command');
                document.execCommand(command, false, null);
                textEditor.focus();
                if (acfTextField) {
                    acfTextField.value = textEditor.innerHTML;
                    const event = new Event('change', {bubbles: true});
                    acfTextField.dispatchEvent(event);
                }
            });
        });

        // Clean paste
        textEditor.addEventListener('paste', function (e) {
            e.preventDefault();
            const text = (e.originalEvent || e).clipboardData.getData('text/plain');
            document.execCommand('insertText', false, text);
        });
    }

    // Window resize handler
    window.addEventListener('resize', function () {
        if (window.vendorSettings) {
            updateEditorBackground(window.vendorSettings);
        }
    });

    // Initial setup
    loadVendorSettings();

    // TinyMCE integration if needed
    function addChangeListenerToTinyMCE() {
        if (typeof tinymce !== 'undefined') {
            tinymce.editors.forEach(function (editor) {
                if (!editor.hasChangeListener) {
                    editor.on('change', function (e) {
                        const content = editor.getContent();
                        if (textEditor) {
                            textEditor.innerHTML = content;
                            if (acfTextField) {
                                acfTextField.value = content;
                                const event = new Event('change', {bubbles: true});
                                acfTextField.dispatchEvent(event);
                            }
                        }
                    });
                    editor.hasChangeListener = true;
                }
            });
        }
    }

    addChangeListenerToTinyMCE();
    setInterval(addChangeListenerToTinyMCE, 1000);
});