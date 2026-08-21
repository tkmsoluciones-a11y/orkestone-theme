# Proposal: json-menu-sync

## Intent

`navigation.primary` items in Hub-generated vertical JSON currently carry only `label` and `url`. The importer's two-pass resolver (`vbb_resolve_navigation_page_ids()`) falls back to `kind: 'custom'` URLs when no page reference is resolvable, so the resulting `wp_navigation` CPT entries contain hard-coded URLs instead of `kind: 'post-type'` links with resolved page IDs. Agencies cannot set navigation targets through the Hub briefing form — the round-trip Form → JSON → Import → wp_navigation breaks at the navigation step.

## Scope

### In Scope
- Add optional `url_slug` page picker per nav item in the Agency Hub Navigation & SEO tab
- `JSON_Builder::build_navigation()` passes `url_slug`, `kind`, and `id` through to JSON output
- Vertical schema annotates `url_slug` as optional string per navigation item
- Importer `vbb_resolve_navigation_page_ids()` resolves `url_slug` → page ID via existing `page_id_map`; no importer code changes required

### Out of Scope
- `wp_navigation` → JSON reverse export (round-trip export)
- Dual `wp_navigation` post conflict resolution on re-import
- `kind: 'page'` (Pro sync) vs `kind: 'post-type'` (import) standardization across both flows

## Capabilities

> This section is the CONTRACT between proposal and specs phases.
> Research `openspec/specs/` was performed before filling this section.

### New Capabilities
<!-- Each becomes a new openspec/specs/<name>/spec.md -->
- `json-menu-sync`: Navigation `url_slug` propagation from Agency Hub briefing form through JSON builder to importer page-ID resolution — closes the gap that causes `kind: 'custom'` fallback

### Modified Capabilities
<!-- Existing capabilities whose spec-level behavior is changing -->
- `agency-hub`: REQ-AH3 Navigation & SEO tab field set expands from `{label, url}` to `{label, url, url_slug, kind?, id?}`; REQ-AH5 JSON Builder output gains optional `url_slug` per navigation item

## Approach

1. **Hub form (agency-hub)**: Extend the Navigation tab nav-item row to include an optional page-slug dropdown (list of site pages). When selected, store `url_slug` alongside existing `label`/`url` fields.
2. **JSON builder**: `build_navigation()` reads the new field and adds `url_slug` to each item object; existing `kind`/`id` are passed through if already set.
3. **Schema annotation**: `vbb_validate_vertical_config()` accepts `url_slug` as an optional string inside each `navigation[].primary[]` item.
4. **Importer resolution**: `vbb_resolve_navigation_page_ids()` already iterates navigation items and resolves slugs/IDs via `page_id_map` — no implementation change needed; only the `url_slug` source data was missing.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `agency-hub/assets/js/form-navigation.js` | Modified | Add `url_slug` dropdown per nav item row |
| `agency-hub/class-json-builder.php` | Modified | Pass `url_slug`, `kind`, `id` through in `build_navigation()` |
| `inc/vertical-validator.php` | Modified | Accept optional `url_slug` in nav-item schema |
| `inc/vertical-importer.php` | Unchanged | `vbb_resolve_navigation_page_ids()` already handles resolution; data supply fix only |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Page slug picker returns slugs that don't match `page_id_map` keys | Low | Importer silently falls back to `kind: 'custom'` — no-op if page was deleted after selection |
| Existing JSON without `url_slug` breaks validator | Low | Field is optional; validator accepts items with or without it |
| Hub form UI complexity increases nav tab density | Med | Use collapsible row expansion; page picker only visible when row is expanded |

## Rollback Plan

- Revert `agency-hub` form changes → nav items return to `{label, url}` only
- Revert JSON builder changes → no `url_slug` emitted
- Revert validator change → `url_slug` key silently ignored on import
- No database migration required; no data loss

## Dependencies

- Existing `vbb_resolve_navigation_page_ids()` resolution path (no new dependency)

## Success Criteria

- [ ] Hub Navigation tab renders a page-slug picker per nav item (optional)
- [ ] Generated JSON includes `url_slug` on navigation items where a page was selected
- [ ] `vbb_validate_vertical_config()` accepts JSON with and without `url_slug`
- [ ] After import, `wp_navigation` CPT entries for nav items with `url_slug` have `kind: 'post-type'` with correct `page_id`
- [ ] Nav items without `url_slug` continue to produce `kind: 'custom'` URLs (backward compat)
- [ ] ZERO regressions on manual vertical import via Command Center