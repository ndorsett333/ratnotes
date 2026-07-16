const CACHE_NAME = '__RATNOTES_CACHE_NAME__';
const ARCHIVE_PATH = '__RATNOTES_ARCHIVE_PATH__';
const PLUGIN_BASE_PATH = '__RATNOTES_PLUGIN_BASE_PATH__';
const MANIFEST_URL = '__RATNOTES_MANIFEST_URL__';
const OFFLINE_HTML = '__RATNOTES_OFFLINE_HTML__';

const ARCHIVE_PATH_NO_TRAILING = ARCHIVE_PATH.endsWith('/')
  ? ARCHIVE_PATH.slice(0, -1)
  : ARCHIVE_PATH;
const ARCHIVE_PATH_WITH_TRAILING = ARCHIVE_PATH.endsWith('/')
  ? ARCHIVE_PATH
  : `${ARCHIVE_PATH}/`;

const APP_SHELL_URLS = [
  ARCHIVE_PATH_NO_TRAILING,
  ARCHIVE_PATH_WITH_TRAILING,
  `${PLUGIN_BASE_PATH}frontend/css/frontend.css`,
  `${PLUGIN_BASE_PATH}frontend/js/frontend.js`,
  MANIFEST_URL,
  `${PLUGIN_BASE_PATH}frontend/icons/ratnotes.png`,
  `${PLUGIN_BASE_PATH}frontend/icons/ratnotes167.png`,
  `${PLUGIN_BASE_PATH}frontend/icons/ratnotes180.png`,
  '/wp-includes/css/dashicons.min.css',
  '/wp-includes/fonts/dashicons.woff2',
  '/wp-includes/fonts/dashicons.woff',
  '/wp-includes/fonts/dashicons.ttf'
];

function normalizePath(pathname) {
  if (!pathname) {
    return '/';
  }

  const trimmed = pathname.replace(/\/+$/, '');
  return trimmed || '/';
}

function isArchivePath(pathname) {
  return normalizePath(pathname) === normalizePath(ARCHIVE_PATH_WITH_TRAILING);
}

async function cacheUrl(cache, url, cacheKey = url) {
  const request = new Request(url, {
    cache: 'reload',
    credentials: 'same-origin'
  });
  const response = await fetch(request);

  if (!response || !response.ok) {
    throw new Error(`Failed to fetch ${url}: ${response ? response.status : 'no response'}`);
  }

  await cache.put(cacheKey, response.clone());
  return response;
}

async function precacheShell(cache) {
  for (const url of APP_SHELL_URLS) {
    try {
      await cacheUrl(cache, url);
    } catch (error) {
      // Optional asset failures should not block service worker installation.
      console.warn('[RatNotes SW] Failed to precache:', url, error);
    }
  }

  try {
    const archiveResponse = await cacheUrl(cache, ARCHIVE_PATH_WITH_TRAILING, ARCHIVE_PATH_WITH_TRAILING);
    await cache.put(ARCHIVE_PATH_NO_TRAILING, archiveResponse.clone());
  } catch (error) {
    console.warn('[RatNotes SW] Failed to precache canonical archive shell:', error);
  }
}

async function warmShell(urls = []) {
  const cache = await caches.open(CACHE_NAME);
  const uniqueUrls = Array.from(new Set([
    ARCHIVE_PATH_NO_TRAILING,
    ARCHIVE_PATH_WITH_TRAILING,
    MANIFEST_URL,
    ...urls
  ]));

  for (const url of uniqueUrls) {
    try {
      if (isArchivePath(new URL(url, self.location.origin).pathname)) {
        const archiveResponse = await cacheUrl(cache, ARCHIVE_PATH_WITH_TRAILING, ARCHIVE_PATH_WITH_TRAILING);
        await cache.put(ARCHIVE_PATH_NO_TRAILING, archiveResponse.clone());
        continue;
      }

      await cacheUrl(cache, url);
    } catch (error) {
      console.warn('[RatNotes SW] Failed to warm URL:', url, error);
    }
  }
}

self.addEventListener('message', (event) => {
  if (!event.data || event.data.type !== 'ratnotes-warm-shell') {
    return;
  }

  event.waitUntil(warmShell(Array.isArray(event.data.urls) ? event.data.urls : []));
});

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => precacheShell(cache))
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
    if (isArchivePath(url.pathname)) {
      // Network-first: the archive HTML is auth-dependent (login form vs.
      // notes UI), so we must always fetch fresh when online and never serve
      // a stale logged-out/logged-in snapshot. Cache is only a fallback for
      // offline launches.
      event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
          try {
            const response = await fetch(request);
            if (response && response.ok) {
              await cache.put(ARCHIVE_PATH_WITH_TRAILING, response.clone());
              await cache.put(ARCHIVE_PATH_NO_TRAILING, response.clone());
            }
            return response;
          } catch (error) {
            const cachedArchive = await cache.match(ARCHIVE_PATH_WITH_TRAILING)
              || await cache.match(ARCHIVE_PATH_NO_TRAILING)
              || await caches.match(ARCHIVE_PATH_WITH_TRAILING)
              || await caches.match(ARCHIVE_PATH_NO_TRAILING);

            if (cachedArchive) {
              return cachedArchive;
            }

            return new Response(OFFLINE_HTML, {
              headers: {
                'Content-Type': 'text/html; charset=UTF-8'
              }
            });
          }
        })
      );
      return;
    }

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

            return caches.match(ARCHIVE_PATH_WITH_TRAILING).then((archiveCached) => {
              if (archiveCached) {
                return archiveCached;
              }

              return caches.match(ARCHIVE_PATH_NO_TRAILING).then((archiveCachedNoSlash) => {
                if (archiveCachedNoSlash) {
                  return archiveCachedNoSlash;
                }

                return new Response(OFFLINE_HTML, {
                  headers: {
                    'Content-Type': 'text/html; charset=UTF-8'
                  }
                });
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
