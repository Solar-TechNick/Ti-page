# Lead-Aufnahmebogen Field-App Rework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite `Lead-Aufnahmebogen.html` into a mobile-first, offline-capable field app with collapsible sections, dynamic roof surfaces, photo upload (IndexedDB), a filled-only PDF that embeds photos, and a "send to office" POST — replacing the current full-rerender / `mailto:` / CDN-dependent version.

**Architecture:** One self-contained `Lead-Aufnahmebogen.html` (all CSS/JS inline), vanilla JS, no build step, internally organized into modules (Schema · State · Storage · Image · Render · Actions · PDF · Backend). Small vendored companions under `assets/lead/` (DM Sans woff2, `html2pdf`, `sw.js`, `manifest.json`, icon) are precached by a service worker for full offline use + home-screen install. Lead field values live in `localStorage`; photo blobs live in IndexedDB. The "send" POST mirrors the site's existing `angebot` form: cross-origin `multipart/form-data` to `${API_BASE}/lead.php` with `files[]` + honeypot `website` (no CSRF — public endpoints use honeypot + rate-limit + CORS).

**Tech Stack:** Static HTML/CSS/vanilla JS; IndexedDB; Service Worker + Web App Manifest; `html2pdf.js` 0.10.2 (vendored); DM Sans (vendored woff2). No framework, no bundler, no npm.

**Spec:** `docs/superpowers/specs/2026-07-09-lead-aufnahmebogen-rework-design.md`

## Global Constraints

- **Zero build step.** No npm/bundler/transpile. Everything runs by opening the file over HTTP.
- **No CDN references at runtime.** Font + PDF lib are vendored under `assets/lead/`.
- **Single app file:** all CSS and JS stay inline in `Lead-Aufnahmebogen.html`. Companions under `assets/lead/` are limited to fonts, `html2pdf.bundle.min.js`, `sw.js`, `manifest.json`, and an icon.
- **API base:** `const API_BASE = "https://api.technik-prignitz.de";` (same value the site uses in `script.js`). Send endpoint: `${API_BASE}/lead.php`. Honeypot field name: `website`. Files field: `files[]`.
- **Language:** German UI copy, `lang="de"`.
- **No automated JS tests** (repo has none for frontend). Each task ends with a **precise manual browser verification** and a commit. If you cannot run a browser, say so — do not claim a step passed without observing it.
- **Branch:** work on `feat/lead-aufnahmebogen-rework` (already checked out). Commit after every task.
- **Photo limits (mirror `backend/src/upload.php`):** allowed types `image/jpeg,image/png,image/heic,image/webp,application/pdf`; downscale images to ~1600 px long edge, JPEG q≈0.8; thumbnails ~200 px.

## File Structure

Created:
- `assets/lead/html2pdf.bundle.min.js` — vendored PDF library (0.10.2).
- `assets/lead/dm-sans-400.woff2`, `dm-sans-500.woff2`, `dm-sans-600.woff2`, `dm-sans-700.woff2`, `dm-sans-800.woff2` — vendored font weights.
- `assets/lead/manifest.json` — PWA manifest.
- `assets/lead/sw.js` — service worker (precache shell + companions).
- `assets/lead/icon-512.png` — app/install icon (copied from existing `assets/logo.png`).

Rewritten:
- `Lead-Aufnahmebogen.html` — the entire app (replaces the current file).

Untouched: `backend/**` (the leads endpoint is a separate follow-up sub-project), `index.html`, `angebot/**`, `script.js`, `styles.css`.

## Conventions (used by every render + handler task)

**No inline event handlers.** All interactivity flows through three delegated listeners on `#app` (`click`, `input`, `change`) added once in Task 3. Elements carry `data-*` attributes:

| Interaction | Markup |
|---|---|
| Text/date/textarea value | `<input data-field="ID">` / `<textarea data-field="ID">` |
| Radio option | `<button type="button" data-act="radio" data-field="ID" data-val="V">` |
| Checkbox option | `<button type="button" data-act="check" data-field="ID" data-val="V">` |
| Section header toggle | `<button data-act="toggle" data-sec="SEC">` |
| Nav jump | `<button data-act="nav" data-sec="SEC">` |
| Add roof / remove roof | `data-act="roof-add"` / `data-act="roof-del" data-idx="N"` |
| Roof field value | `<input data-roof="N" data-rfield="FID">` |
| Remove photo | `data-act="photo-del" data-id="PID"` |
| Photo category (change) | `<select data-act="photo-cat" data-id="PID">` |
| Show-all toggle | `data-act="showall"` |
| Toolbar buttons | `data-act="save｜new｜load｜reset｜export｜import｜pdf｜send｜print"` |
| Load saved entry / close modal | `data-act="loadentry" data-idx="N"` / `data-act="closemodal"` |
| Retry send | `data-act="send"` (reused) |

**Container ids for targeted updates (no full re-render):** each option group renders inside `<div class="optgroup" id="grp-ID">`; each section badge is `#badge-SEC`; roofs list is `#roofs`; photo grid is `#photo-grid`; progress bar `#progress-fill` / `#progress-text`.

---

## Task 1: Vendor assets + PWA companions

**Files:**
- Create: `assets/lead/html2pdf.bundle.min.js`, `assets/lead/dm-sans-{400,500,600,700,800}.woff2`, `assets/lead/icon-512.png`, `assets/lead/manifest.json`, `assets/lead/sw.js`

**Interfaces:**
- Produces: the vendored `assets/lead/` directory referenced by `Lead-Aufnahmebogen.html` (Task 2) and precached by `sw.js`.

- [ ] **Step 1: Create the directory and vendor the PDF library**

```bash
mkdir -p assets/lead
curl -fsSL -o assets/lead/html2pdf.bundle.min.js \
  https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.2/html2pdf.bundle.min.js
```

Expected: a ~1 MB JS file. Verify it is real JS, not an error page:
```bash
head -c 60 assets/lead/html2pdf.bundle.min.js; echo
wc -c assets/lead/html2pdf.bundle.min.js
```
Should start with something like `/*! html2pdf...` or minified code, and be > 500000 bytes.

- [ ] **Step 2: Vendor the DM Sans woff2 weights**

Google's `css2` endpoint returns different `src` URLs per User-Agent; a modern-browser UA yields woff2. Fetch the CSS, then download each weight's woff2. Run:

```bash
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36'
curl -fsSL -A "$UA" \
  'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap' \
  -o /tmp/dmsans.css
grep -oE 'https://[^ )]+\.woff2' /tmp/dmsans.css
```

The output lists woff2 URLs in weight order (there may be several per weight for different unicode ranges — pick the `latin` range block; it is the last `/* latin */` group for each `font-weight`). Download one file per weight and save with these exact names:

```bash
# Replace each URL below with the corresponding 'latin' woff2 URL from the grep output,
# matched by the font-weight in the preceding @font-face block.
curl -fsSL -o assets/lead/dm-sans-400.woff2 '<latin-woff2-url-for-weight-400>'
curl -fsSL -o assets/lead/dm-sans-500.woff2 '<latin-woff2-url-for-weight-500>'
curl -fsSL -o assets/lead/dm-sans-600.woff2 '<latin-woff2-url-for-weight-600>'
curl -fsSL -o assets/lead/dm-sans-700.woff2 '<latin-woff2-url-for-weight-700>'
curl -fsSL -o assets/lead/dm-sans-800.woff2 '<latin-woff2-url-for-weight-800>'
```

Verify each is a real woff2 (starts with `wOF2`) and non-trivial in size:
```bash
for w in 400 500 600 700 800; do printf '%s ' $w; head -c 4 assets/lead/dm-sans-$w.woff2; echo " $(wc -c < assets/lead/dm-sans-$w.woff2)"; done
```
Each line should show `wOF2` and a size of a few tens of KB.

- [ ] **Step 3: Create the install icon**

Reuse the existing logo (already square-ish brand art):
```bash
cp assets/logo.png assets/lead/icon-512.png
```
Expected: file copied. (A dedicated maskable icon can be produced later; this is sufficient for install.)

- [ ] **Step 4: Create the web app manifest**

Create `assets/lead/manifest.json`:

```json
{
  "name": "Lead-Aufnahmebogen",
  "short_name": "Lead-Bogen",
  "description": "Technik- & Instandsetzungs GmbH – Lead-Aufnahme im Außendienst",
  "start_url": "../../Lead-Aufnahmebogen.html",
  "scope": "../../",
  "display": "standalone",
  "orientation": "portrait",
  "background_color": "#f0f3f7",
  "theme_color": "#163d64",
  "icons": [
    { "src": "icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" }
  ]
}
```

- [ ] **Step 5: Create the service worker**

Create `assets/lead/sw.js`:

```js
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
```

- [ ] **Step 6: Verify the files exist**

```bash
ls -la assets/lead/
```
Expected: `html2pdf.bundle.min.js`, five `dm-sans-*.woff2`, `icon-512.png`, `manifest.json`, `sw.js`.

- [ ] **Step 7: Commit**

```bash
git add assets/lead/
git commit -m "feat(lead): vendor fonts + html2pdf and add PWA manifest/service worker"
```

---

## Task 2: New HTML shell + design system + SW registration

Replace the entire `Lead-Aufnahmebogen.html` with the new shell: head (local font, manifest, theme), the full inline CSS design system (mobile-first), a static header, an empty `.layout`, toast + modal containers, and a `<script>` scaffold that registers the service worker and calls `boot()` (a stub for now).

**Files:**
- Rewrite: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Produces: the `#app` root, `.layout` container, `#toast`, `#modal`; global CSS tokens/classes; `API_BASE`; an empty `boot()` that later tasks fill in.

- [ ] **Step 1: Replace the file with the shell**

Overwrite `Lead-Aufnahmebogen.html` with:

