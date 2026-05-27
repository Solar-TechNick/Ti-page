# Angebot form and logo updates

Date: 2026-05-27
Scope: small frontend revisions across the offer wizard, header branding on both pages, and the matching backend/DB/admin/email updates.

## Goals

- Show the new company logo in the site header.
- Expand the offer wizard to cover smart home, additional building types, structured address capture, household-based consumption presets, and clearer upload guidance.

## Files affected

Frontend:
- `index.html` — header brand mark.
- `angebot/index.html` — header brand mark and Steps 1, 2, 3, 4 markup.
- `styles.css` — small rule(s) for the image-based brand mark.

Backend:
- `backend/sql/schema.sql` — idempotent column additions on `angebot_requests`.
- `backend/src/validate.php` — validate new address fields.
- `backend/public/api/angebot.php` — persist new address fields; update operator/visitor email body.
- `backend/public/admin/detail.php` — replace single `Standort/PLZ` row with an `Adresse` block.

Tests:
- `backend/tests/` — update any existing angebot test that asserts the `location` field, validation, or operator email body to cover the new address fields.

Assets:
- `assets/logo.png` — the user adds this file out of band; this spec assumes its presence.

## 1. Header logo (both pages)

Replace:

```html
<span class="brand-mark">TI</span>
```

with:

```html
<img class="brand-mark brand-logo" src="assets/logo.png" alt="" width="44" height="44">
```

(on `angebot/index.html` the `src` is `../assets/logo.png`).

The `alt` is empty because the adjacent `<strong>Technik & Instandsetzung</strong>` text already names the brand to screen readers, and the parent `<a>` already has an `aria-label`. Keeping the image decorative avoids double-announcement.

CSS in `styles.css`: extend the existing `.brand-mark` rule with a `.brand-logo` variant that removes the border, background, and grid-centered text styling, and sets `object-fit: contain` so the logo scales correctly inside the 44×44 box:

```css
.brand-mark.brand-logo {
    border: 0;
    background: transparent;
    padding: 0;
    object-fit: contain;
}
```

## 2. Step 1 — Components

Two changes to `angebot/index.html` inside the first `<fieldset class="offer-step is-active">`:

1. **Wallbox label and value** — change both the display label and the `value` attribute:
   ```html
   <input type="checkbox" name="components" value="Wallbox / EV-Ladestation">
   <span><i data-lucide="plug-zap" aria-hidden="true"></i> Wallbox / EV-Ladestation</span>
   ```
   Rationale: stored value flows verbatim into operator emails and the admin detail view; keeping label and value in sync is what the existing options do.

2. **Smart Home option** — add a new checkbox card after Elektroinstallation:
   ```html
   <label class="option-card">
       <input type="checkbox" name="components" value="Smart Home">
       <span><i data-lucide="house-plug" aria-hidden="true"></i> Smart Home</span>
   </label>
   ```

## 3. Step 2 — Building type and Address

### Building radios

Add two new `<label class="option-card">` entries with radio inputs in the same `.option-grid`, after "Industrie / Anlage":

- **Freiland** — value `Freiland`, icon `trees`.
- **Carport / Garage** — value `Carport / Garage`, icon `car`.

### Address split

Replace the single `location` input:

```html
<label class="offer-field">
    Standort / PLZ
    <input name="location" autocomplete="postal-code" placeholder="z. B. 19348 Sükow">
</label>
```

with three inputs — street on its own row, PLZ and Ort side-by-side using the existing `.form-row` helper:

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

All three remain optional (no `required`).

## 4. Step 3 — Verbrauch preset

Replace the existing `consumption` text input:

```html
<label>
    Jahresverbrauch, falls bekannt
    <input name="consumption" inputmode="numeric" placeholder="z. B. 4.500 kWh">
</label>
```

with a `<select>` mirroring the pattern used by Dachform/Nutzung/Zeitraum:

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

The chosen option label is what gets POSTed and stored in `angebot_requests.consumption` (existing column is `VARCHAR(100)` — all options fit). No JS or validation changes needed.

## 5. Step 4 — Upload helper text

Update the file-input label text inside Step 4:

- Old: `Fotos, Stromrechnung oder vorhandene Angebote (optional)`
- New: `Fotos vom Dach / Gebäude, Zählerschrank, Stromrechnung oder vorhandene Angebote (optional)`

