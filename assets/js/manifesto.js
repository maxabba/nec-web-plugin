(function ($) {
    $(document).ready(function () {
        $('.manifesto-container').each(function () {
            var container = $(this);
            var post_id = container.data('postid');
            var tipo_manifesto = container.data('tipo');
            var offset = 0;
            var $loader = container.siblings('.manifesto-loader');
            var allDataLoaded = false;

            function updateEditorBackground(data, container) {
                const backgroundDiv = container.find('.text-editor-background').get(0);
                const textEditor = container.find('.custom-text-editor').get(0);

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
                            backgroundDiv.style.height = '350px';
                            backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
                        }

                        // Calcola i margini in pixel basati sulla percentuale
                        const marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                        const marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                        const marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                        const marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

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

            function loadAllManifesti(container) {
                $loader.show();
                (function loadNextBatch() {
                    if (allDataLoaded) {
                        $loader.hide();
                        return;
                    }

                    $.ajax({
                        url: my_ajax_object.ajax_url,
                        type: 'post',
                        data: {
                            action: 'load_more_manifesti',
                            post_id: post_id,
                            tipo_manifesto: tipo_manifesto,
                            offset: offset
                        },
                        success: function (response) {
                            //console.log("Risposta ricevuta:", response); // Log di debug

                            if (!response.success || response.data.length === 0) {
                                //console.log("Nessun dato da caricare."); // Log di debug
                                allDataLoaded = true;
                                $loader.hide();
                                return;
                            }

                            response.data.forEach(function (item) {
                                var newElement = $(item.html);
                                container.parent().parent().parent().parent().find('.manifesto_divider').show();
                                container.append(newElement);
                                console.log(item.vendor_data);
                                updateEditorBackground(item.vendor_data, newElement);
                            });

                            offset += response.data.length; // Incrementa l'offset in base ai dati ricevuti
                            loadNextBatch(); // Carica il prossimo batch
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.error("Errore durante il caricamento:", textStatus, errorThrown); // Log di debug
                            allDataLoaded = true;
                            $loader.hide();
                        }
                    });
                })();
            }

            // Controllo compatibilità Intersection Observer
            if ('IntersectionObserver' in window) {
                // Utilizza Intersection Observer per caricare i manifesti solo quando il container è visibile
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            loadAllManifesti(container);
                            observer.unobserve(entry.target); // Interrompe l'osservazione una volta caricato
                        }
                    });
                }, {threshold: 0.1});

                observer.observe(container.get(0)); // Inizia a osservare il container
            } else {
                // Fallback per browser non compatibili
                loadAllManifesti(container);
            }
        });
    });
})(jQuery);