```html
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#163d64">
<title>Lead-Aufnahmebogen · Technik- &amp; Instandsetzungs GmbH</title>
<link rel="manifest" href="assets/lead/manifest.json">
<link rel="apple-touch-icon" href="assets/lead/icon-512.png">
<script defer src="assets/lead/html2pdf.bundle.min.js"></script>
<style>
/* ---- Fonts (vendored, no CDN) ---- */
@font-face{font-family:'DM Sans';font-weight:400;font-display:swap;src:url('assets/lead/dm-sans-400.woff2') format('woff2')}
@font-face{font-family:'DM Sans';font-weight:500;font-display:swap;src:url('assets/lead/dm-sans-500.woff2') format('woff2')}
@font-face{font-family:'DM Sans';font-weight:600;font-display:swap;src:url('assets/lead/dm-sans-600.woff2') format('woff2')}
@font-face{font-family:'DM Sans';font-weight:700;font-display:swap;src:url('assets/lead/dm-sans-700.woff2') format('woff2')}
@font-face{font-family:'DM Sans';font-weight:800;font-display:swap;src:url('assets/lead/dm-sans-800.woff2') format('woff2')}

/* ---- Design tokens ---- */
:root{
  --brand:#1f5f9e; --brand-2:#2264a8; --brand-dark:#163d64; --brand-light:#e8f1fa;
  --green:#1e8449; --green-light:#e8f8ef; --amber:#c77700; --red:#c0392b;
  --bg:#eef2f7; --card:#ffffff; --border:#cfd8e3; --text:#1e2a37; --muted:#5a6b7b;
  --radius:14px; --radius-sm:9px; --shadow:0 1px 3px rgba(20,40,70,.08),0 6px 20px rgba(20,40,70,.05);
  --tap:44px;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--bg);color:var(--text);line-height:1.5;font-size:16px}
button{font-family:inherit}

/* ---- Header ---- */
.hdr{position:sticky;top:0;z-index:50;background:linear-gradient(135deg,var(--brand-dark),var(--brand-2));
  color:#fff;padding:10px 14px;padding-top:calc(10px + env(safe-area-inset-top));
  display:flex;align-items:center;gap:10px;box-shadow:0 2px 14px rgba(0,0,0,.18)}
.hdr .brand{width:34px;height:34px;border-radius:8px;object-fit:contain;background:#fff;padding:3px;flex:none}
.hdr .titles{flex:1;min-width:0}
.hdr h1{font-size:16px;font-weight:800;letter-spacing:.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hdr .sub{font-size:11px;opacity:.8}
.hdr .saved{font-size:11px;opacity:.9;white-space:nowrap}

/* ---- Layout ---- */
.layout{max-width:1080px;margin:14px auto 96px;padding:0 12px;display:flex;gap:16px;align-items:flex-start}
.sidebar{position:sticky;top:70px;width:210px;min-width:210px;background:var(--card);border-radius:var(--radius);
  box-shadow:var(--shadow);padding:12px 0;align-self:flex-start}
.sidebar .label{padding:0 14px 8px;font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:1.2px}
.sidebar button{display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:8px 12px;border:none;
  cursor:pointer;font-size:13px;color:#425364;background:transparent;border-left:3px solid transparent}
.sidebar button:hover{background:#f4f8fc}
.sidebar button.active{font-weight:700;color:var(--brand);background:var(--brand-light);border-left-color:var(--brand)}
.sidebar button.dim{opacity:.45}
.sidebar .badge{margin-left:auto;font-size:10px;font-weight:700;color:var(--muted)}
.main{flex:1;min-width:0}

/* mobile section jumper (hidden on desktop) */
.jumper{display:none;position:sticky;top:64px;z-index:40;margin:0 0 12px;padding-top:12px;background:var(--bg)}
.jumper select{width:100%;padding:11px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);
  font-size:15px;background:var(--card);font-family:inherit;color:var(--text)}
.showall-row{margin-top:8px}
.showall-row button{width:100%;padding:9px;border:1.5px dashed var(--border);border-radius:var(--radius-sm);
  background:var(--card);color:var(--brand);font-weight:600;font-size:13px;cursor:pointer}

/* progress */
.progress-wrap{margin:12px 14px 4px}
.progress-bar{background:#e3e9f0;border-radius:5px;height:7px;overflow:hidden}
.progress-fill{height:100%;border-radius:5px;transition:width .35s,background .35s}
.progress-text{font-size:10px;color:var(--muted);margin-top:4px}

/* ---- Section cards (collapsible) ---- */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:12px;overflow:hidden}
.card.hidden{display:none}
.sec-head{width:100%;display:flex;align-items:center;gap:10px;padding:14px 16px;border:none;background:transparent;
  cursor:pointer;text-align:left;min-height:var(--tap)}
.sec-head .ic{font-size:18px;flex:none}
.sec-head .t{font-weight:700;font-size:15px;color:var(--brand-dark);flex:1;min-width:0}
.sec-head .badge{font-size:11px;font-weight:700;color:var(--muted);background:#eef2f7;border-radius:20px;padding:2px 9px}
.sec-head .badge.done{color:var(--green);background:var(--green-light)}
.sec-head .chev{transition:transform .2s;color:var(--muted);flex:none}
.card.open .sec-head .chev{transform:rotate(180deg)}
.sec-body{padding:0 16px 14px;display:none}
.card.open .sec-body{display:block}
.sub-header{color:var(--brand);font-weight:700;font-size:13px;border-bottom:2px solid var(--brand-light);
  padding-bottom:4px;margin:16px 0 10px;text-transform:uppercase;letter-spacing:.4px}

/* ---- Fields ---- */
.row{display:flex;gap:12px;flex-wrap:wrap}
.field{flex:1 1 45%;min-width:150px;margin-bottom:12px}
.field.full{flex:1 1 100%}
.field label{display:block;font-size:12px;font-weight:600;color:#41505f;margin-bottom:4px}
.field input,.field textarea,.field select{width:100%;padding:11px 12px;border:1.5px solid var(--border);
  border-radius:var(--radius-sm);font-size:16px;background:#fbfcfe;outline:none;font-family:inherit;color:var(--text);
  transition:border-color .15s,box-shadow .15s}
.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(31,95,158,.14)}
.field textarea{resize:vertical;min-height:110px}

/* option chips */
.optgroup{margin-bottom:14px}
.optgroup > .lbl{display:block;font-size:12px;font-weight:600;color:#41505f;margin-bottom:6px}
.opts{display:flex;flex-wrap:wrap;gap:8px}
.opt{display:inline-flex;align-items:center;gap:7px;min-height:var(--tap);padding:8px 14px;border-radius:22px;
  border:1.5px solid var(--border);background:#fbfcfe;cursor:pointer;font-size:14px;color:var(--text);user-select:none}
.opt.check{border-radius:10px}
.opt.sel{border:2px solid var(--brand);background:var(--brand-light);font-weight:600;color:var(--brand-dark)}
.opt .mk{width:16px;height:16px;border-radius:50%;border:2px solid #aeb9c6;display:inline-flex;align-items:center;justify-content:center;flex:none}
.opt.check .mk{border-radius:4px}
.opt.sel .mk{border-color:var(--brand);background:var(--brand)}
.opt .mk svg{display:none}
.opt.sel .mk svg{display:block}

/* roofs */
.roof{border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:10px;background:#fbfcfe}
.roof-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.roof-head .rt{font-weight:700;font-size:13px;color:var(--brand-dark)}
.icon-btn{border:none;background:transparent;cursor:pointer;color:var(--red);font-size:14px;
  min-height:var(--tap);min-width:var(--tap);border-radius:8px}
.icon-btn:hover{background:#fbeceb}
.add-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border:1.5px dashed var(--brand);
  border-radius:var(--radius-sm);background:var(--brand-light);color:var(--brand);font-weight:700;font-size:14px;cursor:pointer}

/* photos */
.photo-tools{display:flex;flex-wrap:wrap;gap:8px;margin:6px 0 12px}
.photo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px}
.pcard{border:1.5px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:#fbfcfe;display:flex;flex-direction:column}
.pcard .thumb{width:100%;aspect-ratio:4/3;object-fit:cover;background:#e9eef4;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:26px}
.pcard .meta{padding:6px 8px;font-size:11px}
.pcard .nm{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#41505f}
.pcard select{width:100%;margin-top:4px;font-size:11px;padding:4px;border:1px solid var(--border);border-radius:6px;font-family:inherit}
.pcard .rm{align-self:flex-end;color:var(--red);border:none;background:transparent;cursor:pointer;font-size:11px;padding:4px 6px}

/* signatures */
.sig-row{display:flex;gap:24px;flex-wrap:wrap;margin-top:12px}
.sig-field{flex:1 1 40%;min-width:180px}
.sig-field input{width:100%;padding:12px 4px;border:none;border-bottom:2px solid #33414f;font-size:17px;
  font-style:italic;background:transparent;outline:none;font-family:inherit}
.sig-field .cap{font-size:11px;color:var(--muted);margin-top:4px}
.consent{font-size:12px;color:var(--muted);line-height:1.7;margin-top:8px}

/* ---- Bottom action bar ---- */
.actionbar{position:fixed;left:0;right:0;bottom:0;z-index:60;background:rgba(255,255,255,.96);
  backdrop-filter:blur(6px);border-top:1px solid var(--border);display:flex;gap:8px;
  padding:10px 12px;padding-bottom:calc(10px + env(safe-area-inset-bottom))}
.btn{flex:1 1 auto;min-height:var(--tap);border:none;border-radius:var(--radius-sm);cursor:pointer;
  font-size:14px;font-weight:700;color:#fff;padding:10px 12px;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.btn.save{background:linear-gradient(135deg,#1e8449,#27ae60)}
.btn.send{background:linear-gradient(135deg,#1f5f9e,#2264a8)}
.btn.more{flex:0 0 auto;background:#eef2f7;color:var(--brand-dark)}
.btn.sent{background:linear-gradient(135deg,#0f6b39,#1e8449)}
.btn.unsent{background:linear-gradient(135deg,#a15a00,#c77700)}

/* toast + modal */
.toast{position:fixed;left:50%;transform:translateX(-50%);bottom:84px;z-index:9999;padding:12px 20px;border-radius:12px;
  color:#fff;font-size:14px;font-weight:600;box-shadow:0 6px 24px rgba(0,0,0,.25);display:none;max-width:90%}
.modal-bg{position:fixed;inset:0;background:rgba(15,25,40,.55);z-index:1000;display:flex;align-items:flex-end;justify-content:center}
.modal{background:#fff;border-radius:16px 16px 0 0;padding:20px;width:100%;max-width:560px;max-height:80vh;overflow:auto;
  box-shadow:0 -10px 40px rgba(0,0,0,.3)}
.modal h3{margin-bottom:12px;color:var(--brand-dark);font-size:17px}
.modal-item{padding:12px;border-radius:10px;margin-bottom:8px;border:1.5px solid var(--border);cursor:pointer;background:#fbfcfe}
.modal-item:hover{background:var(--brand-light)}
.modal-item .name{font-weight:700;font-size:14px}
.modal-item .info{font-size:11px;color:var(--muted);margin-top:2px}
.modal .close{width:100%;margin-top:6px;min-height:var(--tap);border:1.5px solid var(--border);border-radius:var(--radius-sm);
  background:#fff;color:#41505f;font-weight:600;cursor:pointer}
.menu-item{width:100%;text-align:left;padding:12px;border:none;border-bottom:1px solid #eef2f7;background:#fff;font-size:15px;cursor:pointer;display:flex;gap:10px;align-items:center}

/* ---- Responsive ---- */
@media (max-width:860px){
  .sidebar{display:none}
  .layout{flex-direction:column;margin-top:0;padding:0 10px}
  .jumper{display:block}
  .field{flex:1 1 100%}
}

/* ---- Print / PDF document (built off-screen) ---- */
#pdf-doc{display:none}
</style>
</head>
<body>
<div id="app"></div>
<div class="toast" id="toast"></div>
<div id="modal"></div>
<div id="pdf-mount"></div>

<script>
"use strict";
const API_BASE = "https://api.technik-prignitz.de";

/* ============ MODULES (filled in across tasks) ============ */
// Schema (Task 3) · State (Task 3) · Storage (Task 3, 9) · Image (Task 9)
// Render (Task 3-8) · Actions (Task 10) · PDF (Task 11) · Backend (Task 12)

function esc(s){const d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML;}

function boot(){
  document.getElementById('app').innerHTML =
    '<div class="hdr">'
    + '<img class="brand" src="assets/lead/icon-512.png" alt="">'
    + '<div class="titles"><h1>Lead-Aufnahmebogen</h1>'
    + '<div class="sub">Technik- &amp; Instandsetzungs GmbH · PV · WP · Speicher · Wallbox</div></div>'
    + '<div class="saved" id="saved-ind"></div>'
    + '</div>'
    + '<div class="layout"><div class="main"><p style="padding:24px;color:var(--muted)">…</p></div></div>';
}

/* service worker */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('assets/lead/sw.js').catch(()=>{}));
}

boot();
</script>
</body>
</html>
```

- [ ] **Step 2: Manual verification**

Serve the repo root over HTTP (e.g. `python3 -m http.server 8000` from the project root) and open `http://localhost:8000/Lead-Aufnahmebogen.html`. Confirm:
- The header renders in **DM Sans** (not a system fallback) with the logo tile.
- DevTools → **Network**: no requests to `fonts.googleapis.com` or `cdnjs.cloudflare.com`. The font requests resolve to `assets/lead/dm-sans-*.woff2` and `html2pdf.bundle.min.js` loads locally.
- DevTools → **Application → Service Workers**: `sw.js` is registered/activated (may need one reload).
- No console errors.

> If you cannot run a local server, state that; do not mark this verified.

- [ ] **Step 3: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): new mobile-first shell, vendored font, SW registration"
```

---

## Task 3: Schema, state, localStorage, and basic field rendering

Add the section schema, in-memory state, localStorage persistence, the three delegated listeners, and rendering for the non-option field types (`text`/`date`/`textarea`/`row`/`subheader`). Sections render as **open** cards for now (collapsible behavior arrives in Task 5). Typing autosaves and survives reload without losing focus.

**Files:**
- Modify: `Lead-Aufnahmebogen.html` (replace the module region + `boot()`)

**Interfaces:**
- Produces: `SECTIONS`, `GEWERKE`, `ROOF_FIELDS`, `PHOTO_CATEGORIES`, `state`, `saveDraft()`, `loadDraft()`, `newLeadId()`, `getFieldIds()`, `isFilled(v)`, `renderField(f)`, `renderLayout()`, `updateProgress()`, and the delegated `onClick/onInput/onChange` handlers (extended by later tasks).
- Consumes: `esc()` from Task 2.

- [ ] **Step 1: Replace the module region**

In `Lead-Aufnahmebogen.html`, replace everything between `/* ============ MODULES` and the `boot();` call (i.e. the placeholder `boot()` + SW block stay, but the SW registration and final `boot();` are re-added at the end below) with the following. Concretely: replace the current `function esc(...)`, `function boot(){...}`, the service-worker block, and the trailing `boot();` with:

```js
function esc(s){const d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML;}

/* ============ SCHEMA ============ */
const GEWERKE = ["Photovoltaik","Batteriespeicher","Wärmepumpe","Wallbox / Ladesäule","Austausch Anlage","Erweiterung PV","Notstrom / Insel"];
const ROOF_FIELDS = [
  {id:"azimut",label:"Ausrichtung",ph:"z.B. Süd / 180°"},{id:"neigung",label:"Neigung (°)"},
  {id:"laenge",label:"Länge (m)"},{id:"breite",label:"Breite (m)"},
  {id:"verschattung",label:"Verschattung"},{id:"module",label:"Module (ca.)"}
];
const PHOTO_CATEGORIES = ["Dach","Zählerschrank","Hausanschluss","Heizraum","WP-Platz","Sparren","Garage/Wallbox","Grundriss","Stromrechnung","Sonstiges"];

