# Contact & Angebot Backend — Design

**Date:** 2026-05-25
**Status:** Design approved, ready for implementation plan
**Project:** Ti-page (Technik- & Instandsetzungs GmbH website)

## 1. Overview

The Ti-page website currently has two forms — a homepage contact form (`#kontakt`) and a multi-step Angebot (quote) form at `/angebot/`. Both today rely on `mailto:` links that open the visitor's email client with prefilled content. Nothing is stored, and there is no guarantee that the message ever reaches the company.

This design adds a real backend that:

1. Receives both form submissions over HTTPS,
2. Stores them in a MySQL database,
3. Notifies `info@technik-prignitz.de` by email on every submission,
4. Provides a small password-protected admin web UI for reading, organizing, and managing requests,
5. Accepts file uploads (photos / Stromrechnung / existing offers) attached to Angebot requests,
6. Protects against bots with an invisible honeypot + per-IP rate limiting,
7. Complies with GDPR (DSGVO) via documented retention and a right-to-delete action.

The backend runs on the existing Plesk-managed Ubuntu VPS as plain PHP 8.2 + MySQL with no framework and no Composer dependencies (Approach A from the brainstorming).

## 2. Goals & Non-Goals

**Goals**
- Replace fragile `mailto:` flow with a reliable, durable submission pipeline.
- Keep the operational surface small enough for one-person maintenance.
- Use only tools native to the Plesk hosting environment.
- Be GDPR-compliant from day one.

**Non-Goals (YAGNI for v1)**
- Multi-user admin / user-management UI (single admin account; schema supports adding more later).
- CSV / PDF export.
- In-app email composition (admin "reply" opens a regular `mailto:` link).
- Captcha (honeypot + rate-limit only; captcha is easy to add later if spam becomes a problem).
- Audit log of admin actions.
- Webhooks / CRM integration.
- Frontend framework (the site stays vanilla HTML/CSS/JS).

## 3. Architecture

### 3.1 Directory layout

A new `backend/` subdirectory is added next to the existing static site. The `src/` and `config/` directories live **outside** the web docroot.

```
Ti-page/
├── index.html, styles.css, script.js, ...    (unchanged)
├── angebot/, impressum/, ...                 (HTML/CSS/JS edits only)
└── backend/
    ├── public/                               ← web-exposed
    │   ├── api/                              ← api.technik-prignitz.de docroot
    │   │   ├── contact.php
    │   │   └── angebot.php
    │   └── admin/                            ← admin.technik-prignitz.de docroot
    │       ├── index.php
    │       ├── detail.php
    │       ├── attachment.php
    │       ├── login.php
    │       └── logout.php
    ├── src/                                  ← NOT web-exposed
    │   ├── db.php                            PDO connection helper
    │   ├── mailer.php                        mail() wrapper, header-safe
    │   ├── rate_limit.php
    │   ├── auth.php                          session + bcrypt helpers
    │   ├── csrf.php
    │   ├── upload.php                        multipart file handling
    │   └── validate.php
    ├── config/
    │   ├── config.php                        secrets (gitignored)
    │   └── config.example.php
    ├── cron/
    │   ├── setup-admin.php                   one-time, deleted after use
    │   ├── cleanup_rate_limit.php            hourly
    │   ├── retention.php                     daily
    │   └── mail_health.php                   daily
    └── storage/                              ← NOT web-exposed
        ├── uploads/<angebot_id>/<random>.<ext>
        └── logs/{app,mail_errors}.log
```

### 3.2 Subdomains

| Subdomain | Docroot | Purpose |
| --- | --- | --- |
| `api.technik-prignitz.de` | `backend/public/api/` | Public submission endpoints |
| `admin.technik-prignitz.de` | `backend/public/admin/` | Session-protected admin UI |

Both protected by Let's Encrypt certs via Plesk.

### 3.3 Request flow

**Form submission:**
Visitor submits → JS sends `fetch` to `api.technik-prignitz.de/{contact,angebot}.php` → endpoint validates → honeypot check → rate-limit check → DB insert → email notification + visitor autoreply → returns `{ ok: true, id }`.

**Admin:**
Admin loads `admin.technik-prignitz.de` → if no session → redirect to `login.php` → after login → list of requests with filters → detail page with status / notes / attachment downloads.

### 3.4 CORS

