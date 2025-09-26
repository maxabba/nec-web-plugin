document.addEventListener('DOMContentLoaded', function () {
    // Check if acf_ajax_object is available
    if (typeof acf_ajax_object === 'undefined') {
        console.error('acf_ajax_object is not defined! Check wp_localize_script in PHP.');
        return;
    }
    
    // Copy most functionality from manifesto-text-editor.js but adapted for form integration
    var marginTopPx = 0;
    var marginRightPx = 0;
    var marginBottomPx = 0;
    var marginLeftPx = 0;

    const textEditor = document.getElementById('text-editor');
    
    // Ensure editor has content
    if (!textEditor) {
        console.error('Text editor element not found');
        return;
    }
    
    
    // Initialize editor with existing content if any
    if (textEditor.innerHTML.trim() === '' || textEditor.innerHTML.trim() === '<p><br></p>') {
        if (acf_ajax_object.existing_content && acf_ajax_object.existing_content.trim() !== '') {
            textEditor.innerHTML = acf_ajax_object.existing_content;
        } else {
            textEditor.innerHTML = '<p><br></p>';
        }
    }

    // Initialize background and styling
    function initializeEditor() {
        // Show loading indicator initially
        showLoading();
        
        const data = acf_ajax_object.user_data;
        updateEditorBackground(data);
        
        // Hide loading indicator after a short delay to ensure everything is ready
        setTimeout(hideLoading, 500);
    }

    function showLoading() {
        const loadingDiv = document.getElementById('editor-loading');
        const textEditor = document.getElementById('text-editor');
        if (loadingDiv) loadingDiv.style.display = 'block';
        if (textEditor) textEditor.style.opacity = '0.3';
    }

    function hideLoading() {
        const loadingDiv = document.getElementById('editor-loading');
        const textEditor = document.getElementById('text-editor');
        if (loadingDiv) loadingDiv.style.display = 'none';
        if (textEditor) textEditor.style.opacity = '1';
    }

    function updateEditorBackground(data) {
        const backgroundDiv = document.getElementById('text-editor-background');
        const textEditor = document.getElementById('text-editor');

        // Initialize editor immediately with default values
        initializeEditorStyling(textEditor, backgroundDiv, data, 1.4); // Default aspect ratio

        if (data.manifesto_background) {
            // Set background image immediately for faster visual feedback
            backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
            
            // Load image asynchronously to get exact dimensions
            const img = new Image();
            img.onload = function () {
                const aspectRatio = img.width / img.height;
                // Re-initialize with correct aspect ratio
                initializeEditorStyling(textEditor, backgroundDiv, data, aspectRatio);
            };
            img.onerror = function() {
                console.warn('Failed to load background image, using defaults');
            };
            // Load image in background
            img.src = data.manifesto_background;
        } else {
            backgroundDiv.style.backgroundImage = 'none';
        }
    }

    function initializeEditorStyling(textEditor, backgroundDiv, data, aspectRatio) {
        // Use responsive sizing based on viewport
        const maxHeight = window.innerWidth <= 1024 ? '70vh' : '80vh';
        const maxWidth = '100%';
        
        // Set CSS properties for responsive behavior
        backgroundDiv.style.width = maxWidth;
        backgroundDiv.style.maxHeight = maxHeight;
        backgroundDiv.style.height = 'auto';
        
        // Set aspect-ratio CSS property for modern browsers
        if (CSS.supports('aspect-ratio', `${aspectRatio}`)) {
            backgroundDiv.style.aspectRatio = aspectRatio;
        } else {
            // Fallback for older browsers
            if (aspectRatio > 1) {
                backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
            } else {
                backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
            }
        }

        // Calculate margins with fallback for initial load
        const containerHeight = backgroundDiv.clientHeight || 400;
        const containerWidth = backgroundDiv.clientWidth || 600;
        
        marginTopPx = (data.margin_top / 100) * containerHeight;
        marginRightPx = (data.margin_right / 100) * containerWidth;
        marginBottomPx = (data.margin_bottom / 100) * containerHeight;
        marginLeftPx = (data.margin_left / 100) * containerWidth;

        textEditor.style.paddingTop = `${marginTopPx}px`;
        textEditor.style.paddingRight = `${marginRightPx}px`;
        textEditor.style.paddingBottom = `${marginBottomPx}px`;
        textEditor.style.paddingLeft = `${marginLeftPx}px`;
        textEditor.style.textAlign = data.alignment ? data.alignment : 'left';
        
        // Set dynamic font-size for paragraphs based on image orientation and user selection
        updateFontSizeFromSelector(aspectRatio);
    }

    // Function to update font size based on selector value and aspect ratio
    function updateFontSizeFromSelector(aspectRatio) {
        const selector = document.getElementById('font-size-selector');
        const selectedSize = selector ? selector.value : 'medium';
        
        let fontSize;
        
        // Define font sizes based on selection and orientation
        if (aspectRatio > 1) {
            // Horizontal image
            switch (selectedSize) {
                case 'small':
                    fontSize = '6cqh';
                    break;
                case 'large':
                    fontSize = '8cqh';
                    break;
                case 'medium':
                default:
                    fontSize = '7cqh';
            }
        } else {
            switch (selectedSize) {
                case 'small':
                    fontSize = '3cqh';
                    break;
                case 'large':
                    fontSize = '4cqh';
                    break;
                case 'medium':
                default:
                    fontSize = '3.5cqh';
            }
            // Vertical image

        }
        
        
        // Apply font size to all paragraphs
        const paragraphs = textEditor.querySelectorAll('p');
        paragraphs.forEach(p => {
            p.style.fontSize = fontSize;
        });
        
        // Update CSS rule for future paragraphs
        const styleElement = document.getElementById('dynamic-paragraph-style') || document.createElement('style');
        styleElement.id = 'dynamic-paragraph-style';
        styleElement.innerHTML = `.custom-text-editor p { font-size: ${fontSize} !important; }`;
        if (!document.getElementById('dynamic-paragraph-style')) {
            document.head.appendChild(styleElement);
        }
    }


    // Copy height limit functionality from original
    function getContainerHeight() {
        const containerHeight = textEditor.clientHeight;
        const isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
        const isChrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
        
        let adjustedHeight = containerHeight;
        
        if (isFirefox) {
            adjustedHeight = containerHeight + 11;
        }
        
        return adjustedHeight;
    }

    // Handle Enter key to create new paragraphs
    textEditor.addEventListener('keypress', function (event) {
        if (event.key === 'Enter') {
            const containerHeight = getContainerHeight();
            const p = document.createElement('p');
            p.id = 'p' + Math.floor(Math.random() * 1000000);
            p.innerHTML = '<br>';
            textEditor.appendChild(p);

            if (textEditor.scrollHeight > containerHeight) {
                event.preventDefault();
                textEditor.removeChild(p);
            } else {
                textEditor.removeChild(p);
                document.execCommand('formatBlock', false, 'p');
            }
        }
    });

    // Height limit functionality
    let heightCheckTimeout;
    let isProcessingLimit = false;
    
    textEditor.addEventListener('input', function (event) {
        if (isProcessingLimit) {
            return;
        }
        
        // Sync content to hidden field in real-time
        syncContentToHiddenField();
        
        clearTimeout(heightCheckTimeout);
        heightCheckTimeout = setTimeout(() => {
            const containerHeight = getContainerHeight();
            if (textEditor.scrollHeight > containerHeight) {
                isProcessingLimit = true;
                
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    const container = range.startContainer;
                    
                    if (container.nodeType === Node.TEXT_NODE) {
                        const offset = range.startOffset;
                        const textContent = container.textContent;
                        if (offset > 0) {
                            container.textContent = textContent.slice(0, offset - 1) + textContent.slice(offset);
                            range.setStart(container, offset - 1);
                            range.collapse(true);
                            selection.removeAllRanges();
                            selection.addRange(range);
                        }
                    }
                }
                
                alert('Hai raggiunto il limite massimo di caratteri disponibili.');
                
                setTimeout(() => {
                    isProcessingLimit = false;
                }, 200);
            }
        }, 50);
    });

    // Toolbar functionality
    function applyCommand(command) {
        document.execCommand(command, false, null);
        updateToolbarState();
        syncContentToHiddenField(); // Sync after formatting changes
    }

    function updateToolbarState() {
        document.querySelectorAll('.editor-toolbar button').forEach(button => {
            const command = button.getAttribute('data-command');
            const isActive = document.queryCommandState(command);
            if (isActive) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });
    }

    document.querySelectorAll('.editor-toolbar button').forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const command = button.getAttribute('data-command');
            textEditor.focus();
            applyCommand(command);
        });
    });

    textEditor.addEventListener('keyup', updateToolbarState);
    textEditor.addEventListener('mouseup', updateToolbarState);
    textEditor.addEventListener('touchend', updateToolbarState);

    // Function to sync content to hidden ACF field
    function syncToACFField() {
        const acfField = document.querySelector('textarea[name*="testo_manifesto"], input[name*="testo_manifesto"]');
        if (acfField) {
            acfField.value = textEditor.innerHTML;
        }
        // Also try to find by field key
        const acfFieldByKey = document.querySelector('[name*="field_6669ea0bb516e"]');
        if (acfFieldByKey) {
            acfFieldByKey.value = textEditor.innerHTML;
        }
    }

    // Function to sync content to hidden field before form submission
    function syncContentToHiddenField() {
        const hiddenField = document.getElementById('testo_manifesto_hidden');
        if (hiddenField) {
            const content = textEditor.innerHTML;

            //remove style attributes from all tags
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            const elementsWithStyle = tempDiv.querySelectorAll('[style]');
            elementsWithStyle.forEach(el => el.removeAttribute('style'));
            const cleanedContent = tempDiv.innerHTML;


            hiddenField.value = cleanedContent;
            console.log('Synced content to hidden field:', content);
            console.log('Hidden field value:', hiddenField.value);
        } else {
            console.error('Hidden field not found: testo_manifesto_hidden');
        }
    }
    
    // Sync content before form submission
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            // Prevent form submission
            event.preventDefault();

            // Sync content to hidden field
            syncContentToHiddenField();

            // Validate that content exists
            const hiddenField = document.getElementById('testo_manifesto_hidden');
            console.log('Hidden field found:', hiddenField);
            console.log('Hidden field value:', hiddenField ? hiddenField.value : 'FIELD NOT FOUND');
            console.log('Content length:', hiddenField ? hiddenField.value.trim().length : 0);
            
            if (!hiddenField) {
                alert('Errore interno: campo nascosto non trovato.');
                return false;
            }
            
            if (!hiddenField.value.trim() || hiddenField.value.trim() === '<p><br></p>') {
                alert('Per favore inserisci del testo nel manifesto prima di salvare.');
                return false;
            }

            console.log('Form validation passed, submitting form');
            // Submit the form
            form.submit();
        });
    }

    // Font size selector event listener
    const fontSizeSelector = document.getElementById('font-size-selector');
    if (fontSizeSelector) {
        fontSizeSelector.addEventListener('change', function() {
            const data = acf_ajax_object.user_data;
            if (data.manifesto_background) {
                const img = new Image();
                img.src = data.manifesto_background;
                img.onload = function () {
                    const aspectRatio = img.width / img.height;
                    updateFontSizeFromSelector(aspectRatio);
                };
            }
        });
    }

    // Initialize everything
    initializeEditor();
    updateToolbarState();
    
    // Sync initial content to hidden field
    syncContentToHiddenField();
});