// trade: section only auto-expands when this gewerk is selected (stays collapsed otherwise, never hidden)
const SECTIONS = [
  { id:"kunde", num:1, title:"Kundendaten", icon:"👤", fields:[
    {id:"anrede",label:"Anrede",type:"radio",options:["Herr","Frau","Firma","Divers"]},
    {type:"row",children:[{id:"vorname",label:"Vorname",type:"text"},{id:"nachname",label:"Nachname",type:"text"}]},
    {type:"row",children:[{id:"firma",label:"Firma",type:"text"},{id:"funktion",label:"Funktion / Titel",type:"text"}]},
    {type:"row",children:[{id:"strasse",label:"Straße, Nr.",type:"text"},{id:"plzort",label:"PLZ, Ort",type:"text"}]},
    {type:"row",children:[{id:"telefon",label:"Telefon",type:"text"},{id:"mobil",label:"Mobil",type:"text"}]},
    {type:"row",children:[{id:"email",label:"E-Mail",type:"text"},{id:"fax",label:"Fax",type:"text"}]},
    {id:"abw_adresse",label:"Abweichende Objektadresse",type:"radio",options:["Nein","Ja"]},
    {id:"objektadresse",label:"Objektadresse",type:"text",full:true},
  ]},
  { id:"interesse", num:2, title:"Interesse / Gewerke", icon:"⚡", fields:[
    {id:"gewerke",label:"Welche Systeme?",type:"checkbox",options:GEWERKE},
    {id:"zeithorizont",label:"Zeithorizont",type:"radio",options:["< 3 Mon.","3–6 Mon.","6–12 Mon.","> 12 Mon."]},
    {id:"entscheidung",label:"Entscheidung",type:"radio",options:["Ja","Vergleichsangebote","Info-Phase"]},
    {id:"finanzierung",label:"Finanzierung",type:"radio",options:["Eigenmittel","Finanzierung","KfW prüfen"]},
  ]},
  { id:"objekt", num:3, title:"Objekt / Gebäude", icon:"🏠", fields:[
    {id:"gebaeudetyp",label:"Gebäudetyp",type:"checkbox",options:["EFH","MFH","Reihenhaus","Gewerbe/Halle","Büro","Landwirtschaft","Öffentlich","Sonstiges"]},
    {type:"row",children:[{id:"baujahr",label:"Baujahr",type:"text"},{id:"wohneinheiten",label:"Wohneinheiten",type:"text"}]},
    {type:"row",children:[{id:"nutzflaeche",label:"Nutzfläche (m²)",type:"text"},{id:"gebaeudehoehe",label:"Höhe (m)",type:"text"}]},
    {type:"row",children:[{id:"wandaufbau",label:"Wandaufbau",type:"text"},{id:"denkmalschutz",label:"Denkmalschutz",type:"text",ph:"Ja/Nein"}]},
    {type:"subheader",label:"Dach"},
    {id:"anz_dachflaechen",label:"Belegung",type:"radio",options:["1 Dachfläche","2 Dachflächen (z.B. Ost/West)","3+ Dachflächen"]},
    {id:"dachform",label:"Dachform",type:"checkbox",options:["Satteldach","Walmdach","Pultdach","Flachdach","Krüppelwalm","Sonstiges"]},
    {id:"dacheindeckung",label:"Eindeckung",type:"checkbox",options:["Ziegel","Tegalit","Trapezblech","Schiefer","Bitumen/Folie","Sonstiges"]},
    {type:"subheader",label:"Dachflächen"},
    {type:"roofs",id:"roofs"},
    {type:"subheader",label:"Dachkonstruktion"},
    {type:"row",children:[{id:"sparrenabstand",label:"Sparrenabstand (cm)",type:"text"},{id:"sparrenstaerke",label:"Sparrenstärke (mm)",type:"text"}]},
    {type:"row",children:[{id:"blitzschutz",label:"Blitzschutz?",type:"text"},{id:"dach_zustand",label:"Dachzustand",type:"text",ph:"gut / sanierungsbed."}]},
    {id:"geruestbedarf",label:"Gerüstbedarf",type:"radio",options:["Nein","Ja","Kunde stellt"]},
    {id:"lager",label:"Lagermöglichkeit",type:"radio",options:["Nein","Ja"]},
  ]},
  { id:"strom", num:4, title:"Stromverbrauch / Bestand", icon:"🔌", fields:[
    {type:"row",children:[{id:"jahresverbrauch",label:"Jahresverbrauch (kWh)",type:"text"},{id:"stromkosten",label:"Stromkosten (€/a)",type:"text"}]},
    {type:"row",children:[{id:"arbeitspreis",label:"Arbeitspreis (ct/kWh)",type:"text"},{id:"grundpreis",label:"Grundpreis (€/Mon.)",type:"text"}]},
    {type:"row",children:[{id:"versorger",label:"Energieversorger",type:"text"},{id:"zaehler_nr",label:"Zähler-Nr.",type:"text"}]},
    {type:"row",children:[{id:"zaehlerschrank_bj",label:"Zählerschrank BJ",type:"text"},{id:"anz_zaehler",label:"Anz. Zähler",type:"text"}]},
    {id:"zs_zustand",label:"Zählerschrank-Zustand",type:"radio",options:["OK","Prüfung nötig","Erneuerung nötig"]},
    {id:"freier_platz",label:"Freier Zählerplatz",type:"radio",options:["Ja","Nein"]},
    {id:"zusatzverbraucher",label:"Zusatzverbraucher",type:"checkbox",options:["Wärmepumpe","Wallbox","Klimaanlage","Pool/Sauna"]},
    {type:"subheader",label:"Bestehende PV-Anlage"},
    {id:"bestandsanlage",label:"Vorhanden",type:"radio",options:["Nein","Ja"]},
    {type:"row",children:[{id:"ibn_jahr",label:"IBN (Jahr)",type:"text"},{id:"leistung_kwp",label:"Leistung (kWp)",type:"text"}]},
    {type:"row",children:[{id:"wr_typ_bestand",label:"WR Typ",type:"text"},{id:"einspeiseverg",label:"EEG (ct/kWh)",type:"text"}]},
  ]},
  { id:"pv", num:5, title:"PV-Anlage (Planung)", icon:"☀️", trade:"Photovoltaik", fields:[
    {id:"zielsetzung",label:"Zielsetzung",type:"radio",options:["Eigenverbrauch","Volleinspeisung","Autarkie/Notstrom"]},
    {type:"row",children:[{id:"gew_leistung",label:"Leistung (kWp)",type:"text"},{id:"anz_module",label:"Module (ca.)",type:"text"}]},
    {type:"row",children:[{id:"modul_praef",label:"Modul-Präferenz",type:"text"},{id:"leistungsklasse",label:"Leistungsklasse (Wp)",type:"text"}]},
    {type:"row",children:[{id:"wr_praef",label:"WR-Präferenz",type:"text"},{id:"hybrid_wr",label:"Hybrid-WR?",type:"text"}]},
    {id:"wr_typ",label:"Wechselrichter Typ",type:"radio",options:["Budget","Mittelklasse","Qualität"]},
    {id:"modul_opt",label:"Modul-Optimierer",type:"radio",options:["Nein","Ja","Nur bei Verschattung"]},
    {id:"ueberspannung",label:"Überspannungsschutz",type:"radio",options:["Typ 1+2","Typ 2","Nicht gewünscht"]},
    {id:"monitoring",label:"Monitoring",type:"checkbox",options:["LAN","WLAN","Mobilfunk"]},
    {type:"subheader",label:"Aufstellungsort WR"},
    {id:"wr_ort",label:"Standort",type:"checkbox",options:["Keller","HWR","Dachboden","Garage","Außen","Sonstiges"]},
    {type:"row",children:[{id:"kabel_dach_wr",label:"Kabel Dach→WR (m)",type:"text"},{id:"kabel_wr_zaehler",label:"Kabel WR→Zähler (m)",type:"text"}]},
  ]},
  { id:"batterie", num:6, title:"Batteriespeicher", icon:"🔋", trade:"Batteriespeicher", fields:[
    {id:"speicher_gew",label:"Speicher gewünscht",type:"radio",options:["Nein","Ja","Später nachrüstbar"]},
    {type:"row",children:[{id:"kapazitaet",label:"Kapazität (kWh)",type:"text"},{id:"bat_hersteller",label:"Hersteller",type:"text"}]},
    {id:"bat_aufstellort",label:"Aufstellort",type:"text",full:true},
    {id:"kopplung",label:"Kopplung",type:"radio",options:["AC-gekoppelt","DC-gekoppelt","Hybrid-WR"]},
    {id:"notstrom",label:"Notstrom",type:"radio",options:["Nicht gewünscht","Einphasig","3-phasig","Inselfähig"]},
  ]},
  { id:"wp", num:7, title:"Wärmepumpe", icon:"🌡️", trade:"Wärmepumpe", fields:[
    {id:"wp_bauart",label:"Bauart",type:"radio",options:["Luft-Wasser","Sole-Wasser","Wasser-Wasser","Brauchwasser-WP"]},
    {type:"subheader",label:"Bestehende Heizung"},
    {id:"heizungsart",label:"Heizungsart",type:"checkbox",options:["Öl","Gas","Holz/Pellets","Strom","Fernwärme","Wärmepumpe"]},
    {type:"row",children:[{id:"kessel_bj",label:"Kessel-BJ",type:"text"},{id:"heizlast",label:"Heizlast (kW)",type:"text"}]},
    {type:"row",children:[{id:"heiz_verbrauch",label:"Jahresverbr.",type:"text"},{id:"vorlauftemp",label:"Vorlauf (°C)",type:"text"}]},
    {id:"waermeuebergabe",label:"Wärmeübergabe",type:"checkbox",options:["Fußbodenheizung","Heizkörper","Mischsystem","Flächenheizung"]},
    {id:"warmwasser",label:"Warmwasser",type:"radio",options:["Über Heizung","Sep. Speicher","Durchlauferhitzer"]},
    {type:"row",children:[{id:"personen",label:"Personen",type:"text"},{id:"ww_verbrauch",label:"WW-Verbrauch",type:"text",ph:"gering/mittel/hoch"}]},
    {type:"subheader",label:"Gebäudehülle"},
    {id:"daemmung_dach",label:"Dach",type:"radio",options:["Keine","Teilweise","Vollständig"]},
    {id:"daemmung_fassade",label:"Fassade",type:"radio",options:["Keine","Teilweise","Vollständig"]},
    {id:"fenster",label:"Fenster",type:"radio",options:["Einfach","2-fach","3-fach"]},
    {type:"row",children:[{id:"energieausweis",label:"Energieausweis?",type:"text",ph:"Ja/Nein"},{id:"energieausweis_wert",label:"Wert kWh/(m²·a)",type:"text"}]},
    {type:"subheader",label:"Aufstellort WP"},
    {type:"row",children:[{id:"wp_aussen",label:"Außengerät",type:"text"},{id:"abstand_nachbar",label:"Abstand Nachbar (m)",type:"text"}]},
    {type:"row",children:[{id:"wp_innen",label:"Innengerät",type:"text"},{id:"leitungsweg",label:"Leitungsweg (m)",type:"text"}]},
  ]},
  { id:"wallbox", num:8, title:"Wallbox", icon:"🚗", trade:"Wallbox / Ladesäule", fields:[
    {type:"row",children:[{id:"anz_ladepunkte",label:"Ladepunkte",type:"text"},{id:"ladeleistung",label:"Leistung",type:"text",ph:"11/22kW/DC"}]},
    {id:"install_ort",label:"Installationsort",type:"checkbox",options:["Garage","Carport","Tiefgarage","Parkplatz","Fassade","Sonstiges"]},
    {type:"row",children:[{id:"efahrzeug",label:"E-Fahrzeug",type:"text"},{id:"steckertyp",label:"Stecker",type:"text"}]},
    {type:"row",children:[{id:"kabel_wallbox",label:"Kabel Zähler→Box (m)",type:"text"},{id:"befestigung",label:"Wand/Stele",type:"text"}]},
    {id:"funktionen",label:"Funktionen",type:"checkbox",options:["PV-Überschuss","Lastmgmt.","RFID","Eichrecht","App","MID-Zähler","Backup","Dyn. Lastmgmt."]},
    {id:"netzanmeldung_wb",label:"Netzanmeldung",type:"radio",options:["Wir","Kunde","Bereits erfolgt"]},
  ]},
  { id:"module", num:9, title:"PV-Module", icon:"🔲", trade:"Photovoltaik", fields:[
    {id:"modulfarbe",label:"Modulfarbe",type:"radio",options:["Schwarz","Blau"]},
    {id:"rahmenfarbe",label:"Rahmenfarbe",type:"radio",options:["Schwarz","Silber"]},
    {id:"modul_typ_qs",label:"Modul-Typ",type:"radio",options:["Budget","Mittelklasse","Qualität"]},
  ]},
  { id:"netz", num:10, title:"Netzanschluss", icon:"⚙️", fields:[
    {type:"row",children:[{id:"netzbetreiber",label:"Netzbetreiber",type:"text"},{id:"hausanschluss",label:"Hausanschluss (A)",type:"text"}]},
    {type:"row",children:[{id:"phasen",label:"Phasen",type:"text"},{id:"vorzaehlersich",label:"Vorzählersich. (A)",type:"text"}]},
    {type:"row",children:[{id:"hauptleitung",label:"Hauptleitung (mm²)",type:"text"},{id:"imsys",label:"iMSys",type:"text",ph:"Ja/Nein/Geplant"}]},
  ]},
  { id:"notizen", num:11, title:"Notizen", icon:"📝", fields:[
    {id:"notizen",label:"Besonderheiten, Kundenwünsche, technische Hinweise",type:"textarea"},
  ]},
  { id:"unterlagen", num:12, title:"Unterlagen / Fotos", icon:"📎", fields:[
    {id:"unterlagen",label:"Mitgenommen",type:"checkbox",options:["Stromrechnung","Heizkostenabr.","Grundriss","Foto Dach","Foto Zählersch.","Foto Hausanschl.","Foto Heizraum","Foto WP-Platz","Foto Sparren","Foto Garage/WB","Baupläne","Energieausweis"]},
    {type:"photos",id:"photos"},
  ]},
];

