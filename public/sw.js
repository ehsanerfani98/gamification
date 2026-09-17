/*
 * Service Worker سبک PWA — فصل ۹ سند معماری:
 *  - App Shell و فونت‌ها و آیکون‌ها: cache-first (نصب آفلاین)
 *  - API (/api/v1): هرگز کش نمی‌شود — نتیجه بازی فقط سمت سرور معتبر است
 */
const CACHE = 'gm-shell-v1';
const SHELL = [
    '/',
    '/manifest.webmanifest',
    '/favicon.png',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/fonts/Vazirmatn-Regular.woff2',
    '/fonts/Vazirmatn-Medium.woff2',
    '/fonts/Vazirmatn-Bold.woff2',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // API هرگز کش نمی‌شود (نتایج بازی server-authoritative هستند)
    if (url.pathname.startsWith('/api/')) return;

    if (event.request.method !== 'GET') return;

    // استاتیک‌ها: cache-first سپس شبکه
    if (url.origin === self.location.origin) {
        event.respondWith(
            caches.match(event.request).then(
                (hit) =>
                    hit ??
                    fetch(event.request).then((res) => {
                        const copy = res.clone();
                        caches.open(CACHE).then((cache) => cache.put(event.request, copy));
                        return res;
                    }),
            ),
        );
    }
});