Public API endpoints respond to `OPTIONS` preflight and set:
```
Access-Control-Allow-Origin: https://technik-prignitz.de
Access-Control-Allow-Origin: https://www.technik-prignitz.de
Access-Control-Allow-Methods: POST, OPTIONS
Access-Control-Allow-Headers: Content-Type
```
(Echoed from a static allowlist of the two main-site origins.)

## 4. Data Model

All tables `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

```sql
CREATE TABLE contact_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name            VARCHAR(200) NOT NULL,
    contact         VARCHAR(200) NOT NULL,    -- free text: email or phone
    topic           VARCHAR(200) NULL,
    message         TEXT NOT NULL,
    ip_address      VARBINARY(16) NULL,
    user_agent      VARCHAR(500) NULL,
    status          ENUM('new','in_progress','handled','spam') NOT NULL DEFAULT 'new',
    handled_at      DATETIME NULL,
    notes           TEXT NULL,
    INDEX (created_at), INDEX (status)
);

CREATE TABLE angebot_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name            VARCHAR(200) NOT NULL,
    phone           VARCHAR(100) NOT NULL,
    email           VARCHAR(200) NOT NULL,
    components      VARCHAR(500) NOT NULL,    -- CSV: "Photovoltaik, Stromspeicher"
    building        VARCHAR(100) NULL,
    location        VARCHAR(200) NULL,
    roof            VARCHAR(100) NULL,
    usage_profile   VARCHAR(100) NULL,
    consumption     VARCHAR(100) NULL,
    timeline        VARCHAR(100) NULL,
    details         TEXT NULL,
    photos_followup TINYINT(1) NOT NULL DEFAULT 0,
    ip_address      VARBINARY(16) NULL,
    user_agent      VARCHAR(500) NULL,
    status          ENUM('new','in_progress','handled','spam') NOT NULL DEFAULT 'new',
    handled_at      DATETIME NULL,
    notes           TEXT NULL,
    INDEX (created_at), INDEX (status)
);

CREATE TABLE angebot_attachments (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    angebot_id     INT UNSIGNED NOT NULL,
    stored_name    VARCHAR(120) NOT NULL,
    original_name  VARCHAR(255) NOT NULL,
    mime_type      VARCHAR(100) NOT NULL,
    size_bytes     INT UNSIGNED NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (angebot_id) REFERENCES angebot_requests(id) ON DELETE CASCADE,
    INDEX (angebot_id)
);

CREATE TABLE rate_limit (
    ip_address      VARBINARY(16) NOT NULL,
    window_start    DATETIME NOT NULL,
    request_count   INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (ip_address, window_start)
);

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   CHAR(60) NOT NULL,        -- bcrypt
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login      DATETIME NULL,
    failed_logins   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL
);
```

**Notes**
- `ip_address` stored as packed bytes (4 for IPv4, 16 for IPv6) via `inet_pton()`.
- `components` is a comma-separated string on Angebot — simple, matches the email body, supports `LIKE` search in the admin.
- Status `spam` is a soft-delete: kept for review but excluded from default lists.

## 5. Public Submission API

### 5.1 `POST https://api.technik-prignitz.de/contact.php`

Content-Type: `application/json`

```json
{
  "name":     "Max Mustermann",
  "contact":  "max@example.de",
  "topic":    "Photovoltaik",
  "message":  "Wir möchten ...",
  "website":  ""           // honeypot, must be empty
}
```

### 5.2 `POST https://api.technik-prignitz.de/angebot.php`

Content-Type: `multipart/form-data` (because of file uploads)

Fields (request name → DB column where they differ):
- `name`, `phone`, `email` (required)
- `components[]` (one or more, required) — joined into a CSV string for `angebot_requests.components`
- `building`, `location`, `roof`, `consumption`, `timeline`, `details` (optional)
- `usage` → DB column `usage_profile` (column avoids the MySQL `USAGE` keyword)
- `photos_followup` ("0" or "1")
- `privacy` ("1" required — consent)
- `files[]` (zero or more uploads, see §6)
- `website` (honeypot, must be empty)

### 5.3 Responses (both endpoints)

