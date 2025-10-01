// Service Worker for Totem PWA
// Enhanced with strategic caching for critical assets

const CACHE_NAME = 'totem-pwa-v2';
const CACHE_ASSETS = [
    '/totem-pwa/',
    'pwa-totem/manifest.json',
    'pwa-totem/assets/images/logo-192.png',
    'pwa-totem/assets/images/logo-512.png'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Caching critical assets');
                return cache.addAll(CACHE_ASSETS).catch(function(error) {
                    console.warn('Some assets failed to cache:', error);
                });
            })
            .then(function() {
                return self.skipWaiting();
            })
    );
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName.startsWith('totem-pwa-') && cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(function() {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function(event) {
    const url = new URL(event.request.url);

    // Cache strategy for static assets (images, manifest)
    if (url.pathname.includes('/pwa-totem/assets/') ||
        url.pathname.includes('/pwa-totem/manifest.json')) {
        event.respondWith(
            caches.match(event.request).then(function(response) {
                return response || fetch(event.request).then(function(fetchResponse) {
                    return caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, fetchResponse.clone());
                        return fetchResponse;
                    });
                }).catch(function(error) {
                    console.error('Fetch failed for cached asset:', error);
                    return new Response('', { status: 503 });
                });
            })
        );
        return;
    }

    // Network-first strategy for dynamic content (monitor display)
    event.respondWith(
        fetch(event.request, {
            cache: 'no-store',
            credentials: 'same-origin'
        }).catch(function(error) {
            console.error('Network fetch failed:', error);

            // Try cache as fallback
            return caches.match(event.request).then(function(response) {
                if (response) {
                    return response;
                }

                // If offline and requesting HTML, show offline message
                if (event.request.destination === 'document' ||
                    event.request.headers.get('accept').includes('text/html')) {
                    return new Response(
                        '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Offline</title><style>body{display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#000;color:#fff;font-family:sans-serif;text-align:center;}h1{color:#ff5722;}</style></head><body><div><h1>⚠️ Connessione Assente</h1><p>Collegarsi alla rete per utilizzare il totem</p></div></body></html>',
                        {
                            headers: { 'Content-Type': 'text/html' }
                        }
                    );
                }

                return new Response('', { status: 503 });
            });
        })
    );
});

// Prevent service worker from interfering with navigation
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});