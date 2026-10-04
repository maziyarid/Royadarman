// Only these reviewed public bytes may enter this worker's CacheStorage.
// URL versions and browser-enforced integrity pins must change together.
// Existing font imports are network-only; system fonts remain the offline fallback.
const PUBLIC_ASSETS = Object.freeze([
  Object.freeze({url: '/offline.html?v=32774791de7d5e1ee072e6bf8d8fd19d4eb19a5c6336f4f7f3768b5a42ee24eb', integrity: 'sha256-MndHkd59Xh7gcua/jY/RnU6xmlxjNvT383aLWkLuJOs=', type: 'text/html'}),
  Object.freeze({url: '/assets/workspace.css?v=12a65d5cde491c447941b1d2542126c9130d353670ac2fd8c104f54d1ce32de7', integrity: 'sha256-EqZdXN5JHER5QbHSVCEmyRMNNTZwrC/YwQT1TRzjLec=', type: 'text/css'}),
  Object.freeze({url: '/assets/brand-mark.svg?v=491dcbb2cac8e72d4d3380449733c91db36664ca9c776addc83fabff57dded8b', integrity: 'sha256-SR3LssrI5y1NM4BElzPJHbNmZMqcd2rdyD+r/1fd7Ys=', type: 'image/svg+xml'}),
]);
const OFFLINE = PUBLIC_ASSETS[0];
const CACHE = 'royadarman-public-shell-v3-32774791de7d5e1e';
const ownCache = name => /^royadarman-static-v[0-9]+(?:-|$)/.test(name)
  || name.startsWith('royadarman-public-shell-');

function safePublicResponse(response, asset) {
  const type = (response.headers.get('Content-Type') || '').split(';')[0].trim().toLowerCase();
  const cacheControl = response.headers.get('Cache-Control') || '';
  const vary = response.headers.get('Vary') || '';
  return response.status === 200 && response.type === 'basic' && !response.redirected
    && response.url === new URL(asset.url, self.location.origin).href
    && type === asset.type
    && !/\b(?:private|no-store)\b/i.test(cacheControl)
    && !/(?:^|,)\s*(?:\*|cookie|authorization)\s*(?:,|$)/i.test(vary);
}

async function fetchPublic(asset) {
  // Omit credentials even on same-origin static requests. A query string alone
  // is not an integrity guarantee; fetch's SRI rejects changed server bytes.
  const response = await fetch(new Request(new URL(asset.url, self.location.origin), {
    credentials: 'omit', cache: 'reload', redirect: 'error', integrity: asset.integrity,
  }));
  if (!safePublicResponse(response, asset)) throw new TypeError('Public shell response rejected');
  return response;
}

self.addEventListener('install', event => {
  event.waitUntil((async () => {
    // Validate the complete public bundle before writing any entry. A failed
    // update leaves the active worker and its caches untouched.
    const responses = await Promise.all(PUBLIC_ASSETS.map(fetchPublic));
    const cache = await caches.open(CACHE);
    await Promise.all(PUBLIC_ASSETS.map((asset, index) => cache.put(asset.url, responses[index])));
  })());
  // Do not skipWaiting: an open payment/upload/form keeps its current worker.
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(key => key !== CACHE && ownCache(key)).map(key => caches.delete(key)));
  })());
  // No clients.claim or reload message: normal browser lifecycle owns takeover.
});

async function cachedPublic(asset) {
  try {
    const cache = await caches.open(CACHE);
    const response = await cache.match(asset.url);
    return response && safePublicResponse(response, asset) ? response : undefined;
  } catch {
    return undefined; // Storage-disabled sessions remain network-only.
  }
}

async function offlineResponse() {
  const cached = await cachedPublic(OFFLINE);
  if (cached) return cached;
  // Cache eviction must not revive an inherited private response or report a
  // successful write. This last-resort navigation response contains no data.
  return new Response('اتصال اینترنت در دسترس نیست. / الاتصال بالإنترنت غير متاح. / Internet connection unavailable.', {
    status: 503,
    headers: {'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store',
      'Content-Security-Policy': "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"},
  });
}

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return; // No background mutation queue.
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    // Authentication/domain state is always obtained from the server. The
    // offline fallback is neutral presentation, never a cached signed-in page.
    event.respondWith(fetch(request, {cache: 'no-store'}).catch(offlineResponse));
    return;
  }

  const asset = PUBLIC_ASSETS.find(item => new URL(item.url, self.location.origin).href === url.href);
  if (!asset) {
    // Includes APIs, reports, messages, finance, unversioned assets, arbitrary
    // query strings and tokenised links. Never cache their responses.
    event.respondWith(fetch(request, {cache: 'no-store'}));
    return;
  }
  event.respondWith(cachedPublic(asset).then(cached => cached || fetchPublic(asset)));
});