The `<small>` helper line ("JPG, PNG, HEIC, WEBP oder PDF…") stays as is.

## 6. Backend changes

### Schema migration — `backend/sql/schema.sql`

Append three idempotent `ALTER TABLE` blocks following the existing `voucher_code` pattern (check `information_schema.columns`, conditionally `PREPARE`/`EXECUTE`):

- `address_street VARCHAR(200) NULL` after `location`.
- `address_postal VARCHAR(20) NULL` after `address_street`.
- `address_city VARCHAR(100) NULL` after `address_postal`.

The legacy `location` column stays in the schema and table. New rows leave it `NULL`; no data migration of existing rows. This keeps the schema backwards-compatible if anything else still reads the old column.

### Validation — `backend/src/validate.php`

In `validate_angebot()`:

- Remove `'location' => 200` from the length-check loop.
- Add `'address_street' => 200`, `'address_postal' => 20`, `'address_city' => 100`.

No required-field check changes (all remain optional).

### API — `backend/public/api/angebot.php`

`INSERT` statement: drop `location`, add `address_street`, `address_postal`, `address_city`. Bind from `$input` with the same `?? null` pattern.

Operator email — replace this line:

```
'  Standort/PLZ: ' . ($in['location']    ?? '-'),
```

with an address block:

```
'  Adresse:      ' . ($in['address_street'] ?? '-'),
'                ' . trim(($in['address_postal'] ?? '') . ' ' . ($in['address_city'] ?? '')),
```

When both PLZ and Ort are empty, the second line will be just whitespace from the indent — collapse it to `-` if the trim result is empty:

```php
$cityLine = trim(($in['address_postal'] ?? '') . ' ' . ($in['address_city'] ?? ''));
$cityLine = $cityLine === '' ? '-' : $cityLine;
```

Then include `'                ' . $cityLine` as the second line.

Visitor autoreply (`_angebot_autoreply_visitor`) — replace `'Standort:    '` line with `'Adresse:     '` showing the same trimmed combined address (single line: `"$street, $postal $city"` with empty components dropped).

### Admin detail view — `backend/public/admin/detail.php`

Replace:

```php
<dt>Standort/PLZ</dt><dd><?= htmlspecialchars($row['location'] ?? '—') ?></dd>
```

with:

```php
<dt>Adresse</dt>
<dd>
    <?php
        $parts = array_filter([
            $row['address_street'] ?? null,
            trim(($row['address_postal'] ?? '') . ' ' . ($row['address_city'] ?? '')),
        ], fn($p) => $p !== null && $p !== '');
        echo $parts ? htmlspecialchars(implode(', ', $parts)) : '—';
    ?>
</dd>
```

(`address_street` on its own logical line, `PLZ Ort` joined by a comma if street is also present — matches the email layout in spirit while staying compact in a `<dl>`.)

## 7. Tests

Skim `backend/tests/` for fixtures that POST `location` or assert the operator email body / admin detail / validation. Update them to:
- POST `address_street`, `address_postal`, `address_city` instead of `location`.
- Assert the new email format ("Adresse:" lines).
- Cover the three new validation length limits.

No new test files are required; add to existing files if they exist for these areas. If they don't, this spec does not mandate creating new ones.

## 8. Out of scope

- Migration of existing `location` data into the new three columns.
- Removing the `location` column from the schema.
- Required-field enforcement on the new address fields.
- Admin list view (`index.php`) — `Standort` is not shown there today.
- Frontend JS step-validation logic — current behavior (no validation on Step 2 inputs) is preserved.
- Tests for the frontend wizard changes; existing tests are PHP only.

## 9. Risks / notes

- The `house-plug` lucide icon must exist in the bundled `lucide@1.14.0` build. If it does not render at runtime, fall back to `smartphone` (a single one-character edit). Surface this as a manual visual check during implementation.
- Browsers will render `<select>`s differently from text inputs on Step 3; verify `styles.css`'s `.form-row select` rules already cover the new field (they should — Dachform and Nutzung already use the same wrapper).
- The brand `<img>` swap removes the bordered tile look; visually inspect both pages in light and dark scroll states.
