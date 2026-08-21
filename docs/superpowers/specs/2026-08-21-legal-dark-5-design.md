# Design: Legal Dark 5.0 — Dark-Mode Law Firm Vertical

**Date:** 2026-08-21
**Status:** Approved (pending spec review)
**Scope:** Orkestone theme — new vertical JSON + minimal importer/settings enhancement

---

## Goal

Deliver a reusable, dark-by-default law firm vertical (`legal-dark-5`) that bakes a complete demo site in Spanish through the existing Vertical-Driven pipeline, with elegant midnight/gold styling and zero new section bakers.

## Problem

The vertical JSON `brand` block currently feeds only 4 colors into the **light** palette (`pro-settings.php:150-153`). The dark palette is hardcoded in `vbb_pro_default_settings()` (`pro-settings.php:357-365`) and `colorMode` always defaults to `'light'` (`pro-settings.php:269`). Therefore a dark-by-default theme cannot be expressed purely in JSON: every baked instance would require a manual Command Center step, breaking vertical portability.

## Approach (chosen)

Option A: new vertical JSON + ~25-line PHP change so `brand.colorMode` and `brand.palettes` are respected at bake time. Rejected Option B (JSON-only + manual colorMode flip) because each new install would need manual configuration and the dark palette would remain the hardcoded default instead of the vertical's own.

## Design

### 1. PHP change — `inc/pro-settings.php`

In `vbb_pro_default_settings()`:

- Read `$brand['colorMode']` → feeds the `'colorMode'` setting. Valid values `'dark' | 'light' | 'auto'`; default `'light'` (current behavior).
- Read `$brand['palettes']` → `{ "light": {...}, "dark": {...} }`, deep-merged over the default palettes. Each palette accepts the existing 7 keys: `primary`, `secondary`, `accent`, `background`, `surface`, `text`, `mutedText`.
- No changes to `vbb_pro_sanitize_settings()`: the existing per-key `vbb_pro_sanitize_hex` loop (`pro-settings.php:519-520`) already sanitizes both palettes, and the `colorMode` whitelist check (`pro-settings.php:432`) already exists.

Backward compatibility: verticals without these keys produce byte-identical defaults to today.

No validator changes needed — `vertical-validator.php` is lenient about extra `brand` keys.

### 2. New vertical — `config/verticals/legal-dark-5.json`

Structure mirrors `legales-5.json`. Demo content in Spanish (fictional firm).

- **Pages:**
  - `home` (`inicio`, template `front-page`)
  - `services` (`servicios`, template `page`)
  - `about` (`nosotros`, template `page`)
  - `contact` (`contacto`, template `page`)
- **Home sections:** `hero` → `logo_cloud` → `problem` → `benefits` → `process` → `stats` → `testimonials` → `faq` → `cta-final`. All use existing bakers.
- **Other pages:** `hero-centered` openers; services page uses `contentModels.service`; about uses `benefits` + `process`; contact uses `contact-section`.
- **Navigation:** primary menu with the four page slugs.

### 3. Visual identity

Dark mode is the baked default; light palette exists for the front-end toggle.

| Key | Dark (default) | Light |
|---|---|---|
| background | `#0B1220` | `#FFFFFF` |
| surface | `#121C2E` | `#F5F2EA` |
| text | `#EAEFF7` | `#141E30` |
| mutedText | `#9AA7BC` | `#667085` |
| primary | `#E8D9A8` | `#0F1B2D` |
| secondary | `#C9A227` | `#C9A227` |
| accent | `#16233A` | `#F4F1EC` |

Rationale: midnight navy conveys authority/trust; gold signals prestige (classic legal branding); ivory text keeps contrast AA on dark surfaces.

Typography: headings `Georgia` (serif), body `Inter` — same pattern as `legales-5.json`, no new webfont loading.

### 4. Verification

1. `php -l` on touched PHP file.
2. New JSON passes `vbb_validate_vertical_config`.
3. Unit test in `inc/test-orkestone-engine.php` (standalone harness with WP function stubs, no PHPUnit): given `brand.colorMode: 'dark'` + custom palettes, defaults resolve dark; without those keys, output identical to previous defaults.
4. Bake smoke via existing `verification/` flow.

## Error handling

- Invalid `colorMode` values fall back to `'light'` via the existing whitelist.
- Invalid hex values in `palettes` fall back to palette defaults via `vbb_pro_sanitize_hex`.

## Out of scope

- New section bakers or layout variants.
- Google Fonts loading infrastructure.
- Changes to Command Center UI (colorMode is already editable there).
