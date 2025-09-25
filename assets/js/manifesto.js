(function ($) {
    'use strict';
    
    // Simple configuration
    const CONFIG = {
        CONTAINER_SIZE: 0.95, // 80%
        MAX_FONT_SIZE: 20, // Hard limit in px
        MIN_FONT_SIZE: 8,  // Minimum readable size
        LINE_HEIGHT_RATIO: 1.2
    };
    
    // Image cache for performance
    const imageCache = new Map();
    const manifestiData = new Map();
    
    // Cache intelligente per analisi testo
    const textAnalysisCache = new Map();
    
    // Load image with caching
    function loadImage(url) {
        if (imageCache.has(url)) {
            return Promise.resolve(imageCache.get(url));
        }
        
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                imageCache.set(url, img);
                resolve(img);
            };
            img.onerror = () => reject(new Error(`Failed to load: ${url}`));
            img.src = url;
        });
    }
    
    
    // Genera ID unico per l'elemento testo basato sul contenuto
    function generateTextId(textEditor) {
        const testo = textEditor.textContent || textEditor.innerText || '';
        const html = textEditor.innerHTML || '';
        
        // Crea hash semplice del contenuto per ID univoco
        let hash = 0;
        const content = testo + html;
        for (let i = 0; i < content.length; i++) {
            const char = content.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        
        return `text_${Math.abs(hash)}`;
    }
    
    // Analizza la densità del testo per ottimizzare il font (con cache)
    function analizzaTesto(textEditor, forceRecalculate = false) {
        // Genera ID univoco per questo testo
        const textId = generateTextId(textEditor);
        
        // Controlla se abbiamo già l'analisi in cache
        if (!forceRecalculate && textAnalysisCache.has(textId)) {
            const cachedAnalysis = textAnalysisCache.get(textId);
            console.log(`💾 Cache hit per testo ID: ${textId}`);
            return cachedAnalysis;
        }
        
        console.log(`🔍 Analisi testo per ID: ${textId}`);
        
        const testo = textEditor.textContent || textEditor.innerText || '';
        const lunghezzaTotale = testo.length;
        
        // Conta le righe REALI: ogni div + br/accapo dentro ogni div
        const divElements = textEditor.querySelectorAll('div');
        let righeReali = 0;
        let rigaPiuLunga = 0;
        let lunghezzaRigaPiuLunga = 0;
        let rigaCorrente = 0;
        
        if (divElements.length > 0) {
            // Analizza ogni div e conta le righe interne (br, \n)
            divElements.forEach((div, divIndex) => {
                const htmlDiv = div.innerHTML;
                const testoDiv = div.textContent || div.innerText || '';
                
                // Conta i <br> e gli \n dentro questo div
                const brCount = (htmlDiv.match(/<br\s*\/?>/gi) || []).length;
                const nlCount = (testoDiv.match(/\n/g) || []).length;
                const righeInDiv = Math.max(1, brCount + nlCount + 1); // +1 per la riga base del div
                
                righeReali += righeInDiv;
                
                // Per la riga più lunga, spezza il contenuto del div per br/\n
                const sottoRighe = testoDiv.split(/\n/);
                sottoRighe.forEach((sottoRiga) => {
                    rigaCorrente++;
                    if (sottoRiga.length > lunghezzaRigaPiuLunga) {
                        lunghezzaRigaPiuLunga = sottoRiga.length;
                        rigaPiuLunga = rigaCorrente;
                    }
                });
            });
        } else {
            // Fallback: se non ci sono div, analizza righe separate da \n o <br>
            const righeArray = testo.split(/\n|<br\s*\/?>/i);
            righeReali = righeArray.length;
            
            righeArray.forEach((riga, index) => {
                if (riga.length > lunghezzaRigaPiuLunga) {
                    lunghezzaRigaPiuLunga = riga.length;
                    rigaPiuLunga = index + 1;
                }
            });
        }
        
        const righe = Math.max(righeReali, 1); // Minimo 1 riga
        
        const analysis = {
            id: textId, // ID univoco per riferimento
            lunghezzaTotale: lunghezzaTotale,
            lunghezzaRigaPiuLunga: lunghezzaRigaPiuLunga,
            rigaPiuLunga: rigaPiuLunga,
            righe: righe,
            righeDivs: divElements.length,
            isTestoCorto: lunghezzaRigaPiuLunga <= 100 && righe <= 10,
            isTestoLungo: righe > 10,
            isTestoLargo: lunghezzaRigaPiuLunga > 100,
            timestamp: Date.now() // Per debug/cache management
        };
        
        // Salva in cache
        textAnalysisCache.set(textId, analysis);
        console.log(`💾 Cached analisi per ID: ${textId} (${righe} righe, riga max: ${lunghezzaRigaPiuLunga})`);
        
        // Associa l'ID al div per riferimento futuro
        textEditor.dataset.textAnalysisId = textId;
        
        return analysis;
    }
    
    // Versione veloce per resize - usa solo cache
    function adattaFontSizeFromCache(textEditor, backgroundDiv) {
        if (!textEditor || !backgroundDiv) return false;
        
        // Cerca analisi in cache usando l'ID associato
        const textId = textEditor.dataset.textAnalysisId;
        if (!textId || !textAnalysisCache.has(textId)) {
            console.log(`⚠️ Cache miss per resize - ID: ${textId || 'missing'}`);
            return false; // Fallback al calcolo completo
        }
        
        const analisi = textAnalysisCache.get(textId);
        const altezzaMax = backgroundDiv.clientHeight;
        const larghezzaMax = backgroundDiv.clientWidth;
        
        // Calcola font size usando analisi cached
        const fontSize = calculateFontSizeFromAnalysis(analisi, larghezzaMax, altezzaMax);
        
        // Applica direttamente senza iterazioni (veloce!)
        textEditor.style.fontSize = fontSize + 'px';
        textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
        
        console.log(`⚡ Resize veloce: ${fontSize}px usando cache ID: ${textId} | ${larghezzaMax}x${altezzaMax}`);
        return true;
    }
    
    // Calcola font size basandosi sui dati di analisi cached
    function calculateFontSizeFromAnalysis(analisi, larghezzaMax, altezzaMax) {
        let fontSize;
        
        if (analisi.isTestoCorto) {
            const fattoreRiga = Math.max(analisi.lunghezzaRigaPiuLunga, 1);
            fontSize = larghezzaMax / (fattoreRiga * 0.6);
        } else if (analisi.isTestoLungo) {
            fontSize = altezzaMax / (analisi.righe * 2.2);
        } else if (analisi.isTestoLargo) {
            const fattoreRiga = analisi.lunghezzaRigaPiuLunga;
            if(fattoreRiga < 100){
                fontSize = Math.min(
                    larghezzaMax / (fattoreRiga * 0.5),
                    altezzaMax / (analisi.righe * 3.5)
                );
            } else {
                fontSize = Math.max(
                    larghezzaMax / (fattoreRiga * 0.4),
                    altezzaMax / (analisi.righe * 3.5)
                );
            }
        } else {
            fontSize = Math.min(
                larghezzaMax / (analisi.lunghezzaRigaPiuLunga * 0.4),
                altezzaMax / (analisi.righe * 3.5)
            );
        }
        
        // Applica limiti CONFIG
        return Math.max(CONFIG.MIN_FONT_SIZE, Math.min(CONFIG.MAX_FONT_SIZE, fontSize));
    }
    
    // Adatta il font-size intelligentemente in base al contenuto (completo)
    function adattaFontSize(textEditor, backgroundDiv, forceRecalculate = false) {
        if (!textEditor || !backgroundDiv) return;
        
        const altezzaMax = backgroundDiv.clientHeight;
        const larghezzaMax = backgroundDiv.clientWidth;
        const analisi = analizzaTesto(textEditor, forceRecalculate);
        
        // Strategia di font sizing basata sul contenuto
        let fontSize;
        
        if (analisi.isTestoCorto) {
            // TESTO CORTO: Massimizza larghezza basandosi sulla riga più lunga
            const fattoreRiga = Math.max(analisi.lunghezzaRigaPiuLunga, 1); // Minimo 10 per evitare font enormi
            fontSize = larghezzaMax / (fattoreRiga * 0.6); // 0.6 = coefficiente larghezza carattere
            console.log(`Strategia: TESTO CORTO - ottimizza per larghezza (riga più lunga: ${analisi.lunghezzaRigaPiuLunga} char)`);
            
        } else if (analisi.isTestoLungo) {
            // TESTO LUNGO: Massimizza altezza - font piccolo per entrare verticalmente
            fontSize = altezzaMax / (analisi.righe * 2.2); // 1.4 = line-height approssimativo
            console.log(`Strategia: TESTO LUNGO - ottimizza per altezza (${analisi.righe} righe)`);
            
        } else if (analisi.isTestoLargo) {
            // RIGA MOLTO LARGA: Priorità alla larghezza della riga più lunga
            const fattoreRiga = analisi.lunghezzaRigaPiuLunga;

            //se fattore di riga è minore di 100 allora usa math min se no usa math max
            if(fattoreRiga < 100){
            fontSize = Math.min(
                larghezzaMax / (fattoreRiga * 0.5), // Basato su riga più lunga
                altezzaMax / (analisi.righe * 3.5)  // Ma limitato dall'altezza
            );
            }else {
            fontSize = Math.max(
                larghezzaMax / (fattoreRiga * 0.4), // Basato su riga più lunga
                altezzaMax / (analisi.righe * 3.5)  // Ma limitato dall'altezza
            );
            }
            console.log(`Strategia: RIGA LARGA - bilanciamento (riga più lunga: ${analisi.lunghezzaRigaPiuLunga} char)`);
            
        } else {
            // TESTO MEDIO: Bilanciamento tra larghezza della riga più lunga e altezza totale
            fontSize = Math.min(
                larghezzaMax / (analisi.lunghezzaRigaPiuLunga * 0.4),  // Basato su riga più lunga
                altezzaMax / (analisi.righe * 3.5)     // Basato su altezza totale
            );
            console.log(`Strategia: TESTO MEDIO - bilanciamento intelligente`);
        }
        
        // Limiti min/max usando CONFIG
        fontSize = Math.max(CONFIG.MIN_FONT_SIZE, Math.min(CONFIG.MAX_FONT_SIZE, fontSize));
        
        // Applica font size iniziale
        textEditor.style.fontSize = fontSize + 'px';
        textEditor.style.lineHeight = CONFIG.LINE_HEIGHT_RATIO;
        
        // FASE DI OTTIMIZZAZIONE: Aggiusta finemente
        let iterations = 0;
        const maxIterations = 25;
        
        // Prima fase: riduci se non entra
        while (
            (textEditor.scrollHeight > altezzaMax || textEditor.scrollWidth > larghezzaMax) 
            && fontSize > CONFIG.MIN_FONT_SIZE 
            && iterations < maxIterations
        ) {
            fontSize -= 0.5;
            textEditor.style.fontSize = fontSize + 'px';
            iterations++;
            void textEditor.offsetHeight;
        }
        
        // Seconda fase: per testi corti, prova ad aumentare se c'è spazio
        if (analisi.isTestoCorto && iterations < maxIterations) {
            let testFontSize = fontSize;
            while (
                textEditor.scrollHeight <= altezzaMax && 
                textEditor.scrollWidth <= larghezzaMax &&
                testFontSize < CONFIG.MAX_FONT_SIZE &&
                iterations < maxIterations
            ) {
                fontSize = testFontSize;
                testFontSize += 0.5;
                textEditor.style.fontSize = testFontSize + 'px';
                iterations++;
                void textEditor.offsetHeight;
            }
            // Ritorna all'ultima dimensione valida
            textEditor.style.fontSize = fontSize + 'px';
        }
        
        // Log dettagliato per debug
        console.log(`Font ottimizzato: ${fontSize}px | Analisi: ${analisi.righe} righe totali (${analisi.righeDivs} div), riga più lunga: ${analisi.lunghezzaRigaPiuLunga} char (riga #${analisi.rigaPiuLunga}) | Contenitore: ${larghezzaMax}x${altezzaMax}`);
    }
    
    // Sistema responsivo - ricalcolo font size al cambio dimensioni
    let resizeTimeouts = new Map();
    
    function setupResponsiveFontSize(textEditor, backgroundDiv) {
        if (!textEditor || !backgroundDiv) return;
        
        const containerElem = $(backgroundDiv).closest('.flex-item');
        const containerId = containerElem.attr('id') || `container-${Date.now()}`;
        
        // Ricalcolo font size con cache intelligente
        function handleResize() {
            // Clear timeout precedente per questo container
            if (resizeTimeouts.has(containerId)) {
                clearTimeout(resizeTimeouts.get(containerId));
            }
            
            // Imposta nuovo timeout
            resizeTimeouts.set(containerId, setTimeout(() => {
                console.log(`🔄 Resize intelligente per container ${containerId}`);
                
                // Prova prima versione veloce (cache)
                const usedCache = adattaFontSizeFromCache(textEditor, backgroundDiv);
                
                if (!usedCache) {
                    // Fallback a calcolo completo se cache non disponibile
                    console.log(`🐌 Fallback a calcolo completo per container ${containerId}`);
                    adattaFontSize(textEditor, backgroundDiv);
                }
                
                resizeTimeouts.delete(containerId);
            }, 50)); // Debounce ancora più breve per cache veloce
        }
        
        // Observer per cambiamenti dimensioni del text-editor-background
        if (window.ResizeObserver) {
            const resizeObserver = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    if (entry.target === backgroundDiv) {
                        handleResize();
                        break;
                    }
                }
            });
            
            resizeObserver.observe(backgroundDiv);
            
            // Store observer per cleanup futuro
            backgroundDiv._fontResizeObserver = resizeObserver;
        } else {
            // Fallback per browser senza ResizeObserver - usa window resize
            $(window).on(`resize.responsiveFont.${containerId}`, handleResize);
        }
        
        // Calcolo iniziale - stesso identico processo
        setTimeout(() => {
            adattaFontSize(textEditor, backgroundDiv);
        }, 100);
    }
    
    // Apply manifesto styles - VERY SIMPLIFIED
    function applyStyles(data, containerElem, img = null) {
        const backgroundDiv = containerElem.find('.text-editor-background')[0];
        const textEditor = containerElem.find('.custom-text-editor')[0];
        
        if (!backgroundDiv || !textEditor) return;
        
        if (data.manifesto_background && img) {
            setupBackground(backgroundDiv, textEditor, data, img);
        } else {
            setupNoBackground(backgroundDiv, textEditor, data);
        }
        
        // Setup sistema responsivo - ricalcolo al resize
        setupResponsiveFontSize(textEditor, backgroundDiv);
    }
    
    function setupBackground(backgroundDiv, textEditor, data, img) {
        const aspectRatio = img.width / img.height;
        
        // Set background image
        backgroundDiv.style.backgroundImage = `url(${data.manifesto_background})`;
        
        // CSS-based responsive sizing with aspect ratio
        backgroundDiv.style.aspectRatio = `${img.width} / ${img.height}`;
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto'; // Let CSS handle height via aspect-ratio
        backgroundDiv.style.maxWidth = '100%';
        backgroundDiv.style.maxHeight = '80vh'; // Prevent too tall images
        
        // Calculate margins as percentages of image dimensions (like old system)
        const marginTop = data.margin_top || 0;
        const marginRight = data.margin_right || 0;
        const marginBottom = data.margin_bottom || 0;
        const marginLeft = data.margin_left || 0;
        
        // Apply margins as padding percentages - CSS will scale automatically
        textEditor.style.padding = `${marginTop}% ${marginRight}% ${marginBottom}% ${marginLeft}%`;
        textEditor.style.textAlign = data.alignment || 'left';
        
        // Set CSS custom properties for responsive font sizing
        backgroundDiv.style.setProperty('--max-font-size', `${CONFIG.MAX_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--min-font-size', `${CONFIG.MIN_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--line-height-ratio', CONFIG.LINE_HEIGHT_RATIO);
    }
    
    function setupNoBackground(backgroundDiv, textEditor, data) {
        backgroundDiv.style.backgroundImage = 'none';
        backgroundDiv.style.aspectRatio = '16 / 9'; // Default A3 ratio
        backgroundDiv.style.width = `${CONFIG.CONTAINER_SIZE * 100}%`;
        backgroundDiv.style.height = 'auto';
        
        // Simple padding for no-background case
        textEditor.style.padding = '5%';
        textEditor.style.textAlign = data.alignment || 'center';
        
        // Set CSS custom properties
        backgroundDiv.style.setProperty('--max-font-size', `${CONFIG.MAX_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--min-font-size', `${CONFIG.MIN_FONT_SIZE}px`);
        backgroundDiv.style.setProperty('--line-height-ratio', CONFIG.LINE_HEIGHT_RATIO);
    }
    
    // Main function to update manifesto
    function updateManifesto(data, containerElem) {
        if (!data || !containerElem?.length) return;
        
        // Store for potential future use
        const manifestoId = containerElem.attr('id') || `manifesto-${Date.now()}`;
        if (!containerElem.attr('id')) {
            containerElem.attr('id', manifestoId);
        }
        manifestiData.set(manifestoId, { data, containerElem });
        
        const textEditor = containerElem.find('.custom-text-editor')[0];
        
        if (data.manifesto_background) {
            if (textEditor) textEditor.classList.add('loading');
            
            loadImage(data.manifesto_background)
                .then(img => {
                    applyStyles(data, containerElem, img);
                    if (textEditor) textEditor.classList.remove('loading');
                })
                .catch(() => {
                    applyStyles(data, containerElem);
                    if (textEditor) textEditor.classList.remove('loading');
                });
        } else {
            if (textEditor) textEditor.classList.remove('loading');
            applyStyles(data, containerElem);
        }
    }
    
    
    // Initialize on document ready
    $(document).ready(function () {
        
        // Nessuna forzatura mobile necessaria - CSS Grid gestisce tutto automaticamente
        
        // Handle manifesto containers
        $('.manifesto-container').each(function () {
            const container = $(this);
            const postId = container.data('postid');
            const tipoManifesto = container.data('tipo');
            let offset = 0;
            let loading = false;
            let allDataLoaded = false;
            
            // Setup infinite scroll sentinel
            let $sentinel = container.siblings('.sentinel');
            if ($sentinel.length === 0) {
                $sentinel = container.parent().find('.sentinel');
            }
            
            // Setup loader
            let $loader = container.siblings('.manifesto-loader');
            if ($loader.length === 0) {
                $loader = container.parent().find('.manifesto-loader');
            }
            
            function loadManifesti(isInfiniteScroll = false) {
                if (loading || allDataLoaded) return;
                
                loading = true;
                $loader?.show();
                
                $.ajax({
                    url: my_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'load_more_manifesti',
                        post_id: postId,
                        tipo_manifesto: tipoManifesto,
                        offset: offset
                    },
                    success(response) {
                        if (!response.success || !response.data?.length) {
                            allDataLoaded = true;
                            $sentinel?.remove();
                            $loader?.hide();
                            return;
                        }
                        
                        response.data.forEach(item => {
                            if (item?.html) {
                                const newElement = $(item.html);
                                container.append(newElement);
                                
                                // Show manifesto divider for the section
                                container.parent().parent().parent().parent().find('.manifesto_divider').show();
                                
                                if (item.vendor_data) {
                                    updateManifesto(item.vendor_data, newElement);
                                }
                            }
                        });
                        
                        offset += response.data.length;
                        loading = false;
                        $loader?.hide();
                        
                        // Layout automatico CSS Grid - nessuna forzatura necessaria
                        
                        // Auto-load for "top" type
                        if (tipoManifesto === 'top' && !isInfiniteScroll) {
                            loadManifesti();
                        }
                    },
                    error() {
                        loading = false;
                        $loader?.hide();
                    }
                });
            }
            
            // Initialize loading
            if (tipoManifesto === 'top') {
                loadManifesti();
            } else if ($sentinel?.length) {
                // Setup intersection observer for infinite scroll
                const observer = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting && !loading && !allDataLoaded) {
                            loadManifesti(true);
                        }
                    });
                }, { threshold: 0.1 });
                
                observer.observe($sentinel[0]);
                loadManifesti(true);
            } else {
                loadManifesti(true);
            }
        });
    });
    
})(jQuery);