document.addEventListener('DOMContentLoaded', function () {
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

    // Handle Enter key to create new paragraphs
    textEditor.addEventListener('keypress', function (event) {
        const editorMaxHeight = textEditor.clientHeight;
        if (event.key === 'Enter') {
            const p = document.createElement('p');
            p.id = 'p' + Math.floor(Math.random() * 1000000);
            p.innerHTML = '<br>';
            textEditor.appendChild(p);

            if (textEditor.scrollHeight > editorMaxHeight) {
                event.preventDefault();
                textEditor.removeChild(p);
            } else {
                textEditor.removeChild(p);
                document.execCommand('formatBlock', false, 'p');
            }
        } else {
            if (textEditor.scrollHeight > editorMaxHeight) {
                alert('Il testo è troppo lungo per l\'editor.');
                textEditor.innerHTML = textEditor.innerHTML.substring(0, textEditor.innerHTML.length - 1);
            }
        }
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


