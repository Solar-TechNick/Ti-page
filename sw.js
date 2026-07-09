// sw.js — precache the app shell + vendored companions for offline use.
// Served from the site root so its scope covers /Lead-Aufnahmebogen.html.
const CACHE = 'lead-app-v1';
const ASSETS = [
  './Lead-Aufnahmebogen.html',
  './assets/lead/html2pdf.bundle.min.js',
  './assets/lead/manifest.json',
  './assets/lead/icon-512.png',
  './assets/lead/dm-sans-400.woff2',
  './assets/lead/dm-sans-500.woff2',
  './assets/lead/dm-sans-600.woff2',
  './assets/lead/dm-sans-700.woff2',
  './assets/lead/dm-sans-800.woff2'
];

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
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // never intercept the API subdomain
  e.respondWith(
    caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (!res || res.status !== 200) return res;
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
      return res;
    }).catch(() => req.mode === 'navigate' ? caches.match('./Lead-Aufnahmebogen.html') : Response.error()))
  );
});
