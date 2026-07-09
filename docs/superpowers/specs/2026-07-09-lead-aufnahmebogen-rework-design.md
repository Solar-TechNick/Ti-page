# Lead-Aufnahmebogen — Field-App Rework (Design Spec)

**Date:** 2026-07-09
**Status:** Approved for planning
**Scope of this spec:** The client-side field app (`Lead-Aufnahmebogen.html`). The backend leads
endpoint is a **separate sub-project** (own spec + plan) that implements the payload contract
defined in §7 here.

---

## 1. Overview

`Lead-Aufnahmebogen.html` is a self-contained, schema-driven intake form a sales consultant fills
in on a phone/tablet at the customer's home (PV / battery / heat pump / wallbox projects). Today it
is one large inline HTML/CSS/JS file that stores a single draft + a saved-leads list in
`localStorage`, and offers Save / mailto E-Mail / html2pdf PDF / Print / JSON import-export.

This rework delivers a full overhaul: a **mobile-first visual redesign**, a **form restructure**
(collapsible sections + conditional trade sections), a **code reorganization**, **offline
robustness**, and four requested features — collapsible sections, dynamic roof surfaces, photo/file
upload, and a filled-only PDF that embeds the photos. It also adds a proper **"send to office"**
path that POSTs the lead + photos to the backend, replacing the `mailto:` button.

## 2. Goals

- Fast, comfortable data entry on a phone/tablet in the field, including in bright outdoor light.
- Works reliably with no or poor internet; installable to a home screen.
- Consultant can attach photos (camera or gallery) and produce a clean PDF containing only the
  filled-in fields plus those photos.
- One-tap delivery of the complete lead (data + photos) to the office when online, with a safe
  fallback when offline.
- Maintainable code: modular, no full-DOM-rebuild reactivity, no CDN dependencies.

## 3. Non-goals (this spec)

- The backend leads endpoint (`lead.php`, `leads` table, admin view, mailer, PHPUnit tests) — its
  own sub-project, built next against the §7 contract.
- Multi-device sync / accounts / server-side draft storage. Drafts remain local to the device.
- A JS test framework / build pipeline. Zero-build is a hard constraint (§11).
- Reordering roof surfaces or drag-and-drop (add/remove only).

## 4. Packaging & architecture

- **One self-contained file** `Lead-Aufnahmebogen.html` at the site root, served from the Plesk
  webserver (same origin as the PHP backend — the "send" POST is same-origin).
- **Vanilla JS, no framework, no build step.** All CSS/JS inline, internally organized into
  clearly-commented modules:
  - **Schema** — the `SECTIONS` data (preserved from today, with the roof change in §6b).
  - **State** — the in-memory `data` object + view state (open sections, active section, send status).
  - **Storage** — `localStorage` (lead JSON) and IndexedDB (photo blobs).
  - **Render** — schema-driven rendering with **event delegation** and **targeted DOM updates**
    (no whole-page `render()` on every input; see §10).
  - **Actions** — save, new, load, reset, JSON import/export, PDF, send.
  - **PDF** — off-screen print-document builder (§6d).
  - **Backend** — payload builder + `POST /api/lead.php` (§7).
- **Vendored static assets** under `assets/lead/` (kept small): DM Sans `woff2` files, the PDF
  library, `sw.js`, `manifest.json`, and app icons. **No CDN references.**
- **Service worker** (`assets/lead/sw.js`) precaches the HTML shell + vendored assets on install so
  the tool loads and runs fully offline after the first visit. `manifest.json` makes it installable
  ("Zum Startbildschirm hinzufügen").

## 5. Data model & storage

### 5.1 The `data` object

Keyed by field id as today, with two changes:

- **`roofs: []`** — array of roof objects, replacing the fixed `d1_*` / `d2_*` fields:
  `{ label?, azimut, neigung, laenge, breite, verschattung, module }`.
- **`photos: []`** — photo **metadata only** (no binary):
  `{ id, category, name, mime, size }`. The `id` links to the blob in IndexedDB.
- Plus a small internal envelope for saved leads: `leadId` (stable id), `_savedAt`, and send status
  `{ sent: bool, serverId?: number, sentAt?: string }`.

### 5.2 Storage split

- **`localStorage`**
  - `lead-data` — the current draft (`data` JSON, no binary).
  - `lead-list` — array of saved leads (each is a `data` JSON snapshot + envelope), capped at 50.
