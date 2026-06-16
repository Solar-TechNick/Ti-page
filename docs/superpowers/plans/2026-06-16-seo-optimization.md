# SEO Optimization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add technical SEO (structured data, sitemap, robots, Open Graph, canonical, favicon) and lightly keyword-optimize the H1 and two headings on a static HTML site, without changing the visual design.

**Architecture:** Pure static-file edits. Two new root files (`robots.txt`, `sitemap.xml`), `<head>` additions to `index.html` and `angebot/index.html`, two JSON-LD `<script>` blocks at the end of `index.html`'s body, and four in-place text edits. No build step, no backend changes. "Tests" are validation commands (XML well-formedness, JSON parse, grep presence checks) since this is a static site with no test framework.

**Tech Stack:** Static HTML5, schema.org JSON-LD, Open Graph protocol, `python3` (for JSON/XML validation only).

**Conventions to match:** The codebase uses raw `&` (not `&amp;`) in text/titles, 2-space indentation, and absolute URLs under `https://www.technik-prignitz.de`. The share image is `assets/DSC01106.jpeg`; the icon is `assets/logo.png`; the `theme-color` is the brand `--green-dark: #084a3c`.

---

### Task 1: Create `robots.txt`

**Files:**
- Create: `robots.txt`

- [ ] **Step 1: Write the file**

Create `robots.txt` with exactly:

```
User-agent: *
Allow: /

Sitemap: https://www.technik-prignitz.de/sitemap.xml
```

- [ ] **Step 2: Verify contents**

Run: `cat robots.txt`
Expected: the three logical lines above, with the `Sitemap:` line present.

- [ ] **Step 3: Commit**

```bash
git add robots.txt
git commit -m "feat(seo): add robots.txt pointing to sitemap"
```

---

### Task 2: Create `sitemap.xml`

**Files:**
- Create: `sitemap.xml`

Only the two indexable pages are listed. `impressum/` and `datenschutzerklaerung/` are intentionally excluded — they already carry `<meta name="robots" content="noindex">`.

- [ ] **Step 1: Write the file**

Create `sitemap.xml` with exactly:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://www.technik-prignitz.de/</loc>
    <changefreq>monthly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://www.technik-prignitz.de/angebot/</loc>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
</urlset>
```

- [ ] **Step 2: Verify it is well-formed XML**

Run: `python3 -c "import xml.dom.minidom,sys; xml.dom.minidom.parse('sitemap.xml'); print('valid xml')"`
Expected: `valid xml` (no traceback).

- [ ] **Step 3: Commit**

```bash
git add sitemap.xml
git commit -m "feat(seo): add sitemap.xml for the two indexable pages"
```

---

### Task 3: Add canonical, favicon, theme-color, and social tags to `index.html`

**Files:**
- Modify: `index.html` (`<head>`, after the `meta description` block)

- [ ] **Step 1: Insert the head block**

In `index.html`, find this existing block (lines ~7-11):

```html
    <meta
      name="description"
      content="Technik- & Instandsetzungs GmbH aus Sükow: Elektrotechnik, Photovoltaik, Speicher, Wärmepumpen, Schaltschrankbau, Metallbau und Smarthome."
    >
    <link rel="preconnect" href="https://images.unsplash.com">
```

Insert the following BETWEEN the closing `>` of the description meta and the `preconnect` link, so the result is:

```html
    <meta
      name="description"
      content="Technik- & Instandsetzungs GmbH aus Sükow: Elektrotechnik, Photovoltaik, Speicher, Wärmepumpen, Schaltschrankbau, Metallbau und Smarthome."
    >
    <link rel="canonical" href="https://www.technik-prignitz.de/">
    <link rel="icon" href="assets/logo.png">
    <link rel="apple-touch-icon" href="assets/logo.png">
    <meta name="theme-color" content="#084a3c">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Technik- & Instandsetzungs GmbH">
    <meta property="og:locale" content="de_DE">
    <meta property="og:title" content="Technik- & Instandsetzungs GmbH | Elektrotechnik in der Prignitz">
    <meta property="og:description" content="Technik- & Instandsetzungs GmbH aus Sükow: Elektrotechnik, Photovoltaik, Speicher, Wärmepumpen, Schaltschrankbau, Metallbau und Smarthome.">
    <meta property="og:url" content="https://www.technik-prignitz.de/">
    <meta property="og:image" content="https://www.technik-prignitz.de/assets/DSC01106.jpeg">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Technik- & Instandsetzungs GmbH | Elektrotechnik in der Prignitz">
    <meta name="twitter:description" content="Technik- & Instandsetzungs GmbH aus Sükow: Elektrotechnik, Photovoltaik, Speicher, Wärmepumpen, Schaltschrankbau, Metallbau und Smarthome.">
    <meta name="twitter:image" content="https://www.technik-prignitz.de/assets/DSC01106.jpeg">
    <link rel="preconnect" href="https://images.unsplash.com">
