(function ($) {
    $(document).ready(function () {
        $('.manifesto-container').each(function () {
            var container = $(this);
            var post_id = container.data('postid');
            var tipo_manifesto = container.data('tipo');
            var offset = 0;
            var loading = false;
            var allDataLoaded = false;
            var totalManifesti = 0;

            // Se non è la sezione "top" usiamo una sentinella per l'infinite scroll
            var $sentinel = (tipo_manifesto !== 'top') ? container.siblings('.sentinel') : null;
            var $loader = container.siblings('.manifesto-loader');

            function updateEditorBackground(data, containerElem) {
                if (!data || !containerElem || !containerElem.length) {
                    console.warn('Missing data or container for updateEditorBackground');
                    return;
                }

                const backgroundDiv = containerElem.find('.text-editor-background').get(0);
                const textEditor = containerElem.find('.custom-text-editor').get(0);

                if (!backgroundDiv || !textEditor) {
                    console.warn('Required elements not found in container');
                    return;
                }

                if (data.manifesto_background) {
                    const img = new Image();
                    img.src = data.manifesto_background;
                    img.onload = function () {
                        const aspectRatio = img.width / img.height;
                        backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                        if (aspectRatio > 1) {
                            backgroundDiv.style.width = '100%';
                            backgroundDiv.style.height = `${backgroundDiv.clientWidth / aspectRatio}px`;
                        } else {
                            backgroundDiv.style.height = '350px';
                            backgroundDiv.style.width = `${backgroundDiv.clientHeight * aspectRatio}px`;
                        }

                        const marginTopPx = (data.margin_top / 100) * backgroundDiv.clientHeight;
                        const marginRightPx = (data.margin_right / 100) * backgroundDiv.clientWidth;
                        const marginBottomPx = (data.margin_bottom / 100) * backgroundDiv.clientHeight;
                        const marginLeftPx = (data.margin_left / 100) * backgroundDiv.clientWidth;

                        textEditor.style.paddingTop = `${marginTopPx}px`;
                        textEditor.style.paddingRight = `${marginRightPx}px`;
                        textEditor.style.paddingBottom = `${marginBottomPx}px`;
                        textEditor.style.paddingLeft = `${marginLeftPx}px`;
                        textEditor.style.textAlign = data.alignment || 'left';
                    }
                } else {
                    backgroundDiv.style.backgroundImage = 'none';
                }
            }

            function loadManifesti(isInfiniteScroll = false) {
                if (loading || allDataLoaded) return;

                // Su mobile: registra la posizione dell'ultimo elemento del batch precedente
                var prevScrollPos = null;
                if (window.innerWidth <= 768) {
                    var lastChild = container.children().last();
                    if (lastChild.length) {
                        prevScrollPos = lastChild.offset().top + lastChild.outerHeight();
                    }
                }

                loading = true;
                $loader && $loader.show();

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
                        if (!response.success || !response.data || response.data.length === 0) {
                            allDataLoaded = true;
                            $sentinel && $sentinel.remove();
                            $loader && $loader.hide();
                            return;
                        }

                        response.data.forEach(function (item) {
                            if (!item || !item.html) return;

                            var newElement = $(item.html);
                            container.append(newElement);

                            // Rende visibile il manifesto_divider per la sezione
                            container.parent().parent().parent().parent().find('.manifesto_divider').show();

                            if (item.vendor_data) {
                                updateEditorBackground(item.vendor_data, newElement);
                            }
                        });

                        offset += response.data.length;
                        totalManifesti += response.data.length;
                        loading = false;
                        $loader && $loader.hide();

                        // Aggiusta justify-content in base al numero di manifesti
                        if (totalManifesti < 5) {
                            container.css('justify-content', 'center');
                        } else {
                            container.css('justify-content', 'left');
                        }

                        // Su mobile, ripristina la posizione precedente: l'ultimo del batch precedente resta visibile,
                        // mentre i nuovi 5 vengono caricati offscreen
                        if (window.innerWidth <= 768 && prevScrollPos !== null) {
                            $(window).scrollTop(prevScrollPos);
                        }

                        // Per "top": carica il batch successivo in automatico
                        if (tipo_manifesto === 'top' && !isInfiniteScroll) {
                            loadManifesti();
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("Error during loading:", textStatus, errorThrown);
                        loading = false;
                        $loader && $loader.hide();
                    }
                });
            }

            if (tipo_manifesto === 'top') {
                loadManifesti();
            } else {
                // Usa l'infinite scroll osservando la sentinella
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting && !loading && !allDataLoaded) {
                            loadManifesti(true);
                        }
                    });
                }, {
                    root: null,
                    rootMargin: '0px',
                    threshold: 0.1
                });

                observer.observe($sentinel[0]);

                // Carica il primo batch
                loadManifesti(true);
            }
        });
    });
})(jQuery);