| HTTP | Body | Meaning |
| --- | --- | --- |
| 200 | `{ "ok": true, "id": 42 }` | Stored and email queued |
| 400 | `{ "ok": false, "error": "validation", "fields": { "email": "…" } }` | Field errors (German messages) |
| 413 | `{ "ok": false, "error": "too_large" }` | Upload exceeded limits |
| 429 | `{ "ok": false, "error": "rate_limit" }` | Too many requests from this IP |
| 500 | `{ "ok": false, "error": "server" }` | Internal error (logged) |

### 5.4 Server-side processing order

1. CORS preflight handling (`OPTIONS` → 204 + headers).
2. **Honeypot:** if `website` non-empty, return `{ ok: true }` without storing or emailing.
3. **Rate limit:** max **5 submissions per IP per hour** via sliding window in `rate_limit` table → 429 if exceeded.
4. **Validation:** required fields, lengths, email regex, consent flag → 400 with per-field German messages.
5. **Sanitization:** trim strings; HTML is **not** stripped — stored as-is, escaped on output (`htmlspecialchars`).
6. **File handling** (Angebot only — see §6).
7. **DB insert** via PDO prepared statements (only mechanism for DB writes — SQL injection impossible).
8. **Email notification** (and visitor autoreply) — failures logged but do not change the response.

### 5.5 Security notes

- No CSRF token on public endpoints — stateless, no cookies, JSON/multipart only, locked by CORS allowlist.
- Admin UI uses CSRF tokens (§7).
- `Subject` line for outgoing email is stripped of CRLF and truncated (header-injection prevention).
- Server enforces *all* limits even if frontend already validated.

## 6. File Uploads (Angebot only)

**Per file:** ≤ 10 MB, MIME type ∈ {`image/jpeg`, `image/png`, `image/heic`, `image/webp`, `application/pdf`}.
**Per request:** ≤ 10 files, ≤ 50 MB total.

**Response codes on rejection:**
- Single file > 10 MB **or** total upload > 50 MB → `HTTP 413 too_large`.
- File count > 10 **or** disallowed MIME type → `HTTP 400 validation` with the offending file name in `fields.files`.
- Plesk-level rejection (request body exceeds `post_max_size`) → returns Plesk's default 413 before PHP runs; the frontend treats any 413 as "too large" with a friendly German message.

**MIME verification:** `finfo_file()` reads magic bytes server-side. The client-supplied `type` is ignored.

**Storage:** `backend/storage/uploads/<angebot_id>/<random-12-char>.<ext>` — outside web docroot. Extension is derived from the verified MIME, not the original filename.

**Naming:** original filename kept in `angebot_attachments.original_name` for admin display; on disk, only the random name is used.

**Download:** through `admin/attachment.php?id=N` — checks the admin session, looks up the path in DB, streams the file with correct `Content-Type` and `Content-Disposition: attachment; filename="<original>"`.

**Deletion:** `ON DELETE CASCADE` removes rows when an Angebot request is deleted; the same code path also `unlink()`s the files and removes the parent directory.

## 7. Admin UI

### 7.1 Pages

| Page | URL | Purpose |
| --- | --- | --- |
| Login | `/login.php` | Username + password → session |
| Logout | `/logout.php` | Destroys session |
| List | `/index.php` | Both request types, filterable |
| Detail | `/detail.php?type={contact\|angebot}&id=N` | Full record + actions |
| Attachment | `/attachment.php?id=N` | Streams an upload (session-checked) |

### 7.2 List view

- Two tabs (Angebot, Kontakt) with a counter of "new" items per tab.
- Columns: ID, date, name, contact, status badge, view action.
- Filters: status (all / new / in_progress / handled / spam), free-text search across name + email + message.
- Default: status=new, sorted by `created_at DESC`.

### 7.3 Detail view

- All form fields displayed in readable groups.
- Attachments (Angebot): image thumbnails or PDF icon, click to download.
- **Status dropdown** (New / In progress / Handled / Spam) — saves immediately via AJAX with CSRF token.
- **Internal notes** textarea + save button.
- Meta footer (collapsed): created-at, IP (anonymized after 30 days), user-agent.
- **Reply by email** button → opens `mailto:` to the visitor with subject prefilled.
- **Delete request** button (with confirmation) — hard-deletes record + cascaded attachments + files on disk. Used for DSGVO Art. 17 requests.

### 7.4 Authentication

