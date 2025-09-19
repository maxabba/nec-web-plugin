document.addEventListener('DOMContentLoaded', function () {
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
                if (aspectRatio > 1) {
                    backgroundDiv.style.width = '400px';
                    backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
                } else {
                    backgroundDiv.style.height = '400px';
                    backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
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
            }
        } else {
            backgroundDiv.style.backgroundImage = 'none';
        }
    }

    window.setProductID = function (productID) {
        showthedefault();
        document.getElementById('product_id').value = productID;
        jQuery('.manifesti-container').addClass('hide');
        jQuery('#comments-loader').show();
        jQuery.ajax({
            url: my_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_vendor_data',
                product_id: productID,
            },
            success: function (response) {
                if (response.success) {
                    jQuery('#comments-loader').hide();
                    updateEditorBackground(response.data);
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

    updateToolbarState();
});


