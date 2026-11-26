const STATIC_CACHE = 'civicfix-static-v1';
const API_CACHE = 'civicfix-api-v1';
const ACTIVE_CACHES = [STATIC_CACHE, API_CACHE];

const APP_SHELL = [
  '/pwa/',
  '/pwa/index.html',
  '/pwa/styles.css',
  '/pwa/app.js',
  '/pwa/manifest.webmanifest',
  '/pwa/icon.svg'
];

const LEAFLET_ASSETS = [
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
  'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(STATIC_CACHE);
    await Promise.all([...APP_SHELL, ...LEAFLET_ASSETS].map(async (url) => {
      try {
        await cache.add(new Request(url, { cache: 'reload' }));
      } catch {
        return;
      }
    }));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((key) => !ACTIVE_CACHES.includes(key)).map((key) => caches.delete(key)));
    await self.clients.claim();
  })());
});

function isAppShell(url) {
  return url.origin === self.location.origin && url.pathname.startsWith('/pwa/');
}

function isLeafletAsset(url) {
  return url.hostname === 'unpkg.com' && url.pathname.startsWith('/leaflet@1.9.4/dist/');
}

async function cacheFirst(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok || response.type === 'opaque') await cache.put(request, response.clone());
    return response;
  } catch (error) {
    if (request.mode === 'navigate') {
      const shell = await cache.match('/pwa/index.html');
      if (shell) return shell;
    }
    throw error;
  }
}

async function networkFirst(request) {
  const cache = await caches.open(API_CACHE);
  try {
    const response = await fetch(request);
    if (response.ok) {
      const headers = new Headers(response.headers);
      headers.set('Vary', 'Authorization');
      const copy = new Response(response.clone().body, {
        status: response.status,
        statusText: response.statusText,
        headers
      });
      await cache.put(request, copy.clone());
    }
    return response;
  } catch {
    const cached = await cache.match(request);
    if (cached) return cached;
    return new Response(JSON.stringify({ message: 'Sin conexión y sin datos en caché.' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json; charset=utf-8' }
    });
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin === self.location.origin && url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(request));
    return;
  }
  if (isAppShell(url) || isLeafletAsset(url)) {
    event.respondWith(cacheFirst(request));
  }
});