- **Single admin user** for v1. The `users` table is in place so adding more is just SQL + a small "Users" page later.
- **Password:** bcrypt via `password_hash()` / `password_verify()`.
- **Sessions:** PHP sessions, cookie flags `HttpOnly`, `Secure`, `SameSite=Lax`. 8-hour idle timeout.
- **Brute-force protection:** 5 failed logins in a row → lock account for 15 minutes (`locked_until` in `users`).
- **CSRF token** on every mutating form (status, notes, delete).
- Login endpoint always returns generic "Falsche Anmeldedaten" (no enumeration).

### 7.5 Styling

Separate small `admin.css`. Matches brand colors (TI logo / accent) but utilitarian — no hero imagery, no lucide icons (use plain text labels and emoji or unicode symbols for status badges). Faster, simpler, isolated from changes to the public-site CSS.

## 8. Email Notifications

**Delivery:** PHP `mail()` via Plesk's local Postfix. DKIM signing enabled at the Plesk level (verify in Plesk → Mail → Mail Settings). SPF and DMARC verified on initial setup using mxtoolbox.

**Notification email (to operator):**
- From: `anfrage@technik-prignitz.de`
- To: `info@technik-prignitz.de`
- Reply-To: visitor's email
- Plain text, German
- Subject: `Neue Kontaktanfrage: <topic> (#42)` or `Neue Angebotsanfrage: <components> (#42)`
- Body: all fields grouped, plus link to admin detail page, plus attachment summary (count + total size; never attaches files themselves)

**Visitor autoreply:**
- From: `anfrage@technik-prignitz.de`
- To: visitor's email
- Plain text, German
- Subject: `Ihre Anfrage bei Technik- & Instandsetzungs GmbH`
- Body: short acknowledgement, summary of what they submitted, expected response time ("innerhalb von 2 Werktagen"), phone + address footer
- Sent only if a valid email is present and the request was stored

**Failure handling:** failed `mail()` returns are logged to `backend/storage/logs/mail_errors.log` with timestamp + request ID. The visitor still gets a success response — DB is the source of truth. A daily `mail_health.php` cron sends a one-liner to `info@` if any failures occurred in the past 24 h.

**Header injection prevention:** any user-supplied data that ends up in `Subject` or as a `Reply-To` value is stripped of CRLF and truncated.

## 9. Frontend Changes

Only `script.js`, the two form HTML files, and `styles.css` are touched. No new dependencies.

### 9.1 HTML edits

**Both forms** — invisible honeypot as the *last* input:
```html
<input type="text" name="website" tabindex="-1" autocomplete="off"
       class="visually-hidden" aria-hidden="true">
```

**Both forms** — visible noscript fallback near the submit button:
```html
<noscript>
  <p class="form-noscript">
    <strong>Hinweis:</strong> Für das Senden dieses Formulars benötigen wir JavaScript.
    Sie erreichen uns auch direkt unter
    <a href="tel:+493876612474">+49 3876 612474</a>
    oder per E-Mail an
    <a href="mailto:info@technik-prignitz.de">info@technik-prignitz.de</a>.
  </p>
</noscript>
```

**Angebot form only** — file input replacing the "photos können nachgereicht werden" checkbox flag:
```html
<label class="offer-field">
  Fotos, Stromrechnung oder vorhandene Angebote (optional)
  <input type="file" name="files" multiple
         accept="image/jpeg,image/png,image/heic,image/webp,application/pdf"
         data-offer-files>
  <small>JPG, PNG, HEIC, WEBP oder PDF. Max. 10 Dateien, je max. 10 MB.</small>
</label>
<ul class="offer-file-list" data-offer-file-list></ul>
```

(The `photos_followup` boolean is still sent — derived from whether files were attached.)

### 9.2 `script.js` changes

- Replace both `mailto:` submit handlers with `fetch()` calls.
- Contact form sends JSON. Angebot form sends `FormData` (multipart) because of file uploads.
- Submit button is disabled while in-flight; status text shows "Wird gesendet…".
- Success: success message + `form.reset()`.
- 429: friendly rate-limit message ("…in einer Stunde erneut").
- Validation error (400): per-field highlights via `.has-error` class plus a summary status.
- Other error: generic message with fallback phone number.

### 9.3 CSS additions

- `.visually-hidden` utility.
- `.form-noscript` warning-box style.
- `.form-status.is-success` / `.form-status.is-error`.
- `.has-error` per-field highlight (red border).
- `.offer-file-list` styling: file row with name, size, remove (×) button.

