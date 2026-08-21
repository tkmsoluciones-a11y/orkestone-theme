# Design: json-menu-sync — Navigation url_slug Propagation

## Technical Approach

Add an optional `url_slug` text input per primary navigation item in the Agency Hub Navigation & SEO tab. The value flows: form POST → PHP sanitization → JSON builder output → `navigation.primary[].url_slug` → theme importer's existing `vbb_resolve_navigation_page_ids()` resolves it to `kind: 'post-type'` + `object_id`. No importer code change required — only data supply and schema annotation. All fields are optional; existing JSON without `url_slug` is fully backward compatible.

## Architecture Decisions

### Decision 1: Text input (not dropdown) for Page Slug

**Choice**: Plain text input with hint text for WP slug format.
**Alternatives considered**: Dropdown populated from `pages[]` (would require AJAX/state sync); select with page titles (doesn't match slug lookup key).
**Rationale**: The importer resolves by slug string via `page_id_map`. A text input matching WP's slug conventions is the simplest correct surface. Agencies entering `/servicios` as the URL already know the slug is `servicios`.

### Decision 2: No validator logic change

**Choice**: `vbb_validate_vertical_config()` is left unchanged.
**Alternatives considered**: Add per-item schema validation in PHP.
**Rationale**: The existing validator only checks top-level key presence; it has no per-item schema enforcement. Adding `url_slug` to any item does not affect the current shallow check. The spec explicitly marks the field as optional and non-breaking.

### Decision 3: URL field retained alongside url_slug in form and JSON

**Choice**: Form always keeps `url` input visible; `url_slug` is an additional parallel field.
**Alternatives considered**: Replace `url` with `url_slug` when page is selected.
**Rationale**: Maintains backward compatibility with existing importer fallback. When `url_slug` resolves → output uses `kind: 'post-type'`; when it doesn't resolve, the importer reads the original `url` and emits `kind: 'custom'` (see Data Flow §fallback). No data is lost.

### Decision 4: url_slug not rendered for custom-only mode (form-side hint)

**Choice**: Page Slug input is always rendered in the form for every item (simplifies JS server-side symmetry). The "hide for custom links" spec requirement is a future enhancement once `kind` is selectable in the form.
**Alternatives considered**: Conditional render based on `kind` field.
**Rationale**: Current form has no `kind` selector — all items are implicitly custom. Adding conditional logic now adds complexity without blocking the primary flow. Note this for decoupling in a follow-up.

## Data Flow

```
Agency Hub Navigation tab                  Theme Importer
─────────────────────────────              ─────────────────────────────────
Form row per item:
  label  ──────────────────────────────►  "About"
  url    ──────────────────────────────►  "/about"    (fallback if no url_slug)
  url_slug (new, optional) ───────────►  "about-us"  (primary link signal)

build_navigation():
  {label, url}            ──preserved──►
  + {url_slug?: string}   ──added when non-empty──►

JSON: navigation.primary[i]:
  {label, url, url_slug?, kind?, id?}

Importer two-pass resolve (NO CODE CHANGE):

  vbb_generate_vertical_navigation(page_id_map)
    │
    ├─ vbb_resolve_navigation_page_ids(raw_items, page_id_map)
    │     │
    │     ├─ url_slug present AND in page_id_map
    │     │     → kind: 'post-type', id: <page_id>, url: <permalink>
    │     │
    │     ├─ url_slug absent OR slug not in page_id_map
    │     │     → kind: 'custom', url: <original url or '#<slug>'>
    │     │
    │     └─ label preserved in all branches
    │
    └─ vbb_build_navigation_markup(resolved_items)
          → wp_navigation CPT block content
```

**Fallback behavior**: if `url_slug` is absent (empty field) OR the slug key is not in `page_id_map` (page deleted after selection), importer outputs `kind: 'custom'` and emits the original `url` from the JSON item. This is a safe no-op — agencies see their original URL preserved.

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `orkestone-agency-hub/templates/admin-briefing-form.php` | Modify | Add `url_slug` text input in each nav-item row;渲染 existing items with `url_slug` prefilled from saved data |
| `orkestone-agency-hub/assets/js/briefing-form.js` | Modify | Add `url_slug` field to dynamically-inserted nav-item HTML template |
| `orkestone-agency-hub/includes/class-briefing-form.php` | Modify | Capture `url_slug` in `sanitize_form_data()` → `$data['navigation'][]` |
| `orkestone-agency-hub/includes/class-json-builder.php` | Modify | `build_navigation()`: read `$item['url_slug']`, conditionally add to output when non-empty |
| `orkestone-theme/inc/vertical-validator.php` | No change | Existing shallow validation already accepts optional extra keys |
| `orkestone-theme/inc/vertical-importer.php` | No change | `vbb_resolve_navigation_page_ids()` already exists; data flow documented here |

## Interfaces / Contracts

**Navigation item — form data** (PHP `$_POST` → sanitized):
```php
// orke_menu_items[index] after sanitize_form_data()
[
    'label'    => string,   // required, sanitize_text_field
    'url'      => string,   // required, sanitize_url
    'url_slug' => string,   // OPTIONAL, sanitize_title (new)
]
```

**Navigation item — JSON output** (`navigation.primary[i]`):
```json
{
  "label": "Servicios",
  "url": "/servicios",
  "url_slug": "servicios",
  "kind": "post-type",
  "id": 42
}
```

`kind` and `id` are set by the importer at resolution time, not by the form or builder. When present in loaded JSON (e.g., re-import scenario), the builder passes them through unchanged from `$item['kind']` / `$item['id']`.

**Schema annotation** (to add to `default.json` and `vbb_validate_vertical_config()` docblock):
```
navigation.primary[i].url_slug?: string  // optional WP page slug for post-type link resolution
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|--------------|----------|
| Unit | `build_navigation()` outputs `url_slug` when present | Unit test: pass `['label'=>'X','url'=>'/x','url_slug'=>'x']` → assert `url_slug` key in output |
| Unit | `build_navigation()` omits `url_slug` when empty | Unit test: pass item with empty `url_slug` → assert key absent from output |
| Unit | `sanitize_form_data()` captures `url_slug` | Assert `$data['navigation'][0]['url_slug']` matches POST value |
| Integration | JSON round-trip: form → builder → validator | Generate JSON with `url_slug`, pass through `vbb_validate_vertical_config()`, expect `true` |
| Integration | `kind: 'post-type'` outcome | Manually inject JSON with `url_slug: 'servicios'`, run importer with matching `page_id_map`, assert `wp_navigation` entry has `kind: 'post-type'` and correct `object_id` |
| Regression | JSON without `url_slug` still imports correctly | Existing `default.json` (no `url_slug`) → full import → all items `kind: 'custom'` |
| E2E | Page Slug input visible and prefills | Briefing form loads existing config with `url_slug` → field shows value; submit empty → key absent in stored JSON |

## Migration / Rollout

No migration required. The change is additive and optional at every layer:
- Existing `orke_configuration` posts without `url_slug` are unaffected
- Existing verticals without `url_slug` import identically to before
- No database columns or new settings added
- Rollback: revert the three file changes (form, JS, builder) — no residual data

## Open Questions

- [ ] **Form-side kind selector**: The spec says "hide for custom links" but the form has no `kind` selector yet. Currently the Page Slug input is always visible. Decoupling `kind` from the form and adding a link-type toggle is a logical follow-up for a future iteration (no blocker for spec 4).
- [ ] **WP slug format enforcement**: The spec hints at WP slug format (`^[a-z0-9]+(?:-[a-z0-9]+)*$`, no leading/trailing slash) but no validator change is required. Should the PHP sanitization layer normalize the value (currently `sanitize_title` handles this) or should client-side JS enforce the pattern inline?