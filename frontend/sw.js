const CACHE_NAME = '__RATNOTES_CACHE_NAME__';
const ARCHIVE_PATH = '__RATNOTES_ARCHIVE_PATH__';
const PLUGIN_BASE_PATH = '__RATNOTES_PLUGIN_BASE_PATH__';
const OFFLINE_HTML = '__RATNOTES_OFFLINE_HTML__';

const APP_SHELL_URLS = [
  ARCHIVE_PATH,
  `${PLUGIN_BASE_PATH}frontend/css/frontend.css`,
  `${PLUGIN_BASE_PATH}frontend/js/frontend.js`,
  `${PLUGIN_BASE_PATH}frontend/manifest.json`,
  `${PLUGIN_BASE_PATH}frontend/icons/ratnotes.png`,
  `${PLUGIN_BASE_PATH}frontend/icons/ratnotes167.png`,
  `${PLUGIN_BASE_PATH}frontend/icons/ratnotes180.png`,
  '/wp-includes/css/dashicons.min.css',
  '/wp-includes/fonts/dashicons.woff',
  '/wp-includes/fonts/dashicons.ttf'
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
        .catch(() =>
          caches.match(request).then((cached) => {
            if (cached) {
              return cached;
            }

            return caches.match(ARCHIVE_PATH).then((archiveCached) => {
              if (archiveCached) {
                return archiveCached;
              }

              return new Response(OFFLINE_HTML, {
                headers: {
                  'Content-Type': 'text/html; charset=UTF-8'
                }
              });
            });
          })
        )
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
