(function ($) {
    const imageCache = new Map();
    function loadImageWithCache(url) {
        return new Promise((resolve, reject) => {
            if (imageCache.has(url)) {
                const cachedImg = imageCache.get(url);
                resolve(cachedImg);
            } else {
                const img = new Image();
                img.onload = function() {
                    imageCache.set(url, img);
                    resolve(img);
                };
                img.onerror = function() {
                    reject(new Error('Failed to load image: ' + url));
                };
                img.src = url;
            }
        });
    }

    $(document).ready(function () {
        const manifestiDataGlobal = new Map();
        function applyManifestoStyles(data, containerElem, img = null) {
                const backgroundDiv = containerElem.find('.text-editor-background').get(0);
                const textEditor = containerElem.find('.custom-text-editor').get(0);

                if (!backgroundDiv || !textEditor) return;

                if (data.manifesto_background && img) {
                    const aspectRatio = img.width / img.height;
                    const CONTAINER_SIZE = 0.8; // 80% - single source of truth
                    backgroundDiv.style.backgroundImage = 'url(' + data.manifesto_background + ')';
                    
                    // Get parent container dimensions for initial calculation
                    const parentWidth = containerElem.width() || window.innerWidth * 0.8;
                    const parentHeight = containerElem.height() || window.innerHeight * 0.6;
                    
                    if (aspectRatio < 1) {
                        backgroundDiv.style.width = `${CONTAINER_SIZE * 100}%`;
                        // Force a reflow to get actual width, fallback to calculated
                        const actualWidth = backgroundDiv.clientWidth || (parentWidth * CONTAINER_SIZE);
                        backgroundDiv.style.height = `${actualWidth / aspectRatio}px`;
                    } else {
                        backgroundDiv.style.height = `${CONTAINER_SIZE * 100}%`;
                        // Force a reflow to get actual height, fallback to calculated
                        const actualHeight = backgroundDiv.clientHeight || (parentHeight * CONTAINER_SIZE);
                        backgroundDiv.style.width = `${actualHeight * aspectRatio}px`;
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

                    // Calculate font-size using actual pixel dimensions, not CSS percentages
                    // Force layout calculation by accessing offsetWidth/offsetHeight
                    backgroundDiv.offsetWidth; // Force reflow
                    const currentWidth = backgroundDiv.clientWidth || (parentWidth * CONTAINER_SIZE);
                    const currentHeight = backgroundDiv.clientHeight || (parentHeight * CONTAINER_SIZE);
                    
                    const isLandscape = currentWidth > currentHeight;
                    const isPortrait = currentHeight > currentWidth;
                    const area = currentWidth * currentHeight;
                    let baseFontSize;
                    let lineHeightMultiplier = 1.1;
                    let areaBasedFontSize = Math.sqrt(area) * 0.1;
                    
                    if (isPortrait) {
                        baseFontSize = areaBasedFontSize * 0.9;
                    } else if (isLandscape) {
                        baseFontSize = areaBasedFontSize;
                    } else {
                        baseFontSize = areaBasedFontSize;
                    }
                    
                    textEditor.style.fontSize = `${baseFontSize}px`;
                    textEditor.style.lineHeight = `${baseFontSize * lineHeightMultiplier}px`;
                    textEditor.style.color = '#000';
                    textEditor.style.position = 'absolute';
                    textEditor.style.top = '0';
                    textEditor.style.left = '0';
                    textEditor.style.width = '100%';
                    textEditor.style.height = '100%';

                    requestAnimationFrame(function() {
                        let iterations = 0;
                        while (textEditor.scrollHeight > textEditor.clientHeight && iterations < 3) {
                            const currentFontSize = parseFloat(textEditor.style.fontSize);
                            const reductionFactor = Math.max(0.85, textEditor.clientHeight / textEditor.scrollHeight);
                            const newFontSize = Math.max(6, currentFontSize * reductionFactor);
                            textEditor.style.fontSize = `${newFontSize}px`;
                            textEditor.style.lineHeight = `${newFontSize * lineHeightMultiplier}px`;
                            iterations++;
                        }
                    });
                } else {
                    backgroundDiv.style.backgroundImage = 'none';
                    const containerWidth = containerElem.parent().width() || window.innerWidth;
                    const isSmallScreen = window.innerWidth < 768;
                    const isVerySmallScreen = window.innerWidth < 480;
                    
                    let baseFontSize;
                    if (isVerySmallScreen) {
                        baseFontSize = Math.max(8, containerWidth * 0.02);
                    } else if (isSmallScreen) {
                        baseFontSize = Math.max(10, containerWidth * 0.025);
                    } else {
                        baseFontSize = Math.max(14, containerWidth * 0.03);
                    }
                    
                    textEditor.style.fontSize = `${baseFontSize}px`;
                    textEditor.style.lineHeight = `${baseFontSize * 1.3}px`;
                    textEditor.style.textAlign = data.alignment || 'center';
                    
                    requestAnimationFrame(function() {
                        let iterations = 0;
                        while (textEditor.scrollHeight > textEditor.clientHeight && iterations < 3) {
                            const currentFontSize = parseFloat(textEditor.style.fontSize);
                            const reductionFactor = Math.max(0.85, textEditor.clientHeight / textEditor.scrollHeight);
                            const newFontSize = Math.max(6, currentFontSize * reductionFactor);
                            textEditor.style.fontSize = `${newFontSize}px`;
                            textEditor.style.lineHeight = `${newFontSize * 1.3}px`;
                            iterations++;
                        }
                    });
                }
            }

        $('.manifesto-container').each(function () {
            var container = $(this);
            var post_id = container.data('postid');
            var tipo_manifesto = container.data('tipo');
            var offset = 0;
            var loading = false;
            var allDataLoaded = false;
            var totalManifesti = 0;
            
            const manifestiData = new Map();
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

                $sentinel = container.siblings('.sentinel');
                if ($sentinel.length === 0) {
                    $sentinel = container.parent().find('.sentinel');
                }
                if ($sentinel.length === 0 && instanceId) {
                    $sentinel = $('#sentinel-' + instanceId);
                }
            }
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
                if (!data || !containerElem || !containerElem.length) return;

                const manifestoId = containerElem.attr('id') || `manifesto-${Date.now()}-${Math.random()}`;
                if (!containerElem.attr('id')) {
                    containerElem.attr('id', manifestoId);
                }
                manifestiData.set(manifestoId, { data, containerElem });
                manifestiDataGlobal.set(manifestoId, { data, containerElem });

                const backgroundDiv = containerElem.find('.text-editor-background').get(0);
                const textEditor = containerElem.find('.custom-text-editor').get(0);

                if (!backgroundDiv || !textEditor) return;

                if (data.manifesto_background) {
                    textEditor.classList.add('loading');
                    loadImageWithCache(data.manifesto_background)
                        .then(function(img) {
                            applyManifestoStyles(data, containerElem, img);
                            textEditor.classList.remove('loading');
                        })
                        .catch(function(error) {
                            applyManifestoStyles(data, containerElem);
                            textEditor.classList.remove('loading');
                        });
                } else {
                    textEditor.classList.remove('loading');
                    applyManifestoStyles(data, containerElem);
                }
            }

            function loadManifesti(isInfiniteScroll = false) {
                if (loading || allDataLoaded) return;

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

                            container.parent().parent().parent().parent().find('.manifesto_divider').show();

                            if (item.vendor_data) {
                                updateEditorBackground(item.vendor_data, newElement);
                            }
                        });

                        offset += response.data.length;
                        totalManifesti += response.data.length;
                        loading = false;
                        $loader && $loader.hide();

                        if (window.innerWidth <= 768 && prevScrollPos !== null) {
                            $(window).scrollTop(prevScrollPos);
                        }

                        if (tipo_manifesto === 'top' && !isInfiniteScroll) {
                            loadManifesti();
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                            loading = false;
                        $loader && $loader.hide();
                    }
                });
            }

            if (tipo_manifesto === 'top') {
                loadManifesti();
            } else {
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

                    loadManifesti(true);
                } else {
                    loadManifesti(true);
                }
            }
        });

        let resizeRAF;
        $(window).on('resize', function() {
            if (resizeRAF) {
                cancelAnimationFrame(resizeRAF);
            }
            
            resizeRAF = requestAnimationFrame(function() {
                manifestiDataGlobal.forEach(function(manifestoInfo, manifestoId) {
                    const { data, containerElem } = manifestoInfo;
                    
                    if (containerElem && containerElem.length && $.contains(document, containerElem[0])) {
                        if (data.manifesto_background) {
                            loadImageWithCache(data.manifesto_background)
                                .then(function(img) {
                                    applyManifestoStyles(data, containerElem, img);
                                })
                                .catch(function(error) {
                                    applyManifestoStyles(data, containerElem);
                                });
                        } else {
                            applyManifestoStyles(data, containerElem);
                        }
                    } else {
                        manifestiDataGlobal.delete(manifestoId);
                    }
                });
            });
        });
    });
})(jQuery);