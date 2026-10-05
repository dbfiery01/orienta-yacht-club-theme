/**
 * OYC Service Worker
 *
 * Cache strategy
 * ─────────────
 * • HTML / navigation         → network-first (fresh content, offline fallback)
 * • CSS / JS / images / fonts → cache-first, background refresh
 * • Weather / tide DATA       → network-first, keep LAST-GOOD for offline
 *     (our same-origin admin-ajax oyc_* proxies + the Open-Meteo / NWS / NOAA
 *      tide feeds the board falls back to) — so the harbor board still shows the
 *      last-known conditions with no connection.
 * • /wp-admin, /wp-login, other admin-ajax, xmlrpc → always bypass
 *
 * CACHE_NAME is rewritten per deploy (inc/pwa.php injects the theme version) so
 * the shell/asset cache is evicted automatically on each release. DATA_CACHE is
 * intentionally NOT versioned, so last-good conditions survive a deploy.
 */

const CACHE_NAME = 'oyc-v1';     // shell + assets (version injected when served)
const DATA_CACHE = 'oyc-data';   // last-good weather/tide data (persists across deploys)

/* Cross-origin JSON feeds the board reads client-side */
const DATA_HOSTS = [
    'api.open-meteo.com',
    'marine-api.open-meteo.com',
    'api.weather.gov',
    'api.tidesandcurrents.noaa.gov',
];

/* Pre-cache on install so these open offline straight from the home screen */
const PRECACHE_URLS = [ '/', '/weather/' ];

/* ── Install ─────────────────────────────────────────────────────────── */
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_URLS).catch(() => {}))
            .then(() => self.skipWaiting())
    );
});

/* ── Activate: drop old versioned caches, keep the persistent data cache ─ */
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys
                    .filter(k => k !== CACHE_NAME && k !== DATA_CACHE)
                    .map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

/* Network-first with last-good fallback, stored in the persistent data cache */
function dataNetworkFirst(request) {
    return fetch(request)
        .then(response => {
            if (response && response.ok) {
                const clone = response.clone();
                caches.open(DATA_CACHE).then(c => c.put(request, clone));
            }
            return response;
        })
        .catch(() => caches.open(DATA_CACHE).then(c => c.match(request)));
}

/* ── Fetch ───────────────────────────────────────────────────────────── */
self.addEventListener('fetch', event => {
    const { request } = event;
    if (request.method !== 'GET') return;

    let url;
    try { url = new URL(request.url); } catch { return; }

    /* Weather / tide data → network-first, keep last-good (works offline).
       Our same-origin admin-ajax oyc_* proxies + the cross-origin data feeds. */
    const isOurAjax =
        url.origin === self.location.origin &&
        url.pathname.includes('admin-ajax') &&
        /[?&]action=oyc_/.test(url.search);
    if (isOurAjax || DATA_HOSTS.includes(url.hostname)) {
        event.respondWith(dataNetworkFirst(request));
        return;
    }

    /* Everything else is same-origin only (leave other cross-origin alone) */
    if (url.origin !== self.location.origin) return;

    /* Bypass: WordPress admin, login, (non-oyc) AJAX, xmlrpc */
    const p = url.pathname;
    if (
        p.startsWith('/wp-admin') ||
        p.startsWith('/wp-login') ||
        p.includes('admin-ajax') ||
        p.includes('xmlrpc')
    ) return;

    /* Navigation (HTML pages): network-first, offline → cached page → board → home */
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(c => c.put(request, clone));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request)
                        .then(cached => cached || caches.match('/weather/') || caches.match('/'))
                )
        );
        return;
    }

    /* Static assets: cache-first, update in background */
    const staticDestinations = ['style', 'script', 'image', 'font', 'manifest'];
    if (staticDestinations.includes(request.destination)) {
        event.respondWith(
            caches.match(request).then(cached => {
                const network = fetch(request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(c => c.put(request, clone));
                    }
                    return response;
                });
                return cached || network;
            })
        );
    }
});
