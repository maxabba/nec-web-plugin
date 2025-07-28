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
            var $sentinel = null;
            if (tipo_manifesto !== 'top') {
                var containerId = container.attr('id');
                var instanceId = null;
                
                if (containerId) {
                    var instanceMatch = containerId.match(/manifesto-container-(\d+)/);
                    if (instanceMatch) {
                        instanceId = instanceMatch[1];
                    }
                }

                console.log('Searching for sentinel element:', {
                    containerId: containerId,
                    instanceId: instanceId,
                    tipo_manifesto: tipo_manifesto
                });

                // Prova prima come sibling
                $sentinel = container.siblings('.sentinel');
                console.log('Found siblings .sentinel:', $sentinel.length);

                // Se non trovato come sibling, prova nel parent
                if ($sentinel.length === 0) {
                    $sentinel = container.parent().find('.sentinel');
                    console.log('Found in parent .sentinel:', $sentinel.length);
                }

                // Se ancora non trovato, prova con l'ID specifico
                if ($sentinel.length === 0 && instanceId) {
                    $sentinel = $('#sentinel-' + instanceId);
                    console.log('Found by ID #sentinel-' + instanceId + ':', $sentinel.length);
                }
            }
            // Trova l'elemento loader con una strategia simile
            var $loader = container.siblings('.manifesto-loader');
            if ($loader.length === 0) {
                $loader = container.parent().find('.manifesto-loader');
            }
            if ($loader.length === 0) {
                var containerId = container.attr('id');
                if (containerId) {
                    var instanceMatch = containerId.match(/manifesto-container-(\d+)/);
                    if (instanceMatch) {
                        $loader = $('#manifesto-loader-' + instanceMatch[1]);
                    }
                }
            }

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
                if ($sentinel && $sentinel.length > 0) {
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
                } else {
                    console.warn('Sentinel element not found for infinite scroll.', {
                        container: container,
                        containerId: container.attr('id'),
                        tipo_manifesto: tipo_manifesto,
                        siblings: container.siblings().length,
                        siblingClasses: container.siblings().map(function () {
                            return this.className;
                        }).get()
                    });
                    // Carica il primo batch anche se non c'è la sentinella
                    loadManifesti(true);
                }
            }
        });
    });
})(jQuery);