```

- [ ] **Step 2: Verify the tags are present**

Run: `grep -c -E "og:|twitter:|rel=\"canonical\"|theme-color" index.html`
Expected: `13` (12 og/twitter tags + 1 canonical; grep counts lines — confirm it is at least 12).

Run: `grep -o 'rel="canonical" href="[^"]*"' index.html`
Expected: `rel="canonical" href="https://www.technik-prignitz.de/"`

- [ ] **Step 3: Commit**

```bash
git add index.html
git commit -m "feat(seo): add canonical, favicon, theme-color and social tags to index"
```

---

### Task 4: Add JSON-LD structured data to `index.html`

**Files:**
- Modify: `index.html` (just before the closing `</body>` tag)

- [ ] **Step 1: Insert two JSON-LD blocks**

In `index.html`, find the closing of the footer and body (lines ~407-408):

```html
    </footer>
  </body>
```

Insert the two `<script>` blocks BETWEEN `</footer>` and `</body>`:

```html
    </footer>

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Electrician",
      "name": "Technik- und Instandsetzungs GmbH",
      "image": "https://www.technik-prignitz.de/assets/logo.png",
      "logo": "https://www.technik-prignitz.de/assets/logo.png",
      "url": "https://www.technik-prignitz.de/",
      "telephone": "+49 3876 612474",
      "faxNumber": "+49 3876 612838",
      "email": "info@technik-prignitz.de",
      "vatID": "DE138975594",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Quitzower Damm 15",
        "postalCode": "19348",
        "addressLocality": "Sükow",
        "addressRegion": "Brandenburg",
        "addressCountry": "DE"
      },
      "areaServed": {
        "@type": "AdministrativeArea",
        "name": "Prignitz"
      },
      "knowsAbout": [
        "Elektroinstallation",
        "Steuerungs- und Schaltschrankbau",
        "Photovoltaik und Speicher",
        "Wärmepumpen",
        "Metallbau",
        "Smarthome"
      ]
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Welche Dienstleistungen bieten Sie an?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Elektroinstallation, Steuerungs- und Schaltschrankbau, Photovoltaik- und Speichersysteme, Wärmepumpen, Metallbau, Smarthome sowie Wartung und Instandsetzung."
          }
        },
        {
          "@type": "Question",
          "name": "Arbeiten Sie für Privatkunden und Unternehmen?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Ja. Wir betreuen private Haushalte, Gewerbebetriebe und industrielle Anlagen."
          }
        },
        {
          "@type": "Question",
          "name": "Wie läuft eine Anfrage ab?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Nach Ihrer Nachricht melden wir uns zur Klärung der Eckdaten, prüfen den Bedarf vor Ort und erstellen auf dieser Basis eine passende Lösung."
          }
        },
        {
          "@type": "Question",
          "name": "Bieten Sie Wartung an?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Ja. Wir warten und setzen bestehende Technik instand, damit Anlagen länger zuverlässig laufen und Ausfallzeiten reduziert werden."
          }
        }
      ]
    }
    </script>
  </body>
