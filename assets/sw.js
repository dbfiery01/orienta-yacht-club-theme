/**
 * OYC Service Worker
 *
 * Cache strategy
 * ─────────────
 * • HTML / navigation  → network-first  (fresh content, offline fallback)
 * • CSS / JS / images / fonts → cache-first, background refresh
 * • /wp-admin, /wp-login, admin-ajax → always bypass (never cached)
 *
 * Bump CACHE_NAME when you deploy breaking asset changes so stale files are
 * evicted from users' caches automatically.
 */

const CACHE_NAME = 'oyc-v1';

/* Assets to pre-cache on first install (add more as needed) */
const PRECACHE_URLS = [
    '/',
];

/* ── Install ─────────────────────────────────────────────────────────── */
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

/* ── Activate: delete old caches ─────────────────────────────────────── */
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys
                    .filter(k => k !== CACHE_NAME)
                    .map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

/* ── Fetch ───────────────────────────────────────────────────────────── */
self.addEventListener('fetch', event => {
    const { request } = event;

    // Only intercept same-origin GET requests
    if (request.method !== 'GET') return;

    let url;
    try { url = new URL(request.url); } catch { return; }
    if (url.origin !== self.location.origin) return;

    // Bypass: WordPress admin, login, AJAX
    const p = url.pathname;
    if (
        p.startsWith('/wp-admin')  ||
        p.startsWith('/wp-login')  ||
        p.includes('admin-ajax')   ||
        p.includes('xmlrpc')
    ) return;

    /* Navigation (HTML pages): network-first */
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
                        .then(cached => cached || caches.match('/'))
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
