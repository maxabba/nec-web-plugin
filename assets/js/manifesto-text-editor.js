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
                    // Landscape
                    backgroundDiv.style.width = '100%';
                    backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
                } else {
                    // Portrait
                    backgroundDiv.style.height = '400px';
                    backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
                }

                // Calcola i margini in pixel basati sulla percentuale
                marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                // Applica i margini e l'allineamento
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
        document.getElementById('product_id').value = productID;
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

    // Initialize contenteditable div with a <p> if it's empty
    const textEditor = document.getElementById('text-editor');
    const toolbar = document.createElement('div');
    toolbar.className = 'editor-toolbar';
    toolbar.innerHTML = `
                <button type="button" data-command="bold"><b>B</b></button>
                <button type="button" data-command="italic"><i>I</i></button>
                <button type="button" data-command="underline"><u>U</u></button>
            `;
    document.body.appendChild(toolbar);

    function showToolbar(event) {
        const selection = window.getSelection();
        if (selection.rangeCount > 0 && !selection.isCollapsed) {
            const range = selection.getRangeAt(0).getBoundingClientRect();
            toolbar.style.display = 'block';
            toolbar.style.top = `${range.top + window.scrollY - toolbar.offsetHeight - 5}px`;
            toolbar.style.left = `${range.left + window.scrollX + range.width / 2 - toolbar.offsetWidth / 2}px`;

        } else {
            toolbar.style.display = 'none';

        }
    }

    function applyCommand(command) {
        document.execCommand(command, false, null);
    }

    document.addEventListener('mouseup', showToolbar);
    document.addEventListener('touchend', showToolbar);
    toolbar.addEventListener('mousedown', function (event) {
        event.preventDefault();
        applyCommand(event.target.closest('button').getAttribute('data-command'));
        // setTimeout(showToolbar, 50); // Aggiungi un ritardo per permettere alla selezione di stabilizzarsi
    });

    if (textEditor.innerHTML.trim() === '') {
        textEditor.innerHTML = '<p><br></p>';
    }

    // Handle Enter key to create new paragraphs
    textEditor.addEventListener('keypress', function (event) {
        const editorMaxHeight = textEditor.clientHeight;
        if (event.key === 'Enter') {

            //create a p element with a id
            const p = document.createElement('p');
            p.id = 'p' + Math.floor(Math.random() * 1000000);
            //add br to the p element
            p.innerHTML = '<br>';
            textEditor.appendChild(p);

            if (textEditor.scrollHeight > editorMaxHeight) { // 20px buffer for new paragraph
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

    // Convert newlines to <p> tags when submitting the form
    document.getElementById('custom-text-editor-form').addEventListener('submit', function (event) {
        const textEditor = document.getElementById('text-editor');
        const paragraphs = textEditor.innerHTML.split('\n').map(line => `<p>${line}</p>`).join('');
        const hiddenTextarea = document.createElement('textarea');
        hiddenTextarea.name = 'custom_text';
        hiddenTextarea.style.display = 'none';
        hiddenTextarea.value = paragraphs;
        document.getElementById('custom-text-editor-form').appendChild(hiddenTextarea);
    });
});