const META = { datum:{label:"Datum",type:"date"}, berater:{label:"Berater",type:"text"}, lead_nr:{label:"Lead-Nr.",type:"text"} };

/* ============ STATE ============ */
function newLeadId(){ return (self.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'l'+Date.now()+Math.random().toString(16).slice(2); }
function todayISO(){ return new Date().toISOString().split('T')[0]; }

const state = {
  leadId: newLeadId(),
  data: { datum: todayISO(), roofs:[{}], photos:[] },
  open: {},          // secId -> bool
  showAll: false,
  activeSection: 'kunde',
  sent: null,        // {serverId, sentAt} once sent
  toastTimer: null,
  savedList: [],
};

/* ============ STORAGE (localStorage) ============ */
const LS_DRAFT='lead-draft', LS_LIST='lead-list';
function saveDraft(){ try{ localStorage.setItem(LS_DRAFT, JSON.stringify({leadId:state.leadId,data:state.data,sent:state.sent})); }catch(_){} }
function loadDraft(){ try{ const s=localStorage.getItem(LS_DRAFT); return s?JSON.parse(s):null; }catch(_){ return null; } }
function loadList(){ try{ const s=localStorage.getItem(LS_LIST); return s?JSON.parse(s):[]; }catch(_){ return []; } }

/* ============ HELPERS ============ */
function isFilled(v){ return Array.isArray(v) ? v.length>0 : (v!=null && String(v).trim()!==''); }
function getFieldIds(){
  const ids=[];
  SECTIONS.forEach(s=>s.fields.forEach(function walk(f){
    if(f.type==='row') return f.children.forEach(walk);
    if(f.type==='subheader'||f.type==='roofs'||f.type==='photos') return;
    if(f.id) ids.push(f.id);
  }));
  return ids;
}
function sectionFieldIds(sec){
  const ids=[];
  sec.fields.forEach(function walk(f){
    if(f.type==='row') return f.children.forEach(walk);
    if(f.type==='subheader'||f.type==='roofs'||f.type==='photos') return;
    if(f.id) ids.push(f.id);
  });
  return ids;
}
function sectionProgress(sec){
  const ids=sectionFieldIds(sec);
  const filled=ids.filter(id=>isFilled(state.data[id])).length;
  return {filled, total:ids.length};
}
function getProgress(){
  const ids=getFieldIds();
  const filled=ids.filter(id=>isFilled(state.data[id])).length;
  return ids.length ? (filled/ids.length)*100 : 0;
}

/* ============ RENDER: fields ============ */
function renderField(f){
  if(f.type==='subheader') return '<div class="sub-header">'+esc(f.label)+'</div>';
  if(f.type==='row') return '<div class="row">'+f.children.map(renderField).join('')+'</div>';
  if(f.type==='roofs') return '<div id="roofs"></div>';   // filled in Task 8
  if(f.type==='photos') return '<div id="photos-block"></div>'; // filled in Task 10
  if(f.type==='text'||f.type==='date'){
    const v=state.data[f.id]||'';
    return '<div class="field'+(f.full?' full':'')+'"><label>'+esc(f.label)+'</label>'
      +'<input type="'+f.type+'" value="'+esc(v)+'" placeholder="'+esc(f.ph||'')+'" data-field="'+f.id+'"></div>';
  }
  if(f.type==='textarea'){
    const v=state.data[f.id]||'';
    return '<div class="field full"><label>'+esc(f.label)+'</label><textarea data-field="'+f.id+'">'+esc(v)+'</textarea></div>';
  }
  if(f.type==='radio'||f.type==='checkbox') return renderGroup(f); // Task 4
  return '';
}
function renderGroup(f){ return '<div class="optgroup" id="grp-'+f.id+'"></div>'; } // replaced in Task 4

/* ============ RENDER: layout ============ */
function renderLayout(){
  let main='<div class="main">';
  // meta card
  main+='<div class="card open"><div class="sec-body" style="padding-top:14px"><div class="row">'
    + Object.keys(META).map(k=>{
        const m=META[k], v=state.data[k]||'';
        return '<div class="field"><label>'+esc(m.label)+'</label><input type="'+m.type+'" value="'+esc(v)+'" data-field="'+k+'"></div>';
      }).join('')
    + '</div></div></div>';
  // sections
  SECTIONS.forEach(s=>{
    main+='<div class="card open" id="sec-'+s.id+'">'
      + '<div class="sec-body">'
      + s.fields.map(renderField).join('')
      + '</div></div>';
  });
  main+='</div>';
  document.querySelector('.layout').innerHTML = main;
  renderGroupsInto();      // Task 4
  updateProgress();
}
function renderGroupsInto(){ /* no-op until Task 4 */ }

/* ============ PROGRESS ============ */
function updateProgress(){
  const p=getProgress();
  const c = p<30 ? 'var(--red)' : p<70 ? 'var(--amber)' : 'var(--green)';
  const fill=document.getElementById('progress-fill'), txt=document.getElementById('progress-text');
  if(fill){ fill.style.width=p+'%'; fill.style.background=c; }
  if(txt){ txt.textContent=Math.round(p)+'% ausgefüllt'; }
  SECTIONS.forEach(s=>{
    const b=document.getElementById('badge-'+s.id); if(!b) return;
    const {filled,total}=sectionProgress(s);
    b.textContent=filled+' / '+total;
    b.classList.toggle('done', total>0 && filled===total);
  });
  const si=document.getElementById('saved-ind'); if(si) si.textContent='gespeichert';
}

/* ============ DELEGATED EVENTS ============ */
function onInput(e){
  const el=e.target;
  if(el.dataset.field){ state.data[el.dataset.field]=el.value; saveDraft(); updateProgress(); return; }
  if(el.dataset.roof!=null){ onRoofInput(el); return; } // Task 8
}
function onClick(e){
  const a=e.target.closest('[data-act]'); if(!a) return;
  const act=a.dataset.act;
  if(act==='radio') return onRadio(a.dataset.field, a.dataset.val);   // Task 4
  if(act==='check') return onCheck(a.dataset.field, a.dataset.val);   // Task 4
  // later: toggle, nav, showall, roof-add, roof-del, photo-del, save, new, load, reset, export, import, pdf, send, print, loadentry, closemodal
  if(typeof onClickExtra==='function') onClickExtra(act, a, e);
}
function onChange(e){ if(typeof onChangeExtra==='function') onChangeExtra(e); }

/* ============ TOAST ============ */
function showToast(msg,type){
  clearTimeout(state.toastTimer);
  const el=document.getElementById('toast');
  el.textContent=msg;
  el.style.background = type==='success' ? 'var(--green)' : type==='error' ? 'var(--red)' : 'var(--brand-2)';
  el.style.display='block';
  state.toastTimer=setTimeout(()=>el.style.display='none', 2600);
}

/* ============ BOOT ============ */
function boot(){
  const d=loadDraft();
  if(d && d.data){ state.leadId=d.leadId||state.leadId; state.data=Object.assign({datum:todayISO(),roofs:[{}],photos:[]}, d.data); state.sent=d.sent||null; }
  if(!Array.isArray(state.data.roofs)||!state.data.roofs.length) state.data.roofs=[{}];
  if(!Array.isArray(state.data.photos)) state.data.photos=[];

  document.getElementById('app').innerHTML =
    '<div class="hdr">'
    + '<img class="brand" src="assets/lead/icon-512.png" alt="">'
    + '<div class="titles"><h1>Lead-Aufnahmebogen</h1>'
    + '<div class="sub">Technik- &amp; Instandsetzungs GmbH · PV · WP · Speicher · Wallbox</div></div>'
    + '<div class="saved" id="saved-ind"></div>'
    + '</div>'
    + '<div class="layout"></div>';

  const app=document.getElementById('app');
  app.addEventListener('input', onInput);
  app.addEventListener('click', onClick);
  app.addEventListener('change', onChange);

  renderLayout();
  if(d) setTimeout(()=>showToast('Letzten Stand geladen','success'), 400);
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('assets/lead/sw.js').catch(()=>{}));
}
boot();
```

- [ ] **Step 2: Manual verification**

Reload the page. Confirm:
- The meta row (Datum/Berater/Lead-Nr.) and all 12 sections render, with their text/date/textarea/row fields (option groups appear empty for now — Task 4).
- Datum is pre-filled with today.
- Type into several fields (e.g. Vorname, Notizen), reload the page → values persist and **focus is retained while typing** (no full-page rebuild on each keystroke). The header shows "gespeichert".

- [ ] **Step 3: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): schema, state, localStorage, delegated field rendering"
```

---

## Task 4: Radio & checkbox option groups (targeted updates)

Implement `renderGroup`, `onRadio`, `onCheck`, and `renderGroupsInto` so option groups render as chips and toggling one updates **only that group** (no full re-render, no focus loss).

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `state`, `renderField`, `updateProgress`, `saveDraft` (Task 3).
- Produces: `groupHTML(f)`, `onRadio(id,val)`, `onCheck(id,val)`, `applyConditional()` hook call (defined Task 7 — guarded).

- [ ] **Step 1: Replace `renderGroup` and `renderGroupsInto`, add handlers**

Find in the script (from Task 3):

```js
function renderGroup(f){ return '<div class="optgroup" id="grp-'+f.id+'"></div>'; } // replaced in Task 4
```

Replace with:

