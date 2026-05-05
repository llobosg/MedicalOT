// public/sw.js - Service Worker (offline básico)
const CACHE_NAME = 'medicalot-v1';
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/assets/css/main.css',
  '/assets/js/app.js',
  '/assets/icons/icon-192.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys => 
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Estrategia: Network-first para API, Cache-first para assets
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  
  if (url.pathname.startsWith('/api/')) {
    // API: siempre red, con fallback a cache si está offline
    e.respondWith(
      fetch(e.request).catch(() => caches.match(e.request))
    );
  } else {
    // Assets: cache primero, luego red
    e.respondWith(
      caches.match(e.request).then(res => res || fetch(e.request))
    );
  }
});