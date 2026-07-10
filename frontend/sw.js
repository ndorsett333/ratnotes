const CACHE_NAME = '__RATNOTES_CACHE_NAME__';
const ARCHIVE_PATH = '__RATNOTES_ARCHIVE_PATH__';

const APP_SHELL_URLS = [
  ARCHIVE_PATH,
  '/wp-content/plugins/ratnotes/frontend/css/frontend.css',
  '/wp-content/plugins/ratnotes/frontend/js/frontend.js',
  '/wp-content/plugins/ratnotes/frontend/manifest.json',
  '/wp-content/plugins/ratnotes/frontend/icons/ratnotes.png',
  '/wp-content/plugins/ratnotes/frontend/icons/ratnotes167.png',
  '/wp-content/plugins/ratnotes/frontend/icons/ratnotes180.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.addAll(APP_SHELL_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys
        .filter((key) => key !== CACHE_NAME)
        .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match(ARCHIVE_PATH)))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) {
        return cached;
      }

      return fetch(request).then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        return response;
      });
    })
  );
});
