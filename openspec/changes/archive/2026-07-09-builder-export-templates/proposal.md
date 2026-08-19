# Proposal: Builder Export & Template Management

## Intent

Enable the "round-trip" of site configurations. Users can export their current customized site as JSON (global settings + per-page overrides) and choose different visual styles per section. Export data must be importable into a fresh WP install, preserving all overrides and style selections.

## Scope

### In Scope
1. **REST export endpoint** (`GET /orkestone/v1/export`) — returns global settings + per-page overrides + active profile in a schema compatible with vertical JSON.
2. **Section style data model** — `style` field per block (default `'A'`) in global settings, propagated to per-page deltas.
3. **Baker style dispatch** — internal switch on `$data['style']` inside existing baker functions, extract shared helpers for common sub-patterns.
4. **Style selector UI** — button-group control in `renderBlockSettings()`, auto-triggers re-bake on change.
5. **Import parity** — extend existing import handler to consume the expanded export format.

### Out of Scope
- Template files approach (Approach C from exploration) — deferred until style variants exceed 3 per section.
- Bulk export of all WP content — site config only, not pages/posts content.

## Capabilities

### New Capabilities
- `data-export`: Full-site JSON export via REST with global settings, per-page overrides, and active profile.
- `section-styles`: Style variant field (`style: "A"|"B"|"C"`) per block in settings data model.
- `baker-dispatch`: Baker function internal style dispatch with shared rendering helpers.
- `style-selector-ui`: Command Center button-group style selector with auto-rebake trigger.
- `import-expanded`: Extended import handler accepting the new export format with `pageOverrides`.

### Modified Capabilities
None — these are all new capabilities.

## Approach

Two-stage delivery via chained PRs (review budget: 800 lines).

**Stage 1 — Export** (~300 lines):
- Add `GET /orkestone/v1/export` REST route in `pro-rest-api.php`.
- Build JSON document consolidating global settings, per-page overrides, active profile.
- Add schema envelope (`exportedAt`, `schemaVersion: "1.0.0"`, `theme`).
- Add "Export" button in Command Center toolbar. JS fetches blob → triggers `<a>` download.
- Extend import handler to parse `pageOverrides`.

**Stage 2 — Styles** (~500 lines):
- Add `style` field to `vbb_pro_default_settings()` defaulting to `'A'`.
- Extend sanitization in `vbb_pro_sanitize_settings()`.
- Modify 2–3 high-impact baker functions (hero, cta-final, testimonials) with internal `switch ($style)` dispatch.
- Extract shared helpers: `vbb_render_cta_button()`, `vbb_render_heading_block()`.
- Add button-group style selector in `renderBlockSettings()` (JS).
- Wire style change → auto-rebake via existing regenerate endpoint.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/pro-rest-api.php` | Modified | Add export route + optional style PATCH |
| `inc/pro-admin.php` | Modified | Extend import handler for pageOverrides |
| `inc/pro-settings.php` | Modified | Add `style` to defaults + sanitization |
| `inc/block-baker.php` | Modified | Style dispatch in baker functions + shared helpers |
| `assets/js/admin-pro.js` | Modified | Export button + style selector UI |
| `config/verticals/*.json` | Reference | Export schema mirrors this structure |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| JSON format mismatch with vertical schema | Low | Export uses same top-level shape + `pageOverrides` key + `"customized": true` flag |
| Export size with many pages | Low | Per-page deltas only (~2KB/page). 500 pages ≈ 1MB — acceptable for JSON download |
| Missing style for section → broken output | Medium | Default to `'A'` everywhere. Sanitization rejects invalid style values |
| Auto-rebake on style change overwrites manual tweaks | Medium | Show confirmation dialog: "Style change will regenerate this page. Any manual edits will be lost." |
| Orphaned page IDs in export | Low | Filter out deleted pages during export build |

## Rollback Plan

**Stage 1**: Remove REST route registration and "Export" button. Revert `pro-rest-api.php` and `admin-pro.js`. Import handler falls back to existing format.

**Stage 2**: Remove `style` field from defaults/sanitization. Revert baker functions to original dispatch (style param ignored). Remove style selector JS. Profiles without `style` field merge safely.

## Dependencies

- Active theme: OrkestOne (no external deps).
- Prior changes `builder-completion` and `builder-visual-polish` are archived and provide the foundation this extends.

## Success Criteria

- [ ] A user can export their site via the Command Center "Export" button and receive a valid JSON file.
- [ ] The exported JSON can be imported into a fresh WP install, restoring all global settings and per-page overrides.
- [ ] Each section block has a `style` field defaulting to `'A'` in settings.
- [ ] Changing a block's style to `'B'` or `'C'` produces different baked markup on regeneration.
- [ ] The style selector appears in `renderBlockSettings()` and triggers re-bake on change.
