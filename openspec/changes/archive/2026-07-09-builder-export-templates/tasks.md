# Tasks: Builder Export & Template Management

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 660 (Stage 1: ~220, Stage 2: ~440) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Stage 1: Export) → PR 2 (Stage 2: Styles) |
| Delivery strategy | force-chained |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Stage 1: Export System | PR 1 | Base = feature/builder-export-templates. REST export endpoint, Export button + JS download, extended import handler. |
| 2 | Stage 2: Section Styles | PR 2 | Base = PR 1 branch. Style field, baker dispatch, shared helpers, style selector UI, auto-rebake. |

---

## Stage 1: Export System

- [x] **ET1.1** — Add `vbb_rest_export_site()` callback + `/export` route registration in `pro-rest-api.php`. Returns export envelope (exportedAt, schemaVersion, theme, customized, settings, pageOverrides filtered to published pages, activeProfile). Verify: `GET /orkestone/v1/export` returns 200 with valid JSON.
- [x] **ET1.2** — Add "Export Site" button to `.vbb-cc-toolbar` in `pro-admin.php` and JS handler `CC.exportSite` in `admin-pro.js`. Triggers client-side JSON blob download with filename `orkestone-export-{timestamp}.json`. Verify: click triggers file download with correct format.
- [x] **ET1.3** — Extend `import_json` action in `pro-admin.php` to parse `pageOverrides` from schema >= 1.0.0 export documents. Deep-merge per-page overrides with existing settings via `vbb_pro_deep_merge()`. Verify: import of new format restores global settings + page overrides; legacy 0.3.2 import still works.

## Stage 2: Section Styles

- [x] **ET2.1** — Add `'style' => 'A'` default to every block in `vbb_pro_default_settings()` (`pro-settings.php`). Add style sanitization (A/B/C only, fallback to 'A') after block colors loop in `vbb_pro_sanitize_settings()`. Verify: fresh settings have `style:'A'` on every block; invalid values sanitized to 'A'.
- [x] **ET2.2** — Create `vbb_render_cta_button($text, $url, $style)` and `vbb_render_heading_block($text, $level, $align)` shared helpers in `block-baker.php`. Verify: both functions exist, return Gutenberg block markup, handle empty args gracefully.
- [x] **ET2.3** — Rewrite `vbb_bake_hero()` with `switch($data['style'])` producing Style A (current two-column), Style B (centered with overlay), Style C (full-bleed image). Use `vbb_render_cta_button()` where buttons appear. Verify: A/B/C each produce distinct markup with correct CSS classes.
- [x] **ET2.4** — Rewrite `vbb_bake_cta_final()` with `switch($data['style'])` producing Style A (current centered), Style B (split two-column), Style C (card with subtitle). Use both shared helpers. Verify: A/B/C each produce distinct markup.
- [x] **ET2.5** — Rewrite `vbb_bake_testimonials()` with `switch($data['style'])` producing Style A (current stacked quotes), Style B (grid with avatars/rating), Style C (featured + supporting). Use `vbb_render_heading_block()`. Verify: A/B/C each produce distinct markup.
- [x] **ET2.6** — Add A/B/C button-group style selector in `renderBlockSettings()` JS (`admin-pro.js`) after heading field, before block colors. Active button gets `vbb-cc-style-btn--active` class. Verify: selector renders in block settings; clicking highlights the chosen style.
- [x] **ET2.7** — Add auto-rebake confirmation logic after style save in `saveSettings()` callback. On per-page style change: re-bake that page via `POST /pages/{id}/regenerate`. On global: re-bake ALL pages. Cancel reverts by reloading settings from server. Verify: confirmation toast appears; confirm triggers regenerate; cancel reverts style.