```js
const CHECK_SVG='<svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4L3.5 6.5L9 1" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
function optHTML(f,o){
  const isCheck=f.type==='checkbox';
  const sel=isCheck ? (state.data[f.id]||[]).includes(o) : state.data[f.id]===o;
  return '<button type="button" class="opt'+(isCheck?' check':'')+(sel?' sel':'')+'" '
    + 'data-act="'+(isCheck?'check':'radio')+'" data-field="'+f.id+'" data-val="'+esc(o)+'">'
    + '<span class="mk">'+(isCheck?CHECK_SVG:'')+'</span>'+esc(o)+'</button>';
}
function groupInner(f){
  return '<span class="lbl">'+esc(f.label)+'</span><div class="opts">'
    + f.options.map(o=>optHTML(f,o)).join('')+'</div>';
}
function renderGroup(f){ return '<div class="optgroup" id="grp-'+f.id+'">'+groupInner(f)+'</div>'; }
function renderGroupsInto(){ /* groups now render inline via renderGroup; nothing to backfill */ }
function refreshGroup(id){
  const el=document.getElementById('grp-'+id);
  const f=findField(id); if(el&&f) el.innerHTML=groupInner(f);
}
function findField(id){
  let found=null;
  SECTIONS.forEach(s=>s.fields.forEach(function walk(f){
    if(found) return;
    if(f.type==='row') return f.children.forEach(walk);
    if(f.id===id) found=f;
  }));
  return found;
}
function onRadio(id,val){
  state.data[id] = (state.data[id]===val) ? '' : val;
  saveDraft(); refreshGroup(id); updateProgress();
  if(id==='gewerke' && typeof applyConditional==='function') applyConditional();
}
function onCheck(id,val){
  const arr = Array.isArray(state.data[id]) ? state.data[id].slice() : [];
  const i=arr.indexOf(val);
  if(i>=0) arr.splice(i,1); else arr.push(val);
  state.data[id]=arr;
  saveDraft(); refreshGroup(id); updateProgress();
  if(id==='gewerke' && typeof applyConditional==='function') applyConditional();
}
```

- [ ] **Step 2: Manual verification**

Reload. Confirm:
- Anrede (radio) and gewerke (checkbox) render as chips.
- Clicking a radio selects it (blue), clicking again clears it; only one selectable.
- Checkboxes toggle independently and show the check mark.
- While a text field has focus, clicking a chip in another group does **not** blur the text field's caret position (only the chip group updates).
- Section badges (once Task 5 adds them) / overall progress change as you select.

- [ ] **Step 3: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): radio/checkbox chips with targeted group updates"
```

---

## Task 5: Collapsible section cards, badges, sidebar + progress

Give each section a clickable header (icon · title · completion badge · chevron) that toggles its body, plus a sticky desktop sidebar with the same badges and an overall progress bar. Signatures + consent get their own final card.

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `SECTIONS`, `sectionProgress`, `state.open`, `updateProgress`.
- Produces: `renderLayout()` (replaced), `toggleSection(id)`, sidebar markup with `#badge-*`, `#progress-fill/-text`.

- [ ] **Step 1: Replace `renderLayout` with the card+sidebar version**

Find the Task 3 `renderLayout` (and its `renderGroupsInto()` call) and replace the whole `function renderLayout(){...}` with:

```js
function sectionCardHTML(s){
  const {filled,total}=sectionProgress(s);
  const open = state.open[s.id] !== false; // default open
  return '<div class="card'+(open?' open':'')+'" id="sec-'+s.id+'">'
    + '<button class="sec-head" data-act="toggle" data-sec="'+s.id+'">'
    +   '<span class="ic">'+s.icon+'</span>'
    +   '<span class="t">'+s.num+'. '+esc(s.title)+'</span>'
    +   '<span class="badge'+(total>0&&filled===total?' done':'')+'" id="badge-'+s.id+'">'+filled+' / '+total+'</span>'
    +   '<span class="chev">▾</span>'
    + '</button>'
    + '<div class="sec-body">'+s.fields.map(renderField).join('')+'</div>'
    + '</div>';
}
function sidebarHTML(){
  let h='<div class="sidebar"><div class="label">Abschnitte</div>';
  SECTIONS.forEach(s=>{
    const {filled,total}=sectionProgress(s);
    h+='<button data-act="nav" data-sec="'+s.id+'"'+(state.activeSection===s.id?' class="active"':'')+'>'
      +'<span>'+s.icon+'</span><span>'+esc(s.title)+'</span>'
      +'<span class="badge" id="sbadge-'+s.id+'">'+filled+'/'+total+'</span></button>';
  });
  h+='<div class="progress-wrap"><div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>'
    +'<div class="progress-text" id="progress-text"></div></div></div>';
  return h;
}
function jumperHTML(){
  let opts=SECTIONS.map(s=>'<option value="'+s.id+'">'+s.num+'. '+esc(s.title)+'</option>').join('');
  return '<div class="jumper"><select id="jump" data-act="jump">'+opts+'</select>'
    +'<div class="showall-row"><button data-act="showall" id="showall-btn">Alle Abschnitte anzeigen</button></div></div>';
}
function metaCardHTML(){
  return '<div class="card open"><div class="sec-body" style="padding-top:14px"><div class="row">'
    + Object.keys(META).map(k=>{ const m=META[k], v=state.data[k]||'';
        return '<div class="field"><label>'+esc(m.label)+'</label><input type="'+m.type+'" value="'+esc(v)+'" data-field="'+k+'"></div>'; }).join('')
    + '</div></div></div>';
}
function signatureCardHTML(){
  const caps=['Unterschrift Kunde','Unterschrift Berater'];
  return '<div class="card open" id="sec-sig"><button class="sec-head" data-act="toggle" data-sec="sig">'
    +'<span class="ic">✍️</span><span class="t">13. Einwilligung &amp; Unterschriften</span><span class="chev">▾</span></button>'
    +'<div class="sec-body"><p class="consent">Der Kunde erklärt sich mit der Erhebung und Verarbeitung der Daten zum '
    +'Zweck der Angebotserstellung einverstanden. Die Datenschutzerklärung wurde zur Kenntnis genommen.</p>'
    +'<div class="sig-row">'
    + caps.map((c,i)=>'<div class="sig-field"><input type="text" value="'+esc(state.data['sig_'+i]||'')+'" placeholder="Hier tippen…" data-field="sig_'+i+'"><div class="cap">'+c+'</div></div>').join('')
    +'</div></div></div>';
}
function renderLayout(){
  const layout=document.querySelector('.layout');
  layout.innerHTML = sidebarHTML()
    + '<div class="main">'
    +   jumperHTML() + metaCardHTML()
    +   SECTIONS.map(sectionCardHTML).join('')
    +   signatureCardHTML()
    + '</div>';
  if(typeof applyConditional==='function') applyConditional(); // Task 7
  if(typeof renderRoofs==='function') renderRoofs();           // Task 8
  if(typeof renderPhotos==='function') renderPhotos();         // Task 10
  updateProgress();
}
function toggleSection(id){
  const card=document.getElementById('sec-'+id); if(!card) return;
  const nowOpen=!card.classList.contains('open');
  card.classList.toggle('open', nowOpen);
  state.open[id]=nowOpen;
}
```

- [ ] **Step 2: Wire the toggle + nav + sidebar-badge update**

Find `function onClick(e){` and add handling. Replace the comment line `// later: toggle, nav, ...` and the `onClickExtra` guard with:

```js
  if(act==='toggle') return toggleSection(a.dataset.sec);
  if(act==='nav'){ return gotoSection(a.dataset.sec); }
  if(typeof onClickExtra==='function') onClickExtra(act, a, e);
```

Add these functions near `toggleSection`:

```js
function gotoSection(id){
  state.activeSection=id;
  const card=document.getElementById('sec-'+id);
  if(card){ card.classList.add('open'); state.open[id]=true;
    window.scrollTo({top:card.getBoundingClientRect().top+window.scrollY-70,behavior:'smooth'}); }
  document.querySelectorAll('.sidebar button[data-act="nav"]').forEach(b=>b.classList.toggle('active', b.dataset.sec===id));
}
```

In `updateProgress`, extend the per-section loop to also update the sidebar badge. Find:

```js
  SECTIONS.forEach(s=>{
    const b=document.getElementById('badge-'+s.id); if(!b) return;
    const {filled,total}=sectionProgress(s);
    b.textContent=filled+' / '+total;
    b.classList.toggle('done', total>0 && filled===total);
  });
```

Replace with:

```js
  SECTIONS.forEach(s=>{
    const {filled,total}=sectionProgress(s);
    const b=document.getElementById('badge-'+s.id);
    if(b){ b.textContent=filled+' / '+total; b.classList.toggle('done', total>0 && filled===total); }
    const sb=document.getElementById('sbadge-'+s.id);
    if(sb){ sb.textContent=filled+'/'+total; }
  });
```

Add a `jump` change handler — find `function onChange(e){` and replace with:

```js
function onChange(e){
  if(e.target.id==='jump'){ return gotoSection(e.target.value); }
  if(typeof onChangeExtra==='function') onChangeExtra(e);
}
```

- [ ] **Step 3: Manual verification**

Reload (desktop width ≥ 861px):
- Each section is a card with header, icon, title, `filled / total` badge, chevron. Clicking the header collapses/expands the body; chevron rotates. Reload preserves collapsed/expanded state.
- The badge turns green when all a section's fields are filled.
- Sidebar lists all sections with `filled/total`; clicking one scrolls to it, expands it, and highlights it.
- Overall progress bar + "% ausgefüllt" update as you fill fields, color shifts red→amber→green.

Narrow the viewport below 861px:
- Sidebar hides; a sticky "Sprung zu Abschnitt" dropdown appears and jumps on change; "Alle Abschnitte anzeigen" button is visible (wired in Task 7).

- [ ] **Step 4: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): collapsible section cards, badges, sidebar + progress"
```

---

## Task 6: Conditional trade sections + "Alle anzeigen"

Trade sections (`trade` in schema: pv, batterie, wp, wallbox, module) start **collapsed** and auto-expand when their gewerk is selected; they are never hidden, and a "Alle Abschnitte anzeigen" toggle expands everything.

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `SECTIONS[*].trade`, `state.data.gewerke`, `state.showAll`, `toggleSection`.
- Produces: `applyConditional()`, `sectionActive(s)`.

- [ ] **Step 1: Add `applyConditional` + default-collapse logic**

Add near `toggleSection`:

```js
function sectionActive(s){
  if(!s.trade) return true;
  return state.showAll || (state.data.gewerke||[]).includes(s.trade);
}
function applyConditional(){
  SECTIONS.forEach(s=>{
    const card=document.getElementById('sec-'+s.id); if(!card) return;
    const active=sectionActive(s);
    // A trade section the user hasn't explicitly toggled collapses when inactive, expands when active.
    if(s.trade && state.open[s.id]===undefined){
      card.classList.toggle('open', active);
    }
    // dim its sidebar row when inactive
    const nav=document.querySelector('.sidebar button[data-act="nav"][data-sec="'+s.id+'"]');
    if(nav) nav.classList.toggle('dim', s.trade && !active);
  });
}
```

- [ ] **Step 2: Wire the show-all toggle**

In `onClick`, before the `onClickExtra` guard, add:

```js
  if(act==='showall'){
    state.showAll=!state.showAll;
    const btn=document.getElementById('showall-btn');
    if(btn) btn.textContent = state.showAll ? 'Nur relevante Abschnitte' : 'Alle Abschnitte anzeigen';
    // reset explicit toggles for trade sections so conditional logic drives them again
    SECTIONS.forEach(s=>{ if(s.trade) delete state.open[s.id]; });
    document.querySelectorAll('.card[id^="sec-"]').forEach(c=>{});
    SECTIONS.forEach(s=>{ if(s.trade){ const c=document.getElementById('sec-'+s.id); if(c) c.classList.toggle('open', state.showAll || (state.data.gewerke||[]).includes(s.trade)); }});
    applyConditional();
    return;
  }
```

- [ ] **Step 3: Ensure initial collapse for inactive trade sections**

In `sectionCardHTML`, the default `open` is currently `state.open[s.id] !== false`. Change it so trade sections default collapsed unless active. Find:

```js
  const open = state.open[s.id] !== false; // default open
```

Replace with:

```js
  const explicit = state.open[s.id];
  const open = explicit !== undefined ? explicit : (s.trade ? (state.showAll || (state.data.gewerke||[]).includes(s.trade)) : true);
