/**
 * PWA Service Worker – cache static assets for faster repeat loads and basic offline support.
 * Scope: same-origin only.
 */
const CACHE_NAME = 'checkout-pwa-v1';

// Cache CDN and static assets on install
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll([
        '/',
        '/manifest.json',
        '/images/pwa/icon-192.png',
        '/images/pwa/icon-512.png'
      ]).catch(() => {});
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// Network-first for navigation (HTML); cache as fallback
self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate' && event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) return;

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then((res) => {
          const clone = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          return res;
        })
        .catch(() => caches.match(event.request).then((cached) => cached || caches.match('/')))
    );
    return;
  }

  // Cache-first for same-origin static assets
  event.respondWith(
    caches.match(event.request).then((cached) =>
      cached || fetch(event.request).then((res) => {
        const clone = res.clone();
        if (res.ok && (event.request.url.includes('/images/') || event.request.url.includes('/css/') || event.request.url.includes('/js/') || event.request.url.includes('/storage/') || event.request.url.includes('/manifest.json'))) {
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        }
        return res;
      })
    )
  );
});