```

- [ ] **Step 2: Verify both JSON-LD blocks parse as valid JSON**

Run:
```bash
python3 - <<'EOF'
import re, json
html = open('index.html', encoding='utf-8').read()
blocks = re.findall(r'<script type="application/ld\+json">(.*?)</script>', html, re.S)
assert len(blocks) == 2, f"expected 2 JSON-LD blocks, found {len(blocks)}"
types = [json.loads(b)["@type"] for b in blocks]
print("valid:", types)
EOF
```
Expected: `valid: ['Electrician', 'FAQPage']`

- [ ] **Step 3: Commit**

```bash
git add index.html
git commit -m "feat(seo): add LocalBusiness and FAQPage structured data to index"
```

---

### Task 5: Keyword-optimize the H1 and two headings in `index.html`

**Files:**
- Modify: `index.html` (hero eyebrow + H1; Leistungen H2; Unternehmen H2)

Visual layout is unchanged — only the text inside existing elements changes.

- [ ] **Step 1: Update the hero eyebrow (line ~54)**

Find:
```html
          <p class="eyebrow">Elektrotechnik aus Sükow für Prignitz und Umgebung</p>
```
Replace with:
```html
          <p class="eyebrow">Technik- & Instandsetzungs GmbH · Sükow</p>
```

- [ ] **Step 2: Update the H1 (line ~55)**

Find:
```html
          <h1 id="hero-title">Technik- & Instandsetzungs GmbH</h1>
```
Replace with:
```html
          <h1 id="hero-title">Elektrotechnik & Photovoltaik in der Prignitz</h1>
```

- [ ] **Step 3: Update the Leistungen H2 (line ~111)**

Find:
```html
          <h2 id="leistungen-title">Technik, die im Alltag zuverlässig arbeitet.</h2>
```
Replace with:
```html
          <h2 id="leistungen-title">Elektrotechnik, Photovoltaik & Wärmepumpen aus einer Hand</h2>
```

- [ ] **Step 4: Update the Unternehmen H2 (line ~180)**

Find:
```html
            <h2 id="unternehmen-title">Regional, erfahren, lösungsorientiert.</h2>
```
Replace with:
```html
            <h2 id="unternehmen-title">Ihr Elektrobetrieb in Sükow und der Prignitz</h2>
```

- [ ] **Step 5: Verify the new headings are present and the old ones are gone**

Run:
```bash
grep -c "Elektrotechnik & Photovoltaik in der Prignitz" index.html && \
grep -c "Ihr Elektrobetrieb in Sükow und der Prignitz" index.html && \
! grep -q "Regional, erfahren, lösungsorientiert" index.html && echo "old headings removed"
```
Expected: `1`, then `1`, then `old headings removed`.

- [ ] **Step 6: Commit**

```bash
git add index.html
git commit -m "feat(seo): keyword-optimize H1 and section headings"
```

---

### Task 6: Add canonical, favicon, theme-color, and social tags to `angebot/index.html`

**Files:**
- Modify: `angebot/index.html` (`<head>`, after the `meta description` block)

Note the relative icon paths use `../assets/` because this page is one directory deep.

- [ ] **Step 1: Insert the head block**

In `angebot/index.html`, find this existing block:

```html
    <meta
      name="description"
      content="Kostenlose Angebotsanfrage für Photovoltaik, Speicher, Wärmepumpe, Wallbox und Elektrotechnik bei Technik- & Instandsetzungs GmbH."
    >
    <link rel="preconnect" href="https://images.unsplash.com">
```

Insert BETWEEN the description meta and the `preconnect` link so the result is:

```html
    <meta
      name="description"
      content="Kostenlose Angebotsanfrage für Photovoltaik, Speicher, Wärmepumpe, Wallbox und Elektrotechnik bei Technik- & Instandsetzungs GmbH."
    >
    <link rel="canonical" href="https://www.technik-prignitz.de/angebot/">
    <link rel="icon" href="../assets/logo.png">
    <link rel="apple-touch-icon" href="../assets/logo.png">
    <meta name="theme-color" content="#084a3c">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Technik- & Instandsetzungs GmbH">
    <meta property="og:locale" content="de_DE">
    <meta property="og:title" content="Angebot anfragen | Technik- & Instandsetzungs GmbH">
    <meta property="og:description" content="Kostenlose Angebotsanfrage für Photovoltaik, Speicher, Wärmepumpe, Wallbox und Elektrotechnik bei Technik- & Instandsetzungs GmbH.">
    <meta property="og:url" content="https://www.technik-prignitz.de/angebot/">
    <meta property="og:image" content="https://www.technik-prignitz.de/assets/DSC01106.jpeg">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Angebot anfragen | Technik- & Instandsetzungs GmbH">
    <meta name="twitter:description" content="Kostenlose Angebotsanfrage für Photovoltaik, Speicher, Wärmepumpe, Wallbox und Elektrotechnik bei Technik- & Instandsetzungs GmbH.">
    <meta name="twitter:image" content="https://www.technik-prignitz.de/assets/DSC01106.jpeg">
    <link rel="preconnect" href="https://images.unsplash.com">