```

- [ ] **Step 4: Manual verification**

Reload with no gewerke selected:
- PV, Batteriespeicher, Wärmepumpe, Wallbox, PV-Module sections are **collapsed**; non-trade sections open. Their sidebar rows are dimmed.
- Select "Wärmepumpe" in Interesse/Gewerke → the Wärmepumpe section auto-expands and its sidebar row un-dims. Deselect → it collapses again (unless you had manually opened it).
- Manually expand a collapsed trade section by clicking its header → it stays as you set it (explicit toggle wins).
- Click "Alle Abschnitte anzeigen" → all trade sections expand; label flips to "Nur relevante Abschnitte"; clicking again reverts to conditional.

- [ ] **Step 5: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): conditional trade sections + show-all toggle"
```

---

## Task 7: Dynamic roof surfaces

Render `state.data.roofs[]` in the `#roofs` container with an "Add roof" button and per-roof remove; roof field edits persist and update only the roofs container.

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `ROOF_FIELDS`, `state.data.roofs`, `saveDraft`.
- Produces: `renderRoofs()`, `onRoofInput(el)`, `addRoof()`, `removeRoof(i)`.

- [ ] **Step 1: Add roof rendering + handlers**

Add to the script (near the render functions):

```js
function roofCardHTML(roof,i){
  const rows=[];
  for(let k=0;k<ROOF_FIELDS.length;k+=2){
    const pair=ROOF_FIELDS.slice(k,k+2).map(rf=>{
      const v=(roof&&roof[rf.id])||'';
      return '<div class="field"><label>'+esc(rf.label)+'</label>'
        +'<input type="text" value="'+esc(v)+'" placeholder="'+esc(rf.ph||'')+'" data-roof="'+i+'" data-rfield="'+rf.id+'"></div>';
    }).join('');
    rows.push('<div class="row">'+pair+'</div>');
  }
  return '<div class="roof"><div class="roof-head"><span class="rt">Dachfläche '+(i+1)+'</span>'
    + (state.data.roofs.length>1 ? '<button class="icon-btn" data-act="roof-del" data-idx="'+i+'" title="Entfernen">🗑</button>' : '')
    + '</div>'+rows.join('')+'</div>';
}
function renderRoofs(){
  const el=document.getElementById('roofs'); if(!el) return;
  el.innerHTML = state.data.roofs.map(roofCardHTML).join('')
    + '<button class="add-btn" data-act="roof-add">＋ Dachfläche hinzufügen</button>';
}
function onRoofInput(el){
  const i=+el.dataset.roof, f=el.dataset.rfield;
  if(!state.data.roofs[i]) state.data.roofs[i]={};
  state.data.roofs[i][f]=el.value; saveDraft();
}
function addRoof(){ state.data.roofs.push({}); saveDraft(); renderRoofs(); }
function removeRoof(i){ state.data.roofs.splice(i,1); if(!state.data.roofs.length) state.data.roofs.push({}); saveDraft(); renderRoofs(); }
```

- [ ] **Step 2: Wire the click actions**

In `onClick`, before the `onClickExtra` guard, add:

```js
  if(act==='roof-add') return addRoof();
  if(act==='roof-del') return removeRoof(+a.dataset.idx);
```

- [ ] **Step 3: Manual verification**

Reload, open Objekt / Gebäude → under "Dachflächen":
- One roof card ("Dachfläche 1") with the six fields in three rows; no remove button when only one roof.
- Click "＋ Dachfläche hinzufügen" → "Dachfläche 2" appears, both now show 🗑.
- Fill roof fields, reload → values persist per roof.
- Remove a roof → it disappears and the remaining cards renumber; typing in a text field elsewhere is unaffected (only `#roofs` re-renders).

- [ ] **Step 4: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): dynamic roof surfaces (add/remove/persist)"
```

---

## Task 8: Photos — IndexedDB store, image processing, upload UI

Add the IndexedDB photo store, client-side image downscaling/thumbnails, and the upload UI in Unterlagen/Fotos (camera/gallery, thumbnail grid, category, remove). Blobs live in IndexedDB; `state.data.photos` holds metadata.

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `state.leadId`, `state.data.photos`, `PHOTO_CATEGORIES`, `showToast`, `saveDraft`.
- Produces: `idb()`, `photoPut/photosByLead/photoDelete/photosDeleteByLead`, `processImage(file)`, `renderPhotos()`, `addPhotos(fileList)`, `removePhoto(id)`, `setPhotoCategory(id,val)`, `ALLOWED_MIME`, `MAX_FILE_BYTES`.

- [ ] **Step 1: Add the IndexedDB layer + image processing**

Add to the script (Storage/Image module region):

```js
/* ---- IndexedDB (photo blobs) ---- */
const DB_NAME='ti-leads', DB_VER=1, STORE='photos';
const ALLOWED_MIME=['image/jpeg','image/png','image/heic','image/webp','application/pdf'];
const MAX_FILE_BYTES=25*1024*1024;
function idb(){ return new Promise((res,rej)=>{ const r=indexedDB.open(DB_NAME,DB_VER);
  r.onupgradeneeded=()=>{ const db=r.result; if(!db.objectStoreNames.contains(STORE)){ const os=db.createObjectStore(STORE,{keyPath:'id'}); os.createIndex('leadId','leadId',{unique:false}); } };
  r.onsuccess=()=>res(r.result); r.onerror=()=>rej(r.error); }); }
function _tx(db,mode,fn){ return new Promise((res,rej)=>{ const t=db.transaction(STORE,mode); const rq=fn(t.objectStore(STORE)); t.oncomplete=()=>res(rq&&rq.result); t.onerror=()=>rej(t.error); t.onabort=()=>rej(t.error); }); }
async function photoPut(rec){ const db=await idb(); return _tx(db,'readwrite',s=>s.put(rec)); }
async function photoDelete(id){ const db=await idb(); return _tx(db,'readwrite',s=>s.delete(id)); }
async function photosByLead(leadId){ const db=await idb(); return new Promise((res,rej)=>{ const out=[]; const r=db.transaction(STORE).objectStore(STORE).index('leadId').openCursor(IDBKeyRange.only(leadId)); r.onsuccess=()=>{ const c=r.result; if(c){ out.push(c.value); c.continue(); } else res(out); }; r.onerror=()=>rej(r.error); }); }
async function photosDeleteByLead(leadId){ const recs=await photosByLead(leadId); for(const r of recs) await photoDelete(r.id); }

/* ---- image downscale ---- */
function _downscale(bitmap,maxEdge,q){ const {width,height}=bitmap; const scale=Math.min(1,maxEdge/Math.max(width,height));
  const w=Math.max(1,Math.round(width*scale)), h=Math.max(1,Math.round(height*scale));
  const c=document.createElement('canvas'); c.width=w; c.height=h; c.getContext('2d').drawImage(bitmap,0,0,w,h);
  return new Promise(res=>c.toBlob(res,'image/jpeg',q)); }
async function processImage(file){
  if(!file.type.startsWith('image/')) return {blob:file, thumb:null, isImage:false, mime:file.type};
  try{
    const bmp=await createImageBitmap(file);
    const full=await _downscale(bmp,1600,0.8);
    const thumb=await _downscale(bmp,220,0.7);
    if(bmp.close) bmp.close();
    return {blob:full, thumb, isImage:true, mime:'image/jpeg'};
  }catch(_){ // HEIC/unsupported decode: keep original
    return {blob:file, thumb:null, isImage:false, mime:file.type};
  }
}
```

- [ ] **Step 2: Add photo rendering + actions**

```js
function photoCardHTML(p){
  const catOpts=['<option value="">Kategorie…</option>']
    .concat(PHOTO_CATEGORIES.map(c=>'<option'+(p.category===c?' selected':'')+'>'+esc(c)+'</option>')).join('');
  const thumb = p._thumbUrl ? '<img class="thumb" src="'+p._thumbUrl+'" alt="">' : '<div class="thumb">📄</div>';
  return '<div class="pcard">'+thumb
    +'<div class="meta"><div class="nm" title="'+esc(p.name)+'">'+esc(p.name)+'</div>'
    +'<select data-act="photo-cat" data-id="'+p.id+'">'+catOpts+'</select>'
    +'<button class="rm" data-act="photo-del" data-id="'+p.id+'">Entfernen</button></div></div>';
}
async function renderPhotos(){
  const host=document.getElementById('photos-block'); if(!host) return;
  host.innerHTML =
    '<div class="sub-header">Fotos &amp; Dateien</div>'
    +'<div class="photo-tools">'
    +'<label class="add-btn" style="cursor:pointer">📷 Foto aufnehmen'
    +'<input id="cam-input" type="file" accept="image/*" capture="environment" multiple hidden></label>'
    +'<label class="add-btn" style="cursor:pointer">📎 Datei wählen'
    +'<input id="file-input" type="file" accept="image/*,application/pdf" multiple hidden></label>'
    +'</div><div class="photo-grid" id="photo-grid"></div>';
  await paintPhotoGrid();
}
async function paintPhotoGrid(){
  const grid=document.getElementById('photo-grid'); if(!grid) return;
  const recs=await photosByLead(state.leadId);
  const byId={}; recs.forEach(r=>byId[r.id]=r);
  // revoke old object URLs
  (state.data.photos||[]).forEach(p=>{ if(p._thumbUrl){ URL.revokeObjectURL(p._thumbUrl); p._thumbUrl=null; } });
  state.data.photos.forEach(p=>{ const r=byId[p.id]; if(r&&r.thumb) p._thumbUrl=URL.createObjectURL(r.thumb); });
  grid.innerHTML = state.data.photos.length ? state.data.photos.map(photoCardHTML).join('')
    : '<p style="color:var(--muted);font-size:13px">Noch keine Fotos.</p>';
}
async function addPhotos(fileList){
  const files=Array.from(fileList||[]);
  for(const file of files){
    if(file.size>MAX_FILE_BYTES){ showToast(file.name+' ist zu groß (max. 25 MB)','error'); continue; }
    if(!ALLOWED_MIME.includes(file.type) && !file.type.startsWith('image/')){ showToast('Typ nicht erlaubt: '+file.name,'error'); continue; }
    const pid=newLeadId();
    let proc;
    try{ proc=await processImage(file); }
    catch(_){ showToast('Fehler bei '+file.name,'error'); continue; }
    const rec={ id:pid, leadId:state.leadId, category:'', blob:proc.blob, thumb:proc.thumb,
      name:file.name, mime:proc.mime||file.type, size:proc.blob.size, isImage:proc.isImage, createdAt:Date.now() };
    try{ await photoPut(rec); }
    catch(err){ showToast('Speicher voll?','error'); console.error(err); continue; }
    state.data.photos.push({ id:pid, category:'', name:file.name, mime:rec.mime, size:rec.size, isImage:proc.isImage });
  }
  saveDraft(); await paintPhotoGrid(); updateProgress();
}
async function removePhoto(id){
  await photoDelete(id).catch(()=>{});
  const p=state.data.photos.find(x=>x.id===id); if(p&&p._thumbUrl) URL.revokeObjectURL(p._thumbUrl);
  state.data.photos=state.data.photos.filter(x=>x.id!==id);
  saveDraft(); await paintPhotoGrid();
}
function setPhotoCategory(id,val){ const p=state.data.photos.find(x=>x.id===id); if(p){ p.category=val; saveDraft(); } }
```

- [ ] **Step 3: Wire click + change events**

In `onClick`, before the `onClickExtra` guard, add:

```js
  if(act==='photo-del') return removePhoto(a.dataset.id);
```

In `onChange`, extend to handle the two file inputs and the category select. Replace the current `onChange`:

```js
function onChange(e){
  if(e.target.id==='jump'){ return gotoSection(e.target.value); }
  if(e.target.id==='cam-input' || e.target.id==='file-input'){ addPhotos(e.target.files); e.target.value=''; return; }
  const cat=e.target.closest('[data-act="photo-cat"]');
  if(cat){ return setPhotoCategory(cat.dataset.id, cat.value); }
  if(typeof onChangeExtra==='function') onChangeExtra(e);
}
```

- [ ] **Step 4: Manual verification**

Reload, open Unterlagen / Fotos:
- The "Mitgenommen" checklist is intact; below it "Fotos & Dateien" with two buttons.
- On a phone/tablet, "📷 Foto aufnehmen" opens the camera; "📎 Datei wählen" opens gallery/files. (On desktop both open a file picker.)
- Add 2-3 images → thumbnails appear in the grid, each with a filename, category dropdown, and Entfernen. Set a category.
- Reload the page → photos still appear (restored from IndexedDB), categories preserved.
- Add a PDF → shows a 📄 tile. Remove a photo → it disappears from grid and IndexedDB.
- DevTools → Application → IndexedDB → `ti-leads/photos`: records present with the current `leadId`.

- [ ] **Step 5: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): photo upload via IndexedDB with downscaling + thumbnails"
```

