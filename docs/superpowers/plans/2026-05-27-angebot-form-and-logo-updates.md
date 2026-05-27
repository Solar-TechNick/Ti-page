# Angebot form and logo updates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the angebot form revisions (Smart Home, EV-Ladestation rename, Freiland/Carport, address split, consumption presets, upload helper text) plus the header logo swap, including schema, validation, API, admin detail, tests, HTML, and CSS.

**Architecture:** Single-PR change across one static site + one PHP backend. The DB migration is additive (three new nullable columns; legacy `location` stays); the wizard `<form>`'s new inputs flow through unchanged via `FormData`. Backend validation, INSERT, operator email, visitor autoreply, and admin detail view get matching updates. PHPUnit tests cover validation and endpoint behavior; the frontend changes are purely declarative HTML/CSS and have no automated tests in this repo.

**Tech Stack:** Static HTML/CSS/JS; PHP 8 + PDO + MySQL; PHPUnit 10; lucide icons via CDN.

---

## File Structure

Modified:
- `index.html` — header brand image (`brand-mark` swap).
- `angebot/index.html` — header brand image plus Step 1/2/3/4 markup.
- `styles.css` — `.brand-mark.brand-logo` rule.
- `backend/sql/schema.sql` — append idempotent `ALTER TABLE` blocks for three address columns.
- `backend/src/validate.php` — drop `location`, add `address_street` / `address_postal` / `address_city` length checks.
- `backend/public/api/angebot.php` — INSERT new columns; replace `Standort/PLZ` email lines with address block; same for visitor autoreply.
- `backend/public/admin/detail.php` — replace `Standort/PLZ` `<dt>/<dd>` with `Adresse` block.
- `backend/tests/AngebotEndpointTest.php` — replace `location` in happy-path fixture; add address persistence + email body assertions.
- `backend/tests/ValidateTest.php` — add length-limit tests for new address fields.

Created:
- `assets/logo.png` — already in place from the user (not produced by this plan).

---

## Task 1: Schema migration — three new address columns

**Files:**
- Modify: `backend/sql/schema.sql` (append at end)

The project's existing pattern for adding columns to an already-deployed DB is an idempotent `INFORMATION_SCHEMA` lookup + conditional `PREPARE`/`EXECUTE` (see the `voucher_code` block at the end of the file). Apply the same pattern three times.

- [ ] **Step 1: Append idempotent ALTER TABLE blocks**

Append to `backend/sql/schema.sql`:

```sql

-- Idempotent add of address_street column on angebot_requests
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'angebot_requests'
               AND column_name = 'address_street');
SET @sql := IF(@col = 0,
  'ALTER TABLE angebot_requests ADD COLUMN address_street VARCHAR(200) NULL AFTER location',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Idempotent add of address_postal column on angebot_requests
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'angebot_requests'
               AND column_name = 'address_postal');
SET @sql := IF(@col = 0,
  'ALTER TABLE angebot_requests ADD COLUMN address_postal VARCHAR(20) NULL AFTER address_street',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Idempotent add of address_city column on angebot_requests
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'angebot_requests'
               AND column_name = 'address_city');
SET @sql := IF(@col = 0,
  'ALTER TABLE angebot_requests ADD COLUMN address_city VARCHAR(100) NULL AFTER address_postal',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

- [ ] **Step 2: Apply the migration to the test database**

The test suite uses the same MySQL database (test DB credentials come from `backend/src/bootstrap.php` / `config/`). Apply the schema so PHPUnit can see the new columns:

Run from `backend/`:
```bash
cat sql/schema.sql | mysql --defaults-file=<your-test-credentials> <test-db-name>
```

If you don't know the exact command for this environment, check `backend/config/` for DB credentials and how other developers run the schema. Re-running schema.sql is safe — it is fully idempotent.

Expected: no errors. Verify with:
```bash
mysql ... -e "SHOW COLUMNS FROM angebot_requests LIKE 'address_%'" <db>
```
Should list `address_street`, `address_postal`, `address_city`.

- [ ] **Step 3: Commit**

```bash
git add backend/sql/schema.sql
git commit -m "feat(angebot): add address_street/postal/city columns to schema"
```

---

## Task 2: Validation — length limits for new address fields

**Files:**
- Modify: `backend/src/validate.php:69`
- Test: `backend/tests/ValidateTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `backend/tests/ValidateTest.php` inside the `ValidateTest` class:

