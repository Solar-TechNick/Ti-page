// assets/lead/sw.js — precache the app shell + vendored companions for offline use.
const CACHE = 'lead-app-v1';
const ASSETS = [
  '../../Lead-Aufnahmebogen.html',
  './html2pdf.bundle.min.js',
  './manifest.json',
  './icon-512.png',
  './dm-sans-400.woff2',
  './dm-sans-500.woff2',
  './dm-sans-600.woff2',
  './dm-sans-700.woff2',
  './dm-sans-800.woff2'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE)
      .then((c) => c.addAll(ASSETS.map((u) => new Request(u, { cache: 'reload' }))))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting()) // don't block install if one asset 404s in dev
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Cache-first for GET (the app + its assets). API calls (POST, cross-origin) bypass the SW.
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // never intercept the API subdomain
  e.respondWith(
    caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
      return res;
    }).catch(() => caches.match('../../Lead-Aufnahmebogen.html')))
  );
});