### 9.4 Privacy text

`datenschutzerklaerung/index.html` gets a short new section listing what is stored, the legal basis (DSGVO Art. 6 Abs. 1 lit. b for Angebot, lit. f for contact), the 12-month retention for handled requests, and the right to deletion via `info@technik-prignitz.de`.

## 10. Deployment & Operations

### 10.1 One-time Plesk setup

1. **Subdomains:** add `api.technik-prignitz.de` and `admin.technik-prignitz.de`; point each at the corresponding `backend/public/*` folder.
2. **SSL:** Let's Encrypt for both subdomains.
3. **PHP:** version 8.2 on both.
4. **PHP settings on `api.` subdomain only:** `upload_max_filesize=12M`, `post_max_size=60M`, `max_file_uploads=12`, `memory_limit=128M`.
5. **MySQL:** create database `ti_backend` and user `ti_backend_app` (Plesk-generated password). Grant only that DB.
6. **Mail:** create alias `anfrage@technik-prignitz.de`; verify DKIM, SPF, DMARC.
7. **Cron (Plesk → Scheduled Tasks):**
   - Hourly: `cron/cleanup_rate_limit.php` — drops rows older than 2 hours.
   - Daily 03:00: `cron/retention.php` — applies §11.
   - Daily 08:00: `cron/mail_health.php` — alerts on mail failures.

### 10.2 Secrets

- `backend/config/config.php` holds DB credentials, base URLs, mail-from. Permissions `0640`, owned by the PHP user. **Not** committed; `config.example.php` with placeholders is committed.
- No secrets in any web-accessible file.

### 10.3 Initial admin account

`php backend/cron/setup-admin.php` is run once via SSH after deploy. Prompts for username + password, writes bcrypt hash to `users`, prints "Done — delete this file now". The script is then removed.

### 10.4 Logs

- `backend/storage/logs/app.log` — server errors.
- `backend/storage/logs/mail_errors.log` — mail-only failures.
- Rotated weekly via logrotate (small config added during deploy) or Plesk-managed if named conventionally.
- Apache/Nginx access logs kept by Plesk as usual.

### 10.5 Deploy method

Initial: SFTP via Plesk File Manager + run schema in phpMyAdmin + run `setup-admin.php`.
Ongoing: SFTP, or Plesk Git deployment for one-click updates (recommended once the project is initialized as a git repo — currently it is not).

## 11. GDPR / Data Retention

- **What is stored:** form fields, IP address, user-agent, timestamp, uploaded files. Documented in the Datenschutzerklärung.
- **Purpose / legal basis:** Angebot — DSGVO Art. 6 Abs. 1 lit. b (contractual); contact — lit. f (legitimate interest).
- **Retention:**
  - `new` / `in_progress` records: kept indefinitely until the operator changes status.
  - `handled` / `spam` records: auto-deleted **12 months** after `handled_at` by the daily `retention.php` cron. Deletion cascades to attachments (DB rows + files on disk).
  - IP addresses anonymized to `/24` (IPv4) or `/48` (IPv6) **30 days** after `created_at`. The original IP is overwritten in place — no separate audit copy.
- **Right to deletion (Art. 17):** "Delete request" button in the admin detail view → hard delete with confirmation.
- **No third-party processors:** all data and infrastructure on the Plesk VPS. No Google/Cloudflare fonts/scripts in the backend.

## 12. Risks & Mitigations

| Risk | Mitigation |
| --- | --- |
| Email lands in spam folder | DKIM/SPF/DMARC verified; SMTP swap is one-file change if needed. |
| Disk fills with uploads | 50 MB cap per request + 12-month retention + daily cron. Monitor via Plesk disk usage. |
| Honeypot insufficient against modern bots | Schema and code are ready for a captcha drop-in (config flag) if spam volume warrants. |
| Admin password compromised | bcrypt + lockout + session timeout; password rotated via running `setup-admin.php` again. |
| `mail()` silently fails | Logged + daily summary cron. |

## 13. Out of Scope (Phase 2 ideas)

- Multi-user admin with roles.
- CSV / PDF export.
- Status webhooks (e.g. to a CRM).
- Two-factor auth for admin.
- Email replies composed within the admin UI.
- Pagination of attachments by size for very large submissions.

## 14. Open Questions

None at design-approval time. All decisions are recorded above.
