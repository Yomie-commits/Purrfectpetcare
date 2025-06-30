const CACHE_NAME = 'pet-care-cache-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/index.html',
  '/assets/css/main.css',
  '/assets/css/dashboard-features.css',
  '/assets/js/main.js',
  '/assets/js/dashboard-features.js',
  '/assets/js/dashboard.js',
  '/assets/js/script.js',
  '/assets/js/real-time-notifications.js',
  '/assets/js/accessibility.js',
  '/assets/js/admin-analytics.js',
  '/assets/images/favicon.ico',
  '/assets/images/paws1.png',
  '/assets/images/dogicon.png',
  '/assets/images/caticon.png',
  '/assets/images/birdicon.webp',
  '/assets/images/smallicon.png',
  '/assets/images/catwellness.avif',
  '/assets/images/catwellness.jpeg',
  '/assets/images/dental.avif',
  '/assets/images/dental.jpeg',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS_TO_CACHE))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request).catch(() => caches.match('/index..html'));
    })
  );
}); 