```php
    public function testAddressStreetLengthLimit(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>str_repeat('a', 201),
        ]);
        $this->assertNotEmpty($errors['address_street'] ?? null);
    }

    public function testAddressPostalLengthLimit(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_postal'=>str_repeat('1', 21),
        ]);
        $this->assertNotEmpty($errors['address_postal'] ?? null);
    }

    public function testAddressCityLengthLimit(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_city'=>str_repeat('a', 101),
        ]);
        $this->assertNotEmpty($errors['address_city'] ?? null);
    }

    public function testAddressFieldsAtLimitAreAccepted(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>str_repeat('a', 200),
            'address_postal'=>str_repeat('1', 20),
            'address_city'=>str_repeat('a', 100),
        ]);
        $this->assertSame([], $errors);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

From `backend/`:
```bash
vendor/bin/phpunit --filter 'Address' tests/ValidateTest.php
```

Expected: the three length-limit tests fail (errors arrays are empty because the validator does not yet check these fields). `testAddressFieldsAtLimitAreAccepted` may pass — that's fine; it's a guard rail for the fix.

- [ ] **Step 3: Update the validator**

In `backend/src/validate.php`, replace the existing length-loop line at line 69:

```php
    foreach (['building'=>100,'location'=>200,'roof'=>100,'usage'=>100,'consumption'=>100,'timeline'=>100] as $k => $max) {
```

with:

```php
    foreach ([
        'building'      => 100,
        'location'      => 200,
        'address_street'=> 200,
        'address_postal'=> 20,
        'address_city'  => 100,
        'roof'          => 100,
        'usage'         => 100,
        'consumption'   => 100,
        'timeline'      => 100,
    ] as $k => $max) {
```

Note: `location` stays in the list. The legacy column is no longer written by the API (Task 3) but if a client still posts it, length validation still protects the DB.

- [ ] **Step 4: Run tests to verify they pass**

```bash
vendor/bin/phpunit --filter 'Address' tests/ValidateTest.php
```

Expected: all four tests pass.

- [ ] **Step 5: Run the full validate test class to confirm no regression**

```bash
vendor/bin/phpunit tests/ValidateTest.php
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add backend/src/validate.php backend/tests/ValidateTest.php
git commit -m "feat(angebot): validate address_street/postal/city length limits"
```

---

## Task 3: API — persist address fields and update email bodies

**Files:**
- Modify: `backend/public/api/angebot.php`
- Test: `backend/tests/AngebotEndpointTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `backend/tests/AngebotEndpointTest.php` inside the class:

```php
    public function testAddressFieldsArePersisted(): void
    {
        $result = angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Quitzower Damm 15',
            'address_postal'=>'19348',
            'address_city'=>'Sükow',
        ], [], pack_ip('192.0.2.70'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT * FROM angebot_requests')->fetch();
        $this->assertSame('Quitzower Damm 15', $row['address_street']);
        $this->assertSame('19348',             $row['address_postal']);
        $this->assertSame('Sükow',             $row['address_city']);
    }

    public function testOperatorEmailContainsAddressBlock(): void
    {
        angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Quitzower Damm 15',
            'address_postal'=>'19348',
            'address_city'=>'Sükow',
        ], [], pack_ip('192.0.2.71'), 'UA');

        $this->assertStringContainsString('Adresse:', $this->mails[0]['body']);
        $this->assertStringContainsString('Quitzower Damm 15', $this->mails[0]['body']);
        $this->assertStringContainsString('19348 Sükow',       $this->mails[0]['body']);
        $this->assertStringNotContainsString('Standort/PLZ',   $this->mails[0]['body']);
    }

    public function testVisitorAutoreplyContainsAddress(): void
    {
        angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Quitzower Damm 15',
            'address_postal'=>'19348',
            'address_city'=>'Sükow',
        ], [], pack_ip('192.0.2.72'), 'UA');

        // Visitor reply is the second captured mail.
        $this->assertStringContainsString('Adresse:', $this->mails[1]['body']);
        $this->assertStringContainsString('Quitzower Damm 15, 19348 Sükow', $this->mails[1]['body']);
    }

    public function testEmailAddressFallsBackToDashWhenAllFieldsEmpty(): void
    {
        angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
        ], [], pack_ip('192.0.2.73'), 'UA');

        // Operator email: street line should show '-' and the combined PLZ/Ort line too.
        $body = $this->mails[0]['body'];
        $this->assertMatchesRegularExpression('/Adresse:\s+-/', $body);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit --filter 'Address|Email|Autoreply' tests/AngebotEndpointTest.php
```

Expected: the four new tests fail — `address_street` etc. are not yet inserted; emails still say `Standort/PLZ`.

- [ ] **Step 3: Update the INSERT statement and parameters**

In `backend/public/api/angebot.php`, replace the existing `INSERT` prepare and execute (currently lines 37–60). Find:

```php
    $stmt = db()->prepare(
        "INSERT INTO angebot_requests
        (name, phone, email, components, building, location, roof, usage_profile,
         consumption, timeline, details, voucher_code, photos_followup, ip_address, user_agent)
        VALUES (:name,:phone,:email,:components,:building,:location,:roof,:usage,
                :consumption,:timeline,:details,:voucher,:photos,:ip,:ua)"
    );
    $stmt->execute([
        ':name'        => trim($input['name']),
        ':phone'       => trim($input['phone']),
        ':email'       => trim($input['email']),
        ':components'  => mb_substr($components, 0, 500),
        ':building'    => $input['building']    ?? null,
        ':location'    => $input['location']    ?? null,
        ':roof'        => $input['roof']        ?? null,
        ':usage'       => $input['usage']       ?? null,
        ':consumption' => $input['consumption'] ?? null,
        ':timeline'    => $input['timeline']    ?? null,
        ':details'     => $input['details']     ?? null,
        ':voucher'     => $voucherCode,
        ':photos'      => !empty($input['photos_followup']) ? 1 : 0,
        ':ip'          => $packed_ip,
        ':ua'          => mb_substr($userAgent, 0, 500),
    ]);
```

Replace with (drops `location`, adds three address columns):

```php
    $stmt = db()->prepare(
        "INSERT INTO angebot_requests
        (name, phone, email, components, building,
         address_street, address_postal, address_city,
         roof, usage_profile, consumption, timeline, details,
         voucher_code, photos_followup, ip_address, user_agent)
        VALUES (:name,:phone,:email,:components,:building,
                :addr_street,:addr_postal,:addr_city,
                :roof,:usage,:consumption,:timeline,:details,
                :voucher,:photos,:ip,:ua)"
    );
    $stmt->execute([
        ':name'        => trim($input['name']),
        ':phone'       => trim($input['phone']),
        ':email'       => trim($input['email']),
        ':components'  => mb_substr($components, 0, 500),
        ':building'    => $input['building']       ?? null,
        ':addr_street' => $input['address_street'] ?? null,
        ':addr_postal' => $input['address_postal'] ?? null,
        ':addr_city'   => $input['address_city']   ?? null,
        ':roof'        => $input['roof']           ?? null,
        ':usage'       => $input['usage']          ?? null,
        ':consumption' => $input['consumption']    ?? null,
        ':timeline'    => $input['timeline']       ?? null,
        ':details'     => $input['details']        ?? null,
        ':voucher'     => $voucherCode,
        ':photos'      => !empty($input['photos_followup']) ? 1 : 0,
        ':ip'          => $packed_ip,
        ':ua'          => mb_substr($userAgent, 0, 500),
    ]);
```

- [ ] **Step 4: Update the operator email body**

In `_angebot_notify_operator`, replace this block:

```php
    $voucherLine = !empty($in['voucher_code']) ? trim((string)$in['voucher_code']) : '';
    $projectLines = [
        '  Komponenten:  ' . $components,
        '  Objekt:       ' . ($in['building']    ?? '-'),
        '  Standort/PLZ: ' . ($in['location']    ?? '-'),
        '  Dachform:     ' . ($in['roof']        ?? '-'),
        '  Nutzung:      ' . ($in['usage']       ?? '-'),
        '  Verbrauch:    ' . ($in['consumption'] ?? '-'),
        '  Zeitraum:     ' . ($in['timeline']    ?? '-'),
    ];
```

with:

```php
    $voucherLine = !empty($in['voucher_code']) ? trim((string)$in['voucher_code']) : '';

    $street   = trim((string)($in['address_street'] ?? ''));
    $cityLine = trim(($in['address_postal'] ?? '') . ' ' . ($in['address_city'] ?? ''));
    $street   = $street   === '' ? '-' : $street;
    $cityLine = $cityLine === '' ? '-' : $cityLine;

    $projectLines = [
        '  Komponenten:  ' . $components,
        '  Objekt:       ' . ($in['building']    ?? '-'),
        '  Adresse:      ' . $street,
        '                ' . $cityLine,
        '  Dachform:     ' . ($in['roof']        ?? '-'),
        '  Nutzung:      ' . ($in['usage']       ?? '-'),
        '  Verbrauch:    ' . ($in['consumption'] ?? '-'),
        '  Zeitraum:     ' . ($in['timeline']    ?? '-'),
    ];
```

- [ ] **Step 5: Update the visitor autoreply body**

In `_angebot_autoreply_visitor`, replace:

```php
    $body = implode("\n", [
        'Vielen Dank für Ihre Angebotsanfrage.',
        '',
        'Wir haben Ihre Angaben erhalten und melden uns innerhalb von 2 Werktagen.',
        '',
        'Ihre Angaben (Auszug):',
        '  Komponenten: ' . $components,
        '  Objekt:      ' . ($in['building'] ?? '-'),
        '  Standort:    ' . ($in['location'] ?? '-'),
        '',
```

with:

```php
    $addressParts = array_filter([
        trim((string)($in['address_street'] ?? '')),
        trim(trim((string)($in['address_postal'] ?? '')) . ' ' . trim((string)($in['address_city'] ?? ''))),
    ], fn($p) => $p !== '');
    $addressLine = $addressParts ? implode(', ', $addressParts) : '-';

    $body = implode("\n", [
        'Vielen Dank für Ihre Angebotsanfrage.',
        '',
        'Wir haben Ihre Angaben erhalten und melden uns innerhalb von 2 Werktagen.',
        '',
        'Ihre Angaben (Auszug):',
        '  Komponenten: ' . $components,
        '  Objekt:      ' . ($in['building'] ?? '-'),
        '  Adresse:     ' . $addressLine,
        '',
```

- [ ] **Step 6: Run the new tests to verify they pass**

```bash
vendor/bin/phpunit --filter 'Address|Email|Autoreply' tests/AngebotEndpointTest.php
```

Expected: all four new tests pass.

- [ ] **Step 7: Run the full AngebotEndpointTest to confirm no regression**

```bash
vendor/bin/phpunit tests/AngebotEndpointTest.php
```

Expected: all tests pass. (The existing `testHappyPathStoresAndCsvComponents` still posts `'location'=>'19348'`; the validator still accepts it harmlessly, the INSERT ignores it, and the assertions on `name`/`components`/mail-count remain valid.)

- [ ] **Step 8: Commit**

```bash
git add backend/public/api/angebot.php backend/tests/AngebotEndpointTest.php
git commit -m "feat(angebot): persist split address and update mail bodies"
```

---

## Task 4: Admin detail view — replace Standort/PLZ with Adresse block

**Files:**
- Modify: `backend/public/admin/detail.php:55`

There is no PHPUnit coverage for this view; visually verify by loading a record after Task 3 lands.

- [ ] **Step 1: Replace the Standort/PLZ row**

In `backend/public/admin/detail.php`, find line 55:

```php
          <dt>Standort/PLZ</dt><dd><?= htmlspecialchars($row['location'] ?? '—') ?></dd>
```

Replace with:

```php
          <dt>Adresse</dt>
          <dd>
            <?php
              $cityLine = trim(($row['address_postal'] ?? '') . ' ' . ($row['address_city'] ?? ''));
              $parts = array_filter([
                $row['address_street'] ?? null,
                $cityLine,
              ], fn($p) => $p !== null && $p !== '');
              echo $parts ? htmlspecialchars(implode(', ', $parts)) : '—';
            ?>
          </dd>
```

- [ ] **Step 2: Manual verification**

After Task 3 has produced a row with address data, load the admin detail page for that record (`/backend/public/admin/detail.php?type=angebot&id=<id>` in dev) and confirm the `Adresse` row renders as `Quitzower Damm 15, 19348 Sükow` (or `—` for older rows that have no address fields). Use whatever method is normal for this project to view admin pages (PHP dev server, deployed staging, etc.).

If you cannot reach a running instance, say so explicitly; do not claim it works without seeing it.

- [ ] **Step 3: Commit**

```bash
git add backend/public/admin/detail.php
git commit -m "feat(admin): show Adresse instead of Standort/PLZ on angebot detail"
```

---

## Task 5: Frontend Step 1 — Wallbox rename + Smart Home option

**Files:**
- Modify: `angebot/index.html:107-110, 115-118`

- [ ] **Step 1: Rename the Wallbox option**

In `angebot/index.html`, find the current Wallbox option around lines 107–110:

```html
              <label class="option-card">
                <input type="checkbox" name="components" value="Wallbox / Ladestation">
                <span><i data-lucide="plug-zap" aria-hidden="true"></i> Wallbox</span>
              </label>
```

Replace with:

```html
              <label class="option-card">
                <input type="checkbox" name="components" value="Wallbox / EV-Ladestation">
                <span><i data-lucide="plug-zap" aria-hidden="true"></i> Wallbox / EV-Ladestation</span>
              </label>
```

- [ ] **Step 2: Add the Smart Home option**

Immediately after the Elektroinstallation `<label>` (currently lines 115–118), add:

```html
              <label class="option-card">
                <input type="checkbox" name="components" value="Smart Home">
                <span><i data-lucide="house-plug" aria-hidden="true"></i> Smart Home</span>
              </label>
```

So Step 1's grid now has six options in order: Photovoltaik, Stromspeicher, Wallbox / EV-Ladestation, Wärmepumpe, Elektroinstallation, Smart Home.

- [ ] **Step 3: Manual verification**

Open `angebot/index.html` in a browser (or via whatever local server the project uses). On Step 1 confirm:
- The "Wallbox / EV-Ladestation" card replaces "Wallbox".
- The "Smart Home" card appears with its icon. (If `house-plug` is not in this lucide build and the icon renders blank, change `data-lucide="house-plug"` to `data-lucide="smartphone"` as a fallback and re-verify.)
- Both checkboxes can be selected and Step 1's "select at least one" validation still works.

- [ ] **Step 4: Commit**

```bash
git add angebot/index.html
git commit -m "feat(angebot): rename Wallbox option, add Smart Home"
```

---

## Task 6: Frontend Step 2 — Freiland/Carport radios + address split

**Files:**
- Modify: `angebot/index.html:128-149`

- [ ] **Step 1: Add Freiland and Carport radio cards**

In `angebot/index.html`, inside the Step 2 `.option-grid` (lines ~128–145), after the existing "Industrie / Anlage" option, add:

```html
              <label class="option-card">
                <input type="radio" name="building" value="Freiland">
                <span><i data-lucide="trees" aria-hidden="true"></i> Freiland</span>
              </label>
              <label class="option-card">
                <input type="radio" name="building" value="Carport / Garage">
                <span><i data-lucide="car" aria-hidden="true"></i> Carport / Garage</span>
              </label>
```

- [ ] **Step 2: Replace the single location input with the three-field address block**

Find:

```html
            <label class="offer-field">
              Standort / PLZ
              <input name="location" autocomplete="postal-code" placeholder="z. B. 19348 Sükow">
            </label>
```

Replace with:

```html
            <label class="offer-field">
              Strasse und Hausnummer
              <input name="address_street" autocomplete="street-address" maxlength="200" placeholder="z. B. Quitzower Damm 15">
            </label>
            <div class="form-row">
              <label>
                PLZ
                <input name="address_postal" autocomplete="postal-code" maxlength="20" inputmode="numeric" placeholder="z. B. 19348">
              </label>
              <label>
                Ort
                <input name="address_city" autocomplete="address-level2" maxlength="100" placeholder="z. B. Sükow">
              </label>
            </div>
```

- [ ] **Step 3: Manual verification**

In the browser, advance to Step 2:
- Confirm the two new radio cards are visible and selectable, with their icons rendered.
- Confirm "Strasse und Hausnummer" appears on its own row and "PLZ" / "Ort" appear side-by-side via the existing `.form-row` styling (same look as the Step 3 Dachform/Nutzung row).
- Submit the whole form end-to-end with `address_*` filled in and confirm the backend stores them (covered by Task 3 tests, but spot-check a real submission if a running backend is available).

- [ ] **Step 4: Commit**

```bash
git add angebot/index.html
git commit -m "feat(angebot): add Freiland/Carport options and split address into 3 fields"
```

---

## Task 7: Frontend Step 3 — Verbrauch select with presets

**Files:**
- Modify: `angebot/index.html:182-186`

- [ ] **Step 1: Replace the consumption text input with a select**

In `angebot/index.html`, find the Step 3 consumption block:

```html
              <label>
                Jahresverbrauch, falls bekannt
                <input name="consumption" inputmode="numeric" placeholder="z. B. 4.500 kWh">
              </label>
```

Replace with:

```html
              <label>
                Jahresverbrauch, falls bekannt
                <select name="consumption">
                  <option value="">Bitte auswählen</option>
                  <option>1.500 kWh (1–2 Personen)</option>
                  <option>2.500 kWh (2–3 Personen)</option>
                  <option>3.500 kWh (3–4 Personen)</option>
                  <option>4.500 kWh (4–5 Personen)</option>
                  <option>Andere / unbekannt</option>
                </select>
              </label>
```

- [ ] **Step 2: Manual verification**

In the browser on Step 3:
- Confirm the consumption field is now a dropdown with the five preset options plus "Andere / unbekannt".
- Pick one, submit, and confirm the chosen label string is what arrives in the operator email (verifiable against the Task 3 happy-path test, or via a manual submission).

- [ ] **Step 3: Commit**

```bash
git add angebot/index.html
git commit -m "feat(angebot): replace Jahresverbrauch input with preset select"
```

---

## Task 8: Frontend Step 4 — upload helper text

**Files:**
- Modify: `angebot/index.html:213-214`

- [ ] **Step 1: Update the file-upload label**

In `angebot/index.html`, find the Step 4 file-upload `<label>`:

```html
            <label class="offer-field">
              Fotos, Stromrechnung oder vorhandene Angebote (optional)
              <input
                type="file"
                name="files"
```

Change the label text to:

```html
            <label class="offer-field">
              Fotos vom Dach / Gebäude, Zählerschrank, Stromrechnung oder vorhandene Angebote (optional)
              <input
                type="file"
                name="files"
```

The remaining `<small>` and JS file-handling are unchanged.

- [ ] **Step 2: Manual verification**

In the browser on Step 4, confirm the updated helper text is visible above the file input.

- [ ] **Step 3: Commit**

```bash
git add angebot/index.html
git commit -m "feat(angebot): broaden upload helper text on Step 4"
```

---

## Task 9: Header logo — both pages + CSS

**Files:**
- Modify: `index.html:19`, `angebot/index.html:19`, `styles.css`

Precondition: `assets/logo.png` exists (already in place from the user).

- [ ] **Step 1: Replace the brand-mark on the main page**

In `index.html`, find line 19:

```html
        <span class="brand-mark">TI</span>
```

Replace with:

```html
        <img class="brand-mark brand-logo" src="assets/logo.png" alt="" width="44" height="44">
```

- [ ] **Step 2: Replace the brand-mark on the angebot page**

In `angebot/index.html`, find line 19:

```html
        <span class="brand-mark">TI</span>
```

Replace with:

```html
        <img class="brand-mark brand-logo" src="../assets/logo.png" alt="" width="44" height="44">
```

- [ ] **Step 3: Add the brand-logo CSS rule**

In `styles.css`, immediately after the existing `.brand-mark { ... }` rule (currently lines 89–98), append:

```css
.brand-mark.brand-logo {
  border: 0;
  background: transparent;
  padding: 0;
  object-fit: contain;
}
```

This neutralizes the bordered tile look used by the text mark while preserving the 44×44 footprint.

- [ ] **Step 4: Manual verification**

In the browser:
- Load `index.html` — the logo appears in the header where "TI" used to be. The adjacent "Technik & Instandsetzung GmbH" text is still readable in both the transparent (top of page) and scrolled-solid header states. Resize the viewport down to mobile widths to confirm the logo still fits.
- Load `angebot/index.html` — same check; the header on this page always uses the solid `is-scrolled` state.
- Open dev tools, confirm there's no 404 for `assets/logo.png` (or `../assets/logo.png` from the angebot page).

If the logo looks distorted at 44×44, do not adjust the dimensions here — instead leave the size as spec'd and report back so the user can decide whether to provide a square crop or different sizing.

- [ ] **Step 5: Commit**

```bash
git add index.html angebot/index.html styles.css
git commit -m "feat(brand): replace TI text mark with logo image in header"
```

---

## Final verification

- [ ] **Run the full backend test suite**

From `backend/`:
```bash
vendor/bin/phpunit
```

Expected: all tests green.

- [ ] **Smoke-test the wizard end-to-end**

In a browser against a running backend (if available), submit a complete Angebot covering:
- Multiple components including Smart Home and Wallbox / EV-Ladestation
- Building = "Carport / Garage" (or "Freiland")
- Filled-in `address_street`, `address_postal`, `address_city`
- A picked Verbrauch preset
- An optional photo upload

Confirm: success message in the UI; operator email shows the new `Adresse:` block and the chosen Verbrauch label; admin detail view shows the Adresse row.

- [ ] **Done — branch is ready for review**

No additional commit required if everything was committed task-by-task as above.
