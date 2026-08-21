# Technical Design: Hero Style D & Legal Law Firm Vertical Integration

**Change ID**: `hero-style-d`
**Component**: `[PWP]` (Panel SaaS - Frontend) + `[API]` (Backend)
**Date**: 2026-08-20

---

## 1. Architectural Overview

Hero Style D integrates legal/professional firm patterns (inspired by Goldstein Mehta LLC on Squarespace and Kaleidoscope video portfolios) into the Orkestone / VerticalBlockBase (VBB) architecture.

```
[Command Center (Admin UI)]
         │
         ▼ (JSON Config / Vertical Schema)
[Block Registry (`block-registry.php`)]
         │
         ▼ (Sanitization & Defaults)
[Block Bakers (`block-baker.php`)]
         │
         ▼ (HTML Output + CSS Variables)
[Frontend Renderer (`pro-frontend.css` / Theme Templates)]
```

---

## 2. Component Structure & Block Definitions

We will add 6 new block types to `vbb_get_block_registry()` in `block-registry.php`:

1. **`heroStyleD`**: Full-bleed hero with background media, overlay, highlighted heading, dual CTAs.
2. **`trustBadgesStrip`**: Logo strip for media mentions (NBC, Inquirer, AP, Reuters).
3. **`practiceGrid`**: 12-item poster grid with circular clip-path masks and overlay action buttons.
4. **`contactFormMap`**: Dual-column section with contact form on right, photo/map on left, plus BBB badge support.
5. **`headerConfig`**: Header settings (transparent style, nav folders, mobile menu, CTA button).
6. **`colorPaletteDark`**: Custom theme tokens for dark premium legal aesthetic (Gold `#D4A843`, Black `#000000`, White `#FFFFFF`).

---

## 3. Data Flow & JSON Schema

Vertical JSON (`tkm-soluciones-5.json` or similar vertical) will structure the payload:

```json
{
  "vertical_slug": "legal-criminal-defense",
  "theme": "dark-legal",
  "header": {
    "logoUrl": "...",
    "navItems": [...],
    "ctaText": "Contact Us",
    "ctaUrl": "/contact"
  },
  "blocks": {
    "heroStyleD": {
      "enabled": true,
      "style": "A",
      "heading": "Fight Back Against Unfair Criminal Charges",
      "highlightText": "Fight Back",
      "subhead": "Award-winning Philadelphia criminal defense lawyers...",
      "primaryCtaText": "Call Now",
      "primaryCtaUrl": "tel:+12672252545",
      "secondaryCtaText": "Get Help Now",
      "secondaryCtaUrl": "tel:+12672252545",
      "bgImage": "...",
      "bgVideo": ""
    },
    "trustBadgesStrip": {
      "enabled": true,
      "badges": [
        {"name": "NBC 10", "image": "..."},
        {"name": "Philadelphia Inquirer", "image": "..."}
      ]
    },
    "practiceGrid": {
      "enabled": true,
      "items": [
        {"title": "Violent Crimes", "image": "...", "url": "/violent-crimes"},
        {"title": "DUI Defense", "image": "...", "url": "/dui-defense"}
      ]
    }
  }
}
```

---

## 4. CSS Architecture (`pro-frontend.css`)

New classes and variables to add:
- `.hero-style-d`: full-bleed flex container, absolute background video/image, gradient overlay.
- `.sqs-image-shape-container-element`: support for circular clip-path masks (`clip-path: circle(50% at 50% 50%)`).
- `.practice-poster-grid`: CSS grid responsive (4 columns desktop, 2 tablet, 1 mobile).
- Dark theme tokens: `--gold-accent: #D4A843; --dark-bg: #0b0b0b;`.

---

## 5. Verification Plan

1. **JSON Validation**: Validate generated vertical JSON against schema via `ajv` or PHP validator.
2. **PHP Unit / Syntax Check**: Run `php -l` on modified PHP files.
3. **Playwright E2E**: Render page in headless browser, verify hero loads, CTAs are clickable, practice grid displays 4 columns, form submits without JS errors.
