// Service Worker for Totem PWA
// Minimal implementation - no caching, only network requests

const CACHE_NAME = 'totem-pwa-v1';
const NO_CACHE = true;

self.addEventListener('install', function(event) {
    // Skip waiting and activate immediately
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    // Clear any existing caches
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName.startsWith('totem-pwa-')) {
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
    // Always fetch from network, no caching
    if (NO_CACHE) {
        event.respondWith(
            fetch(event.request, {
                cache: 'no-store',
                credentials: 'same-origin'
            }).catch(function() {
                // If offline, return a simple offline message
                if (event.request.destination === 'document') {
                    return new Response(
                        '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Offline</title><style>body{display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#000;color:#fff;font-family:sans-serif;text-align:center;}h1{color:#ff5722;}</style></head><body><div><h1>⚠️ Connessione Assente</h1><p>Collegarsi alla rete per utilizzare il totem</p></div></body></html>',
                        {
                            headers: { 'Content-Type': 'text/html' }
                        }
                    );
                }
                // For other resources, just fail silently
                return new Response('', { status: 503 });
            })
        );
        return;
    }
});

// Prevent service worker from interfering with navigation
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});