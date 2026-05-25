# Deployment Runbook — Contact Backend

This runs **once** to set up the backend on the Plesk Ubuntu VPS.

## 1. Plesk subdomains

In Plesk → Websites & Domains, add two subdomains under `technik-prignitz.de`:

| Subdomain | Document root |
| --- | --- |
| `api.technik-prignitz.de`   | `httpdocs/backend/public/api`   |
| `admin.technik-prignitz.de` | `httpdocs/backend/public/admin` |

For each, click **SSL/TLS Certificates → Install Let's Encrypt** (cover both the bare and www variants if applicable).

## 2. PHP

Both subdomains → **PHP Settings**, select **PHP 8.2 or newer** (the dev environment runs on 8.4; the code is straight PHP and works on any 8.x).

For `api.technik-prignitz.de` only, raise:

| Setting | Value |
| --- | --- |
| `upload_max_filesize` | `12M` |
| `post_max_size`       | `60M` |
| `max_file_uploads`    | `12`  |
| `memory_limit`        | `128M` |

## 3. Database

In Plesk → Databases → Add Database:
- Name: `ti_backend`
- User: `ti_backend_app` (Plesk-generated strong password — copy it)

Load schema:
```bash
mysql -u ti_backend_app -p ti_backend < backend/sql/schema.sql
```

## 4. Mail

- Create address `anfrage@technik-prignitz.de` in Plesk → Mail (or alias).
- Plesk → Mail → **Mail Settings** → confirm **DKIM spam protection** is enabled.
- Check SPF + DMARC are publishing as expected via [mxtoolbox.com](https://mxtoolbox.com) for `technik-prignitz.de`.

## 5. Backend files

SSH in and:
```bash
cd ~/httpdocs   # or your Plesk document-root parent
git clone <repo-url> .   # or git pull if already cloned
cd backend
composer install --no-dev --no-interaction
cp config/config.example.php config/config.php
chmod 640 config/config.php
mkdir -p storage/uploads storage/logs
chmod -R 750 storage
```

Edit `backend/config/config.php`:
- DB host/port/database/username/password (from step 3)
- `mail.from_address`, `to_address`
- `urls.admin_base`, `public_site`
- Remove `'http://localhost'` from `cors_origins`

## 6. Initial admin account

```bash
cd backend && php cron/setup-admin.php
# Enter username + password (8 char min), then:
rm cron/setup-admin.php
```

## 7. Cron jobs

Plesk → Scheduled Tasks (under the domain). Add three:

| Schedule | Command |
| --- | --- |
| Hourly (`0 * * * *`)      | `php /var/www/vhosts/technik-prignitz.de/httpdocs/backend/cron/cleanup_rate_limit.php` |
| Daily 03:00 (`0 3 * * *`) | `php /var/www/vhosts/technik-prignitz.de/httpdocs/backend/cron/retention.php` |
| Daily 08:00 (`0 8 * * *`) | `php /var/www/vhosts/technik-prignitz.de/httpdocs/backend/cron/mail_health.php` |

(Adjust the absolute path to match the real `httpdocs` of the site in your Plesk install.)

## 8. Smoke tests

- Visit `https://api.technik-prignitz.de/contact.php` from a browser → expect `{"ok":false,"error":"method_not_allowed"}`.
- Submit the homepage form on `https://technik-prignitz.de` with a real address; check inbox + admin.
- Submit the Angebot form with a small JPG; download from the admin detail view.
- Verify `mail_errors.log` does not grow (`tail -f backend/storage/logs/mail_errors.log`).

## 9. Updates afterwards

```bash
ssh <vps>
cd ~/httpdocs && git pull
cd backend && composer install --no-dev --no-interaction
# If schema.sql changed, apply the diff manually via phpMyAdmin.
```
