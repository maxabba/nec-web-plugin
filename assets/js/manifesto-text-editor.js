document.addEventListener('DOMContentLoaded', function () {
    // Font size configuration - unified mapping for all functions
    const FONT_SIZE_CONFIG = {
        horizontal: {
            small: '5cqh',
            medium: '6cqh', // default
            large: '7cqh'
        },
        vertical: {
            small: '2.5cqh',
            medium: '3cqh', // default
            large: '4cqh'
        }
    };
    
    // Helper function to get font size based on aspect ratio and size
    function getFontSize(aspectRatio, size = 'medium') {
        const orientation = aspectRatio > 1 ? 'horizontal' : 'vertical';
        return FONT_SIZE_CONFIG[orientation][size] || FONT_SIZE_CONFIG[orientation].medium;
    }
    
    //versione da uplodare
    var marginTopPx = 0;
    var marginRightPx = 0;
    var marginBottomPx = 0;
    var marginLeftPx = 0;

    function updateEditorBackground(data) {
        const backgroundDiv = document.getElementById('text-editor-background');
        const textEditor = document.getElementById('text-editor');

        if (data.manifesto_background) {
            const img = new Image();
            img.src = data.manifesto_background;
            img.onload = function () {
                const aspectRatio = img.width / img.height;
                backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                
                // Use responsive sizing based on viewport instead of fixed 400px
                const maxHeight = window.innerWidth <= 1024 ? '70%' : '80%';
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

                marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                textEditor.style.paddingTop = `${marginTopPx}px`;
                textEditor.style.paddingRight = `${marginRightPx}px`;
                textEditor.style.paddingBottom = `${marginBottomPx}px`;
                textEditor.style.paddingLeft = `${marginLeftPx}px`;
                textEditor.style.textAlign = data.alignment ? data.alignment : 'left';
                
                // Set dynamic font-size using unified configuration
                const fontSize = getFontSize(aspectRatio, 'medium'); // Use medium as default
                textEditor.style.fontSize = fontSize;
                textEditor.style.lineHeight = '1.2';
            }
        } else {
            backgroundDiv.style.backgroundImage = 'none';
        }
    }

    function updateProductDescription(data) {
        const fallbackContent = document.getElementById('fallback-content');
        const selectedContent = document.getElementById('selected-content');
        const productNameElement = document.getElementById('selected-product-name');
        const shortDescriptionElement = document.getElementById('selected-product-short-description');
        const fullDescriptionElement = document.getElementById('selected-product-full-description');
        const priceElement = document.getElementById('custom-widget-price');

        if (fallbackContent && selectedContent) {
            // Nascondi il contenuto di fallback
            fallbackContent.style.display = 'none';
            
            // Aggiorna i contenuti dinamici
            if (data.product_name && productNameElement) {
                productNameElement.innerHTML = data.product_name;
            }
            
            if (data.product_short_description && shortDescriptionElement) {
                shortDescriptionElement.innerHTML = data.product_short_description;
            }
            
            if (data.product_description && fullDescriptionElement) {
                fullDescriptionElement.innerHTML = data.product_description;
            }
            if (data.product_price && priceElement) {
                priceElement.innerHTML = "Costo: " + '<span class="price-text">' + data.product_price + '</span>';
            }
            
            // Mostra il contenuto selezionato se abbiamo almeno un dato del prodotto
            if (data.product_name || data.product_short_description || data.product_description) {
                selectedContent.style.display = 'block';
            }



        }
    }

    function hideProductDescription() {
        const fallbackContent = document.getElementById('fallback-content');
        const selectedContent = document.getElementById('selected-content');
        
        if (fallbackContent && selectedContent) {
            // Mostra il contenuto di fallback
            fallbackContent.style.display = 'block';
            // Nascondi il contenuto selezionato
            selectedContent.style.display = 'none';
        }
    }

    window.setProductID = function (productID) {
        showthedefault();
        hideProductDescription(); // Nascondi la descrizione precedente
        document.getElementById('product_id').value = productID;
        
        // Ottieni il post_id dal form
        var postIdInput = document.querySelector('input[name="post_id"]');
        var postId = postIdInput ? postIdInput.value : null;
        
        jQuery('.manifesti-container').addClass('hide');
        jQuery('#comments-loader').show();
        jQuery.ajax({
            url: my_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_vendor_data',
                product_id: productID,
                post_id: postId,
            },
            success: function (response) {
                if (response.success) {
                    jQuery('#comments-loader').hide();
                    updateEditorBackground(response.data);
                    updateProductDescription(response.data);
                    jQuery('.manifesti-container').removeClass('hide');
                } else {
                    alert('Errore nel caricamento dei dati del venditore: ' + response.data);
                }
            },
            error: function () {
                alert('Errore nella richiesta AJAX.');
            }
        });
    }

    const textEditor = document.getElementById('text-editor');

    if (textEditor.innerHTML.trim() === '') {
        textEditor.innerHTML = '<p><br></p>';
    }

    // Get the total container height with browser-specific adjustments
    function getContainerHeight() {
        const containerHeight = textEditor.clientHeight;
        const isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
        const isChrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
        
        // Firefox stops at 5 lines (244px) while Chrome allows 6 lines (246px)
        // Extend Firefox's limit to match Chrome's 6-line behavior
        let adjustedHeight = containerHeight;
        
        if (isFirefox) {
            // Add 11px to Firefox to allow one more line (6 lines total)
            adjustedHeight = containerHeight + 11;
        }
        
        console.log('Container height calculation:', {
            containerHeight,
            adjustedHeight,
            browser: isFirefox ? 'Firefox' : (isChrome ? 'Chrome' : 'Other'),
            marginTopPx,
            marginBottomPx,
            note: 'Adjusted to allow max 6 lines consistently'
        });
        
        return adjustedHeight;
    }

    // Handle Enter key to create new paragraphs
    textEditor.addEventListener('keypress', function (event) {
        console.log('KEYPRESS EVENT:', event.key);
        if (event.key === 'Enter') {
            const containerHeight = getContainerHeight();
            console.log('ENTER - containerHeight:', containerHeight, 'scrollHeight before:', textEditor.scrollHeight);
            const p = document.createElement('p');
            p.id = 'p' + Math.floor(Math.random() * 1000000);
            p.innerHTML = '<br>';
            textEditor.appendChild(p);

            if (textEditor.scrollHeight > containerHeight) {
                console.log('ENTER - CONTAINER LIMIT EXCEEDED:', textEditor.scrollHeight, '>', containerHeight);
                event.preventDefault();
                textEditor.removeChild(p);
            } else {
                console.log('ENTER - OK:', textEditor.scrollHeight, '<=', containerHeight);
                textEditor.removeChild(p);
                document.execCommand('formatBlock', false, 'p');
            }
        }
    });

    // Use debounced input event for height check
    let heightCheckTimeout;
    let isProcessingLimit = false;
    
    textEditor.addEventListener('input', function (event) {
        console.log('INPUT EVENT:', event.inputType, 'isProcessingLimit:', isProcessingLimit);
        if (isProcessingLimit) {
            console.log('INPUT - BLOCKED by isProcessingLimit');
            return;
        }
        
        // Clean all inline styles immediately on any input
        cleanAllInlineStyles();
        
        clearTimeout(heightCheckTimeout);
        heightCheckTimeout = setTimeout(() => {
            const containerHeight = getContainerHeight();
            console.log('INPUT - HEIGHT CHECK:', 'scrollHeight:', textEditor.scrollHeight, 'containerHeight:', containerHeight);
            if (textEditor.scrollHeight > containerHeight) {
                console.log('INPUT - LIMIT EXCEEDED, setting isProcessingLimit = true');
                isProcessingLimit = true;
                
                const selection = window.getSelection();
                console.log('INPUT - Selection rangeCount:', selection.rangeCount);
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    const container = range.startContainer;
                    console.log('INPUT - Container type:', container.nodeType, 'TEXT_NODE:', Node.TEXT_NODE);
                    
                    if (container.nodeType === Node.TEXT_NODE) {
                        const offset = range.startOffset;
                        const textContent = container.textContent;
                        console.log('INPUT - Before removal:', 'offset:', offset, 'textLength:', textContent.length, 'text:', textContent.substring(Math.max(0, offset-10), offset+10));
                        if (offset > 0) {
                            container.textContent = textContent.slice(0, offset - 1) + textContent.slice(offset);
                            range.setStart(container, offset - 1);
                            range.collapse(true);
                            selection.removeAllRanges();
                            selection.addRange(range);
                            console.log('INPUT - After removal:', 'newOffset:', offset-1, 'newTextLength:', container.textContent.length);
                        }
                    }
                }
                
                alert('Hai raggiunto il limite massimo di caratteri disponibili.');
                
                setTimeout(() => {
                    console.log('INPUT - Resetting isProcessingLimit = false');
                    isProcessingLimit = false;
                }, 200);
            }
        }, 50);
    });

    document.getElementById('custom-text-editor-form').addEventListener('submit', function (event) {
        const textEditor = document.getElementById('text-editor');
        const paragraphs = textEditor.innerHTML.split('\n').map(line => `<p>${line}</p>`).join('');
        const hiddenTextarea = document.createElement('textarea');
        hiddenTextarea.name = 'custom_text';
        hiddenTextarea.style.display = 'none';
        hiddenTextarea.value = paragraphs;
        document.getElementById('custom-text-editor-form').appendChild(hiddenTextarea);
    });

    function applyCommand(command) {
        document.execCommand(command, false, null);
        // Clean any inline styles that might have been added by execCommand
        setTimeout(() => {
            cleanAllInlineStyles();
        }, 10);
        updateToolbarState();
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
        let pastedData = clipboardData.getData('text/html');
        const plainText = clipboardData.getData('text/plain');
        
        // Check if HTML is from our own editor (contains data-cqh-size)
        const isFromOurEditor = pastedData && pastedData.includes('data-cqh-size');
        
        // If no HTML data, it's from WhatsApp, or it's from our own editor, use plain text
        if (!pastedData || pastedData.includes('WhatsApp') || isFromOurEditor || plainText.includes('\n\n')) {
            // Convert plain text to HTML with proper paragraph structure
            pastedData = convertPlainTextToHTML(plainText);
        }
        
        if (pastedData) {
            // Process and clean the pasted content
            const cleanedContent = processPastedContent(pastedData);
            
            // Handle insertion and paragraph replacement
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                
                // Find the current paragraph containing the cursor
                let currentParagraph = range.startContainer;
                while (currentParagraph && (currentParagraph.nodeType !== Node.ELEMENT_NODE || currentParagraph.tagName !== 'P')) {
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
        }
    });
    
    // Function to convert plain text to HTML with proper paragraphs
    function convertPlainTextToHTML(plainText) {
        if (!plainText) return '';
        
        // First normalize line breaks (Windows \r\n to \n)
        plainText = plainText.replace(/\r\n/g, '\n');
        
        // Check if text has double line breaks (typical paragraph separators)
        let paragraphs;
        if (plainText.includes('\n\n')) {
            // Split by double line breaks for proper paragraphs
            paragraphs = plainText.split(/\n\n+/);
        } else {
            // Split by single line breaks if no double breaks found
            paragraphs = plainText.split(/\n+/);
        }
        
        // Process each paragraph
        const htmlParagraphs = paragraphs.map(para => {
            // Trim whitespace
            para = para.trim();
            if (!para) return '';
            
            // Remove any &nbsp; entities and replace with regular space
            para = para.replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
            
            // Skip empty paragraphs that only had nbsp
            if (!para) return '';
            
            // Escape HTML entities to prevent injection
            para = para.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
            
            return `<p>${para}</p>`;
        }).filter(p => p); // Remove empty entries
        
        // If no paragraphs were created, return empty paragraph
        if (htmlParagraphs.length === 0) {
            return '<p>&nbsp;</p>';
        }
        
        return htmlParagraphs.join('');
    }
    
    // Function to process and clean pasted content
    function processPastedContent(htmlContent) {
        // Create a temporary container to manipulate the pasted content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlContent;
        
        // Remove CSS style definitions that might be included as text
        removeCSSDefinitions(tempDiv);
        
        // Clean the HTML content
        const cleanedDiv = cleanHTMLContent(tempDiv);
        
        // Apply standard font sizes
        applyStandardSizes(cleanedDiv);
        
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
                (text.includes('margin:') || text.includes('font:') || text.includes('px') || text.includes('cqh'))) {
                textNode.remove();
            }
        });
        
        // Also remove style elements
        const styleElements = container.querySelectorAll('style');
        styleElements.forEach(style => style.remove());
    }
    
    // Clean HTML content keeping only bold, italic, underline and proper paragraph structure
    function cleanHTMLContent(container) {
        const cleanedDiv = document.createElement('div');
        
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
                    // Skip spans entirely if they only have font-size or data-cqh-size
                    const hasOnlyFontSize = node.style && node.style.fontSize && 
                                          !node.style.fontWeight && 
                                          !node.style.fontStyle && 
                                          !node.style.textDecoration;
                    const hasDataCqh = node.hasAttribute('data-cqh-size');
                    
                    if (hasOnlyFontSize || hasDataCqh) {
                        // Just process children without the span wrapper
                        for (let child of node.childNodes) {
                            processNode(child, parentElement);
                        }
                        return;
                    }
                    
                    // Keep span only if it has other formatting
                    newElement = document.createElement('span');
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
                    // Add formatting based on original styles (but not font-size)
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
        
        // Process all child nodes
        for (let child of container.childNodes) {
            processNode(child, cleanedDiv);
        }
        
        return cleanedDiv;
    }
    
    // Apply standard cqh font sizes
    function applyStandardSizes(container) {
        // Always use medium as default for pasted content
        const selectedSize = 'medium';
        
        // Get current aspect ratio for font size determination
        const backgroundDiv = document.getElementById('text-editor-background');
        const aspectRatio = backgroundDiv ? backgroundDiv.clientWidth / backgroundDiv.clientHeight : 1;
        
        // Use unified font size configuration
        const defaultFontSize = getFontSize(aspectRatio, selectedSize);
        
        // Apply default font size to all paragraphs - NO inline styles
        const allParagraphs = container.querySelectorAll('p');
        // Don't apply inline styles - let the editor CSS handle font sizing
    }
    
    // Remove ALL inline styles - no exceptions
    function removeUnwantedStyles(container) {
        const allElements = container.querySelectorAll('*');
        
        allElements.forEach(element => {
            // Remove ALL style attributes completely
            element.removeAttribute('style');
            element.removeAttribute('data-cqh-size');
            
            // Also remove class attributes that might contain styling
            if (element.className && (element.className.includes('font') || element.className.includes('size'))) {
                element.removeAttribute('class');
            }
        });
    }
    
    // Aggressive cleanup function that removes ALL inline styles from editor
    function cleanAllInlineStyles() {
        const allElements = textEditor.querySelectorAll('*');
        allElements.forEach(element => {
            // Remove ALL style attributes completely - no exceptions
            element.removeAttribute('style');
            element.removeAttribute('data-cqh-size');
            
            // Remove any classes that might contain font or size styling
            if (element.className && (element.className.includes('font') || element.className.includes('size'))) {
                element.removeAttribute('class');
            }
        });
    }

    updateToolbarState();
    
    // Set up periodic cleanup of inline styles
    setInterval(() => {
        cleanAllInlineStyles();
    }, 2000); // Every 2 seconds
    
    // Clean styles on focus/blur
    textEditor.addEventListener('focus', cleanAllInlineStyles);
    textEditor.addEventListener('blur', cleanAllInlineStyles);
});


