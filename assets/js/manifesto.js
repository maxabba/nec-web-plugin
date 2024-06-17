(function ($) {
    $(document).ready(function () {
        $('.manifesto-container').each(function () {
            var container = $(this);
            var post_id = container.data('postid');
            var tipo_manifesto = container.data('tipo');
            var offset = 0;
            var loading = false;
            var $loader = container.siblings('.manifesto-loader');

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
                            backgroundDiv.style.height = '300px';
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

            function loadManifesti(container) {
                if (loading) return;
                loading = true;
                $loader.show();

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
                        if (!response.success || response.data.length === 0) {
                            $(window).off('scroll.' + container.attr('id'));
                            $loader.hide();
                            return;
                        }

                        response.data.forEach(function (item) {
                            var newElement = $(item.html);
                            container.append(newElement);
                            updateEditorBackground(item.vendor_data, newElement);
                        });

                        offset += 5; // Incrementa l'offset per il prossimo caricamento
                        loading = false;
                        $loader.hide();
                    },
                    error: function () {
                        loading = false;
                        $loader.hide();
                    }
                });
            }

            function isElementInViewport(el) {
                var rect = el.getBoundingClientRect();
                return (
                    rect.top <= (window.innerHeight || document.documentElement.clientHeight)
                );
            }

            $(window).on('scroll.' + container.attr('id'), function () {
                if (isElementInViewport(container.get(0))) {
                    loadManifesti(container);
                }
            });

            // Carica i primi manifesti all'inizio
            loadManifesti(container);
        });
    });
})(jQuery);
