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
            p.innerHTML = '&nbsp;';
            textEditor.appendChild(p);

            if (textEditor.scrollHeight > containerHeight) {
                event.preventDefault();
                textEditor.removeChild(p);
            } else {
                textEditor.removeChild(p);
                document.execCommand('formatBlock', false, 'p');
                // Insert nbsp after creating the paragraph
                document.execCommand('insertHTML', false, '&nbsp;');
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
    
    // Handle paste events to clean and normalize content
    textEditor.addEventListener('paste', function(event) {
        event.preventDefault();
        
        // Get clipboard data
        const clipboardData = event.clipboardData || window.clipboardData;
        const pastedText = clipboardData.getData('text/html') || clipboardData.getData('text/plain');
        
        if (pastedText) {
            // Process and clean the pasted content
            const cleanedContent = processPastedContent(pastedText);
            
            // Handle insertion and paragraph replacement
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                
                // Find the current paragraph containing the cursor
                let currentParagraph = range.startContainer;
                while (currentParagraph && currentParagraph.nodeType !== Node.ELEMENT_NODE || currentParagraph.tagName !== 'P') {
                    currentParagraph = currentParagraph.parentNode;
                    // If we reach the text editor without finding a P, break
                    if (currentParagraph === textEditor) {
                        currentParagraph = null;
                        break;
                    }
                }
                
                // Create a temporary div to parse the cleaned content
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = cleanedContent;
                
                if (currentParagraph) {
                    // If we found a paragraph, replace it with the pasted content
                    const fragment = document.createDocumentFragment();
                    const insertedNodes = [];
                    while (tempDiv.firstChild) {
                        const node = tempDiv.firstChild;
                        insertedNodes.push(node);
                        fragment.appendChild(node);
                    }
                    
                    // Store reference to parent before removing current paragraph
                    const parentElement = currentParagraph.parentNode;
                    
                    // Insert the new content before the current paragraph
                    parentElement.insertBefore(fragment, currentParagraph);
                    
                    // Remove the original paragraph
                    currentParagraph.remove();
                    
                    // Set cursor at the end of the last inserted element
                    if (insertedNodes.length > 0) {
                        const lastInserted = insertedNodes[insertedNodes.length - 1];
                        range.selectNodeContents(lastInserted);
                        range.collapse(false);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    }
                } else {
                    // Fallback: if no paragraph found, insert normally
                    range.deleteContents();
                    const fragment = document.createDocumentFragment();
                    while (tempDiv.firstChild) {
                        fragment.appendChild(tempDiv.firstChild);
                    }
                    range.insertNode(fragment);
                    
                    // Move cursor to end of inserted content
                    range.collapse(false);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
            
            // Sync content after paste
            syncContentToHiddenField();
        }
    });

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
            hiddenField.value = textEditor.innerHTML;
        } else {
            console.error('Hidden field not found: testo_manifesto_hidden');
        }
    }
    
    // Handle form submission via AJAX
    const form = document.getElementById('manifesto-form');
    if (form) {
        console.log('Form found, attaching submit handler');
        
        // Function to handle the AJAX submission
        function handleFormSubmit(event) {
            // Prevent form submission
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            console.log('Form submit intercepted - starting AJAX');

            // Sync content to hidden field
            syncContentToHiddenField();

            // Validate that content exists
            const hiddenField = document.getElementById('testo_manifesto_hidden');
            
            if (!hiddenField) {
                alert('Errore interno: campo nascosto non trovato.');
                return false;
            }
            
            if (!hiddenField.value.trim() || hiddenField.value.trim() === '<p><br></p>') {
                alert('Per favore inserisci del testo nel manifesto prima di salvare.');
                return false;
            }

            // Get post status from inline control - prioritize select value over hidden field
            const postStatusSelect = document.getElementById('post_status_selector');
            const postStatusControl = document.getElementById('acf_post_status_control');
            
            let postStatus;
            if (postStatusSelect) {
                // Use the select value (current user selection)
                postStatus = postStatusSelect.value;
                console.log('Using post status from select:', postStatus);
            } else if (postStatusControl) {
                // Fallback to hidden field value
                postStatus = postStatusControl.value;
                console.log('Using post status from hidden field:', postStatus);
            } else {
                // Final fallback
                postStatus = 'draft';
                console.log('Using default post status: draft');
            }

            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton ? submitButton.textContent : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Salvataggio in corso...';
            }

            // Prepare AJAX data
            const formData = new FormData();
            formData.append('action', 'save_manifesto_ajax');
            formData.append('nonce', acf_ajax_object.nonce);
            formData.append('post_id', acf_ajax_object.post_id);
            formData.append('post_id_annuncio', acf_ajax_object.post_id_annuncio);
            formData.append('testo_manifesto', hiddenField.value);
            formData.append('post_status', postStatus);

            // Debug: Check if we have all required data
            console.log('AJAX object content:', acf_ajax_object);
            console.log('Sending AJAX request with data:', {
                action: 'save_manifesto_ajax',
                post_id: acf_ajax_object.post_id,
                post_id_annuncio: acf_ajax_object.post_id_annuncio,
                ajax_url: acf_ajax_object.ajax_url
            });
            
            // Validate required data before sending
            if (!acf_ajax_object.post_id_annuncio) {
                alert('Errore: post_id_annuncio mancante. Verifica che l\'URL contenga il parametro post_id_annuncio.');
                console.error('post_id_annuncio is missing from acf_ajax_object');
                return false;
            }

            // Send AJAX request
            fetch(acf_ajax_object.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('AJAX response:', data);
                if (data.success) {
                    // Success - redirect to the provided URL or default
                    if (data.data.redirect_url) {
                        window.location.href = data.data.redirect_url;
                    } else {
                        window.location.href = acf_ajax_object.redirect_to;
                    }
                } else {
                    // Error - show message
                    alert(data.data.message || data.data || 'Errore durante il salvataggio del manifesto');
                    
                    // Re-enable button
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                alert('Errore di connessione. Per favore riprova.');
                
                // Re-enable button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            });
            
            return false; // Extra safety to prevent form submission
        }
        
        // Attach the handler to form submit event
        form.addEventListener('submit', handleFormSubmit);
        
        // Also attach to button click as a backup
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                handleFormSubmit(event);
            });
        }
    } else {
        console.error('Form with ID "manifesto-form" not found!');
    }

    // Font size selector event listener - now for selected text
    const fontSizeSelector = document.getElementById('font-size-selector');
    if (fontSizeSelector) {
        fontSizeSelector.addEventListener('change', function() {
            const selection = window.getSelection();
            if (selection.rangeCount > 0 && !selection.isCollapsed) {
                // Apply font size to selected text
                const selectedSize = fontSizeSelector.value;
                let fontSize;
                
                // Get current aspect ratio
                const backgroundDiv = document.getElementById('text-editor-background');
                const aspectRatio = backgroundDiv.clientWidth / backgroundDiv.clientHeight;
                
                // Determine font size based on selection and orientation
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
                    // Vertical image
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
                }
                
                // Apply font size to selection
                document.execCommand('fontSize', false, '7'); // Use a temporary size
                
                // Find and replace the font tags with spans having the correct size
                const fontElements = textEditor.querySelectorAll('font[size="7"]');
                fontElements.forEach(font => {
                    const span = document.createElement('span');
                    span.style.fontSize = fontSize;
                    span.innerHTML = font.innerHTML;
                    font.parentNode.replaceChild(span, font);
                });
                
                syncContentToHiddenField();
            } else {
                // No selection - apply to all paragraphs as before
                const data = acf_ajax_object.user_data;
                if (data.manifesto_background) {
                    const img = new Image();
                    img.src = data.manifesto_background;
                    img.onload = function () {
                        const aspectRatio = img.width / img.height;
                        updateFontSizeFromSelector(aspectRatio);
                    };
                }
            }
        });
    }

    // Function to process and clean pasted content
    function processPastedContent(htmlContent) {
        // Create a temporary container to manipulate the pasted content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlContent;
        
        // Remove CSS style definitions that might be included as text
        removeCSSDefinitions(tempDiv);
        
        // Extract font sizes from the content before cleaning
        const fontSizes = extractFontSizes(tempDiv);
        const normalizedSizes = normalizeFontSizes(fontSizes);
        
        // Clean the HTML content
        const cleanedDiv = cleanHTMLContent(tempDiv);
        
        // Apply normalized font sizes
        applyNormalizedSizes(cleanedDiv, normalizedSizes);
        
        // Remove alignments and other unwanted styles
        removeUnwantedStyles(cleanedDiv);
        
        return cleanedDiv.innerHTML;
    }
    
    // Remove CSS style definitions from text content
    function removeCSSDefinitions(container) {
        // Find and remove text nodes that contain CSS definitions
        const walker = document.createTreeWalker(
            container,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }
        
        textNodes.forEach(textNode => {
            const text = textNode.textContent;
            // Check if text looks like CSS (contains { } and style properties)
            if (text.includes('{') && text.includes('}') && 
                (text.includes('margin:') || text.includes('font:') || text.includes('px'))) {
                textNode.remove();
            }
        });
        
        // Also remove style elements
        const styleElements = container.querySelectorAll('style');
        styleElements.forEach(style => style.remove());
    }
    
    // Extract all font sizes from the content - improved version
    function extractFontSizes(container) {
        const fontSizes = [];
        
        // First, parse CSS definitions in text content to extract font sizes
        const textContent = container.textContent || container.innerText || '';
        const cssMatches = textContent.match(/font:\s*(\d+(?:\.\d+)?)px/g);
        if (cssMatches) {
            cssMatches.forEach(match => {
                const size = parseFloat(match.match(/(\d+(?:\.\d+)?)px/)[1]);
                if (size && size > 0) {
                    fontSizes.push(size);
                }
            });
        }
        
        // Also check for font-size property specifically
        const fontSizeMatches = textContent.match(/font-size:\s*(\d+(?:\.\d+)?)px/g);
        if (fontSizeMatches) {
            fontSizeMatches.forEach(match => {
                const size = parseFloat(match.match(/(\d+(?:\.\d+)?)px/)[1]);
                if (size && size > 0) {
                    fontSizes.push(size);
                }
            });
        }
        
        // Then check all elements for inline styles and computed styles
        const elementsWithFontSize = container.querySelectorAll('*');
        elementsWithFontSize.forEach(element => {
            // Check inline styles first
            if (element.style && element.style.fontSize) {
                const fontSize = parseFloat(element.style.fontSize);
                if (fontSize && fontSize > 0) {
                    fontSizes.push(fontSize);
                }
            }
            
            // Check computed styles
            try {
                const style = window.getComputedStyle ? window.getComputedStyle(element) : element.currentStyle;
                if (style && style.fontSize) {
                    const fontSize = parseFloat(style.fontSize);
                    if (fontSize && fontSize > 0 && fontSize !== 16) { // Ignore default browser font size
                        fontSizes.push(fontSize);
                    }
                }
            } catch (e) {
                // getComputedStyle might fail on detached elements
            }
        });
        
        // Remove duplicates and return unique sizes
        return [...new Set(fontSizes)];
    }
    
    // Calculate average and determine small/medium/large categories
    function normalizeFontSizes(fontSizes) {
        if (fontSizes.length === 0) {
            return { small: [], medium: [], large: [] };
        }
        
        // Calculate average font size
        const average = fontSizes.reduce((sum, size) => sum + size, 0) / fontSizes.length;
        
        // Define thresholds based on average
        const smallThreshold = average * 0.8;  // 20% below average
        const largeThreshold = average * 1.2;  // 20% above average
        
        // Categorize font sizes
        const categories = { small: [], medium: [], large: [] };
        
        fontSizes.forEach(size => {
            if (size < smallThreshold) {
                categories.small.push(size);
            } else if (size > largeThreshold) {
                categories.large.push(size);
            } else {
                categories.medium.push(size);
            }
        });
        
        return {
            smallSizes: [...new Set(categories.small)],
            mediumSizes: [...new Set(categories.medium.concat(average))], // Include average in medium
            largeSizes: [...new Set(categories.large)]
        };
    }
    
    // Clean HTML content keeping only bold, italic, underline and proper paragraph structure
    function cleanHTMLContent(container) {
        const cleanedDiv = document.createElement('div');
        
        // First, flatten nested paragraphs and handle structure
        const flattenedContent = flattenParagraphStructure(container);
        
        function processNode(node, parentElement) {
            if (node.nodeType === Node.TEXT_NODE) {
                // Handle text nodes
                const text = node.textContent.trim();
                if (text !== '') {
                    parentElement.appendChild(document.createTextNode(node.textContent));
                }
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.tagName.toLowerCase();
                let newElement = null;
                
                // Only keep allowed formatting tags
                if (tagName === 'b' || tagName === 'strong') {
                    newElement = document.createElement('b');
                } else if (tagName === 'i' || tagName === 'em') {
                    newElement = document.createElement('i');
                } else if (tagName === 'u') {
                    newElement = document.createElement('u');
                } else if (tagName === 'span' || tagName === 'font') {
                    // Keep spans and fonts for font size processing, but clean them
                    newElement = document.createElement('span');
                    
                    // Preserve font size information temporarily for processing
                    if (node.style && node.style.fontSize) {
                        newElement.setAttribute('data-original-font-size', node.style.fontSize);
                    }
                } else if (tagName === 'p' || tagName === 'div') {
                    newElement = document.createElement('p');
                } else if (tagName === 'br') {
                    newElement = document.createElement('br');
                } else {
                    // For other tags, just process children without the container
                    for (let child of node.childNodes) {
                        processNode(child, parentElement);
                    }
                    return;
                }
                
                if (newElement) {
                    // Add formatting based on original styles
                    if ((node.style.fontWeight === 'bold' || node.style.fontWeight === '700') && newElement.tagName !== 'B') {
                        const boldElement = document.createElement('b');
                        newElement.appendChild(boldElement);
                        newElement = boldElement;
                    }
                    if ((node.style.fontStyle === 'italic') && newElement.tagName !== 'I') {
                        const italicElement = document.createElement('i');
                        newElement.appendChild(italicElement);
                        newElement = italicElement;
                    }
                    if ((node.style.textDecoration?.includes('underline')) && newElement.tagName !== 'U') {
                        const underlineElement = document.createElement('u');
                        newElement.appendChild(underlineElement);
                        newElement = underlineElement;
                    }
                    
                    // Process children
                    for (let child of node.childNodes) {
                        processNode(child, newElement);
                    }
                    
                    parentElement.appendChild(newElement);
                }
            }
        }
        
        // Process all child nodes from flattened content
        for (let child of flattenedContent.childNodes) {
            processNode(child, cleanedDiv);
        }
        
        return cleanedDiv;
    }
    
    // Helper function to flatten nested paragraph structure
    function flattenParagraphStructure(container) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = container.innerHTML;
        
        // Find all nested paragraphs and flatten them
        const nestedParagraphs = tempDiv.querySelectorAll('p p');
        nestedParagraphs.forEach(nestedP => {
            // Move the nested paragraph content to top level
            const parentP = nestedP.closest('p');
            const clone = nestedP.cloneNode(true);
            
            // Insert the flattened paragraph after the parent
            parentP.parentNode.insertBefore(clone, parentP.nextSibling);
        });
        
        // Remove now-empty nested paragraphs
        const emptyNestedPs = tempDiv.querySelectorAll('p p');
        emptyNestedPs.forEach(p => p.remove());
        
        // Handle empty paragraphs - convert to <br> if they're separators
        const allParagraphs = tempDiv.querySelectorAll('p');
        allParagraphs.forEach(p => {
            const hasContent = p.textContent.trim() !== '' || p.querySelector('span, b, i, u, strong, em');
            if (!hasContent) {
                const br = document.createElement('br');
                p.parentNode.replaceChild(br, p);
            }
        });
        
        return tempDiv;
    }
    
    // Apply normalized font sizes based on current editor orientation
    function applyNormalizedSizes(container, normalizedSizes) {
        // Get current aspect ratio for font size determination
        const backgroundDiv = document.getElementById('text-editor-background');
        const aspectRatio = backgroundDiv ? backgroundDiv.clientWidth / backgroundDiv.clientHeight : 1;
        
        // Define our standard font sizes based on orientation
        let sizes;
        if (aspectRatio > 1) {
            // Horizontal image
            sizes = { small: '6cqh', medium: '7cqh', large: '8cqh' };
        } else {
            // Vertical image
            sizes = { small: '3cqh', medium: '3.5cqh', large: '4cqh' };
        }
        
        // Create a mapping of original sizes to normalized categories
        const sizeMapping = {};
        
        // Determine size categories based on normalized sizes
        if (normalizedSizes.small && normalizedSizes.small.length > 0) {
            normalizedSizes.small.forEach(size => {
                sizeMapping[size] = sizes.small;
            });
        }
        if (normalizedSizes.medium && normalizedSizes.medium.length > 0) {
            normalizedSizes.medium.forEach(size => {
                sizeMapping[size] = sizes.medium;
            });
        }
        if (normalizedSizes.large && normalizedSizes.large.length > 0) {
            normalizedSizes.large.forEach(size => {
                sizeMapping[size] = sizes.large;
            });
        }
        
        // Find all elements with original font size data
        const elementsWithSize = container.querySelectorAll('[data-original-font-size]');
        
        elementsWithSize.forEach(element => {
            const originalSize = parseFloat(element.getAttribute('data-original-font-size'));
            element.removeAttribute('data-original-font-size');
            
            // Determine which category this size belongs to
            let targetSize = sizes.medium; // Default to medium
            
            if (normalizedSizes.smallSizes.some(size => Math.abs(size - originalSize) < 2)) {
                targetSize = sizes.small;
            } else if (normalizedSizes.largeSizes.some(size => Math.abs(size - originalSize) < 2)) {
                targetSize = sizes.large;
            }
            
            // Apply the normalized size
            element.style.fontSize = targetSize;
        });
        
        // Handle paragraphs without explicit font size spans
        // These should be treated as having the smallest font size from the extracted sizes
        const paragraphsWithoutSpans = container.querySelectorAll('p');
        paragraphsWithoutSpans.forEach(paragraph => {
            const hasSpanWithSize = paragraph.querySelector('span[style*="font-size"]');
            if (!hasSpanWithSize) {
                // If this paragraph has no font size span, treat it as the smallest size
                const span = document.createElement('span');
                span.style.fontSize = sizes.small; // Default to small for paragraphs without explicit size
                
                // Wrap the paragraph content in the span
                const content = paragraph.innerHTML;
                paragraph.innerHTML = '';
                span.innerHTML = content;
                paragraph.appendChild(span);
            }
        });
        
        // For spans without specific font size, apply medium
        const allSpans = container.querySelectorAll('span');
        allSpans.forEach(span => {
            if (!span.style.fontSize) {
                span.style.fontSize = sizes.medium;
            }
        });
    }
    
    // Remove unwanted styles like text alignment
    function removeUnwantedStyles(container) {
        const allElements = container.querySelectorAll('*');
        
        allElements.forEach(element => {
            // Remove text alignment styles
            element.style.textAlign = '';
            element.style.textIndent = '';
            element.style.textTransform = '';
            
            // Remove positioning and layout styles
            element.style.position = '';
            element.style.float = '';
            element.style.display = '';
            element.style.margin = '';
            element.style.padding = '';
            
            // Remove background and border styles
            element.style.backgroundColor = '';
            element.style.background = '';
            element.style.border = '';
            element.style.borderColor = '';
            element.style.borderWidth = '';
            element.style.borderStyle = '';
            
            // Remove color styles (let editor handle this)
            element.style.color = '';
            
            // Remove line-height and other text spacing
            element.style.lineHeight = '';
            element.style.letterSpacing = '';
            element.style.wordSpacing = '';
            
            // Remove font-family (let editor handle this)
            element.style.fontFamily = '';
            
            // Keep only fontSize, fontWeight, fontStyle, textDecoration for our allowed formatting
            const allowedStyles = ['fontSize'];
            const computedStyle = window.getComputedStyle(element);
            
            // Preserve bold, italic, underline through proper HTML tags instead of styles
            if (computedStyle.fontWeight === 'bold' || computedStyle.fontWeight === '700') {
                if (!element.closest('b, strong')) {
                    const bold = document.createElement('b');
                    element.parentNode.insertBefore(bold, element);
                    bold.appendChild(element);
                }
                element.style.fontWeight = '';
            }
            
            if (computedStyle.fontStyle === 'italic') {
                if (!element.closest('i, em')) {
                    const italic = document.createElement('i');
                    element.parentNode.insertBefore(italic, element);
                    italic.appendChild(element);
                }
                element.style.fontStyle = '';
            }
            
            if (computedStyle.textDecoration && computedStyle.textDecoration.includes('underline')) {
                if (!element.closest('u')) {
                    const underline = document.createElement('u');
                    element.parentNode.insertBefore(underline, element);
                    underline.appendChild(element);
                }
                element.style.textDecoration = '';
            }
        });
    }

    // Initialize everything
    initializeEditor();
    updateToolbarState();
    
    // Sync initial content to hidden field
    syncContentToHiddenField();
});