---

## Task 9: Saved leads, new/reset, JSON export/import

Wire the toolbar (bottom action bar + overflow menu): Save to list, load saved, new, reset, JSON export/import — all handling `leadId` + photos correctly.

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `state`, `saveDraft`, `photosByLead`, `photosDeleteByLead`, `renderLayout`, `showToast`.
- Produces: the action bar markup, `onClickExtra(act,a,e)`, `doSaveToList/doNew/doReset/doExport/doImport/doLoadList/loadEntry/closeModal/openMenu`.

- [ ] **Step 1: Add the action bar to boot output**

In `boot()`, after the `'<div class="layout"></div>'` line, append the action bar and menu container. Find in `boot`:

```js
    + '<div class="layout"></div>';
```

Replace with:

```js
    + '<div class="layout"></div>'
    + '<div class="actionbar">'
    +   '<button class="btn save" data-act="save">💾 Speichern</button>'
    +   '<button class="btn send" data-act="send" id="send-btn">✉ An Büro senden</button>'
    +   '<button class="btn more" data-act="menu">⋯</button>'
    + '</div>';
```

- [ ] **Step 2: Add the toolbar action handlers**

Add to the Actions module region:

```js
function onClickExtra(act,a,e){
  if(act==='save') return doSaveToList();
  if(act==='new') return doNew();
  if(act==='reset') return doReset();
  if(act==='export') return doExport();
  if(act==='import') return doImport();
  if(act==='load') return doLoadList();
  if(act==='loadentry') return loadEntry(+a.dataset.idx);
  if(act==='closemodal') return closeModal();
  if(act==='menu') return openMenu();
  if(act==='pdf') { closeModal(); return doPDF(); }        // Task 10
  if(act==='print'){ closeModal(); return window.print(); }
}

function snapshot(){ return { leadId:state.leadId, data:JSON.parse(JSON.stringify(strip(state.data))), sent:state.sent, savedAt:new Date().toLocaleString('de-DE') }; }
function strip(data){ const d=JSON.parse(JSON.stringify(data)); (d.photos||[]).forEach(p=>delete p._thumbUrl); return d; }

function doSaveToList(){
  const list=loadList();
  const snap=snapshot();
  const idx=list.findIndex(x=>x.leadId===state.leadId);
  if(idx>=0) list[idx]=snap; else list.unshift(snap);
  if(list.length>50) list.length=50;
  try{ localStorage.setItem(LS_LIST, JSON.stringify(list)); }catch(_){}
  showToast('Lead gespeichert ✓','success');
}
async function doNew(){
  if(!confirm('Neuen Lead anlegen? Aktueller Stand wird in die Liste gespeichert.')) return;
  doSaveToList();
  state.leadId=newLeadId();
  state.data={ datum:todayISO(), roofs:[{}], photos:[] };
  state.sent=null; state.open={}; state.showAll=false;
  saveDraft(); renderLayout(); refreshSendBtn();
  showToast('Neuer Lead angelegt','success');
  window.scrollTo({top:0,behavior:'smooth'});
}
async function doReset(){
  if(!confirm('Alle Felder zurücksetzen? Fotos dieses Leads werden gelöscht.')) return;
  await photosDeleteByLead(state.leadId).catch(()=>{});
  state.data={ datum:todayISO(), roofs:[{}], photos:[] };
  state.sent=null; state.open={}; state.showAll=false;
  saveDraft(); renderLayout(); refreshSendBtn();
  showToast('Zurückgesetzt');
}
function doExport(){
  const n=[state.data.vorname,state.data.nachname].filter(Boolean).join('_')||'lead';
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([JSON.stringify(strip(state.data),null,2)],{type:'application/json'}));
  a.download='Lead_'+n+'_'+(state.data.datum||'export')+'.json'; a.click();
  showToast('JSON exportiert ✓ (ohne Fotos)','success');
}
function doImport(){
  const inp=document.createElement('input'); inp.type='file'; inp.accept='.json';
  inp.onchange=async ev=>{ try{
      const obj=JSON.parse(await ev.target.files[0].text());
      state.leadId=newLeadId(); // imported data has no photos in IndexedDB under this id
      state.data=Object.assign({datum:todayISO(),roofs:[{}],photos:[]}, obj);
      if(!Array.isArray(state.data.roofs)||!state.data.roofs.length) state.data.roofs=[{}];
      if(!Array.isArray(state.data.photos)) state.data.photos=[]; state.data.photos=[]; // no blobs imported
      state.sent=null; state.open={};
      saveDraft(); renderLayout(); refreshSendBtn(); showToast('Importiert ✓','success');
    }catch(_){ showToast('Ungültige Datei','error'); } };
  inp.click();
}
function doLoadList(){
  state.savedList=loadList();
  let h='<div class="modal-bg" data-act="closemodal"><div class="modal" onclick="event.stopPropagation()">';
  h+='<h3>📂 Gespeicherte Leads</h3>';
  if(!state.savedList.length){ h+='<p style="color:var(--muted)">Keine Leads gespeichert.</p>'; }
  else state.savedList.forEach((it,i)=>{ const d=it.data||{};
    h+='<div class="modal-item" data-act="loadentry" data-idx="'+i+'"><div class="name">'
      +esc(d.vorname||'')+' '+esc(d.nachname||'—')+(d.firma?' ('+esc(d.firma)+')':'')+'</div>'
      +'<div class="info">Nr: '+esc(d.lead_nr||'–')+' · '+esc(it.savedAt||'')+(it.sent?' · gesendet':'')+'</div></div>'; });
  h+='<button class="close" data-act="closemodal">Schließen</button></div></div>';
  document.getElementById('modal').innerHTML=h;
}
function loadEntry(i){
  const it=state.savedList[i]; if(!it) return;
  state.leadId=it.leadId||newLeadId();
  state.data=Object.assign({datum:todayISO(),roofs:[{}],photos:[]}, it.data);
  if(!Array.isArray(state.data.roofs)||!state.data.roofs.length) state.data.roofs=[{}];
  if(!Array.isArray(state.data.photos)) state.data.photos=[];
  state.sent=it.sent||null; state.open={}; state.showAll=false;
  saveDraft(); closeModal(); renderLayout(); refreshSendBtn();
  showToast('Lead geladen','success'); window.scrollTo({top:0,behavior:'smooth'});
}
function closeModal(){ document.getElementById('modal').innerHTML=''; }
function openMenu(){
  const items=[['load','📂 Gespeicherte Leads'],['new','📄 Neuer Lead'],['pdf','📄 Als PDF speichern'],
    ['print','🖨 Drucken'],['export','📥 JSON Export'],['import','📤 JSON Import'],['reset','↺ Zurücksetzen']];
  let h='<div class="modal-bg" data-act="closemodal"><div class="modal" onclick="event.stopPropagation()"><h3>Menü</h3>';
  h+=items.map(([act,lbl])=>'<button class="menu-item" data-act="'+act+'">'+lbl+'</button>').join('');
  h+='<button class="close" data-act="closemodal">Schließen</button></div></div>';
  document.getElementById('modal').innerHTML=h;
}
function refreshSendBtn(){ /* filled in Task 11 */ }
```

Note: `.modal-item`/`.menu-item` clicks bubble to the delegated `#app` click listener, but the modal lives in `#modal` (outside `#app`). Add listeners for the modal container. In `boot()`, after the three `app.addEventListener(...)` lines, add:

```js
  document.getElementById('modal').addEventListener('click', onClick);
```

- [ ] **Step 3: Manual verification**

Reload:
- Bottom action bar shows Speichern · An Büro senden · ⋯. Tapping ⋯ opens a menu sheet with Load/New/PDF/Print/Export/Import/Reset.
- Fill a lead, Speichern → toast; open "Gespeicherte Leads" → it's listed with name + timestamp.
- New Lead → prompts, saves current, clears form (new leadId). Load the saved one back → fields + photos reappear (photos restored by leadId).
- JSON Export downloads a `.json` (no photos); Import loads a file's fields (photos intentionally not imported).
- Reset → clears fields and deletes this lead's photos from IndexedDB.

