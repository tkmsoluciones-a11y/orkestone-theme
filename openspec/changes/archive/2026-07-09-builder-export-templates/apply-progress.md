# Apply Progress: Builder Export & Template Management — Complete

**Change**: builder-export-templates  
**Stage**: 2 (Section Styles)  
**Status**: completed  
**Mode**: Standard

---

## Completed Tasks (Stage 1 — Export)

- [x] **ET1.1** — `vbb_rest_export_site()` callback + `/export` route registration in `pro-rest-api.php`
- [x] **ET1.2** — "Export Site" button in `.vbb-cc-toolbar` (`pro-admin.php`) + `CC.exportSite` JS handler (`admin-pro.js`)
- [x] **ET1.3** — Extended `import_json` action in `pro-admin.php` to parse `pageOverrides` with deep-merge

## Completed Tasks (Stage 2 — Styles)

- [x] **ET2.1** — Added `'style' => 'A'` default to every block in `vbb_pro_default_settings()`. Added style sanitization (A/B/C only, fallback to 'A') after block colors loop in `vbb_pro_sanitize_settings()`.
- [x] **ET2.2** — Created `vbb_render_cta_button($text, $url, $style)` and `vbb_render_heading_block($text, $level, $align)` shared helpers in `block-baker.php`.
- [x] **ET2.3** — Rewrote `vbb_bake_hero()` with `switch($data['style'])` producing Style A (two-column), Style B (centered with overlay), Style C (full-bleed). Uses `vbb_render_cta_button()`.
- [x] **ET2.4** — Rewrote `vbb_bake_cta_final()` with `switch($data['style'])` producing Style A (centered), Style B (split two-column), Style C (card with subtitle). Uses both shared helpers.
- [x] **ET2.5** — Rewrote `vbb_bake_testimonials()` with `switch($data['style'])` producing Style A (stacked quotes), Style B (grid with avatars/rating), Style C (featured + supporting). Uses `vbb_render_heading_block()`.
- [x] **ET2.6** — Added A/B/C button-group style selector in `renderBlockSettings()` for hero, ctaFinal, and testimonials blocks. Active button gets `vbb-cc-style-btn--active` class. Clicking shows confirmation dialog.
- [x] **ET2.7** — Added auto-rebake confirmation on style change. Per-page change re-bakes that page via `POST /pages/{id}/regenerate`. Global change re-bakes ALL pages via `POST /regenerate-pages`. Cancel reverts by reloading settings from server.

---

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `orkestone-theme/inc/pro-rest-api.php` | Modified | (Stage 1) Added `vbb_rest_export_site()` function and `/export` route registration |
| `orkestone-theme/inc/pro-admin.php` | Modified | (Stage 1) Added "Export Site" button to Command Center toolbar; extended `import_json` handler with `pageOverrides` processing |
| `orkestone-theme/inc/pro-settings.php` | Modified | (Stage 2) Added `'style' => 'A'` to block defaults in `vbb_pro_default_settings()`; added style validation loop in `vbb_pro_sanitize_settings()` |
| `orkestone-theme/inc/block-baker.php` | Modified | (Stage 2) Added `vbb_render_cta_button()` and `vbb_render_heading_block()` shared helpers; rewrote `vbb_bake_hero()`, `vbb_bake_cta_final()`, `vbb_bake_testimonials()` with `switch($data['style'])` dispatch |
| `orkestone-theme/assets/js/admin-pro.js` | Modified | (Stage 1) Added `exportBtn` element ref, click event binding, and `CC.exportSite()`; (Stage 2) Added style selector HTML in `renderBlockSettings()`, style button click handler in `bindCardEvents()`, auto-rebake check in `saveSettings()` success path |

---

## Deviations from Design

- **Shared helpers**: The design uses `esc_url()` and `esc_html()` in `vbb_render_cta_button()` and `vbb_render_heading_block()`. Since these helpers receive `{{vbb_*}}` placeholders (not actual URLs/text), `esc_url()` would return empty strings for placeholder URLs. The implementation outputs values raw, matching the existing baker function patterns where placeholders are output unescaped and the `vbb_pro_replace_dynamic_content()` filter handles escaping at render time.
- **Auto-rebake flow**: The design shows confirmation AFTER save. The implementation shows confirmation BEFORE save (update state → confirm → save → regenerate), which is more correct: on cancel, the server still has the old value and reloading properly reverts.

---

## Risks

- **Placeholder compatibility**: The `vbb_render_cta_button()` and `vbb_render_heading_block()` helpers do not escape their text/URL parameters because they contain `{{vbb_*}}` placeholders. This is consistent with the existing baker pattern where `vbb_pro_replace_dynamic_content()` handles escaping at render time.
- **Style selector only for 3 blocks**: The style selector renders for hero, ctaFinal, and testimonials only. Other blocks have a `style` field stored/sanitized but no UI selector nor dispatch in their baker. This is per the design scope.
- **Cancel revert**: On cancel of a style change, the settings are reloaded from the server. If the user had other unsaved changes (from text fields), those will be lost too. This matches the design and existing patterns.
- Legacy export (`admin-post`, schema 0.3.2) is unchanged and still produces the old format. Both schemas coexist.
- Import of legacy files (without `pageOverrides`) still works — the handler checks `isset($data['pageOverrides'])` before attempting to process.
- Page ID mapping across installs remains an accepted limitation (overrides are keyed by WP page ID, which differs between installs).

---

## All Tasks Complete — Ready for Verification