```

- [ ] **Step 2: Verify the tags are present**

Run: `grep -o 'rel="canonical" href="[^"]*"' angebot/index.html`
Expected: `rel="canonical" href="https://www.technik-prignitz.de/angebot/"`

Run: `grep -c -E "og:|twitter:" angebot/index.html`
Expected: at least `12`.

- [ ] **Step 3: Commit**

```bash
git add angebot/index.html
git commit -m "feat(seo): add canonical, favicon, theme-color and social tags to angebot"
```

---

### Task 7: Add favicon and theme-color to the legal pages

**Files:**
- Modify: `impressum/index.html` (`<head>`)
- Modify: `datenschutzerklaerung/index.html` (`<head>`)

These stay `noindex` (no canonical/OG needed), but the favicon and theme-color should be consistent across the whole site.

- [ ] **Step 1: Update `impressum/index.html`**

Find:
```html
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="../styles.css">
```
Replace with:
```html
    <meta name="robots" content="noindex">
    <link rel="icon" href="../assets/logo.png">
    <link rel="apple-touch-icon" href="../assets/logo.png">
    <meta name="theme-color" content="#084a3c">
    <link rel="stylesheet" href="../styles.css">
```

- [ ] **Step 2: Update `datenschutzerklaerung/index.html`**

Find:
```html
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="../styles.css">
```
Replace with:
```html
    <meta name="robots" content="noindex">
    <link rel="icon" href="../assets/logo.png">
    <link rel="apple-touch-icon" href="../assets/logo.png">
    <meta name="theme-color" content="#084a3c">
    <link rel="stylesheet" href="../styles.css">
```

- [ ] **Step 3: Verify**

Run: `grep -c 'rel="icon"' impressum/index.html datenschutzerklaerung/index.html`
Expected: each file reports `1`.

- [ ] **Step 4: Commit**

```bash
git add impressum/index.html datenschutzerklaerung/index.html
git commit -m "feat(seo): add favicon and theme-color to legal pages"
```

---

### Task 8: Final validation

**Files:** none (verification only)

- [ ] **Step 1: Confirm all SEO files and tags exist**

Run:
```bash
test -f robots.txt && test -f sitemap.xml && \
grep -q 'application/ld+json' index.html && \
grep -q 'rel="canonical"' index.html && \
grep -q 'rel="canonical"' angebot/index.html && \
echo "ALL SEO ARTIFACTS PRESENT"
```
Expected: `ALL SEO ARTIFACTS PRESENT`

- [ ] **Step 2: Re-validate both JSON-LD blocks parse**

Run:
```bash
python3 - <<'EOF'
import re, json
html = open('index.html', encoding='utf-8').read()
for b in re.findall(r'<script type="application/ld\+json">(.*?)</script>', html, re.S):
    json.loads(b)
print("JSON-LD OK")
EOF
```
Expected: `JSON-LD OK`

- [ ] **Step 3: Manual external checks (record results, no code)**

After deploy, the engineer should confirm (these need the live URL and are documented for completeness):
- Google Rich Results Test on `https://www.technik-prignitz.de/` detects **LocalBusiness** and **FAQPage** with no errors.
- `https://www.technik-prignitz.de/robots.txt` and `/sitemap.xml` load.
- A link-preview debugger (e.g. opengraph.xyz) shows the title, description, and `DSC01106.jpeg` image for both `/` and `/angebot/`.

- [ ] **Step 4: Confirm clean working tree**

Run: `git status --short`
Expected: empty (all changes committed).