- **IndexedDB** (`ti-leads` DB, `photos` store)
  - One record per photo: `{ id, leadId, category, blob, thumb, name, mime, size, createdAt }`.
  - `thumb` is a small downscaled JPEG blob for the grid; `blob` is the full (compressed) image.
  - Loading a saved lead restores its photos by `leadId`. Deleting/resetting a lead prunes its
    IndexedDB records.

### 5.3 Image handling

Phone photos are large. On attach, each image is downscaled to **max ~1600 px on the long edge and
re-encoded to JPEG (~0.8 quality)** via an off-screen canvas before storing, and a separate small
thumbnail (~200 px) is generated for the grid. Non-image files (e.g. PDF) are stored as-is. Client
enforces the same type/size limits the backend uses (mirrors `backend/src/upload.php`:
JPEG/PNG/HEIC/WebP/PDF, per-file and total caps).

## 6. Features

### 6a. Collapsible sections

Each section is an accordion card. Header row: **number · icon · title · completion badge · chevron**.
Clicking the header (or chevron) toggles the body. Open/closed state persists in view state. The
completion badge shows filled/total for that section (e.g. `3 / 8`) and a check when complete, so
progress is visible while collapsed. The sidebar/section-jumper still navigates to a section and
auto-expands it.

### 6b. Dynamic roof surfaces

Under the "Dach" subheader in **Objekt / Gebäude**, the hardcoded *Dachfläche 1* / *Dachfläche 2*
blocks are replaced by a `roofs[]` list. Each roof renders as a card with an optional label and the
existing fields (Ausrichtung/azimut, Neigung, Länge, Breite, Verschattung, Module). A
**＋ Dachfläche hinzufügen** button appends a roof; each roof has a 🗑 remove button. The list starts
with one roof. `buildText`, the PDF, and the payload all iterate `roofs[]`.

### 6c. Photo / file upload

The existing "Mitgenommen" checklist (Stromrechnung, Foto Dach, …) is kept as a checklist. Below it,
an upload area:

- **Add button** uses `<input type="file" accept="image/*" capture="environment" multiple>` so
  mobile offers the camera directly, and gallery/file selection otherwise.
- Uploaded items render as a **thumbnail grid**; each tile shows the thumbnail, filename, size, an
  optional **category** selector (Dach, Zählerschrank, Hausanschluss, Heizraum, WP-Platz, Sparren,
  Garage/Wallbox, Sonstiges — aligned with the checklist), and a remove button.
- Blobs live in IndexedDB (§5.2); metadata lives in `data.photos`.

### 6d. PDF — filled fields only + photos

A dedicated **off-screen print-document builder** (not the interactive form) renders:

