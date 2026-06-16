# SEO Optimization — Design

**Date:** 2026-06-16
**Site:** Technik- und Instandsetzungs GmbH (static HTML)
**Domain:** https://www.technik-prignitz.de

## Goal

Make the site fully indexable and rich-result-eligible for local search
("Elektriker Prignitz", "Photovoltaik Sükow", etc.) and good-looking when shared
on social/messaging apps — without redesigning the page. Visible layout stays
identical; only the H1 and two section headings change wording.

## Current state

- Static site: `index.html`, `angebot/index.html`, plus `impressum/` and
  `datenschutzerklaerung/` (both already `noindex`).
- Good basics already present: `lang="de"`, per-page `<title>` and
  `meta description`, semantic HTML, ARIA labels, image `alt` text.
- Missing: structured data (JSON-LD), Open Graph / Twitter tags, `robots.txt`,
  `sitemap.xml`, canonical links, favicon.
- The H1 is the bare company name, spending the strongest on-page signal on the
  brand rather than on what the business does + where.

## Scope

### 1. Structured data (JSON-LD) — highest impact

Add to `index.html` (`<head>` or end of `<body>`):

- **`LocalBusiness` / `Electrician` schema** sourced from the Impressum:
  - `name`: "Technik- und Instandsetzungs GmbH"
  - `address` (PostalAddress): Quitzower Damm 15, 19348 Sükow, DE
  - `telephone`: "+49 3876 612474"
  - `faxNumber`: "+49 3876 612838"
  - `email`: "info@technik-prignitz.de"
  - `url`: "https://www.technik-prignitz.de"
  - `image` / `logo`: absolute URL to `assets/logo.png`
  - `areaServed`: "Prignitz"
  - `vatID`: "DE138975594"
  - `knowsAbout` / service list: Elektroinstallation, Schaltschrankbau,
    Photovoltaik & Speicher, Wärmepumpen, Metallbau, Smarthome
- **`FAQPage` schema** built from the existing four FAQ Q&As verbatim.

Precise GPS coordinates are intentionally omitted — Google geocodes from the
postal address, and wrong coordinates hurt more than help.

### 2. Social sharing (Open Graph + Twitter Card)

Add to `index.html` and `angebot/index.html`:

- `og:type=website`, `og:site_name`, `og:locale=de_DE`, `og:title`,
  `og:description`, `og:url` (absolute, per-page), `og:image` (absolute URL to
  `assets/DSC01106.jpeg`).
- Twitter: `twitter:card=summary_large_image` plus matching title/description/image.

Titles/descriptions reuse each page's existing `<title>` and `meta description`
intent.

### 3. Crawl & indexing files (new, at repo root)

- **`robots.txt`**: allow all crawlers; `Sitemap: https://www.technik-prignitz.de/sitemap.xml`.
- **`sitemap.xml`**: lists the two indexable URLs —
  `https://www.technik-prignitz.de/` and
  `https://www.technik-prignitz.de/angebot/`.
  Impressum & Datenschutz excluded (already `noindex`).

### 4. Canonical + favicon

- `<link rel="canonical">` on `index.html`
  (`https://www.technik-prignitz.de/`) and `angebot/index.html`
  (`https://www.technik-prignitz.de/angebot/`).
- `<link rel="icon">` + `apple-touch-icon` pointing at `assets/logo.png`, and a
  `<meta name="theme-color">` on all four HTML pages (or at minimum index +
  angebot).

### 5. Copy changes (visible text)

Hero (`index.html`):

| Element | From | To |
|---|---|---|
| Eyebrow | Elektrotechnik aus Sükow für Prignitz und Umgebung | Technik- & Instandsetzungs GmbH · Sükow |
| H1 | Technik- & Instandsetzungs GmbH | Elektrotechnik & Photovoltaik in der Prignitz |

Section headings (`index.html`):

| Section | From | To |
|---|---|---|
| Leistungen | Technik, die im Alltag zuverlässig arbeitet. | Elektrotechnik, Photovoltaik & Wärmepumpen aus einer Hand |
| Unternehmen | Regional, erfahren, lösungsorientiert. | Ihr Elektrobetrieb in Sükow und der Prignitz |

Other headings (Arbeitsweise, Kundenstimmen, FAQ, Kontakt) stay as-is.

## Out of scope

- Body-paragraph rewrites / keyword stuffing.
- Per-service or per-town landing pages.
- Front-end performance work (e.g. the remote Unsplash hero image).

## Verification

- JSON-LD validates in Google's Rich Results Test (LocalBusiness + FAQPage
  detected, no errors).
- `robots.txt` and `sitemap.xml` reachable at root; sitemap is valid XML.
- OG/Twitter tags render correctly in a link-preview debugger.
- Pages still render identically apart from the four copy changes; no broken
  markup.
