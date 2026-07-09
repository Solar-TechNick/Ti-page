// sw.js — offline support for the Lead-Aufnahmebogen field app.
// Served from the site root; scope is '/', but it ONLY manages the lead app + its
// vendored assets. All other same-origin URLs (marketing site) are left to the network.
// Bump CACHE on any deploy that changes the app HTML or a vendored asset.
const CACHE = 'lead-app-v1';
const APP = './Lead-Aufnahmebogen.html';
const ASSETS = [
  APP,
  './assets/lead/html2pdf.bundle.min.js',
  './assets/lead/manifest.json',
  './assets/lead/icon-512.png',
  './assets/lead/dm-sans-400.woff2',
  './assets/lead/dm-sans-500.woff2',
  './assets/lead/dm-sans-600.woff2',
  './assets/lead/dm-sans-700.woff2',
  './assets/lead/dm-sans-800.woff2'
];
const APP_PATH = new URL(APP, self.location).pathname;
const MANAGED = ASSETS.map((u) => new URL(u, self.location).pathname);

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE)
      .then((c) => c.addAll(ASSETS.map((u) => new Request(u, { cache: 'reload' }))))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                       // never touch POST (the send)
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;        // never touch the API subdomain
  const isApp = req.mode === 'navigate' && url.pathname === APP_PATH;
  if (!isApp && !MANAGED.includes(url.pathname)) return;  // leave the rest of the site to the network

  if (isApp) {
    // Network-first for the app shell so a new deploy propagates; cache fallback offline.
    e.respondWith(
      fetch(req).then((res) => {
        if (res && res.status === 200) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(APP, copy)).catch(() => {}); }
        return res;
      }).catch(() => caches.match(APP))
    );
    return;
  }
  // Cache-first for the vendored assets (bump CACHE on change).
  e.respondWith(
    caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res && res.status === 200) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {}); }
      return res;
    }))
  );
});