- A clean header: title, Lead-Nr., customer name/firm, date (reusing today's `pdf-header` approach).
- **Only fields that have a value.** A section with no filled fields is omitted entirely. Radio/
  checkbox groups render their selected values only. `roofs[]` render as compact per-roof blocks.
- A **photos section**: the stored images in a labeled grid (category + filename), at print-
  reasonable resolution (from the compressed blobs, not thumbnails).

Rendered via the bundled `html2pdf`. Filename: `Lead_<Nr>_<Nachname>_<Datum>.pdf`. The native
**Print** button remains as a fallback using a print stylesheet.

## 7. Delivery & backend payload contract

Two actions replace today's Save/E-Mail/PDF cluster:

- **Als PDF speichern** — offline; downloads the §6d PDF.
- **An Büro senden** — `POST /api/lead.php` as `multipart/form-data`:
  - `payload` — JSON string of the full `data` object: all scalar/array field values (by id) +
    `roofs[]` + `photos[]` metadata + meta (`datum`, `berater`, `lead_nr`, signatures).
  - `files[]` — the photo blobs (full compressed images), with original filenames; category travels
    in `payload.photos[i].category` matched by order/id.
  - `csrf` — CSRF token (per existing backend convention).
  - `website` — honeypot field, empty (per existing backend convention).
  - **Success** `{ ok: true, id }` → toast "An Büro gesendet ✓"; mark lead `sent` with `serverId`
    + timestamp.
  - **Failure / offline** → lead stays flagged **nicht gesendet**; a retry ("Erneut senden") button
    is shown. Background-sync auto-retry is a noted later enhancement, not in scope.

The **field id → label → type** map (derived from the existing `SECTIONS` schema, plus `roofs[]` and
`photos[]`) is the authoritative contract the backend sub-project implements. The backend stores the
JSON payload + a few indexed columns (name, lead_nr, city, created_at, sent status) rather than a
column-per-field, and stores `files[]` via the existing `store_uploads()` pattern.

## 8. Visual redesign (mobile-first)

- **Single-column, large touch targets (≥44 px), bigger inputs, generous spacing, high contrast**
  for outdoor readability. Brand blue (`#2264a8`) retained with a refined palette and type scale;
  same brand feel as the main site.
- **Sticky compact top bar:** title + overall progress + primary actions (Speichern, An Büro
  senden), with secondary actions in an overflow menu.
- **Navigation:** desktop keeps the sticky sidebar; on mobile it becomes a "Sprung zu Abschnitt"
  dropdown/sheet.
- **Autosave indicator:** subtle "gespeichert" state, since input already persists to `localStorage`.

### 8.1 Conditional trade sections

The trade-specific sections (**PV-Anlage, Batteriespeicher, Wärmepumpe, Wallbox**, and PV-Module)
**auto-expand when their item is selected in *Interesse / Gewerke*, and stay collapsed otherwise —
never fully hidden**, so a section is always reachable if the customer changes their mind. A
**"Alle Abschnitte anzeigen"** toggle reveals/expands everything.

## 9. Actions (final set)

Speichern · Neuer Lead · Gespeicherte Leads (load) · Zurücksetzen · JSON Export · JSON Import ·
**Als PDF speichern** · **An Büro senden** · Drucken. The `mailto:` **E-Mail button is removed**.
JSON export/import covers `data` only (photos travel via IndexedDB / send); a full photo backup is a
possible later enhancement.

## 10. Reactivity & rendering

Replace the current "mutate `data` → call `render()` → `innerHTML` the whole layout" loop, which
loses input focus and scroll position and won't scale to conditional sections + dynamic roofs:

- **Event delegation** on a container using `data-*` attributes — no inline `onclick` handlers.
- **Targeted updates:** typing updates only `data` + the affected progress/badge nodes; toggling a
  radio/checkbox updates only that group; adding/removing a roof or photo patches only that list.
- Section expand/collapse and conditional visibility are class toggles, not re-renders.

## 11. Code check — fixes rolled into the rework

- Whole-DOM rebuild on every input → replaced by delegation + targeted updates (§10).
- Inline `onclick="handleRadio('${f.id}','${esc(o)}')"` (data in a JS-string-in-HTML-attribute
  context; fragile quoting/injection) → removed via delegation + `data-` attributes.
- `.btn { min-width: 120; }` missing `px` unit.
- `mailto:` body can exceed URL length limits → replaced by the backend send (§7).
- Add IndexedDB error handling + storage-quota handling; client-side file type/size validation
  mirroring the backend limits.

## 12. Testing / verification

Zero-build is preserved, so **no JS test framework by default**. Pure logic (progress %, filled-
field filtering for the PDF, payload builder, roof add/remove, image downscaling) is factored into
isolated functions so it is unit-testable later without a build. Verification for this spec is a
**documented manual QA checklist**:

1. Fill fields across sections; reload → draft restored (localStorage).
2. Collapse/expand sections; badges reflect filled counts; state persists.
3. Select/deselect trades in *Interesse/Gewerke* → matching sections expand/collapse; "Alle
   anzeigen" reveals all.
4. Add/remove roofs; values persist and appear in PDF + payload.
5. Attach photos (camera + gallery); thumbnails + categories; reload → photos restored (IndexedDB).
6. PDF shows only filled fields + the photos; filename correct.
7. "An Büro senden" online → success + marked sent; offline → flagged, retry works.
8. Install to home screen; open offline → app loads and runs (service worker).

## 13. Follow-up sub-project

**Backend leads endpoint** (separate spec + plan): `public/api/lead.php` with a testable
`lead_handle()`, a `leads` table (JSON payload + indexed columns) and `lead_attachments`, reuse of
`upload.php` / `mailer.php` / `validate.php` / `rate_limit.php` / `csrf.php`, an admin detail view
(`type=lead`) with photo viewing, operator-notification mail, and PHPUnit tests mirroring
`AngebotEndpointTest`. It implements the §7 contract.
