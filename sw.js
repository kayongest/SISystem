// sw.js - Service Worker for aBility Offline-First Asset Intelligence
const CACHE_NAME = 'ability-cache-v1';

// Assets to precache on installation
const PRECACHE_ASSETS = [
  'scan_bulk.php',
  'manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
  'assets/images/express-delivery.png',
  'assets/images/warehouse.png'
];

// Install Event - Pre-cache critical files
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Pre-caching offline assets');
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate Event - Clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Removing old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Network First with Cache Fallback for pages, Cache First for static assets
self.addEventListener('fetch', event => {
  const requestUrl = new URL(event.request.url);

  // Skip non-GET requests (e.g. POST api requests should not be cached)
  if (event.request.method !== 'GET') {
    return;
  }

  // Only intercept requests for scan_bulk.php, get_items_offline_cache.php, and precached files
  const isScanPage = requestUrl.pathname.endsWith('/scan_bulk.php');
  const isOfflineApi = requestUrl.pathname.includes('get_items_offline_cache.php');
  const isPrecached = PRECACHE_ASSETS.some(asset => {
    if (asset.startsWith('http')) {
      return event.request.url === asset;
    }
    return requestUrl.pathname.endsWith('/' + asset);
  });

  if (!isScanPage && !isOfflineApi && !isPrecached) {
    // Bypass the service worker and let the browser load standard dynamic files naturally
    return;
  }

  // Handle API cache requests
  if (requestUrl.pathname.includes('/api/')) {
    if (requestUrl.pathname.includes('get_items_offline_cache.php')) {
      event.respondWith(
        fetch(event.request)
          .then(response => {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
            return response;
          })
          .catch(() => caches.match(event.request))
      );
      return;
    }
    return;
  }

  // Network First, Cache Fallback strategy for main pages (like scan_bulk.php)
  if (event.request.headers.get('accept').includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Cache the latest page version
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          return response;
        })
        .catch(() => {
          // If network is offline, return the cached page
          return caches.match(event.request);
        })
    );
    return;
  }

  // Cache First, Network Fallback strategy for static resources (CSS, JS, Fonts, Images)
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(event.request).then(networkResponse => {
          if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
            return networkResponse;
          }

          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });

          return networkResponse;
        });
      })
  );
});
