// Service worker de Levado: instala la PWA y da soporte offline básico.
// Estrategia conservadora: nunca cachea HTML de la app (datos por tenant),
// solo assets estáticos.
//
// Se sirve desde una ruta de Laravel, no como archivo en public/, para que la
// versión viaje adentro. El browser solo reinstala un service worker cuando
// cambian los BYTES de este script: mientras era un estático con el nombre de
// cache fijo en 'levado-v1', ningún deploy lo modificaba, así que install y
// activate no volvían a correr y el filtro de activate —que borra las caches
// cuyo nombre no coincide con el actual— nunca borraba nada. El cliente
// arrastraba la misma cache desde el día que instaló la PWA.
const VERSION = '{{ $version }}';
const CACHE_NAME = `levado-${VERSION}`;
const OFFLINE_URL = '/offline.html';

// Sin esto, una red que cuelga sin cortar deja el respondWith de la navegación
// sin resolver y la página no termina de cargar nunca: al interponerse el
// service worker, el browser ya no aplica sus propios timeouts.
const NAVIGATION_TIMEOUT_MS = 10000;

const PRECACHE = [
    OFFLINE_URL,
    '/favicon.svg',
    '/icons/icon-192.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            // allSettled y no addAll: addAll es atómico, así que un solo 404 en
            // PRECACHE aborta el install y deja al service worker viejo sirviendo
            // para siempre. El precacheo es una mejora, no un requisito para
            // activar una versión nueva.
            .then((cache) => Promise.allSettled(PRECACHE.map((url) => cache.add(url))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

/** Espera a `promise` como máximo `ms`; si no llegó, rechaza para ir al fallback. */
const withTimeout = (promise, ms) => Promise.race([
    promise,
    new Promise((resolve, reject) => setTimeout(() => reject(new Error('sw-timeout')), ms)),
]);

/** Assets con hash de Vite: el nombre cambia con el contenido, servirlos de cache sin revalidar es seguro. */
const isHashedAsset = (url) => url.pathname.startsWith('/build/');

/** Iconos y favicon: la URL es estable, así que hay que revalidar o quedan congelados para siempre. */
const isStableUrlAsset = (url) => url.pathname.startsWith('/icons/') || url.pathname.startsWith('/favicon');

const cacheFirst = async (request) => {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
};

const staleWhileRevalidate = async (request) => {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached);

    return cached || network;
};

const offlineFallback = async () => {
    const cached = await caches.match(OFFLINE_URL);

    return cached ?? new Response('Sin conexión.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
};

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    // Navegaciones (HTML): siempre red; si no hay conexión, página offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            withTimeout(fetch(request), NAVIGATION_TIMEOUT_MS).catch(() => offlineFallback())
        );

        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (isHashedAsset(url)) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (isStableUrlAsset(url)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});