- [ ] **Step 4: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): saved leads, new/reset, JSON export/import, action bar"
```

---

## Task 10: PDF export — filled fields only + photos

Build an off-screen print document containing only filled fields (empty sections omitted), the roofs, and the photos, then render it with vendored `html2pdf`.

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `SECTIONS`, `META`, `ROOF_FIELDS`, `state`, `isFilled`, `photosByLead`, `esc`, `showToast`.
- Produces: `doPDF()`, `buildPrintDoc()`, `pdfFilename()`.

- [ ] **Step 1: Add the print-document builder + PDF action**

Add to the PDF module region:

```js
function fieldLabel(id){ const f=findField(id); return f?f.label:id; }
function pdfFilename(){
  const clean=s=>String(s||'').replace(/[^a-zA-Z0-9äöüÄÖÜß\-]/g,'');
  const parts=['Lead'];
  if(state.data.lead_nr) parts.push(clean(state.data.lead_nr));
  if(state.data.nachname) parts.push(clean(state.data.nachname));
  parts.push(state.data.datum||todayISO());
  return parts.join('_')+'.pdf';
}
function fmtVal(v){ return Array.isArray(v) ? v.join(', ') : esc(v); }
function sectionPrintHTML(s){
  const rows=[];
  s.fields.forEach(function walk(f){
    if(f.type==='row') return f.children.forEach(walk);
    if(f.type==='subheader'){ rows.push('<div class="ps">'+esc(f.label)+'</div>'); return; }
    if(f.type==='roofs' || f.type==='photos') return;
    if(!f.id) return;
    const v=state.data[f.id];
    if(!isFilled(v)) return;
    rows.push('<div class="pr"><span class="pk">'+esc(f.label)+':</span> <span class="pv">'+fmtVal(v)+'</span></div>');
  });
  // roofs (only in objekt)
  if(s.id==='objekt'){
    (state.data.roofs||[]).forEach((roof,i)=>{
      const parts=ROOF_FIELDS.filter(rf=>isFilled(roof&&roof[rf.id])).map(rf=>esc(rf.label)+': '+esc(roof[rf.id]));
      if(parts.length) rows.push('<div class="pr"><span class="pk">Dachfläche '+(i+1)+':</span> <span class="pv">'+parts.join(' · ')+'</span></div>');
    });
  }
  // drop subheaders with no following rows
  const cleaned=[]; for(let i=0;i<rows.length;i++){ const isHdr=rows[i].indexOf('class="ps"')>=0;
    if(isHdr && (i===rows.length-1 || rows[i+1].indexOf('class="ps"')>=0)) continue; cleaned.push(rows[i]); }
  if(!cleaned.length) return '';
  return '<div class="pblock"><h3>'+s.num+'. '+esc(s.title)+'</h3>'+cleaned.join('')+'</div>';
}
async function buildPrintDoc(){
  const kunde=[state.data.vorname,state.data.nachname].filter(Boolean).join(' ');
  let h='<div id="pdf-doc" style="display:block;font-family:DM Sans,sans-serif;color:#1e2a37;padding:4px 8px">';
  h+='<style>'
    +'#pdf-doc h2{font-size:18px;color:#2264a8;margin:0} #pdf-doc .head{display:flex;justify-content:space-between;'
    +'border-bottom:3px solid #2264a8;padding-bottom:8px;margin-bottom:10px} #pdf-doc .head .r{text-align:right;font-size:12px;color:#333}'
    +'#pdf-doc .pblock{border:1px solid #e3e9f0;border-radius:6px;padding:8px 10px;margin-bottom:8px;break-inside:avoid}'
    +'#pdf-doc h3{font-size:13px;color:#163d64;margin:0 0 6px;border-bottom:1px solid #eef2f7;padding-bottom:3px}'
    +'#pdf-doc .pr{font-size:12px;margin:2px 0} #pdf-doc .pk{color:#5a6b7b} #pdf-doc .pv{font-weight:600}'
    +'#pdf-doc .ps{font-size:11px;font-weight:700;color:#2264a8;margin:6px 0 2px;text-transform:uppercase}'
    +'#pdf-doc .pgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px}'
    +'#pdf-doc .pgrid figure{margin:0;break-inside:avoid} #pdf-doc .pgrid img{width:100%;border:1px solid #ddd;border-radius:4px}'
    +'#pdf-doc figcaption{font-size:10px;color:#5a6b7b;margin-top:2px}'
    +'</style>';
  h+='<div class="head"><div><h2>Lead-Aufnahmebogen</h2>'
    +'<div style="font-size:11px;color:#5a6b7b">Technik- &amp; Instandsetzungs GmbH · PV · Wärmepumpe · Speicher · Wallbox</div></div>'
    +'<div class="r">'
    +(state.data.lead_nr?'<div style="font-weight:800;color:#2264a8">Lead-Nr.: '+esc(state.data.lead_nr)+'</div>':'')
    +(kunde?'<div style="font-weight:700">'+esc(kunde)+'</div>':'')
    +(state.data.firma?'<div>'+esc(state.data.firma)+'</div>':'')
    +(state.data.datum?'<div style="color:#888">'+esc(state.data.datum)+'</div>':'')
    +'</div></div>';
  h+=SECTIONS.map(sectionPrintHTML).join('');
  // signatures if present
  if(isFilled(state.data.sig_0)||isFilled(state.data.sig_1)){
    h+='<div class="pblock"><h3>Unterschriften</h3>'
      +(isFilled(state.data.sig_0)?'<div class="pr"><span class="pk">Kunde:</span> <span class="pv">'+esc(state.data.sig_0)+'</span></div>':'')
      +(isFilled(state.data.sig_1)?'<div class="pr"><span class="pk">Berater:</span> <span class="pv">'+esc(state.data.sig_1)+'</span></div>':'')+'</div>';
  }
  // photos
  const recs=await photosByLead(state.leadId);
  const imgs=recs.filter(r=>r.isImage);
  if(imgs.length){
    const toURL=b=>new Promise(res=>{ const fr=new FileReader(); fr.onload=()=>res(fr.result); fr.readAsDataURL(b); });
    const figs=[];
    for(const r of imgs){ const url=await toURL(r.blob); const meta=state.data.photos.find(p=>p.id===r.id);
      figs.push('<figure><img src="'+url+'"><figcaption>'+esc((meta&&meta.category)||r.name||'')+'</figcaption></figure>'); }
    h+='<div class="pblock"><h3>Fotos</h3><div class="pgrid">'+figs.join('')+'</div></div>';
  }
  h+='</div>';
  const mount=document.getElementById('pdf-mount'); mount.innerHTML=h;
  return document.getElementById('pdf-doc');
}
async function doPDF(){
  showToast('PDF wird erstellt…','info');
  try{
    const el=await buildPrintDoc();
    await html2pdf().set({
      margin:[8,8,10,8], filename:pdfFilename(),
      image:{type:'jpeg',quality:0.92},
      html2canvas:{scale:2,useCORS:true,windowWidth:820,logging:false},
      jsPDF:{unit:'mm',format:'a4',orientation:'portrait'},
      pagebreak:{mode:['css','legacy']}
    }).from(el).save();
    showToast('PDF heruntergeladen ✓','success');
  }catch(err){ console.error(err); showToast('PDF-Fehler','error'); }
  finally{ document.getElementById('pdf-mount').innerHTML=''; }
}
```

- [ ] **Step 2: Manual verification**

Reload. Fill some fields across a few sections (leave others empty), add 1-2 photos with categories, then ⋯ → "Als PDF speichern":
- A PDF downloads named `Lead_<Nr>_<Nachname>_<Datum>.pdf`.
- The PDF contains a header (title, Lead-Nr., customer, date), then **only the sections/fields you filled** — empty sections and empty fields are absent.
- Roofs you filled appear under Objekt; the photos appear in a grid with their category captions.
- Print (⋯ → Drucken) opens the browser print dialog rendering the same interactive page acceptably (native fallback).

- [ ] **Step 3: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): filled-only PDF export with embedded photos"
```

---

## Task 11: Send to office (backend POST) + send status

Wire "An Büro senden": build the payload, POST `multipart/form-data` to `${API_BASE}/lead.php` with `files[]` + honeypot, reflect sent/unsent status, and handle offline/failure with a retry.

**Files:**
- Modify: `Lead-Aufnahmebogen.html`

**Interfaces:**
- Consumes: `API_BASE`, `state`, `strip`, `photosByLead`, `showToast`, `saveDraft`, `doSaveToList`.
- Produces: `buildPayload()`, `doSend()`, `refreshSendBtn()` (replaces the Task 9 stub).

- [ ] **Step 1: Implement send + status button**

Replace the Task 9 stub:

```js
function refreshSendBtn(){ /* filled in Task 11 */ }
```

with:

```js
function refreshSendBtn(){
  const b=document.getElementById('send-btn'); if(!b) return;
  b.classList.remove('send','sent','unsent');
  if(state.sent){ b.classList.add('sent'); b.textContent='✓ Gesendet – erneut senden'; }
  else { b.classList.add('send'); b.textContent='✉ An Büro senden'; }
}
function buildPayload(){
  const d=strip(state.data);
  return JSON.stringify(Object.assign({}, d, { _leadId:state.leadId, _sentBefore:state.sent||null }));
}
async function doSend(){
  const btn=document.getElementById('send-btn'); if(btn) btn.disabled=true;
  showToast('Wird gesendet…','info');
  try{
    const fd=new FormData();
    fd.append('payload', buildPayload());
    fd.append('website','');
    // append photo blobs in the same order as state.data.photos so payload.photos[i] aligns with files[i]
    const recs=await photosByLead(state.leadId);
    const byId={}; recs.forEach(r=>byId[r.id]=r);
    state.data.photos.forEach(p=>{ const r=byId[p.id]; if(r) fd.append('files[]', r.blob, r.name||(p.id+'.jpg')); });
    const res=await fetch(API_BASE+'/lead.php',{method:'POST',body:fd});
    const data=await res.json().catch(()=>({}));
    if(res.status===413){ showToast('Dateien zu groß','error'); }
    else if(res.ok && data.ok){
      state.sent={ serverId:data.id, sentAt:new Date().toISOString() };
      saveDraft(); doSaveToList(); refreshSendBtn();
      showToast('An Büro gesendet ✓','success');
    } else {
      showToast('Senden fehlgeschlagen – bitte erneut versuchen','error');
    }
  }catch(_){
    showToast('Offline oder Serverfehler – erneut senden, sobald online','error');
  }finally{ if(btn) btn.disabled=false; }
}
```

- [ ] **Step 2: Route the send action**

In `onClick`, the `data-act="send"` currently falls through to `onClickExtra`. Add an explicit route. Find in `onClick` (from Task 4/5), before the `onClickExtra` guard, add:

```js
  if(act==='send') return doSend();
```

And call `refreshSendBtn()` at the end of `boot()` (after `renderLayout()`), add:

```js
  refreshSendBtn();
```

- [ ] **Step 3: Manual verification**

> The backend `lead.php` endpoint does not exist yet (separate sub-project). Verify the request shape and offline behavior:

- DevTools → Network. Click "An Büro senden". Confirm a `POST` to `https://api.technik-prignitz.de/lead.php` with `multipart/form-data` containing `payload` (JSON), `website` (empty), and one `files[]` part per photo, **in the same order** as the photo grid. (It will fail with 404/CORS until the backend exists — that's expected; the toast shows a failure.)
- Go offline (DevTools → Network → Offline) and click send → toast reports offline; the button stays "An Büro senden" (unsent).
- Simulate success (optional): temporarily point `API_BASE` at a mock returning `{"ok":true,"id":1}`, send, and confirm the button flips to "✓ Gesendet – erneut senden" and the lead is saved to the list as gesendet. Revert `API_BASE`.

- [ ] **Step 4: Commit**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "feat(lead): send lead + photos to backend with send-status + retry"
```

---

## Task 12: Final QA, offline install, and code-check confirmation

Run the full spec §12 checklist, verify offline/install, and confirm the code-check items from spec §11 are resolved.

**Files:**
- Modify: `Lead-Aufnahmebogen.html` (only if QA turns up fixes)

- [ ] **Step 1: Full functional pass (spec §12)**

In a browser over HTTP, verify in order:
1. Fill fields across sections; reload → draft restored.
2. Collapse/expand sections; badges reflect filled counts; state persists across reload.
3. Select/deselect trades in Interesse/Gewerke → matching sections expand/collapse; "Alle anzeigen" reveals all.
4. Add/remove roofs; values persist and appear in the PDF.
5. Attach photos (camera + gallery); thumbnails + categories; reload → photos restored.
6. PDF shows only filled fields + photos; filename correct.
7. "An Büro senden" offline → flagged unsent, retry available (success path once backend exists).
8. Install to home screen (Chrome: Install app / iOS Safari: Add to Home Screen); open the installed app with network **offline** → it loads and runs.

- [ ] **Step 2: Code-check confirmation (spec §11)**

- Typing in a text field never rebuilds the whole page or loses caret (only targeted nodes update) — confirmed by tabbing through inputs while toggling chips.
- No inline `onclick=` attributes remain except the two intentional `onclick="event.stopPropagation()"` guards on modal panels:
  ```bash
  grep -n "onclick=" Lead-Aufnahmebogen.html
  ```
  Expected: only the modal `event.stopPropagation()` lines. No `handleRadio('...')`-style inline handlers.
- No `min-width:120` (unit-less) and no `mailto:` remain:
  ```bash
  grep -nE "mailto:|min-width:\s*120;" Lead-Aufnahmebogen.html
  ```
  Expected: no matches.
- No runtime CDN references:
  ```bash
  grep -nE "googleapis|cdnjs|https://fonts" Lead-Aufnahmebogen.html
  ```
  Expected: no matches (font + lib are local).

- [ ] **Step 3: Accessibility / touch pass**

- All tappable controls (chips, section headers, action bar, add-roof, photo buttons) are ≥ 44px tall on mobile widths.
- Text is legible at arm's length; brand-blue on white and white on brand-blue meet contrast. Fix any low-contrast greys found (adjust `--muted` usage only if needed).

- [ ] **Step 4: Commit any fixes**

```bash
git add Lead-Aufnahmebogen.html
git commit -m "fix(lead): QA pass — offline/install, a11y, code-check confirmations"
```

If no fixes were needed, note that the branch is ready — no extra commit required.

---

## Self-Review (author checklist — completed)

**Spec coverage:**
- §1 packaging/offline → Tasks 1, 2, 12 (SW/manifest/install).
- §5 data & storage → Tasks 3 (localStorage), 8 (IndexedDB), 5.3 image handling → Task 8.
- §6a collapsible → Task 5; §6b roofs → Task 7; §6c photos → Task 8; §6d PDF → Task 10.
- §7 delivery/payload → Task 11 (honeypot, `files[]`, `${API_BASE}/lead.php`, send status, offline retry).
- §8 redesign (mobile-first) → Tasks 2 (CSS) + 5 (nav/cards); §8.1 conditional → Task 6.
- §9 actions (mailto removed) → Task 9; §10 reactivity → Tasks 3-8 (delegation, targeted updates); §11 code-check → Task 12 confirmations.
- §12 verification → Task 12.
- §13 backend → explicitly out of scope (follow-up sub-project).

**Placeholder scan:** No "TBD/TODO/implement later". The only `/* filled in Task N */` markers are real stubs that later tasks explicitly replace, each with the replacement code shown.

**Type/name consistency:** `state`, `saveDraft`, `renderLayout`, `renderRoofs`, `renderPhotos`, `applyConditional`, `onRadio/onCheck`, `photosByLead/photoPut/photoDelete/photosDeleteByLead`, `processImage`, `buildPrintDoc/doPDF/pdfFilename`, `buildPayload/doSend/refreshSendBtn`, `onClickExtra/onChangeExtra` used consistently across tasks. Data-attribute contract (`data-act`, `data-field`, `data-val`, `data-sec`, `data-roof`/`data-rfield`, `data-id`, `data-idx`) matches between render output and handlers.

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven (recommended)** — a fresh subagent per task with two-stage review between tasks (`superpowers:subagent-driven-development`).
2. **Inline Execution** — execute tasks in this session with checkpoints (`superpowers:executing-plans`).
